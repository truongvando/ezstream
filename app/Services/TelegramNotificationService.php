<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramNotificationService
{
    /**
     * Sends a message to a specific Telegram user.
     *
     * @param string $botToken
     * @param string $chatId
     * @param string $message
     * @return bool
     */
    public function sendMessage(string $botToken, string $chatId, string $message): bool
    {
        if (empty($botToken) || empty($chatId)) {
            return false;
        }

        $url = "https://api.telegram.org/bot{$botToken}/sendMessage";

        try {
            $response = Http::post($url, [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'Markdown',
            ]);

            if ($response->successful()) {
                Log::info("Telegram notification sent to Chat ID: {$chatId}");
                return true;
            } else {
                Log::error("Failed to send Telegram notification to {$chatId}. Response: " . $response->body());
                return false;
            }
        } catch (\Exception $e) {
            Log::error("Exception while sending Telegram notification: " . $e->getMessage());
            return false;
        }
    }
} 