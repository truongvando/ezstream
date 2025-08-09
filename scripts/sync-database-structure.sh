#!/bin/bash

# EZSTREAM Database Structure Sync Script
# ONLY syncs table structure, NOT data
# Safe for production use

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${YELLOW}🔄 EZSTREAM Database Structure Sync${NC}"
echo -e "${RED}⚠️  WARNING: This will modify database structure!${NC}"
echo ""

# Configuration
LOCAL_DB="ezstream"
PROD_DB="sql_ezstream_pro"
BACKUP_DIR="/var/backups/ezstream"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

# Check if we're on production
if [ -f "/var/www/ezstream/.env" ]; then
    echo -e "${YELLOW}📍 Detected production environment${NC}"
    IS_PRODUCTION=true
    PROJECT_DIR="/var/www/ezstream"
else
    echo -e "${YELLOW}📍 Detected local environment${NC}"
    IS_PRODUCTION=false
    PROJECT_DIR="."
fi

# Function to check missing tables
check_missing_tables() {
    echo -e "${YELLOW}🔍 Checking for missing tables...${NC}"
    
    cd $PROJECT_DIR
    php artisan check:database --export
    
    echo -e "${GREEN}✅ Database check completed${NC}"
    echo -e "${YELLOW}💡 Check storage/app/database_check_*.json for details${NC}"
}

# Function to run migrations (SAFE)
run_migrations() {
    echo -e "${YELLOW}🔄 Running database migrations...${NC}"
    
    if [ "$IS_PRODUCTION" = true ]; then
        # Backup first on production
        echo -e "${YELLOW}💾 Creating backup...${NC}"
        mkdir -p $BACKUP_DIR
        mysqldump -u root -p$DB_PASS $PROD_DB > $BACKUP_DIR/structure_backup_$TIMESTAMP.sql
        echo -e "${GREEN}✅ Backup created: $BACKUP_DIR/structure_backup_$TIMESTAMP.sql${NC}"
    fi
    
    cd $PROJECT_DIR
    
    # Run migrations
    echo -e "${YELLOW}📊 Running migrations...${NC}"
    php artisan migrate --force
    
    echo -e "${GREEN}✅ Migrations completed${NC}"
}

# Function to show differences
show_differences() {
    echo -e "${YELLOW}📊 Checking table differences...${NC}"
    
    cd $PROJECT_DIR
    php artisan tinker --execute="
    \$tables = DB::select('SHOW TABLES');
    \$tableCount = count(\$tables);
    echo 'Total tables: ' . \$tableCount . PHP_EOL;
    
    \$youtubeTables = ['youtube_channels', 'youtube_videos', 'youtube_video_snapshots', 'youtube_channel_snapshots', 'youtube_alerts', 'youtube_alert_settings', 'youtube_ai_analysis'];
    \$missing = [];
    foreach(\$youtubeTables as \$table) {
        if(!Schema::hasTable(\$table)) {
            \$missing[] = \$table;
        }
    }
    
    if(count(\$missing) > 0) {
        echo 'Missing YouTube tables: ' . implode(', ', \$missing) . PHP_EOL;
    } else {
        echo 'All YouTube tables present' . PHP_EOL;
    }
    "
}

# Main menu
echo -e "${YELLOW}Choose an option:${NC}"
echo "1. Check database structure"
echo "2. Show table differences"
echo "3. Run migrations (SAFE - only adds missing tables)"
echo "4. Full check and migrate"
echo "5. Exit"
echo ""
read -p "Enter your choice (1-5): " choice

case $choice in
    1)
        check_missing_tables
        ;;
    2)
        show_differences
        ;;
    3)
        run_migrations
        ;;
    4)
        check_missing_tables
        echo ""
        show_differences
        echo ""
        echo -e "${YELLOW}Do you want to run migrations? (y/N)${NC}"
        read -r response
        if [[ $response =~ ^[Yy]$ ]]; then
            run_migrations
        fi
        ;;
    5)
        echo -e "${GREEN}👋 Goodbye!${NC}"
        exit 0
        ;;
    *)
        echo -e "${RED}❌ Invalid choice${NC}"
        exit 1
        ;;
esac

echo ""
echo -e "${GREEN}🎉 Operation completed!${NC}"
echo -e "${YELLOW}💡 Always verify results with: php artisan check:database${NC}"
