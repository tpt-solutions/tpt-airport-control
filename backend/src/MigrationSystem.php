<?php
/**
 * Database Migration System
 *
 * Provides versioned database migrations with rollback support,
 * dependency management, and safe deployment procedures
 */

require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/Config.php';

class MigrationSystem
{
    private static $instance = null;
    private $pdo;
    private $logger;
    private $migrationsPath;
    private $migrationsTable = 'schema_migrations';
    
    /**
     * Get singleton instance
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Private constructor
     */
    private function __construct()
    {
        $this->logger = Logger::getInstance();
        $this->migrationsPath = __DIR__ . '/../migrations/';
        
        $this->initializeDatabase();
        $this->ensureMigrationsTable();
    }
    
    /**
     * Initialize database connection
     */
    private function initializeDatabase()
    {
        $config = new Config();
        $dbConfig = $config->getDatabaseConfig();
        
        try {
            $this->pdo = new PDO(
                "pgsql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']}",
                $dbConfig['username'],
                $dbConfig['password'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
        } catch (Exception $e) {
            $this->logger->error('Database connection failed for migrations', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
    
    /**
     * Ensure migrations tracking table exists
     */
    private function ensureMigrationsTable()
    {
        try {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS {$this->migrationsTable} (
                    version VARCHAR(255) PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    execution_time_ms INTEGER NOT NULL,
                    applied_by VARCHAR(255) NOT NULL,
                    rollback_sql TEXT
                )
            ");
            
            $this->logger->info('Migrations table initialized');
        } catch (Exception $e) {
            $this->logger->error('Failed to create migrations table', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
    
    /**
     * Get all available migration files
     */
    public function getAvailableMigrations()
    {
        if (!is_dir($this->migrationsPath)) {
            mkdir($this->migrationsPath, 0755, true);
        }
        
        $files = glob($this->migrationsPath . '*.php');
        $migrations = [];
        
        foreach ($files as $file) {
            $filename = basename($file);
            if (preg_match('/^(\d{14})_(\w+)\.php$/', $filename, $matches)) {
                $migrations[] = [
                    'version' => $matches[1],
                    'name' => $matches[2],
                    'file' => $file,
                    'filename' => $filename
                ];
            }
        }
        
        usort($migrations, function($a, $b) {
            return strcmp($a['version'], $b['version']);
        });
        
        return $migrations;
    }
    
    /**
     * Get applied migrations
     */
    public function getAppliedMigrations()
    {
        $stmt = $this->pdo->query("SELECT version FROM {$this->migrationsTable} ORDER BY version ASC");
        return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    }
    
    /**
     * Get pending migrations
     */
    public function getPendingMigrations()
    {
        $applied = $this->getAppliedMigrations();
        $available = $this->getAvailableMigrations();
        
        return array_filter($available, function($migration) use ($applied) {
            return !in_array($migration['version'], $applied);
        });
    }
    
    /**
     * Run all pending migrations
     */
    public function migrate($targetVersion = null)
    {
        $pending = $this->getPendingMigrations();
        $applied = [];
        
        $this->logger->info('Starting database migration', [
            'pending_count' => count($pending)
        ]);
        
        foreach ($pending as $migration) {
            if ($targetVersion && $migration['version'] > $targetVersion) {
                break;
            }
            
            $this->applyMigration($migration);
            $applied[] = $migration['version'];
        }
        
        $this->logger->info('Migration completed', [
            'applied_count' => count($applied),
            'applied_versions' => $applied
        ]);
        
        return $applied;
    }
    
    /**
     * Apply a single migration
     */
    private function applyMigration($migration)
    {
        $startTime = microtime(true);
        
        $this->logger->info('Applying migration', [
            'version' => $migration['version'],
            'name' => $migration['name']
        ]);
        
        require_once $migration['file'];
        
        $className = $this->getMigrationClassName($migration['name']);
        
        if (!class_exists($className)) {
            throw new Exception("Migration class {$className} not found in {$migration['filename']}");
        }
        
        $migrationInstance = new $className();
        
        if (!method_exists($migrationInstance, 'up')) {
            throw new Exception("Migration {$className} missing up() method");
        }
        
        $this->pdo->beginTransaction();
        
        try {
            $rollbackSql = $migrationInstance->up($this->pdo);
            
            $executionTime = round((microtime(true) - $startTime) * 1000);
            
            $stmt = $this->pdo->prepare("
                INSERT INTO {$this->migrationsTable} 
                (version, name, execution_time_ms, applied_by, rollback_sql)
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $migration['version'],
                $migration['name'],
                $executionTime,
                get_current_user(),
                $rollbackSql
            ]);
            
            $this->pdo->commit();
            
            $this->logger->info('Migration applied successfully', [
                'version' => $migration['version'],
                'execution_time_ms' => $executionTime
            ]);
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            
            $this->logger->error('Migration failed', [
                'version' => $migration['version'],
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Rollback last migration
     */
    public function rollback($steps = 1)
    {
        $stmt = $this->pdo->prepare("
            SELECT version, name, rollback_sql 
            FROM {$this->migrationsTable} 
            ORDER BY version DESC 
            LIMIT ?
        ");
        
        $stmt->execute([$steps]);
        $migrations = $stmt->fetchAll();
        
        $rolledBack = [];
        
        foreach ($migrations as $migration) {
            $this->rollbackMigration($migration);
            $rolledBack[] = $migration['version'];
        }
        
        return $rolledBack;
    }
    
    /**
     * Rollback a single migration
     */
    private function rollbackMigration($migration)
    {
        $this->logger->info('Rolling back migration', [
            'version' => $migration['version'],
            'name' => $migration['name']
        ]);
        
        $this->pdo->beginTransaction();
        
        try {
            if (!empty($migration['rollback_sql'])) {
                $this->pdo->exec($migration['rollback_sql']);
            }
            
            $stmt = $this->pdo->prepare("DELETE FROM {$this->migrationsTable} WHERE version = ?");
            $stmt->execute([$migration['version']]);
            
            $this->pdo->commit();
            
            $this->logger->info('Migration rolled back successfully', [
                'version' => $migration['version']
            ]);
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            
            $this->logger->error('Rollback failed', [
                'version' => $migration['version'],
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Create a new migration file
     */
    public function createMigration($name)
    {
        $timestamp = date('YmdHis');
        $className = $this->getMigrationClassName($name);
        $filename = "{$timestamp}_{$name}.php";
        $filepath = $this->migrationsPath . $filename;
        
        $template = <<<PHP
<?php

class {$className}
{
    /**
     * Apply the migration
     * Returns SQL string for rollback
     */
    public function up(\$pdo)
    {
        // Migration SQL here
        // \$pdo->exec("CREATE TABLE ...");
        
        // Return rollback SQL
        return "DROP TABLE ...";
    }
}
PHP;
        
        file_put_contents($filepath, $template);
        
        $this->logger->info('Created new migration', [
            'filename' => $filename,
            'path' => $filepath
        ]);
        
        return [
            'version' => $timestamp,
            'name' => $name,
            'file' => $filepath,
            'filename' => $filename
        ];
    }
    
    /**
     * Get migration status
     */
    public function getStatus()
    {
        $applied = $this->getAppliedMigrations();
        $available = $this->getAvailableMigrations();
        $pending = $this->getPendingMigrations();
        
        return [
            'total_available' => count($available),
            'total_applied' => count($applied),
            'pending_count' => count($pending),
            'pending' => $pending,
            'last_applied' => end($applied) ?: null,
            'database_version' => end($applied) ?: '0'
        ];
    }
    
    /**
     * Convert migration name to class name
     */
    private function getMigrationClassName($name)
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $name))) . 'Migration';
    }
}

// Migration helper functions
if (!function_exists('migrate')) {
    function migrate($targetVersion = null) {
        return MigrationSystem::getInstance()->migrate($targetVersion);
    }
}

if (!function_exists('rollback_migrations')) {
    function rollback_migrations($steps = 1) {
        return MigrationSystem::getInstance()->rollback($steps);
    }
}

if (!function_exists('migration_status')) {
    function migration_status() {
        return MigrationSystem::getInstance()->getStatus();
    }
}

?>