<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class JapApiService
{
    private $apiUrl;
    private $apiKey;

    public function __construct()
    {
        $this->apiUrl = 'https://justanotherpanel.com/api/v2';
        $this->apiKey = env('JAP_API_KEY');
    }

    /**
     * Get all services from JAP API
     */
    public function getAllServices()
    {
        try {
            $response = Http::timeout(30)->post($this->apiUrl, [
                'key' => $this->apiKey,
                'action' => 'services'
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('JAP API Error', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return [];
        } catch (\Exception $e) {
            Log::error('JAP API Exception', [
                'message' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get YouTube services only, grouped by categories
     */
    public function getYouTubeServices()
    {
        $cacheKey = 'jap_youtube_services';
        
        return Cache::remember($cacheKey, 3600, function () {
            $allServices = $this->getAllServices();
            
            $youtubeServices = collect($allServices)->filter(function ($service) {
                return isset($service['category']) && 
                       stripos($service['category'], 'youtube') !== false;
            });

            return $this->categorizeYouTubeServices($youtubeServices);
        });
    }

    /**
     * Categorize YouTube services into 3-layer hierarchy
     */
    private function categorizeYouTubeServices($services)
    {
        // First group by original category (Layer 2 - Sub-categories)
        $groupedByCategory = [];
        foreach ($services as $service) {
            $category = $service['category'];
            if (!isset($groupedByCategory[$category])) {
                $groupedByCategory[$category] = [];
            }
            $groupedByCategory[$category][] = $service;
        }

        // Then organize into main categories (Layer 1)
        $mainCategories = [
            'VIEWS' => [],
            'SUBSCRIBERS' => [],
            'LIVESTREAM' => [],
            'LIKES' => [],
            'COMMENTS' => []
        ];

        foreach ($groupedByCategory as $originalCategory => $categoryServices) {
            // Determine main category
            $mainCategory = $this->determineMainCategory($originalCategory);

            if ($mainCategory) {
                $mainCategories[$mainCategory][$originalCategory] = $categoryServices;
            }
        }

        return $mainCategories;
    }

    /**
     * Determine main category from original category name
     */
    private function determineMainCategory($categoryName)
    {
        $category = strtolower($categoryName);

        // Views categories (but not live stream)
        if (stripos($category, 'views') !== false &&
            stripos($category, 'live') === false) {
            return 'VIEWS';
        }
        // Shorts also go to VIEWS
        elseif (stripos($category, 'shorts') !== false) {
            return 'VIEWS';
        }
        // Subscribers
        elseif (stripos($category, 'subscriber') !== false) {
            return 'SUBSCRIBERS';
        }
        // Live Stream
        elseif (stripos($category, 'live') !== false ||
                stripos($category, 'stream') !== false ||
                stripos($category, 'premiere') !== false) {
            return 'LIVESTREAM';
        }
        // Likes/Dislikes/Shares
        elseif (stripos($category, 'like') !== false ||
                stripos($category, 'dislike') !== false ||
                stripos($category, 'share') !== false) {
            return 'LIKES';
        }
        // Comments
        elseif (stripos($category, 'comment') !== false) {
            return 'COMMENTS';
        }
        // Watchtime goes to VIEWS
        elseif (stripos($category, 'watchtime') !== false) {
            return 'VIEWS';
        }

        return null; // Unknown category
    }

    /**
     * Place an order via JAP API
     */
    public function placeOrder($serviceId, $link, $quantity)
    {
        try {
            $response = Http::timeout(30)->post($this->apiUrl, [
                'key' => $this->apiKey,
                'action' => 'add',
                'service' => $serviceId,
                'link' => $link,
                'quantity' => $quantity
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('JAP Order API Error', [
                'status' => $response->status(),
                'body' => $response->body(),
                'service_id' => $serviceId,
                'link' => $link,
                'quantity' => $quantity
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('JAP Order API Exception', [
                'message' => $e->getMessage(),
                'service_id' => $serviceId,
                'link' => $link,
                'quantity' => $quantity
            ]);
            return null;
        }
    }

    /**
     * Check order status
     */
    public function getOrderStatus($orderId)
    {
        try {
            $response = Http::timeout(30)->post($this->apiUrl, [
                'key' => $this->apiKey,
                'action' => 'status',
                'order' => $orderId
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            return null;
        } catch (\Exception $e) {
            Log::error('JAP Status API Exception', [
                'message' => $e->getMessage(),
                'order_id' => $orderId
            ]);
            return null;
        }
    }

    /**
     * Check multiple orders status
     */
    public function getMultipleOrderStatus($orderIds)
    {
        try {
            $orderIdsString = is_array($orderIds) ? implode(',', $orderIds) : $orderIds;

            $response = Http::timeout(30)->post($this->apiUrl, [
                'key' => $this->apiKey,
                'action' => 'status',
                'orders' => $orderIdsString
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            return null;
        } catch (\Exception $e) {
            Log::error('JAP Multiple Status API Exception', [
                'message' => $e->getMessage(),
                'order_ids' => $orderIds
            ]);
            return null;
        }
    }

    /**
     * Create refill for an order
     */
    public function createRefill($orderId)
    {
        try {
            $response = Http::timeout(30)->post($this->apiUrl, [
                'key' => $this->apiKey,
                'action' => 'refill',
                'order' => $orderId
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            return null;
        } catch (\Exception $e) {
            Log::error('JAP Refill API Exception', [
                'message' => $e->getMessage(),
                'order_id' => $orderId
            ]);
            return null;
        }
    }

    /**
     * Get user balance
     */
    public function getBalance()
    {
        try {
            $response = Http::timeout(30)->post($this->apiUrl, [
                'key' => $this->apiKey,
                'action' => 'balance'
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            return null;
        } catch (\Exception $e) {
            Log::error('JAP Balance API Exception', [
                'message' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Cancel orders
     */
    public function cancelOrders($orderIds)
    {
        try {
            $orderIdsString = is_array($orderIds) ? implode(',', $orderIds) : $orderIds;

            $response = Http::timeout(30)->post($this->apiUrl, [
                'key' => $this->apiKey,
                'action' => 'cancel',
                'orders' => $orderIdsString
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('JAP Cancel API Error', [
                'status' => $response->status(),
                'body' => $response->body(),
                'order_ids' => $orderIdsString
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('JAP Cancel API Exception', [
                'message' => $e->getMessage(),
                'order_ids' => $orderIds
            ]);
            return null;
        }
    }

    /**
     * Get refill status
     */
    public function getRefillStatus($refillIds)
    {
        try {
            $refillIdsString = is_array($refillIds) ? implode(',', $refillIds) : $refillIds;

            $response = Http::timeout(30)->post($this->apiUrl, [
                'key' => $this->apiKey,
                'action' => 'refill_status',
                'refills' => $refillIdsString
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            return null;
        } catch (\Exception $e) {
            Log::error('JAP Refill Status API Exception', [
                'message' => $e->getMessage(),
                'refill_ids' => $refillIds
            ]);
            return null;
        }
    }

    /**
     * Clear cache
     */
    public function clearCache()
    {
        Cache::forget('jap_youtube_services');
    }
}
