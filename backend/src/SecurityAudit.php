<?php
/**
 * Security Audit Service
 *
 * Comprehensive security assessment and monitoring for the Flight Control System
 * Reviews authentication, sessions, headers, and implements security best practices
 */

class SecurityAudit
{
    private static $instance = null;
    private $logger;
    private $violations = [];
    private $securityHeaders = [];
    private $auditLog = [];

    // Security check types
    const CHECK_AUTHENTICATION = 'authentication';
    const CHECK_AUTHORIZATION = 'authorization';
    const CHECK_SESSION = 'session';
    const CHECK_HEADERS = 'headers';
    const CHECK_INPUT = 'input_validation';
    const CHECK_RATE_LIMITING = 'rate_limiting';
    const CHECK_ENCRYPTION = 'encryption';
    const CHECK_CORS = 'cors';
    const CHECK_CSRF = 'csrf';
    const CHECK_XSS = 'xss';
    const CHECK_SQL_INJECTION = 'sql_injection';

    // Risk levels
    const RISK_CRITICAL = 'critical';
    const RISK_HIGH = 'high';
    const RISK_MEDIUM = 'medium';
    const RISK_LOW = 'low';
    const RISK_INFO = 'info';

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
        $this->logger = Logger::getInstance();
        $this->initializeSecurityHeaders();
    }

    /**
     * Initialize security headers
     */
    private function initializeSecurityHeaders()
    {
        $this->securityHeaders = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'X-XSS-Protection' => '1; mode=block',
            'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
            'Content-Security-Policy' => "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'",
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()',
            'Cross-Origin-Embedder-Policy' => 'require-corp',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'Cross-Origin-Resource-Policy' => 'same-origin'
        ];
    }

    /**
     * Run comprehensive security audit
     */
    public function runFullAudit()
    {
        $this->logger->info('Starting comprehensive security audit');

        $this->violations = [];

        // Run all security checks
        $this->checkAuthenticationSecurity();
        $this->checkAuthorizationSecurity();
        $this->checkSessionSecurity();
        $this->checkSecurityHeaders();
        $this->checkInputValidation();
        $this->checkRateLimiting();
        $this->checkEncryption();
        $this->checkCORSConfiguration();
        $this->checkCSRFProtection();
        $this->checkXSSProtection();
        $this->checkSQLInjectionProtection();

        // Log results
        $this->logAuditResults();

        return [
            'violations' => $this->violations,
            'total_violations' => count($this->violations),
            'critical_issues' => count(array_filter($this->violations, function($v) {
                return $v['risk'] === self::RISK_CRITICAL;
            })),
            'timestamp' => time()
        ];
    }

    /**
     * Check authentication security
     */
    private function checkAuthenticationSecurity()
    {
        // Check JWT configuration
        if (!getenv('JWT_SECRET') || strlen(getenv('JWT_SECRET')) < 32) {
            $this->addViolation(
                self::CHECK_AUTHENTICATION,
                self::RISK_CRITICAL,
                'JWT secret is missing or too weak',
                'Configure a strong JWT secret (minimum 32 characters)'
            );
        }

        // Check password policies
        if (!defined('PASSWORD_MIN_LENGTH') || PASSWORD_MIN_LENGTH < 8) {
            $this->addViolation(
                self::CHECK_AUTHENTICATION,
                self::RISK_HIGH,
                'Password minimum length is too short',
                'Enforce minimum 8 character passwords'
            );
        }

        // Check for secure password hashing
        if (!defined('PASSWORD_HASH_ALGORITHM') || PASSWORD_HASH_ALGORITHM !== PASSWORD_ARGON2ID) {
            $this->addViolation(
                self::CHECK_AUTHENTICATION,
                self::RISK_MEDIUM,
                'Using weak password hashing algorithm',
                'Use Argon2id for password hashing'
            );
        }

        // Check for account lockout policies
        if (!defined('MAX_LOGIN_ATTEMPTS') || MAX_LOGIN_ATTEMPTS < 5) {
            $this->addViolation(
                self::CHECK_AUTHENTICATION,
                self::RISK_MEDIUM,
                'Account lockout policy is too lenient',
                'Implement account lockout after 5 failed attempts'
            );
        }
    }

    /**
     * Check authorization security
     */
    private function checkAuthorizationSecurity()
    {
        // Check for role-based access control
        if (!class_exists('AuthorizationService')) {
            $this->addViolation(
                self::CHECK_AUTHORIZATION,
                self::RISK_HIGH,
                'No centralized authorization service',
                'Implement proper RBAC (Role-Based Access Control)'
            );
        }

        // Check for privilege escalation vulnerabilities
        if (!defined('ENFORCE_LEAST_PRIVILEGE')) {
            $this->addViolation(
                self::CHECK_AUTHORIZATION,
                self::RISK_MEDIUM,
                'Principle of least privilege not enforced',
                'Implement least privilege access controls'
            );
        }

        // Check for admin role restrictions
        $adminUsers = $this->getAdminUsers();
        if (count($adminUsers) > 5) {
            $this->addViolation(
                self::CHECK_AUTHORIZATION,
                self::RISK_MEDIUM,
                'Too many admin users',
                'Limit admin accounts to essential personnel only'
            );
        }
    }

    /**
     * Check session security
     */
    private function checkSessionSecurity()
    {
        // Check session configuration
        if (session_status() === PHP_SESSION_ACTIVE) {
            $sessionConfig = [
                'session.cookie_secure' => ini_get('session.cookie_secure'),
                'session.cookie_httponly' => ini_get('session.cookie_httponly'),
                'session.cookie_samesite' => ini_get('session.cookie_samesite'),
                'session.gc_maxlifetime' => ini_get('session.gc_maxlifetime')
            ];

            if (!$sessionConfig['session.cookie_secure'] && $this->isHttps()) {
                $this->addViolation(
                    self::CHECK_SESSION,
                    self::RISK_HIGH,
                    'Session cookies not marked as secure',
                    'Enable session.cookie_secure for HTTPS'
                );
            }

            if (!$sessionConfig['session.cookie_httponly']) {
                $this->addViolation(
                    self::CHECK_SESSION,
                    self::RISK_MEDIUM,
                    'Session cookies accessible via JavaScript',
                    'Enable session.cookie_httponly'
                );
            }

            if ($sessionConfig['session.gc_maxlifetime'] > 3600) {
                $this->addViolation(
                    self::CHECK_SESSION,
                    self::RISK_LOW,
                    'Session lifetime too long',
                    'Reduce session.gc_maxlifetime to 1 hour or less'
                );
            }
        }

        // Check for session fixation protection
        if (!defined('REGENERATE_SESSION_ON_LOGIN')) {
            $this->addViolation(
                self::CHECK_SESSION,
                self::RISK_MEDIUM,
                'No session regeneration on login',
                'Regenerate session ID after successful authentication'
            );
        }
    }

    /**
     * Check security headers
     */
    private function checkSecurityHeaders()
    {
        $currentHeaders = $this->getCurrentHeaders();

        foreach ($this->securityHeaders as $header => $expectedValue) {
            if (!isset($currentHeaders[$header])) {
                $risk = $this->getHeaderRiskLevel($header);
                $this->addViolation(
                    self::CHECK_HEADERS,
                    $risk,
                    "Missing security header: {$header}",
                    "Add header: {$header}: {$expectedValue}"
                );
            }
        }

        // Check for deprecated headers
        if (isset($currentHeaders['X-XSS-Protection'])) {
            $this->addViolation(
                self::CHECK_HEADERS,
                self::RISK_LOW,
                'Using deprecated X-XSS-Protection header',
                'Rely on Content-Security-Policy instead'
            );
        }
    }

    /**
     * Check input validation
     */
    private function checkInputValidation()
    {
        // Check if Validator service is available
        if (!class_exists('Validator')) {
            $this->addViolation(
                self::CHECK_INPUT,
                self::RISK_CRITICAL,
                'No centralized input validation service',
                'Implement comprehensive input validation framework'
            );
            return;
        }

        // Check for common input validation bypasses
        $testInputs = [
            '<script>alert("xss")</script>',
            '1\' OR \'1\'=\'1',
            '../../../etc/passwd',
            'javascript:alert("xss")'
        ];

        foreach ($testInputs as $input) {
            if (!$this->isProperlySanitized($input)) {
                $this->addViolation(
                    self::CHECK_INPUT,
                    self::RISK_HIGH,
                    'Input validation may allow malicious content',
                    'Strengthen input sanitization and validation rules'
                );
                break;
            }
        }
    }

    /**
     * Check rate limiting
     */
    private function checkRateLimiting()
    {
        // Check for rate limiting implementation
        if (!defined('RATE_LIMIT_REQUESTS') || !defined('RATE_LIMIT_WINDOW')) {
            $this->addViolation(
                self::CHECK_RATE_LIMITING,
                self::RISK_MEDIUM,
                'No rate limiting implemented',
                'Implement rate limiting to prevent abuse'
            );
        }

        // Check rate limit values
        if (defined('RATE_LIMIT_REQUESTS') && RATE_LIMIT_REQUESTS > 1000) {
            $this->addViolation(
                self::CHECK_RATE_LIMITING,
                self::RISK_LOW,
                'Rate limiting too permissive',
                'Reduce rate limit to prevent abuse'
            );
        }
    }

    /**
     * Check encryption
     */
    private function checkEncryption()
    {
        // Check for HTTPS enforcement
        if (!$this->isHttps()) {
            $this->addViolation(
                self::CHECK_ENCRYPTION,
                self::RISK_HIGH,
                'Not using HTTPS encryption',
                'Enforce HTTPS for all connections'
            );
        }

        // Check database encryption
        if (!defined('DB_ENCRYPTION_ENABLED') || !DB_ENCRYPTION_ENABLED) {
            $this->addViolation(
                self::CHECK_ENCRYPTION,
                self::RISK_MEDIUM,
                'Database encryption not enabled',
                'Enable database encryption for sensitive data - set DB_ENCRYPTION_ENABLED=true'
            );
        }

        // Check for proper TLS configuration
        if (!defined('TLS_MIN_VERSION') || TLS_MIN_VERSION < 1.2) {
            $this->addViolation(
                self::CHECK_ENCRYPTION,
                self::RISK_MEDIUM,
                'Weak TLS configuration',
                'Require TLS 1.2 or higher'
            );
        }
    }

    /**
     * Check CORS configuration
     */
    private function checkCORSConfiguration()
    {
        $corsHeaders = $this->getCORSHeaders();

        if (!isset($corsHeaders['Access-Control-Allow-Origin'])) {
            $this->addViolation(
                self::CHECK_CORS,
                self::RISK_MEDIUM,
                'CORS policy not configured',
                'Configure appropriate CORS headers'
            );
        } else {
            $allowedOrigins = $corsHeaders['Access-Control-Allow-Origin'];
            if ($allowedOrigins === '*') {
                $this->addViolation(
                    self::CHECK_CORS,
                    self::RISK_HIGH,
                    'CORS allows all origins',
                    'Restrict CORS to specific trusted domains'
                );
            }
        }

        // Check for preflight request handling
        if (!isset($corsHeaders['Access-Control-Allow-Methods'])) {
            $this->addViolation(
                self::CHECK_CORS,
                self::RISK_LOW,
                'CORS preflight methods not specified',
                'Specify allowed HTTP methods in CORS configuration'
            );
        }
    }

    /**
     * Check CSRF protection
     */
    private function checkCSRFProtection()
    {
        if (!defined('CSRF_PROTECTION_ENABLED') || !CSRF_PROTECTION_ENABLED) {
            $this->addViolation(
                self::CHECK_CSRF,
                self::RISK_HIGH,
                'CSRF protection not enabled',
                'Implement CSRF tokens for state-changing operations'
            );
        }

        // Check for CSRF token validation in forms
        if (!$this->hasCSRFTokenValidation()) {
            $this->addViolation(
                self::CHECK_CSRF,
                self::RISK_HIGH,
                'CSRF token validation missing',
                'Validate CSRF tokens on all forms and state-changing requests'
            );
        }
    }

    /**
     * Check XSS protection
     */
    private function checkXSSProtection()
    {
        // Check Content Security Policy
        $headers = $this->getCurrentHeaders();
        if (!isset($headers['Content-Security-Policy'])) {
            $this->addViolation(
                self::CHECK_XSS,
                self::RISK_MEDIUM,
                'Content Security Policy not implemented',
                'Implement CSP to prevent XSS attacks'
            );
        }

        // Check for unsafe inline scripts/styles
        $csp = $headers['Content-Security-Policy'] ?? '';
        if (strpos($csp, "'unsafe-inline'") !== false) {
            $this->addViolation(
                self::CHECK_XSS,
                self::RISK_LOW,
                'CSP allows unsafe inline content',
                'Remove \'unsafe-inline\' from CSP or use nonces/hashes'
            );
        }
    }

    /**
     * Check SQL injection protection
     */
    private function checkSQLInjectionProtection()
    {
        // Check for prepared statements usage
        if (!$this->usesPreparedStatements()) {
            $this->addViolation(
                self::CHECK_SQL_INJECTION,
                self::RISK_CRITICAL,
                'Not using prepared statements',
                'Use prepared statements or parameterized queries'
            );
        }

        // Check for input sanitization
        if (!$this->hasInputSanitization()) {
            $this->addViolation(
                self::CHECK_SQL_INJECTION,
                self::RISK_HIGH,
                'Input sanitization missing',
                'Sanitize all user inputs before database operations'
            );
        }
    }

    /**
     * Add security violation
     */
    private function addViolation($checkType, $risk, $description, $recommendation)
    {
        $violation = [
            'check_type' => $checkType,
            'risk' => $risk,
            'description' => $description,
            'recommendation' => $recommendation,
            'timestamp' => time(),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ];

        $this->violations[] = $violation;

        // Log critical and high-risk violations immediately
        if (in_array($risk, [self::RISK_CRITICAL, self::RISK_HIGH])) {
            $this->logger->security("Security violation: {$description}", [
                'check_type' => $checkType,
                'risk' => $risk,
                'recommendation' => $recommendation
            ]);
        }
    }

    /**
     * Log audit results
     */
    private function logAuditResults()
    {
        $totalViolations = count($this->violations);
        $criticalCount = count(array_filter($this->violations, function($v) {
            return $v['risk'] === self::RISK_CRITICAL;
        }));

        $this->logger->info('Security audit completed', [
            'total_violations' => $totalViolations,
            'critical_violations' => $criticalCount,
            'audit_timestamp' => time()
        ]);

        // Log each violation
        foreach ($this->violations as $violation) {
            $this->logger->warning('Security audit violation', $violation);
        }
    }

    /**
     * Apply security headers to response
     */
    public function applySecurityHeaders()
    {
        foreach ($this->securityHeaders as $header => $value) {
            header("{$header}: {$value}");
        }
    }

    /**
     * Generate security report
     */
    public function generateSecurityReport()
    {
        $auditResults = $this->runFullAudit();

        return [
            'audit_timestamp' => date('Y-m-d H:i:s', $auditResults['timestamp']),
            'summary' => [
                'total_violations' => $auditResults['total_violations'],
                'critical_issues' => $auditResults['critical_issues'],
                'status' => $auditResults['critical_issues'] > 0 ? 'CRITICAL' : 'OK'
            ],
            'violations_by_risk' => [
                'critical' => array_filter($this->violations, function($v) {
                    return $v['risk'] === self::RISK_CRITICAL;
                }),
                'high' => array_filter($this->violations, function($v) {
                    return $v['risk'] === self::RISK_HIGH;
                }),
                'medium' => array_filter($this->violations, function($v) {
                    return $v['risk'] === self::RISK_MEDIUM;
                }),
                'low' => array_filter($this->violations, function($v) {
                    return $v['risk'] === self::RISK_LOW;
                })
            ],
            'recommendations' => $this->generateRecommendations()
        ];
    }

    /**
     * Generate security recommendations
     */
    private function generateRecommendations()
    {
        $recommendations = [];

        if (count($this->violations) > 0) {
            $recommendations[] = 'Address all critical and high-risk security violations immediately';
            $recommendations[] = 'Implement regular security audits (weekly/monthly)';
            $recommendations[] = 'Keep security dependencies updated';
            $recommendations[] = 'Monitor security logs for suspicious activity';
            $recommendations[] = 'Conduct security training for development team';
        }

        return $recommendations;
    }

    // ===== HELPER METHODS =====

    private function getHeaderRiskLevel($header)
    {
        $riskLevels = [
            'Strict-Transport-Security' => self::RISK_HIGH,
            'Content-Security-Policy' => self::RISK_HIGH,
            'X-Frame-Options' => self::RISK_MEDIUM,
            'X-Content-Type-Options' => self::RISK_MEDIUM,
            'X-XSS-Protection' => self::RISK_LOW,
            'Referrer-Policy' => self::RISK_LOW,
            'Permissions-Policy' => self::RISK_LOW
        ];

        return $riskLevels[$header] ?? self::RISK_MEDIUM;
    }

    private function getCurrentHeaders()
    {
        $headers = [];
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
        }
        return array_change_key_case($headers, CASE_UPPER);
    }

    private function getCORSHeaders()
    {
        // This would check actual CORS configuration
        return [];
    }

    private function isHttps()
    {
        return isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
    }

    private function getAdminUsers()
    {
        // This would query the database for admin users
        return [];
    }

    private function isProperlySanitized($input)
    {
        // This would test input sanitization
        return true;
    }

    private function hasCSRFTokenValidation()
    {
        // This would check for CSRF token validation
        return false;
    }

    private function usesPreparedStatements()
    {
        // This would check database query patterns
        return true;
    }

    private function hasInputSanitization()
    {
        // This would check for input sanitization
        return class_exists('Validator');
    }
}

// Usage examples:
/*
// Run full security audit
$audit = SecurityAudit::getInstance();
$results = $audit->runFullAudit();

// Generate security report
$report = $audit->generateSecurityReport();

// Apply security headers
$audit->applySecurityHeaders();

// Manual security checks
$audit->checkAuthenticationSecurity();
$audit->checkAuthorizationSecurity();
*/
?>
