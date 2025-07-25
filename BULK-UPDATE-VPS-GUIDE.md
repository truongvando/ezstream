# 🔄 Bulk Update VPS Guide

Hướng dẫn sử dụng tính năng cập nhật hàng loạt VPS servers trong hệ thống EZStream.

## 📋 Tổng quan

Tính năng "Cập nhật tất cả VPS" cho phép admin cập nhật agent trên tất cả VPS servers đang hoạt động cùng một lúc, thay vì phải cập nhật từng VPS một cách thủ công.

## 🎯 Tính năng

### 1. **Web Interface (Admin Panel)**
- **Đường dẫn**: `/admin/vps-servers`
- **Nút**: "Cập nhật tất cả VPS" (màu cam)
- **Modal**: Hiển thị progress real-time
- **Kết quả**: Thống kê thành công/thất bại

### 2. **Command Line Interface**
```bash
# Xem trước những gì sẽ được cập nhật
php artisan vps:bulk-update --dry-run

# Cập nhật với xác nhận
php artisan vps:bulk-update

# Cập nhật không cần xác nhận
php artisan vps:bulk-update --force
```

### 3. **Test Script**
```bash
# Test chức năng bulk update
php test-bulk-update.php
```

## 🚀 Cách sử dụng

### **Qua Web Interface:**

1. **Truy cập Admin Panel**
   ```
   https://your-domain.com/admin/vps-servers
   ```

2. **Nhấn nút "Cập nhật tất cả VPS"**
   - Nút màu cam ở góc phải trên

3. **Xác nhận trong Modal**
   - Nhấn "Bắt đầu cập nhật"
   - Theo dõi progress real-time

4. **Xem kết quả**
   - Thống kê thành công/thất bại
   - Chi tiết từng VPS

### **Qua Command Line:**

1. **Dry Run (Xem trước)**
   ```bash
   php artisan vps:bulk-update --dry-run
   ```

2. **Cập nhật thực tế**
   ```bash
   php artisan vps:bulk-update
   ```

3. **Monitor Queue**
   ```bash
   php artisan queue:work --queue=vps-provisioning
   ```

## 🔧 Cách hoạt động

### **Workflow:**

1. **Lấy danh sách VPS**
   - Chỉ VPS có `is_active = true`
   - Loại trừ VPS có `status = 'PROVISIONING'`

2. **Dispatch Jobs**
   - Mỗi VPS → 1 `UpdateAgentJob`
   - Queue: `vps-provisioning`
   - Delay: 0.5s giữa các job

3. **Job Processing**
   - Stop agent hiện tại
   - Upload agent files mới
   - Restart agent
   - Verify hoạt động

### **Error Handling:**

- **Connection Failed**: Skip VPS, log error
- **Upload Failed**: Rollback, log error  
- **Service Failed**: Attempt restart, log error

## 📊 Monitoring

### **Logs:**

```bash
# Laravel logs
tail -f storage/logs/laravel.log | grep "Bulk update"

# Queue logs
tail -f storage/logs/laravel.log | grep "UpdateAgentJob"

# VPS agent logs (trên từng VPS)
tail -f /var/log/ezstream-agent.log
```

### **Queue Status:**

```bash
# Xem pending jobs
php artisan queue:monitor

# Xem failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all
```

## ⚠️ Lưu ý quan trọng

### **Trước khi cập nhật:**

1. **Backup quan trọng**
   - Agent tự động backup trước khi cập nhật
   - Location: `/opt/ezstream-agent-backup-TIMESTAMP`

2. **Kiểm tra VPS health**
   - Đảm bảo VPS đang online
   - Kiểm tra SSH connection

3. **Monitor streams**
   - Streams đang chạy có thể bị gián đoạn
   - Nên cập nhật khi ít streams hoạt động

### **Trong quá trình cập nhật:**

1. **Không tắt queue worker**
   ```bash
   php artisan queue:work --queue=vps-provisioning
   ```

2. **Monitor progress**
   - Web interface: Real-time progress
   - Command line: Progress bar

3. **Xử lý lỗi**
   - Failed jobs sẽ được log
   - Có thể retry manual

### **Sau khi cập nhật:**

1. **Verify agents**
   ```bash
   # Kiểm tra agent status
   php artisan vps:health-check
   
   # Test Redis connection
   redis-cli PUBSUB CHANNELS "vps-commands:*"
   ```

2. **Check streams**
   - Verify streams đang chạy bình thường
   - Restart streams nếu cần

## 🐛 Troubleshooting

### **Common Issues:**

1. **"No active VPS servers found"**
   - Kiểm tra VPS có `is_active = true`
   - Kiểm tra VPS không ở trạng thái `PROVISIONING`

2. **"Job dispatch failed"**
   - Kiểm tra queue connection
   - Restart queue worker

3. **"SSH connection failed"**
   - Verify SSH credentials
   - Check VPS network connectivity

4. **"Agent not responding"**
   - Check agent logs: `/var/log/ezstream-agent.log`
   - Restart agent manually: `systemctl restart ezstream-agent`

### **Recovery Steps:**

1. **Rollback agent**
   ```bash
   # Trên VPS bị lỗi
   systemctl stop ezstream-agent
   cp -r /opt/ezstream-agent-backup-LATEST/* /opt/ezstream-agent/
   systemctl start ezstream-agent
   ```

2. **Manual update**
   ```bash
   # Update single VPS
   php artisan vps:update-agent {vps_id}
   ```

## 📈 Performance

### **Recommended Settings:**

- **Max concurrent jobs**: 5-10
- **Delay between jobs**: 0.5s
- **Timeout per job**: 300s (5 minutes)
- **Memory limit**: 512MB per job

### **Scaling:**

- **Small setup** (< 10 VPS): Sequential processing
- **Medium setup** (10-50 VPS): Batch processing
- **Large setup** (> 50 VPS): Chunked processing

## 🔐 Security

### **Permissions:**

- Chỉ admin có quyền bulk update
- SSH credentials được encrypt
- Logs không chứa sensitive data

### **Audit:**

- Tất cả bulk updates được log
- Timestamp và user tracking
- Success/failure metrics

---

## 📞 Support

Nếu gặp vấn đề, hãy:

1. Check logs đầu tiên
2. Verify VPS connectivity  
3. Test với single VPS trước
4. Contact admin nếu cần thiết
