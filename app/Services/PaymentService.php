<?php

namespace App\Services;

use App\Models\User;
use App\Models\Transaction;
use App\Models\Subscription;
use App\Models\ServicePackage;
use App\Services\ExchangeRateService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentService
{
    /**
     * Process payment for subscription using balance or bank transfer
     */
    public function processSubscriptionPayment(User $user, ServicePackage $package, string $paymentMethod = 'balance')
    {
        return DB::transaction(function () use ($user, $package, $paymentMethod) {
            
            // Check if user has sufficient balance for balance payment
            if ($paymentMethod === 'balance') {
                if ($user->balance < $package->price) {
                    throw new \Exception("Insufficient balance. Required: ${$package->price}, Available: ${$user->balance}");
                }
            }

            // Deactivate old subscriptions
            $user->subscriptions()
                ->where('status', 'ACTIVE')
                ->update(['status' => 'INACTIVE']);

            // Create new subscription
            $subscription = Subscription::create([
                'user_id' => $user->id,
                'service_package_id' => $package->id,
                'status' => $paymentMethod === 'balance' ? 'ACTIVE' : 'PENDING',
                'starts_at' => $paymentMethod === 'balance' ? now() : null,
                'ends_at' => $paymentMethod === 'balance' ? now()->addMonth() : null,
            ]);

            // Create transaction
            $transaction = Transaction::create([
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'payment_code' => $this->generatePaymentCode('EZS', $subscription->id),
                'amount' => $package->price,
                'currency' => 'USD',
                'payment_gateway' => $paymentMethod === 'balance' ? 'balance' : 'bank_transfer',
                'status' => $paymentMethod === 'balance' ? 'COMPLETED' : 'PENDING',
                'description' => $paymentMethod === 'balance' 
                    ? "Thanh toán gói {$package->name} bằng số dư"
                    : "Thanh toán gói {$package->name} qua chuyển khoản",
            ]);

            // If paying with balance, deduct immediately
            if ($paymentMethod === 'balance') {
                $user->decrement('balance', $package->price);
                
                Log::info("Subscription paid with balance", [
                    'user_id' => $user->id,
                    'package' => $package->name,
                    'amount' => $package->price,
                    'new_balance' => $user->fresh()->balance
                ]);
            }

            return [
                'subscription' => $subscription,
                'transaction' => $transaction,
                'payment_method' => $paymentMethod
            ];
        });
    }

    /**
     * Check if user can pay with balance
     */
    public function canPayWithBalance(User $user, float $amount): bool
    {
        return $user->balance >= $amount;
    }

    /**
     * Get payment options for user
     */
    public function getPaymentOptions(User $user, float $amount): array
    {
        $options = [];

        // Balance payment option
        if ($this->canPayWithBalance($user, $amount)) {
            $options[] = [
                'method' => 'balance',
                'name' => 'Thanh toán bằng số dư',
                'description' => "Số dư hiện tại: $" . number_format($user->balance, 2),
                'icon' => '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>',
                'available' => true
            ];
        } else {
            $options[] = [
                'method' => 'balance',
                'name' => 'Thanh toán bằng số dư',
                'description' => "Không đủ số dư (cần: $" . number_format($amount, 2) . ")",
                'icon' => '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>',
                'available' => false
            ];
        }

        // Bank transfer option
        $options[] = [
            'method' => 'bank_transfer',
            'name' => 'Chuyển khoản ngân hàng',
            'description' => 'Thanh toán qua VietQR',
            'icon' => '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>',
            'available' => true
        ];

        return $options;
    }

    /**
     * Generate payment code
     */
    private function generatePaymentCode(string $prefix, int $id): string
    {
        return $prefix . str_pad($id, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Generate VietQR URL
     */
    public function generateVietQR(float $amount, string $paymentCode): string
    {
        $bankConfig = config('payment.bank');
        $bankId = $bankConfig['id'];
        $accountNo = $bankConfig['account_number'];
        $accountName = $bankConfig['account_name'];

        $baseUrl = "https://img.vietqr.io/image/{$bankId}-{$accountNo}-compact2.png";

        // Convert USD to VND using current exchange rate
        $exchangeService = new ExchangeRateService();
        $vndAmount = $exchangeService->convertUsdToVnd($amount);

        $params = http_build_query([
            'amount' => round($vndAmount),
            'addInfo' => $paymentCode,
            'accountName' => $accountName,
        ]);

        return $baseUrl . '?' . $params;
    }

    /**
     * Get bank info for manual transfer
     */
    public function getBankInfo(): array
    {
        $bankConfig = config('payment.bank');
        return [
            'bank_name' => 'Vietcombank',
            'account_number' => $bankConfig['account_number'],
            'account_name' => $bankConfig['account_name'],
        ];
    }

    /**
     * Get current exchange rate info
     */
    public function getExchangeRateInfo(): array
    {
        $exchangeService = new ExchangeRateService();
        return $exchangeService->getRateInfo();
    }
}
