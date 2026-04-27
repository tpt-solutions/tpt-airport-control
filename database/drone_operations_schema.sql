-- Drone/UAV Operations Module Schema
-- Manages drone traffic control, airspace management, regulatory compliance, and UAV operations

-- Drone registrations
CREATE TABLE IF NOT EXISTS drone_registrations (
    drone_id VARCHAR(50) PRIMARY KEY,
    registration_number VARCHAR(100) UNIQUE NOT NULL,
    serial_number VARCHAR(100),
    manufacturer VARCHAR(100) NOT NULL,
    model VARCHAR(100) NOT NULL,
    drone_type VARCHAR(30) NOT NULL, -- 'multirotor', 'fixed_wing', 'hybrid', 'helicopter'
    max_takeoff_weight_kg DECIMAL(6,2),
    max_payload_kg DECIMAL(6,2),
    flight_duration_minutes INTEGER,
    max_speed_kmh DECIMAL(6,2),
    max_altitude_meters INTEGER,
    communication_range_km DECIMAL(6,2),
    gps_enabled BOOLEAN DEFAULT TRUE,
    autonomous_capable BOOLEAN DEFAULT FALSE,
    camera_type VARCHAR(50),
    camera_resolution VARCHAR(20),
    owner_name VARCHAR(255) NOT NULL,
    owner_contact JSONB,
    operator_license_number VARCHAR(50),
    insurance_provider VARCHAR(100),
    insurance_policy_number VARCHAR(50),
    insurance_expiry DATE,
    registration_date DATE DEFAULT CURRENT_DATE,
    registration_expiry DATE,
    operational_status VARCHAR(20) DEFAULT 'active', -- 'active', 'suspended', 'expired', 'decommissioned'
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Airspace zones
CREATE TABLE IF NOT EXISTS airspace_zones (
    zone_id VARCHAR(50) PRIMARY KEY,
    zone_name VARCHAR(255) NOT NULL,
    zone_type VARCHAR(30) NOT NULL, -- 'controlled', 'restricted', 'prohibited', 'warning', 'alert'
    zone_class VARCHAR(10), -- 'A', 'B', 'C', 'D', 'E', 'G'
    lower_altitude_msl INTEGER,
    upper_altitude_msl INTEGER,
    geometry_type VARCHAR(20) DEFAULT 'polygon', -- 'polygon', 'circle', 'corridor'
    coordinates JSONB NOT NULL, -- GeoJSON format
    center_latitude DECIMAL(10,7),
    center_longitude DECIMAL(10,7),
    radius_meters INTEGER,
    area_sqm DECIMAL(12,2),
    controlling_authority VARCHAR(100),
    contact_info JSONB,
    operational_hours JSONB DEFAULT '{}',
    weather_restrictions JSONB DEFAULT '{}',
    special_requirements TEXT,
    effective_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expiry_date TIMESTAMP,
    status VARCHAR(20) DEFAULT 'active', -- 'active', 'inactive', 'temporary'
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Flight plans
CREATE TABLE IF NOT EXISTS drone_flight_plans (
    flight_plan_id VARCHAR(50) PRIMARY KEY,
    flight_plan_number VARCHAR(100) UNIQUE NOT NULL,
    drone_id VARCHAR(50) REFERENCES drone_registrations(drone_id),
    operator_id VARCHAR(50),
    operator_name VARCHAR(255),
    purpose VARCHAR(50) NOT NULL, -- 'aerial_photography', 'surveying', 'delivery', 'inspection', 'recreation', 'commercial'
    flight_type VARCHAR(30) DEFAULT 'vloss', -- 'vloss', 'bvloss', 'commercial'
    planned_departure TIMESTAMP,
    planned_arrival TIMESTAMP,
    duration_minutes INTEGER,
    max_altitude_meters INTEGER,
    flight_path JSONB NOT NULL, -- GeoJSON LineString
    takeoff_location JSONB,
    landing_location JSONB,
    alternate_landing JSONB,
    weather_minimums JSONB DEFAULT '{}',
    emergency_procedures TEXT,
    communication_plan JSONB DEFAULT '{}',
    approval_required BOOLEAN DEFAULT TRUE,
    approved_by VARCHAR(50),
    approved_at TIMESTAMP,
    approval_notes TEXT,
    status VARCHAR(30) DEFAULT 'planned', -- 'planned', 'approved', 'active', 'completed', 'cancelled', 'rejected'
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Flight operations
CREATE TABLE IF NOT EXISTS drone_flight_operations (
    operation_id VARCHAR(50) PRIMARY KEY,
    flight_plan_id VARCHAR(50) REFERENCES drone_flight_plans(flight_plan_id),
    drone_id VARCHAR(50) REFERENCES drone_registrations(drone_id),
    actual_departure TIMESTAMP,
    actual_arrival TIMESTAMP,
    actual_duration_minutes INTEGER,
    actual_max_altitude_meters INTEGER,
    actual_flight_path JSONB,
    weather_conditions JSONB DEFAULT '{}',
    incidents_reported JSONB DEFAULT '[]',
    operational_notes TEXT,
    fuel_consumption DECIMAL(6,2),
    battery_cycles_used INTEGER,
    maintenance_required BOOLEAN DEFAULT FALSE,
    maintenance_notes TEXT,
    status VARCHAR(20) DEFAULT 'completed', -- 'in_progress', 'completed', 'aborted', 'emergency_landing'
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Airspace reservations
CREATE TABLE IF NOT EXISTS airspace_reservations (
    reservation_id VARCHAR(50) PRIMARY KEY,
    reservation_number VARCHAR(100) UNIQUE NOT NULL,
    zone_id VARCHAR(50) REFERENCES airspace_zones(zone_id),
    drone_id VARCHAR(50) REFERENCES drone_registrations(drone_id),
    operator_id VARCHAR(50),
    reservation_start TIMESTAMP,
    reservation_end TIMESTAMP,
    reservation_type VARCHAR(30) DEFAULT 'exclusive', -- 'exclusive', 'shared', 'temporary'
    altitude_min_msl INTEGER,
    altitude_max_msl INTEGER,
    activity_type VARCHAR(50),
    approval_required BOOLEAN DEFAULT TRUE,
    approved_by VARCHAR(50),
    approved_at TIMESTAMP,
    approval_conditions TEXT,
    status VARCHAR(20) DEFAULT 'requested', -- 'requested', 'approved', 'active', 'completed', 'cancelled', 'rejected'
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Drone telemetry
CREATE TABLE IF NOT EXISTS drone_telemetry (
    telemetry_id SERIAL PRIMARY KEY,
    drone_id VARCHAR(50) REFERENCES drone_registrations(drone_id),
    operation_id VARCHAR(50) REFERENCES drone_flight_operations(operation_id),
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    latitude DECIMAL(10,7),
    longitude DECIMAL(10,7),
    altitude_msl DECIMAL(7,2),
    altitude_agl DECIMAL(7,2),
    ground_speed_kmh DECIMAL(6,2),
    heading_degrees DECIMAL(5,1),
    battery_voltage DECIMAL(4,2),
    battery_percentage DECIMAL(5,2),
    signal_strength_dbm INTEGER,
    gps_satellites INTEGER,
    temperature_celsius DECIMAL(5,2),
    vibration_g DECIMAL(4,2),
    wind_speed_kmh DECIMAL(5,2),
    wind_direction_degrees DECIMAL(5,1),
    payload_status JSONB DEFAULT '{}',
    system_status JSONB DEFAULT '{}',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Drone maintenance logs
CREATE TABLE IF NOT EXISTS drone_maintenance_logs (
    maintenance_id SERIAL PRIMARY KEY,
    drone_id VARCHAR(50) REFERENCES drone_registrations(drone_id),
    maintenance_type VARCHAR(30) NOT NULL, -- 'preventive', 'corrective', 'inspection', 'software_update'
    maintenance_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    performed_by VARCHAR(50),
    technician_certification VARCHAR(50),
    maintenance_description TEXT,
    parts_replaced JSONB DEFAULT '[]',
    parts_ordered JSONB DEFAULT '[]',
    labor_hours DECIMAL(4,2),
    cost DECIMAL(8,2),
    next_maintenance_date TIMESTAMP,
    maintenance_interval_hours INTEGER,
    flight_hours_at_maintenance INTEGER,
    battery_cycles_at_maintenance INTEGER,
    maintenance_status VARCHAR(20) DEFAULT 'completed', -- 'scheduled', 'in_progress', 'completed', 'deferred'
    compliance_check_passed BOOLEAN DEFAULT TRUE,
    compliance_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Regulatory compliance
CREATE TABLE IF NOT EXISTS drone_compliance_records (
    compliance_id SERIAL PRIMARY KEY,
    drone_id VARCHAR(50) REFERENCES drone_registrations(drone_id),
    compliance_type VARCHAR(50) NOT NULL, -- 'faa_registration', 'insurance', 'operator_license', 'maintenance', 'software_update'
    compliance_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expiry_date TIMESTAMP,
    compliance_status VARCHAR(20) DEFAULT 'compliant', -- 'compliant', 'warning', 'non_compliant', 'expired'
    issuing_authority VARCHAR(100),
    certificate_number VARCHAR(100),
    compliance_notes TEXT,
    renewal_required BOOLEAN DEFAULT FALSE,
    renewal_deadline TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- No-fly zones
CREATE TABLE IF NOT EXISTS no_fly_zones (
    zone_id VARCHAR(50) PRIMARY KEY,
    zone_name VARCHAR(255) NOT NULL,
    zone_type VARCHAR(30) NOT NULL, -- 'airport', 'military', 'nuclear', 'prison', 'hospital', 'school', 'event'
    reason TEXT,
    geometry_type VARCHAR(20) DEFAULT 'polygon',
    coordinates JSONB NOT NULL,
    center_latitude DECIMAL(10,7),
    center_longitude DECIMAL(10,7),
    radius_meters INTEGER,
    lower_altitude_msl INTEGER DEFAULT 0,
    upper_altitude_msl INTEGER DEFAULT 400,
    permanent BOOLEAN DEFAULT TRUE,
    effective_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    effective_end TIMESTAMP,
    controlling_authority VARCHAR(100),
    contact_info JSONB,
    enforcement_level VARCHAR(20) DEFAULT 'strict', -- 'strict', 'advisory', 'recommended'
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Drone incidents
CREATE TABLE IF NOT EXISTS drone_incidents (
    incident_id VARCHAR(50) PRIMARY KEY,
    incident_number VARCHAR(100) UNIQUE NOT NULL,
    drone_id VARCHAR(50) REFERENCES drone_registrations(drone_id),
    operation_id VARCHAR(50) REFERENCES drone_flight_operations(operation_id),
    incident_type VARCHAR(30) NOT NULL, -- 'collision', 'loss_of_control', 'communication_failure', 'battery_failure', 'weather_related', 'operator_error'
    severity_level VARCHAR(20) DEFAULT 'minor', -- 'minor', 'moderate', 'major', 'critical'
    incident_location JSONB,
    incident_altitude DECIMAL(7,2),
    weather_conditions JSONB DEFAULT '{}',
    incident_description TEXT,
    contributing_factors JSONB DEFAULT '[]',
    injuries BOOLEAN DEFAULT FALSE,
    injuries_description TEXT,
    property_damage BOOLEAN DEFAULT FALSE,
    property_damage_description TEXT,
    reported_by VARCHAR(50),
    reported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    investigated_by VARCHAR(50),
    investigation_date TIMESTAMP,
    investigation_findings TEXT,
    corrective_actions JSONB DEFAULT '[]',
    regulatory_notification_required BOOLEAN DEFAULT FALSE,
    regulatory_notification_date TIMESTAMP,
    status VARCHAR(20) DEFAULT 'reported', -- 'reported', 'investigating', 'resolved', 'closed'
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Drone traffic management
CREATE TABLE IF NOT EXISTS drone_traffic_management (
    traffic_id SERIAL PRIMARY KEY,
    airspace_sector VARCHAR(10),
    time_slot TIMESTAMP,
    max_concurrent_drones INTEGER DEFAULT 1,
    current_active_drones INTEGER DEFAULT 0,
    weather_conditions JSONB DEFAULT '{}',
    visibility_meters INTEGER,
    wind_speed_kmh DECIMAL(5,2),
    temperature_celsius DECIMAL(5,2),
    traffic_density VARCHAR(20) DEFAULT 'low', -- 'low', 'medium', 'high', 'restricted'
    restrictions_active JSONB DEFAULT '[]',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Operator certifications
CREATE TABLE IF NOT EXISTS operator_certifications (
    certification_id VARCHAR(50) PRIMARY KEY,
    operator_id VARCHAR(50),
    operator_name VARCHAR(255) NOT NULL,
    certification_type VARCHAR(30) NOT NULL, -- 'part_107', 'waiver', 'special_authorization', 'maintenance_technician'
    certification_number VARCHAR(100) UNIQUE,
    issuing_authority VARCHAR(100),
    issue_date DATE,
    expiry_date DATE,
    certification_class VARCHAR(20), -- 'basic', 'advanced', 'instructor', 'maintenance'
    special_privileges JSONB DEFAULT '[]',
    training_records JSONB DEFAULT '[]',
    flight_experience_hours INTEGER,
    status VARCHAR(20) DEFAULT 'active', -- 'active', 'expired', 'suspended', 'revoked'
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Drone performance metrics
CREATE TABLE IF NOT EXISTS drone_performance_metrics (
    metric_id SERIAL PRIMARY KEY,
    drone_id VARCHAR(50) REFERENCES drone_registrations(drone_id),
    date DATE,
    total_flight_hours DECIMAL(6,2),
    total_flight_cycles INTEGER,
    average_flight_duration DECIMAL(5,2),
    average_battery_usage DECIMAL(5,2),
    maintenance_incidents INTEGER DEFAULT 0,
    operational_efficiency DECIMAL(5,2),
    compliance_score DECIMAL(5,2),
    safety_incidents INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Drone airspace violations
CREATE TABLE IF NOT EXISTS airspace_violations (
    violation_id SERIAL PRIMARY KEY,
    drone_id VARCHAR(50) REFERENCES drone_registrations(drone_id),
    operation_id VARCHAR(50) REFERENCES drone_flight_operations(operation_id),
    violation_type VARCHAR(30) NOT NULL, -- 'altitude', 'airspace', 'speed', 'no_fly_zone', 'registration', 'operator_certification'
    violation_location JSONB,
    violation_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    violation_description TEXT,
    severity_level VARCHAR(20) DEFAULT 'minor', -- 'minor', 'moderate', 'major', 'critical'
    corrective_action_required BOOLEAN DEFAULT TRUE,
    corrective_action_taken TEXT,
    fine_amount DECIMAL(8,2),
    points_assessed INTEGER DEFAULT 0,
    reported_to_authority BOOLEAN DEFAULT FALSE,
    authority_reference_number VARCHAR(100),
    status VARCHAR(20) DEFAULT 'open', -- 'open', 'investigating', 'resolved', 'closed'
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Indexes for performance
CREATE INDEX IF NOT EXISTS idx_drone_registrations_owner ON drone_registrations(owner_name);
CREATE INDEX IF NOT EXISTS idx_drone_registrations_status ON drone_registrations(operational_status);
CREATE INDEX IF NOT EXISTS idx_airspace_zones_type ON airspace_zones(zone_type);
CREATE INDEX IF NOT EXISTS idx_airspace_zones_status ON airspace_zones(status);
CREATE INDEX IF NOT EXISTS idx_flight_plans_drone ON drone_flight_plans(drone_id);
CREATE INDEX IF NOT EXISTS idx_flight_plans_status ON drone_flight_plans(status);
CREATE INDEX IF NOT EXISTS idx_flight_plans_date ON drone_flight_plans(planned_departure);
CREATE INDEX IF NOT EXISTS idx_flight_operations_drone ON drone_flight_operations(drone_id);
CREATE INDEX IF NOT EXISTS idx_flight_operations_date ON drone_flight_operations(actual_departure);
CREATE INDEX IF NOT EXISTS idx_reservations_zone ON airspace_reservations(zone_id);
CREATE INDEX IF NOT EXISTS idx_reservations_status ON airspace_reservations(status);
CREATE INDEX IF NOT EXISTS idx_telemetry_drone ON drone_telemetry(drone_id);
CREATE INDEX IF NOT EXISTS idx_telemetry_timestamp ON drone_telemetry(timestamp);
CREATE INDEX IF NOT EXISTS idx_maintenance_drone ON drone_maintenance_logs(drone_id);
CREATE INDEX IF NOT EXISTS idx_maintenance_date ON drone_maintenance_logs(maintenance_date);
CREATE INDEX IF NOT EXISTS idx_compliance_drone ON drone_compliance_records(drone_id);
CREATE INDEX IF NOT EXISTS idx_compliance_type ON drone_compliance_records(compliance_type);
CREATE INDEX IF NOT EXISTS idx_no_fly_zones_type ON no_fly_zones(zone_type);
CREATE INDEX IF NOT EXISTS idx_incidents_drone ON drone_incidents(drone_id);
CREATE INDEX IF NOT EXISTS idx_incidents_type ON drone_incidents(incident_type);
CREATE INDEX IF NOT EXISTS idx_traffic_sector ON drone_traffic_management(airspace_sector);
CREATE INDEX IF NOT EXISTS idx_traffic_time ON drone_traffic_management(time_slot);
CREATE INDEX IF NOT EXISTS idx_certifications_operator ON operator_certifications(operator_id);
CREATE INDEX IF NOT EXISTS idx_certifications_status ON operator_certifications(status);
CREATE INDEX IF NOT EXISTS idx_performance_drone ON drone_performance_metrics(drone_id);
CREATE INDEX IF NOT EXISTS idx_performance_date ON drone_performance_metrics(date);
CREATE INDEX IF NOT EXISTS idx_violations_drone ON airspace_violations(drone_id);
CREATE INDEX IF NOT EXISTS idx_violations_type ON airspace_violations(violation_type);

-- Insert sample drone registrations
INSERT INTO drone_registrations (drone_id, registration_number, manufacturer, model, drone_type, max_takeoff_weight_kg, owner_name, operator_license_number) VALUES
('DRONE001', 'FAA-12345', 'DJI', 'Mavic 3', 'multirotor', 0.9, 'Airport Authority', 'PILOT-001'),
('DRONE002', 'FAA-12346', 'Parrot', 'Anafi', 'multirotor', 0.32, 'Airport Security', 'PILOT-002'),
('DRONE003', 'FAA-12347', 'Autel', 'Evo II', 'multirotor', 1.1, 'Maintenance Division', 'PILOT-003'),
('DRONE004', 'FAA-12348', 'Freefly', 'Alta X', 'hybrid', 3.2, 'Survey Department', 'PILOT-004');

-- Insert sample airspace zones
INSERT INTO airspace_zones (zone_id, zone_name, zone_type, zone_class, lower_altitude_msl, upper_altitude_msl, center_latitude, center_longitude, radius_meters) VALUES
('ZONE-AIRPORT', 'Airport Controlled Airspace', 'controlled', 'D', 0, 3000, 40.6413, -73.7781, 5000),
('ZONE-RUNWAY', 'Runway Protection Zone', 'restricted', 'B', 0, 150, 40.6413, -73.7781, 2000),
('ZONE-CARGO', 'Cargo Terminal Airspace', 'controlled', 'D', 0, 400, 40.6413, -73.7781, 1000),
('ZONE-PARKING', 'Parking Structure Airspace', 'warning', 'E', 0, 200, 40.6413, -73.7781, 500);

-- Insert sample no-fly zones
INSERT INTO no_fly_zones (zone_id, zone_name, zone_type, reason, center_latitude, center_longitude, radius_meters) VALUES
('NFZ-HOSPITAL', 'Local Hospital', 'hospital', 'Medical facility protection', 40.6500, -73.7800, 1000),
('NFZ-SCHOOL', 'Elementary School', 'school', 'Educational facility protection', 40.6350, -73.7750, 800),
('NFZ-PRISON', 'Correctional Facility', 'prison', 'Security facility protection', 40.6300, -73.7900, 1200);

-- Insert sample operator certifications
INSERT INTO operator_certifications (certification_id, operator_id, operator_name, certification_type, certification_number, certification_class) VALUES
('CERT-001', 'PILOT-001', 'John Smith', 'part_107', 'CERT-12345', 'advanced'),
('CERT-002', 'PILOT-002', 'Jane Doe', 'part_107', 'CERT-12346', 'basic'),
('CERT-003', 'PILOT-003', 'Bob Johnson', 'part_107', 'CERT-12347', 'advanced'),
('CERT-004', 'PILOT-004', 'Alice Brown', 'part_107', 'CERT-12348', 'instructor');

-- Function to create drone flight plan
CREATE OR REPLACE FUNCTION create_drone_flight_plan(
    p_plan_data JSONB,
    p_created_by VARCHAR
) RETURNS VARCHAR AS $$
DECLARE
    v_plan_id VARCHAR(50);
    v_plan_number VARCHAR(100);
BEGIN
    -- Generate plan ID and number
    v_plan_id := 'PLAN-' || TO_CHAR(CURRENT_TIMESTAMP, 'YYYYMMDD') || '-' || LPAD(NEXTVAL('flight_plan_seq')::TEXT, 6, '0');
    v_plan_number := 'DFP-' || TO_CHAR(CURRENT_TIMESTAMP, 'YYYYMMDDHH24MISS');

    -- Insert flight plan
    INSERT INTO drone_flight_plans (
        flight_plan_id, flight_plan_number, drone_id, operator_id, operator_name,
        purpose, flight_type, planned_departure, planned_arrival, max_altitude_meters,
        flight_path, takeoff_location, landing_location, emergency_procedures
    ) VALUES (
        v_plan_id,
        v_plan_number,
        p_plan_data->>'drone_id',
        p_created_by,
        p_plan_data->>'operator_name',
        COALESCE(p_plan_data->>'purpose', 'inspection'),
        COALESCE(p_plan_data->>'flight_type', 'vloss'),
        (p_plan_data->>'planned_departure')::TIMESTAMP,
        (p_plan_data->>'planned_arrival')::TIMESTAMP,
        (p_plan_data->>'max_altitude_meters')::INTEGER,
        p_plan_data->'flight_path',
        p_plan_data->'takeoff_location',
        p_plan_data->'landing_location',
        p_plan_data->>'emergency_procedures'
    );

    RETURN v_plan_id;
END;
$$ LANGUAGE plpgsql;

-- Function to check airspace conflicts
CREATE OR REPLACE FUNCTION check_airspace_conflicts(
    p_flight_path JSONB,
    p_altitude_min INTEGER,
    p_altitude_max INTEGER,
    p_start_time TIMESTAMP,
    p_end_time TIMESTAMP
) RETURNS JSON AS $$
DECLARE
    v_conflicts JSON;
BEGIN
    -- This would implement complex airspace conflict checking
    -- For now, return basic conflict assessment
    SELECT json_build_object(
        'has_conflicts', false,
        'conflicts', '[]'::jsonb,
        'warnings', '[]'::jsonb,
        'clearance_required', true,
        'recommended_altitude', p_altitude_min + 50
    ) INTO v_conflicts;

    RETURN v_conflicts;
END;
$$ LANGUAGE plpgsql;

-- Function to calculate flight risk score
CREATE OR REPLACE FUNCTION calculate_flight_risk_score(p_drone_id VARCHAR, p_flight_plan JSONB)
RETURNS DECIMAL AS $$
DECLARE
    v_risk_score DECIMAL := 0.0;
    v_drone_record RECORD;
BEGIN
    -- Get drone details
    SELECT * INTO v_drone_record FROM drone_registrations WHERE drone_id = p_drone_id;

    IF NOT FOUND THEN
        RETURN 10.0; -- High risk for unknown drone
    END IF;

    -- Base risk factors
    IF v_drone_record.operational_status != 'active' THEN
        v_risk_score := v_risk_score + 3.0;
    END IF;

    IF v_drone_record.insurance_expiry < CURRENT_DATE + INTERVAL '30 days' THEN
        v_risk_score := v_risk_score + 2.0;
    END IF;

    -- Flight plan risk factors
    IF (p_flight_plan->>'max_altitude_meters')::INTEGER > 400 THEN
        v_risk_score := v_risk_score + 1.5;
    END IF;

    IF p_flight_plan->>'purpose' = 'commercial' THEN
        v_risk_score := v_risk_score + 1.0;
    END IF;

    -- Ensure score is between 0 and 10
    RETURN GREATEST(0, LEAST(10, v_risk_score));
END;
$$ LANGUAGE plpgsql;

-- Function to validate drone registration
CREATE OR REPLACE FUNCTION validate_drone_registration(p_drone_id VARCHAR)
RETURNS JSON AS $$
DECLARE
    v_validation JSON;
    v_drone_record RECORD;
BEGIN
    -- Get drone details
    SELECT * INTO v_drone_record FROM drone_registrations WHERE drone_id = p_drone_id;

    IF NOT FOUND THEN
        RETURN json_build_object(
            'valid', false,
            'reason', 'Drone not found in registration database'
        );
    END IF;

    -- Check registration status
    IF v_drone_record.operational_status != 'active' THEN
        RETURN json_build_object(
            'valid', false,
            'reason', 'Drone registration is not active'
        );
    END IF;

    -- Check registration expiry
    IF v_drone_record.registration_expiry < CURRENT_DATE THEN
        RETURN json_build_object(
            'valid', false,
            'reason', 'Drone registration has expired'
        );
    END IF;

    -- Check insurance
    IF v_drone_record.insurance_expiry < CURRENT_DATE THEN
        RETURN json_build_object(
            'valid', false,
            'reason', 'Drone insurance has expired'
        );
    END IF;

    RETURN json_build_object(
        'valid', true,
        'registration_status', v_drone_record.operational_status,
        'registration_expiry', v_drone_record.registration_expiry,
        'insurance_expiry', v_drone_record.insurance_expiry
    );
END;
$$ LANGUAGE plpgsql;

-- Function to get drone dashboard data
CREATE OR REPLACE FUNCTION get_drone_dashboard()
RETURNS JSON AS $$
DECLARE
    result JSON;
BEGIN
    SELECT json_build_object(
        'active_drones', (
            SELECT COUNT(*) FROM drone_registrations
            WHERE operational_status = 'active'
        ),
        'flights_today', (
            SELECT COUNT(*) FROM drone_flight_operations
            WHERE DATE(actual_departure) = CURRENT_DATE
        ),
        'pending_approvals', (
            SELECT COUNT(*) FROM drone_flight_plans
            WHERE status = 'planned' AND approval_required = true
        ),
        'airspace_reservations', (
            SELECT COUNT(*) FROM airspace_reservations
            WHERE status = 'active'
        ),
        'active_violations', (
            SELECT COUNT(*) FROM airspace_violations
            WHERE status = 'open'
        ),
        'maintenance_due', (
            SELECT COUNT(*) FROM drone_maintenance_logs
            WHERE next_maintenance_date <= CURRENT_DATE + INTERVAL '7 days'
        ),
        'compliance_issues', (
            SELECT COUNT(*) FROM drone_compliance_records
            WHERE compliance_status IN ('warning', 'non_compliant')
            AND expiry_date <= CURRENT_DATE + INTERVAL '30 days'
        ),
        'recent_incidents', (
            SELECT json_agg(
                json_build_object(
                    'incident_id', incident_id,
                    'drone_id', drone_id,
                    'incident_type', incident_type,
                    'severity_level', severity_level,
                    'reported_at', reported_at
                )
            )
            FROM drone_incidents
            ORDER BY reported_at DESC
            LIMIT 5
        ),
        'traffic_density', (
            SELECT json_build_object(
                'low', COUNT(CASE WHEN traffic_density = 'low' THEN 1 END),
                'medium', COUNT(CASE WHEN traffic_density = 'medium' THEN 1 END),
                'high', COUNT(CASE WHEN traffic_density = 'high' THEN 1 END),
                'restricted', COUNT(CASE WHEN traffic_density = 'restricted' THEN 1 END)
            )
            FROM drone_traffic_management
            WHERE time_slot >= CURRENT_TIMESTAMP - INTERVAL '1 hour'
        )
    ) INTO result;

    RETURN result;
END;
$$ LANGUAGE plpgsql;

-- Function to generate drone operations report
CREATE OR REPLACE FUNCTION generate_drone_operations_report(p_start_date DATE, p_end_date DATE)
RETURNS JSON AS $$
DECLARE
    result JSON;
BEGIN
    SELECT json_build_object(
        'period', json_build_object('start_date', p_start_date, 'end_date', p_end_date),
        'flight_operations', json_build_object(
            'total_flights', (
                SELECT COUNT(*) FROM drone_flight_operations
                WHERE DATE(actual_departure) BETWEEN p_start_date AND p_end_date
            ),
            'by_purpose', (
                SELECT json_agg(
                    json_build_object(
                        'purpose', purpose,
                        'count', COUNT(*),
                        'avg_duration', ROUND(AVG(actual_duration_minutes), 2)
                    )
                )
                FROM drone_flight_operations dfo
                JOIN drone_flight_plans dfp ON dfo.flight_plan_id = dfp.flight_plan_id
                WHERE DATE(dfo.actual_departure) BETWEEN p_start_date AND p_end_date
                GROUP BY purpose
            ),
            'completion_rate', ROUND(
                (SELECT COUNT(*) FROM drone_flight_operations
                 WHERE DATE(actual_departure) BETWEEN p_start_date AND p_end_date
                 AND status = 'completed')::DECIMAL /
                (SELECT COUNT(*) FROM drone_flight_operations
                 WHERE DATE(actual_departure) BETWEEN p_start_date AND p_end_date)::DECIMAL * 100, 2
            )
        ),
        'safety_metrics', json_build_object(
            'total_incidents', (
                SELECT COUNT(*) FROM drone_incidents
                WHERE DATE(reported_at) BETWEEN p_start_date AND p_end_date
            ),
            'incident_rate', ROUND(
                (SELECT COUNT(*) FROM drone_incidents
                 WHERE DATE(reported_at) BETWEEN p_start_date AND p_end_date)::DECIMAL /
                (SELECT COUNT(*) FROM drone_flight_operations
                 WHERE DATE(actual_departure) BETWEEN p_start_date AND p_end_date)::DECIMAL * 100, 2
            ),
            'violations_count', (
                SELECT COUNT(*) FROM airspace_violations
                WHERE DATE(violation_timestamp) BETWEEN p_start_date AND p_end_date
            )
        ),
        'compliance_status', json_build_object(
            'compliant_registrations', (
                SELECT COUNT(*) FROM drone_registrations
                WHERE operational_status = 'active'
                AND registration_expiry > CURRENT_DATE
            ),
            'expired_registrations', (
                SELECT COUNT(*) FROM drone_registrations
                WHERE registration_expiry <= CURRENT_DATE
            ),
            'maintenance_compliance', ROUND(
                (SELECT COUNT(*) FROM drone_maintenance_logs
                 WHERE maintenance_date BETWEEN p_start_date AND p_end_date
                 AND maintenance_status = 'completed')::DECIMAL /
                (SELECT COUNT(*) FROM drone_maintenance_logs
                 WHERE maintenance_date BETWEEN p_start_date AND p_end_date)::DECIMAL * 100, 2
            )
        ),
        'airspace_utilization', json_build_object(
            'reservations_approved', (
                SELECT COUNT(*) FROM airspace_reservations
                WHERE status = 'approved'
                AND reservation_start BETWEEN p_start_date AND p_end_date
            ),
            'peak_traffic_hours', (
                SELECT json_agg(
                    json_build_object(
                        'hour', EXTRACT(HOUR FROM time_slot),
                        'avg_drones', ROUND(AVG(current_active_drones), 2)
                    )
                )
                FROM drone_traffic_management
                WHERE DATE(time_slot) BETWEEN p_start_date AND p_end_date
                GROUP BY EXTRACT(HOUR FROM time_slot)
                ORDER BY EXTRACT(HOUR FROM time_slot)
            )
        )
    ) INTO result;

    RETURN result;
END;
$$ LANGUAGE plpgsql;
