# 🚀 Production Deployment - Quick Start Guide

## 📋 **TÓM TẮT QUY TRÌNH DEPLOY PRODUCTION**

### 🎯 **Khi bạn có VPS và Domain:**

1. **Point domain về VPS IP:**
   ```
   A Record: yourdomain.com → VPS_IP
   A Record: www.yourdomain.com → VPS_IP
   ```

2. **SSH vào VPS và chạy deploy script:**
   ```bash
   # Upload deploy.sh lên VPS
   scp deploy.sh user@your-vps-ip:~/
   
   # SSH vào VPS
   ssh user@your-vps-ip
   
   # Chạy deploy script
   chmod +x deploy.sh
   ./deploy.sh yourdomain.com
   ```

3. **Script sẽ tự động:**
   - ✅ Cài đặt tất cả dependencies (Nginx, PHP, MySQL, Redis, etc.)
   - ✅ Clone code từ Git repository
   - ✅ Cấu hình database và user
   - ✅ Setup SSL certificate với Let's Encrypt
   - ✅ Cấu hình queue workers với Supervisor
   - ✅ Setup cron jobs cho Laravel scheduler
   - ✅ Tối ưu performance (caching, etc.)

4. **Kết quả:**
   ```
   🔗 Website: https://yourdomain.com
   🔑 Admin: https://yourdomain.com/admin/dashboard
   📡 API: https://yourdomain.com/api/*
   ```

---

## 🔄 **SỰ KHÁC BIỆT VỚI NGROK (LOCAL):**

### **🏠 Local Development (Ngrok):**
```env
APP_URL=https://abc123.ngrok.io
```
- ❌ Temporary URLs
- ❌ Thay đổi mỗi lần restart ngrok
- ❌ VPS agents phải update webhook URLs

### **🌐 Production (Real Domain):**
```env
APP_URL=https://yourdomain.com
```
- ✅ **Permanent URLs**
- ✅ **Stable endpoints**
- ✅ **VPS agents tự động nhận đúng URLs**

---

## 🎯 **WEBHOOK URLS SẼ TỰ ĐỘNG ĐÚNG:**

Khi deploy production, tất cả URLs sẽ tự động sử dụng domain thật:

### **Stream Webhooks:**
```
Local:  https://abc123.ngrok.io/api/stream-webhook
Prod:   https://yourdomain.com/api/stream-webhook
```

### **VPS Stats Webhooks:**
```
Local:  https://abc123.ngrok.io/api/vps-stats  
Prod:   https://yourdomain.com/api/vps-stats
```

### **Secure Downloads:**
```
Local:  https://abc123.ngrok.io/api/secure-download/{token}
Prod:   https://yourdomain.com/api/secure-download/{token}
```

---

## 🔧 **KHÔNG CẦN SỬA CODE:**

### **✅ Code đã sử dụng Laravel helpers:**
```php
// Tự động sử dụng APP_URL từ .env
url('/api/stream-webhook')           // ✅ Dynamic
config('app.url') . '/api/vps-stats' // ✅ Dynamic

// Thay vì hardcode:
'https://abc123.ngrok.io/api/...'    // ❌ Static
```

### **✅ VPS Provision tự động:**
```php
// ProvisionVpsJob.php - Tự động dùng domain production
$serverUrl = config('app.url'); // https://yourdomain.com
$authToken = hash('sha256', "vps_stats_{$vpsId}_" . config('app.key'));

// VPS agent sẽ nhận:
Environment=WEBHOOK_URL=https://yourdomain.com/api/vps-stats
Environment=AUTH_TOKEN=abc123...
```

---

## 🚀 **CÁC BƯỚC DEPLOY NHANH:**

### **1. Chuẩn bị:**
```bash
# Trên máy local - push code lên Git
git add .
git commit -m "Ready for production"
git push origin main
```

### **2. Deploy lên VPS:**
```bash
# Upload script
scp deploy.sh production-check.php user@vps-ip:~/

# SSH và deploy
ssh user@vps-ip
./deploy.sh yourdomain.com
```

### **3. Kiểm tra:**
```bash
# Chạy health check
php production-check.php

# Test endpoints
curl https://yourdomain.com
curl -X POST https://yourdomain.com/api/vps-stats \
  -H "Content-Type: application/json" \
  -H "X-VPS-Auth-Token: test" \
  -d '{"vps_id":1,"cpu_usage":10,"ram_usage":20,"disk_usage":30}'
```

---

## 🔄 **UPDATE PRODUCTION:**

### **Cập nhật code mới:**
```bash
# SSH vào production VPS
ssh user@vps-ip
cd /var/www/vps-live-stream

# Chạy update script (đã tự động tạo)
./update.sh
```

### **Script update.sh sẽ:**
- ✅ Git pull latest code
- ✅ Update dependencies  
- ✅ Run new migrations
- ✅ Clear & rebuild cache
- ✅ Restart services

---

## 📊 **MONITORING PRODUCTION:**

### **Check Services:**
```bash
# Queue workers
sudo supervisorctl status

# Web server
sudo systemctl status nginx

# Database
sudo systemctl status mysql

# SSL certificate
sudo certbot certificates
```

### **View Logs:**
```bash
# Application logs
tail -f /var/www/vps-live-stream/storage/logs/laravel.log

# Queue worker logs  
tail -f /var/www/vps-live-stream/storage/logs/worker.log

# Nginx logs
tail -f /var/log/nginx/access.log
tail -f /var/log/nginx/error.log
```

---

## 🎯 **KẾT QUẢ CUỐI CÙNG:**

Sau khi deploy xong, bạn sẽ có:

### **🌐 Production URLs:**
- **Website:** `https://yourdomain.com`
- **Admin Panel:** `https://yourdomain.com/admin/dashboard`
- **API Base:** `https://yourdomain.com/api/`

### **📡 VPS Integration:**
- Khi thêm VPS mới → Tự động provision với production URLs
- VPS agents → Tự động gửi stats về production server
- Stream webhooks → Tự động sử dụng production endpoints
- File downloads → Tự động sử dụng production secure URLs

### **🔒 Security:**
- ✅ SSL/HTTPS enabled
- ✅ Firewall configured  
- ✅ Database secured
- ✅ File permissions correct
- ✅ Production environment settings

### **⚡ Performance:**
- ✅ Redis caching
- ✅ Queue workers
- ✅ Optimized PHP-FPM
- ✅ Nginx optimizations
- ✅ Static file caching

**Tất cả hoạt động tự động, không cần sửa code hay cấu hình thêm!** 🚀 