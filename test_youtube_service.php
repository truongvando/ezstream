<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    echo "Testing YouTube API Service...\n";
    
    $youtube = new App\Services\YoutubeApiService();
    echo "✅ Service loaded successfully\n";
    
    $testUrl = 'https://www.youtube.com/@trieuphongsoicau';
    echo "Testing URL: {$testUrl}\n";
    
    $channelId = $youtube->extractChannelId($testUrl);
    echo "Channel ID: " . ($channelId ?: 'NOT FOUND') . "\n";
    
    if ($channelId) {
        echo "✅ SUCCESS! Found channel: {$channelId}\n";
    } else {
        echo "❌ FAILED! Could not extract channel ID\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
