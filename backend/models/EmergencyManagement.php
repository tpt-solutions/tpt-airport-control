<?php

/**
 * Emergency Management Model
 *
 * Manages emergency protocols, incident response, crisis management, and disaster recovery
 */

require_once '../integrations/emergency-notification-integration.php';
require_once '../integrations/medical-services-integration.php';

class EmergencyManagement
{
    private $db;
    private $logger;
    private $notificationIntegration;
    private $medicalIntegration;

    public function __construct()
    {
        $this->db = new PDO(
            "pgsql:host=" . getenv('DB_HOST') . ";dbname=" . getenv('DB_DATABASE'),
            getenv('DB_USERNAME'),
            getenv('DB_PASSWORD'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $this->logger = new Logger('emergency_management');
        $this->notificationIntegration = new EmergencyNotificationIntegration();
        $this->medicalIntegration = new MedicalServicesIntegration();
    }

    /**
     * Report an emergency incident
     */
    public function reportIncident($incidentData, $reportedBy)
    {
        $this->logger->info("Reporting emergency incident", $incidentData);

        $this->db->beginTransaction();

        try {
            // Generate incident ID and number
            $incidentId = $this->generateIncidentId();
            $incidentNumber = $this->generateIncidentNumber();

            // Insert incident
            $stmt = $this->db->prepare("
                INSERT INTO incident_reports (
                    incident_id, incident_number, incident_type, severity_level,
                    location, location_coordinates, description, reported_by,
                    affected_areas, weather_conditions, evacuation_required
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $incidentId,
                $incidentNumber,
                $incidentData['incident_type'] ?? 'other',
                $incidentData['severity_level'] ?? 'medium',
                $incidentData['location'] ?? null,
                isset($incidentData['location_coordinates']) ? json_encode($incidentData['location_coordinates']) : null,
                $incidentData['description'] ?? null,
                $reportedBy,
                isset($incidentData['affected_areas']) ? json_encode($incidentData['affected_areas']) : '[]',
                isset($incidentData['weather_conditions']) ? json_encode($incidentData['weather_conditions']) : '{}',
                $incidentData['evacuation_required'] ?? false
            ]);

            // Log incident creation
            $this->logAuditEvent($incidentId, 'incident_created', 'Emergency incident reported', $reportedBy, [
                'incident_number' => $incidentNumber,
                'status' => 'reported'
            ]);

            // Auto-activate appropriate protocol if critical
            if (($incidentData['severity_level'] ?? 'medium') === 'critical') {
                $this->autoActivateProtocol($incidentId, $incidentData['incident_type'] ?? 'other', $reportedBy);
            }

            // Send emergency notifications for critical incidents
            if (($incidentData['severity_level'] ?? 'medium') === 'critical') {
                $this->sendEmergencyNotifications($incidentId, $incidentData, $reportedBy);
            }

            // Request medical assistance for medical emergencies
            if (isset($incidentData['incident_type']) && in_array($incidentData['incident_type'], ['medical', 'fire', 'accident'])) {
                $this->requestMedicalAssistance($incidentId, $incidentData, $reportedBy);
            }

            $this->db->commit();

            return [
                'incident_id' => $incidentId,
                'incident_number' => $incidentNumber,
                'status' => 'reported',
                'message' => 'Emergency incident reported successfully'
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Get incident details
     */
    public function getIncident($incidentId)
    {
        $stmt = $this->db->prepare("
            SELECT
                ir.*,
                ep.protocol_name,
                ep.severity_level as protocol_severity,
                ep.response_procedures,
                (
                    SELECT json_agg(
                        json_build_object(
                            'communication_type', communication_type,
                            'message_content', message_content,
                            'sent_at', sent_at,
                            'sender', sender
                        ) ORDER BY sent_at DESC
                    )
                    FROM emergency_communications
                    WHERE incident_id = ir.incident_id
                ) as communications,
                (
                    SELECT json_agg(
                        json_build_object(
                            'resource_type', resource_type,
                            'resource_name', resource_name,
                            'quantity_allocated', quantity_allocated,
                            'allocated_at', allocated_at
                        ) ORDER BY allocated_at DESC
                    )
                    FROM emergency_resources
                    WHERE incident_id = ir.incident_id
                ) as resources,
                (
                    SELECT json_agg(
                        json_build_object(
                            'alert_type', alert_type,
                            'alert_level', alert_level,
                            'alert_title', alert_title,
                            'sent_at', sent_at
                        ) ORDER BY sent_at DESC
                    )
                    FROM emergency_alerts
                    WHERE incident_id = ir.incident_id
                ) as alerts
            FROM incident_reports ir
            LEFT JOIN emergency_protocols ep ON ir.protocol_used = ep.protocol_id
            WHERE ir.incident_id = ?
        ");

        $stmt->execute([$incidentId]);
        $incident = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$incident) {
            throw new Exception("Incident not found");
        }

        return $incident;
    }

    /**
     * Get incidents with filtering
     */
    public function getIncidents($filters = [])
    {
        $whereClause = "";
        $params = [];

        if (isset($filters['status'])) {
            $whereClause .= " AND ir.incident_status = ?";
            $params[] = $filters['status'];
        }

        if (isset($filters['type'])) {
            $whereClause .= " AND ir.incident_type = ?";
            $params[] = $filters['type'];
        }

        if (isset($filters['severity'])) {
            $whereClause .= " AND ir.severity_level = ?";
            $params[] = $filters['severity'];
        }

        if (isset($filters['start_date'])) {
            $whereClause .= " AND ir.reported_at >= ?";
            $params[] = $filters['start_date'];
        }

        if (isset($filters['end_date'])) {
            $whereClause .= " AND ir.reported_at <= ?";
            $params[] = $filters['end_date'];
        }

        $stmt = $this->db->prepare("
            SELECT
                ir.*,
                ep.protocol_name,
                (
                    SELECT COUNT(*)
                    FROM emergency_communications
                    WHERE incident_id = ir.incident_id
                ) as communication_count,
                (
                    SELECT COUNT(*)
                    FROM emergency_resources
                    WHERE incident_id = ir.incident_id
                ) as resource_count
            FROM incident_reports ir
            LEFT JOIN emergency_protocols ep ON ir.protocol_used = ep.protocol_id
            WHERE 1=1 $whereClause
            ORDER BY ir.reported_at DESC
            LIMIT ?
        ");

        $params[] = $filters['limit'] ?? 50;
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Activate emergency protocol
     */
    public function activateProtocol($incidentId, $protocolId, $activatedBy)
    {
        $this->logger->info("Activating emergency protocol", [
            'incident_id' => $incidentId,
            'protocol_id' => $protocolId
        ]);

        // Get protocol details
        $stmt = $this->db->prepare("SELECT * FROM emergency_protocols WHERE protocol_id = ?");
        $stmt->execute([$protocolId]);
        $protocol = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$protocol) {
            throw new Exception("Emergency protocol not found");
        }

        // Update incident with protocol
        $stmt = $this->db->prepare("
            UPDATE incident_reports
            SET protocol_used = ?, updated_at = CURRENT_TIMESTAMP
            WHERE incident_id = ?
        ");
        $stmt->execute([$protocolId, $incidentId]);

        // Log protocol activation
        $this->logAuditEvent($incidentId, 'protocol_activated',
            'Emergency protocol ' . $protocol['protocol_name'] . ' activated',
            $activatedBy, [
                'protocol_id' => $protocolId,
                'status' => 'activated'
            ]);

        // Update incident status to responding
        $this->updateIncidentStatus($incidentId, 'responding', $activatedBy);

        return [
            'protocol_id' => $protocolId,
            'protocol_name' => $protocol['protocol_name'],
            'severity_level' => $protocol['severity_level'],
            'response_procedures' => json_decode($protocol['response_procedures'], true),
            'activation_time' => date('c')
        ];
    }

    /**
     * Update incident status
     */
    public function updateIncidentStatus($incidentId, $status, $updatedBy)
    {
        $stmt = $this->db->prepare("
            UPDATE incident_reports
            SET incident_status = ?, updated_at = CURRENT_TIMESTAMP
            WHERE incident_id = ?
        ");
        $stmt->execute([$status, $incidentId]);

        // Log status change
        $this->logAuditEvent($incidentId, 'status_changed',
            'Incident status changed to ' . $status,
            $updatedBy, [
                'new_status' => $status,
                'changed_at' => date('c')
            ]);

        // Set timestamps based on status
        $timestampFields = [
            'acknowledged' => 'acknowledged_at',
            'responding' => 'response_started_at',
            'contained' => 'resolved_at',
            'resolved' => 'resolved_at',
            'closed' => 'resolved_at'
        ];

        if (isset($timestampFields[$status])) {
            $stmt = $this->db->prepare("
                UPDATE incident_reports
                SET {$timestampFields[$status]} = CURRENT_TIMESTAMP
                WHERE incident_id = ?
            ");
            $stmt->execute([$incidentId]);
        }
    }

    /**
     * Allocate emergency resource
     */
    public function allocateResource($incidentId, $resourceData, $allocatedBy)
    {
        $this->logger->info("Allocating emergency resource", $resourceData);

        $stmt = $this->db->prepare("
            INSERT INTO emergency_resources (
                incident_id, resource_type, resource_name, quantity_allocated,
                quantity_available, allocated_by
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $incidentId,
            $resourceData['resource_type'],
            $resourceData['resource_name'],
            $resourceData['quantity_allocated'] ?? 1,
            $resourceData['quantity_available'] ?? null,
            $allocatedBy
        ]);

        $resourceId = $this->db->lastInsertId();

        // Log resource allocation
        $this->logAuditEvent($incidentId, 'resource_allocated',
            'Emergency resource allocated: ' . $resourceData['resource_name'],
            $allocatedBy, [
                'resource_type' => $resourceData['resource_type'],
                'resource_name' => $resourceData['resource_name'],
                'quantity' => $resourceData['quantity_allocated'] ?? 1
            ]);

        return [
            'resource_id' => $resourceId,
            'status' => 'allocated',
            'message' => 'Emergency resource allocated successfully'
        ];
    }

    /**
     * Send emergency alert
     */
    public function sendAlert($incidentId, $alertData, $sentBy)
    {
        $this->logger->info("Sending emergency alert", $alertData);

        $stmt = $this->db->prepare("
            INSERT INTO emergency_alerts (
                incident_id, alert_type, alert_level, alert_title, alert_message,
                target_audience, delivery_channels
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $incidentId,
            $alertData['alert_type'] ?? 'internal',
            $alertData['alert_level'] ?? 'warning',
            $alertData['alert_title'],
            $alertData['alert_message'],
            isset($alertData['target_audience']) ? json_encode($alertData['target_audience']) : '[]',
            isset($alertData['delivery_channels']) ? json_encode($alertData['delivery_channels']) : '["pa_system"]'
        ]);

        $alertId = $this->db->lastInsertId();

        // Log alert sending
        $this->logAuditEvent($incidentId, 'alert_sent',
            'Emergency alert sent: ' . $alertData['alert_title'],
            $sentBy, [
                'alert_type' => $alertData['alert_type'] ?? 'internal',
                'channels' => $alertData['delivery_channels'] ?? ['pa_system']
            ]);

        return [
            'alert_id' => $alertId,
            'status' => 'sent',
            'message' => 'Emergency alert sent successfully'
        ];
    }

    /**
     * Record emergency communication
     */
    public function recordCommunication($incidentId, $communicationData, $sender)
    {
        $stmt = $this->db->prepare("
            INSERT INTO emergency_communications (
                incident_id, communication_type, sender, recipient, message_type,
                message_content, priority_level, communication_channel
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $incidentId,
            $communicationData['communication_type'] ?? 'internal',
            $sender,
            $communicationData['recipient'] ?? null,
            $communicationData['message_type'] ?? 'text',
            $communicationData['message_content'],
            $communicationData['priority_level'] ?? 'normal',
            $communicationData['communication_channel'] ?? 'radio'
        ]);

        return [
            'communication_id' => $this->db->lastInsertId(),
            'status' => 'recorded',
            'message' => 'Emergency communication recorded successfully'
        ];
    }

    /**
     * Record evacuation
     */
    public function recordEvacuation($incidentId, $evacuationData, $initiatedBy)
    {
        $stmt = $this->db->prepare("
            INSERT INTO emergency_evacuations (
                incident_id, evacuation_type, evacuation_area, evacuation_route,
                assembly_point, initiated_by, evacuation_time_minutes,
                total_evacuated, special_assistance_provided
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $incidentId,
            $evacuationData['evacuation_type'] ?? 'partial',
            $evacuationData['evacuation_area'],
            $evacuationData['evacuation_route'] ?? null,
            $evacuationData['assembly_point'] ?? null,
            $initiatedBy,
            $evacuationData['evacuation_time_minutes'] ?? null,
            $evacuationData['total_evacuated'] ?? 0,
            $evacuationData['special_assistance_provided'] ?? 0
        ]);

        // Update incident with evacuation info
        $stmt = $this->db->prepare("
            UPDATE incident_reports
            SET evacuation_required = true, evacuation_count = ?
            WHERE incident_id = ?
        ");
        $stmt->execute([$evacuationData['total_evacuated'] ?? 0, $incidentId]);

        return [
            'evacuation_id' => $this->db->lastInsertId(),
            'status' => 'recorded',
            'message' => 'Emergency evacuation recorded successfully'
        ];
    }

    /**
     * Get emergency protocols
     */
    public function getProtocols($type = null, $status = 'active')
    {
        $whereClause = "WHERE status = ?";
        $params = [$status];

        if ($type) {
            $whereClause .= " AND protocol_type = ?";
            $params[] = $type;
        }

        $stmt = $this->db->prepare("
            SELECT * FROM emergency_protocols
            $whereClause
            ORDER BY protocol_type, severity_level DESC
        ");

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get emergency response teams
     */
    public function getResponseTeams($status = null, $type = null)
    {
        $whereClause = "";
        $params = [];

        if ($status) {
            $whereClause .= " AND status = ?";
            $params[] = $status;
        }

        if ($type) {
            $whereClause .= " AND team_type = ?";
            $params[] = $type;
        }

        $stmt = $this->db->prepare("
            SELECT
                ert.*,
                (
                    SELECT COUNT(*)
                    FROM emergency_resources er
                    JOIN incident_reports ir ON er.incident_id = ir.incident_id
                    WHERE er.resource_name = ert.team_name
                    AND ir.incident_status NOT IN ('resolved', 'closed')
                ) as active_incidents
            FROM emergency_response_teams ert
            WHERE 1=1 $whereClause
            ORDER BY ert.team_type, ert.status, ert.response_time_minutes
        ");

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get emergency equipment
     */
    public function getEquipment($type = null, $status = null)
    {
        $whereClause = "";
        $params = [];

        if ($type) {
            $whereClause .= " AND equipment_type = ?";
            $params[] = $type;
        }

        if ($status) {
            $whereClause .= " AND operational_status = ?";
            $params[] = $status;
        }

        $stmt = $this->db->prepare("
            SELECT
                ee.*,
                ert.team_name as assigned_team_name,
                (
                    SELECT COUNT(*)
                    FROM emergency_resources er
                    WHERE er.resource_name = ee.equipment_name
                    AND er.resource_type = 'equipment'
                    AND er.released_at IS NULL
                ) as currently_allocated
            FROM emergency_equipment ee
            LEFT JOIN emergency_response_teams ert ON ee.assigned_team = ert.team_id
            WHERE 1=1 $whereClause
            ORDER BY ee.equipment_type, ee.operational_status, ee.location
        ");

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Update equipment status
     */
    public function updateEquipmentStatus($equipmentId, $status, $updatedBy, $notes = null)
    {
        $stmt = $this->db->prepare("
            UPDATE emergency_equipment
            SET operational_status = ?, updated_at = CURRENT_TIMESTAMP
            WHERE equipment_id = ?
        ");
        $stmt->execute([$status, $equipmentId]);

        // Log equipment status change
        $this->logAuditEvent(null, 'equipment_status_changed',
            'Equipment status changed to ' . $status,
            $updatedBy, [
                'equipment_id' => $equipmentId,
                'new_status' => $status,
                'notes' => $notes
            ]);

        return ['status' => 'updated', 'equipment_id' => $equipmentId];
    }

    /**
     * Get emergency contacts
     */
    public function getEmergencyContacts($type = null, $status = 'active')
    {
        $whereClause = "WHERE status = ?";
        $params = [$status];

        if ($type) {
            $whereClause .= " AND contact_type = ?";
            $params[] = $type;
        }

        $stmt = $this->db->prepare("
            SELECT * FROM emergency_contacts
            $whereClause
            ORDER BY contact_type, reliability_rating DESC
        ");

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Schedule emergency drill
     */
    public function scheduleDrill($drillData, $scheduledBy)
    {
        $this->logger->info("Scheduling emergency drill", $drillData);

        $stmt = $this->db->prepare("
            INSERT INTO emergency_drills (
                drill_id, drill_name, drill_type, scenario_description,
                planned_date, duration_minutes, participants, teams_involved,
                drill_objectives, conducted_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $drillId = $this->generateDrillId();

        $stmt->execute([
            $drillId,
            $drillData['drill_name'],
            $drillData['drill_type'],
            $drillData['scenario_description'] ?? null,
            $drillData['planned_date'],
            $drillData['duration_minutes'] ?? null,
            isset($drillData['participants']) ? json_encode($drillData['participants']) : '[]',
            isset($drillData['teams_involved']) ? json_encode($drillData['teams_involved']) : '[]',
            isset($drillData['drill_objectives']) ? json_encode($drillData['drill_objectives']) : '[]',
            $scheduledBy
        ]);

        return [
            'drill_id' => $drillId,
            'status' => 'scheduled',
            'message' => 'Emergency drill scheduled successfully'
        ];
    }

    /**
     * Record drill results
     */
    public function recordDrillResults($drillId, $resultsData, $recordedBy)
    {
        $stmt = $this->db->prepare("
            UPDATE emergency_drills
            SET
                actual_date = CURRENT_TIMESTAMP,
                drill_status = 'completed',
                drill_results = ?,
                performance_rating = ?,
                issues_identified = ?,
                recommendations = ?,
                reviewed_by = ?,
                review_date = CURRENT_TIMESTAMP
            WHERE drill_id = ?
        ");

        $stmt->execute([
            isset($resultsData['drill_results']) ? json_encode($resultsData['drill_results']) : '{}',
            $resultsData['performance_rating'] ?? null,
            isset($resultsData['issues_identified']) ? json_encode($resultsData['issues_identified']) : '[]',
            isset($resultsData['recommendations']) ? json_encode($resultsData['recommendations']) : '[]',
            $recordedBy,
            $drillId
        ]);

        return [
            'drill_id' => $drillId,
            'status' => 'completed',
            'message' => 'Drill results recorded successfully'
        ];
    }

    /**
     * Get emergency drills
     */
    public function getDrills($status = null, $type = null)
    {
        $whereClause = "";
        $params = [];

        if ($status) {
            $whereClause .= " AND drill_status = ?";
            $params[] = $status;
        }

        if ($type) {
            $whereClause .= " AND drill_type = ?";
            $params[] = $type;
        }

        $stmt = $this->db->prepare("
            SELECT * FROM emergency_drills
            WHERE 1=1 $whereClause
            ORDER BY planned_date DESC
        ");

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get emergency dashboard data
     */
    public function getDashboardData()
    {
        $stmt = $this->db->prepare("
            SELECT json_build_object(
                'active_incidents', (
                    SELECT COUNT(*) FROM incident_reports
                    WHERE incident_status NOT IN ('resolved', 'closed')
                ),
                'incidents_today', (
                    SELECT COUNT(*) FROM incident_reports
                    WHERE DATE(reported_at) = CURRENT_DATE
                ),
                'critical_incidents', (
                    SELECT COUNT(*) FROM incident_reports
                    WHERE severity_level = 'critical'
                    AND incident_status NOT IN ('resolved', 'closed')
                ),
                'response_teams_status', (
                    SELECT json_agg(
                        json_build_object(
                            'team_name', team_name,
                            'team_type', team_type,
                            'status', status,
                            'response_time', response_time_minutes
                        )
                    )
                    FROM emergency_response_teams
                    WHERE status = 'available'
                    LIMIT 10
                ),
                'equipment_status', (
                    SELECT json_agg(
                        json_build_object(
                            'equipment_type', equipment_type,
                            'operational_count', COUNT(CASE WHEN operational_status = 'operational' THEN 1 END),
                            'maintenance_count', COUNT(CASE WHEN operational_status = 'maintenance' THEN 1 END),
                            'out_of_service_count', COUNT(CASE WHEN operational_status = 'out_of_service' THEN 1 END)
                        )
                    )
                    FROM emergency_equipment
                    GROUP BY equipment_type
                ),
                'recent_incidents', (
                    SELECT json_agg(
                        json_build_object(
                            'incident_id', incident_id,
                            'incident_type', incident_type,
                            'severity_level', severity_level,
                            'location', location,
                            'status', incident_status,
                            'reported_at', reported_at
                        )
                    )
                    FROM incident_reports
                    ORDER BY reported_at DESC
                    LIMIT 5
                ),
                'drills_upcoming', (
                    SELECT json_agg(
                        json_build_object(
                            'drill_name', drill_name,
                            'drill_type', drill_type,
                            'planned_date', planned_date,
                            'teams_involved', jsonb_array_length(teams_involved)
                        )
                    )
                    FROM emergency_drills
                    WHERE planned_date >= CURRENT_DATE
                    AND planned_date <= CURRENT_DATE + INTERVAL '30 days'
                    ORDER BY planned_date
                    LIMIT 5
                ),
                'performance_metrics', (
                    SELECT json_build_object(
                        'avg_response_time', ROUND(AVG(EXTRACT(EPOCH FROM calculate_response_time(incident_id))/60), 2),
                        'incident_resolution_rate', ROUND(
                            COUNT(CASE WHEN incident_status IN ('resolved', 'closed') THEN 1 END)::DECIMAL /
                            COUNT(*)::DECIMAL * 100, 2
                        ),
                        'equipment_uptime', ROUND(
                            COUNT(CASE WHEN operational_status = 'operational' THEN 1 END)::DECIMAL /
                            COUNT(*)::DECIMAL * 100, 2
                        )
                    )
                    FROM incident_reports ir
                    CROSS JOIN emergency_equipment ee
                    WHERE ir.reported_at >= CURRENT_DATE - INTERVAL '30 days'
                )
            ) as dashboard_data
        ");

        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return json_decode($result['dashboard_data'], true);
    }

    /**
     * Generate emergency incident report
     */
    public function generateIncidentReport($startDate, $endDate)
    {
        $stmt = $this->db->prepare("
            SELECT json_build_object(
                'period', json_build_object('start_date', p_start_date, 'end_date', p_end_date),
                'incident_summary', json_build_object(
                    'total_incidents', (
                        SELECT COUNT(*) FROM incident_reports
                        WHERE DATE(reported_at) BETWEEN ? AND ?
                    ),
                    'by_type', (
                        SELECT json_agg(
                            json_build_object(
                                'type', incident_type,
                                'count', COUNT(*),
                                'avg_response_time', ROUND(AVG(EXTRACT(EPOCH FROM calculate_response_time(incident_id))/60), 2)
                            )
                        )
                        FROM incident_reports
                        WHERE DATE(reported_at) BETWEEN ? AND ?
                        GROUP BY incident_type
                    ),
                    'by_severity', (
                        SELECT json_agg(
                            json_build_object(
                                'severity', severity_level,
                                'count', COUNT(*),
                                'resolution_rate', ROUND(
                                    COUNT(CASE WHEN incident_status IN ('resolved', 'closed') THEN 1 END)::DECIMAL /
                                    COUNT(*)::DECIMAL * 100, 2
                                )
                            )
                        )
                        FROM incident_reports
                        WHERE DATE(reported_at) BETWEEN ? AND ?
                        GROUP BY severity_level
                    )
                ),
                'response_metrics', json_build_object(
                    'average_response_time', (
                        SELECT ROUND(AVG(EXTRACT(EPOCH FROM calculate_response_time(incident_id))/60), 2)
                        FROM incident_reports
                        WHERE DATE(reported_at) BETWEEN ? AND ?
                        AND response_started_at IS NOT NULL
                    ),
                    'evacuation_stats', (
                        SELECT json_build_object(
                            'total_evacuations', COUNT(*),
                            'total_evacuated', SUM(total_evacuated),
                            'avg_evacuation_time', ROUND(AVG(evacuation_time_minutes), 2)
                        )
                        FROM emergency_evacuations ee
                        JOIN incident_reports ir ON ee.incident_id = ir.incident_id
                        WHERE DATE(ir.reported_at) BETWEEN ? AND ?
                    )
                ),
                'resource_utilization', json_build_object(
                    'teams_deployed', (
                        SELECT COUNT(DISTINCT ert.team_id)
                        FROM emergency_resources er
                        JOIN incident_reports ir ON er.incident_id = ir.incident_id
                        JOIN emergency_response_teams ert ON er.resource_name = ert.team_name
                        WHERE DATE(ir.reported_at) BETWEEN ? AND ?
                        AND er.resource_type = 'personnel'
                    ),
                    'equipment_used', (
                        SELECT COUNT(*)
                        FROM emergency_resources er
                        JOIN incident_reports ir ON er.incident_id = ir.incident_id
                        WHERE DATE(ir.reported_at) BETWEEN ? AND ?
                        AND er.resource_type = 'equipment'
                    )
                ),
                'training_drills', json_build_object(
                    'drills_conducted', (
                        SELECT COUNT(*) FROM emergency_drills
                        WHERE DATE(actual_date) BETWEEN ? AND ?
                        AND drill_status = 'completed'
                    ),
                    'average_performance', (
                        SELECT ROUND(AVG(performance_rating), 2)
                        FROM emergency_drills
                        WHERE DATE(actual_date) BETWEEN ? AND ?
                        AND drill_status = 'completed'
                    )
                )
            ) as report_data
            FROM (SELECT ? as p_start_date, ? as p_end_date) params
        ");

        $params = array_fill(0, 14, $startDate);
        $params = array_merge($params, array_fill(0, 14, $endDate));

        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return json_decode($result['report_data'], true);
    }

    /**
     * Generate response time analysis report
     */
    public function generateResponseTimeReport($startDate, $endDate)
    {
        $stmt = $this->db->prepare("
            SELECT json_build_object(
                'period', json_build_object('start_date', ?, 'end_date', ?),
                'response_time_analysis', json_build_object(
                    'overall_average', ROUND(AVG(EXTRACT(EPOCH FROM calculate_response_time(incident_id))/60), 2),
                    'by_incident_type', (
                        SELECT json_agg(
                            json_build_object(
                                'incident_type', incident_type,
                                'average_response_time', ROUND(AVG(EXTRACT(EPOCH FROM calculate_response_time(incident_id))/60), 2),
                                'min_response_time', ROUND(MIN(EXTRACT(EPOCH FROM calculate_response_time(incident_id))/60), 2),
                                'max_response_time', ROUND(MAX(EXTRACT(EPOCH FROM calculate_response_time(incident_id))/60), 2),
                                'incident_count', COUNT(*)
                            )
                        )
                        FROM incident_reports
                        WHERE DATE(reported_at) BETWEEN ? AND ?
                        AND response_started_at IS NOT NULL
                        GROUP BY incident_type
                    ),
                    'by_severity', (
                        SELECT json_agg(
                            json_build_object(
                                'severity', severity_level,
                                'average_response_time', ROUND(AVG(EXTRACT(EPOCH FROM calculate_response_time(incident_id))/60), 2),
                                'target_response_time', CASE
                                    WHEN severity_level = 'critical' THEN 5
                                    WHEN severity_level = 'high' THEN 10
                                    WHEN severity_level = 'medium' THEN 15
                                    ELSE 30
                                END,
                                'on_time_percentage', ROUND(
                                    COUNT(CASE WHEN EXTRACT(EPOCH FROM calculate_response_time(incident_id))/60 <=
                                        CASE
                                            WHEN severity_level = 'critical' THEN 5
                                            WHEN severity_level = 'high' THEN 10
                                            WHEN severity_level = 'medium' THEN 15
                                            ELSE 30
                                        END THEN 1 END)::DECIMAL / COUNT(*)::DECIMAL * 100, 2
                                )
                            )
                        )
                        FROM incident_reports
                        WHERE DATE(reported_at) BETWEEN ? AND ?
                        AND response_started_at IS NOT NULL
                        GROUP BY severity_level
                    ),
                    'response_time_distribution', (
                        SELECT json_agg(
                            json_build_object(
                                'time_range', time_range,
                                'incident_count', COUNT(*),
                                'percentage', ROUND(COUNT(*)::DECIMAL / SUM(COUNT(*)) OVER () * 100, 2)
                            )
                        )
                        FROM (
                            SELECT
                                CASE
                                    WHEN EXTRACT(EPOCH FROM calculate_response_time(incident_id))/60 < 5 THEN '< 5 min'
                                    WHEN EXTRACT(EPOCH FROM calculate_response_time(incident_id))/60 < 10 THEN '5-10 min'
                                    WHEN EXTRACT(EPOCH FROM calculate_response_time(incident_id))/60 < 15 THEN '10-15 min'
                                    WHEN EXTRACT(EPOCH FROM calculate_response_time(incident_id))/60 < 30 THEN '15-30 min'
                                    ELSE '> 30 min'
                                END as time_range
                            FROM incident_reports
                            WHERE DATE(reported_at) BETWEEN ? AND ?
                            AND response_started_at IS NOT NULL
                        ) as time_ranges
                        GROUP BY time_range
                        ORDER BY MIN(EXTRACT(EPOCH FROM calculate_response_time(incident_id))/60)
                    ),
                    'peak_response_times', (
                        SELECT json_agg(
                            json_build_object(
                                'hour', hour,
                                'average_response_time', ROUND(AVG(EXTRACT(EPOCH FROM calculate_response_time(incident_id))/60), 2),
                                'incident_count', COUNT(*)
                            )
                        )
                        FROM (
                            SELECT
                                EXTRACT(HOUR FROM reported_at) as hour,
                                calculate_response_time(incident_id)
                            FROM incident_reports
                            WHERE DATE(reported_at) BETWEEN ? AND ?
                            AND response_started_at IS NOT NULL
                        ) as hourly_data
                        GROUP BY hour
                        ORDER BY hour
                    )
                ),
                'performance_indicators', json_build_object(
                    'target_achievement_rate', ROUND(
                        COUNT(CASE WHEN EXTRACT(EPOCH FROM calculate_response_time(incident_id))/60 <=
                            CASE
                                WHEN severity_level = 'critical' THEN 5
                                WHEN severity_level = 'high' THEN 10
                                WHEN severity_level = 'medium' THEN 15
                                ELSE 30
                            END THEN 1 END)::DECIMAL / COUNT(*)::DECIMAL * 100, 2
                    ),
                    'average_delay', ROUND(AVG(
                        GREATEST(0, EXTRACT(EPOCH FROM calculate_response_time(incident_id))/60 -
                            CASE
                                WHEN severity_level = 'critical' THEN 5
                                WHEN severity_level = 'high' THEN 10
                                WHEN severity_level = 'medium' THEN 15
                                ELSE 30
                            END)
                    ), 2)
                )
            ) as report_data
            FROM incident_reports
            WHERE DATE(reported_at) BETWEEN ? AND ?
            AND response_started_at IS NOT NULL
        ");

        $params = [$startDate, $endDate, $startDate, $endDate, $startDate, $endDate, $startDate, $endDate, $startDate, $endDate, $startDate, $endDate];
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return json_decode($result['report_data'], true);
    }

    /**
     * Generate performance metrics report
     */
    public function generatePerformanceReport($startDate, $endDate)
    {
        $stmt = $this->db->prepare("
            SELECT json_build_object(
                'period', json_build_object('start_date', ?, 'end_date', ?),
                'performance_metrics', json_build_object(
                    'incident_resolution', json_build_object(
                        'total_incidents', COUNT(*),
                        'resolved_incidents', COUNT(CASE WHEN incident_status IN ('resolved', 'closed') THEN 1 END),
                        'resolution_rate', ROUND(
                            COUNT(CASE WHEN incident_status IN ('resolved', 'closed') THEN 1 END)::DECIMAL /
                            COUNT(*)::DECIMAL * 100, 2
                        ),
                        'average_resolution_time', ROUND(AVG(
                            EXTRACT(EPOCH FROM (COALESCE(resolved_at, acknowledged_at) - reported_at))/3600
                        ), 2)
                    ),
                    'resource_efficiency', json_build_object(
                        'total_resources_allocated', (
                            SELECT COUNT(*) FROM emergency_resources er
                            WHERE DATE(er.allocated_at) BETWEEN ? AND ?
                        ),
                        'resource_utilization_rate', ROUND(
                            COUNT(DISTINCT er.incident_id)::DECIMAL / COUNT(DISTINCT ir.incident_id)::DECIMAL * 100, 2
                        ),
                        'average_resources_per_incident', ROUND(
                            COUNT(er.resource_id)::DECIMAL / COUNT(DISTINCT ir.incident_id)::DECIMAL, 2
                        )
                    ),
                    'communication_effectiveness', json_build_object(
                        'total_communications', (
                            SELECT COUNT(*) FROM emergency_communications ec
                            WHERE DATE(ec.sent_at) BETWEEN ? AND ?
                        ),
                        'communications_per_incident', ROUND(
                            (SELECT COUNT(*) FROM emergency_communications ec WHERE DATE(ec.sent_at) BETWEEN ? AND ?)::DECIMAL /
                            COUNT(DISTINCT ir.incident_id)::DECIMAL, 2
                        ),
                        'alert_success_rate', ROUND(
                            (SELECT COUNT(*) FROM emergency_alerts ea WHERE DATE(ea.sent_at) BETWEEN ? AND ?
                             AND ea.delivery_status = 'delivered')::DECIMAL /
                            (SELECT COUNT(*) FROM emergency_alerts ea WHERE DATE(ea.sent_at) BETWEEN ? AND ?)::DECIMAL * 100, 2
                        )
                    ),
                    'equipment_performance', json_build_object(
                        'equipment_availability', ROUND(
                            COUNT(CASE WHEN operational_status = 'operational' THEN 1 END)::DECIMAL /
                            COUNT(*)::DECIMAL * 100, 2
                        ),
                        'equipment_failure_rate', ROUND(
                            COUNT(CASE WHEN operational_status = 'out_of_service' THEN 1 END)::DECIMAL /
                            COUNT(*)::DECIMAL * 100, 2
                        ),
                        'maintenance_efficiency', ROUND(
                            AVG(EXTRACT(EPOCH FROM (maintenance_completed_at - maintenance_started_at))/3600), 2
                        )
                    ),
                    'team_performance', json_build_object(
                        'average_team_response_time', ROUND(AVG(ert.response_time_minutes), 2),
                        'team_availability_rate', ROUND(
                            COUNT(CASE WHEN ert.status = 'available' THEN 1 END)::DECIMAL /
                            COUNT(*)::DECIMAL * 100, 2
                        ),
                        'active_incidents_per_team', ROUND(
                            COUNT(DISTINCT ir.incident_id)::DECIMAL / COUNT(DISTINCT ert.team_id)::DECIMAL, 2
                        )
                    )
                ),
                'kpi_dashboard', json_build_object(
                    'response_time_kpi', ROUND(
                        COUNT(CASE WHEN EXTRACT(EPOCH FROM calculate_response_time(incident_id))/60 <=
                            CASE
                                WHEN severity_level = 'critical' THEN 5
                                WHEN severity_level = 'high' THEN 10
                                WHEN severity_level = 'medium' THEN 15
                                ELSE 30
                            END THEN 1 END)::DECIMAL / COUNT(*)::DECIMAL * 100, 2
                    ),
                    'resolution_time_kpi', ROUND(
                        AVG(EXTRACT(EPOCH FROM (COALESCE(resolved_at, acknowledged_at) - reported_at))/3600), 2
                    ),
                    'resource_utilization_kpi', ROUND(
                        COUNT(DISTINCT er.incident_id)::DECIMAL / COUNT(DISTINCT ir.incident_id)::DECIMAL * 100, 2
                    ),
                    'equipment_uptime_kpi', ROUND(
                        COUNT(CASE WHEN operational_status = 'operational' THEN 1 END)::DECIMAL /
                        COUNT(*)::DECIMAL * 100, 2
                    )
                )
            ) as report_data
            FROM incident_reports ir
            LEFT JOIN emergency_resources er ON ir.incident_id = er.incident_id
            LEFT JOIN emergency_response_teams ert ON er.resource_name = ert.team_name
            LEFT JOIN emergency_equipment ee ON ee.assigned_team = ert.team_id
            WHERE DATE(ir.reported_at) BETWEEN ? AND ?
        ");

        $params = [$startDate, $endDate, $startDate, $endDate, $startDate, $endDate, $startDate, $endDate, $startDate, $endDate, $startDate, $endDate, $startDate, $endDate, $startDate, $endDate];
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return json_decode($result['report_data'], true);
    }

    /**
     * Generate incident trends analysis report
     */
    public function generateTrendsReport($startDate, $endDate)
    {
        $stmt = $this->db->prepare("
            SELECT json_build_object(
                'period', json_build_object('start_date', ?, 'end_date', ?),
                'incident_trends', json_build_object(
                    'monthly_incidents', (
                        SELECT json_agg(
                            json_build_object(
                                'month', TO_CHAR(DATE_TRUNC('month', reported_at), 'YYYY-MM'),
                                'incident_count', COUNT(*),
                                'critical_incidents', COUNT(CASE WHEN severity_level = 'critical' THEN 1 END),
                                'average_response_time', ROUND(AVG(EXTRACT(EPOCH FROM calculate_response_time(incident_id))/60), 2)
                            )
                        )
                        FROM incident_reports
                        WHERE DATE(reported_at) BETWEEN ? AND ?
                        GROUP BY DATE_TRUNC('month', reported_at)
                        ORDER BY DATE_TRUNC('month', reported_at)
                    ),
                    'incident_type_trends', (
                        SELECT json_agg(
                            json_build_object(
                                'incident_type', incident_type,
                                'monthly_data', (
                                    SELECT json_agg(
                                        json_build_object(
                                            'month', TO_CHAR(DATE_TRUNC('month', reported_at), 'YYYY-MM'),
                                            'count', COUNT(*)
                                        )
                                    )
                                    FROM incident_reports ir2
                                    WHERE ir2.incident_type = ir.incident_type
                                    AND DATE(ir2.reported_at) BETWEEN ? AND ?
                                    GROUP BY DATE_TRUNC('month', ir2.reported_at)
                                    ORDER BY DATE_TRUNC('month', ir2.reported_at)
                                )
                            )
                        )
                        FROM incident_reports ir
                        WHERE DATE(reported_at) BETWEEN ? AND ?
                        GROUP BY incident_type
                    ),
                    'severity_trends', (
                        SELECT json_agg(
                            json_build_object(
                                'severity', severity_level,
                                'monthly_data', (
                                    SELECT json_agg(
                                        json_build_object(
                                            'month', TO_CHAR(DATE_TRUNC('month', reported_at), 'YYYY-MM'),
                                            'count', COUNT(*)
                                        )
                                    )
                                    FROM incident_reports ir2
                                    WHERE ir2.severity_level = ir.severity_level
                                    AND DATE(ir2.reported_at) BETWEEN ? AND ?
                                    GROUP BY DATE_TRUNC('month', ir2.reported_at)
                                    ORDER BY DATE_TRUNC('month', ir2.reported_at)
                                )
                            )
                        )
                        FROM incident_reports ir
                        WHERE DATE(reported_at) BETWEEN ? AND ?
                        GROUP BY severity_level
                    ),
                    'location_hotspots', (
                        SELECT json_agg(
                            json_build_object(
                                'location', location,
                                'incident_count', COUNT(*),
                                'most_common_type', (
                                    SELECT incident_type
                                    FROM incident_reports ir2
                                    WHERE ir2.location = ir.location
                                    AND DATE(ir2.reported_at) BETWEEN ? AND ?
                                    GROUP BY incident_type
                                    ORDER BY COUNT(*) DESC
                                    LIMIT 1
                                ),
                                'average_severity', ROUND(AVG(
                                    CASE
                                        WHEN severity_level = 'critical' THEN 4
                                        WHEN severity_level = 'high' THEN 3
                                        WHEN severity_level = 'medium' THEN 2
                                        ELSE 1
                                    END
                                ), 2)
                            )
                        )
                        FROM incident_reports ir
                        WHERE DATE(reported_at) BETWEEN ? AND ?
                        AND location IS NOT NULL
                        GROUP BY location
                        ORDER BY COUNT(*) DESC
                        LIMIT 10
                    ),
                    'time_patterns', json_build_object(
                        'hourly_distribution', (
                            SELECT json_agg(
                                json_build_object(
                                    'hour', hour,
                                    'incident_count', COUNT(*),
                                    'peak_hour', CASE WHEN ROW_NUMBER() OVER (ORDER BY COUNT(*) DESC) = 1 THEN true ELSE false END
                                )
                            )
                            FROM (
                                SELECT EXTRACT(HOUR FROM reported_at) as hour
                                FROM incident_reports
                                WHERE DATE(reported_at) BETWEEN ? AND ?
                            ) as hourly
                            GROUP BY hour
                            ORDER BY hour
                        ),
                        'weekday_distribution', (
                            SELECT json_agg(
                                json_build_object(
                                    'weekday', TO_CHAR(reported_at, 'Day'),
                                    'incident_count', COUNT(*)
                                )
                            )
                            FROM incident_reports
                            WHERE DATE(reported_at) BETWEEN ? AND ?
                            GROUP BY EXTRACT(DOW FROM reported_at), TO_CHAR(reported_at, 'Day')
                            ORDER BY EXTRACT(DOW FROM reported_at)
                        )
                    ),
                    'predictive_insights', json_build_object(
                        'trend_direction', CASE
                            WHEN (
                                SELECT COUNT(*) FROM incident_reports
                                WHERE DATE(reported_at) BETWEEN DATE(? + INTERVAL '60 days') AND DATE(? + INTERVAL '90 days')
                            ) > (
                                SELECT COUNT(*) FROM incident_reports
                                WHERE DATE(reported_at) BETWEEN DATE(? + INTERVAL '30 days') AND DATE(? + INTERVAL '60 days')
                            ) THEN 'increasing'
                            WHEN (
                                SELECT COUNT(*) FROM incident_reports
                                WHERE DATE(reported_at) BETWEEN DATE(? + INTERVAL '60 days') AND DATE(? + INTERVAL '90 days')
                            ) < (
                                SELECT COUNT(*) FROM incident_reports
                                WHERE DATE(reported_at) BETWEEN DATE(? + INTERVAL '30 days') AND DATE(? + INTERVAL '60 days')
                            ) THEN 'decreasing'
                            ELSE 'stable'
                        END,
                        'seasonal_patterns', (
                            SELECT json_agg(
                                json_build_object(
                                    'month', month,
                                    'average_incidents', ROUND(AVG(incident_count), 2),
                                    'peak_month', CASE WHEN ROW_NUMBER() OVER (ORDER BY AVG(incident_count) DESC) = 1 THEN true ELSE false END
                                )
                            )
                            FROM (
                                SELECT
                                    TO_CHAR(reported_at, 'MM') as month,
                                    COUNT(*) as incident_count
                                FROM incident_reports
                                WHERE DATE(reported_at) >= DATE(? - INTERVAL '1 year')
                                GROUP BY TO_CHAR(reported_at, 'MM'), DATE_TRUNC('month', reported_at)
                            ) as monthly_stats
                            GROUP BY month
                            ORDER BY month::INTEGER
                        )
                    )
                )
            ) as report_data
            FROM incident_reports
            WHERE DATE(reported_at) BETWEEN ? AND ?
        ");

        $params = array_fill(0, 20, $startDate);
        $params = array_merge($params, array_fill(0, 20, $endDate));

        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return json_decode($result['report_data'], true);
    }

    // Helper methods

    private function generateIncidentId()
    {
        return 'INC-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
    }

    private function generateIncidentNumber()
    {
        return 'EMERGENCY-' . date('YmdHis');
    }

    private function generateDrillId()
    {
        return 'DRILL-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 4));
    }

    private function autoActivateProtocol($incidentId, $incidentType, $activatedBy)
    {
        // Find appropriate protocol for incident type
        $stmt = $this->db->prepare("
            SELECT protocol_id FROM emergency_protocols
            WHERE protocol_type = ? AND status = 'active'
            ORDER BY severity_level DESC
            LIMIT 1
        ");
        $stmt->execute([$incidentType]);
        $protocol = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($protocol) {
            $this->activateProtocol($incidentId, $protocol['protocol_id'], $activatedBy);
        }
    }

    /**
     * Send emergency notifications for critical incidents
     */
    private function sendEmergencyNotifications($incidentId, $incidentData, $reportedBy)
    {
        try {
            $alertData = [
                'alert_type' => 'emergency',
                'message' => 'CRITICAL EMERGENCY: ' . ($incidentData['description'] ?? 'Emergency incident reported'),
                'priority' => 'high',
                'location' => $incidentData['location'] ?? 'Airport',
                'incident_id' => $incidentId,
                'instructions' => 'All emergency personnel report to incident location immediately.'
            ];

            $result = $this->notificationIntegration->sendEmergencyAlert($alertData);

            // Log notification results
            $this->logAuditEvent($incidentId, 'emergency_notifications_sent',
                'Emergency notifications sent to all personnel',
                $reportedBy, [
                    'notification_result' => $result,
                    'channels_used' => array_keys($result)
                ]);

            $this->logger->info("Emergency notifications sent", [
                'incident_id' => $incidentId,
                'result' => $result
            ]);

        } catch (Exception $e) {
            $this->logger->error('Failed to send emergency notifications', [
                'incident_id' => $incidentId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Request medical assistance for medical emergencies
     */
    private function requestMedicalAssistance($incidentId, $incidentData, $reportedBy)
    {
        try {
            $medicalData = [
                'incident_id' => $incidentId,
                'location' => $incidentData['location'] ?? 'Airport',
                'incident_type' => $incidentData['incident_type'],
                'severity' => $incidentData['severity_level'] ?? 'medium',
                'casualty_count' => $incidentData['casualty_count'] ?? 1,
                'medical_requirements' => $incidentData['medical_requirements'] ?? [],
                'caller_name' => 'Airport Emergency Services',
                'caller_phone' => 'Emergency Line'
            ];

            $result = $this->medicalIntegration->requestEmergencyMedical($medicalData);

            // Log medical assistance request
            $this->logAuditEvent($incidentId, 'medical_assistance_requested',
                'Medical assistance requested for emergency incident',
                $reportedBy, [
                    'medical_request_result' => $result,
                    'services_requested' => array_keys($result)
                ]);

            $this->logger->info("Medical assistance requested", [
                'incident_id' => $incidentId,
                'result' => $result
            ]);

        } catch (Exception $e) {
            $this->logger->error('Failed to request medical assistance', [
                'incident_id' => $incidentId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send targeted emergency alert to specific groups
     */
    public function sendTargetedEmergencyAlert($incidentId, $alertData, $targetGroups, $sentBy)
    {
        try {
            $result = $this->notificationIntegration->sendTargetedAlert($alertData, $targetGroups);

            // Log targeted alert
            $this->logAuditEvent($incidentId, 'targeted_alert_sent',
                'Targeted emergency alert sent to specific groups',
                $sentBy, [
                    'target_groups' => $targetGroups,
                    'alert_result' => $result
                ]);

            return $result;

        } catch (Exception $e) {
            $this->logger->error('Failed to send targeted emergency alert', [
                'incident_id' => $incidentId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Request medical supplies
     */
    public function requestMedicalSupplies($incidentId, $supplyRequest, $requestedBy)
    {
        try {
            $result = $this->medicalIntegration->requestMedicalSupplies($supplyRequest);

            // Log supply request
            $this->logAuditEvent($incidentId, 'medical_supplies_requested',
                'Medical supplies requested',
                $requestedBy, [
                    'supply_request' => $supplyRequest,
                    'request_result' => $result
                ]);

            return $result;

        } catch (Exception $e) {
            $this->logger->error('Failed to request medical supplies', [
                'incident_id' => $incidentId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Initiate telemedicine consultation
     */
    public function initiateTelemedicineConsultation($incidentId, $consultationData, $initiatedBy)
    {
        try {
            $result = $this->medicalIntegration->initiateTelemedicineConsultation($consultationData);

            // Log telemedicine consultation
            $this->logAuditEvent($incidentId, 'telemedicine_consultation_initiated',
                'Telemedicine consultation initiated',
                $initiatedBy, [
                    'consultation_data' => $consultationData,
                    'consultation_result' => $result
                ]);

            return $result;

        } catch (Exception $e) {
            $this->logger->error('Failed to initiate telemedicine consultation', [
                'incident_id' => $incidentId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Monitor medical devices
     */
    public function monitorMedicalDevices($deviceQuery = [])
    {
        try {
            return $this->medicalIntegration->monitorMedicalDevices($deviceQuery);
        } catch (Exception $e) {
            $this->logger->error('Failed to monitor medical devices', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Test emergency notification integrations
     */
    public function testEmergencyNotifications()
    {
        try {
            return $this->notificationIntegration->testIntegrations();
        } catch (Exception $e) {
            $this->logger->error('Failed to test emergency notifications', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Test medical services integrations
     */
    public function testMedicalServices()
    {
        try {
            return $this->medicalIntegration->testIntegrations();
        } catch (Exception $e) {
            $this->logger->error('Failed to test medical services', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    private function logAuditEvent($incidentId, $actionType, $description, $performedBy, $details = [])
    {
        $stmt = $this->db->prepare("
            INSERT INTO emergency_audit_logs (
                incident_id, action_type, action_description, performed_by,
                affected_entities, new_values
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $incidentId,
            $actionType,
            $description,
            $performedBy,
            json_encode($details),
            json_encode($details)
        ]);
    }
}
