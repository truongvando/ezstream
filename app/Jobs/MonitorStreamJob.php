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

        // If stream is already active, our job is done.
        if ($this->stream->status === 'ACTIVE') {
            Log::info("Monitoring stream #{$this->stream->id}: Already active. No action needed.");
            return;
        }

        // If stream is not in an error/starting state, something else is going on, so we stop.
        if (!in_array($this->stream->status, ['STARTING', 'ERROR'])) {
            Log::info("Monitoring stream #{$this->stream->id}: Status is {$this->stream->status}. No action needed.");
            return;
        }

        if (!$sshService->connect($this->stream->vpsServer)) {
            Log::error("MonitorStreamJob: Could not connect to VPS #{$this->stream->vpsServer->id}");
            return;
        }

        $logContent = $sshService->readFile($this->stream->output_log_path);

        $errorKeywords = ['Conversion failed!', 'Connection refused', 'Invalid data', '404 Not Found'];
        $errorFound = false;
        foreach ($errorKeywords as $keyword) {
            if (str_contains($logContent, $keyword)) {
                $errorFound = true;
                break;
            }
        }
        
        // Clean up the log file regardless of the outcome
        $sshService->execute('rm ' . escapeshellarg($this->stream->output_log_path));
        $sshService->disconnect();

        if ($errorFound) {
            Log::error("Stream #{$this->stream->id} failed.");
            $this->stream->update(['status' => 'ERROR']);
            $this->notifyUser("Stream '{$this->stream->title}' đã gặp lỗi. Vui lòng kiểm tra lại cấu hình RTMP URL và Stream Key. Các nền tảng như YouTube, Facebook đã có backup tự động nên lỗi có thể do cấu hình không đúng.");
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
}
