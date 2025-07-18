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
        // 1. Hủy các giao dịch chờ quá 15 phút
        $expiredCount = Transaction::where('status', 'PENDING')
            ->where('created_at', '<', now()->subMinutes(15))
            ->update(['status' => 'CANCELLED']);

        if ($expiredCount > 0) {
            Log::info("Auto-cancelled {$expiredCount} pending transactions quá hạn 15 phút");
        }

        // 2. Check API bank cho các giao dịch pending còn lại
        $pendingTransactions = Transaction::where('status', 'PENDING')
            ->whereNotNull('subscription_id')
            ->get()
            ->mapWithKeys(function($transaction) {
                // Generate payment code: EZS + subscription_id padded to 6 digits
                $paymentCode = 'EZS' . str_pad($transaction->subscription_id, 6, '0', STR_PAD_LEFT);
                return [$transaction->id => $paymentCode];
            });

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
                echo "❌ Payment API endpoint is not configured.\n";
                return;
            }

            Log::info("🌐 Calling bank API: {$apiUrl}");
            echo "🌐 Calling bank API: {$apiUrl}\n";

            $response = Http::get($apiUrl);

            if (!$response->successful()) {
                Log::error('Failed to fetch bank transactions from API.', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                echo "❌ API call failed with status: " . $response->status() . "\n";
                echo "Response: " . $response->body() . "\n";
                return;
            }

            $responseData = $response->json();
            if (!$responseData || ($responseData['status'] ?? null) !== 'success') {
                Log::error('Invalid API response format', ['response' => $response->body()]);
                echo "❌ Invalid API response format\n";
                echo "Response: " . $response->body() . "\n";
                return;
            }

            echo "✅ API call successful\n";

            $bankTransactions = $response->json('transactions');
            $matchedCount = 0;

            echo "📦 Found " . count($bankTransactions) . " bank transactions\n";
            echo "🔍 Looking for payment codes: " . implode(', ', $pendingTransactions->values()->toArray()) . "\n";

            foreach ($bankTransactions as $bankTx) {
                $description = strtoupper($bankTx['description']);
                echo "🏦 Bank transaction: {$description} - Amount: " . ($bankTx['amount'] ?? 'N/A') . "\n";

                // Check if the description contains any of our pending payment codes
                foreach ($pendingTransactions as $id => $code) {
                    $normalizedCode = strtoupper($code);

                    if (str_contains($description, $normalizedCode)) {
                        echo "✅ Found matching payment code: {$normalizedCode}\n";

                        // Get the transaction to verify amount
                        $transaction = Transaction::find($id);
                        if (!$transaction) {
                            echo "❌ Transaction {$id} not found\n";
                            continue;
                        }

                        // Verify amount matches
                        $expectedAmount = (float) $transaction->amount;
                        $receivedAmount = (float) ($bankTx['amount'] ?? 0);

                        echo "💰 Amount check: Expected {$expectedAmount}, Received {$receivedAmount}\n";

                        if (abs($expectedAmount - $receivedAmount) > 1) {
                            echo "❌ Amount mismatch (difference: " . abs($expectedAmount - $receivedAmount) . ")\n";
                            continue;
                        }

                        // Mark transaction as completed
                        $transaction->update([
                            'status' => 'COMPLETED',
                            'gateway_transaction_id' => $bankTx['transactionID'] ?? null,
                        ]);

                        echo "✅ Transaction {$id} marked as COMPLETED\n";

                        // Activate the subscription
                        $subscription = $transaction->subscription;
                        $subscription->load('servicePackage'); // Load relationship
                        if ($subscription) {
                            // 🔥 CRITICAL FIX: Deactivate old subscriptions before activating new one
                            $user = $subscription->user;
                            $oldSubscriptions = $user->subscriptions()
                                ->where('status', 'ACTIVE')
                                ->where('id', '!=', $subscription->id)
                                ->with('servicePackage')
                                ->get();

                            foreach ($oldSubscriptions as $oldSub) {
                                $oldSub->update(['status' => 'INACTIVE']);
                                echo "🔄 Deactivated old subscription {$oldSub->id} (package: {$oldSub->servicePackage->name})\n";
                            }

                            // Now activate the new subscription
                            $subscription->update([
                                'status' => 'ACTIVE',
                                'starts_at' => now(),
                                'ends_at' => now()->addMonth(),
                            ]);

                            echo "✅ Subscription {$subscription->id} activated (package: {$subscription->servicePackage->name})\n";

                            Log::info("Transaction completed and subscription activated", [
                                'transaction_id' => $id,
                                'subscription_id' => $subscription->id,
                                'payment_code' => $normalizedCode,
                                'old_subscriptions_deactivated' => $oldSubscriptions->count()
                            ]);
                        }

                        $matchedCount++;
                        break;
                    }
                }
            }

            Log::info("Bank transaction check completed", [
                'cancelled_transactions' => $expiredCount,
                'matched_transactions' => $matchedCount,
                'remaining_pending' => $pendingTransactions->count() - $matchedCount
            ]);

        } catch (\Exception $e) {
            Log::error('Error checking bank transactions: ' . $e->getMessage());
        }
    }
}
