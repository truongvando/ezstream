<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\ExchangeRateService;
use Illuminate\Support\Facades\Log;

class UpdateExchangeRateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if (!config('payment.auto_update_rate', true)) {
            echo "📊 Exchange rate auto-update is disabled\n";
            return;
        }

        try {
            $exchangeService = new ExchangeRateService();
            
            // Clear cache to force fresh fetch
            $exchangeService->clearCache();
            
            // Get new rate (this will fetch from API)
            $newRate = $exchangeService->getUsdToVndRate();
            
            $rateInfo = $exchangeService->getRateInfo();
            
            echo "💱 Exchange rate updated: 1 USD = " . number_format($newRate, 0, ',', '.') . " VND\n";
            echo "📅 Source: {$rateInfo['source']}\n";
            
            Log::info('Exchange rate auto-updated', [
                'rate' => $newRate,
                'source' => $rateInfo['source']
            ]);

        } catch (\Exception $e) {
            Log::error('Error auto-updating exchange rate: ' . $e->getMessage());
            echo "❌ Error updating exchange rate: " . $e->getMessage() . "\n";
        }
    }
}
