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
    """Information about a running FFmpeg process with enhanced state tracking"""
    process: subprocess.Popen
    stream_id: int
    input_path: str
    rtmp_endpoint: str
    start_time: float
    monitor_future: Optional[Future] = None

    # PREMIUM STATE TRACKING - Comprehensive process lifecycle management
    stop_reason: Optional[str] = None  # 'USER_STOP', 'SYSTEM_STOP', 'CRASH', 'FAST_RESTART', 'FATAL_ERROR'
    stop_initiated_by: Optional[str] = None  # 'LARAVEL', 'AGENT', 'SYSTEM'
    stop_timestamp: Optional[float] = None
    is_restart_pending: bool = False
    restart_count: int = 0

    # Premium monitoring data
    error_history: list = None  # Track error patterns
    performance_metrics: dict = None  # CPU, memory usage
    last_successful_start: Optional[float] = None
    total_runtime: float = 0
    health_score: float = 1.0  # 0.0 to 1.0


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

                    mode = "encoding (libx264)" if self.config.ffmpeg_use_encoding else "copy"
                    logging.info(f"🎬 Starting FFmpeg for stream {stream_id} ({mode} mode)")
                    logging.debug(f"Command: {' '.join(ffmpeg_cmd)}")

                    # Start process with optimized settings
                    process = subprocess.Popen(
                        ffmpeg_cmd,
                        stdout=subprocess.DEVNULL,
                        stderr=subprocess.PIPE,
                        stdin=subprocess.PIPE,  # Enable stdin for 'q' command
                        preexec_fn=os.setsid  # Create new process group
                    )
                    
                    # Create PREMIUM process info with enhanced tracking
                    process_info = ProcessInfo(
                        process=process,
                        stream_id=stream_id,
                        input_path=input_path,
                        rtmp_endpoint=rtmp_endpoint,
                        start_time=time.time(),
                        error_history=[],
                        performance_metrics={},
                        last_successful_start=time.time(),
                        total_runtime=0,
                        health_score=1.0
                    )
                    
                    # Start monitoring
                    monitor_future = self.monitor_executor.submit(
                        self._monitor_process, process_info
                    )
                    process_info.monitor_future = monitor_future

                    # Start resource monitoring for long-running streams
                    resource_future = self.monitor_executor.submit(
                        self._monitor_resources, process_info
                    )
                    process_info.resource_future = resource_future
                    
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

                # CRITICAL: Mark this process with clear stop reason and initiator
                process_info.stop_reason = 'USER_STOP'
                process_info.stop_initiated_by = 'LARAVEL'
                process_info.stop_timestamp = time.time()
                logging.info(f"🛑 Enhanced stop for stream {stream_id} (reason: {reason}, initiated by: LARAVEL)")

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

    def centralized_restart(self, stream_id: int, reason: str, new_input_path: str = None, new_config: Dict[str, Any] = None) -> bool:
        """Centralized restart logic to prevent conflicts between fast restart and user restart"""
        try:
            # Get stream manager for coordination
            from stream_manager import get_stream_manager
            stream_manager = get_stream_manager()

            if not stream_manager:
                logging.error(f"Stream manager not available for restart of stream {stream_id}")
                return False

            # Use stream manager's restart lock to prevent conflicts
            restart_lock = stream_manager._get_restart_lock(stream_id)

            with restart_lock:
                logging.info(f"🔄 [CENTRALIZED_RESTART] Starting restart for stream {stream_id} (reason: {reason})")

                # Check if stream still exists
                if stream_id not in stream_manager.streams:
                    logging.warning(f"Stream {stream_id} no longer exists, skipping restart")
                    return False

                stream_info = stream_manager.streams[stream_id]

                # State priority system: USER_ACTION > FAST_RESTART
                # Import StreamState locally to avoid circular import
                from stream_manager import StreamState
                if (stream_info.state == StreamState.UPDATING and
                    reason.startswith("FAST_RESTART")):
                    logging.info(f"🚦 Stream {stream_id} is being updated by user, skipping fast restart")
                    return False

                # Use existing config if new config not provided
                if new_input_path is None:
                    new_input_path = stream_info.config.playlist_path or stream_info.config.local_files[0]

                if new_config is None:
                    new_config = stream_info.config.__dict__

                # Perform restart
                success = self.restart_ffmpeg(stream_id, new_input_path, new_config)

                if success:
                    logging.info(f"✅ [CENTRALIZED_RESTART] Stream {stream_id} restarted successfully")
                else:
                    logging.error(f"❌ [CENTRALIZED_RESTART] Failed to restart stream {stream_id}")

                return success

        except Exception as e:
            logging.error(f"❌ [CENTRALIZED_RESTART] Error during centralized restart for stream {stream_id}: {e}")
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
        """Build optimized FFmpeg command - encoding mode for stability"""

        if self.config.ffmpeg_use_encoding:
            # Stable encoding mode - regenerates timestamps, prevents DTS issues
            cmd = [
                'ffmpeg',
                '-hide_banner',
                '-loglevel', 'error',
                '-re',  # Realtime playback
                '-stream_loop', '-1',  # Loop infinitely
                '-i', input_path,
                '-c:v', 'libx264',  # Video encoding - stable timestamps
                '-preset', 'fast',   # Fast encoding for VPS performance
                '-crf', '23',        # Good quality/size balance
                '-maxrate', '3000k', # 3Mbps max bitrate
                '-bufsize', '6000k', # 6MB buffer
                '-pix_fmt', 'yuv420p',  # Compatible pixel format
                '-g', '50',          # GOP size for streaming
                '-c:a', 'aac',       # Audio encoding
                '-b:a', '128k',      # 128kbps audio
                '-f', 'flv',
                '-flvflags', 'no_duration_filesize',
                rtmp_endpoint
            ]
        else:
            # Copy mode - faster but may have timestamp issues after 1+ hour
            cmd = [
                'ffmpeg',
                '-hide_banner',
                '-loglevel', 'error',
                '-re',  # Realtime playback
                '-stream_loop', '-1',  # Loop infinitely
                '-i', input_path,
                '-c', 'copy',  # Copy streams (no re-encoding)
                '-f', 'flv',
                '-flvflags', 'no_duration_filesize',
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
            if self.config.ffmpeg_use_encoding:
                # Stable encoding mode for playlists
                cmd = [
                    'ffmpeg',
                    '-hide_banner',
                    '-loglevel', 'error',
                    '-re',  # Realtime playback
                    '-f', 'concat',  # Use concat demuxer
                    '-safe', '0',    # Allow unsafe file paths
                    '-stream_loop', '-1',  # Loop playlist infinitely
                    '-i', playlist_file,
                    '-c:v', 'libx264',  # Video encoding
                    '-preset', 'fast',
                    '-crf', '23',
                    '-maxrate', '3000k',
                    '-bufsize', '6000k',
                    '-pix_fmt', 'yuv420p',
                    '-g', '50',
                    '-c:a', 'aac',
                    '-b:a', '128k',
                    '-f', 'flv',
                    '-flvflags', 'no_duration_filesize',
                    rtmp_endpoint
                ]
            else:
                # Copy mode for playlists
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
        """Enhanced FFmpeg process monitoring with real-time stderr analysis"""
        stream_id = process_info.stream_id
        process = process_info.process

        try:
            logging.info(f"🔍 Started enhanced monitoring for stream {stream_id} (PID: {process.pid})")

            # Real-time stderr monitoring in separate thread
            stderr_monitor = threading.Thread(
                target=self._monitor_stderr_realtime,
                args=(process_info,),
                daemon=True
            )
            stderr_monitor.start()

            # Wait for process to end and capture final stderr
            _, stderr = process.communicate()  # stdout not used
            return_code = process.returncode

            # Check for DTS errors in stderr before analyzing termination
            if stderr:
                stderr_text = stderr.decode('utf-8', errors='ignore')
                dts_error_count = stderr_text.count('Non-monotonous DTS')

                if dts_error_count > 0:
                    logging.warning(f"🚨 Stream {stream_id}: Detected {dts_error_count} DTS errors in stderr")

                    # If many DTS errors and fast restart enabled, trigger fast restart
                    if (self.config.enable_fast_restart and
                        dts_error_count >= self.config.dts_error_threshold):
                        logging.warning(f"⚡ Stream {stream_id}: Too many DTS errors ({dts_error_count}), triggering fast restart...")
                        self._trigger_fast_restart(stream_id, process_info, "DTS_ERRORS")
                        return

            # Analyze termination reason
            termination_reason = self._analyze_termination(return_code, stderr, process_info)

            # Handle different termination reasons with clear actions
            if termination_reason == "user_stop":
                logging.info(f"✅ Stream {stream_id} stopped by user request (code: {return_code})")
                if self.status_reporter:
                    self.status_reporter.publish_stream_status(
                        stream_id, 'STOPPED', 'Stream stopped by user'
                    )
                return
            elif termination_reason == "fast_restart":
                logging.info(f"⚡ Stream {stream_id} terminated for fast restart")
                return
            elif termination_reason == "system_stop":
                logging.warning(f"🔧 Stream {stream_id} stopped by system (code: {return_code})")
                if self.status_reporter:
                    self.status_reporter.publish_stream_status(
                        stream_id, 'STOPPED', 'Stream stopped by system'
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

    def _monitor_stderr_realtime(self, process_info: ProcessInfo):
        """Monitor FFmpeg stderr in real-time để phát hiện lỗi sớm"""
        stream_id = process_info.stream_id
        process = process_info.process

        try:
            import re

            # PREMIUM BUFFER - 96GB RAM allows larger buffers
            recent_lines = []
            max_lines = 1000  # Giữ 1000 dòng gần nhất cho better analysis

            # PREMIUM ERROR PATTERNS - Comprehensive detection
            critical_patterns = {
                'dts_error': r'Non-monotonous DTS|DTS.*discontinuity',
                'pts_error': r'Non-monotonous PTS|PTS.*discontinuity',
                'file_not_found': r'No such file or directory|File not found',
                'permission_denied': r'Permission denied|Access denied',
                'connection_refused': r'Connection refused|Connection reset',
                'connection_timeout': r'Connection timed out|Timeout',
                'out_of_memory': r'Cannot allocate memory|Out of memory',
                'corrupted_file': r'Invalid data found|Corrupt|malformed',
                'rtmp_error': r'RTMP.*error|Failed to connect|RTMP.*timeout',
                'network_error': r'Network.*unreachable|Host.*unreachable',
                'encoding_error': r'Encoding.*failed|Encoder.*error',
                'decoder_error': r'Decoder.*error|Decoding.*failed',
                'stream_error': r'Stream.*error|Invalid.*stream',
                'bitrate_error': r'Bitrate.*too.*high|Buffer.*overflow',
                'keyframe_error': r'Keyframe.*not.*found|No.*keyframe',
                'audio_error': r'Audio.*error|Invalid.*audio',
                'video_error': r'Video.*error|Invalid.*video'
            }

            error_counts = {pattern: 0 for pattern in critical_patterns}

            logging.info(f"📡 Started real-time stderr monitoring for stream {stream_id}")

            # Read stderr line by line
            for line in iter(process.stderr.readline, b''):
                if process.poll() is not None:
                    break

                try:
                    line_str = line.decode('utf-8', errors='ignore').strip()
                    if not line_str:
                        continue

                    # Add to recent lines buffer
                    recent_lines.append(f"{time.time():.3f}: {line_str}")
                    if len(recent_lines) > max_lines:
                        recent_lines.pop(0)  # Remove oldest

                    # Check for critical patterns
                    for pattern_name, pattern in critical_patterns.items():
                        if re.search(pattern, line_str, re.IGNORECASE):
                            error_counts[pattern_name] += 1
                            logging.warning(f"🚨 Stream {stream_id} detected {pattern_name}: {line_str}")

                            # PREMIUM IMMEDIATE ACTION - Smart error handling
                            if pattern_name in ['dts_error', 'pts_error'] and error_counts[pattern_name] >= 2:
                                logging.warning(f"⚡ Stream {stream_id}: {error_counts[pattern_name]} {pattern_name} detected, triggering fast restart...")
                                self._trigger_fast_restart(stream_id, process_info, f"REALTIME_{pattern_name.upper()}_DETECTION")
                                return

                            elif pattern_name in ['connection_timeout', 'rtmp_error'] and error_counts[pattern_name] >= 3:
                                logging.warning(f"🔄 Stream {stream_id}: Network issues detected, triggering restart...")
                                self._trigger_fast_restart(stream_id, process_info, f"REALTIME_NETWORK_ISSUES")
                                return

                            elif pattern_name in ['file_not_found', 'permission_denied'] and error_counts[pattern_name] >= 1:
                                logging.error(f"💀 Stream {stream_id}: Fatal error detected - {pattern_name}")
                                process_info.stop_reason = 'FATAL_ERROR'
                                process_info.stop_initiated_by = 'AGENT'
                                # Let process die naturally, monitor will handle
                                return

                            elif pattern_name in ['out_of_memory', 'corrupted_file'] and error_counts[pattern_name] >= 1:
                                logging.error(f"💀 Stream {stream_id}: Critical error detected - {pattern_name}")
                                process_info.stop_reason = 'FATAL_ERROR'
                                process_info.stop_initiated_by = 'AGENT'
                                return

                            elif pattern_name in ['encoding_error', 'decoder_error'] and error_counts[pattern_name] >= 5:
                                logging.warning(f"🔧 Stream {stream_id}: Encoding issues detected, triggering restart...")
                                self._trigger_fast_restart(stream_id, process_info, f"REALTIME_ENCODING_ISSUES")
                                return

                except Exception as e:
                    logging.debug(f"Error processing stderr line for stream {stream_id}: {e}")
                    continue

            # Store recent lines for post-mortem analysis
            if hasattr(process_info, '__dict__'):
                process_info.recent_stderr_lines = recent_lines
            logging.info(f"📡 Real-time stderr monitoring ended for stream {stream_id}")

        except Exception as e:
            logging.error(f"❌ Real-time stderr monitoring failed for stream {stream_id}: {e}")

    def _update_health_score(self, process_info: ProcessInfo, error_type: str = None):
        """Update health score based on errors and performance"""
        try:
            current_time = time.time()

            # Calculate uptime
            uptime = current_time - process_info.start_time
            process_info.total_runtime = uptime

            # Update error history
            if error_type:
                error_entry = {
                    'timestamp': current_time,
                    'error_type': error_type,
                    'uptime_when_error': uptime
                }
                process_info.error_history.append(error_entry)

                # Keep only last 50 errors
                if len(process_info.error_history) > 50:
                    process_info.error_history = process_info.error_history[-50:]

            # Calculate health score
            recent_errors = [e for e in process_info.error_history
                           if current_time - e['timestamp'] < 3600]  # Last hour

            if len(recent_errors) == 0:
                process_info.health_score = 1.0
            elif len(recent_errors) <= 2:
                process_info.health_score = 0.9
            elif len(recent_errors) <= 5:
                process_info.health_score = 0.7
            elif len(recent_errors) <= 10:
                process_info.health_score = 0.5
            else:
                process_info.health_score = 0.2

            # Log health status
            if process_info.health_score < 0.5:
                logging.warning(f"🏥 Stream {process_info.stream_id} health score: {process_info.health_score:.2f} (unhealthy)")

        except Exception as e:
            logging.error(f"❌ Failed to update health score for stream {process_info.stream_id}: {e}")

    def _trigger_fast_restart(self, stream_id: int, process_info: ProcessInfo, reason: str):
        """Trigger fast restart for DTS errors or other recoverable issues"""
        try:
            logging.info(f"⚡ [FAST_RESTART] Initiating fast restart for stream {stream_id} (reason: {reason})")

            # Mark process for fast restart to avoid confusion in monitor
            process_info.stop_reason = 'FAST_RESTART'
            process_info.stop_initiated_by = 'AGENT'
            process_info.stop_timestamp = time.time()

            # Update health score
            self._update_health_score(process_info, reason)

            # Get stream manager
            from stream_manager import get_stream_manager
            stream_manager = get_stream_manager()

            if not stream_manager or stream_id not in stream_manager.streams:
                logging.warning(f"Stream {stream_id} not in stream manager, cannot fast restart")
                return

            stream_info = stream_manager.streams[stream_id]

            # Kill current process quickly
            try:
                process_info.process.terminate()
                process_info.process.wait(timeout=2)
            except:
                try:
                    process_info.process.kill()
                except:
                    pass

            # Increment restart count
            restart_count = getattr(stream_info, 'fast_restart_count', 0) + 1
            stream_info.fast_restart_count = restart_count
            process_info.restart_count = restart_count

            # Limit fast restarts to prevent infinite loops
            if restart_count > self.config.max_fast_restarts:
                logging.warning(f"⚠️ Stream {stream_id}: Too many fast restarts ({restart_count}), falling back to normal error handling")
                self._request_restart_decision(stream_id, reason, 255, f"Too many fast restarts: {reason}")
                return

            # Report status
            if self.status_reporter:
                self.status_reporter.publish_stream_status(
                    stream_id, 'RESTARTING', f'Fast restart #{restart_count} due to {reason}'
                )

            # Restart immediately with same config
            logging.info(f"🔄 [FAST_RESTART] Restarting stream {stream_id} (attempt #{restart_count})")

            # Small delay to prevent rapid restarts
            time.sleep(self.config.fast_restart_delay)

            # Use centralized restart to prevent conflicts
            success = self.centralized_restart(
                stream_id,
                f"FAST_RESTART_{reason}",
                stream_info.config.playlist_path or stream_info.config.local_files[0],
                stream_info.config.__dict__
            )

            if success:
                logging.info(f"✅ [FAST_RESTART] Stream {stream_id} restarted successfully")
                # Reset restart count on success
                stream_info.fast_restart_count = 0
            else:
                logging.error(f"❌ [FAST_RESTART] Failed to restart stream {stream_id}")

        except Exception as e:
            logging.error(f"❌ [FAST_RESTART] Error during fast restart for stream {stream_id}: {e}")

    def _monitor_resources(self, process_info: ProcessInfo):
        """Monitor process resources to detect issues before they cause crashes"""
        stream_id = process_info.stream_id
        process = process_info.process

        try:
            import psutil
            ps_process = psutil.Process(process.pid)

            # Monitor every 5 minutes
            check_interval = 300  # 5 minutes
            warning_threshold_memory = 1024 * 1024 * 1024  # 1GB
            critical_threshold_memory = 2048 * 1024 * 1024  # 2GB

            while process.poll() is None:  # While process is running
                try:
                    # Get memory usage
                    memory_info = ps_process.memory_info()
                    memory_mb = memory_info.rss / 1024 / 1024

                    # Get CPU usage (over 1 second)
                    cpu_percent = ps_process.cpu_percent(interval=1)

                    # Get runtime
                    runtime_seconds = time.time() - process_info.start_time
                    runtime_hours = runtime_seconds / 3600

                    # Log resource usage every hour
                    if int(runtime_seconds) % 3600 == 0:  # Every hour
                        logging.info(f"📊 Stream {stream_id} resources after {runtime_hours:.1f}h: "
                                   f"Memory: {memory_mb:.1f}MB, CPU: {cpu_percent:.1f}%")

                    # Check for memory issues
                    if memory_info.rss > critical_threshold_memory:
                        logging.warning(f"🚨 Stream {stream_id} using {memory_mb:.1f}MB memory - possible leak!")
                        if self.status_reporter:
                            self.status_reporter.publish_stream_status(
                                stream_id, 'WARNING', f'High memory usage: {memory_mb:.1f}MB'
                            )
                    elif memory_info.rss > warning_threshold_memory:
                        logging.info(f"⚠️ Stream {stream_id} using {memory_mb:.1f}MB memory")

                    # Sleep until next check
                    time.sleep(check_interval)

                except psutil.NoSuchProcess:
                    # Process ended
                    break
                except Exception as e:
                    logging.warning(f"Resource monitoring error for stream {stream_id}: {e}")
                    time.sleep(check_interval)

        except ImportError:
            logging.info(f"psutil not available, skipping resource monitoring for stream {stream_id}")
        except Exception as e:
            logging.error(f"Resource monitoring failed for stream {stream_id}: {e}")

    def _analyze_termination(self, return_code: int, stderr_output: str, process_info: ProcessInfo) -> str:
        """Enhanced termination analysis with clear reason tracking"""

        # Priority 1: Check explicit stop reason (most reliable)
        if process_info.stop_reason:
            logging.info(f"Process has explicit stop reason: {process_info.stop_reason} (initiated by: {process_info.stop_initiated_by})")

            if process_info.stop_reason == 'USER_STOP':
                return "user_stop"
            elif process_info.stop_reason == 'FAST_RESTART':
                return "fast_restart"
            elif process_info.stop_reason == 'SYSTEM_STOP':
                return "system_stop"

        # Priority 2: Analyze exit codes for crashes
        if return_code == 0:
            return "normal_exit"

        # Priority 3: Manual stop signals (fallback)
        if return_code in [-9, -15, -2]:  # SIGKILL, SIGTERM, SIGINT
            # If no explicit reason, assume user stop
            return "user_stop" if not process_info.stop_reason else "system_stop"

        # Priority 4: Everything else is a crash
        return "crash"

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

        # DTS errors - these should trigger fast restart, not normal error handling
        if 'non-monotonous dts' in stderr_lower:
            dts_count = stderr_output.count('Non-monotonous DTS')
            return f'⚡ [DTS_ERRORS] Detected {dts_count} timestamp errors - fast restart triggered'

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

        # Exit code 255 analysis (SIGKILL or system termination)
        elif return_code == 255:
            if 'invalid argument' in stderr_lower:
                return '❌ [INVALID_PARAMS] Invalid FFmpeg parameters - check video format'
            elif 'out of memory' in stderr_lower or 'cannot allocate memory' in stderr_lower:
                return '❌ [OUT_OF_MEMORY] System out of memory - check VPS resources'
            elif 'timeout' in stderr_lower or 'timed out' in stderr_lower:
                return '❌ [TIMEOUT] Network/RTMP timeout after 1+ hour - server may have limits'
            elif 'connection reset' in stderr_lower or 'broken pipe' in stderr_lower:
                return '❌ [CONNECTION_RESET] RTMP server disconnected - may have session limits'
            else:
                return '❌ [SYSTEM_KILL] Process killed by system (255) - possible resource limits or timeout'

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
