#!/usr/bin/env python3
"""
Video Optimizer Pro - Example Tool with EzStream License Integration
Tool ID: 1 (Video Optimizer Pro)
"""

import os
import sys
import json
import time
from pathlib import Path

# Add parent directory to path to import license_client
parent_dir = Path(__file__).parent.parent
sys.path.insert(0, str(parent_dir))

from license_client import LicenseClient

class VideoOptimizerTool:
    def __init__(self):
        self.tool_config = self.load_config()
        self.tool_id = self.tool_config['tool_id']
        self.tool_name = self.tool_config['tool_name']
        self.version = self.tool_config['tool_version']
        self.license_client = None
        
    def load_config(self):
        """Load tool configuration"""
        config_path = Path(__file__).parent / "tool_config.json"
        with open(config_path, 'r') as f:
            return json.load(f)
    
    def print_banner(self):
        """Print tool banner"""
        print("=" * 60)
        print(f"🎬 {self.tool_name} v{self.version}")
        print("=" * 60)
        print("🚀 Professional Video Optimization Tool")
        print("📧 Support: support@ezstream.pro")
        print("🌐 Website: https://ezstream.pro")
        print("=" * 60)
        print()
    
    def get_license_key(self):
        """Get license key from various sources"""
        # 1. Environment variable
        license_key = os.environ.get('EZSTREAM_LICENSE_KEY')
        if license_key:
            print("🔑 Using license key from environment variable")
            return license_key.strip()
        
        # 2. License file in user home
        license_file = Path.home() / '.ezstream' / f'tool_{self.tool_id}_license.txt'
        if license_file.exists():
            try:
                with open(license_file, 'r') as f:
                    license_key = f.read().strip()
                    if license_key:
                        print(f"🔑 Using saved license key from {license_file}")
                        return license_key
            except Exception as e:
                print(f"⚠️ Error reading license file: {e}")
        
        # 3. Command line argument
        if len(sys.argv) > 1 and sys.argv[1].startswith('--license='):
            license_key = sys.argv[1].split('=', 1)[1].strip()
            if license_key:
                print("🔑 Using license key from command line")
                return license_key
        
        # 4. Interactive input
        print("🔑 License Key Required")
        print("-" * 30)
        print("Please enter your license key for Video Optimizer Pro")
        print("Format: XXXX-XXXX-XXXX-XXXX")
        print()
        
        license_key = input("License Key: ").strip()
        
        if license_key:
            # Save for future use
            self.save_license_key(license_key)
            return license_key
        
        return None
    
    def save_license_key(self, license_key):
        """Save license key for future use"""
        try:
            license_dir = Path.home() / '.ezstream'
            license_dir.mkdir(exist_ok=True)
            
            license_file = license_dir / f'tool_{self.tool_id}_license.txt'
            with open(license_file, 'w') as f:
                f.write(license_key)
            
            print(f"💾 License key saved to {license_file}")
        except Exception as e:
            print(f"⚠️ Could not save license key: {e}")
    
    def verify_license(self):
        """Verify license with EzStream server"""
        license_key = self.get_license_key()
        
        if not license_key:
            print("❌ No license key provided!")
            return False
        
        # Validate license key format
        if not self.validate_license_format(license_key):
            print("❌ Invalid license key format!")
            print("Expected format: XXXX-XXXX-XXXX-XXXX")
            return False
        
        # Initialize license client with tool ID
        self.license_client = LicenseClient(
            license_key=license_key,
            tool_id=self.tool_id  # This ensures license is for Video Optimizer Pro
        )
        
        print(f"🔐 Verifying license for {self.tool_name}...")
        print(f"📱 Tool ID: {self.tool_id}")
        print(f"🖥️ Device: {self.license_client.device_name}")
        print(f"🆔 Device ID: {self.license_client.device_id}")
        print()
        
        # Verify with retry mechanism
        if self.license_client.verify_with_retry(max_retries=3, delay=2):
            print("✅ License verified successfully!")
            
            # Get and display license information
            is_valid, license_data = self.license_client.check_status()
            if is_valid and license_data:
                self.display_license_info(license_data)
            
            return True
        else:
            print("❌ License verification failed!")
            self.show_license_help()
            return False
    
    def validate_license_format(self, license_key):
        """Validate license key format"""
        import re
        pattern = r'^[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$'
        return bool(re.match(pattern, license_key.upper()))
    
    def display_license_info(self, license_data):
        """Display license information"""
        print("\n📋 License Information:")
        print("-" * 40)
        
        if 'tool' in license_data:
            tool = license_data['tool']
            print(f"🛠️ Tool: {tool.get('name', 'Unknown')}")
            print(f"💰 Price: ${tool.get('price', 'N/A')}")
        
        if 'activated_at' in license_data:
            print(f"📅 Activated: {license_data['activated_at']}")
        
        if 'expires_at' in license_data:
            if license_data['expires_at']:
                print(f"⏰ Expires: {license_data['expires_at']}")
            else:
                print("⏰ Expires: Never (Lifetime License)")
        
        if 'device_name' in license_data:
            print(f"📱 Device: {license_data['device_name']}")
        
        print("-" * 40)
        print()
    
    def show_license_help(self):
        """Show help for license issues"""
        print("\n💡 License Troubleshooting:")
        print("-" * 30)
        print("1. ✅ Check license key format: XXXX-XXXX-XXXX-XXXX")
        print("2. 🌐 Ensure internet connection is working")
        print("3. 🔄 Try again in a few minutes")
        print("4. 📧 Contact support: support@ezstream.pro")
        print(f"5. 🛒 Purchase license: https://ezstream.pro/tools/video-optimizer")
        print()
    
    def run_optimization(self):
        """Main video optimization logic"""
        print("🎯 Starting Video Optimization Process...")
        print("=" * 50)
        
        # Get input files
        input_files = self.get_input_files()
        
        if not input_files:
            print("❌ No input files specified!")
            return False
        
        print(f"📁 Found {len(input_files)} file(s) to process:")
        for i, file_path in enumerate(input_files, 1):
            print(f"   {i}. {file_path}")
        print()
        
        # Process each file
        success_count = 0
        for i, file_path in enumerate(input_files, 1):
            print(f"[{i}/{len(input_files)}] Processing: {Path(file_path).name}")
            
            # Periodic license check during processing
            if not self.check_license_periodic():
                print("⚠️ License check failed during processing!")
                break
            
            # Simulate video processing
            if self.process_video_file(file_path):
                success_count += 1
                print(f"✅ Completed: {Path(file_path).name}")
            else:
                print(f"❌ Failed: {Path(file_path).name}")
            
            print()
        
        # Summary
        print("📊 Processing Summary:")
        print(f"   ✅ Successful: {success_count}")
        print(f"   ❌ Failed: {len(input_files) - success_count}")
        print(f"   📈 Success Rate: {(success_count/len(input_files)*100):.1f}%")
        
        return success_count > 0
    
    def get_input_files(self):
        """Get input files from command line or user input"""
        # Check command line arguments (skip --license= argument)
        args = [arg for arg in sys.argv[1:] if not arg.startswith('--license=')]
        
        if args:
            # Validate files exist
            valid_files = []
            for file_path in args:
                if os.path.exists(file_path):
                    valid_files.append(file_path)
                else:
                    print(f"⚠️ File not found: {file_path}")
            return valid_files
        
        # Interactive mode
        print("📁 Enter video files to optimize:")
        print("   (Enter file paths one by one, empty line to finish)")
        print()
        
        files = []
        while True:
            file_path = input(f"File {len(files)+1}: ").strip()
            if not file_path:
                break
            
            if os.path.exists(file_path):
                files.append(file_path)
                print(f"   ✅ Added: {Path(file_path).name}")
            else:
                print(f"   ❌ File not found: {file_path}")
        
        return files
    
    def process_video_file(self, file_path):
        """Simulate video processing"""
        try:
            # Simulate processing time
            print(f"   🔄 Analyzing video properties...")
            time.sleep(0.5)
            
            print(f"   ⚙️ Applying optimization algorithms...")
            time.sleep(1.0)
            
            print(f"   💾 Encoding optimized video...")
            time.sleep(1.5)
            
            # In real implementation, you would:
            # 1. Use ffmpeg or similar to process video
            # 2. Apply compression algorithms
            # 3. Save optimized output
            
            output_path = self.get_output_path(file_path)
            print(f"   📁 Output: {output_path}")
            
            return True
            
        except Exception as e:
            print(f"   ❌ Error processing {file_path}: {e}")
            return False
    
    def get_output_path(self, input_path):
        """Generate output file path"""
        input_file = Path(input_path)
        output_dir = input_file.parent / "optimized"
        output_dir.mkdir(exist_ok=True)
        
        output_name = f"{input_file.stem}_optimized{input_file.suffix}"
        return output_dir / output_name
    
    def check_license_periodic(self):
        """Periodic license check during tool usage"""
        if not self.license_client:
            return False
        
        try:
            is_valid, _ = self.license_client.check_status()
            return is_valid
        except:
            # If check fails due to network issues, allow tool to continue
            return True
    
    def run(self):
        """Main entry point"""
        try:
            self.print_banner()
            
            # Verify license first
            if not self.verify_license():
                print("🚫 Cannot start tool without valid license!")
                print("💡 Purchase your license at: https://ezstream.pro/tools/video-optimizer")
                return 1
            
            # Run the optimization
            if self.run_optimization():
                print("\n🎉 Video optimization completed successfully!")
                return 0
            else:
                print("\n❌ Video optimization failed!")
                return 1
                
        except KeyboardInterrupt:
            print("\n\n⚠️ Process interrupted by user")
            return 130
        except Exception as e:
            print(f"\n❌ Unexpected error: {e}")
            return 1

def main():
    """Entry point for the tool"""
    tool = VideoOptimizerTool()
    exit_code = tool.run()
    sys.exit(exit_code)

if __name__ == "__main__":
    main()
