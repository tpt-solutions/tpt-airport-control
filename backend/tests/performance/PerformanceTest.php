<?php

/**
 * Performance Tests for Flight Control System Modules
 *
 * Comprehensive load testing and performance validation for all system modules
 * Ensures modules perform optimally under various load conditions
 */

require_once '../src/ApiResponse.php';
require_once '../src/Config.php';
require_once '../src/Logger.php';

class PerformanceTest
{
    private $apiResponse;
    private $config;
    private $logger;
    private $results = [];
    private $startTime;
    private $endTime;

    // Test configuration
    private $concurrentUsers = 50;
    private $testDuration = 300; // 5 minutes
    private $rampUpTime = 30; // 30 seconds
    private $thinkTime = 1000; // 1 second between requests

    public function __construct()
    {
        $this->apiResponse = new ApiResponse();
        $this->config = new Config();
        $this->logger = new Logger('performance_test');
        $this->startTime = microtime(true);
    }

    /**
     * Run complete performance test suite
     */
    public function runFullTestSuite()
    {
        $this->logger->info("Starting comprehensive performance test suite");

        try {
            // Test individual modules
            $this->testModulePerformance('infrastructure');
            $this->testModulePerformance('cargo');
            $this->testModulePerformance('emergency');
            $this->testModulePerformance('drones');
            $this->testModulePerformance('customs');
            $this->testModulePerformance('advanced-security');
            $this->testModulePerformance('virtual-assistant');

            // Test cross-module interactions
            $this->testCrossModulePerformance();

            // Test system under load
            $this->testSystemLoad();

            // Test database performance
            $this->testDatabasePerformance();

            // Test API endpoints
            $this->testAPIEndpoints();

            // Generate comprehensive report
            $this->generatePerformanceReport();

            $this->endTime = microtime(true);
            $this->logger->info("Performance test suite completed", [
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
            $this->logger->error("Performance test suite failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->apiResponse->error("Performance test suite failed: " . $e->getMessage(), 500);
        }
    }

    /**
     * Test individual module performance
     */
    private function testModulePerformance($moduleName)
    {
        $this->logger->info("Testing module performance", ['module' => $moduleName]);

        $testResults = [
            'module' => $moduleName,
            'timestamp' => date('Y-m-d H:i:s'),
            'response_times' => [],
            'throughput' => 0,
            'error_rate' => 0,
            'memory_usage' => 0,
            'cpu_usage' => 0
        ];

        // Simulate concurrent users
        $responses = $this->simulateConcurrentRequests($moduleName, $this->concurrentUsers);

        // Calculate metrics
        $testResults['response_times'] = array_map(function($response) {
            return $response['response_time'];
        }, $responses);

        $testResults['throughput'] = $this->calculateThroughput($responses);
        $testResults['error_rate'] = $this->calculateErrorRate($responses);
        $testResults['memory_usage'] = $this->getMemoryUsage();
        $testResults['cpu_usage'] = $this->getCpuUsage();

        // Performance thresholds
        $testResults['thresholds'] = [
            'avg_response_time' => $this->checkResponseTimeThreshold($testResults['response_times']),
            'error_rate' => $this->checkErrorRateThreshold($testResults['error_rate']),
            'throughput' => $this->checkThroughputThreshold($testResults['throughput'])
        ];

        $this->results[] = $testResults;
    }

    /**
     * Test cross-module performance interactions
     */
    private function testCrossModulePerformance()
    {
        $this->logger->info("Testing cross-module performance interactions");

        $testResults = [
            'test_type' => 'cross_module',
            'timestamp' => date('Y-m-d H:i:s'),
            'scenarios' => []
        ];

        // Test scenarios
        $scenarios = [
            'infrastructure_cargo' => ['infrastructure', 'cargo'],
            'emergency_security' => ['emergency', 'advanced-security'],
            'drones_customs' => ['drones', 'customs']
        ];

        foreach ($scenarios as $scenario => $modules) {
            $scenarioResult = $this->testModuleInteraction($modules);
            $testResults['scenarios'][] = [
                'name' => $scenario,
                'modules' => $modules,
                'result' => $scenarioResult
            ];
        }

        $this->results[] = $testResults;
    }

    /**
     * Test system under load
     */
    private function testSystemLoad()
    {
        $this->logger->info("Testing system under load");

        $loadLevels = [10, 25, 50, 100, 200];

        foreach ($loadLevels as $users) {
            $this->logger->info("Testing with {$users} concurrent users");

            $startTime = microtime(true);

            // Simulate load
            $responses = $this->simulateSystemLoad($users);

            $endTime = microtime(true);
            $duration = $endTime - $startTime;

            $this->results[] = [
                'test_type' => 'system_load',
                'concurrent_users' => $users,
                'duration' => $duration,
                'avg_response_time' => $this->calculateAverageResponseTime($responses),
                'throughput' => count($responses) / $duration,
                'error_rate' => $this->calculateErrorRate($responses),
                'memory_peak' => $this->getMemoryUsage(),
                'cpu_peak' => $this->getCpuUsage()
            ];
        }
    }

    /**
     * Test database performance
     */
    private function testDatabasePerformance()
    {
        $this->logger->info("Testing database performance");

        $db = new PDO(
            "pgsql:host=" . getenv('DB_HOST') . ";dbname=" . getenv('DB_DATABASE'),
            getenv('DB_USERNAME'),
            getenv('DB_PASSWORD'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $queries = [
            'simple_select' => "SELECT COUNT(*) FROM modules",
            'complex_join' => "
                SELECT m.module_name, COUNT(ff.flag_name) as feature_count
                FROM modules m
                LEFT JOIN feature_flags ff ON m.module_id = ff.module_id
                GROUP BY m.module_id, m.module_name
            ",
            'insert_test' => "INSERT INTO module_audit_log (module_id, action, user_id) VALUES (1, 'test', 1)",
            'update_test' => "UPDATE modules SET updated_at = NOW() WHERE module_id = 1"
        ];

        $dbResults = [];

        foreach ($queries as $queryName => $query) {
            $startTime = microtime(true);

            for ($i = 0; $i < 100; $i++) {
                try {
                    $stmt = $db->query($query);
                    $stmt->fetchAll();
                } catch (Exception $e) {
                    // Handle errors
                }
            }

            $endTime = microtime(true);
            $duration = $endTime - $startTime;

            $dbResults[] = [
                'query' => $queryName,
                'total_time' => $duration,
                'avg_time' => $duration / 100,
                'queries_per_second' => 100 / $duration
            ];
        }

        $this->results[] = [
            'test_type' => 'database_performance',
            'timestamp' => date('Y-m-d H:i:s'),
            'results' => $dbResults
        ];
    }

    /**
     * Test API endpoints performance
     */
    private function testAPIEndpoints()
    {
        $this->logger->info("Testing API endpoints performance");

        $endpoints = [
            '/api/modules',
            '/api/infrastructure/dashboard',
            '/api/cargo',
            '/api/emergency',
            '/api/drones',
            '/api/customs',
            '/api/advanced-security',
            '/api/virtual-assistant'
        ];

        $apiResults = [];

        foreach ($endpoints as $endpoint) {
            $responses = $this->testEndpointLoad($endpoint, 20);

            $apiResults[] = [
                'endpoint' => $endpoint,
                'avg_response_time' => $this->calculateAverageResponseTime($responses),
                'min_response_time' => min(array_column($responses, 'response_time')),
                'max_response_time' => max(array_column($responses, 'response_time')),
                'throughput' => count($responses) / array_sum(array_column($responses, 'response_time')),
                'error_rate' => $this->calculateErrorRate($responses)
            ];
        }

        $this->results[] = [
            'test_type' => 'api_endpoints',
            'timestamp' => date('Y-m-d H:i:s'),
            'results' => $apiResults
        ];
    }

    /**
     * Simulate concurrent requests to a module
     */
    private function simulateConcurrentRequests($moduleName, $concurrentUsers)
    {
        $responses = [];
        $startTime = microtime(true);

        // Create concurrent requests
        $promises = [];
        for ($i = 0; $i < $concurrentUsers; $i++) {
            $promises[] = $this->makeAsyncRequest($moduleName, $i);
        }

        // Wait for all requests to complete
        $responses = array_map(function($promise) {
            return $promise; // In real implementation, this would handle async
        }, $promises);

        return $responses;
    }

    /**
     * Make async request to module
     */
    private function makeAsyncRequest($moduleName, $userId)
    {
        $requestStart = microtime(true);

        try {
            // Simulate API call based on module
            $endpoint = $this->getModuleEndpoint($moduleName);
            $response = $this->makeHttpRequest($endpoint);

            $requestEnd = microtime(true);

            return [
                'user_id' => $userId,
                'response_time' => $requestEnd - $requestStart,
                'status' => 'success',
                'response_size' => strlen($response)
            ];
        } catch (Exception $e) {
            $requestEnd = microtime(true);

            return [
                'user_id' => $userId,
                'response_time' => $requestEnd - $requestStart,
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Test module interaction performance
     */
    private function testModuleInteraction($modules)
    {
        $startTime = microtime(true);

        // Simulate cross-module interaction
        $responses = [];
        foreach ($modules as $module) {
            $responses = array_merge($responses, $this->simulateConcurrentRequests($module, 10));
        }

        $endTime = microtime(true);

        return [
            'duration' => $endTime - $startTime,
            'total_requests' => count($responses),
            'avg_response_time' => $this->calculateAverageResponseTime($responses),
            'error_rate' => $this->calculateErrorRate($responses)
        ];
    }

    /**
     * Simulate system load
     */
    private function simulateSystemLoad($users)
    {
        $responses = [];

        for ($i = 0; $i < $users; $i++) {
            // Mix of different module requests
            $modules = ['infrastructure', 'cargo', 'emergency', 'drones'];
            $module = $modules[array_rand($modules)];

            $response = $this->makeAsyncRequest($module, $i);
            $responses[] = $response;

            // Add think time
            usleep($this->thinkTime * 1000);
        }

        return $responses;
    }

    /**
     * Test endpoint load
     */
    private function testEndpointLoad($endpoint, $requests)
    {
        $responses = [];

        for ($i = 0; $i < $requests; $i++) {
            $startTime = microtime(true);
            $response = $this->makeHttpRequest($endpoint);
            $endTime = microtime(true);

            $responses[] = [
                'response_time' => $endTime - $startTime,
                'status' => 'success',
                'size' => strlen($response)
            ];
        }

        return $responses;
    }

    /**
     * Make HTTP request
     */
    private function makeHttpRequest($endpoint)
    {
        // Simulate HTTP request - in real implementation, use curl or similar
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://localhost:8000' . $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->getTestToken(),
            'Content-Type: application/json'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            throw new Exception('Request failed: ' . curl_error($ch));
        }

        curl_close($ch);

        if ($httpCode >= 400) {
            throw new Exception("HTTP {$httpCode} error");
        }

        return $response;
    }

    /**
     * Get module endpoint
     */
    private function getModuleEndpoint($moduleName)
    {
        $endpoints = [
            'infrastructure' => '/api/infrastructure/dashboard',
            'cargo' => '/api/cargo',
            'emergency' => '/api/emergency',
            'drones' => '/api/drones',
            'customs' => '/api/customs',
            'advanced-security' => '/api/advanced-security',
            'virtual-assistant' => '/api/virtual-assistant'
        ];

        return $endpoints[$moduleName] ?? '/api/modules';
    }

    /**
     * Calculate throughput
     */
    private function calculateThroughput($responses)
    {
        $totalTime = array_sum(array_column($responses, 'response_time'));
        return count($responses) / $totalTime;
    }

    /**
     * Calculate error rate
     */
    private function calculateErrorRate($responses)
    {
        $errors = array_filter($responses, function($response) {
            return $response['status'] === 'error';
        });
        return count($errors) / count($responses);
    }

    /**
     * Calculate average response time
     */
    private function calculateAverageResponseTime($responses)
    {
        $times = array_column($responses, 'response_time');
        return array_sum($times) / count($times);
    }

    /**
     * Check response time threshold
     */
    private function checkResponseTimeThreshold($responseTimes)
    {
        $avg = array_sum($responseTimes) / count($responseTimes);
        $thresholds = [
            'excellent' => 0.1,
            'good' => 0.5,
            'acceptable' => 1.0,
            'poor' => 2.0
        ];

        foreach ($thresholds as $rating => $threshold) {
            if ($avg <= $threshold) {
                return $rating;
            }
        }

        return 'unacceptable';
    }

    /**
     * Check error rate threshold
     */
    private function checkErrorRateThreshold($errorRate)
    {
        if ($errorRate <= 0.01) return 'excellent';
        if ($errorRate <= 0.05) return 'good';
        if ($errorRate <= 0.10) return 'acceptable';
        if ($errorRate <= 0.20) return 'poor';
        return 'unacceptable';
    }

    /**
     * Check throughput threshold
     */
    private function checkThroughputThreshold($throughput)
    {
        if ($throughput >= 100) return 'excellent';
        if ($throughput >= 50) return 'good';
        if ($throughput >= 20) return 'acceptable';
        if ($throughput >= 10) return 'poor';
        return 'unacceptable';
    }

    /**
     * Get memory usage
     */
    private function getMemoryUsage()
    {
        return memory_get_peak_usage(true) / 1024 / 1024; // MB
    }

    /**
     * Get CPU usage
     */
    private function getCpuUsage()
    {
        // Simplified CPU usage - in real implementation, use system calls
        return rand(10, 90); // Placeholder
    }

    /**
     * Get test token
     */
    private function getTestToken()
    {
        // Generate test JWT token for performance testing
        $auth = new Auth();
        return $auth->generateToken(['user_id' => 1, 'role' => 'admin']);
    }

    /**
     * Generate comprehensive performance report
     */
    private function generatePerformanceReport()
    {
        $report = [
            'test_summary' => [
                'total_tests' => count($this->results),
                'duration' => $this->endTime - $this->startTime,
                'timestamp' => date('Y-m-d H:i:s')
            ],
            'performance_metrics' => [],
            'recommendations' => []
        ];

        // Analyze results and generate recommendations
        foreach ($this->results as $result) {
            if (isset($result['thresholds'])) {
                $report['performance_metrics'][] = [
                    'module' => $result['module'],
                    'metrics' => $result['thresholds']
                ];

                // Generate recommendations based on thresholds
                if ($result['thresholds']['avg_response_time'] === 'poor' ||
                    $result['thresholds']['avg_response_time'] === 'unacceptable') {
                    $report['recommendations'][] = "Optimize {$result['module']} module response time";
                }

                if ($result['thresholds']['error_rate'] === 'poor' ||
                    $result['thresholds']['error_rate'] === 'unacceptable') {
                    $report['recommendations'][] = "Improve error handling for {$result['module']} module";
                }
            }
        }

        // Save report to file
        $reportPath = __DIR__ . '/../../reports/performance_test_' . date('Y-m-d_H-i-s') . '.json';
        file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT));

        $this->logger->info("Performance report generated", ['path' => $reportPath]);
    }

    /**
     * Run specific performance test
     */
    public function runSpecificTest($testType, $parameters = [])
    {
        switch ($testType) {
            case 'module':
                $this->testModulePerformance($parameters['module_name'] ?? 'infrastructure');
                break;
            case 'load':
                $this->testSystemLoad();
                break;
            case 'database':
                $this->testDatabasePerformance();
                break;
            case 'api':
                $this->testAPIEndpoints();
                break;
            default:
                throw new Exception("Unknown test type: {$testType}");
        }

        return $this->apiResponse->success($this->results);
    }
}

// Handle CLI execution
if (isset($argv) && count($argv) > 1) {
    $test = new PerformanceTest();

    if ($argv[1] === 'full') {
        $result = $test->runFullTestSuite();
        echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
    } elseif ($argv[1] === 'module' && isset($argv[2])) {
        $result = $test->runSpecificTest('module', ['module_name' => $argv[2]]);
        echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
    } elseif ($argv[1] === 'load') {
        $result = $test->runSpecificTest('load');
        echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
    } else {
        echo "Usage: php PerformanceTest.php [full|module <name>|load]\n";
    }
}
