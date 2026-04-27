<?php
/**
 * Base Integration Abstract Class
 * 
 * Standard base class for all external integrations
 * Provides circuit breakers, caching, retries, metrics, health checks
 * All integrations must extend this class
 * 
 * @package TPT Flight Control
 * @version 1.0.0
 */

abstract class BaseIntegration
{
    protected $db;
    protected $logger;
    protected $config = [];
    protected $cache = [];
    protected $metrics = [];
    protected $circuitBreaker = [
        'state' => 'CLOSED',
        'failure_count' => 0,
        'last_failure_time' => 0,
        'open_until' => 0,
        'success_threshold' => 3,
        'failure_threshold' => 5,
        'recovery_timeout' => 60
    ];

    const CIRCUIT_CLOSED = 'CLOSED';
    const CIRCUIT_OPEN = 'OPEN';
    const CIRCUIT_HALF_OPEN = 'HALF_OPEN';

    /**
     * Constructor - Standard signature for all integrations
     */
    public function __construct($database, $logger, array $config = [])
    {
        $this->db = $database;
        $this->logger = $logger;
        $this->config = array_merge($this->getDefaultConfig(), $config);
        
        $this->initializeMetrics();
        $this->loadCircuitBreakerState();
    }

    /**
     * Get default configuration for this integration
     * Override in child classes
     */
    protected function getDefaultConfig(): array
    {
        return [
            'cache_ttl' => 300,
            'request_timeout' => 10,
            'max_retries' => 3,
            'retry_delay' => 1000,
            'circuit_breaker_enabled' => true
        ];
    }

    /**
     * Initialize metrics tracking
     */
    private function initializeMetrics(): void
    {
        $this->metrics = [
            'requests_total' => 0,
            'requests_success' => 0,
            'requests_failed' => 0,
            'cache_hits' => 0,
            'cache_misses' => 0,
            'total_latency_ms' => 0,
            'last_success_at' => null,
            'last_failure_at' => null
        ];
    }

    /**
     * Check if circuit breaker allows requests
     */
    protected function canMakeRequest(): bool
    {
        if (!$this->config['circuit_breaker_enabled']) {
            return true;
        }

        $now = time();

        if ($this->circuitBreaker['state'] === self::CIRCUIT_OPEN) {
            if ($now >= $this->circuitBreaker['open_until']) {
                $this->circuitBreaker['state'] = self::CIRCUIT_HALF_OPEN;
                $this->logger->info('Circuit breaker entering half-open state', [
                    'integration' => static::class
                ]);
                return true;
            }
            return false;
        }

        return true;
    }

    /**
     * Record successful request
     */
    protected function recordSuccess(): void
    {
        $this->metrics['requests_success']++;
        $this->metrics['last_success_at'] = time();

        if ($this->circuitBreaker['state'] === self::CIRCUIT_HALF_OPEN) {
            $this->circuitBreaker['failure_count']--;
            if ($this->circuitBreaker['failure_count'] <= 0) {
                $this->circuitBreaker['state'] = self::CIRCUIT_CLOSED;
                $this->circuitBreaker['failure_count'] = 0;
                $this->logger->info('Circuit breaker closed, service restored', [
                    'integration' => static::class
                ]);
            }
        }
    }

    /**
     * Record failed request
     */
    protected function recordFailure(): void
    {
        $this->metrics['requests_failed']++;
        $this->metrics['last_failure_at'] = time();
        $this->circuitBreaker['failure_count']++;
        $this->circuitBreaker['last_failure_time'] = time();

        if ($this->circuitBreaker['state'] === self::CIRCUIT_HALF_OPEN) {
            $this->openCircuit();
            return;
        }

        if ($this->circuitBreaker['failure_count'] >= $this->circuitBreaker['failure_threshold']) {
            $this->openCircuit();
        }
    }

    /**
     * Open circuit breaker
     */
    private function openCircuit(): void
    {
        $this->circuitBreaker['state'] = self::CIRCUIT_OPEN;
        $this->circuitBreaker['open_until'] = time() + $this->circuitBreaker['recovery_timeout'];
        
        $this->logger->error('Circuit breaker opened, stopping requests', [
            'integration' => static::class,
            'recovery_seconds' => $this->circuitBreaker['recovery_timeout']
        ]);
    }

    /**
     * Get cached value
     */
    protected function getCache(string $key)
    {
        if (!isset($this->cache[$key])) {
            $this->metrics['cache_misses']++;
            return null;
        }

        $entry = $this->cache[$key];
        if (time() > $entry['expires']) {
            unset($this->cache[$key]);
            $this->metrics['cache_misses']++;
            return null;
        }

        $this->metrics['cache_hits']++;
        return $entry['data'];
    }

    /**
     * Set cache value
     */
    protected function setCache(string $key, $data, ?int $ttl = null): void
    {
        $ttl = $ttl ?? $this->config['cache_ttl'];
        $this->cache[$key] = [
            'data' => $data,
            'expires' => time() + $ttl,
            'stored_at' => time()
        ];
    }

    /**
     * Make HTTP request with automatic retries and error handling
     */
    protected function makeRequest(string $url, string $method = 'GET', array $options = [])
    {
        if (!$this->canMakeRequest()) {
            throw new Exception('Circuit breaker is open, request blocked');
        }

        $this->metrics['requests_total']++;
        $startTime = microtime(true);

        $attempts = 0;
        $maxAttempts = $this->config['max_retries'] + 1;

        while ($attempts < $maxAttempts) {
            try {
                $result = $this->executeCurlRequest($url, $method, $options);
                
                $latency = round((microtime(true) - $startTime) * 1000);
                $this->metrics['total_latency_ms'] += $latency;
                
                $this->recordSuccess();
                return $result;

            } catch (Exception $e) {
                $attempts++;
                
                if ($attempts >= $maxAttempts) {
                    $this->recordFailure();
                    throw $e;
                }

                usleep($this->config['retry_delay'] * 1000 * $attempts);
            }
        }
    }

    /**
     * Execute actual curl request
     */
    private function executeCurlRequest(string $url, string $method, array $options)
    {
        $ch = curl_init();

        $headers = $options['headers'] ?? [];
        $headers[] = 'User-Agent: Flight-Control-System/1.0';

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->config['request_timeout'],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_HTTPHEADER => $headers
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $options['body'] ?? '');
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        if ($error) {
            throw new Exception("Request failed: {$error}");
        }

        if ($httpCode >= 400) {
            throw new Exception("Request failed with HTTP {$httpCode}");
        }

        return $response;
    }

    /**
     * Get integration health status
     * Must be implemented by child classes
     */
    abstract public function healthCheck(): array;

    /**
     * Get current metrics
     */
    public function getMetrics(): array
    {
        return $this->metrics;
    }

    /**
     * Get circuit breaker status
     */
    public function getCircuitBreakerStatus(): array
    {
        return $this->circuitBreaker;
    }

    /**
     * Load circuit breaker state from storage
     */
    private function loadCircuitBreakerState(): void
    {
        // In production this would load from database/cache
        // Currently in-memory only
    }

    /**
     * Get integration name
     */
    public static function getName(): string
    {
        return static::class;
    }

    /**
     * Get integration description
     */
    public static function getDescription(): string
    {
        return '';
    }
}