<?php
/**
 * Runway Service
 *
 * Business logic for runway management, assignment, and utilization
 */

class RunwayService
{
    private $runwayRepository;
    private $flightRepository;
    private $logger;

    public function __construct($runwayRepository, $flightRepository = null)
    {
        $this->runwayRepository = $runwayRepository;
        $this->flightRepository = $flightRepository;
        $this->logger = Logger::getInstance();
    }

    /**
     * Get all runways with optional filters
     */
    public function getRunways(array $filters = [], array $pagination = [])
    {
        try {
            $runways = $this->runwayRepository->findAll($filters, $pagination);

            // Convert to Runway objects
            return array_map(function($data) {
                return Runway::fromDatabaseArray($data);
            }, $runways);
        } catch (Exception $e) {
            $this->logger->error('Failed to get runways', [
                'error' => $e->getMessage(),
                'filters' => $filters
            ]);
            throw $e;
        }
    }

    /**
     * Get runway by ID
     */
    public function getRunwayById($id)
    {
        try {
            $runwayData = $this->runwayRepository->findById($id);

            if (!$runwayData) {
                return null;
            }

            return Runway::fromDatabaseArray($runwayData);
        } catch (Exception $e) {
            $this->logger->error('Failed to get runway by ID', [
                'runway_id' => $id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get runway by runway number
     */
    public function getRunwayByNumber($runwayNumber)
    {
        try {
            $runwayData = $this->runwayRepository->findByRunwayNumber($runwayNumber);

            if (!$runwayData) {
                return null;
            }

            return Runway::fromDatabaseArray($runwayData);
        } catch (Exception $e) {
            $this->logger->error('Failed to get runway by number', [
                'runway_number' => $runwayNumber,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get active runways
     */
    public function getActiveRunways()
    {
        try {
            $runways = $this->runwayRepository->findActive();

            return array_map(function($data) {
                return Runway::fromDatabaseArray($data);
            }, $runways);
        } catch (Exception $e) {
            $this->logger->error('Failed to get active runways', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get available runways for specific operation
     */
    public function getAvailableRunwaysForOperation($operationType, array $windConditions = [])
    {
        try {
            $runways = $this->runwayRepository->findAvailableForOperation($operationType, $windConditions);

            return array_map(function($data) {
                return Runway::fromDatabaseArray($data);
            }, $runways);
        } catch (Exception $e) {
            $this->logger->error('Failed to get available runways', [
                'operation_type' => $operationType,
                'wind_conditions' => $windConditions,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Create new runway
     */
    public function createRunway(array $data)
    {
        try {
            // Validate input data
            $this->validateRunwayData($data);

            // Check if runway number already exists
            if ($this->runwayRepository->runwayNumberExists($data['runway_number'])) {
                throw new Exception('Runway number already exists');
            }

            // Create runway
            $runwayId = $this->runwayRepository->create($data);

            $this->logger->info('Runway created successfully', [
                'runway_id' => $runwayId,
                'runway_number' => $data['runway_number']
            ]);

            return $runwayId;
        } catch (Exception $e) {
            $this->logger->error('Failed to create runway', [
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Update runway
     */
    public function updateRunway($id, array $data)
    {
        try {
            // Check if runway exists
            $existingRunway = $this->getRunwayById($id);
            if (!$existingRunway) {
                throw new Exception('Runway not found');
            }

            // Validate update data
            $this->validateRunwayUpdateData($data, $existingRunway);

            // Check runway number uniqueness if being changed
            if (isset($data['runway_number']) &&
                $data['runway_number'] !== $existingRunway->getRunwayNumber() &&
                $this->runwayRepository->runwayNumberExists($data['runway_number'], $id)) {
                throw new Exception('Runway number already exists');
            }

            // Update runway
            $result = $this->runwayRepository->update($id, $data);

            if ($result) {
                $this->logger->info('Runway updated successfully', [
                    'runway_id' => $id,
                    'updated_fields' => array_keys($data)
                ]);
            }

            return $result;
        } catch (Exception $e) {
            $this->logger->error('Failed to update runway', [
                'runway_id' => $id,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Delete runway
     */
    public function deleteRunway($id)
    {
        try {
            // Check if runway exists
            $runway = $this->getRunwayById($id);
            if (!$runway) {
                throw new Exception('Runway not found');
            }

            // Check if runway can be deleted
            if (!$runway->canBeDeleted()) {
                throw new Exception('Cannot delete runway that is currently assigned');
            }

            // Delete runway
            $result = $this->runwayRepository->delete($id);

            if ($result) {
                $this->logger->info('Runway deleted successfully', [
                    'runway_id' => $id,
                    'runway_number' => $runway->getRunwayNumber()
                ]);
            }

            return $result;
        } catch (Exception $e) {
            $this->logger->error('Failed to delete runway', [
                'runway_id' => $id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Assign runway to flight
     */
    public function assignRunwayToFlight($runwayId, $flightId, $operationType = 'departure', $expectedRelease = null)
    {
        try {
            // Validate runway
            $runway = $this->getRunwayById($runwayId);
            if (!$runway) {
                throw new Exception('Runway not found');
            }

            if (!$runway->canBeAssigned()) {
                throw new Exception('Runway is not available for assignment');
            }

            // Validate operation type compatibility
            if (!$runway->isSuitableFor($operationType)) {
                throw new Exception("Runway is not suitable for {$operationType} operations");
            }

            // Validate expected release time
            if ($expectedRelease) {
                $releaseTime = strtotime($expectedRelease);
                if ($releaseTime <= time()) {
                    throw new Exception('Expected release time must be in the future');
                }
            }

            // Assign runway
            $assignmentId = $this->runwayRepository->assignToFlight(
                $runwayId,
                $flightId,
                $operationType,
                $expectedRelease
            );

            $this->logger->info('Runway assigned to flight', [
                'runway_id' => $runwayId,
                'flight_id' => $flightId,
                'operation_type' => $operationType,
                'assignment_id' => $assignmentId
            ]);

            return $assignmentId;
        } catch (Exception $e) {
            $this->logger->error('Failed to assign runway to flight', [
                'runway_id' => $runwayId,
                'flight_id' => $flightId,
                'operation_type' => $operationType,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Release runway from flight
     */
    public function releaseRunway($runwayId)
    {
        try {
            // Check if runway is assigned
            if (!$this->runwayRepository->isAssigned($runwayId)) {
                throw new Exception('Runway is not currently assigned');
            }

            // Release runway
            $result = $this->runwayRepository->releaseFromFlight($runwayId);

            if ($result) {
                $this->logger->info('Runway released successfully', [
                    'runway_id' => $runwayId
                ]);
            }

            return $result;
        } catch (Exception $e) {
            $this->logger->error('Failed to release runway', [
                'runway_id' => $runwayId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Set runway to maintenance
     */
    public function setRunwayMaintenance($runwayId, $maintenanceType = 'scheduled', $expectedCompletion = null, $notes = null)
    {
        try {
            // Validate runway
            $runway = $this->getRunwayById($runwayId);
            if (!$runway) {
                throw new Exception('Runway not found');
            }

            if (!$runway->canBeSetToMaintenance()) {
                throw new Exception('Cannot set runway to maintenance while it is assigned');
            }

            // Update runway status
            $updateData = [
                'status' => Runway::STATUS_MAINTENANCE,
                'notes' => $notes ?: $runway->getNotes()
            ];

            $result = $this->runwayRepository->update($runwayId, $updateData);

            if ($result) {
                $this->logger->info('Runway set to maintenance', [
                    'runway_id' => $runwayId,
                    'maintenance_type' => $maintenanceType,
                    'expected_completion' => $expectedCompletion
                ]);
            }

            return $result;
        } catch (Exception $e) {
            $this->logger->error('Failed to set runway to maintenance', [
                'runway_id' => $runwayId,
                'maintenance_type' => $maintenanceType,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get current runway assignments
     */
    public function getCurrentAssignments()
    {
        try {
            return $this->runwayRepository->getCurrentAssignments();
        } catch (Exception $e) {
            $this->logger->error('Failed to get current assignments', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get runway utilization statistics
     */
    public function getRunwayStatistics($dateFrom = null, $dateTo = null)
    {
        try {
            return $this->runwayRepository->getUtilizationStats($dateFrom, $dateTo);
        } catch (Exception $e) {
            $this->logger->error('Failed to get runway statistics', [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get runway availability status
     */
    public function getAvailabilityStatus()
    {
        try {
            return $this->runwayRepository->getAvailabilityStatus();
        } catch (Exception $e) {
            $this->logger->error('Failed to get availability status', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Find best runway for operation
     */
    public function findBestRunwayForOperation($operationType, array $windConditions = [])
    {
        try {
            $availableRunways = $this->getAvailableRunwaysForOperation($operationType, $windConditions);

            if (empty($availableRunways)) {
                return null;
            }

            // Sort by capacity score (highest first)
            usort($availableRunways, function($a, $b) {
                return $b->getCapacityScore() <=> $a->getCapacityScore();
            });

            return $availableRunways[0];
        } catch (Exception $e) {
            $this->logger->error('Failed to find best runway', [
                'operation_type' => $operationType,
                'wind_conditions' => $windConditions,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Validate runway data
     */
    private function validateRunwayData(array $data)
    {
        $errors = [];

        // Required fields
        $required = ['runway_number', 'length_ft', 'usage_type'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $errors[] = "Field '{$field}' is required";
            }
        }

        // Runway number format
        if (isset($data['runway_number'])) {
            if (!preg_match('/^\d{1,2}[LCR]?\/\d{1,2}[LCR]?$/', $data['runway_number'])) {
                $errors[] = 'Invalid runway number format (expected: XX/YY or XXL/YYR)';
            }
        }

        // Length validation
        if (isset($data['length_ft'])) {
            if ($data['length_ft'] <= 0 || $data['length_ft'] > 20000) {
                $errors[] = 'Runway length must be between 1 and 20000 feet';
            }
        }

        // Width validation
        if (isset($data['width_ft']) && $data['width_ft'] !== null) {
            if ($data['width_ft'] <= 0 || $data['width_ft'] > 500) {
                $errors[] = 'Runway width must be between 1 and 500 feet';
            }
        }

        // Usage type validation
        if (isset($data['usage_type'])) {
            $validTypes = [Runway::USAGE_DEPARTURE, Runway::USAGE_ARRIVAL, Runway::USAGE_BOTH];
            if (!in_array($data['usage_type'], $validTypes)) {
                $errors[] = 'Invalid usage type';
            }
        }

        // Surface type validation
        if (isset($data['surface_type'])) {
            $validSurfaces = [Runway::SURFACE_ASPHALT, Runway::SURFACE_CONCRETE, Runway::SURFACE_GRASS];
            if (!in_array($data['surface_type'], $validSurfaces)) {
                $errors[] = 'Invalid surface type';
            }
        }

        // Wind limit validations
        if (isset($data['max_crosswind_kts'])) {
            if ($data['max_crosswind_kts'] < 0 || $data['max_crosswind_kts'] > 50) {
                $errors[] = 'Max crosswind must be between 0 and 50 knots';
            }
        }

        if (isset($data['max_tailwind_kts'])) {
            if ($data['max_tailwind_kts'] < 0 || $data['max_tailwind_kts'] > 30) {
                $errors[] = 'Max tailwind must be between 0 and 30 knots';
            }
        }

        if (!empty($errors)) {
            throw new Exception('Validation failed: ' . implode(', ', $errors));
        }
    }

    /**
     * Validate runway update data
     */
    private function validateRunwayUpdateData(array $data, Runway $existingRunway)
    {
        // For updates, we can be more lenient, but still validate critical fields
        if (isset($data['length_ft'])) {
            if ($data['length_ft'] <= 0 || $data['length_ft'] > 20000) {
                throw new Exception('Runway length must be between 1 and 20000 feet');
            }
        }

        if (isset($data['status'])) {
            $validStatuses = [Runway::STATUS_ACTIVE, Runway::STATUS_MAINTENANCE, Runway::STATUS_CLOSED];
            if (!in_array($data['status'], $validStatuses)) {
                throw new Exception('Invalid status value');
            }

            // Check if trying to set to maintenance while assigned
            if ($data['status'] === Runway::STATUS_MAINTENANCE && $existingRunway->isAssigned()) {
                throw new Exception('Cannot set runway to maintenance while it is assigned');
            }
        }
    }

    /**
     * Get runways by capacity score
     */
    public function getRunwaysByCapacityScore($minScore = 0, $maxScore = 100)
    {
        try {
            $runways = $this->runwayRepository->getByCapacityScore($minScore, $maxScore);

            return array_map(function($data) {
                return Runway::fromDatabaseArray($data);
            }, $runways);
        } catch (Exception $e) {
            $this->logger->error('Failed to get runways by capacity score', [
                'min_score' => $minScore,
                'max_score' => $maxScore,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Count runways with filters
     */
    public function countRunways(array $filters = [])
    {
        try {
            return $this->runwayRepository->count($filters);
        } catch (Exception $e) {
            $this->logger->error('Failed to count runways', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
