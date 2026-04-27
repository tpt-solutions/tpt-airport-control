<?php
require_once __DIR__ . '/../repositories/BookingRepository.php';
require_once __DIR__ . '/../src/Logger.php';

class BookingService {
    private $bookingRepository;

    public function __construct($pdo) {
        $this->bookingRepository = new BookingRepository($pdo);
    }

    public function getBookings($filters = [], $pagination = []) {
        try {
            $bookings = $this->bookingRepository->findAll($filters, $pagination);
            $total = $this->bookingRepository->count($filters);

            return [
                'bookings' => array_map(function($booking) {
                    return $booking->toApiArray();
                }, $bookings),
                'pagination' => [
                    'page' => $pagination['page'] ?? 1,
                    'limit' => $pagination['limit'] ?? 50,
                    'total' => $total,
                    'pages' => ceil($total / ($pagination['limit'] ?? 50))
                ]
            ];
        } catch (Exception $e) {
            Logger::error('Failed to get bookings: ' . $e->getMessage());
            throw new Exception('Failed to retrieve bookings');
        }
    }

    public function getBookingById($id) {
        try {
            $booking = $this->bookingRepository->findById($id);

            if (!$booking) {
                throw new Exception('Booking not found');
            }

            return ['booking' => $booking->toApiArray()];
        } catch (Exception $e) {
            Logger::error('Failed to get booking by ID: ' . $e->getMessage());
            throw $e;
        }
    }

    public function getBookingByReference($bookingReference) {
        try {
            $booking = $this->bookingRepository->findByBookingReference($bookingReference);

            if (!$booking) {
                throw new Exception('Booking not found');
            }

            return ['booking' => $booking->toApiArray()];
        } catch (Exception $e) {
            Logger::error('Failed to get booking by reference: ' . $e->getMessage());
            throw $e;
        }
    }

    public function getBookingsByPassenger($passengerId, $filters = []) {
        try {
            $bookings = $this->bookingRepository->findByPassengerId($passengerId, $filters);

            return [
                'bookings' => array_map(function($booking) {
                    return $booking->toApiArray();
                }, $bookings)
            ];
        } catch (Exception $e) {
            Logger::error('Failed to get bookings by passenger: ' . $e->getMessage());
            throw new Exception('Failed to retrieve passenger bookings');
        }
    }

    public function createBooking($data) {
        try {
            // Validate required fields
            $this->validateBookingData($data, ['passenger_id', 'flight_id']);

            // Check if passenger exists
            if (!$this->passengerExists($data['passenger_id'])) {
                throw new Exception('Passenger not found');
            }

            // Check if flight exists and is available for booking
            $flightInfo = $this->validateFlightForBooking($data['flight_id']);
            if (!$flightInfo) {
                throw new Exception('Flight not found or not available for booking');
            }

            // Check if passenger already has a booking for this flight
            if ($this->bookingRepository->exists($data['passenger_id'], $data['flight_id'])) {
                throw new Exception('Passenger already has a booking for this flight');
            }

            // Set default values
            $data['total_amount'] = $data['total_amount'] ?? $this->calculateBookingAmount($flightInfo);
            $data['currency'] = $data['currency'] ?? 'USD';
            $data['payment_status'] = $data['payment_status'] ?? 'paid';

            $booking = $this->bookingRepository->create($data);

            Logger::info('Booking created: ' . $booking->getBookingReference() . ' for passenger ' . $data['passenger_id']);

            return [
                'message' => 'Booking created successfully',
                'booking_id' => $booking->getId(),
                'booking_reference' => $booking->getBookingReference(),
                'booking' => $booking->toApiArray()
            ];
        } catch (Exception $e) {
            Logger::error('Failed to create booking: ' . $e->getMessage());
            throw $e;
        }
    }

    public function updateBooking($id, $data) {
        try {
            // Check if booking exists
            $existingBooking = $this->bookingRepository->findById($id);
            if (!$existingBooking) {
                throw new Exception('Booking not found');
            }

            // Validate status transitions
            if (isset($data['status'])) {
                $this->validateStatusTransition($existingBooking->getStatus(), $data['status']);
            }

            $success = $this->bookingRepository->update($id, $data);

            if (!$success) {
                throw new Exception('Failed to update booking');
            }

            Logger::info('Booking updated: ID ' . $id);

            // Get updated booking
            $updatedBooking = $this->bookingRepository->findById($id);

            return [
                'message' => 'Booking updated successfully',
                'booking' => $updatedBooking->toApiArray()
            ];
        } catch (Exception $e) {
            Logger::error('Failed to update booking: ' . $e->getMessage());
            throw $e;
        }
    }

    public function cancelBooking($id) {
        try {
            // Check if booking exists
            $booking = $this->bookingRepository->findById($id);
            if (!$booking) {
                throw new Exception('Booking not found');
            }

            // Check if booking can be cancelled
            if (!$booking->canBeCancelled()) {
                throw new Exception('Booking cannot be cancelled at this time');
            }

            $this->bookingRepository->cancel($id);

            Logger::info('Booking cancelled: ID ' . $id);

            return ['message' => 'Booking cancelled successfully'];
        } catch (Exception $e) {
            Logger::error('Failed to cancel booking: ' . $e->getMessage());
            throw $e;
        }
    }

    public function checkInBooking($id) {
        try {
            // Check if booking exists
            $booking = $this->bookingRepository->findById($id);
            if (!$booking) {
                throw new Exception('Booking not found');
            }

            // Check if booking can be checked in
            if (!$booking->canCheckIn()) {
                throw new Exception('Booking is not eligible for check-in');
            }

            // Update status to checked-in
            $this->bookingRepository->update($id, ['status' => 'checked-in']);

            Logger::info('Booking checked in: ID ' . $id);

            return ['message' => 'Check-in successful'];
        } catch (Exception $e) {
            Logger::error('Failed to check in booking: ' . $e->getMessage());
            throw $e;
        }
    }

    public function searchBookings($searchTerm, $filters = []) {
        try {
            $filters['search'] = $searchTerm;
            $bookings = $this->bookingRepository->findAll($filters);

            return [
                'bookings' => array_map(function($booking) {
                    return $booking->toApiArray();
                }, $bookings)
            ];
        } catch (Exception $e) {
            Logger::error('Failed to search bookings: ' . $e->getMessage());
            throw new Exception('Failed to search bookings');
        }
    }

    public function getBookingStatistics() {
        try {
            $stats = $this->bookingRepository->getBookingStats();

            return [
                'total_bookings' => array_sum($stats),
                'status_breakdown' => $stats,
                'confirmed_bookings' => $stats['confirmed'] ?? 0,
                'cancelled_bookings' => $stats['cancelled'] ?? 0,
                'checked_in_bookings' => $stats['checked-in'] ?? 0
            ];
        } catch (Exception $e) {
            Logger::error('Failed to get booking statistics: ' . $e->getMessage());
            throw new Exception('Failed to retrieve booking statistics');
        }
    }

    public function getRevenueReport($startDate, $endDate) {
        try {
            $revenue = $this->bookingRepository->getRevenueByDateRange($startDate, $endDate);

            return [
                'revenue_data' => $revenue,
                'total_revenue' => array_sum(array_column($revenue, 'total_revenue')),
                'total_bookings' => array_sum(array_column($revenue, 'booking_count'))
            ];
        } catch (Exception $e) {
            Logger::error('Failed to get revenue report: ' . $e->getMessage());
            throw new Exception('Failed to generate revenue report');
        }
    }

    private function validateBookingData($data, $requiredFields) {
        $missing = [];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty(trim($data[$field]))) {
                $missing[] = $field;
            }
        }

        if (!empty($missing)) {
            throw new Exception('Missing required fields: ' . implode(', ', $missing));
        }
    }

    private function passengerExists($passengerId) {
        // This would typically check against a PassengerRepository
        // For now, we'll do a simple query
        global $pdo;
        $stmt = $pdo->prepare("SELECT id FROM passengers WHERE id = ?");
        $stmt->execute([$passengerId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    }

    private function validateFlightForBooking($flightId) {
        global $pdo;
        $stmt = $pdo->prepare("
            SELECT f.status, f.scheduled_departure, ac.capacity
            FROM flights f
            JOIN aircraft ac ON f.aircraft_id = ac.id
            WHERE f.id = ?
        ");
        $stmt->execute([$flightId]);
        $flight = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$flight) {
            return false;
        }

        if ($flight['status'] === 'cancelled') {
            return false;
        }

        if (strtotime($flight['scheduled_departure']) < time()) {
            return false;
        }

        return $flight;
    }

    private function calculateBookingAmount($flightInfo) {
        // Placeholder logic - in a real system, this would be based on
        // fare rules, distance, class, etc.
        return 299.99;
    }

    private function validateStatusTransition($currentStatus, $newStatus) {
        $validTransitions = [
            'confirmed' => ['checked-in', 'cancelled'],
            'checked-in' => ['cancelled'],
            'cancelled' => [] // Cannot change from cancelled
        ];

        if (!isset($validTransitions[$currentStatus]) || !in_array($newStatus, $validTransitions[$currentStatus])) {
            throw new Exception("Invalid status transition from {$currentStatus} to {$newStatus}");
        }
    }
}
?>
