#!/bin/bash

# ==============================================================================
# EZStream VPS Monitoring Setup Script
# ==============================================================================
# This script sets up the Redis subscriber for VPS stats monitoring
# Run this ONCE on your Laravel server to enable real-time VPS monitoring
# This is separate from VPS provisioning - it runs on Laravel server

echo "🚀 Setting up EZStream VPS Monitoring..."

# Check if we're in the right directory
if [ ! -f "artisan" ]; then
    echo "❌ Error: Please run this script from your Laravel project root directory"
    exit 1
fi

# Check if Redis is accessible
echo "🔍 Checking Redis connection..."
php artisan tinker --execute="
try {
    \Illuminate\Support\Facades\Redis::ping();
    echo 'Redis connection: OK\n';
} catch (Exception \$e) {
    echo 'Redis connection failed: ' . \$e->getMessage() . '\n';
    exit(1);
}
"

if [ $? -ne 0 ]; then
    echo "❌ Redis connection failed. Please check your Redis configuration."
    exit 1
fi

# Create supervisor config for VPS stats listener
echo "📝 Creating Supervisor configuration..."

SUPERVISOR_CONFIG="/etc/supervisor/conf.d/ezstream-vps-stats.conf"
PROJECT_PATH=$(pwd)
WEB_USER="www-data"

# Detect web user (try common ones)
if id "nginx" &>/dev/null; then
    WEB_USER="nginx"
elif id "apache" &>/dev/null; then
    WEB_USER="apache"
elif id "www-data" &>/dev/null; then
    WEB_USER="www-data"
else
    echo "⚠️  Could not detect web user. Using 'www-data' as default."
    echo "   You may need to change the 'user' setting in the supervisor config."
fi

# Create supervisor config
sudo tee $SUPERVISOR_CONFIG > /dev/null <<EOF
[program:ezstream-vps-stats]
process_name=%(program_name)s_%(process_num)02d
command=php $PROJECT_PATH/artisan redis:subscribe-stats
directory=$PROJECT_PATH
autostart=true
autorestart=true
user=$WEB_USER
numprocs=1
redirect_stderr=true
stdout_logfile=$PROJECT_PATH/storage/logs/supervisor-vps-stats.log
stopwaitsecs=3600
EOF

echo "✅ Supervisor config created at: $SUPERVISOR_CONFIG"

# Update supervisor
echo "🔄 Updating Supervisor..."
sudo supervisorctl reread
sudo supervisorctl update

# Start the service
echo "🚀 Starting VPS stats listener..."
sudo supervisorctl start ezstream-vps-stats:*

# Check status
echo "📊 Service status:"
sudo supervisorctl status ezstream-vps-stats:*

echo ""
echo "✅ VPS Monitoring setup completed!"
echo ""
echo "📋 What was set up:"
echo "   • Redis subscriber for VPS stats (redis:subscribe-stats)"
echo "   • Supervisor process management"
echo "   • Auto-restart on failure"
echo ""
echo "🔧 Management commands:"
echo "   • Check status: sudo supervisorctl status ezstream-vps-stats:*"
echo "   • Restart:      sudo supervisorctl restart ezstream-vps-stats:*"
echo "   • Stop:         sudo supervisorctl stop ezstream-vps-stats:*"
echo "   • View logs:    tail -f $PROJECT_PATH/storage/logs/supervisor-vps-stats.log"
echo ""
echo "🌐 Access VPS monitoring at: /admin/vps-monitoring"
echo ""
echo "💡 Note: Make sure your VPS agents are running and sending stats to Redis!"
