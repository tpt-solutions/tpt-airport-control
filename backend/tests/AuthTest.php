<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/JWT.php';

class AuthTest extends TestCase
{
    private $pdo;
    private $auth;

    protected function setUp(): void
    {
        $this->pdo = getTestDatabaseConnection();
        $this->auth = new Auth($this->pdo);

        // Clean up and setup test data
        cleanupTestData();
        setupTestFixtures();
    }

    protected function tearDown(): void
    {
        cleanupTestData();
    }

    public function testUserRegistration()
    {
        $userData = [
            'username' => 'newuser',
            'email' => 'newuser@test.com',
            'password' => 'testpassword123',
            'first_name' => 'New',
            'last_name' => 'User'
        ];

        $result = $this->auth->register($userData);

        $this->assertTrue($result['success']);
        $this->assertEquals('User registered successfully', $result['message']);

        // Verify user was created
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute(['newuser']);
        $user = $stmt->fetch();

        $this->assertNotNull($user);
        $this->assertEquals('newuser@test.com', $user['email']);
        $this->assertEquals('New', $user['first_name']);
        $this->assertEquals('User', $user['last_name']);
    }

    public function testUserLogin()
    {
        $result = $this->auth->login('testadmin', 'testpassword');

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('user', $result);
        $this->assertEquals('testadmin', $result['user']['username']);
    }

    public function testInvalidLogin()
    {
        $result = $this->auth->login('nonexistent', 'wrongpassword');

        $this->assertFalse($result['success']);
        $this->assertEquals('Invalid credentials', $result['message']);
    }

    public function testTokenValidation()
    {
        // First login to get a token
        $loginResult = $this->auth->login('testadmin', 'testpassword');
        $token = $loginResult['token'];

        // Validate the token
        $validationResult = $this->auth->validateToken($token);

        $this->assertTrue($validationResult['valid']);
        $this->assertEquals('testadmin', $validationResult['user']['username']);
    }

    public function testInvalidTokenValidation()
    {
        $validationResult = $this->auth->validateToken('invalid.token.here');

        $this->assertFalse($validationResult['valid']);
        $this->assertEquals('Invalid token', $validationResult['message']);
    }

    public function testPasswordResetRequest()
    {
        $result = $this->auth->requestPasswordReset('testadmin@test.com');

        $this->assertTrue($result['success']);
        $this->assertEquals('Password reset token generated', $result['message']);
    }

    public function testPasswordResetWithInvalidEmail()
    {
        $result = $this->auth->requestPasswordReset('nonexistent@test.com');

        $this->assertFalse($result['success']);
        $this->assertEquals('User not found', $result['message']);
    }

    public function testPasswordReset()
    {
        // First request a reset
        $this->auth->requestPasswordReset('testadmin@test.com');

        // Get the reset token from database
        $stmt = $this->pdo->prepare("SELECT reset_token FROM users WHERE email = ?");
        $stmt->execute(['testadmin@test.com']);
        $user = $stmt->fetch();

        $this->assertNotNull($user['reset_token']);

        // Reset password
        $result = $this->auth->resetPassword($user['reset_token'], 'newpassword123');

        $this->assertTrue($result['success']);
        $this->assertEquals('Password reset successfully', $result['message']);

        // Verify password was changed
        $stmt = $this->pdo->prepare("SELECT password_hash, reset_token FROM users WHERE email = ?");
        $stmt->execute(['testadmin@test.com']);
        $updatedUser = $stmt->fetch();

        $this->assertTrue(password_verify('newpassword123', $updatedUser['password_hash']));
        $this->assertNull($updatedUser['reset_token']);
    }

    public function testGetCurrentUser()
    {
        // Login first
        $loginResult = $this->auth->login('testadmin', 'testpassword');
        $token = $loginResult['token'];

        // Mock the Authorization header
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;

        $currentUser = Auth::getCurrentUser();

        $this->assertNotNull($currentUser);
        $this->assertEquals('testadmin', $currentUser['username']);
        $this->assertEquals('admin', $currentUser['role_name']);
    }

    public function testHasPermission()
    {
        // Login as admin
        $loginResult = $this->auth->login('testadmin', 'testpassword');
        $token = $loginResult['token'];

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;

        $this->assertTrue(Auth::hasPermission('read', 'flights'));
        $this->assertTrue(Auth::hasPermission('write', 'passengers'));
        $this->assertTrue(Auth::hasPermission('admin', 'users'));
    }

    public function testOperatorPermissions()
    {
        // Login as operator
        $loginResult = $this->auth->login('testoperator', 'testpassword');
        $token = $loginResult['token'];

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;

        $this->assertTrue(Auth::hasPermission('read', 'flights'));
        $this->assertTrue(Auth::hasPermission('write', 'passengers'));
        $this->assertFalse(Auth::hasPermission('admin', 'users'));
    }

    public function testPassengerPermissions()
    {
        // Login as passenger
        $loginResult = $this->auth->login('testpassenger', 'testpassword');
        $token = $loginResult['token'];

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;

        $this->assertTrue(Auth::hasPermission('read_own', 'passengers'));
        $this->assertFalse(Auth::hasPermission('read', 'flights'));
        $this->assertFalse(Auth::hasPermission('write', 'passengers'));
    }

    public function testDuplicateUsernameRegistration()
    {
        $userData = [
            'username' => 'testadmin', // Already exists
            'email' => 'different@test.com',
            'password' => 'testpassword123',
            'first_name' => 'Different',
            'last_name' => 'User'
        ];

        $result = $this->auth->register($userData);

        $this->assertFalse($result['success']);
        $this->assertEquals('Username already exists', $result['message']);
    }

    public function testDuplicateEmailRegistration()
    {
        $userData = [
            'username' => 'differentuser',
            'email' => 'testadmin@test.com', // Already exists
            'password' => 'testpassword123',
            'first_name' => 'Different',
            'last_name' => 'User'
        ];

        $result = $this->auth->register($userData);

        $this->assertFalse($result['success']);
        $this->assertEquals('Email already exists', $result['message']);
    }
}
