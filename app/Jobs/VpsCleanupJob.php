<?php

namespace App\Jobs;

use App\Models\VpsServer;
use App\Services\VpsFileManagerService;
use App\Services\SshService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class VpsCleanupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes timeout

    /**
     * Create a new job instance.
     */
    public function __construct(public VpsServer $vps, public int $requiredSpace = 0)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(VpsFileManagerService $fileManager, SshService $sshService): void
    {
        Log::info("Starting VPS cleanup job", [
            'vps_id' => $this->vps->id,
            'vps_name' => $this->vps->name,
            'required_space' => $this->requiredSpace
        ]);

        try {
            // Connect to VPS
            if (!$sshService->connect($this->vps)) {
                throw new \Exception('Failed to connect to VPS via SSH');
            }

            // Get current storage usage
            $storageStats = $this->getVpsStorageUsage($sshService);
            
            Log::info("VPS storage stats", [
                'vps_id' => $this->vps->id,
                'total_space_gb' => round($storageStats['total'] / (1024 * 1024 * 1024), 2),
                'used_space_gb' => round($storageStats['used'] / (1024 * 1024 * 1024), 2),
                'usage_percent' => $storageStats['usage_percent']
            ]);

            // Cleanup if usage is high or specific space is required
            if ($storageStats['usage_percent'] > 75 || $this->requiredSpace > 0) {
                $this->performCleanup($sshService, $fileManager, $storageStats);
            } else {
                Log::info("VPS storage usage is acceptable, no cleanup needed", [
                    'vps_id' => $this->vps->id,
                    'usage_percent' => $storageStats['usage_percent']
                ]);
            }

            $sshService->disconnect();

        } catch (\Exception $e) {
            Log::error("VPS cleanup job failed", [
                'vps_id' => $this->vps->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get VPS storage usage via SSH
     */
    private function getVpsStorageUsage(SshService $sshService)
    {
        // Get disk usage for user files directory
        $command = "df -B1 /opt/user_files 2>/dev/null | tail -1 | awk '{print $2,$3,$4}'";
        $output = $sshService->execute($command);
        
        if ($output) {
            $parts = explode(' ', trim($output));
            if (count($parts) >= 3) {
                $total = (int)$parts[0];
                $used = (int)$parts[1];
                $available = (int)$parts[2];
                
                return [
                    'total' => $total,
                    'used' => $used,
                    'available' => $available,
                    'usage_percent' => $total > 0 ? round(($used / $total) * 100, 2) : 0
                ];
            }
        }

        // Fallback: get root filesystem usage
        $command = "df -B1 / | tail -1 | awk '{print $2,$3,$4}'";
        $output = $sshService->execute($command);
        
        if ($output) {
            $parts = explode(' ', trim($output));
            if (count($parts) >= 3) {
                $total = (int)$parts[0];
                $used = (int)$parts[1];
                $available = (int)$parts[2];
                
                return [
                    'total' => $total,
                    'used' => $used,
                    'available' => $available,
                    'usage_percent' => $total > 0 ? round(($used / $total) * 100, 2) : 0
                ];
            }
        }

        return [
            'total' => 0,
            'used' => 0,
            'available' => 0,
            'usage_percent' => 0
        ];
    }

    /**
     * Perform actual cleanup
     */
    private function performCleanup(SshService $sshService, VpsFileManagerService $fileManager, array $storageStats)
    {
        Log::info("Starting VPS cleanup process", [
            'vps_id' => $this->vps->id,
            'current_usage_percent' => $storageStats['usage_percent'],
            'required_space' => $this->requiredSpace
        ]);

        $cleanedFiles = 0;
        $freedSpace = 0;

        // 1. Clean up temporary stream files (safe to delete)
        $tempCleanup = $this->cleanupTempFiles($sshService);
        $cleanedFiles += $tempCleanup['files_cleaned'];
        $freedSpace += $tempCleanup['space_freed'];

        // 2. Clean up old cached user files (if still needed)
        if ($storageStats['usage_percent'] > 70 || $this->requiredSpace > $freedSpace) {
            $cacheCleanup = $this->cleanupOldCachedFiles($sshService);
            $cleanedFiles += $cacheCleanup['files_cleaned'];
            $freedSpace += $cacheCleanup['space_freed'];
        }

        // 3. Clean up logs older than 7 days
        $logCleanup = $this->cleanupOldLogs($sshService);
        $cleanedFiles += $logCleanup['files_cleaned'];
        $freedSpace += $logCleanup['space_freed'];

        Log::info("VPS cleanup completed", [
            'vps_id' => $this->vps->id,
            'total_files_cleaned' => $cleanedFiles,
            'total_space_freed_mb' => round($freedSpace / (1024 * 1024), 2)
        ]);
    }

    /**
     * Clean up temporary stream files
     */
    private function cleanupTempFiles(SshService $sshService)
    {
        Log::info("Cleaning up temporary stream files", ['vps_id' => $this->vps->id]);

        $commands = [
            // Remove old stream temp directories (older than 1 hour)
            "find /tmp -name 'stream_*' -type d -mmin +60 -exec rm -rf {} + 2>/dev/null || true",
            
            // Remove old job files (older than 2 hours)
            "find /tmp -name 'job_*.json' -mmin +120 -delete 2>/dev/null || true",
            "find /tmp -name 'stop_job_*.json' -mmin +120 -delete 2>/dev/null || true",
            
            // Remove old ffmpeg log files
            "find /tmp -name '*.log' -mmin +1440 -delete 2>/dev/null || true", // 24 hours
        ];

        $filesCleanedTotal = 0;
        foreach ($commands as $command) {
            $output = $sshService->execute($command);
            // Count would be implemented if needed
        }

        return [
            'files_cleaned' => $filesCleanedTotal,
            'space_freed' => 0 // Would be calculated if needed
        ];
    }

    /**
     * Clean up old cached user files
     */
    private function cleanupOldCachedFiles(SshService $sshService)
    {
        Log::info("Cleaning up old cached user files", ['vps_id' => $this->vps->id]);

        // Find files in user cache older than 7 days and not accessed recently
        $command = "find /opt/user_files -type f -atime +7 -exec ls -la {} + 2>/dev/null | wc -l";
        $oldFilesCount = (int)trim($sshService->execute($command));

        if ($oldFilesCount > 0) {
            // Get size before cleanup
            $sizeBefore = $this->getDirectorySize($sshService, '/opt/user_files');
            
            // Remove old files (accessed more than 7 days ago)
            $cleanupCommand = "find /opt/user_files -type f -atime +7 -delete 2>/dev/null || true";
            $sshService->execute($cleanupCommand);
            
            // Get size after cleanup
            $sizeAfter = $this->getDirectorySize($sshService, '/opt/user_files');
            $spaceFreed = $sizeBefore - $sizeAfter;

            Log::info("Old cached files cleanup completed", [
                'vps_id' => $this->vps->id,
                'files_cleaned' => $oldFilesCount,
                'space_freed_mb' => round($spaceFreed / (1024 * 1024), 2)
            ]);

            return [
                'files_cleaned' => $oldFilesCount,
                'space_freed' => $spaceFreed
            ];
        }

        return [
            'files_cleaned' => 0,
            'space_freed' => 0
        ];
    }

    /**
     * Clean up old log files
     */
    private function cleanupOldLogs(SshService $sshService)
    {
        Log::info("Cleaning up old log files", ['vps_id' => $this->vps->id]);

        $commands = [
            // Clean up old streaming agent logs
            "find /opt/streaming_agent -name '*.log' -mtime +7 -delete 2>/dev/null || true",
            
            // Clean up old system logs if they're taking too much space
            "journalctl --vacuum-time=7d 2>/dev/null || true",
            
            // Clean up old nginx logs
            "find /var/log/nginx -name '*.log.*' -mtime +7 -delete 2>/dev/null || true",
        ];

        foreach ($commands as $command) {
            $sshService->execute($command);
        }

        return [
            'files_cleaned' => 0, // Would be counted if needed
            'space_freed' => 0
        ];
    }

    /**
     * Get directory size in bytes
     */
    private function getDirectorySize(SshService $sshService, string $directory)
    {
        $command = "du -sb {$directory} 2>/dev/null | cut -f1";
        $output = trim($sshService->execute($command));
        return (int)$output;
    }
}
