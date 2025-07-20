<?php

namespace App\Console\Commands;

use App\Models\StreamConfiguration;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DebugSchedulerStop extends Command
{
    protected $signature = 'debug:scheduler-stop {stream_id?}';
    protected $description = 'Debug why scheduler is not stopping streams';

    public function handle()
    {
        $streamId = $this->argument('stream_id');
        
        if ($streamId) {
            $this->debugSpecificStream($streamId);
        } else {
            $this->debugAllScheduledStreams();
        }
    }

    private function debugSpecificStream($streamId)
    {
        $stream = StreamConfiguration::find($streamId);
        
        if (!$stream) {
            $this->error("Stream #{$streamId} not found");
            return;
        }

        $this->info("🔍 Debugging Scheduler Stop for Stream #{$streamId}");
        $this->line('');

        $this->displayStreamDetails($stream);
        $this->checkSchedulerConditions($stream);
    }

    private function debugAllScheduledStreams()
    {
        $this->info("🔍 Debugging All Scheduled Streams");
        $this->line('');

        $now = Carbon::now();
        $this->line("Current time: {$now->format('Y-m-d H:i:s')}");
        $this->line('');

        // Find all streams with scheduling enabled
        $scheduledStreams = StreamConfiguration::where('enable_schedule', true)
            ->whereNotNull('scheduled_end')
            ->get();

        if ($scheduledStreams->isEmpty()) {
            $this->warn("No streams found with enable_schedule=true and scheduled_end set");
            return;
        }

        $this->info("Found {$scheduledStreams->count()} streams with scheduling enabled:");
        $this->line('');

        foreach ($scheduledStreams as $stream) {
            $this->displayStreamDetails($stream);
            $this->checkSchedulerConditions($stream);
            $this->line('---');
        }

        // Test scheduler query
        $this->testSchedulerQuery();
    }

    private function displayStreamDetails($stream)
    {
        $this->line("Stream #{$stream->id}: {$stream->title}");
        $this->line("  Status: {$stream->status}");
        $this->line("  Enable Schedule: " . ($stream->enable_schedule ? 'YES' : 'NO'));
        $this->line("  Scheduled End: " . ($stream->scheduled_end ? $stream->scheduled_end->format('Y-m-d H:i:s') : 'NOT SET'));
        $this->line("  Last Started: " . ($stream->last_started_at ? $stream->last_started_at->format('Y-m-d H:i:s') : 'NEVER'));
        $this->line("  VPS ID: " . ($stream->vps_server_id ?: 'NOT ASSIGNED'));
        $this->line("  Last Status Update: " . ($stream->last_status_update ? $stream->last_status_update->format('Y-m-d H:i:s') : 'NEVER'));
    }

    private function checkSchedulerConditions($stream)
    {
        $now = Carbon::now();
        
        $this->line("  Scheduler Conditions Check:");
        
        // Check 1: enable_schedule
        $enableSchedule = $stream->enable_schedule;
        $this->line("    ✓ enable_schedule = true: " . ($enableSchedule ? 'PASS' : 'FAIL'));
        
        // Check 2: scheduled_end exists
        $hasScheduledEnd = !is_null($stream->scheduled_end);
        $this->line("    ✓ scheduled_end exists: " . ($hasScheduledEnd ? 'PASS' : 'FAIL'));
        
        // Check 3: scheduled_end <= now
        $shouldStop = $hasScheduledEnd && $stream->scheduled_end <= $now;
        if ($hasScheduledEnd) {
            $timeDiff = $now->diffInMinutes($stream->scheduled_end, false);
            $this->line("    ✓ scheduled_end <= now: " . ($shouldStop ? 'PASS' : 'FAIL') . " (diff: {$timeDiff} minutes)");
        } else {
            $this->line("    ✓ scheduled_end <= now: SKIP (no scheduled_end)");
        }
        
        // Check 4: status in ['STREAMING', 'STARTING']
        $validStatus = in_array($stream->status, ['STREAMING', 'STARTING']);
        $this->line("    ✓ status in [STREAMING, STARTING]: " . ($validStatus ? 'PASS' : 'FAIL') . " (current: {$stream->status})");
        
        // Check 5: not recently started (2 minutes)
        $notRecentlyStarted = true;
        if ($stream->last_started_at) {
            $minutesSinceStart = $now->diffInMinutes($stream->last_started_at);
            $notRecentlyStarted = $minutesSinceStart >= 2;
            $this->line("    ✓ not recently started (>2min): " . ($notRecentlyStarted ? 'PASS' : 'FAIL') . " (started {$minutesSinceStart} min ago)");
        } else {
            $this->line("    ✓ not recently started (>2min): PASS (never started)");
        }
        
        // Final result
        $shouldBeStoppedByScheduler = $enableSchedule && $hasScheduledEnd && $shouldStop && $validStatus && $notRecentlyStarted;
        $this->line("  🎯 Should be stopped by scheduler: " . ($shouldBeStoppedByScheduler ? 'YES' : 'NO'));
        
        if ($shouldBeStoppedByScheduler) {
            $this->line("  💡 This stream SHOULD be stopped by scheduler");
        } else {
            $this->line("  ⚠️ This stream will NOT be stopped by scheduler");
        }
    }

    private function testSchedulerQuery()
    {
        $this->line('');
        $this->info("🧪 Testing Scheduler Query:");
        
        $now = Carbon::now();
        
        $streamsToStop = StreamConfiguration::where('enable_schedule', true)
            ->where('scheduled_end', '<=', $now)
            ->whereIn('status', ['STREAMING', 'STARTING'])
            ->whereNotNull('scheduled_end')
            ->where(function($query) use ($now) {
                $query->whereNull('last_started_at')
                      ->orWhere('last_started_at', '<=', $now->copy()->subMinutes(2));
            })
            ->get();

        $this->line("Query found {$streamsToStop->count()} streams to stop:");
        
        if ($streamsToStop->count() > 0) {
            foreach ($streamsToStop as $stream) {
                $this->line("  - Stream #{$stream->id}: {$stream->title}");
            }
            
            if ($this->confirm('Run scheduler stop for these streams?')) {
                $this->call('streams:check-scheduled');
            }
        } else {
            $this->line("  (No streams match scheduler stop conditions)");
        }
    }
}
