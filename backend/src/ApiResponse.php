<?php
/**
 * API Response Format Standardization
 *
 * Provides consistent API response formatting across all endpoints
 * Implements JSON API specification with error handling and metadata
 */

class ApiResponse
{
    // HTTP Status Codes
    const HTTP_OK = 200;
    const HTTP_CREATED = 201;
    const HTTP_NO_CONTENT = 204;
    const HTTP_BAD_REQUEST = 400;
    const HTTP_UNAUTHORIZED = 401;
    const HTTP_FORBIDDEN = 403;
    const HTTP_NOT_FOUND = 404;
    const HTTP_METHOD_NOT_ALLOWED = 405;
    const HTTP_CONFLICT = 409;
    const HTTP_UNPROCESSABLE_ENTITY = 422;
    const HTTP_TOO_MANY_REQUESTS = 429;
    const HTTP_INTERNAL_SERVER_ERROR = 500;

    // Response Types
    const TYPE_SUCCESS = 'success';
    const TYPE_ERROR = 'error';
    const TYPE_VALIDATION_ERROR = 'validation_error';

    /**
     * Create a successful response
     */
    public static function success($data = null, $message = null, $statusCode = self::HTTP_OK, array $meta = [])
    {
        $response = [
            'status' => self::TYPE_SUCCESS,
            'timestamp' => time(),
            'request_id' => self::generateRequestId()
        ];

        if ($message !== null) {
            $response['message'] = $message;
        }

        if ($data !== null) {
            $response['data'] = $data;
        }

        if (!empty($meta)) {
            $response['meta'] = $meta;
        }

        self::sendJsonResponse($response, $statusCode);
    }

    /**
     * Create a created response (for POST operations)
     */
    public static function created($data = null, $message = 'Resource created successfully', array $meta = [])
    {
        self::success($data, $message, self::HTTP_CREATED, $meta);
    }

    /**
     * Create a no content response (for DELETE operations)
     */
    public static function noContent($message = 'Resource deleted successfully')
    {
        $response = [
            'status' => self::TYPE_SUCCESS,
            'message' => $message,
            'timestamp' => time(),
            'request_id' => self::generateRequestId()
        ];

        self::sendJsonResponse($response, self::HTTP_NO_CONTENT);
    }

    /**
     * Create an error response
     */
    public static function error($message, $statusCode = self::HTTP_INTERNAL_SERVER_ERROR, $errorCode = null, array $details = [])
    {
        $response = [
            'status' => self::TYPE_ERROR,
            'message' => $message,
            'timestamp' => time(),
            'request_id' => self::generateRequestId()
        ];

        if ($errorCode !== null) {
            $response['error_code'] = $errorCode;
        }

        if (!empty($details)) {
            $response['details'] = $details;
        }

        // Log error for server errors
        if ($statusCode >= 500) {
            $logger = Logger::getInstance();
            $logger->error('API Error Response', [
                'message' => $message,
                'status_code' => $statusCode,
                'error_code' => $errorCode,
                'details' => $details,
                'request_id' => $response['request_id']
            ]);
        }

        self::sendJsonResponse($response, $statusCode);
    }

    /**
     * Create a validation error response
     */
    public static function validationError(array $errors, $message = 'Validation failed')
    {
        $response = [
            'status' => self::TYPE_VALIDATION_ERROR,
            'message' => $message,
            'errors' => $errors,
            'timestamp' => time(),
            'request_id' => self::generateRequestId()
        ];

        self::sendJsonResponse($response, self::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * Create a not found response
     */
    public static function notFound($resource = 'Resource', $message = null)
    {
        $message = $message ?: "{$resource} not found";
        self::error($message, self::HTTP_NOT_FOUND, 'RESOURCE_NOT_FOUND');
    }

    /**
     * Create an unauthorized response
     */
    public static function unauthorized($message = 'Authentication required')
    {
        self::error($message, self::HTTP_UNAUTHORIZED, 'UNAUTHORIZED');
    }

    /**
     * Create a forbidden response
     */
    public static function forbidden($message = 'Access denied')
    {
        self::error($message, self::HTTP_FORBIDDEN, 'FORBIDDEN');
    }

    /**
     * Create a bad request response
     */
    public static function badRequest($message = 'Bad request', $errorCode = null, array $details = [])
    {
        self::error($message, self::HTTP_BAD_REQUEST, $errorCode, $details);
    }

    /**
     * Create a conflict response
     */
    public static function conflict($message = 'Resource conflict', $errorCode = null)
    {
        self::error($message, self::HTTP_CONFLICT, $errorCode);
    }

    /**
     * Create a too many requests response
     */
    public static function tooManyRequests($message = 'Too many requests', $retryAfter = null)
    {
        $headers = [];
        if ($retryAfter !== null) {
            $headers['Retry-After'] = $retryAfter;
        }

        $response = [
            'status' => self::TYPE_ERROR,
            'message' => $message,
            'error_code' => 'RATE_LIMIT_EXCEEDED',
            'timestamp' => time(),
            'request_id' => self::generateRequestId()
        ];

        if ($retryAfter !== null) {
            $response['retry_after'] = $retryAfter;
        }

        self::sendJsonResponse($response, self::HTTP_TOO_MANY_REQUESTS, $headers);
    }

    /**
     * Create a paginated response
     */
    public static function paginated($data, $pagination, $message = null, array $meta = [])
    {
        $meta = array_merge($meta, [
            'pagination' => [
                'page' => $pagination['page'] ?? 1,
                'limit' => $pagination['limit'] ?? 50,
                'total' => $pagination['total'] ?? 0,
                'total_pages' => $pagination['total_pages'] ?? 0,
                'has_next' => $pagination['has_next'] ?? false,
                'has_prev' => $pagination['has_prev'] ?? false
            ]
        ]);

        self::success($data, $message, self::HTTP_OK, $meta);
    }

    /**
     * Send JSON response
     */
    private static function sendJsonResponse(array $response, $statusCode, array $additionalHeaders = [])
    {
        // Set content type
        header('Content-Type: application/json; charset=utf-8');

        // Set status code
        http_response_code($statusCode);

        // Set additional headers
        foreach ($additionalHeaders as $header => $value) {
            header("{$header}: {$value}");
        }

        // Apply security headers
        $securityAudit = SecurityAudit::getInstance();
        $securityAudit->applySecurityHeaders();

        // Set CORS headers if not already set
        if (!isset($additionalHeaders['Access-Control-Allow-Origin'])) {
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
            }
        }

        // Output JSON
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        // Exit to prevent further output
        exit();
    }

    /**
     * Generate unique request ID
     */
    private static function generateRequestId()
    {
        return uniqid('req_', true);
    }

    /**
     * Handle API exceptions
     */
    public static function handleException(Exception $e)
    {
        $logger = Logger::getInstance();

        // Log the exception
        $logger->error('Unhandled API Exception', [
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);

        // Return generic error response
        self::error('An unexpected error occurred', self::HTTP_INTERNAL_SERVER_ERROR, 'INTERNAL_ERROR');
    }

    /**
     * Handle validation exceptions
     */
    public static function handleValidationException(ValidationException $e)
    {
        self::validationError($e->getErrors(), $e->getMessage());
    }
}

/**
 * API Response Builder for complex responses
 */
class ApiResponseBuilder
{
    private $status = ApiResponse::TYPE_SUCCESS;
    private $data = null;
    private $message = null;
    private $meta = [];
    private $statusCode = ApiResponse::HTTP_OK;
    private $headers = [];

    /**
     * Set response status
     */
    public function status($status)
    {
        $this->status = $status;
        return $this;
    }

    /**
     * Set response data
     */
    public function data($data)
    {
        $this->data = $data;
        return $this;
    }

    /**
     * Set response message
     */
    public function message($message)
    {
        $this->message = $message;
        return $this;
    }

    /**
     * Add metadata
     */
    public function meta($key, $value = null)
    {
        if (is_array($key)) {
            $this->meta = array_merge($this->meta, $key);
        } else {
            $this->meta[$key] = $value;
        }
        return $this;
    }

    /**
     * Set HTTP status code
     */
    public function statusCode($code)
    {
        $this->statusCode = $code;
        return $this;
    }

    /**
     * Add custom header
     */
    public function header($name, $value)
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Build and send the response
     */
    public function send()
    {
        $response = [
            'status' => $this->status,
            'timestamp' => time(),
            'request_id' => uniqid('req_', true)
        ];

        if ($this->message !== null) {
            $response['message'] = $this->message;
        }

        if ($this->data !== null) {
            $response['data'] = $this->data;
        }

        if (!empty($this->meta)) {
            $response['meta'] = $this->meta;
        }

        // Send the response
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($this->statusCode);

        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        // Apply security headers
        $securityAudit = SecurityAudit::getInstance();
        $securityAudit->applySecurityHeaders();

        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }
}

/**
 * Custom exception for validation errors
 */
class ValidationException extends Exception
{
    private $errors = [];

    public function __construct(array $errors, $message = 'Validation failed')
    {
        parent::__construct($message);
        $this->errors = $errors;
    }

    public function getErrors()
    {
        return $this->errors;
    }
}

/**
 * API Resource wrapper for consistent data formatting
 */
class ApiResource
{
    private $data;
    private $resourceType;
    private $includes = [];
    private $links = [];

    public function __construct($data, $resourceType = null)
    {
        $this->data = $data;
        $this->resourceType = $resourceType ?: $this->inferResourceType();
    }

    /**
     * Infer resource type from data
     */
    private function inferResourceType()
    {
        if (is_object($this->data)) {
            return strtolower(get_class($this->data));
        }

        if (is_array($this->data) && !empty($this->data)) {
            $first = reset($this->data);
            if (is_object($first)) {
                return strtolower(get_class($first)) . 's';
            }
        }

        return 'resource';
    }

    /**
     * Add included resources
     */
    public function include($relationship, $data)
    {
        $this->includes[$relationship] = $data;
        return $this;
    }

    /**
     * Add links
     */
    public function links(array $links)
    {
        $this->links = array_merge($this->links, $links);
        return $this;
    }

    /**
     * Convert to API response format
     */
    public function toArray()
    {
        $result = [
            'data' => $this->formatData($this->data),
            'type' => $this->resourceType
        ];

        if (!empty($this->includes)) {
            $result['included'] = [];
            foreach ($this->includes as $relationship => $data) {
                $result['included'][$relationship] = $this->formatData($data);
            }
        }

        if (!empty($this->links)) {
            $result['links'] = $this->links;
        }

        return $result;
    }

    /**
     * Format data for API response
     */
    private function formatData($data)
    {
        if (is_object($data) && method_exists($data, 'toArray')) {
            return $data->toArray();
        }

        if (is_array($data)) {
            return array_map(function($item) {
                return is_object($item) && method_exists($item, 'toArray')
                    ? $item->toArray()
                    : $item;
            }, $data);
        }

        return $data;
    }
}

// Usage examples:
/*
// Simple success response
ApiResponse::success(['flights' => $flights], 'Flights retrieved successfully');

// Created response
ApiResponse::created($newFlight, 'Flight created successfully');

// Error responses
ApiResponse::notFound('Flight');
ApiResponse::validationError($validationErrors);
ApiResponse::unauthorized();

// Paginated response
ApiResponse::paginated($flights, [
    'page' => 1,
    'limit' => 50,
    'total' => 150,
    'total_pages' => 3,
    'has_next' => true,
    'has_prev' => false
]);

// Using response builder for complex responses
(new ApiResponseBuilder())
    ->data($flight)
    ->message('Flight updated successfully')
    ->meta('processing_time', 0.15)
    ->header('X-Custom-Header', 'value')
    ->send();

// Using API resource wrapper
$resource = new ApiResource($flight, 'flight');
$resource->include('airline', $airline);
$resource->links(['self' => '/api/flights/' . $flight->id]);
ApiResponse::success($resource->toArray());
*/
?>
