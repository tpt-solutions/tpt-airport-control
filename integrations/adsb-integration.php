<?php
/**
 * ADS-B Data Integration Service
 *
 * Processes real-time ADS-B aircraft tracking data
 * Handles position, altitude, speed, and identification data
 */

require_once __DIR__ . '/../backend/config/database.php';
require_once __DIR__ . '/../backend/src/Logger.php';

class ADSBIntegration {
    private $pdo;
    private $apiKey;
    private $baseUrl;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        // Configuration for ADS-B data source (e.g., OpenSky Network, FAA ADS-B Exchange)
        $this->apiKey = getenv('ADSB_API_KEY') ?: '';
        $this->baseUrl = getenv('ADSB_BASE_URL') ?: 'https://opensky-network.org/api';
    }

    /**
     * Fetch real-time aircraft data from ADS-B source
     */
    public function fetchRealtimeData($bounds = null) {
        try {
            $url = $this->baseUrl . '/states/all';

            if ($bounds) {
                // lamin, lomin, lamax, lomax (latitude/longitude bounds)
                $url .= '?' . http_build_query([
                    'lamin' => $bounds['lamin'],
                    'lomin' => $bounds['lomin'],
                    'lamax' => $bounds['lamax'],
                    'lomax' => $bounds['lomax']
                ]);
            }

            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'user_agent' => 'FlightControl/1.0'
                ]
            ]);

            $response = file_get_contents($url, false, $context);

            if ($response === false) {
                Logger::error('Failed to fetch ADS-B data from API');
                return false;
            }

            $data = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                Logger::error('Invalid JSON response from ADS-B API');
                return false;
            }

            return $this->processAircraftData($data);

        } catch (Exception $e) {
            Logger::error('ADS-B data fetch error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Process aircraft state data and store in database
     */
    private function processAircraftData($data) {
        if (!isset($data['states']) || !is_array($data['states'])) {
            Logger::error('Invalid ADS-B data format');
            return false;
        }

        $processed = 0;
        $time = $data['time'] ?? time();

        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO aircraft_positions (
                    icao24, callsign, origin_country, time_position,
                    last_contact, longitude, latitude, baro_altitude,
                    on_ground, velocity, true_track, vertical_rate,
                    geo_altitude, squawk, spi, position_source,
                    recorded_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ON CONFLICT (icao24, recorded_at)
                DO UPDATE SET
                    callsign = EXCLUDED.callsign,
                    longitude = EXCLUDED.longitude,
                    latitude = EXCLUDED.latitude,
                    baro_altitude = EXCLUDED.baro_altitude,
                    on_ground = EXCLUDED.on_ground,
                    velocity = EXCLUDED.velocity,
                    true_track = EXCLUDED.true_track,
                    vertical_rate = EXCLUDED.vertical_rate,
                    last_contact = EXCLUDED.last_contact
            ");

            foreach ($data['states'] as $aircraft) {
                // ADS-B state vector format:
                // [icao24, callsign, origin_country, time_position, last_contact,
                //  longitude, latitude, baro_altitude, on_ground, velocity,
                //  true_track, vertical_rate, geo_altitude, squawk, spi, position_source]

                $stmt->execute([
                    $aircraft[0],  // icao24
                    $aircraft[1],  // callsign
                    $aircraft[2],  // origin_country
                    $aircraft[3],  // time_position
                    $aircraft[4],  // last_contact
                    $aircraft[5],  // longitude
                    $aircraft[6],  // latitude
                    $aircraft[7],  // baro_altitude
                    $aircraft[8],  // on_ground
                    $aircraft[9],  // velocity
                    $aircraft[10], // true_track
                    $aircraft[11], // vertical_rate
                    $aircraft[12], // geo_altitude
                    $aircraft[13], // squawk
                    $aircraft[14], // spi
                    $aircraft[15]  // position_source
                ]);

                $processed++;
            }

            $this->pdo->commit();
            Logger::info("Processed $processed aircraft positions from ADS-B data");
            return $processed;

        } catch (Exception $e) {
            $this->pdo->rollBack();
            Logger::error('Failed to process aircraft data: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get aircraft positions within bounds
     */
    public function getAircraftInBounds($bounds, $maxAge = 300) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM aircraft_positions
                WHERE latitude BETWEEN ? AND ?
                  AND longitude BETWEEN ? AND ?
                  AND recorded_at > NOW() - INTERVAL '{$maxAge} seconds'
                  AND longitude IS NOT NULL
                  AND latitude IS NOT NULL
                ORDER BY recorded_at DESC
            ");

            $stmt->execute([
                $bounds['lamin'],
                $bounds['lamax'],
                $bounds['lomin'],
                $bounds['lomax']
            ]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            Logger::error('Failed to get aircraft in bounds: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get flight path for specific aircraft
     */
    public function getFlightPath($icao24, $hours = 24) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT longitude, latitude, baro_altitude, velocity, recorded_at
                FROM aircraft_positions
                WHERE icao24 = ?
                  AND recorded_at > NOW() - INTERVAL '{$hours} hours'
                  AND longitude IS NOT NULL
                  AND latitude IS NOT NULL
                ORDER BY recorded_at ASC
            ");

            $stmt->execute([$icao24]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            Logger::error('Failed to get flight path: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Detect potential conflicts between aircraft
     */
    public function detectConflicts($bounds, $separationTime = 300) {
        try {
            // Get all aircraft in the area
            $aircraft = $this->getAircraftInBounds($bounds, 60); // Last minute

            $conflicts = [];
            $count = count($aircraft);

            for ($i = 0; $i < $count; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
                    $conflict = $this->checkAircraftConflict(
                        $aircraft[$i],
                        $aircraft[$j],
                        $separationTime
                    );

                    if ($conflict) {
                        $conflicts[] = $conflict;
                    }
                }
            }

            return $conflicts;

        } catch (Exception $e) {
            Logger::error('Conflict detection failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Check if two aircraft are on conflicting trajectories
     */
    private function checkAircraftConflict($aircraft1, $aircraft2, $timeWindow) {
        // Simplified conflict detection
        // In production, this would use sophisticated trajectory prediction

        $lat1 = $aircraft1['latitude'];
        $lon1 = $aircraft1['longitude'];
        $alt1 = $aircraft1['baro_altitude'] ?: $aircraft1['geo_altitude'];
        $track1 = $aircraft1['true_track'];
        $speed1 = $aircraft1['velocity'];

        $lat2 = $aircraft2['latitude'];
        $lon2 = $aircraft2['longitude'];
        $alt2 = $aircraft2['baro_altitude'] ?: $aircraft2['geo_altitude'];
        $track2 = $aircraft2['true_track'];
        $speed2 = $aircraft2['velocity'];

        // Calculate horizontal separation (simplified)
        $distance = $this->calculateDistance($lat1, $lon1, $lat2, $lon2);

        // Vertical separation
        $altDiff = abs(($alt1 ?: 0) - ($alt2 ?: 0));

        // Minimum separation standards (NM horizontal, feet vertical)
        $minHorizontalSep = 5; // 5 NM
        $minVerticalSep = 1000; // 1000 feet

        if ($distance < $minHorizontalSep && $altDiff < $minVerticalSep) {
            return [
                'aircraft1' => $aircraft1['icao24'],
                'aircraft2' => $aircraft2['icao24'],
                'distance_nm' => $distance,
                'altitude_diff_ft' => $altDiff,
                'severity' => 'high',
                'detected_at' => time()
            ];
        }

        return null;
    }

    /**
     * Calculate great circle distance between two points
     */
    private function calculateDistance($lat1, $lon1, $lat2, $lon2) {
        $earthRadius = 6371; // km

        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);

        $a = sin($latDelta/2) * sin($latDelta/2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($lonDelta/2) * sin($lonDelta/2);

        $c = 2 * atan2(sqrt($a), sqrt(1-$a));

        $distanceKm = $earthRadius * $c;
        $distanceNm = $distanceKm * 0.539957; // Convert km to nautical miles

        return $distanceNm;
    }
}

// Database table for ADS-B data
$adsbTableSQL = "
CREATE TABLE IF NOT EXISTS aircraft_positions (
    id SERIAL PRIMARY KEY,
    icao24 VARCHAR(6) NOT NULL,
    callsign VARCHAR(8),
    origin_country VARCHAR(100),
    time_position INTEGER,
    last_contact INTEGER,
    longitude DECIMAL(10,6),
    latitude DECIMAL(10,6),
    baro_altitude DECIMAL(7,1),
    on_ground BOOLEAN,
    velocity DECIMAL(5,1),
    true_track DECIMAL(5,1),
    vertical_rate DECIMAL(5,1),
    geo_altitude DECIMAL(7,1),
    squawk VARCHAR(4),
    spi BOOLEAN,
    position_source INTEGER,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(icao24, recorded_at)
);

CREATE INDEX IF NOT EXISTS idx_aircraft_positions_location ON aircraft_positions (latitude, longitude);
CREATE INDEX IF NOT EXISTS idx_aircraft_positions_icao24 ON aircraft_positions (icao24);
CREATE INDEX IF NOT EXISTS idx_aircraft_positions_time ON aircraft_positions (recorded_at);
";

// Usage example:
/*
$adsb = new ADSBIntegration($pdo);

// Fetch and process real-time data
$result = $adsb->fetchRealtimeData([
    'lamin' => 40.0,   // min latitude
    'lomin' => -75.0,  // min longitude
    'lamax' => 41.0,   // max latitude
    'lomax' => -74.0   // max longitude
]);

// Get aircraft in area
$aircraft = $adsb->getAircraftInBounds([
    'lamin' => 40.0,
    'lomin' => -75.0,
    'lamax' => 41.0,
    'lomax' => -74.0
]);

// Check for conflicts
$conflicts = $adsb->detectConflicts([
    'lamin' => 40.0,
    'lomin' => -75.0,
    'lamax' => 41.0,
    'lomax' => -74.0
]);
*/
?>
