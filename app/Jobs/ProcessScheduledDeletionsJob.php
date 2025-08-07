<?php

namespace App\Jobs;

use App\Models\UserFile;
use App\Models\StreamConfiguration;
use App\Services\AutoDeleteVideoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessScheduledDeletionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 600; // 10 minutes

    public function __construct()
    {
        //
    }

    public function handle(AutoDeleteVideoService $autoDeleteService): void
    {
        Log::info("🗑️ [ProcessScheduledDeletions] Starting scheduled file deletion check");

        try {
            // Use the service to process scheduled deletions
            $result = $autoDeleteService->processScheduledDeletions();

            if ($result['success']) {
                Log::info("🎉 [ProcessScheduledDeletions] Completed successfully", [
                    'processed' => $result['processed'],
                    'deleted' => $result['deleted'],
                    'errors' => count($result['errors'])
                ]);

                if (!empty($result['errors'])) {
                    Log::warning("⚠️ [ProcessScheduledDeletions] Some errors occurred:", $result['errors']);
                }
            } else {
                Log::error("❌ [ProcessScheduledDeletions] Failed: {$result['error']}");
                throw new \Exception("Failed to process scheduled deletions: {$result['error']}");
            }

        } catch (\Exception $e) {
            Log::error("💥 [ProcessScheduledDeletions] Job failed: {$e->getMessage()}");
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("💥 [ProcessScheduledDeletions] Job failed permanently: {$exception->getMessage()}");
    }
}
