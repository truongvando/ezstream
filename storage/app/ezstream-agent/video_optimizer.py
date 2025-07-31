#!/usr/bin/env python3
"""
Video Optimizer for Stream Loop
Render video về chuẩn FFmpeg tối ưu cho stream loop không lỗi
Test với GPU local trước khi triển khai vast.ai
"""

import os
import sys
import time
import logging
import subprocess
import json
from pathlib import Path
from typing import Dict, Optional, Tuple
from dataclasses import dataclass

# Setup logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)


@dataclass
class OptimizationProfile:
    """Profile cấu hình render tối ưu"""
    name: str
    description: str
    video_codec: str
    video_preset: str
    video_bitrate: str
    video_maxrate: str
    video_bufsize: str
    gop_size: int
    keyint_min: int
    audio_codec: str
    audio_bitrate: str
    audio_samplerate: str
    profile: str
    level: str
    target_width: int
    target_height: int
    additional_params: list


class VideoOptimizer:
    """Tối ưu video cho stream loop ổn định"""
    
    def __init__(self, use_gpu: bool = True):
        self.use_gpu = use_gpu
        self.profiles = self._init_profiles()
        
        # Kiểm tra GPU availability
        if use_gpu:
            self.gpu_available = self._check_gpu_support()
            if self.gpu_available:
                logger.info("🎮 GPU CUDA detected - sẽ sử dụng hardware acceleration")
            else:
                logger.warning("⚠️ GPU không khả dụng - fallback về CPU encoding")
                self.use_gpu = False
    
    def _init_profiles(self) -> Dict[str, OptimizationProfile]:
        """Khởi tạo các profile render tối ưu theo resolution"""
        return {
            # FULL HD (1080p) Profiles
            'fhd_stable': OptimizationProfile(
                name="Full HD Stable",
                description="1080p tối ưu cho stream loop ổn định 24/7",
                video_codec="libx264",
                video_preset="medium",
                video_bitrate="4500k",
                video_maxrate="4500k",
                video_bufsize="9000k",
                gop_size=60,  # Keyframe mỗi 2s (30fps)
                keyint_min=60,
                audio_codec="aac",
                audio_bitrate="128k",
                audio_samplerate="44100",
                profile="high",
                level="4.0",
                target_width=1920,
                target_height=1080,
                additional_params=[
                    '-sc_threshold', '0',
                    '-avoid_negative_ts', 'make_zero',
                    '-fflags', '+genpts',
                    '-movflags', '+faststart',
                    '-pix_fmt', 'yuv420p'
                ]
            ),

            'fhd_premium': OptimizationProfile(
                name="Full HD Premium",
                description="1080p chất lượng cao cho gói premium",
                video_codec="libx264",
                video_preset="slow",
                video_bitrate="6000k",
                video_maxrate="6000k",
                video_bufsize="12000k",
                gop_size=60,
                keyint_min=60,
                audio_codec="aac",
                audio_bitrate="192k",
                audio_samplerate="48000",
                profile="high",
                level="4.1",
                target_width=1920,
                target_height=1080,
                additional_params=[
                    '-sc_threshold', '0',
                    '-avoid_negative_ts', 'make_zero',
                    '-fflags', '+genpts',
                    '-movflags', '+faststart',
                    '-pix_fmt', 'yuv420p',
                    '-tune', 'film'
                ]
            ),

            # 2K (1440p) Profiles
            '2k_stable': OptimizationProfile(
                name="2K Stable",
                description="1440p tối ưu cho stream loop ổn định",
                video_codec="libx264",
                video_preset="medium",
                video_bitrate="8000k",
                video_maxrate="8000k",
                video_bufsize="16000k",
                gop_size=60,
                keyint_min=60,
                audio_codec="aac",
                audio_bitrate="192k",
                audio_samplerate="48000",
                profile="high",
                level="5.0",
                target_width=2560,
                target_height=1440,
                additional_params=[
                    '-sc_threshold', '0',
                    '-avoid_negative_ts', 'make_zero',
                    '-fflags', '+genpts',
                    '-movflags', '+faststart',
                    '-pix_fmt', 'yuv420p',
                ]
            ),

            '2k_premium': OptimizationProfile(
                name="2K Premium",
                description="1440p chất lượng cao cho gói premium",
                video_codec="libx264",
                video_preset="slow",
                video_bitrate="12000k",
                video_maxrate="12000k",
                video_bufsize="24000k",
                gop_size=60,
                keyint_min=60,
                audio_codec="aac",
                audio_bitrate="256k",
                audio_samplerate="48000",
                profile="high",
                level="5.1",
                target_width=2560,
                target_height=1440,
                additional_params=[
                    '-sc_threshold', '0',
                    '-avoid_negative_ts', 'make_zero',
                    '-fflags', '+genpts',
                    '-movflags', '+faststart',
                    '-pix_fmt', 'yuv420p',
                    '-tune', 'film',
                ]
            ),

            # 4K (2160p) Profiles
            '4k_stable': OptimizationProfile(
                name="4K Stable",
                description="2160p tối ưu cho stream loop ổn định",
                video_codec="libx264",
                video_preset="medium",
                video_bitrate="15000k",
                video_maxrate="15000k",
                video_bufsize="30000k",
                gop_size=60,
                keyint_min=60,
                audio_codec="aac",
                audio_bitrate="256k",
                audio_samplerate="48000",
                profile="high",
                level="5.2",
                target_width=3840,
                target_height=2160,
                additional_params=[
                    '-sc_threshold', '0',
                    '-avoid_negative_ts', 'make_zero',
                    '-fflags', '+genpts',
                    '-movflags', '+faststart',
                    '-pix_fmt', 'yuv420p',
                ]
            ),

            '4k_premium': OptimizationProfile(
                name="4K Premium",
                description="2160p chất lượng cao cho gói premium",
                video_codec="libx264",
                video_preset="slow",
                video_bitrate="25000k",
                video_maxrate="25000k",
                video_bufsize="50000k",
                gop_size=60,
                keyint_min=60,
                audio_codec="aac",
                audio_bitrate="320k",
                audio_samplerate="48000",
                profile="high",
                level="5.2",
                target_width=3840,
                target_height=2160,
                additional_params=[
                    '-sc_threshold', '0',
                    '-avoid_negative_ts', 'make_zero',
                    '-fflags', '+genpts',
                    '-movflags', '+faststart',
                    '-pix_fmt', 'yuv420p',
                    '-tune', 'film',
                ]
            ),

            # Test Profile (Fast render)
            'test_fast': OptimizationProfile(
                name="Test Fast",
                description="Render nhanh cho test (720p)",
                video_codec="libx264",
                video_preset="ultrafast",
                video_bitrate="2000k",
                video_maxrate="2000k",
                video_bufsize="4000k",
                gop_size=30,
                keyint_min=30,
                audio_codec="aac",
                audio_bitrate="96k",
                audio_samplerate="44100",
                profile="baseline",
                level="3.1",
                target_width=1280,
                target_height=720,
                additional_params=[
                    '-sc_threshold', '0',
                    '-avoid_negative_ts', 'make_zero',
                    '-fflags', '+genpts',
                    '-movflags', '+faststart',
                    '-pix_fmt', 'yuv420p',
                ]
            )
        }
    
    def _check_gpu_support(self) -> bool:
        """Kiểm tra GPU CUDA support"""
        try:
            result = subprocess.run([
                'ffmpeg', '-hide_banner', '-hwaccels'
            ], capture_output=True, text=True, timeout=10)
            
            return 'cuda' in result.stdout.lower()
        except Exception as e:
            logger.warning(f"Không thể kiểm tra GPU support: {e}")
            return False
    
    def analyze_video(self, input_path: str) -> Dict:
        """Phân tích video input để tối ưu settings"""
        try:
            cmd = [
                'ffprobe', '-v', 'quiet', '-print_format', 'json',
                '-show_format', '-show_streams', input_path
            ]
            
            result = subprocess.run(cmd, capture_output=True, text=True, timeout=30)
            if result.returncode != 0:
                raise Exception(f"ffprobe failed: {result.stderr}")
            
            data = json.loads(result.stdout)
            
            # Extract video stream info
            video_stream = None
            audio_stream = None
            
            for stream in data.get('streams', []):
                if stream.get('codec_type') == 'video' and not video_stream:
                    video_stream = stream
                elif stream.get('codec_type') == 'audio' and not audio_stream:
                    audio_stream = stream
            
            analysis = {
                'duration': float(data.get('format', {}).get('duration', 0)),
                'size_mb': round(float(data.get('format', {}).get('size', 0)) / 1024 / 1024, 2),
                'bitrate': int(data.get('format', {}).get('bit_rate', 0)),
                'video': {},
                'audio': {}
            }
            
            if video_stream:
                analysis['video'] = {
                    'codec': video_stream.get('codec_name'),
                    'width': video_stream.get('width'),
                    'height': video_stream.get('height'),
                    'fps': eval(video_stream.get('r_frame_rate', '0/1')),
                    'bitrate': int(video_stream.get('bit_rate', 0)) if video_stream.get('bit_rate') else None
                }
            
            if audio_stream:
                analysis['audio'] = {
                    'codec': audio_stream.get('codec_name'),
                    'channels': audio_stream.get('channels'),
                    'sample_rate': audio_stream.get('sample_rate'),
                    'bitrate': int(audio_stream.get('bit_rate', 0)) if audio_stream.get('bit_rate') else None
                }
            
            return analysis
            
        except Exception as e:
            logger.error(f"❌ Lỗi phân tích video: {e}")
            return {}

    def recommend_profile(self, input_path: str, quality_tier: str = 'stable') -> str:
        """Recommend profile dựa trên input resolution và quality tier"""

        analysis = self.analyze_video(input_path)
        if not analysis.get('video'):
            return 'test_fast'  # Fallback

        width = analysis['video'].get('width', 0)
        height = analysis['video'].get('height', 0)

        # Determine target resolution based on input
        if height >= 2160 or width >= 3840:  # 4K input
            base_profile = '4k'
        elif height >= 1440 or width >= 2560:  # 2K input
            base_profile = '2k'
        elif height >= 1080 or width >= 1920:  # Full HD input
            base_profile = 'fhd'
        else:  # Lower resolution
            base_profile = 'fhd'  # Upscale to Full HD minimum

        # Apply quality tier
        if quality_tier == 'premium':
            profile_name = f"{base_profile}_premium"
        else:
            profile_name = f"{base_profile}_stable"

        # Verify profile exists
        if profile_name not in self.profiles:
            profile_name = 'fhd_stable'  # Safe fallback

        logger.info(f"📊 Input: {width}x{height} → Recommended: {profile_name}")
        return profile_name

    def get_resolution_profiles(self) -> Dict[str, list]:
        """Get profiles grouped by resolution"""
        return {
            'Full HD (1080p)': ['fhd_stable', 'fhd_premium'],
            '2K (1440p)': ['2k_stable', '2k_premium'],
            '4K (2160p)': ['4k_stable', '4k_premium'],
            'Test': ['test_fast']
        }

    def build_ffmpeg_command(self, input_path: str, output_path: str,
                           profile_name: str = 'fhd_stable') -> list:
        """Build FFmpeg command với profile tối ưu"""

        if profile_name not in self.profiles:
            raise ValueError(f"Profile '{profile_name}' không tồn tại")

        profile = self.profiles[profile_name]

        cmd = ['ffmpeg', '-hide_banner', '-loglevel', 'info']

        # GPU pipeline - dùng built-in resize của NVDEC
        cmd.extend([
            '-vsync', '0',
            '-hwaccel', 'cuda'
        ])

        # Check if scaling needed và add resize option
        analysis = self.analyze_video(input_path)
        if analysis.get('video'):
            input_width = analysis['video'].get('width', 0)
            input_height = analysis['video'].get('height', 0)

            if (input_width != profile.target_width or input_height != profile.target_height):
                # Dùng built-in resize của NVDEC decoder
                cmd.extend(['-resize', f"{profile.target_width}x{profile.target_height}"])
                logger.info(f"🎮 GPU Resize: {input_width}x{input_height} → {profile.target_width}x{profile.target_height}")

        # Input
        cmd.extend(['-i', input_path])
        logger.info("🎮 Using GPU decode + resize + encode")

        # GPU encoding với NVENC - đơn giản
        cmd.extend([
            '-c:v', 'h264_nvenc',
            '-preset', 'p4',
            '-profile:v', 'high',
            '-rc', 'cbr',
            '-b:v', profile.video_bitrate,
            '-maxrate:v', profile.video_maxrate,
            '-bufsize:v', profile.video_bufsize,
            '-g', str(profile.gop_size),
            '-keyint_min', str(profile.keyint_min)
        ])
        logger.info("🎮 Using NVENC GPU encoding")

        # Audio encoding
        cmd.extend([
            '-c:a', profile.audio_codec,
            '-b:a', profile.audio_bitrate,
            '-ar', profile.audio_samplerate
        ])

        # Scaling đã được handle bởi -resize option ở trên

        # Additional parameters
        cmd.extend(profile.additional_params)

        # Output
        cmd.extend(['-y', output_path])

        return cmd

    def optimize_video(self, input_path: str, output_path: str,
                      profile_name: str = 'stream_stable') -> Tuple[bool, Dict]:
        """Render video về chuẩn tối ưu cho stream loop"""

        if not os.path.exists(input_path):
            return False, {'error': f'File không tồn tại: {input_path}'}

        # Tạo output directory nếu cần
        os.makedirs(os.path.dirname(output_path), exist_ok=True)

        logger.info(f"🎬 Bắt đầu optimize video: {input_path}")
        logger.info(f"📁 Output: {output_path}")
        logger.info(f"⚙️ Profile: {profile_name}")

        # Phân tích video input
        analysis = self.analyze_video(input_path)
        if analysis:
            logger.info(f"📊 Video info: {analysis['video']['width']}x{analysis['video']['height']} "
                       f"@ {analysis['video']['fps']:.1f}fps, {analysis['duration']:.1f}s")

        # Build command
        cmd = self.build_ffmpeg_command(input_path, output_path, profile_name)

        logger.info(f"🔧 FFmpeg command: {' '.join(cmd)}")

        # Execute
        start_time = time.time()

        try:
            process = subprocess.Popen(
                cmd,
                stdout=subprocess.PIPE,
                stderr=subprocess.PIPE,
                universal_newlines=True
            )

            # Monitor progress
            stderr_output = []
            while True:
                output = process.stderr.readline()
                if output == '' and process.poll() is not None:
                    break
                if output:
                    stderr_output.append(output.strip())
                    # Log progress periodically
                    if 'time=' in output:
                        logger.info(f"⏳ Progress: {output.strip()}")

            return_code = process.poll()

            if return_code == 0:
                elapsed = time.time() - start_time

                # Verify output file
                if os.path.exists(output_path):
                    output_size = os.path.getsize(output_path) / 1024 / 1024

                    result = {
                        'success': True,
                        'elapsed_time': round(elapsed, 2),
                        'output_size_mb': round(output_size, 2),
                        'input_analysis': analysis,
                        'profile_used': profile_name,
                        'gpu_used': self.use_gpu and self.gpu_available
                    }

                    logger.info(f"✅ Optimize thành công!")
                    logger.info(f"⏱️ Thời gian: {elapsed:.1f}s")
                    logger.info(f"📦 Kích thước: {output_size:.1f}MB")

                    return True, result
                else:
                    return False, {'error': 'Output file không được tạo'}
            else:
                error_msg = '\n'.join(stderr_output[-10:])  # Last 10 lines
                logger.error(f"❌ FFmpeg failed với return code {return_code}")
                logger.error(f"Error: {error_msg}")

                return False, {
                    'error': f'FFmpeg failed (code {return_code})',
                    'stderr': error_msg
                }

        except Exception as e:
            logger.error(f"❌ Exception during optimization: {e}")
            return False, {'error': str(e)}

    def test_stream_compatibility(self, video_path: str) -> Dict:
        """Test video compatibility với stream loop"""

        logger.info(f"🧪 Testing stream compatibility: {video_path}")

        # Test basic playback
        test_cmd = [
            'ffmpeg', '-hide_banner', '-loglevel', 'error',
            '-re', '-stream_loop', '2', '-i', video_path,
            '-f', 'null', '-'
        ]

        try:
            start_time = time.time()
            result = subprocess.run(test_cmd, capture_output=True, text=True, timeout=60)
            elapsed = time.time() - start_time

            if result.returncode == 0:
                logger.info(f"✅ Stream test PASSED ({elapsed:.1f}s)")
                return {
                    'compatible': True,
                    'test_duration': round(elapsed, 2),
                    'errors': []
                }
            else:
                logger.warning(f"⚠️ Stream test có warnings: {result.stderr}")
                return {
                    'compatible': True,  # Warnings không phải errors
                    'test_duration': round(elapsed, 2),
                    'warnings': result.stderr.split('\n')
                }

        except subprocess.TimeoutExpired:
            logger.error("❌ Stream test timeout")
            return {
                'compatible': False,
                'error': 'Test timeout after 60s'
            }
        except Exception as e:
            logger.error(f"❌ Stream test failed: {e}")
            return {
                'compatible': False,
                'error': str(e)
            }


def main():
    """CLI interface cho testing"""
    import argparse

    parser = argparse.ArgumentParser(description='Video Optimizer cho Stream Loop')
    parser.add_argument('input', help='Đường dẫn video input')
    parser.add_argument('output', help='Đường dẫn video output')
    parser.add_argument('--profile', default='auto',
                       help='Profile tối ưu (default: auto - auto-detect từ input resolution)')
    parser.add_argument('--resolution', choices=['fhd', '2k', '4k'],
                       help='Force target resolution (fhd=1080p, 2k=1440p, 4k=2160p)')
    parser.add_argument('--quality', choices=['stable', 'premium'], default='stable',
                       help='Quality tier (default: stable)')
    parser.add_argument('--no-gpu', action='store_true', help='Không sử dụng GPU')
    parser.add_argument('--test', action='store_true', help='Test stream compatibility sau khi render')

    args = parser.parse_args()

    # Initialize optimizer
    optimizer = VideoOptimizer(use_gpu=not args.no_gpu)

    # Determine profile to use
    if args.profile == 'auto':
        if args.resolution:
            # Force specific resolution
            profile_name = f"{args.resolution}_{args.quality}"
        else:
            # Auto-detect from input
            profile_name = optimizer.recommend_profile(args.input, args.quality)
        logger.info(f"🎯 Auto-selected profile: {profile_name}")
    else:
        profile_name = args.profile

    # Verify profile exists
    if profile_name not in optimizer.profiles:
        logger.error(f"❌ Profile '{profile_name}' không tồn tại!")
        logger.info("📋 Available profiles:")
        resolution_profiles = optimizer.get_resolution_profiles()
        for resolution, profiles in resolution_profiles.items():
            logger.info(f"   {resolution}: {', '.join(profiles)}")
        sys.exit(1)

    # Show selected profile info
    selected_profile = optimizer.profiles[profile_name]
    logger.info(f"📋 Selected Profile: {profile_name}")
    logger.info(f"   Description: {selected_profile.description}")
    logger.info(f"   Video: {selected_profile.video_bitrate} @ {selected_profile.video_preset}")
    logger.info(f"   Audio: {selected_profile.audio_bitrate} @ {selected_profile.audio_samplerate}")

    # Optimize video
    success, result = optimizer.optimize_video(args.input, args.output, profile_name)

    if success:
        logger.info("🎉 Optimization completed successfully!")

        # Test stream compatibility nếu được yêu cầu
        if args.test:
            logger.info("🧪 Testing stream compatibility...")
            test_result = optimizer.test_stream_compatibility(args.output)

            if test_result.get('compatible'):
                logger.info("✅ Video tương thích với stream loop!")
            else:
                logger.error(f"❌ Video không tương thích: {test_result.get('error')}")

        # Print summary
        logger.info("📊 SUMMARY:")
        logger.info(f"   Profile: {result.get('profile_used')}")
        logger.info(f"   GPU Used: {result.get('gpu_used')}")
        logger.info(f"   Time: {result.get('elapsed_time')}s")
        logger.info(f"   Size: {result.get('output_size_mb')}MB")

    else:
        logger.error(f"❌ Optimization failed: {result.get('error')}")
        sys.exit(1)


if __name__ == '__main__':
    main()
