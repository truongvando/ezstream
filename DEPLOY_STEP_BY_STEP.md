# 🚀 HƯỚNG DẪN DEPLOY VPS LIVE STREAM CONTROL LÊN PRODUCTION

## 📋 CHUẨN BỊ TRƯỚC KHI BẮT ĐẦU

### 1. **Yêu cầu VPS:**
- **RAM:** Tối thiểu 4GB (khuyến nghị 8GB+)
- **CPU:** 2 cores trở lên
- **Disk:** 50GB+ SSD
- **OS:** Ubuntu 20.04/22.04 LTS
- **Bandwidth:** Unlimited (cho streaming)

### 2. **Chuẩn bị domain:**
- Mua domain (ví dụ: `streamvps.com`)
- Trỏ A record về IP VPS:
  ```
  A     @              123.456.789.10
  A     www            123.456.789.10
  A     *              123.456.789.10
  ```

### 3. **Thông tin cần có:**
- IP VPS
- Root password VPS
- Domain name
- Email cho SSL certificate

---

## 🔧 BƯỚC 1: KẾT NỐI VÀ CẬP NHẬT VPS

```bash
# Kết nối SSH vào VPS
ssh root@YOUR_VPS_IP

# Cập nhật hệ thống
apt update && apt upgrade -y

# Tạo user non-root (bảo mật)
adduser deploy
usermod -aG sudo deploy
su - deploy
```

---

## 📦 BƯỚC 2: CÀI ĐẶT DEPENDENCIES

```bash
# Cài đặt các packages cần thiết
sudo apt install -y nginx mysql-server php8.2-fpm php8.2-mysql php8.2-mbstring \
    php8.2-xml php8.2-curl php8.2-zip php8.2-bcmath php8.2-intl \
    composer git supervisor redis-server certbot python3-certbot-nginx \
    curl unzip software-properties-common
sudo apt update
sudo apt install -y nginx libnginx-mod-rtmp ( cài cả nginx rtmp để control vps phụ )
# Cài đặt Node.js
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt-get install -y nodejs

# Kiểm tra versions
php --version
composer --version
node --version
npm --version
mysql --version
```

---

## 🗄️ BƯỚC 3: CẤU HÌNH MYSQL

```bash
# Secure MySQL installation
sudo mysql_secure_installation
# Trả lời: Y cho tất cả câu hỏi
# Đặt root password mạnh

# Tạo database và user
sudo mysql -u root -p
```

Trong MySQL console:
```sql
CREATE DATABASE vps_live_stream CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'vps_app'@'localhost' IDENTIFIED BY 'VpsApp2024!@#Strong';
GRANT ALL PRIVILEGES ON vps_live_stream.* TO 'vps_app'@'localhost';
FLUSH PRIVILEGES;
SHOW DATABASES;
EXIT;
```

---

## 📁 BƯỚC 4: DEPLOY CODE

```bash
# Tạo thư mục project
sudo mkdir -p /var/www/ezstream
sudo chown -R deploy:www-data /var/www/ezstream

# Clone code (thay YOUR_REPO_URL bằng URL repo thực tế)
cd /var/www/ezstream
git clone https://github.com/truongvando/ezstream.git .

# Cài đặt PHP dependencies
composer install --optimize-autoloader --no-dev

# Cài đặt JS dependencies và build assets
npm install
npm run build

# Set permissions
sudo chown -R www-data:www-data /var/www/vps-live-stream
sudo chmod -R 755 /var/www/vps-live-stream
sudo chmod -R 775 /var/www/vps-live-stream/storage
sudo chmod -R 775 /var/www/vps-live-stream/bootstrap/cache
```

---

## ⚙️ BƯỚC 5: CẤU HÌNH ENVIRONMENT

```bash
# Copy và chỉnh sửa .env
cp .env.example .env
nano .env
```

**Cấu hình .env production:**
```env
APP_NAME="VPS Live Stream Control"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://YOUR_DOMAIN.com

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=vps_live_stream
DB_USERNAME=vps_app
DB_PASSWORD=VpsApp2024!@#Strong

BROADCAST_DRIVER=log
CACHE_DRIVER=redis
FILESYSTEM_DISK=local
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=10080

MEMCACHED_HOST=127.0.0.1

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host.com
MAIL_PORT=587
MAIL_USERNAME=your-email@domain.com
MAIL_PASSWORD=your-email-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@YOUR_DOMAIN.com"
MAIL_FROM_NAME="${APP_NAME}"

# Google Drive (tùy chọn)
GOOGLE_DRIVE_CLIENT_ID=
GOOGLE_DRIVE_CLIENT_SECRET=
GOOGLE_DRIVE_REFRESH_TOKEN=
GOOGLE_DRIVE_FOLDER_ID=

# Session settings - 30 ngày
SESSION_LIFETIME=43200
SESSION_EXPIRE_ON_CLOSE=false
SESSION_ENCRYPT=false
SESSION_COOKIE=vps_live_stream_session
SESSION_SECURE_COOKIE=true
```

```bash
# Generate app key
php artisan key:generate

# Run migrations và seeders
php artisan migrate --force
php artisan db:seed --class=AdminUserSeeder --force
php artisan db:seed --class=ServicePackageSeeder --force
php artisan db:seed --class=PaymentSettingsSeeder --force

# Optimize for production
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## 🌐 BƯỚC 6: CẤU HÌNH NGINX

```bash
sudo nano /etc/nginx/sites-available/vps-live-stream
```

**Nội dung file Nginx config:**
```nginx
server {
    listen 80;
    server_name YOUR_DOMAIN.com www.YOUR_DOMAIN.com;
    root /var/www/vps-live-stream/public;
    index index.php index.html;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Content-Security-Policy "default-src 'self' http: https: data: blob: 'unsafe-inline'" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    # File upload size (cho video lớn)
    client_max_body_size 20G;
    client_body_timeout 300s;
    client_header_timeout 300s;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
        fastcgi_send_timeout 300;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Static files caching
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|pdf|txt|woff|woff2)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # Livewire uploads
    location ^~ /livewire {
        try_files $uri $uri/ /index.php?$query_string;
    }
}
```

```bash
# Enable site và disable default
sudo ln -s /etc/nginx/sites-available/vps-live-stream /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default

# Test và reload nginx
sudo nginx -t
sudo systemctl reload nginx
sudo systemctl enable nginx
```

---

## 🔧 BƯỚC 7: TỐI ƯU PHP-FPM

```bash
sudo nano /etc/php/8.2/fpm/pool.d/www.conf
```

**Thêm vào cuối file:**
```ini
; VPS Live Stream optimizations
pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35
pm.max_requests = 500

; PHP settings for large file uploads
php_admin_value[memory_limit] = 4G
php_admin_value[upload_max_filesize] = 20G
php_admin_value[post_max_size] = 20G
php_admin_value[max_execution_time] = 7200
php_admin_value[max_input_time] = 7200
```

```bash
# Restart PHP-FPM
sudo systemctl restart php8.2-fpm
sudo systemctl enable php8.2-fpm
```

---

## 🔄 BƯỚC 8: CẤU HÌNH QUEUE WORKERS

```bash
sudo nano /etc/supervisor/conf.d/vps-live-stream-worker.conf
```

**Nội dung file:**
```ini
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /www/wwwroot/ezstream.pro/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www
numprocs=4
redirect_stderr=true
stdout_logfile=/www/wwwroot/ezstream.pro/storage/logs/worker.log
stopwaitsecs=3600
```

```bash
# Start supervisor
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start vps-live-stream-worker:*
sudo systemctl enable supervisor
```

---

## ⏰ BƯỚC 9: CẤU HÌNH CRON JOBS

```bash
# Mở crontab
crontab -e
```

**Thêm các dòng sau:**
```bash
# Laravel Scheduler
* * * * * cd /var/www/vps-live-stream && php artisan schedule:run >> /dev/null 2>&1

# Backup database hàng ngày lúc 2h sáng
0 2 * * * cd /var/www/vps-live-stream && php artisan backup:run --only-db

# Dọn dẹp logs cũ hàng tuần
0 3 * * 0 find /var/www/vps-live-stream/storage/logs -name "*.log" -mtime +7 -delete

# Restart queue workers hàng ngày (phòng memory leak)
0 4 * * * sudo supervisorctl restart vps-live-stream-worker:*
```

---

## 🔒 BƯỚC 10: CÀI ĐẶT SSL CERTIFICATE

```bash
# Cài đặt SSL với Let's Encrypt
sudo certbot --nginx -d YOUR_DOMAIN.com -d www.YOUR_DOMAIN.com

# Nhập email của bạn
# Chọn A (Agree) cho terms
# Chọn Y hoặc N cho email sharing
# Chọn 2 (Redirect) để force HTTPS

# Test auto-renewal
sudo certbot renew --dry-run

# Crontab cho auto-renewal (nếu chưa có)
(crontab -l 2>/dev/null; echo "0 12 * * * /usr/bin/certbot renew --quiet") | crontab -
```

---

## ✅ BƯỚC 11: KIỂM TRA HỆ THỐNG

```bash
cd /var/www/vps-live-stream

# Chạy script kiểm tra production
php production-check.php

# Kiểm tra các services
sudo systemctl status nginx
sudo systemctl status php8.2-fpm
sudo systemctl status mysql
sudo systemctl status redis-server
sudo supervisorctl status

# Test website
curl -I https://YOUR_DOMAIN.com
```

---

## 🎯 BƯỚC 12: THIẾT LẬP DOMAIN VÀ DNS

### **Tại nhà cung cấp domain:**
```
Type    Name    Value               TTL
A       @       YOUR_VPS_IP         3600
A       www     YOUR_VPS_IP         3600
A       *       YOUR_VPS_IP         3600
```

### **Kiểm tra DNS:**
```bash
# Kiểm tra domain đã trỏ đúng chưa
nslookup YOUR_DOMAIN.com
ping YOUR_DOMAIN.com
```

---

## 🚀 BƯỚC 13: GO LIVE!

### **1. Truy cập website:**
- **Frontend:** `https://YOUR_DOMAIN.com`
- **Admin Panel:** `https://YOUR_DOMAIN.com/admin/dashboard`

### **2. Đăng nhập admin:**
- **Email:** `admin@example.com`
- **Password:** `password` (đổi ngay!)

### **3. API Endpoints sẽ tự động hoạt động:**
- **Stream Webhook:** `https://YOUR_DOMAIN.com/api/vps/stream-webhook`
- **VPS Stats:** `https://YOUR_DOMAIN.com/api/vps/vps-stats`
- **Secure Download:** `https://YOUR_DOMAIN.com/api/vps/secure-download/{token}`

---

## 🔧 MAINTENANCE VÀ MONITORING

### **Logs quan trọng:**
```bash
# Application logs
tail -f /var/www/vps-live-stream/storage/logs/laravel.log

# Nginx logs
tail -f /var/log/nginx/access.log
tail -f /var/log/nginx/error.log

# Queue worker logs
tail -f /var/www/vps-live-stream/storage/logs/worker.log

# System logs
journalctl -f -u nginx
journalctl -f -u php8.2-fpm
```

### **Commands hữu ích:**
```bash
# Update code
cd /var/www/vps-live-stream
git pull origin main
composer install --no-dev --optimize-autoloader
npm run build
php artisan migrate --force
php artisan config:cache
sudo supervisorctl restart vps-live-stream-worker:*

# Restart services
sudo systemctl restart nginx php8.2-fpm mysql redis-server
sudo supervisorctl restart all

# Check disk space
df -h

# Check memory
free -h

# Check processes
ps aux | grep -E "(nginx|php-fpm|mysql|redis)"
```

---

## 🎉 HOÀN THÀNH!

**Hệ thống VPS Live Stream Control đã sẵn sàng production với:**

✅ **SSL Certificate** tự động gia hạn  
✅ **Queue Workers** xử lý background jobs  
✅ **Auto Backup** database hàng ngày  
✅ **Monitoring & Logs** đầy đủ  
✅ **High Performance** configuration  
✅ **Security** headers và best practices  
✅ **Auto-scaling** queue workers  

**🔗 Truy cập ngay:** `https://YOUR_DOMAIN.com`

**Chúc mừng bạn đã deploy thành công! 🚀** 