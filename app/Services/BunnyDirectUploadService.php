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
     * Generate signed upload URL for direct browser upload
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

            // Store upload metadata in cache
            cache()->put("upload_token_{$uploadToken}", [
                'user_id' => $userId,
                'file_name' => $fileName,
                'remote_path' => $remotePath,
                'max_file_size' => $maxFileSize ?: 10 * 1024 * 1024 * 1024, // 10GB default
                'expires_at' => $expiresAt,
                'created_at' => now()
            ], $expiresAt);



            return [
                'success' => true,
                'upload_url' => "{$this->baseUrl}/{$remotePath}",
                'upload_token' => $uploadToken,
                'remote_path' => $remotePath,
                'cdn_url' => "{$this->cdnUrl}/{$remotePath}",
                'access_key' => $this->accessKey, // Client needs this for upload
                'expires_at' => $expiresAt->toISOString(),
                'max_file_size' => $maxFileSize ?: 10 * 1024 * 1024 * 1024
            ];

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

            // Verify upload hasn't expired
            if (now()->gt($uploadData['expires_at'])) {
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

            // Create database record (match actual database structure)
            $userFile = $user->files()->create([
                'disk' => 'bunny_cdn',
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

            return [
                'success' => true,
                'file_id' => $userFile->id,
                'file_name' => $uploadData['file_name'],
                'file_size' => $actualFileSize,
                'cdn_url' => "{$this->cdnUrl}/{$uploadData['remote_path']}",
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