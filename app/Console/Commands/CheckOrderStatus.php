<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ViewOrder;
use App\Services\JapApiService;
use Illuminate\Support\Facades\Log;

class CheckOrderStatus extends Command
{
    protected $signature = 'orders:check-status';
    protected $description = 'Check order status with JAP API and update local records';

    public function handle()
    {
        $this->info('🔍 Checking order status with JAP API...');

        // Get orders that need status check
        $orders = ViewOrder::whereNotNull('api_order_id')
                          ->whereIn('status', ['PROCESSING', 'PENDING_FUNDS', 'PENDING_RETRY'])
                          ->where('updated_at', '>', now()->subHours(24)) // Only check recent orders
                          ->get();

        if ($orders->isEmpty()) {
            $this->info('No orders to check.');
            return;
        }

        $japApiService = app(JapApiService::class);
        $updated = 0;

        foreach ($orders as $order) {
            try {
                $this->line("Checking order #{$order->id} (JAP: {$order->api_order_id})");

                $statusResult = $japApiService->getOrderStatus($order->api_order_id);

                if ($statusResult && isset($statusResult['status'])) {
                    $japStatus = $statusResult['status'];
                    $newStatus = $this->mapJapStatusToLocal($japStatus);

                    if ($newStatus && $newStatus !== $order->status) {
                        $order->update([
                            'status' => $newStatus,
                            'api_response' => array_merge($order->api_response ?? [], [
                                'jap_status' => $japStatus,
                                'last_checked' => now()->toISOString(),
                                'status_updated' => true
                            ])
                        ]);

                        $this->info("  → Updated to {$newStatus} (JAP: {$japStatus})");
                        $updated++;

                        Log::info('Order status updated', [
                            'order_id' => $order->id,
                            'old_status' => $order->status,
                            'new_status' => $newStatus,
                            'jap_status' => $japStatus
                        ]);
                    } else {
                        $this->line("  → No change ({$japStatus})");
                    }
                } else {
                    $this->warn("  → Failed to get status");
                }

            } catch (\Exception $e) {
                $this->error("  → Error: " . $e->getMessage());
                Log::error('Order status check failed', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->info("✅ Checked {$orders->count()} orders, updated {$updated}");
    }

    private function mapJapStatusToLocal($japStatus)
    {
        return match(strtolower($japStatus)) {
            'pending', 'in progress' => 'PROCESSING',
            'completed' => 'COMPLETED',
            'partial' => 'COMPLETED', // Partial is still completed
            'cancelled', 'canceled' => 'CANCELLED',
            'refunded' => 'REFUNDED',
            default => null // Don't update if unknown status
        };
    }
}
