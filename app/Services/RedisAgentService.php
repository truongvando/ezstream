<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use ZipArchive;

class RedisAgentService
{
    /**
     * Package agent files and store in Redis
     */
    public function packageAndStoreAgent(string $version = null): array
    {
        try {
            $version = $version ?: 'v' . date('Y.m.d.His');
            
            Log::info("📦 [RedisAgent] Packaging agent version: {$version}");
            
            // Step 1: Create agent package
            $packageData = $this->createAgentPackage($version);
            
            if (!$packageData) {
                throw new \Exception('Failed to create agent package');
            }
            
            // Step 2: Store in Redis with TTL (24 hours)
            $redisKey = "agent_package:{$version}";
            $latestKey = "agent_package:latest";
            
            // Store versioned package
            Redis::setex($redisKey, 86400, $packageData); // 24h TTL
            
            // Store as latest
            Redis::setex($latestKey, 86400, $packageData);
            
            // Store metadata
            $metadata = [
                'version' => $version,
                'size' => strlen($packageData),
                'created_at' => now()->toISOString(),
                'redis_key' => $redisKey
            ];
            
            Redis::setex("agent_metadata:{$version}", 86400, json_encode($metadata));
            Redis::setex("agent_metadata:latest", 86400, json_encode($metadata));
            
            Log::info("✅ [RedisAgent] Agent {$version} stored in Redis successfully", [
                'size_kb' => round(strlen($packageData) / 1024, 2),
                'redis_key' => $redisKey
            ]);
            
            return [
                'success' => true,
                'version' => $version,
                'size_kb' => round(strlen($packageData) / 1024, 2),
                'redis_key' => $redisKey
            ];
            
        } catch (\Exception $e) {
            Log::error("❌ [RedisAgent] Package storage failed: {$e->getMessage()}");
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Create base64 encoded ZIP package of agent files
     */
    private function createAgentPackage(string $version): ?string
    {
        $agentDir = storage_path('app/ezstream-agent');
        $packagePath = storage_path("app/temp/ezstream-agent-{$version}.zip");
        
        // Ensure temp directory exists
        $tempDir = dirname($packagePath);
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        
        $zip = new ZipArchive();
        
        if ($zip->open($packagePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
            Log::error("Cannot create ZIP file: {$packagePath}");
            return null;
        }
        
        // Add all Python files
        $pythonFiles = glob($agentDir . '/*.py');
        foreach ($pythonFiles as $file) {
            $zip->addFile($file, basename($file));
        }
        
        // Add config files
        $configFiles = glob($agentDir . '/*.conf');
        foreach ($configFiles as $file) {
            $zip->addFile($file, basename($file));
        }
        
        // Add shell scripts
        $shellFiles = glob($agentDir . '/*.sh');
        foreach ($shellFiles as $file) {
            $zip->addFile($file, basename($file));
        }
        
        $fileCount = $zip->numFiles;
        $zip->close();
        
        if ($fileCount === 0) {
            Log::error("No files added to ZIP package");
            if (file_exists($packagePath)) {
                unlink($packagePath);
            }
            return null;
        }
        
        // Read and encode package
        $packageContent = file_get_contents($packagePath);
        if ($packageContent === false) {
            Log::error("Cannot read package file: {$packagePath}");
            return null;
        }
        
        // Cleanup temp file
        unlink($packagePath);
        
        // Return base64 encoded content
        $encodedContent = base64_encode($packageContent);
        
        Log::info("📦 Created agent package", [
            'files' => $fileCount,
            'size_bytes' => strlen($packageContent),
            'size_kb' => round(strlen($packageContent) / 1024, 2),
            'encoded_size_kb' => round(strlen($encodedContent) / 1024, 2)
        ]);
        
        return $encodedContent;
    }
    
    /**
     * Get agent package from Redis
     */
    public function getAgentPackage(string $version = 'latest'): ?array
    {
        try {
            $redisKey = "agent_package:{$version}";
            $metadataKey = "agent_metadata:{$version}";
            
            $packageData = Redis::get($redisKey);
            $metadata = Redis::get($metadataKey);
            
            if (!$packageData) {
                Log::warning("Agent package not found in Redis: {$redisKey}");
                return null;
            }
            
            $metadataArray = $metadata ? json_decode($metadata, true) : [];
            
            return [
                'package_data' => $packageData,
                'metadata' => $metadataArray,
                'redis_key' => $redisKey
            ];
            
        } catch (\Exception $e) {
            Log::error("❌ Failed to get agent package from Redis: {$e->getMessage()}");
            return null;
        }
    }
    
    /**
     * List available agent versions in Redis
     */
    public function listAvailableVersions(): array
    {
        try {
            $keys = Redis::keys('agent_metadata:*');
            $versions = [];
            
            foreach ($keys as $key) {
                if ($key === 'agent_metadata:latest') {
                    continue;
                }
                
                $metadata = Redis::get($key);
                if ($metadata) {
                    $versions[] = json_decode($metadata, true);
                }
            }
            
            // Sort by created_at desc
            usort($versions, function($a, $b) {
                return strtotime($b['created_at']) - strtotime($a['created_at']);
            });
            
            return $versions;
            
        } catch (\Exception $e) {
            Log::error("❌ Failed to list agent versions: {$e->getMessage()}");
            return [];
        }
    }
    
    /**
     * Clean old agent packages from Redis
     */
    public function cleanOldPackages(int $keepCount = 5): int
    {
        try {
            $versions = $this->listAvailableVersions();
            
            if (count($versions) <= $keepCount) {
                return 0;
            }
            
            $toDelete = array_slice($versions, $keepCount);
            $deletedCount = 0;
            
            foreach ($toDelete as $version) {
                $versionName = $version['version'];
                
                Redis::del("agent_package:{$versionName}");
                Redis::del("agent_metadata:{$versionName}");
                
                $deletedCount++;
                Log::info("🗑️ Cleaned old agent package: {$versionName}");
            }
            
            return $deletedCount;
            
        } catch (\Exception $e) {
            Log::error("❌ Failed to clean old packages: {$e->getMessage()}");
            return 0;
        }
    }
}
