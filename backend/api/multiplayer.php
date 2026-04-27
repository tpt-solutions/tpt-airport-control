<?php
/**
 * Multiplayer Operations API
 * Collaborative multi-user scenario system
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once __DIR__ . '/../src/Logger.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Middleware.php';
require_once __DIR__ . '/../services/MultiplayerService.php';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        exit(0);
    }

    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? 'status';

    $multiplayer = new MultiplayerService($pdo);

    switch ($method) {
        case 'GET':
            handleGetRequest($action, $multiplayer);
            break;
        case 'POST':
            handlePostRequest($action, $multiplayer);
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    Logger::error('Multiplayer API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

function handleGetRequest($action, MultiplayerService $multiplayer) {
    Middleware::authenticate();
    
    $userId = Middleware::getCurrentUserId();
    
    switch ($action) {
        case 'sessions':
            $activeSessions = $multiplayer->getActiveSessions($userId);
            echo json_encode(['sessions' => $activeSessions]);
            break;
            
        case 'session':
            $sessionId = $_GET['id'] ?? null;
            if (!$sessionId) {
                http_response_code(400);
                echo json_encode(['error' => 'Session ID required']);
                return;
            }
            $session = $multiplayer->getSession($sessionId, $userId);
            echo json_encode($session);
            break;
            
        case 'players':
            $sessionId = $_GET['session_id'] ?? null;
            $players = $multiplayer->getSessionPlayers($sessionId);
            echo json_encode(['players' => $players]);
            break;
            
        case 'events':
            $sessionId = $_GET['session_id'] ?? null;
            $lastEventId = $_GET['last_event_id'] ?? 0;
            $events = $multiplayer->getSessionEvents($sessionId, $lastEventId, $userId);
            echo json_encode(['events' => $events]);
            break;
            
        case 'status':
            $status = $multiplayer->getUserStatus($userId);
            echo json_encode($status);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
}

function handlePostRequest($action, MultiplayerService $multiplayer) {
    Middleware::authenticate();
    
    $userId = Middleware::getCurrentUserId();
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        return;
    }

    switch ($action) {
        case 'create':
            Middleware::validateInput($input, ['scenario_id', 'session_name']);
            $session = $multiplayer->createSession(
                $userId,
                $input['scenario_id'],
                $input['session_name'],
                $input['max_players'] ?? 4,
                $input['is_private'] ?? false,
                $input['settings'] ?? []
            );
            echo json_encode([
                'message' => 'Session created',
                'session_id' => $session['id'],
                'session_code' => $session['join_code']
            ]);
            break;
            
        case 'join':
            Middleware::validateInput($input, ['session_code']);
            $result = $multiplayer->joinSession($userId, $input['session_code']);
            echo json_encode([
                'message' => 'Joined session',
                'session_id' => $result['session_id']
            ]);
            break;
            
        case 'leave':
            $sessionId = $input['session_id'] ?? null;
            $multiplayer->leaveSession($sessionId, $userId);
            echo json_encode(['message' => 'Left session']);
            break;
            
        case 'send_event':
            Middleware::validateInput($input, ['session_id', 'event_type', 'event_data']);
            $eventId = $multiplayer->sendEvent(
                $input['session_id'],
                $userId,
                $input['event_type'],
                $input['event_data'],
                $input['target_players'] ?? null
            );
            echo json_encode([
                'message' => 'Event sent',
                'event_id' => $eventId
            ]);
            break;
            
        case 'update_player':
            $sessionId = $input['session_id'] ?? null;
            $multiplayer->updatePlayerState(
                $sessionId,
                $userId,
                $input['player_state'] ?? []
            );
            echo json_encode(['message' => 'Player state updated']);
            break;
            
        case 'start_scenario':
            $sessionId = $input['session_id'] ?? null;
            $multiplayer->startScenario($sessionId, $userId);
            echo json_encode(['message' => 'Scenario started']);
            break;
            
        case 'end_scenario':
            $sessionId = $input['session_id'] ?? null;
            $multiplayer->endScenario($sessionId, $userId);
            echo json_encode(['message' => 'Scenario ended']);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
}