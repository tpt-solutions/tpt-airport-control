<?php
/**
 * Flight Control WebSocket Server
 *
 * This script starts the WebSocket server for real-time flight updates.
 * Run with: php websocket-server.php
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/src/WebSocketServer.php';

echo "Starting Flight Control WebSocket Server...\n";
echo "Press Ctrl+C to stop\n\n";

try {
    startWebSocketServer($pdo);
} catch (Exception $e) {
    echo "Failed to start WebSocket server: " . $e->getMessage() . "\n";
    exit(1);
}
?>
