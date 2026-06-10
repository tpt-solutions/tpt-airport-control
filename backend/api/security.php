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
    $path = str_replace('/api/security', '', $path);

    // Get booking ID from path if present
    $bookingId = null;
    if (!empty($path) && $path !== '/') {
        $bookingId = trim($path, '/');
    }

    switch ($method) {
        case 'GET':
            if ($bookingId) {
                getSecurityStatus($pdo, $bookingId);
            } else {
                getSecurityScreenings($pdo);
            }
            break;

        case 'POST':
            if (isset($_GET['action'])) {
                $action = $_GET['action'];
                switch ($action) {
                    case 'screen':
                        if ($bookingId) {
                            processSecurityScreening($pdo, $bookingId);
                        } else {
                            http_response_code(400);
                            echo json_encode(['error' => 'Booking ID required']);
                        }
                        break;
                    case 'clear':
                        if ($bookingId) {
                            clearSecurity($pdo, $bookingId);
                        } else {
                            http_response_code(400);
                            echo json_encode(['error' => 'Booking ID required']);
                        }
                        break;
                    case 'flag':
                        if ($bookingId) {
                            flagSecurityIssue($pdo, $bookingId);
                        } else {
                            http_response_code(400);
                            echo json_encode(['error' => 'Booking ID required']);
                        }
                        break;
                    default:
                        http_response_code(400);
                        echo json_encode(['error' => 'Invalid action']);
                        break;
                }
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Action parameter required']);
            }
            break;

        case 'PUT':
            if ($bookingId) {
                updateSecurityStatus($pdo, $bookingId);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Booking ID required for update']);
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

function getSecurityScreenings($pdo) {
    // Check permissions - security staff can view screenings
    if (!Auth::hasPermission('read', 'passengers')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 50);
    $offset = ($page - 1) * $limit;

    $status = $_GET['status'] ?? null; // cleared, pending, flagged
    $flightId = $_GET['flight_id'] ?? null;
    $search = $_GET['search'] ?? null;

    $where = [];
    $params = [];

    if ($status) {
        if ($status === 'cleared') {
            $where[] = "ci.security_cleared = true";
        } elseif ($status === 'pending') {
            $where[] = "ci.security_cleared = false AND ci.id IS NOT NULL";
        } elseif ($status === 'not_checked_in') {
            $where[] = "ci.id IS NULL";
        }
    }

    if ($flightId) {
        $where[] = "b.flight_id = ?";
        $params[] = $flightId;
    }

    if ($search) {
        $where[] = "(p.first_name ILIKE ? OR p.last_name ILIKE ? OR b.booking_reference ILIKE ?)";
        $searchParam = '%' . $search . '%';
        $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
    }

    $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

    // Get security screenings with passenger and booking details
    $stmt = $pdo->prepare("
        SELECT
            b.id as booking_id,
            b.booking_reference,
            b.seat_number,
            p.first_name,
            p.last_name,
            p.passport_number,
            p.nationality,
            f.flight_number,
            f.origin,
            f.destination,
            f.scheduled_departure,
            ci.security_cleared,
            ci.check_in_time,
            CASE
                WHEN ci.id IS NULL THEN 'not_checked_in'
                WHEN ci.security_cleared = true THEN 'cleared'
                ELSE 'pending'
            END as security_status
        FROM bookings b
        JOIN passengers p ON b.passenger_id = p.id
        JOIN flights f ON b.flight_id = f.id
        LEFT JOIN check_ins ci ON b.id = ci.booking_id
        {$whereClause}
        ORDER BY f.scheduled_departure ASC, b.created_at ASC
        LIMIT ? OFFSET ?
    ");
    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);
    $screenings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get total count
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM bookings b
        JOIN passengers p ON b.passenger_id = p.id
        LEFT JOIN check_ins ci ON b.id = ci.booking_id
        {$whereClause}
    ");
    array_pop($params); // Remove limit
    array_pop($params); // Remove offset
    $countStmt->execute($params);
    $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

    echo json_encode([
        'screenings' => $screenings,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => ceil($total / $limit)
        ]
    ]);
}

function getSecurityStatus($pdo, $bookingId) {
    // Check permissions
    $currentUser = Auth::getCurrentUser();
    if (!$currentUser) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        return;
    }

    // Get booking details to check ownership
    $bookingStmt = $pdo->prepare("
        SELECT b.passenger_id, b.status
        FROM bookings b
        WHERE b.id = ?
    ");
    $bookingStmt->execute([$bookingId]);
    $booking = $bookingStmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        http_response_code(404);
        echo json_encode(['error' => 'Booking not found']);
        return;
    }

    // Regular passengers can only view their own security status
    if ($currentUser['role_name'] === 'passenger' && $booking['passenger_id'] != $currentUser['passenger_id']) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    // Get security status
    $stmt = $pdo->prepare("
        SELECT
            ci.security_cleared,
            ci.check_in_time,
            b.booking_reference,
            p.first_name,
            p.last_name,
            p.passport_number,
            p.nationality,
            f.flight_number,
            f.origin,
            f.destination
        FROM check_ins ci
        JOIN bookings b ON ci.booking_id = b.id
        JOIN passengers p ON b.passenger_id = p.id
        JOIN flights f ON b.flight_id = f.id
        WHERE ci.booking_id = ?
    ");
    $stmt->execute([$bookingId]);
    $security = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$security) {
        echo json_encode([
            'status' => 'not_checked_in',
            'message' => 'Passenger has not checked in yet'
        ]);
        return;
    }

    echo json_encode([
        'status' => $security['security_cleared'] ? 'cleared' : 'pending',
        'security_cleared' => $security['security_cleared'],
        'check_in_time' => $security['check_in_time'],
        'passenger_info' => [
            'name' => $security['first_name'] . ' ' . $security['last_name'],
            'passport' => $security['passport_number'],
            'nationality' => $security['nationality']
        ],
        'flight_info' => [
            'number' => $security['flight_number'],
            'route' => $security['origin'] . ' → ' . $security['destination']
        ]
    ]);
}

function processSecurityScreening($pdo, $bookingId) {
    // Check permissions - security staff can process screenings
    if (!Auth::hasPermission('write', 'passengers')) {
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

    // Check if check-in exists
    $checkInStmt = $pdo->prepare("SELECT id, security_cleared FROM check_ins WHERE booking_id = ?");
    $checkInStmt->execute([$bookingId]);
    $checkIn = $checkInStmt->fetch(PDO::FETCH_ASSOC);

    if (!$checkIn) {
        http_response_code(404);
        echo json_encode(['error' => 'Check-in not found']);
        return;
    }

    if ($checkIn['security_cleared']) {
        http_response_code(409);
        echo json_encode(['error' => 'Security screening already completed']);
        return;
    }

    // Validate screening data
    $screeningResult = $data['result'] ?? 'pass';
    $notes = $data['notes'] ?? null;

    if (!in_array($screeningResult, ['pass', 'fail', 'additional_check'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid screening result']);
        return;
    }

    // Update security clearance
    $cleared = ($screeningResult === 'pass');
    $stmt = $pdo->prepare("UPDATE check_ins SET security_cleared = ? WHERE booking_id = ?");
    $stmt->execute([$cleared, $bookingId]);

    // Log security screening
    require_once __DIR__ . '/../src/Logger.php';
    Logger::info('Security screening completed: Booking ' . $bookingId . ' - Result: ' . $screeningResult);

    echo json_encode([
        'message' => 'Security screening completed',
        'result' => $screeningResult,
        'security_cleared' => $cleared,
        'notes' => $notes
    ]);
}

function clearSecurity($pdo, $bookingId) {
    // Check permissions
    if (!Auth::hasPermission('write', 'passengers')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    // Check if check-in exists
    $stmt = $pdo->prepare("SELECT id FROM check_ins WHERE booking_id = ?");
    $stmt->execute([$bookingId]);
    $checkIn = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$checkIn) {
        http_response_code(404);
        echo json_encode(['error' => 'Check-in not found']);
        return;
    }

    // Clear security status
    $updateStmt = $pdo->prepare("UPDATE check_ins SET security_cleared = true WHERE booking_id = ?");
    $updateStmt->execute([$bookingId]);

    Logger::info('Security clearance granted: Booking ' . $bookingId);

    echo json_encode(['message' => 'Security clearance granted']);
}

function flagSecurityIssue($pdo, $bookingId) {
    // Check permissions
    if (!Auth::hasPermission('write', 'passengers')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || !isset($data['issue_type'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Issue type required']);
        return;
    }

    // Check if check-in exists
    $stmt = $pdo->prepare("SELECT id FROM check_ins WHERE booking_id = ?");
    $stmt->execute([$bookingId]);
    $checkIn = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$checkIn) {
        http_response_code(404);
        echo json_encode(['error' => 'Check-in not found']);
        return;
    }

    // Log security issue
    $issueType = $data['issue_type'];
    $description = $data['description'] ?? '';
    $severity = $data['severity'] ?? 'medium';

    Logger::warning('Security issue flagged: Booking ' . $bookingId . ' - Type: ' . $issueType . ' - Severity: ' . $severity);

    // In a real system, this would create a security incident record
    // For now, we'll just mark security as not cleared
    $updateStmt = $pdo->prepare("UPDATE check_ins SET security_cleared = false WHERE booking_id = ?");
    $updateStmt->execute([$bookingId]);

    echo json_encode([
        'message' => 'Security issue flagged',
        'issue_type' => $issueType,
        'severity' => $severity,
        'description' => $description
    ]);
}

function updateSecurityStatus($pdo, $bookingId) {
    // Check permissions
    if (!Auth::hasPermission('write', 'passengers')) {
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

    // Check if check-in exists
    $stmt = $pdo->prepare("SELECT id FROM check_ins WHERE booking_id = ?");
    $stmt->execute([$bookingId]);
    $checkIn = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$checkIn) {
        http_response_code(404);
        echo json_encode(['error' => 'Check-in not found']);
        return;
    }

    // Build update query
    $updates = [];
    $params = [];

    if (isset($data['security_cleared'])) {
        $updates[] = "security_cleared = ?";
        $params[] = $data['security_cleared'];
    }

    if (empty($updates)) {
        http_response_code(400);
        echo json_encode(['error' => 'No valid fields to update']);
        return;
    }

    $params[] = $bookingId;
    $stmt = $pdo->prepare("UPDATE check_ins SET " . implode(', ', $updates) . " WHERE booking_id = ?");
    $stmt->execute($params);

    echo json_encode(['message' => 'Security status updated successfully']);
}

// Helper function to check access control
function checkAccessControl($pdo, $bookingId, $accessPoint) {
    // This would integrate with physical access control systems
    // For now, just check if passenger is cleared for boarding

    $stmt = $pdo->prepare("
        SELECT
            ci.security_cleared,
            ci.boarding_pass_issued,
            f.scheduled_departure,
            f.gate
        FROM check_ins ci
        JOIN bookings b ON ci.booking_id = b.id
        JOIN flights f ON b.flight_id = f.id
        WHERE ci.booking_id = ?
    ");
    $stmt->execute([$bookingId]);
    $access = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$access) {
        return ['granted' => false, 'reason' => 'Check-in not found'];
    }

    if (!$access['security_cleared']) {
        return ['granted' => false, 'reason' => 'Security clearance not obtained'];
    }

    if (!$access['boarding_pass_issued']) {
        return ['granted' => false, 'reason' => 'Boarding pass not issued'];
    }

    if (strtotime($access['scheduled_departure']) < time()) {
        return ['granted' => false, 'reason' => 'Flight has departed'];
    }

    // Check if accessing correct gate
    if ($accessPoint !== $access['gate']) {
        return ['granted' => false, 'reason' => 'Incorrect gate access'];
    }

    return ['granted' => true, 'gate' => $access['gate']];
}
?>
