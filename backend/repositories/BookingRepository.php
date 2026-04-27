<?php
require_once __DIR__ . '/../models/Booking.php';

class BookingRepository {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function findAll($filters = [], $pagination = []) {
        $where = [];
        $params = [];

        // Build WHERE clause based on filters
        if (isset($filters['status'])) {
            $where[] = "b.status = ?";
            $params[] = $filters['status'];
        }
        if (isset($filters['passenger_id'])) {
            $where[] = "b.passenger_id = ?";
            $params[] = $filters['passenger_id'];
        }
        if (isset($filters['flight_id'])) {
            $where[] = "b.flight_id = ?";
            $params[] = $filters['flight_id'];
        }
        if (isset($filters['payment_status'])) {
            $where[] = "b.payment_status = ?";
            $params[] = $filters['payment_status'];
        }
        if (isset($filters['booking_reference'])) {
            $where[] = "b.booking_reference = ?";
            $params[] = $filters['booking_reference'];
        }
        if (isset($filters['search'])) {
            $searchTerm = '%' . $filters['search'] . '%';
            $where[] = "(b.booking_reference ILIKE ? OR p.first_name ILIKE ? OR p.last_name ILIKE ? OR p.email ILIKE ? OR f.flight_number ILIKE ?)";
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        }

        $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

        // Pagination
        $limit = $pagination['limit'] ?? 50;
        $offset = $pagination['offset'] ?? 0;

        $sql = "
            SELECT
                b.*,
                p.first_name as passenger_first_name,
                p.last_name as passenger_last_name,
                p.email as passenger_email,
                p.phone as passenger_phone,
                p.passport_number as passenger_passport_number,
                p.nationality as passenger_nationality,
                p.date_of_birth as passenger_date_of_birth,
                f.flight_number,
                f.origin as flight_origin,
                f.destination as flight_destination,
                f.scheduled_departure as flight_scheduled_departure,
                f.scheduled_arrival as flight_scheduled_arrival,
                f.actual_departure as flight_actual_departure,
                f.actual_arrival as flight_actual_arrival,
                f.status as flight_status,
                f.gate as flight_gate,
                f.terminal as flight_terminal,
                a.name as airline_name,
                a.code as airline_code,
                ac.model as aircraft_model,
                ac.registration as aircraft_registration
            FROM bookings b
            JOIN passengers p ON b.passenger_id = p.id
            JOIN flights f ON b.flight_id = f.id
            JOIN airlines a ON f.airline_id = a.id
            JOIN aircraft ac ON f.aircraft_id = ac.id
            {$whereClause}
            ORDER BY b.created_at DESC
            LIMIT ? OFFSET ?
        ";

        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $bookings = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $bookings[] = new Booking($row);
        }

        return $bookings;
    }

    public function findById($id) {
        $stmt = $this->pdo->prepare("
            SELECT
                b.*,
                p.first_name as passenger_first_name,
                p.last_name as passenger_last_name,
                p.email as passenger_email,
                p.phone as passenger_phone,
                p.passport_number as passenger_passport_number,
                p.nationality as passenger_nationality,
                p.date_of_birth as passenger_date_of_birth,
                f.flight_number,
                f.origin as flight_origin,
                f.destination as flight_destination,
                f.scheduled_departure as flight_scheduled_departure,
                f.scheduled_arrival as flight_scheduled_arrival,
                f.actual_departure as flight_actual_departure,
                f.actual_arrival as flight_actual_arrival,
                f.status as flight_status,
                f.gate as flight_gate,
                f.terminal as flight_terminal,
                a.name as airline_name,
                a.code as airline_code,
                ac.model as aircraft_model,
                ac.registration as aircraft_registration
            FROM bookings b
            JOIN passengers p ON b.passenger_id = p.id
            JOIN flights f ON b.flight_id = f.id
            JOIN airlines a ON f.airline_id = a.id
            JOIN aircraft ac ON f.aircraft_id = ac.id
            WHERE b.id = ?
        ");
        $stmt->execute([$id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? new Booking($row) : null;
    }

    public function findByBookingReference($bookingReference) {
        $stmt = $this->pdo->prepare("
            SELECT
                b.*,
                p.first_name as passenger_first_name,
                p.last_name as passenger_last_name,
                p.email as passenger_email,
                p.phone as passenger_phone,
                p.passport_number as passenger_passport_number,
                p.nationality as passenger_nationality,
                p.date_of_birth as passenger_date_of_birth,
                f.flight_number,
                f.origin as flight_origin,
                f.destination as flight_destination,
                f.scheduled_departure as flight_scheduled_departure,
                f.scheduled_arrival as flight_scheduled_arrival,
                f.actual_departure as flight_actual_departure,
                f.actual_arrival as flight_actual_arrival,
                f.status as flight_status,
                f.gate as flight_gate,
                f.terminal as flight_terminal,
                a.name as airline_name,
                a.code as airline_code,
                ac.model as aircraft_model,
                ac.registration as aircraft_registration
            FROM bookings b
            JOIN passengers p ON b.passenger_id = p.id
            JOIN flights f ON b.flight_id = f.id
            JOIN airlines a ON f.airline_id = a.id
            JOIN aircraft ac ON f.aircraft_id = ac.id
            WHERE b.booking_reference = ?
        ");
        $stmt->execute([$bookingReference]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? new Booking($row) : null;
    }

    public function findByPassengerId($passengerId, $filters = []) {
        $filters['passenger_id'] = $passengerId;
        return $this->findAll($filters);
    }

    public function findByFlightId($flightId, $filters = []) {
        $filters['flight_id'] = $flightId;
        return $this->findAll($filters);
    }

    public function create(array $data) {
        // Generate unique booking reference
        $bookingReference = $this->generateBookingReference();

        $stmt = $this->pdo->prepare("
            INSERT INTO bookings (
                passenger_id, flight_id, seat_number, booking_reference,
                status, total_amount, currency, payment_status,
                created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");

        $stmt->execute([
            $data['passenger_id'],
            $data['flight_id'],
            $data['seat_number'] ?? null,
            $bookingReference,
            $data['status'] ?? 'confirmed',
            $data['total_amount'] ?? 0.00,
            $data['currency'] ?? 'USD',
            $data['payment_status'] ?? 'paid'
        ]);

        $bookingId = $this->pdo->lastInsertId();

        // Return the created booking
        return $this->findById($bookingId);
    }

    public function update($id, array $data) {
        $updateFields = [];
        $params = [];

        $allowedFields = [
            'seat_number', 'status', 'total_amount', 'payment_status'
        ];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateFields[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }

        if (empty($updateFields)) {
            return false;
        }

        $updateFields[] = "updated_at = CURRENT_TIMESTAMP";
        $params[] = $id;

        $stmt = $this->pdo->prepare("
            UPDATE bookings SET " . implode(", ", $updateFields) . " WHERE id = ?
        ");

        return $stmt->execute($params);
    }

    public function cancel($id) {
        $stmt = $this->pdo->prepare("
            UPDATE bookings SET status = 'cancelled', updated_at = CURRENT_TIMESTAMP WHERE id = ?
        ");
        return $stmt->execute([$id]);
    }

    public function delete($id) {
        $stmt = $this->pdo->prepare("DELETE FROM bookings WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function count($filters = []) {
        $where = [];
        $params = [];

        if (isset($filters['status'])) {
            $where[] = "status = ?";
            $params[] = $filters['status'];
        }
        if (isset($filters['passenger_id'])) {
            $where[] = "passenger_id = ?";
            $params[] = $filters['passenger_id'];
        }
        if (isset($filters['flight_id'])) {
            $where[] = "flight_id = ?";
            $params[] = $filters['flight_id'];
        }
        if (isset($filters['payment_status'])) {
            $where[] = "payment_status = ?";
            $params[] = $filters['payment_status'];
        }

        $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

        $stmt = $this->pdo->prepare("SELECT COUNT(*) as total FROM bookings {$whereClause}");
        $stmt->execute($params);

        return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }

    public function exists($passengerId, $flightId) {
        $stmt = $this->pdo->prepare("
            SELECT id FROM bookings
            WHERE passenger_id = ? AND flight_id = ? AND status != 'cancelled'
        ");
        $stmt->execute([$passengerId, $flightId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    }

    public function getRevenueByDateRange($startDate, $endDate) {
        $stmt = $this->pdo->prepare("
            SELECT
                DATE(created_at) as date,
                COUNT(*) as booking_count,
                SUM(total_amount) as total_revenue,
                currency
            FROM bookings
            WHERE created_at BETWEEN ? AND ?
            AND payment_status = 'paid'
            AND status != 'cancelled'
            GROUP BY DATE(created_at), currency
            ORDER BY date DESC
        ");
        $stmt->execute([$startDate, $endDate]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getBookingStats() {
        $stmt = $this->pdo->prepare("
            SELECT
                status,
                COUNT(*) as count
            FROM bookings
            GROUP BY status
        ");
        $stmt->execute();

        $stats = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $stats[$row['status']] = (int)$row['count'];
        }

        return $stats;
    }

    private function generateBookingReference() {
        do {
            $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            $reference = '';

            for ($i = 0; $i < 6; $i++) {
                $reference .= $characters[rand(0, strlen($characters) - 1)];
            }

            // Check if reference already exists
            $stmt = $this->pdo->prepare("SELECT id FROM bookings WHERE booking_reference = ?");
            $stmt->execute([$reference]);
            $exists = $stmt->fetch(PDO::FETCH_ASSOC);
        } while ($exists);

        return $reference;
    }
}
?>
