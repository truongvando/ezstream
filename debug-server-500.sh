#!/bin/bash

# Script debug lỗi 500 trên server production
# Chạy script này trên VPS để tìm nguyên nhân lỗi

echo "🔍 DEBUGGING SERVER 500 ERROR - EZSTREAM"
echo "========================================"

# Màu sắc cho output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Đường dẫn project (thay đổi nếu cần)
PROJECT_PATH="/var/www/ezstream"

print_section() {
    echo -e "\n${BLUE}=== $1 ===${NC}"
}

print_error() {
    echo -e "${RED}❌ $1${NC}"
}

print_success() {
    echo -e "${GREEN}✅ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠️ $1${NC}"
}

# 1. Kiểm tra Laravel logs
print_section "1. KIỂM TRA LARAVEL LOGS"
if [ -f "$PROJECT_PATH/storage/logs/laravel.log" ]; then
    echo "📄 Laravel log (50 dòng cuối):"
    tail -50 "$PROJECT_PATH/storage/logs/laravel.log"
    echo ""
    
    # Tìm lỗi gần đây nhất
    echo "🔍 Lỗi gần đây nhất:"
    grep -i "error\|exception\|fatal" "$PROJECT_PATH/storage/logs/laravel.log" | tail -5
else
    print_error "Laravel log file không tồn tại!"
fi

# 2. Kiểm tra Nginx logs
print_section "2. KIỂM TRA NGINX LOGS"
if [ -f "/var/log/nginx/error.log" ]; then
    echo "📄 Nginx error log (20 dòng cuối):"
    tail -20 /var/log/nginx/error.log
else
    print_error "Nginx error log không tồn tại!"
fi

# 3. Kiểm tra PHP-FPM logs
print_section "3. KIỂM TRA PHP-FPM LOGS"
PHP_FPM_LOG="/var/log/php8.2-fpm.log"
if [ -f "$PHP_FPM_LOG" ]; then
    echo "📄 PHP-FPM log (20 dòng cuối):"
    tail -20 "$PHP_FPM_LOG"
else
    # Thử các đường dẫn khác
    for log_path in "/var/log/php-fpm/www-error.log" "/var/log/php8.1-fpm.log" "/var/log/php-fpm.log"; do
        if [ -f "$log_path" ]; then
            echo "📄 PHP-FPM log tại $log_path:"
            tail -20 "$log_path"
            break
        fi
    done
fi

# 4. Kiểm tra quyền thư mục
print_section "4. KIỂM TRA QUYỀN THỦ MỤC"
echo "📁 Quyền thư mục storage:"
ls -la "$PROJECT_PATH/storage/"

echo "📁 Quyền thư mục bootstrap/cache:"
ls -la "$PROJECT_PATH/bootstrap/cache/"

# 5. Kiểm tra file .env
print_section "5. KIỂM TRA FILE .ENV"
if [ -f "$PROJECT_PATH/.env" ]; then
    print_success ".env file tồn tại"
    echo "🔧 Các biến quan trọng:"
    grep -E "^(APP_ENV|APP_DEBUG|APP_KEY|DB_|CACHE_|QUEUE_)" "$PROJECT_PATH/.env" | head -10
else
    print_error ".env file không tồn tại!"
fi

# 6. Kiểm tra database connection
print_section "6. KIỂM TRA DATABASE CONNECTION"
cd "$PROJECT_PATH"
if command -v php >/dev/null 2>&1; then
    echo "🔌 Test database connection:"
    php artisan tinker --execute="try { DB::connection()->getPdo(); echo 'Database connected successfully'; } catch(Exception \$e) { echo 'Database error: ' . \$e->getMessage(); }"
else
    print_error "PHP command không tìm thấy!"
fi

# 7. Kiểm tra composer dependencies
print_section "7. KIỂM TRA COMPOSER DEPENDENCIES"
if [ -f "$PROJECT_PATH/composer.json" ]; then
    echo "📦 Kiểm tra autoload:"
    cd "$PROJECT_PATH"
    if command -v composer >/dev/null 2>&1; then
        composer dump-autoload --optimize
        print_success "Autoload regenerated"
    else
        print_warning "Composer không tìm thấy"
    fi
else
    print_error "composer.json không tồn tại!"
fi

# 8. Kiểm tra cache và config
print_section "8. KIỂM TRA CACHE VÀ CONFIG"
cd "$PROJECT_PATH"
echo "🧹 Clearing all caches..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
php artisan optimize:clear
print_success "All caches cleared"

# 9. Kiểm tra services
print_section "9. KIỂM TRA SERVICES"
echo "🔧 Nginx status:"
systemctl status nginx --no-pager -l

echo "🔧 PHP-FPM status:"
systemctl status php8.2-fpm --no-pager -l 2>/dev/null || systemctl status php8.1-fpm --no-pager -l 2>/dev/null || systemctl status php-fpm --no-pager -l

echo "🔧 MySQL status:"
systemctl status mysql --no-pager -l 2>/dev/null || systemctl status mariadb --no-pager -l

# 10. Test simple PHP
print_section "10. TEST SIMPLE PHP"
echo "🧪 Tạo file test PHP:"
cat > /tmp/test.php << 'EOF'
<?php
phpinfo();
EOF

echo "File test.php đã tạo tại /tmp/test.php"
echo "Truy cập: http://your-domain.com/test.php để kiểm tra PHP"

# 11. Recommendations
print_section "11. KHUYẾN NGHỊ"
echo "🔧 Các bước tiếp theo:"
echo "1. Kiểm tra Laravel logs chi tiết ở trên"
echo "2. Đảm bảo file .env có APP_KEY"
echo "3. Chạy: php artisan key:generate (nếu APP_KEY trống)"
echo "4. Chạy: php artisan migrate --force"
echo "5. Kiểm tra quyền: chmod -R 755 $PROJECT_PATH"
echo "6. Kiểm tra quyền: chmod -R 777 $PROJECT_PATH/storage"
echo "7. Kiểm tra quyền: chmod -R 777 $PROJECT_PATH/bootstrap/cache"
echo "8. Restart services: systemctl restart nginx php8.2-fpm"

print_section "DEBUG HOÀN THÀNH"
echo "📋 Kết quả debug đã hiển thị ở trên"
echo "📞 Nếu vẫn lỗi, gửi output này để được hỗ trợ"
