<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Middleware.php';

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $request = $_SERVER['REQUEST_URI'];

    // Remove query string and decode
    $path = parse_url($request, PHP_URL_PATH);
    $path = str_replace('/api/logs', '', $path);

    switch ($method) {
        case 'GET':
            getOperationalLogs($pdo);
            break;

        case 'POST':
            createOperationalLog($pdo);
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error', 'message' => $e->getMessage()]);
}

function getOperationalLogs($pdo) {
    // Check permissions - operations staff can view logs
    if (!Auth::hasPermission('read', 'flights')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 50);
    $offset = ($page - 1) * $limit;

    $level = $_GET['level'] ?? null; // INFO, WARNING, ERROR
    $category = $_GET['category'] ?? null; // flight, passenger, security, maintenance, etc.
    $userId = $_GET['user_id'] ?? null;
    $dateFrom = $_GET['date_from'] ?? null;
    $dateTo = $_GET['date_to'] ?? null;
    $search = $_GET['search'] ?? null;

    $where = [];
    $params = [];

    if ($level) {
        $where[] = "level = ?";
        $params[] = $level;
    }

    if ($category) {
        $where[] = "category = ?";
        $params[] = $category;
    }

    if ($userId) {
        $where[] = "user_id = ?";
        $params[] = $userId;
    }

    if ($dateFrom) {
        $where[] = "created_at >= ?";
        $params[] = $dateFrom;
    }

    if ($dateTo) {
        $where[] = "created_at <= ?";
        $params[] = $dateTo;
    }

    if ($search) {
        $where[] = "(message ILIKE ? OR details ILIKE ?)";
        $searchParam = '%' . $search . '%';
        $params = array_merge($params, [$searchParam, $searchParam]);
    }

    $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

    // Get operational logs
    $stmt = $pdo->prepare("
        SELECT
            ol.*,
            u.username,
            u.first_name,
            u.last_name
        FROM operational_logs ol
        LEFT JOIN users u ON ol.user_id = u.id
        {$whereClause}
        ORDER BY ol.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get total count
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM operational_logs ol
        {$whereClause}
    ");
    array_pop($params); // Remove limit
    array_pop($params); // Remove offset
    $countStmt->execute($params);
    $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

    echo json_encode([
        'logs' => $logs,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => ceil($total / $limit)
        ]
    ]);
}

function createOperationalLog($pdo) {
    // Check permissions - staff can create operational logs
    if (!Auth::hasPermission('write', 'flights')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON data']);
        return;
    }

    // Validate required fields
    $required = ['level', 'category', 'message'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Missing required field: $field"]);
            return;
        }
    }

    // Validate log level
    $validLevels = ['INFO', 'WARNING', 'ERROR', 'CRITICAL'];
    if (!in_array(strtoupper($data['level']), $validLevels)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid log level. Must be one of: ' . implode(', ', $validLevels)]);
        return;
    }

    // Get current user
    $currentUser = Auth::getCurrentUser();

    // Insert operational log
    $stmt = $pdo->prepare("
        INSERT INTO operational_logs (
            level, category, message, details, user_id, ip_address, user_agent
        ) VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        strtoupper($data['level']),
        $data['category'],
        $data['message'],
        $data['details'] ?? null,
        $currentUser ? $currentUser['id'] : null,
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);

    $logId = $pdo->lastInsertId();

    http_response_code(201);
    echo json_encode([
        'message' => 'Operational log created successfully',
        'log_id' => $logId
    ]);
}

// Helper function to log operational events
function logOperationalEvent($pdo, $level, $category, $message, $details = null, $userId = null) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO operational_logs (
                level, category, message, details, user_id, ip_address, user_agent
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            strtoupper($level),
            $category,
            $message,
            $details,
            $userId,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
        return true;
    } catch (Exception $e) {
        // Log to system log if database logging fails
        error_log("Failed to log operational event: " . $e->getMessage());
        return false;
    }
}

// Helper function to get log statistics
function getLogStatistics($pdo, $days = 7) {
    $stmt = $pdo->prepare("
        SELECT
            level,
            category,
            COUNT(*) as count
        FROM operational_logs
        WHERE created_at >= CURRENT_DATE - INTERVAL '{$days} days'
        GROUP BY level, category
        ORDER BY level, category
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Helper function to get recent critical logs
function getRecentCriticalLogs($pdo, $limit = 10) {
    $stmt = $pdo->prepare("
        SELECT
            ol.*,
            u.username,
            u.first_name,
            u.last_name
        FROM operational_logs ol
        LEFT JOIN users u ON ol.user_id = u.id
        WHERE ol.level IN ('ERROR', 'CRITICAL')
        ORDER BY ol.created_at DESC
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Helper function to clean old logs (for maintenance)
function cleanOldLogs($pdo, $daysToKeep = 90) {
    $stmt = $pdo->prepare("
        DELETE FROM operational_logs
        WHERE created_at < CURRENT_DATE - INTERVAL '{$daysToKeep} days'
    ");
    $stmt->execute();
    return $stmt->rowCount();
}
?>
