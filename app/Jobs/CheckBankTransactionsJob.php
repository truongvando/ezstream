<?php

namespace App\Jobs;

use App\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CheckBankTransactionsJob implements ShouldQueue
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
        // 1. Huỷ các giao dịch chờ quá 15 phút
        $expiredTransactions = Transaction::where('status', 'PENDING')
            ->where('created_at', '<', now()->subMinutes(15))
            ->get();
        foreach ($expiredTransactions as $transaction) {
            $transaction->update(['status' => 'CANCELLED']);
            Log::info('Auto-cancelled pending transaction quá hạn 15 phút', [
                'transaction_id' => $transaction->id,
                'created_at' => $transaction->created_at,
            ]);
        }

        // ✅ EARLY EXIT - Không call API nếu không có pending
        $pendingTransactions = Transaction::where('status', 'PENDING')
            ->pluck('payment_code', 'id');

        if ($pendingTransactions->isEmpty()) {
            Log::info('No pending transactions to check - skipping API call');
            return;
        }

        Log::info("Found {$pendingTransactions->count()} pending transactions, proceeding with bank API check");

        try {
            // API details from settings
            $apiUrl = setting('payment_api_endpoint');

            if (!$apiUrl) {
                Log::error('Payment API endpoint is not configured.');
                return;
            }

            Log::info("Checking bank transactions", [
                'api_url' => $apiUrl,
                'pending_transactions_count' => $pendingTransactions->count(),
                'pending_codes' => $pendingTransactions->values()->toArray()
            ]);

            $response = Http::get($apiUrl);

            if (!$response->successful() || $response->json('status') !== 'success') {
                Log::error('Failed to fetch bank transactions from API.', ['response' => $response->body()]);
                return;
            }

            $bankTransactions = $response->json('transactions');

            $processedCount = 0;
            $matchedCount = 0;

            foreach ($bankTransactions as $bankTx) {
                $processedCount++;
                
                // ✅ SIMPLE EXACT MATCHING - Vì payment_code format đơn giản: HD000003
                $description = strtoupper($bankTx['description']); // Normalize case
                
                Log::info("Processing bank transaction", [
                    'transactionID' => $bankTx['transactionID'],
                    'amount' => $bankTx['amount'],
                    'description' => $description
                ]);
                
                // Check if the description contains any of our pending payment codes
                foreach ($pendingTransactions as $id => $code) {
                    $normalizedCode = strtoupper($code);
                    
                    // ✅ SIMPLE STRING MATCHING - Vì HD000003 format đơn giản không trùng với gì khác
                    if (str_contains($description, $normalizedCode)) {
                        Log::info("Payment code matched", [
                            'transaction_id' => $id,
                            'code' => $normalizedCode,
                            'description' => $description,
                            'bank_amount' => $bankTx['amount']
                        ]);
                        
                        // Get the transaction to verify amount
                        $transaction = Transaction::find($id);
                        if (!$transaction) {
                            Log::warning("Transaction not found", ['id' => $id]);
                            continue;
                        }
                        
                        // Verify amount matches (allow small differences due to rounding)
                        $expectedAmount = (float) $transaction->amount;
                        $receivedAmount = (float) $bankTx['amount'];
                        $amountDifference = abs($expectedAmount - $receivedAmount);
                        
                        if ($amountDifference > 1) { // Allow 1 VND difference
                            Log::warning("Amount mismatch", [
                                'transaction_id' => $id,
                                'expected' => $expectedAmount,
                                'received' => $receivedAmount,
                                'difference' => $amountDifference
                            ]);
                            continue;
                        }
                        
                        // Mark transaction as completed
                        $transaction->update([
                            'status' => 'COMPLETED',
                            'gateway_transaction_id' => $bankTx['transactionID'],
                        ]);
                        
                        // Activate the subscription
                        $subscription = $transaction->subscription;
                        if ($subscription) {
                            $user = $subscription->user;
                            
                            try {
                                // ✅ UPGRADE LOGIC - Hủy tất cả các gói ACTIVE khác của user này
                                $user->subscriptions()
                                    ->where('status', 'ACTIVE')
                                    ->where('id', '!=', $subscription->id) // Không hủy chính gói vừa mua
                                    ->update(['status' => 'CANCELED']);

                                Log::info("Canceled old active subscriptions for user", ['user_id' => $user->id]);

                                // Activate new subscription
                                $subscription->update([
                                    'status' => 'ACTIVE',
                                    'starts_at' => now(),
                                    'ends_at' => now()->addMonth(), // ✅ FIX: Default 1 month duration
                                    'payment_transaction_id' => $transaction->id, // ✅ FIX: Set payment transaction ID
                                ]);
                                
                                // ✅ Verify update was successful
                                $subscription->refresh();
                                if ($subscription->payment_transaction_id !== $transaction->id) {
                                    Log::error("Failed to set payment_transaction_id", [
                                        'subscription_id' => $subscription->id,
                                        'transaction_id' => $transaction->id,
                                        'actual_payment_transaction_id' => $subscription->payment_transaction_id
                                    ]);
                                }
                                
                                Log::info("Subscription activated", [
                                    'subscription_id' => $subscription->id,
                                    'user_id' => $user->id,
                                    'package' => $subscription->servicePackage->name ?? 'Unknown',
                                    'payment_transaction_id' => $subscription->payment_transaction_id
                                ]);
                                
                                // Send notification to user
                                $this->sendPaymentSuccessNotification($transaction, $subscription);
                                
                            } catch (\Exception $e) {
                                Log::error("Error activating subscription", [
                                    'subscription_id' => $subscription->id,
                                    'transaction_id' => $transaction->id,
                                    'error' => $e->getMessage(),
                                    'trace' => $e->getTraceAsString()
                                ]);
                            }
                        }
                        
                        $matchedCount++;
                        
                        // Remove from pending list to avoid duplicate processing
                        $pendingTransactions->forget($id);
                        
                        Log::info("Transaction completed successfully", [
                            'transaction_id' => $id,
                            'payment_code' => $normalizedCode,
                            'amount' => $receivedAmount
                        ]);
                        
                        break; // Found match, move to next bank transaction
                    }
                }
            }

            Log::info("Bank transaction check completed", [
                'processed_count' => $processedCount,
                'matched_count' => $matchedCount,
                'remaining_pending' => $pendingTransactions->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Error checking bank transactions: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    protected function sendPaymentSuccessNotification(Transaction $transaction, \App\Models\Subscription $subscription): void
    {
        // Implement the logic to send a payment success notification to the user
        // This is a placeholder and should be replaced with the actual implementation
        Log::info("Payment success notification sent to user", [
            'transaction_id' => $transaction->id,
            'subscription_id' => $subscription->id,
            'user_id' => $transaction->user->id
        ]);
    }
}
