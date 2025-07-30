<?php

namespace App\Console\Commands;

use App\Jobs\SyncYoutubeChannelsJob;
use App\Models\YoutubeChannel;
use Illuminate\Console\Command;

class SyncYoutubeChannels extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'youtube:sync {--force : Force sync all channels regardless of last sync time}';

    /**
     * The console command description.
     */
    protected $description = 'Sync YouTube channels data from API';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔄 Starting YouTube channels sync...');

        $force = $this->option('force');

        // Get channels that need sync
        $query = YoutubeChannel::active();
        
        if (!$force) {
            $query->needsSync();
        }

        $channelsCount = $query->count();

        if ($channelsCount === 0) {
            $this->info('✅ No channels need sync');
            return 0;
        }

        $this->info("📊 Found {$channelsCount} channels to sync");

        if ($force) {
            $this->warn('⚠️ Force mode enabled - syncing all active channels');
        }

        // Dispatch the job
        SyncYoutubeChannelsJob::dispatch();

        $this->info('✅ Sync job dispatched to queue');
        $this->line('   Use "php artisan queue:work" to process the job');

        return 0;
    }
}
