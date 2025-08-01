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

    # Direct streaming settings for YouTube optimization
    direct_streaming_enabled: bool = True           # Enable direct FFmpeg RTMP streaming
    youtube_rtmp_url: str = 'rtmp://a.rtmp.youtube.com/live2/'  # YouTube RTMP endpoint

    # YouTube FHD technical specifications
    youtube_video_codec: str = 'libx264'            # H.264 for YouTube
    youtube_video_profile: str = 'high'             # High Profile for FHD
    youtube_video_level: str = '4.0'                # Level 4.0 for FHD
    youtube_video_bitrate: str = '5000k'            # 5 Mbps for FHD
    youtube_video_maxrate: str = '6000k'            # Max 6 Mbps
    youtube_video_bufsize: str = '12000k'           # Buffer size
    youtube_video_fps: int = 30                     # 30 FPS
    youtube_video_gop: int = 60                     # GOP size (2s at 30fps)
    youtube_video_preset: str = 'fast'              # Encoding preset
    youtube_video_tune: str = 'zerolatency'         # Tune for streaming

    # YouTube audio specifications
    youtube_audio_codec: str = 'aac'                # AAC-LC for YouTube
    youtube_audio_bitrate: str = '128k'             # 128 kbps
    youtube_audio_samplerate: int = 44100           # 44.1 kHz
    youtube_audio_channels: int = 2                 # Stereo

    # RTMP streaming settings
    rtmp_reconnect_enabled: bool = True             # Enable reconnect
    rtmp_reconnect_at_eof: bool = True              # Reconnect at EOF
    rtmp_reconnect_streamed: bool = True            # Reconnect for streamed content
    rtmp_reconnect_delay_max: int = 5               # Max reconnect delay
    rtmp_buffer_size: int = 1000                    # RTMP buffer (ms)
    rtmp_global_timeout: int = 10000000             # Global timeout (microseconds)

    # Reconnect logic settings - Unlimited reconnect for 24/7 streaming
    reconnect_max_attempts: int = -1                # Unlimited reconnect attempts (-1 = infinite)
    reconnect_base_delay: float = 2.0               # Base delay between attempts
    reconnect_max_delay: float = 300.0              # Max delay between attempts (5 minutes)
    reconnect_exponential_factor: float = 1.5       # Exponential backoff factor
    reconnect_reset_after_success: float = 300.0    # Reset counter after success (seconds)

    # Concurrent processing settings - No stream limit (Laravel manages VPS resources)
    max_concurrent_streams: int = -1                # No limit (-1 = unlimited, Laravel decides)
    stream_start_timeout: float = 60.0              # Longer timeout for downloads
    concurrent_validation_workers: int = 10         # Workers for file validation
    concurrent_download_workers: int = 5            # Workers for file downloads
    process_monitor_interval: float = 2.0           # Process monitoring interval (seconds)
    async_operations_enabled: bool = True           # Enable async operations

    # Stream restart settings
    restart_threshold_failures: int = 3             # Number of failures before restart
    fast_restart_delay: int = 5                     # Delay before fast restart (seconds)

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

        # YouTube streaming settings
        if 'youtube_video_preset' in settings:
            old_preset = self.youtube_video_preset
            self.youtube_video_preset = settings['youtube_video_preset']
            if old_preset != self.youtube_video_preset:
                updated_settings.append(f"youtube_video_preset: {old_preset} → {self.youtube_video_preset}")

        if 'youtube_video_bitrate' in settings:
            old_bitrate = self.youtube_video_bitrate
            self.youtube_video_bitrate = settings['youtube_video_bitrate']
            if old_bitrate != self.youtube_video_bitrate:
                updated_settings.append(f"youtube_video_bitrate: {old_bitrate} → {self.youtube_video_bitrate}")

        if 'youtube_video_maxrate' in settings:
            old_maxrate = self.youtube_video_maxrate
            self.youtube_video_maxrate = settings['youtube_video_maxrate']
            if old_maxrate != self.youtube_video_maxrate:
                updated_settings.append(f"youtube_video_maxrate: {old_maxrate} → {self.youtube_video_maxrate}")

        # Reconnect settings
        if 'reconnect_max_attempts' in settings:
            old_attempts = self.reconnect_max_attempts
            self.reconnect_max_attempts = int(settings['reconnect_max_attempts'])
            if old_attempts != self.reconnect_max_attempts:
                updated_settings.append(f"reconnect_max_attempts: {old_attempts} → {self.reconnect_max_attempts}")

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

    # Windows development override
    def __post_init__(self):
        if os.name == 'nt':  # Windows
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

    def get_youtube_rtmp_endpoint(self, stream_key: str) -> str:
        """Get YouTube RTMP endpoint with stream key"""
        if not stream_key:
            raise ValueError("Stream key is required for YouTube RTMP")

        # Ensure URL ends with /
        rtmp_url = self.youtube_rtmp_url
        if not rtmp_url.endswith('/'):
            rtmp_url += '/'

        return f"{rtmp_url}{stream_key}"

    def get_direct_streaming_config(self) -> dict:
        """Get direct streaming configuration"""
        return {
            'enabled': self.direct_streaming_enabled,
            'youtube_rtmp_url': self.youtube_rtmp_url,
            'video_codec': self.youtube_video_codec,
            'video_profile': self.youtube_video_profile,
            'video_bitrate': self.youtube_video_bitrate,
            'video_maxrate': self.youtube_video_maxrate,
            'video_fps': self.youtube_video_fps,
            'audio_codec': self.youtube_audio_codec,
            'audio_bitrate': self.youtube_audio_bitrate,
            'reconnect_enabled': self.rtmp_reconnect_enabled,
            'reconnect_max_attempts': self.reconnect_max_attempts,
            'reconnect_base_delay': self.reconnect_base_delay
        }

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
