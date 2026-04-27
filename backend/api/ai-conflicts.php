<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once __DIR__ . '/../src/Logger.php';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        exit(0);
    }

    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../src/Middleware.php';
    require_once __DIR__ . '/../src/AIConflictPrediction.php';

    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? 'predict';

    switch ($method) {
        case 'GET':
            handleGet($action);
            break;
        case 'POST':
            handlePost($action);
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    Logger::error('AI Conflicts API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

function handleGet($action) {
    global $pdo;

    try {
        $ai = new AIConflictPrediction($pdo);

        switch ($action) {
            case 'predictions':
                Middleware::authenticate();
                Middleware::checkPermission('read', 'atc');

                $limit = (int)($_GET['limit'] ?? 20);
                $severity = (float)($_GET['min_severity'] ?? 0);

                // Get recent conflict predictions
                $stmt = $pdo->prepare("
                    SELECT * FROM conflict_predictions
                    WHERE severity >= ?
                    ORDER BY predicted_at DESC
                    LIMIT ?
                ");
                $stmt->execute([$severity, $limit]);
                $predictions = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode(['predictions' => $predictions]);
                break;

            case 'analytics':
                Middleware::authenticate();
                Middleware::checkPermission('read', 'analytics');

                // Get conflict analytics
                $stmt = $pdo->prepare("
                    SELECT
                        DATE(predicted_at) as date,
                        COUNT(*) as total_predictions,
                        AVG(severity) as avg_severity,
                        MAX(severity) as max_severity,
                        COUNT(CASE WHEN resolved = true THEN 1 END) as resolved_count
                    FROM conflict_predictions
                    WHERE predicted_at > NOW() - INTERVAL '30 days'
                    GROUP BY DATE(predicted_at)
                    ORDER BY date DESC
                ");
                $stmt->execute();
                $analytics = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode(['analytics' => $analytics]);
                break;

            default:
                http_response_code(400);
                echo json_encode(['error' => 'Invalid action']);
                break;
        }
    } catch (Exception $e) {
        Logger::error('AI Conflicts GET error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to retrieve conflict data']);
    }
}

function handlePost($action) {
    global $pdo;

    try {
        $ai = new AIConflictPrediction($pdo);
        $input = json_decode(file_get_contents('php://input'), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON']);
            return;
        }

        switch ($action) {
            case 'predict':
                Middleware::authenticate();
                Middleware::checkPermission('read', 'atc');

                Middleware::validateInput($input, ['aircraft_data']);

                $aircraftData = $input['aircraft_data'];
                $timeHorizon = (int)($input['time_horizon'] ?? 600);

                $predictions = $ai->predictConflicts($aircraftData, $timeHorizon);

                // Store predictions
                foreach ($predictions as $prediction) {
                    $ai->storeConflictPrediction($prediction);
                }

                echo json_encode([
                    'predictions' => $predictions,
                    'total_predicted' => count($predictions)
                ]);
                break;

            case 'resolve':
                Middleware::authenticate();
                Middleware::checkPermission('write', 'atc');

                Middleware::validateInput($input, ['prediction_id', 'resolution_method']);

                $predictionId = $input['prediction_id'];
                $resolutionMethod = $input['resolution_method'];

                // Mark prediction as resolved
                $stmt = $pdo->prepare("
                    UPDATE conflict_predictions
                    SET resolved = true
                    WHERE id = ?
                ");
                $stmt->execute([$predictionId]);

                // Store in conflict history
                $stmt = $pdo->prepare("
                    INSERT INTO conflict_history (
                        aircraft1, aircraft2, resolution_method, detected_at
                    )
                    SELECT aircraft1, aircraft2, ?, predicted_at
                    FROM conflict_predictions
                    WHERE id = ?
                ");
                $stmt->execute([$resolutionMethod, $predictionId]);

                Logger::info('Conflict resolved: ID ' . $predictionId . ' method: ' . $resolutionMethod);
                echo json_encode(['message' => 'Conflict resolution recorded']);
                break;

            case 'learn':
                Middleware::authenticate();
                Middleware::checkPermission('admin', 'atc');

                $ai->learnFromHistoricalConflicts();

                echo json_encode(['message' => 'AI model updated from historical data']);
                break;

            default:
                http_response_code(400);
                echo json_encode(['error' => 'Invalid action']);
                break;
        }
    } catch (Exception $e) {
        Logger::error('AI Conflicts POST error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to process AI conflict request']);
    }
}
?>
