<?php
require_once __DIR__ . '/JWT.php';
require_once __DIR__ . '/RBAC.php';
require_once __DIR__ . '/Logger.php';

use TPT\FlightControl\RBAC;
use TPT\FlightControl\Logger;

class Auth {
    private static $secretKey;
    private static $pdo;

    public static function init($pdo) {
        self::$pdo = $pdo;
        
        $jwtSecret = getenv('JWT_SECRET');
        if (!$jwtSecret || $jwtSecret === 'fallback_secret_change_this_immediately_in_production') {
            error_log('SECURITY WARNING: Using default JWT secret key! This is INSECURE for production!');
            
            if (getenv('ENVIRONMENT') === 'production') {
                throw new Exception('JWT_SECRET environment variable must be set in production');
            }
            
            $jwtSecret = 'fallback_secret_change_this_immediately_in_production';
        }
        
        self::$secretKey = $jwtSecret;
        RBAC::init($pdo);
    }

    public static function generateToken($userId, $username, $role) {
        $issuedAt = time();
        $expirationTime = $issuedAt + 3600; // 1 hour

        $payload = [
            'iat' => $issuedAt,
            'exp' => $expirationTime,
            'user_id' => $userId,
            'username' => $username,
            'role' => $role
        ];

        return JWT::encode($payload, self::$secretKey);
    }
    
    /**
     * Generate secure refresh token with rotation
     */
    public static function generateRefreshToken($userId, $userAgent = null, $ipAddress = null) {
        $token = bin2hex(random_bytes(48)); // 96 character token
        $tokenId = bin2hex(random_bytes(16));
        $tokenHash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', time() + (86400 * 7)); // 7 days
        
        // Store hash only - token is never stored in plaintext
        $stmt = self::$pdo->prepare("
            INSERT INTO refresh_tokens 
            (user_id, token_hash, token_id, expires_at, user_agent, ip_address)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $tokenHash, $tokenId, $expiresAt, $userAgent, $ipAddress]);
        
        return [
            'token' => $token,
            'token_id' => $tokenId,
            'expires_at' => $expiresAt
        ];
    }
    
    /**
     * Refresh access token using refresh token with automatic token rotation
     */
    public static function refreshAccessToken($refreshToken) {
        $tokenHash = hash('sha256', $refreshToken);
        
        // Find valid refresh token
        $stmt = self::$pdo->prepare("
            SELECT rt.*, u.username, r.name as role_name
            FROM refresh_tokens rt
            JOIN users u ON rt.user_id = u.id
            LEFT JOIN roles r ON u.role_id = r.id
            WHERE rt.token_hash = ? 
              AND rt.is_revoked = false 
              AND rt.expires_at > NOW()
            FOR UPDATE
        ");
        $stmt->execute([$tokenHash]);
        $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$tokenData) {
            // Possible token reuse detected - revoke all user tokens for security
            self::revokeAllUserTokens($tokenData['user_id'] ?? null);
            return null;
        }
        
        // Revoke this refresh token (one time use only)
        $updateStmt = self::$pdo->prepare("
            UPDATE refresh_tokens 
            SET is_revoked = true, revoked_at = NOW(), last_used_at = NOW()
            WHERE id = ?
        ");
        $updateStmt->execute([$tokenData['id']]);
        
        // Generate new token pair
        $newAccessToken = self::generateToken($tokenData['user_id'], $tokenData['username'], $tokenData['role_name']);
        $newRefreshToken = self::generateRefreshToken(
            $tokenData['user_id'], 
            $tokenData['user_agent'], 
            $tokenData['ip_address']
        );
        
        // Clean expired tokens
        self::cleanExpiredTokens();
        
        return [
            'access_token' => $newAccessToken,
            'refresh_token' => $newRefreshToken['token'],
            'expires_in' => 3600
        ];
    }
    
    /**
     * Revoke specific refresh token
     */
    public static function revokeRefreshToken($refreshToken) {
        $tokenHash = hash('sha256', $refreshToken);
        $stmt = self::$pdo->prepare("
            UPDATE refresh_tokens 
            SET is_revoked = true, revoked_at = NOW()
            WHERE token_hash = ?
        ");
        return $stmt->execute([$tokenHash]);
    }
    
    /**
     * Revoke all refresh tokens for a user
     */
    public static function revokeAllUserTokens($userId) {
        if (!$userId) return false;
        $stmt = self::$pdo->prepare("
            UPDATE refresh_tokens 
            SET is_revoked = true, revoked_at = NOW()
            WHERE user_id = ? AND is_revoked = false
        ");
        return $stmt->execute([$userId]);
    }
    
    /**
     * Clean up expired tokens
     */
    private static function cleanExpiredTokens() {
        $stmt = self::$pdo->prepare("
            DELETE FROM refresh_tokens 
            WHERE expires_at < NOW() - INTERVAL '30 days'
        ");
        $stmt->execute();
    }

    public static function validateToken($token) {
        try {
            return JWT::decode($token, self::$secretKey);
        } catch (Exception $e) {
            return false;
        }
    }

    public static function getUserFromToken($token) {
        return self::validateToken($token);
    }

    public static function getCurrentUser() {
        // Check if user is already authenticated in this request
        if (isset($GLOBALS['current_user'])) {
            return $GLOBALS['current_user'];
        }

        // Try to get user from Authorization header
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

        if (empty($authHeader) || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return null;
        }

        $token = $matches[1];
        $userData = self::validateToken($token);

        if (!$userData) {
            return null;
        }

        // Get full user data from database
        try {
            $stmt = self::$pdo->prepare("
                SELECT u.*, r.name as role_name, r.permissions as role_permissions
                FROM users u
                LEFT JOIN roles r ON u.role_id = r.id
                WHERE u.id = ? AND u.is_active = true
            ");
            $stmt->execute([$userData['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // Add passenger_id for passenger role users
                if ($user['role_name'] === 'passenger') {
                    $passengerStmt = self::$pdo->prepare("SELECT id FROM passengers WHERE user_id = ?");
                    $passengerStmt->execute([$user['id']]);
                    $passenger = $passengerStmt->fetch(PDO::FETCH_ASSOC);
                    $user['passenger_id'] = $passenger ? $passenger['id'] : null;
                }

                $GLOBALS['current_user'] = $user;
                return $user;
            }
        } catch (Exception $e) {
            error_log('Database error fetching user data: ' . $e->getMessage());
            if (class_exists('Logger')) {
                Logger::error('Failed to fetch authenticated user data: ' . $e->getMessage(), [
                    'user_id' => $userData['user_id'] ?? null,
                    'exception' => $e
                ]);
            }
        }

        return null;
    }

    public static function hasPermission($permission, $module = null) {
        $user = self::getCurrentUser();
        if (!$user) {
            return false;
        }

        return RBAC::checkAccess($user['id'], $permission, $module);
    }

    public static function hasRole($roleName) {
        $user = self::getCurrentUser();
        if (!$user) {
            return false;
        }

        return RBAC::hasRole($user['id'], $roleName);
    }

    public static function authenticate($username, $password) {
        try {
            // First check if account is locked
            $lockCheckStmt = self::$pdo->prepare("
                SELECT id, login_attempts, last_failed_login, account_locked_until 
                FROM users 
                WHERE (username = ? OR email = ?)
            ");
            $lockCheckStmt->execute([$username, $username]);
            $userStatus = $lockCheckStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($userStatus) {
                // Check if account is currently locked
                if ($userStatus['account_locked_until'] !== null && strtotime($userStatus['account_locked_until']) > time()) {
                    error_log("Login attempt to locked account: " . $username);
                    sleep(2); // Prevent timing attacks
                    return false;
                }
                
                // Clear lock if expired
                if ($userStatus['account_locked_until'] !== null && strtotime($userStatus['account_locked_until']) <= time()) {
                    $clearLockStmt = self::$pdo->prepare("
                        UPDATE users 
                        SET login_attempts = 0, account_locked_until = NULL 
                        WHERE id = ?
                    ");
                    $clearLockStmt->execute([$userStatus['id']]);
                }
            }

            // Find user by username or email
            $stmt = self::$pdo->prepare("
                SELECT u.*, r.name as role_name
                FROM users u
                LEFT JOIN roles r ON u.role_id = r.id
                WHERE (u.username = ? OR u.email = ?) AND u.is_active = true
            ");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user || !password_verify($password, $user['password_hash'])) {
                // Increment failed login attempts
                if ($userStatus) {
                    $newAttempts = (int)$userStatus['login_attempts'] + 1;
                    $lockUntil = null;
                    
                    // Lock account after 5 failed attempts
                    if ($newAttempts >= 5) {
                        $lockUntil = date('Y-m-d H:i:s', time() + 900); // 15 minute lockout
                        error_log("Account locked due to failed login attempts: " . $username);
                    }
                    
                    $updateAttemptsStmt = self::$pdo->prepare("
                        UPDATE users 
                        SET login_attempts = ?, last_failed_login = CURRENT_TIMESTAMP, account_locked_until = ?
                        WHERE id = ?
                    ");
                    $updateAttemptsStmt->execute([$newAttempts, $lockUntil, $userStatus['id']]);
                }
                
                sleep(2); // Prevent timing attacks
                return false;
            }

            // Successful login - reset failed attempts
            $resetAttemptsStmt = self::$pdo->prepare("
                UPDATE users 
                SET login_attempts = 0, last_failed_login = NULL, account_locked_until = NULL, last_login_at = CURRENT_TIMESTAMP 
                WHERE id = ?
            ");
            $resetAttemptsStmt->execute([$user['id']]);

            // Add passenger_id for passenger role users
            if ($user['role_name'] === 'passenger') {
                $passengerStmt = self::$pdo->prepare("SELECT id FROM passengers WHERE user_id = ?");
                $passengerStmt->execute([$user['id']]);
                $passenger = $passengerStmt->fetch(PDO::FETCH_ASSOC);
                $user['passenger_id'] = $passenger ? $passenger['id'] : null;
            }

            return $user;
        } catch (Exception $e) {
            return false;
        }
    }

    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }

    public static function isAuthenticated() {
        return self::getCurrentUser() !== null;
    }

    public static function currentUserId() {
        $user = self::getCurrentUser();
        return $user ? $user['id'] : null;
    }

    public static function currentUserPlan() {
        $user = self::getCurrentUser();
        return $user ? ($user['plan'] ?? 'free') : 'free';
    }

    public static function logout() {
        // Clear current user from globals
        unset($GLOBALS['current_user']);
    }

    public static function requireAuth() {
        if (!self::isAuthenticated()) {
            http_response_code(401);
            echo json_encode(['error' => 'Authentication required']);
            exit;
        }
    }

    public static function requirePermission($permission, $module = null) {
        self::requireAuth();

        if (!self::hasPermission($permission, $module)) {
            http_response_code(403);
            echo json_encode(['error' => 'Insufficient permissions']);
            exit;
        }
    }

    public static function requireRole($roleName) {
        self::requireAuth();

        if (!self::hasRole($roleName)) {
            http_response_code(403);
            echo json_encode(['error' => 'Insufficient role permissions']);
            exit;
        }
    }
}
?>
