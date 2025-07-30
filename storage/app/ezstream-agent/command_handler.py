#!/usr/bin/env python3
"""
EZStream Agent Command Handler
Handles concurrent command processing with proper queue management
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
from enhanced_stream_manager import get_enhanced_stream_manager



class CommandType(Enum):
    """Supported command types"""
    START_STREAM = "START_STREAM"
    STOP_STREAM = "STOP_STREAM"
    UPDATE_STREAM = "UPDATE_STREAM"
    SYNC_STATE = "SYNC_STATE"
    FORCE_KILL_STREAM = "FORCE_KILL_STREAM"
    CLEANUP_FILES = "CLEANUP_FILES"
    UPDATE_AGENT = "UPDATE_AGENT"
    REFRESH_SETTINGS = "REFRESH_SETTINGS"


@dataclass
class CommandResult:
    """Result of command execution"""
    success: bool
    message: str
    duration: float
    error: Optional[str] = None


class CommandHandler:
    """Handles concurrent command processing from Laravel"""
    
    def __init__(self):
        self.config = get_config()
        self.status_reporter = get_status_reporter()
        self.stream_manager = get_enhanced_stream_manager()

        # Redis connection for command listening
        self.redis_conn = None
        self.pubsub = None
        self.running = False
        
        # Command processing
        self.command_executor = ThreadPoolExecutor(
            max_workers=self.config.command_thread_pool_size,
            thread_name_prefix="CommandProcessor"
        )
        
        # Command handlers mapping
        self.command_handlers: Dict[CommandType, Callable] = {
            CommandType.START_STREAM: self._handle_start_stream,
            CommandType.STOP_STREAM: self._handle_stop_stream,
            CommandType.UPDATE_STREAM: self._handle_update_stream,
            CommandType.SYNC_STATE: self._handle_sync_state,
            CommandType.FORCE_KILL_STREAM: self._handle_force_kill_stream,
            CommandType.CLEANUP_FILES: self._handle_cleanup_files,
            CommandType.UPDATE_AGENT: self._handle_update_agent,
            CommandType.REFRESH_SETTINGS: self._handle_refresh_settings,
        }
        
        # Active command tracking
        self.active_commands: Dict[str, Future] = {}
        self.command_lock = threading.RLock()
        
        self._connect_redis()
        logging.info(f"⚡ Command handler initialized (max workers: {self.config.command_thread_pool_size})")
    
    def _connect_redis(self):
        """Connect to Redis for command listening"""
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
            logging.info(f"✅ Command handler connected to Redis")
            
        except Exception as e:
            logging.error(f"❌ Failed to connect to Redis for commands: {e}")
            raise
    
    def start(self):
        """Start command listening loop"""
        self.running = True
        
        # Start command listener thread
        listener_thread = threading.Thread(
            target=self._command_listener_loop,
            name="CommandListener",
            daemon=True
        )
        listener_thread.start()
        
        logging.info("⚡ Command handler started")
    
    def stop(self):
        """Stop command processing"""
        self.running = False

        # Close pubsub safely
        self._safe_pubsub_cleanup()
        logging.info("✅ Pubsub connection closed")

        # Close Redis connection safely
        try:
            if self.redis_conn:
                self.redis_conn.close()
                logging.info("✅ Redis connection closed")
        except Exception as e:
            logging.error(f"❌ Error closing Redis connection: {e}")

        # Shutdown executor (compatible with older Python)
        try:
            self.command_executor.shutdown(wait=True)
            logging.info("✅ Command executor shutdown")
        except Exception as e:
            logging.error(f"❌ Error shutting down executor: {e}")

        logging.info("⚡ Command handler stopped")



    def _command_listener_loop(self):
        """Main command listening loop"""
        command_channel = f"vps-commands:{self.config.vps_id}"
        
        try:
            # Create pubsub connection safely
            if not self.redis_conn:
                logging.error("❌ Redis connection not available for pubsub")
                return

            self.pubsub = self.redis_conn.pubsub(ignore_subscribe_messages=True)
            if not self.pubsub:
                logging.error("❌ Failed to create pubsub connection")
                return

            self.pubsub.subscribe(command_channel)

            logging.info(f"📡 Subscribed to command channel: {command_channel}")

            for message in self.pubsub.listen():
                if not self.running:
                    break

                # Check if pubsub is still valid
                if not self.pubsub:
                    logging.warning("⚠️ Pubsub connection lost during listen")
                    break

                try:
                    self._process_message(message)
                except Exception as e:
                    if self.running:  # Only log if we're still supposed to be running
                        logging.error(f"❌ Error processing command message: {e}")
                        logging.error(f"Raw message: {message}")
                    break  # Exit loop on error

        except Exception as e:
            if self.running:  # Only log if we're still supposed to be running
                logging.error(f"❌ Error in command listener loop: {e}")
        finally:
            self._safe_pubsub_cleanup()

    def _safe_pubsub_cleanup(self):
        """Safely cleanup pubsub connection"""
        try:
            if self.pubsub:
                if hasattr(self.pubsub, 'close'):
                    self.pubsub.close()
                elif hasattr(self.pubsub, 'unsubscribe'):
                    self.pubsub.unsubscribe()
                self.pubsub = None
                logging.debug("🧹 Pubsub connection cleaned up safely")
        except Exception as e:
            logging.debug(f"⚠️ Error during pubsub cleanup: {e}")
            self.pubsub = None

    def _process_message(self, message: Dict[str, Any]):
        """Process incoming command message"""
        try:
            logging.debug(f"🔍 [Agent] Raw message received: {message}")
            
            # Parse command data
            command_data = safe_json_loads(message['data'])
            if not command_data:
                logging.warning("Invalid JSON in command message")
                return
            
            logging.info(f"🔍 [Agent] Parsed command_data: {json.dumps(command_data, indent=2)}")
            
            # Extract command info
            command = command_data.get('command')
            config = command_data.get('config', {})

            # Get stream_id with proper fallback logic
            stream_id = config.get('id') or command_data.get('stream_id')

            # For UPDATE_STREAM, ensure we have stream_id
            if command == 'UPDATE_STREAM' and not stream_id:
                logging.error("UPDATE_STREAM command missing stream_id")
                return
            
            if not command:
                logging.warning("No command specified in message")
                return
            
            # Validate command type
            try:
                command_type = CommandType(command)
            except ValueError:
                logging.warning(f"Unknown command received: {command}")
                return
            
            # Submit command for concurrent processing
            command_key = f"{command}_{stream_id}_{int(time.time())}"
            
            future = self.command_executor.submit(
                self._execute_command,
                command_type,
                stream_id,
                config,
                command_data
            )
            
            # Track active command
            with self.command_lock:
                self.active_commands[command_key] = future
            
            # Add completion callback
            future.add_done_callback(
                lambda f: self._command_completed(command_key, f)
            )
            
            logging.info(f"⚡ Submitted command {command} for stream {stream_id} (concurrent processing)")
            
        except Exception as e:
            logging.error(f"❌ Error processing message: {e}")
    
    def _execute_command(self, command_type: CommandType, stream_id: Optional[int], 
                        config: Dict[str, Any], command_data: Dict[str, Any]) -> CommandResult:
        """Execute command with timing and error handling"""
        start_time = time.time()
        
        try:
            with PerformanceTimer(f"Command {command_type.value}"):
                # Get command handler
                handler = self.command_handlers.get(command_type)
                if not handler:
                    raise Exception(f"No handler for command: {command_type.value}")
                
                # Execute command
                success = handler(stream_id, config, command_data)
                
                duration = time.time() - start_time
                
                if success:
                    return CommandResult(
                        success=True,
                        message=f"Command {command_type.value} completed successfully",
                        duration=duration
                    )
                else:
                    return CommandResult(
                        success=False,
                        message=f"Command {command_type.value} failed",
                        duration=duration,
                        error="Handler returned False"
                    )
                    
        except Exception as e:
            duration = time.time() - start_time
            logging.error(f"❌ Command {command_type.value} failed: {e}")
            
            return CommandResult(
                success=False,
                message=f"Command {command_type.value} failed with exception",
                duration=duration,
                error=str(e)
            )
    
    def _command_completed(self, command_key: str, future: Future):
        """Callback when command completes"""
        try:
            result = future.result()
            
            if result.success:
                logging.info(f"✅ Command completed: {command_key} ({result.duration:.2f}s)")
            else:
                logging.error(f"❌ Command failed: {command_key} - {result.error}")
            
        except Exception as e:
            logging.error(f"❌ Command exception: {command_key} - {e}")
        finally:
            # Remove from active commands
            with self.command_lock:
                self.active_commands.pop(command_key, None)
    
    def _handle_start_stream(self, stream_id: int, config: Dict[str, Any], command_data: Dict[str, Any]) -> bool:
        """Handle START_STREAM command - simplified"""
        try:
            logging.info(f"🚀 [COMMAND] Starting stream {stream_id}")

            if not self.stream_manager:
                logging.error("Stream manager not available")
                return False

            # Start the stream directly - Laravel already decided this is valid
            result = self.stream_manager.start_stream(config)

            if result:
                logging.info(f"✅ Stream {stream_id} started successfully")
            else:
                logging.error(f"❌ Failed to start stream {stream_id}")

            return result

        except Exception as e:
            logging.error(f"❌ Error in start_stream handler: {e}")
            return False
    
    def _handle_stop_stream(self, stream_id: int, config: Dict[str, Any], command_data: Dict[str, Any]) -> bool:
        """Handle STOP_STREAM command - simplified"""
        try:
            logging.info(f"🛑 [COMMAND] Stopping stream {stream_id}")

            if not self.stream_manager:
                logging.error("Stream manager not available")
                return False

            # Stop the stream directly - Laravel already decided this is valid
            result = self.stream_manager.stop_stream(stream_id, "command")

            if result:
                logging.info(f"✅ Stream {stream_id} stopped successfully")
            else:
                logging.error(f"❌ Failed to stop stream {stream_id}")

            return result

        except Exception as e:
            logging.error(f"❌ Error in stop_stream handler: {e}")
            return False
    
    def _handle_update_stream(self, stream_id: int, config: Dict[str, Any], command_data: Dict[str, Any]) -> bool:
        """Handle UPDATE_STREAM command with HLS settings support"""
        try:
            logging.info(f"🔄 [COMMAND] Updating stream {stream_id}")

            if not self.stream_manager:
                logging.error("Stream manager not available")
                return False

            # Validate config structure
            if not self._validate_update_config(config, stream_id):
                return False

            # Extract HLS-specific settings from config
            hls_settings = {}
            original_config = {}  # For rollback

            # Check for encoding mode change
            if 'ffmpeg_use_encoding' in config:
                original_config['ffmpeg_use_encoding'] = self.config.ffmpeg_use_encoding
                hls_settings['ffmpeg_use_encoding'] = config['ffmpeg_use_encoding']
                logging.info(f"🔧 [UPDATE] Encoding mode change: {config['ffmpeg_use_encoding']}")

            # Check for HLS-specific settings
            hls_keys = [
                'hls_segment_duration', 'hls_playlist_size', 'hls_video_preset',
                'hls_video_crf', 'hls_video_maxrate', 'hls_audio_bitrate'
            ]

            for key in hls_keys:
                if key in config:
                    original_config[key] = getattr(self.config, key, None)
                    hls_settings[key] = config[key]
                    logging.info(f"🔧 [UPDATE] HLS setting change: {key} = {config[key]}")

            # Special handling for video_files
            if 'video_files' in config:
                video_files = config['video_files']
                if not isinstance(video_files, list) or not video_files:
                    logging.error(f"Invalid video_files in UPDATE_STREAM for stream {stream_id}")
                    return False
                logging.info(f"🎬 [UPDATE] Video files change: {len(video_files)} files")

            # If HLS settings changed, update global config first
            if hls_settings:
                self._update_global_hls_config(hls_settings)

            # Call enhanced stream manager's update method
            success = self.stream_manager.update_stream(stream_id, config)

            if not success and hls_settings:
                # Rollback global config on failure
                logging.warning(f"🔄 Rolling back global config changes for stream {stream_id}")
                self._update_global_hls_config(original_config)

            return success

        except Exception as e:
            logging.error(f"❌ Error in update_stream handler: {e}")
            return False

    def _validate_update_config(self, config: Dict[str, Any], stream_id: int) -> bool:
        """Validate UPDATE_STREAM config structure"""
        try:
            # Check for required fields if video_files is being updated
            if 'video_files' in config:
                video_files = config['video_files']

                if not isinstance(video_files, list):
                    logging.error(f"video_files must be a list for stream {stream_id}")
                    return False

                if not video_files:
                    logging.error(f"video_files cannot be empty for stream {stream_id}")
                    return False

                # Validate each video file
                for i, video_file in enumerate(video_files):
                    if not isinstance(video_file, dict):
                        logging.error(f"video_files[{i}] must be a dict for stream {stream_id}")
                        return False

                    if 'url' not in video_file and 'path' not in video_file:
                        logging.error(f"video_files[{i}] must have 'url' or 'path' for stream {stream_id}")
                        return False

            # Validate HLS settings ranges
            if 'hls_segment_duration' in config:
                duration = config['hls_segment_duration']
                if not isinstance(duration, int) or duration < 1 or duration > 30:
                    logging.error(f"Invalid hls_segment_duration: {duration} (must be 1-30)")
                    return False

            if 'hls_playlist_size' in config:
                size = config['hls_playlist_size']
                if not isinstance(size, int) or size < 3 or size > 50:
                    logging.error(f"Invalid hls_playlist_size: {size} (must be 3-50)")
                    return False

            if 'hls_video_crf' in config:
                crf = config['hls_video_crf']
                if not isinstance(crf, int) or crf < 15 or crf > 35:
                    logging.error(f"Invalid hls_video_crf: {crf} (must be 15-35)")
                    return False

            return True

        except Exception as e:
            logging.error(f"❌ Error validating update config for stream {stream_id}: {e}")
            return False

    def _update_global_hls_config(self, hls_settings: Dict[str, Any]):
        """Update global HLS configuration with validation"""
        try:
            from config import get_config
            config_instance = get_config()

            updated_settings = []

            for key, value in hls_settings.items():
                if hasattr(config_instance, key):
                    old_value = getattr(config_instance, key)
                    setattr(config_instance, key, value)
                    updated_settings.append(f"{key}: {old_value} → {value}")
                    logging.info(f"🔧 [CONFIG] Updated {key}: {old_value} → {value}")
                else:
                    logging.warning(f"⚠️ [CONFIG] Unknown setting: {key}")

            if updated_settings:
                logging.info(f"✅ [CONFIG] Global HLS config updated: {', '.join(updated_settings)}")

        except Exception as e:
            logging.error(f"❌ Error updating global HLS config: {e}")
            raise  # Re-raise to handle in caller
    
    def _handle_sync_state(self, stream_id: Optional[int], config: Dict[str, Any], command_data: Dict[str, Any]) -> bool:
        """Handle SYNC_STATE command"""
        try:
            logging.info(f"🔄 [SYNC] Received SYNC_STATE command from Laravel")
            
            # Send heartbeat immediately as response
            if self.status_reporter and self.stream_manager:
                active_stream_ids = self.stream_manager.get_active_stream_ids()
                
                heartbeat_payload = {
                    'type': 'HEARTBEAT',
                    'vps_id': self.config.vps_id,
                    'active_streams': active_stream_ids,
                    'timestamp': int(time.time()),
                }
                
                self.status_reporter._publish_report(heartbeat_payload)
                logging.info(f"✅ [SYNC] Sent heartbeat with {len(active_stream_ids)} active streams")
            
            return True
            
        except Exception as e:
            logging.error(f"❌ Error in sync_state handler: {e}")
            return False
    
    def _handle_force_kill_stream(self, stream_id: int, config: Dict[str, Any], command_data: Dict[str, Any]) -> bool:
        """Handle FORCE_KILL_STREAM command"""
        try:
            logging.info(f"💀 [COMMAND] Force killing stream {stream_id}")
            
            if not self.stream_manager:
                logging.error("Stream manager not available")
                return False
            
            # Force stop without graceful shutdown
            return self.stream_manager.stop_stream(stream_id, "force_kill")
            
        except Exception as e:
            logging.error(f"❌ Error in force_kill_stream handler: {e}")
            return False
    
    def _handle_cleanup_files(self, stream_id: int, config: Dict[str, Any], command_data: Dict[str, Any]) -> bool:
        """Handle CLEANUP_FILES command"""
        try:
            force_cleanup = command_data.get('force', False)
            logging.info(f"🧹 [COMMAND] Cleaning up files for stream {stream_id} (force: {force_cleanup})")
            
            from file_manager import get_file_manager
            file_manager = get_file_manager()
            
            if not file_manager:
                logging.error("File manager not available")
                return False
            
            file_manager.cleanup_stream_files(stream_id, force=force_cleanup)
            return True
            
        except Exception as e:
            logging.error(f"❌ Error in cleanup_files handler: {e}")
            return False
    


    def _handle_update_agent(self, stream_id: Optional[int], config: Dict[str, Any], command_data: Dict[str, Any]) -> bool:
        """Handle UPDATE_AGENT command"""
        try:
            logging.info("🔄 [UPDATE] Received UPDATE_AGENT command from Laravel")

            # Get version from command data
            version = command_data.get('version', 'latest')
            logging.info(f"🔄 [UPDATE] Updating agent to version: {version}")

            # Import here to avoid circular imports
            import subprocess
            import os
            import base64
            import zipfile
            import tempfile
            import shutil

            # Connect to Redis to download agent package
            redis_client = redis.Redis(
                host=self.config.redis_host,
                port=self.config.redis_port,
                password=self.config.redis_password,
                decode_responses=False
            )

            # Get agent package from Redis
            package_key = f"agent_package:{version}"
            package_data = redis_client.get(package_key)

            if not package_data:
                logging.error(f"❌ [UPDATE] Agent package not found in Redis: {package_key}")
                return False

            # Decode base64 data
            if isinstance(package_data, bytes):
                package_data = package_data.decode('utf-8')

            zip_data = base64.b64decode(package_data)
            logging.info(f"✅ [UPDATE] Downloaded agent package ({len(zip_data)} bytes)")

            # Create temporary directory for extraction
            with tempfile.TemporaryDirectory() as temp_dir:
                # Write ZIP file
                zip_path = os.path.join(temp_dir, 'agent_update.zip')
                with open(zip_path, 'wb') as f:
                    f.write(zip_data)

                # Extract ZIP file
                extract_dir = os.path.join(temp_dir, 'extracted')
                os.makedirs(extract_dir, exist_ok=True)

                with zipfile.ZipFile(zip_path, 'r') as zip_ref:
                    zip_ref.extractall(extract_dir)

                logging.info("✅ [UPDATE] Agent package extracted")

                # Get current agent directory
                current_dir = os.path.dirname(os.path.abspath(__file__))

                # Backup current agent
                backup_dir = f"{current_dir}_backup_{int(time.time())}"
                shutil.copytree(current_dir, backup_dir)
                logging.info(f"✅ [UPDATE] Current agent backed up to: {backup_dir}")

                # Copy new files (excluding current running script)
                current_script = os.path.abspath(__file__)

                for item in os.listdir(extract_dir):
                    src_path = os.path.join(extract_dir, item)
                    dst_path = os.path.join(current_dir, item)

                    # Skip if it's the currently running script
                    if os.path.abspath(dst_path) == current_script:
                        logging.info(f"⏭️ [UPDATE] Skipping currently running script: {item}")
                        continue

                    if os.path.isfile(src_path):
                        shutil.copy2(src_path, dst_path)
                        logging.info(f"✅ [UPDATE] Updated file: {item}")

                # Set permissions
                agent_py = os.path.join(current_dir, 'agent.py')
                if os.path.exists(agent_py):
                    os.chmod(agent_py, 0o755)

                logging.info("✅ [UPDATE] Agent files updated successfully")

                # Schedule restart (agent.py will handle this)
                logging.info("🔄 [UPDATE] Scheduling agent restart...")

                # Create restart flag file
                restart_flag = os.path.join(current_dir, '.restart_required')
                with open(restart_flag, 'w') as f:
                    f.write(f"restart_requested_at={time.time()}\n")
                    f.write(f"updated_version={version}\n")

                logging.info("✅ [UPDATE] Agent update completed, restart scheduled")
                return True

        except Exception as e:
            logging.error(f"❌ [UPDATE] Error in update_agent handler: {e}")
            import traceback
            logging.error(f"❌ [UPDATE] Traceback: {traceback.format_exc()}")
            return False

    def _handle_refresh_settings(self, stream_id: Optional[int], config: Dict[str, Any], command_data: Dict[str, Any]) -> bool:
        """Handle REFRESH_SETTINGS command"""
        try:
            logging.info("🔧 [COMMAND] Refreshing settings from Laravel...")

            # Get config instance and refresh settings
            from config import get_config
            config_instance = get_config()

            if not config_instance:
                logging.error("❌ Config instance not available")
                return False

            # Fetch new settings from Laravel
            updated_settings = config_instance.fetch_laravel_settings()
            if not updated_settings:
                logging.warning("⚠️ Failed to refresh settings or no changes detected")
                return False

            logging.info("✅ Settings refreshed successfully")

            # Apply settings to running streams if needed
            if self.stream_manager:
                active_streams = self.stream_manager.get_active_streams()
                if active_streams:
                    logging.info(f"🔄 Applying new settings to {len(active_streams)} active streams...")

                    # Check if critical settings changed that require restart
                    critical_settings = [
                        'ffmpeg_mode', 'hls_video_preset', 'hls_video_crf',
                        'hls_video_maxrate', 'hls_audio_bitrate'
                    ]

                    needs_restart = any(
                        any(setting in change for setting in critical_settings)
                        for change in updated_settings
                    )

                    if needs_restart:
                        logging.info("🔄 Critical settings changed - streams will restart on next update")
                        # Don't auto-restart streams, let Laravel decide
                        # Just log that restart is recommended
                        for stream_id in active_streams:
                            logging.info(f"💡 Stream {stream_id} should be restarted to apply new encoding settings")
                    else:
                        logging.info("✅ Non-critical settings updated - no stream restart needed")
                else:
                    logging.info("ℹ️ No active streams to apply settings to")

            return True

        except Exception as e:
            logging.error(f"❌ Error in refresh_settings handler: {e}")
            return False


# Global command handler instance
_command_handler: Optional[CommandHandler] = None


def init_command_handler() -> CommandHandler:
    """Initialize global command handler"""
    global _command_handler
    _command_handler = CommandHandler()
    return _command_handler


def get_command_handler() -> Optional[CommandHandler]:
    """Get global command handler instance"""
    return _command_handler
