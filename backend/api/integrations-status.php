<?php
/**
 * Integrations Status API Endpoint
 * 
 * Returns health status, metrics and circuit breaker status for all integrations
 * 
 * @package TPT Flight Control
 * @version 1.0.0
 */

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../src/Logger.php';
require_once __DIR__ . '/../services/IntegrationManager.php';

use TPT\FlightControl\Config\Database;
use TPT\FlightControl\Logger;

header('Content-Type: application/json');

try {
    $db = Database::getConnection();
    $logger = new Logger();
    
    $integrationManager = new IntegrationManager($db, $logger);
    
    $status = $integrationManager->getSystemHealth();
    
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'data' => $status,
        'timestamp' => time()
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'error' => 'An internal error occurred'
    ]);
}