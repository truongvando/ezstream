#!/bin/bash

# EZStream SRS Server Setup Script
# Use pre-built SRS from provision script

set -e

echo "🎬 EZStream SRS Server Setup (Pre-built)"
echo "========================================"

# Configuration
SCRIPT_DIR="/opt/ezstream-agent"
SRS_CONFIG_PATH="${SCRIPT_DIR}/srs.conf"
SRS_LOGS_DIR="${SCRIPT_DIR}/logs"

# Stop any existing SRS
echo "🛑 Stopping existing SRS..."
pkill -f "srs -c" || true
/usr/local/srs/etc/init.d/srs stop || true

# Create logs directory
mkdir -p "$SRS_LOGS_DIR"

# Create SRS config if not exists
if [ ! -f "$SRS_CONFIG_PATH" ]; then
    echo "📝 Creating SRS config..."
    cat > "$SRS_CONFIG_PATH" << 'EOF'
listen              1935;
max_connections     1000;
daemon              off;
srs_log_tank        console;

http_api {
    enabled         on;
    listen          1985;
}

http_server {
    enabled         on;
    listen          8080;
}

vhost __defaultVhost__ {
    http_remux {
        enabled     on;
        mount       [vhost]/[app]/[stream].flv;
    }
}
EOF
fi

# Start SRS using init.d script (recommended way)
echo "🚀 Starting SRS server using init.d..."
if [ -f "/usr/local/srs/etc/init.d/srs" ]; then
    # Update config path in init.d script
    sed -i "s|conf/srs.conf|${SRS_CONFIG_PATH}|g" /usr/local/srs/etc/init.d/srs
    
    # Start SRS
    /usr/local/srs/etc/init.d/srs start
    
    # Wait for startup
    sleep 5
    
    # Check status
    if /usr/local/srs/etc/init.d/srs status | grep -q "SRS is running"; then
        echo "✅ SRS server started successfully!"
    else
        echo "⚠️ SRS may not be running, trying direct start..."
        nohup /usr/local/srs/objs/srs -c "$SRS_CONFIG_PATH" > "$SRS_LOGS_DIR/srs.log" 2>&1 &
        sleep 3
    fi
else
    echo "⚠️ SRS init.d script not found, starting directly..."
    nohup /usr/local/srs/objs/srs -c "$SRS_CONFIG_PATH" > "$SRS_LOGS_DIR/srs.log" 2>&1 &
    sleep 3
fi

# Verify SRS is running
if pgrep -f "srs -c" > /dev/null; then
    echo "✅ SRS server is running!"
    echo "📊 SRS API: http://localhost:1985/api/v1/versions"
    echo "📊 RTMP: rtmp://localhost:1935/live/"
    echo "📋 Logs: $SRS_LOGS_DIR/srs.log"
    
    # Test API
    if curl -s http://localhost:1985/api/v1/versions > /dev/null; then
        echo "✅ SRS API is responding!"
    else
        echo "⚠️ SRS API not responding yet, may need more time..."
    fi
else
    echo "❌ SRS failed to start!"
    echo "📋 Check logs: $SRS_LOGS_DIR/srs.log"
    if [ -f "$SRS_LOGS_DIR/srs.log" ]; then
        echo "📋 Last 10 lines of SRS log:"
        tail -10 "$SRS_LOGS_DIR/srs.log"
    fi
    exit 1
fi

echo "🎉 SRS setup completed!"
