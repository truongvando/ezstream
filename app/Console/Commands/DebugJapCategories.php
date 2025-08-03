<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\JapApiService;

class DebugJapCategories extends Command
{
    protected $signature = 'debug:jap-categories';
    protected $description = 'Debug JAP categories structure';

    public function handle()
    {
        $this->info('🔍 Debugging JAP Categories Structure...');

        $japService = app(JapApiService::class);
        
        // Get raw services first
        $allServices = $japService->getAllServices();
        $this->info('Total services from API: ' . count($allServices));
        
        // Filter YouTube services manually
        $youtubeServices = [];
        foreach ($allServices as $service) {
            if (isset($service['category']) && stripos($service['category'], 'youtube') !== false) {
                $youtubeServices[] = $service;
            }
        }
        
        $this->info('YouTube services found: ' . count($youtubeServices));
        
        // Group by category manually
        $categories = [];
        foreach ($youtubeServices as $service) {
            $category = $service['category'];
            if (!isset($categories[$category])) {
                $categories[$category] = [];
            }
            $categories[$category][] = $service;
        }
        
        $this->info('Unique categories: ' . count($categories));
        $this->newLine();
        
        // Show categories
        foreach ($categories as $category => $services) {
            $this->line("{$category}: " . count($services) . " services");
        }
        
        $this->newLine();
        $this->info('Now testing getYouTubeServices() method...');
        
        // Test the actual method
        $categorizedServices = $japService->getYouTubeServices();
        
        foreach ($categorizedServices as $mainCategory => $subCategories) {
            $this->info("=== {$mainCategory} ===");
            $this->line("Sub-categories: " . count($subCategories));
            
            $count = 0;
            foreach ($subCategories as $subCategory => $services) {
                if ($count < 3) {
                    $this->line("  → {$subCategory}: " . count($services) . " services");
                    $count++;
                } elseif ($count === 3) {
                    $this->line("  → ... and " . (count($subCategories) - 3) . " more");
                    break;
                }
            }
            $this->newLine();
        }

        return 0;
    }
}
