<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\UserFile;
use App\Models\VpsServer;
use App\Jobs\SyncToCloudJob;
use App\Jobs\DistributeFileJob;
use Exception;

class SmartStorageService
{
    protected $maxUserStorage = 5 * 1024 * 1024 * 1024; // 5GB per user default
    protected $vpsStorageLimit = 80 * 1024 * 1024 * 1024; // 80GB per VPS
    protected $uploadPath = 'user_files';

    /**
     * Upload file with smart distribution
     */
    public function uploadFile($source, $fileName, $mimeType = null)
    {
        try {
            $user = auth()->user();
            $isUploadedFile = $source instanceof \Illuminate\Http\UploadedFile;
            $fileSize = $isUploadedFile ? $source->getSize() : filesize($source);

            // Check user storage quota
            $userUsedStorage = $this->getUserStorageUsage($user->id);
            $userLimit = $this->getUserStorageLimit($user->id);
            
            if (($userUsedStorage + $fileSize) > $userLimit) {
                return [
                    'success' => false,
                    'error' => 'Storage quota exceeded. Used: ' . $this->formatBytes($userUsedStorage) . 
                              ', Limit: ' . $this->formatBytes($userLimit)
                ];
            }

            // Find best VPS for storage
            $targetVps = $this->findBestVpsForStorage($fileSize);
            if (!$targetVps) {
                return [
                    'success' => false,
                    'error' => 'No VPS available with sufficient storage'
                ];
            }

            // Generate file path
            $uniqueFileName = time() . '_' . $user->id . '_' . $fileName;
            $relativePath = $this->uploadPath . '/' . date('Y/m/d') . '/' . $uniqueFileName;

            Log::info("Smart upload starting", [
                'user_id' => $user->id,
                'file_name' => $fileName,
                'file_size' => $fileSize,
                'target_vps' => $targetVps->id,
                'user_storage_used' => $userUsedStorage,
                'user_storage_limit' => $userLimit
            ]);

            // Store file locally first
            if ($isUploadedFile) {
                $localPath = $source->storeAs(
                    $this->uploadPath . '/' . date('Y/m/d'), 
                    $uniqueFileName, 
                    'local'
                );
            } else {
                $localPath = Storage::disk('local')->putFileAs(
                    $this->uploadPath . '/' . date('Y/m/d'),
                    $source,
                    $uniqueFileName
                );
            }

            if (!$localPath) {
                throw new Exception('Failed to store file locally');
            }

            // Create database record
            $userFile = $user->files()->create([
                'disk' => 'smart_storage',
                'path' => $localPath,
                'original_name' => $fileName,
                'mime_type' => $mimeType ?: mime_content_type(Storage::disk('local')->path($localPath)),
                'size' => $fileSize,
                'status' => 'AVAILABLE',
                'primary_vps_id' => $targetVps->id,
                'storage_locations' => json_encode([
                    'main_server' => ['path' => $localPath, 'status' => 'available'],
                    'target_vps' => ['vps_id' => $targetVps->id, 'status' => 'pending'],
                    'cloud_backup' => ['status' => 'pending']
                ])
            ]);

            // Update VPS storage usage
            $this->updateVpsStorageUsage($targetVps->id, $fileSize);

            // Queue file distribution to target VPS
            DistributeFileJob::dispatch($userFile, $targetVps)->delay(now()->addMinutes(2));

            // Queue cloud backup
            SyncToCloudJob::dispatch($userFile)->delay(now()->addMinutes(10));

            Log::info("Smart upload completed", [
                'user_file_id' => $userFile->id,
                'primary_vps' => $targetVps->id
            ]);

            return [
                'success' => true,
                'file_id' => $userFile->id,
                'file_name' => $fileName,
                'file_size' => $fileSize,
                'primary_vps' => $targetVps->name,
                'storage_type' => 'smart_distributed',
                'user_storage_used' => $this->formatBytes($userUsedStorage + $fileSize),
                'user_storage_limit' => $this->formatBytes($userLimit)
            ];

        } catch (Exception $e) {
            Log::error('Smart upload failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get file for streaming - find best available location
     */
    public function getFileForStreaming(UserFile $userFile, VpsServer $streamingVps)
    {
        $locations = json_decode($userFile->storage_locations, true);
        
        Log::info("Finding file for streaming", [
            'file_id' => $userFile->id,
            'streaming_vps' => $streamingVps->id,
            'primary_vps' => $userFile->primary_vps_id
        ]);

        // Priority 1: File is on the streaming VPS
        if ($userFile->primary_vps_id == $streamingVps->id) {
            $vpsPath = $this->getVpsFilePath($userFile, $streamingVps);
            if ($this->checkFileExistsOnVps($streamingVps, $vpsPath)) {
                return [
                    'success' => true,
                    'location' => 'local_vps',
                    'path' => $vpsPath,
                    'vps_id' => $streamingVps->id
                ];
            }
        }

        // Priority 2: Transfer from primary VPS to streaming VPS
        if ($userFile->primary_vps_id != $streamingVps->id) {
            $primaryVps = VpsServer::find($userFile->primary_vps_id);
            if ($primaryVps && $primaryVps->status === 'ACTIVE') {
                // Queue transfer job
                $this->transferFileBetweenVps($userFile, $primaryVps, $streamingVps);
                
                return [
                    'success' => true,
                    'location' => 'transferring',
                    'message' => 'File is being transferred to streaming VPS',
                    'estimated_time' => $this->estimateTransferTime($userFile->size)
                ];
            }
        }

        // Priority 3: Download from cloud backup
        if (isset($locations['cloud_backup']['status']) && $locations['cloud_backup']['status'] === 'completed') {
            return [
                'success' => true,
                'location' => 'cloud_backup',
                'download_required' => true,
                'estimated_time' => $this->estimateDownloadTime($userFile->size)
            ];
        }

        // Priority 4: File available on main server
        if (isset($locations['main_server']['status']) && $locations['main_server']['status'] === 'available') {
            $mainServerPath = Storage::disk('local')->path($locations['main_server']['path']);
            if (file_exists($mainServerPath)) {
                return [
                    'success' => true,
                    'location' => 'main_server',
                    'path' => $mainServerPath,
                    'transfer_required' => true
                ];
            }
        }

        return [
            'success' => false,
            'error' => 'File not available in any location'
        ];
    }

    /**
     * Find best VPS for storing new file
     */
    private function findBestVpsForStorage($fileSize)
    {
        return VpsServer::where('status', 'ACTIVE')
            ->whereRaw('(storage_used + ?) < storage_limit', [$fileSize])
            ->orderBy('storage_used', 'asc') // Choose VPS with most free space
            ->first();
    }

    /**
     * Get user storage usage
     */
    private function getUserStorageUsage($userId)
    {
        return UserFile::where('user_id', $userId)
            ->where('status', '!=', 'DELETED')
            ->sum('size');
    }

    /**
     * Get user storage limit based on plan
     */
    private function getUserStorageLimit($userId)
    {
        // TODO: Implement user plans/subscriptions
        // For now, return default limit
        return $this->maxUserStorage;
    }

    /**
     * Update VPS storage usage
     */
    private function updateVpsStorageUsage($vpsId, $sizeChange)
    {
        VpsServer::where('id', $vpsId)->increment('storage_used', $sizeChange);
    }

    /**
     * Get VPS file path
     */
    private function getVpsFilePath(UserFile $userFile, VpsServer $vps)
    {
        return "/opt/user_files/" . basename($userFile->path);
    }

    /**
     * Check if file exists on VPS via SSH
     */
    private function checkFileExistsOnVps(VpsServer $vps, $filePath)
    {
        // TODO: Implement SSH check
        // ssh user@vps "test -f $filePath && echo 'exists' || echo 'not_found'"
        return false; // Placeholder
    }

    /**
     * Transfer file between VPS servers
     */
    private function transferFileBetweenVps(UserFile $userFile, VpsServer $sourceVps, VpsServer $targetVps)
    {
        // Queue transfer job
        Log::info("Queuing VPS-to-VPS transfer", [
            'file_id' => $userFile->id,
            'source_vps' => $sourceVps->id,
            'target_vps' => $targetVps->id
        ]);
        
        // TODO: Implement VPS transfer job
        // This would use SCP or rsync between VPS servers
    }

    /**
     * Estimate transfer time based on file size
     */
    private function estimateTransferTime($fileSize)
    {
        // Assume 100Mbps between VPS servers
        $seconds = ($fileSize * 8) / (100 * 1024 * 1024); // Convert to seconds
        return max(30, $seconds); // Minimum 30 seconds
    }

    /**
     * Estimate download time from cloud
     */
    private function estimateDownloadTime($fileSize)
    {
        // Assume 50Mbps from cloud
        $seconds = ($fileSize * 8) / (50 * 1024 * 1024);
        return max(60, $seconds); // Minimum 1 minute
    }

    /**
     * Format bytes to human readable
     */
    private function formatBytes($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Get storage statistics
     */
    public function getStorageStats()
    {
        $totalFiles = UserFile::where('disk', 'smart_storage')->count();
        $totalSize = UserFile::where('disk', 'smart_storage')->sum('size');
        
        $vpsStats = VpsServer::select('id', 'name', 'storage_used', 'storage_limit')
            ->where('status', 'ACTIVE')
            ->get()
            ->map(function($vps) {
                return [
                    'vps_name' => $vps->name,
                    'used' => $this->formatBytes($vps->storage_used),
                    'limit' => $this->formatBytes($vps->storage_limit),
                    'usage_percent' => $vps->storage_limit > 0 ? 
                        round(($vps->storage_used / $vps->storage_limit) * 100, 1) : 0
                ];
            });

        return [
            'total_files' => $totalFiles,
            'total_size' => $this->formatBytes($totalSize),
            'vps_storage' => $vpsStats,
            'cloud_backup_pending' => UserFile::where('disk', 'smart_storage')
                ->whereJsonContains('storage_locations->cloud_backup->status', 'pending')
                ->count()
        ];
    }
}
