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

class UpdateStreamStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 30;

    protected array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function handle(): void
    {
        // Throttle concurrent status updates to prevent DB connection exhaustion
        if (!\App\Services\DatabaseConnectionManager::throttleJob(self::class)) {
            // Retry later if too many concurrent jobs
            $this->release(30); // Retry in 30 seconds
            return;
        }

        $streamId = $this->data['stream_id'] ?? null;
        $status = $this->data['status'] ?? null;
        $message = $this->data['message'] ?? '';
        $vpsId = $this->data['vps_id'] ?? null;

        if (!$streamId) {
            Log::warning("📨 [UpdateStreamStatus] Missing stream_id in data", $this->data);
            return;
        }

        Log::info("📨 [UpdateStreamStatus] Processing status update for stream #{$streamId}", [
            'status' => $status,
            'message' => $message,
            'vps_id' => $vpsId,
            'full_data' => $this->data
        ]);

        $stream = StreamConfiguration::find($streamId);
        if (!$stream) {
            Log::warning("📨 [UpdateStreamStatus] Stream #{$streamId} not found in database");
            return;
        }

        // Handle different status types
        switch ($status) {
            case 'RUNNING':
            case 'STREAMING':
                Log::info("📨 [UpdateStreamStatus] Handling STREAMING status for stream #{$streamId} (current: {$stream->status})");
                $this->handleStreamingStatus($stream, $vpsId, $message);
                break;

            case 'STOPPED':
                Log::info("📨 [UpdateStreamStatus] Handling STOPPED status for stream #{$streamId} (current: {$stream->status})");
                $this->handleStoppedStatus($stream, $message);
                break;

            case 'ERROR':
                Log::info("📨 [UpdateStreamStatus] Handling ERROR status for stream #{$streamId} (current: {$stream->status})");
                $this->handleErrorStatus($stream, $message);
                break;

            case 'STARTING':
                Log::info("📨 [UpdateStreamStatus] Handling STARTING status for stream #{$streamId} (current: {$stream->status})");
                $this->handleStartingStatus($stream, $vpsId, $message);
                break;

            case 'PROGRESS':
                Log::info("📨 [UpdateStreamStatus] Handling PROGRESS status for stream #{$streamId} (current: {$stream->status})");
                $this->handleProgressStatus($stream, $vpsId, $message);
                break;

            case 'DEAD':
                Log::warning("📨 [UpdateStreamStatus] Handling DEAD status for stream #{$streamId} (current: {$stream->status})");
                $this->handleDeadStatus($stream, $message);
                break;

            default:
                Log::warning("📨 [UpdateStreamStatus] Unknown status '{$status}' for stream #{$streamId}");
                break;
        }
    }

    private function handleStreamingStatus(StreamConfiguration $stream, $vpsId, $message): void
    {
        if ($stream->status !== 'STREAMING') {
            Log::info("📨 [UpdateStreamStatus] Stream #{$stream->id} status: {$stream->status} → STREAMING");
            
            $stream->update([
                'status' => 'STREAMING',
                'last_started_at' => now(),
                'last_status_update' => now(),
                'error_message' => null,
                'vps_server_id' => $vpsId
            ]);

            // Increment VPS stream count
            if ($vpsId) {
                VpsServer::find($vpsId)?->increment('current_streams');
            }

            StreamProgressService::createStageProgress($stream->id, 'streaming', 'Stream đang phát trực tiếp!');
        } else {
            // Just update timestamp for existing streaming streams
            $stream->update(['last_status_update' => now()]);
        }
    }

    private function handleStoppedStatus(StreamConfiguration $stream, $message): void
    {
        Log::info("📨 [UpdateStreamStatus] Stream #{$stream->id} status: {$stream->status} → INACTIVE (STOPPED)");

        $originalVpsId = $stream->vps_server_id;

        $stream->update([
            'status' => 'INACTIVE',
            'last_stopped_at' => now(),
            'last_status_update' => now(),
            'vps_server_id' => null,
            'process_id' => null,
            'error_message' => null
        ]);

        // Decrement VPS stream count
        if ($originalVpsId) {
            VpsServer::find($originalVpsId)?->decrement('current_streams');
        }

        StreamProgressService::createStageProgress($stream->id, 'stopped', $message ?: 'Stream đã dừng');

        // 🗑️ AUTO-DELETE: Check if stream has auto-delete enabled
        $this->handleAutoDeleteFiles($stream);

        // 🗑️ QUICK STREAM AUTO-DELETE: Trigger VIDEO FILE deletion after stream stops
        // ⚠️ IMPORTANT: This ONLY deletes video files, NOT the stream configuration
        if ($stream->is_quick_stream && $stream->auto_delete_from_cdn) {
            Log::info("🗑️ [UpdateStreamStatus] Scheduling VIDEO FILE auto-deletion for Quick Stream #{$stream->id} (Stream configuration will be preserved)");

            // Delay deletion by 5 minutes to ensure stream is fully stopped
            \App\Jobs\AutoDeleteStreamFilesJob::dispatch($stream)->delay(now()->addMinutes(5));
        }
    }

    private function handleErrorStatus(StreamConfiguration $stream, $message): void
    {
        Log::warning("📨 [UpdateStreamStatus] Stream #{$stream->id} status: {$stream->status} → ERROR");

        $originalVpsId = $stream->vps_server_id;

        // Check if this is a file-related error
        $isFileError = str_contains($message, 'No files were downloaded') ||
                      str_contains($message, 'files may have been deleted') ||
                      str_contains($message, 'FILE_NOT_FOUND');

        $stream->update([
            'status' => 'ERROR',
            'error_message' => $message,
            'last_status_update' => now(),
            'vps_server_id' => null,
            'process_id' => null
        ]);

        // Decrement VPS stream count
        if ($originalVpsId) {
            VpsServer::find($originalVpsId)?->decrement('current_streams');
        }

        // Create appropriate progress message
        $progressMessage = $isFileError ?
            '❌ Files không tồn tại hoặc đã bị xóa. Vui lòng kiểm tra lại files.' :
            ($message ?: 'Stream gặp lỗi');

        StreamProgressService::createStageProgress($stream->id, 'error', $progressMessage);

        // Log file errors for debugging
        if ($isFileError) {
            Log::warning("📁 [UpdateStreamStatus] File-related error for stream #{$stream->id}: {$message}");
        }
    }

    private function handleDeadStatus(StreamConfiguration $stream, $message): void
    {
        Log::error("📨 [UpdateStreamStatus] Stream #{$stream->id} status: {$stream->status} → ERROR (DEAD)");

        $originalVpsId = $stream->vps_server_id;

        // DEAD status means all pipeline stages have terminated permanently
        // This is more severe than regular ERROR - agent has given up on the stream
        $stream->update([
            'status' => 'ERROR',
            'error_message' => $message ?: 'Stream died - all pipeline stages terminated',
            'last_status_update' => now(),
            'last_stopped_at' => now(),
            'vps_server_id' => null,
            'process_id' => null
        ]);

        // Decrement VPS stream count
        if ($originalVpsId) {
            VpsServer::find($originalVpsId)?->decrement('current_streams');
        }

        // Create progress message indicating stream death
        StreamProgressService::createStageProgress(
            $stream->id,
            'error',
            '💀 ' . ($message ?: 'Stream đã chết - tất cả pipeline stages đã dừng')
        );

        Log::error("💀 [UpdateStreamStatus] Stream #{$stream->id} marked as DEAD by agent: {$message}");
    }

    private function handleStartingStatus(StreamConfiguration $stream, $vpsId, $message): void
    {
        if ($stream->status !== 'STARTING') {
            Log::info("📨 [UpdateStreamStatus] Stream #{$stream->id} status: {$stream->status} → STARTING");
            
            $stream->update([
                'status' => 'STARTING',
                'last_started_at' => now(),
                'last_status_update' => now(),
                'vps_server_id' => $vpsId,
                'error_message' => null
            ]);

            StreamProgressService::createStageProgress($stream->id, 'starting', $message ?: 'Stream đang khởi động...');
        } else {
            // Just update timestamp
            $stream->update(['last_status_update' => now()]);
        }
    }

    private function handleProgressStatus(StreamConfiguration $stream, $vpsId, $message): void
    {
        $extraData = $this->data['extra_data'] ?? [];
        $progressData = $extraData['progress_data'] ?? [];

        $stage = $progressData['stage'] ?? 'processing';
        $progressPercentage = $progressData['progress_percentage'] ?? 0;
        $details = $progressData['details'] ?? [];

        Log::info("📊 [UpdateStreamStatus] Stream #{$stream->id} progress: {$stage} ({$progressPercentage}%)", [
            'message' => $message,
            'stage' => $stage,
            'progress' => $progressPercentage,
            'current_status' => $stream->status
        ]);

        // PROGRESS should NOT override higher status levels
        // Status hierarchy: INACTIVE < STARTING < STREAMING
        $allowedToSetStarting = in_array($stream->status, ['INACTIVE', 'STOPPED', 'ERROR']);

        if ($allowedToSetStarting) {
            Log::info("📊 [UpdateStreamStatus] Setting status to STARTING for stream #{$stream->id} (was: {$stream->status})");
            $stream->update([
                'status' => 'STARTING',
                'last_status_update' => now(),
                'vps_server_id' => $vpsId
            ]);
        } else {
            // Don't change status, just update timestamp and VPS if needed
            Log::info("📊 [UpdateStreamStatus] Keeping current status '{$stream->status}' for stream #{$stream->id}, only updating progress");
            $updateData = ['last_status_update' => now()];
            if ($vpsId && !$stream->vps_server_id) {
                $updateData['vps_server_id'] = $vpsId;
            }
            $stream->update($updateData);
        }

        // Always create progress update for UI
        StreamProgressService::createStageProgress(
            $stream->id,
            $stage,
            $message ?: 'Đang xử lý...',
            $progressPercentage,
            $details
        );
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("📨 [UpdateStreamStatus] Job failed permanently", [
            'data' => $this->data,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }

    /**
     * Handle auto-delete files when stream stops
     */
    private function handleAutoDeleteFiles(StreamConfiguration $stream): void
    {
        try {
            // Check if auto-delete is enabled
            if (!$stream->auto_delete_from_cdn) {
                Log::debug("🗑️ [AutoDelete] Stream #{$stream->id} auto-delete disabled, skipping");
                return;
            }

            Log::info("🗑️ [AutoDelete] Processing auto-delete for Stream #{$stream->id}");

            // Get all files associated with this stream
            $videoFiles = $this->getStreamVideoFiles($stream);

            if (empty($videoFiles)) {
                Log::info("🗑️ [AutoDelete] No files found for Stream #{$stream->id}");
                return;
            }

            Log::info("🗑️ [AutoDelete] Found " . count($videoFiles) . " files to delete for Stream #{$stream->id}", [
                'count' => count($videoFiles),
                'files' => array_map(fn($f) => $f['original_name'] ?? $f['id'], $videoFiles)
            ]);

            // Dispatch delete jobs for each file
            foreach ($videoFiles as $fileData) {
                // Add delay to avoid overwhelming Bunny API
                $delay = now()->addSeconds(rand(5, 30));

                \App\Jobs\DeleteFileJob::dispatch($fileData)->delay($delay);

                Log::info("🗑️ [AutoDelete] Scheduled deletion for file", [
                    'file_id' => $fileData['id'],
                    'file_name' => $fileData['original_name'] ?? 'Unknown',
                    'disk' => $fileData['disk'],
                    'delay_seconds' => $delay->diffInSeconds(now())
                ]);
            }

            Log::info("✅ [AutoDelete] Scheduled deletion of " . count($videoFiles) . " files for Stream #{$stream->id}", [
                'count' => count($videoFiles)
            ]);

        } catch (\Exception $e) {
            Log::error("❌ [AutoDelete] Failed to process auto-delete for Stream #{$stream->id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Get video files associated with stream
     */
    private function getStreamVideoFiles(StreamConfiguration $stream): array
    {
        $videoFiles = [];

        foreach (($stream->video_source_path ?? []) as $fileInfo) {
            $userFile = \App\Models\UserFile::find($fileInfo['file_id']);
            if ($userFile) {
                $videoFiles[] = [
                    'id' => $userFile->id,
                    'original_name' => $userFile->original_name,
                    'disk' => $userFile->disk,
                    'path' => $userFile->path,
                    'stream_video_id' => $userFile->stream_video_id
                ];
            }
        }

        return $videoFiles;
    }
}
