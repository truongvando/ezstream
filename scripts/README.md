# 🚀 EZSTREAM VPS Deployment Scripts

Essential scripts for EZSTREAM application deployment and management.

## 📋 **Available Scripts:**

### 🚀 **Deployment & Management:**
- **`deploy.sh`** - Main deployment script with full automation
- **`rollback.sh`** - Rollback deployment to previous state
- **`manage-processes.sh`** - Start/stop/restart background processes

### 🔧 **Setup & Configuration:**
- **`install.sh`** - Initial server setup (PHP 8.2, MySQL, Nginx, Redis)
- **`setup-supervisor.sh`** - Configure background processes (5 workers)
- **`setup-nginx.sh`** - Nginx web server + SSL configuration
- **`security-hardening.sh`** - Server security hardening

### 💾 **Database Management:**
- **`backup-database.sh`** - Create database backups
- **`restore-database.sh`** - Restore database from backup

---

## 🎯 **Quick Start:**

### **🚀 Deploy Application:**
```bash
bash scripts/deploy.sh
```

### **🔧 Setup New Server:**
```bash
# 1. Initial server setup
bash scripts/install.sh

# 2. Setup background processes
bash scripts/setup-supervisor.sh

# 3. Configure Nginx + SSL
bash scripts/setup-nginx.sh

# 4. Security hardening
bash scripts/security-hardening.sh
```

### **⚙️ Manage Background Processes:**
```bash
# Check status
bash scripts/manage-processes.sh status

# Restart all processes
bash scripts/manage-processes.sh restart

# Stop all processes
bash scripts/manage-processes.sh stop
```

### **💾 Database Operations:**
```bash
# Create backup
bash scripts/backup-database.sh

# Restore from backup
bash scripts/restore-database.sh /path/to/backup.sql
```

### **🔄 Rollback Deployment:**
```bash
# Rollback to previous state
bash scripts/rollback.sh
```

---

## 🔧 **Background Processes:**

The system runs **5 essential background processes** via Supervisor:

1. **ezstream-queue** - Default queue worker
2. **ezstream-vps** - VPS provisioning queue worker
3. **ezstream-agent** - Agent reports listener (Redis)
4. **ezstream-redis** - VPS stats subscriber
5. **ezstream-schedule** - Laravel scheduler

### **Monitor Processes:**
```bash
# Check all processes
sudo supervisorctl status | grep ezstream

# View logs
tail -f /var/www/ezstream/storage/logs/laravel.log
```

---

## ✅ **Features:**

- 🚀 **Zero-downtime deployment**
- 💾 **Automatic database backup**
- 🔄 **Safe migrations** - No data loss
- ⏪ **Rollback support**
- 🔒 **SSL auto-renewal**
- ⚡ **Cache optimization**
- 🛡️ **Security hardening**
- 📊 **Process monitoring**

---

## 📞 **Troubleshooting:**

### **🔧 Process Issues:**
```bash
# Check process status
sudo supervisorctl status | grep ezstream

# Restart failed processes
sudo supervisorctl restart ezstream-queue:*
sudo supervisorctl restart ezstream-vps:*

# View process logs
tail -f /var/www/ezstream/storage/logs/vps-queue.log
```

### **🗄️ Database Issues:**
```bash
# Check MySQL status
sudo systemctl status mysql

# Test connection
php artisan tinker --execute="DB::connection()->getPdo(); echo 'Connected!';"

# Restore from backup
bash scripts/restore-database.sh /var/backups/ezstream/database_*.sql
```

### **🌐 Web Server Issues:**
```bash
# Test Nginx config
sudo nginx -t

# Restart services
sudo systemctl restart nginx
sudo systemctl restart php8.2-fpm
```

### **📦 Queue Issues:**
```bash
# Monitor queues
php artisan queue:monitor

# Check failed jobs
php artisan queue:failed

# Process VPS jobs manually
php artisan queue:work --queue=vps-provisioning --once
```

---

## 🎯 **Best Practices:**

1. **Always backup** before major changes
2. **Test deployments** on staging first
3. **Monitor processes** after deployment
4. **Check logs** for any errors
5. **Keep scripts updated** with latest changes

---

## 📝 **Configuration:**

Key variables in scripts:
```bash
DOMAIN="ezstream.pro"
PROJECT_DIR="/var/www/ezstream"
DB_NAME="sql_ezstream_pro"
BACKUP_DIR="/var/backups/ezstream"
```
