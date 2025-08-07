#!/usr/bin/env python3
"""
EZStream Agent Configuration v7.0
Simple FFmpeg Direct Streaming Configuration
"""

import os
import logging
from dataclasses import dataclass
from typing import Optional


@dataclass
class Config:
    """Agent configuration for Simple FFmpeg Direct Streaming"""

    # VPS ID - REQUIRED! Must be first (no default value)
    vps_id: int

    # Agent identification (with defaults)
    agent_id: str = "ezstream-agent"
    agent_version: str = "7.0-simple-ffmpeg"
    
    # Redis connection
    redis_host: str = "localhost"
    redis_port: int = 6379
    redis_db: int = 0
    redis_password: Optional[str] = None
    
    # Laravel communication
    laravel_base_url: str = "http://localhost"
    laravel_api_token: Optional[str] = None
    
    # File management
    base_download_dir: str = "/tmp/ezstream-downloads"
    max_concurrent_downloads: int = 3
    download_timeout: int = 300
    cleanup_after_hours: int = 24
    
    # Reporting intervals
    stats_report_interval: int = 15       # 15 seconds
    heartbeat_interval: int = 5           # 5 seconds
    progress_throttle_interval: int = 2   # 2 seconds
    
    # Simple FFmpeg Direct Streaming settings
    ffmpeg_reconnect_delay: int = 5                 # FFmpeg reconnect delay (seconds)
    ffmpeg_health_check_interval: int = 30          # FFmpeg health check interval (seconds)
    ffmpeg_max_retries: int = 5                     # Max restart attempts per stream
    ffmpeg_restart_delay: int = 10                  # Delay between restart attempts (seconds)

    def update_from_laravel_settings(self, settings: dict):
        """Update config from Laravel settings"""
        updated_settings = []

        # FFmpeg settings
        if 'ffmpeg_reconnect_delay' in settings:
            old_delay = self.ffmpeg_reconnect_delay
            self.ffmpeg_reconnect_delay = int(settings['ffmpeg_reconnect_delay'])
            if old_delay != self.ffmpeg_reconnect_delay:
                updated_settings.append(f"ffmpeg_reconnect_delay: {old_delay} → {self.ffmpeg_reconnect_delay}")

        if 'ffmpeg_max_retries' in settings:
            old_retries = self.ffmpeg_max_retries
            self.ffmpeg_max_retries = int(settings['ffmpeg_max_retries'])
            if old_retries != self.ffmpeg_max_retries:
                updated_settings.append(f"ffmpeg_max_retries: {old_retries} → {self.ffmpeg_max_retries}")

        # Heartbeat interval
        if 'heartbeat_interval' in settings:
            old_interval = self.heartbeat_interval
            self.heartbeat_interval = int(settings['heartbeat_interval'])
            if old_interval != self.heartbeat_interval:
                updated_settings.append(f"heartbeat_interval: {old_interval} → {self.heartbeat_interval}")

        # Log all changes
        if updated_settings:
            logging.info(f"🔧 Settings updated from Laravel: {', '.join(updated_settings)}")
            return updated_settings

        return []

    def get_redis_config(self) -> dict:
        """Get Redis configuration"""
        return {
            'host': self.redis_host,
            'port': self.redis_port,
            'db': self.redis_db,
            'password': self.redis_password,
            'decode_responses': True,
            'socket_connect_timeout': 5,
            'socket_timeout': 5,
            'retry_on_timeout': True
        }

    def get_stream_download_dir(self, stream_id: int) -> str:
        """Get download directory for a specific stream"""
        return os.path.join(self.base_download_dir, f"stream_{stream_id}")

    def get_ffmpeg_config(self) -> dict:
        """Get FFmpeg configuration"""
        return {
            'reconnect_delay': self.ffmpeg_reconnect_delay,
            'health_check_interval': self.ffmpeg_health_check_interval,
            'max_retries': self.ffmpeg_max_retries,
            'restart_delay': self.ffmpeg_restart_delay
        }

    def get_laravel_config(self) -> dict:
        """Get Laravel API configuration"""
        return {
            'base_url': self.laravel_base_url,
            'api_token': self.laravel_api_token,
            'timeout': 30
        }

    def validate(self) -> bool:
        """Validate configuration"""
        try:
            # Check required directories
            if not os.path.exists(self.base_download_dir):
                os.makedirs(self.base_download_dir, exist_ok=True)
                logging.info(f"📁 Created download directory: {self.base_download_dir}")

            # Validate FFmpeg settings
            if self.ffmpeg_max_retries <= 0:
                logging.error(f"❌ Invalid FFmpeg max retries: {self.ffmpeg_max_retries}")
                return False

            if self.ffmpeg_restart_delay <= 0:
                logging.error(f"❌ Invalid FFmpeg restart delay: {self.ffmpeg_restart_delay}")
                return False

            logging.info("✅ Configuration validated successfully")
            return True

        except Exception as e:
            logging.error(f"❌ Configuration validation failed: {e}")
            return False

    def load_from_env(self):
        """Load configuration from environment variables"""
        # Redis settings
        self.redis_host = os.getenv('REDIS_HOST', self.redis_host)
        self.redis_port = int(os.getenv('REDIS_PORT', self.redis_port))
        self.redis_password = os.getenv('REDIS_PASSWORD', self.redis_password)

        # Laravel settings
        self.laravel_base_url = os.getenv('LARAVEL_BASE_URL', self.laravel_base_url)
        self.laravel_api_token = os.getenv('LARAVEL_API_TOKEN', self.laravel_api_token)

        # FFmpeg settings
        self.ffmpeg_reconnect_delay = int(os.getenv('FFMPEG_RECONNECT_DELAY', self.ffmpeg_reconnect_delay))
        self.ffmpeg_max_retries = int(os.getenv('FFMPEG_MAX_RETRIES', self.ffmpeg_max_retries))
        self.ffmpeg_restart_delay = int(os.getenv('FFMPEG_RESTART_DELAY', self.ffmpeg_restart_delay))

        # File management
        self.base_download_dir = os.getenv('DOWNLOAD_DIR', self.base_download_dir)

        logging.info("🔧 Configuration loaded from environment")


# Global configuration instance
_config: Optional[Config] = None


def init_config(vps_id: int, redis_host: str = None, redis_port: int = None, redis_password: str = None) -> Config:
    """Initialize global configuration"""
    global _config

    if vps_id is None:
        raise ValueError("vps_id is required for agent initialization")

    _config = Config(vps_id=vps_id)

    # Override with provided parameters
    if redis_host:
        _config.redis_host = redis_host
    if redis_port:
        _config.redis_port = redis_port
    if redis_password:
        _config.redis_password = redis_password

    # Load from environment (can override the above)
    _config.load_from_env()

    if not _config.validate():
        raise RuntimeError("Configuration validation failed")

    return _config


def get_config() -> Config:
    """Get global configuration instance"""
    if _config is None:
        raise RuntimeError("Configuration not initialized. Call init_config() first.")
    return _config
