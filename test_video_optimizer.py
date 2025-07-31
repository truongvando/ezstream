#!/usr/bin/env python3
"""
Test Script cho Video Optimizer (Wrapper)
Chạy test render video về chuẩn FFmpeg tối ưu
"""

import os
import sys
import subprocess

def main():
    print("🎬 EZStream Video Optimizer Test (Wrapper)")
    print("=" * 50)

    # Check if we're in the right directory
    ezstream_agent_path = os.path.join("storage", "app", "ezstream-agent")
    test_script_path = os.path.join(ezstream_agent_path, "test_optimizer.py")

    if not os.path.exists(test_script_path):
        print(f"❌ Test script not found: {test_script_path}")
        print("🔧 Make sure you're running from the project root directory")
        print("📁 Expected structure:")
        print("   project_root/")
        print("   ├── test_video_optimizer.py")
        print("   └── storage/app/ezstream-agent/")
        print("       ├── video_optimizer.py")
        print("       └── test_optimizer.py")
        return

    print(f"🚀 Running test script from: {ezstream_agent_path}")
    print(f"📁 Working directory: {os.getcwd()}")

    try:
        # Change to ezstream-agent directory and run test
        original_cwd = os.getcwd()
        os.chdir(ezstream_agent_path)

        # Run the actual test script
        result = subprocess.run([sys.executable, "test_optimizer.py"],
                              cwd=os.getcwd())

        # Return to original directory
        os.chdir(original_cwd)

        if result.returncode == 0:
            print("\n✅ Test completed successfully!")
        else:
            print(f"\n❌ Test failed with return code: {result.returncode}")

    except Exception as e:
        print(f"❌ Error running test: {e}")
        # Make sure we return to original directory
        try:
            os.chdir(original_cwd)
        except:
            pass

if __name__ == '__main__':
    main()


if __name__ == '__main__':
    main()
