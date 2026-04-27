<?php
/**
 * Multiplayer Operations Service
 * Collaborative multi-user scenario management
 */

class MultiplayerService {
    private $db;
    private $logger;

    const SESSION_STATUS_WAITING = 'waiting';
    const SESSION_STATUS_PLAYING = 'playing';
    const SESSION_STATUS_FINISHED = 'finished';
    
    const EVENT_TYPE_JOIN = 'player_join';
    const EVENT_TYPE_LEAVE = 'player_leave';
    const EVENT_TYPE_ACTION = 'player_action';
    const EVENT_TYPE_CHAT = 'chat_message';
    const EVENT_TYPE_STATE_UPDATE = 'state_update';
    const EVENT_TYPE_SYSTEM = 'system_message';

    public function __construct(PDO $db) {
        $this->db = $db;
        $this->logger = new Logger();
    }

    /**
     * Create a new multiplayer session
     */
    public function createSession(int $hostId, int $scenarioId, string $sessionName, int $maxPlayers = 4, bool $isPrivate = false, array $settings = []): array {
        $joinCode = $this->generateJoinCode();
        
        $stmt = $this->db->prepare("
            INSERT INTO multiplayer_sessions 
            (host_id, scenario_id, session_name, join_code, max_players, is_private, settings, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $hostId,
            $scenarioId,
            $sessionName,
            $joinCode,
            $maxPlayers,
            $isPrivate ? 1 : 0,
            json_encode($settings),
            self::SESSION_STATUS_WAITING
        ]);

        $sessionId = $this->db->lastInsertId();
        
        // Add host as first player
        $this->addPlayerToSession($sessionId, $hostId, 'host');

        return [
            'id' => $sessionId,
            'join_code' => $joinCode
        ];
    }

    /**
     * Join an existing session using join code
     */
    public function joinSession(int $userId, string $joinCode): array {
        $stmt = $this->db->prepare("
            SELECT id, max_players, status FROM multiplayer_sessions 
            WHERE join_code = ? AND status = ?
        ");
        $stmt->execute([$joinCode, self::SESSION_STATUS_WAITING]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$session) {
            throw new Exception('Session not found or not available');
        }

        // Check player count
        $playerCount = $this->getSessionPlayerCount($session['id']);
        if ($playerCount >= $session['max_players']) {
            throw new Exception('Session is full');
        }

        // Check if already in session
        if ($this->isPlayerInSession($session['id'], $userId)) {
            return ['session_id' => $session['id']];
        }

        $this->addPlayerToSession($session['id'], $userId, 'player');

        // Send join event
        $this->sendEvent($session['id'], $userId, self::EVENT_TYPE_JOIN, [
            'user_id' => $userId,
            'timestamp' => time()
        ]);

        return ['session_id' => $session['id']];
    }

    /**
     * Leave a session
     */
    public function leaveSession(int $sessionId, int $userId): void {
        $stmt = $this->db->prepare("
            UPDATE multiplayer_players 
            SET left_at = NOW() 
            WHERE session_id = ? AND user_id = ? AND left_at IS NULL
        ");
        $stmt->execute([$sessionId, $userId]);

        $this->sendEvent($sessionId, $userId, self::EVENT_TYPE_LEAVE, [
            'user_id' => $userId,
            'timestamp' => time()
        ]);

        // Check if host left
        $remainingPlayers = $this->getActiveSessionPlayers($sessionId);
        if (empty($remainingPlayers)) {
            $this->endSession($sessionId);
        }
    }

    /**
     * Get active sessions for user
     */
    public function getActiveSessions(int $userId): array {
        $stmt = $this->db->prepare("
            SELECT 
                ms.id, ms.session_name, ms.join_code, ms.max_players, ms.status,
                ms.created_at, s.name as scenario_name,
                (SELECT COUNT(*) FROM multiplayer_players WHERE session_id = ms.id AND left_at IS NULL) as player_count
            FROM multiplayer_sessions ms
            JOIN scenarios s ON ms.scenario_id = s.id
            WHERE ms.status IN (?, ?)
            AND (ms.is_private = 0 OR EXISTS (
                SELECT 1 FROM multiplayer_players 
                WHERE session_id = ms.id AND user_id = ? AND left_at IS NULL
            ))
            ORDER BY ms.created_at DESC
            LIMIT 20
        ");
        $stmt->execute([self::SESSION_STATUS_WAITING, self::SESSION_STATUS_PLAYING, $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get session details
     */
    public function getSession(int $sessionId, int $userId): array {
        if (!$this->isPlayerInSession($sessionId, $userId)) {
            throw new Exception('Access denied');
        }

        $stmt = $this->db->prepare("
            SELECT ms.*, s.name as scenario_name, s.description as scenario_description
            FROM multiplayer_sessions ms
            JOIN scenarios s ON ms.scenario_id = s.id
            WHERE ms.id = ?
        ");
        $stmt->execute([$sessionId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get all players in session
     */
    public function getSessionPlayers(int $sessionId): array {
        $stmt = $this->db->prepare("
            SELECT 
                mp.user_id, mp.role, mp.player_state, mp.joined_at,
                u.username, u.first_name, u.last_name
            FROM multiplayer_players mp
            JOIN users u ON mp.user_id = u.id
            WHERE mp.session_id = ? AND mp.left_at IS NULL
            ORDER BY mp.joined_at
        ");
        $stmt->execute([$sessionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get session events since last event id
     */
    public function getSessionEvents(int $sessionId, int $lastEventId, int $userId): array {
        if (!$this->isPlayerInSession($sessionId, $userId)) {
            throw new Exception('Access denied');
        }

        $stmt = $this->db->prepare("
            SELECT id, event_type, event_data, sender_id, created_at
            FROM multiplayer_events
            WHERE session_id = ? AND id > ?
            ORDER BY created_at ASC
            LIMIT 100
        ");
        $stmt->execute([$sessionId, $lastEventId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Send event to session
     */
    public function sendEvent(int $sessionId, int $senderId, string $eventType, array $eventData, array $targetPlayers = null): int {
        $stmt = $this->db->prepare("
            INSERT INTO multiplayer_events 
            (session_id, sender_id, event_type, event_data, target_players, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $sessionId,
            $senderId,
            $eventType,
            json_encode($eventData),
            $targetPlayers ? json_encode($targetPlayers) : null
        ]);

        return $this->db->lastInsertId();
    }

    /**
     * Update player state
     */
    public function updatePlayerState(int $sessionId, int $userId, array $playerState): void {
        $stmt = $this->db->prepare("
            UPDATE multiplayer_players 
            SET player_state = ? 
            WHERE session_id = ? AND user_id = ? AND left_at IS NULL
        ");
        $stmt->execute([json_encode($playerState), $sessionId, $userId]);
    }

    /**
     * Start scenario for session
     */
    public function startScenario(int $sessionId, int $userId): void {
        if (!$this->isSessionHost($sessionId, $userId)) {
            throw new Exception('Only host can start scenario');
        }

        $stmt = $this->db->prepare("
            UPDATE multiplayer_sessions 
            SET status = ?, started_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([self::SESSION_STATUS_PLAYING, $sessionId]);

        $this->sendEvent($sessionId, $userId, self::EVENT_TYPE_SYSTEM, [
            'action' => 'scenario_started',
            'timestamp' => time()
        ]);
    }

    /**
     * End scenario for session
     */
    public function endScenario(int $sessionId, int $userId): void {
        if (!$this->isSessionHost($sessionId, $userId)) {
            throw new Exception('Only host can end scenario');
        }

        $stmt = $this->db->prepare("
            UPDATE multiplayer_sessions 
            SET status = ?, ended_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([self::SESSION_STATUS_FINISHED, $sessionId]);

        $this->sendEvent($sessionId, $userId, self::EVENT_TYPE_SYSTEM, [
            'action' => 'scenario_ended',
            'timestamp' => time()
        ]);
    }

    /**
     * Get user multiplayer status
     */
    public function getUserStatus(int $userId): array {
        $stmt = $this->db->prepare("
            SELECT session_id, role 
            FROM multiplayer_players 
            WHERE user_id = ? AND left_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $currentSession = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'in_session' => $currentSession ? true : false,
            'session_id' => $currentSession['session_id'] ?? null,
            'role' => $currentSession['role'] ?? null
        ];
    }

    /**
     * Generate unique join code
     */
    private function generateJoinCode(): string {
        do {
            $code = strtoupper(substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 6));
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM multiplayer_sessions WHERE join_code = ?");
            $stmt->execute([$code]);
            $exists = $stmt->fetchColumn() > 0;
        } while ($exists);

        return $code;
    }

    /**
     * Add player to session
     */
    private function addPlayerToSession(int $sessionId, int $userId, string $role): void {
        $stmt = $this->db->prepare("
            INSERT INTO multiplayer_players 
            (session_id, user_id, role, joined_at)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE left_at = NULL, role = VALUES(role)
        ");
        $stmt->execute([$sessionId, $userId, $role]);
    }

    /**
     * Check if player is in session
     */
    private function isPlayerInSession(int $sessionId, int $userId): bool {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM multiplayer_players 
            WHERE session_id = ? AND user_id = ? AND left_at IS NULL
        ");
        $stmt->execute([$sessionId, $userId]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Check if user is session host
     */
    private function isSessionHost(int $sessionId, int $userId): bool {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM multiplayer_players 
            WHERE session_id = ? AND user_id = ? AND role = 'host' AND left_at IS NULL
        ");
        $stmt->execute([$sessionId, $userId]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Get session player count
     */
    private function getSessionPlayerCount(int $sessionId): int {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM multiplayer_players 
            WHERE session_id = ? AND left_at IS NULL
        ");
        $stmt->execute([$sessionId]);
        return $stmt->fetchColumn();
    }

    /**
     * Get active session players
     */
    private function getActiveSessionPlayers(int $sessionId): array {
        $stmt = $this->db->prepare("
            SELECT user_id FROM multiplayer_players 
            WHERE session_id = ? AND left_at IS NULL
        ");
        $stmt->execute([$sessionId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * End session
     */
    private function endSession(int $sessionId): void {
        $stmt = $this->db->prepare("
            UPDATE multiplayer_sessions 
            SET status = ?, ended_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([self::SESSION_STATUS_FINISHED, $sessionId]);
    }
}