<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

class RtmpCircuitBreaker
{
    private const FAILURE_THRESHOLD = 5; // failures before opening circuit
    private const SUCCESS_THRESHOLD = 3; // successes to close circuit
    private const TIMEOUT = 300; // 5 minutes before trying again
    private const HALF_OPEN_TIMEOUT = 60; // 1 minute in half-open state

    /**
     * Check if RTMP server is available
     */
    public static function isAvailable(string $rtmpUrl): bool
    {
        $circuitKey = self::getCircuitKey($rtmpUrl);
        $circuitState = Redis::hgetall($circuitKey);

        if (empty($circuitState)) {
            // No circuit state - assume available
            return true;
        }

        $state = $circuitState['state'] ?? 'CLOSED';
        $lastFailure = (int)($circuitState['last_failure'] ?? 0);
        $failureCount = (int)($circuitState['failure_count'] ?? 0);

        switch ($state) {
            case 'CLOSED':
                // Circuit is closed - allow requests
                return true;

            case 'OPEN':
                // Circuit is open - check if timeout has passed
                if (time() - $lastFailure > self::TIMEOUT) {
                    self::setCircuitState($rtmpUrl, 'HALF_OPEN');
                    Log::info("🔄 [CircuitBreaker] Moving to HALF_OPEN state for {$rtmpUrl}");
                    return true;
                }
                return false;

            case 'HALF_OPEN':
                // Circuit is half-open - allow limited requests
                $halfOpenStart = (int)($circuitState['half_open_start'] ?? 0);
                if (time() - $halfOpenStart > self::HALF_OPEN_TIMEOUT) {
                    // Half-open timeout - back to open
                    self::setCircuitState($rtmpUrl, 'OPEN');
                    return false;
                }
                return true;

            default:
                return true;
        }
    }

    /**
     * Record successful RTMP connection
     */
    public static function recordSuccess(string $rtmpUrl): void
    {
        $circuitKey = self::getCircuitKey($rtmpUrl);
        $circuitState = Redis::hgetall($circuitKey);
        $state = $circuitState['state'] ?? 'CLOSED';
        $successCount = (int)($circuitState['success_count'] ?? 0) + 1;

        Log::debug("✅ [CircuitBreaker] Success recorded for {$rtmpUrl} (count: {$successCount})");

        if ($state === 'HALF_OPEN') {
            if ($successCount >= self::SUCCESS_THRESHOLD) {
                // Enough successes - close circuit
                self::setCircuitState($rtmpUrl, 'CLOSED');
                Log::info("✅ [CircuitBreaker] Circuit CLOSED for {$rtmpUrl} after {$successCount} successes");
            } else {
                // Update success count
                Redis::hset($circuitKey, 'success_count', $successCount);
                Redis::expire($circuitKey, 3600);
            }
        } else {
            // Reset failure count on success
            Redis::hset($circuitKey, 'failure_count', 0);
            Redis::hset($circuitKey, 'success_count', $successCount);
            Redis::expire($circuitKey, 3600);
        }
    }

    /**
     * Record failed RTMP connection
     */
    public static function recordFailure(string $rtmpUrl, string $error = ''): void
    {
        $circuitKey = self::getCircuitKey($rtmpUrl);
        $circuitState = Redis::hgetall($circuitKey);
        $failureCount = (int)($circuitState['failure_count'] ?? 0) + 1;

        Log::warning("❌ [CircuitBreaker] Failure recorded for {$rtmpUrl} (count: {$failureCount}): {$error}");

        Redis::hmset($circuitKey, [
            'failure_count' => $failureCount,
            'last_failure' => time(),
            'last_error' => $error,
            'success_count' => 0 // Reset success count
        ]);

        if ($failureCount >= self::FAILURE_THRESHOLD) {
            // Open circuit
            self::setCircuitState($rtmpUrl, 'OPEN');
            Log::error("🚨 [CircuitBreaker] Circuit OPENED for {$rtmpUrl} after {$failureCount} failures");
        }

        Redis::expire($circuitKey, 3600);
    }

    /**
     * Get circuit breaker status for RTMP server
     */
    public static function getStatus(string $rtmpUrl): array
    {
        $circuitKey = self::getCircuitKey($rtmpUrl);
        $circuitState = Redis::hgetall($circuitKey);

        if (empty($circuitState)) {
            return [
                'state' => 'CLOSED',
                'failure_count' => 0,
                'success_count' => 0,
                'available' => true
            ];
        }

        $state = $circuitState['state'] ?? 'CLOSED';
        $available = self::isAvailable($rtmpUrl);

        return [
            'state' => $state,
            'failure_count' => (int)($circuitState['failure_count'] ?? 0),
            'success_count' => (int)($circuitState['success_count'] ?? 0),
            'last_failure' => (int)($circuitState['last_failure'] ?? 0),
            'last_error' => $circuitState['last_error'] ?? '',
            'available' => $available,
            'next_retry' => $state === 'OPEN' ? 
                ((int)($circuitState['last_failure'] ?? 0) + self::TIMEOUT) : null
        ];
    }

    /**
     * Force reset circuit breaker
     */
    public static function reset(string $rtmpUrl): void
    {
        $circuitKey = self::getCircuitKey($rtmpUrl);
        Redis::del($circuitKey);
        Log::info("🔄 [CircuitBreaker] Circuit reset for {$rtmpUrl}");
    }

    /**
     * Get all circuit breaker statuses
     */
    public static function getAllStatuses(): array
    {
        $pattern = 'circuit_breaker:*';
        $keys = Redis::keys($pattern);
        $statuses = [];

        foreach ($keys as $key) {
            $rtmpUrl = str_replace('circuit_breaker:', '', $key);
            $rtmpUrl = str_replace('_', '/', $rtmpUrl);
            $rtmpUrl = str_replace(':', '://', $rtmpUrl, 1);
            
            $statuses[$rtmpUrl] = self::getStatus($rtmpUrl);
        }

        return $statuses;
    }

    /**
     * Check RTMP server health
     */
    public static function healthCheck(string $rtmpUrl): bool
    {
        try {
            // Simple TCP connection test to RTMP server
            $urlParts = parse_url($rtmpUrl);
            $host = $urlParts['host'] ?? '';
            $port = $urlParts['port'] ?? 1935;

            if (empty($host)) {
                return false;
            }

            $connection = @fsockopen($host, $port, $errno, $errstr, 5);
            
            if ($connection) {
                fclose($connection);
                self::recordSuccess($rtmpUrl);
                return true;
            } else {
                self::recordFailure($rtmpUrl, "Connection failed: {$errstr} ({$errno})");
                return false;
            }
            
        } catch (\Exception $e) {
            self::recordFailure($rtmpUrl, "Health check exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get alternative RTMP servers when primary is down
     */
    public static function getAlternativeServers(string $primaryRtmpUrl): array
    {
        // This could be configured based on your RTMP setup
        $alternatives = [];
        
        if (strpos($primaryRtmpUrl, 'youtube.com') !== false) {
            // YouTube RTMP alternatives
            $alternatives = [
                'rtmp://a.rtmp.youtube.com/live2',
                'rtmp://b.rtmp.youtube.com/live2',
                'rtmp://c.rtmp.youtube.com/live2'
            ];
        } elseif (strpos($primaryRtmpUrl, 'facebook.com') !== false) {
            // Facebook RTMP alternatives
            $alternatives = [
                'rtmps://live-api-s.facebook.com:443/rtmp',
                'rtmp://live-api.facebook.com:80/rtmp'
            ];
        }

        // Filter out unavailable alternatives
        $availableAlternatives = [];
        foreach ($alternatives as $alt) {
            if (self::isAvailable($alt)) {
                $availableAlternatives[] = $alt;
            }
        }

        return $availableAlternatives;
    }

    /**
     * Set circuit state
     */
    private static function setCircuitState(string $rtmpUrl, string $state): void
    {
        $circuitKey = self::getCircuitKey($rtmpUrl);
        
        $data = [
            'state' => $state,
            'updated_at' => time()
        ];

        if ($state === 'HALF_OPEN') {
            $data['half_open_start'] = time();
            $data['success_count'] = 0;
        } elseif ($state === 'CLOSED') {
            $data['failure_count'] = 0;
            $data['success_count'] = 0;
        }

        Redis::hmset($circuitKey, $data);
        Redis::expire($circuitKey, 3600);
    }

    /**
     * Get Redis key for circuit breaker
     */
    private static function getCircuitKey(string $rtmpUrl): string
    {
        // Convert URL to safe Redis key
        $key = str_replace(['://', '/', ':'], ['_', '_', '_'], $rtmpUrl);
        return "circuit_breaker:{$key}";
    }
}
