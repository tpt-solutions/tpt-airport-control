<?php
/**
 * GDPR Compliance API Endpoint
 *
 * Comprehensive data protection and privacy compliance system
 */

require_once '../config/database.php';
require_once '../src/Logger.php';
require_once '../src/Middleware.php';
require_once '../../integrations/gdpr-compliance.php';

// Initialize components
$db = getDatabaseConnection();
$logger = new Logger($db);
$middleware = new Middleware($db, $logger);
$gdprCompliance = new GDPRCompliance($db, $logger);
$cookieManager = new CookieConsentManager($db, $logger);
$piaManager = new DPIAManager($db, $logger);

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
$path = str_replace('/backend/api/gdpr-compliance', '', $path);
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
            handleGetRequest($resource, $id, $_GET, $gdprCompliance, $cookieManager, $piaManager);
            break;

        case 'POST':
            handlePostRequest($resource, $input, $gdprCompliance, $cookieManager, $piaManager, $middleware);
            break;

        case 'PUT':
            handlePutRequest($resource, $id, $input, $gdprCompliance, $cookieManager, $piaManager, $middleware);
            break;

        case 'DELETE':
            handleDeleteRequest($resource, $id, $gdprCompliance, $middleware);
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    $logger->error('GDPR Compliance API error', ['error' => $e->getMessage()]);
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

/**
 * Handle GET requests
 */
function handleGetRequest($resource, $id, $queryParams, $gdprCompliance, $cookieManager, $piaManager)
{
    switch ($resource) {
        case null:
            // Get GDPR system status
            $status = $gdprCompliance->getStatus();
            echo json_encode(['success' => true, 'status' => $status]);
            break;

        case 'consents':
            if ($id) {
                // Get specific consent
                $consent = getDataSubjectConsent($id);
                if ($consent) {
                    echo json_encode(['success' => true, 'consent' => $consent]);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Consent not found']);
                }
            } else {
                // Get consents with filters
                $consents = getDataSubjectConsents($queryParams);
                echo json_encode(['success' => true, 'consents' => $consents]);
            }
            break;

        case 'rights-requests':
            if ($id) {
                // Get specific rights request
                $request = getRightsRequest($id);
                if ($request) {
                    echo json_encode(['success' => true, 'request' => $request]);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Rights request not found']);
                }
            } else {
                // Get rights requests
                $requests = getRightsRequests($queryParams);
                echo json_encode(['success' => true, 'requests' => $requests]);
            }
            break;

        case 'processing-activities':
            // Get data processing activities
            $activities = getDataProcessingActivities($queryParams);
            echo json_encode(['success' => true, 'activities' => $activities]);
            break;

        case 'retention-compliance':
            // Check data retention compliance
            $compliance = $gdprCompliance->checkRetentionCompliance();
            echo json_encode(['success' => true, 'compliance' => $compliance]);
            break;

        case 'compliance-report':
            // Generate GDPR compliance report
            $report = $gdprCompliance->generateComplianceReport($queryParams['period'] ?? '30 days');
            echo json_encode(['success' => true, 'report' => $report]);
            break;

        case 'cookie-consent':
            // Get cookie consent preferences
            $consent = $cookieManager->getCookieConsent($queryParams['user_id'] ?? null);
            echo json_encode($consent);
            break;

        case 'pia':
            if ($id) {
                // Get specific PIA
                $pia = getPIA($id);
                if ($pia) {
                    echo json_encode(['success' => true, 'pia' => $pia]);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'PIA not found']);
                }
            } else {
                // Get PIAs
                $pias = $piaManager->getPIAs($queryParams['status'] ?? null);
                echo json_encode($pias);
            }
            break;

        case 'data-breaches':
            // Get data breach notifications
            $breaches = getDataBreaches($queryParams);
            echo json_encode(['success' => true, 'breaches' => $breaches]);
            break;

        case 'audit-logs':
            // Get GDPR audit logs
            $logs = getGDPRAuditLogs($queryParams);
            echo json_encode(['success' => true, 'logs' => $logs]);
            break;

        default:
            http_response_code(404);
            echo json_encode(['error' => 'Resource not found']);
    }
}

/**
 * Handle POST requests
 */
function handlePostRequest($resource, $input, $gdprCompliance, $cookieManager, $piaManager, $middleware)
{
    switch ($resource) {
        case 'initialize':
            // Initialize GDPR compliance system
            $result = $gdprCompliance->initialize();
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'GDPR compliance system initialized']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to initialize GDPR system']);
            }
            break;

        case 'record-consent':
            // Record data subject consent
            if (isset($input['data_subject_id']) && isset($input['data_subject_type']) && isset($input['consent_type'])) {
                $result = $gdprCompliance->recordConsent(
                    $input['data_subject_id'],
                    $input['data_subject_type'],
                    $input['consent_type'],
                    $input['consent_given'] ?? true,
                    $input['legal_basis'] ?? 'consent',
                    $input['consent_scope'] ?? null
                );
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Data subject ID, type, and consent type required']);
            }
            break;

        case 'withdraw-consent':
            // Withdraw data subject consent
            if (isset($input['data_subject_id']) && isset($input['consent_type'])) {
                $result = $gdprCompliance->withdrawConsent($input['data_subject_id'], $input['consent_type']);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Data subject ID and consent type required']);
            }
            break;

        case 'rights-request':
            // Handle data subject rights request
            if (isset($input['data_subject_id']) && isset($input['data_subject_type']) && isset($input['request_type'])) {
                $result = $gdprCompliance->handleRightsRequest(
                    $input['data_subject_id'],
                    $input['data_subject_type'],
                    $input['request_type'],
                    $input['request_details'] ?? null
                );
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Data subject ID, type, and request type required']);
            }
            break;

        case 'process-access-request':
            // Process data access request
            if (isset($input['request_id'])) {
                $result = $gdprCompliance->processDataAccessRequest($input['request_id']);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Request ID required']);
            }
            break;

        case 'process-erasure-request':
            // Process data erasure request
            if (isset($input['request_id'])) {
                $result = $gdprCompliance->processDataErasureRequest($input['request_id']);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Request ID required']);
            }
            break;

        case 'record-breach':
            // Record data breach
            if (isset($input['description'])) {
                $result = $gdprCompliance->recordDataBreach($input);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Breach description required']);
            }
            break;

        case 'anonymize-data':
            // Perform data anonymization
            if (isset($input['data_subject_id']) && isset($input['data_category'])) {
                $result = $gdprCompliance->anonymizeData(
                    $input['data_subject_id'],
                    $input['data_category'],
                    $input['method'] ?? 'pseudonymization'
                );
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Data subject ID and category required']);
            }
            break;

        case 'cookie-consent':
            // Record cookie consent preferences
            if (isset($input['preferences'])) {
                $result = $cookieManager->recordCookieConsent($input['user_id'] ?? null, $input['preferences']);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Cookie preferences required']);
            }
            break;

        case 'create-pia':
            // Create privacy impact assessment
            if (isset($input['project_name'])) {
                $result = $piaManager->createPIA($input);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Project name required']);
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
function handlePutRequest($resource, $id, $input, $gdprCompliance, $cookieManager, $piaManager, $middleware)
{
    // Require authentication for PUT operations
    $user = $middleware->authenticate();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        return;
    }

    switch ($resource) {
        case 'rights-request':
            if ($id) {
                $result = updateRightsRequest($id, $input);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Request ID required']);
            }
            break;

        case 'processing-activity':
            if ($id) {
                $result = updateProcessingActivity($id, $input);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Activity ID required']);
            }
            break;

        case 'pia':
            if ($id) {
                $result = updatePIA($id, $input);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'PIA ID required']);
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
function handleDeleteRequest($resource, $id, $gdprCompliance, $middleware)
{
    // Require authentication
    $user = $middleware->authenticate();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        return;
    }

    switch ($resource) {
        case 'consent':
            if ($id) {
                $result = deleteConsent($id);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Consent ID required']);
            }
            break;

        case 'rights-request':
            if ($id) {
                $result = deleteRightsRequest($id);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Request ID required']);
            }
            break;

        default:
            http_response_code(404);
            echo json_encode(['error' => 'Resource not found']);
    }
}

/**
 * Get data subject consents
 */
function getDataSubjectConsents($queryParams)
{
    $db = $GLOBALS['db'];
    $query = "SELECT * FROM data_subject_consents WHERE 1=1";
    $params = [];

    if (isset($queryParams['data_subject_id'])) {
        $query .= " AND data_subject_id = ?";
        $params[] = $queryParams['data_subject_id'];
    }

    if (isset($queryParams['data_subject_type'])) {
        $query .= " AND data_subject_type = ?";
        $params[] = $queryParams['data_subject_type'];
    }

    if (isset($queryParams['consent_type'])) {
        $query .= " AND consent_type = ?";
        $params[] = $queryParams['consent_type'];
    }

    if (isset($queryParams['withdrawn'])) {
        $withdrawn = $queryParams['withdrawn'] === 'true' ? 1 : 0;
        $query .= " AND consent_withdrawn = ?";
        $params[] = $withdrawn;
    }

    $query .= " ORDER BY created_at DESC LIMIT 100";

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get specific data subject consent
 */
function getDataSubjectConsent($consentId)
{
    $db = $GLOBALS['db'];
    $stmt = $db->prepare("SELECT * FROM data_subject_consents WHERE consent_id = ?");
    $stmt->execute([$consentId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Get rights requests
 */
function getRightsRequests($queryParams)
{
    $db = $GLOBALS['db'];
    $query = "SELECT * FROM data_subject_rights_requests WHERE 1=1";
    $params = [];

    if (isset($queryParams['data_subject_id'])) {
        $query .= " AND data_subject_id = ?";
        $params[] = $queryParams['data_subject_id'];
    }

    if (isset($queryParams['request_type'])) {
        $query .= " AND request_type = ?";
        $params[] = $queryParams['request_type'];
    }

    if (isset($queryParams['status'])) {
        $query .= " AND status = ?";
        $params[] = $queryParams['status'];
    }

    $query .= " ORDER BY request_date DESC LIMIT 100";

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get specific rights request
 */
function getRightsRequest($requestId)
{
    $db = $GLOBALS['db'];
    $stmt = $db->prepare("SELECT * FROM data_subject_rights_requests WHERE request_id = ?");
    $stmt->execute([$requestId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Get data processing activities
 */
function getDataProcessingActivities($queryParams)
{
    $db = $GLOBALS['db'];
    $query = "SELECT * FROM data_processing_activities WHERE 1=1";
    $params = [];

    if (isset($queryParams['legal_basis'])) {
        $query .= " AND legal_basis = ?";
        $params[] = $queryParams['legal_basis'];
    }

    $query .= " ORDER BY created_at DESC";

    $stmt = $db->prepare($query);
    $stmt->execute($params);

    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Decode JSON fields
    foreach ($activities as &$activity) {
        $activity['data_categories'] = json_decode($activity['data_categories'], true);
        $activity['data_subjects'] = json_decode($activity['data_subjects'], true);
        $activity['recipients'] = json_decode($activity['recipients'], true);
        $activity['transfer_countries'] = json_decode($activity['transfer_countries'], true);
        $activity['risk_assessment'] = json_decode($activity['risk_assessment'], true);
    }

    return $activities;
}

/**
 * Get data breaches
 */
function getDataBreaches($queryParams)
{
    $db = $GLOBALS['db'];
    $query = "SELECT * FROM data_breach_notifications WHERE 1=1";
    $params = [];

    if (isset($queryParams['start_date'])) {
        $query .= " AND breach_date >= ?";
        $params[] = $queryParams['start_date'];
    }

    if (isset($queryParams['end_date'])) {
        $query .= " AND breach_date <= ?";
        $params[] = $queryParams['end_date'];
    }

    $query .= " ORDER BY breach_date DESC LIMIT 50";

    $stmt = $db->prepare($query);
    $stmt->execute($params);

    $breaches = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Decode JSON fields
    foreach ($breaches as &$breach) {
        $breach['data_categories_affected'] = json_decode($breach['data_categories_affected'], true);
        $breach['risk_assessment'] = json_decode($breach['risk_assessment'], true);
    }

    return $breaches;
}

/**
 * Get GDPR audit logs
 */
function getGDPRAuditLogs($queryParams)
{
    $db = $GLOBALS['db'];
    $query = "SELECT * FROM gdpr_audit_logs WHERE 1=1";
    $params = [];

    if (isset($queryParams['action_type'])) {
        $query .= " AND action_type = ?";
        $params[] = $queryParams['action_type'];
    }

    if (isset($queryParams['data_subject_id'])) {
        $query .= " AND data_subject_id = ?";
        $params[] = $queryParams['data_subject_id'];
    }

    if (isset($queryParams['start_date'])) {
        $query .= " AND timestamp >= ?";
        $params[] = $queryParams['start_date'];
    }

    if (isset($queryParams['end_date'])) {
        $query .= " AND timestamp <= ?";
        $params[] = $queryParams['end_date'];
    }

    $query .= " ORDER BY timestamp DESC LIMIT 200";

    $stmt = $db->prepare($query);
    $stmt->execute($params);

    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Decode JSON action details
    foreach ($logs as &$log) {
        $log['action_details'] = json_decode($log['action_details'], true);
    }

    return $logs;
}

/**
 * Get specific PIA
 */
function getPIA($piaId)
{
    $db = $GLOBALS['db'];
    $stmt = $db->prepare("SELECT * FROM privacy_impact_assessments WHERE assessment_id = ?");
    $stmt->execute([$piaId]);
    $pia = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($pia) {
        $pia['processing_activities'] = json_decode($pia['processing_activities'], true);
        $pia['data_flows'] = json_decode($pia['data_flows'], true);
        $pia['risks_identified'] = json_decode($pia['risks_identified'], true);
        $pia['mitigation_measures'] = json_decode($pia['mitigation_measures'], true);
        $pia['residual_risks'] = json_decode($pia['residual_risks'], true);
    }

    return $pia;
}

/**
 * Update rights request
 */
function updateRightsRequest($requestId, $updateData)
{
    $db = $GLOBALS['db'];

    try {
        $stmt = $db->prepare("
            UPDATE data_subject_rights_requests
            SET status = ?, response_provided = ?, completed_date = NOW()
            WHERE request_id = ?
        ");

        $stmt->execute([
            $updateData['status'] ?? 'completed',
            $updateData['response_provided'] ?? null,
            $requestId
        ]);

        return ['success' => true, 'message' => 'Rights request updated successfully'];

    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Update processing activity
 */
function updateProcessingActivity($activityId, $updateData)
{
    $db = $GLOBALS['db'];

    try {
        $stmt = $db->prepare("
            UPDATE data_processing_activities
            SET activity_description = ?, dpo_approved = ?, dpo_approval_date = ?
            WHERE activity_id = ?
        ");

        $stmt->execute([
            $updateData['activity_description'] ?? null,
            $updateData['dpo_approved'] ?? null,
            isset($updateData['dpo_approved']) && $updateData['dpo_approved'] ? date('Y-m-d H:i:s') : null,
            $activityId
        ]);

        return ['success' => true, 'message' => 'Processing activity updated successfully'];

    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Update PIA
 */
function updatePIA($piaId, $updateData)
{
    $db = $GLOBALS['db'];

    try {
        $stmt = $db->prepare("
            UPDATE privacy_impact_assessments
            SET approval_status = ?, approval_date = ?, recommendations = ?
            WHERE assessment_id = ?
        ");

        $stmt->execute([
            $updateData['approval_status'] ?? 'approved',
            isset($updateData['approval_status']) && $updateData['approval_status'] === 'approved' ? date('Y-m-d H:i:s') : null,
            $updateData['recommendations'] ?? null,
            $piaId
        ]);

        return ['success' => true, 'message' => 'PIA updated successfully'];

    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Delete consent
 */
function deleteConsent($consentId)
{
    $db = $GLOBALS['db'];

    try {
        $stmt = $db->prepare("DELETE FROM data_subject_consents WHERE consent_id = ?");
        $stmt->execute([$consentId]);

        return ['success' => true, 'message' => 'Consent deleted successfully'];

    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Delete rights request
 */
function deleteRightsRequest($requestId)
{
    $db = $GLOBALS['db'];

    try {
        $stmt = $db->prepare("DELETE FROM data_subject_rights_requests WHERE request_id = ?");
        $stmt->execute([$requestId]);

        return ['success' => true, 'message' => 'Rights request deleted successfully'];

    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}
