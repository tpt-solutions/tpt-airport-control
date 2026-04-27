<?php
require_once __DIR__ . '/Logger.php';

use Ratchet\ConnectionInterface;
use TPT\FlightControl\Logger;

class ConnectionManager {
    protected $clients;
    private $maxConnections;
    private $connectionTimeout;
    private $pingInterval;
    private $pdo;

    public function __construct($pdo, $maxConnections = 1000, $connectionTimeout = 3600, $pingInterval = 30) {
        $this->clients = new \SplObjectStorage;
        $this->pdo = $pdo;
        $this->maxConnections = $maxConnections;
        $this->connectionTimeout = $connectionTimeout;
        $this->pingInterval = $pingInterval;
    }

    public function addConnection(ConnectionInterface $conn) {
        // Check connection limit
        if (count($this->clients) >= $this->maxConnections) {
            Logger::warning('Connection limit reached, rejecting new connection');
            $conn->send(json_encode([
                'type' => 'error',
                'message' => 'Server at maximum capacity',
                'timestamp' => time()
            ]));
            $conn->close();
            return false;
        }

        // Initialize connection metadata
        $conn->connectedAt = time();
        $conn->lastActivity = time();
        $conn->clientId = $this->generateClientId();
        $conn->userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Unknown';
        $conn->ipAddress = $this->getClientIp($conn);

        $this->clients->offsetSet($conn);

        Logger::info("New connection added: {$conn->clientId} from {$conn->ipAddress}");

        // Log connection to database for monitoring
        $this->logConnection($conn);

        return true;
    }

    public function removeConnection(ConnectionInterface $conn) {
        if (isset($conn->clientId)) {
            Logger::info("Connection removed: {$conn->clientId}");

            // Log disconnection
            $this->logDisconnection($conn);

            // Clean up any subscriptions
            $this->cleanupSubscriptions($conn);
        }

        $this->clients->offsetUnset($conn);
    }

    public function updateActivity(ConnectionInterface $conn) {
        $conn->lastActivity = time();
    }

    public function getConnectionStats() {
        $stats = [
            'total_connections' => count($this->clients),
            'max_connections' => $this->maxConnections,
            'connections_by_ip' => [],
            'oldest_connection' => null,
            'newest_connection' => null,
            'inactive_connections' => 0
        ];

        $now = time();
        $oldestTime = $now;
        $newestTime = 0;

        foreach ($this->clients as $client) {
            $connectedAt = isset($client->connectedAt) ? $client->connectedAt : $now;
            $lastActivity = isset($client->lastActivity) ? $client->lastActivity : $connectedAt;
            $ip = isset($client->ipAddress) ? $client->ipAddress : 'unknown';

            // Track connections by IP
            if (!isset($stats['connections_by_ip'][$ip])) {
                $stats['connections_by_ip'][$ip] = 0;
            }
            $stats['connections_by_ip'][$ip]++;

            // Track oldest/newest connections
            if ($connectedAt < $oldestTime) {
                $oldestTime = $connectedAt;
                $stats['oldest_connection'] = [
                    'id' => isset($client->clientId) ? $client->clientId : 'unknown',
                    'connected_at' => $connectedAt,
                    'ip' => $ip
                ];
            }

            if ($connectedAt > $newestTime) {
                $newestTime = $connectedAt;
                $stats['newest_connection'] = [
                    'id' => isset($client->clientId) ? $client->clientId : 'unknown',
                    'connected_at' => $connectedAt,
                    'ip' => $ip
                ];
            }

            // Check for inactive connections
            if (($now - $lastActivity) > ($this->pingInterval * 2)) {
                $stats['inactive_connections']++;
            }
        }

        return $stats;
    }

    public function cleanupInactiveConnections() {
        $now = time();
        $cleaned = 0;

        foreach ($this->clients as $client) {
            $connectedAt = isset($client->connectedAt) ? $client->connectedAt : $now;
            $lastActivity = isset($client->lastActivity) ? $client->lastActivity : $connectedAt;

            // Remove connections that haven't been active for too long
            if (($now - $lastActivity) > $this->connectionTimeout) {
                $clientId = isset($client->clientId) ? $client->clientId : 'unknown';
                Logger::info("Cleaning up inactive connection: {$clientId}");
                $this->removeConnection($client);
                $cleaned++;
            }
        }

        if ($cleaned > 0) {
            Logger::info("Cleaned up {$cleaned} inactive connections");
        }

        return $cleaned;
    }

    public function broadcastToAll($message, $excludeConnection = null) {
        $data = is_string($message) ? $message : json_encode($message);
        $sent = 0;

        foreach ($this->clients as $client) {
            if ($excludeConnection && $client === $excludeConnection) {
                continue;
            }

            try {
                $client->send($data);
                $sent++;
            } catch (Exception $e) {
                $clientId = isset($client->clientId) ? $client->clientId : 'unknown';
                Logger::error("Failed to send message to client {$clientId}: " . $e->getMessage());
            }
        }

        return $sent;
    }

    public function broadcastToAuthenticated($message) {
        $data = is_string($message) ? $message : json_encode($message);
        $sent = 0;

        foreach ($this->clients as $client) {
            if (isset($client->authenticated) && $client->authenticated) {
                try {
                    $client->send($data);
                    $sent++;
                } catch (Exception $e) {
                    $clientId = isset($client->clientId) ? $client->clientId : 'unknown';
                    Logger::error("Failed to send message to authenticated client {$clientId}: " . $e->getMessage());
                }
            }
        }

        return $sent;
    }

    public function broadcastToRole($message, $role) {
        $data = is_string($message) ? $message : json_encode($message);
        $sent = 0;

        foreach ($this->clients as $client) {
            if (isset($client->userRole) && $client->userRole === $role) {
                try {
                    $client->send($data);
                    $sent++;
                } catch (Exception $e) {
                    $clientId = isset($client->clientId) ? $client->clientId : 'unknown';
                    Logger::error("Failed to send message to {$role} client {$clientId}: " . $e->getMessage());
                }
            }
        }

        return $sent;
    }

    public function findConnectionsByUserId($userId) {
        $connections = [];

        foreach ($this->clients as $client) {
            if (isset($client->userId) && $client->userId == $userId) {
                $connections[] = $client;
            }
        }

        return $connections;
    }

    public function sendToUser($userId, $message) {
        $connections = $this->findConnectionsByUserId($userId);
        $data = is_string($message) ? $message : json_encode($message);
        $sent = 0;

        foreach ($connections as $conn) {
            try {
                $conn->send($data);
                $sent++;
            } catch (Exception $e) {
                Logger::error("Failed to send message to user {$userId}: " . $e->getMessage());
            }
        }

        return $sent;
    }

    public function authenticateConnection(ConnectionInterface $conn, $userData) {
        $conn->authenticated = true;
        $conn->userId = $userData['id'];
        $conn->userRole = $userData['role_name'] ?? 'user';
        $conn->username = $userData['username'];

        Logger::info("Connection authenticated: {$conn->clientId} as {$conn->username} ({$conn->userRole})");

        // Update connection in database
        $this->updateConnectionAuth($conn, $userData);
    }

    public function deauthenticateConnection(ConnectionInterface $conn) {
        if (isset($conn->authenticated)) {
            Logger::info("Connection deauthenticated: {$conn->clientId}");
            unset($conn->authenticated);
            unset($conn->userId);
            unset($conn->userRole);
            unset($conn->username);
        }
    }

    public function getConnectionsBySubscription($subscriptionType) {
        $connections = [];

        foreach ($this->clients as $client) {
            $hasSubscription = false;

            switch ($subscriptionType) {
                case 'flights':
                    $hasSubscription = isset($client->flightSubscription);
                    break;
                case 'bookings':
                    $hasSubscription = isset($client->bookingSubscription);
                    break;
                case 'weather':
                    $hasSubscription = isset($client->weatherSubscription);
                    break;
            }

            if ($hasSubscription) {
                $connections[] = $client;
            }
        }

        return $connections;
    }

    private function generateClientId() {
        return uniqid('ws_', true);
    }

    private function getClientIp(ConnectionInterface $conn) {
        // Try to get IP from various headers
        $headers = getallheaders();

        if (isset($headers['X-Forwarded-For'])) {
            return trim(explode(',', $headers['X-Forwarded-For'])[0]);
        }

        if (isset($headers['X-Real-IP'])) {
            return $headers['X-Real-IP'];
        }

        if (isset($_SERVER['REMOTE_ADDR'])) {
            return $_SERVER['REMOTE_ADDR'];
        }

        return 'unknown';
    }

    private function logConnection(ConnectionInterface $conn) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO websocket_connections
                (client_id, ip_address, user_agent, connected_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([
                $conn->clientId,
                $conn->ipAddress,
                $conn->userAgent
            ]);
        } catch (Exception $e) {
            // Log failure but don't fail the connection
            Logger::warning('Failed to log connection to database: ' . $e->getMessage());
        }
    }

    private function logDisconnection(ConnectionInterface $conn) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE websocket_connections
                SET disconnected_at = NOW(),
                    duration_seconds = TIMESTAMPDIFF(SECOND, connected_at, NOW())
                WHERE client_id = ? AND disconnected_at IS NULL
            ");
            $stmt->execute([$conn->clientId]);
        } catch (Exception $e) {
            Logger::warning('Failed to log disconnection to database: ' . $e->getMessage());
        }
    }

    private function updateConnectionAuth(ConnectionInterface $conn, $userData) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE websocket_connections
                SET user_id = ?, authenticated_at = NOW()
                WHERE client_id = ? AND disconnected_at IS NULL
            ");
            $stmt->execute([$userData['id'], $conn->clientId]);
        } catch (Exception $e) {
            Logger::warning('Failed to update connection auth in database: ' . $e->getMessage());
        }
    }

    private function cleanupSubscriptions(ConnectionInterface $conn) {
        // Clean up any subscription data from the connection
        $subscriptionTypes = ['flightSubscription', 'bookingSubscription', 'weatherSubscription'];

        foreach ($subscriptionTypes as $type) {
            if (isset($conn->$type)) {
                unset($conn->$type);
            }
        }
    }

    public function getHealthStatus() {
        $stats = $this->getConnectionStats();

        return [
            'status' => 'healthy',
            'connections' => $stats,
            'server_info' => [
                'max_connections' => $this->maxConnections,
                'connection_timeout' => $this->connectionTimeout,
                'ping_interval' => $this->pingInterval
            ],
            'timestamp' => time()
        ];
    }
}
?>
