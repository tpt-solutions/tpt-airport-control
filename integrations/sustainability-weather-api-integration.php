<?php

/**
 * Sustainability Weather API Integration
 *
 * Integrates with weather APIs for noise monitoring and environmental analysis
 * Handles weather conditions that affect noise propagation and environmental monitoring
 */

require_once __DIR__ . '/../backend/config/database.php';
require_once __DIR__ . '/../backend/src/Logger.php';

class SustainabilityWeatherIntegration {
    private $pdo;
    private $weatherApiKey;
    private $weatherBaseUrl;
    private $logger;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->weatherApiKey = getenv('WEATHER_API_KEY') ?: '';
        $this->weatherBaseUrl = getenv('WEATHER_BASE_URL') ?: 'https://api.openweathermap.org/data/2.5';
        $this->logger = new Logger('sustainability_weather_integration');
    }

    /**
     * Get current weather conditions for noise monitoring
     */
    public function getCurrentWeather($location, $airportCode = null) {
        try {
            $this->logger->info("Getting current weather conditions", ['location' => $location]);

            // Get coordinates for location
            $coordinates = $this->getLocationCoordinates($location, $airportCode);

            if (!$coordinates) {
                throw new Exception("Could not determine coordinates for location: $location");
            }

            // Fetch current weather
            $weatherData = $this->fetchCurrentWeather($coordinates);

            if ($weatherData) {
                // Store weather data
                $this->storeWeatherData($weatherData, $location);

                // Analyze weather impact on noise
                $noiseImpact = $this->analyzeNoiseImpact($weatherData);

                return [
                    'location' => $location,
                    'coordinates' => $coordinates,
                    'weather' => $weatherData,
                    'noise_impact' => $noiseImpact,
                    'timestamp' => time()
                ];
            } else {
                throw new Exception("Failed to fetch weather data");
            }

        } catch (Exception $e) {
            $this->logger->error("Current weather fetch failed: " . $e->getMessage());
            return [
                'error' => $e->getMessage(),
                'location' => $location,
                'status' => 'error'
            ];
        }
    }

    /**
     * Get weather forecast for noise prediction
     */
    public function getWeatherForecast($location, $hours = 24, $airportCode = null) {
        try {
            $this->logger->info("Getting weather forecast", [
                'location' => $location,
                'hours' => $hours
            ]);

            // Get coordinates
            $coordinates = $this->getLocationCoordinates($location, $airportCode);

            if (!$coordinates) {
                throw new Exception("Could not determine coordinates for location: $location");
            }

            // Fetch forecast
            $forecastData = $this->fetchWeatherForecast($coordinates, $hours);

            if ($forecastData) {
                // Store forecast data
                $this->storeForecastData($forecastData, $location);

                // Analyze forecast impact on noise monitoring
                $forecastAnalysis = $this->analyzeForecastImpact($forecastData);

                return [
                    'location' => $location,
                    'coordinates' => $coordinates,
                    'forecast' => $forecastData,
                    'analysis' => $forecastAnalysis,
                    'period_hours' => $hours,
                    'timestamp' => time()
                ];
            } else {
                throw new Exception("Failed to fetch weather forecast");
            }

        } catch (Exception $e) {
            $this->logger->error("Weather forecast fetch failed: " . $e->getMessage());
            return [
                'error' => $e->getMessage(),
                'location' => $location,
                'status' => 'error'
            ];
        }
    }

    /**
     * Analyze weather impact on noise propagation
     */
    public function analyzeNoisePropagation($noiseData, $weatherData) {
        try {
            $this->logger->info("Analyzing noise propagation impact", [
                'noise_sensor' => $noiseData['sensor_id'] ?? null
            ]);

            // Calculate noise propagation factors
            $propagationFactors = $this->calculatePropagationFactors($weatherData);

            // Adjust noise readings based on weather
            $adjustedReadings = $this->adjustNoiseForWeather($noiseData, $propagationFactors);

            // Calculate effective noise impact
            $effectiveImpact = $this->calculateEffectiveImpact($adjustedReadings, $weatherData);

            return [
                'original_readings' => $noiseData,
                'propagation_factors' => $propagationFactors,
                'adjusted_readings' => $adjustedReadings,
                'effective_impact' => $effectiveImpact,
                'weather_conditions' => $weatherData,
                'analysis_timestamp' => time()
            ];

        } catch (Exception $e) {
            $this->logger->error("Noise propagation analysis failed: " . $e->getMessage());
            return [
                'error' => $e->getMessage(),
                'status' => 'error'
            ];
        }
    }

    /**
     * Get historical weather patterns for environmental analysis
     */
    public function getHistoricalWeatherPatterns($location, $startDate, $endDate, $airportCode = null) {
        try {
            $this->logger->info("Getting historical weather patterns", [
                'location' => $location,
                'start_date' => $startDate,
                'end_date' => $endDate
            ]);

            // Get coordinates
            $coordinates = $this->getLocationCoordinates($location, $airportCode);

            if (!$coordinates) {
                throw new Exception("Could not determine coordinates for location: $location");
            }

            // Fetch historical data
            $historicalData = $this->fetchHistoricalWeather($coordinates, $startDate, $endDate);

            if ($historicalData) {
                // Analyze patterns
                $patterns = $this->analyzeWeatherPatterns($historicalData);

                // Correlate with noise data
                $noiseCorrelation = $this->correlateWithNoiseData($location, $historicalData, $startDate, $endDate);

                return [
                    'location' => $location,
                    'coordinates' => $coordinates,
                    'period' => ['start_date' => $startDate, 'end_date' => $endDate],
                    'historical_data' => $historicalData,
                    'patterns' => $patterns,
                    'noise_correlation' => $noiseCorrelation,
                    'analysis_timestamp' => time()
                ];
            } else {
                throw new Exception("Failed to fetch historical weather data");
            }

        } catch (Exception $e) {
            $this->logger->error("Historical weather analysis failed: " . $e->getMessage());
            return [
                'error' => $e->getMessage(),
                'location' => $location,
                'status' => 'error'
            ];
        }
    }

    /**
     * Monitor weather conditions for noise compliance
     */
    public function monitorWeatherForCompliance($airportCode, $monitoringZone = null) {
        try {
            $this->logger->info("Monitoring weather for noise compliance", [
                'airport' => $airportCode,
                'zone' => $monitoringZone
            ]);

            // Get current weather for airport
            $currentWeather = $this->getCurrentWeather($airportCode, $airportCode);

            if (isset($currentWeather['error'])) {
                throw new Exception($currentWeather['error']);
            }

            // Get noise monitoring zones
            $zones = $this->getNoiseMonitoringZones($airportCode, $monitoringZone);

            $complianceResults = [];

            foreach ($zones as $zone) {
                // Get current noise readings for zone
                $noiseReadings = $this->getZoneNoiseReadings($zone);

                // Analyze compliance considering weather
                $zoneCompliance = $this->analyzeZoneCompliance($zone, $noiseReadings, $currentWeather);

                $complianceResults[] = [
                    'zone_id' => $zone['zone_id'],
                    'zone_name' => $zone['zone_name'],
                    'current_weather' => $currentWeather,
                    'noise_readings' => $noiseReadings,
                    'compliance_analysis' => $zoneCompliance,
                    'recommendations' => $this->generateComplianceRecommendations($zoneCompliance)
                ];
            }

            return [
                'airport_code' => $airportCode,
                'monitoring_zone' => $monitoringZone,
                'current_weather' => $currentWeather,
                'compliance_results' => $complianceResults,
                'overall_compliance' => $this->calculateOverallCompliance($complianceResults),
                'timestamp' => time()
            ];

        } catch (Exception $e) {
            $this->logger->error("Weather compliance monitoring failed: " . $e->getMessage());
            return [
                'error' => $e->getMessage(),
                'airport_code' => $airportCode,
                'status' => 'error'
            ];
        }
    }

    /**
     * Generate weather-based noise monitoring report
     */
    public function generateNoiseMonitoringReport($airportCode, $startDate, $endDate) {
        try {
            $this->logger->info("Generating noise monitoring report", [
                'airport' => $airportCode,
                'start_date' => $startDate,
                'end_date' => $endDate
            ]);

            // Get weather data for period
            $weatherData = $this->getHistoricalWeatherPatterns($airportCode, $startDate, $endDate, $airportCode);

            // Get noise data for period
            $noiseData = $this->getNoiseDataForPeriod($airportCode, $startDate, $endDate);

            // Analyze weather-noise correlation
            $correlation = $this->analyzeWeatherNoiseCorrelation($weatherData, $noiseData);

            // Generate compliance summary
            $complianceSummary = $this->generateComplianceSummary($correlation);

            return [
                'airport_code' => $airportCode,
                'report_period' => ['start_date' => $startDate, 'end_date' => $endDate],
                'weather_analysis' => $weatherData,
                'noise_analysis' => $noiseData,
                'correlation_analysis' => $correlation,
                'compliance_summary' => $complianceSummary,
                'recommendations' => $this->generateReportRecommendations($complianceSummary),
                'generated_at' => date('Y-m-d H:i:s')
            ];

        } catch (Exception $e) {
            $this->logger->error("Noise monitoring report generation failed: " . $e->getMessage());
            return [
                'error' => $e->getMessage(),
                'airport_code' => $airportCode,
                'status' => 'error'
            ];
        }
    }

    // Helper methods

    private function getLocationCoordinates($location, $airportCode = null) {
        // First try to get coordinates from airport database
        if ($airportCode) {
            $stmt = $this->pdo->prepare("
                SELECT latitude, longitude FROM airports
                WHERE airport_code = ?
            ");
            $stmt->execute([$airportCode]);
            $airport = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($airport) {
                return [
                    'lat' => $airport['latitude'],
                    'lon' => $airport['longitude']
                ];
            }
        }

        // Fallback to geocoding API
        return $this->geocodeLocation($location);
    }

    private function geocodeLocation($location) {
        $url = "https://api.openweathermap.org/geo/1.0/direct?q=" . urlencode($location) . "&limit=1&appid=" . $this->weatherApiKey;

        $response = file_get_contents($url);
        if ($response) {
            $data = json_decode($response, true);
            if (!empty($data) && isset($data[0]['lat']) && isset($data[0]['lon'])) {
                return [
                    'lat' => $data[0]['lat'],
                    'lon' => $data[0]['lon']
                ];
            }
        }

        return null;
    }

    private function fetchCurrentWeather($coordinates) {
        $url = $this->weatherBaseUrl . "/weather?lat={$coordinates['lat']}&lon={$coordinates['lon']}&appid={$this->weatherApiKey}&units=metric";

        $response = file_get_contents($url);
        if ($response) {
            $data = json_decode($response, true);
            if ($data && !isset($data['cod']) || $data['cod'] == 200) {
                return $this->formatWeatherData($data);
            }
        }

        return null;
    }

    private function fetchWeatherForecast($coordinates, $hours) {
        $url = $this->weatherBaseUrl . "/forecast?lat={$coordinates['lat']}&lon={$coordinates['lon']}&appid={$this->weatherApiKey}&units=metric";

        $response = file_get_contents($url);
        if ($response) {
            $data = json_decode($response, true);
            if ($data && !isset($data['cod']) || $data['cod'] == 200) {
                return $this->formatForecastData($data, $hours);
            }
        }

        return null;
    }

    private function fetchHistoricalWeather($coordinates, $startDate, $endDate) {
        // Note: Historical data might require a different API endpoint or service
        // This is a simplified implementation
        $start = strtotime($startDate);
        $end = strtotime($endDate);

        $historicalData = [];

        // For demonstration, we'll use forecast data as proxy
        // In production, you'd use a historical weather API
        $forecast = $this->fetchWeatherForecast($coordinates, 24);
        if ($forecast) {
            $historicalData = $forecast;
        }

        return $historicalData;
    }

    private function formatWeatherData($rawData) {
        return [
            'temperature' => $rawData['main']['temp'] ?? null,
            'humidity' => $rawData['main']['humidity'] ?? null,
            'pressure' => $rawData['main']['pressure'] ?? null,
            'wind_speed' => $rawData['wind']['speed'] ?? null,
            'wind_direction' => $rawData['wind']['deg'] ?? null,
            'weather_condition' => $rawData['weather'][0]['main'] ?? null,
            'weather_description' => $rawData['weather'][0]['description'] ?? null,
            'visibility' => $rawData['visibility'] ?? null,
            'cloud_cover' => $rawData['clouds']['all'] ?? null,
            'recorded_at' => date('Y-m-d H:i:s', $rawData['dt'] ?? time())
        ];
    }

    private function formatForecastData($rawData, $hours) {
        $forecast = [];
        $maxItems = min(count($rawData['list']), ceil($hours / 3)); // API provides 3-hour intervals

        for ($i = 0; $i < $maxItems; $i++) {
            $item = $rawData['list'][$i];
            $forecast[] = $this->formatWeatherData($item);
        }

        return $forecast;
    }

    private function storeWeatherData($weatherData, $location) {
        $stmt = $this->pdo->prepare("
            INSERT INTO weather_data (
                location, temperature_celsius, humidity_percentage,
                pressure_hpa, wind_speed_ms, wind_direction_deg,
                weather_condition, weather_description, visibility_m,
                cloud_cover_percentage, recorded_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $location,
            $weatherData['temperature'],
            $weatherData['humidity'],
            $weatherData['pressure'],
            $weatherData['wind_speed'],
            $weatherData['wind_direction'],
            $weatherData['weather_condition'],
            $weatherData['weather_description'],
            $weatherData['visibility'],
            $weatherData['cloud_cover'],
            $weatherData['recorded_at']
        ]);
    }

    private function storeForecastData($forecastData, $location) {
        $stmt = $this->pdo->prepare("
            INSERT INTO weather_forecast (
                location, forecast_data, forecast_period_hours, recorded_at
            ) VALUES (?, ?, ?, NOW())
        ");

        $stmt->execute([
            $location,
            json_encode($forecastData),
            count($forecastData) * 3, // Assuming 3-hour intervals
            date('Y-m-d H:i:s')
        ]);
    }

    private function analyzeNoiseImpact($weatherData) {
        $impact = [
            'propagation_factor' => 1.0,
            'attenuation_factors' => [],
            'effective_range_multiplier' => 1.0,
            'recommendations' => []
        ];

        // Temperature effect on sound speed
        $temp = $weatherData['temperature'] ?? 20;
        if ($temp > 25) {
            $impact['propagation_factor'] *= 1.1;
            $impact['attenuation_factors'][] = 'high_temperature_increases_propagation';
        } elseif ($temp < 5) {
            $impact['propagation_factor'] *= 0.9;
            $impact['attenuation_factors'][] = 'low_temperature_reduces_propagation';
        }

        // Humidity effect
        $humidity = $weatherData['humidity'] ?? 50;
        if ($humidity > 80) {
            $impact['propagation_factor'] *= 1.05;
            $impact['attenuation_factors'][] = 'high_humidity_increases_propagation';
        } elseif ($humidity < 30) {
            $impact['propagation_factor'] *= 0.95;
            $impact['attenuation_factors'][] = 'low_humidity_reduces_propagation';
        }

        // Wind effect
        $windSpeed = $weatherData['wind_speed'] ?? 0;
        if ($windSpeed > 10) {
            $impact['propagation_factor'] *= 1.15;
            $impact['attenuation_factors'][] = 'strong_wind_increases_propagation';
            $impact['effective_range_multiplier'] = 1.2;
        }

        // Weather conditions
        $condition = strtolower($weatherData['weather_condition'] ?? '');
        if (strpos($condition, 'rain') !== false) {
            $impact['propagation_factor'] *= 0.8;
            $impact['attenuation_factors'][] = 'rain_attenuates_noise';
        } elseif (strpos($condition, 'snow') !== false) {
            $impact['propagation_factor'] *= 0.7;
            $impact['attenuation_factors'][] = 'snow_attenuates_noise';
        } elseif (strpos($condition, 'fog') !== false) {
            $impact['propagation_factor'] *= 0.85;
            $impact['attenuation_factors'][] = 'fog_attenuates_noise';
        }

        // Generate recommendations
        if ($impact['propagation_factor'] > 1.1) {
            $impact['recommendations'][] = 'Increase monitoring sensitivity due to favorable propagation conditions';
        } elseif ($impact['propagation_factor'] < 0.9) {
            $impact['recommendations'][] = 'Consider reduced monitoring sensitivity due to poor propagation conditions';
        }

        return $impact;
    }

    private function analyzeForecastImpact($forecastData) {
        $analysis = [
            'period_summary' => [],
            'noise_impact_trend' => 'stable',
            'critical_periods' => [],
            'monitoring_recommendations' => []
        ];

        $propagationFactors = [];
        foreach ($forecastData as $forecast) {
            $impact = $this->analyzeNoiseImpact($forecast);
            $propagationFactors[] = $impact['propagation_factor'];

            if ($impact['propagation_factor'] > 1.2) {
                $analysis['critical_periods'][] = [
                    'time' => $forecast['recorded_at'],
                    'impact_level' => 'high',
                    'reason' => 'favorable_noise_propagation'
                ];
            }
        }

        // Analyze trend
        if (!empty($propagationFactors)) {
            $avgFactor = array_sum($propagationFactors) / count($propagationFactors);
            $maxFactor = max($propagationFactors);
            $minFactor = min($propagationFactors);

            if ($maxFactor - $minFactor > 0.3) {
                $analysis['noise_impact_trend'] = 'variable';
            } elseif ($avgFactor > 1.05) {
                $analysis['noise_impact_trend'] = 'increasing';
            } elseif ($avgFactor < 0.95) {
                $analysis['noise_impact_trend'] = 'decreasing';
            }
        }

        // Generate monitoring recommendations
        if ($analysis['noise_impact_trend'] === 'increasing') {
            $analysis['monitoring_recommendations'][] = 'Increase monitoring frequency during forecast period';
        }

        if (!empty($analysis['critical_periods'])) {
            $analysis['monitoring_recommendations'][] = 'Schedule additional monitoring during critical periods';
        }

        return $analysis;
    }

    private function calculatePropagationFactors($weatherData) {
        // Advanced noise propagation calculation
        $factors = [
            'temperature_effect' => $this->calculateTemperatureEffect($weatherData['temperature'] ?? 20),
            'humidity_effect' => $this->calculateHumidityEffect($weatherData['humidity'] ?? 50),
            'wind_effect' => $this->calculateWindEffect($weatherData['wind_speed'] ?? 0),
            'atmospheric_attenuation' => $this->calculateAtmosphericAttenuation($weatherData)
        ];

        $factors['combined_factor'] = array_product($factors);

        return $factors;
    }

    private function calculateTemperatureEffect($temperature) {
        // Sound speed increases with temperature
        // Approximate effect on propagation
        return 1 + ($temperature - 20) * 0.001;
    }

    private function calculateHumidityEffect($humidity) {
        // High humidity can increase propagation
        return 1 + ($humidity - 50) * 0.0005;
    }

    private function calculateWindEffect($windSpeed) {
        // Wind can carry sound further
        return 1 + ($windSpeed * 0.01);
    }

    private function calculateAtmosphericAttenuation($weatherData) {
        $attenuation = 1.0;

        $condition = strtolower($weatherData['weather_condition'] ?? '');
        if (strpos($condition, 'rain') !== false) {
            $attenuation *= 0.8;
        } elseif (strpos($condition, 'snow') !== false) {
            $attenuation *= 0.7;
        } elseif (strpos($condition, 'fog') !== false) {
            $attenuation *= 0.85;
        }

        return $attenuation;
    }

    private function adjustNoiseForWeather($noiseData, $propagationFactors) {
        $adjusted = $noiseData;

        if (isset($noiseData['noise_level_db'])) {
            $adjustment = 10 * log10($propagationFactors['combined_factor']);
            $adjusted['adjusted_noise_level_db'] = $noiseData['noise_level_db'] + $adjustment;
            $adjusted['adjustment_db'] = $adjustment;
            $adjusted['propagation_factor'] = $propagationFactors['combined_factor'];
        }

        return $adjusted;
    }

    private function calculateEffectiveImpact($adjustedReadings, $weatherData) {
        $impact = [
            'effective_noise_level' => $adjustedReadings['adjusted_noise_level_db'] ?? $adjustedReadings['noise_level_db'] ?? 0,
            'weather_adjusted' => true,
            'compliance_status' => 'unknown',
            'risk_level' => 'low'
        ];

        // Determine compliance status
        $threshold = $this->getNoiseThreshold($weatherData);
        if ($impact['effective_noise_level'] > $threshold) {
            $impact['compliance_status'] = 'non_compliant';
            $impact['risk_level'] = $impact['effective_noise_level'] > $threshold + 10 ? 'high' : 'medium';
        } else {
            $impact['compliance_status'] = 'compliant';
        }

        return $impact;
    }

    private function getNoiseThreshold($weatherData) {
        // Base threshold
        $threshold = 65; // dB

        // Adjust based on time of day
        $hour = date('H');
        if ($hour >= 22 || $hour <= 6) {
            $threshold = 55; // Night time threshold
        }

        // Adjust based on weather conditions that affect propagation
        $condition = strtolower($weatherData['weather_condition'] ?? '');
        if (strpos($condition, 'rain') !== false || strpos($condition, 'snow') !== false) {
            $threshold += 5; // Allow higher levels when weather attenuates noise
        }

        return $threshold;
    }

    private function analyzeWeatherPatterns($historicalData) {
        if (empty($historicalData)) {
            return ['patterns' => [], 'summary' => 'insufficient_data'];
        }

        $temperatures = array_column($historicalData, 'temperature');
        $humidities = array_column($historicalData, 'humidity');
        $windSpeeds = array_column($historicalData, 'wind_speed');

        return [
            'average_temperature' => array_sum($temperatures) / count($temperatures),
            'temperature_range' => max($temperatures) - min($temperatures),
            'average_humidity' => array_sum($humidities) / count($humidities),
            'average_wind_speed' => array_sum($windSpeeds) / count($windSpeeds),
            'dominant_weather' => $this->findDominantWeather($historicalData),
            'seasonal_patterns' => $this->analyzeSeasonalPatterns($historicalData)
        ];
    }

    private function findDominantWeather($weatherData) {
        $conditions = array_count_values(array_column($weatherData, 'weather_condition'));
        arsort($conditions);
        return array_key_first($conditions);
    }

    private function analyzeSeasonalPatterns($weatherData) {
        // Simplified seasonal analysis
        return [
            'pattern_type' => 'variable',
            'recommendation' => 'continuous_monitoring_required'
        ];
    }

    private function correlateWithNoiseData($location, $weatherData, $startDate, $endDate) {
        // Get noise data for the same period
        $stmt = $this->pdo->prepare("
            SELECT recorded_at, noise_level_db
            FROM noise_monitoring
            WHERE location = ?
            AND recorded_at BETWEEN ? AND ?
            ORDER BY recorded_at
        ");
        $stmt->execute([$location, $startDate, $endDate]);
        $noiseData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($noiseData)) {
            return ['correlation' => 'no_noise_data', 'coefficient' => 0];
        }

        // Simple correlation analysis
        $correlation = $this->calculateCorrelation($weatherData, $noiseData);

        return [
            'correlation_coefficient' => $correlation,
            'correlation_strength' => $this->interpretCorrelation($correlation),
            'data_points' => count($noiseData),
            'analysis_period' => [$startDate, $endDate]
        ];
    }

    private function calculateCorrelation($weatherData, $noiseData) {
        // Simplified correlation calculation
        // In production, you'd use proper statistical methods
        return 0.0; // Placeholder
    }

    private function interpretCorrelation($coefficient) {
        $abs = abs($coefficient);
        if ($abs > 0.7) return 'strong';
        if ($abs > 0.5) return 'moderate';
        if ($abs > 0.3) return 'weak';
        return 'very_weak';
    }

    private function getNoiseMonitoringZones($airportCode, $specificZone = null) {
        $whereClause = $specificZone ? " AND zone_id = ?" : "";
        $params = $specificZone ? [$airportCode, $specificZone] : [$airportCode];

        $stmt = $this->pdo->prepare("
            SELECT * FROM noise_monitoring_zones
            WHERE airport_code = ? $whereClause
            ORDER BY zone_name
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getZoneNoiseReadings($zone) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM noise_monitoring
            WHERE location = ?
            AND recorded_at > NOW() - INTERVAL '1 hour'
            ORDER BY recorded_at DESC
            LIMIT 10
        ");
        $stmt->execute([$zone['zone_name']]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function analyzeZoneCompliance($zone, $noiseReadings, $weatherData) {
        if (empty($noiseReadings)) {
            return ['status' => 'no_data', 'compliance' => 'unknown'];
        }

        $threshold = $this->getNoiseThreshold($weatherData);
        $violations = 0;
        $maxLevel = 0;

        foreach ($noiseReadings as $reading) {
            $level = $reading['noise_level_db'];
            $maxLevel = max($maxLevel, $level);
            if ($level > $threshold) {
                $violations++;
            }
        }

        $complianceRate = 1 - ($violations / count($noiseReadings));

        return [
            'zone_id' => $zone['zone_id'],
            'threshold_db' => $threshold,
            'max_noise_level' => $maxLevel,
            'violations_count' => $violations,
            'total_readings' => count($noiseReadings),
            'compliance_rate' => round($complianceRate * 100, 2),
            'status' => $complianceRate > 0.95 ? 'compliant' : ($complianceRate > 0.85 ? 'warning' : 'non_compliant')
        ];
    }

    private function generateComplianceRecommendations($zoneCompliance) {
        $recommendations = [];

        if ($zoneCompliance['status'] === 'non_compliant') {
            $recommendations[] = 'Immediate action required to reduce noise levels';
            $recommendations[] = 'Review flight paths and schedules';
        } elseif ($zoneCompliance['status'] === 'warning') {
            $recommendations[] = 'Monitor noise levels closely';
            $recommendations[] = 'Consider operational adjustments';
        } else {
            $recommendations[] = 'Continue current monitoring practices';
        }

        return $recommendations;
    }

    private function calculateOverallCompliance($complianceResults) {
        if (empty($complianceResults)) {
            return ['status' => 'unknown', 'compliance_rate' => 0];
        }

        $totalRate = 0;
        $nonCompliant = 0;

        foreach ($complianceResults as $result) {
            $totalRate += $result['compliance_analysis']['compliance_rate'];
            if ($result['compliance_analysis']['status'] === 'non_compliant') {
                $nonCompliant++;
            }
        }

        $averageRate = $totalRate / count($complianceResults);
        $overallStatus = $nonCompliant > 0 ? 'non_compliant' :
                        ($averageRate > 95 ? 'compliant' : 'warning');

        return [
            'status' => $overallStatus,
            'average_compliance_rate' => round($averageRate, 2),
            'non_compliant_zones' => $nonCompliant,
            'total_zones' => count($complianceResults)
        ];
    }

    private function getNoiseDataForPeriod($airportCode, $startDate, $endDate) {
        $stmt = $this->pdo->prepare("
            SELECT
                DATE(recorded_at) as date,
                AVG(noise_level_db) as avg_noise,
                MAX(noise_level_db) as max_noise,
                MIN(noise_level_db) as min_noise,
                COUNT(*) as reading_count
            FROM noise_monitoring
            WHERE location LIKE ?
            AND recorded_at BETWEEN ? AND ?
            GROUP BY DATE(recorded_at)
            ORDER BY date
        ");
        $stmt->execute([$airportCode . '%', $startDate, $endDate]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function analyzeWeatherNoiseCorrelation($weatherData, $noiseData) {
        // Simplified correlation analysis
        return [
            'correlation_found' => false,
            'correlation_strength' => 'unknown',
            'key_factors' => ['temperature', 'wind_speed', 'humidity'],
            'recommendations' => ['continue_monitoring', 'collect_more_data']
        ];
    }

    private function generateComplianceSummary($correlation) {
        return [
            'overall_compliance' => 'monitoring',
            'trends' => 'stable',
            'risk_areas' => [],
            'improvement_areas' => ['data_collection', 'analysis_methods']
        ];
    }

    private function generateReportRecommendations($complianceSummary) {
        return [
            'Increase monitoring frequency in high-risk areas',
            'Implement weather-based noise prediction models',
            'Develop automated alert systems for noise violations',
            'Regular calibration of noise monitoring equipment'
        ];
    }
}

// Database tables for weather integration
$weatherTablesSQL = "
-- Weather data table
CREATE TABLE IF NOT EXISTS weather_data (
    weather_id SERIAL PRIMARY KEY,
    location VARCHAR(100) NOT NULL,
    temperature_celsius DECIMAL(5,2),
    humidity_percentage DECIMAL(5,2),
    pressure_hpa DECIMAL(7,2),
    wind_speed_ms DECIMAL(5,2),
    wind_direction_deg DECIMAL(5,1),
    weather_condition VARCHAR(50),
    weather_description VARCHAR(100),
    visibility_m INTEGER,
    cloud_cover_percentage DECIMAL(5,2),
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Weather forecast table
CREATE TABLE IF NOT EXISTS weather_forecast (
    forecast_id SERIAL PRIMARY KEY,
    location VARCHAR(100) NOT NULL,
    forecast_data JSONB,
    forecast_period_hours INTEGER,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Noise monitoring zones table
CREATE TABLE IF NOT EXISTS noise_monitoring_zones (
    zone_id SERIAL PRIMARY KEY,
    airport_code VARCHAR(10) NOT NULL,
    zone_name VARCHAR(100) NOT NULL,
    latitude DECIMAL(10,6),
    longitude DECIMAL(10,6),
    radius_meters INTEGER,
    noise_threshold_db DECIMAL(5,1),
    monitoring_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Indexes for performance
CREATE INDEX IF NOT EXISTS idx_weather_data_location_time ON weather_data (location, recorded_at);
CREATE INDEX IF NOT EXISTS idx_weather_forecast_location ON weather_forecast (location, recorded_at);
CREATE INDEX IF NOT EXISTS idx_noise_zones_airport ON noise_monitoring_zones (airport_code);
";

// Usage example:
/*
$weatherIntegration = new SustainabilityWeatherIntegration($pdo);

// Get current weather
$current = $weatherIntegration->getCurrentWeather('JFK', 'JFK');

// Get weather forecast
$forecast = $weatherIntegration->getWeatherForecast('JFK', 24, 'JFK');

// Analyze noise propagation
$analysis = $weatherIntegration->analyzeNoisePropagation([
    'sensor_id' => 'NOISE-001',
    'noise_level_db' => 68.5
], $current['weather']);

// Monitor weather for compliance
$compliance = $weatherIntegration->monitorWeatherForCompliance('JFK');

// Generate noise monitoring report
$report = $weatherIntegration->generateNoiseMonitoringReport('JFK', '2023-12-01', '2023-12-31');
*/
?>
