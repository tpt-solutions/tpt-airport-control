<?php
/**
 * Unit tests for Flight model
 */

require_once __DIR__ . '/../../../models/Flight.php';
require_once __DIR__ . '/../helpers/TestDataFactory.php';

class FlightTest extends PHPUnit\Framework\TestCase
{
    private $testDataFactory;

    protected function setUp(): void
    {
        $this->testDataFactory = new TestDataFactory();
    }

    public function testFlightCreation()
    {
        $flightData = $this->testDataFactory->createFlightData();
        $flight = new Flight($flightData);

        $this->assertInstanceOf('Flight', $flight);
        $this->assertEquals($flightData['flight_number'], $flight->getFlightNumber());
        $this->assertEquals($flightData['origin'], $flight->getOrigin());
        $this->assertEquals($flightData['destination'], $flight->getDestination());
    }

    public function testFlightStatusMethods()
    {
        $flightData = $this->testDataFactory->createFlightData(['status' => 'scheduled']);
        $flight = new Flight($flightData);

        $this->assertTrue($flight->isScheduled());
        $this->assertFalse($flight->isDeparted());
        $this->assertFalse($flight->isArrived());
        $this->assertFalse($flight->isCancelled());
        $this->assertFalse($flight->isDelayed());

        // Test status changes
        $flight->setStatus('departed');
        $this->assertTrue($flight->isDeparted());
        $this->assertFalse($flight->isScheduled());
    }

    public function testFlightTimeCalculations()
    {
        $departureTime = '2025-01-15 10:00:00';
        $arrivalTime = '2025-01-15 12:30:00';

        $flightData = $this->testDataFactory->createFlightData([
            'scheduled_departure' => $departureTime,
            'scheduled_arrival' => $arrivalTime
        ]);
        $flight = new Flight($flightData);

        $this->assertEquals(150, $flight->getFlightDuration()); // 2.5 hours = 150 minutes
        $this->assertTrue($flight->isOnTime());
    }

    public function testFlightDelayHandling()
    {
        $flightData = $this->testDataFactory->createFlightData([
            'status' => 'scheduled',
            'scheduled_departure' => '2025-01-15 10:00:00'
        ]);
        $flight = new Flight($flightData);

        // Test delay setting
        $flight->setDelay(30); // 30 minutes delay
        $this->assertEquals(30, $flight->getDelayMinutes());
        $this->assertTrue($flight->isDelayed());
        $this->assertEquals('delayed', $flight->getStatus());

        // Test estimated departure calculation
        $expectedDeparture = strtotime('2025-01-15 10:30:00');
        $this->assertEquals($expectedDeparture, $flight->getEstimatedDepartureTime());
    }

    public function testFlightValidation()
    {
        // Test valid flight
        $validData = $this->testDataFactory->createFlightData();
        $flight = new Flight($validData);
        $this->assertTrue($flight->isValid());

        // Test invalid flight number
        $invalidData = $validData;
        $invalidData['flight_number'] = '';
        try {
            new Flight($invalidData);
            $this->fail('Expected exception for invalid flight number');
        } catch (Exception $e) {
            $this->assertStringContainsString('flight number', strtolower($e->getMessage()));
        }

        // Test invalid airport codes
        $invalidData = $validData;
        $invalidData['origin'] = 'INVALID';
        try {
            new Flight($invalidData);
            $this->fail('Expected exception for invalid origin airport');
        } catch (Exception $e) {
            $this->assertStringContainsString('airport', strtolower($e->getMessage()));
        }
    }

    public function testFlightCapacityManagement()
    {
        $flightData = $this->testDataFactory->createFlightData([
            'aircraft_capacity' => 150,
            'booked_seats' => 120
        ]);
        $flight = new Flight($flightData);

        $this->assertEquals(150, $flight->getCapacity());
        $this->assertEquals(120, $flight->getBookedSeats());
        $this->assertEquals(30, $flight->getAvailableSeats());
        $this->assertFalse($flight->isFull());

        // Test capacity exceeded
        $flight->setBookedSeats(150);
        $this->assertTrue($flight->isFull());
        $this->assertEquals(0, $flight->getAvailableSeats());
    }

    public function testFlightToArrayConversion()
    {
        $flightData = $this->testDataFactory->createFlightData();
        $flight = new Flight($flightData);

        $array = $flight->toArray();

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('flight_number', $array);
        $this->assertArrayHasKey('origin', $array);
        $this->assertArrayHasKey('destination', $array);
        $this->assertArrayHasKey('status', $array);
        $this->assertArrayHasKey('scheduled_departure', $array);
        $this->assertArrayHasKey('scheduled_arrival', $array);

        // Ensure sensitive data is not exposed
        $this->assertArrayNotHasKey('internal_notes', $array);
    }

    public function testFlightBusinessRules()
    {
        $flightData = $this->testDataFactory->createFlightData([
            'status' => 'scheduled',
            'scheduled_departure' => date('Y-m-d H:i:s', strtotime('+2 hours'))
        ]);
        $flight = new Flight($flightData);

        // Test gate assignment rules
        $this->assertTrue($flight->canAssignGate());
        $flight->setStatus('departed');
        $this->assertFalse($flight->canAssignGate());

        // Test cancellation rules
        $flight->setStatus('scheduled');
        $this->assertTrue($flight->canBeCancelled());
        $flight->setStatus('departed');
        $this->assertFalse($flight->canBeCancelled());
    }

    public function testFlightComparison()
    {
        $flight1 = new Flight($this->testDataFactory->createFlightData(['id' => 1]));
        $flight2 = new Flight($this->testDataFactory->createFlightData(['id' => 1]));
        $flight3 = new Flight($this->testDataFactory->createFlightData(['id' => 2]));

        $this->assertTrue($flight1->equals($flight2));
        $this->assertFalse($flight1->equals($flight3));
    }
}
