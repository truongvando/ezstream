<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ViewOrder;
use App\Services\JapApiService;

class ProcessOrderActions extends Command
{
    protected $signature = 'orders:process-actions {action} {order_id}';
    protected $description = 'Process order actions: refill, cancel, refund';

    public function handle()
    {
        $action = $this->argument('action');
        $orderId = $this->argument('order_id');

        $order = ViewOrder::find($orderId);
        if (!$order) {
            $this->error("Order {$orderId} not found");
            return 1;
        }

        $japService = app(JapApiService::class);

        switch ($action) {
            case 'refill':
                return $this->processRefill($order, $japService);
            case 'cancel':
                return $this->processCancel($order, $japService);
            case 'refund':
                return $this->processRefund($order);
            default:
                $this->error("Unknown action: {$action}");
                return 1;
        }
    }

    private function processRefill($order, $japService)
    {
        if (!$order->canRefill()) {
            $this->error("Order {$order->id} cannot be refilled");
            return 1;
        }

        if (!$order->api_order_id) {
            $this->error("Order {$order->id} has no API order ID");
            return 1;
        }

        $this->info("Processing refill for order {$order->id}...");

        $result = $japService->createRefill($order->api_order_id);
        
        if ($result && isset($result['refill'])) {
            $refillId = $result['refill'];
            
            // Update order with refill info
            $order->update([
                'api_response' => array_merge($order->api_response ?? [], [
                    'refill_id' => $refillId,
                    'refill_requested_at' => now()->toISOString()
                ])
            ]);

            $this->info("Refill created successfully. Refill ID: {$refillId}");
            return 0;
        } else {
            $this->error("Failed to create refill");
            return 1;
        }
    }

    private function processCancel($order, $japService)
    {
        if (!$order->canCancel()) {
            $this->error("Order {$order->id} cannot be cancelled");
            return 1;
        }

        $this->info("Processing cancel for order {$order->id}...");

        if ($order->api_order_id) {
            // Try to cancel via API first
            $result = $japService->cancelOrders($order->api_order_id);
            
            if ($result) {
                $this->info("Order cancelled via API");
            } else {
                $this->warn("API cancel failed, proceeding with local cancel");
            }
        }

        // Process refund
        if ($order->processRefund('Order cancelled by user')) {
            $this->info("Order {$order->id} cancelled and refunded successfully");
            return 0;
        } else {
            $this->error("Failed to process refund");
            return 1;
        }
    }

    private function processRefund($order)
    {
        $this->info("Processing refund for order {$order->id}...");

        if ($order->processRefund('Manual refund')) {
            $this->info("Order {$order->id} refunded successfully");
            return 0;
        } else {
            $this->error("Failed to process refund");
            return 1;
        }
    }
}
