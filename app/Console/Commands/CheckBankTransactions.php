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
        $this->info('🏦 Starting bank transaction check...');

        // Run job directly for immediate feedback
        $job = new CheckBankTransactionsJob();

        try {
            // Show pending transactions before check
            $pendingCount = \App\Models\Transaction::where('status', 'PENDING')->count();
            $this->info("📊 Found {$pendingCount} pending transactions");

            if ($pendingCount > 0) {
                $pending = \App\Models\Transaction::where('status', 'PENDING')
                    ->with('subscription')
                    ->get();

                $this->table(['ID', 'Amount', 'Created', 'Payment Code'],
                    $pending->map(function($t) {
                        $paymentCode = $t->subscription_id ? 'EZS' . str_pad($t->subscription_id, 6, '0', STR_PAD_LEFT) : 'N/A';
                        return [
                            $t->id,
                            number_format($t->amount) . ' VND',
                            $t->created_at->format('H:i:s d/m'),
                            $paymentCode
                        ];
                    })->toArray()
                );
            }

            // Execute the job
            $this->info('🔄 Executing bank check job...');
            $job->handle();

            // Show results after check
            $newPendingCount = \App\Models\Transaction::where('status', 'PENDING')->count();
            $completedCount = \App\Models\Transaction::where('status', 'COMPLETED')->count();
            $cancelledCount = \App\Models\Transaction::where('status', 'CANCELLED')->count();

            $this->info("📈 Results:");
            $this->line("  - Pending: {$newPendingCount}");
            $this->line("  - Completed: {$completedCount}");
            $this->line("  - Cancelled: {$cancelledCount}");

            if ($pendingCount > $newPendingCount) {
                $processed = $pendingCount - $newPendingCount;
                $this->info("✅ Processed {$processed} transactions");
            } else {
                $this->comment("ℹ️  No transactions were processed");
            }

        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            $this->line("Stack trace: " . $e->getTraceAsString());
            return 1;
        }

        $this->info('✅ Bank transaction check completed');
        return 0;
    }
}
