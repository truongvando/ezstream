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
        try {
            // Use the centralized methods from SshService for consistency
            $cpuUsage = $sshService->getCpuUsage($this->vps);
            $ramUsagePercent = $sshService->getRamUsage($this->vps);
            $diskUsagePercent = $sshService->getDiskUsage($this->vps);

            // Save the stats
            $this->vps->stats()->create([
                'cpu_usage_percent' => $cpuUsage,
                'ram_usage_percent' => $ramUsagePercent,
                'disk_usage_percent' => $diskUsagePercent,
            ]);
            
            Log::info("Successfully synced stats for VPS #{$this->vps->id}");

        } catch (\Exception $e) {
            Log::error("SyncVpsStatsJob: Failed to sync stats for VPS #{$this->vps->id} ({$this->vps->name}). Error: " . $e->getMessage());
        }
    }
}
