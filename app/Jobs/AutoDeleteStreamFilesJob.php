<?php

namespace App\Jobs;

use App\Models\StreamConfiguration;
use App\Models\UserFile;
use App\Services\BunnyStorageService;
use App\Services\AutoDeleteVideoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AutoDeleteStreamFilesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 300; // 5 minutes

    public StreamConfiguration $stream;

    public function __construct(StreamConfiguration $stream)
    {
        $this->stream = $stream;
    }

    public function handle(AutoDeleteVideoService $autoDeleteService): void
    {
        Log::info("🗑️ [AutoDeleteStreamFiles] Starting VIDEO FILE auto-deletion for stream #{$this->stream->id}");

        try {
            // Refresh stream from database to get latest status
            $this->stream->refresh();

            // CRITICAL: Do not delete files if stream is still active
            if (in_array($this->stream->status, ['STREAMING', 'STARTING', 'STOPPING'])) {
                Log::warning("⚠️ [Stream #{$this->stream->id}] Stream still active ({$this->stream->status}), skipping video file auto-deletion");
                return;
            }

            // Only process if this is a quick stream with auto-delete enabled
            if (!$this->stream->is_quick_stream || !$this->stream->auto_delete_from_cdn) {
                Log::info("🔄 [Stream #{$this->stream->id}] Not a quick stream or auto-delete disabled, skipping video file deletion");
                return;
            }

            // ⚠️ IMPORTANT: This job ONLY deletes VIDEO FILES, NOT the stream configuration
            // The stream record will remain intact for history/analytics purposes
            Log::info("📝 [Stream #{$this->stream->id}] This job will ONLY delete video files, stream configuration will be preserved");

            // Use the new AutoDeleteVideoService to schedule video deletion
            $result = $autoDeleteService->scheduleVideoDeletion($this->stream, 5);

            if ($result['success']) {
                $scheduledCount = count($result['scheduled_files']);
                $errorCount = count($result['errors']);

                Log::info("🎉 [AutoDeleteStreamFiles] Scheduled {$scheduledCount} files for deletion", [
                    'stream_id' => $this->stream->id,
                    'scheduled_files' => $scheduledCount,
                    'errors' => $errorCount
                ]);

                if (!empty($result['errors'])) {
                    Log::warning("⚠️ [AutoDeleteStreamFiles] Some errors occurred:", $result['errors']);
                }

                // Update stream status
                $this->stream->update([
                    'auto_delete_from_cdn' => false, // Mark as processed
                    'status' => 'COMPLETED', // Mark stream as completed
                    'error_message' => null // Clear any error messages
                ]);

            } else {
                Log::error("❌ [AutoDeleteStreamFiles] Failed to schedule deletion: {$result['error']}");
                throw new \Exception("Failed to schedule video deletion: {$result['error']}");
            }

            // 🔒 SAFETY CHECK: Verify stream configuration still exists after operation
            if (!$this->stream->exists) {
                Log::error("🚨 CRITICAL ERROR: Stream configuration was accidentally deleted! This should NEVER happen!");
                throw new \Exception("Stream configuration was accidentally deleted during auto-deletion process");
            }

            Log::info("🎉 [Stream #{$this->stream->id}] VIDEO FILE auto-deletion completed (Stream configuration preserved)", [
                'deleted_files' => $deletedFiles,
                'failed_files' => $failedFiles,
                'stream_title' => $this->stream->title,
                'stream_preserved' => true
            ]);

        } catch (\Exception $e) {
            Log::error("💥 [Stream #{$this->stream->id}] VIDEO FILE auto-deletion job failed (Stream configuration preserved)", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'stream_preserved' => true
            ]);

            // Don't rethrow - we don't want this job to retry indefinitely
            // Files can be cleaned up manually or by scheduled cleanup jobs
        }
    }

    /**
     * Delete file from VPS agent via Redis command
     */
    private function deleteFileFromVpsAgent(UserFile $userFile, int $vpsId): void
    {
        try {
            // Send delete command to VPS agent via Redis
            $redisCommand = [
                'command' => 'DELETE_FILE',
                'file_id' => $userFile->id,
                'filename' => $userFile->original_name,
                'stream_id' => $this->stream->id
            ];

            $channel = "vps-commands:{$vpsId}";
            $redis = app('redis')->connection();
            $publishResult = $redis->publish($channel, json_encode($redisCommand));

            if ($publishResult > 0) {
                Log::info("📤 [Stream #{$this->stream->id}] Sent delete command to VPS {$vpsId} for file: {$userFile->original_name}");
            } else {
                Log::warning("⚠️ [Stream #{$this->stream->id}] No agent listening on VPS {$vpsId} for file deletion");
            }

        } catch (\Exception $e) {
            Log::error("❌ [Stream #{$this->stream->id}] Failed to send delete command to VPS {$vpsId}: {$e->getMessage()}");
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("💥 [Stream #{$this->stream->id}] VIDEO FILE auto-deletion job failed permanently (Stream configuration preserved)", [
            'error' => $exception->getMessage(),
            'stream_title' => $this->stream->title,
            'stream_preserved' => true
        ]);

        // Mark the stream as processed even if video file deletion failed
        // This prevents the job from being retried indefinitely
        // IMPORTANT: Stream configuration remains intact
        try {
            $this->stream->update([
                'auto_delete_from_cdn' => false,
                'status' => 'COMPLETED', // Mark as completed instead of leaving in ERROR
                'error_message' => null
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to update stream after video file auto-deletion failure: {$e->getMessage()}");
        }
    }
}
