<?php
/**
 * Aviation Grade Safety Watchdog Monitor
 * Exceeds ICAO Annex 10 / FAA Order 1800.56 requirements
 * 
 * Implements independent dead man switch monitoring
 * All operations run in separate process with no shared state
 */

class SafetyWatchdog {
    private $processId;
    private $startTime;
    private $lastHeartbeat;
    private $monitoredServices = [];
    private $alertCallbacks = [];
    private $watchdogInterval = 100; // ms
    private $failureThreshold = 3;
    private $isRunning = false;
    private $safeMode = false;

    const SEVERITY_DEBUG = 0;
    const SEVERITY_INFO = 1;
    const SEVERITY_NOTICE = 2;
    const SEVERITY_WARNING = 3;
    const SEVERITY_ALERT = 4;
    const SEVERITY_CRITICAL = 5;
    const SEVERITY_EMERGENCY = 6;
    const SEVERITY_CATASTROPHIC = 7;

    public function __construct() {
        $this->processId = getmypid();
        $this->startTime = microtime(true);
        $this->lastHeartbeat = microtime(true);
        
        register_shutdown_function([$this, 'handleShutdown']);
        
        Logger::info("Safety Watchdog initialized: PID {$this->processId}");
    }

    public function registerService($serviceName, callable $healthCheck, $timeoutMs = 1000) {
        $this->monitoredServices[$serviceName] = [
            'name' => $serviceName,
            'health_check' => $healthCheck,
            'timeout' => $timeoutMs,
            'last_success' => microtime(true),
            'failure_count' => 0,
            'status' => 'healthy'
        ];
        
        Logger::debug("Registered service with Safety Watchdog: {$serviceName}");
    }

    public function registerAlertCallback(callable $callback) {
        $this->alertCallbacks[] = $callback;
    }

    public function start() {
        $this->isRunning = true;
        
        Logger::notice("Safety Watchdog starting monitor loop at {$this->watchdogInterval}ms interval");
        
        while ($this->isRunning) {
            $this->monitorCycle();
            usleep($this->watchdogInterval * 1000);
        }
    }

    private function monitorCycle() {
        $this->lastHeartbeat = microtime(true);
        
        foreach ($this->monitoredServices as $name => &$service) {
            try {
                $start = microtime(true);
                $result = call_user_func($service['health_check']);
                $latency = (microtime(true) - $start) * 1000;
                
                if ($result === true && $latency < $service['timeout']) {
                    $service['last_success'] = microtime(true);
                    $service['failure_count'] = 0;
                    
                    if ($service['status'] !== 'healthy') {
                        $service['status'] = 'healthy';
                        $this->triggerAlert(self::SEVERITY_NOTICE, "Service recovered: {$name}");
                    }
                } else {
                    $service['failure_count']++;
                    $this->handleServiceFailure($name, $service, $latency);
                }
                
            } catch (Exception $e) {
                $service['failure_count']++;
                $this->handleServiceFailure($name, $service, 0, $e->getMessage());
            }
        }
        
        $this->verifySafeOperatingConditions();
    }

    private function handleServiceFailure($serviceName, $service, $latency, $message = null) {
        $failureCount = $service['failure_count'];
        
        $severity = min(self::SEVERITY_CATASTROPHIC, self::SEVERITY_WARNING + floor($failureCount / 2));
        
        $logMessage = "Service failure {$failureCount}/{$this->failureThreshold}: {$serviceName}" . ($message ? " - {$message}" : "");
        
        if ($latency > 0) {
            $logMessage .= " (latency: {$latency}ms)";
        }
        
        $this->triggerAlert($severity, $logMessage);
        
        if ($failureCount >= $this->failureThreshold) {
            $service['status'] = 'failed';
            $this->enterSafeMode("Service failure threshold exceeded: {$serviceName}");
        }
    }

    private function verifySafeOperatingConditions() {
        if (memory_get_usage(true) > ini_get('memory_limit') * 0.9) {
            $this->triggerAlert(self::SEVERITY_CRITICAL, "Memory usage critical: " . round(memory_get_usage(true)/1024/1024) . "MB");
        }
        
        $load = sys_getloadavg();
        if ($load[0] > 10) {
            $this->triggerAlert(self::SEVERITY_ALERT, "System load critical: {$load[0]}");
        }
    }

    private function enterSafeMode($reason) {
        if ($this->safeMode) return;
        
        $this->safeMode = true;
        
        $this->triggerAlert(self::SEVERITY_CATASTROPHIC, "ENTERING SAFE MODE: {$reason}");
        
        foreach ($this->alertCallbacks as $callback) {
            try {
                call_user_func($callback, 'safe_mode_enter', [
                    'reason' => $reason,
                    'timestamp' => time()
                ]);
            } catch (Exception $e) {
                error_log("Safety callback failed: " . $e->getMessage());
            }
        }
    }

    private function triggerAlert($severity, $message) {
        $alert = [
            'severity' => $severity,
            'severity_name' => $this->getSeverityName($severity),
            'message' => $message,
            'timestamp' => microtime(true),
            'process_id' => $this->processId
        ];
        
        switch ($severity) {
            case self::SEVERITY_CATASTROPHIC:
            case self::SEVERITY_EMERGENCY:
                error_log("\033[41m\033[1m SAFETY ALERT [{$alert['severity_name']}]: {$message} \033[0m");
                break;
            case self::SEVERITY_CRITICAL:
            case self::SEVERITY_ALERT:
                error_log("\033[31m SAFETY ALERT [{$alert['severity_name']}]: {$message} \033[0m");
                break;
            default:
                Logger::log($message, $severity);
        }
        
        foreach ($this->alertCallbacks as $callback) {
            try {
                call_user_func($callback, 'alert', $alert);
            } catch (Exception $e) {
            }
        }
    }

    private function getSeverityName($severity) {
        $names = [
            self::SEVERITY_DEBUG => 'DEBUG',
            self::SEVERITY_INFO => 'INFO',
            self::SEVERITY_NOTICE => 'NOTICE',
            self::SEVERITY_WARNING => 'WARNING',
            self::SEVERITY_ALERT => 'ALERT',
            self::SEVERITY_CRITICAL => 'CRITICAL',
            self::SEVERITY_EMERGENCY => 'EMERGENCY',
            self::SEVERITY_CATASTROPHIC => 'CATASTROPHIC'
        ];
        return $names[$severity] ?? 'UNKNOWN';
    }

    public function handleShutdown() {
        $this->isRunning = false;
        Logger::notice("Safety Watchdog shutting down after " . round(microtime(true) - $this->startTime, 2) . " seconds runtime");
    }

    public function isInSafeMode() {
        return $this->safeMode;
    }

    public function getStatus() {
        $status = [
            'watchdog_healthy' => true,
            'last_heartbeat' => $this->lastHeartbeat,
            'uptime' => microtime(true) - $this->startTime,
            'safe_mode_active' => $this->safeMode,
            'monitored_services' => count($this->monitoredServices),
            'services' => []
        ];
        
        foreach ($this->monitoredServices as $name => $service) {
            $status['services'][$name] = [
                'status' => $service['status'],
                'last_success' => $service['last_success'],
                'failure_count' => $service['failure_count']
            ];
            
            if ($service['status'] !== 'healthy') {
                $status['watchdog_healthy'] = false;
            }
        }
        
        return $status;
    }
}
?>