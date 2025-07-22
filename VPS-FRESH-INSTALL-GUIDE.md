# 🚀 HƯỚNG DẪN CÀI ĐẶT VPS EZSTREAM MỚI

## 📋 TỔNG QUAN
Hướng dẫn chi tiết để cài đặt EZStream lên VPS hoàn toàn mới từ con số 0.

---

## 🎯 YÊU CẦU VPS

### Cấu hình tối thiểu:
- **OS**: Ubuntu 20.04/22.04 LTS
- **RAM**: 2GB (khuyến nghị 4GB+)
- **CPU**: 2 cores
- **Storage**: 50GB SSD (khuyến nghị 100GB+)
- **Network**: 100Mbps unlimited

### Thông tin cần có:
- **VPS IP**: `<YOUR_VPS_IP>`
- **Root password** hoặc SSH key
- **Redis connection**: Host, Port, Password từ Laravel

---

## 🔐 BƯỚC 1: INITIAL SETUP & SECURITY

### SSH vào VPS:
```bash
ssh root@<YOUR_VPS_IP>
```

### Update system:
```bash
# Update packages
apt update && apt upgrade -y

# Set timezone
timedatectl set-timezone Asia/Ho_Chi_Minh
export TZ=Asia/Ho_Chi_Minh

# Install essential tools
apt install -y curl wget jq htop iotop nano vim git unzip
```

### Setup SSH security:
```bash
# Backup SSH config
cp /etc/ssh/sshd_config /etc/ssh/sshd_config.backup

# Configure SSH (optional - nếu muốn tăng security)
cat >> /etc/ssh/sshd_config << 'EOF'
# EZStream SSH Security
PermitRootLogin yes
PasswordAuthentication yes
PubkeyAuthentication yes
MaxAuthTries 3
ClientAliveInterval 300
ClientAliveCountMax 2
EOF

# Restart SSH
systemctl restart sshd
```

---

## 📦 BƯỚC 2: INSTALL CORE PACKAGES

### Install Python & dependencies:
```bash
# Python 3 và pip
apt install -y python3 python3-pip python3-venv

# Python packages cho agent
pip3 install redis psutil requests flask --break-system-packages

# Hoặc nếu lỗi, dùng apt
apt install -y python3-redis python3-psutil python3-requests python3-flask
```

### Install Nginx với RTMP module:
```bash
# Install Nginx + RTMP
apt install -y nginx libnginx-mod-rtmp

# Backup default config
cp /etc/nginx/nginx.conf /etc/nginx/nginx.conf.backup
```

### Install FFmpeg:
```bash
# Install FFmpeg (latest version)
apt install -y ffmpeg

# Verify installation
ffmpeg -version
```

### Install system tools:
```bash
# Supervisor cho process management
apt install -y supervisor

# Redis tools cho debugging
apt install -y redis-tools

# Security tools
apt install -y fail2ban ufw

# System monitoring
apt install -y htop iotop nethogs
```

---

## ⚙️ BƯỚC 3: CONFIGURE NGINX

### Tạo Nginx config cho RTMP:
```bash
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
            record off;
            allow play all;
        }

        # Dynamic RTMP apps directory
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
        
        location /health {
            return 200 "EZStream VPS Ready";
            add_header Content-Type text/plain;
        }
    }
}
EOF

# Tạo thư mục cho dynamic RTMP apps
mkdir -p /etc/nginx/rtmp-apps

# Test config
nginx -t

# Start Nginx
systemctl enable nginx
systemctl start nginx
```

---

## 🤖 BƯỚC 4: INSTALL EZSTREAM AGENT

### Tạo thư mục agent:
```bash
# Tạo thư mục
mkdir -p /opt/ezstream-agent
cd /opt/ezstream-agent

# Tạo log file
touch /var/log/ezstream-agent.log
chmod 666 /var/log/ezstream-agent.log
```

### Download agent từ Laravel:
```bash
# Cách 1: Download từ Laravel server (nếu có public URL)
# wget https://your-domain.com/storage/agent/agent.py

# Cách 2: Copy từ máy local
# scp /path/to/ezstream/storage/app/ezstream-agent/agent.py root@<VPS_IP>:/opt/ezstream-agent/

# Cách 3: Tạo placeholder (sẽ được Laravel upload sau)
cat > /opt/ezstream-agent/agent.py << 'EOF'
#!/usr/bin/env python3
# EZStream Agent Placeholder
# This file will be replaced by Laravel during provision
print("EZStream Agent placeholder - waiting for Laravel provision...")
EOF

chmod +x /opt/ezstream-agent/agent.py
```

### Tạo systemd service:
```bash
cat > /etc/systemd/system/ezstream-agent.service << 'EOF'
[Unit]
Description=EZStream Redis Agent v2.0
After=network.target nginx.service
Requires=nginx.service

[Service]
Type=simple
User=root
WorkingDirectory=/opt/ezstream-agent
ExecStart=/usr/bin/python3 /opt/ezstream-agent/agent.py VPS_ID REDIS_HOST REDIS_PORT REDIS_PASSWORD
Restart=always
RestartSec=10
StandardOutput=journal
StandardError=journal
Environment=PYTHONPATH=/opt/ezstream-agent

[Install]
WantedBy=multi-user.target
EOF

# Note: VPS_ID, REDIS_HOST, etc. sẽ được Laravel thay thế khi provision
```

---

## 🔒 BƯỚC 5: SECURITY SETUP

### Configure Firewall (UFW):
```bash
# Reset UFW
ufw --force reset

# Default policies
ufw default deny incoming
ufw default allow outgoing

# Allow essential ports
ufw allow 22/tcp      # SSH
ufw allow 1935/tcp    # RTMP
ufw allow 8080/tcp    # Nginx stats

# Enable firewall
ufw --force enable

# Check status
ufw status verbose
```

### Configure Fail2ban:
```bash
# Install và enable fail2ban
systemctl enable fail2ban
systemctl start fail2ban

# Tạo custom jail cho SSH
cat > /etc/fail2ban/jail.local << 'EOF'
[DEFAULT]
bantime = 3600
findtime = 600
maxretry = 3

[sshd]
enabled = true
port = ssh
filter = sshd
logpath = /var/log/auth.log
maxretry = 3
bantime = 3600
EOF

# Restart fail2ban
systemctl restart fail2ban

# Check status
fail2ban-client status
```

---

## 📊 BƯỚC 6: SYSTEM OPTIMIZATION

### Configure system limits:
```bash
# Increase file limits for streaming
cat >> /etc/security/limits.conf << 'EOF'
# EZStream optimizations
* soft nofile 65536
* hard nofile 65536
* soft nproc 32768
* hard nproc 32768
EOF

# Kernel optimizations
cat >> /etc/sysctl.conf << 'EOF'
# EZStream network optimizations
net.core.rmem_max = 134217728
net.core.wmem_max = 134217728
net.ipv4.tcp_rmem = 4096 87380 134217728
net.ipv4.tcp_wmem = 4096 65536 134217728
net.core.netdev_max_backlog = 5000
EOF

# Apply changes
sysctl -p
```

### Setup log rotation:
```bash
cat > /etc/logrotate.d/ezstream-agent << 'EOF'
/var/log/ezstream-agent.log {
    daily
    rotate 7
    compress
    delaycompress
    missingok
    notifempty
    create 0644 root root
    postrotate
        systemctl reload ezstream-agent || true
    endscript
}
EOF
```

---

## ✅ BƯỚC 7: VERIFICATION & TESTING

### Check services:
```bash
# Check all services status
systemctl status nginx
systemctl status fail2ban
systemctl status ufw

# Check ports
ss -tulpn | grep -E "(22|1935|8080)"

# Test Nginx
curl http://localhost:8080/health

# Test RTMP stats
curl http://localhost:8080/stat
```

### Create test directories:
```bash
# Tạo thư mục cho downloads
mkdir -p /tmp/ezstream_downloads
chmod 755 /tmp/ezstream_downloads

# Test write permissions
touch /tmp/ezstream_downloads/test.txt
rm /tmp/ezstream_downloads/test.txt
```

### System info:
```bash
# Display system information
echo "=== EZSTREAM VPS SETUP COMPLETED ==="
echo "VPS IP: $(curl -s ifconfig.me)"
echo "OS: $(lsb_release -d | cut -f2)"
echo "Kernel: $(uname -r)"
echo "RAM: $(free -h | grep Mem | awk '{print $2}')"
echo "Disk: $(df -h / | tail -1 | awk '{print $2}')"
echo "Nginx: $(nginx -v 2>&1)"
echo "FFmpeg: $(ffmpeg -version | head -1)"
echo "Python: $(python3 --version)"
echo "=================================="
```

---

## 🔗 BƯỚC 8: LARAVEL INTEGRATION

### Thêm VPS vào Laravel:
```php
// Trong Laravel project
php artisan tinker

// Tạo VPS record mới
App\Models\VpsServer::create([
    'name' => 'VPS-Production-1',
    'ip_address' => '<YOUR_VPS_IP>',
    'status' => 'PROVISIONING',
    'max_concurrent_streams' => 10,
    'current_streams' => 0,
    'capabilities' => json_encode(['multistream', 'nginx-rtmp', 'redis-agent']),
    'webhook_configured' => false
]);
```

### Chạy provision job:
```bash
# Provision VPS từ Laravel
php artisan vps:provision <VPS_ID>

# Hoặc manual dispatch
php artisan tinker --execute="
App\Jobs\ProvisionMultistreamVpsJob::dispatch(
    App\Models\VpsServer::find(<VPS_ID>)
);
"
```

---

## 🧪 BƯỚC 9: FINAL TESTING

### Test checklist:
- [ ] SSH access working
- [ ] Nginx running (port 8080 accessible)
- [ ] RTMP server listening (port 1935)
- [ ] Firewall configured
- [ ] Fail2ban active
- [ ] EZStream Agent service created
- [ ] Log rotation configured
- [ ] Laravel VPS record created

### Test commands:
```bash
# Test từ bên ngoài
curl http://<YOUR_VPS_IP>:8080/health

# Test RTMP (từ máy có ffmpeg)
ffmpeg -re -i test.mp4 -c copy -f flv rtmp://<YOUR_VPS_IP>:1935/live/test

# Monitor logs
tail -f /var/log/nginx/access.log
tail -f /var/log/ezstream-agent.log
```

---

## 📝 POST-INSTALLATION NOTES

### Thông tin quan trọng:
- **VPS IP**: `<YOUR_VPS_IP>`
- **RTMP URL**: `rtmp://<YOUR_VPS_IP>:1935/live/`
- **Stats URL**: `http://<YOUR_VPS_IP>:8080/stat`
- **Health Check**: `http://<YOUR_VPS_IP>:8080/health`

### Next steps:
1. **Update Laravel** với VPS IP mới
2. **Test stream** từ Laravel dashboard
3. **Monitor performance** trong vài ngày đầu
4. **Backup VPS** sau khi stable

### Maintenance commands:
```bash
# Restart services
systemctl restart nginx ezstream-agent

# Check logs
journalctl -u ezstream-agent -f
tail -f /var/log/nginx/error.log

# Monitor resources
htop
iotop
nethogs
```

---

## 🆘 TROUBLESHOOTING

### Common issues:

**Nginx không start:**
```bash
nginx -t  # Check config
systemctl status nginx
journalctl -u nginx
```

**Port không accessible:**
```bash
ufw status
ss -tulpn | grep 1935
iptables -L
```

**Agent không connect Redis:**
```bash
redis-cli -h <REDIS_HOST> -p <REDIS_PORT> -a <PASSWORD> ping
```

**Performance issues:**
```bash
htop  # Check CPU/RAM
iotop  # Check disk I/O
nethogs  # Check network
```

---

**🎉 HOÀN THÀNH! VPS đã sẵn sàng cho EZStream!**

**⏱️ Tổng thời gian setup: ~30-45 phút**

---

## 📋 QUICK SETUP SCRIPT

### One-liner setup (cho advanced users):
```bash
curl -s https://raw.githubusercontent.com/your-repo/ezstream-setup.sh | bash
```

### Manual quick commands:
```bash
# Basic setup
apt update && apt upgrade -y
apt install -y nginx libnginx-mod-rtmp ffmpeg python3 python3-pip supervisor fail2ban ufw redis-tools
pip3 install redis psutil requests flask --break-system-packages

# Security
ufw allow 22,1935,8080/tcp && ufw --force enable
systemctl enable fail2ban nginx && systemctl start fail2ban nginx

# Directories
mkdir -p /opt/ezstream-agent /etc/nginx/rtmp-apps /tmp/ezstream_downloads
touch /var/log/ezstream-agent.log && chmod 666 /var/log/ezstream-agent.log

echo "✅ Quick setup completed! Now configure Nginx and run Laravel provision."
```

---

## 🔄 AUTOMATED PROVISION

### Từ Laravel (recommended):
```bash
# Add VPS to database
php artisan vps:add --ip=<VPS_IP> --name="Production-VPS"

# Auto provision
php artisan vps:provision <VPS_ID>

# Monitor provision
php artisan vps:status <VPS_ID>
```

### Manual provision check:
```bash
# SSH vào VPS và check
systemctl status ezstream-agent
curl http://localhost:8080/health
tail -f /var/log/ezstream-agent.log
```

**🚀 VPS sẵn sàng stream!**
