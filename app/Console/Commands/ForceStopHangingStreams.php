<?php

namespace App\Console\Commands;

use App\Models\StreamConfiguration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ForceStopHangingStreams extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'streams:force-stop-hanging 
                            {--timeout=300 : Timeout in seconds for STOPPING status (default: 5 minutes)}
                            {--dry-run : Show what would be done without actually doing it}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Force stop streams that are stuck in STOPPING status for too long';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $timeout = (int) $this->option('timeout');
        $dryRun = $this->option('dry-run');
        
        $this->info("🔍 Checking for streams stuck in STOPPING status for more than {$timeout} seconds...");
        
        // Find streams stuck in STOPPING status
        $hangingStreams = StreamConfiguration::where('status', 'STOPPING')
            ->where('updated_at', '<', now()->subSeconds($timeout))
            ->get();
            
        if ($hangingStreams->isEmpty()) {
            $this->info("✅ No hanging streams found.");
            return 0;
        }
        
        $this->warn("⚠️ Found {$hangingStreams->count()} hanging stream(s):");
        
        foreach ($hangingStreams as $stream) {
            $stuckDuration = now()->diffInSeconds($stream->updated_at);
            $this->line("  - Stream #{$stream->id} ({$stream->title}) - stuck for {$stuckDuration}s");
            
            if (!$dryRun) {
                try {
                    // Force update to INACTIVE status
                    $stream->update([
                        'status' => 'INACTIVE',
                        'last_stopped_at' => now(),
                        'vps_server_id' => null,
                        'error_message' => "Force stopped due to hanging in STOPPING status for {$stuckDuration}s",
                    ]);
                    
                    // Decrement VPS stream count if needed
                    if ($stream->vps_server_id) {
                        $vps = $stream->vpsServer;
                        if ($vps && $vps->current_streams > 0) {
                            $vps->decrement('current_streams');
                        }
                    }
                    
                    Log::info("🔧 [ForceStopHangingStreams] Force stopped hanging stream #{$stream->id}");
                    $this->info("    ✅ Force stopped successfully");
                    
                } catch (\Exception $e) {
                    Log::error("❌ [ForceStopHangingStreams] Failed to force stop stream #{$stream->id}: {$e->getMessage()}");
                    $this->error("    ❌ Failed to force stop: {$e->getMessage()}");
                }
            } else {
                $this->line("    🔄 Would force stop this stream");
            }
        }
        
        if ($dryRun) {
            $this->info("\n🔍 Dry run completed. Use without --dry-run to actually force stop these streams.");
        } else {
            $this->info("\n✅ Force stop operation completed.");
        }
        
        return 0;
    }
}
