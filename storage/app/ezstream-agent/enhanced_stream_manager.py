#!/usr/bin/env python3
"""
Enhanced EZStream Agent Stream Manager
Integrates HLS pipeline with improved error handling and Laravel communication
"""

import os
import time
import logging
import threading
import subprocess
from typing import Dict, List, Optional, Any
from dataclasses import dataclass, field
from enum import Enum

from config import get_config
from utils import PerformanceTimer, ensure_directory
from status_reporter import get_status_reporter
from hls_process_manager import get_hls_process_manager, init_hls_process_manager
from file_manager import get_file_manager


class EnhancedStreamState(Enum):
    """Enhanced stream state enumeration"""
    INACTIVE = "INACTIVE"
    STARTING = "STARTING"
    DOWNLOADING = "DOWNLOADING"
    HLS_GENERATING = "HLS_GENERATING"
    RTMP_STREAMING = "RTMP_STREAMING"
    STREAMING = "STREAMING"  # Both stages running
    UPDATING = "UPDATING"
    STOPPING = "STOPPING"
    ERROR = "ERROR"
    RESTARTING = "RESTARTING"


@dataclass
class EnhancedStreamConfig:
    """Enhanced stream configuration with HLS support"""
    id: int
    video_files: List[Dict[str, Any]]
    rtmp_url: str
    stream_key: str
    push_urls: Optional[List[str]] = None
    loop: bool = True
    keep_files_after_stop: bool = False
    playlist_order: str = "sequential"
    
    # HLS specific settings
    hls_segment_duration: int = 4  # seconds
    hls_playlist_size: int = 10    # number of segments
    
    # Runtime data
    local_files: List[str] = field(default_factory=list)
    playlist_path: Optional[str] = None
    created_at: float = field(default_factory=time.time)
    updated_at: float = field(default_factory=time.time)


@dataclass
class EnhancedStreamInfo:
    """Enhanced stream information with HLS pipeline tracking"""
    config: EnhancedStreamConfig
    state: EnhancedStreamState
    start_time: Optional[float] = None
    error_message: Optional[str] = None
    last_update: float = field(default_factory=time.time)
    
    # HLS pipeline tracking
    hls_pipeline_active: bool = False
    total_restarts: int = 0
    last_restart_time: Optional[float] = None
    
    # Performance metrics
    uptime: float = 0
    health_score: float = 1.0


class EnhancedStreamManager:
    """Enhanced stream manager with HLS pipeline integration"""
    
    def __init__(self):
        self.config = get_config()
        self.status_reporter = get_status_reporter()
        self.file_manager = get_file_manager()
        
        # Initialize HLS process manager
        self.hls_process_manager = init_hls_process_manager()
        
        # Stream tracking
        self.streams: Dict[int, EnhancedStreamInfo] = {}
        self.stream_lock = threading.RLock()
        
        # Restart operation locks
        self.restart_locks = {}
        self.restart_lock_mutex = threading.Lock()
        
        # Health monitoring
        self.health_monitor_thread = None
        self.health_monitor_running = False
        
        logging.info("🎬 Enhanced Stream Manager initialized with HLS pipeline support")
        
        # Start health monitoring
        self._start_health_monitoring()
    
    def start_stream(self, stream_config_data: Dict[str, Any]) -> bool:
        """Start stream with HLS pipeline"""
        stream_id = stream_config_data.get('id')
        if not stream_id:
            logging.error("No stream ID provided")
            return False
        
        try:
            with self.stream_lock:
                # Check if already running
                if stream_id in self.streams:
                    logging.warning(f"Stream {stream_id} already exists")
                    return False
                
                # Create enhanced config
                config = EnhancedStreamConfig(
                    id=stream_id,
                    video_files=stream_config_data.get('video_files', []),
                    rtmp_url=stream_config_data.get('rtmp_url', 'rtmp://a.rtmp.youtube.com/live2/'),
                    stream_key=stream_config_data.get('stream_key', ''),
                    push_urls=stream_config_data.get('push_urls'),
                    loop=stream_config_data.get('loop', True),
                    keep_files_after_stop=stream_config_data.get('keep_files_after_stop', False),
                    playlist_order=stream_config_data.get('playlist_order', 'sequential'),
                    hls_segment_duration=stream_config_data.get('hls_segment_duration', 4),
                    hls_playlist_size=stream_config_data.get('hls_playlist_size', 10)
                )
                
                # Create stream info
                stream_info = EnhancedStreamInfo(
                    config=config,
                    state=EnhancedStreamState.STARTING,
                    start_time=time.time()
                )
                
                self.streams[stream_id] = stream_info
                
                logging.info(f"🚀 Starting enhanced stream {stream_id}")
                
                # Report starting status
                if self.status_reporter:
                    self.status_reporter.publish_stream_status(
                        stream_id, 'STARTING', 'Preparing stream with HLS pipeline'
                    )
                
                # Download files if needed
                if not self._prepare_stream_files(stream_info):
                    del self.streams[stream_id]
                    return False
                
                # Start HLS pipeline
                if not self._start_hls_pipeline(stream_info):
                    del self.streams[stream_id]
                    return False
                
                # Update state
                stream_info.state = EnhancedStreamState.STREAMING
                stream_info.hls_pipeline_active = True
                
                # Report success
                if self.status_reporter:
                    self.status_reporter.publish_stream_status(
                        stream_id, 'STREAMING', 'HLS pipeline started successfully'
                    )
                
                logging.info(f"✅ Enhanced stream {stream_id} started successfully")
                return True
                
        except Exception as e:
            logging.error(f"❌ Failed to start enhanced stream {stream_id}: {e}")
            
            # Cleanup on failure
            with self.stream_lock:
                if stream_id in self.streams:
                    del self.streams[stream_id]
            
            # Report failure
            if self.status_reporter:
                self.status_reporter.publish_stream_status(
                    stream_id, 'ERROR', f'Failed to start stream: {str(e)}'
                )
            
            return False
    
    def stop_stream(self, stream_id: int, reason: str = "manual") -> bool:
        """Stop stream and HLS pipeline"""
        try:
            with self.stream_lock:
                if stream_id not in self.streams:
                    logging.warning(f"Stream {stream_id} not found")
                    return True
                
                stream_info = self.streams[stream_id]
                stream_info.state = EnhancedStreamState.STOPPING
                
                logging.info(f"🛑 Stopping enhanced stream {stream_id} (reason: {reason})")
                
                # Report stopping status
                if self.status_reporter:
                    self.status_reporter.publish_stream_status(
                        stream_id, 'STOPPING', f'Stopping HLS pipeline - {reason}'
                    )
                
                # Stop HLS pipeline
                success = self.hls_process_manager.stop_hls_pipeline(stream_id, reason)
                
                # Cleanup files if configured
                if not stream_info.config.keep_files_after_stop:
                    self._cleanup_stream_files(stream_info)
                
                # Remove from tracking
                del self.streams[stream_id]
                
                # Report final status
                if self.status_reporter:
                    status = 'STOPPED' if success else 'ERROR'
                    message = f'Stream stopped - {reason}' if success else f'Error stopping stream - {reason}'
                    self.status_reporter.publish_stream_status(stream_id, status, message)
                
                logging.info(f"✅ Enhanced stream {stream_id} stopped")
                return success
                
        except Exception as e:
            logging.error(f"❌ Error stopping enhanced stream {stream_id}: {e}")
            return False
    
    def restart_stream(self, stream_id: int, new_config: Dict[str, Any] = None) -> bool:
        """Restart stream with optional new configuration"""
        try:
            restart_lock = self._get_restart_lock(stream_id)
            
            with restart_lock:
                logging.info(f"🔄 Restarting enhanced stream {stream_id}")
                
                # Get current config if new config not provided
                with self.stream_lock:
                    if stream_id not in self.streams:
                        logging.error(f"Stream {stream_id} not found for restart")
                        return False
                    
                    current_stream = self.streams[stream_id]
                    current_stream.total_restarts += 1
                    current_stream.last_restart_time = time.time()
                    
                    if new_config is None:
                        # Use current config
                        new_config = {
                            'id': current_stream.config.id,
                            'video_files': current_stream.config.video_files,
                            'rtmp_url': current_stream.config.rtmp_url,
                            'stream_key': current_stream.config.stream_key,
                            'push_urls': current_stream.config.push_urls,
                            'loop': current_stream.config.loop,
                            'keep_files_after_stop': current_stream.config.keep_files_after_stop,
                            'playlist_order': current_stream.config.playlist_order,
                            'hls_segment_duration': current_stream.config.hls_segment_duration,
                            'hls_playlist_size': current_stream.config.hls_playlist_size
                        }
                
                # Stop current stream
                if not self.stop_stream(stream_id, "restart"):
                    logging.error(f"Failed to stop stream {stream_id} for restart")
                    return False
                
                # Wait for cleanup
                time.sleep(3)
                
                # Start with new config
                return self.start_stream(new_config)
                
        except Exception as e:
            logging.error(f"❌ Error restarting enhanced stream {stream_id}: {e}")
            return False

    def update_stream(self, stream_id: int, new_config: Dict[str, Any]) -> bool:
        """Update stream configuration with HLS settings support"""
        try:
            with self.stream_lock:
                if stream_id not in self.streams:
                    logging.error(f"Stream {stream_id} not found for update")
                    return False

                current_stream = self.streams[stream_id]

                logging.info(f"🔧 Updating stream {stream_id} configuration")

                # Check if critical HLS settings changed (requires restart)
                critical_changes = self._detect_critical_changes(current_stream.config, new_config)

                if critical_changes:
                    logging.info(f"🔄 Critical HLS changes detected: {critical_changes}")

                    # Special handling for video_files changes
                    if 'video_files' in critical_changes:
                        return self._update_stream_with_new_videos(stream_id, current_stream, new_config)
                    else:
                        # Other critical changes - normal restart
                        logging.info(f"🔄 Restarting stream {stream_id} with new configuration")

                        # Update config first
                        self._update_stream_config(current_stream.config, new_config)

                        # Restart with new config
                        restart_config = self._build_restart_config(current_stream.config)
                        return self.restart_stream(stream_id, restart_config)

                else:
                    # Non-critical changes - just update config
                    logging.info(f"📝 Non-critical changes - updating config only")
                    self._update_stream_config(current_stream.config, new_config)

                    # Report config update
                    if self.status_reporter:
                        self.status_reporter.publish_stream_status(
                            stream_id, 'CONFIG_UPDATED', 'Stream configuration updated'
                        )

                    return True

        except Exception as e:
            logging.error(f"❌ Error updating stream {stream_id}: {e}")
            return False

    def _update_stream_with_new_videos(self, stream_id: int, current_stream: EnhancedStreamInfo,
                                     new_config: Dict[str, Any]) -> bool:
        """Update stream with new video files - download first, then restart"""
        # Get restart lock to prevent concurrent updates
        restart_lock = self._get_restart_lock(stream_id)

        with restart_lock:
            try:
                logging.info(f"📁 Updating stream {stream_id} with new video files")

                # Report preparation status
                if self.status_reporter:
                    self.status_reporter.publish_stream_status(
                        stream_id, 'PREPARING_UPDATE', 'Downloading new video files...'
                    )

                # Step 1: Download new videos WHILE stream is still running
                new_video_files = new_config.get('video_files', [])
                if not new_video_files:
                    logging.error(f"No video files provided for stream {stream_id}")
                    return False

                logging.info(f"📥 Pre-downloading {len(new_video_files)} video files...")

                # Download files to temporary location first
                temp_local_files = []
                for video_file in new_video_files:
                    if 'url' in video_file:
                        # Download remote file
                        temp_local_path = self.file_manager.download_file(
                            video_file['url'], f"{stream_id}_temp", video_file.get('filename')
                        )
                        if temp_local_path:
                            temp_local_files.append(temp_local_path)
                            logging.info(f"✅ Downloaded: {video_file.get('filename', 'unknown')}")
                        else:
                            logging.error(f"❌ Failed to download: {video_file['url']}")
                            # Cleanup partial downloads
                            self._cleanup_temp_files(temp_local_files)
                            return False
                    elif 'path' in video_file:
                        # Validate local file exists
                        if os.path.exists(video_file['path']):
                            temp_local_files.append(video_file['path'])
                            logging.info(f"✅ Validated local file: {video_file['path']}")
                        else:
                            logging.error(f"❌ Local file not found: {video_file['path']}")
                            return False

                if not temp_local_files:
                    logging.error(f"No valid files downloaded for stream {stream_id}")
                    return False

                # Step 2: Create temporary playlist if multiple files
                temp_playlist_path = None
                if len(temp_local_files) > 1:
                    temp_playlist_path = self.file_manager.create_playlist(temp_local_files, f"{stream_id}_temp")
                    if not temp_playlist_path:
                        logging.error(f"Failed to create temporary playlist for stream {stream_id}")
                        self._cleanup_temp_files(temp_local_files)
                        return False
                    logging.info(f"✅ Created temporary playlist: {temp_playlist_path}")
                else:
                    temp_playlist_path = temp_local_files[0]

                # Step 3: Validate the new input works with FFmpeg
                if not self._validate_input_file(temp_playlist_path, stream_id):
                    logging.error(f"New video files validation failed for stream {stream_id}")
                    self._cleanup_temp_files(temp_local_files)
                    return False

                logging.info(f"✅ All new video files ready for stream {stream_id}")

                # Report ready status
                if self.status_reporter:
                    self.status_reporter.publish_stream_status(
                        stream_id, 'UPDATE_READY', 'New videos ready, switching stream...'
                    )

                # Step 4: NOW do the quick restart with pre-downloaded files
                logging.info(f"🔄 Quick restart with pre-downloaded files for stream {stream_id}")

                # Update config with new files
                self._update_stream_config(current_stream.config, new_config)

                # Move temp files to final location
                final_local_files = []
                for temp_file in temp_local_files:
                    if f"{stream_id}_temp" in temp_file:
                        # Move from temp to final location
                        final_file = temp_file.replace(f"{stream_id}_temp", str(stream_id))
                        try:
                            # Safe rename with retry
                            if os.path.exists(final_file):
                                os.remove(final_file)
                            os.rename(temp_file, final_file)
                            final_local_files.append(final_file)
                            logging.info(f"✅ Moved temp file: {temp_file} → {final_file}")
                        except OSError as e:
                            logging.error(f"❌ Failed to move temp file {temp_file}: {e}")
                            # Fallback: use temp file directly
                            final_local_files.append(temp_file)
                    else:
                        # Local file, use as-is
                        final_local_files.append(temp_file)

                # Update config with final paths
                current_stream.config.local_files = final_local_files
                if len(final_local_files) > 1:
                    final_playlist = temp_playlist_path.replace(f"{stream_id}_temp", str(stream_id))
                    try:
                        if os.path.exists(final_playlist):
                            os.remove(final_playlist)
                        os.rename(temp_playlist_path, final_playlist)
                        current_stream.config.playlist_path = final_playlist
                        logging.info(f"✅ Moved temp playlist: {temp_playlist_path} → {final_playlist}")
                    except OSError as e:
                        logging.error(f"❌ Failed to move temp playlist {temp_playlist_path}: {e}")
                        # Fallback: use temp playlist directly
                        current_stream.config.playlist_path = temp_playlist_path
                else:
                    current_stream.config.playlist_path = final_local_files[0]

                # Quick restart with ready files
                restart_config = self._build_restart_config(current_stream.config)
                success = self.restart_stream(stream_id, restart_config)

                if success:
                    logging.info(f"✅ Stream {stream_id} updated successfully with new videos")
                else:
                    logging.error(f"❌ Failed to restart stream {stream_id} with new videos")
                    # Cleanup on failure
                    self._cleanup_temp_files(final_local_files)

                return success

            except Exception as e:
                logging.error(f"❌ Error updating stream {stream_id} with new videos: {e}")
                return False

    def _detect_critical_changes(self, current_config: EnhancedStreamConfig,
                                new_config: Dict[str, Any]) -> List[str]:
        """Detect changes that require stream restart"""
        critical_changes = []

        # HLS encoding mode change
        if 'ffmpeg_use_encoding' in new_config:
            if new_config['ffmpeg_use_encoding'] != self.config.ffmpeg_use_encoding:
                critical_changes.append('encoding_mode')

        # HLS segment settings
        if 'hls_segment_duration' in new_config:
            if new_config['hls_segment_duration'] != current_config.hls_segment_duration:
                critical_changes.append('segment_duration')

        if 'hls_playlist_size' in new_config:
            if new_config['hls_playlist_size'] != current_config.hls_playlist_size:
                critical_changes.append('playlist_size')

        # Video encoding settings (if in encoding mode)
        encoding_settings = ['hls_video_preset', 'hls_video_crf', 'hls_video_maxrate']
        for setting in encoding_settings:
            if setting in new_config:
                current_value = getattr(self.config, setting, None)
                if new_config[setting] != current_value:
                    critical_changes.append(setting)

        # RTMP endpoint changes
        if 'rtmp_url' in new_config:
            if new_config['rtmp_url'] != current_config.rtmp_url:
                critical_changes.append('rtmp_url')

        if 'stream_key' in new_config:
            if new_config['stream_key'] != current_config.stream_key:
                critical_changes.append('stream_key')

        # Video files changes
        if 'video_files' in new_config:
            if new_config['video_files'] != current_config.video_files:
                critical_changes.append('video_files')

        return critical_changes

    def _update_stream_config(self, current_config: EnhancedStreamConfig,
                             new_config: Dict[str, Any]):
        """Update stream configuration object"""

        # Update basic settings
        if 'rtmp_url' in new_config:
            current_config.rtmp_url = new_config['rtmp_url']

        if 'stream_key' in new_config:
            current_config.stream_key = new_config['stream_key']

        if 'video_files' in new_config:
            current_config.video_files = new_config['video_files']

        if 'loop' in new_config:
            current_config.loop = new_config['loop']

        if 'keep_files_after_stop' in new_config:
            current_config.keep_files_after_stop = new_config['keep_files_after_stop']

        if 'playlist_order' in new_config:
            current_config.playlist_order = new_config['playlist_order']

        # Update HLS settings
        if 'hls_segment_duration' in new_config:
            current_config.hls_segment_duration = new_config['hls_segment_duration']

        if 'hls_playlist_size' in new_config:
            current_config.hls_playlist_size = new_config['hls_playlist_size']

        # Update timestamp
        current_config.updated_at = time.time()

        logging.info(f"✅ Stream config updated at {current_config.updated_at}")

    def _build_restart_config(self, stream_config: EnhancedStreamConfig) -> Dict[str, Any]:
        """Build restart configuration from stream config"""
        return {
            'id': stream_config.id,
            'video_files': stream_config.video_files,
            'rtmp_url': stream_config.rtmp_url,
            'stream_key': stream_config.stream_key,
            'push_urls': stream_config.push_urls,
            'loop': stream_config.loop,
            'keep_files_after_stop': stream_config.keep_files_after_stop,
            'playlist_order': stream_config.playlist_order,
            'hls_segment_duration': stream_config.hls_segment_duration,
            'hls_playlist_size': stream_config.hls_playlist_size
        }

    def get_stream_status(self, stream_id: int) -> Optional[Dict[str, Any]]:
        """Get detailed stream status including HLS pipeline"""
        with self.stream_lock:
            if stream_id not in self.streams:
                return None

            stream_info = self.streams[stream_id]

            # Get HLS pipeline status
            hls_status = self.hls_process_manager.get_pipeline_status(stream_id)

            status = {
                'stream_id': stream_id,
                'state': stream_info.state.value,
                'start_time': stream_info.start_time,
                'uptime': time.time() - stream_info.start_time if stream_info.start_time else 0,
                'error_message': stream_info.error_message,
                'last_update': stream_info.last_update,
                'hls_pipeline_active': stream_info.hls_pipeline_active,
                'total_restarts': stream_info.total_restarts,
                'last_restart_time': stream_info.last_restart_time,
                'health_score': stream_info.health_score,
                'config': {
                    'rtmp_url': stream_info.config.rtmp_url,
                    'stream_key': stream_info.config.stream_key[:8] + '...' if stream_info.config.stream_key else '',
                    'video_files_count': len(stream_info.config.video_files),
                    'loop': stream_info.config.loop,
                    'hls_segment_duration': stream_info.config.hls_segment_duration,
                    'hls_playlist_size': stream_info.config.hls_playlist_size
                },
                'hls_pipeline': hls_status
            }

            return status

    def get_all_streams_status(self) -> Dict[int, Dict[str, Any]]:
        """Get status of all streams"""
        with self.stream_lock:
            return {
                stream_id: self.get_stream_status(stream_id)
                for stream_id in self.streams.keys()
            }

    def get_active_streams(self) -> List[int]:
        """Get list of active stream IDs"""
        with self.stream_lock:
            return [
                stream_id for stream_id, stream_info in self.streams.items()
                if stream_info.state in [
                    EnhancedStreamState.STREAMING,
                    EnhancedStreamState.HLS_GENERATING,
                    EnhancedStreamState.RTMP_STREAMING
                ]
            ]

    def get_active_stream_ids(self) -> List[int]:
        """Get list of active stream IDs (alias for compatibility)"""
        return self.get_active_streams()

    def stop_all_streams(self):
        """Stop all streams"""
        logging.info("🛑 Stopping all enhanced streams...")

        with self.stream_lock:
            stream_ids = list(self.streams.keys())

        for stream_id in stream_ids:
            try:
                self.stop_stream(stream_id, "shutdown")
            except Exception as e:
                logging.error(f"❌ Error stopping stream {stream_id} during shutdown: {e}")

        # Stop health monitoring
        self._stop_health_monitoring()

        # Stop HLS process manager
        self.hls_process_manager.stop_all()

        logging.info("✅ Enhanced Stream Manager stopped")

    def _prepare_stream_files(self, stream_info: EnhancedStreamInfo) -> bool:
        """Prepare stream files (download if needed)"""
        try:
            stream_id = stream_info.config.id
            video_files = stream_info.config.video_files

            if not video_files:
                logging.error(f"Stream {stream_id}: No video files provided")
                return False

            # Update state
            stream_info.state = EnhancedStreamState.DOWNLOADING

            # Report downloading status
            if self.status_reporter:
                self.status_reporter.publish_stream_status(
                    stream_id, 'DOWNLOADING', f'Preparing {len(video_files)} video files'
                )

            # Use file manager to prepare files
            logging.info(f"🔍 Stream {stream_id}: Processing {len(video_files)} video files")

            # Download all files using file manager
            try:
                local_files = self.file_manager.download_files(stream_id, video_files)
                logging.info(f"✅ Stream {stream_id}: Downloaded {len(local_files)} files successfully")
            except Exception as e:
                error_msg = f"Failed to download files for stream {stream_id}: {str(e)}"
                logging.error(error_msg)

                # Report error status to Laravel
                if self.status_reporter:
                    self.status_reporter.publish_stream_status(
                        stream_id, 'ERROR', f'Failed to download video files: {str(e)}'
                    )

                return False

            if not local_files:
                error_msg = f"Stream {stream_id}: No valid files prepared"
                logging.error(error_msg)

                # Report error status to Laravel
                if self.status_reporter:
                    self.status_reporter.publish_stream_status(
                        stream_id, 'ERROR', 'No valid video files could be prepared for streaming'
                    )

                return False

            # Create playlist if multiple files
            if len(local_files) > 1:
                playlist_path = self.file_manager.create_playlist(stream_id, local_files)
                if playlist_path:
                    stream_info.config.playlist_path = playlist_path
                    stream_info.config.local_files = local_files
                    logging.info(f"📋 Stream {stream_id}: Created playlist with {len(local_files)} files")
                else:
                    error_msg = f"Failed to create playlist for stream {stream_id}"
                    logging.error(error_msg)

                    # Report error status to Laravel
                    if self.status_reporter:
                        self.status_reporter.publish_stream_status(
                            stream_id, 'ERROR', 'Failed to create video playlist'
                        )

                    return False
            else:
                # Single file
                stream_info.config.playlist_path = local_files[0]
                stream_info.config.local_files = local_files
                logging.info(f"📁 Stream {stream_id}: Using single file: {local_files[0]}")

            logging.info(f"✅ Stream {stream_id}: Files prepared successfully")
            return True

        except Exception as e:
            logging.error(f"❌ Error preparing files for stream {stream_info.config.id}: {e}")
            return False

    def _start_hls_pipeline(self, stream_info: EnhancedStreamInfo) -> bool:
        """Start HLS pipeline for stream"""
        try:
            stream_id = stream_info.config.id
            input_path = stream_info.config.playlist_path

            if not input_path:
                logging.error(f"Stream {stream_id}: No input path available")
                return False

            # Update state
            stream_info.state = EnhancedStreamState.HLS_GENERATING

            # Build stream config for HLS manager
            hls_config = {
                'rtmp_url': stream_info.config.rtmp_url,
                'stream_key': stream_info.config.stream_key,
                'hls_segment_duration': stream_info.config.hls_segment_duration,
                'hls_playlist_size': stream_info.config.hls_playlist_size
            }

            # Start HLS pipeline
            success = self.hls_process_manager.start_hls_pipeline(
                stream_id, input_path, hls_config
            )

            if success:
                stream_info.hls_pipeline_active = True
                logging.info(f"✅ HLS pipeline started for stream {stream_id}")
            else:
                logging.error(f"❌ Failed to start HLS pipeline for stream {stream_id}")

            return success

        except Exception as e:
            logging.error(f"❌ Error starting HLS pipeline for stream {stream_info.config.id}: {e}")
            return False

    def _cleanup_stream_files(self, stream_info: EnhancedStreamInfo):
        """Cleanup stream files"""
        try:
            stream_id = stream_info.config.id

            # Cleanup downloaded files
            if hasattr(self.file_manager, 'cleanup_stream_files'):
                self.file_manager.cleanup_stream_files(stream_id)

            logging.info(f"🧹 Cleaned up files for stream {stream_id}")

        except Exception as e:
            logging.warning(f"⚠️ Error cleaning up files for stream {stream_info.config.id}: {e}")

    def _get_restart_lock(self, stream_id: int) -> threading.Lock:
        """Get or create restart lock for stream"""
        with self.restart_lock_mutex:
            if stream_id not in self.restart_locks:
                self.restart_locks[stream_id] = threading.Lock()
            return self.restart_locks[stream_id]

    def _cleanup_restart_lock(self, stream_id: int):
        """Cleanup restart lock when stream is removed"""
        with self.restart_lock_mutex:
            if stream_id in self.restart_locks:
                del self.restart_locks[stream_id]

    def _start_health_monitoring(self):
        """Start health monitoring thread"""
        if self.health_monitor_running:
            return

        self.health_monitor_running = True
        self.health_monitor_thread = threading.Thread(
            target=self._health_monitor_loop,
            daemon=True,
            name="HealthMonitor"
        )
        self.health_monitor_thread.start()
        logging.info("🏥 Health monitoring started")

    def _stop_health_monitoring(self):
        """Stop health monitoring thread"""
        self.health_monitor_running = False
        if self.health_monitor_thread:
            self.health_monitor_thread.join(timeout=5)
        logging.info("🏥 Health monitoring stopped")

    def _health_monitor_loop(self):
        """Health monitoring loop"""
        while self.health_monitor_running:
            try:
                self._update_stream_health()
                time.sleep(30)  # Check every 30 seconds
            except Exception as e:
                logging.error(f"❌ Health monitoring error: {e}")
                time.sleep(30)

    def _update_stream_health(self):
        """Update health scores for all streams"""
        with self.stream_lock:
            for stream_id, stream_info in self.streams.items():
                try:
                    # Get HLS pipeline status
                    hls_status = self.hls_process_manager.get_pipeline_status(stream_id)

                    if not hls_status:
                        # No HLS pipeline - unhealthy
                        stream_info.health_score = 0.1
                        continue

                    # Calculate health based on stages
                    hls_health = hls_status.get('stages', {}).get('hls_generator', {}).get('health_score', 0)
                    rtmp_health = hls_status.get('stages', {}).get('rtmp_streamer', {}).get('health_score', 0)

                    # Overall health is average of both stages
                    if hls_health > 0 and rtmp_health > 0:
                        stream_info.health_score = (hls_health + rtmp_health) / 2
                    elif hls_health > 0 or rtmp_health > 0:
                        stream_info.health_score = max(hls_health, rtmp_health) * 0.5  # Penalty for one stage down
                    else:
                        stream_info.health_score = 0.1

                    # Update uptime
                    if stream_info.start_time:
                        stream_info.uptime = time.time() - stream_info.start_time

                    # Log unhealthy streams
                    if stream_info.health_score < 0.5:
                        logging.warning(f"🏥 Stream {stream_id} health score: {stream_info.health_score:.2f}")

                except Exception as e:
                    logging.error(f"❌ Error updating health for stream {stream_id}: {e}")

    def _cleanup_temp_files(self, temp_files: List[str]):
        """Cleanup temporary files on failure"""
        try:
            for temp_file in temp_files:
                if os.path.exists(temp_file):
                    os.remove(temp_file)
                    logging.info(f"🧹 Cleaned up temp file: {temp_file}")
        except Exception as e:
            logging.warning(f"⚠️ Error cleaning up temp files: {e}")

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


# Global instance
enhanced_stream_manager: Optional[EnhancedStreamManager] = None


def init_enhanced_stream_manager():
    """Initialize global enhanced stream manager"""
    global enhanced_stream_manager
    enhanced_stream_manager = EnhancedStreamManager()
    return enhanced_stream_manager


def get_enhanced_stream_manager() -> EnhancedStreamManager:
    """Get global enhanced stream manager instance"""
    if enhanced_stream_manager is None:
        raise RuntimeError("Enhanced Stream Manager not initialized. Call init_enhanced_stream_manager() first.")
    return enhanced_stream_manager
