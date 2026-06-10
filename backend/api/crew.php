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
    $path = str_replace('/api/crew', '', $path);

    // Get assignment ID from path if present
    $assignmentId = null;
    if (!empty($path) && $path !== '/') {
        $assignmentId = trim($path, '/');
    }

    switch ($method) {
        case 'GET':
            if ($assignmentId) {
                getCrewAssignment($pdo, $assignmentId);
            } else {
                getCrewAssignments($pdo);
            }
            break;

        case 'POST':
            createCrewAssignment($pdo);
            break;

        case 'PUT':
            if ($assignmentId) {
                updateCrewAssignment($pdo, $assignmentId);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Assignment ID required for update']);
            }
            break;

        case 'DELETE':
            if ($assignmentId) {
                deleteCrewAssignment($pdo, $assignmentId);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Assignment ID required for deletion']);
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

function getCrewAssignments($pdo) {
    // Check permissions - operations staff can view crew assignments
    if (!Auth::hasPermission('read', 'flights')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 50);
    $offset = ($page - 1) * $limit;

    $flightId = $_GET['flight_id'] ?? null;
    $userId = $_GET['user_id'] ?? null;
    $role = $_GET['role'] ?? null;
    $dateFrom = $_GET['date_from'] ?? null;
    $dateTo = $_GET['date_to'] ?? null;

    $where = [];
    $params = [];

    if ($flightId) {
        $where[] = "ca.flight_id = ?";
        $params[] = $flightId;
    }

    if ($userId) {
        $where[] = "ca.user_id = ?";
        $params[] = $userId;
    }

    if ($role) {
        $where[] = "ca.role = ?";
        $params[] = $role;
    }

    if ($dateFrom) {
        $where[] = "f.scheduled_departure >= ?";
        $params[] = $dateFrom;
    }

    if ($dateTo) {
        $where[] = "f.scheduled_departure <= ?";
        $params[] = $dateTo;
    }

    $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

    // Get crew assignments with user and flight details
    $stmt = $pdo->prepare("
        SELECT
            ca.*,
            u.username,
            u.first_name,
            u.last_name,
            u.email,
            f.flight_number,
            f.origin,
            f.destination,
            f.scheduled_departure,
            f.scheduled_arrival,
            f.status as flight_status,
            a.name as airline_name
        FROM crew_assignments ca
        JOIN users u ON ca.user_id = u.id
        JOIN flights f ON ca.flight_id = f.id
        JOIN airlines a ON f.airline_id = a.id
        {$whereClause}
        ORDER BY f.scheduled_departure ASC, ca.assigned_at ASC
        LIMIT ? OFFSET ?
    ");
    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);
    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get total count
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM crew_assignments ca
        JOIN flights f ON ca.flight_id = f.id
        {$whereClause}
    ");
    array_pop($params); // Remove limit
    array_pop($params); // Remove offset
    $countStmt->execute($params);
    $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

    echo json_encode([
        'crew_assignments' => $assignments,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => ceil($total / $limit)
        ]
    ]);
}

function getCrewAssignment($pdo, $assignmentId) {
    // Check permissions
    if (!Auth::hasPermission('read', 'flights')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    $stmt = $pdo->prepare("
        SELECT
            ca.*,
            u.username,
            u.first_name,
            u.last_name,
            u.email,
            f.flight_number,
            f.origin,
            f.destination,
            f.scheduled_departure,
            f.scheduled_arrival,
            f.status as flight_status,
            a.name as airline_name,
            a.code as airline_code
        FROM crew_assignments ca
        JOIN users u ON ca.user_id = u.id
        JOIN flights f ON ca.flight_id = f.id
        JOIN airlines a ON f.airline_id = a.id
        WHERE ca.id = ?
    ");
    $stmt->execute([$assignmentId]);
    $assignment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$assignment) {
        http_response_code(404);
        echo json_encode(['error' => 'Crew assignment not found']);
        return;
    }

    echo json_encode(['crew_assignment' => $assignment]);
}

function createCrewAssignment($pdo) {
    // Check permissions - operations staff can create crew assignments
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
    $required = ['flight_id', 'user_id', 'role'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Missing required field: $field"]);
            return;
        }
    }

    // Check if flight exists
    $flightStmt = $pdo->prepare("SELECT id FROM flights WHERE id = ?");
    $flightStmt->execute([$data['flight_id']]);
    if (!$flightStmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Flight not found']);
        return;
    }

    // Check if user exists and is active
    $userStmt = $pdo->prepare("SELECT id, is_active FROM users WHERE id = ?");
    $userStmt->execute([$data['user_id']]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        http_response_code(404);
        echo json_encode(['error' => 'User not found']);
        return;
    }
    if (!$user['is_active']) {
        http_response_code(400);
        echo json_encode(['error' => 'User is not active']);
        return;
    }

    // Check if user is already assigned to this flight
    $existingStmt = $pdo->prepare("
        SELECT id FROM crew_assignments
        WHERE flight_id = ? AND user_id = ?
    ");
    $existingStmt->execute([$data['flight_id'], $data['user_id']]);
    if ($existingStmt->fetch()) {
        http_response_code(409);
        echo json_encode(['error' => 'User is already assigned to this flight']);
        return;
    }

    // Check for scheduling conflicts (user assigned to another flight at same time)
    $conflictStmt = $pdo->prepare("
        SELECT ca.id, f.flight_number, f.scheduled_departure
        FROM crew_assignments ca
        JOIN flights f ON ca.flight_id = f.id
        WHERE ca.user_id = ?
        AND f.scheduled_departure BETWEEN
            (SELECT scheduled_departure - INTERVAL '2 hours' FROM flights WHERE id = ?) AND
            (SELECT scheduled_arrival + INTERVAL '2 hours' FROM flights WHERE id = ?)
        AND f.id != ?
    ");
    $conflictStmt->execute([
        $data['user_id'],
        $data['flight_id'],
        $data['flight_id'],
        $data['flight_id']
    ]);
    $conflict = $conflictStmt->fetch(PDO::FETCH_ASSOC);
    if ($conflict) {
        http_response_code(409);
        echo json_encode([
            'error' => 'User has scheduling conflict',
            'conflicting_flight' => $conflict['flight_number'],
            'conflict_time' => $conflict['scheduled_departure']
        ]);
        return;
    }

    // Insert crew assignment
    $stmt = $pdo->prepare("
        INSERT INTO crew_assignments (
            flight_id, user_id, role
        ) VALUES (?, ?, ?)
    ");
    $stmt->execute([
        $data['flight_id'],
        $data['user_id'],
        $data['role']
    ]);

    $assignmentId = $pdo->lastInsertId();

    // Log crew assignment
    require_once __DIR__ . '/../src/Logger.php';
    Logger::info('Crew assignment created: ID ' . $assignmentId . ' - User ' . $data['user_id'] . ' assigned to flight ' . $data['flight_id'] . ' as ' . $data['role']);

    http_response_code(201);
    echo json_encode([
        'message' => 'Crew assignment created successfully',
        'assignment_id' => $assignmentId
    ]);
}

function updateCrewAssignment($pdo, $assignmentId) {
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

    // Check if assignment exists
    $stmt = $pdo->prepare("SELECT id FROM crew_assignments WHERE id = ?");
    $stmt->execute([$assignmentId]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Crew assignment not found']);
        return;
    }

    // Build update query
    $updates = [];
    $params = [];

    $allowedFields = ['role'];

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

    $params[] = $assignmentId;
    $stmt = $pdo->prepare("UPDATE crew_assignments SET " . implode(', ', $updates) . " WHERE id = ?");
    $stmt->execute($params);

    echo json_encode(['message' => 'Crew assignment updated successfully']);
}

function deleteCrewAssignment($pdo, $assignmentId) {
    // Check permissions
    if (!Auth::hasPermission('write', 'flights')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    // Check if assignment exists
    $stmt = $pdo->prepare("SELECT id, flight_id FROM crew_assignments WHERE id = ?");
    $stmt->execute([$assignmentId]);
    $assignment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$assignment) {
        http_response_code(404);
        echo json_encode(['error' => 'Crew assignment not found']);
        return;
    }

    // Check if flight has already departed
    $flightStmt = $pdo->prepare("SELECT scheduled_departure FROM flights WHERE id = ?");
    $flightStmt->execute([$assignment['flight_id']]);
    $flight = $flightStmt->fetch(PDO::FETCH_ASSOC);

    if (strtotime($flight['scheduled_departure']) < time()) {
        http_response_code(409);
        echo json_encode(['error' => 'Cannot delete assignment for departed flight']);
        return;
    }

    $stmt = $pdo->prepare("DELETE FROM crew_assignments WHERE id = ?");
    $stmt->execute([$assignmentId]);

    Logger::info('Crew assignment deleted: ID ' . $assignmentId);

    echo json_encode(['message' => 'Crew assignment deleted successfully']);
}

// Helper function to get crew for a specific flight
function getFlightCrew($pdo, $flightId) {
    $stmt = $pdo->prepare("
        SELECT
            ca.*,
            u.username,
            u.first_name,
            u.last_name,
            u.email
        FROM crew_assignments ca
        JOIN users u ON ca.user_id = u.id
        WHERE ca.flight_id = ?
        ORDER BY ca.role ASC, u.last_name ASC
    ");
    $stmt->execute([$flightId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Helper function to get user's flight schedule
function getUserFlightSchedule($pdo, $userId, $startDate = null, $endDate = null) {
    $where = ["ca.user_id = ?"];
    $params = [$userId];

    if ($startDate) {
        $where[] = "f.scheduled_departure >= ?";
        $params[] = $startDate;
    }

    if ($endDate) {
        $where[] = "f.scheduled_departure <= ?";
        $params[] = $endDate;
    }

    $whereClause = implode(" AND ", $where);

    $stmt = $pdo->prepare("
        SELECT
            ca.*,
            f.flight_number,
            f.origin,
            f.destination,
            f.scheduled_departure,
            f.scheduled_arrival,
            f.status as flight_status,
            a.name as airline_name
        FROM crew_assignments ca
        JOIN flights f ON ca.flight_id = f.id
        JOIN airlines a ON f.airline_id = a.id
        WHERE {$whereClause}
        ORDER BY f.scheduled_departure ASC
    ");
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
