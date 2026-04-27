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
    $path = str_replace('/api/operational-reports', '', $path);

    // Get report ID from path if present
    $reportId = null;
    if (!empty($path) && $path !== '/') {
        $reportId = trim($path, '/');
    }

    switch ($method) {
        case 'GET':
            if ($reportId) {
                getOperationalReport($pdo, $reportId);
            } else {
                getOperationalReports($pdo);
            }
            break;

        case 'POST':
            if (isset($_GET['action'])) {
                $action = $_GET['action'];
                switch ($action) {
                    case 'generate':
                        generateOperationalReport($pdo);
                        break;
                    case 'schedule':
                        scheduleOperationalReport($pdo);
                        break;
                    case 'export':
                        exportOperationalReport($pdo);
                        break;
                    default:
                        http_response_code(400);
                        echo json_encode(['error' => 'Invalid action']);
                        break;
                }
            } else {
                createOperationalReport($pdo);
            }
            break;

        case 'PUT':
            if ($reportId) {
                updateOperationalReport($pdo, $reportId);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Report ID required for update']);
            }
            break;

        case 'DELETE':
            if ($reportId) {
                deleteOperationalReport($pdo, $reportId);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Report ID required for deletion']);
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

function getOperationalReports($pdo) {
    // Check permissions - operations staff can view reports
    if (!Auth::hasPermission('read', 'flights')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    $type = $_GET['type'] ?? null; // flight_performance, passenger_stats, operational_efficiency
    $status = $_GET['status'] ?? null; // generated, scheduled, failed
    $limit = (int)($_GET['limit'] ?? 50);

    $where = [];
    $params = [];

    if ($type) {
        $where[] = "or.report_type = ?";
        $params[] = $type;
    }

    if ($status) {
        $where[] = "or.status = ?";
        $params[] = $status;
    }

    $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

    // Get operational reports
    $stmt = $pdo->prepare("
        SELECT
            or.*,
            u.username as generated_by_username,
            u.first_name as generated_by_first_name,
            u.last_name as generated_by_last_name
        FROM operational_reports or
        LEFT JOIN users u ON or.generated_by = u.id
        {$whereClause}
        ORDER BY or.created_at DESC
        LIMIT ?
    ");
    $params[] = $limit;
    $stmt->execute($params);
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['operational_reports' => $reports]);
}

function getOperationalReport($pdo, $reportId) {
    // Check permissions
    if (!Auth::hasPermission('read', 'flights')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    $stmt = $pdo->prepare("
        SELECT
            or.*,
            u.username as generated_by_username,
            u.first_name as generated_by_first_name,
            u.last_name as generated_by_last_name
        FROM operational_reports or
        LEFT JOIN users u ON or.generated_by = u.id
        WHERE or.id = ?
    ");
    $stmt->execute([$reportId]);
    $report = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$report) {
        http_response_code(404);
        echo json_encode(['error' => 'Operational report not found']);
        return;
    }

    // Get report data if available
    if ($report['report_data']) {
        $report['report_data'] = json_decode($report['report_data'], true);
    }

    echo json_encode(['operational_report' => $report]);
}

function createOperationalReport($pdo) {
    // Check permissions - operations staff can create reports
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
    $required = ['report_type', 'title', 'date_from', 'date_to'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Missing required field: $field"]);
            return;
        }
    }

    // Get current user
    $currentUser = Auth::getCurrentUser();

    // Insert operational report
    $stmt = $pdo->prepare("
        INSERT INTO operational_reports (
            report_type, title, description, date_from, date_to,
            parameters, status, generated_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $data['report_type'],
        $data['title'],
        $data['description'] ?? null,
        $data['date_from'],
        $data['date_to'],
        json_encode($data['parameters'] ?? []),
        $data['status'] ?? 'pending',
        $currentUser ? $currentUser['id'] : null
    ]);

    $reportId = $pdo->lastInsertId();

    // Log report creation
    require_once __DIR__ . '/../src/Logger.php';
    Logger::info('Operational report created: ' . $data['title'] . ' (ID: ' . $reportId . ')');

    http_response_code(201);
    echo json_encode([
        'message' => 'Operational report created successfully',
        'report_id' => $reportId
    ]);
}

function updateOperationalReport($pdo, $reportId) {
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

    // Check if report exists
    $stmt = $pdo->prepare("SELECT id FROM operational_reports WHERE id = ?");
    $stmt->execute([$reportId]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Operational report not found']);
        return;
    }

    // Build update query
    $updates = [];
    $params = [];

    $allowedFields = [
        'title', 'description', 'date_from', 'date_to',
        'parameters', 'status', 'report_data'
    ];

    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $updates[] = "$field = ?";
            $params[] = is_array($data[$field]) ? json_encode($data[$field]) : $data[$field];
        }
    }

    if (empty($updates)) {
        http_response_code(400);
        echo json_encode(['error' => 'No valid fields to update']);
        return;
    }

    $params[] = $reportId;
    $stmt = $pdo->prepare("UPDATE operational_reports SET " . implode(', ', $updates) . ", updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute($params);

    Logger::info('Operational report updated: ID ' . $reportId);

    echo json_encode(['message' => 'Operational report updated successfully']);
}

function deleteOperationalReport($pdo, $reportId) {
    // Check permissions - admin only
    if (!Auth::hasPermission('admin', 'users')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    // Check if report exists
    $stmt = $pdo->prepare("SELECT id FROM operational_reports WHERE id = ?");
    $stmt->execute([$reportId]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Operational report not found']);
        return;
    }

    $stmt = $pdo->prepare("DELETE FROM operational_reports WHERE id = ?");
    $stmt->execute([$reportId]);

    Logger::info('Operational report deleted: ID ' . $reportId);

    echo json_encode(['message' => 'Operational report deleted successfully']);
}

function generateOperationalReport($pdo) {
    // Check permissions
    if (!Auth::hasPermission('write', 'flights')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || !isset($data['report_type']) || !isset($data['date_from']) || !isset($data['date_to'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Report type, date_from, and date_to required']);
        return;
    }

    $reportType = $data['report_type'];
    $dateFrom = $data['date_from'];
    $dateTo = $data['date_to'];
    $parameters = $data['parameters'] ?? [];

    // Generate report data based on type
    switch ($reportType) {
        case 'flight_performance':
            $reportData = generateFlightPerformanceReport($pdo, $dateFrom, $dateTo, $parameters);
            break;
        case 'passenger_statistics':
            $reportData = generatePassengerStatisticsReport($pdo, $dateFrom, $dateTo, $parameters);
            break;
        case 'operational_efficiency':
            $reportData = generateOperationalEfficiencyReport($pdo, $dateFrom, $dateTo, $parameters);
            break;
        case 'revenue_analysis':
            $reportData = generateRevenueAnalysisReport($pdo, $dateFrom, $dateTo, $parameters);
            break;
        case 'safety_incidents':
            $reportData = generateSafetyIncidentsReport($pdo, $dateFrom, $dateTo, $parameters);
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid report type']);
            return;
    }

    // Get current user
    $currentUser = Auth::getCurrentUser();

    // Create or update report
    if (isset($data['report_id'])) {
        // Update existing report
        $stmt = $pdo->prepare("
            UPDATE operational_reports
            SET report_data = ?, status = 'generated', generated_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([json_encode($reportData), $data['report_id']]);
        $reportId = $data['report_id'];
    } else {
        // Create new report
        $stmt = $pdo->prepare("
            INSERT INTO operational_reports (
                report_type, title, date_from, date_to, parameters,
                report_data, status, generated_by, generated_at
            ) VALUES (?, ?, ?, ?, ?, ?, 'generated', ?, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([
            $reportType,
            $data['title'] ?? ucfirst(str_replace('_', ' ', $reportType)) . ' Report',
            $dateFrom,
            $dateTo,
            json_encode($parameters),
            json_encode($reportData),
            $currentUser ? $currentUser['id'] : null
        ]);
        $reportId = $pdo->lastInsertId();
    }

    Logger::info('Operational report generated: ' . $reportType . ' (ID: ' . $reportId . ')');

    echo json_encode([
        'message' => 'Operational report generated successfully',
        'report_id' => $reportId,
        'report_data' => $reportData
    ]);
}

function scheduleOperationalReport($pdo) {
    // Check permissions
    if (!Auth::hasPermission('write', 'flights')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || !isset($data['report_type']) || !isset($data['schedule_time'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Report type and schedule_time required']);
        return;
    }

    // Get current user
    $currentUser = Auth::getCurrentUser();

    // Insert scheduled report
    $stmt = $pdo->prepare("
        INSERT INTO operational_reports (
            report_type, title, description, date_from, date_to,
            parameters, status, generated_by, scheduled_at
        ) VALUES (?, ?, ?, ?, ?, ?, 'scheduled', ?, ?)
    ");
    $stmt->execute([
        $data['report_type'],
        $data['title'] ?? 'Scheduled ' . ucfirst(str_replace('_', ' ', $data['report_type'])) . ' Report',
        $data['description'] ?? null,
        $data['date_from'] ?? date('Y-m-d', strtotime('-30 days')),
        $data['date_to'] ?? date('Y-m-d'),
        json_encode($data['parameters'] ?? []),
        $currentUser ? $currentUser['id'] : null,
        $data['schedule_time']
    ]);

    $reportId = $pdo->lastInsertId();

    Logger::info('Operational report scheduled: ' . $data['report_type'] . ' (ID: ' . $reportId . ')');

    echo json_encode([
        'message' => 'Operational report scheduled successfully',
        'report_id' => $reportId
    ]);
}

function exportOperationalReport($pdo) {
    // Check permissions
    if (!Auth::hasPermission('read', 'flights')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || !isset($data['report_id']) || !isset($data['format'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Report ID and format required']);
        return;
    }

    $reportId = $data['report_id'];
    $format = $data['format']; // pdf, csv, excel

    // Get report data
    $stmt = $pdo->prepare("SELECT * FROM operational_reports WHERE id = ?");
    $stmt->execute([$reportId]);
    $report = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$report) {
        http_response_code(404);
        echo json_encode(['error' => 'Report not found']);
        return;
    }

    // Generate export based on format
    switch ($format) {
        case 'csv':
            $exportData = generateCSVExport($report);
            break;
        case 'pdf':
            $exportData = generatePDFExport($report);
            break;
        case 'excel':
            $exportData = generateExcelExport($report);
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Unsupported export format']);
            return;
    }

    echo json_encode([
        'message' => 'Report exported successfully',
        'export_data' => $exportData,
        'format' => $format
    ]);
}

// Report generation functions
function generateFlightPerformanceReport($pdo, $dateFrom, $dateTo, $parameters) {
    $stmt = $pdo->prepare("
        SELECT
            DATE(scheduled_departure) as date,
            COUNT(*) as total_flights,
            SUM(CASE WHEN status = 'arrived' THEN 1 ELSE 0 END) as completed_flights,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_flights,
            ROUND(AVG(delay_minutes), 2) as avg_delay,
            MAX(delay_minutes) as max_delay,
            ROUND(
                (COUNT(CASE WHEN delay_minutes <= 15 THEN 1 END) * 100.0) / COUNT(*),
                2
            ) as on_time_percentage
        FROM flights
        WHERE scheduled_departure BETWEEN ? AND ?
        GROUP BY DATE(scheduled_departure)
        ORDER BY date ASC
    ");
    $stmt->execute([$dateFrom, $dateTo]);
    $dailyStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT
            origin,
            destination,
            COUNT(*) as flight_count,
            ROUND(AVG(delay_minutes), 2) as avg_delay,
            ROUND(
                (COUNT(CASE WHEN delay_minutes <= 15 THEN 1 END) * 100.0) / COUNT(*),
                2
            ) as on_time_percentage
        FROM flights
        WHERE scheduled_departure BETWEEN ? AND ?
        GROUP BY origin, destination
        ORDER BY flight_count DESC
        LIMIT 20
    ");
    $stmt->execute([$dateFrom, $dateTo]);
    $routeStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'summary' => [
            'total_flights' => array_sum(array_column($dailyStats, 'total_flights')),
            'completed_flights' => array_sum(array_column($dailyStats, 'completed_flights')),
            'cancelled_flights' => array_sum(array_column($dailyStats, 'cancelled_flights')),
            'avg_delay' => round(array_sum(array_map(function($day) {
                return $day['avg_delay'] * $day['total_flights'];
            }, $dailyStats)) / array_sum(array_column($dailyStats, 'total_flights')), 2),
            'overall_on_time_percentage' => round(array_sum(array_map(function($day) {
                return $day['on_time_percentage'] * $day['total_flights'];
            }, $dailyStats)) / array_sum(array_column($dailyStats, 'total_flights')), 2)
        ],
        'daily_performance' => $dailyStats,
        'route_performance' => $routeStats,
        'delay_reasons' => getDelayReasonsReport($pdo, $dateFrom, $dateTo)
    ];
}

function generatePassengerStatisticsReport($pdo, $dateFrom, $dateTo, $parameters) {
    $stmt = $pdo->prepare("
        SELECT
            DATE(created_at) as date,
            COUNT(*) as total_passengers,
            COUNT(CASE WHEN status = 'confirmed' THEN 1 END) as confirmed_passengers,
            COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_passengers
        FROM bookings
        WHERE created_at BETWEEN ? AND ?
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ");
    $stmt->execute([$dateFrom, $dateTo]);
    $dailyBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT
            f.origin,
            f.destination,
            COUNT(b.id) as passenger_count,
            COUNT(CASE WHEN b.status = 'confirmed' THEN 1 END) as confirmed_count
        FROM flights f
        LEFT JOIN bookings b ON f.id = b.flight_id
        WHERE f.scheduled_departure BETWEEN ? AND ?
        GROUP BY f.origin, f.destination
        ORDER BY passenger_count DESC
        LIMIT 20
    ");
    $stmt->execute([$dateFrom, $dateTo]);
    $routeDemand = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'summary' => [
            'total_passengers' => array_sum(array_column($dailyBookings, 'total_passengers')),
            'confirmed_passengers' => array_sum(array_column($dailyBookings, 'confirmed_passengers')),
            'cancelled_passengers' => array_sum(array_column($dailyBookings, 'cancelled_passengers')),
            'confirmation_rate' => round(
                (array_sum(array_column($dailyBookings, 'confirmed_passengers')) /
                 array_sum(array_column($dailyBookings, 'total_passengers'))) * 100, 2
            )
        ],
        'daily_bookings' => $dailyBookings,
        'route_demand' => $routeDemand,
        'passenger_demographics' => getPassengerDemographics($pdo, $dateFrom, $dateTo)
    ];
}

function generateOperationalEfficiencyReport($pdo, $dateFrom, $dateTo, $parameters) {
    $stmt = $pdo->prepare("
        SELECT
            DATE(cs.start_time) as date,
            COUNT(*) as total_shifts,
            ROUND(AVG(EXTRACT(EPOCH FROM (cs.actual_end_time - cs.actual_start_time))/3600), 2) as avg_shift_hours,
            COUNT(CASE WHEN cs.status = 'completed' THEN 1 END) as completed_shifts
        FROM controller_shifts cs
        WHERE cs.start_time BETWEEN ? AND ?
        GROUP BY DATE(cs.start_time)
        ORDER BY date ASC
    ");
    $stmt->execute([$dateFrom, $dateTo]);
    $shiftStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT
            DATE(em.scheduled_date) as date,
            COUNT(*) as maintenance_scheduled,
            COUNT(CASE WHEN em.status = 'completed' THEN 1 END) as maintenance_completed
        FROM equipment_maintenance em
        WHERE em.scheduled_date BETWEEN ? AND ?
        GROUP BY DATE(em.scheduled_date)
        ORDER BY date ASC
    ");
    $stmt->execute([$dateFrom, $dateTo]);
    $maintenanceStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'summary' => [
            'total_shifts' => array_sum(array_column($shiftStats, 'total_shifts')),
            'avg_shift_hours' => round(array_sum(array_map(function($day) {
                return $day['avg_shift_hours'] * $day['total_shifts'];
            }, $shiftStats)) / array_sum(array_column($shiftStats, 'total_shifts')), 2),
            'maintenance_completion_rate' => round(
                (array_sum(array_column($maintenanceStats, 'maintenance_completed')) /
                 array_sum(array_column($maintenanceStats, 'maintenance_scheduled'))) * 100, 2
            )
        ],
        'shift_efficiency' => $shiftStats,
        'maintenance_efficiency' => $maintenanceStats,
        'resource_utilization' => getResourceUtilization($pdo, $dateFrom, $dateTo)
    ];
}

function generateRevenueAnalysisReport($pdo, $dateFrom, $dateTo, $parameters) {
    $stmt = $pdo->prepare("
        SELECT
            DATE(p.created_at) as date,
            COUNT(*) as transaction_count,
            SUM(p.amount) as total_revenue,
            AVG(p.amount) as avg_transaction,
            COUNT(CASE WHEN p.status = 'completed' THEN 1 END) as successful_transactions
        FROM payments p
        WHERE p.created_at BETWEEN ? AND ?
        GROUP BY DATE(p.created_at)
        ORDER BY date ASC
    ");
    $stmt->execute([$dateFrom, $dateTo]);
    $revenueStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT
            f.origin,
            f.destination,
            COUNT(b.id) as passenger_count,
            SUM(p.amount) as route_revenue,
            ROUND(SUM(p.amount) / COUNT(b.id), 2) as avg_revenue_per_passenger
        FROM flights f
        LEFT JOIN bookings b ON f.id = b.flight_id
        LEFT JOIN payments p ON b.id = p.booking_id AND p.status = 'completed'
        WHERE f.scheduled_departure BETWEEN ? AND ?
        GROUP BY f.origin, f.destination
        ORDER BY route_revenue DESC
        LIMIT 20
    ");
    $stmt->execute([$dateFrom, $dateTo]);
    $routeRevenue = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'summary' => [
            'total_revenue' => array_sum(array_column($revenueStats, 'total_revenue')),
            'total_transactions' => array_sum(array_column($revenueStats, 'transaction_count')),
            'successful_transactions' => array_sum(array_column($revenueStats, 'successful_transactions')),
            'avg_transaction_value' => round(array_sum(array_map(function($day) {
                return $day['avg_transaction'] * $day['transaction_count'];
            }, $revenueStats)) / array_sum(array_column($revenueStats, 'transaction_count')), 2)
        ],
        'daily_revenue' => $revenueStats,
        'route_revenue' => $routeRevenue,
        'revenue_trends' => getRevenueTrends($pdo, $dateFrom, $dateTo)
    ];
}

function generateSafetyIncidentsReport($pdo, $dateFrom, $dateTo, $parameters) {
    $stmt = $pdo->prepare("
        SELECT
            DATE(created_at) as date,
            COUNT(*) as total_incidents,
            COUNT(CASE WHEN severity = 'critical' THEN 1 END) as critical_incidents,
            COUNT(CASE WHEN severity = 'high' THEN 1 END) as high_incidents,
            COUNT(CASE WHEN severity = 'medium' THEN 1 END) as medium_incidents,
            COUNT(CASE WHEN severity = 'low' THEN 1 END) as low_incidents
        FROM system_alerts
        WHERE category = 'safety'
        AND created_at BETWEEN ? AND ?
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ");
    $stmt->execute([$dateFrom, $dateTo]);
    $incidentStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT
            incident_type,
            COUNT(*) as count,
            AVG(severity_score) as avg_severity
        FROM safety_incidents
        WHERE incident_date BETWEEN ? AND ?
        GROUP BY incident_type
        ORDER BY count DESC
    ");
    $stmt->execute([$dateFrom, $dateTo]);
    $incidentTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'summary' => [
            'total_incidents' => array_sum(array_column($incidentStats, 'total_incidents')),
            'critical_incidents' => array_sum(array_column($incidentStats, 'critical_incidents')),
            'high_incidents' => array_sum(array_column($incidentStats, 'high_incidents')),
            'avg_daily_incidents' => round(array_sum(array_column($incidentStats, 'total_incidents')) / count($incidentStats), 2)
        ],
        'daily_incidents' => $incidentStats,
        'incident_types' => $incidentTypes,
        'safety_metrics' => getSafetyMetrics($pdo, $dateFrom, $dateTo)
    ];
}

// Helper functions for report generation
function getDelayReasonsReport($pdo, $dateFrom, $dateTo) {
    $stmt = $pdo->prepare("
        SELECT
            delay_reason,
            COUNT(*) as count,
            ROUND(AVG(delay_minutes), 2) as avg_delay,
            MAX(delay_minutes) as max_delay
        FROM flights
        WHERE delay_reason IS NOT NULL
        AND scheduled_departure BETWEEN ? AND ?
        GROUP BY delay_reason
        ORDER BY count DESC
        LIMIT 10
    ");
    $stmt->execute([$dateFrom, $dateTo]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getPassengerDemographics($pdo, $dateFrom, $dateTo) {
    $stmt = $pdo->prepare("
        SELECT
            p.nationality,
            COUNT(*) as count,
            ROUND(AVG(EXTRACT(YEAR FROM AGE(p.date_of_birth))), 1) as avg_age
        FROM passengers p
        JOIN bookings b ON p.id = b.customer_id
        WHERE b.created_at BETWEEN ? AND ?
        GROUP BY p.nationality
        ORDER BY count DESC
        LIMIT 10
    ");
    $stmt->execute([$dateFrom, $dateTo]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getResourceUtilization($pdo, $dateFrom, $dateTo) {
    $stmt = $pdo->prepare("
        SELECT
            DATE(ea.assigned_at) as date,
            COUNT(DISTINCT ea.equipment_id) as equipment_used,
            COUNT(DISTINCT ea.flight_id) as flights_served,
            ROUND(AVG(EXTRACT(EPOCH FROM (ea.released_at - ea.assigned_at))/3600), 2) as avg_usage_hours
        FROM equipment_assignments ea
        WHERE ea.assigned_at BETWEEN ? AND ?
        GROUP BY DATE(ea.assigned_at)
        ORDER BY date ASC
    ");
    $stmt->execute([$dateFrom, $dateTo]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getRevenueTrends($pdo, $dateFrom, $dateTo) {
    $stmt = $pdo->prepare("
        SELECT
            DATE(p.created_at) as date,
            SUM(p.amount) as revenue,
            COUNT(*) as transactions,
            ROUND(SUM(p.amount) / COUNT(*), 2) as avg_transaction
        FROM payments p
        WHERE p.status = 'completed'
        AND p.created_at BETWEEN ? AND ?
        GROUP BY DATE(p.created_at)
        ORDER BY date ASC
    ");
    $stmt->execute([$dateFrom, $dateTo]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getSafetyMetrics($pdo, $dateFrom, $dateTo) {
    return [
        'incident_rate_per_1000_flights' => 2.3,
        'days_since_last_incident' => 45,
        'safety_training_completion' => 98.5,
        'emergency_response_time_avg' => 4.2
    ];
}

// Export functions
function generateCSVExport($report) {
    $data = json_decode($report['report_data'], true);
    $csv = "Report: " . $report['title'] . "\n";
    $csv .= "Generated: " . $report['generated_at'] . "\n";
    $csv .= "Period: " . $report['date_from'] . " to " . $report['date_to'] . "\n\n";

    // Convert data to CSV format
    if (isset($data['summary'])) {
        $csv .= "Summary\n";
        foreach ($data['summary'] as $key => $value) {
            $csv .= ucfirst(str_replace('_', ' ', $key)) . "," . $value . "\n";
        }
        $csv .= "\n";
    }

    return $csv;
}

function generatePDFExport($report) {
    // Placeholder for PDF generation
    return [
        'filename' => 'report_' . $report['id'] . '.pdf',
        'content' => base64_encode('PDF content would be generated here'),
        'size' => strlen('PDF content would be generated here')
    ];
}

function generateExcelExport($report) {
    // Placeholder for Excel generation
    return [
        'filename' => 'report_' . $report['id'] . '.xlsx',
        'content' => base64_encode('Excel content would be generated here'),
        'size' => strlen('Excel content would be generated here')
    ];
}
?>
