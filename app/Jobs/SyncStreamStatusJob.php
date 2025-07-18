<?php

namespace App\Jobs;

use App\Models\StreamConfiguration;
use App\Models\VpsServer;
use App\Services\StreamProgressService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class SyncStreamStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 120;

    public function __construct()
    {
        //
    }

    /**
     * Sync stream status between database and VPS reality
     */
    public function handle(): void
    {
        Log::info("🔄 [SyncStreamStatus] Starting stream status synchronization");

        try {
            // Get all streams that claim to be STARTING or STREAMING
            $activeStreams = StreamConfiguration::whereIn('status', ['STARTING', 'STREAMING'])
                ->with('vpsServer')
                ->get();

            $syncedCount = 0;
            $errorCount = 0;

            foreach ($activeStreams as $stream) {
                try {
                    $this->syncStreamStatus($stream);
                    $syncedCount++;
                } catch (\Exception $e) {
                    $errorCount++;
                    Log::error("❌ [SyncStreamStatus] Failed to sync stream #{$stream->id}", [
                        'error' => $e->getMessage(),
                        'stream_id' => $stream->id,
                        'status' => $stream->status
                    ]);
                }
            }

            Log::info("✅ [SyncStreamStatus] Synchronization completed", [
                'total_streams' => $activeStreams->count(),
                'synced' => $syncedCount,
                'errors' => $errorCount
            ]);

        } catch (\Exception $e) {
            Log::error("❌ [SyncStreamStatus] Job failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Sync individual stream status
     */
    private function syncStreamStatus(StreamConfiguration $stream): void
    {
        // Check if stream has been stuck in STARTING for too long
        if ($stream->status === 'STARTING') {
            $minutesSinceStart = $stream->last_started_at ?
                now()->diffInMinutes($stream->last_started_at) : 999;

            // If stuck in STARTING for more than 5 minutes, mark as ERROR (heartbeat every 60s)
            if ($minutesSinceStart > 5) {
                Log::warning("⚠️ [SyncStreamStatus] Stream #{$stream->id} stuck in STARTING for {$minutesSinceStart} minutes");

                $stream->update([
                    'status' => 'ERROR',
                    'error_message' => "Stream stuck in STARTING status for {$minutesSinceStart} minutes",
                    'vps_server_id' => null
                ]);

                if ($stream->vpsServer) {
                    $stream->vpsServer->decrement('current_streams');
                }

                StreamProgressService::createStageProgress(
                    $stream->id,
                    'error',
                    "Stream timeout: stuck in STARTING for {$minutesSinceStart} minutes"
                );
                return;
            }
        }

        // Check if stream has been without heartbeat for too long
        if ($stream->status === 'STREAMING') {
            $minutesSinceUpdate = $stream->last_status_update ? 
                now()->diffInMinutes($stream->last_status_update) : 999;

            // If no heartbeat for more than 3 minutes, mark as ERROR (heartbeat every 60s)
            if ($minutesSinceUpdate > 3) {
                Log::warning("⚠️ [SyncStreamStatus] Stream #{$stream->id} no heartbeat for {$minutesSinceUpdate} minutes");

                $stream->update([
                    'status' => 'ERROR',
                    'error_message' => "No heartbeat received for {$minutesSinceUpdate} minutes",
                    'vps_server_id' => null
                ]);

                if ($stream->vpsServer) {
                    $stream->vpsServer->decrement('current_streams');
                }

                StreamProgressService::createStageProgress(
                    $stream->id,
                    'error',
                    "Stream lost: no heartbeat for {$minutesSinceUpdate} minutes"
                );
                return;
            }
        }

        // If stream has VPS but VPS is offline, mark as ERROR
        if ($stream->vpsServer && !$this->isVpsOnline($stream->vpsServer)) {
            Log::warning("⚠️ [SyncStreamStatus] Stream #{$stream->id} VPS #{$stream->vps_server_id} is offline");
            
            $stream->update([
                'status' => 'ERROR',
                'error_message' => "VPS server is offline",
                'vps_server_id' => null
            ]);

            StreamProgressService::createStageProgress(
                $stream->id, 
                'error', 
                "VPS server is offline"
            );
        }
    }

    /**
     * Check if VPS is online by checking recent stats
     */
    private function isVpsOnline(VpsServer $vps): bool
    {
        try {
            $stats = Redis::hget('vps_live_stats', $vps->id);
            if (!$stats) {
                return false;
            }

            $data = json_decode($stats, true);
            if (!$data || !isset($data['received_at'])) {
                return false;
            }

            // Consider VPS online if we received stats in the last 2 minutes
            $lastUpdate = $data['received_at'];
            $minutesSinceUpdate = (time() - $lastUpdate) / 60;

            return $minutesSinceUpdate <= 2;

        } catch (\Exception $e) {
            Log::error("❌ [SyncStreamStatus] Failed to check VPS #{$vps->id} status", [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("💥 [SyncStreamStatus] Job failed permanently", [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}
