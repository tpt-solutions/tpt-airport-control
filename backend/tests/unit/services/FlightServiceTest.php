<?php
/**
 * Unit tests for FlightService
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../services/FlightService.php';
require_once __DIR__ . '/../../../repositories/FlightRepository.php';
require_once __DIR__ . '/../../../models/Flight.php';

class FlightServiceTest extends TestCase
{
    private FlightService $flightService;
    private $mockRepository;

    protected function setUp(): void
    {
        $this->mockRepository = $this->createMock(FlightRepository::class);
        $this->flightService = new FlightService(null, $this->mockRepository);
    }

    private function makeFlight(array $overrides = []): Flight
    {
        return new Flight(array_merge([
            'id' => 1,
            'flight_number' => 'TA001',
            'origin' => 'JFK',
            'destination' => 'LAX',
            'scheduled_departure' => '2025-01-15 08:00:00',
            'scheduled_arrival' => '2025-01-15 11:00:00',
            'status' => 'scheduled',
        ], $overrides));
    }

    public function testGetFlightsReturnsPaginatedResult(): void
    {
        $this->mockRepository->method('findAll')->willReturn([$this->makeFlight()]);
        $this->mockRepository->method('count')->willReturn(1);

        $result = $this->flightService->getFlights(['status' => 'scheduled'], ['page' => 1, 'limit' => 50]);

        $this->assertArrayHasKey('flights', $result);
        $this->assertArrayHasKey('pagination', $result);
        $this->assertCount(1, $result['flights']);
        $this->assertEquals(1, $result['pagination']['total']);
    }

    public function testGetFlightByIdReturnsFlightData(): void
    {
        $this->mockRepository->method('findById')->with(1)->willReturn($this->makeFlight());

        $result = $this->flightService->getFlightById(1);

        $this->assertArrayHasKey('flight', $result);
        $this->assertEquals('TA001', $result['flight']['flight_number']);
    }

    public function testGetFlightByIdThrowsWhenNotFound(): void
    {
        $this->mockRepository->method('findById')->willReturn(null);
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Flight not found');

        $this->flightService->getFlightById(999);
    }

    public function testCreateFlightThrowsOnMissingFields(): void
    {
        $this->expectException(Exception::class);

        $this->flightService->createFlight([
            'flight_number' => '',
            'origin' => 'JFK',
            'destination' => 'LAX',
        ]);
    }

    public function testUpdateFlightThrowsWhenNotFound(): void
    {
        $this->mockRepository->method('findById')->willReturn(null);
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Flight not found');

        $this->flightService->updateFlight(999, ['status' => 'cancelled']);
    }

    public function testDeleteFlightThrowsWhenNotFound(): void
    {
        $this->mockRepository->method('findById')->willReturn(null);
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Flight not found');

        $this->flightService->deleteFlight(999);
    }

    public function testDeleteFlightSuccess(): void
    {
        $this->mockRepository->method('findById')->willReturn($this->makeFlight());
        $this->mockRepository->method('delete')->willReturn(true);

        $result = $this->flightService->deleteFlight(1);

        $this->assertArrayHasKey('message', $result);
        $this->assertEquals('Flight deleted successfully', $result['message']);
    }

    public function testAssignGateThrowsWhenFlightNotFound(): void
    {
        $this->mockRepository->method('findById')->willReturn(null);
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Flight not found');

        $this->flightService->assignGate(999, 'A1');
    }

    public function testAssignGateSuccess(): void
    {
        $this->mockRepository->method('findById')->willReturn($this->makeFlight());
        $this->mockRepository->method('assignGate')->willReturn(true);

        $result = $this->flightService->assignGate(1, 'B15');

        $this->assertArrayHasKey('message', $result);
        $this->assertEquals('Gate assigned successfully', $result['message']);
    }

    public function testSearchFlightsReturnsFlightsArray(): void
    {
        $this->mockRepository->method('search')->willReturn([$this->makeFlight(['flight_number' => 'AA101'])]);

        $result = $this->flightService->searchFlights('AA101');

        $this->assertArrayHasKey('flights', $result);
        $this->assertCount(1, $result['flights']);
    }

    public function testGetActiveFlightsReturnsFlightsArray(): void
    {
        $this->mockRepository->method('getActiveFlights')->willReturn([$this->makeFlight(['status' => 'boarding'])]);

        $result = $this->flightService->getActiveFlights();

        $this->assertArrayHasKey('flights', $result);
        $this->assertCount(1, $result['flights']);
    }

    public function testGetFlightStatisticsReturnsStats(): void
    {
        $this->mockRepository->method('count')->willReturnMap([
            [[], 100],
            [['status' => 'scheduled'], 45],
            [['status' => 'departed'], 30],
            [['status' => 'arrived'], 25],
        ]);

        $result = $this->flightService->getFlightStatistics();

        $this->assertArrayHasKey('total_flights', $result);
        $this->assertArrayHasKey('active_flights', $result);
        $this->assertEquals(100, $result['total_flights']);
        $this->assertEquals(45, $result['active_flights']);
    }
}
