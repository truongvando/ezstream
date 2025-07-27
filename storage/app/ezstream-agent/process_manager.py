#!/usr/bin/env python3
"""
EZStream Agent Process Manager
Manages FFmpeg processes with proper lifecycle and monitoring
"""

import os
import time
import logging
import threading
import subprocess
from typing import Dict, Optional, List, Any
from concurrent.futures import ThreadPoolExecutor, Future
from dataclasses import dataclass

from config import get_config
from utils import PerformanceTimer, kill_process_tree
from status_reporter import get_status_reporter


@dataclass
class ProcessInfo:
    """Information about a running FFmpeg process"""
    process: subprocess.Popen
    stream_id: int
    input_path: str
    rtmp_endpoint: str
    start_time: float
    monitor_future: Optional[Future] = None


class ProcessManager:
    """Manages FFmpeg processes with proper lifecycle control"""
    
    def __init__(self):
        self.config = get_config()
        self.status_reporter = get_status_reporter()
        
        # Process tracking
        self.processes: Dict[int, ProcessInfo] = {}
        self.process_lock = threading.RLock()
        
        # Thread pool for process monitoring
        self.monitor_executor = ThreadPoolExecutor(
            max_workers=self.config.monitor_thread_pool_size,
            thread_name_prefix="ProcessMonitor"
        )
        
        logging.info(f"🔧 Process manager initialized (max monitors: {self.config.monitor_thread_pool_size})")
    
    def start_ffmpeg(self, stream_id: int, input_path: str, stream_config: Dict[str, Any], 
                     rtmp_endpoint: Optional[str] = None) -> bool:
        """Start FFmpeg process for stream"""
        try:
            with self.process_lock:
                # Check if already running
                if stream_id in self.processes:
                    logging.warning(f"Stream {stream_id} already has a running process")
                    return False
                
                # Use default endpoint if not provided
                if rtmp_endpoint is None:
                    rtmp_endpoint = self.config.get_rtmp_endpoint(stream_id)
                
                with PerformanceTimer(f"FFmpeg Start (Stream {stream_id})"):
                    # Build FFmpeg command
                    ffmpeg_cmd = self._build_ffmpeg_command(input_path, rtmp_endpoint)
                    
                    logging.info(f"🎬 Starting FFmpeg for stream {stream_id}")
                    logging.debug(f"Command: {' '.join(ffmpeg_cmd)}")
                    
                    # Start process with stdin pipe for graceful shutdown
                    process = subprocess.Popen(
                        ffmpeg_cmd,
                        stdout=subprocess.DEVNULL,
                        stderr=subprocess.PIPE,
                        stdin=subprocess.PIPE,  # Enable stdin for 'q' command
                        preexec_fn=os.setsid  # Create new process group
                    )
                    
                    # Create process info
                    process_info = ProcessInfo(
                        process=process,
                        stream_id=stream_id,
                        input_path=input_path,
                        rtmp_endpoint=rtmp_endpoint,
                        start_time=time.time()
                    )
                    
                    # Start monitoring
                    monitor_future = self.monitor_executor.submit(
                        self._monitor_process, process_info
                    )
                    process_info.monitor_future = monitor_future
                    
                    # Store process info
                    self.processes[stream_id] = process_info
                    
                    logging.info(f"✅ FFmpeg started for stream {stream_id} (PID: {process.pid}) → {rtmp_endpoint}")
                    return True
                    
        except Exception as e:
            logging.error(f"❌ Failed to start FFmpeg for stream {stream_id}: {e}")
            if self.status_reporter:
                self.status_reporter.publish_stream_status(
                    stream_id, 'ERROR', f'Failed to start FFmpeg: {str(e)}'
                )
            return False
    
    def stop_ffmpeg(self, stream_id: int, reason: str = "manual") -> bool:
        """Stop FFmpeg process gracefully"""
        try:
            with self.process_lock:
                if stream_id not in self.processes:
                    logging.warning(f"No FFmpeg process found for stream {stream_id}")
                    return True

                process_info = self.processes[stream_id]

                # CRITICAL: Mark this process as being manually stopped
                # This prevents the monitor from treating SIGTERM exit as a crash
                process_info.is_manual_stop = True
                logging.info(f"🛑 Marked stream {stream_id} for manual stop (reason: {reason})")
                
                with PerformanceTimer(f"FFmpeg Stop (Stream {stream_id})"):
                    success = self._graceful_shutdown(process_info)
                    
                    # Remove from tracking
                    del self.processes[stream_id]
                    
                    if success:
                        logging.info(f"✅ FFmpeg stopped for stream {stream_id} (reason: {reason})")
                        if self.status_reporter and reason in ["manual", "command"]:
                            self.status_reporter.publish_stream_status(
                                stream_id, 'STOPPED', 'Stream stopped by user'
                            )
                    else:
                        logging.error(f"❌ Failed to stop FFmpeg for stream {stream_id}")
                    
                    return success
                    
        except Exception as e:
            logging.error(f"❌ Error stopping FFmpeg for stream {stream_id}: {e}")
            return False
    
    def restart_ffmpeg(self, stream_id: int, new_input_path: str, new_config: Dict[str, Any]) -> bool:
        """Restart FFmpeg with new configuration"""
        try:
            logging.info(f"🔄 Restarting FFmpeg for stream {stream_id}")
            
            # Stop current process
            if not self.stop_ffmpeg(stream_id, "restart"):
                logging.error(f"Failed to stop current FFmpeg for stream {stream_id}")
                return False
            
            # Wait for system cleanup
            time.sleep(self.config.system_cleanup_wait)
            
            # Start with new config
            return self.start_ffmpeg(stream_id, new_input_path, new_config)
            
        except Exception as e:
            logging.error(f"❌ Error restarting FFmpeg for stream {stream_id}: {e}")
            return False
    
    def get_process_info(self, stream_id: int) -> Optional[ProcessInfo]:
        """Get process information for stream"""
        with self.process_lock:
            return self.processes.get(stream_id)
    
    def get_active_streams(self) -> List[int]:
        """Get list of active stream IDs"""
        with self.process_lock:
            return [
                stream_id for stream_id, info in self.processes.items()
                if info.process.poll() is None
            ]
    
    def stop_all(self):
        """Stop all FFmpeg processes"""
        logging.info("🛑 Stopping all FFmpeg processes...")
        
        with self.process_lock:
            stream_ids = list(self.processes.keys())
        
        for stream_id in stream_ids:
            self.stop_ffmpeg(stream_id, "shutdown")
        
        # Shutdown monitor executor
        self.monitor_executor.shutdown(wait=True)
        logging.info("✅ All FFmpeg processes stopped")
    
    def _build_ffmpeg_command(self, input_path: str, rtmp_endpoint: str) -> List[str]:
        """Build simple FFmpeg command - keep it simple for 99.9% uptime"""

        # Base command
        base_cmd = [
            'ffmpeg',
            '-hide_banner',
            '-loglevel', 'error',
            '-re',  # Realtime playback
        ]

        # Handle input types
        if input_path.startswith('concat:'):
            # Playlist with infinite loop
            playlist_file = input_path.replace('concat:', '')
            cmd = base_cmd + [
                '-f', 'concat',
                '-safe', '0',
                '-stream_loop', '-1',  # Loop playlist infinitely
                '-i', playlist_file,
            ]
        else:
            # Single file with infinite loop
            cmd = base_cmd + [
                '-stream_loop', '-1',  # Loop file infinitely
                '-i', input_path,
            ]

        # Simple output to local Nginx RTMP
        cmd.extend([
            '-c', 'copy',  # Copy codecs (fastest, most stable)
            '-f', 'flv',
            rtmp_endpoint
        ])

        return cmd
    
    def _graceful_shutdown(self, process_info: ProcessInfo) -> bool:
        """Gracefully shutdown FFmpeg process"""
        try:
            process = process_info.process
            stream_id = process_info.stream_id
            
            # Check if already dead
            if process.poll() is not None:
                logging.info(f"Stream {stream_id}: FFmpeg already stopped")
                return True
            
            old_pid = process.pid
            logging.info(f"Stream {stream_id}: Gracefully shutting down FFmpeg (PID: {old_pid})")

            # Try graceful shutdown via stdin 'q' command first (most graceful)
            try:
                if process.stdin and not process.stdin.closed:
                    process.stdin.write(b'q\n')
                    process.stdin.flush()
                    logging.info(f"Stream {stream_id}: Sent 'q' command to FFmpeg stdin")

                    # Wait a bit for graceful shutdown
                    try:
                        process.wait(timeout=3)
                        logging.info(f"Stream {stream_id}: FFmpeg stopped gracefully via 'q' command")
                        return True
                    except subprocess.TimeoutExpired:
                        logging.info(f"Stream {stream_id}: 'q' command timeout, trying SIGINT")

            except (BrokenPipeError, OSError) as e:
                logging.info(f"Stream {stream_id}: Cannot use stdin method ({e}), trying SIGINT")

            # Fallback to SIGINT (better than SIGTERM for FFmpeg)
            import signal
            import os
            os.kill(process.pid, signal.SIGINT)
            
            # Wait for graceful shutdown
            try:
                process.wait(timeout=self.config.graceful_shutdown_timeout)
                logging.info(f"Stream {stream_id}: FFmpeg stopped gracefully (PID: {old_pid})")
                
            except subprocess.TimeoutExpired:
                logging.warning(f"Stream {stream_id}: FFmpeg didn't stop gracefully, force killing (PID: {old_pid})")
                
                # Force kill if needed
                if kill_process_tree(old_pid, timeout=self.config.force_kill_timeout):
                    logging.info(f"Stream {stream_id}: FFmpeg force killed (PID: {old_pid})")
                else:
                    logging.error(f"Stream {stream_id}: Failed to kill FFmpeg process (PID: {old_pid})")
                    return False
            
            # Cancel monitoring future
            if process_info.monitor_future:
                process_info.monitor_future.cancel()
            
            return True
            
        except Exception as e:
            logging.error(f"Error during graceful shutdown of stream {process_info.stream_id}: {e}")
            return False
    
    def _monitor_process(self, process_info: ProcessInfo):
        """Monitor FFmpeg process in background thread"""
        stream_id = process_info.stream_id
        process = process_info.process
        
        try:
            logging.info(f"🔍 Started monitoring FFmpeg process for stream {stream_id} (PID: {process.pid})")
            
            # Wait for process to end and capture stderr
            stdout, stderr = process.communicate()
            return_code = process.returncode
            
            # Analyze termination reason
            termination_reason = self._analyze_termination(return_code, stderr, process_info)

            if termination_reason == "manual_stop":
                logging.info(f"Stream {stream_id} terminated manually (code: {return_code})")
                if self.status_reporter:
                    self.status_reporter.publish_stream_status(
                        stream_id, 'STOPPED', 'Stream stopped by user'
                    )
                return
            elif termination_reason == "crash" and self._should_auto_restart(stream_id):
                logging.warning(f"Stream {stream_id} crashed (code: {return_code}), attempting auto-restart...")

                # Small delay to avoid race condition with stop command
                time.sleep(1)

                # Re-check if stream should still be restarted after delay
                if self._should_auto_restart(stream_id):
                    if self._attempt_auto_restart(stream_id, process_info):
                        return  # Successfully restarted
                else:
                    logging.info(f"Stream {stream_id} no longer needs restart after delay check")
                    return
                # If restart failed, continue to error handling
            elif termination_reason == "crash":
                # Max restarts exceeded - notify Laravel
                logging.error(f"Stream {stream_id} crashed and max restart attempts exceeded")
                if self.status_reporter:
                    self.status_reporter.publish_stream_status(
                        stream_id, 'ERROR',
                        'FFmpeg crashed multiple times. Auto-restart disabled. Manual intervention required.'
                    )
            
            # Analyze FFmpeg error and provide user-friendly message
            error_message = self._analyze_ffmpeg_error(stderr.decode('utf-8') if stderr else '', return_code)
            
            # Only publish ERROR status if it's a critical error
            if error_message and self.status_reporter:
                self.status_reporter.publish_stream_status(stream_id, 'ERROR', error_message)
            else:
                # Non-critical issue, just log it
                logging.info(f"Stream {stream_id} ended with non-critical issues (code: {return_code})")
                if self.status_reporter:
                    self.status_reporter.publish_stream_status(
                        stream_id, 'STOPPED', f'Stream ended (exit code: {return_code})'
                    )
            
        except Exception as e:
            logging.error(f"Error monitoring FFmpeg process for stream {stream_id}: {e}")
            if self.status_reporter:
                self.status_reporter.publish_stream_status(
                    stream_id, 'ERROR', f'Monitoring error: {str(e)}'
                )
        finally:
            # Clean up process tracking
            with self.process_lock:
                if stream_id in self.processes:
                    del self.processes[stream_id]
            logging.info(f"Cleaned up monitoring for stream {stream_id}")

    def _analyze_termination(self, return_code: int, stderr_output: str, process_info: ProcessInfo) -> str:
        """Enhanced termination analysis with manual stop detection"""

        # Check if this was a manual stop (most reliable method)
        if hasattr(process_info, 'is_manual_stop') and process_info.is_manual_stop:
            logging.info(f"Process marked as manual stop, treating as manual_stop regardless of exit code {return_code}")
            return "manual_stop"

        # Manual stop signals
        if return_code in [-9, -15, -2]:  # SIGKILL, SIGTERM, SIGINT from our stop command
            return "manual_stop"

        # Everything else is a crash that should be restarted
        # (Including code 255, which could be FFmpeg crash or system kill)
        if return_code != 0:
            return "crash"

        return "normal_exit"

    def _should_auto_restart(self, stream_id: int) -> bool:
        """Simple restart check with stream state validation"""
        from stream_manager import get_stream_manager
        stream_manager = get_stream_manager()

        if not stream_manager or stream_id not in stream_manager.streams:
            logging.info(f"Stream {stream_id} not in stream manager, no restart needed")
            return False

        stream_info = stream_manager.streams[stream_id]

        # CRITICAL: Check if stream is being stopped - don't restart if stopping
        from stream_manager import StreamState
        if hasattr(stream_info, 'state') and stream_info.state == StreamState.STOPPING:
            logging.info(f"Stream {stream_id} is in STOPPING state, skipping auto-restart")
            return False

        restart_count = getattr(stream_info, 'restart_count', 0)

        # Auto-restart up to 5 times (FFmpeg crashes are usually temporary)
        return restart_count < 5

    def _attempt_auto_restart(self, stream_id: int, old_process_info: ProcessInfo) -> bool:
        """Simple auto-restart for crashed FFmpeg"""
        try:
            from stream_manager import get_stream_manager
            stream_manager = get_stream_manager()

            if not stream_manager or stream_id not in stream_manager.streams:
                logging.info(f"Stream {stream_id} not found in stream manager, cannot restart")
                return False

            stream_info = stream_manager.streams[stream_id]

            # CRITICAL: Double-check if stream is being stopped
            from stream_manager import StreamState
            if hasattr(stream_info, 'state') and stream_info.state == StreamState.STOPPING:
                logging.info(f"Stream {stream_id} is in STOPPING state, aborting auto-restart")
                return False

            # Track restart attempts
            if not hasattr(stream_info, 'restart_count'):
                stream_info.restart_count = 0
            stream_info.restart_count += 1

            logging.warning(f"FFmpeg crashed for stream {stream_id}, restarting... (attempt #{stream_info.restart_count})")

            # Wait 3 seconds before restart (let system cleanup)
            time.sleep(3)

            # Restart with same config
            success = self.start_ffmpeg(
                stream_id,
                old_process_info.input_path,
                stream_info.config.__dict__,
                old_process_info.rtmp_endpoint
            )

            if success:
                logging.info(f"Stream {stream_id} auto-restarted successfully")
                if self.status_reporter:
                    self.status_reporter.publish_stream_status(
                        stream_id, 'STREAMING',
                        f'Auto-restarted after FFmpeg crash (#{stream_info.restart_count})'
                    )
                return True
            else:
                logging.error(f"Failed to auto-restart stream {stream_id}")
                return False

        except Exception as e:
            logging.error(f"Error auto-restarting stream {stream_id}: {e}")
            return False

    def _analyze_ffmpeg_error(self, stderr_output: str, return_code: int) -> Optional[str]:
        """Analyze FFmpeg error with crash type detection"""
        stderr_lower = stderr_output.lower()

        # Check for manual termination signals first
        if return_code in [-9, -15, -2]:  # SIGKILL, SIGTERM, SIGINT from our commands
            return None  # Don't report as error for manual stops

        # Detailed crash analysis
        if 'no such file or directory' in stderr_lower:
            return '❌ [FILE_NOT_FOUND] Video file missing or path incorrect'
        elif 'permission denied' in stderr_lower:
            return '❌ [PERMISSION_ERROR] Cannot access video file - check permissions'
        elif 'connection refused' in stderr_lower and 'rtmp' in stderr_lower:
            return '❌ [NGINX_DOWN] Cannot connect to local Nginx RTMP - Nginx may be down'
        elif 'invalid data found when processing input' in stderr_lower:
            return '❌ [CORRUPTED_FILE] Video file is corrupted and unreadable'
        elif 'out of memory' in stderr_lower or return_code == 137:  # OOM kill
            return '❌ [OUT_OF_MEMORY] System ran out of memory - need more RAM or reduce concurrent streams'
        elif return_code == 255:
            return '❌ [FFMPEG_CRASH] FFmpeg process crashed unexpectedly - possible system issue'
        elif return_code != 0:
            return f'❌ [UNKNOWN_ERROR] FFmpeg failed with exit code {return_code}'

        return None


# Global process manager instance
_process_manager: Optional[ProcessManager] = None


def init_process_manager() -> ProcessManager:
    """Initialize global process manager"""
    global _process_manager
    _process_manager = ProcessManager()
    return _process_manager


def get_process_manager() -> Optional[ProcessManager]:
    """Get global process manager instance"""
    return _process_manager
