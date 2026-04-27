<?php
/**
 * Rate Limiting Service
 *
 * Provides rate limiting functionality for API endpoints using Redis
 * Prevents abuse and ensures fair resource usage
 */

class RateLimiter
{
    private static $instance = null;
    private $redis;
    private $prefix = 'rate_limit:';
    private $defaultLimits = [
        'api' => ['requests' => 1000, 'window' => 3600], // 1000 requests per hour
        'auth' => ['requests' => 5, 'window' => 300],    // 5 auth attempts per 5 minutes
        'search' => ['requests' => 100, 'window' => 60],  // 100 searches per minute
        'upload' => ['requests' => 10, 'window' => 3600], // 10 uploads per hour
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
     * Private constructor for singleton
     */
    private function __construct()
    {
        $this->initializeRedis();
    }

    /**
     * Initialize Redis connection
     */
    private function initializeRedis()
    {
        try {
            $this->redis = new Redis();
            $host = getenv('REDIS_HOST') ?: 'localhost';
            $port = getenv('REDIS_PORT') ?: 6379;
            $password = getenv('REDIS_PASSWORD') ?: null;

            $this->redis->connect($host, $port);
            if ($password) {
                $this->redis->auth($password);
            }

            // Test connection
            $this->redis->ping();
        } catch (Exception $e) {
            // Fallback to in-memory storage if Redis is not available
            $this->redis = null;
            error_log('Redis connection failed, using in-memory rate limiting: ' . $e->getMessage());
        }
    }

    /**
     * Check if request is within rate limits
     */
    public function checkLimit($identifier, $action = 'api', $customLimit = null)
    {
        $limit = $customLimit ?: ($this->defaultLimits[$action] ?? $this->defaultLimits['api']);
        $key = $this->getKey($identifier, $action);

        if ($this->redis) {
            return $this->checkRedisLimit($key, $limit);
        } else {
            return $this->checkMemoryLimit($key, $limit);
        }
    }

    /**
     * Check rate limit using Redis
     */
    private function checkRedisLimit($key, array $limit)
    {
        $current = $this->redis->get($key);

        if ($current === false) {
            // First request in window
            $this->redis->setex($key, $limit['window'], 1);
            return ['allowed' => true, 'remaining' => $limit['requests'] - 1, 'reset' => time() + $limit['window']];
        }

        $current = intval($current);

        if ($current >= $limit['requests']) {
            // Rate limit exceeded
            $ttl = $this->redis->ttl($key);
            return ['allowed' => false, 'remaining' => 0, 'reset' => time() + $ttl, 'retry_after' => $ttl];
        }

        // Increment counter
        $newCount = $this->redis->incr($key);
        $ttl = $this->redis->ttl($key);

        return [
            'allowed' => true,
            'remaining' => $limit['requests'] - $newCount,
            'reset' => time() + $ttl
        ];
    }

    /**
     * Check rate limit using in-memory storage (fallback)
     */
    private function checkMemoryLimit($key, array $limit)
    {
        static $memoryStorage = [];

        $now = time();

        if (!isset($memoryStorage[$key])) {
            $memoryStorage[$key] = ['count' => 1, 'reset' => $now + $limit['window']];
            return ['allowed' => true, 'remaining' => $limit['requests'] - 1, 'reset' => $now + $limit['window']];
        }

        $data = &$memoryStorage[$key];

        // Check if window has expired
        if ($now > $data['reset']) {
            $data['count'] = 1;
            $data['reset'] = $now + $limit['window'];
            return ['allowed' => true, 'remaining' => $limit['requests'] - 1, 'reset' => $now + $limit['window']];
        }

        if ($data['count'] >= $limit['requests']) {
            return ['allowed' => false, 'remaining' => 0, 'reset' => $data['reset'], 'retry_after' => $data['reset'] - $now];
        }

        $data['count']++;
        return [
            'allowed' => true,
            'remaining' => $limit['requests'] - $data['count'],
            'reset' => $data['reset']
        ];
    }

    /**
     * Get rate limit status for an identifier
     */
    public function getStatus($identifier, $action = 'api')
    {
        $limit = $this->defaultLimits[$action] ?? $this->defaultLimits['api'];
        $key = $this->getKey($identifier, $action);

        if ($this->redis) {
            $current = intval($this->redis->get($key) ?: 0);
            $ttl = $this->redis->ttl($key);
            $reset = $ttl > 0 ? time() + $ttl : time() + $limit['window'];
        } else {
            // For in-memory, we can't get accurate status
            return ['current' => 0, 'limit' => $limit['requests'], 'remaining' => $limit['requests'], 'reset' => time() + $limit['window']];
        }

        return [
            'current' => $current,
            'limit' => $limit['requests'],
            'remaining' => max(0, $limit['requests'] - $current),
            'reset' => $reset
        ];
    }

    /**
     * Reset rate limit for an identifier
     */
    public function resetLimit($identifier, $action = 'api')
    {
        $key = $this->getKey($identifier, $action);

        if ($this->redis) {
            $this->redis->del($key);
        } else {
            // For in-memory, we can't reset individual keys
            // This would require a more complex in-memory implementation
        }

        return true;
    }

    /**
     * Middleware function for protecting routes
     */
    public function protectRoute($action = 'api')
    {
        $identifier = $this->getClientIdentifier();

        $result = $this->checkLimit($identifier, $action);

        if (!$result['allowed']) {
            http_response_code(429);
            header('X-RateLimit-Limit', $this->defaultLimits[$action]['requests']);
            header('X-RateLimit-Remaining', $result['remaining']);
            header('X-RateLimit-Reset', $result['reset']);
            header('Retry-After', $result['retry_after']);

            echo json_encode([
                'error' => 'Rate limit exceeded',
                'message' => 'Too many requests. Please try again later.',
                'retry_after' => $result['retry_after'],
                'reset' => date('c', $result['reset'])
            ]);
            exit;
        }

        // Add rate limit headers to response
        header('X-RateLimit-Limit', $this->defaultLimits[$action]['requests']);
        header('X-RateLimit-Remaining', $result['remaining']);
        header('X-RateLimit-Reset', $result['reset']);
    }

    /**
     * Get client identifier (IP address or user ID)
     */
    private function getClientIdentifier()
    {
        // Use authenticated user ID if available
        if (isset($_SESSION['user_id'])) {
            return 'user:' . $_SESSION['user_id'];
        }

        // Fallback to IP address
        return 'ip:' . $this->getClientIP();
    }

    /**
     * Get client IP address
     */
    private function getClientIP()
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];

                // Handle comma-separated IPs (X-Forwarded-For)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }

                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return '127.0.0.1';
    }

    /**
     * Generate Redis key
     */
    private function getKey($identifier, $action)
    {
        return $this->prefix . $action . ':' . $identifier;
    }

    /**
     * Configure custom rate limits
     */
    public function setLimit($action, $requests, $window)
    {
        $this->defaultLimits[$action] = [
            'requests' => $requests,
            'window' => $window
        ];
    }

    /**
     * Get current configuration
     */
    public function getConfig()
    {
        return [
            'redis_available' => $this->redis !== null,
            'limits' => $this->defaultLimits,
            'prefix' => $this->prefix
        ];
    }

    /**
     * Clean up expired keys (maintenance function)
     */
    public function cleanup()
    {
        if (!$this->redis) {
            return false;
        }

        // This would require scanning all keys with the prefix
        // For production, consider using Redis key expiration instead
        return true;
    }
}

// Usage examples:
/*
// Basic rate limiting
$limiter = RateLimiter::getInstance();

// Check if request is allowed
$result = $limiter->checkLimit('user_123', 'api');
if (!$result['allowed']) {
    http_response_code(429);
    echo json_encode(['error' => 'Rate limit exceeded']);
    exit;
}

// Protect API endpoint
$limiter->protectRoute('api');

// Custom limits
$limiter->setLimit('uploads', 10, 3600); // 10 uploads per hour

// Get status
$status = $limiter->getStatus('user_123', 'api');

// Reset limit (admin function)
$limiter->resetLimit('user_123', 'api');
*/
?>
