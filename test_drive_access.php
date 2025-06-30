<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Http;

echo "=== TESTING GOOGLE DRIVE ACCESS ===\n\n";

$fileId = '1_hRXJ1xhYJdigbyY8nrwzQXeWOtLIgOQ';

$testUrls = [
    'drive.google.com' => "https://drive.google.com/uc?export=download&id={$fileId}",
    'googleapis.com' => "https://www.googleapis.com/drive/v3/files/{$fileId}",
];

foreach ($testUrls as $domain => $url) {
    echo "Testing {$domain}...\n";
    
    try {
        $response = Http::timeout(10)->head($url);
        
        if ($response->successful()) {
            echo "✅ {$domain}: SUCCESS (Status: {$response->status()})\n";
            echo "   Content-Type: " . $response->header('Content-Type') . "\n";
            echo "   Content-Length: " . $response->header('Content-Length') . "\n";
        } else {
            echo "❌ {$domain}: HTTP Error {$response->status()}\n";
        }
    } catch (\Exception $e) {
        echo "❌ {$domain}: FAILED - " . $e->getMessage() . "\n";
    }
    
    echo "\n";
} 