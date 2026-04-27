<?php
require_once __DIR__ . '/../models/User.php';

class UserRepository {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function findAll($filters = [], $pagination = []) {
        $where = [];
        $params = [];

        // Build WHERE clause based on filters
        if (isset($filters['role_id'])) {
            $where[] = "u.role_id = ?";
            $params[] = $filters['role_id'];
        }

        if (isset($filters['is_active'])) {
            $where[] = "u.is_active = ?";
            $params[] = $filters['is_active'];
        }

        if (isset($filters['search'])) {
            $searchTerm = '%' . $filters['search'] . '%';
            $where[] = "(u.username ILIKE ? OR u.email ILIKE ? OR u.first_name ILIKE ? OR u.last_name ILIKE ?)";
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        }

        if (isset($filters['role_name'])) {
            $where[] = "r.name = ?";
            $params[] = $filters['role_name'];
        }

        $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

        // Pagination
        $limit = $pagination['limit'] ?? 50;
        $offset = $pagination['offset'] ?? 0;

        $sql = "
            SELECT
                u.*,
                r.name as role_name,
                r.permissions as role_permissions
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            {$whereClause}
            ORDER BY u.created_at DESC
            LIMIT ? OFFSET ?
        ";

        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $users = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $users[] = new User($row);
        }

        return $users;
    }

    public function findById($id) {
        $stmt = $this->pdo->prepare("
            SELECT
                u.*,
                r.name as role_name,
                r.permissions as role_permissions
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            WHERE u.id = ?
        ");
        $stmt->execute([$id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? new User($row) : null;
    }

    public function findByUsername($username) {
        $stmt = $this->pdo->prepare("
            SELECT
                u.*,
                r.name as role_name,
                r.permissions as role_permissions
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            WHERE u.username = ?
        ");
        $stmt->execute([$username]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? new User($row) : null;
    }

    public function findByEmail($email) {
        $stmt = $this->pdo->prepare("
            SELECT
                u.*,
                r.name as role_name,
                r.permissions as role_permissions
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            WHERE u.email = ?
        ");
        $stmt->execute([$email]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? new User($row) : null;
    }

    public function create(array $data) {
        $stmt = $this->pdo->prepare("
            INSERT INTO users (
                username, email, password_hash, role_id,
                first_name, last_name, is_active,
                created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");

        $stmt->execute([
            $data['username'],
            $data['email'],
            $data['password_hash'],
            $data['role_id'],
            $data['first_name'] ?? null,
            $data['last_name'] ?? null,
            $data['is_active'] ?? true
        ]);

        $userId = $this->pdo->lastInsertId();

        // Return the created user
        return $this->findById($userId);
    }

    public function update($id, array $data) {
        $updateFields = [];
        $params = [];

        $allowedFields = [
            'username', 'email', 'first_name', 'last_name',
            'role_id', 'is_active', 'last_login_at'
        ];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateFields[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }

        if (empty($updateFields)) {
            return false;
        }

        $updateFields[] = "updated_at = CURRENT_TIMESTAMP";
        $params[] = $id;

        $stmt = $this->pdo->prepare("
            UPDATE users SET " . implode(", ", $updateFields) . " WHERE id = ?
        ");

        return $stmt->execute($params);
    }

    public function updatePassword($id, $newPasswordHash) {
        $stmt = $this->pdo->prepare("
            UPDATE users SET password_hash = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?
        ");
        return $stmt->execute([$newPasswordHash, $id]);
    }

    public function updateLastLogin($id) {
        $stmt = $this->pdo->prepare("
            UPDATE users SET last_login_at = CURRENT_TIMESTAMP WHERE id = ?
        ");
        return $stmt->execute([$id]);
    }

    public function delete($id) {
        $stmt = $this->pdo->prepare("DELETE FROM users WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function count($filters = []) {
        $where = [];
        $params = [];

        if (isset($filters['role_id'])) {
            $where[] = "u.role_id = ?";
            $params[] = $filters['role_id'];
        }

        if (isset($filters['is_active'])) {
            $where[] = "u.is_active = ?";
            $params[] = $filters['is_active'];
        }

        if (isset($filters['search'])) {
            $searchTerm = '%' . $filters['search'] . '%';
            $where[] = "(u.username ILIKE ? OR u.email ILIKE ? OR u.first_name ILIKE ? OR u.last_name ILIKE ?)";
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        }

        if (isset($filters['role_name'])) {
            $where[] = "r.name = ?";
            $params[] = $filters['role_name'];
        }

        $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

        $stmt = $this->pdo->prepare("SELECT COUNT(*) as total FROM users u LEFT JOIN roles r ON u.role_id = r.id {$whereClause}");
        $stmt->execute($params);

        return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }

    public function exists($id) {
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    }

    public function existsByUsername($username) {
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    }

    public function existsByEmail($email) {
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    }

    public function existsByUsernameOrEmail($username, $email) {
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    }

    public function getUsersByRole($roleId) {
        $stmt = $this->pdo->prepare("
            SELECT
                u.*,
                r.name as role_name,
                r.permissions as role_permissions
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            WHERE u.role_id = ? AND u.is_active = true
            ORDER BY u.last_name, u.first_name
        ");
        $stmt->execute([$roleId]);

        $users = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $users[] = new User($row);
        }

        return $users;
    }

    public function getUserStatistics() {
        $stmt = $this->pdo->prepare("
            SELECT
                COUNT(*) as total_users,
                COUNT(CASE WHEN is_active = true THEN 1 END) as active_users,
                COUNT(CASE WHEN is_active = false THEN 1 END) as inactive_users,
                COUNT(CASE WHEN last_login_at IS NOT NULL THEN 1 END) as users_with_login,
                AVG(EXTRACT(EPOCH FROM (CURRENT_TIMESTAMP - created_at))/86400) as avg_account_age_days
            FROM users
        ");
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getUsersByActivityStatus() {
        $stmt = $this->pdo->prepare("
            SELECT
                CASE
                    WHEN is_active = false THEN 'inactive'
                    WHEN last_login_at IS NULL THEN 'never_logged_in'
                    WHEN CURRENT_TIMESTAMP - last_login_at > INTERVAL '90 days' THEN 'dormant'
                    WHEN CURRENT_TIMESTAMP - last_login_at > INTERVAL '30 days' THEN 'inactive_recently'
                    ELSE 'active'
                END as status,
                COUNT(*) as count
            FROM users
            GROUP BY
                CASE
                    WHEN is_active = false THEN 'inactive'
                    WHEN last_login_at IS NULL THEN 'never_logged_in'
                    WHEN CURRENT_TIMESTAMP - last_login_at > INTERVAL '90 days' THEN 'dormant'
                    WHEN CURRENT_TIMESTAMP - last_login_at > INTERVAL '30 days' THEN 'inactive_recently'
                    ELSE 'active'
                END
        ");
        $stmt->execute();

        $stats = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $stats[$row['status']] = (int)$row['count'];
        }

        return $stats;
    }

    public function getUsersCreatedInDateRange($startDate, $endDate) {
        $stmt = $this->pdo->prepare("
            SELECT
                DATE(created_at) as date,
                COUNT(*) as count
            FROM users
            WHERE created_at BETWEEN ? AND ?
            GROUP BY DATE(created_at)
            ORDER BY date
        ");
        $stmt->execute([$startDate, $endDate]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRoleDistribution() {
        $stmt = $this->pdo->prepare("
            SELECT
                r.name as role_name,
                COUNT(u.id) as count
            FROM roles r
            LEFT JOIN users u ON r.id = u.role_id
            GROUP BY r.id, r.name
            ORDER BY count DESC
        ");
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function deactivateUser($id) {
        $stmt = $this->pdo->prepare("
            UPDATE users SET is_active = false, updated_at = CURRENT_TIMESTAMP WHERE id = ?
        ");
        return $stmt->execute([$id]);
    }

    public function activateUser($id) {
        $stmt = $this->pdo->prepare("
            UPDATE users SET is_active = true, updated_at = CURRENT_TIMESTAMP WHERE id = ?
        ");
        return $stmt->execute([$id]);
    }
}
?>
