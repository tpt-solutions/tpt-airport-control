<?php
/**
 * Spatial Indexing and Geographic Queries API Endpoint
 *
 * Advanced spatial queries for aviation geographic data
 */

require_once '../config/database.php';
require_once '../src/Logger.php';
require_once '../src/Middleware.php';
require_once '../../integrations/spatial-indexing.php';

// Initialize components
$db = getDatabaseConnection();
$logger = new Logger($db);
$middleware = new Middleware($db, $logger);
$spatialIndex = new SpatialIndexing($db, $logger);

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Get request method and path
$method = $_SERVER['REQUEST_METHOD'];
$request = $_SERVER['REQUEST_URI'];

// Remove query string and decode
$path = parse_url($request, PHP_URL_PATH);
$path = str_replace('/backend/api/spatial', '', $path);
$pathParts = array_filter(explode('/', $path));

// Get path parameters
$resource = $pathParts[1] ?? null;
$id = $pathParts[2] ?? null;

// Get request body
$input = json_decode(file_get_contents('php://input'), true);

// Route requests
try {
    switch ($method) {
        case 'GET':
            handleGetRequest($resource, $id, $_GET, $spatialIndex);
            break;

        case 'POST':
            handlePostRequest($resource, $input, $spatialIndex, $middleware);
            break;

        case 'PUT':
            handlePutRequest($resource, $id, $input, $spatialIndex, $middleware);
            break;

        case 'DELETE':
            handleDeleteRequest($resource, $id, $spatialIndex, $middleware);
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    $logger->error('Spatial API error', ['error' => $e->getMessage()]);
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

/**
 * Handle GET requests
 */
function handleGetRequest($resource, $id, $queryParams, $spatial)
{
    switch ($resource) {
        case null:
            // Get spatial system status
            $status = $spatial->getStatus();
            echo json_encode(['success' => true, 'status' => $status]);
            break;

        case 'airports':
            if ($id) {
                // Get specific airport
                $airport = getAirportById($id);
                if ($airport) {
                    echo json_encode(['success' => true, 'airport' => $airport]);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Airport not found']);
                }
            } else {
                // Find nearest airports
                if (isset($queryParams['lat']) && isset($queryParams['lon'])) {
                    $airports = $spatial->findNearestAirports(
                        $queryParams['lat'],
                        $queryParams['lon'],
                        $queryParams['max_distance'] ?? 500,
                        $queryParams['limit'] ?? 5
                    );
                    echo json_encode(['success' => true, 'airports' => $airports]);
                } else {
                    http_response_code(400);
                    echo json_encode(['error' => 'Latitude and longitude required']);
                }
            }
            break;

        case 'aircraft':
            // Find aircraft in radius
            if (isset($queryParams['lat']) && isset($queryParams['lon'])) {
                $aircraft = $spatial->findAircraftInRadius(
                    $queryParams['lat'],
                    $queryParams['lon'],
                    $queryParams['radius'] ?? 50,
                    $queryParams['min_altitude'] ?? null,
                    $queryParams['max_altitude'] ?? null
                );
                echo json_encode(['success' => true, 'aircraft' => $aircraft]);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Latitude and longitude required']);
            }
            break;

        case 'weather':
            // Find weather in area
            if (isset($queryParams['bounds'])) {
                $bounds = json_decode($queryParams['bounds'], true);
                if ($bounds) {
                    $weather = $spatial->findWeatherInArea(
                        $bounds,
                        $queryParams['min_altitude'] ?? null,
                        $queryParams['max_altitude'] ?? null
                    );
                    echo json_encode(['success' => true, 'weather' => $weather]);
                } else {
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid bounds format']);
                }
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Bounds parameter required']);
            }
            break;

        case 'sector':
            // Get airspace sector for location
            if (isset($queryParams['lat']) && isset($queryParams['lon']) && isset($queryParams['altitude'])) {
                $sector = $spatial->getAirspaceSector(
                    $queryParams['lat'],
                    $queryParams['lon'],
                    $queryParams['altitude']
                );
                echo json_encode(['success' => true, 'sector' => $sector]);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Latitude, longitude, and altitude required']);
            }
            break;

        case 'restricted':
            // Check restricted areas
            if (isset($queryParams['lat']) && isset($queryParams['lon']) && isset($queryParams['altitude'])) {
                $restrictions = $spatial->checkRestrictedAreas(
                    $queryParams['lat'],
                    $queryParams['lon'],
                    $queryParams['altitude']
                );
                echo json_encode(['success' => true, 'restrictions' => $restrictions]);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Latitude, longitude, and altitude required']);
            }
            break;

        case 'route':
            // Calculate flight path
            if (isset($queryParams['waypoints'])) {
                $waypoints = json_decode($queryParams['waypoints'], true);
                if ($waypoints && count($waypoints) >= 2) {
                    $path = $spatial->calculateFlightPath($waypoints);
                    echo json_encode(['success' => true, 'path' => $path]);
                } else {
                    http_response_code(400);
                    echo json_encode(['error' => 'At least 2 waypoints required']);
                }
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Waypoints parameter required']);
            }
            break;

        case 'optimal-route':
            // Find optimal route
            if (isset($queryParams['origin']) && isset($queryParams['destination'])) {
                $origin = json_decode($queryParams['origin'], true);
                $destination = json_decode($queryParams['destination'], true);
                $altitude = $queryParams['altitude'] ?? 35000;

                if ($origin && $destination) {
                    $route = $spatial->findOptimalRoute($origin, $destination, $altitude);
                    echo json_encode(['success' => true, 'route' => $route]);
                } else {
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid origin or destination format']);
                }
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Origin and destination required']);
            }
            break;

        case 'density':
            // Get airspace density
            if (isset($queryParams['bounds'])) {
                $bounds = json_decode($queryParams['bounds'], true);
                if ($bounds) {
                    $density = $spatial->getAirspaceDensity(
                        $bounds,
                        $queryParams['min_altitude'] ?? null,
                        $queryParams['max_altitude'] ?? null,
                        $queryParams['time_window'] ?? 3600
                    );
                    echo json_encode(['success' => true, 'density' => $density]);
                } else {
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid bounds format']);
                }
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Bounds parameter required']);
            }
            break;

        case 'stats':
            // Get spatial statistics
            $stats = $spatial->getSpatialStats();
            echo json_encode(['success' => true, 'stats' => $stats]);
            break;

        default:
            http_response_code(404);
            echo json_encode(['error' => 'Resource not found']);
    }
}

/**
 * Handle POST requests
 */
function handlePostRequest($resource, $input, $spatial, $middleware)
{
    // Require authentication for POST operations
    $user = $middleware->authenticate();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        return;
    }

    switch ($resource) {
        case 'initialize':
            // Initialize spatial system
            $result = $spatial->initialize();
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Spatial indexing system initialized']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to initialize spatial system']);
            }
            break;

        case 'airports':
            // Add airport
            $airportId = addAirport($input);
            echo json_encode(['success' => true, 'airport_id' => $airportId]);
            break;

        case 'sectors':
            // Add airspace sector
            $sectorId = addAirspaceSector($input);
            echo json_encode(['success' => true, 'sector_id' => $sectorId]);
            break;

        case 'weather':
            // Add weather cell
            $weatherId = addWeatherCell($input);
            echo json_encode(['success' => true, 'weather_id' => $weatherId]);
            break;

        case 'restricted':
            // Add restricted area
            $areaId = addRestrictedArea($input);
            echo json_encode(['success' => true, 'area_id' => $areaId]);
            break;

        case 'path':
            // Store flight path
            $pathId = storeFlightPath($input);
            echo json_encode(['success' => true, 'path_id' => $pathId]);
            break;

        default:
            http_response_code(404);
            echo json_encode(['error' => 'Resource not found']);
    }
}

/**
 * Handle PUT requests
 */
function handlePutRequest($resource, $id, $input, $spatial, $middleware)
{
    // Require authentication
    $user = $middleware->authenticate();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        return;
    }

    switch ($resource) {
        case 'airports':
            if ($id) {
                $result = updateAirport($id, $input);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Airport ID required']);
            }
            break;

        case 'sectors':
            if ($id) {
                $result = updateAirspaceSector($id, $input);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Sector ID required']);
            }
            break;

        default:
            http_response_code(404);
            echo json_encode(['error' => 'Resource not found']);
    }
}

/**
 * Handle DELETE requests
 */
function handleDeleteRequest($resource, $id, $spatial, $middleware)
{
    // Require authentication
    $user = $middleware->authenticate();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        return;
    }

    switch ($resource) {
        case 'airports':
            if ($id) {
                $result = deleteAirport($id);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Airport ID required']);
            }
            break;

        case 'sectors':
            if ($id) {
                $result = deleteAirspaceSector($id);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Sector ID required']);
            }
            break;

        default:
            http_response_code(404);
            echo json_encode(['error' => 'Resource not found']);
    }
}

/**
 * Get airport by ID
 */
function getAirportById($id)
{
    $db = $GLOBALS['db'];
    $stmt = $db->prepare("
        SELECT
            id,
            icao_code,
            iata_code,
            name,
            city,
            country,
            elevation,
            runway_count,
            type,
            ST_AsGeoJSON(location) as location_json
        FROM airports
        WHERE id = ?
    ");

    $stmt->execute([$id]);
    $airport = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($airport) {
        $airport['location'] = json_decode($airport['location_json'], true);
        unset($airport['location_json']);
    }

    return $airport;
}

/**
 * Add airport
 */
function addAirport($airportData)
{
    $db = $GLOBALS['db'];
    $stmt = $db->prepare("
        INSERT INTO airports (
            icao_code, iata_code, name, city, country,
            elevation, location, runway_count, type
        ) VALUES (?, ?, ?, ?, ?, ?, ST_SetSRID(ST_MakePoint(?, ?), 4326)::GEOGRAPHY, ?, ?)
    ");

    $stmt->execute([
        $airportData['icao_code'],
        $airportData['iata_code'] ?? null,
        $airportData['name'],
        $airportData['city'] ?? null,
        $airportData['country'] ?? null,
        $airportData['elevation'] ?? null,
        $airportData['longitude'],
        $airportData['latitude'],
        $airportData['runway_count'] ?? 0,
        $airportData['type'] ?? 'airport'
    ]);

    return $db->lastInsertId();
}

/**
 * Add airspace sector
 */
function addAirspaceSector($sectorData)
{
    $db = $GLOBALS['db'];

    // Create polygon from coordinates
    $coordinates = $sectorData['coordinates'];
    $polygonWKT = createPolygonWKT($coordinates);

    $stmt = $db->prepare("
        INSERT INTO airspace_sectors (
            sector_id, sector_name, sector_type, lower_limit, upper_limit,
            boundary, controlling_agency, frequency, active
        ) VALUES (?, ?, ?, ?, ?, ST_GeomFromText(?, 4326)::GEOGRAPHY, ?, ?, ?)
    ");

    $stmt->execute([
        $sectorData['sector_id'],
        $sectorData['sector_name'] ?? null,
        $sectorData['sector_type'] ?? 'enroute',
        $sectorData['lower_limit'] ?? 0,
        $sectorData['upper_limit'] ?? 60000,
        $polygonWKT,
        $sectorData['controlling_agency'] ?? null,
        $sectorData['frequency'] ?? null,
        $sectorData['active'] ?? true
    ]);

    return $db->lastInsertId();
}

/**
 * Add weather cell
 */
function addWeatherCell($weatherData)
{
    $db = $GLOBALS['db'];

    // Create polygon from coordinates
    $coordinates = $weatherData['coordinates'];
    $polygonWKT = createPolygonWKT($coordinates);

    $stmt = $db->prepare("
        INSERT INTO weather_cells (
            cell_id, cell_type, severity, geometry,
            altitude_min, altitude_max, valid_from, valid_to
        ) VALUES (?, ?, ?, ST_GeomFromText(?, 4326)::GEOGRAPHY, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $weatherData['cell_id'],
        $weatherData['cell_type'],
        $weatherData['severity'],
        $polygonWKT,
        $weatherData['altitude_min'] ?? 0,
        $weatherData['altitude_max'] ?? 60000,
        $weatherData['valid_from'],
        $weatherData['valid_to']
    ]);

    return $db->lastInsertId();
}

/**
 * Add restricted area
 */
function addRestrictedArea($areaData)
{
    $db = $GLOBALS['db'];

    // Create polygon from coordinates
    $coordinates = $areaData['coordinates'];
    $polygonWKT = createPolygonWKT($coordinates);

    $stmt = $db->prepare("
        INSERT INTO restricted_areas (
            area_id, area_name, restriction_type, lower_limit, upper_limit,
            boundary, active
        ) VALUES (?, ?, ?, ?, ?, ST_GeomFromText(?, 4326)::GEOGRAPHY, ?)
    ");

    $stmt->execute([
        $areaData['area_id'],
        $areaData['area_name'] ?? null,
        $areaData['restriction_type'],
        $areaData['lower_limit'] ?? 0,
        $areaData['upper_limit'] ?? 60000,
        $polygonWKT,
        $areaData['active'] ?? true
    ]);

    return $db->lastInsertId();
}

/**
 * Store flight path
 */
function storeFlightPath($pathData)
{
    $db = $GLOBALS['db'];

    // Create LINESTRING from waypoints
    $waypoints = $pathData['waypoints'];
    $linestringWKT = createLinestringWKT($waypoints);

    $stmt = $db->prepare("
        INSERT INTO flight_paths (
            flight_id, icao24, callsign, path_geometry,
            altitude_profile, speed_profile, start_time, end_time, distance
        ) VALUES (?, ?, ?, ST_GeomFromText(?, 4326)::GEOGRAPHY, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $pathData['flight_id'] ?? null,
        $pathData['icao24'] ?? null,
        $pathData['callsign'] ?? null,
        $linestringWKT,
        json_encode($pathData['altitude_profile'] ?? []),
        json_encode($pathData['speed_profile'] ?? []),
        $pathData['start_time'] ?? null,
        $pathData['end_time'] ?? null,
        $pathData['distance'] ?? null
    ]);

    return $db->lastInsertId();
}

/**
 * Update airport
 */
function updateAirport($id, $updateData)
{
    $db = $GLOBALS['db'];

    try {
        $stmt = $db->prepare("
            UPDATE airports
            SET name = ?, city = ?, country = ?, elevation = ?, runway_count = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $updateData['name'] ?? null,
            $updateData['city'] ?? null,
            $updateData['country'] ?? null,
            $updateData['elevation'] ?? null,
            $updateData['runway_count'] ?? null,
            $id
        ]);

        return ['success' => true, 'message' => 'Airport updated successfully'];

    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Update airspace sector
 */
function updateAirspaceSector($id, $updateData)
{
    $db = $GLOBALS['db'];

    try {
        $stmt = $db->prepare("
            UPDATE airspace_sectors
            SET sector_name = ?, active = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $updateData['sector_name'] ?? null,
            $updateData['active'] ?? true,
            $id
        ]);

        return ['success' => true, 'message' => 'Airspace sector updated successfully'];

    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Delete airport
 */
function deleteAirport($id)
{
    $db = $GLOBALS['db'];

    try {
        $stmt = $db->prepare("DELETE FROM airports WHERE id = ?");
        $stmt->execute([$id]);

        return ['success' => true, 'message' => 'Airport deleted successfully'];

    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Delete airspace sector
 */
function deleteAirspaceSector($id)
{
    $db = $GLOBALS['db'];

    try {
        $stmt = $db->prepare("DELETE FROM airspace_sectors WHERE id = ?");
        $stmt->execute([$id]);

        return ['success' => true, 'message' => 'Airspace sector deleted successfully'];

    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Create polygon WKT from coordinates
 */
function createPolygonWKT($coordinates)
{
    if (!is_array($coordinates) || empty($coordinates)) {
        throw new Exception('Invalid coordinates for polygon');
    }

    // Ensure the polygon is closed
    $first = $coordinates[0];
    $last = end($coordinates);
    if ($first[0] !== $last[0] || $first[1] !== $last[1]) {
        $coordinates[] = $first;
    }

    $points = [];
    foreach ($coordinates as $coord) {
        $points[] = "{$coord[0]} {$coord[1]}";
    }

    return "POLYGON((" . implode(",", $points) . "))";
}

/**
 * Create linestring WKT from waypoints
 */
function createLinestringWKT($waypoints)
{
    if (!is_array($waypoints) || count($waypoints) < 2) {
        throw new Exception('Invalid waypoints for linestring');
    }

    $points = [];
    foreach ($waypoints as $waypoint) {
        $points[] = "{$waypoint['longitude']} {$waypoint['latitude']}";
    }

    return "LINESTRING(" . implode(",", $points) . ")";
}
