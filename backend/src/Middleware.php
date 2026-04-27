<?php
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/RBAC.php';

use TPT\FlightControl\Logger;
use TPT\FlightControl\RBAC;

class Middleware {
    public static function authenticate() {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

        if (empty($authHeader) || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            Logger::warning('Missing or invalid authorization header');
            http_response_code(401);
            echo json_encode(['error' => 'Authorization required']);
            exit;
        }

        $token = $matches[1];
        $user = Auth::getUserFromToken($token);

        if (!$user) {
            Logger::warning('Invalid or expired token');
            http_response_code(401);
            echo json_encode(['error' => 'Invalid or expired token']);
            exit;
        }

        // Store user in global for use in endpoints
        $GLOBALS['current_user'] = $user;
        Logger::debug('User authenticated: ' . $user['username']);
        return $user;
    }

    public static function checkPermission($permission, $module = null) {
        $user = $GLOBALS['current_user'] ?? null;
        if (!$user) {
            Logger::error('Permission check called without authentication');
            http_response_code(401);
            echo json_encode(['error' => 'Authentication required']);
            exit;
        }

        if (!RBAC::checkAccess($user['user_id'], $permission, $module)) {
            Logger::warning('Access denied for user: ' . $user['username'] . ' permission: ' . $permission . ($module ? ' module: ' . $module : ''));
            http_response_code(403);
            echo json_encode(['error' => 'Insufficient permissions']);
            exit;
        }

        Logger::debug('Permission granted: ' . $permission . ' for user: ' . $user['username']);
    }

    public static function checkRole($requiredRole) {
        $user = $GLOBALS['current_user'] ?? null;
        if (!$user) {
            Logger::error('Role check called without authentication');
            http_response_code(401);
            echo json_encode(['error' => 'Authentication required']);
            exit;
        }

        if (!RBAC::hasRole($user['user_id'], $requiredRole)) {
            Logger::warning('Role check failed for user: ' . $user['username'] . ' required role: ' . $requiredRole);
            http_response_code(403);
            echo json_encode(['error' => 'Insufficient role permissions']);
            exit;
        }

        Logger::debug('Role check passed: ' . $requiredRole . ' for user: ' . $user['username']);
    }

    public static function validateInput($data, $requiredFields) {
        $missing = [];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty(trim($data[$field]))) {
                $missing[] = $field;
            }
        }

        if (!empty($missing)) {
            Logger::warning('Missing required fields: ' . implode(', ', $missing));
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields: ' . implode(', ', $missing)]);
            exit;
        }
    }

    public static function sanitizeInput($data) {
        $sanitized = [];
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $sanitized[$key] = trim(strip_tags($value));
            } else {
                $sanitized[$key] = $value;
            }
        }
        return $sanitized;
    }

    public static function rateLimit($identifier, $maxRequests = 100, $timeWindow = 3600) {
        require_once __DIR__ . '/../services/RateLimiter.php';
        require_once __DIR__ . '/Logger.php';
        
        $rateLimiter = new RateLimiter();
        $userId = isset($GLOBALS['current_user']) ? $GLOBALS['current_user']['user_id'] : null;
        
        if (!$rateLimiter->checkLimit($identifier, $maxRequests, $timeWindow, $userId)) {
            $resetTime = $rateLimiter->getResetTime($identifier, $timeWindow);
            
            header('X-RateLimit-Limit: ' . $maxRequests);
            header('X-RateLimit-Remaining: 0');
            header('X-RateLimit-Reset: ' . $resetTime);
            header('Retry-After: ' . max(1, $resetTime - time()));
            
            Logger::warning('Rate limit exceeded for: ' . $identifier);
            http_response_code(429);
            echo json_encode([
                'error' => 'Too many requests',
                'retry_after' => max(1, $resetTime - time()),
                'reset_time' => $resetTime
            ]);
            exit;
        }
        
        $remaining = $rateLimiter->getRemaining($identifier, $maxRequests, $timeWindow);
        header('X-RateLimit-Limit: ' . $maxRequests);
        header('X-RateLimit-Remaining: ' . $remaining);
        header('X-RateLimit-Reset: ' . $rateLimiter->getResetTime($identifier, $timeWindow));
    }
}
?>
