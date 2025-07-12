<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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

Route::middleware(['web', 'auth'])->get('/user', function (Request $request) {
    return $request->user();
});

// File Upload Routes - Use session auth, no CSRF in API routes
Route::middleware(['web', 'auth'])->group(function () {
    Route::post('/generate-upload-url', [\App\Http\Controllers\BunnyUploadController::class, 'generateUploadUrl']);
    Route::post('/confirm-upload', [\App\Http\Controllers\BunnyUploadController::class, 'confirmUpload']);
});

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

// Multistream VPS Communication API Routes (no auth required - VPS to Laravel)
Route::prefix('vps')->group(function () {
    Route::post('{vpsId}/status', [App\Http\Controllers\Api\VpsController::class, 'updateStatus']);
    Route::post('{vpsId}/provision-complete', [App\Http\Controllers\Api\VpsController::class, 'provisionComplete']);
    Route::get('{vpsId}/pending-streams', [App\Http\Controllers\Api\VpsController::class, 'getPendingStreams']);
});

// Stream webhook endpoint for multistream
Route::post('stream-webhook', [App\Http\Controllers\Api\VpsController::class, 'streamWebhook']);