<?php
/**
 * Runway Model
 *
 * Represents an airport runway with its physical characteristics,
 * operational status, and assignment management
 */

class Runway
{
    private $id;
    private $runwayNumber;
    private $lengthFt;
    private $widthFt;
    private $surfaceType;
    private $usageType; // departure, arrival, both
    private $maxCrosswindKts;
    private $maxTailwindKts;
    private $status; // active, maintenance, closed
    private $notes;
    private $createdAt;
    private $updatedAt;

    // Current assignment data
    private $assignedFlightId;
    private $assignedFlightNumber;
    private $operationType;
    private $assignedAt;
    private $expectedRelease;

    // Status constants
    const STATUS_ACTIVE = 'active';
    const STATUS_MAINTENANCE = 'maintenance';
    const STATUS_CLOSED = 'closed';

    const USAGE_DEPARTURE = 'departure';
    const USAGE_ARRIVAL = 'arrival';
    const USAGE_BOTH = 'both';

    const SURFACE_ASPHALT = 'asphalt';
    const SURFACE_CONCRETE = 'concrete';
    const SURFACE_GRASS = 'grass';

    public function __construct(array $data = [])
    {
        $this->hydrate($data);
    }

    /**
     * Hydrate object from array data
     */
    private function hydrate(array $data)
    {
        $this->id = $data['id'] ?? null;
        $this->runwayNumber = $data['runway_number'] ?? '';
        $this->lengthFt = $data['length_ft'] ?? 0;
        $this->widthFt = $data['width_ft'] ?? null;
        $this->surfaceType = $data['surface_type'] ?? self::SURFACE_ASPHALT;
        $this->usageType = $data['usage_type'] ?? self::USAGE_BOTH;
        $this->maxCrosswindKts = $data['max_crosswind_kts'] ?? 20;
        $this->maxTailwindKts = $data['max_tailwind_kts'] ?? 10;
        $this->status = $data['status'] ?? self::STATUS_ACTIVE;
        $this->notes = $data['notes'] ?? null;
        $this->createdAt = $data['created_at'] ?? null;
        $this->updatedAt = $data['updated_at'] ?? null;

        // Assignment data
        $this->assignedFlightId = $data['assigned_flight_id'] ?? null;
        $this->assignedFlightNumber = $data['assigned_flight_number'] ?? null;
        $this->operationType = $data['operation_type'] ?? null;
        $this->assignedAt = $data['assigned_at'] ?? null;
        $this->expectedRelease = $data['expected_release'] ?? null;
    }

    // ===== GETTERS =====

    public function getId() { return $this->id; }
    public function getRunwayNumber() { return $this->runwayNumber; }
    public function getLengthFt() { return $this->lengthFt; }
    public function getWidthFt() { return $this->widthFt; }
    public function getSurfaceType() { return $this->surfaceType; }
    public function getUsageType() { return $this->usageType; }
    public function getMaxCrosswindKts() { return $this->maxCrosswindKts; }
    public function getMaxTailwindKts() { return $this->maxTailwindKts; }
    public function getStatus() { return $this->status; }
    public function getNotes() { return $this->notes; }
    public function getCreatedAt() { return $this->createdAt; }
    public function getUpdatedAt() { return $this->updatedAt; }

    // Assignment getters
    public function getAssignedFlightId() { return $this->assignedFlightId; }
    public function getAssignedFlightNumber() { return $this->assignedFlightNumber; }
    public function getOperationType() { return $this->operationType; }
    public function getAssignedAt() { return $this->assignedAt; }
    public function getExpectedRelease() { return $this->expectedRelease; }

    // ===== SETTERS =====

    public function setRunwayNumber($number) { $this->runwayNumber = $number; }
    public function setLengthFt($length) { $this->lengthFt = $length; }
    public function setWidthFt($width) { $this->widthFt = $width; }
    public function setSurfaceType($type) { $this->surfaceType = $type; }
    public function setUsageType($type) { $this->usageType = $type; }
    public function setMaxCrosswindKts($kts) { $this->maxCrosswindKts = $kts; }
    public function setMaxTailwindKts($kts) { $this->maxTailwindKts = $kts; }
    public function setStatus($status) { $this->status = $status; }
    public function setNotes($notes) { $this->notes = $notes; }

    // ===== BUSINESS LOGIC METHODS =====

    /**
     * Check if runway is currently active
     */
    public function isActive()
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Check if runway is under maintenance
     */
    public function isUnderMaintenance()
    {
        return $this->status === self::STATUS_MAINTENANCE;
    }

    /**
     * Check if runway is closed
     */
    public function isClosed()
    {
        return $this->status === self::STATUS_CLOSED;
    }

    /**
     * Check if runway is currently assigned
     */
    public function isAssigned()
    {
        return $this->assignedFlightId !== null;
    }

    /**
     * Check if runway can be used for departure
     */
    public function canHandleDeparture()
    {
        return $this->usageType === self::USAGE_DEPARTURE || $this->usageType === self::USAGE_BOTH;
    }

    /**
     * Check if runway can be used for arrival
     */
    public function canHandleArrival()
    {
        return $this->usageType === self::USAGE_ARRIVAL || $this->usageType === self::USAGE_BOTH;
    }

    /**
     * Check if runway can be assigned
     */
    public function canBeAssigned()
    {
        return $this->isActive() && !$this->isAssigned();
    }

    /**
     * Check if runway can be set to maintenance
     */
    public function canBeSetToMaintenance()
    {
        return $this->isActive() && !$this->isAssigned();
    }

    /**
     * Check if runway can be deleted
     */
    public function canBeDeleted()
    {
        return !$this->isAssigned();
    }

    /**
     * Get runway utilization status
     */
    public function getUtilizationStatus()
    {
        if ($this->isAssigned()) {
            return 'assigned';
        }

        switch ($this->status) {
            case self::STATUS_ACTIVE:
                return 'available';
            case self::STATUS_MAINTENANCE:
                return 'maintenance';
            case self::STATUS_CLOSED:
                return 'closed';
            default:
                return 'unknown';
        }
    }

    /**
     * Calculate runway capacity score (0-100)
     */
    public function getCapacityScore()
    {
        $score = 100;

        // Reduce score based on length (shorter runways have lower capacity)
        if ($this->lengthFt < 8000) {
            $score -= 20;
        } elseif ($this->lengthFt < 10000) {
            $score -= 10;
        }

        // Reduce score for maintenance or closed status
        if ($this->isUnderMaintenance()) {
            $score -= 50;
        } elseif ($this->isClosed()) {
            $score = 0;
        }

        // Reduce score if currently assigned
        if ($this->isAssigned()) {
            $score -= 30;
        }

        return max(0, min(100, $score));
    }

    /**
     * Get runway suitability for specific operation
     */
    public function isSuitableFor($operationType, $windConditions = [])
    {
        // Check usage type compatibility
        if ($operationType === 'departure' && !$this->canHandleDeparture()) {
            return false;
        }

        if ($operationType === 'arrival' && !$this->canHandleArrival()) {
            return false;
        }

        // Check wind conditions if provided
        if (!empty($windConditions)) {
            $crosswind = $windConditions['crosswind_kts'] ?? 0;
            $tailwind = $windConditions['tailwind_kts'] ?? 0;

            if ($crosswind > $this->maxCrosswindKts || $tailwind > $this->maxTailwindKts) {
                return false;
            }
        }

        // Check operational status
        return $this->isActive();
    }

    /**
     * Get time until expected release
     */
    public function getMinutesUntilRelease()
    {
        if (!$this->expectedRelease) {
            return null;
        }

        $now = time();
        $releaseTime = strtotime($this->expectedRelease);

        if ($releaseTime <= $now) {
            return 0;
        }

        return round(($releaseTime - $now) / 60);
    }

    // ===== VALIDATION METHODS =====

    /**
     * Validate runway data
     */
    public function validate()
    {
        $errors = [];

        if (empty($this->runwayNumber)) {
            $errors[] = 'Runway number is required';
        }

        if (!preg_match('/^\d{1,2}[LCR]?\/\d{1,2}[LCR]?$/', $this->runwayNumber)) {
            $errors[] = 'Invalid runway number format (expected: XX/YY or XXL/YYR)';
        }

        if ($this->lengthFt <= 0 || $this->lengthFt > 20000) {
            $errors[] = 'Runway length must be between 1 and 20000 feet';
        }

        if ($this->widthFt !== null && ($this->widthFt <= 0 || $this->widthFt > 500)) {
            $errors[] = 'Runway width must be between 1 and 500 feet';
        }

        if (!in_array($this->usageType, [self::USAGE_DEPARTURE, self::USAGE_ARRIVAL, self::USAGE_BOTH])) {
            $errors[] = 'Invalid usage type';
        }

        if (!in_array($this->status, [self::STATUS_ACTIVE, self::STATUS_MAINTENANCE, self::STATUS_CLOSED])) {
            $errors[] = 'Invalid status';
        }

        if (!in_array($this->surfaceType, [self::SURFACE_ASPHALT, self::SURFACE_CONCRETE, self::SURFACE_GRASS])) {
            $errors[] = 'Invalid surface type';
        }

        if ($this->maxCrosswindKts < 0 || $this->maxCrosswindKts > 50) {
            $errors[] = 'Max crosswind must be between 0 and 50 knots';
        }

        if ($this->maxTailwindKts < 0 || $this->maxTailwindKts > 30) {
            $errors[] = 'Max tailwind must be between 0 and 30 knots';
        }

        return $errors;
    }

    /**
     * Check if runway is valid
     */
    public function isValid()
    {
        return empty($this->validate());
    }

    // ===== DATA METHODS =====

    /**
     * Convert to array for API responses
     */
    public function toArray()
    {
        return [
            'id' => $this->id,
            'runway_number' => $this->runwayNumber,
            'length_ft' => $this->lengthFt,
            'width_ft' => $this->widthFt,
            'surface_type' => $this->surfaceType,
            'usage_type' => $this->usageType,
            'max_crosswind_kts' => $this->maxCrosswindKts,
            'max_tailwind_kts' => $this->maxTailwindKts,
            'status' => $this->status,
            'utilization_status' => $this->getUtilizationStatus(),
            'capacity_score' => $this->getCapacityScore(),
            'notes' => $this->notes,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            // Assignment data
            'assigned_flight_id' => $this->assignedFlightId,
            'assigned_flight_number' => $this->assignedFlightNumber,
            'operation_type' => $this->operationType,
            'assigned_at' => $this->assignedAt,
            'expected_release' => $this->expectedRelease,
            'minutes_until_release' => $this->getMinutesUntilRelease()
        ];
    }

    /**
     * Convert to array for database operations
     */
    public function toDatabaseArray()
    {
        return [
            'runway_number' => $this->runwayNumber,
            'length_ft' => $this->lengthFt,
            'width_ft' => $this->widthFt,
            'surface_type' => $this->surfaceType,
            'usage_type' => $this->usageType,
            'max_crosswind_kts' => $this->maxCrosswindKts,
            'max_tailwind_kts' => $this->maxTailwindKts,
            'status' => $this->status,
            'notes' => $this->notes
        ];
    }

    /**
     * Create from database row
     */
    public static function fromDatabaseArray(array $data)
    {
        return new self($data);
    }

    /**
     * Compare two runways for equality
     */
    public function equals(Runway $other)
    {
        return $this->id === $other->getId() &&
               $this->runwayNumber === $other->getRunwayNumber();
    }

    /**
     * Get runway display name
     */
    public function getDisplayName()
    {
        return 'Runway ' . $this->runwayNumber;
    }

    /**
     * Get runway summary for logging
     */
    public function getSummary()
    {
        return sprintf(
            'Runway %s (%s ft, %s)',
            $this->runwayNumber,
            number_format($this->lengthFt),
            $this->status
        );
    }
}
