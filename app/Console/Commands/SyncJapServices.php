<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\JapApiService;
use App\Models\ApiService;

class SyncJapServices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'jap:sync-services {--force : Force sync even if recently synced}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync YouTube services from JAP API';

    private $japApiService;

    public function __construct(JapApiService $japApiService)
    {
        parent::__construct();
        $this->japApiService = $japApiService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔄 Starting JAP API sync...');

        // Get all services from JAP API
        $allServices = $this->japApiService->getAllServices();
        
        if (empty($allServices)) {
            $this->error('❌ Failed to fetch services from JAP API');
            return 1;
        }

        $this->info('📥 Fetched ' . count($allServices) . ' services from JAP API');

        // Filter YouTube services
        $youtubeServices = collect($allServices)->filter(function ($service) {
            return isset($service['category']) && 
                   stripos($service['category'], 'youtube') !== false;
        });

        $this->info('🎬 Found ' . $youtubeServices->count() . ' YouTube services');

        // Sync to database
        $created = 0;
        $updated = 0;
        $errors = 0;

        $progressBar = $this->output->createProgressBar($youtubeServices->count());
        $progressBar->start();

        foreach ($youtubeServices as $service) {
            try {
                $apiService = ApiService::updateOrCreate(
                    ['service_id' => $service['service']],
                    [
                        'name' => $service['name'],
                        'type' => $service['type'],
                        'category' => $service['category'],
                        'rate' => (float) $service['rate'],
                        'min_quantity' => (int) $service['min'],
                        'max_quantity' => (int) $service['max'],
                        'refill' => (bool) $service['refill'],
                        'cancel' => (bool) $service['cancel'],
                        'markup_percentage' => 20, // Default 20% markup
                        'is_active' => true
                    ]
                );

                if ($apiService->wasRecentlyCreated) {
                    $created++;
                } else {
                    $updated++;
                }

            } catch (\Exception $e) {
                $this->error('Error syncing service ' . $service['service'] . ': ' . $e->getMessage());
                $errors++;
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();

        // Clear cache
        $this->japApiService->clearCache();

        // Summary
        $this->info('✅ Sync completed!');
        $this->table(
            ['Action', 'Count'],
            [
                ['Created', $created],
                ['Updated', $updated],
                ['Errors', $errors],
                ['Total', $youtubeServices->count()]
            ]
        );

        return 0;
    }
}
