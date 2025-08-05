<?php

namespace App\Console\Commands;

use App\Jobs\ProvisionMultistreamVpsJob;
use App\Models\VpsServer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RetryProvisionVps extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vps:retry-provision {vps_id? : VPS ID to retry provision}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Retry provision for failed VPS servers';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $vpsId = $this->argument('vps_id');

        if ($vpsId) {
            // Retry specific VPS
            return $this->retrySpecificVps($vpsId);
        } else {
            // Show failed VPS and let user choose
            return $this->showFailedVpsAndChoose();
        }
    }

    private function retrySpecificVps(int $vpsId): int
    {
        $vps = VpsServer::find($vpsId);

        if (!$vps) {
            $this->error("❌ VPS #{$vpsId} not found");
            return 1;
        }

        $this->info("🔍 VPS #{$vps->id}: {$vps->name} ({$vps->ip_address})");
        $this->info("📊 Current status: {$vps->status}");
        $this->info("💬 Status message: {$vps->status_message}");

        if ($vps->status !== 'FAILED') {
            $this->warn("⚠️ VPS is not in FAILED status. Current status: {$vps->status}");
            
            if (!$this->confirm('Do you want to retry provision anyway?')) {
                return 0;
            }
        }

        if ($this->confirm('Do you want to retry provision for this VPS?')) {
            return $this->dispatchProvisionJob($vps);
        }

        return 0;
    }

    private function showFailedVpsAndChoose(): int
    {
        $failedVps = VpsServer::where('status', 'FAILED')->get();

        if ($failedVps->isEmpty()) {
            $this->info("✅ No failed VPS servers found");
            return 0;
        }

        $this->info("📋 Found " . $failedVps->count() . " failed VPS servers:");
        $this->newLine();

        $headers = ['ID', 'Name', 'IP Address', 'Status Message', 'Failed At'];
        $rows = [];

        foreach ($failedVps as $vps) {
            $rows[] = [
                $vps->id,
                $vps->name,
                $vps->ip_address,
                \Illuminate\Support\Str::limit($vps->status_message, 50),
                $vps->updated_at->format('Y-m-d H:i:s')
            ];
        }

        $this->table($headers, $rows);
        $this->newLine();

        // Let user choose
        $choice = $this->choice(
            'What would you like to do?',
            [
                'retry_all' => 'Retry all failed VPS',
                'retry_specific' => 'Retry specific VPS',
                'cancel' => 'Cancel'
            ],
            'cancel'
        );

        switch ($choice) {
            case 'retry_all':
                return $this->retryAllFailedVps($failedVps);
            
            case 'retry_specific':
                return $this->chooseSpecificVps($failedVps);
            
            default:
                $this->info("Operation cancelled");
                return 0;
        }
    }

    private function retryAllFailedVps($failedVps): int
    {
        $this->info("🚀 Retrying provision for " . $failedVps->count() . " VPS servers...");

        $successCount = 0;
        $failCount = 0;

        foreach ($failedVps as $vps) {
            $this->info("📤 Dispatching provision job for VPS #{$vps->id}: {$vps->name}");
            
            if ($this->dispatchProvisionJob($vps, false)) {
                $successCount++;
            } else {
                $failCount++;
            }
        }

        $this->newLine();
        $this->info("✅ Successfully dispatched: {$successCount}");
        if ($failCount > 0) {
            $this->warn("❌ Failed to dispatch: {$failCount}");
        }

        return 0;
    }

    private function chooseSpecificVps($failedVps): int
    {
        $choices = [];
        foreach ($failedVps as $vps) {
            $choices[$vps->id] = "#{$vps->id} - {$vps->name} ({$vps->ip_address})";
        }

        $vpsId = $this->choice('Choose VPS to retry:', $choices);
        $vps = $failedVps->where('id', $vpsId)->first();

        if (!$vps) {
            $this->error("❌ VPS not found");
            return 1;
        }

        return $this->dispatchProvisionJob($vps);
    }

    private function dispatchProvisionJob(VpsServer $vps, bool $showDetails = true): bool
    {
        try {
            if ($showDetails) {
                $this->info("🔄 Retrying provision for VPS #{$vps->id}: {$vps->name}");
                $this->info("📍 IP: {$vps->ip_address}");
                $this->info("💬 Previous error: {$vps->status_message}");
                $this->newLine();
            }

            // Reset VPS status
            $vps->update([
                'status' => 'PENDING',
                'status_message' => 'Retrying provision...',
                'error_message' => null
            ]);

            // Dispatch provision job
            ProvisionMultistreamVpsJob::dispatch($vps->id);

            if ($showDetails) {
                $this->info("✅ Provision job dispatched successfully");
                $this->info("📊 You can monitor progress in the VPS management interface");
            }

            Log::info("🔄 [RetryProvision] Job dispatched for VPS #{$vps->id}", [
                'vps_name' => $vps->name,
                'ip_address' => $vps->ip_address
            ]);

            return true;

        } catch (\Exception $e) {
            if ($showDetails) {
                $this->error("❌ Failed to dispatch provision job: " . $e->getMessage());
            }

            Log::error("❌ [RetryProvision] Failed to dispatch job for VPS #{$vps->id}", [
                'error' => $e->getMessage(),
                'vps_name' => $vps->name
            ]);

            return false;
        }
    }
}
