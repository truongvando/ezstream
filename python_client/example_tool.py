#!/usr/bin/env python3
"""
Example Python Tool với EzStream License Integration
Ví dụ cách tích hợp license vào tool thực tế
"""

import os
import sys
import time
import json
from license_client import LicenseClient

class ExampleTool:
    def __init__(self):
        self.license_client = None
        self.license_verified = False
        self.tool_name = "EzStream Example Tool"
        self.version = "1.0.0"
        
    def print_banner(self):
        """In banner của tool"""
        print("=" * 60)
        print(f"🚀 {self.tool_name} v{self.version}")
        print("=" * 60)
        print("📧 Support: support@ezstream.com")
        print("🌐 Website: https://ezstream.com")
        print("=" * 60)
        print()
    
    def get_license_key(self):
        """Lấy license key từ nhiều nguồn"""
        # 1. Thử từ environment variable
        license_key = os.environ.get('EZSTREAM_LICENSE_KEY')
        if license_key:
            print("🔑 Found license key from environment variable")
            return license_key.strip()
        
        # 2. Thử từ file license.txt
        try:
            if os.path.exists('license.txt'):
                with open('license.txt', 'r') as f:
                    license_key = f.read().strip()
                    if license_key:
                        print("🔑 Found license key from license.txt")
                        return license_key
        except Exception as e:
            print(f"⚠️ Error reading license.txt: {e}")
        
        # 3. Thử từ command line argument
        if len(sys.argv) > 1:
            license_key = sys.argv[1].strip()
            if license_key and len(license_key) > 10:  # Basic validation
                print("🔑 Found license key from command line")
                return license_key
        
        # 4. Yêu cầu user nhập
        print("🔑 Please enter your license key:")
        license_key = input("License Key: ").strip()
        
        if license_key:
            # Lưu vào file để lần sau không cần nhập lại
            try:
                with open('license.txt', 'w') as f:
                    f.write(license_key)
                print("💾 License key saved to license.txt")
            except:
                pass
            return license_key
        
        return None
    
    def verify_license(self):
        """Verify license với error handling"""
        print("🔐 Verifying license...")
        
        license_key = self.get_license_key()
        if not license_key:
            print("❌ No license key provided!")
            return False
        
        # Validate license key format
        if not self.validate_license_format(license_key):
            print("❌ Invalid license key format! Expected: XXXX-XXXX-XXXX-XXXX")
            return False
        
        # Initialize license client
        self.license_client = LicenseClient(license_key)
        
        print(f"📱 Device ID: {self.license_client.device_id}")
        print(f"💻 Device Name: {self.license_client.device_name}")
        print()
        
        # Verify với retry
        if self.license_client.verify_with_retry(max_retries=3):
            self.license_verified = True
            print("✅ License verification successful!")
            
            # Get license info
            is_valid, license_data = self.license_client.check_status()
            if is_valid and license_data:
                self.print_license_info(license_data)
            
            return True
        else:
            print("❌ License verification failed!")
            print("\n💡 Troubleshooting:")
            print("   - Check your internet connection")
            print("   - Verify license key is correct")
            print("   - Contact support if issue persists")
            return False
    
    def validate_license_format(self, license_key):
        """Validate license key format"""
        import re
        pattern = r'^[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$'
        return bool(re.match(pattern, license_key.upper()))
    
    def print_license_info(self, license_data):
        """In thông tin license"""
        print("\n📋 License Information:")
        if 'tool' in license_data:
            tool = license_data['tool']
            print(f"   🛠️ Tool: {tool.get('name', 'Unknown')}")
            print(f"   💰 Price: ${tool.get('price', 'N/A')}")
        
        if 'activated_at' in license_data:
            print(f"   📅 Activated: {license_data['activated_at']}")
        
        if 'expires_at' in license_data:
            if license_data['expires_at']:
                print(f"   ⏰ Expires: {license_data['expires_at']}")
            else:
                print("   ⏰ Expires: Never (Lifetime)")
        print()
    
    def run_tool_logic(self):
        """Main tool functionality - thay thế bằng logic thực tế"""
        print("🚀 Starting tool execution...")
        print()
        
        # Simulate tool work
        tasks = [
            "Initializing components...",
            "Loading configuration...",
            "Connecting to services...",
            "Processing data...",
            "Generating results...",
            "Finalizing output..."
        ]
        
        for i, task in enumerate(tasks, 1):
            print(f"[{i}/{len(tasks)}] {task}")
            time.sleep(1)  # Simulate work
            
            # Periodic license check (optional)
            if i % 3 == 0:  # Check every 3 steps
                if not self.periodic_license_check():
                    print("❌ License check failed during execution!")
                    return False
        
        print("\n✅ Tool execution completed successfully!")
        return True
    
    def periodic_license_check(self):
        """Check license status định kỳ (optional)"""
        if not self.license_client:
            return False
        
        try:
            is_valid, _ = self.license_client.check_status()
            return is_valid
        except:
            # Nếu không check được (network issue), vẫn cho phép tiếp tục
            return True
    
    def cleanup(self):
        """Cleanup khi tool kết thúc"""
        print("\n🧹 Cleaning up...")
        # Cleanup code here
        print("✅ Cleanup completed")
    
    def run(self):
        """Main entry point"""
        try:
            self.print_banner()
            
            # Verify license
            if not self.verify_license():
                print("\n❌ Cannot start tool without valid license!")
                return 1
            
            print("\n" + "="*60)
            print("🎯 TOOL EXECUTION")
            print("="*60)
            
            # Run main tool logic
            success = self.run_tool_logic()
            
            if success:
                print("\n🎉 Tool completed successfully!")
                return 0
            else:
                print("\n❌ Tool execution failed!")
                return 1
                
        except KeyboardInterrupt:
            print("\n\n⚠️ Tool interrupted by user")
            return 130
        except Exception as e:
            print(f"\n❌ Unexpected error: {e}")
            return 1
        finally:
            self.cleanup()

def main():
    """Entry point"""
    tool = ExampleTool()
    exit_code = tool.run()
    sys.exit(exit_code)

if __name__ == "__main__":
    main()
