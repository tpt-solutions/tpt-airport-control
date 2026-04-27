<?php

/**
 * Advanced Security API
 *
 * Handles facial recognition, behavioral analytics, threat detection,
 * and advanced security monitoring for comprehensive airport security
 */

require_once '../src/Config.php';
require_once '../src/ApiResponse.php';
require_once '../models/AdvancedSecurity.php';
require_once '../services/AdvancedSecurityService.php';

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
$path = str_replace('/backend/api/advanced-security', '', $path);
$pathParts = explode('/', trim($path, '/'));

$securityService = new AdvancedSecurityService();

try {
    switch ($method) {
        case 'GET':
            handleGet($pathParts, $securityService);
            break;
        case 'POST':
            handlePost($pathParts, $securityService);
            break;
        case 'PUT':
            handlePut($pathParts, $securityService);
            break;
        case 'DELETE':
            handleDelete($pathParts, $securityService);
            break;
        default:
            ApiResponse::error('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log("Advanced Security API Error: " . $e->getMessage());
    ApiResponse::error('Internal server error', 500);
}

function handleGet($pathParts, $service) {
    if (empty($pathParts[0])) {
        // Get all security events with optional filters
        $filters = $_GET;
        $events = $service->getAllSecurityEvents($filters);
        ApiResponse::success($events);
    } elseif ($pathParts[0] === 'facial-recognition') {
        // Get facial recognition data
        $filters = $_GET;
        $recognition = $service->getFacialRecognitionData($filters);
        ApiResponse::success($recognition);
    } elseif ($pathParts[0] === 'behavioral-analytics') {
        // Get behavioral analytics
        $filters = $_GET;
        $analytics = $service->getBehavioralAnalytics($filters);
        ApiResponse::success($analytics);
    } elseif ($pathParts[0] === 'threat-detection') {
        // Get threat detection results
        $filters = $_GET;
        $threats = $service->getThreatDetectionResults($filters);
        ApiResponse::success($threats);
    } elseif ($pathParts[0] === 'surveillance') {
        // Get surveillance camera data
        $filters = $_GET;
        $surveillance = $service->getSurveillanceData($filters);
        ApiResponse::success($surveillance);
    } elseif ($pathParts[0] === 'alerts') {
        // Get security alerts
        $filters = $_GET;
        $alerts = $service->getSecurityAlerts($filters);
        ApiResponse::success($alerts);
    } elseif ($pathParts[0] === 'incidents') {
        // Get security incidents
        $filters = $_GET;
        $incidents = $service->getSecurityIncidents($filters);
        ApiResponse::success($incidents);
    } elseif ($pathParts[0] === 'access-control') {
        // Get access control logs
        $filters = $_GET;
        $access = $service->getAccessControlLogs($filters);
        ApiResponse::success($access);
    } elseif ($pathParts[0] === 'biometric-data') {
        // Get biometric verification data
        $filters = $_GET;
        $biometric = $service->getBiometricData($filters);
        ApiResponse::success($biometric);
    } elseif ($pathParts[0] === 'stats') {
        // Get security statistics
        $stats = $service->getSecurityStatistics();
        ApiResponse::success($stats);
    } elseif ($pathParts[0] === 'dashboard') {
        // Get security dashboard data
        $dashboard = $service->getSecurityDashboard();
        ApiResponse::success($dashboard);
    } else {
        ApiResponse::error('Invalid endpoint', 400);
    }
}

function handlePost($pathParts, $service) {
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($pathParts[0])) {
        // Create security event
        if (!validateSecurityEventData($data)) {
            ApiResponse::error('Invalid security event data', 400);
            return;
        }

        $eventId = $service->createSecurityEvent($data);
        ApiResponse::success(['event_id' => $eventId, 'message' => 'Security event recorded successfully'], 201);
    } elseif ($pathParts[0] === 'facial-recognition') {
        // Process facial recognition
        if (!validateFacialRecognitionData($data)) {
            ApiResponse::error('Invalid facial recognition data', 400);
            return;
        }

        $result = $service->processFacialRecognition($data);
        ApiResponse::success($result);
    } elseif ($pathParts[0] === 'behavioral-analysis') {
        // Perform behavioral analysis
        if (!validateBehavioralAnalysisData($data)) {
            ApiResponse::error('Invalid behavioral analysis data', 400);
            return;
        }

        $analysis = $service->performBehavioralAnalysis($data);
        ApiResponse::success($analysis);
    } elseif ($pathParts[0] === 'threat-detection') {
        // Run threat detection
        if (!validateThreatDetectionData($data)) {
            ApiResponse::error('Invalid threat detection data', 400);
            return;
        }

        $threats = $service->runThreatDetection($data);
        ApiResponse::success($threats);
    } elseif ($pathParts[0] === 'alerts') {
        // Create security alert
        if (!validateAlertData($data)) {
            ApiResponse::error('Invalid alert data', 400);
            return;
        }

        $alertId = $service->createSecurityAlert($data);
        ApiResponse::success(['alert_id' => $alertId, 'message' => 'Security alert created successfully'], 201);
    } elseif ($pathParts[0] === 'incidents') {
        // Report security incident
        if (!validateIncidentData($data)) {
            ApiResponse::error('Invalid incident data', 400);
            return;
        }

        $incidentId = $service->reportSecurityIncident($data);
        ApiResponse::success(['incident_id' => $incidentId, 'message' => 'Security incident reported successfully'], 201);
    } elseif ($pathParts[0] === 'access-control') {
        // Record access control event
        if (!validateAccessControlData($data)) {
            ApiResponse::error('Invalid access control data', 400);
            return;
        }

        $accessId = $service->recordAccessControlEvent($data);
        ApiResponse::success(['access_id' => $accessId, 'message' => 'Access control event recorded successfully'], 201);
    } elseif ($pathParts[0] === 'biometric-verification') {
        // Perform biometric verification
        if (!validateBiometricVerificationData($data)) {
            ApiResponse::error('Invalid biometric verification data', 400);
            return;
        }

        $verification = $service->performBiometricVerification($data);
        ApiResponse::success($verification);
    } else {
        ApiResponse::error('Invalid endpoint', 400);
    }
}

function handlePut($pathParts, $service) {
    $data = json_decode(file_get_contents('php://input'), true);

    if ($pathParts[0] === 'events' && isset($pathParts[1])) {
        // Update security event
        $eventId = $pathParts[1];
        if (!$service->getSecurityEventById($eventId)) {
            ApiResponse::error('Security event not found', 404);
            return;
        }

        $result = $service->updateSecurityEvent($eventId, $data);
        ApiResponse::success(['message' => 'Security event updated successfully']);
    } elseif ($pathParts[0] === 'alerts' && isset($pathParts[1])) {
        // Update security alert
        $alertId = $pathParts[1];
        $result = $service->updateSecurityAlert($alertId, $data);
        ApiResponse::success(['message' => 'Security alert updated successfully']);
    } elseif ($pathParts[0] === 'incidents' && isset($pathParts[1])) {
        // Update security incident
        $incidentId = $pathParts[1];
        $result = $service->updateSecurityIncident($incidentId, $data);
        ApiResponse::success(['message' => 'Security incident updated successfully']);
    } elseif ($pathParts[0] === 'surveillance' && isset($pathParts[1])) {
        // Update surveillance camera
        $cameraId = $pathParts[1];
        $result = $service->updateSurveillanceCamera($cameraId, $data);
        ApiResponse::success(['message' => 'Surveillance camera updated successfully']);
    } else {
        ApiResponse::error('Invalid endpoint', 400);
    }
}

function handleDelete($pathParts, $service) {
    if ($pathParts[0] === 'events' && isset($pathParts[1])) {
        // Delete security event
        $eventId = $pathParts[1];
        $result = $service->deleteSecurityEvent($eventId);
        ApiResponse::success(['message' => 'Security event deleted successfully']);
    } elseif ($pathParts[0] === 'alerts' && isset($pathParts[1])) {
        // Delete security alert
        $alertId = $pathParts[1];
        $result = $service->deleteSecurityAlert($alertId);
        ApiResponse::success(['message' => 'Security alert deleted successfully']);
    } elseif ($pathParts[0] === 'incidents' && isset($pathParts[1])) {
        // Delete security incident
        $incidentId = $pathParts[1];
        $result = $service->deleteSecurityIncident($incidentId);
        ApiResponse::success(['message' => 'Security incident deleted successfully']);
    } elseif ($pathParts[0] === 'surveillance' && isset($pathParts[1])) {
        // Delete surveillance camera
        $cameraId = $pathParts[1];
        $result = $service->deleteSurveillanceCamera($cameraId);
        ApiResponse::success(['message' => 'Surveillance camera deleted successfully']);
    } else {
        ApiResponse::error('Invalid endpoint', 400);
    }
}

function validateSecurityEventData($data) {
    $required = ['event_type', 'severity', 'location', 'description'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            return false;
        }
    }

    $validTypes = ['unauthorized_access', 'suspicious_behavior', 'security_breach', 'emergency', 'system_alert', 'other'];
    if (!in_array($data['event_type'], $validTypes)) {
        return false;
    }

    $validSeverities = ['low', 'medium', 'high', 'critical'];
    if (!in_array($data['severity'], $validSeverities)) {
        return false;
    }

    return true;
}

function validateFacialRecognitionData($data) {
    $required = ['image_data', 'location', 'camera_id'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            return false;
        }
    }

    return true;
}

function validateBehavioralAnalysisData($data) {
    $required = ['subject_id', 'behavior_type', 'location', 'analysis_period'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            return false;
        }
    }

    $validTypes = ['normal', 'suspicious', 'threatening', 'anomalous'];
    if (!in_array($data['behavior_type'], $validTypes)) {
        return false;
    }

    return true;
}

function validateThreatDetectionData($data) {
    $required = ['detection_type', 'target_area', 'sensitivity_level'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            return false;
        }
    }

    $validTypes = ['facial_recognition', 'behavioral_analysis', 'object_detection', 'crowd_monitoring'];
    if (!in_array($data['detection_type'], $validTypes)) {
        return false;
    }

    return true;
}

function validateAlertData($data) {
    $required = ['alert_type', 'severity', 'message', 'location'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            return false;
        }
    }

    $validTypes = ['security_breach', 'suspicious_activity', 'system_failure', 'emergency', 'maintenance_required'];
    if (!in_array($data['alert_type'], $validTypes)) {
        return false;
    }

    $validSeverities = ['low', 'medium', 'high', 'critical'];
    if (!in_array($data['severity'], $validSeverities)) {
        return false;
    }

    return true;
}

function validateIncidentData($data) {
    $required = ['incident_type', 'severity', 'description', 'location', 'reported_by'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            return false;
        }
    }

    $validTypes = ['theft', 'assault', 'suspicious_package', 'unauthorized_access', 'medical_emergency', 'other'];
    if (!in_array($data['incident_type'], $validTypes)) {
        return false;
    }

    $validSeverities = ['minor', 'moderate', 'serious', 'critical'];
    if (!in_array($data['severity'], $validSeverities)) {
        return false;
    }

    return true;
}

function validateAccessControlData($data) {
    $required = ['person_id', 'access_point', 'access_type', 'authorization_level'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            return false;
        }
    }

    $validTypes = ['granted', 'denied', 'restricted', 'emergency_override'];
    if (!in_array($data['access_type'], $validTypes)) {
        return false;
    }

    return true;
}

function validateBiometricVerificationData($data) {
    $required = ['biometric_type', 'subject_id', 'verification_method'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            return false;
        }
    }

    $validTypes = ['facial', 'fingerprint', 'iris', 'voice'];
    if (!in_array($data['biometric_type'], $validTypes)) {
        return false;
    }

    $validMethods = ['live_scan', 'database_match', 'watchlist_check'];
    if (!in_array($data['verification_method'], $validMethods)) {
        return false;
    }

    return true;
}

?>
