<?php
/**
 * Virtual Assistant Model
 *
 * Manages virtual assistant configurations and settings
 */

require_once __DIR__ . '/../src/Config.php';

class VirtualAssistant {
    private $pdo;
    private $table = 'virtual_assistants';

    public function __construct() {
        $config = new Config();
        $this->pdo = $config->getConnection();
    }

    /**
     * Create a new virtual assistant
     */
    public function create($data) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO {$this->table} (
                    user_id, name, voice_enabled, language, personality,
                    voice_speed, voice_pitch, is_active, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");

            $stmt->execute([
                $data['user_id'],
                $data['name'] ?? 'Airport Assistant',
                $data['voice_enabled'] ?? true,
                $data['language'] ?? 'en',
                $data['personality'] ?? 'professional',
                $data['voice_speed'] ?? 1.0,
                $data['voice_pitch'] ?? 1.0,
                $data['is_active'] ?? true
            ]);

            return $this->getById($this->pdo->lastInsertId());
        } catch (Exception $e) {
            throw new Exception('Failed to create virtual assistant: ' . $e->getMessage());
        }
    }

    /**
     * Get virtual assistant by ID
     */
    public function getById($id) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            throw new Exception('Failed to get virtual assistant: ' . $e->getMessage());
        }
    }

    /**
     * Get virtual assistant by user ID
     */
    public function getByUserId($userId) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE user_id = ?");
            $stmt->execute([$userId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            throw new Exception('Failed to get virtual assistant by user ID: ' . $e->getMessage());
        }
    }

    /**
     * Update virtual assistant
     */
    public function update($id, $data) {
        try {
            $fields = [];
            $values = [];

            if (isset($data['name'])) {
                $fields[] = 'name = ?';
                $values[] = $data['name'];
            }
            if (isset($data['voice_enabled'])) {
                $fields[] = 'voice_enabled = ?';
                $values[] = $data['voice_enabled'];
            }
            if (isset($data['language'])) {
                $fields[] = 'language = ?';
                $values[] = $data['language'];
            }
            if (isset($data['personality'])) {
                $fields[] = 'personality = ?';
                $values[] = $data['personality'];
            }
            if (isset($data['voice_speed'])) {
                $fields[] = 'voice_speed = ?';
                $values[] = $data['voice_speed'];
            }
            if (isset($data['voice_pitch'])) {
                $fields[] = 'voice_pitch = ?';
                $values[] = $data['voice_pitch'];
            }
            if (isset($data['is_active'])) {
                $fields[] = 'is_active = ?';
                $values[] = $data['is_active'];
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
            throw new Exception('Failed to update virtual assistant: ' . $e->getMessage());
        }
    }

    /**
     * Delete virtual assistant
     */
    public function delete($id) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = ?");
            $stmt->execute([$id]);
            return true;
        } catch (Exception $e) {
            throw new Exception('Failed to delete virtual assistant: ' . $e->getMessage());
        }
    }

    /**
     * Get all virtual assistants
     */
    public function getAll($limit = 50, $offset = 0) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT va.*, u.username, u.first_name, u.last_name
                FROM {$this->table} va
                JOIN users u ON va.user_id = u.id
                ORDER BY va.created_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$limit, $offset]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            throw new Exception('Failed to get virtual assistants: ' . $e->getMessage());
        }
    }

    /**
     * Get active virtual assistants count
     */
    public function getActiveCount() {
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM {$this->table} WHERE is_active = true");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['count'];
        } catch (Exception $e) {
            throw new Exception('Failed to get active count: ' . $e->getMessage());
        }
    }

    /**
     * Get virtual assistants by language
     */
    public function getByLanguage($language) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE language = ? AND is_active = true");
            $stmt->execute([$language]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            throw new Exception('Failed to get virtual assistants by language: ' . $e->getMessage());
        }
    }

    /**
     * Toggle voice assistant on/off
     */
    public function toggleVoice($id) {
        try {
            $assistant = $this->getById($id);
            $newState = !$assistant['voice_enabled'];

            $stmt = $this->pdo->prepare("
                UPDATE {$this->table}
                SET voice_enabled = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$newState, $id]);

            return $this->getById($id);
        } catch (Exception $e) {
            throw new Exception('Failed to toggle voice: ' . $e->getMessage());
        }
    }

    /**
     * Update assistant personality
     */
    public function updatePersonality($id, $personality) {
        try {
            $validPersonalities = ['professional', 'friendly', 'concise', 'detailed'];
            if (!in_array($personality, $validPersonalities)) {
                throw new Exception('Invalid personality type');
            }

            $stmt = $this->pdo->prepare("
                UPDATE {$this->table}
                SET personality = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$personality, $id]);

            return $this->getById($id);
        } catch (Exception $e) {
            throw new Exception('Failed to update personality: ' . $e->getMessage());
        }
    }
}

// Database table creation SQL
$virtualAssistantTableSQL = "
CREATE TABLE IF NOT EXISTS virtual_assistants (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    name VARCHAR(100) NOT NULL DEFAULT 'Airport Assistant',
    voice_enabled BOOLEAN NOT NULL DEFAULT true,
    language VARCHAR(10) NOT NULL DEFAULT 'en',
    personality VARCHAR(20) NOT NULL DEFAULT 'professional',
    voice_speed DECIMAL(3,2) NOT NULL DEFAULT 1.00,
    voice_pitch DECIMAL(3,2) NOT NULL DEFAULT 1.00,
    is_active BOOLEAN NOT NULL DEFAULT true,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id)
);

CREATE INDEX IF NOT EXISTS idx_virtual_assistants_user_id ON virtual_assistants(user_id);
CREATE INDEX IF NOT EXISTS idx_virtual_assistants_active ON virtual_assistants(is_active);
CREATE INDEX IF NOT EXISTS idx_virtual_assistants_language ON virtual_assistants(language);
";

?>
