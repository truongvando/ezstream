<?php

namespace App\Jobs;

use App\Models\StreamConfiguration;
use App\Models\UserFile;
use App\Services\BunnyStorageService;
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

    public function handle(BunnyStorageService $bunnyService): void
    {
        Log::info("🗑️ [AutoDeleteStreamFiles] Starting auto-deletion for stream #{$this->stream->id}");

        try {
            // Refresh stream from database to get latest status
            $this->stream->refresh();

            // CRITICAL: Do not delete files if stream is still active
            if (in_array($this->stream->status, ['STREAMING', 'STARTING', 'STOPPING'])) {
                Log::warning("⚠️ [Stream #{$this->stream->id}] Stream still active ({$this->stream->status}), skipping auto-deletion");
                return;
            }

            // Only process if this is a quick stream with auto-delete enabled
            if (!$this->stream->is_quick_stream || !$this->stream->auto_delete_from_cdn) {
                Log::info("🔄 [Stream #{$this->stream->id}] Not a quick stream or auto-delete disabled, skipping");
                return;
            }

            $deletedFiles = 0;
            $failedFiles = 0;
            $videoSourcePath = $this->stream->video_source_path ?? [];

            foreach ($videoSourcePath as $fileInfo) {
                $userFile = UserFile::find($fileInfo['file_id']);
                if (!$userFile) {
                    Log::warning("📁 [Stream #{$this->stream->id}] File not found: {$fileInfo['file_id']}");
                    continue;
                }

                // Only delete files marked for auto-deletion
                if (!$userFile->auto_delete_after_stream) {
                    Log::info("📁 [Stream #{$this->stream->id}] File {$userFile->id} not marked for auto-deletion, skipping");
                    continue;
                }

                try {
                    $fileName = $userFile->original_name;
                    $fileSize = $userFile->size;

                    // 1. Delete from BunnyCDN
                    if ($userFile->disk === 'bunny_cdn' && $userFile->path) {
                        $result = $bunnyService->deleteFile($userFile->path);
                        if ($result['success']) {
                            Log::info("✅ [Stream #{$this->stream->id}] Deleted from CDN: {$userFile->path}");
                        } else {
                            Log::warning("⚠️ [Stream #{$this->stream->id}] Failed to delete from CDN: {$result['error']}");
                        }
                    }

                    // 2. Delete from VPS Agent (if stream was running)
                    if ($this->stream->vps_server_id) {
                        $this->deleteFileFromVpsAgent($userFile, $this->stream->vps_server_id);
                    }

                    // 3. Delete database record
                    $userFile->delete();

                    $deletedFiles++;
                    Log::info("🗑️ [Stream #{$this->stream->id}] Deleted file completely: {$fileName} ({$fileSize} bytes)");

                } catch (\Exception $e) {
                    $failedFiles++;
                    Log::error("❌ [Stream #{$this->stream->id}] Failed to delete file {$userFile->id}: {$e->getMessage()}");
                }
            }

            // Update stream to mark auto-deletion as completed
            $this->stream->update([
                'auto_delete_from_cdn' => false, // Mark as processed
            ]);

            Log::info("🎉 [Stream #{$this->stream->id}] Auto-deletion completed", [
                'deleted_files' => $deletedFiles,
                'failed_files' => $failedFiles,
                'stream_title' => $this->stream->title
            ]);

        } catch (\Exception $e) {
            Log::error("💥 [Stream #{$this->stream->id}] Auto-deletion job failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
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
        Log::error("💥 [Stream #{$this->stream->id}] Auto-deletion job failed permanently", [
            'error' => $exception->getMessage(),
            'stream_title' => $this->stream->title
        ]);

        // Mark the stream as processed even if deletion failed
        // This prevents the job from being retried indefinitely
        try {
            $this->stream->update(['auto_delete_from_cdn' => false]);
        } catch (\Exception $e) {
            Log::error("Failed to update stream after auto-deletion failure: {$e->getMessage()}");
        }
    }
}
