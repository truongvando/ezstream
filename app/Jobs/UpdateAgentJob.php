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

    public $tries = 3;
    public $timeout = 600; // 10 minutes

    public function __construct(public VpsServer $vps)
    {
    }

    public function handle(SshService $sshService): void
    {
        $vps = $this->vps;

        try {
            Log::info("🔄 [UpdateAgent] Starting agent update for VPS #{$vps->id}");

            // Update VPS status
            $vps->update([
                'status' => 'UPDATING',
                'status_message' => 'Updating EZStream Agent...'
            ]);

            // Connect to VPS
            $sshService->connect($vps);

            // Stop agent service
            Log::info("🛑 [UpdateAgent] Stopping agent service...");
            $sshService->execute("sudo systemctl stop ezstream-agent || true");

            // Install latest agent (reuse provision logic)
            $this->installLatestAgent($sshService, $vps);

            // Start agent service
            Log::info("🚀 [UpdateAgent] Starting agent service...");
            $sshService->execute("sudo systemctl start ezstream-agent");
            $sshService->execute("sudo systemctl enable ezstream-agent");

            // Wait and verify
            sleep(5);
            $status = $sshService->execute("sudo systemctl is-active ezstream-agent");
            
            if (trim($status) === 'active') {
                Log::info("✅ [UpdateAgent] Agent updated successfully on VPS #{$vps->id}");
                
                $vps->update([
                    'status' => 'ACTIVE',
                    'status_message' => 'EZStream Agent updated successfully',
                    'last_provisioned_at' => now()
                ]);
            } else {
                throw new \Exception("Agent service failed to start after update");
            }

        } catch (\Exception $e) {
            Log::error("❌ [UpdateAgent] Failed for VPS #{$vps->id}: {$e->getMessage()}");
            
            $vps->update([
                'status' => 'FAILED',
                'status_message' => 'Agent update failed: ' . $e->getMessage(),
                'error_message' => $e->getMessage()
            ]);
            
            throw $e;
        } finally {
            $sshService->disconnect();
        }
    }

    /**
     * Install latest agent from Redis (same as provision)
     */
    private function installLatestAgent(SshService $sshService, VpsServer $vps): void
    {
        Log::info("📦 [UpdateAgent] Installing latest agent from Redis...");

        $remoteDir = '/opt/ezstream-agent';
        
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
        $sshService->execute("sudo rm -rf {$remoteDir}");
        $sshService->execute("sudo mkdir -p {$remoteDir}");
        $sshService->execute("cd /tmp && sudo tar -xzf ezstream-agent-latest.tar.gz -C {$remoteDir}");
        $sshService->execute("sudo chmod +x {$remoteDir}/agent.py");

        // Create systemd service
        $serviceContent = $this->generateSystemdService($remoteDir, $redisHost, $redisPort, $redisPassword, $vps);
        $sshService->execute("sudo tee /etc/systemd/system/ezstream-agent.service > /dev/null << 'EOF'\n{$serviceContent}\nEOF");
        
        // Reload systemd
        $sshService->execute("sudo systemctl daemon-reload");

        Log::info("✅ [UpdateAgent] Agent installed successfully");
    }

    /**
     * Create Python script to download agent from Redis
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

    /**
     * Generate systemd service file
     */
    private function generateSystemdService(string $agentPath, string $redisHost, int $redisPort, ?string $redisPassword, VpsServer $vps): string
    {
        $passwordArg = $redisPassword ? " --redis-password '{$redisPassword}'" : '';
        $command = "/usr/bin/python3 {$agentPath}/agent.py --vps-id {$vps->id} --redis-host {$redisHost} --redis-port {$redisPort}{$passwordArg}";

        return <<<SERVICE
[Unit]
Description=EZStream Agent v6.0 (SRS-Only Streaming)
After=network.target
Requires=network.target

[Service]
Type=simple
User=root
WorkingDirectory={$agentPath}
ExecStart={$command}
Restart=always
RestartSec=10
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
SERVICE;
    }

    public function failed(\Throwable $exception): void
    {
        $vps = $this->vps;
        
        Log::error("💥 [UpdateAgent] Job failed for VPS #{$vps->id}: {$exception->getMessage()}");
        
        $vps->update([
            'status' => 'FAILED',
            'status_message' => 'Agent update failed: ' . $exception->getMessage(),
            'error_message' => $exception->getMessage()
        ]);
    }
}
