<?php

namespace App\Console\Commands;

use App\Exceptions\RedisConnectionHandler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class QueueWorkerWithRetry extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:work-retry 
                            {connection=redis : Queue connection}
                            {--queue=default : Queue name}
                            {--timeout=60 : Worker timeout}
                            {--sleep=3 : Sleep time when no jobs}
                            {--tries=3 : Max tries per job}
                            {--max-redis-retries=5 : Max Redis connection retries}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run queue worker with Redis connection retry logic';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $connection = $this->argument('connection');
        $queue = $this->option('queue');
        $timeout = $this->option('timeout');
        $sleep = $this->option('sleep');
        $tries = $this->option('tries');
        $maxRedisRetries = $this->option('max-redis-retries');
        
        $this->info("🚀 Starting queue worker with Redis retry logic...");
        $this->info("Connection: {$connection}, Queue: {$queue}");
        
        $redisRetries = 0;
        
        while (true) {
            try {
                // Test Redis connection before starting worker
                $this->testRedisConnection();
                $redisRetries = 0; // Reset retry counter on success
                
                // Run the actual queue worker
                $this->call('queue:work', [
                    'connection' => $connection,
                    '--queue' => $queue,
                    '--timeout' => $timeout,
                    '--sleep' => $sleep,
                    '--tries' => $tries,
                    '--max-time' => 3600, // Restart worker every hour
                ]);
                
            } catch (\Exception $e) {
                $redisRetries++;
                
                RedisConnectionHandler::handleConnectionError($e, 'Queue Worker Retry');
                
                if ($redisRetries >= $maxRedisRetries) {
                    $this->error("❌ Max Redis retries ({$maxRedisRetries}) reached. Exiting.");
                    return 1;
                }
                
                $waitTime = min(60, pow(2, $redisRetries)); // Exponential backoff, max 60s
                $this->warn("⚠️ Redis error (attempt {$redisRetries}/{$maxRedisRetries}). Retrying in {$waitTime}s...");
                
                sleep($waitTime);
                
                // Try to fix connection
                $this->attemptRedisReconnect();
            }
        }
    }
    
    /**
     * Test Redis connection
     */
    private function testRedisConnection(): void
    {
        $redis = Redis::connection('queue');
        $result = $redis->ping();

        $isHealthy = $result === 'PONG' ||
                    $result === true ||
                    (is_object($result) && method_exists($result, '__toString') && (string)$result === 'PONG');

        if (!$isHealthy) {
            throw new \Exception("Redis ping failed: " . json_encode($result));
        }
    }
    
    /**
     * Attempt to reconnect to Redis
     */
    private function attemptRedisReconnect(): void
    {
        try {
            $this->info("🔄 Attempting Redis reconnection...");
            
            // Clear connection cache
            Redis::purge('queue');
            Redis::purge('default');
            
            // Test connection
            $this->testRedisConnection();
            
            $this->info("✅ Redis reconnection successful");
            
        } catch (\Exception $e) {
            $this->warn("⚠️ Redis reconnection failed: {$e->getMessage()}");
        }
    }
}
