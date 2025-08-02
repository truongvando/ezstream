<?php

namespace App\Jobs;

use App\Models\Transaction;
use App\Models\ToolOrder;
use App\Models\ViewOrder;
use App\Services\LicenseService;
use App\Jobs\ProcessViewOrderJob;
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
            ->update(['status' => 'FAILED']);

        if ($expiredCount > 0) {
            Log::info("Auto-cancelled {$expiredCount} pending transactions quá hạn 15 phút");
        }

        // 2. Check API bank cho các giao dịch pending còn lại
        $pendingTransactions = Transaction::where('status', 'PENDING')
            ->get()
            ->mapWithKeys(function($transaction) {
                // Generate payment code based on transaction type
                if ($transaction->subscription_id) {
                    // Subscription payment: EZS + subscription_id
                    $paymentCode = 'EZS' . str_pad($transaction->subscription_id, 6, '0', STR_PAD_LEFT);
                } elseif ($transaction->tool_order_id) {
                    // Tool order payment: TOOL + tool_order_id
                    $paymentCode = 'TOOL' . str_pad($transaction->tool_order_id, 6, '0', STR_PAD_LEFT);
                } elseif ($transaction->view_order_id) {
                    // View order payment: VIEW + view_order_id
                    $paymentCode = 'VIEW' . str_pad($transaction->view_order_id, 6, '0', STR_PAD_LEFT);
                } else {
                    // Fallback to payment_code field
                    $paymentCode = $transaction->payment_code;
                }
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

                        // Process based on transaction type
                        if ($transaction->subscription_id) {
                            // Handle subscription payment
                            $this->processSubscriptionPayment($transaction);
                        } elseif ($transaction->tool_order_id) {
                            // Handle tool order payment
                            $this->processToolOrderPayment($transaction);
                        } elseif ($transaction->view_order_id) {
                            // Handle view order payment
                            $this->processViewOrderPayment($transaction);
                        } else {
                            // Handle deposit payment
                            $this->processDepositPayment($transaction);
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

    /**
     * Process subscription payment
     */
    private function processSubscriptionPayment(Transaction $transaction)
    {
        $subscription = $transaction->subscription;
        $subscription->load('servicePackage');

        if ($subscription) {
            // Deactivate old subscriptions before activating new one
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

            // Activate the new subscription
            $subscription->update([
                'status' => 'ACTIVE',
                'starts_at' => now(),
                'ends_at' => now()->addMonth(),
            ]);

            echo "✅ Subscription {$subscription->id} activated (package: {$subscription->servicePackage->name})\n";

            Log::info("Subscription payment processed", [
                'transaction_id' => $transaction->id,
                'subscription_id' => $subscription->id,
                'old_subscriptions_deactivated' => $oldSubscriptions->count()
            ]);
        }
    }

    /**
     * Process tool order payment
     */
    private function processToolOrderPayment(Transaction $transaction)
    {
        $toolOrder = $transaction->toolOrder;

        if ($toolOrder) {
            // Mark tool order as completed
            $toolOrder->update(['status' => 'COMPLETED']);

            // Create license for the tool
            $licenseService = new LicenseService();
            $license = $licenseService->createLicenseForOrder($toolOrder);

            echo "✅ Tool order {$toolOrder->id} completed - License created: {$license->license_key}\n";

            Log::info("Tool order payment processed", [
                'transaction_id' => $transaction->id,
                'tool_order_id' => $toolOrder->id,
                'tool_name' => $toolOrder->tool->name,
                'license_key' => $license->license_key
            ]);
        }
    }

    /**
     * Process view order payment
     */
    private function processViewOrderPayment(Transaction $transaction)
    {
        $viewOrder = $transaction->viewOrder;

        if ($viewOrder) {
            // Dispatch job to process view order via API
            ProcessViewOrderJob::dispatch($viewOrder);

            echo "✅ View order {$viewOrder->id} payment completed - Processing via API\n";

            Log::info("View order payment processed", [
                'transaction_id' => $transaction->id,
                'view_order_id' => $viewOrder->id,
                'service_name' => $viewOrder->apiService->name,
                'quantity' => $viewOrder->quantity
            ]);
        }
    }

    /**
     * Process deposit payment
     */
    private function processDepositPayment(Transaction $transaction)
    {
        $user = $transaction->user;

        if ($user) {
            // Add money to user balance
            $user->increment('balance', $transaction->amount);

            echo "✅ Deposit {$transaction->id} completed - Added ${$transaction->amount} to user {$user->name} (ID: {$user->id})\n";
            echo "💰 New balance: ${$user->fresh()->balance}\n";

            Log::info("Deposit payment processed", [
                'transaction_id' => $transaction->id,
                'user_id' => $user->id,
                'amount' => $transaction->amount,
                'new_balance' => $user->fresh()->balance
            ]);
        }
    }
}
