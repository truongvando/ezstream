#!/bin/bash

# EZSTREAM Deployment Script
# Usage: bash deploy.sh [branch_name]
# Example: bash deploy.sh main

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Configuration
PROJECT_DIR="/var/www/ezstream"
BRANCH=${1:-master}
BACKUP_DIR="/var/backups/ezstream"
DB_NAME="sql_ezstream_pro"
DB_USER="root"
DB_PASS="Dodz1997a@"

echo -e "${BLUE}🚀 EZSTREAM Deployment Starting...${NC}"
echo -e "${YELLOW}Branch: $BRANCH${NC}"
echo -e "${YELLOW}Project Dir: $PROJECT_DIR${NC}"
echo ""

# Create backup directory
mkdir -p $BACKUP_DIR

# Backup database
echo -e "${YELLOW}💾 Creating database backup...${NC}"
BACKUP_FILE="$BACKUP_DIR/database_$(date +%Y%m%d_%H%M%S).sql"
if mysqldump -u $DB_USER -p$DB_PASS $DB_NAME > $BACKUP_FILE 2>/dev/null; then
    gzip $BACKUP_FILE
    echo -e "${GREEN}✅ Database backed up to: $BACKUP_FILE.gz${NC}"
else
    echo -e "${RED}❌ Database backup failed${NC}"
    exit 1
fi

# Backup .env file
echo -e "${YELLOW}💾 Backing up .env file...${NC}"
cp $PROJECT_DIR/.env $BACKUP_DIR/.env.backup.$(date +%Y%m%d_%H%M%S)

# Put application in maintenance mode
echo -e "${YELLOW}🔧 Enabling maintenance mode...${NC}"
cd $PROJECT_DIR
php artisan down --message="Updating application..." --retry=60

# Pull latest code
echo -e "${YELLOW}📥 Pulling latest code...${NC}"
git fetch origin
git reset --hard origin/$BRANCH
git clean -fd

# Install/Update Composer dependencies
echo -e "${YELLOW}📦 Updating Composer dependencies...${NC}"
composer install --no-dev --optimize-autoloader --no-interaction

# Install/Update NPM dependencies
echo -e "${YELLOW}📦 Updating NPM dependencies...${NC}"
npm ci --production

# Build assets
echo -e "${YELLOW}🏗️ Building assets...${NC}"
npm run build

# Clear all caches
echo -e "${YELLOW}🧹 Clearing caches...${NC}"
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
php artisan optimize:clear

# Run database migrations
echo -e "${YELLOW}🗄️ Running database migrations...${NC}"
php artisan migrate --force

# Recreate storage link
echo -e "${YELLOW}🔗 Recreating storage link...${NC}"
php artisan storage:link

# Optimize application
echo -e "${YELLOW}⚡ Optimizing application...${NC}"
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Set permissions
echo -e "${YELLOW}🔐 Setting permissions...${NC}"
chown -R www-data:www-data $PROJECT_DIR
chmod -R 755 $PROJECT_DIR
chmod -R 775 $PROJECT_DIR/storage
chmod -R 775 $PROJECT_DIR/bootstrap/cache
chmod 600 $PROJECT_DIR/.env

# Restart services
echo -e "${YELLOW}🔄 Restarting services...${NC}"
systemctl reload php8.2-fpm
systemctl reload nginx

# Restart background processes
echo -e "${YELLOW}🔄 Restarting background processes...${NC}"
if command -v supervisorctl &> /dev/null; then
    supervisorctl restart ezstream-queue:* || echo "Queue processes not found"
    supervisorctl restart ezstream-stream:* || echo "Stream processes not found"
    supervisorctl restart ezstream-redis:* || echo "Redis processes not found"
    supervisorctl restart ezstream-schedule:* || echo "Schedule processes not found"
else
    echo "⚠️ Supervisor not installed, skipping process restart"
fi

# Test application
echo -e "${YELLOW}🧪 Testing application...${NC}"
if php artisan tinker --execute="echo 'Laravel is working!'; exit;"; then
    echo -e "${GREEN}✅ Laravel test passed${NC}"
else
    echo -e "${RED}❌ Laravel test failed${NC}"
    echo -e "${YELLOW}🔄 Bringing application back up...${NC}"
    php artisan up
    exit 1
fi

# Bring application back up
echo -e "${YELLOW}🔄 Disabling maintenance mode...${NC}"
php artisan up

# Clean old backups (keep last 10)
echo -e "${YELLOW}🧹 Cleaning old backups...${NC}"
find $BACKUP_DIR -name "database_*.sql.gz" -type f | sort -r | tail -n +11 | xargs rm -f
find $BACKUP_DIR -name ".env.backup.*" -type f | sort -r | tail -n +11 | xargs rm -f

echo -e "${GREEN}🎉 Deployment completed successfully!${NC}"
echo -e "${BLUE}📊 Deployment Summary:${NC}"
echo -e "  • Database backup: $BACKUP_FILE"
echo -e "  • Branch deployed: $BRANCH"
echo -e "  • Timestamp: $(date)"
echo -e "  • Website: https://ezstream.pro"
echo ""
echo -e "${YELLOW}💡 To rollback if needed:${NC}"
echo -e "  gunzip $BACKUP_FILE.gz"
echo -e "  mysql -u $DB_USER -p$DB_PASS $DB_NAME < \${BACKUP_FILE%.gz}"
