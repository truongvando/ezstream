<?php

namespace App\Jobs;

use App\Models\StreamConfiguration;
use App\Models\UserFile;
use App\Services\VpsAllocationService;
use App\Services\BunnyStorageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class StartMultistreamJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 120;

    public StreamConfiguration $stream;

    public function __construct(StreamConfiguration $stream)
    {
        $this->stream = $stream;
        Log::info("🎬 [Stream #{$this->stream->id}] Multistream job created");
    }

    public function handle(VpsAllocationService $vpsAllocationService, BunnyStorageService $bunnyService): void
    {
        Log::info("🚀 [Stream #{$this->stream->id}] Starting multistream job: {$this->stream->title}");

        try {
            // Update stream status
            $this->stream->update(['status' => 'STARTING']);

            // 1. Find optimal VPS with multistream capability
            $optimalVps = $vpsAllocationService->findOptimalMultistreamVps();
            if (!$optimalVps) {
                throw new \Exception('No available multistream VPS found');
            }

            // 2. Assign VPS to stream
            $this->stream->update([
                'vps_server_id' => $optimalVps->id,
                'last_started_at' => now(),
            ]);

            Log::info("✅ [Stream #{$this->stream->id}] Assigned to VPS", [
                'vps_id' => $optimalVps->id,
                'vps_ip' => $optimalVps->ip_address,
                'current_streams' => $optimalVps->current_streams,
                'max_streams' => $optimalVps->max_concurrent_streams
            ]);

            // 3. Prepare stream configuration
            $streamConfig = $this->prepareStreamConfig($bunnyService);

            // 4. Send stream start request to VPS via HTTP API
            $this->sendStreamStartRequest($optimalVps, $streamConfig);

            // 5. Update VPS current streams count
            $optimalVps->increment('current_streams');

            Log::info("🎉 [Stream #{$this->stream->id}] Multistream start request sent successfully");

        } catch (\Exception $e) {
            Log::error("❌ [Stream #{$this->stream->id}] Multistream job failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->stream->update([
                'status' => 'ERROR',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function prepareStreamConfig(BunnyStorageService $bunnyService): array
    {
        Log::info("📋 [Stream #{$this->stream->id}] Preparing stream configuration");

        // Get file list from stream
        $fileList = $this->stream->video_source_path;
        if (!is_array($fileList)) {
            throw new \Exception('Invalid file list in stream configuration');
        }

        // Prepare files with download URLs
        $files = [];
        foreach ($fileList as $fileInfo) {
            $userFile = UserFile::find($fileInfo['file_id']);
            if (!$userFile) {
                Log::warning("⚠️ [Stream #{$this->stream->id}] File not found: {$fileInfo['file_id']}");
                continue;
            }

            $downloadUrl = $this->getDownloadUrl($userFile, $bunnyService);
            if (!$downloadUrl) {
                Log::warning("⚠️ [Stream #{$this->stream->id}] Could not get download URL for file: {$userFile->original_name}");
                continue;
            }

            $files[] = [
                'file_id' => $userFile->id,
                'filename' => $userFile->original_name,
                'download_url' => $downloadUrl,
                'size' => $userFile->size,
                'disk' => $userFile->disk
            ];
        }

        if (empty($files)) {
            throw new \Exception('No valid files found for streaming');
        }

        // Build stream configuration
        $config = [
            'stream_id' => $this->stream->id,
            'title' => $this->stream->title,
            'rtmp_url' => $this->stream->rtmp_url,
            'stream_key' => $this->stream->stream_key,
            'files' => $files,
            'loop' => $this->stream->loop ?? false,
            'playlist_order' => $this->stream->playlist_order ?? 'sequential',
            'stream_preset' => $this->stream->stream_preset ?? 'direct',
            'user_id' => $this->stream->user_id,
            'created_at' => $this->stream->created_at->toISOString(),
        ];

        Log::info("✅ [Stream #{$this->stream->id}] Configuration prepared", [
            'files_count' => count($files),
            'total_size' => array_sum(array_column($files, 'size')),
            'loop' => $config['loop']
        ]);

        return $config;
    }

    private function getDownloadUrl(UserFile $userFile, BunnyStorageService $bunnyService): ?string
    {
        try {
            if ($userFile->disk === 'bunny_cdn') {
                // Use direct CDN URL (Python requests handles URL encoding properly)
                $result = $bunnyService->getDirectDownloadLink($userFile->path);
                if ($result['success']) {
                    Log::info("🔗 Using direct CDN URL for file: {$userFile->original_name}");
                    return $result['download_link'];
                }
                Log::warning("⚠️ Failed to get direct CDN URL for file: {$userFile->original_name}");
            }

            // Fallback to secure download
            $downloadToken = Str::random(32);
            cache()->put("download_token_{$downloadToken}", $userFile->id, now()->addDays(7));

            Log::info("🔗 Using secure download URL for file: {$userFile->original_name}");
            return url("/api/secure-download/{$downloadToken}");

        } catch (\Exception $e) {
            Log::error("❌ Error getting download URL for file {$userFile->id}: {$e->getMessage()}");
            return null;
        }
    }

    private function sendStreamStartRequest($vps, array $streamConfig): void
    {
        Log::info("📡 [Stream #{$this->stream->id}] Sending start request to VPS {$vps->id}");

        $apiUrl = "http://{$vps->ip_address}:9999/stream/start";
        
        try {
            $response = Http::timeout(30)
                ->connectTimeout(10)
                ->retry(3, 2000) // 3 retries with 2 second delay
                ->post($apiUrl, $streamConfig);

            if (!$response->successful()) {
                throw new \Exception("VPS API request failed: HTTP {$response->status()} - {$response->body()}");
            }

            $responseData = $response->json();
            
            if (isset($responseData['error'])) {
                throw new \Exception("VPS returned error: {$responseData['error']}");
            }

            Log::info("✅ [Stream #{$this->stream->id}] VPS accepted start request", [
                'vps_response' => $responseData
            ]);

            // Update stream with VPS response
            if (isset($responseData['status'])) {
                $this->stream->update([
                    'status' => strtoupper($responseData['status']),
                    'error_message' => null
                ]);
            }

        } catch (\Exception $e) {
            Log::error("❌ [Stream #{$this->stream->id}] Failed to send start request to VPS", [
                'vps_id' => $vps->id,
                'vps_ip' => $vps->ip_address,
                'api_url' => $apiUrl,
                'error' => $e->getMessage()
            ]);

            throw new \Exception("Failed to communicate with VPS: {$e->getMessage()}");
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("💥 [Stream #{$this->stream->id}] Multistream job failed permanently", [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);

        $this->stream->update([
            'status' => 'ERROR',
            'error_message' => $exception->getMessage(),
        ]);

        // Decrement VPS stream count if it was incremented
        if ($this->stream->vps_server_id) {
            $vps = $this->stream->vpsServer;
            if ($vps && $vps->current_streams > 0) {
                $vps->decrement('current_streams');
            }
        }
    }
}
