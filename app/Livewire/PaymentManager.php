<?php

namespace App\Livewire;

use App\Models\Subscription;
use Livewire\Component;

class PaymentManager extends Component
{
    public Subscription $subscription;
    public $transaction;
    public $qrCodeUrl;

    public function mount(Subscription $subscription)
    {
        // Authorize that the user owns this subscription
        if ($subscription->user_id !== auth()->id()) {
            abort(403);
        }

        $this->subscription = $subscription;
        $this->checkPaymentStatus();
    }

    public function checkPaymentStatus()
    {
        // Refresh subscription from database
        $this->subscription = $this->subscription->fresh();

        // Check if subscription is now active
        if ($this->subscription->status === 'ACTIVE') {
            session()->flash('success', '🎉 Thanh toán thành công! Gói dịch vụ đã được kích hoạt.');
            return redirect()->route('dashboard');
        }

        // Get latest pending transaction
        $this->transaction = $this->subscription->transactions()->where('status', 'PENDING')->latest()->first();

        if (!$this->transaction) {
            // Check if there's a completed transaction
            $completedTransaction = $this->subscription->transactions()->where('status', 'COMPLETED')->latest()->first();
            if ($completedTransaction) {
                session()->flash('success', '🎉 Thanh toán thành công! Gói dịch vụ đã được kích hoạt.');
                return redirect()->route('dashboard');
            }

            session()->flash('error', 'No pending payment found for this subscription.');
            return redirect()->route('dashboard');
        }

        $this->generateQrCodeUrl();
    }

    protected function generateQrCodeUrl()
    {
        // Use hardcoded values for now since database is not accessible
        $bankId = '970436'; // Vietcombank
        $accountNo = '0971000032314';
        $accountName = 'TRUONG VAN DO';

        // Generate payment code dynamically
        $paymentCode = 'EZS' . str_pad($this->transaction->subscription_id, 6, '0', STR_PAD_LEFT);

        $baseUrl = "https://img.vietqr.io/image/{$bankId}-{$accountNo}-compact2.png";

        $params = http_build_query([
            'amount' => $this->transaction->amount,
            'addInfo' => $paymentCode,
            'accountName' => $accountName,
        ]);

        $this->qrCodeUrl = $baseUrl . '?' . $params;
    }

    public function getPaymentCodeProperty()
    {
        return 'EZS' . str_pad($this->transaction->subscription_id, 6, '0', STR_PAD_LEFT);
    }

    public function render()
    {
        return view('livewire.payment-manager')
            ->layout('layouts.sidebar')
            ->slot('header', '<h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Thanh toán</h1>');
    }
}
