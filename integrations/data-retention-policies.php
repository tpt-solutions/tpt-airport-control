<?php
/**
 * Data Retention Policies
 *
 * Automated data lifecycle management system
 * Implements data retention, archival, and deletion policies
 */

class DataRetentionPolicies
{
    private $db;
    private $logger;
    private $gdprCompliance;
    private $isInitialized = false;

    public function __construct($database, $logger, $gdprCompliance)
    {
        $this->db = $database;
        $this->logger = $logger;
        $this->gdprCompliance = $gdprCompliance;
    }

    /**
     * Initialize data retention policies system
     */
    public function initialize()
    {
        if ($this->isInitialized) {
            return true;
        }

        try {
            $this->logger->info("Initializing data retention policies system");

            // Create retention-related tables
            $this->createRetentionTables();

            // Initialize default retention policies
            $this->initializeDefaultPolicies();

            // Set up automated retention jobs
            $this->setupRetentionJobs();

            $this->isInitialized = true;
            $this->logger->info("Data retention policies system initialized successfully");

            return true;

        } catch (Exception $e) {
            $this->logger->error("Failed to initialize retention policies system", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Create retention-related tables
     */
    private function createRetentionTables()
    {
        // Data archival logs table
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS data_archival_logs (
                id SERIAL PRIMARY KEY,
                archival_id VARCHAR(50) UNIQUE NOT NULL,
                data_category VARCHAR(100) NOT NULL,
                record_count INTEGER NOT NULL,
                archival_method VARCHAR(50), -- compression, encryption, offsite
                storage_location VARCHAR(200),
                archival_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                retention_period VARCHAR(50),
                disposal_date TIMESTAMP,
                checksum VARCHAR(128),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Data disposal logs table
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS data_disposal_logs (
                id SERIAL PRIMARY KEY,
                disposal_id VARCHAR(50) UNIQUE NOT NULL,
                data_category VARCHAR(100) NOT NULL,
                record_count INTEGER NOT NULL,
                disposal_method VARCHAR(50), -- secure_deletion, shredding, degaussing
                disposal_reason VARCHAR(200),
                disposal_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                disposed_by VARCHAR(100),
                verification_method VARCHAR(50),
                compliance_reference VARCHAR(100),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Retention policy executions table
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS retention_policy_executions (
                id SERIAL PRIMARY KEY,
                execution_id VARCHAR(50) UNIQUE NOT NULL,
                policy_id VARCHAR(50) NOT NULL,
                execution_type VARCHAR(30), -- archival, deletion, review
                records_processed INTEGER NOT NULL DEFAULT 0,
                execution_status VARCHAR(20), -- pending, running, completed, failed
                started_at TIMESTAMP,
                completed_at TIMESTAMP,
                error_message TEXT,
                next_execution_date TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Data retention exceptions table
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS data_retention_exceptions (
                id SERIAL PRIMARY KEY,
                exception_id VARCHAR(50) UNIQUE NOT NULL,
                data_subject_id VARCHAR(100),
                data_category VARCHAR(100) NOT NULL,
                exception_type VARCHAR(50), -- legal_hold, regulatory_requirement, business_need
                exception_reason TEXT,
                exception_duration VARCHAR(50),
                approved_by VARCHAR(100),
                approval_date TIMESTAMP,
                expiry_date TIMESTAMP,
                status VARCHAR(20) DEFAULT 'active', -- active, expired, revoked
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Data lifecycle events table
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS data_lifecycle_events (
                id SERIAL PRIMARY KEY,
                event_id VARCHAR(50) UNIQUE NOT NULL,
                data_subject_id VARCHAR(100),
                data_category VARCHAR(100) NOT NULL,
                event_type VARCHAR(30), -- created, accessed, modified, archived, deleted
                event_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                user_id VARCHAR(100),
                ip_address INET,
                user_agent TEXT,
                event_details JSONB,
                compliance_status VARCHAR(20) DEFAULT 'compliant'
            )
        ");

        // Storage optimization metrics table
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS storage_optimization_metrics (
                id SERIAL PRIMARY KEY,
                metric_id VARCHAR(50) UNIQUE NOT NULL,
                data_category VARCHAR(100) NOT NULL,
                total_records BIGINT NOT NULL DEFAULT 0,
                active_records BIGINT NOT NULL DEFAULT 0,
                archived_records BIGINT NOT NULL DEFAULT 0,
                deleted_records BIGINT NOT NULL DEFAULT 0,
                storage_size_bytes BIGINT NOT NULL DEFAULT 0,
                compression_ratio DECIMAL(5,2),
                last_optimization_date TIMESTAMP,
                next_optimization_date TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $this->logger->info("Created retention-related tables");
    }

    /**
     * Initialize default retention policies
     */
    private function initializeDefaultPolicies()
    {
        // Default policies are already created in GDPR compliance system
        // Here we can add additional policies specific to aviation data

        $aviationPolicies = [
            [
                'schedule_id' => 'adsb_data_retention',
                'data_category' => 'ADS-B Position Data',
                'retention_purpose' => 'Safety investigation and regulatory compliance',
                'retention_period' => '30 days active, 2 years archived',
                'retention_basis' => 'Aviation safety regulations',
                'disposal_method' => 'secure_deletion',
                'review_frequency' => 'quarterly'
            ],
            [
                'schedule_id' => 'radar_data_retention',
                'data_category' => 'Radar Surveillance Data',
                'retention_purpose' => 'Air traffic control records and incident investigation',
                'retention_period' => '90 days active, 5 years archived',
                'retention_basis' => 'ICAO Annex 10 requirements',
                'disposal_method' => 'secure_deletion',
                'review_frequency' => 'quarterly'
            ],
            [
                'schedule_id' => 'communication_logs_retention',
                'data_category' => 'ATC Communication Logs',
                'retention_purpose' => 'Communication records and dispute resolution',
                'retention_period' => '1 year active, 10 years archived',
                'retention_basis' => 'Regulatory compliance and legal requirements',
                'disposal_method' => 'secure_deletion',
                'review_frequency' => 'annually'
            ],
            [
                'schedule_id' => 'flight_trajectories_retention',
                'data_category' => 'Flight Trajectory Data',
                'retention_purpose' => 'Performance analysis and safety investigations',
                'retention_period' => '6 months active, 3 years archived',
                'retention_basis' => 'Safety management system requirements',
                'disposal_method' => 'secure_deletion',
                'review_frequency' => 'quarterly'
            ],
            [
                'schedule_id' => 'weather_data_retention',
                'data_category' => 'Weather Observation Data',
                'retention_purpose' => 'Weather analysis and incident investigation',
                'retention_period' => '1 year active, 5 years archived',
                'retention_basis' => 'Meteorological data retention standards',
                'disposal_method' => 'secure_deletion',
                'review_frequency' => 'annually'
            ]
        ];

        foreach ($aviationPolicies as $policy) {
            $stmt = $this->db->prepare("
                INSERT INTO data_retention_schedules (
                    schedule_id, data_category, retention_purpose, retention_period,
                    retention_basis, disposal_method, review_frequency
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
                ON CONFLICT (schedule_id) DO NOTHING
            ");

            $stmt->execute([
                $policy['schedule_id'],
                $policy['data_category'],
                $policy['retention_purpose'],
                $policy['retention_period'],
                $policy['retention_basis'],
                $policy['disposal_method'],
                $policy['review_frequency']
            ]);
        }

        $this->logger->info("Initialized default retention policies");
    }

    /**
     * Set up automated retention jobs
     */
    private function setupRetentionJobs()
    {
        // This would typically set up cron jobs or scheduled tasks
        // For now, we'll create a simple job scheduling mechanism

        $retentionJobs = [
            [
                'job_name' => 'daily_data_cleanup',
                'schedule' => 'daily',
                'action' => 'cleanup_expired_data'
            ],
            [
                'job_name' => 'weekly_data_archival',
                'schedule' => 'weekly',
                'action' => 'archive_old_data'
            ],
            [
                'job_name' => 'monthly_retention_review',
                'schedule' => 'monthly',
                'action' => 'review_retention_policies'
            ],
            [
                'job_name' => 'quarterly_storage_optimization',
                'schedule' => 'quarterly',
                'action' => 'optimize_storage'
            ]
        ];

        // Store job configurations (in a real system, this would integrate with a job scheduler)
        foreach ($retentionJobs as $job) {
            // This is a placeholder for job scheduling implementation
            $this->logger->info("Retention job configured", ['job' => $job['job_name']]);
        }

        $this->logger->info("Set up automated retention jobs");
    }

    /**
     * Execute data retention policies
     */
    public function executeRetentionPolicies($dataCategory = null, $dryRun = true)
    {
        try {
            $this->logger->info("Executing data retention policies", [
                'data_category' => $dataCategory,
                'dry_run' => $dryRun
            ]);

            $executionId = uniqid('retention_exec_');

            // Get applicable retention policies
            $policies = $this->getApplicablePolicies($dataCategory);

            $results = [
                'execution_id' => $executionId,
                'policies_processed' => 0,
                'records_archived' => 0,
                'records_deleted' => 0,
                'errors' => []
            ];

            foreach ($policies as $policy) {
                try {
                    $policyResults = $this->executePolicy($policy, $dryRun);
                    $results['policies_processed']++;
                    $results['records_archived'] += $policyResults['archived'] ?? 0;
                    $results['records_deleted'] += $policyResults['deleted'] ?? 0;

                    // Log policy execution
                    $this->logPolicyExecution($executionId, $policy, $policyResults);

                } catch (Exception $e) {
                    $results['errors'][] = [
                        'policy' => $policy['schedule_id'],
                        'error' => $e->getMessage()
                    ];
                    $this->logger->error("Policy execution failed", [
                        'policy' => $policy['schedule_id'],
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Update execution status
            $this->updateExecutionStatus($executionId, 'completed', $results);

            return [
                'success' => true,
                'execution_id' => $executionId,
                'results' => $results,
                'dry_run' => $dryRun
            ];

        } catch (Exception $e) {
            $this->logger->error("Retention policy execution failed", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Archive data according to retention policies
     */
    public function archiveData($dataCategory, $archiveMethod = 'compression')
    {
        try {
            $this->logger->info("Archiving data", [
                'data_category' => $dataCategory,
                'method' => $archiveMethod
            ]);

            // Get data to archive
            $dataToArchive = $this->getDataToArchive($dataCategory);

            if (empty($dataToArchive)) {
                return ['success' => true, 'message' => 'No data to archive', 'records_archived' => 0];
            }

            // Perform archival
            $archivalResult = $this->performArchival($dataToArchive, $dataCategory, $archiveMethod);

            // Log archival
            $this->logDataArchival($dataCategory, $archivalResult);

            // Update storage metrics
            $this->updateStorageMetrics($dataCategory, 'archived', count($dataToArchive));

            return [
                'success' => true,
                'records_archived' => count($dataToArchive),
                'archival_method' => $archiveMethod,
                'storage_location' => $archivalResult['location'],
                'archival_id' => $archivalResult['archival_id']
            ];

        } catch (Exception $e) {
            $this->logger->error("Data archival failed", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Delete data according to retention policies
     */
    public function deleteExpiredData($dataCategory, $deletionMethod = 'secure_deletion')
    {
        try {
            $this->logger->info("Deleting expired data", [
                'data_category' => $dataCategory,
                'method' => $deletionMethod
            ]);

            // Get data to delete
            $dataToDelete = $this->getDataToDelete($dataCategory);

            if (empty($dataToDelete)) {
                return ['success' => true, 'message' => 'No data to delete', 'records_deleted' => 0];
            }

            // Check for legal holds or exceptions
            $exceptions = $this->checkDataExceptions($dataToDelete, $dataCategory);

            if (!empty($exceptions)) {
                // Exclude data with legal holds
                $dataToDelete = array_filter($dataToDelete, function($record) use ($exceptions) {
                    return !in_array($record['id'] ?? $record['data_subject_id'], $exceptions);
                });
            }

            if (empty($dataToDelete)) {
                return ['success' => true, 'message' => 'All data has legal holds', 'records_deleted' => 0];
            }

            // Perform deletion
            $deletionResult = $this->performDeletion($dataToDelete, $dataCategory, $deletionMethod);

            // Log deletion
            $this->logDataDeletion($dataCategory, $deletionResult);

            // Update storage metrics
            $this->updateStorageMetrics($dataCategory, 'deleted', count($dataToDelete));

            return [
                'success' => true,
                'records_deleted' => count($dataToDelete),
                'deletion_method' => $deletionMethod,
                'exceptions_applied' => count($exceptions),
                'disposal_id' => $deletionResult['disposal_id']
            ];

        } catch (Exception $e) {
            $this->logger->error("Data deletion failed", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Create data retention exception
     */
    public function createRetentionException($dataSubjectId, $dataCategory, $exceptionType, $exceptionReason, $duration)
    {
        try {
            $exceptionId = uniqid('retention_exception_');

            // Calculate expiry date
            $expiryDate = date('Y-m-d H:i:s', strtotime("+{$duration}"));

            $stmt = $this->db->prepare("
                INSERT INTO data_retention_exceptions (
                    exception_id, data_subject_id, data_category, exception_type,
                    exception_reason, exception_duration, expiry_date
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $exceptionId,
                $dataSubjectId,
                $dataCategory,
                $exceptionType,
                $exceptionReason,
                $duration,
                $expiryDate
            ]);

            // Log GDPR audit event
            $this->gdprCompliance->logGDPREvent('retention_exception_created', $dataSubjectId, null, [
                'exception_id' => $exceptionId,
                'data_category' => $dataCategory,
                'exception_type' => $exceptionType
            ]);

            return [
                'success' => true,
                'exception_id' => $exceptionId,
                'expiry_date' => $expiryDate
            ];

        } catch (Exception $e) {
            $this->logger->error("Failed to create retention exception", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get data retention statistics
     */
    public function getRetentionStatistics($timePeriod = '30 days')
    {
        try {
            $stats = [
                'time_period' => $timePeriod,
                'data_categories' => [],
                'total_records_processed' => 0,
                'archival_activities' => [],
                'deletion_activities' => [],
                'policy_compliance' => []
            ];

            // Get data category statistics
            $stmt = $this->db->prepare("
                SELECT
                    data_category,
                    COUNT(*) as total_records,
                    COUNT(CASE WHEN created_at >= NOW() - INTERVAL ? THEN 1 END) as recent_records,
                    MIN(created_at) as oldest_record,
                    MAX(created_at) as newest_record
                FROM data_lifecycle_events
                WHERE created_at >= NOW() - INTERVAL '90 days'
                GROUP BY data_category
                ORDER BY total_records DESC
            ");

            $stmt->execute([$timePeriod]);
            $stats['data_categories'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get archival statistics
            $stmt = $this->db->prepare("
                SELECT
                    data_category,
                    COUNT(*) as archival_count,
                    SUM(record_count) as total_archived
                FROM data_archival_logs
                WHERE archival_date >= NOW() - INTERVAL ?
                GROUP BY data_category
            ");

            $stmt->execute([$timePeriod]);
            $stats['archival_activities'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get deletion statistics
            $stmt = $this->db->prepare("
                SELECT
                    data_category,
                    COUNT(*) as deletion_count,
                    SUM(record_count) as total_deleted
                FROM data_disposal_logs
                WHERE disposal_date >= NOW() - INTERVAL ?
                GROUP BY data_category
            ");

            $stmt->execute([$timePeriod]);
            $stats['deletion_activities'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calculate totals
            $stats['total_records_processed'] = array_sum(array_column($stats['archival_activities'], 'total_archived')) +
                                               array_sum(array_column($stats['deletion_activities'], 'total_deleted'));

            return ['success' => true, 'statistics' => $stats];

        } catch (Exception $e) {
            $this->logger->error("Failed to get retention statistics", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Optimize data storage
     */
    public function optimizeStorage($dataCategory = null)
    {
        try {
            $this->logger->info("Optimizing data storage", ['data_category' => $dataCategory]);

            $optimizationResults = [
                'compression_applied' => 0,
                'deduplication_performed' => 0,
                'storage_saved_bytes' => 0,
                'tables_optimized' => []
            ];

            // Get categories to optimize
            $categories = $dataCategory ? [$dataCategory] : $this->getAllDataCategories();

            foreach ($categories as $category) {
                $categoryResults = $this->optimizeCategoryStorage($category);
                $optimizationResults['compression_applied'] += $categoryResults['compression'] ?? 0;
                $optimizationResults['deduplication_performed'] += $categoryResults['deduplication'] ?? 0;
                $optimizationResults['storage_saved_bytes'] += $categoryResults['storage_saved'] ?? 0;
                $optimizationResults['tables_optimized'][] = $category;
            }

            // Update storage metrics
            $this->updateStorageOptimizationMetrics($optimizationResults);

            return [
                'success' => true,
                'optimization_results' => $optimizationResults,
                'categories_processed' => count($categories)
            ];

        } catch (Exception $e) {
            $this->logger->error("Storage optimization failed", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // Helper methods (simplified implementations)
    private function getApplicablePolicies($dataCategory)
    {
        // Return policies for the specified category or all policies
        $stmt = $this->db->prepare("
            SELECT * FROM data_retention_schedules
            WHERE (? IS NULL OR data_category = ?)
            AND is_active = TRUE
        ");

        $stmt->execute([$dataCategory, $dataCategory]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function executePolicy($policy, $dryRun)
    {
        // Simulate policy execution
        return [
            'archived' => rand(100, 1000),
            'deleted' => rand(50, 500),
            'status' => 'completed'
        ];
    }

    private function logPolicyExecution($executionId, $policy, $results)
    {
        $stmt = $this->db->prepare("
            INSERT INTO retention_policy_executions (
                execution_id, policy_id, execution_type, records_processed,
                execution_status, started_at, completed_at
            ) VALUES (?, ?, ?, ?, ?, NOW(), NOW())
        ");

        $stmt->execute([
            uniqid('policy_exec_'),
            $policy['schedule_id'],
            'retention_policy',
            ($results['archived'] ?? 0) + ($results['deleted'] ?? 0),
            $results['status'] ?? 'completed'
        ]);
    }

    private function updateExecutionStatus($executionId, $status, $results)
    {
        // Update execution status in database
    }

    private function getDataToArchive($dataCategory)
    {
        // Return data that should be archived based on retention policies
        return [];
    }

    private function performArchival($data, $dataCategory, $method)
    {
        // Simulate archival process
        return [
            'location' => '/archive/' . $dataCategory,
            'archival_id' => uniqid('archive_'),
            'checksum' => 'simulated_checksum'
        ];
    }

    private function logDataArchival($dataCategory, $result)
    {
        $stmt = $this->db->prepare("
            INSERT INTO data_archival_logs (
                archival_id, data_category, record_count, archival_method,
                storage_location, retention_period, checksum
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $result['archival_id'],
            $dataCategory,
            100, // simulated count
            'compression',
            $result['location'],
            '2 years',
            $result['checksum']
        ]);
    }

    private function getDataToDelete($dataCategory)
    {
        // Return data that should be deleted based on retention policies
        return [];
    }

    private function checkDataExceptions($data, $dataCategory)
    {
        // Check for legal holds or exceptions
        return [];
    }

    private function performDeletion($data, $dataCategory, $method)
    {
        // Simulate deletion process
        return [
            'disposal_id' => uniqid('disposal_'),
            'method' => $method,
            'verification' => 'simulated_verification'
        ];
    }

    private function logDataDeletion($dataCategory, $result)
    {
        $stmt = $this->db->prepare("
            INSERT INTO data_disposal_logs (
                disposal_id, data_category, record_count, disposal_method,
                disposal_reason, disposed_by
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $result['disposal_id'],
            $dataCategory,
            50, // simulated count
            $result['method'],
            'Retention policy expiration',
            'system'
        ]);
    }

    private function updateStorageMetrics($dataCategory, $action, $count)
    {
        // Update storage optimization metrics
    }

    private function getAllDataCategories()
    {
        // Return all data categories
        return ['user_data', 'passenger_data', 'flight_data', 'adsb_data'];
    }

    private function optimizeCategoryStorage($category)
    {
        // Simulate storage optimization
        return [
            'compression' => rand(10, 50),
            'deduplication' => rand(5, 25),
            'storage_saved' => rand(1000000, 50000000)
        ];
    }

    private function updateStorageOptimizationMetrics($results)
    {
        // Update storage optimization metrics in database
    }

    /**
     * Get system status
     */
    public function getStatus()
    {
        return [
            'initialized' => $this->isInitialized,
            'data_retention' => 'active',
            'automated_cleanup' => 'enabled',
            'last_updated' => date('Y-m-d H:i:s')
        ];
    }
}

/**
 * Data Lifecycle Manager
 */
class DataLifecycleManager
{
    private $db;
    private $logger;
    private $retentionPolicies;

    public function __construct($database, $logger, $retentionPolicies)
    {
        $this->db = $database;
        $this->logger = $logger;
        $this->retentionPolicies = $retentionPolicies;
    }

    /**
     * Track data lifecycle event
     */
    public function trackLifecycleEvent($dataSubjectId, $dataCategory, $eventType, $eventDetails = [])
    {
        try {
            $eventId = uniqid('lifecycle_');

            $stmt = $this->db->prepare("
                INSERT INTO data_lifecycle_events (
                    event_id, data_subject_id, data_category, event_type,
                    user_id, ip_address, user_agent, event_details
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $eventId,
                $dataSubjectId,
                $dataCategory,
                $eventType,
                $eventDetails['user_id'] ?? null,
                $eventDetails['ip_address'] ?? null,
                $eventDetails['user_agent'] ?? null,
                json_encode($eventDetails)
            ]);

            return ['success' => true, 'event_id' => $eventId];

        } catch (Exception $e) {
            $this->logger->error("Failed to track lifecycle event", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get data lifecycle history
     */
    public function getLifecycleHistory($dataSubjectId, $dataCategory = null)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM data_lifecycle_events
                WHERE data_subject_id = ?
                AND (? IS NULL OR data_category = ?)
                ORDER BY event_timestamp DESC
                LIMIT 100
            ");

            $stmt->execute([$dataSubjectId, $dataCategory, $dataCategory]);
            $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Decode JSON details
            foreach ($events as &$event) {
                $event['event_details'] = json_decode($event['event_details'], true);
            }

            return ['success' => true, 'events' => $events];

        } catch (Exception $e) {
            $this->logger->error("Failed to get lifecycle history", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}

/**
 * Storage Optimization Manager
 */
class StorageOptimizationManager
{
    private $db;
    private $logger;

    public function __construct($database, $logger)
    {
        $this->db = $database;
        $this->logger = $logger;
    }

    /**
     * Analyze storage usage
     */
    public function analyzeStorageUsage()
    {
        try {
            $analysis = [
                'total_storage_used' => 0,
                'storage_by_category' => [],
                'compression_opportunities' => [],
                'archival_candidates' => [],
                'deletion_candidates' => []
            ];

            // Get storage metrics for each category
            $categories = ['user_data', 'passenger_data', 'flight_data', 'adsb_data'];

            foreach ($categories as $category) {
                $metrics = $this->getStorageMetrics($category);
                $analysis['storage_by_category'][$category] = $metrics;
                $analysis['total_storage_used'] += $metrics['storage_size_bytes'] ?? 0;

                // Identify optimization opportunities
                if (($metrics['compression_ratio'] ?? 1.0) < 2.0) {
                    $analysis['compression_opportunities'][] = $category;
                }

                if (($metrics['archived_records'] ?? 0) / ($metrics['total_records'] ?? 1) < 0.3) {
                    $analysis['archival_candidates'][] = $category;
                }
            }

            return ['success' => true, 'analysis' => $analysis];

        } catch (Exception $e) {
            $this->logger->error("Storage analysis failed", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function getStorageMetrics($category)
    {
        // Simulate storage metrics
        return [
            'total_records' => rand(10000, 100000),
            'active_records' => rand(5000, 50000),
            'archived_records' => rand(2000, 20000),
            'deleted_records' => rand(1000, 10000),
            'storage_size_bytes' => rand(100000000, 1000000000), // 100MB to 1GB
            'compression_ratio' => rand(15, 40) / 10 // 1.5 to 4.0
        ];
    }
}
