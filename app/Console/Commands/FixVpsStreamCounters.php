<?php

namespace App\Console\Commands;

use App\Models\VpsServer;
use App\Models\StreamConfiguration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FixVpsStreamCounters extends Command
{
    protected $signature = 'vps:fix-stream-counters {--dry-run : Show what would be fixed without making changes}';
    protected $description = 'Fix VPS current_streams counters by recalculating from actual streaming streams';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        
        $this->info('🔧 Fixing VPS stream counters...');
        
        $vpsServers = VpsServer::all();
        $fixedCount = 0;
        
        foreach ($vpsServers as $vps) {
            // Count actual streaming streams on this VPS
            $actualStreamCount = StreamConfiguration::where('vps_server_id', $vps->id)
                ->whereIn('status', ['STREAMING', 'STARTING'])
                ->count();
            
            $currentCount = $vps->current_streams ?? 0;
            
            $this->line("VPS #{$vps->id} ({$vps->name}):");
            $this->line("  Current counter: {$currentCount}");
            $this->line("  Actual streams: {$actualStreamCount}");
            
            if ($currentCount !== $actualStreamCount) {
                $this->warn("  ⚠️ Mismatch detected! Difference: " . ($currentCount - $actualStreamCount));
                
                if (!$dryRun) {
                    $vps->update(['current_streams' => $actualStreamCount]);
                    $this->info("  ✅ Fixed: {$currentCount} → {$actualStreamCount}");
                    $fixedCount++;
                    
                    Log::info("Fixed VPS stream counter", [
                        'vps_id' => $vps->id,
                        'vps_name' => $vps->name,
                        'old_count' => $currentCount,
                        'new_count' => $actualStreamCount
                    ]);
                } else {
                    $this->info("  🔍 Would fix: {$currentCount} → {$actualStreamCount}");
                }
            } else {
                $this->info("  ✅ Counter is correct");
            }
            
            $this->line("");
        }
        
        if ($dryRun) {
            $this->info("🔍 Dry run completed. Use --no-dry-run to apply fixes.");
        } else {
            $this->info("✅ Fixed {$fixedCount} VPS stream counters.");
        }
        
        return 0;
    }
}
