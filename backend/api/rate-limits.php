<?php
/**
 * TPT Flight Control System
 * Rate Limit Status API Endpoint
 * 
 * Provides user access to current rate limit usage, quotas and request history
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../services/RateLimiter.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache');

if (!Auth::isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

$userId = Auth::currentUserId();
$rateLimiter = new RateLimiter();

// Get current limits for authenticated user
$limits = $rateLimiter->getUserLimits($userId);

// Get usage statistics
$usage = $rateLimiter->getCurrentUsage($userId);

// Get request history for last 24 hours
$history = $rateLimiter->getRequestHistory($userId, 86400);

echo json_encode([
    'user_id' => $userId,
    'current_plan' => Auth::currentUserPlan(),
    'limits' => $limits,
    'usage' => $usage,
    'remaining' => [
        'requests_per_minute' => $limits['requests_per_minute'] - $usage['minute'],
        'requests_per_hour' => $limits['requests_per_hour'] - $usage['hour'],
        'requests_per_day' => $limits['requests_per_day'] - $usage['day']
    ],
    'reset_times' => [
        'minute' => time() + 60 - (time() % 60),
        'hour' => time() + 3600 - (time() % 3600),
        'day' => strtotime('tomorrow midnight')
    ],
    'history' => $history,
    'rate_limit_increase_allowed' => Auth::currentUserPlan() !== 'free',
    'support_ticket_link' => '/support/rate-limit-request'
]);