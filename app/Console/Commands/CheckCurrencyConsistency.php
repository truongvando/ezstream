<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Transaction;
use App\Models\MmoService;
use App\Models\MmoOrder;
use App\Models\ApiService;
use App\Models\ViewOrder;
use App\Models\ServicePackage;
use App\Services\CurrencyService;
use App\Services\ExchangeRateService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckCurrencyConsistency extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'currency:check-consistency {--fix : Fix inconsistencies automatically}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check and fix currency consistency across the system (ensure all pricing is in USD)';

    private $currencyService;

    public function __construct(CurrencyService $currencyService)
    {
        parent::__construct();
        $this->currencyService = $currencyService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('💰 Checking currency consistency across the system...');
        $fix = $this->option('fix');

        $issues = [];

        // 1. Check Users table (balance should be USD)
        $this->info('👥 Checking Users table...');
        $usersWithBalance = User::where('balance', '>', 0)->count();
        $this->info("Found {$usersWithBalance} users with balance (assuming USD)");

        // 2. Check Transactions table
        $this->info('💳 Checking Transactions table...');
        $nonUsdTransactions = Transaction::where('currency', '!=', 'USD')->count();
        if ($nonUsdTransactions > 0) {
            $issues[] = "Found {$nonUsdTransactions} transactions with non-USD currency";
            $this->warn("⚠️ Found {$nonUsdTransactions} transactions with non-USD currency");
            
            if ($fix) {
                Transaction::where('currency', '!=', 'USD')->update(['currency' => 'USD']);
                $this->info("✅ Fixed {$nonUsdTransactions} transaction currencies to USD");
            }
        }

        // 3. Check MmoServices table
        $this->info('🎮 Checking MmoServices table...');
        $nonUsdMmoServices = MmoService::where('currency', '!=', 'USD')->count();
        if ($nonUsdMmoServices > 0) {
            $issues[] = "Found {$nonUsdMmoServices} MMO services with non-USD currency";
            $this->warn("⚠️ Found {$nonUsdMmoServices} MMO services with non-USD currency");
            
            if ($fix) {
                MmoService::where('currency', '!=', 'USD')->update(['currency' => 'USD']);
                $this->info("✅ Fixed {$nonUsdMmoServices} MMO service currencies to USD");
            }
        }

        // 4. Check MmoOrders table
        $this->info('📦 Checking MmoOrders table...');
        $nonUsdMmoOrders = MmoOrder::where('currency', '!=', 'USD')->count();
        if ($nonUsdMmoOrders > 0) {
            $issues[] = "Found {$nonUsdMmoOrders} MMO orders with non-USD currency";
            $this->warn("⚠️ Found {$nonUsdMmoOrders} MMO orders with non-USD currency");
            
            if ($fix) {
                MmoOrder::where('currency', '!=', 'USD')->update(['currency' => 'USD']);
                $this->info("✅ Fixed {$nonUsdMmoOrders} MMO order currencies to USD");
            }
        }

        // 5. Check ApiServices table (rate should be USD)
        $this->info('🔗 Checking ApiServices table...');
        $apiServicesCount = ApiService::count();
        $this->info("Found {$apiServicesCount} API services (rates assumed to be USD)");

        // 6. Check ServicePackages table
        $this->info('📋 Checking ServicePackages table...');
        $servicePackagesCount = ServicePackage::count();
        $this->info("Found {$servicePackagesCount} service packages (prices assumed to be USD)");

        // 7. Check for hardcoded exchange rates in code
        $this->info('🔍 Checking for hardcoded exchange rates...');
        $this->warn('⚠️ Manual check needed for hardcoded rates in views/components');

        // Summary
        $this->newLine();
        if (empty($issues)) {
            $this->info('✅ Currency consistency check passed! All systems using USD correctly.');
        } else {
            $this->error('❌ Found currency consistency issues:');
            foreach ($issues as $issue) {
                $this->line("  • {$issue}");
            }
            
            if (!$fix) {
                $this->info('💡 Run with --fix flag to automatically fix these issues');
            }
        }

        // Display current exchange rate info
        $this->newLine();
        $exchangeService = new ExchangeRateService();
        $rateInfo = $exchangeService->getRateInfo();
        $this->info('💱 Current Exchange Rate Info:');
        $this->table(
            ['Property', 'Value'],
            [
                ['Rate (1 USD)', number_format($rateInfo['rate'], 0, ',', '.') . ' VND'],
                ['Source', $rateInfo['source']],
                ['Last Updated', $rateInfo['last_updated']],
                ['Cache Status', Cache::has('usd_to_vnd_rate') ? 'Cached' : 'Not Cached']
            ]
        );

        // Recommendations
        $this->newLine();
        $this->info('📋 System Currency Standards:');
        $this->line('  • All database amounts: USD (decimal 10,2)');
        $this->line('  • User balance: USD');
        $this->line('  • Transaction amounts: USD');
        $this->line('  • Service pricing: USD');
        $this->line('  • Display to users: USD primary, VND secondary');
        $this->line('  • Payment QR codes: Convert to VND using ExchangeRateService');

        return empty($issues) ? 0 : 1;
    }
}
