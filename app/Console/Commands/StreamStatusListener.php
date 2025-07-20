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

        // Log to file for debugging
        \Log::info("💓 [Heartbeat] Processing heartbeat", [
            'vps_id' => $vpsId,
            'active_streams' => $activeStreams,
            'timestamp' => now()->toDateTimeString()
        ]);

        // Get all active stream IDs from heartbeat
        $heartbeatStreamIds = collect($activeStreams)->pluck('stream_id')->filter()->toArray();

        if (!empty($heartbeatStreamIds)) {
            $this->info("🔄 [Heartbeat] VPS #{$vpsId} reports active streams: " . implode(', ', $heartbeatStreamIds));

            // CRITICAL: Heartbeat is the source of truth - sync ALL streams reported as active

            // 1. Update streams that are confirmed STREAMING by heartbeat
            foreach ($activeStreams as $streamInfo) {
                if (isset($streamInfo['stream_id'])) {
                    $streamId = $streamInfo['stream_id'];
                    $heartbeatStatus = $streamInfo['status'] ?? 'STREAMING';
                    $heartbeatPid = $streamInfo['pid'] ?? null;

                    $this->info("🔍 [Heartbeat] Processing stream #{$streamId} with heartbeat status: {$heartbeatStatus}, PID: {$heartbeatPid}");

                    $stream = StreamConfiguration::find($streamId);

                    if ($stream) {
                        $oldStatus = $stream->status;
                        $oldVpsId = $stream->vps_server_id;
                        $lastUpdate = $stream->last_status_update ? $stream->last_status_update->format('Y-m-d H:i:s') : 'Never';

                        $this->info("📊 [Heartbeat] Stream #{$streamId} DETAILED INFO:");
                        $this->info("  - DB Status: {$oldStatus}");
                        $this->info("  - DB VPS ID: {$oldVpsId}");
                        $this->info("  - Heartbeat Status: {$heartbeatStatus}");
                        $this->info("  - Heartbeat VPS: {$vpsId}");
                        $this->info("  - Last Update: {$lastUpdate}");
                        $this->info("  - User ID: {$stream->user_id}");
                        $this->info("  - Title: {$stream->title}");

                        // 🚨 CRITICAL: Heartbeat is the SOURCE OF TRUTH
                        // If VPS reports stream as STREAMING, database MUST be synced regardless of current status
                        if ($heartbeatStatus === 'STREAMING') {

                            // 🚨 CRITICAL: Check if stream was force stopped by admin
                            if ($stream->error_message && str_contains($stream->error_message, 'Force stopped by admin')) {
                                $this->warn("🚫 [Heartbeat] Stream #{$streamId} was force stopped by admin, ignoring heartbeat");

                                // Send STOP command to agent to ensure it stops
                                try {
                                    $redis = app('redis')->connection();
                                    $stopCommand = [
                                        'command' => 'STOP_STREAM',
                                        'stream_id' => $streamId,
                                    ];
                                    $channel = "vps-commands:{$vpsId}";
                                    $redis->publish($channel, json_encode($stopCommand));
                                    $this->info("📤 [Heartbeat] Sent STOP command to agent for force-stopped stream #{$streamId}");
                                } catch (\Exception $e) {
                                    $this->error("❌ [Heartbeat] Failed to send stop command: {$e->getMessage()}");
                                }
                                continue;
                            }

                            if ($oldStatus !== 'STREAMING') {
                                // Stream is running on VPS but DB shows different status - FORCE SYNC
                                $this->info("🔥 [Heartbeat] FORCE SYNC NEEDED: Stream #{$streamId} running on VPS but DB shows {$oldStatus} → STREAMING");

                                $updateData = [
                                    'status' => 'STREAMING',
                                    'last_status_update' => now(),
                                    'vps_server_id' => $vpsId,
                                    'error_message' => null, // Only clear if not force stopped
                                    'last_started_at' => $stream->last_started_at ?: now()
                                ];

                                if ($heartbeatPid) {
                                    $updateData['process_id'] = $heartbeatPid;
                                }

                                $this->info("💾 [Heartbeat] Updating database with data: " . json_encode($updateData));

                                $result = $stream->update($updateData);

                                if ($result) {
                                    $this->info("✅ [Heartbeat] Database update successful");

                                    // Verify the update
                                    $stream->refresh();
                                    $this->info("🔍 [Heartbeat] Verification - New status: {$stream->status}, VPS: {$stream->vps_server_id}");

                                    // Create progress update for UI
                                    try {
                                        StreamProgressService::createStageProgress($streamId, 'streaming', "🔄 Stream đã được đồng bộ! VPS báo cáo đang phát trực tiếp (từ {$oldStatus})");
                                        $this->info("📊 [Heartbeat] Progress update created");
                                    } catch (\Exception $e) {
                                        $this->error("❌ [Heartbeat] Failed to create progress: {$e->getMessage()}");
                                    }

                                    // Trigger immediate UI refresh
                                    $this->triggerUIRefresh($streamId);

                                    $this->info("🎯 [Heartbeat] FORCE SYNC COMPLETED: Stream #{$streamId}: {$oldStatus} → STREAMING");

                                } else {
                                    $this->error("❌ [Heartbeat] Database update FAILED for stream #{$streamId}");
                                }

                            } else {
                                // Stream already STREAMING in DB - just update heartbeat timestamp
                                $updateResult = $stream->update([
                                    'last_status_update' => now(),
                                    'vps_server_id' => $vpsId,
                                    'process_id' => $heartbeatPid
                                ]);

                                if ($updateResult) {
                                    $this->info("🔄 [Heartbeat] Heartbeat timestamp updated for stream #{$streamId} (confirmed STREAMING)");
                                } else {
                                    $this->error("❌ [Heartbeat] Failed to update heartbeat timestamp for stream #{$streamId}");
                                }
                            }

                        } else {
                            $this->warn("⚠️ [Heartbeat] Unexpected heartbeat status for stream #{$streamId}: {$heartbeatStatus}");
                        }
                    } else {
                        $this->warn("⚠️ [Heartbeat] Stream #{$streamId} not found in database but reported by VPS #{$vpsId}");

                        // CRITICAL: Stream exists on VPS but not in DB - this is a serious issue
                        // This could happen if:
                        // 1. Stream was deleted from DB but still running on VPS
                        // 2. Database corruption/rollback
                        // 3. Manual deletion without stopping VPS stream

                        $this->error("🚨 [CRITICAL] Orphaned stream detected: #{$streamId} running on VPS #{$vpsId} but missing from database");

                        // Option 1: Try to stop the orphaned stream on VPS
                        try {
                            $this->info("🛑 [Recovery] Attempting to stop orphaned stream #{$streamId} on VPS #{$vpsId}");

                            // Send stop command to VPS (use same format as StopMultistreamJob)
                            $stopCommand = [
                                'command' => 'STOP_STREAM',
                                'stream_id' => $streamId,
                            ];

                            $redis = app('redis')->connection();
                            $channel = "vps-commands:{$vpsId}";
                            $result = $redis->publish($channel, json_encode($stopCommand));

                            if ($result > 0) {
                                $this->info("✅ [Recovery] Stop command sent for orphaned stream #{$streamId} to channel {$channel}");
                                \Log::info("🛑 [OrphanedStreamRecovery] Stop command sent", [
                                    'stream_id' => $streamId,
                                    'vps_id' => $vpsId,
                                    'channel' => $channel,
                                    'command' => $stopCommand,
                                    'subscribers' => $result
                                ]);
                            } else {
                                $this->warn("⚠️ [Recovery] No agent listening for orphaned stream #{$streamId} on channel {$channel}");
                                $this->warn("💡 [Recovery] Agent may be offline. Stream will continue running until agent reconnects.");
                                \Log::warning("⚠️ [OrphanedStreamRecovery] No subscribers - agent offline", [
                                    'stream_id' => $streamId,
                                    'vps_id' => $vpsId,
                                    'channel' => $channel,
                                    'note' => 'Stream will continue as zombie until agent reconnects'
                                ]);
                            }

                        } catch (\Exception $e) {
                            $this->error("❌ [Recovery] Exception stopping orphaned stream #{$streamId}: {$e->getMessage()}");
                        }
                    }
                } else {
                    $this->warn("⚠️ [Heartbeat] Invalid stream info in heartbeat: " . json_encode($streamInfo));
                }
            }
        }

        // 2. CRITICAL: Handle streams that claim to be on this VPS but are NOT in heartbeat
        $dbStreamsOnThisVps = StreamConfiguration::where('vps_server_id', $vpsId)
            ->whereIn('status', ['STREAMING', 'STARTING'])
            ->get();

        $missingStreamIds = $dbStreamsOnThisVps->pluck('id')->diff($heartbeatStreamIds);

        if ($missingStreamIds->isNotEmpty()) {
            $this->warn("🚨 [Heartbeat] VPS #{$vpsId} missing streams from heartbeat: " . $missingStreamIds->implode(', '));
        }

        foreach ($missingStreamIds as $streamId) {
            $stream = $dbStreamsOnThisVps->where('id', $streamId)->first();
            if ($stream) {
                // For new streams, use created_at instead of 999
                $timeSinceUpdate = $stream->last_status_update ?
                    now()->diffInMinutes($stream->last_status_update) :
                    now()->diffInMinutes($stream->created_at);
                $timeSinceStart = $stream->last_started_at ? now()->diffInMinutes($stream->last_started_at) : 999;

                $this->warn("🔍 [Heartbeat] Stream #{$streamId} analysis:");
                $this->warn("  - DB Status: {$stream->status}");
                $this->warn("  - Minutes since heartbeat: {$timeSinceUpdate}");
                $this->warn("  - Minutes since start: {$timeSinceStart}");

                // More aggressive cleanup - if not in heartbeat, it's probably dead
                if ($timeSinceUpdate > 2 || ($stream->status === 'STARTING' && $timeSinceStart > 5)) {
                    $reason = $timeSinceUpdate > 2 ?
                        "missing from VPS heartbeat for {$timeSinceUpdate} minutes" :
                        "stuck in STARTING for {$timeSinceStart} minutes";

                    $this->warn("💀 [Heartbeat] Stream #{$streamId} {$reason}, marking as ERROR");

                    $stream->update([
                        'status' => 'ERROR',
                        'error_message' => "Stream {$reason} (VPS #{$vpsId} heartbeat)",
                        'vps_server_id' => null,
                        'process_id' => null
                    ]);

                    // Create progress update for UI
                    StreamProgressService::createStageProgress($streamId, 'error', "❌ Stream bị mất: {$reason}");

                    // Trigger UI refresh
                    $this->triggerUIRefresh($streamId);

                    if ($stream->vpsServer) {
                        $stream->vpsServer->decrement('current_streams');
                    }
                }
            }
        }

        // 3. RECOVERY: Check for "orphaned" streams that might have been missed
        $this->recoverOrphanedStreams($vpsId, $heartbeatStreamIds);
    }

    /**
     * Recover orphaned streams that might be running but not assigned to correct VPS
     */
    private function recoverOrphanedStreams(int $vpsId, array $heartbeatStreamIds): void
    {
        if (empty($heartbeatStreamIds)) {
            return;
        }

        // Find streams that are reported by this VPS but assigned to different VPS or no VPS
        $orphanedStreams = StreamConfiguration::whereIn('id', $heartbeatStreamIds)
            ->where(function($query) use ($vpsId) {
                $query->where('vps_server_id', '!=', $vpsId)
                      ->orWhereNull('vps_server_id');
            })
            ->get();

        foreach ($orphanedStreams as $stream) {
            $oldVpsId = $stream->vps_server_id;
            $this->warn("🔄 [Recovery] Stream #{$stream->id} running on VPS #{$vpsId} but DB shows VPS #{$oldVpsId}");

            // Update to correct VPS
            $stream->update([
                'vps_server_id' => $vpsId,
                'status' => 'STREAMING',
                'last_status_update' => now(),
                'error_message' => null
            ]);

            // Create progress update
            StreamProgressService::createStageProgress(
                $stream->id,
                'streaming',
                "🔄 Stream đã được khôi phục! Đang chạy trên VPS #{$vpsId}"
            );

            $this->info("✅ [Recovery] Recovered stream #{$stream->id} to VPS #{$vpsId}");
            $this->triggerUIRefresh($stream->id);
        }
    }

    /**
     * Trigger immediate UI refresh for specific stream
     */
    private function triggerUIRefresh(int $streamId): void
    {
        try {
            // Publish to Redis channel that Livewire can listen to
            $redis = app('redis')->connection();
            $redis->publish('stream-ui-refresh', json_encode([
                'stream_id' => $streamId,
                'action' => 'status_synced',
                'timestamp' => time()
            ]));

            $this->info("🔄 [UIRefresh] Triggered UI refresh for stream #{$streamId}");

        } catch (\Exception $e) {
            $this->warn("⚠️ [UIRefresh] Failed to trigger UI refresh: {$e->getMessage()}");
        }
    }
}
