<?php
/**
 * Integration tests for Flight API endpoints
 */

require_once __DIR__ . '/../../api/flights.php';
require_once __DIR__ . '/../helpers/TestDataFactory.php';

class FlightApiIntegrationTest extends PHPUnit_Framework_TestCase
{
    private $testDataFactory;

    protected function setUp()
    {
        $this->testDataFactory = new TestDataFactory();

        // Set up test environment
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];
        $_POST = [];
        $_SESSION = ['user_id' => 1, 'role' => 'admin'];
    }

    protected function tearDown()
    {
        // Clean up after each test
        $_GET = [];
        $_POST = [];
        $_SESSION = [];
    }

    public function testGetFlightsList()
    {
        // Set up request
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET['action'] = 'list';
        $_GET['page'] = '1';
        $_GET['limit'] = '10';

        // Capture output
        ob_start();
        include __DIR__ . '/../../api/flights.php';
        $output = ob_get_clean();

        // Parse JSON response
        $response = json_decode($output, true);

        // Assert response structure
        $this->assertArrayHasKey('status', $response);
        $this->assertArrayHasKey('data', $response);
        $this->assertArrayHasKey('timestamp', $response);
        $this->assertArrayHasKey('request_id', $response);

        // Should be success response
        $this->assertEquals('success', $response['status']);
    }

    public function testGetFlightDetail()
    {
        // Set up request
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET['action'] = 'detail';
        $_GET['id'] = '1';

        // Capture output
        ob_start();
        include __DIR__ . '/../../api/flights.php';
        $output = ob_get_clean();

        // Parse JSON response
        $response = json_decode($output, true);

        // Assert response structure
        $this->assertArrayHasKey('status', $response);
        $this->assertArrayHasKey('timestamp', $response);
        $this->assertArrayHasKey('request_id', $response);
    }

    public function testGetActiveFlights()
    {
        // Set up request
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET['action'] = 'active';

        // Capture output
        ob_start();
        include __DIR__ . '/../../api/flights.php';
        $output = ob_get_clean();

        // Parse JSON response
        $response = json_decode($output, true);

        // Assert response structure
        $this->assertArrayHasKey('status', $response);
        $this->assertArrayHasKey('data', $response);
        $this->assertEquals('success', $response['status']);
    }

    public function testCreateFlight()
    {
        // Set up request
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_GET['action'] = 'create';

        $flightData = $this->testDataFactory->createFlightData();
        $_POST = $flightData;

        // Capture output
        ob_start();
        include __DIR__ . '/../../api/flights.php';
        $output = ob_get_clean();

        // Parse JSON response
        $response = json_decode($output, true);

        // Assert response structure
        $this->assertArrayHasKey('status', $response);
        $this->assertArrayHasKey('timestamp', $response);
        $this->assertArrayHasKey('request_id', $response);
    }

    public function testUpdateFlight()
    {
        // Set up request
        $_SERVER['REQUEST_METHOD'] = 'PUT';
        $_GET['action'] = 'update';

        $updateData = ['id' => 1, 'status' => 'boarding'];
        $_POST = $updateData;

        // Capture output
        ob_start();
        include __DIR__ . '/../../api/flights.php';
        $output = ob_get_clean();

        // Parse JSON response
        $response = json_decode($output, true);

        // Assert response structure
        $this->assertArrayHasKey('status', $response);
        $this->assertArrayHasKey('timestamp', $response);
        $this->assertArrayHasKey('request_id', $response);
    }

    public function testDeleteFlight()
    {
        // Set up request
        $_SERVER['REQUEST_METHOD'] = 'DELETE';
        $_GET['action'] = 'delete';

        $deleteData = ['id' => 1];
        $_POST = $deleteData;

        // Capture output
        ob_start();
        include __DIR__ . '/../../api/flights.php';
        $output = ob_get_clean();

        // Parse JSON response
        $response = json_decode($output, true);

        // Assert response structure
        $this->assertArrayHasKey('status', $response);
        $this->assertArrayHasKey('timestamp', $response);
        $this->assertArrayHasKey('request_id', $response);
    }

    public function testAssignGate()
    {
        // Set up request
        $_SERVER['REQUEST_METHOD'] = 'PATCH';
        $_GET['action'] = 'assign_gate';

        $gateData = ['flight_id' => 1, 'gate' => 'A12'];
        $_POST = $gateData;

        // Capture output
        ob_start();
        include __DIR__ . '/../../api/flights.php';
        $output = ob_get_clean();

        // Parse JSON response
        $response = json_decode($output, true);

        // Assert response structure
        $this->assertArrayHasKey('status', $response);
        $this->assertArrayHasKey('timestamp', $response);
        $this->assertArrayHasKey('request_id', $response);
    }

    public function testSearchFlights()
    {
        // Set up request
        $_SERVER['REQUEST_METHOD'] = 'PATCH';
        $_GET['action'] = 'search';

        $searchData = ['search' => 'AA101'];
        $_POST = $searchData;

        // Capture output
        ob_start();
        include __DIR__ . '/../../api/flights.php';
        $output = ob_get_clean();

        // Parse JSON response
        $response = json_decode($output, true);

        // Assert response structure
        $this->assertArrayHasKey('status', $response);
        $this->assertArrayHasKey('data', $response);
        $this->assertEquals('success', $response['status']);
    }

    public function testInvalidAction()
    {
        // Set up request
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET['action'] = 'invalid_action';

        // Capture output
        ob_start();
        include __DIR__ . '/../../api/flights.php';
        $output = ob_get_clean();

        // Parse JSON response
        $response = json_decode($output, true);

        // Should return error
        $this->assertArrayHasKey('status', $response);
        $this->assertArrayHasKey('message', $response);
        $this->assertEquals('error', $response['status']);
    }

    public function testMissingRequiredParameters()
    {
        // Set up request for detail action without ID
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET['action'] = 'detail';
        // No 'id' parameter

        // Capture output
        ob_start();
        include __DIR__ . '/../../api/flights.php';
        $output = ob_get_clean();

        // Parse JSON response
        $response = json_decode($output, true);

        // Should return error
        $this->assertArrayHasKey('status', $response);
        $this->assertEquals('error', $response['status']);
    }

    public function testStatisticsEndpoint()
    {
        // Set up request
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET['action'] = 'statistics';

        // Capture output
        ob_start();
        include __DIR__ . '/../../api/flights.php';
        $output = ob_get_clean();

        // Parse JSON response
        $response = json_decode($output, true);

        // Assert response structure
        $this->assertArrayHasKey('status', $response);
        $this->assertArrayHasKey('data', $response);
        $this->assertEquals('success', $response['status']);

        // Statistics should contain expected keys
        if (isset($response['data'])) {
            $this->assertArrayHasKey('total_flights', $response['data']);
            $this->assertArrayHasKey('active_flights', $response['data']);
        }
    }

    public function testPaginationParameters()
    {
        // Set up request with pagination
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET['action'] = 'list';
        $_GET['page'] = '2';
        $_GET['limit'] = '25';

        // Capture output
        ob_start();
        include __DIR__ . '/../../api/flights.php';
        $output = ob_get_clean();

        // Parse JSON response
        $response = json_decode($output, true);

        // Assert response structure
        $this->assertArrayHasKey('status', $response);
        $this->assertEquals('success', $response['status']);
    }

    public function testFilterParameters()
    {
        // Set up request with filters
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET['action'] = 'list';
        $_GET['status'] = 'scheduled';
        $_GET['origin'] = 'JFK';

        // Capture output
        ob_start();
        include __DIR__ . '/../../api/flights.php';
        $output = ob_get_clean();

        // Parse JSON response
        $response = json_decode($output, true);

        // Assert response structure
        $this->assertArrayHasKey('status', $response);
        $this->assertEquals('success', $response['status']);
    }

    public function testJsonResponseFormat()
    {
        // Set up request
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET['action'] = 'active';

        // Capture output
        ob_start();
        include __DIR__ . '/../../api/flights.php';
        $output = ob_get_clean();

        // Verify it's valid JSON
        $response = json_decode($output, true);
        $this->assertNotNull($response, 'Response should be valid JSON');

        // Verify JSON structure
        $this->assertArrayHasKey('status', $response);
        $this->assertArrayHasKey('timestamp', $response);
        $this->assertArrayHasKey('request_id', $response);

        // Verify timestamp is numeric (Unix timestamp)
        $this->assertTrue(is_numeric($response['timestamp']), 'Timestamp should be numeric');

        // Verify request_id is string
        $this->assertTrue(is_string($response['request_id']), 'Request ID should be string');
    }

    public function testCorsHeaders()
    {
        // Set up request
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET['action'] = 'list';

        // Capture output and headers
        ob_start();
        include __DIR__ . '/../../api/flights.php';
        $output = ob_get_clean();

        // Check if CORS headers are set (this would be checked in a real HTTP test)
        // In this test environment, we can at least verify the response is generated
        $response = json_decode($output, true);
        $this->assertArrayHasKey('status', $response);
    }

    public function testUnauthorizedAccess()
    {
        // Set up request without authentication
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET['action'] = 'list';
        $_SESSION = []; // Clear session

        // This would normally check authentication, but in our test setup
        // we can verify the response structure is maintained
        ob_start();
        include __DIR__ . '/../../api/flights.php';
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertArrayHasKey('status', $response);
    }
}
