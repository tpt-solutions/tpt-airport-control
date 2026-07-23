<?php

/**
 * Module Model
 *
 * Represents a system module that can be enabled/disabled
 * Handles module configuration, dependencies, and permissions
 */

class Module
{
    private $db;
    private $logger;

    // Module status constants
    const STATUS_ENABLED = 'enabled';
    const STATUS_DISABLED = 'disabled';
    const STATUS_ERROR = 'error';

    // Module categories
    const CATEGORY_OPERATIONS = 'operations';
    const CATEGORY_PASSENGER = 'passenger';
    const CATEGORY_INFRASTRUCTURE = 'infrastructure';
    const CATEGORY_SECURITY = 'security';
    const CATEGORY_COMMERCIAL = 'commercial';

    public function __construct(?PDO $db = null)
    {
        if ($db !== null) {
            $this->db = $db;
        } else {
            $this->db = new PDO(
                "pgsql:host=" . getenv('DB_HOST') . ";dbname=" . getenv('DB_DATABASE'),
                getenv('DB_USERNAME'),
                getenv('DB_PASSWORD'),
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        }
        // Logger is optional; omit when running unit tests without full stack
        $this->logger = null;
    }

    /**
     * Get all modules with their current status
     */
    public function getAllModules()
    {
        $stmt = $this->db->prepare("
            SELECT
                m.*,
                mh.status as health_status,
                mh.last_check,
                mh.response_time
            FROM modules m
            LEFT JOIN module_health mh ON m.module_id = mh.module_id
            ORDER BY m.category, m.display_name
        ");

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get a specific module by ID or name
     */
    public function getModule($moduleId)
    {
        $stmt = $this->db->prepare("
            SELECT
                m.*,
                mh.status as health_status,
                mh.last_check,
                mh.response_time,
                mh.error_message,
                mh.metrics
            FROM modules m
            LEFT JOIN module_health mh ON m.module_id = mh.module_id
            WHERE m.module_id = ? OR m.module_name = ?
        ");

        $stmt->execute([$moduleId, $moduleId]);
        $module = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($module) {
            $module['permissions'] = $this->getModulePermissions($module['module_id']);
            $module['feature_flags'] = $this->getModuleFeatureFlags($module['module_id']);
        }

        return $module;
    }

    /**
     * Enable a module
     */
    public function enableModule($moduleId, $userId = null)
    {
        $module = $this->getModule($moduleId);

        if (!$module) {
            throw new Exception("Module not found: $moduleId");
        }

        if ($module['is_core']) {
            throw new Exception("Core modules cannot be disabled");
        }

        // Check dependencies
        if (!empty($module['dependencies'])) {
            $this->checkDependencies($module['dependencies']);
        }

        // Enable the module
        $stmt = $this->db->prepare("
            UPDATE modules
            SET is_enabled = true, updated_at = NOW()
            WHERE module_id = ?
        ");

        $stmt->execute([$module['module_id']]);

        // Log the action
        $this->logModuleAction($module['module_id'], 'enabled', $userId);

        // Initialize module health
        $this->initializeModuleHealth($module['module_id']);

        if ($this->logger) {
            $this->logger->info("Module enabled", [
                'module_id' => $module['module_id'],
                'module_name' => $module['module_name'],
                'user_id' => $userId
            ]);
        }

        return ['status' => 'success', 'message' => 'Module enabled successfully'];
    }

    /**
     * Disable a module
     */
    public function disableModule($moduleId, $userId = null)
    {
        $module = $this->getModule($moduleId);

        if (!$module) {
            throw new Exception("Module not found: $moduleId");
        }

        if ($module['is_core']) {
            throw new Exception("Core modules cannot be disabled");
        }

        // Check if other modules depend on this one
        $dependents = $this->getDependentModules($module['module_name']);
        if (!empty($dependents)) {
            throw new Exception("Cannot disable module: other modules depend on it");
        }

        // Disable the module
        $stmt = $this->db->prepare("
            UPDATE modules
            SET is_enabled = false, updated_at = NOW()
            WHERE module_id = ?
        ");

        $stmt->execute([$module['module_id']]);

        // Log the action
        $this->logModuleAction($module['module_id'], 'disabled', $userId);

        if ($this->logger) {
            $this->logger->info("Module disabled", [
                'module_id' => $module['module_id'],
                'module_name' => $module['module_name'],
                'user_id' => $userId
            ]);
        }

        return ['status' => 'success', 'message' => 'Module disabled successfully'];
    }

    /**
     * Update module configuration
     */
    public function updateModuleConfig($moduleId, $config, $userId = null)
    {
        $module = $this->getModule($moduleId);

        if (!$module) {
            throw new Exception("Module not found: $moduleId");
        }

        // Validate configuration if schema exists
        if ($module['config_schema']) {
            $this->validateConfiguration($config, $module['config_schema']);
        }

        $stmt = $this->db->prepare("
            UPDATE modules
            SET configuration = ?, updated_at = NOW()
            WHERE module_id = ?
        ");

        $stmt->execute([json_encode($config), $module['module_id']]);

        // Log the action
        $this->logModuleAction($module['module_id'], 'configured', $userId, $config);

        if ($this->logger) {
            $this->logger->info("Module configuration updated", [
                'module_id' => $module['module_id'],
                'module_name' => $module['module_name'],
                'user_id' => $userId
            ]);
        }

        return ['status' => 'success', 'message' => 'Module configuration updated'];
    }

    /**
     * Get module permissions for different user roles
     */
    private function getModulePermissions($moduleId)
    {
        $stmt = $this->db->prepare("
            SELECT role_name, permission_level
            FROM module_permissions
            WHERE module_id = ?
        ");

        $stmt->execute([$moduleId]);
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    /**
     * Get module feature flags
     */
    private function getModuleFeatureFlags($moduleId)
    {
        $stmt = $this->db->prepare("
            SELECT flag_name, is_enabled, rollout_percentage, description
            FROM feature_flags
            WHERE module_id = ?
            ORDER BY flag_name
        ");

        $stmt->execute([$moduleId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Check if all dependencies are enabled
     */
    private function checkDependencies($dependencies)
    {
        $placeholders = str_repeat('?,', count($dependencies) - 1) . '?';

        $stmt = $this->db->prepare("
            SELECT module_name
            FROM modules
            WHERE module_name IN ($placeholders)
            AND is_enabled = false
        ");

        $stmt->execute($dependencies);
        $disabledDeps = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($disabledDeps)) {
            throw new Exception("Dependencies not satisfied: " . implode(', ', $disabledDeps));
        }
    }

    /**
     * Get modules that depend on the given module
     */
    private function getDependentModules($moduleName)
    {
        $stmt = $this->db->prepare("
            SELECT module_name
            FROM modules
            WHERE dependencies::text LIKE ?
            AND is_enabled = true
        ");

        $stmt->execute(['%' . $moduleName . '%']);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Validate configuration against JSON schema
     */
    private function validateConfiguration($config, $schema)
    {
        // Basic validation - in production, use a proper JSON schema validator
        $schemaObj = json_decode($schema, true);

        if (!$schemaObj) {
            throw new Exception("Invalid configuration schema");
        }

        // Simple validation for required fields
        if (isset($schemaObj['required'])) {
            foreach ($schemaObj['required'] as $requiredField) {
                if (!isset($config[$requiredField])) {
                    throw new Exception("Required configuration field missing: $requiredField");
                }
            }
        }

        return true;
    }

    /**
     * Log module actions to audit trail
     */
    private function logModuleAction($moduleId, $action, $userId = null, $newValue = null)
    {
        $stmt = $this->db->prepare("
            INSERT INTO module_audit_log (
                module_id, action, user_id, new_value, created_at
            ) VALUES (?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $moduleId,
            $action,
            $userId,
            $newValue ? json_encode($newValue) : null
        ]);
    }

    /**
     * Initialize module health monitoring
     */
    private function initializeModuleHealth($moduleId)
    {
        $stmt = $this->db->prepare("
            INSERT INTO module_health (module_id, status, last_check)
            VALUES (?, 'unknown', NOW())
            ON CONFLICT (module_id) DO NOTHING
        ");

        $stmt->execute([$moduleId]);
    }

    /**
     * Update module health status
     */
    public function updateModuleHealth($moduleId, $status, $responseTime = null, $errorMessage = null, $metrics = null)
    {
        $stmt = $this->db->prepare("
            INSERT INTO module_health (
                module_id, status, last_check, response_time, error_message, metrics
            ) VALUES (?, ?, NOW(), ?, ?, ?)
            ON CONFLICT (module_id) DO UPDATE SET
                status = EXCLUDED.status,
                last_check = EXCLUDED.last_check,
                response_time = EXCLUDED.response_time,
                error_message = EXCLUDED.error_message,
                metrics = EXCLUDED.metrics
        ");

        $stmt->execute([
            $moduleId,
            $status,
            $responseTime,
            $errorMessage,
            $metrics ? json_encode($metrics) : null
        ]);
    }

    /**
     * Get modules by category
     */
    public function getModulesByCategory($category)
    {
        $stmt = $this->db->prepare("
            SELECT * FROM modules
            WHERE category = ?
            ORDER BY display_name
        ");

        $stmt->execute([$category]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get enabled modules
     */
    public function getEnabledModules()
    {
        $stmt = $this->db->prepare("
            SELECT * FROM modules
            WHERE is_enabled = true
            ORDER BY category, display_name
        ");

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Check if a module is enabled
     */
    public function isModuleEnabled($moduleName)
    {
        $stmt = $this->db->prepare("
            SELECT is_enabled FROM modules
            WHERE module_name = ?
        ");

        $stmt->execute([$moduleName]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ? $result['is_enabled'] : false;
    }

    /**
     * Get module audit log
     */
    public function getModuleAuditLog($moduleId = null, $limit = 50)
    {
        $whereClause = $moduleId ? "WHERE mal.module_id = ?" : "";
        $params = $moduleId ? [$moduleId] : [];

        $stmt = $this->db->prepare("
            SELECT
                mal.*,
                m.module_name,
                m.display_name,
                u.username
            FROM module_audit_log mal
            JOIN modules m ON mal.module_id = m.module_id
            LEFT JOIN users u ON mal.user_id = u.user_id
            $whereClause
            ORDER BY mal.created_at DESC
            LIMIT ?
        ");

        $params[] = $limit;
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get system health overview
     */
    public function getSystemHealth()
    {
        $stmt = $this->db->prepare("
            SELECT
                COUNT(*) as total_modules,
                COUNT(CASE WHEN is_enabled THEN 1 END) as enabled_modules,
                COUNT(CASE WHEN mh.status = 'healthy' THEN 1 END) as healthy_modules,
                COUNT(CASE WHEN mh.status = 'degraded' THEN 1 END) as degraded_modules,
                COUNT(CASE WHEN mh.status = 'unhealthy' THEN 1 END) as unhealthy_modules
            FROM modules m
            LEFT JOIN module_health mh ON m.module_id = mh.module_id
        ");

        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
