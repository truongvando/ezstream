<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use App\Services\RedisMemoryManager;
use App\Services\RtmpCircuitBreaker;
use App\Services\DistributedLock;

class TestRedisConnection extends Command
{
    protected $signature = 'redis:test {--detailed : Show detailed Redis information}';
    protected $description = 'Test Redis connection and functionality';

    public function handle()
    {
        $this->info('🔍 Testing Redis Connection...');
        $this->newLine();

        // Basic connection test
        $this->testBasicConnection();
        
        // Test Redis operations
        $this->testRedisOperations();
        
        // Test our custom services
        $this->testCustomServices();
        
        if ($this->option('detailed')) {
            $this->showDetailedInfo();
        }
        
        $this->newLine();
        $this->info('✅ Redis connection test completed!');
    }

    private function testBasicConnection(): void
    {
        $this->info('📡 Basic Connection Test:');
        
        try {
            // Test basic ping
            $pong = Redis::ping();
            $this->line("   ✅ Ping: {$pong}");
            
            // Test info
            $info = Redis::info();
            $version = $info['redis_version'] ?? 'Unknown';
            $this->line("   ✅ Redis Version: {$version}");
            
            // Test memory info
            $memoryInfo = Redis::info('memory');
            $usedMemory = $memoryInfo['used_memory_human'] ?? 'Unknown';
            $this->line("   ✅ Used Memory: {$usedMemory}");
            
        } catch (\Exception $e) {
            $this->error("   ❌ Connection failed: " . $e->getMessage());
            return;
        }
    }

    private function testRedisOperations(): void
    {
        $this->newLine();
        $this->info('🔧 Redis Operations Test:');
        
        try {
            // Test SET/GET
            $testKey = 'test_key_' . time();
            $testValue = 'test_value_' . uniqid();
            
            Redis::set($testKey, $testValue);
            $retrieved = Redis::get($testKey);
            
            if ($retrieved === $testValue) {
                $this->line("   ✅ SET/GET: Working");
            } else {
                $this->error("   ❌ SET/GET: Failed");
            }
            
            // Test SETEX (with TTL)
            Redis::setex($testKey . '_ttl', 60, $testValue);
            $ttl = Redis::ttl($testKey . '_ttl');
            
            if ($ttl > 0 && $ttl <= 60) {
                $this->line("   ✅ SETEX/TTL: Working (TTL: {$ttl}s)");
            } else {
                $this->error("   ❌ SETEX/TTL: Failed");
            }
            
            // Test HASH operations
            $hashKey = 'test_hash_' . time();
            Redis::hset($hashKey, 'field1', 'value1');
            Redis::hset($hashKey, 'field2', 'value2');
            
            $hashData = Redis::hgetall($hashKey);
            if (count($hashData) === 2) {
                $this->line("   ✅ HASH operations: Working");
            } else {
                $this->error("   ❌ HASH operations: Failed");
            }
            
            // Test LIST operations
            $listKey = 'test_list_' . time();
            Redis::lpush($listKey, 'item1', 'item2', 'item3');
            $listLength = Redis::llen($listKey);
            
            if ($listLength === 3) {
                $this->line("   ✅ LIST operations: Working");
            } else {
                $this->error("   ❌ LIST operations: Failed");
            }
            
            // Test PUB/SUB
            $channel = 'test_channel_' . time();
            $message = 'test_message_' . uniqid();
            $subscribers = Redis::publish($channel, $message);
            
            $this->line("   ✅ PUB/SUB: Working (subscribers: {$subscribers})");
            
            // Cleanup test keys
            Redis::del($testKey, $testKey . '_ttl', $hashKey, $listKey);
            
        } catch (\Exception $e) {
            $this->error("   ❌ Operations failed: " . $e->getMessage());
        }
    }

    private function testCustomServices(): void
    {
        $this->newLine();
        $this->info('🛠️ Custom Services Test:');
        
        // Test DistributedLock
        try {
            $lockKey = 'test_lock_' . time();
            $lockValue = DistributedLock::acquire($lockKey, 30);
            
            if ($lockValue) {
                $this->line("   ✅ DistributedLock: Acquire working");
                
                $released = DistributedLock::release($lockKey, $lockValue);
                if ($released) {
                    $this->line("   ✅ DistributedLock: Release working");
                } else {
                    $this->error("   ❌ DistributedLock: Release failed");
                }
            } else {
                $this->error("   ❌ DistributedLock: Acquire failed");
            }
        } catch (\Exception $e) {
            $this->error("   ❌ DistributedLock: " . $e->getMessage());
        }
        
        // Test RedisMemoryManager
        try {
            $memoryStats = RedisMemoryManager::getMemoryStats();
            $this->line("   ✅ RedisMemoryManager: Working (Usage: {$memoryStats['memory_usage_percentage']}%)");
            
            $keyStats = RedisMemoryManager::getKeyStats();
            $totalKeys = array_sum($keyStats);
            $this->line("   ✅ RedisMemoryManager: Key stats working (Total keys: {$totalKeys})");
            
        } catch (\Exception $e) {
            $this->error("   ❌ RedisMemoryManager: " . $e->getMessage());
        }
        
        // Test RtmpCircuitBreaker
        try {
            $testRtmpUrl = 'rtmp://test.example.com/live';
            $status = RtmpCircuitBreaker::getStatus($testRtmpUrl);
            $this->line("   ✅ RtmpCircuitBreaker: Working (State: {$status['state']})");
            
        } catch (\Exception $e) {
            $this->error("   ❌ RtmpCircuitBreaker: " . $e->getMessage());
        }
    }

    private function showDetailedInfo(): void
    {
        $this->newLine();
        $this->info('📊 Detailed Redis Information:');
        
        try {
            // Server info
            $info = Redis::info('server');
            $this->line("   Redis Version: " . ($info['redis_version'] ?? 'Unknown'));
            $this->line("   Redis Mode: " . ($info['redis_mode'] ?? 'Unknown'));
            $this->line("   OS: " . ($info['os'] ?? 'Unknown'));
            $this->line("   Uptime: " . ($info['uptime_in_seconds'] ?? 'Unknown') . ' seconds');
            
            // Memory info
            $memInfo = Redis::info('memory');
            $this->line("   Used Memory: " . ($memInfo['used_memory_human'] ?? 'Unknown'));
            $this->line("   Peak Memory: " . ($memInfo['used_memory_peak_human'] ?? 'Unknown'));
            $this->line("   Memory Fragmentation: " . ($memInfo['mem_fragmentation_ratio'] ?? 'Unknown'));
            
            // Stats info
            $stats = Redis::info('stats');
            $this->line("   Total Connections: " . ($stats['total_connections_received'] ?? 'Unknown'));
            $this->line("   Total Commands: " . ($stats['total_commands_processed'] ?? 'Unknown'));
            
            // Clients info
            $clients = Redis::info('clients');
            $this->line("   Connected Clients: " . ($clients['connected_clients'] ?? 'Unknown'));
            
            // Key count
            $dbInfo = Redis::info('keyspace');
            $this->line("   Database Info: " . json_encode($dbInfo));
            
        } catch (\Exception $e) {
            $this->error("   ❌ Failed to get detailed info: " . $e->getMessage());
        }
    }
}
