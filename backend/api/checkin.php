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
    $path = str_replace('/api/checkin', '', $path);

    // Get booking ID from path if present
    $bookingId = null;
    if (!empty($path) && $path !== '/') {
        $bookingId = trim($path, '/');
    }

    switch ($method) {
        case 'GET':
            if ($bookingId) {
                getCheckInStatus($pdo, $bookingId);
            } else {
                getCheckIns($pdo);
            }
            break;

        case 'POST':
            if ($bookingId) {
                processCheckIn($pdo, $bookingId);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Booking ID required']);
            }
            break;

        case 'PUT':
            if ($bookingId) {
                updateCheckIn($pdo, $bookingId);
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

function getCheckIns($pdo) {
    // Check permissions
    if (!Auth::hasPermission('read', 'passengers')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 50);
    $offset = ($page - 1) * $limit;

    $status = $_GET['status'] ?? null;
    $flightId = $_GET['flight_id'] ?? null;
    $search = $_GET['search'] ?? null;

    $where = [];
    $params = [];

    if ($status) {
        $where[] = "ci.boarding_pass_issued = ?";
        $params[] = $status === 'completed' ? true : false;
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

    // Get check-ins with passenger and booking details
    $stmt = $pdo->prepare("
        SELECT
            ci.*,
            p.first_name,
            p.last_name,
            p.email,
            p.passport_number,
            b.booking_reference,
            b.seat_number,
            f.flight_number,
            f.origin,
            f.destination,
            f.scheduled_departure,
            f.gate,
            f.terminal,
            a.name as airline_name
        FROM check_ins ci
        JOIN bookings b ON ci.booking_id = b.id
        JOIN passengers p ON b.passenger_id = p.id
        JOIN flights f ON b.flight_id = f.id
        JOIN airlines a ON f.airline_id = a.id
        {$whereClause}
        ORDER BY ci.check_in_time DESC
        LIMIT ? OFFSET ?
    ");
    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);
    $checkIns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get total count
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM check_ins ci
        JOIN bookings b ON ci.booking_id = b.id
        {$whereClause}
    ");
    array_pop($params); // Remove limit
    array_pop($params); // Remove offset
    $countStmt->execute($params);
    $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

    echo json_encode([
        'checkins' => $checkIns,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => ceil($total / $limit)
        ]
    ]);
}

function getCheckInStatus($pdo, $bookingId) {
    // Check permissions
    $currentUser = Auth::getCurrentUser();
    if (!$currentUser) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        return;
    }

    // Get booking details to check ownership
    $bookingStmt = $pdo->prepare("
        SELECT b.passenger_id, b.status, f.scheduled_departure
        FROM bookings b
        JOIN flights f ON b.flight_id = f.id
        WHERE b.id = ?
    ");
    $bookingStmt->execute([$bookingId]);
    $booking = $bookingStmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        http_response_code(404);
        echo json_encode(['error' => 'Booking not found']);
        return;
    }

    // Regular passengers can only check their own bookings
    if ($currentUser['role_name'] === 'passenger' && $booking['passenger_id'] != $currentUser['passenger_id']) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    // Staff need read permission for passengers
    if (!Auth::hasPermission('read', 'passengers')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    // Get check-in status
    $stmt = $pdo->prepare("
        SELECT
            ci.*,
            p.first_name,
            p.last_name,
            p.email,
            p.passport_number,
            p.date_of_birth,
            b.booking_reference,
            b.seat_number,
            f.flight_number,
            f.origin,
            f.destination,
            f.scheduled_departure,
            f.scheduled_arrival,
            f.gate,
            f.terminal,
            a.name as airline_name,
            a.code as airline_code
        FROM check_ins ci
        JOIN bookings b ON ci.booking_id = b.id
        JOIN passengers p ON b.passenger_id = p.id
        JOIN flights f ON b.flight_id = f.id
        JOIN airlines a ON f.airline_id = a.id
        WHERE ci.booking_id = ?
    ");
    $stmt->execute([$bookingId]);
    $checkIn = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$checkIn) {
        // No check-in record exists yet
        echo json_encode([
            'status' => 'not_checked_in',
            'booking_id' => $bookingId,
            'can_check_in' => canCheckIn($booking),
            'message' => 'Passenger has not checked in yet'
        ]);
        return;
    }

    echo json_encode([
        'status' => 'checked_in',
        'checkin' => $checkIn,
        'boarding_pass_available' => $checkIn['boarding_pass_issued']
    ]);
}

function processCheckIn($pdo, $bookingId) {
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

    // Get booking details
    $bookingStmt = $pdo->prepare("
        SELECT b.*, f.scheduled_departure, f.origin, f.destination
        FROM bookings b
        JOIN flights f ON b.flight_id = f.id
        WHERE b.id = ?
    ");
    $bookingStmt->execute([$bookingId]);
    $booking = $bookingStmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        http_response_code(404);
        echo json_encode(['error' => 'Booking not found']);
        return;
    }

    // Validate check-in eligibility
    if (!canCheckIn($booking)) {
        http_response_code(400);
        echo json_encode(['error' => 'Check-in not available for this booking']);
        return;
    }

    // Check if already checked in
    $existingStmt = $pdo->prepare("SELECT id FROM check_ins WHERE booking_id = ?");
    $existingStmt->execute([$bookingId]);
    if ($existingStmt->fetch()) {
        http_response_code(409);
        echo json_encode(['error' => 'Passenger already checked in']);
        return;
    }

    // Process check-in
    $stmt = $pdo->prepare("
        INSERT INTO check_ins (
            booking_id, check_in_time, boarding_pass_issued, security_cleared
        ) VALUES (?, CURRENT_TIMESTAMP, ?, ?)
    ");
    $stmt->execute([
        $bookingId,
        $data['issue_boarding_pass'] ?? true,
        $data['security_cleared'] ?? false
    ]);

    $checkInId = $pdo->lastInsertId();

    // Update booking status
    $bookingUpdateStmt = $pdo->prepare("UPDATE bookings SET status = 'checked-in' WHERE id = ?");
    $bookingUpdateStmt->execute([$bookingId]);

    // Generate boarding pass data if requested
    $boardingPass = null;
    if ($data['issue_boarding_pass'] ?? true) {
        $boardingPass = generateBoardingPass($pdo, $bookingId);
    }

    http_response_code(201);
    echo json_encode([
        'message' => 'Check-in completed successfully',
        'checkin_id' => $checkInId,
        'boarding_pass' => $boardingPass
    ]);
}

function updateCheckIn($pdo, $bookingId) {
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

    $allowedFields = ['boarding_pass_issued', 'security_cleared'];

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

    $params[] = $bookingId;
    $stmt = $pdo->prepare("UPDATE check_ins SET " . implode(', ', $updates) . " WHERE booking_id = ?");
    $stmt->execute($params);

    echo json_encode(['message' => 'Check-in updated successfully']);
}

function canCheckIn($booking) {
    // Check if booking is confirmed
    if ($booking['status'] !== 'confirmed') {
        return false;
    }

    // Check if flight is not departed
    if (strtotime($booking['scheduled_departure']) < time()) {
        return false;
    }

    // Check if check-in is open (typically 24-48 hours before departure)
    $checkInOpens = strtotime($booking['scheduled_departure']) - (24 * 60 * 60); // 24 hours before
    if (time() < $checkInOpens) {
        return false;
    }

    return true;
}

function generateBoardingPass($pdo, $bookingId) {
    // Get booking and passenger details
    $stmt = $pdo->prepare("
        SELECT
            b.booking_reference,
            b.seat_number,
            p.first_name,
            p.last_name,
            p.passport_number,
            f.flight_number,
            f.origin,
            f.destination,
            f.scheduled_departure,
            f.scheduled_arrival,
            f.gate,
            f.terminal,
            a.name as airline_name,
            a.code as airline_code
        FROM bookings b
        JOIN passengers p ON b.passenger_id = p.id
        JOIN flights f ON b.flight_id = f.id
        JOIN airlines a ON f.airline_id = a.id
        WHERE b.id = ?
    ");
    $stmt->execute([$bookingId]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        return null;
    }

    // Generate QR code data
    $qrData = json_encode([
        'booking_ref' => $booking['booking_reference'],
        'flight' => $booking['flight_number'],
        'passenger' => $booking['first_name'] . ' ' . $booking['last_name'],
        'seat' => $booking['seat_number'],
        'departure' => $booking['scheduled_departure'],
        'gate' => $booking['gate'],
        'issued' => date('Y-m-d H:i:s')
    ]);

    return [
        'booking_reference' => $booking['booking_reference'],
        'passenger_name' => $booking['first_name'] . ' ' . $booking['last_name'],
        'flight_number' => $booking['flight_number'],
        'origin' => $booking['origin'],
        'destination' => $booking['destination'],
        'scheduled_departure' => $booking['scheduled_departure'],
        'scheduled_arrival' => $booking['scheduled_arrival'],
        'seat_number' => $booking['seat_number'],
        'gate' => $booking['gate'],
        'terminal' => $booking['terminal'],
        'airline_name' => $booking['airline_name'],
        'airline_code' => $booking['airline_code'],
        'qr_code_data' => $qrData,
        'issued_at' => date('Y-m-d H:i:s')
    ];
}
?>
