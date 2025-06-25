# VPS LIVE STREAM CONTROL - TỔNG HỢP HỆ THỐNG

## 1. TỔNG QUAN HỆ THỐNG

### 1.1 Mục tiêu
- **Hệ thống quản lý live stream video từ Google Drive đến các nền tảng RTMP (YouTube, Facebook Live)**
- **Không lưu trữ file trên VPS** - Chỉ tải về khi stream và xóa ngay sau khi kết thúc
- **Hỗ trợ nhiều VPS** - Phân phối thông minh dựa trên tải và dung lượng
- **Bảo mật cao** - Chống upload file thực thi giả dạng video

### 1.2 Công nghệ sử dụng
- Laravel 11 + Livewire 3
- Google Drive API
- FFmpeg cho streaming
- SSH cho điều khiển VPS
- MySQL cho database

## 2. CÁC TÍNH NĂNG ĐÃ HOÀN THÀNH

### 2.1 Quản lý người dùng
- ✅ Đăng ký/Đăng nhập với role (admin/user)
- ✅ Quản lý gói dịch vụ với giới hạn dung lượng
- ✅ Subscription với thời hạn sử dụng
- ✅ Thanh toán qua chuyển khoản ngân hàng

### 2.2 Upload & Lưu trữ
- ✅ Upload trực tiếp lên Google Drive (không qua VPS)
- ✅ Hỗ trợ file lớn không giới hạn (đã test 500MB+)
- ✅ Giới hạn dung lượng theo gói dịch vụ
- ✅ Xóa file từ Google Drive và database
- ✅ **FileSecurityService** - Kiểm tra file video hợp lệ:
  - Validate magic bytes/signatures
  - Chặn file thực thi giả dạng (exe, php, js, html...)
  - Phát hiện mã độc nhúng trong video
  - Sanitize tên file

### 2.3 Streaming
- ✅ **OptimizedStreamingService v2.0**:
  - 5 phương pháp lấy URL streaming từ Google Drive
  - Kiểm tra hiệu suất trước khi sử dụng
  - Cache thông minh 30 phút
  - Health monitoring với scoring 0-100
  
- ✅ **BufferedStreamingService**:
  - Rolling buffer 2GB max, chunks 100MB
  - Hỗ trợ nhiều stream đồng thời từ 1 buffer
  - Tự động xóa buffer cũ
  - Tạo HLS playlist

- ✅ **VpsNetworkManager**:
  - Phân phối thông minh dựa trên disk usage:
    - >90%: Bắt buộc URL streaming
    - 75-90%: Ưu tiên URL, ngoại trừ file <500MB
    - 50-75%: Xem xét kích thước file
    - <50%: Luôn download để có hiệu suất tốt nhất

### 2.4 VPS Management
- ✅ **VpsCleanupService**:
  - Xóa file >7 ngày
  - Disk >85% + file >24h: Tự động xóa
  - Disk >95%: Khẩn cấp xóa file >1h
  - Giữ lại file phổ biến (>10 lượt xem)
  - Chạy tự động 2AM hàng ngày

- ✅ **StreamLifecycleManager**:
  - Quản lý toàn bộ vòng đời stream
  - Xóa file ngay khi stop stream
  - Tracking stream sessions trong database

### 2.5 Monitoring & Analytics
- ✅ Stream health monitoring
- ✅ VPS stats tracking (CPU, RAM, Disk, Network)
- ✅ Performance benchmarking
- ✅ Cost analysis cho Google Drive API

## 3. QUY TRÌNH HOẠT ĐỘNG CHÍNH

### 3.1 Flow Upload File
```
1. User chọn file từ máy tính
2. JavaScript validate file type (mp4, mov, avi, mkv)
3. Upload trực tiếp lên Google Drive qua API
4. Lưu metadata vào database (file_id, size, name...)
5. Hiển thị trong File Manager với tag "☁️ GDrive"
```

### 3.2 Flow Tạo Stream Mới
```
1. User chọn file từ danh sách đã upload
2. Nhập thông tin stream:
   - Tiêu đề, mô tả
   - Platform (YouTube/Facebook)
   - RTMP URL + Stream Key
   - Preset (direct/optimized)
   - Tùy chọn: Loop, Schedule
3. Hệ thống:
   - Tạo StreamConfiguration record
   - Status = 'PENDING'
   - Chờ user bấm Start
```

### 3.3 Flow Bắt Đầu Stream
```
1. User bấm "Start Stream"
2. VpsNetworkManager quyết định:
   - Download file về VPS (nếu disk < 75%)
   - Hoặc stream trực tiếp từ URL (nếu disk > 75%)
3. Dispatch StartStreamJob:
   - Chọn VPS phù hợp (ít tải nhất)
   - SSH vào VPS
   - Chạy lệnh FFmpeg với parameters phù hợp
   - Update status = 'STREAMING'
4. Monitor stream health liên tục
```

### 3.4 Flow Kết Thúc Stream
```
1. User bấm "Stop Stream"
2. Dispatch StopStreamJob:
   - SSH vào VPS
   - Kill process FFmpeg
   - XÓA NGAY FILE VIDEO (nếu đã download)
   - Update status = 'STOPPED'
3. Lưu stream session history
4. Giải phóng resources
```

## 4. KIỂM TRA LOCAL

### 4.1 Điều kiện test local
- ✅ Upload file lên Google Drive: **SẴN SÀNG**
- ✅ Quản lý file (xem, xóa): **SẴN SÀNG**
- ⚠️ Live stream thực: **CẦN VPS THẬT** (hoặc mock SSH)

### 4.2 Cách test các tính năng
```bash
# 1. Test upload
- Truy cập /files
- Upload video file
- Kiểm tra trong Google Drive

# 2. Test streaming health
- Truy cập /test-google-drive
- Nhập file ID
- Test các chức năng monitoring

# 3. Test cleanup service
php artisan vps:cleanup --dry-run

# 4. Test với mock VPS
- Tạo mock SshService
- Test flow stream không cần VPS thật
```

## 5. NHIỆM VỤ CẦN LÀM

### 5.1 Hoàn thiện Core Features
- [ ] Test live stream thực tế với VPS
- [ ] Xử lý các edge cases (network failure, VPS down...)
- [ ] Optimize FFmpeg parameters cho từng platform
- [ ] Thêm webhook notifications

### 5.2 VPS Auto-provisioning
- [ ] Script cài đặt tự động cho VPS mới:
  - Install FFmpeg
  - Setup directories
  - Configure firewall
  - Install monitoring agents
- [ ] API endpoint để VPS tự đăng ký vào hệ thống
- [ ] Health check định kỳ

### 5.3 Advanced Monitoring
- [ ] Real-time stream quality metrics
- [ ] Viewer count tracking
- [ ] Bandwidth usage per stream
- [ ] Alert system (Telegram/Email)

### 5.4 UI/UX Improvements
- [ ] Live preview trong dashboard
- [ ] Stream scheduling calendar
- [ ] Batch operations
- [ ] Mobile responsive

### 5.5 Security Enhancements
- [ ] 2FA cho admin
- [ ] API rate limiting per user
- [ ] Audit logs
- [ ] Backup & disaster recovery

## 6. CẤU TRÚC THƯ MỤC QUAN TRỌNG

```
app/
├── Services/
│   ├── GoogleDriveService.php      # Upload/download Google Drive
│   ├── OptimizedStreamingService.php # URL streaming optimization
│   ├── BufferedStreamingService.php  # Buffer management
│   ├── VpsNetworkManager.php        # VPS distribution logic
│   ├── VpsCleanupService.php        # Auto cleanup
│   ├── StreamLifecycleManager.php   # Stream lifecycle
│   ├── FileSecurityService.php      # File validation & security
│   └── SshService.php               # SSH operations
├── Jobs/
│   ├── StartStreamJob.php           # Start FFmpeg stream
│   ├── StopStreamJob.php            # Stop stream & cleanup
│   ├── MonitorStreamJob.php         # Health monitoring
│   └── SyncVpsStatsJob.php          # VPS stats sync
├── Livewire/
│   ├── UserStreamManager.php        # User stream UI
│   ├── FileManager.php              # File management UI
│   └── Admin/
│       └── AdminStreamManager.php   # Admin stream control
└── Models/
    ├── StreamConfiguration.php      # Stream settings
    ├── UserFile.php                 # User files
    ├── VpsServer.php                # VPS servers
    └── StreamSession.php            # Stream history
```

## 7. TESTING CHECKLIST

### 7.1 Upload & Storage
- [x] Upload file < 10MB
- [x] Upload file > 100MB
- [x] Upload file > 500MB
- [x] Kiểm tra giới hạn dung lượng
- [x] Xóa file từ Google Drive
- [x] Upload file độc hại (test FileSecurityService)

### 7.2 Streaming
- [ ] Start stream với file nhỏ
- [ ] Start stream với file lớn
- [ ] Stop stream và verify cleanup
- [ ] Test với multiple VPS
- [ ] Test failover khi VPS die

### 7.3 Performance
- [ ] 10 streams đồng thời
- [ ] VPS cleanup với 1000+ files
- [ ] Upload 50 files liên tiếp
- [ ] Stream 24h liên tục

## 8. DEPLOYMENT GUIDE

### 8.1 Requirements
- PHP 8.2+
- MySQL 8.0+
- Redis (cho queue)
- Google Service Account
- VPS với SSH access

### 8.2 Environment Variables
```env
GOOGLE_APPLICATION_CREDENTIALS=/path/to/service-account.json
SSH_PRIVATE_KEY_PATH=/path/to/ssh/key
STREAM_BUFFER_PATH=/tmp/stream_buffers
CLEANUP_SCHEDULE="0 2 * * *"
```

### 8.3 VPS Setup Script
```bash
#!/bin/bash
# Install dependencies
apt-get update
apt-get install -y ffmpeg

# Create directories
mkdir -p /tmp/streaming_files
mkdir -p /var/log/streaming

# Set permissions
chmod 755 /tmp/streaming_files

# Install monitoring
# ... (còn nữa)
```

## 9. KNOWN ISSUES & SOLUTIONS

### 9.1 Memory Issues
- **Problem**: Large file upload exhausts memory
- **Solution**: Removed Livewire upload, use direct form submission

### 9.2 Google Drive Limits
- **Problem**: API quota exceeded
- **Solution**: Implement caching, use public URLs when possible

### 9.3 VPS Disk Full
- **Problem**: Streams fail when disk full
- **Solution**: VpsCleanupService + immediate deletion on stream stop

---

**Last Updated**: 2025-06-23
**Version**: 1.0.0
**Status**: READY FOR PRODUCTION TESTING 