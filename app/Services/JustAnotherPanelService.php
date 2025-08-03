<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class JustAnotherPanelService
{
    private $apiUrl = 'https://justanotherpanel.com/api/v2';
    private $apiKey;

    public function __construct()
    {
        $this->apiKey = env('JAP_API_KEY') ?? setting('jap_api_key');
    }

    /**
     * Get all services from Just Another Panel API
     */
    public function getServices()
    {
        try {
            $response = Http::timeout(30)->post($this->apiUrl, [
                'key' => $this->apiKey,
                'action' => 'services'
            ]);

            if (!$response->successful()) {
                Log::error('JAP API Error: Failed to fetch services', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                return ['success' => false, 'message' => 'API request failed'];
            }

            $data = $response->json();
            
            if (!$data || !is_array($data)) {
                Log::error('JAP API Error: Invalid response format', [
                    'response' => $response->body()
                ]);
                return ['success' => false, 'message' => 'Invalid response format'];
            }

            Log::info('JAP API: Successfully fetched services', [
                'count' => count($data)
            ]);

            return ['success' => true, 'data' => $data];

        } catch (Exception $e) {
            Log::error('JAP API Exception: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Sync services from API to database
     */
    public function syncServices()
    {
        try {
            $result = $this->getServices();

            if (!$result['success']) {
                return ['success' => false, 'message' => $result['message']];
            }

            $services = $result['data'];
            $synced = 0;
            $created = 0;
            $updated = 0;

            foreach ($services as $serviceData) {
                // Convert array to object if needed
                if (is_array($serviceData)) {
                    $serviceData = (object) $serviceData;
                }

                // Find or create service (using ApiService model)
                $service = \App\Models\ApiService::updateOrCreate(
                    ['service_id' => $serviceData->service],
                    [
                        'name' => $serviceData->name,
                        'type' => $serviceData->type ?? 'Default',
                        'category' => $serviceData->category,
                        'rate' => (float) $serviceData->rate,
                        'min_quantity' => (int) $serviceData->min,
                        'max_quantity' => (int) $serviceData->max,
                        'refill' => $serviceData->refill ?? false,
                        'cancel' => $serviceData->cancel ?? false,
                        'is_active' => true,
                        'markup_percentage' => 20, // Default markup
                    ]
                );

                if ($service->wasRecentlyCreated) {
                    $created++;
                } else {
                    $updated++;
                }
                $synced++;
            }

            Log::info('JAP Services Sync Completed', [
                'synced' => $synced,
                'created' => $created,
                'updated' => $updated
            ]);

            return [
                'success' => true,
                'synced' => $synced,
                'created' => $created,
                'updated' => $updated
            ];

        } catch (Exception $e) {
            Log::error('JAP Sync Error: Exception occurred', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ['success' => false, 'message' => 'Sync failed: ' . $e->getMessage()];
        }
    }

    /**
     * Create an order on Just Another Panel
     */
    public function createOrder($data)
    {
        try {
            $response = Http::timeout(30)->post($this->apiUrl, [
                'key' => $this->apiKey,
                'action' => 'add',
                'service' => $data['service_id'],
                'link' => $data['link'],
                'quantity' => $data['quantity']
            ]);

            if (!$response->successful()) {
                Log::error('JAP API Error: Failed to create order', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                    'request_data' => $data
                ]);
                return ['success' => false, 'message' => 'API request failed'];
            }

            $responseData = $response->json();

            if (!$responseData) {
                Log::error('JAP API Error: Invalid response format for order', [
                    'response' => $response->body()
                ]);
                return ['success' => false, 'message' => 'Invalid response format'];
            }

            // Check if order was created successfully
            if (isset($responseData['order'])) {
                Log::info('JAP API: Order created successfully', [
                    'order_id' => $responseData['order'],
                    'request_data' => $data
                ]);
                return ['success' => true, 'data' => $responseData];
            } else {
                Log::error('JAP API Error: Order creation failed', [
                    'response' => $responseData,
                    'request_data' => $data
                ]);
                return ['success' => false, 'message' => $responseData['error'] ?? 'Order creation failed'];
            }

        } catch (Exception $e) {
            Log::error('JAP API Exception during order creation: ' . $e->getMessage(), [
                'request_data' => $data
            ]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get order status from Just Another Panel
     */
    public function getOrderStatus($orderId)
    {
        try {
            $response = Http::timeout(30)->post($this->apiUrl, [
                'key' => $this->apiKey,
                'action' => 'status',
                'order' => $orderId
            ]);

            if (!$response->successful()) {
                Log::error('JAP API Error: Failed to get order status', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                    'order_id' => $orderId
                ]);
                return ['success' => false, 'message' => 'API request failed'];
            }

            $data = $response->json();

            if (!$data) {
                Log::error('JAP API Error: Invalid response format for order status', [
                    'response' => $response->body()
                ]);
                return ['success' => false, 'message' => 'Invalid response format'];
            }

            return ['success' => true, 'data' => $data];

        } catch (Exception $e) {
            Log::error('JAP API Exception during status check: ' . $e->getMessage(), [
                'order_id' => $orderId
            ]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get account balance from Just Another Panel
     */
    public function getBalance()
    {
        try {
            $response = Http::timeout(30)->post($this->apiUrl, [
                'key' => $this->apiKey,
                'action' => 'balance'
            ]);

            if (!$response->successful()) {
                Log::error('JAP API Error: Failed to get balance', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                return ['success' => false, 'message' => 'API request failed'];
            }

            $data = $response->json();

            if (!$data || !isset($data['balance'])) {
                Log::error('JAP API Error: Invalid balance response format', [
                    'response' => $response->body()
                ]);
                return ['success' => false, 'message' => 'Invalid response format'];
            }

            return ['success' => true, 'balance' => $data['balance']];

        } catch (Exception $e) {
            Log::error('JAP API Exception during balance check: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Test API connection
     */
    public function testConnection()
    {
        if (!$this->apiKey) {
            return ['success' => false, 'message' => 'API key not configured'];
        }

        $balanceResult = $this->getBalance();
        
        if ($balanceResult['success']) {
            return ['success' => true, 'message' => 'Connection successful', 'balance' => $balanceResult['balance']];
        } else {
            return $balanceResult;
        }
    }
}
