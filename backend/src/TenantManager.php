<?php
/**
 * Tenant Manager for Multi-tenant Architecture
 *
 * Handles tenant isolation, database routing, and multi-tenant operations
 * Implements row-level security for customer data separation
 */

require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/Logger.php';

class TenantManager
{
    private static $instance = null;
    private $currentTenantId = null;
    private $pdo;
    private $logger;
    private $tenantCache = [];

    private function __construct()
    {
        $this->logger = new Logger('tenant_manager');
        $this->connectDatabase();
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new TenantManager();
        }
        return self::$instance;
    }

    private function connectDatabase()
    {
        try {
            $config = Config::getInstance();
            $dbConfig = $config->get('database');

            $this->pdo = new PDO(
                "pgsql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']}",
                $dbConfig['username'],
                $dbConfig['password']
            );
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (Exception $e) {
            $this->logger->error('Database connection failed', ['error' => $e->getMessage()]);
            throw new Exception('Database connection failed');
        }
    }

    /**
     * Set current active tenant
     */
    public function setCurrentTenant($tenantId)
    {
        if (!$this->tenantExists($tenantId)) {
            throw new Exception("Invalid tenant ID: {$tenantId}");
        }

        $this->currentTenantId = $tenantId;
        
        // Set tenant context in database session
        $this->pdo->exec("SET app.current_tenant_id = '{$tenantId}'");
        
        $this->logger->debug("Current tenant set to: {$tenantId}");
        
        return true;
    }

    /**
     * Get current active tenant
     */
    public function getCurrentTenantId()
    {
        return $this->currentTenantId;
    }

    /**
     * Check if tenant exists
     */
    public function tenantExists($tenantId)
    {
        if (isset($this->tenantCache[$tenantId])) {
            return true;
        }

        $stmt = $this->pdo->prepare("
            SELECT id FROM tenants WHERE id = ? AND is_active = true
        ");
        
        $stmt->execute([$tenantId]);
        $tenant = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($tenant) {
            $this->tenantCache[$tenantId] = $tenant;
            return true;
        }

        return false;
    }

    /**
     * Get tenant by ID
     */
    public function getTenant($tenantId)
    {
        if (isset($this->tenantCache[$tenantId])) {
            return $this->tenantCache[$tenantId];
        }

        $stmt = $this->pdo->prepare("
            SELECT * FROM tenants WHERE id = ?
        ");
        
        $stmt->execute([$tenantId]);
        $tenant = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($tenant) {
            $this->tenantCache[$tenantId] = $tenant;
            return $tenant;
        }

        return null;
    }

    /**
     * Create new tenant
     */
    public function createTenant($tenantData)
    {
        try {
            $this->pdo->beginTransaction();

            // Generate tenant ID
            $tenantId = 'tnt_' . uniqid();

            $stmt = $this->pdo->prepare("
                INSERT INTO tenants (
                    id, name, subdomain, plan_id, status, 
                    created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $tenantId,
                $tenantData['name'],
                $tenantData['subdomain'] ?? null,
                $tenantData['plan_id'] ?? 'free',
                'active',
                date('Y-m-d H:i:s'),
                date('Y-m-d H:i:s')
            ]);

            // Create tenant admin user
            $this->createTenantAdmin($tenantId, $tenantData);

            // Initialize tenant schema
            $this->initializeTenantResources($tenantId);

            $this->pdo->commit();

            $this->logger->info("New tenant created: {$tenantId}", ['name' => $tenantData['name']]);

            return $tenantId;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            $this->logger->error("Failed to create tenant", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Create tenant admin user
     */
    private function createTenantAdmin($tenantId, $tenantData)
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO users (
                email, username, password_hash, role_id, 
                tenant_id, is_tenant_admin, created_at
            ) VALUES (?, ?, ?, ?, ?, true, ?)
        ");

        $stmt->execute([
            $tenantData['admin_email'],
            $tenantData['admin_username'] ?? 'admin',
            password_hash($tenantData['admin_password'], PASSWORD_DEFAULT),
            1, // Admin role
            $tenantId,
            date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Initialize tenant resources
     */
    private function initializeTenantResources($tenantId)
    {
        // Create default tenant settings
        $stmt = $this->pdo->prepare("
            INSERT INTO tenant_settings (tenant_id, setting_key, setting_value)
            VALUES 
            (?, 'timezone', 'UTC'),
            (?, 'language', 'en'),
            (?, 'date_format', 'YYYY-MM-DD'),
            (?, 'max_users', '10')
        ");

        $stmt->execute([$tenantId, $tenantId, $tenantId, $tenantId]);
    }

    /**
     * Get tenant for current user
     */
    public function getTenantForUser($userId)
    {
        $stmt = $this->pdo->prepare("
            SELECT tenant_id FROM users WHERE id = ?
        ");
        
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ? $result['tenant_id'] : null;
    }

    /**
     * Check if current user has access to tenant
     */
    public function userHasTenantAccess($userId, $tenantId)
    {
        $stmt = $this->pdo->prepare("
            SELECT 1 FROM users 
            WHERE id = ? AND tenant_id = ? AND is_active = true
        ");
        
        $stmt->execute([$userId, $tenantId]);
        
        return $stmt->rowCount() > 0;
    }

    /**
     * Add tenant filter to SQL query
     */
    public function addTenantFilter($query, $tableAlias = '')
    {
        if (!$this->currentTenantId) {
            return $query;
        }

        $prefix = $tableAlias ? "{$tableAlias}." : '';
        
        if (strpos($query, 'WHERE') !== false) {
            return $query . " AND {$prefix}tenant_id = '{$this->currentTenantId}'";
        } else {
            return $query . " WHERE {$prefix}tenant_id = '{$this->currentTenantId}'";
        }
    }

    /**
     * Get tenant usage statistics
     */
    public function getTenantUsage($tenantId)
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                (SELECT COUNT(*) FROM users WHERE tenant_id = ?) as user_count,
                (SELECT COUNT(*) FROM flights WHERE tenant_id = ?) as flight_count,
                (SELECT COUNT(*) FROM scenarios WHERE tenant_id = ?) as scenario_count,
                (SELECT created_at FROM tenants WHERE id = ?) as created_at
        ");
        
        $stmt->execute([$tenantId, $tenantId, $tenantId, $tenantId]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Suspend tenant
     */
    public function suspendTenant($tenantId, $reason = '')
    {
        $stmt = $this->pdo->prepare("
            UPDATE tenants 
            SET status = 'suspended', suspension_reason = ?, updated_at = ?
            WHERE id = ?
        ");
        
        $stmt->execute([
            $reason,
            date('Y-m-d H:i:s'),
            $tenantId
        ]);

        $this->logger->info("Tenant suspended: {$tenantId}", ['reason' => $reason]);
        
        return true;
    }
}

/**
 * Tenant aware PDO wrapper for automatic tenant filtering
 */
class TenantAwarePDO
{
    private $pdo;
    private $tenantManager;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->tenantManager = TenantManager::getInstance();
    }

    public function prepare($statement, $options = [])
    {
        $tenantId = $this->tenantManager->getCurrentTenantId();
        
        if ($tenantId && strpos($statement, 'tenant_id') === false) {
            $statement = $this->tenantManager->addTenantFilter($statement);
        }

        return $this->pdo->prepare($statement, $options);
    }

    public function __call($method, $args)
    {
        return call_user_func_array([$this->pdo, $method], $args);
    }
}
?>