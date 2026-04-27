<?php
use Ratchet\ConnectionInterface;
use TPT\FlightControl\Logger;

require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/JWT.php';
require_once __DIR__ . '/Auth.php';

class WebSocketMessageHandler {
    private $pdo;
    private $flightBroadcastService;
    private $auth;

    public function __construct($pdo, $flightBroadcastService) {
        $this->pdo = $pdo;
        $this->flightBroadcastService = $flightBroadcastService;
        Auth::init($pdo);
    }

    public function handleMessage(ConnectionInterface $from, $msg) {
        try {
            $data = json_decode($msg, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->sendError($from, 'Invalid JSON message');
                return;
            }

            Logger::debug('WebSocket message received: ' . $msg);

            switch ($data['type']) {
                case 'subscribe_flights':
                    $this->handleFlightSubscription($from, $data);
                    break;

                case 'unsubscribe_flights':
                    $this->handleFlightUnsubscription($from, $data);
                    break;

                case 'subscribe_bookings':
                    $this->handleBookingSubscription($from, $data);
                    break;

                case 'unsubscribe_bookings':
                    $this->handleBookingUnsubscription($from, $data);
                    break;

                case 'subscribe_weather':
                    $this->handleWeatherSubscription($from, $data);
                    break;

                case 'unsubscribe_weather':
                    $this->handleWeatherUnsubscription($from, $data);
                    break;

                case 'ping':
                    $this->handlePing($from);
                    break;

                case 'authenticate':
                    $this->handleAuthentication($from, $data);
                    break;

                default:
                    $this->sendError($from, 'Unknown message type: ' . $data['type']);
                    break;
            }
        } catch (Exception $e) {
            Logger::error('WebSocket message error: ' . $e->getMessage());
            $this->sendError($from, 'Internal server error');
        }
    }

    private function handleFlightSubscription(ConnectionInterface $conn, $data) {
        // Store subscription info in connection
        $conn->flightSubscription = $data['filters'] ?? [];

        $this->sendSuccess($conn, 'subscription_confirmed', 'Subscribed to flight updates', [
            'filters' => $conn->flightSubscription
        ]);

        // Send current flight data
        $this->sendCurrentFlights($conn);
    }

    private function handleFlightUnsubscription(ConnectionInterface $conn, $data) {
        unset($conn->flightSubscription);

        $this->sendSuccess($conn, 'unsubscription_confirmed', 'Unsubscribed from flight updates');
    }

    private function handleBookingSubscription(ConnectionInterface $conn, $data) {
        // Check authentication
        if (!$this->isAuthenticated($conn)) {
            $this->sendError($conn, 'Authentication required for booking subscription');
            return;
        }

        $conn->bookingSubscription = $data['filters'] ?? [];

        $this->sendSuccess($conn, 'subscription_confirmed', 'Subscribed to booking updates', [
            'filters' => $conn->bookingSubscription
        ]);

        // Send current booking data for authenticated user
        $this->sendCurrentBookings($conn);
    }

    private function handleBookingUnsubscription(ConnectionInterface $conn, $data) {
        unset($conn->bookingSubscription);

        $this->sendSuccess($conn, 'unsubscription_confirmed', 'Unsubscribed from booking updates');
    }

    private function handleWeatherSubscription(ConnectionInterface $conn, $data) {
        $conn->weatherSubscription = $data['filters'] ?? [];

        $this->sendSuccess($conn, 'subscription_confirmed', 'Subscribed to weather updates', [
            'filters' => $conn->weatherSubscription
        ]);

        // Send current weather data
        $this->sendCurrentWeather($conn);
    }

    private function handleWeatherUnsubscription(ConnectionInterface $conn, $data) {
        unset($conn->weatherSubscription);

        $this->sendSuccess($conn, 'unsubscription_confirmed', 'Unsubscribed from weather updates');
    }

    private function handlePing(ConnectionInterface $conn) {
        $conn->send(json_encode([
            'type' => 'pong',
            'timestamp' => time()
        ]));
    }

    private function handleAuthentication(ConnectionInterface $conn, $data) {
        $token = $data['token'] ?? '';

        if (empty($token)) {
            $this->sendError($conn, 'Token required');
            return;
        }

        $userData = Auth::validateToken($token);
        
        if (!$userData) {
            $this->sendError($conn, 'Invalid or expired token');
            return;
        }

        // Get full user information
        try {
            $stmt = $this->pdo->prepare("
                SELECT u.*, r.name as role_name, r.permissions as role_permissions
                FROM users u
                LEFT JOIN roles r ON u.role_id = r.id
                WHERE u.id = ? AND u.is_active = true
            ");
            $stmt->execute([$userData['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                $this->sendError($conn, 'User not found or disabled');
                return;
            }

            // Store authentication info
            $conn->authenticated = true;
            $conn->userId = $user['id'];
            $conn->userRole = $user['role_name'];
            $conn->username = $user['username'];
            $conn->userToken = $token;

            Logger::info("WebSocket connection authenticated: {$conn->clientId} as {$conn->username} ({$conn->userRole})");

            $this->sendSuccess($conn, 'authentication_success', 'Authenticated successfully', [
                'user_id' => $user['id'],
                'username' => $user['username'],
                'role' => $user['role_name']
            ]);

        } catch (Exception $e) {
            Logger::error('WebSocket authentication error: ' . $e->getMessage());
            $this->sendError($conn, 'Authentication failed');
        }
    }

    private function sendCurrentFlights(ConnectionInterface $conn) {
        try {
            $filters = $conn->flightSubscription ?? [];
            $where = [];
            $params = [];

            if (isset($filters['status'])) {
                $where[] = "status = ?";
                $params[] = $filters['status'];
            }
            if (isset($filters['origin'])) {
                $where[] = "origin = ?";
                $params[] = $filters['origin'];
            }
            if (isset($filters['destination'])) {
                $where[] = "destination = ?";
                $params[] = $filters['destination'];
            }
            if (isset($filters['airline'])) {
                $where[] = "airline_code = ?";
                $params[] = $filters['airline'];
            }

            $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

            $stmt = $this->pdo->prepare("
                SELECT
                    id, flight_number, airline_code, origin, destination,
                    scheduled_departure, scheduled_arrival,
                    actual_departure, actual_arrival,
                    status, gate, terminal, aircraft_type, updated_at
                FROM flights
                {$whereClause}
                ORDER BY scheduled_departure ASC
                LIMIT 100
            ");
            $stmt->execute($params);
            $flights = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $conn->send(json_encode([
                'type' => 'flight_update',
                'flights' => $flights,
                'timestamp' => time()
            ]));
        } catch (Exception $e) {
            Logger::error('Error sending current flights: ' . $e->getMessage());
            $this->sendError($conn, 'Failed to load flight data');
        }
    }

    private function sendCurrentBookings(ConnectionInterface $conn) {
        try {
            if (!$this->isAuthenticated($conn)) {
                return;
            }

            // Get user ID from token (simplified - in production use proper JWT decoding)
            $userId = $this->getUserIdFromToken($conn->userToken);

            if (!$userId) {
                $this->sendError($conn, 'Invalid authentication token');
                return;
            }

            $filters = $conn->bookingSubscription ?? [];
            $where = ["b.passenger_id = p.id AND p.user_id = ?"];
            $params = [$userId];

            if (isset($filters['status'])) {
                $where[] = "b.status = ?";
                $params[] = $filters['status'];
            }

            $whereClause = "WHERE " . implode(" AND ", $where);

            $stmt = $this->pdo->prepare("
                SELECT
                    b.id, b.flight_id, b.status, b.seat_number, b.booking_reference,
                    f.flight_number, f.origin, f.destination,
                    f.scheduled_departure, f.scheduled_arrival, f.status as flight_status,
                    b.created_at, b.updated_at
                FROM bookings b
                JOIN flights f ON b.flight_id = f.id
                JOIN passengers p ON b.passenger_id = p.id
                {$whereClause}
                ORDER BY f.scheduled_departure ASC
                LIMIT 50
            ");
            $stmt->execute($params);
            $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $conn->send(json_encode([
                'type' => 'booking_update',
                'bookings' => $bookings,
                'timestamp' => time()
            ]));
        } catch (Exception $e) {
            Logger::error('Error sending current bookings: ' . $e->getMessage());
            $this->sendError($conn, 'Failed to load booking data');
        }
    }

    private function sendCurrentWeather(ConnectionInterface $conn) {
        try {
            $filters = $conn->weatherSubscription ?? [];
            $where = [];
            $params = [];

            if (isset($filters['airport'])) {
                $where[] = "airport_code = ?";
                $params[] = $filters['airport'];
            }

            $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

            $stmt = $this->pdo->prepare("
                SELECT
                    id, airport_code, temperature, humidity, wind_speed,
                    wind_direction, visibility, conditions, pressure,
                    updated_at, next_update
                FROM weather_data
                {$whereClause}
                ORDER BY updated_at DESC
                LIMIT 20
            ");
            $stmt->execute($params);
            $weather = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $conn->send(json_encode([
                'type' => 'weather_update',
                'weather' => $weather,
                'timestamp' => time()
            ]));
        } catch (Exception $e) {
            Logger::error('Error sending current weather: ' . $e->getMessage());
            $this->sendError($conn, 'Failed to load weather data');
        }
    }

    private function isAuthenticated(ConnectionInterface $conn) {
        return isset($conn->authenticated) && $conn->authenticated === true;
    }

    private function getUserIdFromToken($token) {
        $userData = Auth::validateToken($token);
        return $userData ? $userData['user_id'] : null;
    }

    private function sendError(ConnectionInterface $conn, $message) {
        $conn->send(json_encode([
            'type' => 'error',
            'message' => $message,
            'timestamp' => time()
        ]));
    }

    private function sendSuccess(ConnectionInterface $conn, $type, $message, $data = null) {
        $response = [
            'type' => $type,
            'message' => $message,
            'timestamp' => time()
        ];

        if ($data) {
            $response['data'] = $data;
        }

        $conn->send(json_encode($response));
    }
}
?>
