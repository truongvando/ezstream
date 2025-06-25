<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Models\UserFile;
use App\Models\VpsServer;
use App\Services\SshService;

class SyncFileToVpsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600; // 1 hour timeout
    public $tries = 3;

    private UserFile $userFile;
    private VpsServer $sourceVps;
    private VpsServer $targetVps;

    public function __construct(UserFile $userFile, VpsServer $sourceVps, VpsServer $targetVps)
    {
        $this->userFile = $userFile;
        $this->sourceVps = $sourceVps;
        $this->targetVps = $targetVps;
    }

    public function handle(SshService $sshService): void
    {
        Log::info('Starting file sync between VPS servers', [
            'file_id' => $this->userFile->id,
            'source_vps' => $this->sourceVps->id,
            'target_vps' => $this->targetVps->id,
            'file_name' => $this->userFile->original_name
        ]);

        try {
            // 1. Tạo file record cho target VPS
            $targetFileRecord = $this->createTargetFileRecord();

            // 2. Kết nối đến cả 2 VPS
            $sourceConnection = $sshService->connect($this->sourceVps);
            $targetConnection = $sshService->connect($this->targetVps);

            if (!$sourceConnection || !$targetConnection) {
                throw new \Exception('Failed to establish SSH connections');
            }

            // 3. Xác định đường dẫn file
            $sourceFilePath = $this->getFilePathOnVps($this->userFile, $this->sourceVps);
            $targetFilePath = $this->getFilePathOnVps($targetFileRecord, $this->targetVps);

            // 4. Kiểm tra file source có tồn tại không
            if (!$this->fileExistsOnVps($sshService, $sourceConnection, $sourceFilePath)) {
                throw new \Exception('Source file not found on VPS');
            }

            // 5. Tạo thư mục đích nếu chưa có
            $this->ensureDirectoryExists($sshService, $targetConnection, dirname($targetFilePath));

            // 6. Thực hiện sync file
            $this->syncFileViaScp($sourceFilePath, $targetFilePath);

            // 7. Verify file đã được copy thành công
            if (!$this->fileExistsOnVps($sshService, $targetConnection, $targetFilePath)) {
                throw new \Exception('File verification failed after sync');
            }

            // 8. Cập nhật status
            $targetFileRecord->update([
                'status' => 'AVAILABLE',
                'vps_server_id' => $this->targetVps->id
            ]);

            Log::info('File sync completed successfully', [
                'file_id' => $this->userFile->id,
                'source_vps' => $this->sourceVps->id,
                'target_vps' => $this->targetVps->id,
                'target_file_id' => $targetFileRecord->id
            ]);

        } catch (\Exception $e) {
            $this->handleSyncFailure($e);
        }
    }

    /**
     * Tạo file record cho target VPS
     */
    private function createTargetFileRecord(): UserFile
    {
        return UserFile::create([
            'user_id' => $this->userFile->user_id,
            'vps_server_id' => $this->targetVps->id,
            'disk' => 'vps',
            'path' => 'files/' . $this->userFile->id . '_' . $this->userFile->original_name,
            'original_name' => $this->userFile->original_name,
            'mime_type' => $this->userFile->mime_type,
            'size' => $this->userFile->size,
            'status' => 'SYNCING',
            'original_file_id' => $this->userFile->id,
            'download_source' => $this->userFile->download_source ?? 'upload'
        ]);
    }

    /**
     * Lấy đường dẫn file trên VPS
     */
    private function getFilePathOnVps(UserFile $userFile, VpsServer $vps): string
    {
        return "/var/www/files/{$userFile->id}_{$userFile->original_name}";
    }

    /**
     * Kiểm tra file có tồn tại trên VPS không
     */
    private function fileExistsOnVps(SshService $sshService, $connection, string $filePath): bool
    {
        $command = "test -f '{$filePath}' && echo 'EXISTS' || echo 'NOT_EXISTS'";
        $result = $sshService->execute($connection, $command);
        return trim($result) === 'EXISTS';
    }

    /**
     * Đảm bảo thư mục tồn tại
     */
    private function ensureDirectoryExists(SshService $sshService, $connection, string $dirPath): void
    {
        $command = "mkdir -p '{$dirPath}'";
        $sshService->execute($connection, $command);
    }

    /**
     * Sync file qua SCP
     */
    private function syncFileViaScp(string $sourceFilePath, string $targetFilePath): void
    {
        // Tạo SCP command để copy file giữa 2 VPS
        $sourceHost = $this->sourceVps->ip_address;
        $targetHost = $this->targetVps->ip_address;
        $sourceUser = $this->sourceVps->ssh_username ?? 'root';
        $targetUser = $this->targetVps->ssh_username ?? 'root';

        // Method 1: Copy qua server trung gian (recommended)
        $this->syncViaIntermediateServer($sourceFilePath, $targetFilePath);
    }

    /**
     * Sync qua server trung gian
     */
    private function syncViaIntermediateServer(string $sourceFilePath, string $targetFilePath): void
    {
        $sshService = app(SshService::class);
        
        // 1. Download file từ source VPS về server chính
        $tempLocalPath = storage_path('app/temp_sync/' . uniqid() . '_' . basename($sourceFilePath));
        $tempDir = dirname($tempLocalPath);
        
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        // Download từ source VPS
        $downloadCommand = sprintf(
            'scp -o StrictHostKeyChecking=no %s@%s:"%s" "%s"',
            $this->sourceVps->ssh_username ?? 'root',
            $this->sourceVps->ip_address,
            $sourceFilePath,
            $tempLocalPath
        );

        $result = shell_exec($downloadCommand . ' 2>&1');
        
        if (!file_exists($tempLocalPath)) {
            throw new \Exception('Failed to download file from source VPS: ' . $result);
        }

        // 2. Upload file lên target VPS
        $uploadCommand = sprintf(
            'scp -o StrictHostKeyChecking=no "%s" %s@%s:"%s"',
            $tempLocalPath,
            $this->targetVps->ssh_username ?? 'root',
            $this->targetVps->ip_address,
            $targetFilePath
        );

        $result = shell_exec($uploadCommand . ' 2>&1');

        // 3. Cleanup temp file
        if (file_exists($tempLocalPath)) {
            unlink($tempLocalPath);
        }

        // Verify upload success bằng cách check file size
        $targetConnection = $sshService->connect($this->targetVps);
        if ($targetConnection) {
            $sizeCommand = "stat -c%s '{$targetFilePath}' 2>/dev/null || echo '0'";
            $targetSize = (int) trim($sshService->execute($targetConnection, $sizeCommand));
            
            if ($targetSize !== $this->userFile->size) {
                throw new \Exception("File size mismatch after sync. Expected: {$this->userFile->size}, Got: {$targetSize}");
            }
        }
    }

    /**
     * Xử lý lỗi sync
     */
    private function handleSyncFailure(\Exception $e): void
    {
        Log::error('File sync between VPS failed', [
            'file_id' => $this->userFile->id,
            'source_vps' => $this->sourceVps->id,
            'target_vps' => $this->targetVps->id,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        // Cập nhật status các file record liên quan
        UserFile::where('original_file_id', $this->userFile->id)
            ->where('vps_server_id', $this->targetVps->id)
            ->where('status', 'SYNCING')
            ->update([
                'status' => 'SYNC_FAILED',
                'error_message' => $e->getMessage()
            ]);

        // Re-throw để job retry
        throw $e;
    }

    /**
     * Job failed permanently
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('File sync job failed permanently', [
            'file_id' => $this->userFile->id,
            'source_vps' => $this->sourceVps->id,
            'target_vps' => $this->targetVps->id,
            'error' => $exception->getMessage()
        ]);

        // Mark all related records as failed
        UserFile::where('original_file_id', $this->userFile->id)
            ->where('vps_server_id', $this->targetVps->id)
            ->where('status', 'SYNCING')
            ->update([
                'status' => 'SYNC_FAILED',
                'error_message' => 'Sync failed after multiple retries: ' . $exception->getMessage()
            ]);
    }
}
