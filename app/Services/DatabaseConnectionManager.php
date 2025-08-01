<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

class DatabaseConnectionManager
{
    private const MAX_CONCURRENT_JOBS = 50;
    private const CONNECTION_TIMEOUT = 30; // seconds
    private const HEALTH_CHECK_INTERVAL = 60; // seconds

    /**
     * Throttle database-intensive jobs
     */
    public static function throttleJob(string $jobClass): bool
    {
        $runningJobs = self::getRunningJobCount($jobClass);
        
        if ($runningJobs >= self::MAX_CONCURRENT_JOBS) {
            Log::warning("🚦 [DBConnection] Throttling {$jobClass} - {$runningJobs} jobs already running");
            return false;
        }

        return true;
    }

    /**
     * Execute database operation with connection management
     */
    public static function executeWithManagement(callable $callback, string $connection = 'mysql')
    {
        $startTime = microtime(true);
        
        try {
            // Check connection health before operation
            self::ensureConnectionHealth($connection);
            
            // Execute with timeout
            $result = DB::connection($connection)->transaction(function() use ($callback) {
                return $callback();
            }, 3); // Max 3 retry attempts
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            Log::debug("📊 [DBConnection] Operation completed in {$duration}ms");
            
            return $result;
            
        } catch (\Illuminate\Database\QueryException $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            
            if (self::isConnectionError($e)) {
                Log::error("🔌 [DBConnection] Connection error after {$duration}ms: " . $e->getMessage());
                self::handleConnectionError($connection, $e);
                throw $e;
            } else {
                Log::error("❌ [DBConnection] Query error after {$duration}ms: " . $e->getMessage());
                throw $e;
            }
        }
    }

    /**
     * Ensure database connection is healthy
     */
    private static function ensureConnectionHealth(string $connection): void
    {
        try {
            $pdo = DB::connection($connection)->getPdo();
            
            // Simple health check query
            DB::connection($connection)->select('SELECT 1');
            
        } catch (\Exception $e) {
            Log::warning("⚠️ [DBConnection] Connection unhealthy, reconnecting: " . $e->getMessage());
            
            // Force reconnection
            DB::purge($connection);
            DB::connection($connection)->reconnect();
        }
    }

    /**
     * Check if exception is connection-related
     */
    private static function isConnectionError(\Exception $e): bool
    {
        $connectionErrors = [
            'server has gone away',
            'connection lost',
            'timeout',
            'too many connections',
            'connection refused',
            'can\'t connect'
        ];

        $message = strtolower($e->getMessage());
        
        foreach ($connectionErrors as $error) {
            if (strpos($message, $error) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Handle connection errors
     */
    private static function handleConnectionError(string $connection, \Exception $e): void
    {
        Log::error("🔌 [DBConnection] Handling connection error for {$connection}: " . $e->getMessage());

        // Purge and reconnect
        DB::purge($connection);
        
        // Wait before reconnecting
        sleep(1);
        
        try {
            DB::connection($connection)->reconnect();
            Log::info("✅ [DBConnection] Successfully reconnected to {$connection}");
        } catch (\Exception $reconnectError) {
            Log::error("❌ [DBConnection] Failed to reconnect to {$connection}: " . $reconnectError->getMessage());
        }
    }

    /**
     * Get count of running jobs for a specific class
     */
    private static function getRunningJobCount(string $jobClass): int
    {
        try {
            // This is a simplified implementation
            // In production, you might want to use Redis or a dedicated job tracking system
            return DB::table('jobs')
                ->where('payload', 'LIKE', '%' . $jobClass . '%')
                ->count();
        } catch (\Exception $e) {
            Log::warning("⚠️ [DBConnection] Failed to get job count: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Monitor database connection pool
     */
    public static function monitorConnectionPool(): array
    {
        $stats = [];
        
        try {
            // Get connection info
            $connections = config('database.connections');
            
            foreach ($connections as $name => $config) {
                if ($config['driver'] === 'mysql') {
                    $stats[$name] = self::getConnectionStats($name);
                }
            }
            
        } catch (\Exception $e) {
            Log::error("❌ [DBConnection] Failed to monitor connection pool: " . $e->getMessage());
        }

        return $stats;
    }

    /**
     * Get connection statistics
     */
    private static function getConnectionStats(string $connection): array
    {
        try {
            $pdo = DB::connection($connection)->getPdo();
            
            // Get MySQL connection info
            $processlist = DB::connection($connection)
                ->select('SHOW PROCESSLIST');
            
            $status = DB::connection($connection)
                ->select('SHOW STATUS LIKE "Threads_%"');
            
            $variables = DB::connection($connection)
                ->select('SHOW VARIABLES LIKE "max_connections"');

            $stats = [
                'connection_name' => $connection,
                'active_connections' => count($processlist),
                'max_connections' => 0,
                'threads_connected' => 0,
                'threads_running' => 0,
                'health' => 'healthy'
            ];

            // Parse status variables
            foreach ($status as $stat) {
                switch ($stat->Variable_name) {
                    case 'Threads_connected':
                        $stats['threads_connected'] = (int)$stat->Value;
                        break;
                    case 'Threads_running':
                        $stats['threads_running'] = (int)$stat->Value;
                        break;
                }
            }

            // Parse variables
            foreach ($variables as $var) {
                if ($var->Variable_name === 'max_connections') {
                    $stats['max_connections'] = (int)$var->Value;
                }
            }

            // Calculate health
            if ($stats['max_connections'] > 0) {
                $usage_percentage = ($stats['threads_connected'] / $stats['max_connections']) * 100;
                
                if ($usage_percentage > 90) {
                    $stats['health'] = 'critical';
                } elseif ($usage_percentage > 70) {
                    $stats['health'] = 'warning';
                }
                
                $stats['usage_percentage'] = round($usage_percentage, 2);
            }

            return $stats;
            
        } catch (\Exception $e) {
            Log::error("❌ [DBConnection] Failed to get stats for {$connection}: " . $e->getMessage());
            
            return [
                'connection_name' => $connection,
                'health' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Emergency connection cleanup
     */
    public static function emergencyCleanup(): void
    {
        Log::warning("🚨 [DBConnection] Performing emergency connection cleanup");

        try {
            // Kill long-running queries
            $longQueries = DB::select("
                SELECT ID, TIME, INFO 
                FROM INFORMATION_SCHEMA.PROCESSLIST 
                WHERE TIME > 300 
                AND COMMAND != 'Sleep'
                AND USER != 'system user'
            ");

            foreach ($longQueries as $query) {
                Log::warning("🔪 [DBConnection] Killing long query (ID: {$query->ID}, Time: {$query->TIME}s)");
                try {
                    DB::statement("KILL {$query->ID}");
                } catch (\Exception $e) {
                    Log::error("❌ [DBConnection] Failed to kill query {$query->ID}: " . $e->getMessage());
                }
            }

            // Purge all connections
            $connections = array_keys(config('database.connections'));
            foreach ($connections as $connection) {
                if (config("database.connections.{$connection}.driver") === 'mysql') {
                    DB::purge($connection);
                }
            }

            Log::info("✅ [DBConnection] Emergency cleanup completed");
            
        } catch (\Exception $e) {
            Log::error("❌ [DBConnection] Emergency cleanup failed: " . $e->getMessage());
        }
    }

    /**
     * Optimize database connections for high load
     */
    public static function optimizeForHighLoad(): void
    {
        try {
            // Set connection timeouts
            DB::statement('SET SESSION wait_timeout = 28800');
            DB::statement('SET SESSION interactive_timeout = 28800');
            
            // Optimize for concurrent access
            DB::statement('SET SESSION tx_isolation = "READ-COMMITTED"');
            
            Log::info("✅ [DBConnection] Optimized connection for high load");
            
        } catch (\Exception $e) {
            Log::error("❌ [DBConnection] Failed to optimize connection: " . $e->getMessage());
        }
    }
}
