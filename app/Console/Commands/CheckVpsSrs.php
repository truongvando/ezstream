<?php

namespace App\Console\Commands;

use App\Models\VpsServer;
use App\Services\SshService;
use Illuminate\Console\Command;

class CheckVpsSrs extends Command
{
    protected $signature = 'vps:check-srs {vps-id : VPS ID to check}';
    protected $description = 'Check SRS server status on VPS';

    public function handle()
    {
        $vpsId = $this->argument('vps-id');
        
        $vps = VpsServer::find($vpsId);
        if (!$vps) {
            $this->error("❌ VPS #{$vpsId} not found");
            return Command::FAILURE;
        }

        $this->info("🎬 Checking SRS server on VPS #{$vps->id}: {$vps->name}");
        $this->info("📍 IP: {$vps->ip_address}");

        try {
            $sshService = new SshService();
            
            if (!$sshService->connect($vps)) {
                $this->error("❌ Failed to connect to VPS");
                return Command::FAILURE;
            }

            // Check Docker containers
            $this->info("\n🐳 Docker containers:");
            $containers = $sshService->execute("docker ps -a");
            $this->line($containers);

            // Check SRS specific container
            $this->info("\n🎬 SRS container status:");
            $srsContainer = $sshService->execute("docker ps --filter 'name=ezstream-srs' --format 'table {{.Names}}\t{{.Status}}\t{{.Ports}}'");
            $this->line($srsContainer ?: "No SRS container found");

            // Check SRS logs if container exists
            $srsExists = $sshService->execute("docker ps -q --filter 'name=ezstream-srs'");
            if (trim($srsExists)) {
                $this->info("\n📋 SRS container logs (last 20 lines):");
                $logs = $sshService->execute("docker logs ezstream-srs --tail 20");
                $this->line($logs);
            }

            // Check ports
            $this->info("\n🔌 Port status:");
            $ports = $sshService->execute("ss -tulpn | grep -E ':(1935|1985|8080)'");
            $this->line($ports ?: "No SRS ports found");

            // Check if SRS API is responding
            $this->info("\n🔗 Testing SRS API:");
            $apiTest = $sshService->execute("curl -s http://localhost:1985/api/v1/versions 2>/dev/null || echo 'API_NOT_RESPONDING'");
            if (strpos($apiTest, 'API_NOT_RESPONDING') !== false) {
                $this->error("❌ SRS API not responding on port 1985");
            } else {
                $this->info("✅ SRS API responding:");
                $this->line($apiTest);
            }

            // Check SRS config file
            $this->info("\n📄 SRS config file:");
            $configExists = $sshService->execute("ls -la /opt/ezstream-agent/srs.conf 2>/dev/null || echo 'CONFIG_NOT_FOUND'");
            if (strpos($configExists, 'CONFIG_NOT_FOUND') !== false) {
                $this->warn("⚠️ SRS config file not found");
            } else {
                $this->line($configExists);
            }

            // Check setup script
            $this->info("\n🔧 SRS setup script:");
            $setupExists = $sshService->execute("ls -la /opt/ezstream-agent/setup-srs.sh 2>/dev/null || echo 'SETUP_NOT_FOUND'");
            if (strpos($setupExists, 'SETUP_NOT_FOUND') !== false) {
                $this->warn("⚠️ SRS setup script not found");
            } else {
                $this->line($setupExists);
                
                // Check if setup script is executable
                if (strpos($setupExists, '-x') !== false) {
                    $this->info("✅ Setup script is executable");
                } else {
                    $this->warn("⚠️ Setup script is not executable");
                }
            }

            $sshService->disconnect();

        } catch (\Exception $e) {
            $this->error("❌ Error: {$e->getMessage()}");
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
