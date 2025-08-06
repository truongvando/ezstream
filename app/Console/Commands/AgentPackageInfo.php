<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class AgentPackageInfo extends Command
{
    protected $signature = 'agent:info';
    protected $description = 'Show current agent package information in Redis';

    public function handle()
    {
        try {
            $this->info('📦 Agent Package Information');
            $this->line('');

            // Check if package exists
            $packageExists = Redis::exists('agent_package:latest');
            
            if (!$packageExists) {
                $this->warn('⚠️ No agent package found in Redis');
                $this->info('💡 Run: php artisan agent:deploy');
                return Command::SUCCESS;
            }

            // Get package info
            $timestamp = Redis::get('agent_package:timestamp') ?: 'Unknown';
            $size = Redis::get('agent_package:size') ?: 0;
            $packageData = Redis::get('agent_package:latest');
            $encodedSize = strlen($packageData);

            // Display info
            $this->table(['Property', 'Value'], [
                ['📅 Last Updated', $timestamp],
                ['📊 Package Size', number_format($size) . ' bytes'],
                ['📊 Encoded Size', number_format($encodedSize) . ' bytes'],
                ['🔗 Redis Key', 'agent_package:latest'],
                ['✅ Status', 'Available']
            ]);

            // Show local file info if exists
            $localFile = storage_path('app/ezstream-agent-latest.tar.gz');
            if (file_exists($localFile)) {
                $localSize = filesize($localFile);
                $localTime = date('Y-m-d H:i:s', filemtime($localFile));
                
                $this->line('');
                $this->info('📁 Local Package File:');
                $this->line("   Path: {$localFile}");
                $this->line("   Size: " . number_format($localSize) . " bytes");
                $this->line("   Modified: {$localTime}");
                
                // Compare sizes
                if ($localSize != $size) {
                    $this->warn('⚠️ Local file size differs from Redis package!');
                    $this->info('💡 Run: php artisan agent:deploy');
                }
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Error: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
}
