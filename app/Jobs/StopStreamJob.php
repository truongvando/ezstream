<?php

namespace App\Jobs;

use App\Models\StreamConfiguration;
use App\Services\SshService;
use App\Services\VpsFileManagerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class StopStreamJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     */
    public $timeout = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(public StreamConfiguration $stream)
    {
        //
    }

    /**
     * Execute the job - Send stop job package to VPS agent
     */
    public function handle(SshService $sshService, VpsFileManagerService $fileManager): void
    {
        Log::info("Stopping stream: {$this->stream->title}", ['stream_id' => $this->stream->id]);

        try {
            $vps = $this->stream->vpsServer;
            if (!$vps || $vps->status !== 'ACTIVE') {
                throw new \Exception('VPS server is not available');
            }

            // Connect to VPS
            if (!$sshService->connect($vps)) {
                throw new \Exception('Failed to connect to VPS via SSH');
            }

            $streamId = $this->stream->id;
            
            // Create stop job package (consistent with start workflow)
            $stopJobPackage = [
                'action' => 'STOP',
                'stream_id' => $streamId,
                'timestamp' => time(),
                'webhook_url' => $this->getWebhookUrl(),
                'webhook_secret' => $this->generateWebhookSecret($streamId),
            ];

            // Upload stop job package to VPS
            $jobFileName = "stop_job_{$streamId}_" . uniqid() . ".json";
            $localJobFile = storage_path("app/temp/{$jobFileName}");
            
            // Create temp directory if not exists
            if (!file_exists(dirname($localJobFile))) {
                mkdir(dirname($localJobFile), 0755, true);
            }
            
            file_put_contents($localJobFile, json_encode($stopJobPackage, JSON_PRETTY_PRINT));
            
            // Upload to VPS Job Queue (TỐI ƯU HÓA)
            $remoteJobPath = "/opt/job-queue/incoming/{$jobFileName}";
            if (!$sshService->uploadFile($localJobFile, $remoteJobPath)) {
                throw new \Exception('Failed to upload stop job package to VPS job queue');
            }
            
            // Job Queue Daemon sẽ tự động xử lý stop job
            Log::info("Stop job package queued for processing by daemon", [
                'stream_id' => $streamId,
                'job_file' => $remoteJobPath
            ]);
            
            // Update status to STOPPING (webhook will update to STOPPED)
            $this->stream->update([
                'status' => 'STOPPING',
                'output_log' => 'Stop job sent to VPS agent, waiting for confirmation...',
            ]);

            // Cleanup local temp file
            unlink($localJobFile);

            $sshService->disconnect();

            // Schedule file cleanup after stream stops (keep files cached for reuse)
            $fileManager->cleanupAfterStream($this->stream);

        } catch (\Exception $e) {
            // Fallback to direct SSH kill if job package fails
            Log::warning("Stop job package failed, falling back to direct SSH kill", [
                'stream_id' => $this->stream->id,
                'error' => $e->getMessage()
            ]);
            
            $this->fallbackDirectStop($sshService);
        }
    }
    
    /**
     * Fallback method: Direct SSH kill (old method)
     */
    private function fallbackDirectStop(SshService $sshService): void
    {
        try {
            $vps = $this->stream->vpsServer;
            if (!$sshService->connect($vps)) {
                throw new \Exception('Failed to connect for fallback stop');
            }
            
            $streamId = $this->stream->id;
            $rtmpUrl = $this->stream->rtmp_url;
            $streamKey = $this->stream->stream_key;
            
            // Multiple kill strategies
            $sshService->execute("pkill -f 'stream_{$streamId}' 2>/dev/null");
            if ($rtmpUrl && $streamKey) {
                $sshService->execute("pkill -f '{$rtmpUrl}.*{$streamKey}' 2>/dev/null");
            }
            if ($this->stream->ffmpeg_pid) {
                $sshService->execute("kill -TERM {$this->stream->ffmpeg_pid} 2>/dev/null || kill -9 {$this->stream->ffmpeg_pid} 2>/dev/null");
            }
            
            // ✅ THÊM CLEANUP - Xóa files và directories
            Log::info("Performing cleanup for stream {$streamId} via direct SSH");
            $sshService->execute("rm -rf /tmp/stream_{$streamId} 2>/dev/null || true");
            $sshService->execute("rm -f /tmp/job_{$streamId}_*.json 2>/dev/null || true");
            $sshService->execute("rm -f /tmp/stop_job_{$streamId}_*.json 2>/dev/null || true");
            $sshService->execute("rm -f /tmp/stream_{$streamId}.stop 2>/dev/null || true");
            
            // Wait and verify
            sleep(2);
            $remaining = $sshService->execute("pgrep -f 'stream_{$streamId}\\|{$rtmpUrl}' 2>/dev/null || echo 'NONE'");
            
            if (trim($remaining) === 'NONE') {
                $this->stream->update([
                    'status' => 'STOPPED',
                    'output_log' => 'Stream stopped via direct SSH (fallback) with cleanup',
                    'last_stopped_at' => now(),
                    'ffmpeg_pid' => null,
                ]);
                Log::info("Stream {$streamId} stopped and cleaned up successfully via fallback");
            } else {
                $this->stream->update([
                    'status' => 'ERROR',
                    'output_log' => 'Failed to stop stream completely',
                    'last_stopped_at' => now(),
                ]);
            }
            
            $sshService->disconnect();
            
        } catch (\Exception $e) {
            $this->stream->update([
                'status' => 'ERROR',
                'output_log' => 'Stop error: ' . $e->getMessage(),
                'last_stopped_at' => now(),
            ]);
            
            Log::error("Stream stop error: {$e->getMessage()}", [
                'stream_id' => $this->stream->id,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
    
    /**
     * Get webhook URL with ngrok support
     */
    private function getWebhookUrl(): string
    {
        // Priority: 1. NGROK_URL env, 2. WEBHOOK_BASE_URL env, 3. APP_URL
        $baseUrl = env('NGROK_URL') ?: env('WEBHOOK_BASE_URL') ?: env('APP_URL');
        
        // Remove trailing slash and add webhook endpoint
        $baseUrl = rtrim($baseUrl, '/');
        
        return $baseUrl . '/api/stream-webhook';
    }

    /**
     * Generate webhook secret for stop job
     */
    private function generateWebhookSecret(int $streamId): string
    {
        $timestamp = time();
        $secret = hash('sha256', "stop_stream_{$streamId}_{$timestamp}_" . config('app.key'));
        
        // Cache the secret for webhook verification (2 hours)
        cache()->put("webhook_secret_{$streamId}_{$timestamp}", $secret, 7200);
        
        return $secret;
    }
}
