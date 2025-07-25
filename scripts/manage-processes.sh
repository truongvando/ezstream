#!/bin/bash

# EZSTREAM Process Management Script
# Usage: bash manage-processes.sh [start|stop|restart|status|logs]

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Configuration
PROJECT_DIR="/var/www/ezstream"
ACTION=${1:-status}

echo -e "${BLUE}🔧 EZSTREAM Process Management${NC}"
echo -e "${YELLOW}Action: $ACTION${NC}"
echo ""

case $ACTION in
    "start")
        echo -e "${YELLOW}🚀 Starting all processes...${NC}"
        supervisorctl start ezstream-queue:*
        supervisorctl start ezstream-agent:*
        supervisorctl start ezstream-stream:*
        supervisorctl start ezstream-redis:*
        supervisorctl start ezstream-vps:*
        supervisorctl start ezstream-schedule:*
        echo -e "${GREEN}✅ All processes started${NC}"
        ;;

    "stop")
        echo -e "${YELLOW}🛑 Stopping all processes...${NC}"
        supervisorctl stop ezstream-queue:*
        supervisorctl stop ezstream-agent:*
        supervisorctl stop ezstream-stream:*
        supervisorctl stop ezstream-redis:*
        supervisorctl stop ezstream-vps:*
        supervisorctl stop ezstream-schedule:*
        echo -e "${GREEN}✅ All processes stopped${NC}"
        ;;

    "restart")
        echo -e "${YELLOW}🔄 Restarting all processes...${NC}"
        supervisorctl restart ezstream-queue:*
        supervisorctl restart ezstream-agent:*
        supervisorctl restart ezstream-stream:*
        supervisorctl restart ezstream-redis:*
        supervisorctl restart ezstream-vps:*
        supervisorctl restart ezstream-schedule:*
        echo -e "${GREEN}✅ All processes restarted${NC}"
        ;;
        
    "status")
        echo -e "${YELLOW}📊 Process Status:${NC}"
        supervisorctl status
        echo ""
        echo -e "${YELLOW}📈 System Resources:${NC}"
        echo "CPU Usage:"
        top -bn1 | grep "Cpu(s)" | awk '{print $2 $3 $4 $5 $6 $7 $8}'
        echo "Memory Usage:"
        free -h | grep "Mem:"
        echo "Disk Usage:"
        df -h / | tail -1
        ;;
        
    "logs")
        echo -e "${YELLOW}📋 Available log files:${NC}"
        echo "1. Queue logs: tail -f $PROJECT_DIR/storage/logs/queue.log"
        echo "2. Stream logs: tail -f $PROJECT_DIR/storage/logs/stream.log"
        echo "3. Redis logs: tail -f $PROJECT_DIR/storage/logs/redis.log"
        echo "4. Schedule logs: tail -f $PROJECT_DIR/storage/logs/schedule.log"
        echo "5. Laravel logs: tail -f $PROJECT_DIR/storage/logs/laravel.log"
        echo ""
        echo -e "${YELLOW}📊 Recent Queue logs:${NC}"
        tail -20 $PROJECT_DIR/storage/logs/queue.log 2>/dev/null || echo "No queue logs found"
        echo ""
        echo -e "${YELLOW}📊 Recent Laravel logs:${NC}"
        tail -10 $PROJECT_DIR/storage/logs/laravel.log 2>/dev/null || echo "No Laravel logs found"
        ;;
        
    "monitor")
        echo -e "${YELLOW}👀 Real-time monitoring (Ctrl+C to exit)...${NC}"
        watch -n 2 'supervisorctl status; echo ""; echo "=== Recent Queue Jobs ==="; tail -5 /var/www/ezstream/storage/logs/queue.log 2>/dev/null || echo "No logs"; echo ""; echo "=== System Load ==="; uptime'
        ;;
        
    *)
        echo -e "${RED}❌ Invalid action: $ACTION${NC}"
        echo -e "${YELLOW}Usage: bash manage-processes.sh [start|stop|restart|status|logs|monitor]${NC}"
        echo ""
        echo -e "${YELLOW}Available actions:${NC}"
        echo "  • start    - Start all background processes"
        echo "  • stop     - Stop all background processes"
        echo "  • restart  - Restart all background processes"
        echo "  • status   - Show process status and system info"
        echo "  • logs     - Show recent logs"
        echo "  • monitor  - Real-time monitoring"
        exit 1
        ;;
esac

echo ""
echo -e "${BLUE}💡 Quick commands:${NC}"
echo "  • Status: bash scripts/manage-processes.sh status"
echo "  • Restart: bash scripts/manage-processes.sh restart"
echo "  • Logs: bash scripts/manage-processes.sh logs"
echo "  • Monitor: bash scripts/manage-processes.sh monitor"
