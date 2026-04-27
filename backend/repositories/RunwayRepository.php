<?php
/**
 * Runway Repository
 *
 * Handles all database operations for runway management
 */

class RunwayRepository
{
    private $pdo;
    private $logger;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $this->logger = Logger::getInstance();
    }

    /**
     * Find all runways with optional filters
     */
    public function findAll(array $filters = [], array $pagination = [])
    {
        $where = [];
        $params = [];

        // Build filters
        if (isset($filters['status'])) {
            $where[] = "r.status = ?";
            $params[] = $filters['status'];
        }

        if (isset($filters['usage_type'])) {
            $where[] = "r.usage_type = ?";
            $params[] = $filters['usage_type'];
        }

        if (isset($filters['surface_type'])) {
            $where[] = "r.surface_type = ?";
            $params[] = $filters['surface_type'];
        }

        $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

        // Build pagination
        $limit = $pagination['limit'] ?? 50;
        $offset = $pagination['offset'] ?? 0;
        $limitClause = "LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $sql = "
            SELECT
                r.*,
                COALESCE(ra.flight_id, NULL) as assigned_flight_id,
                COALESCE(f.flight_number, NULL) as assigned_flight_number,
                COALESCE(ra.operation_type, NULL) as operation_type,
                COALESCE(ra.assigned_at, NULL) as assigned_at,
                COALESCE(ra.expected_release, NULL) as expected_release
            FROM runways r
            LEFT JOIN runway_assignments ra ON r.id = ra.runway_id AND ra.status = 'active'
            LEFT JOIN flights f ON ra.flight_id = f.id
            {$whereClause}
            ORDER BY r.runway_number ASC
            {$limitClause}
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Find runway by ID
     */
    public function findById($id)
    {
        $stmt = $this->pdo->prepare("
            SELECT
                r.*,
                COALESCE(ra.flight_id, NULL) as assigned_flight_id,
                COALESCE(f.flight_number, NULL) as assigned_flight_number,
                COALESCE(ra.operation_type, NULL) as operation_type,
                COALESCE(ra.assigned_at, NULL) as assigned_at,
                COALESCE(ra.expected_release, NULL) as expected_release,
                COALESCE(ra.status, NULL) as assignment_status
            FROM runways r
            LEFT JOIN runway_assignments ra ON r.id = ra.runway_id AND ra.status = 'active'
            LEFT JOIN flights f ON ra.flight_id = f.id
            WHERE r.id = ?
        ");
        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Find runway by runway number
     */
    public function findByRunwayNumber($runwayNumber)
    {
        $stmt = $this->pdo->prepare("
            SELECT
                r.*,
                COALESCE(ra.flight_id, NULL) as assigned_flight_id,
                COALESCE(f.flight_number, NULL) as assigned_flight_number,
                COALESCE(ra.operation_type, NULL) as operation_type,
                COALESCE(ra.assigned_at, NULL) as assigned_at,
                COALESCE(ra.expected_release, NULL) as expected_release
            FROM runways r
            LEFT JOIN runway_assignments ra ON r.id = ra.runway_id AND ra.status = 'active'
            LEFT JOIN flights f ON ra.flight_id = f.id
            WHERE r.runway_number = ?
        ");
        $stmt->execute([$runwayNumber]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Find available runways for specific operation
     */
    public function findAvailableForOperation($operationType, array $windConditions = [])
    {
        $where = ["r.status = 'active'"];
        $params = [];

        // Check usage type compatibility
        if ($operationType === 'departure') {
            $where[] = "(r.usage_type = 'departure' OR r.usage_type = 'both')";
        } elseif ($operationType === 'arrival') {
            $where[] = "(r.usage_type = 'arrival' OR r.usage_type = 'both')";
        }

        // Check wind conditions
        if (!empty($windConditions)) {
            $crosswind = $windConditions['crosswind_kts'] ?? 0;
            $tailwind = $windConditions['tailwind_kts'] ?? 0;

            if ($crosswind > 0) {
                $where[] = "r.max_crosswind_kts >= ?";
                $params[] = $crosswind;
            }

            if ($tailwind > 0) {
                $where[] = "r.max_tailwind_kts >= ?";
                $params[] = $tailwind;
            }
        }

        // Exclude currently assigned runways
        $where[] = "ra.id IS NULL";

        $whereClause = implode(" AND ", $where);

        $stmt = $this->pdo->prepare("
            SELECT r.*
            FROM runways r
            LEFT JOIN runway_assignments ra ON r.id = ra.runway_id AND ra.status = 'active'
            WHERE {$whereClause}
            ORDER BY r.length_ft DESC, r.runway_number ASC
        ");
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Find active runways (not under maintenance or closed)
     */
    public function findActive()
    {
        $stmt = $this->pdo->prepare("
            SELECT
                r.*,
                COALESCE(ra.flight_id, NULL) as assigned_flight_id,
                COALESCE(f.flight_number, NULL) as assigned_flight_number,
                COALESCE(ra.operation_type, NULL) as operation_type,
                COALESCE(ra.assigned_at, NULL) as assigned_at,
                COALESCE(ra.expected_release, NULL) as expected_release
            FROM runways r
            LEFT JOIN runway_assignments ra ON r.id = ra.runway_id AND ra.status = 'active'
            LEFT JOIN flights f ON ra.flight_id = f.id
            WHERE r.status = 'active'
            ORDER BY r.runway_number ASC
        ");
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Create new runway
     */
    public function create(array $data)
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO runways (
                runway_number, length_ft, width_ft, surface_type,
                usage_type, max_crosswind_kts, max_tailwind_kts,
                status, notes, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");

        $stmt->execute([
            $data['runway_number'],
            $data['length_ft'],
            $data['width_ft'] ?? null,
            $data['surface_type'] ?? 'asphalt',
            $data['usage_type'] ?? 'both',
            $data['max_crosswind_kts'] ?? 20,
            $data['max_tailwind_kts'] ?? 10,
            $data['status'] ?? 'active',
            $data['notes'] ?? null
        ]);

        $runwayId = $this->pdo->lastInsertId();

        $this->logger->info('Runway created', [
            'runway_id' => $runwayId,
            'runway_number' => $data['runway_number']
        ]);

        return $runwayId;
    }

    /**
     * Update runway
     */
    public function update($id, array $data)
    {
        $updates = [];
        $params = [];

        $allowedFields = [
            'runway_number', 'length_ft', 'width_ft', 'surface_type',
            'usage_type', 'max_crosswind_kts', 'max_tailwind_kts',
            'status', 'notes'
        ];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updates[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }

        if (empty($updates)) {
            return false;
        }

        $params[] = $id;
        $updates[] = "updated_at = CURRENT_TIMESTAMP";

        $stmt = $this->pdo->prepare("UPDATE runways SET " . implode(', ', $updates) . " WHERE id = ?");
        $result = $stmt->execute($params);

        if ($result) {
            $this->logger->info('Runway updated', ['runway_id' => $id]);
        }

        return $result;
    }

    /**
     * Delete runway
     */
    public function delete($id)
    {
        // Check if runway has any assignments
        if ($this->hasAssignments($id)) {
            throw new Exception('Cannot delete runway with existing assignments');
        }

        $stmt = $this->pdo->prepare("DELETE FROM runways WHERE id = ?");
        $result = $stmt->execute([$id]);

        if ($result) {
            $this->logger->info('Runway deleted', ['runway_id' => $id]);
        }

        return $result;
    }

    /**
     * Check if runway exists
     */
    public function exists($id)
    {
        $stmt = $this->pdo->prepare("SELECT id FROM runways WHERE id = ?");
        $stmt->execute([$id]);

        return $stmt->fetch() !== false;
    }

    /**
     * Check if runway number exists
     */
    public function runwayNumberExists($runwayNumber, $excludeId = null)
    {
        $sql = "SELECT id FROM runways WHERE runway_number = ?";
        $params = [$runwayNumber];

        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch() !== false;
    }

    /**
     * Check if runway has any assignments
     */
    public function hasAssignments($runwayId)
    {
        $stmt = $this->pdo->prepare("SELECT id FROM runway_assignments WHERE runway_id = ? LIMIT 1");
        $stmt->execute([$runwayId]);

        return $stmt->fetch() !== false;
    }

    /**
     * Check if runway is currently assigned
     */
    public function isAssigned($runwayId)
    {
        $stmt = $this->pdo->prepare("SELECT id FROM runway_assignments WHERE runway_id = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$runwayId]);

        return $stmt->fetch() !== false;
    }

    /**
     * Assign runway to flight
     */
    public function assignToFlight($runwayId, $flightId, $operationType = 'departure', $expectedRelease = null)
    {
        // Check if runway is available
        if ($this->isAssigned($runwayId)) {
            throw new Exception('Runway is already assigned');
        }

        // Check if flight exists
        $stmt = $this->pdo->prepare("SELECT id FROM flights WHERE id = ?");
        $stmt->execute([$flightId]);
        if (!$stmt->fetch()) {
            throw new Exception('Flight not found');
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO runway_assignments (
                runway_id, flight_id, operation_type, expected_release, status, assigned_at
            ) VALUES (?, ?, ?, ?, 'active', CURRENT_TIMESTAMP)
        ");

        $stmt->execute([$runwayId, $flightId, $operationType, $expectedRelease]);
        $assignmentId = $this->pdo->lastInsertId();

        $this->logger->info('Runway assigned to flight', [
            'runway_id' => $runwayId,
            'flight_id' => $flightId,
            'operation_type' => $operationType,
            'assignment_id' => $assignmentId
        ]);

        return $assignmentId;
    }

    /**
     * Release runway from flight
     */
    public function releaseFromFlight($runwayId)
    {
        $stmt = $this->pdo->prepare("
            UPDATE runway_assignments
            SET status = 'completed', released_at = CURRENT_TIMESTAMP
            WHERE runway_id = ? AND status = 'active'
        ");

        $result = $stmt->execute([$runwayId]);

        if ($result && $stmt->rowCount() > 0) {
            $this->logger->info('Runway released', ['runway_id' => $runwayId]);
            return true;
        }

        return false;
    }

    /**
     * Get current runway assignments
     */
    public function getCurrentAssignments()
    {
        $stmt = $this->pdo->prepare("
            SELECT
                ra.*,
                r.runway_number,
                f.flight_number,
                f.origin,
                f.destination
            FROM runway_assignments ra
            JOIN runways r ON ra.runway_id = r.id
            JOIN flights f ON ra.flight_id = f.id
            WHERE ra.status = 'active'
            ORDER BY ra.assigned_at ASC
        ");
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get runway utilization statistics
     */
    public function getUtilizationStats($dateFrom = null, $dateTo = null)
    {
        $whereClause = "";
        $params = [];

        if ($dateFrom && $dateTo) {
            $whereClause = "AND ra.assigned_at BETWEEN ? AND ?";
            $params = [$dateFrom, $dateTo];
        }

        $stmt = $this->pdo->prepare("
            SELECT
                r.runway_number,
                COUNT(ra.id) as total_assignments,
                AVG(EXTRACT(EPOCH FROM (ra.released_at - ra.assigned_at))/60) as avg_usage_minutes,
                MAX(EXTRACT(EPOCH FROM (ra.released_at - ra.assigned_at))/60) as max_usage_minutes,
                MIN(EXTRACT(EPOCH FROM (ra.released_at - ra.assigned_at))/60) as min_usage_minutes
            FROM runways r
            LEFT JOIN runway_assignments ra ON r.id = ra.runway_id AND ra.status = 'completed' {$whereClause}
            GROUP BY r.id, r.runway_number
            ORDER BY r.runway_number
        ");
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get runway availability status
     */
    public function getAvailabilityStatus()
    {
        $stmt = $this->pdo->prepare("
            SELECT
                r.runway_number,
                r.status as runway_status,
                CASE
                    WHEN ra.id IS NOT NULL THEN 'assigned'
                    WHEN r.status = 'active' THEN 'available'
                    WHEN r.status = 'maintenance' THEN 'maintenance'
                    ELSE 'closed'
                END as availability_status,
                f.flight_number as assigned_flight
            FROM runways r
            LEFT JOIN runway_assignments ra ON r.id = ra.runway_id AND ra.status = 'active'
            LEFT JOIN flights f ON ra.flight_id = f.id
            ORDER BY r.runway_number
        ");
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Count total runways
     */
    public function count(array $filters = [])
    {
        $where = [];
        $params = [];

        if (isset($filters['status'])) {
            $where[] = "status = ?";
            $params[] = $filters['status'];
        }

        if (isset($filters['usage_type'])) {
            $where[] = "usage_type = ?";
            $params[] = $filters['usage_type'];
        }

        $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

        $stmt = $this->pdo->prepare("SELECT COUNT(*) as total FROM runways {$whereClause}");
        $stmt->execute($params);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) $result['total'];
    }

    /**
     * Get runways by capacity score
     */
    public function getByCapacityScore($minScore = 0, $maxScore = 100)
    {
        $stmt = $this->pdo->prepare("
            SELECT
                r.*,
                CASE
                    WHEN r.length_ft >= 10000 THEN 100
                    WHEN r.length_ft >= 8000 THEN 90
                    ELSE 70
                END -
                CASE
                    WHEN r.status = 'maintenance' THEN 50
                    WHEN r.status = 'closed' THEN 100
                    ELSE 0
                END as capacity_score
            FROM runways r
            HAVING capacity_score BETWEEN ? AND ?
            ORDER BY capacity_score DESC, r.runway_number ASC
        ");
        $stmt->execute([$minScore, $maxScore]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
