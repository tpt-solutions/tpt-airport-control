<?php
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/../src/Logger.php';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        exit(0);
    }

    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../src/Middleware.php';
    require_once __DIR__ . '/../src/PerformanceAnalytics.php';

    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? 'kpis';

    switch ($method) {
        case 'GET':
            handleGet($action);
            break;
        case 'POST':
            handlePost($action);
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    Logger::error('Analytics API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

function handleGet($action) {
    global $pdo;

    try {
        $analytics = new PerformanceAnalytics($pdo);

        switch ($action) {
            case 'kpis':
                Middleware::authenticate();
                Middleware::checkPermission('read', 'analytics');

                $timeRange = $_GET['time_range'] ?? '1 hour';
                $kpis = $analytics->getRealtimeKPIs($timeRange);

                echo json_encode(['kpis' => $kpis]);
                break;

            case 'historical':
                Middleware::authenticate();
                Middleware::checkPermission('read', 'analytics');

                $metric = $_GET['metric'] ?? '';
                $days = (int)($_GET['days'] ?? 30);

                if (!$metric) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Metric parameter required']);
                    return;
                }

                $history = $analytics->getHistoricalPerformance($metric, $days);
                echo json_encode(['historical_data' => $history]);
                break;

            case 'reports':
                Middleware::authenticate();
                Middleware::checkPermission('read', 'analytics');

                $limit = (int)($_GET['limit'] ?? 10);
                $reportType = $_GET['type'] ?? null;

                $whereClause = "";
                $params = [];

                if ($reportType) {
                    $whereClause = "WHERE report_type = ?";
                    $params[] = $reportType;
                }

                $stmt = $pdo->prepare("
                    SELECT * FROM performance_reports
                    {$whereClause}
                    ORDER BY generated_at DESC
                    LIMIT ?
                ");
                $params[] = $limit;
                $stmt->execute($params);
                $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode(['reports' => $reports]);
                break;

            case 'stats':
                Middleware::authenticate();
                $stats = [
                    'total_flights'         => 0,
                    'active_flights'        => 0,
                    'total_passengers'      => 0,
                    'checked_in_passengers' => 0,
                    'total_bookings'        => 0,
                    'pending_maintenance'   => 0,
                    'security_alerts'       => 0,
                    'system_health'         => 'healthy',
                ];
                try {
                    $conn = \TPT\FlightControl\Config\Database::getConnection();
                    $row = $conn->query("
                        SELECT
                            (SELECT COUNT(*) FROM flights)                                      AS total_flights,
                            (SELECT COUNT(*) FROM flights WHERE status = 'active')              AS active_flights,
                            (SELECT COUNT(*) FROM passengers)                                   AS total_passengers,
                            (SELECT COUNT(*) FROM passengers WHERE checked_in = true)           AS checked_in_passengers,
                            (SELECT COUNT(*) FROM bookings)                                     AS total_bookings,
                            (SELECT COUNT(*) FROM maintenance_records WHERE status = 'pending') AS pending_maintenance,
                            (SELECT COUNT(*) FROM security_incidents WHERE resolved = false)    AS security_alerts
                    ")->fetch(\PDO::FETCH_ASSOC);
                    if ($row) {
                        $stats = array_merge($stats, array_map('intval', $row));
                    }
                } catch (\Exception $e) {
                    // DB not ready — return zeroed stats
                }
                echo json_encode(['stats' => $stats]);
                break;

            default:
                http_response_code(400);
                echo json_encode(['error' => 'Invalid action']);
                break;
        }
    } catch (Exception $e) {
        Logger::error('Analytics GET error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to retrieve analytics data']);
    }
}

function handlePost($action) {
    global $pdo;

    try {
        $analytics = new PerformanceAnalytics($pdo);
        $input = json_decode(file_get_contents('php://input'), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON']);
            return;
        }

        switch ($action) {
            case 'generate_report':
                Middleware::authenticate();
                Middleware::checkPermission('write', 'analytics');

                Middleware::validateInput($input, ['start_date', 'end_date']);

                $startDate = $input['start_date'];
                $endDate = $input['end_date'];
                $reportType = $input['report_type'] ?? 'custom';

                $report = $analytics->generatePerformanceReport($startDate, $endDate, $reportType);

                if (isset($report['error'])) {
                    http_response_code(500);
                    echo json_encode(['error' => $report['error']]);
                } else {
                    echo json_encode(['report' => $report]);
                }
                break;

            case 'export_data':
                Middleware::authenticate();
                Middleware::checkPermission('read', 'analytics');

                Middleware::validateInput($input, ['data_type', 'format']);

                $dataType = $input['data_type'];
                $format = $input['format'];
                $filters = $input['filters'] ?? [];

                // Generate export data based on type
                $exportData = generateExportData($pdo, $dataType, $filters);

                if ($format === 'csv') {
                    header('Content-Type: text/csv');
                    header('Content-Disposition: attachment; filename="' . $dataType . '_export.csv"');
                    echo generateCSV($exportData);
                } elseif ($format === 'json') {
                    header('Content-Type: application/json');
                    header('Content-Disposition: attachment; filename="' . $dataType . '_export.json"');
                    echo json_encode($exportData);
                } else {
                    http_response_code(400);
                    echo json_encode(['error' => 'Unsupported export format']);
                }
                break;

            default:
                http_response_code(400);
                echo json_encode(['error' => 'Invalid action']);
                break;
        }
    } catch (Exception $e) {
        Logger::error('Analytics POST error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to process analytics request']);
    }
}

function generateExportData($pdo, $dataType, $filters) {
    try {
        switch ($dataType) {
            case 'flights':
                $stmt = $pdo->prepare("
                    SELECT f.*, a.name as airline_name, ac.model as aircraft_model
                    FROM flights f
                    JOIN airlines a ON f.airline_id = a.id
                    JOIN aircraft ac ON f.aircraft_id = ac.id
                    WHERE f.created_at > NOW() - INTERVAL '30 days'
                    ORDER BY f.scheduled_departure DESC
                ");
                $stmt->execute();
                return $stmt->fetchAll(PDO::FETCH_ASSOC);

            case 'conflicts':
                $stmt = $pdo->prepare("
                    SELECT * FROM conflict_predictions
                    WHERE predicted_at > NOW() - INTERVAL '30 days'
                    ORDER BY predicted_at DESC
                ");
                $stmt->execute();
                return $stmt->fetchAll(PDO::FETCH_ASSOC);

            case 'performance':
                $stmt = $pdo->prepare("
                    SELECT * FROM performance_reports
                    WHERE generated_at > NOW() - INTERVAL '30 days'
                    ORDER BY generated_at DESC
                ");
                $stmt->execute();
                return $stmt->fetchAll(PDO::FETCH_ASSOC);

            default:
                return ['error' => 'Unknown data type'];
        }
    } catch (Exception $e) {
        Logger::error('Export data generation failed: ' . $e->getMessage());
        return ['error' => 'Failed to generate export data'];
    }
}

function generateCSV($data) {
    if (empty($data) || isset($data['error'])) {
        return "No data available\n";
    }

    $output = fopen('php://temp', 'r+');

    // Write headers
    if (is_array($data[0])) {
        fputcsv($output, array_keys($data[0]));
    }

    // Write data
    foreach ($data as $row) {
        if (is_array($row)) {
            // Flatten nested arrays/objects for CSV
            $flatRow = flattenArray($row);
            fputcsv($output, $flatRow);
        }
    }

    rewind($output);
    $csv = stream_get_contents($output);
    fclose($output);

    return $csv;
}

function flattenArray($array, $prefix = '') {
    $result = [];

    foreach ($array as $key => $value) {
        $newKey = $prefix ? $prefix . '_' . $key : $key;

        if (is_array($value) || is_object($value)) {
            $result = array_merge($result, flattenArray((array)$value, $newKey));
        } else {
            $result[$newKey] = $value;
        }
    }

    return $result;
}
?>
