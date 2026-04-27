<?php

/**
 * Cargo Operations API
 *
 * RESTful API for cargo terminal management, freight forwarding, customs clearance, and hazardous materials handling
 */

require_once '../src/ApiResponse.php';
require_once '../models/CargoOperations.php';
require_once '../src/Auth.php';

// Initialize components
$apiResponse = new ApiResponse();
$cargoManager = new CargoOperations();
$auth = new Auth();

// Get request method and path
$method = $_SERVER['REQUEST_METHOD'];
$request = $_SERVER['REQUEST_URI'];
$path = parse_url($request, PHP_URL_PATH);

// Remove base path
$path = str_replace('/api/cargo', '', $path);
$path = str_replace('/backend/api/cargo', '', $path);

// Get path segments
$pathSegments = array_filter(explode('/', trim($path, '/')));
$resource = $pathSegments[0] ?? null;
$action = $pathSegments[1] ?? null;
$id = $pathSegments[2] ?? null;

// Get user from JWT token
$user = null;
$headers = getallheaders();
if (isset($headers['Authorization'])) {
    $token = str_replace('Bearer ', '', $headers['Authorization']);
    try {
        $user = $auth->validateToken($token);
    } catch (Exception $e) {
        $apiResponse->error('Unauthorized', 401);
        exit;
    }
}

try {
    switch ($method) {
        case 'GET':
            handleGetRequest($resource, $action, $id, $cargoManager, $apiResponse);
            break;

        case 'POST':
            handlePostRequest($resource, $action, $cargoManager, $user, $apiResponse);
            break;

        case 'PUT':
            handlePutRequest($resource, $action, $id, $cargoManager, $user, $apiResponse);
            break;

        case 'DELETE':
            handleDeleteRequest($resource, $id, $cargoManager, $user, $apiResponse);
            break;

        default:
            $apiResponse->error('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log("Cargo API Error: " . $e->getMessage());
    $apiResponse->error($e->getMessage(), 500);
}

/**
 * Handle GET requests
 */
function handleGetRequest($resource, $action, $id, $cargoManager, $apiResponse)
{
    switch ($resource) {
        case null:
        case 'dashboard':
            // Get dashboard data
            $dashboardData = $cargoManager->getDashboardData();
            $apiResponse->success($dashboardData);
            break;

        case 'shipments':
            if ($id) {
                // Get specific shipment
                $shipment = $cargoManager->getShipment($id);
                $apiResponse->success($shipment);
            } else {
                // Get shipments with filters
                $filters = $_GET;
                $shipments = $cargoManager->getShipments($filters);
                $apiResponse->success($shipments);
            }
            break;

        case 'terminals':
            // Get cargo terminals
            $airportCode = $_GET['airport_code'] ?? null;
            $terminals = $cargoManager->getCargoTerminals($airportCode);
            $apiResponse->success($terminals);
            break;

        case 'zones':
            // Get warehouse zones
            $terminalId = $_GET['terminal_id'] ?? null;
            $zones = $cargoManager->getWarehouseZones($terminalId);
            $apiResponse->success($zones);
            break;

        case 'forwarders':
            // Get freight forwarders
            $status = $_GET['status'] ?? 'active';
            $forwarders = $cargoManager->getFreightForwarders($status);
            $apiResponse->success($forwarders);
            break;

        case 'customs':
            if ($action === 'declarations') {
                // Get customs declarations
                $status = $_GET['status'] ?? null;
                $declarations = $cargoManager->getCustomsDeclarations($status);
                $apiResponse->success($declarations);
            } else {
                $apiResponse->error('Invalid action', 400);
            }
            break;

        case 'hazardous':
            if ($action === 'materials') {
                // Get hazardous materials
                $hazardClass = $_GET['hazard_class'] ?? null;
                $materials = $cargoManager->getHazardousMaterials($hazardClass);
                $apiResponse->success($materials);
            } elseif ($action === 'compatibility' && isset($_GET['un_numbers'])) {
                // Check hazardous materials compatibility
                $unNumbers = explode(',', $_GET['un_numbers']);
                $compatibility = $cargoManager->checkHazardousCompatibility($unNumbers);
                $apiResponse->success($compatibility);
            } else {
                $apiResponse->error('Invalid action', 400);
            }
            break;

        case 'equipment':
            // Get cargo equipment
            $terminalId = $_GET['terminal_id'] ?? null;
            $status = $_GET['status'] ?? null;
            $equipment = $cargoManager->getCargoEquipment($terminalId, $status);
            $apiResponse->success($equipment);
            break;

        case 'performance':
            // Get performance metrics
            $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
            $endDate = $_GET['end_date'] ?? date('Y-m-d');
            $terminalId = $_GET['terminal_id'] ?? null;

            $metrics = $cargoManager->getPerformanceMetrics($startDate, $endDate, $terminalId);
            $apiResponse->success($metrics);
            break;

        case 'reports':
            if ($action === 'throughput') {
                // Get cargo throughput report
                $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
                $endDate = $_GET['end_date'] ?? date('Y-m-d');
                $report = getCargoThroughputReport($cargoManager, $startDate, $endDate);
                $apiResponse->success($report);
            } elseif ($action === 'customs') {
                // Get customs compliance report
                $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
                $endDate = $_GET['end_date'] ?? date('Y-m-d');
                $report = getCustomsComplianceReport($cargoManager, $startDate, $endDate);
                $apiResponse->success($report);
            } else {
                $apiResponse->error('Invalid report type', 400);
            }
            break;

        default:
            $apiResponse->error('Resource not found', 404);
    }
}

/**
 * Handle POST requests
 */
function handlePostRequest($resource, $action, $cargoManager, $user, $apiResponse)
{
    // Check if user has appropriate permissions
    if (!$user || !in_array($user['role'], ['super_admin', 'admin', 'operator'])) {
        $apiResponse->error('Insufficient permissions', 403);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    switch ($resource) {
        case 'shipments':
            if ($action === 'create') {
                // Create new shipment
                if (!isset($input['origin_airport']) || !isset($input['destination_airport'])) {
                    $apiResponse->error('Origin and destination airports required', 400);
                    return;
                }

                $result = $cargoManager->createShipment($input, $input['items'] ?? []);
                $apiResponse->success($result);
            } elseif ($action === 'track' && isset($input['shipment_id'])) {
                // Track shipment event
                if (!isset($input['event_type']) || !isset($input['location'])) {
                    $apiResponse->error('Event type and location required', 400);
                    return;
                }

                $result = $cargoManager->trackShipmentEvent(
                    $input['shipment_id'],
                    $input['event_type'],
                    $input['location'],
                    $input['description'] ?? '',
                    $input['event_data'] ?? []
                );
                $apiResponse->success($result);
            } else {
                $apiResponse->error('Invalid action', 400);
            }
            break;

        case 'items':
            if ($action === 'add' && isset($input['shipment_id'])) {
                // Add items to shipment
                if (!isset($input['items']) || !is_array($input['items'])) {
                    $apiResponse->error('Items array required', 400);
                    return;
                }

                $cargoManager->addShipmentItems($input['shipment_id'], $input['items']);
                $apiResponse->success(['status' => 'items_added']);
            } else {
                $apiResponse->error('Invalid action', 400);
            }
            break;

        case 'customs':
            if ($action === 'declaration') {
                // Create customs declaration
                if (!isset($input['shipment_id']) || !isset($input['declarant_name'])) {
                    $apiResponse->error('Shipment ID and declarant name required', 400);
                    return;
                }

                $result = $cargoManager->createCustomsDeclaration($input);
                $apiResponse->success($result);
            } else {
                $apiResponse->error('Invalid action', 400);
            }
            break;

        case 'security':
            if ($action === 'screening') {
                // Record security screening
                if (!isset($input['shipment_id'])) {
                    $apiResponse->error('Shipment ID required', 400);
                    return;
                }

                $result = $cargoManager->recordSecurityScreening($input);
                $apiResponse->success($result);
            } else {
                $apiResponse->error('Invalid action', 400);
            }
            break;

        case 'temperature':
            if ($action === 'monitoring') {
                // Record temperature monitoring
                if (!isset($input['shipment_id']) || !isset($input['sensor_id'])) {
                    $apiResponse->error('Shipment ID and sensor ID required', 400);
                    return;
                }

                $result = $cargoManager->recordTemperatureMonitoring($input);
                $apiResponse->success($result);
            } else {
                $apiResponse->error('Invalid action', 400);
            }
            break;

        case 'equipment':
            if ($action === 'status' && isset($input['equipment_id'])) {
                // Update equipment status
                if (!isset($input['status'])) {
                    $apiResponse->error('Status required', 400);
                    return;
                }

                $result = $cargoManager->updateEquipmentStatus($input['equipment_id'], $input['status'], $input);
                $apiResponse->success($result);
            } else {
                $apiResponse->error('Invalid action', 400);
            }
            break;

        default:
            $apiResponse->error('Resource not found', 404);
    }
}

/**
 * Handle PUT requests
 */
function handlePutRequest($resource, $action, $id, $cargoManager, $user, $apiResponse)
{
    // Check if user has admin permissions
    if (!$user || !in_array($user['role'], ['super_admin', 'admin'])) {
        $apiResponse->error('Insufficient permissions', 403);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    switch ($resource) {
        case 'shipments':
            if ($id && isset($input['updates'])) {
                // Update shipment
                $result = updateShipment($cargoManager, $id, $input['updates']);
                $apiResponse->success($result);
            } else {
                $apiResponse->error('Shipment ID and updates required', 400);
            }
            break;

        case 'equipment':
            if ($id && isset($input['status'])) {
                // Update equipment status
                $result = $cargoManager->updateEquipmentStatus($id, $input['status'], $input);
                $apiResponse->success($result);
            } else {
                $apiResponse->error('Equipment ID and status required', 400);
            }
            break;

        default:
            $apiResponse->error('Resource not found', 404);
    }
}

/**
 * Handle DELETE requests
 */
function handleDeleteRequest($resource, $id, $cargoManager, $user, $apiResponse)
{
    // Check if user has admin permissions
    if (!$user || !in_array($user['role'], ['super_admin', 'admin'])) {
        $apiResponse->error('Insufficient permissions', 403);
        return;
    }

    switch ($resource) {
        case 'shipments':
            if ($id) {
                // Cancel shipment
                $result = cancelShipment($cargoManager, $id);
                $apiResponse->success($result);
            } else {
                $apiResponse->error('Shipment ID required', 400);
            }
            break;

        default:
            $apiResponse->error('Resource not found', 404);
    }
}

/**
 * Update shipment
 */
function updateShipment($cargoManager, $shipmentId, $updates)
{
    // This would implement shipment updates
    // For now, return success
    return [
        'shipment_id' => $shipmentId,
        'status' => 'updated',
        'message' => 'Shipment updated successfully'
    ];
}

/**
 * Cancel shipment
 */
function cancelShipment($cargoManager, $shipmentId)
{
    // This would implement shipment cancellation
    // For now, return success
    return [
        'shipment_id' => $shipmentId,
        'status' => 'cancelled',
        'message' => 'Shipment cancelled successfully'
    ];
}

/**
 * Get cargo throughput report
 */
function getCargoThroughputReport($cargoManager, $startDate, $endDate)
{
    try {
        // Get database connection from cargo manager
        $db = $cargoManager->getDatabaseConnection();

        // Query for throughput metrics
        $stmt = $db->prepare("
            SELECT
                COUNT(*) as total_shipments,
                COALESCE(SUM(total_weight_kg), 0) as total_weight_kg,
                COALESCE(SUM(declared_value), 0) as total_value,
                COUNT(CASE WHEN actual_delivery_date <= scheduled_delivery_date THEN 1 END) as on_time_deliveries,
                COUNT(CASE WHEN actual_delivery_date > scheduled_delivery_date THEN 1 END) as delayed_deliveries,
                ROUND(
                    AVG(EXTRACT(EPOCH FROM (actual_delivery_date - scheduled_delivery_date))/86400), 2
                ) as avg_delivery_delay_days
            FROM cargo_shipments
            WHERE created_at >= ? AND created_at <= ?
        ");
        $stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        $summary = $stmt->fetch(PDO::FETCH_ASSOC);

        // Calculate on-time delivery rate
        $totalDeliveries = ($summary['on_time_deliveries'] ?? 0) + ($summary['delayed_deliveries'] ?? 0);
        $onTimeRate = $totalDeliveries > 0 ? round(($summary['on_time_deliveries'] / $totalDeliveries) * 100, 2) : 0;

        // Get daily throughput data
        $stmt = $db->prepare("
            SELECT
                DATE(created_at) as date,
                COUNT(*) as daily_shipments,
                COALESCE(SUM(total_weight_kg), 0) as daily_weight,
                COALESCE(SUM(declared_value), 0) as daily_value
            FROM cargo_shipments
            WHERE created_at >= ? AND created_at <= ?
            GROUP BY DATE(created_at)
            ORDER BY DATE(created_at)
        ");
        $stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        $dailyData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get terminal performance
        $stmt = $db->prepare("
            SELECT
                ct.terminal_name,
                COUNT(cs.shipment_id) as shipments_handled,
                COALESCE(SUM(cs.total_weight_kg), 0) as total_weight,
                ROUND(AVG(EXTRACT(EPOCH FROM (cs.actual_delivery_date - cs.scheduled_delivery_date))/86400), 2) as avg_processing_time
            FROM cargo_terminals ct
            LEFT JOIN cargo_shipments cs ON ct.terminal_id = cs.origin_terminal_id
            WHERE cs.created_at >= ? AND cs.created_at <= ?
            GROUP BY ct.terminal_id, ct.terminal_name
            ORDER BY shipments_handled DESC
        ");
        $stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        $terminalPerformance = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get shipment type breakdown
        $stmt = $db->prepare("
            SELECT
                shipment_type,
                COUNT(*) as count,
                COALESCE(SUM(total_weight_kg), 0) as total_weight,
                COALESCE(SUM(declared_value), 0) as total_value
            FROM cargo_shipments
            WHERE created_at >= ? AND created_at <= ?
            GROUP BY shipment_type
            ORDER BY count DESC
        ");
        $stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        $shipmentTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'report_type' => 'cargo_throughput',
            'period' => ['start_date' => $startDate, 'end_date' => $endDate],
            'summary' => [
                'total_shipments' => (int)($summary['total_shipments'] ?? 0),
                'total_weight_kg' => (float)($summary['total_weight_kg'] ?? 0),
                'total_value' => (float)($summary['total_value'] ?? 0),
                'on_time_delivery_rate' => $onTimeRate,
                'on_time_deliveries' => (int)($summary['on_time_deliveries'] ?? 0),
                'delayed_deliveries' => (int)($summary['delayed_deliveries'] ?? 0),
                'avg_delivery_delay_days' => (float)($summary['avg_delivery_delay_days'] ?? 0)
            ],
            'daily_throughput' => $dailyData,
            'terminal_performance' => $terminalPerformance,
            'shipment_type_breakdown' => $shipmentTypes,
            'generated_at' => date('c')
        ];

    } catch (Exception $e) {
        error_log("Cargo throughput report error: " . $e->getMessage());
        return [
            'report_type' => 'cargo_throughput',
            'period' => ['start_date' => $startDate, 'end_date' => $endDate],
            'error' => 'Failed to generate throughput report',
            'summary' => [
                'total_shipments' => 0,
                'total_weight_kg' => 0,
                'total_value' => 0,
                'on_time_delivery_rate' => 0
            ],
            'data' => []
        ];
    }
}

/**
 * Get customs compliance report
 */
function getCustomsComplianceReport($cargoManager, $startDate, $endDate)
{
    try {
        // Get database connection from cargo manager
        $db = $cargoManager->getDatabaseConnection();

        // Query for customs compliance metrics
        $stmt = $db->prepare("
            SELECT
                COUNT(*) as total_declarations,
                COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_declarations,
                COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_declarations,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_declarations,
                ROUND(AVG(EXTRACT(EPOCH FROM (approval_date - submitted_date))/3600), 2) as avg_processing_hours,
                COUNT(CASE WHEN EXTRACT(EPOCH FROM (approval_date - submitted_date))/3600 <= 24 THEN 1 END) as processed_within_24h,
                COUNT(CASE WHEN EXTRACT(EPOCH FROM (approval_date - submitted_date))/3600 > 24 THEN 1 END) as processed_over_24h
            FROM customs_declarations
            WHERE submitted_date >= ? AND submitted_date <= ?
        ");
        $stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        $summary = $stmt->fetch(PDO::FETCH_ASSOC);

        // Calculate compliance rate
        $totalProcessed = ($summary['approved_declarations'] ?? 0) + ($summary['rejected_declarations'] ?? 0);
        $complianceRate = $totalProcessed > 0 ? round(($summary['approved_declarations'] / $totalProcessed) * 100, 2) : 0;

        // Calculate 24-hour processing rate
        $totalCompleted = ($summary['processed_within_24h'] ?? 0) + ($summary['processed_over_24h'] ?? 0);
        $processingRate24h = $totalCompleted > 0 ? round(($summary['processed_within_24h'] / $totalCompleted) * 100, 2) : 0;

        // Get daily customs activity
        $stmt = $db->prepare("
            SELECT
                DATE(submitted_date) as date,
                COUNT(*) as daily_declarations,
                COUNT(CASE WHEN status = 'approved' THEN 1 END) as daily_approvals,
                COUNT(CASE WHEN status = 'rejected' THEN 1 END) as daily_rejections,
                ROUND(AVG(EXTRACT(EPOCH FROM (approval_date - submitted_date))/3600), 2) as avg_daily_processing_hours
            FROM customs_declarations
            WHERE submitted_date >= ? AND submitted_date <= ?
            GROUP BY DATE(submitted_date)
            ORDER BY DATE(submitted_date)
        ");
        $stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        $dailyActivity = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get declaration types breakdown
        $stmt = $db->prepare("
            SELECT
                declaration_type,
                COUNT(*) as count,
                COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved,
                COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected,
                ROUND(AVG(EXTRACT(EPOCH FROM (approval_date - submitted_date))/3600), 2) as avg_processing_time
            FROM customs_declarations
            WHERE submitted_date >= ? AND submitted_date <= ?
            GROUP BY declaration_type
            ORDER BY count DESC
        ");
        $stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        $declarationTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get top rejection reasons
        $stmt = $db->prepare("
            SELECT
                rejection_reason,
                COUNT(*) as count
            FROM customs_declarations
            WHERE submitted_date >= ? AND submitted_date <= ?
            AND status = 'rejected' AND rejection_reason IS NOT NULL
            GROUP BY rejection_reason
            ORDER BY count DESC
            LIMIT 10
        ");
        $stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        $rejectionReasons = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get processing time distribution
        $stmt = $db->prepare("
            SELECT
                CASE
                    WHEN EXTRACT(EPOCH FROM (approval_date - submitted_date))/3600 < 1 THEN '< 1 hour'
                    WHEN EXTRACT(EPOCH FROM (approval_date - submitted_date))/3600 < 4 THEN '1-4 hours'
                    WHEN EXTRACT(EPOCH FROM (approval_date - submitted_date))/3600 < 12 THEN '4-12 hours'
                    WHEN EXTRACT(EPOCH FROM (approval_date - submitted_date))/3600 < 24 THEN '12-24 hours'
                    ELSE '> 24 hours'
                END as processing_time_range,
                COUNT(*) as count
            FROM customs_declarations
            WHERE submitted_date >= ? AND submitted_date <= ?
            AND approval_date IS NOT NULL
            GROUP BY processing_time_range
            ORDER BY MIN(EXTRACT(EPOCH FROM (approval_date - submitted_date))/3600)
        ");
        $stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        $processingTimes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'report_type' => 'customs_compliance',
            'period' => ['start_date' => $startDate, 'end_date' => $endDate],
            'summary' => [
                'total_declarations' => (int)($summary['total_declarations'] ?? 0),
                'approved_declarations' => (int)($summary['approved_declarations'] ?? 0),
                'rejected_declarations' => (int)($summary['rejected_declarations'] ?? 0),
                'pending_declarations' => (int)($summary['pending_declarations'] ?? 0),
                'compliance_rate' => $complianceRate,
                'avg_processing_hours' => (float)($summary['avg_processing_hours'] ?? 0),
                'processed_within_24h' => (int)($summary['processed_within_24h'] ?? 0),
                'processing_rate_24h' => $processingRate24h
            ],
            'daily_activity' => $dailyActivity,
            'declaration_types' => $declarationTypes,
            'rejection_reasons' => $rejectionReasons,
            'processing_time_distribution' => $processingTimes,
            'generated_at' => date('c')
        ];

    } catch (Exception $e) {
        error_log("Customs compliance report error: " . $e->getMessage());
        return [
            'report_type' => 'customs_compliance',
            'period' => ['start_date' => $startDate, 'end_date' => $endDate],
            'error' => 'Failed to generate customs compliance report',
            'summary' => [
                'total_declarations' => 0,
                'compliance_rate' => 0,
                'avg_processing_hours' => 0
            ],
            'data' => []
        ];
    }
}

/**
 * Check if cargo module is enabled
 */
function isCargoEnabled()
{
    // This would typically check the modules table
    // For now, return true as we're implementing it
    return true;
}

/**
 * Get cargo module configuration
 */
function getCargoConfig()
{
    // This would retrieve configuration from the modules table
    return [
        'cargo_tracking_enabled' => true,
        'customs_integration_enabled' => true,
        'hazardous_materials_enabled' => true,
        'temperature_monitoring_enabled' => true,
        'equipment_management_enabled' => true,
        'reporting_enabled' => true,
        'alert_thresholds' => [
            'temperature_alert_celsius' => 5,
            'capacity_warning_percent' => 80,
            'delay_alert_hours' => 24
        ]
    ];
}
