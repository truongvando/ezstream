# Database Setup cho Production

## Bước 1: Copy files
```bash
# Copy migrations
cp migrations/* /var/www/vps-live-stream/database/migrations/

# Copy seeders  
cp seeders/* /var/www/vps-live-stream/database/seeders/
```

## Bước 2: Chạy migrations
```bash
cd /var/www/vps-live-stream
php artisan migrate --force
```

## Bước 3: Chạy seeders
```bash
php artisan db:seed --class=AdminUserSeeder
php artisan db:seed --class=ServicePackageSeeder
php artisan db:seed --class=PaymentSettingsSeeder
php artisan db:seed --class=VpsServerSeeder
```

## Bước 4: Cache config
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Các bảng sẽ được tạo:
- users (người dùng)
- service_packages (gói dịch vụ)
- subscriptions (đăng ký)
- transactions (giao dịch)
- vps_servers (máy chủ VPS)
- user_files (file người dùng)
- stream_configurations (cấu hình stream)
- vps_stats (thống kê VPS)
- settings (cài đặt hệ thống)
- password_reset_tokens (reset password)
- sessions (phiên làm việc)
