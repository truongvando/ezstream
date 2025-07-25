#!/bin/bash

# Fix VPS Queue Worker - Add missing vps-provisioning queue worker to Supervisor
# Run this on production server to fix the missing VPS queue worker

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Configuration
PROJECT_DIR="/var/www/ezstream"
USER="www-data"

echo -e "${BLUE}🔧 Fixing VPS Queue Worker Setup...${NC}"
echo -e "${YELLOW}This will add the missing vps-provisioning queue worker to Supervisor${NC}"
echo ""

# Check if Supervisor is installed
if ! command -v supervisorctl &> /dev/null; then
    echo -e "${RED}❌ Supervisor is not installed!${NC}"
    echo -e "${YELLOW}Please run: apt install supervisor${NC}"
    exit 1
fi

# Check current status
echo -e "${YELLOW}📊 Current Supervisor status:${NC}"
supervisorctl status | grep ezstream || echo "No ezstream processes found"
echo ""

# Create VPS Provisioning Queue Worker config
echo -e "${YELLOW}📝 Creating VPS Provisioning Queue Worker config...${NC}"
cat > /etc/supervisor/conf.d/ezstream-vps.conf << EOF
[program:ezstream-vps]
process_name=%(program_name)s_%(process_num)02d
command=php $PROJECT_DIR/artisan queue:work --queue=vps-provisioning --sleep=3 --tries=3 --max-time=3600
directory=$PROJECT_DIR
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=$USER
numprocs=1
redirect_stderr=true
stdout_logfile=$PROJECT_DIR/storage/logs/vps-queue.log
stopwaitsecs=3600
EOF

echo -e "${GREEN}✅ VPS queue worker config created${NC}"

# Create log file
echo -e "${YELLOW}📁 Creating VPS queue log file...${NC}"
touch $PROJECT_DIR/storage/logs/vps-queue.log
chown www-data:www-data $PROJECT_DIR/storage/logs/vps-queue.log
echo -e "${GREEN}✅ Log file created${NC}"

# Stop any existing manual queue workers for vps-provisioning
echo -e "${YELLOW}🛑 Stopping any existing manual VPS queue workers...${NC}"
pkill -f "queue:work.*vps-provisioning" || echo "No manual VPS queue workers found"

# Reload Supervisor configuration
echo -e "${YELLOW}🔄 Reloading Supervisor configuration...${NC}"
supervisorctl reread
supervisorctl update

# Start the VPS queue worker
echo -e "${YELLOW}🚀 Starting VPS queue worker...${NC}"
supervisorctl start ezstream-vps:*

# Show final status
echo -e "${YELLOW}📊 Final Supervisor status:${NC}"
supervisorctl status | grep ezstream

echo ""
echo -e "${GREEN}🎉 VPS Queue Worker setup completed!${NC}"
echo ""
echo -e "${YELLOW}💡 Useful commands:${NC}"
echo "  • Check all processes: supervisorctl status | grep ezstream"
echo "  • Check VPS queue: supervisorctl status ezstream-vps:*"
echo "  • View VPS logs: tail -f $PROJECT_DIR/storage/logs/vps-queue.log"
echo "  • View agent logs: tail -f $PROJECT_DIR/storage/logs/agent.log"
echo "  • Restart VPS queue: supervisorctl restart ezstream-vps:*"
echo "  • Test VPS queue: php artisan queue:work --queue=vps-provisioning --once"
echo ""
echo -e "${BLUE}🧪 Testing VPS queue worker...${NC}"
if supervisorctl status ezstream-vps:* | grep -q RUNNING; then
    echo -e "${GREEN}✅ VPS queue worker is running!${NC}"
    echo -e "${YELLOW}Now VPS provisioning jobs (add/update VPS) will be processed automatically${NC}"
else
    echo -e "${RED}❌ VPS queue worker failed to start${NC}"
    echo -e "${YELLOW}Check logs: tail -f $PROJECT_DIR/storage/logs/vps-queue.log${NC}"
fi
