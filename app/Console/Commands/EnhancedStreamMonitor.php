<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use App\Models\StreamConfiguration;
use App\Models\VpsServer;
use Carbon\Carbon;

class EnhancedStreamMonitor extends Command
{
    protected $signature = 'stream:enhanced-monitor {--detailed : Show detailed information} {--fix : Auto-fix detected issues}';
    protected $description = 'Enhanced stream monitoring with new architecture support';

    public function handle()
    {
        $this->info('🚀 Enhanced Stream Monitor - New Architecture');
        $this->info('=' . str_repeat('=', 60));

        $detailed = $this->option('detailed');
        $autoFix = $this->option('fix');

        // 1. Check Enhanced Heartbeat System
        $this->checkEnhancedHeartbeat($detailed);

        // 2. Check Command Tracking System
        $this->checkCommandTracking($detailed, $autoFix);

        // 3. Check State Machine Health
        $this->checkStateMachineHealth($detailed);

        // 4. Check Agent Communication
        $this->checkAgentCommunication($detailed);

        // 5. Performance Metrics
        $this->showPerformanceMetrics($detailed);

        $this->info("\n✅ Enhanced monitoring complete!");
    }

    private function checkEnhancedHeartbeat(bool $detailed): void
    {
        $this->info("\n💓 Enhanced Heartbeat System");
        $this->line(str_repeat('-', 40));

        $vpsList = VpsServer::where('status', 'active')->get();
        
        foreach ($vpsList as $vps) {
            $agentStateKey = "agent_state:{$vps->id}";
            $agentState = Redis::get($agentStateKey);
            
            if ($agentState) {
                $state = json_decode($agentState, true);
                $lastHeartbeat = $state['last_heartbeat'] ?? null;
                $activeStreams = $state['active_streams'] ?? [];
                $isReAnnounce = $state['is_re_announce'] ?? false;
                $heartbeatCount = Redis::get("heartbeat_count:{$vps->id}") ?? 0;
                
                $status = $lastHeartbeat && 
                         Carbon::parse($lastHeartbeat)->diffInMinutes(now()) <= 3 ? 
                         '✅ HEALTHY' : '❌ UNHEALTHY';
                
                $this->line("VPS #{$vps->id}: {$status}");
                
                if ($detailed) {
                    $this->line("   Active Streams: " . count($activeStreams));
                    $this->line("   Heartbeat Count: {$heartbeatCount}");
                    $this->line("   Re-announce: " . ($isReAnnounce ? 'Yes' : 'No'));
                    
                    if (!empty($activeStreams)) {
                        $this->line("   Stream IDs: " . implode(', ', $activeStreams));
                    }
                }
            } else {
                $this->line("VPS #{$vps->id}: ❌ NO AGENT STATE");
            }
        }
    }

    private function checkCommandTracking(bool $detailed, bool $autoFix): void
    {
        $this->info("\n⚡ Command Tracking System");
        $this->line(str_repeat('-', 40));

        // Check command tracking keys
        $trackingKeys = Redis::keys('command_tracking:*');
        $ackKeys = Redis::keys('command_ack:*');
        $resultKeys = Redis::keys('command_result:*');

        $this->line("Command Tracking Keys: " . count($trackingKeys));
        $this->line("Command ACK Keys: " . count($ackKeys));
        $this->line("Command Result Keys: " . count($resultKeys));

        $pendingCommands = [];
        $stuckCommands = [];

        foreach ($trackingKeys as $key) {
            $data = Redis::get($key);
            if ($data) {
                $commandData = json_decode($data, true);
                $status = $commandData['status'] ?? 'unknown';
                $sentAt = $commandData['sent_at'] ?? time();
                $age = time() - $sentAt;

                if ($status === 'pending') {
                    $pendingCommands[] = $commandData;
                    
                    if ($age > 300) { // 5 minutes
                        $stuckCommands[] = $commandData;
                    }
                }
            }
        }

        $this->line("Pending Commands: " . count($pendingCommands));
        $this->line("Stuck Commands: " . count($stuckCommands));

        if ($detailed && !empty($pendingCommands)) {
            $this->line("\nPending Commands:");
            foreach (array_slice($pendingCommands, 0, 5) as $cmd) {
                $streamId = $cmd['stream_id'] ?? 'unknown';
                $command = $cmd['command'] ?? 'unknown';
                $age = time() - ($cmd['sent_at'] ?? time());
                $this->line("   Stream #{$streamId}: {$command} ({$age}s ago)");
            }
        }

        if (!empty($stuckCommands)) {
            $this->warn("\n⚠️ Stuck Commands Detected:");
            foreach ($stuckCommands as $cmd) {
                $streamId = $cmd['stream_id'] ?? 'unknown';
                $command = $cmd['command'] ?? 'unknown';
                $age = time() - ($cmd['sent_at'] ?? time());
                $this->line("   Stream #{$streamId}: {$command} (stuck for {$age}s)");
                
                if ($autoFix) {
                    // Clean up stuck command
                    $key = "command_tracking:{$streamId}:" . ($cmd['command_id'] ?? '');
                    Redis::del($key);
                    $this->line("     🔧 Cleaned up stuck command");
                }
            }
        }
    }

    private function checkStateMachineHealth(bool $detailed): void
    {
        $this->info("\n🔄 State Machine Health");
        $this->line(str_repeat('-', 40));

        $streams = StreamConfiguration::whereIn('status', ['STARTING', 'STOPPING', 'STREAMING'])->get();
        $stateIssues = [];

        foreach ($streams as $stream) {
            $status = $stream->status;
            $lastUpdate = Carbon::parse($stream->last_status_update ?? $stream->updated_at);
            $minutesSinceUpdate = $lastUpdate->diffInMinutes(now());

            // Check for state machine violations
            if ($status === 'STARTING' && $minutesSinceUpdate > 10) {
                $stateIssues[] = [
                    'stream_id' => $stream->id,
                    'issue' => 'STARTING timeout',
                    'duration' => $minutesSinceUpdate
                ];
            } elseif ($status === 'STOPPING' && $minutesSinceUpdate > 2) {
                $stateIssues[] = [
                    'stream_id' => $stream->id,
                    'issue' => 'STOPPING timeout',
                    'duration' => $minutesSinceUpdate
                ];
            }

            // Check VPS assignment consistency
            if ($stream->vps_server_id) {
                $agentStateKey = "agent_state:{$stream->vps_server_id}";
                $agentState = Redis::get($agentStateKey);
                
                if ($agentState) {
                    $state = json_decode($agentState, true);
                    $activeStreams = $state['active_streams'] ?? [];
                    
                    if ($status === 'STREAMING' && !in_array($stream->id, $activeStreams)) {
                        $stateIssues[] = [
                            'stream_id' => $stream->id,
                            'issue' => 'DB says STREAMING but Agent doesn\'t report',
                            'duration' => $minutesSinceUpdate
                        ];
                    } elseif ($status !== 'STREAMING' && in_array($stream->id, $activeStreams)) {
                        $stateIssues[] = [
                            'stream_id' => $stream->id,
                            'issue' => "DB says {$status} but Agent reports running",
                            'duration' => $minutesSinceUpdate
                        ];
                    }
                }
            }
        }

        if (empty($stateIssues)) {
            $this->line("✅ No state machine issues detected");
        } else {
            $this->warn("⚠️ State Machine Issues:");
            foreach ($stateIssues as $issue) {
                $this->line("   Stream #{$issue['stream_id']}: {$issue['issue']} ({$issue['duration']}m)");
            }
        }

        if ($detailed) {
            $stateCounts = StreamConfiguration::selectRaw('status, count(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray();

            $this->line("\nStream State Distribution:");
            foreach ($stateCounts as $status => $count) {
                $this->line("   {$status}: {$count}");
            }
        }
    }

    private function checkAgentCommunication(bool $detailed): void
    {
        $this->info("\n📡 Agent Communication");
        $this->line(str_repeat('-', 40));

        $vpsList = VpsServer::where('status', 'active')->get();
        $communicationIssues = [];

        foreach ($vpsList as $vps) {
            $lastHeartbeat = $vps->last_heartbeat_at;
            $minutesSinceHeartbeat = $lastHeartbeat ? 
                Carbon::parse($lastHeartbeat)->diffInMinutes(now()) : 999;

            if ($minutesSinceHeartbeat > 5) {
                $communicationIssues[] = [
                    'vps_id' => $vps->id,
                    'issue' => 'No heartbeat',
                    'duration' => $minutesSinceHeartbeat
                ];
            }

            // Check for command acknowledgment delays
            $pendingCommands = Redis::keys("command_tracking:*");
            $delayedAcks = 0;
            
            foreach ($pendingCommands as $key) {
                $data = Redis::get($key);
                if ($data) {
                    $commandData = json_decode($data, true);
                    if (($commandData['vps_id'] ?? null) == $vps->id) {
                        $sentAt = $commandData['sent_at'] ?? time();
                        $acknowledgedAt = $commandData['acknowledged_at'] ?? null;
                        
                        if (!$acknowledgedAt && (time() - $sentAt) > 30) {
                            $delayedAcks++;
                        }
                    }
                }
            }

            if ($delayedAcks > 0) {
                $communicationIssues[] = [
                    'vps_id' => $vps->id,
                    'issue' => "Delayed ACKs ({$delayedAcks})",
                    'duration' => null
                ];
            }

            if ($detailed) {
                $heartbeatCount = Redis::get("heartbeat_count:{$vps->id}") ?? 0;
                $this->line("VPS #{$vps->id}: {$heartbeatCount} heartbeats, last {$minutesSinceHeartbeat}m ago");
            }
        }

        if (empty($communicationIssues)) {
            $this->line("✅ No communication issues detected");
        } else {
            $this->warn("⚠️ Communication Issues:");
            foreach ($communicationIssues as $issue) {
                $duration = $issue['duration'] ? " ({$issue['duration']}m)" : "";
                $this->line("   VPS #{$issue['vps_id']}: {$issue['issue']}{$duration}");
            }
        }
    }

    private function showPerformanceMetrics(bool $detailed): void
    {
        $this->info("\n📊 Performance Metrics");
        $this->line(str_repeat('-', 40));

        // Redis performance
        try {
            $info = Redis::info('stats');
            $totalConnections = $info['total_connections_received'] ?? 0;
            $totalCommands = $info['total_commands_processed'] ?? 0;
            
            $this->line("Redis Total Connections: {$totalConnections}");
            $this->line("Redis Total Commands: {$totalCommands}");
        } catch (\Exception $e) {
            $this->line("❌ Could not get Redis stats");
        }

        // Stream performance
        $totalStreams = StreamConfiguration::count();
        $activeStreams = StreamConfiguration::whereIn('status', ['STREAMING', 'STARTING'])->count();
        $errorStreams = StreamConfiguration::where('status', 'ERROR')->count();

        $this->line("Total Streams: {$totalStreams}");
        $this->line("Active Streams: {$activeStreams}");
        $this->line("Error Streams: {$errorStreams}");

        if ($totalStreams > 0) {
            $successRate = (($totalStreams - $errorStreams) / $totalStreams) * 100;
            $this->line("Success Rate: " . number_format($successRate, 1) . "%");
        }

        if ($detailed) {
            // Show recent activity
            $recentCommands = Redis::keys('command_*');
            $this->line("Recent Command Keys: " . count($recentCommands));
            
            $agentStates = Redis::keys('agent_state:*');
            $this->line("Active Agent States: " . count($agentStates));
        }
    }
}
