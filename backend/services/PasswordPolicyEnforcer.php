<?php
/**
 * TPT Flight Control System
 * Password Policy Enforcement Engine
 * 
 * Implements industry standard password security policies compliant with NIST SP 800-63B
 * and aviation security requirements
 */

declare(strict_types=1);

use TPT\FlightControl\Config\Database;

class PasswordPolicyEnforcer
{
    const MIN_LENGTH = 12;
    const MIN_ENTROPY = 60;
    const PASSWORD_HISTORY_LIMIT = 12;
    const MAX_PASSWORD_AGE_DAYS = 90;

    /**
     * Validate password against all policy requirements
     */
    public static function validate(string $password, ?int $userId = null): ValidationResult
    {
        $result = new ValidationResult();

        // Length check
        if (strlen($password) < self::MIN_LENGTH) {
            $result->addError("Password must be at least " . self::MIN_LENGTH . " characters long");
        }

        // Character class checks
        if (!preg_match('/[A-Z]/', $password)) {
            $result->addError("Password must contain at least one uppercase letter");
        }

        if (!preg_match('/[a-z]/', $password)) {
            $result->addError("Password must contain at least one lowercase letter");
        }

        if (!preg_match('/[0-9]/', $password)) {
            $result->addError("Password must contain at least one number");
        }

        if (!preg_match('/[!@#$%^&*()\-_=+{};:,<.>]/', $password)) {
            $result->addError("Password must contain at least one special character");
        }

        // Common password check
        if (self::isCommonPassword($password)) {
            $result->addError("This password is too common and cannot be used");
        }

        // Password history check
        if ($userId !== null && self::isPasswordInHistory($userId, $password)) {
            $result->addError("Password has been used previously. You cannot reuse your last " . self::PASSWORD_HISTORY_LIMIT . " passwords");
        }

        // Entropy calculation
        $entropy = self::calculateEntropy($password);
        if ($entropy < self::MIN_ENTROPY) {
            $result->addError("Password is too weak. Increase password complexity");
        }

        return $result;
    }

    /**
     * Calculate password entropy in bits
     */
    public static function calculateEntropy(string $password): float
    {
        $charsetSize = 0;
        
        if (preg_match('/[a-z]/', $password)) $charsetSize += 26;
        if (preg_match('/[A-Z]/', $password)) $charsetSize += 26;
        if (preg_match('/[0-9]/', $password)) $charsetSize += 10;
        if (preg_match('/[^a-zA-Z0-9]/', $password)) $charsetSize += 32;

        return strlen($password) * log($charsetSize, 2);
    }

    /**
     * Check password against common password database
     */
    private static function isCommonPassword(string $password): bool
    {
        $commonPasswords = [
            'password', 'password123', 'admin', 'admin123', '123456', '123456789',
            'qwerty', 'letmein', 'welcome', 'monkey', 'dragon', 'master',
            'flight', 'control', 'atc', 'airport', 'aviation', 'pilot',
            'Password1!', 'Welcome123!', 'Summer2023!', 'Winter2024!'
        ];

        return in_array(strtolower($password), $commonPasswords);
    }

    /**
     * Check if password has been used previously by user
     */
    private static function isPasswordInHistory(int $userId, string $password): bool
    {
        $db = Database::getConnection();
        
        $stmt = $db->prepare("
            SELECT password_hash FROM password_history 
            WHERE user_id = :user_id 
            ORDER BY created_at DESC 
            LIMIT " . self::PASSWORD_HISTORY_LIMIT
        );
        
        $stmt->execute(['user_id' => $userId]);
        $history = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($history as $oldHash) {
            if (password_verify($password, $oldHash)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Add password to history after successful change
     */
    public static function addToHistory(int $userId, string $passwordHash): void
    {
        $db = Database::getConnection();
        
        $stmt = $db->prepare("
            INSERT INTO password_history (user_id, password_hash, created_at)
            VALUES (:user_id, :password_hash, NOW())
        ");
        
        $stmt->execute([
            'user_id' => $userId,
            'password_hash' => $passwordHash
        ]);

        // Trim history to keep only last N passwords
        $stmt = $db->prepare("
            DELETE FROM password_history 
            WHERE user_id = :user_id 
            AND id NOT IN (
                SELECT id FROM password_history 
                WHERE user_id = :user_id 
                ORDER BY created_at DESC 
                LIMIT " . self::PASSWORD_HISTORY_LIMIT . "
            )
        ");
        
        $stmt->execute(['user_id' => $userId]);
    }

    /**
     * Check if password is expired and requires rotation
     */
    public static function isPasswordExpired(int $userId): bool
    {
        $db = Database::getConnection();
        
        $stmt = $db->prepare("
            SELECT created_at FROM password_history 
            WHERE user_id = :user_id 
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        
        $stmt->execute(['user_id' => $userId]);
        $lastChange = $stmt->fetchColumn();

        if (!$lastChange) {
            return true;
        }

        $expiryDate = strtotime($lastChange . ' +' . self::MAX_PASSWORD_AGE_DAYS . ' days');
        return time() > $expiryDate;
    }
}

class ValidationResult
{
    private $errors = [];
    private $warnings = [];

    public function addError(string $message): void
    {
        $this->errors[] = $message;
    }

    public function addWarning(string $message): void
    {
        $this->warnings[] = $message;
    }

    public function isValid(): bool
    {
        return empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getWarnings(): array
    {
        return $this->warnings;
    }
}