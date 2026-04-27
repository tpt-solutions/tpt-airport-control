<?php
require_once __DIR__ . '/../models/Passenger.php';

class PassengerRepository {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function findAll($filters = [], $pagination = []) {
        $where = [];
        $params = [];

        // Build WHERE clause based on filters
        if (isset($filters['search'])) {
            $searchTerm = '%' . $filters['search'] . '%';
            $where[] = "(first_name ILIKE ? OR last_name ILIKE ? OR email ILIKE ? OR passport_number ILIKE ?)";
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        }

        if (isset($filters['flight_id'])) {
            $where[] = "EXISTS (SELECT 1 FROM bookings b WHERE b.passenger_id = passengers.id AND b.flight_id = ?)";
            $params[] = $filters['flight_id'];
        }

        if (isset($filters['nationality'])) {
            $where[] = "nationality = ?";
            $params[] = $filters['nationality'];
        }

        if (isset($filters['has_active_bookings'])) {
            if ($filters['has_active_bookings']) {
                $where[] = "EXISTS (SELECT 1 FROM bookings b WHERE b.passenger_id = passengers.id AND b.status IN ('confirmed', 'checked-in'))";
            } else {
                $where[] = "NOT EXISTS (SELECT 1 FROM bookings b WHERE b.passenger_id = passengers.id AND b.status IN ('confirmed', 'checked-in'))";
            }
        }

        $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

        // Pagination
        $limit = $pagination['limit'] ?? 50;
        $offset = $pagination['offset'] ?? 0;

        $sql = "
            SELECT
                p.*,
                COUNT(b.id) as total_bookings,
                COUNT(DISTINCT b.flight_id) as flight_count,
                COUNT(CASE WHEN b.status IN ('confirmed', 'checked-in') THEN 1 END) as active_bookings,
                MAX(b.created_at) as last_booking_date
            FROM passengers p
            LEFT JOIN bookings b ON p.id = b.passenger_id
            {$whereClause}
            GROUP BY p.id
            ORDER BY p.created_at DESC
            LIMIT ? OFFSET ?
        ";

        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $passengers = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $passengers[] = new Passenger($row);
        }

        return $passengers;
    }

    public function findById($id) {
        $stmt = $this->pdo->prepare("
            SELECT
                p.*,
                COUNT(b.id) as total_bookings,
                COUNT(DISTINCT b.flight_id) as flight_count,
                COUNT(CASE WHEN b.status IN ('confirmed', 'checked-in') THEN 1 END) as active_bookings,
                MAX(b.created_at) as last_booking_date
            FROM passengers p
            LEFT JOIN bookings b ON p.id = b.passenger_id
            WHERE p.id = ?
            GROUP BY p.id
        ");
        $stmt->execute([$id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? new Passenger($row) : null;
    }

    public function findByEmail($email) {
        $stmt = $this->pdo->prepare("
            SELECT
                p.*,
                COUNT(b.id) as total_bookings,
                COUNT(DISTINCT b.flight_id) as flight_count,
                COUNT(CASE WHEN b.status IN ('confirmed', 'checked-in') THEN 1 END) as active_bookings,
                MAX(b.created_at) as last_booking_date
            FROM passengers p
            LEFT JOIN bookings b ON p.id = b.passenger_id
            WHERE p.email = ?
            GROUP BY p.id
        ");
        $stmt->execute([$email]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? new Passenger($row) : null;
    }

    public function findByPassportNumber($passportNumber) {
        $stmt = $this->pdo->prepare("
            SELECT
                p.*,
                COUNT(b.id) as total_bookings,
                COUNT(DISTINCT b.flight_id) as flight_count,
                COUNT(CASE WHEN b.status IN ('confirmed', 'checked-in') THEN 1 END) as active_bookings,
                MAX(b.created_at) as last_booking_date
            FROM passengers p
            LEFT JOIN bookings b ON p.id = b.passenger_id
            WHERE p.passport_number = ?
            GROUP BY p.id
        ");
        $stmt->execute([$passportNumber]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? new Passenger($row) : null;
    }

    public function create(array $data) {
        $stmt = $this->pdo->prepare("
            INSERT INTO passengers (
                first_name, last_name, email, phone, passport_number,
                nationality, date_of_birth, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");

        $stmt->execute([
            $data['first_name'],
            $data['last_name'],
            $data['email'] ?? null,
            $data['phone'] ?? null,
            $data['passport_number'] ?? null,
            $data['nationality'] ?? null,
            $data['date_of_birth'] ?? null
        ]);

        $passengerId = $this->pdo->lastInsertId();

        // Return the created passenger
        return $this->findById($passengerId);
    }

    public function update($id, array $data) {
        $updateFields = [];
        $params = [];

        $allowedFields = [
            'first_name', 'last_name', 'email', 'phone',
            'passport_number', 'nationality', 'date_of_birth'
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
            UPDATE passengers SET " . implode(", ", $updateFields) . " WHERE id = ?
        ");

        return $stmt->execute($params);
    }

    public function delete($id) {
        $stmt = $this->pdo->prepare("DELETE FROM passengers WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function count($filters = []) {
        $where = [];
        $params = [];

        if (isset($filters['search'])) {
            $searchTerm = '%' . $filters['search'] . '%';
            $where[] = "(first_name ILIKE ? OR last_name ILIKE ? OR email ILIKE ? OR passport_number ILIKE ?)";
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        }

        if (isset($filters['flight_id'])) {
            $where[] = "EXISTS (SELECT 1 FROM bookings b WHERE b.passenger_id = passengers.id AND b.flight_id = ?)";
            $params[] = $filters['flight_id'];
        }

        if (isset($filters['nationality'])) {
            $where[] = "nationality = ?";
            $params[] = $filters['nationality'];
        }

        if (isset($filters['has_active_bookings'])) {
            if ($filters['has_active_bookings']) {
                $where[] = "EXISTS (SELECT 1 FROM bookings b WHERE b.passenger_id = passengers.id AND b.status IN ('confirmed', 'checked-in'))";
            } else {
                $where[] = "NOT EXISTS (SELECT 1 FROM bookings b WHERE b.passenger_id = passengers.id AND b.status IN ('confirmed', 'checked-in'))";
            }
        }

        $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

        $stmt = $this->pdo->prepare("SELECT COUNT(DISTINCT p.id) as total FROM passengers p {$whereClause}");
        $stmt->execute($params);

        return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }

    public function exists($id) {
        $stmt = $this->pdo->prepare("SELECT id FROM passengers WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    }

    public function existsByEmail($email) {
        $stmt = $this->pdo->prepare("SELECT id FROM passengers WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    }

    public function existsByPassport($passportNumber) {
        $stmt = $this->pdo->prepare("SELECT id FROM passengers WHERE passport_number = ?");
        $stmt->execute([$passportNumber]);
        return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    }

    public function hasActiveBookings($passengerId) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as active_bookings
            FROM bookings
            WHERE passenger_id = ? AND status IN ('confirmed', 'checked-in')
        ");
        $stmt->execute([$passengerId]);
        return $stmt->fetch(PDO::FETCH_ASSOC)['active_bookings'] > 0;
    }

    public function getRecentBookings($passengerId, $limit = 10) {
        $stmt = $this->pdo->prepare("
            SELECT
                b.id,
                b.booking_reference,
                b.status,
                b.total_amount,
                b.created_at,
                f.flight_number,
                f.origin,
                f.destination,
                f.scheduled_departure,
                a.name as airline_name
            FROM bookings b
            JOIN flights f ON b.flight_id = f.id
            JOIN airlines a ON f.airline_id = a.id
            WHERE b.passenger_id = ?
            ORDER BY b.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$passengerId, $limit]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPassengerStatistics() {
        $stmt = $this->pdo->prepare("
            SELECT
                COUNT(*) as total_passengers,
                COUNT(CASE WHEN date_of_birth IS NOT NULL AND EXTRACT(YEAR FROM AGE(date_of_birth)) >= 18 THEN 1 END) as adult_passengers,
                COUNT(CASE WHEN email IS NOT NULL AND email != '' THEN 1 END) as passengers_with_email,
                COUNT(CASE WHEN passport_number IS NOT NULL AND passport_number != '' THEN 1 END) as passengers_with_passport,
                AVG(EXTRACT(YEAR FROM AGE(date_of_birth))) as average_age
            FROM passengers
        ");
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getNationalityDistribution() {
        $stmt = $this->pdo->prepare("
            SELECT
                nationality,
                COUNT(*) as count
            FROM passengers
            WHERE nationality IS NOT NULL AND nationality != ''
            GROUP BY nationality
            ORDER BY count DESC
            LIMIT 10
        ");
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPassengersByFlight($flightId) {
        $stmt = $this->pdo->prepare("
            SELECT
                p.*,
                b.booking_reference,
                b.status as booking_status,
                b.seat_number
            FROM passengers p
            JOIN bookings b ON p.id = b.passenger_id
            WHERE b.flight_id = ? AND b.status != 'cancelled'
            ORDER BY p.last_name, p.first_name
        ");
        $stmt->execute([$flightId]);

        $passengers = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $passengers[] = $row;
        }

        return $passengers;
    }
}
?>
