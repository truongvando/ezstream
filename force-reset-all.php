<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\StreamConfiguration;

echo "🔥 FORCE RESET ALL STREAMS\n";
echo "========================\n\n";

// 1. Force stop ALL streams in DB
echo "1. Force stopping all streams in database...\n";
$allStreams = StreamConfiguration::whereIn('status', ['STREAMING', 'STARTING', 'STOPPING'])->get();

foreach ($allStreams as $stream) {
    echo "  - Stream #{$stream->id}: {$stream->title} ({$stream->status}) → INACTIVE\n";
    $stream->update([
        'status' => 'INACTIVE',
        'vps_server_id' => null,
        'process_id' => null,
        'error_message' => 'Force reset - manual cleanup'
    ]);
}

echo "✅ {$allStreams->count()} streams forced to INACTIVE\n\n";

// 2. Reset VPS stream counters
echo "2. Resetting VPS stream counters...\n";
try {
    \App\Models\VpsServer::query()->update(['current_streams' => 0]);
    echo "✅ All VPS stream counters reset to 0\n\n";
} catch (Exception $e) {
    echo "❌ Failed to reset VPS counters: {$e->getMessage()}\n\n";
}

// 3. Send KILL ALL command to all VPS
echo "3. Sending KILL ALL commands to agents...\n";
try {
    $redis = app('redis')->connection();
    $vpsServers = \App\Models\VpsServer::where('status', 'active')->get();
    
    foreach ($vpsServers as $vps) {
        $killAllCommand = [
            'command' => 'KILL_ALL_STREAMS',
            'reason' => 'Force reset - cleanup all streams',
            'timestamp' => time()
        ];
        
        $channel = "vps-commands:{$vps->id}";
        $result = $redis->publish($channel, json_encode($killAllCommand));
        echo "  - VPS #{$vps->id}: Sent KILL_ALL (subscribers: {$result})\n";
    }
    
    echo "✅ KILL_ALL commands sent to all VPS\n\n";
} catch (Exception $e) {
    echo "❌ Failed to send KILL_ALL: {$e->getMessage()}\n\n";
}

echo "🎯 RESET COMPLETED!\n";
echo "💡 Now restart the agent on VPS to ensure clean state.\n";
echo "💡 All streams should be INACTIVE and ready to start fresh.\n";
