<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\CheckBankTransactionsJob;
use Illuminate\Support\Facades\Log;

class CheckBankTransactions extends Command
{
    protected $signature = 'bank:check-transactions';
    protected $description = 'Check bank transactions every 10 seconds for 1 minute';

    public function handle()
    {
        $this->info('🏦 Starting bank transaction checks (10s intervals)...');
        
        // Run 6 times with 10-second intervals = 1 minute total
        for ($i = 0; $i < 6; $i++) {
            try {
                $this->line("Check #" . ($i + 1) . "/6 at " . now()->format('H:i:s'));
                
                // Dispatch job for actual bank checking
                CheckBankTransactionsJob::dispatch();
                
                // Wait 10 seconds before next check (except last iteration)
                if ($i < 5) {
                    sleep(10);
                }
                
            } catch (\Exception $e) {
                Log::error("Bank check iteration {$i} failed: " . $e->getMessage());
                $this->error("Check #" . ($i + 1) . " failed: " . $e->getMessage());
            }
        }
        
        $this->info('✅ Bank transaction checks completed');
        return 0;
    }
}
