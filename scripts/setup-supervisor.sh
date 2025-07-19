#!/bin/bash

# EZSTREAM Supervisor Setup Script
# Auto-run Laravel background processes

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Configuration
PROJECT_DIR="/var/www/ezstream"
USER="www-data"

echo -e "${YELLOW}🔧 Setting up Supervisor for EZSTREAM background processes...${NC}"

# Install Supervisor
echo -e "${YELLOW}📦 Installing Supervisor...${NC}"
apt update
apt install -y supervisor

# Create Supervisor config for Queue Worker
echo -e "${YELLOW}📝 Creating Queue Worker config...${NC}"
cat > /etc/supervisor/conf.d/ezstream-queue.conf << EOF
[program:ezstream-queue]
process_name=%(program_name)s_%(process_num)02d
command=php $PROJECT_DIR/artisan queue:work --sleep=3 --tries=3 --max-time=3600
directory=$PROJECT_DIR
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=$USER
numprocs=2
redirect_stderr=true
stdout_logfile=$PROJECT_DIR/storage/logs/queue.log
stopwaitsecs=3600
EOF

# Create Supervisor config for Stream Listener
echo -e "${YELLOW}📝 Creating Stream Listener config...${NC}"
cat > /etc/supervisor/conf.d/ezstream-stream.conf << EOF
[program:ezstream-stream]
process_name=%(program_name)s
command=php $PROJECT_DIR/artisan stream:listen
directory=$PROJECT_DIR
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=$USER
numprocs=1
redirect_stderr=true
stdout_logfile=$PROJECT_DIR/storage/logs/stream.log
stopwaitsecs=60
EOF

# Create Supervisor config for Redis Stats
echo -e "${YELLOW}📝 Creating Redis Stats config...${NC}"
cat > /etc/supervisor/conf.d/ezstream-redis.conf << EOF
[program:ezstream-redis]
process_name=%(program_name)s
command=php $PROJECT_DIR/artisan redis:subscribe-stats
directory=$PROJECT_DIR
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=$USER
numprocs=1
redirect_stderr=true
stdout_logfile=$PROJECT_DIR/storage/logs/redis.log
stopwaitsecs=60
EOF

# Create Supervisor config for Schedule Worker
echo -e "${YELLOW}📝 Creating Schedule Worker config...${NC}"
cat > /etc/supervisor/conf.d/ezstream-schedule.conf << EOF
[program:ezstream-schedule]
process_name=%(program_name)s
command=php $PROJECT_DIR/artisan schedule:work
directory=$PROJECT_DIR
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=$USER
numprocs=1
redirect_stderr=true
stdout_logfile=$PROJECT_DIR/storage/logs/schedule.log
stopwaitsecs=60
EOF

# Create log files
echo -e "${YELLOW}📁 Creating log files...${NC}"
touch $PROJECT_DIR/storage/logs/queue.log
touch $PROJECT_DIR/storage/logs/stream.log
touch $PROJECT_DIR/storage/logs/redis.log
touch $PROJECT_DIR/storage/logs/schedule.log

# Set permissions
chown www-data:www-data $PROJECT_DIR/storage/logs/*.log

# Reload Supervisor
echo -e "${YELLOW}🔄 Reloading Supervisor...${NC}"
supervisorctl reread
supervisorctl update

# Start all processes
echo -e "${YELLOW}🚀 Starting all processes...${NC}"
supervisorctl start ezstream-queue:*
supervisorctl start ezstream-stream:*
supervisorctl start ezstream-redis:*
supervisorctl start ezstream-schedule:*

# Enable Supervisor auto-start
systemctl enable supervisor
systemctl start supervisor

echo -e "${GREEN}✅ Supervisor setup completed!${NC}"
echo -e "${YELLOW}📊 Process status:${NC}"
supervisorctl status

echo -e "${YELLOW}💡 Useful commands:${NC}"
echo "  • Check status: supervisorctl status"
echo "  • Restart all: supervisorctl restart all"
echo "  • Stop all: supervisorctl stop all"
echo "  • View logs: tail -f $PROJECT_DIR/storage/logs/queue.log"
