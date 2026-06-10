<?php

/**
 * Self Check-in API
 *
 * RESTful API for automated passenger check-in kiosks and mobile check-in
 */

require_once '../src/ApiResponse.php';
require_once '../models/SelfCheckin.php';
require_once '../src/Auth.php';

// Initialize components
$apiResponse = new ApiResponse();
$selfCheckinManager = new SelfCheckin();
$auth = new Auth();

// Get request method and path
$method = $_SERVER['REQUEST_METHOD'];
$request = $_SERVER['REQUEST_URI'];
$path = parse_url($request, PHP_URL_PATH);

// Remove base path
$path = str_replace('/api/self-checkin', '', $path);
$path = str_replace('/backend/api/self-checkin', '', $path);

// Get path segments
$pathSegments = array_filter(explode('/', trim($path, '/')));
$resource = $pathSegments[0] ?? null;
$action = $pathSegments[1] ?? null;

// Get user from JWT token (optional for public kiosk access)
$user = null;
$headers = getallheaders();
if (isset($headers['Authorization'])) {
    $token = str_replace('Bearer ', '', $headers['Authorization']);
    try {
        $user = $auth->validateToken($token);
    } catch (Exception $e) {
        // For public kiosk endpoints, we don't require authentication
        // but we'll log the attempt
        error_log("Self-checkin auth failed: " . $e->getMessage());
    }
}

try {
    switch ($method) {
        case 'GET':
            handleGetRequest($resource, $action, $selfCheckinManager, $apiResponse);
            break;

        case 'POST':
            handlePostRequest($resource, $action, $selfCheckinManager, $user, $apiResponse);
            break;

        case 'PUT':
            handlePutRequest($resource, $action, $selfCheckinManager, $user, $apiResponse);
            break;

        default:
            $apiResponse->error('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log("Self-checkin API Error: " . $e->getMessage());
    error_log('API error: ' . $e->getMessage());
    $apiResponse->error('An internal error occurred', 500);
}

/**
 * Handle GET requests
 */
function handleGetRequest($resource, $action, $selfCheckinManager, $apiResponse)
{
    switch ($resource) {
        case null:
        case 'kiosks':
            // Get available kiosks
            $kiosks = $selfCheckinManager->getAvailableKiosks();
            $apiResponse->success($kiosks);
            break;

        case 'session':
            if ($action && is_numeric($action)) {
                // Get session details
                $session = $selfCheckinManager->getSession($action);
                if (!$session) {
                    $apiResponse->error('Session not found', 404);
                    return;
                }
                $apiResponse->success($session);
            } else {
                $apiResponse->error('Session ID required', 400);
            }
            break;

        case 'seats':
            // Get available seats for a flight
            $flightId = $_GET['flight_id'] ?? null;
            if (!$flightId) {
                $apiResponse->error('Flight ID required', 400);
                return;
            }

            $seats = $selfCheckinManager->getAvailableSeats($flightId);
            $apiResponse->success($seats);
            break;

        case 'preferences':
            // Get check-in preferences for a passenger
            $passengerId = $_GET['passenger_id'] ?? null;
            if (!$passengerId) {
                $apiResponse->error('Passenger ID required', 400);
                return;
            }

            $preferences = $selfCheckinManager->getCheckinPreferences($passengerId);
            $apiResponse->success($preferences);
            break;

        case 'analytics':
            // Get check-in analytics (admin only)
            if (!$user || !in_array($user['role'], ['super_admin', 'admin'])) {
                $apiResponse->error('Insufficient permissions', 403);
                return;
            }

            $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
            $endDate = $_GET['end_date'] ?? date('Y-m-d');

            $analytics = $selfCheckinManager->getCheckinAnalytics($startDate, $endDate);
            $apiResponse->success($analytics);
            break;

        case 'performance':
            // Get kiosk performance metrics (admin only)
            if (!$user || !in_array($user['role'], ['super_admin', 'admin'])) {
                $apiResponse->error('Insufficient permissions', 403);
                return;
            }

            $kioskId = $_GET['kiosk_id'] ?? null;
            $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
            $endDate = $_GET['end_date'] ?? date('Y-m-d');

            if (!$kioskId) {
                $apiResponse->error('Kiosk ID required', 400);
                return;
            }

            $performance = $selfCheckinManager->getKioskPerformance($kioskId, $startDate, $endDate);
            $apiResponse->success($performance);
            break;

        default:
            $apiResponse->error('Resource not found', 404);
    }
}

/**
 * Handle POST requests
 */
function handlePostRequest($resource, $action, $selfCheckinManager, $user, $apiResponse)
{
    $input = json_decode(file_get_contents('php://input'), true);

    switch ($resource) {
        case 'session':
            // Start a new check-in session
            if (!isset($input['passenger_id']) || !isset($input['booking_id'])) {
                $apiResponse->error('Passenger ID and booking ID required', 400);
                return;
            }

            $result = $selfCheckinManager->startCheckinSession($input);
            $apiResponse->success($result);
            break;

        case 'verify':
            // Perform biometric verification
            if (!isset($input['passenger_id']) || !isset($input['verification_type'])) {
                $apiResponse->error('Passenger ID and verification type required', 400);
                return;
            }

            $result = $selfCheckinManager->performBiometricVerification($input);
            $apiResponse->success($result);
            break;

        case 'progress':
            // Update session progress
            if (!isset($input['session_id']) || !isset($input['step'])) {
                $apiResponse->error('Session ID and step required', 400);
                return;
            }

            $result = $selfCheckinManager->updateSessionProgress($input['session_id'], $input);
            $apiResponse->success($result);
            break;

        case 'seat':
            // Select a seat
            if (!isset($input['session_id']) || !isset($input['flight_id']) || !isset($input['selected_seat'])) {
                $apiResponse->error('Session ID, flight ID, and selected seat required', 400);
                return;
            }

            $result = $selfCheckinManager->selectSeat($input);
            $apiResponse->success($result);
            break;

        case 'service':
            // Select additional services
            if (!isset($input['session_id']) || !isset($input['service_type'])) {
                $apiResponse->error('Session ID and service type required', 400);
                return;
            }

            $result = $selfCheckinManager->selectServices($input);
            $apiResponse->success($result);
            break;

        case 'complete':
            // Complete check-in
            if (!isset($input['session_id'])) {
                $apiResponse->error('Session ID required', 400);
                return;
            }

            $result = $selfCheckinManager->completeCheckin($input['session_id'], $input);
            $apiResponse->success($result);
            break;

        default:
            $apiResponse->error('Resource not found', 404);
    }
}

/**
 * Handle PUT requests
 */
function handlePutRequest($resource, $action, $selfCheckinManager, $user, $apiResponse)
{
    $input = json_decode(file_get_contents('php://input'), true);

    switch ($resource) {
        case 'preferences':
            // Update check-in preferences
            if (!isset($input['passenger_id'])) {
                $apiResponse->error('Passenger ID required', 400);
                return;
            }

            $result = $selfCheckinManager->updateCheckinPreferences($input['passenger_id'], $input);
            $apiResponse->success($result);
            break;

        default:
            $apiResponse->error('Resource not found', 404);
    }
}

/**
 * Validate session ownership
 */
function validateSessionOwnership($sessionId, $passengerId)
{
    $db = new PDO(
        "pgsql:host=" . getenv('DB_HOST') . ";dbname=" . getenv('DB_DATABASE'),
        getenv('DB_USERNAME'),
        getenv('DB_PASSWORD'),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $stmt = $db->prepare("
        SELECT passenger_id FROM checkin_sessions
        WHERE session_id = ?
    ");

    $stmt->execute([$sessionId]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    return $session && $session['passenger_id'] == $passengerId;
}

/**
 * Check if passenger is eligible for check-in
 */
function checkCheckinEligibility($passengerId, $bookingId)
{
    $db = new PDO(
        "pgsql:host=" . getenv('DB_HOST') . ";dbname=" . getenv('DB_DATABASE'),
        getenv('DB_USERNAME'),
        getenv('DB_PASSWORD'),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Check booking status and flight timing
    $stmt = $db->prepare("
        SELECT
            b.checkin_status,
            f.departure_time,
            f.origin,
            f.destination
        FROM bookings b
        JOIN flights f ON b.flight_id = f.flight_id
        WHERE b.booking_id = ? AND b.passenger_id = ?
    ");

    $stmt->execute([$bookingId, $passengerId]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        throw new Exception("Booking not found");
    }

    // Check if already checked in
    if ($booking['checkin_status'] === 'completed') {
        throw new Exception("Passenger already checked in");
    }

    // Check flight timing (must be within check-in window)
    $departureTime = strtotime($booking['departure_time']);
    $currentTime = time();
    $hoursUntilDeparture = ($departureTime - $currentTime) / 3600;

    // Allow check-in 24 hours before to 1 hour before departure
    if ($hoursUntilDeparture > 24) {
        throw new Exception("Check-in not yet available. Check-in opens 24 hours before departure.");
    }

    if ($hoursUntilDeparture < 1) {
        throw new Exception("Check-in closed. Flight departs in less than 1 hour.");
    }

    return $booking;
}

/**
 * Generate session token for kiosk security
 */
function generateSessionToken($sessionId)
{
    $secret = getenv('SESSION_SECRET') ?: 'default_secret_change_in_production';
    $payload = [
        'session_id' => $sessionId,
        'issued_at' => time(),
        'expires_at' => time() + 3600 // 1 hour
    ];

    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    $payload = json_encode($payload);

    $headerEncoded = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $payloadEncoded = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));

    $signature = hash_hmac('sha256', $headerEncoded . "." . $payloadEncoded, $secret, true);
    $signatureEncoded = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

    return $headerEncoded . "." . $payloadEncoded . "." . $signatureEncoded;
}

/**
 * Validate session token
 */
function validateSessionToken($token)
{
    $secret = getenv('SESSION_SECRET') ?: 'default_secret_change_in_production';
    $parts = explode('.', $token);

    if (count($parts) !== 3) {
        return false;
    }

    $header = $parts[0];
    $payload = $parts[1];
    $signature = $parts[2];

    $expectedSignature = hash_hmac('sha256', $header . "." . $payload, $secret, true);
    $expectedSignatureEncoded = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($expectedSignature));

    if (!hash_equals($signature, $expectedSignatureEncoded)) {
        return false;
    }

    $payloadData = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $payload)), true);

    if (!$payloadData || $payloadData['expires_at'] < time()) {
        return false;
    }

    return $payloadData;
}

/**
 * Log check-in activity for audit purposes
 */
function logCheckinActivity($activity, $data)
{
    $logData = [
        'timestamp' => date('Y-m-d H:i:s'),
        'activity' => $activity,
        'data' => $data,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
    ];

    // In production, this would write to a proper logging system
    error_log("Check-in Activity: " . json_encode($logData));
}

/**
 * Get kiosk configuration
 */
function getKioskConfig($kioskId)
{
    $db = new PDO(
        "pgsql:host=" . getenv('DB_HOST') . ";dbname=" . getenv('DB_DATABASE'),
        getenv('DB_USERNAME'),
        getenv('DB_PASSWORD'),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $stmt = $db->prepare("
        SELECT * FROM self_checkin_kiosks
        WHERE kiosk_id = ?
    ");

    $stmt->execute([$kioskId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Check kiosk availability
 */
function isKioskAvailable($kioskId)
{
    $db = new PDO(
        "pgsql:host=" . getenv('DB_HOST') . ";dbname=" . getenv('DB_DATABASE'),
        getenv('DB_USERNAME'),
        getenv('DB_PASSWORD'),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $stmt = $db->prepare("
        SELECT status, network_status
        FROM self_checkin_kiosks
        WHERE kiosk_id = ?
    ");

    $stmt->execute([$kioskId]);
    $kiosk = $stmt->fetch(PDO::FETCH_ASSOC);

    return $kiosk && $kiosk['status'] === 'active' && $kiosk['network_status'] === 'online';
}

/**
 * Get supported languages for a kiosk
 */
function getKioskLanguages($kioskId)
{
    $kiosk = getKioskConfig($kioskId);
    return $kiosk ? json_decode($kiosk['supported_languages'], true) : ['en'];
}

/**
 * Get kiosk features
 */
function getKioskFeatures($kioskId)
{
    $kiosk = getKioskConfig($kioskId);
    return $kiosk ? json_decode($kiosk['features'], true) : [];
}
