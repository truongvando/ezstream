<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ExchangeRateService;

class ManageExchangeRateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'exchange-rate {action} {--rate=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage USD to VND exchange rate';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $action = $this->argument('action');
        $exchangeService = new ExchangeRateService();

        switch ($action) {
            case 'show':
                $this->showCurrentRate($exchangeService);
                break;
                
            case 'update':
                $this->updateRate($exchangeService);
                break;
                
            case 'set':
                $rate = $this->option('rate');
                if (!$rate) {
                    $this->error('Please provide --rate option');
                    return 1;
                }
                $this->setRate($exchangeService, floatval($rate));
                break;
                
            case 'clear':
                $this->clearCache($exchangeService);
                break;
                
            default:
                $this->error('Invalid action. Available: show, update, set, clear');
                return 1;
        }

        return 0;
    }

    private function showCurrentRate(ExchangeRateService $service)
    {
        $rateInfo = $service->getRateInfo();
        
        $this->info('💱 Current Exchange Rate Information:');
        $this->line('');
        $this->line("Rate: 1 USD = " . number_format($rateInfo['rate'], 0, ',', '.') . " VND");
        $this->line("Source: {$rateInfo['source']}");
        $this->line("Last Updated: {$rateInfo['last_updated']}");
        
        // Show conversion examples
        $this->line('');
        $this->info('💰 Conversion Examples:');
        $amounts = [1, 5, 10, 25, 50, 100];
        foreach ($amounts as $usd) {
            $vnd = $service->convertUsdToVnd($usd);
            $this->line("${usd} USD = " . number_format($vnd, 0, ',', '.') . " VND");
        }
    }

    private function updateRate(ExchangeRateService $service)
    {
        $this->info('🔄 Updating exchange rate from APIs...');
        
        $service->clearCache();
        $newRate = $service->getUsdToVndRate();
        
        $this->info("✅ Rate updated: 1 USD = " . number_format($newRate, 0, ',', '.') . " VND");
    }

    private function setRate(ExchangeRateService $service, float $rate)
    {
        if ($rate <= 0) {
            $this->error('Rate must be positive');
            return;
        }

        if ($service->updateRate($rate)) {
            $this->info("✅ Exchange rate set to: 1 USD = " . number_format($rate, 0, ',', '.') . " VND");
        } else {
            $this->error('❌ Failed to set exchange rate');
        }
    }

    private function clearCache(ExchangeRateService $service)
    {
        $service->clearCache();
        $this->info('🧹 Exchange rate cache cleared');
    }
}
