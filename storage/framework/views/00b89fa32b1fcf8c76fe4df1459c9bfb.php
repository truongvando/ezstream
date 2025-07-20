<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gói dịch vụ sắp hết hạn</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
        .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
        .warning-box { background: #fff3cd; border: 1px solid #ffeaa7; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .package-info { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .cta-button { display: inline-block; background: #007bff; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
        .footer { text-align: center; color: #666; font-size: 12px; margin-top: 30px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>⚠️ Thông báo quan trọng</h1>
            <p>Gói dịch vụ của bạn sắp hết hạn</p>
        </div>
        
        <div class="content">
            <p>Xin chào <strong><?php echo e($user_name); ?></strong>,</p>
            
            <div class="warning-box">
                <h3>🚨 Gói dịch vụ sắp hết hạn</h3>
                <p>Gói <strong><?php echo e($package_name); ?></strong> của bạn sẽ hết hạn trong <strong><?php echo e($days_remaining); ?> ngày</strong>.</p>
                <p><strong>Ngày hết hạn:</strong> <?php echo e($expires_at->format('d/m/Y H:i')); ?></p>
            </div>
            
            <div class="package-info">
                <h3>📦 Thông tin gói hiện tại</h3>
                <ul>
                    <li><strong>Tên gói:</strong> <?php echo e($package_name); ?></li>
                    <li><strong>Số streams:</strong> <?php echo e($max_streams); ?> luồng</li>
                    <li><strong>Hết hạn:</strong> <?php echo e($expires_at->format('d/m/Y H:i')); ?></li>
                </ul>
            </div>
            
            <h3>🔄 Để tiếp tục sử dụng dịch vụ:</h3>
            <ol>
                <li>Đăng nhập vào tài khoản của bạn</li>
                <li>Chọn gói dịch vụ phù hợp</li>
                <li>Thanh toán để gia hạn</li>
            </ol>
            
            <div style="text-align: center;">
                <a href="<?php echo e($renewal_url); ?>" class="cta-button">
                    🔄 Gia hạn ngay
                </a>
            </div>
            
            <div class="warning-box">
                <h4>⚠️ Lưu ý quan trọng:</h4>
                <p>Sau khi gói hết hạn, các stream đang chạy sẽ bị dừng và bạn sẽ không thể tạo stream mới cho đến khi gia hạn.</p>
            </div>
            
            <p>Nếu bạn có bất kỳ câu hỏi nào, vui lòng liên hệ với chúng tôi.</p>
            
            <p>Trân trọng,<br>
            <strong>Đội ngũ VPS Live Stream</strong></p>
        </div>
        
        <div class="footer">
            <p>Email này được gửi tự động. Vui lòng không trả lời email này.</p>
            <p>© <?php echo e(date('Y')); ?> VPS Live Stream Control. All rights reserved.</p>
        </div>
    </div>
</body>
</html> <?php /**PATH D:\laragon\www\ezstream\resources\views/emails/subscription-expiring.blade.php ENDPATH**/ ?>