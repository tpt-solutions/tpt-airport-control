<?php
/**
 * Input Validation Framework
 *
 * Provides centralized validation service with comprehensive input sanitization,
 * business rule validation, and security checks
 */

class Validator
{
    private static $instance = null;
    private $rules = [];
    private $errors = [];
    private $data = [];
    private $sanitized = [];
    private $fieldRules = [];
    private $currentField = null;
    private $defaults = [];

    // Common validation rules
    const RULE_REQUIRED = 'required';
    const RULE_EMAIL = 'email';
    const RULE_MIN_LENGTH = 'min_length';
    const RULE_MAX_LENGTH = 'max_length';
    const RULE_NUMERIC = 'numeric';
    const RULE_INTEGER = 'integer';
    const RULE_FLOAT = 'float';
    const RULE_BOOLEAN = 'boolean';
    const RULE_DATE = 'date';
    const RULE_DATETIME = 'datetime';
    const RULE_URL = 'url';
    const RULE_IP = 'ip';
    const RULE_REGEX = 'regex';
    const RULE_IN = 'in';
    const RULE_NOT_IN = 'not_in';
    const RULE_MIN = 'min';
    const RULE_MAX = 'max';
    const RULE_EQUALS = 'equals';
    const RULE_DIFFERENT = 'different';

    // Security validation rules
    const RULE_NO_HTML = 'no_html';
    const RULE_NO_SCRIPT = 'no_script';
    const RULE_SQL_INJECTION = 'no_sql_injection';
    const RULE_XSS = 'no_xss';
    const RULE_SAFE_FILENAME = 'safe_filename';

    // Business-specific rules
    const RULE_FLIGHT_NUMBER = 'flight_number';
    const RULE_AIRPORT_CODE = 'airport_code';
    const RULE_AIRCRAFT_REGISTRATION = 'aircraft_registration';
    const RULE_PHONE_NUMBER = 'phone_number';
    const RULE_PASSPORT_NUMBER = 'passport_number';

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
     * Initialize validation rules
     */
    private function initializeRules()
    {
        // Basic validation rules
        $this->rules[self::RULE_REQUIRED] = [$this, 'validateRequired'];
        $this->rules[self::RULE_EMAIL] = [$this, 'validateEmail'];
        $this->rules[self::RULE_MIN_LENGTH] = [$this, 'validateMinLength'];
        $this->rules[self::RULE_MAX_LENGTH] = [$this, 'validateMaxLength'];
        $this->rules[self::RULE_NUMERIC] = [$this, 'validateNumeric'];
        $this->rules[self::RULE_INTEGER] = [$this, 'validateInteger'];
        $this->rules[self::RULE_FLOAT] = [$this, 'validateFloat'];
        $this->rules[self::RULE_BOOLEAN] = [$this, 'validateBoolean'];
        $this->rules[self::RULE_DATE] = [$this, 'validateDate'];
        $this->rules[self::RULE_DATETIME] = [$this, 'validateDateTime'];
        $this->rules[self::RULE_URL] = [$this, 'validateUrl'];
        $this->rules[self::RULE_IP] = [$this, 'validateIp'];
        $this->rules[self::RULE_REGEX] = [$this, 'validateRegex'];
        $this->rules[self::RULE_IN] = [$this, 'validateIn'];
        $this->rules[self::RULE_NOT_IN] = [$this, 'validateNotIn'];
        $this->rules[self::RULE_MIN] = [$this, 'validateMin'];
        $this->rules[self::RULE_MAX] = [$this, 'validateMax'];
        $this->rules[self::RULE_EQUALS] = [$this, 'validateEquals'];
        $this->rules[self::RULE_DIFFERENT] = [$this, 'validateDifferent'];

        // Security validation rules
        $this->rules[self::RULE_NO_HTML] = [$this, 'validateNoHtml'];
        $this->rules[self::RULE_NO_SCRIPT] = [$this, 'validateNoScript'];
        $this->rules[self::RULE_SQL_INJECTION] = [$this, 'validateNoSqlInjection'];
        $this->rules[self::RULE_XSS] = [$this, 'validateNoXss'];
        $this->rules[self::RULE_SAFE_FILENAME] = [$this, 'validateSafeFilename'];

        // Business-specific rules
        $this->rules[self::RULE_FLIGHT_NUMBER] = [$this, 'validateFlightNumber'];
        $this->rules[self::RULE_AIRPORT_CODE] = [$this, 'validateAirportCode'];
        $this->rules[self::RULE_AIRCRAFT_REGISTRATION] = [$this, 'validateAircraftRegistration'];
        $this->rules[self::RULE_PHONE_NUMBER] = [$this, 'validatePhoneNumber'];
        $this->rules[self::RULE_PASSPORT_NUMBER] = [$this, 'validatePassportNumber'];
    }

    /**
     * Validate data against rules
     */
    public function validate(array $data, array $rules, array $customMessages = [])
    {
        $this->data = $data;
        $this->errors = [];
        $this->sanitized = [];

        foreach ($rules as $field => $fieldRules) {
            $fieldRules = is_string($fieldRules) ? explode('|', $fieldRules) : $fieldRules;

            foreach ($fieldRules as $rule) {
                $this->validateField($field, $rule, $customMessages);
            }
        }

        return empty($this->errors);
    }

    /**
     * Validate a single field against a rule
     */
    private function validateField($field, $rule, array $customMessages = [])
    {
        $value = $this->getFieldValue($field);

        // Parse rule and parameters
        $parsedRule = $this->parseRule($rule);
        $ruleName = $parsedRule['rule'];
        $parameters = $parsedRule['parameters'];

        // Check if rule exists
        if (!isset($this->rules[$ruleName])) {
            $this->addError($field, "Unknown validation rule: {$ruleName}");
            return;
        }

        // Apply validation rule
        $isValid = call_user_func($this->rules[$ruleName], $value, $parameters, $field);

        if (!$isValid) {
            $message = $this->getErrorMessage($field, $ruleName, $parameters, $customMessages);
            $this->addError($field, $message);
        }
    }

    /**
     * Parse validation rule string
     */
    private function parseRule($rule)
    {
        if (is_string($rule)) {
            $parts = explode(':', $rule, 2);
            $ruleName = $parts[0];
            $parameters = isset($parts[1]) ? explode(',', $parts[1]) : [];
        } else {
            $ruleName = $rule;
            $parameters = [];
        }

        return [
            'rule' => $ruleName,
            'parameters' => $parameters
        ];
    }

    /**
     * Get field value from data
     */
    private function getFieldValue($field)
    {
        return isset($this->data[$field]) ? $this->data[$field] : null;
    }

    /**
     * Add validation error
     */
    private function addError($field, $message)
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }

    /**
     * Get error message
     */
    private function getErrorMessage($field, $rule, array $parameters = [], array $customMessages = [])
    {
        // Check for custom message
        $customKey = $field . '.' . $rule;
        if (isset($customMessages[$customKey])) {
            return $customMessages[$customKey];
        }

        // Use default messages
        $messages = $this->getDefaultMessages();
        $key = $rule;

        if (isset($messages[$key])) {
            return $this->formatMessage($messages[$key], $field, $parameters);
        }

        return "Validation failed for field '{$field}' with rule '{$rule}'";
    }

    /**
     * Get default error messages
     */
    private function getDefaultMessages()
    {
        return [
            self::RULE_REQUIRED => 'The :field field is required.',
            self::RULE_EMAIL => 'The :field must be a valid email address.',
            self::RULE_MIN_LENGTH => 'The :field must be at least :param characters.',
            self::RULE_MAX_LENGTH => 'The :field may not be greater than :param characters.',
            self::RULE_NUMERIC => 'The :field must be a number.',
            self::RULE_INTEGER => 'The :field must be an integer.',
            self::RULE_FLOAT => 'The :field must be a decimal number.',
            self::RULE_BOOLEAN => 'The :field must be true or false.',
            self::RULE_DATE => 'The :field is not a valid date.',
            self::RULE_DATETIME => 'The :field is not a valid date and time.',
            self::RULE_URL => 'The :field must be a valid URL.',
            self::RULE_IP => 'The :field must be a valid IP address.',
            self::RULE_REGEX => 'The :field format is invalid.',
            self::RULE_IN => 'The selected :field is invalid.',
            self::RULE_NOT_IN => 'The selected :field is invalid.',
            self::RULE_MIN => 'The :field must be at least :param.',
            self::RULE_MAX => 'The :field may not be greater than :param.',
            self::RULE_EQUALS => 'The :field must be equal to :param.',
            self::RULE_DIFFERENT => 'The :field must be different from :param.',
            self::RULE_NO_HTML => 'The :field may not contain HTML.',
            self::RULE_NO_SCRIPT => 'The :field may not contain scripts.',
            self::RULE_SQL_INJECTION => 'The :field contains invalid characters.',
            self::RULE_XSS => 'The :field contains potentially unsafe content.',
            self::RULE_SAFE_FILENAME => 'The :field contains invalid filename characters.',
            self::RULE_FLIGHT_NUMBER => 'The :field must be a valid flight number.',
            self::RULE_AIRPORT_CODE => 'The :field must be a valid airport code.',
            self::RULE_AIRCRAFT_REGISTRATION => 'The :field must be a valid aircraft registration.',
            self::RULE_PHONE_NUMBER => 'The :field must be a valid phone number.',
            self::RULE_PASSPORT_NUMBER => 'The :field must be a valid passport number.',
        ];
    }

    /**
     * Format error message
     */
    private function formatMessage($message, $field, array $parameters = [])
    {
        $replacements = [
            ':field' => $field,
            ':param' => isset($parameters[0]) ? $parameters[0] : '',
            ':param1' => isset($parameters[0]) ? $parameters[0] : '',
            ':param2' => isset($parameters[1]) ? $parameters[1] : '',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $message);
    }

    /**
     * Get validation errors
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Get first error for field
     */
    public function getFirstError($field)
    {
        return isset($this->errors[$field]) ? $this->errors[$field][0] : null;
    }

    /**
     * Get all errors as flat array
     */
    public function getAllErrors()
    {
        $flat = [];
        foreach ($this->errors as $field => $messages) {
            foreach ($messages as $message) {
                $flat[] = $message;
            }
        }
        return $flat;
    }

    /**
     * Get sanitized data
     */
    public function getSanitizedData()
    {
        return $this->sanitized;
    }

    /**
     * Sanitize input data
     */
    public function sanitize(array $data, array $rules = [])
    {
        $sanitized = [];

        foreach ($data as $field => $value) {
            $sanitized[$field] = $this->sanitizeValue($value, isset($rules[$field]) ? $rules[$field] : []);
        }

        $this->sanitized = $sanitized;
        return $sanitized;
    }

    /**
     * Sanitize a single value
     */
    private function sanitizeValue($value, array $rules = [])
    {
        if ($value === null || $value === '') {
            return $value;
        }

        // Apply sanitization rules
        foreach ($rules as $rule) {
            $parsedRule = $this->parseRule($rule);
            $ruleName = $parsedRule['rule'];

            switch ($ruleName) {
                case 'trim':
                    $value = trim($value);
                    break;
                case 'strip_tags':
                    $value = strip_tags($value);
                    break;
                case 'htmlspecialchars':
                    $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                    break;
                case 'intval':
                    $value = intval($value);
                    break;
                case 'floatval':
                    $value = floatval($value);
                    break;
                case 'strtolower':
                    $value = strtolower($value);
                    break;
                case 'strtoupper':
                    $value = strtoupper($value);
                    break;
            }
        }

        return $value;
    }

    // ===== VALIDATION RULE IMPLEMENTATIONS =====

    private function validateRequired($value, $params, $field)
    {
        return $value !== null && $value !== '' && (!is_array($value) || !empty($value));
    }

    private function validateEmail($value, $params, $field)
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    private function validateMinLength($value, $params, $field)
    {
        $min = isset($params[0]) ? intval($params[0]) : 0;
        return strlen((string)$value) >= $min;
    }

    private function validateMaxLength($value, $params, $field)
    {
        $max = isset($params[0]) ? intval($params[0]) : PHP_INT_MAX;
        return strlen((string)$value) <= $max;
    }

    private function validateNumeric($value, $params, $field)
    {
        return is_numeric($value);
    }

    private function validateInteger($value, $params, $field)
    {
        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    private function validateFloat($value, $params, $field)
    {
        return filter_var($value, FILTER_VALIDATE_FLOAT) !== false;
    }

    private function validateBoolean($value, $params, $field)
    {
        return is_bool($value) || in_array($value, [0, 1, '0', '1', 'true', 'false', true, false], true);
    }

    private function validateDate($value, $params, $field)
    {
        $date = date_create($value);
        return $date !== false;
    }

    private function validateDateTime($value, $params, $field)
    {
        $datetime = date_create($value);
        return $datetime !== false;
    }

    private function validateUrl($value, $params, $field)
    {
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    private function validateIp($value, $params, $field)
    {
        return filter_var($value, FILTER_VALIDATE_IP) !== false;
    }

    private function validateRegex($value, $params, $field)
    {
        $pattern = isset($params[0]) ? $params[0] : '';
        return preg_match($pattern, (string)$value) === 1;
    }

    private function validateIn($value, $params, $field)
    {
        return in_array($value, $params, true);
    }

    private function validateNotIn($value, $params, $field)
    {
        return !in_array($value, $params, true);
    }

    private function validateMin($value, $params, $field)
    {
        $min = isset($params[0]) ? floatval($params[0]) : 0;
        return floatval($value) >= $min;
    }

    private function validateMax($value, $params, $field)
    {
        $max = isset($params[0]) ? floatval($params[0]) : PHP_INT_MAX;
        return floatval($value) <= $max;
    }

    private function validateEquals($value, $params, $field)
    {
        $otherField = isset($params[0]) ? $params[0] : '';
        $otherValue = $this->getFieldValue($otherField);
        return $value === $otherValue;
    }

    private function validateDifferent($value, $params, $field)
    {
        $otherField = isset($params[0]) ? $params[0] : '';
        $otherValue = $this->getFieldValue($otherField);
        return $value !== $otherValue;
    }

    private function validateNoHtml($value, $params, $field)
    {
        return strip_tags($value) === $value;
    }

    private function validateNoScript($value, $params, $field)
    {
        $patterns = [
            '/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi',
            '/javascript:/i',
            '/vbscript:/i',
            '/onload=/i',
            '/onerror=/i',
            '/onclick=/i'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return false;
            }
        }

        return true;
    }

    private function validateNoSqlInjection($value, $params, $field)
    {
        $patterns = [
            '/(\b(union|select|insert|update|delete|drop|create|alter|exec|execute)\b)/i',
            '/(-{2}|\/\*|\*\/)/',
            '/(;|\'|"|`)/'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return false;
            }
        }

        return true;
    }

    private function validateNoXss($value, $params, $field)
    {
        $patterns = [
            '/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi',
            '/javascript:/i',
            '/vbscript:/i',
            '/data:text\/html/i',
            '/<iframe/i',
            '/<object/i',
            '/<embed/i'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return false;
            }
        }

        return true;
    }

    private function validateSafeFilename($value, $params, $field)
    {
        // Allow only alphanumeric, dots, hyphens, and underscores
        return preg_match('/^[a-zA-Z0-9._-]+$/', $value) === 1;
    }

    private function validateFlightNumber($value, $params, $field)
    {
        // Flight numbers typically: 2-3 letter airline code + 1-4 digits
        return preg_match('/^[A-Z]{2,3}\d{1,4}$/', strtoupper($value)) === 1;
    }

    private function validateAirportCode($value, $params, $field)
    {
        // IATA airport codes are 3 letters
        return preg_match('/^[A-Z]{3}$/', strtoupper($value)) === 1;
    }

    private function validateAircraftRegistration($value, $params, $field)
    {
        // Aircraft registrations vary by country, but generally alphanumeric
        return preg_match('/^[A-Z0-9-]{2,10}$/', strtoupper($value)) === 1;
    }

    private function validatePhoneNumber($value, $params, $field)
    {
        // Basic phone number validation (allows international formats)
        $cleaned = preg_replace('/[^\d+\-\s\(\)]/', '', $value);
        return strlen($cleaned) >= 7 && strlen($cleaned) <= 20;
    }

    private function validatePassportNumber($value, $params, $field)
    {
        // Passport numbers vary by country, basic alphanumeric check
        return preg_match('/^[A-Z0-9]{6,20}$/', strtoupper($value)) === 1;
    }

    // ===== PREDEFINED VALIDATION SETS =====

    /**
     * Get validation rules for user registration
     */
    public function getUserRegistrationRules()
    {
        return [
            'username' => ['required', 'min_length:3', 'max_length:50', 'regex:/^[a-zA-Z0-9_]+$/', 'no_sql_injection'],
            'email' => ['required', 'email', 'max_length:255', 'no_xss'],
            'password' => ['required', 'min_length:8', 'max_length:128', 'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/'],
            'first_name' => ['required', 'max_length:50', 'no_html', 'no_xss'],
            'last_name' => ['required', 'max_length:50', 'no_html', 'no_xss'],
            'phone' => ['phone_number', 'max_length:20'],
            'role' => ['required', 'in:admin,controller,passenger']
        ];
    }

    /**
     * Get validation rules for flight creation
     */
    public function getFlightCreationRules()
    {
        return [
            'flight_number' => ['required', 'flight_number', 'max_length:10'],
            'airline_id' => ['required', 'integer', 'min:1'],
            'aircraft_id' => ['required', 'integer', 'min:1'],
            'origin' => ['required', 'airport_code'],
            'destination' => ['required', 'airport_code', 'different:origin'],
            'scheduled_departure' => ['required', 'datetime'],
            'scheduled_arrival' => ['required', 'datetime'],
            'status' => ['in:scheduled,boarding,departed,arrived,cancelled'],
            'gate' => ['max_length:10', 'no_sql_injection'],
            'terminal' => ['max_length:10', 'no_sql_injection']
        ];
    }

    /**
     * Get validation rules for booking creation
     */
    public function getBookingCreationRules()
    {
        return [
            'flight_id' => ['required', 'integer', 'min:1'],
            'passenger_id' => ['required', 'integer', 'min:1'],
            'seat_number' => ['max_length:10', 'no_sql_injection'],
            'class' => ['required', 'in:economy,premium_economy,business,first'],
            'status' => ['in:confirmed,pending,cancelled'],
            'special_requests' => ['max_length:500', 'no_html', 'no_script', 'no_xss']
        ];
    }

    /**
     * Get validation rules for passenger creation
     */
    public function getPassengerCreationRules()
    {
        return [
            'first_name' => ['required', 'max_length:50', 'no_html', 'no_xss'],
            'last_name' => ['required', 'max_length:50', 'no_html', 'no_xss'],
            'email' => ['required', 'email', 'max_length:255'],
            'phone' => ['phone_number', 'max_length:20'],
            'passport_number' => ['required', 'passport_number', 'max_length:20'],
            'passport_expiry' => ['required', 'date'],
            'date_of_birth' => ['required', 'date'],
            'nationality' => ['max_length:50', 'no_sql_injection']
        ];
    }

    // ===== FLUENT INTERFACE METHODS =====

    /**
     * Constructor with input data for fluent interface
     */
    public function __construct($data = null)
    {
        if (self::$instance === null) {
            self::$instance = $this;
        }
        
        if ($data !== null) {
            $this->data = $data;
        }
        $this->initializeRules();
    }

    /**
     * Define optional field
     */
    public function optional($field)
    {
        $this->currentField = $field;
        $this->fieldRules[$field] = [];
        $this->defaults[$field] = null;
        return $this;
    }

    /**
     * Add integer validation rule
     */
    public function integer()
    {
        $this->fieldRules[$this->currentField][] = 'integer';
        return $this;
    }

    /**
     * Add minimum value validation rule
     */
    public function min($value)
    {
        $this->fieldRules[$this->currentField][] = "min:$value";
        return $this;
    }

    /**
     * Add maximum value validation rule
     */
    public function max($value)
    {
        $this->fieldRules[$this->currentField][] = "max:$value";
        return $this;
    }

    /**
     * Set default value for field
     */
    public function default($value)
    {
        $this->defaults[$this->currentField] = $value;
        return $this;
    }

    /**
     * Add string validation rule
     */
    public function string()
    {
        // Strings have no specific validation beyond being scalar
        return $this;
    }

    /**
     * Add dateTime validation rule
     */
    public function dateTime()
    {
        $this->fieldRules[$this->currentField][] = 'datetime';
        return $this;
    }

    /**
     * Add oneOf validation rule
     */
    public function oneOf(array $values)
    {
        $this->fieldRules[$this->currentField][] = 'in:' . implode(',', $values);
        return $this;
    }

    /**
     * Validate all defined fields (fluent interface)
     */
    public function validateFields()
    {
        $this->errors = [];
        $this->sanitized = [];

        foreach ($this->fieldRules as $field => $rules) {
            $value = isset($this->data[$field]) ? $this->data[$field] : $this->defaults[$field];
            
            if ($value !== null) {
                foreach ($rules as $rule) {
                    $this->validateField($field, $rule);
                }
            }
            
            $this->sanitized[$field] = $value;
        }

        return empty($this->errors);
    }

    /**
     * Get validated data after validation
     */
    public function getValidatedData()
    {
        return $this->sanitized;
    }
}

// Usage examples:
/*
// Basic validation
$validator = Validator::getInstance();

$data = [
    'email' => 'user@example.com',
    'password' => 'secure123',
    'age' => 25
];

$rules = [
    'email' => 'required|email',
    'password' => 'required|min_length:8',
    'age' => 'integer|min:18|max:120'
];

if ($validator->validate($data, $rules)) {
    // Data is valid
    $cleanData = $validator->getSanitizedData();
} else {
    // Get validation errors
    $errors = $validator->getErrors();
}

// Using predefined rule sets
$userRules = $validator->getUserRegistrationRules();
$flightRules = $validator->getFlightCreationRules();

// Security-focused validation
$secureRules = [
    'comment' => ['required', 'max_length:1000', 'no_html', 'no_script', 'no_xss', 'no_sql_injection'],
    'filename' => ['required', 'safe_filename', 'max_length:255']
];
*/
?>
