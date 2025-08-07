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
use Illuminate\Support\Str;

class ProvisionMultistreamVpsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3; // Tăng số lần retry
    public $timeout = 600; // 10 minutes for provision
    public $backoff = 30; // Delay 30 giây giữa các retry

    public int $vpsId;

    public function __construct(int $vpsId)
    {
        $this->vpsId = $vpsId;
        Log::info("✅ [VPS #{$this->vpsId}] Provisioning job created for EZStream Agent v7.0 (Simple FFmpeg Direct Streaming)");
    }

    public function handle(SshService $sshService): void
    {
        // Kiểm tra VPS có tồn tại không trước khi xử lý
        $vps = VpsServer::find($this->vpsId);
        if (!$vps) {
            Log::error("💥 [VPS #{$this->vpsId}] VPS not found in database");
            throw new \Exception("VPS #{$this->vpsId} not found in database");
        }

        Log::info("🚀 [VPS #{$vps->id}] Starting provision for EZStream Agent v7.0 (Attempt {$this->attempts()}/{$this->tries})");

        try {
            // Reset error message khi retry
            $vps->update([
                'status' => 'PROVISIONING',
                'status_message' => 'Setting up base system and EZStream Agent v7.0...',
                'error_message' => null
            ]);

            // Check if VPS operations are enabled for this environment
            if (!config('deployment.vps_operations_enabled')) {
                Log::info("🔧 [VPS #{$vps->id}] VPS operations disabled in " . config('app.env') . " environment - mocking provision");
                $this->mockProvisionSuccess($vps);
                return;
            }

            if (!$sshService->connect($vps)) {
                throw new \Exception('Failed to connect to VPS via SSH');
            }

            Log::info("✅ [VPS #{$vps->id}] SSH connection successful");

            // 0. FORCE CLEANUP - Remove any existing agent completely
            $this->setProvisionProgress($vps->id, 'cleanup', 10, 'Force cleanup existing installations');
            $this->forceCleanupExistingAgent($sshService, $vps);

            // 1. Download and run the main provision script (installs nginx, ffmpeg, etc.)
            $this->downloadAndRunProvisionScript($sshService, $vps);

            // 2. Setup EZStream Agent v7.0 (files already downloaded)
            $this->setupStreamAgent($sshService, $vps);

            // 3. SRS Server not needed for Agent v7.0 (Direct FFmpeg Streaming)
            Log::info("⏭️ [VPS #{$vps->id}] Skipping SRS setup - Agent v7.0 uses direct FFmpeg streaming");

            // 4. Verify services and dependencies
            $this->verifyBaseServices($sshService, $vps);
            $this->verifyPythonDependencies($sshService, $vps);

            // 5. Update VPS status
            $maxStreams = $this->calculateMaxStreams($sshService, $vps);

            // Agent v7.0 capabilities - no SRS needed
            $capabilities = ['ffmpeg-streaming', 'youtube-streaming', 'git-agent', 'process-manager', 'simple-streaming'];
            $statusMessage = 'Provisioned with EZStream Agent v7.0 (Simple FFmpeg Streaming)';

            Log::info("✅ [VPS #{$vps->id}] Using simple FFmpeg streaming (no SRS required)");

            $vps->update([
                'status' => 'ACTIVE',
                'last_provisioned_at' => now(),
                'status_message' => $statusMessage,
                'capabilities' => json_encode($capabilities),
                'max_concurrent_streams' => $maxStreams,
                'current_streams' => 0,
                'webhook_configured' => false, // No longer using webhooks
            ]);

            Log::info("🎉 [VPS #{$vps->id}] Redis Agent provision completed successfully", [
                'max_streams' => $maxStreams
            ]);

        } catch (\Exception $e) {
            Log::error("❌ [VPS #{$vps->id}] Redis Agent provision failed (Attempt {$this->attempts()}/{$this->tries})", [
                'error' => $e->getMessage(),
                'trace' => Str::limit($e->getTraceAsString(), 2000)
            ]);

            // Chỉ update status FAILED nếu đây là lần thử cuối cùng
            if ($this->attempts() >= $this->tries) {
                $vps->update([
                    'status' => 'FAILED',
                    'status_message' => 'Provision failed: ' . Str::limit($e->getMessage(), 250),
                    'error_message' => $e->getMessage(),
                ]);
            } else {
                // Nếu còn retry, giữ status PROVISIONING và update message
                $vps->update([
                    'status_message' => "Retrying provision (Attempt {$this->attempts()}/{$this->tries}): " . Str::limit($e->getMessage(), 200),
                    'error_message' => $e->getMessage(),
                ]);
                Log::info("🔄 [VPS #{$vps->id}] Will retry provision in {$this->backoff} seconds");
            }

            throw $e;
        } finally {
            $sshService->disconnect();
        }
    }

    private function downloadAndRunProvisionScript(SshService $sshService, VpsServer $vps): void
    {
        Log::info("📥 [VPS #{$vps->id}] Downloading ezstream-agent from GitHub...");

        // Create target directory first
        $sshService->execute('mkdir -p /opt/ezstream-agent');

        // Download and extract ezstream-agent directory from GitHub
        $downloadCmd = 'cd /tmp && curl -sSL https://github.com/truongvando/ezstream/archive/master.tar.gz -o ezstream-master.tar.gz';
        $extractCmd = 'cd /tmp && tar -xzf ezstream-master.tar.gz && cp -r ezstream-master/storage/app/ezstream-agent/* /opt/ezstream-agent/';

        $downloadResult = $sshService->execute($downloadCmd);
        Log::info("📦 [VPS #{$vps->id}] Download result", ['output' => $downloadResult]);

        $extractResult = $sshService->execute($extractCmd);
        Log::info("📦 [VPS #{$vps->id}] Extract result", ['output' => $extractResult]);

        // Verify download success
        $verifyCmd = 'ls -la /opt/ezstream-agent/';
        $verifyResult = $sshService->execute($verifyCmd);

        if (strpos($verifyResult, 'provision-vps.sh') === false) {
            throw new \Exception('Failed to download ezstream-agent from GitHub');
        }

        // Set proper permissions
        $sshService->execute('chmod +x /opt/ezstream-agent/provision-vps.sh');
        $sshService->execute('chmod -R 755 /opt/ezstream-agent');

        // Clean up temporary files
        $sshService->execute('rm -f /tmp/ezstream-master.tar.gz');
        $sshService->execute('rm -rf /tmp/ezstream-master');

        Log::info("🚀 [VPS #{$vps->id}] Running provision script from downloaded agent...");
        $result = $sshService->execute('/opt/ezstream-agent/provision-vps.sh'); // SSH service handles timeout internally

        // Log the full output for debugging
        Log::info("📋 [VPS #{$vps->id}] Provision script output", ['output' => $result]);

        // Check for success indicators (more flexible)
        $hasCompleteMessage = strpos($result, 'VPS BASE PROVISION COMPLETE') !== false;
        $hasSuccessIndicators = strpos($result, 'Docker installed for container support') !== false ||
                               strpos($result, 'Base system is ready for EZStream Agent') !== false;

        if (!$hasCompleteMessage && !$hasSuccessIndicators) {
            Log::error("❌ [VPS #{$vps->id}] Base provision script failed", [
                'output' => $result,
                'script_path' => $remoteScript
            ]);

            // Try to get more detailed error info
            $errorCheck = $sshService->execute('tail -20 /var/log/syslog | grep -i error || echo "No recent errors in syslog"');
            Log::error("❌ [VPS #{$vps->id}] System error logs", ['syslog' => $errorCheck]);

            throw new \Exception('Base provision script execution failed. Check logs for details.');
        }

        if (!$hasCompleteMessage) {
            Log::warning("⚠️ [VPS #{$vps->id}] Provision script completed but without final completion message");

            // Additional verification - check if key services are available
            $dockerCheck = $sshService->execute('command -v docker >/dev/null 2>&1 && echo "installed" || echo "not_installed"');
            Log::info("🔍 [VPS #{$vps->id}] Service verification", [
                'docker_status' => trim($dockerCheck),
                'agent_architecture' => 'Simple FFmpeg Direct Streaming'
            ]);

            if (trim($dockerCheck) !== 'installed') {
                throw new \Exception('Docker is not installed after provision script');
            }

            // Agent v7.0 uses direct FFmpeg streaming - no SRS verification needed
            Log::info("✅ [VPS #{$vps->id}] Agent v7.0 ready for direct FFmpeg streaming (no SRS required)");
        }

        Log::info("✅ [VPS #{$vps->id}] Base provision script completed successfully");
    }

    private function setupStreamAgent(SshService $sshService, VpsServer $vps): void
    {
        Log::info("🔧 [VPS #{$vps->id}] Setting up EZStream Agent v7.0 (files already downloaded)");

        // 1. Verify agent files exist from GitHub download
        $remoteDir = '/opt/ezstream-agent';
        $fileCheck = $sshService->execute("ls -la {$remoteDir}/ | grep -E '(agent\.py|stream_manager\.py)' || echo 'FILES_NOT_FOUND'");

        if (strpos($fileCheck, 'FILES_NOT_FOUND') !== false) {
            throw new \Exception("EZStream Agent files not found in {$remoteDir}. GitHub download may have failed.");
        }

        Log::info("✅ [VPS #{$vps->id}] Agent files verified in: {$remoteDir}");

        // 2. Set proper permissions
        $sshService->execute("sudo chown -R root:root {$remoteDir}");
        $sshService->execute("sudo chmod -R 755 {$remoteDir}");
        $sshService->execute("sudo chmod +x {$remoteDir}/*.py");

        // 2. Agent files already downloaded from GitHub - no Redis download needed
        Log::info("✅ [VPS #{$vps->id}] Using agent files from GitHub (Git-based deployment)");

        // 3. Upload logrotate config for log management
        $logrotateLocal = storage_path('app/ezstream-agent/ezstream-agent-logrotate.conf');
        if (file_exists($logrotateLocal)) {
            $sshService->uploadFile($logrotateLocal, '/etc/logrotate.d/ezstream-agent');
            Log::info("✅ [VPS #{$vps->id}] Logrotate config uploaded");
        }

        // 4. Get Redis connection details from Laravel's config
        $redisHost = config('database.redis.default.host', '127.0.0.1');
        $redisPort = config('database.redis.default.port', 6379);
        $redisPassword = config('database.redis.default.password', null);

        // 5. Create systemd service file on the VPS
        $serviceName = 'ezstream-agent.service';
        $remoteAgentPath = "{$remoteDir}/agent.py"; // Đường dẫn đến agent.py trên VPS
        $serviceContent = $this->generateAgentSystemdService($remoteAgentPath, $redisHost, $redisPort, $redisPassword, $vps);
        
        // Use a heredoc to safely write the multi-line content
        $sshService->execute("cat > /etc/systemd/system/{$serviceName} << 'EOF'\n{$serviceContent}\nEOF");

        // 5. Verify agent.py exists and is executable before starting service
        $agentCheck = $sshService->execute("ls -la {$remoteAgentPath} 2>/dev/null || echo 'AGENT_NOT_FOUND'");
        if (strpos($agentCheck, 'AGENT_NOT_FOUND') !== false) {
            throw new \Exception("Agent file not found at {$remoteAgentPath} before starting service");
        }
        Log::info("✅ [VPS #{$vps->id}] Agent file verified: {$agentCheck}");

        // Test agent.py syntax before starting service
        $syntaxTest = $sshService->execute("python3 -m py_compile {$remoteAgentPath} 2>&1 || echo 'SYNTAX_ERROR'");
        if (strpos($syntaxTest, 'SYNTAX_ERROR') !== false) {
            Log::error("❌ [VPS #{$vps->id}] Agent syntax error: {$syntaxTest}");
            throw new \Exception("Agent.py has syntax errors: {$syntaxTest}");
        }

        // 6. Enable and start the service
        Log::info("🔄 [VPS #{$vps->id}] Reloading systemd and enabling service...");
        $sshService->execute('systemctl daemon-reload');
        $sshService->execute("systemctl enable {$serviceName}");

        Log::info("🚀 [VPS #{$vps->id}] Starting EZStream Agent service...");
        $sshService->execute("systemctl restart {$serviceName}");

        // Wait a moment and check status multiple times
        Log::info("⏳ [VPS #{$vps->id}] Waiting for service to start...");
        sleep(3);

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $status = $sshService->execute("systemctl is-active {$serviceName}");
            $statusTrimmed = trim($status);

            Log::info("🔍 [VPS #{$vps->id}] Service status check #{$attempt}: {$statusTrimmed}");

            if ($statusTrimmed === 'active') {
                Log::info("✅ [VPS #{$vps->id}] EZStream Agent v7.0 service started successfully");
                return;
            }

            if ($attempt < 3) {
                Log::info("⏳ [VPS #{$vps->id}] Service not active yet, waiting 2 more seconds...");
                sleep(2);
            }
        }

        // Service failed to start - get detailed logs
        $serviceStatus = $sshService->execute("systemctl status {$serviceName} --no-pager -l");
        $serviceLog = $sshService->execute("journalctl -u {$serviceName} --no-pager -n 50");
        $agentDirListing = $sshService->execute("ls -la {$remoteDir}/");

        Log::error("❌ [VPS #{$vps->id}] EZStream Agent service failed to start", [
            'final_status' => trim($status),
            'service_status' => $serviceStatus,
            'service_log' => $serviceLog,
            'agent_directory' => $agentDirListing
        ]);

        throw new \Exception('EZStream Agent service failed to start. Check journalctl logs on the VPS.');
    }

    /**
     * Download and install agent from Redis (same as UpdateAgentJob)
     */
    private function downloadAndInstallAgentFromRedis(SshService $sshService, VpsServer $vps, string $remoteDir): void
    {
        Log::info("📦 [VPS #{$vps->id}] Downloading agent from Redis...");

        // Get Redis config
        $redisHost = config('database.redis.default.host');
        $redisPort = config('database.redis.default.port');
        $redisPassword = config('database.redis.default.password');

        // Create download script
        $downloadScript = $this->createRedisDownloadScript($redisHost, $redisPort, $redisPassword);
        $sshService->execute("cat > /tmp/download_agent.py << 'EOF'\n{$downloadScript}\nEOF");
        $sshService->execute("chmod +x /tmp/download_agent.py");

        // Download agent from Redis
        $downloadResult = $sshService->execute("python3 /tmp/download_agent.py");
        if (strpos($downloadResult, 'SUCCESS') === false) {
            throw new \Exception("Failed to download agent from Redis: {$downloadResult}");
        }

        // Extract agent
        $sshService->execute("cd /tmp && sudo tar -xzf ezstream-agent-latest.tar.gz -C {$remoteDir}");
        $sshService->execute("sudo chmod +x {$remoteDir}/agent.py");
        $sshService->execute("sudo chmod +x {$remoteDir}/*.py");

        Log::info("✅ [VPS #{$vps->id}] Agent downloaded and extracted from Redis");
    }

    /**
     * Create Python script to download agent from Redis (same as UpdateAgentJob)
     */
    private function createRedisDownloadScript(string $redisHost, int $redisPort, ?string $redisPassword): string
    {
        $passwordLine = $redisPassword ? "password='{$redisPassword}'," : '';

        return <<<PYTHON
#!/usr/bin/env python3
import redis
import base64
import sys

try:
    r = redis.Redis(
        host='{$redisHost}',
        port={$redisPort},
        {$passwordLine}
        decode_responses=False
    )

    r.ping()
    print("Connected to Redis successfully")

    package_data = r.get('agent_package:latest')
    if not package_data:
        print("ERROR: Agent package not found in Redis")
        sys.exit(1)

    if isinstance(package_data, bytes):
        package_data = package_data.decode('utf-8')

    zip_data = base64.b64decode(package_data)

    with open('/tmp/ezstream-agent-latest.tar.gz', 'wb') as f:
        f.write(zip_data)

    print(f"SUCCESS: Downloaded agent package ({len(zip_data)} bytes)")

except Exception as e:
    print(f"ERROR: {str(e)}")
    sys.exit(1)
PYTHON;
    }

    private function generateAgentSystemdService(string $agentPath, string $redisHost, int $redisPort, ?string $redisPassword, VpsServer $vps): string
    {
        $pythonCmd = "/usr/bin/python3";

        // Build the command arguments dynamically (v7.0 uses named arguments).
        $commandArgs = "--vps-id {$vps->id} --redis-host {$redisHost} --redis-port {$redisPort}";
        if ($redisPassword) {
            $commandArgs .= " --redis-password '{$redisPassword}'"; // Append password only if it exists
        }

        $command = "{$pythonCmd} {$agentPath} {$commandArgs}";

        return "[Unit]
Description=EZStream Agent v7.0 (Simple FFmpeg Direct Streaming)
After=network.target

[Service]
Type=simple
User=root
ExecStart={$command}
Restart=always
RestartSec=10
StandardOutput=journal
StandardError=journal
Environment=PYTHONPATH=/opt/ezstream-agent
WorkingDirectory=/opt/ezstream-agent

[Install]
WantedBy=multi-user.target";
    }

    private function setupLogrotate(SshService $sshService, VpsServer $vps): void
    {
        // This is now handled by provision-vps.sh to keep all base setup in one place.
        // This function is kept here to avoid breaking old calls, but it does nothing.
        Log::info("☑️ [VPS #{$vps->id}] Logrotate setup is now handled by provision-vps.sh, skipping.");
    }

    private function verifyBaseServices(SshService $sshService, VpsServer $vps): void
    {
        Log::info("🔍 [VPS #{$vps->id}] Verifying base services (FFmpeg ready) - Agent v7.0");

        // Check FFmpeg installation
        $ffmpegCheck = $sshService->execute("ffmpeg -version 2>/dev/null | head -1 || echo 'NOT_INSTALLED'");

        if (strpos($ffmpegCheck, 'NOT_INSTALLED') === false) {
            Log::info("✅ [VPS #{$vps->id}] FFmpeg is installed: " . trim($ffmpegCheck));
        } else {
            Log::warning("⚠️ [VPS #{$vps->id}] FFmpeg not found - installing...");
            $sshService->execute("sudo apt-get update && sudo apt-get install -y ffmpeg");

            // Verify installation
            $ffmpegRecheck = $sshService->execute("ffmpeg -version 2>/dev/null | head -1 || echo 'INSTALL_FAILED'");
            if (strpos($ffmpegRecheck, 'INSTALL_FAILED') === false) {
                Log::info("✅ [VPS #{$vps->id}] FFmpeg installed successfully");
            } else {
                Log::error("❌ [VPS #{$vps->id}] FFmpeg installation failed");
            }
        }

        // Check Python dependencies
        $pythonCheck = $sshService->execute('python3 -c "import redis, requests, psutil; print(\'OK\')" 2>/dev/null || echo "MISSING"');
        if (trim($pythonCheck) !== 'OK') {
            throw new \Exception('Python dependencies missing (redis, requests, psutil)');
        }

        // Agent v7.0 uses direct FFmpeg streaming - no HTTP endpoints needed
        Log::info("⏭️ [VPS #{$vps->id}] Skipping HTTP health check - Agent v7.0 uses direct streaming");

        Log::info("✅ [VPS #{$vps->id}] Base services verified (Agent v7.0 - Simple FFmpeg Direct mode)");
    }

    private function verifyPythonDependencies(SshService $sshService, VpsServer $vps): void
    {
        Log::info("🐍 [VPS #{$vps->id}] Verifying Python dependencies for agent");

        // Test Python 3 availability
        $pythonVersion = $sshService->execute('python3 --version');
        if (empty(trim($pythonVersion))) {
            throw new \Exception('Python 3 is not installed or not accessible');
        }
        Log::info("✅ [VPS #{$vps->id}] Python version: " . trim($pythonVersion));

        // Test required Python packages
        $requiredPackages = ['redis', 'psutil', 'requests'];

        foreach ($requiredPackages as $package) {
            $testResult = $sshService->execute("python3 -c 'import {$package}; print(\"{$package} OK\")'");
            if (strpos($testResult, "{$package} OK") === false) {
                Log::warning("❌ [VPS #{$vps->id}] Python package {$package} not found, attempting to install...");

                // Try to install the missing package
                $installResult = $sshService->execute("pip3 install {$package} --break-system-packages");

                // Test again
                $retestResult = $sshService->execute("python3 -c 'import {$package}; print(\"{$package} OK\")'");
                if (strpos($retestResult, "{$package} OK") === false) {
                    throw new \Exception("Failed to install Python package: {$package}");
                }

                Log::info("✅ [VPS #{$vps->id}] Python package {$package} installed successfully");
            } else {
                Log::info("✅ [VPS #{$vps->id}] Python package {$package} is available");
            }
        }

        // Test agent.py syntax
        $agentPath = '/opt/ezstream-agent/agent.py';
        $syntaxCheck = $sshService->execute("python3 -m py_compile {$agentPath}");
        if (!empty(trim($syntaxCheck))) {
            Log::warning("⚠️ [VPS #{$vps->id}] Agent syntax check output: " . trim($syntaxCheck));
        }

        // Test agent imports
        $importTest = $sshService->execute("cd /opt/ezstream-agent && python3 -c 'import sys; sys.path.insert(0, \".\"); import agent; print(\"Agent imports OK\")'");
        if (strpos($importTest, 'Agent imports OK') === false) {
            Log::warning("⚠️ [VPS #{$vps->id}] Agent import test failed: " . trim($importTest));
            // Don't throw exception here as it might be due to missing runtime arguments
        }

        Log::info("✅ [VPS #{$vps->id}] Python dependencies verified");
    }

    private function calculateMaxStreams(SshService $sshService, VpsServer $vps): int
    {
        try {
            // Get CPU cores
            $cpuCores = (int) trim($sshService->execute('nproc'));
            
            // Get RAM in GB
            $ramGB = (int) trim($sshService->execute("free -g | grep Mem | awk '{print \$2}'"));
            
            // Realistic calculation based on actual usage
            // Each stream needs ~2% CPU and ~100MB RAM (more accurate)
            $maxByCpu = max(1, intval($cpuCores * 0.8 / 0.02));  // 2% CPU per stream
            $maxByRam = max(1, intval($ramGB * 0.8 / 0.1));      // 100MB RAM per stream
            
            $maxStreams = min($maxByCpu, $maxByRam, 50); // Increased hard limit to 50 streams
            
            Log::info("📊 [VPS #{$vps->id}] Calculated capacity", [
                'cpu_cores' => $cpuCores,
                'ram_gb' => $ramGB,
                'max_by_cpu' => $maxByCpu,
                'max_by_ram' => $maxByRam,
                'final_max' => $maxStreams
            ]);
            
            return $maxStreams;
            
        } catch (\Exception $e) {
            Log::warning("⚠️ [VPS #{$vps->id}] Could not calculate max streams: {$e->getMessage()}");
            return 2; // Safe default
        }
    }

    public function failed(\Throwable $exception): void
    {
        $vps = VpsServer::find($this->vpsId);
        if (!$vps) {
            Log::error("💥 Provision job failed but could not find VPS #{$this->vpsId}");
            return;
        }

        Log::error("💥 [VPS #{$vps->id}] Redis Agent provision job failed in failed() method", [
            'error' => $exception->getMessage(),
        ]);

        $vps->update([
            'status' => 'FAILED',
            'status_message' => 'Provision failed: ' . Str::limit($exception->getMessage(), 250),
            'error_message' => $exception->getMessage(),
        ]);
    }

    /**
     * Setup streaming dependencies (FFmpeg)
     */
    private function setupStreamingDependencies(SshService $sshService, VpsServer $vps): void
    {
        try {
            Log::info("🎬 [VPS #{$vps->id}] Setting up streaming dependencies...");

            $vps->update(['status_message' => 'Installing FFmpeg and dependencies...']);

            // Install FFmpeg and required packages
            $result = $sshService->execute("sudo apt-get update && sudo apt-get install -y ffmpeg python3-psutil", 300);

            // Verify FFmpeg installation
            $ffmpegVersion = $sshService->execute("ffmpeg -version | head -1 2>/dev/null || echo 'FAILED'");
            if (strpos($ffmpegVersion, 'FAILED') === false) {
                Log::info("✅ [VPS #{$vps->id}] FFmpeg installed: " . trim($ffmpegVersion));
            } else {
                throw new \Exception("FFmpeg installation failed");
            }

            // Verify psutil installation
            $psutilCheck = $sshService->execute("python3 -c 'import psutil; print(psutil.__version__)' 2>/dev/null || echo 'FAILED'");
            if (strpos($psutilCheck, 'FAILED') === false) {
                Log::info("✅ [VPS #{$vps->id}] Python psutil installed: " . trim($psutilCheck));
            } else {
                Log::warning("⚠️ [VPS #{$vps->id}] Python psutil installation may have failed");
            }

            Log::info("✅ [VPS #{$vps->id}] Streaming dependencies setup completed");

        } catch (\Exception $e) {
            Log::error("❌ [VPS #{$vps->id}] Failed to setup streaming dependencies: " . $e->getMessage());
            throw $e; // FFmpeg is required, so throw exception
        }
    }

    /**
     * Verify SRS installation
     */
    private function verifySrsInstallation(SshService $sshService, VpsServer $vps): void
    {
        try {
            Log::info("🔍 [VPS #{$vps->id}] Verifying SRS installation...");

            // Check if SRS container is running
            $result = $sshService->execute("docker ps --filter 'name=ezstream-srs' --format '{{.Status}}'");

            if (strpos($result, 'Up') !== false) {
                Log::info("✅ [VPS #{$vps->id}] SRS container is running");

                // Test SRS API (same as provision script)
                $apiTest = $sshService->execute("curl -s http://localhost:1985/api/v1/versions > /dev/null && echo 'API_OK' || echo 'API_FAILED'");

                if (trim($apiTest) === 'API_OK') {
                    Log::info("✅ [VPS #{$vps->id}] SRS API is responding correctly");
                } else {
                    Log::warning("⚠️ [VPS #{$vps->id}] SRS API test failed - may need more time to start");
                }
            } else {
                Log::warning("⚠️ [VPS #{$vps->id}] SRS container is not running");
            }

        } catch (\Exception $e) {
            Log::error("❌ [VPS #{$vps->id}] Failed to verify SRS installation: " . $e->getMessage());
        }
    }

    /**
     * Check if SRS is installed and running
     */
    private function isSrsInstalled(SshService $sshService): bool
    {
        try {
            $result = $sshService->execute("docker ps --filter 'name=ezstream-srs' --format '{{.Status}}'");
            return strpos($result, 'Up') !== false;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function mockProvisionSuccess(VpsServer $vps): void
    {
        Log::info("🎭 [VPS #{$vps->id}] Mocking provision success for development environment");

        // Simulate provision delay
        sleep(2);

        $vps->update([
            'status' => 'ACTIVE',
            'last_provisioned_at' => now(),
            'status_message' => 'Mocked provision completed (EZStream Agent v5.0 - Direct FFmpeg)',
            'capabilities' => json_encode(['direct-ffmpeg', 'youtube-streaming', 'redis-agent', 'process-manager']),
            'max_concurrent_streams' => 5, // Mock value
            'current_streams' => 0,
            'webhook_configured' => false,
        ]);

        Log::info("✅ [VPS #{$vps->id}] Mock provision completed successfully");
    }

    /**
     * FORCE CLEANUP - Remove any existing agent completely
     */
    private function forceCleanupExistingAgent(SshService $sshService, VpsServer $vps): void
    {
        Log::info("🧹 [VPS #{$vps->id}] FORCE CLEANUP - Removing any existing agent installations");

        try {
            // 1. Stop và disable tất cả services
            $sshService->execute("systemctl stop ezstream-agent 2>/dev/null || true");
            $sshService->execute("systemctl disable ezstream-agent 2>/dev/null || true");
            $sshService->execute("systemctl stop srs 2>/dev/null || true");
            $sshService->execute("systemctl disable srs 2>/dev/null || true");

            // 2. Kill tất cả processes liên quan
            $sshService->execute("pkill -f 'ezstream-agent' || true");
            $sshService->execute("pkill -f 'agent.py' || true");
            $sshService->execute("pkill -f 'python.*agent' || true");
            $sshService->execute("pkill -f 'srs' || true");

            // 3. Xóa systemd service files
            $sshService->execute("rm -f /etc/systemd/system/ezstream-agent.service");
            $sshService->execute("rm -f /etc/systemd/system/srs.service");
            $sshService->execute("systemctl daemon-reload");

            // 4. Xóa tất cả directories
            $sshService->execute("rm -rf /opt/ezstream-agent*");
            $sshService->execute("rm -rf /opt/srs*");
            $sshService->execute("rm -rf /usr/local/srs*");
            $sshService->execute("rm -rf /tmp/ezstream*");
            $sshService->execute("rm -rf /tmp/srs*");

            // 5. Xóa logrotate configs
            $sshService->execute("rm -f /etc/logrotate.d/ezstream-agent");
            $sshService->execute("rm -f /etc/logrotate.d/srs");

            // 6. Xóa Docker containers/images SRS (nếu có)
            $sshService->execute("docker stop ezstream-srs 2>/dev/null || true");
            $sshService->execute("docker rm ezstream-srs 2>/dev/null || true");
            $sshService->execute("docker rmi ossrs/srs:5 2>/dev/null || true");

            // 7. Xóa cron jobs (nếu có)
            $sshService->execute("crontab -l | grep -v ezstream | crontab - 2>/dev/null || true");

            // 8. Clean up logs
            $sshService->execute("rm -rf /var/log/ezstream*");
            $sshService->execute("rm -rf /var/log/srs*");

            // 9. Clean up any remaining processes
            $sshService->execute("ps aux | grep -E '(ezstream|srs)' | grep -v grep | awk '{print \$2}' | xargs kill -9 2>/dev/null || true");

            // 10. Clean up Python cache
            $sshService->execute("find /opt -name '__pycache__' -type d -exec rm -rf {} + 2>/dev/null || true");
            $sshService->execute("find /opt -name '*.pyc' -delete 2>/dev/null || true");

            Log::info("✅ [VPS #{$vps->id}] FORCE CLEANUP completed - VPS is now clean");

            // Verify cleanup
            $remainingProcesses = $sshService->execute("ps aux | grep -E '(ezstream|srs|agent)' | grep -v grep || echo 'No remaining processes'");
            $remainingDirs = $sshService->execute("ls -la /opt/ | grep -E '(ezstream|srs)' || echo 'No remaining directories'");

            Log::info("🔍 [VPS #{$vps->id}] Cleanup verification", [
                'remaining_processes' => trim($remainingProcesses),
                'remaining_directories' => trim($remainingDirs)
            ]);

        } catch (\Exception $e) {
            Log::warning("⚠️ [VPS #{$vps->id}] Some cleanup operations failed (continuing): {$e->getMessage()}");
            // Continue anyway - this is cleanup, not critical
        }
    }

    /**
     * Set provision progress in Redis for real-time UI updates
     */
    private function setProvisionProgress(int $vpsId, string $stage, int $progressPercentage, string $message): void
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
            $key = "vps_provision_progress:{$vpsId}";
            \Illuminate\Support\Facades\Redis::setex($key, 1800, json_encode($progressData));

            Log::debug("VPS provision progress set", [
                'vps_id' => $vpsId,
                'stage' => $stage,
                'progress' => $progressPercentage,
                'message' => $message
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to set provision progress: {$e->getMessage()}");
        }
    }
}
