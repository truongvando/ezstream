<?php

namespace App\Console\Commands;

use App\Models\VpsServer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class CleanupFailedProvisionJobs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vps:cleanup-failed-provision {--reset-vps : Reset failed VPS status} {--clear-jobs : Clear failed jobs} {--vps-id= : Specific VPS ID to reset}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cleanup failed provision jobs and reset VPS status';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🧹 VPS Provision Cleanup Tool');
        $this->info('==============================');

        $resetVps = $this->option('reset-vps');
        $clearJobs = $this->option('clear-jobs');
        $vpsId = $this->option('vps-id');

        if (!$resetVps && !$clearJobs) {
            $this->warn('Please specify at least one action: --reset-vps or --clear-jobs');
            return Command::FAILURE;
        }

        if ($clearJobs) {
            $this->clearFailedJobs();
        }

        if ($resetVps) {
            $this->resetFailedVps($vpsId);
        }

        $this->info('✅ Cleanup completed successfully!');
        return Command::SUCCESS;
    }

    /**
     * Clear failed provision jobs from database
     */
    private function clearFailedJobs(): void
    {
        $this->info('🗑️ Clearing failed provision jobs...');

        // Get failed provision jobs
        $failedJobs = DB::table('failed_jobs')
            ->where('payload', 'like', '%ProvisionMultistreamVpsJob%')
            ->get();

        if ($failedJobs->isEmpty()) {
            $this->info('No failed provision jobs found.');
            return;
        }

        $this->info("Found {$failedJobs->count()} failed provision jobs:");

        // Display failed jobs
        $headers = ['ID', 'Queue', 'Failed At', 'Exception Preview'];
        $rows = $failedJobs->map(function ($job) {
            $exception = substr($job->exception, 0, 100) . '...';
            return [
                $job->id,
                $job->queue,
                $job->failed_at,
                $exception
            ];
        })->toArray();

        $this->table($headers, $rows);

        if ($this->confirm('Do you want to delete these failed jobs?')) {
            $deletedCount = DB::table('failed_jobs')
                ->where('payload', 'like', '%ProvisionMultistreamVpsJob%')
                ->delete();

            $this->info("✅ Deleted {$deletedCount} failed provision jobs.");
        }
    }

    /**
     * Reset failed VPS status
     */
    private function resetFailedVps(?string $vpsId = null): void
    {
        $this->info('🔄 Resetting failed VPS status...');

        $query = VpsServer::where('status', 'FAILED');
        
        if ($vpsId) {
            $query->where('id', $vpsId);
        }

        $failedVps = $query->get();

        if ($failedVps->isEmpty()) {
            $this->info('No failed VPS found.');
            return;
        }

        $this->info("Found {$failedVps->count()} failed VPS:");

        // Display failed VPS
        $headers = ['ID', 'Name', 'IP Address', 'Status', 'Error Message'];
        $rows = $failedVps->map(function ($vps) {
            return [
                $vps->id,
                $vps->name,
                $vps->ip_address,
                $vps->status,
                substr($vps->error_message ?? '', 0, 50) . '...'
            ];
        })->toArray();

        $this->table($headers, $rows);

        if ($this->confirm('Do you want to reset these VPS to PENDING status?')) {
            foreach ($failedVps as $vps) {
                $vps->update([
                    'status' => 'PENDING',
                    'status_message' => 'Reset from FAILED status - ready for re-provisioning',
                    'error_message' => null,
                ]);

                // Clear provision progress from Redis
                try {
                    $key = "vps_provision_progress:{$vps->id}";
                    Redis::del($key);
                } catch (\Exception $e) {
                    $this->warn("Failed to clear Redis progress for VPS #{$vps->id}: {$e->getMessage()}");
                }

                $this->info("✅ Reset VPS #{$vps->id} ({$vps->name}) to PENDING status");
            }

            $this->info("✅ Reset {$failedVps->count()} VPS successfully.");
        }
    }
}
