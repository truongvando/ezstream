#!/usr/bin/env python3
"""
SRS-based Stream Manager
Alternative to stream_manager.py using SRS server for streaming
"""

import os
import time
import logging
import threading
from typing import Dict, Optional, List, Any
from dataclasses import dataclass, field
from enum import Enum

from config import get_config
from srs_manager import get_srs_manager, SRSIngestState
from status_reporter import get_status_reporter
from utils import PerformanceTimer


class SRSStreamState(Enum):
    """SRS Stream states"""
    STARTING = "STARTING"
    STREAMING = "STREAMING"
    RECONNECTING = "RECONNECTING"
    STOPPING = "STOPPING"
    STOPPED = "STOPPED"
    ERROR = "ERROR"
    DEAD = "DEAD"


@dataclass
class SRSStreamInfo:
    """SRS Stream information"""
    stream_id: int
    video_urls: List[str]
    rtmp_endpoint: str
    ingest_id: Optional[str] = None
    state: SRSStreamState = SRSStreamState.STOPPED
    created_at: float = field(default_factory=time.time)
    started_at: Optional[float] = None
    error_count: int = 0
    last_error: Optional[str] = None
    loop_enabled: bool = True


class SRSStreamManager:
    """SRS-based Stream Manager"""
    
    def __init__(self):
        self.config = get_config()
        self.srs_manager = get_srs_manager()
        self.status_reporter = get_status_reporter()
        
        # Stream tracking
        self.streams: Dict[int, SRSStreamInfo] = {}
        self.stream_lock = threading.RLock()
        
        # Monitoring
        self.monitor_thread = None
        self.monitor_running = False
        
        logging.info("🎬 SRS Stream Manager initialized")
    
    def start_monitoring(self):
        """Start stream monitoring thread"""
        if self.monitor_thread and self.monitor_thread.is_alive():
            return
        
        self.monitor_running = True
        self.monitor_thread = threading.Thread(target=self._monitor_streams, daemon=True)
        self.monitor_thread.start()
        logging.info("📊 SRS stream monitoring started")
    
    def stop_monitoring(self):
        """Stop stream monitoring"""
        self.monitor_running = False
        if self.monitor_thread:
            self.monitor_thread.join(timeout=5)
        logging.info("📊 SRS stream monitoring stopped")
    
    def start_stream(self, stream_id: int, video_files: List[str], stream_config: Dict[str, Any],
                    rtmp_endpoint: Optional[str] = None) -> bool:
        """Start SRS-based stream"""
        try:
            with self.stream_lock:
                # Check if already running
                if stream_id in self.streams:
                    logging.warning(f"SRS Stream {stream_id} already running")
                    self._report_status(stream_id, 'FAILED', 'Stream already running')
                    return False

                # Report stream starting
                self._report_status(stream_id, 'STARTING', 'Initializing SRS stream')

                # Validate SRS manager
                if not self.srs_manager:
                    logging.error("❌ SRS Manager not initialized")
                    self._report_status(stream_id, 'FAILED', 'SRS Manager not initialized')
                    return False
                
                # Check SRS server status
                if not self.srs_manager.check_server_status():
                    logging.error("❌ SRS server not accessible")
                    return False
                
                # Process video files (convert to URLs)
                video_urls = self._process_video_files(video_files, stream_id)
                if not video_urls:
                    logging.error(f"❌ No valid video URLs for stream {stream_id}")
                    return False
                
                # Build RTMP endpoint
                if rtmp_endpoint is None:
                    rtmp_endpoint = self._build_youtube_rtmp_endpoint(stream_config)
                
                # Create stream info
                stream_info = SRSStreamInfo(
                    stream_id=stream_id,
                    video_urls=video_urls,
                    rtmp_endpoint=rtmp_endpoint,
                    state=SRSStreamState.STARTING,
                    loop_enabled=stream_config.get('loop', True)
                )
                
                self.streams[stream_id] = stream_info
                
                logging.info(f"🎬 Starting SRS stream {stream_id} with {len(video_urls)} videos → {rtmp_endpoint}")
                
                # Report starting status
                if self.status_reporter:
                    self.status_reporter.publish_stream_status(
                        stream_id, 'STARTING', f'Starting SRS stream with {len(video_urls)} videos'
                    )
                
                # Start SRS ingest
                success = self._start_srs_ingest(stream_info)
                
                if success:
                    stream_info.state = SRSStreamState.STREAMING
                    stream_info.started_at = time.time()
                    
                    # Report streaming status
                    if self.status_reporter:
                        self.status_reporter.publish_stream_status(
                            stream_id, 'STREAMING', 'SRS stream started successfully'
                        )
                    
                    logging.info(f"✅ SRS stream {stream_id} started successfully")
                    return True
                else:
                    stream_info.state = SRSStreamState.ERROR
                    stream_info.error_count += 1
                    
                    # Report error status
                    if self.status_reporter:
                        self.status_reporter.publish_stream_status(
                            stream_id, 'ERROR', 'Failed to start SRS stream'
                        )
                    
                    logging.error(f"❌ Failed to start SRS stream {stream_id}")
                    return False
                
        except Exception as e:
            logging.error(f"❌ Error starting SRS stream {stream_id}: {e}")
            if stream_id in self.streams:
                self.streams[stream_id].state = SRSStreamState.ERROR
                self.streams[stream_id].last_error = str(e)
            return False
    
    def stop_stream(self, stream_id: int, reason: str = "manual") -> bool:
        """Stop SRS stream"""
        try:
            with self.stream_lock:
                if stream_id not in self.streams:
                    logging.warning(f"SRS Stream {stream_id} not found")
                    return True
                
                stream_info = self.streams[stream_id]
                stream_info.state = SRSStreamState.STOPPING
                
                logging.info(f"🛑 Stopping SRS stream {stream_id} (reason: {reason})")
                
                # Stop SRS ingest
                success = True
                if stream_info.ingest_id:
                    success = self.srs_manager.stop_ingest(stream_info.ingest_id)
                    if success:
                        # Delete ingest configuration
                        self.srs_manager.delete_ingest(stream_info.ingest_id)
                
                # Update state
                stream_info.state = SRSStreamState.STOPPED
                
                # Report stopped status
                if self.status_reporter:
                    self.status_reporter.publish_stream_status(
                        stream_id, 'STOPPED', f'SRS stream stopped: {reason}'
                    )
                
                # Remove from tracking
                del self.streams[stream_id]
                
                logging.info(f"✅ SRS stream {stream_id} stopped")
                return success
                
        except Exception as e:
            logging.error(f"❌ Error stopping SRS stream {stream_id}: {e}")
            return False
    
    def get_stream_status(self, stream_id: int) -> Optional[Dict]:
        """Get SRS stream status"""
        try:
            with self.stream_lock:
                if stream_id not in self.streams:
                    return None
                
                stream_info = self.streams[stream_id]
                
                # Get SRS ingest status if available
                srs_status = None
                if stream_info.ingest_id:
                    srs_status = self.srs_manager.get_ingest_status(stream_info.ingest_id)
                
                return {
                    'stream_id': stream_id,
                    'state': stream_info.state.value,
                    'video_count': len(stream_info.video_urls),
                    'rtmp_endpoint': stream_info.rtmp_endpoint,
                    'ingest_id': stream_info.ingest_id,
                    'created_at': stream_info.created_at,
                    'started_at': stream_info.started_at,
                    'error_count': stream_info.error_count,
                    'last_error': stream_info.last_error,
                    'srs_status': srs_status
                }
                
        except Exception as e:
            logging.error(f"❌ Error getting stream status {stream_id}: {e}")
            return None
    
    def get_active_streams(self) -> List[int]:
        """Get list of active stream IDs"""
        with self.stream_lock:
            return [
                stream_id for stream_id, stream_info in self.streams.items()
                if stream_info.state in [SRSStreamState.STARTING, SRSStreamState.STREAMING, SRSStreamState.RECONNECTING]
            ]
    
    def _start_srs_ingest(self, stream_info: SRSStreamInfo) -> bool:
        """Start SRS ingest for stream"""
        try:
            # For now, use first video URL
            # TODO: Implement playlist support for multiple videos
            input_url = stream_info.video_urls[0]
            
            # Create SRS ingest
            ingest_id = self.srs_manager.create_ingest(
                stream_info.stream_id,
                input_url,
                stream_info.rtmp_endpoint
            )
            
            if not ingest_id:
                return False
            
            stream_info.ingest_id = ingest_id
            
            # Start the ingest
            return self.srs_manager.start_ingest(ingest_id)
            
        except Exception as e:
            logging.error(f"❌ Error starting SRS ingest: {e}")
            return False
    
    def _process_video_files(self, video_files: List[str], stream_id: int) -> List[str]:
        """Process video files and convert to URLs"""
        try:
            video_urls = []
            
            for video_file in video_files:
                if video_file.startswith(('http://', 'https://')):
                    # Already a URL
                    video_urls.append(video_file)
                elif os.path.isfile(video_file):
                    # Local file - convert to file:// URL
                    video_urls.append(f"file://{os.path.abspath(video_file)}")
                else:
                    logging.warning(f"⚠️ Invalid video file/URL: {video_file}")
            
            return video_urls
            
        except Exception as e:
            logging.error(f"❌ Error processing video files: {e}")
            return []
    
    def _build_youtube_rtmp_endpoint(self, stream_config: Dict[str, Any]) -> str:
        """Build YouTube RTMP endpoint"""
        try:
            rtmp_url = stream_config.get('rtmp_url', '')
            stream_key = stream_config.get('stream_key', '')
            
            if rtmp_url and stream_key:
                if rtmp_url.endswith('/'):
                    return f"{rtmp_url}{stream_key}"
                else:
                    return f"{rtmp_url}/{stream_key}"
            
            # Fallback to config default
            return self.config.get_youtube_rtmp_endpoint(stream_key)
            
        except Exception as e:
            logging.error(f"❌ Error building RTMP endpoint: {e}")
            return ""
    
    def _monitor_streams(self):
        """Monitor SRS streams"""
        while self.monitor_running:
            try:
                with self.stream_lock:
                    for stream_id, stream_info in list(self.streams.items()):
                        self._check_stream_health(stream_info)
                
                # Cleanup old ingests
                if self.srs_manager:
                    self.srs_manager.cleanup_stopped_ingests()
                
            except Exception as e:
                logging.error(f"❌ Error in stream monitoring: {e}")
            
            time.sleep(self.config.process_monitor_interval)
    
    def _check_stream_health(self, stream_info: SRSStreamInfo):
        """Check individual stream health"""
        try:
            if not stream_info.ingest_id:
                return
            
            # Get SRS ingest status
            srs_status = self.srs_manager.get_ingest_status(stream_info.ingest_id)
            
            if srs_status is None:
                # Ingest not found or error
                stream_info.state = SRSStreamState.ERROR
                stream_info.error_count += 1
                stream_info.last_error = "SRS ingest not found"
                
                # Report error
                if self.status_reporter:
                    self.status_reporter.publish_stream_status(
                        stream_info.stream_id, 'ERROR', 'SRS ingest not found'
                    )
            
        except Exception as e:
            logging.error(f"❌ Error checking stream health: {e}")

    def _report_status(self, stream_id: int, status: str, message: str, srs_data: Optional[Dict] = None):
        """Report SRS stream status to Laravel"""
        try:
            if self.status_reporter:
                self.status_reporter.publish_srs_stream_status(stream_id, status, message, srs_data)
        except Exception as e:
            logging.error(f"❌ Error reporting SRS stream status: {e}")

    def get_active_streams(self) -> List[int]:
        """Get list of active stream IDs for status reporting"""
        try:
            with self.stream_lock:
                return [stream_id for stream_id, stream_info in self.streams.items()
                       if stream_info.state in [SRSStreamState.STARTING, SRSStreamState.RUNNING]]
        except Exception as e:
            logging.error(f"❌ Error getting active streams: {e}")
            return []


# Global SRS stream manager instance
srs_stream_manager: Optional[SRSStreamManager] = None

def init_srs_stream_manager():
    """Initialize global SRS stream manager"""
    global srs_stream_manager
    srs_stream_manager = SRSStreamManager()
    return srs_stream_manager

def get_srs_stream_manager() -> Optional[SRSStreamManager]:
    """Get global SRS stream manager instance"""
    return srs_stream_manager
