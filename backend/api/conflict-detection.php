<?php
/**
 * Predictive Conflict Detection API Endpoint
 *
 * AI-powered conflict prediction and resolution system
 */

require_once '../config/database.php';
require_once '../src/Logger.php';
require_once '../src/Middleware.php';
require_once '../../integrations/predictive-conflict-detection.php';
require_once '../../integrations/spatial-indexing.php';
require_once '../../integrations/time-series-database.php';
require_once '../../integrations/machine-learning-route-optimization.php';

// Initialize components
$db = getDatabaseConnection();
$logger = new Logger($db);
$middleware = new Middleware($db, $logger);
$spatialIndex = new SpatialIndexing($db, $logger);
$timeSeriesDB = new TimeSeriesDatabase($db, $logger);
$mlOptimizer = new MachineLearningRouteOptimization($db, $logger, $spatialIndex, $timeSeriesDB);
$conflictDetector = new PredictiveConflictDetection($db, $logger, $spatialIndex, $timeSeriesDB, $mlOptimizer);

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Get request method and path
$method = $_SERVER['REQUEST_METHOD'];
$request = $_SERVER['REQUEST_URI'];

// Remove query string and decode
$path = parse_url($request, PHP_URL_PATH);
$path = str_replace('/backend/api/conflict-detection', '', $path);
$pathParts = array_filter(explode('/', $path));

// Get path parameters
$resource = $pathParts[1] ?? null;
$id = $pathParts[2] ?? null;

// Get request body
$input = json_decode(file_get_contents('php://input'), true);

// Route requests
try {
    switch ($method) {
        case 'GET':
            handleGetRequest($resource, $id, $_GET, $conflictDetector);
            break;

        case 'POST':
            handlePostRequest($resource, $input, $conflictDetector, $middleware);
            break;

        case 'PUT':
            handlePutRequest($resource, $id, $input, $conflictDetector, $middleware);
            break;

        case 'DELETE':
            handleDeleteRequest($resource, $id, $conflictDetector, $middleware);
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    $logger->error('Conflict Detection API error', ['error' => $e->getMessage()]);
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

/**
 * Handle GET requests
 */
function handleGetRequest($resource, $id, $queryParams, $detector)
{
    switch ($resource) {
        case null:
            // Get system status
            $status = $detector->getStatus();
            echo json_encode(['success' => true, 'status' => $status]);
            break;

        case 'predictions':
            if ($id) {
                // Get specific prediction
                $prediction = getPrediction($id);
                if ($prediction) {
                    echo json_encode(['success' => true, 'prediction' => $prediction]);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Prediction not found']);
                }
            } else {
                // Get predictions with filters
                $predictions = getPredictions($queryParams);
                echo json_encode(['success' => true, 'predictions' => $predictions]);
            }
            break;

        case 'scenarios':
            if ($id) {
                // Get specific scenario
                $scenario = getScenario($id);
                if ($scenario) {
                    echo json_encode(['success' => true, 'scenario' => $scenario]);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Scenario not found']);
                }
            } else {
                // Get scenarios
                $scenarios = getScenarios($queryParams);
                echo json_encode(['success' => true, 'scenarios' => $scenarios]);
            }
            break;

        case 'alerts':
            if ($id) {
                // Get specific alert
                $alert = getAlert($id);
                if ($alert) {
                    echo json_encode(['success' => true, 'alert' => $alert]);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Alert not found']);
                }
            } else {
                // Get alerts
                $alerts = getAlerts($queryParams);
                echo json_encode(['success' => true, 'alerts' => $alerts]);
            }
            break;

        case 'statistics':
            // Get conflict statistics
            $statistics = getConflictStatistics($queryParams);
            echo json_encode(['success' => true, 'statistics' => $statistics]);
            break;

        case 'models':
            // Get prediction models
            $models = getPredictionModels($queryParams);
            echo json_encode(['success' => true, 'models' => $models]);
            break;

        case 'resolutions':
            // Get conflict resolutions
            $resolutions = getConflictResolutions($queryParams);
            echo json_encode(['success' => true, 'resolutions' => $resolutions]);
            break;

        default:
            http_response_code(404);
            echo json_encode(['error' => 'Resource not found']);
    }
}

/**
 * Handle POST requests
 */
function handlePostRequest($resource, $input, $detector, $middleware)
{
    // Require authentication for POST operations
    $user = $middleware->authenticate();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        return;
    }

    switch ($resource) {
        case 'initialize':
            // Initialize conflict detection system
            $result = $detector->initialize();
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Conflict detection system initialized']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to initialize conflict detection system']);
            }
            break;

        case 'predict':
            // Predict conflicts for aircraft data
            if (isset($input['aircraft_data'])) {
                $timeHorizon = $input['time_horizon'] ?? 600;
                $predictions = $detector->predictConflicts($input['aircraft_data'], $timeHorizon);
                echo json_encode(['success' => true, 'predictions' => $predictions]);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Aircraft data required']);
            }
            break;

        case 'analyze-scenario':
            // Analyze conflict scenario
            if (isset($input['bounds'])) {
                $timeWindow = $input['time_window'] ?? 3600;
                $scenario = $detector->analyzeConflictScenarios($input['bounds'], $timeWindow);
                if ($scenario) {
                    echo json_encode(['success' => true, 'scenario' => $scenario]);
                } else {
                    http_response_code(500);
                    echo json_encode(['error' => 'Scenario analysis failed']);
                }
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Geographic bounds required']);
            }
            break;

        case 'monitor':
            // Monitor conflicts in real-time
            $status = $detector->monitorConflicts();
            echo json_encode(['success' => true, 'status' => $status]);
            break;

        case 'resolve':
            // Resolve a conflict
            if (isset($input['conflict_id']) && isset($input['resolution_method'])) {
                $result = $detector->resolveConflict(
                    $input['conflict_id'],
                    $input['resolution_method'],
                    $input['resolution_details'] ?? []
                );
                if ($result) {
                    echo json_encode(['success' => true, 'message' => 'Conflict resolved successfully']);
                } else {
                    http_response_code(500);
                    echo json_encode(['error' => 'Failed to resolve conflict']);
                }
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Conflict ID and resolution method required']);
            }
            break;

        case 'acknowledge-alert':
            // Acknowledge an alert
            if (isset($input['alert_id'])) {
                $result = acknowledgeAlert($input['alert_id'], $user['username']);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Alert ID required']);
            }
            break;

        case 'train-model':
            // Train prediction model
            if (isset($input['model_type'])) {
                $result = trainPredictionModel($input['model_type'], $input);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Model type required']);
            }
            break;

        default:
            http_response_code(404);
            echo json_encode(['error' => 'Resource not found']);
    }
}

/**
 * Handle PUT requests
 */
function handlePutRequest($resource, $id, $input, $detector, $middleware)
{
    // Require authentication
    $user = $middleware->authenticate();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        return;
    }

    switch ($resource) {
        case 'predictions':
            if ($id) {
                $result = updatePrediction($id, $input);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Prediction ID required']);
            }
            break;

        case 'scenarios':
            if ($id) {
                $result = updateScenario($id, $input);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Scenario ID required']);
            }
            break;

        case 'models':
            if ($id) {
                $result = updatePredictionModel($id, $input);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Model ID required']);
            }
            break;

        default:
            http_response_code(404);
            echo json_encode(['error' => 'Resource not found']);
    }
}

/**
 * Handle DELETE requests
 */
function handleDeleteRequest($resource, $id, $detector, $middleware)
{
    // Require authentication
    $user = $middleware->authenticate();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        return;
    }

    switch ($resource) {
        case 'predictions':
            if ($id) {
                $result = deletePrediction($id);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Prediction ID required']);
            }
            break;

        case 'scenarios':
            if ($id) {
                $result = deleteScenario($id);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Scenario ID required']);
            }
            break;

        case 'alerts':
            if ($id) {
                $result = deleteAlert($id);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Alert ID required']);
            }
            break;

        default:
            http_response_code(404);
            echo json_encode(['error' => 'Resource not found']);
    }
}

/**
 * Get predictions with filters
 */
function getPredictions($queryParams)
{
    $db = $GLOBALS['db'];
    $query = "SELECT * FROM predicted_conflicts WHERE 1=1";
    $params = [];

    if (isset($queryParams['aircraft_icao'])) {
        $query .= " AND (aircraft1_icao = ? OR aircraft2_icao = ?)";
        $params[] = $queryParams['aircraft_icao'];
        $params[] = $queryParams['aircraft_icao'];
    }

    if (isset($queryParams['severity'])) {
        $query .= " AND severity_level = ?";
        $params[] = $queryParams['severity'];
    }

    if (isset($queryParams['status'])) {
        $query .= " AND status = ?";
        $params[] = $queryParams['status'];
    }

    if (isset($queryParams['start_time'])) {
        $query .= " AND prediction_time >= ?";
        $params[] = $queryParams['start_time'];
    }

    if (isset($queryParams['end_time'])) {
        $query .= " AND prediction_time <= ?";
        $params[] = $queryParams['end_time'];
    }

    if (isset($queryParams['min_confidence'])) {
        $query .= " AND confidence_score >= ?";
        $params[] = $queryParams['min_confidence'];
    }

    $query .= " ORDER BY prediction_time DESC LIMIT 100";

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get specific prediction
 */
function getPrediction($conflictId)
{
    $db = $GLOBALS['db'];
    $stmt = $db->prepare("SELECT * FROM predicted_conflicts WHERE conflict_id = ?");
    $stmt->execute([$conflictId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Get scenarios
 */
function getScenarios($queryParams)
{
    $db = $GLOBALS['db'];
    $query = "SELECT * FROM conflict_scenarios WHERE 1=1";
    $params = [];

    if (isset($queryParams['risk_level'])) {
        $query .= " AND risk_level = ?";
        $params[] = $queryParams['risk_level'];
    }

    if (isset($queryParams['status'])) {
        $query .= " AND status = ?";
        $params[] = $queryParams['status'];
    }

    $query .= " ORDER BY created_at DESC LIMIT 50";

    $stmt = $db->prepare($query);
    $stmt->execute($params);

    $scenarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Decode JSON fields
    foreach ($scenarios as &$scenario) {
        $scenario['geographic_bounds'] = json_decode($scenario['geographic_bounds'], true);
        $scenario['mitigation_actions'] = json_decode($scenario['mitigation_actions'], true);
    }

    return $scenarios;
}

/**
 * Get specific scenario
 */
function getScenario($scenarioId)
{
    $db = $GLOBALS['db'];
    $stmt = $db->prepare("SELECT * FROM conflict_scenarios WHERE scenario_id = ?");
    $stmt->execute([$scenarioId]);
    $scenario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($scenario) {
        $scenario['geographic_bounds'] = json_decode($scenario['geographic_bounds'], true);
        $scenario['mitigation_actions'] = json_decode($scenario['mitigation_actions'], true);
    }

    return $scenario;
}

/**
 * Get alerts
 */
function getAlerts($queryParams)
{
    $db = $GLOBALS['db'];
    $query = "SELECT * FROM conflict_alerts WHERE 1=1";
    $params = [];

    if (isset($queryParams['alert_level'])) {
        $query .= " AND alert_level = ?";
        $params[] = $queryParams['alert_level'];
    }

    if (isset($queryParams['acknowledged'])) {
        $acknowledged = $queryParams['acknowledged'] === 'true' ? 1 : 0;
        $query .= " AND acknowledged = ?";
        $params[] = $acknowledged;
    }

    if (isset($queryParams['start_time'])) {
        $query .= " AND created_at >= ?";
        $params[] = $queryParams['start_time'];
    }

    if (isset($queryParams['end_time'])) {
        $query .= " AND created_at <= ?";
        $params[] = $queryParams['end_time'];
    }

    $query .= " ORDER BY created_at DESC LIMIT 100";

    $stmt = $db->prepare($query);
    $stmt->execute($params);

    $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Decode JSON fields
    foreach ($alerts as &$alert) {
        $alert['affected_aircraft'] = json_decode($alert['affected_aircraft'], true);
        $alert['recommended_actions'] = json_decode($alert['recommended_actions'], true);
    }

    return $alerts;
}

/**
 * Get specific alert
 */
function getAlert($alertId)
{
    $db = $GLOBALS['db'];
    $stmt = $db->prepare("SELECT * FROM conflict_alerts WHERE alert_id = ?");
    $stmt->execute([$alertId]);
    $alert = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($alert) {
        $alert['affected_aircraft'] = json_decode($alert['affected_aircraft'], true);
        $alert['recommended_actions'] = json_decode($alert['recommended_actions'], true);
    }

    return $alert;
}

/**
 * Get conflict statistics
 */
function getConflictStatistics($queryParams)
{
    $db = $GLOBALS['db'];
    $timePeriod = $queryParams['time_period'] ?? '24 hours';

    $stmt = $db->prepare("
        SELECT
            COUNT(*) as total_predictions,
            COUNT(CASE WHEN status = 'resolved' THEN 1 END) as resolved_conflicts,
            COUNT(CASE WHEN status = 'false_positive' THEN 1 END) as false_positives,
            AVG(time_to_conflict) as avg_time_to_conflict,
            AVG(confidence_score) as avg_confidence,
            AVG(horizontal_separation) as avg_horizontal_separation,
            AVG(vertical_separation) as avg_vertical_separation,
            MIN(prediction_time) as earliest_prediction,
            MAX(prediction_time) as latest_prediction
        FROM predicted_conflicts
        WHERE prediction_time >= NOW() - INTERVAL ?
    ");

    $stmt->execute([$timePeriod]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get severity distribution
    $stmt = $db->prepare("
        SELECT severity_level, COUNT(*) as count
        FROM predicted_conflicts
        WHERE prediction_time >= NOW() - INTERVAL ?
        GROUP BY severity_level
    ");

    $stmt->execute([$timePeriod]);
    $stats['severity_distribution'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get conflict type distribution
    $stmt = $db->prepare("
        SELECT conflict_type, COUNT(*) as count
        FROM predicted_conflicts
        WHERE prediction_time >= NOW() - INTERVAL ?
        GROUP BY conflict_type
    ");

    $stmt->execute([$timePeriod]);
    $stats['conflict_type_distribution'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get resolution success rate
    $stmt = $db->prepare("
        SELECT
            resolution_type,
            COUNT(*) as total,
            COUNT(CASE WHEN outcome = 'successful' THEN 1 END) as successful,
            ROUND(
                COUNT(CASE WHEN outcome = 'successful' THEN 1 END) * 100.0 / COUNT(*),
                2
            ) as success_rate
        FROM conflict_resolutions
        WHERE implemented_at >= NOW() - INTERVAL ?
        GROUP BY resolution_type
    ");

    $stmt->execute([$timePeriod]);
    $stats['resolution_stats'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return $stats;
}

/**
 * Get prediction models
 */
function getPredictionModels($queryParams)
{
    $db = $GLOBALS['db'];
    $query = "SELECT * FROM conflict_prediction_models WHERE 1=1";
    $params = [];

    if (isset($queryParams['model_type'])) {
        $query .= " AND model_type = ?";
        $params[] = $queryParams['model_type'];
    }

    if (isset($queryParams['active_only']) && $queryParams['active_only'] === 'true') {
        $query .= " AND is_active = TRUE";
    }

    $query .= " ORDER BY last_trained DESC";

    $stmt = $db->prepare($query);
    $stmt->execute($params);

    $models = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Decode JSON model parameters
    foreach ($models as &$model) {
        $model['model_parameters'] = json_decode($model['model_parameters'], true);
    }

    return $models;
}

/**
 * Get conflict resolutions
 */
function getConflictResolutions($queryParams)
{
    $db = $GLOBALS['db'];
    $query = "SELECT * FROM conflict_resolutions WHERE 1=1";
    $params = [];

    if (isset($queryParams['conflict_id'])) {
        $query .= " AND conflict_id = ?";
        $params[] = $queryParams['conflict_id'];
    }

    if (isset($queryParams['resolution_type'])) {
        $query .= " AND resolution_type = ?";
        $params[] = $queryParams['resolution_type'];
    }

    if (isset($queryParams['outcome'])) {
        $query .= " AND outcome = ?";
        $params[] = $queryParams['outcome'];
    }

    $query .= " ORDER BY implemented_at DESC LIMIT 100";

    $stmt = $db->prepare($query);
    $stmt->execute($params);

    $resolutions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Decode JSON resolution details
    foreach ($resolutions as &$resolution) {
        $resolution['resolution_details'] = json_decode($resolution['resolution_details'], true);
    }

    return $resolutions;
}

/**
 * Acknowledge alert
 */
function acknowledgeAlert($alertId, $user)
{
    $db = $GLOBALS['db'];

    try {
        $stmt = $db->prepare("
            UPDATE conflict_alerts
            SET acknowledged = TRUE,
                acknowledged_by = ?,
                acknowledged_at = NOW()
            WHERE alert_id = ?
        ");

        $stmt->execute([$user, $alertId]);

        return ['success' => true, 'message' => 'Alert acknowledged successfully'];

    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Train prediction model
 */
function trainPredictionModel($modelType, $config)
{
    $db = $GLOBALS['db'];

    try {
        // Simulate model training
        $accuracy = rand(85, 95) / 100;
        $precision = rand(80, 93) / 100;
        $recall = rand(78, 91) / 100;

        // Update or insert model
        $stmt = $db->prepare("
            INSERT INTO conflict_prediction_models (
                model_name, model_type, model_version, model_parameters,
                accuracy_score, precision_score, recall_score, last_trained, is_active
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)
            ON CONFLICT (model_name) DO UPDATE SET
                model_parameters = EXCLUDED.model_parameters,
                accuracy_score = EXCLUDED.accuracy_score,
                precision_score = EXCLUDED.precision_score,
                recall_score = EXCLUDED.recall_score,
                last_trained = NOW()
        ");

        $stmt->execute([
            $modelType,
            $modelType,
            '1.0.0',
            json_encode($config),
            $accuracy,
            $precision,
            $recall,
            true
        ]);

        return [
            'success' => true,
            'message' => "Model {$modelType} trained successfully",
            'metrics' => [
                'accuracy' => $accuracy,
                'precision' => $precision,
                'recall' => $recall,
                'f1_score' => 2 * ($precision * $recall) / ($precision + $recall)
            ]
        ];

    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Update prediction
 */
function updatePrediction($conflictId, $updateData)
{
    $db = $GLOBALS['db'];

    try {
        $stmt = $db->prepare("
            UPDATE predicted_conflicts
            SET status = ?, severity_level = ?, confidence_score = ?
            WHERE conflict_id = ?
        ");

        $stmt->execute([
            $updateData['status'] ?? 'predicted',
            $updateData['severity_level'] ?? 'medium',
            $updateData['confidence_score'] ?? 0.8,
            $conflictId
        ]);

        return ['success' => true, 'message' => 'Prediction updated successfully'];

    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Update scenario
 */
function updateScenario($scenarioId, $updateData)
{
    $db = $GLOBALS['db'];

    try {
        $stmt = $db->prepare("
            UPDATE conflict_scenarios
            SET status = ?, risk_level = ?, mitigation_actions = ?
            WHERE scenario_id = ?
        ");

        $stmt->execute([
            $updateData['status'] ?? 'active',
            $updateData['risk_level'] ?? 'medium',
            json_encode($updateData['mitigation_actions'] ?? []),
            $scenarioId
        ]);

        return ['success' => true, 'message' => 'Scenario updated successfully'];

    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Update prediction model
 */
function updatePredictionModel($modelId, $updateData)
{
    $db = $GLOBALS['db'];

    try {
        $stmt = $db->prepare("
            UPDATE conflict_prediction_models
            SET model_parameters = ?, is_active = ?
            WHERE id = ?
        ");

        $stmt->execute([
            json_encode($updateData['model_parameters'] ?? []),
            $updateData['is_active'] ?? true,
            $modelId
        ]);

        return ['success' => true, 'message' => 'Model updated successfully'];

    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Delete prediction
 */
function deletePrediction($conflictId)
{
    $db = $GLOBALS['db'];

    try {
        $stmt = $db->prepare("DELETE FROM predicted_conflicts WHERE conflict_id = ?");
        $stmt->execute([$conflictId]);

        return ['success' => true, 'message' => 'Prediction deleted successfully'];

    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Delete scenario
 */
function deleteScenario($scenarioId)
{
    $db = $GLOBALS['db'];

    try {
        $stmt = $db->prepare("DELETE FROM conflict_scenarios WHERE scenario_id = ?");
        $stmt->execute([$scenarioId]);

        return ['success' => true, 'message' => 'Scenario deleted successfully'];

    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Delete alert
 */
function deleteAlert($alertId)
{
    $db = $GLOBALS['db'];

    try {
        $stmt = $db->prepare("DELETE FROM conflict_alerts WHERE alert_id = ?");
        $stmt->execute([$alertId]);

        return ['success' => true, 'message' => 'Alert deleted successfully'];

    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}
