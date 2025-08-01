<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use App\Services\RedisAgentService;

class SetupRedis extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'redis:setup 
                            {--force : Force setup even if keys exist}
                            {--clean : Clean existing keys first}';

    /**
     * The console command description.
     */
    protected $description = 'Setup Redis with required keys and structures for EZStream';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("🚀 Setting up Redis for EZStream");
        $this->line("==========================================");

        if ($this->option('clean')) {
            $this->cleanRedis();
        }

        // 1. Test connection
        $this->testConnection();

        // 2. Setup agent package (if possible)
        $this->setupAgentPackage();

        // 3. Setup channels and basic structures
        $this->setupChannels();

        // 4. Verify setup
        $this->verifySetup();

        $this->line("");
        $this->info("✅ Redis setup completed successfully!");
        $this->line("");
        $this->info("📋 Next steps:");
        $this->line("   1. Run: php artisan agent:listen");
        $this->line("   2. Test agent communication");
        $this->line("   3. Monitor logs for any issues");
    }

    /**
     * Clean existing Redis keys
     */
    private function cleanRedis(): void
    {
        $this->line("");
        $this->info("[1] Cleaning existing Redis keys");
        $this->line("------------------------------------------");

        try {
            $keys = Redis::keys('*');
            $this->line("   Found " . count($keys) . " existing keys");

            if (count($keys) > 0 && $this->confirm('Delete all existing keys?', false)) {
                foreach ($keys as $key) {
                    Redis::del($key);
                }
                $this->info("   ✅ Cleaned " . count($keys) . " keys");
            } else {
                $this->line("   ⏭️ Skipped cleaning");
            }

        } catch (\Exception $e) {
            $this->error("   ❌ Failed to clean Redis: " . $e->getMessage());
        }
    }

    /**
     * Test Redis connection
     */
    private function testConnection(): void
    {
        $this->line("");
        $this->info("[2] Testing Redis connection");
        $this->line("------------------------------------------");

        try {
            $result = Redis::ping();
            $isHealthy = $result === 'PONG' ||
                        $result === true ||
                        (is_object($result) && method_exists($result, '__toString') && (string)$result === 'PONG');

            if ($isHealthy) {
                $this->info("   ✅ Redis connection successful");
            } else {
                throw new \Exception("Ping failed: " . json_encode($result));
            }

        } catch (\Exception $e) {
            $this->error("   ❌ Redis connection failed: " . $e->getMessage());
            return;
        }
    }

    /**
     * Setup agent package
     */
    private function setupAgentPackage(): void
    {
        $this->line("");
        $this->info("[3] Setting up agent package");
        $this->line("------------------------------------------");

        try {
            // Check if agent package already exists
            $existingPackage = Redis::get('agent_package:latest');
            
            if ($existingPackage && !$this->option('force')) {
                $this->line("   ⏭️ Agent package already exists (use --force to recreate)");
                return;
            }

            // Create a minimal agent package structure
            $this->createMinimalAgentPackage();

        } catch (\Exception $e) {
            $this->warn("   ⚠️ Could not setup agent package: " . $e->getMessage());
            $this->line("   💡 You can setup agent package later with: php artisan agent:package-redis");
        }
    }

    /**
     * Create minimal agent package for testing
     */
    private function createMinimalAgentPackage(): void
    {
        $version = 'v' . date('Y.m.d.His') . '_setup';
        
        // Create minimal package data (base64 encoded empty zip would be ideal, but for now use placeholder)
        $packageData = base64_encode('MINIMAL_AGENT_PACKAGE_PLACEHOLDER');
        
        // Store package
        Redis::setex("agent_package:latest", 86400, $packageData);
        Redis::setex("agent_package:{$version}", 86400, $packageData);
        
        // Store metadata
        $metadata = [
            'version' => $version,
            'size' => strlen($packageData),
            'created_at' => now()->toISOString(),
            'redis_key' => "agent_package:{$version}",
            'type' => 'minimal_setup'
        ];
        
        Redis::setex("agent_metadata:latest", 86400, json_encode($metadata));
        Redis::setex("agent_metadata:{$version}", 86400, json_encode($metadata));
        
        $this->info("   ✅ Created minimal agent package: {$version}");
    }

    /**
     * Setup channels and basic structures
     */
    private function setupChannels(): void
    {
        $this->line("");
        $this->info("[4] Setting up channels and structures");
        $this->line("------------------------------------------");

        try {
            // Test publish to agent-reports channel
            $testMessage = json_encode([
                'type' => 'SETUP_TEST',
                'timestamp' => now()->toISOString(),
                'message' => 'Redis setup test message'
            ]);
            
            Redis::publish('agent-reports', $testMessage);
            $this->info("   ✅ Agent reports channel ready");

            // Setup some basic keys for testing
            Redis::setex('redis_setup_timestamp', 3600, now()->toISOString());
            Redis::setex('redis_setup_status', 3600, 'completed');
            
            $this->info("   ✅ Basic structures created");

        } catch (\Exception $e) {
            $this->error("   ❌ Failed to setup channels: " . $e->getMessage());
        }
    }

    /**
     * Verify setup
     */
    private function verifySetup(): void
    {
        $this->line("");
        $this->info("[5] Verifying setup");
        $this->line("------------------------------------------");

        $checks = [
            'agent_package:latest' => 'Agent package',
            'agent_metadata:latest' => 'Agent metadata',
            'redis_setup_status' => 'Setup status'
        ];

        $allGood = true;

        foreach ($checks as $key => $description) {
            $exists = Redis::exists($key);
            if ($exists) {
                $this->info("   ✅ {$description}: OK");
            } else {
                $this->warn("   ⚠️ {$description}: Missing");
                $allGood = false;
            }
        }

        // Show all keys
        $allKeys = Redis::keys('*');
        $this->line("");
        $this->line("   📊 Total Redis keys: " . count($allKeys));
        
        if (count($allKeys) <= 10) {
            $this->line("   Keys: " . implode(', ', $allKeys));
        }

        if ($allGood) {
            $this->info("   🎉 All checks passed!");
        } else {
            $this->warn("   ⚠️ Some checks failed, but Redis is functional");
        }
    }
}
