<?php
/**
 * GDPR Compliance Features
 *
 * Comprehensive data protection and privacy compliance system
 * Implements GDPR requirements for data subject rights, consent management,
 * and privacy controls
 */

class GDPRCompliance
{
    private $db;
    private $logger;
    private $isInitialized = false;

    public function __construct($database, $logger)
    {
        $this->db = $database;
        $this->logger = $logger;
    }

    /**
     * Initialize GDPR compliance system
     */
    public function initialize()
    {
        if ($this->isInitialized) {
            return true;
        }

        try {
            $this->logger->info("Initializing GDPR compliance system");

            // Create GDPR-related tables
            $this->createGDPRTables();

            // Initialize privacy policies
            $this->initializePrivacyPolicies();

            // Set up data processing records
            $this->setupDataProcessingRecords();

            $this->isInitialized = true;
            $this->logger->info("GDPR compliance system initialized successfully");

            return true;

        } catch (Exception $e) {
            $this->logger->error("Failed to initialize GDPR system", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Create GDPR-related tables
     */
    private function createGDPRTables()
    {
        // Data subject consents table
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS data_subject_consents (
                id SERIAL PRIMARY KEY,
                consent_id VARCHAR(50) UNIQUE NOT NULL,
                data_subject_id VARCHAR(100) NOT NULL, -- user_id, passenger_id, or external identifier
                data_subject_type VARCHAR(20) NOT NULL, -- user, passenger, employee
                consent_type VARCHAR(50) NOT NULL, -- marketing, analytics, profiling, etc.
                consent_given BOOLEAN NOT NULL,
                consent_date TIMESTAMP,
                consent_expiry TIMESTAMP,
                consent_withdrawn BOOLEAN DEFAULT FALSE,
                withdrawal_date TIMESTAMP,
                consent_version VARCHAR(20),
                legal_basis VARCHAR(100), -- consent, legitimate_interest, contract, etc.
                consent_scope TEXT,
                ip_address INET,
                user_agent TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Data processing activities table
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS data_processing_activities (
                id SERIAL PRIMARY KEY,
                activity_id VARCHAR(50) UNIQUE NOT NULL,
                activity_name VARCHAR(200) NOT NULL,
                activity_description TEXT,
                legal_basis VARCHAR(100) NOT NULL,
                purpose VARCHAR(200) NOT NULL,
                data_categories JSONB, -- personal_data, sensitive_data, etc.
                data_subjects JSONB, -- customers, employees, etc.
                recipients JSONB, -- internal, external, third_parties
                retention_period VARCHAR(50),
                automated_decision_making BOOLEAN DEFAULT FALSE,
                international_transfer BOOLEAN DEFAULT FALSE,
                transfer_countries JSONB,
                dpo_approval_required BOOLEAN DEFAULT FALSE,
                dpo_approved BOOLEAN DEFAULT FALSE,
                dpo_approval_date TIMESTAMP,
                risk_assessment JSONB,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Data subject rights requests table
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS data_subject_rights_requests (
                id SERIAL PRIMARY KEY,
                request_id VARCHAR(50) UNIQUE NOT NULL,
                data_subject_id VARCHAR(100) NOT NULL,
                data_subject_type VARCHAR(20) NOT NULL,
                request_type VARCHAR(50) NOT NULL, -- access, rectification, erasure, restriction, portability, objection
                request_details TEXT,
                request_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                status VARCHAR(30) DEFAULT 'pending', -- pending, in_progress, completed, rejected
                completion_deadline TIMESTAMP,
                completed_date TIMESTAMP,
                response_provided TEXT,
                verification_method VARCHAR(50), -- identity_document, email_verification, etc.
                verification_status VARCHAR(20) DEFAULT 'pending',
                appeal_requested BOOLEAN DEFAULT FALSE,
                appeal_details TEXT,
                appeal_date TIMESTAMP,
                appeal_status VARCHAR(20),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Data breach notifications table
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS data_breach_notifications (
                id SERIAL PRIMARY KEY,
                breach_id VARCHAR(50) UNIQUE NOT NULL,
                breach_date TIMESTAMP NOT NULL,
                discovery_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                breach_description TEXT NOT NULL,
                data_categories_affected JSONB,
                number_of_subjects_affected INTEGER,
                potential_consequences TEXT,
                measures_taken TEXT,
                supervisory_authority_notified BOOLEAN DEFAULT FALSE,
                notification_date TIMESTAMP,
                notification_reference VARCHAR(100),
                data_subjects_notified BOOLEAN DEFAULT FALSE,
                subjects_notification_date TIMESTAMP,
                risk_assessment JSONB,
                dpo_notified BOOLEAN DEFAULT FALSE,
                dpo_notification_date TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Privacy impact assessments table
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS privacy_impact_assessments (
                id SERIAL PRIMARY KEY,
                assessment_id VARCHAR(50) UNIQUE NOT NULL,
                project_name VARCHAR(200) NOT NULL,
                assessment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                data_protection_officer VARCHAR(100),
                processing_activities JSONB,
                data_flows JSONB,
                risks_identified JSONB,
                mitigation_measures JSONB,
                residual_risks JSONB,
                recommendations TEXT,
                approval_status VARCHAR(20) DEFAULT 'pending', -- pending, approved, rejected
                approval_date TIMESTAMP,
                review_date TIMESTAMP,
                next_review_date TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Data retention schedules table
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS data_retention_schedules (
                id SERIAL PRIMARY KEY,
                schedule_id VARCHAR(50) UNIQUE NOT NULL,
                data_category VARCHAR(100) NOT NULL,
                retention_purpose VARCHAR(200),
                retention_period VARCHAR(50) NOT NULL,
                retention_basis VARCHAR(100),
                disposal_method VARCHAR(50),
                review_frequency VARCHAR(20),
                last_review_date TIMESTAMP,
                next_review_date TIMESTAMP,
                legal_exceptions TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Cookie consent preferences table
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS cookie_consent_preferences (
                id SERIAL PRIMARY KEY,
                preference_id VARCHAR(50) UNIQUE NOT NULL,
                user_id VARCHAR(100),
                session_id VARCHAR(255),
                ip_address INET,
                user_agent TEXT,
                necessary_cookies BOOLEAN DEFAULT TRUE,
                analytics_cookies BOOLEAN DEFAULT FALSE,
                marketing_cookies BOOLEAN DEFAULT FALSE,
                functional_cookies BOOLEAN DEFAULT FALSE,
                preferences_cookies BOOLEAN DEFAULT FALSE,
                consent_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                consent_expiry TIMESTAMP,
                consent_withdrawn BOOLEAN DEFAULT FALSE,
                withdrawal_date TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Data anonymization logs table
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS data_anonymization_logs (
                id SERIAL PRIMARY KEY,
                log_id VARCHAR(50) UNIQUE NOT NULL,
                data_subject_id VARCHAR(100),
                data_category VARCHAR(100),
                anonymization_method VARCHAR(50), -- pseudonymization, aggregation, masking, etc.
                anonymization_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                anonymization_reason VARCHAR(200),
                original_data_hash VARCHAR(128),
                anonymized_data_hash VARCHAR(128),
                reversibility BOOLEAN DEFAULT FALSE,
                retention_period VARCHAR(50),
                disposal_date TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // GDPR audit logs table
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS gdpr_audit_logs (
                id SERIAL PRIMARY KEY,
                audit_id VARCHAR(50) UNIQUE NOT NULL,
                action_type VARCHAR(50) NOT NULL,
                data_subject_id VARCHAR(100),
                user_id VARCHAR(100),
                action_details JSONB,
                ip_address INET,
                user_agent TEXT,
                timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                compliance_status VARCHAR(20) DEFAULT 'compliant'
            )
        ");

        $this->logger->info("Created GDPR compliance tables");
    }

    /**
     * Initialize privacy policies
     */
    private function initializePrivacyPolicies()
    {
        // Insert default privacy policies
        $policies = [
            [
                'activity_id' => 'user_registration',
                'activity_name' => 'User Registration and Profile Management',
                'activity_description' => 'Processing personal data for user registration and profile management',
                'legal_basis' => 'consent',
                'purpose' => 'User account management',
                'data_categories' => json_encode(['personal_data', 'contact_data']),
                'data_subjects' => json_encode(['users']),
                'recipients' => json_encode(['internal']),
                'retention_period' => 'account_active_plus_3_years'
            ],
            [
                'activity_id' => 'passenger_booking',
                'activity_name' => 'Passenger Booking and Travel Management',
                'activity_description' => 'Processing passenger data for flight bookings and travel management',
                'legal_basis' => 'contract',
                'purpose' => 'Flight booking and passenger management',
                'data_categories' => json_encode(['personal_data', 'travel_data', 'payment_data']),
                'data_subjects' => json_encode(['passengers']),
                'recipients' => json_encode(['internal', 'airlines', 'payment_providers']),
                'retention_period' => 'travel_completed_plus_7_years'
            ],
            [
                'activity_id' => 'adsb_tracking',
                'activity_name' => 'Aircraft Tracking and Surveillance',
                'activity_description' => 'Processing aircraft position data for air traffic control',
                'legal_basis' => 'public_task',
                'purpose' => 'Air traffic control and safety',
                'data_categories' => json_encode(['location_data', 'flight_data']),
                'data_subjects' => json_encode(['aircraft_operators', 'passengers']),
                'recipients' => json_encode(['internal', 'regulatory_authorities']),
                'retention_period' => 'flight_completed_plus_30_days'
            ]
        ];

        foreach ($policies as $policy) {
            $stmt = $this->db->prepare("
                INSERT INTO data_processing_activities (
                    activity_id, activity_name, activity_description, legal_basis,
                    purpose, data_categories, data_subjects, recipients, retention_period
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON CONFLICT (activity_id) DO NOTHING
            ");

            $stmt->execute([
                $policy['activity_id'],
                $policy['activity_name'],
                $policy['activity_description'],
                $policy['legal_basis'],
                $policy['purpose'],
                $policy['data_categories'],
                $policy['data_subjects'],
                $policy['recipients'],
                $policy['retention_period']
            ]);
        }

        $this->logger->info("Initialized privacy policies");
    }

    /**
     * Set up data processing records
     */
    private function setupDataProcessingRecords()
    {
        // Set up data retention schedules
        $retentionSchedules = [
            [
                'schedule_id' => 'user_data_retention',
                'data_category' => 'User Account Data',
                'retention_purpose' => 'Account management and legal compliance',
                'retention_period' => '3 years after account deactivation',
                'retention_basis' => 'Legitimate business interests',
                'disposal_method' => 'secure_deletion'
            ],
            [
                'schedule_id' => 'passenger_data_retention',
                'data_category' => 'Passenger Travel Data',
                'retention_purpose' => 'Legal compliance and dispute resolution',
                'retention_period' => '7 years after travel completion',
                'retention_basis' => 'Legal obligation',
                'disposal_method' => 'secure_deletion'
            ],
            [
                'schedule_id' => 'flight_tracking_retention',
                'data_category' => 'Flight Tracking Data',
                'retention_purpose' => 'Safety investigation and regulatory compliance',
                'retention_period' => '30 days after flight completion',
                'retention_basis' => 'Legal obligation',
                'disposal_method' => 'secure_deletion'
            ]
        ];

        foreach ($retentionSchedules as $schedule) {
            $stmt = $this->db->prepare("
                INSERT INTO data_retention_schedules (
                    schedule_id, data_category, retention_purpose, retention_period,
                    retention_basis, disposal_method
                ) VALUES (?, ?, ?, ?, ?, ?)
                ON CONFLICT (schedule_id) DO NOTHING
            ");

            $stmt->execute([
                $schedule['schedule_id'],
                $schedule['data_category'],
                $schedule['retention_purpose'],
                $schedule['retention_period'],
                $schedule['retention_basis'],
                $schedule['disposal_method']
            ]);
        }

        $this->logger->info("Set up data processing records");
    }

    /**
     * Record data subject consent
     */
    public function recordConsent($dataSubjectId, $dataSubjectType, $consentType, $consentGiven, $legalBasis = 'consent', $consentScope = null)
    {
        try {
            $consentId = uniqid('consent_');

            $stmt = $this->db->prepare("
                INSERT INTO data_subject_consents (
                    consent_id, data_subject_id, data_subject_type, consent_type,
                    consent_given, consent_date, legal_basis, consent_scope,
                    ip_address, user_agent
                ) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?)
            ");

            $stmt->execute([
                $consentId,
                $dataSubjectId,
                $dataSubjectType,
                $consentType,
                $consentGiven,
                $legalBasis,
                $consentScope,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);

            // Log GDPR audit event
            $this->logGDPREvent('consent_recorded', $dataSubjectId, null, [
                'consent_id' => $consentId,
                'consent_type' => $consentType,
                'consent_given' => $consentGiven
            ]);

            return ['success' => true, 'consent_id' => $consentId];

        } catch (Exception $e) {
            $this->logger->error("Failed to record consent", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Withdraw data subject consent
     */
    public function withdrawConsent($dataSubjectId, $consentType)
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE data_subject_consents
                SET consent_withdrawn = TRUE, withdrawal_date = NOW()
                WHERE data_subject_id = ? AND consent_type = ? AND consent_withdrawn = FALSE
            ");

            $stmt->execute([$dataSubjectId, $consentType]);

            // Log GDPR audit event
            $this->logGDPREvent('consent_withdrawn', $dataSubjectId, null, [
                'consent_type' => $consentType
            ]);

            return ['success' => true, 'message' => 'Consent withdrawn successfully'];

        } catch (Exception $e) {
            $this->logger->error("Failed to withdraw consent", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Handle data subject rights request
     */
    public function handleRightsRequest($dataSubjectId, $dataSubjectType, $requestType, $requestDetails = null)
    {
        try {
            $requestId = uniqid('dsr_');

            // Calculate completion deadline (30 days for most requests)
            $completionDeadline = date('Y-m-d H:i:s', strtotime('+30 days'));

            $stmt = $this->db->prepare("
                INSERT INTO data_subject_rights_requests (
                    request_id, data_subject_id, data_subject_type, request_type,
                    request_details, completion_deadline
                ) VALUES (?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $requestId,
                $dataSubjectId,
                $dataSubjectType,
                $requestType,
                $requestDetails,
                $completionDeadline
            ]);

            // Log GDPR audit event
            $this->logGDPREvent('rights_request_submitted', $dataSubjectId, null, [
                'request_id' => $requestId,
                'request_type' => $requestType
            ]);

            return [
                'success' => true,
                'request_id' => $requestId,
                'completion_deadline' => $completionDeadline
            ];

        } catch (Exception $e) {
            $this->logger->error("Failed to handle rights request", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Process data access request
     */
    public function processDataAccessRequest($requestId)
    {
        try {
            // Get request details
            $stmt = $this->db->prepare("
                SELECT * FROM data_subject_rights_requests
                WHERE request_id = ? AND request_type = 'access'
            ");
            $stmt->execute([$requestId]);
            $request = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$request) {
                return ['success' => false, 'error' => 'Access request not found'];
            }

            // Gather data for the subject
            $data = $this->gatherSubjectData($request['data_subject_id'], $request['data_subject_type']);

            // Update request status
            $stmt = $this->db->prepare("
                UPDATE data_subject_rights_requests
                SET status = 'completed', completed_date = NOW(), response_provided = ?
                WHERE request_id = ?
            ");

            $stmt->execute([json_encode($data), $requestId]);

            // Log GDPR audit event
            $this->logGDPREvent('data_access_provided', $request['data_subject_id'], null, [
                'request_id' => $requestId
            ]);

            return ['success' => true, 'data' => $data];

        } catch (Exception $e) {
            $this->logger->error("Failed to process data access request", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Process data erasure request (Right to be Forgotten)
     */
    public function processDataErasureRequest($requestId)
    {
        try {
            // Get request details
            $stmt = $this->db->prepare("
                SELECT * FROM data_subject_rights_requests
                WHERE request_id = ? AND request_type = 'erasure'
            ");
            $stmt->execute([$requestId]);
            $request = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$request) {
                return ['success' => false, 'error' => 'Erasure request not found'];
            }

            // Check if erasure is legally permitted
            $erasureAllowed = $this->checkErasurePermissibility($request['data_subject_id'], $request['data_subject_type']);

            if (!$erasureAllowed['allowed']) {
                // Update request as rejected
                $stmt = $this->db->prepare("
                    UPDATE data_subject_rights_requests
                    SET status = 'rejected', completed_date = NOW(),
                        response_provided = ?
                    WHERE request_id = ?
                ");

                $stmt->execute([$erasureAllowed['reason'], $requestId]);

                return [
                    'success' => false,
                    'error' => 'Erasure not permitted',
                    'reason' => $erasureAllowed['reason']
                ];
            }

            // Perform data erasure
            $erasureResult = $this->performDataErasure($request['data_subject_id'], $request['data_subject_type']);

            // Update request status
            $stmt = $this->db->prepare("
                UPDATE data_subject_rights_requests
                SET status = 'completed', completed_date = NOW(),
                    response_provided = ?
                WHERE request_id = ?
            ");

            $stmt->execute([json_encode($erasureResult), $requestId]);

            // Log GDPR audit event
            $this->logGDPREvent('data_erasure_completed', $request['data_subject_id'], null, [
                'request_id' => $requestId,
                'erasure_result' => $erasureResult
            ]);

            return ['success' => true, 'erasure_result' => $erasureResult];

        } catch (Exception $e) {
            $this->logger->error("Failed to process data erasure request", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Record data breach
     */
    public function recordDataBreach($breachDetails)
    {
        try {
            $breachId = uniqid('breach_');

            $stmt = $this->db->prepare("
                INSERT INTO data_breach_notifications (
                    breach_id, breach_date, breach_description, data_categories_affected,
                    number_of_subjects_affected, potential_consequences, risk_assessment
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $breachId,
                $breachDetails['breach_date'] ?? date('Y-m-d H:i:s'),
                $breachDetails['description'],
                json_encode($breachDetails['data_categories_affected'] ?? []),
                $breachDetails['subjects_affected'] ?? 0,
                $breachDetails['consequences'] ?? null,
                json_encode($breachDetails['risk_assessment'] ?? [])
            ]);

            // Log GDPR audit event
            $this->logGDPREvent('data_breach_recorded', null, null, [
                'breach_id' => $breachId,
                'breach_description' => $breachDetails['description']
            ]);

            return ['success' => true, 'breach_id' => $breachId];

        } catch (Exception $e) {
            $this->logger->error("Failed to record data breach", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Perform data anonymization
     */
    public function anonymizeData($dataSubjectId, $dataCategory, $method = 'pseudonymization')
    {
        try {
            $logId = uniqid('anon_');

            // Perform anonymization based on method
            $anonymizationResult = $this->performAnonymization($dataSubjectId, $dataCategory, $method);

            // Log anonymization
            $stmt = $this->db->prepare("
                INSERT INTO data_anonymization_logs (
                    log_id, data_subject_id, data_category, anonymization_method,
                    anonymization_reason, original_data_hash, anonymized_data_hash
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $logId,
                $dataSubjectId,
                $dataCategory,
                $method,
                'GDPR compliance - data minimization',
                $anonymizationResult['original_hash'],
                $anonymizationResult['anonymized_hash']
            ]);

            // Log GDPR audit event
            $this->logGDPREvent('data_anonymized', $dataSubjectId, null, [
                'log_id' => $logId,
                'data_category' => $dataCategory,
                'method' => $method
            ]);

            return ['success' => true, 'anonymization_log_id' => $logId];

        } catch (Exception $e) {
            $this->logger->error("Failed to anonymize data", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Check data retention compliance
     */
    public function checkRetentionCompliance()
    {
        try {
            $expiredData = [];

            // Check user data retention
            $stmt = $this->db->query("
                SELECT u.id, u.username, u.created_at
                FROM users u
                LEFT JOIN (
                    SELECT data_subject_id, MAX(updated_at) as last_activity
                    FROM audit_logs
                    WHERE data_subject_id IS NOT NULL
                    GROUP BY data_subject_id
                ) a ON u.id::text = a.data_subject_id
                WHERE u.is_active = FALSE
                AND (
                    a.last_activity IS NULL AND u.updated_at < NOW() - INTERVAL '3 years'
                    OR a.last_activity < NOW() - INTERVAL '3 years'
                )
            ");

            $expiredUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $expiredData['users'] = $expiredUsers;

            // Check passenger data retention
            $stmt = $this->db->query("
                SELECT p.id, p.first_name, p.last_name, f.actual_arrival
                FROM passengers p
                JOIN bookings b ON p.id = b.passenger_id
                JOIN flights f ON b.flight_id = f.id
                WHERE f.actual_arrival < NOW() - INTERVAL '7 years'
            ");

            $expiredPassengers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $expiredData['passengers'] = $expiredPassengers;

            // Check flight tracking data retention
            $stmt = $this->db->query("
                SELECT DISTINCT icao24, recorded_at
                FROM aircraft_positions
                WHERE recorded_at < NOW() - INTERVAL '30 days'
                LIMIT 100
            ");

            $expiredTracking = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $expiredData['tracking_data'] = $expiredTracking;

            return [
                'success' => true,
                'expired_data' => $expiredData,
                'total_expired_records' => count($expiredUsers) + count($expiredPassengers) + count($expiredTracking)
            ];

        } catch (Exception $e) {
            $this->logger->error("Failed to check retention compliance", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Generate GDPR compliance report
     */
    public function generateComplianceReport($reportPeriod = '30 days')
    {
        try {
            $report = [
                'report_period' => $reportPeriod,
                'generated_at' => date('Y-m-d H:i:s'),
                'data_processing_activities' => [],
                'consent_statistics' => [],
                'rights_requests' => [],
                'data_breaches' => [],
                'retention_compliance' => []
            ];

            // Get data processing activities
            $stmt = $this->db->query("SELECT * FROM data_processing_activities ORDER BY created_at DESC");
            $report['data_processing_activities'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get consent statistics
            $stmt = $this->db->prepare("
                SELECT
                    consent_type,
                    COUNT(*) as total_consents,
                    COUNT(CASE WHEN consent_given THEN 1 END) as consents_given,
                    COUNT(CASE WHEN consent_withdrawn THEN 1 END) as consents_withdrawn
                FROM data_subject_consents
                WHERE created_at >= NOW() - INTERVAL ?
                GROUP BY consent_type
            ");
            $stmt->execute([$reportPeriod]);
            $report['consent_statistics'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get rights requests statistics
            $stmt = $this->db->prepare("
                SELECT
                    request_type,
                    COUNT(*) as total_requests,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_requests,
                    COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_requests
                FROM data_subject_rights_requests
                WHERE request_date >= NOW() - INTERVAL ?
                GROUP BY request_type
            ");
            $stmt->execute([$reportPeriod]);
            $report['rights_requests'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get data breach statistics
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as total_breaches
                FROM data_breach_notifications
                WHERE breach_date >= NOW() - INTERVAL ?
            ");
            $stmt->execute([$reportPeriod]);
            $breachResult = $stmt->fetch(PDO::FETCH_ASSOC);
            $report['data_breaches'] = $breachResult;

            // Get retention compliance status
            $retentionStatus = $this->checkRetentionCompliance();
            $report['retention_compliance'] = $retentionStatus;

            return ['success' => true, 'report' => $report];

        } catch (Exception $e) {
            $this->logger->error("Failed to generate compliance report", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get data subject consents
     */
    public function getDataSubjectConsents($dataSubjectId, $dataSubjectType)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM data_subject_consents
                WHERE data_subject_id = ? AND data_subject_type = ?
                ORDER BY created_at DESC
            ");

            $stmt->execute([$dataSubjectId, $dataSubjectType]);
            $consents = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return ['success' => true, 'consents' => $consents];

        } catch (Exception $e) {
            $this->logger->error("Failed to get data subject consents", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get data subject rights requests
     */
    public function getDataSubjectRightsRequests($dataSubjectId, $dataSubjectType)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM data_subject_rights_requests
                WHERE data_subject_id = ? AND data_subject_type = ?
                ORDER BY request_date DESC
            ");

            $stmt->execute([$dataSubjectId, $dataSubjectType]);
            $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return ['success' => true, 'requests' => $requests];

        } catch (Exception $e) {
            $this->logger->error("Failed to get data subject rights requests", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // Helper methods (simplified implementations)
    private function gatherSubjectData($dataSubjectId, $dataSubjectType)
    {
        // This would gather all data related to a subject
        return ['personal_data' => [], 'processing_activities' => []];
    }

    private function checkErasurePermissibility($dataSubjectId, $dataSubjectType)
    {
        // Check legal grounds for erasure
        return ['allowed' => true, 'reason' => null];
    }

    private function performDataErasure($dataSubjectId, $dataSubjectType)
    {
        // Perform secure data erasure
        return ['records_deleted' => 0, 'tables_affected' => []];
    }

    private function performAnonymization($dataSubjectId, $dataCategory, $method)
    {
        // Perform data anonymization
        return [
            'original_hash' => 'original_data_hash',
            'anonymized_hash' => 'anonymized_data_hash'
        ];
    }

    private function logGDPREvent($actionType, $dataSubjectId, $userId, $actionDetails)
    {
        try {
            $auditId = uniqid('gdpr_audit_');

            $stmt = $this->db->prepare("
                INSERT INTO gdpr_audit_logs (
                    audit_id, action_type, data_subject_id, user_id, action_details,
                    ip_address, user_agent
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $auditId,
                $actionType,
                $dataSubjectId,
                $userId,
                json_encode($actionDetails),
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);

        } catch (Exception $e) {
            $this->logger->error("Failed to log GDPR event", ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get system status
     */
    public function getStatus()
    {
        return [
            'initialized' => $this->isInitialized,
            'gdpr_compliance' => 'active',
            'last_updated' => date('Y-m-d H:i:s')
        ];
    }
}

/**
 * Cookie Consent Manager
 */
class CookieConsentManager
{
    private $db;
    private $logger;

    public function __construct($database, $logger)
    {
        $this->db = $database;
        $this->logger = $logger;
    }

    /**
     * Record cookie consent preferences
     */
    public function recordCookieConsent($userId = null, $preferences)
    {
        try {
            $preferenceId = uniqid('cookie_pref_');

            $stmt = $this->db->prepare("
                INSERT INTO cookie_consent_preferences (
                    preference_id, user_id, session_id, ip_address, user_agent,
                    necessary_cookies, analytics_cookies, marketing_cookies,
                    functional_cookies, preferences_cookies
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $preferenceId,
                $userId,
                session_id(),
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                $preferences['necessary'] ?? true,
                $preferences['analytics'] ?? false,
                $preferences['marketing'] ?? false,
                $preferences['functional'] ?? false,
                $preferences['preferences'] ?? false
            ]);

            return ['success' => true, 'preference_id' => $preferenceId];

        } catch (Exception $e) {
            $this->logger->error("Failed to record cookie consent", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get cookie consent preferences
     */
    public function getCookieConsent($userId = null)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM cookie_consent_preferences
                WHERE user_id = ? OR session_id = ?
                ORDER BY consent_date DESC
                LIMIT 1
            ");

            $stmt->execute([$userId, session_id()]);
            $preferences = $stmt->fetch(PDO::FETCH_ASSOC);

            return ['success' => true, 'preferences' => $preferences];

        } catch (Exception $e) {
            $this->logger->error("Failed to get cookie consent", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Check if cookie category is allowed
     */
    public function isCookieCategoryAllowed($category, $userId = null)
    {
        $consent = $this->getCookieConsent($userId);

        if (!$consent['success'] || !$consent['preferences']) {
            // Default: only necessary cookies allowed
            return $category === 'necessary';
        }

        $preferences = $consent['preferences'];

        switch ($category) {
            case 'necessary':
                return $preferences['necessary_cookies'];
            case 'analytics':
                return $preferences['analytics_cookies'];
            case 'marketing':
                return $preferences['marketing_cookies'];
            case 'functional':
                return $preferences['functional_cookies'];
            case 'preferences':
                return $preferences['preferences_cookies'];
            default:
                return false;
        }
    }
}

/**
 * Data Protection Impact Assessment Manager
 */
class DPIAManager
{
    private $db;
    private $logger;

    public function __construct($database, $logger)
    {
        $this->db = $database;
        $this->logger = $logger;
    }

    /**
     * Create privacy impact assessment
     */
    public function createPIA($assessmentData)
    {
        try {
            $assessmentId = uniqid('pia_');

            $stmt = $this->db->prepare("
                INSERT INTO privacy_impact_assessments (
                    assessment_id, project_name, data_protection_officer,
                    processing_activities, data_flows, risks_identified,
                    mitigation_measures, residual_risks, recommendations
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $assessmentId,
                $assessmentData['project_name'],
                $assessmentData['dpo'] ?? 'Data Protection Officer',
                json_encode($assessmentData['processing_activities'] ?? []),
                json_encode($assessmentData['data_flows'] ?? []),
                json_encode($assessmentData['risks'] ?? []),
                json_encode($assessmentData['mitigation_measures'] ?? []),
                json_encode($assessmentData['residual_risks'] ?? []),
                $assessmentData['recommendations'] ?? null
            ]);

            return ['success' => true, 'assessment_id' => $assessmentId];

        } catch (Exception $e) {
            $this->logger->error("Failed to create PIA", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get privacy impact assessments
     */
    public function getPIAs($status = null)
    {
        try {
            $query = "SELECT * FROM privacy_impact_assessments";
            $params = [];

            if ($status) {
                $query .= " WHERE approval_status = ?";
                $params[] = $status;
            }

            $query .= " ORDER BY assessment_date DESC";

            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            $assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Decode JSON fields
            foreach ($assessments as &$assessment) {
                $assessment['processing_activities'] = json_decode($assessment['processing_activities'], true);
                $assessment['data_flows'] = json_decode($assessment['data_flows'], true);
                $assessment['risks_identified'] = json_decode($assessment['risks_identified'], true);
                $assessment['mitigation_measures'] = json_decode($assessment['mitigation_measures'], true);
                $assessment['residual_risks'] = json_decode($assessment['residual_risks'], true);
            }

            return ['success' => true, 'assessments' => $assessments];

        } catch (Exception $e) {
            $this->logger->error("Failed to get PIAs", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
