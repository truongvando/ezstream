<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔧 Fixing auto-start issue for Stream #69\n\n";

$stream = App\Models\StreamConfiguration::find(69);

if ($stream) {
    echo "Before fix:\n";
    echo "  Status: {$stream->status}\n";
    echo "  Enable Schedule: " . ($stream->enable_schedule ? 'YES' : 'NO') . "\n";
    echo "  Scheduled At: " . ($stream->scheduled_at ?: 'NOT SET') . "\n";
    echo "  Scheduled End: " . ($stream->scheduled_end ?: 'NOT SET') . "\n";
    
    // Fix: Disable auto-start
    $stream->update([
        'enable_schedule' => false,  // Disable scheduler
        'scheduled_at' => null,      // Remove start time
        'scheduled_end' => null,     // Remove end time
        'error_message' => null      // Clear error
    ]);
    
    echo "\n✅ Fixed! Stream will no longer auto-start\n";
    echo "After fix:\n";
    echo "  Status: {$stream->status}\n";
    echo "  Enable Schedule: " . ($stream->enable_schedule ? 'YES' : 'NO') . "\n";
    echo "  Scheduled At: " . ($stream->scheduled_at ?: 'NOT SET') . "\n";
    echo "  Scheduled End: " . ($stream->scheduled_end ?: 'NOT SET') . "\n";
    
} else {
    echo "❌ Stream #69 not found\n";
}
