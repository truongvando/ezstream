<?php

namespace App\Livewire;

use App\Models\Subscription;
use Livewire\Component;
use Livewire\WithPagination;

class UserSubscriptions extends Component
{
    use WithPagination;

    public $showDetailsModal = false;
    public $selectedSubscription = null;

    public function showDetails($subscriptionId)
    {
        $this->selectedSubscription = auth()->user()->subscriptions()
            ->with(['servicePackage', 'transactions'])
            ->findOrFail($subscriptionId);
        $this->showDetailsModal = true;
    }

    public function closeDetailsModal()
    {
        $this->showDetailsModal = false;
        $this->selectedSubscription = null;
    }

    public function cancelSubscription($subscriptionId)
    {
        try {
            $subscription = auth()->user()->subscriptions()->findOrFail($subscriptionId);
            
            if ($subscription->status !== 'PENDING_PAYMENT') {
                session()->flash('error', 'Chỉ có thể hủy gói đang chờ thanh toán.');
                return;
            }

            // Cancel the subscription
            $subscription->update(['status' => 'CANCELED']);
            
            // Cancel related pending transactions
            $subscription->transactions()->where('status', 'PENDING')->update(['status' => 'CANCELLED']);
            
            session()->flash('success', 'Đã hủy gói dịch vụ thành công.');
            
        } catch (\Exception $e) {
            session()->flash('error', 'Có lỗi xảy ra: ' . $e->getMessage());
        }
    }

    public function render()
    {
        $subscriptions = auth()->user()->subscriptions()
            ->with(['servicePackage', 'transactions'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('livewire.user-subscriptions', [
            'subscriptions' => $subscriptions
        ])->layout('layouts.app');
    }
} 