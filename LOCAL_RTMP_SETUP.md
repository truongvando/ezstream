# HƯỚNG DẪN SETUP RTMP SERVER LOCAL

## 1. WINDOWS - Sử dụng nginx-rtmp

### 1.1 Download nginx-rtmp for Windows
```
1. Download từ: https://github.com/illuspas/nginx-rtmp-win32/releases
2. Extract vào C:\nginx-rtmp
```

### 1.2 Cấu hình nginx.conf
```nginx
worker_processes  1;

events {
    worker_connections  1024;
}

rtmp {
    server {
        listen 1935;
        chunk_size 4096;
        
        application live {
            live on;
            record off;
            
            # Allow publish from localhost only
            allow publish 127.0.0.1;
            deny publish all;
            
            # Allow play from anywhere
            allow play all;
        }
        
        application test {
            live on;
            record off;
            allow publish all;
            allow play all;
        }
    }
}

http {
    server {
        listen 8080;
        
        location /stat {
            rtmp_stat all;
            rtmp_stat_stylesheet stat.xsl;
        }
        
        location /stat.xsl {
            root html;
        }
    }
}
```

### 1.3 Chạy nginx
```bash
cd C:\nginx-rtmp
nginx.exe
```

### 1.4 Test với FFmpeg
```bash
# Stream từ file local
ffmpeg -re -i video.mp4 -c:v libx264 -preset veryfast -c:a aac -f flv rtmp://localhost/live/test

# Stream từ Google Drive
php test_local_streaming.php YOUR_GOOGLE_DRIVE_FILE_ID
```

## 2. LINUX/MAC - Sử dụng Docker

### 2.1 Docker Compose
```yaml
version: '3'
services:
  rtmp:
    image: tiangolo/nginx-rtmp
    ports:
      - "1935:1935"
      - "8080:80"
    volumes:
      - ./nginx.conf:/etc/nginx/nginx.conf
```

### 2.2 Chạy Docker
```bash
docker-compose up -d
```

## 3. SỬ DỤNG OBS STUDIO ĐỂ XEM STREAM

### 3.1 Cài đặt OBS
- Download từ: https://obsproject.com/

### 3.2 Thêm Media Source
1. Click "+" trong Sources
2. Chọn "Media Source"
3. Uncheck "Local File"
4. Input: `rtmp://localhost/live/test`
5. Click OK

### 3.3 Hoặc dùng VLC
1. Mở VLC Media Player
2. Media → Open Network Stream
3. Nhập: `rtmp://localhost/live/test`
4. Play

## 4. TEST VỚI HỆ THỐNG

### 4.1 Test từ Web UI
1. Truy cập: http://localhost/test-google-drive
2. Nhập Google Drive File ID
3. Click "Test Local Streaming"
4. Nhập RTMP URL: `rtmp://localhost/live/mystream`
5. Chọn preset và test

### 4.2 Test từ Command Line
```bash
# Test với file local
php test_local_streaming.php

# Test với Google Drive
php test_local_streaming.php YOUR_FILE_ID
```

### 4.3 Monitor Streaming
- RTMP Stats: http://localhost:8080/stat
- Laravel Logs: `tail -f storage/logs/laravel.log`
- FFmpeg Output: Xem trong console

## 5. STREAMING ĐẾN PLATFORM THẬT

### 5.1 YouTube Live
```
RTMP URL: rtmp://a.rtmp.youtube.com/live2
Stream Key: xxxx-xxxx-xxxx-xxxx (lấy từ YouTube Studio)
```

### 5.2 Facebook Live
```
RTMP URL: rtmps://live-api-s.facebook.com:443/rtmp/
Stream Key: Lấy từ Facebook Creator Studio
```

### 5.3 Twitch
```
RTMP URL: rtmp://live.twitch.tv/app
Stream Key: live_xxxxxxxxx_xxxxxxxxxxxx
```

## 6. TROUBLESHOOTING

### Lỗi: Connection refused
- Kiểm tra nginx-rtmp đang chạy
- Kiểm tra port 1935 không bị block
- Windows Firewall cho phép nginx.exe

### Lỗi: Stream không hiển thị
- Kiểm tra FFmpeg output có lỗi không
- Thử với preset "direct" trước
- Kiểm tra codec compatibility

### Lỗi: High latency
- Sử dụng preset "low_latency"
- Giảm buffer size
- Tăng preset speed (ultrafast)

## 7. FFMPEG COMMANDS MẪU

```bash
# Direct copy (nhanh nhất)
ffmpeg -re -i input.mp4 -c copy -f flv rtmp://localhost/live/test

# Optimized cho YouTube
ffmpeg -re -i input.mp4 -c:v libx264 -preset veryfast -crf 20 -maxrate 4500k -bufsize 9000k -pix_fmt yuv420p -g 60 -c:a aac -b:a 128k -ar 44100 -f flv rtmp://a.rtmp.youtube.com/live2/STREAM_KEY

# Low latency
ffmpeg -re -i input.mp4 -c:v libx264 -preset ultrafast -tune zerolatency -crf 25 -maxrate 2000k -bufsize 4000k -c:a aac -b:a 96k -f flv rtmp://localhost/live/test

# From URL (Google Drive)
ffmpeg -re -headers "User-Agent: Mozilla/5.0" -i "https://drive.google.com/..." -c:v libx264 -preset veryfast -c:a aac -f flv rtmp://localhost/live/test
```

---

**Note**: Đây là setup để test local. Trên production VPS, nginx-rtmp không cần thiết vì stream trực tiếp đến YouTube/Facebook. 