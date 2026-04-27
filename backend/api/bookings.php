<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/BookingController.php';

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $request = $_SERVER['REQUEST_URI'];

    // Remove query string and decode
    $path = parse_url($request, PHP_URL_PATH);
    $path = str_replace('/api/bookings', '', $path);

    $controller = new BookingController($pdo);
    $response = null;

    switch ($method) {
        case 'GET':
            if (!empty($path) && $path !== '/') {
                $pathParts = explode('/', trim($path, '/'));
                $firstPart = $pathParts[0];

                // Handle different GET endpoints
                if ($firstPart === 'search') {
                    $response = $controller->searchBookings();
                } elseif ($firstPart === 'stats') {
                    $response = $controller->getStatistics();
                } elseif ($firstPart === 'revenue') {
                    $response = $controller->getRevenueReport();
                } elseif ($firstPart === 'reference' && isset($pathParts[1])) {
                    $response = $controller->getBookingByReference($pathParts[1]);
                } elseif ($firstPart === 'passenger' && isset($pathParts[1])) {
                    $response = $controller->getBookingsByPassenger($pathParts[1]);
                } else {
                    // Regular booking by ID
                    $response = $controller->getBooking($firstPart);
                }
            } else {
                $response = $controller->getBookings();
            }
            break;

        case 'POST':
            $response = $controller->createBooking();
            break;

        case 'PUT':
            if (!empty($path) && $path !== '/') {
                $bookingId = trim($path, '/');
                $response = $controller->updateBooking($bookingId);
            } else {
                http_response_code(400);
                $response = ['error' => 'Booking ID required for update'];
            }
            break;

        case 'DELETE':
            if (!empty($path) && $path !== '/') {
                $bookingId = trim($path, '/');
                $response = $controller->cancelBooking($bookingId);
            } else {
                http_response_code(400);
                $response = ['error' => 'Booking ID required for cancellation'];
            }
            break;

        default:
            http_response_code(405);
            $response = ['error' => 'Method not allowed'];
            break;
    }

    echo json_encode($response);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error', 'message' => $e->getMessage()]);
}
?>
