#!/bin/bash

# EZSTREAM Cache Clear Script
# Quick script to clear all caches on production
# Usage: bash scripts/clear-cache.sh

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

PROJECT_DIR="/var/www/ezstream"

echo -e "${BLUE}🧹 EZSTREAM Cache Clear Script${NC}"
echo -e "${BLUE}==============================${NC}"
echo ""

# Change to project directory
cd $PROJECT_DIR

echo -e "${YELLOW}🧹 Clearing Laravel caches...${NC}"
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
php artisan optimize:clear

echo -e "${YELLOW}🎨 Clearing compiled views...${NC}"
rm -rf $PROJECT_DIR/storage/framework/views/*
php artisan view:clear

echo -e "${YELLOW}🔄 Clearing Livewire component cache...${NC}"
rm -rf $PROJECT_DIR/storage/framework/cache/livewire-components.php
php artisan livewire:discover 2>/dev/null || echo "Livewire discover command not available"

echo -e "${YELLOW}💾 Clearing application cache...${NC}"
php artisan cache:forget users_files_* 2>/dev/null || true
php artisan cache:forget stream_* 2>/dev/null || true

echo -e "${YELLOW}🔄 Clearing OPcache...${NC}"
# Clear OPcache if available
if command -v php >/dev/null 2>&1; then
    php -r "if (function_exists('opcache_reset')) { opcache_reset(); echo 'OPcache cleared'; } else { echo 'OPcache not available'; }" || true
fi

echo -e "${YELLOW}🔄 Restarting PHP-FPM...${NC}"
systemctl reload php8.2-fpm

echo -e "${YELLOW}⚡ Re-optimizing application...${NC}"
php artisan config:cache
php artisan route:cache
# Skip view:cache for Livewire compatibility

echo -e "${YELLOW}🔐 Setting permissions...${NC}"
chown -R www-data:www-data $PROJECT_DIR/storage
chmod -R 775 $PROJECT_DIR/storage
chmod -R 775 $PROJECT_DIR/bootstrap/cache

echo ""
echo -e "${GREEN}✅ All caches cleared successfully!${NC}"
echo ""
echo -e "${BLUE}💡 If UI issues persist, try:${NC}"
echo -e "  • Hard refresh browser (Ctrl+F5)"
echo -e "  • Clear browser cache"
echo -e "  • Check browser console for errors"
echo ""
