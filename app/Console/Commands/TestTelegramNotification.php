<?php

namespace App\Console\Commands;

use App\Services\TelegramNotificationService;
use Illuminate\Console\Command;

class TestTelegramNotification extends Command
{
    protected $signature = 'telegram:test {--message= : Custom test message}';
    protected $description = 'Test Telegram notification service';

    public function handle()
    {
        $this->info('🔔 Testing Telegram Notification Service...');
        $this->newLine();

        // Check configuration
        $botToken = env('TELEGRAM_BOT_TOKEN');
        $chatId = env('TELEGRAM_ADMIN_CHAT_ID');

        $this->table(['Config', 'Value', 'Status'], [
            ['TELEGRAM_BOT_TOKEN', $botToken ? substr($botToken, 0, 10) . '...' : 'NULL', $botToken ? '✅' : '❌'],
            ['TELEGRAM_ADMIN_CHAT_ID', $chatId ?? 'NULL', $chatId ? '✅' : '❌'],
        ]);

        if (!$botToken || !$chatId) {
            $this->error('❌ Telegram credentials not configured in .env file');
            $this->newLine();
            $this->info('💡 Add these to your .env file:');
            $this->line('TELEGRAM_BOT_TOKEN=your_bot_token_here');
            $this->line('TELEGRAM_ADMIN_CHAT_ID=your_chat_id_here');
            return 1;
        }

        // Test connection
        $this->info('📤 Sending test notification...');
        
        $telegramService = new TelegramNotificationService();
        
        $customMessage = $this->option('message');
        if ($customMessage) {
            $message = "🧪 *Custom Test Message*\n\n{$customMessage}\n\n⏰ Sent at: " . now()->format('d/m/Y H:i:s');
        } else {
            $message = "🧪 *EZStream Test Notification*\n\n";
            $message .= "✅ Telegram notification service is working correctly!\n\n";
            $message .= "📊 *System Status:*\n";
            $message .= "• Laravel: " . app()->version() . "\n";
            $message .= "• Environment: " . app()->environment() . "\n";
            $message .= "• Server Time: " . now()->format('d/m/Y H:i:s') . "\n\n";
            $message .= "🎯 This is a test message from EZStream admin panel.";
        }

        $success = $telegramService->testConnection();

        if ($success) {
            $this->info('✅ Test notification sent successfully!');
            $this->info('📱 Check your Telegram chat for the message.');
        } else {
            $this->error('❌ Failed to send test notification');
            $this->error('💡 Check your bot token and chat ID');
        }

        $this->newLine();
        $this->info('🔗 Useful links:');
        $this->line('• Create bot: https://t.me/BotFather');
        $this->line('• Get chat ID: https://t.me/userinfobot');

        return $success ? 0 : 1;
    }
}
