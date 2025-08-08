<?php

namespace App\Services;

use App\Models\UserFile;
use App\Models\Setting;
use App\Services\BunnyStreamService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Exception;

class StreamLibraryUploadService
{
    private $bunnyStreamService;

    public function __construct(BunnyStreamService $bunnyStreamService)
    {
        $this->bunnyStreamService = $bunnyStreamService;
    }

    /**
     * Generate upload URL for Stream Library (when SRS is enabled)
     */
    public function generateUploadUrl($fileName, $fileSize, $mimeType, $userId)
    {
        try {
            // Check if Stream Library is configured
            if (!$this->bunnyStreamService->isConfigured()) {
                throw new Exception('BunnyCDN Stream Library not configured');
            }

            // Generate upload token
            $uploadToken = Str::random(64);
            
            // Store upload session data
            $uploadData = [
                'user_id' => $userId,
                'file_name' => $fileName,
                'file_size' => $fileSize,
                'mime_type' => $mimeType,
                'upload_method' => 'stream_library',
                'expires_at' => now()->addHours(2)->toISOString(),
                'max_file_size' => 21474836480, // 20GB
            ];

            // Cache upload session for 2 hours
            Cache::put("stream_upload_token_{$uploadToken}", $uploadData, now()->addHours(2));

            Log::info('Stream Library upload URL generated', [
                'user_id' => $userId,
                'file_name' => $fileName,
                'file_size' => $fileSize,
                'upload_token' => $uploadToken
            ]);

            return [
                'success' => true,
                'upload_token' => $uploadToken,
                'upload_method' => 'stream_library',
                'expires_at' => $uploadData['expires_at'],
                'max_file_size' => $uploadData['max_file_size'],
                'instructions' => [
                    'step1' => 'Upload file to temporary storage',
                    'step2' => 'Call confirmStreamUpload with upload_token',
                    'step3' => 'File will be processed and uploaded to Stream Library'
                ]
            ];

        } catch (Exception $e) {
            Log::error('Failed to generate Stream Library upload URL: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Confirm upload and process to Stream Library
     */
    public function confirmStreamUpload($uploadToken, $tempFilePath, $actualFileSize)
    {
        try {
            // Get upload session from cache
            $uploadData = Cache::get("stream_upload_token_{$uploadToken}");
            if (!$uploadData) {
                throw new Exception('Upload session not found or expired');
            }

            // Verify upload hasn't expired
            $expiresAt = \Carbon\Carbon::parse($uploadData['expires_at']);
            if (now()->gt($expiresAt)) {
                throw new Exception('Upload token has expired');
            }

            // Verify file size
            if ($actualFileSize > $uploadData['max_file_size']) {
                throw new Exception('File size exceeds maximum allowed size');
            }

            $user = \App\Models\User::find($uploadData['user_id']);
            if (!$user) {
                throw new Exception('User not found');
            }

            Log::info('Processing Stream Library upload', [
                'user_id' => $user->id,
                'file_name' => $uploadData['file_name'],
                'file_size' => $actualFileSize,
                'temp_path' => $tempFilePath
            ]);

            // Upload to BunnyCDN Stream Library
            $streamResult = $this->bunnyStreamService->uploadVideo(
                $tempFilePath,
                $uploadData['file_name'],
                $user->id
            );

            if (!$streamResult['success']) {
                throw new Exception('Failed to upload to Stream Library: ' . $streamResult['error']);
            }

            // Create UserFile record with Stream Library data
            $userFile = UserFile::create([
                'user_id' => $user->id,
                'original_name' => $uploadData['file_name'],
                'path' => $streamResult['hls_url'], // Store HLS URL as path
                'size' => $actualFileSize,
                'mime_type' => $uploadData['mime_type'],
                'disk' => 'bunny_stream',
                'status' => 'processing',
                'stream_video_id' => $streamResult['video_id'],
                'stream_metadata' => [
                    'hls_url' => $streamResult['hls_url'],
                    'mp4_url' => $streamResult['mp4_url'],
                    'thumbnail_url' => $streamResult['thumbnail_url'],
                    'uploaded_at' => now()->toISOString(),
                    'processing_status' => 'processing'
                ],
            ]);

            // Start video processing check job
            \App\Jobs\CheckVideoProcessingJob::dispatch($userFile->id)
                ->delay(now()->addSeconds(10)); // Start checking after 10 seconds

            // Clean up cache
            Cache::forget("stream_upload_token_{$uploadToken}");

            // Clean up temp file
            if (file_exists($tempFilePath)) {
                unlink($tempFilePath);
            }

            Log::info('Stream Library upload completed successfully', [
                'user_id' => $user->id,
                'file_id' => $userFile->id,
                'video_id' => $streamResult['video_id'],
                'hls_url' => $streamResult['hls_url']
            ]);

            return [
                'success' => true,
                'file_id' => $userFile->id,
                'file_name' => $userFile->original_name,
                'file_size' => $userFile->size,
                'video_id' => $streamResult['video_id'],
                'hls_url' => $streamResult['hls_url'],
                'mp4_url' => $streamResult['mp4_url'],
                'thumbnail_url' => $streamResult['thumbnail_url'],
                'message' => 'File uploaded to Stream Library successfully'
            ];

        } catch (Exception $e) {
            Log::error('Stream Library upload confirmation failed: ' . $e->getMessage());
            
            // Clean up on error
            if (isset($uploadToken)) {
                Cache::forget("stream_upload_token_{$uploadToken}");
            }
            if (isset($tempFilePath) && file_exists($tempFilePath)) {
                unlink($tempFilePath);
            }

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Check video processing status
     */
    public function checkProcessingStatus($videoId)
    {
        try {
            $status = $this->bunnyStreamService->getVideoStatus($videoId);
            
            if ($status['success']) {
                // Update UserFile record if status changed
                $userFile = UserFile::where('stream_video_id', $videoId)->first();
                if ($userFile) {
                    $metadata = $userFile->stream_metadata ?? [];
                    $metadata['processing_status'] = $status['status'];
                    $metadata['encoding_progress'] = $status['encoding_progress'] ?? 0;
                    $metadata['last_checked'] = now()->toISOString();
                    
                    $userFile->update(['stream_metadata' => $metadata]);
                }
            }

            return $status;

        } catch (Exception $e) {
            Log::error('Failed to check processing status: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get HLS URL for streaming (for SRS)
     */
    public function getStreamingUrl($userFile)
    {
        try {
            if (empty($userFile->stream_video_id)) {
                return null;
            }

            // Return HLS playlist URL for SRS ingest
            return $this->bunnyStreamService->getHlsUrl($userFile->stream_video_id);

        } catch (Exception $e) {
            Log::error('Failed to get streaming URL: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Migrate existing file from Storage to Stream Library
     */
    public function migrateFromStorage(UserFile $userFile)
    {
        try {
            if (!empty($userFile->stream_video_id)) {
                // Already in Stream Library
                return [
                    'success' => true,
                    'message' => 'File already in Stream Library',
                    'hls_url' => $this->bunnyStreamService->getHlsUrl($userFile->stream_video_id)
                ];
            }

            // Download from current location
            $tempPath = storage_path('app' . DIRECTORY_SEPARATOR . 'temp' . DIRECTORY_SEPARATOR . Str::random(32) . '_' . $userFile->original_name);
            
            // Create temp directory if not exists
            $tempDir = dirname($tempPath);
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            // Download file content
            $content = file_get_contents($userFile->public_url ?? $userFile->path);
            if ($content === false) {
                throw new Exception('Cannot download file from current location');
            }

            file_put_contents($tempPath, $content);

            // Upload to Stream Library
            $streamResult = $this->bunnyStreamService->uploadVideo(
                $tempPath,
                $userFile->original_name,
                $userFile->user_id
            );

            if (!$streamResult['success']) {
                throw new Exception('Failed to upload to Stream Library: ' . $streamResult['error']);
            }

            // Update UserFile record
            $userFile->update([
                'disk' => 'bunny_stream',
                'path' => $streamResult['hls_url'],
                'stream_video_id' => $streamResult['video_id'],
                'stream_metadata' => [
                    'hls_url' => $streamResult['hls_url'],
                    'mp4_url' => $streamResult['mp4_url'],
                    'thumbnail_url' => $streamResult['thumbnail_url'],
                    'migrated_at' => now()->toISOString(),
                    'processing_status' => 'pending'
                ],
            ]);

            // Clean up temp file
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }

            Log::info('File migrated to Stream Library successfully', [
                'file_id' => $userFile->id,
                'video_id' => $streamResult['video_id'],
                'hls_url' => $streamResult['hls_url']
            ]);

            return [
                'success' => true,
                'video_id' => $streamResult['video_id'],
                'hls_url' => $streamResult['hls_url'],
                'message' => 'File migrated to Stream Library successfully'
            ];

        } catch (Exception $e) {
            Log::error('Migration to Stream Library failed: ' . $e->getMessage());
            
            // Clean up temp file on error
            if (isset($tempPath) && file_exists($tempPath)) {
                unlink($tempPath);
            }

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
