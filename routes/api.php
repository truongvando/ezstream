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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
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