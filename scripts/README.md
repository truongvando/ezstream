# 🚀 EZSTREAM VPS Deployment Scripts

Bộ scripts tự động hóa việc deploy EZSTREAM project lên VPS mới và cập nhật code.

## 📋 Danh sách Scripts

### 1. `install.sh` - Cài đặt VPS mới
Cài đặt toàn bộ môi trường: PHP 8.2, MySQL, Nginx, Redis, Composer, Node.js

### 2. `setup-project.sh` - Setup project Laravel
Cài đặt dependencies, cấu hình .env, chạy migrations

### 3. `setup-nginx.sh` - Cấu hình Nginx + SSL
Tạo virtual host, cài SSL certificate với Let's Encrypt

### 4. `deploy.sh` - Cập nhật code
Pull code mới, backup database, chạy migrations, không mất dữ liệu

### 5. `rollback.sh` - Rollback về backup
Khôi phục database về trạng thái trước đó

### 6. `setup-supervisor.sh` - Setup background processes
Tự động chạy queue, stream, schedule với Supervisor

### 7. `manage-processes.sh` - Quản lý processes
Start/stop/restart/monitor các background processes

## 🔧 Cách sử dụng

### Cài đặt VPS mới (One-click):

```bash
# 1. Upload scripts lên VPS
scp -r scripts/ root@your-vps-ip:/root/

# 2. SSH vào VPS
ssh root@your-vps-ip

# 3. Chạy cài đặt
cd /root
chmod +x scripts/*.sh
bash scripts/install.sh

# 4. Clone project code
cd /var/www/ezstream
git clone https://github.com/yourusername/ezstream.git .

# 5. Setup project
bash /root/scripts/setup-project.sh

# 6. Setup Nginx + SSL
bash /root/scripts/setup-nginx.sh

# 7. Setup security
bash /root/scripts/setup-security.sh

# 8. Setup background processes
bash /root/scripts/setup-supervisor.sh
bash /root/scripts/setup-crontab.sh
```

### Cập nhật code (Zero-downtime):

```bash
# Cập nhật từ branch main
bash scripts/deploy.sh main

# Cập nhật từ branch develop
bash scripts/deploy.sh develop
```

### Rollback nếu có lỗi:

```bash
# Xem danh sách backup
bash scripts/rollback.sh

# Rollback về backup cụ thể
bash scripts/rollback.sh /var/backups/ezstream/database_20250719_143000.sql
```

## ⚙️ Cấu hình

Sửa các biến trong từng script:

```bash
DOMAIN="ezstream.pro"
PROJECT_DIR="/var/www/ezstream"
DB_NAME="sql_ezstream_pro"
DB_USER="root"
DB_PASS="Dodz1997a@"
```

## 🔒 Bảo mật

- Scripts tự động tạo backup trước khi cập nhật
- Maintenance mode trong quá trình deploy
- SSL certificate tự động
- File permissions được set đúng

## 📊 Tính năng

✅ **Zero-downtime deployment**
✅ **Automatic database backup**
✅ **Migration safe** - không mất dữ liệu
✅ **Rollback support**
✅ **SSL auto-renewal**
✅ **Cache optimization**
✅ **Error handling**

## 🚨 Lưu ý

1. **Backup tự động**: Mỗi lần deploy sẽ tự động backup database
2. **Git repository**: Cần setup Git repository cho project
3. **Domain DNS**: Đảm bảo domain đã point về IP VPS
4. **Firewall**: Mở port 80, 443, 22

## 📞 Troubleshooting

### Lỗi permissions:
```bash
sudo chown -R www-data:www-data /var/www/ezstream
sudo chmod -R 775 /var/www/ezstream/storage
```

### Lỗi database:
```bash
# Kiểm tra MySQL
sudo systemctl status mysql
mysql -u root -p

# Restore backup
bash scripts/rollback.sh [backup_file]
```

### Lỗi Nginx:
```bash
# Test config
sudo nginx -t

# Restart
sudo systemctl restart nginx
```

## 🎯 Workflow khuyến nghị

1. **Development**: Code trên local
2. **Testing**: Push lên branch `develop`
3. **Staging**: Deploy develop lên staging server
4. **Production**: Merge vào `main` và deploy

```bash
# Deploy staging
bash scripts/deploy.sh develop

# Deploy production
bash scripts/deploy.sh main
```
