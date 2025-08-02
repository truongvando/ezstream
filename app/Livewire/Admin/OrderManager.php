<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use App\Models\ViewOrder;
use App\Models\ToolOrder;
use App\Models\Transaction;
use Illuminate\Support\Facades\Log;

#[Layout('layouts.sidebar')]
class OrderManager extends Component
{
    use WithPagination;

    public $search = '';
    public $statusFilter = 'all';
    public $typeFilter = 'all';
    public $dateFilter = 'all';
    public $selectedOrder = null;
    public $showDetailModal = false;

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }

    public function updatingTypeFilter()
    {
        $this->resetPage();
    }

    public function updatingDateFilter()
    {
        $this->resetPage();
    }

    public function showOrderDetail($orderId, $orderType)
    {
        try {
            if ($orderType === 'view') {
                $this->selectedOrder = ViewOrder::with(['user', 'service'])->findOrFail($orderId);
            } else {
                $this->selectedOrder = ToolOrder::with(['user', 'tool'])->findOrFail($orderId);
            }
            
            $this->selectedOrder->order_type = $orderType;
            $this->showDetailModal = true;

        } catch (\Exception $e) {
            session()->flash('error', 'Order not found.');
        }
    }

    public function closeDetailModal()
    {
        $this->showDetailModal = false;
        $this->selectedOrder = null;
    }

    public function updateOrderStatus($orderId, $orderType, $newStatus)
    {
        try {
            if ($orderType === 'view') {
                $order = ViewOrder::findOrFail($orderId);
            } else {
                $order = ToolOrder::findOrFail($orderId);
            }

            $order->update(['status' => $newStatus]);
            
            // Update transaction if exists
            $transaction = Transaction::where('order_type', $orderType)
                                   ->where('order_id', $orderId)
                                   ->first();
            
            if ($transaction) {
                $transactionStatus = match($newStatus) {
                    'COMPLETED' => 'COMPLETED',
                    'CANCELLED', 'REFUNDED' => 'FAILED',
                    default => 'PENDING'
                };
                $transaction->update(['status' => $transactionStatus]);
            }

            session()->flash('success', 'Order status updated successfully!');
            $this->closeDetailModal();

        } catch (\Exception $e) {
            Log::error('Error updating order status: ' . $e->getMessage());
            session()->flash('error', 'Error updating order status.');
        }
    }

    public function refundOrder($orderId, $orderType)
    {
        try {
            if ($orderType === 'view') {
                $order = ViewOrder::findOrFail($orderId);
            } else {
                $order = ToolOrder::findOrFail($orderId);
            }

            // Update order status
            $order->update(['status' => 'REFUNDED']);
            
            // Update transaction
            $transaction = Transaction::where('order_type', $orderType)
                                   ->where('order_id', $orderId)
                                   ->first();
            
            if ($transaction) {
                $transaction->update(['status' => 'REFUNDED']);
                
                // Add money back to user balance
                $user = $order->user;
                $user->increment('balance', $transaction->amount);
            }

            session()->flash('success', 'Order refunded successfully!');
            $this->closeDetailModal();

        } catch (\Exception $e) {
            Log::error('Error refunding order: ' . $e->getMessage());
            session()->flash('error', 'Error processing refund.');
        }
    }

    public function render()
    {
        // Get all orders with unified structure
        $viewOrders = ViewOrder::with(['user', 'service'])
            ->selectRaw("id, user_id, service_id as item_id, quantity, total_amount, status, created_at, 'view' as order_type")
            ->when($this->typeFilter === 'view' || $this->typeFilter === 'all', function($query) {
                return $query;
            }, function($query) {
                return $query->whereRaw('1 = 0'); // Exclude if not selected
            });

        $toolOrders = ToolOrder::with(['user', 'tool'])
            ->selectRaw("id, user_id, tool_id as item_id, 1 as quantity, amount as total_amount, status, created_at, 'tool' as order_type")
            ->when($this->typeFilter === 'tool' || $this->typeFilter === 'all', function($query) {
                return $query;
            }, function($query) {
                return $query->whereRaw('1 = 0'); // Exclude if not selected
            });

        // Apply search filter
        if ($this->search) {
            $viewOrders->whereHas('user', function($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('email', 'like', '%' . $this->search . '%');
            });
            
            $toolOrders->whereHas('user', function($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('email', 'like', '%' . $this->search . '%');
            });
        }

        // Apply status filter
        if ($this->statusFilter !== 'all') {
            $viewOrders->where('status', $this->statusFilter);
            $toolOrders->where('status', $this->statusFilter);
        }

        // Apply date filter
        switch ($this->dateFilter) {
            case 'today':
                $viewOrders->whereDate('created_at', today());
                $toolOrders->whereDate('created_at', today());
                break;
            case 'week':
                $viewOrders->where('created_at', '>=', now()->subWeek());
                $toolOrders->where('created_at', '>=', now()->subWeek());
                break;
            case 'month':
                $viewOrders->where('created_at', '>=', now()->subMonth());
                $toolOrders->where('created_at', '>=', now()->subMonth());
                break;
        }

        // Union and paginate
        $orders = $viewOrders->union($toolOrders)
                           ->orderBy('created_at', 'desc')
                           ->paginate(15);

        // Load relationships manually for unified display
        foreach ($orders as $order) {
            if ($order->order_type === 'view') {
                $viewOrder = ViewOrder::with(['user', 'service'])->find($order->id);
                $order->user = $viewOrder->user;
                $order->item = $viewOrder->service;
                $order->item_name = $viewOrder->service->name ?? 'Unknown Service';
            } else {
                $toolOrder = ToolOrder::with(['user', 'tool'])->find($order->id);
                $order->user = $toolOrder->user;
                $order->item = $toolOrder->tool;
                $order->item_name = $toolOrder->tool->name ?? 'Unknown Tool';
            }
        }

        // Get statistics
        $stats = [
            'total_orders' => ViewOrder::count() + ToolOrder::count(),
            'pending_orders' => ViewOrder::where('status', 'PENDING')->count() + ToolOrder::where('status', 'PENDING')->count(),
            'completed_orders' => ViewOrder::where('status', 'COMPLETED')->count() + ToolOrder::where('status', 'COMPLETED')->count(),
            'total_revenue' => ViewOrder::where('status', 'COMPLETED')->sum('total_amount') + ToolOrder::where('status', 'COMPLETED')->sum('amount'),
            'view_orders' => ViewOrder::count(),
            'tool_orders' => ToolOrder::count(),
        ];

        return view('livewire.admin.order-manager', [
            'orders' => $orders,
            'stats' => $stats
        ]);
    }
}
