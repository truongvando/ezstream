#!/usr/bin/env python3
"""
SRS Server API Manager
Handles SRS server communication and ingest management
"""

import json
import time
import logging
import requests
from typing import Dict, Optional, Any, List
from dataclasses import dataclass
from enum import Enum

from config import get_config


class SRSIngestState(Enum):
    """SRS Ingest states"""
    STOPPED = "stopped"
    RUNNING = "running"
    ERROR = "error"


@dataclass
class SRSIngestInfo:
    """SRS Ingest information"""
    ingest_id: str
    stream_id: int
    input_url: str
    output_url: str
    state: SRSIngestState
    created_at: float
    started_at: Optional[float] = None
    error_message: Optional[str] = None


class SRSManager:
    """SRS Server API Manager"""
    
    def __init__(self):
        self.config = get_config()
        self.srs_host = getattr(self.config, 'srs_host', 'localhost')
        self.srs_port = getattr(self.config, 'srs_port', 1985)
        self.api_url = f"http://{self.srs_host}:{self.srs_port}/api/v1"
        self.ingests: Dict[str, SRSIngestInfo] = {}
        
        logging.info(f"🎬 SRS Manager initialized - API: {self.api_url}")
    
    def _make_request(self, method: str, endpoint: str, data: Optional[Dict] = None) -> Dict:
        """Make HTTP request to SRS API"""
        try:
            url = f"{self.api_url}/{endpoint.lstrip('/')}"
            
            if method.upper() == 'GET':
                response = requests.get(url, timeout=10)
            elif method.upper() == 'POST':
                response = requests.post(url, json=data, timeout=10)
            elif method.upper() == 'DELETE':
                response = requests.delete(url, timeout=10)
            else:
                raise ValueError(f"Unsupported HTTP method: {method}")
            
            response.raise_for_status()
            return response.json()
            
        except requests.exceptions.RequestException as e:
            logging.error(f"❌ SRS API request failed: {e}")
            return {"code": -1, "error": str(e)}
    
    def check_server_status(self) -> bool:
        """Check if SRS server is running and accessible"""
        try:
            result = self._make_request('GET', '/summaries')
            if result.get('code') == 0:
                logging.info("✅ SRS server is running and accessible")
                return True
            else:
                logging.error(f"❌ SRS server returned error: {result}")
                return False
                
        except Exception as e:
            logging.error(f"❌ Cannot connect to SRS server: {e}")
            return False
    
    def create_ingest(self, stream_id: int, input_url: str, output_url: str) -> Optional[str]:
        """Create SRS ingest configuration
        
        Args:
            stream_id: EZStream stream ID
            input_url: Input video URL (BunnyCDN)
            output_url: Output RTMP URL (YouTube)
            
        Returns:
            ingest_id if successful, None if failed
        """
        try:
            ingest_id = f"ezstream_{stream_id}_{int(time.time())}"
            
            # SRS ingest configuration
            ingest_config = {
                "ingest": {
                    "enabled": True,
                    "input": {
                        "type": "stream",
                        "url": input_url
                    },
                    "ffmpeg": "/usr/local/bin/ffmpeg",
                    "engine": {
                        "enabled": False,  # Copy mode for performance
                        "output": output_url
                    }
                }
            }
            
            # Create ingest via SRS API
            result = self._make_request('POST', f'/ingests/{ingest_id}', ingest_config)
            
            if result.get('code') == 0:
                # Store ingest info
                ingest_info = SRSIngestInfo(
                    ingest_id=ingest_id,
                    stream_id=stream_id,
                    input_url=input_url,
                    output_url=output_url,
                    state=SRSIngestState.STOPPED,
                    created_at=time.time()
                )
                self.ingests[ingest_id] = ingest_info
                
                logging.info(f"✅ SRS ingest created: {ingest_id}")
                return ingest_id
            else:
                logging.error(f"❌ Failed to create SRS ingest: {result}")
                return None
                
        except Exception as e:
            logging.error(f"❌ Error creating SRS ingest: {e}")
            return None
    
    def start_ingest(self, ingest_id: str) -> bool:
        """Start SRS ingest stream"""
        try:
            if ingest_id not in self.ingests:
                logging.error(f"❌ Ingest not found: {ingest_id}")
                return False
            
            # Start ingest via SRS API
            result = self._make_request('POST', f'/ingests/{ingest_id}/start')
            
            if result.get('code') == 0:
                self.ingests[ingest_id].state = SRSIngestState.RUNNING
                self.ingests[ingest_id].started_at = time.time()
                
                logging.info(f"✅ SRS ingest started: {ingest_id}")
                return True
            else:
                self.ingests[ingest_id].state = SRSIngestState.ERROR
                self.ingests[ingest_id].error_message = result.get('error', 'Unknown error')
                
                logging.error(f"❌ Failed to start SRS ingest: {result}")
                return False
                
        except Exception as e:
            logging.error(f"❌ Error starting SRS ingest: {e}")
            if ingest_id in self.ingests:
                self.ingests[ingest_id].state = SRSIngestState.ERROR
                self.ingests[ingest_id].error_message = str(e)
            return False
    
    def stop_ingest(self, ingest_id: str) -> bool:
        """Stop SRS ingest stream"""
        try:
            if ingest_id not in self.ingests:
                logging.error(f"❌ Ingest not found: {ingest_id}")
                return False
            
            # Stop ingest via SRS API
            result = self._make_request('POST', f'/ingests/{ingest_id}/stop')
            
            if result.get('code') == 0:
                self.ingests[ingest_id].state = SRSIngestState.STOPPED
                logging.info(f"✅ SRS ingest stopped: {ingest_id}")
                return True
            else:
                logging.error(f"❌ Failed to stop SRS ingest: {result}")
                return False
                
        except Exception as e:
            logging.error(f"❌ Error stopping SRS ingest: {e}")
            return False
    
    def delete_ingest(self, ingest_id: str) -> bool:
        """Delete SRS ingest configuration"""
        try:
            # Stop first if running
            if ingest_id in self.ingests and self.ingests[ingest_id].state == SRSIngestState.RUNNING:
                self.stop_ingest(ingest_id)
            
            # Delete ingest via SRS API
            result = self._make_request('DELETE', f'/ingests/{ingest_id}')
            
            if result.get('code') == 0:
                if ingest_id in self.ingests:
                    del self.ingests[ingest_id]
                logging.info(f"✅ SRS ingest deleted: {ingest_id}")
                return True
            else:
                logging.error(f"❌ Failed to delete SRS ingest: {result}")
                return False
                
        except Exception as e:
            logging.error(f"❌ Error deleting SRS ingest: {e}")
            return False
    
    def get_ingest_status(self, ingest_id: str) -> Optional[Dict]:
        """Get SRS ingest status"""
        try:
            result = self._make_request('GET', f'/ingests/{ingest_id}')
            
            if result.get('code') == 0:
                return result.get('data', {})
            else:
                logging.error(f"❌ Failed to get ingest status: {result}")
                return None
                
        except Exception as e:
            logging.error(f"❌ Error getting ingest status: {e}")
            return None
    
    def get_server_stats(self) -> Optional[Dict]:
        """Get SRS server statistics"""
        try:
            result = self._make_request('GET', '/summaries')
            
            if result.get('code') == 0:
                return result.get('data', {})
            else:
                logging.error(f"❌ Failed to get server stats: {result}")
                return None
                
        except Exception as e:
            logging.error(f"❌ Error getting server stats: {e}")
            return None
    
    def get_active_ingests(self) -> List[str]:
        """Get list of active ingest IDs"""
        active_ingests = []
        for ingest_id, ingest_info in self.ingests.items():
            if ingest_info.state == SRSIngestState.RUNNING:
                active_ingests.append(ingest_id)
        return active_ingests
    
    def cleanup_stopped_ingests(self):
        """Clean up stopped ingests older than 1 hour"""
        try:
            current_time = time.time()
            to_delete = []
            
            for ingest_id, ingest_info in self.ingests.items():
                if (ingest_info.state == SRSIngestState.STOPPED and 
                    current_time - ingest_info.created_at > 3600):  # 1 hour
                    to_delete.append(ingest_id)
            
            for ingest_id in to_delete:
                self.delete_ingest(ingest_id)
                
            if to_delete:
                logging.info(f"🧹 Cleaned up {len(to_delete)} old SRS ingests")
                
        except Exception as e:
            logging.error(f"❌ Error cleaning up ingests: {e}")


# Global SRS manager instance
srs_manager: Optional[SRSManager] = None

def init_srs_manager():
    """Initialize global SRS manager"""
    global srs_manager
    srs_manager = SRSManager()
    return srs_manager

def get_srs_manager() -> Optional[SRSManager]:
    """Get global SRS manager instance"""
    return srs_manager
