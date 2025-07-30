#!/bin/bash

# Clear Python cache for EZStream Agent
echo "🧹 Clearing Python cache for EZStream Agent..."

AGENT_DIR="storage/app/ezstream-agent"

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${YELLOW}📁 Clearing cache in: ${AGENT_DIR}${NC}"

# Remove Python cache directories
if [ -d "${AGENT_DIR}/__pycache__" ]; then
    rm -rf "${AGENT_DIR}/__pycache__"
    echo -e "${GREEN}✅ Removed __pycache__ directory${NC}"
else
    echo "ℹ️ No __pycache__ directory found"
fi

# Remove .pyc files
find "${AGENT_DIR}" -name "*.pyc" -delete 2>/dev/null
echo -e "${GREEN}✅ Removed .pyc files${NC}"

# Remove .pyo files
find "${AGENT_DIR}" -name "*.pyo" -delete 2>/dev/null
echo -e "${GREEN}✅ Removed .pyo files${NC}"

echo -e "${GREEN}🎉 Python cache cleared successfully!${NC}"
echo ""
echo -e "${YELLOW}💡 Next steps:${NC}"
echo "1. Deploy updated agent files to production"
echo "2. Restart agent: sudo supervisorctl restart ezstream-agent"
echo "3. Check logs: tail -f /var/www/ezstream/storage/logs/agent.log"
