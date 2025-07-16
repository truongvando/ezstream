<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class TransactionHistory extends Component
{
    use WithPagination;

    public function cancelTransaction($transactionId)
    {
        try {
            $transaction = Auth::user()->transactions()->findOrFail($transactionId);

            if ($transaction->status !== 'PENDING') {
                session()->flash('error', 'Chỉ có thể hủy các giao dịch đang chờ xử lý.');
                return;
            }

            // Delete associated subscription first if it exists
            if ($transaction->subscription) {
                $transaction->subscription->delete();
            }

            // Then delete the transaction
            $transaction->delete();

            session()->flash('success', 'Đã hủy giao dịch thành công.');
            // No need to refresh component, Livewire will re-render
        } catch (\Exception $e) {
            \Log::error("Error canceling transaction {$transactionId}: " . $e->getMessage());
            session()->flash('error', 'Đã có lỗi xảy ra khi hủy giao dịch.');
        }
    }

    public function render()
    {
        $transactions = Auth::user()->transactions()
            ->with('subscription.servicePackage')
            ->latest()
            ->paginate(15);

        return view('livewire.transaction-history', [
            'transactions' => $transactions
        ])->layout('layouts.sidebar')
          ->slot('header', '<h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Lịch Sử Giao Dịch</h1>');
    }
} 