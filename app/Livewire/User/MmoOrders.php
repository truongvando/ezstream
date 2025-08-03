<?php

namespace App\Livewire\User;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use App\Models\MmoOrder;
use Illuminate\Support\Facades\DB;

#[Layout('layouts.sidebar')]
class MmoOrders extends Component
{
    use WithPagination;

    public $statusFilter = '';
    public $showOrderModal = false;
    public $selectedOrderId = '';
    public $showCancelModal = false;
    public $cancelReason = '';

    public function openOrderModal($orderId)
    {
        $this->selectedOrderId = $orderId;
        $this->showOrderModal = true;
    }

    public function closeOrderModal()
    {
        $this->showOrderModal = false;
        $this->selectedOrderId = '';
    }

    public function openCancelModal($orderId)
    {
        $this->selectedOrderId = $orderId;
        $this->cancelReason = '';
        $this->showCancelModal = true;
    }

    public function closeCancelModal()
    {
        $this->showCancelModal = false;
        $this->selectedOrderId = '';
        $this->cancelReason = '';
    }

    public function cancelOrder()
    {
        $this->validate([
            'cancelReason' => 'required|string|max:500'
        ]);

        try {
            $order = MmoOrder::where('id', $this->selectedOrderId)
                            ->where('user_id', auth()->id())
                            ->whereIn('status', ['PENDING', 'PROCESSING'])
                            ->firstOrFail();

            $user = auth()->user();

            if ($order->status === 'PENDING') {
                // PENDING: Hủy ngay lập tức và hoàn tiền
                $user->increment('balance', $order->amount);

                $order->update([
                    'status' => 'CANCELLED',
                    'cancelled_at' => now(),
                    'admin_notes' => "Hủy bởi khách hàng: {$this->cancelReason}"
                ]);

                $this->sendCancelNotification($order, $user, 'cancelled');
                session()->flash('success', "Đã hủy đơn hàng #{$order->order_code} và hoàn tiền \${$order->amount}");

            } else if ($order->status === 'PROCESSING') {
                // PROCESSING: Gửi yêu cầu hủy, chờ admin duyệt
                $order->update([
                    'admin_notes' => "YÊU CẦU HỦY từ khách hàng: {$this->cancelReason} - CHỜ ADMIN DUYỆT"
                ]);

                $this->sendCancelNotification($order, $user, 'request');
                session()->flash('success', "Đã gửi yêu cầu hủy đơn #{$order->order_code}. Admin sẽ xem xét và phản hồi.");
            }

            $this->closeCancelModal();

        } catch (\Exception $e) {
            session()->flash('error', 'Không thể hủy đơn hàng này. Vui lòng liên hệ admin.');
        }
    }

    private function sendCancelNotification($order, $user, $type)
    {
        try {
            $botToken = config('services.telegram.bot_token');
            $chatId = config('services.telegram.admin_chat_id');

            if (!$botToken || !$chatId) {
                return;
            }

            if ($type === 'cancelled') {
                // PENDING order cancelled immediately
                $message = "*MMO Order Cancelled (PENDING)*\n\n";
                $message .= "Order: `#{$order->order_code}`\n";
                $message .= "Customer: {$user->name}\n";
                $message .= "Email: {$user->email}\n";
                $message .= "Service: {$order->mmoService->name}\n";
                $message .= "Refunded: \${$order->amount}\n";
                $message .= "Reason: {$this->cancelReason}\n";
                $message .= "Status: CANCELLED\n";

            } else if ($type === 'request') {
                // PROCESSING order - cancel request
                $message = "*CANCEL REQUEST - MMO Order (PROCESSING)*\n\n";
                $message .= "Order: `#{$order->order_code}`\n";
                $message .= "Customer: {$user->name}\n";
                $message .= "Email: {$user->email}\n";
                $message .= "Service: {$order->mmoService->name}\n";
                $message .= "Amount: \${$order->amount}\n";
                $message .= "Cancel Reason: {$this->cancelReason}\n";
                $message .= "Status: PROCESSING (CANCEL REQUESTED)\n\n";
                $message .= "ACTION REQUIRED:\n";
                $message .= "- Review the cancel request\n";
                $message .= "- Approve/Deny in admin panel\n";
                $message .= "- Process refund if approved\n";
            }

            $message .= "\nTime: " . now()->format('d/m/Y H:i:s');

            \Illuminate\Support\Facades\Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'Markdown'
            ]);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Cancel notification failed', [
                'error' => $e->getMessage(),
                'order_id' => $order->id
            ]);
        }
    }

    public function render()
    {
        $query = MmoOrder::with(['mmoService'])
                         ->where('user_id', auth()->id())
                         ->when($this->statusFilter, function($q) {
                             $q->where('status', $this->statusFilter);
                         })
                         ->orderBy('created_at', 'desc');

        $orders = $query->paginate(10);

        // Statistics
        $stats = [
            'total_orders' => MmoOrder::where('user_id', auth()->id())->count(),
            'pending_orders' => MmoOrder::where('user_id', auth()->id())->where('status', 'PENDING')->count(),
            'processing_orders' => MmoOrder::where('user_id', auth()->id())->where('status', 'PROCESSING')->count(),
            'completed_orders' => MmoOrder::where('user_id', auth()->id())->where('status', 'COMPLETED')->count(),
            'total_spent' => MmoOrder::where('user_id', auth()->id())->where('status', 'COMPLETED')->sum('amount')
        ];

        $selectedOrder = $this->selectedOrderId
            ? MmoOrder::with(['mmoService'])->find($this->selectedOrderId)
            : null;

        return view('livewire.user.mmo-orders', [
            'orders' => $orders,
            'stats' => $stats,
            'selectedOrder' => $selectedOrder
        ]);
    }
}
