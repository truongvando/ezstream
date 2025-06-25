<?php

namespace App\Jobs;

use App\Models\VpsServer;
use App\Services\SshService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncVpsStatsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public VpsServer $vps)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(SshService $sshService): void
    {
        if (!$sshService->connect($this->vps)) {
            Log::error("SyncVpsStatsJob: Could not connect to VPS #{$this->vps->id} ({$this->vps->name})");
            return;
        }

        // --- Get CPU Load ---
        $cpuLoadOutput = $sshService->execute("uptime | grep -o 'load average: .*' | cut -d' ' -f3 | sed 's/,//'");
        $cpuLoad = (float) trim($cpuLoadOutput);

        // --- Get RAM Usage ---
        $ramOutput = $sshService->execute("free -m | grep Mem | awk '{print $2, $3}'");
        list($ramTotal, $ramUsed) = sscanf($ramOutput, "%d %d");

        // --- Get Disk Usage ---
        $diskOutput = $sshService->execute("df -BG / | tail -n 1 | awk '{print $2, $3}'");
        list($diskTotal, $diskUsed) = sscanf($diskOutput, "%dG %dG");

        $sshService->disconnect();

        // Save the stats
        $this->vps->stats()->create([
            'cpu_load' => $cpuLoad,
            'ram_total_mb' => $ramTotal,
            'ram_used_mb' => $ramUsed,
            'disk_total_gb' => $diskTotal,
            'disk_used_gb' => $diskUsed,
        ]);
        
        Log::info("Successfully synced stats for VPS #{$this->vps->id}");
    }
}
