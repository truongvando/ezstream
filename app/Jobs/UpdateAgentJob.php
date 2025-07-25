<?php

namespace App\Jobs;

use App\Models\VpsServer;
use App\Services\SshService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UpdateAgentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $vpsId;
    public $timeout = 300; // 5 phút timeout

    public function __construct(int $vpsId)
    {
        $this->vpsId = $vpsId;
    }

    public function handle(SshService $sshService): void
    {
        $vps = VpsServer::findOrFail($this->vpsId);

        Log::info("🔄 [VPS #{$vps->id}] Bắt đầu cập nhật Redis Agent v3.0");

        try {
            // Update VPS status
            $vps->update([
                'status' => 'UPDATING',
                'status_message' => 'Đang cập nhật Redis Agent...'
            ]);

            // Check if VPS operations are enabled for this environment
            if (!config('deployment.vps_operations_enabled')) {
                Log::info("🔧 [VPS #{$vps->id}] VPS operations disabled in " . config('app.env') . " environment - mocking update");
                $this->mockUpdateSuccess($vps);
                return;
            }

            // Connect to VPS
            if (!$sshService->connect($vps)) {
                throw new \Exception('Không thể kết nối tới VPS qua SSH');
            }

            Log::info("✅ [VPS #{$vps->id}] Kết nối SSH thành công");

            // Step 1: Stop current agent
            $this->stopCurrentAgent($sshService, $vps);

            // Step 2: Backup current agent
            $this->backupCurrentAgent($sshService, $vps);

            // Step 3: Upload new agent files
            $this->uploadNewAgentFiles($sshService, $vps);

            // Step 4: Update systemd service
            $this->updateSystemdService($sshService, $vps);

            // Step 5: Start new agent
            $this->startNewAgent($sshService, $vps);

            // Step 6: Verify agent is running
            $this->verifyAgentRunning($sshService, $vps);

            // Update status to active
            $vps->update([
                'status' => 'ACTIVE',
                'status_message' => 'Redis Agent đã được cập nhật thành công'
            ]);

            Log::info("✅ [VPS #{$vps->id}] Cập nhật Redis Agent v3.0 hoàn tất");

        } catch (\Exception $e) {
            Log::error("❌ [VPS #{$vps->id}] Cập nhật Redis Agent thất bại: {$e->getMessage()}", [
                'trace' => $e->getTraceAsString()
            ]);

            // Try to rollback
            $this->rollbackAgent($sshService, $vps);

            $vps->update([
                'status' => 'ERROR',
                'status_message' => 'Cập nhật thất bại: ' . $e->getMessage()
            ]);
            
            throw $e;
        } finally {
            $sshService->disconnect();
        }
    }

    private function stopCurrentAgent(SshService $sshService, VpsServer $vps): void
    {
        Log::info("🛑 [VPS #{$vps->id}] Dừng agent hiện tại");
        
        $sshService->execute('systemctl stop ezstream-agent');
        sleep(3); // Wait for graceful shutdown
        
        Log::info("✅ [VPS #{$vps->id}] Đã dừng agent hiện tại");
    }

    private function backupCurrentAgent(SshService $sshService, VpsServer $vps): void
    {
        Log::info("💾 [VPS #{$vps->id}] Sao lưu agent hiện tại");
        
        $timestamp = date('Y-m-d_H-i-s');
        $backupDir = "/opt/ezstream-agent-backup-{$timestamp}";
        
        // Chỉ sao lưu nếu thư mục tồn tại
        $checkDir = $sshService->execute("test -d /opt/ezstream-agent && echo 'exists'");
        if (trim($checkDir) === 'exists') {
            $sshService->execute("cp -r /opt/ezstream-agent {$backupDir}");
            Log::info("✅ [VPS #{$vps->id}] Agent đã được sao lưu tại {$backupDir}");
        } else {
            Log::info("⚠️ [VPS #{$vps->id}] Không tìm thấy thư mục agent để sao lưu");
        }
    }

    private function uploadNewAgentFiles(SshService $sshService, VpsServer $vps): void
    {
        Log::info("📤 [VPS #{$vps->id}] Đang upload các file Redis Agent v3.0");

        $remoteDir = '/opt/ezstream-agent';
        $sshService->execute("mkdir -p {$remoteDir}");

        // Upload all agent files
        $agentFiles = [
            'agent.py',           // Main entry point
            'config.py',          // Configuration management
            'stream_manager.py',  // Stream lifecycle management
            'process_manager.py', // FFmpeg process management
            'file_manager.py',    // File download/cleanup
            'status_reporter.py', // Status reporting
            'command_handler.py', // Command processing
            'utils.py'            // Shared utilities
        ];

        $uploadedCount = 0;
        foreach ($agentFiles as $filename) {
            $localPath = storage_path("app/ezstream-agent/{$filename}");
            $remotePath = "{$remoteDir}/{$filename}";

            if (!file_exists($localPath)) {
                Log::warning("⚠️ [VPS #{$vps->id}] Không tìm thấy file: {$filename}");
                continue;
            }

            if (!$sshService->uploadFile($localPath, $remotePath)) {
                throw new \Exception("Không thể upload file: {$filename}");
            }

            if ($filename === 'agent.py') {
                $sshService->execute("chmod +x {$remotePath}");
            }

            $uploadedCount++;
        }

        Log::info("✅ [VPS #{$vps->id}] Đã upload {$uploadedCount} file Redis Agent");
    }

    private function updateSystemdService(SshService $sshService, VpsServer $vps): void
    {
        Log::info("⚙️ [VPS #{$vps->id}] Cập nhật systemd service");
        
        // Get Redis connection details
        $redisHost = config('database.redis.default.host', '127.0.0.1');
        $redisPort = config('database.redis.default.port', 6379);
        $redisPassword = config('database.redis.default.password');

        // Generate new service file
        $remoteAgentPath = "/opt/ezstream-agent/agent.py";
        $serviceContent = $this->generateSystemdService($remoteAgentPath, $redisHost, $redisPort, $redisPassword, $vps);
        
        // Ghi trực tiếp vào file service
        $sshService->execute("cat > /etc/systemd/system/ezstream-agent.service << 'EOF'\n{$serviceContent}\nEOF");
        
        // Reload systemd
        $sshService->execute('systemctl daemon-reload');
        $sshService->execute('systemctl enable ezstream-agent');
        
        Log::info("✅ [VPS #{$vps->id}] Systemd service đã được cập nhật");
    }

    private function startNewAgent(SshService $sshService, VpsServer $vps): void
    {
        Log::info("🚀 [VPS #{$vps->id}] Khởi động Redis Agent mới");
        
        $sshService->execute('systemctl restart ezstream-agent');
        sleep(5); // Wait for startup
        
        Log::info("✅ [VPS #{$vps->id}] Redis Agent mới đã được khởi động");
    }

    private function verifyAgentRunning(SshService $sshService, VpsServer $vps): void
    {
        Log::info("🔍 [VPS #{$vps->id}] Kiểm tra Redis Agent đang chạy");

        // Retry logic - wait up to 30 seconds for agent to start
        $maxRetries = 6;
        $retryDelay = 5;

        for ($i = 0; $i < $maxRetries; $i++) {
            $status = $sshService->execute('systemctl is-active ezstream-agent');

            if (trim($status) === 'active') {
                Log::info("✅ [VPS #{$vps->id}] Redis Agent đang hoạt động bình thường");
                return;
            }

            if ($i < $maxRetries - 1) {
                Log::info("⏳ [VPS #{$vps->id}] Agent chưa sẵn sàng, đợi {$retryDelay}s... (lần thử " . ($i + 1) . "/{$maxRetries})");
                sleep($retryDelay);
            }
        }

        // If we get here, agent failed to start
        $serviceLog = $sshService->execute("journalctl -u ezstream-agent --no-pager -n 50");
        $systemdStatus = $sshService->execute("systemctl status ezstream-agent --no-pager");

        Log::error("❌ [VPS #{$vps->id}] Redis Agent không hoạt động sau {$maxRetries} lần thử", [
            'status' => trim($status),
            'systemd_status' => $systemdStatus,
            'service_log' => $serviceLog
        ]);

        throw new \Exception('Redis Agent không khởi động được sau ' . ($maxRetries * $retryDelay) . ' giây. Kiểm tra log trên VPS.');
    }

    private function rollbackAgent(SshService $sshService, VpsServer $vps): void
    {
        try {
            Log::info("🔄 [VPS #{$vps->id}] Đang khôi phục phiên bản cũ");
            
            // Find latest backup
            $backups = $sshService->execute('ls -t /opt/ezstream-agent-backup-* 2>/dev/null | head -1');
            $latestBackup = trim($backups);
            
            if ($latestBackup) {
                $sshService->execute('systemctl stop ezstream-agent');
                $sshService->execute("rm -rf /opt/ezstream-agent");
                $sshService->execute("mv {$latestBackup} /opt/ezstream-agent");
                $sshService->execute('systemctl start ezstream-agent');
                
                Log::info("✅ [VPS #{$vps->id}] Khôi phục thành công");
            } else {
                Log::error("❌ [VPS #{$vps->id}] Không tìm thấy bản sao lưu để khôi phục");
            }
            
        } catch (\Exception $e) {
            Log::error("❌ [VPS #{$vps->id}] Khôi phục thất bại: {$e->getMessage()}");
        }
    }

    private function generateSystemdService(string $agentPath, string $redisHost, int $redisPort, ?string $redisPassword, VpsServer $vps): string
    {
        $pythonCmd = "/usr/bin/python3";

        // Build command arguments
        $commandArgs = "{$vps->id} {$redisHost} {$redisPort}";
        if ($redisPassword) {
            $commandArgs .= " '{$redisPassword}'";
        }

        $command = "{$pythonCmd} {$agentPath} {$commandArgs}";

        return "[Unit]
Description=EZStream Redis Agent v3.0
After=network.target nginx.service
Requires=nginx.service

[Service]
Type=simple
User=root
WorkingDirectory=/opt/ezstream-agent
ExecStart={$command}
Restart=always
RestartSec=10
StandardOutput=journal
StandardError=journal
Environment=PYTHONPATH=/opt/ezstream-agent
Environment=PYTHONUNBUFFERED=1

[Install]
WantedBy=multi-user.target";
    }

    private function mockUpdateSuccess(VpsServer $vps): void
    {
        Log::info("🎭 [VPS #{$vps->id}] Mocking agent update success for development environment");

        // Simulate update delay
        sleep(1);

        $vps->update([
            'status' => 'ACTIVE',
            'status_message' => 'Redis Agent đã được cập nhật thành công (mocked)',
            'last_updated_at' => now(),
        ]);

        Log::info("✅ [VPS #{$vps->id}] Mock agent update completed successfully");
    }
}
