<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExchangeRateService
{
    private const CACHE_KEY = 'usd_to_vnd_rate';
    private const CACHE_DURATION = 3600; // 1 hour

    /**
     * Get current USD to VND exchange rate
     */
    public function getUsdToVndRate(): float
    {
        // Try to get from cache first
        $cachedRate = Cache::get(self::CACHE_KEY);
        if ($cachedRate) {
            return $cachedRate;
        }

        // Try to fetch from API
        $apiRate = $this->fetchFromApi();
        if ($apiRate) {
            Cache::put(self::CACHE_KEY, $apiRate, self::CACHE_DURATION);
            return $apiRate;
        }

        // Fallback to config
        $configRate = config('payment.usd_to_vnd_rate', 24000);
        Log::warning('Using fallback exchange rate from config', ['rate' => $configRate]);
        
        return $configRate;
    }

    /**
     * Convert USD to VND
     */
    public function convertUsdToVnd(float $usdAmount): float
    {
        $rate = $this->getUsdToVndRate();
        return $usdAmount * $rate;
    }

    /**
     * Convert VND to USD
     */
    public function convertVndToUsd(float $vndAmount): float
    {
        $rate = $this->getUsdToVndRate();
        return $vndAmount / $rate;
    }

    /**
     * Fetch exchange rate from external API
     */
    private function fetchFromApi(): ?float
    {
        try {
            // Try APIs in priority order - Vietcombank first (most accurate for VN)
            $apis = [
                'vietcombank' => function() {
                    // Vietcombank official API - most accurate for Vietnam
                    $response = Http::timeout(15)->get('https://portal.vietcombank.com.vn/Usercontrols/TVPortal.TyGia/pXML.aspx');
                    if ($response->successful()) {
                        $xml = simplexml_load_string($response->body());
                        if ($xml) {
                            foreach ($xml->Exrate as $rate) {
                                if ((string)$rate['CurrencyCode'] === 'USD') {
                                    $sellRate = (string)$rate['Sell'];
                                    return floatval(str_replace(',', '', $sellRate));
                                }
                            }
                        }
                    }
                    return null;
                },
                'exchangerate-api' => function() {
                    $response = Http::timeout(10)->get('https://api.exchangerate-api.com/v4/latest/USD');
                    if ($response->successful()) {
                        $data = $response->json();
                        return $data['rates']['VND'] ?? null;
                    }
                    return null;
                },
                'fixer' => function() {
                    $apiKey = config('services.fixer.api_key');
                    if (!$apiKey) return null;

                    $response = Http::timeout(10)->get("https://api.fixer.io/latest", [
                        'access_key' => $apiKey,
                        'base' => 'USD',
                        'symbols' => 'VND'
                    ]);

                    if ($response->successful()) {
                        $data = $response->json();
                        return $data['rates']['VND'] ?? null;
                    }
                    return null;
                }
            ];

            foreach ($apis as $apiName => $apiFunction) {
                try {
                    $rate = $apiFunction();
                    if ($rate && $rate > 0) {
                        Log::info("Exchange rate fetched from {$apiName}", ['rate' => $rate]);
                        return $rate;
                    }
                } catch (\Exception $e) {
                    Log::warning("Failed to fetch rate from {$apiName}: " . $e->getMessage());
                    continue;
                }
            }

        } catch (\Exception $e) {
            Log::error('Error fetching exchange rate from APIs: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Update exchange rate manually (for admin)
     */
    public function updateRate(float $rate): bool
    {
        try {
            if ($rate <= 0) {
                throw new \InvalidArgumentException('Exchange rate must be positive');
            }

            Cache::put(self::CACHE_KEY, $rate, self::CACHE_DURATION * 24); // Cache for 24 hours
            
            Log::info('Exchange rate updated manually', [
                'rate' => $rate,
                'updated_by' => auth()->id()
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Error updating exchange rate: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get rate info with metadata
     */
    public function getRateInfo(): array
    {
        $rate = $this->getUsdToVndRate();
        $cacheKey = self::CACHE_KEY;
        $cachedAt = Cache::get($cacheKey . '_updated_at');
        
        return [
            'rate' => $rate,
            'cached_at' => $cachedAt,
            'source' => Cache::has($cacheKey) ? 'cache' : 'config',
            'last_updated' => $cachedAt ? \Carbon\Carbon::parse($cachedAt)->diffForHumans() : 'Unknown'
        ];
    }

    /**
     * Clear rate cache (force refresh)
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::CACHE_KEY . '_updated_at');
        Log::info('Exchange rate cache cleared');
    }
}
