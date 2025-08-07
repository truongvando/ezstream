<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\JapApiService;
use App\Models\ApiService;
use Illuminate\Support\Facades\Log;

class CheckJapServiceAvailability extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'jap:check-service-availability {--inactive-only : Only check inactive services}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check JAP service availability and update active status';

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
        $this->info('🔍 Checking JAP service availability...');

        // Get all services from JAP API
        $allJapServices = $this->japApiService->getAllServices();
        
        if (empty($allJapServices)) {
            $this->error('❌ Failed to fetch services from JAP API');
            return 1;
        }

        // Create lookup array for faster searching
        $japServiceIds = collect($allJapServices)->pluck('service')->toArray();
        $this->info('📥 Fetched ' . count($japServiceIds) . ' services from JAP API');

        // Get local services to check
        $query = ApiService::query();
        if ($this->option('inactive-only')) {
            $query->where('is_active', false);
            $this->info('🔍 Checking only inactive services...');
        }
        
        $localServices = $query->get();
        $this->info('💾 Found ' . $localServices->count() . ' local services to check');

        $activated = 0;
        $deactivated = 0;
        $unchanged = 0;

        $progressBar = $this->output->createProgressBar($localServices->count());
        $progressBar->start();

        foreach ($localServices as $service) {
            try {
                $isAvailableInJap = in_array($service->service_id, $japServiceIds);
                
                if ($isAvailableInJap && !$service->is_active) {
                    // Service is available in JAP but inactive locally - activate it
                    $service->update(['is_active' => true]);
                    $activated++;
                    
                    Log::info('Service reactivated', [
                        'service_id' => $service->service_id,
                        'name' => $service->name
                    ]);
                    
                } elseif (!$isAvailableInJap && $service->is_active) {
                    // Service not available in JAP but active locally - deactivate it
                    $service->update(['is_active' => false]);
                    $deactivated++;
                    
                    Log::warning('Service deactivated (not available in JAP)', [
                        'service_id' => $service->service_id,
                        'name' => $service->name
                    ]);
                    
                } else {
                    $unchanged++;
                }

            } catch (\Exception $e) {
                $this->error('Error checking service ' . $service->service_id . ': ' . $e->getMessage());
                Log::error('Service availability check error', [
                    'service_id' => $service->service_id,
                    'error' => $e->getMessage()
                ]);
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();

        // Clear cache to refresh data
        $this->japApiService->clearCache();

        // Summary
        $this->info('✅ Service availability check completed!');
        $this->table(
            ['Action', 'Count'],
            [
                ['Activated', $activated],
                ['Deactivated', $deactivated], 
                ['Unchanged', $unchanged],
                ['Total Checked', $localServices->count()]
            ]
        );

        if ($activated > 0) {
            $this->info("🟢 {$activated} services were reactivated");
        }
        
        if ($deactivated > 0) {
            $this->warn("🔴 {$deactivated} services were deactivated (not available in JAP)");
        }

        return 0;
    }
}
