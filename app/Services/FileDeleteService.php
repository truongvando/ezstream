<?php

namespace App\Services;

use App\Models\UserFile;
use App\Jobs\DeleteFileJob;
use App\Services\BunnyStorageService;
use App\Services\BunnyStreamService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;

class FileDeleteService
{
    protected BunnyStorageService $bunnyStorageService;
    protected BunnyStreamService $bunnyStreamService;

    public function __construct(
        BunnyStorageService $bunnyStorageService,
        BunnyStreamService $bunnyStreamService
    ) {
        $this->bunnyStorageService = $bunnyStorageService;
        $this->bunnyStreamService = $bunnyStreamService;
    }

    /**
     * Delete a single file.
     */
    public function deleteFile(UserFile $file, bool $async = true): array
    {
        try {
            Log::info('🗑️ [FileDeleteService] Starting file deletion', [
                'file_id' => $file->id,
                'file_name' => $file->original_name,
                'disk' => $file->disk,
                'async' => $async
            ]);

            if ($async) {
                return $this->deleteAsync($file);
            } else {
                return $this->deleteSync($file);
            }

        } catch (Exception $e) {
            Log::error('❌ [FileDeleteService] File deletion failed', [
                'file_id' => $file->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Lỗi khi xóa file: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Delete multiple files.
     */
    public function deleteFiles($files, bool $async = true): array
    {
        $successful = 0;
        $failed = 0;
        $errors = [];

        foreach ($files as $file) {
            $result = $this->deleteFile($file, $async);
            
            if ($result['success']) {
                $successful++;
            } else {
                $failed++;
                $errors[] = [
                    'file_id' => $file->id,
                    'file_name' => $file->original_name,
                    'error' => $result['message']
                ];
            }
        }

        Log::info('📊 [FileDeleteService] Bulk deletion completed', [
            'total' => count($files),
            'successful' => $successful,
            'failed' => $failed
        ]);

        return [
            'success' => $failed === 0,
            'successful' => $successful,
            'failed' => $failed,
            'total' => count($files),
            'errors' => $errors,
            'message' => $this->getBulkDeleteMessage($successful, $failed)
        ];
    }

    /**
     * Delete file asynchronously using queue.
     */
    protected function deleteAsync(UserFile $file): array
    {
        try {
            // Dispatch job for async deletion
            dispatch(new DeleteFileJob($file->toArray()));

            Log::info('⚡ [FileDeleteService] File deletion queued', [
                'file_id' => $file->id
            ]);

            return [
                'success' => true,
                'message' => "File '{$file->original_name}' đã được đưa vào hàng đợi xóa"
            ];

        } catch (Exception $e) {
            Log::error('❌ [FileDeleteService] Failed to queue deletion', [
                'file_id' => $file->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Lỗi khi đưa file vào hàng đợi xóa: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Delete file synchronously.
     */
    protected function deleteSync(UserFile $file): array
    {
        try {
            $fileName = $file->original_name;

            // Delete from storage
            $this->deleteFromStorage($file);

            // Delete from database
            $file->delete();

            Log::info('✅ [FileDeleteService] File deleted successfully', [
                'file_id' => $file->id,
                'file_name' => $fileName
            ]);

            return [
                'success' => true,
                'message' => "File '{$fileName}' đã được xóa thành công"
            ];

        } catch (Exception $e) {
            Log::error('❌ [FileDeleteService] Sync deletion failed', [
                'file_id' => $file->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Lỗi khi xóa file: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Delete file from storage based on disk type.
     */
    protected function deleteFromStorage(UserFile $file): void
    {
        $disk = $file->disk;
        $path = $file->path;
        $streamVideoId = $file->stream_video_id;

        Log::info('🗄️ [FileDeleteService] Deleting from storage', [
            'disk' => $disk,
            'path' => $path,
            'stream_video_id' => $streamVideoId
        ]);

        switch ($disk) {
            case 'bunny_cdn':
                if ($path) {
                    $this->deleteFromBunnyCDN($path);
                }
                break;

            case 'bunny_stream':
                if ($streamVideoId) {
                    $this->deleteFromBunnyStream($streamVideoId);
                }
                break;

            case 'local':
            case 'public':
                $this->deleteFromLocal($file);
                break;

            case 'hybrid':
                // Delete from both local and CDN
                $this->deleteFromLocal($file);
                if ($path) {
                    $this->deleteFromBunnyCDN($path);
                }
                break;

            default:
                Log::warning('⚠️ [FileDeleteService] Unknown disk type', [
                    'disk' => $disk,
                    'file_id' => $file->id
                ]);
        }
    }

    /**
     * Delete from Bunny CDN.
     */
    protected function deleteFromBunnyCDN(string $path): void
    {
        try {
            $result = $this->bunnyStorageService->deleteFile($path);
            
            if (!$result['success']) {
                Log::warning('⚠️ [FileDeleteService] Failed to delete from Bunny CDN', [
                    'path' => $path,
                    'error' => $result['error'] ?? 'Unknown error'
                ]);
            }
        } catch (Exception $e) {
            Log::error('❌ [FileDeleteService] Exception deleting from Bunny CDN', [
                'path' => $path,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Delete from Bunny Stream.
     */
    protected function deleteFromBunnyStream(string $videoId): void
    {
        try {
            $result = $this->bunnyStreamService->deleteVideo($videoId);
            
            if (!$result['success']) {
                Log::warning('⚠️ [FileDeleteService] Failed to delete from Bunny Stream', [
                    'video_id' => $videoId,
                    'error' => $result['error'] ?? 'Unknown error'
                ]);
            }
        } catch (Exception $e) {
            Log::error('❌ [FileDeleteService] Exception deleting from Bunny Stream', [
                'video_id' => $videoId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Delete from local storage.
     */
    protected function deleteFromLocal(UserFile $file): void
    {
        try {
            // Delete from app/files directory
            if ($file->path) {
                $localPath = storage_path('app/files/' . $file->path);
                if (file_exists($localPath)) {
                    unlink($localPath);
                    Log::info('✅ [FileDeleteService] Deleted local file', [
                        'local_path' => $localPath
                    ]);
                }
            }

            // Delete from public storage
            if ($file->storage_path && Storage::disk('public')->exists($file->storage_path)) {
                Storage::disk('public')->delete($file->storage_path);
                Log::info('✅ [FileDeleteService] Deleted from public disk', [
                    'storage_path' => $file->storage_path
                ]);
            }

        } catch (Exception $e) {
            Log::error('❌ [FileDeleteService] Exception deleting local file', [
                'file_id' => $file->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get bulk delete message.
     */
    protected function getBulkDeleteMessage(int $successful, int $failed): string
    {
        if ($failed === 0) {
            return $successful === 1 
                ? 'File đã được xóa thành công'
                : "Đã xóa {$successful} file thành công";
        } else {
            return "Hoàn thành: {$successful} thành công, {$failed} thất bại";
        }
    }
}
