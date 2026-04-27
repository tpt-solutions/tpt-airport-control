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
    $path = str_replace('/api/maintenance', '', $path);

    // Get maintenance ID from path if present
    $maintenanceId = null;
    if (!empty($path) && $path !== '/') {
        $maintenanceId = trim($path, '/');
    }

    switch ($method) {
        case 'GET':
            if ($maintenanceId) {
                getMaintenanceSchedule($pdo, $maintenanceId);
            } else {
                getMaintenanceSchedules($pdo);
            }
            break;

        case 'POST':
            createMaintenanceSchedule($pdo);
            break;

        case 'PUT':
            if ($maintenanceId) {
                updateMaintenanceSchedule($pdo, $maintenanceId);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Maintenance ID required for update']);
            }
            break;

        case 'DELETE':
            if ($maintenanceId) {
                deleteMaintenanceSchedule($pdo, $maintenanceId);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Maintenance ID required for deletion']);
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

function getMaintenanceSchedules($pdo) {
    // Check permissions - ground operations staff can view maintenance
    if (!Auth::hasPermission('read', 'flights')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 50);
    $offset = ($page - 1) * $limit;

    $aircraftId = $_GET['aircraft_id'] ?? null;
    $status = $_GET['status'] ?? null; // scheduled, completed, overdue
    $type = $_GET['type'] ?? null;
    $dateFrom = $_GET['date_from'] ?? null;
    $dateTo = $_GET['date_to'] ?? null;

    $where = [];
    $params = [];

    if ($aircraftId) {
        $where[] = "ms.aircraft_id = ?";
        $params[] = $aircraftId;
    }

    if ($status) {
        if ($status === 'overdue') {
            $where[] = "ms.completed = false AND ms.scheduled_date < CURRENT_DATE";
        } elseif ($status === 'completed') {
            $where[] = "ms.completed = true";
        } elseif ($status === 'scheduled') {
            $where[] = "ms.completed = false AND ms.scheduled_date >= CURRENT_DATE";
        }
    }

    if ($type) {
        $where[] = "ms.maintenance_type = ?";
        $params[] = $type;
    }

    if ($dateFrom) {
        $where[] = "ms.scheduled_date >= ?";
        $params[] = $dateFrom;
    }

    if ($dateTo) {
        $where[] = "ms.scheduled_date <= ?";
        $params[] = $dateTo;
    }

    $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

    // Get maintenance schedules with aircraft details
    $stmt = $pdo->prepare("
        SELECT
            ms.*,
            ac.model as aircraft_model,
            ac.registration as aircraft_registration,
            a.name as airline_name
        FROM maintenance_schedules ms
        JOIN aircraft ac ON ms.aircraft_id = ac.id
        LEFT JOIN airlines a ON ac.airline_id = a.id
        {$whereClause}
        ORDER BY ms.scheduled_date ASC, ms.created_at ASC
        LIMIT ? OFFSET ?
    ");
    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get total count
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM maintenance_schedules ms
        {$whereClause}
    ");
    array_pop($params); // Remove limit
    array_pop($params); // Remove offset
    $countStmt->execute($params);
    $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

    echo json_encode([
        'maintenance_schedules' => $schedules,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => ceil($total / $limit)
        ]
    ]);
}

function getMaintenanceSchedule($pdo, $maintenanceId) {
    // Check permissions
    if (!Auth::hasPermission('read', 'flights')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    $stmt = $pdo->prepare("
        SELECT
            ms.*,
            ac.model as aircraft_model,
            ac.registration as aircraft_registration,
            ac.capacity as aircraft_capacity,
            a.name as airline_name,
            a.code as airline_code
        FROM maintenance_schedules ms
        JOIN aircraft ac ON ms.aircraft_id = ac.id
        LEFT JOIN airlines a ON ac.airline_id = a.id
        WHERE ms.id = ?
    ");
    $stmt->execute([$maintenanceId]);
    $schedule = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$schedule) {
        http_response_code(404);
        echo json_encode(['error' => 'Maintenance schedule not found']);
        return;
    }

    echo json_encode(['maintenance_schedule' => $schedule]);
}

function createMaintenanceSchedule($pdo) {
    // Check permissions - maintenance staff can create schedules
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
    $required = ['aircraft_id', 'maintenance_type', 'scheduled_date'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Missing required field: $field"]);
            return;
        }
    }

    // Check if aircraft exists
    $aircraftStmt = $pdo->prepare("SELECT id FROM aircraft WHERE id = ?");
    $aircraftStmt->execute([$data['aircraft_id']]);
    if (!$aircraftStmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Aircraft not found']);
        return;
    }

    // Check if maintenance schedule already exists for this aircraft on this date
    $existingStmt = $pdo->prepare("
        SELECT id FROM maintenance_schedules
        WHERE aircraft_id = ? AND scheduled_date = ? AND maintenance_type = ?
    ");
    $existingStmt->execute([
        $data['aircraft_id'],
        $data['scheduled_date'],
        $data['maintenance_type']
    ]);
    if ($existingStmt->fetch()) {
        http_response_code(409);
        echo json_encode(['error' => 'Maintenance schedule already exists for this aircraft on this date']);
        return;
    }

    // Insert maintenance schedule
    $stmt = $pdo->prepare("
        INSERT INTO maintenance_schedules (
            aircraft_id, maintenance_type, scheduled_date, completed, notes
        ) VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $data['aircraft_id'],
        $data['maintenance_type'],
        $data['scheduled_date'],
        false, // completed
        $data['notes'] ?? null
    ]);

    $maintenanceId = $pdo->lastInsertId();

    // Log maintenance schedule creation
    require_once __DIR__ . '/../src/Logger.php';
    Logger::info('Maintenance schedule created: ID ' . $maintenanceId . ' for aircraft ' . $data['aircraft_id']);

    http_response_code(201);
    echo json_encode([
        'message' => 'Maintenance schedule created successfully',
        'maintenance_id' => $maintenanceId
    ]);
}

function updateMaintenanceSchedule($pdo, $maintenanceId) {
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

    // Check if maintenance schedule exists
    $stmt = $pdo->prepare("SELECT id, completed FROM maintenance_schedules WHERE id = ?");
    $stmt->execute([$maintenanceId]);
    $schedule = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$schedule) {
        http_response_code(404);
        echo json_encode(['error' => 'Maintenance schedule not found']);
        return;
    }

    // Build update query
    $updates = [];
    $params = [];

    $allowedFields = ['maintenance_type', 'scheduled_date', 'completed', 'notes'];

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

    $params[] = $maintenanceId;
    $stmt = $pdo->prepare("UPDATE maintenance_schedules SET " . implode(', ', $updates) . ", updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute($params);

    // Log completion if status changed to completed
    if (isset($data['completed']) && $data['completed'] && !$schedule['completed']) {
        Logger::info('Maintenance completed: ID ' . $maintenanceId);
    }

    echo json_encode(['message' => 'Maintenance schedule updated successfully']);
}

function deleteMaintenanceSchedule($pdo, $maintenanceId) {
    // Check permissions - only admins can delete maintenance schedules
    if (!Auth::hasPermission('admin', 'flights')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    // Check if maintenance is already completed
    $stmt = $pdo->prepare("SELECT completed FROM maintenance_schedules WHERE id = ?");
    $stmt->execute([$maintenanceId]);
    $schedule = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$schedule) {
        http_response_code(404);
        echo json_encode(['error' => 'Maintenance schedule not found']);
        return;
    }

    if ($schedule['completed']) {
        http_response_code(409);
        echo json_encode(['error' => 'Cannot delete completed maintenance schedule']);
        return;
    }

    $stmt = $pdo->prepare("DELETE FROM maintenance_schedules WHERE id = ?");
    $stmt->execute([$maintenanceId]);

    Logger::info('Maintenance schedule deleted: ID ' . $maintenanceId);

    echo json_encode(['message' => 'Maintenance schedule deleted successfully']);
}

// Helper function to get upcoming maintenance
function getUpcomingMaintenance($pdo, $days = 7) {
    $stmt = $pdo->prepare("
        SELECT
            ms.*,
            ac.model as aircraft_model,
            ac.registration as aircraft_registration,
            a.name as airline_name
        FROM maintenance_schedules ms
        JOIN aircraft ac ON ms.aircraft_id = ac.id
        LEFT JOIN airlines a ON ac.airline_id = a.id
        WHERE ms.completed = false
        AND ms.scheduled_date BETWEEN CURRENT_DATE AND CURRENT_DATE + INTERVAL '{$days} days'
        ORDER BY ms.scheduled_date ASC
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Helper function to get overdue maintenance
function getOverdueMaintenance($pdo) {
    $stmt = $pdo->prepare("
        SELECT
            ms.*,
            ac.model as aircraft_model,
            ac.registration as aircraft_registration,
            a.name as airline_name
        FROM maintenance_schedules ms
        JOIN aircraft ac ON ms.aircraft_id = ac.id
        LEFT JOIN airlines a ON ac.airline_id = a.id
        WHERE ms.completed = false
        AND ms.scheduled_date < CURRENT_DATE
        ORDER BY ms.scheduled_date ASC
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
