<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\UserFile;
use App\Services\BunnyStreamService;
use App\Services\BunnyStorageService;
use Exception;

class DeleteFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes
    public $tries = 3;

    protected $fileData;

    /**
     * Create a new job instance.
     */
    public function __construct($fileData)
    {
        // Store file data instead of model to avoid issues if model is deleted
        $this->fileData = [
            'id' => $fileData['id'],
            'original_name' => $fileData['original_name'],
            'disk' => $fileData['disk'],
            'path' => $fileData['path'] ?? null,
            'storage_path' => $fileData['storage_path'] ?? null,
            'stream_video_id' => $fileData['stream_video_id'] ?? null,
            'user_id' => $fileData['user_id']
        ];
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('DeleteFileJob started', [
                'file_data' => $this->fileData
            ]);

            $this->deleteFromStorage();
            $this->deleteFromDatabase();

            Log::info('DeleteFileJob completed successfully', [
                'file_id' => $this->fileData['id']
            ]);

        } catch (Exception $e) {
            Log::error('DeleteFileJob failed', [
                'file_data' => $this->fileData,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e; // Re-throw to trigger retry
        }
    }

    /**
     * Delete file from storage
     */
    private function deleteFromStorage()
    {
        $disk = $this->fileData['disk'];
        $path = $this->fileData['path'];
        $storagePath = $this->fileData['storage_path'];
        $streamVideoId = $this->fileData['stream_video_id'];

        Log::info('Deleting from storage', [
            'disk' => $disk,
            'path' => $path,
            'storage_path' => $storagePath,
            'stream_video_id' => $streamVideoId
        ]);

        if ($disk === 'bunny_stream' && $streamVideoId) {
            $this->deleteFromBunnyStream($streamVideoId);
            
        } elseif ($disk === 'bunny_cdn' && $path) {
            $this->deleteFromBunnyCDN($path);
            
        } elseif (in_array($disk, ['local', 'hybrid', 'public'])) {
            $this->deleteFromLocal($path, $storagePath);
            
            // If hybrid, also delete from CDN
            if ($disk === 'hybrid' && $path) {
                $this->deleteFromBunnyCDN($path);
            }
        }
    }

    /**
     * Delete from Bunny Stream Library
     */
    private function deleteFromBunnyStream($videoId)
    {
        try {
            $streamService = app(BunnyStreamService::class);
            $result = $streamService->deleteVideo($videoId);
            
            if ($result['success']) {
                Log::info('✅ Deleted from Bunny Stream', [
                    'video_id' => $videoId
                ]);
            } else {
                Log::error('❌ Failed to delete from Bunny Stream', [
                    'video_id' => $videoId,
                    'error' => $result['error'] ?? 'Unknown error'
                ]);
                
                // Don't throw exception, just log error
                // Stream might already be deleted or not exist
            }
            
        } catch (Exception $e) {
            Log::error('❌ Exception deleting from Bunny Stream', [
                'video_id' => $videoId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Delete from Bunny CDN Storage
     */
    private function deleteFromBunnyCDN($path)
    {
        try {
            $bunnyService = app(BunnyStorageService::class);
            $result = $bunnyService->deleteFile($path);
            
            if ($result['success']) {
                Log::info('✅ Deleted from Bunny CDN', [
                    'path' => $path
                ]);
            } else {
                Log::error('❌ Failed to delete from Bunny CDN', [
                    'path' => $path,
                    'error' => $result['error'] ?? 'Unknown error'
                ]);
            }
            
        } catch (Exception $e) {
            Log::error('❌ Exception deleting from Bunny CDN', [
                'path' => $path,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Delete from local storage
     */
    private function deleteFromLocal($path, $storagePath)
    {
        try {
            // Delete from app/files directory
            if ($path) {
                $localPath = storage_path('app/files/' . $path);
                if (file_exists($localPath)) {
                    unlink($localPath);
                    Log::info('✅ Deleted local file', [
                        'local_path' => $localPath
                    ]);
                }
            }
            
            // Delete from public storage
            if ($storagePath && Storage::disk('public')->exists($storagePath)) {
                Storage::disk('public')->delete($storagePath);
                Log::info('✅ Deleted from public disk', [
                    'storage_path' => $storagePath
                ]);
            }
            
        } catch (Exception $e) {
            Log::error('❌ Exception deleting local file', [
                'path' => $path,
                'storage_path' => $storagePath,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Delete from database
     */
    private function deleteFromDatabase()
    {
        try {
            $file = UserFile::find($this->fileData['id']);
            if ($file) {
                $file->delete();
                Log::info('✅ Deleted from database', [
                    'file_id' => $this->fileData['id']
                ]);
            } else {
                Log::info('ℹ️ File already deleted from database', [
                    'file_id' => $this->fileData['id']
                ]);
            }
            
        } catch (Exception $e) {
            Log::error('❌ Exception deleting from database', [
                'file_id' => $this->fileData['id'],
                'error' => $e->getMessage()
            ]);
            
            throw $e; // Re-throw database errors
        }
    }

    /**
     * Handle job failure
     */
    public function failed(Exception $exception): void
    {
        Log::error('DeleteFileJob failed permanently', [
            'file_data' => $this->fileData,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);
    }
}
