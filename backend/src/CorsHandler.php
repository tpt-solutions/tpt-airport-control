<?php
/**
 * Global CORS Handler
 *
 * Handles Cross-Origin Resource Sharing for all API endpoints
 * Provides proper origin validation and preflight request handling
 */

class CorsHandler
{
    /**
     * Handle CORS preflight OPTIONS request
     */
    public static function handlePreflight()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            $config = Config::getInstance();
            
            if ($config->get('cors_enabled', true)) {
                $allowedOrigins = $config->get('cors_origins', ['*']);
                $allowedMethods = $config->get('cors_methods', ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS']);
                $allowedHeaders = $config->get('cors_headers', ['Content-Type', 'Authorization', 'X-Requested-With']);
                
                // Handle origin validation
                $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
                
                if (in_array('*', $allowedOrigins)) {
                    header('Access-Control-Allow-Origin: *');
                } elseif (in_array($origin, $allowedOrigins)) {
                    header('Access-Control-Allow-Origin: ' . $origin);
                    header('Vary: Origin');
                }
                
                header('Access-Control-Allow-Methods: ' . implode(', ', $allowedMethods));
                header('Access-Control-Allow-Headers: ' . implode(', ', $allowedHeaders));
                header('Access-Control-Allow-Credentials: true');
                header('Access-Control-Max-Age: 86400');
                
                // Return 200 OK for preflight
                http_response_code(200);
                exit();
            }
        }
    }
    
    /**
     * Apply CORS headers to current response
     */
    public static function applyHeaders()
    {
        $config = Config::getInstance();
        
        if ($config->get('cors_enabled', true)) {
            $allowedOrigins = $config->get('cors_origins', ['*']);
            
            // Handle origin validation
            $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
            
            if (in_array('*', $allowedOrigins)) {
                header('Access-Control-Allow-Origin: *');
            } elseif (in_array($origin, $allowedOrigins)) {
                header('Access-Control-Allow-Origin: ' . $origin);
                header('Vary: Origin');
            }
        }
    }
}
?>