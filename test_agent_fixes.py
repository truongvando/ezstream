#!/usr/bin/env python3
"""
Test script for agent fixes
"""

import sys
import os
import subprocess
import time

def test_python_version():
    """Test Python version compatibility"""
    print("🐍 Testing Python version compatibility...")
    
    version = sys.version_info
    print(f"Python version: {version.major}.{version.minor}.{version.micro}")
    
    if version.major < 3 or (version.major == 3 and version.minor < 7):
        print("❌ Python 3.7+ required")
        return False
    
    print("✅ Python version compatible")
    return True

def test_threadpool_executor():
    """Test ThreadPoolExecutor shutdown compatibility"""
    print("\n🧵 Testing ThreadPoolExecutor shutdown...")
    
    try:
        from concurrent.futures import ThreadPoolExecutor
        
        # Test without timeout (compatible)
        executor = ThreadPoolExecutor(max_workers=2)
        executor.shutdown(wait=True)
        print("✅ ThreadPoolExecutor.shutdown(wait=True) works")
        
        # Test with timeout (may fail on older Python)
        executor = ThreadPoolExecutor(max_workers=2)
        try:
            executor.shutdown(wait=True, timeout=5)
            print("✅ ThreadPoolExecutor.shutdown(wait=True, timeout=5) works")
        except TypeError as e:
            print(f"❌ ThreadPoolExecutor timeout not supported: {e}")
            print("✅ This is expected on Python < 3.9")
        
        return True
        
    except Exception as e:
        print(f"❌ ThreadPoolExecutor test failed: {e}")
        return False

def test_agent_imports():
    """Test agent module imports"""
    print("\n📦 Testing agent module imports...")
    
    agent_dir = os.path.dirname(os.path.abspath(__file__))
    sys.path.insert(0, agent_dir)
    
    modules = [
        'config',
        'command_handler', 
        'status_reporter',
        'process_manager',
        'stream_manager',
        'file_manager',
        'utils'
    ]
    
    for module in modules:
        try:
            __import__(module)
            print(f"✅ {module} imported successfully")
        except Exception as e:
            print(f"❌ Failed to import {module}: {e}")
            return False
    
    return True

def test_redis_connection():
    """Test Redis connection"""
    print("\n🔴 Testing Redis connection...")
    
    try:
        import redis
        
        # Try to connect to Redis
        r = redis.Redis(host='127.0.0.1', port=6379, decode_responses=True)
        r.ping()
        print("✅ Redis connection successful")
        return True
        
    except Exception as e:
        print(f"❌ Redis connection failed: {e}")
        return False

def test_signal_handling():
    """Test signal handling"""
    print("\n📡 Testing signal handling...")
    
    try:
        import signal
        
        def dummy_handler(sig, frame):
            print(f"Signal {sig} received")
        
        signal.signal(signal.SIGTERM, dummy_handler)
        signal.signal(signal.SIGINT, dummy_handler)
        
        print("✅ Signal handlers registered successfully")
        return True
        
    except Exception as e:
        print(f"❌ Signal handling test failed: {e}")
        return False

def main():
    """Run all tests"""
    print("🧪 Running Agent Compatibility Tests...\n")
    
    tests = [
        test_python_version,
        test_threadpool_executor,
        test_agent_imports,
        test_redis_connection,
        test_signal_handling
    ]
    
    passed = 0
    total = len(tests)
    
    for test in tests:
        if test():
            passed += 1
    
    print(f"\n📊 Test Results: {passed}/{total} passed")
    
    if passed == total:
        print("🎉 All tests passed! Agent should work correctly.")
        return 0
    else:
        print("⚠️ Some tests failed. Check the issues above.")
        return 1

if __name__ == "__main__":
    sys.exit(main())
