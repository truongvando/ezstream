<?php

namespace App\Jobs;

use App\Models\StreamConfiguration;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Predis\Client as PredisClient;

class CleanupStreamFilesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private StreamConfiguration $stream;

    /**
     * Create a new job instance.
     */
    public function __construct(StreamConfiguration $stream)
    {
        $this->stream = $stream;
    }

    /**
     * Execute the job - Send cleanup command to VPS to remove downloaded files
     */
    public function handle(): void
    {
        Log::info("🗑️ [CleanupStreamFilesJob] Starting cleanup for stream #{$this->stream->id}");

        try {
            // Check if stream has VPS assigned
            if (!$this->stream->vps_server_id) {
                Log::info("⚠️ [Stream #{$this->stream->id}] No VPS assigned, skipping file cleanup");
                return;
            }

            // Create cleanup command
            $redisCommand = [
                'command' => 'CLEANUP_FILES',
                'stream_id' => $this->stream->id,
                'force' => true, // Always cleanup when deleting stream
            ];

            // Send command to VPS via Redis
            $channel = "vps-commands:{$this->stream->vps_server_id}";

            $redisConfig = config('database.redis.default');
            $rawRedis = new PredisClient([
                'scheme' => 'tcp',
                'host' => $redisConfig['host'],
                'port' => $redisConfig['port'],
                'password' => $redisConfig['password'],
                'database' => $redisConfig['database'],
            ]);

            $publishResult = $rawRedis->publish($channel, json_encode($redisCommand));

            if ($publishResult > 0) {
                Log::info("✅ [Stream #{$this->stream->id}] Cleanup command sent to VPS #{$this->stream->vps_server_id}", [
                    'channel' => $channel,
                    'publish_result' => $publishResult
                ]);
            } else {
                Log::warning("⚠️ [Stream #{$this->stream->id}] No agent listening on Redis channel: {$channel}");
            }

        } catch (\Exception $e) {
            Log::error("❌ [CleanupStreamFilesJob] Failed for stream #{$this->stream->id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Don't throw exception - cleanup failure shouldn't prevent stream deletion
        }
    }
}
