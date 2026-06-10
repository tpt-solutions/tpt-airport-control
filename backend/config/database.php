<?php

declare(strict_types=1);

namespace TPT\FlightControl\Config {

    use PDO;
    use PDOException;

    final class Database
    {
        private static ?PDO $connection = null;

        public static function getConnection(): PDO
        {
            if (self::$connection === null) {
                // Try SQLite demo database first (zero-setup dev/demo mode)
                $sqlitePath = __DIR__ . '/../../database/flight_control_demo.db';
                if (file_exists($sqlitePath) && in_array('sqlite', PDO::getAvailableDrivers())) {
                    try {
                        $conn = new PDO('sqlite:' . $sqlitePath, null, null, [
                            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        ]);
                        // Only use SQLite if it has been initialised (has tables)
                        $tableCount = (int) $conn->query(
                            "SELECT COUNT(*) FROM sqlite_master WHERE type='table'"
                        )->fetchColumn();
                        if ($tableCount > 0) {
                            self::$connection = $conn;
                        }
                    } catch (PDOException $e) {
                        // SQLite unavailable — fall through to PostgreSQL
                    }
                }

                // Fall back to PostgreSQL
                if (self::$connection === null) {
                    if (getenv('ENVIRONMENT') === 'production' && empty(getenv('DB_PASSWORD'))) {
                        throw new PDOException('DB_PASSWORD environment variable must be set in production');
                    }
                    $host     = getenv('DB_HOST')    ?: 'localhost';
                    $dbname   = getenv('DB_NAME')     ?: 'flight_control';
                    $username = getenv('DB_USERNAME') ?: 'flight_user';
                    $password = getenv('DB_PASSWORD') ?: 'flight_pass_2025';
                    if (!getenv('DB_PASSWORD')) {
                        error_log('SECURITY WARNING: DB_PASSWORD not set — using default fallback. Set DB_PASSWORD before going live.');
                    }

                    self::$connection = new PDO(
                        "pgsql:host=$host;dbname=$dbname",
                        $username,
                        $password,
                        [
                            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                            PDO::ATTR_EMULATE_PREPARES   => false,
                            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        ]
                    );
                    // Use REPEATABLE READ globally so booking/payment transactions
                    // see a consistent snapshot and phantom reads cannot cause overbooking.
                    self::$connection->exec('SET SESSION CHARACTERISTICS AS TRANSACTION ISOLATION LEVEL REPEATABLE READ');
                }

                require_once __DIR__ . '/../src/RBAC.php';
                \TPT\FlightControl\RBAC::init(self::$connection);
            }

            return self::$connection;
        }

        public static function resetConnection(): void
        {
            self::$connection = null;
        }

        private function __construct() {}
        private function __clone() {}

        public function __wakeup()
        {
            throw new \RuntimeException('Cannot unserialize database singleton');
        }
    }
}

// Global namespace block: auto-connect on include so every API file gets $pdo
// and Auth JWT validation without needing its own Auth::init() call.
namespace {
    try {
        $pdo = \TPT\FlightControl\Config\Database::getConnection();
        require_once __DIR__ . '/../src/Auth.php';
        \Auth::init($pdo);
    } catch (\Exception $e) {
        error_log('database.php bootstrap error: ' . $e->getMessage());
        $pdo = null;
    }
}
