<?php

namespace App\Console\Commands;

use App\Models\VpsServer;
use App\Services\SshService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckVpsAgent extends Command
{
    protected $signature = 'vps:check-agent {vps-id : VPS ID to check}';
    protected $description = 'Check agent status on VPS';

    public function handle()
    {
        $vpsId = $this->argument('vps-id');
        
        $vps = VpsServer::find($vpsId);
        if (!$vps) {
            $this->error("❌ VPS #{$vpsId} not found");
            return Command::FAILURE;
        }

        $this->info("🔍 Checking agent on VPS #{$vps->id}: {$vps->name}");
        $this->info("📍 IP: {$vps->ip_address}");

        try {
            $sshService = new SshService();
            
            if (!$sshService->connect($vps)) {
                $this->error("❌ Failed to connect to VPS");
                return Command::FAILURE;
            }

            // Check systemd service status
            $this->info("\n🔧 Checking systemd service...");
            $serviceStatus = $sshService->execute("systemctl status ezstream-agent --no-pager -l");
            $this->line($serviceStatus);

            // Check if service is active
            $isActive = $sshService->execute("systemctl is-active ezstream-agent");
            $this->info("Service status: " . trim($isActive));

            // Check recent logs
            $this->info("\n📋 Recent logs (last 20 lines):");
            $logs = $sshService->execute("journalctl -u ezstream-agent --no-pager -n 20");
            $this->line($logs);

            // Check agent files
            $this->info("\n📁 Agent files:");
            $files = $sshService->execute("ls -la /opt/ezstream-agent/");
            $this->line($files);

            // Check Python process
            $this->info("\n🐍 Python processes:");
            $processes = $sshService->execute("ps aux | grep agent.py | grep -v grep");
            $this->line($processes ?: "No agent processes found");

            // Check Redis connectivity from VPS
            $this->info("\n🔗 Testing Redis connectivity...");
            $redisHost = config('database.redis.default.host');
            $redisPort = config('database.redis.default.port');
            $redisPassword = config('database.redis.default.password');
            
            $redisTest = $sshService->execute("python3 -c \"
import redis
try:
    r = redis.Redis(host='{$redisHost}', port={$redisPort}, password='{$redisPassword}', decode_responses=True)
    r.ping()
    print('✅ Redis connection successful')
except Exception as e:
    print(f'❌ Redis connection failed: {e}')
\"");
            $this->line($redisTest);

            $sshService->disconnect();

        } catch (\Exception $e) {
            $this->error("❌ Error: {$e->getMessage()}");
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
