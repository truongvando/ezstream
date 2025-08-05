<?php

namespace App\Console\Commands;

use App\Models\VpsServer;
use App\Services\SshService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CleanInstallAgent extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'agent:clean-install {vps_id? : VPS ID to clean install agent}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean install EZStream Agent (remove old, install fresh v6.0 SRS-only)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $vpsId = $this->argument('vps_id');

        if ($vpsId) {
            return $this->cleanInstallSpecificVps($vpsId);
        } else {
            return $this->showVpsAndChoose();
        }
    }

    private function cleanInstallSpecificVps(int $vpsId): int
    {
        $vps = VpsServer::find($vpsId);

        if (!$vps) {
            $this->error("❌ VPS #{$vpsId} not found");
            return 1;
        }

        $this->info("🔍 VPS #{$vps->id}: {$vps->name} ({$vps->ip_address})");
        $this->info("📊 Current status: {$vps->status}");
        $this->info("💬 Status message: {$vps->status_message}");
        $this->newLine();

        if (!$this->confirm('⚠️  This will COMPLETELY REMOVE the old agent and install fresh. Continue?')) {
            return 0;
        }

        return $this->performCleanInstall($vps);
    }

    private function showVpsAndChoose(): int
    {
        $vpsServers = VpsServer::whereIn('status', ['ACTIVE', 'FAILED', 'PENDING'])->get();

        if ($vpsServers->isEmpty()) {
            $this->info("✅ No VPS servers found");
            return 0;
        }

        $this->info("📋 Available VPS servers:");
        $this->newLine();

        $headers = ['ID', 'Name', 'IP Address', 'Status', 'Status Message'];
        $rows = [];

        foreach ($vpsServers as $vps) {
            $rows[] = [
                $vps->id,
                $vps->name,
                $vps->ip_address,
                $vps->status,
                \Illuminate\Support\Str::limit($vps->status_message, 40)
            ];
        }

        $this->table($headers, $rows);
        $this->newLine();

        $vpsId = $this->ask('Enter VPS ID to clean install (or "cancel" to exit)');

        if ($vpsId === 'cancel' || $vpsId === null) {
            $this->info("Operation cancelled");
            return 0;
        }

        $vps = $vpsServers->find($vpsId);
        if (!$vps) {
            $this->error("❌ Invalid VPS ID");
            return 1;
        }

        return $this->performCleanInstall($vps);
    }

    private function performCleanInstall(VpsServer $vps): int
    {
        try {
            $this->info("🚀 Starting clean installation for VPS #{$vps->id}: {$vps->name}");
            $this->info("📍 IP: {$vps->ip_address}");
            $this->newLine();

            // Update VPS status
            $vps->update([
                'status' => 'PENDING',
                'status_message' => 'Clean installing EZStream Agent...',
                'error_message' => null
            ]);

            // Initialize SSH service
            $sshService = new SshService();

            $this->info("🔗 Connecting to VPS...");
            if (!$sshService->connect($vps->ip_address, $vps->ssh_username, $vps->ssh_password, $vps->ssh_port)) {
                throw new \Exception('Failed to connect to VPS via SSH');
            }

            // Step 1: Upload and run clean script
            $this->info("📤 Uploading clean installation script...");
            $this->uploadAndRunCleanScript($sshService);

            // Step 2: Upload fresh agent files
            $this->info("📥 Uploading fresh EZStream Agent v6.0 files...");
            $this->uploadAgentFiles($sshService);

            // Step 3: Create and start service
            $this->info("⚙️ Creating and starting EZStream Agent service...");
            $this->createAndStartService($sshService, $vps);

            // Step 4: Verify installation
            $this->info("🔍 Verifying installation...");
            $this->verifyInstallation($sshService, $vps);

            $this->newLine();
            $this->info("✅ Clean installation completed successfully!");
            $this->info("🎯 EZStream Agent v6.0 SRS-only streaming is now running");

            Log::info("✅ [CleanInstall] Successfully completed for VPS #{$vps->id}", [
                'vps_name' => $vps->name,
                'ip_address' => $vps->ip_address
            ]);

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Clean installation failed: " . $e->getMessage());

            $vps->update([
                'status' => 'FAILED',
                'status_message' => 'Clean install failed: ' . $e->getMessage(),
                'error_message' => $e->getMessage()
            ]);

            Log::error("❌ [CleanInstall] Failed for VPS #{$vps->id}", [
                'error' => $e->getMessage(),
                'vps_name' => $vps->name
            ]);

            return 1;
        }
    }

    private function uploadAndRunCleanScript(SshService $sshService): void
    {
        // Upload clean script
        $localScript = storage_path('app/ezstream-agent/clean-install-agent.sh');
        $remoteScript = '/tmp/clean-install-agent.sh';

        if (!$sshService->uploadFile($localScript, $remoteScript)) {
            throw new \Exception('Failed to upload clean installation script');
        }

        // Make executable and run
        $sshService->execute("chmod +x {$remoteScript}");
        $result = $sshService->execute("bash {$remoteScript}");

        if (strpos($result, 'CLEAN INSTALLATION COMPLETE') === false) {
            throw new \Exception('Clean installation script failed: ' . $result);
        }

        $this->info("✅ Old agent cleaned successfully");
    }

    private function uploadAgentFiles(SshService $sshService): void
    {
        $agentFiles = [
            'agent.py',                    // Main entry point
            'config.py',                   // Configuration management
            'stream_manager.py',           // Stream & Playlist Management
            'process_manager.py',          // FFmpeg Process Management
            'file_manager.py',             // File download/validation/cleanup
            'status_reporter.py',          // Status reporting to Laravel
            'command_handler.py',          // Command processing from Laravel
            'video_optimizer.py',          // Video optimization
            'utils.py',                    // Shared utilities
            // SRS Support files
            'srs_manager.py',              // SRS Server API Manager
            'srs_stream_manager.py',       // SRS-based Stream Manager
            'setup-srs.sh',                // SRS setup script
            'srs.conf'                     // SRS configuration
        ];

        foreach ($agentFiles as $file) {
            $localPath = storage_path("app/ezstream-agent/{$file}");
            $remotePath = "/opt/ezstream-agent/{$file}";

            if (!file_exists($localPath)) {
                $this->warn("⚠️ File not found: {$file}");
                continue;
            }

            if (!$sshService->uploadFile($localPath, $remotePath)) {
                throw new \Exception("Failed to upload {$file}");
            }

            $this->line("📁 Uploaded: {$file}");
        }

        // Make scripts executable
        $sshService->execute('chmod +x /opt/ezstream-agent/*.sh');
        $sshService->execute('chmod +x /opt/ezstream-agent/*.py');
    }

    private function createAndStartService(SshService $sshService, VpsServer $vps): void
    {
        // Create systemd service file
        $serviceContent = $this->generateServiceFile($vps);
        $sshService->execute("cat > /etc/systemd/system/ezstream-agent.service << 'EOF'\n{$serviceContent}\nEOF");

        // Reload systemd and start service
        $sshService->execute('systemctl daemon-reload');
        $sshService->execute('systemctl enable ezstream-agent');
        $sshService->execute('systemctl start ezstream-agent');

        sleep(3); // Wait for service to start
    }

    private function verifyInstallation(SshService $sshService, VpsServer $vps): void
    {
        // Check service status
        $status = $sshService->execute('systemctl is-active ezstream-agent');
        
        if (trim($status) !== 'active') {
            // Get detailed error info
            $journalLogs = $sshService->execute('journalctl -u ezstream-agent --no-pager -n 20');
            throw new \Exception("Service failed to start. Status: {$status}. Logs: {$journalLogs}");
        }

        // Update VPS status
        $vps->update([
            'status' => 'ACTIVE',
            'status_message' => 'EZStream Agent v6.0 SRS-only streaming - Clean installed and running',
            'error_message' => null,
            'capabilities' => ['streaming', 'srs-streaming']
        ]);

        $this->info("✅ Service is active and running");
    }

    private function generateServiceFile(VpsServer $vps): string
    {
        return "[Unit]
Description=EZStream Agent v6.0 (SRS-Only Streaming)
After=network.target redis.service

[Service]
Type=simple
User=root
WorkingDirectory=/opt/ezstream-agent
ExecStart=/usr/bin/python3 /opt/ezstream-agent/agent.py
Restart=always
RestartSec=10
Environment=PYTHONUNBUFFERED=1
Environment=VPS_ID={$vps->id}
Environment=REDIS_HOST={$vps->redis_host}
Environment=REDIS_PORT={$vps->redis_port}
Environment=REDIS_PASSWORD={$vps->redis_password}

[Install]
WantedBy=multi-user.target";
    }
}
