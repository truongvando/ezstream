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

            // 1. Install required packages including nginx-rtmp
            Log::channel('provisioning')->info("📦 [VPS #{$this->vps->id}] Installing required packages (ffmpeg, nginx, nginx-rtmp, jq, curl)");
            $updateResult = $sshService->execute('sudo apt-get update -y');
            Log::channel('provisioning')->info("📦 [VPS #{$this->vps->id}] apt-get update completed");

            // Install nginx and nginx-rtmp module
            $installResult = $sshService->execute('sudo apt-get install -y ffmpeg nginx libnginx-mod-rtmp jq curl');
            Log::channel('provisioning')->info("✅ [VPS #{$this->vps->id}] Packages installed successfully");

            // 1.5. Setup nginx-rtmp configuration
            Log::channel('provisioning')->info("🔧 [VPS #{$this->vps->id}] Configuring nginx-rtmp proxy");
            $this->setupNginxRtmpProxy($sshService);

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

            // 5. Deploy Job Queue Daemon - TỐI ƯU HÓA MỚI
            Log::channel('provisioning')->info("🔄 [VPS #{$this->vps->id}] Deploying Job Queue Daemon");
            $this->deployJobQueueDaemon($sshService);

            // 6. Deploy Process Monitor for 24/7 stability
            Log::channel('provisioning')->info("👁️ [VPS #{$this->vps->id}] Deploying process monitor");
            $this->deployProcessMonitor($sshService);

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

            // ✅ Install sysstat for disk I/O monitoring
            Log::channel('provisioning')->info("📊 [VPS #{$this->vps->id}] Installing sysstat for I/O monitoring");
            $sshService->execute('sudo apt-get install -y sysstat');

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
    
    # Kiểm tra số lượng stream đang chạy
    local active_streams=\$(pgrep -f 'ffmpeg.*flv' | wc -l)
    
    # Kiểm tra load average
    local load_avg=\$(cat /proc/loadavg | awk '{print \$1}')
    
    # ✅ THÊM CAPACITY CALCULATION
    local total_ram_mb=\$(free -m | grep Mem | awk '{print \$2}')
    local used_ram_mb=\$(free -m | grep Mem | awk '{print \$3}')
    local available_ram_mb=\$((total_ram_mb - used_ram_mb))
    local estimated_capacity=\$((available_ram_mb / 100))  # 100MB per stream
    
    # Giới hạn capacity dựa trên disk space
    local available_disk_gb=\$(df -BG / | tail -1 | awk '{print \$4}' | sed 's/G//')
    local disk_capacity=\$((available_disk_gb * 2))  # 500MB per stream estimate
    
    # Lấy capacity thấp nhất
    local max_capacity=\$estimated_capacity
    if [ \$disk_capacity -lt \$max_capacity ]; then
        max_capacity=\$disk_capacity
    fi
    
    # Available capacity = max - current active
    local available_capacity=\$((max_capacity - active_streams))
    if [ \$available_capacity -lt 0 ]; then
        available_capacity=0
    fi
    
    if [[ -z \"\$cpu_usage\" || -z \"\$ram_usage\" || -z \"\$disk_usage\" ]]; then
        log \"ERROR: Failed to collect stats\"
        return 1
    fi
    
    # Cảnh báo nếu tài nguyên cao
    local alert_level=\"normal\"
    if (( \$(echo \"\$cpu_usage > 80\" | bc -l) )) || (( \$(echo \"\$ram_usage > 85\" | bc -l) )) || (( disk_usage > 90 )); then
        alert_level=\"warning\"
        log \"WARNING: High resource usage - CPU: \${cpu_usage}%, RAM: \${ram_usage}%, Disk: \${disk_usage}%\"
    fi
    
    local json_payload=\"{\\\"vps_id\\\": \$VPS_ID, \\\"cpu_usage\\\": \$cpu_usage, \\\"ram_usage\\\": \$ram_usage, \\\"disk_usage\\\": \$disk_usage, \\\"active_streams\\\": \$active_streams, \\\"load_avg\\\": \$load_avg, \\\"alert_level\\\": \\\"\$alert_level\\\", \\\"available_capacity\\\": \$available_capacity, \\\"max_capacity\\\": \$max_capacity, \\\"available_ram_mb\\\": \$available_ram_mb, \\\"available_disk_gb\\\": \$available_disk_gb, \\\"timestamp\\\": \$timestamp}\"
    
    local response=\$(curl -s -w \"%{http_code}\" \\
        -X POST \"\$WEBHOOK_URL\" \\
        -H \"Content-Type: application/json\" \\
        -H \"X-VPS-Auth-Token: \$AUTH_TOKEN\" \\
        -d \"\$json_payload\" \\
        --connect-timeout 10 \\
        --max-time 30)
    
    local http_code=\"\${response: -3}\"
    
    if [[ \"\$http_code\" == \"200\" ]]; then
        log \"SUCCESS: Stats sent (CPU: \${cpu_usage}%, RAM: \${ram_usage}%, Disk: \${disk_usage}%, Streams: \$active_streams, Capacity: \$available_capacity/\$max_capacity)\"
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
     * Deploy process monitor for 24/7 stability (không xung đột với VpsCleanupService)
     */
    private function deployProcessMonitor(SshService $sshService): void
    {
        try {
            Log::channel('provisioning')->info("👁️ [VPS #{$this->vps->id}] Creating process monitor");

            // Chỉ deploy process monitor - KHÔNG dọn dẹp file (để VpsCleanupService lo)
            $processMonitorScript = $this->getProcessMonitorScript();
            $sshService->execute("sudo tee /opt/process-monitor.sh > /dev/null << 'EOF'
{$processMonitorScript}
EOF");
            $sshService->execute('sudo chmod +x /opt/process-monitor.sh');

            // Setup cron job - CHỈ process monitor
            $sshService->execute('sudo crontab -l > /tmp/current_cron 2>/dev/null || true');
            $sshService->execute('echo "*/5 * * * * /opt/process-monitor.sh >> /var/log/process-monitor.log 2>&1" >> /tmp/current_cron');
            $sshService->execute('sudo crontab /tmp/current_cron');
            $sshService->execute('rm /tmp/current_cron');

            Log::channel('provisioning')->info("✅ [VPS #{$this->vps->id}] Process monitor deployed successfully");

        } catch (\Exception $e) {
            Log::channel('provisioning')->warning("⚠️ [VPS #{$this->vps->id}] Process monitor deployment failed: {$e->getMessage()}");
        }
    }

    /**
     * Get process monitor script content
     */
    private function getProcessMonitorScript(): string
    {
        return "#!/bin/bash
# Process monitor script - runs every 5 minutes
# Monitors FFmpeg processes and system health

log() {
    echo \"[\$(date '+%Y-%m-%d %H:%M:%S')] \$1\"
}

# 1. Kiểm tra FFmpeg processes bị treo
stuck_processes=\$(ps aux | grep ffmpeg | grep -v grep | awk '\$10 > 3600 && \$9 == \"S\" {print \$2}')
if [ -n \"\$stuck_processes\" ]; then
    log \"Found stuck FFmpeg processes: \$stuck_processes\"
    echo \"\$stuck_processes\" | xargs -r kill -9
    log \"Killed stuck processes\"
fi

# 2. Kiểm tra memory usage của FFmpeg
high_memory_ffmpeg=\$(ps aux | grep ffmpeg | grep -v grep | awk '\$4 > 10 {print \$2, \$4}')
if [ -n \"\$high_memory_ffmpeg\" ]; then
    log \"High memory FFmpeg processes detected: \$high_memory_ffmpeg\"
fi

# 3. Kiểm tra load average
load_avg=\$(cat /proc/loadavg | awk '{print \$1}')
if (( \$(echo \"\$load_avg > 5.0\" | bc -l) )); then
    log \"WARNING: High load average: \$load_avg\"
fi

# 4. Kiểm tra disk space
disk_usage=\$(df / | tail -1 | awk '{print \$5}' | sed 's/%//')
if [ \$disk_usage -gt 90 ]; then
    log \"CRITICAL: Disk usage at \${disk_usage}%\"
    # Emergency cleanup
    find /tmp -name \"*.mp4\" -mtime +0 -delete 2>/dev/null || true
fi";
    }

    /**
     * Deploy job queue daemon for robust job processing
     */
    private function deployJobQueueDaemon(SshService $sshService): void
    {
        try {
            Log::channel('provisioning')->info("🔄 [VPS #{$this->vps->id}] Creating job queue daemon");

            // Tạo job queue directory
            $sshService->execute('sudo mkdir -p /opt/job-queue/{incoming,processing,completed,failed}');
            $sshService->execute('sudo chmod 755 /opt/job-queue /opt/job-queue/*');

            // Deploy job queue daemon script
            $daemonScript = $this->getJobQueueDaemonScript();
            $sshService->execute("sudo tee /opt/job-queue-daemon.sh > /dev/null << 'EOF'
{$daemonScript}
EOF");
            $sshService->execute('sudo chmod +x /opt/job-queue-daemon.sh');

            // Deploy systemd service for job queue daemon
            $serviceContent = $this->getJobQueueServiceContent();
            $sshService->execute("sudo tee /etc/systemd/system/job-queue-daemon.service > /dev/null << 'EOF'
{$serviceContent}
EOF");

            // Start job queue daemon service
            $sshService->execute('sudo systemctl daemon-reload');
            $sshService->execute('sudo systemctl enable job-queue-daemon');
            $sshService->execute('sudo systemctl start job-queue-daemon');

            // Verify service is running
            sleep(3);
            $serviceStatus = $sshService->execute('sudo systemctl is-active job-queue-daemon');
            if (trim($serviceStatus) === 'active') {
                Log::channel('provisioning')->info("✅ [VPS #{$this->vps->id}] Job queue daemon started successfully");
            } else {
                Log::channel('provisioning')->warning("⚠️ [VPS #{$this->vps->id}] Job queue daemon status: " . trim($serviceStatus));
            }

        } catch (\Exception $e) {
            Log::channel('provisioning')->warning("⚠️ [VPS #{$this->vps->id}] Job queue daemon deployment failed: {$e->getMessage()}");
        }
    }

    /**
     * Get job queue daemon script content
     */
    private function getJobQueueDaemonScript(): string
    {
        return "#!/bin/bash
# Job Queue Daemon v3.0 - Simple & Reliable Job Processing
VPS_ID=\"{$this->vps->id}\"
JOB_QUEUE_DIR=\"/opt/job-queue\"
INCOMING_DIR=\"\$JOB_QUEUE_DIR/incoming\"
PROCESSING_DIR=\"\$JOB_QUEUE_DIR/processing\"
COMPLETED_DIR=\"\$JOB_QUEUE_DIR/completed\"
FAILED_DIR=\"\$JOB_QUEUE_DIR/failed\"
LOG_FILE=\"/var/log/job-queue-daemon.log\"
STREAMING_AGENT=\"/opt/streaming_agent/main.sh\"

log() {
    echo \"[\$(date '+%Y-%m-%d %H:%M:%S')] \$1\" | tee -a \"\$LOG_FILE\"
}

process_job() {
    local job_file=\"\$1\"
    local job_name=\$(basename \"\$job_file\")
    local processing_file=\"\$PROCESSING_DIR/\$job_name\"
    
    log \"Processing job: \$job_name\"
    
    # Move to processing directory with lock
    if ! mv \"\$job_file\" \"\$processing_file\" 2>/dev/null; then
        log \"ERROR: Failed to move job to processing: \$job_name\"
        return 1
    fi
    
    # Execute streaming agent with timeout
    local start_time=\$(date +%s)
    if timeout 3600 bash \"\$STREAMING_AGENT\" \"\$processing_file\" >> \"\$LOG_FILE\" 2>&1; then
        # Success - move to completed
        mv \"\$processing_file\" \"\$COMPLETED_DIR/\$job_name\"
        local duration=\$(((\$(date +%s) - start_time)))
        log \"SUCCESS: Job \$job_name completed in \${duration}s\"
    else
        # Failed - move to failed directory
        mv \"\$processing_file\" \"\$FAILED_DIR/\$job_name\"
        log \"ERROR: Job \$job_name failed or timed out\"
    fi
}

cleanup_old_jobs() {
    # Cleanup jobs older than 24 hours
    find \"\$COMPLETED_DIR\" -name \"*.json\" -mtime +1 -delete 2>/dev/null || true
    find \"\$FAILED_DIR\" -name \"*.json\" -mtime +7 -delete 2>/dev/null || true
}

log \"Job Queue Daemon v3.0 started (VPS ID: \$VPS_ID) - Simple & Reliable\"

while true; do
    # Process incoming jobs - Server đã quyết định capacity
    local processed=0
    for job_file in \"\$INCOMING_DIR\"/*.json; do
        if [ -f \"\$job_file\" ] && [ \"\$processed\" -lt 3 ]; then
            process_job \"\$job_file\" &
            processed=\$((processed + 1))
            sleep 2  # Delay between job starts
        fi
    done
    
    # Cleanup old jobs every hour
    if [ \$((\$(date +%s) % 3600)) -lt 10 ]; then
        cleanup_old_jobs
    fi
    
    # Wait before next check
    sleep 5
done";
    }

    /**
     * Get job queue daemon systemd service content
     */
    private function getJobQueueServiceContent(): string
    {
        return "[Unit]
Description=Job Queue Daemon
After=network.target

[Service]
Type=simple
User=root
ExecStart=/opt/job-queue-daemon.sh
Restart=always
RestartSec=10
StandardOutput=append:/var/log/job-queue-daemon.log
StandardError=append:/var/log/job-queue-daemon.log

[Install]
WantedBy=multi-user.target";
    }

    /**
     * Setup nginx-rtmp proxy for stable streaming
     */
    private function setupNginxRtmpProxy(SshService $sshService): void
    {
        try {
            Log::channel('provisioning')->info("🔧 [VPS #{$this->vps->id}] Creating nginx-rtmp configuration");

            // Create nginx-rtmp configuration
            $nginxRtmpConfig = $this->getNginxRtmpConfig();

            // Upload nginx config
            $sshService->execute("sudo tee /etc/nginx/nginx.conf > /dev/null << 'EOF'
{$nginxRtmpConfig}
EOF");

            // Test nginx configuration
            $testResult = $sshService->execute('sudo nginx -t');
            Log::channel('provisioning')->info("🧪 [VPS #{$this->vps->id}] Nginx config test: " . $testResult);

            // Enable and start nginx
            $sshService->execute('sudo systemctl enable nginx');
            $sshService->execute('sudo systemctl restart nginx');

            // Check nginx status
            $nginxStatus = $sshService->execute('sudo systemctl is-active nginx');
            Log::channel('provisioning')->info("✅ [VPS #{$this->vps->id}] Nginx-RTMP proxy status: " . trim($nginxStatus));

            // Create streaming directories
            $sshService->execute('sudo mkdir -p /var/log/nginx/rtmp');
            $sshService->execute('sudo chown -R www-data:www-data /var/log/nginx/rtmp');

        } catch (\Exception $e) {
            Log::channel('provisioning')->error("❌ [VPS #{$this->vps->id}] Nginx-RTMP setup failed: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get nginx-rtmp configuration
     */
    private function getNginxRtmpConfig(): string
    {
        return "user www-data;
worker_processes auto;
pid /run/nginx.pid;
include /etc/nginx/modules-enabled/*.conf;

events {
    worker_connections 1024;
    use epoll;
    multi_accept on;
}

# RTMP Configuration for Streaming Proxy
rtmp {
    server {
        listen 1935;
        chunk_size 4096;
        allow publish all;
        allow play all;

        # Live streaming application
        application live {
            live on;
            record off;

            # Enable push to external RTMP servers
            # This will be dynamically configured per stream
            # push rtmp://a.rtmp.youtube.com/live2/STREAM_KEY;

            # Auto-reconnect settings
            push_reconnect 30s;

            # Drop frames on slow connections
            drop_idle_publisher 10s;

            # Sync settings
            sync 10ms;

            # Access log
            access_log /var/log/nginx/rtmp/access.log;
        }

        # Health check application
        application health {
            live on;
            record off;
            allow publish 127.0.0.1;
            allow play all;
        }
    }
}

# HTTP Configuration
http {
    include /etc/nginx/mime.types;
    default_type application/octet-stream;

    # Logging
    log_format main '\$remote_addr - \$remote_user [\$time_local] \"\$request\" '
                    '\$status \$body_bytes_sent \"\$http_referer\" '
                    '\"\$http_user_agent\" \"\$http_x_forwarded_for\"';

    access_log /var/log/nginx/access.log main;
    error_log /var/log/nginx/error.log warn;

    # Performance settings
    sendfile on;
    tcp_nopush on;
    tcp_nodelay on;
    keepalive_timeout 65;
    types_hash_max_size 2048;

    # RTMP statistics endpoint
    server {
        listen 8080;
        server_name localhost;

        location /stat {
            rtmp_stat all;
            rtmp_stat_stylesheet stat.xsl;
            add_header Access-Control-Allow-Origin *;
        }

        location /stat.xsl {
            root /usr/share/nginx/html;
        }

        # Health check endpoint
        location /health {
            access_log off;
            return 200 'RTMP Proxy OK';
            add_header Content-Type text/plain;
        }
    }
}";
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