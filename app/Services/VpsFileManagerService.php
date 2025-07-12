<?php

namespace App\Services;

use App\Models\UserFile;
use App\Models\VpsServer;
use App\Models\StreamConfiguration;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Exception;

class VpsFileManagerService
{
    protected $vpsFileCache = '/opt/user_files/'; // VPS file cache directory
    protected $maxCacheSize = 75 * 1024 * 1024 * 1024; // 75GB cache limit per VPS

    /**
     * Get file path on VPS for streaming
     * Priority: Local cache > Download from cloud
     */
    public function getFileForStreaming(UserFile $userFile, VpsServer $vps)
    {
        Log::info("Getting file for streaming", [
            'file_id' => $userFile->id,
            'vps_id' => $vps->id,
            'file_name' => $userFile->original_name
        ]);

        // Check if file exists in VPS cache
        $cachedPath = $this->getCachedFilePath($userFile, $vps);
        if ($this->fileExistsOnVps($vps, $cachedPath)) {
            Log::info("File found in VPS cache", [
                'file_id' => $userFile->id,
                'cached_path' => $cachedPath
            ]);

            // Update last accessed time
            $this->updateFileAccessTime($userFile, $vps);

            return [
                'success' => true,
                'location' => 'vps_cache',
                'path' => $cachedPath,
                'ready' => true
            ];
        }

        // File not in cache, need to download
        Log::info("File not in VPS cache, will be downloaded during streaming", [
            'file_id' => $userFile->id,
            'vps_id' => $vps->id
        ]);

        return [
            'success' => true,
            'location' => 'cloud_download',
            'download_required' => true,
            'estimated_time' => $this->estimateDownloadTime($userFile->size)
        ];
    }

    /**
     * Get cached file path on VPS
     */
    private function getCachedFilePath(UserFile $userFile, VpsServer $vps)
    {
        $userId = $userFile->user_id;
        $fileName = $this->sanitizeFileName($userFile->original_name);
        return "{$this->vpsFileCache}user_{$userId}/{$userFile->id}_{$fileName}";
    }

    /**
     * Check if file exists on VPS via SSH
     */
    private function fileExistsOnVps(VpsServer $vps, $filePath)
    {
        // This would be implemented with SSH check
        // For now, we'll track in database/cache
        $cacheKey = "vps_file_exists_{$vps->id}_" . md5($filePath);
        return Cache::get($cacheKey, false);
    }

    /**
     * Mark file as cached on VPS
     */
    public function markFileAsCached(UserFile $userFile, VpsServer $vps, $filePath)
    {
        $cacheKey = "vps_file_exists_{$vps->id}_" . md5($filePath);
        Cache::put($cacheKey, true, now()->addDays(7)); // Cache for 7 days

        // Track file access for cleanup
        $this->updateFileAccessTime($userFile, $vps);

        Log::info("File marked as cached on VPS", [
            'file_id' => $userFile->id,
            'vps_id' => $vps->id,
            'file_path' => $filePath
        ]);
    }

    /**
     * Update file access time for cleanup management
     */
    private function updateFileAccessTime(UserFile $userFile, VpsServer $vps)
    {
        $accessKey = "vps_file_access_{$vps->id}_{$userFile->id}";
        Cache::put($accessKey, now(), now()->addDays(30));
    }

    /**
     * Clean up old files on VPS when storage is full
     */
    public function cleanupOldFiles(VpsServer $vps, $requiredSpace = 0)
    {
        Log::info("Starting VPS file cleanup", [
            'vps_id' => $vps->id,
            'required_space' => $requiredSpace
        ]);

        // Get all cached files with access times
        $cachedFiles = $this->getCachedFilesWithAccessTime($vps);
        
        // Sort by last access time (oldest first)
        $cachedFiles = $cachedFiles->sortBy('last_accessed');

        $freedSpace = 0;
        $cleanedFiles = [];

        foreach ($cachedFiles as $fileInfo) {
            if ($requiredSpace > 0 && $freedSpace >= $requiredSpace) {
                break; // We have enough space
            }

            // Don't delete files accessed in last 24 hours
            if ($fileInfo['last_accessed']->diffInHours(now()) < 24) {
                continue;
            }

            // Don't delete files from currently active streams
            if ($this->isFileInActiveStream($fileInfo['file_id'])) {
                continue;
            }

            // Delete file from VPS
            if ($this->deleteFileFromVps($vps, $fileInfo['file_path'])) {
                $freedSpace += $fileInfo['file_size'];
                $cleanedFiles[] = $fileInfo;
                
                // Remove from cache tracking
                $cacheKey = "vps_file_exists_{$vps->id}_" . md5($fileInfo['file_path']);
                Cache::forget($cacheKey);
                
                $accessKey = "vps_file_access_{$vps->id}_{$fileInfo['file_id']}";
                Cache::forget($accessKey);
            }
        }

        Log::info("VPS file cleanup completed", [
            'vps_id' => $vps->id,
            'files_cleaned' => count($cleanedFiles),
            'space_freed_mb' => round($freedSpace / (1024 * 1024), 2)
        ]);

        return [
            'success' => true,
            'files_cleaned' => count($cleanedFiles),
            'space_freed' => $freedSpace,
            'cleaned_files' => $cleanedFiles
        ];
    }

    /**
     * Get cached files with access time info
     */
    private function getCachedFilesWithAccessTime(VpsServer $vps)
    {
        $files = collect();
        
        // Get all access time cache keys for this VPS
        $pattern = "vps_file_access_{$vps->id}_*";
        // This would need to be implemented with proper cache scanning
        // For now, return empty collection
        
        return $files;
    }

    /**
     * Check if file is being used in active stream
     */
    private function isFileInActiveStream($fileId)
    {
        return StreamConfiguration::where('status', 'STREAMING')
            ->whereJsonContains('video_source_path', function($query) use ($fileId) {
                // Check if file_id exists in the JSON array
                return $query->where('file_id', $fileId);
            })
            ->exists();
    }

    /**
     * Delete file from VPS via SSH
     */
    private function deleteFileFromVps(VpsServer $vps, $filePath)
    {
        // This would be implemented with SSH
        // For now, just log the action
        Log::info("Would delete file from VPS", [
            'vps_id' => $vps->id,
            'file_path' => $filePath
        ]);
        
        return true; // Placeholder
    }

    /**
     * Estimate download time based on file size
     */
    private function estimateDownloadTime($fileSize)
    {
        // Assume 50Mbps download speed from Bunny.net
        $seconds = ($fileSize * 8) / (50 * 1024 * 1024);
        return max(30, $seconds); // Minimum 30 seconds
    }

    /**
     * Sanitize filename for VPS storage
     */
    private function sanitizeFileName($fileName)
    {
        return preg_replace('/[^A-Za-z0-9\._-]/', '_', $fileName);
    }

    /**
     * Get VPS storage statistics
     */
    public function getVpsStorageStats(VpsServer $vps)
    {
        // This would query actual VPS storage via SSH
        // For now, return placeholder data
        return [
            'total_space' => $this->maxCacheSize,
            'used_space' => 0, // Would be calculated from actual files
            'cached_files_count' => 0,
            'last_cleanup' => null
        ];
    }

    /**
     * Schedule cleanup when VPS storage is getting full
     */
    public function scheduleCleanupIfNeeded(VpsServer $vps)
    {
        $stats = $this->getVpsStorageStats($vps);
        $usagePercent = ($stats['used_space'] / $stats['total_space']) * 100;

        if ($usagePercent > 80) { // Cleanup when 80% full
            Log::info("VPS storage usage high, scheduling cleanup", [
                'vps_id' => $vps->id,
                'usage_percent' => $usagePercent
            ]);

            // Queue cleanup job
            \App\Jobs\VpsCleanupJob::dispatch($vps)->delay(now()->addMinutes(5));
        }
    }

    /**
     * Clean up files after stream stops
     */
    public function cleanupAfterStream(StreamConfiguration $stream)
    {
        if (!$stream->vps_server_id) {
            return;
        }

        $vps = $stream->vpsServer;
        $streamId = $stream->id;

        Log::info("Cleaning up files after stream stop", [
            'stream_id' => $streamId,
            'vps_id' => $vps->id
        ]);

        // Files are kept in cache for future use
        // Only temporary stream files are cleaned up
        $tempPaths = [
            "/tmp/stream_{$streamId}",
            "/tmp/job_{$streamId}_*.json",
            "/tmp/stop_job_{$streamId}_*.json"
        ];

        foreach ($tempPaths as $path) {
            Log::info("Would cleanup temp path", [
                'vps_id' => $vps->id,
                'path' => $path
            ]);
            // SSH cleanup would be implemented here
        }

        // Update stream file access times (they might be reused soon)
        if (is_array($stream->video_source_path)) {
            foreach ($stream->video_source_path as $file) {
                if (isset($file['file_id'])) {
                    $userFile = UserFile::find($file['file_id']);
                    if ($userFile) {
                        $this->updateFileAccessTime($userFile, $vps);
                    }
                }
            }
        }
    }
}
