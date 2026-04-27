<?php

/**
 * Advanced Security Model
 *
 * Manages facial recognition, behavioral analytics, threat detection, and security monitoring
 */

class AdvancedSecurity
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
        $this->logger = new Logger('advanced_security');
    }

    /**
     * Process facial recognition data
     */
    public function processFacialRecognition($facialData)
    {
        $this->logger->info("Processing facial recognition data", $facialData);

        $stmt = $this->db->prepare("
            INSERT INTO facial_recognition_data (
                face_id, person_id, facial_features, confidence_score,
                capture_device, capture_location, image_quality_score,
                lighting_conditions, angle_degrees, distance_meters,
                occlusion_present, glasses_present, mask_present, hat_present,
                encryption_key_id, retention_period_days, privacy_consent
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $faceId = $this->generateFaceId();

        $stmt->execute([
            $faceId,
            $facialData['person_id'] ?? null,
            json_encode($facialData['facial_features']),
            $facialData['confidence_score'] ?? null,
            $facialData['capture_device'] ?? null,
            $facialData['capture_location'] ?? null,
            $facialData['image_quality_score'] ?? null,
            $facialData['lighting_conditions'] ?? 'good',
            $facialData['angle_degrees'] ?? null,
            $facialData['distance_meters'] ?? null,
            $facialData['occlusion_present'] ?? false,
            $facialData['glasses_present'] ?? false,
            $facialData['mask_present'] ?? false,
            $facialData['hat_present'] ?? false,
            $facialData['encryption_key_id'] ?? null,
            $facialData['retention_period_days'] ?? 365,
            $facialData['privacy_consent'] ?? true
        ]);

        // Check for facial matches
        $matches = $this->detectFacialMatches(json_encode($facialData['facial_features']));

        return [
            'face_id' => $faceId,
            'status' => 'processed',
            'matches' => $matches,
            'message' => 'Facial recognition data processed successfully'
        ];
    }

    /**
     * Analyze behavioral patterns
     */
    public function analyzeBehavior($behaviorData)
    {
        $this->logger->info("Analyzing behavioral patterns", $behaviorData);

        $stmt = $this->db->prepare("
            INSERT INTO behavioral_analytics (
                behavior_id, person_id, session_id, behavior_type, location_zone,
                duration_seconds, movement_pattern, interaction_partners,
                suspicious_indicators, risk_score, anomaly_detected,
                anomaly_type, anomaly_confidence, camera_feeds, ai_model_version,
                processed_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $behaviorId = $this->generateBehaviorId();

        $stmt->execute([
            $behaviorId,
            $behaviorData['person_id'] ?? null,
            $behaviorData['session_id'] ?? null,
            $behaviorData['behavior_type'],
            $behaviorData['location_zone'] ?? null,
            $behaviorData['duration_seconds'] ?? null,
            isset($behaviorData['movement_pattern']) ? json_encode($behaviorData['movement_pattern']) : '[]',
            isset($behaviorData['interaction_partners']) ? json_encode($behaviorData['interaction_partners']) : '[]',
            isset($behaviorData['suspicious_indicators']) ? json_encode($behaviorData['suspicious_indicators']) : '[]',
            $behaviorData['risk_score'] ?? 0.0,
            $behaviorData['anomaly_detected'] ?? false,
            $behaviorData['anomaly_type'] ?? null,
            $behaviorData['anomaly_confidence'] ?? null,
            isset($behaviorData['camera_feeds']) ? json_encode($behaviorData['camera_feeds']) : '[]',
            $behaviorData['ai_model_version'] ?? null,
            $behaviorData['processed_by'] ?? null
        ]);

        // Perform behavioral analysis
        $analysis = $this->analyzeBehavioralPatterns(json_encode($behaviorData));

        return [
            'behavior_id' => $behaviorId,
            'status' => 'analyzed',
            'analysis' => $analysis,
            'message' => 'Behavioral analysis completed successfully'
        ];
    }

    /**
     * Register security camera
     */
    public function registerSecurityCamera($cameraData)
    {
        $this->logger->info("Registering security camera", $cameraData);

        $stmt = $this->db->prepare("
            INSERT INTO security_camera_feeds (
                camera_id, camera_name, camera_type, location_zone,
                location_coordinates, ip_address, port, username, password_hash,
                stream_url, resolution_width, resolution_height, frame_rate,
                night_vision, pan_tilt_zoom, audio_recording, motion_detection,
                facial_recognition_enabled, behavioral_analytics_enabled,
                firmware_version, storage_retention_days
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $cameraId = $this->generateCameraId();

        $stmt->execute([
            $cameraId,
            $cameraData['camera_name'],
            $cameraData['camera_type'] ?? 'ip_camera',
            $cameraData['location_zone'],
            isset($cameraData['location_coordinates']) ? json_encode($cameraData['location_coordinates']) : null,
            $cameraData['ip_address'] ?? null,
            $cameraData['port'] ?? null,
            $cameraData['username'] ?? null,
            password_hash($cameraData['password'] ?? '', PASSWORD_DEFAULT), // Hash the password
            $cameraData['stream_url'] ?? null,
            $cameraData['resolution_width'] ?? null,
            $cameraData['resolution_height'] ?? null,
            $cameraData['frame_rate'] ?? 30,
            $cameraData['night_vision'] ?? false,
            $cameraData['pan_tilt_zoom'] ?? false,
            $cameraData['audio_recording'] ?? false,
            $cameraData['motion_detection'] ?? true,
            $cameraData['facial_recognition_enabled'] ?? true,
            $cameraData['behavioral_analytics_enabled'] ?? true,
            $cameraData['firmware_version'] ?? null,
            $cameraData['storage_retention_days'] ?? 30
        ]);

        return [
            'camera_id' => $cameraId,
            'status' => 'registered',
            'message' => 'Security camera registered successfully'
        ];
    }

    /**
     * Get security cameras
     */
    public function getSecurityCameras($zone = null, $status = null)
    {
        $whereClause = "";
        $params = [];

        if ($zone) {
            $whereClause .= " AND location_zone = ?";
            $params[] = $zone;
        }

        if ($status) {
            $whereClause .= " AND status = ?";
            $params[] = $status;
        }

        $stmt = $this->db->prepare("
            SELECT * FROM security_camera_feeds
            WHERE 1=1 $whereClause
            ORDER BY camera_name
        ");

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Detect and record threat
     */
    public function detectThreat($threatData)
    {
        $this->logger->info("Detecting threat", $threatData);

        $stmt = $this->db->prepare("
            INSERT INTO threat_detection_events (
                event_id, event_type, severity_level, confidence_score,
                location_zone, location_coordinates, camera_id, person_id,
                facial_match_data, behavioral_data, additional_evidence,
                ai_model_used, ai_model_version, false_positive_probability,
                response_required, response_actions
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $eventId = $this->generateEventId();

        $stmt->execute([
            $eventId,
            $threatData['event_type'],
            $threatData['severity_level'] ?? 'low',
            $threatData['confidence_score'] ?? null,
            $threatData['location_zone'] ?? null,
            isset($threatData['location_coordinates']) ? json_encode($threatData['location_coordinates']) : null,
            $threatData['camera_id'] ?? null,
            $threatData['person_id'] ?? null,
            isset($threatData['facial_match_data']) ? json_encode($threatData['facial_match_data']) : null,
            isset($threatData['behavioral_data']) ? json_encode($threatData['behavioral_data']) : null,
            isset($threatData['additional_evidence']) ? json_encode($threatData['additional_evidence']) : '[]',
            $threatData['ai_model_used'] ?? null,
            $threatData['ai_model_version'] ?? null,
            $threatData['false_positive_probability'] ?? null,
            $threatData['response_required'] ?? true,
            isset($threatData['response_actions']) ? json_encode($threatData['response_actions']) : '[]'
        ]);

        // Create security alert if high severity
        if (in_array($threatData['severity_level'], ['high', 'critical'])) {
            $this->createSecurityAlert([
                'alert_type' => 'threat_detected',
                'severity_level' => $threatData['severity_level'],
                'alert_title' => ucfirst($threatData['event_type']) . ' Detected',
                'alert_description' => 'Threat detection event: ' . $threatData['event_type'],
                'location_zone' => $threatData['location_zone'],
                'recommended_actions' => ['investigate_immediately', 'security_response']
            ]);
        }

        return [
            'event_id' => $eventId,
            'status' => 'detected',
            'message' => 'Threat detection event recorded successfully'
        ];
    }

    /**
     * Get threat detection events
     */
    public function getThreatEvents($startDate = null, $endDate = null, $severity = null)
    {
        $whereClause = "";
        $params = [];

        if ($startDate) {
            $whereClause .= " AND DATE(detection_timestamp) >= ?";
            $params[] = $startDate;
        }

        if ($endDate) {
            $whereClause .= " AND DATE(detection_timestamp) <= ?";
            $params[] = $endDate;
        }

        if ($severity) {
            $whereClause .= " AND severity_level = ?";
            $params[] = $severity;
        }

        $stmt = $this->db->prepare("
            SELECT
                tde.*,
                scf.camera_name,
                sz.zone_name
            FROM threat_detection_events tde
            LEFT JOIN security_camera_feeds scf ON tde.camera_id = scf.camera_id
            LEFT JOIN security_zones sz ON tde.location_zone = sz.zone_id
            WHERE 1=1 $whereClause
            ORDER BY tde.detection_timestamp DESC
        ");

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Create security zone
     */
    public function createSecurityZone($zoneData)
    {
        $this->logger->info("Creating security zone", $zoneData);

        $stmt = $this->db->prepare("
            INSERT INTO security_zones (
                zone_id, zone_name, zone_type, security_level, parent_zone_id,
                boundary_coordinates, area_sqm, access_requirements,
                surveillance_coverage, lighting_level, emergency_procedures,
                operating_hours, capacity_limit
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $zoneId = $this->generateZoneId();

        $stmt->execute([
            $zoneId,
            $zoneData['zone_name'],
            $zoneData['zone_type'] ?? 'public',
            $zoneData['security_level'] ?? 'standard',
            $zoneData['parent_zone_id'] ?? null,
            json_encode($zoneData['boundary_coordinates']),
            $zoneData['area_sqm'] ?? null,
            isset($zoneData['access_requirements']) ? json_encode($zoneData['access_requirements']) : '{}',
            $zoneData['surveillance_coverage'] ?? null,
            $zoneData['lighting_level'] ?? 'normal',
            isset($zoneData['emergency_procedures']) ? json_encode($zoneData['emergency_procedures']) : '{}',
            isset($zoneData['operating_hours']) ? json_encode($zoneData['operating_hours']) : '{}',
            $zoneData['capacity_limit'] ?? null
        ]);

        return [
            'zone_id' => $zoneId,
            'status' => 'created',
            'message' => 'Security zone created successfully'
        ];
    }

    /**
     * Get security zones
     */
    public function getSecurityZones($type = null, $securityLevel = null)
    {
        $whereClause = "";
        $params = [];

        if ($type) {
            $whereClause .= " AND zone_type = ?";
            $params[] = $type;
        }

        if ($securityLevel) {
            $whereClause .= " AND security_level = ?";
            $params[] = $securityLevel;
        }

        $stmt = $this->db->prepare("
            SELECT * FROM security_zones
            WHERE 1=1 $whereClause
            ORDER BY zone_name
        ");

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Record access control event
     */
    public function recordAccessEvent($accessData)
    {
        $this->logger->info("Recording access control event", $accessData);

        $stmt = $this->db->prepare("
            INSERT INTO access_control_events (
                access_id, person_id, zone_id, access_type, access_method,
                access_device, authorization_status, denial_reason, escort_required,
                escort_person_id, security_clearance_level, required_clearance_level,
                biometric_verification, additional_checks, processing_time_ms
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $accessId = $this->generateAccessId();

        $stmt->execute([
            $accessId,
            $accessData['person_id'] ?? null,
            $accessData['zone_id'],
            $accessData['access_type'],
            $accessData['access_method'] ?? null,
            $accessData['access_device'] ?? null,
            $accessData['authorization_status'] ?? 'granted',
            $accessData['denial_reason'] ?? null,
            $accessData['escort_required'] ?? false,
            $accessData['escort_person_id'] ?? null,
            $accessData['security_clearance_level'] ?? null,
            $accessData['required_clearance_level'] ?? null,
            isset($accessData['biometric_verification']) ? json_encode($accessData['biometric_verification']) : '{}',
            isset($accessData['additional_checks']) ? json_encode($accessData['additional_checks']) : '[]',
            $accessData['processing_time_ms'] ?? null
        ]);

        // Update zone occupancy if access granted
        if ($accessData['authorization_status'] === 'granted') {
            if ($accessData['access_type'] === 'entry') {
                $this->updateZoneOccupancy($accessData['zone_id'], 1);
            } elseif ($accessData['access_type'] === 'exit') {
                $this->updateZoneOccupancy($accessData['zone_id'], -1);
            }
        }

        return [
            'access_id' => $accessId,
            'status' => 'recorded',
            'message' => 'Access control event recorded successfully'
        ];
    }

    /**
     * Get access control events
     */
    public function getAccessEvents($zoneId = null, $status = null, $startDate = null, $endDate = null)
    {
        $whereClause = "";
        $params = [];

        if ($zoneId) {
            $whereClause .= " AND zone_id = ?";
            $params[] = $zoneId;
        }

        if ($status) {
            $whereClause .= " AND authorization_status = ?";
            $params[] = $status;
        }

        if ($startDate) {
            $whereClause .= " AND DATE(access_timestamp) >= ?";
            $params[] = $startDate;
        }

        if ($endDate) {
            $whereClause .= " AND DATE(access_timestamp) <= ?";
            $params[] = $endDate;
        }

        $stmt = $this->db->prepare("
            SELECT
                ace.*,
                sz.zone_name
            FROM access_control_events ace
            LEFT JOIN security_zones sz ON ace.zone_id = sz.zone_id
            WHERE 1=1 $whereClause
            ORDER BY ace.access_timestamp DESC
        ");

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Report suspicious activity
     */
    public function reportSuspiciousActivity($activityData, $reportedBy)
    {
        $this->logger->info("Reporting suspicious activity", $activityData);

        $stmt = $this->db->prepare("
            INSERT INTO suspicious_activity_reports (
                report_id, report_number, reporter_id, reporter_type,
                activity_type, severity_level, location_zone, location_coordinates,
                description, evidence_urls, camera_feeds, persons_involved,
                investigation_required, response_actions
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $reportId = $this->generateReportId();
        $reportNumber = $this->generateReportNumber();

        $stmt->execute([
            $reportId,
            $reportNumber,
            $reportedBy,
            $activityData['reporter_type'] ?? 'system',
            $activityData['activity_type'],
            $activityData['severity_level'] ?? 'low',
            $activityData['location_zone'] ?? null,
            isset($activityData['location_coordinates']) ? json_encode($activityData['location_coordinates']) : null,
            $activityData['description'],
            isset($activityData['evidence_urls']) ? json_encode($activityData['evidence_urls']) : '[]',
            isset($activityData['camera_feeds']) ? json_encode($activityData['camera_feeds']) : '[]',
            isset($activityData['persons_involved']) ? json_encode($activityData['persons_involved']) : '[]',
            $activityData['investigation_required'] ?? true,
            isset($activityData['response_actions']) ? json_encode($activityData['response_actions']) : '[]'
        ]);

        return [
            'report_id' => $reportId,
            'report_number' => $reportNumber,
            'status' => 'reported',
            'message' => 'Suspicious activity reported successfully'
        ];
    }

    /**
     * Get suspicious activity reports
     */
    public function getSuspiciousReports($status = null, $type = null, $startDate = null, $endDate = null)
    {
        $whereClause = "";
        $params = [];

        if ($status) {
            $whereClause .= " AND investigation_status = ?";
            $params[] = $status;
        }

        if ($type) {
            $whereClause .= " AND activity_type = ?";
            $params[] = $type;
        }

        if ($startDate) {
            $whereClause .= " AND DATE(reported_timestamp) >= ?";
            $params[] = $startDate;
        }

        if ($endDate) {
            $whereClause .= " AND DATE(reported_timestamp) <= ?";
            $params[] = $endDate;
        }

        $stmt = $this->db->prepare("
            SELECT * FROM suspicious_activity_reports
            WHERE 1=1 $whereClause
            ORDER BY reported_timestamp DESC
        ");

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Record security incident
     */
    public function recordSecurityIncident($incidentData, $reportedBy)
    {
        $this->logger->info("Recording security incident", $incidentData);

        $stmt = $this->db->prepare("
            INSERT INTO security_incidents (
                incident_id, incident_number, incident_type, severity_level,
                location_zone, location_coordinates, reported_by, initial_assessment,
                response_protocol, evacuation_required, evacuation_zones,
                affected_persons, injuries_reported, injuries_details,
                property_damage, property_damage_details, suspect_description,
                suspect_apprehended, suspect_details, investigation_officer_id,
                legal_actions
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $incidentId = $this->generateIncidentId();
        $incidentNumber = $this->generateIncidentNumber();

        $stmt->execute([
            $incidentId,
            $incidentNumber,
            $incidentData['incident_type'],
            $incidentData['severity_level'] ?? 'medium',
            $incidentData['location_zone'] ?? null,
            isset($incidentData['location_coordinates']) ? json_encode($incidentData['location_coordinates']) : null,
            $reportedBy,
            $incidentData['initial_assessment'] ?? null,
            $incidentData['response_protocol'] ?? null,
            $incidentData['evacuation_required'] ?? false,
            isset($incidentData['evacuation_zones']) ? json_encode($incidentData['evacuation_zones']) : '[]',
            isset($incidentData['affected_persons']) ? json_encode($incidentData['affected_persons']) : '[]',
            $incidentData['injuries_reported'] ?? false,
            isset($incidentData['injuries_details']) ? json_encode($incidentData['injuries_details']) : '[]',
            $incidentData['property_damage'] ?? false,
            isset($incidentData['property_damage_details']) ? json_encode($incidentData['property_damage_details']) : '[]',
            isset($incidentData['suspect_description']) ? json_encode($incidentData['suspect_description']) : null,
            $incidentData['suspect_apprehended'] ?? false,
            isset($incidentData['suspect_details']) ? json_encode($incidentData['suspect_details']) : null,
            $incidentData['investigation_officer_id'] ?? null,
            isset($incidentData['legal_actions']) ? json_encode($incidentData['legal_actions']) : '[]'
        ]);

        // Create emergency response coordination if evacuation required
        if ($incidentData['evacuation_required']) {
            $this->createEmergencyResponse([
                'incident_id' => $incidentId,
                'response_type' => 'evacuation',
                'response_coordinator_id' => $reportedBy,
                'affected_zones' => $incidentData['evacuation_zones'] ?? [],
                'evacuation_routes' => [],
                'assembly_points' => []
            ]);
        }

        return [
            'incident_id' => $incidentId,
            'incident_number' => $incidentNumber,
            'status' => 'recorded',
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
            $whereClause .= " AND DATE(incident_timestamp) >= ?";
            $params[] = $startDate;
        }

        if ($endDate) {
            $whereClause .= " AND DATE(incident_timestamp) <= ?";
            $params[] = $endDate;
        }

        $stmt = $this->db->prepare("
            SELECT * FROM security_incidents
            WHERE 1=1 $whereClause
            ORDER BY incident_timestamp DESC
        ");

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Create security alert
     */
    public function createSecurityAlert($alertData)
    {
        $this->logger->info("Creating security alert", $alertData);

        $stmt = $this->db->prepare("
            INSERT INTO security_alerts (
                alert_id, alert_type, severity_level, alert_title, alert_description,
                location_zone, affected_systems, recommended_actions,
                escalation_required, escalation_contacts, acknowledgment_required,
                resolution_required
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $alertId = $this->generateAlertId();

        $stmt->execute([
            $alertId,
            $alertData['alert_type'],
            $alertData['severity_level'] ?? 'medium',
            $alertData['alert_title'],
            $alertData['alert_description'],
            $alertData['location_zone'] ?? null,
            isset($alertData['affected_systems']) ? json_encode($alertData['affected_systems']) : '[]',
            isset($alertData['recommended_actions']) ? json_encode($alertData['recommended_actions']) : '[]',
            $alertData['escalation_required'] ?? false,
            isset($alertData['escalation_contacts']) ? json_encode($alertData['escalation_contacts']) : '[]',
            $alertData['acknowledgment_required'] ?? true,
            $alertData['resolution_required'] ?? true
        ]);

        return [
            'alert_id' => $alertId,
            'status' => 'created',
            'message' => 'Security alert created successfully'
        ];
    }

    /**
     * Get security alerts
     */
    public function getSecurityAlerts($status = 'unresolved', $severity = null)
    {
        $whereClause = "WHERE 1=1";
        $params = [];

        if ($status === 'unresolved') {
            $whereClause .= " AND resolved_timestamp IS NULL";
        } elseif ($status === 'resolved') {
            $whereClause .= " AND resolved_timestamp IS NOT NULL";
        }

        if ($severity) {
            $whereClause .= " AND severity_level = ?";
            $params[] = $severity;
        }

        $stmt = $this->db->prepare("
            SELECT * FROM security_alerts
            $whereClause
            ORDER BY alert_timestamp DESC
        ");

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Analyze crowd patterns
     */
    public function analyzeCrowd($analysisData)
    {
        $this->logger->info("Analyzing crowd patterns", $analysisData);

        $stmt = $this->db->prepare("
            INSERT INTO crowd_analysis (
                analysis_id, zone_id, crowd_density, crowd_count, crowd_flow_direction,
                crowd_speed_kmh, congestion_points, bottleneck_areas, unusual_patterns,
                risk_assessment, recommended_actions, camera_feeds, ai_model_version
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $analysisId = $this->generateAnalysisId();

        $stmt->execute([
            $analysisId,
            $analysisData['zone_id'],
            $analysisData['crowd_density'] ?? 'low',
            $analysisData['crowd_count'] ?? null,
            $analysisData['crowd_flow_direction'] ?? null,
            $analysisData['crowd_speed_kmh'] ?? null,
            isset($analysisData['congestion_points']) ? json_encode($analysisData['congestion_points']) : '[]',
            isset($analysisData['bottleneck_areas']) ? json_encode($analysisData['bottleneck_areas']) : '[]',
            isset($analysisData['unusual_patterns']) ? json_encode($analysisData['unusual_patterns']) : '[]',
            $analysisData['risk_assessment'] ?? 'low',
            isset($analysisData['recommended_actions']) ? json_encode($analysisData['recommended_actions']) : '[]',
            isset($analysisData['camera_feeds']) ? json_encode($analysisData['camera_feeds']) : '[]',
            $analysisData['ai_model_version'] ?? null
        ]);

        return [
            'analysis_id' => $analysisId,
            'status' => 'analyzed',
            'message' => 'Crowd analysis completed successfully'
        ];
    }

    /**
     * Record policy violation
     */
    public function recordPolicyViolation($violationData)
    {
        $this->logger->info("Recording policy violation", $violationData);

        $stmt = $this->db->prepare("
            INSERT INTO security_policy_violations (
                violation_id, violation_number, person_id, policy_id, policy_name,
                violation_type, severity_level, location_zone, violation_description,
                evidence_urls, corrective_actions_required, warning_issued,
                suspension_applied, suspension_duration_days, legal_action_required,
                follow_up_required
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $violationId = $this->generateViolationId();
        $violationNumber = $this->generateViolationNumber();

        $stmt->execute([
            $violationId,
            $violationNumber,
            $violationData['person_id'] ?? null,
            $violationData['policy_id'] ?? null,
            $violationData['policy_name'] ?? null,
            $violationData['violation_type'],
            $violationData['severity_level'] ?? 'low',
            $violationData['location_zone'] ?? null,
            $violationData['violation_description'],
            isset($violationData['evidence_urls']) ? json_encode($violationData['evidence_urls']) : '[]',
            isset($violationData['corrective_actions_required']) ? json_encode($violationData['corrective_actions_required']) : '[]',
            $violationData['warning_issued'] ?? false,
            $violationData['suspension_applied'] ?? false,
            $violationData['suspension_duration_days'] ?? null,
            $violationData['legal_action_required'] ?? false,
            $violationData['follow_up_required'] ?? true
        ]);

        return [
            'violation_id' => $violationId,
            'violation_number' => $violationNumber,
            'status' => 'recorded',
            'message' => 'Policy violation recorded successfully'
        ];
    }

    /**
     * Create emergency response coordination
     */
    public function createEmergencyResponse($responseData)
    {
        $this->logger->info("Creating emergency response coordination", $responseData);

        $stmt = $this->db->prepare("
            INSERT INTO emergency_response_coordination (
                response_id, incident_id, response_type, response_coordinator_id,
                response_team, communication_channels, affected_zones,
                evacuation_routes, assembly_points, resource_requirements,
                external_agencies_notified
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $responseId = $this->generateResponseId();

        $stmt->execute([
            $responseId,
            $responseData['incident_id'],
            $responseData['response_type'],
            $responseData['response_coordinator_id'],
            isset($responseData['response_team']) ? json_encode($responseData['response_team']) : '[]',
            isset($responseData['communication_channels']) ? json_encode($responseData['communication_channels']) : '[]',
            isset($responseData['affected_zones']) ? json_encode($responseData['affected_zones']) : '[]',
            isset($responseData['evacuation_routes']) ? json_encode($responseData['evacuation_routes']) : '[]',
            isset($responseData['assembly_points']) ? json_encode($responseData['assembly_points']) : '[]',
            isset($responseData['resource_requirements']) ? json_encode($responseData['resource_requirements']) : '[]',
            isset($responseData['external_agencies_notified']) ? json_encode($responseData['external_agencies_notified']) : '[]'
        ]);

        return [
            'response_id' => $responseId,
            'status' => 'created',
            'message' => 'Emergency response coordination created successfully'
        ];
    }

    /**
     * Detect facial matches
     */
    public function detectFacialMatches($faceFeatures, $threshold = 0.85)
    {
        $stmt = $this->db->prepare("
            SELECT detect_facial_matches(?, ?)
        ");

        $stmt->execute([$faceFeatures, $threshold]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return json_decode($result['detect_facial_matches'], true);
    }

    /**
     * Analyze behavioral patterns
     */
    public function analyzeBehavioralPatterns($behaviorData)
    {
        $stmt = $this->db->prepare("
            SELECT analyze_behavioral_patterns(?)
        ");

        $stmt->execute([$behaviorData]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return json_decode($result['analyze_behavioral_patterns'], true);
    }

    /**
     * Assess security risk
     */
    public function assessSecurityRisk($personId, $location, $behaviorData)
    {
        $stmt = $this->db->prepare("
            SELECT assess_security_risk(?, ?, ?)
        ");

        $stmt->execute([$personId, $location, json_encode($behaviorData)]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return json_decode($result['assess_security_risk'], true);
    }

    /**
     * Get security dashboard
     */
    public function getDashboardData()
    {
        $stmt = $this->db->prepare("
            SELECT generate_security_dashboard()
        ");

        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return json_decode($result['generate_security_dashboard'], true);
    }

    /**
     * Generate security report
     */
    public function generateSecurityReport($startDate, $endDate)
    {
        $stmt = $this->db->prepare("
            SELECT generate_security_report(?, ?)
        ");

        $stmt->execute([$startDate, $endDate]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return json_decode($result['generate_security_report'], true);
    }

    // Helper methods

    private function generateFaceId()
    {
        return 'FACE-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
    }

    private function generateBehaviorId()
    {
        return 'BEHAV-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
    }

    private function generateCameraId()
    {
        return 'CAM-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
    }

    private function generateEventId()
    {
        return 'EVENT-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
    }

    private function generateZoneId()
    {
        return 'ZONE-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
    }

    private function generateAccessId()
    {
        return 'ACCESS-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
    }

    private function generateReportId()
    {
        return 'REPORT-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
    }

    private function generateIncidentId()
    {
        return 'INC-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
    }

    private function generateAlertId()
    {
        return 'ALERT-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
    }

    private function generateAnalysisId()
    {
        return 'ANALYSIS-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
    }

    private function generateViolationId()
    {
        return 'VIOL-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
    }

    private function generateResponseId()
    {
        return 'RESP-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
    }

    private function generateReportNumber()
    {
        return 'SAR-' . date('YmdHis');
    }

    private function generateIncidentNumber()
    {
        return 'SI-' . date('YmdHis');
    }

    private function generateViolationNumber()
    {
        return 'SPV-' . date('YmdHis');
    }

    private function updateZoneOccupancy($zoneId, $change)
    {
        $stmt = $this->db->prepare("
            UPDATE security_zones
            SET current_occupancy = GREATEST(0, current_occupancy + ?)
            WHERE zone_id = ?
        ");

        $stmt->execute([$change, $zoneId]);
    }
}
