<?php

namespace App\Exceptions;

use Illuminate\Support\Facades\Log;
use Predis\Connection\ConnectionException;

class RedisConnectionHandler
{
    private static $lastLogTime = [];
    private static $logThrottle = 60; // Only log once per minute for same error

    /**
     * Handle Redis connection errors with throttling to prevent log spam
     */
    public static function handleConnectionError(\Throwable $exception, string $context = 'Redis'): void
    {
        $errorKey = md5($exception->getMessage() . $context);
        $now = time();
        
        // Check if we've logged this error recently
        if (isset(self::$lastLogTime[$errorKey]) && 
            ($now - self::$lastLogTime[$errorKey]) < self::$logThrottle) {
            return; // Skip logging to prevent spam
        }
        
        self::$lastLogTime[$errorKey] = $now;
        
        // Log with appropriate level based on error type
        if (self::isConnectionError($exception)) {
            Log::warning("🔴 [{$context}] Redis connection error (throttled)", [
                'error' => $exception->getMessage(),
                'type' => get_class($exception),
                'throttle_key' => $errorKey,
                'next_log_after' => date('Y-m-d H:i:s', $now + self::$logThrottle)
            ]);
        } else {
            Log::error("❌ [{$context}] Redis error (throttled)", [
                'error' => $exception->getMessage(),
                'type' => get_class($exception),
                'throttle_key' => $errorKey,
            ]);
        }
    }
    
    /**
     * Check if exception is a connection-related error
     */
    public static function isConnectionError(\Throwable $exception): bool
    {
        $message = $exception->getMessage();
        $trace = $exception->getTraceAsString();
        
        // Check for specific error messages
        $isMessageError = str_contains($message, 'errno=10053') ||
               str_contains($message, 'Connection refused') ||
               str_contains($message, 'Connection reset') ||
               str_contains($message, 'Connection timed out') ||
               str_contains($message, 'fwrite(): Send of') ||
               $exception instanceof ConnectionException;

        // Check if the trace indicates a Redis-related operation
        $isTraceError = str_contains($trace, 'predis') ||
                        str_contains($trace, 'RedisQueue') ||
                        str_contains($trace, 'Redis\\Connections');

        return $isMessageError && $isTraceError;
    }
    
    /**
     * Clear throttle cache (useful for testing)
     */
    public static function clearThrottle(): void
    {
        self::$lastLogTime = [];
    }
}
