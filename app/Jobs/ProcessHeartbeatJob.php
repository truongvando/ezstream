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

class ProcessHeartbeatJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 30;

    public function __construct(
        public int $vpsId,
        public array $activeStreams,
        public bool $isReAnnounce = false,
        public bool $isImmediateUpdate = false
    ) {
    }

    public function handle(): void
    {
        try {
            // Skip ghost stream handling for local development environment
            if (app()->environment('local') && $this->vpsId >= 60) {
                Log::info("🔧 [ProcessHeartbeat] Skipping ghost stream handling for local development VPS #{$this->vpsId}");
                return;
            }

            $logPrefix = $this->isImmediateUpdate ? "⚡ [ProcessHeartbeat-Immediate]" :
                        ($this->isReAnnounce ? "🔄 [ProcessHeartbeat-ReAnnounce]" : "💓 [ProcessHeartbeat]");

            Log::info("{$logPrefix} Processing enhanced heartbeat for VPS #{$this->vpsId}", [
                'active_streams' => $this->activeStreams,
                'stream_count' => count($this->activeStreams),
                'is_re_announce' => $this->isReAnnounce,
                'is_immediate_update' => $this->isImmediateUpdate
            ]);

            // Lưu trạng thái thực tế của agent vào Redis với enhanced data, TTL 10 phút
            $agentStateKey = 'agent_state:' . $this->vpsId;
            $enhancedState = [
                'active_streams' => $this->activeStreams,
                'last_heartbeat' => now()->toISOString(),
                'is_re_announce' => $this->isReAnnounce,
                'is_immediate_update' => $this->isImmediateUpdate,
                'heartbeat_count' => Redis::incr("heartbeat_count:{$this->vpsId}")
            ];

            Redis::setex($agentStateKey, 600, json_encode($enhancedState));

            // Cập nhật thời gian heartbeat cho VPS với enhanced tracking
            $vpsUpdateData = [
                'last_heartbeat_at' => now(),
                'current_streams' => count($this->activeStreams),
                'status' => 'ACTIVE'
            ];

            VpsServer::where('id', $this->vpsId)->update($vpsUpdateData);

            // Enhanced ghost stream detection with state machine awareness
            $this->handleGhostStreams();

            // If this is a re-announce, force sync streams to STREAMING status
            if ($this->isReAnnounce && !empty($this->activeStreams)) {
                $this->handleReAnnounceStreams();
            }

            // Check for streams that should be running but aren't reported
            $this->handleMissingStreams();

            Log::info("✅ {$logPrefix} Enhanced heartbeat processed successfully for VPS #{$this->vpsId}");

        } catch (\Exception $e) {
            Log::error("❌ [ProcessHeartbeat] Failed to process enhanced heartbeat for VPS #{$this->vpsId}", [
                'error' => $e->getMessage(),
                'active_streams' => $this->activeStreams
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("💥 [ProcessHeartbeat] Job failed for VPS #{$this->vpsId}", [
            'error' => $exception->getMessage(),
            'active_streams' => $this->activeStreams
        ]);
    }

    /**
     * Handle ghost streams - agent reports streams that DB doesn't expect
     */
    private function handleGhostStreams(): void
    {
        try {
            if (empty($this->activeStreams)) {
                return;
            }

            foreach ($this->activeStreams as $streamId) {
                $stream = \App\Models\StreamConfiguration::find($streamId);

                if (!$stream) {
                    // Stream doesn't exist in DB - this is a true ghost
                    Log::warning("👻 [GhostStream] Agent reports stream #{$streamId} but it doesn't exist in DB. Sending STOP command.");
                    $this->sendStopCommand($streamId);
                    continue;
                }

                // CRITICAL: Only handle streams assigned to this VPS
                if ($stream->vps_server_id && $stream->vps_server_id != $this->vpsId) {
                    Log::warning("⚠️ [GhostStream] Stream #{$streamId} belongs to VPS #{$stream->vps_server_id}, not this VPS #{$this->vpsId}. Sending STOP command to prevent conflict.");
                    $this->sendStopCommand($streamId, "Stream belongs to different VPS (#{$stream->vps_server_id})");
                    continue;
                }

                // VALIDATE VPS EXISTS before updating
                $vps = VpsServer::find($this->vpsId);
                if (!$vps) {
                    Log::error("❌ [GhostStream] VPS #{$this->vpsId} does not exist! Cannot auto-recover stream #{$streamId}");
                    $this->sendStopCommand($streamId, "VPS #{$this->vpsId} does not exist");
                    continue;
                }

                // SIMPLE APPROACH: If agent reports stream active, trust it and auto-recover
                Log::info("🔄 [GhostStream] Agent reports stream #{$streamId} active (status: {$stream->status}), auto-recovering to STREAMING");

                $stream->update([
                    'status' => 'STREAMING',
                    'vps_server_id' => $this->vpsId,
                    'last_status_update' => now(),
                    'error_message' => null
                ]);

                \App\Services\StreamProgressService::createStageProgress(
                    $streamId,
                    'streaming',
                    "🔄 Stream auto-recovered: Agent confirmed running"
                );
            }
        } catch (\Exception $e) {
            Log::error("❌ [GhostStream] Failed to handle ghost streams: {$e->getMessage()}");
        }
    }

    /**
     * Handle re-announce scenario - force sync streams to correct status
     */
    private function handleReAnnounceStreams(): void
    {
        try {
            foreach ($this->activeStreams as $streamId) {
                $stream = \App\Models\StreamConfiguration::find($streamId);

                if ($stream && in_array($stream->status, ['ERROR', 'INACTIVE', 'STOPPED'])) {
                    Log::info("🔄 [ReAnnounce] Recovering stream #{$streamId} from {$stream->status} to STREAMING");

                    $stream->update([
                        'status' => 'STREAMING',
                        'vps_server_id' => $this->vpsId,
                        'last_status_update' => now(),
                        'error_message' => null,
                        'last_started_at' => $stream->last_started_at ?: now()
                    ]);

                    // Create progress update
                    \App\Services\StreamProgressService::createStageProgress(
                        $streamId,
                        'streaming',
                        "🔄 Stream recovered after Laravel restart (VPS #{$this->vpsId})"
                    );
                }
            }
        } catch (\Exception $e) {
            Log::error("❌ [ReAnnounce] Failed to handle re-announce streams: {$e->getMessage()}");
        }
    }

    /**
     * Send STOP command to agent for specific stream
     */
    private function sendStopCommand(int $streamId, string $reason = 'Ghost stream cleanup'): void
    {
        try {
            $command = [
                'command' => 'STOP_STREAM',
                'stream_id' => $streamId,
                'vps_id' => $this->vpsId,
                'timestamp' => time(),
                'reason' => $reason,
                'source' => 'ProcessHeartbeatJob'
            ];

            $channel = "vps-commands:{$this->vpsId}";
            $result = \Illuminate\Support\Facades\Redis::publish($channel, json_encode($command));

            Log::warning("📤 [GhostStream] Sent STOP command for stream #{$streamId} to VPS #{$this->vpsId} (subscribers: {$result}). Reason: {$reason}");

            // Also update stream in DB to prevent confusion
            $stream = \App\Models\StreamConfiguration::find($streamId);
            if ($stream) {
                $stream->update([
                    'error_message' => "Auto-stopped by system: {$reason}",
                    'last_stopped_at' => now()
                ]);
            }

        } catch (\Exception $e) {
            Log::error("❌ [GhostStream] Failed to send STOP command for stream #{$streamId}: {$e->getMessage()}");
        }
    }

    /**
     * Check for streams that should be running but aren't reported by agent
     */
    private function handleMissingStreams(): void
    {
        try {
            // Find streams that should be running on this VPS but aren't reported
            $expectedStreams = StreamConfiguration::where('vps_server_id', $this->vpsId)
                ->whereIn('status', ['STREAMING', 'STARTING'])
                ->pluck('id')
                ->toArray();

            $missingStreams = array_diff($expectedStreams, $this->activeStreams);

            foreach ($missingStreams as $streamId) {
                $stream = StreamConfiguration::find($streamId);
                if (!$stream) continue;

                $missingDuration = now()->diffInSeconds($stream->last_status_update ?? $stream->updated_at);

                if ($missingDuration > 180) { // 3 minutes tolerance
                    Log::warning("🔍 [MissingStream] Stream #{$streamId} should be running but not reported by agent for {$missingDuration}s");

                    // Mark as ERROR if missing too long
                    $stream->update([
                        'status' => 'ERROR',
                        'error_message' => "Stream missing from agent heartbeat for {$missingDuration} seconds",
                        'vps_server_id' => null
                    ]);

                    \App\Services\StreamProgressService::createStageProgress(
                        $streamId,
                        'error',
                        "❌ Stream mất kết nối với Agent ({$missingDuration}s)"
                    );
                }
            }
        } catch (\Exception $e) {
            Log::error("❌ [MissingStream] Failed to handle missing streams: {$e->getMessage()}");
        }
    }
}
