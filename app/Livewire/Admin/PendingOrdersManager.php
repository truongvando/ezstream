<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use App\Models\ViewOrder;
use App\Services\JapApiService;
use Illuminate\Support\Facades\Log;

#[Layout('layouts.sidebar')]
class PendingOrdersManager extends Component
{
    use WithPagination;

    public $selectedStatus = 'PENDING_FUNDS';
    public $searchTerm = '';
    public $processingOrders = [];

    protected $listeners = ['refreshOrders' => '$refresh'];

    public function mount()
    {
        // Check if user is admin
        if (!auth()->user()->is_admin) {
            abort(403, 'Unauthorized');
        }
    }

    public function retryOrder($orderId)
    {
        try {
            $order = ViewOrder::find($orderId);
            if (!$order) {
                session()->flash('error', 'Đơn hàng không tồn tại');
                return;
            }

            if (!in_array($order->status, ['PENDING_FUNDS', 'PENDING_RETRY', 'FAILED'])) {
                session()->flash('error', 'Đơn hàng không thể retry');
                return;
            }

            $this->processingOrders[] = $orderId;

            $japService = app(JapApiService::class);
            
            // Try to place order again
            $result = $japService->placeOrder(
                $order->service_id,
                $order->link,
                $order->quantity
            );

            if ($result && isset($result['order'])) {
                // Success
                $order->update([
                    'status' => 'PROCESSING',
                    'api_order_id' => $result['order'],
                    'api_response' => array_merge($order->api_response ?? [], [
                        'retry_success' => true,
                        'api_order_id' => $result['order'],
                        'retried_at' => now()->toISOString()
                    ])
                ]);

                session()->flash('success', "✅ Đơn hàng #{$orderId} đã được xử lý thành công!");
            } elseif ($result && isset($result['error'])) {
                // Still failed
                $error = $result['error'];
                $newStatus = 'FAILED';
                
                if (stripos($error, 'not enough funds') !== false) {
                    $newStatus = 'PENDING_FUNDS';
                }

                $order->update([
                    'status' => $newStatus,
                    'api_response' => array_merge($order->api_response ?? [], [
                        'retry_failed' => true,
                        'retry_error' => $error,
                        'retried_at' => now()->toISOString()
                    ])
                ]);

                session()->flash('error', "❌ Đơn hàng #{$orderId} vẫn thất bại: {$error}");
            } else {
                session()->flash('error', "❌ Không thể kết nối API cho đơn hàng #{$orderId}");
            }

        } catch (\Exception $e) {
            Log::error('Retry order failed', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);
            session()->flash('error', "❌ Lỗi khi retry đơn hàng #{$orderId}");
        } finally {
            $this->processingOrders = array_filter($this->processingOrders, fn($id) => $id !== $orderId);
        }
    }

    public function retryAllPendingFunds()
    {
        $pendingOrders = ViewOrder::where('status', 'PENDING_FUNDS')->get();
        
        if ($pendingOrders->isEmpty()) {
            session()->flash('info', 'Không có đơn hàng nào cần retry');
            return;
        }

        $successCount = 0;
        $failCount = 0;

        foreach ($pendingOrders as $order) {
            try {
                $this->retryOrder($order->id);
                $successCount++;
            } catch (\Exception $e) {
                $failCount++;
            }
        }

        session()->flash('info', "Đã retry {$pendingOrders->count()} đơn hàng. Thành công: {$successCount}, Thất bại: {$failCount}");
    }

    public function cancelOrder($orderId)
    {
        try {
            $order = ViewOrder::find($orderId);
            if (!$order) {
                session()->flash('error', 'Đơn hàng không tồn tại');
                return;
            }

            if ($order->processRefund('Cancelled by admin')) {
                session()->flash('success', "✅ Đơn hàng #{$orderId} đã được hủy và hoàn tiền");
            } else {
                session()->flash('error', "❌ Không thể hủy đơn hàng #{$orderId}");
            }

        } catch (\Exception $e) {
            Log::error('Cancel order failed', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);
            session()->flash('error', "❌ Lỗi khi hủy đơn hàng #{$orderId}");
        }
    }

    public function getOrdersProperty()
    {
        $query = ViewOrder::with('user')
            ->orderBy('created_at', 'desc');

        if ($this->selectedStatus) {
            $query->where('status', $this->selectedStatus);
        }

        if ($this->searchTerm) {
            $query->where(function($q) {
                $q->where('id', 'like', '%' . $this->searchTerm . '%')
                  ->orWhere('link', 'like', '%' . $this->searchTerm . '%')
                  ->orWhere('service_id', 'like', '%' . $this->searchTerm . '%')
                  ->orWhereHas('user', function($userQuery) {
                      $userQuery->where('name', 'like', '%' . $this->searchTerm . '%')
                               ->orWhere('email', 'like', '%' . $this->searchTerm . '%');
                  });
            });
        }

        return $query->paginate(20);
    }

    public function render()
    {
        $statusCounts = [
            'PENDING_FUNDS' => ViewOrder::where('status', 'PENDING_FUNDS')->count(),
            'PENDING_RETRY' => ViewOrder::where('status', 'PENDING_RETRY')->count(),
            'FAILED' => ViewOrder::where('status', 'FAILED')->count(),
            'PROCESSING' => ViewOrder::where('status', 'PROCESSING')->count(),
            'COMPLETED' => ViewOrder::where('status', 'COMPLETED')->count(),
        ];

        return view('livewire.admin.pending-orders-manager', [
            'orders' => $this->getOrdersProperty(),
            'statusCounts' => $statusCounts
        ]);
    }
}
