<?php

declare(strict_types=1);

namespace TPT\FlightControl\Services;

use TPT\FlightControl\Config\Database;

/**
 * Alert Escalation Hierarchy
 * Phase 23: Safety Foundation Layer
 *
 * 7 level alert system with dead man acknowledgement.
 * Automatic escalation on acknowledgement timeout.
 *
 * @package TPT\FlightControl\Services
 */
final class AlertEscalationService
{
    public const LEVEL_NORMAL = 0;
    public const LEVEL_ADVISORY = 1;
    public const LEVEL_CAUTION = 2;
    public const LEVEL_WARNING = 3;
    public const LEVEL_ALERT = 4;
    public const LEVEL_EMERGENCY = 5;
    public const LEVEL_MAYDAY = 6;

    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_ACKNOWLEDGED = 'ACKNOWLEDGED';
    public const STATUS_ESCALATED = 'ESCALATED';
    public const STATUS_RESOLVED = 'RESOLVED';

    private static ?self $instance = null;
    private array $escalationLevels = [];
    private float $baseEscalationInterval = 30.0;
    private float $escalationMultiplier = 0.5;

    private function __construct()
    {
        $this->baseEscalationInterval = (float)($_ENV['ALERT_ESCALATION_INTERVAL_BASE'] ?? 30);
        $this->escalationMultiplier = (float)($_ENV['ALERT_ESCALATION_MULTIPLIER'] ?? 0.5);
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function raiseAlert(int $alertLevel, string $alertType, string $message, string $sourceComponent): string
    {
        if ($alertLevel < 0 || $alertLevel > 6) {
            throw new \InvalidArgumentException('Invalid alert level');
        }

        // Generate RFC 4122 compliant UUID v4
        $alertId = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
        
        $escalationInterval = $this->calculateEscalationInterval($alertLevel, 0);

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            INSERT INTO safety_alerts (
                alert_id, alert_level, alert_type, alert_message, source_component,
                next_escalation
            ) VALUES (?, ?, ?, ?, ?, NOW() + INTERVAL ? SECOND)
        ");

        $stmt->execute([
            $alertId,
            $alertLevel,
            $alertType,
            $message,
            $sourceComponent,
            $escalationInterval
        ]);

        WriteAheadLog::getInstance()->log('SAFETY_ALERT_RAISED', [
            'alert_id' => $alertId,
            'alert_level' => $alertLevel,
            'alert_type' => $alertType,
            'message' => $message,
            'source' => $sourceComponent
        ]);

        if ($alertLevel >= self::LEVEL_EMERGENCY) {
            $this->triggerEmergencyNotification($alertId, $alertLevel, $message);
        }

        return $alertId;
    }

    public function acknowledgeAlert(string $alertId, string $userId): bool
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            UPDATE safety_alerts
            SET acknowledged = true,
                acknowledged_by = ?,
                acknowledged_timestamp = NOW(),
                alert_status = 'ACKNOWLEDGED',
                next_escalation = NULL
            WHERE alert_id = ? AND acknowledged = false
        ");

        $stmt->execute([$userId, $alertId]);

        if ($stmt->rowCount() > 0) {
            WriteAheadLog::getInstance()->log('SAFETY_ALERT_ACKNOWLEDGED', [
                'alert_id' => $alertId,
                'user_id' => $userId
            ]);

            return true;
        }

        return false;
    }

    public function processEscalations(): array
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->query("
            SELECT * FROM safety_alerts
            WHERE alert_status = 'ACTIVE'
              AND acknowledged = false
              AND next_escalation <= NOW()
            ORDER BY alert_level DESC, timestamp ASC
        ");

        $alerts = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $escalated = [];

        foreach ($alerts as $alert) {
            $newLevel = min($alert['alert_level'] + 1, self::LEVEL_MAYDAY);
            $newEscalation = $this->calculateEscalationInterval($newLevel, $alert['escalation_level'] + 1);

            $stmtUpdate = $pdo->prepare("
                UPDATE safety_alerts
                SET alert_level = ?,
                    escalation_level = escalation_level + 1,
                    last_escalation = NOW(),
                    next_escalation = NOW() + INTERVAL ? SECOND,
                    alert_status = 'ESCALATED'
                WHERE alert_id = ?
            ");

            $stmtUpdate->execute([$newLevel, $newEscalation, $alert['alert_id']]);

            $escalated[] = [
                'alert_id' => $alert['alert_id'],
                'old_level' => $alert['alert_level'],
                'new_level' => $newLevel,
                'escalation_count' => $alert['escalation_level'] + 1
            ];

            WriteAheadLog::getInstance()->log('SAFETY_ALERT_ESCALATED', [
                'alert_id' => $alert['alert_id'],
                'previous_level' => $alert['alert_level'],
                'new_level' => $newLevel
            ]);
        }

        return $escalated;
    }

    public function resolveAlert(string $alertId, string $userId, string $resolutionNotes = ''): bool
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            UPDATE safety_alerts
            SET alert_status = 'RESOLVED',
                resolved_timestamp = NOW(),
                resolution_notes = ?,
                next_escalation = NULL
            WHERE alert_id = ?
        ");

        $stmt->execute([$resolutionNotes, $alertId]);

        if ($stmt->rowCount() > 0) {
            WriteAheadLog::getInstance()->log('SAFETY_ALERT_RESOLVED', [
                'alert_id' => $alertId,
                'user_id' => $userId,
                'notes' => $resolutionNotes
            ]);

            return true;
        }

        return false;
    }

    private function calculateEscalationInterval(int $alertLevel, int $escalationCount): float
    {
        $baseInterval = $this->baseEscalationInterval * (1 - ($alertLevel * 0.1));
        $multiplier = pow($this->escalationMultiplier, $escalationCount);
        return max(5.0, $baseInterval * $multiplier);
    }

    private function triggerEmergencyNotification(string $alertId, int $alertLevel, string $message): void
    {
        if (function_exists('posix_kill')) {
            posix_kill(posix_getppid(), SIGUSR1);
        }
    }

    public function getActiveAlerts(): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->query("SELECT * FROM safety_alerts WHERE alert_status IN ('ACTIVE', 'ESCALATED') ORDER BY alert_level DESC, timestamp ASC");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function __clone() {}
    public function __wakeup()
    {
        throw new \RuntimeException('Cannot unserialize alert escalation service');
    }
}