#!/bin/bash

# 📧 Email Service Setup Script
# Hỗ trợ Brevo, Resend, Gmail

set -e

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
RED='\033[0;31m'
NC='\033[0m'

echo -e "${BLUE}📧 EMAIL SERVICE SETUP${NC}"
echo "=========================="
echo ""

echo "Chọn email service:"
echo "1. Brevo (300 emails/ngày FREE) - KHUYẾN NGHỊ"
echo "2. Resend (100 emails/ngày FREE)"
echo "3. Gmail SMTP (500 emails/ngày) - Chỉ testing"
echo "4. Mailtrap (1000 emails/tháng FREE)"
echo ""

read -p "Nhập lựa chọn (1-4): " choice

case $choice in
    1)
        echo -e "${GREEN}✅ Đã chọn Brevo${NC}"
        echo ""
        echo "🔗 Hướng dẫn setup Brevo:"
        echo "1. Truy cập: https://www.brevo.com/"
        echo "2. Đăng ký tài khoản miễn phí (không cần credit card)"
        echo "3. Verify email"
        echo "4. Vào SMTP & API → SMTP"
        echo "5. Tạo SMTP key mới"
        echo ""
        
        read -p "📧 Nhập email đăng ký Brevo: " BREVO_EMAIL
        read -p "🔑 Nhập SMTP key từ Brevo: " BREVO_KEY
        read -p "🌐 Nhập domain của bạn: " DOMAIN
        
        # Update .env
        if [ -f .env ]; then
            # Backup .env
            cp .env .env.backup.$(date +%s)
            
            # Update email settings
            sed -i "s|MAIL_MAILER=.*|MAIL_MAILER=smtp|" .env
            sed -i "s|MAIL_HOST=.*|MAIL_HOST=smtp-relay.brevo.com|" .env
            sed -i "s|MAIL_PORT=.*|MAIL_PORT=587|" .env
            sed -i "s|MAIL_USERNAME=.*|MAIL_USERNAME=$BREVO_EMAIL|" .env
            sed -i "s|MAIL_PASSWORD=.*|MAIL_PASSWORD=$BREVO_KEY|" .env
            sed -i "s|MAIL_ENCRYPTION=.*|MAIL_ENCRYPTION=tls|" .env
            sed -i "s|MAIL_FROM_ADDRESS=.*|MAIL_FROM_ADDRESS=noreply@$DOMAIN|" .env
            sed -i "s|MAIL_FROM_NAME=.*|MAIL_FROM_NAME=\"VPS Live Stream Control\"|" .env
            
            echo -e "${GREEN}✅ Đã cập nhật .env với Brevo SMTP${NC}"
        else
            echo -e "${RED}❌ Không tìm thấy file .env${NC}"
            exit 1
        fi
        ;;
        
    2)
        echo -e "${GREEN}✅ Đã chọn Resend${NC}"
        echo ""
        echo "🔗 Hướng dẫn setup Resend:"
        echo "1. Truy cập: https://resend.com/"
        echo "2. Đăng ký tài khoản"
        echo "3. Verify domain hoặc dùng resend.dev"
        echo "4. Tạo API key"
        echo ""
        
        read -p "🔑 Nhập API key từ Resend: " RESEND_KEY
        read -p "🌐 Nhập domain của bạn: " DOMAIN
        
        if [ -f .env ]; then
            cp .env .env.backup.$(date +%s)
            
            sed -i "s|MAIL_MAILER=.*|MAIL_MAILER=smtp|" .env
            sed -i "s|MAIL_HOST=.*|MAIL_HOST=smtp.resend.com|" .env
            sed -i "s|MAIL_PORT=.*|MAIL_PORT=587|" .env
            sed -i "s|MAIL_USERNAME=.*|MAIL_USERNAME=resend|" .env
            sed -i "s|MAIL_PASSWORD=.*|MAIL_PASSWORD=$RESEND_KEY|" .env
            sed -i "s|MAIL_ENCRYPTION=.*|MAIL_ENCRYPTION=tls|" .env
            sed -i "s|MAIL_FROM_ADDRESS=.*|MAIL_FROM_ADDRESS=noreply@$DOMAIN|" .env
            sed -i "s|MAIL_FROM_NAME=.*|MAIL_FROM_NAME=\"VPS Live Stream Control\"|" .env
            
            echo -e "${GREEN}✅ Đã cập nhật .env với Resend SMTP${NC}"
        fi
        ;;
        
    3)
        echo -e "${YELLOW}⚠️ Đã chọn Gmail SMTP (chỉ nên dùng cho testing)${NC}"
        echo ""
        echo "🔗 Hướng dẫn setup Gmail App Password:"
        echo "1. Bật 2FA cho Gmail"
        echo "2. Google Account → Security → App passwords"
        echo "3. Tạo app password cho 'Mail'"
        echo "4. Sử dụng app password này (không phải password thường)"
        echo ""
        
        read -p "📧 Nhập Gmail address: " GMAIL_EMAIL
        read -p "🔑 Nhập App Password (16 ký tự): " GMAIL_PASSWORD
        
        if [ -f .env ]; then
            cp .env .env.backup.$(date +%s)
            
            sed -i "s|MAIL_MAILER=.*|MAIL_MAILER=smtp|" .env
            sed -i "s|MAIL_HOST=.*|MAIL_HOST=smtp.gmail.com|" .env
            sed -i "s|MAIL_PORT=.*|MAIL_PORT=587|" .env
            sed -i "s|MAIL_USERNAME=.*|MAIL_USERNAME=$GMAIL_EMAIL|" .env
            sed -i "s|MAIL_PASSWORD=.*|MAIL_PASSWORD=$GMAIL_PASSWORD|" .env
            sed -i "s|MAIL_ENCRYPTION=.*|MAIL_ENCRYPTION=tls|" .env
            sed -i "s|MAIL_FROM_ADDRESS=.*|MAIL_FROM_ADDRESS=$GMAIL_EMAIL|" .env
            sed -i "s|MAIL_FROM_NAME=.*|MAIL_FROM_NAME=\"VPS Live Stream Control\"|" .env
            
            echo -e "${GREEN}✅ Đã cập nhật .env với Gmail SMTP${NC}"
            echo -e "${YELLOW}⚠️ Lưu ý: Chỉ dùng cho testing, không production!${NC}"
        fi
        ;;
        
    4)
        echo -e "${GREEN}✅ Đã chọn Mailtrap${NC}"
        echo ""
        echo "🔗 Hướng dẫn setup Mailtrap:"
        echo "1. Truy cập: https://mailtrap.io/"
        echo "2. Đăng ký tài khoản"
        echo "3. Vào Email Sending → Domains → Add Domain"
        echo "4. Lấy SMTP credentials"
        echo ""
        
        read -p "📧 Nhập username từ Mailtrap: " MAILTRAP_USER
        read -p "🔑 Nhập password từ Mailtrap: " MAILTRAP_PASS
        read -p "🌐 Nhập domain của bạn: " DOMAIN
        
        if [ -f .env ]; then
            cp .env .env.backup.$(date +%s)
            
            sed -i "s|MAIL_MAILER=.*|MAIL_MAILER=smtp|" .env
            sed -i "s|MAIL_HOST=.*|MAIL_HOST=live.smtp.mailtrap.io|" .env
            sed -i "s|MAIL_PORT=.*|MAIL_PORT=587|" .env
            sed -i "s|MAIL_USERNAME=.*|MAIL_USERNAME=$MAILTRAP_USER|" .env
            sed -i "s|MAIL_PASSWORD=.*|MAIL_PASSWORD=$MAILTRAP_PASS|" .env
            sed -i "s|MAIL_ENCRYPTION=.*|MAIL_ENCRYPTION=tls|" .env
            sed -i "s|MAIL_FROM_ADDRESS=.*|MAIL_FROM_ADDRESS=noreply@$DOMAIN|" .env
            sed -i "s|MAIL_FROM_NAME=.*|MAIL_FROM_NAME=\"VPS Live Stream Control\"|" .env
            
            echo -e "${GREEN}✅ Đã cập nhật .env với Mailtrap SMTP${NC}"
        fi
        ;;
        
    *)
        echo -e "${RED}❌ Lựa chọn không hợp lệ${NC}"
        exit 1
        ;;
esac

# Clear config cache
echo ""
echo "🔄 Clearing config cache..."
php artisan config:clear
php artisan config:cache

# Test email
echo ""
read -p "📧 Nhập email để test gửi mail: " TEST_EMAIL

echo "📤 Gửi email test..."
php artisan tinker --execute="
Mail::raw('🎉 Email service đã được cấu hình thành công!\n\nHệ thống VPS Live Stream Control của bạn có thể gửi email:\n- Reset password\n- Thông báo hệ thống\n- Xác nhận đăng ký\n\nChúc mừng! 🚀', function(\$msg) {
    \$msg->to('$TEST_EMAIL')->subject('✅ Test Email - VPS Live Stream Control');
});
echo 'Email test đã được gửi!';
"

echo ""
echo -e "${GREEN}🎉 SETUP EMAIL HOÀN THÀNH!${NC}"
echo ""
echo "📊 Thông tin cấu hình:"
echo "   - Service: $(grep MAIL_HOST .env | cut -d'=' -f2)"
echo "   - From: $(grep MAIL_FROM_ADDRESS .env | cut -d'=' -f2)"
echo "   - Test email đã gửi tới: $TEST_EMAIL"
echo ""
echo "✅ Hệ thống đã sẵn sàng gửi email!"
echo "⚠️ Kiểm tra spam folder nếu không thấy email test" 