<?php
/**
 * Integration Manager
 * 
 * Manages all external integrations, provides auto-discovery,
 * lifecycle management, health monitoring, and unified access
 * 
 * @package TPT Flight Control
 * @version 1.0.0
 */

class IntegrationManager
{
    private $db;
    private $logger;
    private $integrations = [];
    private $instances = [];
    private $integrationPath;

    public function __construct($database, $logger)
    {
        $this->db = $database;
        $this->logger = $logger;
        $this->integrationPath = __DIR__ . '/../../integrations/';
        
        $this->discoverIntegrations();
    }

    /**
     * Auto-discover all integration classes
     */
    private function discoverIntegrations(): void
    {
        $files = glob($this->integrationPath . '*.php');
        
        foreach ($files as $file) {
            $className = basename($file, '.php');
            
            if (strpos($className, 'test-') === 0) {
                continue;
            }

            require_once $file;
            
            if (class_exists($className) && is_subclass_of($className, 'BaseIntegration')) {
                $this->integrations[$className] = [
                    'file' => $file,
                    'name' => $className::getName(),
                    'description' => $className::getDescription(),
                    'enabled' => true
                ];
            }
        }
    }

    /**
     * Get integration instance
     */
    public function get(string $className, array $config = [])
    {
        if (!isset($this->integrations[$className])) {
            throw new Exception("Integration not found: {$className}");
        }

        if (!isset($this->instances[$className])) {
            $this->instances[$className] = new $className($this->db, $this->logger, $config);
        }

        return $this->instances[$className];
    }

    /**
     * Get all registered integrations
     */
    public function getAllIntegrations(): array
    {
        return $this->integrations;
    }

    /**
     * Get health status for all integrations
     */
    public function getAllHealthStatus(): array
    {
        $status = [];

        foreach ($this->integrations as $className => $info) {
            try {
                $instance = $this->get($className);
                
                $status[$className] = [
                    'name' => $info['name'],
                    'description' => $info['description'],
                    'enabled' => $info['enabled'],
                    'health' => $instance->healthCheck(),
                    'metrics' => $instance->getMetrics(),
                    'circuit_breaker' => $instance->getCircuitBreakerStatus()
                ];

            } catch (Exception $e) {
                $status[$className] = [
                    'name' => $info['name'],
                    'enabled' => false,
                    'error' => $e->getMessage(),
                    'health' => false
                ];
            }
        }

        return $status;
    }

    /**
     * Get overall system health
     */
    public function getSystemHealth(): array
    {
        $allStatus = $this->getAllHealthStatus();
        
        $summary = [
            'total_integrations' => count($allStatus),
            'healthy' => 0,
            'degraded' => 0,
            'failed' => 0,
            'status' => 'healthy',
            'integrations' => $allStatus
        ];

        foreach ($allStatus as $status) {
            if ($status['enabled'] && $status['health']['status'] ?? false === 'healthy') {
                $summary['healthy']++;
            } elseif ($status['enabled']) {
                $summary['degraded']++;
                $summary['status'] = 'degraded';
            }
        }

        if ($summary['degraded'] > count($allStatus) / 2) {
            $summary['status'] = 'critical';
        }

        return $summary;
    }

    /**
     * Register new integration dynamically
     */
    public function registerIntegration(string $className, array $config = []): void
    {
        if (!class_exists($className) || !is_subclass_of($className, 'BaseIntegration')) {
            throw new Exception("Invalid integration class: {$className}");
        }

        $this->integrations[$className] = [
            'file' => (new ReflectionClass($className))->getFileName(),
            'name' => $className::getName(),
            'description' => $className::getDescription(),
            'enabled' => true,
            'config' => $config
        ];
    }

    /**
     * Enable/disable integration
     */
    public function setIntegrationEnabled(string $className, bool $enabled): void
    {
        if (isset($this->integrations[$className])) {
            $this->integrations[$className]['enabled'] = $enabled;
        }
    }
}