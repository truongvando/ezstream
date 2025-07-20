<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔍 Checking all streams with scheduling enabled:\n\n";

$streams = App\Models\StreamConfiguration::where('enable_schedule', true)->get();

if ($streams->isEmpty()) {
    echo "❌ No streams with enable_schedule = true found\n";
} else {
    echo "Found {$streams->count()} streams with scheduling enabled:\n\n";
    
    foreach ($streams as $stream) {
        echo "Stream #{$stream->id}: {$stream->title}\n";
        echo "  Status: {$stream->status}\n";
        echo "  Enable Schedule: " . ($stream->enable_schedule ? 'YES' : 'NO') . "\n";
        echo "  Scheduled At: " . ($stream->scheduled_at ?: 'NOT SET') . "\n";
        echo "  Scheduled End: " . ($stream->scheduled_end ?: 'NOT SET') . "\n";
        echo "  Last Started: " . ($stream->last_started_at ?: 'NEVER') . "\n";
        echo "  Last Stopped: " . ($stream->last_stopped_at ?: 'NEVER') . "\n";
        echo "  Error Message: " . ($stream->error_message ?: 'NONE') . "\n";
        
        // Check if would be started by scheduler
        $now = now();
        $wouldStart = $stream->enable_schedule && 
                     $stream->scheduled_at && 
                     $stream->scheduled_at <= $now &&
                     $stream->status === 'INACTIVE';
        
        echo "  🎯 Would be started by scheduler: " . ($wouldStart ? 'YES ⚠️' : 'NO') . "\n";
        echo "  ---\n";
    }
}

// Check for any streams that might be auto-started
echo "\n🚨 Checking for potential auto-start candidates:\n";
$candidates = App\Models\StreamConfiguration::where('enable_schedule', true)
    ->whereNotNull('scheduled_at')
    ->where('scheduled_at', '<=', now())
    ->get();

if ($candidates->count() > 0) {
    echo "⚠️ Found {$candidates->count()} streams that could be auto-started:\n";
    foreach ($candidates as $stream) {
        echo "  - Stream #{$stream->id}: {$stream->title} (Status: {$stream->status})\n";
    }
    echo "\n💡 To prevent auto-start, set enable_schedule = false or scheduled_at = null\n";
} else {
    echo "✅ No streams will be auto-started\n";
}
