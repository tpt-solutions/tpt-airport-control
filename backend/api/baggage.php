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
    $path = str_replace('/api/baggage', '', $path);

    // Get baggage ID from path if present
    $baggageId = null;
    if (!empty($path) && $path !== '/') {
        $baggageId = trim($path, '/');
    }

    switch ($method) {
        case 'GET':
            if ($baggageId) {
                getBaggage($pdo, $baggageId);
            } else {
                getBaggageItems($pdo);
            }
            break;

        case 'POST':
            createBaggage($pdo);
            break;

        case 'PUT':
            if ($baggageId) {
                updateBaggage($pdo, $baggageId);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Baggage ID required for update']);
            }
            break;

        case 'DELETE':
            if ($baggageId) {
                deleteBaggage($pdo, $baggageId);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Baggage ID required for deletion']);
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

function getBaggageItems($pdo) {
    // Check permissions
    $currentUser = Auth::getCurrentUser();
    if (!$currentUser) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        return;
    }

    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 50);
    $offset = ($page - 1) * $limit;

    $status = $_GET['status'] ?? null;
    $bookingId = $_GET['booking_id'] ?? null;
    $passengerId = $_GET['passenger_id'] ?? null;
    $search = $_GET['search'] ?? null;

    $where = [];
    $params = [];

    // Regular passengers can only see their own baggage
    if ($currentUser['role_name'] === 'passenger') {
        $where[] = "EXISTS (SELECT 1 FROM bookings b WHERE b.id = baggage.booking_id AND b.passenger_id = ?)";
        $params[] = $currentUser['passenger_id'];
    }

    if ($status) {
        $where[] = "baggage.status = ?";
        $params[] = $status;
    }

    if ($bookingId && Auth::hasPermission('read', 'passengers')) {
        $where[] = "baggage.booking_id = ?";
        $params[] = $bookingId;
    }

    if ($passengerId && Auth::hasPermission('read', 'passengers')) {
        $where[] = "EXISTS (SELECT 1 FROM bookings b WHERE b.id = baggage.booking_id AND b.passenger_id = ?)";
        $params[] = $passengerId;
    }

    if ($search) {
        $where[] = "(baggage.tag_number ILIKE ? OR p.first_name ILIKE ? OR p.last_name ILIKE ?)";
        $searchParam = '%' . $search . '%';
        $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
    }

    $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

    // Get baggage with passenger and flight details
    $stmt = $pdo->prepare("
        SELECT
            baggage.*,
            p.first_name,
            p.last_name,
            p.email,
            b.booking_reference,
            b.seat_number,
            f.flight_number,
            f.origin,
            f.destination,
            f.scheduled_departure,
            a.name as airline_name
        FROM baggage
        JOIN bookings b ON baggage.booking_id = b.id
        JOIN passengers p ON b.passenger_id = p.id
        JOIN flights f ON b.flight_id = f.id
        JOIN airlines a ON f.airline_id = a.id
        {$whereClause}
        ORDER BY baggage.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);
    $baggageItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get total count
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM baggage
        JOIN bookings b ON baggage.booking_id = b.id
        JOIN passengers p ON b.passenger_id = p.id
        {$whereClause}
    ");
    array_pop($params); // Remove limit
    array_pop($params); // Remove offset
    $countStmt->execute($params);
    $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

    echo json_encode([
        'baggage' => $baggageItems,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => ceil($total / $limit)
        ]
    ]);
}

function getBaggage($pdo, $baggageId) {
    // Check permissions
    $currentUser = Auth::getCurrentUser();
    if (!$currentUser) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        return;
    }

    $stmt = $pdo->prepare("
        SELECT
            baggage.*,
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
            f.scheduled_arrival,
            a.name as airline_name,
            a.code as airline_code
        FROM baggage
        JOIN bookings b ON baggage.booking_id = b.id
        JOIN passengers p ON b.passenger_id = p.id
        JOIN flights f ON b.flight_id = f.id
        JOIN airlines a ON f.airline_id = a.id
        WHERE baggage.id = ?
    ");
    $stmt->execute([$baggageId]);
    $baggage = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$baggage) {
        http_response_code(404);
        echo json_encode(['error' => 'Baggage not found']);
        return;
    }

    // Regular passengers can only view their own baggage
    if ($currentUser['role_name'] === 'passenger') {
        $passengerCheckStmt = $pdo->prepare("
            SELECT b.passenger_id
            FROM baggage bag
            JOIN bookings b ON bag.booking_id = b.id
            WHERE bag.id = ?
        ");
        $passengerCheckStmt->execute([$baggageId]);
        $bookingData = $passengerCheckStmt->fetch(PDO::FETCH_ASSOC);

        if ($bookingData['passenger_id'] != $currentUser['passenger_id']) {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            return;
        }
    }

    // Staff need read permission for passengers
    if (!Auth::hasPermission('read', 'passengers')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    echo json_encode(['baggage' => $baggage]);
}

function createBaggage($pdo) {
    // Check permissions - staff can create baggage records
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

    // Validate required fields
    $required = ['booking_id'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Missing required field: $field"]);
            return;
        }
    }

    // Check if booking exists and is active
    $bookingStmt = $pdo->prepare("
        SELECT b.status, f.scheduled_departure
        FROM bookings b
        JOIN flights f ON b.flight_id = f.id
        WHERE b.id = ?
    ");
    $bookingStmt->execute([$data['booking_id']]);
    $booking = $bookingStmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        http_response_code(404);
        echo json_encode(['error' => 'Booking not found']);
        return;
    }

    if ($booking['status'] !== 'confirmed' && $booking['status'] !== 'checked-in') {
        http_response_code(400);
        echo json_encode(['error' => 'Booking is not active']);
        return;
    }

    // Generate unique tag number
    $tagNumber = generateBaggageTag();

    // Insert baggage
    $stmt = $pdo->prepare("
        INSERT INTO baggage (
            booking_id, tag_number, weight, status, location
        ) VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $data['booking_id'],
        $tagNumber,
        $data['weight'] ?? null,
        'checked',
        $data['location'] ?? 'check-in counter'
    ]);

    $baggageId = $pdo->lastInsertId();

    http_response_code(201);
    echo json_encode([
        'message' => 'Baggage checked in successfully',
        'baggage_id' => $baggageId,
        'tag_number' => $tagNumber
    ]);
}

function updateBaggage($pdo, $baggageId) {
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

    // Build update query dynamically
    $updates = [];
    $params = [];

    $allowedFields = ['weight', 'status', 'location'];

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

    $params[] = $baggageId;
    $stmt = $pdo->prepare("UPDATE baggage SET " . implode(', ', $updates) . ", updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute($params);

    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Baggage not found']);
        return;
    }

    echo json_encode(['message' => 'Baggage updated successfully']);
}

function deleteBaggage($pdo, $baggageId) {
    // Check permissions - only admins can delete baggage records
    if (!Auth::hasPermission('admin', 'passengers')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    $stmt = $pdo->prepare("DELETE FROM baggage WHERE id = ?");
    $stmt->execute([$baggageId]);

    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Baggage not found']);
        return;
    }

    echo json_encode(['message' => 'Baggage record deleted successfully']);
}

function generateBaggageTag() {
    // Generate a unique baggage tag number
    // Format: XXX-XXXXXXX (3 letters + 7 digits)
    $letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $numbers = '0123456789';

    $tag = '';
    for ($i = 0; $i < 3; $i++) {
        $tag .= $letters[rand(0, strlen($letters) - 1)];
    }
    $tag .= '-';
    for ($i = 0; $i < 7; $i++) {
        $tag .= $numbers[rand(0, strlen($numbers) - 1)];
    }

    return $tag;
}

// Helper function to report lost baggage
function reportLostBaggage($pdo, $baggageId, $reportedBy) {
    // Update baggage status to lost
    $stmt = $pdo->prepare("UPDATE baggage SET status = 'lost', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$baggageId]);

    // In a real application, this would trigger notifications, create incident reports, etc.
    // For now, we'll just log it
    require_once __DIR__ . '/../src/Logger.php';
    Logger::info('Lost baggage reported: ID ' . $baggageId . ' by user ' . $reportedBy);

    return true;
}
?>
