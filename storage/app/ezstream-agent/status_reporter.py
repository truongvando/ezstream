#!/usr/bin/env python3
"""
EZStream Agent Status Reporter
Handles all communication with Laravel via Redis
"""

import json
import time
import logging
import threading
from typing import Dict, Any, Optional, Set
from concurrent.futures import ThreadPoolExecutor

import redis
import psutil

from config import get_config
from utils import safe_json_dumps, throttle_calls, PerformanceTimer


class StatusReporter:
    """Manages all status reporting to Laravel via Redis"""
    
    def __init__(self):
        self.config = get_config()
        self.redis_conn = None
        self.running = False
        
        # Progress throttling - only send progress every N seconds
        self.last_progress_time = {}
        
        # Thread pool for non-blocking reports
        self.executor = ThreadPoolExecutor(max_workers=3, thread_name_prefix="StatusReporter")
        
        # Stats cache
        self._stats_cache = {}
        self._stats_cache_time = 0
        
        self._connect_redis()
    
    def _connect_redis(self):
        """Connect to Redis"""
        try:
            self.redis_conn = redis.Redis(
                host=self.config.redis_host,
                port=self.config.redis_port,
                password=self.config.redis_password,
                decode_responses=True,
                socket_connect_timeout=5,
                socket_timeout=5,
                retry_on_timeout=True
            )
            
            # Test connection
            self.redis_conn.ping()
            logging.info(f"✅ Connected to Redis at {self.config.redis_host}:{self.config.redis_port}")
            
        except Exception as e:
            logging.error(f"❌ Failed to connect to Redis: {e}")
            raise
    
    def start(self):
        """Start background reporting threads"""
        self.running = True
        
        # Start stats reporter thread
        stats_thread = threading.Thread(
            target=self._stats_reporter_loop,
            name="StatsReporter",
            daemon=True
        )
        stats_thread.start()
        
        # Start heartbeat thread
        heartbeat_thread = threading.Thread(
            target=self._heartbeat_loop,
            name="HeartbeatReporter",
            daemon=True
        )
        heartbeat_thread.start()
        
        logging.info("📊 Status reporter started")
    
    def stop(self):
        """Stop all reporting"""
        self.running = False

        # Shutdown executor safely (compatible with older Python)
        try:
            self.executor.shutdown(wait=True)
            logging.info("✅ Status reporter executor shutdown")
        except Exception as e:
            logging.error(f"❌ Error shutting down status reporter executor: {e}")

        # Close Redis connection safely
        try:
            if self.redis_conn:
                self.redis_conn.close()
                logging.info("✅ Status reporter Redis connection closed")
        except Exception as e:
            logging.error(f"❌ Error closing status reporter Redis connection: {e}")

        logging.info("📊 Status reporter stopped")
    
    def publish_stream_status(self, stream_id: int, status: str, message: str, extra_data: Optional[Dict] = None):
        """Publish stream status update to Laravel"""
        # Submit to thread pool for non-blocking execution
        self.executor.submit(self._publish_stream_status_sync, stream_id, status, message, extra_data)
    
    def _publish_stream_status_sync(self, stream_id: int, status: str, message: str, extra_data: Optional[Dict] = None):
        """Synchronous stream status publishing"""
        try:
            # Throttle progress updates
            if status == 'PROGRESS':
                current_time = time.time()
                last_time = self.last_progress_time.get(stream_id, 0)
                
                if current_time - last_time < self.config.progress_throttle_interval:
                    return  # Skip this progress update
                
                self.last_progress_time[stream_id] = current_time
            
            payload = {
                'type': 'STATUS_UPDATE',
                'stream_id': stream_id,
                'vps_id': self.config.vps_id,
                'status': status,
                'message': message,
                'timestamp': int(time.time()),
                'extra_data': extra_data or {}
            }
            
            logging.info(f"🔄 [STATUS] Sending status update for stream {stream_id}: {status} - {message}")
            logging.debug(f"🔍 [STATUS] Full payload: {safe_json_dumps(payload, indent=2)}")
            
            self._publish_report(payload)
            
        except Exception as e:
            logging.error(f"❌ Error publishing stream status: {e}")

    def publish_restart_request(self, stream_id: int, reason: str, crash_count: int, last_error: str = None, error_type: str = None):
        """Request Laravel to decide whether to restart stream"""
        try:
            payload = {
                'type': 'RESTART_REQUEST',
                'stream_id': stream_id,
                'vps_id': self.config.vps_id,
                'reason': reason,
                'crash_count': crash_count,
                'last_error': last_error,
                'error_type': error_type,
                'timestamp': int(time.time())
            }

            logging.warning(f"🔄 [RESTART_REQUEST] Stream {stream_id} crashed #{crash_count}: {reason}")
            logging.debug(f"🔍 [RESTART_REQUEST] Full payload: {safe_json_dumps(payload, indent=2)}")

            self._publish_report(payload)

        except Exception as e:
            logging.error(f"❌ Error publishing restart request: {e}")

    def _publish_report(self, payload: Dict[str, Any]):
        """Publish report to Redis"""
        try:
            json_payload = safe_json_dumps(payload)
            channel = 'agent-reports'
            
            subscribers = self.redis_conn.publish(channel, json_payload)
            report_type = payload.get('type', 'UNKNOWN')
            
            logging.debug(f"📤 [REDIS] Published '{report_type}' to '{channel}' -> {subscribers} subscribers")
            
        except Exception as e:
            logging.error(f"❌ [REDIS] Failed to publish report: {e}")
    
    def _stats_reporter_loop(self):
        """Background thread for system stats reporting"""
        logging.info(f"📊 Stats reporter thread started. Reporting every {self.config.stats_report_interval}s")
        
        while self.running:
            try:
                with PerformanceTimer("Stats Collection"):
                    stats = self._collect_system_stats()
                
                # Send stats via Redis
                payload = safe_json_dumps(stats)
                result = self.redis_conn.publish('vps-stats', payload)
                
                logging.debug(f"📊 Stats sent via Redis: {payload} -> subscribers: {result}")
                
            except Exception as e:
                logging.error(f"❌ Error in stats_reporter_loop: {e}")
            
            time.sleep(self.config.stats_report_interval)
    
    def _heartbeat_loop(self):
        """Background thread for heartbeat reporting"""
        logging.info(f"💓 Heartbeat thread started. Reporting every {self.config.heartbeat_interval}s")

        consecutive_failures = 0
        last_successful_publish = time.time()

        while self.running:
            try:
                # Get active streams from stream manager
                from stream_manager import get_stream_manager
                stream_manager = get_stream_manager()
                active_stream_ids = stream_manager.get_active_stream_ids() if stream_manager else []

                heartbeat_payload = {
                    'type': 'HEARTBEAT',
                    'vps_id': self.config.vps_id,
                    'active_streams': active_stream_ids,
                    'timestamp': int(time.time()),
                }

                # Check if we need to re-announce streams (after potential Laravel restart)
                current_time = time.time()
                if current_time - last_successful_publish > 60:  # No successful publish for 1 minute
                    logging.warning(f"🔄 Potential Laravel restart detected. Re-announcing {len(active_stream_ids)} active streams...")
                    heartbeat_payload['re_announce'] = True

                self._publish_report(heartbeat_payload)

                # Reset failure counter on success
                consecutive_failures = 0
                last_successful_publish = current_time

            except Exception as e:
                consecutive_failures += 1
                logging.error(f"❌ Error in heartbeat_loop (failure #{consecutive_failures}): {e}")

                # If too many consecutive failures, try to reconnect Redis
                if consecutive_failures >= 5:
                    logging.warning("🔄 Too many heartbeat failures, attempting Redis reconnect...")
                    try:
                        self._connect_redis()
                        consecutive_failures = 0
                    except Exception as reconnect_error:
                        logging.error(f"❌ Redis reconnect failed: {reconnect_error}")

            time.sleep(self.config.heartbeat_interval)
    
    def _collect_system_stats(self) -> Dict[str, Any]:
        """Collect system statistics"""
        # Cache stats for 5 seconds to avoid excessive system calls
        current_time = time.time()
        if current_time - self._stats_cache_time < 5 and self._stats_cache:
            return self._stats_cache
        
        try:
            # CPU usage
            cpu_usage = psutil.cpu_percent(interval=1)
            
            # Memory usage
            memory = psutil.virtual_memory()
            ram_usage = memory.percent
            
            # Disk usage
            disk = psutil.disk_usage('/')
            disk_usage = (disk.used / disk.total) * 100
            disk_total_gb = disk.total / (1024**3)
            disk_used_gb = disk.used / (1024**3)
            disk_free_gb = disk.free / (1024**3)
            
            # Network stats
            network = psutil.net_io_counters()
            network_sent_mb = network.bytes_sent / (1024**2)
            network_recv_mb = network.bytes_recv / (1024**2)
            
            # Active streams count
            from stream_manager import get_stream_manager
            stream_manager = get_stream_manager()
            active_streams = len(stream_manager.get_active_stream_ids()) if stream_manager else 0
            
            stats = {
                'vps_id': self.config.vps_id,
                'cpu_usage': round(cpu_usage, 1),
                'ram_usage': round(ram_usage, 1),
                'disk_usage': round(disk_usage, 1),
                'disk_total_gb': round(disk_total_gb, 1),
                'disk_used_gb': round(disk_used_gb, 1),
                'disk_free_gb': round(disk_free_gb, 1),
                'active_streams': active_streams,
                'network_sent_mb': round(network_sent_mb, 1),
                'network_recv_mb': round(network_recv_mb, 1),
                'timestamp': int(time.time())
            }
            
            # Cache the stats
            self._stats_cache = stats
            self._stats_cache_time = current_time
            
            return stats
            
        except Exception as e:
            logging.error(f"Error collecting system stats: {e}")
            return {
                'vps_id': self.config.vps_id,
                'error': str(e),
                'timestamp': int(time.time())
            }


# Global status reporter instance
_status_reporter: Optional[StatusReporter] = None


def init_status_reporter() -> StatusReporter:
    """Initialize global status reporter"""
    global _status_reporter
    _status_reporter = StatusReporter()
    return _status_reporter


def get_status_reporter() -> Optional[StatusReporter]:
    """Get global status reporter instance"""
    return _status_reporter
