<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use App\Models\User;
use App\Models\Transaction;
use App\Models\DepositBonus;
use App\Services\BonusService;
use Illuminate\Support\Facades\Log;

#[Layout('layouts.sidebar')]
class BalanceManager extends Component
{
    use WithPagination;

    public $search = '';
    public $selectedUserId = '';
    public $adjustmentAmount = '';
    public $adjustmentReason = '';
    public $adjustmentType = 'add'; // add or subtract

    public $showAdjustmentModal = false;

    protected $rules = [
        'selectedUserId' => 'required|exists:users,id',
        'adjustmentAmount' => 'required|numeric|min:0.01|max:10000',
        'adjustmentReason' => 'required|string|max:255',
        'adjustmentType' => 'required|in:add,subtract'
    ];

    public function openAdjustmentModal($userId)
    {
        $this->selectedUserId = $userId;
        $this->adjustmentAmount = '';
        $this->adjustmentReason = '';
        $this->adjustmentType = 'add';
        $this->showAdjustmentModal = true;
    }

    public function closeAdjustmentModal()
    {
        $this->showAdjustmentModal = false;
        $this->reset(['selectedUserId', 'adjustmentAmount', 'adjustmentReason', 'adjustmentType']);
    }

    public function adjustBalance()
    {
        $this->validate();

        try {
            $user = User::findOrFail($this->selectedUserId);
            $amount = (float) $this->adjustmentAmount;

            if ($this->adjustmentType === 'subtract') {
                if ($user->balance < $amount) {
                    session()->flash('error', 'Số dư không đủ để trừ');
                    return;
                }
                $amount = -$amount;
            }

            // Update user balance
            $oldBalance = $user->balance;
            $user->increment('balance', $amount);
            $newBalance = $user->fresh()->balance;

            // Create transaction record
            Transaction::create([
                'user_id' => $user->id,
                'amount' => abs($amount),
                'currency' => 'USD',
                'payment_gateway' => 'ADMIN_ADJUSTMENT',
                'status' => 'COMPLETED',
                'description' => "Admin adjustment: {$this->adjustmentReason}",
                'payment_code' => 'ADJ' . time(),
                'api_response' => [
                    'admin_id' => auth()->id(),
                    'old_balance' => $oldBalance,
                    'new_balance' => $newBalance,
                    'adjustment_type' => $this->adjustmentType,
                    'reason' => $this->adjustmentReason,
                    'adjusted_at' => now()->toISOString()
                ]
            ]);

            Log::info('Admin balance adjustment', [
                'admin_id' => auth()->id(),
                'user_id' => $user->id,
                'amount' => $amount,
                'old_balance' => $oldBalance,
                'new_balance' => $newBalance,
                'reason' => $this->adjustmentReason
            ]);

            $actionText = $this->adjustmentType === 'add' ? 'cộng' : 'trừ';
            session()->flash('success', "✅ Đã {$actionText} $" . number_format(abs($amount), 2) . " cho user {$user->name}");
            $this->closeAdjustmentModal();

        } catch (\Exception $e) {
            Log::error('Balance adjustment failed', [
                'error' => $e->getMessage(),
                'admin_id' => auth()->id(),
                'user_id' => $this->selectedUserId
            ]);
            session()->flash('error', 'Có lỗi xảy ra khi điều chỉnh số dư');
        }
    }

    public function render()
    {
        $users = User::when($this->search, function($query) {
                        $query->where('name', 'like', '%' . $this->search . '%')
                              ->orWhere('email', 'like', '%' . $this->search . '%');
                    })
                    ->orderBy('total_deposits', 'desc')
                    ->paginate(20);

        $bonusService = app(BonusService::class);
        $bonusStats = $bonusService->getBonusStats();

        return view('livewire.admin.balance-manager', [
            'users' => $users,
            'bonusStats' => $bonusStats
        ]);
    }
}
