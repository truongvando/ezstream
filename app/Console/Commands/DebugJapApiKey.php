<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\JapApiService;
use App\Services\JustAnotherPanelService;

class DebugJapApiKey extends Command
{
    protected $signature = 'jap:debug-api-key';
    protected $description = 'Debug JAP API key configuration and test connection';

    public function handle()
    {
        $this->info('🔍 Debugging JAP API Key Configuration...');
        
        // Check environment variables
        $envKey = env('JAP_API_KEY');
        $configKey = config('services.jap.api_key');
        $settingKey = setting('jap_api_key');
        
        $this->table(['Source', 'Value', 'Status'], [
            ['env("JAP_API_KEY")', $envKey ? substr($envKey, 0, 10) . '...' : 'NULL', $envKey ? '✅' : '❌'],
            ['config("services.jap.api_key")', $configKey ? substr($configKey, 0, 10) . '...' : 'NULL', $configKey ? '✅' : '❌'],
            ['setting("jap_api_key")', $settingKey ? substr($settingKey, 0, 10) . '...' : 'NULL', $settingKey ? '✅' : '❌'],
        ]);
        
        // Test both services
        $this->info('🧪 Testing JapApiService...');
        try {
            $japService = new JapApiService();
            $services = $japService->getAllServices();
            if (empty($services)) {
                $this->error('❌ JapApiService: No services returned');
            } else {
                $this->info('✅ JapApiService: ' . count($services) . ' services fetched');
            }
        } catch (\Exception $e) {
            $this->error('❌ JapApiService Error: ' . $e->getMessage());
        }
        
        $this->info('🧪 Testing JustAnotherPanelService...');
        try {
            $japService = new JustAnotherPanelService();
            $result = $japService->testConnection();
            if ($result['success']) {
                $this->info('✅ JustAnotherPanelService: Connection successful');
                $this->info('💰 Balance: ' . ($result['balance'] ?? 'N/A'));
            } else {
                $this->error('❌ JustAnotherPanelService: ' . $result['message']);
            }
        } catch (\Exception $e) {
            $this->error('❌ JustAnotherPanelService Error: ' . $e->getMessage());
        }
        
        // Recommendations
        $this->newLine();
        $this->info('💡 Recommendations:');
        
        if (!$envKey) {
            $this->warn('• Add JAP_API_KEY to .env file');
        }
        
        if (!$configKey) {
            $this->warn('• Add jap.api_key to config/services.php');
        }
        
        $this->info('• Run "php artisan config:cache" after updating .env');
        $this->info('• Check production .env file has correct JAP_API_KEY');
        
        return 0;
    }
}
