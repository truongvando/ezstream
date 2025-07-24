#!/usr/bin/env python3
"""
EZStream Agent Configuration Management
Centralized configuration for all agent components
"""

import os
from dataclasses import dataclass
from typing import Optional


@dataclass
class AgentConfig:
    """Main configuration class for EZStream Agent"""
    
    # VPS and Redis settings
    vps_id: int
    redis_host: str = '127.0.0.1'
    redis_port: int = 6379
    redis_password: Optional[str] = None
    
    # Process management
    max_concurrent_streams: int = 50
    graceful_shutdown_timeout: int = 10
    system_cleanup_wait: int = 3
    force_kill_timeout: int = 5
    
    # File management
    download_base_dir: str = '/tmp/ezstream_downloads'
    cleanup_interval_seconds: int = 3600  # 1 hour
    cleanup_threshold_hours: int = 24     # 24 hours
    max_download_retries: int = 3
    download_chunk_size: int = 8192
    
    # Reporting intervals
    stats_report_interval: int = 15       # 15 seconds
    heartbeat_interval: int = 10          # 10 seconds
    progress_throttle_interval: int = 2   # 2 seconds
    
    # FFmpeg settings
    ffmpeg_reconnect_attempts: int = 5
    ffmpeg_reconnect_delay: int = 2
    ffmpeg_startup_timeout: int = 15
    
    # Nginx settings
    nginx_rtmp_base_url: str = 'rtmp://127.0.0.1:1935'
    nginx_config_dir: str = '/etc/nginx/rtmp-apps'
    
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
            redis_password=redis_password
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
            cleanup_threshold_hours=int(os.getenv('CLEANUP_THRESHOLD_HOURS', 24))
        )
    
    def get_redis_url(self) -> str:
        """Get Redis connection URL"""
        if self.redis_password:
            return f"redis://:{self.redis_password}@{self.redis_host}:{self.redis_port}/0"
        return f"redis://{self.redis_host}:{self.redis_port}/0"
    
    def get_stream_download_dir(self, stream_id: int) -> str:
        """Get download directory for specific stream"""
        return os.path.join(self.download_base_dir, str(stream_id))
    
    def get_nginx_app_config_path(self, stream_id: int) -> str:
        """Get nginx app config path for stream"""
        return os.path.join(self.nginx_config_dir, f'stream_{stream_id}.conf')
    
    def get_rtmp_endpoint(self, stream_id: int) -> str:
        """Get RTMP endpoint for stream"""
        return f"{self.nginx_rtmp_base_url}/stream_{stream_id}/stream_{stream_id}"


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
