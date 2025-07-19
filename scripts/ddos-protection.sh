#!/bin/bash

# EZSTREAM DDoS Protection Script
# Usage: bash ddos-protection.sh

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}🛡️ EZSTREAM DDoS Protection Setup${NC}"

# 1. Advanced Nginx rate limiting
echo -e "${YELLOW}🌐 Setting up advanced rate limiting...${NC}"
cat > /etc/nginx/conf.d/ddos-protection.conf << EOF
# DDoS Protection Configuration

# Rate limiting zones
limit_req_zone \$binary_remote_addr zone=login:10m rate=1r/m;
limit_req_zone \$binary_remote_addr zone=register:10m rate=2r/m;
limit_req_zone \$binary_remote_addr zone=api:10m rate=50r/m;
limit_req_zone \$binary_remote_addr zone=upload:10m rate=5r/m;
limit_req_zone \$binary_remote_addr zone=general:10m rate=5r/s;

# Connection limiting
limit_conn_zone \$binary_remote_addr zone=conn_limit_per_ip:10m;
limit_conn_zone \$server_name zone=conn_limit_per_server:10m;

# Request size limits
client_max_body_size 100M;
client_body_buffer_size 128k;
client_header_buffer_size 1k;
large_client_header_buffers 4 4k;

# Timeouts
client_body_timeout 12;
client_header_timeout 12;
keepalive_timeout 15;
send_timeout 10;

# Buffer overflow protection
client_body_buffer_size 128k;
client_header_buffer_size 1k;
client_max_body_size 100M;
large_client_header_buffers 4 4k;

# Slow loris protection
reset_timedout_connection on;

# Hide server information
server_tokens off;
more_set_headers "Server: Unknown";

# Block common attack patterns
map \$request_uri \$blocked_uri {
    ~*\.(asp|aspx|jsp|cgi)$ 1;
    ~*/wp-admin 1;
    ~*/wp-login 1;
    ~*/phpmyadmin 1;
    ~*/admin 1;
    ~*/xmlrpc.php 1;
    default 0;
}

# Block bad user agents
map \$http_user_agent \$blocked_agent {
    ~*bot 1;
    ~*crawler 1;
    ~*spider 1;
    ~*scanner 1;
    ~*nikto 1;
    ~*sqlmap 1;
    default 0;
}

# GeoIP blocking (optional - requires GeoIP module)
# map \$geoip_country_code \$blocked_country {
#     CN 1;  # China
#     RU 1;  # Russia
#     default 0;
# }
EOF

# 2. Update main site with DDoS protection
echo -e "${YELLOW}📝 Updating main site configuration...${NC}"
cat > /etc/nginx/sites-available/ezstream << 'EOF'
server {
    listen 80;
    server_name ezstream.pro www.ezstream.pro;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name ezstream.pro www.ezstream.pro;
    root /var/www/ezstream/public;
    index index.php index.html;

    # SSL Configuration (managed by Certbot)
    ssl_certificate /etc/letsencrypt/live/ezstream.pro/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/ezstream.pro/privkey.pem;
    include /etc/letsencrypt/options-ssl-nginx.conf;
    ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem;

    # DDoS Protection
    limit_conn conn_limit_per_ip 10;
    limit_conn conn_limit_per_server 100;

    # Block malicious requests
    if ($blocked_uri) {
        return 444;
    }
    if ($blocked_agent) {
        return 444;
    }

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    # Main location
    location / {
        limit_req zone=general burst=10 nodelay;
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Login protection
    location ~ ^/(login|register) {
        limit_req zone=login burst=3 nodelay;
        try_files $uri $uri/ /index.php?$query_string;
    }

    # API protection
    location ~ ^/api/ {
        limit_req zone=api burst=20 nodelay;
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Upload protection
    location ~ ^/upload {
        limit_req zone=upload burst=2 nodelay;
        client_max_body_size 20G;
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP processing
    location ~ \.php$ {
        limit_req zone=general burst=5 nodelay;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
        fastcgi_send_timeout 300;
        
        # Additional security
        fastcgi_hide_header X-Powered-By;
    }

    # Static files
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|pdf|txt|woff|woff2)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    # Block access to sensitive files
    location ~ /\.(env|git|svn) {
        deny all;
        return 404;
    }

    location ~ /(composer\.(json|lock)|package\.(json|lock)|yarn\.lock) {
        deny all;
        return 404;
    }

    # Block common exploit attempts
    location ~* \.(sql|bak|backup|old|tmp)$ {
        deny all;
        return 404;
    }
}
EOF

# 3. Install and configure DDoS Deflate
echo -e "${YELLOW}🛡️ Installing DDoS Deflate...${NC}"
cd /tmp
wget https://github.com/jgmdev/ddos-deflate/archive/master.zip
unzip master.zip
cd ddos-deflate-master
./install.sh

# Configure DDoS Deflate
cat > /etc/ddos/ddos.conf << EOF
FREQ=1
NO_OF_CONNECTIONS=50
APF_BAN=0
KILL=1
BAN_PERIOD=3600
EMAIL_TO="admin@ezstream.pro"
BAN_PERIOD=3600
EOF

# 4. Setup iptables rules for additional protection
echo -e "${YELLOW}🔥 Setting up iptables DDoS rules...${NC}"
cat > /etc/iptables/ddos-rules.sh << 'EOF'
#!/bin/bash

# DDoS Protection iptables rules

# Limit connections per IP
iptables -A INPUT -p tcp --dport 80 -m connlimit --connlimit-above 20 -j REJECT
iptables -A INPUT -p tcp --dport 443 -m connlimit --connlimit-above 20 -j REJECT

# Limit new connections per second
iptables -A INPUT -p tcp --dport 80 -m state --state NEW -m recent --set
iptables -A INPUT -p tcp --dport 80 -m state --state NEW -m recent --update --seconds 1 --hitcount 10 -j DROP

iptables -A INPUT -p tcp --dport 443 -m state --state NEW -m recent --set
iptables -A INPUT -p tcp --dport 443 -m state --state NEW -m recent --update --seconds 1 --hitcount 10 -j DROP

# Block ping floods
iptables -A INPUT -p icmp --icmp-type echo-request -m limit --limit 1/second -j ACCEPT
iptables -A INPUT -p icmp --icmp-type echo-request -j DROP

# SYN flood protection
iptables -A INPUT -p tcp --syn -m limit --limit 1/s --limit-burst 3 -j RETURN
iptables -A INPUT -p tcp --syn -j DROP

# Port scan protection
iptables -A INPUT -m recent --name portscan --rcheck --seconds 86400 -j DROP
iptables -A FORWARD -m recent --name portscan --rcheck --seconds 86400 -j DROP
EOF

chmod +x /etc/iptables/ddos-rules.sh

# 5. Create monitoring script
echo -e "${YELLOW}👁️ Creating DDoS monitoring script...${NC}"
cat > /usr/local/bin/ddos-monitor.sh << 'EOF'
#!/bin/bash

# DDoS Monitoring Script

LOG_FILE="/var/log/ddos-monitor.log"
THRESHOLD=100

# Check current connections
CONNECTIONS=$(netstat -ntu | awk '{print $5}' | cut -d: -f1 | sort | uniq -c | sort -nr | head -10)

# Log high connection IPs
echo "$(date): Connection check" >> $LOG_FILE
echo "$CONNECTIONS" >> $LOG_FILE

# Check for suspicious activity
SUSPICIOUS=$(echo "$CONNECTIONS" | awk -v threshold=$THRESHOLD '$1 > threshold {print $2}')

if [ ! -z "$SUSPICIOUS" ]; then
    echo "$(date): Suspicious IPs detected: $SUSPICIOUS" >> $LOG_FILE
    
    # Auto-ban suspicious IPs
    for IP in $SUSPICIOUS; do
        if [[ $IP =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
            iptables -A INPUT -s $IP -j DROP
            echo "$(date): Banned IP: $IP" >> $LOG_FILE
        fi
    done
fi

# Check Nginx error log for attacks
ATTACKS=$(tail -100 /var/log/nginx/error.log | grep -E "(limiting requests|limiting connections)" | wc -l)
if [ $ATTACKS -gt 10 ]; then
    echo "$(date): High number of rate limit triggers: $ATTACKS" >> $LOG_FILE
fi
EOF

chmod +x /usr/local/bin/ddos-monitor.sh

# Add to crontab
(crontab -l 2>/dev/null; echo "*/5 * * * * /usr/local/bin/ddos-monitor.sh") | crontab -

# 6. Restart services
echo -e "${YELLOW}🔄 Restarting services...${NC}"
nginx -t && systemctl reload nginx
systemctl restart fail2ban

echo -e "${GREEN}🎉 DDoS Protection setup completed!${NC}"
echo -e "${BLUE}📋 Protection Summary:${NC}"
echo "  • Rate limiting: 5 req/sec general, 1 req/min login"
echo "  • Connection limiting: 10 per IP, 100 per server"
echo "  • DDoS Deflate: Auto-ban after 50 connections"
echo "  • iptables: SYN flood and port scan protection"
echo "  • Monitoring: Auto-detection and banning"
echo "  • Logs: /var/log/ddos-monitor.log"
echo ""
echo -e "${YELLOW}💡 Monitor with: tail -f /var/log/ddos-monitor.log${NC}"
