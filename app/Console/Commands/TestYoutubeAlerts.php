<?php

namespace App\Console\Commands;

use App\Models\YoutubeChannel;
use App\Services\YoutubeAlertService;
use Illuminate\Console\Command;

class TestYoutubeAlerts extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'youtube:test-alerts {--channel-id= : Test alerts for specific channel ID}';

    /**
     * The console command description.
     */
    protected $description = 'Test YouTube alert system';

    /**
     * Execute the console command.
     */
    public function handle(YoutubeAlertService $alertService): int
    {
        $this->info('🔔 Testing YouTube alert system...');

        $channelId = $this->option('channel-id');

        if ($channelId) {
            $channel = YoutubeChannel::find($channelId);
            
            if (!$channel) {
                $this->error("Channel with ID {$channelId} not found");
                return 1;
            }

            $this->testChannelAlerts($alertService, $channel);
        } else {
            $channels = YoutubeChannel::active()->limit(5)->get();
            
            if ($channels->isEmpty()) {
                $this->warn('No active channels found to test');
                return 0;
            }

            $this->info("Testing alerts for {$channels->count()} channels...");
            
            foreach ($channels as $channel) {
                $this->testChannelAlerts($alertService, $channel);
            }
        }

        $this->info('✅ Alert testing completed');
        return 0;
    }

    private function testChannelAlerts(YoutubeAlertService $alertService, YoutubeChannel $channel): void
    {
        $this->line("🎯 Testing alerts for: {$channel->channel_name}");

        try {
            $alertService->processChannelAlerts($channel);
            $this->info("  ✅ Alerts processed successfully");
        } catch (\Exception $e) {
            $this->error("  ❌ Error processing alerts: {$e->getMessage()}");
        }
    }
}
