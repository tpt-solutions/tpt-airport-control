<?php
/**
 * TPT Flight Control System
 * Maintenance Window Manager
 * 
 * Manages scheduled maintenance windows, read-only mode, and outage communications
 */

declare(strict_types=1);

use TPT\FlightControl\Logger;
use TPT\FlightControl\Config\Database;

class MaintenanceWindowManager
{
    const STATUS_SCHEDULED = 'scheduled';
    const STATUS_ACTIVE = 'active';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    public static function getActiveWindow(): ?array
    {
        $db = Database::getConnection();
        
        $stmt = $db->prepare("
            SELECT * FROM maintenance_windows 
            WHERE status = :status 
            AND start_time <= NOW() 
            AND end_time >= NOW()
            LIMIT 1
        ");
        
        $stmt->execute(['status' => self::STATUS_ACTIVE]);
        $window = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $window ?: null;
    }

    public static function isSystemInReadOnlyMode(): bool
    {
        $window = self::getActiveWindow();
        return $window && $window['read_only_mode'] === true;
    }

    public static function getUpcomingWindows(int $hours = 72): array
    {
        $db = Database::getConnection();
        
        $stmt = $db->prepare("
            SELECT * FROM maintenance_windows 
            WHERE status = :status 
            AND start_time >= NOW()
            AND start_time <= NOW() + INTERVAL :hours HOUR
            ORDER BY start_time ASC
        ");
        
        $stmt->execute([
            'status' => self::STATUS_SCHEDULED,
            'hours' => $hours
        ]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function createWindow(array $data): int
    {
        $db = Database::getConnection();
        
        $stmt = $db->prepare("
            INSERT INTO maintenance_windows (
                title, description, start_time, end_time, 
                read_only_mode, notify_users, created_by, status
            ) VALUES (
                :title, :description, :start_time, :end_time,
                :read_only_mode, :notify_users, :created_by, :status
            )
        ");
        
        $stmt->execute([
            'title' => $data['title'],
            'description' => $data['description'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'read_only_mode' => $data['read_only_mode'] ?? false,
            'notify_users' => $data['notify_users'] ?? true,
            'created_by' => Auth::currentUserId(),
            'status' => self::STATUS_SCHEDULED
        ]);

        $windowId = (int)$db->lastInsertId();
        
        Logger::auditLog(
            Auth::currentUserId(), 
            'maintenance_window_created', 
            'maintenance_windows', 
            $windowId, 
            "Created maintenance window: {$data['title']}"
        );

        return $windowId;
    }

    public static function activateWindow(int $windowId): void
    {
        $db = Database::getConnection();
        
        $stmt = $db->prepare("
            UPDATE maintenance_windows 
            SET status = :status, activated_at = NOW()
            WHERE id = :id
        ");
        
        $stmt->execute([
            'status' => self::STATUS_ACTIVE,
            'id' => $windowId
        ]);

        // Broadcast system wide maintenance notification
        broadcastAnnouncement([
            'type' => 'maintenance_start',
            'window_id' => $windowId,
            'read_only_mode' => self::isSystemInReadOnlyMode()
        ]);

        Logger::auditLog(
            Auth::currentUserId(), 
            'maintenance_window_activated', 
            'maintenance_windows', 
            $windowId, 
            "Maintenance window activated"
        );
    }

    public static function completeWindow(int $windowId): void
    {
        $db = Database::getConnection();
        
        $stmt = $db->prepare("
            UPDATE maintenance_windows 
            SET status = :status, completed_at = NOW()
            WHERE id = :id
        ");
        
        $stmt->execute([
            'status' => self::STATUS_COMPLETED,
            'id' => $windowId
        ]);

        broadcastAnnouncement([
            'type' => 'maintenance_complete',
            'window_id' => $windowId
        ]);

        Logger::auditLog(
            Auth::currentUserId(), 
            'maintenance_window_completed', 
            'maintenance_windows', 
            $windowId, 
            "Maintenance window completed successfully"
        );
    }

    public static function getSystemStatusMessage(): ?array
    {
        $active = self::getActiveWindow();
        
        if ($active) {
            return [
                'type' => 'active_maintenance',
                'message' => "System maintenance currently in progress. {$active['title']}",
                'end_time' => $active['end_time'],
                'read_only_mode' => $active['read_only_mode']
            ];
        }

        $upcoming = self::getUpcomingWindows(24);
        
        if (count($upcoming) > 0) {
            $next = $upcoming[0];
            return [
                'type' => 'scheduled_maintenance',
                'message' => "Scheduled maintenance: {$next['title']}",
                'start_time' => $next['start_time'],
                'duration_minutes' => (strtotime($next['end_time']) - strtotime($next['start_time'])) / 60
            ];
        }

        return null;
    }
}