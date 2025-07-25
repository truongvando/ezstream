#!/bin/bash

# Script để restart agent với các fix mới
# Sử dụng: ./restart-agent-with-fixes.sh <VPS_ID>

VPS_ID="$1"

if [ -z "$VPS_ID" ]; then
    echo "❌ Cần cung cấp VPS ID"
    echo "Sử dụng: $0 <VPS_ID>"
    exit 1
fi

echo "🔄 Restarting EZStream Agent với fixes..."

# 1. Stop agent hiện tại
echo "🛑 Stopping current agent..."
systemctl stop ezstream-agent
sleep 3

# 2. Backup agent cũ
echo "💾 Backing up current agent..."
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
cp -r /opt/ezstream-agent /opt/ezstream-agent-backup-$TIMESTAMP

# 3. Copy agent files mới từ Laravel
echo "📤 Copying new agent files..."
LARAVEL_AGENT_DIR="/var/www/html/storage/app/ezstream-agent"

if [ -d "$LARAVEL_AGENT_DIR" ]; then
    cp "$LARAVEL_AGENT_DIR"/*.py /opt/ezstream-agent/
    chmod +x /opt/ezstream-agent/*.py
    echo "✅ Agent files updated"
else
    echo "❌ Laravel agent directory not found: $LARAVEL_AGENT_DIR"
    exit 1
fi

# 4. Start agent
echo "🚀 Starting agent..."
systemctl start ezstream-agent
sleep 5

# 5. Check status
echo "🔍 Checking agent status..."
systemctl status ezstream-agent --no-pager -l

# 6. Check logs
echo "📜 Recent logs:"
tail -20 /var/log/ezstream-agent.log

# 7. Test Redis connection
echo "🔗 Testing Redis connection..."
redis-cli PUBSUB NUMSUB "vps-commands:$VPS_ID"

echo "✅ Agent restart completed!"
echo "Monitor logs: tail -f /var/log/ezstream-agent.log"
