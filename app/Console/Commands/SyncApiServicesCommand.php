<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\JustAnotherPanelService;
use App\Models\ApiService;
use Illuminate\Support\Facades\Log;

class SyncApiServicesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api:sync-services {--force : Force sync even if services exist}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync services from Just Another Panel API to local database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔄 Starting API services sync...');

        $japService = new JustAnotherPanelService();

        // Test connection first
        $this->info('🔗 Testing API connection...');
        $connectionTest = $japService->testConnection();
        
        if (!$connectionTest['success']) {
            $this->error('❌ API connection failed: ' . $connectionTest['message']);
            return 1;
        }

        $this->info('✅ API connection successful. Balance: $' . $connectionTest['balance']);

        // Get services from API
        $this->info('📥 Fetching services from API...');
        $result = $japService->getServices();

        if (!$result['success']) {
            $this->error('❌ Failed to fetch services: ' . $result['message']);
            return 1;
        }

        $services = $result['data'];
        $this->info('📦 Found ' . count($services) . ' services from API');

        // Check if we should force sync
        $force = $this->option('force');
        $existingCount = ApiService::count();

        if ($existingCount > 0 && !$force) {
            $this->warn("⚠️  Found {$existingCount} existing services in database.");
            if (!$this->confirm('Do you want to continue and update existing services?')) {
                $this->info('Sync cancelled by user.');
                return 0;
            }
        }

        // Sync services
        $this->info('🔄 Syncing services to database...');
        $bar = $this->output->createProgressBar(count($services));
        $bar->start();

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($services as $serviceData) {
            try {
                // Validate required fields
                if (!isset($serviceData['service']) || !isset($serviceData['name'])) {
                    $this->newLine();
                    $this->warn('⚠️  Skipping service with missing required fields');
                    $skipped++;
                    $bar->advance();
                    continue;
                }

                $existingService = ApiService::where('service_id', $serviceData['service'])->first();

                $data = [
                    'service_id' => $serviceData['service'],
                    'name' => $serviceData['name'],
                    'type' => $serviceData['type'] ?? 'Default',
                    'category' => $serviceData['category'] ?? 'Other',
                    'rate' => (float) ($serviceData['rate'] ?? 0),
                    'min_quantity' => (int) ($serviceData['min'] ?? 1),
                    'max_quantity' => (int) ($serviceData['max'] ?? 1000),
                    'refill' => (bool) ($serviceData['refill'] ?? false),
                    'cancel' => (bool) ($serviceData['cancel'] ?? false),
                    'markup_percentage' => 20, // Default 20% markup
                    'is_active' => true
                ];

                if ($existingService) {
                    $existingService->update($data);
                    $updated++;
                } else {
                    ApiService::create($data);
                    $created++;
                }

            } catch (\Exception $e) {
                $this->newLine();
                $this->error('❌ Error syncing service ' . ($serviceData['service'] ?? 'unknown') . ': ' . $e->getMessage());
                $skipped++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // Summary
        $this->info('✅ Sync completed!');
        $this->table(
            ['Action', 'Count'],
            [
                ['Created', $created],
                ['Updated', $updated],
                ['Skipped', $skipped],
                ['Total', count($services)]
            ]
        );

        // Log the sync
        Log::info('API Services sync completed', [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'total' => count($services)
        ]);

        return 0;
    }
}
