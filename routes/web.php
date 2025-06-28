<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\Admin\Dashboard as AdminDashboard;
use App\Livewire\Admin\SettingsManager as AdminSettingsManager;
use App\Livewire\Admin\TransactionManagement as AdminTransactionManagement;
use App\Livewire\Admin\UserManagement as AdminUserManagement;
use App\Livewire\Admin\AdminStreamManager;
use App\Livewire\Admin\VpsMonitoring;
use App\Livewire\ServicePackageManager;
use App\Models\VpsServer;
use App\Http\Controllers\VpsProvisionController;
use App\Jobs\ProvisionVpsJob;
use App\Http\Controllers\TestGoogleDriveController;
use App\Http\Controllers\FileUploadController;
use App\Livewire\FileManager;

Route::get('/', function () {
    return view('welcome');
})->name('welcome');

Route::middleware(['auth', 'verified'])->group(function () {
    // User Dashboard
    Route::get('/dashboard', \App\Livewire\Dashboard::class)->name('dashboard');
    
    // File Upload routes
    Route::get('/file-manager', FileManager::class)->name('file.manager');
    Route::post('/file/upload', [FileUploadController::class, 'uploadVideo'])->name('file.upload');
    
    // Profile routes
    Route::get('/profile', [\App\Http\Controllers\ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [\App\Http\Controllers\ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [\App\Http\Controllers\ProfileController::class, 'destroy'])->name('profile.destroy');
    
    // Test Google Drive (for admin only)
    Route::middleware('role:admin')->group(function () {
        Route::get('/test-google-drive', [TestGoogleDriveController::class, 'index'])->name('test.google-drive');
        Route::post('/test-google-drive/test-connection', [\App\Http\Controllers\TestGoogleDriveController::class, 'testConnection']);
        Route::post('/test-google-drive/upload-test', [\App\Http\Controllers\TestGoogleDriveController::class, 'uploadTest']);
        Route::post('/test-google-drive/upload-file', [\App\Http\Controllers\TestGoogleDriveController::class, 'uploadFile']);
        Route::get('/test-google-drive/list-files', [\App\Http\Controllers\TestGoogleDriveController::class, 'listFiles']);
        Route::post('/test-google-drive/download-file', [\App\Http\Controllers\TestGoogleDriveController::class, 'downloadFile']);
        Route::post('/test-google-drive/delete-file', [\App\Http\Controllers\TestGoogleDriveController::class, 'deleteFile']);
        Route::get('/test-google-drive/file-info', [\App\Http\Controllers\TestGoogleDriveController::class, 'getFileInfo']);
        Route::post('/test-google-drive/test-direct-streaming', [\App\Http\Controllers\TestGoogleDriveController::class, 'testDirectStreaming']);
        Route::post('/test-google-drive/test-optimized-streaming', [\App\Http\Controllers\TestGoogleDriveController::class, 'testOptimizedStreaming']);
        Route::post('/test-google-drive/test-local-ffmpeg', [\App\Http\Controllers\TestGoogleDriveController::class, 'testLocalFFmpeg']);
    });
    
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', AdminDashboard::class)->name('dashboard');
        Route::get('/streams', AdminStreamManager::class)->name('streams');
        Route::get('/users', AdminUserManagement::class)->name('users');
        Route::get('/vps-servers', \App\Livewire\VpsServerManager::class)->name('vps-servers');
        Route::get('/vps-monitoring', VpsMonitoring::class)->name('vps-monitoring');
        Route::get('/files', \App\Livewire\FileManager::class)->name('files');
        Route::get('/service-packages', ServicePackageManager::class)->name('service-packages');
        Route::get('/transactions', AdminTransactionManagement::class)->name('transactions');
        Route::get('/settings', AdminSettingsManager::class)->name('settings');
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
        
        // Dispatch the job
        ProvisionVpsJob::dispatch($vps)->onConnection('database'); // Ensure it uses the database queue

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
            $job = new \App\Jobs\ProvisionVpsJob($vps);
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
            
            return view('test-streaming', [
                'vps' => $vps,
                'nginxStatus' => $nginxStatus,
                'videosDir' => $videosDir,
                'ffmpegProcesses' => $ffmpegProcesses,
                'testVideoExists' => trim($testVideoExists),
                'streamCommand' => $streamCommand,
                'streamUrl' => "rtmp://{$vps->ip_address}/live/test"
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

    // Create test file record for streaming
    Route::get('/create-test-file', function () {
        // Create a test UserFile record pointing to Google Drive
        $testFile = \App\Models\UserFile::firstOrCreate([
            'user_id' => auth()->id(),
            'original_name' => 'test_video.mp4'
        ], [
            'disk' => 'google_drive',
            'path' => 'google_drive',
            'mime_type' => 'video/mp4',
            'size' => 50 * 1024 * 1024, // 50MB
            'status' => 'AVAILABLE',
            'download_source' => 'google_drive',
            'google_drive_file_id' => '1234567890abcdef', // Replace with real Google Drive file ID
            'source_url' => 'https://drive.google.com/file/d/1234567890abcdef/view'
        ]);
        
        return response()->json([
            'success' => true,
            'file_id' => $testFile->id,
            'message' => 'Test file created. Update google_drive_file_id with real file ID from Google Drive.'
        ]);
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
            'video_source_path' => 'google_drive',
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

});

// API Routes for VPS Communication (accessible via /api prefix)
Route::prefix('api')->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])->group(function () {
    // VPS Communication APIs
    Route::prefix('vps')->group(function () {
        // Secure file download for VPS
        Route::get('/secure-download/{token}', [\App\Http\Controllers\Api\SecureDownloadController::class, 'download'])
            ->middleware('throttle:30,1');
        
        // Stream webhook for VPS status updates
        Route::post('/stream-webhook', [\App\Http\Controllers\Api\StreamWebhookController::class, 'handle'])
            ->middleware('throttle:60,1');
        
        // VPS Stats Webhook Routes
        Route::post('/vps-stats', [\App\Http\Controllers\Api\VpsStatsWebhookController::class, 'receiveStats'])
            ->middleware('throttle:120,1');
        
        Route::get('/{vps}/auth-token', [\App\Http\Controllers\Api\VpsStatsWebhookController::class, 'getAuthToken'])
            ->middleware('auth');
    });
});

require __DIR__.'/auth.php';
