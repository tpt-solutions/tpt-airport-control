<?php

declare(strict_types=1);

namespace TPT\FlightControl\Services;

use TPT\FlightControl\Config\Database;

/**
 * Watchdog Monitor
 * Phase 23: Safety Foundation Layer
 *
 * Independent dead man switch for all critical processes.
 * Runs as separate monitor process with independent execution path.
 *
 * @package TPT\FlightControl\Services
 */
final class WatchdogMonitor
{
    public const STATUS_HEALTHY = 'HEALTHY';
    public const STATUS_DEGRADED = 'DEGRADED';
    public const STATUS_FAILED = 'FAILED';
    public const STATUS_TERMINATED = 'TERMINATED';

    public const ACTION_ALERT = 'ALERT';
    public const ACTION_RESTART = 'RESTART';
    public const ACTION_FAILOVER = 'FAILOVER';
    public const ACTION_SHUTDOWN = 'SHUTDOWN';

    private static ?self $instance = null;
    private array $registeredProcesses = [];
    private bool $enabled = true;

    private function __construct()
    {
        $this->enabled = filter_var($_ENV['WATCHDOG_GLOBAL_ENABLED'] ?? true, FILTER_VALIDATE_BOOLEAN);
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function registerProcess(
        string $processId,
        string $processName,
        int $priority,
        int $heartbeatIntervalMs = 1000,
        int $maxMissedHeartbeats = 3,
        string $failureAction = self::ACTION_ALERT
    ): void {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            INSERT INTO safety_watchdog_status
            (process_id, process_name, process_priority, heartbeat_interval_ms, max_missed_heartbeats, failure_action)
            VALUES (?, ?, ?, ?, ?, ?)
            ON CONFLICT (process_id) DO UPDATE SET
                process_name = EXCLUDED.process_name,
                process_priority = EXCLUDED.process_priority,
                heartbeat_interval_ms = EXCLUDED.heartbeat_interval_ms,
                max_missed_heartbeats = EXCLUDED.max_missed_heartbeats,
                failure_action = EXCLUDED.failure_action,
                last_heartbeat = NOW(),
                missed_heartbeats = 0,
                process_status = 'HEALTHY'
        ");

        $stmt->execute([$processId, $processName, $priority, $heartbeatIntervalMs, $maxMissedHeartbeats, $failureAction]);

        $this->registeredProcesses[$processId] = [
            'name' => $processName,
            'priority' => $priority,
            'interval' => $heartbeatIntervalMs,
            'max_missed' => $maxMissedHeartbeats
        ];
    }

    public function heartbeat(string $processId): void
    {
        if (!$this->enabled) {
            return;
        }

        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            UPDATE safety_watchdog_status
            SET last_heartbeat = NOW(),
                missed_heartbeats = 0,
                process_status = 'HEALTHY'
            WHERE process_id = ?
        ");

        $stmt->execute([$processId]);
    }

    public function checkProcesses(): array
    {
        if (!$this->enabled) {
            return [];
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->query("
            SELECT *,
                EXTRACT(EPOCH FROM (NOW() - last_heartbeat)) * 1000 as time_since_heartbeat_ms
            FROM safety_watchdog_status
            WHERE is_monitored = true
        ");

        $processes = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $failures = [];

        foreach ($processes as $process) {
            $elapsedMs = (float)$process['time_since_heartbeat_ms'];
            $maxAllowedMs = $process['heartbeat_interval_ms'] * $process['max_missed_heartbeats'];

            $missedCount = (int)floor($elapsedMs / $process['heartbeat_interval_ms']);

            if ($missedCount > 0) {
                $this->updateMissedHeartbeats($process['process_id'], $missedCount);

                if ($elapsedMs > $maxAllowedMs) {
                    $failures[] = $this->handleProcessFailure($process);
                }
            }
        }

        return $failures;
    }

    private function updateMissedHeartbeats(string $processId, int $missedCount): void
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            UPDATE safety_watchdog_status
            SET missed_heartbeats = ?,
                process_status = CASE
                    WHEN ? >= max_missed_heartbeats THEN 'FAILED'
                    WHEN ? > 0 THEN 'DEGRADED'
                    ELSE 'HEALTHY'
                END,
                last_status_change = CASE
                    WHEN process_status != CASE
                        WHEN ? >= max_missed_heartbeats THEN 'FAILED'
                        WHEN ? > 0 THEN 'DEGRADED'
                        ELSE 'HEALTHY'
                    END THEN NOW()
                    ELSE last_status_change
                END
            WHERE process_id = ?
        ");

        $stmt->execute([$missedCount, $missedCount, $missedCount, $missedCount, $missedCount, $processId]);
    }

    private function handleProcessFailure(array $process): array
    {
        WriteAheadLog::getInstance()->log('WATCHDOG_PROCESS_FAILURE', [
            'process_id' => $process['process_id'],
            'process_name' => $process['process_name'],
            'missed_heartbeats' => $process['missed_heartbeats'],
            'last_heartbeat' => $process['last_heartbeat'],
            'failure_action' => $process['failure_action']
        ]);

        AlertEscalationService::getInstance()->raiseAlert(
            min(4 + (int)($process['process_priority'] / 2), 6),
            'WATCHDOG_FAILURE',
            sprintf('Process %s has failed heartbeat checks', $process['process_name']),
            'WatchdogMonitor'
        );

        return [
            'process_id' => $process['process_id'],
            'process_name' => $process['process_name'],
            'failure_time' => date('c'),
            'action' => $process['failure_action']
        ];
    }

    public function runMonitorLoop(int $checkIntervalMs = 500): void
    {
        if (!$this->enabled) {
            return;
        }

        while (true) {
            $this->checkProcesses();
            usleep($checkIntervalMs * 1000);
        }
    }

    public function getMaximumDetectionTime(): int
    {
        return 500;
    }

    public function getAllProcessStatus(): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->query("SELECT * FROM safety_watchdog_status ORDER BY process_priority DESC");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function __clone() {}
    public function __wakeup()
    {
        throw new \RuntimeException('Cannot unserialize watchdog monitor');
    }
}