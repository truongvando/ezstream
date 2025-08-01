<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\RedisAgentService;

class PackageAgentToRedis extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'agent:package-redis {--agent-version= : Specific version to package} {--clean : Clean old packages}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Package agent files and store in Redis';

    /**
     * Execute the console command.
     */
    public function handle(RedisAgentService $redisService)
    {
        if ($this->option('clean')) {
            $this->cleanOldPackages($redisService);
            return 0;
        }

        $this->info('📦 Packaging EZStream Agent for Redis...');

        $version = $this->option('agent-version');

        if ($version) {
            $this->info("Using specified version: {$version}");
        } else {
            $this->info("Auto-generating version based on timestamp");
        }

        $result = $redisService->packageAndStoreAgent($version);

        if ($result['success']) {
            $this->info("✅ Agent packaged successfully!");
            $this->line("   Version: {$result['version']}");
            $this->line("   Size: {$result['size_kb']} KB");
            $this->line("   Redis Key: {$result['redis_key']}");

            $this->info("\n🚀 Agent is now ready for deployment!");
            $this->line("   VPS can download from Redis using key: {$result['redis_key']}");

            // Show available versions
            $this->showAvailableVersions($redisService);

        } else {
            $this->error("❌ Failed to package agent: {$result['error']}");
            return 1;
        }

        return 0;
    }

    private function cleanOldPackages(RedisAgentService $redisService): void
    {
        $this->info('🗑️ Cleaning old agent packages from Redis...');

        $deletedCount = $redisService->cleanOldPackages(5);

        if ($deletedCount > 0) {
            $this->info("✅ Cleaned {$deletedCount} old packages");
        } else {
            $this->info("✅ No old packages to clean");
        }

        $this->showAvailableVersions($redisService);
    }

    private function showAvailableVersions(RedisAgentService $redisService): void
    {
        $versions = $redisService->listAvailableVersions();

        if (empty($versions)) {
            $this->warn("No agent packages found in Redis");
            return;
        }

        $this->info("\n📋 Available agent versions in Redis:");

        foreach ($versions as $version) {
            $sizeKb = round($version['size'] / 1024, 2);
            $createdAt = date('Y-m-d H:i:s', strtotime($version['created_at']));

            $this->line("   {$version['version']} - {$sizeKb} KB - {$createdAt}");
        }
    }
}
