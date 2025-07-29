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
    protected $signature = 'agent:test-recovery {--vps-id= : Test specific VPS ID} {--force : Force recovery for ERROR streams} {--stop-ghosts : Stop ghost streams}';

    /**
     * The description of the console command.
     */
    protected $description = 'Test agent recovery mechanism, detect ghost streams, and force sync ERROR streams back to STREAMING';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("🔄 Testing Agent Recovery Mechanism");
        
        $vpsId = $this->option('vps-id');
        $force = $this->option('force');
        $stopGhosts = $this->option('stop-ghosts');

        if ($vpsId) {
            $this->testSpecificVps($vpsId, $force, $stopGhosts);
        } else {
            $this->testAllActiveVps($force, $stopGhosts);
        }
    }
    
    private function testSpecificVps(int $vpsId, bool $force, bool $stopGhosts): void
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

        // Check for ghost streams (agent has but DB doesn't expect)
        $this->checkGhostStreams($vpsId, $activeStreams, $stopGhosts);

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
    
    private function testAllActiveVps(bool $force, bool $stopGhosts): void
    {
        $activeVps = VpsServer::where('status', 'ACTIVE')->get();

        $this->info("🌐 Testing " . $activeVps->count() . " active VPS servers");

        foreach ($activeVps as $vps) {
            $this->line("");
            $this->testSpecificVps($vps->id, $force, $stopGhosts);
        }
    }
    
    private function checkGhostStreams(int $vpsId, array $activeStreams, bool $stopGhosts): void
    {
        if (empty($activeStreams)) {
            return;
        }

        $this->line("");
        $this->warn("👻 Checking for ghost streams...");

        $ghostCount = 0;
        $stoppedCount = 0;

        foreach ($activeStreams as $streamId) {
            $stream = StreamConfiguration::find($streamId);

            if (!$stream) {
                $this->error("   👻 GHOST: Stream #{$streamId} doesn't exist in DB but agent reports it's running!");
                $ghostCount++;

                if ($stopGhosts) {
                    $this->sendStopCommand($vpsId, $streamId);
                    $stoppedCount++;
                }
                continue;
            }

            if (!in_array($stream->status, ['STREAMING', 'STARTING'])) {
                $statusColor = $this->getStatusColor($stream->status);
                $this->warn("   👻 GHOST: Stream #{$streamId} has status {$statusColor}{$stream->status}</> but agent reports it's running!");
                $ghostCount++;

                if ($stopGhosts && in_array($stream->status, ['ERROR', 'INACTIVE', 'STOPPED'])) {
                    $this->sendStopCommand($vpsId, $streamId);
                    $stoppedCount++;
                }
            }
        }

        if ($ghostCount === 0) {
            $this->info("   ✅ No ghost streams detected");
        } else {
            $this->warn("   ⚠️ Found {$ghostCount} ghost streams");
            if ($stoppedCount > 0) {
                $this->info("   🛑 Sent STOP commands for {$stoppedCount} ghost streams");
            }
        }
    }

    private function sendStopCommand(int $vpsId, int $streamId): void
    {
        try {
            $command = [
                'command' => 'STOP_STREAM',
                'stream_id' => $streamId,
                'vps_id' => $vpsId,
                'timestamp' => time()
            ];

            $channel = "vps-commands:{$vpsId}";
            $result = Redis::publish($channel, json_encode($command));

            $this->line("   📤 Sent STOP command for ghost stream #{$streamId} (subscribers: {$result})");
        } catch (\Exception $e) {
            $this->error("   ❌ Failed to send STOP command for stream #{$streamId}: {$e->getMessage()}");
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
