<?php

namespace App\Services;

use App\Models\VpsServer;
use App\Models\StreamConfiguration;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

class NetworkPartitionHandler
{
    private const PARTITION_THRESHOLD = 300; // 5 minutes
    private const RECOVERY_GRACE_PERIOD = 120; // 2 minutes

    /**
     * Detect and handle network partitions
     */
    public static function handlePartition(int $vpsId): void
    {
        $vps = VpsServer::find($vpsId);
        if (!$vps) return;

        $lastHeartbeat = $vps->last_heartbeat_at;
        if (!$lastHeartbeat) return;

        $minutesSinceHeartbeat = $lastHeartbeat->diffInMinutes(now());

        if ($minutesSinceHeartbeat > self::PARTITION_THRESHOLD / 60) {
            Log::warning("🔌 [NetworkPartition] VPS #{$vpsId} appears to be partitioned ({$minutesSinceHeartbeat}m since last heartbeat)");
            
            self::markVpsAsPartitioned($vps);
            self::handlePartitionedStreams($vps);
        }
    }

    /**
     * Handle VPS recovery from partition
     */
    public static function handleRecovery(int $vpsId, array $activeStreams): void
    {
        $vps = VpsServer::find($vpsId);
        if (!$vps || $vps->status !== 'PARTITIONED') return;

        Log::info("🔄 [NetworkPartition] VPS #{$vpsId} recovered from partition with " . count($activeStreams) . " active streams");

        // Mark VPS as active
        $vps->update([
            'status' => 'ACTIVE',
            'last_heartbeat_at' => now()
        ]);

        // Reconcile streams
        self::reconcileStreamsAfterRecovery($vps, $activeStreams);
    }

    /**
     * Mark VPS as partitioned
     */
    private static function markVpsAsPartitioned(VpsServer $vps): void
    {
        $vps->update([
            'status' => 'PARTITIONED',
            'status_message' => 'Network partition detected - no heartbeat for ' . 
                               $vps->last_heartbeat_at->diffInMinutes(now()) . ' minutes'
        ]);

        // Store partition timestamp
        Redis::setex("partition_timestamp:{$vps->id}", 3600, now()->timestamp);
    }

    /**
     * Handle streams on partitioned VPS
     */
    private static function handlePartitionedStreams(VpsServer $vps): void
    {
        $streams = StreamConfiguration::where('vps_server_id', $vps->id)
            ->whereIn('status', ['STREAMING', 'STARTING'])
            ->get();

        foreach ($streams as $stream) {
            Log::warning("⚠️ [NetworkPartition] Stream #{$stream->id} on partitioned VPS #{$vps->id}");

            // Don't immediately reassign - wait for potential recovery
            $stream->update([
                'status' => 'PARTITIONED',
                'error_message' => "VPS network partition detected - monitoring for recovery"
            ]);

            \App\Services\StreamProgressService::createStageProgress(
                $stream->id,
                'warning',
                "🔌 Network partition detected - monitoring VPS recovery"
            );
        }

        // Schedule recovery check
        \App\Jobs\CheckPartitionRecoveryJob::dispatch($vps->id)
            ->delay(now()->addMinutes(self::RECOVERY_GRACE_PERIOD / 60));
    }

    /**
     * Reconcile streams after partition recovery
     */
    private static function reconcileStreamsAfterRecovery(VpsServer $vps, array $activeStreams): void
    {
        $partitionedStreams = StreamConfiguration::where('vps_server_id', $vps->id)
            ->where('status', 'PARTITIONED')
            ->get();

        foreach ($partitionedStreams as $stream) {
            if (in_array($stream->id, $activeStreams)) {
                // Stream is still running - recover
                Log::info("✅ [NetworkPartition] Stream #{$stream->id} survived partition, recovering");
                
                $stream->update([
                    'status' => 'STREAMING',
                    'error_message' => null,
                    'last_status_update' => now()
                ]);

                \App\Services\StreamProgressService::createStageProgress(
                    $stream->id,
                    'streaming',
                    "🔄 Stream recovered after network partition"
                );
            } else {
                // Stream died during partition
                Log::warning("💀 [NetworkPartition] Stream #{$stream->id} died during partition");
                
                $stream->update([
                    'status' => 'ERROR',
                    'error_message' => 'Stream died during network partition',
                    'vps_server_id' => null
                ]);

                $vps->decrement('current_streams');

                \App\Services\StreamProgressService::createStageProgress(
                    $stream->id,
                    'error',
                    "💀 Stream died during network partition"
                );
            }
        }
    }

    /**
     * Check if VPS is currently partitioned
     */
    public static function isPartitioned(int $vpsId): bool
    {
        return Redis::exists("partition_timestamp:{$vpsId}") > 0;
    }

    /**
     * Get partition duration
     */
    public static function getPartitionDuration(int $vpsId): ?int
    {
        $timestamp = Redis::get("partition_timestamp:{$vpsId}");
        return $timestamp ? (now()->timestamp - $timestamp) : null;
    }

    /**
     * Clear partition state
     */
    public static function clearPartitionState(int $vpsId): void
    {
        Redis::del("partition_timestamp:{$vpsId}");
        Log::info("🧹 [NetworkPartition] Cleared partition state for VPS #{$vpsId}");
    }
}
