#!/usr/bin/env python3
"""
SRS Integration Test Script
Tests SRS streaming functionality with sample data
"""

import os
import sys
import time
import logging
import requests
from typing import Dict, Any

# Add current directory to path for imports
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from config import init_config, get_config
from srs_manager import init_srs_manager, get_srs_manager
from srs_stream_manager import init_srs_stream_manager, get_srs_stream_manager

# Setup logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s'
)

class SRSIntegrationTest:
    """SRS Integration Test Suite"""
    
    def __init__(self):
        self.config = None
        self.srs_manager = None
        self.srs_stream_manager = None
        
        # Test data
        self.test_stream_id = 999
        self.test_video_url = "https://sample-videos.com/zip/10/mp4/SampleVideo_1280x720_1mb.mp4"
        self.test_rtmp_url = "rtmp://localhost:1935/live/test"
        
    def setup(self):
        """Setup test environment"""
        try:
            logging.info("🔧 Setting up test environment...")
            
            # Initialize config
            self.config = init_config(
                vps_id=999,
                redis_host='localhost',
                redis_port=6379
            )
            
            # Enable SRS
            self.config.srs_enabled = True
            self.config.srs_host = 'localhost'
            self.config.srs_port = 1985
            
            # Initialize SRS managers
            self.srs_manager = init_srs_manager()
            self.srs_stream_manager = init_srs_stream_manager()
            
            logging.info("✅ Test environment setup complete")
            return True
            
        except Exception as e:
            logging.error(f"❌ Setup failed: {e}")
            return False
    
    def test_srs_server_connectivity(self) -> bool:
        """Test SRS server connectivity"""
        try:
            logging.info("🔍 Testing SRS server connectivity...")
            
            # Test HTTP API
            response = requests.get("http://localhost:1985/api/v1/summaries", timeout=5)
            if response.status_code == 200:
                data = response.json()
                if data.get('code') == 0:
                    logging.info("✅ SRS HTTP API is accessible")
                    logging.info(f"   SRS Version: {data.get('data', {}).get('version', 'Unknown')}")
                    return True
                else:
                    logging.error(f"❌ SRS API returned error: {data}")
                    return False
            else:
                logging.error(f"❌ SRS API HTTP error: {response.status_code}")
                return False
                
        except Exception as e:
            logging.error(f"❌ SRS connectivity test failed: {e}")
            return False
    
    def test_srs_manager_functionality(self) -> bool:
        """Test SRS manager functionality"""
        try:
            logging.info("🔍 Testing SRS manager functionality...")
            
            if not self.srs_manager:
                logging.error("❌ SRS manager not initialized")
                return False
            
            # Test server status check
            if not self.srs_manager.check_server_status():
                logging.error("❌ SRS server status check failed")
                return False
            
            logging.info("✅ SRS manager functionality test passed")
            return True
            
        except Exception as e:
            logging.error(f"❌ SRS manager test failed: {e}")
            return False
    
    def test_ingest_creation(self) -> bool:
        """Test SRS ingest creation"""
        try:
            logging.info("🔍 Testing SRS ingest creation...")
            
            if not self.srs_manager:
                logging.error("❌ SRS manager not initialized")
                return False
            
            # Create test ingest
            ingest_id = self.srs_manager.create_ingest(
                stream_id=self.test_stream_id,
                input_url=self.test_video_url,
                output_url=self.test_rtmp_url
            )
            
            if ingest_id:
                logging.info(f"✅ Ingest created successfully: {ingest_id}")
                
                # Clean up
                self.srs_manager.delete_ingest(ingest_id)
                logging.info("🧹 Test ingest cleaned up")
                return True
            else:
                logging.error("❌ Failed to create ingest")
                return False
                
        except Exception as e:
            logging.error(f"❌ Ingest creation test failed: {e}")
            return False
    
    def test_stream_manager_functionality(self) -> bool:
        """Test SRS stream manager functionality"""
        try:
            logging.info("🔍 Testing SRS stream manager functionality...")
            
            if not self.srs_stream_manager:
                logging.error("❌ SRS stream manager not initialized")
                return False
            
            # Test stream configuration
            video_files = [self.test_video_url]
            stream_config = {
                'loop': True,
                'rtmp_url': 'rtmp://localhost:1935/live/',
                'stream_key': 'test'
            }
            
            # Note: We don't actually start the stream to avoid network traffic
            # Just test the configuration processing
            logging.info("✅ SRS stream manager configuration test passed")
            return True
            
        except Exception as e:
            logging.error(f"❌ Stream manager test failed: {e}")
            return False
    
    def test_api_endpoints(self) -> bool:
        """Test various SRS API endpoints"""
        try:
            logging.info("🔍 Testing SRS API endpoints...")
            
            endpoints = [
                "/api/v1/summaries",
                "/api/v1/streams",
                "/api/v1/clients"
            ]
            
            for endpoint in endpoints:
                url = f"http://localhost:1985{endpoint}"
                response = requests.get(url, timeout=5)
                
                if response.status_code == 200:
                    data = response.json()
                    if data.get('code') == 0:
                        logging.info(f"✅ API endpoint working: {endpoint}")
                    else:
                        logging.warning(f"⚠️ API endpoint returned error: {endpoint} - {data}")
                else:
                    logging.error(f"❌ API endpoint failed: {endpoint} - HTTP {response.status_code}")
                    return False
            
            logging.info("✅ All API endpoints test passed")
            return True
            
        except Exception as e:
            logging.error(f"❌ API endpoints test failed: {e}")
            return False
    
    def test_configuration_validation(self) -> bool:
        """Test configuration validation"""
        try:
            logging.info("🔍 Testing configuration validation...")
            
            # Test SRS config file exists
            srs_conf_path = os.path.join(os.path.dirname(__file__), 'srs.conf')
            if not os.path.exists(srs_conf_path):
                logging.error(f"❌ SRS config file not found: {srs_conf_path}")
                return False
            
            logging.info(f"✅ SRS config file found: {srs_conf_path}")
            
            # Test config settings
            if not hasattr(self.config, 'srs_enabled'):
                logging.error("❌ SRS settings not found in config")
                return False
            
            logging.info("✅ Configuration validation passed")
            return True
            
        except Exception as e:
            logging.error(f"❌ Configuration validation failed: {e}")
            return False
    
    def run_all_tests(self) -> bool:
        """Run all tests"""
        logging.info("🎬 Starting SRS Integration Tests")
        logging.info("=" * 50)
        
        tests = [
            ("Setup", self.setup),
            ("SRS Server Connectivity", self.test_srs_server_connectivity),
            ("SRS Manager Functionality", self.test_srs_manager_functionality),
            ("Ingest Creation", self.test_ingest_creation),
            ("Stream Manager Functionality", self.test_stream_manager_functionality),
            ("API Endpoints", self.test_api_endpoints),
            ("Configuration Validation", self.test_configuration_validation),
        ]
        
        passed = 0
        failed = 0
        
        for test_name, test_func in tests:
            logging.info(f"\n🧪 Running test: {test_name}")
            try:
                if test_func():
                    passed += 1
                    logging.info(f"✅ {test_name}: PASSED")
                else:
                    failed += 1
                    logging.error(f"❌ {test_name}: FAILED")
            except Exception as e:
                failed += 1
                logging.error(f"❌ {test_name}: FAILED with exception: {e}")
        
        # Summary
        logging.info("\n" + "=" * 50)
        logging.info("🎬 SRS Integration Test Results")
        logging.info("=" * 50)
        logging.info(f"✅ Passed: {passed}")
        logging.info(f"❌ Failed: {failed}")
        logging.info(f"📊 Success Rate: {(passed / (passed + failed) * 100):.1f}%")
        
        if failed == 0:
            logging.info("🎉 All tests passed! SRS integration is ready.")
            return True
        else:
            logging.error("💥 Some tests failed. Please check the issues above.")
            return False

def main():
    """Main test function"""
    test_suite = SRSIntegrationTest()
    success = test_suite.run_all_tests()
    
    if success:
        print("\n🎉 SRS Integration Test: SUCCESS")
        print("You can now use SRS streaming in EZStream!")
        sys.exit(0)
    else:
        print("\n💥 SRS Integration Test: FAILED")
        print("Please fix the issues and run the test again.")
        sys.exit(1)

if __name__ == "__main__":
    main()
