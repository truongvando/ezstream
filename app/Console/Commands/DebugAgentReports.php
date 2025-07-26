<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use App\Models\VpsServer;
use Carbon\Carbon;

class DebugAgentReports extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'agent:debug-reports {--clear : Clear all cached reports} {--vps-id= : Show reports for specific VPS}';

    /**
     * The description of the console command.
     */
    protected $description = 'Debug agent reports and show what data is actually being received';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("🔍 Debugging Agent Reports");
        
        if ($this->option('clear')) {
            $this->clearCachedReports();
            return 0;
        }
        
        $vpsId = $this->option('vps-id');
        
        if ($vpsId) {
            $this->debugSpecificVps($vpsId);
        } else {
            $this->debugAllReports();
        }
        
        return 0;
    }
    
    private function clearCachedReports(): void
    {
        $this->warn("🗑️ Clearing cached agent reports...");
        
        // Clear VPS stats
        $cleared = Redis::del('vps_live_stats');
        $this->info("   Cleared vps_live_stats: {$cleared}");
        
        // Clear agent states
        $agentKeys = Redis::keys('agent_state:*');
        if (!empty($agentKeys)) {
            $cleared = Redis::del($agentKeys);
            $this->info("   Cleared agent states: {$cleared} keys");
        } else {
            $this->info("   No agent state keys found");
        }
        
        $this->info("✅ Cache cleared. Wait for agents to send new reports.");
    }
    
    private function debugSpecificVps(int $vpsId): void
    {
        $this->info("🎯 Debugging VPS #{$vpsId}");
        
        $vps = VpsServer::find($vpsId);
        if (!$vps) {
            $this->error("❌ VPS #{$vpsId} not found in database");
            return;
        }
        
        $this->line("📋 VPS Info:");
        $this->line("   Name: {$vps->name}");
        $this->line("   Status: {$vps->status}");
        $this->line("   IP: {$vps->ip_address}");
        $this->line("   Last Heartbeat: " . ($vps->last_heartbeat_at ? $vps->last_heartbeat_at->diffForHumans() : 'Never'));
        
        // Check agent state
        $this->line("");
        $this->info("💓 Agent Heartbeat State:");
        $agentStateKey = "agent_state:{$vpsId}";
        $agentState = Redis::get($agentStateKey);
        $ttl = Redis::ttl($agentStateKey);
        
        if ($agentState) {
            $activeStreams = json_decode($agentState, true) ?: [];
            $this->line("   Active Streams: " . (empty($activeStreams) ? 'None' : implode(', ', $activeStreams)));
            $this->line("   TTL: {$ttl} seconds");
            $this->line("   Last Update: ~" . Carbon::now()->subSeconds(600 - $ttl)->diffForHumans());
        } else {
            $this->warn("   ⚠️ No agent state found");
        }
        
        // Check VPS stats
        $this->line("");
        $this->info("📊 VPS Stats:");
        $statsJson = Redis::hget('vps_live_stats', $vpsId);
        
        if ($statsJson) {
            $stats = json_decode($statsJson, true);
            $this->line("   CPU: " . ($stats['cpu_usage'] ?? 'N/A') . "%");
            $this->line("   RAM: " . ($stats['ram_usage'] ?? 'N/A') . "%");
            $this->line("   Disk: " . ($stats['disk_usage'] ?? 'N/A') . "%");
            $this->line("   Active Streams: " . ($stats['active_streams'] ?? 'N/A'));
            $this->line("   Received At: " . (isset($stats['received_at']) ? 
                Carbon::createFromTimestamp($stats['received_at'])->diffForHumans() : 'N/A'));
            
            if (isset($stats['network_sent_mb'])) {
                $this->line("   Network Sent: " . number_format($stats['network_sent_mb'], 1) . " MB");
                $this->line("   Network Recv: " . number_format($stats['network_recv_mb'], 1) . " MB");
            }
        } else {
            $this->warn("   ⚠️ No VPS stats found");
        }
    }
    
    private function debugAllReports(): void
    {
        $this->info("🌐 Debugging All Agent Reports");
        
        // Show all VPS with agent states
        $this->line("");
        $this->info("💓 Agent Heartbeat States:");
        $agentKeys = Redis::keys('agent_state:*');
        
        if (empty($agentKeys)) {
            $this->warn("   ⚠️ No agent states found");
        } else {
            foreach ($agentKeys as $key) {
                $vpsId = str_replace('agent_state:', '', $key);
                $agentState = Redis::get($key);
                $ttl = Redis::ttl($key);
                
                if ($agentState) {
                    $activeStreams = json_decode($agentState, true) ?: [];
                    $this->line("   VPS #{$vpsId}: " . count($activeStreams) . " streams, TTL: {$ttl}s");
                }
            }
        }
        
        // Show all VPS stats
        $this->line("");
        $this->info("📊 VPS Stats Reports:");
        $vpsStats = Redis::hgetall('vps_live_stats');
        
        if (empty($vpsStats)) {
            $this->warn("   ⚠️ No VPS stats found");
        } else {
            foreach ($vpsStats as $vpsId => $statsJson) {
                $stats = json_decode($statsJson, true);
                $age = isset($stats['received_at']) ? 
                    Carbon::createFromTimestamp($stats['received_at'])->diffForHumans() : 'Unknown';
                
                $this->line("   VPS #{$vpsId}: CPU " . ($stats['cpu_usage'] ?? 'N/A') . "%, " .
                           "RAM " . ($stats['ram_usage'] ?? 'N/A') . "%, " .
                           "Streams " . ($stats['active_streams'] ?? 'N/A') . " ({$age})");
            }
        }
        
        // Show database VPS status
        $this->line("");
        $this->info("🗄️ Database VPS Status:");
        $vpsServers = VpsServer::all();
        
        foreach ($vpsServers as $vps) {
            $heartbeatAge = $vps->last_heartbeat_at ? $vps->last_heartbeat_at->diffForHumans() : 'Never';
            $this->line("   VPS #{$vps->id} ({$vps->name}): {$vps->status}, Heartbeat: {$heartbeatAge}");
        }
        
        // Show recommendations
        $this->line("");
        $this->info("💡 Recommendations:");
        
        if (empty($agentKeys) && empty($vpsStats)) {
            $this->warn("   🔴 No agent reports found. Check if:");
            $this->line("      - Agent processes are running on VPS servers");
            $this->line("      - Redis connection is working");
            $this->line("      - StreamStatusListener (agent:listen) is running");
        } elseif (empty($agentKeys)) {
            $this->warn("   🟡 No heartbeat data but have stats. Check agent heartbeat logic.");
        } elseif (empty($vpsStats)) {
            $this->warn("   🟡 No stats data but have heartbeats. Check agent stats reporting.");
        } else {
            $this->info("   ✅ Both heartbeat and stats data found. System appears healthy.");
        }
    }
}
