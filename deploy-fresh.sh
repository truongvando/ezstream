#!/bin/bash
# Script deploy EZSTREAM từ đầu lên VPS

echo "🚀 Bắt đầu deploy EZSTREAM từ đầu..."

# Cấu hình
PROJECT_DIR="/var/www/ezstream"
BACKUP_DIR="/var/backups/ezstream"
DB_NAME="ezstream_prod"
DB_USER="ezstream_user"
DB_PASS="$(openssl rand -base64 32)"

# Tạo backup nếu có data cũ
if [ -d "$PROJECT_DIR" ]; then
    echo "💾 Backup dữ liệu cũ..."
    mkdir -p $BACKUP_DIR
    cp -r $PROJECT_DIR $BACKUP_DIR/ezstream_$(date +%Y%m%d_%H%M%S)
    
    # Backup database nếu tồn tại
    if mysql -e "use $DB_NAME" 2>/dev/null; then
        mysqldump $DB_NAME > $BACKUP_DIR/database_$(date +%Y%m%d_%H%M%S).sql
    fi
fi

# Xóa thư mục cũ
echo "🗑️ Xóa installation cũ..."
rm -rf $PROJECT_DIR

# Tạo thư mục mới
echo "📁 Tạo thư mục project..."
mkdir -p $PROJECT_DIR
cd $PROJECT_DIR

# Giải nén code (giả sử đã upload ezstream.zip)
echo "📦 Giải nén code..."
unzip -q /tmp/ezstream.zip -d $PROJECT_DIR
# Hoặc nếu dùng tar: tar -xzf /tmp/ezstream.tar.gz -C $PROJECT_DIR

# Cài đặt dependencies
echo "📦 Cài đặt PHP dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

echo "🎨 Cài đặt Node dependencies..."
npm install --production

echo "🏗️ Build frontend assets..."
npm run build

# Tạo database và user
echo "🗄️ Tạo database..."
mysql -e "CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';"
mysql -e "GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'localhost';"
mysql -e "FLUSH PRIVILEGES;"

# Tạo .env từ template
echo "⚙️ Cấu hình environment..."
cp .env.example .env

# Cập nhật .env với thông tin thực
cat > .env << EOF
APP_NAME=EZSTREAM
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_TIMEZONE=Asia/Ho_Chi_Minh
APP_URL=https://ezstream.pro

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=$DB_NAME
DB_USERNAME=$DB_USER
DB_PASSWORD=$DB_PASS

BROADCAST_DRIVER=log
CACHE_DRIVER=redis
FILESYSTEM_DISK=local
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=smtp.resend.com
MAIL_PORT=587
MAIL_USERNAME=resend
MAIL_PASSWORD=re_45oSQ3hD_3HG893ERTRragEv17At5Eufi
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@ezstream.pro
MAIL_FROM_NAME="EZSTREAM"
EOF

# Generate app key
echo "🔑 Generate application key..."
php artisan key:generate --force

# Chạy migrations
echo "🗄️ Chạy database migrations..."
php artisan migrate --force

# Seed data nếu cần
echo "🌱 Seed initial data..."
php artisan db:seed --force

# Tạo storage link
echo "🔗 Tạo storage link..."
php artisan storage:link

# Clear và cache
echo "🧹 Clear cache..."
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear

echo "⚡ Tạo cache mới..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Cập nhật quyền
echo "🔐 Cập nhật quyền..."
chown -R www-data:www-data $PROJECT_DIR
chmod -R 755 $PROJECT_DIR
chmod -R 775 $PROJECT_DIR/storage
chmod -R 775 $PROJECT_DIR/bootstrap/cache

# Restart services
echo "🔄 Restart services..."
systemctl restart php8.2-fpm
systemctl restart nginx

# Setup supervisor cho queue
echo "👷 Setup queue worker..."
cat > /etc/supervisor/conf.d/ezstream-worker.conf << EOF
[program:ezstream-worker]
process_name=%(program_name)s_%(process_num)02d
command=php $PROJECT_DIR/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=$PROJECT_DIR/storage/logs/worker.log
stopwaitsecs=3600
EOF

supervisorctl reread
supervisorctl update
supervisorctl start ezstream-worker:*

echo "✅ Deploy hoàn tất!"
echo "🌐 Website: https://ezstream.pro"
echo "🗄️ Database: $DB_NAME"
echo "👤 DB User: $DB_USER"
echo "🔑 DB Pass: $DB_PASS"
echo ""
echo "📝 Lưu thông tin database này!"
