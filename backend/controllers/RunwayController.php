<?php
/**
 * Runway Controller
 *
 * HTTP request handling for runway management operations
 */

class RunwayController
{
    private $runwayService;
    private $logger;

    public function __construct($runwayService = null)
    {
        $this->runwayService = $runwayService ?: app('runway_service');
        $this->logger = Logger::getInstance();
    }

    /**
     * Handle GET requests
     */
    public function handleGet($action, array $params = [])
    {
        try {
            switch ($action) {
                case 'list':
                    return $this->getRunways($params);

                case 'detail':
                    return $this->getRunway($params);

                case 'active':
                    return $this->getActiveRunways();

                case 'available':
                    return $this->getAvailableRunways($params);

                case 'assignments':
                    return $this->getCurrentAssignments();

                case 'statistics':
                    return $this->getRunwayStatistics($params);

                case 'availability':
                    return $this->getAvailabilityStatus();

                default:
                    ApiResponse::badRequest('Invalid action: ' . $action);
            }
        } catch (Exception $e) {
            $this->logger->error('Runway controller GET error', [
                'action' => $action,
                'params' => $params,
                'error' => $e->getMessage()
            ]);
            ApiResponse::error('Failed to process request', ApiResponse::HTTP_INTERNAL_SERVER_ERROR, 'CONTROLLER_ERROR');
        }
    }

    /**
     * Handle POST requests
     */
    public function handlePost($action, array $data = [])
    {
        try {
            switch ($action) {
                case 'create':
                    return $this->createRunway($data);

                case 'assign':
                    return $this->assignRunway($data);

                case 'release':
                    return $this->releaseRunway($data);

                case 'maintenance':
                    return $this->setRunwayMaintenance($data);

                default:
                    ApiResponse::badRequest('Invalid action: ' . $action);
            }
        } catch (Exception $e) {
            $this->logger->error('Runway controller POST error', [
                'action' => $action,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            ApiResponse::error('Failed to process request', ApiResponse::HTTP_INTERNAL_SERVER_ERROR, 'CONTROLLER_ERROR');
        }
    }

    /**
     * Handle PUT requests
     */
    public function handlePut($action, array $data = [])
    {
        try {
            switch ($action) {
                case 'update':
                    return $this->updateRunway($data);

                default:
                    ApiResponse::badRequest('Invalid action: ' . $action);
            }
        } catch (Exception $e) {
            $this->logger->error('Runway controller PUT error', [
                'action' => $action,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            ApiResponse::error('Failed to process request', ApiResponse::HTTP_INTERNAL_SERVER_ERROR, 'CONTROLLER_ERROR');
        }
    }

    /**
     * Handle DELETE requests
     */
    public function handleDelete($action, array $data = [])
    {
        try {
            switch ($action) {
                case 'delete':
                    return $this->deleteRunway($data);

                default:
                    ApiResponse::badRequest('Invalid action: ' . $action);
            }
        } catch (Exception $e) {
            $this->logger->error('Runway controller DELETE error', [
                'action' => $action,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            ApiResponse::error('Failed to process request', ApiResponse::HTTP_INTERNAL_SERVER_ERROR, 'CONTROLLER_ERROR');
        }
    }

    /**
     * Get runways list
     */
    private function getRunways(array $params = [])
    {
        // Check permissions
        Middleware::authenticate();
        Middleware::checkPermission('read', 'flights');

        $filters = [];
        $pagination = [
            'page' => (int)($params['page'] ?? 1),
            'limit' => (int)($params['limit'] ?? 50)
        ];

        // Build filters
        if (isset($params['status'])) {
            $filters['status'] = $params['status'];
        }

        if (isset($params['usage_type'])) {
            $filters['usage_type'] = $params['usage_type'];
        }

        if (isset($params['surface_type'])) {
            $filters['surface_type'] = $params['surface_type'];
        }

        $runways = $this->runwayService->getRunways($filters, $pagination);
        $total = $this->runwayService->countRunways($filters);

        $paginationInfo = [
            'page' => $pagination['page'],
            'limit' => $pagination['limit'],
            'total' => $total,
            'total_pages' => ceil($total / $pagination['limit']),
            'has_next' => ($pagination['page'] * $pagination['limit']) < $total,
            'has_prev' => $pagination['page'] > 1
        ];

        // Convert to arrays for API response
        $runwayArrays = array_map(function($runway) {
            return $runway->toArray();
        }, $runways);

        ApiResponse::paginated($runwayArrays, $paginationInfo, 'Runways retrieved successfully');
    }

    /**
     * Get single runway
     */
    private function getRunway(array $params = [])
    {
        // Check permissions
        Middleware::authenticate();
        Middleware::checkPermission('read', 'flights');

        $runwayId = $params['id'] ?? null;
        if (!$runwayId) {
            ApiResponse::badRequest('Runway ID is required');
        }

        $runway = $this->runwayService->getRunwayById($runwayId);
        if (!$runway) {
            ApiResponse::notFound('Runway');
        }

        ApiResponse::success($runway->toArray(), 'Runway retrieved successfully');
    }

    /**
     * Get active runways
     */
    private function getActiveRunways()
    {
        // Check permissions
        Middleware::authenticate();
        Middleware::checkPermission('read', 'flights');

        $runways = $this->runwayService->getActiveRunways();

        $runwayArrays = array_map(function($runway) {
            return $runway->toArray();
        }, $runways);

        ApiResponse::success($runwayArrays, 'Active runways retrieved successfully');
    }

    /**
     * Get available runways for operation
     */
    private function getAvailableRunways(array $params = [])
    {
        // Check permissions
        Middleware::authenticate();
        Middleware::checkPermission('read', 'flights');

        $operationType = $params['operation_type'] ?? 'departure';
        $windConditions = [];

        if (isset($params['crosswind_kts'])) {
            $windConditions['crosswind_kts'] = (float)$params['crosswind_kts'];
        }

        if (isset($params['tailwind_kts'])) {
            $windConditions['tailwind_kts'] = (float)$params['tailwind_kts'];
        }

        $runways = $this->runwayService->getAvailableRunwaysForOperation($operationType, $windConditions);

        $runwayArrays = array_map(function($runway) {
            return $runway->toArray();
        }, $runways);

        ApiResponse::success($runwayArrays, 'Available runways retrieved successfully', ApiResponse::HTTP_OK, [
            'operation_type' => $operationType,
            'wind_conditions' => $windConditions,
            'count' => count($runways)
        ]);
    }

    /**
     * Get current runway assignments
     */
    private function getCurrentAssignments()
    {
        // Check permissions
        Middleware::authenticate();
        Middleware::checkPermission('read', 'flights');

        $assignments = $this->runwayService->getCurrentAssignments();

        ApiResponse::success($assignments, 'Current runway assignments retrieved successfully');
    }

    /**
     * Get runway statistics
     */
    private function getRunwayStatistics(array $params = [])
    {
        // Check permissions
        Middleware::authenticate();
        Middleware::checkPermission('read', 'flights');

        $dateFrom = $params['date_from'] ?? null;
        $dateTo = $params['date_to'] ?? null;

        $statistics = $this->runwayService->getRunwayStatistics($dateFrom, $dateTo);

        ApiResponse::success($statistics, 'Runway statistics retrieved successfully', ApiResponse::HTTP_OK, [
            'date_range' => [
                'from' => $dateFrom,
                'to' => $dateTo
            ]
        ]);
    }

    /**
     * Get runway availability status
     */
    private function getAvailabilityStatus()
    {
        // Check permissions
        Middleware::authenticate();
        Middleware::checkPermission('read', 'flights');

        $status = $this->runwayService->getAvailabilityStatus();

        ApiResponse::success($status, 'Runway availability status retrieved successfully');
    }

    /**
     * Create new runway
     */
    private function createRunway(array $data = [])
    {
        // Check permissions
        Middleware::authenticate();
        Middleware::checkPermission('admin', 'flights');

        if (empty($data)) {
            ApiResponse::badRequest('Runway data is required');
        }

        try {
            $runwayId = $this->runwayService->createRunway($data);

            // Get the created runway
            $runway = $this->runwayService->getRunwayById($runwayId);

            ApiResponse::created($runway->toArray(), 'Runway created successfully');
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'already exists') !== false) {
                ApiResponse::conflict('Runway number already exists');
            } elseif (strpos($e->getMessage(), 'Validation failed') !== false) {
                ApiResponse::validationError([], $e->getMessage());
            } else {
                ApiResponse::error($e->getMessage(), ApiResponse::HTTP_BAD_REQUEST, 'VALIDATION_ERROR');
            }
        }
    }

    /**
     * Update runway
     */
    private function updateRunway(array $data = [])
    {
        // Check permissions
        Middleware::authenticate();
        Middleware::checkPermission('write', 'flights');

        $runwayId = $data['id'] ?? null;
        if (!$runwayId) {
            ApiResponse::badRequest('Runway ID is required');
        }

        unset($data['id']); // Remove ID from update data

        if (empty($data)) {
            ApiResponse::badRequest('Update data is required');
        }

        try {
            $result = $this->runwayService->updateRunway($runwayId, $data);

            if ($result) {
                $runway = $this->runwayService->getRunwayById($runwayId);
                ApiResponse::success($runway->toArray(), 'Runway updated successfully');
            } else {
                ApiResponse::error('Failed to update runway', ApiResponse::HTTP_INTERNAL_SERVER_ERROR, 'UPDATE_FAILED');
            }
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'not found') !== false) {
                ApiResponse::notFound('Runway');
            } elseif (strpos($e->getMessage(), 'already exists') !== false) {
                ApiResponse::conflict('Runway number already exists');
            } else {
                ApiResponse::error($e->getMessage(), ApiResponse::HTTP_BAD_REQUEST, 'UPDATE_ERROR');
            }
        }
    }

    /**
     * Delete runway
     */
    private function deleteRunway(array $data = [])
    {
        // Check permissions
        Middleware::authenticate();
        Middleware::checkPermission('admin', 'flights');

        $runwayId = $data['id'] ?? null;
        if (!$runwayId) {
            ApiResponse::badRequest('Runway ID is required');
        }

        try {
            $result = $this->runwayService->deleteRunway($runwayId);

            if ($result) {
                ApiResponse::noContent('Runway deleted successfully');
            } else {
                ApiResponse::error('Failed to delete runway', ApiResponse::HTTP_INTERNAL_SERVER_ERROR, 'DELETE_FAILED');
            }
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'not found') !== false) {
                ApiResponse::notFound('Runway');
            } elseif (strpos($e->getMessage(), 'assigned') !== false) {
                ApiResponse::conflict('Cannot delete runway that is currently assigned');
            } else {
                ApiResponse::error($e->getMessage(), ApiResponse::HTTP_BAD_REQUEST, 'DELETE_ERROR');
            }
        }
    }

    /**
     * Assign runway to flight
     */
    private function assignRunway(array $data = [])
    {
        // Check permissions
        Middleware::authenticate();
        Middleware::checkPermission('write', 'flights');

        $runwayId = $data['runway_id'] ?? null;
        $flightId = $data['flight_id'] ?? null;
        $operationType = $data['operation_type'] ?? 'departure';
        $expectedRelease = $data['expected_release'] ?? null;

        if (!$runwayId || !$flightId) {
            ApiResponse::badRequest('Runway ID and Flight ID are required');
        }

        try {
            $assignmentId = $this->runwayService->assignRunwayToFlight(
                $runwayId,
                $flightId,
                $operationType,
                $expectedRelease
            );

            ApiResponse::success([
                'assignment_id' => $assignmentId,
                'runway_id' => $runwayId,
                'flight_id' => $flightId,
                'operation_type' => $operationType,
                'expected_release' => $expectedRelease
            ], 'Runway assigned successfully');
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'not found') !== false) {
                ApiResponse::notFound(strpos($e->getMessage(), 'Runway') !== false ? 'Runway' : 'Flight');
            } elseif (strpos($e->getMessage(), 'not available') !== false) {
                ApiResponse::conflict('Runway is not available for assignment');
            } elseif (strpos($e->getMessage(), 'already assigned') !== false) {
                ApiResponse::conflict('Runway is already assigned');
            } elseif (strpos($e->getMessage(), 'not suitable') !== false) {
                ApiResponse::badRequest('Runway is not suitable for this operation');
            } else {
                ApiResponse::error($e->getMessage(), ApiResponse::HTTP_BAD_REQUEST, 'ASSIGNMENT_ERROR');
            }
        }
    }

    /**
     * Release runway
     */
    private function releaseRunway(array $data = [])
    {
        // Check permissions
        Middleware::authenticate();
        Middleware::checkPermission('write', 'flights');

        $runwayId = $data['runway_id'] ?? null;
        if (!$runwayId) {
            ApiResponse::badRequest('Runway ID is required');
        }

        try {
            $result = $this->runwayService->releaseRunway($runwayId);

            if ($result) {
                ApiResponse::success(['runway_id' => $runwayId], 'Runway released successfully');
            } else {
                ApiResponse::error('Failed to release runway', ApiResponse::HTTP_INTERNAL_SERVER_ERROR, 'RELEASE_FAILED');
            }
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'not currently assigned') !== false) {
                ApiResponse::conflict('Runway is not currently assigned');
            } else {
                ApiResponse::error($e->getMessage(), ApiResponse::HTTP_BAD_REQUEST, 'RELEASE_ERROR');
            }
        }
    }

    /**
     * Set runway to maintenance
     */
    private function setRunwayMaintenance(array $data = [])
    {
        // Check permissions
        Middleware::authenticate();
        Middleware::checkPermission('write', 'flights');

        $runwayId = $data['runway_id'] ?? null;
        $maintenanceType = $data['maintenance_type'] ?? 'scheduled';
        $expectedCompletion = $data['expected_completion'] ?? null;
        $notes = $data['notes'] ?? null;

        if (!$runwayId) {
            ApiResponse::badRequest('Runway ID is required');
        }

        try {
            $result = $this->runwayService->setRunwayMaintenance(
                $runwayId,
                $maintenanceType,
                $expectedCompletion,
                $notes
            );

            if ($result) {
                ApiResponse::success([
                    'runway_id' => $runwayId,
                    'maintenance_type' => $maintenanceType,
                    'expected_completion' => $expectedCompletion,
                    'notes' => $notes
                ], 'Runway set to maintenance successfully');
            } else {
                ApiResponse::error('Failed to set runway to maintenance', ApiResponse::HTTP_INTERNAL_SERVER_ERROR, 'MAINTENANCE_FAILED');
            }
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'not found') !== false) {
                ApiResponse::notFound('Runway');
            } elseif (strpos($e->getMessage(), 'assigned') !== false) {
                ApiResponse::conflict('Cannot set runway to maintenance while it is assigned');
            } else {
                ApiResponse::error($e->getMessage(), ApiResponse::HTTP_BAD_REQUEST, 'MAINTENANCE_ERROR');
            }
        }
    }
}
