<?php
/**
 * Device Sync Service for Airport Operations Simulator
 * Handles cross-device synchronization of user progress and preferences
 */

require_once __DIR__ . '/../src/Config.php';
require_once __DIR__ . '/../src/Logger.php';

class DeviceSyncService
{
    private $pdo;
    private $logger;

    public function __construct()
    {
        $this->logger = new Logger('device_sync_service');
        $this->connectDatabase();
    }

    private function connectDatabase()
    {
        try {
            $config = new Config();
            $dbConfig = $config->getDatabaseConfig();

            $this->pdo = new PDO(
                "pgsql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']}",
                $dbConfig['username'],
                $dbConfig['password']
            );
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (Exception $e) {
            $this->logger->error('Database connection failed', ['error' => $e->getMessage()]);
            throw new Exception('Database connection failed');
        }
    }

    /**
     * Register a new device for a user
     */
    public function registerDevice($userId, $deviceInfo)
    {
        try {
            $deviceId = $this->generateDeviceId();
            $deviceFingerprint = $this->generateDeviceFingerprint($deviceInfo);

            // Check if device already exists
            $stmt = $this->pdo->prepare("
                SELECT id FROM user_devices
                WHERE user_id = ? AND device_fingerprint = ?
            ");
            $stmt->execute([$userId, $deviceFingerprint]);
            $existingDevice = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existingDevice) {
                // Update existing device
                $stmt = $this->pdo->prepare("
                    UPDATE user_devices
                    SET last_seen = NOW(), device_info = ?, is_active = TRUE
                    WHERE id = ?
                ");
                $stmt->execute([json_encode($deviceInfo), $existingDevice['id']]);
                return $existingDevice['id'];
            } else {
                // Register new device
                $stmt = $this->pdo->prepare("
                    INSERT INTO user_devices (user_id, device_id, device_fingerprint, device_info, registered_at, last_seen)
                    VALUES (?, ?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([$userId, $deviceId, $deviceFingerprint, json_encode($deviceInfo)]);
                return $deviceId;
            }
        } catch (Exception $e) {
            $this->logger->error("Failed to register device", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Sync user progress across devices
     */
    public function syncUserProgress($userId, $deviceId, $progressData)
    {
        try {
            // Start transaction
            $this->pdo->beginTransaction();

            // Get current progress from database
            $currentProgress = $this->getUserProgress($userId);

            // Merge progress data (server wins conflicts)
            $mergedProgress = $this->mergeProgressData($currentProgress, $progressData);

            // Update user progress
            $this->updateUserProgress($userId, $mergedProgress);

            // Log sync event
            $this->logSyncEvent($userId, $deviceId, 'progress', $progressData);

            // Update device last sync time
            $this->updateDeviceLastSync($deviceId);

            $this->pdo->commit();

            return [
                'success' => true,
                'merged_progress' => $mergedProgress,
                'sync_timestamp' => date('c')
            ];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            $this->logger->error("Failed to sync progress", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Sync user preferences across devices
     */
    public function syncUserPreferences($userId, $deviceId, $preferences)
    {
        try {
            // Get current preferences
            $currentPrefs = $this->getUserPreferences($userId);

            // Merge preferences (device preferences take precedence for UI settings)
            $mergedPrefs = array_merge($currentPrefs, $preferences);

            // Update preferences
            $stmt = $this->pdo->prepare("
                UPDATE user_preferences
                SET preferences = ?, updated_at = NOW()
                WHERE user_id = ?
            ");
            $stmt->execute([json_encode($mergedPrefs), $userId]);

            // Log sync event
            $this->logSyncEvent($userId, $deviceId, 'preferences', $preferences);

            return [
                'success' => true,
                'merged_preferences' => $mergedPrefs
            ];
        } catch (Exception $e) {
            $this->logger->error("Failed to sync preferences", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Sync achievements across devices
     */
    public function syncAchievements($userId, $deviceId, $achievements)
    {
        try {
            $this->pdo->beginTransaction();

            foreach ($achievements as $achievement) {
                // Check if achievement already exists
                $stmt = $this->pdo->prepare("
                    SELECT id FROM demo_achievements
                    WHERE user_id = ? AND achievement_type = ?
                ");
                $stmt->execute([$userId, $achievement['type']]);
                $existing = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$existing) {
                    // Insert new achievement
                    $stmt = $this->pdo->prepare("
                        INSERT INTO demo_achievements (
                            user_id, achievement_type, achievement_name,
                            points_earned, unlocked_at
                        ) VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $userId,
                        $achievement['type'],
                        $achievement['name'],
                        $achievement['points'],
                        $achievement['unlocked_at']
                    ]);
                }
            }

            // Log sync event
            $this->logSyncEvent($userId, $deviceId, 'achievements', $achievements);

            $this->pdo->commit();

            return ['success' => true];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            $this->logger->error("Failed to sync achievements", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get user's sync status
     */
    public function getSyncStatus($userId)
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    ud.device_id,
                    ud.device_info,
                    ud.last_seen,
                    ud.last_sync,
                    COUNT(se.id) as pending_syncs
                FROM user_devices ud
                LEFT JOIN sync_events se ON ud.id = se.device_id AND se.synced = FALSE
                WHERE ud.user_id = ? AND ud.is_active = TRUE
                GROUP BY ud.id, ud.device_id, ud.device_info, ud.last_seen, ud.last_sync
                ORDER BY ud.last_seen DESC
            ");
            $stmt->execute([$userId]);
            $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'devices' => $devices,
                'total_devices' => count($devices),
                'last_sync' => $this->getLastSyncTime($userId)
            ];
        } catch (Exception $e) {
            $this->logger->error("Failed to get sync status", ['error' => $e->getMessage()]);
            return ['devices' => [], 'total_devices' => 0, 'last_sync' => null];
        }
    }

    /**
     * Force sync all devices for a user
     */
    public function forceSyncAllDevices($userId)
    {
        try {
            // Get all active devices for user
            $stmt = $this->pdo->prepare("
                SELECT device_id FROM user_devices
                WHERE user_id = ? AND is_active = TRUE
            ");
            $stmt->execute([$userId]);
            $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $syncResults = [];
            foreach ($devices as $device) {
                try {
                    // Trigger sync for each device (this would typically be done via push notifications)
                    $syncResults[] = [
                        'device_id' => $device['device_id'],
                        'status' => 'sync_triggered'
                    ];
                } catch (Exception $e) {
                    $syncResults[] = [
                        'device_id' => $device['device_id'],
                        'status' => 'failed',
                        'error' => $e->getMessage()
                    ];
                }
            }

            return [
                'success' => true,
                'sync_results' => $syncResults,
                'total_devices' => count($devices)
            ];
        } catch (Exception $e) {
            $this->logger->error("Failed to force sync all devices", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Deactivate a device
     */
    public function deactivateDevice($userId, $deviceId)
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE user_devices
                SET is_active = FALSE, deactivated_at = NOW()
                WHERE user_id = ? AND device_id = ?
            ");
            $stmt->execute([$userId, $deviceId]);

            // Log deactivation
            $this->logSyncEvent($userId, $deviceId, 'device_deactivated', ['device_id' => $deviceId]);

            return ['success' => true];
        } catch (Exception $e) {
            $this->logger->error("Failed to deactivate device", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get sync conflicts for resolution
     */
    public function getSyncConflicts($userId)
    {
        try {
            // This would check for conflicting data across devices
            // For now, return empty array
            return [
                'conflicts' => [],
                'total_conflicts' => 0
            ];
        } catch (Exception $e) {
            $this->logger->error("Failed to get sync conflicts", ['error' => $e->getMessage()]);
            return ['conflicts' => [], 'total_conflicts' => 0];
        }
    }

    /**
     * Resolve sync conflicts
     */
    public function resolveSyncConflict($userId, $conflictId, $resolution)
    {
        try {
            // This would resolve specific conflicts
            // For now, just return success
            return ['success' => true, 'resolution' => $resolution];
        } catch (Exception $e) {
            $this->logger->error("Failed to resolve sync conflict", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    // Helper methods

    private function generateDeviceId()
    {
        return bin2hex(random_bytes(16));
    }

    private function generateDeviceFingerprint($deviceInfo)
    {
        // Create a fingerprint based on device characteristics
        $fingerprint = '';
        if (isset($deviceInfo['userAgent'])) {
            $fingerprint .= $deviceInfo['userAgent'];
        }
        if (isset($deviceInfo['screen'])) {
            $fingerprint .= $deviceInfo['screen']['width'] . 'x' . $deviceInfo['screen']['height'];
        }
        if (isset($deviceInfo['platform'])) {
            $fingerprint .= $deviceInfo['platform'];
        }

        return hash('sha256', $fingerprint);
    }

    private function getUserProgress($userId)
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM demo_user_progress WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        $progress = $stmt->fetch(PDO::FETCH_ASSOC);

        return $progress ?: [
            'total_score' => 0,
            'scenarios_completed' => 0,
            'experience_points' => 0,
            'current_level' => 1
        ];
    }

    private function mergeProgressData($serverData, $deviceData)
    {
        // Server data takes precedence for objective metrics
        // Device data can update timestamps and session data
        return array_merge($deviceData, [
            'total_score' => max($serverData['total_score'], $deviceData['total_score'] ?? 0),
            'scenarios_completed' => max($serverData['scenarios_completed'], $deviceData['scenarios_completed'] ?? 0),
            'experience_points' => max($serverData['experience_points'], $deviceData['experience_points'] ?? 0),
            'last_sync' => date('c')
        ]);
    }

    private function updateUserProgress($userId, $progressData)
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO demo_user_progress (
                user_id, total_score, scenarios_completed,
                experience_points, current_level, last_played, last_sync
            ) VALUES (?, ?, ?, ?, ?, NOW(), NOW())
            ON CONFLICT (user_id) DO UPDATE SET
                total_score = GREATEST(demo_user_progress.total_score, EXCLUDED.total_score),
                scenarios_completed = GREATEST(demo_user_progress.scenarios_completed, EXCLUDED.scenarios_completed),
                experience_points = GREATEST(demo_user_progress.experience_points, EXCLUDED.experience_points),
                last_played = NOW(),
                last_sync = NOW()
        ");
        $stmt->execute([
            $userId,
            $progressData['total_score'],
            $progressData['scenarios_completed'],
            $progressData['experience_points'],
            $progressData['current_level']
        ]);
    }

    private function getUserPreferences($userId)
    {
        $stmt = $this->pdo->prepare("
            SELECT preferences FROM user_preferences WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ? json_decode($result['preferences'], true) : [];
    }

    private function logSyncEvent($userId, $deviceId, $eventType, $data)
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO sync_events (user_id, device_id, event_type, event_data, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$userId, $deviceId, $eventType, json_encode($data)]);
        } catch (Exception $e) {
            // Log error but don't fail the sync
            $this->logger->error("Failed to log sync event", ['error' => $e->getMessage()]);
        }
    }

    private function updateDeviceLastSync($deviceId)
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE user_devices SET last_sync = NOW() WHERE device_id = ?
            ");
            $stmt->execute([$deviceId]);
        } catch (Exception $e) {
            $this->logger->error("Failed to update device last sync", ['error' => $e->getMessage()]);
        }
    }

    private function getLastSyncTime($userId)
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT MAX(last_sync) as last_sync
                FROM user_devices
                WHERE user_id = ? AND is_active = TRUE
            ");
            $stmt->execute([$userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['last_sync'];
        } catch (Exception $e) {
            return null;
        }
    }
}
?>
