<?php
require_once __DIR__ . '/cors.php';
/**
 * Device Sync API for Airport Operations Simulator
 * Handles cross-device synchronization of user data
 */

require_once __DIR__ . '/../src/Config.php';
require_once __DIR__ . '/../src/Logger.php';
require_once __DIR__ . '/../services/DeviceSyncService.php';

class SyncAPI
{
    private $syncService;
    private $logger;

    public function __construct()
    {
        $this->logger = new Logger('sync_api');
        $this->syncService = new DeviceSyncService();
    }

    public function handleRequest()
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = $_SERVER['REQUEST_URI'];

        // Extract endpoint from path
        $endpoint = str_replace('/api/sync/', '', parse_url($path, PHP_URL_PATH));
        $endpoint = trim($endpoint, '/');

        switch ($method) {
            case 'GET':
                $this->handleGet($endpoint);
                break;
            case 'POST':
                $this->handlePost($endpoint);
                break;
            case 'PUT':
                $this->handlePut($endpoint);
                break;
            case 'DELETE':
                $this->handleDelete($endpoint);
                break;
            default:
                $this->sendError('Method not allowed', 405);
        }
    }

    private function handleGet($endpoint)
    {
        switch ($endpoint) {
            case '':
            case 'status':
                $this->getSyncStatus();
                break;
            case 'conflicts':
                $this->getSyncConflicts();
                break;
            case 'devices':
                $this->getUserDevices();
                break;
            default:
                $this->sendError('Endpoint not found', 404);
        }
    }

    private function handlePost($endpoint)
    {
        $data = json_decode(file_get_contents('php://input'), true);

        switch ($endpoint) {
            case 'register':
                $this->registerDevice($data);
                break;
            case 'progress':
                $this->syncProgress($data);
                break;
            case 'preferences':
                $this->syncPreferences($data);
                break;
            case 'achievements':
                $this->syncAchievements($data);
                break;
            case 'force':
                $this->forceSyncAllDevices();
                break;
            default:
                $this->sendError('Endpoint not found', 404);
        }
    }

    private function handlePut($endpoint)
    {
        $data = json_decode(file_get_contents('php://input'), true);

        switch ($endpoint) {
            case 'resolve':
                $this->resolveConflict($data);
                break;
            default:
                $this->sendError('Endpoint not found', 404);
        }
    }

    private function handleDelete($endpoint)
    {
        $data = json_decode(file_get_contents('php://input'), true);

        switch ($endpoint) {
            case 'device':
                $this->deactivateDevice($data);
                break;
            default:
                $this->sendError('Endpoint not found', 404);
        }
    }

    private function registerDevice($data)
    {
        $userId = $this->getCurrentUserId();
        $deviceInfo = $data['device_info'] ?? null;

        if (!$deviceInfo) {
            $this->sendError('Device info required', 400);
            return;
        }

        try {
            $deviceId = $this->syncService->registerDevice($userId, $deviceInfo);

            $this->logger->info("Device registered", ['user' => $userId, 'device' => $deviceId]);

            $this->sendResponse([
                'success' => true,
                'device_id' => $deviceId,
                'message' => 'Device registered successfully'
            ]);
        } catch (Exception $e) {
            $this->logger->error("Failed to register device", ['error' => $e->getMessage()]);
            error_log('API error: ' . $e->getMessage()); $this->sendError('An internal error occurred', 400);
        }
    }

    private function syncProgress($data)
    {
        $userId = $this->getCurrentUserId();
        $deviceId = $data['device_id'] ?? null;
        $progressData = $data['progress'] ?? null;

        if (!$deviceId || !$progressData) {
            $this->sendError('Device ID and progress data required', 400);
            return;
        }

        try {
            $result = $this->syncService->syncUserProgress($userId, $deviceId, $progressData);

            $this->logger->info("Progress synced", ['user' => $userId, 'device' => $deviceId]);

            $this->sendResponse($result);
        } catch (Exception $e) {
            $this->logger->error("Failed to sync progress", ['error' => $e->getMessage()]);
            error_log('API error: ' . $e->getMessage()); $this->sendError('An internal error occurred', 500);
        }
    }

    private function syncPreferences($data)
    {
        $userId = $this->getCurrentUserId();
        $deviceId = $data['device_id'] ?? null;
        $preferences = $data['preferences'] ?? null;

        if (!$deviceId || !$preferences) {
            $this->sendError('Device ID and preferences required', 400);
            return;
        }

        try {
            $result = $this->syncService->syncUserPreferences($userId, $deviceId, $preferences);

            $this->logger->info("Preferences synced", ['user' => $userId, 'device' => $deviceId]);

            $this->sendResponse($result);
        } catch (Exception $e) {
            $this->logger->error("Failed to sync preferences", ['error' => $e->getMessage()]);
            error_log('API error: ' . $e->getMessage()); $this->sendError('An internal error occurred', 500);
        }
    }

    private function syncAchievements($data)
    {
        $userId = $this->getCurrentUserId();
        $deviceId = $data['device_id'] ?? null;
        $achievements = $data['achievements'] ?? null;

        if (!$deviceId || !$achievements) {
            $this->sendError('Device ID and achievements required', 400);
            return;
        }

        try {
            $result = $this->syncService->syncAchievements($userId, $deviceId, $achievements);

            $this->logger->info("Achievements synced", ['user' => $userId, 'device' => $deviceId]);

            $this->sendResponse($result);
        } catch (Exception $e) {
            $this->logger->error("Failed to sync achievements", ['error' => $e->getMessage()]);
            error_log('API error: ' . $e->getMessage()); $this->sendError('An internal error occurred', 500);
        }
    }

    private function getSyncStatus()
    {
        $userId = $this->getCurrentUserId();

        try {
            $status = $this->syncService->getSyncStatus($userId);
            $this->sendResponse(['sync_status' => $status]);
        } catch (Exception $e) {
            $this->logger->error("Failed to get sync status", ['error' => $e->getMessage()]);
            $this->sendError('Failed to retrieve sync status', 500);
        }
    }

    private function getSyncConflicts()
    {
        $userId = $this->getCurrentUserId();

        try {
            $conflicts = $this->syncService->getSyncConflicts($userId);
            $this->sendResponse(['conflicts' => $conflicts]);
        } catch (Exception $e) {
            $this->logger->error("Failed to get sync conflicts", ['error' => $e->getMessage()]);
            $this->sendError('Failed to retrieve sync conflicts', 500);
        }
    }

    private function getUserDevices()
    {
        $userId = $this->getCurrentUserId();

        try {
            $status = $this->syncService->getSyncStatus($userId);
            $this->sendResponse(['devices' => $status['devices']]);
        } catch (Exception $e) {
            $this->logger->error("Failed to get user devices", ['error' => $e->getMessage()]);
            $this->sendError('Failed to retrieve user devices', 500);
        }
    }

    private function forceSyncAllDevices()
    {
        $userId = $this->getCurrentUserId();

        try {
            $result = $this->syncService->forceSyncAllDevices($userId);

            $this->logger->info("Force sync triggered", ['user' => $userId]);

            $this->sendResponse($result);
        } catch (Exception $e) {
            $this->logger->error("Failed to force sync all devices", ['error' => $e->getMessage()]);
            error_log('API error: ' . $e->getMessage()); $this->sendError('An internal error occurred', 500);
        }
    }

    private function resolveConflict($data)
    {
        $userId = $this->getCurrentUserId();
        $conflictId = $data['conflict_id'] ?? null;
        $resolution = $data['resolution'] ?? null;

        if (!$conflictId || !$resolution) {
            $this->sendError('Conflict ID and resolution required', 400);
            return;
        }

        try {
            $result = $this->syncService->resolveSyncConflict($userId, $conflictId, $resolution);

            $this->logger->info("Conflict resolved", ['user' => $userId, 'conflict' => $conflictId]);

            $this->sendResponse($result);
        } catch (Exception $e) {
            $this->logger->error("Failed to resolve conflict", ['error' => $e->getMessage()]);
            error_log('API error: ' . $e->getMessage()); $this->sendError('An internal error occurred', 500);
        }
    }

    private function deactivateDevice($data)
    {
        $userId = $this->getCurrentUserId();
        $deviceId = $data['device_id'] ?? null;

        if (!$deviceId) {
            $this->sendError('Device ID required', 400);
            return;
        }

        try {
            $result = $this->syncService->deactivateDevice($userId, $deviceId);

            $this->logger->info("Device deactivated", ['user' => $userId, 'device' => $deviceId]);

            $this->sendResponse($result);
        } catch (Exception $e) {
            $this->logger->error("Failed to deactivate device", ['error' => $e->getMessage()]);
            error_log('API error: ' . $e->getMessage()); $this->sendError('An internal error occurred', 500);
        }
    }

    private function getCurrentUserId()
    {
        // Get user ID from session or JWT token
        // This is a simplified implementation
        return $_SESSION['user_id'] ?? 1; // Default to user ID 1 for demo
    }

    private function sendResponse($data, $statusCode = 200)
    {
        http_response_code($statusCode);
        echo json_encode($data);
        exit;
    }

    private function sendError($message, $statusCode = 400)
    {
        http_response_code($statusCode);
        echo json_encode(['error' => $message]);
        exit;
    }
}

// Handle the request
$api = new SyncAPI();
$api->handleRequest();
?>
