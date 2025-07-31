#!/usr/bin/env python3
"""
Simple Video Optimizer Test Runner
"""

import os
import sys

def main():
    print("🎬 EZStream Video Optimizer - Simple Test Runner")
    print("=" * 60)
    
    # Check if we're in the right directory
    ezstream_agent_path = "storage/app/ezstream-agent"
    
    if not os.path.exists(ezstream_agent_path):
        print(f"❌ Directory not found: {ezstream_agent_path}")
        print("🔧 Make sure you're running from the project root directory")
        return
    
    print(f"📁 Found ezstream-agent directory: {ezstream_agent_path}")
    
    # Get input video path
    input_video = input("\n📁 Enter video file path: ").strip().strip('"')
    
    if not os.path.exists(input_video):
        print(f"❌ File not found: {input_video}")
        return
    
    print(f"✅ Input video: {input_video}")
    
    # Choose profile
    print(f"\n🎯 Choose optimization profile:")
    print(f"1. Auto-detect (recommended)")
    print(f"2. Full HD Stable (1080p)")
    print(f"3. Full HD Premium (1080p)")
    print(f"4. 2K Stable (1440p)")
    print(f"5. 2K Premium (1440p)")
    print(f"6. 4K Stable (2160p)")
    print(f"7. 4K Premium (2160p)")
    print(f"8. Test Fast (720p)")

    choice = input("Enter choice (1-8): ").strip()
    
    profile_map = {
        "1": "auto",
        "2": "fhd_stable",
        "3": "fhd_premium", 
        "4": "2k_stable",
        "5": "2k_premium",
        "6": "4k_stable",
        "7": "4k_premium",
        "8": "test_fast"
    }
    
    profile = profile_map.get(choice, "auto")
    print(f"✅ Selected profile: {profile}")
    
    # Output path
    input_name = os.path.splitext(os.path.basename(input_video))[0]
    output_dir = "optimized_videos"
    os.makedirs(output_dir, exist_ok=True)
    
    if profile == "auto":
        output_path = os.path.join(output_dir, f"{input_name}_optimized.mp4")
    else:
        output_path = os.path.join(output_dir, f"{input_name}_{profile}.mp4")
    
    print(f"📁 Output will be: {output_path}")
    
    # Build command
    cmd_parts = [
        sys.executable,
        "video_optimizer.py",
        f'"{input_video}"',
        f'"{output_path}"'
    ]
    
    if profile != "auto":
        cmd_parts.extend(["--profile", profile])

    cmd_parts.append("--test")  # Include stream compatibility test
    
    cmd = " ".join(cmd_parts)
    
    print(f"\n🔧 Running command:")
    print(f"   {cmd}")
    print(f"\n🚀 Starting optimization...")
    print("=" * 60)
    
    # Change to ezstream-agent directory and run
    original_cwd = os.getcwd()
    
    try:
        os.chdir(ezstream_agent_path)
        
        # Run the command
        exit_code = os.system(cmd)
        
        # Return to original directory
        os.chdir(original_cwd)
        
        if exit_code == 0:
            print("\n" + "=" * 60)
            print("✅ OPTIMIZATION COMPLETED SUCCESSFULLY!")
            print(f"📁 Output file: {output_path}")
            print(f"\n🎬 NEXT STEPS:")
            print(f"1. Test the optimized video with your current streaming system")
            print(f"2. Run a 24-hour stream loop test:")
            print(f"   ffmpeg -re -stream_loop -1 -i \"{output_path}\" -c copy -f flv rtmp://your-endpoint")
            print(f"3. If stable → Implement vast.ai integration")
        else:
            print(f"\n❌ OPTIMIZATION FAILED (exit code: {exit_code})")
            print(f"🔧 Check the error messages above")
            
    except Exception as e:
        print(f"❌ Error: {e}")
        os.chdir(original_cwd)


if __name__ == '__main__':
    main()
