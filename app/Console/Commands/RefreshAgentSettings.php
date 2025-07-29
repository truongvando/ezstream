<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use App\Models\VpsServer;

class RefreshAgentSettings extends Command
{
    protected $signature = 'agent:refresh-settings {--vps-id= : Specific VPS ID to refresh, or all if not specified}';
    protected $description = 'Force refresh agent settings from Laravel admin panel';

    public function handle()
    {
        $vpsId = $this->option('vps-id');
        
        if ($vpsId) {
            // Refresh specific VPS
            $vps = VpsServer::find($vpsId);
            if (!$vps) {
                $this->error("VPS #{$vpsId} not found");
                return 1;
            }
            
            $this->refreshVpsSettings($vps);
        } else {
            // Refresh all active VPS
            $vpsList = VpsServer::where('status', 'active')->get();
            
            if ($vpsList->isEmpty()) {
                $this->warn('No active VPS servers found');
                return 0;
            }
            
            $this->info("Refreshing settings for {$vpsList->count()} VPS servers...");
            
            foreach ($vpsList as $vps) {
                $this->refreshVpsSettings($vps);
            }
        }
        
        $this->info('✅ Settings refresh commands sent successfully');
        return 0;
    }
    
    private function refreshVpsSettings(VpsServer $vps): void
    {
        try {
            $command = [
                'command' => 'REFRESH_SETTINGS',
                'timestamp' => now()->toISOString()
            ];
            
            $channel = "vps-commands:{$vps->id}";
            $result = Redis::publish($channel, json_encode($command));
            
            $this->line("📤 VPS #{$vps->id} ({$vps->name}): Sent REFRESH_SETTINGS (subscribers: {$result})");
            
        } catch (\Exception $e) {
            $this->error("❌ Failed to send command to VPS #{$vps->id}: {$e->getMessage()}");
        }
    }
}
