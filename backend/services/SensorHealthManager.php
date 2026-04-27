<?php

declare(strict_types=1);

namespace TPT\FlightControl\Services;

use TPT\FlightControl\Config\Database;

/**
 * Sensor Health Manager
 * Phase 23: Safety Foundation Layer
 *
 * Per sensor confidence scoring and fault detection.
 * Real time health monitoring and automatic degradation handling.
 *
 * @package TPT\FlightControl\Services
 */
final class SensorHealthManager
{
    public const STATUS_OPERATIONAL = 'OPERATIONAL';
    public const STATUS_DEGRADED = 'DEGRADED';
    public const STATUS_FAULTY = 'FAULTY';
    public const STATUS_FAILED = 'FAILED';
    public const STATUS_OFFLINE = 'OFFLINE';

    private static ?self $instance = null;
    private array $sensorCache = [];
    private float $healthDecayRate = 0.01;

    private function __construct()
    {
        $this->healthDecayRate = (float)($_ENV['SENSOR_HEALTH_DECAY_RATE'] ?? 0.01);
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function registerSensor(
        string $sensorId,
        string $sensorType,
        int $updateFrequencyMs,
        ?float $latitude = null,
        ?float $longitude = null
    ): void {
        $pdo = Database::getConnection();

        $location = null;
        if ($latitude !== null && $longitude !== null) {
            $location = sprintf('POINT(%f %f)', $longitude, $latitude);
        }

        $stmt = $pdo->prepare("
            INSERT INTO sensor_health_metrics (
                sensor_id, sensor_type, update_frequency_ms, sensor_location
            ) VALUES (?, ?, ?, ?)
            ON CONFLICT (sensor_id) DO UPDATE SET
                sensor_type = EXCLUDED.sensor_type,
                update_frequency_ms = EXCLUDED.update_frequency_ms,
                sensor_location = EXCLUDED.sensor_location,
                last_update = NOW(),
                missed_updates = 0,
                confidence_score = 1.0,
                sensor_status = 'OPERATIONAL'
        ");

        $stmt->execute([$sensorId, $sensorType, $updateFrequencyMs, $location]);
    }

    public function updateSensor(
        string $sensorId,
        float $confidenceScore,
        float $deviationScore = 0.0,
        float $signalStrength = 1.0,
        array $rawData = []
    ): void {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            UPDATE sensor_health_metrics
            SET last_update = NOW(),
                confidence_score = ?,
                deviation_score = ?,
                signal_strength = ?,
                missed_updates = 0,
                sensor_status = CASE
                    WHEN ? >= 0.9 THEN 'OPERATIONAL'
                    WHEN ? >= 0.7 THEN 'DEGRADED'
                    WHEN ? >= 0.4 THEN 'FAULTY'
                    ELSE 'FAILED'
                END
            WHERE sensor_id = ?
        ");

        $stmt->execute([
            max(0.0, min(1.0, $confidenceScore)),
            max(0.0, min(1.0, $deviationScore)),
            max(0.0, min(1.0, $signalStrength)),
            $confidenceScore,
            $confidenceScore,
            $confidenceScore,
            $sensorId
        ]);

        $this->sensorCache[$sensorId] = $this->getSensorStatus($sensorId);
    }

    public function getSensorStatus(string $sensorId): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM sensor_health_metrics WHERE sensor_id = ?");
        $stmt->execute([$sensorId]);
        $sensor = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$sensor) {
            return [
                'sensor_id' => $sensorId,
                'status' => self::STATUS_OFFLINE,
                'confidence_score' => 0.0,
                'is_valid' => false
            ];
        }

        $elapsedMs = (microtime(true) - strtotime($sensor['last_update'])) * 1000;
        $missedUpdates = (int)floor($elapsedMs / $sensor['update_frequency_ms']);

        $effectiveConfidence = (float)$sensor['confidence_score'] - ($missedUpdates * $this->healthDecayRate);
        $effectiveConfidence = max(0.0, $effectiveConfidence);

        return [
            'sensor_id' => $sensor['sensor_id'],
            'sensor_type' => $sensor['sensor_type'],
            'status' => $sensor['sensor_status'],
            'confidence_score' => $effectiveConfidence,
            'deviation_score' => (float)$sensor['deviation_score'],
            'signal_strength' => (float)$sensor['signal_strength'],
            'missed_updates' => $missedUpdates,
            'fault_count' => (int)$sensor['fault_count'],
            'last_update' => $sensor['last_update'],
            'is_valid' => $effectiveConfidence >= 0.7
        ];
    }

    public function getHealthSummary(): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->query("
            SELECT sensor_status, COUNT(*) as count, AVG(confidence_score) as avg_confidence
            FROM sensor_health_metrics
            GROUP BY sensor_status
        ");

        $statuses = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);

        return [
            'total_sensors' => array_sum($statuses),
            'operational' => $statuses[self::STATUS_OPERATIONAL] ?? 0,
            'degraded' => $statuses[self::STATUS_DEGRADED] ?? 0,
            'faulty' => $statuses[self::STATUS_FAULTY] ?? 0,
            'failed' => $statuses[self::STATUS_FAILED] ?? 0,
            'average_confidence' => isset($statuses['avg_confidence']) ? (float)$statuses['avg_confidence'] : 0.0
        ];
    }

    public function checkSensorHealth(): array
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->query("
            SELECT *,
                EXTRACT(EPOCH FROM (NOW() - last_update)) * 1000 as time_since_update_ms
            FROM sensor_health_metrics
        ");

        $sensors = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $failures = [];

        foreach ($sensors as $sensor) {
            $elapsedMs = (float)$sensor['time_since_update_ms'];
            $missedUpdates = (int)floor($elapsedMs / $sensor['update_frequency_ms']);

            if ($missedUpdates > 0) {
                $newConfidence = (float)$sensor['confidence_score'] - ($missedUpdates * $this->healthDecayRate);
                $newConfidence = max(0.0, $newConfidence);

                $status = self::STATUS_OPERATIONAL;
                if ($newConfidence < 0.4) $status = self::STATUS_FAILED;
                elseif ($newConfidence < 0.7) $status = self::STATUS_DEGRADED;

                $stmtUpdate = $pdo->prepare("
                    UPDATE sensor_health_metrics
                    SET missed_updates = ?,
                        confidence_score = ?,
                        sensor_status = ?
                    WHERE sensor_id = ?
                ");

                $stmtUpdate->execute([$missedUpdates, $newConfidence, $status, $sensor['sensor_id']]);

                if ($newConfidence < 0.7) {
                    $failures[] = [
                        'sensor_id' => $sensor['sensor_id'],
                        'status' => $status,
                        'confidence' => $newConfidence,
                        'missed_updates' => $missedUpdates
                    ];
                }
            }
        }

        return $failures;
    }

    public function getHealthPercentage(): float
    {
        $summary = $this->getHealthSummary();
        
        if ($summary['total_sensors'] === 0) {
            return 100.0;
        }
        
        return ($summary['operational'] / $summary['total_sensors']) * 100;
    }

    public function getAllSensorStatus(): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->query("SELECT sensor_id FROM sensor_health_metrics");
        $sensorIds = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        
        $statuses = [];
        foreach ($sensorIds as $sensorId) {
            $statuses[$sensorId] = $this->getSensorStatus($sensorId);
        }
        
        return $statuses;
    }

    public function getHealthySensors(): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->query("SELECT sensor_id FROM sensor_health_metrics WHERE confidence_score >= 0.7");
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function getAllSensors(): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->query("SELECT sensor_id FROM sensor_health_metrics");
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function injectFault(string $sensorId = 'all'): void
    {
        $pdo = Database::getConnection();
        
        if ($sensorId === 'all') {
            $stmt = $pdo->prepare("UPDATE sensor_health_metrics SET confidence_score = 0.1, sensor_status = 'FAULTY'");
            $stmt->execute();
        } else {
            $stmt = $pdo->prepare("UPDATE sensor_health_metrics SET confidence_score = 0.1, sensor_status = 'FAULTY' WHERE sensor_id = ?");
            $stmt->execute([$sensorId]);
        }
    }

    private function __clone() {}
    public function __wakeup()
    {
        throw new \RuntimeException('Cannot unserialize sensor health manager');
    }
}