#!/bin/bash

# EZSTREAM Crontab Setup Script
# Backup for Laravel Schedule

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Configuration
PROJECT_DIR="/var/www/ezstream"

echo -e "${YELLOW}⏰ Setting up Crontab for EZSTREAM...${NC}"

# Add Laravel Schedule to crontab
echo -e "${YELLOW}📝 Adding Laravel Schedule to crontab...${NC}"

# Create crontab entry
CRON_ENTRY="* * * * * cd $PROJECT_DIR && php artisan schedule:run >> /dev/null 2>&1"

# Check if crontab entry already exists
if crontab -l 2>/dev/null | grep -q "artisan schedule:run"; then
    echo -e "${YELLOW}⚠️ Crontab entry already exists${NC}"
else
    # Add to crontab
    (crontab -l 2>/dev/null; echo "$CRON_ENTRY") | crontab -
    echo -e "${GREEN}✅ Crontab entry added${NC}"
fi

# Show current crontab
echo -e "${YELLOW}📋 Current crontab:${NC}"
crontab -l

echo -e "${GREEN}✅ Crontab setup completed!${NC}"
echo -e "${YELLOW}💡 Note: This is backup for schedule:work in Supervisor${NC}"
