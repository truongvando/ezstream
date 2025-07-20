<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Check if stream 69 still exists, if not find any STOPPING stream
$stream = App\Models\StreamConfiguration::find(69);
if (!$stream || $stream->status !== 'STOPPING') {
    $stream = App\Models\StreamConfiguration::where('status', 'STOPPING')->first();
}

if (!$stream) {
    echo "No STOPPING streams found. Creating test stream...\n";
    $stream = App\Models\StreamConfiguration::create([
        'user_id' => 1,
        'title' => 'Test Timeout Stream',
        'description' => 'Test stream for timeout mechanism',
        'rtmp_url' => 'rtmp://test.example.com/live',
        'stream_key' => 'test-key-' . time(),
        'status' => 'STOPPING',
        'last_stopped_at' => now()->subMinutes(5), // 5 minutes ago
        'vps_server_id' => 1,
        'enable_schedule' => false
    ]);
    echo "Created test stream #{$stream->id}\n";
}

if ($stream) {
    echo "Stream #{$stream->id}: {$stream->title}\n";
    echo "Status: {$stream->status}\n";
    echo "Last stopped at: {$stream->last_stopped_at}\n";
    echo "Current time: " . now() . "\n";
    echo "App timezone: " . config('app.timezone') . "\n";
    echo "PHP timezone: " . date_default_timezone_get() . "\n";

    $minutesSinceStop = now()->diffInMinutes($stream->last_stopped_at);
    $minutesSinceStopAbs = abs($minutesSinceStop);
    echo "Minutes since stop: {$minutesSinceStop} (abs: {$minutesSinceStopAbs})\n";
    echo "Should timeout: " . ($minutesSinceStopAbs > 2 ? 'YES' : 'NO') . "\n";

    // Test manual timeout
    if ($minutesSinceStopAbs > 2 && $stream->status === 'STOPPING') {
        echo "\n🚨 TIMEOUT DETECTED! Manually fixing...\n";
        $stream->update([
            'status' => 'INACTIVE',
            'error_message' => "Manual timeout fix - stuck in STOPPING for {$minutesSinceStopAbs} minutes",
            'vps_server_id' => null,
            'process_id' => null
        ]);
        echo "✅ Stream status updated to INACTIVE\n";
    }
} else {
    echo "Stream not found\n";
}
