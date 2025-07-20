<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Create test stream stuck in STOPPING
$stream = App\Models\StreamConfiguration::create([
    'user_id' => 1,
    'title' => 'Test Timeout Stream',
    'description' => 'Test stream for timeout mechanism',
    'rtmp_url' => 'rtmp://test.example.com/live',
    'stream_key' => 'test-key-' . time(),
    'video_source_path' => '/tmp/test.mp4',
    'status' => 'STOPPING',
    'last_stopped_at' => now()->subMinutes(5), // 5 minutes ago - should timeout
    'vps_server_id' => null,
    'enable_schedule' => false
]);

echo "Created test stream #{$stream->id}\n";
echo "Status: {$stream->status}\n";
echo "Last stopped at: {$stream->last_stopped_at}\n";
echo "Minutes since stop: " . abs(now()->diffInMinutes($stream->last_stopped_at)) . "\n";
echo "Should timeout: YES (>2 minutes)\n";
echo "\nNow run: php artisan test:stopping-timeout\n";
