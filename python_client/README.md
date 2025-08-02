# 🔑 EzStream License Client for Python Tools

Python library để tích hợp license verification vào Python tools.

## 📦 Installation

```bash
# Download files
wget https://ezstream.com/downloads/license_client.py
wget https://ezstream.com/downloads/example_tool.py

# Install dependencies
pip install requests
```

## 🚀 Quick Start

### Basic Usage

```python
from license_client import LicenseClient

# Initialize với license key
client = LicenseClient("ABCD-EFGH-IJKL-MNOP")

# Verify license
if client.verify():
    print("✅ License valid! Tool can run.")
    # Your tool code here
else:
    print("❌ License invalid!")
    exit(1)
```

### Advanced Usage

```python
from license_client import LicenseClient
import sys

def main():
    # Get license key từ environment hoặc user input
    license_key = os.environ.get('EZSTREAM_LICENSE_KEY') or input("License Key: ")
    
    # Initialize client
    client = LicenseClient(license_key)
    
    # Verify với retry
    if client.verify_with_retry(max_retries=3):
        print("✅ License verified!")
        
        # Check license status
        is_valid, data = client.check_status()
        if is_valid:
            print(f"Tool: {data['tool']['name']}")
            print(f"Activated: {data['activated_at']}")
        
        # Run your tool
        run_your_tool()
    else:
        print("❌ License verification failed!")
        sys.exit(1)

if __name__ == "__main__":
    main()
```

## 🔧 API Methods

### `LicenseClient(license_key, server_url)`

Initialize license client.

**Parameters:**
- `license_key` (str): License key format XXXX-XXXX-XXXX-XXXX
- `server_url` (str): EzStream server URL (default: https://ezstream.com)

### `verify(timeout=30)`

Verify và activate license.

**Returns:** `bool` - True nếu license hợp lệ

### `verify_with_retry(max_retries=3, delay=2)`

Verify với retry mechanism.

**Parameters:**
- `max_retries` (int): Số lần retry tối đa
- `delay` (int): Delay giữa các lần retry (seconds)

**Returns:** `bool` - True nếu verification thành công

### `check_status(timeout=30)`

Check license status không activate.

**Returns:** `Tuple[bool, Dict]` - (is_valid, license_data)

### `deactivate(timeout=30)`

Deactivate license từ device hiện tại.

**Returns:** `bool` - True nếu deactivation thành công

## 🎯 Example Tool

Chạy example tool:

```bash
# Với license key từ command line
python example_tool.py "ABCD-EFGH-IJKL-MNOP"

# Với license key từ environment
export EZSTREAM_LICENSE_KEY="ABCD-EFGH-IJKL-MNOP"
python example_tool.py

# Với license key từ file
echo "ABCD-EFGH-IJKL-MNOP" > license.txt
python example_tool.py

# Interactive input
python example_tool.py
```

## 🔧 Command Line Testing

Test license từ command line:

```bash
# Verify license
python license_client.py "YOUR-LICENSE-KEY" verify

# Check status
python license_client.py "YOUR-LICENSE-KEY" status

# Deactivate
python license_client.py "YOUR-LICENSE-KEY" deactivate
```

## ⚠️ Error Handling

```python
try:
    client = LicenseClient(license_key)
    if client.verify():
        # Tool code
        pass
    else:
        print("License verification failed")
except ConnectionError:
    print("Cannot connect to license server")
except TimeoutError:
    print("Request timeout")
except Exception as e:
    print(f"Unexpected error: {e}")
```

## 🔒 Security Best Practices

1. **Không hard-code license keys**
   ```python
   # ❌ Bad
   LICENSE_KEY = "ABCD-EFGH-IJKL-MNOP"
   
   # ✅ Good
   LICENSE_KEY = os.environ.get('EZSTREAM_LICENSE_KEY')
   ```

2. **Validate input**
   ```python
   def validate_license_format(key):
       import re
       pattern = r'^[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$'
       return bool(re.match(pattern, key.upper()))
   ```

3. **Handle errors gracefully**
   ```python
   if not client.verify():
       print("License verification failed. Please check:")
       print("- Internet connection")
       print("- License key format")
       print("- Contact support if issue persists")
       sys.exit(1)
   ```

## 🐛 Troubleshooting

### Common Issues

**"License key not found"**
- Check license key format: XXXX-XXXX-XXXX-XXXX
- Verify key is correct
- Contact support

**"License already activated on another device"**
- Deactivate from previous device first
- Or contact admin for transfer

**"Connection error"**
- Check internet connection
- Verify server URL
- Try again later

**"License has expired"**
- Renew license
- Purchase new license

### Debug Mode

Enable debug output:

```python
import logging
logging.basicConfig(level=logging.DEBUG)

client = LicenseClient(license_key)
client.verify()
```

## 📞 Support

- **Email:** support@ezstream.com
- **Discord:** EzStream Community
- **Docs:** https://docs.ezstream.com
- **GitHub:** https://github.com/ezstream/license-client

## 📄 License

This client library is provided under the EzStream License Agreement.
