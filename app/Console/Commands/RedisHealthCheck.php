<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

class RedisHealthCheck extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'redis:health-check 
                            {--connection=default : Redis connection to check}
                            {--fix : Try to fix connection issues}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check Redis connection health and optionally fix issues';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $connection = $this->option('connection');
        $fix = $this->option('fix');
        
        $this->info("🔍 Checking Redis connection: {$connection}");
        
        try {
            // Test basic connection
            $redis = Redis::connection($connection);
            $result = $redis->ping();

            // Handle different Redis client responses
            $isHealthy = $result === 'PONG' ||
                        $result === true ||
                        (is_object($result) && method_exists($result, '__toString') && (string)$result === 'PONG');

            if ($isHealthy) {
                $this->info("✅ Redis connection '{$connection}' is healthy");

                // Test queue operations if it's queue connection
                if ($connection === 'queue' || $connection === 'default') {
                    $this->testQueueOperations($redis);
                }

                return 0;
            } else {
                $this->error("❌ Redis ping failed: " . json_encode($result));
                return 1;
            }
            
        } catch (\Exception $e) {
            $this->error("❌ Redis connection failed: {$e->getMessage()}");
            
            if ($fix) {
                $this->info("🔧 Attempting to fix connection issues...");
                $this->attemptFix($connection);
            }
            
            return 1;
        }
    }
    
    /**
     * Test queue-specific operations
     */
    private function testQueueOperations($redis): void
    {
        try {
            $this->info("🧪 Testing queue operations...");
            
            // Test basic queue operations
            $testKey = 'health_check_' . time();
            $redis->lpush($testKey, 'test_job');
            $result = $redis->rpop($testKey);
            
            if ($result === 'test_job') {
                $this->info("✅ Queue operations working correctly");
            } else {
                $this->warn("⚠️ Queue operations may have issues");
            }
            
            // Cleanup
            $redis->del($testKey);
            
        } catch (\Exception $e) {
            $this->warn("⚠️ Queue operation test failed: {$e->getMessage()}");
        }
    }
    
    /**
     * Attempt to fix connection issues
     */
    private function attemptFix(string $connection): void
    {
        try {
            $this->info("🔄 Clearing Redis connection cache...");
            
            // Clear connection cache
            Redis::purge($connection);
            
            $this->info("🔄 Reconnecting...");
            
            // Try to reconnect
            $redis = Redis::connection($connection);
            $result = $redis->ping();

            $isHealthy = $result === 'PONG' ||
                        $result === true ||
                        (is_object($result) && method_exists($result, '__toString') && (string)$result === 'PONG');

            if ($isHealthy) {
                $this->info("✅ Connection restored successfully");
            } else {
                $this->error("❌ Fix attempt failed");
            }
            
        } catch (\Exception $e) {
            $this->error("❌ Fix attempt failed: {$e->getMessage()}");
            
            $this->info("💡 Suggestions:");
            $this->line("  - Check if Redis server is running");
            $this->line("  - Verify Redis configuration in .env");
            $this->line("  - Check network connectivity");
            $this->line("  - Restart Redis server if needed");
        }
    }
}
