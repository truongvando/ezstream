#!/usr/bin/env python3
"""
EZStream Agent Process Manager
Simple process tracking for SRS-based streaming
"""

import time
import logging
import threading
from typing import Dict, List, Optional
from dataclasses import dataclass, field
from enum import Enum

from config import get_config
from status_reporter import get_status_reporter


class ProcessState(Enum):
    """SRS stream process states"""
    STARTING = "STARTING"
    RUNNING = "RUNNING"
    STOPPING = "STOPPING"
    STOPPED = "STOPPED"
    ERROR = "ERROR"


@dataclass
class ProcessInfo:
    """SRS stream process information"""
    stream_id: int
    state: ProcessState = ProcessState.STARTING
    start_time: float = field(default_factory=time.time)
    error_message: Optional[str] = None
    uptime: float = 0.0


class ProcessManager:
    """Simple process manager for SRS streaming"""
    
    def __init__(self):
        self.config = get_config()
        self.status_reporter = get_status_reporter()
        
        # Process tracking
        self.processes: Dict[int, ProcessInfo] = {}
        self.process_lock = threading.RLock()
        
        logging.info("🔧 SRS Process Manager initialized")
    
    def start_process(self, stream_id: int, srs_config: Dict) -> bool:
        """Register SRS stream process"""
        try:
            with self.process_lock:
                # Check if already running
                if stream_id in self.processes:
                    logging.warning(f"Process for stream {stream_id} already registered")
                    return False
                
                # Create process info
                process_info = ProcessInfo(stream_id=stream_id)
                self.processes[stream_id] = process_info
                
                logging.info(f"🚀 Registered SRS stream process {stream_id}")
                
                # Process started successfully
                process_info.state = ProcessState.RUNNING
                return True
                
        except Exception as e:
            error_msg = f"Error registering SRS stream process: {e}"
            logging.error(f"Stream {stream_id}: {error_msg}")
            if stream_id in self.processes:
                self.processes[stream_id].error_message = error_msg
                self.processes[stream_id].state = ProcessState.ERROR
            return False
    
    def stop_process(self, stream_id: int, reason: str = "manual") -> bool:
        """Unregister SRS stream process"""
        try:
            with self.process_lock:
                if stream_id not in self.processes:
                    logging.warning(f"No process found for stream {stream_id}")
                    return True
                
                process_info = self.processes[stream_id]
                process_info.state = ProcessState.STOPPING
                
                logging.info(f"🛑 Unregistering SRS stream process {stream_id} (reason: {reason})")
                
                # Remove from tracking
                del self.processes[stream_id]
                
                logging.info(f"✅ SRS stream process unregistered {stream_id}")
                return True
                
        except Exception as e:
            logging.error(f"❌ Error unregistering process for stream {stream_id}: {e}")
            return False
    
    def get_process_status(self, stream_id: int) -> Optional[Dict]:
        """Get process status"""
        with self.process_lock:
            if stream_id not in self.processes:
                return None
            
            process_info = self.processes[stream_id]
            process_info.uptime = time.time() - process_info.start_time
            
            return {
                'stream_id': stream_id,
                'state': process_info.state.value,
                'uptime': process_info.uptime,
                'error_message': process_info.error_message,
                'start_time': process_info.start_time
            }
    
    def get_active_processes(self) -> List[int]:
        """Get list of active process IDs"""
        with self.process_lock:
            return list(self.processes.keys())
    
    def stop_all(self):
        """Stop all processes and cleanup"""
        logging.info("🛑 Stopping all SRS stream processes...")
        
        # Stop all processes
        with self.process_lock:
            for stream_id in list(self.processes.keys()):
                try:
                    self.stop_process(stream_id, "shutdown")
                except Exception as e:
                    logging.error(f"❌ Error stopping process {stream_id} during shutdown: {e}")
        
        logging.info("✅ SRS Process Manager stopped")


# Global instance management
_process_manager: Optional['ProcessManager'] = None


def init_process_manager() -> 'ProcessManager':
    """Initialize global process manager"""
    global _process_manager
    _process_manager = ProcessManager()
    return _process_manager


def get_process_manager() -> 'ProcessManager':
    """Get global process manager instance"""
    if _process_manager is None:
        raise RuntimeError("Process manager not initialized. Call init_process_manager() first.")
    return _process_manager
