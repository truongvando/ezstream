#!/usr/bin/env python3
"""
EZStream Agent HLS Process Manager
Manages 2-stage HLS pipeline: MP4 → HLS → RTMP YouTube
Giải quyết vấn đề DTS/PTS và tối ưu performance
"""

import os
import time
import logging
import threading
import subprocess
import tempfile
import shutil
from typing import Dict, Optional, List, Any, Tuple
from concurrent.futures import ThreadPoolExecutor, Future
from dataclasses import dataclass
from enum import Enum

from config import get_config
from utils import PerformanceTimer, kill_process_tree
from status_reporter import get_status_reporter


class StageType(Enum):
    """HLS Pipeline stages"""
    HLS_GENERATOR = "HLS_GENERATOR"  # MP4 → HLS segments
    RTMP_STREAMER = "RTMP_STREAMER"  # HLS → RTMP YouTube


class StageState(Enum):
    """Stage states"""
    STARTING = "STARTING"
    RUNNING = "RUNNING"
    STOPPING = "STOPPING"
    STOPPED = "STOPPED"
    ERROR = "ERROR"
    RESTARTING = "RESTARTING"


@dataclass
class StageInfo:
    """Information about a pipeline stage"""
    stage_type: StageType
    process: subprocess.Popen
    state: StageState
    start_time: float
    stream_id: int
    monitor_future: Optional[Future] = None
    
    # Error tracking
    error_count: int = 0
    last_error: Optional[str] = None
    restart_count: int = 0
    
    # Performance metrics
    uptime: float = 0
    health_score: float = 1.0


@dataclass
class HLSPipelineInfo:
    """Complete HLS pipeline information"""
    stream_id: int
    input_path: str
    rtmp_endpoint: str
    hls_output_dir: str
    hls_playlist_path: str
    
    # Stages
    hls_generator: Optional[StageInfo] = None
    rtmp_streamer: Optional[StageInfo] = None
    
    # Pipeline state
    start_time: float = 0
    is_stopping: bool = False
    stop_reason: Optional[str] = None


class HLSProcessManager:
    """Manages HLS 2-stage pipeline processes"""
    
    def __init__(self):
        self.config = get_config()
        self.status_reporter = get_status_reporter()
        
        # Pipeline tracking
        self.pipelines: Dict[int, HLSPipelineInfo] = {}
        self.pipeline_lock = threading.RLock()
        
        # Thread pool for monitoring
        self.monitor_executor = ThreadPoolExecutor(
            max_workers=self.config.monitor_thread_pool_size * 2,  # 2 stages per stream
            thread_name_prefix="HLSMonitor"
        )
        
        # HLS working directory from config
        self.hls_base_dir = self.config.hls_base_dir
        os.makedirs(self.hls_base_dir, exist_ok=True)
        
        logging.info(f"🎬 HLS Process Manager initialized (HLS dir: {self.hls_base_dir})")
    
    def start_hls_pipeline(self, stream_id: int, input_path: str, stream_config: Dict[str, Any], 
                          rtmp_endpoint: Optional[str] = None) -> bool:
        """Start complete HLS pipeline for stream"""
        try:
            with self.pipeline_lock:
                # Check if already running
                if stream_id in self.pipelines:
                    logging.warning(f"Stream {stream_id} already has a running HLS pipeline")
                    return False
                
                # Validate input file
                if not self._validate_input_file(input_path, stream_id):
                    return False
                
                # Use default endpoint if not provided
                if rtmp_endpoint is None:
                    rtmp_endpoint = self._get_youtube_rtmp_endpoint(stream_config)
                
                # Setup HLS output directory
                hls_output_dir = os.path.join(self.hls_base_dir, f'stream_{stream_id}')
                os.makedirs(hls_output_dir, exist_ok=True)
                
                hls_playlist_path = os.path.join(hls_output_dir, 'playlist.m3u8')
                
                # Create pipeline info
                pipeline_info = HLSPipelineInfo(
                    stream_id=stream_id,
                    input_path=input_path,
                    rtmp_endpoint=rtmp_endpoint,
                    hls_output_dir=hls_output_dir,
                    hls_playlist_path=hls_playlist_path,
                    start_time=time.time()
                )
                
                self.pipelines[stream_id] = pipeline_info
                
                logging.info(f"🚀 Starting HLS pipeline for stream {stream_id}")
                
                # Start Stage 1: HLS Generator
                if not self._start_hls_generator(pipeline_info):
                    del self.pipelines[stream_id]
                    return False
                
                # Wait for HLS playlist to be created
                if not self._wait_for_hls_playlist(pipeline_info):
                    self._stop_pipeline_internal(pipeline_info, "HLS playlist creation failed")
                    del self.pipelines[stream_id]
                    return False
                
                # Start Stage 2: RTMP Streamer
                if not self._start_rtmp_streamer(pipeline_info):
                    self._stop_pipeline_internal(pipeline_info, "RTMP streamer failed to start")
                    del self.pipelines[stream_id]
                    return False
                
                # Report success
                if self.status_reporter:
                    self.status_reporter.publish_stream_status(
                        stream_id, 'STREAMING', 
                        f'HLS pipeline started successfully (HLS: {pipeline_info.hls_generator.process.pid}, '
                        f'RTMP: {pipeline_info.rtmp_streamer.process.pid})'
                    )
                
                logging.info(f"✅ HLS pipeline started for stream {stream_id} → {rtmp_endpoint}")
                return True
                
        except Exception as e:
            logging.error(f"❌ Failed to start HLS pipeline for stream {stream_id}: {e}")
            
            # Cleanup on failure
            with self.pipeline_lock:
                if stream_id in self.pipelines:
                    self._cleanup_hls_directory(self.pipelines[stream_id].hls_output_dir)
                    del self.pipelines[stream_id]
            
            # Report failure
            if self.status_reporter:
                self.status_reporter.publish_stream_status(
                    stream_id, 'ERROR', f'Failed to start HLS pipeline: {str(e)}'
                )
            return False
    
    def stop_hls_pipeline(self, stream_id: int, reason: str = "manual") -> bool:
        """Stop HLS pipeline for stream"""
        try:
            with self.pipeline_lock:
                if stream_id not in self.pipelines:
                    logging.warning(f"No HLS pipeline found for stream {stream_id}")
                    return True
                
                pipeline_info = self.pipelines[stream_id]
                pipeline_info.is_stopping = True
                pipeline_info.stop_reason = reason
                
                logging.info(f"🛑 Stopping HLS pipeline for stream {stream_id} (reason: {reason})")
                
                # Report stopping status
                if self.status_reporter:
                    self.status_reporter.publish_stream_status(
                        stream_id, 'STOPPING', f'Stopping HLS pipeline - {reason}'
                    )
                
                # Stop both stages
                success = self._stop_pipeline_internal(pipeline_info, reason)
                
                # Cleanup
                self._cleanup_hls_directory(pipeline_info.hls_output_dir)
                del self.pipelines[stream_id]
                
                # Report final status
                if self.status_reporter:
                    status = 'STOPPED' if success else 'ERROR'
                    message = f'HLS pipeline stopped - {reason}' if success else f'Error stopping HLS pipeline - {reason}'
                    self.status_reporter.publish_stream_status(stream_id, status, message)
                
                logging.info(f"✅ HLS pipeline stopped for stream {stream_id}")
                return success
                
        except Exception as e:
            logging.error(f"❌ Error stopping HLS pipeline for stream {stream_id}: {e}")
            return False
    
    def restart_hls_pipeline(self, stream_id: int, new_input_path: str = None, 
                           new_config: Dict[str, Any] = None) -> bool:
        """Restart HLS pipeline with new configuration"""
        try:
            logging.info(f"🔄 Restarting HLS pipeline for stream {stream_id}")
            
            # Get current config if new config not provided
            with self.pipeline_lock:
                if stream_id not in self.pipelines:
                    logging.error(f"No HLS pipeline found for stream {stream_id}")
                    return False
                
                current_pipeline = self.pipelines[stream_id]
                if new_input_path is None:
                    new_input_path = current_pipeline.input_path
                if new_config is None:
                    new_config = {}  # Use default config
            
            # Stop current pipeline
            if not self.stop_hls_pipeline(stream_id, "restart"):
                logging.error(f"Failed to stop current HLS pipeline for stream {stream_id}")
                return False
            
            # Wait for cleanup
            time.sleep(2)
            
            # Start with new config
            return self.start_hls_pipeline(stream_id, new_input_path, new_config)
            
        except Exception as e:
            logging.error(f"❌ Error restarting HLS pipeline for stream {stream_id}: {e}")
            return False

    def restart_stage(self, stream_id: int, stage_type: StageType, reason: str = "error") -> bool:
        """Restart specific stage of HLS pipeline"""
        try:
            with self.pipeline_lock:
                if stream_id not in self.pipelines:
                    logging.error(f"No HLS pipeline found for stream {stream_id}")
                    return False

                pipeline_info = self.pipelines[stream_id]

                logging.info(f"🔄 Restarting {stage_type.value} for stream {stream_id} (reason: {reason})")

                if stage_type == StageType.HLS_GENERATOR:
                    # Stop HLS generator
                    if pipeline_info.hls_generator:
                        self._stop_stage(pipeline_info.hls_generator, reason)

                    # Also need to restart RTMP streamer since HLS will be recreated
                    if pipeline_info.rtmp_streamer:
                        self._stop_stage(pipeline_info.rtmp_streamer, "HLS generator restart")

                    # Restart HLS generator
                    if not self._start_hls_generator(pipeline_info):
                        return False

                    # Wait for HLS playlist
                    if not self._wait_for_hls_playlist(pipeline_info):
                        return False

                    # Restart RTMP streamer
                    return self._start_rtmp_streamer(pipeline_info)

                elif stage_type == StageType.RTMP_STREAMER:
                    # Only restart RTMP streamer
                    if pipeline_info.rtmp_streamer:
                        self._stop_stage(pipeline_info.rtmp_streamer, reason)

                    return self._start_rtmp_streamer(pipeline_info)

                return False

        except Exception as e:
            logging.error(f"❌ Error restarting {stage_type.value} for stream {stream_id}: {e}")
            return False

    def get_pipeline_status(self, stream_id: int) -> Optional[Dict[str, Any]]:
        """Get detailed pipeline status"""
        with self.pipeline_lock:
            if stream_id not in self.pipelines:
                return None

            pipeline_info = self.pipelines[stream_id]

            status = {
                'stream_id': stream_id,
                'input_path': pipeline_info.input_path,
                'rtmp_endpoint': pipeline_info.rtmp_endpoint,
                'start_time': pipeline_info.start_time,
                'uptime': time.time() - pipeline_info.start_time,
                'is_stopping': pipeline_info.is_stopping,
                'stop_reason': pipeline_info.stop_reason,
                'stages': {}
            }

            # HLS Generator status
            if pipeline_info.hls_generator:
                hls_stage = pipeline_info.hls_generator
                status['stages']['hls_generator'] = {
                    'state': hls_stage.state.value,
                    'pid': hls_stage.process.pid if hls_stage.process else None,
                    'uptime': time.time() - hls_stage.start_time,
                    'error_count': hls_stage.error_count,
                    'restart_count': hls_stage.restart_count,
                    'health_score': hls_stage.health_score,
                    'is_alive': hls_stage.process.poll() is None if hls_stage.process else False
                }

            # RTMP Streamer status
            if pipeline_info.rtmp_streamer:
                rtmp_stage = pipeline_info.rtmp_streamer
                status['stages']['rtmp_streamer'] = {
                    'state': rtmp_stage.state.value,
                    'pid': rtmp_stage.process.pid if rtmp_stage.process else None,
                    'uptime': time.time() - rtmp_stage.start_time,
                    'error_count': rtmp_stage.error_count,
                    'restart_count': rtmp_stage.restart_count,
                    'health_score': rtmp_stage.health_score,
                    'is_alive': rtmp_stage.process.poll() is None if rtmp_stage.process else False
                }

            return status

    def get_active_streams(self) -> List[int]:
        """Get list of active stream IDs"""
        with self.pipeline_lock:
            return list(self.pipelines.keys())

    def stop_all(self):
        """Stop all HLS pipelines"""
        logging.info("🛑 Stopping all HLS pipelines...")

        with self.pipeline_lock:
            stream_ids = list(self.pipelines.keys())

        for stream_id in stream_ids:
            try:
                self.stop_hls_pipeline(stream_id, "shutdown")
            except Exception as e:
                logging.error(f"❌ Error stopping HLS pipeline {stream_id} during shutdown: {e}")

        # Shutdown monitor executor
        try:
            self.monitor_executor.shutdown(wait=True)
            logging.info("✅ HLS Process Manager stopped")
        except Exception as e:
            logging.error(f"❌ Error shutting down monitor executor: {e}")

    def _start_hls_generator(self, pipeline_info: HLSPipelineInfo) -> bool:
        """Start HLS generator stage"""
        try:
            stream_id = pipeline_info.stream_id

            # Build HLS generator command
            hls_cmd = self._build_hls_generator_command(
                pipeline_info.input_path,
                pipeline_info.hls_output_dir,
                pipeline_info.hls_playlist_path
            )

            logging.info(f"🎬 Starting HLS generator for stream {stream_id}")
            logging.debug(f"HLS command: {' '.join(hls_cmd)}")

            # Start process
            process = subprocess.Popen(
                hls_cmd,
                stdout=subprocess.DEVNULL,
                stderr=subprocess.PIPE,
                stdin=subprocess.PIPE,
                preexec_fn=os.setsid
            )

            # Create stage info
            stage_info = StageInfo(
                stage_type=StageType.HLS_GENERATOR,
                process=process,
                state=StageState.STARTING,
                start_time=time.time(),
                stream_id=stream_id
            )

            # Start monitoring
            monitor_future = self.monitor_executor.submit(
                self._monitor_stage, stage_info, pipeline_info
            )
            stage_info.monitor_future = monitor_future

            pipeline_info.hls_generator = stage_info
            stage_info.state = StageState.RUNNING

            logging.info(f"✅ HLS generator started for stream {stream_id} (PID: {process.pid})")
            return True

        except Exception as e:
            logging.error(f"❌ Failed to start HLS generator for stream {pipeline_info.stream_id}: {e}")
            return False

    def _start_rtmp_streamer(self, pipeline_info: HLSPipelineInfo) -> bool:
        """Start RTMP streamer stage"""
        try:
            stream_id = pipeline_info.stream_id

            # Build RTMP streamer command
            rtmp_cmd = self._build_rtmp_streamer_command(
                pipeline_info.hls_playlist_path,
                pipeline_info.rtmp_endpoint
            )

            logging.info(f"📡 Starting RTMP streamer for stream {stream_id}")
            logging.debug(f"RTMP command: {' '.join(rtmp_cmd)}")

            # Start process
            process = subprocess.Popen(
                rtmp_cmd,
                stdout=subprocess.DEVNULL,
                stderr=subprocess.PIPE,
                stdin=subprocess.PIPE,
                preexec_fn=os.setsid
            )

            # Create stage info
            stage_info = StageInfo(
                stage_type=StageType.RTMP_STREAMER,
                process=process,
                state=StageState.STARTING,
                start_time=time.time(),
                stream_id=stream_id
            )

            # Start monitoring
            monitor_future = self.monitor_executor.submit(
                self._monitor_stage, stage_info, pipeline_info
            )
            stage_info.monitor_future = monitor_future

            pipeline_info.rtmp_streamer = stage_info
            stage_info.state = StageState.RUNNING

            logging.info(f"✅ RTMP streamer started for stream {stream_id} (PID: {process.pid})")
            return True

        except Exception as e:
            logging.error(f"❌ Failed to start RTMP streamer for stream {pipeline_info.stream_id}: {e}")
            return False

    def _wait_for_hls_playlist(self, pipeline_info: HLSPipelineInfo, timeout: int = 30) -> bool:
        """Wait for HLS playlist to be created and have segments"""
        start_time = time.time()

        while time.time() - start_time < timeout:
            if os.path.exists(pipeline_info.hls_playlist_path):
                try:
                    with open(pipeline_info.hls_playlist_path, 'r') as f:
                        content = f.read()
                        # Check if playlist has at least one segment
                        if '.ts' in content and '#EXTINF' in content:
                            logging.info(f"✅ HLS playlist ready for stream {pipeline_info.stream_id}")
                            return True
                except Exception as e:
                    logging.debug(f"Error reading HLS playlist: {e}")

            time.sleep(1)

        logging.error(f"❌ HLS playlist not ready after {timeout}s for stream {pipeline_info.stream_id}")
        return False

    def _build_hls_generator_command(self, input_path: str, hls_output_dir: str,
                                   hls_playlist_path: str) -> List[str]:
        """Build FFmpeg command for HLS generation with configurable settings"""

        if self.config.ffmpeg_use_encoding:
            # ENCODING MODE: Re-encode video for timestamp normalization
            cmd = [
                'ffmpeg',
                '-hide_banner',
                '-loglevel', 'error',
                '-re',  # Realtime playback
                '-stream_loop', '-1',  # Loop infinitely
                '-i', input_path,

                # Video encoding - configurable settings
                '-c:v', self.config.hls_video_codec,
                '-preset', self.config.hls_video_preset,
                '-crf', str(self.config.hls_video_crf),
                '-maxrate', self.config.hls_video_maxrate,
                '-bufsize', self.config.hls_video_bufsize,
                '-pix_fmt', 'yuv420p',
                '-g', '60',  # GOP size

                # Audio encoding
                '-c:a', self.config.hls_audio_codec,
                '-b:a', self.config.hls_audio_bitrate,

                # HLS specific settings
                '-f', 'hls',
                '-hls_time', str(self.config.hls_segment_duration),
                '-hls_list_size', str(self.config.hls_playlist_size),
                '-hls_flags', 'delete_segments+append_list',
                '-hls_segment_filename', os.path.join(hls_output_dir, 'segment_%03d.ts'),
                hls_playlist_path
            ]
        else:
            # COPY MODE: Copy streams without re-encoding (faster but may have timestamp issues)
            cmd = [
                'ffmpeg',
                '-hide_banner',
                '-loglevel', 'error',
                '-re',  # Realtime playback
                '-stream_loop', '-1',  # Loop infinitely
                '-i', input_path,

                # Copy streams - no re-encoding
                '-c', 'copy',

                # Timestamp fixes for copy mode
                '-avoid_negative_ts', 'make_zero',
                '-fflags', '+genpts',

                # HLS specific settings
                '-f', 'hls',
                '-hls_time', str(self.config.hls_segment_duration),
                '-hls_list_size', str(self.config.hls_playlist_size),
                '-hls_flags', 'delete_segments+append_list',
                '-hls_segment_filename', os.path.join(hls_output_dir, 'segment_%03d.ts'),
                hls_playlist_path
            ]

        return cmd

    def _build_rtmp_streamer_command(self, hls_playlist_path: str, rtmp_endpoint: str) -> List[str]:
        """Build FFmpeg command for RTMP streaming from HLS"""

        cmd = [
            'ffmpeg',
            '-hide_banner',
            '-loglevel', 'error',
            '-re',  # Realtime playback
            '-i', hls_playlist_path,

            # Copy streams - no re-encoding needed
            '-c', 'copy',

            # RTMP output settings
            '-f', 'flv',
            '-flvflags', 'no_duration_filesize',

            rtmp_endpoint
        ]

        return cmd

    def _monitor_stage(self, stage_info: StageInfo, pipeline_info: HLSPipelineInfo):
        """Monitor individual stage process"""
        stream_id = stage_info.stream_id
        stage_type = stage_info.stage_type.value
        process = stage_info.process

        try:
            logging.info(f"🔍 Started monitoring {stage_type} for stream {stream_id} (PID: {process.pid})")

            # Wait for process to end
            _, stderr = process.communicate()
            return_code = process.returncode

            # Update stage state
            stage_info.state = StageState.STOPPED

            # Analyze termination
            if pipeline_info.is_stopping:
                logging.info(f"✅ {stage_type} for stream {stream_id} stopped as requested")
                return

            # Handle unexpected termination
            stage_info.error_count += 1
            error_message = self._analyze_stage_error(stderr.decode('utf-8') if stderr else '',
                                                    return_code, stage_type)

            if error_message:
                stage_info.last_error = error_message
                logging.error(f"❌ {stage_type} for stream {stream_id} failed: {error_message}")

                # Update health score
                stage_info.health_score = max(0.1, stage_info.health_score - 0.2)

                # Report error
                if self.status_reporter:
                    self.status_reporter.publish_stream_status(
                        stream_id, 'ERROR',
                        f'{stage_type} failed: {error_message}'
                    )

                # Decide restart strategy
                self._handle_stage_failure(stage_info, pipeline_info, error_message)
            else:
                logging.info(f"ℹ️ {stage_type} for stream {stream_id} ended normally")

        except Exception as e:
            logging.error(f"❌ Error monitoring {stage_type} for stream {stream_id}: {e}")
            stage_info.state = StageState.ERROR
            stage_info.last_error = str(e)

        finally:
            logging.info(f"🔍 Monitoring ended for {stage_type} stream {stream_id}")

    def _handle_stage_failure(self, stage_info: StageInfo, pipeline_info: HLSPipelineInfo,
                            error_message: str):
        """Handle stage failure with smart restart logic"""
        stream_id = stage_info.stream_id
        stage_type = stage_info.stage_type

        # Check restart limits
        if stage_info.restart_count >= 5:
            logging.error(f"💀 {stage_type.value} for stream {stream_id} exceeded restart limit")

            # Request manual intervention
            if self.status_reporter:
                self.status_reporter.publish_restart_request(
                    stream_id=stream_id,
                    reason=f"{stage_type.value} exceeded restart limit",
                    crash_count=stage_info.restart_count,
                    last_error=error_message,
                    error_type="RESTART_LIMIT_EXCEEDED"
                )
            return

        # Determine restart strategy based on error type
        if "FILE_NOT_FOUND" in error_message or "PERMISSION_ERROR" in error_message:
            # Fatal errors - don't auto-restart
            logging.error(f"💀 Fatal error in {stage_type.value} for stream {stream_id}: {error_message}")
            return

        # Auto-restart for recoverable errors
        stage_info.restart_count += 1
        restart_delay = min(2 ** stage_info.restart_count, 30)  # Exponential backoff, max 30s

        logging.info(f"🔄 Auto-restarting {stage_type.value} for stream {stream_id} "
                    f"(attempt #{stage_info.restart_count}) in {restart_delay}s")

        # Schedule restart
        def delayed_restart():
            time.sleep(restart_delay)
            try:
                self.restart_stage(stream_id, stage_type, f"auto_restart_{stage_info.restart_count}")
            except Exception as e:
                logging.error(f"❌ Failed to auto-restart {stage_type.value} for stream {stream_id}: {e}")

        restart_thread = threading.Thread(target=delayed_restart, daemon=True)
        restart_thread.start()

    def _analyze_stage_error(self, stderr_output: str, return_code: int, stage_type: str) -> Optional[str]:
        """Analyze stage error and return user-friendly message"""
        stderr_lower = stderr_output.lower()

        # Manual termination signals
        if return_code in [-9, -15, -2]:
            return None  # Not an error for manual stops

        # Common errors
        if 'no such file or directory' in stderr_lower:
            return f'❌ [FILE_NOT_FOUND] Input file missing'
        elif 'permission denied' in stderr_lower:
            return f'❌ [PERMISSION_ERROR] Cannot access file'
        elif 'connection refused' in stderr_lower:
            return f'❌ [CONNECTION_ERROR] Cannot connect to RTMP server'
        elif 'connection timed out' in stderr_lower:
            return f'❌ [TIMEOUT] Network timeout'
        elif 'invalid data found' in stderr_lower:
            return f'❌ [CORRUPTED_DATA] Invalid input data'
        elif 'out of memory' in stderr_lower or return_code == 137:
            return f'❌ [OUT_OF_MEMORY] System out of memory'

        # Stage-specific errors
        if stage_type == "HLS_GENERATOR":
            if 'codec not currently supported' in stderr_lower:
                return f'❌ [UNSUPPORTED_CODEC] Video codec not supported'
            elif 'moov atom not found' in stderr_lower:
                return f'❌ [INCOMPLETE_FILE] Video file incomplete'

        elif stage_type == "RTMP_STREAMER":
            if 'rtmp' in stderr_lower and 'error' in stderr_lower:
                return f'❌ [RTMP_ERROR] RTMP streaming error'
            elif 'server returned 4' in stderr_lower:
                return f'❌ [RTMP_SERVER_ERROR] RTMP server rejected stream'

        # Generic error
        if return_code != 0:
            return f'❌ [UNKNOWN_ERROR] Process exited with code {return_code}'

        return None

    def _stop_pipeline_internal(self, pipeline_info: HLSPipelineInfo, reason: str) -> bool:
        """Internal method to stop pipeline stages"""
        success = True

        # Stop RTMP streamer first
        if pipeline_info.rtmp_streamer:
            if not self._stop_stage(pipeline_info.rtmp_streamer, reason):
                success = False

        # Stop HLS generator
        if pipeline_info.hls_generator:
            if not self._stop_stage(pipeline_info.hls_generator, reason):
                success = False

        return success

    def _stop_stage(self, stage_info: StageInfo, reason: str) -> bool:
        """Stop individual stage"""
        try:
            process = stage_info.process
            stage_type = stage_info.stage_type.value
            stream_id = stage_info.stream_id

            if not process or process.poll() is not None:
                logging.info(f"{stage_type} for stream {stream_id} already stopped")
                stage_info.state = StageState.STOPPED
                return True

            stage_info.state = StageState.STOPPING
            old_pid = process.pid

            logging.info(f"🛑 Stopping {stage_type} for stream {stream_id} (PID: {old_pid}, reason: {reason})")

            # Try graceful shutdown via stdin 'q' command
            try:
                if process.stdin and not process.stdin.closed:
                    process.stdin.write(b'q\n')
                    process.stdin.flush()

                    try:
                        process.wait(timeout=3)
                        logging.info(f"✅ {stage_type} stopped gracefully via 'q' command")
                        stage_info.state = StageState.STOPPED
                        return True
                    except subprocess.TimeoutExpired:
                        logging.info(f"{stage_type} 'q' command timeout, trying SIGINT")

            except (BrokenPipeError, OSError, ValueError):
                logging.info(f"{stage_type} cannot use stdin method, trying SIGINT")

            # Fallback to SIGINT
            import signal
            try:
                os.kill(process.pid, signal.SIGINT)

                try:
                    process.wait(timeout=self.config.graceful_shutdown_timeout)
                    logging.info(f"✅ {stage_type} stopped gracefully via SIGINT")
                    stage_info.state = StageState.STOPPED
                    return True

                except subprocess.TimeoutExpired:
                    logging.warning(f"⚠️ {stage_type} didn't stop gracefully, force killing")

                    if kill_process_tree(old_pid, timeout=self.config.force_kill_timeout):
                        logging.info(f"✅ {stage_type} force killed")
                        stage_info.state = StageState.STOPPED
                        return True
                    else:
                        logging.error(f"❌ Failed to kill {stage_type}")
                        stage_info.state = StageState.ERROR
                        return False

            except ProcessLookupError:
                logging.info(f"{stage_type} process already terminated")
                stage_info.state = StageState.STOPPED
                return True
            except Exception as e:
                logging.error(f"❌ Error stopping {stage_type}: {e}")
                stage_info.state = StageState.ERROR
                return False

            # Cancel monitoring future
            if stage_info.monitor_future:
                stage_info.monitor_future.cancel()

            return True

        except Exception as e:
            logging.error(f"❌ Error stopping {stage_info.stage_type.value}: {e}")
            stage_info.state = StageState.ERROR
            return False

    def _validate_input_file(self, input_path: str, stream_id: int) -> bool:
        """Validate input file for streaming"""
        try:
            if not os.path.exists(input_path):
                logging.error(f"Stream {stream_id}: Input file not found: {input_path}")
                return False

            file_size = os.path.getsize(input_path)
            if file_size < 1024:  # Less than 1KB
                logging.error(f"Stream {stream_id}: Input file too small: {input_path}")
                return False

            # Quick ffprobe check
            cmd = ['ffprobe', '-v', 'quiet', '-show_format', input_path]
            result = subprocess.run(cmd, capture_output=True, timeout=5)

            if result.returncode != 0:
                logging.warning(f"Stream {stream_id}: ffprobe failed for {input_path}")
                return False

            logging.info(f"Stream {stream_id}: Input file validation passed for {input_path}")
            return True

        except subprocess.TimeoutExpired:
            logging.warning(f"Stream {stream_id}: ffprobe timeout for {input_path}")
            return False
        except Exception as e:
            logging.warning(f"Stream {stream_id}: Validation error for {input_path}: {e}")
            return False

    def _get_youtube_rtmp_endpoint(self, stream_config: Dict[str, Any]) -> str:
        """Get YouTube RTMP endpoint from stream config"""
        # Extract from config or use default
        rtmp_url = stream_config.get('rtmp_url', 'rtmp://a.rtmp.youtube.com/live2/')
        stream_key = stream_config.get('stream_key', 'YOUR_STREAM_KEY')

        if not rtmp_url.endswith('/'):
            rtmp_url += '/'

        return f"{rtmp_url}{stream_key}"

    def _cleanup_hls_directory(self, hls_output_dir: str):
        """Cleanup HLS output directory"""
        try:
            if os.path.exists(hls_output_dir):
                shutil.rmtree(hls_output_dir)
                logging.info(f"🧹 Cleaned up HLS directory: {hls_output_dir}")
        except Exception as e:
            logging.warning(f"⚠️ Failed to cleanup HLS directory {hls_output_dir}: {e}")


# Global instance
hls_process_manager: Optional[HLSProcessManager] = None


def init_hls_process_manager():
    """Initialize global HLS process manager"""
    global hls_process_manager
    hls_process_manager = HLSProcessManager()
    return hls_process_manager


def get_hls_process_manager() -> HLSProcessManager:
    """Get global HLS process manager instance"""
    if hls_process_manager is None:
        raise RuntimeError("HLS Process Manager not initialized. Call init_hls_process_manager() first.")
    return hls_process_manager
