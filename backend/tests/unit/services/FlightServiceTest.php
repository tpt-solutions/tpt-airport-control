<?php
/**
 * Unit tests for FlightService
 */

require_once __DIR__ . '/../../../services/FlightService.php';
require_once __DIR__ . '/../../../repositories/FlightRepository.php';
require_once __DIR__ . '/../helpers/TestDataFactory.php';

class FlightServiceTest extends PHPUnit_Framework_TestCase
{
    private $flightService;
    private $mockRepository;
    private $testDataFactory;

    protected function setUp()
    {
        // Create mock repository
        $this->mockRepository = $this->getMockBuilder('FlightRepository')
            ->disableOriginalConstructor()
            ->getMock();

        // Create service with mock repository
        $this->flightService = new FlightService($this->mockRepository);
        $this->testDataFactory = new TestDataFactory();
    }

    public function testGetFlights()
    {
        $filters = ['status' => 'scheduled'];
        $pagination = ['page' => 1, 'limit' => 50, 'offset' => 0];
        $expectedFlights = [
            $this->testDataFactory->createFlightData(['id' => 1]),
            $this->testDataFactory->createFlightData(['id' => 2])
        ];

        $this->mockRepository->expects($this->once())
            ->method('findByFilters')
            ->with($filters, $pagination)
            ->willReturn($expectedFlights);

        $result = $this->flightService->getFlights($filters, $pagination);

        $this->assertEquals($expectedFlights, $result);
    }

    public function testGetFlightById()
    {
        $flightId = 1;
        $expectedFlight = $this->testDataFactory->createFlightData(['id' => $flightId]);

        $this->mockRepository->expects($this->once())
            ->method('findById')
            ->with($flightId)
            ->willReturn($expectedFlight);

        $result = $this->flightService->getFlightById($flightId);

        $this->assertEquals($expectedFlight, $result);
    }

    public function testGetFlightByIdNotFound()
    {
        $flightId = 999;

        $this->mockRepository->expects($this->once())
            ->method('findById')
            ->with($flightId)
            ->willReturn(null);

        $result = $this->flightService->getFlightById($flightId);

        $this->assertNull($result);
    }

    public function testGetActiveFlights()
    {
        $activeFlights = [
            $this->testDataFactory->createFlightData(['id' => 1, 'status' => 'scheduled']),
            $this->testDataFactory->createFlightData(['id' => 2, 'status' => 'boarding'])
        ];

        $this->mockRepository->expects($this->once())
            ->method('findActive')
            ->willReturn($activeFlights);

        $result = $this->flightService->getActiveFlights();

        $this->assertEquals($activeFlights, $result);
    }

    public function testCreateFlightSuccess()
    {
        $flightData = $this->testDataFactory->createFlightData();
        $createdFlight = $flightData;
        $createdFlight['id'] = 1;

        $this->mockRepository->expects($this->once())
            ->method('create')
            ->with($flightData)
            ->willReturn($createdFlight);

        $result = $this->flightService->createFlight($flightData);

        $this->assertEquals($createdFlight, $result);
        $this->assertArrayHasKey('id', $result);
    }

    public function testCreateFlightValidationFailure()
    {
        $invalidData = $this->testDataFactory->createFlightData();
        $invalidData['flight_number'] = ''; // Invalid flight number

        $this->expectException('Exception');
        $this->expectExceptionMessage('Flight number is required');

        $this->flightService->createFlight($invalidData);
    }

    public function testUpdateFlightSuccess()
    {
        $flightId = 1;
        $updateData = ['status' => 'boarding', 'gate' => 'A12'];
        $existingFlight = $this->testDataFactory->createFlightData(['id' => $flightId]);
        $updatedFlight = array_merge($existingFlight, $updateData);

        $this->mockRepository->expects($this->once())
            ->method('findById')
            ->with($flightId)
            ->willReturn($existingFlight);

        $this->mockRepository->expects($this->once())
            ->method('update')
            ->with($flightId, $this->callback(function($data) use ($updateData) {
                return isset($data['status']) && $data['status'] === 'boarding';
            }))
            ->willReturn($updatedFlight);

        $result = $this->flightService->updateFlight($flightId, $updateData);

        $this->assertEquals($updatedFlight, $result);
    }

    public function testUpdateFlightNotFound()
    {
        $flightId = 999;
        $updateData = ['status' => 'cancelled'];

        $this->mockRepository->expects($this->once())
            ->method('findById')
            ->with($flightId)
            ->willReturn(null);

        $this->expectException('Exception');
        $this->expectExceptionMessage('Flight not found');

        $this->flightService->updateFlight($flightId, $updateData);
    }

    public function testDeleteFlightSuccess()
    {
        $flightId = 1;
        $existingFlight = $this->testDataFactory->createFlightData([
            'id' => $flightId,
            'status' => 'scheduled'
        ]);

        $this->mockRepository->expects($this->once())
            ->method('findById')
            ->with($flightId)
            ->willReturn($existingFlight);

        $this->mockRepository->expects($this->once())
            ->method('delete')
            ->with($flightId)
            ->willReturn(true);

        $result = $this->flightService->deleteFlight($flightId);

        $this->assertTrue($result);
    }

    public function testDeleteFlightWithBookings()
    {
        $flightId = 1;
        $existingFlight = $this->testDataFactory->createFlightData([
            'id' => $flightId,
            'status' => 'scheduled'
        ]);

        $this->mockRepository->expects($this->once())
            ->method('findById')
            ->with($flightId)
            ->willReturn($existingFlight);

        $this->mockRepository->expects($this->once())
            ->method('hasBookings')
            ->with($flightId)
            ->willReturn(true);

        $this->expectException('Exception');
        $this->expectExceptionMessage('Cannot delete flight with existing bookings');

        $this->flightService->deleteFlight($flightId);
    }

    public function testAssignGateSuccess()
    {
        $flightId = 1;
        $gate = 'B15';
        $existingFlight = $this->testDataFactory->createFlightData([
            'id' => $flightId,
            'status' => 'scheduled'
        ]);

        $this->mockRepository->expects($this->once())
            ->method('findById')
            ->with($flightId)
            ->willReturn($existingFlight);

        $this->mockRepository->expects($this->once())
            ->method('isGateAvailable')
            ->with($gate, $existingFlight['scheduled_departure'])
            ->willReturn(true);

        $this->mockRepository->expects($this->once())
            ->method('update')
            ->with($flightId, $this->callback(function($data) use ($gate) {
                return isset($data['gate']) && $data['gate'] === $gate;
            }))
            ->willReturn(array_merge($existingFlight, ['gate' => $gate]));

        $result = $this->flightService->assignGate($flightId, $gate);

        $this->assertArrayHasKey('gate', $result);
        $this->assertEquals($gate, $result['gate']);
    }

    public function testAssignGateConflict()
    {
        $flightId = 1;
        $gate = 'B15';
        $existingFlight = $this->testDataFactory->createFlightData([
            'id' => $flightId,
            'status' => 'scheduled'
        ]);

        $this->mockRepository->expects($this->once())
            ->method('findById')
            ->with($flightId)
            ->willReturn($existingFlight);

        $this->mockRepository->expects($this->once())
            ->method('isGateAvailable')
            ->with($gate, $existingFlight['scheduled_departure'])
            ->willReturn(false);

        $this->expectException('Exception');
        $this->expectExceptionMessage('Gate is not available');

        $this->flightService->assignGate($flightId, $gate);
    }

    public function testSearchFlights()
    {
        $searchTerm = 'AA101';
        $filters = ['origin' => 'JFK'];
        $expectedResults = [
            $this->testDataFactory->createFlightData(['flight_number' => 'AA101'])
        ];

        $this->mockRepository->expects($this->once())
            ->method('search')
            ->with($searchTerm, $filters)
            ->willReturn($expectedResults);

        $result = $this->flightService->searchFlights($searchTerm, $filters);

        $this->assertEquals($expectedResults, $result);
    }

    public function testGetFlightStatistics()
    {
        $expectedStats = [
            'total_flights' => 150,
            'active_flights' => 45,
            'delayed_flights' => 5,
            'cancelled_flights' => 2
        ];

        $this->mockRepository->expects($this->once())
            ->method('getStatistics')
            ->willReturn($expectedStats);

        $result = $this->flightService->getFlightStatistics();

        $this->assertEquals($expectedStats, $result);
        $this->assertArrayHasKey('total_flights', $result);
        $this->assertArrayHasKey('active_flights', $result);
    }

    public function testValidateFlightData()
    {
        $validData = $this->testDataFactory->createFlightData();

        // Should not throw exception for valid data
        $this->flightService->validateFlightData($validData);

        // Test invalid data
        $invalidData = $validData;
        $invalidData['flight_number'] = '';

        $this->expectException('Exception');
        $this->flightService->validateFlightData($invalidData);
    }

    public function testBusinessRuleValidation()
    {
        // Test departure before arrival validation
        $invalidData = $this->testDataFactory->createFlightData();
        $invalidData['scheduled_departure'] = '2025-01-15 15:00:00';
        $invalidData['scheduled_arrival'] = '2025-01-15 12:00:00'; // Arrival before departure

        $this->expectException('Exception');
        $this->expectExceptionMessage('Arrival time must be after departure time');

        $this->flightService->validateFlightData($invalidData);
    }

    public function testFlightStatusTransitionValidation()
    {
        $flightId = 1;
        $existingFlight = $this->testDataFactory->createFlightData([
            'id' => $flightId,
            'status' => 'departed'
        ]);

        $this->mockRepository->expects($this->once())
            ->method('findById')
            ->with($flightId)
            ->willReturn($existingFlight);

        // Try to change status back to scheduled (invalid transition)
        $this->expectException('Exception');
        $this->expectExceptionMessage('Invalid status transition');

        $this->flightService->updateFlight($flightId, ['status' => 'scheduled']);
    }
}
