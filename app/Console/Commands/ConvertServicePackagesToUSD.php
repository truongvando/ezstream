<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ServicePackage;
use App\Services\ExchangeRateService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ConvertServicePackagesToUSD extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'packages:convert-to-usd {--rate=26000 : Exchange rate to use for conversion} {--dry-run : Show what would be converted without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Convert service package prices from VND to USD';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $rate = (float) $this->option('rate');
        $dryRun = $this->option('dry-run');

        $this->info("💰 Converting service package prices from VND to USD");
        $this->info("📊 Using exchange rate: 1 USD = " . number_format($rate, 0, ',', '.') . " VND");
        
        if ($dryRun) {
            $this->warn("🔍 DRY RUN MODE - No changes will be made");
        }

        // Get packages that likely have VND pricing (price > 1000)
        $packages = ServicePackage::where('price', '>', 1000)->get();

        if ($packages->isEmpty()) {
            $this->info("✅ No packages found with VND pricing (price > 1000)");
            return 0;
        }

        $this->info("📦 Found {$packages->count()} packages to convert:");

        $conversions = [];

        foreach ($packages as $package) {
            $oldPrice = $package->price;
            $newPrice = round($oldPrice / $rate, 2);
            
            $conversions[] = [
                'id' => $package->id,
                'name' => $package->name,
                'old_price_vnd' => number_format($oldPrice, 0, ',', '.') . ' VND',
                'new_price_usd' => '$' . number_format($newPrice, 2),
                'old_price' => $oldPrice,
                'new_price' => $newPrice
            ];
        }

        // Display conversion table
        $this->table(
            ['ID', 'Package Name', 'Old Price (VND)', 'New Price (USD)'],
            collect($conversions)->map(function($conv) {
                return [
                    $conv['id'],
                    $conv['name'],
                    $conv['old_price_vnd'],
                    $conv['new_price_usd']
                ];
            })->toArray()
        );

        if ($dryRun) {
            $this->info("🔍 This was a dry run. Use without --dry-run to apply changes.");
            return 0;
        }

        // Confirm before proceeding
        if (!$this->confirm('Do you want to proceed with the conversion?')) {
            $this->info("❌ Conversion cancelled");
            return 0;
        }

        // Perform conversion
        $converted = 0;
        $errors = 0;

        DB::transaction(function () use ($conversions, &$converted, &$errors) {
            foreach ($conversions as $conversion) {
                try {
                    ServicePackage::where('id', $conversion['id'])
                                  ->update(['price' => $conversion['new_price']]);
                    
                    $converted++;
                    
                    Log::info('Service package price converted', [
                        'package_id' => $conversion['id'],
                        'name' => $conversion['name'],
                        'old_price' => $conversion['old_price'],
                        'new_price' => $conversion['new_price']
                    ]);
                    
                } catch (\Exception $e) {
                    $this->error("Error converting package {$conversion['id']}: " . $e->getMessage());
                    $errors++;
                }
            }
        });

        // Summary
        $this->newLine();
        $this->info("✅ Conversion completed!");
        $this->table(
            ['Result', 'Count'],
            [
                ['Converted', $converted],
                ['Errors', $errors],
                ['Total', count($conversions)]
            ]
        );

        if ($converted > 0) {
            $this->info("🎉 Successfully converted {$converted} service packages to USD pricing");
            $this->warn("⚠️ Remember to update any hardcoded VND displays in views");
        }

        return $errors > 0 ? 1 : 0;
    }
}
