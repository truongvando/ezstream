<?php

namespace App\Jobs;

use App\Services\Stream\StreamAllocation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessStreamQueueJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 60;

    /**
     * Execute the job.
     */
    public function handle(StreamAllocation $streamAllocation): void
    {
        Log::info("🚦 [QueueProcessor] Processing stream queue");

        try {
            // Get queue status before processing
            $queueStatus = $streamAllocation->getQueueStatus();
            
            if ($queueStatus['total_queued'] > 0) {
                Log::info("📋 [QueueProcessor] Queue status: {$queueStatus['total_queued']} streams waiting");
                
                // Process the queue
                $streamAllocation->processQueue();
                
                // Get status after processing
                $newQueueStatus = $streamAllocation->getQueueStatus();
                $processed = $queueStatus['total_queued'] - $newQueueStatus['total_queued'];
                
                if ($processed > 0) {
                    Log::info("✅ [QueueProcessor] Processed {$processed} streams from queue");
                } else {
                    Log::debug("⏸️ [QueueProcessor] No streams could be processed (VPS capacity full)");
                }
            } else {
                Log::debug("📭 [QueueProcessor] Queue is empty");
            }

        } catch (\Exception $e) {
            Log::error("❌ [QueueProcessor] Failed to process queue", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}
