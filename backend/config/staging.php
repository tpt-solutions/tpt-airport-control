<?php
/**
 * Staging Environment Configuration
 *
 * Staging-specific settings that override defaults
 */

return [
    'database' => [
        'host' => getenv('DB_HOST') ?: 'staging-db',
        'port' => getenv('DB_PORT') ?: 5432,
        'database' => getenv('DB_NAME') ?: 'flight_control_staging',
        'username' => getenv('DB_USER') ?: 'flight_control',
        'pool_size' => 15,
        'ssl_mode' => 'prefer'
    ],

    'cache' => [
        'backend' => 'redis',
        'ttl' => 3600, // 1 hour
        'redis_host' => getenv('REDIS_HOST') ?: 'staging-redis',
        'redis_port' => getenv('REDIS_PORT') ?: 6379,
        'redis_db' => 2
    ],

    'logging' => [
        'level' => 'INFO',
        'backends' => ['file'],
        'file_path' => '/var/log/flight-control/staging.log',
        'max_file_size' => 26214400, // 25MB
        'max_files' => 7
    ],

    'security' => [
        'jwt_ttl' => 3600, // 1 hour
        'session_lifetime' => 3600, // 1 hour
        'rate_limit_requests' => 1000,
        'rate_limit_window' => 3600 // 1 hour
    ],

    'api' => [
        'cors_origins' => ['https://staging.flight-control.example.com'],
        'response_compression' => true
    ],

    'email' => [
        'enabled' => true,
        'host' => getenv('SMTP_HOST') ?: 'smtp.mailtrap.io',
        'username' => getenv('SMTP_USER'),
        'port' => 2525,
        'encryption' => null,
        'from_address' => 'noreply@staging.flight-control.example.com'
    ],

    'external_services' => [
        'weather_api_enabled' => true,
        'adsb_enabled' => true,
        'satellite_enabled' => false, // Disabled in staging
        'timeout' => 12,
        'retry_attempts' => 4
    ],

    'feature_flags' => [
        'advanced_analytics' => true,
        'ai_conflict_prediction' => false, // Disabled in staging
        'automated_clearance' => true
    ],

    'app' => [
        'debug' => false,
        'maintenance_mode' => false,
        'memory_limit' => '128M',
        'max_execution_time' => 30
    ]
];
