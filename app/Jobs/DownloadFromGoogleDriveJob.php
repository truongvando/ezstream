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

class DownloadFromGoogleDriveJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600; // 1 hour timeout
    public $tries = 3;

    private UserFile $userFile;
    private string $googleDriveFileId;

    public function __construct(UserFile $userFile, string $googleDriveFileId)
    {
        $this->userFile = $userFile;
        $this->googleDriveFileId = $googleDriveFileId;
    }

    public function handle(FileSecurityService $fileSecurityService): void
    {
        Log::info('Starting Google Drive download', [
            'user_file_id' => $this->userFile->id,
            'google_drive_file_id' => $this->googleDriveFileId,
            'user_id' => $this->userFile->user_id
        ]);

        try {
            // Update status to downloading
            $this->userFile->update(['status' => 'DOWNLOADING']);

            // Get download URL
            $downloadUrl = $this->getDirectDownloadUrl($this->googleDriveFileId);
            if (!$downloadUrl) {
                throw new \Exception('Cannot get download URL for Google Drive file');
            }

            // Create temp file path
            $tempFileName = 'temp_' . uniqid() . '_' . $this->userFile->original_name;
            $tempPath = storage_path('app/temp_downloads/' . $tempFileName);
            
            // Ensure directory exists
            $tempDir = dirname($tempPath);
            if (!file_exists($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            // Download file with progress tracking
            $this->downloadFileWithProgress($downloadUrl, $tempPath);

            // Validate downloaded file
            $validationResult = $this->validateDownloadedFile($tempPath, $fileSecurityService);
            if (!$validationResult['valid']) {
                unlink($tempPath);
                throw new \Exception('File validation failed: ' . $validationResult['reason']);
            }

            // Move to final location
            $finalFileName = uniqid() . '_' . time() . '_' . $this->userFile->original_name;
            $finalPath = storage_path('app/user_uploads/' . $finalFileName);
            
            if (!rename($tempPath, $finalPath)) {
                throw new \Exception('Failed to move file to final location');
            }

            // Update file record
            $actualSize = filesize($finalPath);
            $this->userFile->update([
                'path' => 'user_uploads/' . $finalFileName,
                'size' => $actualSize,
                'status' => 'PENDING_TRANSFER'
            ]);

            // Dispatch transfer to VPS
            TransferVideoToVpsJob::dispatch($this->userFile);

            // Clear progress cache
            cache()->forget('download_progress_' . $this->userFile->id);

            Log::info('Google Drive download completed successfully', [
                'user_file_id' => $this->userFile->id,
                'final_size' => $actualSize,
                'user_id' => $this->userFile->user_id
            ]);

        } catch (\Exception $e) {
            $this->handleDownloadFailure($e);
        }
    }

    /**
     * Get direct download URL for Google Drive file
     */
    private function getDirectDownloadUrl(string $fileId): ?string
    {
        // Try multiple methods to get download URL
        $downloadUrls = [
            "https://drive.google.com/uc?export=download&id={$fileId}",
            "https://drive.google.com/uc?id={$fileId}&export=download",
            "https://docs.google.com/uc?export=download&id={$fileId}"
        ];

        foreach ($downloadUrls as $url) {
            try {
                // Test if URL is accessible
                $response = Http::timeout(30)->head($url);
                if ($response->successful()) {
                    return $url;
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        return null;
    }

    /**
     * Download file with progress tracking
     */
    private function downloadFileWithProgress(string $url, string $destinationPath): void
    {
        $progressKey = 'download_progress_' . $this->userFile->id;
        
        // Initialize progress
        cache()->put($progressKey, 0, 3600);

        $fp = fopen($destinationPath, 'w+');
        if (!$fp) {
            throw new \Exception('Cannot create temporary file');
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 3600, // 1 hour
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            CURLOPT_PROGRESSFUNCTION => function($resource, $downloadSize, $downloaded, $uploadSize, $uploaded) use ($progressKey) {
                if ($downloadSize > 0) {
                    $progress = ($downloaded / $downloadSize) * 100;
                    cache()->put($progressKey, round($progress, 2), 3600);
                }
                return 0; // Continue download
            },
            CURLOPT_NOPROGRESS => false,
        ]);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        fclose($fp);

        if ($result === false || $httpCode !== 200) {
            unlink($destinationPath);
            throw new \Exception("Download failed. HTTP Code: {$httpCode}, Error: {$error}");
        }

        // Check if file was actually downloaded
        if (!file_exists($destinationPath) || filesize($destinationPath) === 0) {
            throw new \Exception('Downloaded file is empty or corrupted');
        }
    }

    /**
     * Validate downloaded file
     */
    private function validateDownloadedFile(string $filePath, FileSecurityService $fileSecurityService): array
    {
        // Get file extension from original name
        $extension = strtolower(pathinfo($this->userFile->original_name, PATHINFO_EXTENSION));
        
        // Validate using FileSecurityService
        return $fileSecurityService->validateVideoFile($filePath, $extension);
    }

    /**
     * Handle download failure
     */
    private function handleDownloadFailure(\Exception $e): void
    {
        Log::error('Google Drive download failed', [
            'user_file_id' => $this->userFile->id,
            'google_drive_file_id' => $this->googleDriveFileId,
            'user_id' => $this->userFile->user_id,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        // Update file status
        $this->userFile->update([
            'status' => 'FAILED',
            'error_message' => $e->getMessage()
        ]);

        // Clear progress cache
        cache()->forget('download_progress_' . $this->userFile->id);

        // Re-throw for job retry mechanism
        throw $e;
    }

    /**
     * Handle job failure after all retries
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Google Drive download job failed permanently', [
            'user_file_id' => $this->userFile->id,
            'google_drive_file_id' => $this->googleDriveFileId,
            'user_id' => $this->userFile->user_id,
            'error' => $exception->getMessage()
        ]);

        $this->userFile->update([
            'status' => 'FAILED',
            'error_message' => 'Download failed after multiple retries: ' . $exception->getMessage()
        ]);

        cache()->forget('download_progress_' . $this->userFile->id);
    }
}
