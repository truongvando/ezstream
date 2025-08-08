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
     * Test Telegram connection
     */
    public function testConnection()
    {
        $message = "🧪 *EZStream Test Notification*\n\n";
        $message .= "✅ Telegram notification service is working correctly!\n\n";
        $message .= "📊 *System Status:*\n";
        $message .= "• Laravel: " . app()->version() . "\n";
        $message .= "• Environment: " . app()->environment() . "\n";
        $message .= "• Server Time: " . now()->format('d/m/Y H:i:s') . "\n\n";
        $message .= "🎯 This is a test message from EZStream admin panel.";
        
        return $this->sendMessageInternal($message);
    }

    /**
     * Send message with custom credentials (for profile testing)
     */
    public function sendMessage($botToken, $chatId, $message)
    {
        try {
            $response = Http::timeout(10)->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'Markdown',
                'disable_web_page_preview' => true
            ]);

            if ($response->successful()) {
                Log::info('Telegram notification sent successfully (custom credentials)');
                return true;
            } else {
                Log::error('Failed to send Telegram notification (custom credentials)', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error('Telegram notification exception (custom credentials)', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send message using configured credentials
     */
    private function sendMessageInternal($message)
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

        return $this->sendMessageInternal($message);
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

        return $this->sendMessageInternal($message);
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

        return $this->sendMessageInternal($message);
    }
}
