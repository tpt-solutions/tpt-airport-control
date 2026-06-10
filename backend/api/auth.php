<?php
declare(strict_types=1);
require_once __DIR__ . '/cors.php';

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../src/Auth.php';

use TPT\FlightControl\Logger;

$action = $_GET['action'] ?? '';

/**
 * Set (or clear) the httpOnly JWT cookie.
 * Passing an empty string expires the cookie immediately.
 */
function setJwtCookie(string $token): void {
    $secure   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
             || (string)($_SERVER['SERVER_PORT'] ?? '80') === '443';
    $lifetime = (int)(getenv('JWT_LIFETIME') ?: 3600);
    setcookie('jwt_token', $token, [
        'expires'  => $token === '' ? 1 : time() + $lifetime,
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}


try {
    Logger::debug('Auth API request received', ['action' => $action, 'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown']);
    
    // Verify PDO is available

    if (!class_exists('PDO')) {
        throw new Exception('PDO extension is not installed on this server');
    }

    $db = null;
    
    // First try SQLite demo database (zero setup demo mode)
    $sqlitePath = __DIR__ . '/../../database/flight_control_demo.db';
    
    if (file_exists($sqlitePath) && in_array('sqlite', PDO::getAvailableDrivers())) {
        try {
            $db = new PDO('sqlite:' . $sqlitePath);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (Exception $sqliteEx) {
            // SQLite failed, continue to PostgreSQL
            $db = null;
        }
    }
    
    // Fallback to PostgreSQL connection
    if (!$db && in_array('pgsql', PDO::getAvailableDrivers())) {
        $host = getenv('DB_HOST') ?: 'localhost';
        $dbname = getenv('DB_NAME') ?: 'flight_control';
        $username = getenv('DB_USERNAME') ?: 'flight_user';
        $password = getenv('DB_PASSWORD') ?: 'flight_pass_2025';

        $db = new PDO("pgsql:host=$host;dbname=$dbname", $username, $password);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    
    if (!$db) {
        Logger::error('No database connection available');
        throw new Exception('No working database connection available. Please install either PDO SQLite or PDO PostgreSQL driver.');
    }
    
    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
    Logger::info('Database connected', ['driver' => $driver]);

    // In demo mode (SQLite with no JWT_SECRET set) generate a stable per-installation
    // secret so demos work out-of-the-box. This secret changes when the SQLite file
    // changes on disk, so it is NOT the same known string for every deployment.
    if ($driver === 'sqlite' && !getenv('JWT_SECRET')) {
        $demoSecret = hash('sha256', __DIR__ . '|' . filemtime($sqlitePath));
        putenv('JWT_SECRET=' . $demoSecret);
    }

    Auth::init($db);

    
    switch ($action) {
        case 'login':
            $data = json_decode(file_get_contents('php://input'), true);
            Logger::debug('Login attempt received', ['username' => $data['username'] ?? 'not_provided']);
            
            if (!isset($data['username']) || !isset($data['password'])) {
                Logger::warning('Login request missing credentials');
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Username and password are required'
                ]);
                exit;
            }
            
            // Default admin credential — configurable via DEFAULT_ADMIN_PASSWORD env var.
            // If the default value is still in use, we set password_change_required so the
            // UI can prompt the user to change it before continuing.
            $defaultAdminPassword = getenv('DEFAULT_ADMIN_PASSWORD') ?: 'FlightControl@2026!';
            $usingDefaultPassword = false;

            if ($data['username'] === 'admin' && $data['password'] === $defaultAdminPassword) {
                $usingDefaultPassword = ($data['password'] === 'FlightControl@2026!' && !getenv('DEFAULT_ADMIN_PASSWORD'));
                if ($usingDefaultPassword) {
                    error_log('SECURITY WARNING: Admin logged in with default password. Change DEFAULT_ADMIN_PASSWORD before going live.');
                    Logger::warning('Admin login with default password — password_change_required', ['username' => $data['username']]);
                }
                Logger::info('Default admin login', ['username' => $data['username']]);
                $user = [
                    'id' => 1,
                    'username' => 'admin',
                    'email' => 'admin@tptflightcontrol.com',
                    'first_name' => 'System',
                    'last_name' => 'Administrator',
                    'role_name' => 'admin'
                ];
            } else {
                $user = Auth::authenticate($data['username'], $data['password']);
            }

            if ($user) {
                $token = Auth::generateToken($user['id'], $user['username'], $user['role_name']);
                Logger::info('Login successful', ['username' => $user['username'], 'role' => $user['role_name']]);
                $result = [
                    'success' => true,
                    'token' => $token,
                    'password_change_required' => $usingDefaultPassword,
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
                Logger::info('Login failed', ['username' => $data['username']]);
                $result = [
                    'success' => false,
                    'message' => 'Invalid username or password'
                ];
            }
            
            Logger::debug('Login response', ['success' => $result['success']]);
            if ($result['success']) {
                setJwtCookie($token);
                echo json_encode($result);
            } else {
                http_response_code(401);
                echo json_encode($result);
            }
            break;

            
        case 'validate':
            $validateToken = null;
            $valHeaders = getallheaders();
            $valAuth = $valHeaders['Authorization'] ?? '';
            if (str_starts_with($valAuth, 'Bearer ')) {
                $validateToken = substr($valAuth, 7);
            } elseif (!empty($_COOKIE['jwt_token'])) {
                $validateToken = $_COOKIE['jwt_token'];
            }

            if (!$validateToken) {
                http_response_code(401);
                echo json_encode(['valid' => false]);
                exit;
            }

            $payload = Auth::validateToken($validateToken);
            if ($payload && is_array($payload)) {
                echo json_encode([
                    'valid'  => true,
                    'token'  => $validateToken,
                    'user'   => [
                        'id'         => $payload['user_id'] ?? 0,
                        'username'   => $payload['username'] ?? '',
                        'email'      => '',
                        'first_name' => $payload['username'] ?? '',
                        'last_name'  => '',
                        'role_name'  => $payload['role'] ?? '',
                    ],
                ]);
            } else {
                http_response_code(401);
                echo json_encode(['valid' => false]);
            }
            break;

        case 'logout':
            setJwtCookie('');
            echo json_encode(['success' => true]);
            break;

        case 'refresh':
            $refreshToken = null;
            $refHeaders = getallheaders();
            $refAuth = $refHeaders['Authorization'] ?? '';
            if (str_starts_with($refAuth, 'Bearer ')) {
                $refreshToken = substr($refAuth, 7);
            } elseif (!empty($_COOKIE['jwt_token'])) {
                $refreshToken = $_COOKIE['jwt_token'];
            }

            if (!$refreshToken) {
                http_response_code(401);
                echo json_encode(['success' => false]);
                exit;
            }

            $result = Auth::refreshAccessToken($refreshToken);

            if ($result) {
                setJwtCookie($result['access_token']);
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
    Logger::error('Auth API exception', ['message' => \$e->getMessage(), 'trace' => $e->getTraceAsString()]);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An internal error occurred. Please try again.'
    ]);
}
