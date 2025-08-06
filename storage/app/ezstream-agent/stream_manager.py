#!/usr/bin/env python3
"""
SRS-based Stream Manager
Manages streams using SRS server for streaming
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
from process_manager import get_process_manager


class SRSStreamState(Enum):
    """SRS Stream states"""
    STARTING = "STARTING"
    STREAMING = "STREAMING"
    STOPPING = "STOPPING"
    STOPPED = "STOPPED"
    ERROR = "ERROR"


@dataclass
class SRSStreamInfo:
    """SRS Stream information"""
    stream_id: int
    rtmp_endpoint: str
    video_files: List[str] = field(default_factory=list)
    state: SRSStreamState = SRSStreamState.STARTING
    start_time: float = field(default_factory=time.time)
    error_message: Optional[str] = None
    srs_stream_id: Optional[str] = None
    loop_playlist: bool = True


class StreamManager:
    """SRS-based Stream Manager"""

    def __init__(self):
        self.config = get_config()
        self.status_reporter = get_status_reporter()
        self.process_manager = get_process_manager()
        
        # Stream tracking
        self.streams: Dict[int, SRSStreamInfo] = {}
        self.stream_lock = threading.RLock()
        
        logging.info("🎬 SRS Stream Manager initialized")

    def start_stream(self, stream_id: int, video_files: List[str], stream_config: Dict[str, Any],
                    rtmp_endpoint: Optional[str] = None) -> bool:
        """Start SRS stream"""
        try:
            logging.info(f"🎬 [STREAM_MANAGER] START_STREAM called for stream {stream_id}")
            logging.info(f"📋 [STREAM_MANAGER] Video files received: {video_files}")
            logging.info(f"📋 [STREAM_MANAGER] Stream config received: {stream_config}")

            with self.stream_lock:
                # Check if already running
                if stream_id in self.streams:
                    logging.warning(f"⚠️ [STREAM_MANAGER] Stream {stream_id} already running")
                    return False

                # Process video files for SRS (no download needed for HTTP URLs)
                if not video_files:
                    logging.error(f"❌ [STREAM_MANAGER] Stream {stream_id}: No video files provided")
                    return False

                # Build RTMP endpoint
                if rtmp_endpoint is None:
                    rtmp_endpoint = self._build_youtube_rtmp_endpoint(stream_config)
                    logging.info(f"🔗 [STREAM_MANAGER] Built RTMP endpoint: {rtmp_endpoint}")
                else:
                    logging.info(f"🔗 [STREAM_MANAGER] Using provided RTMP endpoint: {rtmp_endpoint}")

                # Log video file details
                for i, video_file in enumerate(video_files):
                    logging.info(f"📹 [STREAM_MANAGER] Video {i+1}: {video_file}")

                # Create stream info
                stream_info = SRSStreamInfo(
                    stream_id=stream_id,
                    rtmp_endpoint=rtmp_endpoint,
                    video_files=video_files,
                    loop_playlist=stream_config.get('loop', True)
                )

                self.streams[stream_id] = stream_info

                logging.info(f"🎬 [STREAM_MANAGER] Starting SRS stream {stream_id} with {len(video_files)} videos → {rtmp_endpoint}")

                # Report starting status
                if self.status_reporter:
                    self.status_reporter.publish_stream_status(
                        stream_id, 'STARTING', f'Starting SRS stream with {len(video_files)} videos'
                    )
                    logging.info(f"📤 [STREAM_MANAGER] Status STARTING sent to Laravel for stream {stream_id}")
                
                # Start SRS ingest
                logging.info(f"🔧 [STREAM_MANAGER] Getting SRS manager for stream {stream_id}")
                srs_manager = get_srs_manager()
                if not srs_manager:
                    logging.error(f"❌ [STREAM_MANAGER] SRS manager not available for stream {stream_id}")
                    stream_info.state = SRSStreamState.ERROR
                    stream_info.error_message = "SRS manager not available"
                    return False

                # Create SRS ingest
                srs_stream_id = f"stream_{stream_id}"
                input_url = video_files[0]  # Use first video file

                logging.info(f"🎯 [STREAM_MANAGER] Creating SRS ingest:")
                logging.info(f"   - SRS Stream ID: {srs_stream_id}")
                logging.info(f"   - Input URL: {input_url}")
                logging.info(f"   - Output URL: {rtmp_endpoint}")

                ingest_id = srs_manager.create_ingest(
                    stream_id=stream_id,  # Use actual stream_id, not srs_stream_id
                    input_url=input_url,
                    output_url=rtmp_endpoint
                )

                if ingest_id:
                    logging.info(f"✅ [STREAM_MANAGER] SRS ingest created with ID: {ingest_id}")

                    # Now start the ingest
                    logging.info(f"🚀 [STREAM_MANAGER] Starting SRS ingest {ingest_id}")
                    start_success = srs_manager.start_ingest(ingest_id)

                    if start_success:
                        stream_info.srs_stream_id = ingest_id
                        stream_info.state = SRSStreamState.STREAMING

                        # Register with process manager
                        logging.info(f"📝 [STREAM_MANAGER] Registering stream {stream_id} with process manager")
                        self.process_manager.start_process(stream_id, {})

                        logging.info(f"✅ [STREAM_MANAGER] SRS stream {stream_id} started successfully")

                        if self.status_reporter:
                            self.status_reporter.publish_stream_status(
                                stream_id, 'STREAMING', 'SRS stream started successfully'
                            )
                            logging.info(f"📤 [STREAM_MANAGER] Status STREAMING sent to Laravel for stream {stream_id}")

                        return True
                    else:
                        logging.error(f"❌ [STREAM_MANAGER] Failed to start SRS ingest {ingest_id}")
                        stream_info.state = SRSStreamState.ERROR
                        stream_info.error_message = "Failed to start SRS ingest"

                        if self.status_reporter:
                            self.status_reporter.publish_stream_status(
                                stream_id, 'ERROR', 'Failed to start SRS ingest'
                            )

                        return False
                else:
                    logging.error(f"❌ [STREAM_MANAGER] Failed to create SRS ingest for stream {stream_id}")
                    stream_info.state = SRSStreamState.ERROR
                    stream_info.error_message = "Failed to create SRS ingest"

                    if self.status_reporter:
                        self.status_reporter.publish_stream_status(
                            stream_id, 'ERROR', 'Failed to create SRS ingest'
                        )

                    return False

        except Exception as e:
            logging.error(f"❌ Error starting SRS stream {stream_id}: {e}")
            if stream_id in self.streams:
                self.streams[stream_id].state = SRSStreamState.ERROR
                self.streams[stream_id].error_message = str(e)
            
            if self.status_reporter:
                self.status_reporter.publish_stream_status(
                    stream_id, 'ERROR', f'SRS stream error: {str(e)}'
                )
            
            return False

    def stop_stream(self, stream_id: int) -> bool:
        """Stop SRS stream"""
        try:
            with self.stream_lock:
                if stream_id not in self.streams:
                    logging.warning(f"Stream {stream_id} not found")
                    return True

                stream_info = self.streams[stream_id]
                stream_info.state = SRSStreamState.STOPPING
                
                logging.info(f"🛑 Stopping SRS stream {stream_id}")

                # Stop SRS ingest
                if stream_info.srs_stream_id:
                    srs_manager = get_srs_manager()
                    if srs_manager:
                        srs_manager.delete_ingest(stream_info.srs_stream_id)

                # Unregister from process manager
                self.process_manager.stop_process(stream_id, "manual")

                # Remove from tracking
                del self.streams[stream_id]
                
                logging.info(f"✅ SRS stream {stream_id} stopped")
                
                if self.status_reporter:
                    self.status_reporter.publish_stream_status(
                        stream_id, 'STOPPED', 'SRS stream stopped'
                    )
                
                return True

        except Exception as e:
            logging.error(f"❌ Error stopping SRS stream {stream_id}: {e}")
            return False

    def get_stream_status(self, stream_id: int) -> Optional[Dict[str, Any]]:
        """Get stream status"""
        with self.stream_lock:
            if stream_id not in self.streams:
                return None

            stream_info = self.streams[stream_id]

            return {
                'stream_id': stream_id,
                'state': stream_info.state.value,
                'uptime': time.time() - stream_info.start_time,
                'error_message': stream_info.error_message,
                'video_files': stream_info.video_files,
                'rtmp_endpoint': stream_info.rtmp_endpoint,
                'srs_stream_id': stream_info.srs_stream_id
            }

    def get_active_streams(self) -> List[int]:
        """Get list of active stream IDs"""
        with self.stream_lock:
            return list(self.streams.keys())

    def stop_all(self) -> None:
        """Stop all active streams"""
        try:
            with self.stream_lock:
                active_streams = list(self.streams.keys())

            if not active_streams:
                logging.info("No active streams to stop")
                return

            logging.info(f"🛑 Stopping {len(active_streams)} active streams...")

            for stream_id in active_streams:
                try:
                    self.stop_stream(stream_id)
                    logging.info(f"✅ Stopped stream {stream_id}")
                except Exception as e:
                    logging.error(f"❌ Error stopping stream {stream_id}: {e}")

            logging.info("🛑 All streams stopped")

        except Exception as e:
            logging.error(f"❌ Error in stop_all: {e}")

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


# Aliases for backward compatibility with SRS naming
def init_srs_stream_manager() -> 'StreamManager':
    """Initialize global stream manager (SRS-based) - alias for compatibility"""
    return init_stream_manager()


def get_srs_stream_manager() -> 'StreamManager':
    """Get global stream manager instance (SRS-based) - alias for compatibility"""
    return get_stream_manager()
