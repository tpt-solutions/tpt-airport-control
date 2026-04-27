<?php

declare(strict_types=1);

namespace TPT\FlightControl\Services;

use TPT\FlightControl\Config\Database;

/**
 * Multi Sensor Fusion Engine
 * Phase 24: Sensor Integration Layer
 *
 * Minimum 2 sensor confirmation requirement.
 * Weighted data fusion with confidence scoring.
 *
 * @package TPT\FlightControl\Services
 */
final class MultiSensorFusionEngine
{
    public const MINIMUM_SENSOR_CONFIRMATION = 2;
    public const FUSION_WEIGHTED_AVERAGE = 'WEIGHTED_AVERAGE';
    public const FUSION_KALMAN_FILTER = 'KALMAN_FILTER';

    private static ?self $instance = null;
    private array $trackCache = [];
    private array $sensorWeights = [];
    private int $trackExpiryMs = 15000;

    private function __construct() {}

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function processSensorData(array $sensorData): array
    {
        $icao = $sensorData['icao_address'];

        if (!isset($this->trackCache[$icao])) {
            $this->trackCache[$icao] = [
                'reports' => [],
                'fused_track' => null,
                'last_update' => microtime(true)
            ];
        }

        $this->trackCache[$icao]['reports'][$sensorData['sensor_id']] = $sensorData;
        $this->trackCache[$icao]['last_update'] = microtime(true);

        $reports = $this->trackCache[$icao]['reports'];
        $validReports = $this->filterValidReports($reports);
        $reportCount = count($validReports);

        if ($reportCount < self::MINIMUM_SENSOR_CONFIRMATION) {
            return [
                'fused' => false,
                'report_count' => $reportCount,
                'required' => self::MINIMUM_SENSOR_CONFIRMATION,
                'track' => null
            ];
        }

        $fusedTrack = $this->fuseReports($validReports);
        $this->trackCache[$icao]['fused_track'] = $fusedTrack;

        WriteAheadLog::getInstance()->log('SENSOR_FUSION_COMPLETED', [
            'icao_address' => $icao,
            'report_count' => $reportCount,
            'confidence' => $fusedTrack['confidence_score'],
            'sensors' => array_keys($validReports)
        ]);

        return [
            'fused' => true,
            'report_count' => $reportCount,
            'confidence' => $fusedTrack['confidence_score'],
            'track' => $fusedTrack
        ];
    }

    private function filterValidReports(array $reports): array
    {
        $valid = [];
        $cutoffTime = microtime(true) - 2.0;

        foreach ($reports as $sensorId => $report) {
            $sensorStatus = SensorHealthManager::getInstance()->getSensorStatus($sensorId);

            if (!$sensorStatus['is_valid']) {
                continue;
            }

            if ($report['timestamp'] < $cutoffTime) {
                continue;
            }

            $valid[$sensorId] = $report;
        }

        return $valid;
    }

    private function fuseReports(array $reports): array
    {
        $totalWeight = 0.0;
        $weightedSum = [
            'latitude' => 0.0,
            'longitude' => 0.0,
            'altitude' => 0.0,
            'speed' => 0.0,
            'heading' => 0.0,
            'vertical_speed' => 0.0
        ];

        $minConfidence = 1.0;
        $maxConfidence = 0.0;

        foreach ($reports as $sensorId => $report) {
            $sensorStatus = SensorHealthManager::getInstance()->getSensorStatus($sensorId);
            $weight = $report['confidence_score'] * $sensorStatus['confidence_score'];

            $totalWeight += $weight;

            $weightedSum['latitude'] += $report['latitude'] * $weight;
            $weightedSum['longitude'] += $report['longitude'] * $weight;
            $weightedSum['altitude'] += $report['altitude'] * $weight;
            $weightedSum['speed'] += $report['speed'] * $weight;
            $weightedSum['heading'] += $report['heading'] * $weight;
            $weightedSum['vertical_speed'] += $report['vertical_speed'] * $weight;

            if ($report['confidence_score'] < $minConfidence) {
                $minConfidence = $report['confidence_score'];
            }
            if ($report['confidence_score'] > $maxConfidence) {
                $maxConfidence = $report['confidence_score'];
            }
        }

        $fused = [];

        foreach ($weightedSum as $key => $sum) {
            $fused[$key] = $sum / $totalWeight;
        }

        $fused['icao_address'] = reset($reports)['icao_address'];
        $fused['callsign'] = reset($reports)['callsign'];
        $fused['timestamp'] = microtime(true);
        $fused['confidence_score'] = $this->calculateFusedConfidence($reports, $totalWeight);
        $fused['source_sensors'] = array_keys($reports);
        $fused['report_count'] = count($reports);
        $fused['min_confidence'] = $minConfidence;
        $fused['max_confidence'] = $maxConfidence;

        return $fused;
    }

    private function calculateFusedConfidence(array $reports, float $totalWeight): float
    {
        $count = count($reports);
        $baseConfidence = $totalWeight / $count;
        $countBonus = min(0.1, ($count - self::MINIMUM_SENSOR_CONFIRMATION) * 0.05);

        return min(1.0, $baseConfidence + $countBonus);
    }

    public function getTrack(string $icaoAddress): ?array
    {
        if (!isset($this->trackCache[$icaoAddress])) {
            return null;
        }

        $track = $this->trackCache[$icaoAddress];

        if (microtime(true) - $track['last_update'] * 1000 > $this->trackExpiryMs) {
            unset($this->trackCache[$icaoAddress]);
            return null;
        }

        return $track['fused_track'];
    }

    public function getActiveTracks(): array
    {
        $active = [];
        $cutoff = microtime(true) - ($this->trackExpiryMs / 1000);

        foreach ($this->trackCache as $icao => $track) {
            if ($track['last_update'] > $cutoff && $track['fused_track'] !== null) {
                $active[$icao] = $track['fused_track'];
            } else {
                unset($this->trackCache[$icao]);
            }
        }

        return $active;
    }

    public function getDataConfidence(): float
    {
        $tracks = $this->getActiveTracks();
        if (empty($tracks)) {
            return 1.0;
        }
        
        $totalConfidence = 0.0;
        foreach ($tracks as $track) {
            $totalConfidence += $track['confidence_score'];
        }
        
        return $totalConfidence / count($tracks);
    }

    public function getUpdateFrequency(): float
    {
        return 1.5;
    }

    public function getAltitudeAccuracy(): float
    {
        return 12.5;
    }

    private function __clone() {}
    public function __wakeup()
    {
        throw new \RuntimeException('Cannot unserialize fusion engine');
    }
}