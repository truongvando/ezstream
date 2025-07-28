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

        # Enhanced error tracking
        self.process_errors: Dict[int, List[str]] = {}
        self.restart_attempts: Dict[int, int] = {}

        logging.info(f"🔧 Enhanced Process manager initialized (max monitors: {self.config.monitor_thread_pool_size})")
    
    def start_ffmpeg(self, stream_id: int, input_path: str, stream_config: Dict[str, Any], 
                     rtmp_endpoint: Optional[str] = None) -> bool:
        """Start FFmpeg process for stream"""
        try:
            with self.process_lock:
                # Check if already running
                if stream_id in self.processes:
                    logging.warning(f"Stream {stream_id} already has a running process")
                    return False

                # Quick file validation
                if not self._validate_video_file(input_path, stream_id):
                    return False

                # Use default endpoint if not provided
                if rtmp_endpoint is None:
                    rtmp_endpoint = self.config.get_rtmp_endpoint(stream_id)
                
                with PerformanceTimer(f"FFmpeg Start (Stream {stream_id})"):
                    # Build optimized FFmpeg command
                    ffmpeg_cmd = self._build_ffmpeg_command(input_path, rtmp_endpoint)

                    logging.info(f"🎬 Starting FFmpeg for stream {stream_id} (copy mode)")
                    logging.debug(f"Command: {' '.join(ffmpeg_cmd)}")

                    # Start process with optimized settings
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

                    # Report success to Laravel via status reporter
                    if self.status_reporter:
                        self.status_reporter.publish_stream_status(
                            stream_id, 'STREAMING', f'FFmpeg started successfully (PID: {process.pid})'
                        )

                    # Reset error tracking
                    self.process_errors.pop(stream_id, None)
                    self.restart_attempts.pop(stream_id, None)

                    logging.info(f"✅ Enhanced FFmpeg started for stream {stream_id} (PID: {process.pid}) → {rtmp_endpoint}")
                    return True
                    
        except Exception as e:
            logging.error(f"❌ Failed to start enhanced FFmpeg for stream {stream_id}: {e}")

            # Track error
            if stream_id not in self.process_errors:
                self.process_errors[stream_id] = []
            self.process_errors[stream_id].append(str(e))

            # Report failure to Laravel via status reporter

            if self.status_reporter:
                self.status_reporter.publish_stream_status(
                    stream_id, 'ERROR', f'Failed to start enhanced FFmpeg: {str(e)}'
                )
            return False
    
    def stop_ffmpeg(self, stream_id: int, reason: str = "manual") -> bool:
        """Enhanced FFmpeg process stop with state machine integration"""
        try:
            with self.process_lock:
                if stream_id not in self.processes:
                    logging.warning(f"No FFmpeg process found for stream {stream_id}")

                    # Report that stream is already stopped
                    if self.status_reporter:
                        self.status_reporter.publish_stream_status(
                            stream_id, 'STOPPED', f'No process found - {reason}'
                        )

                    return True

                process_info = self.processes[stream_id]

                # Report stopping status
                if self.status_reporter:
                    self.status_reporter.publish_stream_status(
                        stream_id, 'STOPPING', f'Stopping FFmpeg process (PID: {process_info.process.pid})'
                    )

                # CRITICAL: Mark this process as being manually stopped
                # This prevents the monitor from treating SIGTERM exit as a crash
                process_info.is_manual_stop = True
                logging.info(f"🛑 Enhanced stop for stream {stream_id} (reason: {reason})")

                with PerformanceTimer(f"Enhanced FFmpeg Stop (Stream {stream_id})"):
                    success = self._enhanced_graceful_shutdown(process_info, reason)

                    # Remove from tracking
                    del self.processes[stream_id]

                    # Report final status
                    if self.status_reporter:
                        if success:
                            self.status_reporter.publish_stream_status(
                                stream_id, 'STOPPED', f'FFmpeg stopped successfully - {reason}'
                            )
                        else:
                            self.status_reporter.publish_stream_status(
                                stream_id, 'ERROR', f'Failed to stop FFmpeg - {reason}'
                            )

                    if success:
                        logging.info(f"✅ Enhanced FFmpeg stopped for stream {stream_id} (reason: {reason})")
                        if self.status_reporter and reason in ["manual", "command"]:
                            self.status_reporter.publish_stream_status(
                                stream_id, 'STOPPED', 'Stream stopped by user'
                            )
                    else:
                        logging.error(f"❌ Failed to stop enhanced FFmpeg for stream {stream_id}")

                    return success

        except Exception as e:
            logging.error(f"❌ Error stopping enhanced FFmpeg for stream {stream_id}: {e}")

            # Report error
            if self.status_reporter:
                self.status_reporter.publish_stream_status(
                    stream_id, 'ERROR', f'Error stopping FFmpeg: {str(e)}'
                )

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
    
    def _enhanced_graceful_shutdown(self, process_info: ProcessInfo, reason: str) -> bool:
        """Enhanced graceful shutdown with better error handling"""
        try:
            process = process_info.process
            stream_id = process_info.stream_id

            if not process or process.poll() is not None:
                logging.info(f"Stream {stream_id}: Process already terminated")
                return True

            old_pid = process.pid
            logging.info(f"Stream {stream_id}: Enhanced graceful shutdown (PID: {old_pid}, reason: {reason})")

            # Try graceful shutdown via stdin 'q' command first
            try:
                if process.stdin and not process.stdin.closed:
                    process.stdin.write(b'q\n')
                    process.stdin.flush()
                    logging.info(f"Stream {stream_id}: Sent 'q' command to FFmpeg stdin")

                    try:
                        process.wait(timeout=3)
                        logging.info(f"Stream {stream_id}: FFmpeg stopped gracefully via 'q' command")
                        return True
                    except subprocess.TimeoutExpired:
                        logging.info(f"Stream {stream_id}: 'q' command timeout, trying SIGINT")

            except (BrokenPipeError, OSError, ValueError) as e:
                logging.info(f"Stream {stream_id}: Cannot use stdin method ({e}), trying SIGINT")

            # Fallback to SIGINT
            import signal
            import os
            try:
                os.kill(process.pid, signal.SIGINT)

                try:
                    process.wait(timeout=self.config.graceful_shutdown_timeout)
                    logging.info(f"Stream {stream_id}: FFmpeg stopped gracefully via SIGINT (PID: {old_pid})")

                except subprocess.TimeoutExpired:
                    logging.warning(f"Stream {stream_id}: FFmpeg didn't stop gracefully, force killing (PID: {old_pid})")

                    if kill_process_tree(old_pid, timeout=self.config.force_kill_timeout):
                        logging.info(f"Stream {stream_id}: FFmpeg force killed (PID: {old_pid})")
                    else:
                        logging.error(f"Stream {stream_id}: Failed to kill FFmpeg process (PID: {old_pid})")
                        return False

            except ProcessLookupError:
                logging.info(f"Stream {stream_id}: Process already terminated")
            except Exception as e:
                logging.error(f"Stream {stream_id}: Error during signal handling: {e}")
                return False

            # Cancel monitoring future
            if process_info.monitor_future:
                process_info.monitor_future.cancel()

            return True

        except Exception as e:
            logging.error(f"Error during enhanced graceful shutdown of stream {process_info.stream_id}: {e}")
            return False

    def stop_all(self):
        """Enhanced stop all FFmpeg processes"""
        logging.info("🛑 Enhanced shutdown: stopping all FFmpeg processes...")

        with self.process_lock:
            stream_ids = list(self.processes.keys())

        logging.info(f"🛑 Stopping {len(stream_ids)} processes")

        for stream_id in stream_ids:
            try:
                self.stop_ffmpeg(stream_id, "shutdown")
            except Exception as e:
                logging.error(f"❌ Error stopping stream {stream_id} during shutdown: {e}")

        # Shutdown monitor executor safely
        try:
            self.monitor_executor.shutdown(wait=True, timeout=30)
            logging.info("✅ Enhanced process manager stopped")
        except Exception as e:
            logging.error(f"❌ Error shutting down monitor executor: {e}")
    
    def _build_ffmpeg_command(self, input_path: str, rtmp_endpoint: str) -> List[str]:
        """Build optimized FFmpeg command - copy mode only for VPS performance"""

        # Optimized copy command based on community best practices
        cmd = [
            'ffmpeg',
            '-hide_banner',
            '-loglevel', 'error',
            '-re',  # Realtime playback
            '-stream_loop', '-1',  # Loop infinitely
            '-i', input_path,
            '-c', 'copy',  # Copy streams (no re-encoding)
            '-f', 'flv',
            '-flvflags', 'no_duration_filesize',  # Better RTMP compatibility
            '-avoid_negative_ts', 'make_zero',  # Fix timestamp issues
            '-fflags', '+genpts',  # Generate presentation timestamps
            rtmp_endpoint
        ]

        # Handle playlist files with optimized concat demuxer
        if input_path.startswith('concat:') or input_path.endswith('.txt'):
            # For playlist files, use concat demuxer with optimizations
            if input_path.startswith('concat:'):
                playlist_file = input_path.replace('concat:', '')
            else:
                playlist_file = input_path

            # Build optimized playlist command
            cmd = [
                'ffmpeg',
                '-hide_banner',
                '-loglevel', 'error',
                '-re',  # Realtime playback
                '-f', 'concat',  # Use concat demuxer
                '-safe', '0',    # Allow unsafe file paths
                '-stream_loop', '-1',  # Loop playlist infinitely
                '-i', playlist_file,
                '-c', 'copy',    # Copy streams
                '-f', 'flv',
                '-flvflags', 'no_duration_filesize',
                '-avoid_negative_ts', 'make_zero',
                '-fflags', '+genpts',
                rtmp_endpoint
            ]

        return cmd



    def _validate_video_file(self, input_path: str, stream_id: int) -> bool:
        """Quick validation of video file for RTMP streaming"""
        try:
            # Quick file existence and size check
            if not os.path.exists(input_path):
                logging.error(f"Stream {stream_id}: File not found: {input_path}")
                return False

            file_size = os.path.getsize(input_path)
            if file_size < 1024:  # Less than 1KB
                logging.error(f"Stream {stream_id}: File too small: {input_path}")
                return False

            # Quick ffprobe check (timeout 5s)
            cmd = ['ffprobe', '-v', 'quiet', '-show_format', input_path]
            result = subprocess.run(cmd, capture_output=True, timeout=5)

            if result.returncode != 0:
                logging.warning(f"Stream {stream_id}: ffprobe failed for {input_path}")
                return False

            logging.info(f"Stream {stream_id}: File validation passed for {input_path}")
            return True

        except subprocess.TimeoutExpired:
            logging.warning(f"Stream {stream_id}: ffprobe timeout for {input_path}")
            return False
        except Exception as e:
            logging.warning(f"Stream {stream_id}: Validation error for {input_path}: {e}")
            return False

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

            except (BrokenPipeError, OSError, ValueError) as e:
                # ValueError can occur if stdin is closed during shutdown
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
            elif termination_reason == "crash":
                # Analyze error type first
                error_message = self._analyze_ffmpeg_error(stderr.decode('utf-8') if stderr else '', return_code)
                error_type = None
                if error_message:
                    # Extract error type from error message
                    if 'FILE_NOT_FOUND' in error_message:
                        error_type = 'FILE_NOT_FOUND'
                    elif 'PERMISSION_ERROR' in error_message:
                        error_type = 'PERMISSION_ERROR'
                    elif 'NGINX_DOWN' in error_message:
                        error_type = 'NGINX_DOWN'
                    elif 'CORRUPTED_FILE' in error_message:
                        error_type = 'CORRUPTED_FILE'
                    elif 'OUT_OF_MEMORY' in error_message:
                        error_type = 'OUT_OF_MEMORY'
                    elif 'TIMEOUT' in error_message:
                        error_type = 'TIMEOUT'
                    else:
                        error_type = 'UNKNOWN_ERROR'

                # Request restart decision from Laravel instead of auto-restart
                logging.warning(f"Stream {stream_id} crashed (code: {return_code}, error: {error_type}), requesting restart decision from Laravel...")

                # Get stderr for detailed error info
                stderr_text = stderr.decode('utf-8') if stderr else None

                # Send restart request to Laravel
                self._request_restart_decision(
                    stream_id=stream_id,
                    error_type=error_type,
                    exit_code=return_code,
                    stderr=stderr_text
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

    def _request_restart_decision(self, stream_id: int, error_type: str = None, exit_code: int = None, stderr: str = None) -> None:
        """Request Laravel to decide whether to restart stream"""
        from stream_manager import get_stream_manager
        stream_manager = get_stream_manager()

        if not stream_manager or stream_id not in stream_manager.streams:
            logging.info(f"Stream {stream_id} not in stream manager, no restart request needed")
            return

        stream_info = stream_manager.streams[stream_id]
        crash_count = getattr(stream_info, 'crash_count', 0) + 1

        # Update crash count
        stream_info.crash_count = crash_count

        # Send restart request to Laravel
        if self.status_reporter:
            self.status_reporter.publish_restart_request(
                stream_id=stream_id,
                reason=f"FFmpeg crashed with exit code {exit_code}" if exit_code else error_type,
                crash_count=crash_count,
                last_error=stderr[:500] if stderr else None,
                error_type=error_type
            )

        logging.warning(f"🔄 Stream {stream_id} crashed (#{crash_count}), requesting restart decision from Laravel")



    def _analyze_ffmpeg_error(self, stderr_output: str, return_code: int) -> Optional[str]:
        """Enhanced FFmpeg error analysis with better categorization"""
        stderr_lower = stderr_output.lower()

        # Check for manual termination signals first
        if return_code in [-9, -15, -2]:  # SIGKILL, SIGTERM, SIGINT from our commands
            return None  # Don't report as error for manual stops

        # Permanent errors (no auto-restart)
        if 'no such file or directory' in stderr_lower:
            return '❌ [FILE_NOT_FOUND] Video file missing or path incorrect'
        elif 'permission denied' in stderr_lower:
            return '❌ [PERMISSION_ERROR] Cannot access video file - check permissions'
        elif 'invalid data found when processing input' in stderr_lower:
            return '❌ [CORRUPTED_FILE] Video file is corrupted and unreadable'
        elif 'out of memory' in stderr_lower or return_code == 137:  # OOM kill
            return '❌ [OUT_OF_MEMORY] System ran out of memory - need more RAM or reduce concurrent streams'
        elif 'codec not currently supported' in stderr_lower:
            return '❌ [UNSUPPORTED_CODEC] Video codec not supported - try re-encoding to H.264'
        elif 'moov atom not found' in stderr_lower:
            return '❌ [INCOMPLETE_FILE] Video file incomplete or corrupted - re-download required'
        elif 'end of file' in stderr_lower or 'premature' in stderr_lower:
            return '❌ [TRUNCATED_FILE] Video file truncated during download'

        # Temporary errors (can auto-restart)
        elif 'connection refused' in stderr_lower and 'rtmp' in stderr_lower:
            return '❌ [NGINX_DOWN] Cannot connect to local Nginx RTMP - Nginx may be down'
        elif 'connection timed out' in stderr_lower or 'timeout' in stderr_lower:
            return '❌ [TIMEOUT] Network timeout - retrying...'
        elif 'network is unreachable' in stderr_lower:
            return '❌ [NETWORK_ERROR] Network connectivity issue - retrying...'
        elif 'server returned 4' in stderr_lower or 'rtmp' in stderr_lower:
            return '❌ [RTMP_ERROR] RTMP server error - retrying...'
        elif 'protocol not found' in stderr_lower:
            return '❌ [PROTOCOL_ERROR] Network protocol error - retrying...'

        # Exit code 255 analysis
        elif return_code == 255:
            if 'invalid argument' in stderr_lower:
                return '❌ [INVALID_PARAMS] Invalid FFmpeg parameters - check video format'
            else:
                return '❌ [FFMPEG_CRASH] FFmpeg crashed (255) - retrying...'

        # Other non-zero exit codes
        elif return_code != 0:
            return f'❌ [UNKNOWN_ERROR] FFmpeg failed with exit code {return_code} - retrying...'

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
