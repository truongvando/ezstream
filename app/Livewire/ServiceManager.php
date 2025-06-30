<?php

namespace App\Livewire;

use App\Models\ServicePackage;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Services\ProrationService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class ServiceManager extends Component
{
    use WithPagination;

    public $activeSubscription; // ✅ CHỈ CÓ 1 GÓI ACTIVE TẠI 1 THỜI ĐIỂM
    public $packages;
    public $showPaymentModal = false;
    public $selectedPackage = null;
    public $paymentTransaction = null;
    public $qrCodeUrl = '';
    public $activeTab = 'packages'; // packages, payment, history

    public function mount()
    {
        $user = Auth::user();
        
        // ✅ Lấy 1 gói active duy nhất (ưu tiên gói hết hạn xa nhất)
        $this->activeSubscription = $user->subscriptions()
            ->where('status', 'ACTIVE')
            ->with('servicePackage')
            ->orderBy('ends_at', 'desc')
            ->first();
            
        // ✅ Auto-expire subscriptions hết hạn
        $user->subscriptions()
            ->where('status', 'ACTIVE')
            ->where('ends_at', '<=', now())
            ->update(['status' => 'EXPIRED']);
            
        $this->packages = ServicePackage::where('is_active', true)->orderBy('price')->get();
        
        // ✅ FIX: Kiểm tra nếu có transaction vừa completed trong 5 phút qua
        $recentCompletedTransaction = $user->transactions()
            ->where('status', 'COMPLETED')
            ->where('updated_at', '>=', now()->subMinutes(5))
            ->with('subscription.servicePackage')
            ->latest('updated_at')
            ->first();
            
        if ($recentCompletedTransaction && $recentCompletedTransaction->subscription && $recentCompletedTransaction->subscription->status === 'ACTIVE') {
            // ✅ UNIFY LOGIC: Gọi hàm showSuccessAndRedirect để xử lý
            \Log::info("Recent completed transaction detected in mount(), processing...", [
                'transaction_id' => $recentCompletedTransaction->id
            ]);
            $this->showSuccessAndRedirect($recentCompletedTransaction);
            return; // Exit early
        }
        
        // Kiểm tra nếu có giao dịch đang chờ thanh toán
        $pendingTransaction = $user->transactions()
            ->where('status', 'PENDING')
            ->with('subscription.servicePackage')
            ->latest()
            ->first();
            
        if ($pendingTransaction) {
            $this->paymentTransaction = $pendingTransaction;
            $this->generateQrCodeUrl();
            $this->activeTab = 'payment';
        }
    }

    public function selectPackage($packageId)
    {
        try {
            $user = Auth::user();
            $package = ServicePackage::findOrFail($packageId);
            
            // ✅ BUSINESS LOGIC: Chỉ cho phép 1 giao dịch PENDING tại 1 thời điểm
            $pendingSubscription = $user->subscriptions()
                ->whereIn('status', ['PENDING_PAYMENT', 'PENDING'])
                ->exists();

            if ($pendingSubscription) {
                session()->flash('error', 'Bạn đã có một giao dịch đang chờ xử lý. Vui lòng hoàn tất hoặc hủy giao dịch đó trước khi tạo mới.');
                return;
            }

            // ✅ BUSINESS LOGIC: Logic cho Nâng cấp / Mua mới
            $isUpgrade = false;
            $amountToPay = $package->price;
            $description = "Thanh toán cho gói {$package->name}";

            if ($this->activeSubscription) {
                // Nếu gói mới có giá bằng hoặc thấp hơn -> không cho phép
                if ($package->price <= $this->activeSubscription->servicePackage->price) {
                    session()->flash('error', 'Bạn chỉ có thể nâng cấp lên gói có giá trị cao hơn gói hiện tại.');
                    return;
                }
                $isUpgrade = true;

                // TÍNH TOÁN CHI PHÍ NÂNG CẤP
                $proration = (new ProrationService())->calculate($this->activeSubscription, $package);
                $amountToPay = $proration['final_amount'];
                
                $creditFormatted = number_format($proration['credit'], 0, ',', '.');
                $newPriceFormatted = number_format($proration['new_price'], 0, ',', '.');

                $description = "Nâng cấp lên gói {$package->name}. Chi phí: {$newPriceFormatted}đ (đã trừ {$creditFormatted}đ từ gói cũ)";
            }

            // Tạo subscription và transaction
            $subscription = $user->subscriptions()->create([
                'service_package_id' => $package->id,
                'status' => 'PENDING_PAYMENT',
                // starts_at và ends_at sẽ được set khi thanh toán thành công
            ]);
            
            // ✅ PAYMENT CODE THEO SỐ STREAMS - Chỉ chữ và số vì bank QR không giữ dấu đặc biệt
            // Format: STREAM50001, STREAM100002 (không dấu đặc biệt)
            $paymentCode = 'STREAM' . $package->max_streams . str_pad($subscription->id, 3, '0', STR_PAD_LEFT);
            
            $this->paymentTransaction = $subscription->transactions()->create([
                'user_id' => $user->id,
                'payment_code' => $paymentCode,
                'amount' => $amountToPay, // SỬ DỤNG SỐ TIỀN ĐÃ TÍNH
                'currency' => 'VND',
                'payment_gateway' => 'VIETQR_VCB',
                'status' => 'PENDING',
                'description' => $description, // SỬ DỤNG MÔ TẢ CHI TIẾT
            ]);

            $this->selectedPackage = $package;
            $this->generateQrCodeUrl();
            $this->activeTab = 'payment';
            
            session()->flash('success', $isUpgrade ? 'Đã tạo yêu cầu nâng cấp!' : 'Đã tạo đơn hàng thành công!');
            
        } catch (\Exception $e) {
            session()->flash('error', 'Có lỗi xảy ra: ' . $e->getMessage());
        }
    }

    public function cancelPayment()
    {
        if ($this->paymentTransaction) {
            $subscription = $this->paymentTransaction->subscription;
            
            try {
                // Xóa transaction và subscription để tránh constraint issues
                $this->paymentTransaction->delete();
                $subscription->delete();
                
                $this->paymentTransaction = null;
                $this->selectedPackage = null;
                $this->activeTab = 'packages';
                
                session()->flash('success', 'Đã hủy giao dịch thành công.');
            } catch (\Exception $e) {
                session()->flash('error', 'Lỗi khi hủy giao dịch: ' . $e->getMessage());
            }
        }
    }

    public function switchTab($tab)
    {
        $this->activeTab = $tab;
        
        // Emit event để JavaScript biết tab đã thay đổi
        $this->dispatch('tabChanged', $tab);
    }

    // ✅ SIMPLE CHECK PAYMENT STATUS - Chỉ cần check database thấy OK thì nổ thông báo
    public function simpleCheckPayment()
    {
        if (!$this->paymentTransaction) return;
        
        $user = Auth::user();
        
        // ✅ Method 1: Check transaction status COMPLETED
        $freshTransaction = Transaction::find($this->paymentTransaction->id);
        if ($freshTransaction && $freshTransaction->status === 'COMPLETED') {
            $this->showSuccessAndRedirect($freshTransaction);
            return;
        }
        
        // ✅ Method 2: Check subscription status ACTIVE (backup check)
        $activeSubscription = $user->subscriptions()
            ->where('status', 'ACTIVE')
            ->where('updated_at', '>=', now()->subMinutes(2)) // Vừa được update trong 2 phút
            ->with('servicePackage')
            ->latest('updated_at')
            ->first();
            
        if ($activeSubscription) {
            $this->showSuccessAndRedirect(null, $activeSubscription);
            return;
        }
    }
    
    private function showSuccessAndRedirect($transaction = null, $subscription = null)
    {
        // Determine package name
        $packageName = 'gói dịch vụ';
        if ($transaction && $transaction->subscription && $transaction->subscription->servicePackage) {
            $packageName = $transaction->subscription->servicePackage->name;
        } elseif ($subscription && $subscription->servicePackage) {
            $packageName = $subscription->servicePackage->name;
        }
        
        // ✅ Clear payment transaction trước
        $this->paymentTransaction = null;
        $this->qrCodeUrl = '';
        $this->activeTab = 'packages';
        
        // ✅ Refresh data
        $this->refreshComponentData();
        
        // ✅ Hiện thông báo SUCCESS
        session()->flash('success', '🎉 Thanh toán thành công! Gói "' . $packageName . '" đã được kích hoạt.');
        
        \Log::info("Payment success detected and processed", ['package' => $packageName]);
    }

    // ✅ AUTO-CHECK PAYMENT STATUS
    public function checkPaymentStatus()
    {
        // ✅ Sử dụng method đơn giản
        $this->simpleCheckPayment();
    }

    protected function refreshComponentData()
    {
        $user = Auth::user();
        
        // ✅ Refresh lại 1 gói active duy nhất
        $this->activeSubscription = $user->subscriptions()
            ->where('status', 'ACTIVE')
            ->with('servicePackage')
            ->orderBy('ends_at', 'desc')
            ->first();
            
        // ✅ Auto-expire subscriptions hết hạn
        $user->subscriptions()
            ->where('status', 'ACTIVE')
            ->where('ends_at', '<=', now())
            ->update(['status' => 'EXPIRED']);
            
        $this->packages = ServicePackage::where('is_active', true)->orderBy('price')->get();
        
        // ✅ Clear payment transaction vì đã complete
        $this->paymentTransaction = null;
        $this->qrCodeUrl = '';
        
        \Log::info("Component data refreshed after payment completion");
    }

    protected function generateQrCodeUrl()
    {
        if (!$this->paymentTransaction) return;

        $bankId = '970436'; // Vietcombank
        $accountNo = '0971000032314';
        $accountName = 'TRUONG VAN DO';

        $baseUrl = "https://img.vietqr.io/image/{$bankId}-{$accountNo}-compact2.png";
        
        $params = http_build_query([
            'amount' => $this->paymentTransaction->amount,
            'addInfo' => $this->paymentTransaction->payment_code,
            'accountName' => $accountName,
        ]);
        
        $this->qrCodeUrl = $baseUrl . '?' . $params;
    }

    public function render()
    {
        $user = Auth::user();
        $transactions = $user->transactions()
            ->with('subscription.servicePackage')
            ->latest()
            ->paginate(10);

        return view('livewire.service-manager', [
            'transactions' => $transactions
        ])->layout('layouts.sidebar');
    }

    // ✅ MANUAL CHECK - User có thể nhấn nút để check ngay
    public function manualCheckPayment()
    {
        $this->simpleCheckPayment();
        
        // Nếu không thấy thành công thì hiện thông báo đang chờ
        if ($this->paymentTransaction) {
            session()->flash('info', '⏳ Chưa thấy thanh toán. Hệ thống sẽ tự động check mỗi 5 giây.');
        }
    }
} 