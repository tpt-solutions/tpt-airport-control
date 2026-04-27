<?php
/**
 * Security Audit API Endpoint
 *
 * Provides REST API access to security audit functionality
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/SecurityAudit.php';
require_once __DIR__ . '/../src/Logger.php';
require_once __DIR__ . '/../src/Middleware.php';

// Set content type to JSON
header('Content-Type: application/json');

// Handle CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? 'status';

    switch ($method) {
        case 'GET':
            handleGet($action);
            break;
        case 'POST':
            handlePost($action);
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    Logger::error('Security audit API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

/**
 * Handle GET requests
 */
function handleGet($action)
{
    // Require admin authentication
    Middleware::authenticate();
    Middleware::checkPermission('admin', 'security');

    $audit = SecurityAudit::getInstance();

    switch ($action) {
        case 'status':
            // Get current security status
            $results = $audit->runFullAudit();
            echo json_encode([
                'status' => 'success',
                'data' => [
                    'total_violations' => $results['total_violations'],
                    'critical_issues' => $results['critical_issues'],
                    'last_audit' => date('Y-m-d H:i:s', $results['timestamp']),
                    'status' => $results['critical_issues'] > 0 ? 'CRITICAL' : 'SECURE'
                ]
            ]);
            break;

        case 'report':
            // Generate detailed security report
            $report = $audit->generateSecurityReport();
            echo json_encode([
                'status' => 'success',
                'data' => $report
            ]);
            break;

        case 'violations':
            // Get current violations
            $results = $audit->runFullAudit();
            echo json_encode([
                'status' => 'success',
                'data' => [
                    'violations' => $results['violations'],
                    'total' => $results['total_violations']
                ]
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
}

/**
 * Handle POST requests
 */
function handlePost($action)
{
    // Require admin authentication
    Middleware::authenticate();
    Middleware::checkPermission('admin', 'security');

    $audit = SecurityAudit::getInstance();

    switch ($action) {
        case 'run_audit':
            // Run full security audit
            $results = $audit->runFullAudit();

            Logger::info('Manual security audit executed', [
                'user_id' => $_SESSION['user_id'] ?? 'unknown',
                'violations_found' => $results['total_violations'],
                'critical_issues' => $results['critical_issues']
            ]);

            echo json_encode([
                'status' => 'success',
                'message' => 'Security audit completed',
                'data' => $results
            ]);
            break;

        case 'apply_headers':
            // Apply security headers to current response
            $audit->applySecurityHeaders();

            echo json_encode([
                'status' => 'success',
                'message' => 'Security headers applied to response'
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
}
?>
