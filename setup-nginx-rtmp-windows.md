# 🔧 Setup Nginx RTMP cho Windows/Laragon

## Vấn đề hiện tại:
- Stream không khởi động được vì lỗi: `Error opening output rtmp://127.0.0.1:1935/stream_94/stream_94: Input/output error`
- Exit code 251: FFmpeg không thể kết nối đến RTMP server
- Port 1935 không mở vì Nginx chưa có RTMP module

## Giải pháp:

### 1. Download Nginx với RTMP module
```bash
# Tải nginx-rtmp-module build cho Windows
# Hoặc sử dụng nginx-rtmp-win32 pre-built
```

### 2. Cấu hình Nginx RTMP

Thêm vào `nginx.conf` (thường ở `C:\laragon\bin\nginx\nginx-1.x.x\conf\nginx.conf`):

```nginx
# Thêm vào đầu file, ngoài http block
rtmp {
    server {
        listen 1935;
        chunk_size 4096;
        allow play all;
        
        # Include dynamic stream configs
        include rtmp-apps/*.conf;
        
        # Default application
        application live {
            live on;
            record off;
            allow play all;
            allow publish all;
        }
    }
}

http {
    # ... existing http config ...
}
```

### 3. Tạo thư mục rtmp-apps
```bash
mkdir C:\laragon\bin\nginx\nginx-1.x.x\conf\rtmp-apps
```

### 4. Test cấu hình
```bash
nginx -t
nginx -s reload
```

### 5. Kiểm tra port 1935
```bash
netstat -an | findstr :1935
```

## Giải pháp tạm thời cho Local Development:

### Option 1: Mock RTMP Server
Tạo một mock RTMP server đơn giản để test local:

```python
# mock_rtmp_server.py
import socket
import threading

def handle_client(client_socket):
    try:
        # Simple RTMP handshake mock
        data = client_socket.recv(1024)
        client_socket.send(b"RTMP OK")
        client_socket.close()
    except:
        pass

def start_mock_rtmp():
    server = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    server.bind(('127.0.0.1', 1935))
    server.listen(5)
    print("🎭 Mock RTMP server listening on port 1935")
    
    while True:
        client, addr = server.accept()
        threading.Thread(target=handle_client, args=(client,)).start()

if __name__ == "__main__":
    start_mock_rtmp()
```

### Option 2: Disable RTMP cho Local
Sửa agent để skip RTMP push khi ở local development:

```python
# Trong stream_manager.py
if os.getenv('APP_ENV') == 'local':
    # Skip RTMP push for local development
    logging.info("🏠 Local development mode - skipping RTMP push")
    return True
```

### Option 3: Sử dụng File Output thay vì RTMP
```python
# Thay vì push RTMP, ghi ra file để test
rtmp_url = f"test_output_{stream_id}.mp4"
```
