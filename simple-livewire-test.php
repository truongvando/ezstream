<?php

/**
 * Simple Livewire test
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "🔍 Simple Livewire Test\n";
echo "======================\n\n";

// Test component creation
try {
    echo "1. Testing VpsServerManager component...\n";
    $component = new App\Livewire\VpsServerManager();
    echo "   ✅ Component created successfully\n";
    
    // Test bulk update methods
    echo "\n2. Testing bulk update methods...\n";
    
    $component->openBulkUpdateModal();
    echo "   ✅ openBulkUpdateModal() - showBulkUpdateModal: " . ($component->showBulkUpdateModal ? 'true' : 'false') . "\n";
    
    $component->closeBulkUpdateModal();
    echo "   ✅ closeBulkUpdateModal() - showBulkUpdateModal: " . ($component->showBulkUpdateModal ? 'true' : 'false') . "\n";
    
    echo "\n3. Testing VPS data...\n";
    $activeVps = App\Models\VpsServer::where('is_active', true)->count();
    echo "   ✅ Active VPS count: {$activeVps}\n";
    
    echo "\n4. Testing UpdateAgentJob...\n";
    if ($activeVps > 0) {
        $vps = App\Models\VpsServer::where('is_active', true)->first();
        $job = new App\Jobs\UpdateAgentJob($vps->id);
        echo "   ✅ UpdateAgentJob created for VPS: {$vps->name} (ID: {$vps->id})\n";
    } else {
        echo "   ⚠️ No active VPS to test with\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n✅ Simple test completed!\n";
