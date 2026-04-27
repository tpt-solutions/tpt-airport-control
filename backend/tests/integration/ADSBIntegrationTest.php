<?php
/**
 * ADS-B Integration Tests
 *
 * Tests the complete ADS-B aircraft tracking system including
 * data reception, processing, conflict detection, and real-time updates
 */

use PHPUnit\Framework\TestCase;

class ADSBIntegrationTest extends TestCase
{
    private $db;
    private $adsbReceiver;
    private $adsbProcessor;
    private $conflictDetector;
    private $websocketServer;
    private $dataFusionEngine;

    protected function setUp(): void
    {
        // Set up test database connection
        $this->db = $this->createMock(PDO::class);

        // Initialize ADS-B components
        $this->adsbReceiver = new ADSBReceiver();
        $this->adsbProcessor = new ADSBProcessor($this->db);
        $this->conflictDetector = new ConflictDetector($this->db);
        $this->websocketServer = new WebSocketServer();
        $this->dataFusionEngine = new DataFusionEngine($this->db);
    }

    /**
     * Test ADS-B data reception and processing
     */
    public function testADSBDataReceptionAndProcessing()
    {
        // Simulate ADS-B message
        $adsbMessage = [
            'icao24' => 'ABC123',
            'callsign' => 'UAL123',
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'altitude' => 35000,
            'speed' => 500,
            'heading' => 90,
            'timestamp' => time(),
            'source' => 'adsb'
        ];

        // Test message reception
        $received = $this->adsbReceiver->receiveMessage($adsbMessage);

        $this->assertTrue($received['success']);
        $this->assertEquals('ABC123', $received['icao24']);

        // Test message processing
        $processed = $this->adsbProcessor->processMessage($adsbMessage);

        $this->assertTrue($processed['success']);
        $this->assertArrayHasKey('processed_data', $processed);

        // Verify data storage
        $stored = $this->adsbProcessor->storeAircraftPosition($processed['processed_data']);

        $this->assertTrue($stored['success']);
        $this->assertArrayHasKey('position_id', $stored);
    }

    /**
     * Test aircraft position tracking and trajectory calculation
     */
    public function testAircraftPositionTracking()
    {
        $icao24 = 'DEF456';

        // Simulate multiple position updates
        $positions = [
            [
                'icao24' => $icao24,
                'latitude' => 40.7128,
                'longitude' => -74.0060,
                'altitude' => 30000,
                'speed' => 480,
                'heading' => 90,
                'timestamp' => time() - 60
            ],
            [
                'icao24' => $icao24,
                'latitude' => 40.7589,
                'longitude' => -73.9851,
                'altitude' => 32000,
                'speed' => 490,
                'heading' => 85,
                'timestamp' => time() - 30
            ],
            [
                'icao24' => $icao24,
                'latitude' => 40.8051,
                'longitude' => -73.9542,
                'altitude' => 34000,
                'speed' => 500,
                'heading' => 80,
                'timestamp' => time()
            ]
        ];

        // Process all positions
        foreach ($positions as $position) {
            $this->adsbProcessor->processMessage($position);
        }

        // Test trajectory calculation
        $trajectory = $this->adsbProcessor->calculateTrajectory($icao24, 300); // Last 5 minutes

        $this->assertCount(3, $trajectory['positions']);
        $this->assertArrayHasKey('average_speed', $trajectory);
        $this->assertArrayHasKey('average_altitude', $trajectory);
        $this->assertArrayHasKey('heading_trend', $trajectory);

        // Verify trajectory data
        $this->assertGreaterThan(480, $trajectory['average_speed']);
        $this->assertGreaterThan(30000, $trajectory['average_altitude']);
        $this->assertLessThan(90, $trajectory['heading_trend']); // Heading decreasing
    }

    /**
     * Test conflict detection between aircraft
     */
    public function testConflictDetection()
    {
        // Create two aircraft on conflicting paths
        $aircraft1 = [
            'icao24' => 'AIRCRAFT1',
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'altitude' => 35000,
            'speed' => 500,
            'heading' => 90,
            'timestamp' => time()
        ];

        $aircraft2 = [
            'icao24' => 'AIRCRAFT2',
            'latitude' => 40.7128,
            'longitude' => -73.9000, // Ahead on same latitude
            'altitude' => 35000, // Same altitude
            'speed' => 480,
            'heading' => 270, // Opposite direction
            'timestamp' => time()
        ];

        // Process both aircraft positions
        $this->adsbProcessor->processMessage($aircraft1);
        $this->adsbProcessor->processMessage($aircraft2);

        // Test conflict detection
        $conflicts = $this->conflictDetector->detectConflicts();

        $this->assertNotEmpty($conflicts);
        $this->assertGreaterThan(0, count($conflicts));

        // Find conflict between our test aircraft
        $testConflict = null;
        foreach ($conflicts as $conflict) {
            if (($conflict['aircraft1'] === 'AIRCRAFT1' && $conflict['aircraft2'] === 'AIRCRAFT2') ||
                ($conflict['aircraft1'] === 'AIRCRAFT2' && $conflict['aircraft2'] === 'AIRCRAFT1')) {
                $testConflict = $conflict;
                break;
            }
        }

        $this->assertNotNull($testConflict);
        $this->assertArrayHasKey('severity', $testConflict);
        $this->assertArrayHasKey('time_to_conflict', $testConflict);
        $this->assertArrayHasKey('horizontal_separation', $testConflict);
        $this->assertArrayHasKey('vertical_separation', $testConflict);
    }

    /**
     * Test real-time ADS-B data streaming
     */
    public function testRealTimeADSBStreaming()
    {
        $clientId = 'test_client_' . uniqid();

        // Subscribe to ADS-B updates
        $subscription = $this->websocketServer->subscribeToADSB($clientId);

        $this->assertTrue($subscription['success']);

        // Simulate ADS-B data stream
        $adsbData = [
            'icao24' => 'STREAM123',
            'callsign' => 'TEST123',
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'altitude' => 36000,
            'speed' => 510,
            'heading' => 95,
            'timestamp' => time()
        ];

        // Process and broadcast
        $this->adsbProcessor->processAndBroadcast($adsbData);

        // Check if message was received by client
        $messages = $this->websocketServer->getMessagesForClient($clientId);

        $this->assertNotEmpty($messages);

        $adsbMessage = null;
        foreach ($messages as $message) {
            if ($message['type'] === 'adsb_update') {
                $adsbMessage = $message;
                break;
            }
        }

        $this->assertNotNull($adsbMessage);
        $this->assertEquals('STREAM123', $adsbMessage['data']['icao24']);
        $this->assertEquals('TEST123', $adsbMessage['data']['callsign']);
    }

    /**
     * Test aircraft identification and registration lookup
     */
    public function testAircraftIdentification()
    {
        $icao24 = 'A1B2C3';

        // Test ICAO24 to aircraft registration lookup
        $identification = $this->adsbProcessor->identifyAircraft($icao24);

        $this->assertArrayHasKey('registration', $identification);
        $this->assertArrayHasKey('aircraft_type', $identification);
        $this->assertArrayHasKey('airline', $identification);

        // Test callsign to flight number conversion
        $callsignData = [
            'callsign' => 'UAL123',
            'icao24' => $icao24
        ];

        $flightInfo = $this->adsbProcessor->parseCallsign($callsignData);

        $this->assertEquals('UA', $flightInfo['airline_code']);
        $this->assertEquals('123', $flightInfo['flight_number']);
        $this->assertEquals('UAL123', $flightInfo['full_callsign']);
    }

    /**
     * Test ADS-B data validation and error handling
     */
    public function testADSBDataValidation()
    {
        // Test invalid latitude
        $invalidData1 = [
            'icao24' => 'TEST001',
            'latitude' => 91.0, // Invalid latitude
            'longitude' => -74.0060,
            'altitude' => 35000
        ];

        $result1 = $this->adsbProcessor->validateADSBData($invalidData1);

        $this->assertFalse($result1['valid']);
        $this->assertStringContains('latitude', $result1['errors'][0]);

        // Test invalid longitude
        $invalidData2 = [
            'icao24' => 'TEST002',
            'latitude' => 40.7128,
            'longitude' => 181.0, // Invalid longitude
            'altitude' => 35000
        ];

        $result2 = $this->adsbProcessor->validateADSBData($invalidData2);

        $this->assertFalse($result2['valid']);
        $this->assertStringContains('longitude', $result2['errors'][0]);

        // Test invalid altitude
        $invalidData3 = [
            'icao24' => 'TEST003',
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'altitude' => 100000 // Invalid altitude (too high)
        ];

        $result3 = $this->adsbProcessor->validateADSBData($invalidData3);

        $this->assertFalse($result3['valid']);
        $this->assertStringContains('altitude', $result3['errors'][0]);

        // Test valid data
        $validData = [
            'icao24' => 'TEST004',
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'altitude' => 35000,
            'speed' => 500,
            'heading' => 90
        ];

        $result4 = $this->adsbProcessor->validateADSBData($validData);

        $this->assertTrue($result4['valid']);
        $this->assertEmpty($result4['errors']);
    }

    /**
     * Test ADS-B data fusion with other sources
     */
    public function testADSBDataFusion()
    {
        $icao24 = 'FUSION123';

        // ADS-B data
        $adsbData = [
            'icao24' => $icao24,
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'altitude' => 35000,
            'speed' => 500,
            'heading' => 90,
            'source' => 'adsb',
            'timestamp' => time()
        ];

        // Radar data
        $radarData = [
            'icao24' => $icao24,
            'latitude' => 40.7130,
            'longitude' => -74.0062,
            'altitude' => 34980,
            'speed' => 498,
            'heading' => 89,
            'source' => 'radar',
            'timestamp' => time()
        ];

        // Satellite data
        $satelliteData = [
            'icao24' => $icao24,
            'latitude' => 40.7125,
            'longitude' => -74.0058,
            'altitude' => 35020,
            'speed' => 502,
            'heading' => 91,
            'source' => 'satellite',
            'timestamp' => time()
        ];

        // Fuse data from multiple sources
        $fusedData = $this->dataFusionEngine->fuseAircraftData([
            $adsbData,
            $radarData,
            $satelliteData
        ]);

        $this->assertTrue($fusedData['success']);
        $this->assertArrayHasKey('fused_position', $fusedData);

        $fused = $fusedData['fused_position'];

        // Verify fused data is within expected ranges
        $this->assertGreaterThan(40.7120, $fused['latitude']);
        $this->assertLessThan(40.7135, $fused['latitude']);
        $this->assertGreaterThan(-74.0070, $fused['longitude']);
        $this->assertLessThan(-74.0050, $fused['longitude']);
        $this->assertGreaterThan(34900, $fused['altitude']);
        $this->assertLessThan(35100, $fused['altitude']);

        // Verify confidence scores
        $this->assertArrayHasKey('confidence', $fused);
        $this->assertGreaterThan(0.8, $fused['confidence']); // High confidence due to multiple sources
    }

    /**
     * Test ADS-B outage detection and handling
     */
    public function testADSBOutageDetection()
    {
        $icao24 = 'OUTAGE123';

        // Simulate normal ADS-B transmission
        $normalData = [
            'icao24' => $icao24,
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'altitude' => 35000,
            'timestamp' => time() - 30
        ];

        $this->adsbProcessor->processMessage($normalData);

        // Simulate outage (no data for extended period)
        $outageDetected = $this->adsbProcessor->detectOutage($icao24, 300); // 5 minute threshold

        $this->assertFalse($outageDetected); // Should not detect outage yet

        // Simulate longer outage
        $outageDetected = $this->adsbProcessor->detectOutage($icao24, 30); // 30 second threshold

        $this->assertTrue($outageDetected);

        // Test outage handling
        $outageResponse = $this->adsbProcessor->handleOutage($icao24);

        $this->assertTrue($outageResponse['success']);
        $this->assertArrayHasKey('estimated_position', $outageResponse);
        $this->assertArrayHasKey('uncertainty_radius', $outageResponse);

        // Verify estimated position is reasonable
        $estimated = $outageResponse['estimated_position'];
        $this->assertEquals(40.7128, $estimated['latitude']);
        $this->assertEquals(-74.0060, $estimated['longitude']);
        $this->assertGreaterThan(0, $outageResponse['uncertainty_radius']);
    }

    /**
     * Test ADS-B data filtering and noise reduction
     */
    public function testADSBDataFiltering()
    {
        $icao24 = 'FILTER123';

        // Simulate noisy data with outliers
        $noisyData = [
            [
                'icao24' => $icao24,
                'latitude' => 40.7128,
                'longitude' => -74.0060,
                'altitude' => 35000,
                'speed' => 500,
                'timestamp' => time() - 50
            ],
            [
                'icao24' => $icao24,
                'latitude' => 40.7128,
                'longitude' => -74.0060,
                'altitude' => 35000,
                'speed' => 500,
                'timestamp' => time() - 40
            ],
            [
                'icao24' => $icao24,
                'latitude' => 41.0000, // Outlier latitude
                'longitude' => -74.0060,
                'altitude' => 35000,
                'speed' => 500,
                'timestamp' => time() - 30
            ],
            [
                'icao24' => $icao24,
                'latitude' => 40.7128,
                'longitude' => -74.0060,
                'altitude' => 35000,
                'speed' => 500,
                'timestamp' => time() - 20
            ],
            [
                'icao24' => $icao24,
                'latitude' => 40.7128,
                'longitude' => -74.0060,
                'altitude' => 35000,
                'speed' => 500,
                'timestamp' => time() - 10
            ]
        ];

        // Process noisy data
        foreach ($noisyData as $data) {
            $this->adsbProcessor->processMessage($data);
        }

        // Apply filtering
        $filteredData = $this->adsbProcessor->filterAircraftData($icao24);

        $this->assertTrue($filteredData['success']);
        $this->assertArrayHasKey('filtered_positions', $filteredData);

        $filtered = $filteredData['filtered_positions'];

        // Verify outlier was filtered out
        foreach ($filtered as $position) {
            $this->assertNotEquals(41.0000, $position['latitude']);
        }

        // Verify most positions remain
        $this->assertGreaterThan(3, count($filtered));
    }

    /**
     * Test ADS-B performance metrics
     */
    public function testADSBPerformanceMetrics()
    {
        // Simulate high-volume ADS-B data processing
        $startTime = microtime(true);
        $messagesProcessed = 0;

        for ($i = 0; $i < 1000; $i++) {
            $adsbMessage = [
                'icao24' => 'PERF' . str_pad($i, 3, '0', STR_PAD_LEFT),
                'latitude' => 40.7128 + ($i * 0.001),
                'longitude' => -74.0060 + ($i * 0.001),
                'altitude' => 35000 + ($i * 10),
                'speed' => 500,
                'heading' => 90,
                'timestamp' => time()
            ];

            $this->adsbProcessor->processMessage($adsbMessage);
            $messagesProcessed++;
        }

        $endTime = microtime(true);
        $processingTime = $endTime - $startTime;

        // Calculate performance metrics
        $messagesPerSecond = $messagesProcessed / $processingTime;
        $averageLatency = ($processingTime / $messagesProcessed) * 1000; // milliseconds

        // Verify performance requirements
        $this->assertGreaterThan(100, $messagesPerSecond); // At least 100 messages/second
        $this->assertLessThan(50, $averageLatency); // Less than 50ms average latency

        // Test memory usage
        $memoryUsage = memory_get_peak_usage(true);
        $this->assertLessThan(100 * 1024 * 1024, $memoryUsage); // Less than 100MB

        // Log performance metrics
        $this->adsbProcessor->logPerformanceMetrics([
            'messages_processed' => $messagesProcessed,
            'processing_time' => $processingTime,
            'messages_per_second' => $messagesPerSecond,
            'average_latency' => $averageLatency,
            'memory_usage' => $memoryUsage
        ]);
    }
}
