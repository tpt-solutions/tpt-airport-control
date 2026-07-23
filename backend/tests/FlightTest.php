<?php

use PHPUnit\Framework\TestCase;

class FlightApiTest extends TestCase
{
    private $pdo;

    protected function setUp(): void
    {
        $this->pdo = getTestDatabaseConnection();
        cleanupTestData();
        setupTestFixtures();
    }

    protected function tearDown(): void
    {
        cleanupTestData();
    }

    public function testGetFlightsList()
    {
        // Mock the request
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/flights';

        // Capture output
        ob_start();
        include __DIR__ . '/../api/flights.php';
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('flights', $response);
        $this->assertArrayHasKey('pagination', $response);
        $this->assertCount(2, $response['flights']); // We set up 2 test flights
    }

    public function testGetFlightById()
    {
        // Get first flight ID
        $stmt = $this->pdo->query("SELECT id FROM flights LIMIT 1");
        $flight = $stmt->fetch();
        $flightId = $flight['id'];

        // Mock the request
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = "/api/flights/{$flightId}";

        // Capture output
        ob_start();
        include __DIR__ . '/../api/flights.php';
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('flight', $response);
        $this->assertEquals($flightId, $response['flight']['id']);
    }

    public function testCreateFlight()
    {
        // Mock authentication as admin
        $adminUser = $this->pdo->query("SELECT id FROM users WHERE username = 'testadmin'")->fetch();
        $token = $this->generateTestToken($adminUser['id']);

        // Mock the request
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/flights';
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;

        // Mock POST data
        $postData = [
            'flight_number' => 'TEST123',
            'origin' => 'JFK',
            'destination' => 'LAX',
            'scheduled_departure' => '2025-01-20 10:00:00',
            'scheduled_arrival' => '2025-01-20 13:00:00',
            'aircraft_id' => 1,
            'airline_id' => 1
        ];

        // Simulate JSON input
        $jsonInput = json_encode($postData);
        file_put_contents('php://input', $jsonInput);

        // Capture output
        ob_start();
        include __DIR__ . '/../api/flights.php';
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('flight_id', $response);

        // Verify flight was created
        $stmt = $this->pdo->prepare("SELECT * FROM flights WHERE flight_number = ?");
        $stmt->execute(['TEST123']);
        $flight = $stmt->fetch();

        $this->assertNotNull($flight);
        $this->assertEquals('JFK', $flight['origin']);
        $this->assertEquals('LAX', $flight['destination']);
    }

    public function testUpdateFlight()
    {
        // Get first flight
        $stmt = $this->pdo->query("SELECT id FROM flights LIMIT 1");
        $flight = $stmt->fetch();
        $flightId = $flight['id'];

        // Mock authentication as admin
        $adminUser = $this->pdo->query("SELECT id FROM users WHERE username = 'testadmin'")->fetch();
        $token = $this->generateTestToken($adminUser['id']);

        // Mock the request
        $_SERVER['REQUEST_METHOD'] = 'PUT';
        $_SERVER['REQUEST_URI'] = "/api/flights/{$flightId}";
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;

        // Mock PUT data
        $updateData = [
            'status' => 'boarding',
            'gate' => 'A12'
        ];

        // Simulate JSON input
        $jsonInput = json_encode($updateData);
        file_put_contents('php://input', $jsonInput);

        // Capture output
        ob_start();
        include __DIR__ . '/../api/flights.php';
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertTrue($response['success']);
        $this->assertEquals('Flight updated successfully', $response['message']);

        // Verify flight was updated
        $stmt = $this->pdo->prepare("SELECT status, gate FROM flights WHERE id = ?");
        $stmt->execute([$flightId]);
        $updatedFlight = $stmt->fetch();

        $this->assertEquals('boarding', $updatedFlight['status']);
        $this->assertEquals('A12', $updatedFlight['gate']);
    }

    public function testDeleteFlight()
    {
        // Create a test flight
        $stmt = $this->pdo->prepare("
            INSERT INTO flights (flight_number, origin, destination, scheduled_departure, scheduled_arrival, aircraft_id, airline_id, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute(['DELETE_TEST', 'JFK', 'LAX', '2025-01-25 10:00:00', '2025-01-25 13:00:00', 1, 1, 'scheduled']);
        $flightId = $this->pdo->lastInsertId();

        // Mock authentication as admin
        $adminUser = $this->pdo->query("SELECT id FROM users WHERE username = 'testadmin'")->fetch();
        $token = $this->generateTestToken($adminUser['id']);

        // Mock the request
        $_SERVER['REQUEST_METHOD'] = 'DELETE';
        $_SERVER['REQUEST_URI'] = "/api/flights/{$flightId}";
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;

        // Capture output
        ob_start();
        include __DIR__ . '/../api/flights.php';
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertTrue($response['success']);
        $this->assertEquals('Flight deleted successfully', $response['message']);

        // Verify flight was deleted
        $stmt = $this->pdo->prepare("SELECT id FROM flights WHERE id = ?");
        $stmt->execute([$flightId]);
        $flight = $stmt->fetch();

        $this->assertFalse($flight);
    }

    public function testFlightSearch()
    {
        // Mock the request with search parameters
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/flights';
        $_GET['origin'] = 'JFK';

        // Capture output
        ob_start();
        include __DIR__ . '/../api/flights.php';
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('flights', $response);

        // All test flights have JFK as origin, so we should get results
        $this->assertGreaterThan(0, count($response['flights']));

        // Verify all returned flights have JFK as origin
        foreach ($response['flights'] as $flight) {
            $this->assertEquals('JFK', $flight['origin']);
        }
    }

    public function testFlightStatusFilter()
    {
        // Mock the request with status filter
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/flights';
        $_GET['status'] = 'scheduled';

        // Capture output
        ob_start();
        include __DIR__ . '/../api/flights.php';
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('flights', $response);

        // Verify all returned flights have scheduled status
        foreach ($response['flights'] as $flight) {
            $this->assertEquals('scheduled', $flight['status']);
        }
    }

    public function testUnauthorizedAccess()
    {
        // Mock the request without authentication
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/flights';

        // Mock POST data
        $postData = [
            'flight_number' => 'UNAUTH_TEST',
            'origin' => 'JFK',
            'destination' => 'LAX',
            'scheduled_departure' => '2025-01-20 10:00:00',
            'scheduled_arrival' => '2025-01-20 13:00:00',
            'aircraft_id' => 1,
            'airline_id' => 1
        ];

        // Simulate JSON input
        $jsonInput = json_encode($postData);
        file_put_contents('php://input', $jsonInput);

        // Capture output
        ob_start();
        include __DIR__ . '/../api/flights.php';
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertFalse($response['success']);
        $this->assertEquals('Authentication required', $response['message']);
    }

    public function testInsufficientPermissions()
    {
        // Mock authentication as passenger (insufficient permissions)
        $passengerUser = $this->pdo->query("SELECT id FROM users WHERE username = 'testpassenger'")->fetch();
        $token = $this->generateTestToken($passengerUser['id']);

        // Mock the request
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/flights';
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;

        // Mock POST data
        $postData = [
            'flight_number' => 'PERMISSION_TEST',
            'origin' => 'JFK',
            'destination' => 'LAX',
            'scheduled_departure' => '2025-01-20 10:00:00',
            'scheduled_arrival' => '2025-01-20 13:00:00',
            'aircraft_id' => 1,
            'airline_id' => 1
        ];

        // Simulate JSON input
        $jsonInput = json_encode($postData);
        file_put_contents('php://input', $jsonInput);

        // Capture output
        ob_start();
        include __DIR__ . '/../api/flights.php';
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertFalse($response['success']);
        $this->assertEquals('Access denied', $response['message']);
    }

    public function testInvalidFlightData()
    {
        // Mock authentication as admin
        $adminUser = $this->pdo->query("SELECT id FROM users WHERE username = 'testadmin'")->fetch();
        $token = $this->generateTestToken($adminUser['id']);

        // Mock the request
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/flights';
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;

        // Mock POST data with missing required fields
        $postData = [
            'flight_number' => 'INVALID_TEST',
            // Missing required fields: origin, destination, etc.
        ];

        // Simulate JSON input
        $jsonInput = json_encode($postData);
        file_put_contents('php://input', $jsonInput);

        // Capture output
        ob_start();
        include __DIR__ . '/../api/flights.php';
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertFalse($response['success']);
        $this->assertStringContains('Missing required field', $response['message']);
    }

    public function testNonExistentFlight()
    {
        // Mock the request for non-existent flight
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/flights/99999';

        // Capture output
        ob_start();
        include __DIR__ . '/../api/flights.php';
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertFalse($response['success']);
        $this->assertEquals('Flight not found', $response['message']);
    }

    private function generateTestToken($userId)
    {
        // Create a simple test token (in real scenario, use proper JWT)
        return base64_encode(json_encode([
            'user_id' => $userId,
            'exp' => time() + 3600,
            'iat' => time()
        ]));
    }
}
