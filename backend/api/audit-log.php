<?php
/**
 * TPT Flight Control System
 * Audit Log API Endpoint
 * 
 * Provides administrator interface for immutable audit trail searching, filtering and export
 * All operations are read-only, audit log cannot be modified or deleted
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/RBAC.php';
require_once __DIR__ . '/../src/Validator.php';
require_once __DIR__ . '/../src/Logger.php';

use TPT\FlightControl\Config\Database;
use TPT\FlightControl\Logger;
use TPT\FlightControl\RBAC;

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Only users with AUDIT_VIEW permission can access this endpoint
if (!Auth::isAuthenticated() || !RBAC::hasPermission(Auth::currentUserId(), 'audit_log_view')) {
    http_response_code(403);
    echo json_encode(['error' => 'Insufficient permissions']);
    exit;
}

$validator = new Validator($_GET);

$validator->optional('page')->integer()->min(1)->default(1);
$validator->optional('limit')->integer()->min(1)->max(1000)->default(50);
$validator->optional('user_id')->integer();
$validator->optional('action')->string()->max(50);
$validator->optional('resource_type')->string()->max(50);
$validator->optional('resource_id')->integer();
$validator->optional('start_date')->dateTime();
$validator->optional('end_date')->dateTime();
$validator->optional('search')->string()->max(100);
$validator->optional('export')->oneOf(['csv', 'json']);

if (!$validator->validateFields()) {
    http_response_code(400);
    echo json_encode(['errors' => $validator->getErrors()]);
    exit;
}

$params = $validator->getValidatedData();

$db = Database::getConnection();

// Base query - audit log is append only, no updates or deletes permitted
$query = "SELECT 
            id, 
            user_id, 
            username,
            action, 
            resource_type, 
            resource_id, 
            description, 
            ip_address, 
            user_agent, 
            created_at,
            cryptographic_hash
          FROM audit_log 
          WHERE 1=1";

$bindParams = [];

if (isset($params['user_id'])) {
    $query .= " AND user_id = :user_id";
    $bindParams['user_id'] = $params['user_id'];
}

if (isset($params['action'])) {
    $query .= " AND action = :action";
    $bindParams['action'] = $params['action'];
}

if (isset($params['resource_type'])) {
    $query .= " AND resource_type = :resource_type";
    $bindParams['resource_type'] = $params['resource_type'];
}

if (isset($params['start_date'])) {
    $query .= " AND created_at >= :start_date";
    $bindParams['start_date'] = $params['start_date'];
}

if (isset($params['end_date'])) {
    $query .= " AND created_at <= :end_date";
    $bindParams['end_date'] = $params['end_date'];
}

if (isset($params['search'])) {
    $query .= " AND (description ILIKE :search OR username ILIKE :search)";
    $bindParams['search'] = '%' . $params['search'] . '%';
}

$query .= " ORDER BY created_at DESC";

// Count total records
$countStmt = $db->prepare($query);
$countStmt->execute($bindParams);
$totalRecords = $countStmt->rowCount();

// Apply pagination
$offset = ($params['page'] - 1) * $params['limit'];
$query .= " LIMIT :limit OFFSET :offset";
$bindParams['limit'] = $params['limit'];
$bindParams['offset'] = $offset;

$stmt = $db->prepare($query);
$stmt->execute($bindParams);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle export requests
if (isset($params['export'])) {
    // Log export action for audit trail
    Logger::auditLog(Auth::currentUserId(), 'audit_log_export', 'audit_log', null, "Exported {$totalRecords} audit log entries");
    
    if ($params['export'] === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="audit-log-' . date('Y-m-d-His') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, array_keys($logs[0]));
        foreach ($logs as $log) {
            fputcsv($output, $log);
        }
        fclose($output);
        exit;
    }
    
    if ($params['export'] === 'json') {
        header('Content-Disposition: attachment; filename="audit-log-' . date('Y-m-d-His') . '.json"');
    }
}

echo json_encode([
    'data' => $logs,
    'pagination' => [
        'page' => $params['page'],
        'limit' => $params['limit'],
        'total' => $totalRecords,
        'pages' => (int)ceil($totalRecords / $params['limit'])
    ]
]);