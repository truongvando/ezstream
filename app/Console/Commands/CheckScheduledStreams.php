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

        // Debug: Log current time and search criteria
        $this->info("🕐 Checking scheduled streams at: {$now->format('Y-m-d H:i:s')}");

        // Check streams to start (with safe column check)
        $streamsToStart = collect();
        try {
            $streamsToStart = StreamConfiguration::where('enable_schedule', true)
                ->where('scheduled_at', '<=', $now)
                ->where('status', 'INACTIVE')
                ->whereNotNull('scheduled_at')
                // 🚨 CRITICAL: Additional safety checks
                ->where(function($query) use ($now) {
                    // Only start if not recently force stopped by admin
                    $query->whereNull('error_message')
                          ->orWhere('error_message', 'not like', '%Force stopped by admin%');
                })
                ->get();
        } catch (\Exception $e) {
            $this->warn("enable_schedule column not found: " . $e->getMessage());
            $this->warn("Please run: ALTER TABLE stream_configurations ADD COLUMN enable_schedule BOOLEAN DEFAULT FALSE AFTER loop;");
            return 1;
        }

        $this->info("Found {$streamsToStart->count()} streams to start");

        foreach ($streamsToStart as $stream) {
            try {
                Log::info("🕐 Starting scheduled stream: {$stream->title}", [
                    'stream_id' => $stream->id,
                    'scheduled_at' => $stream->scheduled_at,
                    'current_status' => $stream->status,
                    'enable_schedule' => $stream->enable_schedule,
                    'error_message' => $stream->error_message,
                    'last_stopped_at' => $stream->last_stopped_at
                ]);

                // Update status and dispatch job
                $stream->update(['status' => 'STARTING']);
                StartMultistreamJob::dispatch($stream);

                $this->info("Started scheduled stream: {$stream->title}");

            } catch (\Exception $e) {
                Log::error("Failed to start scheduled stream {$stream->id}: {$e->getMessage()}");
                $stream->update(['status' => 'ERROR']);
            }
        }

        // Check streams to stop (only if scheduled_end column exists)
        $streamsToStop = collect();
        try {
            $streamsToStop = StreamConfiguration::where('enable_schedule', true)
                ->where('scheduled_end', '<=', $now)
                ->whereIn('status', ['STREAMING', 'STARTING'])
                ->whereNotNull('scheduled_end')
                ->get();
        } catch (\Exception $e) {
            $this->warn("scheduled_end column not found, skipping stop checks: " . $e->getMessage());
        }

        $this->info("Found {$streamsToStop->count()} streams to stop");

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
