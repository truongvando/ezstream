<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

class RedisMemoryManager
{
    private const MAX_HEARTBEAT_AGE = 3600; // 1 hour
    private const MAX_COMMAND_HISTORY = 1000; // Keep last 1000 commands per VPS
    private const MAX_AGENT_STATE_AGE = 1800; // 30 minutes

    /**
     * Cleanup old Redis data to prevent memory overflow
     */
    public static function cleanup(): void
    {
        Log::info("🧹 [RedisMemory] Starting Redis memory cleanup");

        $cleaned = [
            'heartbeats' => self::cleanupOldHeartbeats(),
            'commands' => self::cleanupOldCommands(),
            'agent_states' => self::cleanupOldAgentStates(),
            'locks' => self::cleanupExpiredLocks(),
            'temp_data' => self::cleanupTempData()
        ];

        $totalCleaned = array_sum($cleaned);
        Log::info("✅ [RedisMemory] Cleanup completed", $cleaned + ['total' => $totalCleaned]);
    }

    /**
     * Cleanup old heartbeat data
     */
    private static function cleanupOldHeartbeats(): int
    {
        $pattern = 'heartbeat:*';
        $keys = Redis::keys($pattern);
        $cleaned = 0;

        foreach ($keys as $key) {
            $ttl = Redis::ttl($key);
            
            // If key has no TTL or TTL is too long, set appropriate TTL
            if ($ttl === -1 || $ttl > self::MAX_HEARTBEAT_AGE) {
                Redis::expire($key, self::MAX_HEARTBEAT_AGE);
                $cleaned++;
            }
        }

        return $cleaned;
    }

    /**
     * Cleanup old command history
     */
    private static function cleanupOldCommands(): int
    {
        $pattern = 'command_history:*';
        $keys = Redis::keys($pattern);
        $cleaned = 0;

        foreach ($keys as $key) {
            $listLength = Redis::llen($key);
            
            if ($listLength > self::MAX_COMMAND_HISTORY) {
                // Keep only the latest commands
                $toRemove = $listLength - self::MAX_COMMAND_HISTORY;
                Redis::ltrim($key, $toRemove, -1);
                $cleaned += $toRemove;
            }

            // Set TTL if not exists
            if (Redis::ttl($key) === -1) {
                Redis::expire($key, self::MAX_HEARTBEAT_AGE);
            }
        }

        return $cleaned;
    }

    /**
     * Cleanup old agent states
     */
    private static function cleanupOldAgentStates(): int
    {
        $pattern = 'agent_state:*';
        $keys = Redis::keys($pattern);
        $cleaned = 0;

        foreach ($keys as $key) {
            $stateData = Redis::get($key);
            if (!$stateData) {
                Redis::del($key);
                $cleaned++;
                continue;
            }

            $state = json_decode($stateData, true);
            if (!$state || !isset($state['last_heartbeat'])) {
                Redis::del($key);
                $cleaned++;
                continue;
            }

            $lastHeartbeat = \Carbon\Carbon::parse($state['last_heartbeat']);
            $ageInSeconds = $lastHeartbeat->diffInSeconds(now());

            if ($ageInSeconds > self::MAX_AGENT_STATE_AGE) {
                Redis::del($key);
                $cleaned++;
            }
        }

        return $cleaned;
    }

    /**
     * Cleanup expired distributed locks
     */
    private static function cleanupExpiredLocks(): int
    {
        $pattern = 'lock:*';
        $keys = Redis::keys($pattern);
        $cleaned = 0;

        foreach ($keys as $key) {
            $ttl = Redis::ttl($key);
            
            // If lock is expired (TTL = -2) or has no TTL but exists
            if ($ttl === -2) {
                Redis::del($key);
                $cleaned++;
            } elseif ($ttl === -1) {
                // Lock without TTL - force expire in 5 minutes
                Redis::expire($key, 300);
            }
        }

        return $cleaned;
    }

    /**
     * Cleanup temporary data
     */
    private static function cleanupTempData(): int
    {
        $patterns = [
            'temp:*',
            'cache:*',
            'session:*',
            'queue:*'
        ];
        
        $cleaned = 0;

        foreach ($patterns as $pattern) {
            $keys = Redis::keys($pattern);
            
            foreach ($keys as $key) {
                $ttl = Redis::ttl($key);
                
                // If temp data has no TTL, set 1 hour expiry
                if ($ttl === -1) {
                    Redis::expire($key, 3600);
                    $cleaned++;
                }
            }
        }

        return $cleaned;
    }

    /**
     * Get Redis memory usage statistics
     */
    public static function getMemoryStats(): array
    {
        $info = Redis::info('memory');
        
        return [
            'used_memory' => $info['used_memory'] ?? 0,
            'used_memory_human' => $info['used_memory_human'] ?? '0B',
            'used_memory_peak' => $info['used_memory_peak'] ?? 0,
            'used_memory_peak_human' => $info['used_memory_peak_human'] ?? '0B',
            'total_system_memory' => $info['total_system_memory'] ?? 0,
            'total_system_memory_human' => $info['total_system_memory_human'] ?? '0B',
            'memory_usage_percentage' => isset($info['used_memory'], $info['total_system_memory']) 
                ? round(($info['used_memory'] / $info['total_system_memory']) * 100, 2) 
                : 0
        ];
    }

    /**
     * Get key count by pattern
     */
    public static function getKeyStats(): array
    {
        $patterns = [
            'agent_state' => 'agent_state:*',
            'heartbeat' => 'heartbeat:*',
            'locks' => 'lock:*',
            'commands' => 'command_history:*',
            'temp' => 'temp:*'
        ];

        $stats = [];
        foreach ($patterns as $name => $pattern) {
            $keys = Redis::keys($pattern);
            $stats[$name] = count($keys);
        }

        return $stats;
    }

    /**
     * Force cleanup if memory usage is too high
     */
    public static function emergencyCleanup(): void
    {
        $stats = self::getMemoryStats();
        
        if ($stats['memory_usage_percentage'] > 80) {
            Log::warning("🚨 [RedisMemory] High memory usage detected: {$stats['memory_usage_percentage']}%");
            
            // Aggressive cleanup
            self::cleanup();
            
            // If still high, remove old temp data
            if ($stats['memory_usage_percentage'] > 90) {
                Log::error("🚨 [RedisMemory] Critical memory usage, performing emergency cleanup");
                
                $patterns = ['temp:*', 'cache:*'];
                foreach ($patterns as $pattern) {
                    $keys = Redis::keys($pattern);
                    foreach ($keys as $key) {
                        Redis::del($key);
                    }
                }
            }
        }
    }

    /**
     * Set TTL for keys without expiration
     */
    public static function setDefaultTTLs(): void
    {
        $keyConfigs = [
            'agent_state:*' => 1800,    // 30 minutes
            'heartbeat:*' => 3600,      // 1 hour
            'command_history:*' => 7200, // 2 hours
            'temp:*' => 3600,           // 1 hour
        ];

        foreach ($keyConfigs as $pattern => $ttl) {
            $keys = Redis::keys($pattern);
            foreach ($keys as $key) {
                if (Redis::ttl($key) === -1) {
                    Redis::expire($key, $ttl);
                }
            }
        }
    }
}
