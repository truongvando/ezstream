<?php

namespace App\Jobs;

use App\Models\StreamConfiguration;
use App\Services\Stream\StreamAllocation;
use App\Services\StreamProgressService;
use App\Services\Vps\VpsMonitor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class StartMultistreamJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 30; // Giảm timeout, job chỉ làm 1 việc

    public function __construct(public StreamConfiguration $stream)
    {
    }

    public function handle(StreamAllocation $streamAllocation, VpsMonitor $vpsMonitor): void
    {
        $stream = $this->stream;

        try {
            Log::info("▶️ [StartJob] Processing Stream #{$stream->id}");

            // 0. Validate all files are ready for streaming
            $validationResult = $this->validateStreamFiles($stream);
            if (!$validationResult['ready']) {
                // Set stream to waiting status if files not ready
                $stream->update([
                    'status' => 'waiting_for_processing',
                    'status_message' => $validationResult['message']
                ]);
                Log::warning("⏸️ [StartJob] Stream #{$stream->id} waiting for file processing: {$validationResult['message']}");
                return;
            }

            // 1. Allocate a VPS if not already set
            if (!$stream->vps_server_id) {
                $vps = $streamAllocation->findOptimalVps($stream);
                if (!$vps) {
                    throw new \Exception('No suitable VPS available for allocation.');
                }
                $stream->vps_server_id = $vps->id;
                $stream->save();
                Log::info("✅ [StartJob] Stream #{$stream->id} allocated to VPS #{$stream->vps_server_id}");
            }

            $vps = $stream->vpsServer;
            if (!$vps) {
                throw new \Exception("VPS server with ID {$stream->vps_server_id} not found.");
            }

            // 2. Wait for agent to be ready (new robust check)
            $isReady = $this->waitForAgent($vps->id, 15); // Wait up to 15 seconds
            if (!$isReady) {
                throw new \Exception("Agent on VPS #{$vps->id} did not become ready in time.");
            }

            // 3. Prepare config payload
            $configPayload = $this->buildConfigPayload($stream);

            // 4. Publish command to the specific VPS channel
            $channel = "vps-commands:{$vps->id}";
            $subscribersReceived = Redis::publish($channel, json_encode($configPayload));

            if ($subscribersReceived == 0) {
                throw new \Exception("No active agent listening on channel '{$channel}'. Agent may have crashed or disconnected.");
            }

            Log::info("✅ [StartJob] START_STREAM command published to '{$channel}' for Stream #{$stream->id}. Received by {$subscribersReceived} agent(s).");

        } catch (\Exception $e) {
            $errorMessage = Str::limit($e->getMessage(), 250);
            Log::error("💥 [StartJob] FAILED for Stream #{$stream->id}", ['error' => $errorMessage]);
            $stream->update([
                'status' => 'ERROR',
                'status_message' => "Start failed: {$errorMessage}",
            ]);
            // Optional: Notify user
        }
    }

    private function waitForAgent(int $vpsId, int $timeoutSeconds): bool
    {
        $channel = "vps-commands:{$vpsId}";
        $startTime = time();

        Log::info("⏳ [StartJob] Checking for agent on '{$channel}'. Timeout: {$timeoutSeconds}s.");

        while (time() - $startTime < $timeoutSeconds) {
            // PUBSUB NUMSUB returns associative array like ['vps-commands:36' => 1]
            $listeners = Redis::command('PUBSUB', ['NUMSUB', $channel]);

            // Check if channel has listeners (handle both array formats)
            $listenerCount = 0;
            if (is_array($listeners)) {
                if (isset($listeners[$channel])) {
                    $listenerCount = $listeners[$channel]; // Associative format
                } elseif (isset($listeners[1])) {
                    $listenerCount = $listeners[1]; // Indexed format
                }
            }

            if ($listenerCount > 0) {
                Log::info("✅ [StartJob] Agent is ready on '{$channel}' with {$listenerCount} listener(s).");
                return true;
            }

            Log::info("... agent not yet ready on '{$channel}', waiting 2s...");
            sleep(2);
        }

        Log::error("❌ [StartJob] Timeout reached. No agent listening on '{$channel}'.");
        return false;
    }

    private function buildConfigPayload(StreamConfiguration $stream): array
    {
        $stream->load('userFile'); // Eager load relations

        // Always use FFmpeg (only supported method)
        $streamingMethod = 'ffmpeg';
        $useFFmpeg = true;

        return [
            'command' => 'START_STREAM',  // ← FIX: Agent cần biết command
            'config' => [
                'id' => $stream->id,
                'stream_key' => $stream->stream_key,
                'video_files' => $this->prepareVideoFiles($stream, $useFFmpeg),
                'rtmp_url' => $stream->rtmp_url . '/' . $stream->stream_key,
                'push_urls' => $stream->push_urls ?? [],
                'loop' => $stream->loop ?? true,
                'keep_files_on_agent' => $stream->keep_files_on_agent ?? false,
                'use_ffmpeg' => $useFFmpeg,  // Tell agent to use FFmpeg
                'streaming_method' => $streamingMethod,  // NEW: Pass streaming method
            ]
        ];
    }

    private function prepareVideoFiles(StreamConfiguration $stream, bool $useFFmpeg = true): array
    {
        $videoFiles = [];
        $videoSourcePath = is_string($stream->video_source_path)
            ? json_decode($stream->video_source_path, true)
            : ($stream->video_source_path ?? []);

        foreach ($videoSourcePath as $fileInfo) {
            $userFile = \App\Models\UserFile::find($fileInfo['file_id']);
            if (!$userFile) continue;

            $downloadUrl = $this->getDownloadUrl($userFile, $useFFmpeg);
            if ($downloadUrl) {
                $videoFiles[] = [
                    'file_id' => $userFile->id,
                    'filename' => $userFile->original_name,
                    'download_url' => $downloadUrl,
                    'size' => $userFile->size,
                    'disk' => $userFile->disk,
                    'use_ffmpeg' => $useFFmpeg,  // Pass FFmpeg flag to agent
                ];
            }
        }
        return $videoFiles;
    }

    private function getDownloadUrl(\App\Models\UserFile $userFile, bool $useFFmpeg = true): ?string
    {
        try {
            // For FFmpeg streaming with bunny_stream files, use HLS URL directly
            // We've disabled Bunny Stream access restrictions, so VPS can access HLS URLs

            if ($userFile->disk === 'bunny_stream' && $userFile->stream_video_id) {
                // Use HLS URL directly since access restrictions are disabled
                $bunnyStreamService = app(\App\Services\BunnyStreamService::class);
                $hlsUrl = $bunnyStreamService->getHlsUrl($userFile->stream_video_id);
                return $hlsUrl;
            }

            // Handle other storage types through BunnyStorageService
            if (in_array($userFile->disk, ['bunny_cdn', 'local', 'hybrid'])) {
                $bunnyService = app(\App\Services\BunnyStorageService::class);
                $result = $bunnyService->getDirectDownloadLink($userFile->path);
                if ($result['success']) {
                    return $result['download_link'];
                }
            }

            // Final fallback to secure download
            $downloadToken = \Illuminate\Support\Str::random(32);
            cache()->put("download_token_{$downloadToken}", $userFile->id, now()->addDays(7));

            return url("/api/secure-download/{$downloadToken}");

        } catch (\Exception $e) {
            Log::error("Failed to get download URL", ['file_id' => $userFile->id, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Check if we should use Stream Library for this file
     */
    private function shouldUseStreamLibrary(\App\Models\UserFile $userFile): bool
    {
        // Check if file has stream_video_id (uploaded to Stream Library)
        return !empty($userFile->stream_video_id);
    }

    /**
     * Get Stream Library URL for SRS streaming
     */
    private function getStreamLibraryUrl(\App\Models\UserFile $userFile): ?string
    {
        try {
            if (empty($userFile->stream_video_id)) {
                return null;
            }

            $bunnyStreamService = app(\App\Services\BunnyStreamService::class);

            // Return HLS playlist URL for SRS to ingest
            return $bunnyStreamService->getHlsUrl($userFile->stream_video_id);

        } catch (\Exception $e) {
            Log::error("Failed to get Stream Library URL for file {$userFile->id}: " . $e->getMessage());
            return null;
        }
    }

    public function failed(\Throwable $exception): void
    {
        $this->failAndSetErrorStatus($exception);
    }
    
    private function failAndSetErrorStatus(\Throwable $exception): void
    {
        Log::error("💥 [StartJob] FAILED for Stream #{$this->stream->id}", ['error' => $exception->getMessage()]);
        $this->stream->update([
            'status' => 'ERROR',
            'error_message' => "Job failed: " . $exception->getMessage(),
        ]);
        StreamProgressService::createStageProgress($this->stream->id, 'error', "Job failed: " . $exception->getMessage());
    }

    /**
     * Validate all files in stream are ready for streaming
     */
    private function validateStreamFiles(StreamConfiguration $stream): array
    {
        $videoSourcePath = is_string($stream->video_source_path)
            ? json_decode($stream->video_source_path, true)
            : ($stream->video_source_path ?? []);

        if (empty($videoSourcePath)) {
            return [
                'ready' => false,
                'message' => 'No video files configured for this stream'
            ];
        }

        $notReadyFiles = [];
        $totalFiles = count($videoSourcePath);

        foreach ($videoSourcePath as $fileInfo) {
            $userFile = \App\Models\UserFile::find($fileInfo['file_id']);
            if (!$userFile) {
                $notReadyFiles[] = "File ID {$fileInfo['file_id']} not found";
                continue;
            }

            // Check if file is from Stream Library
            if ($userFile->stream_video_id) {
                $processingStatus = $userFile->stream_metadata['processing_status'] ?? 'unknown';
                if ($processingStatus !== 'completed') {
                    $notReadyFiles[] = "'{$userFile->original_name}' đang xử lý";
                }
            }
            // Regular files are always ready
        }

        if (!empty($notReadyFiles)) {
            return [
                'ready' => false,
                'message' => 'Một số video đang xử lý: ' . implode(', ', $notReadyFiles)
            ];
        }

        return [
            'ready' => true,
            'message' => "All {$totalFiles} files are ready for streaming"
        ];
    }
}
