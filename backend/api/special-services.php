<?php

/**
 * Special Services API
 *
 * Handles special assistance requests, accessibility services, medical equipment tracking,
 * and language services coordination for passengers with special needs
 */

require_once '../src/Config.php';
require_once '../src/ApiResponse.php';
require_once '../models/SpecialServices.php';
require_once '../services/SpecialServicesService.php';

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
$path = str_replace('/backend/api/special-services', '', $path);
$pathParts = explode('/', trim($path, '/'));

$specialServicesService = new SpecialServicesService();

try {
    switch ($method) {
        case 'GET':
            handleGet($pathParts, $specialServicesService);
            break;
        case 'POST':
            handlePost($pathParts, $specialServicesService);
            break;
        case 'PUT':
            handlePut($pathParts, $specialServicesService);
            break;
        case 'DELETE':
            handleDelete($pathParts, $specialServicesService);
            break;
        default:
            ApiResponse::error('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log("Special Services API Error: " . $e->getMessage());
    ApiResponse::error('Internal server error', 500);
}

function handleGet($pathParts, $service) {
    if (empty($pathParts[0])) {
        // Get all special services requests
        $filters = $_GET;
        $requests = $service->getAllRequests($filters);
        ApiResponse::success($requests);
    } elseif ($pathParts[0] === 'requests' && isset($pathParts[1])) {
        // Get specific request
        $requestId = $pathParts[1];
        $request = $service->getRequestById($requestId);
        if ($request) {
            ApiResponse::success($request);
        } else {
            ApiResponse::error('Request not found', 404);
        }
    } elseif ($pathParts[0] === 'types') {
        // Get available service types
        $types = $service->getServiceTypes();
        ApiResponse::success($types);
    } elseif ($pathParts[0] === 'equipment') {
        // Get medical equipment availability
        $equipment = $service->getMedicalEquipment();
        ApiResponse::success($equipment);
    } elseif ($pathParts[0] === 'languages') {
        // Get available language services
        $languages = $service->getLanguageServices();
        ApiResponse::success($languages);
    } elseif ($pathParts[0] === 'stats') {
        // Get special services statistics
        $stats = $service->getServiceStatistics();
        ApiResponse::success($stats);
    } elseif ($pathParts[0] === 'reports') {
        if (isset($pathParts[1]) && $pathParts[1] === 'utilization') {
            // Get service utilization report
            $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
            $endDate = $_GET['end_date'] ?? date('Y-m-d');
            $report = getServiceUtilizationReport($service, $startDate, $endDate);
            ApiResponse::success($report);
        } elseif (isset($pathParts[1]) && $pathParts[1] === 'accessibility') {
            // Get accessibility compliance report
            $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-90 days'));
            $endDate = $_GET['end_date'] ?? date('Y-m-d');
            $report = getAccessibilityComplianceReport($service, $startDate, $endDate);
            ApiResponse::success($report);
        } else {
            ApiResponse::error('Invalid report type', 400);
        }
    } else {
        ApiResponse::error('Invalid endpoint', 400);
    }
}

function handlePost($pathParts, $service) {
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($pathParts[0])) {
        // Create new special services request
        if (!validateRequestData($data)) {
            ApiResponse::error('Invalid request data', 400);
            return;
        }

        $requestId = $service->createRequest($data);
        ApiResponse::success(['request_id' => $requestId, 'message' => 'Special services request created successfully'], 201);
    } elseif ($pathParts[0] === 'requests' && isset($pathParts[1]) && $pathParts[1] === 'assign') {
        // Assign request to staff member
        if (!isset($data['request_id']) || !isset($data['staff_id'])) {
            ApiResponse::error('Request ID and staff ID required', 400);
            return;
        }

        $result = $service->assignRequest($data['request_id'], $data['staff_id']);
        ApiResponse::success(['message' => 'Request assigned successfully']);
    } elseif ($pathParts[0] === 'equipment') {
        // Add medical equipment
        if (!validateEquipmentData($data)) {
            ApiResponse::error('Invalid equipment data', 400);
            return;
        }

        $equipmentId = $service->addMedicalEquipment($data);
        ApiResponse::success(['equipment_id' => $equipmentId, 'message' => 'Medical equipment added successfully'], 201);
    } elseif ($pathParts[0] === 'languages') {
        // Add language service provider
        if (!validateLanguageData($data)) {
            ApiResponse::error('Invalid language service data', 400);
            return;
        }

        $providerId = $service->addLanguageProvider($data);
        ApiResponse::success(['provider_id' => $providerId, 'message' => 'Language service provider added successfully'], 201);
    } else {
        ApiResponse::error('Invalid endpoint', 400);
    }
}

function handlePut($pathParts, $service) {
    $data = json_decode(file_get_contents('php://input'), true);

    if ($pathParts[0] === 'requests' && isset($pathParts[1])) {
        // Update special services request
        $requestId = $pathParts[1];
        if (!$service->getRequestById($requestId)) {
            ApiResponse::error('Request not found', 404);
            return;
        }

        $result = $service->updateRequest($requestId, $data);
        ApiResponse::success(['message' => 'Request updated successfully']);
    } elseif ($pathParts[0] === 'equipment' && isset($pathParts[1])) {
        // Update medical equipment
        $equipmentId = $pathParts[1];
        $result = $service->updateMedicalEquipment($equipmentId, $data);
        ApiResponse::success(['message' => 'Medical equipment updated successfully']);
    } elseif ($pathParts[0] === 'languages' && isset($pathParts[1])) {
        // Update language service provider
        $providerId = $pathParts[1];
        $result = $service->updateLanguageProvider($providerId, $data);
        ApiResponse::success(['message' => 'Language service provider updated successfully']);
    } else {
        ApiResponse::error('Invalid endpoint', 400);
    }
}

function handleDelete($pathParts, $service) {
    if ($pathParts[0] === 'requests' && isset($pathParts[1])) {
        // Delete special services request
        $requestId = $pathParts[1];
        $result = $service->deleteRequest($requestId);
        ApiResponse::success(['message' => 'Request deleted successfully']);
    } elseif ($pathParts[0] === 'equipment' && isset($pathParts[1])) {
        // Delete medical equipment
        $equipmentId = $pathParts[1];
        $result = $service->deleteMedicalEquipment($equipmentId);
        ApiResponse::success(['message' => 'Medical equipment deleted successfully']);
    } elseif ($pathParts[0] === 'languages' && isset($pathParts[1])) {
        // Delete language service provider
        $providerId = $pathParts[1];
        $result = $service->deleteLanguageProvider($providerId);
        ApiResponse::success(['message' => 'Language service provider deleted successfully']);
    } else {
        ApiResponse::error('Invalid endpoint', 400);
    }
}

function validateRequestData($data) {
    $required = ['passenger_id', 'service_type', 'request_details'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            return false;
        }
    }

    $validTypes = ['wheelchair', 'medical_assistance', 'visual_impairment', 'hearing_impairment',
                   'mobility_aid', 'oxygen_support', 'language_assistance', 'unaccompanied_minor'];
    if (!in_array($data['service_type'], $validTypes)) {
        return false;
    }

    return true;
}

function validateEquipmentData($data) {
    $required = ['equipment_type', 'location', 'status'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            return false;
        }
    }

    $validStatuses = ['available', 'in_use', 'maintenance', 'out_of_service'];
    if (!in_array($data['status'], $validStatuses)) {
        return false;
    }

    return true;
}

function validateLanguageData($data) {
    $required = ['provider_name', 'languages', 'availability_status'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            return false;
        }
    }

    $validStatuses = ['available', 'busy', 'off_duty'];
    if (!in_array($data['availability_status'], $validStatuses)) {
        return false;
    }

    return true;
}

/**
 * Get service utilization report
 */
function getServiceUtilizationReport($service, $startDate, $endDate)
{
    try {
        // Get database connection from service
        $db = $service->getDatabaseConnection();

        // Get service requests by type and time period
        $stmt = $db->prepare("
            SELECT
                DATE_TRUNC('month', created_at) as period,
                service_type,
                COUNT(*) as total_requests,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_requests,
                COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress_requests,
                COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_requests,
                ROUND(AVG(CASE WHEN completion_time IS NOT NULL THEN
                    EXTRACT(EPOCH FROM (completion_time - created_at))/3600 END), 2) as avg_completion_hours
            FROM special_services_requests
            WHERE created_at BETWEEN ? AND ?
            GROUP BY DATE_TRUNC('month', created_at), service_type
            ORDER BY period, service_type
        ");
        $stmt->execute([$startDate, $endDate]);
        $serviceUtilization = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get daily request volume
        $stmt = $db->prepare("
            SELECT
                DATE(created_at) as date,
                COUNT(*) as daily_requests,
                COUNT(DISTINCT passenger_id) as unique_passengers
            FROM special_services_requests
            WHERE created_at BETWEEN ? AND ?
            GROUP BY DATE(created_at)
            ORDER BY date
        ");
        $stmt->execute([$startDate, $endDate]);
        $dailyVolume = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get service type distribution
        $stmt = $db->prepare("
            SELECT
                service_type,
                COUNT(*) as request_count,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completion_rate,
                ROUND(AVG(priority_level), 2) as avg_priority
            FROM special_services_requests
            WHERE created_at BETWEEN ? AND ?
            GROUP BY service_type
            ORDER BY request_count DESC
        ");
        $stmt->execute([$startDate, $endDate]);
        $serviceDistribution = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get staff workload analysis
        $stmt = $db->prepare("
            SELECT
                assigned_staff_id,
                COUNT(*) as assigned_requests,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_by_staff,
                ROUND(AVG(CASE WHEN completion_time IS NOT NULL THEN
                    EXTRACT(EPOCH FROM (completion_time - created_at))/3600 END), 2) as avg_resolution_time
            FROM special_services_requests
            WHERE created_at BETWEEN ? AND ?
            AND assigned_staff_id IS NOT NULL
            GROUP BY assigned_staff_id
            ORDER BY assigned_requests DESC
        ");
        $stmt->execute([$startDate, $endDate]);
        $staffWorkload = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate overall metrics
        $totalRequests = array_sum(array_column($serviceUtilization, 'total_requests'));
        $completedRequests = array_sum(array_column($serviceUtilization, 'completed_requests'));
        $completionRate = $totalRequests > 0 ? round(($completedRequests / $totalRequests) * 100, 2) : 0;
        $avgCompletionTime = array_filter(array_column($serviceUtilization, 'avg_completion_hours'));
        $avgCompletionTime = count($avgCompletionTime) > 0 ? round(array_sum($avgCompletionTime) / count($avgCompletionTime), 2) : 0;

        return [
            'report_type' => 'service_utilization',
            'period' => ['start_date' => $startDate, 'end_date' => $endDate],
            'summary' => [
                'total_requests' => $totalRequests,
                'completed_requests' => $completedRequests,
                'completion_rate_percent' => $completionRate,
                'avg_completion_hours' => $avgCompletionTime,
                'unique_passengers_served' => count(array_unique(array_column($dailyVolume, 'unique_passengers'))),
                'peak_daily_requests' => max(array_column($dailyVolume, 'daily_requests')) ?? 0
            ],
            'monthly_service_utilization' => $serviceUtilization,
            'daily_request_volume' => $dailyVolume,
            'service_type_distribution' => $serviceDistribution,
            'staff_workload_analysis' => $staffWorkload,
            'generated_at' => date('c')
        ];

    } catch (Exception $e) {
        error_log("Service utilization report error: " . $e->getMessage());
        return [
            'report_type' => 'service_utilization',
            'period' => ['start_date' => $startDate, 'end_date' => $endDate],
            'error' => 'Failed to generate service utilization report',
            'summary' => [
                'total_requests' => 0,
                'completion_rate_percent' => 0,
                'avg_completion_hours' => 0
            ],
            'data' => []
        ];
    }
}

/**
 * Get accessibility compliance report
 */
function getAccessibilityComplianceReport($service, $startDate, $endDate)
{
    try {
        // Get database connection from service
        $db = $service->getDatabaseConnection();

        // Get accessibility compliance metrics
        $stmt = $db->prepare("
            SELECT
                DATE_TRUNC('month', created_at) as period,
                service_type,
                COUNT(*) as total_requests,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_requests,
                COUNT(CASE WHEN status = 'completed' AND completion_time <= requested_time THEN 1 END) as on_time_completions,
                COUNT(CASE WHEN status = 'completed' AND completion_time > requested_time THEN 1 END) as delayed_completions,
                ROUND(AVG(CASE WHEN completion_time IS NOT NULL THEN
                    EXTRACT(EPOCH FROM (completion_time - requested_time))/3600 END), 2) as avg_response_time_hours
            FROM special_services_requests
            WHERE created_at BETWEEN ? AND ?
            AND service_type IN ('wheelchair', 'visual_impairment', 'hearing_impairment', 'mobility_aid')
            GROUP BY DATE_TRUNC('month', created_at), service_type
            ORDER BY period, service_type
        ");
        $stmt->execute([$startDate, $endDate]);
        $accessibilityMetrics = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get equipment availability and usage
        $stmt = $db->prepare("
            SELECT
                equipment_type,
                COUNT(*) as total_units,
                COUNT(CASE WHEN status = 'available' THEN 1 END) as available_units,
                COUNT(CASE WHEN status = 'in_use' THEN 1 END) as in_use_units,
                COUNT(CASE WHEN status = 'maintenance' THEN 1 END) as maintenance_units,
                ROUND(
                    CASE
                        WHEN COUNT(*) > 0
                        THEN (COUNT(CASE WHEN status = 'available' THEN 1 END) * 100.0 / COUNT(*))
                        ELSE 0
                    END, 2
                ) as availability_rate
            FROM medical_equipment
            GROUP BY equipment_type
            ORDER BY equipment_type
        ");
        $stmt->execute();
        $equipmentAvailability = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get language service utilization
        $stmt = $db->prepare("
            SELECT
                lp.languages,
                COUNT(*) as total_requests,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_requests,
                lp.availability_status
            FROM language_providers lp
            LEFT JOIN special_services_requests ssr ON ssr.service_type = 'language_assistance'
            AND ssr.created_at BETWEEN ? AND ?
            GROUP BY lp.provider_id, lp.languages, lp.availability_status
            ORDER BY total_requests DESC
        ");
        $stmt->execute([$startDate, $endDate]);
        $languageServices = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get accessibility compliance standards
        $stmt = $db->prepare("
            SELECT
                standard_name,
                compliance_level,
                last_audit_date,
                next_audit_date,
                audit_findings,
                corrective_actions_required
            FROM accessibility_standards
            ORDER BY next_audit_date ASC
        ");
        $stmt->execute();
        $complianceStandards = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get passenger feedback on accessibility services
        $stmt = $db->prepare("
            SELECT
                service_type,
                ROUND(AVG(satisfaction_rating), 2) as avg_satisfaction,
                COUNT(CASE WHEN satisfaction_rating >= 4 THEN 1 END) as satisfied_count,
                COUNT(CASE WHEN satisfaction_rating < 3 THEN 1 END) as dissatisfied_count,
                COUNT(*) as total_feedback
            FROM special_services_feedback
            WHERE created_at BETWEEN ? AND ?
            GROUP BY service_type
            ORDER BY avg_satisfaction DESC
        ");
        $stmt->execute([$startDate, $endDate]);
        $passengerFeedback = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate compliance metrics
        $totalAccessibilityRequests = array_sum(array_column($accessibilityMetrics, 'total_requests'));
        $onTimeCompletions = array_sum(array_column($accessibilityMetrics, 'on_time_completions'));
        $accessibilityComplianceRate = $totalAccessibilityRequests > 0 ? round(($onTimeCompletions / $totalAccessibilityRequests) * 100, 2) : 0;

        $avgResponseTime = array_filter(array_column($accessibilityMetrics, 'avg_response_time_hours'));
        $avgResponseTime = count($avgResponseTime) > 0 ? round(array_sum($avgResponseTime) / count($avgResponseTime), 2) : 0;

        $equipmentAvailabilityRate = count($equipmentAvailability) > 0 ? round(array_sum(array_column($equipmentAvailability, 'availability_rate')) / count($equipmentAvailability), 2) : 0;

        return [
            'report_type' => 'accessibility_compliance',
            'period' => ['start_date' => $startDate, 'end_date' => $endDate],
            'summary' => [
                'total_accessibility_requests' => $totalAccessibilityRequests,
                'accessibility_compliance_rate_percent' => $accessibilityComplianceRate,
                'avg_response_time_hours' => $avgResponseTime,
                'equipment_availability_rate_percent' => $equipmentAvailabilityRate,
                'active_language_providers' => count(array_filter($languageServices, function($provider) {
                    return $provider['availability_status'] === 'available';
                })),
                'standards_compliance_score' => calculateStandardsComplianceScore($complianceStandards)
            ],
            'accessibility_service_metrics' => $accessibilityMetrics,
            'equipment_availability' => $equipmentAvailability,
            'language_service_utilization' => $languageServices,
            'compliance_standards' => $complianceStandards,
            'passenger_feedback' => $passengerFeedback,
            'generated_at' => date('c')
        ];

    } catch (Exception $e) {
        error_log("Accessibility compliance report error: " . $e->getMessage());
        return [
            'report_type' => 'accessibility_compliance',
            'period' => ['start_date' => $startDate, 'end_date' => $endDate],
            'error' => 'Failed to generate accessibility compliance report',
            'summary' => [
                'total_accessibility_requests' => 0,
                'accessibility_compliance_rate_percent' => 0,
                'avg_response_time_hours' => 0
            ],
            'data' => []
        ];
    }
}

/**
 * Calculate standards compliance score
 */
function calculateStandardsComplianceScore($standards)
{
    if (empty($standards)) return 0;

    $totalScore = 0;
    $count = 0;

    foreach ($standards as $standard) {
        $score = match($standard['compliance_level']) {
            'fully_compliant' => 100,
            'mostly_compliant' => 80,
            'partially_compliant' => 60,
            'non_compliant' => 20,
            default => 50
        };
        $totalScore += $score;
        $count++;
    }

    return $count > 0 ? round($totalScore / $count, 2) : 0;
}

?>
