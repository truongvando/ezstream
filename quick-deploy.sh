#!/bin/bash

# 🚀 VPS Live Stream Control - Quick Deploy Script
# Usage: ./quick-deploy.sh yourdomain.com

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
NC='\033[0m' # No Color

# Functions
success() { echo -e "${GREEN}✅ $1${NC}"; }
warning() { echo -e "${YELLOW}⚠️  $1${NC}"; }
error() { echo -e "${RED}❌ $1${NC}"; exit 1; }
info() { echo -e "${BLUE}ℹ️  $1${NC}"; }
header() { echo -e "${PURPLE}🚀 $1${NC}"; }

# Check parameters
if [ $# -eq 0 ]; then
    error "Usage: ./quick-deploy.sh yourdomain.com"
fi

DOMAIN="$1"
PROJECT_DIR="/var/www/vps-live-stream"
DB_PASSWORD="VpsApp$(date +%s)!@#"

header "VPS LIVE STREAM CONTROL - QUICK DEPLOY"
echo "=========================================="
echo "Domain: $DOMAIN"
echo "Project Directory: $PROJECT_DIR"
echo ""

# Check if running as root
if [[ $EUID -eq 0 ]]; then
   error "Vui lòng chạy script này với user thường, không phải root"
fi

# Prompt for repo URL
read -p "🔗 Nhập URL repository của bạn (GitHub/GitLab): " REPO_URL
if [ -z "$REPO_URL" ]; then
    error "Repository URL không được để trống"
fi

# Prompt for email
read -p "📧 Nhập email cho SSL certificate: " SSL_EMAIL
if [ -z "$SSL_EMAIL" ]; then
    error "Email không được để trống"
fi

echo ""
info "Bắt đầu quá trình deploy tự động..."
echo ""

# 1. System Update
header "BƯỚC 1: CẬP NHẬT HỆ THỐNG"
sudo apt update && sudo apt upgrade -y
success "Hệ thống đã được cập nhật"

# 2. Install Dependencies
header "BƯỚC 2: CÀI ĐẶT DEPENDENCIES"
sudo apt install -y nginx mysql-server php8.2-fpm php8.2-mysql php8.2-mbstring \
    php8.2-xml php8.2-curl php8.2-zip php8.2-bcmath php8.2-intl \
    composer git supervisor redis-server certbot python3-certbot-nginx \
    curl unzip software-properties-common bc jq

# Install Node.js
if ! command -v node &> /dev/null; then
    info "Cài đặt Node.js..."
    curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
    sudo apt-get install -y nodejs
fi

success "Dependencies đã được cài đặt"

# 3. Configure MySQL
header "BƯỚC 3: CẤU HÌNH MYSQL"
info "Cấu hình MySQL với password tự động..."

# Set MySQL root password if not set
sudo mysql -e "ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY '$DB_PASSWORD';" 2>/dev/null || true

# Create database and user
mysql -u root -p$DB_PASSWORD << EOF 2>/dev/null || {
    warning "Không thể tự động cấu hình MySQL. Vui lòng chạy thủ công:"
    echo "sudo mysql_secure_installation"
    echo "Sau đó tạo database bằng tay."
    exit 1
}
CREATE DATABASE IF NOT EXISTS vps_live_stream CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'vps_app'@'localhost' IDENTIFIED BY '$DB_PASSWORD';
GRANT ALL PRIVILEGES ON vps_live_stream.* TO 'vps_app'@'localhost';
FLUSH PRIVILEGES;
EOF

success "MySQL đã được cấu hình"

# 4. Deploy Code
header "BƯỚC 4: DEPLOY CODE"
sudo mkdir -p $PROJECT_DIR
sudo chown -R $USER:www-data $PROJECT_DIR

if [ -d "$PROJECT_DIR/.git" ]; then
    cd $PROJECT_DIR
    git pull origin main
else
    cd /var/www
    git clone $REPO_URL vps-live-stream
    cd $PROJECT_DIR
fi

# Install dependencies
composer install --optimize-autoloader --no-dev
npm install && npm run build

success "Code đã được deploy"

# 5. Environment Configuration
header "BƯỚC 5: CẤU HÌNH ENVIRONMENT"
if [ ! -f .env ]; then
    cp .env.example .env
    php artisan key:generate
fi

# Update .env
cat > .env << EOF
APP_NAME="VPS Live Stream Control"
APP_ENV=production
APP_KEY=$(php artisan key:generate --show)
APP_DEBUG=false
APP_URL=https://$DOMAIN

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=vps_live_stream
DB_USERNAME=vps_app
DB_PASSWORD=$DB_PASSWORD

BROADCAST_DRIVER=log
CACHE_DRIVER=redis
FILESYSTEM_DISK=local
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=10080

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@$DOMAIN"
MAIL_FROM_NAME="VPS Live Stream Control"

SESSION_LIFETIME=10080
SESSION_EXPIRE_ON_CLOSE=false
SESSION_ENCRYPT=false
SESSION_COOKIE=vps_live_stream_session
SESSION_SECURE_COOKIE=true
EOF

# Run migrations
php artisan migrate --force
php artisan db:seed --class=AdminUserSeeder --force
php artisan db:seed --class=ServicePackageSeeder --force
php artisan db:seed --class=PaymentSettingsSeeder --force

# Optimize
php artisan config:cache
php artisan route:cache
php artisan view:cache

success "Environment đã được cấu hình"

# 6. Set Permissions
header "BƯỚC 6: THIẾT LẬP PERMISSIONS"
sudo chown -R www-data:www-data $PROJECT_DIR
sudo chmod -R 755 $PROJECT_DIR
sudo chmod -R 775 $PROJECT_DIR/storage
sudo chmod -R 775 $PROJECT_DIR/bootstrap/cache

success "Permissions đã được thiết lập"

# 7. Configure Nginx
header "BƯỚC 7: CẤU HÌNH NGINX"
sudo tee /etc/nginx/sites-available/vps-live-stream > /dev/null << EOF
server {
    listen 80;
    server_name $DOMAIN www.$DOMAIN;
    root $PROJECT_DIR/public;
    index index.php index.html;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Content-Security-Policy "default-src 'self' http: https: data: blob: 'unsafe-inline'" always;

    # File upload size
    client_max_body_size 20G;
    client_body_timeout 300s;
    client_header_timeout 300s;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
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
        try_files \$uri \$uri/ /index.php?\$query_string;
    }
}
EOF

sudo ln -sf /etc/nginx/sites-available/vps-live-stream /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t
sudo systemctl reload nginx
sudo systemctl enable nginx

success "Nginx đã được cấu hình"

# 8. Optimize PHP-FPM
header "BƯỚC 8: TỐI ƯU PHP-FPM"
sudo tee -a /etc/php/8.2/fpm/pool.d/www.conf > /dev/null << EOF

; VPS Live Stream optimizations
pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35
pm.max_requests = 500

php_admin_value[memory_limit] = 4G
php_admin_value[upload_max_filesize] = 20G
php_admin_value[post_max_size] = 20G
php_admin_value[max_execution_time] = 7200
php_admin_value[max_input_time] = 7200
EOF

sudo systemctl restart php8.2-fpm
sudo systemctl enable php8.2-fpm

success "PHP-FPM đã được tối ưu"

# 9. Setup Queue Workers
header "BƯỚC 9: CẤU HÌNH QUEUE WORKERS"
sudo tee /etc/supervisor/conf.d/vps-live-stream-worker.conf > /dev/null << EOF
[program:vps-live-stream-worker]
process_name=%(program_name)s_%(process_num)02d
command=php $PROJECT_DIR/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=$PROJECT_DIR/storage/logs/worker.log
stopwaitsecs=3600
EOF

sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start vps-live-stream-worker:*
sudo systemctl enable supervisor

success "Queue workers đã được cấu hình"

# 10. Setup Cron Jobs
header "BƯỚC 10: CẤU HÌNH CRON JOBS"
(crontab -l 2>/dev/null; cat << EOF
# Laravel Scheduler
* * * * * cd $PROJECT_DIR && php artisan schedule:run >> /dev/null 2>&1

# Backup database daily
0 2 * * * cd $PROJECT_DIR && php artisan backup:run --only-db

# Clean old logs weekly
0 3 * * 0 find $PROJECT_DIR/storage/logs -name "*.log" -mtime +7 -delete

# Restart queue workers daily
0 4 * * * sudo supervisorctl restart vps-live-stream-worker:*

# SSL certificate auto-renewal
0 12 * * * /usr/bin/certbot renew --quiet
EOF
) | crontab -

success "Cron jobs đã được cấu hình"

# 11. Install SSL Certificate
header "BƯỚC 11: CÀI ĐẶT SSL CERTIFICATE"
info "Cài đặt SSL certificate cho $DOMAIN..."

if sudo certbot --nginx -d $DOMAIN -d www.$DOMAIN --non-interactive --agree-tos --email $SSL_EMAIL --redirect; then
    success "SSL certificate đã được cài đặt thành công"
else
    warning "SSL certificate có thể chưa được cài đặt. Kiểm tra DNS và thử lại sau."
fi

# 12. Start All Services
header "BƯỚC 12: KHỞI ĐỘNG SERVICES"
sudo systemctl enable nginx php8.2-fpm mysql redis-server supervisor
sudo systemctl start nginx php8.2-fpm mysql redis-server supervisor

success "Tất cả services đã được khởi động"

# 13. Run Production Check
header "BƯỚC 13: KIỂM TRA HỆ THỐNG"
cd $PROJECT_DIR
php production-check.php

# 14. Create Update Script
cat > $PROJECT_DIR/update.sh << 'EOF'
#!/bin/bash
# Quick update script

cd /var/www/vps-live-stream

echo "🔄 Updating VPS Live Stream Control..."

git pull origin main
composer install --optimize-autoloader --no-dev
npm install && npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
sudo supervisorctl restart vps-live-stream-worker:*
sudo systemctl reload nginx php8.2-fpm

echo "✅ Update completed!"
EOF

chmod +x $PROJECT_DIR/update.sh

# Final Summary
echo ""
header "🎉 DEPLOY HOÀN THÀNH!"
echo "=================================="
echo ""
success "VPS Live Stream Control đã được deploy thành công!"
echo ""
echo "🔗 THÔNG TIN TRUY CẬP:"
echo "   Website: https://$DOMAIN"
echo "   Admin Panel: https://$DOMAIN/admin/dashboard"
echo ""
echo "🔑 THÔNG TIN ĐĂNG NHẬP ADMIN:"
echo "   Email: admin@example.com"
echo "   Password: password"
echo "   ⚠️  Vui lòng đổi password ngay sau khi đăng nhập!"
echo ""
echo "🗄️ THÔNG TIN DATABASE:"
echo "   Database: vps_live_stream"
echo "   Username: vps_app"
echo "   Password: $DB_PASSWORD"
echo ""
echo "📡 API ENDPOINTS:"
echo "   Stream Webhook: https://$DOMAIN/api/vps/stream-webhook"
echo "   VPS Stats: https://$DOMAIN/api/vps/vps-stats"
echo "   Secure Download: https://$DOMAIN/api/vps/secure-download/{token}"
echo ""
echo "🔧 MAINTENANCE:"
echo "   Update: cd $PROJECT_DIR && ./update.sh"
echo "   Logs: tail -f $PROJECT_DIR/storage/logs/laravel.log"
echo "   Workers: sudo supervisorctl status"
echo ""
success "Hệ thống đã sẵn sàng cho production! 🚀"
echo ""
warning "QUAN TRỌNG:"
echo "1. Đổi password admin ngay lập tức"
echo "2. Cấu hình email SMTP trong admin panel"
echo "3. Thiết lập Google Drive nếu cần"
echo "4. Kiểm tra firewall và bảo mật"
echo ""
echo "Chúc mừng bạn đã deploy thành công! 🎉" 