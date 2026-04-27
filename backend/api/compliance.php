<?php
/**
 * Compliance Reporting API Endpoint
 *
 * Handles regulatory compliance reports, audit logs, and data export
 */

require_once '../config/database.php';
require_once '../src/Logger.php';
require_once '../src/Middleware.php';
require_once '../../integrations/compliance-reporting.php';

// Initialize components
$db = getDatabaseConnection();
$logger = new Logger($db);
$middleware = new Middleware($db, $logger);
$complianceReporting = new ComplianceReporting($db, $logger);

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Get request method and path
$method = $_SERVER['REQUEST_METHOD'];
$request = $_SERVER['REQUEST_URI'];

// Remove query string and decode
$path = parse_url($request, PHP_URL_PATH);
$path = str_replace('/backend/api/compliance', '', $path);
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
            handleGetRequest($resource, $id, $_GET, $complianceReporting);
            break;

        case 'POST':
            handlePostRequest($resource, $input, $complianceReporting, $middleware);
            break;

        case 'PUT':
            handlePutRequest($resource, $id, $input, $complianceReporting, $middleware);
            break;

        case 'DELETE':
            handleDeleteRequest($resource, $id, $complianceReporting, $middleware);
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    $logger->error('Compliance API error', ['error' => $e->getMessage()]);
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

/**
 * Handle GET requests
 */
function handleGetRequest($resource, $id, $queryParams, $reporting)
{
    switch ($resource) {
        case null:
            // Get available report types
            $reportTypes = getAvailableReportTypes();
            echo json_encode(['success' => true, 'report_types' => $reportTypes]);
            break;

        case 'audit':
            if ($id) {
                // Get specific audit log entry
                $auditLog = getAuditLogById($id);
                if ($auditLog) {
                    echo json_encode(['success' => true, 'data' => $auditLog]);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Audit log entry not found']);
                }
            } else {
                // Get audit logs with filters
                handleAuditLogQuery($queryParams, $reporting);
            }
            break;

        case 'reports':
            if ($id) {
                // Get specific compliance report
                $report = getComplianceReportById($id);
                if ($report) {
                    echo json_encode(['success' => true, 'data' => $report]);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Compliance report not found']);
                }
            } else {
                // Get list of compliance reports
                handleComplianceReportQuery($queryParams);
            }
            break;

        case 'generate':
            // Generate a compliance report
            if (isset($queryParams['type'])) {
                $period = isset($queryParams['period']) ? json_decode($queryParams['period'], true) : null;
                $report = $reporting->generateComplianceReport($queryParams['type'], $period);
                echo json_encode(['success' => true, 'report' => $report]);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Report type required']);
            }
            break;

        case 'export':
            // Export a report
            if (isset($queryParams['report_id']) && isset($queryParams['format'])) {
                $report = getComplianceReportById($queryParams['report_id']);
                if ($report) {
                    $exportResult = $reporting->exportReport($report['report_data'], $queryParams['format']);

                    if (isset($exportResult['error'])) {
                        http_response_code(400);
                        echo json_encode(['error' => $exportResult['error']]);
                    } else {
                        // Set appropriate headers for file download
                        header('Content-Type: ' . $exportResult['content_type']);
                        header('Content-Disposition: attachment; filename="' . $exportResult['filename'] . '"');
                        header('Content-Length: ' . $exportResult['size']);

                        echo $exportResult['data'];
                        exit;
                    }
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Report not found']);
                }
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Report ID and format required']);
            }
            break;

        case 'retention':
            // Get data retention policies
            $policies = getRetentionPolicies();
            echo json_encode(['success' => true, 'data' => $policies]);
            break;

        case 'deletion-logs':
            // Get data deletion logs
            handleDeletionLogQuery($queryParams);
            break;

        default:
            http_response_code(404);
            echo json_encode(['error' => 'Resource not found']);
    }
}

/**
 * Handle POST requests
 */
function handlePostRequest($resource, $input, $reporting, $middleware)
{
    // Require authentication for POST operations
    $user = $middleware->authenticate();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        return;
    }

    switch ($resource) {
        case 'audit':
            // Log audit event
            $result = $reporting->logAuditEvent(
                $user['id'],
                $input['action'] ?? 'unknown',
                $input['resource_type'] ?? 'unknown',
                $input['resource_id'] ?? null,
                $input['details'] ?? null,
                $input['ip_address'] ?? null
            );

            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Audit event logged']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to log audit event']);
            }
            break;

        case 'reports':
            // Save compliance report
            $reportId = saveComplianceReport($input, $user['id']);
            echo json_encode(['success' => true, 'report_id' => $reportId]);
            break;

        case 'generate':
            // Generate and save compliance report
            if (isset($input['type'])) {
                $period = $input['period'] ?? null;
                $report = $reporting->generateComplianceReport($input['type'], $period);

                if (!isset($report['error'])) {
                    // Save the generated report
                    $reportData = [
                        'type' => $input['type'],
                        'period' => $period,
                        'data' => $report,
                        'generated_by' => $user['id']
                    ];

                    $reportId = saveComplianceReport($reportData, $user['id']);
                    echo json_encode(['success' => true, 'report' => $report, 'saved_id' => $reportId]);
                } else {
                    http_response_code(400);
                    echo json_encode(['error' => $report['error']]);
                }
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Report type required']);
            }
            break;

        case 'export':
            // Generate and export report
            if (isset($input['type']) && isset($input['format'])) {
                $period = $input['period'] ?? null;
                $report = $reporting->generateComplianceReport($input['type'], $period);

                if (!isset($report['error'])) {
                    $exportResult = $reporting->exportReport($report, $input['format']);

                    if (isset($exportResult['error'])) {
                        http_response_code(400);
                        echo json_encode(['error' => $exportResult['error']]);
                    } else {
                        // Set appropriate headers for file download
                        header('Content-Type: ' . $exportResult['content_type']);
                        header('Content-Disposition: attachment; filename="' . $exportResult['filename'] . '"');
                        header('Content-Length: ' . $exportResult['size']);

                        echo $exportResult['data'];
                        exit;
                    }
                } else {
                    http_response_code(400);
                    echo json_encode(['error' => $report['error']]);
                }
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Report type and format required']);
            }
            break;

        default:
            http_response_code(404);
            echo json_encode(['error' => 'Resource not found']);
    }
}

/**
 * Handle PUT requests
 */
function handlePutRequest($resource, $id, $input, $reporting, $middleware)
{
    // Require authentication
    $user = $middleware->authenticate();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        return;
    }

    switch ($resource) {
        case 'reports':
            if ($id) {
                // Update compliance report
                $result = updateComplianceReport($id, $input);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Report ID required']);
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
function handleDeleteRequest($resource, $id, $reporting, $middleware)
{
    // Require authentication
    $user = $middleware->authenticate();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        return;
    }

    switch ($resource) {
        case 'reports':
            if ($id) {
                $result = deleteComplianceReport($id);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Report ID required']);
            }
            break;

        default:
            http_response_code(404);
            echo json_encode(['error' => 'Resource not found']);
    }
}

/**
 * Get available report types
 */
function getAvailableReportTypes()
{
    return [
        'audit_log' => [
            'name' => 'Audit Log Report',
            'description' => 'Comprehensive audit trail of system activities',
            'parameters' => ['start_date', 'end_date', 'filters']
        ],
        'faa_part_139' => [
            'name' => 'FAA Part 139 Airport Certification',
            'description' => 'Airport certification and safety compliance report',
            'parameters' => ['period']
        ],
        'icao_annex_14' => [
            'name' => 'ICAO Annex 14 Aerodrome Standards',
            'description' => 'International aerodrome standards compliance',
            'parameters' => ['period']
        ],
        'gdpr_compliance' => [
            'name' => 'GDPR Compliance Report',
            'description' => 'Data protection and privacy compliance assessment',
            'parameters' => ['period']
        ],
        'security_incidents' => [
            'name' => 'Security Incidents Report',
            'description' => 'Security incidents and response actions',
            'parameters' => ['period']
        ],
        'data_retention' => [
            'name' => 'Data Retention Report',
            'description' => 'Data retention and deletion compliance',
            'parameters' => ['period']
        ]
    ];
}

/**
 * Handle audit log queries
 */
function handleAuditLogQuery($queryParams, $reporting)
{
    $startDate = $queryParams['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
    $endDate = $queryParams['end_date'] ?? date('Y-m-d');
    $filters = [];

    if (isset($queryParams['user_id'])) {
        $filters['user_id'] = $queryParams['user_id'];
    }
    if (isset($queryParams['action'])) {
        $filters['action'] = $queryParams['action'];
    }
    if (isset($queryParams['resource_type'])) {
        $filters['resource_type'] = $queryParams['resource_type'];
    }

    $report = $reporting->generateAuditReport($startDate, $endDate, $filters);
    echo json_encode(['success' => true, 'data' => $report]);
}

/**
 * Handle compliance report queries
 */
function handleComplianceReportQuery($queryParams)
{
    $db = $GLOBALS['db'];
    $page = isset($queryParams['page']) ? (int)$queryParams['page'] : 1;
    $limit = isset($queryParams['limit']) ? (int)$queryParams['limit'] : 50;
    $offset = ($page - 1) * $limit;

    $query = "SELECT * FROM compliance_reports WHERE 1=1";
    $params = [];

    if (isset($queryParams['type'])) {
        $query .= " AND report_type = ?";
        $params[] = $queryParams['type'];
    }

    if (isset($queryParams['start_date'])) {
        $query .= " AND period_start >= ?";
        $params[] = $queryParams['start_date'];
    }

    if (isset($queryParams['end_date'])) {
        $query .= " AND period_end <= ?";
        $params[] = $queryParams['end_date'];
    }

    $query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;

    $stmt = $db->prepare($query);
    $stmt->execute($params);

    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Decode JSON data
    foreach ($reports as &$report) {
        $report['report_data'] = json_decode($report['report_data'], true);
    }

    echo json_encode(['success' => true, 'data' => $reports, 'page' => $page, 'limit' => $limit]);
}

/**
 * Handle deletion log queries
 */
function handleDeletionLogQuery($queryParams)
{
    $db = $GLOBALS['db'];
    $page = isset($queryParams['page']) ? (int)$queryParams['page'] : 1;
    $limit = isset($queryParams['limit']) ? (int)$queryParams['limit'] : 50;
    $offset = ($page - 1) * $limit;

    $query = "SELECT * FROM data_deletion_logs WHERE 1=1";
    $params = [];

    if (isset($queryParams['data_type'])) {
        $query .= " AND data_type = ?";
        $params[] = $queryParams['data_type'];
    }

    if (isset($queryParams['start_date'])) {
        $query .= " AND executed_at >= ?";
        $params[] = $queryParams['start_date'];
    }

    if (isset($queryParams['end_date'])) {
        $query .= " AND executed_at <= ?";
        $params[] = $queryParams['end_date'];
    }

    $query .= " ORDER BY executed_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;

    $stmt = $db->prepare($query);
    $stmt->execute($params);

    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $logs, 'page' => $page, 'limit' => $limit]);
}

/**
 * Get audit log by ID
 */
function getAuditLogById($id)
{
    $db = $GLOBALS['db'];
    $stmt = $db->prepare("SELECT * FROM audit_logs WHERE id = ?");
    $stmt->execute([$id]);
    $log = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($log) {
        $log['details'] = json_decode($log['details'], true);
    }

    return $log;
}

/**
 * Get compliance report by ID
 */
function getComplianceReportById($id)
{
    $db = $GLOBALS['db'];
    $stmt = $db->prepare("SELECT * FROM compliance_reports WHERE id = ?");
    $stmt->execute([$id]);
    $report = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($report) {
        $report['report_data'] = json_decode($report['report_data'], true);
    }

    return $report;
}

/**
 * Save compliance report
 */
function saveComplianceReport($reportData, $userId)
{
    $db = $GLOBALS['db'];
    $stmt = $db->prepare("
        INSERT INTO compliance_reports (
            report_type, report_data, generated_by,
            period_start, period_end, status
        ) VALUES (?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $reportData['type'],
        json_encode($reportData['data']),
        $userId,
        $reportData['period']['start'] ?? null,
        $reportData['period']['end'] ?? null,
        'saved'
    ]);

    return $db->lastInsertId();
}

/**
 * Update compliance report
 */
function updateComplianceReport($id, $updateData)
{
    $db = $GLOBALS['db'];

    try {
        $stmt = $db->prepare("
            UPDATE compliance_reports
            SET report_data = ?, status = ?
            WHERE id = ?
        ");

        $stmt->execute([
            json_encode($updateData['report_data'] ?? []),
            $updateData['status'] ?? 'updated',
            $id
        ]);

        return ['success' => true, 'message' => 'Report updated successfully'];

    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Delete compliance report
 */
function deleteComplianceReport($id)
{
    $db = $GLOBALS['db'];

    try {
        $stmt = $db->prepare("DELETE FROM compliance_reports WHERE id = ?");
        $stmt->execute([$id]);

        return ['success' => true, 'message' => 'Report deleted successfully'];

    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Get retention policies
 */
function getRetentionPolicies()
{
    $db = $GLOBALS['db'];
    $stmt = $db->query("SELECT * FROM data_retention_policies ORDER BY data_type");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
