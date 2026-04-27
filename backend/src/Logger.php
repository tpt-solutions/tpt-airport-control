<?php
/**
 * Standardized Logging Service
 *
 * Provides unified logging interface with structured logging,
 * multiple log levels, and configurable backends
 */

namespace TPT\FlightControl;

class Logger
{
    private static $instance = null;
    private $backends = [];
    private $minLevel;
    private $defaultContext;

    // Log levels (RFC 5424)
    const EMERGENCY = 0; // system is unusable
    const ALERT     = 1; // action must be taken immediately
    const CRITICAL  = 2; // critical conditions
    const ERROR     = 3; // error conditions
    const WARNING   = 4; // warning conditions
    const NOTICE    = 5; // normal but significant condition
    const INFO      = 6; // informational messages
    const DEBUG     = 7; // debug-level messages

    // Log level names
    private static $levelNames = [
        self::EMERGENCY => 'EMERGENCY',
        self::ALERT     => 'ALERT',
        self::CRITICAL  => 'CRITICAL',
        self::ERROR     => 'ERROR',
        self::WARNING   => 'WARNING',
        self::NOTICE    => 'NOTICE',
        self::INFO      => 'INFO',
        self::DEBUG     => 'DEBUG'
    ];

    /**
     * Get singleton instance
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Static log wrapper for audit logging
     */
    public static function auditLog($userId, $action, $resourceType, $resourceId, $description = '')
    {
        $instance = self::getInstance();
        $instance->info($description, [
            'user_id' => $userId,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId
        ]);
    }

    /**
     * Private constructor for singleton
     */
    private function __construct()
    {
        $this->minLevel = $this->getMinLevelFromEnv();
        $this->defaultContext = [
            'service' => 'flight-control',
            'version' => getenv('APP_VERSION') ?: '1.0.0',
            'environment' => getenv('APP_ENV') ?: 'development'
        ];

        $this->initializeBackends();
    }

    /**
     * Get minimum log level from environment
     */
    private function getMinLevelFromEnv()
    {
        $level = getenv('LOG_LEVEL') ?: 'INFO';

        $levels = array_flip(self::$levelNames);
        return isset($levels[$level]) ? $levels[$level] : self::INFO;
    }

    /**
     * Initialize logging backends
     */
    private function initializeBackends()
    {
        $backends = getenv('LOG_BACKENDS') ?: 'file';

        foreach (explode(',', $backends) as $backend) {
            $backend = trim($backend);

            switch ($backend) {
                case 'file':
                    $this->backends[] = new FileLoggerBackend();
                    break;
                case 'console':
                    $this->backends[] = new ConsoleLoggerBackend();
                    break;
                case 'database':
                    $this->backends[] = new DatabaseLoggerBackend();
                    break;
                case 'syslog':
                    $this->backends[] = new SyslogLoggerBackend();
                    break;
                default:
                    // Unknown backend, skip
                    break;
            }
        }

        // Always have at least one backend
        if (empty($this->backends)) {
            $this->backends[] = new FileLoggerBackend();
        }
    }

    /**
     * Log a message
     */
    public function log($level, $message, array $context = [])
    {
        if ($level > $this->minLevel) {
            return;
        }

        $entry = $this->createLogEntry($level, $message, $context);

        foreach ($this->backends as $backend) {
            $backend->write($entry);
        }
    }

    /**
     * Create a structured log entry
     */
    private function createLogEntry($level, $message, array $context = [])
    {
        $timestamp = microtime(true);
        $levelName = self::$levelNames[$level] ?? 'UNKNOWN';

        // Merge default context with provided context
        $fullContext = array_merge($this->defaultContext, $context);

        // Add request context if available
        if (isset($_SERVER)) {
            $fullContext['request'] = [
                'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
                'uri' => $_SERVER['REQUEST_URI'] ?? $_SERVER['SCRIPT_NAME'] ?? 'unknown',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ];
        }

        return [
            'timestamp' => $timestamp,
            'datetime' => date('Y-m-d H:i:s', (int)$timestamp),
            'level' => $level,
            'level_name' => $levelName,
            'message' => $message,
            'context' => $fullContext,
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true)
        ];
    }

    /**
     * Emergency: system is unusable
     */
    public static function emergency($message, array $context = [])
    {
        self::getInstance()->log(self::EMERGENCY, $message, $context);
    }

    /**
     * Alert: action must be taken immediately
     */
    public static function alert($message, array $context = [])
    {
        self::getInstance()->log(self::ALERT, $message, $context);
    }

    /**
     * Critical: critical conditions
     */
    public static function critical($message, array $context = [])
    {
        self::getInstance()->log(self::CRITICAL, $message, $context);
    }

    /**
     * Error: error conditions
     */
    public static function error($message, array $context = [])
    {
        self::getInstance()->log(self::ERROR, $message, $context);
    }

    /**
     * Warning: warning conditions
     */
    public static function warning($message, array $context = [])
    {
        self::getInstance()->log(self::WARNING, $message, $context);
    }

    /**
     * Notice: normal but significant condition
     */
    public static function notice($message, array $context = [])
    {
        self::getInstance()->log(self::NOTICE, $message, $context);
    }

    /**
     * Info: informational messages
     */
    public static function info($message, array $context = [])
    {
        self::getInstance()->log(self::INFO, $message, $context);
    }

    /**
     * Debug: debug-level messages
     */
    public static function debug($message, array $context = [])
    {
        self::getInstance()->log(self::DEBUG, $message, $context);
    }

    /**
     * Log performance metrics
     */
    public function performance($operation, $duration, array $context = [])
    {
        $context = array_merge($context, [
            'operation' => $operation,
            'duration_ms' => $duration,
            'performance' => true
        ]);

        if ($duration > 1000) { // Log slow operations as warnings
            $this->warning("Slow operation: {$operation} took {$duration}ms", $context);
        } else {
            $this->info("Performance: {$operation} completed in {$duration}ms", $context);
        }
    }

    /**
     * Log API requests
     */
    public function apiRequest($method, $endpoint, $statusCode, $duration, array $context = [])
    {
        $context = array_merge($context, [
            'method' => $method,
            'endpoint' => $endpoint,
            'status_code' => $statusCode,
            'duration_ms' => $duration,
            'api' => true
        ]);

        if ($statusCode >= 500) {
            $this->error("API Error: {$method} {$endpoint} returned {$statusCode}", $context);
        } elseif ($statusCode >= 400) {
            $this->warning("API Client Error: {$method} {$endpoint} returned {$statusCode}", $context);
        } else {
            $this->info("API Request: {$method} {$endpoint}", $context);
        }
    }

    /**
     * Log database queries
     */
    public function databaseQuery($query, $duration, array $context = [])
    {
        $context = array_merge($context, [
            'query' => $query,
            'duration_ms' => $duration,
            'database' => true
        ]);

        if ($duration > 100) { // Log slow queries as warnings
            $this->warning("Slow query: {$query} took {$duration}ms", $context);
        } else {
            $this->debug("Database query executed in {$duration}ms", $context);
        }
    }

    /**
     * Log security events
     */
    public function security($event, array $context = [])
    {
        $context = array_merge($context, [
            'event' => $event,
            'security' => true
        ]);

        $this->warning("Security event: {$event}", $context);
    }

    /**
     * Log user actions
     */
    public function userAction($userId, $action, array $context = [])
    {
        $context = array_merge($context, [
            'user_id' => $userId,
            'action' => $action,
            'audit' => true
        ]);

        $this->info("User action: {$action}", $context);
    }

    /**
     * Get current log level
     */
    public function getMinLevel()
    {
        return $this->minLevel;
    }

    /**
     * Set minimum log level
     */
    public function setMinLevel($level)
    {
        $this->minLevel = $level;
    }

    /**
     * Get log level name
     */
    public static function getLevelName($level)
    {
        return self::$levelNames[$level] ?? 'UNKNOWN';
    }
}

/**
 * Logger Backend Interface
 */
interface LoggerBackendInterface
{
    public function write(array $entry);
}

/**
 * File Logger Backend
 */
class FileLoggerBackend implements LoggerBackendInterface
{
    private $logFile;
    private $maxFileSize;
    private $maxFiles;

    public function __construct()
    {
        $this->logFile = getenv('LOG_FILE') ?: __DIR__ . '/../../logs/app.log';
        $this->maxFileSize = getenv('LOG_MAX_SIZE') ?: 10485760; // 10MB
        $this->maxFiles = getenv('LOG_MAX_FILES') ?: 5;

        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }

    public function write(array $entry)
    {
        $this->rotateLogIfNeeded();

        $formatted = $this->formatEntry($entry);
        file_put_contents($this->logFile, $formatted . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    private function formatEntry(array $entry)
    {
        $timestamp = $entry['datetime'];
        $level = $entry['level_name'];
        $message = $entry['message'];
        $context = json_encode($entry['context']);

        return "[{$timestamp}] {$level}: {$message} {$context}";
    }

    private function rotateLogIfNeeded()
    {
        if (!file_exists($this->logFile)) {
            return;
        }

        if (filesize($this->logFile) < $this->maxFileSize) {
            return;
        }

        // Rotate files
        for ($i = $this->maxFiles - 1; $i >= 1; $i--) {
            $oldFile = $this->logFile . '.' . $i;
            $newFile = $this->logFile . '.' . ($i + 1);

            if (file_exists($oldFile)) {
                rename($oldFile, $newFile);
            }
        }

        // Move current log file
        rename($this->logFile, $this->logFile . '.1');
    }
}

/**
 * Console Logger Backend (for development)
 */
class ConsoleLoggerBackend implements LoggerBackendInterface
{
    public function write(array $entry)
    {
        $formatted = $this->formatEntry($entry);

        // Color coding for console output
        $color = $this->getColorCode($entry['level']);

        if (PHP_SAPI === 'cli') {
            echo $color . $formatted . "\033[0m" . PHP_EOL;
        } else {
            echo $formatted . PHP_EOL;
        }
    }

    private function formatEntry(array $entry)
    {
        $timestamp = $entry['datetime'];
        $level = $entry['level_name'];
        $message = $entry['message'];

        return "[{$timestamp}] {$level}: {$message}";
    }

    private function getColorCode($level)
    {
        switch ($level) {
            case Logger::EMERGENCY:
            case Logger::ALERT:
            case Logger::CRITICAL:
                return "\033[1;31m"; // Bright red
            case Logger::ERROR:
                return "\033[31m"; // Red
            case Logger::WARNING:
                return "\033[33m"; // Yellow
            case Logger::NOTICE:
            case Logger::INFO:
                return "\033[32m"; // Green
            case Logger::DEBUG:
                return "\033[36m"; // Cyan
            default:
                return "\033[0m"; // Default
        }
    }
}

/**
 * Database Logger Backend
 */
class DatabaseLoggerBackend implements LoggerBackendInterface
{
    private $pdo;

    public function __construct()
    {
        // This would need database connection
        // For now, we'll skip database logging in this implementation
    }

    public function write(array $entry)
    {
        // TODO: Implement database logging
        // This would insert log entries into a logs table
    }
}

/**
 * Syslog Logger Backend
 */
class SyslogLoggerBackend implements LoggerBackendInterface
{
    private $ident;
    private $facility;

    public function __construct()
    {
        $this->ident = getenv('SYSLOG_IDENT') ?: 'flight-control';
        $this->facility = LOG_LOCAL0;

        openlog($this->ident, LOG_PID | LOG_PERROR, $this->facility);
    }

    public function write(array $entry)
    {
        $priority = $this->mapLevelToSyslogPriority($entry['level']);
        $message = $this->formatEntry($entry);

        syslog($priority, $message);
    }

    private function formatEntry(array $entry)
    {
        $level = $entry['level_name'];
        $message = $entry['message'];
        $context = json_encode($entry['context']);

        return "{$level}: {$message} {$context}";
    }

    private function mapLevelToSyslogPriority($level)
    {
        switch ($level) {
            case Logger::EMERGENCY:
                return LOG_EMERG;
            case Logger::ALERT:
                return LOG_ALERT;
            case Logger::CRITICAL:
                return LOG_CRIT;
            case Logger::ERROR:
                return LOG_ERR;
            case Logger::WARNING:
                return LOG_WARNING;
            case Logger::NOTICE:
                return LOG_NOTICE;
            case Logger::INFO:
                return LOG_INFO;
            case Logger::DEBUG:
                return LOG_DEBUG;
            default:
                return LOG_INFO;
        }
    }

    public function __destruct()
    {
        closelog();
    }
}

// Usage examples:
/*
// Basic logging
Logger::info("User logged in", ['user_id' => 123]);
Logger::error("Database connection failed", ['error' => $e->getMessage()]);
Logger::warning("High memory usage detected", ['usage' => memory_get_usage()]);

// Performance logging
$start = microtime(true);
// ... some operation ...
$duration = (microtime(true) - $start) * 1000;
Logger::performance("database_query", $duration, ['query' => $sql]);

// API logging
Logger::apiRequest("POST", "/api/flights", 200, 150.5, ['user_id' => 123]);

// Security logging
Logger::security("failed_login_attempt", ['ip' => $_SERVER['REMOTE_ADDR'], 'username' => $username]);

// User action logging
Logger::userAction(123, "created_flight", ['flight_id' => 456]);
*/
?>
