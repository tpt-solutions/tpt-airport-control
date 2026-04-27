<?php
/**
 * Real-Time Data Fusion Engine
 *
 * Combines data from multiple aviation sources (ADS-B, radar, satellite, weather)
 * Provides unified situational awareness and conflict prediction
 */

class DataFusionEngine
{
    private $db;
    private $logger;
    private $cache;
    private $fusionRules;

    public function __construct($database, $logger)
    {
        $this->db = $database;
        $this->logger = $logger;
        $this->cache = [];
        $this->fusionRules = $this->loadFusionRules();
    }

    /**
     * Process real-time data fusion for airspace sector
     */
    public function processSectorFusion($sectorBounds, $timeWindow = 300)
    {
        try {
            $this->db->beginTransaction();

            // Collect data from all sources
            $adsbData = $this->getADSBData($sectorBounds, $timeWindow);
            $radarData = $this->getRadarData($sectorBounds, $timeWindow);
            $satelliteData = $this->getSatelliteData($sectorBounds, $timeWindow);
            $weatherData = $this->getWeatherData($sectorBounds, $timeWindow);
            $flightPlanData = $this->getFlightPlanData($sectorBounds, $timeWindow);

            // Fuse aircraft position data
            $fusedAircraft = $this->fuseAircraftPositions($adsbData, $radarData, $satelliteData);

            // Apply weather corrections
            $correctedAircraft = $this->applyWeatherCorrections($fusedAircraft, $weatherData);

            // Validate against flight plans
            $validatedAircraft = $this->validateAgainstFlightPlans($correctedAircraft, $flightPlanData);

            // Detect conflicts
            $conflicts = $this->detectConflicts($validatedAircraft);

            // Generate fusion report
            $fusionReport = $this->generateFusionReport($validatedAircraft, $conflicts, $weatherData);

            // Store fusion results
            $this->storeFusionResults($fusionReport);

            $this->db->commit();

            $this->logger->info("Data fusion completed", [
                'sector' => $sectorBounds,
                'aircraft_count' => count($validatedAircraft),
                'conflicts_detected' => count($conflicts)
            ]);

            return $fusionReport;

        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logger->error("Data fusion failed", ['error' => $e->getMessage()]);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get ADS-B data for sector
     */
    private function getADSBData($sectorBounds, $timeWindow)
    {
        $stmt = $this->db->prepare("
            SELECT *,
                   EXTRACT(EPOCH FROM (NOW() - recorded_at)) as age_seconds
            FROM aircraft_positions
            WHERE latitude BETWEEN ? AND ?
            AND longitude BETWEEN ? AND ?
            AND recorded_at >= NOW() - INTERVAL '? seconds'
            ORDER BY recorded_at DESC
        ");

        $stmt->execute([
            $sectorBounds['lat_min'], $sectorBounds['lat_max'],
            $sectorBounds['lon_min'], $sectorBounds['lon_max'],
            $timeWindow
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get radar data for sector
     */
    private function getRadarData($sectorBounds, $timeWindow)
    {
        $stmt = $this->db->prepare("
            SELECT *,
                   EXTRACT(EPOCH FROM (NOW() - recorded_at)) as age_seconds
            FROM weather_radar_data
            WHERE latitude BETWEEN ? AND ?
            AND longitude BETWEEN ? AND ?
            AND recorded_at >= NOW() - INTERVAL '? seconds'
            ORDER BY recorded_at DESC
        ");

        $stmt->execute([
            $sectorBounds['lat_min'], $sectorBounds['lat_max'],
            $sectorBounds['lon_min'], $sectorBounds['lon_max'],
            $timeWindow
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get satellite data for sector
     */
    private function getSatelliteData($sectorBounds, $timeWindow)
    {
        $stmt = $this->db->prepare("
            SELECT *,
                   EXTRACT(EPOCH FROM (NOW() - received_at)) as age_seconds
            FROM satellite_messages
            WHERE message_type IN ('position', 'tracking')
            AND received_at >= NOW() - INTERVAL '? seconds'
            ORDER BY received_at DESC
        ");

        $stmt->execute([$timeWindow]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get weather data for sector
     */
    private function getWeatherData($sectorBounds, $timeWindow)
    {
        // Get METAR data
        $stmt = $this->db->prepare("
            SELECT *,
                   EXTRACT(EPOCH FROM (NOW() - recorded_at)) as age_seconds
            FROM metar_reports
            WHERE recorded_at >= NOW() - INTERVAL '? seconds'
            ORDER BY recorded_at DESC
        ");

        $stmt->execute([$timeWindow]);
        $metarData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get radar weather data
        $stmt = $this->db->prepare("
            SELECT *,
                   EXTRACT(EPOCH FROM (NOW() - recorded_at)) as age_seconds
            FROM weather_radar_data
            WHERE latitude BETWEEN ? AND ?
            AND longitude BETWEEN ? AND ?
            AND recorded_at >= NOW() - INTERVAL '? seconds'
            ORDER BY recorded_at DESC
        ");

        $stmt->execute([
            $sectorBounds['lat_min'], $sectorBounds['lat_max'],
            $sectorBounds['lon_min'], $sectorBounds['lon_max'],
            $timeWindow
        ]);

        $radarWeather = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'metar' => $metarData,
            'radar' => $radarWeather
        ];
    }

    /**
     * Get flight plan data for sector
     */
    private function getFlightPlanData($sectorBounds, $timeWindow)
    {
        $stmt = $this->db->prepare("
            SELECT fp.*, f.flight_number, f.origin, f.destination
            FROM flight_plans fp
            LEFT JOIN flights f ON fp.flight_id = f.id
            WHERE fp.status IN ('filed', 'active')
            AND fp.departure_time <= NOW() + INTERVAL '? seconds'
            AND fp.arrival_time >= NOW() - INTERVAL '? seconds'
            ORDER BY fp.departure_time ASC
        ");

        $stmt->execute([$timeWindow, $timeWindow]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Fuse aircraft position data from multiple sources
     */
    private function fuseAircraftPositions($adsbData, $radarData, $satelliteData)
    {
        $fusedAircraft = [];

        // Group data by aircraft identifier
        $aircraftData = $this->groupByAircraft($adsbData, $radarData, $satelliteData);

        foreach ($aircraftData as $aircraftId => $dataSources) {
            $fusedPosition = $this->fusePositionData($dataSources);
            if ($fusedPosition) {
                $fusedAircraft[$aircraftId] = $fusedPosition;
            }
        }

        return $fusedAircraft;
    }

    /**
     * Group data by aircraft identifier
     */
    private function groupByAircraft($adsbData, $radarData, $satelliteData)
    {
        $grouped = [];

        // Group ADS-B data
        foreach ($adsbData as $data) {
            $id = $data['icao24'] ?? $data['callsign'];
            if (!isset($grouped[$id])) {
                $grouped[$id] = ['adsb' => [], 'radar' => [], 'satellite' => []];
            }
            $grouped[$id]['adsb'][] = $data;
        }

        // Group radar data (associate with nearest ADS-B track)
        foreach ($radarData as $data) {
            $nearestAircraft = $this->findNearestAircraft($data, $grouped);
            if ($nearestAircraft) {
                $grouped[$nearestAircraft]['radar'][] = $data;
            }
        }

        // Group satellite data
        foreach ($satelliteData as $data) {
            $id = $data['aircraft_id'];
            if (!isset($grouped[$id])) {
                $grouped[$id] = ['adsb' => [], 'radar' => [], 'satellite' => []];
            }
            $grouped[$id]['satellite'][] = $data;
        }

        return $grouped;
    }

    /**
     * Fuse position data from multiple sources
     */
    private function fusePositionData($dataSources)
    {
        $adsbData = $dataSources['adsb'] ?? [];
        $radarData = $dataSources['radar'] ?? [];
        $satelliteData = $dataSources['satellite'] ?? [];

        // Use ADS-B as primary source if available
        if (!empty($adsbData)) {
            $primaryData = $adsbData[0];

            // Enhance with radar data if available
            if (!empty($radarData)) {
                $primaryData = $this->enhanceWithRadar($primaryData, $radarData[0]);
            }

            // Enhance with satellite data if available
            if (!empty($satelliteData)) {
                $primaryData = $this->enhanceWithSatellite($primaryData, $satelliteData[0]);
            }

            return $primaryData;
        }

        // Fallback to radar data
        if (!empty($radarData)) {
            return $radarData[0];
        }

        // Fallback to satellite data
        if (!empty($satelliteData)) {
            return $satelliteData[0];
        }

        return null;
    }

    /**
     * Enhance ADS-B data with radar information
     */
    private function enhanceWithRadar($adsbData, $radarData)
    {
        // Combine position data with quality weighting
        $adsbWeight = 0.7; // ADS-B is generally more accurate
        $radarWeight = 0.3;

        $fused = $adsbData;
        $fused['position_source'] = 'fused_adsb_radar';
        $fused['fusion_confidence'] = min($adsbData['age_seconds'] ?? 0, $radarData['age_seconds'] ?? 0);

        // Fuse velocity data
        if (isset($radarData['velocity'])) {
            $fused['velocity'] = ($adsbData['velocity'] ?? 0) * $adsbWeight +
                               $radarData['velocity'] * $radarWeight;
        }

        return $fused;
    }

    /**
     * Enhance with satellite data
     */
    private function enhanceWithSatellite($primaryData, $satelliteData)
    {
        $fused = $primaryData;
        $fused['position_source'] = 'fused_satellite';
        $fused['satellite_backup'] = true;

        // Satellite data often provides additional telemetry
        if (isset($satelliteData['message_data'])) {
            $fused['satellite_telemetry'] = $satelliteData['message_data'];
        }

        return $fused;
    }

    /**
     * Apply weather corrections to aircraft data
     */
    private function applyWeatherCorrections($aircraftData, $weatherData)
    {
        $corrected = [];

        foreach ($aircraftData as $aircraftId => $aircraft) {
            $corrected[$aircraftId] = $aircraft;

            // Apply wind corrections
            $windCorrection = $this->calculateWindCorrection($aircraft, $weatherData);
            if ($windCorrection) {
                $corrected[$aircraftId]['wind_corrected_velocity'] = $windCorrection['velocity'];
                $corrected[$aircraftId]['wind_corrected_heading'] = $windCorrection['heading'];
            }

            // Check for weather hazards
            $weatherHazards = $this->checkWeatherHazards($aircraft, $weatherData);
            if (!empty($weatherHazards)) {
                $corrected[$aircraftId]['weather_hazards'] = $weatherHazards;
                $corrected[$aircraftId]['weather_alert'] = true;
            }
        }

        return $corrected;
    }

    /**
     * Calculate wind correction for aircraft
     */
    private function calculateWindCorrection($aircraft, $weatherData)
    {
        // Find nearest weather station
        $nearestWeather = $this->findNearestWeather($aircraft, $weatherData['metar']);

        if (!$nearestWeather) {
            return null;
        }

        $windSpeed = $nearestWeather['wind_speed'] ?? 0;
        $windDirection = $nearestWeather['wind_direction'] ?? 0;
        $aircraftHeading = $aircraft['true_track'] ?? 0;
        $aircraftSpeed = $aircraft['velocity'] ?? 0;

        // Calculate wind correction angle
        $windCorrectionAngle = $windDirection - $aircraftHeading;

        // Calculate ground speed with wind
        $headwind = $windSpeed * cos(deg2rad($windCorrectionAngle));
        $groundSpeed = $aircraftSpeed + $headwind;

        return [
            'velocity' => $groundSpeed,
            'heading' => $aircraftHeading,
            'wind_speed' => $windSpeed,
            'wind_direction' => $windDirection,
            'correction_angle' => $windCorrectionAngle
        ];
    }

    /**
     * Check for weather hazards
     */
    private function checkWeatherHazards($aircraft, $weatherData)
    {
        $hazards = [];

        // Check radar data for precipitation
        foreach ($weatherData['radar'] as $radarPoint) {
            $distance = $this->calculateDistance(
                $aircraft['latitude'], $aircraft['longitude'],
                $radarPoint['latitude'], $radarPoint['longitude']
            );

            if ($distance < 10) { // Within 10km
                if (($radarPoint['reflectivity'] ?? 0) > 30) {
                    $hazards[] = [
                        'type' => 'heavy_precipitation',
                        'severity' => 'moderate',
                        'distance_km' => $distance,
                        'reflectivity' => $radarPoint['reflectivity']
                    ];
                }

                if (($radarPoint['velocity'] ?? 0) > 50) {
                    $hazards[] = [
                        'type' => 'wind_shear',
                        'severity' => 'high',
                        'distance_km' => $distance,
                        'velocity' => $radarPoint['velocity']
                    ];
                }
            }
        }

        return $hazards;
    }

    /**
     * Validate aircraft against flight plans
     */
    private function validateAgainstFlightPlans($aircraftData, $flightPlanData)
    {
        $validated = [];

        foreach ($aircraftData as $aircraftId => $aircraft) {
            $validated[$aircraftId] = $aircraft;

            // Find matching flight plan
            $flightPlan = $this->findMatchingFlightPlan($aircraft, $flightPlanData);

            if ($flightPlan) {
                $validated[$aircraftId]['flight_plan'] = $flightPlan;
                $validated[$aircraftId]['plan_compliance'] = $this->checkPlanCompliance($aircraft, $flightPlan);
            } else {
                $validated[$aircraftId]['plan_compliance'] = 'no_plan_found';
            }
        }

        return $validated;
    }

    /**
     * Find matching flight plan for aircraft
     */
    private function findMatchingFlightPlan($aircraft, $flightPlanData)
    {
        $bestMatch = null;
        $bestScore = 0;

        foreach ($flightPlanData as $plan) {
            $score = 0;

            // Match by callsign/flight number
            if (isset($aircraft['callsign']) && isset($plan['flight_number'])) {
                if (stripos($plan['flight_number'], $aircraft['callsign']) !== false ||
                    stripos($aircraft['callsign'], $plan['flight_number']) !== false) {
                    $score += 50;
                }
            }

            // Match by aircraft ID
            if (isset($aircraft['icao24']) && isset($plan['aircraft_id'])) {
                if ($aircraft['icao24'] === $plan['aircraft_id']) {
                    $score += 30;
                }
            }

            // Match by position and time
            if (isset($plan['route'])) {
                $routeCompliance = $this->checkRouteCompliance($aircraft, $plan);
                $score += $routeCompliance * 20;
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMatch = $plan;
            }
        }

        return $bestMatch;
    }

    /**
     * Check route compliance
     */
    private function checkRouteCompliance($aircraft, $flightPlan)
    {
        // Simplified route compliance check
        // In production, this would involve detailed route analysis
        return 0.8; // 80% compliance as example
    }

    /**
     * Check plan compliance
     */
    private function checkPlanCompliance($aircraft, $flightPlan)
    {
        $compliance = ['overall' => 'compliant', 'issues' => []];

        // Check altitude compliance
        if (isset($aircraft['baro_altitude']) && isset($flightPlan['altitude_profile'])) {
            $expectedAltitude = $this->getExpectedAltitude($aircraft, $flightPlan);
            $altitudeDeviation = abs($aircraft['baro_altitude'] - $expectedAltitude);

            if ($altitudeDeviation > 1000) { // More than 1000 feet deviation
                $compliance['issues'][] = [
                    'type' => 'altitude_deviation',
                    'severity' => 'medium',
                    'deviation_ft' => $altitudeDeviation
                ];
            }
        }

        // Check speed compliance
        if (isset($aircraft['velocity']) && isset($flightPlan['speed_profile'])) {
            $expectedSpeed = $this->getExpectedSpeed($aircraft, $flightPlan);
            $speedDeviation = abs($aircraft['velocity'] - $expectedSpeed);

            if ($speedDeviation > 50) { // More than 50 knots deviation
                $compliance['issues'][] = [
                    'type' => 'speed_deviation',
                    'severity' => 'low',
                    'deviation_kt' => $speedDeviation
                ];
            }
        }

        if (!empty($compliance['issues'])) {
            $compliance['overall'] = 'non_compliant';
        }

        return $compliance;
    }

    /**
     * Detect conflicts between aircraft
     */
    private function detectConflicts($aircraftData)
    {
        $conflicts = [];

        $aircraftArray = array_values($aircraftData);

        for ($i = 0; $i < count($aircraftArray); $i++) {
            for ($j = $i + 1; $j < count($aircraftArray); $j++) {
                $aircraft1 = $aircraftArray[$i];
                $aircraft2 = $aircraftArray[$j];

                $conflict = $this->checkAircraftConflict($aircraft1, $aircraft2);

                if ($conflict) {
                    $conflicts[] = $conflict;
                }
            }
        }

        return $conflicts;
    }

    /**
     * Check for conflict between two aircraft
     */
    private function checkAircraftConflict($aircraft1, $aircraft2)
    {
        // Calculate horizontal separation
        $horizontalSep = $this->calculateDistance(
            $aircraft1['latitude'], $aircraft1['longitude'],
            $aircraft2['latitude'], $aircraft2['longitude']
        );

        // Calculate vertical separation
        $verticalSep = abs(($aircraft1['baro_altitude'] ?? 0) - ($aircraft2['baro_altitude'] ?? 0));

        // Calculate time to closest point of approach
        $timeToCPA = $this->calculateTimeToCPA($aircraft1, $aircraft2);

        // Check separation standards
        $horizontalMin = 5; // 5 nautical miles
        $verticalMin = 1000; // 1000 feet

        if ($horizontalSep < $horizontalMin && $verticalSep < $verticalMin) {
            return [
                'aircraft1' => $aircraft1['icao24'] ?? $aircraft1['callsign'],
                'aircraft2' => $aircraft2['icao24'] ?? $aircraft2['callsign'],
                'horizontal_separation_nm' => $horizontalSep,
                'vertical_separation_ft' => $verticalSep,
                'time_to_conflict_sec' => $timeToCPA,
                'severity' => $this->assessConflictSeverity($horizontalSep, $verticalSep, $timeToCPA),
                'detected_at' => date('Y-m-d H:i:s')
            ];
        }

        return null;
    }

    /**
     * Calculate time to closest point of approach
     */
    private function calculateTimeToCPA($aircraft1, $aircraft2)
    {
        // Simplified CPA calculation
        // In production, this would use more sophisticated algorithms
        $relativeSpeed = abs(($aircraft1['velocity'] ?? 0) - ($aircraft2['velocity'] ?? 0));
        $separation = $this->calculateDistance(
            $aircraft1['latitude'], $aircraft1['longitude'],
            $aircraft2['latitude'], $aircraft2['longitude']
        );

        if ($relativeSpeed > 0) {
            return ($separation * 3600) / $relativeSpeed; // Time in seconds
        }

        return 999999; // No convergence
    }

    /**
     * Assess conflict severity
     */
    private function assessConflictSeverity($horizontalSep, $verticalSep, $timeToCPA)
    {
        if ($horizontalSep < 3 || $verticalSep < 500 || $timeToCPA < 300) {
            return 'critical';
        } elseif ($horizontalSep < 5 || $verticalSep < 1000 || $timeToCPA < 600) {
            return 'warning';
        } else {
            return 'monitor';
        }
    }

    /**
     * Generate fusion report
     */
    private function generateFusionReport($aircraftData, $conflicts, $weatherData)
    {
        return [
            'timestamp' => date('Y-m-d H:i:s'),
            'aircraft_count' => count($aircraftData),
            'conflicts_count' => count($conflicts),
            'weather_conditions' => $this->summarizeWeather($weatherData),
            'aircraft_summary' => $this->summarizeAircraft($aircraftData),
            'conflicts' => $conflicts,
            'system_status' => 'operational'
        ];
    }

    /**
     * Summarize weather conditions
     */
    private function summarizeWeather($weatherData)
    {
        $summary = [
            'active_weather_alerts' => 0,
            'precipitation_areas' => 0,
            'wind_shear_zones' => 0
        ];

        // Count weather alerts
        $stmt = $this->db->query("SELECT COUNT(*) as count FROM weather_alerts WHERE active = true");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $summary['active_weather_alerts'] = $result['count'];

        return $summary;
    }

    /**
     * Summarize aircraft data
     */
    private function summarizeAircraft($aircraftData)
    {
        $summary = [
            'total_aircraft' => count($aircraftData),
            'compliant_flights' => 0,
            'non_compliant_flights' => 0,
            'unidentified_aircraft' => 0
        ];

        foreach ($aircraftData as $aircraft) {
            if (isset($aircraft['plan_compliance'])) {
                if ($aircraft['plan_compliance'] === 'no_plan_found') {
                    $summary['unidentified_aircraft']++;
                } elseif (is_array($aircraft['plan_compliance'])) {
                    if ($aircraft['plan_compliance']['overall'] === 'compliant') {
                        $summary['compliant_flights']++;
                    } else {
                        $summary['non_compliant_flights']++;
                    }
                }
            }
        }

        return $summary;
    }

    /**
     * Store fusion results
     */
    private function storeFusionResults($fusionReport)
    {
        $stmt = $this->db->prepare("
            INSERT INTO data_fusion_reports (
                report_timestamp, aircraft_count, conflicts_count,
                weather_summary, aircraft_summary, conflicts_data,
                system_status
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $fusionReport['timestamp'],
            $fusionReport['aircraft_count'],
            $fusionReport['conflicts_count'],
            json_encode($fusionReport['weather_conditions']),
            json_encode($fusionReport['aircraft_summary']),
            json_encode($fusionReport['conflicts']),
            $fusionReport['system_status']
        ]);
    }

    /**
     * Load fusion rules from configuration
     */
    private function loadFusionRules()
    {
        // Default fusion rules
        return [
            'position_weight_adsb' => 0.7,
            'position_weight_radar' => 0.3,
            'position_weight_satellite' => 0.2,
            'max_fusion_age_seconds' => 300,
            'conflict_horizontal_min_nm' => 5,
            'conflict_vertical_min_ft' => 1000,
            'weather_hazard_distance_km' => 10
        ];
    }

    /**
     * Utility functions
     */
    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371; // km

        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);

        $a = sin($latDelta/2) * sin($latDelta/2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($lonDelta/2) * sin($lonDelta/2);

        $c = 2 * atan2(sqrt($a), sqrt(1-$a));

        return $earthRadius * $c * 0.539957; // Convert to nautical miles
    }

    private function findNearestAircraft($radarPoint, $groupedAircraft)
    {
        $minDistance = PHP_FLOAT_MAX;
        $nearestId = null;

        foreach ($groupedAircraft as $aircraftId => $data) {
            if (!empty($data['adsb'])) {
                $adsbPoint = $data['adsb'][0];
                $distance = $this->calculateDistance(
                    $radarPoint['latitude'], $radarPoint['longitude'],
                    $adsbPoint['latitude'], $adsbPoint['longitude']
                );

                if ($distance < $minDistance && $distance < 5) { // Within 5nm
                    $minDistance = $distance;
                    $nearestId = $aircraftId;
                }
            }
        }

        return $nearestId;
    }

    private function findNearestWeather($aircraft, $weatherData)
    {
        $minDistance = PHP_FLOAT_MAX;
        $nearestWeather = null;

        foreach ($weatherData as $weather) {
            // Mock coordinates for weather stations
            $weatherLat = 40.0; // Would be stored in database
            $weatherLon = -74.0;

            $distance = $this->calculateDistance(
                $aircraft['latitude'], $aircraft['longitude'],
                $weatherLat, $weatherLon
            );

            if ($distance < $minDistance) {
                $minDistance = $distance;
                $nearestWeather = $weather;
            }
        }

        return $nearestWeather;
    }

    private function getExpectedAltitude($aircraft, $flightPlan)
    {
        // Simplified altitude expectation
        // In production, this would interpolate from flight plan profile
        return 35000; // Example cruise altitude
    }

    private function getExpectedSpeed($aircraft, $flightPlan)
    {
        // Simplified speed expectation
        return 500; // Example cruise speed in knots
    }
}
