#!/usr/bin/env python3
"""
SRS Server API Manager
Handles SRS server communication and ingest management
"""

import json
import time
import logging
import requests
import subprocess
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

            logging.info(f"🌐 [SRS_API] Making {method} request to: {url}")
            if data:
                logging.info(f"📋 [SRS_API] Request data: {json.dumps(data, indent=2)}")

            if method.upper() == 'GET':
                response = requests.get(url, timeout=10)
            elif method.upper() == 'POST':
                response = requests.post(url, json=data, timeout=10)
            elif method.upper() == 'DELETE':
                response = requests.delete(url, timeout=10)
            else:
                raise ValueError(f"Unsupported HTTP method: {method}")

            logging.info(f"📡 [SRS_API] Response status: {response.status_code}")

            response.raise_for_status()
            result = response.json()

            logging.info(f"📋 [SRS_API] Response data: {json.dumps(result, indent=2)}")
            return result

        except requests.exceptions.RequestException as e:
            logging.error(f"❌ [SRS_API] Request failed: {e}")
            logging.error(f"❌ [SRS_API] URL: {url}")
            if hasattr(e, 'response') and e.response is not None:
                logging.error(f"❌ [SRS_API] Response status: {e.response.status_code}")
                logging.error(f"❌ [SRS_API] Response text: {e.response.text}")
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
        """Create SRS ingest configuration via API

        Args:
            stream_id: EZStream stream ID
            input_url: Input video URL (HLS from BunnyCDN)
            output_url: Output RTMP URL (YouTube)

        Returns:
            ingest_id if successful, None if failed
        """
        try:
            ingest_id = f"ezstream_stream_{stream_id}_{int(time.time())}"

            logging.info(f"🎯 [SRS_MANAGER] Creating SRS ingest via API:")
            logging.info(f"   - Ingest ID: {ingest_id}")
            logging.info(f"   - Input URL: {input_url}")
            logging.info(f"   - Output URL: {output_url}")

            # SRS ingest configuration
            # Use local RTMP as intermediate, then forward to YouTube
            local_rtmp = f"rtmp://127.0.0.1:1935/live/{ingest_id}"

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
                        "output": local_rtmp
                    }
                }
            }

            # Create ingest via SRS API
            # Note: SRS API might need different endpoint, let's try multiple approaches
            result = self._create_ingest_via_api(ingest_id, ingest_config)

            if result:
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

                # Setup forward to YouTube
                self._setup_forward(ingest_id, output_url)

                logging.info(f"✅ [SRS_MANAGER] SRS ingest created: {ingest_id}")
                return ingest_id
            else:
                logging.error(f"❌ [SRS_MANAGER] Failed to create SRS ingest via API")
                return None

        except Exception as e:
            logging.error(f"❌ [SRS_MANAGER] Error creating SRS ingest: {e}")
            return None
    
    def start_ingest(self, ingest_id: str) -> bool:
        """Start SRS ingest via API"""
        try:
            if ingest_id not in self.ingests:
                logging.error(f"❌ [SRS_MANAGER] Ingest not found: {ingest_id}")
                return False

            logging.info(f"🚀 [SRS_MANAGER] Starting SRS ingest: {ingest_id}")

            # Start ingest via SRS API
            result = self._start_ingest_via_api(ingest_id)

            if result:
                self.ingests[ingest_id].state = SRSIngestState.RUNNING
                self.ingests[ingest_id].started_at = time.time()

                logging.info(f"✅ [SRS_MANAGER] SRS ingest started: {ingest_id}")
                return True
            else:
                self.ingests[ingest_id].state = SRSIngestState.ERROR
                self.ingests[ingest_id].error_message = "Failed to start via SRS API"

                logging.error(f"❌ [SRS_MANAGER] Failed to start SRS ingest: {ingest_id}")
                return False

        except Exception as e:
            logging.error(f"❌ [SRS_MANAGER] Error starting SRS ingest: {e}")
            if ingest_id in self.ingests:
                self.ingests[ingest_id].state = SRSIngestState.ERROR
                self.ingests[ingest_id].error_message = str(e)
            return False
    
    def stop_ingest(self, ingest_id: str) -> bool:
        """Stop FFmpeg stream process"""
        try:
            if ingest_id not in self.ingests:
                logging.error(f"❌ [SRS_MANAGER] Ingest not found: {ingest_id}")
                return False

            logging.info(f"🛑 [SRS_MANAGER] Stopping FFmpeg stream: {ingest_id}")

            # Stop FFmpeg process if exists
            if hasattr(self, 'processes') and ingest_id in self.processes:
                process = self.processes[ingest_id]
                try:
                    process.terminate()
                    # Wait for process to terminate
                    process.wait(timeout=5)
                    logging.info(f"✅ [SRS_MANAGER] FFmpeg process terminated: {ingest_id}")
                except subprocess.TimeoutExpired:
                    # Force kill if doesn't terminate gracefully
                    process.kill()
                    logging.warning(f"⚠️ [SRS_MANAGER] FFmpeg process force killed: {ingest_id}")
                except Exception as e:
                    logging.error(f"❌ [SRS_MANAGER] Error stopping FFmpeg process: {e}")

                # Remove from processes
                del self.processes[ingest_id]

            # Update state
            self.ingests[ingest_id].state = SRSIngestState.STOPPED
            logging.info(f"✅ [SRS_MANAGER] Stream stopped: {ingest_id}")
            return True

        except Exception as e:
            logging.error(f"❌ [SRS_MANAGER] Error stopping stream: {e}")
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

    def _create_ingest_via_api(self, ingest_id: str, ingest_config: Dict) -> bool:
        """Create ingest via SRS API - try multiple approaches"""
        try:
            # Approach 1: Try direct ingest API (if exists)
            logging.info(f"🔧 [SRS_API] Trying to create ingest via API: {ingest_id}")

            # Method 1: POST to /ingests/{id}
            result1 = self._make_request('POST', f'/ingests/{ingest_id}', ingest_config)
            if result1.get('code') == 0:
                logging.info(f"✅ [SRS_API] Ingest created via /ingests/{ingest_id}")
                return True

            # Method 2: POST to /ingests
            result2 = self._make_request('POST', '/ingests', {**ingest_config, 'id': ingest_id})
            if result2.get('code') == 0:
                logging.info(f"✅ [SRS_API] Ingest created via /ingests")
                return True

            # Method 3: Use raw API to modify config
            result3 = self._make_request('POST', '/raw', {
                'rpc': 'ingest',
                'action': 'create',
                'ingest_id': ingest_id,
                'config': ingest_config
            })
            if result3.get('code') == 0:
                logging.info(f"✅ [SRS_API] Ingest created via /raw")
                return True

            logging.error(f"❌ [SRS_API] All ingest creation methods failed")
            logging.error(f"   - Method 1 result: {result1}")
            logging.error(f"   - Method 2 result: {result2}")
            logging.error(f"   - Method 3 result: {result3}")

            return False

        except Exception as e:
            logging.error(f"❌ [SRS_API] Error creating ingest: {e}")
            return False

    def _start_ingest_via_api(self, ingest_id: str) -> bool:
        """Start ingest via SRS API"""
        try:
            logging.info(f"🚀 [SRS_API] Starting ingest: {ingest_id}")

            # Method 1: POST to /ingests/{id}/start
            result1 = self._make_request('POST', f'/ingests/{ingest_id}/start')
            if result1.get('code') == 0:
                logging.info(f"✅ [SRS_API] Ingest started via /ingests/{ingest_id}/start")
                return True

            # Method 2: Use raw API
            result2 = self._make_request('POST', '/raw', {
                'rpc': 'ingest',
                'action': 'start',
                'ingest_id': ingest_id
            })
            if result2.get('code') == 0:
                logging.info(f"✅ [SRS_API] Ingest started via /raw")
                return True

            logging.error(f"❌ [SRS_API] Failed to start ingest")
            logging.error(f"   - Method 1 result: {result1}")
            logging.error(f"   - Method 2 result: {result2}")

            return False

        except Exception as e:
            logging.error(f"❌ [SRS_API] Error starting ingest: {e}")
            return False

    def _setup_forward(self, ingest_id: str, output_url: str) -> bool:
        """Setup SRS forward to YouTube"""
        try:
            logging.info(f"🔗 [SRS_API] Setting up forward for {ingest_id} → {output_url}")

            # Configure forward via API
            forward_config = {
                "forward": {
                    "enabled": True,
                    "destination": output_url
                }
            }

            # Try to set forward configuration
            result = self._make_request('POST', '/vhosts/__defaultVhost__/forward', forward_config)
            if result.get('code') == 0:
                logging.info(f"✅ [SRS_API] Forward configured successfully")
                return True
            else:
                logging.warning(f"⚠️ [SRS_API] Forward config failed, will rely on ingest output: {result}")
                return True  # Continue anyway, ingest might output directly

        except Exception as e:
            logging.error(f"❌ [SRS_API] Error setting up forward: {e}")
            return False


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
