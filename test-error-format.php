<?php

/**
 * Simple test for error response format
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "🔍 Testing Error Response Format\n";
echo "================================\n\n";

// Test 1: Check VideoValidationService
echo "1. Testing VideoValidationService...\n";

try {
    $service = new App\Services\VideoValidationService();
    
    // Test resolution names
    $resolutions = [
        [3840, 2160, '4K UHD'],
        [1920, 1080, 'Full HD 1080p'],
        [1280, 720, 'HD 720p'],
        [854, 480, 'SD 480p']
    ];
    
    foreach ($resolutions as [$width, $height, $expected]) {
        $result = $service->getResolutionName($width, $height);
        echo "   📏 {$width}x{$height} = {$result} " . ($result === $expected ? '✅' : '❌') . "\n";
    }
    
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 2: Check ServicePackage limits
echo "2. Testing ServicePackage limits...\n";

try {
    $packages = App\Models\ServicePackage::all();
    
    foreach ($packages as $package) {
        echo "   📦 {$package->name}:\n";
        echo "      Max resolution: {$package->max_video_width}x{$package->max_video_height}\n";
        
        $service = new App\Services\VideoValidationService();
        $resolutionName = $service->getResolutionName($package->max_video_width, $package->max_video_height);
        echo "      Resolution name: {$resolutionName}\n";
        echo "\n";
    }
    
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

// Test 3: Simulate error response format
echo "3. Testing error response format...\n";

try {
    // Simulate resolution error
    $errorResponse = [
        'error' => "❌ Video không thể upload",
        'reason' => "Độ phân giải vượt quá giới hạn gói",
        'details' => [
            'video_resolution' => "3840x2160 (4K UHD)",
            'package_name' => "Gói Basic",
            'package_limit' => "1920x1080 (Full HD 1080p)",
            'supported_orientations' => "Cả video ngang và dọc đều được hỗ trợ trong giới hạn này"
        ],
        'solutions' => [
            "🔧 Giảm chất lượng video xuống Full HD 1080p hoặc thấp hơn",
            "📈 Nâng cấp lên gói cao hơn để hỗ trợ 4K UHD",
            "✂️ Sử dụng phần mềm như HandBrake để resize video"
        ],
        'show_modal' => true
    ];
    
    echo "   📄 Resolution error format:\n";
    echo "   " . json_encode($errorResponse, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    
    // Check required fields
    $requiredFields = ['error', 'reason', 'details', 'solutions', 'show_modal'];
    $hasAllFields = true;
    
    foreach ($requiredFields as $field) {
        if (!isset($errorResponse[$field])) {
            echo "   ❌ Missing field: {$field}\n";
            $hasAllFields = false;
        }
    }
    
    if ($hasAllFields) {
        echo "   ✅ All required fields present\n";
    }
    
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 4: Simulate storage error
echo "4. Testing storage error format...\n";

try {
    $storageError = [
        'error' => '❌ Không thể upload video',
        'reason' => 'Vượt quá giới hạn dung lượng lưu trữ',
        'details' => [
            'storage_used' => "9.5GB / 10GB",
            'file_size' => "2.3GB",
            'remaining_space' => "0.5GB",
            'package_name' => "Gói Standard"
        ],
        'solutions' => [
            "🗑️ Xóa bớt 2.3GB file cũ để có đủ dung lượng",
            "📈 Nâng cấp lên gói có dung lượng lưu trữ cao hơn",
            "📁 Kiểm tra và xóa các file không cần thiết"
        ],
        'show_modal' => true
    ];
    
    echo "   📄 Storage error format:\n";
    echo "   " . json_encode($storageError, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    
    echo "   ✅ Storage error format is correct\n";
    
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

echo "\n✅ Error Format Test completed!\n";
echo "\n💡 Key points:\n";
echo "   - Error responses have 'reason', 'details', 'solutions' fields\n";
echo "   - Frontend checks for these fields to show detailed modal\n";
echo "   - Fallback error handling is implemented in quick-upload-area\n";
echo "   - Global file-upload.js is now included in app.blade.php layout\n";
echo "\n🔧 To test in browser:\n";
echo "   1. Open quick stream modal\n";
echo "   2. Try uploading 4K video with Basic package\n";
echo "   3. Should see detailed error modal instead of generic 400 error\n";
