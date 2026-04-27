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
    $path = str_replace('/api/clearances', '', $path);

    // Get clearance ID from path if present
    $clearanceId = null;
    if (!empty($path) && $path !== '/') {
        $clearanceId = trim($path, '/');
    }

    switch ($method) {
        case 'GET':
            if ($clearanceId) {
                getClearance($pdo, $clearanceId);
            } else {
                getClearances($pdo);
            }
            break;

        case 'POST':
            if (isset($_GET['action'])) {
                $action = $_GET['action'];
                switch ($action) {
                    case 'grant':
                        grantClearance($pdo);
                        break;
                    case 'revoke':
                        revokeClearance($pdo);
                        break;
                    case 'hold':
                        holdClearance($pdo);
                        break;
                    default:
                        http_response_code(400);
                        echo json_encode(['error' => 'Invalid action']);
                        break;
                }
            } else {
                createClearance($pdo);
            }
            break;

        case 'PUT':
            if ($clearanceId) {
                updateClearance($pdo, $clearanceId);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Clearance ID required for update']);
            }
            break;

        case 'DELETE':
            if ($clearanceId) {
                deleteClearance($pdo, $clearanceId);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Clearance ID required for deletion']);
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

function getClearances($pdo) {
    // Check permissions - operations staff can view clearances
    if (!Auth::hasPermission('read', 'flights')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    $status = $_GET['status'] ?? null; // active, granted, revoked, expired
    $type = $_GET['type'] ?? null; // takeoff, landing
    $flightId = $_GET['flight_id'] ?? null;

    $where = [];
    $params = [];

    if ($status) {
        $where[] = "c.status = ?";
        $params[] = $status;
    }

    if ($type) {
        $where[] = "c.clearance_type = ?";
        $params[] = $type;
    }

    if ($flightId) {
        $where[] = "c.flight_id = ?";
        $params[] = $flightId;
    }

    $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

    // Get clearances with flight and runway details
    $stmt = $pdo->prepare("
        SELECT
            c.*,
            f.flight_number,
            f.origin,
            f.destination,
            f.scheduled_departure,
            f.scheduled_arrival,
            r.runway_number,
            u.username as granted_by_username,
            u.first_name as granted_by_first_name,
            u.last_name as granted_by_last_name
        FROM clearances c
        JOIN flights f ON c.flight_id = f.id
        LEFT JOIN runways r ON c.runway_id = r.id
        LEFT JOIN users u ON c.granted_by = u.id
        {$whereClause}
        ORDER BY c.created_at DESC
        LIMIT 100
    ");
    $stmt->execute($params);
    $clearances = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['clearances' => $clearances]);
}

function getClearance($pdo, $clearanceId) {
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
            f.scheduled_departure,
            f.scheduled_arrival,
            r.runway_number,
            u.username as granted_by_username,
            u.first_name as granted_by_first_name,
            u.last_name as granted_by_last_name
        FROM clearances c
        JOIN flights f ON c.flight_id = f.id
        LEFT JOIN runways r ON c.runway_id = r.id
        LEFT JOIN users u ON c.granted_by = u.id
        WHERE c.id = ?
    ");
    $stmt->execute([$clearanceId]);
    $clearance = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$clearance) {
        http_response_code(404);
        echo json_encode(['error' => 'Clearance not found']);
        return;
    }

    echo json_encode(['clearance' => $clearance]);
}

function createClearance($pdo) {
    // Check permissions - operations staff can create clearances
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
    $required = ['flight_id', 'clearance_type'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Missing required field: $field"]);
            return;
        }
    }

    // Check if flight exists
    $stmt = $pdo->prepare("SELECT id FROM flights WHERE id = ?");
    $stmt->execute([$data['flight_id']]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Flight not found']);
        return;
    }

    // Check if runway exists (if provided)
    if (isset($data['runway_id'])) {
        $stmt = $pdo->prepare("SELECT id FROM runways WHERE id = ?");
        $stmt->execute([$data['runway_id']]);
        if (!$stmt->fetch()) {
            http_response_code(404);
            echo json_encode(['error' => 'Runway not found']);
            return;
        }
    }

    // Check for existing active clearance for this flight
    $stmt = $pdo->prepare("SELECT id FROM clearances WHERE flight_id = ? AND status = 'active'");
    $stmt->execute([$data['flight_id']]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['error' => 'Flight already has an active clearance']);
        return;
    }

    // Get current user
    $currentUser = Auth::getCurrentUser();

    // Insert clearance
    $stmt = $pdo->prepare("
        INSERT INTO clearances (
            flight_id, clearance_type, runway_id, instructions,
            altitude_restrictions, speed_restrictions, heading_instructions,
            weather_conditions, valid_until, status, granted_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $data['flight_id'],
        $data['clearance_type'],
        $data['runway_id'] ?? null,
        $data['instructions'] ?? null,
        $data['altitude_restrictions'] ?? null,
        $data['speed_restrictions'] ?? null,
        $data['heading_instructions'] ?? null,
        $data['weather_conditions'] ?? null,
        $data['valid_until'] ?? null,
        $data['status'] ?? 'pending',
        $currentUser ? $currentUser['id'] : null
    ]);

    $clearanceId = $pdo->lastInsertId();

    // Log clearance creation
    require_once __DIR__ . '/../src/Logger.php';
    Logger::info('Clearance created: ID ' . $clearanceId . ' for flight ' . $data['flight_id'] . ' (' . $data['clearance_type'] . ')');

    http_response_code(201);
    echo json_encode([
        'message' => 'Clearance created successfully',
        'clearance_id' => $clearanceId
    ]);
}

function updateClearance($pdo, $clearanceId) {
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

    // Check if clearance exists
    $stmt = $pdo->prepare("SELECT id FROM clearances WHERE id = ?");
    $stmt->execute([$clearanceId]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Clearance not found']);
        return;
    }

    // Build update query
    $updates = [];
    $params = [];

    $allowedFields = [
        'clearance_type', 'runway_id', 'instructions',
        'altitude_restrictions', 'speed_restrictions', 'heading_instructions',
        'weather_conditions', 'valid_until', 'status'
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

    $params[] = $clearanceId;
    $stmt = $pdo->prepare("UPDATE clearances SET " . implode(', ', $updates) . ", updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute($params);

    Logger::info('Clearance updated: ID ' . $clearanceId);

    echo json_encode(['message' => 'Clearance updated successfully']);
}

function deleteClearance($pdo, $clearanceId) {
    // Check permissions - admin only
    if (!Auth::hasPermission('admin', 'flights')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    // Check if clearance is active
    $stmt = $pdo->prepare("SELECT status FROM clearances WHERE id = ?");
    $stmt->execute([$clearanceId]);
    $clearance = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$clearance) {
        http_response_code(404);
        echo json_encode(['error' => 'Clearance not found']);
        return;
    }

    if ($clearance['status'] === 'active') {
        http_response_code(409);
        echo json_encode(['error' => 'Cannot delete active clearance']);
        return;
    }

    $stmt = $pdo->prepare("DELETE FROM clearances WHERE id = ?");
    $stmt->execute([$clearanceId]);

    Logger::info('Clearance deleted: ID ' . $clearanceId);

    echo json_encode(['message' => 'Clearance deleted successfully']);
}

function grantClearance($pdo) {
    // Check permissions
    if (!Auth::hasPermission('write', 'flights')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || !isset($data['clearance_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Clearance ID required']);
        return;
    }

    $clearanceId = $data['clearance_id'];
    $validUntil = $data['valid_until'] ?? null;

    // Check if clearance exists
    $stmt = $pdo->prepare("SELECT id, status FROM clearances WHERE id = ?");
    $stmt->execute([$clearanceId]);
    $clearance = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$clearance) {
        http_response_code(404);
        echo json_encode(['error' => 'Clearance not found']);
        return;
    }

    if ($clearance['status'] === 'active') {
        http_response_code(409);
        echo json_encode(['error' => 'Clearance is already active']);
        return;
    }

    // Get current user
    $currentUser = Auth::getCurrentUser();

    // Update clearance status
    $stmt = $pdo->prepare("
        UPDATE clearances
        SET status = 'active', granted_at = CURRENT_TIMESTAMP, granted_by = ?, valid_until = ?
        WHERE id = ?
    ");
    $stmt->execute([$currentUser ? $currentUser['id'] : null, $validUntil, $clearanceId]);

    Logger::info('Clearance granted: ID ' . $clearanceId . ' by user ' . ($currentUser ? $currentUser['id'] : 'system'));

    echo json_encode(['message' => 'Clearance granted successfully']);
}

function revokeClearance($pdo) {
    // Check permissions
    if (!Auth::hasPermission('write', 'flights')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || !isset($data['clearance_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Clearance ID required']);
        return;
    }

    $clearanceId = $data['clearance_id'];
    $reason = $data['reason'] ?? null;

    // Check if clearance exists and is active
    $stmt = $pdo->prepare("SELECT id, status FROM clearances WHERE id = ?");
    $stmt->execute([$clearanceId]);
    $clearance = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$clearance) {
        http_response_code(404);
        echo json_encode(['error' => 'Clearance not found']);
        return;
    }

    if ($clearance['status'] !== 'active') {
        http_response_code(409);
        echo json_encode(['error' => 'Clearance is not active']);
        return;
    }

    // Update clearance status
    $stmt = $pdo->prepare("
        UPDATE clearances
        SET status = 'revoked', revoked_at = CURRENT_TIMESTAMP, revocation_reason = ?
        WHERE id = ?
    ");
    $stmt->execute([$reason, $clearanceId]);

    Logger::info('Clearance revoked: ID ' . $clearanceId . ' - Reason: ' . ($reason ?? 'Not specified'));

    echo json_encode(['message' => 'Clearance revoked successfully']);
}

function holdClearance($pdo) {
    // Check permissions
    if (!Auth::hasPermission('write', 'flights')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || !isset($data['clearance_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Clearance ID required']);
        return;
    }

    $clearanceId = $data['clearance_id'];
    $reason = $data['reason'] ?? null;
    $expectedRelease = $data['expected_release'] ?? null;

    // Check if clearance exists and is active
    $stmt = $pdo->prepare("SELECT id, status FROM clearances WHERE id = ?");
    $stmt->execute([$clearanceId]);
    $clearance = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$clearance) {
        http_response_code(404);
        echo json_encode(['error' => 'Clearance not found']);
        return;
    }

    if ($clearance['status'] !== 'active') {
        http_response_code(409);
        echo json_encode(['error' => 'Clearance is not active']);
        return;
    }

    // Update clearance status
    $stmt = $pdo->prepare("
        UPDATE clearances
        SET status = 'hold', hold_reason = ?, expected_release = ?, hold_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $stmt->execute([$reason, $expectedRelease, $clearanceId]);

    Logger::info('Clearance put on hold: ID ' . $clearanceId . ' - Reason: ' . ($reason ?? 'Not specified'));

    echo json_encode(['message' => 'Clearance put on hold successfully']);
}

// Helper function to get active clearances
function getActiveClearances($pdo) {
    $stmt = $pdo->prepare("
        SELECT
            c.*,
            f.flight_number,
            f.origin,
            f.destination,
            r.runway_number
        FROM clearances c
        JOIN flights f ON c.flight_id = f.id
        LEFT JOIN runways r ON c.runway_id = r.id
        WHERE c.status = 'active'
        AND (c.valid_until IS NULL OR c.valid_until > CURRENT_TIMESTAMP)
        ORDER BY c.created_at ASC
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Helper function to check clearance conflicts
function checkClearanceConflicts($pdo, $flightId, $runwayId, $clearanceType) {
    // Check for conflicting clearances on the same runway
    $stmt = $pdo->prepare("
        SELECT c.id, f.flight_number
        FROM clearances c
        JOIN flights f ON c.flight_id = f.id
        WHERE c.runway_id = ?
        AND c.status = 'active'
        AND c.clearance_type = ?
        AND c.flight_id != ?
        AND (c.valid_until IS NULL OR c.valid_until > CURRENT_TIMESTAMP)
    ");
    $stmt->execute([$runwayId, $clearanceType, $flightId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Helper function to get clearance statistics
function getClearanceStatistics($pdo, $dateFrom = null, $dateTo = null) {
    $whereClause = "";
    $params = [];

    if ($dateFrom && $dateTo) {
        $whereClause = "WHERE c.created_at BETWEEN ? AND ?";
        $params = [$dateFrom, $dateTo];
    }

    $stmt = $pdo->prepare("
        SELECT
            c.clearance_type,
            c.status,
            COUNT(*) as count
        FROM clearances c
        {$whereClause}
        GROUP BY c.clearance_type, c.status
        ORDER BY c.clearance_type, c.status
    ");
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
