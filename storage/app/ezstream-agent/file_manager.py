#!/usr/bin/env python3
"""
EZStream Agent File Manager
Handles file downloads, playlist creation, and cleanup
"""

import os
import time
import shutil
import logging
import threading
import requests
from typing import Dict, List, Optional, Any
from concurrent.futures import ThreadPoolExecutor, as_completed
from dataclasses import dataclass

from config import get_config
from utils import (
    sanitize_filename, calculate_file_hash, format_bytes, 
    ensure_directory, PerformanceTimer, retry_operation
)
from status_reporter import get_status_reporter


@dataclass
class VideoFile:
    """Video file information"""
    file_id: int
    filename: str
    download_url: str
    size: int
    disk: str
    local_path: Optional[str] = None
    hash: Optional[str] = None


@dataclass
class DownloadResult:
    """Download operation result"""
    success: bool
    file_path: Optional[str] = None
    error: Optional[str] = None
    bytes_downloaded: int = 0
    duration: float = 0.0


class FileManager:
    """Manages file downloads, playlist creation, and cleanup"""
    
    def __init__(self):
        self.config = get_config()
        self.status_reporter = get_status_reporter()
        
        # Download tracking
        self.active_downloads: Dict[int, threading.Event] = {}
        self.download_lock = threading.RLock()
        
        # Thread pool for concurrent downloads
        self.download_executor = ThreadPoolExecutor(
            max_workers=self.config.download_thread_pool_size,
            thread_name_prefix="FileDownloader"
        )
        
        # Cleanup thread
        self.cleanup_running = False
        self.cleanup_thread = None
        
        logging.info(f"📁 File manager initialized (max downloads: {self.config.download_thread_pool_size})")
    
    def start_cleanup_service(self):
        """Start periodic cleanup service"""
        self.cleanup_running = True
        self.cleanup_thread = threading.Thread(
            target=self._periodic_cleanup_loop,
            name="FileCleanup",
            daemon=True
        )
        self.cleanup_thread.start()
        logging.info("🧹 File cleanup service started")
    
    def stop_cleanup_service(self):
        """Stop cleanup service"""
        self.cleanup_running = False
        if self.cleanup_thread:
            self.cleanup_thread.join(timeout=5)
        logging.info("🧹 File cleanup service stopped")
    
    def download_files(self, stream_id: int, video_files: List[Dict[str, Any]]) -> List[str]:
        """Download multiple files concurrently"""
        try:
            with PerformanceTimer(f"File Downloads (Stream {stream_id})"):
                # Parse video files
                files_to_download = []
                for vf in video_files:
                    video_file = VideoFile(
                        file_id=vf.get('file_id', 0),
                        filename=vf.get('filename', 'unknown'),
                        download_url=vf.get('download_url', ''),
                        size=vf.get('size', 0),
                        disk=vf.get('disk', 'unknown')
                    )
                    files_to_download.append(video_file)
                
                # Create download directory
                download_dir = self.config.get_stream_download_dir(stream_id)
                if not ensure_directory(download_dir):
                    raise Exception(f"Failed to create download directory: {download_dir}")
                
                # Download files concurrently
                local_files = []
                download_futures = {}
                
                for video_file in files_to_download:
                    safe_filename = sanitize_filename(video_file.filename)
                    local_path = os.path.join(download_dir, safe_filename)
                    video_file.local_path = local_path
                    
                    # Check if file already exists and is complete
                    if self._is_file_complete(local_path, video_file.size):
                        logging.info(f"File already exists and complete: {local_path}")
                        local_files.append(local_path)
                        continue
                    
                    # Submit download task
                    future = self.download_executor.submit(
                        self._download_file, stream_id, video_file
                    )
                    download_futures[future] = video_file
                
                # Wait for downloads to complete
                for future in as_completed(download_futures):
                    video_file = download_futures[future]
                    try:
                        result = future.result()
                        if result.success and result.file_path:
                            local_files.append(result.file_path)
                            logging.info(f"✅ Downloaded: {video_file.filename} ({format_bytes(result.bytes_downloaded)})")
                        else:
                            logging.error(f"❌ Failed to download: {video_file.filename} - {result.error}")
                    except Exception as e:
                        logging.error(f"❌ Download exception for {video_file.filename}: {e}")
                
                if not local_files:
                    raise Exception("No files were downloaded successfully")
                
                logging.info(f"✅ Downloaded {len(local_files)}/{len(files_to_download)} files for stream {stream_id}")
                return local_files
                
        except Exception as e:
            logging.error(f"❌ Error downloading files for stream {stream_id}: {e}")
            raise
    
    def create_playlist(self, stream_id: int, local_files: List[str], loop_count: Optional[int] = None) -> str:
        """Create FFmpeg concat playlist for local files"""
        try:
            with PerformanceTimer(f"Playlist Creation (Stream {stream_id})"):
                download_dir = self.config.get_stream_download_dir(stream_id)
                
                # Generate unique playlist filename with timestamp
                timestamp = int(time.time())
                playlist_path = os.path.join(download_dir, f'playlist_{timestamp}.txt')
                
                # Calculate smart loop count for long-duration streaming
                if loop_count is None:
                    estimated_duration_minutes = len(local_files) * 10  # Assume 10 min per file
                    loops_for_one_week = max(100, (7 * 24 * 60) // estimated_duration_minutes)
                    loop_count = loops_for_one_week
                
                # Create playlist content
                with open(playlist_path, 'w', encoding='utf-8') as f:
                    for loop_iteration in range(loop_count):
                        for local_file in local_files:
                            # FFmpeg concat format - escape single quotes
                            escaped_file = local_file.replace("'", "'\"'\"'")
                            f.write(f"file '{escaped_file}'\n")
                
                total_entries = len(local_files) * loop_count
                estimated_duration_hours = (total_entries * 10) / 60
                
                logging.info(f"🎬 Stream {stream_id}: Created playlist with {len(local_files)} files x {loop_count} loops = {total_entries} entries (~{estimated_duration_hours:.1f} hours)")
                
                # Clean up old playlists
                self._cleanup_old_playlists(stream_id, playlist_path)
                
                return f"concat:{playlist_path}"
                
        except Exception as e:
            logging.error(f"❌ Error creating playlist for stream {stream_id}: {e}")
            raise
    
    def cleanup_stream_files(self, stream_id: int, force: bool = False, keep_files: bool = False):
        """Clean up files for a specific stream"""
        try:
            download_dir = self.config.get_stream_download_dir(stream_id)
            
            if force:
                # Force cleanup (when deleting stream)
                if os.path.exists(download_dir):
                    shutil.rmtree(download_dir)
                    logging.info(f"🧹 Force cleaned up downloads directory for stream {stream_id}")
                return
            
            if keep_files:
                logging.info(f"📁 Keeping files for stream {stream_id} as requested")
                return
            
            # Default behavior: cleanup files
            if os.path.exists(download_dir):
                shutil.rmtree(download_dir)
                logging.info(f"🧹 Cleaned up downloads directory for stream {stream_id}")
                
        except Exception as e:
            logging.error(f"❌ Error cleaning up files for stream {stream_id}: {e}")
    
    def validate_video_file(self, file_path: str) -> bool:
        """Quick validation of video file"""
        try:
            if not os.path.exists(file_path):
                return False
            
            # Check file size
            file_size = os.path.getsize(file_path)
            if file_size < 1024:  # Less than 1KB
                return False
            
            # Basic file header check for common video formats
            with open(file_path, 'rb') as f:
                header = f.read(12)
                
                # MP4 signature
                if b'ftyp' in header:
                    return True
                
                # AVI signature
                if header.startswith(b'RIFF') and b'AVI ' in header:
                    return True
                
                # MKV signature
                if header.startswith(b'\x1a\x45\xdf\xa3'):
                    return True
            
            # If no known signature, assume it's valid
            # (FFmpeg can handle many formats)
            return True
            
        except Exception as e:
            logging.error(f"Error validating video file {file_path}: {e}")
            return False
    
    def _download_file(self, stream_id: int, video_file: VideoFile) -> DownloadResult:
        """Download a single file with retry logic"""
        start_time = time.time()
        
        def _download_attempt():
            return self._download_file_attempt(stream_id, video_file)
        
        try:
            result = retry_operation(
                _download_attempt,
                max_retries=self.config.max_download_retries,
                delay=1.0,
                backoff=2.0
            )
            result.duration = time.time() - start_time
            return result
            
        except Exception as e:
            return DownloadResult(
                success=False,
                error=str(e),
                duration=time.time() - start_time
            )
    
    def _download_file_attempt(self, stream_id: int, video_file: VideoFile) -> DownloadResult:
        """Single download attempt"""
        try:
            local_path = video_file.local_path
            if not local_path:
                raise Exception("Local path not set")
            
            # Create directory if needed
            os.makedirs(os.path.dirname(local_path), exist_ok=True)
            
            # Download with progress reporting
            response = requests.get(
                video_file.download_url,
                stream=True,
                timeout=30,
                headers={'User-Agent': 'EZStream-Agent/3.0'}
            )
            response.raise_for_status()
            
            total_size = int(response.headers.get('content-length', 0))
            downloaded = 0
            
            with open(local_path, 'wb') as f:
                for chunk in response.iter_content(chunk_size=self.config.download_chunk_size):
                    if chunk:
                        f.write(chunk)
                        downloaded += len(chunk)
                        
                        # Report progress occasionally
                        if downloaded % (1024 * 1024) == 0:  # Every MB
                            if self.status_reporter:
                                progress = (downloaded / total_size * 100) if total_size > 0 else 0
                                self.status_reporter.publish_stream_status(
                                    stream_id, 'PROGRESS',
                                    f'Downloading {video_file.filename}: {progress:.1f}%'
                                )
            
            # Verify download
            if total_size > 0 and downloaded != total_size:
                raise Exception(f"Download incomplete: {downloaded}/{total_size} bytes")
            
            # Calculate hash for verification
            file_hash = calculate_file_hash(local_path)
            
            return DownloadResult(
                success=True,
                file_path=local_path,
                bytes_downloaded=downloaded
            )
            
        except Exception as e:
            # Clean up partial download
            if local_path and os.path.exists(local_path):
                try:
                    os.remove(local_path)
                except:
                    pass
            
            return DownloadResult(
                success=False,
                error=str(e)
            )
    
    def _is_file_complete(self, file_path: str, expected_size: int) -> bool:
        """Check if file exists and has expected size"""
        try:
            if not os.path.exists(file_path):
                return False
            
            actual_size = os.path.getsize(file_path)
            
            # Allow 1% size difference for compression variations
            size_diff = abs(actual_size - expected_size) / expected_size if expected_size > 0 else 0
            
            return size_diff < 0.01  # Less than 1% difference
            
        except Exception:
            return False
    
    def _cleanup_old_playlists(self, stream_id: int, current_playlist: str):
        """Clean up old playlist files to save disk space"""
        try:
            download_dir = self.config.get_stream_download_dir(stream_id)
            current_filename = os.path.basename(current_playlist)
            
            for filename in os.listdir(download_dir):
                if filename.startswith('playlist_') and filename.endswith('.txt'):
                    if filename != current_filename:
                        old_playlist = os.path.join(download_dir, filename)
                        try:
                            os.remove(old_playlist)
                            logging.debug(f"🧹 Removed old playlist: {filename}")
                        except Exception as e:
                            logging.warning(f"Failed to remove old playlist {filename}: {e}")
                            
        except Exception as e:
            logging.warning(f"Error cleaning up old playlists for stream {stream_id}: {e}")
    
    def _periodic_cleanup_loop(self):
        """Periodic cleanup of old stream directories"""
        logging.info("🧹 Periodic cleanup thread started")
        
        while self.cleanup_running:
            try:
                time.sleep(self.config.cleanup_interval_seconds)
                
                if not self.cleanup_running:
                    break
                
                self._cleanup_old_directories()
                
            except Exception as e:
                logging.error(f"❌ Error in periodic cleanup: {e}")
    
    def _cleanup_old_directories(self):
        """Clean up old stream directories"""
        try:
            downloads_dir = self.config.download_base_dir
            if not os.path.exists(downloads_dir):
                return
            
            current_time = time.time()
            cleanup_threshold = self.config.cleanup_threshold_hours * 3600
            cleaned_count = 0
            
            # Get active streams to avoid deleting
            from stream_manager import get_stream_manager
            stream_manager = get_stream_manager()
            active_stream_ids = set(stream_manager.get_active_streams()) if stream_manager else set()
            
            for item in os.listdir(downloads_dir):
                item_path = os.path.join(downloads_dir, item)
                
                if not os.path.isdir(item_path):
                    continue
                
                try:
                    stream_id = int(item)
                    
                    # Skip active streams
                    if stream_id in active_stream_ids:
                        continue
                    
                    # Check modification time
                    mod_time = os.path.getmtime(item_path)
                    if (current_time - mod_time) > cleanup_threshold:
                        shutil.rmtree(item_path)
                        cleaned_count += 1
                        logging.info(f"🧹 Cleaned up old directory for stream {stream_id}")
                        
                except (ValueError, OSError) as e:
                    logging.warning(f"Error processing directory {item}: {e}")
            
            if cleaned_count > 0:
                logging.info(f"✅ Periodic cleanup completed. Removed {cleaned_count} old directories")
                
        except Exception as e:
            logging.error(f"❌ Error in periodic directory cleanup: {e}")


# Global file manager instance
_file_manager: Optional[FileManager] = None


def init_file_manager() -> FileManager:
    """Initialize global file manager"""
    global _file_manager
    _file_manager = FileManager()
    return _file_manager


def get_file_manager() -> Optional[FileManager]:
    """Get global file manager instance"""
    return _file_manager
