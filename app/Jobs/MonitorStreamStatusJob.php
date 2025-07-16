<?php

namespace App\Jobs;

use App\Models\StreamConfiguration;
use App\Models\VpsServer;
use App\Services\Vps\VpsConnection;
use App\Services\Vps\VpsMonitor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class MonitorStreamStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        //
    }

    /**
     * Monitor all streams and update their real status
     */
    public function handle(VpsConnection $vpsConnection, VpsMonitor $vpsMonitor): void
    {
        Log::info("Starting stream status monitoring");

        // Get all streams that claim to be streaming
        $streamingStreams = StreamConfiguration::whereIn('status', ['STREAMING', 'STARTING'])->get();

        foreach ($streamingStreams as $stream) {
            $this->checkStreamStatus($stream, $vpsConnection, $vpsMonitor);
        }

        Log::info("Stream status monitoring completed", [
            'checked_streams' => $streamingStreams->count()
        ]);
    }

    private function checkStreamStatus(StreamConfiguration $stream, VpsConnection $vpsConnection, VpsMonitor $vpsMonitor): void
    {
        try {
            if (!$stream->vps_server_id) {
                Log::warning("Stream has no VPS assigned", ['stream_id' => $stream->id]);
                $stream->update(['status' => 'ERROR', 'error_message' => 'No VPS assigned']);
                return;
            }

            $vps = VpsServer::find($stream->vps_server_id);
            if (!$vps || $vps->status !== 'ACTIVE') {
                Log::warning("Stream VPS is not active", [
                    'stream_id' => $stream->id,
                    'vps_id' => $stream->vps_server_id,
                    'vps_status' => $vps->status ?? 'NOT_FOUND'
                ]);
                $stream->update(['status' => 'ERROR', 'error_message' => 'VPS not active']);
                return;
            }

            // Check VPS health first
            if (!$vpsMonitor->isHealthy($vps)) {
                Log::warning("Stream VPS is not healthy", [
                    'stream_id' => $stream->id,
                    'vps_id' => $vps->id
                ]);
                $stream->update(['status' => 'ERROR', 'error_message' => 'VPS is not healthy']);
                return;
            }

            // Check if manager is running on VPS
            if (!$vpsConnection->isManagerRunning($vps)) {
                Log::warning("Stream manager not running on VPS", [
                    'stream_id' => $stream->id,
                    'vps_id' => $vps->id
                ]);
                $stream->update(['status' => 'ERROR', 'error_message' => 'Stream manager not running on VPS']);
                return;
            }

            // For streams older than 10 minutes without webhook updates, mark as potentially failed
            if ($stream->updated_at->lt(now()->subMinutes(10)) && $stream->status === 'STREAMING') {
                Log::warning("Stream has not been updated via webhook for 10+ minutes", [
                    'stream_id' => $stream->id,
                    'last_update' => $stream->updated_at
                ]);

                // Don't immediately mark as error, but log for investigation
                // The webhook system should handle status updates
            }

        } catch (\Exception $e) {
            Log::error("Error monitoring stream status", [
                'stream_id' => $stream->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
