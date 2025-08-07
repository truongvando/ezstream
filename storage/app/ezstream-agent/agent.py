#!/usr/bin/env python3
"""
EZStream Agent v7.0 - Simple FFmpeg Direct Streaming
Clean, simple FFmpeg-based streaming without SRS complexity
"""

import sys
import signal
import logging
import time
import os
from typing import Optional

# Import components
from config import init_config
from status_reporter import init_status_reporter
from file_manager import init_file_manager
# Legacy stream_manager removed - using simple_stream_manager
from command_handler import init_command_handler



# Logging setup - Linux focused
# Create logs directory if it doesn't exist
log_dir = os.path.join(os.path.dirname(__file__), 'logs')
os.makedirs(log_dir, exist_ok=True)

logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler(os.path.join(log_dir, 'ezstream-agent.log')),
        logging.StreamHandler()
    ]
)

logging.getLogger('urllib3').setLevel(logging.WARNING)
logging.getLogger('requests').setLevel(logging.WARNING)

class EZStreamAgent:
    """Main EZStream Agent with Direct Streaming"""

    def __init__(self, vps_id: int, redis_host: str, redis_port: int, redis_password: Optional[str] = None):
        self.running = False
        self.config = init_config(vps_id, redis_host, redis_port, redis_password)

        # Components
        self.status_reporter = None
        self.file_manager = None
        # Legacy stream_manager removed
        self.command_handler = None

        # Simple streaming components
        self.simple_stream_manager = None

        logging.info(f"EZStream Agent v7.0 (Simple FFmpeg Streaming) initialized for VPS {vps_id}")
    
    def start(self):
        """Start agent"""
        try:
            logging.info("Starting EZStream Agent v7.0 with Simple FFmpeg Streaming...")
            self.running = True

            # Config is already loaded in __init__, no need to fetch from Laravel

            # Initialize components in correct order
            self.status_reporter = init_status_reporter()
            self.file_manager = init_file_manager()

            # Initialize process manager BEFORE stream manager (required dependency)
            from process_manager import init_process_manager
            init_process_manager()
            logging.info("✅ Process manager initialized")

            # Legacy stream_manager removed - using simple_stream_manager only
            self.command_handler = init_command_handler()

            # SRS managers removed - using FFmpeg direct streaming only

            # Initialize simple streaming components
            try:
                from simple_stream_manager import init_simple_stream_manager
                self.simple_stream_manager = init_simple_stream_manager()
                logging.info("✅ Simple streaming components initialized successfully")
            except Exception as e:
                logging.error(f"❌ Failed to initialize simple streaming components: {e}")
                raise

            # Start services
            if self.status_reporter:
                self.status_reporter.start()

            if self.file_manager:
                self.file_manager.start_cleanup_service()

            if self.command_handler:
                self.command_handler.start()

            logging.info("EZStream Agent v7.0 (Simple FFmpeg Streaming) started successfully!")
            self._main_loop()
            
        except Exception as e:
            logging.error(f"Failed to start agent: {e}")
            self.stop()
            sys.exit(1)
    
    def stop(self):
        """Stop agent"""
        try:
            logging.info("Shutting down EZStream Agent v7.0...")
            self.running = False

            if self.command_handler:
                try:
                    self.command_handler.stop()
                    logging.info("Command handler stopped")
                except Exception as e:
                    logging.error(f"Error stopping command handler: {e}")

            # Legacy stream_manager removed

            # Stop simple stream manager
            if self.simple_stream_manager:
                try:
                    self.simple_stream_manager.shutdown()
                    logging.info("Simple stream manager stopped")
                except Exception as e:
                    logging.error(f"Error stopping simple stream manager: {e}")

            if self.file_manager:
                try:
                    self.file_manager.stop_cleanup_service()
                    logging.info("File manager stopped")
                except Exception as e:
                    logging.error(f"Error stopping file manager: {e}")

            if self.status_reporter:
                try:
                    self.status_reporter.stop()
                    logging.info("Status reporter stopped")
                except Exception as e:
                    logging.error(f"Error stopping status reporter: {e}")

            logging.info("EZStream Agent v7.0 shutdown complete")

        except Exception as e:
            logging.error(f"Error during shutdown: {e}")
    
    def _main_loop(self):
        """Main loop"""
        try:
            while self.running:
                time.sleep(1)
        except KeyboardInterrupt:
            logging.info("Received interrupt signal")
            self.stop()
        except Exception as e:
            logging.error(f"Error in main loop: {e}")
            self.stop()
    
    def _setup_signal_handlers(self):
        """Setup signal handlers"""
        def signal_handler(signum, _):
            logging.info(f"Received signal {signum} - initiating graceful shutdown")
            self.stop()

            # Give components time to cleanup
            time.sleep(2)

            logging.info("Graceful shutdown complete")
            sys.exit(0)

        signal.signal(signal.SIGINT, signal_handler)
        signal.signal(signal.SIGTERM, signal_handler)

def main():
    """Main entry point"""
    import argparse
    
    parser = argparse.ArgumentParser(description='EZStream Agent v7.0 - Simple FFmpeg Streaming')
    parser.add_argument('--vps-id', type=int, required=True, help='VPS ID')
    parser.add_argument('--redis-host', type=str, default='127.0.0.1', help='Redis host')
    parser.add_argument('--redis-port', type=int, default=6379, help='Redis port')
    parser.add_argument('--redis-password', type=str, help='Redis password')
    
    args = parser.parse_args()
    
    agent = EZStreamAgent(
        vps_id=args.vps_id,
        redis_host=args.redis_host,
        redis_port=args.redis_port,
        redis_password=args.redis_password
    )
    
    agent._setup_signal_handlers()
    agent.start()

if __name__ == '__main__':
    main()
