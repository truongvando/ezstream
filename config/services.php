<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'agent' => [
        'secret_token' => env('AGENT_SECRET_TOKEN'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        'api_key' => env('GOOGLE_API_KEY'),
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    ],

    'cloudflare' => [
        'worker_url' => env('CLOUDFLARE_WORKER_URL'),
        'api_token' => env('CLOUDFLARE_API_TOKEN'),
        'zone_id' => env('CLOUDFLARE_ZONE_ID'),
    ],

    'aws' => [
        'cloudfront_domain' => env('AWS_CLOUDFRONT_DOMAIN'),
        'access_key_id' => env('AWS_ACCESS_KEY_ID'),
        'secret_access_key' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'nginx_proxy' => [
        'domain' => env('NGINX_PROXY_DOMAIN'),
        'cache_size' => env('NGINX_CACHE_SIZE', '1g'),
    ],

    'hls_proxy' => [
        'base_url' => env('HLS_PROXY_BASE_URL'),
        'segment_duration' => env('HLS_SEGMENT_DURATION', 10),
    ],

    'cdn' => [
        'base_url' => env('CDN_BASE_URL'),
        'cache_ttl' => env('CDN_CACHE_TTL', 3600),
    ],



    'onedrive' => [
        'client_id' => env('ONEDRIVE_CLIENT_ID'),
        'client_secret' => env('ONEDRIVE_CLIENT_SECRET'),
        'refresh_token' => env('ONEDRIVE_REFRESH_TOKEN'),
        'tenant_id' => env('ONEDRIVE_TENANT_ID', 'common'),
        'redirect_uri' => env('ONEDRIVE_REDIRECT_URI', 'http://localhost'),
        'folder_id' => env('ONEDRIVE_FOLDER_ID', 'root'),
    ],

    's3_storage' => [
        'access_key' => env('S3_STORAGE_ACCESS_KEY'),
        'secret_key' => env('S3_STORAGE_SECRET_KEY'),
        'region' => env('S3_STORAGE_REGION', 'us-east-1'),
        'bucket' => env('S3_STORAGE_BUCKET'),
        'endpoint' => env('S3_STORAGE_ENDPOINT'),
        'url' => env('S3_STORAGE_URL'),
    ],

    'bunny' => [
        'storage_zone' => env('BUNNY_STORAGE_ZONE', 'ezstream'),
        'access_key' => env('BUNNY_ACCESS_KEY'),
        'read_only_password' => env('BUNNY_READ_ONLY_PASSWORD'),
        'cdn_url' => env('BUNNY_CDN_URL', 'https://ezstream.b-cdn.net'),
    ],

];
