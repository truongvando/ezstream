<?php

namespace App\Console\Commands;

use App\Models\StreamConfiguration;
use Illuminate\Console\Command;

class CheckStreamStatus extends Command
{
    protected $signature = 'check:stream-status {stream_id}';
    protected $description = 'Check detailed status of a specific stream';

    public function handle()
    {
        $streamId = $this->argument('stream_id');
        
        $this->info("🔍 [CheckStream] Checking status for Stream #{$streamId}");

        $stream = StreamConfiguration::with(['user', 'vpsServer'])->find($streamId);
        
        if (!$stream) {
            $this->error("❌ Stream #{$streamId} not found in database");
            return 1;
        }

        // Display stream information
        $this->info("📊 Stream Information:");
        $this->line("  ID: {$stream->id}");
        $this->line("  Title: {$stream->title}");
        $this->line("  User: {$stream->user->name} (ID: {$stream->user_id})");
        $this->line("  Status: {$stream->status}");
        $this->line("  VPS ID: " . ($stream->vps_server_id ?: 'None'));
        $this->line("  VPS Name: " . ($stream->vpsServer->name ?? 'None'));
        $this->line("  Process ID: " . ($stream->process_id ?: 'None'));
        $this->line("  Created: {$stream->created_at}");
        $this->line("  Updated: {$stream->updated_at}");
        $this->line("  Last Started: " . ($stream->last_started_at ?: 'Never'));
        $this->line("  Last Stopped: " . ($stream->last_stopped_at ?: 'Never'));
        $this->line("  Last Status Update: " . ($stream->last_status_update ?: 'Never'));
        $this->line("  Error Message: " . ($stream->error_message ?: 'None'));
        $this->line("  Is Quick Stream: " . ($stream->is_quick_stream ? 'Yes' : 'No'));

        // Check heartbeat status
        if ($stream->last_status_update) {
            $minutesSinceHeartbeat = $stream->last_status_update->diffInMinutes();
            $this->line("  Minutes since heartbeat: {$minutesSinceHeartbeat}");
            
            if ($minutesSinceHeartbeat < 2) {
                $this->info("  💚 Heartbeat status: GOOD (< 2 minutes)");
            } elseif ($minutesSinceHeartbeat < 3) {
                $this->warn("  💛 Heartbeat status: WARNING (2-3 minutes)");
            } else {
                $this->error("  ❤️ Heartbeat status: BAD (> 3 minutes)");
            }
        } else {
            $this->error("  💔 Heartbeat status: NO HEARTBEAT");
        }

        // Check if stream should be synced
        if ($stream->status === 'STARTING' && $stream->last_started_at) {
            $minutesSinceStart = $stream->last_started_at->diffInMinutes();
            $this->line("  Minutes since start: {$minutesSinceStart}");
            
            if ($minutesSinceStart > 5) {
                $this->error("  ⚠️ Stream stuck in STARTING for {$minutesSinceStart} minutes!");
            }
        }

        return 0;
    }
}
