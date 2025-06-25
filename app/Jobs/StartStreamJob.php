<?php

namespace App\Jobs;

use App\Models\StreamConfiguration;
use App\Services\SshService;
use App\Services\DirectStreamingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Services\TelegramNotificationService;
use App\Jobs\MonitorStreamJob;

class StartStreamJob implements ShouldQueue
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
    public function handle(SshService $sshService, DirectStreamingService $directStreamingService): void
    {
        $vps = $this->stream->vpsServer;
        $logPath = "/var/log/livestream_{$this->stream->id}.log";
        $this->stream->update(['output_log_path' => $logPath]);

        if (!$vps) {
            $this->stream->update(['status' => 'ERROR', 'output_log' => 'VPS server not found.']);
            return;
        }

        if (!$sshService->connect($vps)) {
            $this->stream->update(['status' => 'ERROR', 'output_log' => 'Failed to connect to VPS via SSH.']);
            return;
        }

        try {
            // Xác định source type và build command tương ứng
            $ffmpegCommand = $this->buildOptimizedFfmpegCommand($this->stream, $directStreamingService);

            // Execute and get PID
            $pid = $sshService->executeInBackgroundAndGetPid($ffmpegCommand);
            
            $sshService->disconnect();

            if ($pid) {
                $this->stream->update([
                    'status' => 'ACTIVE',
                    'ffmpeg_pid' => $pid,
                    'last_started_at' => now(),
                ]);
                Log::info("Stream command sent successfully for: {$this->stream->title} (PID: {$pid})");
                
                // Dispatch the monitor job to check for initial errors
                MonitorStreamJob::dispatch($this->stream);
            } else {
                $this->stream->update([
                    'status' => 'ERROR',
                    'output_log' => 'Failed to execute FFmpeg command or get PID.',
                ]);
                Log::error("Failed to start stream: {$this->stream->title}");
                $this->notifyUserOfError('Failed to start your stream. The system could not execute the command on the VPS.');
            }
        } catch (\Exception $e) {
            $this->stream->update([
                'status' => 'ERROR',
                'output_log' => 'Error: ' . $e->getMessage()
            ]);
            Log::error("Stream start error: {$e->getMessage()}", [
                'stream_id' => $this->stream->id,
                'trace' => $e->getTraceAsString()
            ]);
            $this->notifyUserOfError('Stream failed to start: ' . $e->getMessage());
        }
    }

    /**
     * Build optimized FFmpeg command based on file source
     */
    protected function buildOptimizedFfmpegCommand(StreamConfiguration $stream, DirectStreamingService $directStreamingService): string
    {
        $userFile = $stream->userFile;
        
        // Kiểm tra nếu file từ Google Drive
        if ($userFile && $userFile->download_source === 'google_drive' && $userFile->google_drive_file_id) {
            return $this->buildDirectStreamingCommand($stream, $directStreamingService);
        }
        
        // Fallback to traditional file-based streaming
        return $this->buildTraditionalCommand($stream);
    }

    /**
     * Build direct streaming command từ Google Drive
     */
    protected function buildDirectStreamingCommand(StreamConfiguration $stream, DirectStreamingService $directStreamingService): string
    {
        $userFile = $stream->userFile;
        
        // Lấy direct URL từ Google Drive
        $directUrl = $directStreamingService->getGoogleDriveDirectUrl($userFile->google_drive_file_id);
        if (!$directUrl) {
            throw new \Exception('Cannot get direct download URL from Google Drive');
        }

        Log::info('Using direct streaming from Google Drive', [
            'stream_id' => $stream->id,
            'file_id' => $userFile->google_drive_file_id,
            'direct_url_available' => !empty($directUrl)
        ]);

        $loopOption = $stream->loop ? '-stream_loop -1' : '';
        $rtmpUrl = escapeshellarg($stream->rtmp_url . '/' . $stream->stream_key);
        
        // Optimized options cho direct streaming
        $streamingOptions = $this->getDirectStreamingOptions($stream);
        
        // Headers để access Google Drive
        $headers = '-headers "User-Agent: Mozilla/5.0 (compatible; FFmpeg-Streamer/1.0)" -headers "Referer: https://drive.google.com/"';
        
        // Build command với direct URL
        return "ffmpeg {$loopOption} -re {$headers} -i '{$directUrl}' {$streamingOptions} -f flv {$rtmpUrl}";
    }

    /**
     * Get optimized options cho direct streaming
     */
    protected function getDirectStreamingOptions(StreamConfiguration $stream): string
    {
        switch ($stream->stream_preset) {
            case 'optimized':
                // Transcode để giảm bandwidth và CPU
                return '-c:v libx264 -preset veryfast -crf 23 -maxrate 3000k -bufsize 6000k -c:a aac -b:a 128k -ar 44100';
                
            case 'high_quality':
                // High quality với adaptive bitrate
                return '-c:v libx264 -preset medium -crf 20 -maxrate 5000k -bufsize 10000k -c:a aac -b:a 192k -ar 48000';
                
            case 'low_latency':
                // Low latency cho live streaming
                return '-c:v libx264 -preset ultrafast -tune zerolatency -crf 25 -maxrate 2000k -bufsize 4000k -c:a aac -b:a 96k';
                
            case 'direct':
            default:
                // Copy stream nếu format tương thích
                return '-c:v copy -c:a copy';
        }
    }

    /**
     * Build traditional command cho file trên VPS
     */
    protected function buildTraditionalCommand(StreamConfiguration $stream): string
    {
        $loopOption = $stream->loop ? '-stream_loop -1' : '';
        $videoPath = escapeshellarg($stream->video_source_path);
        $rtmpUrl = escapeshellarg($stream->rtmp_url . '/' . $stream->stream_key);
        
        $presetOptions = '';
        switch ($stream->stream_preset) {
            case 'optimized':
                $presetOptions = '-c:v libx264 -preset veryfast -crf 28 -c:a aac -b:a 128k';
                break;
            case 'direct':
            default:
                $presetOptions = '-c:v copy -c:a copy';
                break;
        }

        return "ffmpeg {$loopOption} -re -i {$videoPath} {$presetOptions} -f flv {$rtmpUrl}";
    }

    /**
     * Alternative: Sử dụng yt-dlp để extract best quality URL
     */
    protected function buildYtDlpStreamingCommand(StreamConfiguration $stream): string
    {
        $userFile = $stream->userFile;
        $googleDriveUrl = $userFile->source_url;
        
        $loopOption = $stream->loop ? '-stream_loop -1' : '';
        $rtmpUrl = escapeshellarg($stream->rtmp_url . '/' . $stream->stream_key);
        $streamingOptions = $this->getDirectStreamingOptions($stream);
        
        // Sử dụng yt-dlp để extract URL trong FFmpeg command
        $ytDlpExtract = "$(yt-dlp -g --no-warnings '{$googleDriveUrl}' | head -1)";
        
        return "ffmpeg {$loopOption} -re -i {$ytDlpExtract} {$streamingOptions} -f flv {$rtmpUrl}";
    }

    /**
     * Notify the user about an error with their stream.
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
