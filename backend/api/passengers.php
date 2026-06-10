<?php
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Middleware.php';
require_once __DIR__ . '/../controllers/PassengerController.php';

Middleware::authenticate();

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $request = $_SERVER['REQUEST_URI'];

    // Remove query string and decode
    $path = parse_url($request, PHP_URL_PATH);
    $path = str_replace('/api/passengers', '', $path);

    $controller = new PassengerController($pdo);
    $response = null;

    switch ($method) {
        case 'GET':
            if (!empty($path) && $path !== '/') {
                $pathParts = explode('/', trim($path, '/'));
                $firstPart = $pathParts[0];

                // Handle different GET endpoints
                if ($firstPart === 'search') {
                    $response = $controller->searchPassengers();
                } elseif ($firstPart === 'stats') {
                    $response = $controller->getStatistics();
                } elseif ($firstPart === 'nationalities') {
                    $response = $controller->getNationalityDistribution();
                } elseif ($firstPart === 'profile' && isset($pathParts[1])) {
                    $response = $controller->getPassengerProfile($pathParts[1]);
                } elseif ($firstPart === 'email' && isset($pathParts[1])) {
                    $response = $controller->getPassengerByEmail(urldecode($pathParts[1]));
                } elseif ($firstPart === 'passport' && isset($pathParts[1])) {
                    $response = $controller->getPassengerByPassport($pathParts[1]);
                } elseif ($firstPart === 'flight' && isset($pathParts[1])) {
                    $response = $controller->getPassengersByFlight($pathParts[1]);
                } else {
                    // Regular passenger by ID
                    $response = $controller->getPassenger($firstPart);
                }
            } else {
                $response = $controller->getPassengers();
            }
            break;

        case 'POST':
            $response = $controller->createPassenger();
            break;

        case 'PUT':
            if (!empty($path) && $path !== '/') {
                $passengerId = trim($path, '/');
                $response = $controller->updatePassenger($passengerId);
            } else {
                http_response_code(400);
                $response = ['error' => 'Passenger ID required for update'];
            }
            break;

        case 'DELETE':
            if (!empty($path) && $path !== '/') {
                $passengerId = trim($path, '/');
                $response = $controller->deletePassenger($passengerId);
            } else {
                http_response_code(400);
                $response = ['error' => 'Passenger ID required for deletion'];
            }
            break;

        default:
            http_response_code(405);
            $response = ['error' => 'Method not allowed'];
            break;
    }

    echo json_encode($response);
} catch (Exception $e) {
    error_log('passengers.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?>
