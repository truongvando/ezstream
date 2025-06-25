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
        $pendingTransactions = Transaction::where('status', 'PENDING')
            ->pluck('payment_code', 'id');

        if ($pendingTransactions->isEmpty()) {
            Log::info('No pending transactions to check.');
            return;
        }

        try {
            // API details from settings
            $apiUrl = setting('payment_api_endpoint');

            if (!$apiUrl) {
                Log::error('Payment API endpoint is not configured.');
                return;
            }

            $response = Http::get($apiUrl);

            if (!$response->successful() || $response->json('status') !== 'success') {
                Log::error('Failed to fetch bank transactions from API.', ['response' => $response->body()]);
                return;
            }

            $bankTransactions = $response->json('transactions');

            foreach ($bankTransactions as $bankTx) {
                // Check if the description contains any of our pending payment codes
                foreach ($pendingTransactions as $id => $code) {
                    if (str_contains($bankTx['description'], $code)) {
                        $transaction = Transaction::find($id);

                        // Double check amount and status
                        if ($transaction && $transaction->status === 'PENDING' && (int)$bankTx['amount'] >= $transaction->amount) {
                            $this->completeTransaction($transaction, $bankTx);
                            $pendingTransactions->forget($id); // Remove from list to avoid re-checking
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Exception while checking bank transactions: ' . $e->getMessage());
        }
    }

    protected function completeTransaction(Transaction $transaction, array $bankTxData): void
    {
        $transaction->update([
            'status' => 'COMPLETED',
            'gateway_transaction_id' => $bankTxData['transactionID'],
        ]);

        $transaction->subscription->update([
            'status' => 'ACTIVE',
        ]);
        
        // Optional: Notify user of successful payment via Telegram
        // $telegramService = new \App\Services\TelegramNotificationService();
        // $user = $transaction->user;
        // if ($user->telegram_bot_token && $user->telegram_chat_id) {
        //     $message = "✅ Your payment for package '{$transaction->subscription->servicePackage->name}' has been confirmed!";
        //     $telegramService->sendMessage($user->telegram_bot_token, $user->telegram_chat_id, $message);
        // }

        Log::info("Transaction #{$transaction->id} completed successfully.");
    }
}
