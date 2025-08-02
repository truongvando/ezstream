<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Payment Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains all payment-related configuration options
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Transaction Timeout
    |--------------------------------------------------------------------------
    |
    | How long (in minutes) should pending transactions remain valid
    | before being automatically cancelled
    |
    */
    'transaction_timeout' => env('PAYMENT_TRANSACTION_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Exchange Rate
    |--------------------------------------------------------------------------
    |
    | Default USD to VND exchange rate (fallback when API fails)
    |
    */
    'usd_to_vnd_rate' => env('PAYMENT_USD_TO_VND_RATE', 24000),

    /*
    |--------------------------------------------------------------------------
    | Auto Update Exchange Rate
    |--------------------------------------------------------------------------
    |
    | Whether to automatically fetch exchange rates from external APIs
    |
    */
    'auto_update_rate' => env('PAYMENT_AUTO_UPDATE_RATE', true),

    /*
    |--------------------------------------------------------------------------
    | Bank Information
    |--------------------------------------------------------------------------
    |
    | Bank account details for VietQR payments
    |
    */
    'bank' => [
        'id' => env('PAYMENT_BANK_ID', '970436'), // Vietcombank
        'account_number' => env('PAYMENT_BANK_ACCOUNT', '0971000032314'),
        'account_name' => env('PAYMENT_BANK_NAME', 'TRUONG VAN DO'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Methods
    |--------------------------------------------------------------------------
    |
    | Available payment methods and their configurations
    |
    */
    'methods' => [
        'balance' => [
            'enabled' => env('PAYMENT_BALANCE_ENABLED', true),
            'name' => 'Thanh toán bằng số dư',
            'icon' => '💰',
        ],
        'bank_transfer' => [
            'enabled' => env('PAYMENT_BANK_ENABLED', true),
            'name' => 'Chuyển khoản ngân hàng',
            'icon' => '🏦',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Minimum Amounts
    |--------------------------------------------------------------------------
    |
    | Minimum amounts for different operations
    |
    */
    'minimums' => [
        'deposit' => env('PAYMENT_MIN_DEPOSIT', 1), // USD
        'subscription' => env('PAYMENT_MIN_SUBSCRIPTION', 5), // USD
    ],

    /*
    |--------------------------------------------------------------------------
    | Maximum Amounts
    |--------------------------------------------------------------------------
    |
    | Maximum amounts for different operations
    |
    */
    'maximums' => [
        'deposit' => env('PAYMENT_MAX_DEPOSIT', 10000), // USD
        'subscription' => env('PAYMENT_MAX_SUBSCRIPTION', 1000), // USD
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto Cancel Settings
    |--------------------------------------------------------------------------
    |
    | Settings for automatically cancelling expired transactions
    |
    */
    'auto_cancel' => [
        'enabled' => env('PAYMENT_AUTO_CANCEL_ENABLED', true),
        'check_interval' => env('PAYMENT_AUTO_CANCEL_INTERVAL', 10), // minutes
        'notification' => env('PAYMENT_AUTO_CANCEL_NOTIFY', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Update Schedule
    |--------------------------------------------------------------------------
    |
    | How often to update exchange rates
    |
    */
    'rate_update' => [
        'interval' => env('PAYMENT_RATE_UPDATE_INTERVAL', 60), // minutes
        'apis' => [
            'vietcombank' => [
                'enabled' => true,
                'priority' => 1, // Highest priority - most accurate for Vietnam
                'description' => 'Official Vietcombank exchange rate',
            ],
            'exchangerate-api' => [
                'enabled' => true,
                'priority' => 2,
                'description' => 'Free international exchange rate API',
            ],
            'fixer' => [
                'enabled' => env('FIXER_API_KEY') ? true : false,
                'priority' => 3,
                'description' => 'Professional exchange rate service (requires API key)',
            ],
        ],
    ],
];
