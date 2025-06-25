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
        $this->transaction = $subscription->transactions()->where('status', 'PENDING')->latest()->first();

        if (!$this->transaction) {
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

        $baseUrl = "https://img.vietqr.io/image/{$bankId}-{$accountNo}-compact2.png";
        
        $params = http_build_query([
            'amount' => $this->transaction->amount,
            'addInfo' => $this->transaction->payment_code,
            'accountName' => $accountName,
        ]);
        
        $this->qrCodeUrl = $baseUrl . '?' . $params;
    }

    public function render()
    {
        return view('livewire.payment-manager')
            ->layout('layouts.app');
    }
}
