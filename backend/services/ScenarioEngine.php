<?php
/**
 * Scenario Engine for Airport Operations Simulator
 *
 * Manages scenario execution, objectives, and completion logic
 */

require_once __DIR__ . '/../src/Config.php';
require_once __DIR__ . '/../src/Logger.php';
require_once __DIR__ . '/AchievementService.php';

class ScenarioEngine
{
    private $pdo;
    private $logger;
    private $achievementService;

    // Scenario definitions with objectives and logic
    private $scenarios = [
        'morning_rush' => [
            'title' => 'Morning Rush Hour',
            'description' => 'Handle peak morning traffic with multiple arrivals and departures',
            'difficulty' => 'intermediate',
            'estimated_time' => 900, // 15 minutes
            'objectives' => [
                [
                    'id' => 'process_flights',
                    'description' => 'Process 20 flights on time',
                    'type' => 'counter',
                    'target' => 20,
                    'current' => 0,
                    'points' => 200
                ],
                [
                    'id' => 'gate_changes',
                    'description' => 'Handle 2 gate changes efficiently',
                    'type' => 'counter',
                    'target' => 2,
                    'current' => 0,
                    'points' => 150
                ],
                [
                    'id' => 'passenger_satisfaction',
                    'description' => 'Maintain passenger satisfaction above 85%',
                    'type' => 'threshold',
                    'target' => 85,
                    'current' => 100,
                    'points' => 150
                ]
            ],
            'events' => [
                ['time' => 60, 'type' => 'flight_arrival', 'data' => ['flight' => 'AA101', 'gate' => 'A1']],
                ['time' => 120, 'type' => 'flight_departure', 'data' => ['flight' => 'UA202', 'gate' => 'B2']],
                ['time' => 180, 'type' => 'gate_change', 'data' => ['flight' => 'DL303', 'old_gate' => 'C3', 'new_gate' => 'C5']],
                ['time' => 300, 'type' => 'passenger_complaint', 'data' => ['type' => 'delay', 'severity' => 'medium']],
                ['time' => 420, 'type' => 'flight_arrival', 'data' => ['flight' => 'SW404', 'gate' => 'D4']],
                ['time' => 480, 'type' => 'gate_change', 'data' => ['flight' => 'AA505', 'old_gate' => 'A2', 'new_gate' => 'A4']],
                ['time' => 600, 'type' => 'crew_issue', 'data' => ['type' => 'late_crew', 'flight' => 'UA606']],
                ['time' => 720, 'type' => 'baggage_delay', 'data' => ['flight' => 'DL707', 'delay' => 30]],
                ['time' => 780, 'type' => 'passenger_complaint', 'data' => ['type' => 'gate_change', 'severity' => 'high']]
            ],
            'max_score' => 1000,
            'time_bonus_multiplier' => 0.1
        ],

        'weather_diversion' => [
            'title' => 'Weather Emergency Diversion',
            'description' => 'Manage flight diversions due to sudden weather changes',
            'difficulty' => 'advanced',
            'estimated_time' => 1200, // 20 minutes
            'objectives' => [
                [
                    'id' => 'divert_flights',
                    'description' => 'Divert 5 flights safely',
                    'type' => 'counter',
                    'target' => 5,
                    'current' => 0,
                    'points' => 300
                ],
                [
                    'id' => 'rebook_passengers',
                    'description' => 'Rebook 200+ passengers',
                    'type' => 'counter',
                    'target' => 200,
                    'current' => 0,
                    'points' => 250
                ],
                [
                    'id' => 'airline_communication',
                    'description' => 'Communicate effectively with airlines',
                    'type' => 'boolean',
                    'target' => true,
                    'current' => false,
                    'points' => 200
                ]
            ],
            'events' => [
                ['time' => 30, 'type' => 'weather_alert', 'data' => ['type' => 'thunderstorm', 'severity' => 'severe']],
                ['time' => 60, 'type' => 'flight_diversion_request', 'data' => ['flight' => 'AA101', 'reason' => 'weather']],
                ['time' => 120, 'type' => 'passenger_panic', 'data' => ['gate' => 'A1', 'passengers' => 150]],
                ['time' => 180, 'type' => 'flight_diversion_request', 'data' => ['flight' => 'UA202', 'reason' => 'weather']],
                ['time' => 240, 'type' => 'airline_call', 'data' => ['airline' => 'American', 'issue' => 'diversion_coordination']],
                ['time' => 300, 'type' => 'flight_diversion_request', 'data' => ['flight' => 'DL303', 'reason' => 'weather']],
                ['time' => 360, 'type' => 'passenger_rebooking', 'data' => ['passengers' => 80, 'gate' => 'A1']],
                ['time' => 420, 'type' => 'flight_diversion_request', 'data' => ['flight' => 'SW404', 'reason' => 'weather']],
                ['time' => 480, 'type' => 'airline_call', 'data' => ['airline' => 'United', 'issue' => 'crew_reassignment']],
                ['time' => 540, 'type' => 'passenger_rebooking', 'data' => ['passengers' => 120, 'gate' => 'B2']],
                ['time' => 600, 'type' => 'flight_diversion_request', 'data' => ['flight' => 'AA505', 'reason' => 'weather']],
                ['time' => 660, 'type' => 'emergency_meeting', 'data' => ['topic' => 'weather_response_coordination']],
                ['time' => 720, 'type' => 'passenger_rebooking', 'data' => ['passengers' => 100, 'gate' => 'C3']]
            ],
            'max_score' => 1500,
            'time_bonus_multiplier' => 0.15
        ],

        'security_threat' => [
            'title' => 'Security Threat Response',
            'description' => 'Respond to a security incident and coordinate emergency procedures',
            'difficulty' => 'advanced',
            'estimated_time' => 1080, // 18 minutes
            'objectives' => [
                [
                    'id' => 'secure_areas',
                    'description' => 'Secure affected areas within 5 minutes',
                    'type' => 'timer',
                    'target' => 300,
                    'current' => 0,
                    'points' => 250
                ],
                [
                    'id' => 'evacuate_passengers',
                    'description' => 'Evacuate 500+ passengers safely',
                    'type' => 'counter',
                    'target' => 500,
                    'current' => 0,
                    'points' => 300
                ],
                [
                    'id' => 'coordinate_authorities',
                    'description' => 'Coordinate with authorities',
                    'type' => 'boolean',
                    'target' => true,
                    'current' => false,
                    'points' => 250
                ]
            ],
            'events' => [
                ['time' => 30, 'type' => 'security_alert', 'data' => ['type' => 'suspicious_package', 'location' => 'Terminal A']],
                ['time' => 60, 'type' => 'evacuation_order', 'data' => ['area' => 'Terminal A', 'passengers' => 300]],
                ['time' => 90, 'type' => 'police_notification', 'data' => ['response_time' => 180]],
                ['time' => 120, 'type' => 'passenger_panic', 'data' => ['area' => 'Terminal A', 'severity' => 'high']],
                ['time' => 180, 'type' => 'security_teams_deployed', 'data' => ['teams' => 3, 'area' => 'Terminal A']],
                ['time' => 240, 'type' => 'evacuation_progress', 'data' => ['evacuated' => 200, 'total' => 500]],
                ['time' => 300, 'type' => 'bomb_squad_arrival', 'data' => ['assessment_time' => 300]],
                ['time' => 360, 'type' => 'media_inquiry', 'data' => ['outlets' => 5, 'response_required' => true]],
                ['time' => 420, 'type' => 'evacuation_progress', 'data' => ['evacuated' => 350, 'total' => 500]],
                ['time' => 480, 'type' => 'false_alarm_confirmed', 'data' => ['threat_level' => 'none']],
                ['time' => 540, 'type' => 'reopen_terminal', 'data' => ['area' => 'Terminal A', 'inspection_complete' => true]],
                ['time' => 600, 'type' => 'passenger_reentry', 'data' => ['passengers' => 500, 'controlled_entry' => true]]
            ],
            'max_score' => 1200,
            'time_bonus_multiplier' => 0.12
        ],

        'cargo_crisis' => [
            'title' => 'Perishable Cargo Emergency',
            'description' => 'Handle temperature-sensitive cargo that needs immediate attention',
            'difficulty' => 'intermediate',
            'estimated_time' => 720, // 12 minutes
            'objectives' => [
                [
                    'id' => 'identify_cargo',
                    'description' => 'Identify affected cargo containers',
                    'type' => 'counter',
                    'target' => 8,
                    'current' => 0,
                    'points' => 200
                ],
                [
                    'id' => 'coordinate_routing',
                    'description' => 'Coordinate alternative routing',
                    'type' => 'boolean',
                    'target' => true,
                    'current' => false,
                    'points' => 150
                ],
                [
                    'id' => 'minimize_losses',
                    'description' => 'Minimize financial losses',
                    'type' => 'threshold',
                    'target' => 10000,
                    'current' => 50000,
                    'points' => 150
                ]
            ],
            'events' => [
                ['time' => 30, 'type' => 'temperature_alert', 'data' => ['container' => 'C001', 'temperature' => 8, 'threshold' => 2]],
                ['time' => 60, 'type' => 'cargo_inspection', 'data' => ['container' => 'C001', 'contents' => 'vaccines', 'value' => 50000]],
                ['time' => 90, 'type' => 'temperature_alert', 'data' => ['container' => 'C002', 'temperature' => 6, 'threshold' => 2]],
                ['time' => 120, 'type' => 'supplier_contact', 'data' => ['supplier' => 'PharmaCorp', 'urgency' => 'critical']],
                ['time' => 150, 'type' => 'alternative_routing', 'data' => ['container' => 'C001', 'new_route' => 'express_air', 'cost_increase' => 2000]],
                ['time' => 180, 'type' => 'temperature_alert', 'data' => ['container' => 'C003', 'temperature' => 7, 'threshold' => 2]],
                ['time' => 210, 'type' => 'cargo_inspection', 'data' => ['container' => 'C002', 'contents' => 'organs', 'value' => 75000]],
                ['time' => 240, 'type' => 'insurance_claim', 'data' => ['container' => 'C001', 'estimated_loss' => 10000]],
                ['time' => 270, 'type' => 'alternative_routing', 'data' => ['container' => 'C002', 'new_route' => 'priority_ground', 'cost_increase' => 1500]],
                ['time' => 300, 'type' => 'temperature_alert', 'data' => ['container' => 'C004', 'temperature' => 5, 'threshold' => 2]],
                ['time' => 330, 'type' => 'supplier_contact', 'data' => ['supplier' => 'BioTech', 'urgency' => 'high']],
                ['time' => 360, 'type' => 'cargo_inspection', 'data' => ['container' => 'C003', 'contents' => 'blood_products', 'value' => 25000]]
            ],
            'max_score' => 800,
            'time_bonus_multiplier' => 0.08
        ],

        'vip_handling' => [
            'title' => 'VIP Passenger Arrival',
            'description' => 'Manage high-profile passenger arrival with special requirements',
            'difficulty' => 'intermediate',
            'estimated_time' => 600, // 10 minutes
            'objectives' => [
                [
                    'id' => 'coordinate_services',
                    'description' => 'Coordinate special services team',
                    'type' => 'boolean',
                    'target' => true,
                    'current' => false,
                    'points' => 150
                ],
                [
                    'id' => 'ensure_privacy',
                    'description' => 'Ensure passenger privacy',
                    'type' => 'boolean',
                    'target' => true,
                    'current' => false,
                    'points' => 100
                ],
                [
                    'id' => 'manage_media',
                    'description' => 'Manage media presence',
                    'type' => 'boolean',
                    'target' => true,
                    'current' => false,
                    'points' => 100
                ]
            ],
            'events' => [
                ['time' => 30, 'type' => 'vip_arrival_alert', 'data' => ['passenger' => 'John Smith', 'title' => 'CEO', 'company' => 'TechCorp']],
                ['time' => 60, 'type' => 'security_clearance', 'data' => ['level' => 'high', 'escort_required' => true]],
                ['time' => 90, 'type' => 'special_services_request', 'data' => ['service' => 'private_lounge', 'duration' => 120]],
                ['time' => 120, 'type' => 'media_presence', 'data' => ['photographers' => 5, 'reporters' => 3]],
                ['time' => 150, 'type' => 'privacy_concerns', 'data' => ['paparazzi_alert' => true, 'crowd_control_needed' => true]],
                ['time' => 180, 'type' => 'ground_transport', 'data' => ['vehicle' => 'limousine', 'security_detail' => 2]],
                ['time' => 210, 'type' => 'flight_status_update', 'data' => ['flight' => 'AA001', 'status' => 'on_time', 'gate' => 'VIP1']],
                ['time' => 240, 'type' => 'press_release', 'data' => ['coordination_required' => true, 'timing' => 'post_arrival']],
                ['time' => 270, 'type' => 'security_briefing', 'data' => ['threat_assessment' => 'low', 'additional_measures' => false]],
                ['time' => 300, 'type' => 'arrival_procedure', 'data' => ['private_jetbridge' => true, 'customs_fast_track' => true]]
            ],
            'max_score' => 600,
            'time_bonus_multiplier' => 0.06
        ]
    ];

    public function __construct()
    {
        $this->logger = new Logger('scenario_engine');
        $this->achievementService = new AchievementService();
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
            throw new Exception('Database connection failed');
        }
    }

    /**
     * Start a scenario for a user
     */
    public function startScenario($userId, $scenarioId, $roleId = null)
    {
        if (!isset($this->scenarios[$scenarioId])) {
            throw new Exception("Scenario {$scenarioId} not found");
        }

        $scenario = $this->scenarios[$scenarioId];

        try {
            // Check if user already has an active scenario
            $stmt = $this->pdo->prepare("
                SELECT id FROM demo_scenario_attempts
                WHERE user_id = ? AND scenario_id = ? AND completed_at IS NULL
            ");
            $stmt->execute([$userId, $scenarioId]);

            if ($stmt->fetch()) {
                throw new Exception("Scenario already in progress");
            }

            // Start new scenario attempt
            $stmt = $this->pdo->prepare("
                INSERT INTO demo_scenario_attempts (
                    user_id, scenario_id, started_at, progress, score
                ) VALUES (?, ?, NOW(), 0, 0)
            ");
            $stmt->execute([$userId, $scenarioId]);

            // Initialize scenario state
            $scenarioState = [
                'scenario_id' => $scenarioId,
                'user_id' => $userId,
                'objectives' => $scenario['objectives'],
                'events' => $scenario['events'],
                'start_time' => time(),
                'current_event_index' => 0,
                'completed_objectives' => [],
                'score' => 0
            ];

            // Store scenario state (you might want to use Redis or a cache for this)
            $this->storeScenarioState($userId, $scenarioId, $scenarioState);

            $this->logger->info("Scenario started: {$scenarioId} for user {$userId}");

            return [
                'scenario' => $scenario,
                'state' => $scenarioState
            ];
        } catch (Exception $e) {
            $this->logger->error("Failed to start scenario {$scenarioId}", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Process scenario action
     */
    public function processAction($userId, $scenarioId, $action)
    {
        $scenarioState = $this->getScenarioState($userId, $scenarioId);

        if (!$scenarioState) {
            throw new Exception("No active scenario found");
        }

        $scenario = $this->scenarios[$scenarioId];
        $updated = false;

        // Process action based on type
        switch ($action['type']) {
            case 'flight_processed':
                $updated = $this->updateObjective($scenarioState, 'process_flights', 1);
                break;
            case 'gate_change_handled':
                $updated = $this->updateObjective($scenarioState, 'gate_changes', 1);
                break;
            case 'passenger_rebooked':
                $updated = $this->updateObjective($scenarioState, 'rebook_passengers', $action['count'] ?? 1);
                break;
            case 'flight_diverted':
                $updated = $this->updateObjective($scenarioState, 'divert_flights', 1);
                break;
            case 'area_secured':
                $updated = $this->updateObjective($scenarioState, 'secure_areas', time() - $scenarioState['start_time']);
                break;
            case 'passenger_evacuated':
                $updated = $this->updateObjective($scenarioState, 'evacuate_passengers', $action['count'] ?? 1);
                break;
            case 'cargo_identified':
                $updated = $this->updateObjective($scenarioState, 'identify_cargo', 1);
                break;
            case 'communication_established':
                $updated = $this->updateObjective($scenarioState, 'airline_communication', true);
                $updated = $updated || $this->updateObjective($scenarioState, 'coordinate_authorities', true);
                break;
            case 'routing_coordinated':
                $updated = $this->updateObjective($scenarioState, 'coordinate_routing', true);
                break;
            case 'services_coordinated':
                $updated = $this->updateObjective($scenarioState, 'coordinate_services', true);
                break;
            case 'privacy_ensured':
                $updated = $this->updateObjective($scenarioState, 'ensure_privacy', true);
                break;
            case 'media_managed':
                $updated = $this->updateObjective($scenarioState, 'manage_media', true);
                break;
        }

        if ($updated) {
            $this->storeScenarioState($userId, $scenarioId, $scenarioState);

            // Check if scenario is complete
            if ($this->isScenarioComplete($scenarioState)) {
                return $this->completeScenario($userId, $scenarioId, $scenarioState);
            }
        }

        return [
            'updated' => $updated,
            'state' => $scenarioState
        ];
    }

    /**
     * Update objective progress
     */
    private function updateObjective(&$scenarioState, $objectiveId, $value)
    {
        foreach ($scenarioState['objectives'] as &$objective) {
            if ($objective['id'] === $objectiveId) {
                $oldValue = $objective['current'];

                if ($objective['type'] === 'counter') {
                    $objective['current'] = min($objective['target'], $objective['current'] + $value);
                } elseif ($objective['type'] === 'boolean') {
                    $objective['current'] = $value;
                } elseif ($objective['type'] === 'threshold') {
                    $objective['current'] = max(0, $objective['current'] - $value);
                } elseif ($objective['type'] === 'timer') {
                    $objective['current'] = $value;
                }

                // Check if objective is now complete
                if ($this->isObjectiveComplete($objective) && !in_array($objectiveId, $scenarioState['completed_objectives'])) {
                    $scenarioState['completed_objectives'][] = $objectiveId;
                    $scenarioState['score'] += $objective['points'];
                    return true;
                }

                return $oldValue !== $objective['current'];
            }
        }
        return false;
    }

    /**
     * Check if objective is complete
     */
    private function isObjectiveComplete($objective)
    {
        switch ($objective['type']) {
            case 'counter':
            case 'timer':
                return $objective['current'] >= $objective['target'];
            case 'boolean':
                return $objective['current'] === $objective['target'];
            case 'threshold':
                return $objective['current'] <= $objective['target'];
            default:
                return false;
        }
    }

    /**
     * Check if scenario is complete
     */
    private function isScenarioComplete($scenarioState)
    {
        foreach ($scenarioState['objectives'] as $objective) {
            if (!$this->isObjectiveComplete($objective)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Complete scenario and calculate final score
     */
    private function completeScenario($userId, $scenarioId, $scenarioState)
    {
        $scenario = $this->scenarios[$scenarioId];
        $elapsedTime = time() - $scenarioState['start_time'];

        // Calculate time bonus
        $timeBonus = 0;
        if ($elapsedTime < $scenario['estimated_time']) {
            $timeSaved = $scenario['estimated_time'] - $elapsedTime;
            $timeBonus = (int)($timeSaved * $scenario['time_bonus_multiplier']);
        }

        $finalScore = min($scenario['max_score'], $scenarioState['score'] + $timeBonus);

        try {
            // Update scenario attempt
            $stmt = $this->pdo->prepare("
                UPDATE demo_scenario_attempts
                SET completed_at = NOW(), score = ?, success = true, time_taken = ?
                WHERE user_id = ? AND scenario_id = ? AND completed_at IS NULL
            ");
            $stmt->execute([$finalScore, $elapsedTime, $userId, $scenarioId]);

            // Update user progress
            $this->updateUserProgress($userId, $scenarioId, $finalScore);

            // Check for achievements
            $unlockedAchievements = $this->achievementService->checkAchievements($userId, 'scenario_complete', [
                'scenario_id' => $scenarioId,
                'score' => $finalScore,
                'time_taken' => $elapsedTime
            ]);

            // Clear scenario state
            $this->clearScenarioState($userId, $scenarioId);

            $this->logger->info("Scenario completed: {$scenarioId} by user {$userId}, score: {$finalScore}");

            return [
                'completed' => true,
                'score' => $finalScore,
                'time_bonus' => $timeBonus,
                'elapsed_time' => $elapsedTime,
                'unlocked_achievements' => $unlockedAchievements,
                'state' => $scenarioState
            ];
        } catch (Exception $e) {
            $this->logger->error("Failed to complete scenario {$scenarioId}", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Update user progress
     */
    private function updateUserProgress($userId, $scenarioId, $score)
    {
        try {
            // Get or create user progress record
            $stmt = $this->pdo->prepare("
                SELECT id FROM demo_user_progress WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            $progressExists = $stmt->fetch();

            if ($progressExists) {
                $stmt = $this->pdo->prepare("
                    UPDATE demo_user_progress
                    SET scenarios_completed = scenarios_completed + 1,
                        total_score = total_score + ?,
                        last_played = NOW()
                    WHERE user_id = ?
                ");
                $stmt->execute([$score, $userId]);
            } else {
                $stmt = $this->pdo->prepare("
                    INSERT INTO demo_user_progress (
                        user_id, scenarios_completed, total_score, last_played
                    ) VALUES (?, 1, ?, NOW())
                ");
                $stmt->execute([$userId, $score]);
            }
        } catch (Exception $e) {
            $this->logger->error("Failed to update user progress", ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get next scenario event
     */
    public function getNextEvent($userId, $scenarioId)
    {
        $scenarioState = $this->getScenarioState($userId, $scenarioId);

        if (!$scenarioState) {
            return null;
        }

        $scenario = $this->scenarios[$scenarioId];
        $currentTime = time() - $scenarioState['start_time'];
        $events = $scenario['events'];

        // Find next event
        for ($i = $scenarioState['current_event_index']; $i < count($events); $i++) {
            $event = $events[$i];
            if ($event['time'] <= $currentTime) {
                $scenarioState['current_event_index'] = $i + 1;
                $this->storeScenarioState($userId, $scenarioId, $scenarioState);
                return $event;
            }
        }

        return null;
    }

    /**
     * Get scenario state (simplified - in production you'd use Redis/cache)
     */
    private function getScenarioState($userId, $scenarioId)
    {
        // In a real implementation, you'd store this in Redis or a cache
        // For demo purposes, we'll use a simple file-based approach
        $stateFile = sys_get_temp_dir() . "/scenario_{$userId}_{$scenarioId}.json";

        if (file_exists($stateFile)) {
            return json_decode(file_get_contents($stateFile), true);
        }

        return null;
    }

    /**
     * Store scenario state
     */
    private function storeScenarioState($userId, $scenarioId, $state)
    {
        $stateFile = sys_get_temp_dir() . "/scenario_{$userId}_{$scenarioId}.json";
        file_put_contents($stateFile, json_encode($state));
    }

    /**
     * Clear scenario state
     */
    private function clearScenarioState($userId, $scenarioId)
    {
        $stateFile = sys_get_temp_dir() . "/scenario_{$userId}_{$scenarioId}.json";
        if (file_exists($stateFile)) {
            unlink($stateFile);
        }
    }

    /**
     * Get available scenarios
     */
    public function getScenarios()
    {
        return array_map(function($scenario) {
            return [
                'id' => $scenario['id'] ?? '',
                'title' => $scenario['title'],
                'description' => $scenario['description'],
                'difficulty' => $scenario['difficulty'],
                'estimated_time' => $scenario['estimated_time'],
                'max_score' => $scenario['max_score']
            ];
        }, $this->scenarios);
    }

    /**
     * Get scenario details
     */
    public function getScenario($scenarioId)
    {
        return $this->scenarios[$scenarioId] ?? null;
    }

    /**
     * Get user's scenario progress
     */
    public function getUserScenarioProgress($userId)
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT scenario_id, score, time_taken, completed_at
                FROM demo_scenario_attempts
                WHERE user_id = ? AND success = true
                ORDER BY completed_at DESC
            ");
            $stmt->execute([$userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
}
?>
