<?php
require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/../src/Logger.php';

class UserService {
    private $userRepository;

    public function __construct($pdo) {
        $this->userRepository = new UserRepository($pdo);
    }

    public function getUsers($filters = [], $pagination = []) {
        try {
            $users = $this->userRepository->findAll($filters, $pagination);
            $total = $this->userRepository->count($filters);

            return [
                'users' => array_map(function($user) {
                    return $user->toApiArray();
                }, $users),
                'pagination' => [
                    'page' => $pagination['page'] ?? 1,
                    'limit' => $pagination['limit'] ?? 50,
                    'total' => $total,
                    'pages' => ceil($total / ($pagination['limit'] ?? 50))
                ]
            ];
        } catch (Exception $e) {
            Logger::error('Failed to get users: ' . $e->getMessage());
            throw new Exception('Failed to retrieve users');
        }
    }

    public function getUserById($id) {
        try {
            $user = $this->userRepository->findById($id);

            if (!$user) {
                throw new Exception('User not found');
            }

            return ['user' => $user->toApiArray()];
        } catch (Exception $e) {
            Logger::error('Failed to get user by ID: ' . $e->getMessage());
            throw $e;
        }
    }

    public function getUserByUsername($username) {
        try {
            $user = $this->userRepository->findByUsername($username);

            if (!$user) {
                throw new Exception('User not found');
            }

            return ['user' => $user->toApiArray()];
        } catch (Exception $e) {
            Logger::error('Failed to get user by username: ' . $e->getMessage());
            throw $e;
        }
    }

    public function getUserByEmail($email) {
        try {
            $user = $this->userRepository->findByEmail($email);

            if (!$user) {
                throw new Exception('User not found');
            }

            return ['user' => $user->toApiArray()];
        } catch (Exception $e) {
            Logger::error('Failed to get user by email: ' . $e->getMessage());
            throw $e;
        }
    }

    public function createUser($data) {
        try {
            // Validate required fields
            $this->validateUserData($data, ['username', 'email', 'password', 'role_id']);

            // Create user object for validation
            $user = new User($data);
            $validationErrors = $user->validateForCreation();

            if (!empty($validationErrors)) {
                throw new Exception('Validation failed: ' . implode(', ', $validationErrors));
            }

            // Validate password strength
            $passwordErrors = $user->validatePasswordStrength($data['password']);
            if (!empty($passwordErrors)) {
                throw new Exception('Password validation failed: ' . implode(', ', $passwordErrors));
            }

            // Check for duplicates
            if ($this->userRepository->existsByUsername($data['username'])) {
                throw new Exception('Username already exists');
            }

            if ($this->userRepository->existsByEmail($data['email'])) {
                throw new Exception('Email already exists');
            }

            // Hash password
            $user->setPassword($data['password']);
            $data['password_hash'] = $user->getPasswordHash();

            $createdUser = $this->userRepository->create($data);

            Logger::info('User created: ' . $createdUser->getUsername() . ' (ID: ' . $createdUser->getId() . ')');

            return [
                'message' => 'User created successfully',
                'user_id' => $createdUser->getId(),
                'user' => $createdUser->toApiArray()
            ];
        } catch (Exception $e) {
            Logger::error('Failed to create user: ' . $e->getMessage());
            throw $e;
        }
    }

    public function updateUser($id, $data) {
        try {
            // Check if user exists
            $existingUser = $this->userRepository->findById($id);
            if (!$existingUser) {
                throw new Exception('User not found');
            }

            // Create updated user object for validation
            $updatedData = array_merge($existingUser->toArray(), $data);
            $user = new User($updatedData);
            $validationErrors = $user->validateForUpdate();

            if (!empty($validationErrors)) {
                throw new Exception('Validation failed: ' . implode(', ', $validationErrors));
            }

            // Check for username duplicates (excluding current user)
            if (!empty($data['username']) && $data['username'] !== $existingUser->getUsername()) {
                $existingWithUsername = $this->userRepository->findByUsername($data['username']);
                if ($existingWithUsername && $existingWithUsername->getId() != $id) {
                    throw new Exception('Username already exists');
                }
            }

            // Check for email duplicates (excluding current user)
            if (!empty($data['email']) && $data['email'] !== $existingUser->getEmail()) {
                $existingWithEmail = $this->userRepository->findByEmail($data['email']);
                if ($existingWithEmail && $existingWithEmail->getId() != $id) {
                    throw new Exception('Email already exists');
                }
            }

            $success = $this->userRepository->update($id, $data);

            if (!$success) {
                throw new Exception('Failed to update user');
            }

            Logger::info('User updated: ID ' . $id);

            // Get updated user
            $updatedUser = $this->userRepository->findById($id);

            return [
                'message' => 'User updated successfully',
                'user' => $updatedUser->toApiArray()
            ];
        } catch (Exception $e) {
            Logger::error('Failed to update user: ' . $e->getMessage());
            throw $e;
        }
    }

    public function updatePassword($id, $currentPassword, $newPassword) {
        try {
            // Get user
            $user = $this->userRepository->findById($id);
            if (!$user) {
                throw new Exception('User not found');
            }

            // Verify current password
            if (!$user->verifyPassword($currentPassword)) {
                throw new Exception('Current password is incorrect');
            }

            // Validate new password strength
            $passwordErrors = $user->validatePasswordStrength($newPassword);
            if (!empty($passwordErrors)) {
                throw new Exception('New password validation failed: ' . implode(', ', $passwordErrors));
            }

            // Hash new password
            $user->setPassword($newPassword);
            $newPasswordHash = $user->getPasswordHash();

            $success = $this->userRepository->updatePassword($id, $newPasswordHash);

            if (!$success) {
                throw new Exception('Failed to update password');
            }

            Logger::info('Password updated for user ID: ' . $id);

            return ['message' => 'Password updated successfully'];
        } catch (Exception $e) {
            Logger::error('Failed to update password: ' . $e->getMessage());
            throw $e;
        }
    }

    public function deleteUser($id) {
        try {
            // Check if user exists
            $user = $this->userRepository->findById($id);
            if (!$user) {
                throw new Exception('User not found');
            }

            // Prevent deleting admin users (optional business rule)
            if ($user->isAdmin()) {
                throw new Exception('Cannot delete admin users');
            }

            $this->userRepository->delete($id);

            Logger::info('User deleted: ID ' . $id . ' (' . $user->getUsername() . ')');

            return ['message' => 'User deleted successfully'];
        } catch (Exception $e) {
            Logger::error('Failed to delete user: ' . $e->getMessage());
            throw $e;
        }
    }

    public function authenticateUser($usernameOrEmail, $password) {
        try {
            // Try to find user by username first, then by email
            $user = $this->userRepository->findByUsername($usernameOrEmail);

            if (!$user) {
                $user = $this->userRepository->findByEmail($usernameOrEmail);
            }

            if (!$user) {
                throw new Exception('Invalid credentials');
            }

            if (!$user->isActive()) {
                throw new Exception('Account is deactivated');
            }

            if (!$user->verifyPassword($password)) {
                throw new Exception('Invalid credentials');
            }

            // Update last login
            $this->userRepository->updateLastLogin($user->getId());
            $user->updateLastLogin();

            Logger::info('User authenticated: ' . $user->getUsername());

            return ['user' => $user->toApiArray()];
        } catch (Exception $e) {
            Logger::error('Authentication failed for: ' . $usernameOrEmail);
            throw $e;
        }
    }

    public function deactivateUser($id) {
        try {
            $user = $this->userRepository->findById($id);
            if (!$user) {
                throw new Exception('User not found');
            }

            if ($user->isAdmin()) {
                throw new Exception('Cannot deactivate admin users');
            }

            $this->userRepository->deactivateUser($id);

            Logger::info('User deactivated: ID ' . $id);

            return ['message' => 'User deactivated successfully'];
        } catch (Exception $e) {
            Logger::error('Failed to deactivate user: ' . $e->getMessage());
            throw $e;
        }
    }

    public function activateUser($id) {
        try {
            $user = $this->userRepository->findById($id);
            if (!$user) {
                throw new Exception('User not found');
            }

            $this->userRepository->activateUser($id);

            Logger::info('User activated: ID ' . $id);

            return ['message' => 'User activated successfully'];
        } catch (Exception $e) {
            Logger::error('Failed to activate user: ' . $e->getMessage());
            throw $e;
        }
    }

    public function searchUsers($searchTerm, $filters = []) {
        try {
            $filters['search'] = $searchTerm;
            $users = $this->userRepository->findAll($filters);

            return [
                'users' => array_map(function($user) {
                    return $user->toApiArray();
                }, $users)
            ];
        } catch (Exception $e) {
            Logger::error('Failed to search users: ' . $e->getMessage());
            throw new Exception('Failed to search users');
        }
    }

    public function getUsersByRole($roleId) {
        try {
            $users = $this->userRepository->getUsersByRole($roleId);

            return [
                'users' => array_map(function($user) {
                    return $user->toApiArray();
                }, $users)
            ];
        } catch (Exception $e) {
            Logger::error('Failed to get users by role: ' . $e->getMessage());
            throw new Exception('Failed to retrieve users by role');
        }
    }

    public function getUserStatistics() {
        try {
            $stats = $this->userRepository->getUserStatistics();
            $activityStats = $this->userRepository->getUsersByActivityStatus();

            return [
                'total_users' => $stats['total_users'],
                'active_users' => $stats['active_users'],
                'inactive_users' => $stats['inactive_users'],
                'users_with_login' => $stats['users_with_login'],
                'average_account_age_days' => round($stats['avg_account_age_days'], 1),
                'activity_breakdown' => $activityStats
            ];
        } catch (Exception $e) {
            Logger::error('Failed to get user statistics: ' . $e->getMessage());
            throw new Exception('Failed to retrieve user statistics');
        }
    }

    public function getRoleDistribution() {
        try {
            $distribution = $this->userRepository->getRoleDistribution();

            return [
                'roles' => $distribution,
                'total_users' => array_sum(array_column($distribution, 'count'))
            ];
        } catch (Exception $e) {
            Logger::error('Failed to get role distribution: ' . $e->getMessage());
            throw new Exception('Failed to retrieve role distribution');
        }
    }

    public function getUserProfile($userId) {
        try {
            $user = $this->userRepository->findById($userId);

            if (!$user) {
                throw new Exception('User not found');
            }

            $profile = $user->toPublicArray();
            $profile['profile_completion'] = $user->getProfileCompletionPercentage();
            $profile['account_status'] = $user->getAccountStatus();
            $profile['can_manage_users'] = $user->canManageUsers();
            $profile['can_manage_flights'] = $user->canManageFlights();
            $profile['can_view_analytics'] = $user->canViewAnalytics();

            return ['profile' => $profile];
        } catch (Exception $e) {
            Logger::error('Failed to get user profile: ' . $e->getMessage());
            throw $e;
        }
    }

    private function validateUserData($data, $requiredFields) {
        $missing = [];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty(trim($data[$field]))) {
                $missing[] = $field;
            }
        }

        if (!empty($missing)) {
            throw new Exception('Missing required fields: ' . implode(', ', $missing));
        }
    }
}
?>
