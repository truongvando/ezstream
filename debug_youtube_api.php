<?php
// Debug YouTube API Key on Production
echo "🔍 YouTube API Key Debug\n";
echo "========================\n\n";

// Check .env file
echo "1. Checking .env file:\n";
if (file_exists('.env')) {
    echo "   ✅ .env file exists\n";
    $envContent = file_get_contents('.env');
    
    if (strpos($envContent, 'YOUTUBE_API_KEY_1') !== false) {
        echo "   ✅ YOUTUBE_API_KEY_1 found in .env\n";
    } else {
        echo "   ❌ YOUTUBE_API_KEY_1 NOT found in .env\n";
    }
    
    if (strpos($envContent, 'YOUTUBE_API_CURRENT_KEY_INDEX') !== false) {
        echo "   ✅ YOUTUBE_API_CURRENT_KEY_INDEX found in .env\n";
    } else {
        echo "   ❌ YOUTUBE_API_CURRENT_KEY_INDEX NOT found in .env\n";
    }
} else {
    echo "   ❌ .env file does not exist\n";
}

echo "\n2. Environment Variables:\n";
$key1 = getenv('YOUTUBE_API_KEY_1') ?: $_ENV['YOUTUBE_API_KEY_1'] ?? null;
$index = getenv('YOUTUBE_API_CURRENT_KEY_INDEX') ?: $_ENV['YOUTUBE_API_CURRENT_KEY_INDEX'] ?? null;

echo "   YOUTUBE_API_KEY_1: " . ($key1 ? "Found (" . strlen($key1) . " chars)" : "NOT FOUND") . "\n";
echo "   YOUTUBE_API_CURRENT_KEY_INDEX: " . ($index ?: "NOT FOUND") . "\n";

echo "\n3. Laravel env() function:\n";
require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$envKey1 = env('YOUTUBE_API_KEY_1');
$envIndex = env('YOUTUBE_API_CURRENT_KEY_INDEX');

echo "   env('YOUTUBE_API_KEY_1'): " . ($envKey1 ? "Found (" . strlen($envKey1) . " chars)" : "NOT FOUND") . "\n";
echo "   env('YOUTUBE_API_CURRENT_KEY_INDEX'): " . ($envIndex ?: "NOT FOUND") . "\n";

echo "\n4. Testing ApiKeyRotationService:\n";
try {
    $service = new \App\Services\ApiKeyRotationService();
    $currentKey = $service->getCurrentYouTubeApiKey();
    
    if ($currentKey) {
        echo "   ✅ API Key retrieved successfully (" . strlen($currentKey) . " chars)\n";
        echo "   🔑 Key preview: " . substr($currentKey, 0, 10) . "...\n";
    } else {
        echo "   ❌ API Key is empty\n";
    }
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

echo "\n5. Testing YoutubeApiService:\n";
try {
    $youtube = new \App\Services\YoutubeApiService();
    echo "   ✅ YoutubeApiService initialized successfully\n";
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

echo "\n🎯 Summary:\n";
echo "===========\n";
if ($envKey1 && $currentKey) {
    echo "✅ YouTube API should be working!\n";
} else {
    echo "❌ YouTube API configuration issue detected.\n";
    echo "💡 Try: php artisan config:clear && php artisan config:cache\n";
}
