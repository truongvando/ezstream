<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

class DistributedLock
{
    private const DEFAULT_TTL = 30; // seconds
    private const RETRY_DELAY = 100; // milliseconds
    private const MAX_RETRIES = 50; // 5 seconds total

    /**
     * Acquire a distributed lock
     */
    public static function acquire(string $key, int $ttl = self::DEFAULT_TTL, int $maxRetries = self::MAX_RETRIES): ?string
    {
        $lockKey = "lock:{$key}";
        $lockValue = uniqid(gethostname() . '_', true);
        
        for ($i = 0; $i < $maxRetries; $i++) {
            // Try to acquire lock with SET NX EX
            $acquired = Redis::set($lockKey, $lockValue, 'EX', $ttl, 'NX');
            
            if ($acquired) {
                Log::debug("🔒 [DistributedLock] Acquired lock: {$key} (value: {$lockValue})");
                return $lockValue;
            }
            
            // Wait before retry
            usleep(self::RETRY_DELAY * 1000);
        }
        
        Log::warning("⏰ [DistributedLock] Failed to acquire lock after {$maxRetries} retries: {$key}");
        return null;
    }

    /**
     * Release a distributed lock
     */
    public static function release(string $key, string $lockValue): bool
    {
        $lockKey = "lock:{$key}";
        
        // Lua script to ensure we only release our own lock
        $script = "
            if redis.call('GET', KEYS[1]) == ARGV[1] then
                return redis.call('DEL', KEYS[1])
            else
                return 0
            end
        ";
        
        $result = Redis::eval($script, 1, $lockKey, $lockValue);
        
        if ($result) {
            Log::debug("🔓 [DistributedLock] Released lock: {$key} (value: {$lockValue})");
            return true;
        } else {
            Log::warning("⚠️ [DistributedLock] Failed to release lock (not owner): {$key}");
            return false;
        }
    }

    /**
     * Execute code with distributed lock
     */
    public static function execute(string $key, callable $callback, int $ttl = self::DEFAULT_TTL)
    {
        $lockValue = self::acquire($key, $ttl);
        
        if (!$lockValue) {
            throw new \Exception("Failed to acquire distributed lock: {$key}");
        }
        
        try {
            return $callback();
        } finally {
            self::release($key, $lockValue);
        }
    }

    /**
     * Check if lock exists
     */
    public static function exists(string $key): bool
    {
        $lockKey = "lock:{$key}";
        return Redis::exists($lockKey) > 0;
    }

    /**
     * Get lock TTL
     */
    public static function ttl(string $key): int
    {
        $lockKey = "lock:{$key}";
        return Redis::ttl($lockKey);
    }

    /**
     * Force release lock (admin only)
     */
    public static function forceRelease(string $key): bool
    {
        $lockKey = "lock:{$key}";
        $result = Redis::del($lockKey);
        
        Log::warning("🔨 [DistributedLock] Force released lock: {$key}");
        return $result > 0;
    }
}
