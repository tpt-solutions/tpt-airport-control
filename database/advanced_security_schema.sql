-- Advanced Security Module Schema
-- Manages facial recognition, behavioral analytics, threat detection, and security monitoring

-- Facial recognition data
CREATE TABLE IF NOT EXISTS facial_recognition_data (
    face_id VARCHAR(50) PRIMARY KEY,
    person_id VARCHAR(50),
    image_data BYTEA, -- encrypted facial image data
    facial_features JSONB NOT NULL, -- facial landmark coordinates and features
    confidence_score DECIMAL(3,2), -- 0.00 to 1.00
    capture_device VARCHAR(100),
    capture_location VARCHAR(100),
    capture_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    image_quality_score DECIMAL(3,2),
    lighting_conditions VARCHAR(20), -- 'good', 'poor', 'dark', 'bright'
    angle_degrees INTEGER,
    distance_meters DECIMAL(4,2),
    occlusion_present BOOLEAN DEFAULT FALSE,
    occlusion_details JSONB DEFAULT '[]',
    glasses_present BOOLEAN DEFAULT FALSE,
    mask_present BOOLEAN DEFAULT FALSE,
    hat_present BOOLEAN DEFAULT FALSE,
    encryption_key_id VARCHAR(100),
    retention_period_days INTEGER DEFAULT 365,
    privacy_consent BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Behavioral analytics
CREATE TABLE IF NOT EXISTS behavioral_analytics (
    behavior_id VARCHAR(50) PRIMARY KEY,
    person_id VARCHAR(50),
    session_id VARCHAR(50),
    behavior_type VARCHAR(30) NOT NULL, -- 'walking', 'running', 'standing', 'sitting', 'interacting', 'loitering'
    location_zone VARCHAR(100),
    start_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    end_timestamp TIMESTAMP,
    duration_seconds INTEGER,
    movement_pattern JSONB DEFAULT '[]', -- path coordinates over time
    interaction_partners JSONB DEFAULT '[]', -- other people interacted with
    suspicious_indicators JSONB DEFAULT '[]', -- behavioral red flags
    risk_score DECIMAL(3,2) DEFAULT 0.0, -- 0.00 to 1.00
    anomaly_detected BOOLEAN DEFAULT FALSE,
    anomaly_type VARCHAR(50),
    anomaly_confidence DECIMAL(3,2),
    camera_feeds JSONB DEFAULT '[]', -- cameras that captured this behavior
    ai_model_version VARCHAR(20),
    processed_by VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Security camera feeds
CREATE TABLE IF NOT EXISTS security_camera_feeds (
    camera_id VARCHAR(50) PRIMARY KEY,
    camera_name VARCHAR(255) NOT NULL,
    camera_type VARCHAR(30) DEFAULT 'ip_camera', -- 'ip_camera', 'thermal', 'infrared', 'ptz', 'dome'
    location_zone VARCHAR(100) NOT NULL,
    location_coordinates JSONB, -- latitude, longitude, altitude
    ip_address INET,
    port INTEGER,
    username VARCHAR(50),
    password_hash VARCHAR(255), -- encrypted
    stream_url VARCHAR(500),
    resolution_width INTEGER,
    resolution_height INTEGER,
    frame_rate INTEGER DEFAULT 30,
    night_vision BOOLEAN DEFAULT FALSE,
    pan_tilt_zoom BOOLEAN DEFAULT FALSE,
    audio_recording BOOLEAN DEFAULT FALSE,
    motion_detection BOOLEAN DEFAULT TRUE,
    facial_recognition_enabled BOOLEAN DEFAULT TRUE,
    behavioral_analytics_enabled BOOLEAN DEFAULT TRUE,
    status VARCHAR(20) DEFAULT 'active', -- 'active', 'inactive', 'maintenance', 'offline'
    last_online TIMESTAMP,
    maintenance_schedule JSONB DEFAULT '{}',
    firmware_version VARCHAR(20),
    storage_retention_days INTEGER DEFAULT 30,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Threat detection events
CREATE TABLE IF NOT EXISTS threat_detection_events (
    event_id VARCHAR(50) PRIMARY KEY,
    event_type VARCHAR(30) NOT NULL, -- 'facial_match', 'behavioral_anomaly', 'unauthorized_access', 'suspicious_package', 'crowd_gathering'
    severity_level VARCHAR(20) DEFAULT 'low', -- 'low', 'medium', 'high', 'critical'
    confidence_score DECIMAL(3,2), -- 0.00 to 1.00
    detection_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    location_zone VARCHAR(100),
    location_coordinates JSONB,
    camera_id VARCHAR(50) REFERENCES security_camera_feeds(camera_id),
    person_id VARCHAR(50),
    facial_match_data JSONB,
    behavioral_data JSONB,
    additional_evidence JSONB DEFAULT '[]',
    ai_model_used VARCHAR(50),
    ai_model_version VARCHAR(20),
    false_positive_probability DECIMAL(3,2),
    response_required BOOLEAN DEFAULT TRUE,
    response_actions JSONB DEFAULT '[]',
    response_timestamp TIMESTAMP,
    response_officer_id VARCHAR(50),
    investigation_status VARCHAR(20) DEFAULT 'pending', -- 'pending', 'investigating', 'resolved', 'false_positive'
    investigation_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Security zones and access control
CREATE TABLE IF NOT EXISTS security_zones (
    zone_id VARCHAR(50) PRIMARY KEY,
    zone_name VARCHAR(255) NOT NULL,
    zone_type VARCHAR(30) DEFAULT 'public', -- 'public', 'restricted', 'secure', 'sterile', 'employee_only'
    security_level VARCHAR(20) DEFAULT 'standard', -- 'standard', 'enhanced', 'high', 'maximum'
    parent_zone_id VARCHAR(50) REFERENCES security_zones(zone_id),
    boundary_coordinates JSONB NOT NULL, -- GeoJSON polygon
    area_sqm DECIMAL(10,2),
    access_requirements JSONB DEFAULT '{}', -- badge, biometric, escort requirements
    surveillance_coverage DECIMAL(5,2), -- percentage of zone covered by cameras
    lighting_level VARCHAR(20) DEFAULT 'normal', -- 'low', 'normal', 'high', 'emergency'
    emergency_procedures JSONB DEFAULT '{}',
    operating_hours JSONB DEFAULT '{}',
    capacity_limit INTEGER,
    current_occupancy INTEGER DEFAULT 0,
    status VARCHAR(20) DEFAULT 'active', -- 'active', 'inactive', 'maintenance', 'emergency'
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Access control events
CREATE TABLE IF NOT EXISTS access_control_events (
    access_id VARCHAR(50) PRIMARY KEY,
    person_id VARCHAR(50),
    zone_id VARCHAR(50) REFERENCES security_zones(zone_id),
    access_type VARCHAR(20) NOT NULL, -- 'entry', 'exit', 'attempted_entry'
    access_method VARCHAR(30), -- 'badge', 'biometric', 'manual', 'emergency'
    access_device VARCHAR(100),
    access_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    authorization_status VARCHAR(20) DEFAULT 'granted', -- 'granted', 'denied', 'escalated'
    denial_reason VARCHAR(50),
    escort_required BOOLEAN DEFAULT FALSE,
    escort_person_id VARCHAR(50),
    security_clearance_level VARCHAR(20),
    required_clearance_level VARCHAR(20),
    biometric_verification JSONB DEFAULT '{}',
    additional_checks JSONB DEFAULT '[]',
    processing_time_ms INTEGER,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Suspicious activity reports
CREATE TABLE IF NOT EXISTS suspicious_activity_reports (
    report_id VARCHAR(50) PRIMARY KEY,
    report_number VARCHAR(100) UNIQUE NOT NULL,
    reporter_id VARCHAR(50),
    reporter_type VARCHAR(20) DEFAULT 'system', -- 'system', 'security_officer', 'passenger', 'employee'
    activity_type VARCHAR(30) NOT NULL, -- 'loitering', 'unauthorized_access', 'suspicious_package', 'aggressive_behavior', 'unattended_baggage'
    severity_level VARCHAR(20) DEFAULT 'low', -- 'low', 'medium', 'high', 'critical'
    location_zone VARCHAR(100),
    location_coordinates JSONB,
    description TEXT,
    evidence_urls JSONB DEFAULT '[]',
    camera_feeds JSONB DEFAULT '[]',
    persons_involved JSONB DEFAULT '[]',
    reported_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    response_timestamp TIMESTAMP,
    response_officer_id VARCHAR(50),
    response_actions JSONB DEFAULT '[]',
    investigation_required BOOLEAN DEFAULT TRUE,
    investigation_status VARCHAR(20) DEFAULT 'pending', -- 'pending', 'investigating', 'resolved', 'unfounded'
    investigation_findings TEXT,
    follow_up_required BOOLEAN DEFAULT FALSE,
    follow_up_date TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Security incidents
CREATE TABLE IF NOT EXISTS security_incidents (
    incident_id VARCHAR(50) PRIMARY KEY,
    incident_number VARCHAR(100) UNIQUE NOT NULL,
    incident_type VARCHAR(30) NOT NULL, -- 'theft', 'assault', 'bomb_threat', 'active_shooter', 'unauthorized_intrusion', 'cyber_attack'
    severity_level VARCHAR(20) DEFAULT 'medium', -- 'low', 'medium', 'high', 'critical'
    incident_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    location_zone VARCHAR(100),
    location_coordinates JSONB,
    reported_by VARCHAR(50),
    reported_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    initial_assessment TEXT,
    response_protocol VARCHAR(50),
    emergency_services_notified BOOLEAN DEFAULT FALSE,
    emergency_services_timestamp TIMESTAMP,
    evacuation_required BOOLEAN DEFAULT FALSE,
    evacuation_zones JSONB DEFAULT '[]',
    affected_persons JSONB DEFAULT '[]',
    injuries_reported BOOLEAN DEFAULT FALSE,
    injuries_details JSONB DEFAULT '[]',
    property_damage BOOLEAN DEFAULT FALSE,
    property_damage_details JSONB DEFAULT '[]',
    suspect_description JSONB,
    suspect_apprehended BOOLEAN DEFAULT FALSE,
    suspect_details JSONB,
    investigation_officer_id VARCHAR(50),
    investigation_status VARCHAR(20) DEFAULT 'ongoing', -- 'ongoing', 'completed', 'closed'
    investigation_findings TEXT,
    legal_actions JSONB DEFAULT '[]',
    resolution_timestamp TIMESTAMP,
    lessons_learned TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- AI threat detection models
CREATE TABLE IF NOT EXISTS ai_threat_models (
    model_id VARCHAR(50) PRIMARY KEY,
    model_name VARCHAR(255) NOT NULL,
    model_type VARCHAR(30) NOT NULL, -- 'facial_recognition', 'behavioral_analysis', 'anomaly_detection', 'crowd_analysis'
    model_version VARCHAR(20) NOT NULL,
    training_data_info JSONB DEFAULT '{}',
    accuracy_score DECIMAL(5,4), -- 0.0000 to 1.0000
    precision_score DECIMAL(5,4),
    recall_score DECIMAL(5,4),
    f1_score DECIMAL(5,4),
    false_positive_rate DECIMAL(5,4),
    deployment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_retrained TIMESTAMP,
    retraining_schedule VARCHAR(20), -- 'daily', 'weekly', 'monthly'
    model_status VARCHAR(20) DEFAULT 'active', -- 'active', 'training', 'deprecated', 'failed'
    performance_metrics JSONB DEFAULT '{}',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Security alerts and notifications
CREATE TABLE IF NOT EXISTS security_alerts (
    alert_id VARCHAR(50) PRIMARY KEY,
    alert_type VARCHAR(30) NOT NULL, -- 'threat_detected', 'system_failure', 'maintenance_required', 'policy_violation'
    severity_level VARCHAR(20) DEFAULT 'medium', -- 'low', 'medium', 'high', 'critical'
    alert_title VARCHAR(255) NOT NULL,
    alert_description TEXT,
    alert_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    location_zone VARCHAR(100),
    affected_systems JSONB DEFAULT '[]',
    recommended_actions JSONB DEFAULT '[]',
    escalation_required BOOLEAN DEFAULT FALSE,
    escalation_contacts JSONB DEFAULT '[]',
    acknowledgment_required BOOLEAN DEFAULT TRUE,
    acknowledged_by VARCHAR(50),
    acknowledged_timestamp TIMESTAMP,
    resolution_required BOOLEAN DEFAULT TRUE,
    resolution_actions JSONB DEFAULT '[]',
    resolved_by VARCHAR(50),
    resolved_timestamp TIMESTAMP,
    resolution_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Security performance metrics
CREATE TABLE IF NOT EXISTS security_performance_metrics (
    metric_id SERIAL PRIMARY KEY,
    date DATE,
    zone_id VARCHAR(50) REFERENCES security_zones(zone_id),
    total_access_attempts INTEGER DEFAULT 0,
    access_granted INTEGER DEFAULT 0,
    access_denied INTEGER DEFAULT 0,
    biometric_verification_attempts INTEGER DEFAULT 0,
    biometric_verification_success INTEGER DEFAULT 0,
    facial_recognition_attempts INTEGER DEFAULT 0,
    facial_recognition_matches INTEGER DEFAULT 0,
    threat_detection_events INTEGER DEFAULT 0,
    false_positive_alerts INTEGER DEFAULT 0,
    response_time_avg_seconds DECIMAL(6,2),
    incident_response_time_avg_minutes DECIMAL(6,2),
    camera_uptime_percentage DECIMAL(5,2),
    system_availability_percentage DECIMAL(5,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Crowd analysis data
CREATE TABLE IF NOT EXISTS crowd_analysis (
    analysis_id VARCHAR(50) PRIMARY KEY,
    zone_id VARCHAR(50) REFERENCES security_zones(zone_id),
    analysis_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    crowd_density VARCHAR(20), -- 'low', 'medium', 'high', 'critical'
    crowd_count INTEGER,
    crowd_flow_direction VARCHAR(10), -- 'N', 'S', 'E', 'W', 'NE', 'NW', 'SE', 'SW'
    crowd_speed_kmh DECIMAL(4,2),
    congestion_points JSONB DEFAULT '[]',
    bottleneck_areas JSONB DEFAULT '[]',
    unusual_patterns JSONB DEFAULT '[]',
    risk_assessment VARCHAR(20) DEFAULT 'low', -- 'low', 'medium', 'high', 'critical'
    recommended_actions JSONB DEFAULT '[]',
    camera_feeds JSONB DEFAULT '[]',
    ai_model_version VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Security policy violations
CREATE TABLE IF NOT EXISTS security_policy_violations (
    violation_id VARCHAR(50) PRIMARY KEY,
    violation_number VARCHAR(100) UNIQUE NOT NULL,
    person_id VARCHAR(50),
    policy_id VARCHAR(50),
    policy_name VARCHAR(255),
    violation_type VARCHAR(30) NOT NULL, -- 'access_violation', 'behavior_violation', 'equipment_violation', 'procedure_violation'
    severity_level VARCHAR(20) DEFAULT 'low', -- 'low', 'medium', 'high', 'critical'
    violation_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    location_zone VARCHAR(100),
    violation_description TEXT,
    evidence_urls JSONB DEFAULT '[]',
    corrective_actions_required JSONB DEFAULT '[]',
    corrective_actions_taken JSONB DEFAULT '[]',
    warning_issued BOOLEAN DEFAULT FALSE,
    suspension_applied BOOLEAN DEFAULT FALSE,
    suspension_duration_days INTEGER,
    legal_action_required BOOLEAN DEFAULT FALSE,
    legal_referral_date TIMESTAMP,
    follow_up_required BOOLEAN DEFAULT TRUE,
    follow_up_date TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Emergency response coordination
CREATE TABLE IF NOT EXISTS emergency_response_coordination (
    response_id VARCHAR(50) PRIMARY KEY,
    incident_id VARCHAR(50) REFERENCES security_incidents(incident_id),
    response_type VARCHAR(30) NOT NULL, -- 'evacuation', 'lockdown', 'medical', 'fire', 'police', 'bomb_squad'
    activation_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    response_coordinator_id VARCHAR(50),
    response_team JSONB DEFAULT '[]',
    communication_channels JSONB DEFAULT '[]',
    affected_zones JSONB DEFAULT '[]',
    evacuation_routes JSONB DEFAULT '[]',
    assembly_points JSONB DEFAULT '[]',
    resource_requirements JSONB DEFAULT '[]',
    external_agencies_notified JSONB DEFAULT '[]',
    status VARCHAR(20) DEFAULT 'active', -- 'active', 'completed', 'stand_down'
    completion_timestamp TIMESTAMP,
    effectiveness_rating INTEGER, -- 1-5 scale
    lessons_learned TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Indexes for performance
CREATE INDEX IF NOT EXISTS idx_facial_recognition_person ON facial_recognition_data(person_id);
CREATE INDEX IF NOT EXISTS idx_facial_recognition_timestamp ON facial_recognition_data(capture_timestamp);
CREATE INDEX IF NOT EXISTS idx_behavioral_person ON behavioral_analytics(person_id);
CREATE INDEX IF NOT EXISTS idx_behavioral_type ON behavioral_analytics(behavior_type);
CREATE INDEX IF NOT EXISTS idx_behavioral_timestamp ON behavioral_analytics(start_timestamp);
CREATE INDEX IF NOT EXISTS idx_camera_location ON security_camera_feeds(location_zone);
CREATE INDEX IF NOT EXISTS idx_camera_status ON security_camera_feeds(status);
CREATE INDEX IF NOT EXISTS idx_threat_type ON threat_detection_events(event_type);
CREATE INDEX IF NOT EXISTS idx_threat_timestamp ON threat_detection_events(detection_timestamp);
CREATE INDEX IF NOT EXISTS idx_threat_severity ON threat_detection_events(severity_level);
CREATE INDEX IF NOT EXISTS idx_zones_type ON security_zones(zone_type);
CREATE INDEX IF NOT EXISTS idx_zones_security_level ON security_zones(security_level);
CREATE INDEX IF NOT EXISTS idx_access_person ON access_control_events(person_id);
CREATE INDEX IF NOT EXISTS idx_access_zone ON access_control_events(zone_id);
CREATE INDEX IF NOT EXISTS idx_access_timestamp ON access_control_events(access_timestamp);
CREATE INDEX IF NOT EXISTS idx_suspicious_type ON suspicious_activity_reports(activity_type);
CREATE INDEX IF NOT EXISTS idx_suspicious_timestamp ON suspicious_activity_reports(reported_timestamp);
CREATE INDEX IF NOT EXISTS idx_incidents_type ON security_incidents(incident_type);
CREATE INDEX IF NOT EXISTS idx_incidents_timestamp ON security_incidents(incident_timestamp);
CREATE INDEX IF NOT EXISTS idx_models_type ON ai_threat_models(model_type);
CREATE INDEX IF NOT EXISTS idx_models_status ON ai_threat_models(model_status);
CREATE INDEX IF NOT EXISTS idx_alerts_type ON security_alerts(alert_type);
CREATE INDEX IF NOT EXISTS idx_alerts_timestamp ON security_alerts(alert_timestamp);
CREATE INDEX IF NOT EXISTS idx_metrics_date ON security_performance_metrics(date);
CREATE INDEX IF NOT EXISTS idx_metrics_zone ON security_performance_metrics(zone_id);
CREATE INDEX IF NOT EXISTS idx_crowd_zone ON crowd_analysis(zone_id);
CREATE INDEX IF NOT EXISTS idx_crowd_timestamp ON crowd_analysis(analysis_timestamp);
CREATE INDEX IF NOT EXISTS idx_violations_type ON security_policy_violations(violation_type);
CREATE INDEX IF NOT EXISTS idx_violations_timestamp ON security_policy_violations(violation_timestamp);
CREATE INDEX IF NOT EXISTS idx_emergency_incident ON emergency_response_coordination(incident_id);
CREATE INDEX IF NOT EXISTS idx_emergency_type ON emergency_response_coordination(response_type);

-- Insert sample security zones
INSERT INTO security_zones (zone_id, zone_name, zone_type, security_level, boundary_coordinates, area_sqm) VALUES
('ZONE-ARRIVAL', 'Arrival Hall', 'public', 'standard', '{"type": "Polygon", "coordinates": [[[0,0], [100,0], [100,50], [0,50], [0,0]]]}', 5000),
('ZONE-SECURITY', 'Security Screening', 'secure', 'high', '{"type": "Polygon", "coordinates": [[[0,0], [50,0], [50,30], [0,30], [0,0]]]}', 1500),
('ZONE-DEPARTURE', 'Departure Lounge', 'restricted', 'enhanced', '{"type": "Polygon", "coordinates": [[[0,0], [150,0], [150,40], [0,40], [0,0]]]}', 6000),
('ZONE-STAFF', 'Staff Only Area', 'employee_only', 'high', '{"type": "Polygon", "coordinates": [[[0,0], [30,0], [30,20], [0,20], [0,0]]]}', 600);

-- Insert sample security cameras
INSERT INTO security_camera_feeds (camera_id, camera_name, location_zone, resolution_width, resolution_height, status) VALUES
('CAM-ARRIVAL-001', 'Arrival Hall Main Entrance', 'ZONE-ARRIVAL', 1920, 1080, 'active'),
('CAM-SECURITY-001', 'Security Checkpoint A', 'ZONE-SECURITY', 1920, 1080, 'active'),
('CAM-DEPARTURE-001', 'Departure Gate A1', 'ZONE-DEPARTURE', 1920, 1080, 'active'),
('CAM-STAFF-001', 'Staff Entrance', 'ZONE-STAFF', 1920, 1080, 'active');

-- Insert sample AI threat models
INSERT INTO ai_threat_models (model_id, model_name, model_type, model_version, accuracy_score, precision_score, recall_score) VALUES
('MODEL-FACIAL-001', 'Advanced Facial Recognition v2.1', 'facial_recognition', '2.1.0', 0.9876, 0.9823, 0.9912),
('MODEL-BEHAVIOR-001', 'Behavioral Anomaly Detection v1.8', 'behavioral_analysis', '1.8.0', 0.9456, 0.9321, 0.9587),
('MODEL-CROWD-001', 'Crowd Analysis Engine v3.2', 'crowd_analysis', '3.2.0', 0.9234, 0.9187, 0.9278);

-- Function to detect facial matches
CREATE OR REPLACE FUNCTION detect_facial_matches(p_face_features JSONB, p_threshold DECIMAL DEFAULT 0.85)
RETURNS JSON AS $$
DECLARE
    v_matches JSON;
BEGIN
    -- This would implement facial recognition matching algorithm
    -- For now, return mock results
    SELECT json_build_object(
        'matches_found', true,
        'best_match', json_build_object(
            'person_id', 'PERSON-001',
            'confidence_score', 0.92,
            'match_details', json_build_object(
                'similarity_score', 0.92,
                'matched_features', '["eyes", "nose", "mouth"]',
                'processing_time_ms', 150
            )
        ),
        'alternative_matches', json_build_array(
            json_build_object(
                'person_id', 'PERSON-002',
                'confidence_score', 0.78
            )
        ),
        'processing_timestamp', CURRENT_TIMESTAMP
    ) INTO v_matches;

    RETURN v_matches;
END;
$$ LANGUAGE plpgsql;

-- Function to analyze behavioral patterns
CREATE OR REPLACE FUNCTION analyze_behavioral_patterns(p_behavior_data JSONB)
RETURNS JSON AS $$
DECLARE
    v_analysis JSON;
BEGIN
    -- This would implement behavioral analysis algorithm
    -- For now, return mock analysis
    SELECT json_build_object(
        'behavior_type', p_behavior_data->>'behavior_type',
        'anomaly_score', 0.15,
        'risk_level', 'low',
        'patterns_detected', json_build_array(
            'normal_walking_pattern',
            'brief_stoppage'
        ),
        'suspicious_indicators', json_build_array(),
        'confidence_score', 0.89,
        'recommendations', json_build_array(
            'continue_monitoring'
        ),
        'processing_timestamp', CURRENT_TIMESTAMP
    ) INTO v_analysis;

    RETURN v_analysis;
END;
$$ LANGUAGE plpgsql;

-- Function to assess security risk
CREATE OR REPLACE FUNCTION assess_security_risk(p_person_id VARCHAR, p_location VARCHAR, p_behavior_data JSONB)
RETURNS JSON AS $$
DECLARE
    v_risk_assessment JSON;
    v_base_risk DECIMAL := 0.0;
BEGIN
    -- Calculate base risk from various factors
    -- Check if person has history of violations
    IF EXISTS (
        SELECT 1 FROM security_policy_violations
        WHERE person_id = p_person_id
        AND violation_timestamp > CURRENT_DATE - INTERVAL '30 days'
    ) THEN
        v_base_risk := v_base_risk + 0.3;
    END IF

    -- Check location security level
    IF p_location IN ('ZONE-SECURITY', 'ZONE-STAFF') THEN
        v_base_risk := v_base_risk + 0.2;
    END IF

    -- Check behavioral anomalies
    IF (p_behavior_data->>'anomaly_score')::DECIMAL > 0.7 THEN
        v_base_risk := v_base_risk + 0.4;
    END IF

    -- Determine risk level
    SELECT json_build_object(
        'person_id', p_person_id,
        'location', p_location,
        'overall_risk_score', LEAST(v_base_risk, 1.0),
        'risk_level', CASE
            WHEN v_base_risk >= 0.8 THEN 'critical'
            WHEN v_base_risk >= 0.6 THEN 'high'
            WHEN v_base_risk >= 0.4 THEN 'medium'
            ELSE 'low'
        END,
        'risk_factors', json_build_array(
            CASE WHEN EXISTS (
                SELECT 1 FROM security_policy_violations
                WHERE person_id = p_person_id
                AND violation_timestamp > CURRENT_DATE - INTERVAL '30 days'
            ) THEN 'recent_violations' END,
            CASE WHEN p_location IN ('ZONE-SECURITY', 'ZONE-STAFF') THEN 'sensitive_location' END,
            CASE WHEN (p_behavior_data->>'anomaly_score')::DECIMAL > 0.7 THEN 'behavioral_anomaly' END
        ),
        'recommended_actions', CASE
            WHEN v_base_risk >= 0.8 THEN json_build_array('immediate_intervention', 'security_alert', 'access_denial')
            WHEN v_base_risk >= 0.6 THEN json_build_array('enhanced_monitoring', 'security_escort')
            WHEN v_base_risk >= 0.4 THEN json_build_array('additional_screening', 'supervision')
            ELSE json_build_array('standard_monitoring')
        END,
        'assessment_timestamp', CURRENT_TIMESTAMP
    ) INTO v_risk_assessment;

    RETURN v_risk_assessment;
END;
$$ LANGUAGE plpgsql;

-- Function to generate security dashboard
CREATE OR REPLACE FUNCTION generate_security_dashboard()
RETURNS JSON AS $$
DECLARE
    result JSON;
BEGIN
    SELECT json_build_object(
        'active_cameras', (
            SELECT COUNT(*) FROM security_camera_feeds
            WHERE status = 'active'
        ),
        'offline_cameras', (
            SELECT COUNT(*) FROM security_camera_feeds
            WHERE status = 'offline'
        ),
        'active_zones', (
            SELECT COUNT(*) FROM security_zones
            WHERE status = 'active'
        ),
        'threat_events_today', (
            SELECT COUNT(*) FROM threat_detection_events
            WHERE DATE(detection_timestamp) = CURRENT_DATE
        ),
        'high_severity_alerts', (
            SELECT COUNT(*) FROM security_alerts
            WHERE severity_level IN ('high', 'critical')
            AND resolved_timestamp IS NULL
        ),
        'active_incidents', (
            SELECT COUNT(*) FROM security_incidents
            WHERE investigation_status IN ('ongoing', 'pending')
        ),
        'facial_recognition_matches_today', (
            SELECT COUNT(*) FROM facial_recognition_data
            WHERE DATE(capture_timestamp) = CURRENT_DATE
            AND confidence_score > 0.8
        ),
        'behavioral_anomalies_today', (
            SELECT COUNT(*) FROM behavioral_analytics
            WHERE DATE(start_timestamp) = CURRENT_DATE
            AND anomaly_detected = true
        ),
        'access_denials_today', (
            SELECT COUNT(*) FROM access_control_events
            WHERE DATE(access_timestamp) = CURRENT_DATE
            AND authorization_status = 'denied'
        ),
        'system_health', json_build_object(
            'camera_uptime_percentage', (
                SELECT ROUND(AVG(
                    CASE WHEN status = 'active' THEN 100 ELSE 0 END
                ), 2) FROM security_camera_feeds
            ),
            'ai_model_performance', (
                SELECT json_build_object(
                    'avg_accuracy', ROUND(AVG(accuracy_score), 4),
                    'models_active', COUNT(*)
                ) FROM ai_threat_models
                WHERE model_status = 'active'
            )
        ),
        'recent_alerts', (
            SELECT json_agg(
                json_build_object(
                    'alert_id', alert_id,
                    'alert_type', alert_type,
                    'severity_level', severity_level,
                    'alert_title', alert_title,
                    'alert_timestamp', alert_timestamp
                )
            )
            FROM security_alerts
            ORDER BY alert_timestamp DESC
            LIMIT 5
        ),
        'zone_occupancy', (
            SELECT json_agg(
                json_build_object(
                    'zone_name', zone_name,
                    'current_occupancy', current_occupancy,
                    'capacity_limit', capacity_limit,
                    'occupancy_percentage', ROUND(
                        (current_occupancy::DECIMAL / NULLIF(capacity_limit, 0)) * 100, 2
                    )
                )
            )
            FROM security_zones
            WHERE capacity_limit IS NOT NULL
        )
    ) INTO result;

    RETURN result;
END;
$$ LANGUAGE plpgsql;

-- Function to generate security report
CREATE OR REPLACE FUNCTION generate_security_report(p_start_date DATE, p_end_date DATE)
RETURNS JSON AS $$
DECLARE
    result JSON;
BEGIN
    SELECT json_build_object(
        'period', json_build_object('start_date', p_start_date, 'end_date', p_end_date),
        'threat_detection', json_build_object(
            'total_events', (
                SELECT COUNT(*) FROM threat_detection_events
                WHERE DATE(detection_timestamp) BETWEEN p_start_date AND p_end_date
            ),
            'by_type', (
                SELECT json_agg(
                    json_build_object(
                        'event_type', event_type,
                        'count', COUNT(*),
                        'avg_confidence', ROUND(AVG(confidence_score), 3)
                    )
                )
                FROM threat_detection_events
                WHERE DATE(detection_timestamp) BETWEEN p_start_date AND p_end_date
                GROUP BY event_type
                ORDER BY COUNT(*) DESC
            ),
            'false_positive_rate', (
                SELECT ROUND(
                    (SELECT COUNT(*) FROM threat_detection_events
                     WHERE DATE(detection_timestamp) BETWEEN p_start_date AND p_end_date
                     AND false_positive_probability > 0.5)::DECIMAL /
                    (SELECT COUNT(*) FROM threat_detection_events
                     WHERE DATE(detection_timestamp) BETWEEN p_start_date AND p_end_date)::DECIMAL * 100, 2
                )
            )
        ),
        'access_control', json_build_object(
            'total_attempts', (
                SELECT COUNT(*) FROM access_control_events
                WHERE DATE(access_timestamp) BETWEEN p_start_date AND p_end_date
            ),
            'access_granted', (
                SELECT COUNT(*) FROM access_control_events
                WHERE DATE(access_timestamp) BETWEEN p_start_date AND p_end_date
                AND authorization_status = 'granted'
            ),
            'access_denied', (
                SELECT COUNT(*) FROM access_control_events
                WHERE DATE(access_timestamp) BETWEEN p_start_date AND p_end_date
                AND authorization_status = 'denied'
            ),
            'biometric_success_rate', (
                SELECT ROUND(
                    (SELECT COUNT(*) FROM access_control_events
                     WHERE DATE(access_timestamp) BETWEEN p_start_date AND p_end_date
                     AND authorization_status = 'granted'
                     AND access_method = 'biometric')::DECIMAL /
                    (SELECT COUNT(*) FROM access_control_events
                     WHERE DATE(access_timestamp) BETWEEN p_start_date AND p_end_date
                     AND access_method = 'biometric')::DECIMAL * 100, 2
                )
            )
        ),
        'security_incidents', json_build_object(
            'total_incidents', (
                SELECT COUNT(*) FROM security_incidents
                WHERE DATE(incident_timestamp) BETWEEN p_start_date AND p_end_date
            ),
            'by_type', (
                SELECT json_agg(
                    json_build_object(
                        'incident_type', incident_type,
                        'count', COUNT(*),
                        'avg_severity', ROUND(AVG(
                            CASE severity_level
                                WHEN 'low' THEN 1
                                WHEN 'medium' THEN 2
                                WHEN 'high' THEN 3
                                WHEN 'critical' THEN 4
                            END
                        ), 2)
                    )
                )
                FROM security_incidents
                WHERE DATE(incident_timestamp) BETWEEN p_start_date AND p_end_date
                GROUP BY incident_type
                ORDER BY COUNT(*) DESC
            ),
            'resolution_rate', (
                SELECT ROUND(
                    (SELECT COUNT(*) FROM security_incidents
                     WHERE DATE(incident_timestamp) BETWEEN p_start_date AND p_end_date
                     AND investigation_status = 'completed')::DECIMAL /
                    (SELECT COUNT(*) FROM security_incidents
                     WHERE DATE(incident_timestamp) BETWEEN p_start_date AND p_end_date)::DECIMAL * 100, 2
                )
            )
        ),
        'system_performance', json_build_object(
            'camera_uptime', (
                SELECT ROUND(AVG(
                    CASE WHEN status = 'active' THEN 100 ELSE 0 END
                ), 2) FROM security_camera_feeds
            ),
            'ai_model_accuracy', (
                SELECT ROUND(AVG(accuracy_score), 4) FROM ai_threat_models
                WHERE model_status = 'active'
            ),
            'response_time_avg', (
                SELECT ROUND(AVG(response_time_avg_seconds), 2)
                FROM security_performance_metrics
                WHERE date BETWEEN p_start_date AND p_end_date
            )
        ),
        'policy_compliance', json_build_object(
            'violations_recorded', (
                SELECT COUNT(*) FROM security_policy_violations
                WHERE DATE(violation_timestamp) BETWEEN p_start_date AND p_end_date
            ),
            'violations_by_type', (
                SELECT json_agg(
                    json_build_object(
                        'violation_type', violation_type,
                        'count', COUNT(*)
                    )
                )
                FROM security_policy_violations
                WHERE DATE(violation_timestamp) BETWEEN p_start_date AND p_end_date
                GROUP BY violation_type
                ORDER BY COUNT(*) DESC
            ),
            'corrective_action_rate', (
                SELECT ROUND(
                    (SELECT COUNT(*) FROM security_policy_violations
                     WHERE DATE(violation_timestamp) BETWEEN p_start_date AND p_end_date
                     AND jsonb_array_length(corrective_actions_taken) > 0)::DECIMAL /
                    (SELECT COUNT(*) FROM security_policy_violations
                     WHERE DATE(violation_timestamp) BETWEEN p_start_date AND p_end_date)::DECIMAL * 100, 2
                )
            )
        )
    ) INTO result;

    RETURN result;
END;
$$ LANGUAGE plpgsql;
