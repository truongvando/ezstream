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

    // Job configuration
    public $timeout = 600; // 10 minutes timeout
    public $tries = 1;     // Don't retry automatically
    public $maxExceptions = 1;

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

            // Step 3: Send UPDATE_AGENT command to agent
            $this->sendUpdateAgentCommand($vps);

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
        Log::info("📤 [VPS #{$vps->id}] Đang upload các file Redis Agent v3.0");

        $remoteDir = '/opt/ezstream-agent';
        $sshService->execute("mkdir -p {$remoteDir}");

        // Get all Python files from agent directory
        $agentDir = storage_path('app/ezstream-agent');
        $pythonFiles = glob($agentDir . '/*.py');
        $configFiles = glob($agentDir . '/*.conf');

        $agentFiles = [];

        // Add Python files
        foreach ($pythonFiles as $file) {
            $agentFiles[] = basename($file);
        }

        // Add config files
        foreach ($configFiles as $file) {
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

            if (!$sshService->uploadFile($localPath, $remotePath)) {
                throw new \Exception("Không thể upload file: {$filename}");
            }

            // Set appropriate permissions
            if ($filename === 'agent.py') {
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

        Log::info("✅ [VPS #{$vps->id}] Đã upload {$uploadedCount} file Redis Agent");
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
        $sshService->execute("sudo chmod 644 {$remoteDir}/*.py");
        $sshService->execute("sudo chmod 644 {$remoteDir}/*.conf 2>/dev/null || true");

        // Handle logrotate config
        $sshService->execute("sudo cp {$remoteDir}/ezstream-agent-logrotate.conf /etc/logrotate.d/ezstream-agent 2>/dev/null || true");
        $sshService->execute("sudo chmod 644 /etc/logrotate.d/ezstream-agent 2>/dev/null || true");

        // Cleanup temp files
        $sshService->execute("rm -f {$tempFile} {$scriptPath}");

        Log::info("✅ [VPS #{$vps->id}] Agent installed from Redis successfully");
    }

    private function sendUpdateAgentCommand(VpsServer $vps): void
    {
        Log::info("📤 [VPS #{$vps->id}] Sending UPDATE_AGENT command to agent");

        try {
            // Send UPDATE_AGENT command via Redis
            $commandData = [
                'command' => 'UPDATE_AGENT',
                'vps_id' => $vps->id,
                'version' => 'latest',
                'timestamp' => now()->timestamp,
                'command_id' => uniqid('update_agent_', true)
            ];

            $redisKey = "agent_commands:{$vps->id}";

            // Push command to Redis list
            Redis::lpush($redisKey, json_encode($commandData));

            Log::info("✅ [VPS #{$vps->id}] UPDATE_AGENT command sent via Redis");

            // Wait for agent to process (with timeout)
            $maxWaitTime = 300; // 5 minutes
            $startTime = time();

            while ((time() - $startTime) < $maxWaitTime) {
                // Check if agent is still responding
                $lastHeartbeat = $vps->last_heartbeat;
                if ($lastHeartbeat && $lastHeartbeat->diffInMinutes(now()) > 2) {
                    Log::warning("⚠️ [VPS #{$vps->id}] Agent not responding during update");
                    break;
                }

                // Check if restart flag was created (indicates update in progress)
                sleep(5);

                // Agent will restart itself, so we consider it successful
                // if command was sent without Redis errors
                break;
            }

            Log::info("✅ [VPS #{$vps->id}] Agent update command completed");

        } catch (\Exception $e) {
            Log::error("❌ [VPS #{$vps->id}] Failed to send UPDATE_AGENT command: {$e->getMessage()}");
            throw $e;
        }
    }

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
}
