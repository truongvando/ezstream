#!/usr/bin/env python3
"""
Build script for Video Optimizer Pro
Creates distributable package with license integration
"""

import os
import sys
import shutil
import zipfile
import json
from pathlib import Path

def build_tool():
    """Build the Video Optimizer Pro tool package"""
    print("🔨 Building Video Optimizer Pro...")
    print("=" * 50)
    
    # Load tool config
    with open('tool_config.json', 'r') as f:
        config = json.load(f)
    
    tool_name = config['tool_name']
    tool_version = config['tool_version']
    
    # Create build directory
    build_dir = Path('build')
    if build_dir.exists():
        shutil.rmtree(build_dir)
    build_dir.mkdir()
    
    print(f"📁 Created build directory: {build_dir}")
    
    # Copy tool files
    tool_files = [
        'video_optimizer.py',
        'tool_config.json'
    ]
    
    for file_name in tool_files:
        if Path(file_name).exists():
            shutil.copy2(file_name, build_dir)
            print(f"📄 Copied: {file_name}")
        else:
            print(f"❌ Missing file: {file_name}")
            return False
    
    # Copy license client from parent directory
    license_client_source = Path('../license_client.py')
    license_client_dest = build_dir / 'license_client.py'
    
    if license_client_source.exists():
        shutil.copy2(license_client_source, license_client_dest)
        print("📄 Copied: license_client.py")
    else:
        print("❌ license_client.py not found in parent directory!")
        return False
    
    # Create requirements.txt
    requirements = [
        'requests>=2.25.0',
        # Add other dependencies as needed
    ]
    
    requirements_file = build_dir / 'requirements.txt'
    with open(requirements_file, 'w') as f:
        f.write('\n'.join(requirements))
    print("📄 Created: requirements.txt")
    
    # Create installation script
    create_install_script(build_dir, config)
    
    # Create user documentation
    create_user_readme(build_dir, config)
    
    # Create batch/shell scripts for easy execution
    create_run_scripts(build_dir, config)
    
    # Create distribution package
    create_distribution_package(build_dir, config)
    
    print("\n✅ Build completed successfully!")
    print(f"📦 Distribution package: dist/{tool_name.lower().replace(' ', '-')}-{tool_version}.zip")
    
    return True

def create_install_script(build_dir, config):
    """Create installation script"""
    install_script = build_dir / 'install.py'
    
    script_content = f'''#!/usr/bin/env python3
"""
Installation script for {config['tool_name']}
"""

import subprocess
import sys
import os
from pathlib import Path

def check_python_version():
    """Check if Python version meets requirements"""
    min_version = "{config['min_python_version']}"
    current_version = f"{{sys.version_info.major}}.{{sys.version_info.minor}}"
    
    if sys.version_info < tuple(map(int, min_version.split('.'))):
        print(f"❌ Python {{min_version}} or higher required. Current: {{current_version}}")
        return False
    
    print(f"✅ Python version: {{current_version}}")
    return True

def install_dependencies():
    """Install required dependencies"""
    print("📦 Installing dependencies...")
    
    try:
        subprocess.run([sys.executable, '-m', 'pip', 'install', '-r', 'requirements.txt'], check=True)
        print("✅ Dependencies installed successfully!")
        return True
    except subprocess.CalledProcessError as e:
        print(f"❌ Failed to install dependencies: {{e}}")
        return False

def main():
    print("🚀 Installing {config['tool_name']} v{config['tool_version']}")
    print("=" * 60)
    
    # Check Python version
    if not check_python_version():
        return 1
    
    # Install dependencies
    if not install_dependencies():
        return 1
    
    print("\\n✅ Installation completed successfully!")
    print("\\n📋 Next Steps:")
    print("1. Run the tool:")
    print("   • Windows: run_tool.bat")
    print("   • Linux/Mac: ./run_tool.sh")
    print("   • Python: python video_optimizer.py")
    print("\\n2. Enter your license key when prompted")
    print("3. Start optimizing your videos!")
    print("\\n📞 Support:")
    print("   • Email: support@ezstream.pro")
    print("   • Website: https://ezstream.pro")
    
    return 0

if __name__ == "__main__":
    sys.exit(main())
'''
    
    with open(install_script, 'w') as f:
        f.write(script_content)
    
    print("📄 Created: install.py")

def create_user_readme(build_dir, config):
    """Create user documentation"""
    readme_file = build_dir / 'README.txt'
    
    readme_content = f'''
🎬 {config['tool_name']} v{config['tool_version']}
{'=' * 60}

📋 DESCRIPTION:
{config['description']}

🚀 QUICK START:
1. Run installation: python install.py
2. Start the tool: python video_optimizer.py
3. Enter your license key when prompted
4. Select video files to optimize

💻 USAGE EXAMPLES:

Command Line Mode:
  python video_optimizer.py video1.mp4 video2.mp4

With License Key:
  python video_optimizer.py --license=XXXX-XXXX-XXXX-XXXX video.mp4

Interactive Mode:
  python video_optimizer.py
  (Then follow the prompts)

🔑 LICENSE ACTIVATION:
• Purchase license: https://ezstream.pro/tools/video-optimizer
• License format: XXXX-XXXX-XXXX-XXXX
• One license per device
• Lifetime validity

✨ FEATURES:
'''
    
    for feature in config.get('features', []):
        readme_content += f"• {feature}\n"
    
    readme_content += f'''
📁 SUPPORTED FORMATS:
'''
    
    for format_type in config.get('supported_formats', []):
        readme_content += f"• {format_type.upper()}\n"
    
    readme_content += f'''
🔧 REQUIREMENTS:
• Python {config['min_python_version']} or higher
• Internet connection (for license verification)
• Valid EzStream license key

📞 SUPPORT:
• Email: support@ezstream.pro
• Website: https://ezstream.pro
• Documentation: https://docs.ezstream.pro

⚖️ LICENSE:
This software is licensed under the EzStream License Agreement.
Unauthorized distribution or use is prohibited.

© 2025 EzStream Platform. All rights reserved.
'''
    
    with open(readme_file, 'w') as f:
        f.write(readme_content)
    
    print("📄 Created: README.txt")

def create_run_scripts(build_dir, config):
    """Create convenient run scripts"""
    
    # Windows batch script
    bat_script = build_dir / 'run_tool.bat'
    bat_content = f'''@echo off
echo Starting {config['tool_name']}...
python video_optimizer.py %*
pause
'''
    
    with open(bat_script, 'w') as f:
        f.write(bat_content)
    print("📄 Created: run_tool.bat")
    
    # Linux/Mac shell script
    sh_script = build_dir / 'run_tool.sh'
    sh_content = f'''#!/bin/bash
echo "Starting {config['tool_name']}..."
python3 video_optimizer.py "$@"
'''
    
    with open(sh_script, 'w') as f:
        f.write(sh_content)
    
    # Make shell script executable
    os.chmod(sh_script, 0o755)
    print("📄 Created: run_tool.sh")

def create_distribution_package(build_dir, config):
    """Create final distribution ZIP package"""
    dist_dir = Path('dist')
    dist_dir.mkdir(exist_ok=True)
    
    tool_name = config['tool_name'].lower().replace(' ', '-')
    tool_version = config['tool_version']
    zip_filename = f"{tool_name}-{tool_version}.zip"
    zip_path = dist_dir / zip_filename
    
    print(f"📦 Creating distribution package: {zip_filename}")
    
    with zipfile.ZipFile(zip_path, 'w', zipfile.ZIP_DEFLATED) as zipf:
        for file_path in build_dir.rglob('*'):
            if file_path.is_file():
                arcname = file_path.relative_to(build_dir)
                zipf.write(file_path, arcname)
                print(f"   📄 Added: {arcname}")
    
    print(f"✅ Distribution package created: {zip_path}")
    print(f"📊 Package size: {zip_path.stat().st_size / 1024:.1f} KB")
    
    return zip_path

def main():
    """Main build function"""
    if not Path('tool_config.json').exists():
        print("❌ tool_config.json not found!")
        print("Make sure you're running this script from the tool directory.")
        return 1
    
    if build_tool():
        print("\\n🎉 Build process completed successfully!")
        print("\\n📋 Next Steps:")
        print("1. Test the package locally")
        print("2. Upload to EzStream download server")
        print("3. Update database download_url")
        print("4. Test end-to-end user flow")
        return 0
    else:
        print("\\n❌ Build process failed!")
        return 1

if __name__ == "__main__":
    sys.exit(main())
