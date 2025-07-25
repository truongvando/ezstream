#!/bin/bash

# Fix stream:listen command error - Remove deprecated ezstream-stream process
# Run this on production server to fix the stream:listen command error

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}🔧 Fixing stream:listen Command Error...${NC}"
echo -e "${YELLOW}This will remove the deprecated ezstream-stream process${NC}"
echo ""

# Check if Supervisor is installed
if ! command -v supervisorctl &> /dev/null; then
    echo -e "${RED}❌ Supervisor is not installed!${NC}"
    exit 1
fi

# Check current status
echo -e "${YELLOW}📊 Current Supervisor status:${NC}"
supervisorctl status | grep ezstream || echo "No ezstream processes found"
echo ""

# Stop ezstream-stream process if running
echo -e "${YELLOW}🛑 Stopping ezstream-stream process...${NC}"
supervisorctl stop ezstream-stream:* 2>/dev/null || echo "ezstream-stream not running"

# Remove ezstream-stream config
echo -e "${YELLOW}🗑️ Removing ezstream-stream config...${NC}"
if [ -f "/etc/supervisor/conf.d/ezstream-stream.conf" ]; then
    rm -f /etc/supervisor/conf.d/ezstream-stream.conf
    echo -e "${GREEN}✅ Removed ezstream-stream.conf${NC}"
else
    echo -e "${YELLOW}⚠️ ezstream-stream.conf not found${NC}"
fi

# Reload Supervisor configuration
echo -e "${YELLOW}🔄 Reloading Supervisor configuration...${NC}"
supervisorctl reread
supervisorctl update

# Show final status
echo -e "${YELLOW}📊 Final Supervisor status:${NC}"
supervisorctl status | grep ezstream

echo ""
echo -e "${GREEN}🎉 stream:listen error fix completed!${NC}"
echo ""
echo -e "${YELLOW}💡 What was fixed:${NC}"
echo "  • Removed deprecated ezstream-stream process"
echo "  • stream:listen command no longer exists"
echo "  • agent:listen handles all stream reports now"
echo ""
echo -e "${YELLOW}💡 Current active processes:${NC}"
echo "  • ezstream-queue: Default queue worker"
echo "  • ezstream-vps: VPS provisioning queue worker"
echo "  • ezstream-agent: Agent reports listener (replaces stream:listen)"
echo "  • ezstream-redis: Redis stats subscriber"
echo "  • ezstream-schedule: Laravel scheduler"
echo ""
echo -e "${BLUE}🧪 Checking for errors...${NC}"
if supervisorctl status | grep -q "FATAL\|ERROR"; then
    echo -e "${RED}❌ Some processes have errors. Check logs:${NC}"
    supervisorctl status | grep -E "FATAL|ERROR"
else
    echo -e "${GREEN}✅ All processes are healthy!${NC}"
fi
