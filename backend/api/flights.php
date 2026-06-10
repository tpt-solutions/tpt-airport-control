<?php
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/../src/Logger.php';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        exit(0);
    }

    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../src/Middleware.php';
    require_once __DIR__ . '/../controllers/FlightController.php';

    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? 'list';

    // Initialize controller
    $controller = new FlightController($pdo);

    // Handle request based on method
    $data = null;
    if (in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'])) {
        $data = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON']);
            exit;
        }
    }

    $result = $controller->handleRequest($method, $action, $data);
    echo json_encode($result);

} catch (Exception $e) {
    Logger::error('Flights API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}


?>
