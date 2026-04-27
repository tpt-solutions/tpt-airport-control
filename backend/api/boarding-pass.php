<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
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
    $path = str_replace('/api/boarding-pass', '', $path);

    // Get booking ID from path if present
    $bookingId = null;
    if (!empty($path) && $path !== '/') {
        $bookingId = trim($path, '/');
    }

    switch ($method) {
        case 'GET':
            if ($bookingId) {
                getBoardingPass($pdo, $bookingId);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Booking ID required']);
            }
            break;

        case 'POST':
            if (isset($_GET['action'])) {
                $action = $_GET['action'];
                switch ($action) {
                    case 'regenerate':
                        if ($bookingId) {
                            regenerateBoardingPass($pdo, $bookingId);
                        } else {
                            http_response_code(400);
                            echo json_encode(['error' => 'Booking ID required']);
                        }
                        break;
                    case 'validate_qr':
                        validateQRCode($pdo);
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

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error', 'message' => $e->getMessage()]);
}

function getBoardingPass($pdo, $bookingId) {
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

    // Regular passengers can only view their own boarding passes
    if ($currentUser['role_name'] === 'passenger' && $booking['passenger_id'] != $currentUser['passenger_id']) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    // Check if boarding pass has been issued
    $checkInStmt = $pdo->prepare("
        SELECT ci.boarding_pass_issued, ci.security_cleared
        FROM check_ins ci
        WHERE ci.booking_id = ?
    ");
    $checkInStmt->execute([$bookingId]);
    $checkIn = $checkInStmt->fetch(PDO::FETCH_ASSOC);

    if (!$checkIn || !$checkIn['boarding_pass_issued']) {
        http_response_code(404);
        echo json_encode(['error' => 'Boarding pass not issued yet']);
        return;
    }

    // Generate boarding pass data
    $boardingPass = generateBoardingPassData($pdo, $bookingId);

    if (!$boardingPass) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to generate boarding pass']);
        return;
    }

    echo json_encode([
        'boarding_pass' => $boardingPass,
        'security_cleared' => $checkIn['security_cleared']
    ]);
}

function regenerateBoardingPass($pdo, $bookingId) {
    // Check permissions
    if (!Auth::hasPermission('write', 'passengers')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    // Check if boarding pass exists
    $checkInStmt = $pdo->prepare("
        SELECT ci.id, ci.boarding_pass_issued
        FROM check_ins ci
        WHERE ci.booking_id = ?
    ");
    $checkInStmt->execute([$bookingId]);
    $checkIn = $checkInStmt->fetch(PDO::FETCH_ASSOC);

    if (!$checkIn) {
        http_response_code(404);
        echo json_encode(['error' => 'Check-in not found']);
        return;
    }

    // Generate new boarding pass
    $boardingPass = generateBoardingPassData($pdo, $bookingId);

    if (!$boardingPass) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to regenerate boarding pass']);
        return;
    }

    echo json_encode([
        'message' => 'Boarding pass regenerated successfully',
        'boarding_pass' => $boardingPass
    ]);
}

function validateQRCode($pdo) {
    // Check permissions - security staff can validate QR codes
    if (!Auth::hasPermission('read', 'passengers')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || !isset($data['qr_data'])) {
        http_response_code(400);
        echo json_encode(['error' => 'QR data required']);
        return;
    }

    // Decode QR data
    $qrData = json_decode($data['qr_data'], true);
    if (!$qrData || !isset($qrData['booking_ref'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid QR code data']);
        return;
    }

    $bookingRef = $qrData['booking_ref'];

    // Find booking by reference
    $stmt = $pdo->prepare("
        SELECT
            b.id,
            b.status,
            b.seat_number,
            p.first_name,
            p.last_name,
            f.flight_number,
            f.scheduled_departure,
            f.gate,
            ci.security_cleared,
            ci.boarding_pass_issued
        FROM bookings b
        JOIN passengers p ON b.passenger_id = p.id
        JOIN flights f ON b.flight_id = f.id
        LEFT JOIN check_ins ci ON b.id = ci.booking_id
        WHERE b.booking_reference = ?
    ");
    $stmt->execute([$bookingRef]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        echo json_encode([
            'valid' => false,
            'error' => 'Booking not found'
        ]);
        return;
    }

    // Check if boarding pass is valid
    $isValid = true;
    $errors = [];

    if ($booking['status'] !== 'checked-in') {
        $isValid = false;
        $errors[] = 'Passenger not checked in';
    }

    if (!$booking['boarding_pass_issued']) {
        $isValid = false;
        $errors[] = 'Boarding pass not issued';
    }

    if (!$booking['security_cleared']) {
        $isValid = false;
        $errors[] = 'Security clearance not obtained';
    }

    if (strtotime($booking['scheduled_departure']) < time()) {
        $isValid = false;
        $errors[] = 'Flight has already departed';
    }

    echo json_encode([
        'valid' => $isValid,
        'booking' => $isValid ? [
            'reference' => $bookingRef,
            'passenger' => $booking['first_name'] . ' ' . $booking['last_name'],
            'flight' => $booking['flight_number'],
            'seat' => $booking['seat_number'],
            'gate' => $booking['gate'],
            'departure' => $booking['scheduled_departure']
        ] : null,
        'errors' => $errors
    ]);
}

function generateBoardingPassData($pdo, $bookingId) {
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
        'issued' => date('Y-m-d H:i:s'),
        'valid_until' => date('Y-m-d H:i:s', strtotime($booking['scheduled_departure']) + 3600) // Valid for 1 hour after departure
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
        'issued_at' => date('Y-m-d H:i:s'),
        'valid_until' => date('Y-m-d H:i:s', strtotime($booking['scheduled_departure']) + 3600)
    ];
}
?>
