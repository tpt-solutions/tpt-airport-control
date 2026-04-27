<?php

namespace TPT\FlightControl;

use PDO;
use PDOException;

require_once __DIR__ . '/Logger.php';

class RBAC {
    private static $pdo;

    public static function init($pdo) {
        self::$pdo = $pdo;
    }

    /**
     * Check if user has specific permission
     */
    public static function hasPermission($userId, $permission, $module = null) {
        try {
            if ($module) {
                // Check module-specific permission
                $stmt = self::$pdo->prepare("
                    SELECT up.id FROM user_permissions up
                    JOIN modules m ON up.module_id = m.id
                    WHERE up.user_id = ? AND up.permission = ? AND m.name = ? AND m.is_enabled = true
                ");
                $stmt->execute([$userId, $permission, $module]);
            } else {
                // Check global permission
                $stmt = self::$pdo->prepare("
                    SELECT up.id FROM user_permissions up
                    WHERE up.user_id = ? AND up.permission = ?
                ");
                $stmt->execute([$userId, $permission]);
            }

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result !== false;
        } catch (PDOException $e) {
            Logger::error('Permission check failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if user has role
     */
    public static function hasRole($userId, $roleName) {
        try {
            $stmt = self::$pdo->prepare("
                SELECT r.id FROM users u
                JOIN roles r ON u.role_id = r.id
                WHERE u.id = ? AND r.name = ?
            ");
            $stmt->execute([$userId, $roleName]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result !== false;
        } catch (PDOException $e) {
            Logger::error('Role check failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all permissions for a user
     */
    public static function getUserPermissions($userId) {
        try {
            $stmt = self::$pdo->prepare("
                SELECT m.name as module, up.permission
                FROM user_permissions up
                LEFT JOIN modules m ON up.module_id = m.id
                WHERE up.user_id = ?
                ORDER BY m.name, up.permission
            ");
            $stmt->execute([$userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            Logger::error('Get user permissions failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get all available modules
     */
    public static function getModules() {
        try {
            $stmt = self::$pdo->query("SELECT id, name, description, is_enabled FROM modules ORDER BY name");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            Logger::error('Get modules failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get all roles
     */
    public static function getRoles() {
        try {
            $stmt = self::$pdo->query("SELECT id, name, description FROM roles ORDER BY name");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            Logger::error('Get roles failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Assign permission to user
     */
    public static function assignPermission($userId, $permission, $moduleId = null) {
        try {
            // Check if permission already exists
            if ($moduleId) {
                $stmt = self::$pdo->prepare("
                    SELECT id FROM user_permissions
                    WHERE user_id = ? AND permission = ? AND module_id = ?
                ");
                $stmt->execute([$userId, $permission, $moduleId]);
            } else {
                $stmt = self::$pdo->prepare("
                    SELECT id FROM user_permissions
                    WHERE user_id = ? AND permission = ? AND module_id IS NULL
                ");
                $stmt->execute([$userId, $permission]);
            }

            if ($stmt->fetch()) {
                return true; // Already has permission
            }

            // Insert new permission
            $stmt = self::$pdo->prepare("
                INSERT INTO user_permissions (user_id, module_id, permission)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$userId, $moduleId, $permission]);

            Logger::info("Permission '$permission' assigned to user $userId" . ($moduleId ? " for module $moduleId" : ""));
            return true;
        } catch (PDOException $e) {
            Logger::error('Assign permission failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Remove permission from user
     */
    public static function removePermission($userId, $permission, $moduleId = null) {
        try {
            if ($moduleId) {
                $stmt = self::$pdo->prepare("
                    DELETE FROM user_permissions
                    WHERE user_id = ? AND permission = ? AND module_id = ?
                ");
                $stmt->execute([$userId, $permission, $moduleId]);
            } else {
                $stmt = self::$pdo->prepare("
                    DELETE FROM user_permissions
                    WHERE user_id = ? AND permission = ? AND module_id IS NULL
                ");
                $stmt->execute([$userId, $permission]);
            }

            Logger::info("Permission '$permission' removed from user $userId" . ($moduleId ? " for module $moduleId" : ""));
            return true;
        } catch (PDOException $e) {
            Logger::error('Remove permission failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Create role with default permissions
     */
    public static function createRole($name, $description, $defaultPermissions = []) {
        try {
            self::$pdo->beginTransaction();

            // Insert role
            $stmt = self::$pdo->prepare("INSERT INTO roles (name, description) VALUES (?, ?)");
            $stmt->execute([$name, $description]);
            $roleId = self::$pdo->lastInsertId();

            // Insert default permissions if provided
            if (!empty($defaultPermissions)) {
                foreach ($defaultPermissions as $perm) {
                    $moduleId = $perm['module_id'] ?? null;
                    $permission = $perm['permission'];

                    $stmt = self::$pdo->prepare("
                        INSERT INTO role_permissions (role_id, module_id, permission)
                        VALUES (?, ?, ?)
                    ");
                    $stmt->execute([$roleId, $moduleId, $permission]);
                }
            }

            self::$pdo->commit();
            Logger::info("Role '$name' created with ID $roleId");
            return $roleId;
        } catch (PDOException $e) {
            self::$pdo->rollBack();
            Logger::error('Create role failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check access based on role hierarchy and permissions
     */
    public static function checkAccess($userId, $requiredPermission, $module = null, $resourceOwnerId = null) {
        // First check direct permission
        if (self::hasPermission($userId, $requiredPermission, $module)) {
            return true;
        }

        // Check role-based permissions
        $userRole = self::getUserRole($userId);
        if ($userRole) {
            // Super admin has all permissions
            if ($userRole['name'] === 'super_admin') {
                return true;
            }

            // Check role permissions
            if (self::roleHasPermission($userRole['id'], $requiredPermission, $module)) {
                return true;
            }
        }

        // Check resource ownership (for own resources)
        if ($resourceOwnerId && $userId == $resourceOwnerId && $requiredPermission === 'edit_own') {
            return true;
        }

        return false;
    }

    /**
     * Get user's role
     */
    private static function getUserRole($userId) {
        try {
            $stmt = self::$pdo->prepare("
                SELECT r.id, r.name FROM users u
                JOIN roles r ON u.role_id = r.id
                WHERE u.id = ?
            ");
            $stmt->execute([$userId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            Logger::error('Get user role failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Check if role has permission
     */
    private static function roleHasPermission($roleId, $permission, $module = null) {
        try {
            if ($module) {
                $stmt = self::$pdo->prepare("
                    SELECT rp.id FROM role_permissions rp
                    JOIN modules m ON rp.module_id = m.id
                    WHERE rp.role_id = ? AND rp.permission = ? AND m.name = ?
                ");
                $stmt->execute([$roleId, $permission, $module]);
            } else {
                $stmt = self::$pdo->prepare("
                    SELECT rp.id FROM role_permissions rp
                    WHERE rp.role_id = ? AND rp.permission = ?
                ");
                $stmt->execute([$roleId, $permission]);
            }

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result !== false;
        } catch (PDOException $e) {
            Logger::error('Role permission check failed: ' . $e->getMessage());
            return false;
        }
    }
}
?>
