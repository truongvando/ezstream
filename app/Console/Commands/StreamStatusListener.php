<?php

namespace App\Console\Commands;

use App\Models\StreamConfiguration;
use App\Models\VpsServer;
use App\Services\StreamProgressService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Predis\Client as PredisClient;

class StreamStatusListener extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stream:listen 
                            {--timeout=0 : Timeout in seconds (0 = no timeout)}
                            {--reconnect=true : Auto-reconnect on failure}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Listen for stream status updates from VPS via Redis';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $timeout = (int) $this->option('timeout');
        $autoReconnect = $this->option('reconnect');
        
        $this->info("🎧 Starting Stream Status Listener...");
        $this->info("Timeout: " . ($timeout > 0 ? "{$timeout}s" : "No timeout"));
        $this->info("Auto-reconnect: " . ($autoReconnect ? "Yes" : "No"));
        
        $retryCount = 0;
        $maxRetries = 5;
        
        while ($retryCount < $maxRetries) {
            try {
                $this->listenToRedis($timeout);
                
                if (!$autoReconnect) {
                    break;
                }
                
                $retryCount++;
                $this->warn("⚠️ Connection lost. Retrying in 5 seconds... (Attempt {$retryCount}/{$maxRetries})");
                sleep(5);
                
            } catch (\Exception $e) {
                $this->error("❌ Redis listener error: {$e->getMessage()}");
                
                if (!$autoReconnect) {
                    return 1;
                }
                
                $retryCount++;
                if ($retryCount >= $maxRetries) {
                    $this->error("💥 Max retries reached. Exiting.");
                    return 1;
                }
                
                $waitTime = min(60, pow(2, $retryCount)); // Exponential backoff
                $this->warn("⚠️ Retrying in {$waitTime} seconds... (Attempt {$retryCount}/{$maxRetries})");
                sleep($waitTime);
            }
        }
        
        return 0;
    }
    
    /**
     * Listen to Redis for stream status updates
     */
    private function listenToRedis(int $timeout = 0): void
    {
        $redisConfig = config('database.redis.default');
        $rawRedis = new PredisClient([
            'scheme' => 'tcp',
            'host' => $redisConfig['host'],
            'port' => $redisConfig['port'],
            'password' => $redisConfig['password'],
            'database' => $redisConfig['database'],
            'timeout' => 10.0,
            'read_write_timeout' => 0,
            'persistent' => false,
        ]);
        
        $this->info("✅ Connected to Redis: {$redisConfig['host']}:{$redisConfig['port']}");
        
        $channels = ['stream-status']; // Only one channel needed now
        $this->info("📡 Subscribing to channels: " . implode(', ', $channels));

        $pubsub = $rawRedis->pubSubLoop();
        $pubsub->subscribe('stream-status');
        
        $startTime = time();
        
        foreach ($pubsub as $message) {
            if ($message->kind === 'message') {
                $this->processMessage($message->payload);
            }
            
            if ($timeout > 0 && (time() - $startTime) > $timeout) {
                $this->info("⏰ Timeout reached after {$timeout}s.");
                break;
            }
        }
        
        $pubsub->unsubscribe();
        $this->info("🔌 Disconnected from Redis");
    }
    
    /**
     * Process received message directly
     */
    private function processMessage(string $payload): void
    {
        $this->line("📨 [stream-status] {$payload}");
        
        try {
            $data = json_decode($payload, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->warn("⚠️ Invalid JSON received: {$payload}");
                return;
            }
            
            // Re-route to the correct handler based on message type
            $type = $data['type'] ?? 'status_update';

            switch ($type) {
                case 'progress':
                    $this->handleProgressUpdate($data['stream_id'], $data);
                    break;
                case 'batch_progress':
                    $this->handleBatchProgressUpdate($data);
                    break;
                case 'stream_heartbeat':
                    $this->handleStreamHeartbeat($data);
                    break;
                default:
                    $this->handleStatusUpdate($data);
                    break;
            }

        } catch (\Exception $e) {
            $this->error("❌ Error processing message: {$e->getMessage()}");
            Log::error("Stream listener message processing error", [
                'payload' => $payload,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function handleStatusUpdate(array $data): void
    {
        $streamId = $data['stream_id'] ?? null;
        if (!$streamId) return;

        $stream = StreamConfiguration::find($streamId);
        if (!$stream) {
            $this->warn("[StatusUpdate] Stream #{$streamId} not found in database.");
            return;
        }

        $status = strtoupper($data['status'] ?? 'UNKNOWN');
        $vpsId = $data['vps_id'] ?? $stream->vps_server_id;
        $message = $data['message'] ?? 'No message';

        $this->info("✅ [StatusUpdate] Updating status for Stream #{$streamId} to {$status}");

        switch ($status) {
            case 'RUNNING':
            case 'STREAMING':
                if ($stream->status !== 'STREAMING') {
                    $stream->update(['status' => 'STREAMING', 'last_started_at' => now(), 'error_message' => null, 'vps_server_id' => $vpsId]);
                    if ($vpsId) {
                        VpsServer::find($vpsId)?->increment('current_streams');
                    }
                    StreamProgressService::createStageProgress($streamId, 'streaming', 'Stream đang phát trực tiếp!');
                }
                break;

            case 'ERROR':
                $originalVpsId = $stream->vps_server_id;
                $stream->update(['status' => 'ERROR', 'error_message' => $message]);
                if ($originalVpsId) {
                    VpsServer::find($originalVpsId)?->decrement('current_streams');
                }
                StreamProgressService::createStageProgress($streamId, 'error', $message);
                break;
            
            case 'COMPLETED':
            case 'STOPPED':
                $originalVpsId = $stream->vps_server_id;
                $stream->update(['status' => 'INACTIVE', 'last_stopped_at' => now(), 'vps_server_id' => null, 'error_message' => null]);
                $vpsToDecrement = $vpsId ?? $originalVpsId;
                if ($vpsToDecrement) {
                    VpsServer::find($vpsToDecrement)?->decrement('current_streams');
                }
                break;
            
            case 'STARTED':
                $this->info("[StatusUpdate] Stream #{$streamId} process has started on VPS #{$vpsId}");
                if (!$stream->vps_server_id && $vpsId) {
                    $stream->update(['vps_server_id' => $vpsId]);
                }
                StreamProgressService::createStageProgress($streamId, 'ffmpeg_started', 'FFmpeg đã khởi động, đang kết nối...');
                break;
        }
    }

    private function handleProgressUpdate(int $streamId, array $data): void
    {
        $stage = $data['stage'] ?? 'unknown';
        $progressPercentage = $data['progress_percentage'] ?? 0;
        $message = $data['message'] ?? 'Đang xử lý...';
        $details = $data['details'] ?? null;

        if ($stage === 'downloading' && is_string($message)) {
            if (preg_match('/Đang tải (.+?): (\d+)% \((\d+)MB\/(\d+)MB\)/', $message, $matches)) {
                $details = array_merge($details ?? [], [
                    'file_name' => $matches[1], 'download_percentage' => (int)$matches[2],
                    'downloaded_mb' => (int)$matches[3], 'total_mb' => (int)$matches[4]
                ]);
            }
        }
        
        $this->info("📊 [Progress] Stream #{$streamId}: {$stage} ({$progressPercentage}%)");

        StreamProgressService::setProgress($streamId, $stage, $progressPercentage, $message, $details);
        
        // FIX: Update database status when stream is confirmed to be running
        if ($stage === 'streaming') {
            $stream = StreamConfiguration::find($streamId);
            if ($stream && $stream->status !== 'STREAMING') {
                $this->info("✅ [ProgressHandler] Stream #{$streamId} is live. Updating DB status to STREAMING.");
                $stream->update(['status' => 'STREAMING']);
            }
        }
    }

    private function handleBatchProgressUpdate(array $data): void
    {
        $updates = $data['updates'] ?? [];
        $this->info("📊 [BatchProgress] Processing " . count($updates) . " updates.");
        foreach ($updates as $update) {
            if (isset($update['stream_id'])) {
                $this->handleProgressUpdate($update['stream_id'], $update);
            }
        }
    }

    private function handleStreamHeartbeat(array $data): void
    {
        $vpsId = $data['vps_id'] ?? null;
        $activeStreams = $data['active_streams'] ?? [];

        $this->info("💓 [Heartbeat] Received from VPS #{$vpsId} with " . count($activeStreams) . " active streams.");

        // Get all active stream IDs from heartbeat
        $heartbeatStreamIds = collect($activeStreams)->pluck('stream_id')->filter()->toArray();

        if (!empty($heartbeatStreamIds)) {
            $this->info("🔄 [Heartbeat] Syncing status for streams: " . implode(', ', $heartbeatStreamIds));

            // 1. Update streams that are confirmed STREAMING by heartbeat
            foreach ($activeStreams as $streamInfo) {
                if (isset($streamInfo['stream_id'])) {
                    $streamId = $streamInfo['stream_id'];
                    $stream = StreamConfiguration::find($streamId);

                    if ($stream) {
                        $oldStatus = $stream->status;

                        // Sync status based on heartbeat - this is the source of truth
                        if (in_array($oldStatus, ['STARTING', 'INACTIVE', 'ERROR'])) {
                            $this->info("✅ [Heartbeat] Syncing stream #{$streamId}: {$oldStatus} → STREAMING");
                            $stream->update([
                                'status' => 'STREAMING',
                                'last_status_update' => now(),
                                'vps_server_id' => $vpsId,
                                'error_message' => null,
                                'last_started_at' => $stream->last_started_at ?: now()
                            ]);

                            // Create progress update for UI
                            StreamProgressService::createStageProgress($streamId, 'streaming', 'Stream đang phát trực tiếp! (Synced by heartbeat)');
                        } else {
                            // Just update timestamp for already STREAMING streams
                            $stream->update(['last_status_update' => now()]);
                        }
                    }
                }
            }
        }

        // 2. Detect streams that should be STREAMING but are not in heartbeat
        $dbStreamingStreams = StreamConfiguration::where('vps_server_id', $vpsId)
            ->whereIn('status', ['STREAMING', 'STARTING'])
            ->pluck('id')
            ->toArray();

        $missingStreams = array_diff($dbStreamingStreams, $heartbeatStreamIds);

        foreach ($missingStreams as $streamId) {
            $stream = StreamConfiguration::find($streamId);
            if ($stream) {
                $timeSinceUpdate = $stream->last_status_update ? now()->diffInMinutes($stream->last_status_update) : 999;

                // Only mark as stale if it's been more than 3 minutes without heartbeat
                if ($timeSinceUpdate > 3) {
                    $this->warn("⚠️ [Heartbeat] Stream #{$streamId} missing from heartbeat for {$timeSinceUpdate} minutes, marking as ERROR");
                    $stream->update([
                        'status' => 'ERROR',
                        'error_message' => "Stream missing from VPS heartbeat for {$timeSinceUpdate} minutes",
                        'vps_server_id' => null
                    ]);

                    if ($stream->vpsServer) {
                        $stream->vpsServer->decrement('current_streams');
                    }
                }
            }
        }
    }
}
