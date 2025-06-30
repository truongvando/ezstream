<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use App\Models\UserFile;
use App\Services\FileSecurityService;
use App\Services\GoogleDriveService;

class DownloadFromGoogleDriveJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 7200; // 2 hours timeout for large files
    public $tries = 2;

    private UserFile $userFile;
    private string $googleDriveFileId;

    public function __construct(UserFile $userFile, string $googleDriveFileId)
    {
        $this->userFile = $userFile;
        $this->googleDriveFileId = $googleDriveFileId;
    }

    public function handle(FileSecurityService $fileSecurityService, GoogleDriveService $googleDriveService): void
    {
        Log::info('GOOGLE_DRIVE_IMPORT_JOB: Starting download', [
            'user_file_id' => $this->userFile->id,
            'google_drive_file_id' => $this->googleDriveFileId,
        ]);

        $tempPath = null; // Initialize temp path to null

        try {
            $this->userFile->update(['status' => 'DOWNLOADING']);

            $downloadUrl = $this->getDirectDownloadUrl($this->googleDriveFileId);
            if (!$downloadUrl) {
                throw new \Exception('Cannot get a valid download URL from Google Drive.');
            }

            // Create a unique temporary file path
            $tempFileName = 'gdrive_import_' . uniqid() . '.' . pathinfo($this->userFile->original_name, PATHINFO_EXTENSION);
            $tempPath = storage_path('app/temp_downloads/' . $tempFileName);
            
            $tempDir = dirname($tempPath);
            if (!file_exists($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            $this->downloadFileWithProgress($downloadUrl, $tempPath);

            Log::info('GOOGLE_DRIVE_IMPORT_JOB: File downloaded successfully, now uploading to system Google Drive', [
                'user_file_id' => $this->userFile->id,
                'temp_path' => $tempPath,
                'file_size' => filesize($tempPath),
            ]);
            
            // Upload to system's Google Drive (same as normal upload)
            $uploadedFileId = $googleDriveService->uploadFile($tempPath, $this->userFile->original_name);
            
            if (!$uploadedFileId) {
                throw new \Exception('Failed to upload file to system Google Drive');
            }
            
            Log::info('GOOGLE_DRIVE_IMPORT_JOB: File uploaded to system Google Drive successfully', [
                'user_file_id' => $this->userFile->id,
                'google_drive_file_id' => $uploadedFileId,
            ]);
            
            // Update file record (same as normal upload)
            $this->userFile->update([
                'disk' => 'google',
                'path' => $uploadedFileId,
                'size' => filesize($tempPath),
                'status' => 'AVAILABLE',
                'error_message' => null,
            ]);
            
            Log::info('GOOGLE_DRIVE_IMPORT_JOB: Process completed successfully', [
                'user_file_id' => $this->userFile->id,
                'google_drive_file_id' => $uploadedFileId,
            ]);

        } catch (\Exception $e) {
            $this->handleDownloadFailure($e);
        } finally {
            // Always clean up the temporary file
            if ($tempPath && file_exists($tempPath)) {
                unlink($tempPath);
                Log::info('GOOGLE_DRIVE_IMPORT_JOB: Cleaned up temporary file.', ['path' => $tempPath]);
            }
        }
    }

    private function getDirectDownloadUrl(string $fileId): ?string
    {
        // Use direct download URLs like Python's gdown - no API key needed
        $directUrls = [
            "https://drive.google.com/uc?export=download&id={$fileId}",
            "https://drive.google.com/uc?id={$fileId}&export=download", 
            "https://docs.google.com/uc?export=download&id={$fileId}",
            "https://drive.google.com/file/d/{$fileId}/view?usp=sharing"
        ];
        
        foreach ($directUrls as $url) {
            try {
                // Test with HEAD request first
                $response = Http::timeout(10)->head($url);
                
                if ($response->successful()) {
                    $contentType = $response->header('Content-Type');
                    $contentLength = $response->header('Content-Length');
                    
                    Log::info('GOOGLE_DRIVE_IMPORT_JOB: Testing direct URL', [
                        'url' => substr($url, 0, 50) . '...',
                        'content_type' => $contentType,
                        'content_length' => $contentLength
                    ]);
                    
                    // If it returns video content or reasonable size, use this URL
                    if (str_contains($contentType, 'video/') || 
                        str_contains($contentType, 'application/octet-stream') ||
                        $contentLength > 1000000) { // > 1MB
                        return $url;
                    }
                }
            } catch (\Exception $e) {
                Log::warning('GOOGLE_DRIVE_IMPORT_JOB: Direct URL failed', [
                    'url' => substr($url, 0, 50) . '...',
                    'error' => $e->getMessage()
                ]);
                continue;
            }
        }
        
        // Fallback to first URL anyway
        Log::info('GOOGLE_DRIVE_IMPORT_JOB: Using fallback URL');
        return $directUrls[0];
    }

    private function downloadFileWithProgress(string $url, string $destinationPath): void
    {
        $progressKey = 'download_progress_' . $this->userFile->id;
        cache()->put($progressKey, 0, 7200);

        $destinationHandle = fopen($destinationPath, 'w');
        if ($destinationHandle === false) {
            throw new \Exception('Cannot create temporary file for download.');
        }

        $response = Http::withOptions([
            'stream' => true,
            'sink' => $destinationHandle,
            'timeout' => 7200, // 2 hours
            'progress' => function ($totalDownload, $downloadedBytes) use ($progressKey) {
                if ($totalDownload > 0) {
                    $progress = round(($downloadedBytes / $totalDownload) * 100, 2);
                    cache()->put($progressKey, $progress, 7200);
                }
            }
        ])->get($url);

        if ($response->failed()) {
            throw new \Exception("Download failed with status: " . $response->status());
        }

        // Check if downloaded file is actually HTML (Google Drive error page)
        if (filesize($destinationPath) < 1024 * 1024) { // Only check small files (< 1MB)
            $content = file_get_contents($destinationPath, false, null, 0, 2048);
            
            // Check for common Google Drive error indicators
            if (str_contains($content, '<html') || 
                str_contains($content, 'Virus scan warning') || 
                str_contains($content, 'accounts.google.com') ||
                str_contains($content, 'Google Drive - Virus scan warning') ||
                str_contains($content, 'cannot be scanned with Google')) {
                
                // Try alternative download URLs
                $alternativeUrls = $this->getAlternativeDownloadUrls($this->googleDriveFileId);
                
                foreach ($alternativeUrls as $altUrl) {
                    Log::info('GOOGLE_DRIVE_IMPORT_JOB: Trying alternative download URL', ['url' => $altUrl]);
                    
                    // Reset file handle
                    fclose($destinationHandle);
                    $destinationHandle = fopen($destinationPath, 'w');
                    
                    $altResponse = Http::withOptions([
                        'stream' => true,
                        'sink' => $destinationHandle,
                        'timeout' => 7200,
                        'progress' => function ($totalDownload, $downloadedBytes) use ($progressKey) {
                            if ($totalDownload > 0) {
                                $progress = round(($downloadedBytes / $totalDownload) * 100, 2);
                                cache()->put($progressKey, $progress, 7200);
                            }
                        }
                    ])->get($altUrl);
                    
                    if ($altResponse->successful() && filesize($destinationPath) > 1024) {
                        $newContent = file_get_contents($destinationPath, false, null, 0, 1024);
                        if (!str_contains($newContent, '<html')) {
                            Log::info('GOOGLE_DRIVE_IMPORT_JOB: Successfully downloaded with alternative URL');
                            return; // Success
                        }
                    }
                }
                
                throw new \Exception('Downloaded file appears to be HTML instead of video. The file may require manual confirmation or may not be publicly accessible.');
            }
        }
    }

    private function getAlternativeDownloadUrls(string $fileId): array
    {
        return [
            "https://drive.google.com/uc?export=download&id={$fileId}&confirm=t",
            "https://drive.google.com/uc?id={$fileId}&export=download",
            "https://docs.google.com/uc?export=download&id={$fileId}",
            "https://drive.google.com/u/0/uc?id={$fileId}&export=download&confirm=t"
        ];
    }

    private function handleDownloadFailure(\Exception $e): void
    {
        Log::error('GOOGLE_DRIVE_IMPORT_JOB: Job failed.', [
            'user_file_id' => $this->userFile->id,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        $this->userFile->update([
            'status' => 'FAILED',
            'error_message' => $e->getMessage()
        ]);
        cache()->forget('download_progress_' . $this->userFile->id);
    }
    
    public function failed(\Throwable $exception): void
    {
        Log::critical('GOOGLE_DRIVE_IMPORT_JOB: Job failed permanently after all retries.', [
            'user_file_id' => $this->userFile->id,
            'exception' => $exception->getMessage(),
        ]);
        $this->userFile->update([
            'status' => 'FAILED',
            'error_message' => 'Job failed permanently: ' . $exception->getMessage()
        ]);
        cache()->forget('download_progress_' . $this->userFile->id);
    }
}
