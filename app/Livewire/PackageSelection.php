<?php

namespace App\Livewire;

use App\Models\ServicePackage;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Illuminate\Support\Str;
use App\Models\Subscription;

class PackageSelection extends Component
{
    public $currentPackageId = null;

    public function mount()
    {
        $activeSubscription = Auth::user()->subscriptions()->where('status', 'ACTIVE')->first();
        if ($activeSubscription) {
            $this->currentPackageId = $activeSubscription->service_package_id;
        }
    }
    
    public function selectPackage($packageId)
    {
        try {
            $user = Auth::user();
            $package = ServicePackage::findOrFail($packageId);
            
            $pendingSubscription = $user->subscriptions()
                ->where('status', 'PENDING_PAYMENT')
                ->exists();

            if ($pendingSubscription) {
                session()->flash('error', 'Bạn có gói dịch vụ đang chờ thanh toán. Vui lòng hoàn tất thanh toán hoặc hủy gói đó trước khi mua gói mới.');
                return redirect()->route('billing.manager');
            }

            $activeSubscription = $user->subscriptions()
                ->where('status', 'ACTIVE')
                ->with('servicePackage')
                ->first();

            $isUpgrade = false;
            if ($activeSubscription) {
                if ($package->price <= $activeSubscription->servicePackage->price) {
                    session()->flash('error', 'Bạn chỉ có thể nâng cấp lên gói cao hơn. Gói bạn chọn không phải là nâng cấp.');
                    return redirect()->route('billing.manager');
                }
                $isUpgrade = true;
            }

            $amount = $package->price;
            $description = $isUpgrade ? "Nâng cấp lên gói {$package->name}" : "Thanh toán gói {$package->name}";
            
            $subscription = $user->subscriptions()->create([
                'service_package_id' => $package->id,
                'status' => 'PENDING_PAYMENT',
            ]);
            
            $paymentCode = 'HD' . str_pad($subscription->id, 6, '0', STR_PAD_LEFT);

            $transaction = $subscription->transactions()->create([
                'user_id' => $user->id,
                'payment_code' => $paymentCode,
                'amount' => $amount,
                'currency' => 'VND',
                'payment_gateway' => 'VIETQR_VCB',
                'status' => 'PENDING',
                'description' => $description,
            ]);

            session()->flash('success', $isUpgrade ? 'Đã tạo yêu cầu nâng cấp! Vui lòng thanh toán để hoàn tất.' : 'Đã tạo đơn hàng thành công! Đang chuyển đến trang thanh toán...');
            
            return redirect()->route('payment.manager', ['transactionId' => $transaction->id]);
            
        } catch (\Exception $e) {
            session()->flash('error', 'Có lỗi xảy ra khi chọn gói: ' . $e->getMessage());
            return redirect()->route('billing.manager');
        }
    }

    public function render()
    {
        $packages = ServicePackage::where('is_active', true)->orderBy('price')->get();
        return view('livewire.package-selection', [
            'packages' => $packages,
            'hasPendingSubscription' => Auth::user()->subscriptions()->where('status', 'PENDING_PAYMENT')->exists(),
        ]);
    }
}
