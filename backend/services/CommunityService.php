<?php
/**
 * Community Service for Airport Operations Simulator
 *
 * Manages leaderboards, social features, and community interactions
 */

require_once __DIR__ . '/../src/Config.php';
require_once __DIR__ . '/../src/Logger.php';

class CommunityService
{
    private $pdo;
    private $logger;

    public function __construct()
    {
        $this->logger = new Logger('community_service');
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
     * Get global leaderboard
     */
    public function getGlobalLeaderboard($limit = 100, $timeframe = 'all')
    {
        try {
            $timeCondition = $this->getTimeframeCondition($timeframe);

            $stmt = $this->pdo->prepare("
                SELECT
                    u.id,
                    u.username,
                    u.first_name,
                    u.last_name,
                    COALESCE(dup.total_score, 0) as total_score,
                    COALESCE(da.achievement_count, 0) as achievements_count,
                    COALESCE(dup.scenarios_completed, 0) as scenarios_completed,
                    COALESCE(dup.last_played, u.created_at) as last_active,
                    ROW_NUMBER() OVER (ORDER BY COALESCE(dup.total_score, 0) DESC, COALESCE(da.achievement_count, 0) DESC) as rank
                FROM users u
                LEFT JOIN demo_user_progress dup ON u.id = dup.user_id
                LEFT JOIN (
                    SELECT user_id, COUNT(*) as achievement_count
                    FROM demo_achievements
                    WHERE unlocked_at >= ?
                    GROUP BY user_id
                ) da ON u.id = da.user_id
                WHERE u.role_id IN (SELECT id FROM roles WHERE name != 'super_admin')
                ORDER BY total_score DESC, achievements_count DESC, scenarios_completed DESC
                LIMIT ?
            ");

            $stmt->execute([$timeCondition, $limit]);
            $leaderboard = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->formatLeaderboard($leaderboard);
        } catch (Exception $e) {
            $this->logger->error("Failed to get global leaderboard", ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get role-specific leaderboard
     */
    public function getRoleLeaderboard($roleId, $limit = 50)
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    u.id,
                    u.username,
                    u.first_name,
                    u.last_name,
                    COALESCE(dup.total_score, 0) as total_score,
                    COALESCE(da.achievement_count, 0) as achievements_count,
                    COALESCE(dup.scenarios_completed, 0) as scenarios_completed,
                    ROW_NUMBER() OVER (ORDER BY COALESCE(dup.total_score, 0) DESC) as rank
                FROM users u
                LEFT JOIN demo_user_progress dup ON u.id = dup.user_id
                LEFT JOIN (
                    SELECT user_id, COUNT(*) as achievement_count
                    FROM demo_achievements
                    GROUP BY user_id
                ) da ON u.id = da.user_id
                WHERE u.role_id = ? AND u.role_id IN (SELECT id FROM roles WHERE name != 'super_admin')
                ORDER BY total_score DESC, achievements_count DESC
                LIMIT ?
            ");

            $stmt->execute([$roleId, $limit]);
            $leaderboard = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->formatLeaderboard($leaderboard);
        } catch (Exception $e) {
            $this->logger->error("Failed to get role leaderboard", ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get scenario-specific leaderboard
     */
    public function getScenarioLeaderboard($scenarioId, $limit = 50)
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    u.id,
                    u.username,
                    u.first_name,
                    u.last_name,
                    dsa.score,
                    dsa.time_taken,
                    dsa.completed_at,
                    ROW_NUMBER() OVER (ORDER BY dsa.score DESC, dsa.time_taken ASC) as rank
                FROM demo_scenario_attempts dsa
                JOIN users u ON dsa.user_id = u.id
                WHERE dsa.scenario_id = ? AND dsa.success = true
                ORDER BY dsa.score DESC, dsa.time_taken ASC
                LIMIT ?
            ");

            $stmt->execute([$scenarioId, $limit]);
            $leaderboard = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return array_map(function($entry) {
                return [
                    'user_id' => $entry['id'],
                    'username' => $entry['username'],
                    'first_name' => $entry['first_name'],
                    'last_name' => $entry['last_name'],
                    'score' => (int)$entry['score'],
                    'time_taken' => (int)$entry['time_taken'],
                    'completed_at' => $entry['completed_at'],
                    'rank' => (int)$entry['rank']
                ];
            }, $leaderboard);
        } catch (Exception $e) {
            $this->logger->error("Failed to get scenario leaderboard", ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get user's rank in different leaderboards
     */
    public function getUserRanks($userId)
    {
        try {
            $ranks = [];

            // Global rank
            $stmt = $this->pdo->prepare("
                SELECT rank FROM (
                    SELECT
                        u.id,
                        ROW_NUMBER() OVER (ORDER BY COALESCE(dup.total_score, 0) DESC) as rank
                    FROM users u
                    LEFT JOIN demo_user_progress dup ON u.id = dup.user_id
                    WHERE u.role_id IN (SELECT id FROM roles WHERE name != 'super_admin')
                ) rankings
                WHERE id = ?
            ");
            $stmt->execute([$userId]);
            $ranks['global'] = $stmt->fetch(PDO::FETCH_ASSOC)['rank'] ?? null;

            // Get user's role
            $stmt = $this->pdo->prepare("SELECT role_id FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $userRole = $stmt->fetch(PDO::FETCH_ASSOC)['role_id'] ?? null;

            if ($userRole) {
                // Role-specific rank
                $stmt = $this->pdo->prepare("
                    SELECT rank FROM (
                        SELECT
                            u.id,
                            ROW_NUMBER() OVER (ORDER BY COALESCE(dup.total_score, 0) DESC) as rank
                        FROM users u
                        LEFT JOIN demo_user_progress dup ON u.id = dup.user_id
                        WHERE u.role_id = ?
                    ) rankings
                    WHERE id = ?
                ");
                $stmt->execute([$userRole, $userId]);
                $ranks['role'] = $stmt->fetch(PDO::FETCH_ASSOC)['rank'] ?? null;
            }

            return $ranks;
        } catch (Exception $e) {
            $this->logger->error("Failed to get user ranks", ['error' => $e->getMessage()]);
            return ['global' => null, 'role' => null];
        }
    }

    /**
     * Share achievement on social media
     */
    public function shareAchievement($userId, $achievementId, $platform = 'twitter')
    {
        try {
            // Get achievement details
            $stmt = $this->pdo->prepare("
                SELECT da.*, u.username
                FROM demo_achievements da
                JOIN users u ON da.user_id = u.id
                WHERE da.user_id = ? AND da.achievement_type = ?
                ORDER BY da.unlocked_at DESC
                LIMIT 1
            ");
            $stmt->execute([$userId, $achievementId]);
            $achievement = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$achievement) {
                throw new Exception('Achievement not found');
            }

            // Generate share URL and text
            $shareData = $this->generateShareContent($achievement, $platform);

            // Log the share
            $this->logSocialShare($userId, $achievementId, $platform);

            return $shareData;
        } catch (Exception $e) {
            $this->logger->error("Failed to share achievement", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get community statistics
     */
    public function getCommunityStats()
    {
        try {
            $stats = [];

            // Total users
            $stmt = $this->pdo->query("
                SELECT COUNT(*) as total_users
                FROM users
                WHERE role_id IN (SELECT id FROM roles WHERE name != 'super_admin')
            ");
            $stats['total_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_users'];

            // Active users (played in last 7 days)
            $stmt = $this->pdo->query("
                SELECT COUNT(DISTINCT user_id) as active_users
                FROM demo_user_progress
                WHERE last_played >= CURRENT_TIMESTAMP - INTERVAL '7 days'
            ");
            $stats['active_users_7d'] = $stmt->fetch(PDO::FETCH_ASSOC)['active_users_7d'] ?? 0;

            // Total scenarios completed
            $stmt = $this->pdo->query("
                SELECT COUNT(*) as total_completions
                FROM demo_scenario_attempts
                WHERE success = true
            ");
            $stats['total_scenario_completions'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_completions'];

            // Total achievements unlocked
            $stmt = $this->pdo->query("
                SELECT COUNT(*) as total_achievements
                FROM demo_achievements
            ");
            $stats['total_achievements_unlocked'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_achievements'];

            // Average session duration
            $stmt = $this->pdo->query("
                SELECT AVG(time_taken) as avg_session_duration
                FROM demo_scenario_attempts
                WHERE success = true AND time_taken > 0
            ");
            $stats['avg_session_duration'] = round($stmt->fetch(PDO::FETCH_ASSOC)['avg_session_duration'] ?? 0);

            return $stats;
        } catch (Exception $e) {
            $this->logger->error("Failed to get community stats", ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get recent activity feed
     */
    public function getActivityFeed($limit = 20)
    {
        try {
            $activities = [];

            // Recent achievements
            $stmt = $this->pdo->prepare("
                SELECT
                    'achievement' as type,
                    da.achievement_name as title,
                    CONCAT(u.first_name, ' ', u.last_name) as user_name,
                    da.unlocked_at as timestamp,
                    da.points_earned as points
                FROM demo_achievements da
                JOIN users u ON da.user_id = u.id
                ORDER BY da.unlocked_at DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            $achievements = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $activities = array_merge($activities, $achievements);

            // Recent scenario completions
            $stmt = $this->pdo->prepare("
                SELECT
                    'scenario' as type,
                    CONCAT('Completed ', dsa.scenario_id) as title,
                    CONCAT(u.first_name, ' ', u.last_name) as user_name,
                    dsa.completed_at as timestamp,
                    dsa.score as points
                FROM demo_scenario_attempts dsa
                JOIN users u ON dsa.user_id = u.id
                WHERE dsa.success = true
                ORDER BY dsa.completed_at DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            $scenarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $activities = array_merge($activities, $scenarios);

            // Sort by timestamp and limit
            usort($activities, function($a, $b) {
                return strtotime($b['timestamp']) - strtotime($a['timestamp']);
            });

            return array_slice($activities, 0, $limit);
        } catch (Exception $e) {
            $this->logger->error("Failed to get activity feed", ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Create user profile
     */
    public function getUserProfile($userId)
    {
        try {
            // Get basic user info
            $stmt = $this->pdo->prepare("
                SELECT u.*, r.name as role_name
                FROM users u
                JOIN roles r ON u.role_id = r.id
                WHERE u.id = ?
            ");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                throw new Exception('User not found');
            }

            // Get user stats
            $stmt = $this->pdo->prepare("
                SELECT
                    COALESCE(dup.total_score, 0) as total_score,
                    COALESCE(dup.scenarios_completed, 0) as scenarios_completed,
                    COALESCE(dup.experience_points, 0) as experience_points,
                    COALESCE(da.achievement_count, 0) as achievements_count
                FROM demo_user_progress dup
                LEFT JOIN (
                    SELECT user_id, COUNT(*) as achievement_count
                    FROM demo_achievements
                    GROUP BY user_id
                ) da ON dup.user_id = da.user_id
                WHERE dup.user_id = ?
            ");
            $stmt->execute([$userId]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
                'total_score' => 0,
                'scenarios_completed' => 0,
                'experience_points' => 0,
                'achievements_count' => 0
            ];

            // Get recent achievements
            $stmt = $this->pdo->prepare("
                SELECT achievement_name, points_earned, unlocked_at
                FROM demo_achievements
                WHERE user_id = ?
                ORDER BY unlocked_at DESC
                LIMIT 5
            ");
            $stmt->execute([$userId]);
            $recentAchievements = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get user ranks
            $ranks = $this->getUserRanks($userId);

            return [
                'user' => $user,
                'stats' => $stats,
                'recent_achievements' => $recentAchievements,
                'ranks' => $ranks
            ];
        } catch (Exception $e) {
            $this->logger->error("Failed to get user profile", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Follow/unfollow user
     */
    public function toggleFollow($followerId, $followedId)
    {
        try {
            // Check if already following
            $stmt = $this->pdo->prepare("
                SELECT id FROM user_follows
                WHERE follower_id = ? AND followed_id = ?
            ");
            $stmt->execute([$followerId, $followedId]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                // Unfollow
                $stmt = $this->pdo->prepare("DELETE FROM user_follows WHERE id = ?");
                $stmt->execute([$existing['id']]);
                return ['action' => 'unfollowed'];
            } else {
                // Follow
                $stmt = $this->pdo->prepare("
                    INSERT INTO user_follows (follower_id, followed_id, created_at)
                    VALUES (?, ?, NOW())
                ");
                $stmt->execute([$followerId, $followedId]);
                return ['action' => 'followed'];
            }
        } catch (Exception $e) {
            $this->logger->error("Failed to toggle follow", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get user's followers/following
     */
    public function getUserNetwork($userId, $type = 'followers', $limit = 20)
    {
        try {
            if ($type === 'followers') {
                $stmt = $this->pdo->prepare("
                    SELECT u.id, u.username, u.first_name, u.last_name
                    FROM user_follows uf
                    JOIN users u ON uf.follower_id = u.id
                    WHERE uf.followed_id = ?
                    ORDER BY uf.created_at DESC
                    LIMIT ?
                ");
            } else {
                $stmt = $this->pdo->prepare("
                    SELECT u.id, u.username, u.first_name, u.last_name
                    FROM user_follows uf
                    JOIN users u ON uf.followed_id = u.id
                    WHERE uf.follower_id = ?
                    ORDER BY uf.created_at DESC
                    LIMIT ?
                ");
            }

            $stmt->execute([$userId, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logger->error("Failed to get user network", ['error' => $e->getMessage()]);
            return [];
        }
    }

    // Helper methods

    private function getTimeframeCondition($timeframe)
    {
        switch ($timeframe) {
            case 'week':
                return date('Y-m-d H:i:s', strtotime('-1 week'));
            case 'month':
                return date('Y-m-d H:i:s', strtotime('-1 month'));
            case 'year':
                return date('Y-m-d H:i:s', strtotime('-1 year'));
            default:
                return '1970-01-01 00:00:00'; // All time
        }
    }

    private function formatLeaderboard($leaderboard)
    {
        return array_map(function($entry) {
            return [
                'user_id' => $entry['id'],
                'username' => $entry['username'],
                'first_name' => $entry['first_name'],
                'last_name' => $entry['last_name'],
                'total_score' => (int)$entry['total_score'],
                'achievements_count' => (int)$entry['achievements_count'],
                'scenarios_completed' => (int)$entry['scenarios_completed'],
                'last_active' => $entry['last_active'],
                'rank' => (int)$entry['rank']
            ];
        }, $leaderboard);
    }

    private function generateShareContent($achievement, $platform)
    {
        $baseUrl = getenv('APP_URL') ?: 'https://airport-simulator.com';
        $shareUrl = $baseUrl . '/achievement/' . $achievement['achievement_type'];

        switch ($platform) {
            case 'twitter':
                return [
                    'url' => 'https://twitter.com/intent/tweet?' . http_build_query([
                        'text' => "I just unlocked the '{$achievement['achievement_name']}' achievement in Airport Operations Simulator! ✈️🎮 #AirportSim #Aviation",
                        'url' => $shareUrl
                    ]),
                    'text' => "Share on Twitter"
                ];
            case 'facebook':
                return [
                    'url' => 'https://www.facebook.com/sharer/sharer.php?' . http_build_query([
                        'u' => $shareUrl
                    ]),
                    'text' => "Share on Facebook"
                ];
            case 'linkedin':
                return [
                    'url' => 'https://www.linkedin.com/sharing/share-offsite/?' . http_build_query([
                        'url' => $shareUrl
                    ]),
                    'text' => "Share on LinkedIn"
                ];
            default:
                return [
                    'url' => $shareUrl,
                    'text' => "Share Achievement"
                ];
        }
    }

    private function logSocialShare($userId, $achievementId, $platform)
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO social_shares (user_id, achievement_id, platform, shared_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$userId, $achievementId, $platform]);
        } catch (Exception $e) {
            // Log error but don't fail the share
            $this->logger->error("Failed to log social share", ['error' => $e->getMessage()]);
        }
    }
}
?>
