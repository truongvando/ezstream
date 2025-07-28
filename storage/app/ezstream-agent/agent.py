#!/usr/bin/env python3
"""
EZStream Agent v3.0 - Refactored Architecture
High-performance, concurrent stream management agent
"""

import os
import sys
import signal
import logging
import time
from typing import Optional

# Import our modular components
from config import init_config, get_config
from status_reporter import init_status_reporter, get_status_reporter
from process_manager import init_process_manager, get_process_manager
from file_manager import init_file_manager, get_file_manager
from stream_manager import init_stream_manager, get_stream_manager
from command_handler import init_command_handler, get_command_handler

from utils import PerformanceTimer

# --- LOGGING CONFIGURATION ---
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler('/var/log/ezstream-agent.log'),
        logging.StreamHandler()
    ]
)

# Reduce noise from external libraries
logging.getLogger('urllib3').setLevel(logging.WARNING)
logging.getLogger('requests').setLevel(logging.WARNING)


class EZStreamAgent:
    """Main EZStream Agent class with modular architecture"""
    
    def __init__(self, vps_id: int, redis_host: str, redis_port: int, redis_password: Optional[str] = None):
        self.running = False
        
        # Initialize configuration first
        self.config = init_config(vps_id, redis_host, redis_port, redis_password)
        
        # Component references
        self.status_reporter = None
        self.process_manager = None
        self.file_manager = None
        self.stream_manager = None
        self.command_handler = None
        
        logging.info(f"🚀 EZStream Agent v3.0 initialized for VPS {vps_id}")
    
    def start(self):
        """Start all agent components"""
        try:
            logging.info("🚀 Starting EZStream Agent v3.0...")
            self.running = True
            
            # Initialize components in dependency order
            self.status_reporter = init_status_reporter()
            self.process_manager = init_process_manager()
            self.file_manager = init_file_manager()
            self.stream_manager = init_stream_manager()
            self.command_handler = init_command_handler()
            
            # Start all services
            if self.status_reporter:
                self.status_reporter.start()
            
            if self.file_manager:
                self.file_manager.start_cleanup_service()
            
            if self.command_handler:
                self.command_handler.start()
            
            logging.info("✅ EZStream Agent v3.0 started successfully!")
            
            # Main loop
            self._main_loop()
            
        except Exception as e:
            logging.error(f"❌ Failed to start agent: {e}")
            self.stop()
            sys.exit(1)
    
    def stop(self):
        """Gracefully stop all agent components"""
        try:
            logging.info("🛑 Shutting down EZStream Agent...")
            self.running = False

            # Stop components in reverse order with proper error handling
            self._safe_stop_component("command_handler", self.command_handler)
            self._safe_stop_component("stream_manager", self.stream_manager, "stop_all_streams")
            self._safe_stop_component("process_manager", self.process_manager, "stop_all")
            self._safe_stop_component("file_manager", self.file_manager, "stop_cleanup_service")
            self._safe_stop_component("status_reporter", self.status_reporter)

            logging.info("✅ EZStream Agent shutdown complete")

        except Exception as e:
            logging.error(f"❌ Error during shutdown: {e}")

    def _safe_stop_component(self, name: str, component, method_name: str = "stop"):
        """Safely stop a component with error handling"""
        try:
            if component:
                if hasattr(component, method_name):
                    method = getattr(component, method_name)
                    method()
                    logging.info(f"✅ {name} stopped successfully")
                else:
                    logging.warning(f"⚠️ {name} has no {method_name} method")
        except Exception as e:
            logging.error(f"❌ Error stopping {name}: {e}")
            # Continue with shutdown even if one component fails
    
    def _main_loop(self):
        """Main agent loop"""
        try:
            while self.running:
                # Check for restart flag
                restart_flag = os.path.join(os.path.dirname(__file__), '.restart_required')
                if os.path.exists(restart_flag):
                    logging.info("🔄 [UPDATE] Restart flag detected, initiating restart...")

                    # Read restart info
                    try:
                        with open(restart_flag, 'r') as f:
                            restart_info = f.read()
                        logging.info(f"🔄 [UPDATE] Restart info: {restart_info}")
                    except:
                        pass

                    # Remove restart flag
                    try:
                        os.remove(restart_flag)
                    except:
                        pass

                    # Initiate restart
                    logging.info("🔄 [UPDATE] Restarting agent for update...")
                    self.stop()

                    # Restart using systemctl
                    try:
                        import subprocess
                        subprocess.run(['sudo', 'systemctl', 'restart', 'ezstream-agent'], check=False)
                    except Exception as e:
                        logging.error(f"❌ [UPDATE] Failed to restart via systemctl: {e}")

                    break

                time.sleep(1)

        except KeyboardInterrupt:
            logging.info("🔄 Received interrupt signal")
        except Exception as e:
            logging.error(f"❌ Error in main loop: {e}")
        finally:
            self.stop()


def main():
    """Main entry point"""
    if len(sys.argv) < 4:
        print("Usage: python3 agent.py <vps_id> <redis_host> <redis_port> [redis_password]")
        sys.exit(1)
    
    try:
        vps_id = int(sys.argv[1])
        redis_host = sys.argv[2]
        redis_port = int(sys.argv[3])
        redis_password = sys.argv[4] if len(sys.argv) > 4 else None
        
        # Create and start agent
        agent = EZStreamAgent(vps_id, redis_host, redis_port, redis_password)
        
        # Setup signal handlers
        def signal_handler(sig, frame):
            logging.info(f"🔔 Caught signal {sig}, shutting down...")
            agent.stop()
            sys.exit(0)
        
        signal.signal(signal.SIGINT, signal_handler)
        signal.signal(signal.SIGTERM, signal_handler)
        
        # Start the agent
        agent.start()
        
    except Exception as e:
        logging.error(f"❌ Fatal error: {e}")
        sys.exit(1)


if __name__ == "__main__":
    main()
