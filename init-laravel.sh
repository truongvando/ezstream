#!/bin/bash

echo "🚀 Khởi tạo dự án Laravel..."

# Tạo Laravel project tạm thời
composer create-project laravel/laravel temp-laravel --prefer-dist

# Di chuyển các file từ temp-laravel vào thư mục gốc
cp -r temp-laravel/* .
cp temp-laravel/.env.example .
cp temp-laravel/.gitignore .

# Xóa thư mục tạm
rm -rf temp-laravel

echo "✅ Đã tạo xong Laravel project!"

# Cài đặt Laravel Breeze
echo "📦 Cài đặt Laravel Breeze..."
composer require laravel/breeze --dev

echo "🔧 Cấu hình Breeze..."
php artisan breeze:install blade --dark

echo "📋 Cài đặt npm dependencies..."
npm install

echo "🔑 Tạo application key..."
php artisan key:generate

echo "🗄️ Chạy migrations..."
php artisan migrate

echo "🎨 Build assets..."
npm run build

echo "🎉 Hoàn thành khởi tạo dự án!" 