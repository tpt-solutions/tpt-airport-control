<?php

class User {
    private $id;
    private $username;
    private $email;
    private $passwordHash;
    private $firstName;
    private $lastName;
    private $roleId;
    private $isActive;
    private $createdAt;
    private $updatedAt;
    private $lastLoginAt;

    // Joined data from related tables
    private $roleName;
    private $rolePermissions;

    public function __construct(array $data = []) {
        $this->hydrate($data);
    }

    public function hydrate(array $data) {
        foreach ($data as $key => $value) {
            $method = 'set' . str_replace('_', '', ucwords($key, '_'));
            if (method_exists($this, $method)) {
                $this->$method($value);
            }
        }
    }

    // Getters
    public function getId() { return $this->id; }
    public function getUsername() { return $this->username; }
    public function getEmail() { return $this->email; }
    public function getPasswordHash() { return $this->passwordHash; }
    public function getFirstName() { return $this->firstName; }
    public function getLastName() { return $this->lastName; }
    public function getRoleId() { return $this->roleId; }
    public function getIsActive() { return $this->isActive; }
    public function getCreatedAt() { return $this->createdAt; }
    public function getUpdatedAt() { return $this->updatedAt; }
    public function getLastLoginAt() { return $this->lastLoginAt; }

    // Role getters
    public function getRoleName() { return $this->roleName; }
    public function getRolePermissions() { return $this->rolePermissions; }

    // Setters
    public function setId($id) { $this->id = $id; }
    public function setUsername($username) { $this->username = $username; }
    public function setEmail($email) { $this->email = $email; }
    public function setPasswordHash($passwordHash) { $this->passwordHash = $passwordHash; }
    public function setFirstName($firstName) { $this->firstName = $firstName; }
    public function setLastName($lastName) { $this->lastName = $lastName; }
    public function setRoleId($roleId) { $this->roleId = $roleId; }
    public function setIsActive($isActive) { $this->isActive = $isActive; }
    public function setCreatedAt($createdAt) { $this->createdAt = $createdAt; }
    public function setUpdatedAt($updatedAt) { $this->updatedAt = $updatedAt; }
    public function setLastLoginAt($lastLoginAt) { $this->lastLoginAt = $lastLoginAt; }

    // Role setters
    public function setRoleName($roleName) { $this->roleName = $roleName; }
    public function setRolePermissions($rolePermissions) { $this->rolePermissions = $rolePermissions; }

    // Business logic methods
    public function getFullName() {
        return trim($this->firstName . ' ' . $this->lastName);
    }

    public function getDisplayName() {
        if (!empty($this->getFullName())) {
            return $this->getFullName();
        }
        return $this->username;
    }

    public function isActive() {
        return $this->isActive;
    }

    public function isAdmin() {
        return $this->roleName === 'admin';
    }

    public function isOperator() {
        return $this->roleName === 'operator';
    }

    public function isPassenger() {
        return $this->roleName === 'passenger';
    }

    public function hasPermission($permission) {
        if (!$this->rolePermissions) {
            return false;
        }

        $permissions = is_array($this->rolePermissions) ? $this->rolePermissions : json_decode($this->rolePermissions, true);
        return in_array($permission, $permissions);
    }

    public function canManageUsers() {
        return $this->isAdmin();
    }

    public function canManageFlights() {
        return $this->isAdmin() || $this->isOperator();
    }

    public function canViewAnalytics() {
        return $this->isAdmin() || $this->isOperator();
    }

    public function getAccountStatus() {
        if (!$this->isActive) {
            return 'inactive';
        }

        if ($this->lastLoginAt) {
            $lastLogin = strtotime($this->lastLoginAt);
            $daysSinceLogin = (time() - $lastLogin) / (60 * 60 * 24);

            if ($daysSinceLogin > 90) {
                return 'dormant';
            } elseif ($daysSinceLogin > 30) {
                return 'inactive_recently';
            }
        }

        return 'active';
    }

    public function isProfileComplete() {
        return !empty($this->firstName) &&
               !empty($this->lastName) &&
               !empty($this->email) &&
               filter_var($this->email, FILTER_VALIDATE_EMAIL);
    }

    public function getProfileCompletionPercentage() {
        $fields = ['firstName', 'lastName', 'email'];
        $completed = 0;

        foreach ($fields as $field) {
            if (!empty($this->$field)) {
                if ($field === 'email' && !filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
                    continue;
                }
                $completed++;
            }
        }

        return round(($completed / count($fields)) * 100);
    }

    public function updateLastLogin() {
        $this->lastLoginAt = date('Y-m-d H:i:s');
    }

    public function setPassword($plainPassword) {
        $this->passwordHash = password_hash($plainPassword, PASSWORD_DEFAULT);
    }

    public function verifyPassword($plainPassword) {
        return password_verify($plainPassword, $this->passwordHash);
    }

    public function needsPasswordRehash() {
        return password_needs_rehash($this->passwordHash, PASSWORD_DEFAULT);
    }

    public function toArray() {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'email' => $this->email,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'role_id' => $this->roleId,
            'role_name' => $this->roleName,
            'is_active' => $this->isActive,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'last_login_at' => $this->lastLoginAt
        ];
    }

    public function toPublicArray() {
        $data = $this->toArray();
        unset($data['password_hash']);
        return $data;
    }

    public function toApiArray() {
        return $this->toPublicArray();
    }

    // Validation methods
    public function validateForCreation() {
        $errors = [];

        if (empty($this->username)) {
            $errors[] = 'Username is required';
        } elseif (strlen($this->username) < 3) {
            $errors[] = 'Username must be at least 3 characters long';
        }

        if (empty($this->email)) {
            $errors[] = 'Email is required';
        } elseif (!filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format';
        }

        if (empty($this->passwordHash)) {
            $errors[] = 'Password is required';
        }

        if (empty($this->roleId)) {
            $errors[] = 'Role is required';
        }

        return $errors;
    }

    public function validateForUpdate() {
        $errors = [];

        if (!empty($this->username) && strlen($this->username) < 3) {
            $errors[] = 'Username must be at least 3 characters long';
        }

        if (!empty($this->email) && !filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format';
        }

        return $errors;
    }

    public function validatePasswordStrength($password) {
        $errors = [];

        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long';
        }

        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter';
        }

        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter';
        }

        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number';
        }

        return $errors;
    }
}
?>
