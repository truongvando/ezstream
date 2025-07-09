# Hướng dẫn chi tiết cài đặt và di chuyển hệ thống Laravel sang VPS mới

## Mục lục
1. [Chuẩn bị VPS mới](#1-chuẩn-bị-vps-mới)
2. [Cài đặt AaPanel](#2-cài-đặt-aapanel)
3. [Cài đặt môi trường web](#3-cài-đặt-môi-trường-web)
4. [Tạo website và cấu hình domain](#4-tạo-website-và-cấu-hình-domain)
5. [Di chuyển mã nguồn Laravel](#5-di-chuyển-mã-nguồn-laravel)
6. [Cài đặt và cấu hình database](#6-cài-đặt-và-cấu-hình-database)
7. [Cấu hình Laravel](#7-cấu-hình-laravel)
8. [Cài đặt và cấu hình Queue Worker](#8-cài-đặt-và-cấu-hình-queue-worker)
9. [Cài đặt Cron Job](#9-cài-đặt-cron-job)
10. [Kiểm tra hệ thống](#10-kiểm-tra-hệ-thống)
11. [Bảo mật và tối ưu](#11-bảo-mật-và-tối-ưu)
12. [Xử lý sự cố thường gặp](#12-xử-lý-sự-cố-thường-gặp)

## 1. Chuẩn bị VPS mới

### Yêu cầu tối thiểu cho VPS
- CPU: 2 cores
- RAM: 4GB
- Ổ cứng: 50GB SSD
- Hệ điều hành: CentOS 7+ hoặc Ubuntu 18.04+

### Đăng nhập SSH vào VPS
```bash
ssh root@địa_chỉ_IP_VPS
```

## 2. Cài đặt AaPanel

### Cài đặt AaPanel trên CentOS
```bash
yum install -y wget && wget -O install.sh http://www.aapanel.com/script/install_6.0_en.sh && bash install.sh
```

### Cài đặt AaPanel trên Ubuntu
```bash
wget -O install.sh http://www.aapanel.com/script/install-ubuntu_6.0_en.sh && sudo bash install.sh
```

Sau khi cài đặt xong, bạn sẽ nhận được URL, username và password để đăng nhập vào AaPanel.

## 3. Cài đặt môi trường web

Đăng nhập vào AaPanel và cài đặt các phần mềm cần thiết:

1. Vào tab **App Store**
2. Cài đặt các phần mềm sau:
   - **LNMP** (Nginx + MySQL + PHP)
   - Chọn **PHP 8.1** (hoặc phiên bản phù hợp với Laravel của bạn)
   - **MySQL 5.7** hoặc **MySQL 8.0**
   - **phpMyAdmin**
   - **Redis** (nếu cần)
   - **Supervisor** (để chạy queue worker)

## 4. Tạo website và cấu hình domain

1. Trong AaPanel, chọn tab **Website**
2. Nhấn **Add site**
3. Điền thông tin:
   - Domain: ezstream.pro (hoặc domain của bạn)
   - Chọn PHP version: PHP 8.1 (hoặc phiên bản tương thích)
   - Database: Tạo database mới
   - FTP: Tạo tài khoản FTP nếu cần
   - SSL: Chọn "Apply Let's Encrypt Certificate" nếu cần HTTPS

## 5. Di chuyển mã nguồn Laravel

### Phương pháp 1: Sử dụng Git

```bash
# Đi đến thư mục web
cd /www/wwwroot/ezstream.pro

# Xóa các file mặc định
rm -rf ./*

# Clone repository từ Git (nếu có)
git clone https://your-repository-url.git .

# Cài đặt Composer
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer

# Cài đặt các dependency
composer install --no-dev --optimize-autoloader
```

### Phương pháp 2: Upload qua FTP/SFTP

1. Nén toàn bộ project Laravel trên máy local
2. Upload file nén lên VPS qua FTP hoặc SFTP
3. Giải nén file trên VPS:
```bash
cd /www/wwwroot/ezstream.pro
unzip project.zip
```

### Cấu hình quyền thư mục

```bash
# Đặt quyền cho các thư mục
chown -R www:www /www/wwwroot/ezstream.pro
chmod -R 755 /www/wwwroot/ezstream.pro
chmod -R 777 /www/wwwroot/ezstream.pro/storage
chmod -R 777 /www/wwwroot/ezstream.pro/bootstrap/cache
```

## 6. Cài đặt và cấu hình database

### Import database từ local

1. Xuất database từ local:
```bash
# Trên máy local
mysqldump -u username -p database_name > database.sql
```

2. Upload file SQL lên VPS
3. Import database trên VPS:
```bash
# Trên VPS
mysql -u username -p database_name < database.sql
```

Hoặc sử dụng phpMyAdmin trong AaPanel để import database.

## 7. Cấu hình Laravel

### Tạo và cấu hình file .env

```bash
# Copy file .env.example thành .env
cp .env.example .env

# Chỉnh sửa file .env
nano .env
```

Cấu hình các thông tin quan trọng trong file .env:
```
APP_NAME="EZStream Pro"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://ezstream.pro

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=tên_database
DB_USERNAME=tên_user
DB_PASSWORD=mật_khẩu

CACHE_DRIVER=file
SESSION_DRIVER=file
QUEUE_CONNECTION=database

MAIL_MAILER=smtp
MAIL_HOST=smtp.provider.com
MAIL_PORT=587
MAIL_USERNAME=your_email
MAIL_PASSWORD=your_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your_email
MAIL_FROM_NAME="${APP_NAME}"
```

### Tạo key và tối ưu Laravel

```bash
# Tạo application key
php artisan key:generate

# Xóa cache cấu hình
php artisan config:clear
php artisan config:cache

# Tối ưu route
php artisan route:cache

# Tối ưu view
php artisan view:cache

# Chạy migration nếu cần
php artisan migrate
```

### Cấu hình Nginx

Trong AaPanel, vào **Website** > chọn domain > **Settings** > **Rewrite**

Chọn template **Laravel 5** hoặc sao chép cấu hình sau:

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

## 8. Cài đặt và cấu hình Queue Worker

### Cài đặt Supervisor

Nếu chưa cài đặt Supervisor từ App Store của AaPanel:

```bash
# Cài đặt Supervisor
yum install -y supervisor  # CentOS
# hoặc
apt-get install -y supervisor  # Ubuntu

# Khởi động Supervisor
systemctl enable supervisord
systemctl start supervisord
```

### Cấu hình Supervisor cho Laravel Queue Worker

1. Tạo file cấu hình:

```bash
nano /etc/supervisord.d/laravel-worker.ini
```

2. Thêm nội dung sau:

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

3. Cập nhật và khởi động lại Supervisor:

```bash
supervisorctl reread
supervisorctl update
supervisorctl start laravel-worker:*
```

### Kiểm tra trạng thái Queue Worker

```bash
supervisorctl status
```

Kết quả sẽ hiển thị như sau nếu thành công:
```
laravel-worker:laravel-worker_00   RUNNING   pid 259672, uptime 0:04:56
laravel-worker:laravel-worker_01   RUNNING   pid 259673, uptime 0:04:56
laravel-worker:laravel-worker_02   RUNNING   pid 259674, uptime 0:04:56
laravel-worker:laravel-worker_03   RUNNING   pid 259675, uptime 0:04:56
```

### Khởi động lại Queue Worker sau khi cập nhật code

```bash
php artisan queue:restart
```

## 9. Cài đặt Cron Job

### Cấu hình Cron Job cho Laravel Scheduler

1. Trong AaPanel, vào **Cron**
2. Chọn **Add Task**
3. Cấu hình như sau:
   - Type: Shell Script
   - Name: Laravel Scheduler
   - Cycle: Every minute (*/1 * * * *)
   - Script content:
     ```bash
     cd /www/wwwroot/ezstream.pro && php artisan schedule:run >> /dev/null 2>&1
     ```

Hoặc thực hiện thông qua SSH:

```bash
# Mở crontab editor
crontab -e

# Thêm dòng sau
* * * * * cd /www/wwwroot/ezstream.pro && php artisan schedule:run >> /dev/null 2>&1
```

### Cron Job kiểm tra và khởi động lại Queue Worker

Tạo thêm một cron job để kiểm tra và khởi động lại queue worker nếu cần:

```bash
# Chạy mỗi 30 phút
*/30 * * * * supervisorctl status laravel-worker:* | grep -q RUNNING || supervisorctl restart laravel-worker:*
```

## 10. Kiểm tra hệ thống

### Kiểm tra Laravel

```bash
# Kiểm tra phiên bản Laravel
php artisan --version

# Kiểm tra kết nối database
php artisan db:monitor

# Kiểm tra các route
php artisan route:list

# Kiểm tra queue
php artisan queue:list
```

### Kiểm tra Queue Worker

```bash
# Kiểm tra trạng thái queue worker
supervisorctl status

# Kiểm tra log queue worker
tail -f /www/wwwroot/ezstream.pro/storage/logs/worker.log

# Kiểm tra số lượng job trong queue
php artisan queue:list
```

### Kiểm tra Cron Job

```bash
# Kiểm tra cron job đã được cài đặt
crontab -l

# Kiểm tra log của cron
grep CRON /var/log/syslog
```

### Kiểm tra website

1. Truy cập website của bạn qua trình duyệt
2. Kiểm tra các chức năng cơ bản
3. Kiểm tra log lỗi nếu có vấn đề:
```bash
tail -f /www/wwwroot/ezstream.pro/storage/logs/laravel.log
```

## 11. Bảo mật và tối ưu

### Bảo mật cơ bản

1. Cập nhật hệ thống:
```bash
yum update -y  # CentOS
# hoặc
apt update && apt upgrade -y  # Ubuntu
```

2. Cấu hình tường lửa:
```bash
# Mở các cổng cần thiết
firewall-cmd --permanent --add-service=http
firewall-cmd --permanent --add-service=https
firewall-cmd --permanent --add-service=ssh
firewall-cmd --reload
```

3. Cấu hình SSL/HTTPS:
   - Trong AaPanel: Website > Domain > SSL > Let's Encrypt

### Tối ưu hiệu suất

1. Cấu hình PHP-FPM:
   - Trong AaPanel: App Store > PHP-8.1 > Settings > Performance

2. Cấu hình Opcache:
   - Trong AaPanel: App Store > PHP-8.1 > Settings > Performance

3. Cấu hình Nginx:
   - Trong AaPanel: Website > Domain > Settings > Rewrite

## 12. Xử lý sự cố thường gặp

### Lỗi 500 Internal Server Error

1. Kiểm tra log lỗi:
```bash
tail -f /www/wwwroot/ezstream.pro/storage/logs/laravel.log
```

2. Kiểm tra quyền thư mục:
```bash
chmod -R 755 /www/wwwroot/ezstream.pro
chmod -R 777 /www/wwwroot/ezstream.pro/storage
chmod -R 777 /www/wwwroot/ezstream.pro/bootstrap/cache
```

### Queue Worker không hoạt động

1. Kiểm tra trạng thái:
```bash
supervisorctl status
```

2. Khởi động lại nếu cần:
```bash
supervisorctl restart laravel-worker:*
```

3. Kiểm tra log:
```bash
tail -f /www/wwwroot/ezstream.pro/storage/logs/worker.log
```

### Cron Job không chạy

1. Kiểm tra cron đã được cài đặt:
```bash
crontab -l
```

2. Kiểm tra log:
```bash
grep CRON /var/log/syslog
```

3. Đảm bảo đường dẫn chính xác:
```bash
cd /www/wwwroot/ezstream.pro && php artisan schedule:run
```

### Lệnh hữu ích khi gặp sự cố

```bash
# Xóa cache Laravel
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan optimize:clear

# Khởi động lại queue
php artisan queue:restart

# Khởi động lại web server
systemctl restart nginx
systemctl restart php-fpm

# Kiểm tra log hệ thống
tail -f /var/log/nginx/error.log
```

---

## Tóm tắt các bước quan trọng khi di chuyển sang VPS mới

1. **Cài đặt AaPanel**
2. **Cài đặt LNMP (Nginx, MySQL, PHP)**
3. **Tạo website và cấu hình domain**
4. **Di chuyển mã nguồn Laravel**
5. **Import database**
6. **Cấu hình file .env**
7. **Cài đặt và cấu hình Queue Worker với Supervisor**
8. **Cài đặt Cron Job cho Laravel Scheduler**
9. **Kiểm tra hệ thống**
10. **Cấu hình bảo mật và tối ưu**

Sau khi hoàn thành các bước trên, hệ thống Laravel của bạn sẽ hoạt động trên VPS mới với Queue Worker và Cron Job được cấu hình đúng cách. 