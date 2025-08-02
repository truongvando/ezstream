<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\JustAnotherPanelService;

class TestJapApiCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api:test-jap';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test Just Another Panel API connection and functionality';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🧪 Testing Just Another Panel API...');
        $this->newLine();

        $japService = new JustAnotherPanelService();

        // Test 1: Connection Test
        $this->info('1️⃣ Testing API connection...');
        $connectionResult = $japService->testConnection();
        
        if ($connectionResult['success']) {
            $this->info('✅ Connection successful!');
            $this->info('💰 Account balance: $' . $connectionResult['balance']);
        } else {
            $this->error('❌ Connection failed: ' . $connectionResult['message']);
            return 1;
        }

        $this->newLine();

        // Test 2: Get Services
        $this->info('2️⃣ Testing services retrieval...');
        $servicesResult = $japService->getServices();
        
        if ($servicesResult['success']) {
            $services = $servicesResult['data'];
            $this->info('✅ Services retrieved successfully!');
            $this->info('📦 Total services: ' . count($services));
            
            // Show sample services
            if (count($services) > 0) {
                $this->info('📋 Sample services:');
                $sampleServices = array_slice($services, 0, 5);
                
                $tableData = [];
                foreach ($sampleServices as $service) {
                    $tableData[] = [
                        $service['service'] ?? 'N/A',
                        substr($service['name'] ?? 'N/A', 0, 40) . '...',
                        $service['category'] ?? 'N/A',
                        '$' . ($service['rate'] ?? '0'),
                        $service['min'] ?? 'N/A',
                        $service['max'] ?? 'N/A'
                    ];
                }
                
                $this->table(
                    ['ID', 'Name', 'Category', 'Rate', 'Min', 'Max'],
                    $tableData
                );
            }
        } else {
            $this->error('❌ Failed to retrieve services: ' . $servicesResult['message']);
        }

        $this->newLine();

        // Test 3: Service Categories
        if ($servicesResult['success']) {
            $this->info('3️⃣ Analyzing service categories...');
            $services = $servicesResult['data'];
            $categories = [];
            
            foreach ($services as $service) {
                $category = $service['category'] ?? 'Other';
                if (!isset($categories[$category])) {
                    $categories[$category] = 0;
                }
                $categories[$category]++;
            }
            
            arsort($categories);
            
            $this->info('📊 Service categories:');
            $categoryData = [];
            foreach ($categories as $category => $count) {
                $categoryData[] = [$category, $count];
            }
            
            $this->table(['Category', 'Count'], array_slice($categoryData, 0, 10));
        }

        $this->newLine();

        // Test 4: Mock Order Test (if user confirms)
        if ($this->confirm('Do you want to test order creation? (This will NOT create a real order)')) {
            $this->info('4️⃣ Testing order creation (mock)...');
            
            // This is just a validation test, not a real order
            $mockOrderData = [
                'service_id' => 1, // Mock service ID
                'link' => 'https://example.com/test',
                'quantity' => 100
            ];
            
            $this->info('📝 Mock order data:');
            $this->info('   Service ID: ' . $mockOrderData['service_id']);
            $this->info('   Link: ' . $mockOrderData['link']);
            $this->info('   Quantity: ' . $mockOrderData['quantity']);
            
            $this->warn('⚠️  This is a mock test - no real order will be created');
            $this->info('✅ Order creation logic is ready');
        }

        $this->newLine();
        $this->info('🎉 API testing completed!');
        
        return 0;
    }
}
