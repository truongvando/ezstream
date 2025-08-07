<?php

namespace App\Services;

use App\Models\UserFile;
use App\Models\StreamConfiguration;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Exception;
use Carbon\Carbon;

class AutoDeleteVideoService
{
    private BunnyStreamService $bunnyStreamService;
    private BunnyStorageService $bunnyStorageService;

    public function __construct(
        BunnyStreamService $bunnyStreamService,
        BunnyStorageService $bunnyStorageService
    ) {
        $this->bunnyStreamService = $bunnyStreamService;
        $this->bunnyStorageService = $bunnyStorageService;
    }

    /**
     * Schedule video deletion after stream completion
     */
    public function scheduleVideoDeletion(StreamConfiguration $stream, int $delayMinutes = 5): array
    {
        try {
            $videoSourcePath = $stream->video_source_path;
            if (!is_array($videoSourcePath)) {
                Log::warning("🗑️ [AutoDeleteVideo] Invalid video_source_path for stream #{$stream->id}");
                return ['success' => false, 'error' => 'Invalid video source path'];
            }

            $scheduledFiles = [];
            $errors = [];

            foreach ($videoSourcePath as $fileInfo) {
                $userFile = UserFile::find($fileInfo['file_id']);
                if (!$userFile) {
                    $errors[] = "File not found: {$fileInfo['file_id']}";
                    continue;
                }

                // Only schedule files marked for auto-deletion
                if (!$userFile->auto_delete_after_stream) {
                    Log::info("🗑️ [AutoDeleteVideo] File {$userFile->id} not marked for auto-deletion, skipping");
                    continue;
                }

                // Check if file is still being used by other active streams
                if ($this->isFileInUseByActiveStreams($userFile, $stream->id)) {
                    Log::info("🗑️ [AutoDeleteVideo] File {$userFile->id} still in use by other streams, postponing deletion");
                    $userFile->update(['scheduled_deletion_at' => now()->addHours(1)]);
                    continue;
                }

                // Schedule deletion
                $deletionTime = now()->addMinutes($delayMinutes);
                $userFile->update(['scheduled_deletion_at' => $deletionTime]);
                
                $scheduledFiles[] = [
                    'file_id' => $userFile->id,
                    'filename' => $userFile->original_name,
                    'scheduled_at' => $deletionTime->toISOString()
                ];

                Log::info("🗑️ [AutoDeleteVideo] Scheduled deletion for file {$userFile->id} at {$deletionTime}");
            }

            return [
                'success' => true,
                'scheduled_files' => $scheduledFiles,
                'errors' => $errors
            ];

        } catch (Exception $e) {
            Log::error("❌ [AutoDeleteVideo] Failed to schedule deletion for stream #{$stream->id}: {$e->getMessage()}");
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Process all videos scheduled for deletion
     */
    public function processScheduledDeletions(): array
    {
        try {
            $filesToDelete = UserFile::where('auto_delete_after_stream', true)
                ->whereNotNull('scheduled_deletion_at')
                ->where('scheduled_deletion_at', '<=', now())
                ->where('status', '!=', 'DELETED')
                ->get();

            if ($filesToDelete->isEmpty()) {
                Log::info("🗑️ [AutoDeleteVideo] No files scheduled for deletion");
                return ['success' => true, 'processed' => 0, 'deleted' => 0, 'errors' => []];
            }

            Log::info("🗑️ [AutoDeleteVideo] Processing {$filesToDelete->count()} scheduled deletions");

            $deleted = 0;
            $errors = [];

            foreach ($filesToDelete as $file) {
                try {
                    // Double-check if file is still in use
                    if ($this->isFileInUseByActiveStreams($file)) {
                        Log::warning("⚠️ [AutoDeleteVideo] File {$file->id} still in use, postponing deletion");
                        $file->update(['scheduled_deletion_at' => now()->addHour()]);
                        continue;
                    }

                    $result = $this->deleteVideoFromAllSources($file);
                    if ($result['success']) {
                        $deleted++;
                        Log::info("✅ [AutoDeleteVideo] Successfully deleted file {$file->id}");
                    } else {
                        $errors[] = "Failed to delete file {$file->id}: {$result['error']}";
                    }

                } catch (Exception $e) {
                    $errors[] = "Exception deleting file {$file->id}: {$e->getMessage()}";
                    Log::error("❌ [AutoDeleteVideo] Exception processing file {$file->id}: {$e->getMessage()}");
                }
            }

            return [
                'success' => true,
                'processed' => $filesToDelete->count(),
                'deleted' => $deleted,
                'errors' => $errors
            ];

        } catch (Exception $e) {
            Log::error("❌ [AutoDeleteVideo] Failed to process scheduled deletions: {$e->getMessage()}");
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Delete video from all sources (Bunny Stream, CDN, VPS, Database)
     */
    public function deleteVideoFromAllSources(UserFile $file): array
    {
        $deletionResults = [];
        $errors = [];

        try {
            Log::info("🗑️ [AutoDeleteVideo] Starting deletion for file {$file->id} ({$file->original_name})");

            // 1. Delete from Bunny Stream Library if applicable
            if ($file->stream_video_id) {
                $result = $this->bunnyStreamService->deleteVideo($file->stream_video_id);
                $deletionResults['bunny_stream'] = $result;
                
                if ($result['success']) {
                    Log::info("✅ [AutoDeleteVideo] Deleted from Bunny Stream: {$file->stream_video_id}");
                } else {
                    $errors[] = "Bunny Stream deletion failed: {$result['error']}";
                    Log::warning("⚠️ [AutoDeleteVideo] Bunny Stream deletion failed: {$result['error']}");
                }
            }

            // 2. Delete from Bunny CDN if applicable
            if ($file->disk === 'bunny_cdn' && $file->path) {
                $result = $this->bunnyStorageService->deleteFile($file->path);
                $deletionResults['bunny_cdn'] = $result;
                
                if ($result['success']) {
                    Log::info("✅ [AutoDeleteVideo] Deleted from Bunny CDN: {$file->path}");
                } else {
                    $errors[] = "Bunny CDN deletion failed: {$result['message']}";
                    Log::warning("⚠️ [AutoDeleteVideo] Bunny CDN deletion failed: {$result['message']}");
                }
            }

            // 3. Delete from VPS agents if file was distributed
            if ($file->storage_locations && is_array($file->storage_locations)) {
                foreach ($file->storage_locations as $vpsId) {
                    $result = $this->deleteFileFromVpsAgent($file, $vpsId);
                    $deletionResults["vps_{$vpsId}"] = $result;
                }
            }

            // 4. Update database record
            $file->update([
                'status' => 'DELETED',
                'deleted_at' => now(),
                'auto_delete_after_stream' => false,
                'scheduled_deletion_at' => null
            ]);

            Log::info("🎉 [AutoDeleteVideo] Successfully deleted file {$file->id} from all sources");

            return [
                'success' => true,
                'deletion_results' => $deletionResults,
                'errors' => $errors
            ];

        } catch (Exception $e) {
            Log::error("❌ [AutoDeleteVideo] Exception deleting file {$file->id}: {$e->getMessage()}");
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'deletion_results' => $deletionResults
            ];
        }
    }

    /**
     * Check if file is still being used by active streams
     */
    private function isFileInUseByActiveStreams(UserFile $file, int $excludeStreamId = null): bool
    {
        $activeStatuses = ['STREAMING', 'STARTING', 'STOPPING'];
        
        $query = StreamConfiguration::whereJsonContains('video_source_path', [['file_id' => $file->id]])
            ->whereIn('status', $activeStatuses);
            
        if ($excludeStreamId) {
            $query->where('id', '!=', $excludeStreamId);
        }
        
        return $query->exists();
    }

    /**
     * Delete file from VPS agent via Redis command
     */
    private function deleteFileFromVpsAgent(UserFile $file, int $vpsId): array
    {
        try {
            $redisCommand = [
                'command' => 'DELETE_FILE',
                'file_id' => $file->id,
                'filename' => $file->original_name,
                'timestamp' => now()->toISOString()
            ];

            $channel = "vps-commands:{$vpsId}";
            $redis = Redis::connection();
            $publishResult = $redis->publish($channel, json_encode($redisCommand));

            if ($publishResult > 0) {
                Log::info("📤 [AutoDeleteVideo] Sent delete command to VPS {$vpsId} for file: {$file->original_name}");
                return ['success' => true, 'subscribers' => $publishResult];
            } else {
                Log::warning("⚠️ [AutoDeleteVideo] No agent listening on VPS {$vpsId} for file deletion");
                return ['success' => false, 'error' => 'No agent listening'];
            }

        } catch (Exception $e) {
            Log::error("❌ [AutoDeleteVideo] Failed to send delete command to VPS {$vpsId}: {$e->getMessage()}");
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get videos scheduled for deletion
     */
    public function getVideosScheduledForDeletion(int $limit = 100): array
    {
        $files = UserFile::where('auto_delete_after_stream', true)
            ->whereNotNull('scheduled_deletion_at')
            ->where('status', '!=', 'DELETED')
            ->orderBy('scheduled_deletion_at')
            ->limit($limit)
            ->get();

        return $files->map(function ($file) {
            return [
                'id' => $file->id,
                'filename' => $file->original_name,
                'size' => $file->size,
                'scheduled_at' => $file->scheduled_deletion_at,
                'is_overdue' => $file->scheduled_deletion_at <= now(),
                'disk' => $file->disk,
                'stream_video_id' => $file->stream_video_id
            ];
        })->toArray();
    }

    /**
     * Cancel scheduled deletion for a file
     */
    public function cancelScheduledDeletion(UserFile $file): bool
    {
        try {
            $file->update([
                'auto_delete_after_stream' => false,
                'scheduled_deletion_at' => null
            ]);

            Log::info("🚫 [AutoDeleteVideo] Cancelled scheduled deletion for file {$file->id}");
            return true;

        } catch (Exception $e) {
            Log::error("❌ [AutoDeleteVideo] Failed to cancel deletion for file {$file->id}: {$e->getMessage()}");
            return false;
        }
    }
}
