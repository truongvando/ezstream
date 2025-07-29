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
            cache()->put("upload_token_{$uploadToken}", [
                'user_id' => $userId,
                'file_name' => $fileName,
                'remote_path' => $remotePath,
                'max_file_size' => $maxFileSize ?: 10 * 1024 * 1024 * 1024, // 10GB default
                'expires_at' => $expiresAt->toISOString(),
                'created_at' => now()->toISOString(),
                'storage_mode' => $storageMode
            ], $expiresAt);

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

                default:
                    throw new Exception("Invalid storage mode: {$storageMode}");
            }

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
            $storageMode = \App\Models\Setting::where('key', 'storage_mode')->value('value') ?? 'server';
            $disk = match($storageMode) {
                'server' => 'local',
                'cdn' => 'bunny_cdn',
                'hybrid' => 'hybrid',
                default => 'local'
            };

            // Create database record (match actual database structure)
            $userFile = $user->files()->create([
                'disk' => $disk,
                'path' => $uploadData['remote_path'],
                'original_name' => $uploadData['file_name'],
                'mime_type' => $mimeType,
                'size' => $actualFileSize,
                'status' => 'ready',
                'auto_delete_after_stream' => $autoDeleteAfterStream,
                'scheduled_deletion_at' => $autoDeleteAfterStream ? now()->addDays(1) : null
            ]);

            // Clean up upload token
            cache()->forget("upload_token_{$uploadToken}");

            Log::info("Direct upload confirmed", [
                'user_file_id' => $userFile->id,
                'user_id' => $uploadData['user_id'],
                'file_name' => $uploadData['file_name'],
                'file_size' => $actualFileSize
            ]);

            // Generate appropriate URL based on storage mode
            $fileUrl = match($storageMode) {
                'server' => config('app.url') . "/storage/files/" . basename($uploadData['remote_path']),
                'hybrid' => config('app.url') . "/storage/files/" . basename($uploadData['remote_path']),
                'cdn' => "{$this->cdnUrl}/{$uploadData['remote_path']}",
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