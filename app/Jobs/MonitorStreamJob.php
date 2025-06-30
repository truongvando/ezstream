<?php

namespace App\Jobs;

use App\Models\StreamConfiguration;
use App\Services\SshService;
use App\Services\TelegramNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class MonitorStreamJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public StreamConfiguration $stream)
    {
        // Add a delay of 30 seconds to allow ffmpeg to start and potentially fail
        $this->delay(now()->addSeconds(30));
    }

    /**
     * Execute the job.
     */
    public function handle(SshService $sshService): void
    {
        // Refresh the model to get the latest status
        $this->stream->refresh();

        // If stream is active or streaming (including recovery), our job is done.
        if (in_array($this->stream->status, ['ACTIVE', 'STREAMING'])) {
            Log::info("Monitoring stream #{$this->stream->id}: Status is {$this->stream->status}. No action needed.");
            return;
        }

        // If stream is not in an error/starting state, something else is going on, so we stop.
        if (!in_array($this->stream->status, ['STARTING', 'ERROR'])) {
            Log::info("Monitoring stream #{$this->stream->id}: Status is {$this->stream->status}. No action needed.");
            return;
        }

        // Check if stream has been updated recently by webhook (indicates active recovery/streaming)
        if ($this->stream->last_status_update && 
            $this->stream->last_status_update->diffInSeconds(now()) < 120) { // Updated within 2 minutes
            Log::info("Monitoring stream #{$this->stream->id}: Recently updated by webhook, skipping SSH monitoring.");
            return;
        }
        
        // Check if we should skip SSH monitoring based on webhook reliability
        $webhookReliable = $this->isWebhookReliable();
        if ($webhookReliable && $this->stream->status === 'STARTING') {
            Log::info("Monitoring stream #{$this->stream->id}: Webhook reliable, extending wait time for SSH check.");
            // Reschedule for later if webhook is working well
            self::dispatch($this->stream)->delay(now()->addMinutes(2));
            return;
        }

        if (!$sshService->connect($this->stream->vpsServer)) {
            Log::error("MonitorStreamJob: Could not connect to VPS #{$this->stream->vpsServer->id}");
            return;
        }

        $logContent = $sshService->readFile($this->stream->output_log_path);

        $errorKeywords = [
            'Conversion failed!' => 'Lỗi chuyển đổi video',
            'Connection refused' => 'Kết nối bị từ chối',
            'Invalid data' => 'Dữ liệu không hợp lệ',
            '404 Not Found' => 'Không tìm thấy nguồn',
            'Authentication failed' => 'Xác thực thất bại',
            'No space left' => 'Hết dung lượng đĩa',
            'Permission denied' => 'Không có quyền truy cập'
        ];
        $errorFound = false;
        $errorType = '';
        foreach ($errorKeywords as $keyword => $description) {
            if (str_contains($logContent, $keyword)) {
                $errorFound = true;
                $errorType = $description;
                break;
            }
        }
        
        // Clean up the log file regardless of the outcome
        $sshService->execute('rm ' . escapeshellarg($this->stream->output_log_path));
        $sshService->disconnect();

        if ($errorFound) {
            // Double-check: Maybe stream recovered after initial error
            $this->stream->refresh();
            
            if (in_array($this->stream->status, ['STREAMING', 'ACTIVE'])) {
                Log::info("Stream #{$this->stream->id} recovered after initial error. Not sending error notification.");
                return;
            }
            
            // Check if backup URL exists (auto-recovery capability)
            $hasBackup = !empty($this->stream->rtmp_backup_url);
            
            Log::error("Stream #{$this->stream->id} failed with error: {$errorType}");
            $this->stream->update(['status' => 'ERROR']);
            
            $message = "🚨 *Stream gặp lỗi!*\n\n";
            $message .= "**Stream:** {$this->stream->title}\n";
            $message .= "**Lỗi:** {$errorType}\n";
            
            if ($hasBackup) {
                $message .= "**Trạng thái:** Hệ thống đang thử kết nối backup URL...\n";
                $message .= "\n**Lưu ý:** Nếu có backup URL, stream có thể tự phục hồi trong vài phút.\n";
            }
            
            $message .= "\n**Hướng dẫn khắc phục:**\n";
            $message .= "• Kiểm tra lại RTMP URL và Stream Key\n";
            $message .= "• Đảm bảo kết nối internet ổn định\n";
            if ($hasBackup) {
                $message .= "• Đợi 2-3 phút để hệ thống thử backup URL\n";
            }
            $message .= "• Thử khởi động lại stream nếu vẫn lỗi\n\n";
            $message .= "**Thời gian:** " . now()->format('d/m/Y H:i:s');
            
            // Delay notification if backup exists (give time for recovery)
            if ($hasBackup) {
                // Schedule delayed notification after 3 minutes
                \App\Jobs\DelayedStreamErrorNotificationJob::dispatch($this->stream, $message)
                    ->delay(now()->addMinutes(3));
            } else {
                $this->notifyUser($message);
            }
        } else {
            Log::info("Stream #{$this->stream->id} seems to have started correctly, but status is not ACTIVE. Manual check might be needed.");
        }
    }

    private function notifyUser(string $message): void
    {
        $user = $this->stream->user;
        if ($user && $user->telegram_bot_token && $user->telegram_chat_id) {
            (new TelegramNotificationService())->sendMessage($user->telegram_bot_token, $user->telegram_chat_id, $message);
        }
    }
    
    /**
     * Check if webhook system is reliable for this stream
     */
    private function isWebhookReliable(): bool
    {
        // Check if this stream has received webhooks recently
        $recentWebhooks = $this->stream->last_status_update && 
                         $this->stream->last_status_update->diffInMinutes(now()) < 10;
        
        // Check if other streams from same VPS are getting webhooks
        $vpsStreams = StreamConfiguration::where('vps_server_id', $this->stream->vps_server_id)
                                        ->where('status', 'STREAMING')
                                        ->whereNotNull('last_status_update')
                                        ->where('last_status_update', '>', now()->subMinutes(10))
                                        ->count();
        
        return $recentWebhooks || $vpsStreams > 0;
    }
}
