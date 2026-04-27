<?php

/**
 * Ground Handling System Integration
 *
 * This integration manages comprehensive ground handling operations:
 * - Aircraft marshalling and parking
 * - Passenger boarding bridges (jetways)
 * - Ground power units and preconditioned air
 * - Aircraft towing and pushback services
 * - De-icing and anti-icing services
 * - Cargo and baggage loading/unloading
 * - Aircraft cleaning and servicing
 * - Lavatory and water servicing
 * - GSE (Ground Support Equipment) management
 * - Ramp safety and operations
 */

class GroundHandlingSystemIntegration
{
    private $serviceProviders;
    private $equipmentTracking;
    private $logger;
    private $dbConnection;
    private $realTimeUpdates;

    public function __construct()
    {
        $this->initializeServiceProviders();
        $this->initializeEquipmentTracking();
        $this->initializeLogger();
        $this->setupDatabaseConnection();
        $this->initializeRealTimeUpdates();
    }

    /**
     * Initialize ground handling service providers
     */
    private function initializeServiceProviders()
    {
        $this->serviceProviders = [
            'marshalling' => [
                'name' => 'Aircraft Marshalling Services',
                'base_url' => getenv('MARSHALLING_API_URL') ?: 'https://api.marshalling.local',
                'endpoints' => [
                    'assign' => '/v1/marshalling/assign',
                    'status' => '/v1/marshalling/status/{flight_id}',
                    'complete' => '/v1/marshalling/complete',
                    'crew' => '/v1/marshalling/crew'
                ]
            ],
            'jetways' => [
                'name' => 'Passenger Boarding Bridges',
                'base_url' => getenv('JETWAYS_API_URL') ?: 'https://api.jetways.local',
                'endpoints' => [
                    'assign' => '/v1/jetways/assign',
                    'position' => '/v1/jetways/position/{gate_id}',
                    'status' => '/v1/jetways/status/{gate_id}',
                    'maintenance' => '/v1/jetways/maintenance'
                ]
            ],
            'gpu' => [
                'name' => 'Ground Power Units',
                'base_url' => getenv('GPU_API_URL') ?: 'https://api.gpu.local',
                'endpoints' => [
                    'connect' => '/v1/gpu/connect',
                    'disconnect' => '/v1/gpu/disconnect',
                    'status' => '/v1/gpu/status/{stand_id}',
                    'maintenance' => '/v1/gpu/maintenance'
                ]
            ],
            'towing' => [
                'name' => 'Aircraft Towing Services',
                'base_url' => getenv('TOWING_API_URL') ?: 'https://api.towing.local',
                'endpoints' => [
                    'request' => '/v1/towing/request',
                    'status' => '/v1/towing/status/{flight_id}',
                    'complete' => '/v1/towing/complete',
                    'equipment' => '/v1/towing/equipment'
                ]
            ],
            'deicing' => [
                'name' => 'De-icing Services',
                'base_url' => getenv('DEICING_API_URL') ?: 'https://api.deicing.local',
                'endpoints' => [
                    'request' => '/v1/deicing/request',
                    'status' => '/v1/deicing/status/{flight_id}',
                    'complete' => '/v1/deicing/complete',
                    'fluids' => '/v1/deicing/fluids'
                ]
            ],
            'cargo' => [
                'name' => 'Cargo Handling Services',
                'base_url' => getenv('CARGO_API_URL') ?: 'https://api.cargo.local',
                'endpoints' => [
                    'load' => '/v1/cargo/load',
                    'unload' => '/v1/cargo/unload',
                    'status' => '/v1/cargo/status/{flight_id}',
                    'inventory' => '/v1/cargo/inventory'
                ]
            ],
            'cleaning' => [
                'name' => 'Aircraft Cleaning Services',
                'base_url' => getenv('CLEANING_API_URL') ?: 'https://api.cleaning.local',
                'endpoints' => [
                    'schedule' => '/v1/cleaning/schedule',
                    'status' => '/v1/cleaning/status/{flight_id}',
                    'complete' => '/v1/cleaning/complete',
                    'supplies' => '/v1/cleaning/supplies'
                ]
            ],
            'lavatory' => [
                'name' => 'Lavatory Servicing',
                'base_url' => getenv('LAVATORY_API_URL') ?: 'https://api.lavatory.local',
                'endpoints' => [
                    'service' => '/v1/lavatory/service',
                    'status' => '/v1/lavatory/status/{flight_id}',
                    'supplies' => '/v1/lavatory/supplies',
                    'maintenance' => '/v1/lavatory/maintenance'
                ]
            ]
        ];
    }

    /**
     * Initialize equipment tracking system
     */
    private function initializeEquipmentTracking()
    {
        $this->equipmentTracking = [
            'marshalling_wands' => [],
            'jetway_bridges' => [],
            'gpu_units' => [],
            'tow_tractors' => [],
            'deicing_trucks' => [],
            'cargo_loaders' => [],
            'cleaning_equipment' => [],
            'lavatory_trucks' => []
        ];
    }

    /**
     * Initialize logger
     */
    private function initializeLogger()
    {
        $this->logger = new Logger('ground_handling');
        $this->logger->pushHandler(new StreamHandler(__DIR__ . '/../logs/ground_handling.log', Logger::INFO));
    }

    /**
     * Setup database connection
     */
    private function setupDatabaseConnection()
    {
        try {
            $this->dbConnection = new PDO(
                "pgsql:host=" . getenv('DB_HOST') . ";dbname=" . getenv('DB_DATABASE'),
                getenv('DB_USERNAME'),
                getenv('DB_PASSWORD'),
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (PDOException $e) {
            $this->logger->error("Database connection failed: " . $e->getMessage());
            throw new Exception("Failed to connect to database");
        }
    }

    /**
     * Initialize real-time updates
     */
    private function initializeRealTimeUpdates()
    {
        $this->realTimeUpdates = new WebSocketServer('ground_handling_updates');
    }

    /**
     * Request aircraft marshalling services
     */
    public function requestMarshalling($flightData)
    {
        $this->logger->info("Requesting marshalling services", $flightData);

        // Check aircraft stand availability
        $standId = $this->getAvailableStand($flightData['aircraft_type']);

        if (!$standId) {
            throw new Exception("No available stand for aircraft type: " . $flightData['aircraft_type']);
        }

        // Assign marshalling crew
        $crewAssignment = $this->assignMarshallingCrew($standId);

        // Request marshalling service
        $requestData = [
            'flight_id' => $flightData['flight_id'],
            'aircraft_type' => $flightData['aircraft_type'],
            'stand_id' => $standId,
            'crew_id' => $crewAssignment['crew_id'],
            'scheduled_time' => $flightData['arrival_time'],
            'service_type' => $flightData['service_type'] // arrival or departure
        ];

        $response = $this->makeApiRequest(
            $this->serviceProviders['marshalling']['base_url'] .
            $this->serviceProviders['marshalling']['endpoints']['assign'],
            'POST',
            $requestData
        );

        if ($response['status'] === 'success') {
            $this->logServiceRequest('marshalling', $requestData);
            $this->notifyRealTimeUpdate('marshalling_assigned', $requestData);
            return $response['data'];
        }

        throw new Exception("Marshalling request failed: " . $response['message']);
    }

    /**
     * Get available aircraft stand
     */
    private function getAvailableStand($aircraftType)
    {
        $stmt = $this->dbConnection->prepare("
            SELECT stand_id FROM aircraft_stands
            WHERE aircraft_type = ? AND status = 'available'
            ORDER BY stand_id LIMIT 1
        ");

        $stmt->execute([$aircraftType]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ? $result['stand_id'] : null;
    }

    /**
     * Assign marshalling crew
     */
    private function assignMarshallingCrew($standId)
    {
        $stmt = $this->dbConnection->prepare("
            SELECT crew_id, name FROM marshalling_crew
            WHERE status = 'available' AND certified_stands LIKE ?
            ORDER BY last_assignment ASC LIMIT 1
        ");

        $stmt->execute(['%' . $standId . '%']);
        $crew = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($crew) {
            // Mark crew as assigned
            $updateStmt = $this->dbConnection->prepare("
                UPDATE marshalling_crew
                SET status = 'assigned', last_assignment = NOW()
                WHERE crew_id = ?
            ");
            $updateStmt->execute([$crew['crew_id']]);
        }

        return $crew;
    }

    /**
     * Assign passenger boarding bridge
     */
    public function assignJetway($gateData)
    {
        $this->logger->info("Assigning jetway", $gateData);

        // Check jetway availability
        $jetwayId = $this->getAvailableJetway($gateData['gate_id']);

        if (!$jetwayId) {
            throw new Exception("No available jetway for gate: " . $gateData['gate_id']);
        }

        $requestData = [
            'gate_id' => $gateData['gate_id'],
            'jetway_id' => $jetwayId,
            'flight_id' => $gateData['flight_id'],
            'aircraft_type' => $gateData['aircraft_type'],
            'scheduled_time' => $gateData['scheduled_time']
        ];

        $response = $this->makeApiRequest(
            $this->serviceProviders['jetways']['base_url'] .
            $this->serviceProviders['jetways']['endpoints']['assign'],
            'POST',
            $requestData
        );

        if ($response['status'] === 'success') {
            $this->logServiceRequest('jetway', $requestData);
            $this->notifyRealTimeUpdate('jetway_assigned', $requestData);
            return $response['data'];
        }

        throw new Exception("Jetway assignment failed: " . $response['message']);
    }

    /**
     * Get available jetway for gate
     */
    private function getAvailableJetway($gateId)
    {
        $stmt = $this->dbConnection->prepare("
            SELECT jetway_id FROM jetways
            WHERE gate_id = ? AND status = 'available'
            ORDER BY jetway_id LIMIT 1
        ");

        $stmt->execute([$gateId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ? $result['jetway_id'] : null;
    }

    /**
     * Connect ground power unit
     */
    public function connectGPU($gpuData)
    {
        $this->logger->info("Connecting GPU", $gpuData);

        $gpuId = $this->getAvailableGPU($gpuData['stand_id']);

        if (!$gpuId) {
            throw new Exception("No available GPU for stand: " . $gpuData['stand_id']);
        }

        $requestData = [
            'gpu_id' => $gpuId,
            'stand_id' => $gpuData['stand_id'],
            'flight_id' => $gpuData['flight_id'],
            'power_requirements' => $gpuData['power_requirements'],
            'duration' => $gpuData['duration']
        ];

        $response = $this->makeApiRequest(
            $this->serviceProviders['gpu']['base_url'] .
            $this->serviceProviders['gpu']['endpoints']['connect'],
            'POST',
            $requestData
        );

        if ($response['status'] === 'success') {
            $this->logServiceRequest('gpu', $requestData);
            $this->notifyRealTimeUpdate('gpu_connected', $requestData);
            return $response['data'];
        }

        throw new Exception("GPU connection failed: " . $response['message']);
    }

    /**
     * Get available GPU for stand
     */
    private function getAvailableGPU($standId)
    {
        $stmt = $this->dbConnection->prepare("
            SELECT gpu_id FROM gpu_units
            WHERE stand_id = ? AND status = 'available'
            ORDER BY last_used ASC LIMIT 1
        ");

        $stmt->execute([$standId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ? $result['gpu_id'] : null;
    }

    /**
     * Request aircraft towing service
     */
    public function requestTowing($towingData)
    {
        $this->logger->info("Requesting towing service", $towingData);

        $tractorId = $this->getAvailableTractor($towingData['aircraft_type']);

        if (!$tractorId) {
            throw new Exception("No available tractor for aircraft type: " . $towingData['aircraft_type']);
        }

        $requestData = [
            'flight_id' => $towingData['flight_id'],
            'tractor_id' => $tractorId,
            'aircraft_type' => $towingData['aircraft_type'],
            'from_stand' => $towingData['from_stand'],
            'to_stand' => $towingData['to_stand'],
            'scheduled_time' => $towingData['scheduled_time']
        ];

        $response = $this->makeApiRequest(
            $this->serviceProviders['towing']['base_url'] .
            $this->serviceProviders['towing']['endpoints']['request'],
            'POST',
            $requestData
        );

        if ($response['status'] === 'success') {
            $this->logServiceRequest('towing', $requestData);
            $this->notifyRealTimeUpdate('towing_requested', $requestData);
            return $response['data'];
        }

        throw new Exception("Towing request failed: " . $response['message']);
    }

    /**
     * Get available tow tractor
     */
    private function getAvailableTractor($aircraftType)
    {
        $stmt = $this->dbConnection->prepare("
            SELECT tractor_id FROM tow_tractors
            WHERE aircraft_types LIKE ? AND status = 'available'
            ORDER BY last_used ASC LIMIT 1
        ");

        $stmt->execute(['%' . $aircraftType . '%']);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ? $result['tractor_id'] : null;
    }

    /**
     * Request de-icing services
     */
    public function requestDeicing($deicingData)
    {
        $this->logger->info("Requesting de-icing service", $deicingData);

        // Check weather conditions for de-icing requirement
        if (!$this->requiresDeicing($deicingData['flight_id'])) {
            return ['status' => 'not_required', 'message' => 'De-icing not required'];
        }

        $truckId = $this->getAvailableDeicingTruck($deicingData['stand_id']);

        if (!$truckId) {
            throw new Exception("No available de-icing truck for stand: " . $deicingData['stand_id']);
        }

        $requestData = [
            'flight_id' => $deicingData['flight_id'],
            'truck_id' => $truckId,
            'stand_id' => $deicingData['stand_id'],
            'fluid_type' => $deicingData['fluid_type'],
            'quantity' => $deicingData['quantity'],
            'scheduled_time' => $deicingData['scheduled_time']
        ];

        $response = $this->makeApiRequest(
            $this->serviceProviders['deicing']['base_url'] .
            $this->serviceProviders['deicing']['endpoints']['request'],
            'POST',
            $requestData
        );

        if ($response['status'] === 'success') {
            $this->logServiceRequest('deicing', $requestData);
            $this->notifyRealTimeUpdate('deicing_requested', $requestData);
            return $response['data'];
        }

        throw new Exception("De-icing request failed: " . $response['message']);
    }

    /**
     * Check if de-icing is required
     */
    private function requiresDeicing($flightId)
    {
        // Check weather conditions and aircraft status
        $stmt = $this->dbConnection->prepare("
            SELECT w.temperature, w.precipitation, w.wind_speed
            FROM weather_conditions w
            JOIN flights f ON f.route LIKE CONCAT('%', w.airport_code, '%')
            WHERE f.flight_id = ? AND w.timestamp >= NOW() - INTERVAL '2 hours'
            ORDER BY w.timestamp DESC LIMIT 1
        ");

        $stmt->execute([$flightId]);
        $weather = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$weather) {
            return false;
        }

        // De-icing required if temperature below 3°C and precipitation or high winds
        return ($weather['temperature'] < 3 &&
                ($weather['precipitation'] > 0 || $weather['wind_speed'] > 20));
    }

    /**
     * Get available de-icing truck
     */
    private function getAvailableDeicingTruck($standId)
    {
        $stmt = $this->dbConnection->prepare("
            SELECT truck_id FROM deicing_trucks
            WHERE stand_id = ? AND status = 'available' AND fluid_level > 1000
            ORDER BY last_used ASC LIMIT 1
        ");

        $stmt->execute([$standId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ? $result['truck_id'] : null;
    }

    /**
     * Load/unload cargo and baggage
     */
    public function handleCargo($cargoData)
    {
        $this->logger->info("Handling cargo", $cargoData);

        $loaderId = $this->getAvailableCargoLoader($cargoData['stand_id']);

        if (!$loaderId) {
            throw new Exception("No available cargo loader for stand: " . $cargoData['stand_id']);
        }

        $requestData = [
            'flight_id' => $cargoData['flight_id'],
            'loader_id' => $loaderId,
            'stand_id' => $cargoData['stand_id'],
            'operation' => $cargoData['operation'], // 'load' or 'unload'
            'cargo_type' => $cargoData['cargo_type'], // 'baggage', 'cargo', 'mail'
            'weight' => $cargoData['weight'],
            'scheduled_time' => $cargoData['scheduled_time']
        ];

        $endpoint = $cargoData['operation'] === 'load' ? 'load' : 'unload';

        $response = $this->makeApiRequest(
            $this->serviceProviders['cargo']['base_url'] .
            $this->serviceProviders['cargo']['endpoints'][$endpoint],
            'POST',
            $requestData
        );

        if ($response['status'] === 'success') {
            $this->logServiceRequest('cargo', $requestData);
            $this->notifyRealTimeUpdate('cargo_handling', $requestData);
            return $response['data'];
        }

        throw new Exception("Cargo handling failed: " . $response['message']);
    }

    /**
     * Get available cargo loader
     */
    private function getAvailableCargoLoader($standId)
    {
        $stmt = $this->dbConnection->prepare("
            SELECT loader_id FROM cargo_loaders
            WHERE stand_id = ? AND status = 'available'
            ORDER BY last_used ASC LIMIT 1
        ");

        $stmt->execute([$standId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ? $result['loader_id'] : null;
    }

    /**
     * Schedule aircraft cleaning
     */
    public function scheduleCleaning($cleaningData)
    {
        $this->logger->info("Scheduling aircraft cleaning", $cleaningData);

        $crewId = $this->getAvailableCleaningCrew($cleaningData['aircraft_type']);

        if (!$crewId) {
            throw new Exception("No available cleaning crew for aircraft type: " . $cleaningData['aircraft_type']);
        }

        $requestData = [
            'flight_id' => $cleaningData['flight_id'],
            'crew_id' => $crewId,
            'aircraft_type' => $cleaningData['aircraft_type'],
            'stand_id' => $cleaningData['stand_id'],
            'cleaning_type' => $cleaningData['cleaning_type'], // 'quick', 'full', 'deep'
            'scheduled_time' => $cleaningData['scheduled_time'],
            'estimated_duration' => $cleaningData['estimated_duration']
        ];

        $response = $this->makeApiRequest(
            $this->serviceProviders['cleaning']['base_url'] .
            $this->serviceProviders['cleaning']['endpoints']['schedule'],
            'POST',
            $requestData
        );

        if ($response['status'] === 'success') {
            $this->logServiceRequest('cleaning', $requestData);
            $this->notifyRealTimeUpdate('cleaning_scheduled', $requestData);
            return $response['data'];
        }

        throw new Exception("Cleaning scheduling failed: " . $response['message']);
    }

    /**
     * Get available cleaning crew
     */
    private function getAvailableCleaningCrew($aircraftType)
    {
        $stmt = $this->dbConnection->prepare("
            SELECT crew_id FROM cleaning_crews
            WHERE aircraft_types LIKE ? AND status = 'available'
            ORDER BY last_assignment ASC LIMIT 1
        ");

        $stmt->execute(['%' . $aircraftType . '%']);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ? $result['crew_id'] : null;
    }

    /**
     * Service aircraft lavatory
     */
    public function serviceLavatory($lavatoryData)
    {
        $this->logger->info("Servicing aircraft lavatory", $lavatoryData);

        $truckId = $this->getAvailableLavatoryTruck($lavatoryData['stand_id']);

        if (!$truckId) {
            throw new Exception("No available lavatory truck for stand: " . $lavatoryData['stand_id']);
        }

        $requestData = [
            'flight_id' => $lavatoryData['flight_id'],
            'truck_id' => $truckId,
            'stand_id' => $lavatoryData['stand_id'],
            'service_type' => $lavatoryData['service_type'], // 'empty', 'fill', 'clean'
            'scheduled_time' => $lavatoryData['scheduled_time']
        ];

        $response = $this->makeApiRequest(
            $this->serviceProviders['lavatory']['base_url'] .
            $this->serviceProviders['lavatory']['endpoints']['service'],
            'POST',
            $requestData
        );

        if ($response['status'] === 'success') {
            $this->logServiceRequest('lavatory', $requestData);
            $this->notifyRealTimeUpdate('lavatory_service', $requestData);
            return $response['data'];
        }

        throw new Exception("Lavatory service failed: " . $response['message']);
    }

    /**
     * Get available lavatory truck
     */
    private function getAvailableLavatoryTruck($standId)
    {
        $stmt = $this->dbConnection->prepare("
            SELECT truck_id FROM lavatory_trucks
            WHERE stand_id = ? AND status = 'available' AND water_level > 1000
            ORDER BY last_used ASC LIMIT 1
        ");

        $stmt->execute([$standId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ? $result['truck_id'] : null;
    }

    /**
     * Get comprehensive ground handling status for flight
     */
    public function getGroundHandlingStatus($flightId)
    {
        $stmt = $this->dbConnection->prepare("
            SELECT service_type, status, scheduled_time, actual_time,
                   assigned_equipment, assigned_crew, notes
            FROM ground_handling_services
            WHERE flight_id = ?
            ORDER BY scheduled_time
        ");

        $stmt->execute([$flightId]);
        $services = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $status = [
            'flight_id' => $flightId,
            'overall_status' => 'pending',
            'services' => []
        ];

        $completedServices = 0;
        $totalServices = count($services);

        foreach ($services as $service) {
            $status['services'][] = $service;

            if ($service['status'] === 'completed') {
                $completedServices++;
            }
        }

        // Determine overall status
        if ($completedServices === $totalServices && $totalServices > 0) {
            $status['overall_status'] = 'completed';
        } elseif ($completedServices > 0) {
            $status['overall_status'] = 'in_progress';
        }

        return $status;
    }

    /**
     * Complete ground handling service
     */
    public function completeService($serviceData)
    {
        $this->logger->info("Completing ground handling service", $serviceData);

        // Update service status in database
        $stmt = $this->dbConnection->prepare("
            UPDATE ground_handling_services
            SET status = 'completed', actual_time = NOW(),
                completion_notes = ?, updated_at = NOW()
            WHERE flight_id = ? AND service_type = ?
        ");

        $stmt->execute([
            $serviceData['notes'] ?? null,
            $serviceData['flight_id'],
            $serviceData['service_type']
        ]);

        // Notify relevant service provider
        $this->notifyServiceCompletion($serviceData);

        // Update equipment status
        $this->updateEquipmentStatus($serviceData['equipment_id'], 'available');

        // Update crew status
        $this->updateCrewStatus($serviceData['crew_id'], 'available');

        $this->notifyRealTimeUpdate('service_completed', $serviceData);

        return ['status' => 'completed', 'timestamp' => date('Y-m-d H:i:s')];
    }

    /**
     * Notify service provider of completion
     */
    private function notifyServiceCompletion($serviceData)
    {
        $provider = $this->getServiceProvider($serviceData['service_type']);

        if ($provider) {
            $this->makeApiRequest(
                $this->serviceProviders[$provider]['base_url'] .
                $this->serviceProviders[$provider]['endpoints']['complete'],
                'POST',
                $serviceData,
                [],
                false // Don't cache completion notifications
            );
        }
    }

    /**
     * Get service provider for service type
     */
    private function getServiceProvider($serviceType)
    {
        $providerMap = [
            'marshalling' => 'marshalling',
            'jetway' => 'jetways',
            'gpu' => 'gpu',
            'towing' => 'towing',
            'deicing' => 'deicing',
            'cargo' => 'cargo',
            'cleaning' => 'cleaning',
            'lavatory' => 'lavatory'
        ];

        return $providerMap[$serviceType] ?? null;
    }

    /**
     * Update equipment status
     */
    private function updateEquipmentStatus($equipmentId, $status)
    {
        $stmt = $this->dbConnection->prepare("
            UPDATE ground_support_equipment
            SET status = ?, last_used = NOW(), updated_at = NOW()
            WHERE equipment_id = ?
        ");

        $stmt->execute([$status, $equipmentId]);
    }

    /**
     * Update crew status
     */
    private function updateCrewStatus($crewId, $status)
    {
        $stmt = $this->dbConnection->prepare("
            UPDATE ground_crew
            SET status = ?, last_assignment = NOW(), updated_at = NOW()
            WHERE crew_id = ?
        ");

        $stmt->execute([$status, $crewId]);
    }

    /**
     * Log service request
     */
    private function logServiceRequest($serviceType, $data)
    {
        $stmt = $this->dbConnection->prepare("
            INSERT INTO ground_handling_log (
                service_type, flight_id, data, created_at
            ) VALUES (?, ?, ?, NOW())
        ");

        $stmt->execute([
            $serviceType,
            $data['flight_id'],
            json_encode($data)
        ]);
    }

    /**
     * Notify real-time updates
     */
    private function notifyRealTimeUpdate($event, $data)
    {
        $this->realTimeUpdates->broadcast($event, $data);
    }

    /**
     * Make API request to service provider
     */
    private function makeApiRequest($url, $method = 'GET', $data = null, $headers = [], $useCache = true)
    {
        $ch = curl_init();

        if ($method === 'GET' && !empty($data)) {
            $url .= '?' . http_build_query($data);
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => array_merge([
                'Content-Type: application/json',
                'Authorization: Bearer ' . getenv('GROUND_HANDLING_API_TOKEN'),
                'User-Agent: Flight-Control-System/1.0'
            ], $headers)
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($error) {
            return ['status' => 'error', 'message' => $error];
        }

        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'status' => 'success',
                'data' => json_decode($response, true),
                'http_code' => $httpCode
            ];
        } else {
            return [
                'status' => 'error',
                'message' => "HTTP $httpCode: $response",
                'http_code' => $httpCode
            ];
        }
    }

    /**
     * Get equipment utilization report
     */
    public function getEquipmentUtilizationReport($dateRange = null)
    {
        $dateCondition = "";
        $params = [];

        if ($dateRange) {
            $dateCondition = "WHERE created_at BETWEEN ? AND ?";
            $params = [$dateRange['start'], $dateRange['end']];
        }

        $stmt = $this->dbConnection->prepare("
            SELECT
                service_type,
                COUNT(*) as total_services,
                AVG(TIMESTAMPDIFF(MINUTE, scheduled_time, actual_time)) as avg_duration,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_services
            FROM ground_handling_services
            $dateCondition
            GROUP BY service_type
            ORDER BY total_services DESC
        ");

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Health check for ground handling system
     */
    public function healthCheck()
    {
        $health = [
            'status' => 'healthy',
            'checks' => []
        ];

        // Database connectivity
        try {
            $this->dbConnection->query('SELECT 1');
            $health['checks']['database'] = ['status' => 'healthy'];
        } catch (Exception $e) {
            $health['checks']['database'] = ['status' => 'unhealthy', 'error' => $e->getMessage()];
            $health['status'] = 'unhealthy';
        }

        // Service provider connectivity
        foreach ($this->serviceProviders as $key => $provider) {
            try {
                $response = $this->makeApiRequest($provider['base_url'] . '/health', 'GET', [], [], false);
                if ($response['status'] === 'success') {
                    $health['checks'][$key . '_api'] = ['status' => 'healthy'];
                } else {
                    $health['checks'][$key . '_api'] = ['status' => 'unhealthy'];
                    $health['status'] = 'unhealthy';
                }
            } catch (Exception $e) {
                $health['checks'][$key . '_api'] = ['status' => 'unhealthy', 'error' => $e->getMessage()];
                $health['status'] = 'unhealthy';
            }
        }

        return $health;
    }
}

// Usage examples
/*
$groundHandling = new GroundHandlingSystemIntegration();

// Request marshalling for arriving flight
$marshallingResult = $groundHandling->requestMarshalling([
    'flight_id' => 'AA123',
    'aircraft_type' => 'B737',
    'arrival_time' => '2025-01-15 14:30:00',
    'service_type' => 'arrival'
]);

// Assign jetway to gate
$jetwayResult = $groundHandling->assignJetway([
    'gate_id' => 'A12',
    'flight_id' => 'AA123',
    'aircraft_type' => 'B737',
    'scheduled_time' => '2025-01-15 14:35:00'
]);

// Connect GPU
$gpuResult = $groundHandling->connectGPU([
    'stand_id' => '12A',
    'flight_id' => 'AA123',
    'power_requirements' => '400Hz',
    'duration' => 120
]);

// Request towing for departure
$towingResult = $groundHandling->requestTowing([
    'flight_id' => 'AA123',
    'aircraft_type' => 'B737',
    'from_stand' => '12A',
    'to_stand' => '12B',
    'scheduled_time' => '2025-01-15 16:00:00'
]);

// Request de-icing if needed
$deicingResult = $groundHandling->requestDeicing([
    'flight_id' => 'AA123',
    'stand_id' => '12A',
    'fluid_type' => 'Type I',
    'quantity' => 2000,
    'scheduled_time' => '2025-01-15 15:45:00'
]);

// Handle cargo loading
$cargoResult = $groundHandling->handleCargo([
    'flight_id' => 'AA123',
    'stand_id' => '12A',
    'operation' => 'load',
    'cargo_type' => 'baggage',
    'weight' => 15000,
    'scheduled_time' => '2025-01-15 15:30:00'
]);

// Schedule cleaning
$cleaningResult = $groundHandling->scheduleCleaning([
    'flight_id' => 'AA123',
    'aircraft_type' => 'B737',
    'stand_id' => '12A',
    'cleaning_type' => 'quick',
    'scheduled_time' => '2025-01-15 17:00:00',
    'estimated_duration' => 45
]);

// Service lavatory
$lavatoryResult = $groundHandling->serviceLavatory([
    'flight_id' => 'AA123',
    'stand_id' => '12A',
    'service_type' => 'empty',
    'scheduled_time' => '2025-01-15 15:20:00'
]);

// Get ground handling status
$status = $groundHandling->getGroundHandlingStatus('AA123');

// Complete service
$completionResult = $groundHandling->completeService([
    'flight_id' => 'AA123',
    'service_type' => 'marshalling',
    'equipment_id' => 'MARSHALL_001',
    'crew_id' => 'CREW_001',
    'notes' => 'Marshalling completed successfully'
]);

// Get utilization report
$report = $groundHandling->getEquipmentUtilizationReport([
    'start' => '2025-01-01',
    'end' => '2025-01-31'
]);

// Health check
$health = $groundHandling->healthCheck();
*/
