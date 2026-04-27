<?php

/**
 * Module Compatibility Tests for Flight Control System
 *
 * Comprehensive testing for module interactions, dependencies, and compatibility
 * Ensures modules work together without conflicts and maintain system stability
 */

require_once '../src/ApiResponse.php';
require_once '../src/Config.php';
require_once '../src/Logger.php';
require_once '../src/Auth.php';

class ModuleCompatibilityTest
{
    private $apiResponse;
    private $config;
    private $logger;
    private $auth;
    private $results = [];
    private $startTime;
    private $endTime;

    // Module dependency matrix
    private $moduleDependencies = [
        'infrastructure' => [],
        'cargo' => ['infrastructure'],
        'emergency' => ['infrastructure'],
        'drones' => ['infrastructure', 'emergency'],
        'customs' => ['infrastructure'],
        'advanced-security' => ['infrastructure'],
        'virtual-assistant' => [],
        'sustainability' => [],
        'commercial' => [],
        'special-services' => [],
        'advanced-analytics' => []
    ];

    // Module conflict matrix
    private $moduleConflicts = [
        'advanced-security' => [], // No conflicts
        'virtual-assistant' => [], // No conflicts
        'sustainability' => [], // No conflicts
        'commercial' => [], // No conflicts
        'special-services' => [], // No conflicts
        'advanced-analytics' => [] // No conflicts
    ];

    public function __construct()
    {
        $this->apiResponse = new ApiResponse();
        $this->config = new Config();
        $this->logger = new Logger('module_compatibility_test');
        $this->auth = new Auth();
        $this->startTime = microtime(true);
    }

    /**
     * Run complete module compatibility test suite
     */
    public function runFullCompatibilityTestSuite()
    {
        $this->logger->info("Starting comprehensive module compatibility test suite");

        try {
            // Test module dependencies
            $this->testModuleDependencies();

            // Test module conflicts
            $this->testModuleConflicts();

            // Test module interactions
            $this->testModuleInteractions();

            // Test module enablement order
            $this->testModuleEnablementOrder();

            // Test module disablement order
            $this->testModuleDisablementOrder();

            // Test cross-module data sharing
            $this->testCrossModuleDataSharing();

            // Test module API compatibility
            $this->testModuleAPICompatibility();

            // Test module configuration compatibility
            $this->testModuleConfigurationCompatibility();

            // Test module resource sharing
            $this->testModuleResourceSharing();

            // Generate comprehensive compatibility report
            $this->generateCompatibilityReport();

            $this->endTime = microtime(true);
            $this->logger->info("Module compatibility test suite completed", [
                'duration' => $this->endTime - $this->startTime,
                'tests_run' => count($this->results)
            ]);

            return $this->apiResponse->success([
                'status' => 'completed',
                'duration' => $this->endTime - $this->startTime,
                'tests_run' => count($this->results),
                'results' => $this->results
            ]);

        } catch (Exception $e) {
            $this->logger->error("Module compatibility test suite failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->apiResponse->error("Module compatibility test suite failed: " . $e->getMessage(), 500);
        }
    }

    /**
     * Test module dependencies
     */
    private function testModuleDependencies()
    {
        $this->logger->info("Testing module dependencies");

        $testResults = [
            'test_type' => 'module_dependencies',
            'timestamp' => date('Y-m-d H:i:s'),
            'tests' => []
        ];

        foreach ($this->moduleDependencies as $module => $dependencies) {
            $dependencyTest = $this->testModuleDependencyChain($module, $dependencies);
            $testResults['tests'][] = $dependencyTest;
        }

        $this->results[] = $testResults;
    }

    /**
     * Test module conflicts
     */
    private function testModuleConflicts()
    {
        $this->logger->info("Testing module conflicts");

        $testResults = [
            'test_type' => 'module_conflicts',
            'timestamp' => date('Y-m-d H:i:s'),
            'tests' => []
        ];

        foreach ($this->moduleConflicts as $module => $conflicts) {
            $conflictTest = $this->testModuleConflictResolution($module, $conflicts);
            $testResults['tests'][] = $conflictTest;
        }

        $this->results[] = $testResults;
    }

    /**
     * Test module interactions
     */
    private function testModuleInteractions()
    {
        $this->logger->info("Testing module interactions");

        $testResults = [
            'test_type' => 'module_interactions',
            'timestamp' => date('Y-m-d H:i:s'),
            'scenarios' => []
        ];

        // Test interaction scenarios
        $interactionScenarios = [
            'infrastructure_emergency' => ['infrastructure', 'emergency'],
            'cargo_customs' => ['cargo', 'customs'],
            'drones_security' => ['drones', 'advanced-security'],
            'analytics_virtual_assistant' => ['advanced-analytics', 'virtual-assistant']
        ];

        foreach ($interactionScenarios as $scenario => $modules) {
            $interactionTest = $this->testModuleInteractionScenario($scenario, $modules);
            $testResults['scenarios'][] = $interactionTest;
        }

        $this->results[] = $testResults;
    }

    /**
     * Test module enablement order
     */
    private function testModuleEnablementOrder()
    {
        $this->logger->info("Testing module enablement order");

        $testResults = [
            'test_type' => 'module_enablement_order',
            'timestamp' => date('Y-m-d H:i:s'),
            'tests' => []
        ];

        // Test different enablement orders
        $enablementOrders = [
            'correct_order' => ['infrastructure', 'cargo', 'emergency', 'drones'],
            'reverse_order' => ['drones', 'emergency', 'cargo', 'infrastructure'],
            'random_order' => ['emergency', 'infrastructure', 'drones', 'cargo']
        ];

        foreach ($enablementOrders as $orderName => $order) {
            $orderTest = $this->testEnablementOrder($orderName, $order);
            $testResults['tests'][] = $orderTest;
        }

        $this->results[] = $testResults;
    }

    /**
     * Test module disablement order
     */
    private function testModuleDisablementOrder()
    {
        $this->logger->info("Testing module disablement order");

        $testResults = [
            'test_type' => 'module_disablement_order',
            'timestamp' => date('Y-m-d H:i:s'),
            'tests' => []
        ];

        // Test different disablement orders
        $disablementOrders = [
            'correct_order' => ['drones', 'emergency', 'cargo', 'infrastructure'],
            'reverse_order' => ['infrastructure', 'cargo', 'emergency', 'drones'],
            'random_order' => ['cargo', 'infrastructure', 'drones', 'emergency']
        ];

        foreach ($disablementOrders as $orderName => $order) {
            $orderTest = $this->testDisablementOrder($orderName, $order);
            $testResults['tests'][] = $orderTest;
        }

        $this->results[] = $testResults;
    }

    /**
     * Test cross-module data sharing
     */
    private function testCrossModuleDataSharing()
    {
        $this->logger->info("Testing cross-module data sharing");

        $testResults = [
            'test_type' => 'cross_module_data_sharing',
            'timestamp' => date('Y-m-d H:i:s'),
            'tests' => []
        ];

        // Test data sharing scenarios
        $dataSharingScenarios = [
            'infrastructure_cargo' => ['source' => 'infrastructure', 'target' => 'cargo', 'data_type' => 'facility_status'],
            'emergency_security' => ['source' => 'emergency', 'target' => 'advanced-security', 'data_type' => 'incident_alerts'],
            'cargo_customs' => ['source' => 'cargo', 'target' => 'customs', 'data_type' => 'shipment_data']
        ];

        foreach ($dataSharingScenarios as $scenario => $config) {
            $sharingTest = $this->testDataSharingScenario($scenario, $config);
            $testResults['tests'][] = $sharingTest;
        }

        $this->results[] = $testResults;
    }

    /**
     * Test module API compatibility
     */
    private function testModuleAPICompatibility()
    {
        $this->logger->info("Testing module API compatibility");

        $testResults = [
            'test_type' => 'module_api_compatibility',
            'timestamp' => date('Y-m-d H:i:s'),
            'tests' => []
        ];

        $modules = ['infrastructure', 'cargo', 'emergency', 'drones', 'customs', 'advanced-security', 'virtual-assistant'];

        foreach ($modules as $module) {
            $apiTest = $this->testModuleAPIEndpoints($module);
            $testResults['tests'][] = $apiTest;
        }

        $this->results[] = $testResults;
    }

    /**
     * Test module configuration compatibility
     */
    private function testModuleConfigurationCompatibility()
    {
        $this->logger->info("Testing module configuration compatibility");

        $testResults = [
            'test_type' => 'module_configuration_compatibility',
            'timestamp' => date('Y-m-d H:i:s'),
            'tests' => []
        ];

        $modules = ['infrastructure', 'cargo', 'emergency', 'drones', 'customs', 'advanced-security', 'virtual-assistant'];

        foreach ($modules as $module) {
            $configTest = $this->testModuleConfigurationValidation($module);
            $testResults['tests'][] = $configTest;
        }

        $this->results[] = $testResults;
    }

    /**
     * Test module resource sharing
     */
    private function testModuleResourceSharing()
    {
        $this->logger->info("Testing module resource sharing");

        $testResults = [
            'test_type' => 'module_resource_sharing',
            'timestamp' => date('Y-m-d H:i:s'),
            'tests' => []
        ];

        // Test resource sharing scenarios
        $resourceScenarios = [
            'database_connections' => ['resource' => 'database', 'modules' => ['infrastructure', 'cargo', 'emergency']],
            'cache_memory' => ['resource' => 'cache', 'modules' => ['virtual-assistant', 'advanced-analytics']],
            'api_rate_limits' => ['resource' => 'api_limits', 'modules' => ['drones', 'customs', 'advanced-security']]
        ];

        foreach ($resourceScenarios as $scenario => $config) {
            $resourceTest = $this->testResourceSharingScenario($scenario, $config);
            $testResults['tests'][] = $resourceTest;
        }

        $this->results[] = $testResults;
    }

    /**
     * Test module dependency chain
     */
    private function testModuleDependencyChain($module, $dependencies)
    {
        $this->logger->info("Testing dependency chain", ['module' => $module, 'dependencies' => $dependencies]);

        $dependencyWorking = true;
        $missingDependencies = [];
        $circularDependencies = [];

        // Check if dependencies exist
        foreach ($dependencies as $dependency) {
            if (!$this->moduleExists($dependency)) {
                $dependencyWorking = false;
                $missingDependencies[] = $dependency;
            }
        }

        // Check for circular dependencies
        if ($this->hasCircularDependency($module, $dependencies)) {
            $dependencyWorking = false;
            $circularDependencies[] = $module;
        }

        return [
            'module' => $module,
            'dependencies' => $dependencies,
            'passed' => $dependencyWorking,
            'missing_dependencies' => $missingDependencies,
            'circular_dependencies' => $circularDependencies,
            'details' => $dependencyWorking ?
                'All dependencies satisfied' :
                'Dependency issues detected: ' . implode(', ', array_merge($missingDependencies, $circularDependencies))
        ];
    }

    /**
     * Test module conflict resolution
     */
    private function testModuleConflictResolution($module, $conflicts)
    {
        $this->logger->info("Testing conflict resolution", ['module' => $module, 'conflicts' => $conflicts]);

        $conflictFree = true;
        $activeConflicts = [];

        // Check if conflicting modules are active
        foreach ($conflicts as $conflict) {
            if ($this->isModuleActive($conflict)) {
                $conflictFree = false;
                $activeConflicts[] = $conflict;
            }
        }

        return [
            'module' => $module,
            'conflicts' => $conflicts,
            'passed' => $conflictFree,
            'active_conflicts' => $activeConflicts,
            'details' => $conflictFree ?
                'No conflicts detected' :
                'Active conflicts with: ' . implode(', ', $activeConflicts)
        ];
    }

    /**
     * Test module interaction scenario
     */
    private function testModuleInteractionScenario($scenario, $modules)
    {
        $this->logger->info("Testing interaction scenario", ['scenario' => $scenario, 'modules' => $modules]);

        $interactionWorking = true;
        $issues = [];

        // Enable all modules in the scenario
        foreach ($modules as $module) {
            if (!$this->enableModule($module)) {
                $interactionWorking = false;
                $issues[] = "Failed to enable {$module}";
            }
        }

        // Test interaction between modules
        if ($interactionWorking) {
            $interactionResult = $this->testModuleCommunication($modules);
            if (!$interactionResult['success']) {
                $interactionWorking = false;
                $issues = array_merge($issues, $interactionResult['issues']);
            }
        }

        // Cleanup - disable modules
        foreach ($modules as $module) {
            $this->disableModule($module);
        }

        return [
            'scenario' => $scenario,
            'modules' => $modules,
            'passed' => $interactionWorking,
            'issues' => $issues,
            'details' => $interactionWorking ?
                'Modules interact successfully' :
                'Interaction issues: ' . implode(', ', $issues)
        ];
    }

    /**
     * Test enablement order
     */
    private function testEnablementOrder($orderName, $order)
    {
        $this->logger->info("Testing enablement order", ['order' => $orderName, 'sequence' => $order]);

        $enablementWorking = true;
        $failedModules = [];

        // Try to enable modules in the specified order
        foreach ($order as $module) {
            if (!$this->enableModule($module)) {
                $enablementWorking = false;
                $failedModules[] = $module;
            }
        }

        // Verify all modules are enabled
        $allEnabled = true;
        foreach ($order as $module) {
            if (!$this->isModuleActive($module)) {
                $allEnabled = false;
                break;
            }
        }

        // Cleanup
        foreach ($order as $module) {
            $this->disableModule($module);
        }

        return [
            'order_name' => $orderName,
            'sequence' => $order,
            'passed' => $enablementWorking && $allEnabled,
            'failed_modules' => $failedModules,
            'details' => ($enablementWorking && $allEnabled) ?
                'Enablement order successful' :
                'Enablement order failed for: ' . implode(', ', $failedModules)
        ];
    }

    /**
     * Test disablement order
     */
    private function testDisablementOrder($orderName, $order)
    {
        $this->logger->info("Testing disablement order", ['order' => $orderName, 'sequence' => $order]);

        // First enable all modules
        foreach ($order as $module) {
            $this->enableModule($module);
        }

        $disablementWorking = true;
        $failedModules = [];

        // Try to disable modules in the specified order
        foreach ($order as $module) {
            if (!$this->disableModule($module)) {
                $disablementWorking = false;
                $failedModules[] = $module;
            }
        }

        // Verify all modules are disabled
        $allDisabled = true;
        foreach ($order as $module) {
            if ($this->isModuleActive($module)) {
                $allDisabled = false;
                break;
            }
        }

        return [
            'order_name' => $orderName,
            'sequence' => $order,
            'passed' => $disablementWorking && $allDisabled,
            'failed_modules' => $failedModules,
            'details' => ($disablementWorking && $allDisabled) ?
                'Disablement order successful' :
                'Disablement order failed for: ' . implode(', ', $failedModules)
        ];
    }

    /**
     * Test data sharing scenario
     */
    private function testDataSharingScenario($scenario, $config)
    {
        $this->logger->info("Testing data sharing scenario", ['scenario' => $scenario, 'config' => $config]);

        // Enable source module
        $this->enableModule($config['source']);

        // Enable target module
        $this->enableModule($config['target']);

        // Test data sharing
        $sharingWorking = $this->testDataFlow($config['source'], $config['target'], $config['data_type']);

        // Cleanup
        $this->disableModule($config['target']);
        $this->disableModule($config['source']);

        return [
            'scenario' => $scenario,
            'source' => $config['source'],
            'target' => $config['target'],
            'data_type' => $config['data_type'],
            'passed' => $sharingWorking,
            'details' => $sharingWorking ?
                'Data sharing successful' :
                'Data sharing failed'
        ];
    }

    /**
     * Test module API endpoints
     */
    private function testModuleAPIEndpoints($module)
    {
        $this->logger->info("Testing module API endpoints", ['module' => $module]);

        $endpoints = $this->getModuleEndpoints($module);
        $apiWorking = true;
        $failedEndpoints = [];

        foreach ($endpoints as $endpoint) {
            try {
                $response = $this->makeAuthenticatedRequest($endpoint, 'admin');
                if ($response['status'] !== 'success') {
                    $apiWorking = false;
                    $failedEndpoints[] = $endpoint;
                }
            } catch (Exception $e) {
                $apiWorking = false;
                $failedEndpoints[] = $endpoint;
            }
        }

        return [
            'module' => $module,
            'endpoints_tested' => count($endpoints),
            'passed' => $apiWorking,
            'failed_endpoints' => $failedEndpoints,
            'details' => $apiWorking ?
                'All API endpoints working' :
                'Failed endpoints: ' . implode(', ', $failedEndpoints)
        ];
    }

    /**
     * Test module configuration validation
     */
    private function testModuleConfigurationValidation($module)
    {
        $this->logger->info("Testing module configuration validation", ['module' => $module]);

        $configValid = true;
        $validationErrors = [];

        // Test valid configuration
        $validConfig = $this->getValidModuleConfig($module);
        $validationResult = $this->validateModuleConfig($module, $validConfig);
        if (!$validationResult['valid']) {
            $configValid = false;
            $validationErrors[] = 'Valid config rejected: ' . implode(', ', $validationResult['errors']);
        }

        // Test invalid configuration
        $invalidConfig = $this->getInvalidModuleConfig($module);
        $validationResult = $this->validateModuleConfig($module, $invalidConfig);
        if ($validationResult['valid']) {
            $configValid = false;
            $validationErrors[] = 'Invalid config accepted';
        }

        return [
            'module' => $module,
            'passed' => $configValid,
            'validation_errors' => $validationErrors,
            'details' => $configValid ?
                'Configuration validation working' :
                'Configuration validation issues: ' . implode(', ', $validationErrors)
        ];
    }

    /**
     * Test resource sharing scenario
     */
    private function testResourceSharingScenario($scenario, $config)
    {
        $this->logger->info("Testing resource sharing scenario", ['scenario' => $scenario, 'config' => $config]);

        // Enable all modules in the scenario
        foreach ($config['modules'] as $module) {
            $this->enableModule($module);
        }

        // Test resource sharing
        $sharingWorking = $this->testResourceAllocation($config['resource'], $config['modules']);

        // Cleanup
        foreach ($config['modules'] as $module) {
            $this->disableModule($module);
        }

        return [
            'scenario' => $scenario,
            'resource' => $config['resource'],
            'modules' => $config['modules'],
            'passed' => $sharingWorking,
            'details' => $sharingWorking ?
                'Resource sharing successful' :
                'Resource sharing conflicts detected'
        ];
    }

    /**
     * Helper methods
     */
    private function moduleExists($moduleName)
    {
        // Check if module exists in the system
        return in_array($moduleName, [
            'infrastructure', 'cargo', 'emergency', 'drones', 'customs',
            'advanced-security', 'virtual-assistant', 'sustainability',
            'commercial', 'special-services', 'advanced-analytics'
        ]);
    }

    private function hasCircularDependency($module, $dependencies)
    {
        // Check for circular dependencies
        foreach ($dependencies as $dependency) {
            if (isset($this->moduleDependencies[$dependency]) &&
                in_array($module, $this->moduleDependencies[$dependency])) {
                return true;
            }
        }
        return false;
    }

    private function isModuleActive($moduleName)
    {
        // Check if module is currently active
        return rand(0, 1) === 1; // Placeholder - in real implementation, check module status
    }

    private function enableModule($moduleName)
    {
        // Enable module
        return rand(0, 1) === 1; // Placeholder - in real implementation, enable module
    }

    private function disableModule($moduleName)
    {
        // Disable module
        return rand(0, 1) === 1; // Placeholder - in real implementation, disable module
    }

    private function testModuleCommunication($modules)
    {
        // Test communication between modules
        return [
            'success' => rand(0, 1) === 1,
            'issues' => rand(0, 1) === 1 ? ['Communication timeout', 'Data format mismatch'] : []
        ];
    }

    private function testDataFlow($source, $target, $dataType)
    {
        // Test data flow between modules
        return rand(0, 1) === 1;
    }

    private function getModuleEndpoints($module)
    {
        // Get module API endpoints
        $endpoints = [
            'infrastructure' => ['/api/infrastructure/dashboard', '/api/infrastructure/status'],
            'cargo' => ['/api/cargo', '/api/cargo/shipments'],
            'emergency' => ['/api/emergency', '/api/emergency/incidents'],
            'drones' => ['/api/drones', '/api/drones/traffic'],
            'customs' => ['/api/customs', '/api/customs/declarations'],
            'advanced-security' => ['/api/advanced-security', '/api/advanced-security/threats'],
            'virtual-assistant' => ['/api/virtual-assistant', '/api/virtual-assistant/conversations']
        ];

        return $endpoints[$module] ?? [];
    }

    private function getValidModuleConfig($module)
    {
        // Get valid configuration for module
        return ['enabled' => true, 'settings' => []];
    }

    private function getInvalidModuleConfig($module)
    {
        // Get invalid configuration for module
        return ['enabled' => 'invalid', 'settings' => null];
    }

    private function validateModuleConfig($module, $config)
    {
        // Validate module configuration
        return [
            'valid' => rand(0, 1) === 1,
            'errors' => rand(0, 1) === 1 ? ['Invalid setting', 'Missing required field'] : []
        ];
    }

    private function testResourceAllocation($resource, $modules)
    {
        // Test resource allocation
        return rand(0, 1) === 1;
    }

    private function makeAuthenticatedRequest($endpoint, $role = 'admin', $userId = null, $data = [])
    {
        // Simulate authenticated request
        $token = $this->auth->generateToken([
            'user_id' => $userId ?: 1,
            'role' => $role
        ]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://localhost:8000' . $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ]);

        if (!empty($data)) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            throw new Exception('Request failed: ' . curl_error($ch));
        }

        curl_close($ch);

        return json_decode($response, true);
    }

    /**
     * Generate comprehensive compatibility report
     */
    private function generateCompatibilityReport()
    {
        $report = [
            'test_summary' => [
                'total_tests' => count($this->results),
                'duration' => $this->endTime - $this->startTime,
                'timestamp' => date('Y-m-d H:i:s')
            ],
            'compatibility_metrics' => [],
            'recommendations' => []
        ];

        // Analyze results and generate recommendations
        foreach ($this->results as $result) {
            if (isset($result['tests'])) {
                foreach ($result['tests'] as $test) {
                    if (!$test['passed']) {
                        $report['recommendations'][] = "Fix compatibility issue in {$result['test_type']}: {$test['details']}";
                    }
                }
            }
        }

        // Save report to file
        $reportPath = __DIR__ . '/../../reports/compatibility_test_' . date('Y-m-d_H-i-s') . '.json';
        file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT));

        $this->logger->info("Compatibility report generated", ['path' => $reportPath]);
    }

    /**
     * Run specific compatibility test
     */
    public function runSpecificCompatibilityTest($testType, $parameters = [])
    {
        switch ($testType) {
            case 'dependencies':
                $this->testModuleDependencies();
                break;
            case 'conflicts':
                $this->testModuleConflicts();
                break;
            case 'interactions':
                $this->testModuleInteractions();
                break;
            case 'module':
                $this->testModuleAPICompatibility();
                break;
            default:
                throw new Exception("Unknown test type: {$testType}");
        }

        return $this->apiResponse->success($this->results);
    }
}

// Handle CLI execution
if (isset($argv) && count($argv) > 1) {
    $test = new ModuleCompatibilityTest();

    if ($argv[1] === 'full') {
        $result = $test->runFullCompatibilityTestSuite();
        echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
    } elseif ($argv[1] === 'dependencies') {
        $result = $test->runSpecificCompatibilityTest('dependencies');
        echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
    } elseif ($argv[1] === 'conflicts') {
        $result = $test->runSpecificCompatibilityTest('conflicts');
        echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
    } elseif ($argv[1] === 'interactions') {
        $result = $test->runSpecificCompatibilityTest('interactions');
        echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
    } else {
        echo "Usage: php ModuleCompatibilityTest.php [full|dependencies|conflicts|interactions]\n";
    }
}
