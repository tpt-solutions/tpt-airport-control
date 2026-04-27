<?php

declare(strict_types=1);

namespace TPT\FlightControl\Services;

use TPT\FlightControl\Config\Database;

/**
 * Stale Data Detector
 * Phase 24: Sensor Integration Layer
 *
 * Automatic timeout and status marking for stale sensor data.
 * Per data point reliability metrics.
 *
 * @package TPT\FlightControl\Services
 */
final class StaleDataDetector
{
    public const STATUS_FRESH = 'FRESH';
    public const STATUS_VALID = 'VALID';
    public const STATUS_STALE = 'STALE';
    public const STATUS_EXPIRED = 'EXPIRED';

    private static ?self $instance = null;
    private array $dataAgeThresholds = [
        'ADSB' => 1000,
        'RADAR_PRIMARY' => 4000,
        'RADAR_SECONDARY' => 1000,
        'MLAT' => 2000,
        'WEATHER' => 60000,
        'RUNWAY' => 500
    ];

    private function __construct() {}

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function checkDataAge(float $timestamp, string $sensorType): array
    {
        $threshold = $this->dataAgeThresholds[$sensorType] ?? 2000;
        $ageMs = (microtime(true) - $timestamp) * 1000;

        $status = self::STATUS_FRESH;
        $valid = true;

        if ($ageMs > $threshold * 3) {
            $status = self::STATUS_EXPIRED;
            $valid = false;
        } elseif ($ageMs > $threshold * 2) {
            $status = self::STATUS_STALE;
            $valid = false;
        } elseif ($ageMs > $threshold) {
            $status = self::STATUS_VALID;
            $valid = true;
        }

        return [
            'status' => $status,
            'age_ms' => $ageMs,
            'threshold_ms' => $threshold,
            'is_valid' => $valid,
            'confidence_modifier' => $valid ? 1.0 : max(0.0, 1.0 - ($ageMs / ($threshold * 4)))
        ];
    }

    public function validateTrack(array $track): array
    {
        $ageCheck = $this->checkDataAge($track['timestamp'], $track['sensor_type'] ?? 'UNKNOWN');

        $track['data_status'] = $ageCheck['status'];
        $track['data_age_ms'] = $ageCheck['age_ms'];
        $track['is_data_valid'] = $ageCheck['is_valid'];
        $track['confidence_score'] *= $ageCheck['confidence_modifier'];

        if (!$ageCheck['is_valid']) {
            AlertEscalationService::getInstance()->raiseAlert(
                3,
                'STALE_SENSOR_DATA',
                sprintf('Sensor %s data is stale (%.1fs old)', $track['sensor_id'], $ageCheck['age_ms'] / 1000),
                'StaleDataDetector'
            );
        }

        return $track;
    }

    public function runDetectionCycle(): array
    {
        $fusedTracks = MultiSensorFusionEngine::getInstance()->getActiveTracks();
        $results = [];

        foreach ($fusedTracks as $icao => $track) {
            $results[$icao] = $this->validateTrack($track);
        }

        return $results;
    }

    private function __clone() {}
    public function __wakeup()
    {
        throw new \RuntimeException('Cannot unserialize stale data detector');
    }
}