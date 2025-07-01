# 🚀 Production Deployment Guide - VPS Live Stream Control

## 📋 Checklist Deploy Production

### 1. 🏗️ **Chuẩn bị VPS Production**
```bash
# Cập nhật hệ thống
sudo apt update && sudo apt upgrade -y

# Cài đặt packages cần thiết
sudo apt install -y nginx mysql-server php8.2-fpm php8.2-mysql php8.2-mbstring \
    php8.2-xml php8.2-curl php8.2-zip php8.2-bcmath php8.2-intl \
    composer git supervisor redis-server

# Cài đặt Node.js (cho build assets)
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt-get install -y nodejs
```

### 2. 🔧 **Cấu hình MySQL**
```bash
# Secure MySQL installation
sudo mysql_secure_installation

# Tạo database và user
sudo mysql -u root -p
```

```sql
CREATE DATABASE vps_live_stream;
CREATE USER 'vps_app'@'localhost' IDENTIFIED BY 'your_strong_password_here';
GRANT ALL PRIVILEGES ON vps_live_stream.* TO 'vps_app'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 3. 📁 **Deploy Code**
```bash
# Tạo thư mục project
sudo mkdir -p /var/www/vps-live-stream
sudo chown -R $USER:www-data /var/www/vps-live-stream

# Clone repository
cd /var/www/vps-live-stream
git clone https://truongvando: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@github.com/truongvando/ezstream.git .

# Cài đặt dependencies
composer install --optimize-autoloader --no-dev
npm install && npm run build

# Set permissions
sudo chown -R www-data:www-data /var/www/vps-live-stream
sudo chmod -R 755 /var/www/vps-live-stream
sudo chmod -R 775 /var/www/vps-live-stream/storage
sudo chmod -R 775 /var/www/vps-live-stream/bootstrap/cache
```

### 4. ⚙️ **Cấu hình Environment**
```bash
# Copy và cấu hình .env
cp .env.example .env
nano .env
```

```env
# === PRODUCTION ENVIRONMENT ===
APP_NAME="VPS Live Stream Control"
APP_ENV=production
APP_KEY=base64:your_generated_key_here
APP_DEBUG=false
APP_URL=https://yourdomain.com

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=vps_live_stream
DB_USERNAME=vps_app
DB_PASSWORD=your_strong_password_here

# Queue
QUEUE_CONNECTION=redis

# Cache
CACHE_DRIVER=redis
SESSION_DRIVER=redis

# Redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Mail (cấu hình SMTP)
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_USERNAME=your-email
MAIL_PASSWORD=your-email-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="${APP_NAME}"

# Google Drive (nếu sử dụng)
GOOGLE_DRIVE_CLIENT_ID=your_client_id
GOOGLE_DRIVE_CLIENT_SECRET=your_client_secret
GOOGLE_DRIVE_REFRESH_TOKEN=your_refresh_token
GOOGLE_DRIVE_FOLDER_ID=your_folder_id

# Telegram Notifications (tùy chọn)
TELEGRAM_BOT_TOKEN=your_bot_token
TELEGRAM_CHAT_ID=your_chat_id
```

```bash
# Generate application key
php artisan key:generate

# Run migrations
php artisan migrate --force

# Seed initial data
php artisan db:seed --class=AdminUserSeeder
php artisan db:seed --class=ServicePackageSeeder
php artisan db:seed --class=PaymentSettingsSeeder

# Cache configuration
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 5. 🌐 **Cấu hình Nginx**
```bash
sudo nano /etc/nginx/sites-available/vps-live-stream
```

```nginx
server {
    listen 80;
    server_name yourdomain.com www.yourdomain.com;
    root /var/www/vps-live-stream/public;
    index index.php index.html;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Content-Security-Policy "default-src 'self' http: https: data: blob: 'unsafe-inline'" always;

    # File upload size
    client_max_body_size 2G;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Static files caching
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|pdf|txt)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

```bash
# Enable site
sudo ln -s /etc/nginx/sites-available/vps-live-stream /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### 6. 🔒 **SSL Certificate với Let's Encrypt**
```bash
# Cài đặt Certbot
sudo apt install certbot python3-certbot-nginx

# Tạo SSL certificate
sudo certbot --nginx -d yourdomain.com -d www.yourdomain.com

# Verify auto-renewal
sudo certbot renew --dry-run
```

### 7. 🔄 **Cấu hình Queue Workers**
```bash
sudo nano /etc/supervisor/conf.d/vps-live-stream-worker.conf
```

```ini
[program:vps-live-stream-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/vps-live-stream/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/www/vps-live-stream/storage/logs/worker.log
stopwaitsecs=3600
```

```bash
# Start supervisor
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start vps-live-stream-worker:*
```

### 8. ⏰ **Cấu hình Cron Jobs**
```bash
sudo crontab -e
```

```bash
# Laravel Scheduler
* * * * * cd /var/www/vps-live-stream && php artisan schedule:run >> /dev/null 2>&1

# Backup database daily (2 AM)
0 2 * * * cd /var/www/vps-live-stream && php artisan backup:run --only-db

# Clean old logs weekly
0 3 * * 0 find /var/www/vps-live-stream/storage/logs -name "*.log" -mtime +7 -delete
```

### 9. 🔧 **Cấu hình PHP-FPM (Tối ưu)**
```bash
sudo nano /etc/php/8.2/fpm/pool.d/www.conf
```

```ini
; Process manager
pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35
pm.max_requests = 500

; PHP settings
php_admin_value[memory_limit] = 4G
php_admin_value[upload_max_filesize] = 20G
php_admin_value[post_max_size] = 20G
php_admin_value[max_execution_time] = 7200
```

```bash
sudo systemctl restart php8.2-fpm
```

## 🔄 **Cập nhật Config cho Production**

### 1. **Cập nhật APP_URL trong code**
Không cần thay đổi code vì đã sử dụng `config('app.url')` và `url()` helpers.

### 2. **Webhook URLs sẽ tự động đúng:**
- Stream webhook: `https://yourdomain.com/api/stream-webhook`
- VPS stats webhook: `https://yourdomain.com/api/vps-stats`
- Secure download: `https://yourdomain.com/api/secure-download/{token}`

### 3. **VPS Agent sẽ tự động nhận đúng URLs:**
Khi provision VPS mới, `ProvisionVpsJob` sẽ sử dụng `config('app.url')` để tạo đúng webhook URLs.

## 🚀 **Deployment Script**

Tạo script tự động deploy:

```bash
#!/bin/bash
# deploy.sh - Production deployment script

set -e

echo "🚀 Starting production deployment..."

# Pull latest code
git pull origin main

# Install/update dependencies
composer install --optimize-autoloader --no-dev
npm install && npm run build

# Run migrations
php artisan migrate --force

# Clear and cache
php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Restart services
sudo supervisorctl restart vps-live-stream-worker:*
sudo systemctl reload nginx
sudo systemctl reload php8.2-fpm

echo "✅ Deployment completed successfully!"
```

## 🔍 **Monitoring & Logs**

### 1. **Application Logs:**
```bash
# Laravel logs
tail -f /var/www/vps-live-stream/storage/logs/laravel.log

# Nginx logs
tail -f /var/log/nginx/access.log
tail -f /var/log/nginx/error.log

# Queue worker logs
tail -f /var/www/vps-live-stream/storage/logs/worker.log
```

### 2. **System Monitoring:**
```bash
# Check services status
sudo systemctl status nginx
sudo systemctl status php8.2-fpm
sudo systemctl status mysql
sudo systemctl status redis-server
sudo supervisorctl status

# Check disk space
df -h

# Check memory usage
free -h

# Check running processes
ps aux | grep -E "(nginx|php-fpm|mysql|redis)"
```

## 🔒 **Security Checklist**

- ✅ SSL Certificate đã cài đặt
- ✅ Firewall cấu hình (chỉ mở port 80, 443, 22)
- ✅ Database user với quyền hạn tối thiểu
- ✅ File permissions đúng
- ✅ APP_DEBUG=false
- ✅ Strong passwords cho tất cả services
- ✅ Regular backups
- ✅ Log monitoring

## 🎯 **Domain Configuration**

Sau khi có domain:

1. **Point domain to VPS IP:**
   ```
   A Record: yourdomain.com → VPS_IP
   A Record: www.yourdomain.com → VPS_IP
   ```

2. **Update .env:**
   ```env
   APP_URL=https://yourdomain.com
   ```

3. **Restart services:**
   ```bash
   php artisan config:cache
   sudo systemctl reload nginx
   ```

Tất cả webhook URLs và download links sẽ tự động sử dụng domain mới! 🎉 