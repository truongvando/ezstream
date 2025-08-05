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

    public $tries = 1;
    public $timeout = 600; // 10 minutes for provision

    public int $vpsId;

    public function __construct(int $vpsId)
    {
        $this->vpsId = $vpsId;
        Log::info("✅ [VPS #{$this->vpsId}] Provisioning job created for EZStream Agent v6.0 (SRS-Only Streaming)");
    }

    public function handle(SshService $sshService): void
    {
        $vps = VpsServer::findOrFail($this->vpsId);

        Log::info("🚀 [VPS #{$vps->id}] Starting provision for EZStream Agent v6.0");

        try {
            $vps->update([
                'status' => 'PROVISIONING',
                'status_message' => 'Setting up base system and EZStream Agent v6.0...'
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
            Log::error("❌ [VPS #{$vps->id}] Redis Agent provision failed", [
                'error' => $e->getMessage(),
                'trace' => Str::limit($e->getTraceAsString(), 2000)
            ]);

            $vps->update([
                'status' => 'FAILED',
                'status_message' => 'Provision failed: ' . Str::limit($e->getMessage(), 250),
                'error_message' => $e->getMessage(),
            ]);

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

        // 1. Create remote directory
        $remoteDir = '/opt/ezstream-agent';
        $sshService->execute("mkdir -p {$remoteDir}");

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

        foreach ($agentFiles as $filename) {
            $localPath = storage_path("app/ezstream-agent/{$filename}");
            $remotePath = "{$remoteDir}/{$filename}";

            if (!file_exists($localPath)) {
                Log::warning("Agent file not found: {$filename}");
                continue;
            }

            if (!$sshService->uploadFile($localPath, $remotePath)) {
                throw new \Exception("Failed to upload agent file: {$filename}");
            }

            if ($filename === 'agent.py') {
                $sshService->execute("chmod +x {$remotePath}");
            }
        }

        Log::info("✅ [VPS #{$vps->id}] Uploaded " . count($agentFiles) . " agent files");

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

        // 5. Enable and start the service
        $sshService->execute('systemctl daemon-reload');
        $sshService->execute("systemctl enable {$serviceName}");
        $sshService->execute("systemctl restart {$serviceName}");

        // Wait a moment and check status
        sleep(5);
        $status = $sshService->execute("systemctl is-active {$serviceName}");
        if (trim($status) !== 'active') {
            $serviceLog = $sshService->execute("journalctl -u {$serviceName} --no-pager -n 50");
            Log::error("❌ [VPS #{$vps->id}] EZStream Agent service failed to start", [
                'status' => $status,
                'log' => $serviceLog
            ]);
            throw new \Exception('EZStream Agent service failed to start. Check journalctl logs on the VPS.');
        }

        Log::info("✅ [VPS #{$vps->id}] EZStream Agent v6.0 service started successfully");
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
}
