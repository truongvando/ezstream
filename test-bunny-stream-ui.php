#!/usr/bin/env php
<?php
/**
 * Test Script for Bunny Stream Video Display in UI
 * Kiểm tra xem video Bunny Stream có hiển thị đúng trong quick-stream-modal không
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🧪 Testing Bunny Stream Video Display in UI\n";
echo "==========================================\n\n";

// Test 1: Check Bunny Stream videos in database
echo "📁 1. CHECKING BUNNY STREAM VIDEOS IN DATABASE\n";
$bunnyVideos = \App\Models\UserFile::where('disk', 'bunny_stream')
    ->whereNotNull('stream_video_id')
    ->orderBy('created_at', 'desc')
    ->get();

if ($bunnyVideos->isEmpty()) {
    echo "❌ No Bunny Stream videos found in database\n";
    echo "💡 Upload a video to Bunny Stream first\n";
    exit(1);
}

echo "✅ Found {$bunnyVideos->count()} Bunny Stream videos\n\n";

// Test 2: Check processing status
echo "🔍 2. CHECKING PROCESSING STATUS\n";
foreach ($bunnyVideos->take(5) as $video) {
    $processingStatus = $video->stream_metadata['processing_status'] ?? 'unknown';
    $canSelect = in_array($processingStatus, ['finished', 'completed', 'ready']);
    
    echo "📹 {$video->original_name}\n";
    echo "   ID: {$video->id}\n";
    echo "   Status: {$processingStatus}\n";
    echo "   Can Select: " . ($canSelect ? '✅ YES' : '❌ NO') . "\n";
    echo "   Created: {$video->created_at->diffForHumans()}\n\n";
}

// Test 3: Test getUserFiles() method
echo "🔍 3. TESTING getUserFiles() METHOD\n";
try {
    $userId = \App\Models\User::first()->id ?? 1;
    
    $userFiles = \App\Models\UserFile::where('user_id', $userId)
        ->where(function($query) {
            $query->where('status', 'ready')
                  ->orWhere(function($q) {
                      // Include Bunny Stream files that are finished processing
                      $q->where('disk', 'bunny_stream')
                        ->whereJsonContains('stream_metadata->processing_status', 'finished');
                  })
                  ->orWhere(function($q) {
                      // Include Bunny Stream files that are completed
                      $q->where('disk', 'bunny_stream')
                        ->whereJsonContains('stream_metadata->processing_status', 'completed');
                  });
        })
        ->latest()
        ->get();
    
    echo "✅ getUserFiles() method returned {$userFiles->count()} files\n";
    
    $bunnyCount = $userFiles->where('disk', 'bunny_stream')->count();
    $regularCount = $userFiles->where('disk', '!=', 'bunny_stream')->count();
    
    echo "   📺 Bunny Stream files: {$bunnyCount}\n";
    echo "   📁 Regular files: {$regularCount}\n\n";
    
} catch (Exception $e) {
    echo "❌ getUserFiles() test failed: {$e->getMessage()}\n\n";
}

// Test 4: Test Livewire component data
echo "🔍 4. TESTING LIVEWIRE COMPONENT DATA\n";
try {
    // Simulate what UserStreamManager does
    $user = \App\Models\User::first();
    if (!$user) {
        echo "❌ No users found in database\n";
    } else {
        echo "✅ Testing with user: {$user->name} (ID: {$user->id})\n";
        
        // Get files like the component does
        $userFiles = $user->files()
            ->where(function($query) {
                $query->where('status', 'ready')
                      ->orWhere(function($q) {
                          $q->where('disk', 'bunny_stream')
                            ->whereJsonContains('stream_metadata->processing_status', 'finished');
                      })
                      ->orWhere(function($q) {
                          $q->where('disk', 'bunny_stream')
                            ->whereJsonContains('stream_metadata->processing_status', 'completed');
                      });
            })
            ->latest()
            ->get();
        
        echo "✅ Component would show {$userFiles->count()} files\n";
        
        foreach ($userFiles->where('disk', 'bunny_stream')->take(3) as $file) {
            $processingStatus = $file->stream_metadata['processing_status'] ?? 'processing';
            $canSelect = in_array($processingStatus, ['finished', 'completed', 'ready']);
            
            echo "   📹 {$file->original_name}\n";
            echo "      Status: {$processingStatus}\n";
            echo "      Selectable: " . ($canSelect ? '✅' : '❌') . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Livewire component test failed: {$e->getMessage()}\n";
}

echo "\n";

// Test 5: Check cache
echo "🔍 5. CHECKING CACHE\n";
try {
    $cacheKeys = [
        'users_files_' . ($user->id ?? 1),
        'stream_configurations',
        'livewire_components'
    ];
    
    foreach ($cacheKeys as $key) {
        if (\Illuminate\Support\Facades\Cache::has($key)) {
            echo "⚠️ Cache key exists: {$key}\n";
        } else {
            echo "✅ Cache key clean: {$key}\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Cache check failed: {$e->getMessage()}\n";
}

echo "\n";

// Summary
echo "📊 SUMMARY\n";
echo "==========\n";
$readyVideos = $bunnyVideos->filter(function($video) {
    $status = $video->stream_metadata['processing_status'] ?? 'unknown';
    return in_array($status, ['finished', 'completed', 'ready']);
});

echo "Total Bunny Stream videos: {$bunnyVideos->count()}\n";
echo "Ready for selection: {$readyVideos->count()}\n";

if ($readyVideos->count() > 0) {
    echo "✅ Videos should appear in quick-stream-modal\n";
} else {
    echo "❌ No videos ready for selection\n";
    echo "💡 Wait for videos to finish processing or check Bunny API status\n";
}

echo "\n💡 If videos don't appear in UI:\n";
echo "   1. Run: bash scripts/clear-cache.sh\n";
echo "   2. Hard refresh browser (Ctrl+F5)\n";
echo "   3. Check browser console for errors\n";
echo "   4. Verify user permissions\n";
