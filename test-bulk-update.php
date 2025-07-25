<?php

/**
 * Script test chức năng bulk update VPS
 * Chạy: php test-bulk-update.php
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\VpsServer;
use App\Jobs\UpdateAgentJob;
use Illuminate\Support\Facades\Queue;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "🔍 Testing Bulk VPS Update Functionality\n";
echo "==========================================\n\n";

// 1. Kiểm tra VPS servers
echo "1. Checking VPS servers...\n";
$activeVpsServers = VpsServer::where('is_active', true)
    ->where('status', '!=', 'PROVISIONING')
    ->get();

echo "   Found {$activeVpsServers->count()} active VPS servers:\n";
foreach ($activeVpsServers as $vps) {
    echo "   - {$vps->name} (ID: {$vps->id}) - Status: {$vps->status}\n";
}
echo "\n";

// 2. Kiểm tra queue connection
echo "2. Checking queue connection...\n";
try {
    $queueConnection = config('queue.default');
    echo "   Queue connection: {$queueConnection}\n";
    
    // Test queue
    $pendingJobs = Queue::size('vps-provisioning');
    echo "   Pending jobs in 'vps-provisioning' queue: {$pendingJobs}\n";
} catch (Exception $e) {
    echo "   ❌ Queue error: " . $e->getMessage() . "\n";
}
echo "\n";

// 3. Test dispatch một job
echo "3. Testing job dispatch...\n";
if ($activeVpsServers->isNotEmpty()) {
    $testVps = $activeVpsServers->first();
    echo "   Testing with VPS: {$testVps->name} (ID: {$testVps->id})\n";
    
    try {
        UpdateAgentJob::dispatch($testVps)->onQueue('vps-provisioning');
        echo "   ✅ UpdateAgentJob dispatched successfully\n";
        
        // Check queue size after dispatch
        $pendingJobs = Queue::size('vps-provisioning');
        echo "   Pending jobs after dispatch: {$pendingJobs}\n";
        
    } catch (Exception $e) {
        echo "   ❌ Job dispatch failed: " . $e->getMessage() . "\n";
    }
} else {
    echo "   ⚠️ No active VPS servers to test with\n";
}
echo "\n";

// 4. Simulate bulk update logic
echo "4. Simulating bulk update logic...\n";
$successCount = 0;
$failedCount = 0;

foreach ($activeVpsServers as $vps) {
    echo "   Processing VPS: {$vps->name}... ";
    
    try {
        // Simulate job dispatch (không thực sự dispatch để tránh spam queue)
        // UpdateAgentJob::dispatch($vps)->onQueue('vps-provisioning');
        
        echo "✅ Success\n";
        $successCount++;
        
    } catch (Exception $e) {
        echo "❌ Failed: " . $e->getMessage() . "\n";
        $failedCount++;
    }
    
    // Simulate delay
    usleep(100000); // 0.1 second
}

echo "\n";
echo "5. Summary:\n";
echo "   Total VPS: " . $activeVpsServers->count() . "\n";
echo "   Success: {$successCount}\n";
echo "   Failed: {$failedCount}\n";
echo "\n";

// 6. Check UpdateAgentJob class
echo "6. Checking UpdateAgentJob class...\n";
if (class_exists('App\Jobs\UpdateAgentJob')) {
    echo "   ✅ UpdateAgentJob class exists\n";
    
    $reflection = new ReflectionClass('App\Jobs\UpdateAgentJob');
    $constructor = $reflection->getConstructor();
    
    if ($constructor) {
        $parameters = $constructor->getParameters();
        echo "   Constructor parameters: " . count($parameters) . "\n";
        foreach ($parameters as $param) {
            echo "     - {$param->getName()}: {$param->getType()}\n";
        }
    }
} else {
    echo "   ❌ UpdateAgentJob class not found\n";
}

echo "\n✅ Test completed!\n";
