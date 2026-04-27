<?php
/**
 * PHPUnit Bootstrap File for Flight Control Software
 */

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Load configuration
require_once __DIR__ . '/../config/database.php';

// Set up test environment
define('TESTING', true);
define('APP_ENV', 'testing');

// Create test database connection
function getTestDatabaseConnection() {
    static $pdo = null;

    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'pgsql:host=localhost;dbname=flight_control_test',
                'postgres',
                'password',
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {
            throw new RuntimeException('Test database connection failed: ' . $e->getMessage());
        }
    }

    return $pdo;
}

// Clean up test data
function cleanupTestData() {
    $pdo = getTestDatabaseConnection();

    // Disable foreign key checks temporarily
    $pdo->exec('SET CONSTRAINTS ALL DEFERRED');

    // Truncate all tables in reverse dependency order
    $tables = [
        'operational_logs',
        'crew_assignments',
        'maintenance_schedules',
        'check_ins',
        'baggage',
        'bookings',
        'passengers',
        'flights',
        'aircraft',
        'airlines',
        'users',
        'user_roles',
        'roles'
    ];

    foreach ($tables as $table) {
        try {
            $pdo->exec("TRUNCATE TABLE {$table} CASCADE");
        } catch (PDOException $e) {
            // Table might not exist, continue
            continue;
        }
    }

    // Re-enable foreign key checks
    $pdo->exec('SET CONSTRAINTS ALL IMMEDIATE');
}

// Set up test data fixtures
function setupTestFixtures() {
    $pdo = getTestDatabaseConnection();

    // Insert test roles
    $pdo->exec("
        INSERT INTO roles (name, description) VALUES
        ('super_admin', 'Super Administrator'),
        ('admin', 'Administrator'),
        ('operator', 'Airport Operator'),
        ('passenger', 'Passenger')
    ");

    // Insert test users
    $pdo->exec("
        INSERT INTO users (username, email, password_hash, first_name, last_name, role_id, is_active) VALUES
        ('testadmin', 'admin@test.com', '$2y$10\$test.hash.for.admin', 'Test', 'Admin', 2, true),
        ('testoperator', 'operator@test.com', '$2y$10\$test.hash.for.operator', 'Test', 'Operator', 3, true),
        ('testpassenger', 'passenger@test.com', '$2y$10\$test.hash.for.passenger', 'Test', 'Passenger', 4, true)
    ");

    // Insert test airlines
    $pdo->exec("
        INSERT INTO airlines (name, code, country) VALUES
        ('Test Airlines', 'TA', 'Test Country'),
        ('Another Airlines', 'AA', 'Another Country')
    ");

    // Insert test aircraft
    $pdo->exec("
        INSERT INTO aircraft (registration, model, capacity, airline_id) VALUES
        ('TEST001', 'Boeing 737', 150, 1),
        ('TEST002', 'Airbus A320', 180, 2)
    ");

    // Insert test flights
    $pdo->exec("
        INSERT INTO flights (flight_number, origin, destination, scheduled_departure, scheduled_arrival, aircraft_id, airline_id, status) VALUES
        ('TA001', 'JFK', 'LAX', '2025-01-15 08:00:00', '2025-01-15 11:00:00', 1, 1, 'scheduled'),
        ('AA002', 'LAX', 'JFK', '2025-01-15 14:00:00', '2025-01-15 17:00:00', 2, 2, 'scheduled')
    ");

    // Insert test passengers
    $pdo->exec("
        INSERT INTO passengers (first_name, last_name, email, phone, passport_number, nationality) VALUES
        ('John', 'Doe', 'john.doe@test.com', '+1234567890', 'P123456789', 'USA'),
        ('Jane', 'Smith', 'jane.smith@test.com', '+1234567891', 'P987654321', 'UK')
    ");
}

// Test helper functions
function createTestUser($role = 'passenger') {
    $pdo = getTestDatabaseConnection();

    $roleMap = [
        'super_admin' => 1,
        'admin' => 2,
        'operator' => 3,
        'passenger' => 4
    ];

    $roleId = $roleMap[$role] ?? 4;

    $stmt = $pdo->prepare("
        INSERT INTO users (username, email, password_hash, first_name, last_name, role_id, is_active)
        VALUES (?, ?, ?, ?, ?, ?, true)
    ");

    $username = 'testuser_' . uniqid();
    $email = $username . '@test.com';

    $stmt->execute([
        $username,
        $email,
        password_hash('testpassword', PASSWORD_DEFAULT),
        'Test',
        'User',
        $roleId
    ]);

    return $pdo->lastInsertId();
}

function createTestFlight() {
    $pdo = getTestDatabaseConnection();

    $stmt = $pdo->prepare("
        INSERT INTO flights (flight_number, origin, destination, scheduled_departure, scheduled_arrival, aircraft_id, airline_id, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $flightNumber = 'TEST' . rand(100, 999);

    $stmt->execute([
        $flightNumber,
        'JFK',
        'LAX',
        date('Y-m-d H:i:s', strtotime('+1 hour')),
        date('Y-m-d H:i:s', strtotime('+4 hours')),
        1,
        1,
        'scheduled'
    ]);

    return $pdo->lastInsertId();
}

function createTestPassenger() {
    $pdo = getTestDatabaseConnection();

    $stmt = $pdo->prepare("
        INSERT INTO passengers (first_name, last_name, email, phone, passport_number, nationality)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $firstName = 'Test' . rand(100, 999);
    $lastName = 'Passenger' . rand(100, 999);
    $email = strtolower($firstName . '.' . $lastName) . '@test.com';

    $stmt->execute([
        $firstName,
        $lastName,
        $email,
        '+1234567890',
        'P' . rand(100000000, 999999999),
        'Test Country'
    ]);

    return $pdo->lastInsertId();
}

// Mock HTTP request for API testing
function mockHttpRequest($method = 'GET', $uri = '/', $postData = null, $headers = []) {
    $_SERVER['REQUEST_METHOD'] = $method;
    $_SERVER['REQUEST_URI'] = $uri;

    if ($postData) {
        $_POST = $postData;
        $_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
    }

    foreach ($headers as $key => $value) {
        $_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $key))] = $value;
    }
}

// Clean up after each test
function tearDownTest() {
    cleanupTestData();
}

// Initialize test environment
if (!defined('TEST_DB_INITIALIZED')) {
    // Create test database if it doesn't exist
    try {
        $pdo = new PDO('pgsql:host=localhost', 'postgres', 'password');
        $pdo->exec('CREATE DATABASE IF NOT EXISTS flight_control_test');
        define('TEST_DB_INITIALIZED', true);
    } catch (PDOException $e) {
        throw new RuntimeException('Could not create test database: ' . $e->getMessage());
    }
}
