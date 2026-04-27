<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../src/Logger.php';

try {
    require_once __DIR__ . '/../config/database.php';

    // Check database connection
    $dbStatus = 'healthy';
    try {
        $stmt = $pdo->query('SELECT 1');
        $stmt->fetch();
    } catch (PDOException $e) {
        $dbStatus = 'unhealthy';
        Logger::error('Database health check failed: ' . $e->getMessage());
    }

    // Check file system permissions
    $logWritable = is_writable(__DIR__ . '/../logs');
    $backupDir = __DIR__ . '/../database/backups';
    $backupWritable = is_dir($backupDir) ? is_writable($backupDir) : (mkdir($backupDir, 0755, true) && is_writable($backupDir));

    // System info
    $systemInfo = [
        'php_version' => PHP_VERSION,
        'server_time' => date('Y-m-d H:i:s T'),
        'memory_usage' => memory_get_peak_usage(true),
        'uptime' => (function_exists('sys_getloadavg')) ? sys_getloadavg() : 'N/A'
    ];

    $status = ($dbStatus === 'healthy' && $logWritable && $backupWritable) ? 'healthy' : 'degraded';

    $response = [
        'status' => $status,
        'timestamp' => time(),
        'checks' => [
            'database' => $dbStatus,
            'logs_writable' => $logWritable,
            'backups_writable' => $backupWritable
        ],
        'system' => $systemInfo
    ];

    http_response_code($status === 'healthy' ? 200 : 503);
    echo json_encode($response);

    Logger::info('Health check performed: ' . $status);

} catch (Exception $e) {
    Logger::error('Health check error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Health check failed',
        'timestamp' => time()
    ]);
}
?>
