#!/bin/bash

# Fix Agent Listener - Add missing agent:listen process to Supervisor
# Run this on production server to fix the missing agent listener

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

echo -e "${BLUE}🔧 Fixing Agent Listener Setup...${NC}"
echo -e "${YELLOW}This will add the missing agent:listen process to Supervisor${NC}"
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

# Create Agent Reports Listener config
echo -e "${YELLOW}📝 Creating Agent Reports Listener config...${NC}"
cat > /etc/supervisor/conf.d/ezstream-agent.conf << EOF
[program:ezstream-agent]
process_name=%(program_name)s
command=php $PROJECT_DIR/artisan agent:listen
directory=$PROJECT_DIR
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=$USER
numprocs=1
redirect_stderr=true
stdout_logfile=$PROJECT_DIR/storage/logs/agent.log
stopwaitsecs=60
EOF

echo -e "${GREEN}✅ Agent listener config created${NC}"

# Create log file
echo -e "${YELLOW}📁 Creating agent log file...${NC}"
touch $PROJECT_DIR/storage/logs/agent.log
chown www-data:www-data $PROJECT_DIR/storage/logs/agent.log
echo -e "${GREEN}✅ Log file created${NC}"

# Stop any existing manual agent listeners
echo -e "${YELLOW}🛑 Stopping any existing manual agent listeners...${NC}"
pkill -f "agent:listen" || echo "No manual agent listeners found"

# Reload Supervisor configuration
echo -e "${YELLOW}🔄 Reloading Supervisor configuration...${NC}"
supervisorctl reread
supervisorctl update

# Start the agent listener
echo -e "${YELLOW}🚀 Starting agent listener...${NC}"
supervisorctl start ezstream-agent:*

# Show final status
echo -e "${YELLOW}📊 Final Supervisor status:${NC}"
supervisorctl status | grep ezstream

echo ""
echo -e "${GREEN}🎉 Agent Listener setup completed!${NC}"
echo ""
echo -e "${YELLOW}💡 What this process does:${NC}"
echo "  • Listens to 'agent-reports' Redis channel"
echo "  • Processes stream status updates from VPS agents"
echo "  • Handles heartbeat monitoring"
echo "  • Updates stream status in database"
echo ""
echo -e "${YELLOW}💡 Useful commands:${NC}"
echo "  • Check agent status: supervisorctl status ezstream-agent:*"
echo "  • View agent logs: tail -f $PROJECT_DIR/storage/logs/agent.log"
echo "  • Restart agent: supervisorctl restart ezstream-agent:*"
echo "  • Test agent: php artisan agent:listen"
echo ""
echo -e "${BLUE}🧪 Testing agent listener...${NC}"
if supervisorctl status ezstream-agent:* | grep -q RUNNING; then
    echo -e "${GREEN}✅ Agent listener is running!${NC}"
    echo -e "${YELLOW}Now agent reports will be processed automatically${NC}"
    echo -e "${YELLOW}You can stop running 'php artisan redis:subscribe-stats' manually${NC}"
else
    echo -e "${RED}❌ Agent listener failed to start${NC}"
    echo -e "${YELLOW}Check logs: tail -f $PROJECT_DIR/storage/logs/agent.log${NC}"
fi
