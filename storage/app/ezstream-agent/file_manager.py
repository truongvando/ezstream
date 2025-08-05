#!/usr/bin/env python3
"""
EZStream Agent File Manager
Simple file management for SRS-based streaming
"""

import os
import time
import shutil
import logging
import threading
from typing import List, Dict, Any, Optional
from dataclasses import dataclass
from concurrent.futures import ThreadPoolExecutor

from config import get_config
from status_reporter import get_status_reporter
from utils import PerformanceTimer, ensure_directory


@dataclass
class VideoFile:
    """Video file information"""
    file_id: int
    filename: str
    download_url: str
    size: int
    disk: str
    local_path: Optional[str] = None


class FileManager:
    """Simple file manager for SRS streaming"""
    
    def __init__(self):
        self.config = get_config()
        self.status_reporter = get_status_reporter()
        
        # Cleanup management
        self.cleanup_running = False
        self.cleanup_thread = None
        
        logging.info("📁 SRS File Manager initialized")

    def start_cleanup_service(self):
        """Start periodic cleanup service"""
        if self.cleanup_running:
            return
        
        self.cleanup_running = True
        self.cleanup_thread = threading.Thread(
            target=self._periodic_cleanup_loop,
            name="FileCleanup",
            daemon=True
        )
        self.cleanup_thread.start()
        logging.info("🧹 File cleanup service started")

    def stop_cleanup_service(self):
        """Stop periodic cleanup service"""
        self.cleanup_running = False
        if self.cleanup_thread:
            self.cleanup_thread.join(timeout=5)
        logging.info("🧹 File cleanup service stopped")

    def validate_urls_for_srs(self, stream_id: int, video_files: List[Dict[str, Any]]) -> List[str]:
        """Validate URLs for SRS streaming (no download needed)"""
        try:
            logging.info(f"🔍 [SRS] Validating {len(video_files)} URLs for stream {stream_id}")
            
            validated_urls = []
            for vf in video_files:
                url = vf.get('download_url', '')
                if not url:
                    logging.error(f"❌ Empty URL for file: {vf.get('filename', 'unknown')}")
                    continue
                
                # Basic URL validation
                if not (url.startswith('http://') or url.startswith('https://')):
                    logging.error(f"❌ Invalid URL format: {url}")
                    continue
                
                # For SRS, we just pass the URL directly
                validated_urls.append(url)
                logging.info(f"✅ Validated URL: {vf.get('filename', 'unknown')}")
            
            if not validated_urls:
                raise Exception("No valid URLs found for SRS streaming")
            
            logging.info(f"✅ [SRS] Validated {len(validated_urls)} URLs for streaming")
            return validated_urls
            
        except Exception as e:
            logging.error(f"❌ Error validating URLs for SRS: {e}")
            return []

    def validate_local_files(self, stream_id: int, file_paths: List[str]) -> List[str]:
        """Validate local files exist"""
        try:
            validated_files = []
            
            for file_path in file_paths:
                if os.path.exists(file_path) and os.path.isfile(file_path):
                    validated_files.append(file_path)
                    logging.info(f"✅ Validated local file: {file_path}")
                else:
                    logging.error(f"❌ Local file not found: {file_path}")
            
            return validated_files
            
        except Exception as e:
            logging.error(f"❌ Error validating local files: {e}")
            return []

    def cleanup_stream_files(self, stream_id: int, force: bool = False):
        """Clean up files for a specific stream"""
        try:
            download_dir = self.config.get_stream_download_dir(stream_id)
            
            if not os.path.exists(download_dir):
                logging.debug(f"Stream {stream_id}: Download directory doesn't exist")
                return
            
            if force:
                # Force cleanup - remove entire directory
                shutil.rmtree(download_dir)
                logging.info(f"🧹 Stream {stream_id}: Force cleaned up directory")
            else:
                # Normal cleanup - remove old files
                cutoff_time = time.time() - (self.config.cleanup_after_hours * 3600)
                
                for filename in os.listdir(download_dir):
                    file_path = os.path.join(download_dir, filename)
                    try:
                        if os.path.getmtime(file_path) < cutoff_time:
                            os.remove(file_path)
                            logging.debug(f"🧹 Removed old file: {filename}")
                    except Exception as e:
                        logging.warning(f"Failed to remove file {filename}: {e}")
                
                # Remove directory if empty
                try:
                    os.rmdir(download_dir)
                    logging.info(f"🧹 Stream {stream_id}: Cleaned up empty directory")
                except OSError:
                    pass  # Directory not empty
                    
        except Exception as e:
            logging.error(f"❌ Error cleaning up stream {stream_id} files: {e}")

    def get_stream_directory_size(self, stream_id: int) -> int:
        """Get total size of stream directory in bytes"""
        try:
            download_dir = self.config.get_stream_download_dir(stream_id)
            
            if not os.path.exists(download_dir):
                return 0
            
            total_size = 0
            for dirpath, dirnames, filenames in os.walk(download_dir):
                for filename in filenames:
                    file_path = os.path.join(dirpath, filename)
                    try:
                        total_size += os.path.getsize(file_path)
                    except (OSError, IOError):
                        pass
            
            return total_size
            
        except Exception as e:
            logging.error(f"❌ Error calculating directory size for stream {stream_id}: {e}")
            return 0

    def _periodic_cleanup_loop(self):
        """Periodic cleanup of old stream directories"""
        logging.info("🧹 Periodic cleanup thread started")
        
        while self.cleanup_running:
            try:
                # Sleep for 1 hour between cleanups
                for _ in range(3600):
                    if not self.cleanup_running:
                        break
                    time.sleep(1)
                
                if not self.cleanup_running:
                    break
                
                # Perform cleanup
                self._cleanup_old_directories()
                
            except Exception as e:
                logging.error(f"❌ Error in periodic cleanup: {e}")
                time.sleep(60)  # Wait 1 minute before retrying
        
        logging.info("🧹 Periodic cleanup thread stopped")

    def _cleanup_old_directories(self):
        """Clean up old stream directories"""
        try:
            if not os.path.exists(self.config.base_download_dir):
                return
            
            cutoff_time = time.time() - (self.config.cleanup_after_hours * 3600)
            cleaned_count = 0
            
            for dirname in os.listdir(self.config.base_download_dir):
                if dirname.startswith('stream_'):
                    dir_path = os.path.join(self.config.base_download_dir, dirname)
                    
                    try:
                        # Check if directory is old
                        if os.path.getmtime(dir_path) < cutoff_time:
                            shutil.rmtree(dir_path)
                            cleaned_count += 1
                            logging.info(f"🧹 Cleaned up old directory: {dirname}")
                    except Exception as e:
                        logging.warning(f"Failed to cleanup directory {dirname}: {e}")
            
            if cleaned_count > 0:
                logging.info(f"🧹 Periodic cleanup completed: {cleaned_count} directories removed")
            
        except Exception as e:
            logging.error(f"❌ Error in periodic directory cleanup: {e}")


# Global instance management
_file_manager: Optional['FileManager'] = None


def init_file_manager() -> 'FileManager':
    """Initialize global file manager"""
    global _file_manager
    _file_manager = FileManager()
    return _file_manager


def get_file_manager() -> 'FileManager':
    """Get global file manager instance"""
    if _file_manager is None:
        raise RuntimeError("File manager not initialized. Call init_file_manager() first.")
    return _file_manager
