<?php

namespace App\Jobs;

use App\Models\VpsServer;
use App\Models\StreamConfiguration;
use App\Services\NetworkPartitionHandler;
use App\Services\Stream\StreamAllocation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CheckPartitionRecoveryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private int $vpsId;
    private int $maxRetries = 3;

    public function __construct(int $vpsId)
    {
        $this->vpsId = $vpsId;
    }

    public function handle(): void
    {
        $vps = VpsServer::find($this->vpsId);
        if (!$vps) return;

        Log::info("🔍 [PartitionRecovery] Checking recovery status for VPS #{$this->vpsId}");

        if ($vps->status === 'PARTITIONED') {
            // VPS still partitioned - handle failed streams
            $this->handleFailedRecovery($vps);
        } else {
            // VPS recovered - cleanup
            NetworkPartitionHandler::clearPartitionState($this->vpsId);
            Log::info("✅ [PartitionRecovery] VPS #{$this->vpsId} has recovered");
        }
    }

    private function handleFailedRecovery(VpsServer $vps): void
    {
        Log::warning("💀 [PartitionRecovery] VPS #{$this->vpsId} failed to recover, reassigning streams");

        $partitionedStreams = StreamConfiguration::where('vps_server_id', $vps->id)
            ->where('status', 'PARTITIONED')
            ->get();

        $streamAllocation = new StreamAllocation();

        foreach ($partitionedStreams as $stream) {
            Log::info("🔄 [PartitionRecovery] Reassigning stream #{$stream->id} from failed VPS #{$vps->id}");

            // Reset stream for reassignment
            $stream->update([
                'vps_server_id' => null,
                'status' => 'PENDING',
                'error_message' => 'Reassigning due to VPS partition failure'
            ]);

            // Decrement VPS capacity
            $vps->decrement('current_streams');

            // Try to reassign
            try {
                $result = $streamAllocation->assignStreamToVps($stream);
                
                if ($result['success'] && $result['action'] === 'assigned') {
                    Log::info("✅ [PartitionRecovery] Stream #{$stream->id} reassigned to VPS #{$result['vps_id']}");
                    
                    // Start the stream on new VPS
                    \App\Jobs\StartMultistreamJob::dispatch($stream->id);
                } else {
                    Log::warning("⏳ [PartitionRecovery] Stream #{$stream->id} queued for later assignment");
                }
            } catch (\Exception $e) {
                Log::error("❌ [PartitionRecovery] Failed to reassign stream #{$stream->id}: " . $e->getMessage());
                
                $stream->update([
                    'status' => 'ERROR',
                    'error_message' => 'Failed to reassign after VPS partition: ' . $e->getMessage()
                ]);
            }
        }

        // Mark VPS as failed
        $vps->update([
            'status' => 'FAILED',
            'status_message' => 'Failed to recover from network partition'
        ]);

        NetworkPartitionHandler::clearPartitionState($this->vpsId);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("❌ [PartitionRecovery] Job failed for VPS #{$this->vpsId}: " . $exception->getMessage());
    }
}
