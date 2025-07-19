#!/bin/bash

# EZSTREAM Database Backup Script
# Usage: bash backup-database.sh

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
PROJECT_DIR="/var/www/ezstream"

# Create backup directory
mkdir -p $BACKUP_DIR

echo -e "${BLUE}💾 EZSTREAM Database Backup${NC}"
echo "$(date)"
echo "=================================="

# Generate backup filename with timestamp
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="$BACKUP_DIR/database_$TIMESTAMP.sql"

echo -e "${YELLOW}📁 Backup file: $BACKUP_FILE${NC}"

# Create database backup
echo -e "${YELLOW}💾 Creating database backup...${NC}"
if mysqldump -u $DB_USER -p$DB_PASS $DB_NAME > $BACKUP_FILE 2>/dev/null; then
    echo -e "${GREEN}✅ Database backup created successfully${NC}"
    
    # Get file size
    SIZE=$(du -h $BACKUP_FILE | cut -f1)
    echo -e "${BLUE}📊 Backup size: $SIZE${NC}"
    
    # Compress backup
    echo -e "${YELLOW}🗜️ Compressing backup...${NC}"
    gzip $BACKUP_FILE
    COMPRESSED_FILE="$BACKUP_FILE.gz"
    COMPRESSED_SIZE=$(du -h $COMPRESSED_FILE | cut -f1)
    echo -e "${GREEN}✅ Backup compressed: $COMPRESSED_SIZE${NC}"
    
else
    echo -e "${RED}❌ Database backup failed${NC}"
    exit 1
fi

# Backup .env file
echo -e "${YELLOW}📝 Backing up .env file...${NC}"
if [ -f "$PROJECT_DIR/.env" ]; then
    cp "$PROJECT_DIR/.env" "$BACKUP_DIR/.env_$TIMESTAMP"
    echo -e "${GREEN}✅ .env file backed up${NC}"
fi

# Clean old backups (keep last 7 days)
echo -e "${YELLOW}🧹 Cleaning old backups...${NC}"
find $BACKUP_DIR -name "database_*.sql.gz" -type f -mtime +7 -delete
find $BACKUP_DIR -name ".env_*" -type f -mtime +7 -delete

REMAINING=$(ls $BACKUP_DIR/database_*.sql.gz 2>/dev/null | wc -l)
echo -e "${BLUE}📊 Backups remaining: $REMAINING${NC}"

# List recent backups
echo -e "${YELLOW}📋 Recent backups:${NC}"
ls -lah $BACKUP_DIR/database_*.sql.gz 2>/dev/null | tail -5 | while read line; do
    echo "  $line"
done

# Backup summary
echo ""
echo -e "${GREEN}🎉 Backup completed successfully!${NC}"
echo -e "${BLUE}📊 Backup Summary:${NC}"
echo "  • Database: $DB_NAME"
echo "  • File: $(basename $COMPRESSED_FILE)"
echo "  • Size: $COMPRESSED_SIZE"
echo "  • Location: $BACKUP_DIR"
echo "  • Timestamp: $TIMESTAMP"

# Test backup integrity
echo -e "${YELLOW}🧪 Testing backup integrity...${NC}"
if gunzip -t $COMPRESSED_FILE 2>/dev/null; then
    echo -e "${GREEN}✅ Backup file is valid${NC}"
else
    echo -e "${RED}❌ Backup file is corrupted${NC}"
    exit 1
fi

echo ""
echo -e "${YELLOW}💡 To restore this backup:${NC}"
echo "  gunzip $COMPRESSED_FILE"
echo "  mysql -u $DB_USER -p$DB_PASS $DB_NAME < $BACKUP_FILE"

echo ""
echo -e "${BLUE}Backup completed at $(date)${NC}"
