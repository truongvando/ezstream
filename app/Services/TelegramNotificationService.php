<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramNotificationService
{
    private $botToken;
    private $chatId;

    public function __construct()
    {
        $this->botToken = env('TELEGRAM_BOT_TOKEN');
        $this->chatId = env('TELEGRAM_ADMIN_CHAT_ID');
    }

    /**
     * Send notification to admin about insufficient funds
     */
    public function notifyInsufficientFunds($order, $error)
    {
        $message = "🚨 *CẢNH BÁO: Nhà cung cấp hết tiền*\n\n";
        $message .= "📋 *Đơn hàng:* #{$order->id}\n";
        $message .= "👤 *User:* {$order->user->name} (ID: {$order->user_id})\n";
        $message .= "🎯 *Service:* {$order->service_id}\n";
        $message .= "📊 *Số lượng:* " . number_format($order->quantity) . "\n";
        $message .= "💰 *Số tiền:* $" . number_format($order->total_amount, 2) . "\n";
        $message .= "🔗 *Link:* {$order->link}\n\n";
        $message .= "❌ *Lỗi API:* {$error}\n\n";
        $message .= "⚡ *Hành động cần thiết:*\n";
        $message .= "1. Nạp tiền vào tài khoản JAP\n";
        $message .= "2. Vào admin panel xử lý đơn hàng\n";
        $message .= "3. Retry đơn hàng cho user\n\n";
        $message .= "🔗 [Xử lý đơn hàng](" . route('admin.pending-orders') . ")";

        return $this->sendMessage($message);
    }

    /**
     * Send notification about order processing issues
     */
    public function notifyOrderIssue($order, $issue, $action = null)
    {
        $message = "⚠️ *Vấn đề đơn hàng*\n\n";
        $message .= "📋 *Đơn hàng:* #{$order->id}\n";
        $message .= "👤 *User:* {$order->user->name}\n";
        $message .= "🎯 *Service:* {$order->service_id}\n";
        $message .= "💰 *Số tiền:* $" . number_format($order->total_amount, 2) . "\n";

        if ($order->api_order_id) {
            $message .= "🆔 *JAP Order ID:* {$order->api_order_id}\n";
        }

        $message .= "\n❌ *Vấn đề:* {$issue}\n";

        if ($action) {
            $message .= "\n🔧 *Hành động:* {$action}";
        }

        $message .= "\n\n🔗 [Xử lý đơn hàng](" . route('admin.pending-orders') . ")";

        return $this->sendMessage($message);
    }

    /**
     * Send notification about cancel request issues
     */
    public function notifyCancelIssue($order, $reason, $japStatus = null)
    {
        $message = "🚫 *Yêu cầu hủy đơn - Cần xem xét*\n\n";
        $message .= "📋 *Đơn hàng:* #{$order->id}\n";
        $message .= "👤 *User:* {$order->user->name}\n";
        $message .= "🎯 *Service:* {$order->service_id}\n";
        $message .= "💰 *Số tiền:* $" . number_format($order->total_amount, 2) . "\n";
        $message .= "🆔 *JAP Order ID:* {$order->api_order_id}\n";

        if ($japStatus) {
            $message .= "📊 *JAP Status:* {$japStatus}\n";
        }

        $message .= "\n❌ *Lý do không thể hoàn tiền:* {$reason}\n\n";
        $message .= "⚡ *Hành động cần thiết:*\n";
        $message .= "1. Kiểm tra trạng thái đơn hàng trên JAP\n";
        $message .= "2. Xác định có thể hoàn tiền không\n";
        $message .= "3. Xử lý manual hoàn tiền nếu cần\n";
        $message .= "4. Thông báo kết quả cho user\n\n";
        $message .= "🔗 [Xử lý đơn hàng](" . route('admin.orders.index') . ")";

        return $this->sendMessage($message);
    }

    /**
     * Send notification about successful order processing
     */
    public function notifyOrderSuccess($order, $apiOrderId)
    {
        $message = "✅ *Đơn hàng thành công*\n\n";
        $message .= "📋 *Đơn hàng:* #{$order->id}\n";
        $message .= "🆔 *API Order ID:* {$apiOrderId}\n";
        $message .= "👤 *User:* {$order->user->name}\n";
        $message .= "💰 *Số tiền:* $" . number_format($order->total_amount, 2) . "\n";
        $message .= "📊 *Số lượng:* " . number_format($order->quantity);

        return $this->sendMessage($message);
    }

    /**
     * Send notification about balance low warning
     */
    public function notifyLowBalance($currentBalance, $threshold = 10)
    {
        $message = "⚠️ *CẢNH BÁO: Số dư JAP thấp*\n\n";
        $message .= "💰 *Số dư hiện tại:* $" . number_format($currentBalance, 2) . "\n";
        $message .= "📊 *Ngưỡng cảnh báo:* $" . number_format($threshold, 2) . "\n\n";
        $message .= "🔔 *Khuyến nghị:* Nạp tiền vào tài khoản JAP để tránh gián đoạn dịch vụ\n\n";
        $message .= "🔗 [Kiểm tra số dư JAP](https://justanotherpanel.com)";

        return $this->sendMessage($message);
    }

    /**
     * Send message to Telegram
     */
    private function sendMessage($message)
    {
        if (!$this->botToken || !$this->chatId) {
            Log::warning('Telegram credentials not configured');
            return false;
        }

        try {
            $response = Http::timeout(10)->post("https://api.telegram.org/bot{$this->botToken}/sendMessage", [
                'chat_id' => $this->chatId,
                'text' => $message,
                'parse_mode' => 'Markdown',
                'disable_web_page_preview' => true
            ]);

            if ($response->successful()) {
                Log::info('Telegram notification sent successfully');
                return true;
            } else {
                Log::error('Failed to send Telegram notification', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error('Telegram notification exception', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Test Telegram connection
     */
    public function testConnection()
    {
        $message = "🧪 *Test Notification*\n\nTelegram notification service is working correctly!";
        return $this->sendMessage($message);
    }
}
