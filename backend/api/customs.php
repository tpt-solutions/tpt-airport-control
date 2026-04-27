<?php

/**
 * Customs & Border Protection API
 *
 * Handles passport verification, customs declarations, border control processes,
 * and regulatory compliance for international travel management
 */

require_once '../src/Config.php';
require_once '../src/ApiResponse.php';
require_once '../models/CustomsBorderProtection.php';
require_once '../services/CustomsBorderProtectionService.php';

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
$path = str_replace('/backend/api/customs', '', $path);
$pathParts = explode('/', trim($path, '/'));

$customsService = new CustomsBorderProtectionService();

try {
    switch ($method) {
        case 'GET':
            handleGet($pathParts, $customsService);
            break;
        case 'POST':
            handlePost($pathParts, $customsService);
            break;
        case 'PUT':
            handlePut($pathParts, $customsService);
            break;
        case 'DELETE':
            handleDelete($pathParts, $customsService);
            break;
        default:
            ApiResponse::error('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log("Customs & Border Protection API Error: " . $e->getMessage());
    ApiResponse::error('Internal server error', 500);
}

function handleGet($pathParts, $service) {
    if (empty($pathParts[0])) {
        // Get all customs declarations with optional filters
        $filters = $_GET;
        $declarations = $service->getAllDeclarations($filters);
        ApiResponse::success($declarations);
    } elseif ($pathParts[0] === 'passports') {
        // Get passport records
        $filters = $_GET;
        $passports = $service->getPassports($filters);
        ApiResponse::success($passports);
    } elseif ($pathParts[0] === 'passports' && isset($pathParts[1])) {
        // Get specific passport
        $passportId = $pathParts[1];
        $passport = $service->getPassportById($passportId);
        if ($passport) {
            ApiResponse::success($passport);
        } else {
            ApiResponse::error('Passport not found', 404);
        }
    } elseif ($pathParts[0] === 'declarations' && isset($pathParts[1])) {
        // Get specific declaration
        $declarationId = $pathParts[1];
        $declaration = $service->getDeclarationById($declarationId);
        if ($declaration) {
            ApiResponse::success($declaration);
        } else {
            ApiResponse::error('Declaration not found', 404);
        }
    } elseif ($pathParts[0] === 'passenger' && isset($pathParts[1])) {
        // Get customs data for specific passenger
        $passengerId = $pathParts[1];
        $data = $service->getPassengerCustomsData($passengerId);
        ApiResponse::success($data);
    } elseif ($pathParts[0] === 'flight' && isset($pathParts[1])) {
        // Get customs data for specific flight
        $flightId = $pathParts[1];
        $data = $service->getFlightCustomsData($flightId);
        ApiResponse::success($data);
    } elseif ($pathParts[0] === 'watchlist') {
        // Get watchlist entries
        $filters = $_GET;
        $watchlist = $service->getWatchlist($filters);
        ApiResponse::success($watchlist);
    } elseif ($pathParts[0] === 'clearance') {
        // Get clearance status
        $filters = $_GET;
        $clearance = $service->getClearanceStatus($filters);
        ApiResponse::success($clearance);
    } elseif ($pathParts[0] === 'violations') {
        // Get customs violations
        $filters = $_GET;
        $violations = $service->getCustomsViolations($filters);
        ApiResponse::success($violations);
    } elseif ($pathParts[0] === 'stats') {
        // Get customs statistics
        $stats = $service->getCustomsStatistics();
        ApiResponse::success($stats);
    } else {
        ApiResponse::error('Invalid endpoint', 400);
    }
}

function handlePost($pathParts, $service) {
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($pathParts[0])) {
        // Create new customs declaration
        if (!validateDeclarationData($data)) {
            ApiResponse::error('Invalid declaration data', 400);
            return;
        }

        $declarationId = $service->createDeclaration($data);
        ApiResponse::success(['declaration_id' => $declarationId, 'message' => 'Customs declaration created successfully'], 201);
    } elseif ($pathParts[0] === 'passports') {
        // Register new passport
        if (!validatePassportData($data)) {
            ApiResponse::error('Invalid passport data', 400);
            return;
        }

        $passportId = $service->registerPassport($data);
        ApiResponse::success(['passport_id' => $passportId, 'message' => 'Passport registered successfully'], 201);
    } elseif ($pathParts[0] === 'verify') {
        // Verify passport/document
        if (!validateVerificationData($data)) {
            ApiResponse::error('Invalid verification data', 400);
            return;
        }

        $verification = $service->verifyDocument($data);
        ApiResponse::success($verification);
    } elseif ($pathParts[0] === 'clearance') {
        // Process customs clearance
        if (!validateClearanceData($data)) {
            ApiResponse::error('Invalid clearance data', 400);
            return;
        }

        $clearance = $service->processClearance($data);
        ApiResponse::success($clearance);
    } elseif ($pathParts[0] === 'watchlist') {
        // Add to watchlist
        if (!validateWatchlistData($data)) {
            ApiResponse::error('Invalid watchlist data', 400);
            return;
        }

        $entryId = $service->addToWatchlist($data);
        ApiResponse::success(['entry_id' => $entryId, 'message' => 'Added to watchlist successfully'], 201);
    } elseif ($pathParts[0] === 'violations') {
        // Report customs violation
        if (!validateViolationData($data)) {
            ApiResponse::error('Invalid violation data', 400);
            return;
        }

        $violationId = $service->reportViolation($data);
        ApiResponse::success(['violation_id' => $violationId, 'message' => 'Violation reported successfully'], 201);
    } elseif ($pathParts[0] === 'inspection') {
        // Schedule inspection
        if (!validateInspectionData($data)) {
            ApiResponse::error('Invalid inspection data', 400);
            return;
        }

        $inspectionId = $service->scheduleInspection($data);
        ApiResponse::success(['inspection_id' => $inspectionId, 'message' => 'Inspection scheduled successfully'], 201);
    } else {
        ApiResponse::error('Invalid endpoint', 400);
    }
}

function handlePut($pathParts, $service) {
    $data = json_decode(file_get_contents('php://input'), true);

    if ($pathParts[0] === 'declarations' && isset($pathParts[1])) {
        // Update customs declaration
        $declarationId = $pathParts[1];
        if (!$service->getDeclarationById($declarationId)) {
            ApiResponse::error('Declaration not found', 404);
            return;
        }

        $result = $service->updateDeclaration($declarationId, $data);
        ApiResponse::success(['message' => 'Customs declaration updated successfully']);
    } elseif ($pathParts[0] === 'passports' && isset($pathParts[1])) {
        // Update passport
        $passportId = $pathParts[1];
        $result = $service->updatePassport($passportId, $data);
        ApiResponse::success(['message' => 'Passport updated successfully']);
    } elseif ($pathParts[0] === 'clearance' && isset($pathParts[1])) {
        // Update clearance status
        $clearanceId = $pathParts[1];
        $result = $service->updateClearanceStatus($clearanceId, $data);
        ApiResponse::success(['message' => 'Clearance status updated successfully']);
    } elseif ($pathParts[0] === 'watchlist' && isset($pathParts[1])) {
        // Update watchlist entry
        $entryId = $pathParts[1];
        $result = $service->updateWatchlistEntry($entryId, $data);
        ApiResponse::success(['message' => 'Watchlist entry updated successfully']);
    } elseif ($pathParts[0] === 'violations' && isset($pathParts[1])) {
        // Update violation
        $violationId = $pathParts[1];
        $result = $service->updateViolation($violationId, $data);
        ApiResponse::success(['message' => 'Violation updated successfully']);
    } else {
        ApiResponse::error('Invalid endpoint', 400);
    }
}

function handleDelete($pathParts, $service) {
    if ($pathParts[0] === 'declarations' && isset($pathParts[1])) {
        // Delete customs declaration
        $declarationId = $pathParts[1];
        $result = $service->deleteDeclaration($declarationId);
        ApiResponse::success(['message' => 'Customs declaration deleted successfully']);
    } elseif ($pathParts[0] === 'passports' && isset($pathParts[1])) {
        // Delete passport
        $passportId = $pathParts[1];
        $result = $service->deletePassport($passportId);
        ApiResponse::success(['message' => 'Passport deleted successfully']);
    } elseif ($pathParts[0] === 'watchlist' && isset($pathParts[1])) {
        // Remove from watchlist
        $entryId = $pathParts[1];
        $result = $service->removeFromWatchlist($entryId);
        ApiResponse::success(['message' => 'Removed from watchlist successfully']);
    } elseif ($pathParts[0] === 'violations' && isset($pathParts[1])) {
        // Delete violation
        $violationId = $pathParts[1];
        $result = $service->deleteViolation($violationId);
        ApiResponse::success(['message' => 'Violation deleted successfully']);
    } else {
        ApiResponse::error('Invalid endpoint', 400);
    }
}

function validateDeclarationData($data) {
    $required = ['passenger_id', 'flight_id', 'declaration_type', 'items'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            return false;
        }
    }

    $validTypes = ['personal_goods', 'commercial_goods', 'food_items', 'plants_animals', 'currency', 'medications'];
    if (!in_array($data['declaration_type'], $validTypes)) {
        return false;
    }

    return true;
}

function validatePassportData($data) {
    $required = ['passenger_id', 'passport_number', 'issuing_country', 'issue_date', 'expiry_date'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            return false;
        }
    }

    // Validate dates
    if (strtotime($data['expiry_date']) <= strtotime($data['issue_date'])) {
        return false;
    }

    return true;
}

function validateVerificationData($data) {
    $required = ['document_type', 'document_number', 'verification_method'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            return false;
        }
    }

    $validMethods = ['manual', 'biometric', 'database_lookup', 'api_verification'];
    if (!in_array($data['verification_method'], $validMethods)) {
        return false;
    }

    return true;
}

function validateClearanceData($data) {
    $required = ['passenger_id', 'declaration_id', 'clearance_type'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            return false;
        }
    }

    $validTypes = ['standard', 'expedited', 'random_inspection', 'secondary_screening'];
    if (!in_array($data['clearance_type'], $validTypes)) {
        return false;
    }

    return true;
}

function validateWatchlistData($data) {
    $required = ['entity_type', 'entity_id', 'watchlist_reason', 'severity_level'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            return false;
        }
    }

    $validTypes = ['passenger', 'passport', 'flight', 'cargo'];
    if (!in_array($data['entity_type'], $validTypes)) {
        return false;
    }

    $validSeverities = ['low', 'medium', 'high', 'critical'];
    if (!in_array($data['severity_level'], $validSeverities)) {
        return false;
    }

    return true;
}

function validateViolationData($data) {
    $required = ['passenger_id', 'violation_type', 'description', 'severity'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            return false;
        }
    }

    $validTypes = ['prohibited_items', 'false_declaration', 'currency_violation', 'documentation_issues', 'other'];
    if (!in_array($data['violation_type'], $validTypes)) {
        return false;
    }

    $validSeverities = ['minor', 'moderate', 'serious', 'critical'];
    if (!in_array($data['severity'], $validSeverities)) {
        return false;
    }

    return true;
}

function validateInspectionData($data) {
    $required = ['passenger_id', 'inspection_type', 'scheduled_time', 'location'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            return false;
        }
    }

    $validTypes = ['random', 'targeted', 'secondary', 'detailed'];
    if (!in_array($data['inspection_type'], $validTypes)) {
        return false;
    }

    return true;
}

?>
