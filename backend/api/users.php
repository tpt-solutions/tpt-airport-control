<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/UserController.php';

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $request = $_SERVER['REQUEST_URI'];

    // Remove query string and decode
    $path = parse_url($request, PHP_URL_PATH);
    $path = str_replace('/api/users', '', $path);

    $controller = new UserController($pdo);
    $response = null;

    switch ($method) {
        case 'GET':
            if (!empty($path) && $path !== '/') {
                $pathParts = explode('/', trim($path, '/'));
                $firstPart = $pathParts[0];

                // Handle different GET endpoints
                if ($firstPart === 'search') {
                    $response = $controller->searchUsers();
                } elseif ($firstPart === 'stats') {
                    $response = $controller->getStatistics();
                } elseif ($firstPart === 'roles') {
                    $response = $controller->getRoleDistribution();
                } elseif ($firstPart === 'profile' && isset($pathParts[1])) {
                    $response = $controller->getUserProfile($pathParts[1]);
                } elseif ($firstPart === 'role' && isset($pathParts[1])) {
                    $response = $controller->getUsersByRole($pathParts[1]);
                } else {
                    // Regular user by ID
                    $response = $controller->getUser($firstPart);
                }
            } else {
                $response = $controller->getUsers();
            }
            break;

        case 'POST':
            if (!empty($path) && $path === '/auth') {
                $response = $controller->authenticateUser();
            } else {
                $response = $controller->createUser();
            }
            break;

        case 'PUT':
            if (!empty($path) && $path !== '/') {
                $pathParts = explode('/', trim($path, '/'));
                $firstPart = $pathParts[0];

                if ($firstPart === 'profile') {
                    $response = $controller->updateProfile();
                } elseif ($firstPart === 'password' && isset($pathParts[1])) {
                    $response = $controller->updatePassword($pathParts[1]);
                } elseif ($firstPart === 'deactivate' && isset($pathParts[1])) {
                    $response = $controller->deactivateUser($pathParts[1]);
                } elseif ($firstPart === 'activate' && isset($pathParts[1])) {
                    $response = $controller->activateUser($pathParts[1]);
                } else {
                    // Regular user update
                    $response = $controller->updateUser($firstPart);
                }
            } else {
                $response = $controller->updateProfile();
            }
            break;

        case 'DELETE':
            if (!empty($path) && $path !== '/') {
                $userId = trim($path, '/');
                $response = $controller->deleteUser($userId);
            } else {
                http_response_code(400);
                $response = ['error' => 'User ID required for deletion'];
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
