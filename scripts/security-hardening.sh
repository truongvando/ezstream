#!/bin/bash

# EZSTREAM Security Hardening Script
# Usage: bash security-hardening.sh

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}🔒 EZSTREAM Security Hardening${NC}"

# Configuration
PROJECT_DIR="/var/www/ezstream"
DB_NAME="sql_ezstream_pro"
DB_USER="root"
DB_PASS="Dodz1997a@"

# 1. Create dedicated database user
echo -e "${YELLOW}🗄️ Creating dedicated database user...${NC}"
DB_APP_USER="ezstream_app"
DB_APP_PASS=$(openssl rand -base64 32)

mysql -u $DB_USER -p$DB_PASS << EOF
CREATE USER IF NOT EXISTS '$DB_APP_USER'@'localhost' IDENTIFIED BY '$DB_APP_PASS';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, INDEX, ALTER ON $DB_NAME.* TO '$DB_APP_USER'@'localhost';
FLUSH PRIVILEGES;
EOF

# Update .env with new user
sed -i "s|DB_USERNAME=.*|DB_USERNAME=$DB_APP_USER|" $PROJECT_DIR/.env
sed -i "s|DB_PASSWORD=.*|DB_PASSWORD=$DB_APP_PASS|" $PROJECT_DIR/.env

echo -e "${GREEN}✅ Database user created: $DB_APP_USER${NC}"

# 2. Secure MySQL configuration
echo -e "${YELLOW}🔒 Securing MySQL configuration...${NC}"
cat >> /etc/mysql/mysql.conf.d/security.cnf << EOF
[mysqld]
# Security settings
bind-address = 127.0.0.1
skip-networking = 0
local-infile = 0
max_connections = 100
max_user_connections = 50
max_connect_errors = 10

# Disable dangerous functions
secure_file_priv = ""
sql_mode = "STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO"
EOF

systemctl restart mysql

# 3. Install and configure Fail2Ban
echo -e "${YELLOW}🛡️ Installing Fail2Ban...${NC}"
apt install -y fail2ban

# Nginx jail
cat > /etc/fail2ban/jail.d/nginx.conf << EOF
[nginx-http-auth]
enabled = true
port = http,https
logpath = /var/log/nginx/error.log

[nginx-limit-req]
enabled = true
port = http,https
logpath = /var/log/nginx/error.log
maxretry = 10
findtime = 600
bantime = 7200

[nginx-botsearch]
enabled = true
port = http,https
logpath = /var/log/nginx/access.log
maxretry = 2
EOF

# SSH jail
cat > /etc/fail2ban/jail.d/ssh.conf << EOF
[sshd]
enabled = true
port = ssh
logpath = /var/log/auth.log
maxretry = 3
bantime = 3600
findtime = 600
EOF

systemctl restart fail2ban

# 4. Configure UFW Firewall
echo -e "${YELLOW}🔥 Configuring UFW Firewall...${NC}"
ufw --force reset
ufw default deny incoming
ufw default allow outgoing

# Allow essential ports
ufw allow 22/tcp    # SSH
ufw allow 80/tcp    # HTTP
ufw allow 443/tcp   # HTTPS

# Rate limiting for SSH
ufw limit 22/tcp

ufw --force enable

# 5. Secure file permissions
echo -e "${YELLOW}🔐 Securing file permissions...${NC}"
# Create separate user for Laravel
useradd -r -s /bin/false laravel || true

# Set proper ownership
chown -R laravel:www-data $PROJECT_DIR
chown -R www-data:www-data $PROJECT_DIR/storage
chown -R www-data:www-data $PROJECT_DIR/bootstrap/cache

# Set secure permissions
chmod -R 755 $PROJECT_DIR
chmod -R 775 $PROJECT_DIR/storage
chmod -R 775 $PROJECT_DIR/bootstrap/cache
chmod 600 $PROJECT_DIR/.env
chmod 644 $PROJECT_DIR/composer.json
chmod 644 $PROJECT_DIR/package.json

# 6. Nginx security headers and rate limiting
echo -e "${YELLOW}🌐 Enhancing Nginx security...${NC}"
cat > /etc/nginx/conf.d/security.conf << EOF
# Rate limiting
limit_req_zone \$binary_remote_addr zone=login:10m rate=5r/m;
limit_req_zone \$binary_remote_addr zone=api:10m rate=100r/m;
limit_req_zone \$binary_remote_addr zone=general:10m rate=10r/s;

# Security headers
add_header X-Frame-Options "SAMEORIGIN" always;
add_header X-XSS-Protection "1; mode=block" always;
add_header X-Content-Type-Options "nosniff" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:; connect-src 'self'; media-src 'self'; object-src 'none'; child-src 'self'; frame-ancestors 'self'; form-action 'self'; base-uri 'self';" always;

# Hide Nginx version
server_tokens off;

# Prevent access to sensitive files
location ~ /\.(env|git|svn) {
    deny all;
    return 404;
}

location ~ /(composer\.(json|lock)|package\.(json|lock)|yarn\.lock) {
    deny all;
    return 404;
}
EOF

# Update main site config with rate limiting
sed -i '/location \/ {/a\        limit_req zone=general burst=20 nodelay;' /etc/nginx/sites-available/ezstream
sed -i '/location ~ \.php\$ {/a\        limit_req zone=general burst=10 nodelay;' /etc/nginx/sites-available/ezstream

# 7. Install and configure ModSecurity (optional)
echo -e "${YELLOW}🛡️ Installing ModSecurity...${NC}"
apt install -y libmodsecurity3 modsecurity-crs

# 8. Setup log monitoring
echo -e "${YELLOW}📊 Setting up log monitoring...${NC}"
cat > /etc/logrotate.d/ezstream << EOF
$PROJECT_DIR/storage/logs/*.log {
    daily
    missingok
    rotate 30
    compress
    delaycompress
    notifempty
    create 644 www-data www-data
    postrotate
        supervisorctl restart ezstream-queue:* ezstream-stream:* ezstream-redis:* ezstream-schedule:*
    endscript
}
EOF

# 9. Disable unnecessary services
echo -e "${YELLOW}🔇 Disabling unnecessary services...${NC}"
systemctl disable apache2 2>/dev/null || true
systemctl stop apache2 2>/dev/null || true

# 10. Setup intrusion detection
echo -e "${YELLOW}👁️ Installing AIDE (intrusion detection)...${NC}"
apt install -y aide
aideinit
mv /var/lib/aide/aide.db.new /var/lib/aide/aide.db

# Add to crontab
(crontab -l 2>/dev/null; echo "0 2 * * * /usr/bin/aide --check") | crontab -

echo -e "${GREEN}🎉 Security hardening completed!${NC}"
echo -e "${BLUE}📋 Security Summary:${NC}"
echo "  • Database: Dedicated user created"
echo "  • Firewall: UFW enabled with rate limiting"
echo "  • Fail2Ban: Protection against brute force"
echo "  • File permissions: Secured"
echo "  • Nginx: Security headers and rate limiting"
echo "  • Log monitoring: Automated rotation"
echo "  • Intrusion detection: AIDE installed"
echo ""
echo -e "${YELLOW}🔑 New database credentials saved to .env${NC}"
echo -e "${YELLOW}⚠️ Please test the application after hardening${NC}"
