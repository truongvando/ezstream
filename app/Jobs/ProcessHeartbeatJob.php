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
        public array $activeStreams
    ) {
    }

    public function handle(): void
    {
        try {
            Log::info("💓 [ProcessHeartbeat] Processing heartbeat for VPS #{$this->vpsId}", [
                'active_streams' => $this->activeStreams,
                'stream_count' => count($this->activeStreams)
            ]);

            // Lưu trạng thái thực tế của agent vào Redis, TTL 10 phút
            $key = 'agent_state:' . $this->vpsId;
            Redis::setex($key, 600, json_encode($this->activeStreams));
            
            // Cập nhật thời gian heartbeat cho VPS
            VpsServer::where('id', $this->vpsId)->update([
                'last_heartbeat_at' => now(),
                'current_streams' => count($this->activeStreams)
            ]);

            Log::info("✅ [ProcessHeartbeat] Heartbeat processed successfully for VPS #{$this->vpsId}");

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
}
