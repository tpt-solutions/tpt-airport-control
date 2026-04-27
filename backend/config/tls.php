<?php
/**
 * TLS/SSL Configuration
 *
 * Secure TLS configuration for the Flight Control System
 * Implements modern TLS best practices and security standards
 */

// TLS configuration for different environments
class TLSConfig
{
    private static $instance = null;
    private $config = [];

    // TLS versions
    const TLS_1_2 = 'TLSv1.2';
    const TLS_1_3 = 'TLSv1.3';

    // Cipher suites for TLS 1.2
    private $tls12Ciphers = [
        'ECDHE-RSA-AES256-GCM-SHA384',
        'ECDHE-RSA-AES128-GCM-SHA256',
        'ECDHE-RSA-AES256-SHA384',
        'ECDHE-RSA-AES128-SHA256',
        'DHE-RSA-AES256-GCM-SHA384',
        'DHE-RSA-AES128-GCM-SHA256'
    ];

    // Cipher suites for TLS 1.3 (automatically negotiated)
    private $tls13Ciphers = [
        'TLS_AES_256_GCM_SHA384',
        'TLS_AES_128_GCM_SHA256',
        'TLS_CHACHA20_POLY1305_SHA256'
    ];

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
     * Private constructor
     */
    private function __construct()
    {
        $this->initializeConfig();
    }

    /**
     * Initialize TLS configuration
     */
    private function initializeConfig()
    {
        $env = getenv('APP_ENV') ?: 'production';

        if ($env === 'development') {
            $this->config = $this->getDevelopmentConfig();
        } else {
            $this->config = $this->getProductionConfig();
        }
    }

    /**
     * Get development TLS configuration
     */
    private function getDevelopmentConfig()
    {
        return [
            'min_version' => self::TLS_1_2,
            'ciphers' => implode(':', $this->tls12Ciphers),
            'cert_file' => getenv('TLS_CERT_FILE') ?: __DIR__ . '/../../ssl/dev.crt',
            'key_file' => getenv('TLS_KEY_FILE') ?: __DIR__ . '/../../ssl/dev.key',
            'ca_file' => getenv('TLS_CA_FILE') ?: __DIR__ . '/../../ssl/dev-ca.crt',
            'verify_peer' => false, // Allow self-signed certificates in development
            'verify_peer_name' => false,
            'allow_self_signed' => true,
            'SNI_enabled' => true,
            'disable_compression' => true,
            'session_cache' => 'shared:SSL:10m'
        ];
    }

    /**
     * Get production TLS configuration
     */
    private function getProductionConfig()
    {
        return [
            'min_version' => self::TLS_1_3,
            'ciphers' => implode(':', array_merge($this->tls13Ciphers, $this->tls12Ciphers)),
            'cert_file' => getenv('TLS_CERT_FILE') ?: '/etc/ssl/certs/flight-control.crt',
            'key_file' => getenv('TLS_KEY_FILE') ?: '/etc/ssl/private/flight-control.key',
            'ca_file' => getenv('TLS_CA_FILE') ?: '/etc/ssl/certs/ca-certificates.crt',
            'verify_peer' => true,
            'verify_peer_name' => true,
            'allow_self_signed' => false,
            'SNI_enabled' => true,
            'disable_compression' => true,
            'session_cache' => 'shared:SSL:50m',
            'hsts' => [
                'max_age' => 31536000, // 1 year
                'include_subdomains' => true,
                'preload' => true
            ],
            'ocsp' => [
                'enabled' => true,
                'url' => getenv('OCSP_URL') ?: null
            ]
        ];
    }

    /**
     * Get TLS configuration array
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * Get specific configuration value
     */
    public function get($key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * Set custom configuration value
     */
    public function set($key, $value)
    {
        $this->config[$key] = $value;
    }

    /**
     * Apply TLS configuration to PHP streams
     */
    public function applyToStreams()
    {
        $context = stream_context_create([
            'ssl' => $this->config
        ]);

        // Set default SSL context
        stream_context_set_default(['ssl' => $this->config]);

        return $context;
    }

    /**
     * Apply TLS configuration to cURL
     */
    public function applyToCurl($ch)
    {
        curl_setopt($ch, CURLOPT_SSLVERSION, $this->getSslVersion());
        curl_setopt($ch, CURLOPT_SSL_CIPHER_LIST, $this->config['ciphers']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->config['verify_peer'] ? 2 : 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->config['verify_peer']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYSTATUS, $this->config['ocsp']['enabled'] ?? false);

        if ($this->config['cert_file'] && file_exists($this->config['cert_file'])) {
            curl_setopt($ch, CURLOPT_SSLCERT, $this->config['cert_file']);
        }

        if ($this->config['key_file'] && file_exists($this->config['key_file'])) {
            curl_setopt($ch, CURLOPT_SSLKEY, $this->config['key_file']);
        }

        if ($this->config['ca_file'] && file_exists($this->config['ca_file'])) {
            curl_setopt($ch, CURLOPT_CAINFO, $this->config['ca_file']);
        }
    }

    /**
     * Get SSL version constant for cURL
     */
    private function getSslVersion()
    {
        switch ($this->config['min_version']) {
            case self::TLS_1_3:
                return CURL_SSLVERSION_TLSv1_3;
            case self::TLS_1_2:
            default:
                return CURL_SSLVERSION_TLSv1_2;
        }
    }

    /**
     * Generate self-signed certificate for development
     */
    public function generateSelfSignedCert($days = 365, $keySize = 2048)
    {
        $certDir = __DIR__ . '/../../ssl';
        if (!is_dir($certDir)) {
            mkdir($certDir, 0755, true);
        }

        $certFile = $certDir . '/dev.crt';
        $keyFile = $certDir . '/dev.key';

        // Generate private key
        $privateKey = openssl_pkey_new([
            'private_key_bits' => $keySize,
            'private_key_type' => OPENSSL_KEYTYPE_RSA
        ]);

        // Generate certificate
        $dn = [
            'countryName' => 'US',
            'stateOrProvinceName' => 'Development',
            'localityName' => 'Local',
            'organizationName' => 'Flight Control Dev',
            'organizationalUnitName' => 'Development',
            'commonName' => 'localhost',
            'emailAddress' => 'dev@flightcontrol.local'
        ];

        $csr = openssl_csr_new($dn, $privateKey, ['digest_alg' => 'sha256']);
        $cert = openssl_csr_sign($csr, null, $privateKey, $days, ['digest_alg' => 'sha256']);

        // Save certificate and private key
        openssl_x509_export($cert, $certOut);
        file_put_contents($certFile, $certOut);

        openssl_pkey_export($privateKey, $keyOut);
        file_put_contents($keyFile, $keyOut);

        // Set proper permissions
        chmod($certFile, 0644);
        chmod($keyFile, 0600);

        return [
            'cert_file' => $certFile,
            'key_file' => $keyFile,
            'generated' => true
        ];
    }

    /**
     * Check certificate validity
     */
    public function checkCertificate($certFile = null)
    {
        $certFile = $certFile ?: $this->config['cert_file'];

        if (!file_exists($certFile)) {
            return ['valid' => false, 'error' => 'Certificate file not found'];
        }

        $certData = file_get_contents($certFile);
        $cert = openssl_x509_parse($certData);

        if (!$cert) {
            return ['valid' => false, 'error' => 'Invalid certificate format'];
        }

        $now = time();
        $validFrom = $cert['validFrom_time_t'];
        $validTo = $cert['validTo_time_t'];

        if ($now < $validFrom) {
            return ['valid' => false, 'error' => 'Certificate not yet valid'];
        }

        if ($now > $validTo) {
            return ['valid' => false, 'error' => 'Certificate expired'];
        }

        $daysLeft = floor(($validTo - $now) / (60 * 60 * 24));

        return [
            'valid' => true,
            'subject' => $cert['subject'],
            'issuer' => $cert['issuer'],
            'valid_from' => date('Y-m-d H:i:s', $validFrom),
            'valid_to' => date('Y-m-d H:i:s', $validTo),
            'days_left' => $daysLeft,
            'serial_number' => $cert['serialNumber']
        ];
    }

    /**
     * Get security headers for HTTPS
     */
    public function getSecurityHeaders()
    {
        $headers = [];

        // HSTS header
        if (isset($this->config['hsts'])) {
            $hsts = $this->config['hsts'];
            $hstsValue = 'max-age=' . $hsts['max_age'];

            if ($hsts['include_subdomains']) {
                $hstsValue .= '; includeSubDomains';
            }

            if ($hsts['preload']) {
                $hstsValue .= '; preload';
            }

            $headers['Strict-Transport-Security'] = $hstsValue;
        }

        // Other security headers
        $headers['X-Frame-Options'] = 'DENY';
        $headers['X-Content-Type-Options'] = 'nosniff';
        $headers['X-XSS-Protection'] = '1; mode=block';
        $headers['Referrer-Policy'] = 'strict-origin-when-cross-origin';

        return $headers;
    }

    /**
     * Force HTTPS redirect
     */
    public function forceHttps()
    {
        if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
            if (!in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1', '::1'])) {
                $redirectUrl = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
                header('Location: ' . $redirectUrl, true, 301);
                exit;
            }
        }
    }

    /**
     * Get TLS statistics
     */
    public function getStats()
    {
        $certInfo = $this->checkCertificate();

        return [
            'tls_version' => $this->config['min_version'],
            'certificate_valid' => $certInfo['valid'] ?? false,
            'certificate_days_left' => $certInfo['days_left'] ?? 0,
            'ciphers_enabled' => count(explode(':', $this->config['ciphers'])),
            'peer_verification' => $this->config['verify_peer'],
            'compression_disabled' => $this->config['disable_compression'],
            'hsts_enabled' => isset($this->config['hsts']),
            'ocsp_enabled' => $this->config['ocsp']['enabled'] ?? false
        ];
    }
}

// Initialize TLS configuration
$tlsConfig = TLSConfig::getInstance();

// Apply security headers
$securityHeaders = $tlsConfig->getSecurityHeaders();
foreach ($securityHeaders as $header => $value) {
    header($header . ': ' . $value);
}

// Force HTTPS in production
if (getenv('APP_ENV') !== 'development') {
    $tlsConfig->forceHttps();
}

// Make TLS config available globally
$GLOBALS['tls_config'] = $tlsConfig;

?>
