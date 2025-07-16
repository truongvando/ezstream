<?php

namespace App\Console\Commands;

use App\Models\StreamConfiguration;
use App\Jobs\StartMultistreamJob;
use App\Jobs\StopMultistreamJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CheckScheduledStreams extends Command
{
    protected $signature = 'streams:check-scheduled';
    protected $description = 'Check and execute scheduled stream start/stop';

    public function handle()
    {
        $now = Carbon::now();
        
        // Check streams to start
        $streamsToStart = StreamConfiguration::where('enable_schedule', true)
            ->where('scheduled_at', '<=', $now)
            ->where('status', 'INACTIVE')
            ->whereNotNull('scheduled_at')
            ->get();

        foreach ($streamsToStart as $stream) {
            try {
                Log::info("🕐 Starting scheduled stream: {$stream->title}");
                
                // Update status and dispatch job
                $stream->update(['status' => 'STARTING']);
                StartMultistreamJob::dispatch($stream);
                
                $this->info("Started scheduled stream: {$stream->title}");
                
            } catch (\Exception $e) {
                Log::error("Failed to start scheduled stream {$stream->id}: {$e->getMessage()}");
                $stream->update(['status' => 'ERROR']);
            }
        }

        // Check streams to stop
        $streamsToStop = StreamConfiguration::where('enable_schedule', true)
            ->where('scheduled_end', '<=', $now)
            ->whereIn('status', ['STREAMING', 'STARTING'])
            ->whereNotNull('scheduled_end')
            ->get();

        foreach ($streamsToStop as $stream) {
            try {
                Log::info("🕐 Stopping scheduled stream: {$stream->title}");
                
                // Update status and dispatch job
                $stream->update(['status' => 'STOPPING']);
                StopMultistreamJob::dispatch($stream);
                
                $this->info("Stopped scheduled stream: {$stream->title}");
                
            } catch (\Exception $e) {
                Log::error("Failed to stop scheduled stream {$stream->id}: {$e->getMessage()}");
            }
        }

        if ($streamsToStart->count() > 0 || $streamsToStop->count() > 0) {
            $this->info("Processed {$streamsToStart->count()} starts and {$streamsToStop->count()} stops");
        }

        return 0;
    }
}
