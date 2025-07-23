<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\StreamController;
use App\Http\Controllers\Api\SecureDownloadController;
use App\Http\Controllers\Api\DirectUploadController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Controllers\FileUploadController;
use App\Http\Controllers\Api\AgentReportController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/stream/{streamId}/progress', [StreamController::class, 'getStreamProgress']);

Route::middleware(['signed', 'throttle:60,1'])->group(function () {
    Route::get('/secure-download/{userFile}', [SecureDownloadController::class, 'download'])->name('secure-download');
});

Route::post('/direct-upload/initiate', [DirectUploadController::class, 'initiate']);
Route::post('/direct-upload/complete', [DirectUploadController::class, 'complete']);

Route::post('/vps/webhook/{token}', [WebhookController::class, 'handleVpsWebhook']);

// Stateful API routes for uploads from the web app
Route::middleware(['web', 'auth'])->group(function () {
    Route::post('/generate-upload-url', [FileUploadController::class, 'generateUploadUrl']);
    Route::post('/confirm-upload', [FileUploadController::class, 'confirmUpload']);
});

// 🔥 NEW UNIFIED WEBHOOK ENDPOINTS
Route::prefix('webhook')->middleware('agent.token')->group(function () {
    // Single webhooks
    Route::post('/vps', [\App\Http\Controllers\Api\WebhookController::class, 'handleVpsStats'])
        ->middleware('throttle:120,1');
    Route::post('/stream', [\App\Http\Controllers\Api\WebhookController::class, 'handleStreamStatus'])
        ->middleware('throttle:300,1');

    // 🚀 BATCH ENDPOINTS FOR HIGH SCALE (hàng nghìn streams)
    Route::prefix('batch')->middleware('throttle:1800,1')->group(function () {
        Route::post('/vps-stats', [\App\Http\Controllers\Api\WebhookController::class, 'batchVpsStats']);
        Route::post('/stream-events', [\App\Http\Controllers\Api\WebhookController::class, 'batchStreamEvents']);
    });

    // Health check
    Route::post('/health', [\App\Http\Controllers\Api\WebhookController::class, 'handleHealthCheck'])
        ->middleware('throttle:60,1');

    // System statistics (admin only)
    Route::get('/system-stats', [\App\Http\Controllers\Api\WebhookController::class, 'systemStats'])
        ->middleware(['auth', 'throttle:60,1']);
});

// 🔥 NEW STREAM API ENDPOINTS
Route::prefix('stream')->middleware('auth')->group(function () {
    Route::post('/{stream}/start', [\App\Http\Controllers\Api\StreamController::class, 'start']);
    Route::post('/{stream}/stop', [\App\Http\Controllers\Api\StreamController::class, 'stop']);
    Route::get('/{stream}/status', [\App\Http\Controllers\Api\StreamController::class, 'status']);
    Route::get('/allocation-stats', [\App\Http\Controllers\Api\StreamController::class, 'allocationStats']);
});

// 🔥 NEW VPS API ENDPOINTS
Route::prefix('vps')->middleware('auth')->group(function () {
    Route::get('/{vps}/health', [\App\Http\Controllers\Api\VpsController::class, 'health']);
    Route::get('/{vps}/stats', [\App\Http\Controllers\Api\VpsController::class, 'stats']);
    Route::post('/{vps}/test-connection', [\App\Http\Controllers\Api\VpsController::class, 'testConnection']);
    Route::get('/health-overview', [\App\Http\Controllers\Api\VpsController::class, 'healthOverview']);
    Route::get('/aggregated-stats', [\App\Http\Controllers\Api\VpsController::class, 'aggregatedStats']);
    Route::post('/{vps}/execute', [\App\Http\Controllers\Api\VpsController::class, 'executeCommand']);
    Route::get('/{vps}/system-info', [\App\Http\Controllers\Api\VpsController::class, 'systemInfo']);
});

// 🔧 UTILITY ENDPOINTS
Route::prefix('vps')->group(function () {
    // Secure file download for VPS (still needed)
    Route::get('/secure-download/{token}', [\App\Http\Controllers\Api\SecureDownloadController::class, 'download'])
        ->middleware('throttle:30,1');
});

// 🧪 PUBLIC TESTING ENDPOINTS (no auth required)
Route::prefix('public')->middleware('throttle:60,1')->group(function () {
    Route::get('/vps/health-overview', [\App\Http\Controllers\Api\VpsController::class, 'healthOverview']);
    Route::get('/vps/aggregated-stats', [\App\Http\Controllers\Api\VpsController::class, 'aggregatedStats']);
    Route::get('/stream/allocation-stats', [\App\Http\Controllers\Api\StreamController::class, 'allocationStats']);
});

// ✅ ALL LEGACY ROUTES REMOVED - USING NEW MODULAR ENDPOINTS ONLY
// New endpoints are defined above in their respective groups:
// - /api/webhook/* (WebhookController)
// - /api/stream/* (StreamController)
// - /api/vps/* (VpsController)

Route::prefix('v1')->group(function () {
    Route::post('/agent/report', [AgentReportController::class, 'handleReport'])
        ->middleware('agent.token')
        ->name('agent.report');
});