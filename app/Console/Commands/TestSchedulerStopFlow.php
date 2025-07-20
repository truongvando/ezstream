<?php

namespace App\Console\Commands;

use App\Models\StreamConfiguration;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestSchedulerStopFlow extends Command
{
    protected $signature = 'test:scheduler-stop-flow {stream_id?}';
    protected $description = 'Test scheduler stop flow for scheduled streams';

    public function handle()
    {
        $streamId = $this->argument('stream_id');
        
        if ($streamId) {
            $this->testSpecificStream($streamId);
        } else {
            $this->testSchedulerFlow();
        }
    }

    private function testSpecificStream($streamId)
    {
        $stream = StreamConfiguration::find($streamId);
        
        if (!$stream) {
            $this->error("Stream #{$streamId} not found");
            return;
        }

        $this->info("🧪 Testing scheduler stop flow for Stream #{$streamId}");
        $this->displayStreamInfo($stream);

        if (!$stream->enable_schedule) {
            $this->warn("Stream does not have scheduling enabled");
            return;
        }

        if (!$stream->scheduled_end) {
            $this->warn("Stream does not have scheduled_end set");
            return;
        }

        // Simulate scheduler conditions
        $now = Carbon::now();
        $shouldStop = $stream->scheduled_end <= $now && 
                     in_array($stream->status, ['STREAMING', 'STARTING']);

        $this->info("Scheduler evaluation:");
        $this->line("  - Current time: {$now->format('Y-m-d H:i:s')}");
        $this->line("  - Scheduled end: {$stream->scheduled_end->format('Y-m-d H:i:s')}");
        $this->line("  - Should stop: " . ($shouldStop ? 'YES' : 'NO'));

        if ($shouldStop) {
            if ($this->confirm('Simulate scheduler stop for this stream?')) {
                $this->simulateSchedulerStop($stream);
            }
        }
    }

    private function testSchedulerFlow()
    {
        $this->info("🧪 Testing scheduler stop flow");
        
        // Find streams that would be stopped by scheduler
        $now = Carbon::now();
        
        $candidateStreams = StreamConfiguration::where('enable_schedule', true)
            ->where('scheduled_end', '<=', $now)
            ->whereIn('status', ['STREAMING', 'STARTING'])
            ->whereNotNull('scheduled_end')
            ->get();

        if ($candidateStreams->isEmpty()) {
            $this->info("No streams found that would be stopped by scheduler");
            $this->suggestTestSetup();
            return;
        }

        $this->info("Found {$candidateStreams->count()} streams that would be stopped:");
        
        foreach ($candidateStreams as $stream) {
            $this->displayStreamInfo($stream);
        }

        if ($this->confirm('Run scheduler check command?')) {
            $this->call('streams:check-scheduled');
        }
    }

    private function displayStreamInfo($stream)
    {
        $this->line("Stream #{$stream->id}: {$stream->title}");
        $this->line("  - Status: {$stream->status}");
        $this->line("  - Enable schedule: " . ($stream->enable_schedule ? 'YES' : 'NO'));
        $this->line("  - Scheduled end: " . ($stream->scheduled_end ? $stream->scheduled_end->format('Y-m-d H:i:s') : 'Not set'));
        $this->line("  - VPS ID: {$stream->vps_server_id}");
        $this->line("  - Last started: " . ($stream->last_started_at ? $stream->last_started_at->format('Y-m-d H:i:s') : 'Never'));
        $this->line("  - Last stopped: " . ($stream->last_stopped_at ? $stream->last_stopped_at->format('Y-m-d H:i:s') : 'Never'));
        $this->line("");
    }

    private function simulateSchedulerStop($stream)
    {
        $this->info("🛑 Simulating scheduler stop...");
        
        // Log the operation
        Log::info("🧪 [TestSchedulerStop] Simulating scheduler stop for stream #{$stream->id}");
        
        // Update status like scheduler does
        $stream->update([
            'status' => 'STOPPING',
            'last_stopped_at' => now(),
            'error_message' => null,
            'sync_notes' => 'Test scheduler stop initiated at ' . now()->format('Y-m-d H:i:s')
        ]);

        $this->info("✅ Status updated to STOPPING");
        
        // Dispatch stop job
        \App\Jobs\StopMultistreamJob::dispatch($stream);
        $this->info("✅ StopMultistreamJob dispatched");
        
        $this->info("Monitor logs to see the stop flow:");
        $this->line("  tail -f storage/logs/laravel.log | grep -E '(STOP_STREAM|STOPPING|#{$stream->id})'");
    }

    private function suggestTestSetup()
    {
        $this->info("To test scheduler stop flow, you need a stream with:");
        $this->line("  1. enable_schedule = true");
        $this->line("  2. scheduled_end <= current time");
        $this->line("  3. status = 'STREAMING' or 'STARTING'");
        $this->line("");
        $this->info("You can create a test stream with:");
        $this->line("  php artisan tinker");
        $this->line("  \$stream = StreamConfiguration::find(YOUR_STREAM_ID);");
        $this->line("  \$stream->update([");
        $this->line("      'enable_schedule' => true,");
        $this->line("      'scheduled_end' => now()->subMinutes(1),");
        $this->line("      'status' => 'STREAMING'");
        $this->line("  ]);");
    }
}
