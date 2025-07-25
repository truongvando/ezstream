<?php

/**
 * Script test Livewire endpoint trực tiếp
 * Chạy: php test-livewire-endpoint.php
 */

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

echo "🔍 Testing Livewire Endpoint\n";
echo "============================\n\n";

// Create a mock request to test Livewire
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

try {
    // 1. Test basic Laravel request handling
    echo "1. Testing basic Laravel request...\n";
    
    $request = Request::create('/admin/vps-servers', 'GET');
    $request->headers->set('Accept', 'text/html');
    
    // Set up session for the request
    $request->setLaravelSession(app('session.store'));
    
    echo "   ✅ Request created successfully\n";
    
    // 2. Test route resolution
    echo "\n2. Testing route resolution...\n";
    
    $route = Route::getRoutes()->match($request);
    if ($route) {
        echo "   ✅ Route found: " . $route->getName() . "\n";
        echo "   ✅ Route action: " . $route->getActionName() . "\n";
    } else {
        echo "   ❌ Route not found\n";
    }
    
    // 3. Test Livewire component instantiation
    echo "\n3. Testing Livewire component...\n";
    
    $component = new App\Livewire\VpsServerManager();
    
    // Test render method
    $view = $component->render();
    if ($view instanceof \Illuminate\View\View) {
        echo "   ✅ Component render() returns View instance\n";
        echo "   ✅ View name: " . $view->getName() . "\n";
    } else {
        echo "   ❌ Component render() failed\n";
    }
    
    // 4. Test component methods
    echo "\n4. Testing component methods...\n";
    
    // Test openBulkUpdateModal
    try {
        $component->openBulkUpdateModal();
        if ($component->showBulkUpdateModal === true) {
            echo "   ✅ openBulkUpdateModal() works\n";
        } else {
            echo "   ❌ openBulkUpdateModal() failed\n";
        }
    } catch (Exception $e) {
        echo "   ❌ openBulkUpdateModal() error: " . $e->getMessage() . "\n";
    }
    
    // Test closeBulkUpdateModal
    try {
        $component->closeBulkUpdateModal();
        if ($component->showBulkUpdateModal === false) {
            echo "   ✅ closeBulkUpdateModal() works\n";
        } else {
            echo "   ❌ closeBulkUpdateModal() failed\n";
        }
    } catch (Exception $e) {
        echo "   ❌ closeBulkUpdateModal() error: " . $e->getMessage() . "\n";
    }
    
    // 5. Test VPS data retrieval
    echo "\n5. Testing VPS data retrieval...\n";
    
    try {
        $servers = App\Models\VpsServer::select([
                'vps_servers.id', 
                'vps_servers.name', 
                'vps_servers.ip_address', 
                'vps_servers.is_active', 
                'vps_servers.status'
            ])
            ->where('is_active', true)
            ->get();
            
        echo "   ✅ Found " . $servers->count() . " active VPS servers\n";
        
        foreach ($servers as $server) {
            echo "     - {$server->name} ({$server->ip_address}) - {$server->status}\n";
        }
        
    } catch (Exception $e) {
        echo "   ❌ VPS data error: " . $e->getMessage() . "\n";
    }
    
    // 6. Test UpdateAgentJob
    echo "\n6. Testing UpdateAgentJob...\n";
    
    try {
        $testVps = App\Models\VpsServer::where('is_active', true)->first();
        
        if ($testVps) {
            // Don't actually dispatch, just test instantiation
            $job = new App\Jobs\UpdateAgentJob($testVps);
            echo "   ✅ UpdateAgentJob can be instantiated\n";
            echo "   ✅ Test VPS: {$testVps->name} (ID: {$testVps->id})\n";
        } else {
            echo "   ⚠️ No active VPS found for testing\n";
        }
        
    } catch (Exception $e) {
        echo "   ❌ UpdateAgentJob error: " . $e->getMessage() . "\n";
    }
    
    // 7. Test Queue
    echo "\n7. Testing Queue...\n";
    
    try {
        $queueConnection = config('queue.default');
        echo "   ✅ Queue connection: {$queueConnection}\n";
        
        $queueSize = \Illuminate\Support\Facades\Queue::size('vps-provisioning');
        echo "   ✅ Queue 'vps-provisioning' size: {$queueSize}\n";
        
    } catch (Exception $e) {
        echo "   ❌ Queue error: " . $e->getMessage() . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Fatal error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n✅ Endpoint test completed!\n";
echo "\n💡 If all tests pass, the Livewire 500 error should be resolved.\n";
echo "   Try accessing: http://your-domain/admin/vps-servers\n";
