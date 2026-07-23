<?php

/**
 * Unit tests for Module model
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../models/Module.php';

class ModuleTest extends TestCase
{
    private Module $module;
    private $db;

    protected function setUp(): void
    {
        $this->db = $this->getMockBuilder(PDO::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->module = new Module($this->db);
    }

    private function mockStmt(array $fetchAllReturn = [], $fetchReturn = null): object
    {
        $stmt = $this->getMockBuilder(PDOStatement::class)->getMock();
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->with(PDO::FETCH_ASSOC)->willReturn($fetchAllReturn);
        $stmt->method('fetch')->with(PDO::FETCH_ASSOC)->willReturn($fetchReturn);
        return $stmt;
    }

    public function testGetAllModulesReturnsArray(): void
    {
        $expected = [
            ['module_id' => 'sustainability', 'module_name' => 'Environmental & Sustainability', 'is_enabled' => true],
            ['module_id' => 'cargo', 'module_name' => 'Cargo Operations', 'is_enabled' => false],
        ];

        $stmt = $this->mockStmt($expected);
        $this->db->expects($this->once())
            ->method('prepare')
            ->willReturn($stmt);

        $result = $this->module->getAllModules();

        $this->assertEquals($expected, $result);
    }

    public function testGetEnabledModulesReturnsOnlyEnabled(): void
    {
        $expected = [
            ['module_id' => 'sustainability', 'module_name' => 'Environmental & Sustainability', 'is_enabled' => true],
        ];

        $stmt = $this->mockStmt($expected);
        $this->db->expects($this->once())
            ->method('prepare')
            ->willReturn($stmt);

        $result = $this->module->getEnabledModules();

        $this->assertEquals($expected, $result);
    }

    public function testIsModuleEnabledReturnsBool(): void
    {
        $stmt = $this->mockStmt([], ['is_enabled' => true]);
        $this->db->expects($this->once())
            ->method('prepare')
            ->willReturn($stmt);

        $result = $this->module->isModuleEnabled('sustainability');

        $this->assertTrue($result);
    }

    public function testIsModuleEnabledReturnsFalseWhenNotFound(): void
    {
        $stmt = $this->mockStmt([], false);
        $this->db->expects($this->once())
            ->method('prepare')
            ->willReturn($stmt);

        $result = $this->module->isModuleEnabled('nonexistent');

        $this->assertFalse($result);
    }

    public function testGetModulesByCategory(): void
    {
        $expected = [
            ['module_id' => 'cargo', 'module_name' => 'Cargo Operations', 'category' => 'operations'],
        ];

        $stmt = $this->mockStmt($expected);
        $this->db->expects($this->once())
            ->method('prepare')
            ->willReturn($stmt);

        $result = $this->module->getModulesByCategory('operations');

        $this->assertEquals($expected, $result);
    }

    public function testModuleConstants(): void
    {
        $this->assertEquals('enabled', Module::STATUS_ENABLED);
        $this->assertEquals('disabled', Module::STATUS_DISABLED);
        $this->assertEquals('error', Module::STATUS_ERROR);
        $this->assertEquals('operations', Module::CATEGORY_OPERATIONS);
        $this->assertEquals('passenger', Module::CATEGORY_PASSENGER);
    }

    public function testEnableModuleThrowsWhenNotFound(): void
    {
        $stmt = $this->mockStmt([], false);
        $this->db->method('prepare')->willReturn($stmt);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Module not found');

        $this->module->enableModule('nonexistent', 'admin');
    }

    public function testDisableModuleThrowsWhenNotFound(): void
    {
        $stmt = $this->mockStmt([], false);
        $this->db->method('prepare')->willReturn($stmt);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Module not found');

        $this->module->disableModule('nonexistent', 'admin');
    }

    public function testDatabaseConnectionFailure(): void
    {
        $this->db->method('prepare')
            ->willThrowException(new PDOException('Database connection failed'));

        $this->expectException(PDOException::class);
        $this->expectExceptionMessage('Database connection failed');

        $this->module->getAllModules();
    }
}
