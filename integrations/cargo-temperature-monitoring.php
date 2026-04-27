<?php

/**
 * Cargo Temperature Monitoring Integration
 *
 * Integrates with IoT temperature sensors for real-time cargo monitoring
 * Handles perishable goods, pharmaceuticals, and temperature-sensitive cargo
 */

require_once __DIR__ . '/../backend/config/database.php';
require_once __DIR__ . '/../backend/src/Logger.php';

class CargoTemperatureMonitoring {
    private $pdo;
    private $sensorApiKey;
    private $sensorBaseUrl;
    private $logger;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->sensorApiKey = getenv('TEMPERATURE_SENSOR_API_KEY') ?: '';
        $this->sensorBaseUrl = getenv('TEMPERATURE_SENSOR_BASE_URL') ?: 'https://api.tempsensors.iot/v1';
        $this->logger = new Logger('cargo_temperature_monitoring');
    }

    /**
     * Register temperature sensor for shipment
     */
    public function registerSensor($sensorData) {
        try {
            $this->logger->info("Registering temperature sensor", $sensorData);

            // Validate sensor data
            if (!isset($sensorData['sensor_id']) || !isset($sensorData['shipment_id'])) {
                throw new Exception("Sensor ID and shipment ID are required");
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
     * Get real-time temperature readings
     */
    public function getRealtimeReadings($sensorId, $hours = 24) {
        try {
            $this->logger->info("Getting real-time temperature readings", ['sensor_id' => $sensorId]);

            // Get readings from IoT platform
            $readings = $this->fetchSensorReadings($sensorId, $hours);

            if ($readings) {
                // Store readings in database
                $this->storeTemperatureReadings($sensorId, $readings);

                // Check for temperature violations
                $violations = $this->checkTemperatureViolations($sensorId, $readings);

                return [
                    'sensor_id' => $sensorId,
                    'readings' => $readings,
                    'violations' => $violations,
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
     * Monitor temperature for multiple shipments
     */
    public function monitorShipments($shipmentIds = null) {
        try {
            $this->logger->info("Monitoring temperature for shipments");

            // Get active sensors
            $sensors = $this->getActiveSensors($shipmentIds);

            $monitoringResults = [];
            $alerts = [];

            foreach ($sensors as $sensor) {
                // Get recent readings
                $readings = $this->getRealtimeReadings($sensor['sensor_id'], 1); // Last hour

                if ($readings && isset($readings['readings'])) {
                    // Analyze temperature trends
                    $analysis = $this->analyzeTemperatureTrends($sensor, $readings['readings']);

                    // Check for alerts
                    $sensorAlerts = $this->generateTemperatureAlerts($sensor, $readings['readings']);

                    $monitoringResults[] = [
                        'sensor_id' => $sensor['sensor_id'],
                        'shipment_id' => $sensor['shipment_id'],
                        'current_temp' => end($readings['readings'])['temperature'] ?? null,
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
            $this->logger->error("Shipment monitoring failed: " . $e->getMessage());
            return [
                'error' => $e->getMessage(),
                'status' => 'error'
            ];
        }
    }

    /**
     * Generate temperature monitoring report
     */
    public function generateMonitoringReport($shipmentId, $startDate, $endDate) {
        try {
            $this->logger->info("Generating temperature monitoring report", [
                'shipment_id' => $shipmentId,
                'start_date' => $startDate,
                'end_date' => $endDate
            ]);

            // Get temperature data for the period
            $temperatureData = $this->getTemperatureHistory($shipmentId, $startDate, $endDate);

            if (empty($temperatureData)) {
                throw new Exception("No temperature data found for the specified period");
            }

            // Analyze temperature compliance
            $compliance = $this->analyzeTemperatureCompliance($temperatureData);

            // Generate report
            $report = [
                'shipment_id' => $shipmentId,
                'report_period' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate
                ],
                'temperature_summary' => [
                    'average_temperature' => $this->calculateAverageTemperature($temperatureData),
                    'min_temperature' => min(array_column($temperatureData, 'temperature_celsius')),
                    'max_temperature' => max(array_column($temperatureData, 'temperature_celsius')),
                    'total_readings' => count($temperatureData)
                ],
                'compliance_analysis' => $compliance,
                'violations' => $this->getTemperatureViolations($shipmentId, $startDate, $endDate),
                'recommendations' => $this->generateTemperatureRecommendations($compliance),
                'generated_at' => date('Y-m-d H:i:s')
            ];

            return $report;

        } catch (Exception $e) {
            $this->logger->error("Report generation failed: " . $e->getMessage());
            return [
                'error' => $e->getMessage(),
                'status' => 'error'
            ];
        }
    }

    /**
     * Configure temperature thresholds for shipment
     */
    public function configureThresholds($shipmentId, $thresholds) {
        try {
            $this->logger->info("Configuring temperature thresholds", [
                'shipment_id' => $shipmentId,
                'thresholds' => $thresholds
            ]);

            // Validate thresholds
            if (!$this->validateTemperatureThresholds($thresholds)) {
                throw new Exception("Invalid temperature thresholds");
            }

            // Store thresholds in database
            $this->storeTemperatureThresholds($shipmentId, $thresholds);

            // Update sensor configuration
            $this->updateSensorConfiguration($shipmentId, $thresholds);

            return [
                'success' => true,
                'shipment_id' => $shipmentId,
                'thresholds' => $thresholds,
                'status' => 'configured'
            ];

        } catch (Exception $e) {
            $this->logger->error("Threshold configuration failed: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Handle temperature alert notifications
     */
    public function handleTemperatureAlert($alertData) {
        try {
            $this->logger->info("Handling temperature alert", $alertData);

            // Store alert in database
            $this->storeTemperatureAlert($alertData);

            // Determine alert severity and actions
            $alertActions = $this->determineAlertActions($alertData);

            // Send notifications
            $this->sendAlertNotifications($alertData, $alertActions);

            // Log alert response
            $this->logAlertResponse($alertData, $alertActions);

            return [
                'success' => true,
                'alert_id' => $alertData['alert_id'] ?? null,
                'actions_taken' => $alertActions,
                'status' => 'handled'
            ];

        } catch (Exception $e) {
            $this->logger->error("Alert handling failed: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get temperature monitoring dashboard data
     */
    public function getMonitoringDashboard() {
        try {
            // Get active sensors count
            $activeSensors = $this->getActiveSensorsCount();

            // Get current alerts
            $currentAlerts = $this->getCurrentAlerts();

            // Get temperature violations in last 24 hours
            $recentViolations = $this->getRecentViolations(24);

            // Get temperature trends
            $temperatureTrends = $this->getTemperatureTrends();

            return [
                'active_sensors' => $activeSensors,
                'current_alerts' => $currentAlerts,
                'recent_violations' => $recentViolations,
                'temperature_trends' => $temperatureTrends,
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
            SELECT COUNT(*) as count FROM temperature_sensors
            WHERE sensor_id = ? AND status = 'available'
        ");
        $stmt->execute([$sensorId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] > 0;
    }

    private function registerWithIoTPlatform($sensorData) {
        $url = $this->sensorBaseUrl . '/sensors/register';

        $data = [
            'sensor_id' => $sensorData['sensor_id'],
            'sensor_type' => 'temperature',
            'location' => $sensorData['location'] ?? 'cargo_hold',
            'shipment_id' => $sensorData['shipment_id'],
            'monitoring_parameters' => [
                'frequency' => $sensorData['monitoring_frequency'] ?? 300, // 5 minutes
                'alert_thresholds' => $sensorData['alert_thresholds'] ?? [
                    'min_temp' => 2,
                    'max_temp' => 8,
                    'alert_delay' => 600 // 10 minutes
                ]
            ]
        ];

        $response = $this->callSensorAPI('POST', '/sensors/register', $data);
        return $response;
    }

    private function storeSensorRegistration($sensorData, $registration) {
        $stmt = $this->pdo->prepare("
            INSERT INTO temperature_sensors (
                sensor_id, shipment_id, registration_id, status,
                location, monitoring_frequency, alert_thresholds,
                registered_at
            ) VALUES (?, ?, ?, 'active', ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $sensorData['sensor_id'],
            $sensorData['shipment_id'],
            $registration['registration_id'],
            $sensorData['location'] ?? 'cargo_hold',
            $sensorData['monitoring_frequency'] ?? 300,
            json_encode($sensorData['alert_thresholds'] ?? ['min_temp' => 2, 'max_temp' => 8])
        ]);
    }

    private function setupMonitoringParameters($sensorId, $sensorData) {
        $stmt = $this->pdo->prepare("
            INSERT INTO temperature_monitoring_config (
                sensor_id, min_temperature, max_temperature,
                alert_delay_seconds, notification_channels
            ) VALUES (?, ?, ?, ?, ?)
        ");

        $thresholds = $sensorData['alert_thresholds'] ?? ['min_temp' => 2, 'max_temp' => 8];

        $stmt->execute([
            $sensorId,
            $thresholds['min_temp'] ?? 2,
            $thresholds['max_temp'] ?? 8,
            $thresholds['alert_delay'] ?? 600,
            json_encode($sensorData['notification_channels'] ?? ['email', 'sms'])
        ]);
    }

    private function fetchSensorReadings($sensorId, $hours) {
        $url = $this->sensorBaseUrl . "/sensors/{$sensorId}/readings";
        $params = ['hours' => $hours];

        $response = $this->callSensorAPI('GET', "/sensors/{$sensorId}/readings", null, $params);
        return $response['readings'] ?? null;
    }

    private function storeTemperatureReadings($sensorId, $readings) {
        $stmt = $this->pdo->prepare("
            INSERT INTO temperature_readings (
                sensor_id, temperature_celsius, humidity_percentage,
                recorded_at, sensor_status
            ) VALUES (?, ?, ?, ?, ?)
        ");

        foreach ($readings as $reading) {
            $stmt->execute([
                $sensorId,
                $reading['temperature'],
                $reading['humidity'] ?? null,
                date('Y-m-d H:i:s', $reading['timestamp']),
                $reading['status'] ?? 'normal'
            ]);
        }
    }

    private function checkTemperatureViolations($sensorId, $readings) {
        $stmt = $this->pdo->prepare("
            SELECT min_temperature, max_temperature, alert_delay_seconds
            FROM temperature_monitoring_config
            WHERE sensor_id = ?
        ");
        $stmt->execute([$sensorId]);
        $config = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$config) {
            return [];
        }

        $violations = [];
        $violationStart = null;

        foreach ($readings as $reading) {
            $temp = $reading['temperature'];
            $isViolation = $temp < $config['min_temperature'] || $temp > $config['max_temperature'];

            if ($isViolation) {
                if (!$violationStart) {
                    $violationStart = $reading['timestamp'];
                }
            } else {
                if ($violationStart) {
                    $duration = $reading['timestamp'] - $violationStart;
                    if ($duration >= $config['alert_delay_seconds']) {
                        $violations[] = [
                            'start_time' => $violationStart,
                            'end_time' => $reading['timestamp'],
                            'duration_seconds' => $duration,
                            'min_temp' => min(array_column(array_slice($readings, array_search($reading, $readings) - 1, $duration / 300), 'temperature')),
                            'max_temp' => max(array_column(array_slice($readings, array_search($reading, $readings) - 1, $duration / 300), 'temperature'))
                        ];
                    }
                    $violationStart = null;
                }
            }
        }

        return $violations;
    }

    private function callSensorAPI($method, $endpoint, $data = null, $params = []) {
        $url = $this->sensorBaseUrl . $endpoint;

        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $context = [
            'http' => [
                'method' => $method,
                'header' => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $this->sensorApiKey,
                    'User-Agent: FlightControl-Temperature/1.0'
                ],
                'timeout' => 30
            ]
        ];

        if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $context['http']['content'] = json_encode($data);
        }

        $response = file_get_contents($url, false, stream_context_create($context));

        if ($response === false) {
            throw new Exception("Sensor API call failed");
        }

        return json_decode($response, true);
    }

    private function getActiveSensors($shipmentIds = null) {
        $whereClause = "";
        $params = [];

        if ($shipmentIds) {
            $placeholders = str_repeat('?,', count($shipmentIds) - 1) . '?';
            $whereClause = " AND shipment_id IN ($placeholders)";
            $params = $shipmentIds;
        }

        $stmt = $this->pdo->prepare("
            SELECT * FROM temperature_sensors
            WHERE status = 'active' $whereClause
            ORDER BY registered_at DESC
        ");

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function analyzeTemperatureTrends($sensor, $readings) {
        if (empty($readings)) {
            return ['status' => 'no_data', 'trend' => 'unknown'];
        }

        $temperatures = array_column($readings, 'temperature');
        $current = end($temperatures);
        $average = array_sum($temperatures) / count($temperatures);

        $thresholds = json_decode($sensor['alert_thresholds'], true);
        $minTemp = $thresholds['min_temp'] ?? 2;
        $maxTemp = $thresholds['max_temp'] ?? 8;

        $status = 'normal';
        if ($current < $minTemp) {
            $status = 'too_low';
        } elseif ($current > $maxTemp) {
            $status = 'too_high';
        }

        $trend = 'stable';
        if (count($temperatures) > 1) {
            $recent = array_slice($temperatures, -5); // Last 5 readings
            $recentAvg = array_sum($recent) / count($recent);
            $older = array_slice($temperatures, 0, -5);
            $olderAvg = !empty($older) ? array_sum($older) / count($older) : $recentAvg;

            if ($recentAvg > $olderAvg + 0.5) {
                $trend = 'rising';
            } elseif ($recentAvg < $olderAvg - 0.5) {
                $trend = 'falling';
            }
        }

        return [
            'status' => $status,
            'trend' => $trend,
            'current_temperature' => $current,
            'average_temperature' => round($average, 2)
        ];
    }

    private function generateTemperatureAlerts($sensor, $readings) {
        $alerts = [];
        $analysis = $this->analyzeTemperatureTrends($sensor, $readings);

        if ($analysis['status'] !== 'normal') {
            $alerts[] = [
                'sensor_id' => $sensor['sensor_id'],
                'shipment_id' => $sensor['shipment_id'],
                'alert_type' => 'temperature_' . $analysis['status'],
                'severity' => $analysis['status'] === 'too_low' || $analysis['status'] === 'too_high' ? 'high' : 'medium',
                'message' => "Temperature is {$analysis['status']} ({$analysis['current_temperature']}°C)",
                'current_value' => $analysis['current_temperature'],
                'threshold' => $analysis['status'] === 'too_low' ? json_decode($sensor['alert_thresholds'], true)['min_temp'] : json_decode($sensor['alert_thresholds'], true)['max_temp'],
                'timestamp' => time()
            ];
        }

        if ($analysis['trend'] === 'rising' || $analysis['trend'] === 'falling') {
            $alerts[] = [
                'sensor_id' => $sensor['sensor_id'],
                'shipment_id' => $sensor['shipment_id'],
                'alert_type' => 'temperature_trend',
                'severity' => 'low',
                'message' => "Temperature is {$analysis['trend']}",
                'trend' => $analysis['trend'],
                'timestamp' => time()
            ];
        }

        return $alerts;
    }

    private function getTemperatureHistory($shipmentId, $startDate, $endDate) {
        $stmt = $this->pdo->prepare("
            SELECT tr.*, ts.sensor_id
            FROM temperature_readings tr
            JOIN temperature_sensors ts ON tr.sensor_id = ts.sensor_id
            WHERE ts.shipment_id = ?
            AND tr.recorded_at BETWEEN ? AND ?
            ORDER BY tr.recorded_at ASC
        ");

        $stmt->execute([$shipmentId, $startDate, $endDate]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function analyzeTemperatureCompliance($temperatureData) {
        if (empty($temperatureData)) {
            return ['compliance_rate' => 0, 'violations_count' => 0];
        }

        // Get thresholds for the shipment
        $sensorId = $temperatureData[0]['sensor_id'];
        $stmt = $this->pdo->prepare("
            SELECT alert_thresholds FROM temperature_sensors
            WHERE sensor_id = ?
        ");
        $stmt->execute([$sensorId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $thresholds = json_decode($result['alert_thresholds'], true);

        $minTemp = $thresholds['min_temp'] ?? 2;
        $maxTemp = $thresholds['max_temp'] ?? 8;

        $totalReadings = count($temperatureData);
        $compliantReadings = 0;
        $violations = 0;

        foreach ($temperatureData as $reading) {
            $temp = $reading['temperature_celsius'];
            if ($temp >= $minTemp && $temp <= $maxTemp) {
                $compliantReadings++;
            } else {
                $violations++;
            }
        }

        return [
            'compliance_rate' => $totalReadings > 0 ? round(($compliantReadings / $totalReadings) * 100, 2) : 0,
            'total_readings' => $totalReadings,
            'compliant_readings' => $compliantReadings,
            'violations_count' => $violations,
            'thresholds' => ['min' => $minTemp, 'max' => $maxTemp]
        ];
    }

    private function getTemperatureViolations($shipmentId, $startDate, $endDate) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM temperature_violations
            WHERE shipment_id = ?
            AND violation_start BETWEEN ? AND ?
            ORDER BY violation_start DESC
        ");

        $stmt->execute([$shipmentId, $startDate, $endDate]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function generateTemperatureRecommendations($compliance) {
        $recommendations = [];

        if ($compliance['compliance_rate'] < 95) {
            $recommendations[] = "Temperature compliance is below 95%. Review cooling system.";
        }

        if ($compliance['violations_count'] > 10) {
            $recommendations[] = "High number of violations detected. Consider shipment rerouting.";
        }

        if ($compliance['compliance_rate'] >= 98) {
            $recommendations[] = "Excellent temperature control maintained.";
        }

        return $recommendations;
    }

    private function validateTemperatureThresholds($thresholds) {
        return isset($thresholds['min_temp']) &&
               isset($thresholds['max_temp']) &&
               is_numeric($thresholds['min_temp']) &&
               is_numeric($thresholds['max_temp']) &&
               $thresholds['min_temp'] < $thresholds['max_temp'];
    }

    private function storeTemperatureThresholds($shipmentId, $thresholds) {
        $stmt = $this->pdo->prepare("
            UPDATE temperature_sensors
            SET alert_thresholds = ?
            WHERE shipment_id = ?
        ");

        $stmt->execute([json_encode($thresholds), $shipmentId]);
    }

    private function updateSensorConfiguration($shipmentId, $thresholds) {
        // Update IoT platform configuration
        $sensors = $this->getActiveSensors([$shipmentId]);

        foreach ($sensors as $sensor) {
            $this->callSensorAPI('PUT', "/sensors/{$sensor['sensor_id']}/config", [
                'alert_thresholds' => $thresholds
            ]);
        }
    }

    private function storeTemperatureAlert($alertData) {
        $stmt = $this->pdo->prepare("
            INSERT INTO temperature_alerts (
                sensor_id, shipment_id, alert_type, severity,
                message, current_value, threshold_value, timestamp
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $alertData['sensor_id'],
            $alertData['shipment_id'],
            $alertData['alert_type'],
            $alertData['severity'],
            $alertData['message'],
            $alertData['current_value'],
            $alertData['threshold'],
            date('Y-m-d H:i:s', $alertData['timestamp'])
        ]);
    }

    private function determineAlertActions($alertData) {
        $actions = [];

        switch ($alertData['severity']) {
            case 'high':
                $actions[] = 'immediate_notification';
                $actions[] = 'escalate_to_supervisor';
                $actions[] = 'log_incident';
                break;
            case 'medium':
                $actions[] = 'notification';
                $actions[] = 'monitor_closely';
                break;
            case 'low':
                $actions[] = 'log_only';
                break;
        }

        return $actions;
    }

    private function sendAlertNotifications($alertData, $actions) {
        // Implementation would send actual notifications
        // For now, just log the actions
        $this->logger->info("Sending alert notifications", [
            'alert' => $alertData,
            'actions' => $actions
        ]);
    }

    private function logAlertResponse($alertData, $actions) {
        $stmt = $this->pdo->prepare("
            INSERT INTO temperature_alert_responses (
                alert_id, actions_taken, response_timestamp
            ) VALUES (?, ?, NOW())
        ");

        $stmt->execute([
            $alertData['alert_id'] ?? null,
            json_encode($actions)
        ]);
    }

    private function getActiveSensorsCount() {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count FROM temperature_sensors
            WHERE status = 'active'
        ");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'];
    }

    private function getCurrentAlerts() {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count FROM temperature_alerts
            WHERE timestamp > NOW() - INTERVAL '1 hour'
            AND severity IN ('high', 'medium')
        ");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'];
    }

    private function getRecentViolations($hours) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count FROM temperature_violations
            WHERE violation_start > NOW() - INTERVAL '{$hours} hours'
        ");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'];
    }

    private function getTemperatureTrends() {
        $stmt = $this->pdo->prepare("
            SELECT
                DATE(recorded_at) as date,
                AVG(temperature_celsius) as avg_temp,
                MIN(temperature_celsius) as min_temp,
                MAX(temperature_celsius) as max_temp,
                COUNT(*) as reading_count
            FROM temperature_readings
            WHERE recorded_at > NOW() - INTERVAL '7 days'
            GROUP BY DATE(recorded_at)
            ORDER BY date DESC
            LIMIT 7
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function calculateAverageTemperature($temperatureData) {
        if (empty($temperatureData)) {
            return 0;
        }

        $temperatures = array_column($temperatureData, 'temperature_celsius');
        return round(array_sum($temperatures) / count($temperatures), 2);
    }
}

// Database tables for temperature monitoring
$temperatureTablesSQL = "
-- Temperature sensors table
CREATE TABLE IF NOT EXISTS temperature_sensors (
    sensor_id VARCHAR(50) PRIMARY KEY,
    shipment_id VARCHAR(20) NOT NULL,
    registration_id VARCHAR(100),
    status VARCHAR(20) DEFAULT 'available',
    location VARCHAR(100),
    monitoring_frequency INTEGER DEFAULT 300,
    alert_thresholds JSONB,
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (shipment_id) REFERENCES cargo_shipments(shipment_id)
);

-- Temperature readings table
CREATE TABLE IF NOT EXISTS temperature_readings (
    reading_id SERIAL PRIMARY KEY,
    sensor_id VARCHAR(50) NOT NULL,
    temperature_celsius DECIMAL(5,2) NOT NULL,
    humidity_percentage DECIMAL(5,2),
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sensor_status VARCHAR(20) DEFAULT 'normal',
    FOREIGN KEY (sensor_id) REFERENCES temperature_sensors(sensor_id)
);

-- Temperature monitoring configuration
CREATE TABLE IF NOT EXISTS temperature_monitoring_config (
    config_id SERIAL PRIMARY KEY,
    sensor_id VARCHAR(50) NOT NULL,
    min_temperature DECIMAL(5,2),
    max_temperature DECIMAL(5,2),
    alert_delay_seconds INTEGER DEFAULT 600,
    notification_channels JSONB,
    FOREIGN KEY (sensor_id) REFERENCES temperature_sensors(sensor_id)
);

-- Temperature violations table
CREATE TABLE IF NOT EXISTS temperature_violations (
    violation_id SERIAL PRIMARY KEY,
    sensor_id VARCHAR(50) NOT NULL,
    shipment_id VARCHAR(20) NOT NULL,
    violation_start TIMESTAMP NOT NULL,
    violation_end TIMESTAMP,
    min_temp DECIMAL(5,2),
    max_temp DECIMAL(5,2),
    duration_seconds INTEGER,
    severity VARCHAR(20),
    FOREIGN KEY (sensor_id) REFERENCES temperature_sensors(sensor_id),
    FOREIGN KEY (shipment_id) REFERENCES cargo_shipments(shipment_id)
);

-- Temperature alerts table
CREATE TABLE IF NOT EXISTS temperature_alerts (
    alert_id SERIAL PRIMARY KEY,
    sensor_id VARCHAR(50) NOT NULL,
    shipment_id VARCHAR(20) NOT NULL,
    alert_type VARCHAR(50) NOT NULL,
    severity VARCHAR(20) NOT NULL,
    message TEXT,
    current_value DECIMAL(5,2),
    threshold_value DECIMAL(5,2),
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sensor_id) REFERENCES temperature_sensors(sensor_id),
    FOREIGN KEY (shipment_id) REFERENCES cargo_shipments(shipment_id)
);

-- Temperature alert responses
CREATE TABLE IF NOT EXISTS temperature_alert_responses (
    response_id SERIAL PRIMARY KEY,
    alert_id INTEGER,
    actions_taken JSONB,
    response_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Indexes for performance
CREATE INDEX IF NOT EXISTS idx_temperature_readings_sensor_time ON temperature_readings (sensor_id, recorded_at);
CREATE INDEX IF NOT EXISTS idx_temperature_alerts_shipment ON temperature_alerts (shipment_id, timestamp);
CREATE INDEX IF NOT EXISTS idx_temperature_violations_shipment ON temperature_violations (shipment_id, violation_start);
";

// Usage example:
/*
$tempMonitor = new CargoTemperatureMonitoring($pdo);

// Register sensor
$result = $tempMonitor->registerSensor([
    'sensor_id' => 'TEMP-001',
    'shipment_id' => 'CGO-20231201-0001',
    'location' => 'cargo_hold_a1',
    'alert_thresholds' => ['min_temp' => 2, 'max_temp' => 8]
]);

// Get real-time readings
$readings = $tempMonitor->getRealtimeReadings('TEMP-001', 24);

// Monitor shipments
$monitoring = $tempMonitor->monitorShipments();

// Generate report
$report = $tempMonitor->generateMonitoringReport('CGO-20231201-0001', '2023-12-01', '2023-12-07');

// Configure thresholds
$config = $tempMonitor->configureThresholds('CGO-20231201-0001', [
    'min_temp' => 2,
    'max_temp' => 8,
    'alert_delay' => 600
]);

// Handle alert
$alertResult = $tempMonitor->handleTemperatureAlert([
    'sensor_id' => 'TEMP-001',
    'shipment_id' => 'CGO-20231201-0001',
    'alert_type' => 'temperature_too_high',
    'severity' => 'high',
    'current_value' => 9.5,
    'threshold' => 8
]);

// Get dashboard data
$dashboard = $tempMonitor->getMonitoringDashboard();
*/
?>
