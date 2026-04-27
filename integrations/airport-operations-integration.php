<?php

/**
 * Airport Operations Database Integration
 *
 * This integration connects to airport operational databases to synchronize:
 * - Gate assignments and terminal information
 * - Baggage handling systems
 * - Ground transportation data
 * - Airport resource management
 * - Passenger flow analytics
 */

class AirportOperationsIntegration
{
    private $dbConnection;
    private $apiEndpoints;
    private $syncInterval;
    private $lastSyncTime;
    private $logger;

    public function __construct()
    {
        $this->initializeConfiguration();
        $this->setupDatabaseConnection();
        $this->initializeLogger();
    }

    /**
     * Initialize integration configuration
     */
    private function initializeConfiguration()
    {
        $this->apiEndpoints = [
            'gates' => getenv('AIRPORT_GATES_API_URL') ?: 'https://api.airport.local/gates',
            'baggage' => getenv('AIRPORT_BAGGAGE_API_URL') ?: 'https://api.airport.local/baggage',
            'transport' => getenv('AIRPORT_TRANSPORT_API_URL') ?: 'https://api.airport.local/transport',
            'resources' => getenv('AIRPORT_RESOURCES_API_URL') ?: 'https://api.airport.local/resources',
            'passenger_flow' => getenv('AIRPORT_PASSENGER_API_URL') ?: 'https://api.airport.local/passenger-flow'
        ];

        $this->syncInterval = getenv('AIRPORT_SYNC_INTERVAL') ?: 300; // 5 minutes
        $this->lastSyncTime = 0;
    }

    /**
     * Setup database connection for airport operations
     */
    private function setupDatabaseConnection()
    {
        try {
            $this->dbConnection = new PDO(
                "pgsql:host=" . getenv('AIRPORT_DB_HOST') .
                ";port=" . getenv('AIRPORT_DB_PORT') .
                ";dbname=" . getenv('AIRPORT_DB_NAME'),
                getenv('AIRPORT_DB_USER'),
                getenv('AIRPORT_DB_PASSWORD'),
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            $this->logger->error("Airport DB connection failed: " . $e->getMessage());
            throw new Exception("Failed to connect to airport operations database");
        }
    }

    /**
     * Initialize logger
     */
    private function initializeLogger()
    {
        $this->logger = new Logger('airport_operations');
        $this->logger->pushHandler(new StreamHandler(__DIR__ . '/../logs/airport_operations.log', Logger::INFO));
    }

    /**
     * Synchronize gate assignments
     */
    public function syncGateAssignments()
    {
        $this->logger->info("Starting gate assignments synchronization");

        try {
            // Fetch gate data from airport system
            $gateData = $this->fetchGateData();

            foreach ($gateData as $gate) {
                $this->updateGateAssignment($gate);
            }

            $this->logger->info("Gate assignments synchronized successfully");
            return ['status' => 'success', 'gates_updated' => count($gateData)];

        } catch (Exception $e) {
            $this->logger->error("Gate synchronization failed: " . $e->getMessage());
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Fetch gate data from airport API
     */
    private function fetchGateData()
    {
        $response = $this->makeApiRequest($this->apiEndpoints['gates'], 'GET');

        if ($response['status'] !== 'success') {
            throw new Exception("Failed to fetch gate data: " . $response['message']);
        }

        return $response['data'];
    }

    /**
     * Update gate assignment in local database
     */
    private function updateGateAssignment($gateData)
    {
        $stmt = $this->dbConnection->prepare("
            INSERT INTO gate_assignments (
                gate_id, terminal_id, flight_id, aircraft_type,
                scheduled_time, status, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, NOW())
            ON CONFLICT (gate_id)
            DO UPDATE SET
                flight_id = EXCLUDED.flight_id,
                aircraft_type = EXCLUDED.aircraft_type,
                scheduled_time = EXCLUDED.scheduled_time,
                status = EXCLUDED.status,
                updated_at = NOW()
        ");

        $stmt->execute([
            $gateData['gate_id'],
            $gateData['terminal_id'],
            $gateData['flight_id'],
            $gateData['aircraft_type'],
            $gateData['scheduled_time'],
            $gateData['status']
        ]);
    }

    /**
     * Synchronize baggage handling data
     */
    public function syncBaggageData()
    {
        $this->logger->info("Starting baggage data synchronization");

        try {
            $baggageData = $this->fetchBaggageData();

            foreach ($baggageData as $baggage) {
                $this->updateBaggageStatus($baggage);
            }

            $this->logger->info("Baggage data synchronized successfully");
            return ['status' => 'success', 'baggage_updated' => count($baggageData)];

        } catch (Exception $e) {
            $this->logger->error("Baggage synchronization failed: " . $e->getMessage());
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Fetch baggage data from airport system
     */
    private function fetchBaggageData()
    {
        $response = $this->makeApiRequest($this->apiEndpoints['baggage'], 'GET');

        if ($response['status'] !== 'success') {
            throw new Exception("Failed to fetch baggage data: " . $response['message']);
        }

        return $response['data'];
    }

    /**
     * Update baggage status in local database
     */
    private function updateBaggageStatus($baggageData)
    {
        $stmt = $this->dbConnection->prepare("
            UPDATE baggage_tracking
            SET status = ?, location = ?, updated_at = NOW()
            WHERE baggage_id = ?
        ");

        $stmt->execute([
            $baggageData['status'],
            $baggageData['location'],
            $baggageData['baggage_id']
        ]);
    }

    /**
     * Synchronize ground transportation data
     */
    public function syncGroundTransportation()
    {
        $this->logger->info("Starting ground transportation synchronization");

        try {
            $transportData = $this->fetchTransportationData();

            foreach ($transportData as $transport) {
                $this->updateTransportationStatus($transport);
            }

            $this->logger->info("Ground transportation synchronized successfully");
            return ['status' => 'success', 'transport_updated' => count($transportData)];

        } catch (Exception $e) {
            $this->logger->error("Transportation synchronization failed: " . $e->getMessage());
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Fetch transportation data from airport system
     */
    private function fetchTransportationData()
    {
        $response = $this->makeApiRequest($this->apiEndpoints['transport'], 'GET');

        if ($response['status'] !== 'success') {
            throw new Exception("Failed to fetch transportation data: " . $response['message']);
        }

        return $response['data'];
    }

    /**
     * Update transportation status
     */
    private function updateTransportationStatus($transportData)
    {
        $stmt = $this->dbConnection->prepare("
            INSERT INTO ground_transportation (
                transport_id, type, location, status,
                capacity, available_seats, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, NOW())
            ON CONFLICT (transport_id)
            DO UPDATE SET
                location = EXCLUDED.location,
                status = EXCLUDED.status,
                available_seats = EXCLUDED.available_seats,
                updated_at = NOW()
        ");

        $stmt->execute([
            $transportData['transport_id'],
            $transportData['type'],
            $transportData['location'],
            $transportData['status'],
            $transportData['capacity'],
            $transportData['available_seats']
        ]);
    }

    /**
     * Synchronize airport resource data
     */
    public function syncAirportResources()
    {
        $this->logger->info("Starting airport resources synchronization");

        try {
            $resourceData = $this->fetchResourceData();

            foreach ($resourceData as $resource) {
                $this->updateResourceStatus($resource);
            }

            $this->logger->info("Airport resources synchronized successfully");
            return ['status' => 'success', 'resources_updated' => count($resourceData)];

        } catch (Exception $e) {
            $this->logger->error("Resource synchronization failed: " . $e->getMessage());
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Fetch resource data from airport system
     */
    private function fetchResourceData()
    {
        $response = $this->makeApiRequest($this->apiEndpoints['resources'], 'GET');

        if ($response['status'] !== 'success') {
            throw new Exception("Failed to fetch resource data: " . $response['message']);
        }

        return $response['data'];
    }

    /**
     * Update resource status
     */
    private function updateResourceStatus($resourceData)
    {
        $stmt = $this->dbConnection->prepare("
            INSERT INTO airport_resources (
                resource_id, type, location, status,
                capacity, utilization, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, NOW())
            ON CONFLICT (resource_id)
            DO UPDATE SET
                status = EXCLUDED.status,
                utilization = EXCLUDED.utilization,
                updated_at = NOW()
        ");

        $stmt->execute([
            $resourceData['resource_id'],
            $resourceData['type'],
            $resourceData['location'],
            $resourceData['status'],
            $resourceData['capacity'],
            $resourceData['utilization']
        ]);
    }

    /**
     * Synchronize passenger flow analytics
     */
    public function syncPassengerFlow()
    {
        $this->logger->info("Starting passenger flow synchronization");

        try {
            $flowData = $this->fetchPassengerFlowData();

            foreach ($flowData as $flow) {
                $this->updatePassengerFlow($flow);
            }

            $this->logger->info("Passenger flow synchronized successfully");
            return ['status' => 'success', 'flow_updated' => count($flowData)];

        } catch (Exception $e) {
            $this->logger->error("Passenger flow synchronization failed: " . $e->getMessage());
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Fetch passenger flow data
     */
    private function fetchPassengerFlowData()
    {
        $response = $this->makeApiRequest($this->apiEndpoints['passenger_flow'], 'GET');

        if ($response['status'] !== 'success') {
            throw new Exception("Failed to fetch passenger flow data: " . $response['message']);
        }

        return $response['data'];
    }

    /**
     * Update passenger flow analytics
     */
    private function updatePassengerFlow($flowData)
    {
        $stmt = $this->dbConnection->prepare("
            INSERT INTO passenger_flow_analytics (
                zone_id, timestamp, passenger_count,
                direction, congestion_level, updated_at
            ) VALUES (?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $flowData['zone_id'],
            $flowData['timestamp'],
            $flowData['passenger_count'],
            $flowData['direction'],
            $flowData['congestion_level']
        ]);
    }

    /**
     * Make API request to airport system
     */
    private function makeApiRequest($url, $method = 'GET', $data = null)
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . getenv('AIRPORT_API_TOKEN'),
                'User-Agent: Flight-Control-System/1.0'
            ]
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
     * Run complete synchronization
     */
    public function runFullSync()
    {
        $this->logger->info("Starting full airport operations synchronization");

        $results = [];

        // Check if sync is needed
        if (time() - $this->lastSyncTime < $this->syncInterval) {
            return ['status' => 'skipped', 'message' => 'Sync interval not reached'];
        }

        try {
            $results['gates'] = $this->syncGateAssignments();
            $results['baggage'] = $this->syncBaggageData();
            $results['transportation'] = $this->syncGroundTransportation();
            $results['resources'] = $this->syncAirportResources();
            $results['passenger_flow'] = $this->syncPassengerFlow();

            $this->lastSyncTime = time();

            $this->logger->info("Full synchronization completed successfully");
            return [
                'status' => 'success',
                'timestamp' => date('Y-m-d H:i:s'),
                'results' => $results
            ];

        } catch (Exception $e) {
            $this->logger->error("Full synchronization failed: " . $e->getMessage());
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
    }

    /**
     * Get synchronization status
     */
    public function getSyncStatus()
    {
        return [
            'last_sync' => $this->lastSyncTime ? date('Y-m-d H:i:s', $this->lastSyncTime) : null,
            'next_sync' => date('Y-m-d H:i:s', $this->lastSyncTime + $this->syncInterval),
            'sync_interval' => $this->syncInterval,
            'time_until_next' => max(0, $this->lastSyncTime + $this->syncInterval - time())
        ];
    }

    /**
     * Health check for airport integration
     */
    public function healthCheck()
    {
        $health = [
            'status' => 'healthy',
            'checks' => []
        ];

        // Database connection check
        try {
            $this->dbConnection->query('SELECT 1');
            $health['checks']['database'] = ['status' => 'healthy'];
        } catch (Exception $e) {
            $health['checks']['database'] = ['status' => 'unhealthy', 'error' => $e->getMessage()];
            $health['status'] = 'unhealthy';
        }

        // API connectivity check
        foreach ($this->apiEndpoints as $name => $url) {
            try {
                $response = $this->makeApiRequest($url, 'GET');
                if ($response['status'] === 'success') {
                    $health['checks'][$name . '_api'] = ['status' => 'healthy'];
                } else {
                    $health['checks'][$name . '_api'] = ['status' => 'unhealthy', 'error' => $response['message']];
                    $health['status'] = 'unhealthy';
                }
            } catch (Exception $e) {
                $health['checks'][$name . '_api'] = ['status' => 'unhealthy', 'error' => $e->getMessage()];
                $health['status'] = 'unhealthy';
            }
        }

        return $health;
    }
}

// Usage example
/*
$airportIntegration = new AirportOperationsIntegration();

// Run full synchronization
$result = $airportIntegration->runFullSync();
print_r($result);

// Check synchronization status
$status = $airportIntegration->getSyncStatus();
print_r($status);

// Health check
$health = $airportIntegration->healthCheck();
print_r($health);
*/
