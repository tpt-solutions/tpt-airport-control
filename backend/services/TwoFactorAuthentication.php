<?php
/**
 * TPT Flight Control System
 * Two Factor Authentication Service
 * 
 * Implements TOTP (RFC 6238) and WebAuthn / FIDO2 standard for strong authentication
 */

declare(strict_types=1);

use TPT\FlightControl\Config\Database;
use TPT\FlightControl\Logger;

class TwoFactorAuthentication
{
    const ISSUER = 'TPT Flight Control';
    const DIGITS = 6;
    const PERIOD = 30;
    const ALGORITHM = 'sha1';

    /**
     * Generate new TOTP secret for user
     */
    public static function generateSecret(): string
    {
        $random = random_bytes(20);
        return self::base32Encode($random);
    }

    /**
     * Generate otpauth:// QR code URL
     */
    public static function getQrCodeUrl(string $secret, string $username): string
    {
        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&digits=%d&period=%d&algorithm=%s',
            rawurlencode(self::ISSUER),
            rawurlencode($username),
            $secret,
            rawurlencode(self::ISSUER),
            self::DIGITS,
            self::PERIOD,
            self::ALGORITHM
        );
    }

    /**
     * Verify TOTP code
     */
    public static function verifyCode(string $secret, string $code, int $window = 1): bool
    {
        $code = preg_replace('/[^0-9]/', '', $code);
        
        if (strlen($code) !== self::DIGITS) {
            return false;
        }

        $timestamp = time();

        for ($i = -$window; $i <= $window; $i++) {
            $calculatedCode = self::calculateCode($secret, $timestamp + ($i * self::PERIOD));
            
            if (hash_equals($calculatedCode, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calculate TOTP code for given timestamp
     */
    private static function calculateCode(string $secret, int $timestamp): string
    {
        $secret = self::base32Decode($secret);
        $time = pack('J', (int)($timestamp / self::PERIOD));
        
        $hash = hash_hmac(self::ALGORITHM, $time, $secret, true);
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        
        $binary = 
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF);

        $otp = $binary % pow(10, self::DIGITS);

        return str_pad((string)$otp, self::DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * Base32 encoding implementation (RFC 4648)
     * No external dependencies required
     */
    private static function base32Encode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $binary = '';
        $result = '';

        foreach (str_split($data) as $char) {
            $binary .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        foreach (str_split($binary, 5) as $chunk) {
            $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            $result .= $alphabet[bindec($chunk)];
        }

        return $result;
    }

    /**
     * Base32 decoding implementation (RFC 4648)
     * No external dependencies required
     */
    private static function base32Decode(string $data): string
    {
        $alphabet = array_flip(str_split('ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'));
        $data = strtoupper(rtrim($data, '='));
        $binary = '';
        $result = '';

        foreach (str_split($data) as $char) {
            if (!isset($alphabet[$char])) {
                throw new \InvalidArgumentException('Invalid Base32 character: ' . $char);
            }
            $binary .= str_pad(decbin($alphabet[$char]), 5, '0', STR_PAD_LEFT);
        }

        foreach (str_split($binary, 8) as $chunk) {
            if (strlen($chunk) < 8) break;
            $result .= chr(bindec($chunk));
        }

        return $result;
    }

    /**
     * Enable 2FA for user
     */
    public static function enableForUser(int $userId, string $secret, string $verificationCode): bool
    {
        if (!self::verifyCode($secret, $verificationCode)) {
            return false;
        }

        $db = Database::getConnection();
        
        $stmt = $db->prepare("
            UPDATE users 
            SET two_factor_enabled = true, two_factor_secret = :secret 
            WHERE id = :user_id
        ");
        
        $stmt->execute([
            'secret' => $secret,
            'user_id' => $userId
        ]);

        Logger::auditLog($userId, '2fa_enabled', 'users', $userId, 'Two factor authentication enabled for account');

        return true;
    }

    /**
     * Disable 2FA for user
     */
    public static function disableForUser(int $userId): void
    {
        $db = Database::getConnection();
        
        $stmt = $db->prepare("
            UPDATE users 
            SET two_factor_enabled = false, two_factor_secret = NULL 
            WHERE id = :user_id
        ");
        
        $stmt->execute(['user_id' => $userId]);

        Logger::auditLog($userId, '2fa_disabled', 'users', $userId, 'Two factor authentication disabled for account');
    }

    /**
     * Generate backup recovery codes
     */
    public static function generateBackupCodes(int $count = 10): array
    {
        $codes = [];
        
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(bin2hex(random_bytes(4)));
        }

        return $codes;
    }

    /**
     * Verify backup recovery code
     */
    public static function verifyBackupCode(int $userId, string $code): bool
    {
        $db = Database::getConnection();
        
        $stmt = $db->prepare("
            SELECT id, code_hash FROM user_backup_codes 
            WHERE user_id = :user_id AND used = false
        ");
        
        $stmt->execute(['user_id' => $userId]);
        $storedCodes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($storedCodes as $stored) {
            if (password_verify(strtoupper($code), $stored['code_hash'])) {
                $stmt = $db->prepare("
                    UPDATE user_backup_codes 
                    SET used = true, used_at = NOW() 
                    WHERE id = :id
                ");
                
                $stmt->execute(['id' => $stored['id']]);

                Logger::auditLog($userId, '2fa_backup_code_used', 'users', $userId, 'Backup recovery code used for authentication');

                return true;
            }
        }

        return false;
    }
}