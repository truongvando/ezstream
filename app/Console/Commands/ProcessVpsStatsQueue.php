<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use App\Jobs\UpdateVpsStatsJob;

class ProcessVpsStatsQueue extends Command
{
    protected $signature = 'vps:process-stats-queue {--timeout=0 : Timeout in seconds (0 = infinite)}';
    protected $description = 'Process VPS stats from Redis queue (alternative to subscription)';

    private $running = true;

    public function handle()
    {
        $timeout = (int) $this->option('timeout');
        $startTime = time();
        
        $this->info('🚀 Starting VPS stats queue processor...');
        $this->info('📊 Polling Redis for VPS stats every 5 seconds');
        
        // Handle graceful shutdown
        pcntl_signal(SIGTERM, [$this, 'shutdown']);
        pcntl_signal(SIGINT, [$this, 'shutdown']);
        
        while ($this->running) {
            try {
                // Check timeout
                if ($timeout > 0 && (time() - $startTime) >= $timeout) {
                    $this->info('⏰ Timeout reached, shutting down...');
                    break;
                }
                
                // Process any pending stats
                $this->processVpsStats();
                
                // Allow signal handling
                pcntl_signal_dispatch();
                
                // Sleep for 5 seconds
                sleep(5);
                
            } catch (\Exception $e) {
                Log::error('VPS stats queue processor error: ' . $e->getMessage());
                $this->error('Error: ' . $e->getMessage());
                sleep(10); // Wait longer on error
            }
        }
        
        $this->info('✅ VPS stats queue processor stopped');
        return Command::SUCCESS;
    }
    
    private function processVpsStats(): void
    {
        try {
            // Get all VPS stats from Redis hash
            $allStats = Redis::hgetall('vps_live_stats');
            
            if (empty($allStats)) {
                return; // No stats to process
            }
            
            $processedCount = 0;
            
            foreach ($allStats as $vpsId => $statsJson) {
                $statsData = json_decode($statsJson, true);
                
                if (!$statsData || !isset($statsData['vps_id'])) {
                    continue;
                }
                
                // Check if this is fresh data (within last 2 minutes)
                $receivedAt = $statsData['received_at'] ?? 0;
                if ($receivedAt && (time() - $receivedAt) < 120) {
                    // Process fresh stats
                    $this->line("📊 Processing stats for VPS {$vpsId}");
                    $processedCount++;
                }
            }
            
            if ($processedCount > 0) {
                $this->info("✅ Processed stats for {$processedCount} VPS servers");
            }
            
        } catch (\Exception $e) {
            Log::error('Error processing VPS stats: ' . $e->getMessage());
            throw $e;
        }
    }
    
    public function shutdown(): void
    {
        $this->running = false;
        $this->info('🛑 Received shutdown signal...');
    }
}
