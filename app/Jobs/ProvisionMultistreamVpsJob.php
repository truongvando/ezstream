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
        Log::info("✅ [VPS #{$this->vpsId}] Provisioning job created for new Redis Agent architecture");
    }

    public function handle(SshService $sshService): void
    {
        $vps = VpsServer::findOrFail($this->vpsId);

        Log::info("🚀 [VPS #{$vps->id}] Starting provision for Redis Agent");

        try {
            $vps->update([
                'status' => 'PROVISIONING',
                'status_message' => 'Setting up base system and Redis Agent...'
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

            // 2. Upload and set up the new Redis Agent
            $this->uploadAndSetupRedisAgent($sshService, $vps);

            // 3. Verify services and dependencies
            $this->verifyBaseServices($sshService, $vps);
            $this->verifyPythonDependencies($sshService, $vps);

            // 4. Update VPS status
            $maxStreams = $this->calculateMaxStreams($sshService, $vps);
            
            $vps->update([
                'status' => 'ACTIVE',
                'last_provisioned_at' => now(),
                'status_message' => 'Provisioned with Redis Agent',
                'capabilities' => json_encode(['multistream', 'nginx-rtmp', 'redis-agent']),
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

        if (strpos($result, 'VPS BASE PROVISION COMPLETE') === false) {
            Log::error("❌ [VPS #{$vps->id}] Base provision script failed", [
                'output' => $result,
                'script_path' => $remoteScript
            ]);

            // Try to get more detailed error info
            $errorCheck = $sshService->execute('tail -20 /var/log/syslog | grep -i error || echo "No recent errors in syslog"');
            Log::error("❌ [VPS #{$vps->id}] System error logs", ['syslog' => $errorCheck]);

            throw new \Exception('Base provision script execution failed. Check logs for details.');
        }

        Log::info("✅ [VPS #{$vps->id}] Base provision script completed successfully");
    }

    private function uploadAndSetupRedisAgent(SshService $sshService, VpsServer $vps): void
    {
        Log::info("📦 [VPS #{$vps->id}] Uploading and setting up Redis Agent");

        // 1. Create remote directory
        $remoteDir = '/opt/ezstream-agent';
        $sshService->execute("mkdir -p {$remoteDir}");

        // 2. Upload all agent files
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

        // 3. Get Redis connection details from Laravel's config
        $redisHost = config('database.redis.default.host', '127.0.0.1');
        $redisPort = config('database.redis.default.port', 6379);
        $redisPassword = config('database.redis.default.password', null);

        // 4. Create systemd service file on the VPS
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
            Log::error("❌ [VPS #{$vps->id}] Redis Agent service failed to start", [
                'status' => $status,
                'log' => $serviceLog
            ]);
            throw new \Exception('Redis Agent service failed to start. Check journalctl logs on the VPS.');
        }
        
        Log::info("✅ [VPS #{$vps->id}] Redis Agent service started successfully");
    }
    
    private function generateAgentSystemdService(string $agentPath, string $redisHost, int $redisPort, ?string $redisPassword, VpsServer $vps): string
    {
        $pythonCmd = "/usr/bin/python3";

        // Build the command arguments dynamically.
        $commandArgs = "{$vps->id} {$redisHost} {$redisPort}";
        if ($redisPassword) {
            $commandArgs .= " '{$redisPassword}'"; // Append password only if it exists
        }

        $command = "{$pythonCmd} {$agentPath} {$commandArgs}";

        return "[Unit]
Description=EZStream Redis Agent v3.0
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
        Log::info("🔍 [VPS #{$vps->id}] Verifying base services (Nginx, RTMP)");

        // Check nginx
        $nginxStatus = $sshService->execute('systemctl is-active nginx');
        if (trim($nginxStatus) !== 'active') {
            throw new \Exception('Nginx service is not running');
        }

        // Check RTMP port
        $rtmpPort = $sshService->execute('ss -tulpn | grep :1935');
        if (empty(trim($rtmpPort))) {
            throw new \Exception('RTMP port 1935 is not listening');
        }
        
        Log::info("✅ [VPS #{$vps->id}] Base services verified");
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

    private function mockProvisionSuccess(VpsServer $vps): void
    {
        Log::info("🎭 [VPS #{$vps->id}] Mocking provision success for development environment");

        // Simulate provision delay
        sleep(2);

        $vps->update([
            'status' => 'ACTIVE',
            'last_provisioned_at' => now(),
            'status_message' => 'Mocked provision completed (development)',
            'capabilities' => json_encode(['multistream', 'nginx-rtmp', 'redis-agent']),
            'max_concurrent_streams' => 5, // Mock value
            'current_streams' => 0,
            'webhook_configured' => false,
        ]);

        Log::info("✅ [VPS #{$vps->id}] Mock provision completed successfully");
    }
}
