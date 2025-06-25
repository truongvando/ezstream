#!/usr/bin/env pwsh

Write-Host "🚀 Bắt đầu thiết lập dự án VPS Livestream Control..." -ForegroundColor Green

# Kiểm tra Docker
Write-Host "🔍 Kiểm tra Docker..." -ForegroundColor Yellow
if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    Write-Host "❌ Docker chưa được cài đặt. Vui lòng cài đặt Docker Desktop trước." -ForegroundColor Red
    exit 1
}

# Kiểm tra Docker Compose
if (-not (Get-Command docker-compose -ErrorAction SilentlyContinue)) {
    Write-Host "❌ Docker Compose chưa được cài đặt." -ForegroundColor Red
    exit 1
}

Write-Host "✅ Docker đã sẵn sàng!" -ForegroundColor Green

# Khởi tạo Laravel project nếu chưa có
if (-not (Test-Path "composer.json")) {
    Write-Host "📦 Khởi tạo Laravel project..." -ForegroundColor Yellow
    
    # Build image để khởi tạo
    docker build -f Dockerfile.init -t laravel-init .
    
    # Chạy container để khởi tạo Laravel
    docker run --rm -v "${PWD}:/var/www" laravel-init
    
    Write-Host "✅ Đã khởi tạo Laravel project!" -ForegroundColor Green
}

# Copy file .env
if (-not (Test-Path ".env")) {
    Write-Host "📋 Tạo file .env..." -ForegroundColor Yellow
    Copy-Item "env.docker.example" ".env"
    Write-Host "✅ Đã tạo file .env!" -ForegroundColor Green
}

# Khởi động Docker Compose
Write-Host "🐳 Khởi động Docker containers..." -ForegroundColor Yellow
docker-compose up -d --build

Write-Host "⏳ Đợi containers khởi động..." -ForegroundColor Yellow
Start-Sleep -Seconds 10

# Generate application key
Write-Host "🔑 Tạo application key..." -ForegroundColor Yellow
docker-compose exec app php artisan key:generate

# Chạy migrations
Write-Host "🗄️ Chạy database migrations..." -ForegroundColor Yellow
docker-compose exec app php artisan migrate

# Cài đặt npm dependencies và build assets
Write-Host "📦 Cài đặt npm dependencies..." -ForegroundColor Yellow
docker-compose exec app npm install

Write-Host "🎨 Build assets..." -ForegroundColor Yellow
docker-compose exec app npm run build

Write-Host ""
Write-Host "🎉 Thiết lập hoàn tất!" -ForegroundColor Green
Write-Host "🌐 Ứng dụng đang chạy tại: http://localhost:8000" -ForegroundColor Cyan
Write-Host "🗄️ Database: PostgreSQL tại localhost:5432" -ForegroundColor Cyan
Write-Host "🔄 Redis: localhost:6379" -ForegroundColor Cyan
Write-Host ""
Write-Host "📋 Các lệnh hữu ích:" -ForegroundColor White
Write-Host "  - Xem logs: docker-compose logs -f" -ForegroundColor Gray
Write-Host "  - Vào container: docker-compose exec app bash" -ForegroundColor Gray
Write-Host "  - Dừng: docker-compose down" -ForegroundColor Gray
Write-Host "  - Khởi động lại: docker-compose restart" -ForegroundColor Gray 