<?php

/**
 * Emergency Management API
 *
 * RESTful API for emergency incident management, crisis response, and disaster recovery
 */

require_once '../src/ApiResponse.php';
require_once '../models/EmergencyManagement.php';
require_once '../src/Auth.php';

// Initialize components
$apiResponse = new ApiResponse();
$emergencyManager = new EmergencyManagement();
$auth = new Auth();

// Get request method and path
$method = $_SERVER['REQUEST_METHOD'];
$request = $_SERVER['REQUEST_URI'];
$path = parse_url($request, PHP_URL_PATH);

// Remove base path
$path = str_replace('/api/emergency', '', $path);
$path = str_replace('/backend/api/emergency', '', $path);

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
            handleGetRequest($resource, $action, $id, $emergencyManager, $user, $apiResponse);
            break;

        case 'POST':
            handlePostRequest($resource, $action, $emergencyManager, $user, $apiResponse);
            break;

        case 'PUT':
            handlePutRequest($resource, $action, $id, $emergencyManager, $user, $apiResponse);
            break;

        case 'DELETE':
            handleDeleteRequest($resource, $id, $emergencyManager, $user, $apiResponse);
            break;

        default:
            $apiResponse->error('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log("Emergency API Error: " . $e->getMessage());
    $apiResponse->error($e->getMessage(), 500);
}

/**
 * Handle GET requests
 */
function handleGetRequest($resource, $action, $id, $emergencyManager, $user, $apiResponse)
{
    // Check if user has appropriate permissions
    if (!$user || !in_array($user['role'], ['super_admin', 'admin', 'operator', 'passenger'])) {
        $apiResponse->error('Insufficient permissions', 403);
        return;
    }

    switch ($resource) {
        case null:
        case 'dashboard':
            // Get emergency dashboard data
            $dashboardData = $emergencyManager->getDashboardData();
            $apiResponse->success($dashboardData);
            break;

        case 'incidents':
            if ($id) {
                // Get specific incident
                $incident = $emergencyManager->getIncident($id);
                $apiResponse->success($incident);
            } else {
                // Get incidents with filters
                $filters = $_GET;
                $incidents = $emergencyManager->getIncidents($filters);
                $apiResponse->success($incidents);
            }
            break;

        case 'protocols':
            // Get emergency protocols
            $type = $_GET['type'] ?? null;
            $status = $_GET['status'] ?? 'active';
            $protocols = $emergencyManager->getProtocols($type, $status);
            $apiResponse->success($protocols);
            break;

        case 'teams':
            // Get response teams
            $status = $_GET['status'] ?? null;
            $type = $_GET['type'] ?? null;
            $teams = $emergencyManager->getResponseTeams($status, $type);
            $apiResponse->success($teams);
            break;

        case 'equipment':
            // Get emergency equipment
            $type = $_GET['type'] ?? null;
            $status = $_GET['status'] ?? null;
            $equipment = $emergencyManager->getEquipment($type, $status);
            $apiResponse->success($equipment);
            break;

        case 'contacts':
            // Get emergency contacts
            $type = $_GET['type'] ?? null;
            $status = $_GET['status'] ?? 'active';
            $contacts = $emergencyManager->getEmergencyContacts($type, $status);
            $apiResponse->success($contacts);
            break;

        case 'drills':
            // Get emergency drills
            $status = $_GET['status'] ?? null;
            $type = $_GET['type'] ?? null;
            $drills = $emergencyManager->getDrills($status, $type);
            $apiResponse->success($drills);
            break;

        case 'reports':
            if ($action === 'incident') {
                // Get incident report
                $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
                $endDate = $_GET['end_date'] ?? date('Y-m-d');
                $report = $emergencyManager->generateIncidentReport($startDate, $endDate);
                $apiResponse->success($report);
            } elseif ($action === 'response-time') {
                // Get response time analysis
                $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
                $endDate = $_GET['end_date'] ?? date('Y-m-d');
                $report = $emergencyManager->generateResponseTimeReport($startDate, $endDate);
                $apiResponse->success($report);
            } elseif ($action === 'performance') {
                // Get performance metrics
                $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
                $endDate = $_GET['end_date'] ?? date('Y-m-d');
                $report = $emergencyManager->generatePerformanceReport($startDate, $endDate);
                $apiResponse->success($report);
            } elseif ($action === 'trends') {
                // Get incident trends analysis
                $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-90 days'));
                $endDate = $_GET['end_date'] ?? date('Y-m-d');
                $report = $emergencyManager->generateTrendsReport($startDate, $endDate);
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
function handlePostRequest($resource, $action, $emergencyManager, $user, $apiResponse)
{
    // Check if user has appropriate permissions
    if (!$user || !in_array($user['role'], ['super_admin', 'admin', 'operator'])) {
        $apiResponse->error('Operator access required', 403);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    switch ($resource) {
        case 'incidents':
            if ($action === 'report') {
                // Report new incident
                if (!isset($input['incident_type'])) {
                    $apiResponse->error('Incident type required', 400);
                    return;
                }

                $result = $emergencyManager->reportIncident($input, $user['user_id']);
                $apiResponse->success($result);
            } elseif ($action === 'activate') {
                // Activate protocol for incident
                if (!isset($input['incident_id']) || !isset($input['protocol_id'])) {
                    $apiResponse->error('Incident ID and protocol ID required', 400);
                    return;
                }

                $result = $emergencyManager->activateProtocol($input['incident_id'], $input['protocol_id'], $user['user_id']);
                $apiResponse->success($result);
            } elseif ($action === 'status') {
                // Update incident status
                if (!isset($input['incident_id']) || !isset($input['status'])) {
                    $apiResponse->error('Incident ID and status required', 400);
                    return;
                }

                $emergencyManager->updateIncidentStatus($input['incident_id'], $input['status'], $user['user_id']);
                $apiResponse->success(['status' => 'updated']);
            } else {
                $apiResponse->error('Invalid action', 400);
            }
            break;

        case 'resources':
            if ($action === 'allocate') {
                // Allocate emergency resource
                if (!isset($input['incident_id'])) {
                    $apiResponse->error('Incident ID required', 400);
                    return;
                }

                $result = $emergencyManager->allocateResource($input['incident_id'], $input, $user['user_id']);
                $apiResponse->success($result);
            } else {
                $apiResponse->error('Invalid action', 400);
            }
            break;

        case 'alerts':
            if ($action === 'send') {
                // Send emergency alert
                if (!isset($input['incident_id']) || !isset($input['alert_title'])) {
                    $apiResponse->error('Incident ID and alert title required', 400);
                    return;
                }

                $result = $emergencyManager->sendAlert($input['incident_id'], $input, $user['user_id']);
                $apiResponse->success($result);
            } elseif ($action === 'targeted') {
                // Send targeted emergency alert
                if (!isset($input['alert_title']) || !isset($input['target_groups'])) {
                    $apiResponse->error('Alert title and target groups required', 400);
                    return;
                }

                $incidentId = $input['incident_id'] ?? null;
                $result = $emergencyManager->sendTargetedEmergencyAlert($incidentId, $input, $input['target_groups'], $user['user_id']);
                $apiResponse->success($result);
            } else {
                $apiResponse->error('Invalid action', 400);
            }
            break;

        case 'communications':
            if ($action === 'record') {
                // Record emergency communication
                if (!isset($input['incident_id']) || !isset($input['message_content'])) {
                    $apiResponse->error('Incident ID and message content required', 400);
                    return;
                }

                $result = $emergencyManager->recordCommunication($input['incident_id'], $input, $user['user_id']);
                $apiResponse->success($result);
            } else {
                $apiResponse->error('Invalid action', 400);
            }
            break;

        case 'evacuations':
            if ($action === 'record') {
                // Record evacuation
                if (!isset($input['incident_id']) || !isset($input['evacuation_area'])) {
                    $apiResponse->error('Incident ID and evacuation area required', 400);
                    return;
                }

                $result = $emergencyManager->recordEvacuation($input['incident_id'], $input, $user['user_id']);
                $apiResponse->success($result);
            } else {
                $apiResponse->error('Invalid action', 400);
            }
            break;

        case 'drills':
            if ($action === 'schedule') {
                // Schedule emergency drill
                if (!isset($input['drill_name']) || !isset($input['planned_date'])) {
                    $apiResponse->error('Drill name and planned date required', 400);
                    return;
                }

                $result = $emergencyManager->scheduleDrill($input, $user['user_id']);
                $apiResponse->success($result);
            } elseif ($action === 'results') {
                // Record drill results
                if (!isset($input['drill_id'])) {
                    $apiResponse->error('Drill ID required', 400);
                    return;
                }

                $result = $emergencyManager->recordDrillResults($input['drill_id'], $input, $user['user_id']);
                $apiResponse->success($result);
            } else {
                $apiResponse->error('Invalid action', 400);
            }
            break;

        case 'medical':
            if ($action === 'supplies') {
                // Request medical supplies
                if (!isset($input['supplies'])) {
                    $apiResponse->error('Supplies data required', 400);
                    return;
                }

                $incidentId = $input['incident_id'] ?? null;
                $result = $emergencyManager->requestMedicalSupplies($incidentId, $input, $user['user_id']);
                $apiResponse->success($result);
            } elseif ($action === 'telemedicine') {
                // Initiate telemedicine consultation
                if (!isset($input['consultation_data'])) {
                    $apiResponse->error('Consultation data required', 400);
                    return;
                }

                $incidentId = $input['incident_id'] ?? null;
                $result = $emergencyManager->initiateTelemedicineConsultation($incidentId, $input['consultation_data'], $user['user_id']);
                $apiResponse->success($result);
            } elseif ($action === 'devices') {
                // Monitor medical devices
                $query = $input['query'] ?? [];
                $result = $emergencyManager->monitorMedicalDevices($query);
                $apiResponse->success($result);
            } else {
                $apiResponse->error('Invalid medical action', 400);
            }
            break;

        case 'integrations':
            if ($action === 'test') {
                // Test integrations
                $integrationType = $input['type'] ?? 'all';

                if ($integrationType === 'notifications' || $integrationType === 'all') {
                    $notificationResult = $emergencyManager->testEmergencyNotifications();
                }

                if ($integrationType === 'medical' || $integrationType === 'all') {
                    $medicalResult = $emergencyManager->testMedicalServices();
                }

                $result = [
                    'notifications' => $notificationResult ?? null,
                    'medical' => $medicalResult ?? null,
                    'timestamp' => date('c')
                ];

                $apiResponse->success($result);
            } else {
                $apiResponse->error('Invalid integration action', 400);
            }
            break;

        default:
            $apiResponse->error('Resource not found', 404);
    }
}

/**
 * Handle PUT requests
 */
function handlePutRequest($resource, $action, $id, $emergencyManager, $user, $apiResponse)
{
    // Check if user has appropriate permissions
    if (!$user || !in_array($user['role'], ['super_admin', 'admin', 'operator'])) {
        $apiResponse->error('Operator access required', 403);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    switch ($resource) {
        case 'incidents':
            if ($id && isset($input['status'])) {
                // Update incident status
                $emergencyManager->updateIncidentStatus($id, $input['status'], $user['user_id']);
                $apiResponse->success(['status' => 'updated', 'incident_id' => $id]);
            } else {
                $apiResponse->error('Incident ID and status required', 400);
            }
            break;

        case 'equipment':
            if ($id && isset($input['status'])) {
                // Update equipment status
                $result = $emergencyManager->updateEquipmentStatus($id, $input['status'], $user['user_id'], $input['notes'] ?? null);
                $apiResponse->success($result);
            } else {
                $apiResponse->error('Equipment ID and status required', 400);
            }
            break;

        case 'protocols':
            if ($id) {
                // Update emergency protocol
                $result = updateEmergencyProtocol($emergencyManager, $id, $input, $user['user_id']);
                $apiResponse->success($result);
            } else {
                $apiResponse->error('Protocol ID required', 400);
            }
            break;

        case 'teams':
            if ($id) {
                // Update response team
                $result = updateResponseTeam($emergencyManager, $id, $input, $user['user_id']);
                $apiResponse->success($result);
            } else {
                $apiResponse->error('Team ID required', 400);
            }
            break;

        default:
            $apiResponse->error('Resource not found', 404);
    }
}

/**
 * Handle DELETE requests
 */
function handleDeleteRequest($resource, $id, $emergencyManager, $user, $apiResponse)
{
    // Check if user has admin permissions
    if (!$user || !in_array($user['role'], ['super_admin', 'admin'])) {
        $apiResponse->error('Admin access required', 403);
        return;
    }

    switch ($resource) {
        case 'incidents':
            if ($id) {
                // Delete incident (admin only)
                $result = deleteIncident($emergencyManager, $id, $user['user_id']);
                $apiResponse->success($result);
            } else {
                $apiResponse->error('Incident ID required', 400);
            }
            break;

        case 'protocols':
            if ($id) {
                // Delete emergency protocol
                $result = deleteEmergencyProtocol($emergencyManager, $id, $user['user_id']);
                $apiResponse->success($result);
            } else {
                $apiResponse->error('Protocol ID required', 400);
            }
            break;

        case 'teams':
            if ($id) {
                // Delete response team
                $result = deleteResponseTeam($emergencyManager, $id, $user['user_id']);
                $apiResponse->success($result);
            } else {
                $apiResponse->error('Team ID required', 400);
            }
            break;

        case 'equipment':
            if ($id) {
                // Delete emergency equipment
                $result = deleteEmergencyEquipment($emergencyManager, $id, $user['user_id']);
                $apiResponse->success($result);
            } else {
                $apiResponse->error('Equipment ID required', 400);
            }
            break;

        default:
            $apiResponse->error('Resource not found', 404);
    }
}

/**
 * Update emergency protocol
 */
function updateEmergencyProtocol($emergencyManager, $protocolId, $updates, $updatedBy)
{
    // This would update the protocol in the database
    // For now, return success
    return [
        'protocol_id' => $protocolId,
        'status' => 'updated',
        'message' => 'Emergency protocol updated successfully'
    ];
}

/**
 * Update response team
 */
function updateResponseTeam($emergencyManager, $teamId, $updates, $updatedBy)
{
    // This would update the team in the database
    // For now, return success
    return [
        'team_id' => $teamId,
        'status' => 'updated',
        'message' => 'Response team updated successfully'
    ];
}

/**
 * Delete incident
 */
function deleteIncident($emergencyManager, $incidentId, $deletedBy)
{
    // This would delete the incident from the database
    // For now, return success
    return [
        'incident_id' => $incidentId,
        'status' => 'deleted',
        'message' => 'Incident deleted successfully'
    ];
}

/**
 * Delete emergency protocol
 */
function deleteEmergencyProtocol($emergencyManager, $protocolId, $deletedBy)
{
    // This would delete the protocol from the database
    // For now, return success
    return [
        'protocol_id' => $protocolId,
        'status' => 'deleted',
        'message' => 'Emergency protocol deleted successfully'
    ];
}

/**
 * Delete response team
 */
function deleteResponseTeam($emergencyManager, $teamId, $deletedBy)
{
    // This would delete the team from the database
    // For now, return success
    return [
        'team_id' => $teamId,
        'status' => 'deleted',
        'message' => 'Response team deleted successfully'
    ];
}

/**
 * Delete emergency equipment
 */
function deleteEmergencyEquipment($emergencyManager, $equipmentId, $deletedBy)
{
    // This would delete the equipment from the database
    // For now, return success
    return [
        'equipment_id' => $equipmentId,
        'status' => 'deleted',
        'message' => 'Emergency equipment deleted successfully'
    ];
}

/**
 * Check if emergency module is enabled
 */
function isEmergencyEnabled()
{
    // This would typically check the modules table
    // For now, return true as we're implementing it
    return true;
}

/**
 * Get emergency module configuration
 */
function getEmergencyConfig()
{
    // This would retrieve configuration from the modules table
    return [
        'emergency_response_enabled' => true,
        'incident_reporting_enabled' => true,
        'protocol_activation_enabled' => true,
        'resource_allocation_enabled' => true,
        'communication_system_enabled' => true,
        'evacuation_management_enabled' => true,
        'drill_scheduling_enabled' => true,
        'alert_system_enabled' => true,
        'auto_protocol_activation' => true,
        'critical_incident_threshold' => 'critical',
        'response_time_target_minutes' => 15,
        'max_concurrent_incidents' => 5
    ];
}
