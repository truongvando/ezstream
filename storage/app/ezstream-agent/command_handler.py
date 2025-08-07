#!/usr/bin/env python3
"""
EZStream Agent Command Handler v7.0
Simple FFmpeg Direct Streaming command processing
"""

import json
import time
import logging
import threading
from typing import Dict, Any, Optional, Callable
from concurrent.futures import ThreadPoolExecutor, Future
from dataclasses import dataclass
from enum import Enum

import redis

from config import get_config
from utils import safe_json_loads, PerformanceTimer
from status_reporter import get_status_reporter
# Simple streaming - no SRS dependencies


class CommandStatus(Enum):
    """Command execution status"""
    PENDING = "PENDING"
    PROCESSING = "PROCESSING"
    SUCCESS = "SUCCESS"
    FAILED = "FAILED"


@dataclass
class CommandExecution:
    """Command execution tracking"""
    command_key: str
    command: str
    stream_id: Optional[int]
    start_time: float
    status: CommandStatus = CommandStatus.PENDING
    result: Optional[bool] = None
    error_message: Optional[str] = None


class CommandHandler:
    """Simple FFmpeg Direct Streaming command handler"""
    
    def __init__(self):
        self.config = get_config()
        self.status_reporter = get_status_reporter()

        # Redis connection for command listening
        self.redis_conn = None
        self.pubsub = None
        self.running = False
        
        # Command processing
        self.command_executor = ThreadPoolExecutor(max_workers=5, thread_name_prefix="CommandWorker")
        self.active_commands: Dict[str, CommandExecution] = {}
        self.command_lock = threading.RLock()
        
        # Command handlers
        self.command_handlers: Dict[str, Callable] = {
            'START_STREAM': self._handle_start_stream,
            'STOP_STREAM': self._handle_stop_stream,
            'UPDATE_STREAM': self._handle_update_stream,
            'SYNC_STATE': self._handle_sync_state,
            'UPDATE_SETTINGS': self._handle_update_settings,
            'RESTART_AGENT': self._handle_restart_agent,
            'UPDATE_AGENT': self._handle_update_agent
        }
        
        logging.info("🎛️ Simple FFmpeg Command Handler initialized")

    def start(self):
        """Start command processing"""
        try:
            # Connect to Redis
            redis_config = self.config.get_redis_config()
            self.redis_conn = redis.Redis(**redis_config)
            
            # Test connection
            self.redis_conn.ping()
            logging.info("✅ Connected to Redis for command processing")
            
            # Subscribe to VPS-specific command channel
            from config import get_config
            config = get_config()
            command_channel = f'vps-commands:{config.vps_id}'

            self.pubsub = self.redis_conn.pubsub()
            self.pubsub.subscribe(command_channel)

            logging.info(f"📡 Subscribed to command channel: {command_channel}")
            
            self.running = True
            logging.info("🎛️ Command Handler started - listening for commands")
            
            # Start command processing loop
            self._process_commands()
            
        except Exception as e:
            logging.error(f"❌ Failed to start Command Handler: {e}")
            raise

    def stop(self):
        """Stop command processing"""
        logging.info("🛑 Stopping Command Handler...")
        
        self.running = False
        
        # Close Redis connections
        if self.pubsub:
            self.pubsub.close()
        if self.redis_conn:
            self.redis_conn.close()
        
        # Shutdown executor
        self.command_executor.shutdown(wait=True)
        
        logging.info("✅ Command Handler stopped")

    def _process_commands(self):
        """Main command processing loop"""
        while self.running:
            try:
                # Get message with timeout
                message = self.pubsub.get_message(timeout=1.0)
                if message:
                    logging.info(f"[DEBUG] Received message: {message}")
                    if message['type'] == 'message':
                        self._handle_command_message(message['data'])

            except Exception as e:
                if self.running:
                    logging.error(f"❌ Error in command processing loop: {e}")
                    time.sleep(1)

    def _handle_command_message(self, message_data):
        """Handle incoming command message"""
        try:
            # Parse command data
            if isinstance(message_data, bytes):
                message_data = message_data.decode('utf-8')

            logging.info(f"📨 [COMMAND] Raw message received: {message_data}")

            command_data = safe_json_loads(message_data)
            if not command_data:
                logging.error("❌ [COMMAND] Invalid command data received")
                return

            logging.info(f"📋 [COMMAND] Parsed command data: {command_data}")

            # Extract command info
            command = command_data.get('command')
            config = command_data.get('config', {})

            # Get stream_id
            stream_id = config.get('id') or command_data.get('stream_id')

            logging.info(f"🎯 [COMMAND] Extracted command: {command}, stream_id: {stream_id}")
            logging.info(f"📋 [COMMAND] Config data: {config}")

            if command not in self.command_handlers:
                logging.warning(f"⚠️ [COMMAND] Unknown command: {command}")
                logging.info(f"📋 [COMMAND] Available commands: {list(self.command_handlers.keys())}")
                return

            # Create command execution tracking
            command_key = f"{command}_{stream_id}_{int(time.time())}"
            execution = CommandExecution(
                command_key=command_key,
                command=command,
                stream_id=stream_id,
                start_time=time.time()
            )

            with self.command_lock:
                self.active_commands[command_key] = execution

            logging.info(f"📝 [COMMAND] Created execution tracking: {command_key}")

            # Submit command for processing
            future = self.command_executor.submit(
                self._execute_command, execution, config, command_data
            )

            logging.info(f"📥 [COMMAND] Received {command} for stream {stream_id} - submitted for processing")

        except Exception as e:
            logging.error(f"❌ [COMMAND] Error handling command message: {e}")
            import traceback
            logging.error(f"❌ [COMMAND] Traceback: {traceback.format_exc()}")

    def _execute_command(self, execution: CommandExecution, config: Dict[str, Any], command_data: Dict[str, Any]):
        """Execute command with error handling"""
        try:
            execution.status = CommandStatus.PROCESSING
            
            # Get command handler
            handler = self.command_handlers[execution.command]
            
            # Execute command
            with PerformanceTimer(f"Command {execution.command}"):
                result = handler(execution.stream_id, config, command_data)
            
            execution.result = result
            execution.status = CommandStatus.SUCCESS if result else CommandStatus.FAILED
            
            if result:
                logging.info(f"✅ [COMMAND] {execution.command} completed successfully")
            else:
                logging.error(f"❌ [COMMAND] {execution.command} failed")
                
        except Exception as e:
            execution.status = CommandStatus.FAILED
            execution.error_message = str(e)
            logging.error(f"❌ [COMMAND] {execution.command} error: {e}")
        
        finally:
            # Cleanup command tracking
            with self.command_lock:
                self.active_commands.pop(execution.command_key, None)

    def _handle_start_stream(self, stream_id: int, config: Dict[str, Any], command_data: Dict[str, Any]) -> bool:
        """Handle START_STREAM command - Simple FFmpeg Direct"""
        try:
            logging.info(f"🚀 [COMMAND] Starting simple stream {stream_id}")

            # Use simple stream manager (FFmpeg direct)
            success = self._start_stream_simple(stream_id, config)

            if success:
                logging.info(f"✅ [COMMAND] Simple stream {stream_id} started successfully")
                return True
            else:
                logging.error(f"❌ [COMMAND] Simple streaming failed for stream {stream_id}")
                return False

        except Exception as e:
            logging.error(f"❌ Error in start_stream handler: {e}")
            return False

    def _start_stream_simple(self, stream_id: int, config: Dict[str, Any]) -> bool:
        """Start stream using simple FFmpeg direct"""
        try:
            # Get simple stream manager
            from simple_stream_manager import get_simple_stream_manager, StreamConfig
            stream_manager = get_simple_stream_manager()

            # Extract video files from config
            video_files = config.get('video_files', [])
            if not video_files:
                logging.error(f"❌ [SIMPLE] No video files provided for stream {stream_id}")
                return False

            # Extract all input URLs
            input_urls = []
            for video_file in video_files:
                if isinstance(video_file, dict):
                    url = video_file.get('download_url', '')
                    if url:
                        input_urls.append(url)
                else:
                    input_urls.append(str(video_file))

            if not input_urls:
                logging.error(f"❌ [SIMPLE] No input URLs found for stream {stream_id}")
                return False

            # Extract RTMP endpoint
            output_url = None
            if config.get('rtmp_url'):
                output_url = config['rtmp_url']
            elif config.get('stream_key'):
                output_url = f"rtmp://a.rtmp.youtube.com/live2/{config['stream_key']}"

            if not output_url:
                logging.error(f"❌ [SIMPLE] No RTMP endpoint found for stream {stream_id}")
                return False

            logging.info(f"🎬 [SIMPLE] Starting stream {stream_id}")
            logging.info(f"   - Input URLs: {input_urls}")
            logging.info(f"   - Output: {output_url}")

            # Create stream config
            stream_config = StreamConfig(
                stream_id=stream_id,
                input_urls=input_urls,
                output_url=output_url,
                loop_enabled=config.get('loop', True),
                playback_mode=config.get('playback_mode', 'sequential'),
                max_retries=5,
                restart_delay=10,
                health_check_interval=30
            )

            # Start stream
            success = stream_manager.start_stream(stream_config)

            if success:
                logging.info(f"✅ [SIMPLE] Stream {stream_id} started successfully")

                # Report status to Laravel
                if self.status_reporter:
                    logging.info(f"📤 [SIMPLE] Sending STREAMING status to Laravel for stream {stream_id}")
                    self.status_reporter.publish_stream_status(
                        stream_id, 'STREAMING', 'Simple FFmpeg stream started successfully'
                    )
                else:
                    logging.warning(f"⚠️ [SIMPLE] Status reporter not available for stream {stream_id}")

                return True
            else:
                logging.error(f"❌ [SIMPLE] Failed to start stream {stream_id}")

                # Report error to Laravel
                if self.status_reporter:
                    self.status_reporter.publish_stream_status(
                        stream_id, 'ERROR', 'Failed to start simple FFmpeg stream'
                    )

                return False

        except Exception as e:
            logging.error(f"❌ [SIMPLE] Error starting simple stream {stream_id}: {e}")
            return False





    def _handle_stop_stream(self, stream_id: int, config: Dict[str, Any], command_data: Dict[str, Any]) -> bool:
        """Handle STOP_STREAM command - Simple FFmpeg Direct"""
        try:
            logging.info(f"🛑 [COMMAND] Stopping stream {stream_id}")

            # Use simple stream manager
            success = self._stop_stream_simple(stream_id)

            if success:
                logging.info(f"✅ [COMMAND] Simple stream {stream_id} stopped successfully")
                return True
            else:
                logging.error(f"❌ [COMMAND] Failed to stop simple stream {stream_id}")
                return False

        except Exception as e:
            logging.error(f"❌ Error in stop_stream handler: {e}")
            return False

    def _stop_stream_simple(self, stream_id: int) -> bool:
        """Stop stream using simple stream manager"""
        try:
            # Get simple stream manager
            from simple_stream_manager import get_simple_stream_manager
            stream_manager = get_simple_stream_manager()

            logging.info(f"🛑 [SIMPLE] Stopping stream {stream_id}")

            # Stop stream
            success = stream_manager.stop_stream(stream_id)

            if success:
                logging.info(f"✅ [SIMPLE] Stream {stream_id} stopped successfully")

                # Report status to Laravel
                if self.status_reporter:
                    self.status_reporter.publish_stream_status(
                        stream_id, 'STOPPED', 'Simple FFmpeg stream stopped successfully'
                    )

                return True
            else:
                logging.error(f"❌ [SIMPLE] Failed to stop stream {stream_id}")

                # Report error to Laravel
                if self.status_reporter:
                    self.status_reporter.publish_stream_status(
                        stream_id, 'ERROR', 'Failed to stop simple FFmpeg stream'
                    )

                return False

        except Exception as e:
            logging.error(f"❌ [SIMPLE] Error stopping simple stream {stream_id}: {e}")
            return False





    def _handle_update_stream(self, stream_id: int, config: Dict[str, Any], command_data: Dict[str, Any]) -> bool:
        """Handle UPDATE_STREAM command - simplified for SRS"""
        try:
            logging.info(f"🔄 [COMMAND] Updating stream {stream_id}")

            # For SRS, update means restart with new config
            # Stop current stream
            self._handle_stop_stream(stream_id, config, command_data)
            
            # Small delay
            time.sleep(1)
            
            # Start with new config
            return self._handle_start_stream(stream_id, config, command_data)

        except Exception as e:
            logging.error(f"❌ Error in update_stream handler: {e}")
            return False

    def _handle_sync_state(self, stream_id: Optional[int], config: Dict[str, Any], command_data: Dict[str, Any]) -> bool:
        """Handle SYNC_STATE command"""
        try:
            logging.info(f"🔄 [SYNC] Received SYNC_STATE command from Laravel")

            # Get simple stream manager
            from simple_stream_manager import get_simple_stream_manager
            stream_manager = get_simple_stream_manager()

            # Get active streams
            active_streams = stream_manager.get_all_streams_status()

            # Report current state to Laravel
            if self.status_reporter:
                for stream_status in active_streams:
                    stream_id = stream_status['stream_id']
                    status = 'STREAMING' if stream_status['status'] == 'running' else 'STOPPED'
                    self.status_reporter.publish_stream_status(
                        stream_id, status, 'Stream sync update'
                    )

            logging.info(f"✅ [SYNC] Synced {len(active_streams)} active streams")
            return True

        except Exception as e:
            logging.error(f"❌ Error in sync_state handler: {e}")
            return False

    def _handle_update_settings(self, stream_id: Optional[int], config: Dict[str, Any], command_data: Dict[str, Any]) -> bool:
        """Handle UPDATE_SETTINGS command"""
        try:
            logging.info(f"🔧 [SETTINGS] Updating agent settings")

            # Update config from Laravel
            updated_settings = self.config.update_from_laravel_settings(config)
            
            if updated_settings:
                logging.info(f"✅ [SETTINGS] Updated: {', '.join(updated_settings)}")
            else:
                logging.info("ℹ️ [SETTINGS] No settings changed")

            return True

        except Exception as e:
            logging.error(f"❌ Error updating settings: {e}")
            return False

    def _handle_restart_agent(self, stream_id: Optional[int], config: Dict[str, Any], command_data: Dict[str, Any]) -> bool:
        """Handle RESTART_AGENT command"""
        try:
            logging.info("🔄 [RESTART] Agent restart requested")
            
            # This will be handled by the main agent process
            # Just acknowledge the command
            return True

        except Exception as e:
            logging.error(f"❌ Error in restart handler: {e}")
            return False

    def _handle_update_agent(self, stream_id: Optional[int], config: Dict[str, Any], command_data: Dict[str, Any]) -> bool:
        """Handle UPDATE_AGENT command"""
        try:
            logging.info("📦 [UPDATE] Agent update requested")
            
            # This will be handled by the main agent process
            # Just acknowledge the command
            return True

        except Exception as e:
            logging.error(f"❌ Error in update handler: {e}")
            return False


# Global instance management
_command_handler: Optional['CommandHandler'] = None


def init_command_handler() -> 'CommandHandler':
    """Initialize global command handler"""
    global _command_handler
    _command_handler = CommandHandler()
    return _command_handler


def get_command_handler() -> 'CommandHandler':
    """Get global command handler instance"""
    if _command_handler is None:
        raise RuntimeError("Command handler not initialized. Call init_command_handler() first.")
    return _command_handler
