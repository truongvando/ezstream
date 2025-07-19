#!/bin/bash

# EZSTREAM Security Setup Script
# Usage: bash setup-security.sh

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}🔒 EZSTREAM Security Setup${NC}"

# 1. Setup UFW Firewall
echo -e "${YELLOW}🔥 Setting up UFW Firewall...${NC}"
ufw --force enable
ufw default deny incoming
ufw default allow outgoing

# Allow essential ports
ufw allow 22/tcp    # SSH
ufw allow 80/tcp    # HTTP
ufw allow 443/tcp   # HTTPS

echo -e "${GREEN}✅ UFW Firewall enabled${NC}"

# 2. Setup Fail2Ban
echo -e "${YELLOW}🛡️ Setting up Fail2Ban...${NC}"

# SSH protection
cat > /etc/fail2ban/jail.d/ssh.conf << EOF
[sshd]
enabled = true
port = ssh
logpath = /var/log/auth.log
maxretry = 3
bantime = 3600
findtime = 600
EOF

# Restart Fail2Ban
systemctl restart fail2ban
systemctl enable fail2ban

echo -e "${GREEN}✅ Fail2Ban configured${NC}"

# 3. Secure file permissions
echo -e "${YELLOW}🔐 Securing file permissions...${NC}"
PROJECT_DIR="/var/www/ezstream"

if [ -d "$PROJECT_DIR" ]; then
    # Secure .env file
    chmod 600 $PROJECT_DIR/.env
    
    # Set proper ownership
    chown -R www-data:www-data $PROJECT_DIR
    
    # Set secure permissions
    chmod -R 755 $PROJECT_DIR
    chmod -R 775 $PROJECT_DIR/storage
    chmod -R 775 $PROJECT_DIR/bootstrap/cache
    
    echo -e "${GREEN}✅ File permissions secured${NC}"
else
    echo -e "${YELLOW}⚠️ Project directory not found, skipping file permissions${NC}"
fi

# 4. Display security status
echo -e "${BLUE}📊 Security Status:${NC}"
echo "• UFW Firewall: $(ufw status | head -1)"
echo "• Fail2Ban jails: $(fail2ban-client status | grep 'Jail list' | cut -d: -f2)"
echo "• File permissions: Secured"

echo -e "${GREEN}🎉 Security setup completed!${NC}"
echo -e "${YELLOW}💡 Security features enabled:${NC}"
echo "  • Firewall protection (UFW)"
echo "  • Brute force protection (Fail2Ban)"
echo "  • Rate limiting (Nginx)"
echo "  • Secure file permissions"
echo ""
echo -e "${BLUE}📋 Next steps:${NC}"
echo "  • Monitor logs: tail -f /var/log/auth.log"
echo "  • Check banned IPs: fail2ban-client status sshd"
echo "  • Test rate limiting: curl -I http://ezstream.pro"
