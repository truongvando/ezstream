<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Transaction;
use App\Services\BonusService;
use Illuminate\Support\Facades\Log;

class ProcessPendingDeposits extends Command
{
    protected $signature = 'deposits:process-pending';
    protected $description = 'Process pending deposit transactions and apply bonuses';

    public function handle()
    {
        $this->info('🔍 Processing pending deposits...');

        $pendingDeposits = Transaction::where('status', 'PENDING')
                                     ->whereIn('payment_gateway', ['VIETQR_VCB', 'BANK_TRANSFER'])
                                     ->where('created_at', '>', now()->subDays(7)) // Only check recent
                                     ->get();

        if ($pendingDeposits->isEmpty()) {
            $this->info('No pending deposits to process.');
            return;
        }

        $bonusService = app(BonusService::class);
        $processed = 0;

        foreach ($pendingDeposits as $transaction) {
            try {
                $this->line("Checking transaction #{$transaction->id} - {$transaction->payment_code}");

                // Here you would implement bank API check
                // For now, we'll simulate manual approval

                // In real implementation, you would:
                // 1. Call bank API to check if payment received
                // 2. Match amount and payment code
                // 3. Update transaction status

                // Simulate: Mark as completed if older than 5 minutes (for demo)
                if ($transaction->created_at->diffInMinutes() > 5) {
                    $transaction->update(['status' => 'COMPLETED']);

                    // Apply bonus
                    $bonus = $bonusService->calculateAndApplyBonus($transaction->user, $transaction);

                    if ($bonus) {
                        $this->info("  → Completed with bonus: $" . number_format($bonus->bonus_amount, 2));
                    } else {
                        $this->info("  → Completed (no bonus)");
                    }

                    $processed++;

                    Log::info('Deposit processed', [
                        'transaction_id' => $transaction->id,
                        'user_id' => $transaction->user_id,
                        'amount' => $transaction->amount,
                        'bonus_applied' => $bonus ? $bonus->bonus_amount : 0
                    ]);
                } else {
                    $this->line("  → Still pending (too recent)");
                }

            } catch (\Exception $e) {
                $this->error("  → Error: " . $e->getMessage());
                Log::error('Deposit processing failed', [
                    'transaction_id' => $transaction->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->info("✅ Processed {$processed} deposits out of {$pendingDeposits->count()}");
    }
}
