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
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProvisionVpsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 1;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 300; // 5 minutes

    /**
     * The VPS server instance.
     * @var VpsServer
     */
    public VpsServer $vps;

    /**
     * Create a new job instance.
     */
    public function __construct(VpsServer $vps)
    {
        $this->vps = $vps;
        Log::channel('provisioning')->info("✅ [VPS #{$this->vps->id}] Job Constructed.");
    }

    /**
     * Execute the job.
     */
    public function handle(SshService $sshService): void
    {
        Log::channel('provisioning')->info("🚀 [VPS #{$this->vps->id}] Starting provisioning for: {$this->vps->name}");

        try {
            Log::channel('provisioning')->info("🔌 [VPS #{$this->vps->id}] Attempting SSH connection to {$this->vps->ip_address}");
            
            if (!$sshService->connect($this->vps)) {
                throw new \Exception("Failed to connect to VPS via SSH.");
            }

            Log::channel('provisioning')->info("✅ [VPS #{$this->vps->id}] SSH connection successful");
            $this->vps->update(['status' => 'PROVISIONING', 'status_message' => 'Connecting and installing packages...']);

            // 1. Install required packages
            Log::channel('provisioning')->info("📦 [VPS #{$this->vps->id}] Installing required packages (ffmpeg, jq, curl)");
            $updateResult = $sshService->execute('sudo apt-get update -y');
            Log::channel('provisioning')->info("📦 [VPS #{$this->vps->id}] apt-get update completed");
            
            $installResult = $sshService->execute('sudo apt-get install -y ffmpeg jq curl');
            Log::channel('provisioning')->info("✅ [VPS #{$this->vps->id}] Packages installed successfully");

            // 2. Deploy Streaming Agent
            Log::channel('provisioning')->info("📁 [VPS #{$this->vps->id}] Deploying streaming agent");
            $agentLocalPath = storage_path('app/agent/main.sh');
            $agentRemotePath = '/opt/streaming_agent/main.sh';

            Log::channel('provisioning')->info("📁 [VPS #{$this->vps->id}] Creating agent directory");
            $sshService->execute('sudo mkdir -p /opt/streaming_agent');
            
            Log::channel('provisioning')->info("📤 [VPS #{$this->vps->id}] Uploading agent script from {$agentLocalPath}");
            
            // Alternative upload method using base64 encoding (more reliable than SFTP)
            $agentContent = file_get_contents($agentLocalPath);
            $base64Content = base64_encode($agentContent);
            
            // Upload via SSH command instead of SFTP
            $uploadCommand = "echo '{$base64Content}' | base64 -d | sudo tee {$agentRemotePath} > /dev/null";
            $uploadResult = $sshService->execute($uploadCommand);
            
            // Verify upload worked
            $verifySize = $sshService->execute("stat -c%s {$agentRemotePath} 2>/dev/null || echo '0'");
            $expectedSize = strlen($agentContent);
            
            if (trim($verifySize) != $expectedSize) {
                throw new \Exception("Failed to upload streaming agent script. Expected size: {$expectedSize}, got: " . trim($verifySize));
            }
            
            Log::channel('provisioning')->info("✅ [VPS #{$this->vps->id}] Agent uploaded via SSH command ({$expectedSize} bytes)");
            
            Log::channel('provisioning')->info("🔧 [VPS #{$this->vps->id}] Setting executable permissions");
            $sshService->execute("sudo chmod +x {$agentRemotePath}");
            Log::channel('provisioning')->info("✅ [VPS #{$this->vps->id}] Streaming agent deployed successfully");

            // 3. Simple verification that agent was deployed correctly
            Log::channel('provisioning')->info("🔍 [VPS #{$this->vps->id}] Verifying agent deployment");
            $this->vps->update(['status_message' => 'Verifying deployment...']);
            
            // Just check if the file exists and is executable
            $checkAgent = $sshService->execute("test -x {$agentRemotePath} && echo 'OK' || echo 'FAIL'");
            if (trim($checkAgent) !== 'OK') {
                throw new \Exception("Agent script is not executable or missing");
            }
            Log::channel('provisioning')->info("✅ [VPS #{$this->vps->id}] Agent verification passed");

            // 4. Deploy VPS Stats Agent
            Log::channel('provisioning')->info("📊 [VPS #{$this->vps->id}] Deploying VPS Stats Agent");
            $this->deployStatsAgent($sshService);

            // 5. Update status to ACTIVE
            $this->vps->update([
                'status' => 'ACTIVE',
                'status_message' => 'Provisioning completed successfully',
                'provisioned_at' => now(),
            ]);
            
            Log::channel('provisioning')->info("🎉 [VPS #{$this->vps->id}] Provisioning completed successfully! Status: ACTIVE");

        } catch (\Exception $e) {
            Log::channel('provisioning')->error("💥 [VPS #{$this->vps->id}] Provision error: {$e->getMessage()}");
            $this->vps->update([
                'status' => 'PROVISION_FAILED',
                'status_message' => 'Provision failed: ' . $e->getMessage()
            ]);
            throw $e; // Re-throw để trigger failed() method
        } finally {
            $sshService->disconnect();
            Log::channel('provisioning')->info("🔌 [VPS #{$this->vps->id}] SSH connection closed");
        }
    }

    /**
     * Deploy VPS Stats Agent via SFTP (more reliable than base64 encoding)
     */
    private function deployStatsAgent(SshService $sshService): void
    {
        try {
            $this->vps->update(['status_message' => 'Deploying stats monitoring agent...']);

            // Install bc package (required for calculations)
            Log::channel('provisioning')->info("📦 [VPS #{$this->vps->id}] Installing bc package for calculations");
            $sshService->execute('sudo apt-get install -y bc');

            // Generate auth token and webhook URL
            $authToken = hash('sha256', "vps_stats_{$this->vps->id}_" . config('app.key'));
            $webhookUrl = config('app.url') . '/api/vps-stats';

            Log::channel('provisioning')->info("📊 [VPS #{$this->vps->id}] Creating stats agent via SFTP");

            // Create local temp files with Unix line endings
            $scriptContent = $this->getStatsAgentScript($authToken, $webhookUrl);
            $serviceContent = $this->getSystemdServiceContent($authToken, $webhookUrl);
            
            $tempScript = tempnam(sys_get_temp_dir(), 'vps_agent_');
            $tempService = tempnam(sys_get_temp_dir(), 'vps_service_');
            
            // Write with explicit Unix line endings
            file_put_contents($tempScript, str_replace("\r\n", "\n", $scriptContent));
            file_put_contents($tempService, str_replace("\r\n", "\n", $serviceContent));

            // Upload files via SFTP
            Log::channel('provisioning')->info("📤 [VPS #{$this->vps->id}] Uploading agent files via SFTP");
            
            $sftp = new \phpseclib3\Net\SFTP($this->vps->ip_address, $this->vps->ssh_port);
            if (!$sftp->login($this->vps->ssh_user, $this->vps->ssh_password)) {
                throw new \Exception("SFTP login failed");
            }
            
            // Upload script
            if (!$sftp->put('/opt/vps-stats-agent.sh', $tempScript, \phpseclib3\Net\SFTP::SOURCE_LOCAL_FILE)) {
                throw new \Exception("Failed to upload stats agent script");
            }
            
            // Upload service
            if (!$sftp->put('/etc/systemd/system/vps-stats-agent.service', $tempService, \phpseclib3\Net\SFTP::SOURCE_LOCAL_FILE)) {
                throw new \Exception("Failed to upload systemd service");
            }

            // Cleanup temp files
            unlink($tempScript);
            unlink($tempService);

            // Set permissions and start service
            Log::channel('provisioning')->info("🔧 [VPS #{$this->vps->id}] Setting permissions and starting service");
            $sshService->execute('sudo chmod +x /opt/vps-stats-agent.sh');
            $sshService->execute('sudo systemctl daemon-reload');
            $sshService->execute('sudo systemctl enable vps-stats-agent');
            $sshService->execute('sudo systemctl start vps-stats-agent');

            // Verify service is running
            sleep(3); // Wait for service to start
            $serviceStatus = $sshService->execute('sudo systemctl is-active vps-stats-agent');
            if (trim($serviceStatus) === 'active') {
                Log::channel('provisioning')->info("✅ [VPS #{$this->vps->id}] Stats agent service started successfully");
            } else {
                Log::channel('provisioning')->warning("⚠️ [VPS #{$this->vps->id}] Stats agent service status: " . trim($serviceStatus));
                
                // Try to get error details
                $serviceDetails = $sshService->execute('sudo systemctl status vps-stats-agent --no-pager -l');
                Log::channel('provisioning')->warning("📋 [VPS #{$this->vps->id}] Service details: " . $serviceDetails);
            }

        } catch (\Exception $e) {
            Log::channel('provisioning')->warning("⚠️ [VPS #{$this->vps->id}] Stats agent deployment failed: {$e->getMessage()}");
            // Don't throw - stats agent is not critical for basic VPS functionality
        }
    }

    /**
     * Get the stats agent script content
     */
    private function getStatsAgentScript(string $authToken, string $webhookUrl): string
    {
        return "#!/bin/bash

# VPS Stats Agent v2.0 - Auto-deployed via ProvisionVpsJob
VPS_ID=\"{$this->vps->id}\"
AUTH_TOKEN=\"{$authToken}\"
WEBHOOK_URL=\"{$webhookUrl}\"
INTERVAL=60
LOG_FILE=\"/var/log/vps-stats-agent.log\"

log() {
    echo \"[\$(date '+%Y-%m-%d %H:%M:%S')] \$1\" | tee -a \"\$LOG_FILE\"
}

get_cpu_usage() {
    top -bn1 | grep \"Cpu(s)\" | awk '{print \$2}' | sed 's/%us,//' | head -1
}

get_ram_usage() {
    local mem_info=\$(cat /proc/meminfo)
    local total=\$(echo \"\$mem_info\" | grep MemTotal | awk '{print \$2}')
    local free=\$(echo \"\$mem_info\" | grep MemFree | awk '{print \$2}')
    local buffers=\$(echo \"\$mem_info\" | grep Buffers | awk '{print \$2}')
    local cached=\$(echo \"\$mem_info\" | grep \"^Cached\" | awk '{print \$2}')
    
    local used=\$((total - free - buffers - cached))
    echo \"scale=2; \$used * 100 / \$total\" | bc
}

get_disk_usage() {
    df -h / | awk 'NR==2 {print \$5}' | sed 's/%//'
}

send_stats() {
    local cpu_usage=\$(get_cpu_usage)
    local ram_usage=\$(get_ram_usage)
    local disk_usage=\$(get_disk_usage)
    local timestamp=\$(date +%s)
    
    if [[ -z \"\$cpu_usage\" || -z \"\$ram_usage\" || -z \"\$disk_usage\" ]]; then
        log \"ERROR: Failed to collect stats\"
        return 1
    fi
    
    local json_payload=\"{\\\"vps_id\\\": \$VPS_ID, \\\"cpu_usage\\\": \$cpu_usage, \\\"ram_usage\\\": \$ram_usage, \\\"disk_usage\\\": \$disk_usage, \\\"timestamp\\\": \$timestamp}\"
    
    local response=\$(curl -s -w \"%{http_code}\" \\
        -X POST \"\$WEBHOOK_URL\" \\
        -H \"Content-Type: application/json\" \\
        -H \"X-VPS-Auth-Token: \$AUTH_TOKEN\" \\
        -d \"\$json_payload\" \\
        --connect-timeout 10 \\
        --max-time 30)
    
    local http_code=\"\${response: -3}\"
    
    if [[ \"\$http_code\" == \"200\" ]]; then
        log \"SUCCESS: Stats sent (CPU: \${cpu_usage}%, RAM: \${ram_usage}%, Disk: \${disk_usage}%)\"
        return 0
    else
        log \"ERROR: HTTP \$http_code - \$response\"
        return 1
    fi
}

log \"VPS Stats Agent started (VPS ID: \$VPS_ID)\"

while true; do
    send_stats
    sleep \$INTERVAL
done";
    }

    /**
     * Get systemd service content
     */
    private function getSystemdServiceContent(string $authToken, string $webhookUrl): string
    {
        return "[Unit]
Description=VPS Stats Agent
After=network.target

[Service]
Type=simple
User=root
ExecStart=/opt/vps-stats-agent.sh
Restart=always
RestartSec=10
StandardOutput=append:/var/log/vps-stats-agent.log
StandardError=append:/var/log/vps-stats-agent.log

[Install]
WantedBy=multi-user.target";
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::channel('provisioning')->error("--- 💣 [VPS #{$this->vps->id}] JOB FAILED ---");
        Log::channel('provisioning')->error("Error: " . $exception->getMessage());
        
        $this->vps->refresh();
        $this->vps->update([
            'status' => 'PROVISION_FAILED',
            'status_message' => 'Job failed: ' . $exception->getMessage(),
        ]);
    }
} 