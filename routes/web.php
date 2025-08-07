<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use App\Livewire\Admin\Dashboard as AdminDashboard;
use App\Livewire\Admin\SettingsManager as AdminSettingsManager;
use App\Livewire\Admin\TransactionManagement as AdminTransactionManagement;
use App\Livewire\Admin\UserManagement as AdminUserManagement;
use App\Livewire\Admin\AdminStreamManager;
use App\Livewire\Admin\VpsMonitoring;

use App\Livewire\ServicePackageManager;
use App\Models\VpsServer;
use App\Http\Controllers\VpsProvisionController;
use App\Jobs\ProvisionMultistreamVpsJob;
use App\Services\YoutubeAIAnalysisService;



use App\Http\Controllers\DashboardController;
use App\Livewire\ServiceManager;
use App\Livewire\VpsServerManager;
use App\Livewire\Dashboard;
use App\Models\StreamConfiguration;
use App\Services\StreamProgressService;

use App\Livewire\PaymentManager;
use App\Livewire\TransactionHistory;
use App\Livewire\UserStreamManager;


// Admin Components
use App\Livewire\Admin\Blog\PostList;
use App\Livewire\Admin\Blog\PostForm;

// Public Blog Controller
use App\Http\Controllers\BlogController;

// File Upload API Routes moved to api.php


// Test queue route
Route::get('/test-queue', function () {
    $output = [];

    // 1. Test Redis connection
    try {
        $redis = app('redis')->connection();
        $redis->ping();
        $output[] = "✅ Redis connection: OK";
    } catch (\Exception $e) {
        $output[] = "❌ Redis connection: " . $e->getMessage();
    }

    // 2. Check queue size
    try {
        $queueSize = \Illuminate\Support\Facades\Redis::llen('queues:default');
        $output[] = "Queue size: {$queueSize} jobs pending";
    } catch (\Exception $e) {
        $output[] = "❌ Queue check error: " . $e->getMessage();
    }

    // 3. Test dispatch job
    try {
        $testStream = \App\Models\StreamConfiguration::where('enable_schedule', true)->first();
        if ($testStream) {
            \App\Jobs\StartMultistreamJob::dispatch($testStream);
            $output[] = "✅ Test job dispatched for stream #{$testStream->id}";
        } else {
            $output[] = "❌ No scheduled stream found to test";
        }
    } catch (\Exception $e) {
        $output[] = "❌ Job dispatch error: " . $e->getMessage();
    }

    return '<pre>' . implode("\n", $output) . '</pre>';
});

// Compare create vs edit fields
Route::get('/compare-stream-fields', function () {
    $output = [];

    // Create method fields
    $createFields = [
        'title', 'description', 'video_source_path', 'rtmp_url', 'rtmp_backup_url',
        'stream_key', 'status', 'loop', 'scheduled_at', 'scheduled_end',
        'enable_schedule', 'playlist_order', 'keep_files_on_agent',
        'user_file_id', 'last_started_at'
    ];

    // Edit method fields (before fix)
    $editFieldsBefore = [
        'title', 'description', 'video_source_path', 'rtmp_url', 'rtmp_backup_url',
        'stream_key', 'loop', 'enable_schedule', 'scheduled_at', 'scheduled_end',
        'playlist_order', 'keep_files_on_agent', 'user_file_id'
        // Missing: status, last_started_at
    ];

    // Edit method fields (after fix)
    $editFieldsAfter = [
        'title', 'description', 'video_source_path', 'rtmp_url', 'rtmp_backup_url',
        'stream_key', 'loop', 'enable_schedule', 'scheduled_at', 'scheduled_end',
        'playlist_order', 'keep_files_on_agent', 'user_file_id', 'last_started_at',
        'status' // ✅ Now has smart status handling
    ];

    $output[] = "🔍 Field Comparison:";
    $output[] = "";
    $output[] = "✅ CREATE fields: " . implode(', ', $createFields);
    $output[] = "";
    $output[] = "❌ EDIT fields (before): " . implode(', ', $editFieldsBefore);
    $output[] = "Missing: " . implode(', ', array_diff($createFields, $editFieldsBefore));
    $output[] = "";
    $output[] = "✅ EDIT fields (after fix): " . implode(', ', $editFieldsAfter);
    $missing = array_diff($createFields, $editFieldsAfter);
    $output[] = "Still missing: " . (empty($missing) ? "NONE! ✅" : implode(', ', $missing));

    return '<pre>' . implode("\n", $output) . '</pre>';
});

// Test FORCE_KILL_STREAM command
Route::get('/test-force-kill/{streamId}', function ($streamId) {
    $output = [];

    $stream = \App\Models\StreamConfiguration::find($streamId);
    if (!$stream) {
        return '<pre>Stream not found</pre>';
    }

    $output[] = "🎯 Testing FORCE_KILL_STREAM for Stream #{$streamId}";
    $output[] = "Stream: {$stream->title}";
    $output[] = "Status: {$stream->status}";
    $output[] = "VPS: {$stream->vps_server_id}";
    $output[] = "";

    if (!$stream->vps_server_id) {
        $output[] = "❌ No VPS assigned to stream";
        return '<pre>' . implode("\n", $output) . '</pre>';
    }

    try {
        $redis = app('redis')->connection();
        $killCommand = [
            'command' => 'FORCE_KILL_STREAM',
            'stream_id' => (int)$streamId,
            'reason' => 'Manual test kill',
            'timestamp' => time()
        ];

        $channel = "vps-commands:{$stream->vps_server_id}";
        $result = $redis->publish($channel, json_encode($killCommand));

        $output[] = "📤 Sent FORCE_KILL_STREAM command:";
        $output[] = "Channel: {$channel}";
        $output[] = "Command: " . json_encode($killCommand, JSON_PRETTY_PRINT);
        $output[] = "Subscribers: {$result}";

        if ($result > 0) {
            $output[] = "✅ Command sent successfully!";
            $output[] = "💡 Check agent logs to see if it received the command";
        } else {
            $output[] = "❌ No subscribers listening";
            $output[] = "💡 Agent may be offline";
        }

    } catch (\Exception $e) {
        $output[] = "❌ Error: " . $e->getMessage();
    }

    return '<pre>' . implode("\n", $output) . '</pre>';
});

// Test agent connection
Route::get('/test-agent-command', function () {
    $output = [];

    // Find VPS with active streams
    $vps = \App\Models\VpsServer::where('status', 'ACTIVE')->first();
    if (!$vps) {
        return '<pre>No active VPS found</pre>';
    }

    $output[] = "🎯 Testing Agent Command";
    $output[] = "VPS #{$vps->id}: {$vps->name}";
    $output[] = "";

    // Send test command
    try {
        $redis = app('redis')->connection();
        $testCommand = [
            'command' => 'PING',
            'timestamp' => time(),
            'test' => true
        ];

        $channel = "vps-commands:{$vps->id}";
        $result = $redis->publish($channel, json_encode($testCommand));

        $output[] = "📤 Sent PING command to channel: {$channel}";
        $output[] = "📊 Subscribers listening: {$result}";

        if ($result > 0) {
            $output[] = "✅ Agent is listening!";
        } else {
            $output[] = "❌ No agent listening on this channel";
            $output[] = "💡 Agent may be offline or not running";
        }

    } catch (\Exception $e) {
        $output[] = "❌ Error: " . $e->getMessage();
    }

    return '<pre>' . implode("\n", $output) . '</pre>';
});

// Test YouTube API Service
Route::get('/test-youtube-api', function () {
    try {
        $youtube = new \App\Services\YoutubeApiService();
        $testUrl = 'https://www.youtube.com/@trieuphongsoicau';
        $channelId = $youtube->extractChannelId($testUrl);

        return response()->json([
            'success' => true,
            'input' => $testUrl,
            'channel_id' => $channelId,
            'message' => $channelId ? 'Channel found!' : 'Channel not found'
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
            'file' => $e->getFile() . ':' . $e->getLine()
        ]);
    }
});

// Debug scheduled streams
Route::get('/debug-scheduled', function () {
    $now = now();
    $output = [];

    $output[] = "🕐 Current time: " . $now->format('Y-m-d H:i:s');
    $output[] = "";

    // All scheduled streams
    $allScheduled = \App\Models\StreamConfiguration::where('enable_schedule', true)->get();
    $output[] = "📋 All scheduled streams: " . $allScheduled->count();

    foreach ($allScheduled as $stream) {
        $shouldStart = $stream->scheduled_at && $stream->scheduled_at <= $now;
        $validStatus = in_array($stream->status, ['INACTIVE', 'STOPPED', 'ERROR']);

        $output[] = "";
        $output[] = "Stream #{$stream->id}: {$stream->title}";
        $output[] = "  Status: {$stream->status}";
        $output[] = "  Scheduled At: " . ($stream->scheduled_at ?? 'NULL');
        $output[] = "  Should Start (time): " . ($shouldStart ? 'YES' : 'NO');
        $output[] = "  Valid Status: " . ($validStatus ? 'YES' : 'NO');
        $output[] = "  Will Start: " . ($shouldStart && $validStatus ? 'YES' : 'NO');
    }

    return '<pre>' . implode("\n", $output) . '</pre>';
});

// Test new master-slave logic
Route::get('/test-master-slave', function () {
    $output = [];

    // Find a STREAMING stream
    $stream = \App\Models\StreamConfiguration::where('status', 'STREAMING')->first();

    if (!$stream) {
        return '<pre>No STREAMING streams found to test</pre>';
    }

    $output[] = "🎯 Testing Master-Slave Logic";
    $output[] = "Stream #{$stream->id}: {$stream->title}";
    $output[] = "Current Status: {$stream->status}";
    $output[] = "";

    // Test 1: Change to STOPPED (simulate user stop)
    $output[] = "Test 1: User stops stream (Laravel Master decision)";
    $stream->update(['status' => 'STOPPED']);
    $output[] = "✅ DB updated to STOPPED";
    $output[] = "⏳ Next heartbeat should trigger FORCE_KILL_STREAM command";
    $output[] = "";

    // Test 2: Change back to STREAMING
    $output[] = "Test 2: Revert to STREAMING";
    $stream->update(['status' => 'STREAMING']);
    $output[] = "✅ DB reverted to STREAMING";
    $output[] = "⏳ Next heartbeat should confirm stream is correct";

    return '<pre>' . implode("\n", $output) . '</pre>';
});

// Check latest streams
Route::get('/check-streams', function () {
    $streams = \App\Models\StreamConfiguration::orderBy('id', 'desc')->take(10)->get();

    $output = [];
    $output[] = "📋 Latest 10 streams:";
    $output[] = "";

    foreach ($streams as $stream) {
        $output[] = "Stream #{$stream->id}: {$stream->title}";
        $output[] = "  Status: {$stream->status}";
        $output[] = "  VPS ID: " . ($stream->vps_server_id ?? 'NULL');
        $output[] = "  Created: {$stream->created_at}";
        $output[] = "  Updated: {$stream->updated_at}";
        $output[] = "  Enable Schedule: " . ($stream->enable_schedule ? 'YES' : 'NO');
        if ($stream->enable_schedule) {
            $output[] = "  Scheduled At: " . ($stream->scheduled_at ?? 'NULL');
            $output[] = "  Scheduled End: " . ($stream->scheduled_end ?? 'NULL');
        }
        $output[] = "";
    }

    return '<pre>' . implode("\n", $output) . '</pre>';
});

// Debug queue route
Route::get('/debug-queue', function () {
    $output = [];

    // 1. Check Redis connection
    try {
        $redis = app('redis')->connection();
        $redis->ping();
        $output[] = "✅ Redis: Connected";
    } catch (\Exception $e) {
        $output[] = "❌ Redis: " . $e->getMessage();
        return '<pre>' . implode("\n", $output) . '</pre>';
    }

    // 2. Check queue size
    $queueSize = \Illuminate\Support\Facades\Redis::llen('queues:default');
    $output[] = "📋 Queue size: {$queueSize} jobs";

    // 3. Check failed jobs
    $failedJobs = \Illuminate\Support\Facades\Redis::llen('queues:default:failed');
    $output[] = "❌ Failed jobs: {$failedJobs}";

    // 4. Test job dispatch
    $testStream = \App\Models\StreamConfiguration::where('enable_schedule', true)->first();
    if ($testStream) {
        \App\Jobs\StartMultistreamJob::dispatch($testStream);
        $output[] = "✅ Test job dispatched for stream #{$testStream->id}";

        // Check queue size after dispatch
        $newQueueSize = \Illuminate\Support\Facades\Redis::llen('queues:default');
        $output[] = "📋 Queue size after dispatch: {$newQueueSize}";
    } else {
        $output[] = "❌ No scheduled stream found";
    }

    return '<pre>' . implode("\n", $output) . '</pre>';
});

// Test scheduler route
Route::get('/test-scheduler', function () {
    $output = [];

    // 1. Check current time
    $output[] = "Current time: " . now()->format('Y-m-d H:i:s');

    // 2. Check scheduled streams
    $scheduledStreams = \App\Models\StreamConfiguration::where('enable_schedule', true)->get();
    $output[] = "Found {$scheduledStreams->count()} scheduled streams:";

    foreach ($scheduledStreams as $stream) {
        $shouldStart = $stream->scheduled_at && $stream->scheduled_at <= now() && $stream->status === 'INACTIVE';
        $shouldStop = $stream->scheduled_end && $stream->scheduled_end <= now() && in_array($stream->status, ['STREAMING', 'STARTING']);

        $output[] = "Stream #{$stream->id}: {$stream->title}";
        $output[] = "  Status: {$stream->status}";
        $output[] = "  Scheduled At: " . ($stream->scheduled_at ?? 'NULL');
        $output[] = "  Scheduled End: " . ($stream->scheduled_end ?? 'NULL');
        $output[] = "  Should Start: " . ($shouldStart ? 'YES' : 'NO');
        $output[] = "  Should Stop: " . ($shouldStop ? 'YES' : 'NO');
        $output[] = "";
    }

    // 3. Run scheduler
    $output[] = "Running scheduler...";
    try {
        Artisan::call('streams:check-scheduled');
        $output[] = "Scheduler output:";
        $output[] = Artisan::output();
    } catch (\Exception $e) {
        $output[] = "Scheduler error: " . $e->getMessage();
    }

    return '<pre>' . implode("\n", $output) . '</pre>';
});

Route::get('/', function () {
    try {
        // Lấy số liệu thật để tính toán số ảo
        $realVps = \App\Models\VpsServer::where('status', 'ACTIVE')->count();
        $realStreams = \App\Models\StreamConfiguration::where('status', 'STREAMING')->count();
        $realUsers = \App\Models\User::count();

        // Tạo số liệu ảo (thật + số ảo)
        $stats = [
            'total_vps' => $realVps + 50,        // +50 servers ảo
            'active_streams' => $realStreams + 100,  // +100 streams ảo
            'total_users' => $realUsers + 1000,      // +1000 users ảo
            'service_packages' => \App\Models\ServicePackage::orderBy('price')->take(6)->get(), // Giới hạn 6 gói
            'uptime_percentage' => 99.9,
        ];
    } catch (\Exception $e) {
        // Fallback data với số liệu ảo
        $stats = [
            'total_vps' => 55,      // 50+ servers
            'active_streams' => 120, // 100+ streams
            'total_users' => 1150,   // 1000+ users
            'service_packages' => collect([
                (object)[
                    'id' => 1,
                    'name' => 'Basic',
                    'description' => 'Gói cơ bản cho người mới bắt đầu',
                    'price' => 299000,
                    'features' => json_encode(['2 Streams đồng thời', '720p HD', '5GB Storage', 'Hỗ trợ 24/7']),
                    'is_popular' => false
                ],
                (object)[
                    'id' => 2,
                    'name' => 'Pro',
                    'description' => 'Gói chuyên nghiệp cho creator',
                    'price' => 599000,
                    'features' => json_encode(['5 Streams đồng thời', '1080p Full HD', '20GB Storage', 'Auto Recovery', 'Priority Support']),
                    'is_popular' => true
                ],
                (object)[
                    'id' => 3,
                    'name' => 'Enterprise',
                    'description' => 'Gói doanh nghiệp với tính năng đầy đủ',
                    'price' => 999000,
                    'features' => json_encode(['20 Streams đồng thời', '4K Ultra HD', '100GB Storage', 'Custom Setup', 'Dedicated Support']),
                    'is_popular' => false
                ]
            ]),
            'uptime_percentage' => 99.9,
        ];
    }

    return view('welcome', compact('stats'));
})->name('welcome');

// Public Blog Routes
Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('/blog/{post:slug}', [BlogController::class, 'show'])->name('blog.show');

// File serving route for VPS downloads (cost optimization)
Route::get('/storage/files/{path}', function ($path) {
    $filePath = storage_path('app/files/' . $path);

    if (!file_exists($filePath)) {
        abort(404, 'File not found');
    }

    return response()->file($filePath);
})->where('path', '.*')->name('files.serve');


// Simple test route
Route::get('/test', function () {
    return response()->json([
        'status' => 'success',
        'message' => 'Laravel is working!',
        'app_env' => config('app.env'),
        'app_debug' => config('app.debug'),
        'database_connection' => config('database.default'),
        'timestamp' => now()->toDateTimeString(),
        'livewire_installed' => class_exists('Livewire\Component') ? 'YES' : 'NO',
        'vendor_path_exists' => file_exists(base_path('vendor/livewire/livewire')) ? 'YES' : 'NO'
    ]);
})->name('test');





// Test route without Livewire
Route::get('/simple', function () {
    return '<h1>Laravel hoạt động OK!</h1><p>Thời gian: ' . now() . '</p><p>Environment: ' . config('app.env') . '</p>';
})->name('simple');

// Test route
Route::get('/test-layout', function () {
    return view('layouts.sidebar', ['slot' => '<h1>Test Layout</h1>']);
})->name('test.layout');

// Debug CSRF route
Route::get('/debug-csrf', function () {
    return response()->json([
        'csrf_token' => csrf_token(),
        'session_id' => session()->getId(),
        'session_driver' => config('session.driver'),
        'cache_driver' => config('cache.default'),
        'redis_connection' => app('redis')->ping() ? 'OK' : 'FAILED',
    ]);
});

// Test CSRF form
Route::get('/test-csrf', function () {
    return view('test-csrf');
});

// Test CSRF POST
Route::post('/test-csrf', function (Illuminate\Http\Request $request) {
    return response()->json([
        'success' => true,
        'message' => 'CSRF token valid!',
        'token_from_request' => $request->input('_token'),
        'session_token' => session()->token(),
        'tokens_match' => $request->input('_token') === session()->token(),
    ]);
});

// Debug route
Route::get('/debug-auth', function () {
    $user = auth()->user();
    return response()->json([
        'authenticated' => auth()->check(),
        'user_id' => auth()->id(),
        'email_verified' => $user ? $user->hasVerifiedEmail() : false,
        'user_email' => $user ? $user->email : null,
        'user_role' => $user ? ($user->isAdmin() ? 'admin' : 'user') : null,
    ]);
})->name('debug.auth');

// Test dashboard without Livewire
Route::get('/test-dashboard', function () {
    return view('layouts.sidebar', [
        'slot' => '<div class="p-6"><h1 class="text-2xl font-bold">Test Dashboard</h1><p>This is a test page without Livewire.</p></div>'
    ]);
})->middleware('auth')->name('test.dashboard');

// Auto login for testing
Route::get('/auto-login', function () {
    $user = \App\Models\User::first();
    if ($user) {
        auth()->login($user);
        return redirect('/dashboard')->with('message', 'Auto logged in as: ' . $user->email);
    }
    return redirect('/register')->with('error', 'No user found. Please register first.');
})->name('auto.login');

// Debug dashboard component
Route::get('/debug-dashboard', function () {
    if (!auth()->check()) {
        return response()->json(['error' => 'Not authenticated']);
    }
    
    $user = auth()->user();
    
    try {
        // Test các query trong Dashboard component
        $streamCount = \App\Models\StreamConfiguration::where('user_id', $user->id)->count();
        $totalStorageUsed = \App\Models\UserFile::where('user_id', $user->id)->sum('size');
        $activeSubscription = $user->subscriptions()->where('status', 'ACTIVE')->with('servicePackage')->first();
        
        return response()->json([
            'user_id' => $user->id,
            'user_email' => $user->email,
            'stream_count' => $streamCount,
            'total_storage_used' => $totalStorageUsed,
            'active_subscription' => $activeSubscription ? $activeSubscription->toArray() : null,
            'status' => 'Dashboard queries work fine'
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
    }
})->middleware('auth')->name('debug.dashboard');

// Test render dashboard với error handling
Route::get('/test-render-dashboard', function () {
    if (!auth()->check()) {
        return 'Not authenticated. <a href="/auto-login">Auto Login</a>';
    }
    
    try {
        // Tạo instance Dashboard component và render
        $dashboard = new \App\Livewire\Dashboard();
        $dashboard->mount();
        
        return 'Dashboard component mounted successfully. <a href="/dashboard">Go to Dashboard</a>';
    } catch (\Exception $e) {
        return 'Error mounting Dashboard: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
    }
})->middleware('auth')->name('test.render.dashboard');

// Language switching
Route::get('/language/{locale}', function ($locale) {
    if (in_array($locale, ['en', 'vi'])) {
        session(['locale' => $locale]);
        app()->setLocale($locale);
    }
    return redirect()->back();
})->middleware('locale')->name('language.switch');

Route::middleware(['auth', 'locale'])->group(function () {
    // User Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Debug blog images
    // Test routes for queue monitoring
    Route::get('/test/queue-monitor', function () {
        return view('test.queue-monitor');
    })->name('test.queue-monitor');

    Route::get('/test/process-queue', function () {
        $allocation = app(\App\Services\Stream\StreamAllocation::class);
        $allocation->processQueue();
        return redirect()->route('test.queue-monitor')->with('success', 'Queue processed!');
    })->name('test.process-queue');

    Route::get('/debug-blog-images', function () {
        $posts = \App\Models\Post::whereNotNull('featured_image')->get();

        $results = [];
        foreach ($posts as $post) {
            $results[] = [
                'id' => $post->id,
                'title' => $post->title,
                'featured_image' => $post->featured_image,
                'image_accessible' => @get_headers($post->featured_image)[0] ?? 'Cannot check',
            ];
        }

        return response()->json($results);
    })->name('debug.blog.images');

    // Service Manager - Trang gói dịch vụ tổng hợp
    Route::get('/services', ServiceManager::class)->name('services');
    Route::get('/service-manager', ServiceManager::class)->name('service-manager');

    // Alias routes để tương thích với code cũ
    Route::get('/billing', ServiceManager::class)->name('billing.manager');
    Route::get('/packages', ServiceManager::class)->name('package.selection');
    Route::get('/subscriptions', ServiceManager::class)->name('user.subscriptions');
    Route::get('/payments', ServiceManager::class)->name('user.payments');

    // Payment Manager (giữ lại cho thanh toán riêng biệt nếu cần)
    Route::get('/payment/{subscription}', PaymentManager::class)->name('payment.show');

    // User Stream Manager
    Route::get('/streams', \App\Livewire\UserStreamManager::class)->name('user.stream.manager');

    // Alias routes for consistency
    Route::get('/user/streams', \App\Livewire\UserStreamManager::class)->name('user.streams');
    // File Upload routes
    Route::get('/files', [\App\Http\Controllers\FileController::class, 'index'])->name('files.index');
    Route::post('/files/delete', [\App\Http\Controllers\FileController::class, 'delete'])->name('files.delete');
    Route::get('/files/stats', [\App\Http\Controllers\FileController::class, 'stats'])->name('files.stats');

    // User file routes for consistency
    Route::get('/user/files', [\App\Http\Controllers\FileController::class, 'index'])->name('user.files');
    Route::get('/packages-selection', ServiceManager::class)->name('packages');

    // Additional user routes from sidebar
    Route::get('/user/packages', ServiceManager::class)->name('user.packages');
    Route::get('/user/billing', ServiceManager::class)->name('user.billing');

    // File Upload routes - Will be recreated
    
    // Profile routes
    Route::get('/profile', [\App\Http\Controllers\ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [\App\Http\Controllers\ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [\App\Http\Controllers\ProfileController::class, 'destroy'])->name('profile.destroy');

    // New Features Routes
    // View Services Routes
    Route::get('/view-services', \App\Livewire\ViewServiceManager::class)->name('view-services.index');

    // Tool Store Routes
    Route::get('/tools', \App\Livewire\ToolStore::class)->name('tools.index');
    Route::get('/tools/{slug}', \App\Livewire\ToolDetail::class)->name('tools.show');

    // License Manager Routes
    Route::get('/licenses', \App\Livewire\LicenseManager::class)->name('licenses.index');

    // File Manager Route (temporary redirect)
    Route::get('/file-manager', function() {
        return redirect()->route('user.files');
    })->name('file.manager');

    // Payment Routes for new features
    Route::get('/payment/view-order/{order}', [\App\Http\Controllers\PaymentController::class, 'viewOrder'])->name('payment.view-order');
    Route::get('/payment/tool-order/{order}', [\App\Http\Controllers\PaymentController::class, 'toolOrder'])->name('payment.tool-order');

    // Deposit Routes
    Route::get('/deposit', \App\Livewire\DepositManager::class)->name('deposit.index');

    // MMO Services Routes
    Route::get('/mmo-services', \App\Livewire\User\MmoServices::class)->name('mmo-services.index');
    Route::get('/mmo-orders', \App\Livewire\User\MmoOrders::class)->name('mmo-orders.index');


    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', AdminDashboard::class)->name('dashboard');
        Route::get('/streams', AdminStreamManager::class)->name('streams');
        Route::get('/users', AdminUserManagement::class)->name('users');
        Route::get('/vps-servers', VpsServerManager::class)->name('vps-servers');
        Route::get('/vps-monitoring', VpsMonitoring::class)->name('vps-monitoring');
        Route::get('/files', [\App\Http\Controllers\FileController::class, 'index'])->name('files');
        Route::post('/files/delete', [\App\Http\Controllers\FileController::class, 'delete'])->name('files.delete');
        Route::get('/files/stats', [\App\Http\Controllers\FileController::class, 'stats'])->name('files.stats');
        Route::get('/service-packages', ServicePackageManager::class)->name('service-packages');
        Route::get('/transactions', AdminTransactionManagement::class)->name('transactions');
        Route::get('/settings', AdminSettingsManager::class)->name('settings');
        
        // Admin Blog Routes
        Route::get('/blog', PostList::class)->name('blog.index');
        Route::get('/blog/create', PostForm::class)->name('blog.create');
        Route::get('/blog/{postId}/edit', PostForm::class)->name('blog.edit');
        Route::post('/blog', [\App\Http\Controllers\Admin\BlogController::class, 'store'])->name('blog.store');
        Route::put('/blog/{post}', [\App\Http\Controllers\Admin\BlogController::class, 'update'])->name('blog.update');

        // Test route for debugging
        Route::get('/blog/test-create', function() {
            return 'Test route works! PostForm class: ' . (class_exists(\App\Livewire\Admin\Blog\PostForm::class) ? 'EXISTS' : 'NOT FOUND');
        })->name('blog.test');

        // Test PostForm component directly
        Route::get('/blog/test-component', \App\Livewire\Admin\Blog\PostForm::class)->name('blog.test.component');

        // Store Management Routes
        Route::get('/tools', \App\Livewire\Admin\ToolManager::class)->name('tools.index');
        Route::get('/tools/create', [\App\Http\Controllers\Admin\ToolController::class, 'create'])->name('tools.create');
        Route::post('/tools', [\App\Http\Controllers\Admin\ToolController::class, 'store'])->name('tools.store');
        Route::get('/tools/{tool}/edit', [\App\Http\Controllers\Admin\ToolController::class, 'edit'])->name('tools.edit');
        Route::put('/tools/{tool}', [\App\Http\Controllers\Admin\ToolController::class, 'update'])->name('tools.update');
        Route::delete('/tools/{tool}', [\App\Http\Controllers\Admin\ToolController::class, 'destroy'])->name('tools.destroy');

        Route::get('/view-services', \App\Livewire\Admin\ViewServiceManager::class)->name('view-services.index');
        Route::get('/pending-orders', \App\Livewire\Admin\PendingOrdersManager::class)->name('admin.pending-orders');
        Route::get('/balance-manager', \App\Livewire\Admin\BalanceManager::class)->name('admin.balance-manager');
        Route::get('/payment-manager', \App\Livewire\Admin\PaymentManager::class)->name('admin.payment-manager');
        Route::get('/mmo-services', \App\Livewire\Admin\MmoServiceManager::class)->name('admin.mmo-services');
        Route::get('/mmo-orders', \App\Livewire\Admin\MmoOrderManager::class)->name('admin.mmo-orders');
        Route::get('/licenses', \App\Livewire\Admin\LicenseManager::class)->name('licenses.index');
        Route::get('/orders', \App\Livewire\Admin\OrderManager::class)->name('orders.index');
    });

    // Public blog routes
    Route::prefix('blog')->name('blog.')->group(function () {
        Route::get('/', [BlogController::class, 'index'])->name('index'); // blog.index
        Route::get('/{post:slug}', [BlogController::class, 'show'])->name('show'); // blog.show
    });

    // Safe VPS Creation Routes
    // Step 1: Show the creation form
    Route::get('/vps/create', function () {
        // This can be a simple Blade view
        return view('admin.vps.create');
    })->name('vps.create');

    // Step 2: Store the new VPS and redirect to the status page
    Route::post('/vps/store', function () {
        $validatedData = request()->validate([
            'name' => 'required|string|max:255',
            'ip_address' => 'required|ip|unique:vps_servers,ip_address',
            'ssh_user' => 'required|string|max:255',
            'ssh_password' => 'required|string',
            'ssh_port' => 'required|integer|min:1|max:65535',
        ]);
        
        $vps = VpsServer::create($validatedData + ['is_active' => true, 'status' => 'PENDING']);
        
        // Dispatch the job to vps-provisioning queue
        ProvisionMultistreamVpsJob::dispatch($vps->id)->onQueue('vps-provisioning');

        session()->flash('message', "VPS '{$vps->name}' đã được thêm. Job cài đặt đã được gửi vào hàng đợi.");

        return redirect()->route('admin.provision.status', ['vps' => $vps->id]);

    })->name('vps.store');
    
    // VPS Provisioning (Self-Reporting Method)
    Route::prefix('vps-provision')->name('vps.provision.')->group(function () {
        Route::get('/script/{token}', [VpsProvisionController::class, 'getScript'])->name('script');
        Route::post('/finish/{token}', [VpsProvisionController::class, 'finish'])->name('finish');
    });

    // New route for manual tracking and execution
    Route::get('/admin/provision-status/{vps}', function(VpsServer $vps) {
        return VpsProvisionController::getProvisionStatusPage($vps);
    })->middleware('auth')->name('admin.provision.status');

    Route::get('/run-provision-job-directly/{vps}', function (VpsServer $vps) {
        echo "<!DOCTYPE html><body style='background:#111; color:#eee; font-family:monospace; padding:15px; white-space:pre-wrap;'>";
        try {
            $job = new \App\Jobs\ProvisionMultistreamVpsJob($vps->id);
            $sshService = new \App\Services\SshService();
            $job->handle($sshService);
            echo "✅ Job handle completed without exceptions.";
        } catch (\Throwable $e) {
            echo "❌ CAUGHT EXCEPTION! ❌\n\n";
            echo "Error: " . $e->getMessage() . "\n";
            echo "File: " . $e->getFile() . " on line " . $e->getLine();
        }
        echo "</body></html>";
    })->middleware('auth')->name('admin.provision.run');

    // Test streaming
    Route::get('/test-streaming/{vps}', function (VpsServer $vps) {
        $ssh = new \App\Services\SshService();
        
        try {
            $ssh->connect($vps);
            
            // 1. Kiểm tra nginx và rtmp module
            $nginxStatus = $ssh->execute('systemctl status nginx | head -10');
            
            // 2. Kiểm tra thư mục videos
            $videosDir = $ssh->execute('ls -la /home/videos/');
            
            // 3. Kiểm tra process ffmpeg đang chạy
            $ffmpegProcesses = $ssh->execute('ps aux | grep ffmpeg | grep -v grep');
            
            // 4. Tạo test video nếu chưa có
            $testVideoExists = $ssh->execute('test -f /home/videos/test.mp4 && echo "EXISTS" || echo "NOT_EXISTS"');
            
            if (trim($testVideoExists) === 'NOT_EXISTS') {
                // Tạo test video 10 giây với ffmpeg
                $ssh->execute('ffmpeg -f lavfi -i testsrc=duration=10:size=1280x720:rate=30 -f lavfi -i sine=frequency=1000:duration=10 -c:v libx264 -c:a aac /home/videos/test.mp4');
            }
            
            // 5. Instructions để start stream
            $streamCommand = "ffmpeg -re -i /home/videos/test.mp4 -c:v libx264 -preset fast -b:v 3000k -maxrate 3000k -bufsize 6000k -pix_fmt yuv420p -g 50 -c:a aac -b:a 128k -f flv rtmp://localhost/live/test";
            
            $ssh->disconnect();
            
            // View đã bị xóa, trả về response JSON thay thế
            return response()->json([
                'message' => 'Test Streaming Info',
                'note' => 'View đã được cleanup',
                'vps' => $vps->only(['id', 'name', 'ip_address']),
                'nginx_status' => $nginxStatus,
                'videos_dir' => $videosDir,
                'ffmpeg_processes' => $ffmpegProcesses,
                'test_video_exists' => trim($testVideoExists),
                'stream_command' => $streamCommand,
                'stream_url' => "rtmp://{$vps->ip_address}/live/test",
                'instructions' => 'Sử dụng POST /start-test-stream/{vps} để start stream'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    })->middleware(['auth', 'verified']);

    // Start test stream
    Route::post('/start-test-stream/{vps}', function (VpsServer $vps) {
        $ssh = new \App\Services\SshService();
        
        try {
            $ssh->connect($vps);
            
            // Kill existing test streams
            $ssh->execute('pkill -f "rtmp://localhost/live/test" || true');
            
            // Start new stream in background
            $streamCommand = 'nohup ffmpeg -re -stream_loop -1 -i /home/videos/test.mp4 -c:v libx264 -preset fast -b:v 3000k -maxrate 3000k -bufsize 6000k -pix_fmt yuv420p -g 50 -c:a aac -b:a 128k -f flv rtmp://localhost/live/test > /tmp/ffmpeg_test.log 2>&1 &';
            
            $ssh->execute($streamCommand);
            
            // Get PID
            $pid = $ssh->execute('pgrep -f "rtmp://localhost/live/test" | head -1');
            
            $ssh->disconnect();
            
            return response()->json([
                'success' => true,
                'pid' => trim($pid),
                'stream_url' => "rtmp://{$vps->ip_address}/live/test"
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    })->middleware(['auth', 'verified']);

    // Stop test stream
    Route::post('/stop-test-stream/{vps}', function (VpsServer $vps) {
        $ssh = new \App\Services\SshService();
        
        try {
            $ssh->connect($vps);
            
            // Kill test streams
            $ssh->execute('pkill -f "rtmp://localhost/live/test" || true');
            
            $ssh->disconnect();
            
            return response()->json([
                'success' => true,
                'message' => 'Test stream stopped'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    })->middleware(['auth', 'verified']);



    // Quick stream setup
    Route::get('/quick-stream-setup/{vps}', function (VpsServer $vps) {
        // Create test file if not exists
        $testFile = \App\Models\UserFile::where('user_id', auth()->id())
            ->where('original_name', 'test_video.mp4')
            ->first();
        
        if (!$testFile) {
            return redirect('/create-test-file');
        }
        
        // Create stream configuration
        $stream = \App\Models\StreamConfiguration::create([
            'user_id' => auth()->id(),
            'title' => 'Test Stream - ' . now()->format('Y-m-d H:i'),
            'description' => 'Testing VPS: ' . $vps->name,
            'vps_server_id' => $vps->id,
            'user_file_id' => $testFile->id,
            'video_source_path' => 'bunny_cdn',
            'rtmp_url' => 'rtmp://a.rtmp.youtube.com/live2',
            'stream_key' => 'YOUR_STREAM_KEY', // Replace with real stream key
            'status' => 'INACTIVE',
            'stream_preset' => 'optimized',
            'loop' => true
        ]);
        
        // Start stream immediately
        \App\Jobs\StartStreamJob::dispatch($stream);
        
        return redirect('/admin/streams')->with('success', 'Stream created and starting on VPS: ' . $vps->name);
    })->middleware(['auth', 'verified']);

    // Webhook Testing Routes (for development)
    Route::middleware(['auth', 'role:admin'])->prefix('webhook-test')->group(function () {
        Route::get('/', [\App\Http\Controllers\WebhookTestController::class, 'testInterface'])->name('webhook.test');
        Route::post('/simulate', [\App\Http\Controllers\WebhookTestController::class, 'simulateWebhook'])->name('webhook.simulate');
        Route::get('/quick/{streamId}/{status}', [\App\Http\Controllers\WebhookTestController::class, 'quickTest'])->name('webhook.quick');
    });

    // Upload route - DEPRECATED: Redirect to direct upload
    Route::post('/upload/stream', function() {
        return response()->json([
            'status' => 'error',
            'message' => 'Phương thức upload cũ đã bị vô hiệu hóa. Vui lòng sử dụng direct upload.'
        ], 400);
    })->name('upload.stream');
    
    Route::get('/user/transactions', TransactionHistory::class)->name('transactions.history');
});

// API Routes for VPS Communication (accessible via /api prefix)
Route::prefix('api')->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])->group(function () {
    // VPS Communication APIs
    Route::prefix('vps')->group(function () {
        // Secure file download for VPS
        Route::get('/secure-download/{token}', [\App\Http\Controllers\Api\SecureDownloadController::class, 'download'])
            ->middleware('throttle:30,1');
        
        // Stream webhook moved to /api/webhook/stream in api.php
        
        // VPS Stats Webhook moved to /api/webhook/vps in api.php
    });
});

// API Routes for Stream Progress
Route::get('/api/stream/{streamId}/progress', [App\Http\Controllers\StreamProgressController::class, 'getProgress']);
Route::middleware('auth')->group(function () {
    Route::delete('/api/stream/{streamId}/progress', [App\Http\Controllers\StreamProgressController::class, 'clearProgress']);
});

// Debug Routes (remove in production)
Route::get('/debug/stream/{streamId}', function($streamId) {
    $stream = StreamConfiguration::find($streamId);

    if (!$stream) {
        return response()->json(['error' => 'Stream not found'], 404);
    }

    return response()->json([
        'stream' => [
            'id' => $stream->id,
            'title' => $stream->title,
            'status' => $stream->status,
            'vps_server_id' => $stream->vps_server_id,
            'process_id' => $stream->process_id,
            'last_status_update' => $stream->last_status_update,
            'last_started_at' => $stream->last_started_at,
            'error_message' => $stream->error_message,
            'user_id' => $stream->user_id
        ]
    ]);
});

Route::get('/debug/fix-stream/{streamId}', function($streamId) {
    $stream = StreamConfiguration::find($streamId);

    if (!$stream) {
        return response()->json(['error' => 'Stream not found'], 404);
    }

    $oldStatus = $stream->status;

    // Force sync to STREAMING
    $result = $stream->update([
        'status' => 'STREAMING',
        'vps_server_id' => 24,
        'last_status_update' => now(),
        'error_message' => null,
        'process_id' => 722503
    ]);

    // Create progress update
    try {
        StreamProgressService::createStageProgress($streamId, 'streaming', '🔧 Manual fix: Stream synced to STREAMING');
        $progressCreated = true;
    } catch (\Exception $e) {
        $progressCreated = false;
        $progressError = $e->getMessage();
    }

    $stream->refresh();

    return response()->json([
        'success' => $result,
        'old_status' => $oldStatus,
        'new_status' => $stream->status,
        'vps_id' => $stream->vps_server_id,
        'last_update' => $stream->last_status_update,
        'progress_created' => $progressCreated,
        'progress_error' => $progressError ?? null
    ]);
});

// YouTube Monitoring Routes
Route::middleware(['auth'])->group(function () {
    Route::get('/youtube-monitoring', [App\Http\Controllers\YoutubeMonitoringController::class, 'index'])->name('youtube.index');
    Route::get('/youtube-monitoring/{channel}', [App\Http\Controllers\YoutubeMonitoringController::class, 'show'])->name('youtube.show');
    Route::post('/youtube-monitoring', [App\Http\Controllers\YoutubeMonitoringController::class, 'store'])->name('youtube.store');
    Route::delete('/youtube-monitoring/{channel}', [App\Http\Controllers\YoutubeMonitoringController::class, 'destroy'])->name('youtube.destroy');
    Route::patch('/youtube-monitoring/{channel}/toggle', [App\Http\Controllers\YoutubeMonitoringController::class, 'toggleActive'])->name('youtube.toggle');

    // YouTube Alerts Routes
    Route::get('/youtube-alerts', [App\Http\Controllers\YoutubeAlertController::class, 'index'])->name('youtube.alerts.index');
    Route::get('/youtube-alerts/page', function() { return view('youtube-monitoring.alerts'); })->name('youtube.alerts.page');
    Route::patch('/youtube-alerts/{alert}/read', [App\Http\Controllers\YoutubeAlertController::class, 'markAsRead'])->name('youtube.alerts.read');
    Route::patch('/youtube-alerts/read-all', [App\Http\Controllers\YoutubeAlertController::class, 'markAllAsRead'])->name('youtube.alerts.read-all');
    Route::delete('/youtube-alerts/{alert}', [App\Http\Controllers\YoutubeAlertController::class, 'destroy'])->name('youtube.alerts.destroy');
    Route::get('/youtube-alerts/unread-count', [App\Http\Controllers\YoutubeAlertController::class, 'getUnreadCount'])->name('youtube.alerts.unread-count');
    Route::get('/youtube-alerts/recent', [App\Http\Controllers\YoutubeAlertController::class, 'getRecent'])->name('youtube.alerts.recent');

    // Alert Settings Routes
    Route::get('/youtube-monitoring/{channel}/alert-settings', [App\Http\Controllers\YoutubeAlertController::class, 'getSettings'])->name('youtube.alerts.settings');
    Route::put('/youtube-monitoring/{channel}/alert-settings', [App\Http\Controllers\YoutubeAlertController::class, 'updateSettings'])->name('youtube.alerts.settings.update');

    // YouTube Comparison Routes
    Route::get('/youtube-comparison', [App\Http\Controllers\YoutubeComparisonController::class, 'index'])->name('youtube.comparison.index');
    Route::post('/youtube-comparison/compare', [App\Http\Controllers\YoutubeComparisonController::class, 'compare'])->name('youtube.comparison.compare');

    // YouTube AI Analysis Routes
    Route::post('/youtube-ai/analyze', [App\Http\Controllers\YoutubeAIController::class, 'analyzeChannel'])->name('youtube.ai.analyze');
    Route::post('/youtube-ai/status', [App\Http\Controllers\YoutubeAIController::class, 'checkAnalysisStatus'])->name('youtube.ai.status');
    Route::post('/youtube-ai/compare', [App\Http\Controllers\YoutubeAIController::class, 'compareChannels'])->name('youtube.ai.compare');

    // YouTube Video Management Routes
    Route::get('/youtube-monitoring/{channel}/videos', [App\Http\Controllers\YoutubeMonitoringController::class, 'getChannelVideos'])->name('youtube.videos.list');
    Route::post('/youtube-monitoring/{channel}/sync-more-videos', [App\Http\Controllers\YoutubeMonitoringController::class, 'syncMoreVideos'])->name('youtube.videos.sync-more');

    // Debug route to see AI prompt data
    Route::get('/debug-ai-prompt/{channelId}', [App\Http\Controllers\YoutubeAIController::class, 'debugPrompt'])->name('debug.ai.prompt');


});

require __DIR__.'/auth.php';
