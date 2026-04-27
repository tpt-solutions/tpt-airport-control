<?php
/**
 * Weather API Integration
 *
 * Integrates with external weather services for aviation weather data
 * Supports multiple weather providers (NOAA, OpenWeatherMap, Aviation Weather Center)
 */

class WeatherAPIIntegration
{
    private $db;
    private $logger;
    private $apiKeys;
    private $cache;

    public function __construct($database, $logger, $apiKeys = [])
    {
        $this->db = $database;
        $this->logger = $logger;
        $this->apiKeys = $apiKeys;
        $this->cache = [];
    }

    /**
     * Fetch current weather for airport
     */
    public function getAirportWeather($icaoCode, $provider = 'auto')
    {
        try {
            // Check cache first
            $cacheKey = "weather_{$icaoCode}";
            if (isset($this->cache[$cacheKey]) &&
                (time() - $this->cache[$cacheKey]['timestamp']) < 300) { // 5 minute cache
                return $this->cache[$cacheKey]['data'];
            }

            $weatherData = null;

            switch ($provider) {
                case 'noaa':
                    $weatherData = $this->fetchFromNOAA($icaoCode);
                    break;
                case 'aviation_weather':
                    $weatherData = $this->fetchFromAviationWeather($icaoCode);
                    break;
                case 'openweather':
                    $weatherData = $this->fetchFromOpenWeather($icaoCode);
                    break;
                case 'auto':
                default:
                    $weatherData = $this->fetchAuto($icaoCode);
                    break;
            }

            if ($weatherData) {
                // Cache the result
                $this->cache[$cacheKey] = [
                    'timestamp' => time(),
                    'data' => $weatherData
                ];

                // Store in database
                $this->storeWeatherData($icaoCode, $weatherData);

                return $weatherData;
            }

            return null;

        } catch (Exception $e) {
            $this->logger->error("Failed to fetch weather for {$icaoCode}", ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Fetch weather from NOAA Aviation Weather Center
     */
    private function fetchFromNOAA($icaoCode)
    {
        if (!isset($this->apiKeys['noaa'])) {
            throw new Exception("NOAA API key not configured");
        }

        $url = "https://aviationweather.gov/api/data/metar?ids={$icaoCode}&format=json";

        $response = $this->makeAPIRequest($url);
        if (!$response) return null;

        $data = json_decode($response, true);
        if (!$data || !isset($data[0])) return null;

        $metar = $data[0];

        return [
            'station_id' => $icaoCode,
            'observation_time' => $metar['obsTime'] ?? null,
            'temperature' => $metar['temp'] ?? null,
            'dewpoint' => $metar['dewp'] ?? null,
            'wind_direction' => $metar['wdir'] ?? null,
            'wind_speed' => $metar['wspd'] ?? null,
            'wind_gust' => $metar['wgst'] ?? null,
            'visibility' => $metar['visib'] ?? null,
            'altimeter_setting' => $metar['altim'] ?? null,
            'weather_conditions' => $metar['wxString'] ?? null,
            'sky_conditions' => $metar['cloudLayers'] ?? null,
            'raw_text' => $metar['rawOb'] ?? null,
            'source' => 'noaa'
        ];
    }

    /**
     * Fetch weather from Aviation Weather Center
     */
    private function fetchFromAviationWeather($icaoCode)
    {
        $url = "https://aviationweather.gov/api/data/metar?ids={$icaoCode}&format=xml";

        $response = $this->makeAPIRequest($url);
        if (!$response) return null;

        // Parse XML response
        $xml = simplexml_load_string($response);
        if (!$xml) return null;

        $metar = $xml->METAR[0];
        if (!$metar) return null;

        return [
            'station_id' => $icaoCode,
            'observation_time' => (string)$metar->observation_time,
            'temperature' => (float)$metar->temp_c,
            'dewpoint' => (float)$metar->dewpoint_c,
            'wind_direction' => (int)$metar->wind_dir_degrees,
            'wind_speed' => (float)$metar->wind_speed_kt,
            'visibility' => (float)$metar->visibility_statute_mi,
            'altimeter_setting' => (float)$metar->altim_in_hg,
            'weather_conditions' => (string)$metar->wx_string,
            'sky_conditions' => (string)$metar->sky_condition,
            'raw_text' => (string)$metar->raw_text,
            'source' => 'aviation_weather'
        ];
    }

    /**
     * Fetch weather from OpenWeatherMap
     */
    private function fetchFromOpenWeather($icaoCode)
    {
        if (!isset($this->apiKeys['openweather'])) {
            throw new Exception("OpenWeatherMap API key not configured");
        }

        // First get coordinates for ICAO code
        $coords = $this->getAirportCoordinates($icaoCode);
        if (!$coords) return null;

        $url = "https://api.openweathermap.org/data/2.5/weather?" .
               "lat={$coords['lat']}&lon={$coords['lon']}&appid={$this->apiKeys['openweather']}&units=metric";

        $response = $this->makeAPIRequest($url);
        if (!$response) return null;

        $data = json_decode($response, true);
        if (!$data) return null;

        return [
            'station_id' => $icaoCode,
            'observation_time' => date('Y-m-d H:i:s', $data['dt']),
            'temperature' => $data['main']['temp'],
            'dewpoint' => isset($data['main']['dew_point']) ? $data['main']['dew_point'] : null,
            'wind_direction' => $data['wind']['deg'] ?? null,
            'wind_speed' => isset($data['wind']['speed']) ? $data['wind']['speed'] * 1.94384 : null, // m/s to knots
            'visibility' => isset($data['visibility']) ? $data['visibility'] / 1609 : null, // meters to miles
            'weather_conditions' => $data['weather'][0]['description'] ?? null,
            'humidity' => $data['main']['humidity'] ?? null,
            'pressure' => $data['main']['pressure'] ?? null,
            'source' => 'openweather'
        ];
    }

    /**
     * Auto-select best weather provider
     */
    private function fetchAuto($icaoCode)
    {
        // Try NOAA first (most reliable for aviation)
        try {
            return $this->fetchFromNOAA($icaoCode);
        } catch (Exception $e) {
            $this->logger->info("NOAA weather fetch failed, trying Aviation Weather", ['icao' => $icaoCode]);
        }

        // Fallback to Aviation Weather
        try {
            return $this->fetchFromAviationWeather($icaoCode);
        } catch (Exception $e) {
            $this->logger->info("Aviation Weather fetch failed, trying OpenWeatherMap", ['icao' => $icaoCode]);
        }

        // Final fallback to OpenWeatherMap
        try {
            return $this->fetchFromOpenWeather($icaoCode);
        } catch (Exception $e) {
            $this->logger->error("All weather providers failed", ['icao' => $icaoCode]);
            return null;
        }
    }

    /**
     * Get airport coordinates (mock implementation)
     */
    private function getAirportCoordinates($icaoCode)
    {
        // In production, this would query an airport database
        $airportCoords = [
            'KJFK' => ['lat' => 40.6413, 'lon' => -73.7781],
            'KLAX' => ['lat' => 33.9425, 'lon' => -118.4081],
            'KORD' => ['lat' => 41.9742, 'lon' => -87.9073],
            'KLAS' => ['lat' => 36.0840, 'lon' => -115.1537],
            'KDEN' => ['lat' => 39.8561, 'lon' => -104.6737],
            // Add more airports as needed
        ];

        return $airportCoords[$icaoCode] ?? null;
    }

    /**
     * Make HTTP API request with error handling
     */
    private function makeAPIRequest($url, $method = 'GET', $headers = [])
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => array_merge([
                'User-Agent: Flight-Control-System/1.0',
                'Accept: application/json'
            ], $headers)
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        if ($error) {
            throw new Exception("API request failed: {$error}");
        }

        if ($httpCode !== 200) {
            throw new Exception("API request failed with HTTP {$httpCode}");
        }

        return $response;
    }

    /**
     * Store weather data in database
     */
    private function storeWeatherData($icaoCode, $weatherData)
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO metar_reports (
                    station_id, observation_time, wind_direction, wind_speed,
                    wind_gust, visibility, temperature, dewpoint,
                    altimeter_setting, weather_conditions, sky_conditions,
                    raw_text
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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
                    raw_text = EXCLUDED.raw_text
            ");

            $stmt->execute([
                $weatherData['station_id'],
                $weatherData['observation_time'],
                $weatherData['wind_direction'] ?? null,
                $weatherData['wind_speed'] ?? null,
                $weatherData['wind_gust'] ?? null,
                $weatherData['visibility'] ?? null,
                $weatherData['temperature'] ?? null,
                $weatherData['dewpoint'] ?? null,
                $weatherData['altimeter_setting'] ?? null,
                $weatherData['weather_conditions'] ?? null,
                json_encode($weatherData['sky_conditions'] ?? null),
                $weatherData['raw_text'] ?? null
            ]);

            $this->logger->info("Weather data stored", ['station' => $icaoCode]);

        } catch (Exception $e) {
            $this->logger->error("Failed to store weather data", ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get weather forecast for route
     */
    public function getRouteWeatherForecast($waypoints, $altitude = null)
    {
        $forecast = [];

        foreach ($waypoints as $waypoint) {
            $weather = $this->getAirportWeather($waypoint['icao'] ?? $waypoint['ident']);
            if ($weather) {
                $forecast[] = [
                    'waypoint' => $waypoint,
                    'weather' => $weather,
                    'altitude' => $altitude
                ];
            }
        }

        return $forecast;
    }

    /**
     * Check weather conditions for flight
     */
    public function checkFlightWeatherConditions($departureIcao, $arrivalIcao, $cruisingAltitude = null)
    {
        $departureWeather = $this->getAirportWeather($departureIcao);
        $arrivalWeather = $this->getAirportWeather($arrivalIcao);

        $issues = [];

        // Check departure weather
        if ($departureWeather) {
            $depIssues = $this->analyzeWeatherConditions($departureWeather, 'departure');
            $issues = array_merge($issues, $depIssues);
        }

        // Check arrival weather
        if ($arrivalWeather) {
            $arrIssues = $this->analyzeWeatherConditions($arrivalWeather, 'arrival');
            $issues = array_merge($issues, $arrIssues);
        }

        // Check for crosswinds
        if ($departureWeather && isset($departureWeather['wind_direction'])) {
            $crosswindIssues = $this->checkCrosswindLimitations($departureWeather, $arrivalWeather);
            $issues = array_merge($issues, $crosswindIssues);
        }

        return [
            'departure_weather' => $departureWeather,
            'arrival_weather' => $arrivalWeather,
            'issues' => $issues,
            'flight_permitted' => empty($issues)
        ];
    }

    /**
     * Analyze weather conditions for issues
     */
    private function analyzeWeatherConditions($weather, $type)
    {
        $issues = [];

        // Check visibility
        if (isset($weather['visibility']) && $weather['visibility'] < 1.0) {
            $issues[] = [
                'type' => 'low_visibility',
                'severity' => 'high',
                'location' => $type,
                'description' => "Low visibility: {$weather['visibility']} miles",
                'value' => $weather['visibility']
            ];
        }

        // Check ceiling
        if (isset($weather['sky_conditions'])) {
            $ceiling = $this->parseCeiling($weather['sky_conditions']);
            if ($ceiling && $ceiling < 500) {
                $issues[] = [
                    'type' => 'low_ceiling',
                    'severity' => 'high',
                    'location' => $type,
                    'description' => "Low ceiling: {$ceiling} feet",
                    'value' => $ceiling
                ];
            }
        }

        // Check wind speed
        if (isset($weather['wind_speed']) && $weather['wind_speed'] > 30) {
            $issues[] = [
                'type' => 'high_winds',
                'severity' => 'medium',
                'location' => $type,
                'description' => "High wind speed: {$weather['wind_speed']} knots",
                'value' => $weather['wind_speed']
            ];
        }

        return $issues;
    }

    /**
     * Check crosswind limitations
     */
    private function checkCrosswindLimitations($depWeather, $arrWeather)
    {
        $issues = [];

        // This would require runway orientation data
        // Simplified implementation
        if (isset($depWeather['wind_speed']) && $depWeather['wind_speed'] > 20) {
            $issues[] = [
                'type' => 'crosswind',
                'severity' => 'medium',
                'location' => 'departure',
                'description' => 'Potential crosswind conditions at departure',
                'wind_speed' => $depWeather['wind_speed']
            ];
        }

        return $issues;
    }

    /**
     * Parse ceiling from sky conditions
     */
    private function parseCeiling($skyConditions)
    {
        // Simplified ceiling parsing
        if (is_string($skyConditions)) {
            if (preg_match('/BKN(\d{3})/', $skyConditions, $matches)) {
                return (int)$matches[1] * 100;
            }
            if (preg_match('/OVC(\d{3})/', $skyConditions, $matches)) {
                return (int)$matches[1] * 100;
            }
        }

        return null;
    }

    /**
     * Get weather alerts for region
     */
    public function getWeatherAlerts($latitude, $longitude, $radiusKm = 100)
    {
        // This would integrate with weather alert services
        // For now, return mock data structure
        return [
            'alerts' => [],
            'warnings' => [],
            'region' => [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'radius_km' => $radiusKm
            ]
        ];
    }
}
