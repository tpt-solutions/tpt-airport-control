<?php
require_once __DIR__ . '/../src/RBAC.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Logger.php';

class AuthorizationService {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function checkPermission($userId, $permission, $module = null) {
        try {
            return RBAC::hasPermission($userId, $permission, $module);
        } catch (Exception $e) {
            Logger::error('Permission check error: ' . $e->getMessage());
            return false;
        }
    }

    public function checkRole($userId, $roleName) {
        try {
            return RBAC::hasRole($userId, $roleName);
        } catch (Exception $e) {
            Logger::error('Role check error: ' . $e->getMessage());
            return false;
        }
    }

    public function checkAccess($userId, $requiredPermission, $module = null, $resourceOwnerId = null) {
        try {
            return RBAC::checkAccess($userId, $requiredPermission, $module, $resourceOwnerId);
        } catch (Exception $e) {
            Logger::error('Access check error: ' . $e->getMessage());
            return false;
        }
    }

    public function getUserPermissions($userId) {
        try {
            return RBAC::getUserPermissions($userId);
        } catch (Exception $e) {
            Logger::error('Get user permissions error: ' . $e->getMessage());
            return [];
        }
    }

    public function getUserRoles($userId) {
        try {
            // Get user role from database
            $stmt = $this->pdo->prepare("
                SELECT r.name FROM users u
                JOIN roles r ON u.role_id = r.id
                WHERE u.id = ?
            ");
            $stmt->execute([$userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result ? [$result['name']] : [];
        } catch (Exception $e) {
            Logger::error('Get user roles error: ' . $e->getMessage());
            return [];
        }
    }

    public function assignPermission($userId, $permission, $moduleId = null) {
        try {
            $success = RBAC::assignPermission($userId, $permission, $moduleId);

            if ($success) {
                Logger::info("Permission '$permission' assigned to user $userId");
            }

            return $success;
        } catch (Exception $e) {
            Logger::error('Assign permission error: ' . $e->getMessage());
            return false;
        }
    }

    public function removePermission($userId, $permission, $moduleId = null) {
        try {
            $success = RBAC::removePermission($userId, $permission, $moduleId);

            if ($success) {
                Logger::info("Permission '$permission' removed from user $userId");
            }

            return $success;
        } catch (Exception $e) {
            Logger::error('Remove permission error: ' . $e->getMessage());
            return false;
        }
    }

    public function createRole($name, $description, $defaultPermissions = []) {
        try {
            $roleId = RBAC::createRole($name, $description, $defaultPermissions);

            if ($roleId) {
                Logger::info("Role '$name' created with ID $roleId");
            }

            return $roleId;
        } catch (Exception $e) {
            Logger::error('Create role error: ' . $e->getMessage());
            return false;
        }
    }

    public function getAllRoles() {
        try {
            return RBAC::getRoles();
        } catch (Exception $e) {
            Logger::error('Get all roles error: ' . $e->getMessage());
            return [];
        }
    }

    public function getAllModules() {
        try {
            return RBAC::getModules();
        } catch (Exception $e) {
            Logger::error('Get all modules error: ' . $e->getMessage());
            return [];
        }
    }

    public function requirePermission($permission, $module = null) {
        $user = Auth::getCurrentUser();

        if (!$user) {
            throw new Exception('Authentication required');
        }

        if (!$this->checkPermission($user['id'], $permission, $module)) {
            throw new Exception('Insufficient permissions');
        }
    }

    public function requireRole($roleName) {
        $user = Auth::getCurrentUser();

        if (!$user) {
            throw new Exception('Authentication required');
        }

        if (!$this->checkRole($user['id'], $roleName)) {
            throw new Exception('Insufficient role permissions');
        }
    }

    public function requireAccess($requiredPermission, $module = null, $resourceOwnerId = null) {
        $user = Auth::getCurrentUser();

        if (!$user) {
            throw new Exception('Authentication required');
        }

        if (!$this->checkAccess($user['id'], $requiredPermission, $module, $resourceOwnerId)) {
            throw new Exception('Access denied');
        }
    }

    public function canUserManageUsers($userId) {
        return $this->checkRole($userId, 'admin');
    }

    public function canUserManageFlights($userId) {
        return $this->checkRole($userId, 'admin') || $this->checkRole($userId, 'operator');
    }

    public function canUserViewAnalytics($userId) {
        return $this->checkRole($userId, 'admin') || $this->checkRole($userId, 'operator');
    }

    public function canUserViewPassengers($userId) {
        return $this->checkPermission($userId, 'read', 'passengers');
    }

    public function canUserManagePassengers($userId) {
        return $this->checkPermission($userId, 'write', 'passengers');
    }

    public function canUserViewOwnBookings($userId) {
        // All authenticated users can view their own bookings
        return true;
    }

    public function canUserManageOwnBookings($userId) {
        // All authenticated users can manage their own bookings
        return true;
    }

    public function canUserViewAllBookings($userId) {
        return $this->checkPermission($userId, 'read', 'bookings');
    }

    public function canUserManageAllBookings($userId) {
        return $this->checkPermission($userId, 'write', 'bookings');
    }

    public function filterDataByOwnership($userId, $data, $ownerField = 'user_id') {
        $user = Auth::getCurrentUser();

        // If user is admin, return all data
        if ($this->checkRole($user['id'], 'admin')) {
            return $data;
        }

        // Otherwise, filter to only show user's own data
        return array_filter($data, function($item) use ($userId, $ownerField) {
            return isset($item[$ownerField]) && $item[$ownerField] == $userId;
        });
    }

    public function validateResourceOwnership($userId, $resourceId, $resourceType) {
        // This would check if the user owns the specific resource
        // Implementation depends on the resource type
        switch ($resourceType) {
            case 'booking':
                // Check if user owns the booking
                $stmt = $this->pdo->prepare("
                    SELECT b.id FROM bookings b
                    JOIN passengers p ON b.passenger_id = p.id
                    WHERE b.id = ? AND p.user_id = ?
                ");
                $stmt->execute([$resourceId, $userId]);
                return $stmt->fetch(PDO::FETCH_ASSOC) !== false;

            case 'passenger':
                // Check if passenger belongs to user (for passenger role users)
                $stmt = $this->pdo->prepare("SELECT id FROM passengers WHERE id = ? AND user_id = ?");
                $stmt->execute([$resourceId, $userId]);
                return $stmt->fetch(PDO::FETCH_ASSOC) !== false;

            default:
                return false;
        }
    }

    public function getUserAccessSummary($userId) {
        try {
            $user = Auth::getCurrentUser();

            if (!$user || $user['id'] != $userId) {
                throw new Exception('Access denied');
            }

            return [
                'user_id' => $userId,
                'role' => $user['role_name'],
                'permissions' => $this->getUserPermissions($userId),
                'can_manage_users' => $this->canUserManageUsers($userId),
                'can_manage_flights' => $this->canUserManageFlights($userId),
                'can_view_analytics' => $this->canUserViewAnalytics($userId),
                'can_view_passengers' => $this->canUserViewPassengers($userId),
                'can_manage_passengers' => $this->canUserManagePassengers($userId),
                'can_view_own_bookings' => $this->canUserViewOwnBookings($userId),
                'can_manage_own_bookings' => $this->canUserManageOwnBookings($userId),
                'can_view_all_bookings' => $this->canUserViewAllBookings($userId),
                'can_manage_all_bookings' => $this->canUserManageAllBookings($userId)
            ];
        } catch (Exception $e) {
            Logger::error('Get user access summary error: ' . $e->getMessage());
            throw $e;
        }
    }
}
?>
