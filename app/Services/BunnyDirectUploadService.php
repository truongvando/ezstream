<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;

class BunnyDirectUploadService
{
    protected $storageZone;
    protected $accessKey;
    protected $baseUrl;
    protected $cdnUrl;

    public function __construct()
    {
        $this->storageZone = config('services.bunny.storage_zone', 'ezstream');
        $this->accessKey = config('services.bunny.access_key');
        $this->baseUrl = "https://sg.storage.bunnycdn.com/{$this->storageZone}";
        $this->cdnUrl = config('services.bunny.cdn_url', "https://ezstream.b-cdn.net");
    }

    /**
     * Generate upload URL based on storage mode setting
     */
    public function generateUploadUrl($fileName, $userId, $maxFileSize = null)
    {
        try {
            // Generate unique remote path
            $datePrefix = date('Y/m/d');
            $uniqueFileName = time() . '_' . $userId . '_' . $fileName;
            $remotePath = "users/{$userId}/{$datePrefix}/{$uniqueFileName}";

            // Generate upload token for security
            $uploadToken = Str::random(32);
            $expiresAt = now()->addHours(2); // 2 hour expiry

            // Get storage mode from admin settings
            $storageMode = \App\Models\Setting::where('key', 'storage_mode')->value('value') ?? 'server';

            // Store upload metadata in cache (use timestamps for better serialization)
            $cacheData = [
                'user_id' => $userId,
                'file_name' => $fileName,
                'remote_path' => $remotePath,
                'max_file_size' => $maxFileSize ?: 10 * 1024 * 1024 * 1024, // 10GB default
                'expires_at' => $expiresAt->toISOString(),
                'created_at' => now()->toISOString(),
                'storage_mode' => $storageMode
            ];

            $result = [
                'success' => true,
                'upload_token' => $uploadToken,
                'remote_path' => $remotePath,
                'cdn_url' => "{$this->cdnUrl}/{$remotePath}",
                'expires_at' => $expiresAt->toISOString(),
                'max_file_size' => $maxFileSize ?: 10 * 1024 * 1024 * 1024,
                'storage_mode' => $storageMode
            ];

            switch ($storageMode) {
                case 'server':
                    // Upload to server endpoint
                    $result['upload_url'] = config('app.url') . "/api/server-upload/{$uploadToken}";
                    $result['method'] = 'POST'; // Use POST for server upload
                    break;

                case 'cdn':
                    // Direct upload to Bunny CDN
                    $result['upload_url'] = "{$this->baseUrl}/{$remotePath}";
                    $result['access_key'] = $this->accessKey;
                    $result['method'] = 'PUT'; // Use PUT for CDN upload
                    break;

                case 'hybrid':
                    // Upload to server first, then sync to CDN
                    $result['upload_url'] = config('app.url') . "/api/server-upload/{$uploadToken}";
                    $result['method'] = 'POST';
                    $result['sync_to_cdn'] = true;
                    break;

                case 'stream_library':
                    // Direct upload to Stream Library using TUS
                    $streamService = app(\App\Services\BunnyStreamService::class);
                    if (!$streamService->isConfigured()) {
                        \Log::error('Stream Library not configured');
                        throw new Exception('Stream Library not configured');
                    }

                    // Create video object first
                    \Log::info('Creating video in Stream Library', ['file_name' => $fileName]);
                    $videoResult = $streamService->createVideo($fileName);
                    \Log::info('Video creation result', ['result' => $videoResult]);

                    if (!$videoResult['success']) {
                        \Log::error('Failed to create video', ['error' => $videoResult['error']]);
                        throw new Exception('Failed to create video: ' . $videoResult['error']);
                    }

                    $libraryId = config('bunnycdn.video_library_id');
                    $videoId = $videoResult['video_id'];
                    $streamApiKey = config('bunnycdn.stream_api_key');

                    // Generate upload token for TUS upload
                    $uploadToken = Str::random(32);
                    $expiresAt = now()->addHours(2);

                    // Generate proper signature for TUS upload
                    $expireTime = time() + 7200; // 2 hours from now
                    $signature = hash('sha256', $libraryId . $streamApiKey . $expireTime . $videoId);

                    // Store upload metadata in cache for confirmation later
                    $cacheData = [
                        'user_id' => $userId,
                        'file_name' => $fileName,
                        'remote_path' => "stream_library/{$videoId}",
                        'max_file_size' => $maxFileSize ?: 10 * 1024 * 1024 * 1024,
                        'expires_at' => $expiresAt->toISOString(),
                        'created_at' => now()->toISOString(),
                        'storage_mode' => 'stream_library',
                        'video_id' => $videoId,
                        'library_id' => $libraryId,
                        'method' => 'TUS'
                    ];

                    cache()->put("upload_token_{$uploadToken}", $cacheData, $expiresAt);

                    \Log::info('TUS upload configuration', [
                        'upload_token' => $uploadToken,
                        'library_id' => $libraryId,
                        'video_id' => $videoId,
                        'expire_time' => $expireTime,
                        'signature' => $signature,
                        'has_api_key' => !empty($streamApiKey)
                    ]);

                    // TUS endpoint without library/video ID in URL - they go in headers
                    $result['upload_url'] = "https://video.bunnycdn.com/tusupload";
                    $result['method'] = 'TUS';
                    $result['video_id'] = $videoId;
                    $result['library_id'] = $libraryId;
                    $result['auth_signature'] = $signature;
                    $result['auth_expire'] = $expireTime;
                    $result['upload_token'] = $uploadToken; // Add upload token for confirmation
                    break;

                case 'auto':
                    // Auto mode: choose best storage based on file size and type
                    $fileSize = $maxFileSize ?: 0;
                    $isVideoFile = in_array(strtolower(pathinfo($fileName, PATHINFO_EXTENSION)),
                        ['mp4', 'avi', 'mov', 'wmv', 'flv', 'webm', 'mkv']);

                    if ($isVideoFile && $fileSize > 100 * 1024 * 1024) { // > 100MB video files
                        // Use stream library for large video files
                        $streamService = app(\App\Services\BunnyStreamService::class);
                        if ($streamService->isConfigured()) {
                            $videoResult = $streamService->createVideo($fileName);
                            if ($videoResult['success']) {
                                $libraryId = config('bunnycdn.video_library_id');
                                $videoId = $videoResult['video_id'];
                                $streamApiKey = config('bunnycdn.stream_api_key');

                                // Generate upload token for TUS upload
                                $uploadToken = Str::random(32);
                                $expiresAt = now()->addHours(2);

                                // Generate proper signature for TUS upload
                                $expireTime = time() + 7200; // 2 hours from now
                                $signature = hash('sha256', $libraryId . $streamApiKey . $expireTime . $videoId);

                                // Store upload metadata in cache for confirmation later
                                $cacheData = [
                                    'user_id' => $userId,
                                    'file_name' => $fileName,
                                    'remote_path' => "stream_library/{$videoId}",
                                    'max_file_size' => $maxFileSize ?: 10 * 1024 * 1024 * 1024,
                                    'expires_at' => $expiresAt->toISOString(),
                                    'created_at' => now()->toISOString(),
                                    'storage_mode' => 'stream_library',
                                    'video_id' => $videoId,
                                    'library_id' => $libraryId,
                                    'method' => 'TUS'
                                ];

                                cache()->put("upload_token_{$uploadToken}", $cacheData, $expiresAt);

                                // TUS endpoint without library/video ID in URL
                                $result['upload_url'] = "https://video.bunnycdn.com/tusupload";
                                $result['method'] = 'TUS';
                                $result['video_id'] = $videoId;
                                $result['library_id'] = $libraryId;
                                $result['auth_signature'] = $signature;
                                $result['auth_expire'] = $expireTime;
                                $result['upload_token'] = $uploadToken; // Add upload token for confirmation
                                $result['auto_selected'] = 'stream_library';
                                break;
                            }
                        }
                        // Fallback to CDN if stream library fails
                        $result['upload_url'] = "{$this->baseUrl}/{$remotePath}";
                        $result['access_key'] = $this->accessKey;
                        $result['method'] = 'PUT';
                        $result['auto_selected'] = 'cdn';
                    } elseif ($fileSize > 50 * 1024 * 1024) { // > 50MB files
                        // Use CDN for large files
                        $result['upload_url'] = "{$this->baseUrl}/{$remotePath}";
                        $result['access_key'] = $this->accessKey;
                        $result['method'] = 'PUT';
                        $result['auto_selected'] = 'cdn';
                    } else {
                        // Use server for small files
                        $result['upload_url'] = config('app.url') . "/api/server-upload/{$uploadToken}";
                        $result['method'] = 'POST';
                        $result['auto_selected'] = 'server';
                    }
                    break;

                default:
                    throw new Exception("Invalid storage mode: {$storageMode}");
            }

            // Add auto_selected and video_id to cache data if they exist
            if (isset($result['auto_selected'])) {
                $cacheData['auto_selected'] = $result['auto_selected'];
            }
            if (isset($result['video_id'])) {
                $cacheData['video_id'] = $result['video_id'];
            }

            // Store the cache data
            cache()->put("upload_token_{$uploadToken}", $cacheData, $expiresAt);

            return $result;

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Confirm upload completion and create database record
     */
    public function confirmUpload($uploadToken, $actualFileSize, $mimeType, $autoDeleteAfterStream = false)
    {
        try {
            // Get upload metadata from cache
            $uploadData = cache("upload_token_{$uploadToken}");

            Log::info("Confirming upload", [
                'upload_token' => $uploadToken,
                'cache_data_exists' => !is_null($uploadData),
                'actual_file_size' => $actualFileSize,
                'mime_type' => $mimeType
            ]);

            if (!$uploadData) {
                Log::error("Upload token not found in cache", ['token' => $uploadToken]);
                throw new Exception('Invalid or expired upload token');
            }

            // Verify upload hasn't expired (parse ISO string back to Carbon)
            $expiresAt = \Carbon\Carbon::parse($uploadData['expires_at']);
            if (now()->gt($expiresAt)) {
                throw new Exception('Upload token has expired');
            }

            // Verify file size is within limits
            if ($actualFileSize > $uploadData['max_file_size']) {
                throw new Exception('File size exceeds maximum allowed size');
            }

            $user = \App\Models\User::find($uploadData['user_id']);
            if (!$user) {
                throw new Exception('User not found');
            }

            // Get storage mode to set correct disk
            $storageMode = $uploadData['storage_mode'] ?? 'server';

            // For auto mode, use the actual selected storage from upload data
            if ($storageMode === 'auto' && isset($uploadData['auto_selected'])) {
                $actualStorageMode = $uploadData['auto_selected'];
            } else {
                $actualStorageMode = $storageMode;
            }

            $disk = match($actualStorageMode) {
                'server' => 'local',
                'cdn' => 'bunny_cdn',
                'hybrid' => 'hybrid',
                'stream_library' => 'bunny_stream',
                default => 'local'
            };

            // Create database record (match actual database structure)
            $fileData = [
                'disk' => $disk,
                'path' => $uploadData['remote_path'],
                'original_name' => $uploadData['file_name'],
                'mime_type' => $mimeType,
                'size' => $actualFileSize,
                'status' => 'ready',
                'auto_delete_after_stream' => $autoDeleteAfterStream,
                'scheduled_deletion_at' => $autoDeleteAfterStream ? now()->addDays(1) : null
            ];

            // Add stream-specific fields for TUS uploads
            if ($actualStorageMode === 'stream_library' && isset($uploadData['video_id'])) {
                $fileData['stream_video_id'] = $uploadData['video_id'];
                $fileData['path'] = $uploadData['video_id']; // Use video_id as path for stream library
                $fileData['stream_metadata'] = [
                    'video_id' => $uploadData['video_id'],
                    'library_id' => config('bunnycdn.video_library_id'),
                    'uploaded_at' => now()->toISOString(),
                    'processing_status' => 'uploaded'
                ];
            }

            $userFile = $user->files()->create($fileData);

            // Clean up upload token
            cache()->forget("upload_token_{$uploadToken}");

            Log::info("Direct upload confirmed", [
                'user_file_id' => $userFile->id,
                'user_id' => $uploadData['user_id'],
                'file_name' => $uploadData['file_name'],
                'file_size' => $actualFileSize
            ]);

            // Generate appropriate URL based on actual storage mode used
            $fileUrl = match($actualStorageMode) {
                'server' => config('app.url') . "/storage/files/" . basename($uploadData['remote_path']),
                'hybrid' => config('app.url') . "/storage/files/" . basename($uploadData['remote_path']),
                'cdn' => "{$this->cdnUrl}/{$uploadData['remote_path']}",
                'stream_library' => isset($uploadData['video_id']) ?
                    app(\App\Services\BunnyStreamService::class)->getHlsUrl($uploadData['video_id']) :
                    null,
                default => config('app.url') . "/storage/files/" . basename($uploadData['remote_path'])
            };

            return [
                'success' => true,
                'file_id' => $userFile->id,
                'file_name' => $uploadData['file_name'],
                'file_size' => $actualFileSize,
                'cdn_url' => $fileUrl,
                'remote_path' => $uploadData['remote_path']
            ];

        } catch (Exception $e) {
            Log::error('Failed to confirm upload: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get upload progress (if supported by client)
     */
    public function getUploadProgress($uploadToken)
    {
        $uploadData = cache("upload_token_{$uploadToken}");
        if (!$uploadData) {
            return [
                'success' => false,
                'error' => 'Invalid upload token'
            ];
        }

        // Check if file exists on Bunny.net (basic progress check)
        $remotePath = $uploadData['remote_path'];
        $checkUrl = "{$this->baseUrl}/{$remotePath}";

        try {
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'AccessKey' => $this->accessKey
            ])->head($checkUrl);

            if ($response->successful()) {
                $fileSize = $response->header('Content-Length', 0);
                return [
                    'success' => true,
                    'status' => 'completed',
                    'file_size' => (int)$fileSize,
                    'progress' => 100
                ];
            } else {
                return [
                    'success' => true,
                    'status' => 'uploading',
                    'progress' => 0
                ];
            }

        } catch (Exception $e) {
            return [
                'success' => true,
                'status' => 'uploading',
                'progress' => 0
            ];
        }
    }

    /**
     * Cancel upload and cleanup
     */
    public function cancelUpload($uploadToken)
    {
        try {
            $uploadData = cache("upload_token_{$uploadToken}");
            if ($uploadData) {
                // Try to delete partial file from Bunny.net
                $remotePath = $uploadData['remote_path'];
                $deleteUrl = "{$this->baseUrl}/{$remotePath}";

                \Illuminate\Support\Facades\Http::withHeaders([
                    'AccessKey' => $this->accessKey
                ])->delete($deleteUrl);

                // Clean up upload token
                cache()->forget("upload_token_{$uploadToken}");

                Log::info("Upload cancelled", [
                    'upload_token' => $uploadToken,
                    'remote_path' => $remotePath
                ]);
            }

            return [
                'success' => true,
                'message' => 'Upload cancelled successfully'
            ];

        } catch (Exception $e) {
            Log::error('Failed to cancel upload: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Validate upload token
     */
    public function validateUploadToken($uploadToken)
    {
        $uploadData = cache("upload_token_{$uploadToken}");
        
        if (!$uploadData) {
            return [
                'valid' => false,
                'error' => 'Invalid upload token'
            ];
        }

        if (now()->gt($uploadData['expires_at'])) {
            cache()->forget("upload_token_{$uploadToken}");
            return [
                'valid' => false,
                'error' => 'Upload token has expired'
            ];
        }

        return [
            'valid' => true,
            'data' => $uploadData
        ];
    }

    /**
     * Get upload statistics
     */
    public function getUploadStats($userId = null)
    {
        // This would track upload statistics
        // For now, return basic info
        return [
            'total_uploads' => 0,
            'successful_uploads' => 0,
            'failed_uploads' => 0,
            'total_size_uploaded' => 0
        ];
    }


}