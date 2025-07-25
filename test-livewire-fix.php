<?php

/**
 * Script test Livewire sau khi fix APP_KEY issue
 * Chạy: php test-livewire-fix.php
 */

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "🔍 Testing Livewire Fix\n";
echo "======================\n\n";

// 1. Test APP_KEY
echo "1. Testing APP_KEY...\n";
try {
    $appKey = config('app.key');
    if ($appKey) {
        echo "   ✅ APP_KEY is set: " . substr($appKey, 0, 20) . "...\n";
    } else {
        echo "   ❌ APP_KEY is not set\n";
    }
} catch (Exception $e) {
    echo "   ❌ Error getting APP_KEY: " . $e->getMessage() . "\n";
}

// 2. Test Encrypter
echo "\n2. Testing Encrypter...\n";
try {
    $encrypter = app('encrypter');
    $testData = 'test-data-' . time();
    $encrypted = $encrypter->encrypt($testData);
    $decrypted = $encrypter->decrypt($encrypted);
    
    if ($decrypted === $testData) {
        echo "   ✅ Encrypter working correctly\n";
    } else {
        echo "   ❌ Encrypter failed: decrypted data doesn't match\n";
    }
} catch (Exception $e) {
    echo "   ❌ Encrypter error: " . $e->getMessage() . "\n";
}

// 3. Test Livewire Component
echo "\n3. Testing Livewire Component...\n";
try {
    $component = new App\Livewire\VpsServerManager();
    echo "   ✅ VpsServerManager component created successfully\n";
    
    // Test properties
    $properties = [
        'showBulkUpdateModal',
        'bulkUpdateProgress', 
        'bulkUpdateInProgress'
    ];
    
    foreach ($properties as $prop) {
        if (property_exists($component, $prop)) {
            echo "   ✅ Property '{$prop}' exists\n";
        } else {
            echo "   ❌ Property '{$prop}' missing\n";
        }
    }
    
    // Test methods
    $methods = [
        'openBulkUpdateModal',
        'closeBulkUpdateModal',
        'updateAllVps'
    ];
    
    foreach ($methods as $method) {
        if (method_exists($component, $method)) {
            echo "   ✅ Method '{$method}' exists\n";
        } else {
            echo "   ❌ Method '{$method}' missing\n";
        }
    }
    
} catch (Exception $e) {
    echo "   ❌ Component error: " . $e->getMessage() . "\n";
}

// 4. Test Session
echo "\n4. Testing Session...\n";
try {
    // Start session for testing
    if (!session_id()) {
        session_start();
    }
    
    $sessionKey = 'test_key_' . time();
    $sessionValue = 'test_value_' . time();
    
    session([$sessionKey => $sessionValue]);
    $retrieved = session($sessionKey);
    
    if ($retrieved === $sessionValue) {
        echo "   ✅ Session working correctly\n";
    } else {
        echo "   ❌ Session failed\n";
    }
} catch (Exception $e) {
    echo "   ❌ Session error: " . $e->getMessage() . "\n";
}

// 5. Test Cache
echo "\n5. Testing Cache...\n";
try {
    $cacheKey = 'test_cache_' . time();
    $cacheValue = 'test_value_' . time();
    
    cache([$cacheKey => $cacheValue], 60);
    $retrieved = cache($cacheKey);
    
    if ($retrieved === $cacheValue) {
        echo "   ✅ Cache working correctly\n";
    } else {
        echo "   ❌ Cache failed\n";
    }
} catch (Exception $e) {
    echo "   ❌ Cache error: " . $e->getMessage() . "\n";
}

// 6. Test Database
echo "\n6. Testing Database...\n";
try {
    $vpsCount = App\Models\VpsServer::count();
    echo "   ✅ Database connection working. VPS count: {$vpsCount}\n";
} catch (Exception $e) {
    echo "   ❌ Database error: " . $e->getMessage() . "\n";
}

// 7. Check recent logs
echo "\n7. Checking recent logs...\n";
$logFile = storage_path('logs/laravel.log');
if (file_exists($logFile)) {
    $logContent = file_get_contents($logFile);
    $recentErrors = substr_count($logContent, '[' . date('Y-m-d'));
    echo "   📊 Log entries today: {$recentErrors}\n";
    
    if (strpos($logContent, 'MissingAppKeyException') !== false) {
        echo "   ⚠️ Still has APP_KEY errors in log\n";
    } else {
        echo "   ✅ No APP_KEY errors found\n";
    }
} else {
    echo "   ⚠️ Log file not found\n";
}

echo "\n✅ Test completed!\n";
echo "\n💡 Next steps:\n";
echo "   1. Clear browser cache and cookies\n";
echo "   2. Test Livewire in browser: /admin/vps-servers\n";
echo "   3. Monitor logs: tail -f storage/logs/laravel.log\n";
echo "   4. If still errors, restart web server\n";
