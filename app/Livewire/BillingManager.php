<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\ServicePackage;
use App\Models\Subscription;
use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;

class BillingManager extends Component
{
    public $packages;
    public $currentSubscription;

    public function mount()
    {
        $user = Auth::user();
        $this->packages = ServicePackage::where('is_active', true)->orderBy('price_monthly')->get();
        $this->currentSubscription = $user->subscriptions()->where('status', 'ACTIVE')->first();
    }

    public function render()
    {
        $user = Auth::user();
        $transactions = $user->transactions()->latest()->paginate(10);
        
        return view('livewire.billing-manager', [
            'transactions' => $transactions
        ])->layout('layouts.sidebar')
          ->slot('header', '<h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Lịch sử giao dịch</h1>');
    }
}
