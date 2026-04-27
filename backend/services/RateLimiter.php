<?php
/**
 * TPT Flight Control System
 * Rate Limiter Service
 * 
 * Manages rate limit tracking, quotas and usage statistics
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/Database.php';

use TPT\FlightControl\Config\Database;

class RateLimiter {
    private $pdo;
    
    /**
     * Rate limit definitions per plan
     */
    private const PLAN_LIMITS = [
        'free' => [
            'requests_per_minute' => 60,
            'requests_per_hour' => 1000,
            'requests_per_day' => 10000
        ],
        'basic' => [
            'requests_per_minute' => 300,
            'requests_per_hour' => 5000,
            'requests_per_day' => 50000
        ],
        'professional' => [
            'requests_per_minute' => 1000,
            'requests_per_hour' => 15000,
            'requests_per_day' => 150000
        ],
        'enterprise' => [
            'requests_per_minute' => 5000,
            'requests_per_hour' => 50000,
            'requests_per_day' => 500000
        ]
    ];
    
    public function __construct() {
        $this->pdo = Database::getConnection();
    }
    
    /**
     * Get configured rate limits for a user based on their plan
     */
    public function getUserLimits(int $userId): array {
        $plan = $this->getUserPlan($userId);
        return self::PLAN_LIMITS[$plan] ?? self::PLAN_LIMITS['free'];
    }
    
    /**
     * Get current rate limit usage statistics for a user
     */
    public function getCurrentUsage(int $userId): array {
        $now = time();
        $minuteStart = $now - ($now % 60);
        $hourStart = $now - ($now % 3600);
        $dayStart = strtotime('today midnight');
        
        $stmt = $this->pdo->prepare("
            SELECT
                COUNT(CASE WHEN timestamp >= ? THEN 1 END) as minute_count,
                COUNT(CASE WHEN timestamp >= ? THEN 1 END) as hour_count,
                COUNT(CASE WHEN timestamp >= ? THEN 1 END) as day_count
            FROM request_logs
            WHERE user_id = ?
        ");
        
        $stmt->execute([$minuteStart, $hourStart, $dayStart, $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'minute' => (int)($result['minute_count'] ?? 0),
            'hour' => (int)($result['hour_count'] ?? 0),
            'day' => (int)($result['day_count'] ?? 0)
        ];
    }
    
    /**
     * Get request history for a user within the specified time window
     */
    public function getRequestHistory(int $userId, int $timeWindowSeconds = 86400): array {
        $since = time() - $timeWindowSeconds;
        
        $stmt = $this->pdo->prepare("
            SELECT 
                timestamp,
                endpoint,
                method,
                response_code,
                execution_time_ms
            FROM request_logs
            WHERE user_id = ? AND timestamp >= ?
            ORDER BY timestamp DESC
            LIMIT 1000
        ");
        
        $stmt->execute([$userId, $since]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Record a request for rate limit tracking
     */
    public function recordRequest(int $userId, string $endpoint, string $method, int $responseCode, float $executionTime): void {
        $stmt = $this->pdo->prepare("
            INSERT INTO request_logs (user_id, endpoint, method, response_code, execution_time_ms, timestamp)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $userId,
            $endpoint,
            $method,
            $responseCode,
            $executionTime,
            time()
        ]);
    }
    
    /**
     * Check if user has exceeded rate limits
     */
    public function isRateLimited(int $userId): bool {
        $limits = $this->getUserLimits($userId);
        $usage = $this->getCurrentUsage($userId);
        
        return 
            $usage['minute'] >= $limits['requests_per_minute'] ||
            $usage['hour'] >= $limits['requests_per_hour'] ||
            $usage['day'] >= $limits['requests_per_day'];
    }
    
    /**
     * Get user's current subscription plan
     */
    private function getUserPlan(int $userId): string {
        $stmt = $this->pdo->prepare("
            SELECT plan FROM users WHERE id = ?
        ");
        
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['plan'] ?? 'free';
    }
    
    /**
     * Check if identifier has exceeded rate limit
     */
    public function checkLimit(string $identifier, int $maxRequests, int $timeWindow, ?int $userId = null): bool {
        $windowStart = time() - $timeWindow;
        
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count 
            FROM request_logs 
            WHERE identifier = ? AND timestamp >= ?
        ");
        
        $stmt->execute([$identifier, $windowStart]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $count = (int)($result['count'] ?? 0);
        
        // Record this request
        $stmt = $this->pdo->prepare("
            INSERT INTO request_logs (identifier, user_id, timestamp)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$identifier, $userId, time()]);
        
        return $count < $maxRequests;
    }
    
    /**
     * Get time when rate limit will reset
     */
    public function getResetTime(string $identifier, int $timeWindow): int {
        $stmt = $this->pdo->prepare("
            SELECT MIN(timestamp) as first_request
            FROM request_logs 
            WHERE identifier = ? AND timestamp >= ?
        ");
        
        $windowStart = time() - $timeWindow;
        $stmt->execute([$identifier, $windowStart]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['first_request']) {
            return (int)$result['first_request'] + $timeWindow;
        }
        
        return time();
    }
    
    /**
     * Get remaining requests in window
     */
    public function getRemaining(string $identifier, int $maxRequests, int $timeWindow): int {
        $windowStart = time() - $timeWindow;
        
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count 
            FROM request_logs 
            WHERE identifier = ? AND timestamp >= ?
        ");
        
        $stmt->execute([$identifier, $windowStart]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $count = (int)($result['count'] ?? 0);
        
        return max(0, $maxRequests - $count);
    }
}
