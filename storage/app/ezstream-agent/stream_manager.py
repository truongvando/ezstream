#!/usr/bin/env python3
"""
EZStream Agent Stream Manager
Manages complete stream lifecycle with proper state management
"""

import os
import time
import logging
import threading
from typing import Dict, List, Optional, Any
from dataclasses import dataclass, field
from enum import Enum

from config import get_config
from utils import PerformanceTimer, ensure_directory
from status_reporter import get_status_reporter
from process_manager import get_process_manager
from file_manager import get_file_manager


class StreamState(Enum):
    """Stream state enumeration"""
    INACTIVE = "INACTIVE"
    STARTING = "STARTING"
    DOWNLOADING = "DOWNLOADING"
    STREAMING = "STREAMING"
    UPDATING = "UPDATING"
    STOPPING = "STOPPING"
    ERROR = "ERROR"


@dataclass
class StreamConfig:
    """Stream configuration data"""
    id: int
    video_files: List[Dict[str, Any]]
    rtmp_url: str
    push_urls: Optional[List[str]] = None
    loop: bool = True
    keep_files_after_stop: bool = False
    playlist_order: str = "sequential"  # sequential or random
    
    # Runtime data
    local_files: List[str] = field(default_factory=list)
    playlist_path: Optional[str] = None
    created_at: float = field(default_factory=time.time)
    updated_at: float = field(default_factory=time.time)


@dataclass
class StreamInfo:
    """Complete stream information"""
    config: StreamConfig
    state: StreamState
    start_time: Optional[float] = None
    error_message: Optional[str] = None
    last_update: float = field(default_factory=time.time)


class StreamManager:
    """Manages complete stream lifecycle and state"""
    
    def __init__(self):
        self.config = get_config()
        self.status_reporter = get_status_reporter()
        self.process_manager = get_process_manager()
        self.file_manager = get_file_manager()
        
        # Stream tracking
        self.streams: Dict[int, StreamInfo] = {}
        self.stream_lock = threading.RLock()

        # Restart operation locks to prevent conflicts
        self.restart_locks = {}  # Per-stream locks for restart operations
        self.restart_lock_mutex = threading.Lock()  # Protect restart_locks dict

        logging.info("🎬 Stream manager initialized with conflict prevention")

    def _get_restart_lock(self, stream_id: int) -> threading.Lock:
        """Get or create restart lock for stream to prevent conflicts"""
        with self.restart_lock_mutex:
            if stream_id not in self.restart_locks:
                self.restart_locks[stream_id] = threading.Lock()
            return self.restart_locks[stream_id]

    def _cleanup_restart_lock(self, stream_id: int):
        """Cleanup restart lock when stream is removed"""
        with self.restart_lock_mutex:
            if stream_id in self.restart_locks:
                del self.restart_locks[stream_id]

    def start_stream(self, stream_config_data: Dict[str, Any]) -> bool:
        """Start a new stream with complete lifecycle management"""
        stream_id = stream_config_data.get('id')
        if not stream_id:
            logging.error("No stream ID provided")
            return False
        
        try:
            with self.stream_lock:
                # Check if stream already exists
                if stream_id in self.streams:
                    logging.warning(f"Stream {stream_id} already exists")
                    return False
                
                # Create stream config
                stream_config = StreamConfig(
                    id=stream_id,
                    video_files=stream_config_data.get('video_files', []),
                    rtmp_url=stream_config_data.get('rtmp_url', ''),
                    push_urls=stream_config_data.get('push_urls'),
                    loop=stream_config_data.get('loop', True),
                    keep_files_after_stop=stream_config_data.get('keep_files_after_stop', False),
                    playlist_order=stream_config_data.get('playlist_order', 'sequential')
                )
                
                # Create stream info
                stream_info = StreamInfo(
                    config=stream_config,
                    state=StreamState.STARTING,
                    start_time=time.time()
                )
                
                self.streams[stream_id] = stream_info
                
                # Report starting status
                if self.status_reporter:
                    self.status_reporter.publish_stream_status(
                        stream_id, 'STARTING', 'Đang khởi tạo stream...'
                    )
                
                # Start stream in background
                threading.Thread(
                    target=self._start_stream_async,
                    args=(stream_info,),
                    name=f"StreamStarter-{stream_id}",
                    daemon=True
                ).start()
                
                return True
                
        except Exception as e:
            logging.error(f"❌ Error starting stream {stream_id}: {e}")
            if self.status_reporter:
                self.status_reporter.publish_stream_status(
                    stream_id, 'ERROR', f'Lỗi khởi tạo stream: {str(e)}'
                )
            return False
    
    def stop_stream(self, stream_id: int, reason: str = "manual") -> bool:
        """Stop a running stream"""
        try:
            with self.stream_lock:
                if stream_id not in self.streams:
                    logging.warning(f"Stream {stream_id} not found")
                    return True
                
                stream_info = self.streams[stream_id]
                stream_info.state = StreamState.STOPPING
                stream_info.last_update = time.time()
                
                # Report stopping status for manual or command stops
                if self.status_reporter and reason in ["manual", "command"]:
                    self.status_reporter.publish_stream_status(
                        stream_id, 'STOPPING', 'Đang dừng stream...'
                    )
                
                # Stop FFmpeg process
                success = True
                if self.process_manager:
                    success = self.process_manager.stop_ffmpeg(stream_id, reason)
                
                # Cleanup files if needed
                if self.file_manager and not stream_info.config.keep_files_after_stop:
                    self.file_manager.cleanup_stream_files(stream_id)
                
                # Remove nginx config
                self._cleanup_nginx_config(stream_id)
                
                # Remove from tracking
                del self.streams[stream_id]
                # Cleanup restart lock
                self._cleanup_restart_lock(stream_id)

                logging.info(f"✅ Stream {stream_id} stopped (reason: {reason})")

                # Always report STOPPED status for command stops to ensure Laravel knows
                if self.status_reporter and reason in ["manual", "command"]:
                    self.status_reporter.publish_stream_status(
                        stream_id, 'STOPPED', 'Stream đã được dừng'
                    )

                    # Force immediate heartbeat update to reflect the change
                    logging.info(f"🔄 Triggering immediate heartbeat update after stopping stream {stream_id}")
                    self._trigger_immediate_heartbeat()
                
                return success
                
        except Exception as e:
            logging.error(f"❌ Error stopping stream {stream_id}: {e}")
            return False
    
    def update_stream(self, stream_id: int, new_config_data: Dict[str, Any]) -> bool:
        """Update running stream with new configuration"""
        try:
            with self.stream_lock:
                if stream_id not in self.streams:
                    logging.warning(f"Stream {stream_id} not found for update")
                    return False
                
                stream_info = self.streams[stream_id]
                stream_info.state = StreamState.UPDATING
                stream_info.last_update = time.time()
                
                # Report update status
                if self.status_reporter:
                    self.status_reporter.publish_stream_status(
                        stream_id, 'PROGRESS', 'Đang cập nhật stream - tải file mới...',
                        extra_data={'progress_data': {'stage': 'starting_update', 'progress_percentage': 5}}
                    )
                
                # Start update in background
                threading.Thread(
                    target=self._update_stream_async,
                    args=(stream_info, new_config_data),
                    name=f"StreamUpdater-{stream_id}",
                    daemon=True
                ).start()
                
                return True
                
        except Exception as e:
            logging.error(f"❌ Error updating stream {stream_id}: {e}")
            if self.status_reporter:
                self.status_reporter.publish_stream_status(
                    stream_id, 'ERROR', f'Lỗi cập nhật stream: {str(e)}'
                )
            return False
    
    def get_stream_info(self, stream_id: int) -> Optional[StreamInfo]:
        """Get stream information"""
        with self.stream_lock:
            return self.streams.get(stream_id)
    
    def get_active_stream_ids(self) -> List[int]:
        """Get list of active stream IDs"""
        with self.stream_lock:
            return [
                stream_id for stream_id, info in self.streams.items()
                if info.state in [StreamState.STARTING, StreamState.DOWNLOADING, StreamState.STREAMING, StreamState.UPDATING]
            ]
    
    def get_all_streams(self) -> Dict[int, StreamInfo]:
        """Get all streams (copy)"""
        with self.stream_lock:
            return self.streams.copy()
    
    def stop_all_streams(self):
        """Stop all running streams"""
        logging.info("🛑 Stopping all streams...")
        
        with self.stream_lock:
            stream_ids = list(self.streams.keys())
        
        for stream_id in stream_ids:
            self.stop_stream(stream_id, "shutdown")
        
        logging.info("✅ All streams stopped")
    
    def _start_stream_async(self, stream_info: StreamInfo):
        """Asynchronously start stream with full lifecycle"""
        stream_id = stream_info.config.id
        
        try:
            with PerformanceTimer(f"Stream Start (Stream {stream_id})"):
                # Step 1: Download files
                stream_info.state = StreamState.DOWNLOADING
                if self.status_reporter:
                    self.status_reporter.publish_stream_status(
                        stream_id, 'PROGRESS', 'Đang tải files...',
                        extra_data={'progress_data': {'stage': 'downloading', 'progress_percentage': 20}}
                    )
                
                if self.file_manager:
                    local_files = self.file_manager.download_files(stream_id, stream_info.config.video_files)
                    if not local_files:
                        raise Exception("No files were downloaded successfully - files may have been deleted or are inaccessible")
                    stream_info.config.local_files = local_files
                else:
                    raise Exception("File manager not available")
                
                # Step 2: Create playlist or use single file
                if len(local_files) == 1:
                    input_path = local_files[0]
                    logging.info(f"Stream {stream_id}: Using single file mode")
                else:
                    if self.status_reporter:
                        self.status_reporter.publish_stream_status(
                            stream_id, 'PROGRESS', 'Đang tạo playlist...',
                            extra_data={'progress_data': {'stage': 'creating_playlist', 'progress_percentage': 60}}
                        )
                    
                    input_path = self.file_manager.create_playlist(stream_id, local_files)
                    stream_info.config.playlist_path = input_path
                    logging.info(f"Stream {stream_id}: Using playlist mode with {len(local_files)} files")
                
                # Step 3: Create nginx config
                self._create_nginx_config(stream_info)
                
                # Step 4: Start FFmpeg
                if self.status_reporter:
                    self.status_reporter.publish_stream_status(
                        stream_id, 'PROGRESS', 'Đang khởi động FFmpeg...',
                        extra_data={'progress_data': {'stage': 'starting_ffmpeg', 'progress_percentage': 80}}
                    )
                
                if self.process_manager:
                    success = self.process_manager.start_ffmpeg(
                        stream_id, input_path, stream_info.config.__dict__
                    )
                    
                    if success:
                        stream_info.state = StreamState.STREAMING
                        if self.status_reporter:
                            self.status_reporter.publish_stream_status(
                                stream_id, 'STREAMING', f'Stream đang phát với {len(local_files)} files'
                            )
                        logging.info(f"✅ Stream {stream_id} started successfully")
                    else:
                        raise Exception("Failed to start FFmpeg process")
                else:
                    raise Exception("Process manager not available")
                
        except Exception as e:
            logging.error(f"❌ Failed to start stream {stream_id}: {e}")
            stream_info.state = StreamState.ERROR
            stream_info.error_message = str(e)
            
            if self.status_reporter:
                self.status_reporter.publish_stream_status(
                    stream_id, 'ERROR', f'Lỗi khởi động stream: {str(e)}'
                )
            
            # Cleanup on failure
            with self.stream_lock:
                if stream_id in self.streams:
                    del self.streams[stream_id]
                    # Cleanup restart lock
                    self._cleanup_restart_lock(stream_id)
    
    def _update_stream_async(self, stream_info: StreamInfo, new_config_data: Dict[str, Any]):
        """Asynchronously update stream"""
        stream_id = stream_info.config.id
        
        try:
            with PerformanceTimer(f"Stream Update (Stream {stream_id})"):
                # Update config
                new_video_files = new_config_data.get('video_files', [])
                
                # Download new files
                if self.file_manager:
                    local_files = self.file_manager.download_files(stream_id, new_video_files)
                else:
                    raise Exception("File manager not available")
                
                # Create new input path
                if len(local_files) == 1:
                    new_input_path = local_files[0]
                else:
                    if self.status_reporter:
                        self.status_reporter.publish_stream_status(
                            stream_id, 'PROGRESS', 'Đang tạo playlist mới...',
                            extra_data={'progress_data': {'stage': 'creating_playlist', 'progress_percentage': 65}}
                        )
                    
                    new_input_path = self.file_manager.create_playlist(stream_id, local_files)
                
                # Restart FFmpeg with new config
                if self.status_reporter:
                    self.status_reporter.publish_stream_status(
                        stream_id, 'PROGRESS', 'Đang khởi động lại stream...',
                        extra_data={'progress_data': {'stage': 'restarting_stream', 'progress_percentage': 90}}
                    )
                
                if self.process_manager:
                    # Use centralized restart to prevent conflicts with fast restart
                    success = self.process_manager.centralized_restart(
                        stream_id, "USER_UPDATE", new_input_path, new_config_data
                    )
                    
                    if success:
                        # Update stream config
                        stream_info.config.video_files = new_video_files
                        stream_info.config.local_files = local_files
                        stream_info.config.playlist_path = new_input_path
                        stream_info.config.updated_at = time.time()
                        stream_info.state = StreamState.STREAMING
                        
                        if self.status_reporter:
                            self.status_reporter.publish_stream_status(
                                stream_id, 'STREAMING', f'Stream đã được cập nhật với {len(local_files)} files'
                            )
                        logging.info(f"✅ Stream {stream_id} updated successfully")
                    else:
                        raise Exception("Failed to restart FFmpeg with new config")
                else:
                    raise Exception("Process manager not available")
                
        except Exception as e:
            logging.error(f"❌ Failed to update stream {stream_id}: {e}")
            stream_info.state = StreamState.ERROR
            stream_info.error_message = str(e)
            
            if self.status_reporter:
                self.status_reporter.publish_stream_status(
                    stream_id, 'ERROR', f'Lỗi cập nhật stream: {str(e)}'
                )
    
    def _create_nginx_config(self, stream_info: StreamInfo):
        """Create nginx RTMP app configuration"""
        try:
            stream_id = stream_info.config.id
            rtmp_url = stream_info.config.rtmp_url
            
            # Create nginx app config
            app_dir = self.config.nginx_config_dir
            ensure_directory(app_dir)
            
            config_path = self.config.get_nginx_app_config_path(stream_id)
            
            config_content = f"""
application stream_{stream_id} {{
    live on;
    record off;
    allow play all;

    # Basic streaming settings
    wait_key on;            # Start with keyframe for better quality
    wait_video on;          # Wait for video before audio
    meta copy;              # Copy exact metadata

    # Push to YouTube/platform
    push {rtmp_url};
}}
"""
            
            with open(config_path, 'w') as f:
                f.write(config_content)
            
            # Reload nginx
            os.system('nginx -s reload')
            
            logging.info(f"✅ Created nginx config for stream {stream_id}")
            
        except Exception as e:
            logging.error(f"❌ Error creating nginx config for stream {stream_info.config.id}: {e}")
    
    def _cleanup_nginx_config(self, stream_id: int):
        """Remove nginx RTMP app configuration"""
        try:
            config_path = self.config.get_nginx_app_config_path(stream_id)
            
            if os.path.exists(config_path):
                os.remove(config_path)
                os.system('nginx -s reload')
                logging.info(f"🧹 Removed nginx config for stream {stream_id}")
                
        except Exception as e:
            logging.error(f"❌ Error removing nginx config for stream {stream_id}: {e}")

    def _trigger_immediate_heartbeat(self):
        """Trigger immediate heartbeat to update Laravel about stream changes"""
        try:
            if self.status_reporter:
                # Get current active streams
                active_stream_ids = self.get_active_stream_ids()

                # Create heartbeat payload
                heartbeat_payload = {
                    'type': 'HEARTBEAT',
                    'vps_id': self.config.vps_id,
                    'active_streams': active_stream_ids,
                    'timestamp': int(time.time()),
                    'immediate_update': True  # Flag to indicate this is an immediate update
                }

                # Publish directly through status reporter
                self.status_reporter._publish_report(heartbeat_payload)
                logging.info(f"📤 Immediate heartbeat sent with {len(active_stream_ids)} active streams")

        except Exception as e:
            logging.error(f"❌ Error triggering immediate heartbeat: {e}")


# Global stream manager instance
_stream_manager: Optional[StreamManager] = None


def init_stream_manager() -> StreamManager:
    """Initialize global stream manager"""
    global _stream_manager
    _stream_manager = StreamManager()
    return _stream_manager


def get_stream_manager() -> Optional[StreamManager]:
    """Get global stream manager instance"""
    return _stream_manager
