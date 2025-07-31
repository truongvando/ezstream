#!/usr/bin/env python3
"""
Test Script cho Video Optimizer (chạy từ ezstream-agent directory)
"""

import os
import sys
import time
from pathlib import Path
from video_optimizer import VideoOptimizer

def main():
    print("🎬 EZStream Video Optimizer Test")
    print("=" * 50)
    
    # Input video path
    input_video = input("📁 Nhập đường dẫn video input: ").strip()
    
    if not os.path.exists(input_video):
        print(f"❌ File không tồn tại: {input_video}")
        return
    
    # Output path
    input_name = Path(input_video).stem
    output_dir = "../../../optimized_videos"  # Relative to ezstream-agent
    os.makedirs(output_dir, exist_ok=True)
    
    # Initialize optimizer
    optimizer = VideoOptimizer(use_gpu=True)
    
    # Analyze input video
    print(f"\n🔍 Analyzing input video...")
    analysis = optimizer.analyze_video(input_video)
    
    if analysis.get('video'):
        width = analysis['video'].get('width', 0)
        height = analysis['video'].get('height', 0)
        fps = analysis['video'].get('fps', 0)
        duration = analysis.get('duration', 0)
        
        print(f"📊 Video Info:")
        print(f"   Resolution: {width}x{height}")
        print(f"   FPS: {fps:.1f}")
        print(f"   Duration: {duration:.1f}s")
        print(f"   Size: {analysis.get('size_mb', 0):.1f}MB")
    
    # Get recommended profile
    recommended_stable = optimizer.recommend_profile(input_video, 'stable')
    recommended_premium = optimizer.recommend_profile(input_video, 'premium')
    
    print(f"\n🎯 Recommended Profiles:")
    print(f"   • {recommended_stable} (Stable)")
    print(f"   • {recommended_premium} (Premium)")
    
    # Show all available profiles by resolution
    print(f"\n📋 Available Profiles by Resolution:")
    resolution_profiles = optimizer.get_resolution_profiles()
    for resolution, profiles in resolution_profiles.items():
        print(f"   {resolution}:")
        for profile in profiles:
            marker = "👉" if profile in [recommended_stable, recommended_premium] else "  "
            desc = optimizer.profiles[profile].description
            print(f"   {marker} {profile}: {desc}")
    
    # Ask user which profiles to test
    print(f"\n🎯 Select profiles to test:")
    print(f"1. Quick test (test_fast only)")
    print(f"2. Recommended stable ({recommended_stable})")
    print(f"3. Recommended premium ({recommended_premium})")
    print(f"4. Both recommended profiles")
    print(f"5. All profiles (warning: takes long time)")
    
    choice = input("Enter choice (1-5): ").strip()
    
    if choice == "1":
        profiles_to_test = [('test_fast', 'Quick test (720p)')]
    elif choice == "2":
        profiles_to_test = [(recommended_stable, 'Recommended Stable')]
    elif choice == "3":
        profiles_to_test = [(recommended_premium, 'Recommended Premium')]
    elif choice == "4":
        profiles_to_test = [
            (recommended_stable, 'Recommended Stable'),
            (recommended_premium, 'Recommended Premium')
        ]
    elif choice == "5":
        profiles_to_test = [(name, profile.description) for name, profile in optimizer.profiles.items()]
    else:
        print("❌ Invalid choice, using recommended stable")
        profiles_to_test = [(recommended_stable, 'Recommended Stable')]
    
    # Remove duplicates
    seen = set()
    unique_profiles = []
    for profile, desc in profiles_to_test:
        if profile not in seen:
            unique_profiles.append((profile, desc))
            seen.add(profile)
    
    profiles_to_test = unique_profiles
    
    print(f"\n🎯 Will test {len(profiles_to_test)} profiles:")
    for profile, desc in profiles_to_test:
        print(f"   • {profile}: {desc}")
    
    print(f"\n📁 Output directory: {output_dir}")
    
    # Test từng profile
    results = []
    
    for profile_name, description in profiles_to_test:
        print(f"\n{'='*60}")
        print(f"🔄 Testing Profile: {profile_name}")
        print(f"📝 Description: {description}")
        print(f"{'='*60}")
        
        output_path = os.path.join(output_dir, f"{input_name}_{profile_name}.mp4")
        
        # Optimize
        start_time = time.time()
        success, result = optimizer.optimize_video(input_video, output_path, profile_name)
        total_time = time.time() - start_time
        
        if success:
            print(f"✅ SUCCESS - {profile_name}")
            print(f"   ⏱️ Total time: {total_time:.1f}s")
            print(f"   📦 Output size: {result.get('output_size_mb', 0):.1f}MB")
            print(f"   🎮 GPU used: {result.get('gpu_used', False)}")
            
            # Test stream compatibility
            print(f"   🧪 Testing stream compatibility...")
            test_result = optimizer.test_stream_compatibility(output_path)
            
            compatible = test_result.get('compatible', False)
            print(f"   {'✅' if compatible else '❌'} Stream compatible: {compatible}")
            
            results.append({
                'profile': profile_name,
                'success': True,
                'time': total_time,
                'size_mb': result.get('output_size_mb', 0),
                'gpu_used': result.get('gpu_used', False),
                'compatible': compatible,
                'output_path': output_path
            })
            
        else:
            print(f"❌ FAILED - {profile_name}")
            print(f"   Error: {result.get('error', 'Unknown error')}")
            
            results.append({
                'profile': profile_name,
                'success': False,
                'error': result.get('error', 'Unknown error')
            })
    
    # Summary
    print(f"\n{'='*60}")
    print("📊 TEST SUMMARY")
    print(f"{'='*60}")
    
    successful_profiles = [r for r in results if r.get('success')]
    
    if successful_profiles:
        print(f"✅ Successful profiles: {len(successful_profiles)}/{len(results)}")
        print()
        
        for result in successful_profiles:
            print(f"🎯 {result['profile']}:")
            print(f"   ⏱️ Time: {result['time']:.1f}s")
            print(f"   📦 Size: {result['size_mb']:.1f}MB")
            print(f"   🎮 GPU: {result['gpu_used']}")
            print(f"   🧪 Compatible: {result['compatible']}")
            print(f"   📁 File: {result['output_path']}")
            print()
        
        # Recommend best profile
        compatible_profiles = [r for r in successful_profiles if r.get('compatible')]
        if compatible_profiles:
            best_profile = min(compatible_profiles, key=lambda x: x['time'])
            print(f"🏆 RECOMMENDED FOR PRODUCTION: {best_profile['profile']}")
            print(f"   Fastest compatible render: {best_profile['time']:.1f}s")
            print(f"   Output: {best_profile['output_path']}")
            
            print(f"\n🎬 NEXT STEPS:")
            print(f"1. Test video {best_profile['output_path']} với hệ thống stream hiện tại")
            print(f"2. Chạy stream loop 24h để verify stability:")
            print(f"   ffmpeg -re -stream_loop -1 -i {best_profile['output_path']} -c copy -f flv rtmp://your-endpoint")
            print(f"3. Nếu OK → Triển khai vast.ai integration")
        else:
            print("⚠️ Không có profile nào compatible với stream loop!")
            print("🔧 Cần điều chỉnh settings")
        
    else:
        print("❌ Không có profile nào thành công!")
        print("🔧 Cần check:")
        print("   • FFmpeg installation")
        print("   • GPU drivers (CUDA)")
        print("   • Input video format")


if __name__ == '__main__':
    main()
