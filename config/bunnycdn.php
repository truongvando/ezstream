<?php

return [
    /*
    |--------------------------------------------------------------------------
    | BunnyCDN Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for BunnyCDN storage and CDN services
    |
    */

    'storage_zone' => env('BUNNYCDN_STORAGE_ZONE', 'ezstream'),
    'storage_password' => env('BUNNYCDN_STORAGE_PASSWORD'),
    'cdn_url' => env('BUNNYCDN_CDN_URL', 'https://ezstream.b-cdn.net'),
    'storage_url' => env('BUNNYCDN_STORAGE_URL', 'https://storage.bunnycdn.com'),
    
    /*
    |--------------------------------------------------------------------------
    | API Configuration
    |--------------------------------------------------------------------------
    */
    
    'api_key' => env('BUNNYCDN_API_KEY'),
    'api_url' => env('BUNNYCDN_API_URL', 'https://api.bunny.net'),

    /*
    |--------------------------------------------------------------------------
    | Stream Library Configuration
    |--------------------------------------------------------------------------
    */

    'stream_api_key' => env('BUNNYCDN_STREAM_API_KEY'),
    'stream_api_url' => env('BUNNYCDN_STREAM_API_URL', 'https://video.bunnycdn.com'),
    'video_library_id' => env('BUNNYCDN_VIDEO_LIBRARY_ID'),
    'stream_cdn_hostname' => env('BUNNYCDN_STREAM_CDN_HOSTNAME'),
    
    /*
    |--------------------------------------------------------------------------
    | Upload Configuration
    |--------------------------------------------------------------------------
    */
    
    'max_file_size' => env('BUNNYCDN_MAX_FILE_SIZE', 20 * 1024 * 1024 * 1024), // 20GB
    'allowed_extensions' => ['mp4', 'avi', 'mov', 'wmv', 'flv', 'webm', 'mkv'],
    'upload_timeout' => env('BUNNYCDN_UPLOAD_TIMEOUT', 3600), // 1 hour

    // Webhook configuration
    'webhook_secret' => env('BUNNYCDN_WEBHOOK_SECRET'),
    'webhook_url' => env('APP_URL') . '/api/bunny/webhook/stream',
];
