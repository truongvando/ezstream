#!/bin/bash

# Laravel Deployment Script for EZSTREAM
# Run after uploading source code to /var/www/ezstream

set -e

PROJECT_PATH="/var/www/ezstream"
DOMAIN="your-domain.com"  # Thay đổi domain của bạn

echo "🚀 Deploying Laravel EZSTREAM..."

# Navigate to project directory
cd $PROJECT_PATH

# Install PHP dependencies
echo "📦 Installing Composer dependencies..."
composer install --no-dev --optimize-autoloader

# Install Node.js dependencies and build assets
echo "🎨 Building frontend assets..."
npm install
npm run build

# Set proper permissions
echo "🔐 Setting permissions..."
sudo chown -R www-data:www-data $PROJECT_PATH
sudo chmod -R 755 $PROJECT_PATH
sudo chmod -R 775 $PROJECT_PATH/storage
sudo chmod -R 775 $PROJECT_PATH/bootstrap/cache

# Create .env file if not exists
if [ ! -f .env ]; then
    echo "📝 Creating .env file..."
    cp .env.example .env
    
    # Generate application key
    php artisan key:generate
fi

# Clear and cache config
echo "🧹 Clearing caches..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Cache for production
echo "⚡ Caching for production..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Create database and run migrations
echo "🗄️ Setting up database..."
read -p "Enter MySQL root password: " -s mysql_password
echo

# Create database
mysql -u root -p$mysql_password -e "CREATE DATABASE IF NOT EXISTS ezstream_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p$mysql_password -e "CREATE USER IF NOT EXISTS 'ezstream_user'@'localhost' IDENTIFIED BY 'ezstream_password_123';"
mysql -u root -p$mysql_password -e "GRANT ALL PRIVILEGES ON ezstream_db.* TO 'ezstream_user'@'localhost';"
mysql -u root -p$mysql_password -e "FLUSH PRIVILEGES;"

# Update .env with database info
sed -i "s/DB_DATABASE=.*/DB_DATABASE=ezstream_db/" .env
sed -i "s/DB_USERNAME=.*/DB_USERNAME=ezstream_user/" .env
sed -i "s/DB_PASSWORD=.*/DB_PASSWORD=ezstream_password_123/" .env
sed -i "s/APP_ENV=.*/APP_ENV=production/" .env
sed -i "s/APP_DEBUG=.*/APP_DEBUG=false/" .env
sed -i "s|APP_URL=.*|APP_URL=https://$DOMAIN|" .env

# Run migrations
php artisan migrate --force

# Create storage link
php artisan storage:link

echo "✅ Laravel deployment completed!"
echo ""
echo "Next step: Configure Nginx virtual host"
