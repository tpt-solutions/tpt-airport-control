<?php
/**
 * Achievement Service for Airport Operations Simulator
 *
 * Manages achievements, scoring, and gamification features
 */

require_once __DIR__ . '/../src/Config.php';
require_once __DIR__ . '/../src/Logger.php';

class AchievementService
{
    private $pdo;
    private $logger;

    // Achievement definitions
    private $achievements = [
        // Basic achievements
        'first_scenario' => [
            'name' => 'First Flight',
            'description' => 'Complete your first airport scenario',
            'points' => 100,
            'rarity' => 'common',
            'icon' => '✈️',
            'category' => 'milestone'
        ],
        'speed_demon' => [
            'name' => 'Speed Demon',
            'description' => 'Complete a scenario in under 5 minutes',
            'points' => 250,
            'rarity' => 'rare',
            'icon' => '⚡',
            'category' => 'performance'
        ],
        'perfectionist' => [
            'name' => 'Perfectionist',
            'description' => 'Achieve 100% score on any scenario',
            'points' => 500,
            'rarity' => 'epic',
            'icon' => '💎',
            'category' => 'performance'
        ],
        'crisis_manager' => [
            'name' => 'Crisis Manager',
            'description' => 'Successfully handle 5 emergency scenarios',
            'points' => 750,
            'rarity' => 'legendary',
            'icon' => '🛡️',
            'category' => 'specialist'
        ],

        // Role-specific achievements
        'air_traffic_controller' => [
            'name' => 'Air Traffic Controller',
            'description' => 'Master the controller role with 10+ scenarios',
            'points' => 1000,
            'rarity' => 'legendary',
            'icon' => '🎯',
            'category' => 'role_master'
        ],
        'emergency_coordinator' => [
            'name' => 'Emergency Response Expert',
            'description' => 'Successfully coordinate 10 emergency responses',
            'points' => 800,
            'rarity' => 'epic',
            'icon' => '🚨',
            'category' => 'role_master'
        ],
        'cargo_specialist' => [
            'name' => 'Cargo Operations Expert',
            'description' => 'Manage cargo operations for 15+ flights',
            'points' => 600,
            'rarity' => 'epic',
            'icon' => '📦',
            'category' => 'role_master'
        ],
        'security_expert' => [
            'name' => 'Security Operations Expert',
            'description' => 'Handle 20+ security incidents successfully',
            'points' => 700,
            'rarity' => 'epic',
            'icon' => '🔒',
            'category' => 'role_master'
        ],

        // Scenario-based achievements
        'weather_warrior' => [
            'name' => 'Weather Warrior',
            'description' => 'Successfully manage operations during severe weather',
            'points' => 400,
            'rarity' => 'rare',
            'icon' => '⛈️',
            'category' => 'scenario'
        ],
        'peak_hour_hero' => [
            'name' => 'Peak Hour Hero',
            'description' => 'Maintain efficiency during peak traffic hours',
            'points' => 350,
            'rarity' => 'rare',
            'icon' => '🕐',
            'category' => 'scenario'
        ],
        'vip_handler' => [
            'name' => 'VIP Handler',
            'description' => 'Successfully manage VIP passenger operations',
            'points' => 300,
            'rarity' => 'rare',
            'icon' => '👑',
            'category' => 'scenario'
        ],

        // Progress achievements
        'scenario_veteran' => [
            'name' => 'Scenario Veteran',
            'description' => 'Complete 50 scenarios across all roles',
            'points' => 1500,
            'rarity' => 'legendary',
            'icon' => '🏆',
            'category' => 'progress'
        ],
        'role_explorer' => [
            'name' => 'Role Explorer',
            'description' => 'Try all 15 different airport roles',
            'points' => 1200,
            'rarity' => 'legendary',
            'icon' => '🗺️',
            'category' => 'progress'
        ],
        'efficiency_expert' => [
            'name' => 'Efficiency Expert',
            'description' => 'Achieve an average score above 90% across 25 scenarios',
            'points' => 900,
            'rarity' => 'epic',
            'icon' => '📊',
            'category' => 'progress'
        ]
    ];

    public function __construct()
    {
        $this->logger = new Logger('achievement_service');
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
     * Check and unlock achievements for a user
     */
    public function checkAchievements($userId, $eventType, $eventData = [])
    {
        $unlockedAchievements = [];

        switch ($eventType) {
            case 'scenario_complete':
                $unlockedAchievements = array_merge(
                    $unlockedAchievements,
                    $this->checkScenarioAchievements($userId, $eventData)
                );
                break;

            case 'role_mastered':
                $unlockedAchievements = array_merge(
                    $unlockedAchievements,
                    $this->checkRoleAchievements($userId, $eventData)
                );
                break;

            case 'performance_milestone':
                $unlockedAchievements = array_merge(
                    $unlockedAchievements,
                    $this->checkPerformanceAchievements($userId, $eventData)
                );
                break;
        }

        // Check progress achievements
        $unlockedAchievements = array_merge(
            $unlockedAchievements,
            $this->checkProgressAchievements($userId)
        );

        return $unlockedAchievements;
    }

    /**
     * Check scenario completion achievements
     */
    private function checkScenarioAchievements($userId, $eventData)
    {
        $unlocked = [];
        $scenarioId = $eventData['scenario_id'] ?? null;
        $score = $eventData['score'] ?? 0;
        $timeTaken = $eventData['time_taken'] ?? 0;

        if (!$scenarioId) return $unlocked;

        // First scenario achievement
        if (!$this->hasAchievement($userId, 'first_scenario')) {
            $this->unlockAchievement($userId, 'first_scenario');
            $unlocked[] = 'first_scenario';
        }

        // Speed demon achievement
        if ($timeTaken < 300 && !$this->hasAchievement($userId, 'speed_demon')) { // Under 5 minutes
            $this->unlockAchievement($userId, 'speed_demon');
            $unlocked[] = 'speed_demon';
        }

        // Perfectionist achievement
        if ($score >= 100 && !$this->hasAchievement($userId, 'perfectionist')) {
            $this->unlockAchievement($userId, 'perfectionist');
            $unlocked[] = 'perfectionist';
        }

        // Scenario-specific achievements
        switch ($scenarioId) {
            case 'weather_diversion':
                if (!$this->hasAchievement($userId, 'weather_warrior')) {
                    $this->unlockAchievement($userId, 'weather_warrior');
                    $unlocked[] = 'weather_warrior';
                }
                break;
            case 'morning_rush':
                if (!$this->hasAchievement($userId, 'peak_hour_hero')) {
                    $this->unlockAchievement($userId, 'peak_hour_hero');
                    $unlocked[] = 'peak_hour_hero';
                }
                break;
            case 'vip_handling':
                if (!$this->hasAchievement($userId, 'vip_handler')) {
                    $this->unlockAchievement($userId, 'vip_handler');
                    $unlocked[] = 'vip_handler';
                }
                break;
        }

        return $unlocked;
    }

    /**
     * Check role mastery achievements
     */
    private function checkRoleAchievements($userId, $eventData)
    {
        $unlocked = [];
        $role = $eventData['role'] ?? null;
        $scenarioCount = $eventData['scenario_count'] ?? 0;

        if (!$role) return $unlocked;

        // Role-specific mastery achievements
        $roleAchievements = [
            'controller' => ['air_traffic_controller', 10],
            'emergency_coordinator' => ['emergency_coordinator', 10],
            'cargo_manager' => ['cargo_specialist', 15],
            'security_officer' => ['security_expert', 20]
        ];

        if (isset($roleAchievements[$role])) {
            list($achievementId, $requiredCount) = $roleAchievements[$role];

            if ($scenarioCount >= $requiredCount && !$this->hasAchievement($userId, $achievementId)) {
                $this->unlockAchievement($userId, $achievementId);
                $unlocked[] = $achievementId;
            }
        }

        return $unlocked;
    }

    /**
     * Check performance-based achievements
     */
    private function checkPerformanceAchievements($userId, $eventData)
    {
        $unlocked = [];

        // Crisis manager achievement
        $emergencyScenarios = $this->countEmergencyScenarios($userId);
        if ($emergencyScenarios >= 5 && !$this->hasAchievement($userId, 'crisis_manager')) {
            $this->unlockAchievement($userId, 'crisis_manager');
            $unlocked[] = 'crisis_manager';
        }

        return $unlocked;
    }

    /**
     * Check progress-based achievements
     */
    private function checkProgressAchievements($userId)
    {
        $unlocked = [];

        // Get user stats
        $stats = $this->getUserStats($userId);

        // Scenario veteran
        if ($stats['total_scenarios'] >= 50 && !$this->hasAchievement($userId, 'scenario_veteran')) {
            $this->unlockAchievement($userId, 'scenario_veteran');
            $unlocked[] = 'scenario_veteran';
        }

        // Role explorer
        if ($stats['unique_roles'] >= 15 && !$this->hasAchievement($userId, 'role_explorer')) {
            $this->unlockAchievement($userId, 'role_explorer');
            $unlocked[] = 'role_explorer';
        }

        // Efficiency expert
        if ($stats['total_scenarios'] >= 25 && $stats['average_score'] >= 90 && !$this->hasAchievement($userId, 'efficiency_expert')) {
            $this->unlockAchievement($userId, 'efficiency_expert');
            $unlocked[] = 'efficiency_expert';
        }

        return $unlocked;
    }

    /**
     * Unlock an achievement for a user
     */
    public function unlockAchievement($userId, $achievementId)
    {
        if (!isset($this->achievements[$achievementId])) {
            return false;
        }

        $achievement = $this->achievements[$achievementId];

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO demo_achievements (
                    user_id, achievement_type, achievement_name,
                    description, points_earned, badge_icon, rarity, unlocked_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            $stmt->execute([
                $userId,
                $achievementId,
                $achievement['name'],
                $achievement['description'],
                $achievement['points'],
                $achievement['icon'],
                $achievement['rarity']
            ]);

            $this->logger->info("Achievement unlocked: {$achievement['name']} for user {$userId}");

            return true;
        } catch (Exception $e) {
            $this->logger->error("Failed to unlock achievement {$achievementId}", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Check if user has achievement
     */
    public function hasAchievement($userId, $achievementId)
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id FROM demo_achievements
                WHERE user_id = ? AND achievement_type = ?
            ");
            $stmt->execute([$userId, $achievementId]);
            return $stmt->fetch() !== false;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get user's achievements
     */
    public function getUserAchievements($userId)
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT achievement_type, achievement_name, description,
                       points_earned, badge_icon, rarity, unlocked_at
                FROM demo_achievements
                WHERE user_id = ?
                ORDER BY unlocked_at DESC
            ");
            $stmt->execute([$userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Get user statistics
     */
    public function getUserStats($userId)
    {
        try {
            // Get scenario completion stats
            $stmt = $this->pdo->prepare("
                SELECT
                    COUNT(*) as total_scenarios,
                    AVG(score) as average_score,
                    COUNT(DISTINCT role_name) as unique_roles
                FROM demo_scenario_attempts dsa
                JOIN demo_user_progress dup ON dsa.user_id = dup.user_id
                WHERE dsa.user_id = ? AND dsa.success = true
            ");
            $stmt->execute([$userId]);
            $scenarioStats = $stmt->fetch(PDO::FETCH_ASSOC);

            // Get achievement stats
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as total_achievements, SUM(points_earned) as total_points
                FROM demo_achievements
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            $achievementStats = $stmt->fetch(PDO::FETCH_ASSOC);

            return array_merge($scenarioStats, $achievementStats);
        } catch (Exception $e) {
            return [
                'total_scenarios' => 0,
                'average_score' => 0,
                'unique_roles' => 0,
                'total_achievements' => 0,
                'total_points' => 0
            ];
        }
    }

    /**
     * Count emergency scenarios completed
     */
    private function countEmergencyScenarios($userId)
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count
                FROM demo_scenario_attempts dsa
                JOIN demo_scenarios ds ON dsa.scenario_id = ds.scenario_id
                WHERE dsa.user_id = ? AND ds.category = 'emergency' AND dsa.success = true
            ");
            $stmt->execute([$userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['count'] ?? 0;
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Get leaderboard
     */
    public function getLeaderboard($limit = 50)
    {
        try {
            $stmt = $this->pdo->query("
                SELECT
                    u.username,
                    u.first_name,
                    u.last_name,
                    COALESCE(dup.total_score, 0) as total_score,
                    COALESCE(da.achievement_count, 0) as achievements_count,
                    COALESCE(dup.scenarios_completed, 0) as scenarios_completed,
                    COALESCE(dup.last_played, u.created_at) as last_active
                FROM users u
                LEFT JOIN demo_user_progress dup ON u.id = dup.user_id
                LEFT JOIN (
                    SELECT user_id, COUNT(*) as achievement_count
                    FROM demo_achievements
                    GROUP BY user_id
                ) da ON u.id = da.user_id
                WHERE u.role_id IN (SELECT id FROM roles WHERE name != 'super_admin')
                ORDER BY total_score DESC, achievements_count DESC, scenarios_completed DESC
                LIMIT {$limit}
            ");

            $leaderboard = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Add ranks
            foreach ($leaderboard as $index => &$entry) {
                $entry['rank'] = $index + 1;
            }

            return $leaderboard;
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Get achievement definitions
     */
    public function getAchievementDefinitions()
    {
        return $this->achievements;
    }

    /**
     * Calculate user level based on experience points
     */
    public function calculateUserLevel($experiencePoints)
    {
        // Simple level calculation: every 1000 points = 1 level
        return floor($experiencePoints / 1000) + 1;
    }

    /**
     * Get experience points needed for next level
     */
    public function getExperienceForNextLevel($currentLevel)
    {
        return $currentLevel * 1000;
    }
}
?>
