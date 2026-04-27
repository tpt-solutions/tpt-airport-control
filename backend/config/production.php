<?php
/**
 * Production Environment Configuration
 *
 * Production-specific settings that override defaults
 */

return [
    'database' => [
        'host' => getenv('DB_HOST') ?: 'flight-control-db',
        'port' => getenv('DB_PORT') ?: 5432,
        'database' => getenv('DB_NAME') ?: 'flight_control_prod',
        'username' => getenv('DB_USER') ?: 'flight_control',
        'pool_size' => 20,
        'ssl_mode' => 'require'
    ],

    'cache' => [
        'backend' => 'redis',
        'ttl' => 7200, // 2 hours
        'redis_host' => getenv('REDIS_HOST') ?: 'flight-control-redis',
        'redis_port' => getenv('REDIS_PORT') ?: 6379,
        'redis_db' => 1
    ],

    'logging' => [
        'level' => 'WARNING',
        'backends' => ['file', 'syslog'],
        'file_path' => '/var/log/flight-control/app.log',
        'max_file_size' => 52428800, // 50MB
        'max_files' => 10
    ],

    'security' => [
        'jwt_ttl' => 1800, // 30 minutes
        'session_lifetime' => 7200, // 2 hours
        'rate_limit_requests' => 500,
        'rate_limit_window' => 1800 // 30 minutes
    ],

    'api' => [
        'cors_origins' => ['https://flight-control.example.com'],
        'response_compression' => true
    ],

    'cors_enabled' => true,
    'cors_origins' => ['https://flight-control.example.com'],
    'cors_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'],
    'cors_headers' => ['Content-Type', 'Authorization', 'X-Requested-With'],

    'email' => [
        'enabled' => true,
        'host' => getenv('SMTP_HOST') ?: 'smtp.gmail.com',
        'username' => getenv('SMTP_USER'),
        'port' => 587,
        'encryption' => 'tls',
        'from_address' => 'noreply@flight-control.example.com'
    ],

    'external_services' => [
        'weather_api_enabled' => true,
        'adsb_enabled' => true,
        'satellite_enabled' => true,
        'timeout' => 15,
        'retry_attempts' => 5
    ],

    'feature_flags' => [
        'advanced_analytics' => true,
        'ai_conflict_prediction' => true,
        'automated_clearance' => true
    ],

    'app' => [
        'debug' => false,
        'maintenance_mode' => false,
        'memory_limit' => '256M',
        'max_execution_time' => 60
    ]
];
