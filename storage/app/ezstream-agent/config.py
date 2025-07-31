#!/usr/bin/env python3
"""
EZStream Agent Configuration Management
Centralized configuration for all agent components
"""

import os
import json
from dataclasses import dataclass
from typing import Optional
import logging


@dataclass
class AgentConfig:
    """Main configuration class for EZStream Agent"""

    # VPS and Redis settings
    vps_id: int
    redis_host: str = '127.0.0.1'
    redis_port: int = 6379
    redis_password: Optional[str] = None

    # Laravel API settings
    laravel_base_url: str = 'http://localhost'
    agent_token: Optional[str] = None
    
    # Process management
    max_concurrent_streams: int = 50
    graceful_shutdown_timeout: int = 15  # Increased from 10s to 15s
    system_cleanup_wait: int = 3
    force_kill_timeout: int = 10  # Increased from 5s to 10s
    
    # File management
    download_base_dir: str = '/tmp/ezstream_downloads'
    cleanup_interval_seconds: int = 3600  # 1 hour
    cleanup_threshold_hours: int = 24     # 24 hours
    max_download_retries: int = 3
    download_chunk_size: int = 8192
    
    # Reporting intervals
    stats_report_interval: int = 15       # 15 seconds
    heartbeat_interval: int = 5           # 5 seconds (faster detection)
    progress_throttle_interval: int = 2   # 2 seconds
    
    # FFmpeg settings
    ffmpeg_reconnect_attempts: int = 5   # Reconnect attempts
    ffmpeg_reconnect_delay: int = 2      # Delay between reconnects
    ffmpeg_startup_timeout: int = 15     # Startup timeout
    ffmpeg_use_encoding: bool = True     # Use encoding mode to fix SPS issues (slower but stable)

    # HLS Pipeline settings
    hls_segment_duration: int = 4        # HLS segment duration in seconds
    hls_playlist_size: int = 10          # Number of segments to keep in playlist
    hls_base_dir: str = '/tmp/ezstream-hls'  # Base directory for HLS files (temp storage)

    # HLS Encoding settings (Stage 1)
    hls_video_codec: str = 'libx264'     # Video codec for HLS generation
    hls_video_preset: str = 'ultrafast'  # Encoding preset (ultrafast/superfast/veryfast/faster/fast)
    hls_video_crf: int = 28              # CRF value (18-28, lower = better quality)
    hls_video_maxrate: str = '2000k'     # Max bitrate for HLS
    hls_video_bufsize: str = '4000k'     # Buffer size
    hls_audio_codec: str = 'aac'         # Audio codec
    hls_audio_bitrate: str = '128k'      # Audio bitrate

    # Fast restart settings for DTS errors
    enable_fast_restart: bool = True     # Enable fast restart on DTS errors
    dts_error_threshold: int = 3         # Number of DTS errors before fast restart
    max_fast_restarts: int = 5           # Max fast restarts before falling back to normal error handling
    fast_restart_delay: int = 2          # Delay between fast restarts (seconds)

    def update_from_laravel_settings(self, settings: dict):
        """Update config from Laravel settings"""
        updated_settings = []

        # FFmpeg encoding mode
        if 'ffmpeg_encoding_mode' in settings:
            old_mode = 'encoding' if self.ffmpeg_use_encoding else 'copy'
            self.ffmpeg_use_encoding = settings['ffmpeg_encoding_mode'] == 'encoding'
            new_mode = 'encoding' if self.ffmpeg_use_encoding else 'copy'
            if old_mode != new_mode:
                updated_settings.append(f"ffmpeg_mode: {old_mode} → {new_mode}")

        # HLS encoding settings
        if 'hls_video_preset' in settings:
            old_preset = self.hls_video_preset
            self.hls_video_preset = settings['hls_video_preset']
            if old_preset != self.hls_video_preset:
                updated_settings.append(f"hls_video_preset: {old_preset} → {self.hls_video_preset}")

        if 'hls_video_crf' in settings:
            old_crf = self.hls_video_crf
            self.hls_video_crf = int(settings['hls_video_crf'])
            if old_crf != self.hls_video_crf:
                updated_settings.append(f"hls_video_crf: {old_crf} → {self.hls_video_crf}")

        if 'hls_video_maxrate' in settings:
            old_maxrate = self.hls_video_maxrate
            self.hls_video_maxrate = settings['hls_video_maxrate']
            if old_maxrate != self.hls_video_maxrate:
                updated_settings.append(f"hls_video_maxrate: {old_maxrate} → {self.hls_video_maxrate}")

        if 'hls_audio_bitrate' in settings:
            old_bitrate = self.hls_audio_bitrate
            self.hls_audio_bitrate = settings['hls_audio_bitrate']
            if old_bitrate != self.hls_audio_bitrate:
                updated_settings.append(f"hls_audio_bitrate: {old_bitrate} → {self.hls_audio_bitrate}")

        # HLS segment settings
        if 'hls_segment_duration' in settings:
            old_duration = self.hls_segment_duration
            self.hls_segment_duration = int(settings['hls_segment_duration'])
            if old_duration != self.hls_segment_duration:
                updated_settings.append(f"hls_segment_duration: {old_duration} → {self.hls_segment_duration}")

        if 'hls_playlist_size' in settings:
            old_size = self.hls_playlist_size
            self.hls_playlist_size = int(settings['hls_playlist_size'])
            if old_size != self.hls_playlist_size:
                updated_settings.append(f"hls_playlist_size: {old_size} → {self.hls_playlist_size}")

        # Performance settings
        if 'max_concurrent_streams' in settings:
            old_max = self.max_concurrent_streams
            self.max_concurrent_streams = int(settings['max_concurrent_streams'])
            if old_max != self.max_concurrent_streams:
                updated_settings.append(f"max_concurrent_streams: {old_max} → {self.max_concurrent_streams}")

        if 'restart_threshold_failures' in settings:
            old_threshold = self.restart_threshold_failures
            self.restart_threshold_failures = int(settings['restart_threshold_failures'])
            if old_threshold != self.restart_threshold_failures:
                updated_settings.append(f"restart_threshold_failures: {old_threshold} → {self.restart_threshold_failures}")

        if 'fast_restart_delay' in settings:
            old_delay = self.fast_restart_delay
            self.fast_restart_delay = int(settings['fast_restart_delay'])
            if old_delay != self.fast_restart_delay:
                updated_settings.append(f"fast_restart_delay: {old_delay} → {self.fast_restart_delay}")

        # Log all changes
        if updated_settings:
            logging.info(f"🔧 Settings updated from Laravel: {', '.join(updated_settings)}")
            return updated_settings
        else:
            logging.info("🔧 No settings changes detected from Laravel")
            return []

    def fetch_laravel_settings(self):
        """Fetch settings from Redis (not HTTP API)"""
        try:
            import redis

            # Connect to Redis to get settings
            redis_client = redis.Redis(
                host=self.redis_host,
                port=self.redis_port,
                password=self.redis_password,
                decode_responses=True,
                socket_connect_timeout=5,
                socket_timeout=5
            )

            # Get settings from Redis
            settings_key = "agent_settings"
            settings_data = redis_client.get(settings_key)

            if settings_data:
                settings = json.loads(settings_data)
                updated_settings = self.update_from_laravel_settings(settings)
                logging.info("✅ Successfully fetched settings from Redis")
                return updated_settings  # Return list of changes
            else:
                logging.warning(f"⚠️ No settings found in Redis key: {settings_key}")
                return []

        except Exception as e:
            logging.warning(f"⚠️ Error fetching settings from Redis: {e}")
            return []
    
    # DEPRECATED: Nginx settings (removed in HLS Pipeline v4.0)
    # nginx_rtmp_base_url: str = 'rtmp://127.0.0.1:1935'  # ← REMOVED
    # nginx_config_dir: str = '/etc/nginx/rtmp-apps'       # ← REMOVED

    # Windows development override
    def __post_init__(self):
        if os.name == 'nt':  # Windows
            # Ensure HLS base directory exists on Windows
            os.makedirs(self.hls_base_dir, exist_ok=True)
            # Ensure download directory exists
            os.makedirs(self.download_base_dir, exist_ok=True)
    
    # Thread pool settings
    command_thread_pool_size: int = 10
    download_thread_pool_size: int = 5
    monitor_thread_pool_size: int = 20
    
    # Logging
    log_level: str = 'INFO'
    log_format: str = '%(asctime)s - %(name)s - %(levelname)s - %(message)s'
    
    @classmethod
    def from_args(cls, vps_id: int, redis_host: str, redis_port: int, redis_password: Optional[str] = None):
        """Create config from command line arguments"""
        return cls(
            vps_id=vps_id,
            redis_host=redis_host,
            redis_port=redis_port,
            redis_password=redis_password,
            laravel_base_url=os.getenv('APP_URL', 'http://localhost'),
            agent_token=os.getenv('AGENT_SECRET_TOKEN')
        )
    
    @classmethod
    def from_env(cls):
        """Create config from environment variables"""
        return cls(
            vps_id=int(os.getenv('VPS_ID', 0)),
            redis_host=os.getenv('REDIS_HOST', '127.0.0.1'),
            redis_port=int(os.getenv('REDIS_PORT', 6379)),
            redis_password=os.getenv('REDIS_PASSWORD'),
            max_concurrent_streams=int(os.getenv('MAX_CONCURRENT_STREAMS', 50)),
            download_base_dir=os.getenv('DOWNLOAD_BASE_DIR', '/tmp/ezstream_downloads'),
            cleanup_interval_seconds=int(os.getenv('CLEANUP_INTERVAL', 3600)),
            cleanup_threshold_hours=int(os.getenv('CLEANUP_THRESHOLD_HOURS', 24)),
            laravel_base_url=os.getenv('APP_URL', 'http://localhost'),
            agent_token=os.getenv('AGENT_SECRET_TOKEN')
        )
    
    def get_redis_url(self) -> str:
        """Get Redis connection URL"""
        if self.redis_password:
            return f"redis://:{self.redis_password}@{self.redis_host}:{self.redis_port}/0"
        return f"redis://{self.redis_host}:{self.redis_port}/0"
    
    def get_stream_download_dir(self, stream_id: int) -> str:
        """Get download directory for specific stream"""
        return os.path.join(self.download_base_dir, str(stream_id))
    
    # DEPRECATED: Nginx methods (removed in HLS Pipeline v4.0)
    # def get_nginx_app_config_path(self, stream_id: int) -> str:  # ← REMOVED
    # def get_rtmp_endpoint(self, stream_id: int) -> str:          # ← REMOVED

    def get_hls_output_dir(self, stream_id: int) -> str:
        """Get HLS output directory for stream"""
        return os.path.join(self.hls_base_dir, f'stream_{stream_id}')

    def get_hls_playlist_path(self, stream_id: int) -> str:
        """Get HLS playlist path for stream"""
        return os.path.join(self.get_hls_output_dir(stream_id), 'playlist.m3u8')


# Global config instance
config: Optional[AgentConfig] = None


def init_config(vps_id: int, redis_host: str, redis_port: int, redis_password: Optional[str] = None):
    """Initialize global config"""
    global config
    config = AgentConfig.from_args(vps_id, redis_host, redis_port, redis_password)
    return config


def get_config() -> AgentConfig:
    """Get global config instance"""
    if config is None:
        raise RuntimeError("Config not initialized. Call init_config() first.")
    return config
