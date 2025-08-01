<?php

namespace App\Jobs;

use App\Models\VpsServer;
use App\Services\GracefulAgentUpdate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CheckAgentUpdateCompletionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private int $vpsId;
    private int $maxRetries = 5;

    public function __construct(int $vpsId)
    {
        $this->vpsId = $vpsId;
    }

    public function handle(): void
    {
        $vps = VpsServer::find($this->vpsId);
        if (!$vps) {
            Log::error("❌ [AgentUpdateCheck] VPS #{$this->vpsId} not found");
            return;
        }

        Log::info("🔍 [AgentUpdateCheck] Checking update completion for VPS #{$this->vpsId}");

        // Check if VPS is back online and responding
        if ($vps->status === 'ACTIVE' && $vps->last_heartbeat_at && $vps->last_heartbeat_at->gt(now()->subMinutes(2))) {
            // Update completed successfully
            GracefulAgentUpdate::handleUpdateCompletion($this->vpsId, true, 'Agent responding normally');
            return;
        }

        // Check if still updating
        if (in_array($vps->status, ['UPDATING', 'PREPARING_UPDATE'])) {
            $minutesSinceUpdate = $vps->updated_at->diffInMinutes(now());
            
            if ($minutesSinceUpdate > 10) {
                // Update taking too long - mark as failed
                Log::error("⏰ [AgentUpdateCheck] Update timeout for VPS #{$this->vpsId} ({$minutesSinceUpdate}m)");
                GracefulAgentUpdate::handleUpdateCompletion($this->vpsId, false, 'Update timeout');
                return;
            }

            // Still updating - check again later
            if ($this->attempts() < $this->maxRetries) {
                Log::info("⏳ [AgentUpdateCheck] VPS #{$this->vpsId} still updating, checking again in 1 minute");
                self::dispatch($this->vpsId)->delay(now()->addMinute());
            } else {
                Log::error("❌ [AgentUpdateCheck] Max retries exceeded for VPS #{$this->vpsId}");
                GracefulAgentUpdate::handleUpdateCompletion($this->vpsId, false, 'Max retries exceeded');
            }
            return;
        }

        // VPS in unexpected state
        Log::warning("⚠️ [AgentUpdateCheck] VPS #{$this->vpsId} in unexpected state: {$vps->status}");
        GracefulAgentUpdate::handleUpdateCompletion($this->vpsId, false, "Unexpected state: {$vps->status}");
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("❌ [AgentUpdateCheck] Job failed for VPS #{$this->vpsId}: " . $exception->getMessage());
        GracefulAgentUpdate::handleUpdateCompletion($this->vpsId, false, 'Check job failed: ' . $exception->getMessage());
    }
}
