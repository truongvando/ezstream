#!/bin/bash

# EZSTREAM Migration Fix Script
# Usage: bash fix-migrations.sh

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}🔧 EZSTREAM Migration Fix Starting...${NC}"

# Check if we're in the right directory
if [ ! -f "artisan" ]; then
    echo -e "${RED}❌ Error: artisan file not found. Please run this script from the Laravel project root.${NC}"
    exit 1
fi

# Function to check if table exists
table_exists() {
    local table_name=$1
    php artisan tinker --execute="
    try {
        \$exists = \Schema::hasTable('$table_name');
        echo \$exists ? 'YES' : 'NO';
    } catch(Exception \$e) {
        echo 'ERROR';
    }
    " 2>/dev/null | tail -1
}

# Function to mark migration as ran if table exists
mark_if_exists() {
    local migration_file=$1
    local table_name=$2
    
    if [ "$(table_exists $table_name)" = "YES" ]; then
        echo -e "${YELLOW}📋 Table '$table_name' exists, marking migration as ran: $migration_file${NC}"
        php artisan migrate:mark-ran $migration_file
    else
        echo -e "${BLUE}📋 Table '$table_name' does not exist, will run migration: $migration_file${NC}"
    fi
}

echo -e "${YELLOW}🔍 Checking existing tables and marking migrations...${NC}"

# Check and mark basic Laravel tables
mark_if_exists "2014_10_12_000000_create_users_table" "users"
mark_if_exists "2014_10_12_100000_create_password_reset_tokens_table" "password_reset_tokens"

# Check and mark application tables
mark_if_exists "2024_01_01_000000_create_service_packages_table" "service_packages"
mark_if_exists "2024_01_01_100000_create_transactions_table" "transactions"
mark_if_exists "2024_01_02_000000_create_subscriptions_table" "subscriptions"
mark_if_exists "2024_01_03_000000_create_vps_servers_table" "vps_servers"
mark_if_exists "2024_01_04_000000_create_user_files_table" "user_files"
mark_if_exists "2024_01_05_000000_create_stream_configurations_table" "stream_configurations"
mark_if_exists "2024_01_06_000000_create_vps_stats_table" "vps_stats"
mark_if_exists "2024_01_07_000000_create_settings_table" "settings"
mark_if_exists "2025_06_30_231221_create_sessions_table" "sessions"
mark_if_exists "2025_07_13_100350_create_jobs_table" "jobs"
mark_if_exists "2025_07_14_063006_create_failed_jobs_table" "failed_jobs"

# Check for specific columns in stream_configurations table
echo -e "${YELLOW}🔍 Checking stream_configurations table columns...${NC}"
if [ "$(table_exists stream_configurations)" = "YES" ]; then
    # Check if scheduled_end column exists
    SCHEDULED_END_EXISTS=$(php artisan tinker --execute="
    try {
        \$exists = \Schema::hasColumn('stream_configurations', 'scheduled_end');
        echo \$exists ? 'YES' : 'NO';
    } catch(Exception \$e) {
        echo 'ERROR';
    }
    " 2>/dev/null | tail -1)
    
    # Check if enable_schedule column exists
    ENABLE_SCHEDULE_EXISTS=$(php artisan tinker --execute="
    try {
        \$exists = \Schema::hasColumn('stream_configurations', 'enable_schedule');
        echo \$exists ? 'YES' : 'NO';
    } catch(Exception \$e) {
        echo 'ERROR';
    }
    " 2>/dev/null | tail -1)
    
    echo -e "${BLUE}   scheduled_end column exists: $SCHEDULED_END_EXISTS${NC}"
    echo -e "${BLUE}   enable_schedule column exists: $ENABLE_SCHEDULE_EXISTS${NC}"
    
    # Mark schedule-related migrations if columns exist
    if [ "$SCHEDULED_END_EXISTS" = "YES" ] && [ "$ENABLE_SCHEDULE_EXISTS" = "YES" ]; then
        echo -e "${YELLOW}📋 Schedule columns exist, marking related migrations as ran...${NC}"
        php artisan migrate:mark-ran "2024_01_20_000000_add_schedule_fields_to_stream_configurations_table" 2>/dev/null || true
        php artisan migrate:mark-ran "2025_07_12_185620_add_scheduling_to_stream_configurations_table" 2>/dev/null || true
        php artisan migrate:mark-ran "2025_07_12_185628_add_scheduling_to_stream_configurations_table" 2>/dev/null || true
        php artisan migrate:mark-ran "2025_07_20_095950_add_schedule_fields_to_stream_configurations_table" 2>/dev/null || true
        php artisan migrate:mark-ran "2025_07_20_095959_add_schedule_fields_to_stream_configurations_table" 2>/dev/null || true
        php artisan migrate:mark-ran "2025_07_20_100007_add_schedule_fields_to_stream_configurations_table" 2>/dev/null || true
    fi
fi

echo -e "${YELLOW}🔍 Current migration status after marking:${NC}"
php artisan migrate:status

echo -e "${YELLOW}🚀 Running remaining migrations...${NC}"
if php artisan migrate --force 2>&1 | tee /tmp/migration_fix_output.log; then
    echo -e "${GREEN}✅ Migrations completed successfully${NC}"
else
    echo -e "${RED}❌ Some migrations failed. Check output above.${NC}"
    echo -e "${YELLOW}📋 Migration output:${NC}"
    cat /tmp/migration_fix_output.log
fi

# Clean up temp file
rm -f /tmp/migration_fix_output.log

echo -e "${YELLOW}🔍 Final migration status:${NC}"
php artisan migrate:status

echo -e "${GREEN}🎉 Migration fix completed!${NC}"
echo -e "${BLUE}💡 If you still see issues, you may need to manually check specific tables and columns.${NC}"
