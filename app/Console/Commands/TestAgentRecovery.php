<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use App\Models\StreamConfiguration;
use App\Models\VpsServer;
use App\Services\StreamProgressService;

class TestAgentRecovery extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'agent:test-recovery {--vps-id= : Test specific VPS ID} {--force : Force recovery for ERROR streams}';

    /**
     * The description of the console command.
     */
    protected $description = 'Test agent recovery mechanism and force sync ERROR streams back to STREAMING';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("🔄 Testing Agent Recovery Mechanism");
        
        $vpsId = $this->option('vps-id');
        $force = $this->option('force');
        
        if ($vpsId) {
            $this->testSpecificVps($vpsId, $force);
        } else {
            $this->testAllActiveVps($force);
        }
    }
    
    private function testSpecificVps(int $vpsId, bool $force): void
    {
        $this->info("🎯 Testing VPS #{$vpsId}");
        
        // Check if VPS exists and is active
        $vps = VpsServer::find($vpsId);
        if (!$vps) {
            $this->error("❌ VPS #{$vpsId} not found");
            return;
        }
        
        if ($vps->status !== 'ACTIVE') {
            $this->warn("⚠️ VPS #{$vpsId} is not ACTIVE (status: {$vps->status})");
        }
        
        // Get agent state from Redis
        $agentStateKey = "agent_state:{$vpsId}";
        $agentState = Redis::get($agentStateKey);
        
        if (!$agentState) {
            $this->warn("⚠️ No agent state found in Redis for VPS #{$vpsId}");
            $activeStreams = [];
        } else {
            $activeStreams = json_decode($agentState, true) ?: [];
            $this->info("📊 Agent reports " . count($activeStreams) . " active streams: " . implode(', ', $activeStreams));
        }
        
        // Get streams from database
        $dbStreams = StreamConfiguration::where('vps_server_id', $vpsId)
            ->whereIn('status', ['STREAMING', 'ERROR', 'STARTING'])
            ->get();
            
        $this->info("💾 Database shows " . $dbStreams->count() . " streams for VPS #{$vpsId}");
        
        $recoveredCount = 0;
        
        foreach ($dbStreams as $stream) {
            $streamInAgent = in_array($stream->id, $activeStreams);
            $statusColor = $this->getStatusColor($stream->status);
            
            $this->line("   Stream #{$stream->id}: {$statusColor}{$stream->status}</> | Agent: " . ($streamInAgent ? '✅' : '❌'));
            
            // Force recovery if requested and conditions met
            if ($force && $stream->status === 'ERROR' && $streamInAgent) {
                $this->warn("   🔄 Force recovering stream #{$stream->id}...");
                
                $stream->update([
                    'status' => 'STREAMING',
                    'last_status_update' => now(),
                    'error_message' => null
                ]);
                
                StreamProgressService::createStageProgress(
                    $stream->id,
                    'streaming',
                    "🔄 Force recovered via agent:test-recovery command"
                );
                
                $recoveredCount++;
                $this->info("   ✅ Stream #{$stream->id} recovered to STREAMING");
            }
        }
        
        if ($recoveredCount > 0) {
            $this->info("🎉 Recovered {$recoveredCount} streams for VPS #{$vpsId}");
        }
        
        // Test heartbeat
        $this->info("💓 Last heartbeat: " . ($vps->last_heartbeat_at ? $vps->last_heartbeat_at->diffForHumans() : 'Never'));
    }
    
    private function testAllActiveVps(bool $force): void
    {
        $activeVps = VpsServer::where('status', 'ACTIVE')->get();
        
        $this->info("🌐 Testing " . $activeVps->count() . " active VPS servers");
        
        foreach ($activeVps as $vps) {
            $this->line("");
            $this->testSpecificVps($vps->id, $force);
        }
    }
    
    private function getStatusColor(string $status): string
    {
        return match($status) {
            'STREAMING' => '<fg=green>',
            'ERROR' => '<fg=red>',
            'STARTING' => '<fg=yellow>',
            'STOPPING' => '<fg=yellow>',
            'INACTIVE' => '<fg=gray>',
            default => '<fg=white>'
        };
    }
}
