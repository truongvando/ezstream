<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use App\Models\VpsServer;

class DebugAgentCommunication extends Command
{
    protected $signature = 'debug:agent-communication {vps_id?}';
    protected $description = 'Debug agent communication and crash handling';

    public function handle()
    {
        $vpsId = $this->argument('vps_id');
        
        if ($vpsId) {
            $this->debugSpecificVps($vpsId);
        } else {
            $this->debugAllVps();
        }
    }
    
    private function debugSpecificVps($vpsId)
    {
        $vps = VpsServer::find($vpsId);
        if (!$vps) {
            $this->error("VPS #{$vpsId} not found");
            return;
        }
        
        $this->info("🔍 Debugging VPS #{$vps->id} - {$vps->name}");
        
        // Check agent state
        $agentState = Redis::get("agent_state:{$vps->id}");
        if ($agentState) {
            $state = json_decode($agentState, true);
            $this->info("📊 Agent State:");
            $this->line("  Last Heartbeat: " . ($state['last_heartbeat'] ?? 'N/A'));
            $this->line("  Status: " . ($state['status'] ?? 'N/A'));
            $this->line("  Active Streams: " . count($state['streams'] ?? []));
        } else {
            $this->warn("❌ No agent state found in Redis");
        }
        
        // Check recent restart requests
        $this->info("\n🔄 Recent Restart Requests:");
        $this->checkRestartRequests($vps->id);
        
        // Check stream statuses
        $this->info("\n📺 Stream Statuses:");
        $this->checkStreamStatuses($vps->id);
    }
    
    private function debugAllVps()
    {
        $vpsList = VpsServer::where('status', 'ACTIVE')->get();
        
        $this->info("🔍 Debugging all active VPS servers...\n");
        
        foreach ($vpsList as $vps) {
            $this->line("VPS #{$vps->id} - {$vps->name}:");
            
            $agentState = Redis::get("agent_state:{$vps->id}");
            if ($agentState) {
                $state = json_decode($agentState, true);
                $lastHeartbeat = $state['last_heartbeat'] ?? 0;
                $timeSince = time() - $lastHeartbeat;
                
                if ($timeSince < 60) {
                    $this->line("  ✅ Online (heartbeat {$timeSince}s ago)");
                } else {
                    $this->line("  ❌ Offline (last heartbeat {$timeSince}s ago)");
                }
            } else {
                $this->line("  ❌ No agent state");
            }
        }
    }
    
    private function checkRestartRequests($vpsId)
    {
        // Check Redis for recent restart requests
        $pattern = "stream_reports:*";
        $keys = Redis::keys($pattern);
        
        $restartRequests = [];
        foreach ($keys as $key) {
            $data = Redis::get($key);
            if ($data) {
                $report = json_decode($data, true);
                if (isset($report['type']) && $report['type'] === 'RESTART_REQUEST' && $report['vps_id'] == $vpsId) {
                    $restartRequests[] = $report;
                }
            }
        }
        
        if (empty($restartRequests)) {
            $this->line("  No recent restart requests");
        } else {
            foreach (array_slice($restartRequests, -5) as $request) {
                $time = date('H:i:s', $request['timestamp']);
                $this->line("  {$time} - Stream {$request['stream_id']}: {$request['reason']} (crash #{$request['crash_count']})");
            }
        }
    }
    
    private function checkStreamStatuses($vpsId)
    {
        $pattern = "stream_status:{$vpsId}:*";
        $keys = Redis::keys($pattern);
        
        if (empty($keys)) {
            $this->line("  No active streams");
            return;
        }
        
        foreach ($keys as $key) {
            $data = Redis::get($key);
            if ($data) {
                $status = json_decode($data, true);
                $streamId = str_replace("stream_status:{$vpsId}:", '', $key);
                $time = date('H:i:s', $status['timestamp']);
                $this->line("  Stream {$streamId}: {$status['status']} - {$status['message']} ({$time})");
            }
        }
    }
}
