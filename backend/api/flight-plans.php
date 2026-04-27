<?php
/**
 * Flight Plans API Endpoint
 *
 * Handles flight plan management, clearances, CPDLC, and ACARS messages
 */

require_once '../config/database.php';
require_once '../src/Logger.php';
require_once '../src/Middleware.php';
require_once '../../integrations/flight-plan-integration.php';

// Initialize components
$db = getDatabaseConnection();
$logger = new Logger($db);
$middleware = new Middleware($db, $logger);
$flightPlanIntegration = new FlightPlanIntegration($db, $logger);

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
$path = str_replace('/backend/api/flight-plans', '', $path);
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
            handleGetRequest($resource, $id, $flightPlanIntegration);
            break;

        case 'POST':
            handlePostRequest($resource, $input, $flightPlanIntegration, $middleware);
            break;

        case 'PUT':
            handlePutRequest($resource, $id, $input, $flightPlanIntegration, $middleware);
            break;

        case 'DELETE':
            handleDeleteRequest($resource, $id, $flightPlanIntegration, $middleware);
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    $logger->error('Flight plans API error', ['error' => $e->getMessage()]);
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

/**
 * Handle GET requests
 */
function handleGetRequest($resource, $id, $integration)
{
    switch ($resource) {
        case null:
            // Get all active flight plans
            $flightPlans = $integration->getActiveFlightPlans();
            echo json_encode(['success' => true, 'data' => $flightPlans]);
            break;

        case 'clearances':
            if ($id) {
                // Get clearances for specific flight plan
                $clearances = $integration->getClearancesForFlight($id);
                echo json_encode(['success' => true, 'data' => $clearances]);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Flight plan ID required']);
            }
            break;

        case 'cpdlc':
            // Get CPDLC messages
            getCPDLCMessages($id, $integration);
            break;

        case 'acars':
            // Get ACARS messages
            getACARSMessages($id, $integration);
            break;

        default:
            http_response_code(404);
            echo json_encode(['error' => 'Resource not found']);
    }
}

/**
 * Handle POST requests
 */
function handlePostRequest($resource, $input, $integration, $middleware)
{
    // Require authentication for POST operations
    $user = $middleware->authenticate();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        return;
    }

    switch ($resource) {
        case null:
            // Create new flight plan
            $result = $integration->processFlightPlan($input);
            if ($result['success']) {
                http_response_code(201);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode($result);
            }
            break;

        case 'clearances':
            // Process clearance
            if (isset($input['flight_plan_id'])) {
                $validationErrors = $integration->validateClearance($input);
                if (!empty($validationErrors)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Validation failed', 'details' => $validationErrors]);
                    return;
                }

                $clearanceId = $integration->processClearance($input['flight_plan_id'], $input);
                echo json_encode(['success' => true, 'clearance_id' => $clearanceId]);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Flight plan ID required']);
            }
            break;

        case 'cpdlc':
            // Process CPDLC message
            $result = $integration->processCPDLCMessage($input);
            if ($result['success']) {
                http_response_code(201);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode($result);
            }
            break;

        case 'acars':
            // Process ACARS message
            $result = $integration->processACARSMessage($input);
            if ($result['success']) {
                http_response_code(201);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode($result);
            }
            break;

        case 'validate-clearance':
            // Validate clearance automatically
            $validation = $integration->validateClearanceAutomatically($input);
            echo json_encode(['success' => true, 'validation' => $validation]);
            break;

        default:
            http_response_code(404);
            echo json_encode(['error' => 'Resource not found']);
    }
}

/**
 * Handle PUT requests
 */
function handlePutRequest($resource, $id, $input, $integration, $middleware)
{
    // Require authentication
    $user = $middleware->authenticate();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        return;
    }

    switch ($resource) {
        case 'cpdlc':
            if ($id) {
                // Acknowledge CPDLC message
                $acknowledged = $input['acknowledged'] ?? true;
                $integration->acknowledgeCPDLCMessage($id, $acknowledged);
                echo json_encode(['success' => true, 'message' => 'CPDLC message acknowledged']);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Message ID required']);
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
function handleDeleteRequest($resource, $id, $integration, $middleware)
{
    // Require authentication
    $user = $middleware->authenticate();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        return;
    }

    // Only allow deletion of certain resources
    switch ($resource) {
        case 'clearances':
            if ($id) {
                // Soft delete clearance (mark as cancelled)
                $stmt = $GLOBALS['db']->prepare("
                    UPDATE clearances
                    SET valid_to = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $stmt->execute([$id]);
                echo json_encode(['success' => true, 'message' => 'Clearance cancelled']);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Clearance ID required']);
            }
            break;

        default:
            http_response_code(404);
            echo json_encode(['error' => 'Resource not found']);
    }
}

/**
 * Get CPDLC messages
 */
function getCPDLCMessages($flightPlanId, $integration)
{
    $db = $GLOBALS['db'];

    if ($flightPlanId) {
        $stmt = $db->prepare("
            SELECT * FROM cpdlc_messages
            WHERE flight_plan_id = ?
            ORDER BY sent_at DESC
            LIMIT 100
        ");
        $stmt->execute([$flightPlanId]);
    } else {
        $stmt = $db->query("
            SELECT * FROM cpdlc_messages
            ORDER BY sent_at DESC
            LIMIT 100
        ");
    }

    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'data' => $messages]);
}

/**
 * Get ACARS messages
 */
function getACARSMessages($flightPlanId, $integration)
{
    $db = $GLOBALS['db'];

    if ($flightPlanId) {
        $stmt = $db->prepare("
            SELECT * FROM acars_messages
            WHERE flight_plan_id = ?
            ORDER BY received_at DESC
            LIMIT 100
        ");
        $stmt->execute([$flightPlanId]);
    } else {
        $stmt = $db->query("
            SELECT * FROM acars_messages
            ORDER BY received_at DESC
            LIMIT 100
        ");
    }

    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'data' => $messages]);
}
