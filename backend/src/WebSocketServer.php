<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use TPT\FlightControl\Logger;

require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/ConnectionManager.php';
require_once __DIR__ . '/WebSocketMessageHandler.php';
require_once __DIR__ . '/FlightBroadcastService.php';

class FlightWebSocketServer implements MessageComponentInterface {
    private $connectionManager;
    private $messageHandler;
    private $flightBroadcastService;
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;

        // Initialize modular components
        $this->connectionManager = new ConnectionManager($pdo);
        $this->flightBroadcastService = new FlightBroadcastService($pdo);
        $this->messageHandler = new WebSocketMessageHandler($pdo, $this->flightBroadcastService);

        Logger::info('Flight WebSocket Server initialized with modular components');
    }

    public function onOpen(ConnectionInterface $conn) {
        // Use connection manager to handle new connections
        if ($this->connectionManager->addConnection($conn)) {
            // Send welcome message
            $conn->send(json_encode([
                'type' => 'welcome',
                'message' => 'Connected to Flight Control WebSocket',
                'timestamp' => time()
            ]));
        }
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        // Update activity timestamp
        $this->connectionManager->updateActivity($from);

        // Delegate message handling to the message handler
        $this->messageHandler->handleMessage($from, $msg);
    }

    public function onClose(ConnectionInterface $conn) {
        // Use connection manager to handle disconnections
        $this->connectionManager->removeConnection($conn);
        $this->flightBroadcastService->removeClient($conn);
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        Logger::error('WebSocket error: ' . $e->getMessage());
        $this->connectionManager->removeConnection($conn);
        $this->flightBroadcastService->removeClient($conn);
        $conn->close();
    }

    // Public interface methods for broadcasting
    public function broadcastFlightUpdate($flightId, $updateData) {
        return $this->flightBroadcastService->broadcastFlightUpdate($flightId, $updateData);
    }

    public function broadcastFlightStatusChange($flightId, $oldStatus, $newStatus, $additionalData = []) {
        return $this->flightBroadcastService->broadcastFlightStatusChange($flightId, $oldStatus, $newStatus, $additionalData);
    }

    public function broadcastFlightDelay($flightId, $delayMinutes, $reason = null) {
        return $this->flightBroadcastService->broadcastFlightDelay($flightId, $delayMinutes, $reason);
    }

    public function broadcastFlightCancellation($flightId, $reason = null) {
        return $this->flightBroadcastService->broadcastFlightCancellation($flightId, $reason);
    }

    public function broadcastGateChange($flightId, $newGate, $oldGate = null) {
        return $this->flightBroadcastService->broadcastGateChange($flightId, $newGate, $oldGate);
    }

    public function broadcastDepartureUpdate($flightId, $actualDepartureTime) {
        return $this->flightBroadcastService->broadcastDepartureUpdate($flightId, $actualDepartureTime);
    }

    public function broadcastArrivalUpdate($flightId, $actualArrivalTime) {
        return $this->flightBroadcastService->broadcastArrivalUpdate($flightId, $actualArrivalTime);
    }

    public function broadcastAnnouncement($message, $level = 'info', $targetFilters = null) {
        return $this->flightBroadcastService->broadcastAnnouncement($message, $level, $targetFilters);
    }

    public function broadcastWeatherAlert($airportCode, $alertType, $message, $severity = 'moderate') {
        return $this->flightBroadcastService->broadcastWeatherAlert($airportCode, $alertType, $message, $severity);
    }

    public function broadcastSystemStatus($status, $message = null) {
        return $this->flightBroadcastService->broadcastSystemStatus($status, $message);
    }

    public function notifyFlightCrew($flightId, $message, $crewType = null) {
        return $this->flightBroadcastService->notifyFlightCrew($flightId, $message, $crewType);
    }

    // Administrative methods
    public function getConnectionStats() {
        return $this->connectionManager->getConnectionStats();
    }

    public function getSubscriptionStats() {
        return $this->flightBroadcastService->getSubscriptionStats();
    }

    public function cleanupInactiveConnections() {
        return $this->connectionManager->cleanupInactiveConnections();
    }

    public function getHealthStatus() {
        $connectionStats = $this->connectionManager->getConnectionStats();
        $subscriptionStats = $this->flightBroadcastService->getSubscriptionStats();

        return [
            'status' => 'healthy',
            'timestamp' => time(),
            'connections' => $connectionStats,
            'subscriptions' => $subscriptionStats,
            'uptime' => time() - $_SERVER['REQUEST_TIME'] ?? time()
        ];
    }

    // Authentication methods
    public function authenticateConnection(ConnectionInterface $conn, $userData) {
        $this->connectionManager->authenticateConnection($conn, $userData);
        $this->flightBroadcastService->addClient($conn);
    }

    public function deauthenticateConnection(ConnectionInterface $conn) {
        $this->connectionManager->deauthenticateConnection($conn);
    }

    // Utility methods
    public function sendToUser($userId, $message) {
        return $this->connectionManager->sendToUser($userId, $message);
    }

    public function broadcastToAuthenticated($message) {
        return $this->connectionManager->broadcastToAuthenticated($message);
    }

    public function broadcastToRole($message, $role) {
        return $this->connectionManager->broadcastToRole($message, $role);
    }
}

// Function to check if a port is available
function isPortAvailable($port, $host = '0.0.0.0') {
    $socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    if ($socket === false) {
        return false;
    }
    
    socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
    $result = @socket_bind($socket, $host, $port);
    socket_close($socket);
    
    return $result !== false;
}

// Function to find an available port starting from base port
function findAvailablePort($startPort, $maxAttempts = 10) {
    for ($port = $startPort; $port < $startPort + $maxAttempts; $port++) {
        if (isPortAvailable($port)) {
            return $port;
        }
        Logger::warning("Port $port is busy, trying next port...");
    }
    return null;
}

// Function to start the WebSocket server
function startWebSocketServer($pdo) {
    $basePort = 8080;
    $port = findAvailablePort($basePort);
    
    if ($port === null) {
        throw new Exception("No available ports found. Tried ports $basePort to " . ($basePort + 9));
    }
    
    $server = IoServer::factory(
        new HttpServer(
            new WsServer(
                new FlightWebSocketServer($pdo)
            )
        ),
        $port,
        '0.0.0.0' // Listen on all interfaces
    );

    Logger::info("Flight Control WebSocket Server starting on port $port");
    echo "✅ WebSocket server running on ws://0.0.0.0:$port\n";
    
    if ($port != $basePort) {
        echo "ℹ️  Note: Using alternate port (default port $basePort was occupied)\n";
    }
    
    $server->run();
}

// Function to get the WebSocket server instance for broadcasting
function getWebSocketServer() {
    static $server = null;
    return $server;
}

// Function to broadcast flight updates from API endpoints
function broadcastFlightUpdate($flightId, $updateData) {
    $server = getWebSocketServer();
    if ($server) {
        $server->broadcastFlightUpdate($flightId, $updateData);
    }
}

// Additional helper functions for external use
function broadcastFlightStatusChange($flightId, $oldStatus, $newStatus, $additionalData = []) {
    $server = getWebSocketServer();
    if ($server) {
        return $server->broadcastFlightStatusChange($flightId, $oldStatus, $newStatus, $additionalData);
    }
    return 0;
}

function broadcastFlightDelay($flightId, $delayMinutes, $reason = null) {
    $server = getWebSocketServer();
    if ($server) {
        return $server->broadcastFlightDelay($flightId, $delayMinutes, $reason);
    }
    return 0;
}

function broadcastFlightCancellation($flightId, $reason = null) {
    $server = getWebSocketServer();
    if ($server) {
        return $server->broadcastFlightCancellation($flightId, $reason);
    }
    return 0;
}

function broadcastGateChange($flightId, $newGate, $oldGate = null) {
    $server = getWebSocketServer();
    if ($server) {
        return $server->broadcastGateChange($flightId, $newGate, $oldGate);
    }
    return 0;
}

function broadcastAnnouncement($message, $level = 'info', $targetFilters = null) {
    $server = getWebSocketServer();
    if ($server) {
        return $server->broadcastAnnouncement($message, $level, $targetFilters);
    }
    return 0;
}

function broadcastWeatherAlert($airportCode, $alertType, $message, $severity = 'moderate') {
    $server = getWebSocketServer();
    if ($server) {
        return $server->broadcastWeatherAlert($airportCode, $alertType, $message, $severity);
    }
    return 0;
}
?>
