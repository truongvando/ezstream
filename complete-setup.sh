#!/bin/bash

# EzStream - Script triển khai tự động lên VPS
# Sử dụng: bash complete-setup.sh

set -e

# Các biến cấu hình - Hãy thay đổi theo nhu cầu
VPS_IP="172.236.157.67"
DOMAIN="ezstream.pro"
EMAIL_SSL="ezstream@ezstream.pro"
DB_NAME="ezstream_db"
DB_USER="ezstream_user"
DB_PASSWORD="Dodz1997a@123"
APP_NAME="EZSTREAM"

# Hàm in thông báo
print_message() {
    echo -e "\n\033[1;34m>>> $1\033[0m\n"
}

# Kiểm tra thông tin đầu vào
check_input() {
    if [[ -z "$VPS_IP" || -z "$DOMAIN" || -z "$EMAIL_SSL" ]]; then
        print_message "🛑 Vui lòng cập nhật các biến trong script:"
        echo "VPS_IP=\"your-vps-ip\"         # Ví dụ: 123.456.789.10"
        echo "DOMAIN=\"your-domain.com\"     # Domain của bạn"
        echo "EMAIL_SSL=\"your@email.com\"   # Email cho SSL certificate"
        exit 1
    fi
}

# 1. Cập nhật hệ thống
update_system() {
    print_message "🔄 Cập nhật hệ thống"
    apt update && apt upgrade -y
}

# 2. Cài đặt các packages cần thiết
install_dependencies() {
    print_message "📦 Cài đặt packages"
    apt install -y nginx mysql-server redis-server supervisor git curl unzip software-properties-common

    # Cài đặt PHP 8.2
    add-apt-repository ppa:ondrej/php -y
    apt update
    apt install -y php8.2-fpm php8.2-mysql php8.2-mbstring php8.2-xml php8.2-curl php8.2-zip php8.2-bcmath php8.2-intl php8.2-gd
    
    # Cài đặt Composer
    curl -sS https://getcomposer.org/installer | php
    mv composer.phar /usr/local/bin/composer
    chmod +x /usr/local/bin/composer
    
    # Cài đặt Node.js
    curl -fsSL https://deb.nodesource.com/setup_18.x | bash -
    apt-get install -y nodejs
    
    # Cài đặt Certbot
    apt install -y certbot python3-certbot-nginx
}

# 3. Cấu hình MySQL
setup_database() {
    print_message "🛢️ Cấu hình MySQL"
    
    # Kiểm tra xem database đã tồn tại chưa
    DB_EXISTS=$(mysql -e "SHOW DATABASES LIKE '$DB_NAME';" | grep -o $DB_NAME)
    
    if [ "$DB_EXISTS" != "$DB_NAME" ]; then
        mysql -e "CREATE DATABASE $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
        mysql -e "CREATE USER '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASSWORD';"
        mysql -e "GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'localhost';"
        mysql -e "FLUSH PRIVILEGES;"
        print_message "✅ Đã tạo database $DB_NAME và user $DB_USER"
    else
        print_message "⚠️ Database $DB_NAME đã tồn tại"
    fi
}

# 4. Deploy code
deploy_code() {
    print_message "📂 Deploy code"
    
    # Tạo thư mục và phân quyền
    mkdir -p /var/www/ezstream
    chown -R www-data:www-data /var/www/ezstream
    
    # Chuyển đến thư mục project
    cd /var/www/ezstream
    
    # Clone code từ repository hiện tại
    print_message "📥 Đang clone code từ repository hiện tại"
    git init
    git remote add origin https://github.com/truongvando/ezstream.git
    git fetch
    git checkout -f master
    
    # Cài đặt PHP dependencies
    print_message "🔧 Cài đặt PHP dependencies"
    composer install --optimize-autoloader --no-dev
    
    # Cài đặt JS dependencies và build assets
    print_message "🎨 Build frontend assets"
    npm install
    npm run build
    
    # Phân quyền thư mục
    chown -R www-data:www-data /var/www/ezstream
    chmod -R 755 /var/www/ezstream
    chmod -R 775 /var/www/ezstream/storage
    chmod -R 775 /var/www/ezstream/bootstrap/cache
}

# 5. Cấu hình environment
setup_environment() {
    print_message "⚙️ Cấu hình environment"
    
    # Copy và cấu hình file .env
    cp .env.example .env
    
    # Cập nhật các biến trong .env
    sed -i "s/APP_NAME=.*/APP_NAME=\"$APP_NAME\"/" .env
    sed -i "s/APP_ENV=.*/APP_ENV=production/" .env
    sed -i "s/APP_DEBUG=.*/APP_DEBUG=false/" .env
    sed -i "s|APP_URL=.*|APP_URL=https://$DOMAIN|" .env
    
    sed -i "s/DB_DATABASE=.*/DB_DATABASE=$DB_NAME/" .env
    sed -i "s/DB_USERNAME=.*/DB_USERNAME=$DB_USER/" .env
    sed -i "s/DB_PASSWORD=.*/DB_PASSWORD=$DB_PASSWORD/" .env
    
    sed -i "s/CACHE_DRIVER=.*/CACHE_DRIVER=redis/" .env
    sed -i "s/QUEUE_CONNECTION=.*/QUEUE_CONNECTION=redis/" .env
    sed -i "s/SESSION_DRIVER=.*/SESSION_DRIVER=redis/" .env
    sed -i "s/SESSION_LIFETIME=.*/SESSION_LIFETIME=43200/" .env
    
    # Generate app key
    php artisan key:generate
    
    # Chạy migrations và seeders
    php artisan migrate --force
    php artisan db:seed --class=AdminUserSeeder --force
    php artisan db:seed --class=ServicePackageSeeder --force
    php artisan db:seed --class=PaymentSettingsSeeder --force
    
    # Optimize cho production
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
}

# 6. Cấu hình Nginx
setup_nginx() {
    print_message "🌐 Cấu hình Nginx"
    
    # Tạo file cấu hình Nginx
    cat > /etc/nginx/sites-available/ezstream << EOL
server {
    listen 80;
    server_name $DOMAIN www.$DOMAIN;
    root /var/www/ezstream/public;
    index index.php index.html;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Content-Security-Policy "default-src 'self' http: https: data: blob: 'unsafe-inline''unsafe-eval'" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    # File upload size (cho video lớn)
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
EOL

    # Enable site và disable default
    ln -s /etc/nginx/sites-available/ezstream /etc/nginx/sites-enabled/
    rm -f /etc/nginx/sites-enabled/default
    
    # Test và reload nginx
    nginx -t && systemctl reload nginx
}

# 7. Tối ưu PHP-FPM
optimize_php() {
    print_message "🔧 Tối ưu PHP-FPM"
    
    # Cập nhật cấu hình PHP-FPM
    cat >> /etc/php/8.2/fpm/pool.d/www.conf << EOL

; EzStream optimizations
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
EOL

    # Restart PHP-FPM
    systemctl restart php8.2-fpm
}

# 8. Cấu hình Queue Workers
setup_queue_workers() {
    print_message "🔄 Cấu hình Queue Workers"
    
    # Tạo file cấu hình supervisor
    cat > /etc/supervisor/conf.d/ezstream-worker.conf << EOL
[program:ezstream-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/ezstream/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/www/ezstream/storage/logs/worker.log
stopwaitsecs=3600
EOL

    # Reload và start supervisor
    supervisorctl reread
    supervisorctl update
    supervisorctl start ezstream-worker:*
}

# 9. Cấu hình Cron Jobs
setup_cron_jobs() {
    print_message "⏰ Cấu hình Cron Jobs"
    
    # Thêm Laravel scheduler vào crontab
    (crontab -l 2>/dev/null || true; echo "* * * * * cd /var/www/ezstream && php artisan schedule:run >> /dev/null 2>&1") | crontab -
    
    # Thêm các cron jobs khác
    (crontab -l 2>/dev/null || true; echo "0 4 * * * sudo supervisorctl restart ezstream-worker:*") | crontab -
}

# 10. Cài đặt SSL Certificate
setup_ssl() {
    print_message "🔒 Cài đặt SSL Certificate"
    
    # Cài đặt SSL với Let's Encrypt
    certbot --nginx -d $DOMAIN -d www.$DOMAIN --non-interactive --agree-tos --email $EMAIL_SSL --redirect
    
    # Test auto-renewal
    certbot renew --dry-run
    
    # Crontab cho auto-renewal
    (crontab -l 2>/dev/null || true; echo "0 12 * * * /usr/bin/certbot renew --quiet") | crontab -
}

# MAIN EXECUTION
print_message "🚀 Bắt đầu cài đặt EzStream lên VPS"

# Kiểm tra thông tin đầu vào
check_input

# Chạy các bước cài đặt
update_system
install_dependencies
setup_database
deploy_code
setup_environment
setup_nginx
optimize_php
setup_queue_workers
setup_cron_jobs
setup_ssl

print_message "✅ HOÀN THÀNH CÀI ĐẶT!"
echo ""
echo "🔹 Website URL: https://$DOMAIN"
echo "🔹 Admin panel: https://$DOMAIN/admin/dashboard"
echo "🔹 Tài khoản admin mặc định: admin@example.com / password"
echo ""
echo "⚠️ LƯU Ý: Hãy đổi mật khẩu admin ngay sau khi đăng nhập!"
echo "⚠️ Đảm bảo đã trỏ domain $DOMAIN về IP $VPS_IP"
