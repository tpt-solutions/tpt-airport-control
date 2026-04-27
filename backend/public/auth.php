<?php
declare(strict_types=1);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../config/Database.php';

$action = $_GET['action'] ?? '';

try {
    $db = new PDO('sqlite:' . __DIR__ . '/../../database/flight_control_demo.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    Auth::init($db);
    
    switch ($action) {
        case 'login':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['username']) || !isset($data['password'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Username and password are required'
                ]);
                exit;
            }
            
            $user = Auth::authenticate($data['username'], $data['password']);
            
            if ($user) {
                $token = Auth::generateToken($user['id'], $user['username'], $user['role_name']);
                $result = [
                    'success' => true,
                    'token' => $token,
                    'user' => [
                        'id' => $user['id'],
                        'username' => $user['username'],
                        'email' => $user['email'],
                        'first_name' => $user['first_name'],
                        'last_name' => $user['last_name'],
                        'role_name' => $user['role_name']
                    ]
                ];
            } else {
                $result = [
                    'success' => false,
                    'message' => 'Invalid username or password'
                ];
            }
            
            if ($result['success']) {
                echo json_encode($result);
            } else {
                http_response_code(401);
                echo json_encode($result);
            }
            break;
            
        case 'validate':
            $headers = getallheaders();
            $authHeader = $headers['Authorization'] ?? '';
            
            if (!str_starts_with($authHeader, 'Bearer ')) {
                http_response_code(401);
                echo json_encode(['valid' => false]);
                exit;
            }
            
            $token = substr($authHeader, 7);
            $valid = Auth::validateToken($token);
            
            echo json_encode(['valid' => $valid !== false]);
            break;
            
        case 'refresh':
            $headers = getallheaders();
            $authHeader = $headers['Authorization'] ?? '';
            
            if (!str_starts_with($authHeader, 'Bearer ')) {
                http_response_code(401);
                echo json_encode(['success' => false]);
                exit;
            }
            
            $token = substr($authHeader, 7);
            $result = Auth::refreshAccessToken($token);
            
            if ($result) {
                echo json_encode([
                    'success' => true,
                    'token' => $result['access_token']
                ]);
            } else {
                http_response_code(401);
                echo json_encode(['success' => false]);
            }
            break;
            
        case 'register':
            http_response_code(501);
            echo json_encode([
                'success' => false,
                'message' => 'Registration is disabled in demo mode'
            ]);
            break;
            
        default:
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid action'
            ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}