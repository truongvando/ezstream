 # HƯỚNG DẪN TRIỂN KHAI EZSTREAM LÊN VPS

## PHẦN 1: CÀI ĐẶT MÔI TRƯỜNG

### Bước 1: Kết nối SSH vào VPS
```bash
ssh root@your_vps_ip
```

### Bước 2: Cập nhật hệ thống
```bash
apt update && apt upgrade -y
```

### Bước 3: Cài đặt các gói cần thiết
```bash
# Cài đặt Nginx, MySQL, Redis, Supervisor và các công cụ cần thiết
apt install -y nginx mysql-server redis-server supervisor git curl unzip software-properties-common

# Cài đặt PHP 8.2 và các extensions
add-apt-repository ppa:ondrej/php -y
apt update
apt install -y php8.2-fpm php8.2-mysql php8.2-mbstring php8.2-xml php8.2-curl php8.2-zip php8.2-bcmath php8.2-intl php8.2-gd php8.2-redis

# Cài đặt Composer
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer
chmod +x /usr/local/bin/composer

# Cài đặt Node.js
curl -fsSL https://deb.nodesource.com/setup_18.x | bash -
apt install -y nodejs

# Cài đặt Certbot (cho SSL)
apt install -y certbot python3-certbot-nginx
```

## PHẦN 2: CẤU HÌNH CSDL

### Bước 1: Tạo database và user
```bash
# Tạo database
mysql -e "CREATE DATABASE ezstream_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Tạo user và cấp quyền
mysql -e "CREATE USER 'ezstream_user'@'localhost' IDENTIFIED BY 'Dodz1997a@123';"
mysql -e "GRANT ALL PRIVILEGES ON ezstream_db.* TO 'ezstream_user'@'localhost';"
mysql -e "FLUSH PRIVILEGES;"

# Kiểm tra
mysql -e "SELECT user, host FROM mysql.user WHERE user='ezstream_user';"
mysql -e "SHOW DATABASES LIKE 'ezstream_db';"
```

## PHẦN 3: TRIỂN KHAI CODE

### Bước 1: Tạo thư mục và clone code
```bash
# Tạo thư mục project
mkdir -p /var/www/ezstream
cd /var/www

# Clone code từ Git
git clone https://github.com/truongvando/ezstream.git
cd ezstream
```

### Bước 2: Cài đặt dependencies
```bash
# Cài đặt PHP dependencies
composer install --no-dev --optimize-autoloader

# Cài đặt JS dependencies và build assets
npm install
npm run build
```

### Bước 3: Cấu hình môi trường
```bash
# Tạo file .env
cp .env.example .env

# Chỉnh sửa cấu hình trong .env
nano .env
```

Nội dung file .env:
```
APP_NAME=EZSTREAM
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=http://ezstream.pro

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ezstream_db
DB_USERNAME=ezstream_user
DB_PASSWORD=Dodz1997a@123

BROADCAST_DRIVER=log
CACHE_DRIVER=file
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=10080

MEMCACHED_HOST=127.0.0.1

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"
```

### Bước 4: Tạo key và migrations
```bash
# Tạo application key
php artisan key:generate

# Chạy migrations và seeders
php artisan migrate --force
php artisan db:seed --class=AdminUserSeeder --force
php artisan db:seed --class=ServicePackageSeeder --force
php artisan db:seed --class=PaymentSettingsSeeder --force

# Tạo symbolic link cho storage
php artisan storage:link
```

### Bước 5: Cấu hình quyền
```bash
# Phân quyền thư mục
chown -R www-data:www-data /var/www/ezstream
chmod -R 755 /var/www/ezstream
chmod -R 775 /var/www/ezstream/storage
chmod -R 775 /var/www/ezstream/bootstrap/cache
```

### Bước 6: Tối ưu hóa cho production
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## PHẦN 4: CẤU HÌNH NGINX

### Bước 1: Tạo cấu hình Nginx
```bash
nano /etc/nginx/sites-available/ezstream
```

Nội dung:
```nginx
server {
    listen 80;
    server_name ezstream.pro www.ezstream.pro your_vps_ip;
    root /var/www/ezstream/public;
    index index.php index.html;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Content-Security-Policy "default-src 'self' http: https: data: blob: 'unsafe-inline'" always;

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

    # Chặn truy cập các file ẩn
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

### Bước 2: Kích hoạt cấu hình và khởi động lại Nginx
```bash
# Tạo symbolic link
ln -s /etc/nginx/sites-available/ezstream /etc/nginx/sites-enabled/

# Xóa cấu hình mặc định nếu có
rm -f /etc/nginx/sites-enabled/default

# Kiểm tra cấu hình
nginx -t

# Khởi động lại Nginx
systemctl restart nginx
```

## PHẦN 5: CẤU HÌNH SSL (HTTPS)

### Bước 1: Cài đặt SSL với Certbot
```bash
certbot --nginx -d ezstream.pro -d www.ezstream.pro
```

### Bước 2: Kiểm tra auto-renewal
```bash
certbot renew --dry-run
```

## PHẦN 6: CẤU HÌNH SUPERVISOR (CHO QUEUE)

### Bước 1: Tạo cấu hình supervisor
```bash
nano /etc/supervisor/conf.d/ezstream-worker.conf
```

Nội dung:
```ini
[program:ezstream-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/ezstream/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/ezstream/storage/logs/worker.log
stopwaitsecs=3600
```

### Bước 2: Cập nhật và khởi động supervisor
```bash
supervisorctl reread
supervisorctl update
supervisorctl start ezstream-worker:*
```

## PHẦN 7: CẤU HÌNH CRON JOB

### Bước 1: Mở crontab
```bash
crontab -e
```

### Bước 2: Thêm cron jobs
```
# Laravel Scheduler (chạy mỗi phút)
* * * * * cd /var/www/ezstream && php artisan schedule:run >> /dev/null 2>&1

# Khởi động lại queue workers mỗi ngày lúc 4h sáng (tránh memory leak)
0 4 * * * supervisorctl restart ezstream-worker:*
```

## PHẦN 8: THEO DÕI VÀ BẢO TRÌ

### Kiểm tra logs
```bash
# Laravel logs
tail -f /var/www/ezstream/storage/logs/laravel.log

# Nginx logs
tail -f /var/log/nginx/access.log
tail -f /var/log/nginx/error.log

# Queue worker logs
tail -f /var/www/ezstream/storage/logs/worker.log
```

### Kiểm tra status các dịch vụ
```bash
systemctl status nginx
systemctl status php8.2-fpm
systemctl status mysql
systemctl status redis-server
supervisorctl status
```

## PHẦN 9: CẬP NHẬT CODE

### Bước 1: Tạo script cập nhật tự động
```bash
nano /var/www/ezstream/update.sh
```

Nội dung script:
```bash
#!/bin/bash
# Script cập nhật code tự động cho EZSTREAM

echo "🚀 Bắt đầu cập nhật EZSTREAM..."

# Di chuyển đến thư mục dự án
cd /var/www/ezstream

# Lưu trạng thái hiện tại
echo "💾 Sao lưu trạng thái hiện tại..."
git stash

# Lấy code mới
echo "📥 Đang lấy code mới từ Git..."
git pull origin master

# Khôi phục các thay đổi cục bộ (nếu cần)
git stash pop

# Cài đặt dependencies
echo "📦 Cài đặt dependencies..."
composer install --no-dev --optimize-autoloader

# Cập nhật frontend
echo "🎨 Build frontend assets..."
npm install
npm run build

# Chạy migrations
echo "🗄️ Cập nhật database..."
php artisan migrate --force

# Xóa cache
echo "🧹 Xóa cache..."
php artisan config:clear
php artisan cache:clear
php artisan view:clear

# Tạo lại cache
echo "⚡ Tạo cache mới..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Cập nhật quyền
echo "🔐 Cập nhật quyền thư mục..."
chown -R www-data:www-data /var/www/ezstream
chmod -R 755 /var/www/ezstream
chmod -R 775 /var/www/ezstream/storage
chmod -R 775 /var/www/ezstream/bootstrap/cache

# Khởi động lại PHP
echo "🔄 Khởi động lại PHP-FPM..."
systemctl restart php8.2-fpm

# Khởi động lại queue workers
echo "🔁 Khởi động lại queue workers..."
supervisorctl restart ezstream-worker:*

echo "✅ Cập nhật hoàn tất!"
echo "🌐 Truy cập website tại: https://ezstream.pro"
```

### Bước 2: Cấp quyền thực thi cho script
```bash
chmod +x /var/www/ezstream/update.sh
```

### Bước 3: Chạy script khi cần cập nhật
```bash
/var/www/ezstream/update.sh
```

## PHẦN 10: XỬ LÝ SỰ CỐ THƯỜNG GẶP

### 1. Lỗi quyền truy cập
```bash
# Nếu gặp lỗi quyền, hãy chạy lại lệnh này
sudo chown -R www-data:www-data /var/www/ezstream
sudo chmod -R 755 /var/www/ezstream
sudo chmod -R 775 /var/www/ezstream/storage
sudo chmod -R 775 /var/www/ezstream/bootstrap/cache
```

### 2. Lỗi kết nối database
```bash
# Kiểm tra database user
mysql -e "SELECT user, host FROM mysql.user WHERE user='ezstream_user';"

# Kiểm tra kết nối
mysql -u ezstream_user -p'Dodz1997a@123' -e "USE ezstream_db; SHOW TABLES;"

# Tạo lại user nếu cần
mysql -e "DROP USER IF EXISTS 'ezstream_user'@'localhost';"
mysql -e "CREATE USER 'ezstream_user'@'localhost' IDENTIFIED BY 'Dodz1997a@123';"
mysql -e "GRANT ALL PRIVILEGES ON ezstream_db.* TO 'ezstream_user'@'localhost';"
mysql -e "FLUSH PRIVILEGES;"
```

### 3. Lỗi Nginx
```bash
# Kiểm tra cấu hình
nginx -t

# Nếu có lỗi, sửa file cấu hình
nano /etc/nginx/sites-available/ezstream

# Khởi động lại Nginx
systemctl restart nginx
```

### 4. Lỗi PHP
```bash
# Kiểm tra PHP đang chạy
systemctl status php8.2-fpm

# Kiểm tra extensions
php -m

# Cài đặt extension thiếu
apt install -y php8.2-extension_name

# Khởi động lại PHP-FPM
systemctl restart php8.2-fpm
```

### 5. Lỗi Laravel
```bash
# Nếu Laravel báo lỗi, thử các lệnh sau
php artisan optimize:clear
php artisan cache:clear
php artisan config:clear
php artisan view:clear
php artisan route:clear

# Tạo lại cache
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## PHẦN 11: THÔNG TIN ĐĂNG NHẬP MẶC ĐỊNH

### Thông tin admin
- **URL:** https://ezstream.pro/admin/dashboard
- **Email:** admin@example.com
- **Password:** password

### Thông tin database
- **Database:** ezstream_db
- **Username:** ezstream_user
- **Password:** Dodz1997a@123

### Thông tin SSH
- **IP:** your_vps_ip
- **User:** root
- **Password:** your_password

## PHẦN 12: LƯU Ý QUAN TRỌNG

1. **Đổi mật khẩu admin** ngay sau lần đăng nhập đầu tiên
2. **Backup database** định kỳ
3. **Kiểm tra logs** thường xuyên
4. **Cập nhật VPS** với các bản vá bảo mật mới
5. **Tối ưu hóa** MySQL và Nginx nếu cần thiết