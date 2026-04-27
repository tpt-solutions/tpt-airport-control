<?php
/**
 * Flight Management Integration Tests
 *
 * Tests the complete flight management workflow including
 * flight creation, passenger booking, check-in, and status updates
 */

use PHPUnit\Framework\TestCase;

class FlightManagementIntegrationTest extends TestCase
{
    private $db;
    private $flightApi;
    private $passengerApi;
    private $bookingApi;
    private $checkinApi;
    private $websocketServer;

    protected function setUp(): void
    {
        // Set up test database connection
        $this->db = $this->createMock(PDO::class);

        // Initialize API components
        $this->flightApi = new FlightAPI($this->db);
        $this->passengerApi = new PassengerAPI($this->db);
        $this->bookingApi = new BookingAPI($this->db);
        $this->checkinApi = new CheckinAPI($this->db);
        $this->websocketServer = new WebSocketServer();
    }

    /**
     * Test complete flight lifecycle from creation to completion
     */
    public function testCompleteFlightLifecycle()
    {
        // Step 1: Create a flight
        $flightData = [
            'flight_number' => 'AA101',
            'airline_id' => 1,
            'aircraft_id' => 1,
            'origin' => 'JFK',
            'destination' => 'LAX',
            'scheduled_departure' => '2025-09-15 08:00:00',
            'scheduled_arrival' => '2025-09-15 11:30:00',
            'status' => 'scheduled'
        ];

        $flightResult = $this->flightApi->createFlight($flightData);

        $this->assertTrue($flightResult['success']);
        $this->assertArrayHasKey('flight_id', $flightResult);

        $flightId = $flightResult['flight_id'];

        // Step 2: Create passenger
        $passengerData = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'phone' => '+1-555-0123',
            'passport_number' => 'P123456789',
            'nationality' => 'USA',
            'date_of_birth' => '1990-01-15'
        ];

        $passengerResult = $this->passengerApi->createPassenger($passengerData);

        $this->assertTrue($passengerResult['success']);
        $this->assertArrayHasKey('passenger_id', $passengerResult);

        $passengerId = $passengerResult['passenger_id'];

        // Step 3: Create booking
        $bookingData = [
            'passenger_id' => $passengerId,
            'flight_id' => $flightId,
            'seat_number' => '12A',
            'booking_reference' => 'ABC123',
            'total_amount' => 450.00,
            'currency' => 'USD'
        ];

        $bookingResult = $this->bookingApi->createBooking($bookingData);

        $this->assertTrue($bookingResult['success']);
        $this->assertArrayHasKey('booking_id', $bookingResult);

        $bookingId = $bookingResult['booking_id'];

        // Step 4: Update flight status to boarding
        $statusUpdate = $this->flightApi->updateFlightStatus($flightId, 'boarding');

        $this->assertTrue($statusUpdate['success']);

        // Step 5: Perform check-in
        $checkinData = [
            'booking_id' => $bookingId,
            'check_in_time' => date('Y-m-d H:i:s'),
            'boarding_pass_issued' => true,
            'security_cleared' => true
        ];

        $checkinResult = $this->checkinApi->checkInPassenger($checkinData);

        $this->assertTrue($checkinResult['success']);

        // Step 6: Update flight to departed
        $departureUpdate = $this->flightApi->updateFlightStatus($flightId, 'departed', [
            'actual_departure' => date('Y-m-d H:i:s')
        ]);

        $this->assertTrue($departureUpdate['success']);

        // Step 7: Update flight to arrived
        $arrivalUpdate = $this->flightApi->updateFlightStatus($flightId, 'arrived', [
            'actual_arrival' => date('Y-m-d H:i:s', strtotime('+3 hours'))
        ]);

        $this->assertTrue($arrivalUpdate['success']);

        // Verify final flight status
        $finalFlight = $this->flightApi->getFlight($flightId);

        $this->assertEquals('arrived', $finalFlight['status']);
        $this->assertNotNull($finalFlight['actual_departure']);
        $this->assertNotNull($finalFlight['actual_arrival']);
    }

    /**
     * Test flight search and filtering
     */
    public function testFlightSearchAndFiltering()
    {
        // Create test flights
        $flights = [
            [
                'flight_number' => 'UA202',
                'origin' => 'ORD',
                'destination' => 'SFO',
                'scheduled_departure' => '2025-09-15 10:00:00',
                'status' => 'scheduled'
            ],
            [
                'flight_number' => 'UA303',
                'origin' => 'ORD',
                'destination' => 'DEN',
                'scheduled_departure' => '2025-09-15 14:00:00',
                'status' => 'boarding'
            ],
            [
                'flight_number' => 'UA404',
                'origin' => 'SFO',
                'destination' => 'ORD',
                'scheduled_departure' => '2025-09-15 16:00:00',
                'status' => 'departed'
            ]
        ];

        foreach ($flights as $flight) {
            $this->flightApi->createFlight($flight);
        }

        // Test search by origin/destination
        $searchResults = $this->flightApi->searchFlights([
            'origin' => 'ORD',
            'destination' => 'SFO'
        ]);

        $this->assertCount(1, $searchResults);
        $this->assertEquals('UA202', $searchResults[0]['flight_number']);

        // Test search by date range
        $dateSearch = $this->flightApi->searchFlights([
            'departure_date' => '2025-09-15',
            'status' => 'scheduled'
        ]);

        $this->assertCount(1, $dateSearch);
        $this->assertEquals('UA202', $dateSearch[0]['flight_number']);

        // Test search by status
        $statusSearch = $this->flightApi->searchFlights([
            'status' => 'boarding'
        ]);

        $this->assertCount(1, $statusSearch);
        $this->assertEquals('UA303', $statusSearch[0]['flight_number']);
    }

    /**
     * Test passenger booking workflow
     */
    public function testPassengerBookingWorkflow()
    {
        // Create flight
        $flightData = [
            'flight_number' => 'DL500',
            'origin' => 'ATL',
            'destination' => 'JFK',
            'scheduled_departure' => '2025-09-16 09:00:00',
            'scheduled_arrival' => '2025-09-16 11:30:00'
        ];

        $flightResult = $this->flightApi->createFlight($flightData);
        $flightId = $flightResult['flight_id'];

        // Create multiple passengers
        $passengers = [
            ['first_name' => 'Alice', 'last_name' => 'Smith', 'email' => 'alice@example.com'],
            ['first_name' => 'Bob', 'last_name' => 'Johnson', 'email' => 'bob@example.com'],
            ['first_name' => 'Carol', 'last_name' => 'Williams', 'email' => 'carol@example.com']
        ];

        $bookingIds = [];
        foreach ($passengers as $index => $passenger) {
            // Create passenger
            $passengerResult = $this->passengerApi->createPassenger($passenger);
            $passengerId = $passengerResult['passenger_id'];

            // Create booking
            $bookingData = [
                'passenger_id' => $passengerId,
                'flight_id' => $flightId,
                'seat_number' => '1' . chr(65 + $index), // 1A, 1B, 1C
                'booking_reference' => 'DL5' . str_pad($index + 1, 2, '0', STR_PAD_LEFT),
                'total_amount' => 350.00
            ];

            $bookingResult = $this->bookingApi->createBooking($bookingData);
            $bookingIds[] = $bookingResult['booking_id'];
        }

        // Verify all bookings created
        $this->assertCount(3, $bookingIds);

        // Test booking retrieval
        foreach ($bookingIds as $bookingId) {
            $booking = $this->bookingApi->getBooking($bookingId);
            $this->assertNotNull($booking);
            $this->assertEquals($flightId, $booking['flight_id']);
        }

        // Test booking cancellation
        $cancelResult = $this->bookingApi->cancelBooking($bookingIds[0]);

        $this->assertTrue($cancelResult['success']);

        // Verify cancellation
        $cancelledBooking = $this->bookingApi->getBooking($bookingIds[0]);
        $this->assertEquals('cancelled', $cancelledBooking['status']);
    }

    /**
     * Test real-time flight updates via WebSocket
     */
    public function testRealTimeFlightUpdates()
    {
        // Create flight
        $flightData = [
            'flight_number' => 'SW600',
            'origin' => 'LAS',
            'destination' => 'DEN',
            'scheduled_departure' => '2025-09-17 12:00:00'
        ];

        $flightResult = $this->flightApi->createFlight($flightData);
        $flightId = $flightResult['flight_id'];

        // Simulate WebSocket connection
        $clientId = 'test_client_' . uniqid();

        // Subscribe to flight updates
        $subscriptionResult = $this->websocketServer->subscribeToFlight($clientId, $flightId);

        $this->assertTrue($subscriptionResult['success']);

        // Update flight status
        $updateResult = $this->flightApi->updateFlightStatus($flightId, 'boarding');

        $this->assertTrue($updateResult['success']);

        // Verify WebSocket message was sent
        $messages = $this->websocketServer->getMessagesForClient($clientId);

        $this->assertNotEmpty($messages);

        $lastMessage = end($messages);
        $this->assertEquals('flight_update', $lastMessage['type']);
        $this->assertEquals($flightId, $lastMessage['data']['flight_id']);
        $this->assertEquals('boarding', $lastMessage['data']['status']);
    }

    /**
     * Test flight delay and disruption handling
     */
    public function testFlightDelayHandling()
    {
        // Create flight
        $flightData = [
            'flight_number' => 'LH777',
            'origin' => 'FRA',
            'destination' => 'JFK',
            'scheduled_departure' => '2025-09-18 14:30:00',
            'scheduled_arrival' => '2025-09-18 17:00:00'
        ];

        $flightResult = $this->flightApi->createFlight($flightData);
        $flightId = $flightResult['flight_id'];

        // Create passenger and booking
        $passengerResult = $this->passengerApi->createPassenger([
            'first_name' => 'Hans',
            'last_name' => 'Mueller',
            'email' => 'hans@example.com'
        ]);

        $bookingResult = $this->bookingApi->createBooking([
            'passenger_id' => $passengerResult['passenger_id'],
            'flight_id' => $flightId,
            'seat_number' => '15C',
            'booking_reference' => 'LH777001'
        ]);

        // Simulate flight delay
        $delayResult = $this->flightApi->delayFlight($flightId, 120, 'Weather conditions');

        $this->assertTrue($delayResult['success']);

        // Verify flight status changed to delayed
        $delayedFlight = $this->flightApi->getFlight($flightId);

        $this->assertEquals('delayed', $delayedFlight['status']);
        $this->assertStringContains('Weather conditions', $delayedFlight['delay_reason']);

        // Test passenger notification (mock)
        $notificationSent = $this->bookingApi->notifyPassengerOfDelay($bookingResult['booking_id']);

        $this->assertTrue($notificationSent['success']);

        // Test flight rescheduling
        $newDeparture = '2025-09-18 16:30:00';
        $rescheduleResult = $this->flightApi->rescheduleFlight($flightId, $newDeparture);

        $this->assertTrue($rescheduleResult['success']);

        // Verify rescheduled time
        $rescheduledFlight = $this->flightApi->getFlight($flightId);

        $this->assertEquals($newDeparture, $rescheduledFlight['scheduled_departure']);
    }

    /**
     * Test baggage tracking integration
     */
    public function testBaggageTrackingIntegration()
    {
        // Create flight and passenger
        $flightResult = $this->flightApi->createFlight([
            'flight_number' => 'AF001',
            'origin' => 'CDG',
            'destination' => 'NCE'
        ]);

        $passengerResult = $this->passengerApi->createPassenger([
            'first_name' => 'Pierre',
            'last_name' => 'Dubois'
        ]);

        $bookingResult = $this->bookingApi->createBooking([
            'passenger_id' => $passengerResult['passenger_id'],
            'flight_id' => $flightResult['flight_id'],
            'booking_reference' => 'AF001001'
        ]);

        // Create baggage
        $baggageApi = new BaggageAPI($this->db);

        $baggageData = [
            'booking_id' => $bookingResult['booking_id'],
            'tag_number' => 'AF001001001',
            'weight' => 23.5,
            'status' => 'checked'
        ];

        $baggageResult = $baggageApi->createBaggage($baggageData);

        $this->assertTrue($baggageResult['success']);

        $baggageId = $baggageResult['baggage_id'];

        // Update baggage status through journey
        $statusUpdates = ['loaded', 'unloaded', 'claimed'];

        foreach ($statusUpdates as $status) {
            $updateResult = $baggageApi->updateBaggageStatus($baggageId, $status);
            $this->assertTrue($updateResult['success']);

            // Verify status update
            $baggage = $baggageApi->getBaggage($baggageId);
            $this->assertEquals($status, $baggage['status']);
        }

        // Test baggage search
        $foundBaggage = $baggageApi->findBaggageByTag('AF001001001');

        $this->assertNotNull($foundBaggage);
        $this->assertEquals($baggageId, $foundBaggage['id']);
        $this->assertEquals('claimed', $foundBaggage['status']);
    }

    /**
     * Test flight capacity and seat allocation
     */
    public function testFlightCapacityAndSeatAllocation()
    {
        // Create flight with limited capacity
        $flightData = [
            'flight_number' => 'BA005',
            'aircraft_id' => 1, // Assume aircraft with capacity 100
            'origin' => 'LHR',
            'destination' => 'JFK'
        ];

        $flightResult = $this->flightApi->createFlight($flightData);
        $flightId = $flightResult['flight_id'];

        // Fill flight to capacity
        $passengersCreated = 0;
        $bookingsCreated = 0;

        for ($i = 0; $i < 105; $i++) { // Try to overbook
            $passengerResult = $this->passengerApi->createPassenger([
                'first_name' => 'Passenger',
                'last_name' => (string)$i,
                'email' => "passenger{$i}@example.com"
            ]);

            $bookingData = [
                'passenger_id' => $passengerResult['passenger_id'],
                'flight_id' => $flightId,
                'seat_number' => str_pad($i + 1, 3, '0', STR_PAD_LEFT) . 'A',
                'booking_reference' => 'BA' . str_pad($i + 1, 3, '0', STR_PAD_LEFT)
            ];

            $bookingResult = $this->bookingApi->createBooking($bookingData);

            if ($bookingResult['success']) {
                $bookingsCreated++;
            }
        }

        // Verify capacity limits were enforced
        $this->assertLessThanOrEqual(100, $bookingsCreated);

        // Test waitlist functionality
        $waitlistResult = $this->bookingApi->addToWaitlist($flightId, $passengerResult['passenger_id']);

        $this->assertTrue($waitlistResult['success']);

        // Test seat availability
        $availableSeats = $this->flightApi->getAvailableSeats($flightId);

        $this->assertIsArray($availableSeats);
        $this->assertLessThanOrEqual(100 - $bookingsCreated, count($availableSeats));
    }

    /**
     * Test multi-leg flight handling
     */
    public function testMultiLegFlightHandling()
    {
        // Create connecting flights
        $legs = [
            [
                'flight_number' => 'UA123',
                'origin' => 'SFO',
                'destination' => 'DEN',
                'scheduled_departure' => '2025-09-20 06:00:00',
                'scheduled_arrival' => '2025-09-20 09:30:00'
            ],
            [
                'flight_number' => 'UA456',
                'origin' => 'DEN',
                'destination' => 'ORD',
                'scheduled_departure' => '2025-09-20 10:30:00',
                'scheduled_arrival' => '2025-09-20 13:00:00'
            ],
            [
                'flight_number' => 'UA789',
                'origin' => 'ORD',
                'destination' => 'BOS',
                'scheduled_departure' => '2025-09-20 14:00:00',
                'scheduled_arrival' => '2025-09-20 17:30:00'
            ]
        ];

        $flightIds = [];
        foreach ($legs as $leg) {
            $result = $this->flightApi->createFlight($leg);
            $flightIds[] = $result['flight_id'];
        }

        // Create passenger journey
        $passengerResult = $this->passengerApi->createPassenger([
            'first_name' => 'Sarah',
            'last_name' => 'Connor',
            'email' => 'sarah@example.com'
        ]);

        $passengerId = $passengerResult['passenger_id'];

        // Book all legs
        $bookingReferences = [];
        foreach ($flightIds as $index => $flightId) {
            $bookingResult = $this->bookingApi->createBooking([
                'passenger_id' => $passengerId,
                'flight_id' => $flightId,
                'seat_number' => '2' . chr(65 + $index),
                'booking_reference' => 'UA' . str_pad($index + 1, 3, '0', STR_PAD_LEFT)
            ]);

            $bookingReferences[] = $bookingResult['booking_reference'];
        }

        // Test journey tracking
        $journey = $this->bookingApi->getPassengerJourney($passengerId, '2025-09-20');

        $this->assertCount(3, $journey['flights']);
        $this->assertEquals('SFO', $journey['origin']);
        $this->assertEquals('BOS', $journey['destination']);

        // Test connection time calculation
        $connectionTime = $this->flightApi->calculateConnectionTime($flightIds[0], $flightIds[1]);

        $this->assertEquals(60, $connectionTime); // 60 minutes layover

        // Test missed connection handling
        $delayResult = $this->flightApi->delayFlight($flightIds[0], 90); // 90 minute delay

        $this->assertTrue($delayResult['success']);

        // Check for missed connection
        $missedConnection = $this->bookingApi->checkMissedConnection($bookingReferences[1]);

        $this->assertTrue($missedConnection['missed']);
        $this->assertGreaterThan(0, $missedConnection['delay_minutes']);
    }
}
