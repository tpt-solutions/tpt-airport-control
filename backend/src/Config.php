<?php
/**
 * Environment Configuration Management
 *
 * Centralized configuration management with environment-specific settings,
 * validation, and secure credential handling
 */

class Config
{
    private static $instance = null;
    private $config = [];
    private $env = 'development';
    private $configPath;
    private $secrets = [];
    private $validators = [];

    // Configuration sections
    const SECTION_DATABASE = 'database';
    const SECTION_CACHE = 'cache';
    const SECTION_LOGGING = 'logging';
    const SECTION_SECURITY = 'security';
    const SECTION_API = 'api';
    const SECTION_EMAIL = 'email';
    const SECTION_EXTERNAL_SERVICES = 'external_services';
    const SECTION_FEATURE_FLAGS = 'feature_flags';

    /**
     * Get singleton instance
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
            self::$instance->loadConfiguration();
        }
        return self::$instance;
    }

    /**
     * Private constructor for singleton
     */
    private function __construct()
    {
        $this->env = getenv('APP_ENV') ?: 'development';
        $this->configPath = __DIR__ . '/../../config/';

        $this->initializeValidators();
    }

    /**
     * Initialize configuration validators
     */
    private function initializeValidators()
    {
        $this->validators = [
            self::SECTION_DATABASE => [$this, 'validateDatabaseConfig'],
            self::SECTION_CACHE => [$this, 'validateCacheConfig'],
            self::SECTION_LOGGING => [$this, 'validateLoggingConfig'],
            self::SECTION_SECURITY => [$this, 'validateSecurityConfig'],
            self::SECTION_API => [$this, 'validateApiConfig'],
            self::SECTION_EMAIL => [$this, 'validateEmailConfig'],
            self::SECTION_EXTERNAL_SERVICES => [$this, 'validateExternalServicesConfig'],
        ];
    }

    /**
     * Load configuration from multiple sources
     */
    private function loadConfiguration()
    {
        // Load default configuration
        $this->loadDefaultConfig();

        // Load environment-specific configuration
        $this->loadEnvironmentConfig();

        // Load local overrides (for development)
        $this->loadLocalConfig();

        // Load secrets securely
        $this->loadSecrets();

        // Validate configuration
        $this->validateConfiguration();

        // Set PHP configuration based on loaded config
        $this->applyConfiguration();
    }

    /**
     * Load default configuration
     */
    private function loadDefaultConfig()
    {
        $defaultConfig = [
            self::SECTION_DATABASE => [
                'driver' => 'pgsql',
                'host' => 'localhost',
                'port' => 5432,
                'database' => 'flight_control',
                'charset' => 'utf8',
                'collation' => 'utf8_unicode_ci',
                'pool_size' => 10,
                'timeout' => 30,
                'ssl_mode' => 'prefer'
            ],

            self::SECTION_CACHE => [
                'backend' => 'memory',
                'ttl' => 3600,
                'prefix' => 'flight_control_',
                'redis_host' => 'localhost',
                'redis_port' => 6379,
                'redis_db' => 0,
                'memcached_servers' => ['localhost:11211']
            ],

            self::SECTION_LOGGING => [
                'level' => 'INFO',
                'backends' => ['file'],
                'file_path' => __DIR__ . '/../../logs/app.log',
                'max_file_size' => 10485760, // 10MB
                'max_files' => 5,
                'syslog_ident' => 'flight-control',
                'syslog_facility' => LOG_LOCAL0
            ],

            self::SECTION_SECURITY => [
                'jwt_secret' => null, // Must be set via environment
                'jwt_ttl' => 3600, // 1 hour
                'password_min_length' => 8,
                'password_hash_algorithm' => PASSWORD_ARGON2ID,
                'max_login_attempts' => 5,
                'lockout_duration' => 900, // 15 minutes
                'session_lifetime' => 3600,
                'csrf_token_length' => 32,
                'rate_limit_requests' => 1000,
                'rate_limit_window' => 3600 // 1 hour
            ],

            self::SECTION_API => [
                'version' => 'v1',
                'rate_limit' => true,
                'cors_enabled' => true,
                'cors_origins' => ['*'],
                'cors_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'],
                'cors_headers' => ['Content-Type', 'Authorization', 'X-Requested-With'],
                'pagination_default_limit' => 50,
                'pagination_max_limit' => 1000,
                'request_timeout' => 30,
                'response_compression' => true
            ],

            self::SECTION_EMAIL => [
                'enabled' => false,
                'driver' => 'smtp',
                'host' => 'localhost',
                'port' => 587,
                'encryption' => 'tls',
                'from_address' => 'noreply@flightcontrol.local',
                'from_name' => 'Flight Control System'
            ],

            self::SECTION_EXTERNAL_SERVICES => [
                'weather_api_enabled' => false,
                'weather_api_key' => null,
                'adsb_enabled' => false,
                'adsb_api_key' => null,
                'satellite_enabled' => false,
                'satellite_api_key' => null,
                'timeout' => 10,
                'retry_attempts' => 3,
                'circuit_breaker_threshold' => 5
            ],

            self::SECTION_FEATURE_FLAGS => [
                'real_time_updates' => true,
                'advanced_analytics' => false,
                'ai_conflict_prediction' => false,
                'automated_clearance' => false,
                'mobile_app' => true,
                'api_documentation' => true,
                'debug_mode' => false
            ],

            // Application settings
            'app' => [
                'name' => 'Flight Control System',
                'version' => '1.0.0',
                'environment' => $this->env,
                'debug' => $this->env === 'development',
                'timezone' => 'UTC',
                'locale' => 'en_US',
                'maintenance_mode' => false
            ]
        ];

        $this->config = array_merge($this->config, $defaultConfig);
    }

    /**
     * Load environment-specific configuration
     */
    private function loadEnvironmentConfig()
    {
        $envConfigFile = $this->configPath . $this->env . '.php';

        if (file_exists($envConfigFile)) {
            $envConfig = require $envConfigFile;

            // Deep merge environment config
            $this->config = $this->arrayMergeRecursive($this->config, $envConfig);
        }
    }

    /**
     * Load local configuration overrides
     */
    private function loadLocalConfig()
    {
        $localConfigFile = $this->configPath . 'local.php';

        if (file_exists($localConfigFile)) {
            $localConfig = require $localConfigFile;

            // Deep merge local config (overrides environment config)
            $this->config = $this->arrayMergeRecursive($this->config, $localConfig);
        }
    }

    /**
     * Load secrets from secure sources
     */
    private function loadSecrets()
    {
        // Load from environment variables (most secure)
        $this->loadSecretsFromEnv();

        // Load from .env file if it exists
        $this->loadSecretsFromDotEnv();

        // Load from secure key management service (future enhancement)
        // $this->loadSecretsFromKMS();
    }

    /**
     * Load secrets from environment variables
     */
    private function loadSecretsFromEnv()
    {
        $secretMappings = [
            'database.password' => 'DB_PASSWORD',
            'security.jwt_secret' => 'JWT_SECRET',
            'cache.redis_password' => 'REDIS_PASSWORD',
            'email.password' => 'SMTP_PASSWORD',
            'external_services.weather_api_key' => 'WEATHER_API_KEY',
            'external_services.adsb_api_key' => 'ADSB_API_KEY',
            'external_services.satellite_api_key' => 'SATELLITE_API_KEY'
        ];

        foreach ($secretMappings as $configKey => $envVar) {
            $value = getenv($envVar);
            if ($value !== false) {
                $this->set($configKey, $value);
            }
        }
    }

    /**
     * Load secrets from .env file
     */
    private function loadSecretsFromDotEnv()
    {
        $dotEnvFile = __DIR__ . '/../../.env';

        if (file_exists($dotEnvFile)) {
            $lines = file($dotEnvFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

            foreach ($lines as $line) {
                // Skip comments
                if (strpos($line, '#') === 0) {
                    continue;
                }

                // Parse KEY=VALUE
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value);

                    // Remove quotes if present
                    $value = trim($value, '"\'');

                    // Map common .env variables to config keys
                    $envMappings = [
                        'DB_PASSWORD' => 'database.password',
                        'JWT_SECRET' => 'security.jwt_secret',
                        'REDIS_PASSWORD' => 'cache.redis_password',
                        'SMTP_PASSWORD' => 'email.password',
                        'WEATHER_API_KEY' => 'external_services.weather_api_key',
                        'ADSB_API_KEY' => 'external_services.adsb_api_key',
                        'SATELLITE_API_KEY' => 'external_services.satellite_api_key'
                    ];

                    if (isset($envMappings[$key])) {
                        $this->set($envMappings[$key], $value);
                    }
                }
            }
        }
    }

    /**
     * Validate configuration
     */
    private function validateConfiguration()
    {
        $logger = Logger::getInstance();

        foreach ($this->validators as $section => $validator) {
            try {
                call_user_func($validator);
            } catch (Exception $e) {
                $logger->error("Configuration validation failed for section '{$section}'", [
                    'error' => $e->getMessage(),
                    'section' => $section
                ]);

                // In production, this might throw an exception to prevent startup
                if ($this->env === 'production') {
                    throw new Exception("Invalid configuration in section '{$section}': " . $e->getMessage());
                }
            }
        }
    }

    /**
     * Apply configuration to PHP runtime
     */
    private function applyConfiguration()
    {
        // Set timezone
        if (isset($this->config['app']['timezone'])) {
            date_default_timezone_set($this->config['app']['timezone']);
        }

        // Set locale
        if (isset($this->config['app']['locale'])) {
            setlocale(LC_ALL, $this->config['app']['locale']);
        }

        // Configure error reporting
        if ($this->config['app']['debug']) {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
        } else {
            error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
            ini_set('display_errors', '0');
        }

        // Configure session settings
        if (isset($this->config[self::SECTION_SECURITY]['session_lifetime'])) {
            ini_set('session.gc_maxlifetime', $this->config[self::SECTION_SECURITY]['session_lifetime']);
        }

        // Configure memory limit
        if (isset($this->config['app']['memory_limit'])) {
            ini_set('memory_limit', $this->config['app']['memory_limit']);
        }

        // Configure execution time
        if (isset($this->config['app']['max_execution_time'])) {
            ini_set('max_execution_time', $this->config['app']['max_execution_time']);
        }
    }

    // ===== CONFIGURATION VALIDATORS =====

    private function validateDatabaseConfig()
    {
        $db = $this->config[self::SECTION_DATABASE];

        if (empty($db['host'])) {
            throw new Exception('Database host is required');
        }

        if (empty($db['database'])) {
            throw new Exception('Database name is required');
        }

        if (!in_array($db['driver'], ['mysql', 'pgsql', 'sqlite', 'sqlsrv'])) {
            throw new Exception('Unsupported database driver: ' . $db['driver']);
        }

        if ($db['pool_size'] < 1 || $db['pool_size'] > 100) {
            throw new Exception('Database pool size must be between 1 and 100');
        }
    }

    private function validateCacheConfig()
    {
        $cache = $this->config[self::SECTION_CACHE];

        if (!in_array($cache['backend'], ['memory', 'file', 'redis', 'memcached'])) {
            throw new Exception('Unsupported cache backend: ' . $cache['backend']);
        }

        if ($cache['ttl'] < 0) {
            throw new Exception('Cache TTL must be non-negative');
        }

        if ($cache['backend'] === 'redis' && empty($cache['redis_host'])) {
            throw new Exception('Redis host is required when using Redis backend');
        }
    }

    private function validateLoggingConfig()
    {
        $logging = $this->config[self::SECTION_LOGGING];

        $validLevels = ['EMERGENCY', 'ALERT', 'CRITICAL', 'ERROR', 'WARNING', 'NOTICE', 'INFO', 'DEBUG'];
        if (!in_array(strtoupper($logging['level']), $validLevels)) {
            throw new Exception('Invalid log level: ' . $logging['level']);
        }

        $validBackends = ['file', 'console', 'database', 'syslog'];
        foreach ($logging['backends'] as $backend) {
            if (!in_array($backend, $validBackends)) {
                throw new Exception('Invalid log backend: ' . $backend);
            }
        }
    }

    private function validateSecurityConfig()
    {
        $security = $this->config[self::SECTION_SECURITY];

        if (empty($security['jwt_secret'])) {
            throw new Exception('JWT secret is required for security');
        }

        if (strlen($security['jwt_secret']) < 32) {
            throw new Exception('JWT secret must be at least 32 characters long');
        }

        if ($security['password_min_length'] < 8) {
            throw new Exception('Password minimum length must be at least 8 characters');
        }

        if ($security['max_login_attempts'] < 1) {
            throw new Exception('Maximum login attempts must be at least 1');
        }
    }

    private function validateApiConfig()
    {
        $api = $this->config[self::SECTION_API];

        if (!preg_match('/^v\d+$/', $api['version'])) {
            throw new Exception('API version must be in format v1, v2, etc.');
        }

        if ($api['pagination_default_limit'] < 1 || $api['pagination_default_limit'] > $api['pagination_max_limit']) {
            throw new Exception('Invalid pagination default limit');
        }
    }

    private function validateEmailConfig()
    {
        $email = $this->config[self::SECTION_EMAIL];

        if ($email['enabled']) {
            if (empty($email['host'])) {
                throw new Exception('SMTP host is required when email is enabled');
            }

            if (!in_array($email['encryption'], ['tls', 'ssl', null])) {
                throw new Exception('Invalid email encryption: ' . $email['encryption']);
            }
        }
    }

    private function validateExternalServicesConfig()
    {
        $services = $this->config[self::SECTION_EXTERNAL_SERVICES];

        if ($services['timeout'] < 1 || $services['timeout'] > 300) {
            throw new Exception('External service timeout must be between 1 and 300 seconds');
        }

        if ($services['retry_attempts'] < 0 || $services['retry_attempts'] > 10) {
            throw new Exception('Retry attempts must be between 0 and 10');
        }
    }

    // ===== PUBLIC API METHODS =====

    /**
     * Get configuration value
     */
    public function get($key, $default = null)
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * Set configuration value
     */
    public function set($key, $value)
    {
        $keys = explode('.', $key);
        $config = &$this->config;

        foreach ($keys as $k) {
            if (!isset($config[$k])) {
                $config[$k] = [];
            }
            $config = &$config[$k];
        }

        $config = $value;
    }

    /**
     * Check if configuration key exists
     */
    public function has($key)
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return false;
            }
            $value = $value[$k];
        }

        return true;
    }

    /**
     * Get entire configuration section
     */
    public function getSection($section)
    {
        return $this->config[$section] ?? [];
    }

    /**
     * Get all configuration
     */
    public function all()
    {
        return $this->config;
    }

    /**
     * Get current environment
     */
    public function getEnvironment()
    {
        return $this->env;
    }

    /**
     * Check if in production environment
     */
    public function isProduction()
    {
        return $this->env === 'production';
    }

    /**
     * Check if in development environment
     */
    public function isDevelopment()
    {
        return $this->env === 'development';
    }

    /**
     * Check if debug mode is enabled
     */
    public function isDebug()
    {
        return $this->config['app']['debug'] ?? false;
    }

    /**
     * Check if maintenance mode is enabled
     */
    public function isMaintenanceMode()
    {
        return $this->config['app']['maintenance_mode'] ?? false;
    }

    /**
     * Check if feature flag is enabled
     */
    public function isFeatureEnabled($feature)
    {
        return $this->config[self::SECTION_FEATURE_FLAGS][$feature] ?? false;
    }

    /**
     * Get database configuration for PDO
     */
    public function getDatabaseConfig()
    {
        $db = $this->config[self::SECTION_DATABASE];

        return [
            'dsn' => $this->buildDsn($db),
            'username' => $db['username'] ?? null,
            'password' => $db['password'] ?? null,
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        ];
    }

    /**
     * Build DSN string
     */
    private function buildDsn($db)
    {
        switch ($db['driver']) {
            case 'pgsql':
                return "pgsql:host={$db['host']};port={$db['port']};dbname={$db['database']}";
            case 'mysql':
                return "mysql:host={$db['host']};port={$db['port']};dbname={$db['database']};charset={$db['charset']}";
            case 'sqlite':
                return "sqlite:{$db['database']}";
            case 'sqlsrv':
                return "sqlsrv:Server={$db['host']},{$db['port']};Database={$db['database']}";
            default:
                throw new Exception('Unsupported database driver: ' . $db['driver']);
        }
    }

    /**
     * Deep merge arrays
     */
    private function arrayMergeRecursive($array1, $array2)
    {
        $merged = $array1;

        foreach ($array2 as $key => $value) {
            if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
                $merged[$key] = $this->arrayMergeRecursive($merged[$key], $value);
            } else {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }
}

// Global helper function
if (!function_exists('config')) {
    /**
     * Get configuration value
     */
    function config($key, $default = null) {
        return Config::getInstance()->get($key, $default);
    }
}

// Usage examples:
/*
// Get configuration values
$appName = config('app.name');
$dbHost = config('database.host');
$isDebug = config('app.debug');

// Check environment
$config = Config::getInstance();
if ($config->isProduction()) {
    // Production-specific code
}

// Check feature flags
if ($config->isFeatureEnabled('ai_conflict_prediction')) {
    // Enable AI features
}

// Get database config for PDO
$dbConfig = $config->getDatabaseConfig();
$pdo = new PDO($dbConfig['dsn'], $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
*/
?>
