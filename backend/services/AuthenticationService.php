<?php
require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Logger.php';

class AuthenticationService {
    private $userRepository;

    public function __construct($pdo) {
        $this->userRepository = new UserRepository($pdo);
    }

    public function authenticate($usernameOrEmail, $password) {
        try {
            Logger::info('Authentication attempt for: ' . $usernameOrEmail);

            // Use the Auth class method for authentication
            $user = Auth::authenticate($usernameOrEmail, $password);

            if (!$user) {
                Logger::warning('Authentication failed for: ' . $usernameOrEmail);
                throw new Exception('Invalid credentials');
            }

            // Generate JWT token
            $token = Auth::generateToken($user['id'], $user['username'], $user['role_name']);

            Logger::info('Authentication successful for user: ' . $user['username']);

            return [
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'first_name' => $user['first_name'],
                    'last_name' => $user['last_name'],
                    'role_name' => $user['role_name'],
                    'passenger_id' => $user['passenger_id'] ?? null
                ],
                'token' => $token,
                'expires_in' => 3600 // 1 hour
            ];
        } catch (Exception $e) {
            Logger::error('Authentication service error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function validateToken($token) {
        try {
            $userData = Auth::validateToken($token);

            if (!$userData) {
                return false;
            }

            // Verify user still exists and is active
            $user = $this->userRepository->findById($userData['user_id']);

            if (!$user || !$user->isActive()) {
                return false;
            }

            return $userData;
        } catch (Exception $e) {
            Logger::error('Token validation error: ' . $e->getMessage());
            return false;
        }
    }

    public function refreshToken($oldToken) {
        try {
            $userData = $this->validateToken($oldToken);

            if (!$userData) {
                throw new Exception('Invalid token');
            }

            $user = $this->userRepository->findById($userData['user_id']);

            if (!$user) {
                throw new Exception('User not found');
            }

            // Generate new token
            $newToken = Auth::generateToken($user->getId(), $user->getUsername(), $user->getRoleName());

            Logger::info('Token refreshed for user: ' . $user->getUsername());

            return [
                'token' => $newToken,
                'expires_in' => 3600
            ];
        } catch (Exception $e) {
            Logger::error('Token refresh error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function logout($token) {
        try {
            // In a more advanced system, you might want to blacklist tokens
            // For now, we just log the logout
            $userData = Auth::validateToken($token);
            if ($userData) {
                Logger::info('User logged out: ' . $userData['username']);
            }

            return ['message' => 'Logged out successfully'];
        } catch (Exception $e) {
            Logger::error('Logout error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function getCurrentUser() {
        return Auth::getCurrentUser();
    }

    public function isAuthenticated() {
        return Auth::isAuthenticated();
    }

    public function changePassword($userId, $currentPassword, $newPassword) {
        try {
            $user = $this->userRepository->findById($userId);

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

            // Hash new password and update
            $user->setPassword($newPassword);
            $success = $this->userRepository->updatePassword($userId, $user->getPasswordHash());

            if (!$success) {
                throw new Exception('Failed to update password');
            }

            Logger::info('Password changed for user ID: ' . $userId);

            return ['message' => 'Password changed successfully'];
        } catch (Exception $e) {
            Logger::error('Password change error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function initiatePasswordReset($email) {
        try {
            $user = $this->userRepository->findByEmail($email);

            if (!$user) {
                // Don't reveal if email exists or not for security
                return ['message' => 'If the email exists, a reset link has been sent'];
            }

            // Generate reset token (in a real system, you'd store this securely)
            $resetToken = bin2hex(random_bytes(32));
            $resetExpires = date('Y-m-d H:i:s', time() + 3600); // 1 hour

            // Store reset token (simplified - in production use a secure table)
            // For now, we'll just log it
            Logger::info('Password reset initiated for user: ' . $user->getEmail() . ' token: ' . $resetToken);

            // In a real system, send email here
            // mail($email, 'Password Reset', 'Reset token: ' . $resetToken);

            return ['message' => 'Password reset link sent to your email'];
        } catch (Exception $e) {
            Logger::error('Password reset initiation error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function resetPassword($resetToken, $newPassword) {
        try {
            // Verify reset token (simplified - in production verify from secure storage)
            // For now, accept any token for demo purposes

            // Validate new password strength
            $user = new User([]); // Create dummy user for validation
            $passwordErrors = $user->validatePasswordStrength($newPassword);
            if (!empty($passwordErrors)) {
                throw new Exception('Password validation failed: ' . implode(', ', $passwordErrors));
            }

            // In a real system, you'd find the user by reset token and update their password
            Logger::info('Password reset completed with token: ' . $resetToken);

            return ['message' => 'Password reset successfully'];
        } catch (Exception $e) {
            Logger::error('Password reset error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function getUserSessionInfo($userId) {
        try {
            $user = $this->userRepository->findById($userId);

            if (!$user) {
                throw new Exception('User not found');
            }

            return [
                'user_id' => $user->getId(),
                'username' => $user->getUsername(),
                'role' => $user->getRoleName(),
                'last_login' => $user->getLastLoginAt(),
                'is_active' => $user->isActive(),
                'permissions' => $user->getRolePermissions()
            ];
        } catch (Exception $e) {
            Logger::error('Get user session info error: ' . $e->getMessage());
            throw $e;
        }
    }
}
?>
