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
use Illuminate\Support\Facades\Redis;

class UpdateAgentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $vpsId;

    // Job configuration - increased for production stability
    public $timeout = 900; // 15 minutes timeout for production
    public $tries = 1;     // Don't retry automatically
    public $maxExceptions = 1;

    public function __construct(int $vpsId)
    {
        $this->vpsId = $vpsId;
    }

    public function handle(SshService $sshService): void
    {
        $vps = VpsServer::findOrFail($this->vpsId);

        Log::info("🔄 [VPS #{$vps->id}] Bắt đầu cập nhật EZStream Agent v5.0");

        try {
            // Update VPS status
            $vps->update([
                'status' => 'UPDATING',
                'status_message' => 'Đang cập nhật EZStream Agent v5.0...'
            ]);

            // Initialize progress tracking
            $this->setUpdateProgress($vps->id, 'starting', 5, 'Bắt đầu cập nhật EZStream Agent v5.0');

            // Check if VPS operations are enabled for this environment
            if (!config('deployment.vps_operations_enabled')) {
                Log::info("🔧 [VPS #{$vps->id}] VPS operations disabled in " . config('app.env') . " environment - mocking update");
                $this->mockUpdateSuccess($vps);
                return;
            }

            // Connect to VPS with retry logic
            $maxRetries = 3;
            $connected = false;

            for ($i = 0; $i < $maxRetries; $i++) {
                if ($sshService->connect($vps)) {
                    $connected = true;
                    break;
                }

                if ($i < $maxRetries - 1) {
                    Log::warning("🔄 [VPS #{$vps->id}] SSH connection failed, retrying... (" . ($i + 1) . "/{$maxRetries})");
                    sleep(5);
                }
            }

            if (!$connected) {
                throw new \Exception("Không thể kết nối tới VPS qua SSH sau {$maxRetries} lần thử");
            }

            Log::info("✅ [VPS #{$vps->id}] Kết nối SSH thành công");
            $this->setUpdateProgress($vps->id, 'connected', 10, 'Kết nối SSH thành công');

            // Step 1: Stop current agent
            $this->setUpdateProgress($vps->id, 'stopping', 20, 'Dừng agent hiện tại');
            $this->stopCurrentAgent($sshService, $vps);

            // Step 2: Backup current agent
            $this->setUpdateProgress($vps->id, 'backup', 30, 'Sao lưu agent hiện tại');
            $this->backupCurrentAgent($sshService, $vps);

            // Step 3: Upload new agent files
            $this->setUpdateProgress($vps->id, 'uploading', 50, 'Upload agent files v3.0');
            $this->uploadNewAgentFiles($sshService, $vps);

            // Step 4: Update systemd service
            $this->setUpdateProgress($vps->id, 'systemd', 70, 'Cập nhật systemd service');
            $this->updateSystemdService($sshService, $vps);

            // Step 5: Start new agent
            $this->setUpdateProgress($vps->id, 'starting', 80, 'Khởi động EZStream Agent v5.0');
            $this->startNewAgent($sshService, $vps);

            // Step 6: Verify agent is running
            $this->setUpdateProgress($vps->id, 'verifying', 90, 'Kiểm tra agent đang chạy');
            $this->verifyAgentRunning($sshService, $vps);

            // Step 7: Verify agent compatibility
            $this->setUpdateProgress($vps->id, 'compatibility', 95, 'Kiểm tra tương thích v5.0');
            $this->verifyAgentCompatibility($sshService, $vps);

            // Update status to active
            $this->setUpdateProgress($vps->id, 'completed', 100, 'Cập nhật EZStream Agent v5.0 hoàn tất');
            $vps->update([
                'status' => 'ACTIVE',
                'status_message' => 'EZStream Agent v5.0 đã được cập nhật thành công'
            ]);

            Log::info("✅ [VPS #{$vps->id}] Cập nhật EZStream Agent v5.0 hoàn tất");

        } catch (\Exception $e) {
            $this->setUpdateProgress($vps->id, 'error', 0, 'Lỗi: ' . $e->getMessage());

            Log::error("❌ [VPS #{$vps->id}] Cập nhật EZStream Agent thất bại: {$e->getMessage()}", [
                'trace' => $e->getTraceAsString(),
                'vps_name' => $vps->name,
                'error_type' => get_class($e)
            ]);

            // Try to rollback
            try {
                $this->rollbackAgent($sshService, $vps);
                Log::info("🔄 [VPS #{$vps->id}] Rollback completed");
            } catch (\Exception $rollbackError) {
                Log::error("❌ [VPS #{$vps->id}] Rollback failed: {$rollbackError->getMessage()}");
            }

            // Always reset status - never leave VPS in UPDATING state
            $vps->update([
                'status' => 'ERROR',
                'status_message' => 'Cập nhật thất bại: ' . $e->getMessage() . ' (Lúc: ' . now()->format('H:i:s') . ')'
            ]);

            // Don't re-throw to prevent job retry
            Log::error("❌ [VPS #{$vps->id}] UpdateAgentJob marked as failed, VPS status reset to ERROR");
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
        Log::info("📤 [VPS #{$vps->id}] Đang upload các file EZStream Agent v5.0");

        $remoteDir = '/opt/ezstream-agent';
        $sshService->execute("mkdir -p {$remoteDir}");

        // Get all agent files from agent directory
        $agentDir = storage_path('app/ezstream-agent');
        $pythonFiles = glob($agentDir . '/*.py');
        $configFiles = glob($agentDir . '/*.conf');
        $shellFiles = glob($agentDir . '/*.sh');

        $agentFiles = [];

        // Add Python files
        foreach ($pythonFiles as $file) {
            $agentFiles[] = basename($file);
        }

        // Add config files
        foreach ($configFiles as $file) {
            $agentFiles[] = basename($file);
        }

        // Add shell scripts
        foreach ($shellFiles as $file) {
            $agentFiles[] = basename($file);
        }

        $uploadedCount = 0;
        foreach ($agentFiles as $filename) {
            $localPath = storage_path("app/ezstream-agent/{$filename}");
            $remotePath = "{$remoteDir}/{$filename}";

            if (!file_exists($localPath)) {
                Log::warning("⚠️ [VPS #{$vps->id}] Không tìm thấy file: {$filename}");
                continue;
            }

            // Upload with retry logic
            $uploadSuccess = false;
            for ($retry = 0; $retry < 3; $retry++) {
                if ($sshService->uploadFile($localPath, $remotePath)) {
                    $uploadSuccess = true;
                    break;
                }

                if ($retry < 2) {
                    Log::warning("🔄 [VPS #{$vps->id}] Upload failed for {$filename}, retrying... (" . ($retry + 1) . "/3)");
                    sleep(2);
                }
            }

            if (!$uploadSuccess) {
                throw new \Exception("Không thể upload file sau 3 lần thử: {$filename}");
            }

            // Set appropriate permissions
            if ($filename === 'agent.py') {
                $sshService->execute("chmod +x {$remotePath}");
            } elseif (str_ends_with($filename, '.sh')) {
                // Shell scripts
                $sshService->execute("chmod +x {$remotePath}");
            } elseif (str_ends_with($filename, '.conf')) {
                // Handle config files
                if ($filename === 'ezstream-agent-logrotate.conf') {
                    $sshService->execute("sudo cp {$remotePath} /etc/logrotate.d/ezstream-agent");
                    $sshService->execute("sudo chmod 644 /etc/logrotate.d/ezstream-agent");
                }
                $sshService->execute("chmod 644 {$remotePath}");
            } else {
                // Python files
                $sshService->execute("chmod 644 {$remotePath}");
            }

            $uploadedCount++;
        }

        Log::info("✅ [VPS #{$vps->id}] Đã upload {$uploadedCount} file EZStream Agent v5.0");
    }



    private function downloadAndInstallAgentFromRedis(SshService $sshService, VpsServer $vps): void
    {
        Log::info("📦 [VPS #{$vps->id}] Downloading agent from Redis");

        $remoteDir = '/opt/ezstream-agent';
        $tempFile = '/tmp/ezstream-agent-latest.zip';

        // Create Python script to download from Redis
        $pythonScript = $this->createRedisDownloadScript($vps);
        $scriptPath = '/tmp/download_agent.py';

        // Upload Python script
        $sshService->execute("cat > {$scriptPath} << 'EOF'\n{$pythonScript}\nEOF");
        $sshService->execute("chmod +x {$scriptPath}");

        // Run Python script to download from Redis
        $downloadResult = $sshService->execute("python3 {$scriptPath}");

        if (strpos($downloadResult, 'SUCCESS') === false) {
            throw new \Exception("Failed to download agent from Redis: {$downloadResult}");
        }

        Log::info("✅ [VPS #{$vps->id}] Downloaded agent package from Redis");

        // Backup current agent directory
        $backupDir = "/opt/ezstream-agent-backup-" . date('Y-m-d-H-i-s');
        $sshService->execute("sudo cp -r {$remoteDir} {$backupDir} 2>/dev/null || true");

        // Create/clear agent directory
        $sshService->execute("sudo mkdir -p {$remoteDir}");
        $sshService->execute("sudo rm -rf {$remoteDir}/*");

        // Extract agent package
        $extractCmd = "cd {$remoteDir} && sudo unzip -o {$tempFile}";
        $extractResult = $sshService->execute($extractCmd);

        if (strpos($extractResult, 'inflating') === false && strpos($extractResult, 'extracting') === false) {
            throw new \Exception("Failed to extract agent package: {$extractResult}");
        }

        // Set permissions
        $sshService->execute("sudo chmod +x {$remoteDir}/agent.py");
        $sshService->execute("sudo chmod +x {$remoteDir}/*.sh 2>/dev/null || true");
        $sshService->execute("sudo chmod 644 {$remoteDir}/*.py");
        $sshService->execute("sudo chmod 644 {$remoteDir}/*.conf 2>/dev/null || true");

        // Handle logrotate config
        $sshService->execute("sudo cp {$remoteDir}/ezstream-agent-logrotate.conf /etc/logrotate.d/ezstream-agent 2>/dev/null || true");
        $sshService->execute("sudo chmod 644 /etc/logrotate.d/ezstream-agent 2>/dev/null || true");

        // Cleanup temp files
        $sshService->execute("rm -f {$tempFile} {$scriptPath}");

        Log::info("✅ [VPS #{$vps->id}] Agent installed from Redis successfully");
    }

    // Removed sendUpdateAgentCommand - using pure SSH approach for reliability

    private function createRedisDownloadScript(VpsServer $vps): string
    {
        $redisHost = config('database.redis.default.host');
        $redisPort = config('database.redis.default.port');
        $redisPassword = config('database.redis.default.password');

        return <<<PYTHON
#!/usr/bin/env python3
import redis
import base64
import sys

try:
    # Connect to Redis
    r = redis.Redis(
        host='{$redisHost}',
        port={$redisPort},
        password='{$redisPassword}',
        decode_responses=False
    )

    # Test connection
    r.ping()
    print("Connected to Redis successfully")

    # Get agent package
    package_data = r.get('agent_package:latest')

    if not package_data:
        print("ERROR: Agent package not found in Redis")
        sys.exit(1)

    # Decode base64 data
    if isinstance(package_data, bytes):
        package_data = package_data.decode('utf-8')

    zip_data = base64.b64decode(package_data)

    # Write to file
    with open('/tmp/ezstream-agent-latest.zip', 'wb') as f:
        f.write(zip_data)

    print(f"SUCCESS: Downloaded agent package ({len(zip_data)} bytes)")

except Exception as e:
    print(f"ERROR: {str(e)}")
    sys.exit(1)
PYTHON;
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

        // Clear Python cache before restart
        $sshService->execute('cd /opt/ezstream-agent && rm -rf __pycache__/ *.pyc *.pyo');
        Log::info("🧹 [VPS #{$vps->id}] Cleared Python cache");

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

        Log::error("❌ [VPS #{$vps->id}] EZStream Agent không hoạt động sau {$maxRetries} lần thử", [
            'status' => trim($status),
            'systemd_status' => $systemdStatus,
            'service_log' => $serviceLog
        ]);

        throw new \Exception('EZStream Agent không khởi động được sau ' . ($maxRetries * $retryDelay) . ' giây. Kiểm tra log trên VPS.');
    }

    private function verifyAgentCompatibility(SshService $sshService, VpsServer $vps): void
    {
        Log::info("🔍 [VPS #{$vps->id}] Kiểm tra tương thích EZStream Agent v5.0");

        try {
            // Check if agent files exist (v5.0 architecture)
            $requiredFiles = [
                'agent.py',
                'command_handler.py',
                'config.py',
                'status_reporter.py',
                'stream_manager.py',        // ✅ New v5.0 file
                'process_manager.py',       // ✅ New v5.0 file
                'file_manager.py',
                'utils.py'
            ];

            $missingFiles = [];
            foreach ($requiredFiles as $file) {
                $checkFile = $sshService->execute("test -f /opt/ezstream-agent/{$file} && echo 'exists'");
                if (trim($checkFile) !== 'exists') {
                    $missingFiles[] = $file;
                }
            }

            if (!empty($missingFiles)) {
                throw new \Exception('Thiếu các file agent: ' . implode(', ', $missingFiles));
            }

            // Test agent version/compatibility by checking imports (v5.0 modules)
            $testImports = $sshService->execute("cd /opt/ezstream-agent && python3 -c 'import config, command_handler, status_reporter, stream_manager, process_manager; print(\"OK\")'");

            if (strpos($testImports, 'OK') === false) {
                throw new \Exception('Agent modules không import được: ' . $testImports);
            }

            // Wait for agent to report to Redis (up to 30 seconds)
            $maxWait = 30;
            $startTime = time();

            while ((time() - $startTime) < $maxWait) {
                $agentState = Redis::get("agent_state:{$vps->id}");
                if ($agentState) {
                    $stateData = json_decode($agentState, true);
                    if (isset($stateData['last_heartbeat'])) {
                        Log::info("✅ [VPS #{$vps->id}] EZStream Agent v5.0 đang báo cáo heartbeat bình thường");
                        return;
                    }
                }
                sleep(2);
            }

            Log::warning("⚠️ [VPS #{$vps->id}] Agent chưa báo cáo heartbeat sau {$maxWait}s, nhưng service đang chạy");

        } catch (\Exception $e) {
            Log::error("❌ [VPS #{$vps->id}] Agent compatibility check failed: {$e->getMessage()}");
            throw new \Exception('EZStream Agent v5.0 compatibility check failed: ' . $e->getMessage());
        }
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

        // Build command arguments (v5.0 uses named arguments)
        $commandArgs = "--vps-id {$vps->id} --redis-host {$redisHost} --redis-port {$redisPort}";
        if ($redisPassword) {
            $commandArgs .= " --redis-password '{$redisPassword}'";
        }

        $command = "{$pythonCmd} {$agentPath} {$commandArgs}";

        return "[Unit]
Description=EZStream Agent v5.0 (Stream Manager + Process Manager)
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
Environment=PYTHONDONTWRITEBYTECODE=1
KillMode=mixed
KillSignal=SIGTERM
TimeoutStopSec=30

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

    /**
     * Handle job failure - ensure VPS is never stuck in UPDATING state
     */
    public function failed(\Throwable $exception): void
    {
        try {
            $vps = VpsServer::find($this->vpsId);

            if ($vps && $vps->status === 'UPDATING') {
                Log::error("🚨 [VPS #{$vps->id}] UpdateAgentJob failed, resetting status from UPDATING", [
                    'error' => $exception->getMessage(),
                    'previous_status' => $vps->status
                ]);

                $vps->update([
                    'status' => 'ERROR',
                    'status_message' => 'Cập nhật Agent thất bại: ' . $exception->getMessage() . ' (Job failed at: ' . now()->format('H:i:s') . ')'
                ]);

                Log::info("✅ [VPS #{$vps->id}] Status reset to ERROR, VPS is no longer stuck");
            }

        } catch (\Exception $e) {
            Log::error("❌ Failed to reset VPS status in failed() method: {$e->getMessage()}");
        }
    }

    /**
     * Set update progress in Redis for real-time UI updates
     */
    private function setUpdateProgress(int $vpsId, string $stage, int $progressPercentage, string $message): void
    {
        try {
            $progressData = [
                'vps_id' => $vpsId,
                'stage' => $stage,
                'progress_percentage' => max(0, min(100, $progressPercentage)),
                'message' => $message,
                'updated_at' => now()->toISOString(),
                'completed_at' => $progressPercentage >= 100 ? now()->toISOString() : null
            ];

            // Store in Redis with TTL (30 minutes)
            $key = "vps_update_progress:{$vpsId}";
            Redis::setex($key, 1800, json_encode($progressData));

            // Also update VPS status_message for immediate feedback
            $vps = VpsServer::find($vpsId);
            if ($vps) {
                $vps->update(['status_message' => $message]);
            }

            Log::debug("VPS update progress set", [
                'vps_id' => $vpsId,
                'stage' => $stage,
                'progress' => $progressPercentage,
                'message' => $message
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to set update progress: {$e->getMessage()}");
        }
    }
}
