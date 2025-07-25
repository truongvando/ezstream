<?php

/**
 * Test API error response for upload validation
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

echo "🔍 Testing API Error Response\n";
echo "=============================\n\n";

use Illuminate\Http\Request;
use App\Http\Controllers\FileUploadController;
use Illuminate\Support\Facades\Auth;

// Test 1: Resolution error
echo "1. Testing resolution error...\n";

try {
    // Create a test user
    $user = App\Models\User::first();
    if (!$user) {
        echo "   ❌ No users found in database\n";
        exit(1);
    }
    
    Auth::login($user);
    echo "   ✅ Logged in as user: {$user->name}\n";
    
    // Get user's package
    $package = $user->currentPackage();
    if ($package) {
        echo "   📦 User package: {$package->name}\n";
        echo "   📏 Package limits: {$package->max_video_width}x{$package->max_video_height}\n";
    } else {
        echo "   ⚠️ User has no package\n";
    }
    
    // Create request with high resolution
    $request = new Request();
    $request->merge([
        'filename' => 'test_4k_video.mp4',
        'content_type' => 'video/mp4',
        'size' => 1000000000, // 1GB
        'width' => 3840,  // 4K width
        'height' => 2160  // 4K height
    ]);
    
    $controller = new FileUploadController();
    $response = $controller->generateUploadUrl($request);
    
    echo "   📊 Response status: {$response->getStatusCode()}\n";
    
    $responseData = json_decode($response->getContent(), true);
    echo "   📄 Response data:\n";
    echo "   " . json_encode($responseData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    
    // Check if it's a detailed error
    if (isset($responseData['reason']) && isset($responseData['details']) && isset($responseData['solutions'])) {
        echo "   ✅ Detailed error response format is correct\n";
        echo "   🎯 Error: {$responseData['error']}\n";
        echo "   🎯 Reason: {$responseData['reason']}\n";
        echo "   🎯 Show modal: " . ($responseData['show_modal'] ? 'true' : 'false') . "\n";
    } else {
        echo "   ❌ Not a detailed error response\n";
    }
    
} catch (Exception $e) {
    echo "   ❌ Exception: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 2: Valid request
echo "2. Testing valid request...\n";

try {
    // Create request with valid resolution
    $request = new Request();
    $request->merge([
        'filename' => 'test_hd_video.mp4',
        'content_type' => 'video/mp4',
        'size' => 100000000, // 100MB
        'width' => 1920,  // HD width
        'height' => 1080  // HD height
    ]);
    
    $controller = new FileUploadController();
    $response = $controller->generateUploadUrl($request);
    
    echo "   📊 Response status: {$response->getStatusCode()}\n";
    
    if ($response->getStatusCode() === 200) {
        echo "   ✅ Valid request accepted\n";
    } else {
        $responseData = json_decode($response->getContent(), true);
        echo "   ❌ Valid request rejected:\n";
        echo "   " . json_encode($responseData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    }
    
} catch (Exception $e) {
    echo "   ❌ Exception: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 3: Storage limit error
echo "3. Testing storage limit error...\n";

try {
    // Create request with huge file
    $request = new Request();
    $request->merge([
        'filename' => 'huge_video.mp4',
        'content_type' => 'video/mp4',
        'size' => 50000000000, // 50GB - should exceed most limits
        'width' => 1920,
        'height' => 1080
    ]);
    
    $controller = new FileUploadController();
    $response = $controller->generateUploadUrl($request);
    
    echo "   📊 Response status: {$response->getStatusCode()}\n";
    
    $responseData = json_decode($response->getContent(), true);
    echo "   📄 Response data:\n";
    echo "   " . json_encode($responseData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    
    // Check if it's a detailed error
    if (isset($responseData['reason']) && isset($responseData['details']) && isset($responseData['solutions'])) {
        echo "   ✅ Storage error response format is correct\n";
    } else {
        echo "   ❌ Not a detailed error response\n";
    }
    
} catch (Exception $e) {
    echo "   ❌ Exception: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 4: Invalid file type
echo "4. Testing invalid file type...\n";

try {
    // Create request with invalid content type
    $request = new Request();
    $request->merge([
        'filename' => 'test_video.avi',
        'content_type' => 'video/avi', // Not MP4
        'size' => 100000000,
        'width' => 1920,
        'height' => 1080
    ]);
    
    $controller = new FileUploadController();
    $response = $controller->generateUploadUrl($request);
    
    echo "   📊 Response status: {$response->getStatusCode()}\n";
    
    $responseData = json_decode($response->getContent(), true);
    echo "   📄 Response data:\n";
    echo "   " . json_encode($responseData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    
} catch (Exception $e) {
    echo "   ❌ Exception: " . $e->getMessage() . "\n";
}

echo "\n✅ API Error Response Test completed!\n";
echo "\n💡 Summary:\n";
echo "   - Resolution errors should return detailed error with show_modal: true\n";
echo "   - Storage errors should return detailed error with show_modal: true\n";
echo "   - Invalid file type should return simple error message\n";
echo "   - Valid requests should return 200 with upload URL\n";
echo "\n🔧 Next steps:\n";
echo "   1. Open test-upload-error-handling.html in browser\n";
echo "   2. Test error modal display\n";
echo "   3. Verify fallback error handling works\n";
echo "   4. Test in actual quick stream modal\n";
