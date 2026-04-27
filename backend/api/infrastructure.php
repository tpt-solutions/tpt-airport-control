<?php

/**
 * Infrastructure Management API
 *
 * Handles building systems monitoring, IoT sensor management, utilities tracking,
 * and facility maintenance scheduling for comprehensive infrastructure oversight
 */

require_once '../src/Config.php';
require_once '../src/ApiResponse.php';
require_once '../models/InfrastructureManagement.php';
require_once '../services/InfrastructureManagementService.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// JWT Authentication
$headers = getallheaders();
$authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';

if (empty($authHeader) || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
    ApiResponse::error('Unauthorized', 401);
}

$token = $matches[1];
$auth = new Auth();
$userId = $auth->validateToken($token);

if (!$userId) {
    ApiResponse::error('Invalid token', 401);
}

$method = $_SERVER['REQUEST_METHOD'];
$request = $_SERVER['REQUEST_URI'];
$path = parse_url($request, PHP_URL_PATH);
$path = str_replace('/backend/api/infrastructure', '', $path);
$pathParts = explode('/', trim($path, '/'));

$infrastructureService = new InfrastructureManagementService();

try {
    switch ($method) {
        case 'GET':
            handleGet($pathParts, $infrastructureService);
            break;
        case 'POST':
            handlePost($pathParts, $infrastructureService);
            break;
        case 'PUT':
            handlePut($pathParts, $infrastructureService);
            break;
        case 'DELETE':
            handleDelete($pathParts, $infrastructureService);
            break;
        default:
            ApiResponse::error('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log("Infrastructure Management API Error: " . $e->getMessage());
    ApiResponse::error('Internal server error', 500);
}

function handleGet($pathParts, $service) {
    if (empty($pathParts[0])) {
        // Get all infrastructure systems
        $filters = $_GET;
        $systems = $service->getAllSystems($filters);
        ApiResponse::success($systems);
    } elseif ($pathParts[0] === 'systems' && isset($pathParts[1])) {
        // Get specific system
        $systemId = $pathParts[1];
        $system = $service->getSystemById($systemId);
        if ($system) {
            ApiResponse::success($system);
        } else {
            ApiResponse::error('System not found', 404);
        }
    } elseif ($pathParts[0] === 'sensors') {
        // Get IoT sensors
        $filters = $_GET;
        $sensors = $service->getSensors($filters);
        ApiResponse::success($sensors);
    } elseif ($pathParts[0] === 'sensors' && isset($pathParts[1])) {
        // Get specific sensor
        $sensorId = $pathParts[1];
        $sensor = $service->getSensorById($sensorId);
        if ($sensor) {
            ApiResponse::success($sensor);
        } else {
            ApiResponse::error('Sensor not found', 404);
        }
    } elseif ($pathParts[0] === 'utilities') {
        // Get utilities data
        $filters = $_GET;
        $utilities = $service->getUtilities($filters);
        ApiResponse::success($utilities);
    } elseif ($pathParts[0] === 'maintenance') {
        // Get maintenance schedules
        $filters = $_GET;
        $maintenance = $service->getMaintenanceSchedules($filters);
        ApiResponse::success($maintenance);
    } elseif ($pathParts[0] === 'alerts') {
        // Get system alerts
        $filters = $_GET;
        $alerts = $service->getSystemAlerts($filters);
        ApiResponse::success($alerts);
    } elseif ($pathParts[0] === 'readings') {
        // Get sensor readings
        $filters = $_GET;
        $readings = $service->getSensorReadings($filters);
        ApiResponse::success($readings);
    } elseif ($pathParts[0] === 'dashboard') {
        // Get infrastructure dashboard data
        $dashboard = $service->getDashboardData();
        ApiResponse::success($dashboard);
    } elseif ($pathParts[0] === 'reports') {
        // Get infrastructure reports
        $filters = $_GET;
        $reports = $service->getInfrastructureReports($filters);
        ApiResponse::success($reports);
    } else {
        ApiResponse::error('Invalid endpoint', 400);
    }
}

function handlePost($pathParts, $service) {
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($pathParts[0])) {
        // Create new infrastructure system
        if (!validateSystemData($data)) {
            ApiResponse::error('Invalid system data', 400);
            return;
        }

        $systemId = $service->createSystem($data);
        ApiResponse::success(['system_id' => $systemId, 'message' => 'Infrastructure system created successfully'], 201);
    } elseif ($pathParts[0] === 'sensors') {
        // Register new IoT sensor
        if (!validateSensorData($data)) {
            ApiResponse::error('Invalid sensor data', 400);
            return;
        }

        $sensorId = $service->registerSensor($data);
        ApiResponse::success(['sensor_id' => $sensorId, 'message' => 'IoT sensor registered successfully'], 201);
    } elseif ($pathParts[0] === 'readings') {
        // Record sensor reading
        if (!validateReadingData($data)) {
            ApiResponse::error('Invalid reading data', 400);
            return;
        }

        $readingId = $service->recordSensorReading($data);
        ApiResponse::success(['reading_id' => $readingId, 'message' => 'Sensor reading recorded successfully'], 201);
    } elseif ($pathParts[0] === 'maintenance') {
        // Schedule maintenance
        if (!validateMaintenanceData($data)) {
            ApiResponse::error('Invalid maintenance data', 400);
            return;
        }

        $scheduleId = $service->scheduleMaintenance($data);
        ApiResponse::success(['schedule_id' => $scheduleId, 'message' => 'Maintenance scheduled successfully'], 201);
    } elseif ($pathParts[0] === 'alerts') {
        // Create system alert
        if (!validateAlertData($data)) {
            ApiResponse::error('Invalid alert data', 400);
            return;
        }

        $alertId = $service->createAlert($data);
        ApiResponse::success(['alert_id' => $alertId, 'message' => 'System alert created successfully'], 201);
    } elseif ($pathParts[0] === 'reports') {
        // Generate infrastructure report
        if (!validateReportData($data)) {
            ApiResponse::error('Invalid report data', 400);
            return;
        }

        $reportId = $service->generateReport($data);
        ApiResponse::success(['report_id' => $reportId, 'message' => 'Infrastructure report generated successfully'], 201);
    } else {
        ApiResponse::error('Invalid endpoint', 400);
    }
}

function handlePut($pathParts, $service) {
    $data = json_decode(file_get_contents('php://input'), true);

    if ($pathParts[0] === 'systems' && isset($pathParts[1])) {
        // Update infrastructure system
        $systemId = $pathParts[1];
        if (!$service->getSystemById($systemId)) {
            ApiResponse::error('System not found', 404);
            return;
        }

        $result = $service->updateSystem($systemId, $data);
        ApiResponse::success(['message' => 'Infrastructure system updated successfully']);
    } elseif ($pathParts[0] === 'sensors' && isset($pathParts[1])) {
        // Update IoT sensor
        $sensorId = $pathParts[1];
        $result = $service->updateSensor($sensorId, $data);
        ApiResponse::success(['message' => 'IoT sensor updated successfully']);
    } elseif ($pathParts[0] === 'maintenance' && isset($pathParts[1])) {
        // Update maintenance schedule
        $scheduleId = $pathParts[1];
        $result = $service->updateMaintenanceSchedule($scheduleId, $data);
        ApiResponse::success(['message' => 'Maintenance schedule updated successfully']);
    } elseif ($pathParts[0] === 'alerts' && isset($pathParts[1])) {
        // Update system alert
        $alertId = $pathParts[1];
        $result = $service->updateAlert($alertId, $data);
        ApiResponse::success(['message' => 'System alert updated successfully']);
    } else {
        ApiResponse::error('Invalid endpoint', 400);
    }
}

function handleDelete($pathParts, $service) {
    if ($pathParts[0] === 'systems' && isset($pathParts[1])) {
        // Delete infrastructure system
        $systemId = $pathParts[1];
        $result = $service->deleteSystem($systemId);
        ApiResponse::success(['message' => 'Infrastructure system deleted successfully']);
    } elseif ($pathParts[0] === 'sensors' && isset($pathParts[1])) {
        // Delete IoT sensor
        $sensorId = $pathParts[1];
        $result = $service->deleteSensor($sensorId);
        ApiResponse::success(['message' => 'IoT sensor deleted successfully']);
    } elseif ($pathParts[0] === 'maintenance' && isset($pathParts[1])) {
        // Delete maintenance schedule
        $scheduleId = $pathParts[1];
        $result = $service->deleteMaintenanceSchedule($scheduleId);
        ApiResponse::success(['message' => 'Maintenance schedule deleted successfully']);
    } elseif ($pathParts[0] === 'alerts' && isset($pathParts[1])) {
        // Delete system alert
        $alertId = $pathParts[1];
        $result = $service->deleteAlert($alertId);
        ApiResponse::success(['message' => 'System alert deleted successfully']);
    } else {
        ApiResponse::error('Invalid endpoint', 400);
    }
}

function validateSystemData($data) {
    $required = ['system_name', 'system_type', 'location', 'status'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            return false;
        }
    }

    $validTypes = ['hvac', 'electrical', 'plumbing', 'security', 'fire_safety', 'elevator', 'lighting', 'communication'];
    if (!in_array($data['system_type'], $validTypes)) {
        return false;
    }

    $validStatuses = ['operational', 'maintenance', 'faulty', 'offline'];
    if (!in_array($data['status'], $validStatuses)) {
        return false;
    }

    return true;
}

function validateSensorData($data) {
    $required = ['sensor_name', 'sensor_type', 'location', 'system_id'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            return false;
        }
    }

    $validTypes = ['temperature', 'humidity', 'pressure', 'motion', 'smoke', 'water_leak', 'power', 'vibration'];
    if (!in_array($data['sensor_type'], $validTypes)) {
        return false;
    }

    return true;
}

function validateReadingData($data) {
    $required = ['sensor_id', 'reading_value', 'unit'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            return false;
        }
    }

    return true;
}

function validateMaintenanceData($data) {
    $required = ['system_id', 'maintenance_type', 'scheduled_date', 'description'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            return false;
        }
    }

    $validTypes = ['preventive', 'corrective', 'predictive', 'emergency'];
    if (!in_array($data['maintenance_type'], $validTypes)) {
        return false;
    }

    return true;
}

function validateAlertData($data) {
    $required = ['system_id', 'alert_type', 'severity', 'message'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            return false;
        }
    }

    $validTypes = ['system_failure', 'sensor_malfunction', 'maintenance_required', 'power_outage', 'security_breach'];
    if (!in_array($data['alert_type'], $validTypes)) {
        return false;
    }

    $validSeverities = ['low', 'medium', 'high', 'critical'];
    if (!in_array($data['severity'], $validSeverities)) {
        return false;
    }

    return true;
}

function validateReportData($data) {
    $required = ['report_name', 'report_type', 'date_range'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            return false;
        }
    }

    $validTypes = ['system_status', 'maintenance_history', 'energy_consumption', 'sensor_performance', 'alert_summary'];
    if (!in_array($data['report_type'], $validTypes)) {
        return false;
    }

    return true;
}

?>
