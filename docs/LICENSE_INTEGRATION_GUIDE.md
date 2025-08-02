# 🔑 EzStream License System Integration Guide

Hướng dẫn tích hợp license verification vào Python tools của EzStream.

## 📋 Tổng quan

EzStream License System cho phép:
- ✅ Xác thực license keys cho Python tools
- 🔒 Ràng buộc license với thiết bị cụ thể
- 🔄 Chuyển license giữa các thiết bị
- 📊 Theo dõi việc sử dụng license
- ⏰ Quản lý thời hạn license

## 🚀 Quick Start

### 1. Cài đặt Dependencies

```bash
pip install requests
```

### 2. Download License Client

```bash
# Download license_client.py từ EzStream
wget https://ezstream.com/downloads/license_client.py
```

### 3. Tích hợp vào Tool

```python
from license_client import LicenseClient

def main():
    # Khởi tạo license client
    license_key = "XXXX-XXXX-XXXX-XXXX"  # License key từ user
    client = LicenseClient(license_key)
    
    # Verify license
    if not client.verify():
        print("❌ License không hợp lệ!")
        exit(1)
    
    print("✅ License hợp lệ! Tool đang chạy...")
    
    # Code tool của bạn ở đây
    run_your_tool()

if __name__ == "__main__":
    main()
```

## 📚 API Documentation

### LicenseClient Class

#### Constructor

```python
client = LicenseClient(license_key, api_base_url="https://ezstream.com/api")
```

**Parameters:**
- `license_key` (str): License key từ EzStream
- `api_base_url` (str): Base URL của API (optional)

#### Methods

##### verify() → bool

Xác thực và kích hoạt license trên thiết bị hiện tại.

```python
if client.verify():
    print("License hợp lệ!")
else:
    print("License không hợp lệ!")
```

**Returns:** `True` nếu license hợp lệ và được kích hoạt thành công.

##### check_status() → Tuple[bool, Optional[Dict]]

Kiểm tra trạng thái license mà không kích hoạt.

```python
is_valid, data = client.check_status()
if is_valid:
    print(f"Tool: {data['tool']['name']}")
    print(f"Activated: {data['activated_at']}")
```

##### deactivate() → bool

Hủy kích hoạt license khỏi thiết bị hiện tại.

```python
if client.deactivate():
    print("License đã được hủy kích hoạt")
```

##### verify_with_retry(max_retries=3, delay=1.0) → bool

Xác thực license với cơ chế retry.

```python
if client.verify_with_retry(max_retries=5, delay=2.0):
    print("License hợp lệ sau khi retry!")
```

## 🛠️ Advanced Usage

### 1. Custom Error Handling

```python
from license_client import LicenseClient
import sys

def verify_license_with_custom_handling(license_key):
    client = LicenseClient(license_key)
    
    try:
        if client.verify():
            return True
    except Exception as e:
        print(f"❌ License verification error: {e}")
        
        # Cho phép user nhập license key mới
        new_key = input("Nhập license key mới: ")
        if new_key:
            client.license_key = new_key
            return client.verify()
    
    return False
```

### 2. License Status Monitoring

```python
import time
from license_client import LicenseClient

def monitor_license_status(license_key, check_interval=300):
    """Monitor license status every 5 minutes"""
    client = LicenseClient(license_key)
    
    while True:
        is_valid, data = client.check_status()
        if not is_valid:
            print("⚠️ License không còn hợp lệ!")
            break
        
        print(f"✅ License OK - Tool: {data['tool']['name']}")
        time.sleep(check_interval)
```

### 3. Offline License Caching

```python
import json
import os
from datetime import datetime, timedelta

class CachedLicenseClient:
    def __init__(self, license_key, cache_duration_hours=24):
        self.client = LicenseClient(license_key)
        self.cache_file = f".license_cache_{self.client.device_id}.json"
        self.cache_duration = timedelta(hours=cache_duration_hours)
    
    def verify_with_cache(self):
        # Kiểm tra cache trước
        if self._is_cache_valid():
            print("✅ Using cached license verification")
            return True
        
        # Verify online
        if self.client.verify():
            self._save_cache()
            return True
        
        return False
    
    def _is_cache_valid(self):
        if not os.path.exists(self.cache_file):
            return False
        
        try:
            with open(self.cache_file, 'r') as f:
                cache_data = json.load(f)
            
            cached_time = datetime.fromisoformat(cache_data['timestamp'])
            return datetime.now() - cached_time < self.cache_duration
        except:
            return False
    
    def _save_cache(self):
        cache_data = {
            'timestamp': datetime.now().isoformat(),
            'license_key': self.client.license_key,
            'device_id': self.client.device_id
        }
        
        with open(self.cache_file, 'w') as f:
            json.dump(cache_data, f)
```

## 🔧 Command Line Usage

License client có thể được sử dụng từ command line:

```bash
# Verify license
python license_client.py "XXXX-XXXX-XXXX-XXXX" verify

# Check status
python license_client.py "XXXX-XXXX-XXXX-XXXX" status

# Deactivate license
python license_client.py "XXXX-XXXX-XXXX-XXXX" deactivate
```

## 🚨 Error Handling

### Common Error Codes

| Status Code | Meaning | Action |
|-------------|---------|---------|
| 200 | Success | License hợp lệ |
| 404 | License not found | Kiểm tra license key |
| 403 | License expired | Gia hạn license |
| 409 | Already activated | Deactivate trước khi activate |
| 500 | Server error | Thử lại sau |

### Error Messages

```python
def handle_license_error(client):
    if not client.verify():
        # Kiểm tra lý do cụ thể
        is_valid, data = client.check_status()
        
        if not is_valid:
            print("Possible reasons:")
            print("1. License key không đúng")
            print("2. License đã hết hạn") 
            print("3. License đã được kích hoạt trên thiết bị khác")
            print("4. Không có kết nối internet")
            
            # Hướng dẫn user
            print("\nSolutions:")
            print("- Kiểm tra license key")
            print("- Liên hệ support để gia hạn")
            print("- Deactivate license trên thiết bị cũ")
```

## 🔒 Security Best Practices

### 1. Protect License Keys

```python
import os
from cryptography.fernet import Fernet

def encrypt_license_key(license_key, password):
    """Encrypt license key with user password"""
    key = Fernet.generate_key()
    f = Fernet(key)
    encrypted = f.encrypt(license_key.encode())
    return encrypted, key

def decrypt_license_key(encrypted_key, key):
    """Decrypt license key"""
    f = Fernet(key)
    return f.decrypt(encrypted_key).decode()
```

### 2. Environment Variables

```python
import os

def get_license_key():
    # Ưu tiên environment variable
    license_key = os.environ.get('EZSTREAM_LICENSE_KEY')
    
    if not license_key:
        # Fallback to user input
        license_key = input("Enter license key: ")
    
    return license_key
```

## 📞 Support

- 📧 Email: support@ezstream.com
- 💬 Discord: [EzStream Community](https://discord.gg/ezstream)
- 📖 Documentation: https://docs.ezstream.com
- 🐛 Bug Reports: https://github.com/ezstream/issues

## 📝 License

This integration guide is part of EzStream License System.
© 2024 EzStream. All rights reserved.
