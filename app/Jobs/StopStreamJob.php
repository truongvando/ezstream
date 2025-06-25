<?php

namespace App\Jobs;

use App\Models\StreamConfiguration;
use App\Services\SshService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Services\TelegramNotificationService;

class StopStreamJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public StreamConfiguration $stream)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(SshService $sshService): void
    {
        $vps = $this->stream->vpsServer;
        $pid = $this->stream->ffmpeg_pid;

        if (!$vps || !$pid) {
            $this->stream->update(['status' => 'INACTIVE', 'ffmpeg_pid' => null, 'output_log' => 'VPS or PID not found, marking as INACTIVE.']);
            Log::warning("Could not stop stream {$this->stream->id}: VPS or PID missing.");
            return;
        }

        if (!$sshService->connect($vps)) {
            $this->stream->update(['status' => 'ERROR', 'output_log' => 'Failed to connect to VPS to stop stream.']);
            return;
        }

        $killed = $sshService->killProcess($pid);

        if ($killed) {
            $this->stream->update([
                'status' => 'INACTIVE',
                'ffmpeg_pid' => null,
                'last_stopped_at' => now(),
            ]);
            Log::info("Stream stopped successfully: {$this->stream->title}");
        } else {
            $this->stream->update([
                'status' => 'ERROR',
                'output_log' => "Failed to kill process with PID: {$pid}. Manual intervention may be required.",
            ]);
            Log::error("Failed to stop stream: {$this->stream->title} (PID: {$pid})");
            $this->notifyUserOfError("Failed to stop your stream. The process on the server could not be terminated.");
        }
        
        $sshService->disconnect();
    }

    /**
     * Notify the user about an error with their stream.
     * @param string $errorMessage
     */
    protected function notifyUserOfError(string $errorMessage): void
    {
        $user = $this->stream->user;
        if ($user && $user->telegram_bot_token && $user->telegram_chat_id) {
            $telegramService = new TelegramNotificationService();
            $message = "❌ *Stream Error: {$this->stream->title}*\n\n{$errorMessage}\n\nPlease check your stream configuration or contact support.";
            $telegramService->sendMessage($user->telegram_bot_token, $user->telegram_chat_id, $message);
        }
    }
}
