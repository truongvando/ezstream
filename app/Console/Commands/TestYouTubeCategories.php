<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\JapApiService;

class TestYouTubeCategories extends Command
{
    protected $signature = 'test:youtube-categories';
    protected $description = 'Test YouTube categories from JAP API';

    public function handle()
    {
        $this->info('🧪 Testing YouTube Categories...');

        $japService = app(JapApiService::class);
        $categories = $japService->getYouTubeServices();

        $this->newLine();

        foreach ($categories as $mainCategory => $subCategories) {
            $this->info("=== {$mainCategory} ===");
            $this->line("Sub-categories: " . count($subCategories));

            // Show only first 5 sub-categories to avoid spam
            $count = 0;
            foreach ($subCategories as $subCategory => $services) {
                if ($count < 5) {
                    $this->line("  → {$subCategory}: " . count($services) . " services");
                    $count++;
                } elseif ($count === 5) {
                    $this->line("  → ... and " . (count($subCategories) - 5) . " more sub-categories");
                    break;
                }
            }
            $this->newLine();
        }

        // Calculate totals
        $totalServices = 0;
        $totalSubCategories = 0;
        foreach ($categories as $mainCategory => $subCategories) {
            $totalSubCategories += count($subCategories);
            foreach ($subCategories as $services) {
                $totalServices += count($services);
            }
        }
        
        // Count services per main category
        $viewsCount = 0;
        $subsCount = 0;
        $liveCount = 0;
        $likesCount = 0;
        $commentsCount = 0;

        foreach ($categories['VIEWS'] ?? [] as $services) {
            $viewsCount += count($services);
        }
        foreach ($categories['SUBSCRIBERS'] ?? [] as $services) {
            $subsCount += count($services);
        }
        foreach ($categories['LIVESTREAM'] ?? [] as $services) {
            $liveCount += count($services);
        }
        foreach ($categories['LIKES'] ?? [] as $services) {
            $likesCount += count($services);
        }
        foreach ($categories['COMMENTS'] ?? [] as $services) {
            $commentsCount += count($services);
        }

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total main categories', count($categories)],
                ['Total sub-categories', $totalSubCategories],
                ['Total services', $totalServices],
                ['VIEWS services', $viewsCount],
                ['SUBSCRIBERS services', $subsCount],
                ['LIVESTREAM services', $liveCount],
                ['LIKES services', $likesCount],
                ['COMMENTS services', $commentsCount]
            ]
        );

        return 0;
    }
}
