<?php

// Simple API test without Laravel bootstrap
echo "🌐 Testing Bunny Stream API (Simple)...\n";

// Config from .env
$apiKey = 'fa483e83-fe7c-46f6-8906695a8d83-b93c-4c4c';
$libraryId = '476035';
$apiUrl = 'https://video.bunnycdn.com';

// Test with a sample video ID (replace with real one)
$videoId = 'test-video-id';

echo "📡 API URL: {$apiUrl}\n";
echo "📚 Library ID: {$libraryId}\n";
echo "🔑 API Key: " . substr($apiKey, 0, 8) . "...\n";
echo "🆔 Video ID: {$videoId}\n";
echo str_repeat('=', 60) . "\n";

// Build URL
$url = "{$apiUrl}/library/{$libraryId}/videos/{$videoId}";
echo "🌐 Full URL: {$url}\n";

// Prepare headers
$headers = [
    'AccessKey: ' . $apiKey,
    'Accept: application/json',
    'Content-Type: application/json'
];

echo "\n📤 SENDING REQUEST...\n";
echo "Headers:\n";
foreach ($headers as $header) {
    if (strpos($header, 'AccessKey') !== false) {
        echo "  AccessKey: " . substr($apiKey, 0, 8) . "...\n";
    } else {
        echo "  {$header}\n";
    }
}

// Use cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "\n📥 RESPONSE RECEIVED:\n";
echo "HTTP Code: {$httpCode}\n";

if ($error) {
    echo "❌ cURL Error: {$error}\n";
} else {
    echo "✅ Request successful\n";
    
    if ($httpCode == 200) {
        echo "\n📊 RESPONSE BODY:\n";
        echo $response . "\n";
        
        $data = json_decode($response, true);
        if ($data) {
            echo "\n🎯 PARSED DATA:\n";
            if (isset($data['status'])) {
                echo "Status (numeric): {$data['status']}\n";
                echo "Encode Progress: " . ($data['encodeProgress'] ?? 'N/A') . "\n";
                echo "Title: " . ($data['title'] ?? 'N/A') . "\n";
                
                // Status mapping
                $statusMap = [
                    0 => 'created',
                    1 => 'processing', 
                    2 => 'error',
                    3 => 'finished',
                    4 => 'finished'
                ];
                
                $status = $data['status'];
                $statusString = $statusMap[$status] ?? 'unknown';
                echo "Status String: {$statusString}\n";
            } else {
                echo "Available fields: " . implode(', ', array_keys($data)) . "\n";
            }
        } else {
            echo "❌ Failed to parse JSON response\n";
        }
    } else {
        echo "❌ HTTP Error {$httpCode}:\n";
        echo $response . "\n";
    }
}

echo str_repeat('=', 60) . "\n";

// Test what we send vs what we get
echo "🔍 ANALYSIS:\n";
echo "📤 What we SEND:\n";
echo "  - GET request to: {$url}\n";
echo "  - AccessKey header with API key\n";
echo "  - Accept: application/json\n";

echo "\n📥 What we EXPECT to GET:\n";
echo "  - HTTP 200 status\n";
echo "  - JSON response with:\n";
echo "    * status: 0-4 (numeric)\n";
echo "    * encodeProgress: 0-100\n";
echo "    * title: video title\n";
echo "    * length: duration in seconds\n";
echo "    * dateUploaded: upload timestamp\n";

echo "\n🔄 How we COMPARE:\n";
echo "  1. Get numeric status from API\n";
echo "  2. Map to string: 0=created, 1=processing, 2=error, 3/4=finished\n";
echo "  3. Compare with database 'processing_status' field\n";
echo "  4. If API=finished but DB=processing → video ready!\n";

echo "\n💡 NEXT STEPS:\n";
echo "  1. Upload a real video to get a real video ID\n";
echo "  2. Replace 'test-video-id' with real ID\n";
echo "  3. Run this script again to see real API response\n";

echo "\n✅ Test completed!\n";
