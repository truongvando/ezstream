#!/usr/bin/env python3
"""
Simple Stream Manager - FFmpeg Direct Only
No SRS bullshit, just reliable FFmpeg streaming
"""

import subprocess
import threading
import time
import logging
import psutil
import os
import requests
import hashlib
from typing import Dict, Optional, List
from enum import Enum
from dataclasses import dataclass

class StreamStatus(Enum):
    STOPPED = "stopped"
    STARTING = "starting"
    RUNNING = "running"
    ERROR = "error"
    RESTARTING = "restarting"

@dataclass
class StreamConfig:
    stream_id: int
    input_urls: List[str]  # Multiple HLS URLs
    output_url: str
    loop_enabled: bool = True
    playback_mode: str = "sequential"  # sequential, random
    max_retries: int = 5
    restart_delay: int = 10
    health_check_interval: int = 30

class SimpleStreamManager:
    """Simple, reliable stream manager using FFmpeg direct"""
    
    def __init__(self):
        self.streams: Dict[int, Dict] = {}
        self.monitoring_threads: Dict[int, threading.Thread] = {}
        self.running = True
        self.cache_dir = "/tmp/ezstream_cache"

        # Create cache directory
        os.makedirs(self.cache_dir, exist_ok=True)

        logging.info("🎬 Simple Stream Manager initialized (FFmpeg Direct with Caching)")

    def _get_cache_key(self, url: str) -> str:
        """Generate cache key for URL"""
        return hashlib.md5(url.encode()).hexdigest()

    def _should_cache_stream(self, config: StreamConfig) -> bool:
        """Determine if stream should be cached (for loops)"""
        return config.loop_enabled and len(config.input_urls) <= 5  # Cache small playlists

    def start_stream(self, config: StreamConfig) -> bool:
        """Start a stream with auto-restart capability"""
        try:
            stream_id = config.stream_id
            
            if stream_id in self.streams:
                logging.warning(f"⚠️ Stream {stream_id} already exists, stopping first")
                self.stop_stream(stream_id)
            
            # Initialize stream state
            self.streams[stream_id] = {
                'config': config,
                'status': StreamStatus.STARTING,
                'process': None,
                'retry_count': 0,
                'start_time': time.time(),
                'last_restart': None
            }
            
            logging.info(f"🚀 Starting stream {stream_id}")
            logging.info(f"   Input: {config.input_url}")
            logging.info(f"   Output: {config.output_url}")
            
            # Start monitoring thread
            monitor_thread = threading.Thread(
                target=self._monitor_stream,
                args=(stream_id,),
                daemon=True
            )
            monitor_thread.start()
            self.monitoring_threads[stream_id] = monitor_thread
            
            return True
            
        except Exception as e:
            logging.error(f"❌ Error starting stream {stream_id}: {e}")
            return False
    
    def stop_stream(self, stream_id: int) -> bool:
        """Stop a stream and its monitoring"""
        try:
            if stream_id not in self.streams:
                logging.warning(f"⚠️ Stream {stream_id} not found")
                return True
            
            logging.info(f"🛑 Stopping stream {stream_id}")
            
            # Update status
            self.streams[stream_id]['status'] = StreamStatus.STOPPED
            
            # Kill process
            process = self.streams[stream_id].get('process')
            if process and process.poll() is None:
                try:
                    # Graceful termination
                    process.terminate()
                    time.sleep(2)
                    
                    # Force kill if still running
                    if process.poll() is None:
                        process.kill()
                        
                    logging.info(f"✅ Stream {stream_id} process terminated")
                except Exception as e:
                    logging.error(f"❌ Error killing process: {e}")
            
            # Stop monitoring thread
            if stream_id in self.monitoring_threads:
                # Thread will exit when status is STOPPED
                del self.monitoring_threads[stream_id]
            
            # Clean up
            del self.streams[stream_id]
            
            logging.info(f"✅ Stream {stream_id} stopped successfully")
            return True
            
        except Exception as e:
            logging.error(f"❌ Error stopping stream {stream_id}: {e}")
            return False
    
    def get_stream_status(self, stream_id: int) -> Optional[Dict]:
        """Get stream status and stats"""
        if stream_id not in self.streams:
            return None
        
        stream = self.streams[stream_id]
        process = stream.get('process')
        
        status = {
            'stream_id': stream_id,
            'status': stream['status'].value,
            'retry_count': stream['retry_count'],
            'uptime': time.time() - stream['start_time'],
            'last_restart': stream['last_restart'],
            'process_alive': process and process.poll() is None if process else False
        }
        
        # Add process stats if running
        if process and process.poll() is None:
            try:
                proc = psutil.Process(process.pid)
                status.update({
                    'cpu_percent': proc.cpu_percent(),
                    'memory_mb': proc.memory_info().rss / 1024 / 1024,
                    'pid': process.pid
                })
            except:
                pass
        
        return status
    
    def get_all_streams_status(self) -> List[Dict]:
        """Get status of all streams with enhanced health info"""
        status_list = []

        for stream_id in self.streams.keys():
            stream_status = self.get_stream_status(stream_id)
            if stream_status:
                # Add health check
                health = self._check_stream_health(stream_id)
                stream_status['health'] = health

                # Add loop info
                config = self.streams[stream_id].get('config')
                stream_status['loop_enabled'] = config.loop_enabled if config else False

                status_list.append(stream_status)

        return status_list

    def _check_stream_health(self, stream_id: int) -> str:
        """Check individual stream health"""
        try:
            if stream_id not in self.streams:
                return 'not_found'

            stream = self.streams[stream_id]
            process = stream.get('process')

            if not process:
                return 'no_process'

            if process.poll() is not None:
                return 'process_dead'

            # Check if process is active
            try:
                proc = psutil.Process(process.pid)
                cpu_percent = proc.cpu_percent()

                # Update last health check
                stream['last_health_check'] = time.time()

                # Health determination
                if cpu_percent > 0.1:  # Active streaming
                    return 'healthy'
                else:
                    return 'idle'  # Process exists but not active

            except psutil.NoSuchProcess:
                return 'process_dead'
            except Exception as e:
                logging.debug(f"Health check error for stream {stream_id}: {e}")
                return 'unknown'

        except Exception as e:
            logging.error(f"❌ Error checking health for stream {stream_id}: {e}")
            return 'error'
    
    def _monitor_stream(self, stream_id: int):
        """Monitor stream health and restart if needed"""
        stream = self.streams[stream_id]
        config = stream['config']
        
        while stream['status'] != StreamStatus.STOPPED and self.running:
            try:
                # Start/restart FFmpeg process
                if not self._start_ffmpeg_process(stream_id):
                    if stream['retry_count'] >= config.max_retries:
                        logging.error(f"❌ Stream {stream_id} exceeded max retries ({config.max_retries})")
                        stream['status'] = StreamStatus.ERROR
                        break
                    
                    # Wait before retry
                    time.sleep(config.restart_delay)
                    continue
                
                # Monitor process health with status reporting
                while (stream['status'] != StreamStatus.STOPPED and
                       self.running and
                       self._is_process_healthy(stream_id)):

                    # Report health status periodically
                    self._report_stream_health(stream_id)

                    time.sleep(config.health_check_interval)
                
                # Process died or unhealthy
                if stream['status'] != StreamStatus.STOPPED:
                    health = self._check_stream_health(stream_id)
                    logging.warning(f"⚠️ Stream {stream_id} process died ({health}), restarting...")

                    # Report disconnect to Laravel
                    self._report_stream_disconnect(stream_id, health)

                    stream['status'] = StreamStatus.RESTARTING
                    stream['retry_count'] += 1
                    stream['last_restart'] = time.time()

                    # Clean up dead process
                    self._cleanup_process(stream_id)
                    
                    # Wait before restart
                    time.sleep(config.restart_delay)
                
            except Exception as e:
                logging.error(f"❌ Monitor error for stream {stream_id}: {e}")
                time.sleep(config.restart_delay)
        
        logging.info(f"🏁 Monitor thread for stream {stream_id} exited")
    
    def _create_playlist_file(self, stream_id: int, input_urls: List[str]) -> str:
        """Create playlist file for multiple URLs"""
        playlist_path = f"/tmp/playlist_{stream_id}.txt"

        try:
            with open(playlist_path, 'w') as f:
                for url in input_urls:
                    f.write(f"file '{url}'\n")

            logging.info(f"📝 Created playlist for stream {stream_id} with {len(input_urls)} videos")
            return playlist_path

        except Exception as e:
            logging.error(f"❌ Failed to create playlist for stream {stream_id}: {e}")
            return None

    def _start_ffmpeg_process(self, stream_id: int) -> bool:
        """Start FFmpeg process for stream"""
        try:
            stream = self.streams[stream_id]
            config = stream['config']

            # Handle multiple input URLs
            if len(config.input_urls) > 1:
                # Multiple videos - use playlist
                playlist_path = self._create_playlist_file(stream_id, config.input_urls)
                if not playlist_path:
                    return False

                input_source = playlist_path
                input_format = ['-f', 'concat', '-safe', '0']
            else:
                # Single video - direct URL
                input_source = config.input_urls[0]
                input_format = []

            logging.info(f"🎯 Using copy mode to preserve original M3U8 quality")

            # Build FFmpeg command with quality optimization
            cmd = ['ffmpeg', '-re']  # Read input at native frame rate

            # Add input format if needed (for playlist)
            cmd.extend(input_format)

            # Add input source
            cmd.extend(['-i', input_source])

            # Add loop if enabled
            if config.loop_enabled:
                cmd.extend(['-stream_loop', '-1'])

            cmd.extend([
                # Reconnection options
                '-reconnect', '1',
                '-reconnect_streamed', '1',
                '-reconnect_delay_max', '5',

                # Simple copy mode - preserve original quality
                '-c', 'copy',

                # Output format
                '-f', 'flv',   # FLV format for RTMP

                # Error handling
                '-avoid_negative_ts', 'make_zero',
                '-fflags', '+genpts',

                # Logging
                '-loglevel', 'info',
                '-stats',

                config.output_url
            ])
            
            logging.info(f"🎬 Starting FFmpeg for stream {stream_id}")
            logging.debug(f"Command: {' '.join(cmd)}")
            
            # Start process
            process = subprocess.Popen(
                cmd,
                stdout=subprocess.PIPE,
                stderr=subprocess.PIPE,
                stdin=subprocess.PIPE
            )
            
            stream['process'] = process
            stream['status'] = StreamStatus.RUNNING
            
            logging.info(f"✅ FFmpeg started for stream {stream_id} (PID: {process.pid})")
            return True
            
        except Exception as e:
            logging.error(f"❌ Failed to start FFmpeg for stream {stream_id}: {e}")
            return False
    
    def _is_process_healthy(self, stream_id: int) -> bool:
        """Check if FFmpeg process is healthy"""
        try:
            stream = self.streams[stream_id]
            process = stream.get('process')
            
            if not process:
                return False
            
            # Check if process is still running
            if process.poll() is not None:
                return False
            
            # Check process stats
            try:
                proc = psutil.Process(process.pid)
                
                # Check if process is zombie
                if proc.status() == psutil.STATUS_ZOMBIE:
                    return False
                
                # Check memory usage (basic sanity check)
                memory_mb = proc.memory_info().rss / 1024 / 1024
                if memory_mb > 1000:  # More than 1GB is suspicious
                    logging.warning(f"⚠️ Stream {stream_id} using {memory_mb:.1f}MB memory")
                
            except psutil.NoSuchProcess:
                return False
            
            return True
            
        except Exception as e:
            logging.error(f"❌ Health check error for stream {stream_id}: {e}")
            return False
    
    def _cleanup_process(self, stream_id: int):
        """Clean up dead/zombie process"""
        try:
            stream = self.streams[stream_id]
            process = stream.get('process')
            
            if process:
                try:
                    if process.poll() is None:
                        process.terminate()
                        time.sleep(1)
                        if process.poll() is None:
                            process.kill()
                except:
                    pass
                
                stream['process'] = None
                
        except Exception as e:
            logging.error(f"❌ Cleanup error for stream {stream_id}: {e}")
    
    def shutdown(self):
        """Shutdown stream manager"""
        logging.info("🛑 Shutting down Simple Stream Manager...")
        
        self.running = False
        
        # Stop all streams
        stream_ids = list(self.streams.keys())
        for stream_id in stream_ids:
            self.stop_stream(stream_id)
        
        # Wait for monitoring threads
        for thread in self.monitoring_threads.values():
            if thread.is_alive():
                thread.join(timeout=5)
        
        logging.info("✅ Simple Stream Manager shutdown complete")

    def _report_stream_health(self, stream_id: int):
        """Report stream health to Laravel via status reporter"""
        try:
            # Get status reporter
            from status_reporter import get_status_reporter
            status_reporter = get_status_reporter()

            if not status_reporter:
                return

            # Get stream health
            health = self._check_stream_health(stream_id)
            stream = self.streams.get(stream_id)

            if not stream:
                return

            # Determine status message
            if health == 'healthy':
                status = 'STREAMING'
                message = 'Stream is healthy and active'
            elif health == 'idle':
                status = 'STREAMING'
                message = 'Stream process running but idle'
            elif health == 'process_dead':
                status = 'ERROR'
                message = 'Stream process has died'
            elif health == 'no_process':
                status = 'ERROR'
                message = 'No stream process found'
            else:
                status = 'WARNING'
                message = f'Stream health: {health}'

            # Add performance metrics
            config = stream.get('config')
            if config and config.loop_enabled:
                message += ' (Loop enabled)'

            # Report to Laravel
            status_reporter.publish_stream_status(stream_id, status, message)

            # Log health check (debug level to avoid spam)
            logging.debug(f"🔍 Stream {stream_id} health: {health}")

        except Exception as e:
            logging.debug(f"Error reporting health for stream {stream_id}: {e}")

    def _report_stream_disconnect(self, stream_id: int, health_status: str):
        """Report stream disconnect to Laravel"""
        try:
            from status_reporter import get_status_reporter
            status_reporter = get_status_reporter()

            if not status_reporter:
                return

            # Determine disconnect reason
            if health_status == 'process_dead':
                message = 'Stream process has died unexpectedly'
            elif health_status == 'no_process':
                message = 'Stream process not found'
            else:
                message = f'Stream disconnected: {health_status}'

            # Get stream info
            stream = self.streams.get(stream_id)
            if stream:
                config = stream.get('config')
                retry_count = stream.get('retry_count', 0)

                if config and config.loop_enabled:
                    message += f' (Loop stream, retry #{retry_count})'
                else:
                    message += f' (Retry #{retry_count})'

            # Report disconnect (use ERROR status as Laravel handles it)
            status_reporter.publish_stream_status(stream_id, 'ERROR', message)
            logging.info(f"📡 Reported disconnect for stream {stream_id}: {message}")

        except Exception as e:
            logging.error(f"❌ Error reporting disconnect for stream {stream_id}: {e}")

# Global instance
_stream_manager = None

def get_simple_stream_manager() -> SimpleStreamManager:
    """Get global stream manager instance"""
    global _stream_manager
    if _stream_manager is None:
        _stream_manager = SimpleStreamManager()
    return _stream_manager

def init_simple_stream_manager() -> SimpleStreamManager:
    """Initialize stream manager"""
    return get_simple_stream_manager()
