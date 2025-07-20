<?php

namespace App\Console\Commands;

use App\Models\StreamConfiguration;
use App\Jobs\StopMultistreamJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ForceStopAllStreams extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'streams:force-stop-all 
                            {--user-id= : Stop streams for specific user only}
                            {--dry-run : Show what would be done without actually doing it}
                            {--reason=Admin force stop : Reason for stopping streams}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Force stop all active streams (emergency command)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $userId = $this->option('user-id');
        $reason = $this->option('reason');

        $this->info("🚨 [ForceStopAll] Starting emergency stream shutdown...");
        
        if ($dryRun) {
            $this->warn("🔍 [DRY RUN] No actual changes will be made");
        }

        // Build query
        $query = StreamConfiguration::whereIn('status', ['STREAMING', 'STARTING', 'STOPPING']);
        
        if ($userId) {
            $query->where('user_id', $userId);
            $this->info("🎯 [ForceStopAll] Targeting user ID: {$userId}");
        }

        $activeStreams = $query->get();
        
        if ($activeStreams->isEmpty()) {
            $this->info("✅ [ForceStopAll] No active streams found");
            return 0;
        }

        $this->info("🔍 [ForceStopAll] Found {$activeStreams->count()} active streams");
        
        // Show streams to be stopped
        $this->table(
            ['ID', 'Title', 'Status', 'User', 'VPS', 'Last Update'],
            $activeStreams->map(function ($stream) {
                return [
                    $stream->id,
                    substr($stream->title, 0, 30),
                    $stream->status,
                    $stream->user->name ?? 'N/A',
                    $stream->vpsServer->name ?? 'N/A',
                    $stream->updated_at->diffForHumans()
                ];
            })->toArray()
        );

        if (!$dryRun) {
            if (!$this->confirm('Are you sure you want to force stop all these streams?')) {
                $this->info("❌ [ForceStopAll] Operation cancelled by user");
                return 1;
            }
        }

        $stoppedCount = 0;
        $errorCount = 0;

        foreach ($activeStreams as $stream) {
            try {
                $this->line("🛑 Processing stream #{$stream->id}: {$stream->title}");
                
                if (!$dryRun) {
                    // Force stop with admin reason
                    $stream->update([
                        'status' => 'INACTIVE',
                        'last_stopped_at' => now(),
                        'vps_server_id' => null,
                        'enable_schedule' => false, // Disable schedule to prevent restart
                        'error_message' => "Force stopped by admin: {$reason}",
                    ]);

                    // Decrement VPS stream count if needed
                    if ($stream->vps_server_id && $stream->vpsServer) {
                        $stream->vpsServer->decrement('current_streams');
                    }

                    // Send stop command to agent
                    if ($stream->vps_server_id) {
                        try {
                            $redis = app('redis')->connection();
                            $stopCommand = [
                                'command' => 'STOP_STREAM',
                                'stream_id' => $stream->id,
                            ];
                            $channel = "vps-commands:{$stream->vps_server_id}";
                            $redis->publish($channel, json_encode($stopCommand));
                            $this->info("    📤 Sent stop command to VPS #{$stream->vps_server_id}");
                        } catch (\Exception $e) {
                            $this->warn("    ⚠️ Failed to send stop command: {$e->getMessage()}");
                        }
                    }

                    Log::info("🔧 [ForceStopAll] Force stopped stream #{$stream->id}", [
                        'stream_id' => $stream->id,
                        'title' => $stream->title,
                        'user_id' => $stream->user_id,
                        'reason' => $reason
                    ]);
                    
                    $this->info("    ✅ Force stopped successfully");
                }
                
                $stoppedCount++;
                
            } catch (\Exception $e) {
                $errorCount++;
                $this->error("    ❌ Failed to force stop: {$e->getMessage()}");
                
                Log::error("❌ [ForceStopAll] Failed to force stop stream #{$stream->id}", [
                    'stream_id' => $stream->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->info("🎉 [ForceStopAll] Operation completed!");
        $this->info("    ✅ Stopped: {$stoppedCount}");
        $this->info("    ❌ Errors: {$errorCount}");

        if ($dryRun) {
            $this->warn("🔍 [DRY RUN] No actual changes were made");
        }

        return 0;
    }
}
