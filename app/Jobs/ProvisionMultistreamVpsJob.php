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
        Log::info("✅ [VPS #{$this->vpsId}] Provisioning job created for EZStream Agent v6.0 (SRS-Only Streaming)");
    }

    public function handle(SshService $sshService): void
    {
        // Kiểm tra VPS có tồn tại không trước khi xử lý
        $vps = VpsServer::find($this->vpsId);
        if (!$vps) {
            Log::error("💥 [VPS #{$this->vpsId}] VPS not found in database");
            throw new \Exception("VPS #{$this->vpsId} not found in database");
        }

        Log::info("🚀 [VPS #{$vps->id}] Starting provision for EZStream Agent v6.0 (Attempt {$this->attempts()}/{$this->tries})");

        try {
            // Reset error message khi retry
            $vps->update([
                'status' => 'PROVISIONING',
                'status_message' => 'Setting up base system and EZStream Agent v6.0...',
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

            // 1. Upload and run the main provision script (installs nginx, ffmpeg, etc.)
            $this->uploadAndRunProvisionScript($sshService, $vps);

            // 2. Upload and set up EZStream Agent v6.0
            $this->uploadAndSetupStreamAgent($sshService, $vps);

            // 3. Setup SRS Server (if enabled)
            $this->setupSrsServer($sshService, $vps);

            // 4. Verify services and dependencies
            $this->verifyBaseServices($sshService, $vps);
            $this->verifyPythonDependencies($sshService, $vps);

            // 5. Update VPS status
            $maxStreams = $this->calculateMaxStreams($sshService, $vps);

            // Check if SRS is installed
            $srsInstalled = $this->isSrsInstalled($sshService);
            $capabilities = ['srs-streaming', 'youtube-streaming', 'redis-agent', 'process-manager'];
            $statusMessage = 'Provisioned with EZStream Agent v6.0 (SRS-Only Streaming)';

            if ($srsInstalled) {
                $statusMessage = 'Provisioned with EZStream Agent v6.0 + SRS Server';
            } else {
                Log::warning("⚠️ [VPS #{$vps->id}] SRS Server not installed - agent may not function properly");
            }

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

    private function uploadAndRunProvisionScript(SshService $sshService, VpsServer $vps): void
    {
        Log::info("📦 [VPS #{$vps->id}] Uploading and running base provision script (provision-vps.sh)");

        $localScript = storage_path('app/ezstream-agent/provision-vps.sh');
        $remoteScript = '/tmp/provision-vps.sh';

        if (!file_exists($localScript)) {
            throw new \Exception('Base provision script (provision-vps.sh) not found');
        }

        if (!$sshService->uploadFile($localScript, $remoteScript)) {
            throw new \Exception('Failed to upload provision script');
        }

        $sshService->execute("chmod +x {$remoteScript}");

        Log::info("🚀 [VPS #{$vps->id}] Running base provision script...");
        $result = $sshService->execute($remoteScript); // SSH service handles timeout internally

        // Log the full output for debugging
        Log::info("📋 [VPS #{$vps->id}] Provision script output", ['output' => $result]);

        // Check for success indicators (more flexible)
        $hasCompleteMessage = strpos($result, 'VPS BASE PROVISION COMPLETE') !== false;
        $hasSuccessIndicators = strpos($result, 'Docker installed for SRS streaming server support') !== false ||
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
            $nginxCheck = $sshService->execute('systemctl is-active nginx 2>/dev/null || echo "inactive"');
            $dockerCheck = $sshService->execute('command -v docker >/dev/null 2>&1 && echo "installed" || echo "not_installed"');

            Log::info("🔍 [VPS #{$vps->id}] Service verification", [
                'nginx_status' => trim($nginxCheck),
                'docker_status' => trim($dockerCheck)
            ]);

            if (trim($nginxCheck) !== 'active') {
                throw new \Exception('Nginx service is not active after provision script');
            }
        }

        Log::info("✅ [VPS #{$vps->id}] Base provision script completed successfully");
    }

    private function uploadAndSetupStreamAgent(SshService $sshService, VpsServer $vps): void
    {
        Log::info("📦 [VPS #{$vps->id}] Uploading and setting up EZStream Agent v6.0");

        // 1. Create remote directory with proper permissions
        $remoteDir = '/opt/ezstream-agent';
        $sshService->execute("sudo mkdir -p {$remoteDir}");
        $sshService->execute("sudo chown root:root {$remoteDir}");
        $sshService->execute("sudo chmod 755 {$remoteDir}");

        // Verify directory creation
        $dirCheck = $sshService->execute("ls -la /opt/ | grep ezstream-agent || echo 'DIRECTORY_NOT_FOUND'");
        if (strpos($dirCheck, 'DIRECTORY_NOT_FOUND') !== false) {
            throw new \Exception("Failed to create agent directory: {$remoteDir}");
        }
        Log::info("✅ [VPS #{$vps->id}] Agent directory created: {$remoteDir}");

        // 2. Upload all agent files (EZStream Agent v6.0 - SRS-Only Streaming)
        $agentFiles = [
            'agent.py',                    // Main entry point
            'config.py',                   // Configuration management
            'stream_manager.py',           // SRS-based Stream Manager (main)
            'process_manager.py',          // Process Management with auto reconnect
            'file_manager.py',             // File download/validation/cleanup
            'status_reporter.py',          // Status reporting to Laravel
            'command_handler.py',          // Command processing from Laravel
            'video_optimizer.py',          // Video optimization (optional)
            'utils.py',                    // Shared utilities
            // SRS Support files
            'srs_manager.py',              // SRS Server API Manager
            'setup-srs.sh',                // SRS setup script
            'srs.conf'                     // SRS configuration
        ];

        $uploadedFiles = 0;
        foreach ($agentFiles as $filename) {
            $localPath = storage_path("app/ezstream-agent/{$filename}");
            $remotePath = "{$remoteDir}/{$filename}";

            if (!file_exists($localPath)) {
                Log::warning("⚠️ [VPS #{$vps->id}] Agent file not found: {$filename}");
                continue;
            }

            Log::info("📤 [VPS #{$vps->id}] Uploading {$filename}...");

            if (!$sshService->uploadFile($localPath, $remotePath)) {
                throw new \Exception("Failed to upload agent file: {$filename}");
            }

            // Verify file was uploaded successfully
            $fileCheck = $sshService->execute("ls -la {$remotePath} 2>/dev/null || echo 'FILE_NOT_FOUND'");
            if (strpos($fileCheck, 'FILE_NOT_FOUND') !== false) {
                throw new \Exception("File upload verification failed for: {$filename}");
            }

            // Set proper permissions
            $sshService->execute("sudo chown root:root {$remotePath}");
            $sshService->execute("sudo chmod 644 {$remotePath}");

            if ($filename === 'agent.py' || $filename === 'setup-srs.sh') {
                $sshService->execute("sudo chmod +x {$remotePath}");
                Log::info("✅ [VPS #{$vps->id}] Made {$filename} executable");
            }

            $uploadedFiles++;
            Log::info("✅ [VPS #{$vps->id}] Successfully uploaded {$filename}");
        }

        if ($uploadedFiles === 0) {
            throw new \Exception("No agent files were uploaded successfully");
        }

        Log::info("✅ [VPS #{$vps->id}] Uploaded {$uploadedFiles} agent files successfully");

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
                Log::info("✅ [VPS #{$vps->id}] EZStream Agent v6.0 service started successfully");
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

        // Kiểm tra các lỗi phổ biến
        $errorAnalysis = $this->analyzeServiceFailure($serviceLog, $serviceStatus);

        Log::error("❌ [VPS #{$vps->id}] EZStream Agent service failed to start", [
            'final_status' => trim($status),
            'service_status' => $serviceStatus,
            'service_log' => $serviceLog,
            'agent_directory' => $agentDirListing,
            'error_analysis' => $errorAnalysis,
            'attempt' => $this->attempts()
        ]);

        throw new \Exception("EZStream Agent service failed to start. {$errorAnalysis['suggestion']} (Attempt {$this->attempts()}/{$this->tries})");
    }
    
    private function generateAgentSystemdService(string $agentPath, string $redisHost, int $redisPort, ?string $redisPassword, VpsServer $vps): string
    {
        $pythonCmd = "/usr/bin/python3";

        // Build the command arguments dynamically (v6.0 uses named arguments).
        $commandArgs = "--vps-id {$vps->id} --redis-host {$redisHost} --redis-port {$redisPort}";
        if ($redisPassword) {
            $commandArgs .= " --redis-password '{$redisPassword}'"; // Append password only if it exists
        }

        $command = "{$pythonCmd} {$agentPath} {$commandArgs}";

        return "[Unit]
Description=EZStream Agent v6.0 (SRS-Only Streaming)
After=network.target
Wants=nginx.service

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
        Log::info("🔍 [VPS #{$vps->id}] Verifying base services (Nginx health endpoint) - Agent v6.0");

        // Check nginx
        $nginxStatus = $sshService->execute('systemctl is-active nginx');
        if (trim($nginxStatus) !== 'active') {
            throw new \Exception('Nginx service is not running');
        }

        // Check HTTP health endpoint port 8080 (Agent v6.0 SRS-only)
        $httpPort = $sshService->execute('ss -tulpn | grep :8080');
        if (empty(trim($httpPort))) {
            throw new \Exception('HTTP health endpoint port 8080 is not listening');
        }

        // Test health endpoint
        $healthCheck = $sshService->execute('curl -s http://localhost:8080/health || echo "HEALTH_CHECK_FAILED"');
        if (strpos($healthCheck, 'HEALTH_CHECK_FAILED') !== false) {
            Log::warning("⚠️ [VPS #{$vps->id}] Health endpoint not responding, but HTTP port is active - continuing");
        } else {
            Log::info("✅ [VPS #{$vps->id}] Health endpoint responding: " . trim($healthCheck));
        }

        Log::info("✅ [VPS #{$vps->id}] Base services verified (Agent v6.0 - SRS-Only mode)");
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
            Log::error("💥 Provision job failed but could not find VPS #{$this->vpsId}", [
                'exception' => $exception->getMessage(),
                'attempts' => $this->attempts() ?? 'unknown'
            ]);
            return;
        }

        Log::error("💥 [VPS #{$vps->id}] Redis Agent provision job failed in failed() method after {$this->attempts()} attempts", [
            'error' => $exception->getMessage(),
            'final_attempt' => true
        ]);

        // Chỉ update status khi thực sự failed (sau tất cả attempts)
        $vps->update([
            'status' => 'FAILED',
            'status_message' => 'Provision failed after ' . $this->attempts() . ' attempts: ' . Str::limit($exception->getMessage(), 200),
            'error_message' => $exception->getMessage(),
        ]);

        // Clear provision progress từ Redis
        try {
            $key = "vps_provision_progress:{$this->vpsId}";
            \Illuminate\Support\Facades\Redis::del($key);
        } catch (\Exception $e) {
            Log::warning("Failed to clear provision progress from Redis", ['error' => $e->getMessage()]);
        }
    }

    /**
     * Phân tích lỗi service để đưa ra gợi ý khắc phục
     */
    private function analyzeServiceFailure(string $serviceLog, string $serviceStatus): array
    {
        $analysis = [
            'error_type' => 'unknown',
            'suggestion' => 'Check journalctl logs on the VPS for more details'
        ];

        // Kiểm tra các lỗi phổ biến
        if (strpos($serviceLog, 'Permission denied') !== false) {
            $analysis['error_type'] = 'permission';
            $analysis['suggestion'] = 'Permission denied - check file permissions and ownership';
        } elseif (strpos($serviceLog, 'No such file or directory') !== false) {
            $analysis['error_type'] = 'missing_file';
            $analysis['suggestion'] = 'Missing files - agent files may not have been uploaded correctly';
        } elseif (strpos($serviceLog, 'python3: command not found') !== false || strpos($serviceLog, 'python: command not found') !== false) {
            $analysis['error_type'] = 'missing_python';
            $analysis['suggestion'] = 'Python3 not installed or not in PATH';
        } elseif (strpos($serviceLog, 'ModuleNotFoundError') !== false || strpos($serviceLog, 'ImportError') !== false) {
            $analysis['error_type'] = 'missing_dependencies';
            $analysis['suggestion'] = 'Missing Python dependencies - run pip install requirements';
        } elseif (strpos($serviceLog, 'Connection refused') !== false || strpos($serviceLog, 'redis') !== false) {
            $analysis['error_type'] = 'redis_connection';
            $analysis['suggestion'] = 'Cannot connect to Redis server - check Redis configuration';
        } elseif (strpos($serviceLog, 'Address already in use') !== false) {
            $analysis['error_type'] = 'port_conflict';
            $analysis['suggestion'] = 'Port already in use - another service may be running';
        } elseif (strpos($serviceStatus, 'failed') !== false && strpos($serviceStatus, 'code=exited') !== false) {
            $analysis['error_type'] = 'exit_code';
            $analysis['suggestion'] = 'Service exited with error code - check agent logs for specific error';
        }

        return $analysis;
    }

    /**
     * Setup SRS Server for streaming
     */
    private function setupSrsServer(SshService $sshService, VpsServer $vps): void
    {
        try {
            Log::info("🎬 [VPS #{$vps->id}] Setting up SRS Server...");

            // Check if SRS should be installed (based on settings)
            $streamingMethod = \App\Models\Setting::where('key', 'streaming_method')->value('value') ?? 'ffmpeg_copy';

            if ($streamingMethod !== 'srs') {
                Log::info("🔧 [VPS #{$vps->id}] SRS not enabled in settings, skipping installation");
                return;
            }

            $vps->update(['status_message' => 'Installing SRS Server...']);

            // Run SRS setup script
            $agentDir = '/opt/ezstream-agent';
            $setupScript = "{$agentDir}/setup-srs.sh";

            // Make setup script executable
            $sshService->execute("chmod +x {$setupScript}");

            // Run SRS setup
            $result = $sshService->execute("{$setupScript} setup", 300); // 5 minute timeout

            if (strpos($result, 'SRS Server setup completed successfully') !== false) {
                Log::info("✅ [VPS #{$vps->id}] SRS Server installed successfully");

                // Verify SRS is running
                $this->verifySrsInstallation($sshService, $vps);
            } else {
                Log::warning("⚠️ [VPS #{$vps->id}] SRS setup may have failed, output: {$result}");
            }

        } catch (\Exception $e) {
            Log::error("❌ [VPS #{$vps->id}] Failed to setup SRS Server: " . $e->getMessage());
            // Don't throw - SRS is optional, continue with FFmpeg fallback
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

                // Test SRS API
                $apiTest = $sshService->execute("curl -s http://localhost:1985/api/v1/summaries | grep '\"code\":0' || echo 'API_FAILED'");

                if (strpos($apiTest, 'API_FAILED') === false) {
                    Log::info("✅ [VPS #{$vps->id}] SRS API is responding correctly");
                } else {
                    Log::warning("⚠️ [VPS #{$vps->id}] SRS API test failed");
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
