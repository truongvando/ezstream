<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use App\Models\StreamConfiguration;
use App\Models\VpsServer;
use Carbon\Carbon;

class MonitorStreamHealth extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'streams:monitor-health {--stream-id= : Monitor specific stream} {--auto-fix : Auto-fix detected issues}';

    /**
     * The description of the console command.
     */
    protected $description = 'Monitor stream health and detect FFmpeg crashes, ghost streams, and auto-restart issues';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("[HEALTH] Stream Health Monitor");
        
        $streamId = $this->option('stream-id');
        $autoFix = $this->option('auto-fix');
        
        if ($streamId) {
            $this->monitorSpecificStream($streamId, $autoFix);
        } else {
            $this->monitorAllStreams($autoFix);
        }
        
        return 0;
    }
    
    private function monitorSpecificStream(int $streamId, bool $autoFix): void
    {
        $this->info("[STREAM #{$streamId}] Monitoring specific stream");
        
        $stream = StreamConfiguration::find($streamId);
        if (!$stream) {
            $this->error("Stream #{$streamId} not found");
            return;
        }
        
        $this->displayStreamInfo($stream);
        $issues = $this->detectStreamIssues($stream);
        
        if (empty($issues)) {
            $this->info("   [SUCCESS] No issues detected");
        } else {
            $this->warn("   [ISSUES] Found " . count($issues) . " issues:");
            foreach ($issues as $issue) {
                $this->line("      - {$issue['type']}: {$issue['description']}");
                
                if ($autoFix && isset($issue['fix'])) {
                    $this->line("      [AUTO-FIX] Attempting to fix...");
                    $result = $issue['fix']();
                    $this->line("      " . ($result ? "[SUCCESS]" : "[FAILED]") . " Fix result");
                }
            }
        }
    }
    
    private function monitorAllStreams(bool $autoFix): void
    {
        $this->info("[ALL] Monitoring all active streams");
        
        $streams = StreamConfiguration::whereIn('status', ['STREAMING', 'STARTING', 'ERROR'])
            ->with('vpsServer')
            ->get();
            
        $totalIssues = 0;
        $fixedIssues = 0;
        
        foreach ($streams as $stream) {
            $issues = $this->detectStreamIssues($stream);
            $totalIssues += count($issues);
            
            if (!empty($issues)) {
                $this->line("");
                $this->warn("[STREAM #{$stream->id}] Found " . count($issues) . " issues:");
                
                foreach ($issues as $issue) {
                    $this->line("   - {$issue['type']}: {$issue['description']}");
                    
                    if ($autoFix && isset($issue['fix'])) {
                        $result = $issue['fix']();
                        if ($result) {
                            $fixedIssues++;
                            $this->info("   [FIXED] Issue resolved");
                        } else {
                            $this->error("   [FAILED] Could not fix issue");
                        }
                    }
                }
            }
        }
        
        $this->line("");
        $this->info("[SUMMARY] Total issues: {$totalIssues}");
        if ($autoFix) {
            $this->info("[SUMMARY] Fixed issues: {$fixedIssues}");
        }
    }
    
    private function displayStreamInfo(StreamConfiguration $stream): void
    {
        $this->line("[INFO] Stream Details:");
        $this->line("   ID: {$stream->id}");
        $this->line("   Status: {$stream->status}");
        $this->line("   VPS: " . ($stream->vpsServer ? "#{$stream->vpsServer->id} ({$stream->vpsServer->name})" : "None"));
        $this->line("   Last Started: " . ($stream->last_started_at ? $stream->last_started_at->diffForHumans() : "Never"));
        $this->line("   Last Status Update: " . ($stream->last_status_update ? $stream->last_status_update->diffForHumans() : "Never"));
        
        if ($stream->error_message) {
            $this->line("   Error: {$stream->error_message}");
        }
    }
    
    private function detectStreamIssues(StreamConfiguration $stream): array
    {
        $issues = [];
        
        // Issue 1: Ghost stream (agent reports but DB doesn't expect)
        if ($stream->vps_server_id) {
            $agentState = Redis::get("agent_state:{$stream->vps_server_id}");
            if ($agentState) {
                $activeStreams = json_decode($agentState, true) ?: [];
                $isInAgent = in_array($stream->id, $activeStreams);
                
                if ($isInAgent && !in_array($stream->status, ['STREAMING', 'STARTING'])) {
                    $issues[] = [
                        'type' => 'GHOST_STREAM',
                        'description' => "Agent reports running but DB status is '{$stream->status}'",
                        'fix' => function() use ($stream) {
                            return $this->fixGhostStream($stream);
                        }
                    ];
                }
                
                if (!$isInAgent && in_array($stream->status, ['STREAMING', 'STARTING'])) {
                    $issues[] = [
                        'type' => 'MISSING_STREAM',
                        'description' => "DB expects running but agent doesn't report it",
                        'fix' => function() use ($stream) {
                            return $this->fixMissingStream($stream);
                        }
                    ];
                }
            }
        }
        
        // Issue 2: Stuck in STARTING too long
        if ($stream->status === 'STARTING' && $stream->last_started_at) {
            $minutesStuck = $stream->last_started_at->diffInMinutes(now());
            if ($minutesStuck > 5) {
                $issues[] = [
                    'type' => 'STUCK_STARTING',
                    'description' => "Stuck in STARTING for {$minutesStuck} minutes",
                    'fix' => function() use ($stream) {
                        return $this->fixStuckStream($stream);
                    }
                ];
            }
        }
        
        // Issue 3: ERROR status with recent activity
        if ($stream->status === 'ERROR' && $stream->last_status_update) {
            $minutesSinceError = $stream->last_status_update->diffInMinutes(now());
            if ($minutesSinceError < 10 && $stream->vps_server_id) {
                // Check if VPS is still active
                $vps = $stream->vpsServer;
                if ($vps && $vps->status === 'ACTIVE' && $vps->last_heartbeat_at && $vps->last_heartbeat_at->diffInMinutes(now()) < 2) {

                    // Avoid restart conflicts - check if recently attempted
                    $minutesSinceLastStart = $stream->last_started_at ? now()->diffInMinutes($stream->last_started_at) : 999;
                    if ($minutesSinceLastStart > 5) { // Only restart if no recent start attempts
                        $issues[] = [
                            'type' => 'RECENT_ERROR',
                            'description' => "Recent ERROR status but VPS is healthy - possible auto-restart candidate",
                            'fix' => function() use ($stream) {
                                return $this->attemptStreamRestart($stream);
                            }
                        ];
                    } else {
                        $this->line("   ⏳ Skipping restart for stream #{$stream->id} - recent start attempt ({$minutesSinceLastStart}m ago)");
                    }
                }
            }
        }
        
        return $issues;
    }
    
    private function fixGhostStream(StreamConfiguration $stream): bool
    {
        try {
            // Send STOP command to agent
            $command = [
                'command' => 'STOP_STREAM',
                'stream_id' => $stream->id,
                'vps_id' => $stream->vps_server_id,
                'timestamp' => time(),
                'reason' => 'Health monitor ghost stream cleanup',
                'source' => 'MonitorStreamHealth'
            ];

            $channel = "vps-commands:{$stream->vps_server_id}";
            Redis::publish($channel, json_encode($command));
            
            Log::info("[HEALTH] Sent STOP command for ghost stream #{$stream->id}");
            return true;
            
        } catch (\Exception $e) {
            Log::error("[HEALTH] Failed to fix ghost stream #{$stream->id}: {$e->getMessage()}");
            return false;
        }
    }
    
    private function fixMissingStream(StreamConfiguration $stream): bool
    {
        try {
            $stream->update([
                'status' => 'ERROR',
                'error_message' => 'Stream process lost on agent - health monitor detection',
                'vps_server_id' => null
            ]);
            
            Log::info("[HEALTH] Marked missing stream #{$stream->id} as ERROR");
            return true;
            
        } catch (\Exception $e) {
            Log::error("[HEALTH] Failed to fix missing stream #{$stream->id}: {$e->getMessage()}");
            return false;
        }
    }
    
    private function fixStuckStream(StreamConfiguration $stream): bool
    {
        try {
            $stream->update([
                'status' => 'ERROR',
                'error_message' => 'Stream stuck in STARTING - health monitor timeout',
                'vps_server_id' => null
            ]);
            
            Log::info("[HEALTH] Fixed stuck stream #{$stream->id}");
            return true;
            
        } catch (\Exception $e) {
            Log::error("[HEALTH] Failed to fix stuck stream #{$stream->id}: {$e->getMessage()}");
            return false;
        }
    }
    
    private function attemptStreamRestart(StreamConfiguration $stream): bool
    {
        try {
            // This would trigger a restart through the normal flow
            $stream->update([
                'status' => 'INACTIVE',
                'error_message' => null,
                'restart_requested_at' => now()
            ]);
            
            Log::info("[HEALTH] Marked stream #{$stream->id} for restart attempt");
            return true;
            
        } catch (\Exception $e) {
            Log::error("[HEALTH] Failed to attempt restart for stream #{$stream->id}: {$e->getMessage()}");
            return false;
        }
    }
}
