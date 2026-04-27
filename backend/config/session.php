<?php
/**
 * Session Configuration
 *
 * Secure session management configuration for the Flight Control System
 * Implements best practices for session security and management
 */

// Session configuration
ini_set('session.name', 'flight_control_session');
ini_set('session.cookie_secure', 1); // HTTPS only
ini_set('session.cookie_httponly', 1); // Prevent JavaScript access
ini_set('session.cookie_samesite', 'Strict'); // CSRF protection
ini_set('session.gc_maxlifetime', 3600); // 1 hour
ini_set('session.cookie_lifetime', 0); // Session cookie (expires on browser close)
ini_set('session.use_strict_mode', 1); // Prevent session fixation
ini_set('session.use_only_cookies', 1); // Disable URL-based sessions
ini_set('session.cookie_path', '/');
ini_set('session.cookie_domain', getenv('SESSION_DOMAIN') ?: '');

// Custom session save handler for better security
class SecureSessionHandler extends SessionHandler
{
    private $key;
    private $name;
    private $cookie;

    public function __construct($key, $name = 'flight_control_session')
    {
        $this->key = $key;
        $this->name = $name;
        $this->cookie = [
            'lifetime' => 0,
            'path' => '/',
            'domain' => getenv('SESSION_DOMAIN') ?: '',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict'
        ];
    }

    public function read($id)
    {
        $data = parent::read($id);

        if (!$data) {
            return '';
        }

        $data = $this->decrypt($data, $this->key);

        return $data;
    }

    public function write($id, $data)
    {
        $data = $this->encrypt($data, $this->key);

        return parent::write($id, $data);
    }

    private function encrypt($data, $key)
    {
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }

    private function decrypt($data, $key)
    {
        $data = base64_decode($data);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);

        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }
}

// Initialize secure session handler
$sessionKey = getenv('SESSION_ENCRYPTION_KEY') ?: 'default_session_key_change_in_production';
$sessionHandler = new SecureSessionHandler($sessionKey);
session_set_save_handler($sessionHandler, true);

// Start session with security checks
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Regenerate session ID for authenticated users to prevent fixation
if (isset($_SESSION['user_id']) && !isset($_SESSION['regenerated'])) {
    session_regenerate_id(true);
    $_SESSION['regenerated'] = true;
}

// Session timeout check
if (isset($_SESSION['last_activity'])) {
    $inactiveTime = time() - $_SESSION['last_activity'];

    if ($inactiveTime > 1800) { // 30 minutes
        session_unset();
        session_destroy();
        session_start();
    }
}

$_SESSION['last_activity'] = time();

// CSRF token management
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['csrf_token_time'] = time();
}

// Regenerate CSRF token every 15 minutes
if (isset($_SESSION['csrf_token_time']) && (time() - $_SESSION['csrf_token_time']) > 900) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['csrf_token_time'] = time();
}

// Session security functions
class SessionManager
{
    /**
     * Get current session ID securely
     */
    public static function getSessionId()
    {
        return session_id();
    }

    /**
     * Regenerate session ID
     */
    public static function regenerateId($deleteOld = true)
    {
        session_regenerate_id($deleteOld);
        unset($_SESSION['regenerated']);
    }

    /**
     * Get CSRF token
     */
    public static function getCsrfToken()
    {
        return $_SESSION['csrf_token'] ?? null;
    }

    /**
     * Validate CSRF token
     */
    public static function validateCsrfToken($token)
    {
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Destroy session securely
     */
    public static function destroy()
    {
        // Clear all session data
        $_SESSION = [];

        // Delete session cookie
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }

        // Destroy session
        session_destroy();
    }

    /**
     * Check if user is authenticated
     */
    public static function isAuthenticated()
    {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }

    /**
     * Get authenticated user ID
     */
    public static function getUserId()
    {
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Get authenticated user role
     */
    public static function getUserRole()
    {
        return $_SESSION['user_role'] ?? null;
    }

    /**
     * Set authenticated user
     */
    public static function setUser($userId, $userRole = null)
    {
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_role'] = $userRole;
        $_SESSION['login_time'] = time();
    }

    /**
     * Get session statistics
     */
    public static function getStats()
    {
        return [
            'session_id' => session_id(),
            'start_time' => $_SESSION['login_time'] ?? null,
            'last_activity' => $_SESSION['last_activity'] ?? null,
            'is_authenticated' => self::isAuthenticated(),
            'user_id' => self::getUserId(),
            'user_role' => self::getUserRole(),
            'csrf_token_age' => isset($_SESSION['csrf_token_time']) ? time() - $_SESSION['csrf_token_time'] : null
        ];
    }

    /**
     * Clean up expired sessions (maintenance function)
     */
    public static function cleanup()
    {
        // This would typically be handled by PHP's garbage collection
        // But we can add custom cleanup logic here if needed
        $inactiveThreshold = 3600; // 1 hour

        if (isset($_SESSION['last_activity'])) {
            if ((time() - $_SESSION['last_activity']) > $inactiveThreshold) {
                self::destroy();
                return true;
            }
        }

        return false;
    }
}

// Security headers for session protection
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Content Security Policy
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:; connect-src 'self' wss: https:;");

// Prevent clickjacking
header('X-Frame-Options: SAMEORIGIN');

// Prevent MIME type sniffing
header('X-Content-Type-Options: nosniff');

// Enable XSS filtering
header('X-XSS-Protection: 1; mode=block');

// Force HTTPS
if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
    if (!in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1'])) {
        header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
        exit;
    }
}

?>
