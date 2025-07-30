<?php
// Simple test without Laravel
$handle = 'trieuphongsoicau';
$apiKey = 'AIzaSyAZsUCvHOGHYoUuVBMZsKJSXuDH5Czj1qw';

echo "Testing YouTube handle: @{$handle}\n";

// Test 1: Search API
$searchUrl = "https://www.googleapis.com/youtube/v3/search?" . http_build_query([
    'key' => $apiKey,
    'q' => "@{$handle}",
    'type' => 'channel',
    'part' => 'snippet',
    'maxResults' => 5
]);

echo "Search URL: {$searchUrl}\n\n";

$response = file_get_contents($searchUrl);
$data = json_decode($response, true);

echo "Search API Response:\n";
echo json_encode($data, JSON_PRETTY_PRINT) . "\n\n";

// Test 2: Try without @
$searchUrl2 = "https://www.googleapis.com/youtube/v3/search?" . http_build_query([
    'key' => $apiKey,
    'q' => $handle,
    'type' => 'channel',
    'part' => 'snippet',
    'maxResults' => 5
]);

echo "Search without @ URL: {$searchUrl2}\n\n";

$response2 = file_get_contents($searchUrl2);
$data2 = json_decode($response2, true);

echo "Search without @ Response:\n";
echo json_encode($data2, JSON_PRETTY_PRINT) . "\n";
