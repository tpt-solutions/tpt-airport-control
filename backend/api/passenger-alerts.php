<?php

/**
 * Passenger Alerts API
 *
 * RESTful API for managing passenger notifications, travel reminders, and alert preferences
 */

require_once '../src/ApiResponse.php';
require_once '../models/PassengerAlerts.php';
require_once '../src/Auth.php';

// Initialize components
$apiResponse = new ApiResponse();
$alertsManager = new PassengerAlerts();
$auth = new Auth();

// Get request method and path
$method = $_SERVER['REQUEST_METHOD'];
$request = $_SERVER['REQUEST_URI'];
$path = parse_url($request, PHP_URL_PATH);

// Remove base path
$path = str_replace('/api/passenger-alerts', '', $path);
$path = str_replace('/backend/api/passenger-alerts', '', $path);

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
            handleGetRequest($resource, $action, $id, $alertsManager, $user, $apiResponse);
            break;

        case 'POST':
            handlePostRequest($resource, $action, $alertsManager, $user, $apiResponse);
            break;

        case 'PUT':
            handlePutRequest($resource, $action, $id, $alertsManager, $user, $apiResponse);
            break;

        case 'DELETE':
            handleDeleteRequest($resource, $id, $alertsManager, $user, $apiResponse);
            break;

        default:
            $apiResponse->error('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log("Passenger Alerts API Error: " . $e->getMessage());
    $apiResponse->error($e->getMessage(), 500);
}

/**
 * Handle GET requests
 */
function handleGetRequest($resource, $action, $id, $alertsManager, $user, $apiResponse)
{
    // Check if user has appropriate permissions
    if (!$user || !in_array($user['role'], ['super_admin', 'admin', 'operator', 'passenger'])) {
        $apiResponse->error('Insufficient permissions', 403);
        return;
    }

    switch ($resource) {
        case null:
        case 'dashboard':
            // Get alerts dashboard data
            $dashboardData = getAlertsDashboard($alertsManager, $user);
            $apiResponse->success($dashboardData);
            break;

        case 'alerts':
            if ($id) {
                // Get specific alert
                $alert = getAlertDetails($alertsManager, $id, $user);
                $apiResponse->success($alert);
            } else {
                // Get alerts with filters
                $filters = $_GET;
                $alerts = getAlerts($alertsManager, $filters, $user);
                $apiResponse->success($alerts);
            }
            break;

        case 'preferences':
            // Get notification preferences
            $passengerId = $_GET['passenger_id'] ?? $user['user_id'];
            if ($user['role'] !== 'passenger' && $user['user_id'] !== $passengerId) {
                $apiResponse->error('Can only view own preferences', 403);
                return;
            }

            $preferences = $alertsManager->getNotificationPreferences($passengerId);
            $apiResponse->success($preferences);
            break;

        case 'itinerary':
            // Get travel itinerary
            $passengerId = $_GET['passenger_id'] ?? $user['user_id'];
            $bookingId = $_GET['booking_id'] ?? null;

            if ($user['role'] !== 'passenger' && $user['user_id'] !== $passengerId) {
                $apiResponse->error('Can only view own itinerary', 403);
                return;
            }

            $itinerary = $alertsManager->getTravelItinerary($passengerId, $bookingId);
            $apiResponse->success($itinerary);
            break;

        case 'templates':
            // Get alert templates
            $templates = getAlertTemplates($alertsManager);
            $apiResponse->success($templates);
            break;

        case 'analytics':
            // Get alert analytics (admin only)
            if (!in_array($user['role'], ['super_admin', 'admin'])) {
                $apiResponse->error('Admin access required', 403);
                return;
            }

            $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
            $endDate = $_GET['end_date'] ?? date('Y-m-d');
            $analytics = $alertsManager->getAlertAnalytics($startDate, $endDate);
            $apiResponse->success($analytics);
            break;

        default:
            $apiResponse->error('Resource not found', 404);
    }
}

/**
 * Handle POST requests
 */
function handlePostRequest($resource, $action, $alertsManager, $user, $apiResponse)
{
    // Check if user has appropriate permissions
    if (!$user || !in_array($user['role'], ['super_admin', 'admin', 'operator', 'passenger'])) {
        $apiResponse->error('Insufficient permissions', 403);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    switch ($resource) {
        case 'alerts':
            if ($action === 'send') {
                // Send alert notification
                if (!in_array($user['role'], ['super_admin', 'admin', 'operator'])) {
                    $apiResponse->error('Operator access required to send alerts', 403);
                    return;
                }

                if (!isset($input['template_id']) && !isset($input['template_name'])) {
                    $apiResponse->error('Template ID or name required', 400);
                    return;
                }

                $result = $alertsManager->sendAlert($input);
                $apiResponse->success($result);
            } elseif ($action === 'location') {
                // Send location-based alert
                if (!in_array($user['role'], ['super_admin', 'admin', 'operator'])) {
                    $apiResponse->error('Operator access required', 403);
                    return;
                }

                if (!isset($input['latitude']) || !isset($input['longitude'])) {
                    $apiResponse->error('Location coordinates required', 400);
                    return;
                }

                $result = $alertsManager->sendLocationAlert($input);
                $apiResponse->success($result);
            } else {
                $apiResponse->error('Invalid action', 400);
            }
            break;

        case 'push':
            if ($action === 'subscribe') {
                // Register push subscription
                if (!isset($input['endpoint'])) {
                    $apiResponse->error('Push endpoint required', 400);
                    return;
                }

                $subscriptionData = $input;
                $subscriptionData['passenger_id'] = $user['user_id'];

                $result = $alertsManager->registerPushSubscription($subscriptionData);
                $apiResponse->success($result);
            } else {
                $apiResponse->error('Invalid action', 400);
            }
            break;

        case 'preferences':
            // Update notification preferences
            $passengerId = $input['passenger_id'] ?? $user['user_id'];

            if ($user['role'] !== 'passenger' && $user['user_id'] !== $passengerId) {
                $apiResponse->error('Can only update own preferences', 403);
                return;
            }

            if (!isset($input['preferences'])) {
                $apiResponse->error('Preferences data required', 400);
                return;
            }

            $result = $alertsManager->updateNotificationPreferences($passengerId, $input['preferences']);
            $apiResponse->success($result);
            break;

        case 'itinerary':
            if ($action === 'create') {
                // Create travel itinerary
                if (!isset($input['booking_id'])) {
                    $apiResponse->error('Booking ID required', 400);
                    return;
                }

                $result = $alertsManager->createTravelItinerary($input['booking_id']);
                $apiResponse->success($result);
            } else {
                $apiResponse->error('Invalid action', 400);
            }
            break;

        case 'suppress':
            // Suppress notifications
            $passengerId = $input['passenger_id'] ?? $user['user_id'];

            if ($user['role'] !== 'passenger' && $user['user_id'] !== $passengerId) {
                $apiResponse->error('Can only suppress own notifications', 403);
                return;
            }

            if (!isset($input['alert_type']) || !isset($input['duration_hours'])) {
                $apiResponse->error('Alert type and duration required', 400);
                return;
            }

            $result = $alertsManager->suppressNotifications(
                $passengerId,
                $input['alert_type'],
                $input['duration_hours'],
                $input['reason'] ?? 'User requested'
            );
            $apiResponse->success($result);
            break;

        default:
            $apiResponse->error('Resource not found', 404);
    }
}

/**
 * Handle PUT requests
 */
function handlePutRequest($resource, $action, $id, $alertsManager, $user, $apiResponse)
{
    // Check if user has appropriate permissions
    if (!$user || !in_array($user['role'], ['super_admin', 'admin', 'operator', 'passenger'])) {
        $apiResponse->error('Insufficient permissions', 403);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    switch ($resource) {
        case 'alerts':
            if ($id) {
                // Update alert status
                if (!in_array($user['role'], ['super_admin', 'admin', 'operator'])) {
                    $apiResponse->error('Operator access required', 403);
                    return;
                }

                $result = updateAlert($alertsManager, $id, $input);
                $apiResponse->success($result);
            } else {
                $apiResponse->error('Alert ID required', 400);
            }
            break;

        case 'preferences':
            // Update specific preference
            $passengerId = $input['passenger_id'] ?? $user['user_id'];

            if ($user['role'] !== 'passenger' && $user['user_id'] !== $passengerId) {
                $apiResponse->error('Can only update own preferences', 403);
                return;
            }

            $result = $alertsManager->updateNotificationPreferences($passengerId, [$input]);
            $apiResponse->success($result);
            break;

        default:
            $apiResponse->error('Resource not found', 404);
    }
}

/**
 * Handle DELETE requests
 */
function handleDeleteRequest($resource, $id, $alertsManager, $user, $apiResponse)
{
    // Check if user has appropriate permissions
    if (!$user || !in_array($user['role'], ['super_admin', 'admin', 'operator', 'passenger'])) {
        $apiResponse->error('Insufficient permissions', 403);
        return;
    }

    switch ($resource) {
        case 'alerts':
            if ($id) {
                // Delete alert (admin only)
                if (!in_array($user['role'], ['super_admin', 'admin'])) {
                    $apiResponse->error('Admin access required', 403);
                    return;
                }

                $result = deleteAlert($alertsManager, $id);
                $apiResponse->success($result);
            } else {
                $apiResponse->error('Alert ID required', 400);
            }
            break;

        case 'push':
            if ($id) {
                // Unsubscribe from push notifications
                $passengerId = $user['user_id'];

                if ($user['role'] !== 'passenger') {
                    $apiResponse->error('Passenger access required', 403);
                    return;
                }

                $result = unsubscribePush($alertsManager, $passengerId, $id);
                $apiResponse->success($result);
            } else {
                $apiResponse->error('Subscription ID required', 400);
            }
            break;

        default:
            $apiResponse->error('Resource not found', 404);
    }
}

/**
 * Get alerts dashboard data
 */
function getAlertsDashboard($alertsManager, $user)
{
    // Get recent alerts for the user
    $recentAlerts = getRecentAlerts($alertsManager, $user);

    // Get notification preferences
    $preferences = $alertsManager->getNotificationPreferences($user['user_id']);

    // Get upcoming reminders
    $upcomingReminders = getUpcomingReminders($alertsManager, $user);

    return [
        'recent_alerts' => $recentAlerts,
        'preferences' => $preferences,
        'upcoming_reminders' => $upcomingReminders,
        'notification_channels' => [
            'push' => isPushEnabled($alertsManager, $user['user_id']),
            'sms' => isSMSEnabled($alertsManager, $user['user_id']),
            'email' => isEmailEnabled($alertsManager, $user['user_id'])
        ]
    ];
}

/**
 * Get recent alerts for user
 */
function getRecentAlerts($alertsManager, $user)
{
    // This would query the database for recent alerts
    // For now, return mock data
    return [
        [
            'alert_id' => 1,
            'type' => 'flight_reminder',
            'message' => 'Your flight departs in 2 hours',
            'timestamp' => date('Y-m-d H:i:s', time() - 3600)
        ]
    ];
}

/**
 * Get upcoming reminders
 */
function getUpcomingReminders($alertsManager, $user)
{
    $itinerary = $alertsManager->getTravelItinerary($user['user_id']);

    $upcoming = [];
    $now = time();

    foreach ($itinerary as $reminder) {
        $reminderTime = strtotime($reminder['reminder_time']);
        if ($reminderTime > $now && $reminderTime < $now + 86400) { // Next 24 hours
            $upcoming[] = $reminder;
        }
    }

    return $upcoming;
}

/**
 * Check if push notifications are enabled
 */
function isPushEnabled($alertsManager, $passengerId)
{
    // Check database for push subscriptions
    return true; // Mock implementation
}

/**
 * Check if SMS notifications are enabled
 */
function isSMSEnabled($alertsManager, $passengerId)
{
    // Check passenger preferences
    return false; // Mock implementation
}

/**
 * Check if email notifications are enabled
 */
function isEmailEnabled($alertsManager, $passengerId)
{
    // Check passenger preferences
    return true; // Mock implementation
}

/**
 * Get alert details
 */
function getAlertDetails($alertsManager, $alertId, $user)
{
    // This would query the database for specific alert
    // For now, return mock data
    return [
        'alert_id' => $alertId,
        'type' => 'flight_update',
        'message' => 'Flight status update',
        'status' => 'delivered',
        'timestamp' => date('Y-m-d H:i:s')
    ];
}

/**
 * Get alerts with filters
 */
function getAlerts($alertsManager, $filters, $user)
{
    // This would query the database with filters
    // For now, return mock data
    return [
        [
            'alert_id' => 1,
            'type' => 'boarding_reminder',
            'message' => 'Boarding starts in 30 minutes',
            'status' => 'sent',
            'timestamp' => date('Y-m-d H:i:s', time() - 1800)
        ]
    ];
}

/**
 * Get alert templates
 */
function getAlertTemplates($alertsManager)
{
    // This would query the database for alert templates
    // For now, return mock data
    return [
        [
            'template_id' => 1,
            'template_name' => 'Flight Reminder',
            'template_type' => 'flight_reminder',
            'subject' => 'Flight Reminder',
            'message_template' => 'Your flight {{flight_number}} departs at {{departure_time}}',
            'channels' => ['push', 'email'],
            'priority' => 'normal'
        ]
    ];
}

/**
 * Update alert
 */
function updateAlert($alertsManager, $alertId, $updates)
{
    // This would update the alert in the database
    // For now, return success
    return [
        'alert_id' => $alertId,
        'status' => 'updated',
        'message' => 'Alert updated successfully'
    ];
}

/**
 * Delete alert
 */
function deleteAlert($alertsManager, $alertId)
{
    // This would delete the alert from the database
    // For now, return success
    return [
        'alert_id' => $alertId,
        'status' => 'deleted',
        'message' => 'Alert deleted successfully'
    ];
}

/**
 * Unsubscribe from push notifications
 */
function unsubscribePush($alertsManager, $passengerId, $subscriptionId)
{
    // This would remove the push subscription
    // For now, return success
    return [
        'subscription_id' => $subscriptionId,
        'status' => 'unsubscribed',
        'message' => 'Push subscription removed successfully'
    ];
}

/**
 * Check if passenger alerts module is enabled
 */
function isPassengerAlertsEnabled()
{
    // This would typically check the modules table
    // For now, return true as we're implementing it
    return true;
}

/**
 * Get passenger alerts module configuration
 */
function getPassengerAlertsConfig()
{
    // This would retrieve configuration from the modules table
    return [
        'push_notifications_enabled' => true,
        'sms_notifications_enabled' => false,
        'email_notifications_enabled' => true,
        'location_based_alerts_enabled' => true,
        'travel_reminders_enabled' => true,
        'quiet_hours_respected' => true,
        'max_alerts_per_hour' => 10,
        'default_channels' => ['push', 'email'],
        'alert_retention_days' => 90
    ];
}
