<?php
/**
 * Data Fusion Engine API Endpoint
 *
 * Provides real-time data fusion and situational awareness
 */

require_once '../config/database.php';
require_once '../src/Logger.php';
require_once '../src/Middleware.php';
require_once '../../integrations/data-fusion-engine.php';

// Initialize components
$db = getDatabaseConnection();
$logger = new Logger($db);
$middleware = new Middleware($db, $logger);
$dataFusionEngine = new DataFusionEngine($db, $logger);

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
$path = str_replace('/backend/api/data-fusion', '', $path);
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
            handleGetRequest($resource, $id, $_GET, $dataFusionEngine);
            break;

        case 'POST':
            handlePostRequest($resource, $input, $dataFusionEngine, $middleware);
            break;

        case 'PUT':
            handlePutRequest($resource, $id, $input, $dataFusionEngine, $middleware);
            break;

        case 'DELETE':
            handleDeleteRequest($resource, $id, $dataFusionEngine, $middleware);
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    $logger->error('Data fusion API error', ['error' => $e->getMessage()]);
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

/**
 * Handle GET requests
 */
function handleGetRequest($resource, $id, $queryParams, $engine)
{
    switch ($resource) {
        case null:
            // Get latest fusion report
            $report = getLatestFusionReport($engine);
            echo json_encode(['success' => true, 'data' => $report]);
            break;

        case 'sector':
            // Get fusion data for specific sector
            if (isset($queryParams['lat_min']) && isset($queryParams['lat_max']) &&
                isset($queryParams['lon_min']) && isset($queryParams['lon_max'])) {

                $sectorBounds = [
                    'lat_min' => (float)$queryParams['lat_min'],
                    'lat_max' => (float)$queryParams['lat_max'],
                    'lon_min' => (float)$queryParams['lon_min'],
                    'lon_max' => (float)$queryParams['lon_max']
                ];

                $timeWindow = isset($queryParams['time_window']) ? (int)$queryParams['time_window'] : 300;

                $fusionData = $engine->processSectorFusion($sectorBounds, $timeWindow);
                echo json_encode(['success' => true, 'data' => $fusionData]);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Sector bounds required (lat_min, lat_max, lon_min, lon_max)']);
            }
            break;

        case 'reports':
            // Get fusion reports with pagination
            $page = isset($queryParams['page']) ? (int)$queryParams['page'] : 1;
            $limit = isset($queryParams['limit']) ? (int)$queryParams['limit'] : 50;
            $startDate = $queryParams['start_date'] ?? null;
            $endDate = $queryParams['end_date'] ?? null;

            $reports = getFusionReports($page, $limit, $startDate, $endDate);
            echo json_encode(['success' => true, 'data' => $reports]);
            break;

        case 'conflicts':
            // Get active conflicts
            $conflicts = getActiveConflicts();
            echo json_encode(['success' => true, 'data' => $conflicts]);
            break;

        case 'aircraft':
            // Get fused aircraft data
            if (isset($queryParams['icao']) || isset($queryParams['callsign'])) {
                $identifier = $queryParams['icao'] ?? $queryParams['callsign'];
                $aircraft = getFusedAircraftData($identifier);
                if ($aircraft) {
                    echo json_encode(['success' => true, 'data' => $aircraft]);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Aircraft not found']);
                }
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Aircraft ICAO or callsign required']);
            }
            break;

        case 'weather':
            // Get fused weather data
            $weather = getFusedWeatherData($queryParams);
            echo json_encode(['success' => true, 'data' => $weather]);
            break;

        case 'status':
            // Get system status
            $status = getSystemStatus();
            echo json_encode(['success' => true, 'status' => $status]);
            break;

        default:
            http_response_code(404);
            echo json_encode(['error' => 'Resource not found']);
    }
}

/**
 * Handle POST requests
 */
function handlePostRequest($resource, $input, $engine, $middleware)
{
    // Require authentication for POST operations
    $user = $middleware->authenticate();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        return;
    }

    switch ($resource) {
        case 'fusion':
            // Trigger manual data fusion
            if (isset($input['sector_bounds'])) {
                $timeWindow = $input['time_window'] ?? 300;
                $result = $engine->processSectorFusion($input['sector_bounds'], $timeWindow);
                echo json_encode(['success' => true, 'result' => $result]);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Sector bounds required']);
            }
            break;

        case 'alert':
            // Create manual alert
            $alertId = createManualAlert($input, $user);
            echo json_encode(['success' => true, 'alert_id' => $alertId]);
            break;

        default:
            http_response_code(404);
            echo json_encode(['error' => 'Resource not found']);
    }
}

/**
 * Handle PUT requests
 */
function handlePutRequest($resource, $id, $input, $engine, $middleware)
{
    // Require authentication
    $user = $middleware->authenticate();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        return;
    }

    switch ($resource) {
        case 'conflicts':
            if ($id && isset($input['action'])) {
                if ($input['action'] === 'resolve') {
                    $result = resolveConflict($id, $user);
                    echo json_encode($result);
                } else {
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid action']);
                }
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Conflict ID and action required']);
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
function handleDeleteRequest($resource, $id, $engine, $middleware)
{
    // Require authentication
    $user = $middleware->authenticate();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        return;
    }

    switch ($resource) {
        case 'reports':
            if ($id) {
                $result = deleteFusionReport($id);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Report ID required']);
            }
            break;

        default:
            http_response_code(404);
            echo json_encode(['error' => 'Resource not found']);
    }
}

/**
 * Get latest fusion report
 */
function getLatestFusionReport($engine)
{
    $db = $GLOBALS['db'];
    $stmt = $db->query("
        SELECT * FROM data_fusion_reports
        ORDER BY report_timestamp DESC
        LIMIT 1
    ");

    $report = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($report) {
        // Decode JSON fields
        $report['weather_summary'] = json_decode($report['weather_summary'], true);
        $report['aircraft_summary'] = json_decode($report['aircraft_summary'], true);
        $report['conflicts_data'] = json_decode($report['conflicts_data'], true);
    }

    return $report;
}

/**
 * Get fusion reports with pagination
 */
function getFusionReports($page, $limit, $startDate, $endDate)
{
    $db = $GLOBALS['db'];
    $offset = ($page - 1) * $limit;

    $query = "
        SELECT * FROM data_fusion_reports
        WHERE 1=1
    ";
    $params = [];

    if ($startDate) {
        $query .= " AND report_timestamp >= ?";
        $params[] = $startDate;
    }

    if ($endDate) {
        $query .= " AND report_timestamp <= ?";
        $params[] = $endDate;
    }

    $query .= " ORDER BY report_timestamp DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;

    $stmt = $db->prepare($query);
    $stmt->execute($params);

    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Decode JSON fields
    foreach ($reports as &$report) {
        $report['weather_summary'] = json_decode($report['weather_summary'], true);
        $report['aircraft_summary'] = json_decode($report['aircraft_summary'], true);
        $report['conflicts_data'] = json_decode($report['conflicts_data'], true);
    }

    return [
        'reports' => $reports,
        'page' => $page,
        'limit' => $limit,
        'total' => count($reports)
    ];
}

/**
 * Get active conflicts
 */
function getActiveConflicts()
{
    $db = $GLOBALS['db'];
    $stmt = $db->query("
        SELECT * FROM data_fusion_reports
        WHERE conflicts_count > 0
        AND report_timestamp >= NOW() - INTERVAL '5 minutes'
        ORDER BY report_timestamp DESC
        LIMIT 10
    ");

    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $conflicts = [];
    foreach ($reports as $report) {
        $conflictsData = json_decode($report['conflicts_data'], true);
        if ($conflictsData) {
            $conflicts = array_merge($conflicts, $conflictsData);
        }
    }

    return $conflicts;
}

/**
 * Get fused aircraft data
 */
function getFusedAircraftData($identifier)
{
    $db = $GLOBALS['db'];

    // Try to find aircraft by ICAO first, then callsign
    $stmt = $db->prepare("
        SELECT *,
               EXTRACT(EPOCH FROM (NOW() - recorded_at)) as age_seconds
        FROM aircraft_positions
        WHERE (icao24 = ? OR callsign = ?)
        AND recorded_at >= NOW() - INTERVAL '10 minutes'
        ORDER BY recorded_at DESC
        LIMIT 1
    ");

    $stmt->execute([$identifier, $identifier]);
    $adsbData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$adsbData) {
        return null;
    }

    // Get additional data sources
    $radarData = getRadarDataForAircraft($adsbData);
    $satelliteData = getSatelliteDataForAircraft($adsbData);
    $flightPlanData = getFlightPlanForAircraft($adsbData);

    return [
        'primary_data' => $adsbData,
        'radar_data' => $radarData,
        'satellite_data' => $satelliteData,
        'flight_plan' => $flightPlanData,
        'fusion_status' => 'active'
    ];
}

/**
 * Get radar data for specific aircraft
 */
function getRadarDataForAircraft($aircraftData)
{
    $db = $GLOBALS['db'];
    $stmt = $db->prepare("
        SELECT *,
               EXTRACT(EPOCH FROM (NOW() - recorded_at)) as age_seconds
        FROM weather_radar_data
        WHERE latitude BETWEEN ? - 0.01 AND ? + 0.01
        AND longitude BETWEEN ? - 0.01 AND ? + 0.01
        AND recorded_at >= NOW() - INTERVAL '5 minutes'
        ORDER BY recorded_at DESC
        LIMIT 5
    ");

    $stmt->execute([
        $aircraftData['latitude'], $aircraftData['latitude'],
        $aircraftData['longitude'], $aircraftData['longitude']
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get satellite data for specific aircraft
 */
function getSatelliteDataForAircraft($aircraftData)
{
    $db = $GLOBALS['db'];
    $stmt = $db->prepare("
        SELECT *,
               EXTRACT(EPOCH FROM (NOW() - received_at)) as age_seconds
        FROM satellite_messages
        WHERE aircraft_id = ?
        AND received_at >= NOW() - INTERVAL '10 minutes'
        ORDER BY received_at DESC
        LIMIT 5
    ");

    $stmt->execute([$aircraftData['icao24']]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get flight plan for aircraft
 */
function getFlightPlanForAircraft($aircraftData)
{
    $db = $GLOBALS['db'];
    $stmt = $db->prepare("
        SELECT fp.*, f.flight_number
        FROM flight_plans fp
        LEFT JOIN flights f ON fp.flight_id = f.id
        WHERE fp.aircraft_id = ?
        AND fp.status IN ('filed', 'active')
        ORDER BY fp.filed_at DESC
        LIMIT 1
    ");

    $stmt->execute([$aircraftData['icao24']]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Get fused weather data
 */
function getFusedWeatherData($queryParams)
{
    $db = $GLOBALS['db'];

    // Get latest METAR data
    $stmt = $db->query("
        SELECT * FROM metar_reports
        WHERE recorded_at >= NOW() - INTERVAL '1 hour'
        ORDER BY recorded_at DESC
        LIMIT 20
    ");
    $metarData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get active weather alerts
    $stmt = $db->query("
        SELECT * FROM weather_alerts
        WHERE active = true
        AND start_time <= NOW()
        AND (end_time IS NULL OR end_time >= NOW())
        ORDER BY issued_at DESC
        LIMIT 10
    ");
    $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get radar data
    $stmt = $db->query("
        SELECT * FROM weather_radar_data
        WHERE recorded_at >= NOW() - INTERVAL '15 minutes'
        ORDER BY recorded_at DESC
        LIMIT 50
    ");
    $radarData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'metar_reports' => $metarData,
        'weather_alerts' => $alerts,
        'radar_data' => $radarData,
        'last_updated' => date('Y-m-d H:i:s')
    ];
}

/**
 * Get system status
 */
function getSystemStatus()
{
    $db = $GLOBALS['db'];

    // Check latest fusion report
    $stmt = $db->query("
        SELECT report_timestamp, system_status, aircraft_count, conflicts_count
        FROM data_fusion_reports
        ORDER BY report_timestamp DESC
        LIMIT 1
    ");
    $latestReport = $stmt->fetch(PDO::FETCH_ASSOC);

    // Check data source health
    $health = [
        'adsb_active' => checkDataSourceHealth('aircraft_positions'),
        'radar_active' => checkDataSourceHealth('weather_radar_data'),
        'satellite_active' => checkDataSourceHealth('satellite_messages'),
        'weather_active' => checkDataSourceHealth('metar_reports')
    ];

    return [
        'overall_status' => $latestReport['system_status'] ?? 'unknown',
        'last_fusion_report' => $latestReport['report_timestamp'] ?? null,
        'active_aircraft' => $latestReport['aircraft_count'] ?? 0,
        'active_conflicts' => $latestReport['conflicts_count'] ?? 0,
        'data_sources' => $health,
        'timestamp' => date('Y-m-d H:i:s')
    ];
}

/**
 * Check data source health
 */
function checkDataSourceHealth($tableName)
{
    $db = $GLOBALS['db'];
    $stmt = $db->prepare("
        SELECT COUNT(*) as count
        FROM {$tableName}
        WHERE created_at >= NOW() - INTERVAL '5 minutes'
    ");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    return $result['count'] > 0;
}

/**
 * Create manual alert
 */
function createManualAlert($alertData, $user)
{
    $db = $GLOBALS['db'];
    $stmt = $db->prepare("
        INSERT INTO weather_alerts (
            alert_type, severity, location_lat, location_lon,
            radius_km, description, start_time, end_time, issued_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $alertData['alert_type'] ?? 'manual',
        $alertData['severity'] ?? 'moderate',
        $alertData['location_lat'] ?? null,
        $alertData['location_lon'] ?? null,
        $alertData['radius_km'] ?? 10,
        $alertData['description'],
        $alertData['start_time'] ?? date('Y-m-d H:i:s'),
        $alertData['end_time'] ?? date('Y-m-d H:i:s', strtotime('+1 hour')),
        $user['username']
    ]);

    return $db->lastInsertId();
}

/**
 * Resolve conflict
 */
function resolveConflict($conflictId, $user)
{
    $db = $GLOBALS['db'];

    try {
        // In a real implementation, this would update conflict resolution status
        // For now, we'll just log the resolution
        $stmt = $db->prepare("
            UPDATE data_fusion_reports
            SET conflicts_data = jsonb_set(
                conflicts_data,
                '{resolution}',
                jsonb_build_object(
                    'resolved_by', ?,
                    'resolved_at', ?,
                    'method', 'manual'
                )
            )
            WHERE id = ?
        ");
        $stmt->execute([$user['username'], date('Y-m-d H:i:s'), $conflictId]);

        return ['success' => true, 'message' => 'Conflict marked as resolved'];

    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Delete fusion report
 */
function deleteFusionReport($reportId)
{
    $db = $GLOBALS['db'];

    try {
        $stmt = $db->prepare("DELETE FROM data_fusion_reports WHERE id = ?");
        $stmt->execute([$reportId]);

        return ['success' => true, 'message' => 'Fusion report deleted'];

    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}
