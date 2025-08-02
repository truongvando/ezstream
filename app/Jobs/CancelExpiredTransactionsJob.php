<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Transaction;
use App\Models\Subscription;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CancelExpiredTransactionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $expiredMinutes = config('payment.transaction_timeout', 30); // Default 30 minutes
        $cutoffTime = Carbon::now()->subMinutes($expiredMinutes);

        // Find expired pending transactions
        $expiredTransactions = Transaction::where('status', 'PENDING')
            ->where('created_at', '<', $cutoffTime)
            ->with(['subscription'])
            ->get();

        foreach ($expiredTransactions as $transaction) {
            try {
                // Update transaction status
                $transaction->update([
                    'status' => 'EXPIRED',
                    'description' => $transaction->description . ' (Hết hạn sau ' . $expiredMinutes . ' phút)'
                ]);

                // Cancel related subscription if exists
                if ($transaction->subscription) {
                    $subscription = $transaction->subscription;
                    
                    if ($subscription->status === 'PENDING_PAYMENT') {
                        $subscription->update([
                            'status' => 'CANCELLED'
                        ]);
                        
                        Log::info("Cancelled expired subscription", [
                            'subscription_id' => $subscription->id,
                            'transaction_id' => $transaction->id,
                            'user_id' => $transaction->user_id
                        ]);
                    }
                }

                // Log for deposits
                if (!$transaction->subscription_id && !$transaction->tool_order_id && !$transaction->view_order_id) {
                    Log::info("Cancelled expired deposit transaction", [
                        'transaction_id' => $transaction->id,
                        'payment_code' => $transaction->payment_code,
                        'amount' => $transaction->amount,
                        'user_id' => $transaction->user_id
                    ]);
                }

                echo "✅ Cancelled expired transaction {$transaction->id} (Payment Code: {$transaction->payment_code})\n";

            } catch (\Exception $e) {
                Log::error("Error cancelling expired transaction {$transaction->id}: " . $e->getMessage());
                echo "❌ Error cancelling transaction {$transaction->id}: " . $e->getMessage() . "\n";
            }
        }

        if ($expiredTransactions->count() > 0) {
            echo "🧹 Processed " . $expiredTransactions->count() . " expired transactions\n";
        } else {
            echo "✨ No expired transactions found\n";
        }
    }
}
