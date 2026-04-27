<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Middleware.php';

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $request = $_SERVER['REQUEST_URI'];

    // Remove query string and decode
    $path = parse_url($request, PHP_URL_PATH);
    $path = str_replace('/api/communications', '', $path);

    // Get communication ID from path if present
    $communicationId = null;
    if (!empty($path) && $path !== '/') {
        $communicationId = trim($path, '/');
    }

    switch ($method) {
        case 'GET':
            if ($communicationId) {
                getCommunication($pdo, $communicationId);
            } else {
                getCommunications($pdo);
            }
            break;

        case 'POST':
            if (isset($_GET['action'])) {
                $action = $_GET['action'];
                switch ($action) {
                    case 'voice':
                        logVoiceCommunication($pdo);
                        break;
                    case 'text':
                        logTextCommunication($pdo);
                        break;
                    case 'emergency':
                        logEmergencyCommunication($pdo);
                        break;
                    default:
                        http_response_code(400);
                        echo json_encode(['error' => 'Invalid action']);
                        break;
                }
            } else {
                createCommunication($pdo);
            }
            break;

        case 'PUT':
            if ($communicationId) {
                updateCommunication($pdo, $communicationId);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Communication ID required for update']);
            }
            break;

        case 'DELETE':
            if ($communicationId) {
                deleteCommunication($pdo, $communicationId);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Communication ID required for deletion']);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error', 'message' => $e->getMessage()]);
}

function getCommunications($pdo) {
    // Check permissions - operations staff can view communications
    if (!Auth::hasPermission('read', 'flights')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    $type = $_GET['type'] ?? null; // voice, text, emergency
    $flightId = $_GET['flight_id'] ?? null;
    $frequency = $_GET['frequency'] ?? null;
    $limit = (int)($_GET['limit'] ?? 100);

    $where = [];
    $params = [];

    if ($type) {
        $where[] = "c.communication_type = ?";
        $params[] = $type;
    }

    if ($flightId) {
        $where[] = "c.flight_id = ?";
        $params[] = $flightId;
    }

    if ($frequency) {
        $where[] = "c.frequency_mhz = ?";
        $params[] = $frequency;
    }

    $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

    // Get communications with flight and user details
    $stmt = $pdo->prepare("
        SELECT
            c.*,
            f.flight_number,
            f.origin,
            f.destination,
            u.username as controller_username,
            u.first_name as controller_first_name,
            u.last_name as controller_last_name
        FROM communications c
        LEFT JOIN flights f ON c.flight_id = f.id
        LEFT JOIN users u ON c.controller_id = u.id
        {$whereClause}
        ORDER BY c.timestamp DESC
        LIMIT ?
    ");
    $params[] = $limit;
    $stmt->execute($params);
    $communications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['communications' => $communications]);
}

function getCommunication($pdo, $communicationId) {
    // Check permissions
    if (!Auth::hasPermission('read', 'flights')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    $stmt = $pdo->prepare("
        SELECT
            c.*,
            f.flight_number,
            f.origin,
            f.destination,
            u.username as controller_username,
            u.first_name as controller_first_name,
            u.last_name as controller_last_name
        FROM communications c
        LEFT JOIN flights f ON c.flight_id = f.id
        LEFT JOIN users u ON c.controller_id = u.id
        WHERE c.id = ?
    ");
    $stmt->execute([$communicationId]);
    $communication = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$communication) {
        http_response_code(404);
        echo json_encode(['error' => 'Communication not found']);
        return;
    }

    echo json_encode(['communication' => $communication]);
}

function createCommunication($pdo) {
    // Check permissions - operations staff can create communications
    if (!Auth::hasPermission('write', 'flights')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON data']);
        return;
    }

    // Validate required fields
    $required = ['communication_type', 'message'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Missing required field: $field"]);
            return;
        }
    }

    // Get current user
    $currentUser = Auth::getCurrentUser();

    // Insert communication
    $stmt = $pdo->prepare("
        INSERT INTO communications (
            flight_id, controller_id, communication_type, frequency_mhz,
            message, response, priority, status, timestamp
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $data['flight_id'] ?? null,
        $currentUser ? $currentUser['id'] : null,
        $data['communication_type'],
        $data['frequency_mhz'] ?? null,
        $data['message'],
        $data['response'] ?? null,
        $data['priority'] ?? 'normal',
        $data['status'] ?? 'sent',
        $data['timestamp'] ?? date('Y-m-d H:i:s')
    ]);

    $communicationId = $pdo->lastInsertId();

    // Log communication creation
    require_once __DIR__ . '/../src/Logger.php';
    Logger::info('Communication logged: ' . $data['communication_type'] . ' - ' . substr($data['message'], 0, 50) . '... (ID: ' . $communicationId . ')');

    http_response_code(201);
    echo json_encode([
        'message' => 'Communication logged successfully',
        'communication_id' => $communicationId
    ]);
}

function updateCommunication($pdo, $communicationId) {
    // Check permissions
    if (!Auth::hasPermission('write', 'flights')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON data']);
        return;
    }

    // Check if communication exists
    $stmt = $pdo->prepare("SELECT id FROM communications WHERE id = ?");
    $stmt->execute([$communicationId]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Communication not found']);
        return;
    }

    // Build update query
    $updates = [];
    $params = [];

    $allowedFields = [
        'response', 'status', 'priority'
    ];

    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $updates[] = "$field = ?";
            $params[] = $data[$field];
        }
    }

    if (empty($updates)) {
        http_response_code(400);
        echo json_encode(['error' => 'No valid fields to update']);
        return;
    }

    $params[] = $communicationId;
    $stmt = $pdo->prepare("UPDATE communications SET " . implode(', ', $updates) . ", updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute($params);

    Logger::info('Communication updated: ID ' . $communicationId);

    echo json_encode(['message' => 'Communication updated successfully']);
}

function deleteCommunication($pdo, $communicationId) {
    // Check permissions - admin only
    if (!Auth::hasPermission('admin', 'flights')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    // Check if communication exists
    $stmt = $pdo->prepare("SELECT id FROM communications WHERE id = ?");
    $stmt->execute([$communicationId]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Communication not found']);
        return;
    }

    $stmt = $pdo->prepare("DELETE FROM communications WHERE id = ?");
    $stmt->execute([$communicationId]);

    Logger::info('Communication deleted: ID ' . $communicationId);

    echo json_encode(['message' => 'Communication deleted successfully']);
}

function logVoiceCommunication($pdo) {
    // Check permissions
    if (!Auth::hasPermission('write', 'flights')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON data']);
        return;
    }

    // Validate required fields
    $required = ['flight_id', 'frequency_mhz', 'message'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Missing required field: $field"]);
            return;
        }
    }

    // Get current user
    $currentUser = Auth::getCurrentUser();

    // Insert voice communication
    $stmt = $pdo->prepare("
        INSERT INTO communications (
            flight_id, controller_id, communication_type, frequency_mhz,
            message, response, priority, status, timestamp, duration_seconds
        ) VALUES (?, ?, 'voice', ?, ?, ?, ?, 'completed', ?, ?)
    ");
    $stmt->execute([
        $data['flight_id'],
        $currentUser ? $currentUser['id'] : null,
        $data['frequency_mhz'],
        $data['message'],
        $data['response'] ?? null,
        $data['priority'] ?? 'normal',
        $data['timestamp'] ?? date('Y-m-d H:i:s'),
        $data['duration_seconds'] ?? null
    ]);

    $communicationId = $pdo->lastInsertId();

    Logger::info('Voice communication logged: Flight ' . $data['flight_id'] . ' on ' . $data['frequency_mhz'] . ' MHz (ID: ' . $communicationId . ')');

    echo json_encode([
        'message' => 'Voice communication logged successfully',
        'communication_id' => $communicationId
    ]);
}

function logTextCommunication($pdo) {
    // Check permissions
    if (!Auth::hasPermission('write', 'flights')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON data']);
        return;
    }

    // Validate required fields
    $required = ['flight_id', 'message'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Missing required field: $field"]);
            return;
        }
    }

    // Get current user
    $currentUser = Auth::getCurrentUser();

    // Insert text communication
    $stmt = $pdo->prepare("
        INSERT INTO communications (
            flight_id, controller_id, communication_type,
            message, response, priority, status, timestamp
        ) VALUES (?, ?, 'text', ?, ?, ?, 'completed', ?)
    ");
    $stmt->execute([
        $data['flight_id'],
        $currentUser ? $currentUser['id'] : null,
        $data['message'],
        $data['response'] ?? null,
        $data['priority'] ?? 'normal',
        $data['timestamp'] ?? date('Y-m-d H:i:s')
    ]);

    $communicationId = $pdo->lastInsertId();

    Logger::info('Text communication logged: Flight ' . $data['flight_id'] . ' (ID: ' . $communicationId . ')');

    echo json_encode([
        'message' => 'Text communication logged successfully',
        'communication_id' => $communicationId
    ]);
}

function logEmergencyCommunication($pdo) {
    // Emergency communications can be logged by any authenticated user
    if (!Auth::hasPermission('read', 'flights')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON data']);
        return;
    }

    // Validate required fields
    $required = ['flight_id', 'message'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Missing required field: $field"]);
            return;
        }
    }

    // Get current user
    $currentUser = Auth::getCurrentUser();

    // Insert emergency communication
    $stmt = $pdo->prepare("
        INSERT INTO communications (
            flight_id, controller_id, communication_type, frequency_mhz,
            message, response, priority, status, timestamp, is_emergency
        ) VALUES (?, ?, 'emergency', ?, ?, ?, 'critical', 'completed', ?, true)
    ");
    $stmt->execute([
        $data['flight_id'],
        $currentUser ? $currentUser['id'] : null,
        $data['frequency_mhz'] ?? 121.5, // Emergency frequency
        $data['message'],
        $data['response'] ?? null,
        $data['timestamp'] ?? date('Y-m-d H:i:s')
    ]);

    $communicationId = $pdo->lastInsertId();

    Logger::info('EMERGENCY communication logged: Flight ' . $data['flight_id'] . ' - ' . substr($data['message'], 0, 50) . '... (ID: ' . $communicationId . ')');

    // Trigger emergency alert
    triggerEmergencyAlert($pdo, $data['flight_id'], $data['message']);

    echo json_encode([
        'message' => 'Emergency communication logged successfully',
        'communication_id' => $communicationId,
        'emergency_alert_triggered' => true
    ]);
}

// Helper function to trigger emergency alert
function triggerEmergencyAlert($pdo, $flightId, $message) {
    // Get flight details
    $stmt = $pdo->prepare("SELECT flight_number FROM flights WHERE id = ?");
    $stmt->execute([$flightId]);
    $flight = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($flight) {
        // Log emergency alert
        Logger::critical('EMERGENCY ALERT: Flight ' . $flight['flight_number'] . ' - ' . $message);

        // Here you could integrate with emergency notification systems
        // SMS alerts, siren activation, emergency response coordination, etc.
    }
}

// Helper function to get communication statistics
function getCommunicationStatistics($pdo, $dateFrom = null, $dateTo = null) {
    $whereClause = "";
    $params = [];

    if ($dateFrom && $dateTo) {
        $whereClause = "WHERE c.timestamp BETWEEN ? AND ?";
        $params = [$dateFrom, $dateTo];
    }

    $stmt = $pdo->prepare("
        SELECT
            c.communication_type,
            c.status,
            COUNT(*) as count
        FROM communications c
        {$whereClause}
        GROUP BY c.communication_type, c.status
        ORDER BY c.communication_type, c.status
    ");
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Helper function to get recent communications for a flight
function getFlightCommunications($pdo, $flightId, $limit = 20) {
    $stmt = $pdo->prepare("
        SELECT
            c.*,
            u.username as controller_username
        FROM communications c
        LEFT JOIN users u ON c.controller_id = u.id
        WHERE c.flight_id = ?
        ORDER BY c.timestamp DESC
        LIMIT ?
    ");
    $stmt->execute([$flightId, $limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Helper function to get communications by frequency
function getCommunicationsByFrequency($pdo, $frequency, $limit = 50) {
    $stmt = $pdo->prepare("
        SELECT
            c.*,
            f.flight_number,
            u.username as controller_username
        FROM communications c
        LEFT JOIN flights f ON c.flight_id = f.id
        LEFT JOIN users u ON c.controller_id = u.id
        WHERE c.frequency_mhz = ?
        ORDER BY c.timestamp DESC
        LIMIT ?
    ");
    $stmt->execute([$frequency, $limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Helper function to get emergency communications
function getEmergencyCommunications($pdo, $limit = 100) {
    $stmt = $pdo->prepare("
        SELECT
            c.*,
            f.flight_number,
            f.origin,
            f.destination,
            u.username as controller_username
        FROM communications c
        JOIN flights f ON c.flight_id = f.id
        LEFT JOIN users u ON c.controller_id = u.id
        WHERE c.is_emergency = true
        ORDER BY c.timestamp DESC
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
