<?php

/**
 * Unit tests for Module model
 */

class ModuleTest extends PHPUnit_Framework_TestCase
{
    private $module;
    private $db;

    protected function setUp()
    {
        // Mock database connection
        $this->db = $this->getMockBuilder(PDO::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->module = new Module();
        // Inject mock database
        $reflection = new ReflectionClass($this->module);
        $dbProperty = $reflection->getProperty('db');
        $dbProperty->setAccessible(true);
        $dbProperty->setValue($this->module, $this->db);
    }

    public function testGetAllModules()
    {
        // Mock the PDO statement
        $stmt = $this->getMockBuilder(PDOStatement::class)
            ->getMock();

        $expectedModules = [
            ['module_id' => 'sustainability', 'name' => 'Environmental & Sustainability', 'enabled' => true],
            ['module_id' => 'cargo', 'name' => 'Cargo Operations', 'enabled' => false]
        ];

        $stmt->expects($this->once())
            ->method('execute')
            ->willReturn(true);

        $stmt->expects($this->once())
            ->method('fetchAll')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn($expectedModules);

        $this->db->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('SELECT'))
            ->willReturn($stmt);

        $result = $this->module->getAllModules();

        $this->assertEquals($expectedModules, $result);
    }

    public function testEnableModule()
    {
        $stmt = $this->getMockBuilder(PDOStatement::class)
            ->getMock();

        $stmt->expects($this->once())
            ->method('execute')
            ->with(['sustainability', 'admin'])
            ->willReturn(true);

        $this->db->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('UPDATE'))
            ->willReturn($stmt);

        $result = $this->module->enableModule('sustainability', 'admin');

        $this->assertEquals([
            'module_id' => 'sustainability',
            'status' => 'enabled',
            'message' => 'Module enabled successfully'
        ], $result);
    }

    public function testDisableModule()
    {
        $stmt = $this->getMockBuilder(PDOStatement::class)
            ->getMock();

        $stmt->expects($this->once())
            ->method('execute')
            ->with(['cargo', 'admin'])
            ->willReturn(true);

        $this->db->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('UPDATE'))
            ->willReturn($stmt);

        $result = $this->module->disableModule('cargo', 'admin');

        $this->assertEquals([
            'module_id' => 'cargo',
            'status' => 'disabled',
            'message' => 'Module disabled successfully'
        ], $result);
    }

    public function testValidateModuleDependencies()
    {
        $stmt = $this->getMockBuilder(PDOStatement::class)
            ->getMock();

        $dependencies = [
            ['dependent_module' => 'advanced_analytics', 'dependency' => 'passenger_services']
        ];

        $stmt->expects($this->once())
            ->method('execute')
            ->with(['advanced_analytics'])
            ->willReturn(true);

        $stmt->expects($this->once())
            ->method('fetchAll')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn($dependencies);

        $this->db->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('SELECT'))
            ->willReturn($stmt);

        $result = $this->module->validateModuleDependencies('advanced_analytics');

        $this->assertEquals($dependencies, $result);
    }

    public function testCheckModuleCompatibility()
    {
        $stmt = $this->getMockBuilder(PDOStatement::class)
            ->getMock();

        $compatibility = [
            ['module_a' => 'sustainability', 'module_b' => 'infrastructure', 'compatible' => true]
        ];

        $stmt->expects($this->once())
            ->method('execute')
            ->with(['sustainability', 'infrastructure'])
            ->willReturn(true);

        $stmt->expects($this->once())
            ->method('fetchAll')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn($compatibility);

        $this->db->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('SELECT'))
            ->willReturn($stmt);

        $result = $this->module->checkModuleCompatibility('sustainability', 'infrastructure');

        $this->assertEquals($compatibility, $result);
    }

    public function testGetModuleConfiguration()
    {
        $stmt = $this->getMockBuilder(PDOStatement::class)
            ->getMock();

        $config = [
            'module_id' => 'sustainability',
            'config_key' => 'carbon_tracking_enabled',
            'config_value' => 'true',
            'data_type' => 'boolean'
        ];

        $stmt->expects($this->once())
            ->method('execute')
            ->with(['sustainability'])
            ->willReturn(true);

        $stmt->expects($this->once())
            ->method('fetchAll')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn([$config]);

        $this->db->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('SELECT'))
            ->willReturn($stmt);

        $result = $this->module->getModuleConfiguration('sustainability');

        $this->assertEquals([$config], $result);
    }

    public function testUpdateModuleConfiguration()
    {
        $stmt = $this->getMockBuilder(PDOStatement::class)
            ->getMock();

        $stmt->expects($this->once())
            ->method('execute')
            ->with(['sustainability', 'carbon_tracking_enabled', 'true', 'boolean', 'admin'])
            ->willReturn(true);

        $this->db->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('INSERT'))
            ->willReturn($stmt);

        $result = $this->module->updateModuleConfiguration(
            'sustainability',
            'carbon_tracking_enabled',
            'true',
            'boolean',
            'admin'
        );

        $this->assertEquals([
            'module_id' => 'sustainability',
            'config_key' => 'carbon_tracking_enabled',
            'status' => 'updated',
            'message' => 'Module configuration updated successfully'
        ], $result);
    }

    public function testGetModuleMetrics()
    {
        $stmt = $this->getMockBuilder(PDOStatement::class)
            ->getMock();

        $metrics = [
            'total_modules' => 12,
            'enabled_modules' => 8,
            'disabled_modules' => 4,
            'modules_with_dependencies' => 6
        ];

        $stmt->expects($this->exactly(4))
            ->method('execute')
            ->willReturn(true);

        $stmt->expects($this->exactly(4))
            ->method('fetchColumn')
            ->willReturnOnConsecutiveCalls(12, 8, 4, 6);

        $this->db->expects($this->exactly(4))
            ->method('prepare')
            ->willReturn($stmt);

        $result = $this->module->getModuleMetrics();

        $this->assertEquals($metrics, $result);
    }

    public function testGetModuleHealthStatus()
    {
        $stmt = $this->getMockBuilder(PDOStatement::class)
            ->getMock();

        $healthStatus = [
            'module_id' => 'sustainability',
            'status' => 'healthy',
            'last_check' => '2025-01-15 10:30:00',
            'issues' => []
        ];

        $stmt->expects($this->once())
            ->method('execute')
            ->with(['sustainability'])
            ->willReturn(true);

        $stmt->expects($this->once())
            ->method('fetch')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn($healthStatus);

        $this->db->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('SELECT'))
            ->willReturn($stmt);

        $result = $this->module->getModuleHealthStatus('sustainability');

        $this->assertEquals($healthStatus, $result);
    }

    public function testInvalidModuleId()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid module ID');

        $this->module->enableModule('', 'admin');
    }

    public function testDatabaseConnectionFailure()
    {
        $this->db->expects($this->once())
            ->method('prepare')
            ->willThrowException(new PDOException('Database connection failed'));

        $this->expectException(PDOException::class);
        $this->expectExceptionMessage('Database connection failed');

        $this->module->getAllModules();
    }
}
