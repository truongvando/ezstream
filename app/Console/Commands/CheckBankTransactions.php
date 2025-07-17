<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\CheckBankTransactionsJob;
use Illuminate\Support\Facades\Log;

class CheckBankTransactions extends Command
{
    protected $signature = 'bank:check-transactions';
    protected $description = 'Check bank transactions every 15 seconds for 1.5 minutes';

    public function handle()
    {
        $this->info('🏦 Starting bank transaction checks (15s intervals)...');
        
        // Run 6 times với 15s interval = 1.5 phút
        for ($i = 0; $i < 6; $i++) {
            try {
                $this->line("Check #" . ($i + 1) . "/6 at " . now()->format('H:i:s'));
                
                // Dispatch job for actual bank checking
                CheckBankTransactionsJob::dispatch();
                
                // Wait 10 seconds before next check (except last iteration)
                if ($i < 5) {
                    sleep(15);
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
