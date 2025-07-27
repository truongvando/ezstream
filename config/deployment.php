<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Deployment Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains deployment-specific settings that differ between
    | local development and production environments.
    |
    */

    // VPS Operations - Enable real VPS provisioning only in production
    'vps_operations_enabled' => env('VPS_OPERATIONS_ENABLED', env('APP_ENV') === 'production'),
    
    // SSH Operations - Enable real SSH connections only in production  
    'ssh_operations_enabled' => env('SSH_OPERATIONS_ENABLED', env('APP_ENV') === 'production'),
    
    // Queue Processing - Different behavior per environment
    'queue_processing' => [
        'local' => [
            'vps_provisioning' => 'database', // Use real queue for testing
            'default' => 'sync',
        ],
        'staging' => [
            'vps_provisioning' => 'database', // Use real queue
            'default' => 'database',
        ],
        'production' => [
            'vps_provisioning' => 'database', // Use real queue with Supervisor
            'default' => 'database',
        ],
    ],
    
    // Background Processes - Different per environment
    'background_processes' => [
        'local' => [
            'supervisor_enabled' => false,
            'manual_queue_workers' => true,
        ],
        'production' => [
            'supervisor_enabled' => true,
            'manual_queue_workers' => false,
        ],
    ],
    
    // VPS Provisioning Settings
    'vps_provisioning' => [
        'timeout' => env('VPS_PROVISION_TIMEOUT', 600), // 10 minutes
        'retry_attempts' => env('VPS_PROVISION_RETRIES', 3),
        'mock_in_local' => env('VPS_MOCK_LOCAL', env('APP_ENV') !== 'production'),
    ],
    
    // Agent Settings
    'agent' => [
        'verification_timeout' => env('AGENT_VERIFICATION_TIMEOUT', 30),
        'startup_delay' => env('AGENT_STARTUP_DELAY', 5),
        'mock_in_local' => env('AGENT_MOCK_LOCAL', env('APP_ENV') !== 'production'),
    ],
];
