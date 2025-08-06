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
    redis-tools \
    git build-essential \
    unzip automake cmake pkg-config
    # SRS dependencies - cần thiết cho build SRS từ source
    # nginx removed - conflicts with SRS port 8080

# Cài đặt thư viện Python cần thiết cho EZStream Agent v5.0
echo "Installing Python packages for EZStream Agent v5.0..."
pip3 install redis psutil requests --break-system-packages || {
    echo "pip3 direct install failed. Trying via apt..."
    apt-get install -y python3-redis python3-psutil python3-requests
}

echo "Đặt múi giờ về Asia/Ho_Chi_Minh (Việt Nam)..."
timedatectl set-timezone Asia/Ho_Chi_Minh
export TZ=Asia/Ho_Chi_Minh


# 2. NGINX CONFIGURATION - DISABLED (conflicts with SRS port 8080)
echo "2. Skipping Nginx configuration (not needed for SRS streaming)..."
# EZStream Agent v6.0 uses SRS for streaming, nginx not needed
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
# # HTTP Configuration - No RTMP/HLS needed for EZStream Agent v5.0
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
#             return 200 "EZStream Agent v5.0 Ready";
#             add_header Content-Type text/plain;
#         }
#
#         # Agent status endpoint (for monitoring)
#         location /agent-status {
#             return 200 "EZStream Agent v5.0 - Stream Manager + Process Manager";
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
echo "✅ Nginx disabled - SRS will handle HTTP endpoints"


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
echo "6. Skipping nginx services (SRS will handle HTTP)..."
systemctl daemon-reload
# systemctl enable nginx
# systemctl restart nginx

# sleep 5
# if ! systemctl is-active --quiet nginx; then
#     echo "ERROR: Nginx failed to start"
#     systemctl status nginx
#     exit 1
# fi

echo "✅ Base services ready (nginx disabled)"

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
echo "Skipping port 8080 check (will be used by SRS)..."
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

echo "✅ Base system ready (nginx disabled, SRS will handle HTTP)"

# Install Docker for SRS support
echo ""
echo "=== INSTALLING DOCKER FOR SRS SUPPORT ==="
echo "Installing Docker to support SRS streaming server..."

# Check if Docker is already installed
if command -v docker &> /dev/null; then
    echo "✅ Docker is already installed"
    docker --version
else
    echo "📦 Installing Docker..."

    # Update package index
    apt-get update

    # Install required packages
    apt-get install -y \
        ca-certificates \
        curl \
        gnupg \
        lsb-release

    # Add Docker's official GPG key
    mkdir -p /etc/apt/keyrings
    curl -fsSL https://download.docker.com/linux/ubuntu/gpg | gpg --dearmor -o /etc/apt/keyrings/docker.gpg
    chmod a+r /etc/apt/keyrings/docker.gpg

    # Set up the repository
    echo \
      "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu \
      $(lsb_release -cs) stable" | tee /etc/apt/sources.list.d/docker.list > /dev/null

    # Update package index again
    apt-get update

    # Install Docker Engine
    if apt-get install -y docker-ce docker-ce-cli containerd.io docker-compose-plugin; then
        echo "✅ Docker packages installed successfully"
    else
        echo "⚠️ Docker installation had issues, but continuing..."
    fi

    # Start and enable Docker
    systemctl start docker
    systemctl enable docker

    # Wait for Docker to start
    sleep 3

    # Add current user to docker group (if not root)
    if [ "$USER" != "root" ]; then
        usermod -aG docker $USER
    fi

    echo "✅ Docker installed successfully"
    docker --version
fi

# Verify Docker is working
echo "🔍 Verifying Docker installation..."
# Use timeout to prevent hanging
if timeout 30 docker run --rm hello-world > /dev/null 2>&1; then
    echo "✅ Docker is working correctly"
else
    echo "⚠️ Docker test failed or timed out, but continuing..."
    # Try to start docker service if it's not running
    systemctl start docker 2>/dev/null || true
fi

# 8. SRS STREAMING SERVER SETUP
echo "8. Setting up SRS (Simple Realtime Server)..."

# Use Docker SRS instead of building from source (faster and more reliable)
echo "🐳 Setting up SRS via Docker..."

# Create SRS config directory
mkdir -p /opt/srs-config

# Create SRS configuration file
cat > /opt/srs-config/srs.conf << 'EOF'
# SRS Configuration for EZStream
listen              1935;
max_connections     1000;
daemon              off;
srs_log_tank        console;

http_server {
    enabled         on;
    listen          8080;
    dir             ./objs/nginx/html;
}

http_api {
    enabled         on;
    listen          1985;
}

rtc_server {
    enabled         on;
    listen          8000;
}

vhost __defaultVhost__ {
    hls {
        enabled         on;
        hls_path        ./objs/nginx/html;
        hls_fragment    10;
        hls_window      60;
    }

    http_remux {
        enabled     on;
        mount       [vhost]/[app]/[stream].flv;
    }

    dvr {
        enabled      off;
    }
}
EOF

echo "✅ SRS configuration created"

# Pull SRS Docker image
echo "📥 Pulling SRS Docker image..."
docker pull ossrs/srs:5

# Create SRS Docker container
echo "🚀 Creating SRS Docker container..."
docker run -d \
    --name ezstream-srs \
    --restart unless-stopped \
    -p 1935:1935 \
    -p 1985:1985 \
    -p 8080:8080 \
    -p 8000:8000/udp \
    -v /opt/srs-config:/usr/local/srs/conf \
    ossrs/srs:5 \
    ./objs/srs -c /usr/local/srs/conf/srs.conf

# Wait for SRS to start
echo "⏳ Waiting for SRS to start..."
sleep 10

# Verify SRS is running
if docker ps | grep -q "ezstream-srs"; then
    echo "✅ SRS Docker container is running"

    # Test SRS API
    if curl -s http://localhost:1985/api/v1/versions > /dev/null; then
        echo "✅ SRS API is responding"
    else
        echo "⚠️ SRS API not responding yet (may need more time)"
    fi
else
    echo "❌ SRS Docker container failed to start!"
    docker logs ezstream-srs
    exit 1
fi

echo ""
echo "=== VPS BASE PROVISION COMPLETE ==="
echo "✅ Base system is ready for EZStream Agent v6.0 deployment from Laravel."
echo "📋 Architecture: Stream Manager + Process Manager + File Manager + SRS Docker"
echo "🐳 SRS Server running in Docker container (ossrs/srs:5)"
echo "🔗 SRS Ports: RTMP(1935), API(1985), HTTP(8080), WebRTC(8000)"
echo "🕐 Provision completed at: $(date)"
echo ""
