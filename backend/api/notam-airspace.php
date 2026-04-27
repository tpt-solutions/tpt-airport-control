<?php
/**
 * NOTAM & Airspace Data API Endpoint
 *
 * Handles NOTAM processing, airspace restrictions, and drone operations
 */

require_once '../config/database.php';
require_once '../src/Logger.php';
require_once '../src/Middleware.php';
require_once '../../integrations/notam-airspace-integration.php';

// Initialize components
$db = getDatabaseConnection();
$logger = new Logger($db);
$middleware = new Middleware($db, $logger);
$notamAirspaceIntegration = new NotamAirspaceIntegration($db, $logger);

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
$path = str_replace('/backend/api/notam-airspace', '', $path);
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
            handleGetRequest($resource, $id, $_GET, $notamAirspaceIntegration);
            break;

        case 'POST':
            handlePostRequest($resource, $input, $notamAirspaceIntegration, $middleware);
            break;

        case 'PUT':
            handlePutRequest($resource, $id, $input, $notamAirspaceIntegration, $middleware);
            break;

        case 'DELETE':
            handleDeleteRequest($resource, $id, $notamAirspaceIntegration, $middleware);
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    $logger->error('NOTAM/Airspace API error', ['error' => $e->getMessage()]);
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

/**
 * Handle GET requests
 */
function handleGetRequest($resource, $id, $queryParams, $integration)
{
    switch ($resource) {
        case null:
            // Get airspace information for location
            if (isset($queryParams['lat']) && isset($queryParams['lon'])) {
                $latitude = (float)$queryParams['lat'];
                $longitude = (float)$queryParams['lon'];
                $altitude = isset($queryParams['alt']) ? (float)$queryParams['alt'] : null;
                $radius = isset($queryParams['radius']) ? (int)$queryParams['radius'] : 100;

                $airspaceInfo = getAirspaceInfo($latitude, $longitude, $altitude, $radius, $integration);
                echo json_encode(['success' => true, 'data' => $airspaceInfo]);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Latitude and longitude required']);
            }
            break;

        case 'notams':
            if ($id) {
                // Get specific NOTAM
                $notam = getNOTAMById($id, $integration);
                if ($notam) {
                    echo json_encode(['success' => true, 'data' => $notam]);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'NOTAM not found']);
                }
            } else {
                // Get NOTAMs for area
                handleNotamQuery($queryParams, $integration);
            }
            break;

        case 'restrictions':
            if ($id) {
                // Get specific restriction
                $restriction = getRestrictionById($id, $integration);
                if ($restriction) {
                    echo json_encode(['success' => true, 'data' => $restriction]);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Restriction not found']);
                }
            } else {
                // Get restrictions for area
                handleRestrictionQuery($queryParams, $integration);
            }
            break;

        case 'drones':
            if ($id) {
                // Get specific drone operation
                $droneOp = getDroneOperationById($id, $integration);
                if ($droneOp) {
                    echo json_encode(['success' => true, 'data' => $droneOp]);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Drone operation not found']);
                }
            } else {
                // Get drone operations for area
                handleDroneQuery($queryParams, $integration);
            }
            break;

        case 'validate-flight':
            // Validate flight plan against restrictions
            if (isset($queryParams['flight_plan_id'])) {
                $validation = validateFlightPlan($queryParams['flight_plan_id'], $integration);
                echo json_encode(['success' => true, 'validation' => $validation]);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Flight plan ID required']);
            }
            break;

        case 'availability':
            // Get airspace availability
            if (isset($queryParams['lat']) && isset($queryParams['lon'])) {
                $latitude = (float)$queryParams['lat'];
                $longitude = (float)$queryParams['lon'];
                $altitude = isset($queryParams['alt']) ? (float)$queryParams['alt'] : null;
                $timeWindow = isset($queryParams['time_window']) ? (int)$queryParams['time_window'] : 3600;

                $availability = $integration->getAirspaceAvailability($latitude, $longitude, $altitude, $timeWindow);
                echo json_encode(['success' => true, 'availability' => $availability]);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Latitude and longitude required']);
            }
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
        case 'notams':
            // Process NOTAM
            $result = $integration->processNOTAM($input);
            if ($result['success']) {
                http_response_code(201);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode($result);
            }
            break;

        case 'restrictions':
            // Process airspace restriction
            $result = $integration->processAirspaceRestriction($input);
            if ($result['success']) {
                http_response_code(201);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode($result);
            }
            break;

        case 'drones':
            // Process drone operation
            $result = $integration->processDroneOperation($input);
            if ($result['success']) {
                http_response_code(201);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode($result);
            }
            break;

        case 'validate-flight':
            // Validate flight plan data
            $validation = $integration->validateFlightPlan($input);
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
        case 'drones':
            if ($id && isset($input['action'])) {
                if ($input['action'] === 'approve') {
                    $result = $integration->approveDroneOperation($id, $user['username']);
                    if ($result['success']) {
                        echo json_encode($result);
                    } else {
                        http_response_code(400);
                        echo json_encode($result);
                    }
                } else {
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid action']);
                }
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Drone operation ID and action required']);
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

    switch ($resource) {
        case 'notams':
            if ($id) {
                // Soft delete NOTAM (mark as cancelled)
                $result = softDeleteNOTAM($id, $integration);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'NOTAM ID required']);
            }
            break;

        case 'restrictions':
            if ($id) {
                // Deactivate restriction
                $result = deactivateRestriction($id, $integration);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Restriction ID required']);
            }
            break;

        default:
            http_response_code(404);
            echo json_encode(['error' => 'Resource not found']);
    }
}

/**
 * Get comprehensive airspace information
 */
function getAirspaceInfo($latitude, $longitude, $altitude, $radius, $integration)
{
    $notams = $integration->getActiveNOTAMs($latitude, $longitude, $radius);
    $restrictions = $integration->getAirspaceRestrictions($latitude, $longitude, $altitude);
    $droneOps = $integration->getDroneOperations($latitude, $longitude, $radius);

    return [
        'location' => ['latitude' => $latitude, 'longitude' => $longitude, 'altitude' => $altitude],
        'notams' => $notams,
        'restrictions' => $restrictions,
        'drone_operations' => $droneOps,
        'summary' => [
            'total_notams' => count($notams),
            'total_restrictions' => count($restrictions),
            'total_drone_operations' => count($droneOps),
            'airspace_clear' => empty($notams) && empty($restrictions) && empty($droneOps)
        ]
    ];
}

/**
 * Handle NOTAM queries
 */
function handleNotamQuery($queryParams, $integration)
{
    if (!isset($queryParams['lat']) || !isset($queryParams['lon'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Latitude and longitude required']);
        return;
    }

    $latitude = (float)$queryParams['lat'];
    $longitude = (float)$queryParams['lon'];
    $radius = isset($queryParams['radius']) ? (int)$queryParams['radius'] : 100;

    $notams = $integration->getActiveNOTAMs($latitude, $longitude, $radius);
    echo json_encode(['success' => true, 'data' => $notams]);
}

/**
 * Handle restriction queries
 */
function handleRestrictionQuery($queryParams, $integration)
{
    if (!isset($queryParams['lat']) || !isset($queryParams['lon'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Latitude and longitude required']);
        return;
    }

    $latitude = (float)$queryParams['lat'];
    $longitude = (float)$queryParams['lon'];
    $altitude = isset($queryParams['alt']) ? (float)$queryParams['alt'] : null;

    $restrictions = $integration->getAirspaceRestrictions($latitude, $longitude, $altitude);
    echo json_encode(['success' => true, 'data' => $restrictions]);
}

/**
 * Handle drone operation queries
 */
function handleDroneQuery($queryParams, $integration)
{
    if (!isset($queryParams['lat']) || !isset($queryParams['lon'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Latitude and longitude required']);
        return;
    }

    $latitude = (float)$queryParams['lat'];
    $longitude = (float)$queryParams['lon'];
    $radius = isset($queryParams['radius']) ? (int)$queryParams['radius'] : 50;

    $droneOps = $integration->getDroneOperations($latitude, $longitude, $radius);
    echo json_encode(['success' => true, 'data' => $droneOps]);
}

/**
 * Get NOTAM by ID
 */
function getNOTAMById($id, $integration)
{
    $db = $GLOBALS['db'];
    $stmt = $db->prepare("SELECT * FROM notams WHERE notam_id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Get restriction by ID
 */
function getRestrictionById($id, $integration)
{
    $db = $GLOBALS['db'];
    $stmt = $db->prepare("SELECT * FROM airspace_restrictions WHERE restriction_id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Get drone operation by ID
 */
function getDroneOperationById($id, $integration)
{
    $db = $GLOBALS['db'];
    $stmt = $db->prepare("SELECT * FROM drone_operations WHERE operation_id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Validate flight plan
 */
function validateFlightPlan($flightPlanId, $integration)
{
    $db = $GLOBALS['db'];

    // Get flight plan details
    $stmt = $db->prepare("
        SELECT fp.*, f.origin, f.destination
        FROM flight_plans fp
        LEFT JOIN flights f ON fp.flight_id = f.id
        WHERE fp.id = ?
    ");
    $stmt->execute([$flightPlanId]);
    $flightPlan = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$flightPlan) {
        return ['valid' => false, 'error' => 'Flight plan not found'];
    }

    // Mock coordinates for airports (in production, you'd have an airport database)
    $airportCoords = [
        'JFK' => ['lat' => 40.6413, 'lon' => -73.7781],
        'LAX' => ['lat' => 33.9425, 'lon' => -118.4081],
        'ORD' => ['lat' => 41.9742, 'lon' => -87.9073],
        // Add more airports as needed
    ];

    $departureCoords = $airportCoords[$flightPlan['origin']] ?? ['lat' => 0, 'lon' => 0];
    $arrivalCoords = $airportCoords[$flightPlan['destination']] ?? ['lat' => 0, 'lon' => 0];

    $flightPlanData = [
        'departure_lat' => $departureCoords['lat'],
        'departure_lon' => $departureCoords['lon'],
        'arrival_lat' => $arrivalCoords['lat'],
        'arrival_lon' => $arrivalCoords['lon']
    ];

    return $integration->validateFlightPlan($flightPlanData);
}

/**
 * Soft delete NOTAM
 */
function softDeleteNOTAM($notamId, $integration)
{
    $db = $GLOBALS['db'];
    try {
        $stmt = $db->prepare("
            UPDATE notams
            SET end_time = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
            WHERE notam_id = ?
        ");
        $stmt->execute([$notamId]);
        return ['success' => true, 'message' => 'NOTAM cancelled'];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Deactivate restriction
 */
function deactivateRestriction($restrictionId, $integration)
{
    $db = $GLOBALS['db'];
    try {
        $stmt = $db->prepare("
            UPDATE airspace_restrictions
            SET active = false, effective_to = CURRENT_TIMESTAMP
            WHERE restriction_id = ?
        ");
        $stmt->execute([$restrictionId]);
        return ['success' => true, 'message' => 'Restriction deactivated'];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}
