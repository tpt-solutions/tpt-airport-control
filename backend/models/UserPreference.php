<?php
/**
 * User Preference Model
 *
 * Manages user preferences for virtual assistant interactions
 */

require_once __DIR__ . '/../src/Config.php';

class UserPreference {
    private $pdo;
    private $table = 'user_preferences';

    public function __construct() {
        $config = new Config();
        $this->pdo = $config->getConnection();
    }

    /**
     * Create user preferences
     */
    public function create($data) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO {$this->table} (
                    user_id, preferred_language, voice_enabled, notification_preferences,
                    interaction_style, response_length, auto_listen, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");

            $stmt->execute([
                $data['user_id'],
                $data['preferred_language'] ?? 'en',
                $data['voice_enabled'] ?? true,
                json_encode($data['notification_preferences'] ?? []),
                $data['interaction_style'] ?? 'professional',
                $data['response_length'] ?? 'medium',
                $data['auto_listen'] ?? false
            ]);

            return $this->getById($this->pdo->lastInsertId());
        } catch (Exception $e) {
            throw new Exception('Failed to create user preferences: ' . $e->getMessage());
        }
    }

    /**
     * Get preferences by ID
     */
    public function getById($id) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE id = ?");
            $stmt->execute([$id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                $result['notification_preferences'] = json_decode($result['notification_preferences'], true);
            }

            return $result;
        } catch (Exception $e) {
            throw new Exception('Failed to get user preferences: ' . $e->getMessage());
        }
    }

    /**
     * Get preferences by user ID
     */
    public function getByUserId($userId) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE user_id = ?");
            $stmt->execute([$userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                $result['notification_preferences'] = json_decode($result['notification_preferences'], true);
            }

            return $result;
        } catch (Exception $e) {
            throw new Exception('Failed to get user preferences by user ID: ' . $e->getMessage());
        }
    }

    /**
     * Update or create preferences
     */
    public function updateOrCreate($data) {
        try {
            $existing = $this->getByUserId($data['user_id']);

            if ($existing) {
                return $this->update($existing['id'], $data);
            } else {
                return $this->create($data);
            }
        } catch (Exception $e) {
            throw new Exception('Failed to update or create preferences: ' . $e->getMessage());
        }
    }

    /**
     * Update preferences
     */
    public function update($id, $data) {
        try {
            $fields = [];
            $values = [];

            if (isset($data['preferred_language'])) {
                $fields[] = 'preferred_language = ?';
                $values[] = $data['preferred_language'];
            }
            if (isset($data['voice_enabled'])) {
                $fields[] = 'voice_enabled = ?';
                $values[] = $data['voice_enabled'];
            }
            if (isset($data['notification_preferences'])) {
                $fields[] = 'notification_preferences = ?';
                $values[] = json_encode($data['notification_preferences']);
            }
            if (isset($data['interaction_style'])) {
                $fields[] = 'interaction_style = ?';
                $values[] = $data['interaction_style'];
            }
            if (isset($data['response_length'])) {
                $fields[] = 'response_length = ?';
                $values[] = $data['response_length'];
            }
            if (isset($data['auto_listen'])) {
                $fields[] = 'auto_listen = ?';
                $values[] = $data['auto_listen'];
            }

            if (empty($fields)) {
                throw new Exception('No fields to update');
            }

            $fields[] = 'updated_at = NOW()';
            $values[] = $id;

            $stmt = $this->pdo->prepare("
                UPDATE {$this->table}
                SET " . implode(', ', $fields) . "
                WHERE id = ?
            ");

            $stmt->execute($values);
            return $this->getById($id);
        } catch (Exception $e) {
            throw new Exception('Failed to update user preferences: ' . $e->getMessage());
        }
    }

    /**
     * Delete preferences
     */
    public function delete($id) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = ?");
            $stmt->execute([$id]);
            return true;
        } catch (Exception $e) {
            throw new Exception('Failed to delete user preferences: ' . $e->getMessage());
        }
    }

    /**
     * Get preferences by language
     */
    public function getByLanguage($language) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE preferred_language = ?");
            $stmt->execute([$language]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($results as &$result) {
                $result['notification_preferences'] = json_decode($result['notification_preferences'], true);
            }

            return $results;
        } catch (Exception $e) {
            throw new Exception('Failed to get preferences by language: ' . $e->getMessage());
        }
    }

    /**
     * Get preferences by interaction style
     */
    public function getByInteractionStyle($style) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE interaction_style = ?");
            $stmt->execute([$style]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($results as &$result) {
                $result['notification_preferences'] = json_decode($result['notification_preferences'], true);
            }

            return $results;
        } catch (Exception $e) {
            throw new Exception('Failed to get preferences by interaction style: ' . $e->getMessage());
        }
    }

    /**
     * Get voice-enabled users count
     */
    public function getVoiceEnabledCount() {
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM {$this->table} WHERE voice_enabled = true");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['count'];
        } catch (Exception $e) {
            throw new Exception('Failed to get voice enabled count: ' . $e->getMessage());
        }
    }

    /**
     * Update notification preferences
     */
    public function updateNotificationPreferences($userId, $preferences) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE {$this->table}
                SET notification_preferences = ?, updated_at = NOW()
                WHERE user_id = ?
            ");
            $stmt->execute([json_encode($preferences), $userId]);

            return $this->getByUserId($userId);
        } catch (Exception $e) {
            throw new Exception('Failed to update notification preferences: ' . $e->getMessage());
        }
    }

    /**
     * Toggle voice assistant
     */
    public function toggleVoice($userId) {
        try {
            $preferences = $this->getByUserId($userId);
            if (!$preferences) {
                // Create default preferences
                return $this->create([
                    'user_id' => $userId,
                    'voice_enabled' => true
                ]);
            }

            $newState = !$preferences['voice_enabled'];

            $stmt = $this->pdo->prepare("
                UPDATE {$this->table}
                SET voice_enabled = ?, updated_at = NOW()
                WHERE user_id = ?
            ");
            $stmt->execute([$newState, $userId]);

            return $this->getByUserId($userId);
        } catch (Exception $e) {
            throw new Exception('Failed to toggle voice: ' . $e->getMessage());
        }
    }

    /**
     * Get user statistics
     */
    public function getUserStatistics() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    COUNT(*) as total_users,
                    SUM(CASE WHEN voice_enabled THEN 1 ELSE 0 END) as voice_enabled,
                    SUM(CASE WHEN auto_listen THEN 1 ELSE 0 END) as auto_listen_enabled,
                    preferred_language,
                    COUNT(*) as language_count
                FROM {$this->table}
                GROUP BY preferred_language
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            throw new Exception('Failed to get user statistics: ' . $e->getMessage());
        }
    }
}

// Database table creation SQL
$userPreferenceTableSQL = "
CREATE TABLE IF NOT EXISTS user_preferences (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    preferred_language VARCHAR(10) NOT NULL DEFAULT 'en',
    voice_enabled BOOLEAN NOT NULL DEFAULT true,
    notification_preferences JSONB NOT NULL DEFAULT '[]',
    interaction_style VARCHAR(20) NOT NULL DEFAULT 'professional',
    response_length VARCHAR(20) NOT NULL DEFAULT 'medium',
    auto_listen BOOLEAN NOT NULL DEFAULT false,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id)
);

CREATE INDEX IF NOT EXISTS idx_user_preferences_user_id ON user_preferences(user_id);
CREATE INDEX IF NOT EXISTS idx_user_preferences_language ON user_preferences(preferred_language);
CREATE INDEX IF NOT EXISTS idx_user_preferences_style ON user_preferences(interaction_style);
";

?>
