<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use App\Models\MmoOrder;
use App\Models\MmoService;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

#[Layout('layouts.sidebar')]
class MmoOrderManager extends Component
{
    use WithPagination;

    public $search = '';
    public $statusFilter = '';
    public $serviceFilter = '';

    // Order processing
    public $showOrderModal = false;
    public $selectedOrderId = '';
    public $adminNotes = '';
    public $deliveryNotes = '';
    public $newStatus = '';
    public $showCancelRequestModal = false;

    protected $rules = [
        'adminNotes' => 'nullable|string|max:1000',
        'deliveryNotes' => 'nullable|string|max:1000',
        'newStatus' => 'required|in:PENDING,PROCESSING,COMPLETED,CANCELLED'
    ];

    public function openOrderModal($orderId)
    {
        $order = MmoOrder::with(['user', 'mmoService'])->findOrFail($orderId);
        $this->selectedOrderId = $orderId;
        $this->adminNotes = $order->admin_notes;
        $this->deliveryNotes = $order->delivery_notes;
        $this->newStatus = $order->status;
        $this->showOrderModal = true;
    }

    public function closeOrderModal()
    {
        $this->showOrderModal = false;
        $this->reset(['selectedOrderId', 'adminNotes', 'deliveryNotes', 'newStatus']);
    }

    public function updateOrder()
    {
        $this->validate();

        try {
            $order = MmoOrder::findOrFail($this->selectedOrderId);
            $oldStatus = $order->status;

            $order->update([
                'status' => $this->newStatus,
                'admin_notes' => $this->adminNotes,
                'delivery_notes' => $this->deliveryNotes,
                'completed_at' => $this->newStatus === 'COMPLETED' ? now() : null,
                'cancelled_at' => $this->newStatus === 'CANCELLED' ? now() : null
            ]);

            // Send Telegram notification if status changed
            if ($oldStatus !== $this->newStatus) {
                $this->sendTelegramNotification($order, $oldStatus);
            }

            session()->flash('success', "✅ Đã cập nhật đơn hàng #{$order->order_code}");
            $this->closeOrderModal();

            Log::info('MMO Order updated', [
                'order_id' => $order->id,
                'old_status' => $oldStatus,
                'new_status' => $this->newStatus,
                'admin_id' => auth()->id()
            ]);

        } catch (\Exception $e) {
            Log::error('MMO Order update failed', [
                'error' => $e->getMessage(),
                'order_id' => $this->selectedOrderId,
                'admin_id' => auth()->id()
            ]);
            session()->flash('error', 'Có lỗi xảy ra khi cập nhật đơn hàng');
        }
    }

    public function quickUpdateStatus($orderId, $status)
    {
        try {
            $order = MmoOrder::findOrFail($orderId);
            $oldStatus = $order->status;

            $order->update([
                'status' => $status,
                'completed_at' => $status === 'COMPLETED' ? now() : null,
                'cancelled_at' => $status === 'CANCELLED' ? now() : null
            ]);

            // Send Telegram notification
            $this->sendTelegramNotification($order, $oldStatus);

            $statusText = match($status) {
                'PROCESSING' => 'đang xử lý',
                'COMPLETED' => 'hoàn thành',
                'CANCELLED' => 'hủy'
            };

            session()->flash('success', "✅ Đã {$statusText} đơn hàng #{$order->order_code}");

        } catch (\Exception $e) {
            session()->flash('error', 'Có lỗi xảy ra');
        }
    }

    private function sendTelegramNotification($order, $oldStatus)
    {
        try {
            $botToken = config('services.telegram.bot_token');
            $chatId = config('services.telegram.admin_chat_id');

            if (!$botToken || !$chatId) {
                return;
            }

            $statusEmoji = match($order->status) {
                'PENDING' => '🟡',
                'PROCESSING' => '🔵',
                'COMPLETED' => '🟢',
                'CANCELLED' => '🔴',
                'REFUNDED' => '🟠'
            };

            $message = "🎮 *MMO Order Update*\n\n";
            $message .= "📋 Order: `#{$order->order_code}`\n";
            $message .= "👤 User: {$order->user->name}\n";
            $message .= "🛍️ Service: {$order->mmoService->name}\n";
            $message .= "💰 Amount: \${$order->amount}\n";
            $message .= "📊 Status: {$oldStatus} → {$statusEmoji} *{$order->status}*\n";

            if ($order->admin_notes) {
                $message .= "📝 Admin Notes: {$order->admin_notes}\n";
            }

            if ($order->delivery_notes) {
                $message .= "📦 Delivery: {$order->delivery_notes}\n";
            }

            $message .= "\n🕒 " . now()->format('d/m/Y H:i:s');

            Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'Markdown'
            ]);

        } catch (\Exception $e) {
            Log::error('Telegram notification failed', [
                'error' => $e->getMessage(),
                'order_id' => $order->id
            ]);
        }
    }

    public function render()
    {
        $query = MmoOrder::with(['user', 'mmoService'])
                         ->when($this->search, function($q) {
                             $q->where('order_code', 'like', '%' . $this->search . '%')
                               ->orWhereHas('user', function($userQuery) {
                                   $userQuery->where('name', 'like', '%' . $this->search . '%')
                                            ->orWhere('email', 'like', '%' . $this->search . '%');
                               })
                               ->orWhereHas('mmoService', function($serviceQuery) {
                                   $serviceQuery->where('name', 'like', '%' . $this->search . '%');
                               });
                         })
                         ->when($this->statusFilter, function($q) {
                             $q->where('status', $this->statusFilter);
                         })
                         ->when($this->serviceFilter, function($q) {
                             $q->where('mmo_service_id', $this->serviceFilter);
                         })
                         ->orderBy('created_at', 'desc');

        $orders = $query->paginate(20);

        // Statistics
        $stats = [
            'total_orders' => MmoOrder::count(),
            'pending_orders' => MmoOrder::where('status', 'PENDING')->count(),
            'processing_orders' => MmoOrder::where('status', 'PROCESSING')->count(),
            'completed_orders' => MmoOrder::where('status', 'COMPLETED')->count(),
            'cancelled_orders' => MmoOrder::where('status', 'CANCELLED')->count(),
            'total_revenue' => MmoOrder::where('status', 'COMPLETED')->sum('amount'),
            'today_orders' => MmoOrder::whereDate('created_at', today())->count(),
            'today_revenue' => MmoOrder::where('status', 'COMPLETED')->whereDate('created_at', today())->sum('amount')
        ];

        // Services for filter
        $services = MmoService::select('id', 'name')
                              ->where('is_active', true)
                              ->orderBy('name')
                              ->get();

        $selectedOrder = $this->selectedOrderId
            ? MmoOrder::with(['user', 'mmoService'])->find($this->selectedOrderId)
            : null;

        return view('livewire.admin.mmo-order-manager', [
            'orders' => $orders,
            'stats' => $stats,
            'services' => $services,
            'selectedOrder' => $selectedOrder
        ]);
    }

    public function approveCancelRequest($orderId)
    {
        try {
            $order = MmoOrder::findOrFail($orderId);

            if ($order->status !== 'PROCESSING' || !str_contains($order->admin_notes, 'YÊU CẦU HỦY')) {
                session()->flash('error', 'Đơn hàng này không có yêu cầu hủy hợp lệ');
                return;
            }

            // Refund to user
            $order->user->increment('balance', $order->amount);

            // Update order
            $order->update([
                'status' => 'CANCELLED',
                'cancelled_at' => now(),
                'admin_notes' => $order->admin_notes . " - ADMIN APPROVED CANCEL & REFUNDED"
            ]);

            // Send notification
            $this->sendCancelApprovalNotification($order, 'approved');

            session()->flash('success', "Đã duyệt hủy đơn #{$order->order_code} và hoàn tiền \${$order->amount}");

        } catch (\Exception $e) {
            session()->flash('error', 'Có lỗi xảy ra khi duyệt hủy đơn');
        }
    }

    public function denyCancelRequest($orderId)
    {
        try {
            $order = MmoOrder::findOrFail($orderId);

            if ($order->status !== 'PROCESSING' || !str_contains($order->admin_notes, 'YÊU CẦU HỦY')) {
                session()->flash('error', 'Đơn hàng này không có yêu cầu hủy hợp lệ');
                return;
            }

            // Update order - remove cancel request
            $originalNotes = str_replace('YÊU CẦU HỦY từ khách hàng:', 'CANCEL REQUEST DENIED -', $order->admin_notes);
            $order->update([
                'admin_notes' => $originalNotes . " - ADMIN DENIED CANCEL REQUEST"
            ]);

            // Send notification
            $this->sendCancelApprovalNotification($order, 'denied');

            session()->flash('success', "Đã từ chối yêu cầu hủy đơn #{$order->order_code}");

        } catch (\Exception $e) {
            session()->flash('error', 'Có lỗi xảy ra khi từ chối hủy đơn');
        }
    }

    private function sendCancelApprovalNotification($order, $action)
    {
        try {
            $botToken = config('services.telegram.bot_token');
            $chatId = config('services.telegram.admin_chat_id');

            if (!$botToken || !$chatId) {
                return;
            }

            $message = "*Cancel Request " . strtoupper($action) . "*\n\n";
            $message .= "Order: `#{$order->order_code}`\n";
            $message .= "Customer: {$order->user->name}\n";
            $message .= "Service: {$order->mmoService->name}\n";
            $message .= "Amount: \${$order->amount}\n";

            if ($action === 'approved') {
                $message .= "Status: CANCELLED\n";
                $message .= "Refund: PROCESSED\n";
            } else {
                $message .= "Status: PROCESSING (continues)\n";
                $message .= "Refund: NOT PROCESSED\n";
            }

            $message .= "\nTime: " . now()->format('d/m/Y H:i:s');

            Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'Markdown'
            ]);

        } catch (\Exception $e) {
            Log::error('Cancel approval notification failed', [
                'error' => $e->getMessage(),
                'order_id' => $order->id
            ]);
        }
    }
}
