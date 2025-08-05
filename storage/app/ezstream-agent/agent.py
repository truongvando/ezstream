#!/usr/bin/env python3
"""
EZStream Agent v5.0 - Direct Streaming Architecture
Eliminated direct streaming, using direct FFmpeg RTMP streaming
"""

import sys
import signal
import logging
import time
from typing import Optional

# Import components
from config import init_config
from status_reporter import init_status_reporter
from file_manager import init_file_manager
from stream_manager import init_stream_manager
from command_handler import init_command_handler

# Logging setup - Linux focused
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler('/var/log/ezstream-agent.log'),
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
        self.stream_manager = None
        self.command_handler = None

        logging.info(f"EZStream Agent v5.0 (Direct Streaming) initialized for VPS {vps_id}")
    
    def start(self):
        """Start agent"""
        try:
            logging.info("Starting EZStream Agent v5.0 with Direct Streaming...")
            self.running = True

            logging.info("Fetching settings from Laravel...")
            self.config.fetch_laravel_settings()

            # Initialize components
            self.status_reporter = init_status_reporter()
            self.file_manager = init_file_manager()
            self.stream_manager = init_stream_manager()  # Stream management with playlist support
            self.command_handler = init_command_handler()

            # Initialize SRS managers (main streaming method)
            try:
                from srs_manager import init_srs_manager
                # stream_manager is already initialized above

                init_srs_manager()
                logging.info("✅ SRS managers initialized successfully")
            except Exception as e:
                logging.error(f"❌ Failed to initialize SRS managers: {e}")
                raise

            # Start services
            if self.status_reporter:
                self.status_reporter.start()

            if self.file_manager:
                self.file_manager.start_cleanup_service()

            if self.command_handler:
                self.command_handler.start()

            logging.info("EZStream Agent v5.0 (Direct Streaming) started successfully!")
            self._main_loop()
            
        except Exception as e:
            logging.error(f"Failed to start agent: {e}")
            self.stop()
            sys.exit(1)
    
    def stop(self):
        """Stop agent"""
        try:
            logging.info("Shutting down EZStream Agent v4.0...")
            self.running = False

            if self.command_handler:
                try:
                    self.command_handler.stop()
                    logging.info("Command handler stopped")
                except Exception as e:
                    logging.error(f"Error stopping command handler: {e}")

            if self.stream_manager:
                try:
                    self.stream_manager.stop_all()
                    logging.info("Stream manager stopped")
                except Exception as e:
                    logging.error(f"Error stopping stream manager: {e}")

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

            logging.info("EZStream Agent v4.0 shutdown complete")

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
    
    parser = argparse.ArgumentParser(description='EZStream Agent v5.0 - Direct Streaming')
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
