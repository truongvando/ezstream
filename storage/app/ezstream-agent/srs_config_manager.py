#!/usr/bin/env python3
"""
SRS Configuration Manager
Handles dynamic SRS configuration for streaming
"""

import os
import time
import logging
import requests
import subprocess
from typing import Dict, Optional, List, Any
from dataclasses import dataclass

@dataclass
class SRSForwardConfig:
    """SRS Forward configuration"""
    stream_key: str
    destination_url: str
    enabled: bool = True

class SRSConfigManager:
    """
    Manages SRS configuration dynamically
    
    Since SRS doesn't support dynamic ingest creation via API,
    we use a hybrid approach:
    1. FFmpeg pushes to SRS local RTMP
    2. SRS forwards to destinations (configured statically or dynamically)
    3. Monitor via SRS API
    """
    
    def __init__(self, srs_api_url: str = "http://127.0.0.1:1985"):
        self.srs_api_url = srs_api_url.rstrip('/')
        self.config_file = "/opt/srs-config/srs.conf"
        self.forwards: Dict[str, SRSForwardConfig] = {}
        
        logging.info("🔧 [SRS_CONFIG] Initialized SRS Config Manager")

    def add_forward(self, stream_key: str, destination_url: str) -> bool:
        """Add a forward configuration for a stream"""
        try:
            logging.info(f"🔗 [SRS_CONFIG] Adding forward: {stream_key} → {destination_url}")
            
            forward_config = SRSForwardConfig(
                stream_key=stream_key,
                destination_url=destination_url
            )
            
            self.forwards[stream_key] = forward_config
            
            # Update SRS configuration
            success = self._update_srs_config()
            
            if success:
                logging.info(f"✅ [SRS_CONFIG] Forward added successfully")
                return True
            else:
                # Rollback
                del self.forwards[stream_key]
                logging.error(f"❌ [SRS_CONFIG] Failed to add forward")
                return False
                
        except Exception as e:
            logging.error(f"❌ [SRS_CONFIG] Error adding forward: {e}")
            return False

    def remove_forward(self, stream_key: str) -> bool:
        """Remove a forward configuration"""
        try:
            if stream_key not in self.forwards:
                logging.warning(f"⚠️ [SRS_CONFIG] Forward {stream_key} not found")
                return True
            
            logging.info(f"🗑️ [SRS_CONFIG] Removing forward: {stream_key}")
            
            del self.forwards[stream_key]
            
            # Update SRS configuration
            success = self._update_srs_config()
            
            if success:
                logging.info(f"✅ [SRS_CONFIG] Forward removed successfully")
                return True
            else:
                logging.error(f"❌ [SRS_CONFIG] Failed to remove forward")
                return False
                
        except Exception as e:
            logging.error(f"❌ [SRS_CONFIG] Error removing forward: {e}")
            return False

    def check_stream_status(self, stream_key: str) -> Optional[Dict[str, Any]]:
        """Check stream status via SRS API"""
        try:
            # Get all streams from SRS
            response = requests.get(f"{self.srs_api_url}/api/v1/streams", timeout=5)
            
            if response.status_code == 200:
                data = response.json()
                
                if data.get('code') == 0 and 'streams' in data:
                    streams = data['streams']
                    
                    # Look for our stream
                    for stream in streams:
                        if stream.get('name') == stream_key:
                            return {
                                'found': True,
                                'active': True,
                                'clients': stream.get('clients', 0),
                                'publish': stream.get('publish', {}),
                                'play': stream.get('play', {})
                            }
                
                # Stream not found in active streams
                return {'found': False, 'active': False}
            else:
                logging.warning(f"⚠️ [SRS_CONFIG] SRS API returned status {response.status_code}")
                return None
                
        except Exception as e:
            logging.error(f"❌ [SRS_CONFIG] Error checking stream status: {e}")
            return None

    def get_srs_stats(self) -> Optional[Dict[str, Any]]:
        """Get SRS server statistics"""
        try:
            response = requests.get(f"{self.srs_api_url}/api/v1/summaries", timeout=5)
            
            if response.status_code == 200:
                data = response.json()
                if data.get('code') == 0:
                    return data.get('data', {})
            
            return None
            
        except Exception as e:
            logging.error(f"❌ [SRS_CONFIG] Error getting SRS stats: {e}")
            return None

    def _update_srs_config(self) -> bool:
        """Update SRS configuration file with current forwards"""
        try:
            # Read current config
            if not os.path.exists(self.config_file):
                logging.error(f"❌ [SRS_CONFIG] Config file not found: {self.config_file}")
                return False
            
            with open(self.config_file, 'r') as f:
                config_content = f.read()
            
            # Generate forward configuration
            forward_config = self._generate_forward_config()
            
            # Update config content
            updated_config = self._inject_forward_config(config_content, forward_config)
            
            # Write updated config
            backup_file = f"{self.config_file}.backup.{int(time.time())}"
            
            # Backup original
            with open(backup_file, 'w') as f:
                f.write(config_content)
            
            # Write new config
            with open(self.config_file, 'w') as f:
                f.write(updated_config)
            
            # Reload SRS configuration
            success = self._reload_srs()
            
            if not success:
                # Restore backup on failure
                with open(backup_file, 'r') as f:
                    original_config = f.read()
                
                with open(self.config_file, 'w') as f:
                    f.write(original_config)
                
                logging.error(f"❌ [SRS_CONFIG] Config update failed, restored backup")
                return False
            
            # Clean up old backups (keep last 5)
            self._cleanup_backups()
            
            return True
            
        except Exception as e:
            logging.error(f"❌ [SRS_CONFIG] Error updating SRS config: {e}")
            return False

    def _generate_forward_config(self) -> str:
        """Generate forward configuration section"""
        if not self.forwards:
            return ""
        
        config_lines = []
        config_lines.append("    # Dynamic forwards managed by EZStream Agent")
        config_lines.append("    forward {")
        config_lines.append("        enabled on;")
        
        # Add all destinations
        for stream_key, forward_config in self.forwards.items():
            if forward_config.enabled:
                config_lines.append(f"        destination {forward_config.destination_url};")
        
        config_lines.append("    }")
        
        return "\n".join(config_lines)

    def _inject_forward_config(self, config_content: str, forward_config: str) -> str:
        """Inject forward configuration into SRS config"""
        try:
            lines = config_content.split('\n')
            new_lines = []
            in_vhost = False
            forward_injected = False
            
            for line in lines:
                # Skip existing forward sections managed by agent
                if "# Dynamic forwards managed by EZStream Agent" in line:
                    # Skip until end of forward block
                    continue
                
                if line.strip().startswith("vhost __defaultVhost__"):
                    in_vhost = True
                    new_lines.append(line)
                elif in_vhost and line.strip() == "}":
                    # End of vhost, inject forward config before closing
                    if forward_config and not forward_injected:
                        new_lines.append(forward_config)
                        forward_injected = True
                    new_lines.append(line)
                    in_vhost = False
                else:
                    new_lines.append(line)
            
            return '\n'.join(new_lines)
            
        except Exception as e:
            logging.error(f"❌ [SRS_CONFIG] Error injecting forward config: {e}")
            return config_content

    def _reload_srs(self) -> bool:
        """Reload SRS configuration"""
        try:
            # Try API reload first
            try:
                response = requests.get(f"{self.srs_api_url}/api/v1/raw?rpc=reload", timeout=10)
                if response.status_code == 200:
                    data = response.json()
                    if data.get('code') == 0:
                        logging.info("✅ [SRS_CONFIG] SRS reloaded via API")
                        return True
            except:
                pass
            
            # Fallback to signal reload
            try:
                result = subprocess.run(['docker', 'exec', 'ezstream-srs', 'killall', '-1', 'srs'], 
                                      capture_output=True, text=True, timeout=10)
                if result.returncode == 0:
                    logging.info("✅ [SRS_CONFIG] SRS reloaded via signal")
                    return True
            except:
                pass
            
            # Fallback to container restart
            try:
                result = subprocess.run(['docker', 'restart', 'ezstream-srs'], 
                                      capture_output=True, text=True, timeout=30)
                if result.returncode == 0:
                    logging.info("✅ [SRS_CONFIG] SRS restarted")
                    time.sleep(5)  # Wait for restart
                    return True
            except:
                pass
            
            logging.error("❌ [SRS_CONFIG] All reload methods failed")
            return False
            
        except Exception as e:
            logging.error(f"❌ [SRS_CONFIG] Error reloading SRS: {e}")
            return False

    def _cleanup_backups(self):
        """Clean up old backup files"""
        try:
            import glob
            backup_pattern = f"{self.config_file}.backup.*"
            backup_files = sorted(glob.glob(backup_pattern))
            
            # Keep only last 5 backups
            if len(backup_files) > 5:
                for old_backup in backup_files[:-5]:
                    os.remove(old_backup)
                    
        except Exception as e:
            logging.error(f"❌ [SRS_CONFIG] Error cleaning up backups: {e}")


# Global instance
_srs_config_manager: Optional[SRSConfigManager] = None

def init_srs_config_manager() -> SRSConfigManager:
    """Initialize global SRS config manager"""
    global _srs_config_manager
    _srs_config_manager = SRSConfigManager()
    return _srs_config_manager

def get_srs_config_manager() -> Optional[SRSConfigManager]:
    """Get global SRS config manager instance"""
    return _srs_config_manager
