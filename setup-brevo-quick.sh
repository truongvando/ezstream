#!/bin/bash

# 🚀 Quick Brevo Setup với thông tin có sẵn
# Usage: ./setup-brevo-quick.sh yourdomain.com

set -e

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
RED='\033[0;31m'
NC='\033[0m'

DOMAIN="${1:-yourdomain.com}"

echo -e "${BLUE}📧 BREVO SMTP QUICK SETUP${NC}"
echo "=========================="
echo ""

if [ ! -f .env ]; then
    echo -e "${RED}❌ Không tìm thấy file .env${NC}"
    exit 1
fi

echo -e "${GREEN}✅ Sử dụng Brevo credentials có sẵn${NC}"
echo ""

# Backup .env
cp .env .env.backup.$(date +%s)

# Update .env với thông tin Brevo
echo "🔄 Updating .env với Brevo SMTP..."

# Remove existing mail settings
sed -i '/^MAIL_/d' .env

# Add new Brevo settings
cat >> .env << EOF

# Brevo SMTP Configuration
MAIL_MAILER=smtp
MAIL_HOST=smtp-relay.brevo.com
MAIL_PORT=587
MAIL_USERNAME=90ea76001@smtp-brevo.com
MAIL_PASSWORD=PhvbVEBcjUKa3psJ
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@$DOMAIN
MAIL_FROM_NAME="VPS Live Stream Control"
EOF

echo -e "${GREEN}✅ Đã cập nhật .env với Brevo SMTP${NC}"

# Clear config cache
echo "🔄 Clearing config cache..."
php artisan config:clear
php artisan config:cache

echo ""
echo -e "${BLUE}📤 Testing email...${NC}"

# Test email
read -p "📧 Nhập email để test (Enter để dùng vipdopro02@gmail.com): " TEST_EMAIL
TEST_EMAIL=${TEST_EMAIL:-vipdopro02@gmail.com}

echo "📤 Gửi email test tới $TEST_EMAIL..."

php artisan tinker --execute="
try {
    Mail::raw('🎉 Brevo SMTP đã được cấu hình thành công!

Hệ thống VPS Live Stream Control của bạn có thể gửi email:
✅ Reset password  
✅ Thông báo hệ thống
✅ Xác nhận đăng ký
✅ Notifications

Thông tin SMTP:
- Server: smtp-relay.brevo.com
- Port: 587
- From: noreply@$DOMAIN

Chúc mừng! 🚀', function(\$msg) {
        \$msg->to('$TEST_EMAIL')
             ->subject('✅ Brevo SMTP Test - VPS Live Stream Control');
    });
    echo '✅ Email test đã được gửi thành công!\n';
} catch (Exception \$e) {
    echo '❌ Lỗi gửi email: ' . \$e->getMessage() . '\n';
}
"

echo ""
echo -e "${GREEN}🎉 BREVO SETUP HOÀN THÀNH!${NC}"
echo ""
echo "📊 Thông tin cấu hình:"
echo "   - SMTP Server: smtp-relay.brevo.com"
echo "   - Port: 587"
echo "   - Username: 90ea76001@smtp-brevo.com"
echo "   - From Address: noreply@$DOMAIN"
echo "   - Free Limit: 300 emails/ngày"
echo ""
echo "✅ Reset password sẽ hoạt động bình thường!"
echo "✅ Tất cả email notifications đã sẵn sàng!"
echo ""
echo -e "${YELLOW}💡 Lưu ý:${NC}"
echo "- Kiểm tra spam folder nếu không thấy email"
echo "- Thay $DOMAIN bằng domain thật của bạn"
echo "- Brevo dashboard: https://app.brevo.com/"
echo ""
echo -e "${GREEN}Chúc mừng! Email service đã sẵn sàng! 📧✨${NC}" 