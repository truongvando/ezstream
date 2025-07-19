#!/bin/bash

# EZSTREAM Nginx Setup Script
# Usage: bash setup-nginx.sh

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Configuration
DOMAIN="ezstream.pro"
PROJECT_DIR="/var/www/ezstream"

echo -e "${YELLOW}🌐 Setting up Nginx for EZSTREAM...${NC}"

# Create Nginx configuration
echo -e "${YELLOW}📝 Creating Nginx configuration...${NC}"
cat > /etc/nginx/sites-available/ezstream << EOF
server {
    listen 80;
    server_name $DOMAIN www.$DOMAIN;
    root $PROJECT_DIR/public;
    index index.php index.html;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Content-Security-Policy "default-src 'self' http: https: data: blob: 'unsafe-inline' 'unsafe-eval'" always;

    # File upload size
    client_max_body_size 20G;
    client_body_timeout 300s;
    client_header_timeout 300s;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php\$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
        fastcgi_send_timeout 300;
    }

    # Livewire uploads
    location ^~ /livewire {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    # Static files caching
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|pdf|txt|woff|woff2)\$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # Deny access to hidden files
    location ~ /\.(?!well-known).* {
        deny all;
    }
}
EOF

# Enable site
echo -e "${YELLOW}🔗 Enabling site...${NC}"
ln -sf /etc/nginx/sites-available/ezstream /etc/nginx/sites-enabled/

# Remove default site
rm -f /etc/nginx/sites-enabled/default

# Test Nginx configuration
echo -e "${YELLOW}🧪 Testing Nginx configuration...${NC}"
nginx -t

# Restart Nginx
echo -e "${YELLOW}🔄 Restarting Nginx...${NC}"
systemctl restart nginx
systemctl enable nginx

# Setup SSL with Certbot
echo -e "${YELLOW}🔒 Setting up SSL certificate...${NC}"
certbot --nginx -d $DOMAIN -d www.$DOMAIN --non-interactive --agree-tos --email admin@$DOMAIN

# Test SSL renewal
echo -e "${YELLOW}🔄 Testing SSL renewal...${NC}"
certbot renew --dry-run

echo -e "${GREEN}✅ Nginx setup completed!${NC}"
echo -e "${GREEN}🌐 Website should be accessible at: https://$DOMAIN${NC}"
