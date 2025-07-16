<?php

namespace App\Console\Commands;

use App\Models\VpsServer;
use App\Services\SshService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EnsureVpsManagerRunning extends Command
{
    protected $signature = 'vps:ensure-managers-running';
    protected $description = 'Ensure all VPS managers are running and restart if needed';

    public function handle()
    {
        $this->info('🔍 Checking all VPS managers...');
        
        $vpsServers = VpsServer::where('status', 'ACTIVE')
            ->whereJsonContains('capabilities', 'multistream')
            ->get();

        if ($vpsServers->isEmpty()) {
            $this->info('No multistream VPS servers found.');
            return 0;
        }

        $fixed = 0;
        $total = $vpsServers->count();

        foreach ($vpsServers as $vps) {
            $this->info("Checking VPS {$vps->id} ({$vps->name})...");
            
            if ($this->checkAndFixManager($vps)) {
                $fixed++;
            }
        }

        $this->info("✅ Checked {$total} VPS servers, fixed {$fixed} managers");
        return 0;
    }

    private function checkAndFixManager(VpsServer $vps): bool
    {
        try {
            // Test if manager is responding
            $response = Http::timeout(5)->get("http://{$vps->ip}:9999/health");
            
            if ($response->successful()) {
                $this->line("  ✅ VPS {$vps->id} manager is running");
                return false; // No fix needed
            }
        } catch (\Exception $e) {
            // Manager not responding
        }

        $this->warn("  ⚠️ VPS {$vps->id} manager not responding, attempting restart...");
        
        return $this->restartManager($vps);
    }

    private function restartManager(VpsServer $vps): bool
    {
        $sshService = new SshService();
        
        try {
            if (!$sshService->connect($vps)) {
                $this->error("  ❌ Cannot connect to VPS {$vps->id} via SSH");
                return false;
            }

            // Kill existing manager processes
            $sshService->execute("pkill -f manager.py || true");
            sleep(2);

            // Upload latest manager v2.0 and modules
            $localManagerPath = storage_path('app/multistream/manager.py');
            if (!file_exists($localManagerPath)) {
                $this->error("  ❌ Local manager.py not found");
                return false;
            }

            if (!$sshService->uploadFile($localManagerPath, '/opt/multistream/manager.py')) {
                $this->error("  ❌ Failed to upload manager.py");
                return false;
            }

            // Upload modules
            $this->uploadModules($sshService, $vps);

            $sshService->execute("chmod +x /opt/multistream/manager.py");

            // Start manager with proper Python path
            $startCmd = "cd /opt/multistream && PYTHONPATH=/opt/multistream:/opt/multistream/modules nohup python3 manager.py {$vps->id} https://ezstream.pro vps_token_{$vps->id} > logs/manager.log 2>&1 &";
            $sshService->execute($startCmd);

            // Wait and verify
            sleep(5);
            
            $response = Http::timeout(5)->get("http://{$vps->ip}:9999/health");
            
            if ($response->successful()) {
                $this->info("  ✅ VPS {$vps->id} manager restarted successfully");
                
                // Reset current_streams to actual count
                $statusResponse = Http::timeout(5)->get("http://{$vps->ip}:9999/status");
                if ($statusResponse->successful()) {
                    $data = $statusResponse->json();
                    $actualStreams = $data['active_streams'] ?? 0;
                    $vps->update(['current_streams' => $actualStreams]);
                    $this->line("  🔄 Synced current_streams to {$actualStreams}");
                }
                
                return true;
            } else {
                $this->error("  ❌ VPS {$vps->id} manager still not responding after restart");
                return false;
            }

        } catch (\Exception $e) {
            $this->error("  ❌ Failed to restart VPS {$vps->id} manager: " . $e->getMessage());
            Log::error("Failed to restart VPS {$vps->id} manager", [
                'error' => $e->getMessage(),
                'vps_id' => $vps->id
            ]);
            return false;
        } finally {
            $sshService->disconnect();
        }
    }

    private function uploadModules(SshService $sshService, VpsServer $vps): void
    {
        $this->line("  📦 Uploading Python modules...");

        // Create modules directory
        $sshService->execute("mkdir -p /opt/multistream/modules");

        $modules = [
            'webhook_client.py',
            'stats_reporter.py',
            'stream_handler.py'
        ];

        foreach ($modules as $module) {
            $localPath = storage_path("app/multistream/modules/{$module}");
            $remotePath = "/opt/multistream/modules/{$module}";

            if (!file_exists($localPath)) {
                $this->warn("  ⚠️ Module {$module} not found locally");
                continue;
            }

            if (!$sshService->uploadFile($localPath, $remotePath)) {
                $this->error("  ❌ Failed to upload module {$module}");
                continue;
            }

            $this->line("  ✅ Module {$module} uploaded");
        }

        // Create __init__.py for Python package
        $sshService->execute("touch /opt/multistream/modules/__init__.py");
    }
}
