<?php

/**
 * Sustainability IoT Integration
 *
 * Integrates with IoT sensors for environmental monitoring
 * Handles emissions sensors, noise monitoring devices, and energy consumption meters
 */

require_once __DIR__ . '/../backend/config/database.php';
require_once __DIR__ . '/../backend/src/Logger.php';

class SustainabilityIoTIntegration {
    private $pdo;
    private $iotApiKey;
    private $iotBaseUrl;
    private $logger;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->iotApiKey = getenv('SUSTAINABILITY_IOT_API_KEY') ?: '';
        $this->iotBaseUrl = getenv('SUSTAINABILITY_IOT_BASE_URL') ?: 'https://api.sustainability-iot.com/v1';
        $this->logger = new Logger('sustainability_iot_integration');
    }

    /**
     * Register IoT sensor for environmental monitoring
     */
    public function registerSensor($sensorData) {
        try {
            $this->logger->info("Registering IoT sensor", $sensorData);

            // Validate sensor data
            if (!isset($sensorData['sensor_id']) || !isset($sensorData['sensor_type'])) {
                throw new Exception("Sensor ID and type are required");
            }

            // Check if sensor is available
            if (!$this->isSensorAvailable($sensorData['sensor_id'])) {
                throw new Exception("Sensor is not available or already in use");
            }

            // Register sensor with IoT platform
            $registration = $this->registerWithIoTPlatform($sensorData);

            if ($registration) {
                // Store sensor registration in database
                $this->storeSensorRegistration($sensorData, $registration);

                // Set up monitoring parameters
                $this->setupMonitoringParameters($sensorData['sensor_id'], $sensorData);

                return [
                    'success' => true,
                    'sensor_id' => $sensorData['sensor_id'],
                    'registration_id' => $registration['registration_id'],
                    'status' => 'registered'
                ];
            } else {
                throw new Exception("Failed to register sensor with IoT platform");
            }

        } catch (Exception $e) {
            $this->logger->error("Sensor registration failed: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get real-time sensor readings
     */
    public function getRealtimeReadings($sensorId, $hours = 24) {
        try {
            $this->logger->info("Getting real-time sensor readings", ['sensor_id' => $sensorId]);

            // Get readings from IoT platform
            $readings = $this->fetchSensorReadings($sensorId, $hours);

            if ($readings) {
                // Store readings in database
                $this->storeSensorReadings($sensorId, $readings);

                // Check for environmental alerts
                $alerts = $this->checkEnvironmentalAlerts($sensorId, $readings);

                return [
                    'sensor_id' => $sensorId,
                    'readings' => $readings,
                    'alerts' => $alerts,
                    'last_reading' => end($readings),
                    'status' => 'success'
                ];
            } else {
                throw new Exception("Failed to fetch sensor readings");
            }

        } catch (Exception $e) {
            $this->logger->error("Failed to get real-time readings: " . $e->getMessage());
            return [
                'sensor_id' => $sensorId,
                'error' => $e->getMessage(),
                'status' => 'error'
            ];
        }
    }

    /**
     * Monitor environmental sensors
     */
    public function monitorEnvironmentalSensors($sensorType = null) {
        try {
            $this->logger->info("Monitoring environmental sensors", ['sensor_type' => $sensorType]);

            // Get active sensors
            $sensors = $this->getActiveSensors($sensorType);

            $monitoringResults = [];
            $alerts = [];

            foreach ($sensors as $sensor) {
                // Get recent readings
                $readings = $this->getRealtimeReadings($sensor['sensor_id'], 1); // Last hour

                if ($readings && isset($readings['readings'])) {
                    // Analyze environmental trends
                    $analysis = $this->analyzeEnvironmentalTrends($sensor, $readings['readings']);

                    // Check for alerts
                    $sensorAlerts = $this->generateEnvironmentalAlerts($sensor, $readings['readings']);

                    $monitoringResults[] = [
                        'sensor_id' => $sensor['sensor_id'],
                        'sensor_type' => $sensor['sensor_type'],
                        'location' => $sensor['location'],
                        'current_value' => end($readings['readings'])['value'] ?? null,
                        'unit' => $sensor['unit'],
                        'status' => $analysis['status'],
                        'trend' => $analysis['trend'],
                        'alerts' => $sensorAlerts
                    ];

                    if (!empty($sensorAlerts)) {
                        $alerts = array_merge($alerts, $sensorAlerts);
                    }
                }
            }

            return [
                'monitoring_results' => $monitoringResults,
                'alerts' => $alerts,
                'total_sensors' => count($sensors),
                'timestamp' => time()
            ];

        } catch (Exception $e) {
            $this->logger->error("Environmental monitoring failed: " . $e->getMessage());
            return [
                'error' => $e->getMessage(),
                'status' => 'error'
            ];
        }
    }

    /**
     * Record emissions data from sensors
     */
    public function recordEmissionsData($emissionsData) {
        try {
            $this->logger->info("Recording emissions data", $emissionsData);

            // Validate emissions data
            if (!isset($emissionsData['sensor_id']) || !isset($emissionsData['co2_emissions'])) {
                throw new Exception("Sensor ID and CO2 emissions are required");
            }

            // Store emissions data
            $this->storeEmissionsData($emissionsData);

            // Calculate carbon footprint
            $carbonFootprint = $this->calculateCarbonFootprint($emissionsData);

            // Check emissions thresholds
            $thresholdCheck = $this->checkEmissionsThresholds($emissionsData);

            return [
                'success' => true,
                'sensor_id' => $emissionsData['sensor_id'],
                'carbon_footprint' => $carbonFootprint,
                'threshold_check' => $thresholdCheck,
                'status' => 'recorded'
            ];

        } catch (Exception $e) {
            $this->logger->error("Emissions data recording failed: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Record noise monitoring data
     */
    public function recordNoiseData($noiseData) {
        try {
            $this->logger->info("Recording noise data", $noiseData);

            // Validate noise data
            if (!isset($noiseData['sensor_id']) || !isset($noiseData['noise_level'])) {
                throw new Exception("Sensor ID and noise level are required");
            }

            // Store noise data
            $this->storeNoiseData($noiseData);

            // Check noise regulations
            $compliance = $this->checkNoiseCompliance($noiseData);

            return [
                'success' => true,
                'sensor_id' => $noiseData['sensor_id'],
                'noise_level' => $noiseData['noise_level'],
                'compliance' => $compliance,
                'status' => 'recorded'
            ];

        } catch (Exception $e) {
            $this->logger->error("Noise data recording failed: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Record energy consumption data
     */
    public function recordEnergyData($energyData) {
        try {
            $this->logger->info("Recording energy data", $energyData);

            // Validate energy data
            if (!isset($energyData['sensor_id']) || !isset($energyData['energy_consumption'])) {
                throw new Exception("Sensor ID and energy consumption are required");
            }

            // Store energy data
            $this->storeEnergyData($energyData);

            // Calculate energy efficiency
            $efficiency = $this->calculateEnergyEfficiency($energyData);

            return [
                'success' => true,
                'sensor_id' => $energyData['sensor_id'],
                'energy_consumption' => $energyData['energy_consumption'],
                'efficiency' => $efficiency,
                'status' => 'recorded'
            ];

        } catch (Exception $e) {
            $this->logger->error("Energy data recording failed: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get environmental monitoring dashboard data
     */
    public function getEnvironmentalDashboard() {
        try {
            // Get emissions summary
            $emissionsSummary = $this->getEmissionsSummary();

            // Get noise monitoring summary
            $noiseSummary = $this->getNoiseSummary();

            // Get energy consumption summary
            $energySummary = $this->getEnergySummary();

            // Get active alerts
            $activeAlerts = $this->getActiveAlerts();

            // Get sensor status
            $sensorStatus = $this->getSensorStatus();

            return [
                'emissions_summary' => $emissionsSummary,
                'noise_summary' => $noiseSummary,
                'energy_summary' => $energySummary,
                'active_alerts' => $activeAlerts,
                'sensor_status' => $sensorStatus,
                'system_status' => 'operational',
                'last_updated' => time()
            ];

        } catch (Exception $e) {
            $this->logger->error("Dashboard data retrieval failed: " . $e->getMessage());
            return [
                'error' => $e->getMessage(),
                'status' => 'error'
            ];
        }
    }

    // Helper methods

    private function isSensorAvailable($sensorId) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count FROM iot_sensors
            WHERE sensor_id = ? AND status = 'available'
        ");
        $stmt->execute([$sensorId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] > 0;
    }

    private function registerWithIoTPlatform($sensorData) {
        $data = [
            'sensor_id' => $sensorData['sensor_id'],
            'sensor_type' => $sensorData['sensor_type'],
            'location' => $sensorData['location'] ?? 'airport_facility',
            'monitoring_parameters' => [
                'frequency' => $sensorData['monitoring_frequency'] ?? 300, // 5 minutes
                'alert_thresholds' => $sensorData['alert_thresholds'] ?? $this->getDefaultThresholds($sensorData['sensor_type']),
                'data_retention_days' => $sensorData['data_retention_days'] ?? 90
            ]
        ];

        $response = $this->callIoTAPI('POST', '/sensors/register', $data);
        return $response;
    }

    private function storeSensorRegistration($sensorData, $registration) {
        $stmt = $this->pdo->prepare("
            INSERT INTO iot_sensors (
                sensor_id, sensor_type, location, registration_id,
                status, monitoring_frequency, alert_thresholds,
                registered_at
            ) VALUES (?, ?, ?, ?, 'active', ?, ?, NOW())
        ");

        $stmt->execute([
            $sensorData['sensor_id'],
            $sensorData['sensor_type'],
            $sensorData['location'] ?? 'airport_facility',
            $registration['registration_id'],
            $sensorData['monitoring_frequency'] ?? 300,
            json_encode($sensorData['alert_thresholds'] ?? $this->getDefaultThresholds($sensorData['sensor_type']))
        ]);
    }

    private function setupMonitoringParameters($sensorId, $sensorData) {
        $stmt = $this->pdo->prepare("
            INSERT INTO iot_monitoring_config (
                sensor_id, min_value, max_value, alert_delay_seconds,
                notification_channels, calibration_date
            ) VALUES (?, ?, ?, ?, ?, NOW())
        ");

        $thresholds = $sensorData['alert_thresholds'] ?? $this->getDefaultThresholds($sensorData['sensor_type']);

        $stmt->execute([
            $sensorId,
            $thresholds['min_value'] ?? null,
            $thresholds['max_value'] ?? null,
            $thresholds['alert_delay'] ?? 600,
            json_encode($sensorData['notification_channels'] ?? ['email', 'dashboard'])
        ]);
    }

    private function fetchSensorReadings($sensorId, $hours) {
        $params = ['hours' => $hours];
        $response = $this->callIoTAPI('GET', "/sensors/{$sensorId}/readings", null, $params);
        return $response['readings'] ?? null;
    }

    private function storeSensorReadings($sensorId, $readings) {
        $stmt = $this->pdo->prepare("
            INSERT INTO iot_sensor_readings (
                sensor_id, parameter_name, parameter_value, unit,
                recorded_at, sensor_status
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");

        foreach ($readings as $reading) {
            $stmt->execute([
                $sensorId,
                $reading['parameter'] ?? 'value',
                $reading['value'],
                $reading['unit'] ?? null,
                date('Y-m-d H:i:s', $reading['timestamp']),
                $reading['status'] ?? 'normal'
            ]);
        }
    }

    private function checkEnvironmentalAlerts($sensorId, $readings) {
        $stmt = $this->pdo->prepare("
            SELECT alert_thresholds FROM iot_sensors
            WHERE sensor_id = ?
        ");
        $stmt->execute([$sensorId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            return [];
        }

        $thresholds = json_decode($result['alert_thresholds'], true);
        $alerts = [];

        foreach ($readings as $reading) {
            $value = $reading['value'];

            if (isset($thresholds['max_value']) && $value > $thresholds['max_value']) {
                $alerts[] = [
                    'sensor_id' => $sensorId,
                    'alert_type' => 'threshold_exceeded',
                    'severity' => 'high',
                    'message' => "Value {$value} exceeds maximum threshold {$thresholds['max_value']}",
                    'value' => $value,
                    'threshold' => $thresholds['max_value'],
                    'timestamp' => $reading['timestamp']
                ];
            }

            if (isset($thresholds['min_value']) && $value < $thresholds['min_value']) {
                $alerts[] = [
                    'sensor_id' => $sensorId,
                    'alert_type' => 'threshold_below',
                    'severity' => 'medium',
                    'message' => "Value {$value} below minimum threshold {$thresholds['min_value']}",
                    'value' => $value,
                    'threshold' => $thresholds['min_value'],
                    'timestamp' => $reading['timestamp']
                ];
            }
        }

        return $alerts;
    }

    private function callIoTAPI($method, $endpoint, $data = null, $params = []) {
        $url = $this->iotBaseUrl . $endpoint;

        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $context = [
            'http' => [
                'method' => $method,
                'header' => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $this->iotApiKey,
                    'User-Agent: FlightControl-Sustainability/1.0'
                ],
                'timeout' => 30
            ]
        ];

        if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $context['http']['content'] = json_encode($data);
        }

        $response = file_get_contents($url, false, stream_context_create($context));

        if ($response === false) {
            throw new Exception("IoT API call failed");
        }

        return json_decode($response, true);
    }

    private function getActiveSensors($sensorType = null) {
        $whereClause = "";
        $params = [];

        if ($sensorType) {
            $whereClause = " AND sensor_type = ?";
            $params[] = $sensorType;
        }

        $stmt = $this->pdo->prepare("
            SELECT * FROM iot_sensors
            WHERE status = 'active' $whereClause
            ORDER BY registered_at DESC
        ");

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function analyzeEnvironmentalTrends($sensor, $readings) {
        if (empty($readings)) {
            return ['status' => 'no_data', 'trend' => 'unknown'];
        }

        $values = array_column($readings, 'value');
        $current = end($values);
        $average = array_sum($values) / count($values);

        $thresholds = json_decode($sensor['alert_thresholds'], true);
        $minValue = $thresholds['min_value'] ?? null;
        $maxValue = $thresholds['max_value'] ?? null;

        $status = 'normal';
        if ($maxValue && $current > $maxValue) {
            $status = 'high';
        } elseif ($minValue && $current < $minValue) {
            $status = 'low';
        }

        $trend = 'stable';
        if (count($values) > 1) {
            $recent = array_slice($values, -5); // Last 5 readings
            $recentAvg = array_sum($recent) / count($recent);
            $older = array_slice($values, 0, -5);
            $olderAvg = !empty($older) ? array_sum($older) / count($older) : $recentAvg;

            if ($recentAvg > $olderAvg + 1) {
                $trend = 'increasing';
            } elseif ($recentAvg < $olderAvg - 1) {
                $trend = 'decreasing';
            }
        }

        return [
            'status' => $status,
            'trend' => $trend,
            'current_value' => $current,
            'average_value' => round($average, 2)
        ];
    }

    private function generateEnvironmentalAlerts($sensor, $readings) {
        $alerts = [];
        $analysis = $this->analyzeEnvironmentalTrends($sensor, $readings);

        if ($analysis['status'] !== 'normal') {
            $alerts[] = [
                'sensor_id' => $sensor['sensor_id'],
                'sensor_type' => $sensor['sensor_type'],
                'alert_type' => 'environmental_' . $analysis['status'],
                'severity' => $analysis['status'] === 'high' ? 'high' : 'medium',
                'message' => ucfirst($sensor['sensor_type']) . " level is {$analysis['status']} ({$analysis['current_value']} {$sensor['unit']})",
                'current_value' => $analysis['current_value'],
                'unit' => $sensor['unit'],
                'timestamp' => time()
            ];
        }

        return $alerts;
    }

    private function storeEmissionsData($emissionsData) {
        $stmt = $this->pdo->prepare("
            INSERT INTO emissions_data (
                sensor_id, co2_emissions, nox_emissions, pm25_emissions,
                pm10_emissions, voc_emissions, recorded_at, source
            ) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)
        ");

        $stmt->execute([
            $emissionsData['sensor_id'],
            $emissionsData['co2_emissions'],
            $emissionsData['nox_emissions'] ?? null,
            $emissionsData['pm25_emissions'] ?? null,
            $emissionsData['pm10_emissions'] ?? null,
            $emissionsData['voc_emissions'] ?? null,
            $emissionsData['source'] ?? 'sensor'
        ]);
    }

    private function calculateCarbonFootprint($emissionsData) {
        // Simplified carbon footprint calculation
        $co2 = $emissionsData['co2_emissions'];
        $nox = $emissionsData['nox_emissions'] ?? 0;
        $voc = $emissionsData['voc_emissions'] ?? 0;

        // Convert to CO2 equivalents
        $co2e = $co2 + ($nox * 298) + ($voc * 21); // Using IPCC conversion factors

        return round($co2e, 2);
    }

    private function checkEmissionsThresholds($emissionsData) {
        $co2 = $emissionsData['co2_emissions'];
        $threshold = 1000; // kg CO2 per hour threshold

        return [
            'co2_emissions' => $co2,
            'threshold' => $threshold,
            'exceeds_threshold' => $co2 > $threshold,
            'severity' => $co2 > $threshold * 1.5 ? 'high' : ($co2 > $threshold ? 'medium' : 'low')
        ];
    }

    private function storeNoiseData($noiseData) {
        $stmt = $this->pdo->prepare("
            INSERT INTO noise_monitoring (
                sensor_id, noise_level_db, frequency_hz, duration_seconds,
                recorded_at, location
            ) VALUES (?, ?, ?, ?, NOW(), ?)
        ");

        $stmt->execute([
            $noiseData['sensor_id'],
            $noiseData['noise_level'],
            $noiseData['frequency'] ?? null,
            $noiseData['duration'] ?? null,
            $noiseData['location'] ?? null
        ]);
    }

    private function checkNoiseCompliance($noiseData) {
        $noiseLevel = $noiseData['noise_level'];
        $timeOfDay = date('H');

        // Different thresholds for day/night
        $threshold = ($timeOfDay >= 6 && $timeOfDay <= 22) ? 65 : 55; // dB

        return [
            'noise_level' => $noiseLevel,
            'threshold' => $threshold,
            'compliant' => $noiseLevel <= $threshold,
            'time_of_day' => $timeOfDay >= 6 && $timeOfDay <= 22 ? 'day' : 'night'
        ];
    }

    private function storeEnergyData($energyData) {
        $stmt = $this->pdo->prepare("
            INSERT INTO energy_consumption (
                sensor_id, energy_consumption_kwh, facility_type,
                recorded_at, cost_per_kwh
            ) VALUES (?, ?, ?, NOW(), ?)
        ");

        $stmt->execute([
            $energyData['sensor_id'],
            $energyData['energy_consumption'],
            $energyData['facility_type'] ?? 'general',
            $energyData['cost_per_kwh'] ?? 0.12
        ]);
    }

    private function calculateEnergyEfficiency($energyData) {
        $consumption = $energyData['energy_consumption'];
        $facilityType = $energyData['facility_type'] ?? 'general';

        // Baseline consumption per facility type (kWh per hour)
        $baselines = [
            'terminal' => 500,
            'runway' => 200,
            'hangar' => 300,
            'office' => 50,
            'general' => 100
        ];

        $baseline = $baselines[$facilityType] ?? $baselines['general'];
        $efficiency = ($consumption <= $baseline) ? 'efficient' : 'inefficient';

        return [
            'consumption' => $consumption,
            'baseline' => $baseline,
            'efficiency' => $efficiency,
            'efficiency_ratio' => round($baseline / max($consumption, 1), 2)
        ];
    }

    private function getDefaultThresholds($sensorType) {
        $defaults = [
            'emissions' => ['max_value' => 1000, 'alert_delay' => 3600],
            'noise' => ['max_value' => 70, 'alert_delay' => 600],
            'energy' => ['max_value' => 1000, 'alert_delay' => 1800],
            'temperature' => ['min_value' => -10, 'max_value' => 40, 'alert_delay' => 600],
            'humidity' => ['min_value' => 10, 'max_value' => 90, 'alert_delay' => 600]
        ];

        return $defaults[$sensorType] ?? ['max_value' => 100, 'alert_delay' => 600];
    }

    private function getEmissionsSummary() {
        $stmt = $this->pdo->prepare("
            SELECT
                DATE(recorded_at) as date,
                SUM(co2_emissions) as total_co2,
                AVG(co2_emissions) as avg_co2,
                COUNT(*) as readings_count
            FROM emissions_data
            WHERE recorded_at > NOW() - INTERVAL '7 days'
            GROUP BY DATE(recorded_at)
            ORDER BY date DESC
            LIMIT 7
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getNoiseSummary() {
        $stmt = $this->pdo->prepare("
            SELECT
                DATE(recorded_at) as date,
                AVG(noise_level_db) as avg_noise,
                MAX(noise_level_db) as max_noise,
                COUNT(*) as readings_count
            FROM noise_monitoring
            WHERE recorded_at > NOW() - INTERVAL '7 days'
            GROUP BY DATE(recorded_at)
            ORDER BY date DESC
            LIMIT 7
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getEnergySummary() {
        $stmt = $this->pdo->prepare("
            SELECT
                DATE(recorded_at) as date,
                SUM(energy_consumption_kwh) as total_energy,
                AVG(energy_consumption_kwh) as avg_energy,
                COUNT(*) as readings_count
            FROM energy_consumption
            WHERE recorded_at > NOW() - INTERVAL '7 days'
            GROUP BY DATE(recorded_at)
            ORDER BY date DESC
            LIMIT 7
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getActiveAlerts() {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count FROM iot_environmental_alerts
            WHERE status = 'active'
            AND created_at > NOW() - INTERVAL '24 hours'
        ");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'];
    }

    private function getSensorStatus() {
        $stmt = $this->pdo->prepare("
            SELECT
                sensor_type,
                COUNT(*) as total,
                COUNT(CASE WHEN status = 'active' THEN 1 END) as active,
                COUNT(CASE WHEN status = 'maintenance' THEN 1 END) as maintenance,
                COUNT(CASE WHEN status = 'offline' THEN 1 END) as offline
            FROM iot_sensors
            GROUP BY sensor_type
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Database tables for IoT integration
$iotTablesSQL = "
-- IoT sensors table
CREATE TABLE IF NOT EXISTS iot_sensors (
    sensor_id VARCHAR(50) PRIMARY KEY,
    sensor_type VARCHAR(50) NOT NULL,
    location VARCHAR(100),
    registration_id VARCHAR(100),
    status VARCHAR(20) DEFAULT 'available',
    monitoring_frequency INTEGER DEFAULT 300,
    alert_thresholds JSONB,
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- IoT sensor readings table
CREATE TABLE IF NOT EXISTS iot_sensor_readings (
    reading_id SERIAL PRIMARY KEY,
    sensor_id VARCHAR(50) NOT NULL,
    parameter_name VARCHAR(50) NOT NULL,
    parameter_value DECIMAL(10,2) NOT NULL,
    unit VARCHAR(20),
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sensor_status VARCHAR(20) DEFAULT 'normal',
    FOREIGN KEY (sensor_id) REFERENCES iot_sensors(sensor_id)
);

-- IoT monitoring configuration
CREATE TABLE IF NOT EXISTS iot_monitoring_config (
    config_id SERIAL PRIMARY KEY,
    sensor_id VARCHAR(50) NOT NULL,
    min_value DECIMAL(10,2),
    max_value DECIMAL(10,2),
    alert_delay_seconds INTEGER DEFAULT 600,
    notification_channels JSONB,
    calibration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sensor_id) REFERENCES iot_sensors(sensor_id)
);

-- Emissions data table
CREATE TABLE IF NOT EXISTS emissions_data (
    emission_id SERIAL PRIMARY KEY,
    sensor_id VARCHAR(50) NOT NULL,
    co2_emissions DECIMAL(10,2) NOT NULL,
    nox_emissions DECIMAL(10,2),
    pm25_emissions DECIMAL(10,2),
    pm10_emissions DECIMAL(10,2),
    voc_emissions DECIMAL(10,2),
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    source VARCHAR(50) DEFAULT 'sensor',
    FOREIGN KEY (sensor_id) REFERENCES iot_sensors(sensor_id)
);

-- Noise monitoring table
CREATE TABLE IF NOT EXISTS noise_monitoring (
    noise_id SERIAL PRIMARY KEY,
    sensor_id VARCHAR(50) NOT NULL,
    noise_level_db DECIMAL(5,1) NOT NULL,
    frequency_hz INTEGER,
    duration_seconds INTEGER,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    location VARCHAR(100),
    FOREIGN KEY (sensor_id) REFERENCES iot_sensors(sensor_id)
);

-- Energy consumption table
CREATE TABLE IF NOT EXISTS energy_consumption (
    energy_id SERIAL PRIMARY KEY,
    sensor_id VARCHAR(50) NOT NULL,
    energy_consumption_kwh DECIMAL(10,2) NOT NULL,
    facility_type VARCHAR(50) DEFAULT 'general',
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    cost_per_kwh DECIMAL(5,3) DEFAULT 0.12,
    FOREIGN KEY (sensor_id) REFERENCES iot_sensors(sensor_id)
);

-- Environmental alerts table
CREATE TABLE IF NOT EXISTS iot_environmental_alerts (
    alert_id SERIAL PRIMARY KEY,
    sensor_id VARCHAR(50) NOT NULL,
    alert_type VARCHAR(50) NOT NULL,
    severity VARCHAR(20) NOT NULL,
    message TEXT,
    current_value DECIMAL(10,2),
    threshold_value DECIMAL(10,2),
    status VARCHAR(20) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP,
    FOREIGN KEY (sensor_id) REFERENCES iot_sensors(sensor_id)
);

-- Indexes for performance
CREATE INDEX IF NOT EXISTS idx_iot_sensor_readings_sensor_time ON iot_sensor_readings (sensor_id, recorded_at);
CREATE INDEX IF NOT EXISTS idx_emissions_data_sensor_time ON emissions_data (sensor_id, recorded_at);
CREATE INDEX IF NOT EXISTS idx_noise_monitoring_sensor_time ON noise_monitoring (sensor_id, recorded_at);
CREATE INDEX IF NOT EXISTS idx_energy_consumption_sensor_time ON energy_consumption (sensor_id, recorded_at);
";

// Usage example:
/*
$iotIntegration = new SustainabilityIoTIntegration($pdo);

// Register sensor
$result = $iotIntegration->registerSensor([
    'sensor_id' => 'EMISSIONS-001',
    'sensor_type' => 'emissions',
    'location' => 'runway_approach',
    'alert_thresholds' => ['max_value' => 1000]
]);

// Get real-time readings
$readings = $iotIntegration->getRealtimeReadings('EMISSIONS-001', 24);

// Monitor environmental sensors
$monitoring = $iotIntegration->monitorEnvironmentalSensors('emissions');

// Record emissions data
$emissions = $iotIntegration->recordEmissionsData([
    'sensor_id' => 'EMISSIONS-001',
    'co2_emissions' => 850.5,
    'nox_emissions' => 15.2
]);

// Record noise data
$noise = $iotIntegration->recordNoiseData([
    'sensor_id' => 'NOISE-001',
    'noise_level' => 68.5,
    'location' => 'terminal_area'
]);

// Record energy data
$energy = $iotIntegration->recordEnergyData([
    'sensor_id' => 'ENERGY-001',
    'energy_consumption' => 450.0,
    'facility_type' => 'terminal'
]);

// Get dashboard data
$dashboard = $iotIntegration->getEnvironmentalDashboard();
*/
?>
