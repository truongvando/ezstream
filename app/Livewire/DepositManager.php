<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Models\Transaction;
use App\Services\ExchangeRateService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

#[Layout('layouts.sidebar')]
class DepositManager extends Component
{
    public $amount = '';
    public $showPaymentModal = false;
    public $currentTransaction = null;
    public $paymentInfo = null;

    protected $rules = [
        'amount' => 'required|numeric|min:1|max:10000'
    ];

    public function createDeposit()
    {
        $this->validate();

        $user = Auth::user();

        // Generate unique payment code for deposit
        $paymentCode = 'DEP' . str_pad($user->id, 4, '0', STR_PAD_LEFT) . strtoupper(Str::random(4));

        // Create transaction
        $transaction = Transaction::create([
            'user_id' => $user->id,
            'payment_code' => $paymentCode,
            'amount' => $this->amount,
            'currency' => 'USD',
            'payment_gateway' => 'bank_transfer',
            'status' => 'PENDING',
            'description' => "Nạp tiền vào tài khoản - {$paymentCode}",
        ]);

        $this->currentTransaction = $transaction;
        $this->paymentInfo = $this->generatePaymentInfo($transaction);
        $this->showPaymentModal = true;
        $this->amount = '';
    }

    public function closePaymentModal()
    {
        $this->showPaymentModal = false;
        $this->currentTransaction = null;
        $this->paymentInfo = null;
    }

    public function checkPaymentStatus()
    {
        if ($this->currentTransaction) {
            $this->currentTransaction = $this->currentTransaction->fresh();

            if ($this->currentTransaction->status === 'COMPLETED') {
                session()->flash('success', 'Thanh toán thành công! Số dư đã được cập nhật.');
                $this->closePaymentModal();

                // Dispatch event to update balance in header
                $newBalance = auth()->user()->fresh()->balance;
                $this->dispatch('balance-updated', balance: $newBalance);
            }
        }
    }

    public function cancelTransaction()
    {
        if ($this->currentTransaction && $this->currentTransaction->status === 'PENDING') {
            $this->currentTransaction->update([
                'status' => 'FAILED',
                'description' => $this->currentTransaction->description . ' (Đã hủy bởi người dùng)'
            ]);
            
            session()->flash('success', 'Đã hủy giao dịch nạp tiền.');
            $this->closePaymentModal();
        }
    }

    private function generatePaymentInfo($transaction)
    {
        $amount = $transaction->amount;
        $paymentCode = $transaction->payment_code;

        return [
            'type' => 'bank_transfer',
            'bank_name' => 'Vietcombank',
            'account_number' => '0971000032314',
            'account_name' => 'TRUONG VAN DO',
            'amount' => $amount,
            'content' => $paymentCode,
            'qr_code' => $this->generateVietQR($amount, $paymentCode)
        ];
    }

    private function generateVietQR($amount, $paymentCode)
    {
        // Sử dụng VietQR API với tỉ giá động
        $bankConfig = config('payment.bank');
        $bankId = $bankConfig['id'];
        $accountNo = $bankConfig['account_number'];
        $accountName = $bankConfig['account_name'];

        $baseUrl = "https://img.vietqr.io/image/{$bankId}-{$accountNo}-compact2.png";

        // Convert USD to VND using current exchange rate
        $exchangeService = new ExchangeRateService();
        $vndAmount = $exchangeService->convertUsdToVnd($amount);

        $params = http_build_query([
            'amount' => round($vndAmount),
            'addInfo' => $paymentCode,
            'accountName' => $accountName,
        ]);

        return $baseUrl . '?' . $params;
    }

    public function render()
    {
        $user = Auth::user();
        $recentDeposits = Transaction::where('user_id', $user->id)
                                   ->whereNull('subscription_id')
                                   ->whereNull('tool_order_id')
                                   ->whereNull('view_order_id')
                                   ->where('description', 'LIKE', 'Nạp tiền%')
                                   ->orderBy('created_at', 'desc')
                                   ->limit(10)
                                   ->get();

        return view('livewire.deposit-manager', [
            'recentDeposits' => $recentDeposits
        ]);
    }
}
