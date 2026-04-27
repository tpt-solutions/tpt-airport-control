<?php
require_once __DIR__ . '/../repositories/PassengerRepository.php';
require_once __DIR__ . '/../src/Logger.php';

class PassengerService {
    private $passengerRepository;

    public function __construct($pdo) {
        $this->passengerRepository = new PassengerRepository($pdo);
    }

    public function getPassengers($filters = [], $pagination = []) {
        try {
            $passengers = $this->passengerRepository->findAll($filters, $pagination);
            $total = $this->passengerRepository->count($filters);

            return [
                'passengers' => array_map(function($passenger) {
                    return $passenger->toApiArray();
                }, $passengers),
                'pagination' => [
                    'page' => $pagination['page'] ?? 1,
                    'limit' => $pagination['limit'] ?? 50,
                    'total' => $total,
                    'pages' => ceil($total / ($pagination['limit'] ?? 50))
                ]
            ];
        } catch (Exception $e) {
            Logger::error('Failed to get passengers: ' . $e->getMessage());
            throw new Exception('Failed to retrieve passengers');
        }
    }

    public function getPassengerById($id) {
        try {
            $passenger = $this->passengerRepository->findById($id);

            if (!$passenger) {
                throw new Exception('Passenger not found');
            }

            // Get recent bookings
            $recentBookings = $this->passengerRepository->getRecentBookings($id);

            $passengerData = $passenger->toApiArray();
            $passengerData['recent_bookings'] = $recentBookings;

            return ['passenger' => $passengerData];
        } catch (Exception $e) {
            Logger::error('Failed to get passenger by ID: ' . $e->getMessage());
            throw $e;
        }
    }

    public function getPassengerByEmail($email) {
        try {
            $passenger = $this->passengerRepository->findByEmail($email);

            if (!$passenger) {
                throw new Exception('Passenger not found');
            }

            return ['passenger' => $passenger->toApiArray()];
        } catch (Exception $e) {
            Logger::error('Failed to get passenger by email: ' . $e->getMessage());
            throw $e;
        }
    }

    public function getPassengerByPassport($passportNumber) {
        try {
            $passenger = $this->passengerRepository->findByPassportNumber($passportNumber);

            if (!$passenger) {
                throw new Exception('Passenger not found');
            }

            return ['passenger' => $passenger->toApiArray()];
        } catch (Exception $e) {
            Logger::error('Failed to get passenger by passport: ' . $e->getMessage());
            throw $e;
        }
    }

    public function createPassenger($data) {
        try {
            // Validate required fields
            $this->validatePassengerData($data, ['first_name', 'last_name']);

            // Create passenger object for validation
            $passenger = new Passenger($data);
            $validationErrors = $passenger->validateForCreation();

            if (!empty($validationErrors)) {
                throw new Exception('Validation failed: ' . implode(', ', $validationErrors));
            }

            // Check for duplicates
            if (!empty($data['email']) && $this->passengerRepository->existsByEmail($data['email'])) {
                throw new Exception('A passenger with this email already exists');
            }

            if (!empty($data['passport_number']) && $this->passengerRepository->existsByPassport($data['passport_number'])) {
                throw new Exception('A passenger with this passport number already exists');
            }

            $createdPassenger = $this->passengerRepository->create($data);

            Logger::info('Passenger created: ' . $createdPassenger->getFullName() . ' (ID: ' . $createdPassenger->getId() . ')');

            return [
                'message' => 'Passenger created successfully',
                'passenger_id' => $createdPassenger->getId(),
                'passenger' => $createdPassenger->toApiArray()
            ];
        } catch (Exception $e) {
            Logger::error('Failed to create passenger: ' . $e->getMessage());
            throw $e;
        }
    }

    public function updatePassenger($id, $data) {
        try {
            // Check if passenger exists
            $existingPassenger = $this->passengerRepository->findById($id);
            if (!$existingPassenger) {
                throw new Exception('Passenger not found');
            }

            // Create updated passenger object for validation
            $updatedData = array_merge($existingPassenger->toArray(), $data);
            $passenger = new Passenger($updatedData);
            $validationErrors = $passenger->validateForUpdate();

            if (!empty($validationErrors)) {
                throw new Exception('Validation failed: ' . implode(', ', $validationErrors));
            }

            // Check for email duplicates (excluding current passenger)
            if (!empty($data['email']) && $data['email'] !== $existingPassenger->getEmail()) {
                $existingWithEmail = $this->passengerRepository->findByEmail($data['email']);
                if ($existingWithEmail && $existingWithEmail->getId() != $id) {
                    throw new Exception('A passenger with this email already exists');
                }
            }

            // Check for passport duplicates (excluding current passenger)
            if (!empty($data['passport_number']) && $data['passport_number'] !== $existingPassenger->getPassportNumber()) {
                $existingWithPassport = $this->passengerRepository->findByPassportNumber($data['passport_number']);
                if ($existingWithPassport && $existingWithPassport->getId() != $id) {
                    throw new Exception('A passenger with this passport number already exists');
                }
            }

            $success = $this->passengerRepository->update($id, $data);

            if (!$success) {
                throw new Exception('Failed to update passenger');
            }

            Logger::info('Passenger updated: ID ' . $id);

            // Get updated passenger
            $updatedPassenger = $this->passengerRepository->findById($id);

            return [
                'message' => 'Passenger updated successfully',
                'passenger' => $updatedPassenger->toApiArray()
            ];
        } catch (Exception $e) {
            Logger::error('Failed to update passenger: ' . $e->getMessage());
            throw $e;
        }
    }

    public function deletePassenger($id) {
        try {
            // Check if passenger exists
            $passenger = $this->passengerRepository->findById($id);
            if (!$passenger) {
                throw new Exception('Passenger not found');
            }

            // Check if passenger has active bookings
            if ($this->passengerRepository->hasActiveBookings($id)) {
                throw new Exception('Cannot delete passenger with active bookings');
            }

            $this->passengerRepository->delete($id);

            Logger::info('Passenger deleted: ID ' . $id . ' (' . $passenger->getFullName() . ')');

            return ['message' => 'Passenger deleted successfully'];
        } catch (Exception $e) {
            Logger::error('Failed to delete passenger: ' . $e->getMessage());
            throw $e;
        }
    }

    public function searchPassengers($searchTerm, $filters = []) {
        try {
            $filters['search'] = $searchTerm;
            $passengers = $this->passengerRepository->findAll($filters);

            return [
                'passengers' => array_map(function($passenger) {
                    return $passenger->toApiArray();
                }, $passengers)
            ];
        } catch (Exception $e) {
            Logger::error('Failed to search passengers: ' . $e->getMessage());
            throw new Exception('Failed to search passengers');
        }
    }

    public function getPassengersByFlight($flightId) {
        try {
            $passengers = $this->passengerRepository->getPassengersByFlight($flightId);

            return [
                'passengers' => array_map(function($passenger) {
                    // Mask sensitive data for flight manifests
                    $passenger['passport_number'] = (new Passenger($passenger))->getMaskedPassportNumber();
                    $passenger['phone'] = (new Passenger($passenger))->getMaskedPhoneNumber();
                    return $passenger;
                }, $passengers)
            ];
        } catch (Exception $e) {
            Logger::error('Failed to get passengers by flight: ' . $e->getMessage());
            throw new Exception('Failed to retrieve flight passengers');
        }
    }

    public function getPassengerStatistics() {
        try {
            $stats = $this->passengerRepository->getPassengerStatistics();

            return [
                'total_passengers' => $stats['total_passengers'],
                'adult_passengers' => $stats['adult_passengers'],
                'passengers_with_email' => $stats['passengers_with_email'],
                'passengers_with_passport' => $stats['passengers_with_passport'],
                'average_age' => round($stats['average_age'], 1),
                'completion_rate' => [
                    'email' => $stats['total_passengers'] > 0 ? round(($stats['passengers_with_email'] / $stats['total_passengers']) * 100, 1) : 0,
                    'passport' => $stats['total_passengers'] > 0 ? round(($stats['passengers_with_passport'] / $stats['total_passengers']) * 100, 1) : 0
                ]
            ];
        } catch (Exception $e) {
            Logger::error('Failed to get passenger statistics: ' . $e->getMessage());
            throw new Exception('Failed to retrieve passenger statistics');
        }
    }

    public function getNationalityDistribution() {
        try {
            $distribution = $this->passengerRepository->getNationalityDistribution();

            return [
                'nationalities' => $distribution,
                'total_represented' => array_sum(array_column($distribution, 'count'))
            ];
        } catch (Exception $e) {
            Logger::error('Failed to get nationality distribution: ' . $e->getMessage());
            throw new Exception('Failed to retrieve nationality distribution');
        }
    }

    public function getPassengerProfile($passengerId) {
        try {
            $passenger = $this->passengerRepository->findById($passengerId);

            if (!$passenger) {
                throw new Exception('Passenger not found');
            }

            $profile = $passenger->toPublicArray();
            $profile['profile_completion'] = $passenger->getProfileCompletionPercentage();
            $profile['can_book_flights'] = $passenger->canBookFlights();
            $profile['age'] = $passenger->getAge();
            $profile['is_adult'] = $passenger->isAdult();

            return ['profile' => $profile];
        } catch (Exception $e) {
            Logger::error('Failed to get passenger profile: ' . $e->getMessage());
            throw $e;
        }
    }

    private function validatePassengerData($data, $requiredFields) {
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
}
?>
