<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\VpsServer;
use App\Jobs\UpdateAgentJob;
use Illuminate\Support\Facades\Log;

class BulkUpdateVpsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vps:bulk-update 
                            {--dry-run : Show what would be updated without actually doing it}
                            {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update agent on all active VPS servers';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔄 VPS Bulk Update Command');
        $this->info('==========================');

        // Get active VPS servers
        $activeVpsServers = VpsServer::where('is_active', true)
            ->where('status', '!=', 'PROVISIONING')
            ->get();

        if ($activeVpsServers->isEmpty()) {
            $this->warn('⚠️ No active VPS servers found to update.');
            return Command::SUCCESS;
        }

        $this->info("Found {$activeVpsServers->count()} active VPS servers:");
        
        // Display VPS list
        $headers = ['ID', 'Name', 'IP Address', 'Status'];
        $rows = $activeVpsServers->map(function ($vps) {
            return [
                $vps->id,
                $vps->name,
                $vps->ip_address,
                $vps->status
            ];
        })->toArray();

        $this->table($headers, $rows);

        // Dry run mode
        if ($this->option('dry-run')) {
            $this->info('🔍 DRY RUN MODE - No actual updates will be performed');
            $this->info('The following VPS servers would be updated:');
            
            foreach ($activeVpsServers as $vps) {
                $this->line("  - {$vps->name} (ID: {$vps->id})");
            }
            
            return Command::SUCCESS;
        }

        // Confirmation
        if (!$this->option('force')) {
            if (!$this->confirm('Do you want to proceed with updating all these VPS servers?')) {
                $this->info('Operation cancelled.');
                return Command::SUCCESS;
            }
        }

        // Perform bulk update
        $this->info('🚀 Starting bulk update...');
        
        $progressBar = $this->output->createProgressBar($activeVpsServers->count());
        $progressBar->start();

        $successCount = 0;
        $failedCount = 0;
        $errors = [];

        foreach ($activeVpsServers as $vps) {
            try {
                // Dispatch UpdateAgentJob
                UpdateAgentJob::dispatch($vps)->onQueue('vps-provisioning');
                
                $successCount++;
                Log::info("Bulk update: UpdateAgentJob dispatched for VPS {$vps->name} (ID: {$vps->id})");
                
            } catch (\Exception $e) {
                $failedCount++;
                $errors[] = "VPS {$vps->name} (ID: {$vps->id}): " . $e->getMessage();
                Log::error("Bulk update failed for VPS {$vps->name} (ID: {$vps->id}): " . $e->getMessage());
            }

            $progressBar->advance();
            
            // Small delay to avoid overwhelming the system
            usleep(500000); // 0.5 second
        }

        $progressBar->finish();
        $this->newLine(2);

        // Display results
        $this->info('📊 Bulk Update Results:');
        $this->info("✅ Successfully dispatched: {$successCount}");
        
        if ($failedCount > 0) {
            $this->error("❌ Failed: {$failedCount}");
            $this->error('Errors:');
            foreach ($errors as $error) {
                $this->error("  - {$error}");
            }
        }

        $this->info('🎉 Bulk update completed!');
        $this->info('💡 Monitor the queue with: php artisan queue:work --queue=vps-provisioning');

        return Command::SUCCESS;
    }
}
