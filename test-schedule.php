<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

echo "🔍 Testing Laravel Schedule System\n";
echo "==================================\n\n";

try {
    // 1. Test helper function
    echo "1. Testing setting() function:\n";
    if (function_exists('setting')) {
        echo "   ✅ setting() function exists\n";
        $endpoint = setting('payment_api_endpoint', 'NOT_SET');
        echo "   📡 API Endpoint: $endpoint\n";
    } else {
        echo "   ❌ setting() function not found\n";
    }
    echo "\n";

    // 2. Test schedule instance
    echo "2. Testing Schedule instance:\n";
    $schedule = $app->make(\Illuminate\Console\Scheduling\Schedule::class);
    echo "   ✅ Schedule instance created\n";
    
    // 3. Test manual registration
    echo "3. Testing manual command registration:\n";
    $schedule->command('bank:check-transactions')->everyMinute();
    echo "   ✅ Command registered manually\n";
    
    // 4. Check events
    $reflection = new ReflectionClass($schedule);
    $eventsProperty = $reflection->getProperty('events');
    $eventsProperty->setAccessible(true);
    $events = $eventsProperty->getValue($schedule);
    
    echo "   📊 Number of events: " . count($events) . "\n";
    
    // 5. Test Kernel schedule method
    echo "4. Testing Kernel schedule method:\n";
    $kernelInstance = $app->make(\App\Console\Kernel::class);
    $reflection = new ReflectionClass($kernelInstance);
    $method = $reflection->getMethod('schedule');
    $method->setAccessible(true);
    
    // Create fresh schedule for kernel test
    $kernelSchedule = $app->make(\Illuminate\Console\Scheduling\Schedule::class);
    $method->invoke($kernelInstance, $kernelSchedule);
    
    echo "   ✅ Kernel schedule method called\n";
    
    // Check kernel events
    $reflection = new ReflectionClass($kernelSchedule);
    $eventsProperty = $reflection->getProperty('events');
    $eventsProperty->setAccessible(true);
    $kernelEvents = $eventsProperty->getValue($kernelSchedule);
    
    echo "   📊 Kernel events: " . count($kernelEvents) . "\n";
    
    if (count($kernelEvents) > 0) {
        echo "   📋 Scheduled commands:\n";
        foreach ($kernelEvents as $event) {
            echo "      - " . $event->getSummaryForDisplay() . "\n";
        }
    }
    
    // 6. Test command exists
    echo "5. Testing command existence:\n";
    $commands = $kernel->all();
    $bankCommand = null;
    foreach ($commands as $name => $command) {
        if (strpos($name, 'bank') !== false) {
            $bankCommand = $name;
            break;
        }
    }
    
    if ($bankCommand) {
        echo "   ✅ Bank command found: $bankCommand\n";
    } else {
        echo "   ❌ Bank command not found\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "\n🎯 Test completed!\n";
