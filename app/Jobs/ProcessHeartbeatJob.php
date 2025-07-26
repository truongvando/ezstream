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
}
