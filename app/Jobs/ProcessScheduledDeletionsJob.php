<?php

namespace App\Jobs;

use App\Models\UserFile;
use App\Models\StreamConfiguration;
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

    public function handle(): void
    {
        Log::info("🗑️ [ProcessScheduledDeletions] Starting scheduled file deletion check");

        try {
            // Find files scheduled for deletion that are past their deletion time
            $filesToDelete = UserFile::where('auto_delete_after_stream', true)
                ->where('scheduled_deletion_at', '<=', now())
                ->whereNotNull('scheduled_deletion_at')
                ->get();

            if ($filesToDelete->isEmpty()) {
                Log::info("🗑️ [ProcessScheduledDeletions] No files scheduled for deletion");
                return;
            }

            Log::info("🗑️ [ProcessScheduledDeletions] Found {$filesToDelete->count()} files to process");

            $processedStreams = [];
            $deletedFiles = 0;
            $skippedFiles = 0;

            foreach ($filesToDelete as $file) {
                try {
                    // Find streams using this file
                    $streams = StreamConfiguration::where('video_source_path', 'like', '%"file_id":' . $file->id . '%')
                        ->where('is_quick_stream', true)
                        ->where('auto_delete_from_cdn', true)
                        ->get();

                    if ($streams->isEmpty()) {
                        Log::info("📁 [ProcessScheduledDeletions] File {$file->id} not used by any quick streams, skipping");
                        $skippedFiles++;
                        continue;
                    }

                    // Check if any stream using this file is still active
                    $activeStreams = $streams->whereIn('status', ['STREAMING', 'STARTING', 'STOPPING']);
                    if ($activeStreams->isNotEmpty()) {
                        Log::warning("⚠️ [ProcessScheduledDeletions] File {$file->id} still used by active streams, postponing deletion");
                        
                        // Postpone deletion by 1 hour
                        $file->update(['scheduled_deletion_at' => now()->addHour()]);
                        $skippedFiles++;
                        continue;
                    }

                    // Process each stream that uses this file
                    foreach ($streams as $stream) {
                        if (!in_array($stream->id, $processedStreams)) {
                            Log::info("🗑️ [ProcessScheduledDeletions] Processing Quick Stream #{$stream->id}");
                            
                            // Dispatch auto-delete job for the stream
                            AutoDeleteStreamFilesJob::dispatch($stream);
                            
                            $processedStreams[] = $stream->id;
                        }
                    }

                    $deletedFiles++;

                } catch (\Exception $e) {
                    Log::error("❌ [ProcessScheduledDeletions] Failed to process file {$file->id}: {$e->getMessage()}");
                }
            }

            Log::info("🎉 [ProcessScheduledDeletions] Completed", [
                'processed_streams' => count($processedStreams),
                'deleted_files' => $deletedFiles,
                'skipped_files' => $skippedFiles
            ]);

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
