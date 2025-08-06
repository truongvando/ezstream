<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

class PackageAndDeployAgent extends Command
{
    protected $signature = 'agent:deploy {--update-vps=* : VPS IDs to update after deploy}';
    protected $description = 'Package agent code and deploy to Redis (with optional VPS updates)';

    public function handle()
    {
        try {
            $this->info('🚀 Starting agent deployment process...');
            
            // Step 1: Package agent files
            $this->packageAgent();
            
            // Step 2: Upload to Redis
            $this->uploadToRedis();
            
            // Step 3: Update VPS if requested
            $vpsIds = $this->option('update-vps');
            if (!empty($vpsIds)) {
                $this->updateVpsServers($vpsIds);
            }
            
            $this->info('✅ Agent deployment completed successfully!');
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Deployment failed: {$e->getMessage()}");
            Log::error("Agent deployment failed", ['error' => $e->getMessage()]);
            return Command::FAILURE;
        }
    }

    private function packageAgent(): void
    {
        $this->info('📦 Step 1: Packaging agent files...');
        
        $agentDir = storage_path('app/ezstream-agent');
        $packagePath = storage_path('app/ezstream-agent-latest.tar.gz');
        
        if (!is_dir($agentDir)) {
            throw new \Exception("Agent directory not found: {$agentDir}");
        }

        // Remove old package
        if (file_exists($packagePath)) {
            unlink($packagePath);
            $this->line("🗑️ Removed old package");
        }

        // Create new package
        $command = "cd " . escapeshellarg($agentDir) . " && tar -czf " . escapeshellarg($packagePath) . " .";
        $result = shell_exec($command . " 2>&1");
        
        if (!file_exists($packagePath)) {
            throw new \Exception("Failed to create package. Output: {$result}");
        }

        $size = File::size($packagePath);
        $this->info("✅ Package created: " . number_format($size) . " bytes");
    }

    private function uploadToRedis(): void
    {
        $this->info('☁️ Step 2: Uploading to Redis...');
        
        $packagePath = storage_path('app/ezstream-agent-latest.tar.gz');
        
        if (!file_exists($packagePath)) {
            throw new \Exception("Package file not found: {$packagePath}");
        }

        // Read and encode package
        $packageData = file_get_contents($packagePath);
        $encodedData = base64_encode($packageData);
        
        // Store in Redis with timestamp
        $timestamp = now()->format('Y-m-d H:i:s');
        Redis::set('agent_package:latest', $encodedData);
        Redis::set('agent_package:timestamp', $timestamp);
        Redis::set('agent_package:size', strlen($packageData));
        
        $this->info("✅ Uploaded to Redis:");
        $this->line("   📊 Size: " . number_format(strlen($packageData)) . " bytes");
        $this->line("   📊 Encoded: " . number_format(strlen($encodedData)) . " bytes");
        $this->line("   🕐 Timestamp: {$timestamp}");

        Log::info("Agent package deployed to Redis", [
            'size' => strlen($packageData),
            'encoded_size' => strlen($encodedData),
            'timestamp' => $timestamp
        ]);
    }

    private function updateVpsServers(array $vpsIds): void
    {
        $this->info('🔄 Step 3: Updating VPS servers...');
        
        foreach ($vpsIds as $vpsId) {
            $vps = \App\Models\VpsServer::find($vpsId);
            
            if (!$vps) {
                $this->warn("⚠️ VPS #{$vpsId} not found, skipping...");
                continue;
            }

            $this->line("🔄 Updating VPS #{$vpsId}: {$vps->name}");
            
            try {
                // Dispatch update job
                \App\Jobs\UpdateAgentJob::dispatch($vps);
                $this->info("   ✅ Update job dispatched for VPS #{$vpsId}");
                
            } catch (\Exception $e) {
                $this->error("   ❌ Failed to dispatch update for VPS #{$vpsId}: {$e->getMessage()}");
            }
        }
        
        $this->info("💡 Run 'php artisan queue:work' to process update jobs");
    }
}
