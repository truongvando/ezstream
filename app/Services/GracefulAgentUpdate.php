<?php

namespace App\Services;

use App\Models\VpsServer;
use App\Models\StreamConfiguration;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

class GracefulAgentUpdate
{
    private const UPDATE_TIMEOUT = 300; // 5 minutes
    private const HANDOVER_GRACE_PERIOD = 60; // 1 minute

    /**
     * Initiate graceful agent update
     */
    public static function initiateUpdate(int $vpsId, array $updateData = []): array
    {
        $vps = VpsServer::find($vpsId);
        if (!$vps) {
            return ['success' => false, 'error' => 'VPS not found'];
        }

        Log::info("🔄 [AgentUpdate] Initiating graceful update for VPS #{$vpsId}");

        // Check if VPS has active streams
        $activeStreams = StreamConfiguration::where('vps_server_id', $vpsId)
            ->whereIn('status', ['STREAMING', 'STARTING'])
            ->get();

        if ($activeStreams->isEmpty()) {
            // No active streams - can update immediately
            return self::performDirectUpdate($vps, $updateData);
        } else {
            // Has active streams - need graceful handover
            return self::performGracefulUpdate($vps, $activeStreams, $updateData);
        }
    }

    /**
     * Perform direct update (no active streams)
     */
    private static function performDirectUpdate(VpsServer $vps, array $updateData): array
    {
        Log::info("⚡ [AgentUpdate] No active streams, performing direct update for VPS #{$vps->id}");

        // Mark VPS as updating
        $vps->update([
            'status' => 'UPDATING',
            'status_message' => 'Agent update in progress'
        ]);

        // Send update command
        $command = [
            'command' => 'UPDATE_AGENT',
            'vps_id' => $vps->id,
            'timestamp' => time(),
            'update_data' => $updateData,
            'source' => 'GracefulAgentUpdate'
        ];

        $channel = "vps-commands:{$vps->id}";
        $result = Redis::publish($channel, json_encode($command));

        // Schedule update completion check
        \App\Jobs\CheckAgentUpdateCompletionJob::dispatch($vps->id)
            ->delay(now()->addMinutes(2));

        Log::info("📤 [AgentUpdate] Sent UPDATE_AGENT command to VPS #{$vps->id} (subscribers: {$result})");

        return [
            'success' => true,
            'type' => 'direct',
            'message' => 'Agent update initiated - no active streams'
        ];
    }

    /**
     * Perform graceful update with stream handover
     */
    private static function performGracefulUpdate(VpsServer $vps, $activeStreams, array $updateData): array
    {
        Log::info("🔄 [AgentUpdate] Performing graceful update for VPS #{$vps->id} with {$activeStreams->count()} active streams");

        // Find alternative VPS for stream handover
        $alternativeVps = self::findAlternativeVps($vps, $activeStreams->count());
        
        if (!$alternativeVps) {
            Log::warning("⚠️ [AgentUpdate] No alternative VPS available for handover, delaying update");
            return [
                'success' => false,
                'error' => 'No alternative VPS available for stream handover',
                'retry_after' => 300 // Retry in 5 minutes
            ];
        }

        // Mark VPS as preparing for update
        $vps->update([
            'status' => 'PREPARING_UPDATE',
            'status_message' => "Preparing update - handover to VPS #{$alternativeVps->id}"
        ]);

        // Store update context
        $updateContext = [
            'original_vps_id' => $vps->id,
            'handover_vps_id' => $alternativeVps->id,
            'streams_to_handover' => $activeStreams->pluck('id')->toArray(),
            'update_data' => $updateData,
            'initiated_at' => now()->toISOString()
        ];

        Redis::setex("agent_update_context:{$vps->id}", self::UPDATE_TIMEOUT, json_encode($updateContext));

        // Start stream handover process
        self::initiateStreamHandover($vps, $alternativeVps, $activeStreams);

        return [
            'success' => true,
            'type' => 'graceful',
            'handover_vps_id' => $alternativeVps->id,
            'streams_count' => $activeStreams->count(),
            'message' => "Graceful update initiated - handover to VPS #{$alternativeVps->id}"
        ];
    }

    /**
     * Initiate stream handover to alternative VPS
     */
    private static function initiateStreamHandover(VpsServer $originalVps, VpsServer $handoverVps, $streams): void
    {
        Log::info("🔄 [AgentUpdate] Starting stream handover from VPS #{$originalVps->id} to VPS #{$handoverVps->id}");

        foreach ($streams as $stream) {
            Log::info("🔄 [AgentUpdate] Handing over stream #{$stream->id}");

            // Update stream assignment
            $stream->update([
                'vps_server_id' => $handoverVps->id,
                'status' => 'STARTING',
                'error_message' => "Stream handover during agent update"
            ]);

            // Update VPS counters
            $originalVps->decrement('current_streams');
            $handoverVps->increment('current_streams');

            // Start stream on new VPS
            \App\Jobs\StartMultistreamJob::dispatch($stream->id)
                ->delay(now()->addSeconds(5)); // Small delay to avoid conflicts

            \App\Services\StreamProgressService::createStageProgress(
                $stream->id,
                'starting',
                "🔄 Stream handover during agent update (VPS #{$originalVps->id} → #{$handoverVps->id})"
            );
        }

        // Schedule handover completion check
        \App\Jobs\CheckHandoverCompletionJob::dispatch($originalVps->id, $handoverVps->id)
            ->delay(now()->addSeconds(self::HANDOVER_GRACE_PERIOD));
    }

    /**
     * Find alternative VPS for handover
     */
    private static function findAlternativeVps(VpsServer $originalVps, int $streamsCount): ?VpsServer
    {
        return VpsServer::where('id', '!=', $originalVps->id)
            ->where('status', 'ACTIVE')
            ->where('last_heartbeat_at', '>', now()->subMinutes(2))
            ->whereRaw('(max_streams - current_streams) >= ?', [$streamsCount])
            ->orderBy('current_streams', 'asc') // Prefer less loaded VPS
            ->first();
    }

    /**
     * Complete agent update after handover
     */
    public static function completeUpdateAfterHandover(int $vpsId): void
    {
        $contextKey = "agent_update_context:{$vpsId}";
        $contextData = Redis::get($contextKey);

        if (!$contextData) {
            Log::warning("⚠️ [AgentUpdate] No update context found for VPS #{$vpsId}");
            return;
        }

        $context = json_decode($contextData, true);
        $vps = VpsServer::find($vpsId);

        if (!$vps) {
            Log::error("❌ [AgentUpdate] VPS #{$vpsId} not found during update completion");
            return;
        }

        Log::info("🔄 [AgentUpdate] Completing agent update for VPS #{$vpsId} after handover");

        // Mark VPS as updating
        $vps->update([
            'status' => 'UPDATING',
            'status_message' => 'Agent update in progress - streams handed over'
        ]);

        // Send update command
        $command = [
            'command' => 'UPDATE_AGENT',
            'vps_id' => $vpsId,
            'timestamp' => time(),
            'update_data' => $context['update_data'] ?? [],
            'source' => 'GracefulAgentUpdate'
        ];

        $channel = "vps-commands:{$vpsId}";
        $result = Redis::publish($channel, json_encode($command));

        // Schedule update completion check
        \App\Jobs\CheckAgentUpdateCompletionJob::dispatch($vpsId)
            ->delay(now()->addMinutes(2));

        // Cleanup context
        Redis::del($contextKey);

        Log::info("📤 [AgentUpdate] Sent UPDATE_AGENT command to VPS #{$vpsId} after handover (subscribers: {$result})");
    }

    /**
     * Handle update completion
     */
    public static function handleUpdateCompletion(int $vpsId, bool $success, string $message = ''): void
    {
        $vps = VpsServer::find($vpsId);
        if (!$vps) return;

        if ($success) {
            Log::info("✅ [AgentUpdate] Agent update completed successfully for VPS #{$vpsId}");
            
            $vps->update([
                'status' => 'ACTIVE',
                'status_message' => 'Agent updated successfully',
                'last_heartbeat_at' => now() // Reset heartbeat
            ]);
        } else {
            Log::error("❌ [AgentUpdate] Agent update failed for VPS #{$vpsId}: {$message}");
            
            $vps->update([
                'status' => 'ERROR',
                'status_message' => "Agent update failed: {$message}"
            ]);
        }

        // Cleanup any remaining context
        Redis::del("agent_update_context:{$vpsId}");
    }

    /**
     * Get update status
     */
    public static function getUpdateStatus(int $vpsId): array
    {
        $vps = VpsServer::find($vpsId);
        if (!$vps) {
            return ['status' => 'not_found'];
        }

        $contextKey = "agent_update_context:{$vpsId}";
        $contextData = Redis::get($contextKey);

        $status = [
            'vps_status' => $vps->status,
            'status_message' => $vps->status_message,
            'has_context' => !empty($contextData),
            'context' => $contextData ? json_decode($contextData, true) : null
        ];

        return $status;
    }
}
