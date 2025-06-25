<?php

namespace App\Livewire\Admin;

use App\Models\Transaction;
use App\Models\User;
use Livewire\Component;
use Livewire\WithPagination;

class TransactionManagement extends Component
{
    use WithPagination;

    public $showEditModal = false;
    public $editingTransaction;
    public $status;
    
    // Filters
    public $filterUserId = '';
    public $filterStatus = '';

    public $search = '';
    public $statusFilter = '';
    public $sortField = 'created_at';
    public $sortDirection = 'desc';

    protected $rules = [
        'status' => 'required|in:PENDING,COMPLETED,FAILED',
    ];

    public function edit(Transaction $transaction)
    {
        $this->editingTransaction = $transaction;
        $this->status = $transaction->status;
        $this->showEditModal = true;
    }

    public function update()
    {
        $this->validate();
        
        // Add logic to activate subscription if transaction is completed
        if ($this->status === 'COMPLETED' && $this->editingTransaction->status !== 'COMPLETED') {
            if ($this->editingTransaction->subscription) {
                $this->editingTransaction->subscription->update(['status' => 'ACTIVE']);
            }
        }
        
        $this->editingTransaction->update(['status' => $this->status]);
        
        $this->showEditModal = false;
        session()->flash('success', 'Transaction status updated.');
    }
    
    public function updatingFilterUserId() { $this->resetPage(); }
    public function updatingFilterStatus() { $this->resetPage(); }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }

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
            'statuses' => ['PENDING', 'COMPLETED', 'FAILED', 'REFUNDED'],
        ])->layout('layouts.admin');
    }
}
