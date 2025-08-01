<?php

namespace App\Jobs;

use App\Models\VpsServer;
use App\Models\StreamConfiguration;
use App\Services\GracefulAgentUpdate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CheckHandoverCompletionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private int $originalVpsId;
    private int $handoverVpsId;

    public function __construct(int $originalVpsId, int $handoverVpsId)
    {
        $this->originalVpsId = $originalVpsId;
        $this->handoverVpsId = $handoverVpsId;
    }

    public function handle(): void
    {
        Log::info("🔍 [HandoverCheck] Checking handover completion from VPS #{$this->originalVpsId} to VPS #{$this->handoverVpsId}");

        $originalVps = VpsServer::find($this->originalVpsId);
        $handoverVps = VpsServer::find($this->handoverVpsId);

        if (!$originalVps || !$handoverVps) {
            Log::error("❌ [HandoverCheck] VPS not found - Original: {$this->originalVpsId}, Handover: {$this->handoverVpsId}");
            return;
        }

        // Check streams that should have been handed over
        $handedOverStreams = StreamConfiguration::where('vps_server_id', $this->handoverVpsId)
            ->whereIn('status', ['STREAMING', 'STARTING'])
            ->get();

        $remainingStreams = StreamConfiguration::where('vps_server_id', $this->originalVpsId)
            ->whereIn('status', ['STREAMING', 'STARTING'])
            ->get();

        Log::info("📊 [HandoverCheck] Handover status - Handed over: {$handedOverStreams->count()}, Remaining: {$remainingStreams->count()}");

        if ($remainingStreams->isEmpty()) {
            // All streams successfully handed over
            Log::info("✅ [HandoverCheck] All streams successfully handed over, proceeding with agent update");
            GracefulAgentUpdate::completeUpdateAfterHandover($this->originalVpsId);
        } else {
            // Some streams still on original VPS
            Log::warning("⚠️ [HandoverCheck] {$remainingStreams->count()} streams still on original VPS #{$this->originalVpsId}");
            
            // Try to force handover remaining streams
            foreach ($remainingStreams as $stream) {
                Log::info("🔄 [HandoverCheck] Force handing over remaining stream #{$stream->id}");
                
                $stream->update([
                    'vps_server_id' => $this->handoverVpsId,
                    'status' => 'STARTING',
                    'error_message' => 'Force handover during agent update'
                ]);

                $originalVps->decrement('current_streams');
                $handoverVps->increment('current_streams');

                \App\Jobs\StartMultistreamJob::dispatch($stream->id);
            }

            // Schedule another check
            self::dispatch($this->originalVpsId, $this->handoverVpsId)
                ->delay(now()->addSeconds(30));
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("❌ [HandoverCheck] Job failed for handover {$this->originalVpsId} → {$this->handoverVpsId}: " . $exception->getMessage());
        
        // Force proceed with update anyway
        GracefulAgentUpdate::completeUpdateAfterHandover($this->originalVpsId);
    }
}
