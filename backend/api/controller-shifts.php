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
    $path = str_replace('/api/controller-shifts', '', $path);

    // Get shift ID from path if present
    $shiftId = null;
    if (!empty($path) && $path !== '/') {
        $shiftId = trim($path, '/');
    }

    switch ($method) {
        case 'GET':
            if ($shiftId) {
                getControllerShift($pdo, $shiftId);
            } else {
                getControllerShifts($pdo);
            }
            break;

        case 'POST':
            if (isset($_GET['action'])) {
                $action = $_GET['action'];
                switch ($action) {
                    case 'start':
                        startControllerShift($pdo);
                        break;
                    case 'end':
                        endControllerShift($pdo);
                        break;
                    case 'handover':
                        performShiftHandover($pdo);
                        break;
                    case 'break':
                        logBreakTime($pdo);
                        break;
                    default:
                        http_response_code(400);
                        echo json_encode(['error' => 'Invalid action']);
                        break;
                }
            } else {
                createControllerShift($pdo);
            }
            break;

        case 'PUT':
            if ($shiftId) {
                updateControllerShift($pdo, $shiftId);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Shift ID required for update']);
            }
            break;

        case 'DELETE':
            if ($shiftId) {
                deleteControllerShift($pdo, $shiftId);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Shift ID required for deletion']);
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

function getControllerShifts($pdo) {
    // Check permissions - operations staff can view shifts
    if (!Auth::hasPermission('read', 'flights')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    $status = $_GET['status'] ?? null; // active, completed, scheduled
    $controllerId = $_GET['controller_id'] ?? null;
    $position = $_GET['position'] ?? null;
    $date = $_GET['date'] ?? null;
    $limit = (int)($_GET['limit'] ?? 50);

    $where = [];
    $params = [];

    if ($status) {
        $where[] = "cs.status = ?";
        $params[] = $status;
    }

    if ($controllerId) {
        $where[] = "cs.controller_id = ?";
        $params[] = $controllerId;
    }

    if ($position) {
        $where[] = "cs.position = ?";
        $params[] = $position;
    }

    if ($date) {
        $where[] = "DATE(cs.start_time) = ?";
        $params[] = $date;
    }

    $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

    // Get controller shifts with user details
    $stmt = $pdo->prepare("
        SELECT
            cs.*,
            u.username as controller_username,
            u.first_name as controller_first_name,
            u.last_name as controller_last_name,
            u2.username as relieved_by_username,
            u2.first_name as relieved_by_first_name,
            u2.last_name as relieved_by_last_name
        FROM controller_shifts cs
        JOIN users u ON cs.controller_id = u.id
        LEFT JOIN users u2 ON cs.relieved_by = u2.id
        {$whereClause}
        ORDER BY cs.start_time DESC
        LIMIT ?
    ");
    $params[] = $limit;
    $stmt->execute($params);
    $shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['controller_shifts' => $shifts]);
}

function getControllerShift($pdo, $shiftId) {
    // Check permissions
    if (!Auth::hasPermission('read', 'flights')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    $stmt = $pdo->prepare("
        SELECT
            cs.*,
            u.username as controller_username,
            u.first_name as controller_first_name,
            u.last_name as controller_last_name,
            u2.username as relieved_by_username,
            u2.first_name as relieved_by_first_name,
            u2.last_name as relieved_by_last_name
        FROM controller_shifts cs
        JOIN users u ON cs.controller_id = u.id
        LEFT JOIN users u2 ON cs.relieved_by = u2.id
        WHERE cs.id = ?
    ");
    $stmt->execute([$shiftId]);
    $shift = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$shift) {
        http_response_code(404);
        echo json_encode(['error' => 'Controller shift not found']);
        return;
    }

    echo json_encode(['controller_shift' => $shift]);
}

function createControllerShift($pdo) {
    // Check permissions - operations staff can create shifts
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
    $required = ['controller_id', 'position', 'start_time'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Missing required field: $field"]);
            return;
        }
    }

    // Check if controller exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$data['controller_id']]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Controller not found']);
        return;
    }

    // Check for conflicting shifts
    $stmt = $pdo->prepare("
        SELECT id FROM controller_shifts
        WHERE controller_id = ?
        AND position = ?
        AND status = 'active'
        AND (
            (start_time <= ? AND end_time > ?) OR
            (start_time < ? AND end_time >= ?)
        )
    ");
    $stmt->execute([
        $data['controller_id'],
        $data['position'],
        $data['start_time'],
        $data['start_time'],
        $data['end_time'] ?? $data['start_time'],
        $data['end_time'] ?? $data['start_time']
    ]);

    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['error' => 'Controller has conflicting shift']);
        return;
    }

    // Insert controller shift
    $stmt = $pdo->prepare("
        INSERT INTO controller_shifts (
            controller_id, position, start_time, end_time,
            shift_type, sector, notes, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $data['controller_id'],
        $data['position'],
        $data['start_time'],
        $data['end_time'] ?? null,
        $data['shift_type'] ?? 'regular',
        $data['sector'] ?? null,
        $data['notes'] ?? null,
        $data['status'] ?? 'scheduled'
    ]);

    $shiftId = $pdo->lastInsertId();

    // Log shift creation
    require_once __DIR__ . '/../src/Logger.php';
    Logger::info('Controller shift created: ID ' . $shiftId . ' for controller ' . $data['controller_id'] . ' at position ' . $data['position']);

    http_response_code(201);
    echo json_encode([
        'message' => 'Controller shift created successfully',
        'shift_id' => $shiftId
    ]);
}

function updateControllerShift($pdo, $shiftId) {
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

    // Check if shift exists
    $stmt = $pdo->prepare("SELECT id FROM controller_shifts WHERE id = ?");
    $stmt->execute([$shiftId]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Controller shift not found']);
        return;
    }

    // Build update query
    $updates = [];
    $params = [];

    $allowedFields = [
        'position', 'start_time', 'end_time', 'shift_type',
        'sector', 'notes', 'status'
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

    $params[] = $shiftId;
    $stmt = $pdo->prepare("UPDATE controller_shifts SET " . implode(', ', $updates) . ", updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute($params);

    Logger::info('Controller shift updated: ID ' . $shiftId);

    echo json_encode(['message' => 'Controller shift updated successfully']);
}

function deleteControllerShift($pdo, $shiftId) {
    // Check permissions - admin only
    if (!Auth::hasPermission('admin', 'flights')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    // Check if shift exists
    $stmt = $pdo->prepare("SELECT status FROM controller_shifts WHERE id = ?");
    $stmt->execute([$shiftId]);
    $shift = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$shift) {
        http_response_code(404);
        echo json_encode(['error' => 'Controller shift not found']);
        return;
    }

    if ($shift['status'] === 'active') {
        http_response_code(409);
        echo json_encode(['error' => 'Cannot delete active shift']);
        return;
    }

    $stmt = $pdo->prepare("DELETE FROM controller_shifts WHERE id = ?");
    $stmt->execute([$shiftId]);

    Logger::info('Controller shift deleted: ID ' . $shiftId);

    echo json_encode(['message' => 'Controller shift deleted successfully']);
}

function startControllerShift($pdo) {
    // Check permissions
    if (!Auth::hasPermission('write', 'flights')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || !isset($data['shift_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Shift ID required']);
        return;
    }

    $shiftId = $data['shift_id'];

    // Check if shift exists and is scheduled
    $stmt = $pdo->prepare("SELECT id, status, controller_id FROM controller_shifts WHERE id = ?");
    $stmt->execute([$shiftId]);
    $shift = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$shift) {
        http_response_code(404);
        echo json_encode(['error' => 'Controller shift not found']);
        return;
    }

    if ($shift['status'] !== 'scheduled') {
        http_response_code(409);
        echo json_encode(['error' => 'Shift is not in scheduled status']);
        return;
    }

    // Get current user
    $currentUser = Auth::getCurrentUser();

    // Verify the current user is the assigned controller
    if ($currentUser['id'] != $shift['controller_id']) {
        http_response_code(403);
        echo json_encode(['error' => 'You are not assigned to this shift']);
        return;
    }

    // Update shift status to active
    $stmt = $pdo->prepare("
        UPDATE controller_shifts
        SET status = 'active', actual_start_time = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $stmt->execute([$shiftId]);

    Logger::info('Controller shift started: ID ' . $shiftId . ' by controller ' . $currentUser['id']);

    echo json_encode(['message' => 'Controller shift started successfully']);
}

function endControllerShift($pdo) {
    // Check permissions
    if (!Auth::hasPermission('write', 'flights')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || !isset($data['shift_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Shift ID required']);
        return;
    }

    $shiftId = $data['shift_id'];

    // Check if shift exists and is active
    $stmt = $pdo->prepare("SELECT id, status, controller_id FROM controller_shifts WHERE id = ?");
    $stmt->execute([$shiftId]);
    $shift = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$shift) {
        http_response_code(404);
        echo json_encode(['error' => 'Controller shift not found']);
        return;
    }

    if ($shift['status'] !== 'active') {
        http_response_code(409);
        echo json_encode(['error' => 'Shift is not active']);
        return;
    }

    // Get current user
    $currentUser = Auth::getCurrentUser();

    // Verify the current user is the assigned controller
    if ($currentUser['id'] != $shift['controller_id']) {
        http_response_code(403);
        echo json_encode(['error' => 'You are not assigned to this shift']);
        return;
    }

    // Update shift status to completed
    $stmt = $pdo->prepare("
        UPDATE controller_shifts
        SET status = 'completed', actual_end_time = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $stmt->execute([$shiftId]);

    Logger::info('Controller shift ended: ID ' . $shiftId . ' by controller ' . $currentUser['id']);

    echo json_encode(['message' => 'Controller shift ended successfully']);
}

function performShiftHandover($pdo) {
    // Check permissions
    if (!Auth::hasPermission('write', 'flights')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || !isset($data['shift_id']) || !isset($data['relieving_controller_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Shift ID and relieving controller ID required']);
        return;
    }

    $shiftId = $data['shift_id'];
    $relievingControllerId = $data['relieving_controller_id'];
    $handoverNotes = $data['handover_notes'] ?? null;

    // Check if shift exists and is active
    $stmt = $pdo->prepare("SELECT id, status, controller_id FROM controller_shifts WHERE id = ?");
    $stmt->execute([$shiftId]);
    $shift = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$shift) {
        http_response_code(404);
        echo json_encode(['error' => 'Controller shift not found']);
        return;
    }

    if ($shift['status'] !== 'active') {
        http_response_code(409);
        echo json_encode(['error' => 'Shift is not active']);
        return;
    }

    // Check if relieving controller exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$relievingControllerId]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Relieving controller not found']);
        return;
    }

    // Get current user
    $currentUser = Auth::getCurrentUser();

    // Update shift with handover information
    $stmt = $pdo->prepare("
        UPDATE controller_shifts
        SET relieved_by = ?, handover_notes = ?, handover_time = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $stmt->execute([$relievingControllerId, $handoverNotes, $shiftId]);

    Logger::info('Shift handover performed: Shift ' . $shiftId . ' from controller ' . $currentUser['id'] . ' to ' . $relievingControllerId);

    echo json_encode(['message' => 'Shift handover completed successfully']);
}

function logBreakTime($pdo) {
    // Check permissions
    if (!Auth::hasPermission('write', 'flights')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || !isset($data['shift_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Shift ID required']);
        return;
    }

    $shiftId = $data['shift_id'];
    $breakType = $data['break_type'] ?? 'regular';
    $breakStart = $data['break_start'] ?? date('Y-m-d H:i:s');
    $breakEnd = $data['break_end'] ?? null;
    $notes = $data['notes'] ?? null;

    // Check if shift exists and is active
    $stmt = $pdo->prepare("SELECT id, status FROM controller_shifts WHERE id = ?");
    $stmt->execute([$shiftId]);
    $shift = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$shift) {
        http_response_code(404);
        echo json_encode(['error' => 'Controller shift not found']);
        return;
    }

    if ($shift['status'] !== 'active') {
        http_response_code(409);
        echo json_encode(['error' => 'Shift is not active']);
        return;
    }

    // Insert break record
    $stmt = $pdo->prepare("
        INSERT INTO controller_breaks (
            shift_id, break_type, break_start, break_end, notes
        ) VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$shiftId, $breakType, $breakStart, $breakEnd, $notes]);

    $breakId = $pdo->lastInsertId();

    Logger::info('Controller break logged: Shift ' . $shiftId . ' - Type: ' . $breakType);

    echo json_encode([
        'message' => 'Controller break logged successfully',
        'break_id' => $breakId
    ]);
}

// Helper function to get current active shifts
function getActiveControllerShifts($pdo) {
    $stmt = $pdo->prepare("
        SELECT
            cs.*,
            u.username as controller_username,
            u.first_name as controller_first_name,
            u.last_name as controller_last_name
        FROM controller_shifts cs
        JOIN users u ON cs.controller_id = u.id
        WHERE cs.status = 'active'
        ORDER BY cs.start_time ASC
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Helper function to get shift schedule for a date range
function getShiftSchedule($pdo, $startDate, $endDate) {
    $stmt = $pdo->prepare("
        SELECT
            cs.*,
            u.username as controller_username,
            u.first_name as controller_first_name,
            u.last_name as controller_last_name
        FROM controller_shifts cs
        JOIN users u ON cs.controller_id = u.id
        WHERE DATE(cs.start_time) BETWEEN ? AND ?
        ORDER BY cs.start_time ASC
    ");
    $stmt->execute([$startDate, $endDate]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Helper function to get controller workload statistics
function getControllerWorkloadStats($pdo, $controllerId, $startDate, $endDate) {
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) as total_shifts,
            SUM(EXTRACT(EPOCH FROM (COALESCE(actual_end_time, end_time) - actual_start_time))/3600) as total_hours,
            AVG(EXTRACT(EPOCH FROM (COALESCE(actual_end_time, end_time) - actual_start_time))/3600) as avg_shift_hours,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_shifts
        FROM controller_shifts
        WHERE controller_id = ?
        AND DATE(start_time) BETWEEN ? AND ?
        AND status IN ('completed', 'active')
    ");
    $stmt->execute([$controllerId, $startDate, $endDate]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Helper function to check for shift conflicts
function checkShiftConflicts($pdo, $controllerId, $startTime, $endTime, $excludeShiftId = null) {
    $where = "controller_id = ? AND status IN ('scheduled', 'active') AND ((start_time <= ? AND end_time > ?) OR (start_time < ? AND end_time >= ?))";
    $params = [$controllerId, $startTime, $startTime, $endTime, $endTime];

    if ($excludeShiftId) {
        $where .= " AND id != ?";
        $params[] = $excludeShiftId;
    }

    $stmt = $pdo->prepare("SELECT id FROM controller_shifts WHERE {$where}");
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
