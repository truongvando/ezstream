#!/bin/bash

# 🚀 VPS Live Stream Control - Production Deployment Script
# Usage: ./deploy.sh [domain]

set -e

DOMAIN="${1:-yourdomain.com}"
PROJECT_DIR="/var/www/vps-live-stream"
NGINX_SITE="vps-live-stream"

echo "🚀 VPS Live Stream Control - Production Deployment"
echo "=================================================="
echo "Domain: $DOMAIN"
echo "Project Directory: $PROJECT_DIR"
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

success() {
    echo -e "${GREEN}✅ $1${NC}"
}

warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

error() {
    echo -e "${RED}❌ $1${NC}"
    exit 1
}

info() {
    echo -e "${BLUE}ℹ️  $1${NC}"
}

# Check if running as root
if [[ $EUID -eq 0 ]]; then
   error "This script should not be run as root for security reasons"
fi

# 1. System Updates & Dependencies
info "1. Installing system dependencies..."
sudo apt update && sudo apt upgrade -y

sudo apt install -y nginx mysql-server php8.2-fpm php8.2-mysql php8.2-mbstring \
    php8.2-xml php8.2-curl php8.2-zip php8.2-bcmath php8.2-intl \
    composer git supervisor redis-server certbot python3-certbot-nginx \
    bc jq curl unzip

# Install Node.js
if ! command -v node &> /dev/null; then
    info "Installing Node.js..."
    curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
    sudo apt-get install -y nodejs
fi

success "System dependencies installed"

# 2. Setup MySQL
info "2. Configuring MySQL..."
if ! mysql -u root -e "SELECT 1;" &>/dev/null; then
    warning "MySQL root password not set. Please run: sudo mysql_secure_installation"
fi

# Create database and user
read -p "Enter MySQL root password: " -s MYSQL_ROOT_PASSWORD
echo ""

mysql -u root -p$MYSQL_ROOT_PASSWORD << EOF
CREATE DATABASE IF NOT EXISTS vps_live_stream;
CREATE USER IF NOT EXISTS 'vps_app'@'localhost' IDENTIFIED BY 'VpsApp2024!@#';
GRANT ALL PRIVILEGES ON vps_live_stream.* TO 'vps_app'@'localhost';
FLUSH PRIVILEGES;
EOF

success "MySQL configured"

# 3. Setup Project Directory
info "3. Setting up project directory..."
sudo mkdir -p $PROJECT_DIR
sudo chown -R $USER:www-data $PROJECT_DIR

# If git repo exists, pull. Otherwise clone
if [ -d "$PROJECT_DIR/.git" ]; then
    cd $PROJECT_DIR
    git pull origin main
else
    cd /var/www
    # Replace with your actual repository URL
    read -p "Enter your Git repository URL: " REPO_URL
    git clone $REPO_URL vps-live-stream
    cd $PROJECT_DIR
fi

success "Project code deployed"

# 4. Install Dependencies
info "4. Installing application dependencies..."
composer install --optimize-autoloader --no-dev
npm install && npm run build

success "Dependencies installed"

# 5. Environment Configuration
info "5. Configuring environment..."

if [ ! -f .env ]; then
    cp .env.example .env
    php artisan key:generate
fi

# Update .env with production settings
sed -i "s|APP_ENV=.*|APP_ENV=production|" .env
sed -i "s|APP_DEBUG=.*|APP_DEBUG=false|" .env
sed -i "s|APP_URL=.*|APP_URL=https://$DOMAIN|" .env
sed -i "s|DB_DATABASE=.*|DB_DATABASE=vps_live_stream|" .env
sed -i "s|DB_USERNAME=.*|DB_USERNAME=vps_app|" .env
sed -i "s|DB_PASSWORD=.*|DB_PASSWORD=VpsApp2024!@#|" .env
sed -i "s|QUEUE_CONNECTION=.*|QUEUE_CONNECTION=redis|" .env
sed -i "s|CACHE_DRIVER=.*|CACHE_DRIVER=redis|" .env
sed -i "s|SESSION_DRIVER=.*|SESSION_DRIVER=redis|" .env

success "Environment configured"

# 6. Database Migration
info "6. Running database migrations..."
php artisan migrate --force
php artisan db:seed --class=AdminUserSeeder --force
php artisan db:seed --class=ServicePackageSeeder --force
php artisan db:seed --class=PaymentSettingsSeeder --force

success "Database migrated"

# 7. Cache Configuration
info "7. Optimizing application..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

success "Application optimized"

# 8. Set Permissions
info "8. Setting file permissions..."
sudo chown -R www-data:www-data $PROJECT_DIR
sudo chmod -R 755 $PROJECT_DIR
sudo chmod -R 775 $PROJECT_DIR/storage
sudo chmod -R 775 $PROJECT_DIR/bootstrap/cache

success "Permissions set"

# 9. Configure Nginx
info "9. Configuring Nginx..."

sudo tee /etc/nginx/sites-available/$NGINX_SITE > /dev/null << EOF
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
    client_max_body_size 2G;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
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
EOF

# Enable site
sudo ln -sf /etc/nginx/sites-available/$NGINX_SITE /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default

# Test nginx config
sudo nginx -t
sudo systemctl reload nginx

success "Nginx configured"

# 10. Configure PHP-FPM
info "10. Optimizing PHP-FPM..."

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
EOF

sudo systemctl restart php8.2-fpm

success "PHP-FPM optimized"

# 11. Setup Queue Workers
info "11. Setting up queue workers..."

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

success "Queue workers configured"

# 12. Setup Cron Jobs
info "12. Setting up cron jobs..."

(crontab -l 2>/dev/null; echo "* * * * * cd $PROJECT_DIR && php artisan schedule:run >> /dev/null 2>&1") | crontab -
(crontab -l 2>/dev/null; echo "0 2 * * * cd $PROJECT_DIR && php artisan backup:run --only-db") | crontab -
(crontab -l 2>/dev/null; echo "0 3 * * 0 find $PROJECT_DIR/storage/logs -name '*.log' -mtime +7 -delete") | crontab -

success "Cron jobs configured"

# 13. SSL Certificate
info "13. Setting up SSL certificate..."

if sudo certbot --nginx -d $DOMAIN -d www.$DOMAIN --non-interactive --agree-tos --email admin@$DOMAIN; then
    success "SSL certificate installed"
else
    warning "SSL certificate installation failed. You may need to configure it manually."
fi

# 14. Final Configuration Check
info "14. Running configuration check..."
cd $PROJECT_DIR
php production-check.php

# 15. Start Services
info "15. Starting all services..."
sudo systemctl enable nginx php8.2-fpm mysql redis-server supervisor
sudo systemctl start nginx php8.2-fpm mysql redis-server supervisor

success "All services started"

echo ""
echo "🎉 DEPLOYMENT COMPLETED SUCCESSFULLY!"
echo "====================================="
echo ""
echo "🔗 Your application is now available at:"
echo "   https://$DOMAIN"
echo "   https://www.$DOMAIN"
echo ""
echo "🔑 Admin Panel:"
echo "   https://$DOMAIN/admin/dashboard"
echo ""
echo "📡 API Endpoints:"
echo "   Stream Webhook: https://$DOMAIN/api/stream-webhook"
echo "   VPS Stats Webhook: https://$DOMAIN/api/vps-stats"
echo "   Secure Download: https://$DOMAIN/api/secure-download/{token}"
echo ""
echo "📊 Monitoring Commands:"
echo "   Check queue workers: sudo supervisorctl status"
echo "   View application logs: tail -f $PROJECT_DIR/storage/logs/laravel.log"
echo "   View worker logs: tail -f $PROJECT_DIR/storage/logs/worker.log"
echo "   Check SSL certificate: sudo certbot certificates"
echo ""
echo "🚀 Your VPS Live Stream Control system is ready for production!"

# Create update script
cat > $PROJECT_DIR/update.sh << 'EOF'
#!/bin/bash
# Quick update script for future deployments

cd /var/www/vps-live-stream

echo "🔄 Updating VPS Live Stream Control..."

# Pull latest code
git pull origin main

# Update dependencies
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

echo "✅ Update completed!"
EOF

chmod +x $PROJECT_DIR/update.sh

success "Update script created at $PROJECT_DIR/update.sh" 