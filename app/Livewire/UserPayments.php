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
        ])->layout('layouts.app');
    }
} 