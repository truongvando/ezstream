#!/bin/bash

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}🔧 EZSTREAM Email Fix Script${NC}"
echo "=================================="

PROJECT_DIR="/var/www/ezstream"
cd $PROJECT_DIR

# 1. Test current SMTP connection
echo -e "${YELLOW}📡 Testing current SMTP connection...${NC}"
MAIL_HOST=$(grep MAIL_HOST .env | cut -d '=' -f2)
MAIL_PORT=$(grep MAIL_PORT .env | cut -d '=' -f2)

echo -e "${BLUE}Current config: $MAIL_HOST:$MAIL_PORT${NC}"

# Test connection
if timeout 10 nc -zv $MAIL_HOST $MAIL_PORT 2>/dev/null; then
    echo -e "${GREEN}✅ SMTP connection successful${NC}"
    echo -e "${YELLOW}Issue might be authentication or other config${NC}"
else
    echo -e "${RED}❌ Cannot connect to $MAIL_HOST:$MAIL_PORT${NC}"
    echo -e "${YELLOW}Trying alternative solutions...${NC}"
    
    # Test alternative ports
    echo -e "${BLUE}Testing alternative ports...${NC}"
    
    # Test port 465 (SSL)
    if timeout 5 nc -zv $MAIL_HOST 465 2>/dev/null; then
        echo -e "${GREEN}✅ Port 465 (SSL) works!${NC}"
        echo -e "${YELLOW}Updating .env to use port 465...${NC}"
        sed -i 's/MAIL_PORT=.*/MAIL_PORT=465/' .env
        sed -i 's/MAIL_ENCRYPTION=.*/MAIL_ENCRYPTION=ssl/' .env
        echo -e "${GREEN}✅ Updated to use SSL on port 465${NC}"
    
    # Test port 25
    elif timeout 5 nc -zv $MAIL_HOST 25 2>/dev/null; then
        echo -e "${GREEN}✅ Port 25 works!${NC}"
        echo -e "${YELLOW}Updating .env to use port 25...${NC}"
        sed -i 's/MAIL_PORT=.*/MAIL_PORT=25/' .env
        sed -i 's/MAIL_ENCRYPTION=.*/MAIL_ENCRYPTION=null/' .env
        echo -e "${GREEN}✅ Updated to use port 25${NC}"
    
    else
        echo -e "${RED}❌ All SMTP ports blocked. Setting up local mail...${NC}"
        
        # Install sendmail if not exists
        if ! command -v sendmail &> /dev/null; then
            echo -e "${YELLOW}📦 Installing sendmail...${NC}"
            apt update && apt install -y sendmail
        fi
        
        # Configure for local mail
        echo -e "${YELLOW}🔧 Configuring local mail...${NC}"
        sed -i 's/MAIL_MAILER=.*/MAIL_MAILER=sendmail/' .env
        sed -i 's/MAIL_HOST=.*/MAIL_HOST=localhost/' .env
        echo -e "${GREEN}✅ Configured to use local sendmail${NC}"
    fi
fi

# 2. Test DNS resolution
echo -e "${YELLOW}🌐 Testing DNS resolution...${NC}"
if nslookup $MAIL_HOST > /dev/null 2>&1; then
    echo -e "${GREEN}✅ DNS resolution works${NC}"
else
    echo -e "${RED}❌ DNS resolution failed${NC}"
    echo -e "${YELLOW}Adding Google DNS...${NC}"
    echo "nameserver 8.8.8.8" >> /etc/resolv.conf
    echo "nameserver 8.8.4.4" >> /etc/resolv.conf
fi

# 3. Check firewall
echo -e "${YELLOW}🔥 Checking firewall...${NC}"
if command -v ufw &> /dev/null; then
    echo -e "${BLUE}UFW status:${NC}"
    ufw status
    echo -e "${YELLOW}Allowing outbound SMTP ports...${NC}"
    ufw allow out 25
    ufw allow out 465
    ufw allow out 587
fi

if command -v iptables &> /dev/null; then
    echo -e "${YELLOW}Checking iptables for SMTP blocks...${NC}"
    # Allow outbound SMTP
    iptables -A OUTPUT -p tcp --dport 25 -j ACCEPT
    iptables -A OUTPUT -p tcp --dport 465 -j ACCEPT
    iptables -A OUTPUT -p tcp --dport 587 -j ACCEPT
fi

# 4. Clear Laravel config cache
echo -e "${YELLOW}🧹 Clearing Laravel cache...${NC}"
php artisan config:clear
php artisan cache:clear

# 5. Test email functionality
echo -e "${YELLOW}🧪 Testing email functionality...${NC}"
php artisan test:email

echo -e "${GREEN}🎯 Email fix script completed!${NC}"
echo -e "${BLUE}If still not working, try these manual steps:${NC}"
echo "1. Contact VPS provider about SMTP port blocking"
echo "2. Use a different SMTP provider (Gmail, Mailgun, etc.)"
echo "3. Set up a mail relay service"
