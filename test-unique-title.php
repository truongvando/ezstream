<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\StreamConfiguration;
use App\Models\User;
use Illuminate\Support\Facades\Log;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== TEST UNIQUE TITLE VALIDATION ===\n\n";

// 1. Kiểm tra constraint đã được thêm
echo "1. KIỂM TRA DATABASE CONSTRAINT:\n";

try {
    $indexes = \DB::select("SHOW INDEX FROM stream_configurations WHERE Key_name = 'unique_user_title'");
    if (!empty($indexes)) {
        echo "   ✅ Unique constraint 'unique_user_title' đã được thêm\n";
        foreach ($indexes as $index) {
            echo "     - Column: {$index->Column_name}\n";
        }
    } else {
        echo "   ❌ Unique constraint chưa được thêm\n";
    }
} catch (\Exception $e) {
    echo "   ❌ Lỗi kiểm tra constraint: " . $e->getMessage() . "\n";
}

echo "\n";

// 2. Test tạo stream với title trùng
echo "2. TEST TẠO STREAM VỚI TITLE TRÙNG:\n";

$user = User::first();
if (!$user) {
    echo "   ❌ Không tìm thấy user để test\n";
    exit(1);
}

echo "   👤 Test với user: {$user->name} (ID: {$user->id})\n";

// Tạo stream đầu tiên
$testTitle = "Test Unique Title " . time();

try {
    $stream1 = StreamConfiguration::create([
        'user_id' => $user->id,
        'title' => $testTitle,
        'description' => 'Test stream 1',
        'video_source_path' => [['file_id' => 1]],
        'rtmp_url' => 'rtmp://test.com/live',
        'stream_key' => 'test-key-1',
        'status' => 'INACTIVE',
    ]);
    
    echo "   ✅ Stream 1 tạo thành công: #{$stream1->id}\n";
    
    // Thử tạo stream thứ 2 với cùng title
    try {
        $stream2 = StreamConfiguration::create([
            'user_id' => $user->id,
            'title' => $testTitle, // Same title
            'description' => 'Test stream 2',
            'video_source_path' => [['file_id' => 1]],
            'rtmp_url' => 'rtmp://test.com/live',
            'stream_key' => 'test-key-2',
            'status' => 'INACTIVE',
        ]);
        
        echo "   ❌ Stream 2 tạo thành công - CONSTRAINT KHÔNG HOẠT ĐỘNG!\n";
        
        // Cleanup
        $stream2->delete();
        
    } catch (\Illuminate\Database\QueryException $e) {
        if (str_contains($e->getMessage(), 'unique_user_title')) {
            echo "   ✅ Stream 2 bị từ chối - CONSTRAINT HOẠT ĐỘNG ĐÚNG!\n";
            echo "     Error: Duplicate entry for unique constraint\n";
        } else {
            echo "   ⚠️ Stream 2 bị từ chối nhưng không phải do unique constraint:\n";
            echo "     Error: " . $e->getMessage() . "\n";
        }
    }
    
    // Cleanup
    $stream1->delete();
    echo "   🧹 Đã xóa test streams\n";
    
} catch (\Exception $e) {
    echo "   ❌ Lỗi tạo stream 1: " . $e->getMessage() . "\n";
}

echo "\n";

// 3. Test với user khác nhau
echo "3. TEST VỚI USER KHÁC NHAU:\n";

$users = User::take(2)->get();
if ($users->count() < 2) {
    echo "   ⚠️ Cần ít nhất 2 users để test\n";
} else {
    $user1 = $users[0];
    $user2 = $users[1];
    
    echo "   👤 User 1: {$user1->name} (ID: {$user1->id})\n";
    echo "   👤 User 2: {$user2->name} (ID: {$user2->id})\n";
    
    $sharedTitle = "Shared Title " . time();
    
    try {
        // Tạo stream cho user 1
        $stream1 = StreamConfiguration::create([
            'user_id' => $user1->id,
            'title' => $sharedTitle,
            'description' => 'Stream của user 1',
            'video_source_path' => [['file_id' => 1]],
            'rtmp_url' => 'rtmp://test.com/live',
            'stream_key' => 'test-key-user1',
            'status' => 'INACTIVE',
        ]);
        
        echo "   ✅ Stream user 1 tạo thành công: #{$stream1->id}\n";
        
        // Tạo stream cho user 2 với cùng title
        $stream2 = StreamConfiguration::create([
            'user_id' => $user2->id,
            'title' => $sharedTitle, // Same title but different user
            'description' => 'Stream của user 2',
            'video_source_path' => [['file_id' => 1]],
            'rtmp_url' => 'rtmp://test.com/live',
            'stream_key' => 'test-key-user2',
            'status' => 'INACTIVE',
        ]);
        
        echo "   ✅ Stream user 2 tạo thành công: #{$stream2->id}\n";
        echo "   ✅ Constraint cho phép cùng title với user khác nhau\n";
        
        // Cleanup
        $stream1->delete();
        $stream2->delete();
        echo "   🧹 Đã xóa test streams\n";
        
    } catch (\Exception $e) {
        echo "   ❌ Lỗi: " . $e->getMessage() . "\n";
    }
}

echo "\n";

// 4. Kiểm tra streams hiện tại
echo "4. KIỂM TRA STREAMS HIỆN TẠI:\n";

$allStreams = StreamConfiguration::with('user')->get();
$titleGroups = $allStreams->groupBy(function($stream) {
    return $stream->user_id . '|' . $stream->title;
});

$duplicates = $titleGroups->filter(function($group) {
    return $group->count() > 1;
});

if ($duplicates->isEmpty()) {
    echo "   ✅ Không có streams nào có title trùng nhau trong cùng user\n";
} else {
    echo "   ⚠️ Vẫn còn " . $duplicates->count() . " nhóm streams có title trùng:\n";
    foreach ($duplicates as $key => $streams) {
        list($userId, $title) = explode('|', $key);
        echo "     - User #{$userId}: \"{$title}\" ({$streams->count()} streams)\n";
    }
}

echo "\n=== KẾT THÚC TEST ===\n";
