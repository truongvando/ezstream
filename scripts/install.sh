#!/bin/bash

# EZSTREAM VPS Installation Script
# Usage: bash install.sh

set -e

echo "🚀 EZSTREAM VPS Installation Starting..."

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration
DOMAIN="ezstream.pro"
PROJECT_DIR="/var/www/ezstream"
DB_NAME="sql_ezstream_pro"
DB_USER="root"
DB_PASS="Dodz1997a@"

echo -e "${YELLOW}📋 Configuration:${NC}"
echo "Domain: $DOMAIN"
echo "Project Dir: $PROJECT_DIR"
echo "Database: $DB_NAME"
echo "DB User: $DB_USER"
echo ""

# Update system
echo -e "${YELLOW}📦 Updating system...${NC}"
apt update && apt upgrade -y

# Install basic packages
echo -e "${YELLOW}📦 Installing basic packages...${NC}"
apt install -y curl wget git unzip software-properties-common apt-transport-https ca-certificates gnupg lsb-release

# Add PHP repository
echo -e "${YELLOW}📦 Adding PHP repository...${NC}"
add-apt-repository ppa:ondrej/php -y
apt update

# Install PHP 8.2 and extensions
echo -e "${YELLOW}🐘 Installing PHP 8.2...${NC}"
apt install -y php8.2 php8.2-cli php8.2-fpm php8.2-mysql php8.2-mbstring php8.2-xml php8.2-curl php8.2-zip php8.2-gd php8.2-intl php8.2-bcmath php8.2-redis php8.2-imagick

# Set PHP 8.2 as default
update-alternatives --set php /usr/bin/php8.2

# Install Composer
echo -e "${YELLOW}🎼 Installing Composer...${NC}"
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer
chmod +x /usr/local/bin/composer

# Install Node.js 18
echo -e "${YELLOW}📦 Installing Node.js 18...${NC}"
curl -fsSL https://deb.nodesource.com/setup_18.x | bash -
apt install -y nodejs

# Install MySQL
echo -e "${YELLOW}🗄️ Installing MySQL...${NC}"
apt install -y mysql-server

# Secure MySQL installation
echo -e "${YELLOW}🔒 Configuring MySQL...${NC}"
mysql -e "ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY '$DB_PASS';"
mysql -e "DELETE FROM mysql.user WHERE User='';"
mysql -e "DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');"
mysql -e "DROP DATABASE IF EXISTS test;"
mysql -e "DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';"
mysql -e "FLUSH PRIVILEGES;"

# Create database
echo -e "${YELLOW}🗄️ Creating database...${NC}"
mysql -u root -p$DB_PASS -e "CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Install Redis
echo -e "${YELLOW}📦 Installing Redis...${NC}"
apt install -y redis-server
systemctl enable redis-server
systemctl start redis-server

# Install Nginx
echo -e "${YELLOW}🌐 Installing Nginx...${NC}"
apt install -y nginx

# Install Certbot for SSL
echo -e "${YELLOW}🔒 Installing Certbot...${NC}"
apt install -y certbot python3-certbot-nginx

# Create project directory
echo -e "${YELLOW}📁 Creating project directory...${NC}"
mkdir -p $PROJECT_DIR
cd $PROJECT_DIR

# Clone project (you need to replace with your repo)
echo -e "${YELLOW}📥 Cloning project...${NC}"
# git clone https://github.com/yourusername/ezstream.git .
echo "⚠️  Please clone your project manually or update this script with your repo URL"

# Set permissions
echo -e "${YELLOW}🔐 Setting permissions...${NC}"
chown -R www-data:www-data $PROJECT_DIR
chmod -R 755 $PROJECT_DIR
chmod -R 775 $PROJECT_DIR/storage
chmod -R 775 $PROJECT_DIR/bootstrap/cache

echo -e "${GREEN}✅ Basic installation completed!${NC}"
echo -e "${YELLOW}📝 Next steps:${NC}"
echo "1. Clone your project to $PROJECT_DIR"
echo "2. Run: bash scripts/setup-project.sh"
echo "3. Run: bash scripts/setup-nginx.sh"
