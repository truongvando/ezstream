<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\UserFile;
use App\Models\StreamConfiguration;
use Exception;

class UploadService
{
    private $bunnyStorageService;
    private $bunnyStreamService;

    public function __construct(
        BunnyStorageService $bunnyStorageService,
        BunnyStreamService $bunnyStreamService
    ) {
        $this->bunnyStorageService = $bunnyStorageService;
        $this->bunnyStreamService = $bunnyStreamService;
    }

    /**
     * Determine the best upload method based on file size and user settings
     */
    public function determineUploadMethod(int $fileSize, string $fileType): string
    {
        $user = Auth::user();
        $streamConfig = StreamConfiguration::where('user_id', $user->id)->first();
        
        // Get storage mode from user settings or default
        $storageMode = $streamConfig->storage_mode ?? 'auto';
        
        Log::info('🎯 [UploadService] Determining upload method', [
            'file_size' => $fileSize,
            'storage_mode' => $storageMode,
            'user_id' => $user->id
        ]);

        switch ($storageMode) {
            case 'server':
                return 'server';
                
            case 'cdn':
                return 'cdn';
                
            case 'stream_library':
                return 'stream';
                
            case 'auto':
            default:
                // Auto mode: choose based on file size
                if ($fileSize > 100 * 1024 * 1024) { // > 100MB
                    return 'stream'; // Use TUS for large files
                } elseif ($fileSize > 10 * 1024 * 1024) { // > 10MB
                    return 'cdn'; // Use CDN for medium files
                } else {
                    return 'server'; // Use server for small files
                }
        }
    }

    /**
     * Generate upload configuration for frontend
     */
    public function generateUploadConfig(string $fileName, int $userId, int $fileSize, string $method): array
    {
        $uploadToken = Str::random(32);
        $remotePath = $this->generateRemotePath($fileName, $userId);
        
        Log::info('🔧 [UploadService] Generating upload config', [
            'method' => $method,
            'file_size' => $fileSize,
            'upload_token' => $uploadToken
        ]);

        switch ($method) {
            case 'server':
                return $this->generateServerConfig($uploadToken, $remotePath, $fileName);
                
            case 'cdn':
                return $this->generateCDNConfig($uploadToken, $remotePath, $fileName, $fileSize);
                
            case 'stream':
                return $this->generateStreamConfig($uploadToken, $remotePath, $fileName, $fileSize);
                
            default:
                throw new Exception("Unsupported upload method: {$method}");
        }
    }

    /**
     * Generate server upload configuration
     */
    private function generateServerConfig(string $uploadToken, string $remotePath, string $fileName): array
    {
        // Store upload info in cache for later confirmation
        cache()->put("upload_token:{$uploadToken}", [
            'method' => 'server',
            'remote_path' => $remotePath,
            'original_name' => $fileName,
            'user_id' => Auth::id(),
            'created_at' => now()
        ], 3600); // 1 hour

        return [
            'method' => 'server',
            'method_name' => 'Server Storage',
            'upload_token' => $uploadToken,
            'upload_url' => route('api.server-upload', ['token' => $uploadToken]),
            'remote_path' => $remotePath
        ];
    }

    /**
     * Generate CDN upload configuration
     */
    private function generateCDNConfig(string $uploadToken, string $remotePath, string $fileName, int $fileSize): array
    {
        try {
            $uploadUrl = $this->bunnyStorageService->generateUploadUrl($remotePath);
            
            // Store upload info in cache
            cache()->put("upload_token:{$uploadToken}", [
                'method' => 'cdn',
                'remote_path' => $remotePath,
                'original_name' => $fileName,
                'user_id' => Auth::id(),
                'upload_url' => $uploadUrl,
                'created_at' => now()
            ], 3600);

            return [
                'method' => 'cdn',
                'method_name' => 'Bunny CDN',
                'upload_token' => $uploadToken,
                'upload_url' => $uploadUrl,
                'access_key' => config('services.bunny.storage_password'),
                'remote_path' => $remotePath
            ];

        } catch (Exception $e) {
            Log::error('❌ [UploadService] CDN config generation failed', [
                'error' => $e->getMessage(),
                'remote_path' => $remotePath
            ]);
            throw new Exception('Failed to generate CDN upload URL: ' . $e->getMessage());
        }
    }

    /**
     * Generate Stream Library upload configuration
     */
    private function generateStreamConfig(string $uploadToken, string $remotePath, string $fileName, int $fileSize): array
    {
        try {
            $streamData = $this->bunnyStreamService->createVideo($fileName);
            
            // Store upload info in cache
            cache()->put("upload_token:{$uploadToken}", [
                'method' => 'stream',
                'remote_path' => $remotePath,
                'original_name' => $fileName,
                'user_id' => Auth::id(),
                'video_id' => $streamData['guid'],
                'library_id' => $streamData['videoLibraryId'],
                'created_at' => now()
            ], 3600);

            return [
                'method' => 'stream',
                'method_name' => 'Bunny Stream Library',
                'upload_token' => $uploadToken,
                'upload_url' => 'https://video.bunnycdn.com/tusupload',
                'video_id' => $streamData['guid'],
                'library_id' => $streamData['videoLibraryId'],
                'auth_signature' => $streamData['authenticationSignature'],
                'auth_expire' => $streamData['authenticationExpire'],
                'remote_path' => $remotePath
            ];

        } catch (Exception $e) {
            Log::error('❌ [UploadService] Stream config generation failed', [
                'error' => $e->getMessage(),
                'file_name' => $fileName
            ]);
            throw new Exception('Failed to generate Stream upload URL: ' . $e->getMessage());
        }
    }

    /**
     * Confirm upload and save to database
     */
    public function confirmUpload(string $uploadToken, int $fileSize, string $contentType): UserFile
    {
        $uploadInfo = cache()->get("upload_token:{$uploadToken}");
        
        if (!$uploadInfo) {
            throw new Exception('Upload token not found or expired');
        }

        Log::info('✅ [UploadService] Confirming upload', [
            'upload_token' => $uploadToken,
            'method' => $uploadInfo['method'],
            'file_size' => $fileSize
        ]);

        try {
            // Create file record
            $file = UserFile::create([
                'user_id' => $uploadInfo['user_id'],
                'original_name' => $uploadInfo['original_name'],
                'file_name' => basename($uploadInfo['remote_path']),
                'file_path' => $uploadInfo['remote_path'],
                'size' => $fileSize,
                'mime_type' => $contentType,
                'storage_type' => $uploadInfo['method'],
                'video_id' => $uploadInfo['video_id'] ?? null,
                'library_id' => $uploadInfo['library_id'] ?? null,
                'cdn_url' => $this->generateCDNUrl($uploadInfo['remote_path'], $uploadInfo['method']),
                'is_processed' => $uploadInfo['method'] === 'stream' ? false : true
            ]);

            // Clean up cache
            cache()->forget("upload_token:{$uploadToken}");

            Log::info('🎉 [UploadService] Upload confirmed successfully', [
                'file_id' => $file->id,
                'method' => $uploadInfo['method']
            ]);

            return $file;

        } catch (Exception $e) {
            Log::error('❌ [UploadService] Upload confirmation failed', [
                'error' => $e->getMessage(),
                'upload_token' => $uploadToken
            ]);
            throw new Exception('Failed to confirm upload: ' . $e->getMessage());
        }
    }

    /**
     * Cancel upload
     */
    public function cancelUpload(string $uploadToken): void
    {
        $uploadInfo = cache()->get("upload_token:{$uploadToken}");
        
        if ($uploadInfo) {
            Log::info('🛑 [UploadService] Cancelling upload', [
                'upload_token' => $uploadToken,
                'method' => $uploadInfo['method']
            ]);

            // Clean up based on method
            if ($uploadInfo['method'] === 'stream' && isset($uploadInfo['video_id'])) {
                try {
                    $this->bunnyStreamService->deleteVideo($uploadInfo['video_id']);
                } catch (Exception $e) {
                    Log::warning('⚠️ [UploadService] Failed to cleanup stream video', [
                        'video_id' => $uploadInfo['video_id'],
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Clean up cache
            cache()->forget("upload_token:{$uploadToken}");
        }
    }

    /**
     * Generate remote path for file
     */
    private function generateRemotePath(string $fileName, int $userId): string
    {
        $timestamp = time();
        $extension = pathinfo($fileName, PATHINFO_EXTENSION);
        $baseName = pathinfo($fileName, PATHINFO_FILENAME);
        $safeName = Str::slug($baseName);
        
        return "users/{$userId}/" . date('Y/m/d') . "/{$timestamp}_{$userId}_{$safeName}.{$extension}";
    }

    /**
     * Generate CDN URL based on storage method
     */
    private function generateCDNUrl(string $remotePath, string $method): string
    {
        switch ($method) {
            case 'cdn':
                return "https://" . config('services.bunny.storage_zone') . ".b-cdn.net/{$remotePath}";
            case 'server':
                return url("storage/{$remotePath}");
            case 'stream':
                // Stream URLs are generated differently, will be updated after processing
                return '';
            default:
                return '';
        }
    }
}
