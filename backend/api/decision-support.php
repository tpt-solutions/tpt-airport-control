<?php
/**
 * Automated Decision Support API Endpoint
 *
 * AI-powered decision making and automated recommendations
 */

require_once '../config/database.php';
require_once '../src/Logger.php';
require_once '../src/Middleware.php';
require_once '../../integrations/automated-decision-support.php';
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
$decisionSupport = new AutomatedDecisionSupport($db, $logger, $spatialIndex, $timeSeriesDB, $conflictDetector, $mlOptimizer);

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
$path = str_replace('/backend/api/decision-support', '', $path);
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
            handleGetRequest($resource, $id, $_GET, $decisionSupport);
            break;

        case 'POST':
            handlePostRequest($resource, $input, $decisionSupport, $middleware);
            break;

        case 'PUT':
            handlePutRequest($resource, $id, $input, $decisionSupport, $middleware);
            break;

        case 'DELETE':
            handleDeleteRequest($resource, $id, $decisionSupport, $middleware);
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    $logger->error('Decision Support API error', ['error' => $e->getMessage()]);
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

/**
 * Handle GET requests
 */
function handleGetRequest($resource, $id, $queryParams, $decisionSupport)
{
    switch ($resource) {
        case null:
            // Get system status
            $status = $decisionSupport->getStatus();
            echo json_encode(['success' => true, 'status' => $status]);
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

        case 'recommendations':
            if ($id) {
                // Get specific recommendation
                $recommendation = getRecommendation($id);
                if ($recommendation) {
                    echo json_encode(['success' => true, 'recommendation' => $recommendation]);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Recommendation not found']);
                }
            } else {
                // Get recommendations
                $recommendations = getRecommendations($queryParams);
                echo json_encode(['success' => true, 'recommendations' => $recommendations]);
            }
            break;

        case 'alerts':
            if ($id) {
                // Get specific alert
                $alert = getDecisionAlert($id);
                if ($alert) {
                    echo json_encode(['success' => true, 'alert' => $alert]);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Alert not found']);
                }
            } else {
                // Get alerts
                $alerts = getDecisionAlerts($queryParams);
                echo json_encode(['success' => true, 'alerts' => $alerts]);
            }
            break;

        case 'rules':
            // Get decision rules
            $rules = getDecisionRules($queryParams);
            echo json_encode(['success' => true, 'rules' => $rules]);
            break;

        case 'performance':
            // Get model performance
            $performance = getDecisionModelPerformance($queryParams);
            echo json_encode(['success' => true, 'performance' => $performance]);
            break;

        case 'automated-actions':
            // Get automated actions
            $actions = getAutomatedActions($queryParams);
            echo json_encode(['success' => true, 'automated_actions' => $actions]);
            break;

        case 'outcomes':
            // Get decision outcomes
            $outcomes = getDecisionOutcomes($queryParams);
            echo json_encode(['success' => true, 'outcomes' => $outcomes]);
            break;

        default:
            http_response_code(404);
            echo json_encode(['error' => 'Resource not found']);
    }
}

/**
 * Handle POST requests
 */
function handlePostRequest($resource, $input, $decisionSupport, $middleware)
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
            // Initialize decision support system
            $result = $decisionSupport->initialize();
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Decision support system initialized']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to initialize decision support system']);
            }
            break;

        case 'analyze':
            // Analyze situation and provide decision support
            if (isset($input['situation_data'])) {
                $analysis = $decisionSupport->analyzeSituation($input['situation_data'], $input['context'] ?? []);
                if ($analysis) {
                    echo json_encode(['success' => true, 'analysis' => $analysis]);
                } else {
                    http_response_code(500);
                    echo json_encode(['error' => 'Situation analysis failed']);
                }
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Situation data required']);
            }
            break;

        case 'real-time-support':
            // Provide real-time decision support
            $support = $decisionSupport->provideRealTimeSupport(
                $input['current_state'] ?? [],
                $input['upcoming_events'] ?? []
            );
            if ($support) {
                echo json_encode(['success' => true, 'support' => $support]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Real-time support failed']);
            }
            break;

        case 'execute-automated':
            // Execute automated decision
            if (isset($input['decision_id'])) {
                $result = $decisionSupport->executeAutomatedDecision(
                    $input['decision_id'],
                    $input['parameters'] ?? []
                );
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Decision ID required']);
            }
            break;

        case 'learn-outcome':
            // Learn from decision outcome
            if (isset($input['decision_id']) && isset($input['actual_outcome'])) {
                $result = $decisionSupport->learnFromOutcome(
                    $input['decision_id'],
                    $input['actual_outcome'],
                    $input['feedback'] ?? []
                );
                if ($result) {
                    echo json_encode(['success' => true, 'message' => 'Learned from decision outcome']);
                } else {
                    http_response_code(500);
                    echo json_encode(['error' => 'Learning from outcome failed']);
                }
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Decision ID and actual outcome required']);
            }
            break;

        case 'acknowledge-alert':
            // Acknowledge decision alert
            if (isset($input['alert_id'])) {
                $result = acknowledgeDecisionAlert($input['alert_id'], $user['username']);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Alert ID required']);
            }
            break;

        case 'add-rule':
            // Add decision rule
            if (isset($input['rule_name']) && isset($input['conditions']) && isset($input['actions'])) {
                $result = addDecisionRule($input);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Rule name, conditions, and actions required']);
            }
            break;

        case 'evaluate-performance':
            // Evaluate model performance
            if (isset($input['model_name'])) {
                $result = evaluateDecisionModel($input['model_name'], $input);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Model name required']);
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
function handlePutRequest($resource, $id, $input, $decisionSupport, $middleware)
{
    // Require authentication
    $user = $middleware->authenticate();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        return;
    }

    switch ($resource) {
        case 'scenarios':
            if ($id) {
                $result = updateScenario($id, $input);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Scenario ID required']);
            }
            break;

        case 'recommendations':
            if ($id) {
                $result = updateRecommendation($id, $input);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Recommendation ID required']);
            }
            break;

        case 'rules':
            if ($id) {
                $result = updateDecisionRule($id, $input);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Rule ID required']);
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
function handleDeleteRequest($resource, $id, $decisionSupport, $middleware)
{
    // Require authentication
    $user = $middleware->authenticate();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        return;
    }

    switch ($resource) {
        case 'scenarios':
            if ($id) {
                $result = deleteScenario($id);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Scenario ID required']);
            }
            break;

        case 'recommendations':
            if ($id) {
                $result = deleteRecommendation($id);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Recommendation ID required']);
            }
            break;

        case 'rules':
            if ($id) {
                $result = deleteDecisionRule($id);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Rule ID required']);
            }
            break;

        case 'alerts':
            if ($id) {
                $result = deleteDecisionAlert($id);
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
 * Get scenarios
 */
function getScenarios($queryParams)
{
    $db = $GLOBALS['db'];
    $query = "SELECT * FROM decision_scenarios WHERE 1=1";
    $params = [];

    if (isset($queryParams['scenario_type'])) {
        $query .= " AND scenario_type = ?";
        $params[] = $queryParams['scenario_type'];
    }

    if (isset($queryParams['priority_level'])) {
        $query .= " AND priority_level = ?";
        $params[] = $queryParams['priority_level'];
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
        $scenario['affected_aircraft'] = json_decode($scenario['affected_aircraft'], true);
        $scenario['environmental_factors'] = json_decode($scenario['environmental_factors'], true);
        $scenario['operational_constraints'] = json_decode($scenario['operational_constraints'], true);
    }

    return $scenarios;
}

/**
 * Get specific scenario
 */
function getScenario($scenarioId)
{
    $db = $GLOBALS['db'];
    $stmt = $db->prepare("SELECT * FROM decision_scenarios WHERE scenario_id = ?");
    $stmt->execute([$scenarioId]);
    $scenario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($scenario) {
        $scenario['affected_aircraft'] = json_decode($scenario['affected_aircraft'], true);
        $scenario['environmental_factors'] = json_decode($scenario['environmental_factors'], true);
        $scenario['operational_constraints'] = json_decode($scenario['operational_constraints'], true);
    }

    return $scenario;
}

/**
 * Get recommendations
 */
function getRecommendations($queryParams)
{
    $db = $GLOBALS['db'];
    $query = "SELECT * FROM decision_recommendations WHERE 1=1";
    $params = [];

    if (isset($queryParams['scenario_id'])) {
        $query .= " AND scenario_id = ?";
        $params[] = $queryParams['scenario_id'];
    }

    if (isset($queryParams['recommendation_type'])) {
        $query .= " AND recommendation_type = ?";
        $params[] = $queryParams['recommendation_type'];
    }

    if (isset($queryParams['status'])) {
        $query .= " AND status = ?";
        $params[] = $queryParams['status'];
    }

    if (isset($queryParams['min_confidence'])) {
        $query .= " AND confidence_score >= ?";
        $params[] = $queryParams['min_confidence'];
    }

    $query .= " ORDER BY priority_score DESC, created_at DESC LIMIT 100";

    $stmt = $db->prepare($query);
    $stmt->execute($params);

    $recommendations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Decode JSON fields
    foreach ($recommendations as &$rec) {
        $rec['expected_outcome'] = json_decode($rec['expected_outcome'], true);
        $rec['alternative_options'] = json_decode($rec['alternative_options'], true);
        $rec['implementation_steps'] = json_decode($rec['implementation_steps'], true);
        $rec['risk_assessment'] = json_decode($rec['risk_assessment'], true);
    }

    return $recommendations;
}

/**
 * Get specific recommendation
 */
function getRecommendation($recommendationId)
{
    $db = $GLOBALS['db'];
    $stmt = $db->prepare("SELECT * FROM decision_recommendations WHERE recommendation_id = ?");
    $stmt->execute([$recommendationId]);
    $rec = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($rec) {
        $rec['expected_outcome'] = json_decode($rec['expected_outcome'], true);
        $rec['alternative_options'] = json_decode($rec['alternative_options'], true);
        $rec['implementation_steps'] = json_decode($rec['implementation_steps'], true);
        $rec['risk_assessment'] = json_decode($rec['risk_assessment'], true);
    }

    return $rec;
}

/**
 * Get decision alerts
 */
function getDecisionAlerts($queryParams)
{
    $db = $GLOBALS['db'];
    $query = "SELECT * FROM decision_alerts WHERE 1=1";
    $params = [];

    if (isset($queryParams['alert_type'])) {
        $query .= " AND alert_type = ?";
        $params[] = $queryParams['alert_type'];
    }

    if (isset($queryParams['alert_level'])) {
        $query .= " AND alert_level = ?";
        $params[] = $queryParams['alert_level'];
    }

    if (isset($queryParams['acknowledged'])) {
        $acknowledged = $queryParams['acknowledged'] === 'true' ? 1 : 0;
        $query .= " AND acknowledged = ?";
        $params[] = $acknowledged;
    }

    $query .= " ORDER BY created_at DESC LIMIT 100";

    $stmt = $db->prepare($query);
    $stmt->execute($params);

    $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Decode JSON fields
    foreach ($alerts as &$alert) {
        $alert['recommended_actions'] = json_decode($alert['recommended_actions'], true);
        $alert['affected_parties'] = json_decode($alert['affected_parties'], true);
    }

    return $alerts;
}

/**
 * Get specific decision alert
 */
function getDecisionAlert($alertId)
{
    $db = $GLOBALS['db'];
    $stmt = $db->prepare("SELECT * FROM decision_alerts WHERE alert_id = ?");
    $stmt->execute([$alertId]);
    $alert = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($alert) {
        $alert['recommended_actions'] = json_decode($alert['recommended_actions'], true);
        $alert['affected_parties'] = json_decode($alert['affected_parties'], true);
    }

    return $alert;
}

/**
 * Get decision rules
 */
function getDecisionRules($queryParams)
{
    $db = $GLOBALS['db'];
    $query = "SELECT * FROM decision_rules WHERE 1=1";
    $params = [];

    if (isset($queryParams['rule_type'])) {
        $query .= " AND rule_type = ?";
        $params[] = $queryParams['rule_type'];
    }

    if (isset($queryParams['is_active'])) {
        $active = $queryParams['is_active'] === 'true' ? 1 : 0;
        $query .= " AND is_active = ?";
        $params[] = $active;
    }

    $query .= " ORDER BY priority DESC, created_at DESC";

    $stmt = $db->prepare($query);
    $stmt->execute($params);

    $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Decode JSON fields
    foreach ($rules as &$rule) {
        $rule['conditions'] = json_decode($rule['conditions'], true);
        $rule['actions'] = json_decode($rule['actions'], true);
    }

    return $rules;
}

/**
 * Get decision model performance
 */
function getDecisionModelPerformance($queryParams)
{
    $db = $GLOBALS['db'];
    $query = "SELECT * FROM decision_model_performance WHERE 1=1";
    $params = [];

    if (isset($queryParams['model_name'])) {
        $query .= " AND model_name = ?";
        $params[] = $queryParams['model_name'];
    }

    if (isset($queryParams['scenario_type'])) {
        $query .= " AND scenario_type = ?";
        $params[] = $queryParams['scenario_type'];
    }

    $query .= " ORDER BY evaluated_at DESC LIMIT 100";

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get automated actions
 */
function getAutomatedActions($queryParams)
{
    $db = $GLOBALS['db'];
    $query = "SELECT * FROM automated_actions WHERE 1=1";
    $params = [];

    if (isset($queryParams['action_type'])) {
        $query .= " AND action_type = ?";
        $params[] = $queryParams['action_type'];
    }

    if (isset($queryParams['execution_status'])) {
        $query .= " AND execution_status = ?";
        $params[] = $queryParams['execution_status'];
    }

    if (isset($queryParams['executed_by'])) {
        $query .= " AND executed_by = ?";
        $params[] = $queryParams['executed_by'];
    }

    $query .= " ORDER BY created_at DESC LIMIT 100";

    $stmt = $db->prepare($query);
    $stmt->execute($params);

    $actions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Decode JSON fields
    foreach ($actions as &$action) {
        $action['affected_entities'] = json_decode($action['affected_entities'], true);
        $action['action_parameters'] = json_decode($action['action_parameters'], true);
        $action['execution_result'] = json_decode($action['execution_result'], true);
    }

    return $actions;
}

/**
 * Get decision outcomes
 */
function getDecisionOutcomes($queryParams)
{
    $db = $GLOBALS['db'];
    $query = "SELECT * FROM decision_outcomes WHERE 1=1";
    $params = [];

    if (isset($queryParams['decision_id'])) {
        $query .= " AND decision_id = ?";
        $params[] = $queryParams['decision_id'];
    }

    if (isset($queryParams['outcome_quality'])) {
        $query .= " AND outcome_quality >= ?";
        $params[] = $queryParams['outcome_quality'];
    }

    $query .= " ORDER BY recorded_at DESC LIMIT 100";

    $stmt = $db->prepare($query);
    $stmt->execute($params);

    $outcomes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Decode JSON fields
    foreach ($outcomes as &$outcome) {
        $outcome['actual_outcome'] = json_decode($outcome['actual_outcome'], true);
    }

    return $outcomes;
}

/**
 * Acknowledge decision alert
 */
function acknowledgeDecisionAlert($alertId, $user)
{
    $db = $GLOBALS['db'];

    try {
        $stmt = $db->prepare("
            UPDATE decision_alerts
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
 * Add decision rule
 */
function addDecisionRule($ruleData)
{
    $db = $GLOBALS['db'];

    try {
        $stmt = $db->prepare("
            INSERT INTO decision_rules (
                rule_id, rule_name, rule_type, conditions, actions, priority, is_active
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $ruleId = uniqid('rule_');
        $stmt->execute([
            $ruleId,
            $ruleData['rule_name'],
            $ruleData['rule_type'] ?? 'general',
            json_encode($ruleData['conditions']),
            json_encode($ruleData['actions']),
            $ruleData['priority'] ?? 1,
            $ruleData['is_active'] ?? true
        ]);

        return [
            'success' => true,
            'message' => 'Decision rule added successfully',
            'rule_id' => $ruleId
        ];

    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Evaluate decision model
 */
function evaluateDecisionModel($modelName, $config)
{
    $db = $GLOBALS['db'];

    try {
        // Simulate model evaluation
        $accuracy = rand(85, 95) / 100;
        $precision = rand(80, 93) / 100;
        $recall = rand(78, 91) / 100;
        $decisionQuality = rand(82, 96) / 100;
        $responseTime = rand(50, 200) / 1000; // seconds

        // Store evaluation results
        $stmt = $db->prepare("
            INSERT INTO decision_model_performance (
                model_name, scenario_type, accuracy, precision, recall,
                decision_quality, response_time_avg
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $modelName,
            $config['scenario_type'] ?? 'general',
            $accuracy,
            $precision,
            $recall,
            $decisionQuality,
            $responseTime
        ]);

        return [
            'success' => true,
            'model' => $modelName,
            'metrics' => [
                'accuracy' => $accuracy,
                'precision' => $precision,
                'recall' => $recall,
                'decision_quality' => $decisionQuality,
                'response_time_avg' => $responseTime
            ]
        ];

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
            UPDATE decision_scenarios
            SET status = ?, priority_level = ?, complexity_score = ?
            WHERE scenario_id = ?
        ");

        $stmt->execute([
            $updateData['status'] ?? 'active',
            $updateData['priority_level'] ?? 'medium',
            $updateData['complexity_score'] ?? 0.5,
            $scenarioId
        ]);

        return ['success' => true, 'message' => 'Scenario updated successfully'];

    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Update recommendation
 */
function updateRecommendation($recommendationId, $updateData)
{
    $db = $GLOBALS['db'];

    try {
        $stmt = $db->prepare("
            UPDATE decision_recommendations
            SET status = ?, confidence_score = ?, priority_score = ?
            WHERE recommendation_id = ?
        ");

        $stmt->execute([
            $updateData['status'] ?? 'pending',
            $updateData['confidence_score'] ?? 0.8,
            $updateData['priority_score'] ?? 0.5,
            $recommendationId
        ]);

        return ['success' => true, 'message' => 'Recommendation updated successfully'];

    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Update decision rule
 */
function updateDecisionRule($ruleId, $updateData)
{
    $db = $GLOBALS['db'];

    try {
        $stmt = $db->prepare("
            UPDATE decision_rules
            SET rule_name = ?, conditions = ?, actions = ?, priority = ?, is_active = ?
            WHERE rule_id = ?
        ");

        $stmt->execute([
            $updateData['rule_name'] ?? null,
            isset($updateData['conditions']) ? json_encode($updateData['conditions']) : null,
            isset($updateData['actions']) ? json_encode($updateData['actions']) : null,
            $updateData['priority'] ?? null,
            $updateData['is_active'] ?? null,
            $ruleId
        ]);

        return ['success' => true, 'message' => 'Decision rule updated successfully'];

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
        $stmt = $db->prepare("DELETE FROM decision_scenarios WHERE scenario_id = ?");
        $stmt->execute([$scenarioId]);

        return ['success' => true, 'message' => 'Scenario deleted successfully'];

    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Delete recommendation
 */
function deleteRecommendation($recommendationId)
{
    $db = $GLOBALS['db'];

    try {
        $stmt = $db->prepare("DELETE FROM decision_recommendations WHERE recommendation_id = ?");
        $stmt->execute([$recommendationId]);

        return ['success' => true, 'message' => 'Recommendation deleted successfully'];

    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Delete decision rule
 */
function deleteDecisionRule($ruleId)
{
    $db = $GLOBALS['db'];

    try {
        $stmt = $db->prepare("DELETE FROM decision_rules WHERE rule_id = ?");
        $stmt->execute([$ruleId]);

        return ['success' => true, 'message' => 'Decision rule deleted successfully'];

    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Delete decision alert
 */
function deleteDecisionAlert($alertId)
{
    $db = $GLOBALS['db'];

    try {
        $stmt = $db->prepare("DELETE FROM decision_alerts WHERE alert_id = ?");
        $stmt->execute([$alertId]);

        return ['success' => true, 'message' => 'Alert deleted successfully'];

    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}
