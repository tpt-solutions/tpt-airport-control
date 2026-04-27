<?php
/**
 * Satellite Connectivity Integration
 *
 * Handles aircraft satellite communications (Starlink, Iridium)
 * Processes real-time data from aircraft in remote areas
 */

require_once __DIR__ . '/../backend/config/database.php';
require_once __DIR__ . '/../backend/src/Logger.php';

class SatelliteIntegration {
    private $pdo;
    private $starlinkApiUrl;
    private $iridiumApiUrl;
    private $apiKey;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->starlinkApiUrl = getenv('STARLINK_API_URL') ?: 'https://api.starlink.com';
        $this->iridiumApiUrl = getenv('IRIDIUM_API_URL') ?: 'https://api.iridium.com';
        $this->apiKey = getenv('SATELLITE_API_KEY') ?: '';
    }

    /**
     * Process incoming satellite message from aircraft
     */
    public function processSatelliteMessage($messageData) {
        try {
            Logger::info('Processing satellite message: ' . json_encode($messageData));

            $messageType = $messageData['type'] ?? 'unknown';
            $aircraftId = $messageData['aircraft_id'] ?? $messageData['icao24'] ?? null;
            $satelliteType = $messageData['satellite_type'] ?? 'starlink'; // starlink or iridium

            if (!$aircraftId) {
                Logger::warning('Satellite message missing aircraft identifier');
                return false;
            }

            // Store the raw message
            $this->storeSatelliteMessage($aircraftId, $messageData, $satelliteType);

            // Process based on message type
            switch ($messageType) {
                case 'position_report':
                    return $this->processPositionReport($aircraftId, $messageData);

                case 'emergency':
                    return $this->processEmergencyMessage($aircraftId, $messageData);

                case 'maintenance':
                    return $this->processMaintenanceMessage($aircraftId, $messageData);

                case 'weather_request':
                    return $this->processWeatherRequest($aircraftId, $messageData);

                case 'flight_plan_update':
                    return $this->processFlightPlanUpdate($aircraftId, $messageData);

                default:
                    Logger::info('Unknown satellite message type: ' . $messageType);
                    return true; // Accept but don't process
            }

        } catch (Exception $e) {
            Logger::error('Failed to process satellite message: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Store raw satellite message for audit trail
     */
    private function storeSatelliteMessage($aircraftId, $messageData, $satelliteType) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO satellite_messages (
                    aircraft_id, satellite_type, message_type, message_data,
                    received_at, signal_strength, frequency
                ) VALUES (?, ?, ?, ?, NOW(), ?, ?)
            ");

            $stmt->execute([
                $aircraftId,
                $satelliteType,
                $messageData['type'] ?? 'unknown',
                json_encode($messageData),
                $messageData['signal_strength'] ?? null,
                $messageData['frequency'] ?? null
            ]);

        } catch (Exception $e) {
            Logger::error('Failed to store satellite message: ' . $e->getMessage());
        }
    }

    /**
     * Process position report from satellite
     */
    private function processPositionReport($aircraftId, $data) {
        try {
            // Store in aircraft_positions table
            $stmt = $this->pdo->prepare("
                INSERT INTO aircraft_positions (
                    icao24, callsign, longitude, latitude, baro_altitude,
                    velocity, true_track, on_ground, recorded_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ON CONFLICT (icao24, recorded_at)
                DO UPDATE SET
                    longitude = EXCLUDED.longitude,
                    latitude = EXCLUDED.latitude,
                    baro_altitude = EXCLUDED.baro_altitude,
                    velocity = EXCLUDED.velocity,
                    true_track = EXCLUDED.true_track,
                    on_ground = EXCLUDED.on_ground
            ");

            $stmt->execute([
                $aircraftId,
                $data['callsign'] ?? null,
                $data['longitude'],
                $data['latitude'],
                $data['altitude'],
                $data['ground_speed'] ?? $data['velocity'] ?? null,
                $data['heading'] ?? $data['true_track'] ?? null,
                $data['on_ground'] ?? false
            ]);

            Logger::info('Satellite position report processed for aircraft: ' . $aircraftId);
            return true;

        } catch (Exception $e) {
            Logger::error('Failed to process position report: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Process emergency message from aircraft
     */
    private function processEmergencyMessage($aircraftId, $data) {
        try {
            // Create emergency record
            $stmt = $this->pdo->prepare("
                INSERT INTO emergencies (
                    flight_id, type, description, reported_at
                ) VALUES (
                    (SELECT id FROM flights WHERE flight_number = ? LIMIT 1),
                    ?, ?, NOW()
                )
            ");

            $stmt->execute([
                $data['flight_number'] ?? null,
                'satellite_emergency',
                $data['message'] ?? 'Emergency reported via satellite'
            ]);

            // Broadcast emergency alert
            $this->broadcastEmergencyAlert($aircraftId, $data);

            Logger::error('Satellite emergency processed for aircraft: ' . $aircraftId);
            return true;

        } catch (Exception $e) {
            Logger::error('Failed to process emergency message: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Process maintenance message from aircraft
     */
    private function processMaintenanceMessage($aircraftId, $data) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO maintenance_reports (
                    aircraft_registration, report_type, description,
                    severity, reported_at, satellite_transmission
                ) VALUES (?, ?, ?, ?, NOW(), true)
            ");

            $stmt->execute([
                $aircraftId,
                $data['maintenance_type'] ?? 'general',
                $data['description'] ?? '',
                $data['severity'] ?? 'medium'
            ]);

            Logger::info('Satellite maintenance report processed for aircraft: ' . $aircraftId);
            return true;

        } catch (Exception $e) {
            Logger::error('Failed to process maintenance message: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Process weather request from aircraft
     */
    private function processWeatherRequest($aircraftId, $data) {
        try {
            $latitude = $data['latitude'] ?? null;
            $longitude = $data['longitude'] ?? null;

            if (!$latitude || !$longitude) {
                Logger::warning('Weather request missing coordinates');
                return false;
            }

            // Get weather data for the area
            $weatherData = $this->getWeatherForLocation($latitude, $longitude);

            // Send weather data back via satellite
            $this->sendWeatherResponse($aircraftId, $weatherData);

            Logger::info('Weather request processed for aircraft: ' . $aircraftId);
            return true;

        } catch (Exception $e) {
            Logger::error('Failed to process weather request: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Process flight plan update from aircraft
     */
    private function processFlightPlanUpdate($aircraftId, $data) {
        try {
            // Update flight plan in database
            $stmt = $this->pdo->prepare("
                UPDATE flights SET
                    updated_at = NOW()
                WHERE flight_number = ?
            ");

            $stmt->execute([$data['flight_number'] ?? null]);

            // Log the flight plan change
            Logger::info('Flight plan update received via satellite for aircraft: ' . $aircraftId);
            return true;

        } catch (Exception $e) {
            Logger::error('Failed to process flight plan update: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get weather data for specific location
     */
    private function getWeatherForLocation($latitude, $longitude) {
        try {
            // Query weather data from our database
            $stmt = $this->pdo->prepare("
                SELECT * FROM weather_data
                WHERE ST_DWithin(
                    ST_MakePoint(longitude, latitude)::geography,
                    ST_MakePoint(?, ?)::geography,
                    50000
                )
                ORDER BY recorded_at DESC
                LIMIT 5
            ");

            $stmt->execute([$longitude, $latitude]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            Logger::error('Failed to get weather data: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Send response back to aircraft via satellite
     */
    private function sendWeatherResponse($aircraftId, $weatherData) {
        // In production, this would send data back through satellite network
        // For now, just log the response
        Logger::info('Weather response prepared for aircraft ' . $aircraftId . ': ' . json_encode($weatherData));
    }

    /**
     * Broadcast emergency alert to ground stations
     */
    private function broadcastEmergencyAlert($aircraftId, $data) {
        // Broadcast via WebSocket to all connected ATC clients
        require_once __DIR__ . '/../backend/src/WebSocketServer.php';

        $alertMessage = sprintf(
            'SATELLITE EMERGENCY: Aircraft %s reported: %s',
            $aircraftId,
            $data['message'] ?? 'Emergency situation'
        );

        broadcastAnnouncement($alertMessage, 'emergency');
    }

    /**
     * Get satellite connectivity status
     */
    public function getConnectivityStatus() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    satellite_type,
                    COUNT(*) as message_count,
                    MAX(received_at) as last_message,
                    AVG(signal_strength) as avg_signal
                FROM satellite_messages
                WHERE received_at > NOW() - INTERVAL '1 hour'
                GROUP BY satellite_type
            ");

            $stmt->execute();
            $status = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'status' => 'operational',
                'satellite_networks' => $status,
                'timestamp' => time()
            ];

        } catch (Exception $e) {
            Logger::error('Failed to get connectivity status: ' . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'Unable to retrieve connectivity status',
                'timestamp' => time()
            ];
        }
    }

    /**
     * Send command to aircraft via satellite
     */
    public function sendAircraftCommand($aircraftId, $command, $parameters = []) {
        try {
            // Store command for transmission
            $stmt = $this->pdo->prepare("
                INSERT INTO satellite_commands (
                    aircraft_id, command_type, parameters, status, created_at
                ) VALUES (?, ?, ?, 'pending', NOW())
            ");

            $stmt->execute([
                $aircraftId,
                $command,
                json_encode($parameters)
            ]);

            // In production, this would queue the command for satellite transmission
            Logger::info('Satellite command queued for aircraft ' . $aircraftId . ': ' . $command);
            return true;

        } catch (Exception $e) {
            Logger::error('Failed to send aircraft command: ' . $e->getMessage());
            return false;
        }
    }
}

// Database tables for satellite integration
$satelliteTablesSQL = "
CREATE TABLE IF NOT EXISTS satellite_messages (
    id SERIAL PRIMARY KEY,
    aircraft_id VARCHAR(10) NOT NULL,
    satellite_type VARCHAR(20) NOT NULL, -- starlink, iridium
    message_type VARCHAR(50) NOT NULL,
    message_data JSONB,
    received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    signal_strength INTEGER,
    frequency DECIMAL(10,2),
    processed BOOLEAN DEFAULT FALSE
);

CREATE TABLE IF NOT EXISTS satellite_commands (
    id SERIAL PRIMARY KEY,
    aircraft_id VARCHAR(10) NOT NULL,
    command_type VARCHAR(50) NOT NULL,
    parameters JSONB,
    status VARCHAR(20) DEFAULT 'pending', -- pending, sent, acknowledged
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sent_at TIMESTAMP,
    acknowledged_at TIMESTAMP
);

CREATE TABLE IF NOT EXISTS maintenance_reports (
    id SERIAL PRIMARY KEY,
    aircraft_registration VARCHAR(20),
    report_type VARCHAR(50),
    description TEXT,
    severity VARCHAR(20) DEFAULT 'low', -- low, medium, high, critical
    reported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    satellite_transmission BOOLEAN DEFAULT FALSE,
    resolved BOOLEAN DEFAULT FALSE,
    resolved_at TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_satellite_messages_aircraft ON satellite_messages (aircraft_id);
CREATE INDEX IF NOT EXISTS idx_satellite_messages_time ON satellite_messages (received_at);
CREATE INDEX IF NOT EXISTS idx_satellite_commands_status ON satellite_commands (status);
";

// Usage examples:
/*
$satellite = new SatelliteIntegration($pdo);

// Process incoming message
$message = [
    'type' => 'position_report',
    'aircraft_id' => 'ABC123',
    'satellite_type' => 'starlink',
    'latitude' => 40.7128,
    'longitude' => -74.0060,
    'altitude' => 35000,
    'ground_speed' => 500,
    'heading' => 90
];
$satellite->processSatelliteMessage($message);

// Send command to aircraft
$satellite->sendAircraftCommand('ABC123', 'divert', ['airport' => 'JFK', 'reason' => 'weather']);

// Check connectivity
$status = $satellite->getConnectivityStatus();
*/
?>
