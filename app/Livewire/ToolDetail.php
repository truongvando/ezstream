<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Models\Tool;
use App\Models\ToolOrder;
use App\Models\Transaction;
use App\Models\License;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

#[Layout('layouts.sidebar')]
class ToolDetail extends Component
{
    public Tool $tool;
    public $activeImageIndex = 0;
    public $showPurchaseModal = false;
    public $userBalance = 0;

    public function mount($slug)
    {
        $this->tool = Tool::where('slug', $slug)
                         ->where('is_active', true)
                         ->firstOrFail();
        
        // Calculate user balance (mock for now)
        $this->userBalance = 1000000; // 1M VND mock balance
    }

    public function setActiveImage($index)
    {
        $this->activeImageIndex = $index;
    }

    public function togglePurchaseModal()
    {
        $this->showPurchaseModal = !$this->showPurchaseModal;
    }

    public function purchase()
    {
        try {
            $user = Auth::user();
            
            // Check if user already owns this tool
            $existingOrder = ToolOrder::where('user_id', $user->id)
                                    ->where('tool_id', $this->tool->id)
                                    ->where('status', 'COMPLETED')
                                    ->first();

            if ($existingOrder) {
                session()->flash('error', 'Bạn đã sở hữu tool này rồi!');
                return;
            }

            $amount = $this->tool->final_price;

            // Create tool order
            $toolOrder = ToolOrder::create([
                'user_id' => $user->id,
                'tool_id' => $this->tool->id,
                'amount' => $amount,
                'status' => 'PENDING'
            ]);

            // Generate payment code
            $paymentCode = 'TOOL' . str_pad($toolOrder->id, 6, '0', STR_PAD_LEFT);

            // Create transaction
            $transaction = Transaction::create([
                'user_id' => $user->id,
                'tool_order_id' => $toolOrder->id,
                'payment_code' => $paymentCode,
                'amount' => $amount,
                'currency' => 'VND',
                'payment_gateway' => 'VIETQR_VCB',
                'status' => 'PENDING',
                'description' => "Mua tool: {$this->tool->name}"
            ]);

            Log::info('Tool order created', [
                'order_id' => $toolOrder->id,
                'user_id' => $user->id,
                'tool' => $this->tool->name,
                'amount' => $amount
            ]);

            $this->showPurchaseModal = false;
            session()->flash('success', 'Đơn hàng đã được tạo thành công! Mã thanh toán: ' . $paymentCode);

            // Redirect to payment page
            return redirect()->route('payment.tool-order', ['order' => $toolOrder->id]);

        } catch (\Exception $e) {
            Log::error('Error creating tool order', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'tool_id' => $this->tool->id
            ]);

            session()->flash('error', 'Có lỗi xảy ra khi tạo đơn hàng. Vui lòng thử lại.');
        }
    }

    public function getUserToolOrder()
    {
        if (!Auth::check()) {
            return null;
        }

        return ToolOrder::where('user_id', Auth::id())
                       ->where('tool_id', $this->tool->id)
                       ->with('license')
                       ->latest()
                       ->first();
    }

    public function getUserLicense()
    {
        if (!Auth::check()) {
            return null;
        }

        return License::where('user_id', Auth::id())
                     ->where('tool_id', $this->tool->id)
                     ->where('is_active', true)
                     ->first();
    }

    public function downloadTool()
    {
        $userOrder = $this->getUserToolOrder();
        
        if (!$userOrder || $userOrder->status !== 'COMPLETED') {
            session()->flash('error', 'Bạn chưa mua tool này hoặc đơn hàng chưa được xử lý.');
            return;
        }

        // Log download activity
        Log::info('Tool download', [
            'user_id' => Auth::id(),
            'tool_id' => $this->tool->id,
            'order_id' => $userOrder->id
        ]);

        // In real implementation, this would serve the actual file
        session()->flash('success', 'Đang chuẩn bị file download...');
        
        // Redirect to download URL
        return redirect($this->tool->download_url);
    }

    public function render()
    {
        $userOrder = $this->getUserToolOrder();
        $userLicense = $this->getUserLicense();
        $relatedTools = Tool::where('is_active', true)
                           ->where('id', '!=', $this->tool->id)
                           ->inRandomOrder()
                           ->take(3)
                           ->get();

        return view('livewire.tool-detail', [
            'userOrder' => $userOrder,
            'userLicense' => $userLicense,
            'relatedTools' => $relatedTools
        ]);
    }
}
