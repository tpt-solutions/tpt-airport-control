<?php
/**
 * Test Data Factory for Flight Control System
 * Provides consistent test data across all unit tests
 */

class TestDataFactory
{
    /**
     * Create a test flight array
     */
    public static function createFlight(array $overrides = []): array
    {
        return array_merge([
            'id' => 1,
            'flight_number' => 'TEST001',
            'airline_id' => 1,
            'aircraft_id' => 1,
            'origin' => 'JFK',
            'destination' => 'LAX',
            'scheduled_departure' => '2025-01-15 08:00:00',
            'scheduled_arrival' => '2025-01-15 11:00:00',
            'actual_departure' => null,
            'actual_arrival' => null,
            'status' => 'scheduled',
            'gate' => 'A1',
            'terminal' => 'T1',
            'created_at' => '2025-01-01 00:00:00',
            'updated_at' => '2025-01-01 00:00:00'
        ], $overrides);
    }

    /**
     * Create a test booking array
     */
    public static function createBooking(array $overrides = []): array
    {
        return array_merge([
            'id' => 1,
            'passenger_id' => 1,
            'flight_id' => 1,
            'seat_number' => '12A',
            'booking_reference' => 'ABC123',
            'status' => 'confirmed',
            'total_amount' => 299.99,
            'currency' => 'USD',
            'payment_status' => 'paid',
            'created_at' => '2025-01-01 00:00:00',
            'updated_at' => '2025-01-01 00:00:00'
        ], $overrides);
    }

    /**
     * Create a test passenger array
     */
    public static function createPassenger(array $overrides = []): array
    {
        return array_merge([
            'id' => 1,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@test.com',
            'phone' => '+1234567890',
            'passport_number' => 'P123456789',
            'nationality' => 'USA',
            'date_of_birth' => '1990-01-01',
            'created_at' => '2025-01-01 00:00:00'
        ], $overrides);
    }

    /**
     * Create a test user array
     */
    public static function createUser(array $overrides = []): array
    {
        return array_merge([
            'id' => 1,
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'role_id' => 3, // operator
            'first_name' => 'Test',
            'last_name' => 'User',
            'is_active' => true,
            'created_at' => '2025-01-01 00:00:00',
            'updated_at' => '2025-01-01 00:00:00'
        ], $overrides);
    }

    /**
     * Create a test airline array
     */
    public static function createAirline(array $overrides = []): array
    {
        return array_merge([
            'id' => 1,
            'name' => 'Test Airlines',
            'code' => 'TA',
            'country' => 'Test Country'
        ], $overrides);
    }

    /**
     * Create a test aircraft array
     */
    public static function createAircraft(array $overrides = []): array
    {
        return array_merge([
            'id' => 1,
            'model' => 'Boeing 737',
            'registration' => 'TEST001',
            'capacity' => 150
        ], $overrides);
    }

    /**
     * Create test baggage array
     */
    public static function createBaggage(array $overrides = []): array
    {
        return array_merge([
            'id' => 1,
            'booking_id' => 1,
            'tag_number' => 'B123456',
            'weight' => 23.5,
            'status' => 'checked',
            'location' => 'Cargo Hold A',
            'created_at' => '2025-01-01 00:00:00',
            'updated_at' => '2025-01-01 00:00:00'
        ], $overrides);
    }

    /**
     * Create test check-in array
     */
    public static function createCheckIn(array $overrides = []): array
    {
        return array_merge([
            'id' => 1,
            'booking_id' => 1,
            'check_in_time' => '2025-01-15 06:30:00',
            'boarding_pass_issued' => true,
            'security_cleared' => true
        ], $overrides);
    }

    /**
     * Create test clearance array
     */
    public static function createClearance(array $overrides = []): array
    {
        return array_merge([
            'id' => 1,
            'flight_id' => 1,
            'clearance_type' => 'takeoff',
            'issued_by' => 1,
            'issued_at' => '2025-01-15 07:45:00',
            'notes' => 'Cleared for takeoff runway 27L'
        ], $overrides);
    }

    /**
     * Create test weather data array
     */
    public static function createWeatherData(array $overrides = []): array
    {
        return array_merge([
            'id' => 1,
            'location' => 'JFK',
            'temperature' => 15.5,
            'wind_speed' => 12.0,
            'visibility' => 10.0,
            'conditions' => 'Partly cloudy',
            'recorded_at' => '2025-01-15 08:00:00'
        ], $overrides);
    }

    /**
     * Create test runway array
     */
    public static function createRunway(array $overrides = []): array
    {
        return array_merge([
            'id' => 1,
            'runway_number' => '27L',
            'length' => 12000,
            'status' => 'available'
        ], $overrides);
    }

    /**
     * Create test communication array
     */
    public static function createCommunication(array $overrides = []): array
    {
        return array_merge([
            'id' => 1,
            'flight_id' => 1,
            'user_id' => 1,
            'message' => 'Flight TEST001 cleared for takeoff',
            'timestamp' => '2025-01-15 07:50:00'
        ], $overrides);
    }

    /**
     * Create test aircraft position array
     */
    public static function createAircraftPosition(array $overrides = []): array
    {
        return array_merge([
            'id' => 1,
            'icao24' => 'ABC123',
            'callsign' => 'TEST001',
            'origin_country' => 'USA',
            'time_position' => 1640995200,
            'last_contact' => 1640995200,
            'longitude' => -73.7781,
            'latitude' => 40.6413,
            'baro_altitude' => 35000,
            'on_ground' => false,
            'velocity' => 500,
            'true_track' => 270,
            'vertical_rate' => 0,
            'geo_altitude' => 36000,
            'squawk' => '1234',
            'spi' => false,
            'position_source' => 0,
            'recorded_at' => '2025-01-15 08:00:00'
        ], $overrides);
    }

    /**
     * Create test satellite message array
     */
    public static function createSatelliteMessage(array $overrides = []): array
    {
        return array_merge([
            'id' => 1,
            'aircraft_id' => 'TEST001',
            'satellite_type' => 'starlink',
            'message_type' => 'position_report',
            'message_data' => json_encode(['lat' => 40.6413, 'lon' => -73.7781]),
            'received_at' => '2025-01-15 08:00:00',
            'signal_strength' => 85,
            'frequency' => 14.5,
            'processed' => false
        ], $overrides);
    }

    /**
     * Create test flight plan array
     */
    public static function createFlightPlan(array $overrides = []): array
    {
        return array_merge([
            'id' => 1,
            'flight_id' => 1,
            'aircraft_id' => 'TEST001',
            'departure_airport' => 'JFK',
            'arrival_airport' => 'LAX',
            'departure_time' => '2025-01-15 08:00:00',
            'arrival_time' => '2025-01-15 11:00:00',
            'route' => 'JFK..ROC..BUF..CLE..ORD..MSP..FAR..JFK',
            'altitude_profile' => '[35000, 35000, 35000]',
            'speed_profile' => '[500, 500, 500]',
            'fuel_requirements' => 25000.0,
            'alternate_airports' => 'BOS,PHL',
            'pilot_in_command' => 'John Smith',
            'filed_at' => '2025-01-14 20:00:00',
            'status' => 'filed',
            'updated_at' => '2025-01-14 20:00:00'
        ], $overrides);
    }

    /**
     * Create test conflict prediction array
     */
    public static function createConflictPrediction(array $overrides = []): array
    {
        return array_merge([
            'id' => 1,
            'conflict_id' => 'CONF001',
            'aircraft1_icao' => 'ABC123',
            'aircraft2_icao' => 'DEF456',
            'prediction_time' => '2025-01-15 08:15:00',
            'conflict_time' => '2025-01-15 08:30:00',
            'time_to_conflict' => 900,
            'horizontal_separation' => 2.5,
            'vertical_separation' => 1000,
            'conflict_type' => 'horizontal',
            'severity_level' => 'medium',
            'confidence_score' => 0.85,
            'prediction_model' => 'trajectory_analysis',
            'location_lat' => 40.6413,
            'location_lon' => -73.7781,
            'altitude' => 35000,
            'status' => 'predicted',
            'resolution_method' => null,
            'resolved_at' => null,
            'created_at' => '2025-01-15 08:00:00'
        ], $overrides);
    }

    /**
     * Create test decision recommendation array
     */
    public static function createDecisionRecommendation(array $overrides = []): array
    {
        return array_merge([
            'id' => 1,
            'recommendation_id' => 'REC001',
            'scenario_id' => 'SCN001',
            'recommendation_type' => 'altitude_change',
            'confidence_score' => 0.92,
            'expected_outcome' => json_encode(['separation_improved' => true]),
            'alternative_options' => json_encode(['speed_change', 'route_change']),
            'implementation_steps' => json_encode(['notify_pilot', 'confirm_acknowledgment']),
            'risk_assessment' => json_encode(['collision_risk' => 'low']),
            'time_to_implement' => 60,
            'priority_score' => 0.9,
            'status' => 'pending',
            'created_at' => '2025-01-15 08:00:00'
        ], $overrides);
    }

    /**
     * Create test audit log array
     */
    public static function createAuditLog(array $overrides = []): array
    {
        return array_merge([
            'id' => 1,
            'user_id' => 1,
            'action' => 'flight_created',
            'resource_type' => 'flight',
            'resource_id' => '1',
            'details' => json_encode(['flight_number' => 'TEST001']),
            'ip_address' => '192.168.1.100',
            'user_agent' => 'Mozilla/5.0 (Test Browser)',
            'session_id' => 'sess_123456',
            'created_at' => '2025-01-15 08:00:00'
        ], $overrides);
    }

    /**
     * Create test data subject consent array
     */
    public static function createDataSubjectConsent(array $overrides = []): array
    {
        return array_merge([
            'id' => 1,
            'consent_id' => 'CONS001',
            'data_subject_id' => '1',
            'data_subject_type' => 'passenger',
            'consent_type' => 'marketing',
            'consent_given' => true,
            'consent_date' => '2025-01-15 08:00:00',
            'consent_expiry' => '2026-01-15 08:00:00',
            'consent_withdrawn' => false,
            'withdrawal_date' => null,
            'consent_version' => '1.0',
            'legal_basis' => 'consent',
            'consent_scope' => 'Email marketing for flight offers',
            'ip_address' => '192.168.1.100',
            'user_agent' => 'Mozilla/5.0 (Test Browser)',
            'created_at' => '2025-01-15 08:00:00'
        ], $overrides);
    }

    /**
     * Create multiple test flights
     */
    public static function createMultipleFlights(int $count = 3): array
    {
        $flights = [];
        for ($i = 1; $i <= $count; $i++) {
            $flights[] = self::createFlight([
                'id' => $i,
                'flight_number' => 'TEST' . str_pad($i, 3, '0', STR_PAD_LEFT)
            ]);
        }
        return $flights;
    }

    /**
     * Create multiple test bookings for a flight
     */
    public static function createMultipleBookings(int $flightId, int $count = 5): array
    {
        $bookings = [];
        for ($i = 1; $i <= $count; $i++) {
            $bookings[] = self::createBooking([
                'id' => $i,
                'flight_id' => $flightId,
                'passenger_id' => $i,
                'booking_reference' => 'REF' . str_pad($i, 3, '0', STR_PAD_LEFT)
            ]);
        }
        return $bookings;
    }

    /**
     * Create test validation data for edge cases
     */
    public static function createInvalidFlight(): array
    {
        return [
            'flight_number' => '', // Invalid: empty
            'origin' => 'JFK',
            'destination' => 'JFK', // Invalid: same as origin
            'scheduled_departure' => 'invalid_date', // Invalid: not a date
            'scheduled_arrival' => '2025-01-15 08:00:00',
            'status' => 'invalid_status' // Invalid: not in allowed statuses
        ];
    }

    /**
     * Create test data for performance testing
     */
    public static function createPerformanceTestData(int $count = 1000): array
    {
        $data = [];
        for ($i = 1; $i <= $count; $i++) {
            $data[] = self::createFlight([
                'id' => $i,
                'flight_number' => 'PERF' . str_pad($i, 4, '0', STR_PAD_LEFT)
            ]);
        }
        return $data;
    }
}
