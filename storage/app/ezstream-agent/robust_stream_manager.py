#!/usr/bin/env python3
"""
Robust Multi-Stream Manager for EZStream Agent
Handles multiple concurrent streams with error recovery and monitoring
"""

import os
import time
import logging
import threading
import subprocess
import json
import signal
from typing import Dict, Optional, List, Any, Tuple
from dataclasses import dataclass, field
from enum import Enum
from datetime import datetime, timedelta

class StreamState(Enum):
    """Stream states"""
    STOPPED = "stopped"
    STARTING = "starting"
    RUNNING = "running"
    ERROR = "error"
    RECOVERING = "recovering"

class StreamType(Enum):
    """Stream types"""
    HLS_TO_RTMP = "hls_to_rtmp"
    RTMP_TO_RTMP = "rtmp_to_rtmp"

@dataclass
class StreamConfig:
    """Stream configuration"""
    stream_id: int
    input_url: str
    output_url: str
    stream_type: StreamType = StreamType.HLS_TO_RTMP
    use_srs: bool = True
    max_retries: int = 5
    retry_delay: int = 10
    health_check_interval: int = 30
    ffmpeg_options: Dict[str, Any] = field(default_factory=dict)

@dataclass
class StreamProcess:
    """Stream process information"""
    config: StreamConfig
    process: Optional[subprocess.Popen] = None
    state: StreamState = StreamState.STOPPED
    start_time: Optional[datetime] = None
    last_health_check: Optional[datetime] = None
    retry_count: int = 0
    error_message: Optional[str] = None
    srs_stream_key: Optional[str] = None
    pid: Optional[int] = None

class RobustStreamManager:
    """
    Robust Stream Manager with multi-stream support and error recovery
    
    Features:
    - Multiple concurrent streams
    - Auto-restart on failure
    - Health monitoring
    - SRS integration
    - Process management
    """
    
    def __init__(self, status_reporter=None):
        self.streams: Dict[int, StreamProcess] = {}
        self.status_reporter = status_reporter
        self.monitor_thread = None
        self.running = False
        self.lock = threading.RLock()
        
        # Configuration
        self.health_check_interval = 15  # seconds
        self.max_concurrent_streams = 10
        self.ffmpeg_timeout = 300  # 5 minutes
        
        logging.info("🚀 [ROBUST_STREAM] Initialized Robust Stream Manager")

    def start_stream(self, config: StreamConfig) -> bool:
        """Start a new stream with robust error handling"""
        try:
            with self.lock:
                if config.stream_id in self.streams:
                    logging.warning(f"⚠️ [ROBUST_STREAM] Stream {config.stream_id} already exists")
                    return False
                
                if len(self.streams) >= self.max_concurrent_streams:
                    logging.error(f"❌ [ROBUST_STREAM] Max concurrent streams reached ({self.max_concurrent_streams})")
                    return False
                
                logging.info(f"🎬 [ROBUST_STREAM] Starting stream {config.stream_id}")
                logging.info(f"   - Input: {config.input_url}")
                logging.info(f"   - Output: {config.output_url}")
                logging.info(f"   - Type: {config.stream_type.value}")
                logging.info(f"   - Use SRS: {config.use_srs}")
                
                # Create stream process
                stream_process = StreamProcess(config=config)
                self.streams[config.stream_id] = stream_process
                
                # Start the stream
                success = self._start_stream_process(stream_process)
                
                if success:
                    # Start monitoring if not already running
                    self._ensure_monitor_running()
                    
                    # Report status
                    if self.status_reporter:
                        self.status_reporter.publish_stream_status(
                            config.stream_id, 'STARTING', 'Stream starting with robust manager'
                        )
                    
                    return True
                else:
                    # Clean up on failure
                    del self.streams[config.stream_id]
                    return False
                    
        except Exception as e:
            logging.error(f"❌ [ROBUST_STREAM] Error starting stream {config.stream_id}: {e}")
            return False

    def stop_stream(self, stream_id: int) -> bool:
        """Stop a stream gracefully"""
        try:
            with self.lock:
                if stream_id not in self.streams:
                    logging.warning(f"⚠️ [ROBUST_STREAM] Stream {stream_id} not found")
                    return False
                
                stream_process = self.streams[stream_id]
                logging.info(f"🛑 [ROBUST_STREAM] Stopping stream {stream_id}")
                
                # Stop the process
                self._stop_stream_process(stream_process)
                
                # Remove from tracking
                del self.streams[stream_id]
                
                # Report status
                if self.status_reporter:
                    self.status_reporter.publish_stream_status(
                        stream_id, 'STOPPED', 'Stream stopped by user'
                    )
                
                logging.info(f"✅ [ROBUST_STREAM] Stream {stream_id} stopped successfully")
                return True
                
        except Exception as e:
            logging.error(f"❌ [ROBUST_STREAM] Error stopping stream {stream_id}: {e}")
            return False

    def get_stream_status(self, stream_id: int) -> Optional[Dict[str, Any]]:
        """Get detailed stream status"""
        try:
            with self.lock:
                if stream_id not in self.streams:
                    return None
                
                stream_process = self.streams[stream_id]
                
                return {
                    'stream_id': stream_id,
                    'state': stream_process.state.value,
                    'input_url': stream_process.config.input_url,
                    'output_url': stream_process.config.output_url,
                    'start_time': stream_process.start_time.isoformat() if stream_process.start_time else None,
                    'retry_count': stream_process.retry_count,
                    'error_message': stream_process.error_message,
                    'pid': stream_process.pid,
                    'srs_stream_key': stream_process.srs_stream_key,
                    'uptime_seconds': (datetime.now() - stream_process.start_time).total_seconds() if stream_process.start_time else 0
                }
                
        except Exception as e:
            logging.error(f"❌ [ROBUST_STREAM] Error getting stream status {stream_id}: {e}")
            return None

    def get_all_streams_status(self) -> List[Dict[str, Any]]:
        """Get status of all streams"""
        try:
            with self.lock:
                statuses = []
                for stream_id in self.streams:
                    status = self.get_stream_status(stream_id)
                    if status:
                        statuses.append(status)
                return statuses
                
        except Exception as e:
            logging.error(f"❌ [ROBUST_STREAM] Error getting all streams status: {e}")
            return []

    def shutdown(self):
        """Shutdown the stream manager gracefully"""
        try:
            logging.info("🛑 [ROBUST_STREAM] Shutting down stream manager...")
            
            self.running = False
            
            # Stop all streams
            with self.lock:
                stream_ids = list(self.streams.keys())
                for stream_id in stream_ids:
                    self.stop_stream(stream_id)
            
            # Wait for monitor thread to finish
            if self.monitor_thread and self.monitor_thread.is_alive():
                self.monitor_thread.join(timeout=5)
            
            logging.info("✅ [ROBUST_STREAM] Stream manager shutdown complete")
            
        except Exception as e:
            logging.error(f"❌ [ROBUST_STREAM] Error during shutdown: {e}")

    def _start_stream_process(self, stream_process: StreamProcess) -> bool:
        """Start the actual FFmpeg process for a stream"""
        try:
            config = stream_process.config

            # Build FFmpeg command based on stream type and SRS usage
            if config.use_srs:
                # FFmpeg → SRS Local → SRS Forward → Destination
                srs_stream_key = f"stream_{config.stream_id}_{int(time.time())}"
                local_rtmp_url = f"rtmp://127.0.0.1:1935/live/{srs_stream_key}"

                ffmpeg_cmd = self._build_ffmpeg_command(
                    config.input_url,
                    local_rtmp_url,
                    config.stream_type,
                    config.ffmpeg_options
                )

                stream_process.srs_stream_key = srs_stream_key

                # TODO: Configure SRS forward to destination
                self._configure_srs_forward(srs_stream_key, config.output_url)

            else:
                # FFmpeg Direct → Destination
                ffmpeg_cmd = self._build_ffmpeg_command(
                    config.input_url,
                    config.output_url,
                    config.stream_type,
                    config.ffmpeg_options
                )

            logging.info(f"🔧 [ROBUST_STREAM] FFmpeg command: {' '.join(ffmpeg_cmd)}")

            # Start FFmpeg process
            process = subprocess.Popen(
                ffmpeg_cmd,
                stdout=subprocess.PIPE,
                stderr=subprocess.PIPE,
                universal_newlines=True,
                preexec_fn=os.setsid  # Create new process group for clean termination
            )

            stream_process.process = process
            stream_process.pid = process.pid
            stream_process.state = StreamState.STARTING
            stream_process.start_time = datetime.now()
            stream_process.retry_count = 0
            stream_process.error_message = None

            logging.info(f"✅ [ROBUST_STREAM] FFmpeg process started: PID {process.pid}")

            # Give it a moment to start
            time.sleep(2)

            # Check if process is still running
            if process.poll() is None:
                stream_process.state = StreamState.RUNNING
                logging.info(f"✅ [ROBUST_STREAM] Stream {config.stream_id} is running")
                return True
            else:
                # Process died immediately
                stdout, stderr = process.communicate()
                error_msg = f"FFmpeg died immediately: {stderr}"
                logging.error(f"❌ [ROBUST_STREAM] {error_msg}")

                stream_process.state = StreamState.ERROR
                stream_process.error_message = error_msg
                return False

        except Exception as e:
            logging.error(f"❌ [ROBUST_STREAM] Error starting stream process: {e}")
            stream_process.state = StreamState.ERROR
            stream_process.error_message = str(e)
            return False

    def _stop_stream_process(self, stream_process: StreamProcess):
        """Stop a stream process gracefully"""
        try:
            if stream_process.process:
                logging.info(f"🛑 [ROBUST_STREAM] Terminating process PID {stream_process.pid}")

                # Try graceful termination first
                try:
                    os.killpg(os.getpgid(stream_process.process.pid), signal.SIGTERM)

                    # Wait for graceful termination
                    try:
                        stream_process.process.wait(timeout=5)
                        logging.info(f"✅ [ROBUST_STREAM] Process terminated gracefully")
                    except subprocess.TimeoutExpired:
                        # Force kill if doesn't terminate gracefully
                        logging.warning(f"⚠️ [ROBUST_STREAM] Force killing process")
                        os.killpg(os.getpgid(stream_process.process.pid), signal.SIGKILL)
                        stream_process.process.wait()

                except ProcessLookupError:
                    # Process already dead
                    logging.info(f"ℹ️ [ROBUST_STREAM] Process already terminated")

                stream_process.process = None
                stream_process.pid = None

            stream_process.state = StreamState.STOPPED

        except Exception as e:
            logging.error(f"❌ [ROBUST_STREAM] Error stopping process: {e}")

    def _build_ffmpeg_command(self, input_url: str, output_url: str,
                            stream_type: StreamType, options: Dict[str, Any]) -> List[str]:
        """Build FFmpeg command based on stream type and options"""
        try:
            cmd = ['ffmpeg']

            # Input options
            cmd.extend(['-re'])  # Read input at native frame rate
            cmd.extend(['-i', input_url])

            # Stream type specific options
            if stream_type == StreamType.HLS_TO_RTMP:
                # HLS to RTMP optimized settings
                cmd.extend(['-c', 'copy'])  # Copy streams without re-encoding
                cmd.extend(['-f', 'flv'])   # FLV format for RTMP
                cmd.extend(['-rtmp_live', 'live'])

                # Additional HLS specific options
                cmd.extend(['-fflags', '+genpts'])  # Generate PTS for streams
                cmd.extend(['-avoid_negative_ts', 'make_zero'])

            elif stream_type == StreamType.RTMP_TO_RTMP:
                # RTMP to RTMP settings
                cmd.extend(['-c', 'copy'])
                cmd.extend(['-f', 'flv'])
                cmd.extend(['-rtmp_live', 'live'])

            # Custom options from config
            for key, value in options.items():
                if isinstance(value, list):
                    cmd.extend(value)
                else:
                    cmd.extend([key, str(value)])

            # Output URL
            cmd.append(output_url)

            return cmd

        except Exception as e:
            logging.error(f"❌ [ROBUST_STREAM] Error building FFmpeg command: {e}")
            return []

    def _configure_srs_forward(self, stream_key: str, destination_url: str):
        """Configure SRS to forward stream to destination"""
        try:
            logging.info(f"🔗 [ROBUST_STREAM] Configuring SRS forward: {stream_key} → {destination_url}")

            # Get SRS config manager
            from srs_config_manager import get_srs_config_manager
            srs_config_manager = get_srs_config_manager()

            if srs_config_manager:
                success = srs_config_manager.add_forward(stream_key, destination_url)
                if success:
                    logging.info(f"✅ [ROBUST_STREAM] SRS forward configured successfully")
                else:
                    logging.error(f"❌ [ROBUST_STREAM] Failed to configure SRS forward")
            else:
                logging.warning(f"⚠️ [ROBUST_STREAM] SRS config manager not available, using static config")

        except Exception as e:
            logging.error(f"❌ [ROBUST_STREAM] Error configuring SRS forward: {e}")

    def _ensure_monitor_running(self):
        """Ensure monitoring thread is running"""
        try:
            if not self.running:
                self.running = True
                self.monitor_thread = threading.Thread(target=self._monitor_streams, daemon=True)
                self.monitor_thread.start()
                logging.info("🔍 [ROBUST_STREAM] Started monitoring thread")

        except Exception as e:
            logging.error(f"❌ [ROBUST_STREAM] Error starting monitor: {e}")

    def _monitor_streams(self):
        """Monitor all streams and handle failures"""
        logging.info("🔍 [ROBUST_STREAM] Stream monitoring started")

        while self.running:
            try:
                with self.lock:
                    current_time = datetime.now()

                    for stream_id, stream_process in list(self.streams.items()):
                        # Skip if not running
                        if stream_process.state not in [StreamState.RUNNING, StreamState.STARTING]:
                            continue

                        # Check if it's time for health check
                        if (stream_process.last_health_check is None or
                            (current_time - stream_process.last_health_check).total_seconds() >= self.health_check_interval):

                            self._health_check_stream(stream_process)
                            stream_process.last_health_check = current_time

                # Sleep between checks
                time.sleep(5)

            except Exception as e:
                logging.error(f"❌ [ROBUST_STREAM] Error in monitoring loop: {e}")
                time.sleep(10)  # Wait longer on error

        logging.info("🔍 [ROBUST_STREAM] Stream monitoring stopped")

    def _health_check_stream(self, stream_process: StreamProcess):
        """Perform health check on a stream"""
        try:
            config = stream_process.config

            # Check if FFmpeg process is still alive
            if stream_process.process is None or stream_process.process.poll() is not None:
                # Process died
                if stream_process.process:
                    stdout, stderr = stream_process.process.communicate()
                    error_msg = f"FFmpeg process died: {stderr[-500:] if stderr else 'Unknown error'}"
                else:
                    error_msg = "FFmpeg process is None"

                logging.error(f"❌ [ROBUST_STREAM] Stream {config.stream_id} failed: {error_msg}")

                stream_process.state = StreamState.ERROR
                stream_process.error_message = error_msg

                # Attempt recovery
                self._attempt_recovery(stream_process)
                return

            # Check SRS stream if using SRS
            if config.use_srs and stream_process.srs_stream_key:
                srs_healthy = self._check_srs_stream_health(stream_process.srs_stream_key)
                if not srs_healthy:
                    logging.warning(f"⚠️ [ROBUST_STREAM] SRS stream {config.stream_id} not healthy")
                    # Could trigger recovery here if needed

            # If we get here, stream is healthy
            if stream_process.state != StreamState.RUNNING:
                stream_process.state = StreamState.RUNNING
                logging.info(f"✅ [ROBUST_STREAM] Stream {config.stream_id} health check passed")

                # Report healthy status
                if self.status_reporter:
                    uptime = (datetime.now() - stream_process.start_time).total_seconds()
                    self.status_reporter.publish_stream_status(
                        config.stream_id, 'STREAMING', f'Stream healthy, uptime: {int(uptime)}s'
                    )

        except Exception as e:
            logging.error(f"❌ [ROBUST_STREAM] Error in health check for stream {stream_process.config.stream_id}: {e}")

    def _attempt_recovery(self, stream_process: StreamProcess):
        """Attempt to recover a failed stream"""
        try:
            config = stream_process.config

            # Check retry limits
            if stream_process.retry_count >= config.max_retries:
                logging.error(f"❌ [ROBUST_STREAM] Stream {config.stream_id} exceeded max retries ({config.max_retries})")
                stream_process.state = StreamState.ERROR

                # Report final failure
                if self.status_reporter:
                    self.status_reporter.publish_stream_status(
                        config.stream_id, 'ERROR', f'Stream failed after {config.max_retries} retries'
                    )
                return

            logging.info(f"🔄 [ROBUST_STREAM] Attempting recovery for stream {config.stream_id} (retry {stream_process.retry_count + 1}/{config.max_retries})")

            stream_process.state = StreamState.RECOVERING
            stream_process.retry_count += 1

            # Report recovery attempt
            if self.status_reporter:
                self.status_reporter.publish_stream_status(
                    config.stream_id, 'RECOVERING', f'Attempting recovery (retry {stream_process.retry_count})'
                )

            # Clean up old process
            self._stop_stream_process(stream_process)

            # Wait before retry
            time.sleep(config.retry_delay)

            # Attempt restart
            success = self._start_stream_process(stream_process)

            if success:
                logging.info(f"✅ [ROBUST_STREAM] Stream {config.stream_id} recovered successfully")
            else:
                logging.error(f"❌ [ROBUST_STREAM] Stream {config.stream_id} recovery failed")

        except Exception as e:
            logging.error(f"❌ [ROBUST_STREAM] Error during recovery for stream {stream_process.config.stream_id}: {e}")
            stream_process.state = StreamState.ERROR

    def _check_srs_stream_health(self, stream_key: str) -> bool:
        """Check if SRS stream is healthy"""
        try:
            # Get SRS config manager for health check
            from srs_config_manager import get_srs_config_manager
            srs_config_manager = get_srs_config_manager()

            if srs_config_manager:
                stream_status = srs_config_manager.check_stream_status(stream_key)

                if stream_status is None:
                    logging.warning(f"⚠️ [ROBUST_STREAM] Cannot check SRS health for {stream_key}")
                    return True  # Assume healthy if can't check

                if stream_status.get('found') and stream_status.get('active'):
                    logging.debug(f"✅ [ROBUST_STREAM] SRS stream {stream_key} is healthy")
                    return True
                else:
                    logging.warning(f"⚠️ [ROBUST_STREAM] SRS stream {stream_key} not found or inactive")
                    return False
            else:
                # No SRS config manager, assume healthy
                return True

        except Exception as e:
            logging.error(f"❌ [ROBUST_STREAM] Error checking SRS health: {e}")
            return False


# Global instance
_robust_stream_manager: Optional[RobustStreamManager] = None

def init_robust_stream_manager(status_reporter=None) -> RobustStreamManager:
    """Initialize global robust stream manager"""
    global _robust_stream_manager
    _robust_stream_manager = RobustStreamManager(status_reporter)
    return _robust_stream_manager

def get_robust_stream_manager() -> Optional[RobustStreamManager]:
    """Get global robust stream manager instance"""
    return _robust_stream_manager
