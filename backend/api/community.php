<?php
/**
 * Community API for Airport Operations Simulator
 *
 * Handles leaderboards, social features, and community interactions
 */

require_once __DIR__ . '/../src/Config.php';
require_once __DIR__ . '/../src/Logger.php';
require_once __DIR__ . '/../services/CommunityService.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

class CommunityAPI
{
    private $communityService;
    private $logger;

    public function __construct()
    {
        $this->logger = new Logger('community_api');
        $this->communityService = new CommunityService();
    }

    public function handleRequest()
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = $_SERVER['REQUEST_URI'];

        // Extract endpoint from path
        $endpoint = str_replace('/api/community/', '', parse_url($path, PHP_URL_PATH));
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
            case 'leaderboard':
                $this->getLeaderboard();
                break;
            case 'stats':
                $this->getCommunityStats();
                break;
            case 'activity':
                $this->getActivityFeed();
                break;
            case 'profile':
                $this->getUserProfile();
                break;
            case 'network':
                $this->getUserNetwork();
                break;
            case 'challenges':
                $this->getChallenges();
                break;
            case 'events':
                $this->getEvents();
                break;
            case 'discussions':
                $this->getDiscussions();
                break;
            default:
                $this->sendError('Endpoint not found', 404);
        }
    }

    private function handlePost($endpoint)
    {
        $data = json_decode(file_get_contents('php://input'), true);

        switch ($endpoint) {
            case 'share':
                $this->shareAchievement($data);
                break;
            case 'follow':
                $this->toggleFollow($data);
                break;
            case 'discussion':
                $this->createDiscussion($data);
                break;
            case 'reply':
                $this->createReply($data);
                break;
            case 'like':
                $this->toggleLike($data);
                break;
            case 'join-challenge':
                $this->joinChallenge($data);
                break;
            case 'join-event':
                $this->joinEvent($data);
                break;
            default:
                $this->sendError('Endpoint not found', 404);
        }
    }

    private function handlePut($endpoint)
    {
        $data = json_decode(file_get_contents('php://input'), true);

        switch ($endpoint) {
            case 'preferences':
                $this->updatePreferences($data);
                break;
            default:
                $this->sendError('Endpoint not found', 404);
        }
    }

    private function getLeaderboard()
    {
        $type = $_GET['type'] ?? 'global'; // global, role, scenario
        $referenceId = $_GET['reference_id'] ?? null;
        $timeframe = $_GET['timeframe'] ?? 'all'; // all, week, month, year
        $limit = (int)($_GET['limit'] ?? 50);

        try {
            switch ($type) {
                case 'global':
                    $leaderboard = $this->communityService->getGlobalLeaderboard($limit, $timeframe);
                    break;
                case 'role':
                    if (!$referenceId) {
                        $this->sendError('Reference ID required for role leaderboard', 400);
                        return;
                    }
                    $leaderboard = $this->communityService->getRoleLeaderboard($referenceId, $limit);
                    break;
                case 'scenario':
                    if (!$referenceId) {
                        $this->sendError('Reference ID required for scenario leaderboard', 400);
                        return;
                    }
                    $leaderboard = $this->communityService->getScenarioLeaderboard($referenceId, $limit);
                    break;
                default:
                    $this->sendError('Invalid leaderboard type', 400);
                    return;
            }

            $this->sendResponse(['leaderboard' => $leaderboard]);
        } catch (Exception $e) {
            $this->logger->error("Failed to get leaderboard", ['error' => $e->getMessage()]);
            $this->sendError('Failed to retrieve leaderboard', 500);
        }
    }

    private function getCommunityStats()
    {
        try {
            $stats = $this->communityService->getCommunityStats();
            $this->sendResponse(['stats' => $stats]);
        } catch (Exception $e) {
            $this->logger->error("Failed to get community stats", ['error' => $e->getMessage()]);
            $this->sendError('Failed to retrieve community stats', 500);
        }
    }

    private function getActivityFeed()
    {
        $limit = (int)($_GET['limit'] ?? 20);

        try {
            $activity = $this->communityService->getActivityFeed($limit);
            $this->sendResponse(['activity' => $activity]);
        } catch (Exception $e) {
            $this->logger->error("Failed to get activity feed", ['error' => $e->getMessage()]);
            $this->sendError('Failed to retrieve activity feed', 500);
        }
    }

    private function getUserProfile()
    {
        $userId = $_GET['user_id'] ?? $this->getCurrentUserId();

        if (!$userId) {
            $this->sendError('User ID required', 400);
            return;
        }

        try {
            $profile = $this->communityService->getUserProfile($userId);
            $this->sendResponse(['profile' => $profile]);
        } catch (Exception $e) {
            $this->logger->error("Failed to get user profile", ['error' => $e->getMessage()]);
            $this->sendError('Failed to retrieve user profile', 500);
        }
    }

    private function getUserNetwork()
    {
        $userId = $_GET['user_id'] ?? $this->getCurrentUserId();
        $type = $_GET['type'] ?? 'followers'; // followers, following
        $limit = (int)($_GET['limit'] ?? 20);

        if (!$userId) {
            $this->sendError('User ID required', 400);
            return;
        }

        try {
            $network = $this->communityService->getUserNetwork($userId, $type, $limit);
            $this->sendResponse(['network' => $network, 'type' => $type]);
        } catch (Exception $e) {
            $this->logger->error("Failed to get user network", ['error' => $e->getMessage()]);
            $this->sendError('Failed to retrieve user network', 500);
        }
    }

    private function getChallenges()
    {
        // This would typically query the community_challenges table
        // For now, return sample challenges
        $challenges = [
            [
                'id' => 1,
                'title' => 'Weekly Speed Challenge',
                'description' => 'Complete 5 scenarios in under 10 minutes each',
                'type' => 'weekly',
                'requirements' => ['scenarios' => 5, 'max_time' => 600],
                'rewards' => ['points' => 500, 'badge' => 'speed_demon'],
                'participants' => 45,
                'start_date' => date('c', strtotime('monday this week')),
                'end_date' => date('c', strtotime('sunday this week'))
            ],
            [
                'id' => 2,
                'title' => 'Achievement Hunter',
                'description' => 'Unlock 3 new achievements this week',
                'type' => 'weekly',
                'requirements' => ['achievements' => 3],
                'rewards' => ['points' => 300, 'badge' => 'achievement_hunter'],
                'participants' => 67,
                'start_date' => date('c', strtotime('monday this week')),
                'end_date' => date('c', strtotime('sunday this week'))
            ]
        ];

        $this->sendResponse(['challenges' => $challenges]);
    }

    private function getEvents()
    {
        // This would typically query the community_events table
        // For now, return sample events
        $events = [
            [
                'id' => 1,
                'title' => 'Airport Operations Tournament',
                'description' => 'Compete in a series of challenging scenarios',
                'type' => 'tournament',
                'start_date' => date('c', strtotime('+2 days')),
                'end_date' => date('c', strtotime('+7 days')),
                'participants' => 23,
                'max_participants' => 50,
                'rewards' => ['points' => 1000, 'badge' => 'champion']
            ]
        ];

        $this->sendResponse(['events' => $events]);
    }

    private function getDiscussions()
    {
        $type = $_GET['type'] ?? 'general'; // general, achievement, scenario
        $referenceId = $_GET['reference_id'] ?? null;
        $limit = (int)($_GET['limit'] ?? 20);

        // This would typically query the community_discussions table
        // For now, return sample discussions
        $discussions = [
            [
                'id' => 1,
                'title' => 'Tips for Weather Diversion Scenario',
                'content' => 'Here are some strategies for handling weather diversions...',
                'author' => 'AirportPro',
                'replies' => 12,
                'last_reply' => date('c', strtotime('-2 hours')),
                'created_at' => date('c', strtotime('-1 day'))
            ],
            [
                'id' => 2,
                'title' => 'Share your achievement stories!',
                'content' => 'What was your most challenging achievement to unlock?',
                'author' => 'AviationFan',
                'replies' => 8,
                'last_reply' => date('c', strtotime('-4 hours')),
                'created_at' => date('c', strtotime('-2 days'))
            ]
        ];

        $this->sendResponse(['discussions' => $discussions]);
    }

    private function shareAchievement($data)
    {
        $userId = $this->getCurrentUserId();
        $achievementId = $data['achievement_id'] ?? null;
        $platform = $data['platform'] ?? 'twitter';

        if (!$achievementId) {
            $this->sendError('Achievement ID required', 400);
            return;
        }

        try {
            $shareData = $this->communityService->shareAchievement($userId, $achievementId, $platform);

            $this->logger->info("Achievement shared", ['user' => $userId, 'achievement' => $achievementId, 'platform' => $platform]);

            $this->sendResponse([
                'message' => 'Achievement shared successfully',
                'share_url' => $shareData['url'],
                'share_text' => $shareData['text']
            ]);
        } catch (Exception $e) {
            $this->logger->error("Failed to share achievement", ['error' => $e->getMessage()]);
            $this->sendError($e->getMessage(), 400);
        }
    }

    private function toggleFollow($data)
    {
        $userId = $this->getCurrentUserId();
        $followedId = $data['user_id'] ?? null;

        if (!$followedId) {
            $this->sendError('User ID to follow required', 400);
            return;
        }

        if ($userId == $followedId) {
            $this->sendError('Cannot follow yourself', 400);
            return;
        }

        try {
            $result = $this->communityService->toggleFollow($userId, $followedId);

            $this->logger->info("Follow toggled", ['follower' => $userId, 'followed' => $followedId, 'action' => $result['action']]);

            $this->sendResponse([
                'message' => 'Follow status updated',
                'action' => $result['action']
            ]);
        } catch (Exception $e) {
            $this->logger->error("Failed to toggle follow", ['error' => $e->getMessage()]);
            $this->sendError($e->getMessage(), 400);
        }
    }

    private function createDiscussion($data)
    {
        $userId = $this->getCurrentUserId();
        $title = $data['title'] ?? null;
        $content = $data['content'] ?? null;
        $type = $data['type'] ?? 'general';
        $referenceId = $data['reference_id'] ?? null;

        if (!$title || !$content) {
            $this->sendError('Title and content required', 400);
            return;
        }

        try {
            // This would typically insert into community_discussions table
            // For now, just return success
            $this->sendResponse([
                'message' => 'Discussion created successfully',
                'discussion_id' => rand(1000, 9999) // Mock ID
            ]);
        } catch (Exception $e) {
            $this->logger->error("Failed to create discussion", ['error' => $e->getMessage()]);
            $this->sendError('Failed to create discussion', 500);
        }
    }

    private function createReply($data)
    {
        $userId = $this->getCurrentUserId();
        $discussionId = $data['discussion_id'] ?? null;
        $content = $data['content'] ?? null;

        if (!$discussionId || !$content) {
            $this->sendError('Discussion ID and content required', 400);
            return;
        }

        try {
            // This would typically insert into discussion_replies table
            // For now, just return success
            $this->sendResponse([
                'message' => 'Reply posted successfully',
                'reply_id' => rand(1000, 9999) // Mock ID
            ]);
        } catch (Exception $e) {
            $this->logger->error("Failed to create reply", ['error' => $e->getMessage()]);
            $this->sendError('Failed to create reply', 500);
        }
    }

    private function toggleLike($data)
    {
        $userId = $this->getCurrentUserId();
        $replyId = $data['reply_id'] ?? null;

        if (!$replyId) {
            $this->sendError('Reply ID required', 400);
            return;
        }

        try {
            // This would typically toggle in reply_likes table
            // For now, just return success
            $this->sendResponse([
                'message' => 'Like toggled successfully'
            ]);
        } catch (Exception $e) {
            $this->logger->error("Failed to toggle like", ['error' => $e->getMessage()]);
            $this->sendError('Failed to toggle like', 500);
        }
    }

    private function joinChallenge($data)
    {
        $userId = $this->getCurrentUserId();
        $challengeId = $data['challenge_id'] ?? null;

        if (!$challengeId) {
            $this->sendError('Challenge ID required', 400);
            return;
        }

        try {
            // This would typically insert into challenge_participants table
            // For now, just return success
            $this->sendResponse([
                'message' => 'Joined challenge successfully'
            ]);
        } catch (Exception $e) {
            $this->logger->error("Failed to join challenge", ['error' => $e->getMessage()]);
            $this->sendError('Failed to join challenge', 500);
        }
    }

    private function joinEvent($data)
    {
        $userId = $this->getCurrentUserId();
        $eventId = $data['event_id'] ?? null;

        if (!$eventId) {
            $this->sendError('Event ID required', 400);
            return;
        }

        try {
            // This would typically insert into event_participants table
            // For now, just return success
            $this->sendResponse([
                'message' => 'Joined event successfully'
            ]);
        } catch (Exception $e) {
            $this->logger->error("Failed to join event", ['error' => $e->getMessage()]);
            $this->sendError('Failed to join event', 500);
        }
    }

    private function updatePreferences($data)
    {
        $userId = $this->getCurrentUserId();

        try {
            // This would typically update user_community_preferences table
            // For now, just return success
            $this->sendResponse([
                'message' => 'Preferences updated successfully'
            ]);
        } catch (Exception $e) {
            $this->logger->error("Failed to update preferences", ['error' => $e->getMessage()]);
            $this->sendError('Failed to update preferences', 500);
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
$api = new CommunityAPI();
$api->handleRequest();
?>
