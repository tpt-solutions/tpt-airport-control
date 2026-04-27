<?php

declare(strict_types=1);

namespace TPT\FlightControl\Config;

use PDO;
use PDOException;

/**
 * Database Connection Manager
 * 
 * Provides static access to database connection singleton
 * Used by all backend services for database operations
 * 
 * @package TPT\FlightControl\Config
 */
final class Database
{
    private static ?PDO $connection = null;

    /**
     * Get database connection instance
     * 
     * @return PDO
     * @throws PDOException If connection fails
     */
    public static function getConnection(): PDO
    {
        if (self::$connection === null) {
            $host = getenv('DB_HOST') ?: 'localhost';
            $dbname = getenv('DB_NAME') ?: 'flight_control';
            $username = getenv('DB_USERNAME') ?: 'flight_user';
            $password = getenv('DB_PASSWORD') ?: '';

            // Validate required environment variables in production
            if (getenv('ENVIRONMENT') === 'production' && empty($password)) {
                throw new PDOException('DB_PASSWORD environment variable must be set in production');
            }

            self::$connection = new PDO(
                "pgsql:host=$host;dbname=$dbname",
                $username,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );

            // Initialize RBAC with database connection
            require_once __DIR__ . '/../src/RBAC.php';
            \TPT\FlightControl\RBAC::init(self::$connection);
        }

        return self::$connection;
    }

    /**
     * Reset connection (for testing purposes)
     */
    public static function resetConnection(): void
    {
        self::$connection = null;
    }

    /**
     * Prevent instantiation
     */
    private function __construct() {}

    /**
     * Prevent cloning
     */
    private function __clone() {}

    /**
     * Prevent unserialization
     */
    public function __wakeup()
    {
        throw new \RuntimeException('Cannot unserialize database singleton');
    }
}