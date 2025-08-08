<?php

namespace App\Jobs;

use App\Models\UserFile;
use App\Models\StreamConfiguration;
use App\Services\BunnyStreamService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CheckVideoProcessingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $userFileId;
    protected $maxRetries = 60; // 60 retries = 5 minutes (5s interval)
    protected $currentRetry;

    public function __construct($userFileId, $currentRetry = 0)
    {
        $this->userFileId = $userFileId;
        $this->currentRetry = $currentRetry;
        $this->queue = 'video-processing';
    }

    public function handle()
    {
        try {
            $userFile = UserFile::find($this->userFileId);
            if (!$userFile || !$userFile->stream_video_id) {
                Log::warning("🔍 [VideoProcessing] UserFile not found or no stream_video_id", [
                    'user_file_id' => $this->userFileId
                ]);
                return;
            }

            $bunnyService = app(BunnyStreamService::class);
            $status = $bunnyService->getVideoStatus($userFile->stream_video_id);

            if (!$status['success']) {
                Log::error("❌ [VideoProcessing] Failed to get video status", [
                    'user_file_id' => $this->userFileId,
                    'video_id' => $userFile->stream_video_id,
                    'error' => $status['error'] ?? 'Unknown error'
                ]);
                $this->scheduleRetry();
                return;
            }

            $videoStatus = $status['status'];
            $encodingProgress = $status['encoding_progress'] ?? 0;

            Log::info("🔍 [VideoProcessing] Checking video status", [
                'user_file_id' => $this->userFileId,
                'video_id' => $userFile->stream_video_id,
                'status' => $videoStatus,
                'progress' => $encodingProgress,
                'retry' => $this->currentRetry
            ]);

            // Update metadata
            $metadata = $userFile->stream_metadata ?? [];
            $metadata['processing_status'] = $videoStatus;
            $metadata['encoding_progress'] = $encodingProgress;
            $metadata['last_checked'] = now()->toISOString();
            $metadata['retry_count'] = $this->currentRetry;

            switch ($videoStatus) {
                case 'finished':
                case 'completed':
                case 'ready':
                    // Video is ready!
                    $metadata['processing_completed_at'] = now()->toISOString();
                    $userFile->update([
                        'stream_metadata' => $metadata,
                        'status' => 'ready'
                    ]);

                    // Update any streams waiting for this video
                    $this->updateWaitingStreams($userFile);

                    Log::info("✅ [VideoProcessing] Video processing completed", [
                        'user_file_id' => $this->userFileId,
                        'video_id' => $userFile->stream_video_id,
                        'final_status' => $videoStatus
                    ]);
                    break;

                case 'error':
                case 'failed':
                    // Processing failed
                    $metadata['processing_failed_at'] = now()->toISOString();
                    $userFile->update([
                        'stream_metadata' => $metadata,
                        'status' => 'FAILED'
                    ]);

                    // Update streams to ERROR status
                    $this->updateWaitingStreams($userFile, 'ERROR');

                    Log::error("❌ [VideoProcessing] Video processing failed", [
                        'user_file_id' => $this->userFileId,
                        'video_id' => $userFile->stream_video_id,
                        'final_status' => $videoStatus
                    ]);
                    break;

                case 'processing':
                case 'uploading':
                case 'queued':
                case 'created':
                default:
                    // Still processing, schedule retry
                    $userFile->update(['stream_metadata' => $metadata]);
                    $this->scheduleRetry();
                    break;
            }

        } catch (\Exception $e) {
            Log::error("💥 [VideoProcessing] Exception in CheckVideoProcessingJob", [
                'user_file_id' => $this->userFileId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $this->scheduleRetry();
        }
    }

    private function scheduleRetry()
    {
        if ($this->currentRetry >= $this->maxRetries) {
            Log::error("⏰ [VideoProcessing] Max retries reached, giving up", [
                'user_file_id' => $this->userFileId,
                'max_retries' => $this->maxRetries
            ]);

            // Mark as timeout
            $userFile = UserFile::find($this->userFileId);
            if ($userFile) {
                $metadata = $userFile->stream_metadata ?? [];
                $metadata['processing_timeout_at'] = now()->toISOString();
                $userFile->update([
                    'stream_metadata' => $metadata,
                    'status' => 'TIMEOUT'
                ]);

                $this->updateWaitingStreams($userFile, 'ERROR');
            }
            return;
        }

        // Schedule next check in 5 seconds
        CheckVideoProcessingJob::dispatch($this->userFileId, $this->currentRetry + 1)
            ->delay(now()->addSeconds(5));

        Log::info("⏳ [VideoProcessing] Scheduled retry", [
            'user_file_id' => $this->userFileId,
            'next_retry' => $this->currentRetry + 1,
            'max_retries' => $this->maxRetries
        ]);
    }

    private function updateWaitingStreams(UserFile $userFile, $status = 'INACTIVE')
    {
        $streams = StreamConfiguration::where('user_file_id', $userFile->id)
            ->where('status', 'waiting_for_processing')
            ->get();

        foreach ($streams as $stream) {
            $stream->update(['status' => $status]);
            
            Log::info("🔄 [VideoProcessing] Updated stream status", [
                'stream_id' => $stream->id,
                'user_file_id' => $userFile->id,
                'new_status' => $status
            ]);
        }
    }

    public function failed(\Throwable $exception)
    {
        Log::error("💥 [VideoProcessing] CheckVideoProcessingJob failed permanently", [
            'user_file_id' => $this->userFileId,
            'error' => $exception->getMessage()
        ]);
    }
}
