<?php

namespace App\Jobs;

use App\Models\StreamConfiguration;
use App\Services\SshService;
use App\Services\VpsAllocationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
     * Execute the job - Create job package and send to VPS
     */
    public function handle(SshService $sshService, VpsAllocationService $vpsAllocationService): void
    {
        Log::info("Starting stream job for: {$this->stream->title}", ['stream_id' => $this->stream->id]);

        try {
            // 1. Allocate VPS if not already assigned
            if (!$this->stream->vps_server_id) {
                $optimalVps = $vpsAllocationService->findOptimalVps();
                if (!$optimalVps) {
                    throw new \Exception('No available VPS servers found');
                }
                $this->stream->update(['vps_server_id' => $optimalVps->id]);
            }

            $vps = $this->stream->vpsServer;
            if (!$vps || $vps->status !== 'ACTIVE') {
                throw new \Exception('VPS server is not available');
            }

            // 2. Create job package JSON
            $jobPackage = $this->createJobPackage();
            
            // 3. Connect to VPS
            if (!$sshService->connect($vps)) {
                throw new \Exception('Failed to connect to VPS via SSH');
            }

            // 4. Upload job package to VPS Job Queue (TỐI ƯU HÓA)
            $jobFileName = "job_{$this->stream->id}_" . time() . "_" . uniqid() . ".json";
            $jobFilePath = "/opt/job-queue/incoming/{$jobFileName}";
            
            if (!$this->uploadJobPackage($sshService, $jobPackage, $jobFilePath)) {
                throw new \Exception('Failed to upload job package to VPS job queue');
            }

            // 5. Job Queue Daemon sẽ tự động xử lý job - KHÔNG cần execute trực tiếp
            Log::info("Job package queued for processing by daemon", [
                'stream_id' => $this->stream->id,
                'vps_id' => $vps->id,
                'job_file' => $jobFilePath
            ]);
            
            $sshService->disconnect();

            // 6. Update stream status
            $this->stream->update([
                'status' => 'STREAMING',
                'last_started_at' => now(),
                'output_log_path' => "/tmp/stream_{$this->stream->id}/stream.log",
            ]);

        } catch (\Exception $e) {
            $this->stream->update([
                'status' => 'ERROR',
                'output_log' => 'Error: ' . $e->getMessage()
            ]);
            
            Log::error("Stream start error: {$e->getMessage()}", [
                'stream_id' => $this->stream->id,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Create job package JSON for VPS
     */
    protected function createJobPackage(): array
    {
        // Parse file list from video_source_path
        if (is_array($this->stream->video_source_path)) {
            $fileList = $this->stream->video_source_path;
        } elseif (is_string($this->stream->video_source_path)) {
            $fileList = json_decode($this->stream->video_source_path, true);
        } else {
            $fileList = null;
        }
        
        if (!is_array($fileList)) {
            throw new \Exception('Invalid file list in stream configuration');
        }

        // ✅ SIMPLIFIED: Chỉ dùng download mode ổn định
        Log::info("Using stable download streaming mode", ['stream_id' => $this->stream->id]);

        // ✅ BEST SOLUTION: Use direct CDN URLs (no expiration)
        $filesToDownload = [];
        foreach ($fileList as $file) {
            $userFile = UserFile::find($file['file_id']);

            if ($userFile->disk === 'bunny_cdn') {
                // Direct CDN URL - never expires
                $bunnyService = app(\App\Services\BunnyStorageService::class);
                $result = $bunnyService->getDirectDownloadLink($userFile->path);
                $downloadUrl = $result['success'] ? $result['download_link'] : null;
            } else {
                // Fallback to secure download with extended expiry for other storage types
                $downloadToken = Str::random(32);
                cache()->put("download_token_{$downloadToken}", $file['file_id'], now()->addDays(7));
                $downloadUrl = url("/api/secure-download/{$downloadToken}");
            }

            if ($downloadUrl) {
                $filesToDownload[] = [
                    'file_id' => $file['file_id'],
                    'filename' => $file['filename'],
                    'download_url' => $downloadUrl
                ];
            }
        }

        // Create job package for VPS agent
        $jobPackage = [
            'stream_id' => $this->stream->id,
            'rtmp_url' => $this->stream->rtmp_url,
            'rtmp_backup_url' => $this->stream->rtmp_backup_url,
            'stream_key' => $this->stream->stream_key,
            'loop' => $this->stream->loop ?? false,
            'playlist_order' => $this->stream->playlist_order ?? 'sequential',
            'webhook_url' => $this->getWebhookUrl(),
            'webhook_secret' => $this->generateWebhookSecret(),
            'files_to_download' => $filesToDownload,
        ];

        Log::info("Job package created for download streaming", [
            'stream_id' => $this->stream->id, 
            'files_count' => count($filesToDownload)
        ]);

        return $jobPackage;
    }

    /**
     * Upload job package to VPS
     */
    protected function uploadJobPackage(SshService $sshService, array $jobPackage, string $remotePath): bool
    {
        $jsonContent = json_encode($jobPackage, JSON_PRETTY_PRINT);
        $tempFile = tempnam(sys_get_temp_dir(), 'job_package_');
        
        try {
            file_put_contents($tempFile, $jsonContent);
            $success = $sshService->uploadFile($tempFile, $remotePath);
            unlink($tempFile);
            
            if ($success) {
                Log::info("Job package uploaded to VPS", [
                    'stream_id' => $this->stream->id,
                    'remote_path' => $remotePath,
                    'package_size' => strlen($jsonContent)
                ]);
            }
            
            return $success;
        } catch (\Exception $e) {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
            Log::error("Failed to upload job package", [
                'stream_id' => $this->stream->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    protected function getWebhookUrl()
    {
        // Priority: 1. NGROK_URL env, 2. WEBHOOK_BASE_URL env, 3. APP_URL
        $baseUrl = env('NGROK_URL') ?: env('WEBHOOK_BASE_URL') ?: env('APP_URL');
        
        // Remove trailing slash and add webhook endpoint
        $baseUrl = rtrim($baseUrl, '/');
        
        return $baseUrl . '/api/stream-webhook';
    }

    protected function generateWebhookSecret()
    {
        // Generate webhook secret with timestamp to avoid collisions
        $timestamp = time();
        $secret = hash('sha256', "webhook_secret_{$this->stream->id}_{$timestamp}_" . config('app.key'));
        
        // Cache the secret for webhook verification (24 hours)
        cache()->put("webhook_secret_{$this->stream->id}_{$timestamp}", $secret, now()->addDay());
        
        return $secret;
    }
}
