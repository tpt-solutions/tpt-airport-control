<?php
require_once __DIR__ . '/cors.php';
/**
 * Data Retention Policies API Endpoint
 *
 * Automated data lifecycle management system
 */

require_once '../config/database.php';
require_once '../src/Logger.php';
require_once '../src/Middleware.php';
require_once '../../integrations/data-retention-policies.php';
require_once '../../integrations/gdpr-compliance.php';

// Initialize components
$db = getDatabaseConnection();
$logger = new Logger($db);
$middleware = new Middleware($db, $logger);
$gdprCompliance = new GDPRCompliance($db, $logger);
$dataRetention = new DataRetentionPolicies($db, $logger, $gdprCompliance);
$lifecycleManager = new DataLifecycleManager($db, $logger, $dataRetention);
$storageManager = new StorageOptimizationManager($db, $logger);

// Top-level authentication gate — data-retention endpoints are admin-only.
Middleware::authenticate();

// Get request method and path
$method = $_SERVER['REQUEST_METHOD'];
$request = $_SERVER['REQUEST_URI'];

// Remove query string and decode
$path = parse_url($request, PHP_URL_PATH);
$path = str_replace('/backend/api/data-retention', '', $path);
$pathParts = array_filter(explode('/', $path));

// Get path parameters
$resource = $pathParts[1] ?? null;
$id = $pathParts[2] ?? null;

// Get request body
$input = json_decode(file_get_contents('php://input'), true);

// Route requests
try {
    switch ($method) {
        case 'GET':
            handleGetRequest($resource, $id, $_GET, $dataRetention, $lifecycleManager, $storageManager);
            break;

        case 'POST':
            handlePostRequest($resource, $input, $dataRetention, $lifecycleManager, $storageManager, $middleware);
            break;

        case 'PUT':
            handlePutRequest($resource, $id, $input, $dataRetention, $middleware);
            break;

        case 'DELETE':
            handleDeleteRequest($resource, $id, $dataRetention, $middleware);
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    $logger->error('Data Retention API error', ['error' => $e->getMessage()]);
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

/**
 * Handle GET requests
 */
function handleGetRequest($resource, $id, $queryParams, $dataRetention, $lifecycleManager, $storageManager)
{
    switch ($resource) {
        case null:
            // Get data retention system status
            $status = $dataRetention->getStatus();
            echo json_encode(['success' => true, 'status' => $status]);
            break;

        case 'policies':
            // Get retention policies
            $policies = getRetentionPolicies($queryParams);
            echo json_encode(['success' => true, 'policies' => $policies]);
            break;

        case 'statistics':
            // Get retention statistics
            $stats = $dataRetention->getRetentionStatistics($queryParams['period'] ?? '30 days');
            echo json_encode($stats);
            break;

        case 'lifecycle':
            if ($id) {
                // Get lifecycle history for specific subject
                $history = $lifecycleManager->getLifecycleHistory($id, $queryParams['category'] ?? null);
                echo json_encode($history);
            } else {
                // Get lifecycle events
                $events = getLifecycleEvents($queryParams);
                echo json_encode(['success' => true, 'events' => $events]);
            }
            break;

        case 'storage':
            // Get storage analysis
            $analysis = $storageManager->analyzeStorageUsage();
            echo json_encode($analysis);
            break;

        case 'exceptions':
            // Get retention exceptions
            $exceptions = getRetentionExceptions($queryParams);
            echo json_encode(['success' => true, 'exceptions' => $exceptions]);
            break;

        case 'archival-logs':
            // Get archival logs
            $logs = getArchivalLogs($queryParams);
            echo json_encode(['success' => true, 'logs' => $logs]);
            break;

        case 'disposal-logs':
            // Get disposal logs
            $logs = getDisposalLogs($queryParams);
            echo json_encode(['success' => true, 'logs' => $logs]);
            break;

        case 'executions':
            // Get policy execution history
            $executions = getPolicyExecutions($queryParams);
            echo json_encode(['success' => true, 'executions' => $executions]);
            break;

        default:
            http_response_code(404);
            echo json_encode(['error' => 'Resource not found']);
    }
}

/**
 * Handle POST requests
 */
function handlePostRequest($resource, $input, $dataRetention, $lifecycleManager, $storageManager, $middleware)
{
    switch ($resource) {
        case 'initialize':
            // Initialize data retention system
            $result = $dataRetention->initialize();
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Data retention system initialized']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to initialize data retention system']);
            }
            break;

        case 'execute-policies':
            // Execute retention policies
            $result = $dataRetention->executeRetentionPolicies(
                $input['data_category'] ?? null,
                $input['dry_run'] ?? true
            );
            echo json_encode($result);
            break;

        case 'archive':
            // Archive data
            if (isset($input['data_category'])) {
                $result = $dataRetention->archiveData(
                    $input['data_category'],
                    $input['method'] ?? 'compression'
                );
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Data category required']);
            }
            break;

        case 'delete':
            // Delete expired data
            if (isset($input['data_category'])) {
                $result = $dataRetention->deleteExpiredData(
                    $input['data_category'],
                    $input['method'] ?? 'secure_deletion'
                );
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Data category required']);
            }
            break;

        case 'exception':
            // Create retention exception
            if (isset($input['data_subject_id']) && isset($input['data_category']) &&
                isset($input['exception_type']) && isset($input['exception_reason']) &&
                isset($input['duration'])) {
                $result = $dataRetention->createRetentionException(
                    $input['data_subject_id'],
                    $input['data_category'],
                    $input['exception_type'],
                    $input['exception_reason'],
                    $input['duration']
                );
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Required fields: data_subject_id, data_category, exception_type, exception_reason, duration']);
            }
            break;

        case 'lifecycle-event':
            // Track lifecycle event
            if (isset($input['data_subject_id']) && isset($input['data_category']) && isset($input['event_type'])) {
                $result = $lifecycleManager->trackLifecycleEvent(
                    $input['data_subject_id'],
                    $input['data_category'],
                    $input['event_type'],
                    $input['event_details'] ?? []
                );
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Required fields: data_subject_id, data_category, event_type']);
            }
            break;

        case 'optimize-storage':
            // Optimize storage
            $result = $dataRetention->optimizeStorage($input['data_category'] ?? null);
            echo json_encode($result);
            break;

        default:
            http_response_code(404);
            echo json_encode(['error' => 'Resource not found']);
    }
}

/**
 * Handle PUT requests
 */
function handlePutRequest($resource, $id, $input, $dataRetention, $middleware)
{
    // Require authentication for PUT operations
    $user = $middleware->authenticate();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        return;
    }

    switch ($resource) {
        case 'exception':
            if ($id) {
                $result = updateRetentionException($id, $input);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Exception ID required']);
            }
            break;

        case 'policy':
            if ($id) {
                $result = updateRetentionPolicy($id, $input);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Policy ID required']);
            }
            break;

        default:
            http_response_code(404);
            echo json_encode(['error' => 'Resource not found']);
    }
}

/**
 * Handle DELETE requests
 */
function handleDeleteRequest($resource, $id, $dataRetention, $middleware)
{
    // Require authentication
    $user = $middleware->authenticate();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        return;
    }

    switch ($resource) {
        case 'exception':
            if ($id) {
                $result = deleteRetentionException($id);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Exception ID required']);
            }
            break;

        default:
            http_response_code(404);
            echo json_encode(['error' => 'Resource not found']);
    }
}

/**
 * Get retention policies
 */
function getRetentionPolicies($queryParams)
{
    $db = $GLOBALS['db'];
    $query = "SELECT * FROM data_retention_schedules WHERE 1=1";
    $params = [];

    if (isset($queryParams['data_category'])) {
        $query .= " AND data_category = ?";
        $params[] = $queryParams['data_category'];
    }

    if (isset($queryParams['active'])) {
        $active = $queryParams['active'] === 'true' ? 'TRUE' : 'FALSE';
        $query .= " AND is_active = ?";
        $params[] = $active;
    }

    $query .= " ORDER BY data_category, created_at DESC";

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get lifecycle events
 */
function getLifecycleEvents($queryParams)
{
    $db = $GLOBALS['db'];
    $query = "SELECT * FROM data_lifecycle_events WHERE 1=1";
    $params = [];

    if (isset($queryParams['data_subject_id'])) {
        $query .= " AND data_subject_id = ?";
        $params[] = $queryParams['data_subject_id'];
    }

    if (isset($queryParams['data_category'])) {
        $query .= " AND data_category = ?";
        $params[] = $queryParams['data_category'];
    }

    if (isset($queryParams['event_type'])) {
        $query .= " AND event_type = ?";
        $params[] = $queryParams['event_type'];
    }

    if (isset($queryParams['start_date'])) {
        $query .= " AND event_timestamp >= ?";
        $params[] = $queryParams['start_date'];
    }

    if (isset($queryParams['end_date'])) {
        $query .= " AND event_timestamp <= ?";
        $params[] = $queryParams['end_date'];
    }

    $query .= " ORDER BY event_timestamp DESC LIMIT 500";

    $stmt = $db->prepare($query);
    $stmt->execute($params);

    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Decode JSON details
    foreach ($events as &$event) {
        $event['event_details'] = json_decode($event['event_details'], true);
    }

    return $events;
}

/**
 * Get retention exceptions
 */
function getRetentionExceptions($queryParams)
{
    $db = $GLOBALS['db'];
    $query = "SELECT * FROM data_retention_exceptions WHERE 1=1";
    $params = [];

    if (isset($queryParams['data_subject_id'])) {
        $query .= " AND data_subject_id = ?";
        $params[] = $queryParams['data_subject_id'];
    }

    if (isset($queryParams['data_category'])) {
        $query .= " AND data_category = ?";
        $params[] = $queryParams['data_category'];
    }

    if (isset($queryParams['status'])) {
        $query .= " AND status = ?";
        $params[] = $queryParams['status'];
    }

    $query .= " ORDER BY created_at DESC LIMIT 200";

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get archival logs
 */
function getArchivalLogs($queryParams)
{
    $db = $GLOBALS['db'];
    $query = "SELECT * FROM data_archival_logs WHERE 1=1";
    $params = [];

    if (isset($queryParams['data_category'])) {
        $query .= " AND data_category = ?";
        $params[] = $queryParams['data_category'];
    }

    if (isset($queryParams['start_date'])) {
        $query .= " AND archival_date >= ?";
        $params[] = $queryParams['start_date'];
    }

    if (isset($queryParams['end_date'])) {
        $query .= " AND archival_date <= ?";
        $params[] = $queryParams['end_date'];
    }

    $query .= " ORDER BY archival_date DESC LIMIT 200";

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get disposal logs
 */
function getDisposalLogs($queryParams)
{
    $db = $GLOBALS['db'];
    $query = "SELECT * FROM data_disposal_logs WHERE 1=1";
    $params = [];

    if (isset($queryParams['data_category'])) {
        $query .= " AND data_category = ?";
        $params[] = $queryParams['data_category'];
    }

    if (isset($queryParams['start_date'])) {
        $query .= " AND disposal_date >= ?";
        $params[] = $queryParams['start_date'];
    }

    if (isset($queryParams['end_date'])) {
        $query .= " AND disposal_date <= ?";
        $params[] = $queryParams['end_date'];
    }

    $query .= " ORDER BY disposal_date DESC LIMIT 200";

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get policy executions
 */
function getPolicyExecutions($queryParams)
{
    $db = $GLOBALS['db'];
    $query = "SELECT * FROM retention_policy_executions WHERE 1=1";
    $params = [];

    if (isset($queryParams['policy_id'])) {
        $query .= " AND policy_id = ?";
        $params[] = $queryParams['policy_id'];
    }

    if (isset($queryParams['status'])) {
        $query .= " AND execution_status = ?";
        $params[] = $queryParams['status'];
    }

    if (isset($queryParams['start_date'])) {
        $query .= " AND started_at >= ?";
        $params[] = $queryParams['start_date'];
    }

    if (isset($queryParams['end_date'])) {
        $query .= " AND started_at <= ?";
        $params[] = $queryParams['end_date'];
    }

    $query .= " ORDER BY started_at DESC LIMIT 100";

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Update retention exception
 */
function updateRetentionException($exceptionId, $updateData)
{
    $db = $GLOBALS['db'];

    try {
        $stmt = $db->prepare("
            UPDATE data_retention_exceptions
            SET status = ?, approved_by = ?, approval_date = NOW()
            WHERE exception_id = ?
        ");

        $stmt->execute([
            $updateData['status'] ?? 'approved',
            $updateData['approved_by'] ?? 'system',
            $exceptionId
        ]);

        return ['success' => true, 'message' => 'Retention exception updated successfully'];

    } catch (Exception $e) {
        return ['success' => false, 'error' => 'An internal error occurred'];
    }
}

/**
 * Update retention policy
 */
function updateRetentionPolicy($policyId, $updateData)
{
    $db = $GLOBALS['db'];

    try {
        $stmt = $db->prepare("
            UPDATE data_retention_schedules
            SET retention_period = ?, disposal_method = ?, review_frequency = ?
            WHERE schedule_id = ?
        ");

        $stmt->execute([
            $updateData['retention_period'] ?? null,
            $updateData['disposal_method'] ?? null,
            $updateData['review_frequency'] ?? null,
            $policyId
        ]);

        return ['success' => true, 'message' => 'Retention policy updated successfully'];

    } catch (Exception $e) {
        return ['success' => false, 'error' => 'An internal error occurred'];
    }
}

/**
 * Delete retention exception
 */
function deleteRetentionException($exceptionId)
{
    $db = $GLOBALS['db'];

    try {
        $stmt = $db->prepare("DELETE FROM data_retention_exceptions WHERE exception_id = ?");
        $stmt->execute([$exceptionId]);

        return ['success' => true, 'message' => 'Retention exception deleted successfully'];

    } catch (Exception $e) {
        return ['success' => false, 'error' => 'An internal error occurred'];
    }
}
