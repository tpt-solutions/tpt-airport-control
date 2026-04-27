<?php
/**
 * Usage Metering & Quota Enforcement Service
 *
 * Tracks resource usage, enforces subscription quotas,
 * and provides usage analytics for SaaS billing
 */

require_once __DIR__ . '/../src/Logger.php';
require_once __DIR__ . '/SubscriptionService.php';

class UsageMeteringService
{
    private $pdo;
    private $logger;
    private $subscriptionService;
    
    // Usage metrics definitions
    const METRIC_SCENARIOS_PLAYED = 'scenarios_played';
    const METRIC_SCENARIOS_CREATED = 'scenarios_created';
    const METRIC_API_REQUESTS = 'api_requests';
    const METRIC_STORAGE_USED = 'storage_used';
    const METRIC_ACTIVE_USERS = 'active_users';
    const METRIC_SIMULATION_TIME = 'simulation_time_seconds';
    
    /**
     * Usage quotas per subscription tier
     */
    private $tierQuotas = [
        'free' => [
            self::METRIC_SCENARIOS_PLAYED => 10,
            self::METRIC_SCENARIOS_CREATED => 0,
            self::METRIC_API_REQUESTS => 1000,
            self::METRIC_STORAGE_USED => 10 * 1024 * 1024, // 10MB
            self::METRIC_ACTIVE_USERS => 1,
            self::METRIC_SIMULATION_TIME => 3600 // 1 hour
        ],
        'premium' => [
            self::METRIC_SCENARIOS_PLAYED => 100,
            self::METRIC_SCENARIOS_CREATED => 10,
            self::METRIC_API_REQUESTS => 10000,
            self::METRIC_STORAGE_USED => 100 * 1024 * 1024, // 100MB
            self::METRIC_ACTIVE_USERS => 1,
            self::METRIC_SIMULATION_TIME => 36000 // 10 hours
        ],
        'pro' => [
            self::METRIC_SCENARIOS_PLAYED => 1000,
            self::METRIC_SCENARIOS_CREATED => 100,
            self::METRIC_API_REQUESTS => 100000,
            self::METRIC_STORAGE_USED => 1024 * 1024 * 1024, // 1GB
            self::METRIC_ACTIVE_USERS => 10,
            self::METRIC_SIMULATION_TIME => 360000 // 100 hours
        ],
        'institutional' => [
            self::METRIC_SCENARIOS_PLAYED => -1, // Unlimited
            self::METRIC_SCENARIOS_CREATED => -1, // Unlimited
            self::METRIC_API_REQUESTS => -1, // Unlimited
            self::METRIC_STORAGE_USED => 10 * 1024 * 1024 * 1024, // 10GB
            self::METRIC_ACTIVE_USERS => 100,
            self::METRIC_SIMULATION_TIME => -1 // Unlimited
        ]
    ];
    
    public function __construct()
    {
        $this->logger = Logger::getInstance();
        $this->subscriptionService = new SubscriptionService();
        $this->connectDatabase();
    }
    
    private function connectDatabase()
    {
        require __DIR__ . '/../config/database.php';
        $this->pdo = $pdo;
    }
    
    /**
     * Record usage for a tenant/user
     */
    public function recordUsage($tenantId, $userId, $metric, $amount = 1, $metadata = [])
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO usage_metrics 
                (tenant_id, user_id, metric, amount, recorded_at, metadata)
                VALUES (?, ?, ?, ?, NOW(), ?)
            ");
            
            $stmt->execute([
                $tenantId,
                $userId,
                $metric,
                $amount,
                json_encode($metadata)
            ]);
            
            $this->logger->info('Usage recorded', [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'metric' => $metric,
                'amount' => $amount
            ]);
            
            return true;
            
        } catch (Exception $e) {
            $this->logger->error('Failed to record usage', [
                'error' => $e->getMessage(),
                'metric' => $metric
            ]);
            return false;
        }
    }
    
    /**
     * Get current usage for a tenant in current billing period
     */
    public function getCurrentUsage($tenantId, $period = 'current_month')
    {
        $periodWhere = $this->getPeriodWhereClause($period);
        
        $stmt = $this->pdo->prepare("
            SELECT metric, SUM(amount) as total_usage
            FROM usage_metrics
            WHERE tenant_id = ? AND {$periodWhere}
            GROUP BY metric
        ");
        
        $stmt->execute([$tenantId]);
        $usage = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        // Fill in zeros for metrics with no usage
        $allMetrics = [
            self::METRIC_SCENARIOS_PLAYED,
            self::METRIC_SCENARIOS_CREATED,
            self::METRIC_API_REQUESTS,
            self::METRIC_STORAGE_USED,
            self::METRIC_ACTIVE_USERS,
            self::METRIC_SIMULATION_TIME
        ];
        
        foreach ($allMetrics as $metric) {
            if (!isset($usage[$metric])) {
                $usage[$metric] = 0;
            }
        }
        
        return $usage;
    }
    
    /**
     * Check if tenant has remaining quota for a metric
     */
    public function checkQuota($tenantId, $metric, $requestedAmount = 1)
    {
        $subscription = $this->subscriptionService->getUserSubscription($tenantId);
        $tier = $subscription['tier'] ?? 'free';
        
        $quota = $this->getQuotaForTier($tier, $metric);
        
        if ($quota === -1) {
            return [
                'allowed' => true,
                'quota' => -1,
                'usage' => 0,
                'remaining' => -1,
                'unlimited' => true
            ];
        }
        
        $usage = $this->getCurrentUsage($tenantId);
        $currentUsage = $usage[$metric] ?? 0;
        
        $allowed = ($currentUsage + $requestedAmount) <= $quota;
        
        return [
            'allowed' => $allowed,
            'quota' => $quota,
            'usage' => $currentUsage,
            'remaining' => max(0, $quota - $currentUsage),
            'unlimited' => false
        ];
    }
    
    /**
     * Enforce quota - will exit with 402 if quota exceeded
     */
    public function enforceQuota($tenantId, $metric, $requestedAmount = 1)
    {
        $check = $this->checkQuota($tenantId, $metric, $requestedAmount);
        
        if (!$check['allowed']) {
            http_response_code(402);
            echo json_encode([
                'error' => 'Quota exceeded',
                'message' => "You have exceeded your {$metric} quota. Please upgrade your subscription.",
                'quota' => $check['quota'],
                'usage' => $check['usage'],
                'remaining' => $check['remaining']
            ]);
            exit;
        }
        
        return $check;
    }
    
    /**
     * Get quota limit for specific tier and metric
     */
    public function getQuotaForTier($tier, $metric)
    {
        return $this->tierQuotas[$tier][$metric] ?? 0;
    }
    
    /**
     * Get all quotas for a tenant
     */
    public function getTenantQuotas($tenantId)
    {
        $subscription = $this->subscriptionService->getUserSubscription($tenantId);
        $tier = $subscription['tier'] ?? 'free';
        $usage = $this->getCurrentUsage($tenantId);
        
        $quotas = [];
        
        foreach ($this->tierQuotas[$tier] as $metric => $limit) {
            $currentUsage = $usage[$metric] ?? 0;
            
            $quotas[$metric] = [
                'limit' => $limit,
                'usage' => $currentUsage,
                'remaining' => $limit === -1 ? -1 : max(0, $limit - $currentUsage),
                'percent_used' => $limit === -1 ? 0 : round(($currentUsage / $limit) * 100, 2),
                'unlimited' => $limit === -1
            ];
        }
        
        return $quotas;
    }
    
    /**
     * Get usage history for a tenant
     */
    public function getUsageHistory($tenantId, $metric, $period = 'last_30_days')
    {
        $periodWhere = $this->getPeriodWhereClause($period);
        
        $stmt = $this->pdo->prepare("
            SELECT DATE(recorded_at) as date, SUM(amount) as usage
            FROM usage_metrics
            WHERE tenant_id = ? AND metric = ? AND {$periodWhere}
            GROUP BY DATE(recorded_at)
            ORDER BY date ASC
        ");
        
        $stmt->execute([$tenantId, $metric]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Reset usage for a tenant (called on billing cycle reset)
     */
    public function resetUsage($tenantId)
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO usage_metrics_archive
                SELECT * FROM usage_metrics WHERE tenant_id = ?
            ");
            $stmt->execute([$tenantId]);
            
            $stmt = $this->pdo->prepare("DELETE FROM usage_metrics WHERE tenant_id = ?");
            $stmt->execute([$tenantId]);
            
            $this->logger->info('Usage reset for tenant', ['tenant_id' => $tenantId]);
            
            return true;
            
        } catch (Exception $e) {
            $this->logger->error('Failed to reset usage', [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId
            ]);
            return false;
        }
    }
    
    /**
     * Generate usage report for billing
     */
    public function generateBillingReport($tenantId, $period = 'last_month')
    {
        $usage = $this->getCurrentUsage($tenantId, $period);
        $quotas = $this->getTenantQuotas($tenantId);
        $subscription = $this->subscriptionService->getUserSubscription($tenantId);
        
        return [
            'tenant_id' => $tenantId,
            'subscription_tier' => $subscription['tier'],
            'period' => $period,
            'generated_at' => date('Y-m-d H:i:s'),
            'usage' => $usage,
            'quotas' => $quotas,
            'overages' => array_filter($usage, function($amount, $metric) use ($quotas) {
                return $quotas[$metric]['limit'] !== -1 && $amount > $quotas[$metric]['limit'];
            }, ARRAY_FILTER_USE_BOTH)
        ];
    }
    
    /**
     * Get WHERE clause for time periods
     */
    private function getPeriodWhereClause($period)
    {
        switch ($period) {
            case 'today':
                return "DATE(recorded_at) = CURDATE()";
            case 'current_week':
                return "YEARWEEK(recorded_at) = YEARWEEK(NOW())";
            case 'current_month':
                return "MONTH(recorded_at) = MONTH(NOW()) AND YEAR(recorded_at) = YEAR(NOW())";
            case 'last_7_days':
                return "recorded_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            case 'last_30_days':
                return "recorded_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            case 'last_month':
                return "MONTH(recorded_at) = MONTH(DATE_SUB(NOW(), INTERVAL 1 MONTH)) AND YEAR(recorded_at) = YEAR(DATE_SUB(NOW(), INTERVAL 1 MONTH))";
            default:
                return "1=1";
        }
    }
}

?>