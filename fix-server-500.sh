#!/bin/bash

# Script fix nhanh lỗi 500 trên server production
# Chạy script này để tự động fix các lỗi thường gặp

echo "🔧 AUTO FIX SERVER 500 ERROR - EZSTREAM"
echo "======================================"

# Màu sắc
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Đường dẫn project
PROJECT_PATH="/var/www/ezstream"

print_step() {
    echo -e "\n${BLUE}🔧 $1${NC}"
}

print_success() {
    echo -e "${GREEN}✅ $1${NC}"
}

print_error() {
    echo -e "${RED}❌ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠️ $1${NC}"
}

# Kiểm tra quyền root
if [[ $EUID -ne 0 ]]; then
   print_error "Script này cần chạy với quyền root (sudo)"
   exit 1
fi

cd "$PROJECT_PATH" || {
    print_error "Không thể truy cập thư mục $PROJECT_PATH"
    exit 1
}

# 1. Fix quyền thư mục
print_step "1. FIX QUYỀN THƯ MỤC"
chown -R www-data:www-data "$PROJECT_PATH"
chmod -R 755 "$PROJECT_PATH"
chmod -R 775 "$PROJECT_PATH/storage"
chmod -R 775 "$PROJECT_PATH/bootstrap/cache"
print_success "Đã fix quyền thư mục"

# 2. Tạo thư mục cần thiết
print_step "2. TẠO THƯ MỤC CẦN THIẾT"
mkdir -p "$PROJECT_PATH/storage/logs"
mkdir -p "$PROJECT_PATH/storage/framework/cache"
mkdir -p "$PROJECT_PATH/storage/framework/sessions"
mkdir -p "$PROJECT_PATH/storage/framework/views"
mkdir -p "$PROJECT_PATH/storage/app/public"
mkdir -p "$PROJECT_PATH/bootstrap/cache"
print_success "Đã tạo các thư mục cần thiết"

# 3. Clear all caches
print_step "3. CLEAR ALL CACHES"
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
php artisan optimize:clear
print_success "Đã xóa tất cả cache"

# 4. Kiểm tra và tạo APP_KEY
print_step "4. KIỂM TRA APP_KEY"
if ! grep -q "APP_KEY=base64:" "$PROJECT_PATH/.env" 2>/dev/null; then
    print_warning "APP_KEY chưa được set, đang tạo..."
    php artisan key:generate --force
    print_success "Đã tạo APP_KEY mới"
else
    print_success "APP_KEY đã tồn tại"
fi

# 5. Chạy migrations
print_step "5. CHẠY DATABASE MIGRATIONS"
php artisan migrate --force
print_success "Đã chạy migrations"

# 6. Create storage link
print_step "6. TẠO STORAGE LINK"
php artisan storage:link
print_success "Đã tạo storage link"

# 7. Optimize autoloader
print_step "7. OPTIMIZE AUTOLOADER"
if command -v composer >/dev/null 2>&1; then
    composer dump-autoload --optimize --no-dev
    print_success "Đã optimize autoloader"
else
    print_warning "Composer không tìm thấy, bỏ qua bước này"
fi

# 8. Cache config for production
print_step "8. CACHE CONFIG CHO PRODUCTION"
php artisan config:cache
php artisan route:cache
php artisan view:cache
print_success "Đã cache config cho production"

# 9. Fix SELinux (nếu có)
print_step "9. FIX SELINUX (NẾU CÓ)"
if command -v setsebool >/dev/null 2>&1; then
    setsebool -P httpd_can_network_connect 1
    setsebool -P httpd_can_network_relay 1
    print_success "Đã fix SELinux"
else
    print_warning "SELinux không có hoặc đã disabled"
fi

# 10. Restart services
print_step "10. RESTART SERVICES"
systemctl restart nginx
systemctl restart php8.2-fpm 2>/dev/null || systemctl restart php8.1-fpm 2>/dev/null || systemctl restart php-fpm
systemctl restart mysql 2>/dev/null || systemctl restart mariadb 2>/dev/null || true

# Kiểm tra status
if systemctl is-active --quiet nginx; then
    print_success "Nginx đã restart thành công"
else
    print_error "Nginx restart thất bại"
fi

if systemctl is-active --quiet php8.2-fpm || systemctl is-active --quiet php8.1-fpm || systemctl is-active --quiet php-fpm; then
    print_success "PHP-FPM đã restart thành công"
else
    print_error "PHP-FPM restart thất bại"
fi

# 11. Test website
print_step "11. TEST WEBSITE"
echo "🧪 Tạo file test..."
cat > "$PROJECT_PATH/public/health-check.php" << 'EOF'
<?php
echo json_encode([
    'status' => 'OK',
    'timestamp' => date('Y-m-d H:i:s'),
    'php_version' => PHP_VERSION,
    'laravel_path' => realpath(__DIR__ . '/../'),
    'writable_storage' => is_writable(__DIR__ . '/../storage'),
    'writable_cache' => is_writable(__DIR__ . '/../bootstrap/cache')
]);
EOF

print_success "File health-check.php đã tạo"
echo "Truy cập: http://your-domain.com/health-check.php để test"

# 12. Tạo file info để debug
print_step "12. TẠO FILE DEBUG INFO"
cat > "$PROJECT_PATH/debug-info.txt" << EOF
=== EZSTREAM DEBUG INFO ===
Generated: $(date)
PHP Version: $(php -v | head -1)
Laravel Version: $(php artisan --version 2>/dev/null || echo "Unknown")
Project Path: $PROJECT_PATH
Storage Writable: $([ -w "$PROJECT_PATH/storage" ] && echo "YES" || echo "NO")
Cache Writable: $([ -w "$PROJECT_PATH/bootstrap/cache" ] && echo "YES" || echo "NO")
.env Exists: $([ -f "$PROJECT_PATH/.env" ] && echo "YES" || echo "NO")
APP_KEY Set: $(grep -q "APP_KEY=base64:" "$PROJECT_PATH/.env" 2>/dev/null && echo "YES" || echo "NO")

=== SERVICES STATUS ===
Nginx: $(systemctl is-active nginx)
PHP-FPM: $(systemctl is-active php8.2-fpm 2>/dev/null || systemctl is-active php8.1-fpm 2>/dev/null || systemctl is-active php-fpm 2>/dev/null || echo "inactive")
MySQL: $(systemctl is-active mysql 2>/dev/null || systemctl is-active mariadb 2>/dev/null || echo "inactive")

=== RECENT ERRORS ===
$(tail -10 "$PROJECT_PATH/storage/logs/laravel.log" 2>/dev/null || echo "No Laravel logs found")
EOF

print_success "Debug info đã lưu vào debug-info.txt"

# Kết thúc
echo -e "\n${GREEN}🎉 AUTO FIX HOÀN THÀNH!${NC}"
echo "================================"
echo "✅ Đã fix quyền thư mục"
echo "✅ Đã clear cache"
echo "✅ Đã tạo APP_KEY (nếu cần)"
echo "✅ Đã chạy migrations"
echo "✅ Đã restart services"
echo ""
echo "🔍 Kiểm tra:"
echo "1. Truy cập website để xem còn lỗi 500 không"
echo "2. Xem file health-check.php: http://your-domain.com/health-check.php"
echo "3. Xem debug-info.txt để biết thêm chi tiết"
echo ""
echo "📞 Nếu vẫn lỗi, chạy: ./debug-server-500.sh để debug chi tiết"
