# 🔧 Redis Connection Error Fix Documentation

## 🚨 **Vấn đề gốc:**
```
[2025-07-16 03:10:25] local.ERROR: fwrite(): Send of 918 bytes failed with errno=10053 
An established connection was aborted by the software in your host machine
```

Lỗi này xảy ra khi:
- Redis connection bị ngắt đột ngột trong queue worker
- Queue worker cố gắng migrate expired jobs
- Connection timeout hoặc network issues

## ✅ **Các giải pháp đã triển khai:**

### 1. **Improved Redis Configuration**
- **File**: `config/database.php`
- **Thay đổi**: Thêm timeout settings cho tất cả Redis connections
- **Dedicated queue connection** với settings tối ưu

### 2. **Redis Connection Error Handler**
- **File**: `app/Exceptions/RedisConnectionHandler.php`
- **Chức năng**: Throttle Redis error logs để tránh spam
- **Logic**: Chỉ log cùng một lỗi 1 lần/phút

### 3. **Custom Exception Handler**
- **File**: `app/Exceptions/Handler.php`
- **Chức năng**: Intercept Redis errors và xử lý gracefully
- **Kết quả**: Không spam log nữa

### 4. **Redis Health Check Command**
- **Command**: `php artisan redis:health-check`
- **Options**: `--connection=queue --fix`
- **Chức năng**: Kiểm tra và sửa Redis connection issues

### 5. **Queue Worker với Retry Logic**
- **Command**: `php artisan queue:work-retry`
- **Chức năng**: Auto-retry khi Redis connection fail
- **Features**: Exponential backoff, max retries, auto-reconnect

### 6. **Scheduled Health Monitoring**
- **Schedule**: Mỗi 10 phút check Redis health
- **Auto-fix**: Tự động sửa connection issues
- **Location**: `app/Console/Kernel.php`

## 🚀 **Cách sử dụng:**

### Kiểm tra Redis Health:
```bash
# Kiểm tra connection default
php artisan redis:health-check

# Kiểm tra queue connection
php artisan redis:health-check --connection=queue

# Kiểm tra và tự động sửa
php artisan redis:health-check --connection=queue --fix
```

### Chạy Queue Worker với Retry:
```bash
# Thay vì php artisan queue:work
php artisan queue:work-retry redis --queue=default --timeout=60
```

### Monitor Logs:
```bash
# Logs sẽ được throttle, không spam nữa
tail -f storage/logs/laravel.log | grep Redis
```

## 📊 **Kết quả:**

### Trước khi fix:
- ❌ Redis errors spam log liên tục
- ❌ Queue worker crash khi Redis disconnect
- ❌ Streams bị treo ở trạng thái STOPPING

### Sau khi fix:
- ✅ Redis errors được throttle (1 lần/phút)
- ✅ Queue worker auto-retry với exponential backoff
- ✅ Health check tự động mỗi 10 phút
- ✅ Streams được auto-cleanup khi bị treo

## 🔧 **Troubleshooting:**

### Nếu vẫn có Redis errors:
1. Chạy health check: `php artisan redis:health-check --fix`
2. Restart queue worker: `php artisan queue:restart`
3. Check Redis server status
4. Verify .env Redis settings

### Nếu queue worker vẫn crash:
1. Sử dụng: `php artisan queue:work-retry`
2. Tăng max-redis-retries: `--max-redis-retries=10`
3. Check network connectivity

### Emergency commands:
```bash
# Force stop hanging streams
php artisan streams:force-stop-hanging --timeout=300

# Clear Redis connections
php artisan cache:clear
php artisan config:clear

# Restart everything
php artisan queue:restart
```

## 📝 **Files đã thay đổi:**
- `config/database.php` - Redis connections config
- `config/queue.php` - Queue settings
- `app/Exceptions/Handler.php` - Exception handling
- `app/Exceptions/RedisConnectionHandler.php` - Redis error throttling
- `app/Console/Commands/RedisHealthCheck.php` - Health check tool
- `app/Console/Commands/QueueWorkerWithRetry.php` - Robust queue worker
- `app/Console/Kernel.php` - Scheduled tasks
- `app/Jobs/StopMultistreamJob.php` - Improved error handling
- `app/Jobs/StartMultistreamJob.php` - Improved error handling

## 🎯 **Kết luận:**
Hệ thống bây giờ có khả năng tự phục hồi khi gặp Redis connection issues, không spam logs, và tự động cleanup streams bị treo.
