<?php

namespace App\Console\Commands;

use App\Models\StreamConfiguration;
use Illuminate\Console\Command;
use Carbon\Carbon;

class DebugStreamStatus extends Command
{
    protected $signature = 'streams:debug-status';
    protected $description = 'Debug stream status and timeout issues';

    public function handle()
    {
        $this->info("🔍 Debugging Stream Status Issues");
        $this->info("Current time: " . now()->format('Y-m-d H:i:s'));
        $this->newLine();

        // Check all streams with potential issues
        $problematicStreams = StreamConfiguration::whereIn('status', ['STARTING', 'ERROR'])
            ->orWhere('error_message', 'like', '%999 minutes%')
            ->orWhere('error_message', 'like', '%stuck in STARTING%')
            ->get();

        if ($problematicStreams->isEmpty()) {
            $this->info("✅ No problematic streams found!");
            return;
        }

        $this->warn("Found {$problematicStreams->count()} problematic streams:");
        $this->newLine();

        foreach ($problematicStreams as $stream) {
            $this->info("Stream #{$stream->id}: {$stream->title}");
            $this->line("  Status: {$stream->status}");
            $this->line("  Enable Schedule: " . ($stream->enable_schedule ? 'YES' : 'NO'));
            $this->line("  Scheduled At: " . ($stream->scheduled_at ?? 'NULL'));
            $this->line("  Last Started At: " . ($stream->last_started_at ?? 'NULL'));
            $this->line("  Last User Action: " . ($stream->last_user_action ?? 'NULL'));
            $this->line("  Last User Action At: " . ($stream->last_user_action_at ?? 'NULL'));
            $this->line("  Error Message: " . ($stream->error_message ?? 'NULL'));
            
            if ($stream->last_started_at) {
                $minutesSinceStart = now()->diffInMinutes($stream->last_started_at);
                $this->line("  Minutes Since Start: {$minutesSinceStart}");
            } else {
                $this->line("  Minutes Since Start: N/A (never started)");
            }
            
            $this->newLine();
        }

        // Check scheduled streams
        $this->info("🕐 Checking Scheduled Streams:");
        $scheduledStreams = StreamConfiguration::where('enable_schedule', true)->get();
        
        if ($scheduledStreams->isEmpty()) {
            $this->info("No scheduled streams found.");
        } else {
            foreach ($scheduledStreams as $stream) {
                $this->info("Scheduled Stream #{$stream->id}: {$stream->title}");
                $this->line("  Status: {$stream->status}");
                $this->line("  Scheduled At: " . ($stream->scheduled_at ?? 'NULL'));
                $this->line("  Scheduled End: " . ($stream->scheduled_end ?? 'NULL'));
                $this->line("  Should Start: " . ($stream->scheduled_at && $stream->scheduled_at <= now() ? 'YES' : 'NO'));
                $this->newLine();
            }
        }
    }
}
