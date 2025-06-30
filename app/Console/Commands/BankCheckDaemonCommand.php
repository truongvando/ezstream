<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\CheckBankTransactionsJob;

class BankCheckDaemonCommand extends Command
{
    protected $signature = 'bank:check-daemon {--interval=10 : Check interval in seconds}';
    protected $description = 'Run bank check daemon every X seconds';

    public function handle()
    {
        $interval = (int) $this->option('interval');
        $idleInterval = $interval * 6; // 60 giây khi không có pending
        
        $this->info("🚀 Starting Bank Check Daemon");
        $this->info("Active interval: {$interval}s (when pending)");
        $this->info("Idle interval: {$idleInterval}s (when no pending)");
        $this->info("Press Ctrl+C to stop");
        
        while (true) {
            $this->info("⏰ " . now()->format('H:i:s') . " - Checking for pending transactions...");
            
            // ✅ SMART CHECK - Chỉ call API khi có pending transactions
            $pendingCount = \App\Models\Transaction::where('status', 'PENDING')->count();
            
            if ($pendingCount === 0) {
                $this->comment("💤 No pending transactions, sleeping for {$idleInterval}s");
                sleep($idleInterval);
                continue;
            }
            
            $this->info("🔍 Found {$pendingCount} pending transactions, checking bank API...");
            
            try {
                // Run job directly (không qua queue để nhanh hơn)
                $job = new CheckBankTransactionsJob();
                $job->handle();
                
                $this->info("✅ Check completed, sleeping for {$interval}s");
            } catch (\Exception $e) {
                $this->error("❌ Error: " . $e->getMessage());
            }
            
            sleep($interval);
        }
    }
} 