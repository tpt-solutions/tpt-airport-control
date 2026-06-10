<?php

require_once __DIR__ . '/cors.php';
/**
 * Drone Operations API
 *
 * Handles UAV airspace coordination, flight planning, real-time tracking,
 * and regulatory compliance for drone operations management
 */

require_once '../src/Config.php';
require_once '../src/ApiResponse.php';
require_once '../models/DroneOperations.php';
require_once '../services/DroneOperationsService.php';

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
$path = str_replace('/backend/api/drones', '', $path);
$pathParts = explode('/', trim($path, '/'));

$droneService = new DroneOperationsService();

try {
    switch ($method) {
        case 'GET':
            handleGet($pathParts, $droneService);
            break;
        case 'POST':
            handlePost($pathParts, $droneService);
            break;
        case 'PUT':
            handlePut($pathParts, $droneService);
            break;
        case 'DELETE':
            handleDelete($pathParts, $droneService);
            break;
        default:
            ApiResponse::error('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log("Drone Operations API Error: " . $e->getMessage());
    ApiResponse::error('Internal server error', 500);
}

function handleGet($pathParts, $service) {
    if (empty($pathParts[0])) {
        // Get all drones with optional filters
        $filters = $_GET;
        $drones = $service->getAllDrones($filters);
        ApiResponse::success($drones);
    } elseif ($pathParts[0] === 'drones' && isset($pathParts[1])) {
        // Get specific drone
        $droneId = $pathParts[1];
        $drone = $service->getDroneById($droneId);
        if ($drone) {
            ApiResponse::success($drone);
        } else {
            ApiResponse::error('Drone not found', 404);
        }
    } elseif ($pathParts[0] === 'pilots') {
        // Get drone pilots
        $filters = $_GET;
        $pilots = $service->getDronePilots($filters);
        ApiResponse::success($pilots);
    } elseif ($pathParts[0] === 'pilots' && isset($pathParts[1])) {
        // Get specific pilot
        $pilotId = $pathParts[1];
        $pilot = $service->getPilotById($pilotId);
        if ($pilot) {
            ApiResponse::success($pilot);
        } else {
            ApiResponse::error('Pilot not found', 404);
        }
    } elseif ($pathParts[0] === 'plans') {
        // Get flight plans
        $filters = $_GET;
        $plans = $service->getFlightPlans($filters);
        ApiResponse::success($plans);
    } elseif ($pathParts[0] === 'plans' && isset($pathParts[1])) {
        // Get specific flight plan
        $planId = $pathParts[1];
        $plan = $service->getFlightPlanById($planId);
        if ($plan) {
            ApiResponse::success($plan);
        } else {
            ApiResponse::error('Flight plan not found', 404);
        }
    } elseif ($pathParts[0] === 'reservations') {
        // Get airspace reservations
        $filters = $_GET;
        $reservations = $service->getAirspaceReservations($filters);
        ApiResponse::success($reservations);
    } elseif ($pathParts[0] === 'telemetry') {
        // Get drone telemetry data
        $filters = $_GET;
        $telemetry = $service->getDroneTelemetry($filters);
        ApiResponse::success($telemetry);
    } elseif ($pathParts[0] === 'incidents') {
        // Get drone incidents
        $filters = $_GET;
        $incidents = $service->getDroneIncidents($filters);
        ApiResponse::success($incidents);
    } elseif ($pathParts[0] === 'airspace') {
        // Get airspace status
        $filters = $_GET;
        $airspace = $service->getAirspaceStatus($filters);
        ApiResponse::success($airspace);
    } elseif ($pathParts[0] === 'maintenance') {
        // Get maintenance records
        $filters = $_GET;
        $maintenance = $service->getMaintenanceRecords($filters);
        ApiResponse::success($maintenance);
    } elseif ($pathParts[0] === 'stats') {
        // Get drone operations statistics
        $stats = $service->getDroneStatistics();
        ApiResponse::success($stats);
    } else {
        ApiResponse::error('Invalid endpoint', 400);
    }
}

function handlePost($pathParts, $service) {
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($pathParts[0])) {
        // Register new drone
        if (!validateDroneData($data)) {
            ApiResponse::error('Invalid drone data', 400);
            return;
        }

        $droneId = $service->registerDrone($data);
        ApiResponse::success(['drone_id' => $droneId, 'message' => 'Drone registered successfully'], 201);
    } elseif ($pathParts[0] === 'pilots') {
        // Register new pilot
        if (!validatePilotData($data)) {
            ApiResponse::error('Invalid pilot data', 400);
            return;
        }

        $pilotId = $service->registerPilot($data);
        ApiResponse::success(['pilot_id' => $pilotId, 'message' => 'Pilot registered successfully'], 201);
    } elseif ($pathParts[0] === 'plans') {
        // Submit flight plan
        if (!validateFlightPlanData($data)) {
            ApiResponse::error('Invalid flight plan data', 400);
            return;
        }

        $planId = $service->submitFlightPlan($data);
        ApiResponse::success(['plan_id' => $planId, 'message' => 'Flight plan submitted successfully'], 201);
    } elseif ($pathParts[0] === 'reservations') {
        // Reserve airspace
        if (!validateReservationData($data)) {
            ApiResponse::error('Invalid reservation data', 400);
            return;
        }

        $reservationId = $service->reserveAirspace($data);
        ApiResponse::success(['reservation_id' => $reservationId, 'message' => 'Airspace reserved successfully'], 201);
    } elseif ($pathParts[0] === 'telemetry') {
        // Record telemetry data
        if (!validateTelemetryData($data)) {
            ApiResponse::error('Invalid telemetry data', 400);
            return;
        }

        $telemetryId = $service->recordTelemetry($data);
        ApiResponse::success(['telemetry_id' => $telemetryId, 'message' => 'Telemetry recorded successfully'], 201);
    } elseif ($pathParts[0] === 'incidents') {
        // Report incident
        if (!validateIncidentData($data)) {
            ApiResponse::error('Invalid incident data', 400);
            return;
        }

        $incidentId = $service->reportIncident($data);
        ApiResponse::success(['incident_id' => $incidentId, 'message' => 'Incident reported successfully'], 201);
    } elseif ($pathParts[0] === 'maintenance') {
        // Schedule maintenance
        if (!validateMaintenanceData($data)) {
            ApiResponse::error('Invalid maintenance data', 400);
            return;
        }

        $maintenanceId = $service->scheduleMaintenance($data);
        ApiResponse::success(['maintenance_id' => $maintenanceId, 'message' => 'Maintenance scheduled successfully'], 201);
    } else {
        ApiResponse::error('Invalid endpoint', 400);
    }
}

function handlePut($pathParts, $service) {
    $data = json_decode(file_get_contents('php://input'), true);

    if ($pathParts[0] === 'drones' && isset($pathParts[1])) {
        // Update drone
        $droneId = $pathParts[1];
        if (!$service->getDroneById($droneId)) {
            ApiResponse::error('Drone not found', 404);
            return;
        }

        $result = $service->updateDrone($droneId, $data);
        ApiResponse::success(['message' => 'Drone updated successfully']);
    } elseif ($pathParts[0] === 'pilots' && isset($pathParts[1])) {
        // Update pilot
        $pilotId = $pathParts[1];
        $result = $service->updatePilot($pilotId, $data);
        ApiResponse::success(['message' => 'Pilot updated successfully']);
    } elseif ($pathParts[0] === 'plans' && isset($pathParts[1])) {
        // Update flight plan
        $planId = $pathParts[1];
        $result = $service->updateFlightPlan($planId, $data);
        ApiResponse::success(['message' => 'Flight plan updated successfully']);
    } elseif ($pathParts[0] === 'reservations' && isset($pathParts[1])) {
        // Update reservation
        $reservationId = $pathParts[1];
        $result = $service->updateReservation($reservationId, $data);
        ApiResponse::success(['message' => 'Reservation updated successfully']);
    } elseif ($pathParts[0] === 'maintenance' && isset($pathParts[1])) {
        // Update maintenance
        $maintenanceId = $pathParts[1];
        $result = $service->updateMaintenance($maintenanceId, $data);
        ApiResponse::success(['message' => 'Maintenance updated successfully']);
    } else {
        ApiResponse::error('Invalid endpoint', 400);
    }
}

function handleDelete($pathParts, $service) {
    if ($pathParts[0] === 'drones' && isset($pathParts[1])) {
        // Delete drone
        $droneId = $pathParts[1];
        $result = $service->deleteDrone($droneId);
        ApiResponse::success(['message' => 'Drone deleted successfully']);
    } elseif ($pathParts[0] === 'pilots' && isset($pathParts[1])) {
        // Delete pilot
        $pilotId = $pathParts[1];
        $result = $service->deletePilot($pilotId);
        ApiResponse::success(['message' => 'Pilot deleted successfully']);
    } elseif ($pathParts[0] === 'plans' && isset($pathParts[1])) {
        // Delete flight plan
        $planId = $pathParts[1];
        $result = $service->deleteFlightPlan($planId);
        ApiResponse::success(['message' => 'Flight plan deleted successfully']);
    } elseif ($pathParts[0] === 'reservations' && isset($pathParts[1])) {
        // Delete reservation
        $reservationId = $pathParts[1];
        $result = $service->deleteReservation($reservationId);
        ApiResponse::success(['message' => 'Reservation deleted successfully']);
    } elseif ($pathParts[0] === 'maintenance' && isset($pathParts[1])) {
        // Delete maintenance
        $maintenanceId = $pathParts[1];
        $result = $service->deleteMaintenance($maintenanceId);
        ApiResponse::success(['message' => 'Maintenance deleted successfully']);
    } else {
        ApiResponse::error('Invalid endpoint', 400);
    }
}

function validateDroneData($data) {
    $required = ['drone_model', 'serial_number', 'operator_id', 'max_altitude', 'max_speed'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            return false;
        }
    }

    $validStatuses = ['active', 'maintenance', 'grounded', 'retired'];
    if (isset($data['status']) && !in_array($data['status'], $validStatuses)) {
        return false;
    }

    return true;
}

function validatePilotData($data) {
    $required = ['pilot_name', 'license_number', 'certification_level', 'contact_info'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            return false;
        }
    }

    $validLevels = ['recreational', 'commercial', 'instructor', 'test_pilot'];
    if (!in_array($data['certification_level'], $validLevels)) {
        return false;
    }

    return true;
}

function validateFlightPlanData($data) {
    $required = ['drone_id', 'pilot_id', 'start_time', 'end_time', 'flight_path', 'purpose'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            return false;
        }
    }

    if (strtotime($data['start_time']) >= strtotime($data['end_time'])) {
        return false;
    }

    $validPurposes = ['aerial_photography', 'surveying', 'delivery', 'inspection', 'recreational', 'commercial'];
    if (!in_array($data['purpose'], $validPurposes)) {
        return false;
    }

    return true;
}

function validateReservationData($data) {
    $required = ['operator_id', 'start_time', 'end_time', 'altitude_min', 'altitude_max', 'purpose'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            return false;
        }
    }

    if (strtotime($data['start_time']) >= strtotime($data['end_time'])) {
        return false;
    }

    if ($data['altitude_min'] >= $data['altitude_max']) {
        return false;
    }

    return true;
}

function validateTelemetryData($data) {
    $required = ['drone_id', 'latitude', 'longitude', 'altitude', 'speed', 'heading'];
    foreach ($required as $field) {
        if (!isset($data[$field])) {
            return false;
        }
    }

    // Validate coordinates
    if ($data['latitude'] < -90 || $data['latitude'] > 90) {
        return false;
    }
    if ($data['longitude'] < -180 || $data['longitude'] > 180) {
        return false;
    }

    return true;
}

function validateIncidentData($data) {
    $required = ['drone_id', 'incident_type', 'description', 'location'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            return false;
        }
    }

    $validTypes = ['collision', 'loss_of_control', 'communication_failure', 'battery_failure', 'weather_related', 'other'];
    if (!in_array($data['incident_type'], $validTypes)) {
        return false;
    }

    return true;
}

function validateMaintenanceData($data) {
    $required = ['drone_id', 'maintenance_type', 'scheduled_date', 'description'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            return false;
        }
    }

    $validTypes = ['routine', 'repair', 'inspection', 'software_update', 'calibration'];
    if (!in_array($data['maintenance_type'], $validTypes)) {
        return false;
    }

    return true;
}

?>
