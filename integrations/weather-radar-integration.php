<?php
/**
 * Weather Radar Integration
 *
 * Handles integration with NEXRAD, TDWR, satellite weather imagery,
 * METAR/TAF reports, and turbulence/wind shear detection.
 */

class WeatherRadarIntegration
{
    private $db;
    private $logger;
    private $apiKeys;

    public function __construct($database, $logger, $apiKeys = [])
    {
        $this->db = $database;
        $this->logger = $logger;
        $this->apiKeys = $apiKeys;
    }

    /**
     * Process NEXRAD weather radar data
     */
    public function processNEXRADData($radarData)
    {
        try {
            $this->db->beginTransaction();

            foreach ($radarData['data_points'] as $point) {
                $this->insertWeatherRadarData(array_merge($point, [
                    'radar_type' => 'nexrad',
                    'radar_station' => $radarData['station_id']
                ]));
            }

            // Process reflectivity data for precipitation analysis
            $this->analyzeReflectivityData($radarData);

            $this->db->commit();
            $this->logger->info("NEXRAD data processed successfully", ['station' => $radarData['station_id']]);

            return ['success' => true, 'points_processed' => count($radarData['data_points'])];

        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logger->error("Failed to process NEXRAD data", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Process TDWR (Terminal Doppler Weather Radar) data
     */
    public function processTDWRData($radarData)
    {
        try {
            $this->db->beginTransaction();

            foreach ($radarData['data_points'] as $point) {
                $this->insertWeatherRadarData(array_merge($point, [
                    'radar_type' => 'tdwr',
                    'radar_station' => $radarData['station_id']
                ]));
            }

            // Analyze for microbursts and wind shear
            $this->analyzeWindShearData($radarData);

            $this->db->commit();
            $this->logger->info("TDWR data processed successfully", ['station' => $radarData['station_id']]);

            return ['success' => true, 'points_processed' => count($radarData['data_points'])];

        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logger->error("Failed to process TDWR data", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Process satellite weather imagery
     */
    public function processSatelliteImagery($satelliteData)
    {
        try {
            $this->db->beginTransaction();

            // Store satellite image metadata
            $imageId = $this->storeSatelliteImage($satelliteData);

            // Process cloud cover and precipitation estimates
            $this->analyzeSatelliteData($satelliteData, $imageId);

            $this->db->commit();
            $this->logger->info("Satellite imagery processed successfully", ['image_id' => $imageId]);

            return ['success' => true, 'image_id' => $imageId];

        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logger->error("Failed to process satellite imagery", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Process METAR weather report
     */
    public function processMETARReport($metarData)
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO metar_reports (
                    station_id, observation_time, wind_direction, wind_speed, wind_gust,
                    visibility, temperature, dewpoint, altimeter_setting,
                    weather_conditions, sky_conditions, remarks, raw_text
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON CONFLICT (station_id, observation_time) DO UPDATE SET
                    wind_direction = EXCLUDED.wind_direction,
                    wind_speed = EXCLUDED.wind_speed,
                    wind_gust = EXCLUDED.wind_gust,
                    visibility = EXCLUDED.visibility,
                    temperature = EXCLUDED.temperature,
                    dewpoint = EXCLUDED.dewpoint,
                    altimeter_setting = EXCLUDED.altimeter_setting,
                    weather_conditions = EXCLUDED.weather_conditions,
                    sky_conditions = EXCLUDED.sky_conditions,
                    remarks = EXCLUDED.remarks,
                    raw_text = EXCLUDED.raw_text
            ");

            $stmt->execute([
                $metarData['station_id'],
                $metarData['observation_time'],
                $metarData['wind_direction'] ?? null,
                $metarData['wind_speed'] ?? null,
                $metarData['wind_gust'] ?? null,
                $metarData['visibility'] ?? null,
                $metarData['temperature'] ?? null,
                $metarData['dewpoint'] ?? null,
                $metarData['altimeter_setting'] ?? null,
                $metarData['weather_conditions'] ?? null,
                $metarData['sky_conditions'] ?? null,
                $metarData['remarks'] ?? null,
                $metarData['raw_text'] ?? null
            ]);

            $this->logger->info("METAR report processed", ['station' => $metarData['station_id']]);
            return ['success' => true];

        } catch (Exception $e) {
            $this->logger->error("Failed to process METAR report", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Process TAF (Terminal Aerodrome Forecast)
     */
    public function processTAFReport($tafData)
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO taf_reports (
                    station_id, issue_time, valid_from, valid_to, forecast_text, raw_text
                ) VALUES (?, ?, ?, ?, ?, ?)
                ON CONFLICT (station_id, issue_time) DO UPDATE SET
                    valid_from = EXCLUDED.valid_from,
                    valid_to = EXCLUDED.valid_to,
                    forecast_text = EXCLUDED.forecast_text,
                    raw_text = EXCLUDED.raw_text
            ");

            $stmt->execute([
                $tafData['station_id'],
                $tafData['issue_time'],
                $tafData['valid_from'],
                $tafData['valid_to'],
                $tafData['forecast_text'],
                $tafData['raw_text'] ?? null
            ]);

            $this->logger->info("TAF report processed", ['station' => $tafData['station_id']]);
            return ['success' => true];

        } catch (Exception $e) {
            $this->logger->error("Failed to process TAF report", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Insert weather radar data point
     */
    private function insertWeatherRadarData($data)
    {
        $stmt = $this->db->prepare("
            INSERT INTO weather_radar_data (
                radar_station, radar_type, latitude, longitude, altitude,
                reflectivity, velocity, spectrum_width, precipitation_type,
                intensity, recorded_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $data['radar_station'],
            $data['radar_type'],
            $data['latitude'],
            $data['longitude'],
            $data['altitude'] ?? null,
            $data['reflectivity'] ?? null,
            $data['velocity'] ?? null,
            $data['spectrum_width'] ?? null,
            $data['precipitation_type'] ?? null,
            $data['intensity'] ?? null,
            $data['recorded_at'] ?? date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Analyze reflectivity data for precipitation patterns
     */
    private function analyzeReflectivityData($radarData)
    {
        // Analyze reflectivity values to determine precipitation intensity
        $highReflectivity = array_filter($radarData['data_points'], function($point) {
            return ($point['reflectivity'] ?? 0) > 40; // High reflectivity indicates heavy precipitation
        });

        if (!empty($highReflectivity)) {
            $this->generateWeatherAlert([
                'alert_type' => 'heavy_precipitation',
                'severity' => 'moderate',
                'description' => 'Heavy precipitation detected in radar coverage area',
                'location_lat' => $radarData['station_lat'] ?? null,
                'location_lon' => $radarData['station_lon'] ?? null,
                'radius_km' => 50
            ]);
        }
    }

    /**
     * Analyze wind shear data from TDWR
     */
    private function analyzeWindShearData($radarData)
    {
        // Analyze velocity data for wind shear patterns
        $windShearDetected = false;
        $maxVelocityChange = 0;

        foreach ($radarData['data_points'] as $point) {
            if (isset($point['velocity'])) {
                // Simple wind shear detection based on velocity gradients
                // In practice, this would be more sophisticated
                $velocityChange = abs($point['velocity']);
                if ($velocityChange > $maxVelocityChange) {
                    $maxVelocityChange = $velocityChange;
                }
            }
        }

        if ($maxVelocityChange > 30) { // Significant wind shear threshold
            $this->generateWeatherAlert([
                'alert_type' => 'wind_shear',
                'severity' => 'severe',
                'description' => 'Significant wind shear detected',
                'location_lat' => $radarData['station_lat'] ?? null,
                'location_lon' => $radarData['station_lon'] ?? null,
                'radius_km' => 10
            ]);
        }
    }

    /**
     * Analyze satellite data for cloud cover and precipitation
     */
    private function analyzeSatelliteData($satelliteData, $imageId)
    {
        // Analyze satellite imagery for weather patterns
        // This would involve image processing algorithms
        // For now, we'll store basic metadata
    }

    /**
     * Store satellite image metadata
     */
    private function storeSatelliteImage($satelliteData)
    {
        // In a real implementation, this would store the actual image file
        // and return a reference ID
        return uniqid('sat_');
    }

    /**
     * Generate weather alert
     */
    public function generateWeatherAlert($alertData)
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO weather_alerts (
                    alert_type, severity, location_lat, location_lon, radius_km,
                    description, start_time, end_time, issued_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $alertData['alert_type'],
                $alertData['severity'],
                $alertData['location_lat'] ?? null,
                $alertData['location_lon'] ?? null,
                $alertData['radius_km'] ?? null,
                $alertData['description'],
                $alertData['start_time'] ?? date('Y-m-d H:i:s'),
                $alertData['end_time'] ?? date('Y-m-d H:i:s', strtotime('+2 hours')),
                $alertData['issued_by'] ?? 'weather_radar_system'
            ]);

            $alertId = $this->db->lastInsertId();
            $this->logger->info("Weather alert generated", ['alert_id' => $alertId, 'type' => $alertData['alert_type']]);

            return $alertId;

        } catch (Exception $e) {
            $this->logger->error("Failed to generate weather alert", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Get current weather conditions for location
     */
    public function getCurrentWeather($latitude, $longitude, $radiusKm = 50)
    {
        // Get latest METAR reports within radius
        $stmt = $this->db->prepare("
            SELECT *,
                   (6371 * acos(cos(radians(?)) * cos(radians(latitude)) *
                   cos(radians(longitude) - radians(?)) + sin(radians(?)) *
                   sin(radians(latitude)))) AS distance
            FROM metar_reports
            WHERE observation_time >= NOW() - INTERVAL '2 hours'
            HAVING distance < ?
            ORDER BY distance ASC, observation_time DESC
            LIMIT 5
        ");

        $stmt->execute([$latitude, $longitude, $latitude, $radiusKm]);
        $metarReports = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get active weather alerts
        $alerts = $this->getActiveWeatherAlerts($latitude, $longitude, $radiusKm);

        // Get radar data
        $radarData = $this->getNearbyRadarData($latitude, $longitude, $radiusKm);

        return [
            'metar_reports' => $metarReports,
            'weather_alerts' => $alerts,
            'radar_data' => $radarData
        ];
    }

    /**
     * Get active weather alerts
     */
    public function getActiveWeatherAlerts($latitude, $longitude, $radiusKm = 50)
    {
        $stmt = $this->db->prepare("
            SELECT *,
                   (6371 * acos(cos(radians(?)) * cos(radians(location_lat)) *
                   cos(radians(location_lon) - radians(?)) + sin(radians(?)) *
                   sin(radians(location_lat)))) AS distance
            FROM weather_alerts
            WHERE active = true
            AND start_time <= NOW()
            AND (end_time IS NULL OR end_time >= NOW())
            HAVING distance < ?
            ORDER BY severity DESC, issued_at DESC
        ");

        $stmt->execute([$latitude, $longitude, $latitude, $radiusKm]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get nearby radar data
     */
    private function getNearbyRadarData($latitude, $longitude, $radiusKm = 50)
    {
        $stmt = $this->db->prepare("
            SELECT *,
                   (6371 * acos(cos(radians(?)) * cos(radians(latitude)) *
                   cos(radians(longitude) - radians(?)) + sin(radians(?)) *
                   sin(radians(latitude)))) AS distance
            FROM weather_radar_data
            WHERE recorded_at >= NOW() - INTERVAL '15 minutes'
            HAVING distance < ?
            ORDER BY recorded_at DESC
            LIMIT 100
        ");

        $stmt->execute([$latitude, $longitude, $latitude, $radiusKm]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Detect turbulence from radar data
     */
    public function detectTurbulence($flightPath)
    {
        // Analyze radar data along flight path for turbulence indicators
        $turbulenceZones = [];

        // This would involve complex analysis of radar returns
        // For now, return mock data structure
        return $turbulenceZones;
    }

    /**
     * Get weather forecast for route
     */
    public function getRouteWeatherForecast($routePoints)
    {
        $forecast = [];

        foreach ($routePoints as $point) {
            $weather = $this->getCurrentWeather($point['latitude'], $point['longitude'], 25);
            $forecast[] = [
                'point' => $point,
                'weather' => $weather
            ];
        }

        return $forecast;
    }
}
