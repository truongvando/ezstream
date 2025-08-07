#!/bin/bash
# ==============================================================================
# EZSTREAM BASE PROVISION SCRIPT v7.0 (Simple FFmpeg Direct Streaming)
# ==============================================================================
#
# MÔ TẢ:
# Script này chuẩn bị một VPS mới để chạy EZStream Agent v7.0 với kiến trúc đơn giản:
# - Simple Stream Manager: FFmpeg direct streaming không cần SRS
# - Process Manager: Quản lý FFmpeg processes với auto reconnect
# - File Manager: Download và validate files
# - Direct FFmpeg to YouTube/RTMP (không cần HLS pipeline hoặc SRS)
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
    htop supervisor \
    redis-tools \
    git unzip
    # Removed: iotop, build-essential, automake, cmake, pkg-config (not needed for Agent v7.0)
    # Removed: nginx (Agent v7.0 uses direct FFmpeg streaming)

# Cài đặt thư viện Python cần thiết cho EZStream Agent v7.0
echo "Installing Python packages for EZStream Agent v7.0..."
pip3 install redis psutil requests --break-system-packages || {
    echo "pip3 direct install failed. Trying via apt..."
    apt-get install -y python3-redis python3-psutil python3-requests
}

echo "Đặt múi giờ về Asia/Ho_Chi_Minh (Việt Nam)..."
timedatectl set-timezone Asia/Ho_Chi_Minh
export TZ=Asia/Ho_Chi_Minh


# 2. NGINX CONFIGURATION - DISABLED (not needed for direct FFmpeg streaming)
echo "2. Skipping Nginx configuration (not needed for direct FFmpeg streaming)..."
# EZStream Agent v7.0 uses direct FFmpeg streaming, nginx not needed
# cp /etc/nginx/nginx.conf /etc/nginx/nginx.conf.backup
# cat > /etc/nginx/nginx.conf << 'EOF'
# user www-data;
# worker_processes auto;
# pid /run/nginx.pid;
#
# events {
#     worker_connections 1024;
#     use epoll;
#     multi_accept on;
# }
#
# # HTTP Configuration - No RTMP/HLS needed for EZStream Agent v7.0
# http {
#     sendfile on;
#     tcp_nopush on;
#     tcp_nodelay on;
#     keepalive_timeout 65;
#     types_hash_max_size 2048;
#
#     include /etc/nginx/mime.types;
#     default_type application/octet-stream;
#
#     access_log /var/log/nginx/access.log;
#     error_log /var/log/nginx/error.log;
#
#     gzip on;
#
#     # Basic health check and HLS serving
#     server {
#         listen 8080;
#         server_name _;
#
#         # Health check endpoint
#         location /health {
#             return 200 "EZStream Agent v7.0 Ready";
#             add_header Content-Type text/plain;
#         }
#
#         # Agent status endpoint (for monitoring)
#         location /agent-status {
#             return 200 "EZStream Agent v7.0 - Simple FFmpeg Direct Streaming";
#             add_header Content-Type text/plain;
#         }
#     }
# }
# EOF

# Tạo thư mục cho downloads (file_manager)
mkdir -p /opt/ezstream-downloads

# Test nginx config - DISABLED
# nginx -t
# if [ $? -ne 0 ]; then
#     echo "ERROR: Nginx configuration test failed"
#     exit 1
# fi
echo "✅ Nginx disabled - Direct FFmpeg streaming (no HTTP endpoints needed)"


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


# 6. START SERVICES - NGINX DISABLED
echo "6. Skipping nginx services (Direct FFmpeg streaming - no HTTP needed)..."
systemctl daemon-reload
# systemctl enable nginx
# systemctl restart nginx

# sleep 5
# if ! systemctl is-active --quiet nginx; then
#     echo "ERROR: Nginx failed to start"
#     systemctl status nginx
#     exit 1
# fi

echo "✅ Base services ready (nginx disabled - direct FFmpeg streaming)"

# 6.5. PREPARE FOR EZSTREAM AGENT (files already downloaded from GitHub)
echo "6.5. Preparing for EZStream Agent..."
if systemctl list-unit-files | grep -q "ezstream-agent.service"; then
    echo "⚠️ Stopping existing EZStream Agent service (will be reconfigured by ProvisionJob)"
    systemctl stop ezstream-agent || true
    systemctl disable ezstream-agent || true
else
    echo "ℹ️ EZStream Agent service not found (will be created by ProvisionJob)"
fi

# Agent directory and files already exist from GitHub download
if [ -d "/opt/ezstream-agent" ] && [ -f "/opt/ezstream-agent/agent.py" ]; then
    echo "✅ EZStream Agent files ready from GitHub"

    # Install Python dependencies if requirements.txt exists
    if [ -f "/opt/ezstream-agent/requirements.txt" ]; then
        echo "📦 Installing Python dependencies..."
        cd /opt/ezstream-agent
        pip3 install -r requirements.txt
    fi

    # Make Python files executable
    chmod +x /opt/ezstream-agent/*.py
else
    echo "⚠️ EZStream Agent files not found (will be handled by ProvisionJob)"
fi

# 7. FINAL CHECK
echo "7. Performing final system check..."

# Test nginx configuration - DISABLED
echo "Skipping nginx tests (nginx disabled)..."
# nginx -t
# if [ $? -ne 0 ]; then
#     echo "ERROR: Nginx configuration test failed"
#     exit 1
# fi
# Check HTTP port for health endpoint - DISABLED
echo "Skipping port 8080 check (not needed for direct FFmpeg streaming)..."
# if ! ss -tulpn | grep -q ":8080"; then
#     echo "ERROR: HTTP port 8080 not listening"
#     ss -tulpn | grep nginx || echo "No nginx processes found"
#     exit 1
# fi

# Check health endpoint - DISABLED
echo "Skipping nginx health check (nginx disabled)..."
# if ! curl -s http://localhost:8080/health | grep -q "EZStream Agent v5.0 Ready"; then
#     echo "WARNING: Nginx health check failed"
#     echo "This might be normal if health endpoint is not fully configured"
#     echo "Continuing anyway as HTTP port is working..."
# fi

echo "✅ Base system ready (nginx disabled, direct FFmpeg streaming)"

# Docker not needed for Agent v7.0 (Direct FFmpeg Streaming)
echo ""
echo "=== SKIPPING DOCKER INSTALLATION ==="
echo "✅ Docker not needed for Agent v7.0 - using direct FFmpeg streaming"

# 8. STREAMING DEPENDENCIES SETUP
echo "8. Setting up streaming dependencies..."

# Install FFmpeg and required packages
echo "🎬 Installing FFmpeg and streaming dependencies..."

# Install FFmpeg
apt-get update
apt-get install -y ffmpeg python3-psutil

# Test FFmpeg installation
echo "🧪 Testing FFmpeg installation..."
FFMPEG_VERSION=$(ffmpeg -version | head -1 2>/dev/null || echo "FAILED")

if [[ "$FFMPEG_VERSION" != "FAILED" ]]; then
    echo "✅ FFmpeg installed successfully: $FFMPEG_VERSION"
else
    echo "❌ FFmpeg installation failed"
    exit 1
fi

# Test psutil installation
PSUTIL_VERSION=$(python3 -c 'import psutil; print(psutil.__version__)' 2>/dev/null || echo "FAILED")

if [[ "$PSUTIL_VERSION" != "FAILED" ]]; then
    echo "✅ Python psutil installed: $PSUTIL_VERSION"
else
    echo "⚠️ Python psutil installation may have failed"
fi

echo ""
echo "=== VPS BASE PROVISION COMPLETE ==="
echo "✅ Base system is ready for EZStream Agent v7.0 deployment from Laravel."
echo "📋 Architecture: Simple FFmpeg Direct Streaming (no SRS, no HLS pipeline)"
echo "🎬 FFmpeg installed for direct RTMP streaming to YouTube/platforms"
echo "🕐 Provision completed at: $(date)"
echo ""
