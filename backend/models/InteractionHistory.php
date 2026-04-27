<?php
/**
 * Interaction History Model
 *
 * Tracks all user interactions with the virtual assistant
 */

require_once __DIR__ . '/../src/Config.php';

class InteractionHistory {
    private $pdo;
    private $table = 'interaction_history';

    public function __construct() {
        $config = new Config();
        $this->pdo = $config->getConnection();
    }

    /**
     * Create new interaction record
     */
    public function create($data) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO {$this->table} (
                    user_id, interaction_type, input, output, response_time,
                    success, error_message, confidence_score, timestamp
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $data['user_id'],
                $data['interaction_type'] ?? 'text_query',
                $data['input'],
                $data['output'] ?? null,
                $data['response_time'] ?? null,
                $data['success'] ?? true,
                $data['error_message'] ?? null,
                $data['confidence_score'] ?? null,
                $data['timestamp'] ?? date('Y-m-d H:i:s')
            ]);

            return $this->getById($this->pdo->lastInsertId());
        } catch (Exception $e) {
            throw new Exception('Failed to create interaction history: ' . $e->getMessage());
        }
    }

    /**
     * Create feedback record
     */
    public function createFeedback($data) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO interaction_feedback (
                    user_id, interaction_id, rating, feedback_text, helpful,
                    suggested_improvement, timestamp
                ) VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");

            $stmt->execute([
                $data['user_id'],
                $data['interaction_id'] ?? null,
                $data['rating'] ?? null,
                $data['feedback_text'] ?? null,
                $data['helpful'] ?? null,
                $data['suggested_improvement'] ?? null
            ]);

            return ['id' => $this->pdo->lastInsertId(), 'message' => 'Feedback recorded successfully'];
        } catch (Exception $e) {
            throw new Exception('Failed to create feedback: ' . $e->getMessage());
        }
    }

    /**
     * Get interaction by ID
     */
    public function getById($id) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            throw new Exception('Failed to get interaction: ' . $e->getMessage());
        }
    }

    /**
     * Get interactions by user ID
     */
    public function getByUserId($userId, $limit = 50, $offset = 0) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT ih.*, u.username
                FROM {$this->table} ih
                LEFT JOIN users u ON ih.user_id = u.id
                WHERE ih.user_id = ?
                ORDER BY ih.timestamp DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$userId, $limit, $offset]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            throw new Exception('Failed to get interactions by user ID: ' . $e->getMessage());
        }
    }

    /**
     * Get interactions by type
     */
    public function getByType($type, $limit = 50, $offset = 0) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT ih.*, u.username
                FROM {$this->table} ih
                LEFT JOIN users u ON ih.user_id = u.id
                WHERE ih.interaction_type = ?
                ORDER BY ih.timestamp DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$type, $limit, $offset]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            throw new Exception('Failed to get interactions by type: ' . $e->getMessage());
        }
    }

    /**
     * Get interactions by date range
     */
    public function getByDateRange($startDate, $endDate, $limit = 1000) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT ih.*, u.username
                FROM {$this->table} ih
                LEFT JOIN users u ON ih.user_id = u.id
                WHERE ih.timestamp BETWEEN ? AND ?
                ORDER BY ih.timestamp DESC
                LIMIT ?
            ");
            $stmt->execute([$startDate, $endDate, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            throw new Exception('Failed to get interactions by date range: ' . $e->getMessage());
        }
    }

    /**
     * Get interaction statistics
     */
    public function getStatistics($userId = null, $days = 30) {
        try {
            $whereClause = $userId ? "WHERE user_id = ?" : "";
            $params = $userId ? [$userId] : [];

            $stmt = $this->pdo->prepare("
                SELECT
                    COUNT(*) as total_interactions,
                    COUNT(CASE WHEN success = true THEN 1 END) as successful_interactions,
                    COUNT(CASE WHEN success = false THEN 1 END) as failed_interactions,
                    AVG(response_time) as avg_response_time,
                    AVG(confidence_score) as avg_confidence,
                    interaction_type,
                    COUNT(*) as type_count
                FROM {$this->table}
                WHERE timestamp >= NOW() - INTERVAL '{$days} days'
                {$whereClause}
                GROUP BY interaction_type
            ");

            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            throw new Exception('Failed to get interaction statistics: ' . $e->getMessage());
        }
    }

    /**
     * Get daily interaction counts
     */
    public function getDailyCounts($days = 30) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    DATE(timestamp) as date,
                    COUNT(*) as total_count,
                    COUNT(CASE WHEN success = true THEN 1 END) as success_count,
                    COUNT(CASE WHEN success = false THEN 1 END) as failure_count,
                    AVG(response_time) as avg_response_time
                FROM {$this->table}
                WHERE timestamp >= NOW() - INTERVAL '{$days} days'
                GROUP BY DATE(timestamp)
                ORDER BY date DESC
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            throw new Exception('Failed to get daily counts: ' . $e->getMessage());
        }
    }

    /**
     * Get most common queries
     */
    public function getCommonQueries($limit = 20, $days = 30) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    input,
                    COUNT(*) as frequency,
                    AVG(confidence_score) as avg_confidence,
                    MAX(timestamp) as last_used
                FROM {$this->table}
                WHERE timestamp >= NOW() - INTERVAL '{$days} days'
                AND interaction_type IN ('text_query', 'voice_command')
                GROUP BY input
                ORDER BY frequency DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            throw new Exception('Failed to get common queries: ' . $e->getMessage());
        }
    }

    /**
     * Get error analysis
     */
    public function getErrorAnalysis($days = 30) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    error_message,
                    COUNT(*) as error_count,
                    interaction_type,
                    AVG(response_time) as avg_response_time
                FROM {$this->table}
                WHERE success = false
                AND timestamp >= NOW() - INTERVAL '{$days} days'
                GROUP BY error_message, interaction_type
                ORDER BY error_count DESC
                LIMIT 20
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            throw new Exception('Failed to get error analysis: ' . $e->getMessage());
        }
    }

    /**
     * Get user engagement metrics
     */
    public function getUserEngagement($days = 30) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    user_id,
                    COUNT(*) as interaction_count,
                    COUNT(DISTINCT DATE(timestamp)) as active_days,
                    AVG(response_time) as avg_response_time,
                    MAX(timestamp) as last_interaction
                FROM {$this->table}
                WHERE timestamp >= NOW() - INTERVAL '{$days} days'
                GROUP BY user_id
                ORDER BY interaction_count DESC
                LIMIT 100
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            throw new Exception('Failed to get user engagement: ' . $e->getMessage());
        }
    }

    /**
     * Get feedback statistics
     */
    public function getFeedbackStatistics($days = 30) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    COUNT(*) as total_feedback,
                    AVG(rating) as avg_rating,
                    COUNT(CASE WHEN helpful = true THEN 1 END) as helpful_count,
                    COUNT(CASE WHEN helpful = false THEN 1 END) as not_helpful_count
                FROM interaction_feedback
                WHERE timestamp >= NOW() - INTERVAL '{$days} days'
            ");
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            throw new Exception('Failed to get feedback statistics: ' . $e->getMessage());
        }
    }

    /**
     * Delete old interactions (cleanup)
     */
    public function deleteOldInteractions($daysToKeep = 365) {
        try {
            $stmt = $this->pdo->prepare("
                DELETE FROM {$this->table}
                WHERE timestamp < NOW() - INTERVAL '{$daysToKeep} days'
            ");
            $stmt->execute();
            return $stmt->rowCount();
        } catch (Exception $e) {
            throw new Exception('Failed to delete old interactions: ' . $e->getMessage());
        }
    }

    /**
     * Export interactions for analysis
     */
    public function exportInteractions($startDate, $endDate, $format = 'json') {
        try {
            $interactions = $this->getByDateRange($startDate, $endDate, 10000);

            if ($format === 'csv') {
                return $this->convertToCSV($interactions);
            }

            return json_encode($interactions, JSON_PRETTY_PRINT);
        } catch (Exception $e) {
            throw new Exception('Failed to export interactions: ' . $e->getMessage());
        }
    }

    /**
     * Convert interactions to CSV format
     */
    private function convertToCSV($interactions) {
        if (empty($interactions)) {
            return '';
        }

        $csv = "ID,User ID,Username,Type,Input,Output,Response Time,Success,Error Message,Confidence,Timestamp\n";

        foreach ($interactions as $interaction) {
            $csv .= sprintf(
                "%d,%d,%s,%s,\"%s\",\"%s\",%s,%s,\"%s\",%s,%s\n",
                $interaction['id'],
                $interaction['user_id'],
                $interaction['username'] ?? '',
                $interaction['interaction_type'],
                str_replace('"', '""', $interaction['input']),
                str_replace('"', '""', $interaction['output'] ?? ''),
                $interaction['response_time'] ?? '',
                $interaction['success'] ? '1' : '0',
                str_replace('"', '""', $interaction['error_message'] ?? ''),
                $interaction['confidence_score'] ?? '',
                $interaction['timestamp']
            );
        }

        return $csv;
    }
}

// Database table creation SQL
$interactionHistoryTableSQL = "
CREATE TABLE IF NOT EXISTS interaction_history (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    interaction_type VARCHAR(50) NOT NULL,
    input TEXT NOT NULL,
    output TEXT,
    response_time DECIMAL(6,3),
    success BOOLEAN NOT NULL DEFAULT true,
    error_message TEXT,
    confidence_score DECIMAL(5,4),
    timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS interaction_feedback (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    interaction_id INTEGER REFERENCES interaction_history(id) ON DELETE SET NULL,
    rating INTEGER CHECK (rating >= 1 AND rating <= 5),
    feedback_text TEXT,
    helpful BOOLEAN,
    suggested_improvement TEXT,
    timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_interaction_history_user_id ON interaction_history(user_id);
CREATE INDEX IF NOT EXISTS idx_interaction_history_type ON interaction_history(interaction_type);
CREATE INDEX IF NOT EXISTS idx_interaction_history_timestamp ON interaction_history(timestamp DESC);
CREATE INDEX IF NOT EXISTS idx_interaction_history_success ON interaction_history(success);
CREATE INDEX IF NOT EXISTS idx_interaction_feedback_user_id ON interaction_feedback(user_id);
CREATE INDEX IF NOT EXISTS idx_interaction_feedback_timestamp ON interaction_feedback(timestamp DESC);
";

?>
