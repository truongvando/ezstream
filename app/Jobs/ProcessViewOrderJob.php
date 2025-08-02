<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\ViewOrder;
use App\Services\JustAnotherPanelService;
use Illuminate\Support\Facades\Log;

class ProcessViewOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $viewOrder;

    /**
     * Create a new job instance.
     */
    public function __construct(ViewOrder $viewOrder)
    {
        $this->viewOrder = $viewOrder;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Processing view order', ['order_id' => $this->viewOrder->id]);

        try {
            // Check if order is still pending
            if ($this->viewOrder->status !== 'PENDING') {
                Log::info('View order is not pending, skipping', [
                    'order_id' => $this->viewOrder->id,
                    'status' => $this->viewOrder->status
                ]);
                return;
            }

            // Update status to processing
            $this->viewOrder->update(['status' => 'PROCESSING']);

            $japService = new JustAnotherPanelService();

            // Prepare order data
            $orderData = [
                'service_id' => $this->viewOrder->apiService->service_id,
                'link' => $this->viewOrder->link,
                'quantity' => $this->viewOrder->quantity
            ];

            Log::info('Sending order to JAP API', [
                'order_id' => $this->viewOrder->id,
                'order_data' => $orderData
            ]);

            // Create order via API
            $result = $japService->createOrder($orderData);

            if ($result['success']) {
                // Order created successfully
                $apiResponse = $result['data'];
                
                $this->viewOrder->update([
                    'status' => 'COMPLETED',
                    'api_order_id' => $apiResponse['order'] ?? null,
                    'api_response' => $apiResponse
                ]);

                Log::info('View order processed successfully', [
                    'order_id' => $this->viewOrder->id,
                    'api_order_id' => $apiResponse['order'] ?? null
                ]);

                // Send notification to user (if needed)
                $this->notifyUser('success');

            } else {
                // Order failed
                $this->viewOrder->update([
                    'status' => 'FAILED',
                    'api_response' => [
                        'error' => $result['message'],
                        'timestamp' => now()->toISOString()
                    ]
                ]);

                Log::error('View order failed', [
                    'order_id' => $this->viewOrder->id,
                    'error' => $result['message']
                ]);

                // Send notification to user
                $this->notifyUser('failed', $result['message']);
            }

        } catch (\Exception $e) {
            // Handle exceptions
            $this->viewOrder->update([
                'status' => 'FAILED',
                'api_response' => [
                    'error' => 'Exception: ' . $e->getMessage(),
                    'timestamp' => now()->toISOString()
                ]
            ]);

            Log::error('Exception while processing view order', [
                'order_id' => $this->viewOrder->id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Send notification to user
            $this->notifyUser('failed', 'System error occurred');

            // Re-throw exception to mark job as failed
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessViewOrderJob failed', [
            'order_id' => $this->viewOrder->id,
            'exception' => $exception->getMessage()
        ]);

        // Update order status to failed
        $this->viewOrder->update([
            'status' => 'FAILED',
            'api_response' => [
                'error' => 'Job failed: ' . $exception->getMessage(),
                'timestamp' => now()->toISOString()
            ]
        ]);

        // Notify user of failure
        $this->notifyUser('failed', 'Processing failed');
    }

    /**
     * Notify user about order status
     */
    private function notifyUser($status, $message = null)
    {
        try {
            $user = $this->viewOrder->user;
            
            // You can implement notification logic here
            // For example: email, SMS, push notification, etc.
            
            Log::info('User notification sent', [
                'user_id' => $user->id,
                'order_id' => $this->viewOrder->id,
                'status' => $status,
                'message' => $message
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send user notification', [
                'order_id' => $this->viewOrder->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
