<?php
/**
 * CSRF Protection Service
 *
 * Provides Cross-Site Request Forgery protection for the Flight Control System
 * Generates and validates CSRF tokens for state-changing operations
 */

class CSRF
{
    private static $instance = null;
    private $tokenName = 'csrf_token';
    private $tokenLength = 32;
    private $tokenLifetime = 3600; // 1 hour

    /**
     * Get singleton instance
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor for singleton
     */
    private function __construct()
    {
        $this->initializeSession();
    }

    /**
     * Initialize session for CSRF token storage
     */
    private function initializeSession()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Initialize CSRF token storage if not exists
        if (!isset($_SESSION['csrf_tokens'])) {
            $_SESSION['csrf_tokens'] = [];
        }
    }

    /**
     * Generate a new CSRF token
     */
    public function generateToken()
    {
        $token = bin2hex(random_bytes($this->tokenLength));
        $expires = time() + $this->tokenLifetime;

        // Store token with expiration
        $_SESSION['csrf_tokens'][$token] = $expires;

        // Clean up expired tokens
        $this->cleanupExpiredTokens();

        return $token;
    }

    /**
     * Validate CSRF token
     */
    public function validateToken($token)
    {
        // Check if token exists and is not expired
        if (!isset($_SESSION['csrf_tokens'][$token])) {
            return false;
        }

        $expires = $_SESSION['csrf_tokens'][$token];

        if (time() > $expires) {
            // Token expired, remove it
            unset($_SESSION['csrf_tokens'][$token]);
            return false;
        }

        // Token is valid, remove it to prevent reuse
        unset($_SESSION['csrf_tokens'][$token]);
        return true;
    }

    /**
     * Get CSRF token for forms
     */
    public function getToken()
    {
        // Return existing token if available and not expired
        foreach ($_SESSION['csrf_tokens'] as $token => $expires) {
            if (time() <= $expires) {
                return $token;
            } else {
                // Clean up expired token
                unset($_SESSION['csrf_tokens'][$token]);
            }
        }

        // Generate new token if no valid token exists
        return $this->generateToken();
    }

    /**
     * Validate CSRF token from request
     */
    public function validateRequest()
    {
        // Get token from various sources
        $token = $this->getTokenFromRequest();

        if (!$token) {
            return false;
        }

        return $this->validateToken($token);
    }

    /**
     * Get CSRF token from request (POST, GET, or header)
     */
    private function getTokenFromRequest()
    {
        // Check POST data
        if (isset($_POST[$this->tokenName])) {
            return $_POST[$this->tokenName];
        }

        // Check GET data
        if (isset($_GET[$this->tokenName])) {
            return $_GET[$this->tokenName];
        }

        // Check headers
        $headers = getallheaders();
        $headerName = 'X-CSRF-Token';

        if (isset($headers[$headerName])) {
            return $headers[$headerName];
        }

        // Check Authorization header for Bearer token format
        if (isset($headers['Authorization'])) {
            $authHeader = $headers['Authorization'];
            if (preg_match('/Bearer\s+(.+)/', $authHeader, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    /**
     * Generate hidden input field for forms
     */
    public function getHiddenInput()
    {
        $token = $this->getToken();
        return '<input type="hidden" name="' . $this->tokenName . '" value="' . $token . '">';
    }

    /**
     * Generate meta tag for AJAX requests
     */
    public function getMetaTag()
    {
        $token = $this->getToken();
        return '<meta name="csrf-token" content="' . $token . '">';
    }

    /**
     * Get token for AJAX requests
     */
    public function getAjaxToken()
    {
        return $this->getToken();
    }

    /**
     * Clean up expired tokens
     */
    private function cleanupExpiredTokens()
    {
        $now = time();
        foreach ($_SESSION['csrf_tokens'] as $token => $expires) {
            if ($now > $expires) {
                unset($_SESSION['csrf_tokens'][$token]);
            }
        }
    }

    /**
     * Middleware function for protecting routes
     */
    public function protectRoute()
    {
        if (!$this->validateRequest()) {
            http_response_code(403);
            echo json_encode([
                'error' => 'CSRF token validation failed',
                'message' => 'Invalid or missing CSRF token'
            ]);
            exit;
        }
    }

    /**
     * Check if current request method requires CSRF protection
     */
    public function requiresProtection()
    {
        $protectedMethods = ['POST', 'PUT', 'DELETE', 'PATCH'];
        return in_array($_SERVER['REQUEST_METHOD'], $protectedMethods);
    }

    /**
     * Get statistics about CSRF tokens
     */
    public function getStats()
    {
        $this->cleanupExpiredTokens();

        return [
            'active_tokens' => count($_SESSION['csrf_tokens']),
            'token_lifetime' => $this->tokenLifetime,
            'token_length' => $this->tokenLength,
            'protected_methods' => ['POST', 'PUT', 'DELETE', 'PATCH']
        ];
    }
}

// Usage examples:
/*
// Generate token for form
$csrf = CSRF::getInstance();
$tokenInput = $csrf->getHiddenInput();

// Validate request in API endpoint
if ($csrf->requiresProtection()) {
    $csrf->protectRoute();
}

// Get token for AJAX requests
$ajaxToken = $csrf->getAjaxToken();

// Add meta tag to HTML head
$metaTag = $csrf->getMetaTag();
*/
?>
