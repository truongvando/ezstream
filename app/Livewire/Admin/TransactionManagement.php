<?php

namespace App\Livewire\Admin;

use App\Models\Transaction;
use App\Models\User;
use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Subscription;

class TransactionManagement extends Component
{
    use WithPagination;

    public $showEditModal = false;
    public ?Transaction $editingTransaction = null;
    public $newStatus = '';
    
    // Filters
    public $filterUserId = '';
    public $filterStatus = '';

    public $search = '';
    public $statusFilter = '';
    public $sortField = 'created_at';
    public $sortDirection = 'desc';

    protected function rules()
    {
        return [
            'newStatus' => 'required|in:PENDING,COMPLETED,FAILED,CANCELED',
        ];
    }
    
    public function editTransaction(Transaction $transaction)
    {
        $this->editingTransaction = $transaction;
        $this->newStatus = $transaction->status;
        $this->showEditModal = true;
    }

    public function updateTransaction()
    {
        $this->validate();
        
        $originalStatus = $this->editingTransaction->status;
        $newStatus = $this->newStatus;

        if ($originalStatus !== $newStatus) {
            $subscription = $this->editingTransaction->subscription;
            
            if ($subscription) {
                // Logic when manually completing a transaction
                if ($newStatus === 'COMPLETED' && $originalStatus !== 'COMPLETED') {
                     // Deactivate other active subscriptions of the user
                    $subscription->user->subscriptions()
                        ->where('status', 'ACTIVE')
                        ->where('id', '!=', $subscription->id)
                        ->update(['status' => 'CANCELED']);

                    // Activate the new subscription
                    $subscription->update([
                        'status' => 'ACTIVE',
                        'starts_at' => $subscription->starts_at ?? now(),
                        'ends_at' => $subscription->ends_at ?? now()->addMonth(),
                    ]);
                }
                // Logic when manually failing/canceling a transaction that was active
                elseif (in_array($newStatus, ['FAILED', 'CANCELED']) && $subscription->status === 'ACTIVE') {
                    $subscription->update(['status' => 'INACTIVE']);
                }
            }
             $this->editingTransaction->update(['status' => $newStatus]);
        }
        
        $this->showEditModal = false;
        session()->flash('success', 'Trạng thái giao dịch đã được cập nhật.');
    }
    
    public function updatingFilterUserId() { $this->resetPage(); }
    public function updatingFilterStatus() { $this->resetPage(); }

    public function updatingSearch() { $this->resetPage(); }
    public function updatingStatusFilter() { $this->resetPage(); }

    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortDirection = 'asc';
        }
        $this->sortField = $field;
    }

    public function render()
    {
        $stats = [
            'total_transactions' => Transaction::count(),
            'completed_transactions' => Transaction::where('status', 'COMPLETED')->count(),
            'pending_transactions' => Transaction::where('status', 'PENDING')->count(),
            'failed_transactions' => Transaction::where('status', 'FAILED')->count(),
            'total_revenue' => Transaction::where('status', 'COMPLETED')->sum('amount'),
            'pending_amount' => Transaction::where('status', 'PENDING')->sum('amount'),
        ];

        $query = Transaction::with('user');

        if ($this->search) {
            $query->whereHas('user', function($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('email', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->statusFilter && $this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        $query->orderBy($this->sortField, $this->sortDirection);
        $transactions = $query->paginate(10);

        return view('livewire.admin.transaction-management', [
            'transactions' => $transactions,
            'stats' => $stats,
            'statuses' => ['PENDING', 'COMPLETED', 'FAILED', 'REFUNDED'],
        ])->layout('layouts.sidebar')
          ->slot('header', '<h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Quản lý Giao dịch</h1>');
    }

    /**
     * ✅ THÊM HELPER METHOD FORMAT TIỀN VNĐ
     */
    public function formatVND($amount)
    {
        return number_format($amount, 0, ',', '.') . ' VNĐ';
    }
}
