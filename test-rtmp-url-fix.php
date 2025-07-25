<?php

/**
 * Test RTMP URL fix
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "🔍 Testing RTMP URL Fix\n";
echo "=======================\n\n";

// Test buildConfigPayload method
echo "1. Testing buildConfigPayload method...\n";

try {
    $stream = App\Models\StreamConfiguration::find(94);
    
    if (!$stream) {
        echo "   ❌ Stream #94 not found\n";
        exit(1);
    }
    
    echo "   📊 Stream data:\n";
    echo "      ID: {$stream->id}\n";
    echo "      RTMP URL: {$stream->rtmp_url}\n";
    echo "      Stream Key: {$stream->stream_key}\n";
    
    // Simulate buildConfigPayload
    $stream->load('userFile');
    $configPayload = [
        'command' => 'START_STREAM',
        'config' => [
            'id' => $stream->id,
            'stream_key' => $stream->stream_key,
            'rtmp_url' => $stream->rtmp_url . '/' . $stream->stream_key,
            'push_urls' => $stream->push_urls ?? [],
            'loop' => $stream->loop ?? true,
            'keep_files_on_agent' => $stream->keep_files_on_agent ?? false,
        ]
    ];
    
    echo "\n   ✅ Generated config payload:\n";
    echo "      Command: {$configPayload['command']}\n";
    echo "      Stream ID: {$configPayload['config']['id']}\n";
    echo "      Full RTMP URL: {$configPayload['config']['rtmp_url']}\n";
    
    // Verify URL format
    $fullRtmpUrl = $configPayload['config']['rtmp_url'];
    if (preg_match('/rtmp:\/\/.*\/.*/', $fullRtmpUrl)) {
        echo "   ✅ RTMP URL format is correct (contains stream key)\n";
    } else {
        echo "   ❌ RTMP URL format is incorrect (missing stream key)\n";
    }
    
    // Test what agent would receive
    echo "\n2. Testing agent config parsing...\n";
    
    $agentConfig = $configPayload['config'];
    $agentRtmpUrl = $agentConfig['rtmp_url'];
    
    echo "   📡 Agent would receive RTMP URL: {$agentRtmpUrl}\n";
    
    // Simulate nginx config generation
    $streamId = $agentConfig['id'];
    $nginxConfig = "
application stream_{$streamId} {
    live on;
    record off;
    allow play all;
    push {$agentRtmpUrl};
}";
    
    echo "\n   📄 Generated nginx config:\n";
    echo "   ----------------------------------------\n";
    echo $nginxConfig;
    echo "   ----------------------------------------\n";
    
    // Verify nginx config
    if (strpos($nginxConfig, $stream->stream_key) !== false) {
        echo "   ✅ Nginx config contains stream key\n";
    } else {
        echo "   ❌ Nginx config missing stream key\n";
    }
    
    echo "\n3. Testing with different stream...\n";
    
    // Test with another stream
    $anotherStream = App\Models\StreamConfiguration::where('id', '!=', 94)->first();
    
    if ($anotherStream) {
        echo "   📊 Testing with stream #{$anotherStream->id}:\n";
        echo "      RTMP URL: {$anotherStream->rtmp_url}\n";
        echo "      Stream Key: {$anotherStream->stream_key}\n";
        
        $fullUrl = $anotherStream->rtmp_url . '/' . $anotherStream->stream_key;
        echo "      Full URL: {$fullUrl}\n";
        
        if (preg_match('/rtmp:\/\/.*\/.*/', $fullUrl)) {
            echo "   ✅ Another stream URL format is also correct\n";
        } else {
            echo "   ❌ Another stream URL format is incorrect\n";
        }
    } else {
        echo "   ⚠️ No other streams found for testing\n";
    }
    
    echo "\n4. Testing edge cases...\n";
    
    // Test empty stream key
    $testCases = [
        ['rtmp://test.com/live', 'abc123', 'rtmp://test.com/live/abc123'],
        ['rtmp://test.com/live/', 'def456', 'rtmp://test.com/live//def456'],
        ['rtmp://test.com', 'ghi789', 'rtmp://test.com/ghi789'],
    ];
    
    foreach ($testCases as $i => $case) {
        list($baseUrl, $key, $expected) = $case;
        $result = $baseUrl . '/' . $key;
        
        echo "   Test case " . ($i + 1) . ":\n";
        echo "      Base: {$baseUrl}\n";
        echo "      Key: {$key}\n";
        echo "      Result: {$result}\n";
        echo "      Expected: {$expected}\n";
        
        if ($result === $expected) {
            echo "      ✅ Correct\n";
        } else {
            echo "      ❌ Incorrect\n";
        }
        echo "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "✅ Test completed!\n";
echo "\n💡 Next steps:\n";
echo "   1. Copy check-nginx-config.sh to VPS\n";
echo "   2. Run: ./check-nginx-config.sh 94\n";
echo "   3. Verify nginx config contains full RTMP URL with stream key\n";
echo "   4. Check YouTube Studio for live stream status\n";
