<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use App\Models\StreamConfiguration;
use App\Models\VpsServer;

class CleanupGhostStreams extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'streams:cleanup-ghosts {--dry-run : Show what would be cleaned without actually doing it} {--vps-id= : Target specific VPS}';

    /**
     * The description of the console command.
     */
    protected $description = 'Cleanup ghost streams - streams that agents report but DB doesn\'t expect';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("👻 Starting Ghost Stream Cleanup");
        
        $dryRun = $this->option('dry-run');
        $vpsId = $this->option('vps-id');
        
        if ($dryRun) {
            $this->warn("🔍 DRY RUN MODE - No actual changes will be made");
        }
        
        $totalGhosts = 0;
        $totalStopped = 0;
        
        if ($vpsId) {
            $vps = VpsServer::find($vpsId);
            if (!$vps) {
                $this->error("❌ VPS #{$vpsId} not found");
                return 1;
            }
            
            [$ghosts, $stopped] = $this->cleanupVpsGhosts($vps, $dryRun);
            $totalGhosts += $ghosts;
            $totalStopped += $stopped;
        } else {
            $activeVps = VpsServer::where('status', 'ACTIVE')->get();
            
            foreach ($activeVps as $vps) {
                [$ghosts, $stopped] = $this->cleanupVpsGhosts($vps, $dryRun);
                $totalGhosts += $ghosts;
                $totalStopped += $stopped;
            }
        }
        
        $this->line("");
        $this->info("📊 Summary:");
        $this->info("   Ghost streams found: {$totalGhosts}");
        
        if ($dryRun) {
            $this->info("   Would stop: {$totalStopped}");
            $this->warn("   Run without --dry-run to actually stop ghost streams");
        } else {
            $this->info("   Ghost streams stopped: {$totalStopped}");
        }
        
        return 0;
    }
    
    private function cleanupVpsGhosts(VpsServer $vps, bool $dryRun): array
    {
        $this->line("");
        $this->info("🔍 Checking VPS #{$vps->id} ({$vps->name})");
        
        // Get agent state
        $agentStateKey = "agent_state:{$vps->id}";
        $agentState = Redis::get($agentStateKey);

        if (!$agentState) {
            $this->warn("   ⚠️ No agent state found - skipping");
            return [0, 0];
        }

        $agentData = json_decode($agentState, true) ?: [];

        // Extract active_streams from agent data structure
        $activeStreams = $agentData['active_streams'] ?? $agentData;
        
        if (empty($activeStreams)) {
            $this->info("   ✅ No active streams reported by agent");
            return [0, 0];
        }
        
        // Ensure activeStreams is array and convert to strings safely
        $activeStreams = is_array($activeStreams) ? $activeStreams : [];
        $streamIds = [];

        foreach ($activeStreams as $streamId) {
            // Handle both simple values and nested arrays/objects
            if (is_scalar($streamId)) {
                $streamIds[] = (string) $streamId;
            } elseif (is_array($streamId) && isset($streamId['id'])) {
                $streamIds[] = (string) $streamId['id'];
            } elseif (is_object($streamId) && isset($streamId->id)) {
                $streamIds[] = (string) $streamId->id;
            }
        }

        $this->info("   📊 Agent reports " . count($streamIds) . " active streams: " . implode(', ', $streamIds));
        
        $ghostCount = 0;
        $stoppedCount = 0;
        
        foreach ($streamIds as $streamId) {
            $stream = StreamConfiguration::find($streamId);
            $isGhost = false;
            $shouldStop = false;
            $reason = '';
            
            if (!$stream) {
                $isGhost = true;
                $shouldStop = true;
                $reason = "Stream doesn't exist in DB";
            } elseif (!in_array($stream->status, ['STREAMING', 'STARTING'])) {
                $isGhost = true;
                $reason = "DB status is '{$stream->status}' (expected STREAMING/STARTING)";
                
                // Only stop if it's clearly wrong (not a potential recovery case)
                if (in_array($stream->status, ['STOPPED', 'COMPLETED']) || 
                    ($stream->status === 'INACTIVE' && !$stream->vps_server_id)) {
                    $shouldStop = true;
                }
            } elseif ($stream->vps_server_id && $stream->vps_server_id != $vps->id) {
                $isGhost = true;
                $reason = "Stream assigned to different VPS #{$stream->vps_server_id}";
                $shouldStop = true;
            }
            
            if ($isGhost) {
                $ghostCount++;
                $statusIcon = $shouldStop ? '🛑' : '⚠️';
                $this->warn("   {$statusIcon} GHOST #{$streamId}: {$reason}");
                
                if ($shouldStop) {
                    if ($dryRun) {
                        $this->line("      [DRY RUN] Would send STOP command");
                    } else {
                        $this->sendStopCommand($vps->id, $streamId);
                        $stoppedCount++;
                    }
                } else {
                    $this->line("      Skipping (potential recovery case)");
                }
            }
        }
        
        if ($ghostCount === 0) {
            $this->info("   ✅ No ghost streams found");
        }
        
        return [$ghostCount, $stoppedCount];
    }
    
    private function sendStopCommand(int $vpsId, int $streamId): void
    {
        try {
            $command = [
                'command' => 'STOP_STREAM',
                'stream_id' => $streamId,
                'vps_id' => $vpsId,
                'timestamp' => time(),
                'reason' => 'Ghost stream cleanup'
            ];

            $channel = "vps_commands:{$vpsId}";
            $result = Redis::publish($channel, json_encode($command));
            
            $this->line("      📤 Sent STOP command (subscribers: {$result})");
            
            Log::info("👻 [GhostCleanup] Sent STOP command for ghost stream #{$streamId} on VPS #{$vpsId}");
            
        } catch (\Exception $e) {
            $this->error("      ❌ Failed to send STOP command: {$e->getMessage()}");
            Log::error("❌ [GhostCleanup] Failed to send STOP command for stream #{$streamId}: {$e->getMessage()}");
        }
    }
}
