<?php

namespace App\Jobs;

use App\Models\StreamConfiguration;
use App\Services\Stream\StreamAllocation;
use App\Services\StreamProgressService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Predis\Client as PredisClient;

class StartMultistreamJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 60; // Giảm timeout vì job chỉ gửi lệnh qua Redis

    public StreamConfiguration $stream;

    public function __construct(StreamConfiguration $stream)
    {
        $this->stream = $stream;
        Log::info("🎬 [Stream #{$this->stream->id}] New Redis-based Start job created");
    }

    public function handle(StreamAllocation $streamAllocation): void
    {
        Log::info("🚀 [StartMultistreamJob-Redis] Job started for stream #{$this->stream->id}");

        try {
            // Đảm bảo stream chưa chạy
            $this->stream->refresh();
            if ($this->stream->status === 'STREAMING') {
                Log::warning("⚠️ [Stream #{$this->stream->id}] Stream already STREAMING, skipping job.");
                return;
            }

            // Đánh dấu stream đang bắt đầu
            $this->stream->update(['status' => 'STARTING']);

            // Clear old progress - agent.py sẽ báo cáo progress thực tế
            StreamProgressService::clearProgress($this->stream->id);
            StreamProgressService::createStageProgress($this->stream->id, 'preparing', 'Đang gửi lệnh tới VPS...');

            // 1. Tìm VPS tốt nhất để chạy stream
            $vps = $streamAllocation->findOptimalVps($this->stream);
            if (!$vps) {
                throw new \Exception("No suitable VPS found for the stream requirements.");
            }
            $this->stream->update(['vps_server_id' => $vps->id]);

            // 2. Xây dựng gói tin cấu hình cho agent.py
            Log::info("🔍 [Stream #{$this->stream->id}] Debug stream_key value", [
                'stream_key' => $this->stream->stream_key,
                'stream_key_type' => gettype($this->stream->stream_key),
                'stream_key_empty' => empty($this->stream->stream_key),
                'stream_key_null' => is_null($this->stream->stream_key),
                'stream_key_length' => strlen($this->stream->stream_key ?? ''),
            ]);

            $configPayload = [
                'id' => $this->stream->id,
                'stream_key' => $this->stream->stream_key,
                'video_files' => $this->prepareVideoFiles($this->stream),
                'rtmp_url' => $this->stream->rtmp_url,
                'push_urls' => $this->stream->push_urls ?? [], // Ensure it's always an array
                'loop' => $this->stream->loop ?? true,
                'keep_files_on_agent' => $this->stream->keep_files_on_agent ?? false,
            ];

            // 3. Tạo lệnh hoàn chỉnh để gửi qua Redis
            $redisCommand = [
                'command' => 'START_STREAM',
                'config' => $configPayload,
            ];

            // 4. Publish lệnh tới kênh Redis của VPS đã chọn với retry mechanism
            $channel = "vps-commands:{$vps->id}";
            $publishResult = $this->publishWithRetry($channel, $redisCommand);

            Log::info("✅ [Stream #{$this->stream->id}] Start command published to Redis channel '{$channel}'", [
                'vps_id' => $vps->id,
                'command' => $redisCommand,
                'publish_result' => $publishResult,
                'subscribers' => $publishResult > 0 ? 'YES' : 'NO',
                'json_payload' => json_encode($redisCommand), // Log the actual JSON being sent
            ]);

            // Check if agent received the command
            if ($publishResult > 0) {
                StreamProgressService::createStageProgress($this->stream->id, 'command_sent', 'Lệnh đã gửi tới VPS, đang chờ agent xử lý...');
            } else {
                throw new \Exception("No agent listening on VPS {$vps->id}. Agent may not be running.");
            }

        } catch (\Exception $e) {
            Log::error("❌ [Stream #{$this->stream->id}] StartMultistreamJob-Redis failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Update progress to error
            StreamProgressService::createStageProgress($this->stream->id, 'error', $e->getMessage());

            $this->stream->update([
                'status' => 'ERROR',
                'error_message' => $e->getMessage(),
            ]);

            // Rethrow exception để job được retry nếu cần
            throw $e;
        }
    }

    /**
     * Publish Redis command with retry mechanism
     */
    private function publishWithRetry(string $channel, array $command, int $maxRetries = 3): int
    {
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                // Tạo raw Redis connection với timeout settings
                $redisConfig = config('database.redis.default');

                $connectionParams = [
                    'scheme' => 'tcp',
                    'host' => $redisConfig['host'],
                    'port' => $redisConfig['port'],
                    'database' => $redisConfig['database'],
                    'timeout' => 5.0, // Connection timeout
                    'read_write_timeout' => 10.0, // Read/write timeout
                ];

                // Add password if exists
                if (!empty($redisConfig['password'])) {
                    $connectionParams['password'] = $redisConfig['password'];
                }

                // Add username if exists
                if (!empty($redisConfig['username'])) {
                    $connectionParams['username'] = $redisConfig['username'];
                }

                Log::info("🔍 [Stream #{$this->stream->id}] Redis connection params", [
                    'host' => $connectionParams['host'],
                    'port' => $connectionParams['port'],
                    'has_password' => !empty($connectionParams['password']),
                    'has_username' => !empty($connectionParams['username']),
                ]);

                $rawRedis = new PredisClient($connectionParams);

                // Test Redis connection first
                $rawRedis->ping();
                Log::info("✅ [Stream #{$this->stream->id}] Redis ping successful");

                // Encode JSON and check for errors
                $jsonPayload = json_encode($command);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \Exception("JSON encoding failed: " . json_last_error_msg());
                }

                Log::info("🔍 [Stream #{$this->stream->id}] JSON payload to publish", [
                    'payload_length' => strlen($jsonPayload),
                    'payload_preview' => substr($jsonPayload, 0, 200) . (strlen($jsonPayload) > 200 ? '...' : ''),
                ]);

                $publishResult = $rawRedis->publish($channel, $jsonPayload);

                Log::info("✅ [Stream #{$this->stream->id}] Redis publish successful on attempt {$attempt}");
                return $publishResult;

            } catch (\Exception $e) {
                $lastException = $e;
                Log::warning("⚠️ [Stream #{$this->stream->id}] Redis publish attempt {$attempt} failed: {$e->getMessage()}");

                if ($attempt < $maxRetries) {
                    // Wait before retry (exponential backoff)
                    $waitTime = pow(2, $attempt - 1); // 1s, 2s, 4s...
                    sleep($waitTime);
                }
            }
        }

        // All attempts failed
        throw new \Exception("Redis publish failed after {$maxRetries} attempts. Last error: " . $lastException->getMessage());
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("💥 [Stream #{$this->stream->id}] StartMultistreamJob-Redis failed permanently", [
            'error' => $exception->getMessage(),
        ]);

        // Update progress to error
        StreamProgressService::createStageProgress($this->stream->id, 'error', "Job failed after retries: " . $exception->getMessage());

        $this->stream->update([
            'status' => 'ERROR',
            'error_message' => "Job failed after retries: " . $exception->getMessage(),
        ]);
    }

    /**
     * Prepare video files with download URLs for agent.py
     */
    private function prepareVideoFiles(StreamConfiguration $stream): array
    {
        $videoFiles = [];
        $videoSourcePath = $stream->video_source_path ?? [];

        foreach ($videoSourcePath as $fileInfo) {
            $userFile = \App\Models\UserFile::find($fileInfo['file_id']);
            if (!$userFile) {
                Log::warning("File not found for stream #{$stream->id}", ['file_id' => $fileInfo['file_id']]);
                continue;
            }

            $downloadUrl = $this->getDownloadUrl($userFile);
            if ($downloadUrl) {
                $videoFiles[] = [
                    'file_id' => $userFile->id,
                    'filename' => $userFile->original_name,
                    'download_url' => $downloadUrl,
                    'size' => $userFile->size,
                    'disk' => $userFile->disk
                ];
            }
        }

        return $videoFiles;
    }

    /**
     * Get download URL for file
     */
    private function getDownloadUrl(\App\Models\UserFile $userFile): ?string
    {
        try {
            if ($userFile->disk === 'bunny_cdn') {
                $bunnyService = app(\App\Services\BunnyStorageService::class);
                $result = $bunnyService->getDirectDownloadLink($userFile->path);
                if ($result['success']) {
                    return $result['download_link'];
                }
            }

            // Fallback to secure download
            $downloadToken = \Illuminate\Support\Str::random(32);
            cache()->put("download_token_{$downloadToken}", $userFile->id, now()->addDays(7));

            return url("/api/secure-download/{$downloadToken}");

        } catch (\Exception $e) {
            Log::error("Failed to get download URL for stream #{$this->stream->id}", [
                'file_id' => $userFile->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}
