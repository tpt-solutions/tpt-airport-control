<?php
require_once __DIR__ . '/../repositories/FlightRepository.php';
require_once __DIR__ . '/../src/Logger.php';

use TPT\FlightControl\Logger;

class FlightService {
    private $flightRepository;

    public function __construct($pdo, ?FlightRepository $repository = null) {
        $this->flightRepository = $repository ?? new FlightRepository($pdo);
    }

    public function getFlights($filters = [], $pagination = []) {
        try {
            $flights = $this->flightRepository->findAll($filters, $pagination);
            $total = $this->flightRepository->count($filters);

            return [
                'flights' => array_map(function($flight) {
                    return $flight->toApiArray();
                }, $flights),
                'pagination' => [
                    'page' => $pagination['page'] ?? 1,
                    'limit' => $pagination['limit'] ?? 50,
                    'total' => $total,
                    'pages' => ceil($total / ($pagination['limit'] ?? 50))
                ]
            ];
        } catch (Exception $e) {
            Logger::error('Failed to get flights: ' . $e->getMessage());
            throw new Exception('Failed to retrieve flights');
        }
    }

    public function getFlightById($id) {
        try {
            $flight = $this->flightRepository->findById($id);

            if (!$flight) {
                throw new Exception('Flight not found');
            }

            return ['flight' => $flight->toApiArray()];
        } catch (Exception $e) {
            Logger::error('Failed to get flight by ID: ' . $e->getMessage());
            throw $e;
        }
    }

    public function createFlight($data) {
        try {
            // Validate required fields
            $this->validateFlightData($data, ['flight_number', 'airline_id', 'aircraft_id', 'origin', 'destination', 'scheduled_departure', 'scheduled_arrival']);

            // Check if flight number already exists
            $existingFlight = $this->flightRepository->findByFlightNumber($data['flight_number']);
            if ($existingFlight) {
                throw new Exception('Flight number already exists');
            }

            // Validate dates
            $this->validateFlightDates($data['scheduled_departure'], $data['scheduled_arrival']);

            $flight = $this->flightRepository->create($data);

            Logger::info('Flight created: ' . $data['flight_number'] . ' (ID: ' . $flight->getId() . ')');

            return [
                'message' => 'Flight created successfully',
                'flight_id' => $flight->getId(),
                'flight' => $flight->toApiArray()
            ];
        } catch (Exception $e) {
            Logger::error('Failed to create flight: ' . $e->getMessage());
            throw $e;
        }
    }

    public function updateFlight($id, $data) {
        try {
            // Check if flight exists
            $existingFlight = $this->flightRepository->findById($id);
            if (!$existingFlight) {
                throw new Exception('Flight not found');
            }

            // Validate data
            if (isset($data['scheduled_departure']) && isset($data['scheduled_arrival'])) {
                $this->validateFlightDates($data['scheduled_departure'], $data['scheduled_arrival']);
            }

            // If flight number is being changed, check for duplicates
            if (isset($data['flight_number']) && $data['flight_number'] !== $existingFlight->getFlightNumber()) {
                $duplicateFlight = $this->flightRepository->findByFlightNumber($data['flight_number']);
                if ($duplicateFlight) {
                    throw new Exception('Flight number already exists');
                }
            }

            $success = $this->flightRepository->update($id, $data);

            if (!$success) {
                throw new Exception('Failed to update flight');
            }

            Logger::info('Flight updated: ID ' . $id);

            // Get updated flight
            $updatedFlight = $this->flightRepository->findById($id);

            return [
                'message' => 'Flight updated successfully',
                'flight' => $updatedFlight->toApiArray()
            ];
        } catch (Exception $e) {
            Logger::error('Failed to update flight: ' . $e->getMessage());
            throw $e;
        }
    }

    public function deleteFlight($id) {
        try {
            // Check if flight exists
            $flight = $this->flightRepository->findById($id);
            if (!$flight) {
                throw new Exception('Flight not found');
            }

            $this->flightRepository->delete($id);

            Logger::info('Flight deleted: ID ' . $id);

            return ['message' => 'Flight deleted successfully'];
        } catch (Exception $e) {
            Logger::error('Failed to delete flight: ' . $e->getMessage());
            throw $e;
        }
    }

    public function assignGate($flightId, $gate) {
        try {
            // Validate flight exists
            $flight = $this->flightRepository->findById($flightId);
            if (!$flight) {
                throw new Exception('Flight not found');
            }

            $this->flightRepository->assignGate($flightId, $gate);

            Logger::info('Gate assigned: Flight ' . $flightId . ' -> Gate ' . $gate);

            return ['message' => 'Gate assigned successfully'];
        } catch (Exception $e) {
            Logger::error('Failed to assign gate: ' . $e->getMessage());
            throw $e;
        }
    }

    public function assignTerminal($flightId, $terminal) {
        try {
            // Validate flight exists
            $flight = $this->flightRepository->findById($flightId);
            if (!$flight) {
                throw new Exception('Flight not found');
            }

            $this->flightRepository->assignTerminal($flightId, $terminal);

            Logger::info('Terminal assigned: Flight ' . $flightId . ' -> Terminal ' . $terminal);

            return ['message' => 'Terminal assigned successfully'];
        } catch (Exception $e) {
            Logger::error('Failed to assign terminal: ' . $e->getMessage());
            throw $e;
        }
    }

    public function searchFlights($searchTerm, $filters = []) {
        try {
            $flights = $this->flightRepository->search($searchTerm, $filters);

            return [
                'flights' => array_map(function($flight) {
                    return $flight->toApiArray();
                }, $flights)
            ];
        } catch (Exception $e) {
            Logger::error('Failed to search flights: ' . $e->getMessage());
            throw new Exception('Failed to search flights');
        }
    }

    public function getActiveFlights() {
        try {
            $flights = $this->flightRepository->getActiveFlights();

            return [
                'flights' => array_map(function($flight) {
                    return $flight->toApiArray();
                }, $flights)
            ];
        } catch (Exception $e) {
            Logger::error('Failed to get active flights: ' . $e->getMessage());
            throw new Exception('Failed to retrieve active flights');
        }
    }

    public function getFlightStatistics() {
        try {
            $totalFlights = $this->flightRepository->count();
            $activeFlights = $this->flightRepository->count(['status' => 'scheduled']);
            $departedFlights = $this->flightRepository->count(['status' => 'departed']);
            $arrivedFlights = $this->flightRepository->count(['status' => 'arrived']);

            return [
                'total_flights' => $totalFlights,
                'active_flights' => $activeFlights,
                'departed_flights' => $departedFlights,
                'arrived_flights' => $arrivedFlights
            ];
        } catch (Exception $e) {
            Logger::error('Failed to get flight statistics: ' . $e->getMessage());
            throw new Exception('Failed to retrieve flight statistics');
        }
    }

    private function validateFlightData($data, $requiredFields) {
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

    private function validateFlightDates($departure, $arrival) {
        $departureTime = strtotime($departure);
        $arrivalTime = strtotime($arrival);

        if (!$departureTime || !$arrivalTime) {
            throw new Exception('Invalid date format');
        }

        if ($arrivalTime <= $departureTime) {
            throw new Exception('Arrival time must be after departure time');
        }

        // Check if departure is not in the past (with some buffer)
        if ($departureTime < (time() - 3600)) { // 1 hour buffer
            throw new Exception('Departure time cannot be in the past');
        }
    }

    public function validateFlightForOperation($flightId, $operation) {
        $flight = $this->flightRepository->findById($flightId);
        if (!$flight) {
            throw new Exception('Flight not found');
        }

        switch ($operation) {
            case 'boarding':
                if (!in_array($flight->getStatus(), ['scheduled'])) {
                    throw new Exception('Flight must be scheduled to start boarding');
                }
                break;
            case 'departure':
                if (!in_array($flight->getStatus(), ['boarding'])) {
                    throw new Exception('Flight must be boarding to depart');
                }
                break;
            case 'arrival':
                if (!in_array($flight->getStatus(), ['departed'])) {
                    throw new Exception('Flight must be departed to arrive');
                }
                break;
        }

        return $flight;
    }
}
?>
