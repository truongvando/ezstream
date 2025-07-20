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

        // Enhanced logging for debugging
        $this->info("🕐 [Scheduler] Checking scheduled streams at: {$now->format('Y-m-d H:i:s')}");
        Log::info("🕐 [Scheduler] Starting scheduled stream check", [
            'current_time' => $now->format('Y-m-d H:i:s'),
            'timezone' => $now->getTimezone()->getName()
        ]);

        // Check streams to start (with safe column check)
        $streamsToStart = collect();
        try {
            $streamsToStart = StreamConfiguration::where('enable_schedule', true)
                ->where('scheduled_at', '<=', $now)
                ->whereIn('status', ['INACTIVE']) // 🔧 FIX: Only INACTIVE, NOT ERROR/STOPPED
                ->whereNotNull('scheduled_at')
                // 🚨 CRITICAL: Additional safety checks
                ->where(function($query) use ($now) {
                    // Only start if not recently force stopped by admin or scheduler
                    $query->whereNull('error_message')
                          ->orWhere('error_message', 'not like', '%Force stopped%')
                          ->orWhere('error_message', 'not like', '%timeout%')
                          ->orWhere('error_message', 'not like', '%killed%');
                })
                // 🔧 FIX: Don't restart streams that were recently stopped (prevent loops)
                ->where(function($query) use ($now) {
                    $query->whereNull('last_stopped_at')
                          ->orWhere('last_stopped_at', '<=', $now->copy()->subMinutes(5));
                })
                ->get();
        } catch (\Exception $e) {
            $this->warn("enable_schedule column not found: " . $e->getMessage());
            $this->warn("Please run: ALTER TABLE stream_configurations ADD COLUMN enable_schedule BOOLEAN DEFAULT FALSE AFTER loop;");
            return 1;
        }

        $this->info("Found {$streamsToStart->count()} streams to start");
        Log::info("🚀 [Scheduler] Found streams to start", [
            'count' => $streamsToStart->count(),
            'stream_ids' => $streamsToStart->pluck('id')->toArray()
        ]);

        foreach ($streamsToStart as $stream) {
            try {
                Log::warning("🚨 [Scheduler] ATTEMPTING TO START scheduled stream: {$stream->title}", [
                    'stream_id' => $stream->id,
                    'scheduled_at' => $stream->scheduled_at,
                    'current_status' => $stream->status,
                    'enable_schedule' => $stream->enable_schedule,
                    'error_message' => $stream->error_message,
                    'last_stopped_at' => $stream->last_stopped_at
                ]);

                // Update status to STARTING (simple, like before)
                $stream->update([
                    'status' => 'STARTING',
                    'last_started_at' => now()
                ]);
                // Dispatch job to queue (proper way)
                StartMultistreamJob::dispatch($stream);

                // Log for debugging
                Log::info("📤 [Scheduler] Job dispatched for stream #{$stream->id}");

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
                // ✅ Enhanced: Avoid stopping streams that just started (give at least 2 minutes)
                ->where(function($query) use ($now) {
                    $query->whereNull('last_started_at')
                          ->orWhere('last_started_at', '<=', $now->copy()->subMinutes(2));
                })
                ->get();
        } catch (\Exception $e) {
            $this->warn("scheduled_end column not found, skipping stop checks: " . $e->getMessage());
        }

        $this->info("Found {$streamsToStop->count()} streams to stop");
        Log::info("🛑 [Scheduler] Found streams to stop", [
            'count' => $streamsToStop->count(),
            'stream_ids' => $streamsToStop->pluck('id')->toArray(),
            'details' => $streamsToStop->map(function($stream) {
                return [
                    'id' => $stream->id,
                    'title' => $stream->title,
                    'scheduled_end' => $stream->scheduled_end?->format('Y-m-d H:i:s'),
                    'status' => $stream->status,
                    'last_started_at' => $stream->last_started_at?->format('Y-m-d H:i:s')
                ];
            })->toArray()
        ]);

        foreach ($streamsToStop as $stream) {
            try {
                Log::info("🕐 [Scheduler] Stopping scheduled stream: {$stream->title}", [
                    'stream_id' => $stream->id,
                    'scheduled_end' => $stream->scheduled_end,
                    'current_status' => $stream->status,
                    'vps_server_id' => $stream->vps_server_id,
                    'last_status_update' => $stream->last_status_update
                ]);

                // ✅ Enhanced: Add scheduler context to prevent conflicts
                $stream->update([
                    'status' => 'STOPPING',
                    'last_stopped_at' => now(),
                    'error_message' => null,
                    'sync_notes' => 'Scheduled stop initiated at ' . now()->format('Y-m-d H:i:s')
                ]);

                // Dispatch stop job with enhanced logging
                StopMultistreamJob::dispatch($stream);

                Log::info("✅ [Scheduler] Stop job dispatched for scheduled stream #{$stream->id}");
                $this->info("Stopped scheduled stream: {$stream->title}");

            } catch (\Exception $e) {
                Log::error("❌ [Scheduler] Failed to stop scheduled stream {$stream->id}: {$e->getMessage()}", [
                    'stream_id' => $stream->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);

                // Mark as error to prevent retry loops
                $stream->update([
                    'status' => 'ERROR',
                    'error_message' => "Scheduler stop failed: " . $e->getMessage(),
                    'last_stopped_at' => now()
                ]);
            }
        }

        if ($streamsToStart->count() > 0 || $streamsToStop->count() > 0) {
            $this->info("Processed {$streamsToStart->count()} starts and {$streamsToStop->count()} stops");
            Log::info("✅ [Scheduler] Completed scheduled stream check", [
                'starts_processed' => $streamsToStart->count(),
                'stops_processed' => $streamsToStop->count(),
                'total_processed' => $streamsToStart->count() + $streamsToStop->count()
            ]);
        } else {
            Log::debug("ℹ️ [Scheduler] No scheduled streams to process at this time");
        }

        return 0;
    }
}
