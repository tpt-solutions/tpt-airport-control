<?php
require_once __DIR__ . '/../services/FlightService.php';
require_once __DIR__ . '/../src/Logger.php';
require_once __DIR__ . '/../src/Middleware.php';
require_once __DIR__ . '/../src/Validator.php';
require_once __DIR__ . '/../src/ApiResponse.php';

class FlightController {
    private $flightService;

    public function __construct($pdo) {
        $this->flightService = new FlightService($pdo);
    }

    public function handleRequest($method, $action, $data = null) {
        try {
            switch ($method) {
                case 'GET':
                    return $this->handleGet($action);
                case 'POST':
                    return $this->handlePost($action, $data);
                case 'PUT':
                    return $this->handlePut($action, $data);
                case 'DELETE':
                    return $this->handleDelete($action, $data);
                case 'PATCH':
                    return $this->handlePatch($action, $data);
                default:
                    http_response_code(405);
                    return ['error' => 'Method not allowed'];
            }
        } catch (Exception $e) {
            Logger::error('Flight controller error: ' . $e->getMessage());
            http_response_code(500);
            return ['error' => 'Internal server error'];
        }
    }

    private function handleGet($action) {
        switch ($action) {
            case 'list':
                Middleware::authenticate();
                Middleware::checkPermission('read', 'flights');

                $page = max(1, (int)($_GET['page'] ?? 1));
                $limit = min(100, max(1, (int)($_GET['limit'] ?? 50)));
                $offset = ($page - 1) * $limit;

                $filters = [];
                if (isset($_GET['status'])) $filters['status'] = $_GET['status'];
                if (isset($_GET['origin'])) $filters['origin'] = $_GET['origin'];
                if (isset($_GET['destination'])) $filters['destination'] = $_GET['destination'];

                $result = $this->flightService->getFlights($filters, [
                    'page' => $page,
                    'limit' => $limit,
                    'offset' => $offset
                ]);

                return $result;

            case 'detail':
                Middleware::authenticate();
                Middleware::checkPermission('read', 'flights');

                $flightId = $_GET['id'] ?? null;
                if (!$flightId) {
                    http_response_code(400);
                    return ['error' => 'Flight ID required'];
                }

                return $this->flightService->getFlightById($flightId);

            case 'active':
                Middleware::authenticate();
                Middleware::checkPermission('read', 'flights');

                return $this->flightService->getActiveFlights();

            case 'statistics':
                Middleware::authenticate();
                Middleware::checkPermission('read', 'flights');

                return $this->flightService->getFlightStatistics();

            default:
                http_response_code(400);
                return ['error' => 'Invalid action'];
        }
    }

    private function handlePost($action, $data) {
        switch ($action) {
            case 'create':
                Middleware::authenticate();
                Middleware::checkPermission('write', 'flights');

                // Validate input data
                $validator = Validator::getInstance();
                $rules = $validator->getFlightCreationRules();

                if (!$validator->validate($data, $rules)) {
                    http_response_code(400);
                    return [
                        'error' => 'Validation failed',
                        'details' => $validator->getErrors()
                    ];
                }

                // Sanitize input data
                $sanitizedData = $validator->sanitize($data, [
                    'flight_number' => ['strtoupper'],
                    'origin' => ['strtoupper'],
                    'destination' => ['strtoupper'],
                    'gate' => ['trim'],
                    'terminal' => ['trim']
                ]);

                try {
                    return $this->flightService->createFlight($sanitizedData);
                } catch (Exception $e) {
                    Logger::error('Flight creation failed', [
                        'error' => $e->getMessage(),
                        'data' => $sanitizedData
                    ]);
                    http_response_code(400);
                    error_log('Controller error: ' . $e->getMessage());
            return ['error' => 'An error occurred. Please try again.'];
                }

            default:
                http_response_code(400);
                return ['error' => 'Invalid action'];
        }
    }

    private function handlePut($action, $data) {
        switch ($action) {
            case 'update':
                Middleware::authenticate();
                Middleware::checkPermission('write', 'flights');

                $flightId = $data['id'] ?? null;
                if (!$flightId) {
                    http_response_code(400);
                    return ['error' => 'Flight ID required'];
                }

                unset($data['id']); // Remove ID from update data

                try {
                    return $this->flightService->updateFlight($flightId, $data);
                } catch (Exception $e) {
                    http_response_code(400);
                    error_log('Controller error: ' . $e->getMessage());
            return ['error' => 'An error occurred. Please try again.'];
                }

            default:
                http_response_code(400);
                return ['error' => 'Invalid action'];
        }
    }

    private function handleDelete($action, $data) {
        switch ($action) {
            case 'delete':
                Middleware::authenticate();
                Middleware::checkPermission('admin', 'flights');

                $flightId = $data['id'] ?? null;
                if (!$flightId) {
                    http_response_code(400);
                    return ['error' => 'Flight ID required'];
                }

                try {
                    return $this->flightService->deleteFlight($flightId);
                } catch (Exception $e) {
                    if ($e->getMessage() === 'Cannot delete flight with existing bookings') {
                        http_response_code(409);
                    } else {
                        http_response_code(400);
                    }
                    error_log('Controller error: ' . $e->getMessage());
            return ['error' => 'An error occurred. Please try again.'];
                }

            default:
                http_response_code(400);
                return ['error' => 'Invalid action'];
        }
    }

    private function handlePatch($action, $data) {
        switch ($action) {
            case 'assign_gate':
                Middleware::authenticate();
                Middleware::checkPermission('write', 'flights');

                $flightId = $data['flight_id'] ?? null;
                $gate = $data['gate'] ?? null;

                if (!$flightId || !$gate) {
                    http_response_code(400);
                    return ['error' => 'Flight ID and gate required'];
                }

                try {
                    return $this->flightService->assignGate($flightId, $gate);
                } catch (Exception $e) {
                    http_response_code(409);
                    error_log('Controller error: ' . $e->getMessage());
            return ['error' => 'An error occurred. Please try again.'];
                }

            case 'assign_terminal':
                Middleware::authenticate();
                Middleware::checkPermission('write', 'flights');

                $flightId = $data['flight_id'] ?? null;
                $terminal = $data['terminal'] ?? null;

                if (!$flightId || !$terminal) {
                    http_response_code(400);
                    return ['error' => 'Flight ID and terminal required'];
                }

                try {
                    return $this->flightService->assignTerminal($flightId, $terminal);
                } catch (Exception $e) {
                    http_response_code(400);
                    error_log('Controller error: ' . $e->getMessage());
            return ['error' => 'An error occurred. Please try again.'];
                }

            case 'search':
                Middleware::authenticate();
                Middleware::checkPermission('read', 'flights');

                $searchTerm = $data['search'] ?? '';
                $filters = $data['filters'] ?? [];

                if (empty($searchTerm) && empty($filters)) {
                    http_response_code(400);
                    return ['error' => 'Search term or filters required'];
                }

                return $this->flightService->searchFlights($searchTerm, $filters);

            default:
                http_response_code(400);
                return ['error' => 'Invalid action'];
        }
    }
}
?>
