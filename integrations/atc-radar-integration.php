<?php
/**
 * ATC Radar/Surveillance Integration Module
 *
 * This module handles integration with various radar and surveillance systems:
 * - Primary Surveillance Radar (PSR)
 * - Secondary Surveillance Radar (SSR) with Mode S/ADS-B
 * - Multilateration (MLAT) systems
 * - Surface Movement Radar (SMR)
 * - Airport Surface Detection Equipment (ASDE)
 */

class ATCRadarIntegration
{
    private $pdo;
    private $logger;
    private $radarSystems = [];
    private $activeConnections = [];

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $this->logger = new Logger();
        $this->initializeRadarSystems();
    }

    /**
     * Initialize radar system configurations
     */
    private function initializeRadarSystems()
    {
        $this->radarSystems = [
            'psr' => [
                'name' => 'Primary Surveillance Radar',
                'type' => 'PSR',
                'frequency' => '2.7-2.9 GHz',
                'protocol' => 'ASTERIX',
                'category' => 1,
                'active' => true
            ],
            'ssr' => [
                'name' => 'Secondary Surveillance Radar',
                'type' => 'SSR',
                'frequency' => '1030/1090 MHz',
                'protocol' => 'Mode S',
                'category' => 2,
                'active' => true
            ],
            'mlat' => [
                'name' => 'Multilateration System',
                'type' => 'MLAT',
                'frequency' => '1090 MHz',
                'protocol' => 'ASTERIX',
                'category' => 21,
                'active' => true
            ],
            'smr' => [
                'name' => 'Surface Movement Radar',
                'type' => 'SMR',
                'frequency' => '9-10 GHz',
                'protocol' => 'ASTERIX',
                'category' => 10,
                'active' => true
            ],
            'asde' => [
                'name' => 'Airport Surface Detection Equipment',
                'type' => 'ASDE',
                'frequency' => '15-16 GHz',
                'protocol' => 'ASTERIX',
                'category' => 62,
                'active' => true
            ]
        ];
    }

    /**
     * Connect to radar data source
     */
    public function connectToRadar($radarType, $connectionParams)
    {
        try {
            $this->logger->info("Connecting to {$radarType} radar system");

            switch ($radarType) {
                case 'psr':
                    return $this->connectPSR($connectionParams);
                case 'ssr':
                    return $this->connectSSR($connectionParams);
                case 'mlat':
                    return $this->connectMLAT($connectionParams);
                case 'smr':
                    return $this->connectSMR($connectionParams);
                case 'asde':
                    return $this->connectASDE($connectionParams);
                default:
                    throw new Exception("Unknown radar type: {$radarType}");
            }
        } catch (Exception $e) {
            $this->logger->error("Failed to connect to {$radarType} radar: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Connect to Primary Surveillance Radar
     */
    private function connectPSR($params)
    {
        // Simulate PSR connection
        $connectionId = 'psr_' . uniqid();

        $this->activeConnections[$connectionId] = [
            'type' => 'PSR',
            'status' => 'connected',
            'last_ping' => time(),
            'data_received' => 0
        ];

        $this->logger->info("PSR connection established: {$connectionId}");
        return $connectionId;
    }

    /**
     * Connect to Secondary Surveillance Radar
     */
    private function connectSSR($params)
    {
        // Simulate SSR connection
        $connectionId = 'ssr_' . uniqid();

        $this->activeConnections[$connectionId] = [
            'type' => 'SSR',
            'status' => 'connected',
            'last_ping' => time(),
            'data_received' => 0,
            'mode_s_enabled' => true,
            'adsb_enabled' => true
        ];

        $this->logger->info("SSR connection established: {$connectionId}");
        return $connectionId;
    }

    /**
     * Connect to Multilateration System
     */
    private function connectMLAT($params)
    {
        // Simulate MLAT connection
        $connectionId = 'mlat_' . uniqid();

        $this->activeConnections[$connectionId] = [
            'type' => 'MLAT',
            'status' => 'connected',
            'last_ping' => time(),
            'data_received' => 0,
            'receivers_count' => $params['receivers_count'] ?? 4
        ];

        $this->logger->info("MLAT connection established: {$connectionId}");
        return $connectionId;
    }

    /**
     * Connect to Surface Movement Radar
     */
    private function connectSMR($params)
    {
        // Simulate SMR connection
        $connectionId = 'smr_' . uniqid();

        $this->activeConnections[$connectionId] = [
            'type' => 'SMR',
            'status' => 'connected',
            'last_ping' => time(),
            'data_received' => 0,
            'coverage_area' => $params['coverage_area'] ?? 'runways_and_taxiways'
        ];

        $this->logger->info("SMR connection established: {$connectionId}");
        return $connectionId;
    }

    /**
     * Connect to Airport Surface Detection Equipment
     */
    private function connectASDE($params)
    {
        // Simulate ASDE connection
        $connectionId = 'asde_' . uniqid();

        $this->activeConnections[$connectionId] = [
            'type' => 'ASDE',
            'status' => 'connected',
            'last_ping' => time(),
            'data_received' => 0,
            'detection_range' => $params['detection_range'] ?? 3.0 // km
        ];

        $this->logger->info("ASDE connection established: {$connectionId}");
        return $connectionId;
    }

    /**
     * Process incoming radar data
     */
    public function processRadarData($radarType, $rawData)
    {
        try {
            switch ($radarType) {
                case 'psr':
                    return $this->processPSRData($rawData);
                case 'ssr':
                    return $this->processSSRData($rawData);
                case 'mlat':
                    return $this->processMLATData($rawData);
                case 'smr':
                    return $this->processSMRData($rawData);
                case 'asde':
                    return $this->processASDEData($rawData);
                default:
                    throw new Exception("Unknown radar type: {$radarType}");
            }
        } catch (Exception $e) {
            $this->logger->error("Error processing {$radarType} radar data: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Process Primary Surveillance Radar data (ASTERIX Category 1)
     */
    private function processPSRData($rawData)
    {
        // Parse ASTERIX Category 1 data
        $parsedData = $this->parseASTERIXData($rawData, 1);

        foreach ($parsedData as $target) {
            $this->storeRadarTarget($target, 'PSR');
        }

        return count($parsedData);
    }

    /**
     * Process Secondary Surveillance Radar data (Mode S/ADS-B)
     */
    private function processSSRData($rawData)
    {
        // Parse Mode S data
        $parsedData = $this->parseModeSData($rawData);

        foreach ($parsedData as $target) {
            $this->storeRadarTarget($target, 'SSR');
        }

        return count($parsedData);
    }

    /**
     * Process Multilateration data (ASTERIX Category 21)
     */
    private function processMLATData($rawData)
    {
        // Parse ASTERIX Category 21 data
        $parsedData = $this->parseASTERIXData($rawData, 21);

        foreach ($parsedData as $target) {
            $this->storeRadarTarget($target, 'MLAT');
        }

        return count($parsedData);
    }

    /**
     * Process Surface Movement Radar data (ASTERIX Category 10)
     */
    private function processSMRData($rawData)
    {
        // Parse ASTERIX Category 10 data
        $parsedData = $this->parseASTERIXData($rawData, 10);

        foreach ($parsedData as $target) {
            $this->storeRadarTarget($target, 'SMR');
        }

        return count($parsedData);
    }

    /**
     * Process Airport Surface Detection Equipment data (ASTERIX Category 62)
     */
    private function processASDEData($rawData)
    {
        // Parse ASTERIX Category 62 data
        $parsedData = $this->parseASTERIXData($rawData, 62);

        foreach ($parsedData as $target) {
            $this->storeRadarTarget($target, 'ASDE');
        }

        return count($parsedData);
    }

    /**
     * Parse ASTERIX format data
     */
    private function parseASTERIXData($rawData, $category)
    {
        // Simplified ASTERIX parsing (in real implementation, use proper ASTERIX library)
        $targets = [];

        // Mock parsing logic
        if (is_string($rawData)) {
            // Assume rawData contains multiple target reports
            $targetReports = explode("\n", trim($rawData));

            foreach ($targetReports as $report) {
                if (empty($report)) continue;

                $target = $this->parseASTERIXTarget($report, $category);
                if ($target) {
                    $targets[] = $target;
                }
            }
        }

        return $targets;
    }

    /**
     * Parse individual ASTERIX target
     */
    private function parseASTERIXTarget($report, $category)
    {
        // Mock ASTERIX target parsing
        // In real implementation, this would parse binary ASTERIX format

        $parts = explode(',', $report);
        if (count($parts) < 5) return null;

        return [
            'icao_address' => $parts[0] ?? null,
            'callsign' => $parts[1] ?? null,
            'latitude' => floatval($parts[2] ?? 0),
            'longitude' => floatval($parts[3] ?? 0),
            'altitude' => intval($parts[4] ?? 0),
            'ground_speed' => intval($parts[5] ?? 0),
            'heading' => intval($parts[6] ?? 0),
            'timestamp' => date('Y-m-d H:i:s'),
            'radar_type' => $this->getRadarTypeFromCategory($category),
            'data_source' => 'ASTERIX_CAT_' . $category
        ];
    }

    /**
     * Parse Mode S data
     */
    private function parseModeSData($rawData)
    {
        // Simplified Mode S parsing
        $targets = [];

        if (is_string($rawData)) {
            $messages = explode("\n", trim($rawData));

            foreach ($messages as $message) {
                if (empty($message)) continue;

                $target = $this->parseModeSMessage($message);
                if ($target) {
                    $targets[] = $target;
                }
            }
        }

        return $targets;
    }

    /**
     * Parse individual Mode S message
     */
    private function parseModeSMessage($message)
    {
        // Mock Mode S message parsing
        $parts = explode(',', $message);
        if (count($parts) < 5) return null;

        return [
            'icao_address' => $parts[0] ?? null,
            'callsign' => $parts[1] ?? null,
            'latitude' => floatval($parts[2] ?? 0),
            'longitude' => floatval($parts[3] ?? 0),
            'altitude' => intval($parts[4] ?? 0),
            'ground_speed' => intval($parts[5] ?? 0),
            'heading' => intval($parts[6] ?? 0),
            'timestamp' => date('Y-m-d H:i:s'),
            'radar_type' => 'SSR',
            'data_source' => 'MODE_S'
        ];
    }

    /**
     * Store radar target data
     */
    private function storeRadarTarget($target, $radarType)
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO radar_targets (
                    icao_address, callsign, latitude, longitude, altitude,
                    ground_speed, heading, timestamp, radar_type, data_source
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON CONFLICT (icao_address, timestamp)
                DO UPDATE SET
                    latitude = EXCLUDED.latitude,
                    longitude = EXCLUDED.longitude,
                    altitude = EXCLUDED.altitude,
                    ground_speed = EXCLUDED.ground_speed,
                    heading = EXCLUDED.heading
            ");

            $stmt->execute([
                $target['icao_address'],
                $target['callsign'],
                $target['latitude'],
                $target['longitude'],
                $target['altitude'],
                $target['ground_speed'],
                $target['heading'],
                $target['timestamp'],
                $radarType,
                $target['data_source']
            ]);

            // Update flight position if ICAO address matches
            if ($target['icao_address']) {
                $this->updateFlightPosition($target);
            }

        } catch (Exception $e) {
            $this->logger->error("Failed to store radar target: " . $e->getMessage());
        }
    }

    /**
     * Update flight position from radar data
     */
    private function updateFlightPosition($target)
    {
        if (!$target['icao_address']) return;

        try {
            // Find flight by ICAO address
            $stmt = $this->pdo->prepare("
                SELECT id FROM flights
                WHERE icao_code = ? AND status IN ('scheduled', 'departed', 'en_route')
                ORDER BY scheduled_departure DESC
                LIMIT 1
            ");
            $stmt->execute([$target['icao_address']]);

            $flight = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($flight) {
                // Update flight position
                $stmt = $this->pdo->prepare("
                    UPDATE flights
                    SET
                        current_latitude = ?,
                        current_longitude = ?,
                        current_altitude = ?,
                        ground_speed = ?,
                        heading = ?,
                        last_radar_update = ?,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");

                $stmt->execute([
                    $target['latitude'],
                    $target['longitude'],
                    $target['altitude'],
                    $target['ground_speed'],
                    $target['heading'],
                    $target['timestamp'],
                    $flight['id']
                ]);

                $this->logger->info("Updated flight position for {$target['icao_address']}");
            }
        } catch (Exception $e) {
            $this->logger->error("Failed to update flight position: " . $e->getMessage());
        }
    }

    /**
     * Get radar type from ASTERIX category
     */
    private function getRadarTypeFromCategory($category)
    {
        $categoryMap = [
            1 => 'PSR',
            2 => 'SSR',
            10 => 'SMR',
            21 => 'MLAT',
            62 => 'ASDE'
        ];

        return $categoryMap[$category] ?? 'UNKNOWN';
    }

    /**
     * Get radar system status
     */
    public function getRadarStatus()
    {
        $status = [];

        foreach ($this->radarSystems as $key => $system) {
            $status[$key] = [
                'name' => $system['name'],
                'type' => $system['type'],
                'active' => $system['active'],
                'connections' => count(array_filter($this->activeConnections, function($conn) use ($key) {
                    return stripos($conn['type'], strtoupper($key)) !== false;
                }))
            ];
        }

        return $status;
    }

    /**
     * Get radar coverage statistics
     */
    public function getRadarCoverageStats($timeRange = '1 hour')
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    radar_type,
                    COUNT(*) as target_count,
                    COUNT(DISTINCT icao_address) as unique_aircraft,
                    AVG(altitude) as avg_altitude,
                    MAX(altitude) as max_altitude,
                    MIN(altitude) as min_altitude
                FROM radar_targets
                WHERE timestamp >= NOW() - INTERVAL '{$timeRange}'
                GROUP BY radar_type
                ORDER BY target_count DESC
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logger->error("Failed to get radar coverage stats: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Detect potential conflicts using radar data
     */
    public function detectConflicts()
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    a.icao_address as aircraft1,
                    b.icao_address as aircraft2,
                    a.latitude as lat1,
                    a.longitude as lon1,
                    a.altitude as alt1,
                    b.latitude as lat2,
                    b.longitude as lon2,
                    b.altitude as alt2,
                    ST_Distance(
                        ST_Point(a.longitude, a.latitude)::geography,
                        ST_Point(b.longitude, b.latitude)::geography
                    ) as horizontal_distance,
                    ABS(a.altitude - b.altitude) as vertical_distance
                FROM radar_targets a
                JOIN radar_targets b ON a.icao_address != b.icao_address
                    AND a.timestamp = b.timestamp
                    AND a.radar_type = b.radar_type
                WHERE a.timestamp >= NOW() - INTERVAL '5 minutes'
                    AND ST_Distance(
                        ST_Point(a.longitude, a.latitude)::geography,
                        ST_Point(b.longitude, b.latitude)::geography
                    ) < 5000  -- 5km horizontal separation
                    AND ABS(a.altitude - b.altitude) < 1000  -- 1000ft vertical separation
                ORDER BY ST_Distance(
                    ST_Point(a.longitude, a.latitude)::geography,
                    ST_Point(b.longitude, b.latitude)::geography
                ) ASC
                LIMIT 20
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logger->error("Failed to detect conflicts: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get radar data quality metrics
     */
    public function getDataQualityMetrics()
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    radar_type,
                    data_source,
                    COUNT(*) as total_reports,
                    COUNT(CASE WHEN icao_address IS NOT NULL THEN 1 END) as valid_icao,
                    COUNT(CASE WHEN latitude IS NOT NULL AND longitude IS NOT NULL THEN 1 END) as valid_position,
                    COUNT(CASE WHEN altitude IS NOT NULL THEN 1 END) as valid_altitude,
                    AVG(EXTRACT(EPOCH FROM (NOW() - timestamp))) as avg_age_seconds
                FROM radar_targets
                WHERE timestamp >= NOW() - INTERVAL '1 hour'
                GROUP BY radar_type, data_source
                ORDER BY radar_type, total_reports DESC
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logger->error("Failed to get data quality metrics: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Disconnect from radar system
     */
    public function disconnectRadar($connectionId)
    {
        if (isset($this->activeConnections[$connectionId])) {
            $this->activeConnections[$connectionId]['status'] = 'disconnected';
            $this->logger->info("Disconnected from radar: {$connectionId}");
            return true;
        }
        return false;
    }

    /**
     * Get active radar connections
     */
    public function getActiveConnections()
    {
        return $this->activeConnections;
    }

    /**
     * Clean up old radar data
     */
    public function cleanupOldData($daysOld = 30)
    {
        try {
            $stmt = $this->pdo->prepare("
                DELETE FROM radar_targets
                WHERE timestamp < NOW() - INTERVAL '{$daysOld} days'
            ");
            $deleted = $stmt->execute();
            $this->logger->info("Cleaned up {$deleted} old radar target records");
            return $deleted;
        } catch (Exception $e) {
            $this->logger->error("Failed to cleanup old radar data: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Radar Data Fusion Engine
 * Combines data from multiple radar sources for improved accuracy
 */
class RadarDataFusionEngine
{
    private $pdo;
    private $logger;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $this->logger = new Logger();
    }

    /**
     * Fuse radar data from multiple sources
     */
    public function fuseRadarData($icaoAddress, $timeWindow = 30)
    {
        try {
            // Get all radar reports for this aircraft within time window
            $stmt = $this->pdo->prepare("
                SELECT * FROM radar_targets
                WHERE icao_address = ?
                AND timestamp >= NOW() - INTERVAL '{$timeWindow} seconds'
                ORDER BY timestamp DESC
            ");
            $stmt->execute([$icaoAddress]);
            $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($reports)) {
                return null;
            }

            // Perform data fusion
            $fusedData = $this->performDataFusion($reports);

            return $fusedData;
        } catch (Exception $e) {
            $this->logger->error("Failed to fuse radar data: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Perform data fusion algorithm
     */
    private function performDataFusion($reports)
    {
        $fused = [
            'icao_address' => $reports[0]['icao_address'],
            'callsign' => $reports[0]['callsign'],
            'latitude' => 0,
            'longitude' => 0,
            'altitude' => 0,
            'ground_speed' => 0,
            'heading' => 0,
            'timestamp' => $reports[0]['timestamp'],
            'data_sources' => [],
            'confidence' => 0
        ];

        $totalWeight = 0;

        foreach ($reports as $report) {
            $weight = $this->calculateReportWeight($report);
            $totalWeight += $weight;

            $fused['latitude'] += $report['latitude'] * $weight;
            $fused['longitude'] += $report['longitude'] * $weight;
            $fused['altitude'] += $report['altitude'] * $weight;
            $fused['ground_speed'] += $report['ground_speed'] * $weight;
            $fused['heading'] += $report['heading'] * $weight;

            $fused['data_sources'][] = $report['radar_type'] . ':' . $report['data_source'];
        }

        // Normalize by total weight
        if ($totalWeight > 0) {
            $fused['latitude'] /= $totalWeight;
            $fused['longitude'] /= $totalWeight;
            $fused['altitude'] /= $totalWeight;
            $fused['ground_speed'] /= $totalWeight;
            $fused['heading'] /= $totalWeight;
        }

        $fused['confidence'] = min(100, count($reports) * 20); // Simple confidence calculation

        return $fused;
    }

    /**
     * Calculate weight for radar report based on reliability
     */
    private function calculateReportWeight($report)
    {
        $baseWeight = 1.0;

        // PSR has good position accuracy
        if ($report['radar_type'] === 'PSR') {
            $baseWeight = 1.2;
        }

        // SSR/Mode S has good identity information
        if ($report['radar_type'] === 'SSR') {
            $baseWeight = 1.1;
        }

        // MLAT has good accuracy in coverage areas
        if ($report['radar_type'] === 'MLAT') {
            $baseWeight = 1.3;
        }

        // ADS-B has high accuracy and additional data
        if ($report['data_source'] === 'ADS-B') {
            $baseWeight = 1.5;
        }

        // Reduce weight for older data
        $ageSeconds = time() - strtotime($report['timestamp']);
        if ($ageSeconds > 60) {
            $baseWeight *= max(0.1, 1 - ($ageSeconds - 60) / 300); // Linear decay after 1 minute
        }

        return $baseWeight;
    }

    /**
     * Store fused radar data
     */
    public function storeFusedData($fusedData)
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO radar_fused_targets (
                    icao_address, callsign, latitude, longitude, altitude,
                    ground_speed, heading, timestamp, data_sources, confidence
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $fusedData['icao_address'],
                $fusedData['callsign'],
                $fusedData['latitude'],
                $fusedData['longitude'],
                $fusedData['altitude'],
                $fusedData['ground_speed'],
                $fusedData['heading'],
                $fusedData['timestamp'],
                json_encode($fusedData['data_sources']),
                $fusedData['confidence']
            ]);

            return true;
        } catch (Exception $e) {
            $this->logger->error("Failed to store fused radar data: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Ground-based Positioning System Integration
 */
class GroundBasedPositioningSystem
{
    private $pdo;
    private $logger;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $this->logger = new Logger();
    }

    /**
     * Process GPS-based positioning data
     */
    public function processGPSData($gpsData)
    {
        try {
            foreach ($gpsData as $position) {
                $this->storeGPSPosition($position);
                $this->updateAircraftPosition($position);
            }

            return count($gpsData);
        } catch (Exception $e) {
            $this->logger->error("Failed to process GPS data: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Store GPS position data
     */
    private function storeGPSPosition($position)
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO gps_positions (
                    device_id, latitude, longitude, altitude, accuracy,
                    speed, heading, timestamp, satellite_count
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $position['device_id'] ?? null,
                $position['latitude'],
                $position['longitude'],
                $position['altitude'] ?? null,
                $position['accuracy'] ?? null,
                $position['speed'] ?? null,
                $position['heading'] ?? null,
                $position['timestamp'] ?? date('Y-m-d H:i:s'),
                $position['satellite_count'] ?? null
            ]);
        } catch (Exception $e) {
            $this->logger->error("Failed to store GPS position: " . $e->getMessage());
        }
    }

    /**
     * Update aircraft position from GPS data
     */
    private function updateAircraftPosition($position)
    {
        if (!isset($position['device_id'])) return;

        try {
            // Find aircraft by device ID
            $stmt = $this->pdo->prepare("
                SELECT id FROM aircraft
                WHERE gps_device_id = ?
            ");
            $stmt->execute([$position['device_id']]);

            $aircraft = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($aircraft) {
                // Update aircraft position
                $stmt = $this->pdo->prepare("
                    UPDATE aircraft
                    SET
                        current_latitude = ?,
                        current_longitude = ?,
                        current_altitude = ?,
                        last_gps_update = ?,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");

                $stmt->execute([
                    $position['latitude'],
                    $position['longitude'],
                    $position['altitude'],
                    $position['timestamp'],
                    $aircraft['id']
                ]);

                $this->logger->info("Updated aircraft position from GPS for device {$position['device_id']}");
            }
        } catch (Exception $e) {
            $this->logger->error("Failed to update aircraft position from GPS: " . $e->getMessage());
        }
    }

    /**
     * Get GPS coverage statistics
     */
    public function getGPSCoverageStats($timeRange = '1 hour')
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    COUNT(*) as total_positions,
                    COUNT(DISTINCT device_id) as active_devices,
                    AVG(accuracy) as avg_accuracy,
                    MIN(accuracy) as best_accuracy,
                    MAX(accuracy) as worst_accuracy
                FROM gps_positions
                WHERE timestamp >= NOW() - INTERVAL '{$timeRange}'
            ");
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logger->error("Failed to get GPS coverage stats: " . $e->getMessage());
            return [];
        }
    }
}

// Usage example:
/*
// Initialize radar integration
$radarIntegration = new ATCRadarIntegration($pdo);

// Connect to radar systems
$psrConnection = $radarIntegration->connectToRadar('psr', ['host' => '192.168.1.100', 'port' => 30001]);
$ssrConnection = $radarIntegration->connectToRadar('ssr', ['host' => '192.168.1.101', 'port' => 30002]);

// Process incoming radar data
$radarData = "PSR_DATA_HERE"; // Raw ASTERIX data
$processedTargets = $radarIntegration->processRadarData('psr', $radarData);

// Initialize data fusion engine
$dataFusion = new RadarDataFusionEngine($pdo);
$fusedData = $dataFusion->fuseRadarData('ABC123');
if ($fusedData) {
    $dataFusion->storeFusedData($fusedData);
}

// Initialize ground-based positioning
$gpsSystem = new GroundBasedPositioningSystem($pdo);
$gpsData = [
    [
        'device_id' => 'GPS001',
        'latitude' => 40.7128,
        'longitude' => -74.0060,
        'altitude' => 1000,
        'accuracy' => 5.2,
        'timestamp' => date('Y-m-d H:i:s')
    ]
];
$gpsSystem->processGPSData($gpsData);
*/
?>
