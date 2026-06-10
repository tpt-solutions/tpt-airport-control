<?php

/**
 * Module Management API
 *
 * RESTful API for managing system modules
 * Supports enabling/disabling modules, configuration, and monitoring
 */

require_once '../src/ApiResponse.php';
require_once '../models/Module.php';
require_once '../src/Auth.php';

// Initialize components
$apiResponse = new ApiResponse();
$moduleManager = new Module();
$auth = new Auth();

// Get request method and path
$method = $_SERVER['REQUEST_METHOD'];
$request = $_SERVER['REQUEST_URI'];
$path = parse_url($request, PHP_URL_PATH);

// Remove base path
$path = str_replace('/api/modules', '', $path);
$path = str_replace('/backend/api/modules', '', $path);

// Get path segments
$pathSegments = array_filter(explode('/', trim($path, '/')));
$moduleId = $pathSegments[0] ?? null;
$action = $pathSegments[1] ?? null;

// Get user from JWT token
$user = null;
$headers = getallheaders();
if (isset($headers['Authorization'])) {
    $token = str_replace('Bearer ', '', $headers['Authorization']);
    try {
        $user = $auth->validateToken($token);
    } catch (Exception $e) {
        $apiResponse->error('Unauthorized', 401);
        exit;
    }
}

try {
    switch ($method) {
        case 'GET':
            handleGetRequest($moduleId, $action, $moduleManager, $apiResponse);
            break;

        case 'POST':
            handlePostRequest($moduleId, $action, $moduleManager, $user, $apiResponse);
            break;

        case 'PUT':
            handlePutRequest($moduleId, $action, $moduleManager, $user, $apiResponse);
            break;

        case 'DELETE':
            handleDeleteRequest($moduleId, $moduleManager, $user, $apiResponse);
            break;

        default:
            $apiResponse->error('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log("Module API Error: " . $e->getMessage());
    error_log('API error: ' . $e->getMessage());
    $apiResponse->error('An internal error occurred', 500);
}

/**
 * Handle GET requests
 */
function handleGetRequest($moduleId, $action, $moduleManager, $apiResponse)
{
    if (!$moduleId) {
        // Get all modules
        $modules = $moduleManager->getAllModules();
        $apiResponse->success($modules);
    } elseif ($moduleId === 'health') {
        // Get system health
        $health = $moduleManager->getSystemHealth();
        $apiResponse->success($health);
    } elseif ($moduleId === 'enabled') {
        // Get enabled modules
        $modules = $moduleManager->getEnabledModules();
        $apiResponse->success($modules);
    } elseif ($moduleId === 'audit') {
        // Get audit log
        $limit = $_GET['limit'] ?? 50;
        $auditLog = $moduleManager->getModuleAuditLog(null, $limit);
        $apiResponse->success($auditLog);
    } elseif (is_numeric($moduleId)) {
        if ($action === 'audit') {
            // Get audit log for specific module
            $limit = $_GET['limit'] ?? 50;
            $auditLog = $moduleManager->getModuleAuditLog($moduleId, $limit);
            $apiResponse->success($auditLog);
        } else {
            // Get specific module
            $module = $moduleManager->getModule($moduleId);
            if (!$module) {
                $apiResponse->error('Module not found', 404);
                return;
            }
            $apiResponse->success($module);
        }
    } else {
        // Get modules by category
        $modules = $moduleManager->getModulesByCategory($moduleId);
        $apiResponse->success($modules);
    }
}

/**
 * Handle POST requests
 */
function handlePostRequest($moduleId, $action, $moduleManager, $user, $apiResponse)
{
    // Check if user has admin permissions
    if (!$user || !in_array($user['role'], ['super_admin', 'admin'])) {
        $apiResponse->error('Insufficient permissions', 403);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (!$moduleId) {
        $apiResponse->error('Module ID required', 400);
        return;
    }

    if ($action === 'enable') {
        // Enable module
        $result = $moduleManager->enableModule($moduleId, $user['user_id']);
        $apiResponse->success($result);
    } elseif ($action === 'disable') {
        // Disable module
        $result = $moduleManager->disableModule($moduleId, $user['user_id']);
        $apiResponse->success($result);
    } elseif ($action === 'health') {
        // Update module health
        $status = $input['status'] ?? 'unknown';
        $responseTime = $input['response_time'] ?? null;
        $errorMessage = $input['error_message'] ?? null;
        $metrics = $input['metrics'] ?? null;

        $moduleManager->updateModuleHealth($moduleId, $status, $responseTime, $errorMessage, $metrics);
        $apiResponse->success(['message' => 'Health updated']);
    } else {
        $apiResponse->error('Invalid action', 400);
    }
}

/**
 * Handle PUT requests
 */
function handlePutRequest($moduleId, $action, $moduleManager, $user, $apiResponse)
{
    // Check if user has admin permissions
    if (!$user || !in_array($user['role'], ['super_admin', 'admin'])) {
        $apiResponse->error('Insufficient permissions', 403);
        return;
    }

    if (!$moduleId || !is_numeric($moduleId)) {
        $apiResponse->error('Valid module ID required', 400);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if ($action === 'config') {
        // Update module configuration
        if (!isset($input['configuration'])) {
            $apiResponse->error('Configuration data required', 400);
            return;
        }

        $result = $moduleManager->updateModuleConfig($moduleId, $input['configuration'], $user['user_id']);
        $apiResponse->success($result);
    } elseif ($action === 'permissions') {
        // Update module permissions
        if (!isset($input['permissions'])) {
            $apiResponse->error('Permissions data required', 400);
            return;
        }

        $result = updateModulePermissions($moduleId, $input['permissions'], $user['user_id']);
        $apiResponse->success($result);
    } elseif ($action === 'feature-flags') {
        // Update feature flags
        if (!isset($input['feature_flags'])) {
            $apiResponse->error('Feature flags data required', 400);
            return;
        }

        $result = updateFeatureFlags($moduleId, $input['feature_flags'], $user['user_id']);
        $apiResponse->success($result);
    } else {
        $apiResponse->error('Invalid action', 400);
    }
}

/**
 * Handle DELETE requests
 */
function handleDeleteRequest($moduleId, $moduleManager, $user, $apiResponse)
{
    // Check if user has super admin permissions
    if (!$user || $user['role'] !== 'super_admin') {
        $apiResponse->error('Super admin permissions required', 403);
        return;
    }

    if (!$moduleId || !is_numeric($moduleId)) {
        $apiResponse->error('Valid module ID required', 400);
        return;
    }

    // Check if module is core
    $module = $moduleManager->getModule($moduleId);
    if ($module && $module['is_core']) {
        $apiResponse->error('Cannot delete core modules', 400);
        return;
    }

    // Note: In a real implementation, you might want to soft delete or archive modules
    // For now, we'll just disable them
    $result = $moduleManager->disableModule($moduleId, $user['user_id']);
    $apiResponse->success($result);
}

/**
 * Update module permissions
 */
function updateModulePermissions($moduleId, $permissions, $userId)
{
    $db = new PDO(
        "pgsql:host=" . getenv('DB_HOST') . ";dbname=" . getenv('DB_DATABASE'),
        getenv('DB_USERNAME'),
        getenv('DB_PASSWORD'),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $db->beginTransaction();

    try {
        // Delete existing permissions
        $stmt = $db->prepare("DELETE FROM module_permissions WHERE module_id = ?");
        $stmt->execute([$moduleId]);

        // Insert new permissions
        $stmt = $db->prepare("
            INSERT INTO module_permissions (module_id, role_name, permission_level)
            VALUES (?, ?, ?)
        ");

        foreach ($permissions as $role => $level) {
            $stmt->execute([$moduleId, $role, $level]);
        }

        // Log the change
        $stmt = $db->prepare("
            INSERT INTO module_audit_log (module_id, action, user_id, new_value, created_at)
            VALUES (?, 'permissions_updated', ?, ?, NOW())
        ");
        $stmt->execute([$moduleId, $userId, json_encode($permissions)]);

        $db->commit();

        return ['status' => 'success', 'message' => 'Permissions updated successfully'];
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

/**
 * Update feature flags
 */
function updateFeatureFlags($moduleId, $featureFlags, $userId)
{
    $db = new PDO(
        "pgsql:host=" . getenv('DB_HOST') . ";dbname=" . getenv('DB_DATABASE'),
        getenv('DB_USERNAME'),
        getenv('DB_PASSWORD'),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $db->beginTransaction();

    try {
        foreach ($featureFlags as $flag) {
            $stmt = $db->prepare("
                INSERT INTO feature_flags (
                    module_id, flag_name, display_name, description,
                    is_enabled, rollout_percentage, conditions
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
                ON CONFLICT (module_id, flag_name) DO UPDATE SET
                    display_name = EXCLUDED.display_name,
                    description = EXCLUDED.description,
                    is_enabled = EXCLUDED.is_enabled,
                    rollout_percentage = EXCLUDED.rollout_percentage,
                    conditions = EXCLUDED.conditions,
                    updated_at = NOW()
            ");

            $stmt->execute([
                $moduleId,
                $flag['flag_name'],
                $flag['display_name'] ?? $flag['flag_name'],
                $flag['description'] ?? '',
                $flag['is_enabled'] ?? false,
                $flag['rollout_percentage'] ?? 100,
                isset($flag['conditions']) ? json_encode($flag['conditions']) : '{}'
            ]);
        }

        // Log the change
        $stmt = $db->prepare("
            INSERT INTO module_audit_log (module_id, action, user_id, new_value, created_at)
            VALUES (?, 'feature_flags_updated', ?, ?, NOW())
        ");
        $stmt->execute([$moduleId, $userId, json_encode($featureFlags)]);

        $db->commit();

        return ['status' => 'success', 'message' => 'Feature flags updated successfully'];
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

/**
 * Check if module is enabled (utility function)
 */
function isModuleEnabled($moduleName)
{
    static $moduleManager = null;

    if (!$moduleManager) {
        $moduleManager = new Module();
    }

    return $moduleManager->isModuleEnabled($moduleName);
}

/**
 * Get enabled modules list (utility function)
 */
function getEnabledModules()
{
    static $moduleManager = null;

    if (!$moduleManager) {
        $moduleManager = new Module();
    }

    return $moduleManager->getEnabledModules();
}
