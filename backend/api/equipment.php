<?php
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Middleware.php';

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $request = $_SERVER['REQUEST_URI'];

    // Remove query string and decode
    $path = parse_url($request, PHP_URL_PATH);
    $path = str_replace('/api/equipment', '', $path);

    // Get equipment ID from path if present
    $equipmentId = null;
    if (!empty($path) && $path !== '/') {
        $equipmentId = trim($path, '/');
    }

    switch ($method) {
        case 'GET':
            if ($equipmentId) {
                getEquipment($pdo, $equipmentId);
            } else {
                getEquipmentList($pdo);
            }
            break;

        case 'POST':
            if (isset($_GET['action'])) {
                $action = $_GET['action'];
                switch ($action) {
                    case 'assign':
                        assignEquipment($pdo);
                        break;
                    case 'release':
                        releaseEquipment($pdo);
                        break;
                    case 'maintenance':
                        scheduleEquipmentMaintenance($pdo);
                        break;
                    case 'status':
                        updateEquipmentStatus($pdo);
                        break;
                    default:
                        http_response_code(400);
                        echo json_encode(['error' => 'Invalid action']);
                        break;
                }
            } else {
                createEquipment($pdo);
            }
            break;

        case 'PUT':
            if ($equipmentId) {
                updateEquipment($pdo, $equipmentId);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Equipment ID required for update']);
            }
            break;

        case 'DELETE':
            if ($equipmentId) {
                deleteEquipment($pdo, $equipmentId);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Equipment ID required for deletion']);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

function getEquipmentList($pdo) {
    // Check permissions - operations staff can view equipment
    if (!Auth::hasPermission('read', 'flights')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    $type = $_GET['type'] ?? null; // aircraft, ground_support, maintenance
    $status = $_GET['status'] ?? null; // active, maintenance, out_of_service
    $location = $_GET['location'] ?? null;
    $limit = (int)($_GET['limit'] ?? 100);

    $where = [];
    $params = [];

    if ($type) {
        $where[] = "e.equipment_type = ?";
        $params[] = $type;
    }

    if ($status) {
        $where[] = "e.status = ?";
        $params[] = $status;
    }

    if ($location) {
        $where[] = "e.current_location = ?";
        $params[] = $location;
    }

    $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

    // Get equipment with current assignments
    $stmt = $pdo->prepare("
        SELECT
            e.*,
            COALESCE(ea.flight_id, NULL) as assigned_flight_id,
            COALESCE(f.flight_number, NULL) as assigned_flight_number,
            COALESCE(ea.assignment_type, NULL) as assignment_type,
            COALESCE(ea.assigned_at, NULL) as assigned_at,
            COALESCE(ea.expected_release, NULL) as expected_release,
            COALESCE(em.maintenance_type, NULL) as current_maintenance_type,
            COALESCE(em.scheduled_date, NULL) as next_maintenance_date
        FROM equipment e
        LEFT JOIN equipment_assignments ea ON e.id = ea.equipment_id AND ea.status = 'active'
        LEFT JOIN flights f ON ea.flight_id = f.id
        LEFT JOIN equipment_maintenance em ON e.id = em.equipment_id
            AND em.status = 'scheduled'
            AND em.scheduled_date > CURRENT_TIMESTAMP
        {$whereClause}
        ORDER BY e.equipment_type ASC, e.equipment_id ASC
        LIMIT ?
    ");
    $params[] = $limit;
    $stmt->execute($params);
    $equipment = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['equipment' => $equipment]);
}

function getEquipment($pdo, $equipmentId) {
    // Check permissions
    if (!Auth::hasPermission('read', 'flights')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    $stmt = $pdo->prepare("
        SELECT
            e.*,
            COALESCE(ea.flight_id, NULL) as assigned_flight_id,
            COALESCE(f.flight_number, NULL) as assigned_flight_number,
            COALESCE(ea.assignment_type, NULL) as assignment_type,
            COALESCE(ea.assigned_at, NULL) as assigned_at,
            COALESCE(ea.expected_release, NULL) as expected_release,
            COALESCE(ea.status, NULL) as assignment_status
        FROM equipment e
        LEFT JOIN equipment_assignments ea ON e.id = ea.equipment_id AND ea.status = 'active'
        LEFT JOIN flights f ON ea.flight_id = f.id
        WHERE e.id = ?
    ");
    $stmt->execute([$equipmentId]);
    $equipment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$equipment) {
        http_response_code(404);
        echo json_encode(['error' => 'Equipment not found']);
        return;
    }

    echo json_encode(['equipment' => $equipment]);
}

function createEquipment($pdo) {
    // Check permissions - admin can create equipment
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
    $required = ['equipment_id', 'equipment_type', 'model'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Missing required field: $field"]);
            return;
        }
    }

    // Check if equipment ID already exists
    $stmt = $pdo->prepare("SELECT id FROM equipment WHERE equipment_id = ?");
    $stmt->execute([$data['equipment_id']]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['error' => 'Equipment ID already exists']);
        return;
    }

    // Insert equipment
    $stmt = $pdo->prepare("
        INSERT INTO equipment (
            equipment_id, equipment_type, model, manufacturer,
            serial_number, purchase_date, operational_date,
            current_location, status, fuel_capacity, max_weight,
            dimensions, notes
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $data['equipment_id'],
        $data['equipment_type'],
        $data['model'],
        $data['manufacturer'] ?? null,
        $data['serial_number'] ?? null,
        $data['purchase_date'] ?? null,
        $data['operational_date'] ?? null,
        $data['current_location'] ?? null,
        $data['status'] ?? 'active',
        $data['fuel_capacity'] ?? null,
        $data['max_weight'] ?? null,
        $data['dimensions'] ?? null,
        $data['notes'] ?? null
    ]);

    $equipmentId = $pdo->lastInsertId();

    // Log equipment creation
    require_once __DIR__ . '/../src/Logger.php';
    Logger::info('Equipment created: ' . $data['equipment_id'] . ' (' . $data['equipment_type'] . ') - ID: ' . $equipmentId);

    http_response_code(201);
    echo json_encode([
        'message' => 'Equipment created successfully',
        'equipment_id' => $equipmentId
    ]);
}

function updateEquipment($pdo, $equipmentId) {
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

    // Check if equipment exists
    $stmt = $pdo->prepare("SELECT id FROM equipment WHERE id = ?");
    $stmt->execute([$equipmentId]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Equipment not found']);
        return;
    }

    // Build update query
    $updates = [];
    $params = [];

    $allowedFields = [
        'equipment_id', 'equipment_type', 'model', 'manufacturer',
        'serial_number', 'purchase_date', 'operational_date',
        'current_location', 'status', 'fuel_capacity', 'max_weight',
        'dimensions', 'notes'
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

    $params[] = $equipmentId;
    $stmt = $pdo->prepare("UPDATE equipment SET " . implode(', ', $updates) . ", updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute($params);

    Logger::info('Equipment updated: ID ' . $equipmentId);

    echo json_encode(['message' => 'Equipment updated successfully']);
}

function deleteEquipment($pdo, $equipmentId) {
    // Check permissions - admin only
    if (!Auth::hasPermission('admin', 'flights')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    // Check if equipment is currently assigned
    $stmt = $pdo->prepare("SELECT id FROM equipment_assignments WHERE equipment_id = ? AND status = 'active'");
    $stmt->execute([$equipmentId]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['error' => 'Cannot delete equipment that is currently assigned']);
        return;
    }

    $stmt = $pdo->prepare("DELETE FROM equipment WHERE id = ?");
    $stmt->execute([$equipmentId]);

    Logger::info('Equipment deleted: ID ' . $equipmentId);

    echo json_encode(['message' => 'Equipment deleted successfully']);
}

function assignEquipment($pdo) {
    // Check permissions
    if (!Auth::hasPermission('write', 'flights')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || !isset($data['equipment_id']) || !isset($data['flight_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Equipment ID and Flight ID required']);
        return;
    }

    $equipmentId = $data['equipment_id'];
    $flightId = $data['flight_id'];
    $assignmentType = $data['assignment_type'] ?? 'support';
    $expectedRelease = $data['expected_release'] ?? null;

    // Check if equipment exists and is available
    $stmt = $pdo->prepare("SELECT id, status FROM equipment WHERE id = ?");
    $stmt->execute([$equipmentId]);
    $equipment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$equipment) {
        http_response_code(404);
        echo json_encode(['error' => 'Equipment not found']);
        return;
    }

    if ($equipment['status'] !== 'active') {
        http_response_code(409);
        echo json_encode(['error' => 'Equipment is not available']);
        return;
    }

    // Check if equipment is already assigned
    $stmt = $pdo->prepare("SELECT id FROM equipment_assignments WHERE equipment_id = ? AND status = 'active'");
    $stmt->execute([$equipmentId]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['error' => 'Equipment is already assigned']);
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

    // Create equipment assignment
    $stmt = $pdo->prepare("
        INSERT INTO equipment_assignments (
            equipment_id, flight_id, assignment_type, expected_release, status
        ) VALUES (?, ?, ?, ?, 'active')
    ");
    $stmt->execute([$equipmentId, $flightId, $assignmentType, $expectedRelease]);

    $assignmentId = $pdo->lastInsertId();

    Logger::info('Equipment assigned: Equipment ' . $equipmentId . ' to flight ' . $flightId . ' for ' . $assignmentType);

    echo json_encode([
        'message' => 'Equipment assigned successfully',
        'assignment_id' => $assignmentId
    ]);
}

function releaseEquipment($pdo) {
    // Check permissions
    if (!Auth::hasPermission('write', 'flights')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || !isset($data['equipment_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Equipment ID required']);
        return;
    }

    $equipmentId = $data['equipment_id'];

    // Update assignment status to completed
    $stmt = $pdo->prepare("
        UPDATE equipment_assignments
        SET status = 'completed', released_at = CURRENT_TIMESTAMP
        WHERE equipment_id = ? AND status = 'active'
    ");
    $stmt->execute([$equipmentId]);

    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'No active assignment found for this equipment']);
        return;
    }

    Logger::info('Equipment released: Equipment ' . $equipmentId);

    echo json_encode(['message' => 'Equipment released successfully']);
}

function scheduleEquipmentMaintenance($pdo) {
    // Check permissions
    if (!Auth::hasPermission('write', 'flights')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || !isset($data['equipment_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Equipment ID required']);
        return;
    }

    $equipmentId = $data['equipment_id'];
    $maintenanceType = $data['maintenance_type'] ?? 'routine';
    $scheduledDate = $data['scheduled_date'] ?? null;
    $estimatedDuration = $data['estimated_duration_hours'] ?? null;
    $notes = $data['notes'] ?? null;

    // Check if equipment exists
    $stmt = $pdo->prepare("SELECT id FROM equipment WHERE id = ?");
    $stmt->execute([$equipmentId]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Equipment not found']);
        return;
    }

    // Insert maintenance schedule
    $stmt = $pdo->prepare("
        INSERT INTO equipment_maintenance (
            equipment_id, maintenance_type, scheduled_date,
            estimated_duration_hours, status, notes
        ) VALUES (?, ?, ?, ?, 'scheduled', ?)
    ");
    $stmt->execute([$equipmentId, $maintenanceType, $scheduledDate, $estimatedDuration, $notes]);

    $maintenanceId = $pdo->lastInsertId();

    Logger::info('Equipment maintenance scheduled: Equipment ' . $equipmentId . ' - Type: ' . $maintenanceType);

    echo json_encode([
        'message' => 'Equipment maintenance scheduled successfully',
        'maintenance_id' => $maintenanceId
    ]);
}

function updateEquipmentStatus($pdo) {
    // Check permissions
    if (!Auth::hasPermission('write', 'flights')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || !isset($data['equipment_id']) || !isset($data['status'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Equipment ID and status required']);
        return;
    }

    $equipmentId = $data['equipment_id'];
    $status = $data['status'];
    $location = $data['location'] ?? null;
    $notes = $data['notes'] ?? null;

    // Check if equipment exists
    $stmt = $pdo->prepare("SELECT id FROM equipment WHERE id = ?");
    $stmt->execute([$equipmentId]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Equipment not found']);
        return;
    }

    // Update equipment status
    $stmt = $pdo->prepare("
        UPDATE equipment
        SET status = ?, current_location = ?, updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $stmt->execute([$status, $location, $equipmentId]);

    // Log status change
    Logger::info('Equipment status updated: Equipment ' . $equipmentId . ' - Status: ' . $status);

    echo json_encode(['message' => 'Equipment status updated successfully']);
}

// Helper function to get equipment utilization statistics
function getEquipmentUtilization($pdo, $equipmentType = null, $dateFrom = null, $dateTo = null) {
    $where = [];
    $params = [];

    if ($equipmentType) {
        $where[] = "e.equipment_type = ?";
        $params[] = $equipmentType;
    }

    if ($dateFrom && $dateTo) {
        $where[] = "ea.assigned_at BETWEEN ? AND ?";
        $params = array_merge($params, [$dateFrom, $dateTo]);
    }

    $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

    $stmt = $pdo->prepare("
        SELECT
            e.equipment_id,
            e.equipment_type,
            e.model,
            COUNT(ea.id) as total_assignments,
            AVG(EXTRACT(EPOCH FROM (ea.released_at - ea.assigned_at))/3600) as avg_usage_hours,
            MAX(EXTRACT(EPOCH FROM (ea.released_at - ea.assigned_at))/3600) as max_usage_hours,
            SUM(EXTRACT(EPOCH FROM (ea.released_at - ea.assigned_at))/3600) as total_usage_hours
        FROM equipment e
        LEFT JOIN equipment_assignments ea ON e.id = ea.equipment_id AND ea.status = 'completed'
        {$whereClause}
        GROUP BY e.id, e.equipment_id, e.equipment_type, e.model
        ORDER BY e.equipment_type ASC, total_assignments DESC
    ");
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Helper function to get equipment maintenance schedule
function getEquipmentMaintenanceSchedule($pdo, $equipmentId = null) {
    $where = $equipmentId ? "WHERE em.equipment_id = ?" : "";
    $params = $equipmentId ? [$equipmentId] : [];

    $stmt = $pdo->prepare("
        SELECT
            em.*,
            e.equipment_id,
            e.equipment_type,
            e.model
        FROM equipment_maintenance em
        JOIN equipment e ON em.equipment_id = e.id
        {$where}
        ORDER BY em.scheduled_date ASC
    ");
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Helper function to get equipment by location
function getEquipmentByLocation($pdo, $location) {
    $stmt = $pdo->prepare("
        SELECT
            e.*,
            COUNT(ea.id) as active_assignments
        FROM equipment e
        LEFT JOIN equipment_assignments ea ON e.id = ea.equipment_id AND ea.status = 'active'
        WHERE e.current_location = ?
        GROUP BY e.id
        ORDER BY e.equipment_type ASC
    ");
    $stmt->execute([$location]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Helper function to get equipment availability
function getEquipmentAvailability($pdo, $equipmentType = null, $startTime = null, $endTime = null) {
    $where = [];
    $params = [];

    if ($equipmentType) {
        $where[] = "e.equipment_type = ?";
        $params[] = $equipmentType;
    }

    if ($startTime && $endTime) {
        $where[] = "e.status = 'active'";
        $where[] = "NOT EXISTS (
            SELECT 1 FROM equipment_assignments ea
            WHERE ea.equipment_id = e.id
            AND ea.status = 'active'
            AND (
                (ea.assigned_at <= ? AND ea.expected_release > ?) OR
                (ea.assigned_at < ? AND ea.expected_release >= ?)
            )
        )";
        $params = array_merge($params, [$startTime, $startTime, $endTime, $endTime]);
    }

    $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

    $stmt = $pdo->prepare("
        SELECT
            e.*,
            COUNT(ea.id) as current_assignments
        FROM equipment e
        LEFT JOIN equipment_assignments ea ON e.id = ea.equipment_id AND ea.status = 'active'
        {$whereClause}
        GROUP BY e.id
        HAVING COUNT(ea.id) = 0
        ORDER BY e.equipment_type ASC
    ");
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
