<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use App\Models\Transaction;
use App\Models\User;
use App\Models\DepositBonus;
use App\Services\BonusService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

#[Layout('layouts.sidebar')]
class PaymentManager extends Component
{
    use WithPagination;

    public $search = '';
    public $statusFilter = '';
    public $gatewayFilter = '';
    public $dateFrom = '';
    public $dateTo = '';
    public $selectedTransactionId = '';
    public $showTransactionModal = false;
    public $showRefundModal = false;
    public $refundReason = '';

    // Manual transaction
    public $showManualModal = false;
    public $manualUserId = '';
    public $manualAmount = '';
    public $manualType = 'deposit'; // deposit, withdrawal, adjustment
    public $manualReason = '';
    public $manualGateway = 'ADMIN_MANUAL';

    protected $rules = [
        'refundReason' => 'required|string|max:255',
        'manualUserId' => 'required|exists:users,id',
        'manualAmount' => 'required|numeric|min:0.01|max:100000',
        'manualReason' => 'required|string|max:255',
        'manualType' => 'required|in:deposit,withdrawal,adjustment'
    ];

    public function mount()
    {
        $this->dateFrom = now()->subDays(30)->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
    }

    public function openTransactionModal($transactionId)
    {
        $this->selectedTransactionId = $transactionId;
        $this->showTransactionModal = true;
    }

    public function closeTransactionModal()
    {
        $this->showTransactionModal = false;
        $this->selectedTransactionId = '';
    }

    public function openRefundModal($transactionId)
    {
        $this->selectedTransactionId = $transactionId;
        $this->refundReason = '';
        $this->showRefundModal = true;
    }

    public function closeRefundModal()
    {
        $this->showRefundModal = false;
        $this->selectedTransactionId = '';
        $this->refundReason = '';
    }

    public function openManualModal()
    {
        $this->reset(['manualUserId', 'manualAmount', 'manualReason', 'manualType']);
        $this->manualType = 'deposit';
        $this->manualGateway = 'ADMIN_MANUAL';
        $this->showManualModal = true;
    }

    public function closeManualModal()
    {
        $this->showManualModal = false;
        $this->reset(['manualUserId', 'manualAmount', 'manualReason', 'manualType']);
    }

    public function processRefund()
    {
        $this->validate(['refundReason' => 'required|string|max:255']);

        try {
            $transaction = Transaction::findOrFail($this->selectedTransactionId);

            if ($transaction->status !== 'COMPLETED') {
                session()->flash('error', 'Chỉ có thể refund transaction đã COMPLETED');
                return;
            }

            DB::transaction(function () use ($transaction) {
                $user = $transaction->user;

                // Refund to user balance
                $user->increment('balance', $transaction->amount);

                // Create refund transaction
                Transaction::create([
                    'user_id' => $user->id,
                    'amount' => $transaction->amount,
                    'currency' => $transaction->currency,
                    'payment_gateway' => 'ADMIN_REFUND',
                    'status' => 'COMPLETED',
                    'description' => "Refund for transaction #{$transaction->id}: {$this->refundReason}",
                    'payment_code' => 'REF' . time(),
                    'api_response' => [
                        'original_transaction_id' => $transaction->id,
                        'refund_reason' => $this->refundReason,
                        'admin_id' => auth()->id(),
                        'refunded_at' => now()->toISOString()
                    ]
                ]);

                // Update original transaction
                $transaction->update([
                    'api_response' => array_merge($transaction->api_response ?? [], [
                        'refunded' => true,
                        'refund_reason' => $this->refundReason,
                        'refunded_by' => auth()->id(),
                        'refunded_at' => now()->toISOString()
                    ])
                ]);

                Log::info('Admin refund processed', [
                    'admin_id' => auth()->id(),
                    'transaction_id' => $transaction->id,
                    'user_id' => $user->id,
                    'amount' => $transaction->amount,
                    'reason' => $this->refundReason
                ]);
            });

            session()->flash('success', "✅ Đã refund $" . number_format($transaction->amount, 2) . " cho user {$transaction->user->name}");
            $this->closeRefundModal();

        } catch (\Exception $e) {
            Log::error('Admin refund failed', [
                'error' => $e->getMessage(),
                'transaction_id' => $this->selectedTransactionId,
                'admin_id' => auth()->id()
            ]);
            session()->flash('error', 'Có lỗi xảy ra khi refund');
        }
    }

    public function createManualTransaction()
    {
        $this->validate();

        try {
            DB::transaction(function () {
                $user = User::findOrFail($this->manualUserId);
                $amount = (float) $this->manualAmount;

                if ($this->manualType === 'withdrawal' && $user->balance < $amount) {
                    throw new \Exception('User balance insufficient for withdrawal');
                }

                // Update user balance
                if ($this->manualType === 'deposit' || $this->manualType === 'adjustment') {
                    $user->increment('balance', $amount);
                } else { // withdrawal
                    $user->decrement('balance', $amount);
                }

                // Create transaction
                $transaction = Transaction::create([
                    'user_id' => $user->id,
                    'amount' => $amount,
                    'currency' => 'USD',
                    'payment_gateway' => $this->manualGateway,
                    'status' => 'COMPLETED',
                    'description' => "Manual {$this->manualType}: {$this->manualReason}",
                    'payment_code' => strtoupper($this->manualType) . time(),
                    'api_response' => [
                        'manual_transaction' => true,
                        'type' => $this->manualType,
                        'admin_id' => auth()->id(),
                        'reason' => $this->manualReason,
                        'created_at' => now()->toISOString()
                    ]
                ]);

                // Apply bonus if it's a deposit
                if ($this->manualType === 'deposit') {
                    $bonusService = app(BonusService::class);
                    $bonusService->calculateAndApplyBonus($user, $transaction);
                }

                Log::info('Manual transaction created', [
                    'admin_id' => auth()->id(),
                    'transaction_id' => $transaction->id,
                    'user_id' => $user->id,
                    'type' => $this->manualType,
                    'amount' => $amount
                ]);
            });

            $actionText = match($this->manualType) {
                'deposit' => 'nạp tiền',
                'withdrawal' => 'rút tiền',
                'adjustment' => 'điều chỉnh'
            };

            session()->flash('success', "✅ Đã tạo giao dịch {$actionText} $" . number_format($this->manualAmount, 2));
            $this->closeManualModal();

        } catch (\Exception $e) {
            Log::error('Manual transaction failed', [
                'error' => $e->getMessage(),
                'admin_id' => auth()->id()
            ]);
            session()->flash('error', 'Có lỗi xảy ra: ' . $e->getMessage());
        }
    }

    public function render()
    {
        $query = Transaction::with(['user'])
                           ->when($this->search, function($q) {
                               $q->whereHas('user', function($userQuery) {
                                   $userQuery->where('name', 'like', '%' . $this->search . '%')
                                            ->orWhere('email', 'like', '%' . $this->search . '%');
                               })->orWhere('payment_code', 'like', '%' . $this->search . '%')
                                 ->orWhere('description', 'like', '%' . $this->search . '%');
                           })
                           ->when($this->statusFilter, function($q) {
                               $q->where('status', $this->statusFilter);
                           })
                           ->when($this->gatewayFilter, function($q) {
                               $q->where('payment_gateway', $this->gatewayFilter);
                           })
                           ->when($this->dateFrom, function($q) {
                               $q->whereDate('created_at', '>=', $this->dateFrom);
                           })
                           ->when($this->dateTo, function($q) {
                               $q->whereDate('created_at', '<=', $this->dateTo);
                           })
                           ->orderBy('created_at', 'desc');

        $transactions = $query->paginate(20);

        // Statistics
        $stats = [
            'total_transactions' => Transaction::count(),
            'completed_transactions' => Transaction::where('status', 'COMPLETED')->count(),
            'pending_transactions' => Transaction::where('status', 'PENDING')->count(),
            'failed_transactions' => Transaction::where('status', 'FAILED')->count(),
            'total_amount' => Transaction::where('status', 'COMPLETED')->sum('amount'),
            'today_amount' => Transaction::where('status', 'COMPLETED')->whereDate('created_at', today())->sum('amount'),
            'this_month_amount' => Transaction::where('status', 'COMPLETED')->whereMonth('created_at', now()->month)->sum('amount'),
        ];

        // Payment gateways
        $gateways = Transaction::select('payment_gateway')
                              ->distinct()
                              ->pluck('payment_gateway')
                              ->filter()
                              ->sort()
                              ->values();

        $selectedTransaction = $this->selectedTransactionId
            ? Transaction::with(['user'])->find($this->selectedTransactionId)
            : null;

        return view('livewire.admin.payment-manager', [
            'transactions' => $transactions,
            'stats' => $stats,
            'gateways' => $gateways,
            'selectedTransaction' => $selectedTransaction
        ]);
    }
}
