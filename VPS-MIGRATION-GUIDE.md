# 🚚 HƯỚNG DẪN MIGRATION VPS EZSTREAM

## 📋 TỔNG QUAN
Hướng dẫn chi tiết để migrate toàn bộ VPS EZStream sang server mới mà không cần cài lại từ đầu.

---

## 🎯 CHUẨN BỊ

### 1. Thông tin cần có:
- **VPS cũ IP**: `103.90.227.24` (hoặc IP hiện tại)
- **VPS mới IP**: `<NEW_VPS_IP>`
- **SSH access** vào cả 2 VPS
- **Root privileges** trên cả 2 VPS

### 2. Yêu cầu VPS mới:
- **OS**: Ubuntu 20.04/22.04 (giống VPS cũ)
- **RAM**: Tối thiểu 2GB
- **Storage**: Tối thiểu 50GB
- **Network**: Port 22, 1935, 8080 mở

---

## 📦 BƯỚC 1: BACKUP VPS CŨ

### SSH vào VPS cũ:
```bash
ssh root@103.90.227.24
```

### Tạo script backup:
```bash
cat > /tmp/backup-ezstream.sh << 'EOF'
#!/bin/bash
echo "🔄 Starting EZStream VPS backup..."

# Tạo thư mục backup
mkdir -p /tmp/ezstream-backup
cd /tmp/ezstream-backup

# 1. Backup system configs
echo "📁 Backing up system configs..."
tar -czf system-configs.tar.gz \
    /etc/nginx/ \
    /etc/systemd/system/ezstream-agent.service \
    /etc/supervisor/ \
    /etc/logrotate.d/ezstream-agent \
    /etc/ufw/ \
    /etc/fail2ban/ \
    /root/.ssh/authorized_keys

# 2. Backup EZStream Agent
echo "🤖 Backing up EZStream Agent..."
tar -czf ezstream-agent.tar.gz \
    /opt/ezstream-agent/ \
    /var/log/ezstream-agent.log*

# 3. Backup application data
echo "📊 Backing up application data..."
tar -czf app-data.tar.gz \
    /tmp/ezstream_downloads/ \
    /etc/nginx/rtmp-apps/

# 4. Export package lists
echo "📋 Exporting package lists..."
dpkg --get-selections > installed-packages.txt
pip3 list --format=freeze > python-packages.txt

# 5. Export system info
echo "ℹ️ Exporting system info..."
uname -a > system-info.txt
nginx -v > nginx-version.txt 2>&1
python3 --version > python-version.txt
systemctl list-enabled > enabled-services.txt

# 6. Create restore script
cat > restore-commands.txt << 'RESTORE_EOF'
# Commands to run on new VPS after file transfer:
systemctl daemon-reload
systemctl enable nginx ezstream-agent fail2ban ufw
systemctl start nginx
systemctl start ezstream-agent
ufw --force enable
nginx -t && systemctl restart nginx
RESTORE_EOF

echo "✅ Backup completed!"
echo "📁 Backup files location: /tmp/ezstream-backup/"
ls -la /tmp/ezstream-backup/
EOF

chmod +x /tmp/backup-ezstream.sh
/tmp/backup-ezstream.sh
```

### Download backup files:
```bash
# Tạo archive tổng
cd /tmp
tar -czf ezstream-full-backup-$(date +%Y%m%d).tar.gz ezstream-backup/

# Download về máy local (chạy từ máy local)
scp root@103.90.227.24:/tmp/ezstream-full-backup-*.tar.gz ./
```

---

## 🚀 BƯỚC 2: SETUP VPS MỚI

### SSH vào VPS mới:
```bash
ssh root@<NEW_VPS_IP>
```

### Cài đặt base system:
```bash
# Update system
apt update && apt upgrade -y

# Set timezone
timedatectl set-timezone Asia/Ho_Chi_Minh

# Install basic packages
apt install -y curl wget jq htop iotop supervisor \
    python3 python3-pip redis-tools fail2ban ufw

# Install Nginx with RTMP
apt install -y nginx libnginx-mod-rtmp

# Install FFmpeg
apt install -y ffmpeg
```

---

## 📥 BƯỚC 3: RESTORE BACKUP

### Upload backup lên VPS mới:
```bash
# Từ máy local
scp ezstream-full-backup-*.tar.gz root@<NEW_VPS_IP>:/tmp/
```

### Extract và restore:
```bash
# SSH vào VPS mới
ssh root@<NEW_VPS_IP>

# Extract backup
cd /tmp
tar -xzf ezstream-full-backup-*.tar.gz
cd ezstream-backup

# Restore packages
xargs apt install -y < installed-packages.txt
pip3 install -r python-packages.txt --break-system-packages

# Restore system configs
cd /
tar -xzf /tmp/ezstream-backup/system-configs.tar.gz

# Restore EZStream Agent
tar -xzf /tmp/ezstream-backup/ezstream-agent.tar.gz

# Restore app data
tar -xzf /tmp/ezstream-backup/app-data.tar.gz

# Set permissions
chmod +x /opt/ezstream-agent/agent.py
chmod 666 /var/log/ezstream-agent.log

# Create required directories
mkdir -p /etc/nginx/rtmp-apps
mkdir -p /tmp/ezstream_downloads
```

---

## ⚙️ BƯỚC 4: CONFIGURE SERVICES

### Start services:
```bash
# Reload systemd
systemctl daemon-reload

# Enable services
systemctl enable nginx
systemctl enable ezstream-agent
systemctl enable fail2ban
systemctl enable ufw

# Start services
systemctl start nginx
systemctl start ezstream-agent
systemctl start fail2ban

# Configure firewall
ufw allow 22/tcp
ufw allow 1935/tcp
ufw allow 8080/tcp
ufw --force enable

# Test Nginx config
nginx -t

# Restart Nginx if config is OK
systemctl restart nginx
```

### Verify services:
```bash
# Check service status
systemctl status nginx
systemctl status ezstream-agent
systemctl status fail2ban

# Check ports
ss -tulpn | grep -E "(22|1935|8080)"

# Check agent logs
tail -f /var/log/ezstream-agent.log

# Test Nginx RTMP
curl http://localhost:8080/health
```

---

## 🔄 BƯỚC 5: UPDATE LARAVEL

### Trong Laravel project:
```php
// Update VPS IP trong database
php artisan tinker

// Tìm VPS cũ và update IP
$vps = App\Models\VpsServer::where('ip_address', '103.90.227.24')->first();
$vps->update([
    'ip_address' => '<NEW_VPS_IP>',
    'status' => 'ACTIVE',
    'last_provisioned_at' => now()
]);

// Verify update
App\Models\VpsServer::find($vps->id);
```

### Test connection:
```bash
# Test Redis command từ Laravel
php artisan tinker --execute="
\$redis = app('redis')->connection();
\$result = \$redis->publish('vps-commands:<VPS_ID>', json_encode(['command' => 'TEST']));
echo 'Subscribers: ' . \$result;
"
```

---

## ✅ BƯỚC 6: VERIFICATION

### Checklist hoàn thành:
- [ ] Nginx running và accessible (port 1935, 8080)
- [ ] EZStream Agent running và connected to Redis
- [ ] Firewall configured
- [ ] Fail2ban active
- [ ] Laravel updated với IP mới
- [ ] Test stream start/stop thành công

### Test commands:
```bash
# Test RTMP
curl http://<NEW_VPS_IP>:8080/stat

# Test agent connection
tail -f /var/log/ezstream-agent.log | grep "Connected to Redis"

# Test stream (từ Laravel)
# Start một stream và check logs
```

---

## 🚨 ROLLBACK (Nếu cần)

### Nếu migration thất bại:
```bash
# Revert Laravel về VPS cũ
php artisan tinker --execute="
App\Models\VpsServer::where('ip_address', '<NEW_VPS_IP>')
    ->update(['ip_address' => '103.90.227.24']);
"

# VPS cũ vẫn hoạt động bình thường
```

---

## 📝 GHI CHÚ

### Thời gian migration:
- **Backup**: 5-10 phút
- **Setup VPS mới**: 15-20 phút  
- **Restore**: 10-15 phút
- **Testing**: 10 phút
- **Tổng**: ~45-60 phút

### Lưu ý quan trọng:
1. **Backup trước khi migrate**
2. **Test trên VPS staging trước**
3. **Giữ VPS cũ 24h để rollback**
4. **Update DNS nếu cần**
5. **Monitor logs sau migration**

### Files quan trọng cần backup:
- `/opt/ezstream-agent/agent.py`
- `/etc/nginx/nginx.conf`
- `/etc/systemd/system/ezstream-agent.service`
- `/var/log/ezstream-agent.log`
- SSH keys và firewall rules

---

## 🆘 TROUBLESHOOTING

### Agent không start:
```bash
journalctl -u ezstream-agent -f
# Check Redis connection, Python dependencies
```

### Nginx lỗi:
```bash
nginx -t
# Check config syntax, RTMP module
```

### Firewall issues:
```bash
ufw status verbose
# Check port rules
```

**🎯 Hoàn thành! VPS mới đã sẵn sàng với toàn bộ config cũ.**
