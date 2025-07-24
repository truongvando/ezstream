#!/bin/bash
# ==============================================================================
# EZSTREAM BASE PROVISION SCRIPT v3.0 (for Redis Agent)
# ==============================================================================
#
# MÔ TẢ:
# Script này chuẩn bị một VPS mới để chạy các stream. Nó cài đặt các phần
# mềm cần thiết như Nginx (với RTMP module), FFmpeg, và các công cụ hệ
# thống. Nó KHÔNG cài đặt agent, việc đó sẽ do Laravel Job xử lý.
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
    curl wget jq ffmpeg nginx \
    python3 python3-pip \
    htop iotop supervisor \
    libnginx-mod-rtmp \
    redis-tools # Cần thiết cho redis-cli (debug)

# Cài đặt thư viện Python cần thiết cho agent
echo "Installing Python packages for Redis Agent..."
pip3 install redis psutil requests --break-system-packages || {
    echo "pip3 direct install failed. Trying via apt..."
    apt-get install -y python3-redis python3-psutil python3-requests
}

echo "Đặt múi giờ về Asia/Ho_Chi_Minh (Việt Nam)..."
timedatectl set-timezone Asia/Ho_Chi_Minh
export TZ=Asia/Ho_Chi_Minh


# 2. NGINX CONFIGURATION FOR MULTISTREAM
echo "2. Configuring Nginx for multistream..."
# (Giữ nguyên cấu hình Nginx vì nó vẫn cần thiết cho RTMP)
cp /etc/nginx/nginx.conf /etc/nginx/nginx.conf.backup
cat > /etc/nginx/nginx.conf << 'EOF'
user www-data;
worker_processes auto;
pid /run/nginx.pid;
include /etc/nginx/modules-enabled/*.conf;

events {
    worker_connections 1024;
    use epoll;
    multi_accept on;
}

# RTMP Configuration for Multiple Concurrent Streams
rtmp {
    server {
        listen 1935;
        chunk_size 4096;
        
        # Chỉ cho phép ffmpeg chạy trên localhost publish stream
        allow publish 127.0.0.1;
        deny publish all;
        
        # Application chính cho tất cả các stream
        application live {
            live on;
            record off; # Tắt ghi lại mặc định

            # Cho phép mọi người xem stream
            allow play all;

            # Ghi chú: Các lệnh on_publish, on_disconnect đã bị loại bỏ
            # vì chúng thuộc về kiến trúc webhook cũ. Trong kiến trúc
            # mới dựa trên Redis, agent sẽ hoạt động độc lập và không
            # cần callback từ Nginx.
        }

        # THÊM DÒNG NÀY ĐỂ HỖ TRỢ APP ĐỘNG
        include /etc/nginx/rtmp-apps/*.conf;
    }
}

# HTTP Configuration
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
    
    # RTMP Statistics and Health Check
    server {
        listen 8080;
        server_name _;
        
        location /stat {
            rtmp_stat all;
            rtmp_stat_stylesheet stat.xsl;
            add_header Access-Control-Allow-Origin *;
        }
        
        location /stat.xsl {
            root /var/www/html;
        }
        
        location /health {
            return 200 "VPS Base Ready";
            add_header Content-Type text/plain;
        }
    }
}
EOF

# Tạo thư mục cho các app RTMP động nếu chưa có
mkdir -p /etc/nginx/rtmp-apps

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
ufw allow 1935/tcp  # RTMP
ufw allow 8080/tcp  # Nginx stats
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


# 7. FINAL CHECK
echo "7. Performing final system check..."
if ! ss -tulpn | grep -q ":1935"; then
    echo "ERROR: RTMP port 1935 not listening"
    exit 1
fi

if ! curl -s http://localhost:8080/health | grep -q "Ready"; then
    echo "ERROR: Nginx health check failed"
    exit 1
fi

echo ""
echo "=== VPS BASE PROVISION COMPLETE ==="
echo "✅ Base system is ready for Redis Agent deployment from Laravel."
echo ""
