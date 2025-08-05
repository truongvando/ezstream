#!/usr/bin/env python3
"""
EZStream Agent Configuration
SRS-only streaming configuration
"""

import os
import logging
from dataclasses import dataclass
from typing import Optional


@dataclass
class Config:
    """Agent configuration for SRS streaming"""
    
    # Agent identification
    agent_id: str = "ezstream-agent"
    agent_version: str = "6.0-srs-only"
    
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
    
    # SRS Server settings - MAIN STREAMING METHOD
    srs_host: str = 'localhost'                     # SRS server host
    srs_port: int = 1985                            # SRS HTTP API port
    srs_rtmp_port: int = 1935                       # SRS RTMP port
    srs_ingest_timeout: int = 30                    # SRS ingest start timeout (seconds)
    srs_health_check_interval: int = 10             # SRS health check interval (seconds)

    def update_from_laravel_settings(self, settings: dict):
        """Update config from Laravel settings"""
        updated_settings = []

        # SRS settings
        if 'srs_host' in settings:
            old_host = self.srs_host
            self.srs_host = settings['srs_host']
            if old_host != self.srs_host:
                updated_settings.append(f"srs_host: {old_host} → {self.srs_host}")

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

    def get_srs_config(self) -> dict:
        """Get SRS configuration"""
        return {
            'host': self.srs_host,
            'port': self.srs_port,
            'rtmp_port': self.srs_rtmp_port,
            'ingest_timeout': self.srs_ingest_timeout,
            'health_check_interval': self.srs_health_check_interval
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

            # Validate SRS settings
            if not self.srs_host:
                logging.error("❌ SRS host is required")
                return False

            if self.srs_port <= 0 or self.srs_port > 65535:
                logging.error(f"❌ Invalid SRS port: {self.srs_port}")
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

        # SRS settings
        self.srs_host = os.getenv('SRS_HOST', self.srs_host)
        self.srs_port = int(os.getenv('SRS_PORT', self.srs_port))
        self.srs_rtmp_port = int(os.getenv('SRS_RTMP_PORT', self.srs_rtmp_port))

        # File management
        self.base_download_dir = os.getenv('DOWNLOAD_DIR', self.base_download_dir)

        logging.info("🔧 Configuration loaded from environment")


# Global configuration instance
_config: Optional[Config] = None


def init_config() -> Config:
    """Initialize global configuration"""
    global _config
    _config = Config()
    _config.load_from_env()
    
    if not _config.validate():
        raise RuntimeError("Configuration validation failed")
    
    return _config


def get_config() -> Config:
    """Get global configuration instance"""
    if _config is None:
        raise RuntimeError("Configuration not initialized. Call init_config() first.")
    return _config
