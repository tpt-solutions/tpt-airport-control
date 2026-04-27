<?php
/**
 * Airline API Connector Endpoint
 *
 * Handles flight searches, bookings, and airline data integration
 */

require_once '../config/database.php';
require_once '../src/Logger.php';
require_once '../src/Middleware.php';
require_once '../../integrations/airline-api-connector.php';

// Initialize components
$db = getDatabaseConnection();
$logger = new Logger($db);
$middleware = new Middleware($db, $logger);

// API keys configuration (in production, these should be in environment variables)
$apiKeys = [
    'amadeus' => [
        'client_id' => getenv('AMADEUS_CLIENT_ID') ?: 'your_amadeus_client_id',
        'client_secret' => getenv('AMADEUS_CLIENT_SECRET') ?: 'your_amadeus_client_secret'
    ],
    'sabre' => [
        'token' => getenv('SABRE_TOKEN') ?: 'your_sabre_token'
    ]
];

$airlineConnector = new AirlineAPIConnector($db, $logger, $apiKeys);

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
$path = str_replace('/backend/api/airline-api', '', $path);
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
            handleGetRequest($resource, $id, $_GET, $airlineConnector);
            break;

        case 'POST':
            handlePostRequest($resource, $input, $airlineConnector, $middleware);
            break;

        case 'PUT':
            handlePutRequest($resource, $id, $input, $airlineConnector, $middleware);
            break;

        case 'DELETE':
            handleDeleteRequest($resource, $id, $airlineConnector, $middleware);
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    $logger->error('Airline API error', ['error' => $e->getMessage()]);
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

/**
 * Handle GET requests
 */
function handleGetRequest($resource, $id, $queryParams, $connector)
{
    switch ($resource) {
        case null:
            // Get airline information
            if (isset($queryParams['airline_code'])) {
                $airline = $connector->getAirlineInfo($queryParams['airline_code']);
                if ($airline) {
                    echo json_encode(['success' => true, 'data' => $airline]);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Airline not found']);
                }
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Airline code required']);
            }
            break;

        case 'search':
            // Search flights
            if (isset($queryParams['origin']) && isset($queryParams['destination']) &&
                isset($queryParams['departure_date'])) {

                $searchParams = [
                    'origin' => $queryParams['origin'],
                    'destination' => $queryParams['destination'],
                    'departure_date' => $queryParams['departure_date'],
                    'return_date' => $queryParams['return_date'] ?? null,
                    'passengers' => (int)($queryParams['passengers'] ?? 1),
                    'provider' => $queryParams['provider'] ?? 'auto'
                ];

                $results = $connector->searchFlights($searchParams);
                echo json_encode(['success' => true, 'data' => $results]);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Origin, destination, and departure date required']);
            }
            break;

        case 'schedule':
            // Get flight schedule
            if (isset($queryParams['airline']) && isset($queryParams['flight_number']) &&
                isset($queryParams['date'])) {

                $schedule = $connector->getFlightSchedule(
                    $queryParams['airline'],
                    $queryParams['flight_number'],
                    $queryParams['date']
                );

                if ($schedule) {
                    echo json_encode(['success' => true, 'data' => $schedule]);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Flight schedule not found']);
                }
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Airline, flight number, and date required']);
            }
            break;

        case 'bookings':
            if ($id) {
                // Get specific booking
                $booking = getBookingById($id, $connector);
                if ($booking) {
                    echo json_encode(['success' => true, 'data' => $booking]);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Booking not found']);
                }
            } else {
                // Get user's bookings (requires authentication)
                handleUserBookings($queryParams, $connector, $GLOBALS['middleware']);
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
function handlePostRequest($resource, $input, $connector, $middleware)
{
    switch ($resource) {
        case 'search':
            // Search flights
            $results = $connector->searchFlights($input);
            echo json_encode(['success' => true, 'data' => $results]);
            break;

        case 'bookings':
            // Create booking
            // Require authentication for booking
            $user = $middleware->authenticate();
            if (!$user) {
                http_response_code(401);
                echo json_encode(['error' => 'Authentication required']);
                return;
            }

            $result = $connector->createBooking($input);
            if ($result['success']) {
                http_response_code(201);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode($result);
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
function handlePutRequest($resource, $id, $input, $connector, $middleware)
{
    // Require authentication
    $user = $middleware->authenticate();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        return;
    }

    switch ($resource) {
        case 'bookings':
            if ($id && isset($input['action'])) {
                if ($input['action'] === 'cancel') {
                    $result = cancelBooking($id, $connector);
                    echo json_encode($result);
                } else {
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid action']);
                }
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Booking ID and action required']);
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
function handleDeleteRequest($resource, $id, $connector, $middleware)
{
    // Require authentication
    $user = $middleware->authenticate();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        return;
    }

    switch ($resource) {
        case 'bookings':
            if ($id) {
                $result = cancelBooking($id, $connector);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Booking ID required']);
            }
            break;

        default:
            http_response_code(404);
            echo json_encode(['error' => 'Resource not found']);
    }
}

/**
 * Get booking by ID
 */
function getBookingById($bookingId, $connector)
{
    $db = $GLOBALS['db'];
    $stmt = $db->prepare("SELECT * FROM airline_bookings WHERE booking_reference = ?");
    $stmt->execute([$bookingId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Handle user bookings
 */
function handleUserBookings($queryParams, $connector, $middleware)
{
    $user = $middleware->authenticate();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        return;
    }

    $db = $GLOBALS['db'];
    $stmt = $db->prepare("
        SELECT * FROM airline_bookings
        WHERE passenger_data::text LIKE ?
        ORDER BY created_at DESC
        LIMIT 50
    ");
    $stmt->execute(['%' . $user['email'] . '%']);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $bookings]);
}

/**
 * Cancel booking
 */
function cancelBooking($bookingId, $connector)
{
    $db = $GLOBALS['db'];

    try {
        $stmt = $db->prepare("
            UPDATE airline_bookings
            SET status = 'cancelled', updated_at = CURRENT_TIMESTAMP
            WHERE booking_reference = ?
        ");
        $stmt->execute([$bookingId]);

        $GLOBALS['logger']->info("Booking cancelled", ['booking_id' => $bookingId]);
        return ['success' => true, 'message' => 'Booking cancelled successfully'];

    } catch (Exception $e) {
        $GLOBALS['logger']->error("Failed to cancel booking", ['error' => $e->getMessage()]);
        return ['success' => false, 'error' => $e->getMessage()];
    }
}
