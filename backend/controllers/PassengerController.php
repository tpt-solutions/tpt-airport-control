<?php
require_once __DIR__ . '/../services/PassengerService.php';
require_once __DIR__ . '/../src/Auth.php';

class PassengerController {
    private $passengerService;

    public function __construct($pdo) {
        $this->passengerService = new PassengerService($pdo);
    }

    public function getPassengers() {
        try {
            // Check permissions - operators and admins can view passengers
            $currentUser = Auth::getCurrentUser();
            if (!$currentUser || !Auth::hasPermission('read', 'passengers')) {
                http_response_code(403);
                return ['error' => 'Access denied'];
            }

            // Build filters
            $filters = [];
            $pagination = [];

            // Parse query parameters
            if (isset($_GET['search'])) {
                $filters['search'] = $_GET['search'];
            }
            if (isset($_GET['flight_id'])) {
                $filters['flight_id'] = (int)$_GET['flight_id'];
            }
            if (isset($_GET['nationality'])) {
                $filters['nationality'] = $_GET['nationality'];
            }
            if (isset($_GET['has_active_bookings'])) {
                $filters['has_active_bookings'] = filter_var($_GET['has_active_bookings'], FILTER_VALIDATE_BOOLEAN);
            }

            // Pagination
            $pagination['page'] = (int)($_GET['page'] ?? 1);
            $pagination['limit'] = (int)($_GET['limit'] ?? 50);
            $pagination['offset'] = ($pagination['page'] - 1) * $pagination['limit'];

            $result = $this->passengerService->getPassengers($filters, $pagination);

            return $result;
        } catch (Exception $e) {
            http_response_code(500);
            return ['error' => 'Failed to retrieve passengers', 'message' => $e->getMessage()];
        }
    }

    public function getPassenger($id) {
        try {
            // Check permissions
            $currentUser = Auth::getCurrentUser();
            if (!$currentUser) {
                http_response_code(401);
                return ['error' => 'Authentication required'];
            }

            // Regular passengers can only view their own profile
            if ($currentUser['role_name'] === 'passenger' && $currentUser['passenger_id'] != $id) {
                http_response_code(403);
                return ['error' => 'Access denied'];
            }

            // Staff can view any passenger
            if (!Auth::hasPermission('read', 'passengers')) {
                http_response_code(403);
                return ['error' => 'Access denied'];
            }

            $result = $this->passengerService->getPassengerById($id);

            return $result;
        } catch (Exception $e) {
            if ($e->getMessage() === 'Passenger not found') {
                http_response_code(404);
            } else {
                http_response_code(500);
            }
            return ['error' => $e->getMessage()];
        }
    }

    public function getPassengerByEmail($email) {
        try {
            // Check permissions - staff only
            if (!Auth::hasPermission('read', 'passengers')) {
                http_response_code(403);
                return ['error' => 'Access denied'];
            }

            $result = $this->passengerService->getPassengerByEmail($email);

            return $result;
        } catch (Exception $e) {
            if ($e->getMessage() === 'Passenger not found') {
                http_response_code(404);
            } else {
                http_response_code(500);
            }
            return ['error' => $e->getMessage()];
        }
    }

    public function getPassengerByPassport($passportNumber) {
        try {
            // Check permissions - staff only
            if (!Auth::hasPermission('read', 'passengers')) {
                http_response_code(403);
                return ['error' => 'Access denied'];
            }

            $result = $this->passengerService->getPassengerByPassport($passportNumber);

            return $result;
        } catch (Exception $e) {
            if ($e->getMessage() === 'Passenger not found') {
                http_response_code(404);
            } else {
                http_response_code(500);
            }
            return ['error' => $e->getMessage()];
        }
    }

    public function getPassengerProfile($passengerId) {
        try {
            // Check permissions
            $currentUser = Auth::getCurrentUser();
            if (!$currentUser) {
                http_response_code(401);
                return ['error' => 'Authentication required'];
            }

            // Regular passengers can only view their own profile
            if ($currentUser['role_name'] === 'passenger' && $currentUser['passenger_id'] != $passengerId) {
                http_response_code(403);
                return ['error' => 'Access denied'];
            }

            // Staff can view any passenger profile
            if (!Auth::hasPermission('read', 'passengers')) {
                http_response_code(403);
                return ['error' => 'Access denied'];
            }

            $result = $this->passengerService->getPassengerProfile($passengerId);

            return $result;
        } catch (Exception $e) {
            if ($e->getMessage() === 'Passenger not found') {
                http_response_code(404);
            } else {
                http_response_code(500);
            }
            return ['error' => $e->getMessage()];
        }
    }

    public function createPassenger() {
        try {
            // Check permissions - staff can create passengers
            if (!Auth::hasPermission('write', 'passengers')) {
                http_response_code(403);
                return ['error' => 'Access denied'];
            }

            $data = json_decode(file_get_contents('php://input'), true);

            if (!$data) {
                http_response_code(400);
                return ['error' => 'Invalid JSON data'];
            }

            $result = $this->passengerService->createPassenger($data);

            http_response_code(201);
            return $result;
        } catch (Exception $e) {
            if (str_contains($e->getMessage(), 'Missing required fields') ||
                str_contains($e->getMessage(), 'Validation failed') ||
                str_contains($e->getMessage(), 'already exists')) {
                http_response_code(400);
            } else {
                http_response_code(500);
            }
            return ['error' => $e->getMessage()];
        }
    }

    public function updatePassenger($id) {
        try {
            // Check permissions
            $currentUser = Auth::getCurrentUser();
            if (!$currentUser) {
                http_response_code(401);
                return ['error' => 'Authentication required'];
            }

            $data = json_decode(file_get_contents('php://input'), true);

            if (!$data) {
                http_response_code(400);
                return ['error' => 'Invalid JSON data'];
            }

            // Regular passengers can only update their own profile
            if ($currentUser['role_name'] === 'passenger' && $currentUser['passenger_id'] != $id) {
                http_response_code(403);
                return ['error' => 'Access denied'];
            }

            // Staff can update any passenger
            if (!Auth::hasPermission('write', 'passengers')) {
                http_response_code(403);
                return ['error' => 'Access denied'];
            }

            $result = $this->passengerService->updatePassenger($id, $data);

            return $result;
        } catch (Exception $e) {
            if ($e->getMessage() === 'Passenger not found') {
                http_response_code(404);
            } elseif (str_contains($e->getMessage(), 'Validation failed') ||
                      str_contains($e->getMessage(), 'already exists')) {
                http_response_code(400);
            } else {
                http_response_code(500);
            }
            return ['error' => $e->getMessage()];
        }
    }

    public function deletePassenger($id) {
        try {
            // Check permissions - only admins can delete passengers
            if (!Auth::hasPermission('admin', 'passengers')) {
                http_response_code(403);
                return ['error' => 'Access denied'];
            }

            $result = $this->passengerService->deletePassenger($id);

            return $result;
        } catch (Exception $e) {
            if ($e->getMessage() === 'Passenger not found') {
                http_response_code(404);
            } elseif (str_contains($e->getMessage(), 'Cannot delete passenger')) {
                http_response_code(409);
            } else {
                http_response_code(500);
            }
            return ['error' => $e->getMessage()];
        }
    }

    public function searchPassengers() {
        try {
            // Check permissions
            $currentUser = Auth::getCurrentUser();
            if (!$currentUser || !Auth::hasPermission('read', 'passengers')) {
                http_response_code(403);
                return ['error' => 'Access denied'];
            }

            if (!isset($_GET['q']) || empty(trim($_GET['q']))) {
                http_response_code(400);
                return ['error' => 'Search query is required'];
            }

            $searchTerm = trim($_GET['q']);
            $filters = [];

            // Regular passengers can only search their own records (though this is limited use)
            if ($currentUser['role_name'] === 'passenger') {
                $filters['passenger_id'] = $currentUser['passenger_id'];
            }

            $result = $this->passengerService->searchPassengers($searchTerm, $filters);

            return $result;
        } catch (Exception $e) {
            http_response_code(500);
            return ['error' => 'Search failed', 'message' => $e->getMessage()];
        }
    }

    public function getPassengersByFlight($flightId) {
        try {
            // Check permissions - staff can view flight manifests
            if (!Auth::hasPermission('read', 'passengers')) {
                http_response_code(403);
                return ['error' => 'Access denied'];
            }

            $result = $this->passengerService->getPassengersByFlight($flightId);

            return $result;
        } catch (Exception $e) {
            http_response_code(500);
            return ['error' => 'Failed to retrieve flight passengers', 'message' => $e->getMessage()];
        }
    }

    public function getStatistics() {
        try {
            // Check permissions - staff can view statistics
            if (!Auth::hasPermission('read', 'analytics')) {
                http_response_code(403);
                return ['error' => 'Access denied'];
            }

            $result = $this->passengerService->getPassengerStatistics();

            return $result;
        } catch (Exception $e) {
            http_response_code(500);
            return ['error' => 'Failed to retrieve statistics', 'message' => $e->getMessage()];
        }
    }

    public function getNationalityDistribution() {
        try {
            // Check permissions - staff can view analytics
            if (!Auth::hasPermission('read', 'analytics')) {
                http_response_code(403);
                return ['error' => 'Access denied'];
            }

            $result = $this->passengerService->getNationalityDistribution();

            return $result;
        } catch (Exception $e) {
            http_response_code(500);
            return ['error' => 'Failed to retrieve nationality distribution', 'message' => $e->getMessage()];
        }
    }
}
?>
