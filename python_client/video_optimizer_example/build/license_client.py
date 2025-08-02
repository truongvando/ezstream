#!/usr/bin/env python3
"""
EzStream License Client
Tích hợp license verification cho Python tools
"""

import requests
import hashlib
import platform
import socket
import uuid
import json
import time
import sys
import os
from typing import Dict, Tuple, Optional

class LicenseClient:
    def __init__(self, license_key: str, server_url: str = None, tool_id: int = None):
        """
        Initialize license client

        Args:
            license_key: License key format XXXX-XXXX-XXXX-XXXX
            server_url: EzStream server URL (auto-detected if None)
            tool_id: Specific tool ID for verification (optional)
        """
        self.license_key = license_key.strip()
        self.server_url = self._get_server_url(server_url)
        self.tool_id = tool_id
        self.api_config = None
        self.device_id = self._generate_device_id()
        self.device_name = self._get_device_name()
        self.device_info = self._get_device_info()

    def _get_server_url(self, provided_url: str = None) -> str:
        """Get server URL from multiple sources"""
        # 1. Use provided URL
        if provided_url:
            return provided_url.rstrip('/')

        # 2. Try environment variable
        env_url = os.environ.get('EZSTREAM_SERVER_URL')
        if env_url:
            return env_url.rstrip('/')

        # 3. Try config file
        try:
            if os.path.exists('ezstream_config.json'):
                with open('ezstream_config.json', 'r') as f:
                    config = json.loads(f.read())
                    if config.get('server_url'):
                        return config['server_url'].rstrip('/')
        except:
            pass

        # 4. Default fallback
        return "https://ezstream.pro"

    def _fetch_api_config(self) -> bool:
        """Fetch API configuration from server"""
        try:
            config_url = f"{self.server_url}/api/license/config"
            response = requests.get(config_url, timeout=10)

            if response.status_code == 200:
                data = response.json()
                if data.get('success'):
                    self.api_config = data.get('data', {})
                    return True
            return False
        except:
            return False

    def _generate_device_id(self) -> str:
        """Generate unique device ID based on hardware"""
        try:
            # Combine multiple hardware identifiers
            machine = platform.machine()
            processor = platform.processor()
            hostname = socket.gethostname()
            
            # Try to get MAC address
            try:
                mac = ':'.join(['{:02x}'.format((uuid.getnode() >> elements) & 0xff) 
                               for elements in range(0,2*6,2)][::-1])
            except:
                mac = "unknown"
            
            # Create unique string
            unique_string = f"{machine}-{processor}-{hostname}-{mac}"
            
            # Hash to create consistent device ID
            device_hash = hashlib.sha256(unique_string.encode()).hexdigest()[:16]
            return device_hash.upper()
            
        except Exception as e:
            # Fallback to basic UUID
            return str(uuid.uuid4()).replace('-', '')[:16].upper()
    
    def _get_device_name(self) -> str:
        """Get user-friendly device name"""
        try:
            hostname = socket.gethostname()
            system = platform.system()
            return f"{hostname} ({system})"
        except:
            return "Unknown Device"
    
    def _get_device_info(self) -> Dict:
        """Get detailed device information"""
        try:
            return {
                "platform": platform.platform(),
                "system": platform.system(),
                "release": platform.release(),
                "version": platform.version(),
                "machine": platform.machine(),
                "processor": platform.processor(),
                "hostname": socket.gethostname(),
                "python_version": platform.python_version(),
            }
        except Exception as e:
            return {"error": str(e)}
    
    def verify(self, timeout: int = 30) -> bool:
        """
        Verify license with server
        
        Args:
            timeout: Request timeout in seconds
            
        Returns:
            bool: True if license is valid and activated
        """
        try:
            url = f"{self.server_url}/api/license/verify"
            
            payload = {
                "license_key": self.license_key,
                "device_id": self.device_id,
                "device_name": self.device_name,
                "device_info": self.device_info
            }

            # Add tool_id if specified
            if self.tool_id:
                payload["tool_id"] = self.tool_id
            
            response = requests.post(
                url, 
                json=payload, 
                timeout=timeout,
                headers={"Content-Type": "application/json"}
            )
            
            if response.status_code == 200:
                data = response.json()
                if data.get('success'):
                    print(f"✅ License verified successfully!")
                    print(f"📱 Device: {self.device_name}")
                    print(f"🔑 Device ID: {self.device_id}")
                    return True
                else:
                    print(f"❌ License verification failed: {data.get('message', 'Unknown error')}")
                    return False
            else:
                print(f"❌ Server error: HTTP {response.status_code}")
                if response.status_code == 404:
                    print("License key not found. Please check your license key.")
                elif response.status_code == 409:
                    print("License already activated on another device.")
                elif response.status_code == 403:
                    print("License has expired or been revoked.")
                return False
                
        except requests.exceptions.Timeout:
            print("❌ Connection timeout. Please check your internet connection.")
            return False
        except requests.exceptions.ConnectionError:
            print("❌ Cannot connect to license server. Please check your internet connection.")
            return False
        except Exception as e:
            print(f"❌ Unexpected error: {e}")
            return False
    
    def verify_with_retry(self, max_retries: int = 3, delay: int = 2) -> bool:
        """
        Verify license with retry mechanism
        
        Args:
            max_retries: Maximum number of retry attempts
            delay: Delay between retries in seconds
            
        Returns:
            bool: True if verification successful
        """
        for attempt in range(max_retries):
            if attempt > 0:
                print(f"🔄 Retry attempt {attempt}/{max_retries-1}...")
                time.sleep(delay)
            
            if self.verify():
                return True
        
        print(f"❌ License verification failed after {max_retries} attempts.")
        return False
    
    def check_status(self, timeout: int = 30) -> Tuple[bool, Optional[Dict]]:
        """
        Check license status without activation
        
        Args:
            timeout: Request timeout in seconds
            
        Returns:
            Tuple[bool, Dict]: (is_valid, license_data)
        """
        try:
            url = f"{self.server_url}/api/license/check-status"
            
            payload = {
                "license_key": self.license_key,
                "device_id": self.device_id
            }

            # Add tool_id if specified
            if self.tool_id:
                payload["tool_id"] = self.tool_id

            response = requests.post(
                url, 
                json=payload, 
                timeout=timeout,
                headers={"Content-Type": "application/json"}
            )
            
            if response.status_code == 200:
                data = response.json()
                return data.get('success', False), data.get('data')
            else:
                return False, None
                
        except Exception as e:
            print(f"❌ Error checking status: {e}")
            return False, None
    
    def deactivate(self, timeout: int = 30) -> bool:
        """
        Deactivate license from current device
        
        Args:
            timeout: Request timeout in seconds
            
        Returns:
            bool: True if deactivation successful
        """
        try:
            url = f"{self.server_url}/api/license/deactivate"
            
            payload = {
                "license_key": self.license_key,
                "device_id": self.device_id
            }
            
            response = requests.post(
                url, 
                json=payload, 
                timeout=timeout,
                headers={"Content-Type": "application/json"}
            )
            
            if response.status_code == 200:
                data = response.json()
                if data.get('success'):
                    print("✅ License deactivated successfully!")
                    return True
                else:
                    print(f"❌ Deactivation failed: {data.get('message', 'Unknown error')}")
                    return False
            else:
                print(f"❌ Server error: HTTP {response.status_code}")
                return False
                
        except Exception as e:
            print(f"❌ Error deactivating license: {e}")
            return False

def main():
    """Command line interface for testing"""
    if len(sys.argv) < 3:
        print("Usage: python license_client.py <license_key> <action>")
        print("Actions: verify, status, deactivate")
        sys.exit(1)
    
    license_key = sys.argv[1]
    action = sys.argv[2].lower()
    
    client = LicenseClient(license_key)
    
    print(f"🔑 License Key: {license_key}")
    print(f"📱 Device ID: {client.device_id}")
    print(f"💻 Device Name: {client.device_name}")
    print("-" * 50)
    
    if action == "verify":
        success = client.verify_with_retry()
        sys.exit(0 if success else 1)
        
    elif action == "status":
        is_valid, data = client.check_status()
        if is_valid and data:
            print("📊 License Status:")
            print(json.dumps(data, indent=2))
        else:
            print("❌ Cannot get license status")
        sys.exit(0 if is_valid else 1)
        
    elif action == "deactivate":
        success = client.deactivate()
        sys.exit(0 if success else 1)
        
    else:
        print(f"❌ Unknown action: {action}")
        sys.exit(1)

if __name__ == "__main__":
    main()
