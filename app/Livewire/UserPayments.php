<?php

namespace App\Livewire;

use App\Models\Transaction;
use Livewire\Component;
use Livewire\WithPagination;

class UserPayments extends Component
{
    use WithPagination;

    public function render()
    {
        $transactions = auth()->user()->transactions()
            ->with(['subscription.servicePackage'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('livewire.user-payments', [
            'transactions' => $transactions
        ])->layout('layouts.sidebar')
          ->slot('header', '<h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Thanh toán</h1>');
    }
} 