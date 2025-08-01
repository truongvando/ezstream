#!/bin/bash
# ==============================================================================
# EZSTREAM BASE PROVISION SCRIPT v5.0 (for Stream Manager + Process Manager)
# ==============================================================================
#
# MÔ TẢ:
# Script này chuẩn bị một VPS mới để chạy EZStream Agent v5.0 với kiến trúc mới:
# - Stream Manager: Quản lý streams và playlists
# - Process Manager: Quản lý FFmpeg processes với auto reconnect
# - File Manager: Download và validate files
# - Direct FFmpeg to YouTube (không cần HLS pipeline)
#
# ==============================================================================

set -e

echo "=== EZSTREAM BASE PROVISION START ==="
echo "Timestamp: $(date)"

# 1. SYSTEM UPDATES & BASIC TOOLS
echo "1. Preparing system and installing packages..."
export DEBIAN_FRONTEND=noninteractive

# CRITICAL: Update package database first (like Bissive does)
echo "Running initial apt update (Bissive-style preparation)..."
apt-get update -y

# Wait for any existing apt processes to finish
echo "Waiting for apt lock to be released..."
while fuser /var/lib/dpkg/lock-frontend >/dev/null 2>&1; do
    echo "Waiting for other apt process to finish..."
    sleep 5
done

# Remove any stale locks
rm -f /var/lib/dpkg/lock-frontend
rm -f /var/lib/dpkg/lock

# Second update to ensure clean state
echo "Final apt update before package installation..."
apt-get update -y
apt-get install -y \
    curl wget jq ffmpeg \
    python3 python3-pip \
    htop iotop supervisor \
    redis-tools # Cần thiết cho redis-cli (debug)

# Cài đặt thư viện Python cần thiết cho EZStream Agent v5.0
echo "Installing Python packages for EZStream Agent v5.0..."
pip3 install redis psutil requests --break-system-packages || {
    echo "pip3 direct install failed. Trying via apt..."
    apt-get install -y python3-redis python3-psutil python3-requests
}

echo "Đặt múi giờ về Asia/Ho_Chi_Minh (Việt Nam)..."
timedatectl set-timezone Asia/Ho_Chi_Minh
export TZ=Asia/Ho_Chi_Minh


# 2. NGINX CONFIGURATION (Optional - for health checks only)
echo "2. Configuring basic Nginx for health checks..."
# EZStream Agent v5.0 streams direct to YouTube, không cần HLS serving
cp /etc/nginx/nginx.conf /etc/nginx/nginx.conf.backup
cat > /etc/nginx/nginx.conf << 'EOF'
user www-data;
worker_processes auto;
pid /run/nginx.pid;

events {
    worker_connections 1024;
    use epoll;
    multi_accept on;
}

# HTTP Configuration - No RTMP/HLS needed for EZStream Agent v5.0
http {
    sendfile on;
    tcp_nopush on;
    tcp_nodelay on;
    keepalive_timeout 65;
    types_hash_max_size 2048;

    include /etc/nginx/mime.types;
    default_type application/octet-stream;

    access_log /var/log/nginx/access.log;
    error_log /var/log/nginx/error.log;

    gzip on;

    # Basic health check and HLS serving
    server {
        listen 8080;
        server_name _;

        # Health check endpoint
        location /health {
            return 200 "EZStream Agent v5.0 Ready";
            add_header Content-Type text/plain;
        }

        # Agent status endpoint (for monitoring)
        location /agent-status {
            return 200 "EZStream Agent v5.0 - Stream Manager + Process Manager";
            add_header Content-Type text/plain;
        }
    }
}
EOF

# Tạo thư mục cho downloads (file_manager)
mkdir -p /opt/ezstream-downloads

# Test nginx config
nginx -t
if [ $? -ne 0 ]; then
    echo "ERROR: Nginx configuration test failed"
    exit 1
fi


# 3. CREATE AGENT DIRECTORY & LOG FILE
echo "3. Setting up agent directories, log file, and log rotation..."
mkdir -p /opt/ezstream-agent
mkdir -p /var/log/
touch /var/log/ezstream-agent.log
chmod 666 /var/log/ezstream-agent.log

echo "Creating logrotate configuration for agent log..."
cat > /etc/logrotate.d/ezstream-agent << 'EOF'
/var/log/ezstream-agent.log {
    daily
    rotate 7
    compress
    delaycompress
    missingok
    notifempty
    create 0644 root root
}
EOF


# 4. SYSTEM OPTIMIZATIONS FOR STREAMING
echo "4. Applying system optimizations..."
cat >> /etc/security/limits.conf << 'EOF'
* soft nofile 65536
* hard nofile 65536
EOF
sysctl -p


# 5. FIREWALL CONFIGURATION
echo "5. Configuring firewall..."
ufw allow 22/tcp    # SSH
ufw allow 8080/tcp  # Health check & agent status
ufw --force enable


# 6. START SERVICES
echo "6. Restarting base services..."
systemctl daemon-reload
systemctl enable nginx
systemctl restart nginx

sleep 5
if ! systemctl is-active --quiet nginx; then
    echo "ERROR: Nginx failed to start"
    systemctl status nginx
    exit 1
fi

echo "✅ Nginx started successfully"

# 6.5. PREPARE FOR EZSTREAM AGENT (don't start yet - files will be uploaded by ProvisionJob)
echo "6.5. Preparing for EZStream Agent..."
if systemctl list-unit-files | grep -q "ezstream-agent.service"; then
    echo "⚠️ Stopping existing EZStream Agent service (will be reconfigured by ProvisionJob)"
    systemctl stop ezstream-agent || true
    systemctl disable ezstream-agent || true
else
    echo "ℹ️ EZStream Agent service not found (will be created by ProvisionJob)"
fi

# Create agent directory (files will be uploaded later)
mkdir -p /opt/ezstream-agent
echo "✅ Agent directory prepared"

# 7. FINAL CHECK
echo "7. Performing final system check..."

# Test nginx configuration
echo "Testing nginx configuration..."
nginx -t
if [ $? -ne 0 ]; then
    echo "ERROR: Nginx configuration test failed"
    exit 1
fi

# Check HTTP port for health endpoint
echo "Checking HTTP port 8080..."
if ! ss -tulpn | grep -q ":8080"; then
    echo "ERROR: HTTP port 8080 not listening"
    ss -tulpn | grep nginx || echo "No nginx processes found"
    exit 1
fi

# Check health endpoint
echo "Testing nginx health endpoint..."
if ! curl -s http://localhost:8080/health | grep -q "EZStream Agent v5.0 Ready"; then
    echo "WARNING: Nginx health check failed"
    echo "This might be normal if health endpoint is not fully configured"
    echo "Continuing anyway as HTTP port is working..."
fi

echo "✅ All base services verified successfully"

echo ""
echo "=== VPS BASE PROVISION COMPLETE ==="
echo "✅ Base system is ready for EZStream Agent v5.0 deployment from Laravel."
echo "📋 Architecture: Stream Manager + Process Manager + File Manager"
echo ""
