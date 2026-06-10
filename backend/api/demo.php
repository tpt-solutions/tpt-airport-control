<?php
require_once __DIR__ . '/cors.php';
/**
 * Demo Mode API
 *
 * Handles demo mode functionality, role selection, and gamification features
 * for the Airport Operations Simulator
 */

require_once __DIR__ . '/../src/Config.php';
require_once __DIR__ . '/../src/Logger.php';
require_once __DIR__ . '/../src/RBAC.php';
require_once __DIR__ . '/../services/AchievementService.php';

class DemoAPI
{
    private $pdo;
    private $logger;

    public function __construct()
    {
        $this->logger = new Logger('demo_api');
        $this->connectDatabase();
    }

    private function connectDatabase()
    {
        try {
            $config = new Config();
            $dbConfig = $config->getDatabaseConfig();

            $this->pdo = new PDO(
                "pgsql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']}",
                $dbConfig['username'],
                $dbConfig['password']
            );
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (Exception $e) {
            $this->logger->error('Database connection failed', ['error' => $e->getMessage()]);
            $this->sendError('Database connection failed', 500);
        }
    }

    public function handleRequest()
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = $_SERVER['REQUEST_URI'];

        // Extract endpoint from path
        $endpoint = str_replace('/api/demo/', '', parse_url($path, PHP_URL_PATH));
        $endpoint = trim($endpoint, '/');

        switch ($method) {
            case 'GET':
                $this->handleGet($endpoint);
                break;
            case 'POST':
                $this->handlePost($endpoint);
                break;
            case 'PUT':
                $this->handlePut($endpoint);
                break;
            case 'DELETE':
                $this->handleDelete($endpoint);
                break;
            default:
                $this->sendError('Method not allowed', 405);
        }
    }

    private function handleGet($endpoint)
    {
        switch ($endpoint) {
            case '':
            case 'roles':
                $this->getRoles();
                break;
            case 'scenarios':
                $this->getScenarios();
                break;
            case 'achievements':
                $this->getAchievements();
                break;
            case 'leaderboard':
                $this->getLeaderboard();
                break;
            case 'stats':
                $this->getUserStats();
                break;
            default:
                $this->sendError('Endpoint not found', 404);
        }
    }

    private function handlePost($endpoint)
    {
        $data = json_decode(file_get_contents('php://input'), true);

        switch ($endpoint) {
            case 'start':
                $this->startDemoSession($data);
                break;
            case 'scenario':
                $this->startScenario($data);
                break;
            case 'achievement':
                $this->unlockAchievement($data);
                break;
            default:
                $this->sendError('Endpoint not found', 404);
        }
    }

    private function handlePut($endpoint)
    {
        $data = json_decode(file_get_contents('php://input'), true);

        switch ($endpoint) {
            case 'progress':
                $this->updateProgress($data);
                break;
            default:
                $this->sendError('Endpoint not found', 404);
        }
    }

    private function getRoles()
    {
        try {
            $stmt = $this->pdo->query("
                SELECT id, name, description
                FROM roles
                WHERE name NOT IN ('super_admin', 'admin')
                ORDER BY name
            ");
            $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Add demo-specific metadata
            foreach ($roles as &$role) {
                $role['demo_info'] = $this->getRoleDemoInfo($role['name']);
            }

            $this->sendResponse(['roles' => $roles]);
        } catch (Exception $e) {
            $this->logger->error('Failed to get roles', ['error' => $e->getMessage()]);
            $this->sendError('Failed to retrieve roles', 500);
        }
    }

    private function getRoleDemoInfo($roleName)
    {
        $roleInfo = [
            'controller' => [
                'difficulty' => 'advanced',
                'estimated_time' => 20,
                'scenarios' => 15,
                'description' => 'Manage airspace, coordinate with pilots, handle emergencies',
                'skills' => ['Communication', 'Decision Making', 'Stress Management'],
                'modules' => ['atc_operations', 'flight_management', 'emergency']
            ],
            'dispatcher' => [
                'difficulty' => 'intermediate',
                'estimated_time' => 15,
                'scenarios' => 12,
                'description' => 'Coordinate flight crews, manage schedules, handle delays',
                'skills' => ['Organization', 'Communication', 'Problem Solving'],
                'modules' => ['flight_management', 'crew_scheduling', 'passenger_services']
            ],
            'cargo_manager' => [
                'difficulty' => 'intermediate',
                'estimated_time' => 12,
                'scenarios' => 10,
                'description' => 'Oversee cargo operations, manage perishables, coordinate logistics',
                'skills' => ['Logistics', 'Quality Control', 'Time Management'],
                'modules' => ['cargo_operations', 'customs', 'infrastructure']
            ],
            'security_officer' => [
                'difficulty' => 'intermediate',
                'estimated_time' => 18,
                'scenarios' => 14,
                'description' => 'Monitor security systems, respond to threats, manage access control',
                'skills' => ['Situational Awareness', 'Emergency Response', 'Risk Assessment'],
                'modules' => ['advanced_security', 'emergency', 'infrastructure']
            ],
            'customs_officer' => [
                'difficulty' => 'beginner',
                'estimated_time' => 10,
                'scenarios' => 8,
                'description' => 'Process international passengers, inspect declarations, manage customs',
                'skills' => ['Attention to Detail', 'Cultural Awareness', 'Regulatory Knowledge'],
                'modules' => ['customs', 'passenger_services', 'compliance']
            ],
            'emergency_coordinator' => [
                'difficulty' => 'advanced',
                'estimated_time' => 25,
                'scenarios' => 18,
                'description' => 'Coordinate emergency responses, manage crisis situations, ensure safety',
                'skills' => ['Crisis Management', 'Leadership', 'Emergency Protocols'],
                'modules' => ['emergency', 'security', 'communications']
            ],
            'infrastructure_manager' => [
                'difficulty' => 'intermediate',
                'estimated_time' => 14,
                'scenarios' => 11,
                'description' => 'Monitor building systems, manage utilities, maintain facilities',
                'skills' => ['Technical Knowledge', 'Maintenance Planning', 'System Monitoring'],
                'modules' => ['infrastructure', 'sustainability', 'maintenance']
            ],
            'drone_operator' => [
                'difficulty' => 'advanced',
                'estimated_time' => 16,
                'scenarios' => 13,
                'description' => 'Coordinate UAV operations, manage airspace reservations, ensure safety',
                'skills' => ['Technical Operation', 'Airspace Management', 'Safety Protocols'],
                'modules' => ['drones', 'atc_operations', 'infrastructure']
            ],
            'commercial_manager' => [
                'difficulty' => 'beginner',
                'estimated_time' => 8,
                'scenarios' => 6,
                'description' => 'Manage retail operations, optimize revenue, coordinate concessions',
                'skills' => ['Business Management', 'Customer Service', 'Revenue Optimization'],
                'modules' => ['commercial', 'analytics', 'passenger_services']
            ],
            'sustainability_officer' => [
                'difficulty' => 'beginner',
                'estimated_time' => 10,
                'scenarios' => 7,
                'description' => 'Track environmental metrics, manage green initiatives, reduce carbon footprint',
                'skills' => ['Environmental Science', 'Data Analysis', 'Sustainability Planning'],
                'modules' => ['sustainability', 'analytics', 'infrastructure']
            ],
            'ai_analyst' => [
                'difficulty' => 'intermediate',
                'estimated_time' => 12,
                'scenarios' => 9,
                'description' => 'Analyze predictive models, interpret AI recommendations, optimize operations',
                'skills' => ['Data Analysis', 'AI/ML Understanding', 'Decision Support'],
                'modules' => ['advanced_analytics', 'ai_automation', 'flight_management']
            ],
            'virtual_assistant_admin' => [
                'difficulty' => 'beginner',
                'estimated_time' => 8,
                'scenarios' => 5,
                'description' => 'Configure AI assistants, manage conversations, optimize responses',
                'skills' => ['AI Configuration', 'User Experience', 'Technical Support'],
                'modules' => ['virtual_assistant', 'ai_automation', 'passenger_services']
            ],
            'kiosk_operator' => [
                'difficulty' => 'beginner',
                'estimated_time' => 6,
                'scenarios' => 4,
                'description' => 'Manage self-checkin kiosks, troubleshoot issues, assist passengers',
                'skills' => ['Technical Support', 'Customer Service', 'Problem Solving'],
                'modules' => ['self_checkin', 'passenger_services', 'infrastructure']
            ],
            'baggage_handler' => [
                'difficulty' => 'beginner',
                'estimated_time' => 8,
                'scenarios' => 6,
                'description' => 'Track baggage routing, resolve delivery issues, manage lost items',
                'skills' => ['Logistics', 'Customer Service', 'Problem Resolution'],
                'modules' => ['enhanced_baggage', 'passenger_services', 'infrastructure']
            ],
            'passenger_services_rep' => [
                'difficulty' => 'beginner',
                'estimated_time' => 10,
                'scenarios' => 8,
                'description' => 'Assist special needs passengers, manage alerts, coordinate services',
                'skills' => ['Customer Service', 'Accessibility Awareness', 'Coordination'],
                'modules' => ['special_services', 'passenger_alerts', 'passenger_services']
            ],
            'passenger' => [
                'difficulty' => 'beginner',
                'estimated_time' => 5,
                'scenarios' => 3,
                'description' => 'Experience airport from passenger perspective, use self-service features',
                'skills' => ['Self-Service Usage', 'Navigation', 'Time Management'],
                'modules' => ['passenger_services', 'self_checkin', 'passenger_alerts']
            ],
            'operator' => [
                'difficulty' => 'intermediate',
                'estimated_time' => 15,
                'scenarios' => 12,
                'description' => 'General airport operations, coordinate multiple departments',
                'skills' => ['Multi-tasking', 'Communication', 'Operations Management'],
                'modules' => ['flight_management', 'passenger_services', 'ground_ops']
            ]
        ];

        return $roleInfo[$roleName] ?? [
            'difficulty' => 'beginner',
            'estimated_time' => 10,
            'scenarios' => 5,
            'description' => 'General airport operations role',
            'skills' => ['Basic Operations', 'Communication'],
            'modules' => ['flight_management', 'passenger_services']
        ];
    }

    private function getScenarios()
    {
        // Return predefined scenarios for demo
        $scenarios = [
            [
                'id' => 'morning_rush',
                'title' => 'Morning Rush Hour',
                'description' => 'Handle peak morning traffic with multiple arrivals and departures',
                'difficulty' => 'intermediate',
                'estimated_time' => 15,
                'objectives' => ['Process 20 flights', 'Maintain on-time performance', 'Handle 2 gate changes'],
                'roles' => ['controller', 'dispatcher', 'operator'],
                'reward_points' => 500
            ],
            [
                'id' => 'weather_diversion',
                'title' => 'Weather Emergency Diversion',
                'description' => 'Manage flight diversions due to sudden weather changes',
                'difficulty' => 'advanced',
                'estimated_time' => 20,
                'objectives' => ['Divert 5 flights safely', 'Rebook passengers', 'Communicate with airlines'],
                'roles' => ['controller', 'emergency_coordinator', 'dispatcher'],
                'reward_points' => 750
            ],
            [
                'id' => 'security_threat',
                'title' => 'Security Threat Response',
                'description' => 'Respond to a security incident and coordinate emergency procedures',
                'difficulty' => 'advanced',
                'estimated_time' => 18,
                'objectives' => ['Secure affected areas', 'Evacuate passengers', 'Coordinate with authorities'],
                'roles' => ['security_officer', 'emergency_coordinator', 'controller'],
                'reward_points' => 800
            ],
            [
                'id' => 'cargo_crisis',
                'title' => 'Perishable Cargo Emergency',
                'description' => 'Handle temperature-sensitive cargo that needs immediate attention',
                'difficulty' => 'intermediate',
                'estimated_time' => 12,
                'objectives' => ['Identify affected cargo', 'Coordinate alternative routing', 'Minimize losses'],
                'roles' => ['cargo_manager', 'infrastructure_manager', 'dispatcher'],
                'reward_points' => 400
            ],
            [
                'id' => 'vip_handling',
                'title' => 'VIP Passenger Arrival',
                'description' => 'Manage high-profile passenger arrival with special requirements',
                'difficulty' => 'intermediate',
                'estimated_time' => 10,
                'objectives' => ['Coordinate special services', 'Ensure privacy', 'Manage media presence'],
                'roles' => ['passenger_services_rep', 'security_officer', 'commercial_manager'],
                'reward_points' => 350
            ]
        ];

        $this->sendResponse(['scenarios' => $scenarios]);
    }

    private function getAchievements()
    {
        $userId = $this->getCurrentUserId();

        try {
            $stmt = $this->pdo->prepare("
                SELECT achievement_type, achievement_name, points_earned, unlocked_at
                FROM demo_achievements
                WHERE user_id = ?
                ORDER BY unlocked_at DESC
            ");
            $stmt->execute([$userId]);
            $achievements = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->sendResponse(['achievements' => $achievements]);
        } catch (Exception $e) {
            $this->sendError('Failed to retrieve achievements', 500);
        }
    }

    private function getLeaderboard()
    {
        try {
            $stmt = $this->pdo->query("
                SELECT
                    u.username,
                    u.first_name,
                    u.last_name,
                    COUNT(da.id) as achievements_count,
                    SUM(da.points_earned) as total_points,
                    MAX(da.unlocked_at) as last_achievement
                FROM users u
                LEFT JOIN demo_achievements da ON u.id = da.user_id
                WHERE u.role_id IN (SELECT id FROM roles WHERE name != 'super_admin')
                GROUP BY u.id, u.username, u.first_name, u.last_name
                ORDER BY total_points DESC, achievements_count DESC
                LIMIT 50
            ");
            $leaderboard = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->sendResponse(['leaderboard' => $leaderboard]);
        } catch (Exception $e) {
            $this->sendError('Failed to retrieve leaderboard', 500);
        }
    }

    private function getUserStats()
    {
        $userId = $this->getCurrentUserId();

        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    COUNT(*) as total_achievements,
                    SUM(points_earned) as total_points,
                    MAX(unlocked_at) as last_activity
                FROM demo_achievements
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);

            $this->sendResponse(['stats' => $stats ?: ['total_achievements' => 0, 'total_points' => 0, 'last_activity' => null]]);
        } catch (Exception $e) {
            $this->sendError('Failed to retrieve user stats', 500);
        }
    }

    private function startDemoSession($data)
    {
        $userId = $this->getCurrentUserId();
        $roleId = $data['role_id'] ?? null;

        if (!$roleId) {
            $this->sendError('Role ID is required', 400);
            return;
        }

        try {
            // Update user's demo session
            $stmt = $this->pdo->prepare("
                UPDATE users
                SET demo_mode = true, demo_role_id = ?, demo_start_time = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$roleId, $userId]);

            $this->sendResponse([
                'message' => 'Demo session started successfully',
                'demo_mode' => true,
                'role_id' => $roleId
            ]);
        } catch (Exception $e) {
            $this->sendError('Failed to start demo session', 500);
        }
    }

    private function startScenario($data)
    {
        $userId = $this->getCurrentUserId();
        $scenarioId = $data['scenario_id'] ?? null;

        if (!$scenarioId) {
            $this->sendError('Scenario ID is required', 400);
            return;
        }

        try {
            // Record scenario start
            $stmt = $this->pdo->prepare("
                INSERT INTO demo_scenario_attempts (user_id, scenario_id, started_at)
                VALUES (?, ?, NOW())
            ");
            $stmt->execute([$userId, $scenarioId]);

            $this->sendResponse([
                'message' => 'Scenario started successfully',
                'scenario_id' => $scenarioId,
                'started_at' => date('c')
            ]);
        } catch (Exception $e) {
            $this->sendError('Failed to start scenario', 500);
        }
    }

    private function unlockAchievement($data)
    {
        $userId = $this->getCurrentUserId();
        $achievementType = $data['achievement_type'] ?? null;
        $achievementName = $data['achievement_name'] ?? null;
        $points = $data['points'] ?? 0;

        if (!$achievementType || !$achievementName) {
            $this->sendError('Achievement type and name are required', 400);
            return;
        }

        try {
            // Check if achievement already exists
            $stmt = $this->pdo->prepare("
                SELECT id FROM demo_achievements
                WHERE user_id = ? AND achievement_type = ? AND achievement_name = ?
            ");
            $stmt->execute([$userId, $achievementType, $achievementName]);

            if ($stmt->fetch()) {
                $this->sendResponse(['message' => 'Achievement already unlocked']);
                return;
            }

            // Insert new achievement
            $stmt = $this->pdo->prepare("
                INSERT INTO demo_achievements (user_id, achievement_type, achievement_name, points_earned, unlocked_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$userId, $achievementType, $achievementName, $points]);

            $this->sendResponse([
                'message' => 'Achievement unlocked successfully',
                'achievement' => $achievementName,
                'points' => $points
            ]);
        } catch (Exception $e) {
            $this->sendError('Failed to unlock achievement', 500);
        }
    }

    private function updateProgress($data)
    {
        $userId = $this->getCurrentUserId();
        $scenarioId = $data['scenario_id'] ?? null;
        $progress = $data['progress'] ?? 0;

        if (!$scenarioId) {
            $this->sendError('Scenario ID is required', 400);
            return;
        }

        try {
            $stmt = $this->pdo->prepare("
                UPDATE demo_scenario_attempts
                SET progress = ?, updated_at = NOW()
                WHERE user_id = ? AND scenario_id = ?
            ");
            $stmt->execute([$progress, $userId, $scenarioId]);

            $this->sendResponse(['message' => 'Progress updated successfully']);
        } catch (Exception $e) {
            $this->sendError('Failed to update progress', 500);
        }
    }

    private function getCurrentUserId()
    {
        // Get user ID from session or JWT token
        // This is a simplified implementation
        return $_SESSION['user_id'] ?? 1; // Default to user ID 1 for demo
    }

    private function sendResponse($data, $statusCode = 200)
    {
        http_response_code($statusCode);
        echo json_encode($data);
        exit;
    }

    private function sendError($message, $statusCode = 400)
    {
        http_response_code($statusCode);
        echo json_encode(['error' => $message]);
        exit;
    }
}

// Handle the request
$api = new DemoAPI();
$api->handleRequest();
?>
