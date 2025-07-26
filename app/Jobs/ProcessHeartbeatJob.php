<?php

namespace App\Jobs;

use App\Models\VpsServer;
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
        public bool $isReAnnounce = false
    ) {
    }

    public function handle(): void
    {
        try {
            $logPrefix = $this->isReAnnounce ? "🔄 [ProcessHeartbeat-ReAnnounce]" : "💓 [ProcessHeartbeat]";

            Log::info("{$logPrefix} Processing heartbeat for VPS #{$this->vpsId}", [
                'active_streams' => $this->activeStreams,
                'stream_count' => count($this->activeStreams),
                'is_re_announce' => $this->isReAnnounce
            ]);

            // Lưu trạng thái thực tế của agent vào Redis, TTL 10 phút
            $key = 'agent_state:' . $this->vpsId;
            Redis::setex($key, 600, json_encode($this->activeStreams));

            // Cập nhật thời gian heartbeat cho VPS
            VpsServer::where('id', $this->vpsId)->update([
                'last_heartbeat_at' => now(),
                'current_streams' => count($this->activeStreams)
            ]);

            // Always check for ghost streams (agent reports but DB doesn't expect)
            $this->handleGhostStreams();

            // If this is a re-announce, force sync streams to STREAMING status
            if ($this->isReAnnounce && !empty($this->activeStreams)) {
                $this->handleReAnnounceStreams();
            }

            Log::info("✅ {$logPrefix} Heartbeat processed successfully for VPS #{$this->vpsId}");

        } catch (\Exception $e) {
            Log::error("❌ [ProcessHeartbeat] Failed to process heartbeat for VPS #{$this->vpsId}", [
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

                // Stream exists but has wrong status
                if (!in_array($stream->status, ['STREAMING', 'STARTING'])) {
                    Log::warning("👻 [GhostStream] Agent reports stream #{$streamId} but DB status is '{$stream->status}'. Expected STREAMING/STARTING.");

                    // Check if this could be a legitimate recovery case
                    if (in_array($stream->status, ['ERROR', 'INACTIVE']) && $stream->vps_server_id == $this->vpsId) {
                        // This might be a stream that errored due to Laravel restart but is actually still running
                        Log::info("🔄 [GhostStream] Attempting to recover stream #{$streamId} from {$stream->status} to STREAMING");

                        $stream->update([
                            'status' => 'STREAMING',
                            'vps_server_id' => $this->vpsId,
                            'last_status_update' => now(),
                            'error_message' => null,
                            'last_started_at' => $stream->last_started_at ?: now()
                        ]);

                        \App\Services\StreamProgressService::createStageProgress(
                            $streamId,
                            'streaming',
                            "👻 Ghost stream recovered: Agent was still streaming despite DB status"
                        );
                    } else {
                        // Stream shouldn't be running - send stop command with detailed reason
                        $reason = "Ghost stream cleanup: DB status '{$stream->status}' but agent reports running";
                        Log::warning("👻 [GhostStream] Stream #{$streamId} shouldn't be running (status: {$stream->status}). Sending STOP command. Reason: {$reason}");
                        $this->sendStopCommand($streamId, $reason);
                    }
                }

                // Stream exists and has correct status - ensure VPS assignment is correct
                if (in_array($stream->status, ['STREAMING', 'STARTING']) && $stream->vps_server_id != $this->vpsId) {
                    Log::info("🔄 [GhostStream] Correcting VPS assignment for stream #{$streamId}: {$stream->vps_server_id} → {$this->vpsId}");
                    $stream->update(['vps_server_id' => $this->vpsId]);
                }
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

            $channel = "vps_commands:{$this->vpsId}";
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
}
