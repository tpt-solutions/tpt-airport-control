<?php

/**
 * Customs & Border Protection Model
 *
 * Manages passport data, customs declarations, border control, and immigration processes
 */

class CustomsBorderProtection
{
    private $db;
    private $logger;

    public function __construct()
    {
        $this->db = new PDO(
            "pgsql:host=" . getenv('DB_HOST') . ";dbname=" . getenv('DB_DATABASE'),
            getenv('DB_USERNAME'),
            getenv('DB_PASSWORD'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $this->logger = new Logger('customs_border_protection');
    }

    /**
     * Register passport data
     */
    public function registerPassport($passportData)
    {
        $this->logger->info("Registering passport", $passportData);

        $stmt = $this->db->prepare("
            INSERT INTO passport_data (
                passport_id, passport_number, issuing_country, issuing_authority,
                issue_date, expiry_date, passport_type, holder_name, holder_nationality,
                holder_birth_date, holder_gender, holder_birth_place, holder_address,
                holder_contact, biometric_data, visa_information, travel_history,
                security_clearance_level, watchlist_status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON CONFLICT (passport_number) DO UPDATE SET
                expiry_date = EXCLUDED.expiry_date,
                holder_contact = EXCLUDED.holder_contact,
                biometric_data = EXCLUDED.biometric_data,
                visa_information = EXCLUDED.visa_information,
                travel_history = EXCLUDED.travel_history,
                security_clearance_level = EXCLUDED.security_clearance_level,
                watchlist_status = EXCLUDED.watchlist_status,
                last_updated = CURRENT_TIMESTAMP
        ");

        $passportId = $this->generatePassportId();

        $stmt->execute([
            $passportId,
            $passportData['passport_number'],
            $passportData['issuing_country'],
            $passportData['issuing_authority'] ?? null,
            $passportData['issue_date'],
            $passportData['expiry_date'],
            $passportData['passport_type'] ?? 'ordinary',
            $passportData['holder_name'],
            $passportData['holder_nationality'],
            $passportData['holder_birth_date'] ?? null,
            $passportData['holder_gender'] ?? null,
            $passportData['holder_birth_place'] ?? null,
            isset($passportData['holder_address']) ? json_encode($passportData['holder_address']) : null,
            isset($passportData['holder_contact']) ? json_encode($passportData['holder_contact']) : null,
            isset($passportData['biometric_data']) ? json_encode($passportData['biometric_data']) : '{}',
            isset($passportData['visa_information']) ? json_encode($passportData['visa_information']) : '[]',
            isset($passportData['travel_history']) ? json_encode($passportData['travel_history']) : '[]',
            $passportData['security_clearance_level'] ?? 'standard',
            $passportData['watchlist_status'] ?? 'clear'
        ]);

        return [
            'passport_id' => $passportId,
            'status' => 'registered',
            'message' => 'Passport data registered successfully'
        ];
    }

    /**
     * Get passport details
     */
    public function getPassport($passportNumber)
    {
        $stmt = $this->db->prepare("
            SELECT
                pd.*,
                (
                    SELECT COUNT(*)
                    FROM border_control_entries
                    WHERE passport_id = pd.passport_id
                    AND entry_status = 'approved'
                ) as total_entries,
                (
                    SELECT COUNT(*)
                    FROM immigration_records
                    WHERE passport_id = pd.passport_id
                    AND overstayed = true
                ) as overstayed_count,
                (
                    SELECT COUNT(*)
                    FROM security_watchlist
                    WHERE individual_name = pd.holder_name
                    AND active = true
                ) as watchlist_alerts
            FROM passport_data pd
            WHERE pd.passport_number = ?
        ");

        $stmt->execute([$passportNumber]);
        $passport = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$passport) {
            throw new Exception("Passport not found");
        }

        return $passport;
    }

    /**
     * Create customs declaration
     */
    public function createCustomsDeclaration($declarationData, $createdBy)
    {
        $this->logger->info("Creating customs declaration", $declarationData);

        $stmt = $this->db->prepare("
            SELECT generate_customs_declaration(?, ?)
        ");

        $stmt->execute([
            json_encode($declarationData['passenger_data'] ?? []),
            json_encode($declarationData['goods_data'] ?? [])
        ]);

        return [
            'declaration_id' => $stmt->fetchColumn(),
            'status' => 'created',
            'message' => 'Customs declaration created successfully'
        ];
    }

    /**
     * Get customs declarations
     */
    public function getCustomsDeclarations($filters = [])
    {
        $whereClause = "";
        $params = [];

        if (isset($filters['status'])) {
            $whereClause .= " AND declaration_status = ?";
            $params[] = $filters['status'];
        }

        if (isset($filters['passport_id'])) {
            $whereClause .= " AND passport_id = ?";
            $params[] = $filters['passport_id'];
        }

        if (isset($filters['flight_number'])) {
            $whereClause .= " AND flight_number = ?";
            $params[] = $filters['flight_number'];
        }

        if (isset($filters['start_date'])) {
            $whereClause .= " AND DATE(created_at) >= ?";
            $params[] = $filters['start_date'];
        }

        if (isset($filters['end_date'])) {
            $whereClause .= " AND DATE(created_at) <= ?";
            $params[] = $filters['end_date'];
        }

        $stmt = $this->db->prepare("
            SELECT
                cd.*,
                pd.passport_number,
                pd.holder_name,
                pd.holder_nationality,
                (
                    SELECT COUNT(*)
                    FROM customs_inspection_reports
                    WHERE declaration_id = cd.declaration_id
                ) as inspection_count
            FROM customs_declarations cd
            LEFT JOIN passport_data pd ON cd.passport_id = pd.passport_id
            WHERE 1=1 $whereClause
            ORDER BY cd.created_at DESC
            LIMIT ?
        ");

        $params[] = $filters['limit'] ?? 50;
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Process border entry
     */
    public function processBorderEntry($entryData, $processedBy)
    {
        $this->logger->info("Processing border entry", $entryData);

        $stmt = $this->db->prepare("
            SELECT process_border_entry(?)
        ");

        $stmt->execute([json_encode($entryData)]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return json_decode($result['process_border_entry'], true);
    }

    /**
     * Get border control entries
     */
    public function getBorderEntries($filters = [])
    {
        $whereClause = "";
        $params = [];

        if (isset($filters['status'])) {
            $whereClause .= " AND entry_status = ?";
            $params[] = $filters['status'];
        }

        if (isset($filters['entry_type'])) {
            $whereClause .= " AND entry_type = ?";
            $params[] = $filters['entry_type'];
        }

        if (isset($filters['port_of_entry'])) {
            $whereClause .= " AND port_of_entry = ?";
            $params[] = $filters['port_of_entry'];
        }

        if (isset($filters['start_date'])) {
            $whereClause .= " AND DATE(entry_timestamp) >= ?";
            $params[] = $filters['start_date'];
        }

        if (isset($filters['end_date'])) {
            $whereClause .= " AND DATE(entry_timestamp) <= ?";
            $params[] = $filters['end_date'];
        }

        $stmt = $this->db->prepare("
            SELECT
                bce.*,
                pd.passport_number,
                pd.holder_name,
                pd.holder_nationality,
                calculate_border_processing_time(bce.entry_id) as processing_time_minutes
            FROM border_control_entries bce
            LEFT JOIN passport_data pd ON bce.passport_id = pd.passport_id
            WHERE 1=1 $whereClause
            ORDER BY bce.entry_timestamp DESC
            LIMIT ?
        ");

        $params[] = $filters['limit'] ?? 50;
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Record biometric data
     */
    public function recordBiometricData($biometricData)
    {
        $this->logger->info("Recording biometric data", $biometricData);

        $stmt = $this->db->prepare("
            INSERT INTO biometric_data (
                biometric_id, passport_id, biometric_type, biometric_data,
                capture_device, capture_location, quality_score, verification_status,
                encryption_key_id, retention_period_days, privacy_consent
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $biometricId = $this->generateBiometricId();

        $stmt->execute([
            $biometricId,
            $biometricData['passport_id'],
            $biometricData['biometric_type'],
            $biometricData['biometric_data'], // This should be encrypted
            $biometricData['capture_device'] ?? null,
            $biometricData['capture_location'] ?? null,
            $biometricData['quality_score'] ?? null,
            $biometricData['verification_status'] ?? 'captured',
            $biometricData['encryption_key_id'] ?? null,
            $biometricData['retention_period_days'] ?? 365,
            $biometricData['privacy_consent'] ?? true
        ]);

        return [
            'biometric_id' => $biometricId,
            'status' => 'recorded',
            'message' => 'Biometric data recorded successfully'
        ];
    }

    /**
     * Get biometric data
     */
    public function getBiometricData($passportId, $biometricType = null)
    {
        $whereClause = "WHERE passport_id = ?";
        $params = [$passportId];

        if ($biometricType) {
            $whereClause .= " AND biometric_type = ?";
            $params[] = $biometricType;
        }

        $stmt = $this->db->prepare("
            SELECT * FROM biometric_data
            $whereClause
            ORDER BY capture_timestamp DESC
        ");

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Add to security watchlist
     */
    public function addToWatchlist($watchlistData)
    {
        $this->logger->info("Adding to security watchlist", $watchlistData);

        $stmt = $this->db->prepare("
            INSERT INTO security_watchlist (
                watchlist_id, individual_name, aliases, passport_numbers,
                nationalities, date_of_birth, threat_level, threat_category,
                issuing_authority, issue_date, expiry_date, alert_description,
                action_required
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $watchlistId = $this->generateWatchlistId();

        $stmt->execute([
            $watchlistId,
            $watchlistData['individual_name'],
            isset($watchlistData['aliases']) ? json_encode($watchlistData['aliases']) : '[]',
            isset($watchlistData['passport_numbers']) ? json_encode($watchlistData['passport_numbers']) : '[]',
            isset($watchlistData['nationalities']) ? json_encode($watchlistData['nationalities']) : '[]',
            $watchlistData['date_of_birth'] ?? null,
            $watchlistData['threat_level'] ?? 'low',
            $watchlistData['threat_category'] ?? null,
            $watchlistData['issuing_authority'],
            $watchlistData['issue_date'] ?? date('Y-m-d'),
            $watchlistData['expiry_date'] ?? null,
            $watchlistData['alert_description'] ?? null,
            $watchlistData['action_required'] ?? 'additional_screening'
        ]);

        return [
            'watchlist_id' => $watchlistId,
            'status' => 'added',
            'message' => 'Individual added to security watchlist successfully'
        ];
    }

    /**
     * Get security watchlist
     */
    public function getWatchlist($activeOnly = true, $threatLevel = null)
    {
        $whereClause = $activeOnly ? "WHERE active = true" : "";
        $params = [];

        if ($threatLevel) {
            $whereClause .= ($activeOnly ? " AND" : "WHERE") . " threat_level = ?";
            $params[] = $threatLevel;
        }

        $stmt = $this->db->prepare("
            SELECT * FROM security_watchlist
            $whereClause
            ORDER BY threat_level DESC, issue_date DESC
        ");

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Create visa application
     */
    public function createVisaApplication($applicationData, $applicantId)
    {
        $this->logger->info("Creating visa application", $applicationData);

        $stmt = $this->db->prepare("
            INSERT INTO visa_applications (
                application_id, application_number, passport_id, applicant_name,
                applicant_nationality, visa_type, visa_subtype, purpose_of_visit,
                intended_entry_date, intended_stay_days, accommodation_details,
                financial_documents, invitation_letter, invitation_details,
                employment_details, education_details, visa_fee, processing_fee,
                service_fee
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $applicationId = $this->generateApplicationId();
        $applicationNumber = $this->generateApplicationNumber();

        $stmt->execute([
            $applicationId,
            $applicationNumber,
            $applicationData['passport_id'],
            $applicationData['applicant_name'],
            $applicationData['applicant_nationality'],
            $applicationData['visa_type'],
            $applicationData['visa_subtype'] ?? null,
            $applicationData['purpose_of_visit'] ?? null,
            $applicationData['intended_entry_date'] ?? null,
            $applicationData['intended_stay_days'] ?? null,
            isset($applicationData['accommodation_details']) ? json_encode($applicationData['accommodation_details']) : null,
            isset($applicationData['financial_documents']) ? json_encode($applicationData['financial_documents']) : '[]',
            $applicationData['invitation_letter'] ?? false,
            isset($applicationData['invitation_details']) ? json_encode($applicationData['invitation_details']) : null,
            isset($applicationData['employment_details']) ? json_encode($applicationData['employment_details']) : null,
            isset($applicationData['education_details']) ? json_encode($applicationData['education_details']) : null,
            $applicationData['visa_fee'] ?? null,
            $applicationData['processing_fee'] ?? null,
            $applicationData['service_fee'] ?? null
        ]);

        return [
            'application_id' => $applicationId,
            'application_number' => $applicationNumber,
            'status' => 'submitted',
            'message' => 'Visa application created successfully'
        ];
    }

    /**
     * Get visa applications
     */
    public function getVisaApplications($status = null, $visaType = null)
    {
        $whereClause = "";
        $params = [];

        if ($status) {
            $whereClause .= " AND application_status = ?";
            $params[] = $status;
        }

        if ($visaType) {
            $whereClause .= " AND visa_type = ?";
            $params[] = $visaType;
        }

        $stmt = $this->db->prepare("
            SELECT
                va.*,
                pd.passport_number,
                pd.holder_name
            FROM visa_applications va
            LEFT JOIN passport_data pd ON va.passport_id = pd.passport_id
            WHERE 1=1 $whereClause
            ORDER BY va.submission_date DESC
        ");

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Process visa application
     */
    public function processVisaApplication($applicationId, $decision, $officerId, $notes = null)
    {
        $stmt = $this->db->prepare("
            UPDATE visa_applications
            SET application_status = ?, review_officer_id = ?, review_date = CURRENT_TIMESTAMP,
                approval_date = CASE WHEN ? = 'approved' THEN CURRENT_TIMESTAMP ELSE NULL END,
                rejection_reason = CASE WHEN ? = 'rejected' THEN ? ELSE NULL END
            WHERE application_id = ?
        ");

        $stmt->execute([$decision, $officerId, $decision, $decision, $notes, $applicationId]);

        return [
            'application_id' => $applicationId,
            'status' => $decision,
            'message' => 'Visa application processed successfully'
        ];
    }

    /**
     * Record customs inspection
     */
    public function recordInspection($inspectionData, $inspectorId)
    {
        $this->logger->info("Recording customs inspection", $inspectionData);

        $stmt = $this->db->prepare("
            INSERT INTO customs_inspection_reports (
                inspection_id, inspection_number, declaration_id, inspection_type,
                inspection_reason, inspector_id, inspection_location, goods_examined,
                prohibited_items_found, restricted_items_found, seized_items,
                estimated_value, violation_type, fine_amount, penalty_assessed,
                legal_action_required, inspection_notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $inspectionId = $this->generateInspectionId();
        $inspectionNumber = $this->generateInspectionNumber();

        $stmt->execute([
            $inspectionId,
            $inspectionNumber,
            $inspectionData['declaration_id'],
            $inspectionData['inspection_type'] ?? 'routine',
            $inspectionData['inspection_reason'] ?? null,
            $inspectorId,
            $inspectionData['inspection_location'] ?? null,
            isset($inspectionData['goods_examined']) ? json_encode($inspectionData['goods_examined']) : '[]',
            isset($inspectionData['prohibited_items_found']) ? json_encode($inspectionData['prohibited_items_found']) : '[]',
            isset($inspectionData['restricted_items_found']) ? json_encode($inspectionData['restricted_items_found']) : '[]',
            isset($inspectionData['seized_items']) ? json_encode($inspectionData['seized_items']) : '[]',
            $inspectionData['estimated_value'] ?? null,
            $inspectionData['violation_type'] ?? null,
            $inspectionData['fine_amount'] ?? null,
            $inspectionData['penalty_assessed'] ?? false,
            $inspectionData['legal_action_required'] ?? false,
            $inspectionData['inspection_notes'] ?? null
        ]);

        // Update declaration status
        $stmt = $this->db->prepare("
            UPDATE customs_declarations
            SET declaration_status = 'inspected', inspection_date = CURRENT_TIMESTAMP,
                inspector_id = ?, inspection_notes = ?
            WHERE declaration_id = ?
        ");
        $stmt->execute([$inspectorId, $inspectionData['inspection_notes'] ?? null, $inspectionData['declaration_id']]);

        return [
            'inspection_id' => $inspectionId,
            'inspection_number' => $inspectionNumber,
            'status' => 'recorded',
            'message' => 'Customs inspection recorded successfully'
        ];
    }

    /**
     * Get customs inspections
     */
    public function getInspections($startDate = null, $endDate = null, $violationType = null)
    {
        $whereClause = "";
        $params = [];

        if ($startDate) {
            $whereClause .= " AND DATE(inspection_start) >= ?";
            $params[] = $startDate;
        }

        if ($endDate) {
            $whereClause .= " AND DATE(inspection_start) <= ?";
            $params[] = $endDate;
        }

        if ($violationType) {
            $whereClause .= " AND violation_type = ?";
            $params[] = $violationType;
        }

        $stmt = $this->db->prepare("
            SELECT
                cir.*,
                cd.declaration_number,
                pd.passport_number,
                pd.holder_name
            FROM customs_inspection_reports cir
            LEFT JOIN customs_declarations cd ON cir.declaration_id = cd.declaration_id
            LEFT JOIN passport_data pd ON cd.passport_id = pd.passport_id
            WHERE 1=1 $whereClause
            ORDER BY cir.inspection_start DESC
        ");

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Record border security incident
     */
    public function recordSecurityIncident($incidentData, $reportedBy)
    {
        $this->logger->info("Recording security incident", $incidentData);

        $stmt = $this->db->prepare("
            INSERT INTO border_security_incidents (
                incident_id, incident_number, incident_type, severity_level,
                incident_location, reporting_officer_id, involved_passports,
                involved_individuals, contraband_details, estimated_value,
                arrest_made, arrest_details, investigation_required,
                investigation_officer_id, incident_description, response_actions
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $incidentId = $this->generateIncidentId();
        $incidentNumber = $this->generateIncidentNumber();

        $stmt->execute([
            $incidentId,
            $incidentNumber,
            $incidentData['incident_type'],
            $incidentData['severity_level'] ?? 'low',
            $incidentData['incident_location'] ?? null,
            $reportedBy,
            isset($incidentData['involved_passports']) ? json_encode($incidentData['involved_passports']) : '[]',
            isset($incidentData['involved_individuals']) ? json_encode($incidentData['involved_individuals']) : '[]',
            isset($incidentData['contraband_details']) ? json_encode($incidentData['contraband_details']) : '[]',
            $incidentData['estimated_value'] ?? null,
            $incidentData['arrest_made'] ?? false,
            isset($incidentData['arrest_details']) ? json_encode($incidentData['arrest_details']) : null,
            $incidentData['investigation_required'] ?? true,
            $incidentData['investigation_officer_id'] ?? null,
            $incidentData['incident_description'],
            isset($incidentData['response_actions']) ? json_encode($incidentData['response_actions']) : '[]'
        ]);

        return [
            'incident_id' => $incidentId,
            'incident_number' => $incidentNumber,
            'status' => 'reported',
            'message' => 'Security incident recorded successfully'
        ];
    }

    /**
     * Get security incidents
     */
    public function getSecurityIncidents($type = null, $severity = null, $startDate = null, $endDate = null)
    {
        $whereClause = "";
        $params = [];

        if ($type) {
            $whereClause .= " AND incident_type = ?";
            $params[] = $type;
        }

        if ($severity) {
            $whereClause .= " AND severity_level = ?";
            $params[] = $severity;
        }

        if ($startDate) {
            $whereClause .= " AND DATE(incident_date) >= ?";
            $params[] = $startDate;
        }

        if ($endDate) {
            $whereClause .= " AND DATE(incident_date) <= ?";
            $params[] = $endDate;
        }

        $stmt = $this->db->prepare("
            SELECT * FROM border_security_incidents
            WHERE 1=1 $whereClause
            ORDER BY incident_date DESC
        ");

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Record document verification
     */
    public function recordDocumentVerification($verificationData, $officerId)
    {
        $this->logger->info("Recording document verification", $verificationData);

        $stmt = $this->db->prepare("
            INSERT INTO document_verification (
                verification_id, passport_id, verification_type, verification_device,
                verification_officer_id, verification_result, authenticity_score,
                security_features_checked, anomalies_detected, verification_notes,
                requires_additional_check
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $verificationId = $this->generateVerificationId();

        $stmt->execute([
            $verificationId,
            $verificationData['passport_id'],
            $verificationData['verification_type'] ?? 'visual',
            $verificationData['verification_device'] ?? null,
            $officerId,
            $verificationData['verification_result'] ?? 'valid',
            $verificationData['authenticity_score'] ?? null,
            isset($verificationData['security_features_checked']) ? json_encode($verificationData['security_features_checked']) : '[]',
            isset($verificationData['anomalies_detected']) ? json_encode($verificationData['anomalies_detected']) : '[]',
            $verificationData['verification_notes'] ?? null,
            $verificationData['requires_additional_check'] ?? false
        ]);

        return [
            'verification_id' => $verificationId,
            'status' => 'recorded',
            'message' => 'Document verification recorded successfully'
        ];
    }

    /**
     * Get document verifications
     */
    public function getDocumentVerifications($passportId = null, $result = null, $startDate = null, $endDate = null)
    {
        $whereClause = "";
        $params = [];

        if ($passportId) {
            $whereClause .= " AND passport_id = ?";
            $params[] = $passportId;
        }

        if ($result) {
            $whereClause .= " AND verification_result = ?";
            $params[] = $result;
        }

        if ($startDate) {
            $whereClause .= " AND DATE(verification_timestamp) >= ?";
            $params[] = $startDate;
        }

        if ($endDate) {
            $whereClause .= " AND DATE(verification_timestamp) <= ?";
            $params[] = $endDate;
        }

        $stmt = $this->db->prepare("
            SELECT
                dv.*,
                pd.passport_number,
                pd.holder_name
            FROM document_verification dv
            LEFT JOIN passport_data pd ON dv.passport_id = pd.passport_id
            WHERE 1=1 $whereClause
            ORDER BY dv.verification_timestamp DESC
        ");

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Validate passport
     */
    public function validatePassport($passportNumber)
    {
        $stmt = $this->db->prepare("
            SELECT validate_passport(?)
        ");

        $stmt->execute([$passportNumber]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return json_decode($result['validate_passport'], true);
    }

    /**
     * Check visa validity
     */
    public function checkVisaValidity($passportId, $entryDate)
    {
        $stmt = $this->db->prepare("
            SELECT check_visa_validity(?, ?)
        ");

        $stmt->execute([$passportId, $entryDate]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return json_decode($result['check_visa_validity'], true);
    }

    /**
     * Get border control dashboard
     */
    public function getDashboardData()
    {
        $stmt = $this->db->prepare("
            SELECT json_build_object(
                'today_entries', (
                    SELECT COUNT(*) FROM border_control_entries
                    WHERE DATE(entry_timestamp) = CURRENT_DATE
                    AND entry_status = 'approved'
                ),
                'today_departures', (
                    SELECT COUNT(*) FROM border_control_entries
                    WHERE DATE(exit_timestamp) = CURRENT_DATE
                ),
                'pending_entries', (
                    SELECT COUNT(*) FROM border_control_entries
                    WHERE entry_status = 'processing'
                ),
                'denied_entries_today', (
                    SELECT COUNT(*) FROM border_control_entries
                    WHERE DATE(entry_timestamp) = CURRENT_DATE
                    AND entry_status = 'denied'
                ),
                'active_watchlist_alerts', (
                    SELECT COUNT(*) FROM security_watchlist
                    WHERE active = true
                ),
                'pending_visa_applications', (
                    SELECT COUNT(*) FROM visa_applications
                    WHERE application_status = 'submitted'
                ),
                'customs_inspections_today', (
                    SELECT COUNT(*) FROM customs_inspection_reports
                    WHERE DATE(inspection_start) = CURRENT_DATE
                ),
                'security_incidents_this_week', (
                    SELECT COUNT(*) FROM border_security_incidents
                    WHERE incident_date >= CURRENT_DATE - INTERVAL '7 days'
                ),
                'average_processing_time', (
                    SELECT ROUND(AVG(calculate_border_processing_time(entry_id)), 2)
                    FROM border_control_entries
                    WHERE DATE(entry_timestamp) = CURRENT_DATE
                    AND entry_status = 'approved'
                ),
                'biometric_verification_rate', (
                    SELECT ROUND(
                        (SELECT COUNT(*) FROM document_verification
                         WHERE DATE(verification_timestamp) = CURRENT_DATE
                         AND verification_result = 'valid')::DECIMAL /
                        (SELECT COUNT(*) FROM document_verification
                         WHERE DATE(verification_timestamp) = CURRENT_DATE)::DECIMAL * 100, 2
                    )
                ),
                'recent_entries', (
                    SELECT json_agg(
                        json_build_object(
                            'entry_id', entry_id,
                            'passport_number', pd.passport_number,
                            'holder_name', pd.holder_name,
                            'nationality', pd.holder_nationality,
                            'entry_timestamp', entry_timestamp,
                            'purpose_of_visit', purpose_of_visit
                        )
                    )
                    FROM border_control_entries bce
                    JOIN passport_data pd ON bce.passport_id = pd.passport_id
                    WHERE DATE(entry_timestamp) = CURRENT_DATE
                    AND entry_status = 'approved'
                    ORDER BY entry_timestamp DESC
                    LIMIT 10
                )
            ) as dashboard_data
        ");

        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return json_decode($result['dashboard_data'], true);
    }

    /**
     * Generate border control report
     */
    public function generateBorderControlReport($startDate, $endDate)
    {
        $stmt = $this->db->prepare("
            SELECT json_build_object(
                'period', json_build_object('start_date', p_start_date, 'end_date', p_end_date),
                'border_activity', json_build_object(
                    'total_entries', (
                        SELECT COUNT(*) FROM border_control_entries
                        WHERE DATE(entry_timestamp) BETWEEN ? AND ?
                        AND entry_status = 'approved'
                    ),
                    'total_departures', (
                        SELECT COUNT(*) FROM border_control_entries
                        WHERE DATE(exit_timestamp) BETWEEN ? AND ?
                    ),
                    'denied_entries', (
                        SELECT COUNT(*) FROM border_control_entries
                        WHERE DATE(entry_timestamp) BETWEEN ? AND ?
                        AND entry_status = 'denied'
                    ),
                    'by_nationality', (
                        SELECT json_agg(
                            json_build_object(
                                'nationality', nationality,
                                'entries', COUNT(*),
                                'percentage', ROUND(COUNT(*)::DECIMAL /
                                    (SELECT COUNT(*) FROM border_control_entries
                                     WHERE DATE(entry_timestamp) BETWEEN ? AND ?
                                     AND entry_status = 'approved') * 100, 2)
                            )
                        )
                        FROM border_control_entries
                        WHERE DATE(entry_timestamp) BETWEEN ? AND ?
                        AND entry_status = 'approved'
                        GROUP BY nationality
                        ORDER BY COUNT(*) DESC
                        LIMIT 10
                    )
                ),
                'security_metrics', json_build_object(
                    'watchlist_intercepts', (
                        SELECT COUNT(*) FROM border_control_entries bce
                        JOIN passport_data pd ON bce.passport_id = pd.passport_id
                        JOIN security_watchlist sw ON pd.holder_name = sw.individual_name
                        WHERE DATE(bce.entry_timestamp) BETWEEN ? AND ?
                        AND sw.active = true
                    ),
                    'security_incidents', (
                        SELECT COUNT(*) FROM border_security_incidents
                        WHERE DATE(incident_date) BETWEEN ? AND ?
                    ),
                    'document_fraud_cases', (
                        SELECT COUNT(*) FROM document_verification
                        WHERE DATE(verification_timestamp) BETWEEN ? AND ?
                        AND verification_result IN ('invalid', 'suspect')
                    )
                ),
                'customs_performance', json_build_object(
                    'declarations_processed', (
                        SELECT COUNT(*) FROM customs_declarations
                        WHERE DATE(created_at) BETWEEN ? AND ?
                    ),
                    'inspections_conducted', (
                        SELECT COUNT(*) FROM customs_inspection_reports
                        WHERE DATE(inspection_start) BETWEEN ? AND ?
                    ),
                    'violations_detected', (
                        SELECT COUNT(*) FROM customs_inspection_reports
                        WHERE DATE(inspection_start) BETWEEN ? AND ?
                        AND violation_type IS NOT NULL
                    ),
                    'revenue_collected', (
                        SELECT SUM(duty_amount + tax_amount) FROM customs_declarations
                        WHERE DATE(created_at) BETWEEN ? AND ?
                        AND declaration_status = 'cleared'
                    )
                ),
                'immigration_status', json_build_object(
                    'visa_applications_processed', (
                        SELECT COUNT(*) FROM visa_applications
                        WHERE DATE(submission_date) BETWEEN ? AND ?
                        AND application_status IN ('approved', 'rejected')
                    ),
                    'overstayed_cases', (
                        SELECT COUNT(*) FROM immigration_records
                        WHERE overstayed = true
                        AND entry_date BETWEEN ? AND ?
                    ),
                    'deportation_orders', (
                        SELECT COUNT(*) FROM immigration_enforcement
                        WHERE DATE(enforcement_date) BETWEEN ? AND ?
                        AND enforcement_type = 'deportation'
                    )
                ),
                'processing_efficiency', json_build_object(
                    'average_processing_time', (
                        SELECT ROUND(AVG(calculate_border_processing_time(entry_id)), 2)
                        FROM border_control_entries
                        WHERE DATE(entry_timestamp) BETWEEN ? AND ?
                        AND entry_status = 'approved'
                    ),
                    'biometric_success_rate', (
                        SELECT ROUND(
                            (SELECT COUNT(*) FROM document_verification
                             WHERE DATE(verification_timestamp) BETWEEN ? AND ?
                             AND verification_result = 'valid')::DECIMAL /
                            (SELECT COUNT(*) FROM document_verification
                             WHERE DATE(verification_timestamp) BETWEEN ? AND ?)::DECIMAL * 100, 2
                        )
                    ),
                    'peak_hour_performance', (
                        SELECT json_agg(
                            json_build_object(
                                'hour', EXTRACT(HOUR FROM entry_timestamp),
                                'entries', COUNT(*),
                                'avg_processing_time', ROUND(AVG(calculate_border_processing_time(entry_id)), 2)
                            )
                        )
                        FROM border_control_entries
                        WHERE DATE(entry_timestamp) BETWEEN ? AND ?
                        AND entry_status = 'approved'
                        GROUP BY EXTRACT(HOUR FROM entry_timestamp)
                        ORDER BY EXTRACT(HOUR FROM entry_timestamp)
                    )
                )
            ) as report_data
            FROM (SELECT ? as p_start_date, ? as p_end_date) params
        ");

        $params = array_fill(0, 32, $startDate);
        $params = array_merge($params, array_fill(0, 32, $endDate));

        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return json_decode($result['report_data'], true);
    }

    // Helper methods

    private function generatePassportId()
    {
        return 'PASS-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
    }

    private function generateBiometricId()
    {
        return 'BIO-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
    }

    private function generateWatchlistId()
    {
        return 'WATCH-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
    }

    private function generateApplicationId()
    {
        return 'APP-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
    }

    private function generateInspectionId()
    {
        return 'INSP-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
    }

    private function generateIncidentId()
    {
        return 'INC-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
    }

    private function generateVerificationId()
    {
        return 'VER-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
    }

    private function generateApplicationNumber()
    {
        return 'VA-' . date('YmdHis');
    }

    private function generateInspectionNumber()
    {
        return 'CI-' . date('YmdHis');
    }

    private function generateIncidentNumber()
    {
        return 'BSI-' . date('YmdHis');
    }
}
