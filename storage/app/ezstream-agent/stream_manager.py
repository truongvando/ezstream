#!/usr/bin/env python3
"""
EZStream Agent Stream Manager
Manages streams, playlists, and updates
"""

import os
import time
import logging
import threading
import tempfile
# Note: No concurrent imports needed - file_manager handles concurrency
from typing import Dict, Optional, List, Any
from dataclasses import dataclass, field
from enum import Enum

from config import get_config
from process_manager import init_process_manager
from status_reporter import get_status_reporter
from file_manager import get_file_manager


class StreamState(Enum):
    """Stream states"""
    STARTING = "STARTING"
    STREAMING = "STREAMING"
    RECONNECTING = "RECONNECTING"
    STOPPING = "STOPPING"
    STOPPED = "STOPPED"
    ERROR = "ERROR"
    DEAD = "DEAD"


@dataclass
class StreamInfo:
    """Stream information"""
    stream_id: int
    rtmp_endpoint: str
    state: StreamState = StreamState.STARTING
    start_time: float = field(default_factory=time.time)
    error_message: Optional[str] = None

    # Playlist support
    video_files: List[str] = field(default_factory=list)
    playlist_path: Optional[str] = None
    loop_playlist: bool = True
    cleanup_files_after_stop: bool = False


class StreamManager:
    """Manages streams with concurrent playlist support"""

    def __init__(self):
        self.config = get_config()
        self.status_reporter = get_status_reporter()

        # Initialize file manager if not already initialized
        try:
            self.file_manager = get_file_manager()
        except RuntimeError:
            # File manager not initialized, initialize it
            from file_manager import init_file_manager
            init_file_manager()
            self.file_manager = get_file_manager()

        # Initialize process manager
        self.process_manager = init_process_manager()

        # Stream tracking
        self.streams: Dict[int, StreamInfo] = {}
        self.stream_lock = threading.RLock()

        # Note: file_manager handles downloads and validation

        logging.info("🎬 Stream Manager initialized")
    
    def start_stream(self, stream_id: int, video_files: List[str], stream_config: Dict[str, Any],
                    rtmp_endpoint: Optional[str] = None) -> bool:
        """Start stream with concurrent playlist support"""
        try:
            with self.stream_lock:
                # Check if already running
                if stream_id in self.streams:
                    logging.warning(f"Stream {stream_id} already running")
                    return False

                # No stream limit - Laravel manages VPS resources

                # Process video files (download URLs, validate local files)
                if not video_files:
                    logging.error(f"Stream {stream_id}: No video files provided")
                    return False

                # Download and validate files
                local_video_files = self._download_and_validate_files(video_files, stream_id)
                if not local_video_files:
                    return False

                # Build RTMP endpoint
                if rtmp_endpoint is None:
                    rtmp_endpoint = self._build_youtube_rtmp_endpoint(stream_config)

                # Handle single video vs playlist
                playlist_path = None
                if len(local_video_files) > 1:
                    # Multiple videos - create playlist
                    playlist_path = self._create_playlist_file(stream_id, local_video_files)
                    if not playlist_path:
                        return False

                # Create stream info
                stream_info = StreamInfo(
                    stream_id=stream_id,
                    rtmp_endpoint=rtmp_endpoint,
                    video_files=local_video_files,  # Use local files
                    playlist_path=playlist_path,  # None for single video
                    loop_playlist=stream_config.get('loop', True),
                    cleanup_files_after_stop=stream_config.get('cleanup_files_after_stop', False)
                )
                
                self.streams[stream_id] = stream_info
                
                logging.info(f"🎬 Starting stream {stream_id} with {len(video_files)} videos → {rtmp_endpoint}")
                
                # Report starting status
                if self.status_reporter:
                    self.status_reporter.publish_stream_status(
                        stream_id, 'STARTING', f'Starting stream with {len(video_files)} videos'
                    )
                
                # Build FFmpeg command
                ffmpeg_command = self._build_ffmpeg_command(stream_info)
                
                # Start FFmpeg process
                if not self.process_manager.start_process(stream_id, ffmpeg_command):
                    self._cleanup_playlist_file(stream_info)
                    del self.streams[stream_id]
                    return False
                
                # Update state
                stream_info.state = StreamState.STREAMING

                # Report success
                if self.status_reporter:
                    self.status_reporter.publish_stream_status(
                        stream_id, 'STREAMING',
                        f'Stream started with {len(video_files)} videos'
                    )

                logging.info(f"✅ Stream {stream_id} started successfully")
                return True
                
        except Exception as e:
            logging.error(f"❌ Error starting stream {stream_id}: {e}")
            if stream_id in self.streams:
                del self.streams[stream_id]
            return False
    
    def stop_stream(self, stream_id: int, reason: str = "manual") -> bool:
        """Stop stream"""
        try:
            with self.stream_lock:
                if stream_id not in self.streams:
                    logging.warning(f"Stream {stream_id} not found")
                    return True
                
                stream_info = self.streams[stream_id]
                stream_info.state = StreamState.STOPPING
                
                logging.info(f"🛑 Stopping stream {stream_id} (reason: {reason})")
                
                # Report stopping status
                if self.status_reporter:
                    self.status_reporter.publish_stream_status(
                        stream_id, 'STOPPING', f'Stopping stream - {reason}'
                    )
                
                # Stop FFmpeg process
                success = self.process_manager.stop_process(stream_id, reason)
                
                # Cleanup playlist file (if exists)
                if stream_info.playlist_path:
                    self._cleanup_playlist_file(stream_info)
                
                # Remove from tracking
                del self.streams[stream_id]
                
                # Report final status
                if self.status_reporter:
                    status = 'STOPPED' if success else 'ERROR'
                    message = f'Stream stopped - {reason}' if success else f'Error stopping stream - {reason}'
                    self.status_reporter.publish_stream_status(stream_id, status, message)
                
                logging.info(f"✅ Stream {stream_id} stopped")
                return success
                
        except Exception as e:
            logging.error(f"❌ Error stopping stream {stream_id}: {e}")
            return False
    
    def restart_stream(self, stream_id: int, new_video_files: List[str] = None, 
                      new_config: Dict[str, Any] = None) -> bool:
        """Restart stream with new configuration"""
        try:
            logging.info(f"🔄 Restarting stream {stream_id}")
            
            # Get current config if new config not provided
            with self.stream_lock:
                if stream_id not in self.streams:
                    logging.error(f"Stream {stream_id} not found for restart")
                    return False
                
                current_stream = self.streams[stream_id]
                if new_video_files is None:
                    new_video_files = current_stream.video_files
                if new_config is None:
                    new_config = {'loop': current_stream.loop_playlist}
                
                # Get current RTMP endpoint
                rtmp_endpoint = current_stream.rtmp_endpoint
                
                # Stop current stream
                self.stop_stream(stream_id, "restart")
            
            # Small delay before restart
            time.sleep(1)
            
            # Start with new configuration
            return self.start_stream(stream_id, new_video_files, new_config, rtmp_endpoint)
            
        except Exception as e:
            logging.error(f"❌ Error restarting stream {stream_id}: {e}")
            return False
    
    def update_stream(self, stream_id: int, new_video_files: List[str], 
                     update_mode: str = "replace") -> bool:
        """Update stream playlist
        
        Args:
            stream_id: Stream ID to update
            new_video_files: New video files to add/replace
            update_mode: 'replace', 'append', or 'prepend'
        """
        try:
            with self.stream_lock:
                if stream_id not in self.streams:
                    logging.error(f"Stream {stream_id} not found for update")
                    return False
                
                stream_info = self.streams[stream_id]
                
                # Download and validate new video files
                local_new_files = self._download_and_validate_files(new_video_files, stream_id)
                if not local_new_files:
                    return False
                
                # Update video files list based on mode (use local files)
                if update_mode == "replace":
                    stream_info.video_files = local_new_files
                elif update_mode == "append":
                    stream_info.video_files.extend(local_new_files)
                elif update_mode == "prepend":
                    stream_info.video_files = local_new_files + stream_info.video_files
                else:
                    logging.error(f"Invalid update mode: {update_mode}")
                    return False
                
                logging.info(f"🔄 Updating stream {stream_id}: {update_mode} {len(new_video_files)} videos")
                
                # Create new playlist file
                new_playlist_path = self._create_playlist_file(stream_id, stream_info.video_files)
                if not new_playlist_path:
                    return False
                
                # Cleanup old playlist (if exists)
                if stream_info.playlist_path:
                    self._cleanup_playlist_file(stream_info)
                stream_info.playlist_path = new_playlist_path
                
                # Restart stream with new playlist
                return self.restart_stream(stream_id, stream_info.video_files, {})
                
        except Exception as e:
            logging.error(f"❌ Error updating stream {stream_id}: {e}")
            return False

    def get_stream_status(self, stream_id: int) -> Optional[Dict[str, Any]]:
        """Get stream status"""
        with self.stream_lock:
            if stream_id not in self.streams:
                return None

            stream_info = self.streams[stream_id]

            # Get process status
            process_status = self.process_manager.get_process_status(stream_id)

            return {
                'stream_id': stream_id,
                'state': stream_info.state.value,
                'uptime': time.time() - stream_info.start_time,
                'error_message': stream_info.error_message,
                'playlist': {
                    'total_videos': len(stream_info.video_files),
                    'loop_enabled': stream_info.loop_playlist,
                    'video_files': stream_info.video_files
                },
                'process': process_status
            }

    def get_active_streams(self) -> List[int]:
        """Get list of active stream IDs"""
        with self.stream_lock:
            return list(self.streams.keys())

    def stop_all(self):
        """Stop all streams and cleanup"""
        logging.info("🛑 Stopping all streams...")

        # Stop all streams
        with self.stream_lock:
            stream_ids = list(self.streams.keys())

        for stream_id in stream_ids:
            try:
                self.stop_stream(stream_id, "shutdown")
            except Exception as e:
                logging.error(f"❌ Error stopping stream {stream_id} during shutdown: {e}")

        # Stop process manager
        self.process_manager.stop_all()

        # Note: file_manager handles its own thread pool cleanup

        logging.info("✅ Stream Manager stopped")



    def _build_youtube_rtmp_endpoint(self, stream_config: Dict[str, Any]) -> str:
        """Build YouTube RTMP endpoint from config"""
        rtmp_url = stream_config.get('rtmp_url', 'rtmp://a.rtmp.youtube.com/live2/')
        stream_key = stream_config.get('stream_key', '')

        if not stream_key:
            raise ValueError("Stream key is required for YouTube RTMP")

        # Check if rtmp_url already contains the stream key
        if stream_key in rtmp_url:
            logging.info(f"Using complete RTMP URL")
            return rtmp_url

        # Build URL from base + stream key
        if not rtmp_url.endswith('/'):
            rtmp_url += '/'

        complete_url = f"{rtmp_url}{stream_key}"
        logging.info(f"Built RTMP URL for stream")
        return complete_url

    def _create_playlist_file(self, stream_id: int, video_files: List[str]) -> Optional[str]:
        """Create FFmpeg concat playlist file"""
        try:
            # Create temporary playlist file
            playlist_fd, playlist_path = tempfile.mkstemp(suffix=f'_stream_{stream_id}.txt', prefix='ezstream_playlist_')

            with os.fdopen(playlist_fd, 'w', encoding='utf-8') as f:
                for video_file in video_files:
                    # Convert to absolute path and normalize for Windows
                    abs_path = os.path.abspath(video_file)

                    # For Windows, convert backslashes to forward slashes for FFmpeg
                    if os.name == 'nt':
                        abs_path = abs_path.replace('\\', '/')

                    # Escape single quotes for FFmpeg concat
                    escaped_path = abs_path.replace("'", "'\"'\"'")
                    f.write(f"file '{escaped_path}'\n")

            logging.info(f"Stream {stream_id}: Created playlist with {len(video_files)} videos: {playlist_path}")
            return playlist_path

        except Exception as e:
            logging.error(f"Stream {stream_id}: Error creating playlist: {e}")
            return None

    def _cleanup_playlist_file(self, stream_info: StreamInfo):
        """Cleanup playlist file"""
        try:
            if stream_info.playlist_path and os.path.exists(stream_info.playlist_path):
                os.remove(stream_info.playlist_path)
                logging.debug(f"Stream {stream_info.stream_id}: Cleaned up playlist file")
        except Exception as e:
            logging.warning(f"Stream {stream_info.stream_id}: Error cleaning up playlist: {e}")

    def _validate_files(self, video_files: List[str], stream_id: int) -> bool:
        """Validate multiple video files using file_manager"""
        try:
            logging.info(f"Stream {stream_id}: Validating {len(video_files)} files...")

            # Use file_manager's validation (more comprehensive)
            failed_files = []
            for video_file in video_files:
                if not self.file_manager.validate_video_file(video_file):
                    failed_files.append(video_file)

            if failed_files:
                logging.error(f"Stream {stream_id}: Validation failed for {len(failed_files)} files: {failed_files}")
                return False

            logging.info(f"Stream {stream_id}: All {len(video_files)} files validated successfully")
            return True

        except Exception as e:
            logging.error(f"Stream {stream_id}: Validation error: {e}")
            return False

    def _download_and_validate_files(self, video_files: List[str], stream_id: int) -> List[str]:
        """Process video files - download URLs, validate local files"""
        try:
            # Separate local files and URLs
            local_files = []
            urls_to_download = []

            for video_file in video_files:
                if video_file.startswith(('http://', 'https://')):
                    urls_to_download.append(video_file)
                else:
                    local_files.append(video_file)

            # Download URLs if any
            downloaded_files = []
            if urls_to_download:
                # Convert URLs to Laravel format for file_manager
                laravel_format = []
                for i, url in enumerate(urls_to_download):
                    laravel_format.append({
                        'file_id': i + 1000,
                        'filename': os.path.basename(url) or f"video_{i+1}.mp4",
                        'download_url': url,
                        'size': 0,
                        'disk': 'remote'
                    })
                downloaded_files = self.file_manager.download_files(stream_id, laravel_format)

            # Combine all files
            all_files = local_files + downloaded_files

            # Validate all files
            if not self._validate_files(all_files, stream_id):
                return []

            return all_files

        except Exception as e:
            logging.error(f"Stream {stream_id}: Error processing files: {e}")
            return []



    def _build_ffmpeg_command(self, stream_info: StreamInfo) -> List[str]:
        """Build optimized FFmpeg command for direct YouTube streaming

        ENCODING MODE vs COPY MODE:

        🎬 COPY MODE (ffmpeg_use_encoding=False):
        - Copies video/audio streams without re-encoding
        - Much faster, lower CPU usage
        - Requires videos to already be YouTube-compatible
        - Command: -c:v copy -c:a copy
        - Best for: Pre-optimized videos from video_optimizer.py

        🎨 ENCODING MODE (ffmpeg_use_encoding=True):
        - Re-encodes video/audio to YouTube specs
        - Higher CPU usage but guaranteed compatibility
        - Command: -c:v libx264 -profile:v high -b:v 5000k -c:a aac -b:a 128k
        - Best for: Raw videos that need optimization

        ⚠️ RTMP Fix: Minimal parameters to avoid 'Cannot assign requested address' error
        """

        cmd = [
            'ffmpeg',
            '-hide_banner',
            '-loglevel', 'error',
            '-re',  # Realtime playback
        ]

        # Input settings - different for single video vs playlist
        if stream_info.playlist_path:
            # Multiple videos - use playlist
            cmd.extend([
                '-f', 'concat',
                '-safe', '0',
            ])
            # Loop settings
            if stream_info.loop_playlist:
                cmd.extend(['-stream_loop', '-1'])
            # Input playlist file
            cmd.extend(['-i', stream_info.playlist_path])
        else:
            # Single video - direct input
            # Loop settings
            if stream_info.loop_playlist:
                cmd.extend(['-stream_loop', '-1'])
            # Input single video file
            cmd.extend(['-i', stream_info.video_files[0]])

        # Video encoding settings - YouTube optimized
        if self.config.ffmpeg_use_encoding:
            # ENCODING MODE: Re-encode for guaranteed YouTube compatibility
            cmd.extend([
                # Video codec - H.264 High Profile for YouTube
                '-c:v', 'libx264',
                '-profile:v', 'high',
                '-level', '4.0',

                # Bitrate settings - YouTube FHD recommended
                '-b:v', '5000k',  # 5 Mbps for FHD
                '-maxrate', '6000k',
                '-bufsize', '12000k',
                '-r', '30',  # 30 FPS

                # GOP settings for streaming
                '-g', '60',  # GOP size: 2 seconds at 30fps
                '-keyint_min', '60',
                '-sc_threshold', '0',  # Disable scene change detection

                # No B-frames for streaming
                '-bf', '0',

                # Preset for encoding speed vs quality
                '-preset', 'fast',
                '-tune', 'zerolatency',

                # Audio codec - AAC-LC for YouTube
                '-c:a', 'aac',
                '-b:a', '128k',
                '-ar', '44100',
                '-ac', '2',
            ])
        else:
            # COPY MODE: Copy streams for performance (videos already optimized)
            cmd.extend([
                '-c:v', 'copy',
                '-c:a', 'copy',

                # Fix H.264 headers for copy mode
                '-bsf:v', 'h264_mp4toannexb',
            ])

        # Container and streaming settings
        if stream_info.rtmp_endpoint.startswith('rtmp://'):
            # RTMP streaming - only essential parameters to avoid conflicts
            cmd.extend([
                # Container format for RTMP
                '-f', 'flv',

                # Essential RTMP settings (tested to not cause parameter conflicts)
                '-rtmp_live', 'live',
            ])
        else:
            # File output (for testing)
            cmd.extend([
                '-f', 'flv',
                '-t', '5',  # Limit to 5 seconds for testing
            ])

        # Output destination
        cmd.append(stream_info.rtmp_endpoint)

        return cmd




# Global instance management
_stream_manager: Optional['StreamManager'] = None


def init_stream_manager() -> 'StreamManager':
    """Initialize global stream manager"""
    global _stream_manager
    _stream_manager = StreamManager()
    return _stream_manager


def get_stream_manager() -> 'StreamManager':
    """Get global stream manager instance"""
    if _stream_manager is None:
        raise RuntimeError("Stream manager not initialized. Call init_stream_manager() first.")
    return _stream_manager
