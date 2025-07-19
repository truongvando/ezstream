#!/bin/bash

# EZSTREAM Project Setup Script
# Usage: bash setup-project.sh

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Configuration
PROJECT_DIR="/var/www/ezstream"
DB_NAME="sql_ezstream_pro"
DB_USER="root"
DB_PASS="Dodz1997a@"
DOMAIN="ezstream.pro"

echo -e "${YELLOW}🔧 Setting up EZSTREAM project...${NC}"

cd $PROJECT_DIR

# Install Composer dependencies
echo -e "${YELLOW}📦 Installing Composer dependencies...${NC}"
composer install --no-dev --optimize-autoloader

# Install NPM dependencies
echo -e "${YELLOW}📦 Installing NPM dependencies...${NC}"
npm install

# Build assets
echo -e "${YELLOW}🏗️ Building assets...${NC}"
npm run build

# Copy .env file
echo -e "${YELLOW}📝 Setting up .env file...${NC}"
if [ ! -f .env ]; then
    cp .env.example .env
fi

# Update .env configuration
echo -e "${YELLOW}⚙️ Configuring .env...${NC}"
sed -i "s/APP_ENV=.*/APP_ENV=production/" .env
sed -i "s/APP_DEBUG=.*/APP_DEBUG=false/" .env
sed -i "s/APP_URL=.*/APP_URL=https:\/\/$DOMAIN/" .env
sed -i "s/DB_DATABASE=.*/DB_DATABASE=$DB_NAME/" .env
sed -i "s/DB_USERNAME=.*/DB_USERNAME=$DB_USER/" .env
sed -i "s/DB_PASSWORD=.*/DB_PASSWORD=$DB_PASS/" .env

# Generate APP_KEY
echo -e "${YELLOW}🔑 Generating APP_KEY...${NC}"
php artisan key:generate

# Clear all caches
echo -e "${YELLOW}🧹 Clearing caches...${NC}"
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
php artisan optimize:clear

# Run migrations
echo -e "${YELLOW}🗄️ Running database migrations...${NC}"
php artisan migrate --force

# Seed database (if needed)
echo -e "${YELLOW}🌱 Seeding database...${NC}"
php artisan db:seed --force

# Create storage link
echo -e "${YELLOW}🔗 Creating storage link...${NC}"
php artisan storage:link

# Set final permissions
echo -e "${YELLOW}🔐 Setting final permissions...${NC}"
chown -R www-data:www-data $PROJECT_DIR
chmod -R 755 $PROJECT_DIR
chmod -R 775 $PROJECT_DIR/storage
chmod -R 775 $PROJECT_DIR/bootstrap/cache

# Start services
echo -e "${YELLOW}🚀 Starting services...${NC}"
systemctl start php8.2-fpm
systemctl enable php8.2-fpm
systemctl start mysql
systemctl enable mysql
systemctl start redis-server
systemctl enable redis-server

echo -e "${GREEN}✅ Project setup completed!${NC}"
echo -e "${YELLOW}📝 Next step: Run bash scripts/setup-nginx.sh${NC}"
