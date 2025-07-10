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