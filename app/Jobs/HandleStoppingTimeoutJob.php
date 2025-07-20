<?php

namespace App\Jobs;

use App\Models\StreamConfiguration;
use App\Services\StreamProgressService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class HandleStoppingTimeoutJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;
    public $timeout = 60;

    public function handle(): void
    {
        Log::info("⏰ [StoppingTimeout] Starting timeout check for STOPPING streams");

        // Find all streams stuck in STOPPING status
        $stoppingStreams = StreamConfiguration::where('status', 'STOPPING')
            ->get();

        if ($stoppingStreams->isEmpty()) {
            Log::debug("⏰ [StoppingTimeout] No streams in STOPPING status");
            return;
        }

        $timeoutCount = 0;
        $checkedCount = 0;

        foreach ($stoppingStreams as $stream) {
            $checkedCount++;
            
            $timeSinceStop = $stream->last_stopped_at ?
                abs(now()->diffInMinutes($stream->last_stopped_at)) :
                abs(now()->diffInMinutes($stream->updated_at));

            Log::info("⏱️ [StoppingTimeout] Checking stream #{$stream->id} - {$timeSinceStop} minutes since stop command", [
                'stream_id' => $stream->id,
                'title' => $stream->title,
                'status' => $stream->status,
                'last_stopped_at' => $stream->last_stopped_at,
                'vps_server_id' => $stream->vps_server_id,
                'minutes_since_stop' => $timeSinceStop
            ]);

            // If STOPPING for more than 2 minutes, assume agent failed to respond
            if ($timeSinceStop > 2) {
                $timeoutCount++;
                
                Log::warning("⏰ [StoppingTimeout] Stream #{$stream->id} stuck in STOPPING for {$timeSinceStop} minutes, forcing to INACTIVE");

                $originalVpsId = $stream->vps_server_id;
                
                $stream->update([
                    'status' => 'INACTIVE',
                    'error_message' => "Stop command timeout after {$timeSinceStop} minutes - agent may be offline",
                    'vps_server_id' => null,
                    'process_id' => null,
                    'last_stopped_at' => now() // Update to current time
                ]);

                // Create progress update for UI
                StreamProgressService::createStageProgress(
                    $stream->id, 
                    'stopped', 
                    "⏰ Stream dừng do timeout (agent không phản hồi sau {$timeSinceStop} phút)"
                );

                // Decrement VPS stream count
                if ($originalVpsId && $stream->vpsServer) {
                    $stream->vpsServer->decrement('current_streams');
                    Log::info("📉 [StoppingTimeout] Decremented stream count for VPS #{$originalVpsId}");
                }

                Log::warning("✅ [StoppingTimeout] Stream #{$stream->id} forced to INACTIVE after {$timeSinceStop} minutes", [
                    'stream_id' => $stream->id,
                    'title' => $stream->title,
                    'original_vps_id' => $originalVpsId,
                    'timeout_minutes' => $timeSinceStop,
                    'reason' => 'Agent did not confirm stop command'
                ]);
            }
        }

        Log::info("✅ [StoppingTimeout] Timeout check completed", [
            'checked_streams' => $checkedCount,
            'timeout_streams' => $timeoutCount,
            'remaining_stopping' => $checkedCount - $timeoutCount
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("❌ [StoppingTimeout] Job failed", [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}
