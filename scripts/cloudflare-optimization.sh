#!/bin/bash

# EZSTREAM Cloudflare Optimization Script
# Usage: bash cloudflare-optimization.sh

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}☁️ EZSTREAM Cloudflare Optimization${NC}"

# Configuration
PROJECT_DIR="/var/www/ezstream"

# 1. Install mod_cloudflare for real IP detection
echo -e "${YELLOW}🌐 Installing Cloudflare real IP detection...${NC}"

# Create Nginx config for Cloudflare IPs
cat > /etc/nginx/conf.d/cloudflare.conf << 'EOF'
# Cloudflare IP ranges for real IP detection
# Updated: 2025-07-19

# IPv4
set_real_ip_from 173.245.48.0/20;
set_real_ip_from 103.21.244.0/22;
set_real_ip_from 103.22.200.0/22;
set_real_ip_from 103.31.4.0/22;
set_real_ip_from 141.101.64.0/18;
set_real_ip_from 108.162.192.0/18;
set_real_ip_from 190.93.240.0/20;
set_real_ip_from 188.114.96.0/20;
set_real_ip_from 197.234.240.0/22;
set_real_ip_from 198.41.128.0/17;
set_real_ip_from 162.158.0.0/15;
set_real_ip_from 104.16.0.0/13;
set_real_ip_from 104.24.0.0/14;
set_real_ip_from 172.64.0.0/13;
set_real_ip_from 131.0.72.0/22;

# IPv6
set_real_ip_from 2400:cb00::/32;
set_real_ip_from 2606:4700::/32;
set_real_ip_from 2803:f800::/32;
set_real_ip_from 2405:b500::/32;
set_real_ip_from 2405:8100::/32;
set_real_ip_from 2a06:98c0::/29;
set_real_ip_from 2c0f:f248::/32;

# Use CF-Connecting-IP header for real IP
real_ip_header CF-Connecting-IP;
real_ip_recursive on;
EOF

# 2. Update Nginx site config for Cloudflare
echo -e "${YELLOW}📝 Updating Nginx config for Cloudflare...${NC}"
cat > /etc/nginx/sites-available/ezstream << 'EOF'
server {
    listen 80;
    server_name ezstream.pro www.ezstream.pro;
    
    # Redirect HTTP to HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name ezstream.pro www.ezstream.pro;
    root /var/www/ezstream/public;
    index index.php index.html;

    # SSL Configuration (Cloudflare Origin Certificate)
    ssl_certificate /etc/ssl/certs/cloudflare-origin.pem;
    ssl_certificate_key /etc/ssl/private/cloudflare-origin.key;
    
    # SSL Security
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512:ECDHE-RSA-AES256-GCM-SHA384:DHE-RSA-AES256-GCM-SHA384;
    ssl_prefer_server_ciphers off;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 10m;

    # Cloudflare optimizations
    add_header CF-Cache-Status $http_cf_cache_status;
    add_header CF-Ray $http_cf_ray;
    
    # Security headers (some handled by Cloudflare)
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    # Rate limiting (reduced since Cloudflare handles most)
    limit_req zone=general burst=50 nodelay;
    limit_conn conn_limit_per_ip 50;

    # Main location
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # API with higher limits (Cloudflare protects)
    location ~ ^/api/ {
        limit_req zone=api burst=100 nodelay;
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Upload with Cloudflare protection
    location ~ ^/upload {
        client_max_body_size 20G;
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP processing
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
        fastcgi_send_timeout 300;
        
        # Pass real IP to PHP
        fastcgi_param HTTP_CF_CONNECTING_IP $http_cf_connecting_ip;
        fastcgi_param HTTP_CF_IPCOUNTRY $http_cf_ipcountry;
        fastcgi_param HTTP_CF_RAY $http_cf_ray;
        fastcgi_param HTTP_CF_VISITOR $http_cf_visitor;
    }

    # Static files with long cache (Cloudflare will cache)
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|pdf|txt|woff|woff2|svg)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        add_header Vary "Accept-Encoding";
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

    # Health check for Cloudflare
    location /health {
        access_log off;
        return 200 "OK\n";
        add_header Content-Type text/plain;
    }
}
EOF

# 3. Create Cloudflare origin certificate placeholder
echo -e "${YELLOW}🔒 Setting up Cloudflare origin certificate...${NC}"
echo "⚠️  You need to create Cloudflare Origin Certificate:"
echo "1. Go to Cloudflare Dashboard > SSL/TLS > Origin Server"
echo "2. Create Certificate"
echo "3. Save certificate to: /etc/ssl/certs/cloudflare-origin.pem"
echo "4. Save private key to: /etc/ssl/private/cloudflare-origin.key"
echo ""

# Create directories
mkdir -p /etc/ssl/certs /etc/ssl/private

# 4. Update Laravel for Cloudflare
echo -e "${YELLOW}🚀 Configuring Laravel for Cloudflare...${NC}"

# Add Cloudflare trusted proxies to Laravel
cat >> $PROJECT_DIR/.env << 'EOF'

# Cloudflare Configuration
CLOUDFLARE_ENABLED=true
TRUSTED_PROXIES=*
EOF

# 5. Create Cloudflare cache purge script
echo -e "${YELLOW}🧹 Creating cache purge script...${NC}"
cat > /usr/local/bin/purge-cloudflare.sh << 'EOF'
#!/bin/bash

# Cloudflare Cache Purge Script
# Usage: purge-cloudflare.sh [zone_id] [api_token]

ZONE_ID="$1"
API_TOKEN="$2"

if [ -z "$ZONE_ID" ] || [ -z "$API_TOKEN" ]; then
    echo "Usage: purge-cloudflare.sh [zone_id] [api_token]"
    echo "Get these from Cloudflare Dashboard"
    exit 1
fi

echo "Purging Cloudflare cache..."

curl -X POST "https://api.cloudflare.com/client/v4/zones/$ZONE_ID/purge_cache" \
     -H "Authorization: Bearer $API_TOKEN" \
     -H "Content-Type: application/json" \
     --data '{"purge_everything":true}'

echo "Cache purge completed!"
EOF

chmod +x /usr/local/bin/purge-cloudflare.sh

# 6. Update deploy script to purge Cloudflare cache
echo -e "${YELLOW}🔄 Updating deploy script for Cloudflare...${NC}"
cat >> $PROJECT_DIR/scripts/deploy.sh << 'EOF'

# Purge Cloudflare cache after deployment
echo -e "${YELLOW}🧹 Purging Cloudflare cache...${NC}"
if [ -f "/usr/local/bin/purge-cloudflare.sh" ] && [ ! -z "$CLOUDFLARE_ZONE_ID" ] && [ ! -z "$CLOUDFLARE_API_TOKEN" ]; then
    /usr/local/bin/purge-cloudflare.sh "$CLOUDFLARE_ZONE_ID" "$CLOUDFLARE_API_TOKEN"
else
    echo "⚠️ Cloudflare credentials not set, skipping cache purge"
    echo "Set CLOUDFLARE_ZONE_ID and CLOUDFLARE_API_TOKEN environment variables"
fi
EOF

# 7. Test Nginx configuration
echo -e "${YELLOW}🧪 Testing Nginx configuration...${NC}"
nginx -t

echo -e "${GREEN}✅ Cloudflare optimization completed!${NC}"
echo -e "${BLUE}📋 Next Steps:${NC}"
echo "1. Create Cloudflare Origin Certificate:"
echo "   • Dashboard > SSL/TLS > Origin Server > Create Certificate"
echo "   • Save to /etc/ssl/certs/cloudflare-origin.pem"
echo "   • Save key to /etc/ssl/private/cloudflare-origin.key"
echo ""
echo "2. Set Cloudflare SSL mode to 'Full (strict)'"
echo ""
echo "3. Configure Cloudflare settings:"
echo "   • Security > DDoS Protection: High"
echo "   • Speed > Optimization: Auto Minify CSS/JS/HTML"
echo "   • Caching > Browser Cache TTL: 1 year"
echo ""
echo "4. Set environment variables for cache purging:"
echo "   export CLOUDFLARE_ZONE_ID='your_zone_id'"
echo "   export CLOUDFLARE_API_TOKEN='your_api_token'"
echo ""
echo "5. Reload Nginx: systemctl reload nginx"
echo ""
echo -e "${YELLOW}💡 Test real IP detection:${NC}"
echo "Check logs: tail -f /var/log/nginx/access.log"
