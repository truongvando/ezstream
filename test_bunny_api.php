<?php

require 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🌐 Testing Bunny Stream API...\n";
echo str_repeat('=', 60) . "\n";

// Get config
$apiKey = config('bunnycdn.stream_api_key');
$apiUrl = config('bunnycdn.stream_api_url');
$libraryId = config('bunnycdn.video_library_id');

echo "📡 API URL: {$apiUrl}\n";
echo "📚 Library ID: {$libraryId}\n";
echo "🔑 API Key: " . (empty($apiKey) ? '❌ MISSING' : '✅ Present (' . substr($apiKey, 0, 8) . '...)') . "\n";

if (empty($apiKey) || empty($libraryId)) {
    echo "❌ Missing API configuration!\n";
    exit(1);
}

// Find a video to test
$file = \App\Models\UserFile::where('disk', 'bunny_stream')
    ->whereNotNull('stream_video_id')
    ->orderBy('created_at', 'desc')
    ->first();

if (!$file) {
    echo "❌ No Stream Library videos found. Upload a video first.\n";
    exit(1);
}

$videoId = $file->stream_video_id;
echo "📁 Testing with file: {$file->original_name}\n";
echo "🆔 Video ID: {$videoId}\n";
echo str_repeat('=', 60) . "\n";

// Test 1: Raw API call
echo "🔍 1. RAW API CALL\n";

$url = "{$apiUrl}/library/{$libraryId}/videos/{$videoId}";
echo "🌐 Full URL: {$url}\n";

try {
    echo "\n📤 SENDING REQUEST...\n";
    echo "Headers: AccessKey: " . substr($apiKey, 0, 8) . "...\n";
    
    $response = \Illuminate\Support\Facades\Http::withHeaders([
        'AccessKey' => $apiKey,
        'Accept' => 'application/json'
    ])->get($url);
    
    echo "📥 RESPONSE RECEIVED:\n";
    echo "Status Code: {$response->status()}\n";
    echo "Success: " . ($response->successful() ? '✅ Yes' : '❌ No') . "\n";
    
    if ($response->successful()) {
        $data = $response->json();
        echo "\n📊 RESPONSE DATA:\n";
        echo json_encode($data, JSON_PRETTY_PRINT) . "\n";
        
        // Extract key fields
        if (isset($data['status'])) {
            echo "\n🎯 KEY FIELDS:\n";
            echo "Status (numeric): {$data['status']}\n";
            echo "Encode Progress: " . ($data['encodeProgress'] ?? 'N/A') . "\n";
            echo "Title: " . ($data['title'] ?? 'N/A') . "\n";
            echo "Length: " . ($data['length'] ?? 'N/A') . " seconds\n";
            echo "Created: " . ($data['dateUploaded'] ?? 'N/A') . "\n";
            
            // Status mapping
            echo "\n📋 STATUS MAPPING:\n";
            $statusMap = [
                0 => 'created (Video created, no file uploaded)',
                1 => 'processing (Video being processed/encoded)',
                2 => 'error (Processing failed)',
                3 => 'finished (Processing completed)',
                4 => 'finished (Processing completed - alternative)'
            ];
            
            $currentStatus = $data['status'];
            echo "Current Status: {$currentStatus} = " . ($statusMap[$currentStatus] ?? 'unknown') . "\n";
        }
    } else {
        echo "❌ Error Response:\n";
        echo $response->body() . "\n";
    }
    
} catch (\Exception $e) {
    echo "💥 Exception: " . $e->getMessage() . "\n";
}

echo str_repeat('=', 60) . "\n";

// Test 2: Service method
echo "🔍 2. SERVICE METHOD CALL\n";

try {
    $bunnyService = app(\App\Services\BunnyStreamService::class);
    
    echo "📤 Calling BunnyStreamService::getVideoStatus()...\n";
    $result = $bunnyService->getVideoStatus($videoId);
    
    echo "📥 SERVICE RESPONSE:\n";
    echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
    
    if ($result['success']) {
        echo "\n🎯 MAPPED STATUS:\n";
        echo "Numeric Status: {$result['numeric_status']}\n";
        echo "String Status: {$result['status']}\n";
        echo "Progress: {$result['encoding_progress']}%\n";
    }
    
} catch (\Exception $e) {
    echo "💥 Service Exception: " . $e->getMessage() . "\n";
}

echo str_repeat('=', 60) . "\n";

// Test 3: Database comparison
echo "🔍 3. DATABASE COMPARISON\n";

echo "📁 File: {$file->original_name}\n";
echo "🆔 File ID: {$file->id}\n";
echo "📅 Created: {$file->created_at}\n";
echo "💾 Status: {$file->status}\n";

if ($file->stream_metadata) {
    echo "\n📊 DATABASE METADATA:\n";
    foreach ($file->stream_metadata as $key => $value) {
        echo "  {$key}: {$value}\n";
    }
    
    $dbStatus = $file->stream_metadata['processing_status'] ?? 'unknown';
    echo "\n🔄 COMPARISON:\n";
    echo "Database Status: {$dbStatus}\n";
    
    // Get fresh API status
    try {
        $bunnyService = app(\App\Services\BunnyStreamService::class);
        $apiResult = $bunnyService->getVideoStatus($file->stream_video_id);
        
        if ($apiResult['success']) {
            $apiStatus = $apiResult['status'];
            echo "API Status: {$apiStatus}\n";
            
            if ($apiStatus === $dbStatus) {
                echo "✅ Status MATCH\n";
            } else {
                echo "⚠️  Status MISMATCH!\n";
                echo "This means database is outdated!\n";
                
                if (in_array($apiStatus, ['finished', 'completed'])) {
                    echo "💡 Video is ready but database not updated\n";
                    echo "Run: php artisan tinker --execute=\"\\App\\Jobs\\CheckVideoProcessingJob::dispatch({$file->id})\"\n";
                }
            }
        } else {
            echo "❌ API call failed: " . $apiResult['error'] . "\n";
        }
        
    } catch (\Exception $e) {
        echo "💥 Comparison failed: " . $e->getMessage() . "\n";
    }
} else {
    echo "⚠️  No metadata found in database\n";
}

// Check related streams
$waitingStreams = \App\Models\StreamConfiguration::where('status', 'waiting_for_processing')
    ->where(function($query) use ($file) {
        $query->where('user_file_id', $file->id)
              ->orWhereJsonContains('video_source_path', [['file_id' => $file->id]]);
    })
    ->get();
    
if ($waitingStreams->count() > 0) {
    echo "\n⏳ WAITING STREAMS ({$waitingStreams->count()}):\n";
    foreach ($waitingStreams as $stream) {
        echo "  Stream #{$stream->id}: {$stream->title}\n";
    }
} else {
    echo "\n✅ No streams waiting for this video\n";
}

echo str_repeat('=', 60) . "\n";
echo "✅ Test completed!\n";
