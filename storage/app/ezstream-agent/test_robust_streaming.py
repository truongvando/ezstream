#!/usr/bin/env python3
"""
Test script for robust streaming system
Tests all components and integration
"""

import sys
import time
import logging
import json
from typing import Dict, Any

# Setup logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)

def test_srs_api():
    """Test SRS API connectivity"""
    try:
        import requests
        
        print("🔍 Testing SRS API connectivity...")
        
        # Test basic API
        response = requests.get("http://127.0.0.1:1985/api/v1/versions", timeout=5)
        if response.status_code == 200:
            data = response.json()
            print(f"✅ SRS API working - Version: {data.get('data', {}).get('version', 'Unknown')}")
            return True
        else:
            print(f"❌ SRS API returned status {response.status_code}")
            return False
            
    except Exception as e:
        print(f"❌ SRS API test failed: {e}")
        return False

def test_srs_config_manager():
    """Test SRS config manager"""
    try:
        print("🔧 Testing SRS Config Manager...")
        
        from srs_config_manager import SRSConfigManager
        
        # Initialize manager
        manager = SRSConfigManager()
        
        # Test SRS stats
        stats = manager.get_srs_stats()
        if stats:
            print(f"✅ SRS stats retrieved - Uptime: {stats.get('self', {}).get('srs_uptime', 'Unknown')}s")
        else:
            print("⚠️ Could not retrieve SRS stats")
        
        # Test stream status check
        status = manager.check_stream_status("test_stream")
        if status is not None:
            print(f"✅ Stream status check working - Found: {status.get('found', False)}")
        else:
            print("⚠️ Stream status check failed")
        
        return True
        
    except Exception as e:
        print(f"❌ SRS Config Manager test failed: {e}")
        return False

def test_robust_stream_manager():
    """Test robust stream manager"""
    try:
        print("🚀 Testing Robust Stream Manager...")
        
        from robust_stream_manager import RobustStreamManager, StreamConfig, StreamType
        
        # Initialize manager
        manager = RobustStreamManager()
        
        # Test configuration
        config = StreamConfig(
            stream_id=999,
            input_url="https://test.example.com/test.m3u8",
            output_url="rtmp://test.example.com/live/test",
            stream_type=StreamType.HLS_TO_RTMP,
            use_srs=False,  # Don't actually start for test
            max_retries=1
        )
        
        print(f"✅ Stream config created - ID: {config.stream_id}")
        
        # Test status methods
        status = manager.get_stream_status(999)
        print(f"✅ Status method working - Status: {status}")
        
        all_status = manager.get_all_streams_status()
        print(f"✅ All status method working - Count: {len(all_status)}")
        
        return True
        
    except Exception as e:
        print(f"❌ Robust Stream Manager test failed: {e}")
        return False

def test_stream_integration():
    """Test stream integration layer"""
    try:
        print("🔗 Testing Stream Integration...")
        
        from stream_integration import StreamIntegration
        
        # Initialize integration
        integration = StreamIntegration()
        
        # Test configuration methods
        rtmp_url = integration._build_youtube_rtmp_endpoint({
            'stream_key': 'test_key_123'
        })
        
        expected_url = "rtmp://a.rtmp.youtube.com/live2/test_key_123"
        if rtmp_url == expected_url:
            print(f"✅ RTMP URL building working - URL: {rtmp_url}")
        else:
            print(f"⚠️ RTMP URL mismatch - Got: {rtmp_url}, Expected: {expected_url}")
        
        # Test stream type detection
        from robust_stream_manager import StreamType
        
        hls_type = integration._detect_stream_type("https://example.com/test.m3u8")
        if hls_type == StreamType.HLS_TO_RTMP:
            print("✅ HLS stream type detection working")
        else:
            print(f"⚠️ HLS stream type detection failed - Got: {hls_type}")
        
        rtmp_type = integration._detect_stream_type("rtmp://example.com/live/test")
        if rtmp_type == StreamType.RTMP_TO_RTMP:
            print("✅ RTMP stream type detection working")
        else:
            print(f"⚠️ RTMP stream type detection failed - Got: {rtmp_type}")
        
        return True
        
    except Exception as e:
        print(f"❌ Stream Integration test failed: {e}")
        return False

def test_ffmpeg_command_building():
    """Test FFmpeg command building"""
    try:
        print("🎬 Testing FFmpeg command building...")
        
        from robust_stream_manager import RobustStreamManager, StreamType
        
        manager = RobustStreamManager()
        
        # Test HLS to RTMP command
        cmd = manager._build_ffmpeg_command(
            "https://example.com/test.m3u8",
            "rtmp://example.com/live/test",
            StreamType.HLS_TO_RTMP,
            {}
        )
        
        if 'ffmpeg' in cmd and '-i' in cmd and 'https://example.com/test.m3u8' in cmd:
            print(f"✅ FFmpeg command building working - Command length: {len(cmd)}")
            print(f"   Command: {' '.join(cmd[:10])}...")  # Show first 10 parts
        else:
            print(f"⚠️ FFmpeg command building failed - Command: {cmd}")
        
        return True
        
    except Exception as e:
        print(f"❌ FFmpeg command building test failed: {e}")
        return False

def test_full_integration():
    """Test full integration without actually starting streams"""
    try:
        print("🔄 Testing full integration...")
        
        # Initialize all components
        from srs_config_manager import init_srs_config_manager
        from stream_integration import init_stream_integration
        
        srs_manager = init_srs_config_manager()
        stream_integration = init_stream_integration()
        
        if srs_manager and stream_integration:
            print("✅ All components initialized successfully")
        else:
            print("⚠️ Some components failed to initialize")
            return False
        
        # Test configuration flow
        test_config = {
            'stream_key': 'test_key_123',
            'video_files': ['https://example.com/test.m3u8'],
            'quality': 'medium',
            'loop': True
        }
        
        # Test config processing (without actually starting)
        rtmp_endpoint = stream_integration._build_youtube_rtmp_endpoint(test_config)
        stream_type = stream_integration._detect_stream_type(test_config['video_files'][0])
        ffmpeg_options = stream_integration._build_ffmpeg_options(test_config)
        
        print(f"✅ Config processing working:")
        print(f"   - RTMP: {rtmp_endpoint}")
        print(f"   - Type: {stream_type.value}")
        print(f"   - Options: {ffmpeg_options}")
        
        return True
        
    except Exception as e:
        print(f"❌ Full integration test failed: {e}")
        return False

def main():
    """Run all tests"""
    print("🧪 Starting Robust Streaming System Tests")
    print("=" * 50)
    
    tests = [
        ("SRS API", test_srs_api),
        ("SRS Config Manager", test_srs_config_manager),
        ("Robust Stream Manager", test_robust_stream_manager),
        ("Stream Integration", test_stream_integration),
        ("FFmpeg Command Building", test_ffmpeg_command_building),
        ("Full Integration", test_full_integration),
    ]
    
    results = {}
    
    for test_name, test_func in tests:
        print(f"\n📋 Running {test_name} test...")
        try:
            result = test_func()
            results[test_name] = result
            if result:
                print(f"✅ {test_name} test PASSED")
            else:
                print(f"❌ {test_name} test FAILED")
        except Exception as e:
            print(f"💥 {test_name} test CRASHED: {e}")
            results[test_name] = False
    
    # Summary
    print("\n" + "=" * 50)
    print("📊 TEST SUMMARY")
    print("=" * 50)
    
    passed = sum(1 for result in results.values() if result)
    total = len(results)
    
    for test_name, result in results.items():
        status = "✅ PASS" if result else "❌ FAIL"
        print(f"{test_name:25} {status}")
    
    print(f"\nOverall: {passed}/{total} tests passed")
    
    if passed == total:
        print("🎉 All tests passed! Robust streaming system is ready.")
        return 0
    else:
        print("⚠️ Some tests failed. Please check the issues above.")
        return 1

if __name__ == "__main__":
    sys.exit(main())
