<?php
require_once __DIR__ . '/../models/Flight.php';
require_once __DIR__ . '/../src/Cache.php';
require_once __DIR__ . '/../src/Logger.php';

class FlightRepository {
    private $pdo;
    private $cache;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->cache = Cache::getInstance();
    }

    public function findAll($filters = [], $pagination = []) {
        $where = [];
        $params = [];

        // Build WHERE clause
        if (isset($filters['status'])) {
            $where[] = "f.status = ?";
            $params[] = $filters['status'];
        }
        if (isset($filters['origin'])) {
            $where[] = "f.origin = ?";
            $params[] = $filters['origin'];
        }
        if (isset($filters['destination'])) {
            $where[] = "f.destination = ?";
            $params[] = $filters['destination'];
        }
        if (isset($filters['airline_id'])) {
            $where[] = "f.airline_id = ?";
            $params[] = $filters['airline_id'];
        }
        if (isset($filters['date_from'])) {
            $where[] = "f.scheduled_departure >= ?";
            $params[] = $filters['date_from'];
        }
        if (isset($filters['date_to'])) {
            $where[] = "f.scheduled_departure <= ?";
            $params[] = $filters['date_to'];
        }

        $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

        // Pagination
        $limit = $pagination['limit'] ?? 50;
        $offset = $pagination['offset'] ?? 0;

        $sql = "
            SELECT
                f.*,
                a.name as airline_name,
                a.code as airline_code,
                ac.model as aircraft_model,
                ac.registration as aircraft_registration,
                ac.capacity as aircraft_capacity
            FROM flights f
            JOIN airlines a ON f.airline_id = a.id
            JOIN aircraft ac ON f.aircraft_id = ac.id
            {$whereClause}
            ORDER BY f.scheduled_departure ASC
            LIMIT ? OFFSET ?
        ";

        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $flights = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $flights[] = new Flight($row);
        }

        return $flights;
    }

    public function findById($id) {
        $cacheKey = "flight_{$id}";

        return $this->cache->remember($cacheKey, 300, function() use ($id) {
            $stmt = $this->pdo->prepare("
                SELECT
                    f.*,
                    a.name as airline_name,
                    a.code as airline_code,
                    ac.model as aircraft_model,
                    ac.registration as aircraft_registration,
                    ac.capacity as aircraft_capacity
                FROM flights f
                JOIN airlines a ON f.airline_id = a.id
                JOIN aircraft ac ON f.aircraft_id = ac.id
                WHERE f.id = ?
            ");
            $stmt->execute([$id]);

            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? new Flight($row) : null;
        });
    }

    public function findByFlightNumber($flightNumber) {
        $stmt = $this->pdo->prepare("
            SELECT
                f.*,
                a.name as airline_name,
                a.code as airline_code,
                ac.model as aircraft_model,
                ac.registration as aircraft_registration,
                ac.capacity as aircraft_capacity
            FROM flights f
            JOIN airlines a ON f.airline_id = a.id
            JOIN aircraft ac ON f.aircraft_id = ac.id
            WHERE f.flight_number = ?
        ");
        $stmt->execute([$flightNumber]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? new Flight($row) : null;
    }

    public function search($searchTerm, $filters = []) {
        $where = [];
        $params = [];

        // Text search
        if (!empty($searchTerm)) {
            $where[] = "(f.flight_number ILIKE ? OR a.name ILIKE ? OR f.origin ILIKE ? OR f.destination ILIKE ?)";
            $searchParam = '%' . $searchTerm . '%';
            $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
        }

        // Additional filters
        if (isset($filters['status'])) {
            $where[] = "f.status = ?";
            $params[] = $filters['status'];
        }
        if (isset($filters['airline_id'])) {
            $where[] = "f.airline_id = ?";
            $params[] = $filters['airline_id'];
        }

        $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

        $stmt = $this->pdo->prepare("
            SELECT
                f.*,
                a.name as airline_name,
                a.code as airline_code,
                ac.model as aircraft_model,
                ac.registration as aircraft_registration,
                ac.capacity as aircraft_capacity
            FROM flights f
            JOIN airlines a ON f.airline_id = a.id
            JOIN aircraft ac ON f.aircraft_id = ac.id
            {$whereClause}
            ORDER BY f.scheduled_departure ASC
            LIMIT 100
        ");
        $stmt->execute($params);

        $flights = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $flights[] = new Flight($row);
        }

        return $flights;
    }

    public function create(array $data) {
        $stmt = $this->pdo->prepare("
            INSERT INTO flights (
                flight_number, airline_id, aircraft_id, origin, destination,
                scheduled_departure, scheduled_arrival, status, gate, terminal,
                created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");

        $stmt->execute([
            $data['flight_number'],
            $data['airline_id'],
            $data['aircraft_id'],
            $data['origin'],
            $data['destination'],
            $data['scheduled_departure'],
            $data['scheduled_arrival'],
            $data['status'] ?? 'scheduled',
            $data['gate'] ?? null,
            $data['terminal'] ?? null
        ]);

        $flightId = $this->pdo->lastInsertId();

        // Clear relevant caches
        $this->clearFlightCaches();

        // Return the created flight
        return $this->findById($flightId);
    }

    public function update($id, array $data) {
        $updateFields = [];
        $params = [];

        $allowedFields = [
            'flight_number', 'airline_id', 'aircraft_id', 'origin', 'destination',
            'scheduled_departure', 'scheduled_arrival', 'actual_departure',
            'actual_arrival', 'status', 'gate', 'terminal'
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
            UPDATE flights SET " . implode(", ", $updateFields) . " WHERE id = ?
        ");

        $result = $stmt->execute($params);

        if ($result) {
            // Clear specific flight cache and related caches
            $this->cache->delete("flight_{$id}");
            $this->clearFlightCaches();
        }

        return $result;
    }

    public function delete($id) {
        // Check if flight has bookings
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as booking_count FROM bookings WHERE flight_id = ?");
        $stmt->execute([$id]);
        $bookingCount = $stmt->fetch(PDO::FETCH_ASSOC)['booking_count'];

        if ($bookingCount > 0) {
            throw new Exception('Cannot delete flight with existing bookings');
        }

        $stmt = $this->pdo->prepare("DELETE FROM flights WHERE id = ?");
        $result = $stmt->execute([$id]);

        if ($result) {
            // Clear specific flight cache and related caches
            $this->cache->delete("flight_{$id}");
            $this->clearFlightCaches();
        }

        return $result;
    }

    public function assignGate($flightId, $gate) {
        // Check if gate is already assigned to another flight at the same time
        $stmt = $this->pdo->prepare("
            SELECT f.id, f.flight_number, f.scheduled_departure, f.scheduled_arrival
            FROM flights f
            WHERE f.gate = ?
            AND f.id != ?
            AND (
                (f.scheduled_departure BETWEEN
                    (SELECT scheduled_departure - INTERVAL '2 hours' FROM flights WHERE id = ?) AND
                    (SELECT scheduled_arrival + INTERVAL '2 hours' FROM flights WHERE id = ?)
                ) OR
                (f.scheduled_arrival BETWEEN
                    (SELECT scheduled_departure - INTERVAL '2 hours' FROM flights WHERE id = ?) AND
                    (SELECT scheduled_arrival + INTERVAL '2 hours' FROM flights WHERE id = ?)
                )
            )
        ");
        $stmt->execute([$gate, $flightId, $flightId, $flightId, $flightId, $flightId]);
        $conflictingFlight = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($conflictingFlight) {
            throw new Exception('Gate already assigned to another flight: ' . $conflictingFlight['flight_number']);
        }

        // Assign gate
        $stmt = $this->pdo->prepare("UPDATE flights SET gate = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $result = $stmt->execute([$gate, $flightId]);

        if ($result) {
            // Clear flight cache since gate assignment affects flight data
            $this->cache->delete("flight_{$flightId}");
        }

        return $result;
    }

    public function assignTerminal($flightId, $terminal) {
        $stmt = $this->pdo->prepare("UPDATE flights SET terminal = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $result = $stmt->execute([$terminal, $flightId]);

        if ($result) {
            // Clear flight cache since terminal assignment affects flight data
            $this->cache->delete("flight_{$flightId}");
        }

        return $result;
    }

    public function count($filters = []) {
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

        $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

        $stmt = $this->pdo->prepare("SELECT COUNT(*) as total FROM flights {$whereClause}");
        $stmt->execute($params);

        return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }

    public function getActiveFlights() {
        return $this->findAll(['status' => ['scheduled', 'boarding', 'departed']]);
    }

    public function getFlightsByTimeRange($startTime, $endTime) {
        return $this->findAll([
            'date_from' => $startTime,
            'date_to' => $endTime
        ]);
    }

    /**
     * Clear flight-related caches
     * Called when flight data changes to ensure cache consistency
     */
    private function clearFlightCaches() {
        // Clear any cached flight lists or counts
        // In a more sophisticated implementation, you might use cache tags
        // For now, we'll clear specific known cache keys

        // Note: Individual flight caches are cleared separately in update/delete methods
        // This method can be extended to clear aggregated caches like:
        // - active_flights
        // - flight_counts
        // - search_results
    }
}
?>
