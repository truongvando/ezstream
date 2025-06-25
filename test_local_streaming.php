<?php
require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::capture();
$response = $kernel->handle($request);

use App\Services\LocalStreamingService;

echo "🎬 VPS LIVE STREAM - LOCAL FFMPEG TESTING\n";
echo "=========================================\n\n";

$localStreaming = new LocalStreamingService();

// Test 1: Check FFmpeg installation
echo "1️⃣ Checking FFmpeg installation...\n";
$ffmpegCheck = $localStreaming->testFFmpegInstallation();
if ($ffmpegCheck['success']) {
    echo "✅ FFmpeg {$ffmpegCheck['version']} installed\n";
    echo "   H.264 codec: " . ($ffmpegCheck['codecs']['h264'] ? '✅' : '❌') . "\n";
    echo "   AAC codec: " . ($ffmpegCheck['codecs']['aac'] ? '✅' : '❌') . "\n";
} else {
    echo "❌ FFmpeg not found: {$ffmpegCheck['error']}\n";
    echo "Please install FFmpeg first!\n";
    exit(1);
}

// Test 2: Generate test video
echo "\n2️⃣ Generating test video...\n";
$testVideoPath = storage_path('app/test_video.mp4');
$generateResult = $localStreaming->generateTestVideo($testVideoPath, 10);
if ($generateResult['success']) {
    echo "✅ Test video generated: {$testVideoPath}\n";
    echo "   Size: " . number_format($generateResult['size'] / 1024 / 1024, 2) . " MB\n";
} else {
    echo "❌ Failed to generate test video: {$generateResult['error']}\n";
}

// Test 3: Test local file streaming
if (file_exists($testVideoPath)) {
    echo "\n3️⃣ Testing local file streaming...\n";
    echo "   Input: {$testVideoPath}\n";
    echo "   Output: rtmp://localhost/live/test\n";
    echo "   Duration: 10 seconds\n";
    echo "   Preset: optimized\n\n";
    
    $streamResult = $localStreaming->testLocalStream([
        'input_file' => $testVideoPath,
        'output_url' => 'rtmp://localhost/live/test',
        'preset' => 'optimized',
        'duration' => 10
    ]);
    
    if ($streamResult['success']) {
        echo "✅ Stream started! PID: {$streamResult['pid']}\n";
        echo "\n📋 FFmpeg Command:\n";
        echo $streamResult['command'] . "\n";
        
        echo "\n📊 Waiting for stream to process...\n";
        
        // Monitor for 10 seconds
        for ($i = 1; $i <= 10; $i++) {
            sleep(1);
            $status = $localStreaming->getStreamStatus($streamResult['pid']);
            
            if ($status['success'] && $status['running']) {
                echo "   {$i}s - Stream running...\n";
                
                // Show any new output
                if (!empty($status['error'])) {
                    echo "   Output: " . substr($status['error'], -100) . "\n";
                }
            } else {
                echo "   Stream stopped at {$i}s\n";
                break;
            }
        }
        
        // Stop stream
        echo "\n🛑 Stopping stream...\n";
        $stopResult = $localStreaming->stopLocalStream($streamResult['pid']);
        if ($stopResult['success']) {
            echo "✅ Stream stopped successfully\n";
        }
        
    } else {
        echo "❌ Failed to start stream: {$streamResult['error']}\n";
    }
}

// Test 4: Test Google Drive streaming (if file ID provided)
echo "\n4️⃣ Google Drive Streaming Test\n";
echo "To test Google Drive streaming, run:\n";
echo "php test_local_streaming.php <google_drive_file_id>\n";

if (isset($argv[1])) {
    $fileId = $argv[1];
    echo "\n🌐 Testing Google Drive stream for file ID: {$fileId}\n";
    
    $gdResult = $localStreaming->testGoogleDriveStream($fileId, [
        'output_url' => 'rtmp://localhost/live/gdrive',
        'preset' => 'optimized',
        'duration' => 15
    ]);
    
    if ($gdResult['success']) {
        echo "✅ Google Drive stream started! PID: {$gdResult['pid']}\n";
        echo "   Stream URL method: {$gdResult['stream_method']}\n";
        echo "\n📋 FFmpeg Command:\n";
        echo $gdResult['command'] . "\n";
        
        // Monitor
        echo "\n📊 Monitoring stream...\n";
        for ($i = 1; $i <= 15; $i++) {
            sleep(1);
            $status = $localStreaming->getStreamStatus($gdResult['pid']);
            
            if ($status['success'] && $status['running']) {
                echo "   {$i}s - Stream running from Google Drive...\n";
            } else {
                echo "   Stream stopped at {$i}s\n";
                break;
            }
        }
        
        // Stop
        $localStreaming->stopLocalStream($gdResult['pid']);
        echo "✅ Google Drive stream test completed\n";
        
    } else {
        echo "❌ Failed to start Google Drive stream: {$gdResult['error']}\n";
    }
}

// Test 5: Test different presets
echo "\n5️⃣ Testing Different Streaming Presets\n";
$presets = ['direct', 'optimized', 'low_latency', 'youtube', 'facebook'];

foreach ($presets as $preset) {
    echo "\n   Testing preset: {$preset}\n";
    
    $presetResult = $localStreaming->testLocalStream([
        'input_file' => $testVideoPath,
        'output_url' => "rtmp://localhost/live/preset_{$preset}",
        'preset' => $preset,
        'duration' => 5
    ]);
    
    if ($presetResult['success']) {
        echo "   ✅ {$preset} preset working - PID: {$presetResult['pid']}\n";
        
        // Let it run for 2 seconds then stop
        sleep(2);
        $localStreaming->stopLocalStream($presetResult['pid']);
    } else {
        echo "   ❌ {$preset} preset failed\n";
    }
}

// Cleanup
echo "\n🧹 Cleaning up test files...\n";
if (file_exists($testVideoPath)) {
    unlink($testVideoPath);
    echo "✅ Test video deleted\n";
}

echo "\n✨ Testing completed!\n";
echo "\n💡 Tips:\n";
echo "- Install a local RTMP server (like nginx-rtmp) to test actual streaming\n";
echo "- Use OBS Studio to receive the RTMP stream\n";
echo "- Monitor FFmpeg output for encoding stats\n";
echo "- Test with real video files for production scenarios\n"; 