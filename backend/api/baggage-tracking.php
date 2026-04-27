<?php

/**
 * Baggage Tracking API
 *
 * Handles real-time baggage tracking, RFID tag management, and automated baggage routing
 * for enhanced passenger experience and operational efficiency
 */

require_once '../src/Config.php';
require_once '../src/ApiResponse.php';
require_once '../models/BaggageTracking.php';
require_once '../services/BaggageTrackingService.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// JWT Authentication
$headers = getallheaders();
$authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';

if (empty($authHeader) || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
    ApiResponse::error('Unauthorized', 401);
}

$token = $matches[1];
$auth = new Auth();
$userId = $auth->validateToken($token);

if (!$userId) {
    ApiResponse::error('Invalid token', 401);
}

$method = $_SERVER['REQUEST_METHOD'];
$request = $_SERVER['REQUEST_URI'];
$path = parse_url($request, PHP_URL_PATH);
$path = str_replace('/backend/api/baggage-tracking', '', $path);
$pathParts = explode('/', trim($path, '/'));

$baggageService = new BaggageTrackingService();

try {
    switch ($method) {
        case 'GET':
            handleGet($pathParts, $baggageService);
            break;
        case 'POST':
            handlePost($pathParts, $baggageService);
            break;
        case 'PUT':
            handlePut($pathParts, $baggageService);
            break;
        case 'DELETE':
            handleDelete($pathParts, $baggageService);
            break;
        default:
            ApiResponse::error('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log("Baggage Tracking API Error: " . $e->getMessage());
    ApiResponse::error('Internal server error', 500);
}

function handleGet($pathParts, $service) {
    if (empty($pathParts[0])) {
        // Get all baggage items with optional filters
        $filters = $_GET;
        $baggage = $service->getAllBaggage($filters);
        ApiResponse::success($baggage);
    } elseif ($pathParts[0] === 'items' && isset($pathParts[1])) {
        // Get specific baggage item
        $baggageId = $pathParts[1];
        $baggage = $service->getBaggageById($baggageId);
        if ($baggage) {
            ApiResponse::success($baggage);
        } else {
            ApiResponse::error('Baggage item not found', 404);
        }
    } elseif ($pathParts[0] === 'passenger' && isset($pathParts[1])) {
        // Get baggage for specific passenger
        $passengerId = $pathParts[1];
        $baggage = $service->getBaggageByPassenger($passengerId);
        ApiResponse::success($baggage);
    } elseif ($pathParts[0] === 'flight' && isset($pathParts[1])) {
        // Get baggage for specific flight
        $flightId = $pathParts[1];
        $baggage = $service->getBaggageByFlight($flightId);
        ApiResponse::success($baggage);
    } elseif ($pathParts[0] === 'tags' && isset($pathParts[1])) {
        // Get baggage by RFID tag
        $tagId = $pathParts[1];
        $baggage = $service->getBaggageByTag($tagId);
        if ($baggage) {
            ApiResponse::success($baggage);
        } else {
            ApiResponse::error('Baggage tag not found', 404);
        }
    } elseif ($pathParts[0] === 'scan') {
        // Get baggage scan history
        $filters = $_GET;
        $scans = $service->getScanHistory($filters);
        ApiResponse::success($scans);
    } elseif ($pathParts[0] === 'lost') {
        // Get lost baggage reports
        $filters = $_GET;
        $lostBaggage = $service->getLostBaggage($filters);
        ApiResponse::success($lostBaggage);
    } elseif ($pathParts[0] === 'stats') {
        // Get baggage statistics
        $stats = $service->getBaggageStatistics();
        ApiResponse::success($stats);
    } elseif ($pathParts[0] === 'routes') {
        // Get baggage routing information
        $routes = $service->getBaggageRoutes();
        ApiResponse::success($routes);
    } else {
        ApiResponse::error('Invalid endpoint', 400);
    }
}

function handlePost($pathParts, $service) {
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($pathParts[0])) {
        // Register new baggage item
        if (!validateBaggageData($data)) {
            ApiResponse::error('Invalid baggage data', 400);
            return;
        }

        $baggageId = $service->registerBaggage($data);
        ApiResponse::success(['baggage_id' => $baggageId, 'message' => 'Baggage registered successfully'], 201);
    } elseif ($pathParts[0] === 'scan') {
        // Record baggage scan
        if (!validateScanData($data)) {
            ApiResponse::error('Invalid scan data', 400);
            return;
        }

        $scanId = $service->recordScan($data);
        ApiResponse::success(['scan_id' => $scanId, 'message' => 'Baggage scan recorded successfully'], 201);
    } elseif ($pathParts[0] === 'lost') {
        // Report lost baggage
        if (!validateLostBaggageData($data)) {
            ApiResponse::error('Invalid lost baggage data', 400);
            return;
        }

        $reportId = $service->reportLostBaggage($data);
        ApiResponse::success(['report_id' => $reportId, 'message' => 'Lost baggage reported successfully'], 201);
    } elseif ($pathParts[0] === 'transfer') {
        // Transfer baggage between flights
        if (!validateTransferData($data)) {
            ApiResponse::error('Invalid transfer data', 400);
            return;
        }

        $result = $service->transferBaggage($data);
        ApiResponse::success(['message' => 'Baggage transferred successfully']);
    } elseif ($pathParts[0] === 'tags') {
        // Register RFID tag
        if (!validateTagData($data)) {
            ApiResponse::error('Invalid tag data', 400);
            return;
        }

        $tagId = $service->registerTag($data);
        ApiResponse::success(['tag_id' => $tagId, 'message' => 'RFID tag registered successfully'], 201);
    } else {
        ApiResponse::error('Invalid endpoint', 400);
    }
}

function handlePut($pathParts, $service) {
    $data = json_decode(file_get_contents('php://input'), true);

    if ($pathParts[0] === 'items' && isset($pathParts[1])) {
        // Update baggage item
        $baggageId = $pathParts[1];
        if (!$service->getBaggageById($baggageId)) {
            ApiResponse::error('Baggage item not found', 404);
            return;
        }

        $result = $service->updateBaggage($baggageId, $data);
        ApiResponse::success(['message' => 'Baggage updated successfully']);
    } elseif ($pathParts[0] === 'tags' && isset($pathParts[1])) {
        // Update RFID tag
        $tagId = $pathParts[1];
        $result = $service->updateTag($tagId, $data);
        ApiResponse::success(['message' => 'RFID tag updated successfully']);
    } elseif ($pathParts[0] === 'lost' && isset($pathParts[1])) {
        // Update lost baggage report
        $reportId = $pathParts[1];
        $result = $service->updateLostBaggageReport($reportId, $data);
        ApiResponse::success(['message' => 'Lost baggage report updated successfully']);
    } elseif ($pathParts[0] === 'routes' && isset($pathParts[1])) {
        // Update baggage route
        $routeId = $pathParts[1];
        $result = $service->updateBaggageRoute($routeId, $data);
        ApiResponse::success(['message' => 'Baggage route updated successfully']);
    } else {
        ApiResponse::error('Invalid endpoint', 400);
    }
}

function handleDelete($pathParts, $service) {
    if ($pathParts[0] === 'items' && isset($pathParts[1])) {
        // Delete baggage item
        $baggageId = $pathParts[1];
        $result = $service->deleteBaggage($baggageId);
        ApiResponse::success(['message' => 'Baggage item deleted successfully']);
    } elseif ($pathParts[0] === 'tags' && isset($pathParts[1])) {
        // Delete RFID tag
        $tagId = $pathParts[1];
        $result = $service->deleteTag($tagId);
        ApiResponse::success(['message' => 'RFID tag deleted successfully']);
    } elseif ($pathParts[0] === 'lost' && isset($pathParts[1])) {
        // Delete lost baggage report
        $reportId = $pathParts[1];
        $result = $service->deleteLostBaggageReport($reportId);
        ApiResponse::success(['message' => 'Lost baggage report deleted successfully']);
    } else {
        ApiResponse::error('Invalid endpoint', 400);
    }
}

function validateBaggageData($data) {
    $required = ['passenger_id', 'flight_id', 'weight_kg', 'dimensions'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            return false;
        }
    }

    // Validate weight (max 32kg for most airlines)
    if ($data['weight_kg'] <= 0 || $data['weight_kg'] > 32) {
        return false;
    }

    // Validate dimensions
    if (!isset($data['dimensions']['length']) || !isset($data['dimensions']['width']) || !isset($data['dimensions']['height'])) {
        return false;
    }

    return true;
}

function validateScanData($data) {
    $required = ['baggage_id', 'scanner_location', 'scan_type'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            return false;
        }
    }

    $validScanTypes = ['check_in', 'security', 'loading', 'unloading', 'delivery'];
    if (!in_array($data['scan_type'], $validScanTypes)) {
        return false;
    }

    return true;
}

function validateLostBaggageData($data) {
    $required = ['passenger_id', 'baggage_description', 'last_seen_location'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            return false;
        }
    }

    return true;
}

function validateTransferData($data) {
    $required = ['baggage_id', 'from_flight_id', 'to_flight_id', 'transfer_reason'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            return false;
        }
    }

    return true;
}

function validateTagData($data) {
    $required = ['tag_number', 'baggage_id'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            return false;
        }
    }

    return true;
}

?>
