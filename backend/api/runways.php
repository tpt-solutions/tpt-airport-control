<?php
require_once __DIR__ . '/cors.php';
/**
 * Runways API Endpoint
 *
 * Refactored to use MVC pattern with RunwayController
 */

require_once __DIR__ . '/../controllers/RunwayController.php';

try {
    $method = $_SERVER['REQUEST_METHOD'];

    // Parse action from query parameters or path
    $action = $_GET['action'] ?? 'list';

    // Get additional parameters
    $params = $_GET;
    $data = [];

    // Handle different HTTP methods
    switch ($method) {
        case 'GET':
            // For detail requests, check if ID is in path or query
            if (isset($_GET['id'])) {
                $params['id'] = $_GET['id'];
                $action = 'detail';
            }
            $controller = new RunwayController();
            $controller->handleGet($action, $params);
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true) ?: [];
            $controller = new RunwayController();
            $controller->handlePost($action, $data);
            break;

        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true) ?: [];
            $controller = new RunwayController();
            $controller->handlePut($action, $data);
            break;

        case 'DELETE':
            $data = json_decode(file_get_contents('php://input'), true) ?: [];
            $controller = new RunwayController();
            $controller->handleDelete($action, $data);
            break;

        case 'OPTIONS':
            // Handle preflight requests
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
            http_response_code(200);
            exit();

        default:
            ApiResponse::error('Method not allowed', 405);
            break;
    }
} catch (Exception $e) {
    // Global exception handler - ApiResponse handles logging
    ApiResponse::handleException($e);
}

function getRunways($pdo) {
    // Check permissions - operations staff can view runways
    if (!Auth::hasPermission('read', 'flights')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    $status = $_GET['status'] ?? null; // active, maintenance, closed
    $type = $_GET['type'] ?? null; // departure, arrival, both

    $where = [];
    $params = [];

    if ($status) {
        $where[] = "r.status = ?";
        $params[] = $status;
    }

    if ($type) {
        $where[] = "r.usage_type = ?";
        $params[] = $type;
    }

    $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

    // Get runways with current assignments
    $stmt = $pdo->prepare("
        SELECT
            r.*,
            COALESCE(ra.flight_id, NULL) as assigned_flight_id,
            COALESCE(f.flight_number, NULL) as assigned_flight_number,
            COALESCE(ra.operation_type, NULL) as operation_type,
            COALESCE(ra.assigned_at, NULL) as assigned_at,
            COALESCE(ra.expected_release, NULL) as expected_release
        FROM runways r
        LEFT JOIN runway_assignments ra ON r.id = ra.runway_id AND ra.status = 'active'
        LEFT JOIN flights f ON ra.flight_id = f.id
        {$whereClause}
        ORDER BY r.runway_number ASC
    ");
    $stmt->execute($params);
    $runways = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['runways' => $runways]);
}

function getRunway($pdo, $runwayId) {
    // Check permissions
    if (!Auth::hasPermission('read', 'flights')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    $stmt = $pdo->prepare("
        SELECT
            r.*,
            COALESCE(ra.flight_id, NULL) as assigned_flight_id,
            COALESCE(f.flight_number, NULL) as assigned_flight_number,
            COALESCE(ra.operation_type, NULL) as operation_type,
            COALESCE(ra.assigned_at, NULL) as assigned_at,
            COALESCE(ra.expected_release, NULL) as expected_release,
            COALESCE(ra.status, NULL) as assignment_status
        FROM runways r
        LEFT JOIN runway_assignments ra ON r.id = ra.runway_id AND ra.status = 'active'
        LEFT JOIN flights f ON ra.flight_id = f.id
        WHERE r.id = ?
    ");
    $stmt->execute([$runwayId]);
    $runway = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$runway) {
        http_response_code(404);
        echo json_encode(['error' => 'Runway not found']);
        return;
    }

    echo json_encode(['runway' => $runway]);
}

function createRunway($pdo) {
    // Check permissions - admin can create runways
    if (!Auth::hasPermission('admin', 'flights')) {
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
    $required = ['runway_number', 'length_ft', 'usage_type'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Missing required field: $field"]);
            return;
        }
    }

    // Check if runway number already exists
    $stmt = $pdo->prepare("SELECT id FROM runways WHERE runway_number = ?");
    $stmt->execute([$data['runway_number']]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['error' => 'Runway number already exists']);
        return;
    }

    // Insert runway
    $stmt = $pdo->prepare("
        INSERT INTO runways (
            runway_number, length_ft, width_ft, surface_type, usage_type,
            max_crosswind_kts, max_tailwind_kts, status, notes
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $data['runway_number'],
        $data['length_ft'],
        $data['width_ft'] ?? null,
        $data['surface_type'] ?? 'asphalt',
        $data['usage_type'],
        $data['max_crosswind_kts'] ?? 20,
        $data['max_tailwind_kts'] ?? 10,
        $data['status'] ?? 'active',
        $data['notes'] ?? null
    ]);

    $runwayId = $pdo->lastInsertId();

    // Log runway creation
    require_once __DIR__ . '/../src/Logger.php';
    Logger::info('Runway created: ' . $data['runway_number'] . ' (ID: ' . $runwayId . ')');

    http_response_code(201);
    echo json_encode([
        'message' => 'Runway created successfully',
        'runway_id' => $runwayId
    ]);
}

function updateRunway($pdo, $runwayId) {
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

    // Check if runway exists
    $stmt = $pdo->prepare("SELECT id FROM runways WHERE id = ?");
    $stmt->execute([$runwayId]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Runway not found']);
        return;
    }

    // Build update query
    $updates = [];
    $params = [];

    $allowedFields = [
        'runway_number', 'length_ft', 'width_ft', 'surface_type',
        'usage_type', 'max_crosswind_kts', 'max_tailwind_kts',
        'status', 'notes'
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

    $params[] = $runwayId;
    $stmt = $pdo->prepare("UPDATE runways SET " . implode(', ', $updates) . ", updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute($params);

    Logger::info('Runway updated: ID ' . $runwayId);

    echo json_encode(['message' => 'Runway updated successfully']);
}

function deleteRunway($pdo, $runwayId) {
    // Check permissions - admin only
    if (!Auth::hasPermission('admin', 'flights')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    // Check if runway is currently assigned
    $stmt = $pdo->prepare("SELECT id FROM runway_assignments WHERE runway_id = ? AND status = 'active'");
    $stmt->execute([$runwayId]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['error' => 'Cannot delete runway that is currently assigned']);
        return;
    }

    $stmt = $pdo->prepare("DELETE FROM runways WHERE id = ?");
    $stmt->execute([$runwayId]);

    Logger::info('Runway deleted: ID ' . $runwayId);

    echo json_encode(['message' => 'Runway deleted successfully']);
}

function assignRunway($pdo) {
    // Check permissions
    if (!Auth::hasPermission('write', 'flights')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || !isset($data['runway_id']) || !isset($data['flight_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Runway ID and Flight ID required']);
        return;
    }

    $runwayId = $data['runway_id'];
    $flightId = $data['flight_id'];
    $operationType = $data['operation_type'] ?? 'departure';
    $expectedRelease = $data['expected_release'] ?? null;

    // Check if runway exists and is available
    $stmt = $pdo->prepare("SELECT id, status FROM runways WHERE id = ?");
    $stmt->execute([$runwayId]);
    $runway = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$runway) {
        http_response_code(404);
        echo json_encode(['error' => 'Runway not found']);
        return;
    }

    if ($runway['status'] !== 'active') {
        http_response_code(409);
        echo json_encode(['error' => 'Runway is not available']);
        return;
    }

    // Check if runway is already assigned
    $stmt = $pdo->prepare("SELECT id FROM runway_assignments WHERE runway_id = ? AND status = 'active'");
    $stmt->execute([$runwayId]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['error' => 'Runway is already assigned']);
        return;
    }

    // Check if flight exists
    $stmt = $pdo->prepare("SELECT id FROM flights WHERE id = ?");
    $stmt->execute([$flightId]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Flight not found']);
        return;
    }

    // Create runway assignment
    $stmt = $pdo->prepare("
        INSERT INTO runway_assignments (
            runway_id, flight_id, operation_type, expected_release, status
        ) VALUES (?, ?, ?, ?, 'active')
    ");
    $stmt->execute([$runwayId, $flightId, $operationType, $expectedRelease]);

    $assignmentId = $pdo->lastInsertId();

    Logger::info('Runway assigned: Runway ' . $runwayId . ' to flight ' . $flightId . ' for ' . $operationType);

    echo json_encode([
        'message' => 'Runway assigned successfully',
        'assignment_id' => $assignmentId
    ]);
}

function releaseRunway($pdo) {
    // Check permissions
    if (!Auth::hasPermission('write', 'flights')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || !isset($data['runway_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Runway ID required']);
        return;
    }

    $runwayId = $data['runway_id'];

    // Update assignment status to completed
    $stmt = $pdo->prepare("
        UPDATE runway_assignments
        SET status = 'completed', released_at = CURRENT_TIMESTAMP
        WHERE runway_id = ? AND status = 'active'
    ");
    $stmt->execute([$runwayId]);

    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'No active assignment found for this runway']);
        return;
    }

    Logger::info('Runway released: Runway ' . $runwayId);

    echo json_encode(['message' => 'Runway released successfully']);
}

function setRunwayMaintenance($pdo) {
    // Check permissions
    if (!Auth::hasPermission('write', 'flights')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || !isset($data['runway_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Runway ID required']);
        return;
    }

    $runwayId = $data['runway_id'];
    $maintenanceType = $data['maintenance_type'] ?? 'scheduled';
    $expectedCompletion = $data['expected_completion'] ?? null;
    $notes = $data['notes'] ?? null;

    // Check if runway exists
    $stmt = $pdo->prepare("SELECT id FROM runways WHERE id = ?");
    $stmt->execute([$runwayId]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Runway not found']);
        return;
    }

    // Check if runway is currently assigned
    $stmt = $pdo->prepare("SELECT id FROM runway_assignments WHERE runway_id = ? AND status = 'active'");
    $stmt->execute([$runwayId]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['error' => 'Cannot set runway to maintenance while it is assigned']);
        return;
    }

    // Update runway status to maintenance
    $stmt = $pdo->prepare("
        UPDATE runways
        SET status = 'maintenance', updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $stmt->execute([$runwayId]);

    // Log maintenance
    Logger::info('Runway set to maintenance: Runway ' . $runwayId . ' - Type: ' . $maintenanceType);

    echo json_encode(['message' => 'Runway set to maintenance successfully']);
}

// Helper function to get runway utilization statistics
function getRunwayUtilization($pdo, $dateFrom = null, $dateTo = null) {
    $whereClause = "";
    $params = [];

    if ($dateFrom && $dateTo) {
        $whereClause = "WHERE ra.assigned_at BETWEEN ? AND ?";
        $params = [$dateFrom, $dateTo];
    }

    $stmt = $pdo->prepare("
        SELECT
            r.runway_number,
            COUNT(ra.id) as total_assignments,
            AVG(EXTRACT(EPOCH FROM (ra.released_at - ra.assigned_at))/60) as avg_usage_minutes,
            MAX(EXTRACT(EPOCH FROM (ra.released_at - ra.assigned_at))/60) as max_usage_minutes
        FROM runways r
        LEFT JOIN runway_assignments ra ON r.id = ra.runway_id AND ra.status = 'completed'
        {$whereClause}
        GROUP BY r.id, r.runway_number
        ORDER BY r.runway_number
    ");
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Helper function to get current runway assignments
function getCurrentAssignments($pdo) {
    $stmt = $pdo->prepare("
        SELECT
            ra.*,
            r.runway_number,
            f.flight_number,
            f.origin,
            f.destination
        FROM runway_assignments ra
        JOIN runways r ON ra.runway_id = r.id
        JOIN flights f ON ra.flight_id = f.id
        WHERE ra.status = 'active'
        ORDER BY ra.assigned_at ASC
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
