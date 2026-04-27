<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Middleware.php';

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $request = $_SERVER['REQUEST_URI'];

    // Remove query string and decode
    $path = parse_url($request, PHP_URL_PATH);
    $path = str_replace('/api/weather', '', $path);

    // Get weather station ID from path if present
    $stationId = null;
    if (!empty($path) && $path !== '/') {
        $stationId = trim($path, '/');
    }

    switch ($method) {
        case 'GET':
            if ($stationId) {
                getWeatherStation($pdo, $stationId);
            } else {
                getWeatherData($pdo);
            }
            break;

        case 'POST':
            if (isset($_GET['action'])) {
                $action = $_GET['action'];
                switch ($action) {
                    case 'alert':
                        createWeatherAlert($pdo);
                        break;
                    case 'forecast':
                        getWeatherForecast($pdo);
                        break;
                    case 'update':
                        updateWeatherData($pdo);
                        break;
                    default:
                        http_response_code(400);
                        echo json_encode(['error' => 'Invalid action']);
                        break;
                }
            } else {
                createWeatherStation($pdo);
            }
            break;

        case 'PUT':
            if ($stationId) {
                updateWeatherStation($pdo, $stationId);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Weather station ID required for update']);
            }
            break;

        case 'DELETE':
            if ($stationId) {
                deleteWeatherStation($pdo, $stationId);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Weather station ID required for deletion']);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error', 'message' => $e->getMessage()]);
}

function getWeatherData($pdo) {
    // Check permissions - operations staff can view weather data
    if (!Auth::hasPermission('read', 'flights')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    $station = $_GET['station'] ?? null;
    $type = $_GET['type'] ?? null; // current, forecast, historical
    $limit = (int)($_GET['limit'] ?? 50);

    if ($type === 'current') {
        getCurrentWeather($pdo, $station);
    } elseif ($type === 'forecast') {
        getWeatherForecast($pdo, $station);
    } elseif ($type === 'historical') {
        getHistoricalWeather($pdo, $station, $limit);
    } else {
        getAllWeatherStations($pdo);
    }
}

function getCurrentWeather($pdo, $stationCode = null) {
    $where = [];
    $params = [];

    if ($stationCode) {
        $where[] = "ws.station_code = ?";
        $params[] = $stationCode;
    }

    $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

    // Get current weather data from all stations
    $stmt = $pdo->prepare("
        SELECT
            ws.*,
            wd.temperature_c,
            wd.dewpoint_c,
            wd.humidity_percent,
            wd.wind_speed_kts,
            wd.wind_direction_deg,
            wd.wind_gust_kts,
            wd.visibility_nm,
            wd.altimeter_mb,
            wd.sky_conditions,
            wd.weather_phenomena,
            wd.remarks,
            wd.observation_time,
            wd.next_update_time
        FROM weather_stations ws
        LEFT JOIN weather_data wd ON ws.id = wd.station_id
        AND wd.observation_time = (
            SELECT MAX(observation_time)
            FROM weather_data wd2
            WHERE wd2.station_id = ws.id
        )
        {$whereClause}
        ORDER BY ws.station_code ASC
    ");
    $stmt->execute($params);
    $weatherData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['weather_data' => $weatherData]);
}

function getWeatherForecast($pdo, $stationCode = null) {
    $where = [];
    $params = [];

    if ($stationCode) {
        $where[] = "ws.station_code = ?";
        $params[] = $stationCode;
    }

    $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

    // Get forecast data
    $stmt = $pdo->prepare("
        SELECT
            ws.station_code,
            ws.station_name,
            wf.*
        FROM weather_stations ws
        JOIN weather_forecast wf ON ws.id = wf.station_id
        {$whereClause}
        AND wf.forecast_time > CURRENT_TIMESTAMP
        ORDER BY ws.station_code ASC, wf.forecast_time ASC
        LIMIT 200
    ");
    $stmt->execute($params);
    $forecastData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['forecast_data' => $forecastData]);
}

function getHistoricalWeather($pdo, $stationCode = null, $limit = 50) {
    $where = [];
    $params = [];

    if ($stationCode) {
        $where[] = "ws.station_code = ?";
        $params[] = $stationCode;
    }

    $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

    // Get historical weather data
    $stmt = $pdo->prepare("
        SELECT
            ws.station_code,
            ws.station_name,
            wd.*
        FROM weather_stations ws
        JOIN weather_data wd ON ws.id = wd.station_id
        {$whereClause}
        ORDER BY wd.observation_time DESC
        LIMIT ?
    ");
    $params[] = $limit;
    $stmt->execute($params);
    $historicalData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['historical_data' => $historicalData]);
}

function getAllWeatherStations($pdo) {
    $stmt = $pdo->prepare("
        SELECT
            ws.*,
            COUNT(wd.id) as data_points,
            MAX(wd.observation_time) as last_observation,
            AVG(wd.temperature_c) as avg_temperature_24h
        FROM weather_stations ws
        LEFT JOIN weather_data wd ON ws.id = wd.station_id
        AND wd.observation_time > CURRENT_TIMESTAMP - INTERVAL '24 hours'
        GROUP BY ws.id
        ORDER BY ws.station_code ASC
    ");
    $stmt->execute();
    $stations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['weather_stations' => $stations]);
}

function getWeatherStation($pdo, $stationId) {
    // Check permissions
    if (!Auth::hasPermission('read', 'flights')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    $stmt = $pdo->prepare("
        SELECT
            ws.*,
            COUNT(wd.id) as total_observations,
            MAX(wd.observation_time) as last_observation,
            MIN(wd.observation_time) as first_observation
        FROM weather_stations ws
        LEFT JOIN weather_data wd ON ws.id = wd.station_id
        WHERE ws.id = ?
        GROUP BY ws.id
    ");
    $stmt->execute([$stationId]);
    $station = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$station) {
        http_response_code(404);
        echo json_encode(['error' => 'Weather station not found']);
        return;
    }

    echo json_encode(['weather_station' => $station]);
}

function createWeatherStation($pdo) {
    // Check permissions - admin can create weather stations
    if (!Auth::hasPermission('admin', 'flights')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON data']);
        return;
    }

    // Validate required fields
    $required = ['station_code', 'station_name', 'latitude', 'longitude'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Missing required field: $field"]);
            return;
        }
    }

    // Check if station code already exists
    $stmt = $pdo->prepare("SELECT id FROM weather_stations WHERE station_code = ?");
    $stmt->execute([$data['station_code']]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['error' => 'Weather station code already exists']);
        return;
    }

    // Insert weather station
    $stmt = $pdo->prepare("
        INSERT INTO weather_stations (
            station_code, station_name, latitude, longitude,
            elevation_ft, station_type, data_source, is_active
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $data['station_code'],
        $data['station_name'],
        $data['latitude'],
        $data['longitude'],
        $data['elevation_ft'] ?? null,
        $data['station_type'] ?? 'METAR',
        $data['data_source'] ?? 'NOAA',
        $data['is_active'] ?? true
    ]);

    $stationId = $pdo->lastInsertId();

    // Log weather station creation
    require_once __DIR__ . '/../src/Logger.php';
    Logger::info('Weather station created: ' . $data['station_code'] . ' (ID: ' . $stationId . ')');

    http_response_code(201);
    echo json_encode([
        'message' => 'Weather station created successfully',
        'station_id' => $stationId
    ]);
}

function updateWeatherStation($pdo, $stationId) {
    // Check permissions
    if (!Auth::hasPermission('write', 'flights')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON data']);
        return;
    }

    // Check if station exists
    $stmt = $pdo->prepare("SELECT id FROM weather_stations WHERE id = ?");
    $stmt->execute([$stationId]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Weather station not found']);
        return;
    }

    // Build update query
    $updates = [];
    $params = [];

    $allowedFields = [
        'station_code', 'station_name', 'latitude', 'longitude',
        'elevation_ft', 'station_type', 'data_source', 'is_active'
    ];

    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $updates[] = "$field = ?";
            $params[] = $data[$field];
        }
    }

    if (empty($updates)) {
        http_response_code(400);
        echo json_encode(['error' => 'No valid fields to update']);
        return;
    }

    $params[] = $stationId;
    $stmt = $pdo->prepare("UPDATE weather_stations SET " . implode(', ', $updates) . ", updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute($params);

    Logger::info('Weather station updated: ID ' . $stationId);

    echo json_encode(['message' => 'Weather station updated successfully']);
}

function deleteWeatherStation($pdo, $stationId) {
    // Check permissions - admin only
    if (!Auth::hasPermission('admin', 'flights')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    // Check if station exists
    $stmt = $pdo->prepare("SELECT id FROM weather_stations WHERE id = ?");
    $stmt->execute([$stationId]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Weather station not found']);
        return;
    }

    $stmt = $pdo->prepare("DELETE FROM weather_stations WHERE id = ?");
    $stmt->execute([$stationId]);

    Logger::info('Weather station deleted: ID ' . $stationId);

    echo json_encode(['message' => 'Weather station deleted successfully']);
}

function createWeatherAlert($pdo) {
    // Check permissions
    if (!Auth::hasPermission('write', 'flights')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON data']);
        return;
    }

    // Validate required fields
    $required = ['alert_type', 'severity', 'message'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Missing required field: $field"]);
            return;
        }
    }

    // Get current user
    $currentUser = Auth::getCurrentUser();

    // Insert weather alert
    $stmt = $pdo->prepare("
        INSERT INTO weather_alerts (
            alert_type, severity, message, station_id, affected_area,
            start_time, end_time, issued_by, is_active
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $data['alert_type'],
        $data['severity'],
        $data['message'],
        $data['station_id'] ?? null,
        $data['affected_area'] ?? null,
        $data['start_time'] ?? date('Y-m-d H:i:s'),
        $data['end_time'] ?? null,
        $currentUser ? $currentUser['id'] : null,
        $data['is_active'] ?? true
    ]);

    $alertId = $pdo->lastInsertId();

    Logger::info('Weather alert created: ' . $data['alert_type'] . ' - ' . $data['severity'] . ' (ID: ' . $alertId . ')');

    http_response_code(201);
    echo json_encode([
        'message' => 'Weather alert created successfully',
        'alert_id' => $alertId
    ]);
}

function updateWeatherData($pdo) {
    // Check permissions
    if (!Auth::hasPermission('write', 'flights')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || !isset($data['station_code'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Station code required']);
        return;
    }

    // Find station by code
    $stmt = $pdo->prepare("SELECT id FROM weather_stations WHERE station_code = ?");
    $stmt->execute([$data['station_code']]);
    $station = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$station) {
        http_response_code(404);
        echo json_encode(['error' => 'Weather station not found']);
        return;
    }

    // Insert weather data
    $stmt = $pdo->prepare("
        INSERT INTO weather_data (
            station_id, observation_time, temperature_c, dewpoint_c,
            humidity_percent, wind_speed_kts, wind_direction_deg,
            wind_gust_kts, visibility_nm, altimeter_mb, sky_conditions,
            weather_phenomena, remarks, raw_metar
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $station['id'],
        $data['observation_time'] ?? date('Y-m-d H:i:s'),
        $data['temperature_c'] ?? null,
        $data['dewpoint_c'] ?? null,
        $data['humidity_percent'] ?? null,
        $data['wind_speed_kts'] ?? null,
        $data['wind_direction_deg'] ?? null,
        $data['wind_gust_kts'] ?? null,
        $data['visibility_nm'] ?? null,
        $data['altimeter_mb'] ?? null,
        $data['sky_conditions'] ?? null,
        $data['weather_phenomena'] ?? null,
        $data['remarks'] ?? null,
        $data['raw_metar'] ?? null
    ]);

    $dataId = $pdo->lastInsertId();

    Logger::info('Weather data updated for station: ' . $data['station_code']);

    echo json_encode([
        'message' => 'Weather data updated successfully',
        'data_id' => $dataId
    ]);
}

// Helper function to get active weather alerts
function getActiveWeatherAlerts($pdo) {
    $stmt = $pdo->prepare("
        SELECT
            wa.*,
            ws.station_code,
            ws.station_name,
            u.username as issued_by_username
        FROM weather_alerts wa
        LEFT JOIN weather_stations ws ON wa.station_id = ws.id
        LEFT JOIN users u ON wa.issued_by = u.id
        WHERE wa.is_active = true
        AND (wa.end_time IS NULL OR wa.end_time > CURRENT_TIMESTAMP)
        ORDER BY wa.severity DESC, wa.created_at DESC
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Helper function to get weather trends
function getWeatherTrends($pdo, $stationId, $hours = 24) {
    $stmt = $pdo->prepare("
        SELECT
            DATE_TRUNC('hour', observation_time) as hour,
            AVG(temperature_c) as avg_temperature,
            AVG(wind_speed_kts) as avg_wind_speed,
            AVG(visibility_nm) as avg_visibility,
            MIN(temperature_c) as min_temperature,
            MAX(temperature_c) as max_temperature,
            MAX(wind_gust_kts) as max_wind_gust
        FROM weather_data
        WHERE station_id = ?
        AND observation_time > CURRENT_TIMESTAMP - INTERVAL '{$hours} hours'
        GROUP BY DATE_TRUNC('hour', observation_time)
        ORDER BY hour ASC
    ");
    $stmt->execute([$stationId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Helper function to check weather conditions for flight operations
function checkWeatherForOperations($pdo, $stationCode = null) {
    $where = $stationCode ? "WHERE ws.station_code = ?" : "";
    $params = $stationCode ? [$stationCode] : [];

    $stmt = $pdo->prepare("
        SELECT
            ws.station_code,
            ws.station_name,
            wd.temperature_c,
            wd.wind_speed_kts,
            wd.wind_gust_kts,
            wd.visibility_nm,
            wd.sky_conditions,
            wd.weather_phenomena,
            CASE
                WHEN wd.wind_speed_kts > 30 THEN 'high_winds'
                WHEN wd.wind_gust_kts > 40 THEN 'severe_gusts'
                WHEN wd.visibility_nm < 1 THEN 'low_visibility'
                WHEN wd.temperature_c < -10 THEN 'extreme_cold'
                WHEN wd.weather_phenomena LIKE '%TS%' THEN 'thunderstorms'
                WHEN wd.weather_phenomena LIKE '%FG%' THEN 'fog'
                WHEN wd.weather_phenomena LIKE '%SN%' THEN 'snow'
                ELSE 'normal'
            END as operational_status
        FROM weather_stations ws
        LEFT JOIN weather_data wd ON ws.id = wd.station_id
        AND wd.observation_time = (
            SELECT MAX(observation_time)
            FROM weather_data wd2
            WHERE wd2.station_id = ws.id
        )
        {$where}
        ORDER BY ws.station_code ASC
    ");
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
