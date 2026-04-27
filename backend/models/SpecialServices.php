<?php

/**
 * Special Services Model
 *
 * Manages accessibility services, special assistance, medical support, and passenger care
 */

class SpecialServices
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
        $this->logger = new Logger('special_services');
    }

    /**
     * Request special assistance
     */
    public function requestAssistance($requestData)
    {
        $this->logger->info("Requesting special assistance", $requestData);

        $stmt = $this->db->prepare("
            INSERT INTO special_assistance_requests (
                passenger_id, booking_id, flight_id, assistance_type,
                assistance_level, special_requirements, medical_conditions,
                mobility_aids, communication_needs, companion_details,
                estimated_duration_minutes, requested_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $requestData['passenger_id'],
            $requestData['booking_id'] ?? null,
            $requestData['flight_id'] ?? null,
            $requestData['assistance_type'],
            $requestData['assistance_level'] ?? 'standard',
            $requestData['special_requirements'] ?? null,
            $requestData['medical_conditions'] ?? null,
            isset($requestData['mobility_aids']) ? json_encode($requestData['mobility_aids']) : '[]',
            isset($requestData['communication_needs']) ? json_encode($requestData['communication_needs']) : '{}',
            isset($requestData['companion_details']) ? json_encode($requestData['companion_details']) : '{}',
            $requestData['estimated_duration_minutes'] ?? null,
            $requestData['requested_by'] ?? 'passenger'
        ]);

        $requestId = $this->db->lastInsertId();

        // Auto-assign staff if possible
        $this->autoAssignStaff($requestId, $requestData['assistance_type']);

        return [
            'request_id' => $requestId,
            'status' => 'requested',
            'assistance_type' => $requestData['assistance_type'],
            'message' => 'Special assistance request submitted successfully'
        ];
    }

    /**
     * Get passenger assistance requests
     */
    public function getPassengerRequests($passengerId, $status = null)
    {
        $whereClause = "WHERE sar.passenger_id = ?";
        $params = [$passengerId];

        if ($status) {
            $whereClause .= " AND sar.request_status = ?";
            $params[] = $status;
        }

        $stmt = $this->db->prepare("
            SELECT
                sar.*,
                f.flight_number,
                f.origin,
                f.destination,
                f.departure_time,
                f.arrival_time,
                sa.staff_member_name,
                sa.assignment_status,
                sa.service_location
            FROM special_assistance_requests sar
            LEFT JOIN flights f ON sar.flight_id = f.flight_id
            LEFT JOIN service_assignments sa ON sar.request_id = sa.request_id
            $whereClause
            ORDER BY sar.requested_at DESC
        ");

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Update request status
     */
    public function updateRequestStatus($requestId, $status, $updatedBy)
    {
        $this->logger->info("Updating request status", [
            'request_id' => $requestId,
            'status' => $status,
            'updated_by' => $updatedBy
        ]);

        $stmt = $this->db->prepare("
            UPDATE special_assistance_requests
            SET request_status = ?, confirmed_by = ?, confirmed_at = CURRENT_TIMESTAMP
            WHERE request_id = ?
        ");

        $stmt->execute([$status, $updatedBy, $requestId]);

        return ['status' => 'updated', 'request_id' => $requestId];
    }

    /**
     * Assign staff to request
     */
    public function assignStaff($assignmentData)
    {
        $this->logger->info("Assigning staff to request", $assignmentData);

        $stmt = $this->db->prepare("
            INSERT INTO service_assignments (
                request_id, service_id, staff_member_id, staff_member_name,
                service_location, equipment_used, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $assignmentData['request_id'],
            $assignmentData['service_id'] ?? null,
            $assignmentData['staff_member_id'],
            $assignmentData['staff_member_name'],
            $assignmentData['service_location'] ?? null,
            isset($assignmentData['equipment_used']) ? json_encode($assignmentData['equipment_used']) : '[]',
            $assignmentData['notes'] ?? null
        ]);

        $assignmentId = $this->db->lastInsertId();

        // Update staff assignment count
        $stmt = $this->db->prepare("
            UPDATE special_services_staff
            SET assigned_requests = assigned_requests + 1
            WHERE staff_id = ?
        ");

        $stmt->execute([$assignmentData['staff_member_id']]);

        return [
            'assignment_id' => $assignmentId,
            'status' => 'assigned',
            'staff_member' => $assignmentData['staff_member_name']
        ];
    }

    /**
     * Complete service assignment
     */
    public function completeAssignment($assignmentId, $completionData)
    {
        $stmt = $this->db->prepare("
            UPDATE service_assignments
            SET assignment_status = 'completed',
                started_at = ?,
                completed_at = ?,
                service_duration_minutes = ?,
                passenger_feedback = ?,
                staff_feedback = ?
            WHERE assignment_id = ?
        ");

        $stmt->execute([
            $completionData['started_at'] ?? null,
            $completionData['completed_at'] ?? date('Y-m-d H:i:s'),
            $completionData['service_duration_minutes'] ?? null,
            $completionData['passenger_feedback'] ?? null,
            $completionData['staff_feedback'] ?? null,
            $assignmentId
        ]);

        // Update staff completed requests count
        $stmt = $this->db->prepare("
            UPDATE special_services_staff
            SET assigned_requests = GREATEST(assigned_requests - 1, 0),
                completed_requests = completed_requests + 1
            WHERE staff_id = (
                SELECT staff_member_id FROM service_assignments WHERE assignment_id = ?
            )
        ");

        $stmt->execute([$assignmentId]);

        return ['status' => 'completed', 'assignment_id' => $assignmentId];
    }

    /**
     * Get accessibility services
     */
    public function getAccessibilityServices($serviceType = null, $terminal = null)
    {
        $whereClause = "WHERE asv.is_active = true";
        $params = [];

        if ($serviceType) {
            $whereClause .= " AND asv.service_type = ?";
            $params[] = $serviceType;
        }

        if ($terminal) {
            $whereClause .= " AND asv.terminal = ?";
            $params[] = $terminal;
        }

        $stmt = $this->db->prepare("
            SELECT
                asv.*,
                (
                    SELECT COUNT(*)
                    FROM service_assignments sa
                    WHERE sa.service_id = asv.service_id
                    AND sa.assignment_status IN ('assigned', 'in_progress')
                ) as current_assignments
            FROM accessibility_services asv
            $whereClause
            ORDER BY asv.service_type, asv.location
        ");

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get mobility equipment availability
     */
    public function getMobilityEquipment($equipmentType = null, $terminal = null, $status = 'available')
    {
        $whereClause = "";
        $params = [];

        if ($equipmentType) {
            $whereClause .= " AND equipment_type = ?";
            $params[] = $equipmentType;
        }

        if ($terminal) {
            $whereClause .= " AND terminal = ?";
            $params[] = $terminal;
        }

        if ($status) {
            $whereClause .= " AND status = ?";
            $params[] = $status;
        }

        $stmt = $this->db->prepare("
            SELECT * FROM mobility_equipment
            WHERE 1=1 $whereClause
            ORDER BY equipment_type, condition_rating DESC
        ");

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Assign mobility equipment
     */
    public function assignEquipment($equipmentId, $assignedTo, $assignmentData = [])
    {
        $this->logger->info("Assigning mobility equipment", [
            'equipment_id' => $equipmentId,
            'assigned_to' => $assignedTo
        ]);

        $stmt = $this->db->prepare("
            UPDATE mobility_equipment
            SET status = 'in_use',
                assigned_to = ?,
                assigned_at = CURRENT_TIMESTAMP,
                usage_log = usage_log || ?
            WHERE equipment_id = ?
        ");

        $usageEntry = json_encode([
            'assigned_to' => $assignedTo,
            'assignment_type' => $assignmentData['assignment_type'] ?? 'assistance',
            'assigned_at' => date('Y-m-d H:i:s'),
            'assigned_by' => $assignmentData['assigned_by'] ?? 'system'
        ]);

        $stmt->execute([$assignedTo, $usageEntry, $equipmentId]);

        return [
            'equipment_id' => $equipmentId,
            'status' => 'assigned',
            'assigned_to' => $assignedTo
        ];
    }

    /**
     * Return mobility equipment
     */
    public function returnEquipment($equipmentId, $returnData = [])
    {
        $stmt = $this->db->prepare("
            UPDATE mobility_equipment
            SET status = 'available',
                assigned_to = NULL,
                returned_at = CURRENT_TIMESTAMP,
                condition_rating = ?,
                usage_log = usage_log || ?
            WHERE equipment_id = ?
        ");

        $returnEntry = json_encode([
            'returned_at' => date('Y-m-d H:i:s'),
            'condition_rating' => $returnData['condition_rating'] ?? null,
            'returned_by' => $returnData['returned_by'] ?? 'system',
            'notes' => $returnData['notes'] ?? null
        ]);

        $stmt->execute([
            $returnData['condition_rating'] ?? null,
            $returnEntry,
            $equipmentId
        ]);

        return [
            'equipment_id' => $equipmentId,
            'status' => 'returned'
        ];
    }

    /**
     * Register unaccompanied minor
     */
    public function registerUnaccompaniedMinor($minorData)
    {
        $this->logger->info("Registering unaccompanied minor", $minorData);

        $stmt = $this->db->prepare("
            INSERT INTO unaccompanied_minors (
                passenger_id, booking_id, flight_id, guardian_name,
                guardian_relationship, guardian_contact, emergency_contact,
                minor_age, special_instructions, identification_documents
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $minorData['passenger_id'],
            $minorData['booking_id'] ?? null,
            $minorData['flight_id'] ?? null,
            $minorData['guardian_name'],
            $minorData['guardian_relationship'] ?? null,
            json_encode($minorData['guardian_contact']),
            json_encode($minorData['emergency_contact']),
            $minorData['minor_age'],
            $minorData['special_instructions'] ?? null,
            isset($minorData['identification_documents']) ? json_encode($minorData['identification_documents']) : '[]'
        ]);

        $minorId = $this->db->lastInsertId();

        // Auto-assign special assistance request
        $this->requestAssistance([
            'passenger_id' => $minorData['passenger_id'],
            'booking_id' => $minorData['booking_id'],
            'flight_id' => $minorData['flight_id'],
            'assistance_type' => 'unaccompanied_minor',
            'assistance_level' => 'complex',
            'special_requirements' => 'Unaccompanied minor - requires special handling and monitoring',
            'requested_by' => 'system'
        ]);

        return [
            'minor_id' => $minorId,
            'status' => 'registered',
            'message' => 'Unaccompanied minor registered successfully'
        ];
    }

    /**
     * Update minor location and status
     */
    public function updateMinorStatus($minorId, $status, $locationData = [])
    {
        $stmt = $this->db->prepare("
            UPDATE unaccompanied_minors
            SET status = ?,
                check_in_time = CASE WHEN ? = 'checked_in' THEN CURRENT_TIMESTAMP ELSE check_in_time END,
                boarding_time = CASE WHEN ? = 'boarding' THEN CURRENT_TIMESTAMP ELSE boarding_time END,
                arrival_time = CASE WHEN ? = 'arrived' THEN CURRENT_TIMESTAMP ELSE arrival_time END,
                delivery_time = CASE WHEN ? = 'delivered' THEN CURRENT_TIMESTAMP ELSE delivery_time END,
                location_tracking = location_tracking || ?
            WHERE minor_id = ?
        ");

        $locationEntry = json_encode([
            'status' => $status,
            'timestamp' => date('Y-m-d H:i:s'),
            'location' => $locationData['location'] ?? null,
            'staff_member' => $locationData['staff_member'] ?? null,
            'notes' => $locationData['notes'] ?? null
        ]);

        $stmt->execute([
            $status,
            $status,
            $status,
            $status,
            $status,
            $locationEntry,
            $minorId
        ]);

        return ['status' => 'updated', 'minor_id' => $minorId];
    }

    /**
     * Request language assistance
     */
    public function requestLanguageAssistance($languageData)
    {
        $this->logger->info("Requesting language assistance", $languageData);

        $stmt = $this->db->prepare("
            INSERT INTO language_services (
                passenger_id, request_id, primary_language, secondary_languages,
                required_services, proficiency_level, urgency_level, service_status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $languageData['passenger_id'],
            $languageData['request_id'] ?? null,
            $languageData['primary_language'],
            isset($languageData['secondary_languages']) ? json_encode($languageData['secondary_languages']) : '[]',
            isset($languageData['required_services']) ? json_encode($languageData['required_services']) : '["translation"]',
            $languageData['proficiency_level'] ?? 'basic',
            $languageData['urgency_level'] ?? 'standard',
            'requested'
        ]);

        $serviceId = $this->db->lastInsertId();

        // Try to assign interpreter
        $this->assignInterpreter($serviceId, $languageData['primary_language']);

        return [
            'service_id' => $serviceId,
            'status' => 'requested',
            'language' => $languageData['primary_language']
        ];
    }

    /**
     * Assign interpreter to language service
     */
    private function assignInterpreter($serviceId, $language)
    {
        $stmt = $this->db->prepare("
            SELECT * FROM special_services_staff
            WHERE staff_role = 'interpreter'
            AND status = 'active'
            AND languages_spoken ? ?
            ORDER BY assigned_requests ASC
            LIMIT 1
        ");

        $stmt->execute([$language]);
        $interpreter = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($interpreter) {
            $stmt = $this->db->prepare("
                UPDATE language_services
                SET assigned_interpreter = ?,
                    interpreter_contact = ?,
                    service_status = 'assigned'
                WHERE service_id = ?
            ");

            $stmt->execute([
                $interpreter['staff_name'],
                json_encode($interpreter['contact_info']),
                $serviceId
            ]);

            // Update interpreter assignment count
            $stmt = $this->db->prepare("
                UPDATE special_services_staff
                SET assigned_requests = assigned_requests + 1
                WHERE staff_id = ?
            ");

            $stmt->execute([$interpreter['staff_id']]);
        }
    }

    /**
     * Record medical assistance
     */
    public function recordMedicalAssistance($medicalData)
    {
        $this->logger->info("Recording medical assistance", $medicalData);

        $stmt = $this->db->prepare("
            INSERT INTO medical_assistance (
                passenger_id, request_id, medical_condition, severity_level,
                symptoms, medications, allergies, medical_history,
                physician_contact, emergency_contact, assistance_required,
                equipment_needed, special_handling_instructions
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $medicalData['passenger_id'],
            $medicalData['request_id'] ?? null,
            $medicalData['medical_condition'],
            $medicalData['severity_level'] ?? 'minor',
            $medicalData['symptoms'] ?? null,
            isset($medicalData['medications']) ? json_encode($medicalData['medications']) : '[]',
            isset($medicalData['allergies']) ? json_encode($medicalData['allergies']) : '[]',
            $medicalData['medical_history'] ?? null,
            isset($medicalData['physician_contact']) ? json_encode($medicalData['physician_contact']) : '{}',
            isset($medicalData['emergency_contact']) ? json_encode($medicalData['emergency_contact']) : '{}',
            $medicalData['assistance_required'] ?? null,
            isset($medicalData['equipment_needed']) ? json_encode($medicalData['equipment_needed']) : '[]',
            $medicalData['special_handling_instructions'] ?? null
        ]);

        return [
            'assistance_id' => $this->db->lastInsertId(),
            'status' => 'recorded',
            'severity' => $medicalData['severity_level']
        ];
    }

    /**
     * Get special services staff
     */
    public function getSpecialServicesStaff($role = null, $status = 'active')
    {
        $whereClause = "WHERE status = ?";
        $params = [$status];

        if ($role) {
            $whereClause .= " AND staff_role = ?";
            $params[] = $role;
        }

        $stmt = $this->db->prepare("
            SELECT
                *,
                (
                    SELECT COUNT(*)
                    FROM service_assignments sa
                    WHERE sa.staff_member_id = sss.staff_id
                    AND sa.assignment_status IN ('assigned', 'in_progress')
                ) as active_assignments
            FROM special_services_staff sss
            $whereClause
            ORDER BY staff_role, staff_name
        ");

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Report service incident
     */
    public function reportIncident($incidentData)
    {
        $this->logger->info("Reporting service incident", $incidentData);

        $stmt = $this->db->prepare("
            INSERT INTO service_incidents (
                request_id, incident_type, severity_level, description,
                root_cause, immediate_actions_taken, corrective_actions,
                reported_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $incidentData['request_id'] ?? null,
            $incidentData['incident_type'],
            $incidentData['severity_level'] ?? 'minor',
            $incidentData['description'],
            $incidentData['root_cause'] ?? null,
            $incidentData['immediate_actions_taken'] ?? null,
            isset($incidentData['corrective_actions']) ? json_encode($incidentData['corrective_actions']) : '[]',
            $incidentData['reported_by'] ?? 'system'
        ]);

        return [
            'incident_id' => $this->db->lastInsertId(),
            'status' => 'reported',
            'severity' => $incidentData['severity_level']
        ];
    }

    /**
     * Get service performance metrics
     */
    public function getPerformanceMetrics($startDate, $endDate, $terminal = null)
    {
        $whereClause = "";
        $params = [$startDate, $endDate];

        if ($terminal) {
            $whereClause = " AND terminal = ?";
            $params[] = $terminal;
        }

        $stmt = $this->db->prepare("
            SELECT * FROM service_performance_metrics
            WHERE date BETWEEN ? AND ?
            $whereClause
            ORDER BY date DESC
        ");

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Schedule staff training
     */
    public function scheduleStaffTraining($trainingData)
    {
        $stmt = $this->db->prepare("
            INSERT INTO staff_training_records (
                staff_id, training_type, training_provider, training_date,
                expiry_date, training_score, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $trainingData['staff_id'],
            $trainingData['training_type'],
            $trainingData['training_provider'] ?? null,
            $trainingData['training_date'],
            $trainingData['expiry_date'] ?? null,
            $trainingData['training_score'] ?? null,
            $trainingData['notes'] ?? null
        ]);

        return [
            'training_id' => $this->db->lastInsertId(),
            'status' => 'scheduled'
        ];
    }

    /**
     * Get accessibility compliance status
     */
    public function getComplianceStatus($facilityArea = null, $terminal = null)
    {
        $whereClause = "";
        $params = [];

        if ($facilityArea) {
            $whereClause .= " AND facility_area = ?";
            $params[] = $facilityArea;
        }

        if ($terminal) {
            $whereClause .= " AND terminal = ?";
            $params[] = $terminal;
        }

        $stmt = $this->db->prepare("
            SELECT * FROM accessibility_compliance
            WHERE 1=1 $whereClause
            ORDER BY last_audit_date DESC
        ");

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get emergency protocols
     */
    public function getEmergencyProtocols($protocolType = null)
    {
        $whereClause = $protocolType ? "WHERE protocol_type = ?" : "";
        $params = $protocolType ? [$protocolType] : [];

        $stmt = $this->db->prepare("
            SELECT * FROM emergency_protocols
            WHERE is_active = true $whereClause
            ORDER BY priority_level DESC, protocol_name
        ");

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Auto-assign staff to request
     */
    private function autoAssignStaff($requestId, $assistanceType)
    {
        $stmt = $this->db->prepare("
            SELECT * FROM special_services_staff
            WHERE status = 'active'
            AND staff_role = CASE
                WHEN ? IN ('wheelchair', 'mobility') THEN 'accessibility_assistant'
                WHEN ? = 'medical' THEN 'medical_assistant'
                WHEN ? IN ('visual', 'hearing') THEN 'accessibility_assistant'
                ELSE 'accessibility_assistant'
            END
            AND assigned_requests < 5
            ORDER BY assigned_requests ASC
            LIMIT 1
        ");

        $stmt->execute([$assistanceType, $assistanceType, $assistanceType]);
        $staff = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($staff) {
            $this->assignStaff([
                'request_id' => $requestId,
                'staff_member_id' => $staff['staff_id'],
                'staff_member_name' => $staff['staff_name']
            ]);
        }
    }

    /**
     * Get special services dashboard data
     */
    public function getDashboardData()
    {
        $stmt = $this->db->prepare("
            SELECT json_build_object(
                'active_requests', (
                    SELECT COUNT(*) FROM special_assistance_requests
                    WHERE request_status IN ('pending', 'confirmed', 'in_progress')
                ),
                'completed_today', (
                    SELECT COUNT(*) FROM special_assistance_requests
                    WHERE request_status = 'completed'
                    AND DATE(updated_at) = CURRENT_DATE
                ),
                'equipment_in_use', (
                    SELECT COUNT(*) FROM mobility_equipment
                    WHERE status = 'in_use'
                ),
                'available_staff', (
                    SELECT COUNT(*) FROM special_services_staff
                    WHERE status = 'active'
                    AND assigned_requests < 5
                ),
                'unaccompanied_minors', (
                    SELECT COUNT(*) FROM unaccompanied_minors
                    WHERE status IN ('checked_in', 'boarding', 'in_flight')
                ),
                'pending_maintenance', (
                    SELECT COUNT(*) FROM equipment_maintenance
                    WHERE maintenance_status = 'scheduled'
                    AND maintenance_date <= CURRENT_DATE + INTERVAL '7 days'
                ),
                'service_utilization', (
                    SELECT json_agg(
                        json_build_object(
                            'service_type', service_type,
                            'utilization_rate', ROUND(
                                (current_utilization::DECIMAL / NULLIF(capacity, 0)) * 100, 2
                            )
                        )
                    )
                    FROM accessibility_services
                    WHERE is_active = true
                ),
                'recent_incidents', (
                    SELECT json_agg(
                        json_build_object(
                            'incident_type', incident_type,
                            'severity_level', severity_level,
                            'reported_at', reported_at
                        )
                    )
                    FROM service_incidents
                    WHERE resolution_status = 'open'
                    ORDER BY reported_at DESC
                    LIMIT 5
                )
            ) as dashboard_data
        ");

        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return json_decode($result['dashboard_data'], true);
    }

    /**
     * Get service availability for a specific type and location
     */
    public function getServiceAvailability($serviceType, $terminal = null)
    {
        $stmt = $this->db->prepare("
            SELECT
                service_id,
                service_name,
                location,
                capacity,
                current_utilization,
                ROUND(
                    ((capacity - current_utilization)::DECIMAL / capacity) * 100, 2
                ) as availability_percentage,
                CASE
                    WHEN current_utilization >= capacity THEN 30
                    ELSE ROUND((current_utilization::DECIMAL / capacity) * 20, 0)
                END as estimated_wait_time
            FROM accessibility_services
            WHERE service_type = ?
            AND is_active = true
            AND (? IS NULL OR terminal = ?)
            ORDER BY availability_percentage DESC
        ");

        $stmt->execute([$serviceType, $terminal, $terminal]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
