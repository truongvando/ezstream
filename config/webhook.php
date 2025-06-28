<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for webhook URLs used by VPS agents
    |
    */

    // Base webhook URL - can be overridden by environment
    'base_url' => env('WEBHOOK_BASE_URL', env('APP_URL')),
    
    // Webhook endpoint path
    'endpoint' => '/api/stream-webhook',
    
    // Full webhook URL
    'url' => env('WEBHOOK_BASE_URL', env('APP_URL')) . '/api/stream-webhook',
    
    // Development settings
    'dev_mode' => env('APP_ENV') === 'local',
    
    // Ngrok detection
    'ngrok_url' => env('NGROK_URL', null),
    
    // Timeout settings
    'timeout' => 30,
    'retry_attempts' => 3,
]; 