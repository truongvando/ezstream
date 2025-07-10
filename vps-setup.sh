#!/bin/bash

# VPS Setup Script for Laravel + EZSTREAM
# Run with: bash vps-setup.sh

set -e

echo "🚀 Starting VPS Setup for EZSTREAM..."

# Update system
echo "📦 Updating system packages..."
sudo apt update && sudo apt upgrade -y

# Install essential packages
echo "🔧 Installing essential packages..."
sudo apt install -y curl wget git unzip software-properties-common apt-transport-https ca-certificates gnupg lsb-release

# Install PHP 8.2
echo "🐘 Installing PHP 8.2..."
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y php8.2 php8.2-fpm php8.2-mysql php8.2-xml php8.2-curl php8.2-zip php8.2-mbstring php8.2-gd php8.2-bcmath php8.2-intl php8.2-soap php8.2-cli php8.2-common php8.2-opcache

# Install Composer
echo "🎼 Installing Composer..."
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
sudo chmod +x /usr/local/bin/composer

# Install Node.js and npm
echo "📦 Installing Node.js..."
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs

# Install MySQL
echo "🗄️ Installing MySQL..."
sudo apt install -y mysql-server
sudo systemctl start mysql
sudo systemctl enable mysql

# Install Nginx
echo "🌐 Installing Nginx..."
sudo apt install -y nginx
sudo systemctl start nginx
sudo systemctl enable nginx

# Install Redis (for caching)
echo "📮 Installing Redis..."
sudo apt install -y redis-server
sudo systemctl start redis-server
sudo systemctl enable redis-server

# Install Supervisor (for queue workers)
echo "👷 Installing Supervisor..."
sudo apt install -y supervisor
sudo systemctl start supervisor
sudo systemctl enable supervisor

# Install FFmpeg (for video processing)
echo "🎥 Installing FFmpeg..."
sudo apt install -y ffmpeg

# Create web directory
echo "📁 Creating web directory..."
sudo mkdir -p /var/www/ezstream
sudo chown -R $USER:www-data /var/www/ezstream
sudo chmod -R 755 /var/www/ezstream

# Configure firewall
echo "🔥 Configuring firewall..."
sudo ufw allow OpenSSH
sudo ufw allow 'Nginx Full'
sudo ufw allow 3306/tcp
sudo ufw --force enable

echo "✅ VPS setup completed!"
echo ""
echo "Next steps:"
echo "1. Upload your Laravel project to /var/www/ezstream"
echo "2. Run the Laravel setup script"
echo "3. Configure Nginx virtual host"
echo ""
echo "MySQL root password needs to be set. Run: sudo mysql_secure_installation"
