<?php
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/../src/Logger.php';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        exit(0);
    }

    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../src/Middleware.php';

    // All RBAC operations require admin access
    Middleware::authenticate();
    Middleware::checkRole('admin');

    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';

    switch ($method) {
        case 'GET':
            handleGet($action);
            break;
        case 'POST':
            handlePost($action);
            break;
        case 'PUT':
            handlePut($action);
            break;
        case 'DELETE':
            handleDelete($action);
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    Logger::error('RBAC API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

function handleGet($action) {
    global $pdo;

    try {
        switch ($action) {
            case 'roles':
                $roles = RBAC::getRoles();
                echo json_encode(['roles' => $roles]);
                break;

            case 'modules':
                $modules = RBAC::getModules();
                echo json_encode(['modules' => $modules]);
                break;

            case 'user_permissions':
                $userId = $_GET['user_id'] ?? null;
                if (!$userId) {
                    http_response_code(400);
                    echo json_encode(['error' => 'User ID required']);
                    return;
                }
                $permissions = RBAC::getUserPermissions($userId);
                echo json_encode(['permissions' => $permissions]);
                break;

            default:
                http_response_code(400);
                echo json_encode(['error' => 'Invalid action']);
                break;
        }
    } catch (Exception $e) {
        Logger::error('RBAC GET error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to retrieve data']);
    }
}

function handlePost($action) {
    global $pdo;
    $input = json_decode(file_get_contents('php://input'), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        return;
    }

    try {
        switch ($action) {
            case 'assign_permission':
                Middleware::validateInput($input, ['user_id', 'permission']);
                $moduleId = $input['module_id'] ?? null;

                $success = RBAC::assignPermission($input['user_id'], $input['permission'], $moduleId);
                if ($success) {
                    echo json_encode(['message' => 'Permission assigned successfully']);
                } else {
                    http_response_code(500);
                    echo json_encode(['error' => 'Failed to assign permission']);
                }
                break;

            case 'create_role':
                Middleware::validateInput($input, ['name', 'description']);
                $defaultPermissions = $input['default_permissions'] ?? [];

                $roleId = RBAC::createRole($input['name'], $input['description'], $defaultPermissions);
                if ($roleId) {
                    echo json_encode(['message' => 'Role created successfully', 'role_id' => $roleId]);
                } else {
                    http_response_code(500);
                    echo json_encode(['error' => 'Failed to create role']);
                }
                break;

            default:
                http_response_code(400);
                echo json_encode(['error' => 'Invalid action']);
                break;
        }
    } catch (Exception $e) {
        Logger::error('RBAC POST error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to process request']);
    }
}

function handlePut($action) {
    // For updating roles, modules, etc.
    http_response_code(501);
    echo json_encode(['error' => 'Not implemented']);
}

function handleDelete($action) {
    global $pdo;
    $input = json_decode(file_get_contents('php://input'), true);

    try {
        switch ($action) {
            case 'remove_permission':
                Middleware::validateInput($input, ['user_id', 'permission']);
                $moduleId = $input['module_id'] ?? null;

                $success = RBAC::removePermission($input['user_id'], $input['permission'], $moduleId);
                if ($success) {
                    echo json_encode(['message' => 'Permission removed successfully']);
                } else {
                    http_response_code(500);
                    echo json_encode(['error' => 'Failed to remove permission']);
                }
                break;

            default:
                http_response_code(400);
                echo json_encode(['error' => 'Invalid action']);
                break;
        }
    } catch (Exception $e) {
        Logger::error('RBAC DELETE error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to process request']);
    }
}
?>
