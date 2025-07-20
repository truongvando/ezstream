<?php

namespace App\Jobs;

use App\Models\StreamConfiguration;
use App\Services\Stream\StreamManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Predis\Client as PredisClient;

class StopMultistreamJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 60;

    public StreamConfiguration $stream;

    public function __construct(StreamConfiguration $stream)
    {
        $this->stream = $stream;
        Log::info("🛑 [Stream #{$this->stream->id}] New Redis-based Stop job created");
    }

    public function handle(): void
    {
        Log::info("🛑 [StopMultistreamJob-Redis] Job started for stream #{$this->stream->id}");

        try {
            $vpsId = $this->stream->vps_server_id;

            // Nếu không có VPS ID, không thể gửi lệnh -> đánh dấu là đã dừng
            if (!$vpsId) {
                Log::warning("⚠️ [Stream #{$this->stream->id}] No VPS ID assigned. Marking as INACTIVE directly.");
                $this->stream->update([
                    'status' => 'INACTIVE',
                    'last_stopped_at' => now(),
                    'vps_server_id' => null,
                ]);
                return;
            }

            // Tạo lệnh STOP
            $redisCommand = [
                'command' => 'STOP_STREAM',
                'stream_id' => $this->stream->id,
                'timestamp' => time(),
                'laravel_request_id' => uniqid('stop_', true) // Unique ID để track request
            ];

            // Gửi lệnh qua Redis với retry mechanism
            $channel = "vps-commands:{$vpsId}";
            $publishResult = $this->publishWithRetry($channel, $redisCommand);

            Log::info("✅ [Stream #{$this->stream->id}] Stop command published to Redis channel '{$channel}'", [
                'command' => $redisCommand,
                'channel' => $channel,
                'publish_result' => $publishResult,
                'subscribers' => $publishResult > 0 ? 'YES' : 'NO',
                'vps_id' => $vpsId,
                'current_status' => $this->stream->status
            ]);

            // Log detailed debugging info
            Log::debug("🔍 [StopMultistreamJob] Debug info for stream #{$this->stream->id}", [
                'stream_data' => [
                    'id' => $this->stream->id,
                    'title' => $this->stream->title,
                    'status' => $this->stream->status,
                    'vps_server_id' => $this->stream->vps_server_id,
                    'process_id' => $this->stream->process_id,
                    'last_status_update' => $this->stream->last_status_update,
                    'last_started_at' => $this->stream->last_started_at,
                ],
                'redis_command' => $redisCommand,
                'redis_channel' => $channel,
                'redis_result' => $publishResult
            ]);

            // Keep status as STOPPING and preserve vps_server_id
            // Let agent confirm stop via heartbeat or timeout mechanism handle it
            $this->stream->update([
                'status' => 'STOPPING',  // Keep STOPPING until agent confirms
                'last_stopped_at' => now(),
                // Keep vps_server_id so we can send kill commands if needed
                'error_message' => null,
            ]);

            // Trigger auto-deletion for quick streams
            if ($this->stream->is_quick_stream && $this->stream->auto_delete_from_cdn) {
                Log::info("🗑️ [Stream #{$this->stream->id}] Dispatching auto-deletion job for quick stream");
                \App\Jobs\AutoDeleteStreamFilesJob::dispatch($this->stream)->delay(now()->addMinutes(2));
            }

        } catch (\Exception $e) {
            Log::error("❌ [Stream #{$this->stream->id}] StopMultistreamJob-Redis failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Quan trọng: Luôn cập nhật trạng thái để tránh bị treo ở STOPPING
            // Nếu không gửi được lệnh stop, vẫn đánh dấu là INACTIVE vì stream có thể đã dừng
            $this->stream->update([
                'status' => 'INACTIVE', // Thay vì ERROR để tránh treo
                'error_message' => "Stop command failed but marked as stopped: " . $e->getMessage(),
                'last_stopped_at' => now(),
                'vps_server_id' => null, // Xóa vps_id để giải phóng
            ]);

            // Không throw exception để tránh job retry vô tận
            Log::warning("⚠️ [Stream #{$this->stream->id}] Stop job completed with errors but stream marked as INACTIVE");
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
                $rawRedis = new PredisClient([
                    'scheme' => 'tcp',
                    'host' => $redisConfig['host'],
                    'port' => $redisConfig['port'],
                    'password' => $redisConfig['password'],
                    'database' => $redisConfig['database'],
                    'timeout' => 5.0, // Connection timeout
                    'read_write_timeout' => 10.0, // Read/write timeout
                ]);

                $publishResult = $rawRedis->publish($channel, json_encode($command));

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
        Log::error("💥 [Stream #{$this->stream->id}] StopMultistreamJob-Redis failed permanently", [
            'error' => $exception->getMessage(),
        ]);

        // Đảm bảo stream không bị treo ở trạng thái STOPPING
        $this->stream->update([
            'status' => 'INACTIVE', // Thay vì ERROR để tránh treo
            'error_message' => "Stop job failed after retries: " . $exception->getMessage(),
            'last_stopped_at' => now(),
            'vps_server_id' => null, // Xóa vps_id để giải phóng
        ]);
    }
}
