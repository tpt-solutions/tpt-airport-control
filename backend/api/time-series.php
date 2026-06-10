<?php
require_once __DIR__ . '/cors.php';
/**
 * Time-Series Database API Endpoint
 *
 * Manages time-series data storage and querying for aviation positional data
 */

require_once '../config/database.php';
require_once '../src/Logger.php';
require_once '../src/Middleware.php';
require_once '../../integrations/time-series-database.php';

// Initialize components
$db = getDatabaseConnection();
$logger = new Logger($db);
$middleware = new Middleware($db, $logger);
$timeSeriesDB = new TimeSeriesDatabase($db, $logger);

// Set headers
// Handle preflight OPTIONS request
// Get request method and path
$method = $_SERVER['REQUEST_METHOD'];
$request = $_SERVER['REQUEST_URI'];

// Remove query string and decode
$path = parse_url($request, PHP_URL_PATH);
$path = str_replace('/backend/api/time-series', '', $path);
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
            handleGetRequest($resource, $id, $_GET, $timeSeriesDB);
            break;

        case 'POST':
            handlePostRequest($resource, $input, $timeSeriesDB, $middleware);
            break;

        case 'PUT':
            handlePutRequest($resource, $id, $input, $timeSeriesDB, $middleware);
            break;

        case 'DELETE':
            handleDeleteRequest($resource, $id, $timeSeriesDB, $middleware);
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    $logger->error('Time-series API error', ['error' => $e->getMessage()]);
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

/**
 * Handle GET requests
 */
function handleGetRequest($resource, $id, $queryParams, $tsdb)
{
    switch ($resource) {
        case null:
            // Get database status
            $status = $tsdb->getStatus();
            echo json_encode(['success' => true, 'status' => $status]);
            break;

        case 'aircraft':
            if ($id) {
                // Get specific aircraft trajectory
                $trajectory = $tsdb->getAircraftTrajectory($id,
                    $queryParams['start_time'] ?? date('Y-m-d H:i:s', strtotime('-24 hours')),
                    $queryParams['end_time'] ?? date('Y-m-d H:i:s'),
                    $queryParams['max_points'] ?? 1000
                );
                echo json_encode(['success' => true, 'trajectory' => $trajectory]);
            } else {
                // Query aircraft positions
                handleAircraftQuery($queryParams, $tsdb);
            }
            break;

        case 'traffic':
            // Get traffic density
            $bounds = [
                'lat_min' => $queryParams['lat_min'] ?? -90,
                'lat_max' => $queryParams['lat_max'] ?? 90,
                'lon_min' => $queryParams['lon_min'] ?? -180,
                'lon_max' => $queryParams['lon_max'] ?? 180
            ];

            $density = $tsdb->getTrafficDensity($bounds,
                $queryParams['start_time'] ?? date('Y-m-d H:i:s', strtotime('-1 hour')),
                $queryParams['end_time'] ?? date('Y-m-d H:i:s'),
                $queryParams['grid_size'] ?? 0.1
            );
            echo json_encode(['success' => true, 'density' => $density]);
            break;

        case 'patterns':
            // Get flight patterns
            if (isset($queryParams['icao24'])) {
                $patterns = $tsdb->getFlightPatterns($queryParams['icao24'], $queryParams['days'] ?? 30);
                echo json_encode(['success' => true, 'patterns' => $patterns]);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'ICAO24 parameter required']);
            }
            break;

        case 'query':
            // General time-series query
            if (isset($queryParams['table'])) {
                $results = $tsdb->query(
                    $queryParams['table'],
                    $queryParams['start_time'] ?? date('Y-m-d H:i:s', strtotime('-24 hours')),
                    $queryParams['end_time'] ?? date('Y-m-d H:i:s'),
                    buildFilters($queryParams),
                    isset($queryParams['aggregation']) ? ['interval' => $queryParams['aggregation']] : null
                );
                echo json_encode(['success' => true, 'data' => $results]);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Table parameter required']);
            }
            break;

        case 'metrics':
            // Get performance metrics
            $metrics = $tsdb->getPerformanceMetrics();
            echo json_encode(['success' => true, 'metrics' => $metrics]);
            break;

        case 'optimize':
            // Trigger optimization
            $result = $tsdb->optimize();
            echo json_encode(['success' => $result, 'message' => 'Optimization completed']);
            break;

        case 'cleanup':
            // Trigger cleanup
            $result = $tsdb->cleanup();
            echo json_encode(['success' => $result, 'message' => 'Cleanup completed']);
            break;

        default:
            http_response_code(404);
            echo json_encode(['error' => 'Resource not found']);
    }
}

/**
 * Handle POST requests
 */
function handlePostRequest($resource, $input, $tsdb, $middleware)
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
            // Initialize time-series database
            $result = $tsdb->initialize();
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Time-series database initialized']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to initialize database']);
            }
            break;

        case 'insert':
            // Insert time-series data
            if (isset($input['table']) && isset($input['data'])) {
                $result = $tsdb->insert($input['table'], $input['data']);
                if ($result) {
                    echo json_encode(['success' => true, 'message' => 'Data inserted successfully']);
                } else {
                    http_response_code(500);
                    echo json_encode(['error' => 'Failed to insert data']);
                }
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Table and data required']);
            }
            break;

        case 'bulk-insert':
            // Bulk insert time-series data
            if (isset($input['table']) && isset($input['data']) && is_array($input['data'])) {
                $totalInserted = 0;
                $batchSize = $input['batch_size'] ?? 1000;

                // Process in batches
                $batches = array_chunk($input['data'], $batchSize);
                foreach ($batches as $batch) {
                    $result = $tsdb->insert($input['table'], $batch);
                    if ($result) {
                        $totalInserted += count($batch);
                    } else {
                        http_response_code(500);
                        echo json_encode(['error' => 'Failed to insert batch', 'inserted' => $totalInserted]);
                        return;
                    }
                }

                echo json_encode(['success' => true, 'inserted' => $totalInserted]);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Table and data array required']);
            }
            break;

        default:
            http_response_code(404);
            echo json_encode(['error' => 'Resource not found']);
    }
}

/**
 * Handle PUT requests
 */
function handlePutRequest($resource, $id, $input, $tsdb, $middleware)
{
    // Require authentication
    $user = $middleware->authenticate();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        return;
    }

    switch ($resource) {
        case 'optimize':
            // Trigger optimization
            $result = $tsdb->optimize();
            echo json_encode(['success' => $result, 'message' => 'Optimization completed']);
            break;

        case 'cleanup':
            // Trigger cleanup
            $result = $tsdb->cleanup();
            echo json_encode(['success' => $result, 'message' => 'Cleanup completed']);
            break;

        default:
            http_response_code(404);
            echo json_encode(['error' => 'Resource not found']);
    }
}

/**
 * Handle DELETE requests
 */
function handleDeleteRequest($resource, $id, $tsdb, $middleware)
{
    // Require authentication
    $user = $middleware->authenticate();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        return;
    }

    switch ($resource) {
        case 'data':
            // Delete time-series data (admin only)
            if (isset($input['table']) && isset($input['conditions'])) {
                $result = deleteTimeSeriesData($input['table'], $input['conditions']);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Table and conditions required']);
            }
            break;

        default:
            http_response_code(404);
            echo json_encode(['error' => 'Resource not found']);
    }
}

/**
 * Handle aircraft position queries
 */
function handleAircraftQuery($queryParams, $tsdb)
{
    $table = 'aircraft_positions_ts';
    $startTime = $queryParams['start_time'] ?? date('Y-m-d H:i:s', strtotime('-1 hour'));
    $endTime = $queryParams['end_time'] ?? date('Y-m-d H:i:s');

    $filters = [];

    // Build filters from query parameters
    if (isset($queryParams['icao24'])) {
        $filters['icao24'] = $queryParams['icao24'];
    }
    if (isset($queryParams['callsign'])) {
        $filters['callsign'] = $queryParams['callsign'];
    }
    if (isset($queryParams['data_source'])) {
        $filters['data_source'] = $queryParams['data_source'];
    }

    // Altitude range filter
    if (isset($queryParams['min_altitude']) || isset($queryParams['max_altitude'])) {
        // This would require custom query building
        $filters['altitude_range'] = [
            'min' => $queryParams['min_altitude'] ?? 0,
            'max' => $queryParams['max_altitude'] ?? 50000
        ];
    }

    // Geographic bounds filter
    if (isset($queryParams['bounds'])) {
        $bounds = json_decode($queryParams['bounds'], true);
        if ($bounds) {
            $filters['bounds'] = $bounds;
        }
    }

    $aggregation = null;
    if (isset($queryParams['aggregation'])) {
        $aggregation = ['interval' => $queryParams['aggregation']];
    }

    $results = $tsdb->query($table, $startTime, $endTime, $filters, $aggregation);

    echo json_encode(['success' => true, 'data' => $results]);
}

/**
 * Build filters from query parameters
 */
function buildFilters($queryParams)
{
    $filters = [];

    // Common filters
    $filterFields = ['icao24', 'callsign', 'data_source', 'aircraft_id', 'station_id', 'radar_station'];

    foreach ($filterFields as $field) {
        if (isset($queryParams[$field])) {
            $filters[$field] = $queryParams[$field];
        }
    }

    // Array filters (comma-separated)
    $arrayFilterFields = ['icao24_list', 'callsign_list', 'data_sources'];
    foreach ($arrayFilterFields as $field) {
        if (isset($queryParams[$field])) {
            $filters[str_replace('_list', '', $field)] = explode(',', $queryParams[$field]);
        }
    }

    return $filters;
}

/**
 * Delete time-series data
 */
function deleteTimeSeriesData($table, $conditions)
{
    $db = $GLOBALS['db'];

    try {
        $query = "DELETE FROM {$table} WHERE 1=1";
        $params = [];

        // Add conditions
        if (isset($conditions['start_time'])) {
            $query .= " AND time >= ?";
            $params[] = $conditions['start_time'];
        }

        if (isset($conditions['end_time'])) {
            $query .= " AND time <= ?";
            $params[] = $conditions['end_time'];
        }

        if (isset($conditions['icao24'])) {
            $query .= " AND icao24 = ?";
            $params[] = $conditions['icao24'];
        }

        if (isset($conditions['data_source'])) {
            $query .= " AND data_source = ?";
            $params[] = $conditions['data_source'];
        }

        // Add LIMIT for safety
        $query .= " LIMIT 10000";

        $stmt = $db->prepare($query);
        $stmt->execute($params);

        return ['success' => true, 'message' => 'Data deleted successfully', 'affected_rows' => $stmt->rowCount()];

    } catch (Exception $e) {
        return ['success' => false, 'error' => 'An internal error occurred'];
    }
}

/**
 * Get available time-series tables
 */
function getAvailableTables()
{
    return [
        'aircraft_positions_ts' => [
            'description' => 'Aircraft position data from ADS-B and other sources',
            'fields' => ['time', 'icao24', 'callsign', 'latitude', 'longitude', 'altitude', 'speed', 'heading', 'vertical_rate', 'on_ground', 'data_source', 'quality_score']
        ],
        'radar_tracks_ts' => [
            'description' => 'Radar track data',
            'fields' => ['time', 'latitude', 'longitude', 'altitude', 'reflectivity', 'velocity', 'spectrum_width', 'precipitation_type', 'intensity', 'radar_station', 'data_source', 'quality_score']
        ],
        'satellite_positions_ts' => [
            'description' => 'Satellite-based aircraft position data',
            'fields' => ['time', 'aircraft_id', 'latitude', 'longitude', 'altitude', 'speed', 'heading', 'satellite_type', 'signal_strength', 'data_source', 'quality_score']
        ],
        'weather_data_ts' => [
            'description' => 'Weather observation data',
            'fields' => ['time', 'latitude', 'longitude', 'temperature', 'wind_speed', 'wind_direction', 'visibility', 'precipitation', 'pressure', 'humidity', 'weather_conditions', 'station_id', 'data_source', 'quality_score']
        ],
        'flight_trajectories_ts' => [
            'description' => 'Complete flight trajectory data',
            'fields' => ['time', 'flight_id', 'icao24', 'callsign', 'latitude', 'longitude', 'altitude', 'speed', 'heading', 'vertical_rate', 'phase', 'data_source', 'quality_score']
        ]
    ];
}

/**
 * Get aggregation options
 */
function getAggregationOptions()
{
    return [
        '5_minutes' => '5-minute intervals',
        '1_hour' => '1-hour intervals',
        '1_day' => '1-day intervals'
    ];
}

/**
 * Get query examples
 */
function getQueryExamples()
{
    return [
        'aircraft_positions' => [
            'description' => 'Query aircraft positions for specific ICAO24',
            'example' => '/time-series/aircraft/ABC123?start_time=2024-01-01T00:00:00Z&end_time=2024-01-01T01:00:00Z'
        ],
        'traffic_density' => [
            'description' => 'Get traffic density in geographic bounds',
            'example' => '/time-series/traffic?lat_min=40.0&lat_max=41.0&lon_min=-74.0&lon_max=-73.0&start_time=2024-01-01T00:00:00Z'
        ],
        'flight_patterns' => [
            'description' => 'Get historical flight patterns',
            'example' => '/time-series/patterns?icao24=ABC123&days=30'
        ],
        'aggregated_data' => [
            'description' => 'Get aggregated data',
            'example' => '/time-series/query?table=aircraft_positions_ts&aggregation=1_hour&start_time=2024-01-01T00:00:00Z'
        ]
    ];
}
