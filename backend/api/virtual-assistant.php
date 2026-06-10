<?php
require_once __DIR__ . '/cors.php';
/**
 * Virtual Assistant API
 *
 * Provides voice-controlled assistance and personalized recommendations
 * for airport operations and passenger services
 */

require_once __DIR__ . '/../src/Config.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/ApiResponse.php';
require_once __DIR__ . '/../models/VirtualAssistant.php';
require_once __DIR__ . '/../models/UserPreference.php';
require_once __DIR__ . '/../models/InteractionHistory.php';

// JWT Authentication
$auth = new Auth();
$user = $auth->getCurrentUser();

if (!$user) {
    ApiResponse::error('Unauthorized', 401);
}

$method = $_SERVER['REQUEST_METHOD'];
$request = $_SERVER['REQUEST_URI'];
$path = parse_url($request, PHP_URL_PATH);
$path = str_replace('/backend/api/virtual-assistant', '', $path);
$path = trim($path, '/');
$segments = explode('/', $path);

try {
    $virtualAssistant = new VirtualAssistant();
    $userPreference = new UserPreference();
    $interactionHistory = new InteractionHistory();

    switch ($method) {
        case 'GET':
            if (empty($segments[0])) {
                // Get user's virtual assistant status
                $assistant = $virtualAssistant->getByUserId($user['id']);
                if (!$assistant) {
                    $assistant = $virtualAssistant->create([
                        'user_id' => $user['id'],
                        'name' => 'Airport Assistant',
                        'voice_enabled' => true,
                        'language' => 'en',
                        'personality' => 'professional',
                        'is_active' => true
                    ]);
                }
                ApiResponse::success($assistant);
            } elseif ($segments[0] === 'preferences') {
                // Get user preferences
                $preferences = $userPreference->getByUserId($user['id']);
                ApiResponse::success($preferences ?: []);
            } elseif ($segments[0] === 'history') {
                // Get interaction history
                $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
                $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
                $history = $interactionHistory->getByUserId($user['id'], $limit, $offset);
                ApiResponse::success($history);
            } elseif ($segments[0] === 'commands') {
                // Get available voice commands
                $commands = getAvailableCommands($user['role_name']);
                ApiResponse::success($commands);
            } elseif ($segments[0] === 'recommendations') {
                // Get personalized recommendations
                $recommendations = getPersonalizedRecommendations($user['id'], $user['role_name']);
                ApiResponse::success($recommendations);
            }
            break;

        case 'POST':
            if (empty($segments[0])) {
                // Create new virtual assistant
                $data = json_decode(file_get_contents('php://input'), true);
                $data['user_id'] = $user['id'];
                $assistant = $virtualAssistant->create($data);
                ApiResponse::success($assistant, 'Virtual assistant created successfully', 201);
            } elseif ($segments[0] === 'voice-command') {
                // Process voice command
                $data = json_decode(file_get_contents('php://input'), true);
                $result = processVoiceCommand($data['command'], $user);
                ApiResponse::success($result);
            } elseif ($segments[0] === 'text-query') {
                // Process text query
                $data = json_decode(file_get_contents('php://input'), true);
                $result = processTextQuery($data['query'], $user);
                ApiResponse::success($result);
            } elseif ($segments[0] === 'feedback') {
                // Submit feedback
                $data = json_decode(file_get_contents('php://input'), true);
                $data['user_id'] = $user['id'];
                $feedback = $interactionHistory->createFeedback($data);
                ApiResponse::success($feedback, 'Feedback submitted successfully');
            }
            break;

        case 'PUT':
            if (is_numeric($segments[0])) {
                // Update virtual assistant
                $data = json_decode(file_get_contents('php://input'), true);
                $assistant = $virtualAssistant->update($segments[0], $data);
                ApiResponse::success($assistant, 'Virtual assistant updated successfully');
            } elseif ($segments[0] === 'preferences') {
                // Update user preferences
                $data = json_decode(file_get_contents('php://input'), true);
                $data['user_id'] = $user['id'];
                $preferences = $userPreference->updateOrCreate($data);
                ApiResponse::success($preferences, 'Preferences updated successfully');
            }
            break;

        case 'DELETE':
            if (is_numeric($segments[0])) {
                // Delete virtual assistant
                $virtualAssistant->delete($segments[0]);
                ApiResponse::success(null, 'Virtual assistant deleted successfully');
            }
            break;

        default:
            ApiResponse::error('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log('Virtual Assistant API Error: ' . $e->getMessage());
    ApiResponse::error('Internal server error', 500);
}

/**
 * Get available voice commands based on user role
 */
function getAvailableCommands($role) {
    $baseCommands = [
        'help' => 'Show available commands',
        'status' => 'Get system status',
        'weather' => 'Get weather information',
        'time' => 'Get current time',
        'repeat' => 'Repeat last response'
    ];

    $roleCommands = [];

    switch ($role) {
        case 'admin':
            $roleCommands = [
                'system status' => 'Get overall system status',
                'active flights' => 'List active flights',
                'security alerts' => 'Check security alerts',
                'emergency protocols' => 'Access emergency protocols',
                'performance metrics' => 'View system performance',
                'user management' => 'Access user management',
                'backup status' => 'Check backup status'
            ];
            break;

        case 'operator':
            $roleCommands = [
                'flight status' => 'Check flight status',
                'gate assignments' => 'View gate assignments',
                'baggage status' => 'Check baggage handling',
                'passenger count' => 'Get passenger counts',
                'maintenance schedule' => 'View maintenance schedule',
                'cargo operations' => 'Check cargo status',
                'drone operations' => 'Monitor drone activities'
            ];
            break;

        case 'passenger':
            $roleCommands = [
                'flight information' => 'Get flight details',
                'gate location' => 'Find gate location',
                'baggage claim' => 'Locate baggage claim',
                'restaurant locations' => 'Find dining options',
                'shop locations' => 'Find shopping areas',
                'transportation' => 'Get transportation options',
                'check-in status' => 'Check check-in status'
            ];
            break;
    }

    return array_merge($baseCommands, $roleCommands);
}

/**
 * Process voice command
 */
function processVoiceCommand($command, $user) {
    $command = strtolower(trim($command));

    // Log interaction
    global $interactionHistory;
    $interactionHistory->create([
        'user_id' => $user['id'],
        'interaction_type' => 'voice_command',
        'input' => $command,
        'timestamp' => date('Y-m-d H:i:s')
    ]);

    // Process command
    if (strpos($command, 'help') !== false) {
        return [
            'response' => 'Here are some things I can help you with. Say "show commands" to see all available options.',
            'type' => 'help',
            'commands' => getAvailableCommands($user['role_name'])
        ];
    }

    if (strpos($command, 'status') !== false) {
        return getSystemStatus($user['role_name']);
    }

    if (strpos($command, 'weather') !== false) {
        return getWeatherInfo();
    }

    if (strpos($command, 'time') !== false) {
        return [
            'response' => 'Current time is ' . date('H:i'),
            'type' => 'time',
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    if (strpos($command, 'flight') !== false && $user['role_name'] !== 'passenger') {
        return getFlightInfo($command);
    }

    if (strpos($command, 'gate') !== false) {
        return getGateInfo($command);
    }

    if (strpos($command, 'baggage') !== false) {
        return getBaggageInfo($command, $user);
    }

    // Default response
    return [
        'response' => 'I\'m sorry, I didn\'t understand that command. Say "help" to see available options.',
        'type' => 'unknown_command',
        'suggestions' => ['help', 'status', 'commands']
    ];
}

/**
 * Process text query
 */
function processTextQuery($query, $user) {
    $query = strtolower(trim($query));

    // Log interaction
    global $interactionHistory;
    $interactionHistory->create([
        'user_id' => $user['id'],
        'interaction_type' => 'text_query',
        'input' => $query,
        'timestamp' => date('Y-m-d H:i:s')
    ]);

    // Simple NLP processing
    if (preg_match('/(what|how|when|where|who)/', $query)) {
        return processQuestion($query, $user);
    }

    if (preg_match('/(show|list|display|get)/', $query)) {
        return processRequest($query, $user);
    }

    // Default response
    return [
        'response' => 'I can help you with information about flights, gates, baggage, weather, and system status. What would you like to know?',
        'type' => 'clarification_needed',
        'suggestions' => ['flight status', 'gate locations', 'weather', 'system status']
    ];
}

/**
 * Process question queries
 */
function processQuestion($query, $user) {
    if (strpos($query, 'time') !== false) {
        return [
            'response' => 'The current time is ' . date('H:i T'),
            'type' => 'time_info'
        ];
    }

    if (strpos($query, 'weather') !== false) {
        return getWeatherInfo();
    }

    if (strpos($query, 'flight') !== false) {
        return getFlightInfo($query);
    }

    if (strpos($query, 'gate') !== false) {
        return getGateInfo($query);
    }

    return [
        'response' => 'I can provide information about time, weather, flights, and gates. What specific information do you need?',
        'type' => 'clarification_needed'
    ];
}

/**
 * Process request queries
 */
function processRequest($query, $user) {
    if (strpos($query, 'status') !== false) {
        return getSystemStatus($user['role_name']);
    }

    if (strpos($query, 'flight') !== false) {
        return getFlightInfo($query);
    }

    if (strpos($query, 'gate') !== false) {
        return getGateInfo($query);
    }

    return [
        'response' => 'I can show you system status, flight information, and gate assignments. What would you like to see?',
        'type' => 'clarification_needed'
    ];
}

/**
 * Get system status
 */
function getSystemStatus($role) {
    // Mock system status - in real implementation, this would query actual system health
    $status = [
        'overall' => 'operational',
        'flights' => 'normal',
        'security' => 'active',
        'baggage' => 'operational',
        'timestamp' => date('Y-m-d H:i:s')
    ];

    if ($role === 'admin') {
        $status['cpu_usage'] = '45%';
        $status['memory_usage'] = '62%';
        $status['active_users'] = 127;
    }

    return [
        'response' => 'System is operating normally with all critical services active.',
        'type' => 'system_status',
        'details' => $status
    ];
}

/**
 * Get weather information
 */
function getWeatherInfo() {
    // Mock weather data - in real implementation, this would call weather API
    return [
        'response' => 'Current weather: Partly cloudy, 22°C, wind 15 km/h from southwest.',
        'type' => 'weather_info',
        'details' => [
            'condition' => 'Partly cloudy',
            'temperature' => '22°C',
            'wind_speed' => '15 km/h',
            'wind_direction' => 'SW',
            'visibility' => '10 km',
            'updated' => date('H:i')
        ]
    ];
}

/**
 * Get flight information
 */
function getFlightInfo($query) {
    // Mock flight data - in real implementation, this would query flight database
    $flights = [
        [
            'flight_number' => 'AA123',
            'destination' => 'New York',
            'status' => 'On time',
            'gate' => 'A12',
            'departure' => '14:30'
        ],
        [
            'flight_number' => 'UA456',
            'destination' => 'Los Angeles',
            'status' => 'Delayed',
            'gate' => 'B08',
            'departure' => '15:15'
        ]
    ];

    return [
        'response' => 'Here are the current flight statuses.',
        'type' => 'flight_info',
        'flights' => $flights
    ];
}

/**
 * Get gate information
 */
function getGateInfo($query) {
    // Mock gate data
    return [
        'response' => 'Gate information is available on the main display screens throughout the terminal.',
        'type' => 'gate_info',
        'gates' => [
            'A' => 'International Departures',
            'B' => 'Domestic Departures',
            'C' => 'Arrivals'
        ]
    ];
}

/**
 * Get baggage information
 */
function getBaggageInfo($query, $user) {
    if ($user['role_name'] === 'passenger') {
        return [
            'response' => 'Your baggage is being processed. Please proceed to the baggage claim area when your flight arrives.',
            'type' => 'baggage_info',
            'claim_area' => 'Baggage Claim 3'
        ];
    }

    return [
        'response' => 'Baggage handling systems are operating normally with all belts active.',
        'type' => 'baggage_status',
        'active_belts' => 12,
        'total_baggage' => 2847
    ];
}

/**
 * Get personalized recommendations
 */
function getPersonalizedRecommendations($userId, $role) {
    $recommendations = [];

    switch ($role) {
        case 'admin':
            $recommendations = [
                [
                    'type' => 'system_check',
                    'title' => 'Review System Performance',
                    'description' => 'Check recent system performance metrics',
                    'priority' => 'medium',
                    'action' => 'view_performance'
                ],
                [
                    'type' => 'security_review',
                    'title' => 'Security Alert Review',
                    'description' => 'Review recent security incidents',
                    'priority' => 'high',
                    'action' => 'view_security'
                ]
            ];
            break;

        case 'operator':
            $recommendations = [
                [
                    'type' => 'flight_monitoring',
                    'title' => 'Monitor Flight Delays',
                    'description' => 'Check for any flight delays requiring attention',
                    'priority' => 'high',
                    'action' => 'view_delays'
                ],
                [
                    'type' => 'resource_check',
                    'title' => 'Resource Availability',
                    'description' => 'Verify ground crew and equipment availability',
                    'priority' => 'medium',
                    'action' => 'check_resources'
                ]
            ];
            break;

        case 'passenger':
            $recommendations = [
                [
                    'type' => 'boarding_reminder',
                    'title' => 'Boarding Reminder',
                    'description' => 'Your flight AA123 begins boarding in 30 minutes',
                    'priority' => 'high',
                    'action' => 'view_boarding'
                ],
                [
                    'type' => 'dining_option',
                    'title' => 'Dining Recommendation',
                    'description' => 'Try the new restaurant near gate A15',
                    'priority' => 'low',
                    'action' => 'view_restaurants'
                ]
            ];
            break;
    }

    return $recommendations;
}
?>
