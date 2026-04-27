<?php
/**
 * Spatial Indexing and Geographic Queries
 *
 * Advanced spatial indexing system for aviation geographic data
 * Based on PostGIS/PostgreSQL spatial capabilities
 */

class SpatialIndexing
{
    private $db;
    private $logger;
    private $spatialTables;
    private $isInitialized = false;

    public function __construct($database, $logger)
    {
        $this->db = $database;
        $this->logger = $logger;
        $this->spatialTables = $this->initializeSpatialTables();
    }

    /**
     * Initialize spatial tables configuration
     */
    private function initializeSpatialTables()
    {
        return [
            'airports' => [
                'table' => 'airports',
                'geometry_column' => 'location',
                'srid' => 4326, // WGS84
                'indexes' => ['gist', 'spgist', 'brin']
            ],
            'airspace_sectors' => [
                'table' => 'airspace_sectors',
                'geometry_column' => 'boundary',
                'srid' => 4326,
                'indexes' => ['gist', 'spgist']
            ],
            'runways' => [
                'table' => 'runways',
                'geometry_column' => 'centerline',
                'srid' => 4326,
                'indexes' => ['gist']
            ],
            'navaids' => [
                'table' => 'navaids',
                'geometry_column' => 'location',
                'srid' => 4326,
                'indexes' => ['gist']
            ],
            'aircraft_positions' => [
                'table' => 'aircraft_positions_ts',
                'geometry_column' => 'position_geom',
                'srid' => 4326,
                'indexes' => ['gist', 'brin']
            ],
            'weather_cells' => [
                'table' => 'weather_cells',
                'geometry_column' => 'geometry',
                'srid' => 4326,
                'indexes' => ['gist']
            ],
            'restricted_areas' => [
                'table' => 'restricted_areas',
                'geometry_column' => 'boundary',
                'srid' => 4326,
                'indexes' => ['gist']
            ],
            'flight_paths' => [
                'table' => 'flight_paths',
                'geometry_column' => 'path_geometry',
                'srid' => 4326,
                'indexes' => ['gist']
            ]
        ];
    }

    /**
     * Initialize spatial indexing system
     */
    public function initialize()
    {
        if ($this->isInitialized) {
            return true;
        }

        try {
            $this->logger->info("Initializing spatial indexing system");

            // Check if PostGIS is available
            if (!$this->checkPostGIS()) {
                throw new Exception("PostGIS extension is not available");
            }

            // Create spatial tables
            $this->createSpatialTables();

            // Create spatial indexes
            $this->createSpatialIndexes();

            // Create spatial functions
            $this->createSpatialFunctions();

            // Populate initial spatial data
            $this->populateInitialData();

            $this->isInitialized = true;
            $this->logger->info("Spatial indexing system initialized successfully");

            return true;

        } catch (Exception $e) {
            $this->logger->error("Failed to initialize spatial indexing system", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Check if PostGIS extension is available
     */
    private function checkPostGIS()
    {
        try {
            $stmt = $this->db->query("SELECT PostGIS_Version()");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return !empty($result);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Create spatial tables
     */
    private function createSpatialTables()
    {
        // Airports table
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS airports (
                id SERIAL PRIMARY KEY,
                icao_code VARCHAR(4) UNIQUE NOT NULL,
                iata_code VARCHAR(3),
                name VARCHAR(100) NOT NULL,
                city VARCHAR(100),
                country VARCHAR(100),
                elevation INTEGER,
                location GEOGRAPHY(POINT, 4326),
                runway_count INTEGER DEFAULT 0,
                type VARCHAR(20) DEFAULT 'airport',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Airspace sectors
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS airspace_sectors (
                id SERIAL PRIMARY KEY,
                sector_id VARCHAR(20) UNIQUE NOT NULL,
                sector_name VARCHAR(100),
                sector_type VARCHAR(20), -- terminal, enroute, oceanic
                lower_limit INTEGER,
                upper_limit INTEGER,
                boundary GEOGRAPHY(POLYGON, 4326),
                controlling_agency VARCHAR(100),
                frequency DECIMAL(6,3),
                active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Runways
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS runways (
                id SERIAL PRIMARY KEY,
                airport_id INTEGER REFERENCES airports(id),
                runway_number VARCHAR(10) NOT NULL,
                length INTEGER,
                width INTEGER,
                surface_type VARCHAR(20),
                centerline GEOGRAPHY(LINESTRING, 4326),
                threshold1 GEOGRAPHY(POINT, 4326),
                threshold2 GEOGRAPHY(POINT, 4326),
                active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Navigation aids
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS navaids (
                id SERIAL PRIMARY KEY,
                identifier VARCHAR(10) UNIQUE NOT NULL,
                name VARCHAR(100),
                type VARCHAR(20), -- VOR, DME, NDB, ILS
                frequency DECIMAL(8,3),
                location GEOGRAPHY(POINT, 4326),
                elevation INTEGER,
                range INTEGER,
                active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Weather cells
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS weather_cells (
                id SERIAL PRIMARY KEY,
                cell_id VARCHAR(20) UNIQUE NOT NULL,
                cell_type VARCHAR(20), -- convective, turbulence, icing
                severity VARCHAR(10), -- light, moderate, severe
                geometry GEOGRAPHY(POLYGON, 4326),
                altitude_min INTEGER,
                altitude_max INTEGER,
                valid_from TIMESTAMP,
                valid_to TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Restricted areas
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS restricted_areas (
                id SERIAL PRIMARY KEY,
                area_id VARCHAR(20) UNIQUE NOT NULL,
                area_name VARCHAR(100),
                restriction_type VARCHAR(20), -- prohibited, restricted, danger
                lower_limit INTEGER,
                upper_limit INTEGER,
                boundary GEOGRAPHY(POLYGON, 4326),
                active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Flight paths
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS flight_paths (
                id SERIAL PRIMARY KEY,
                flight_id INTEGER REFERENCES flights(id),
                icao24 VARCHAR(6),
                callsign VARCHAR(8),
                path_geometry GEOGRAPHY(LINESTRING, 4326),
                altitude_profile TEXT, -- JSON array of altitudes
                speed_profile TEXT, -- JSON array of speeds
                start_time TIMESTAMP,
                end_time TIMESTAMP,
                distance DECIMAL(8,2),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $this->logger->info("Created spatial tables");
    }

    /**
     * Create spatial indexes
     */
    private function createSpatialIndexes()
    {
        $indexes = [
            'CREATE INDEX IF NOT EXISTS idx_airports_location ON airports USING GIST (location)',
            'CREATE INDEX IF NOT EXISTS idx_airports_location_spgist ON airports USING SPGIST (location)',
            'CREATE INDEX IF NOT EXISTS idx_airspace_sectors_boundary ON airspace_sectors USING GIST (boundary)',
            'CREATE INDEX IF NOT EXISTS idx_runways_centerline ON runways USING GIST (centerline)',
            'CREATE INDEX IF NOT EXISTS idx_navaids_location ON navaids USING GIST (location)',
            'CREATE INDEX IF NOT EXISTS idx_weather_cells_geometry ON weather_cells USING GIST (geometry)',
            'CREATE INDEX IF NOT EXISTS idx_restricted_areas_boundary ON restricted_areas USING GIST (boundary)',
            'CREATE INDEX IF NOT EXISTS idx_flight_paths_geometry ON flight_paths USING GIST (path_geometry)'
        ];

        // Add geometry column to aircraft positions if it doesn't exist
        $this->db->exec("
            ALTER TABLE aircraft_positions_ts
            ADD COLUMN IF NOT EXISTS position_geom GEOGRAPHY(POINT, 4326)
        ");

        // Create index for aircraft positions
        $indexes[] = 'CREATE INDEX IF NOT EXISTS idx_aircraft_positions_geom ON aircraft_positions_ts USING GIST (position_geom)';
        $indexes[] = 'CREATE INDEX IF NOT EXISTS idx_aircraft_positions_geom_brin ON aircraft_positions_ts USING BRIN (time)';

        foreach ($indexes as $indexSql) {
            try {
                $this->db->exec($indexSql);
            } catch (Exception $e) {
                $this->logger->info("Index may already exist or PostGIS not fully available");
            }
        }

        $this->logger->info("Created spatial indexes");
    }

    /**
     * Create spatial functions
     */
    private function createSpatialFunctions()
    {
        // Function to update aircraft position geometry
        $this->db->exec("
            CREATE OR REPLACE FUNCTION update_aircraft_position_geom()
            RETURNS TRIGGER AS $$
            BEGIN
                NEW.position_geom = ST_SetSRID(ST_MakePoint(NEW.longitude, NEW.latitude), 4326)::GEOGRAPHY;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ");

        // Trigger to automatically update geometry
        $this->db->exec("
            DROP TRIGGER IF EXISTS trigger_update_aircraft_position_geom ON aircraft_positions_ts;
            CREATE TRIGGER trigger_update_aircraft_position_geom
                BEFORE INSERT OR UPDATE ON aircraft_positions_ts
                FOR EACH ROW EXECUTE FUNCTION update_aircraft_position_geom();
        ");

        // Function to calculate distance between two points
        $this->db->exec("
            CREATE OR REPLACE FUNCTION calculate_distance(
                lat1 DECIMAL, lon1 DECIMAL, lat2 DECIMAL, lon2 DECIMAL
            )
            RETURNS DECIMAL AS $$
            BEGIN
                RETURN ST_Distance(
                    ST_SetSRID(ST_MakePoint(lon1, lat1), 4326)::GEOGRAPHY,
                    ST_SetSRID(ST_MakePoint(lon2, lat2), 4326)::GEOGRAPHY
                ) / 1000; -- Convert to kilometers
            END;
            $$ LANGUAGE plpgsql;
        ");

        // Function to find nearest airport
        $this->db->exec("
            CREATE OR REPLACE FUNCTION find_nearest_airport(
                lat DECIMAL, lon DECIMAL, max_distance INTEGER DEFAULT 500
            )
            RETURNS TABLE (
                airport_id INTEGER,
                icao_code VARCHAR,
                name VARCHAR,
                distance DECIMAL
            ) AS $$
            BEGIN
                RETURN QUERY
                SELECT
                    a.id,
                    a.icao_code,
                    a.name,
                    ST_Distance(a.location, ST_SetSRID(ST_MakePoint(lon, lat), 4326)::GEOGRAPHY) / 1000 as distance
                FROM airports a
                WHERE ST_DWithin(
                    a.location,
                    ST_SetSRID(ST_MakePoint(lon, lat), 4326)::GEOGRAPHY,
                    max_distance * 1000
                )
                ORDER BY distance ASC
                LIMIT 5;
            END;
            $$ LANGUAGE plpgsql;
        ");

        // Function to check if point is in airspace sector
        $this->db->exec("
            CREATE OR REPLACE FUNCTION get_airspace_sector(
                lat DECIMAL, lon DECIMAL, altitude INTEGER
            )
            RETURNS TABLE (
                sector_id VARCHAR,
                sector_name VARCHAR,
                sector_type VARCHAR
            ) AS $$
            BEGIN
                RETURN QUERY
                SELECT
                    s.sector_id,
                    s.sector_name,
                    s.sector_type
                FROM airspace_sectors s
                WHERE ST_Contains(s.boundary, ST_SetSRID(ST_MakePoint(lon, lat), 4326)::GEOGRAPHY)
                AND altitude >= s.lower_limit
                AND altitude <= s.upper_limit
                AND s.active = TRUE;
            END;
            $$ LANGUAGE plpgsql;
        ");

        $this->logger->info("Created spatial functions");
    }

    /**
     * Populate initial spatial data
     */
    private function populateInitialData()
    {
        // Insert major airports (sample data)
        $airports = [
            ['KJFK', 'JFK', 'John F. Kennedy International Airport', 'New York', 'USA', 13, -73.7781, 40.6413],
            ['KLAX', 'LAX', 'Los Angeles International Airport', 'Los Angeles', 'USA', 125, -118.4085, 33.9425],
            ['KORD', 'ORD', 'O\'Hare International Airport', 'Chicago', 'USA', 672, -87.9048, 41.9786],
            ['KDEN', 'DEN', 'Denver International Airport', 'Denver', 'USA', 5431, -104.6737, 39.8617],
            ['EGLL', 'LHR', 'London Heathrow Airport', 'London', 'UK', 83, -0.4543, 51.4775],
            ['LFPG', 'CDG', 'Charles de Gaulle Airport', 'Paris', 'France', 293, 2.5478, 49.0097]
        ];

        foreach ($airports as $airport) {
            $this->insertAirport($airport);
        }

        // Insert sample airspace sectors
        $this->insertSampleAirspaceSectors();

        $this->logger->info("Populated initial spatial data");
    }

    /**
     * Insert airport data
     */
    private function insertAirport($airportData)
    {
        $stmt = $this->db->prepare("
            INSERT INTO airports (icao_code, iata_code, name, city, country, elevation, location)
            VALUES (?, ?, ?, ?, ?, ?, ST_SetSRID(ST_MakePoint(?, ?), 4326)::GEOGRAPHY)
            ON CONFLICT (icao_code) DO NOTHING
        ");

        $stmt->execute($airportData);
    }

    /**
     * Insert sample airspace sectors
     */
    private function insertSampleAirspaceSectors()
    {
        // Sample airspace sectors (simplified polygons)
        $sectors = [
            ['NE_SECTOR', 'Northeast Sector', 'enroute', 18000, 45000,
             'POLYGON((-80 35, -70 35, -70 45, -80 45, -80 35))'],
            ['SW_SECTOR', 'Southwest Sector', 'enroute', 18000, 45000,
             'POLYGON((-120 30, -110 30, -110 40, -120 40, -120 30))']
        ];

        foreach ($sectors as $sector) {
            $this->insertAirspaceSector($sector);
        }
    }

    /**
     * Insert airspace sector
     */
    private function insertAirspaceSector($sectorData)
    {
        $stmt = $this->db->prepare("
            INSERT INTO airspace_sectors (sector_id, sector_name, sector_type, lower_limit, upper_limit, boundary)
            VALUES (?, ?, ?, ?, ?, ST_GeomFromText(?, 4326)::GEOGRAPHY)
            ON CONFLICT (sector_id) DO NOTHING
        ");

        $stmt->execute($sectorData);
    }

    /**
     * Find nearest airports to a location
     */
    public function findNearestAirports($latitude, $longitude, $maxDistance = 500, $limit = 5)
    {
        $stmt = $this->db->prepare("
            SELECT * FROM find_nearest_airport(?, ?, ?)
            ORDER BY distance ASC
            LIMIT ?
        ");

        $stmt->execute([$latitude, $longitude, $maxDistance, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get airspace sector for a location and altitude
     */
    public function getAirspaceSector($latitude, $longitude, $altitude)
    {
        $stmt = $this->db->prepare("SELECT * FROM get_airspace_sector(?, ?, ?)");
        $stmt->execute([$latitude, $longitude, $altitude]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Find aircraft within radius of a point
     */
    public function findAircraftInRadius($latitude, $longitude, $radiusKm, $altitudeMin = null, $altitudeMax = null)
    {
        $params = [$longitude, $latitude, $radiusKm * 1000]; // Convert to meters
        $whereClause = "ST_DWithin(position_geom, ST_SetSRID(ST_MakePoint(?, ?), 4326)::GEOGRAPHY, ?)";

        if ($altitudeMin !== null) {
            $whereClause .= " AND altitude >= ?";
            $params[] = $altitudeMin;
        }

        if ($altitudeMax !== null) {
            $whereClause .= " AND altitude <= ?";
            $params[] = $altitudeMax;
        }

        $sql = "
            SELECT
                time,
                icao24,
                callsign,
                latitude,
                longitude,
                altitude,
                speed,
                heading,
                ST_Distance(position_geom, ST_SetSRID(ST_MakePoint(?, ?), 4326)::GEOGRAPHY) / 1000 as distance_km
            FROM aircraft_positions_ts
            WHERE {$whereClause}
            AND time >= NOW() - INTERVAL '5 minutes'
            ORDER BY time DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Find weather cells affecting an area
     */
    public function findWeatherInArea($bounds, $altitudeMin = null, $altitudeMax = null)
    {
        $params = [];
        $whereClause = "ST_Intersects(geometry, ST_MakeEnvelope(?, ?, ?, ?, 4326)::GEOGRAPHY)";

        $params[] = $bounds['west'];
        $params[] = $bounds['south'];
        $params[] = $bounds['east'];
        $params[] = $bounds['north'];

        if ($altitudeMin !== null) {
            $whereClause .= " AND altitude_max >= ?";
            $params[] = $altitudeMin;
        }

        if ($altitudeMax !== null) {
            $whereClause .= " AND altitude_min <= ?";
            $params[] = $altitudeMax;
        }

        $whereClause .= " AND valid_from <= NOW() AND valid_to >= NOW()";

        $sql = "
            SELECT
                cell_id,
                cell_type,
                severity,
                altitude_min,
                altitude_max,
                valid_from,
                valid_to,
                ST_AsGeoJSON(geometry) as geometry_json
            FROM weather_cells
            WHERE {$whereClause}
            ORDER BY severity DESC, valid_from ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Parse GeoJSON
        foreach ($results as &$result) {
            $result['geometry'] = json_decode($result['geometry_json'], true);
            unset($result['geometry_json']);
        }

        return $results;
    }

    /**
     * Check if location is in restricted area
     */
    public function checkRestrictedAreas($latitude, $longitude, $altitude)
    {
        $stmt = $this->db->prepare("
            SELECT
                area_id,
                area_name,
                restriction_type,
                lower_limit,
                upper_limit,
                ST_Distance(boundary, ST_SetSRID(ST_MakePoint(?, ?), 4326)::GEOGRAPHY) / 1000 as distance_km
            FROM restricted_areas
            WHERE ST_Contains(boundary, ST_SetSRID(ST_MakePoint(?, ?), 4326)::GEOGRAPHY)
            AND ? >= lower_limit
            AND ? <= upper_limit
            AND active = TRUE
        ");

        $stmt->execute([$longitude, $latitude, $longitude, $latitude, $altitude, $altitude]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Calculate flight path distance and geometry
     */
    public function calculateFlightPath($waypoints)
    {
        if (count($waypoints) < 2) {
            return null;
        }

        // Create LINESTRING from waypoints
        $points = [];
        foreach ($waypoints as $waypoint) {
            $points[] = "{$waypoint['longitude']} {$waypoint['latitude']}";
        }

        $linestring = "LINESTRING(" . implode(",", $points) . ")";

        $stmt = $this->db->prepare("
            SELECT
                ST_Length(ST_GeomFromText(?, 4326)::GEOGRAPHY) / 1000 as distance_km,
                ST_AsGeoJSON(ST_GeomFromText(?, 4326)) as geometry_json
        ");

        $stmt->execute([$linestring, $linestring]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            $result['geometry'] = json_decode($result['geometry_json'], true);
            unset($result['geometry_json']);
        }

        return $result;
    }

    /**
     * Find optimal route avoiding weather and restricted areas
     */
    public function findOptimalRoute($origin, $destination, $altitude, $constraints = [])
    {
        // This would implement A* or similar pathfinding algorithm
        // considering weather cells, restricted areas, and airspace sectors

        $originGeom = "ST_SetSRID(ST_MakePoint({$origin['longitude']}, {$origin['latitude']}), 4326)";
        $destGeom = "ST_SetSRID(ST_MakePoint({$destination['longitude']}, {$destination['latitude']}), 4326)";

        // Find direct route first
        $stmt = $this->db->prepare("
            SELECT
                ST_AsGeoJSON(ST_MakeLine(ARRAY[{$originGeom}, {$destGeom}])) as direct_path,
                ST_Distance({$originGeom}::GEOGRAPHY, {$destGeom}::GEOGRAPHY) / 1000 as direct_distance
        ");

        $stmt->execute();
        $directRoute = $stmt->fetch(PDO::FETCH_ASSOC);

        // Check for obstacles along the route
        $obstacles = $this->checkRouteObstacles($origin, $destination, $altitude);

        if (empty($obstacles)) {
            // Direct route is clear
            return [
                'route_type' => 'direct',
                'distance' => $directRoute['direct_distance'],
                'path' => json_decode($directRoute['direct_path'], true),
                'obstacles' => []
            ];
        }

        // Calculate alternative route avoiding obstacles
        $alternativeRoute = $this->calculateAlternativeRoute($origin, $destination, $altitude, $obstacles);

        return $alternativeRoute;
    }

    /**
     * Check for obstacles along a route
     */
    private function checkRouteObstacles($origin, $destination, $altitude)
    {
        $obstacles = [];

        // Check weather cells
        $weatherObstacles = $this->checkWeatherObstacles($origin, $destination, $altitude);
        $obstacles = array_merge($obstacles, $weatherObstacles);

        // Check restricted areas
        $restrictedObstacles = $this->checkRestrictedObstacles($origin, $destination, $altitude);
        $obstacles = array_merge($obstacles, $restrictedObstacles);

        return $obstacles;
    }

    /**
     * Check weather obstacles along route
     */
    private function checkWeatherObstacles($origin, $destination, $altitude)
    {
        $stmt = $this->db->prepare("
            SELECT
                cell_id,
                cell_type,
                severity,
                ST_AsGeoJSON(geometry) as geometry_json
            FROM weather_cells
            WHERE ST_Intersects(
                geometry,
                ST_MakeLine(
                    ST_SetSRID(ST_MakePoint(?, ?), 4326),
                    ST_SetSRID(ST_MakePoint(?, ?), 4326)
                )::GEOGRAPHY
            )
            AND ? >= altitude_min
            AND ? <= altitude_max
            AND valid_from <= NOW()
            AND valid_to >= NOW()
            AND severity IN ('moderate', 'severe')
        ");

        $stmt->execute([
            $origin['longitude'], $origin['latitude'],
            $destination['longitude'], $destination['latitude'],
            $altitude, $altitude
        ]);

        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($results as &$result) {
            $result['geometry'] = json_decode($result['geometry_json'], true);
            unset($result['geometry_json']);
        }

        return $results;
    }

    /**
     * Check restricted area obstacles along route
     */
    private function checkRestrictedObstacles($origin, $destination, $altitude)
    {
        $stmt = $this->db->prepare("
            SELECT
                area_id,
                area_name,
                restriction_type,
                ST_AsGeoJSON(boundary) as geometry_json
            FROM restricted_areas
            WHERE ST_Intersects(
                boundary,
                ST_MakeLine(
                    ST_SetSRID(ST_MakePoint(?, ?), 4326),
                    ST_SetSRID(ST_MakePoint(?, ?), 4326)
                )::GEOGRAPHY
            )
            AND ? >= lower_limit
            AND ? <= upper_limit
            AND active = TRUE
        ");

        $stmt->execute([
            $origin['longitude'], $origin['latitude'],
            $destination['longitude'], $destination['latitude'],
            $altitude, $altitude
        ]);

        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($results as &$result) {
            $result['geometry'] = json_decode($result['geometry_json'], true);
            unset($result['geometry_json']);
        }

        return $results;
    }

    /**
     * Calculate alternative route avoiding obstacles
     */
    private function calculateAlternativeRoute($origin, $destination, $altitude, $obstacles)
    {
        // Simplified route calculation - in practice, this would use
        // more sophisticated pathfinding algorithms

        // For now, return a simple offset route
        $midpoint = [
            'latitude' => ($origin['latitude'] + $destination['latitude']) / 2,
            'longitude' => ($origin['longitude'] + $destination['longitude']) / 2
        ];

        // Offset the midpoint to avoid obstacles
        $offsetLat = 0.5; // degrees
        $offsetLon = 0.5; // degrees

        $alternativeWaypoints = [
            $origin,
            ['latitude' => $midpoint['latitude'] + $offsetLat, 'longitude' => $midpoint['longitude'] + $offsetLon],
            $destination
        ];

        $pathData = $this->calculateFlightPath($alternativeWaypoints);

        return [
            'route_type' => 'alternative',
            'distance' => $pathData['distance_km'],
            'path' => $pathData['geometry'],
            'obstacles' => $obstacles,
            'waypoints' => $alternativeWaypoints
        ];
    }

    /**
     * Get airspace traffic density
     */
    public function getAirspaceDensity($bounds, $altitudeMin = null, $altitudeMax = null, $timeWindow = 3600)
    {
        $params = [$bounds['west'], $bounds['south'], $bounds['east'], $bounds['north'], $timeWindow];
        $whereClause = "
            latitude >= ? AND latitude <= ? AND longitude >= ? AND longitude <= ?
            AND time >= NOW() - INTERVAL '? seconds'
        ";

        if ($altitudeMin !== null) {
            $whereClause .= " AND altitude >= ?";
            $params[] = $altitudeMin;
        }

        if ($altitudeMax !== null) {
            $whereClause .= " AND altitude <= ?";
            $params[] = $altitudeMax;
        }

        $sql = "
            SELECT
                ROUND(latitude, 2) as lat_grid,
                ROUND(longitude, 2) as lon_grid,
                COUNT(*) as aircraft_count,
                AVG(altitude) as avg_altitude,
                MIN(time) as earliest_sighting,
                MAX(time) as latest_sighting
            FROM aircraft_positions_ts
            WHERE {$whereClause}
            GROUP BY lat_grid, lon_grid
            HAVING COUNT(*) > 0
            ORDER BY aircraft_count DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get spatial statistics
     */
    public function getSpatialStats()
    {
        $stats = [];

        // Table row counts
        $tables = ['airports', 'airspace_sectors', 'runways', 'navaids', 'weather_cells', 'restricted_areas'];
        foreach ($tables as $table) {
            $stmt = $this->db->query("SELECT COUNT(*) as count FROM {$table}");
            $stats[$table] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        }

        // Aircraft position stats
        $stmt = $this->db->query("
            SELECT
                COUNT(*) as total_positions,
                COUNT(DISTINCT icao24) as unique_aircraft,
                MIN(time) as oldest_position,
                MAX(time) as newest_position
            FROM aircraft_positions_ts
            WHERE time >= NOW() - INTERVAL '24 hours'
        ");
        $stats['aircraft_positions'] = $stmt->fetch(PDO::FETCH_ASSOC);

        return $stats;
    }

    /**
     * Get system status
     */
    public function getStatus()
    {
        return [
            'initialized' => $this->isInitialized,
            'postgis_available' => $this->checkPostGIS(),
            'spatial_tables' => array_keys($this->spatialTables),
            'statistics' => $this->getSpatialStats(),
            'last_updated' => date('Y-m-d H:i:s')
        ];
    }
}
