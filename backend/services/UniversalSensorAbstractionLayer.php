<?php

declare(strict_types=1);

namespace TPT\FlightControl\Services;

use TPT\FlightControl\Config\Database;

/**
 * Universal Sensor Abstraction Layer
 * Phase 24: Sensor Integration Layer
 *
 * Standard driver interface for all sensor types.
 * Provides uniform sensor access regardless of underlying protocol.
 *
 * @package TPT\FlightControl\Services
 */
final class UniversalSensorAbstractionLayer
{
    public const SENSOR_TYPE_ADSB = 'ADSB';
    public const SENSOR_TYPE_RADAR_PRIMARY = 'RADAR_PRIMARY';
    public const SENSOR_TYPE_RADAR_SECONDARY = 'RADAR_SECONDARY';
    public const SENSOR_TYPE_MLAT = 'MLAT';
    public const SENSOR_TYPE_WEATHER = 'WEATHER';
    public const SENSOR_TYPE_TOWER = 'TOWER';
    public const SENSOR_TYPE_RUNWAY = 'RUNWAY';

    public const PROTOCOL_ASTERIX = 'ASTERIX';
    public const PROTOCOL_JSON = 'JSON';
    public const PROTOCOL_BINARY = 'BINARY';
    public const PROTOCOL_NMEA = 'NMEA';
    public const PROTOCOL_CUSTOM = 'CUSTOM';

    private static ?self $instance = null;
    private array $drivers = [];
    private array $sensorMetadata = [];
    private bool $initialized = false;

    private function __construct() {}

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function initialize(): void
    {
        if ($this->initialized) {
            return;
        }

        $this->loadDrivers();
        $this->loadSensorMetadata();
        $this->initialized = true;
    }

    public function registerDriver(string $protocol, callable $decoder, callable $encoder = null): void
    {
        $this->drivers[$protocol] = [
            'decoder' => $decoder,
            'encoder' => $encoder
        ];
    }

    public function decode(string $sensorId, string $protocol, mixed $rawData): array
    {
        if (!$this->initialized) {
            $this->initialize();
        }

        if (!isset($this->drivers[$protocol])) {
            throw new \InvalidArgumentException("No driver registered for protocol: $protocol");
        }

        $decoded = $this->drivers[$protocol]['decoder']($rawData);

        $normalized = $this->normalizeData($decoded, $sensorId);

        SensorHealthManager::getInstance()->updateSensor(
            $sensorId,
            $normalized['confidence_score'] ?? 1.0,
            $normalized['deviation_score'] ?? 0.0,
            $normalized['signal_strength'] ?? 1.0,
            $decoded
        );

        WriteAheadLog::getInstance()->log('SENSOR_DATA_RECEIVED', [
            'sensor_id' => $sensorId,
            'protocol' => $protocol,
            'data_points' => count($normalized),
            'confidence' => $normalized['confidence_score'] ?? 1.0
        ]);

        return $normalized;
    }

    private function normalizeData(array $decodedData, string $sensorId): array
    {
        $metadata = $this->sensorMetadata[$sensorId] ?? [];

        $normalized = [
            'sensor_id' => $sensorId,
            'timestamp' => $decodedData['timestamp'] ?? microtime(true),
            'icao_address' => $decodedData['icao_address'] ?? null,
            'callsign' => $decodedData['callsign'] ?? null,
            'latitude' => $decodedData['latitude'] ?? null,
            'longitude' => $decodedData['longitude'] ?? null,
            'altitude' => $decodedData['altitude'] ?? null,
            'speed' => $decodedData['speed'] ?? null,
            'heading' => $decodedData['heading'] ?? null,
            'vertical_speed' => $decodedData['vertical_speed'] ?? null,
            'squawk' => $decodedData['squawk'] ?? null,
            'confidence_score' => $decodedData['confidence_score'] ?? $metadata['default_confidence'] ?? 0.9,
            'deviation_score' => $decodedData['deviation_score'] ?? 0.0,
            'signal_strength' => $decodedData['signal_strength'] ?? 1.0,
            'position_accuracy' => $decodedData['position_accuracy'] ?? $metadata['accuracy'] ?? 10.0,
            'altitude_accuracy' => $decodedData['altitude_accuracy'] ?? $metadata['altitude_accuracy'] ?? 25.0,
            'raw_data' => $decodedData
        ];

        if (isset($metadata['position_offset_lat'])) {
            $normalized['latitude'] += $metadata['position_offset_lat'];
        }
        if (isset($metadata['position_offset_lon'])) {
            $normalized['longitude'] += $metadata['position_offset_lon'];
        }
        if (isset($metadata['altitude_offset'])) {
            $normalized['altitude'] += $metadata['altitude_offset'];
        }

        return $normalized;
    }

    private function loadDrivers(): void
    {
        $this->registerDriver(self::PROTOCOL_ASTERIX, [$this, 'decodeASTERIX']);
        $this->registerDriver(self::PROTOCOL_JSON, fn($data) => json_decode($data, true));
    }

    private function decodeASTERIX(string $rawData): array
    {
        $decoded = [];
        $offset = 0;
        $length = strlen($rawData);

        while ($offset < $length) {
            $category = ord($rawData[$offset]);
            $len = (ord($rawData[$offset + 1]) << 8) | ord($rawData[$offset + 2]);

            $block = substr($rawData, $offset, $len);

            switch ($category) {
                case 1:
                    $decoded = array_merge($decoded, $this->decodeCAT001($block));
                    break;
                case 21:
                    $decoded = array_merge($decoded, $this->decodeCAT021($block));
                    break;
                case 48:
                    $decoded = array_merge($decoded, $this->decodeCAT048($block));
                    break;
            }

            $offset += $len;
        }

        return $decoded;
    }

    private function decodeCAT001(string $block): array
    {
        $data = unpack('Ccategory/nlength/Cfspec/C*', $block);

        $result = [];

        if ($data['fspec'] & 0x80) {
            $result['latitude'] = (unpack('N', substr($block, 4, 4))[1] / 0x40000000) * 90.0;
        }
        if ($data['fspec'] & 0x40) {
            $result['longitude'] = (unpack('N', substr($block, 8, 4))[1] / 0x40000000) * 180.0;
        }
        if ($data['fspec'] & 0x20) {
            $result['altitude'] = unpack('n', substr($block, 12, 2))[1] * 25;
        }

        return $result;
    }

    private function decodeCAT021(string $block): array
    {
        $data = unpack('Ccategory/nlength/Cfspec/C*', $block);

        $result = [];
        $pos = 4;

        if ($data['fspec'] & 0x80) {
            $result['icao_address'] = strtoupper(bin2hex(substr($block, $pos, 3)));
            $pos += 3;
        }
        if ($data['fspec'] & 0x40) {
            $result['latitude'] = (unpack('N', substr($block, $pos, 4))[1] / 0x40000000) * 90.0;
            $pos += 4;
        }
        if ($data['fspec'] & 0x20) {
            $result['longitude'] = (unpack('N', substr($block, $pos, 4))[1] / 0x40000000) * 180.0;
            $pos += 4;
        }
        if ($data['fspec'] & 0x10) {
            $result['altitude'] = unpack('n', substr($block, $pos, 2))[1] * 25;
            $pos += 2;
        }

        return $result;
    }

    private function decodeCAT048(string $block): array
    {
        return $this->decodeCAT021($block);
    }

    private function loadSensorMetadata(): void
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->query("SELECT sensor_id, sensor_type, metadata FROM sensor_health_metrics");
        $sensors = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($sensors as $sensor) {
            $this->sensorMetadata[$sensor['sensor_id']] = array_merge(
                json_decode($sensor['metadata'] ?? '{}', true),
                ['sensor_type' => $sensor['sensor_type']]
            );
        }
    }

    private function __clone() {}
    public function __wakeup()
    {
        throw new \RuntimeException('Cannot unserialize sensor abstraction layer');
    }
}