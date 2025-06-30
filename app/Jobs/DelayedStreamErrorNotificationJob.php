<?php

namespace App\Jobs;

use App\Models\StreamConfiguration;
use App\Services\TelegramNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DelayedStreamErrorNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public StreamConfiguration $stream,
        public string $errorMessage
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Refresh stream to get latest status
        $this->stream->refresh();
        
        // If stream recovered, don't send error notification
        if (in_array($this->stream->status, ['STREAMING', 'ACTIVE', 'COMPLETED'])) {
            Log::info("Stream #{$this->stream->id} recovered successfully. Sending recovery notification instead.");
            $this->sendRecoveryNotification();
            return;
        }
        
        // If stream is still in error state, send the error notification
        if ($this->stream->status === 'ERROR') {
            Log::info("Stream #{$this->stream->id} still in error state after recovery attempt. Sending error notification.");
            $this->sendErrorNotification();
        } else {
            Log::info("Stream #{$this->stream->id} status is {$this->stream->status}. No notification needed.");
        }
    }
    
    private function sendErrorNotification(): void
    {
        $user = $this->stream->user;
        if ($user && $user->telegram_bot_token && $user->telegram_chat_id) {
            $finalMessage = $this->errorMessage . "\n\n❌ **Kết quả:** Stream vẫn gặp lỗi sau khi thử backup URL.";
            (new TelegramNotificationService())->sendMessage(
                $user->telegram_bot_token, 
                $user->telegram_chat_id, 
                $finalMessage
            );
        }
    }
    
    private function sendRecoveryNotification(): void
    {
        $user = $this->stream->user;
        if ($user && $user->telegram_bot_token && $user->telegram_chat_id) {
            $message = "✅ *Stream đã phục hồi!*\n\n";
            $message .= "**Stream:** {$this->stream->title}\n";
            $message .= "**Trạng thái:** {$this->stream->status}\n";
            $message .= "**Thông tin:** Hệ thống đã tự động chuyển sang backup URL và stream hoạt động bình thường.\n\n";
            $message .= "**Thời gian phục hồi:** " . now()->format('d/m/Y H:i:s');
            
            (new TelegramNotificationService())->sendMessage(
                $user->telegram_bot_token, 
                $user->telegram_chat_id, 
                $message
            );
        }
    }
}
