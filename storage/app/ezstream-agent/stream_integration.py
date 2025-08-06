#!/usr/bin/env python3
"""
Stream Integration Layer
Integrates robust stream manager with existing EZStream Agent
"""

import logging
from typing import Dict, List, Optional, Any

from robust_stream_manager import (
    RobustStreamManager, StreamConfig, StreamType, 
    init_robust_stream_manager, get_robust_stream_manager
)
from srs_config_manager import init_srs_config_manager, get_srs_config_manager
from status_reporter import get_status_reporter

class StreamIntegration:
    """
    Integration layer between EZStream Agent and Robust Stream Manager
    
    Provides backward compatibility with existing agent while adding
    robust streaming capabilities
    """
    
    def __init__(self):
        self.robust_manager: Optional[RobustStreamManager] = None
        self.srs_config_manager = None
        self.status_reporter = None
        
        self._initialize()

    def _initialize(self):
        """Initialize all components"""
        try:
            # Get status reporter
            self.status_reporter = get_status_reporter()
            
            # Initialize SRS config manager
            self.srs_config_manager = init_srs_config_manager()
            
            # Initialize robust stream manager
            self.robust_manager = init_robust_stream_manager(self.status_reporter)
            
            logging.info("✅ [STREAM_INTEGRATION] All components initialized")
            
        except Exception as e:
            logging.error(f"❌ [STREAM_INTEGRATION] Initialization failed: {e}")

    def start_stream(self, stream_id: int, video_files: List[str], 
                    stream_config: Dict[str, Any], rtmp_endpoint: Optional[str] = None) -> bool:
        """
        Start stream with robust manager (backward compatible interface)
        
        Args:
            stream_id: Stream ID
            video_files: List of video URLs (HLS, etc)
            stream_config: Stream configuration
            rtmp_endpoint: RTMP destination URL
            
        Returns:
            True if stream started successfully
        """
        try:
            if not self.robust_manager:
                logging.error("❌ [STREAM_INTEGRATION] Robust manager not initialized")
                return False
            
            if not video_files:
                logging.error(f"❌ [STREAM_INTEGRATION] No video files provided for stream {stream_id}")
                return False
            
            # Build RTMP endpoint if not provided
            if rtmp_endpoint is None:
                rtmp_endpoint = self._build_youtube_rtmp_endpoint(stream_config)
            
            # Determine stream type
            input_url = video_files[0]  # Use first video file
            stream_type = self._detect_stream_type(input_url)
            
            # Create robust stream config
            robust_config = StreamConfig(
                stream_id=stream_id,
                input_url=input_url,
                output_url=rtmp_endpoint,
                stream_type=stream_type,
                use_srs=True,  # Use SRS for stability
                max_retries=5,
                retry_delay=10,
                health_check_interval=30,
                ffmpeg_options=self._build_ffmpeg_options(stream_config)
            )
            
            logging.info(f"🎬 [STREAM_INTEGRATION] Starting robust stream {stream_id}")
            logging.info(f"   - Input: {input_url}")
            logging.info(f"   - Output: {rtmp_endpoint}")
            logging.info(f"   - Type: {stream_type.value}")
            
            # Start stream with robust manager
            success = self.robust_manager.start_stream(robust_config)
            
            if success:
                logging.info(f"✅ [STREAM_INTEGRATION] Stream {stream_id} started successfully")
                return True
            else:
                logging.error(f"❌ [STREAM_INTEGRATION] Failed to start stream {stream_id}")
                return False
                
        except Exception as e:
            logging.error(f"❌ [STREAM_INTEGRATION] Error starting stream {stream_id}: {e}")
            return False

    def stop_stream(self, stream_id: int) -> bool:
        """Stop stream (backward compatible interface)"""
        try:
            if not self.robust_manager:
                logging.error("❌ [STREAM_INTEGRATION] Robust manager not initialized")
                return False
            
            logging.info(f"🛑 [STREAM_INTEGRATION] Stopping stream {stream_id}")
            
            success = self.robust_manager.stop_stream(stream_id)
            
            if success:
                logging.info(f"✅ [STREAM_INTEGRATION] Stream {stream_id} stopped successfully")
            else:
                logging.error(f"❌ [STREAM_INTEGRATION] Failed to stop stream {stream_id}")
            
            return success
            
        except Exception as e:
            logging.error(f"❌ [STREAM_INTEGRATION] Error stopping stream {stream_id}: {e}")
            return False

    def get_stream_status(self, stream_id: int) -> Optional[Dict[str, Any]]:
        """Get stream status (backward compatible interface)"""
        try:
            if not self.robust_manager:
                return None
            
            return self.robust_manager.get_stream_status(stream_id)
            
        except Exception as e:
            logging.error(f"❌ [STREAM_INTEGRATION] Error getting stream status {stream_id}: {e}")
            return None

    def get_all_streams_status(self) -> List[Dict[str, Any]]:
        """Get all streams status"""
        try:
            if not self.robust_manager:
                return []
            
            return self.robust_manager.get_all_streams_status()
            
        except Exception as e:
            logging.error(f"❌ [STREAM_INTEGRATION] Error getting all streams status: {e}")
            return []

    def is_stream_running(self, stream_id: int) -> bool:
        """Check if stream is running (backward compatible interface)"""
        try:
            status = self.get_stream_status(stream_id)
            return status is not None and status.get('state') == 'running'
            
        except Exception as e:
            logging.error(f"❌ [STREAM_INTEGRATION] Error checking stream status {stream_id}: {e}")
            return False

    def shutdown(self):
        """Shutdown all components"""
        try:
            logging.info("🛑 [STREAM_INTEGRATION] Shutting down...")
            
            if self.robust_manager:
                self.robust_manager.shutdown()
            
            logging.info("✅ [STREAM_INTEGRATION] Shutdown complete")
            
        except Exception as e:
            logging.error(f"❌ [STREAM_INTEGRATION] Error during shutdown: {e}")

    def _build_youtube_rtmp_endpoint(self, stream_config: Dict[str, Any]) -> str:
        """Build YouTube RTMP endpoint from config"""
        try:
            # Extract YouTube stream key from config
            youtube_key = stream_config.get('youtube_key') or stream_config.get('stream_key')
            
            if youtube_key:
                return f"rtmp://a.rtmp.youtube.com/live2/{youtube_key}"
            else:
                # Fallback: try to extract from existing config
                rtmp_url = stream_config.get('rtmp_url') or stream_config.get('output_url')
                if rtmp_url:
                    return rtmp_url
                
                logging.error("❌ [STREAM_INTEGRATION] No YouTube key or RTMP URL found in config")
                return "rtmp://a.rtmp.youtube.com/live2/INVALID_KEY"
                
        except Exception as e:
            logging.error(f"❌ [STREAM_INTEGRATION] Error building RTMP endpoint: {e}")
            return "rtmp://a.rtmp.youtube.com/live2/ERROR"

    def _detect_stream_type(self, input_url: str) -> StreamType:
        """Detect stream type from input URL"""
        try:
            input_url_lower = input_url.lower()
            
            if '.m3u8' in input_url_lower or 'hls' in input_url_lower:
                return StreamType.HLS_TO_RTMP
            elif input_url_lower.startswith('rtmp://'):
                return StreamType.RTMP_TO_RTMP
            else:
                # Default to HLS for HTTP URLs
                return StreamType.HLS_TO_RTMP
                
        except Exception as e:
            logging.error(f"❌ [STREAM_INTEGRATION] Error detecting stream type: {e}")
            return StreamType.HLS_TO_RTMP

    def _build_ffmpeg_options(self, stream_config: Dict[str, Any]) -> Dict[str, Any]:
        """Build FFmpeg options from stream config"""
        try:
            options = {}
            
            # Add custom FFmpeg options if specified
            if 'ffmpeg_options' in stream_config:
                options.update(stream_config['ffmpeg_options'])
            
            # Add quality settings if specified
            if 'quality' in stream_config:
                quality = stream_config['quality']
                if quality == 'high':
                    options['-b:v'] = '4000k'
                    options['-b:a'] = '128k'
                elif quality == 'medium':
                    options['-b:v'] = '2000k'
                    options['-b:a'] = '96k'
                elif quality == 'low':
                    options['-b:v'] = '1000k'
                    options['-b:a'] = '64k'
            
            # Add loop option for playlists
            if stream_config.get('loop', False):
                options['-stream_loop'] = '-1'
            
            return options
            
        except Exception as e:
            logging.error(f"❌ [STREAM_INTEGRATION] Error building FFmpeg options: {e}")
            return {}


# Global instance
_stream_integration: Optional[StreamIntegration] = None

def init_stream_integration() -> StreamIntegration:
    """Initialize global stream integration"""
    global _stream_integration
    _stream_integration = StreamIntegration()
    return _stream_integration

def get_stream_integration() -> Optional[StreamIntegration]:
    """Get global stream integration instance"""
    return _stream_integration

# Backward compatibility functions
def start_stream_robust(stream_id: int, video_files: List[str], 
                       stream_config: Dict[str, Any], rtmp_endpoint: Optional[str] = None) -> bool:
    """Backward compatible start_stream function"""
    integration = get_stream_integration()
    if integration:
        return integration.start_stream(stream_id, video_files, stream_config, rtmp_endpoint)
    return False

def stop_stream_robust(stream_id: int) -> bool:
    """Backward compatible stop_stream function"""
    integration = get_stream_integration()
    if integration:
        return integration.stop_stream(stream_id)
    return False

def is_stream_running_robust(stream_id: int) -> bool:
    """Backward compatible is_stream_running function"""
    integration = get_stream_integration()
    if integration:
        return integration.is_stream_running(stream_id)
    return False
