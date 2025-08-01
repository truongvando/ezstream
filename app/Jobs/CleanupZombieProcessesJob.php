<?php

namespace App\Jobs;

use App\Models\VpsServer;
use App\Models\StreamConfiguration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

class CleanupZombieProcessesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 120;

    public function handle(): void
    {
        Log::info("🧹 [ZombieCleanup] Starting zombie process cleanup");

        $this->cleanupOrphanedStreams();
        $this->cleanupStaleAgentStates();
        $this->sendCleanupCommandsToAgents();

        Log::info("✅ [ZombieCleanup] Zombie process cleanup completed");
    }

    /**
     * Cleanup streams that are marked as STREAMING but VPS is dead
     */
    private function cleanupOrphanedStreams(): void
    {
        $orphanedStreams = StreamConfiguration::whereIn('status', ['STREAMING', 'STARTING'])
            ->whereHas('vpsServer', function($query) {
                $query->where('status', '!=', 'ACTIVE')
                    ->orWhere('last_heartbeat_at', '<', now()->subMinutes(10));
            })
            ->get();

        foreach ($orphanedStreams as $stream) {
            Log::warning("🧟 [ZombieCleanup] Found orphaned stream #{$stream->id} on dead VPS #{$stream->vps_server_id}");

            $stream->update([
                'status' => 'ERROR',
                'error_message' => 'Stream orphaned - VPS became unresponsive',
                'vps_server_id' => null,
                'last_stopped_at' => now()
            ]);

            // Decrement VPS stream count
            if ($stream->vpsServer) {
                $stream->vpsServer->decrement('current_streams');
            }

            \App\Services\StreamProgressService::createStageProgress(
                $stream->id,
                'error',
                "🧟 Stream orphaned - VPS became unresponsive"
            );
        }

        if ($orphanedStreams->count() > 0) {
            Log::info("🧹 [ZombieCleanup] Cleaned up {$orphanedStreams->count()} orphaned streams");
        }
    }

    /**
     * Cleanup stale agent states from Redis
     */
    private function cleanupStaleAgentStates(): void
    {
        $pattern = 'agent_state:*';
        $keys = Redis::keys($pattern);
        $cleanedCount = 0;

        foreach ($keys as $key) {
            $stateData = Redis::get($key);
            if (!$stateData) continue;

            $state = json_decode($stateData, true);
            if (!$state || !isset($state['last_heartbeat'])) continue;

            $lastHeartbeat = \Carbon\Carbon::parse($state['last_heartbeat']);
            $minutesSinceHeartbeat = $lastHeartbeat->diffInMinutes(now());

            // Remove states older than 30 minutes
            if ($minutesSinceHeartbeat > 30) {
                Redis::del($key);
                $cleanedCount++;
                
                $vpsId = str_replace('agent_state:', '', $key);
                Log::debug("🧹 [ZombieCleanup] Removed stale agent state for VPS #{$vpsId} ({$minutesSinceHeartbeat}m old)");
            }
        }

        if ($cleanedCount > 0) {
            Log::info("🧹 [ZombieCleanup] Cleaned up {$cleanedCount} stale agent states");
        }
    }

    /**
     * Send cleanup commands to all active agents
     */
    private function sendCleanupCommandsToAgents(): void
    {
        $activeVpsList = VpsServer::where('status', 'ACTIVE')
            ->where('last_heartbeat_at', '>', now()->subMinutes(5))
            ->get();

        foreach ($activeVpsList as $vps) {
            $this->sendCleanupCommand($vps->id);
        }

        Log::info("📤 [ZombieCleanup] Sent cleanup commands to {$activeVpsList->count()} active VPS");
    }

    /**
     * Send cleanup command to specific VPS
     */
    private function sendCleanupCommand(int $vpsId): void
    {
        $command = [
            'command' => 'CLEANUP_ZOMBIES',
            'vps_id' => $vpsId,
            'timestamp' => time(),
            'source' => 'CleanupZombieProcessesJob'
        ];

        $channel = "vps-commands:{$vpsId}";
        $result = Redis::publish($channel, json_encode($command));

        Log::debug("📤 [ZombieCleanup] Sent CLEANUP_ZOMBIES to VPS #{$vpsId} (subscribers: {$result})");
    }

    /**
     * Cleanup specific VPS zombie processes
     */
    public static function cleanupVpsZombies(int $vpsId): void
    {
        Log::info("🧹 [ZombieCleanup] Cleaning up zombies for VPS #{$vpsId}");

        // Find streams that should not be running on this VPS
        $expectedStreams = StreamConfiguration::where('vps_server_id', $vpsId)
            ->whereIn('status', ['STREAMING', 'STARTING'])
            ->pluck('id')
            ->toArray();

        // Get agent state to see what's actually running
        $agentStateKey = 'agent_state:' . $vpsId;
        $agentState = Redis::get($agentStateKey);
        
        if (!$agentState) {
            Log::warning("⚠️ [ZombieCleanup] No agent state found for VPS #{$vpsId}");
            return;
        }

        $state = json_decode($agentState, true);
        $actualStreams = $state['active_streams'] ?? [];

        // Find zombie streams (running on agent but not expected in DB)
        $zombieStreams = array_diff($actualStreams, $expectedStreams);

        foreach ($zombieStreams as $streamId) {
            Log::warning("🧟 [ZombieCleanup] Found zombie stream #{$streamId} on VPS #{$vpsId}");
            
            // Send STOP command for zombie stream
            $command = [
                'command' => 'STOP_STREAM',
                'stream_id' => $streamId,
                'vps_id' => $vpsId,
                'timestamp' => time(),
                'reason' => 'Zombie process cleanup',
                'source' => 'CleanupZombieProcessesJob'
            ];

            $channel = "vps-commands:{$vpsId}";
            Redis::publish($channel, json_encode($command));
        }

        if (count($zombieStreams) > 0) {
            Log::info("🧹 [ZombieCleanup] Sent STOP commands for " . count($zombieStreams) . " zombie streams on VPS #{$vpsId}");
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("❌ [ZombieCleanup] Job failed: " . $exception->getMessage());
    }
}
