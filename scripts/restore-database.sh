#!/bin/bash

# EZSTREAM Database Restore Script
# Usage: bash restore-database.sh [backup_file]

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Configuration
DB_NAME="sql_ezstream_pro"
DB_USER="root"
DB_PASS="Dodz1997a@"
BACKUP_DIR="/var/backups/ezstream"

echo -e "${BLUE}🔄 EZSTREAM Database Restore${NC}"
echo "$(date)"
echo "=================================="

# List available backups if no file specified
if [ -z "$1" ]; then
    echo -e "${YELLOW}📋 Available backups:${NC}"
    ls -lah $BACKUP_DIR/database_*.sql.gz 2>/dev/null || echo "No backups found"
    echo ""
    echo -e "${YELLOW}Usage: bash restore-database.sh [backup_file]${NC}"
    echo -e "${YELLOW}Example: bash restore-database.sh $BACKUP_DIR/database_20250719_143000.sql.gz${NC}"
    exit 1
fi

BACKUP_FILE="$1"

# Check if backup file exists
if [ ! -f "$BACKUP_FILE" ]; then
    echo -e "${RED}❌ Backup file not found: $BACKUP_FILE${NC}"
    exit 1
fi

echo -e "${YELLOW}📁 Restore file: $BACKUP_FILE${NC}"

# Confirmation
echo -e "${YELLOW}⚠️  This will replace the current database!${NC}"
echo -e "${YELLOW}⚠️  Current data will be lost!${NC}"
echo ""
read -p "Are you sure you want to proceed? (yes/no): " -r
if [[ ! $REPLY =~ ^[Yy][Ee][Ss]$ ]]; then
    echo -e "${YELLOW}❌ Restore cancelled${NC}"
    exit 1
fi

# Create current backup before restore
echo -e "${YELLOW}💾 Creating current backup before restore...${NC}"
CURRENT_BACKUP="$BACKUP_DIR/pre_restore_$(date +%Y%m%d_%H%M%S).sql"
if mysqldump -u $DB_USER -p$DB_PASS $DB_NAME > $CURRENT_BACKUP 2>/dev/null; then
    gzip $CURRENT_BACKUP
    echo -e "${GREEN}✅ Current state backed up to: $CURRENT_BACKUP.gz${NC}"
else
    echo -e "${RED}❌ Failed to backup current state${NC}"
    exit 1
fi

# Decompress backup if needed
RESTORE_FILE="$BACKUP_FILE"
if [[ $BACKUP_FILE == *.gz ]]; then
    echo -e "${YELLOW}🗜️ Decompressing backup...${NC}"
    RESTORE_FILE="${BACKUP_FILE%.gz}"
    gunzip -c "$BACKUP_FILE" > "$RESTORE_FILE"
fi

# Restore database
echo -e "${YELLOW}🔄 Restoring database...${NC}"
if mysql -u $DB_USER -p$DB_PASS $DB_NAME < "$RESTORE_FILE" 2>/dev/null; then
    echo -e "${GREEN}✅ Database restored successfully${NC}"
else
    echo -e "${RED}❌ Database restore failed${NC}"
    echo -e "${YELLOW}🔄 Restoring from current backup...${NC}"
    gunzip -c "$CURRENT_BACKUP.gz" | mysql -u $DB_USER -p$DB_PASS $DB_NAME
    exit 1
fi

# Clean up temporary file
if [[ $BACKUP_FILE == *.gz ]] && [ -f "$RESTORE_FILE" ]; then
    rm "$RESTORE_FILE"
fi

# Clear Laravel cache
echo -e "${YELLOW}🧹 Clearing Laravel cache...${NC}"
cd /var/www/ezstream
php artisan config:clear
php artisan cache:clear

echo ""
echo -e "${GREEN}🎉 Database restore completed!${NC}"
echo -e "${BLUE}📊 Restore Summary:${NC}"
echo "  • Restored from: $(basename $BACKUP_FILE)"
echo "  • Current backup: $(basename $CURRENT_BACKUP.gz)"
echo "  • Timestamp: $(date)"

echo ""
echo -e "${YELLOW}💡 Test the website:${NC}"
echo "  curl -I http://ezstream.pro"
