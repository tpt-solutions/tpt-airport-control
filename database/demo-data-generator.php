<?php
/**
 * Demo Data Generator for Flight Control System
 *
 * Generates comprehensive demo data for all modules and features
 * Creates realistic airport operations data for demonstration purposes
 */

require_once __DIR__ . '/../backend/src/Config.php';
require_once __DIR__ . '/../backend/src/Logger.php';

class DemoDataGenerator
{
    private $pdo;
    private $logger;
    private $airlines = [
        ['code' => 'AA', 'name' => 'American Airlines', 'country' => 'USA'],
        ['code' => 'DL', 'name' => 'Delta Air Lines', 'country' => 'USA'],
        ['code' => 'UA', 'name' => 'United Airlines', 'country' => 'USA'],
        ['code' => 'WN', 'name' => 'Southwest Airlines', 'country' => 'USA'],
        ['code' => 'BA', 'name' => 'British Airways', 'country' => 'UK'],
        ['code' => 'LH', 'name' => 'Lufthansa', 'country' => 'Germany'],
        ['code' => 'AF', 'name' => 'Air France', 'country' => 'France'],
        ['code' => 'KL', 'name' => 'KLM Royal Dutch Airlines', 'country' => 'Netherlands'],
        ['code' => 'EK', 'name' => 'Emirates', 'country' => 'UAE'],
        ['code' => 'SQ', 'name' => 'Singapore Airlines', 'country' => 'Singapore']
    ];

    private $airports = [
        ['code' => 'JFK', 'name' => 'John F. Kennedy International Airport', 'city' => 'New York', 'country' => 'USA'],
        ['code' => 'LAX', 'name' => 'Los Angeles International Airport', 'city' => 'Los Angeles', 'country' => 'USA'],
        ['code' => 'ORD', 'name' => 'O\'Hare International Airport', 'city' => 'Chicago', 'country' => 'USA'],
        ['code' => 'MIA', 'name' => 'Miami International Airport', 'city' => 'Miami', 'country' => 'USA'],
        ['code' => 'SFO', 'name' => 'San Francisco International Airport', 'city' => 'San Francisco', 'country' => 'USA'],
        ['code' => 'LHR', 'name' => 'London Heathrow Airport', 'city' => 'London', 'country' => 'UK'],
        ['code' => 'CDG', 'name' => 'Charles de Gaulle Airport', 'city' => 'Paris', 'country' => 'France'],
        ['code' => 'FRA', 'name' => 'Frankfurt Airport', 'city' => 'Frankfurt', 'country' => 'Germany'],
        ['code' => 'AMS', 'name' => 'Amsterdam Schiphol Airport', 'city' => 'Amsterdam', 'country' => 'Netherlands'],
        ['code' => 'DXB', 'name' => 'Dubai International Airport', 'city' => 'Dubai', 'country' => 'UAE'],
        ['code' => 'SIN', 'name' => 'Singapore Changi Airport', 'city' => 'Singapore', 'country' => 'Singapore'],
        ['code' => 'HKG', 'name' => 'Hong Kong International Airport', 'city' => 'Hong Kong', 'country' => 'China']
    ];

    private $aircraftTypes = [
        ['model' => 'Boeing 737-800', 'capacity' => 160, 'range' => 5400],
        ['model' => 'Boeing 777-300ER', 'capacity' => 396, 'range' => 13650],
        ['model' => 'Airbus A320', 'capacity' => 150, 'range' => 6150],
        ['model' => 'Airbus A350-900', 'capacity' => 325, 'range' => 15000],
        ['model' => 'Boeing 787-9', 'capacity' => 290, 'range' => 14000],
        ['model' => 'Embraer E190', 'capacity' => 106, 'range' => 3334]
    ];

    public function __construct()
    {
        $this->logger = new Logger('demo_generator');
        $this->connectDatabase();
    }

    private function connectDatabase()
    {
        try {
            $config = new Config();
            $dbConfig = $config->getDatabaseConfig();

            $this->pdo = new PDO(
                "pgsql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']}",
                $dbConfig['username'],
                $dbConfig['password']
            );
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (Exception $e) {
            $this->logger->error('Database connection failed', ['error' => $e->getMessage()]);
            die("Database connection failed: " . $e->getMessage() . "\n");
        }
    }

    public function generateAllDemoData()
    {
        $this->logger->info('Starting demo data generation');

        try {
            $this->pdo->beginTransaction();

            // Core data
            $this->generateRoles();
            $this->generateUsers();
            $this->generateAirlines();
            $this->generateAirports();
            $this->generateAircraft();
            $this->generateFlights();
            $this->generatePassengers();
            $this->generateBookings();

            // Module-specific data
            $this->generateCargoData();
            $this->generateSustainabilityData();
            $this->generateCommercialData();
            $this->generateEmergencyData();
            $this->generateSpecialServicesData();
            $this->generateAnalyticsData();
            $this->generateInfrastructureData();
            $this->generateDroneData();
            $this->generateCustomsData();
            $this->generateSecurityData();
            $this->generateAIData();
            $this->generateVirtualAssistantData();

            // PWA features
            $this->generateSelfCheckinData();
            $this->generateBaggageData();
            $this->generateAlertsData();

            $this->pdo->commit();
            $this->logger->info('Demo data generation completed successfully');

            echo "✅ Demo data generation completed successfully!\n";
            echo "📊 Generated data includes:\n";
            echo "   • 50+ demo users with different roles\n";
            echo "   • 100+ flights across multiple airlines\n";
            echo "   • 500+ passenger records\n";
            echo "   • Complete data for all 12 modules\n";
            echo "   • Realistic airport operations scenarios\n";

        } catch (Exception $e) {
            $this->pdo->rollBack();
            $this->logger->error('Demo data generation failed', ['error' => $e->getMessage()]);
            echo "❌ Demo data generation failed: " . $e->getMessage() . "\n";
        }
    }

    private function generateRoles()
    {
        $roles = [
            ['name' => 'super_admin', 'description' => 'Full system access', 'permissions' => 'all'],
            ['name' => 'admin', 'description' => 'Airport administration', 'permissions' => 'admin'],
            ['name' => 'operator', 'description' => 'Daily operations staff', 'permissions' => 'operator'],
            ['name' => 'controller', 'description' => 'Air traffic controller', 'permissions' => 'controller'],
            ['name' => 'passenger', 'description' => 'Self-service access', 'permissions' => 'passenger']
        ];

        foreach ($roles as $role) {
            $this->insert('roles', $role);
        }
        echo "✓ Generated roles\n";
    }

    private function generateUsers()
    {
        $users = [
            [
                'username' => 'admin',
                'email' => 'admin@flightcontrol.demo',
                'password_hash' => password_hash('admin123', PASSWORD_DEFAULT),
                'first_name' => 'System',
                'last_name' => 'Administrator',
                'role_id' => 1,
                'is_active' => true,
                'created_at' => date('Y-m-d H:i:s')
            ],
            [
                'username' => 'controller1',
                'email' => 'controller@flightcontrol.demo',
                'password_hash' => password_hash('controller123', PASSWORD_DEFAULT),
                'first_name' => 'John',
                'last_name' => 'Controller',
                'role_id' => 4,
                'is_active' => true,
                'created_at' => date('Y-m-d H:i:s')
            ],
            [
                'username' => 'operator1',
                'email' => 'operator@flightcontrol.demo',
                'password_hash' => password_hash('operator123', PASSWORD_DEFAULT),
                'first_name' => 'Sarah',
                'last_name' => 'Operator',
                'role_id' => 3,
                'is_active' => true,
                'created_at' => date('Y-m-d H:i:s')
            ]
        ];

        // Generate 50 random passengers
        for ($i = 1; $i <= 50; $i++) {
            $users[] = [
                'username' => 'passenger' . $i,
                'email' => 'passenger' . $i . '@demo.com',
                'password_hash' => password_hash('pass123', PASSWORD_DEFAULT),
                'first_name' => $this->getRandomFirstName(),
                'last_name' => $this->getRandomLastName(),
                'role_id' => 5,
                'is_active' => true,
                'created_at' => date('Y-m-d H:i:s', strtotime('-' . rand(1, 365) . ' days'))
            ];
        }

        foreach ($users as $user) {
            $this->insert('users', $user);
        }
        echo "✓ Generated " . count($users) . " users\n";
    }

    private function generateAirlines()
    {
        foreach ($this->airlines as $airline) {
            $this->insert('airlines', $airline);
        }
        echo "✓ Generated " . count($this->airlines) . " airlines\n";
    }

    private function generateAirports()
    {
        foreach ($this->airports as $airport) {
            $this->insert('airports', $airport);
        }
        echo "✓ Generated " . count($this->airports) . " airports\n";
    }

    private function generateAircraft()
    {
        for ($i = 1; $i <= 20; $i++) {
            $type = $this->aircraftTypes[array_rand($this->aircraftTypes)];
            $aircraft = [
                'registration' => 'N' . strtoupper(substr(md5($i), 0, 6)),
                'model' => $type['model'],
                'capacity' => $type['capacity'],
                'airline_id' => rand(1, count($this->airlines)),
                'status' => ['active', 'maintenance', 'standby'][rand(0, 2)],
                'manufacture_date' => date('Y-m-d', strtotime('-' . rand(1, 15) . ' years')),
                'last_maintenance' => date('Y-m-d', strtotime('-' . rand(1, 365) . ' days'))
            ];
            $this->insert('aircraft', $aircraft);
        }
        echo "✓ Generated 20 aircraft\n";
    }

    private function generateFlights()
    {
        $flights = [];
        $today = strtotime('today');

        for ($i = 0; $i < 100; $i++) {
            $departure = $this->airports[array_rand($this->airports)];
            $arrival = $this->airports[array_rand($this->airports)];

            // Ensure different airports
            while ($arrival['code'] === $departure['code']) {
                $arrival = $this->airports[array_rand($this->airports)];
            }

            $departureTime = $today + (rand(1, 30) * 86400) + (rand(6, 22) * 3600);
            $flightDuration = rand(2, 12) * 3600; // 2-12 hours
            $arrivalTime = $departureTime + $flightDuration;

            $flight = [
                'flight_number' => $this->airlines[array_rand($this->airlines)]['code'] . rand(100, 999),
                'airline_id' => rand(1, count($this->airlines)),
                'aircraft_id' => rand(1, 20),
                'origin' => $departure['code'],
                'destination' => $arrival['code'],
                'scheduled_departure' => date('Y-m-d H:i:s', $departureTime),
                'scheduled_arrival' => date('Y-m-d H:i:s', $arrivalTime),
                'status' => ['scheduled', 'boarding', 'departed', 'arrived', 'delayed', 'cancelled'][rand(0, 5)],
                'gate' => 'G' . rand(1, 50),
                'terminal' => 'T' . rand(1, 4),
                'created_at' => date('Y-m-d H:i:s')
            ];

            $this->insert('flights', $flight);
            $flights[] = $flight;
        }
        echo "✓ Generated 100 flights\n";
        return $flights;
    }

    private function generatePassengers()
    {
        $passengers = [];

        for ($i = 1; $i <= 500; $i++) {
            $userId = rand(4, 53); // Passenger user IDs
            $passenger = [
                'user_id' => $userId,
                'first_name' => $this->getRandomFirstName(),
                'last_name' => $this->getRandomLastName(),
                'email' => 'passenger' . $i . '@demo.com',
                'phone' => '+1' . rand(100, 999) . rand(100, 999) . rand(1000, 9999),
                'passport_number' => strtoupper(substr(md5($i), 0, 9)),
                'passport_expiry' => date('Y-m-d', strtotime('+' . rand(1, 10) . ' years')),
                'date_of_birth' => date('Y-m-d', strtotime('-' . rand(18, 80) . ' years')),
                'nationality' => ['USA', 'UK', 'Germany', 'France', 'Canada', 'Australia'][rand(0, 5)],
                'frequent_flyer_number' => strtoupper(substr(md5($i . 'ff'), 0, 12)),
                'created_at' => date('Y-m-d H:i:s')
            ];

            $this->insert('passengers', $passenger);
            $passengers[] = $passenger;
        }
        echo "✓ Generated 500 passengers\n";
        return $passengers;
    }

    private function generateBookings()
    {
        for ($i = 1; $i <= 300; $i++) {
            $passengerId = rand(1, 500);
            $flightId = rand(1, 100);

            $booking = [
                'passenger_id' => $passengerId,
                'flight_id' => $flightId,
                'seat_number' => rand(1, 50) . chr(65 + rand(0, 5)), // e.g., "12A"
                'class' => ['economy', 'premium_economy', 'business', 'first'][rand(0, 3)],
                'status' => ['confirmed', 'pending', 'cancelled'][rand(0, 2)],
                'booking_reference' => strtoupper(substr(md5($i), 0, 6)),
                'total_price' => rand(200, 2000) + (rand(0, 99) / 100),
                'currency' => 'USD',
                'special_requests' => rand(0, 4) === 0 ? $this->getRandomSpecialRequest() : null,
                'created_at' => date('Y-m-d H:i:s')
            ];

            $this->insert('bookings', $booking);
        }
        echo "✓ Generated 300 bookings\n";
    }

    private function generateCargoData()
    {
        for ($i = 1; $i <= 50; $i++) {
            $cargo = [
                'flight_id' => rand(1, 100),
                'cargo_type' => ['general', 'perishable', 'hazardous', 'valuable'][rand(0, 3)],
                'description' => 'Demo cargo item ' . $i,
                'weight' => rand(10, 500) + (rand(0, 99) / 100),
                'volume' => rand(1, 100) + (rand(0, 99) / 100),
                'origin' => $this->airports[array_rand($this->airports)]['code'],
                'destination' => $this->airports[array_rand($this->airports)]['code'],
                'temperature_required' => rand(0, 2) === 0 ? rand(2, 8) : null,
                'tracking_number' => 'CARGO' . str_pad($i, 6, '0', STR_PAD_LEFT),
                'status' => ['received', 'loaded', 'in_transit', 'delivered'][rand(0, 3)],
                'created_at' => date('Y-m-d H:i:s')
            ];
            $this->insert('cargo', $cargo);
        }
        echo "✓ Generated cargo data\n";
    }

    private function generateSustainabilityData()
    {
        for ($i = 1; $i <= 100; $i++) {
            $sustainability = [
                'flight_id' => rand(1, 100),
                'carbon_emissions' => rand(500, 5000) + (rand(0, 99) / 100),
                'fuel_consumption' => rand(2000, 20000) + (rand(0, 99) / 100),
                'noise_level' => rand(70, 120) + (rand(0, 99) / 100),
                'green_energy_used' => rand(0, 100),
                'recycling_rate' => rand(60, 95),
                'water_consumption' => rand(1000, 10000),
                'waste_generated' => rand(50, 500),
                'measurement_date' => date('Y-m-d', strtotime('-' . rand(1, 30) . ' days')),
                'created_at' => date('Y-m-d H:i:s')
            ];
            $this->insert('sustainability_metrics', $sustainability);
        }
        echo "✓ Generated sustainability data\n";
    }

    private function generateCommercialData()
    {
        for ($i = 1; $i <= 20; $i++) {
            $retail = [
                'name' => 'Store ' . $i,
                'type' => ['duty_free', 'restaurant', 'shop', 'lounge'][rand(0, 3)],
                'location' => 'Terminal ' . rand(1, 4) . ', Gate Area',
                'daily_revenue' => rand(1000, 10000) + (rand(0, 99) / 100),
                'monthly_target' => rand(30000, 300000),
                'status' => ['active', 'maintenance', 'closed'][rand(0, 2)],
                'created_at' => date('Y-m-d H:i:s')
            ];
            $this->insert('retail_outlets', $retail);
        }
        echo "✓ Generated commercial data\n";
    }

    private function generateEmergencyData()
    {
        $protocols = [
            ['name' => 'Fire Emergency', 'type' => 'fire', 'severity' => 'critical'],
            ['name' => 'Medical Emergency', 'type' => 'medical', 'severity' => 'high'],
            ['name' => 'Security Threat', 'type' => 'security', 'severity' => 'critical'],
            ['name' => 'Weather Emergency', 'type' => 'weather', 'severity' => 'medium']
        ];

        foreach ($protocols as $protocol) {
            $this->insert('emergency_protocols', $protocol);
        }
        echo "✓ Generated emergency protocols\n";
    }

    private function generateSpecialServicesData()
    {
        for ($i = 1; $i <= 30; $i++) {
            $service = [
                'passenger_id' => rand(1, 500),
                'service_type' => ['wheelchair', 'medical_assistance', 'unaccompanied_minor', 'special_diet'][rand(0, 3)],
                'description' => 'Demo special service request ' . $i,
                'status' => ['requested', 'confirmed', 'completed'][rand(0, 2)],
                'assigned_staff' => 'Staff Member ' . rand(1, 20),
                'created_at' => date('Y-m-d H:i:s')
            ];
            $this->insert('special_services', $service);
        }
        echo "✓ Generated special services data\n";
    }

    private function generateAnalyticsData()
    {
        for ($i = 1; $i <= 200; $i++) {
            $analytics = [
                'metric_type' => ['passenger_satisfaction', 'flight_delay', 'baggage_delivery', 'security_wait'][rand(0, 3)],
                'value' => rand(1, 100),
                'date_recorded' => date('Y-m-d', strtotime('-' . rand(1, 90) . ' days')),
                'prediction' => rand(0, 1),
                'confidence_score' => rand(70, 99) + (rand(0, 99) / 100),
                'created_at' => date('Y-m-d H:i:s')
            ];
            $this->insert('analytics_data', $analytics);
        }
        echo "✓ Generated analytics data\n";
    }

    private function generateInfrastructureData()
    {
        for ($i = 1; $i <= 50; $i++) {
            $sensor = [
                'sensor_type' => ['temperature', 'humidity', 'power', 'lighting', 'security'][rand(0, 4)],
                'location' => 'Terminal ' . rand(1, 4) . ', Zone ' . rand(1, 10),
                'value' => rand(10, 100) + (rand(0, 99) / 100),
                'unit' => ['°C', '%', 'W', 'lux', 'status'][rand(0, 4)],
                'status' => ['normal', 'warning', 'critical'][rand(0, 2)],
                'last_reading' => date('Y-m-d H:i:s', strtotime('-' . rand(1, 60) . ' minutes')),
                'created_at' => date('Y-m-d H:i:s')
            ];
            $this->insert('infrastructure_sensors', $sensor);
        }
        echo "✓ Generated infrastructure data\n";
    }

    private function generateDroneData()
    {
        for ($i = 1; $i <= 25; $i++) {
            $drone = [
                'registration' => 'DRONE' . str_pad($i, 4, '0', STR_PAD_LEFT),
                'operator' => 'Demo Operator ' . rand(1, 5),
                'purpose' => ['inspection', 'delivery', 'surveillance', 'mapping'][rand(0, 3)],
                'max_altitude' => rand(50, 400),
                'flight_duration' => rand(15, 60),
                'status' => ['grounded', 'active', 'maintenance'][rand(0, 2)],
                'last_flight' => date('Y-m-d H:i:s', strtotime('-' . rand(1, 30) . ' days')),
                'created_at' => date('Y-m-d H:i:s')
            ];
            $this->insert('drones', $drone);
        }
        echo "✓ Generated drone data\n";
    }

    private function generateCustomsData()
    {
        for ($i = 1; $i <= 100; $i++) {
            $declaration = [
                'passenger_id' => rand(1, 500),
                'flight_id' => rand(1, 100),
                'declaration_type' => ['goods', 'currency', 'food', 'medicine'][rand(0, 3)],
                'description' => 'Demo customs declaration ' . $i,
                'value' => rand(50, 5000) + (rand(0, 99) / 100),
                'currency' => 'USD',
                'status' => ['pending', 'approved', 'rejected', 'inspected'][rand(0, 3)],
                'inspector' => rand(0, 4) === 0 ? 'Inspector ' . rand(1, 10) : null,
                'created_at' => date('Y-m-d H:i:s')
            ];
            $this->insert('customs_declarations', $declaration);
        }
        echo "✓ Generated customs data\n";
    }

    private function generateSecurityData()
    {
        for ($i = 1; $i <= 75; $i++) {
            $incident = [
                'type' => ['suspicious_behavior', 'unauthorized_access', 'lost_item', 'medical_emergency'][rand(0, 3)],
                'severity' => ['low', 'medium', 'high', 'critical'][rand(0, 3)],
                'location' => 'Terminal ' . rand(1, 4) . ', Gate ' . rand(1, 50),
                'description' => 'Demo security incident ' . $i,
                'status' => ['reported', 'investigating', 'resolved', 'closed'][rand(0, 3)],
                'reported_by' => 'Security Officer ' . rand(1, 20),
                'assigned_to' => 'Investigator ' . rand(1, 10),
                'created_at' => date('Y-m-d H:i:s')
            ];
            $this->insert('security_incidents', $incident);
        }
        echo "✓ Generated security data\n";
    }

    private function generateAIData()
    {
        for ($i = 1; $i <= 150; $i++) {
            $ai = [
                'agent_type' => ['chatbot', 'predictive', 'automation', 'analysis'][rand(0, 3)],
                'query' => 'Demo AI query ' . $i,
                'response' => 'Demo AI response for query ' . $i,
                'confidence' => rand(70, 99) + (rand(0, 99) / 100),
                'processing_time' => rand(100, 2000),
                'user_id' => rand(1, 53),
                'created_at' => date('Y-m-d H:i:s')
            ];
            $this->insert('ai_interactions', $ai);
        }
        echo "✓ Generated AI data\n";
    }

    private function generateVirtualAssistantData()
    {
        for ($i = 1; $i <= 200; $i++) {
            $interaction = [
                'user_id' => rand(1, 53),
                'query_type' => ['flight_info', 'booking_help', 'terminal_guide', 'service_info'][rand(0, 3)],
                'query' => 'Demo virtual assistant query ' . $i,
                'response' => 'Demo virtual assistant response ' . $i,
                'satisfaction_rating' => rand(3, 5),
                'response_time' => rand(500, 3000),
                'created_at' => date('Y-m-d H:i:s')
            ];
            $this->insert('virtual_assistant_interactions', $interaction);
        }
        echo "✓ Generated virtual assistant data\n";
    }

    private function generateSelfCheckinData()
    {
        for ($i = 1; $i <= 40; $i++) {
            $kiosk = [
                'kiosk_id' => 'KIOSK' . str_pad($i, 3, '0', STR_PAD_LEFT),
                'location' => 'Terminal ' . rand(1, 4) . ', Check-in Area',
                'status' => ['active', 'maintenance', 'offline'][rand(0, 2)],
                'last_used' => date('Y-m-d H:i:s', strtotime('-' . rand(1, 1440) . ' minutes')),
                'usage_count' => rand(50, 500),
                'created_at' => date('Y-m-d H:i:s')
            ];
            $this->insert('self_checkin_kiosks', $kiosk);
        }
        echo "✓ Generated self-checkin data\n";
    }

    private function generateBaggageData()
    {
        for ($i = 1; $i <= 300; $i++) {
            $baggage = [
                'booking_id' => rand(1, 300),
                'tag_number' => 'TAG' . str_pad($i, 6, '0', STR_PAD_LEFT),
                'weight' => rand(10, 30) + (rand(0, 9) / 10),
                'status' => ['checked_in', 'loaded', 'unloaded', 'delivered', 'lost'][rand(0, 4)],
                'location' => 'Baggage Claim ' . rand(1, 10),
                'last_scan' => date('Y-m-d H:i:s', strtotime('-' . rand(1, 720) . ' minutes')),
                'created_at' => date('Y-m-d H:i:s')
            ];
            $this->insert('baggage_tracking', $baggage);
        }
        echo "✓ Generated baggage tracking data\n";
    }

    private function generateAlertsData()
    {
        $templates = [
            ['name' => 'Flight Delay', 'type' => 'delay', 'message' => 'Your flight has been delayed'],
            ['name' => 'Gate Change', 'type' => 'gate_change', 'message' => 'Your gate has changed'],
            ['name' => 'Boarding Call', 'type' => 'boarding', 'message' => 'Boarding is now open'],
            ['name' => 'Security Reminder', 'type' => 'security', 'message' => 'Please proceed to security']
        ];

        foreach ($templates as $template) {
            $this->insert('alert_templates', $template);
        }

        for ($i = 1; $i <= 100; $i++) {
            $alert = [
                'passenger_id' => rand(1, 500),
                'template_id' => rand(1, 4),
                'message' => 'Demo alert message ' . $i,
                'status' => ['sent', 'delivered', 'read'][rand(0, 2)],
                'channel' => ['push', 'sms', 'email'][rand(0, 2)],
                'sent_at' => date('Y-m-d H:i:s', strtotime('-' . rand(1, 1440) . ' minutes')),
                'created_at' => date('Y-m-d H:i:s')
            ];
            $this->insert('passenger_alerts', $alert);
        }
        echo "✓ Generated alerts data\n";
    }

    private function insert($table, $data)
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($data));
    }

    private function getRandomFirstName()
    {
        $names = ['John', 'Jane', 'Michael', 'Sarah', 'David', 'Emma', 'James', 'Lisa', 'Robert', 'Maria'];
        return $names[array_rand($names)];
    }

    private function getRandomLastName()
    {
        $names = ['Smith', 'Johnson', 'Brown', 'Williams', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez'];
        return $names[array_rand($names)];
    }

    private function getRandomSpecialRequest()
    {
        $requests = [
            'Vegetarian meal',
            'Wheelchair assistance',
            'Extra legroom',
            'Bassinet for infant',
            'Allergy alert',
            'Medical oxygen'
        ];
        return $requests[array_rand($requests)];
    }
}

// Run the generator
if ($argc > 1 && $argv[1] === 'run') {
    $generator = new DemoDataGenerator();
    $generator->generateAllDemoData();
} else {
    echo "Demo Data Generator for Flight Control System\n";
    echo "Usage: php demo-data-generator.php run\n";
    echo "\n";
    echo "This will generate comprehensive demo data including:\n";
    echo "• Users and roles\n";
    echo "• Airlines and airports\n";
    echo "• Flights and aircraft\n";
    echo "• Passengers and bookings\n";
    echo "• All module-specific data\n";
    echo "• PWA feature data\n";
    echo "\n";
    echo "⚠️  WARNING: This will populate your database with demo data!\n";
    echo "   Make sure you're running this on a development database.\n";
}
?>
