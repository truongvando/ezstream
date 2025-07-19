#!/bin/bash

# EZSTREAM Rollback Script
# Usage: bash rollback.sh [backup_file]

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Configuration
PROJECT_DIR="/var/www/ezstream"
BACKUP_DIR="/var/backups/ezstream"
DB_NAME="sql_ezstream_pro"
DB_USER="root"
DB_PASS="Dodz1997a@"

echo -e "${BLUE}🔄 EZSTREAM Rollback Starting...${NC}"

# List available backups if no file specified
if [ -z "$1" ]; then
    echo -e "${YELLOW}📋 Available database backups:${NC}"
    ls -la $BACKUP_DIR/database_*.sql 2>/dev/null || echo "No backups found"
    echo ""
    echo -e "${YELLOW}Usage: bash rollback.sh [backup_file]${NC}"
    echo -e "${YELLOW}Example: bash rollback.sh $BACKUP_DIR/database_20250719_143000.sql${NC}"
    exit 1
fi

BACKUP_FILE="$1"

# Check if backup file exists
if [ ! -f "$BACKUP_FILE" ]; then
    echo -e "${RED}❌ Backup file not found: $BACKUP_FILE${NC}"
    exit 1
fi

echo -e "${YELLOW}📁 Backup file: $BACKUP_FILE${NC}"
echo -e "${YELLOW}⚠️  This will restore the database to the backup state.${NC}"
echo -e "${YELLOW}⚠️  Current data will be lost!${NC}"
echo ""

# Confirmation
read -p "Are you sure you want to proceed? (yes/no): " -r
if [[ ! $REPLY =~ ^[Yy][Ee][Ss]$ ]]; then
    echo -e "${YELLOW}❌ Rollback cancelled${NC}"
    exit 1
fi

cd $PROJECT_DIR

# Put application in maintenance mode
echo -e "${YELLOW}🔧 Enabling maintenance mode...${NC}"
php artisan down --message="Rolling back application..." --retry=60

# Create current backup before rollback
echo -e "${YELLOW}💾 Creating current backup before rollback...${NC}"
CURRENT_BACKUP="$BACKUP_DIR/pre_rollback_$(date +%Y%m%d_%H%M%S).sql"
mysqldump -u $DB_USER -p$DB_PASS $DB_NAME > $CURRENT_BACKUP
echo -e "${GREEN}✅ Current state backed up to: $CURRENT_BACKUP${NC}"

# Restore database
echo -e "${YELLOW}🗄️ Restoring database...${NC}"
mysql -u $DB_USER -p$DB_PASS $DB_NAME < $BACKUP_FILE
echo -e "${GREEN}✅ Database restored${NC}"

# Clear all caches
echo -e "${YELLOW}🧹 Clearing caches...${NC}"
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
php artisan optimize:clear

# Restart services
echo -e "${YELLOW}🔄 Restarting services...${NC}"
systemctl reload php8.2-fpm
systemctl reload nginx

# Test application
echo -e "${YELLOW}🧪 Testing application...${NC}"
if php artisan tinker --execute="echo 'Laravel is working!'; exit;"; then
    echo -e "${GREEN}✅ Laravel test passed${NC}"
else
    echo -e "${RED}❌ Laravel test failed${NC}"
    echo -e "${YELLOW}🔄 Bringing application back up anyway...${NC}"
fi

# Bring application back up
echo -e "${YELLOW}🔄 Disabling maintenance mode...${NC}"
php artisan up

echo -e "${GREEN}🎉 Rollback completed!${NC}"
echo -e "${BLUE}📊 Rollback Summary:${NC}"
echo -e "  • Restored from: $BACKUP_FILE"
echo -e "  • Current backup: $CURRENT_BACKUP"
echo -e "  • Timestamp: $(date)"
echo -e "  • Website: https://ezstream.pro"
