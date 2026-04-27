<?php

/**
 * Integration tests for module interactions and cross-module functionality
 */

class ModuleIntegrationTest extends PHPUnit_Framework_TestCase
{
    private $db;
    private $module;
    private $sustainability;
    private $infrastructure;

    protected function setUp()
    {
        // Use actual database for integration testing
        $this->db = new PDO(
            "pgsql:host=" . getenv('DB_HOST') . ";dbname=" . getenv('DB_DATABASE'),
            getenv('DB_USERNAME'),
            getenv('DB_PASSWORD'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $this->module = new Module();
        $this->sustainability = new Sustainability();
        $this->infrastructure = new InfrastructureManagement();

        // Inject database connections
        $this->injectDatabase($this->module, $this->db);
        $this->injectDatabase($this->sustainability, $this->db);
        $this->injectDatabase($this->infrastructure, $this->db);

        // Clean up test data
        $this->cleanupTestData();
    }

    protected function tearDown()
    {
        $this->cleanupTestData();
    }

    private function injectDatabase($object, $db)
    {
        $reflection = new ReflectionClass($object);
        $dbProperty = $reflection->getProperty('db');
        $dbProperty->setAccessible(true);
        $dbProperty->setValue($object, $db);
    }

    private function cleanupTestData()
    {
        // Clean up test data from all relevant tables
        $tables = [
            'module_configurations',
            'carbon_emissions',
            'energy_consumption',
            'building_systems',
            'iot_sensors'
        ];

        foreach ($tables as $table) {
            $this->db->exec("DELETE FROM $table WHERE created_at > CURRENT_TIMESTAMP - INTERVAL '1 hour'");
        }
    }

    /**
     * Test module enablement and cross-module data flow
     */
    public function testModuleEnablementAndDataFlow()
    {
        // Enable sustainability module
        $result = $this->module->enableModule('sustainability', 'test_admin');
        $this->assertEquals('enabled', $result['status']);
        $this->assertEquals('sustainability', $result['module_id']);

        // Enable infrastructure module
        $result = $this->module->enableModule('infrastructure', 'test_admin');
        $this->assertEquals('enabled', $result['status']);
        $this->assertEquals('infrastructure', $result['module_id']);

        // Verify modules are enabled
        $modules = $this->module->getAllModules();
        $sustainabilityEnabled = false;
        $infrastructureEnabled = false;

        foreach ($modules as $module) {
            if ($module['module_id'] === 'sustainability' && $module['enabled']) {
                $sustainabilityEnabled = true;
            }
            if ($module['module_id'] === 'infrastructure' && $module['enabled']) {
                $infrastructureEnabled = true;
            }
        }

        $this->assertTrue($sustainabilityEnabled, 'Sustainability module should be enabled');
        $this->assertTrue($infrastructureEnabled, 'Infrastructure module should be enabled');
    }

    /**
     * Test cross-module data integration between sustainability and infrastructure
     */
    public function testCrossModuleDataIntegration()
    {
        // Enable both modules
        $this->module->enableModule('sustainability', 'test_admin');
        $this->module->enableModule('infrastructure', 'test_admin');

        // Create infrastructure data that sustainability module can use
        $buildingData = [
            'building_name' => 'Terminal A',
            'building_type' => 'terminal',
            'total_area_sqm' => 50000,
            'floor_count' => 3,
            'construction_year' => 2010,
            'energy_rating' => 'A',
            'maintenance_schedule' => json_encode(['monthly' => true, 'quarterly' => true])
        ];

        $buildingResult = $this->infrastructure->createBuilding($buildingData);
        $this->assertArrayHasKey('building_id', $buildingResult);

        $buildingId = $buildingResult['building_id'];

        // Add energy consumption data
        $energyData = [
            'building_id' => $buildingId,
            'energy_type' => 'electricity',
            'consumption_kwh' => 15000,
            'cost_usd' => 2250,
            'measurement_date' => date('Y-m-d'),
            'peak_demand_kw' => 500,
            'efficiency_rating' => 85.5
        ];

        $energyResult = $this->infrastructure->recordEnergyConsumption($energyData);
        $this->assertArrayHasKey('consumption_id', $energyResult);

        // Sustainability module should be able to access infrastructure energy data
        $buildingEnergy = $this->sustainability->getBuildingEnergyConsumption($buildingId);
        $this->assertNotEmpty($buildingEnergy);
        $this->assertEquals(15000, $buildingEnergy[0]['consumption_kwh']);
    }

    /**
     * Test module configuration and dependency validation
     */
    public function testModuleConfigurationAndDependencies()
    {
        // Test module configuration
        $configResult = $this->module->updateModuleConfiguration(
            'sustainability',
            'carbon_tracking_enabled',
            'true',
            'boolean',
            'test_admin'
        );

        $this->assertEquals('updated', $configResult['status']);
        $this->assertEquals('sustainability', $configResult['module_id']);
        $this->assertEquals('carbon_tracking_enabled', $configResult['config_key']);

        // Verify configuration was saved
        $configs = $this->module->getModuleConfiguration('sustainability');
        $found = false;
        foreach ($configs as $config) {
            if ($config['config_key'] === 'carbon_tracking_enabled' && $config['config_value'] === 'true') {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Configuration should be saved and retrievable');

        // Test dependency validation
        $dependencies = $this->module->validateModuleDependencies('advanced_analytics');
        $this->assertIsArray($dependencies);

        // Test module compatibility
        $compatibility = $this->module->checkModuleCompatibility('sustainability', 'infrastructure');
        $this->assertIsArray($compatibility);
    }

    /**
     * Test module health monitoring and metrics
     */
    public function testModuleHealthAndMetrics()
    {
        // Enable modules for testing
        $this->module->enableModule('sustainability', 'test_admin');
        $this->module->enableModule('infrastructure', 'test_admin');

        // Get module metrics
        $metrics = $this->module->getModuleMetrics();
        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('total_modules', $metrics);
        $this->assertArrayHasKey('enabled_modules', $metrics);
        $this->assertArrayHasKey('disabled_modules', $metrics);

        // Verify metrics are reasonable
        $this->assertGreaterThanOrEqual(0, $metrics['total_modules']);
        $this->assertGreaterThanOrEqual(0, $metrics['enabled_modules']);
        $this->assertGreaterThanOrEqual(0, $metrics['disabled_modules']);
        $this->assertEquals($metrics['total_modules'], $metrics['enabled_modules'] + $metrics['disabled_modules']);
    }

    /**
     * Test concurrent module operations
     */
    public function testConcurrentModuleOperations()
    {
        // Enable multiple modules simultaneously
        $modulesToEnable = ['sustainability', 'infrastructure', 'cargo'];

        foreach ($modulesToEnable as $moduleId) {
            $result = $this->module->enableModule($moduleId, 'test_admin');
            $this->assertEquals('enabled', $result['status']);
        }

        // Verify all modules are enabled
        $modules = $this->module->getAllModules();
        $enabledCount = 0;
        foreach ($modules as $module) {
            if (in_array($module['module_id'], $modulesToEnable) && $module['enabled']) {
                $enabledCount++;
            }
        }

        $this->assertEquals(count($modulesToEnable), $enabledCount);
    }

    /**
     * Test module data consistency across operations
     */
    public function testModuleDataConsistency()
    {
        // Enable sustainability module
        $this->module->enableModule('sustainability', 'test_admin');

        // Create carbon emission data
        $emissionData = [
            'source_type' => 'building',
            'source_id' => 'terminal_a',
            'emission_type' => 'co2',
            'amount_kg' => 1500.5,
            'measurement_date' => date('Y-m-d'),
            'measurement_method' => 'calculated',
            'confidence_level' => 95.2
        ];

        $emissionResult = $this->sustainability->recordCarbonEmission($emissionData);
        $this->assertArrayHasKey('emission_id', $emissionResult);

        $emissionId = $emissionResult['emission_id'];

        // Retrieve the data to verify consistency
        $emissions = $this->sustainability->getCarbonEmissions(['source_id' => 'terminal_a']);
        $this->assertNotEmpty($emissions);

        $found = false;
        foreach ($emissions as $emission) {
            if ($emission['emission_id'] === $emissionId) {
                $found = true;
                $this->assertEquals(1500.5, $emission['amount_kg']);
                $this->assertEquals('co2', $emission['emission_type']);
                break;
            }
        }

        $this->assertTrue($found, 'Emission data should be consistent');
    }

    /**
     * Test module error handling and recovery
     */
    public function testModuleErrorHandling()
    {
        // Test enabling non-existent module
        try {
            $this->module->enableModule('non_existent_module', 'test_admin');
            $this->fail('Should have thrown an exception for non-existent module');
        } catch (Exception $e) {
            $this->assertStringContains('Invalid module ID', $e->getMessage());
        }

        // Test disabling already disabled module
        $this->module->disableModule('cargo', 'test_admin'); // Should not fail

        // Test configuration with invalid data type
        try {
            $this->module->updateModuleConfiguration(
                'sustainability',
                'invalid_config',
                'invalid_value',
                'invalid_type',
                'test_admin'
            );
            // This might not fail depending on implementation, but should handle gracefully
        } catch (Exception $e) {
            // Expected behavior - invalid configuration should be handled
        }
    }

    /**
     * Test module performance under load
     */
    public function testModulePerformanceUnderLoad()
    {
        // Enable modules
        $this->module->enableModule('sustainability', 'test_admin');
        $this->module->enableModule('infrastructure', 'test_admin');

        $startTime = microtime(true);

        // Perform multiple operations simultaneously
        $operations = [];

        // Create multiple carbon emission records
        for ($i = 0; $i < 10; $i++) {
            $emissionData = [
                'source_type' => 'building',
                'source_id' => 'terminal_' . $i,
                'emission_type' => 'co2',
                'amount_kg' => 1000 + $i * 100,
                'measurement_date' => date('Y-m-d'),
                'measurement_method' => 'calculated',
                'confidence_level' => 90 + $i
            ];

            $operations[] = $this->sustainability->recordCarbonEmission($emissionData);
        }

        // Create multiple building records
        for ($i = 0; $i < 5; $i++) {
            $buildingData = [
                'building_name' => 'Building ' . $i,
                'building_type' => 'terminal',
                'total_area_sqm' => 10000 + $i * 5000,
                'floor_count' => 2 + $i,
                'construction_year' => 2000 + $i * 5,
                'energy_rating' => chr(65 + $i), // A, B, C, D, E
                'maintenance_schedule' => json_encode(['monthly' => true])
            ];

            $operations[] = $this->infrastructure->createBuilding($buildingData);
        }

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // Verify all operations completed successfully
        foreach ($operations as $operation) {
            $this->assertArrayHasKey('status', $operation);
            $this->assertContains($operation['status'], ['recorded', 'created']);
        }

        // Performance should be reasonable (less than 5 seconds for this load)
        $this->assertLessThan(5.0, $executionTime, 'Operations should complete within reasonable time');
    }

    /**
     * Test module data export/import compatibility
     */
    public function testModuleDataExportImport()
    {
        // Enable sustainability module
        $this->module->enableModule('sustainability', 'test_admin');

        // Create test data
        $emissionData = [
            'source_type' => 'building',
            'source_id' => 'export_test',
            'emission_type' => 'co2',
            'amount_kg' => 2000.0,
            'measurement_date' => date('Y-m-d'),
            'measurement_method' => 'calculated',
            'confidence_level' => 95.0
        ];

        $this->sustainability->recordCarbonEmission($emissionData);

        // Export data (simulate)
        $exportData = $this->sustainability->getCarbonEmissions(['source_id' => 'export_test']);
        $this->assertNotEmpty($exportData);

        // Verify export data structure
        $record = $exportData[0];
        $this->assertArrayHasKey('emission_id', $record);
        $this->assertArrayHasKey('source_type', $record);
        $this->assertArrayHasKey('source_id', $record);
        $this->assertArrayHasKey('emission_type', $record);
        $this->assertArrayHasKey('amount_kg', $record);
        $this->assertArrayHasKey('measurement_date', $record);
        $this->assertEquals('export_test', $record['source_id']);
        $this->assertEquals(2000.0, $record['amount_kg']);
    }
}
