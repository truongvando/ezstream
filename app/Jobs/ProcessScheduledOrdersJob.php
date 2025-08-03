<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\ScheduledOrder;
use App\Models\ViewOrder;
use App\Models\Transaction;
use App\Services\JapApiService;
use Illuminate\Support\Facades\Log;

class ProcessScheduledOrdersJob implements ShouldQueue
{
    use Queueable;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $scheduledOrders = ScheduledOrder::readyToExecute()->get();

        foreach ($scheduledOrders as $scheduledOrder) {
            try {
                $this->processScheduledOrder($scheduledOrder);
            } catch (\Exception $e) {
                Log::error('Error processing scheduled order', [
                    'scheduled_order_id' => $scheduledOrder->id,
                    'error' => $e->getMessage()
                ]);

                $scheduledOrder->update([
                    'status' => 'FAILED',
                    'last_order_response' => ['error' => $e->getMessage()]
                ]);
            }
        }
    }

    private function processScheduledOrder(ScheduledOrder $scheduledOrder)
    {
        $scheduledOrder->update(['status' => 'PROCESSING']);

        $japApiService = app(JapApiService::class);

        // Place order via JAP API
        $japResult = $japApiService->placeOrder(
            $scheduledOrder->service_id,
            $scheduledOrder->link,
            $scheduledOrder->quantity
        );

        // Create view order
        $orderStatus = 'PENDING';
        $apiResponse = [];

        if ($japResult && isset($japResult['order'])) {
            $orderStatus = 'PROCESSING';
            $apiResponse = [
                'api_order_id' => $japResult['order'],
                'placed_at' => now()->toISOString()
            ];
        } elseif ($japResult && isset($japResult['error'])) {
            $error = $japResult['error'];
            if (stripos($error, 'not enough funds') !== false ||
                stripos($error, 'insufficient funds') !== false ||
                stripos($error, 'low balance') !== false) {
                $orderStatus = 'PENDING_FUNDS';
            } else {
                $orderStatus = 'FAILED';
            }
            $apiResponse = [
                'error' => $error,
                'failed_at' => now()->toISOString()
            ];
        } else {
            $orderStatus = 'PENDING_RETRY';
            $apiResponse = [
                'error' => 'API call failed',
                'failed_at' => now()->toISOString()
            ];
        }

        $viewOrder = ViewOrder::create([
            'user_id' => $scheduledOrder->user_id,
            'service_id' => $scheduledOrder->service_id,
            'api_order_id' => $apiResponse['api_order_id'] ?? null,
            'link' => $scheduledOrder->link,
            'quantity' => $scheduledOrder->quantity,
            'total_amount' => $scheduledOrder->total_amount,
            'status' => $orderStatus,
            'api_response' => $apiResponse
        ]);

        // Generate payment code
        $paymentCode = 'VIEW' . str_pad($viewOrder->id, 6, '0', STR_PAD_LEFT);

        // Create transaction
        Transaction::create([
            'user_id' => $scheduledOrder->user_id,
            'view_order_id' => $viewOrder->id,
            'payment_code' => $paymentCode,
            'amount' => $scheduledOrder->total_amount,
            'currency' => 'USD',
            'payment_gateway' => 'SCHEDULED_ORDER',
            'status' => 'COMPLETED', // Already paid from reserved balance
            'description' => "Scheduled order: {$scheduledOrder->quantity} " . ($scheduledOrder->service_data['name'] ?? 'Unknown Service')
        ]);

        // Update scheduled order
        $scheduledOrder->update([
            'last_order_response' => $apiResponse,
            'last_executed_at' => now()
        ]);

        $scheduledOrder->markExecuted();

        Log::info('Scheduled order processed', [
            'scheduled_order_id' => $scheduledOrder->id,
            'view_order_id' => $viewOrder->id,
            'status' => $orderStatus,
            'can_repeat' => $scheduledOrder->canRepeat()
        ]);
    }
}
