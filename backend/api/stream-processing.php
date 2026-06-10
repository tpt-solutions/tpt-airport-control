<?php
require_once __DIR__ . '/cors.php';
/**
 * Stream Processing Pipeline API Endpoint
 *
 * Manages high-throughput data processing pipeline for aviation data streams
 */

require_once '../config/database.php';
require_once '../src/Logger.php';
require_once '../src/Middleware.php';
require_once '../../integrations/stream-processing-pipeline.php';

// Initialize components
$db = getDatabaseConnection();
$logger = new Logger($db);
$middleware = new Middleware($db, $logger);
$streamPipeline = new StreamProcessingPipeline($db, $logger);

// Set headers
// Handle preflight OPTIONS request
// Get request method and path
$method = $_SERVER['REQUEST_METHOD'];
$request = $_SERVER['REQUEST_URI'];

// Remove query string and decode
$path = parse_url($request, PHP_URL_PATH);
$path = str_replace('/backend/api/stream-processing', '', $path);
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
            handleGetRequest($resource, $id, $_GET, $streamPipeline);
            break;

        case 'POST':
            handlePostRequest($resource, $input, $streamPipeline, $middleware);
            break;

        case 'PUT':
            handlePutRequest($resource, $id, $input, $streamPipeline, $middleware);
            break;

        case 'DELETE':
            handleDeleteRequest($resource, $id, $streamPipeline, $middleware);
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    $logger->error('Stream processing API error', ['error' => $e->getMessage()]);
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

/**
 * Handle GET requests
 */
function handleGetRequest($resource, $id, $queryParams, $pipeline)
{
    switch ($resource) {
        case null:
            // Get pipeline status
            $status = $pipeline->getStatus();
            echo json_encode(['success' => true, 'status' => $status]);
            break;

        case 'topics':
            if ($id) {
                // Get specific topic info
                $topicInfo = getTopicInfo($id);
                if ($topicInfo) {
                    echo json_encode(['success' => true, 'topic' => $topicInfo]);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Topic not found']);
                }
            } else {
                // Get all topics
                $topics = getAllTopics();
                echo json_encode(['success' => true, 'topics' => $topics]);
            }
            break;

        case 'consumers':
            // Get consumer groups
            $consumers = getConsumerGroups($queryParams);
            echo json_encode(['success' => true, 'consumers' => $consumers]);
            break;

        case 'metrics':
            // Get processing metrics
            $metrics = getProcessingMetrics($queryParams);
            echo json_encode(['success' => true, 'metrics' => $metrics]);
            break;

        case 'jobs':
            if ($id) {
                // Get specific job status
                $job = getJobStatus($id);
                if ($job) {
                    echo json_encode(['success' => true, 'job' => $job]);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Job not found']);
                }
            } else {
                // Get all jobs
                $jobs = getAllJobs($queryParams);
                echo json_encode(['success' => true, 'jobs' => $jobs]);
            }
            break;

        case 'quality':
            // Get data quality metrics
            $quality = getDataQualityMetrics($queryParams);
            echo json_encode(['success' => true, 'quality' => $quality]);
            break;

        case 'messages':
            // Get messages from topic (with pagination)
            if (isset($queryParams['topic'])) {
                $messages = getTopicMessages($queryParams);
                echo json_encode(['success' => true, 'messages' => $messages]);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Topic parameter required']);
            }
            break;

        case 'throughput':
            // Get throughput statistics
            $throughput = getThroughputStats($queryParams);
            echo json_encode(['success' => true, 'throughput' => $throughput]);
            break;

        default:
            http_response_code(404);
            echo json_encode(['error' => 'Resource not found']);
    }
}

/**
 * Handle POST requests
 */
function handlePostRequest($resource, $input, $pipeline, $middleware)
{
    // Require authentication for POST operations
    $user = $middleware->authenticate();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        return;
    }

    switch ($resource) {
        case 'start':
            // Start the pipeline
            $result = $pipeline->start();
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Stream processing pipeline started']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to start pipeline']);
            }
            break;

        case 'stop':
            // Stop the pipeline
            $result = $pipeline->stop();
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Stream processing pipeline stopped']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to stop pipeline']);
            }
            break;

        case 'publish':
            // Publish message to topic
            if (isset($input['topic']) && isset($input['data'])) {
                $result = $pipeline->publishToTopic($input['topic'], $input['data'], $input['key'] ?? null);
                if ($result) {
                    echo json_encode(['success' => true, 'message' => 'Message published']);
                } else {
                    http_response_code(500);
                    echo json_encode(['error' => 'Failed to publish message']);
                }
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Topic and data required']);
            }
            break;

        case 'subscribe':
            // Subscribe to topic
            if (isset($input['topic']) && isset($input['callback'])) {
                $consumer = $pipeline->subscribeToTopic($input['topic'], $input['callback'], $input['group_id'] ?? null);
                echo json_encode(['success' => true, 'consumer_id' => spl_object_hash($consumer)]);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Topic and callback required']);
            }
            break;

        case 'jobs':
            // Create processing job
            $jobId = createProcessingJob($input, $user['id']);
            echo json_encode(['success' => true, 'job_id' => $jobId]);
            break;

        case 'topics':
            // Create new topic
            $topicId = createTopic($input);
            echo json_encode(['success' => true, 'topic_id' => $topicId]);
            break;

        default:
            http_response_code(404);
            echo json_encode(['error' => 'Resource not found']);
    }
}

/**
 * Handle PUT requests
 */
function handlePutRequest($resource, $id, $input, $pipeline, $middleware)
{
    // Require authentication
    $user = $middleware->authenticate();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        return;
    }

    switch ($resource) {
        case 'jobs':
            if ($id) {
                // Update job configuration
                $result = updateProcessingJob($id, $input);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Job ID required']);
            }
            break;

        case 'topics':
            if ($id) {
                // Update topic configuration
                $result = updateTopic($id, $input);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Topic ID required']);
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
function handleDeleteRequest($resource, $id, $pipeline, $middleware)
{
    // Require authentication
    $user = $middleware->authenticate();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        return;
    }

    switch ($resource) {
        case 'jobs':
            if ($id) {
                $result = deleteProcessingJob($id);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Job ID required']);
            }
            break;

        case 'topics':
            if ($id) {
                $result = deleteTopic($id);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Topic ID required']);
            }
            break;

        case 'messages':
            if ($id) {
                $result = deleteMessages($id, $input ?? []);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Topic name required']);
            }
            break;

        default:
            http_response_code(404);
            echo json_encode(['error' => 'Resource not found']);
    }
}

/**
 * Get all topics
 */
function getAllTopics()
{
    $db = $GLOBALS['db'];
    $stmt = $db->query("SELECT * FROM stream_topics ORDER BY topic_name");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get topic information
 */
function getTopicInfo($topicName)
{
    $db = $GLOBALS['db'];
    $stmt = $db->prepare("SELECT * FROM stream_topics WHERE topic_name = ?");
    $stmt->execute([$topicName]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Get consumer groups
 */
function getConsumerGroups($queryParams)
{
    $db = $GLOBALS['db'];
    $query = "SELECT * FROM stream_consumer_groups WHERE 1=1";
    $params = [];

    if (isset($queryParams['group_id'])) {
        $query .= " AND group_id = ?";
        $params[] = $queryParams['group_id'];
    }

    if (isset($queryParams['topic'])) {
        $query .= " AND topic_name = ?";
        $params[] = $queryParams['topic'];
    }

    $query .= " ORDER BY last_seen DESC";

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get processing metrics
 */
function getProcessingMetrics($queryParams)
{
    $db = $GLOBALS['db'];
    $query = "SELECT * FROM stream_processing_metrics WHERE 1=1";
    $params = [];

    if (isset($queryParams['metric_name'])) {
        $query .= " AND metric_name = ?";
        $params[] = $queryParams['metric_name'];
    }

    if (isset($queryParams['start_time'])) {
        $query .= " AND timestamp >= ?";
        $params[] = $queryParams['start_time'];
    }

    if (isset($queryParams['end_time'])) {
        $query .= " AND timestamp <= ?";
        $params[] = $queryParams['end_time'];
    }

    $query .= " ORDER BY timestamp DESC LIMIT 1000";

    $stmt = $db->prepare($query);
    $stmt->execute($params);

    $metrics = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Decode JSON labels
    foreach ($metrics as &$metric) {
        $metric['labels'] = json_decode($metric['labels'], true);
    }

    return $metrics;
}

/**
 * Get all processing jobs
 */
function getAllJobs($queryParams)
{
    $db = $GLOBALS['db'];
    $query = "SELECT * FROM stream_processing_jobs WHERE 1=1";
    $params = [];

    if (isset($queryParams['status'])) {
        $query .= " AND status = ?";
        $params[] = $queryParams['status'];
    }

    if (isset($queryParams['type'])) {
        $query .= " AND job_type = ?";
        $params[] = $queryParams['type'];
    }

    $query .= " ORDER BY created_at DESC";

    $stmt = $db->prepare($query);
    $stmt->execute($params);

    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Decode JSON config
    foreach ($jobs as &$job) {
        $job['config'] = json_decode($job['config'], true);
    }

    return $jobs;
}

/**
 * Get job status
 */
function getJobStatus($jobName)
{
    $db = $GLOBALS['db'];
    $stmt = $db->prepare("SELECT * FROM stream_processing_jobs WHERE job_name = ?");
    $stmt->execute([$jobName]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($job) {
        $job['config'] = json_decode($job['config'], true);
    }

    return $job;
}

/**
 * Get data quality metrics
 */
function getDataQualityMetrics($queryParams)
{
    $db = $GLOBALS['db'];
    $query = "SELECT * FROM stream_data_quality WHERE 1=1";
    $params = [];

    if (isset($queryParams['data_source'])) {
        $query .= " AND data_source = ?";
        $params[] = $queryParams['data_source'];
    }

    $query .= " ORDER BY last_updated DESC";

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get topic messages
 */
function getTopicMessages($queryParams)
{
    $db = $GLOBALS['db'];
    $topic = $queryParams['topic'];
    $partition = $queryParams['partition'] ?? 0;
    $limit = min($queryParams['limit'] ?? 100, 1000);
    $offset = $queryParams['offset'] ?? 0;

    $stmt = $db->prepare("
        SELECT * FROM stream_messages
        WHERE topic_name = ? AND partition_id = ?
        ORDER BY message_offset DESC
        LIMIT ? OFFSET ?
    ");

    $stmt->execute([$topic, $partition, $limit, $offset]);

    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Decode JSON data
    foreach ($messages as &$message) {
        $message['message_data'] = json_decode($message['message_data'], true);
    }

    return $messages;
}

/**
 * Get throughput statistics
 */
function getThroughputStats($queryParams)
{
    $db = $GLOBALS['db'];

    // Calculate throughput over time windows
    $stmt = $db->prepare("
        SELECT
            DATE_TRUNC('hour', timestamp) as hour,
            COUNT(*) as message_count,
            AVG(metric_value) as avg_processing_time
        FROM stream_processing_metrics
        WHERE metric_name IN ('adsb_processed', 'radar_processed', 'satellite_processed', 'weather_processed')
        AND timestamp >= NOW() - INTERVAL '24 hours'
        GROUP BY DATE_TRUNC('hour', timestamp)
        ORDER BY hour DESC
    ");

    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Create processing job
 */
function createProcessingJob($jobData, $userId)
{
    $db = $GLOBALS['db'];
    $stmt = $db->prepare("
        INSERT INTO stream_processing_jobs (
            job_name, job_type, status, config
        ) VALUES (?, ?, ?, ?)
    ");

    $stmt->execute([
        $jobData['job_name'],
        $jobData['job_type'],
        $jobData['status'] ?? 'stopped',
        json_encode($jobData['config'] ?? [])
    ]);

    return $db->lastInsertId();
}

/**
 * Create topic
 */
function createTopic($topicData)
{
    $db = $GLOBALS['db'];
    $stmt = $db->prepare("
        INSERT INTO stream_topics (
            topic_name, partitions, replication_factor, retention_hours
        ) VALUES (?, ?, ?, ?)
    ");

    $stmt->execute([
        $topicData['topic_name'],
        $topicData['partitions'] ?? 1,
        $topicData['replication_factor'] ?? 1,
        $topicData['retention_hours'] ?? 168
    ]);

    return $db->lastInsertId();
}

/**
 * Update processing job
 */
function updateProcessingJob($jobId, $updateData)
{
    $db = $GLOBALS['db'];

    try {
        $stmt = $db->prepare("
            UPDATE stream_processing_jobs
            SET config = ?, status = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");

        $stmt->execute([
            json_encode($updateData['config'] ?? []),
            $updateData['status'] ?? 'stopped',
            $jobId
        ]);

        return ['success' => true, 'message' => 'Job updated successfully'];

    } catch (Exception $e) {
        return ['success' => false, 'error' => 'An internal error occurred'];
    }
}

/**
 * Update topic
 */
function updateTopic($topicId, $updateData)
{
    $db = $GLOBALS['db'];

    try {
        $stmt = $db->prepare("
            UPDATE stream_topics
            SET partitions = ?, replication_factor = ?, retention_hours = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $updateData['partitions'] ?? 1,
            $updateData['replication_factor'] ?? 1,
            $updateData['retention_hours'] ?? 168,
            $topicId
        ]);

        return ['success' => true, 'message' => 'Topic updated successfully'];

    } catch (Exception $e) {
        return ['success' => false, 'error' => 'An internal error occurred'];
    }
}

/**
 * Delete processing job
 */
function deleteProcessingJob($jobId)
{
    $db = $GLOBALS['db'];

    try {
        $stmt = $db->prepare("DELETE FROM stream_processing_jobs WHERE id = ?");
        $stmt->execute([$jobId]);

        return ['success' => true, 'message' => 'Job deleted successfully'];

    } catch (Exception $e) {
        return ['success' => false, 'error' => 'An internal error occurred'];
    }
}

/**
 * Delete topic
 */
function deleteTopic($topicId)
{
    $db = $GLOBALS['db'];

    try {
        $stmt = $db->prepare("DELETE FROM stream_topics WHERE id = ?");
        $stmt->execute([$topicId]);

        return ['success' => true, 'message' => 'Topic deleted successfully'];

    } catch (Exception $e) {
        return ['success' => false, 'error' => 'An internal error occurred'];
    }
}

/**
 * Delete messages from topic
 */
function deleteMessages($topicName, $criteria)
{
    $db = $GLOBALS['db'];

    try {
        $query = "DELETE FROM stream_messages WHERE topic_name = ?";
        $params = [$topicName];

        if (isset($criteria['before_timestamp'])) {
            $query .= " AND timestamp < ?";
            $params[] = $criteria['before_timestamp'];
        }

        if (isset($criteria['partition_id'])) {
            $query .= " AND partition_id = ?";
            $params[] = $criteria['partition_id'];
        }

        $stmt = $db->prepare($query);
        $stmt->execute($params);

        return ['success' => true, 'message' => 'Messages deleted successfully'];

    } catch (Exception $e) {
        return ['success' => false, 'error' => 'An internal error occurred'];
    }
}
