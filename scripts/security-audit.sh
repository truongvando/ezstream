#!/bin/bash

# EZSTREAM Security Audit Script
# Usage: bash security-audit.sh

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}🔍 EZSTREAM Security Audit${NC}"
echo "$(date)"
echo "=================================="

# 1. System Information
echo -e "\n${YELLOW}📊 System Information:${NC}"
echo "OS: $(lsb_release -d | cut -f2)"
echo "Kernel: $(uname -r)"
echo "Uptime: $(uptime -p)"
echo "Load: $(uptime | awk -F'load average:' '{print $2}')"

# 2. Network Security
echo -e "\n${YELLOW}🌐 Network Security:${NC}"
echo "Open ports:"
ss -tlnp | grep LISTEN

echo -e "\nFirewall status:"
ufw status

echo -e "\nFail2Ban status:"
fail2ban-client status

# 3. File Permissions Audit
echo -e "\n${YELLOW}🔐 File Permissions Audit:${NC}"
PROJECT_DIR="/var/www/ezstream"

echo "Checking critical files..."
if [ -f "$PROJECT_DIR/.env" ]; then
    PERM=$(stat -c "%a" "$PROJECT_DIR/.env")
    if [ "$PERM" = "600" ]; then
        echo -e "✅ .env permissions: $PERM"
    else
        echo -e "❌ .env permissions: $PERM (should be 600)"
    fi
fi

echo "World-writable files:"
find $PROJECT_DIR -type f -perm -002 2>/dev/null | head -10

echo "SUID/SGID files:"
find $PROJECT_DIR -type f \( -perm -4000 -o -perm -2000 \) 2>/dev/null

# 4. Database Security
echo -e "\n${YELLOW}🗄️ Database Security:${NC}"
echo "MySQL users:"
mysql -e "SELECT User, Host FROM mysql.user;" 2>/dev/null || echo "Cannot access MySQL"

echo "Database connections:"
mysql -e "SHOW PROCESSLIST;" 2>/dev/null | wc -l || echo "Cannot check connections"

# 5. Web Server Security
echo -e "\n${YELLOW}🌐 Web Server Security:${NC}"
echo "Nginx version:"
nginx -v 2>&1

echo "PHP version:"
php -v | head -1

echo "Nginx rate limiting:"
grep -r "limit_req" /etc/nginx/ | wc -l

# 6. SSL/TLS Security
echo -e "\n${YELLOW}🔒 SSL/TLS Security:${NC}"
if [ -f "/etc/letsencrypt/live/ezstream.pro/fullchain.pem" ]; then
    echo "SSL certificate exists"
    EXPIRY=$(openssl x509 -enddate -noout -in /etc/letsencrypt/live/ezstream.pro/fullchain.pem | cut -d= -f2)
    echo "SSL expires: $EXPIRY"
else
    echo "❌ No SSL certificate found"
fi

# 7. Log Analysis
echo -e "\n${YELLOW}📊 Log Analysis (Last 24h):${NC}"
echo "Failed login attempts:"
grep "Failed password" /var/log/auth.log | grep "$(date +%b\ %d)" | wc -l

echo "Nginx 4xx errors:"
grep "$(date +%d/%b/%Y)" /var/log/nginx/access.log | grep " 4[0-9][0-9] " | wc -l

echo "Nginx 5xx errors:"
grep "$(date +%d/%b/%Y)" /var/log/nginx/access.log | grep " 5[0-9][0-9] " | wc -l

echo "Top attacking IPs:"
grep "$(date +%d/%b/%Y)" /var/log/nginx/access.log | awk '{print $1}' | sort | uniq -c | sort -nr | head -5

# 8. Process Security
echo -e "\n${YELLOW}⚙️ Process Security:${NC}"
echo "Processes running as root:"
ps aux | grep "^root" | wc -l

echo "Web server processes:"
ps aux | grep -E "(nginx|php-fpm)" | grep -v grep

# 9. Disk Usage
echo -e "\n${YELLOW}💾 Disk Usage:${NC}"
df -h /

echo "Large files in project:"
find $PROJECT_DIR -type f -size +100M 2>/dev/null | head -5

# 10. Security Updates
echo -e "\n${YELLOW}🔄 Security Updates:${NC}"
apt list --upgradable 2>/dev/null | grep -i security | wc -l

# 11. Intrusion Detection
echo -e "\n${YELLOW}👁️ Intrusion Detection:${NC}"
if command -v aide &> /dev/null; then
    echo "AIDE installed: ✅"
    echo "Last AIDE check: $(stat -c %y /var/lib/aide/aide.db 2>/dev/null || echo 'Never')"
else
    echo "AIDE not installed: ❌"
fi

# 12. Backup Status
echo -e "\n${YELLOW}💾 Backup Status:${NC}"
BACKUP_DIR="/var/backups/ezstream"
if [ -d "$BACKUP_DIR" ]; then
    echo "Backup directory exists: ✅"
    echo "Latest backup: $(ls -t $BACKUP_DIR/database_*.sql 2>/dev/null | head -1 || echo 'None')"
    echo "Backup count: $(ls $BACKUP_DIR/database_*.sql 2>/dev/null | wc -l)"
else
    echo "Backup directory missing: ❌"
fi

# 13. Application Security
echo -e "\n${YELLOW}🚀 Application Security:${NC}"
cd $PROJECT_DIR

echo "Laravel environment:"
grep "APP_ENV=" .env 2>/dev/null || echo "Cannot read .env"

echo "Debug mode:"
grep "APP_DEBUG=" .env 2>/dev/null || echo "Cannot read .env"

echo "Composer security check:"
if command -v composer &> /dev/null; then
    composer audit --no-dev 2>/dev/null | tail -5 || echo "Cannot run security audit"
else
    echo "Composer not found"
fi

# 14. Recommendations
echo -e "\n${BLUE}💡 Security Recommendations:${NC}"

# Check for common issues
ISSUES=0

if [ ! -f "/etc/fail2ban/jail.local" ]; then
    echo "❌ Consider configuring Fail2Ban jail.local"
    ((ISSUES++))
fi

if ! ufw status | grep -q "Status: active"; then
    echo "❌ UFW firewall is not active"
    ((ISSUES++))
fi

if grep -q "APP_DEBUG=true" $PROJECT_DIR/.env 2>/dev/null; then
    echo "❌ Debug mode is enabled in production"
    ((ISSUES++))
fi

if [ ! -f "/etc/letsencrypt/live/ezstream.pro/fullchain.pem" ]; then
    echo "❌ SSL certificate not found"
    ((ISSUES++))
fi

if [ $ISSUES -eq 0 ]; then
    echo -e "${GREEN}✅ No major security issues found${NC}"
else
    echo -e "${RED}⚠️ Found $ISSUES security issues${NC}"
fi

echo -e "\n${YELLOW}🔧 Quick fixes:${NC}"
echo "• Run: bash scripts/security-hardening.sh"
echo "• Run: bash scripts/ddos-protection.sh"
echo "• Update system: apt update && apt upgrade"
echo "• Check logs: tail -f /var/log/auth.log"

echo -e "\n${BLUE}Audit completed at $(date)${NC}"
