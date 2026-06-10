<?php
require_once __DIR__ . '/../services/BookingService.php';
require_once __DIR__ . '/../src/Auth.php';

class BookingController {
    private $bookingService;

    public function __construct($pdo) {
        $this->bookingService = new BookingService($pdo);
    }

    public function getBookings() {
        try {
            // Check permissions
            $currentUser = Auth::getCurrentUser();
            if (!$currentUser) {
                http_response_code(401);
                return ['error' => 'Authentication required'];
            }

            // Build filters
            $filters = [];
            $pagination = [];

            // Regular passengers can only see their own bookings
            if ($currentUser['role_name'] === 'passenger') {
                $filters['passenger_id'] = $currentUser['passenger_id'];
            }

            // Parse query parameters
            if (isset($_GET['status'])) {
                $filters['status'] = $_GET['status'];
            }
            if (isset($_GET['passenger_id']) && Auth::hasPermission('read', 'passengers')) {
                $filters['passenger_id'] = (int)$_GET['passenger_id'];
            }
            if (isset($_GET['flight_id'])) {
                $filters['flight_id'] = (int)$_GET['flight_id'];
            }
            if (isset($_GET['payment_status'])) {
                $filters['payment_status'] = $_GET['payment_status'];
            }
            if (isset($_GET['search'])) {
                $filters['search'] = $_GET['search'];
            }

            // Pagination
            $pagination['page'] = max(1, (int)($_GET['page'] ?? 1));
            $pagination['limit'] = min(100, max(1, (int)($_GET['limit'] ?? 50)));
            $pagination['offset'] = ($pagination['page'] - 1) * $pagination['limit'];

            $result = $this->bookingService->getBookings($filters, $pagination);

            return $result;
        } catch (Exception $e) {
            http_response_code(500);
            return ['error' => 'Failed to retrieve bookings', 'message' => 'An error occurred. Please try again.'];
        }
    }

    public function getBooking($id) {
        try {
            // Check permissions
            $currentUser = Auth::getCurrentUser();
            if (!$currentUser) {
                http_response_code(401);
                return ['error' => 'Authentication required'];
            }

            $result = $this->bookingService->getBookingById($id);

            // Regular passengers can only view their own bookings
            if ($currentUser['role_name'] === 'passenger') {
                if ($result['booking']['passenger_id'] != $currentUser['passenger_id']) {
                    http_response_code(403);
                    return ['error' => 'Access denied'];
                }
            }

            // Staff need read permission for passengers
            if (!Auth::hasPermission('read', 'passengers')) {
                http_response_code(403);
                return ['error' => 'Access denied'];
            }

            return $result;
        } catch (Exception $e) {
            if ($e->getMessage() === 'Booking not found') {
                http_response_code(404);
            } else {
                http_response_code(500);
            }
            error_log('Controller error: ' . $e->getMessage());
            return ['error' => 'An error occurred. Please try again.'];
        }
    }

    public function getBookingByReference($bookingReference) {
        try {
            // Check permissions
            $currentUser = Auth::getCurrentUser();
            if (!$currentUser) {
                http_response_code(401);
                return ['error' => 'Authentication required'];
            }

            $result = $this->bookingService->getBookingByReference($bookingReference);

            // Regular passengers can only view their own bookings
            if ($currentUser['role_name'] === 'passenger') {
                if ($result['booking']['passenger_id'] != $currentUser['passenger_id']) {
                    http_response_code(403);
                    return ['error' => 'Access denied'];
                }
            }

            // Staff need read permission for passengers
            if (!Auth::hasPermission('read', 'passengers')) {
                http_response_code(403);
                return ['error' => 'Access denied'];
            }

            return $result;
        } catch (Exception $e) {
            if ($e->getMessage() === 'Booking not found') {
                http_response_code(404);
            } else {
                http_response_code(500);
            }
            error_log('Controller error: ' . $e->getMessage());
            return ['error' => 'An error occurred. Please try again.'];
        }
    }

    public function getBookingsByPassenger($passengerId) {
        try {
            // Check permissions
            $currentUser = Auth::getCurrentUser();
            if (!$currentUser) {
                http_response_code(401);
                return ['error' => 'Authentication required'];
            }

            // Regular passengers can only view their own bookings
            if ($currentUser['role_name'] === 'passenger' && $passengerId != $currentUser['passenger_id']) {
                http_response_code(403);
                return ['error' => 'Access denied'];
            }

            // Staff need read permission for passengers
            if (!Auth::hasPermission('read', 'passengers')) {
                http_response_code(403);
                return ['error' => 'Access denied'];
            }

            $filters = [];
            if (isset($_GET['status'])) {
                $filters['status'] = $_GET['status'];
            }

            $result = $this->bookingService->getBookingsByPassenger($passengerId, $filters);

            return $result;
        } catch (Exception $e) {
            http_response_code(500);
            return ['error' => 'Failed to retrieve passenger bookings', 'message' => 'An error occurred. Please try again.'];
        }
    }

    public function createBooking() {
        try {
            // Check permissions - staff can create bookings for passengers
            if (!Auth::hasPermission('write', 'passengers')) {
                http_response_code(403);
                return ['error' => 'Access denied'];
            }

            $data = json_decode(file_get_contents('php://input'), true);

            if (!$data) {
                http_response_code(400);
                return ['error' => 'Invalid JSON data'];
            }

            $result = $this->bookingService->createBooking($data);

            http_response_code(201);
            return $result;
        } catch (Exception $e) {
            if (str_contains($e->getMessage(), 'Missing required fields') ||
                str_contains($e->getMessage(), 'Passenger not found') ||
                str_contains($e->getMessage(), 'Flight not found') ||
                str_contains($e->getMessage(), 'already has a booking')) {
                http_response_code(400);
            } else {
                http_response_code(500);
            }
            error_log('Controller error: ' . $e->getMessage());
            return ['error' => 'An error occurred. Please try again.'];
        }
    }

    public function updateBooking($id) {
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

            // Get current booking to check ownership
            $bookingResult = $this->bookingService->getBookingById($id);
            $booking = $bookingResult['booking'];

            // Regular passengers can only update their own bookings
            if ($currentUser['role_name'] === 'passenger' && $booking['passenger_id'] != $currentUser['passenger_id']) {
                http_response_code(403);
                return ['error' => 'Access denied'];
            }

            // Staff need write permission for passengers
            if (!Auth::hasPermission('write', 'passengers')) {
                http_response_code(403);
                return ['error' => 'Access denied'];
            }

            // Only staff can update status
            if (isset($data['status']) && !Auth::hasPermission('write', 'passengers')) {
                unset($data['status']);
            }

            $result = $this->bookingService->updateBooking($id, $data);

            return $result;
        } catch (Exception $e) {
            if ($e->getMessage() === 'Booking not found') {
                http_response_code(404);
            } elseif (str_contains($e->getMessage(), 'Invalid status transition')) {
                http_response_code(400);
            } else {
                http_response_code(500);
            }
            error_log('Controller error: ' . $e->getMessage());
            return ['error' => 'An error occurred. Please try again.'];
        }
    }

    public function cancelBooking($id) {
        try {
            // Check permissions
            $currentUser = Auth::getCurrentUser();
            if (!$currentUser) {
                http_response_code(401);
                return ['error' => 'Authentication required'];
            }

            // Get current booking to check ownership
            $bookingResult = $this->bookingService->getBookingById($id);
            $booking = $bookingResult['booking'];

            // Regular passengers can only cancel their own bookings
            if ($currentUser['role_name'] === 'passenger' && $booking['passenger_id'] != $currentUser['passenger_id']) {
                http_response_code(403);
                return ['error' => 'Access denied'];
            }

            // Staff need write permission for passengers
            if (!Auth::hasPermission('write', 'passengers')) {
                http_response_code(403);
                return ['error' => 'Access denied'];
            }

            $result = $this->bookingService->cancelBooking($id);

            return $result;
        } catch (Exception $e) {
            if ($e->getMessage() === 'Booking not found') {
                http_response_code(404);
            } elseif (str_contains($e->getMessage(), 'cannot be cancelled')) {
                http_response_code(400);
            } else {
                http_response_code(500);
            }
            error_log('Controller error: ' . $e->getMessage());
            return ['error' => 'An error occurred. Please try again.'];
        }
    }

    public function checkInBooking($id) {
        try {
            // Check permissions
            $currentUser = Auth::getCurrentUser();
            if (!$currentUser) {
                http_response_code(401);
                return ['error' => 'Authentication required'];
            }

            // Get current booking to check ownership
            $bookingResult = $this->bookingService->getBookingById($id);
            $booking = $bookingResult['booking'];

            // Regular passengers can only check in their own bookings
            if ($currentUser['role_name'] === 'passenger' && $booking['passenger_id'] != $currentUser['passenger_id']) {
                http_response_code(403);
                return ['error' => 'Access denied'];
            }

            // Staff need write permission for passengers
            if (!Auth::hasPermission('write', 'passengers')) {
                http_response_code(403);
                return ['error' => 'Access denied'];
            }

            $result = $this->bookingService->checkInBooking($id);

            return $result;
        } catch (Exception $e) {
            if ($e->getMessage() === 'Booking not found') {
                http_response_code(404);
            } elseif (str_contains($e->getMessage(), 'not eligible for check-in')) {
                http_response_code(400);
            } else {
                http_response_code(500);
            }
            error_log('Controller error: ' . $e->getMessage());
            return ['error' => 'An error occurred. Please try again.'];
        }
    }

    public function searchBookings() {
        try {
            // Check permissions
            $currentUser = Auth::getCurrentUser();
            if (!$currentUser) {
                http_response_code(401);
                return ['error' => 'Authentication required'];
            }

            if (!isset($_GET['q']) || empty(trim($_GET['q']))) {
                http_response_code(400);
                return ['error' => 'Search query is required'];
            }

            $searchTerm = trim($_GET['q']);
            $filters = [];

            // Regular passengers can only search their own bookings
            if ($currentUser['role_name'] === 'passenger') {
                $filters['passenger_id'] = $currentUser['passenger_id'];
            }

            $result = $this->bookingService->searchBookings($searchTerm, $filters);

            return $result;
        } catch (Exception $e) {
            http_response_code(500);
            return ['error' => 'Search failed', 'message' => 'An error occurred. Please try again.'];
        }
    }

    public function getStatistics() {
        try {
            // Check permissions - only staff can view statistics
            if (!Auth::hasPermission('read', 'analytics')) {
                http_response_code(403);
                return ['error' => 'Access denied'];
            }

            $result = $this->bookingService->getBookingStatistics();

            return $result;
        } catch (Exception $e) {
            http_response_code(500);
            return ['error' => 'Failed to retrieve statistics', 'message' => 'An error occurred. Please try again.'];
        }
    }

    public function getRevenueReport() {
        try {
            // Check permissions - only staff can view revenue reports
            if (!Auth::hasPermission('read', 'analytics')) {
                http_response_code(403);
                return ['error' => 'Access denied'];
            }

            $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
            $endDate = $_GET['end_date'] ?? date('Y-m-d');

            $result = $this->bookingService->getRevenueReport($startDate, $endDate);

            return $result;
        } catch (Exception $e) {
            http_response_code(500);
            return ['error' => 'Failed to generate revenue report', 'message' => 'An error occurred. Please try again.'];
        }
    }
}
?>
