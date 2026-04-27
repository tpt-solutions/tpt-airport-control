-- Emergency Management Module Schema
-- Manages emergency protocols, incident response, crisis management, and disaster recovery

-- Emergency protocols
CREATE TABLE IF NOT EXISTS emergency_protocols (
    protocol_id VARCHAR(50) PRIMARY KEY,
    protocol_name VARCHAR(255) NOT NULL,
    protocol_type VARCHAR(50) NOT NULL, -- 'fire', 'medical', 'security', 'weather', 'structural', 'chemical', 'biological', 'radiation'
    severity_level VARCHAR(20) DEFAULT 'medium', -- 'low', 'medium', 'high', 'critical'
    description TEXT,
    activation_criteria TEXT,
    response_procedures JSONB NOT NULL,
    evacuation_routes JSONB DEFAULT '[]',
    assembly_points JSONB DEFAULT '[]',
    communication_plan JSONB DEFAULT '{}',
    resource_requirements JSONB DEFAULT '{}',
    coordination_roles JSONB DEFAULT '{}',
    estimated_response_time INTERVAL,
    last_reviewed TIMESTAMP,
    reviewed_by VARCHAR(50),
    status VARCHAR(20) DEFAULT 'active', -- 'active', 'draft', 'archived'
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Incident reports
CREATE TABLE IF NOT EXISTS incident_reports (
    incident_id VARCHAR(50) PRIMARY KEY,
    incident_number VARCHAR(100) UNIQUE NOT NULL,
    incident_type VARCHAR(50) NOT NULL, -- 'fire', 'medical', 'security', 'weather', 'structural', 'chemical', 'biological', 'radiation', 'other'
    severity_level VARCHAR(20) DEFAULT 'medium', -- 'low', 'medium', 'high', 'critical'
    location VARCHAR(255),
    location_coordinates JSONB, -- latitude, longitude
    description TEXT,
    reported_by VARCHAR(50),
    reported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    acknowledged_by VARCHAR(50),
    acknowledged_at TIMESTAMP,
    response_started_at TIMESTAMP,
    resolved_at TIMESTAMP,
    incident_status VARCHAR(30) DEFAULT 'reported', -- 'reported', 'acknowledged', 'responding', 'contained', 'resolved', 'closed'
    affected_areas JSONB DEFAULT '[]',
    affected_persons INTEGER DEFAULT 0,
    injuries INTEGER DEFAULT 0,
    fatalities INTEGER DEFAULT 0,
    property_damage DECIMAL(12,2),
    evacuation_required BOOLEAN DEFAULT FALSE,
    evacuation_count INTEGER DEFAULT 0,
    protocol_used VARCHAR(50) REFERENCES emergency_protocols(protocol_id),
    response_team JSONB DEFAULT '[]',
    external_agencies JSONB DEFAULT '[]',
    weather_conditions JSONB DEFAULT '{}',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Emergency response teams
CREATE TABLE IF NOT EXISTS emergency_response_teams (
    team_id VARCHAR(50) PRIMARY KEY,
    team_name VARCHAR(255) NOT NULL,
    team_type VARCHAR(50) NOT NULL, -- 'fire', 'medical', 'security', 'technical', 'communication', 'coordination'
    specialization VARCHAR(100),
    contact_info JSONB,
    team_leader VARCHAR(50),
    team_members JSONB DEFAULT '[]',
    equipment_inventory JSONB DEFAULT '{}',
    training_records JSONB DEFAULT '[]',
    availability_schedule JSONB DEFAULT '{}',
    response_time_minutes INTEGER,
    jurisdiction VARCHAR(100), -- 'terminal_a', 'terminal_b', 'cargo_area', 'runways', 'entire_airport'
    status VARCHAR(20) DEFAULT 'available', -- 'available', 'responding', 'training', 'maintenance', 'unavailable'
    last_drill_date TIMESTAMP,
    next_drill_date TIMESTAMP,
    performance_rating DECIMAL(3,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Emergency equipment
CREATE TABLE IF NOT EXISTS emergency_equipment (
    equipment_id VARCHAR(50) PRIMARY KEY,
    equipment_name VARCHAR(255) NOT NULL,
    equipment_type VARCHAR(50) NOT NULL, -- 'fire_extinguisher', 'defibrillator', 'first_aid', 'communication', 'rescue', 'detection', 'protection'
    location VARCHAR(255),
    location_coordinates JSONB,
    manufacturer VARCHAR(100),
    model VARCHAR(100),
    serial_number VARCHAR(100),
    installation_date DATE,
    last_inspection TIMESTAMP,
    next_inspection TIMESTAMP,
    inspection_interval_months INTEGER DEFAULT 12,
    maintenance_schedule JSONB DEFAULT '{}',
    operational_status VARCHAR(20) DEFAULT 'operational', -- 'operational', 'maintenance', 'out_of_service', 'needs_replacement'
    battery_level DECIMAL(5,2),
    last_used TIMESTAMP,
    usage_count INTEGER DEFAULT 0,
    assigned_team VARCHAR(50) REFERENCES emergency_response_teams(team_id),
    emergency_type_coverage JSONB DEFAULT '[]', -- types of emergencies this equipment can handle
    training_required BOOLEAN DEFAULT FALSE,
    special_requirements TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Emergency communication logs
CREATE TABLE IF NOT EXISTS emergency_communications (
    communication_id SERIAL PRIMARY KEY,
    incident_id VARCHAR(50) REFERENCES incident_reports(incident_id),
    communication_type VARCHAR(30) NOT NULL, -- 'internal', 'external', 'public', 'media', 'regulatory'
    sender VARCHAR(50),
    recipient VARCHAR(255),
    message_type VARCHAR(30) DEFAULT 'text', -- 'text', 'voice', 'video', 'alert'
    message_content TEXT,
    priority_level VARCHAR(20) DEFAULT 'normal', -- 'low', 'normal', 'high', 'urgent'
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    delivered_at TIMESTAMP,
    acknowledged_at TIMESTAMP,
    response_required BOOLEAN DEFAULT FALSE,
    response_received BOOLEAN DEFAULT FALSE,
    response_content TEXT,
    communication_channel VARCHAR(50), -- 'radio', 'phone', 'email', 'sms', 'app', 'pa_system'
    attachments JSONB DEFAULT '[]',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Emergency evacuation records
CREATE TABLE IF NOT EXISTS emergency_evacuations (
    evacuation_id SERIAL PRIMARY KEY,
    incident_id VARCHAR(50) REFERENCES incident_reports(incident_id),
    evacuation_type VARCHAR(30) NOT NULL, -- 'partial', 'full', 'zone_specific', 'building_specific'
    evacuation_area VARCHAR(255),
    evacuation_route VARCHAR(100),
    assembly_point VARCHAR(100),
    initiated_by VARCHAR(50),
    initiated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP,
    evacuation_time_minutes INTEGER,
    total_evacuated INTEGER DEFAULT 0,
    special_assistance_provided INTEGER DEFAULT 0,
    issues_encountered JSONB DEFAULT '[]',
    effectiveness_rating DECIMAL(3,2),
    lessons_learned TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Emergency drills and training
CREATE TABLE IF NOT EXISTS emergency_drills (
    drill_id VARCHAR(50) PRIMARY KEY,
    drill_name VARCHAR(255) NOT NULL,
    drill_type VARCHAR(50) NOT NULL, -- 'fire', 'medical', 'security', 'evacuation', 'communication', 'equipment'
    scenario_description TEXT,
    planned_date TIMESTAMP,
    actual_date TIMESTAMP,
    duration_minutes INTEGER,
    participants JSONB DEFAULT '[]',
    teams_involved JSONB DEFAULT '[]',
    equipment_used JSONB DEFAULT '[]',
    drill_status VARCHAR(20) DEFAULT 'planned', -- 'planned', 'in_progress', 'completed', 'cancelled'
    drill_objectives JSONB DEFAULT '[]',
    success_criteria JSONB DEFAULT '[]',
    drill_results JSONB DEFAULT '{}',
    performance_rating DECIMAL(3,2),
    issues_identified JSONB DEFAULT '[]',
    recommendations JSONB DEFAULT '[]',
    follow_up_actions JSONB DEFAULT '[]',
    conducted_by VARCHAR(50),
    reviewed_by VARCHAR(50),
    review_date TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Emergency resource allocation
CREATE TABLE IF NOT EXISTS emergency_resources (
    resource_id SERIAL PRIMARY KEY,
    incident_id VARCHAR(50) REFERENCES incident_reports(incident_id),
    resource_type VARCHAR(50) NOT NULL, -- 'personnel', 'equipment', 'vehicle', 'facility', 'supply'
    resource_name VARCHAR(255) NOT NULL,
    quantity_allocated INTEGER DEFAULT 1,
    quantity_available INTEGER,
    allocated_by VARCHAR(50),
    allocated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    released_at TIMESTAMP,
    allocation_status VARCHAR(20) DEFAULT 'allocated', -- 'allocated', 'in_use', 'released', 'consumed'
    usage_notes TEXT,
    cost_incurred DECIMAL(8,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Emergency alerts and notifications
CREATE TABLE IF NOT EXISTS emergency_alerts (
    alert_id SERIAL PRIMARY KEY,
    incident_id VARCHAR(50) REFERENCES incident_reports(incident_id),
    alert_type VARCHAR(30) NOT NULL, -- 'internal', 'passenger', 'staff', 'external', 'regulatory'
    alert_level VARCHAR(20) DEFAULT 'information', -- 'information', 'warning', 'emergency', 'evacuation'
    alert_title VARCHAR(255) NOT NULL,
    alert_message TEXT,
    target_audience JSONB DEFAULT '[]',
    delivery_channels JSONB DEFAULT '[]', -- 'pa_system', 'mobile_app', 'email', 'sms', 'digital_signage'
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    acknowledged_count INTEGER DEFAULT 0,
    total_recipients INTEGER DEFAULT 0,
    effectiveness_rating DECIMAL(3,2),
    feedback_received JSONB DEFAULT '[]',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Emergency medical records
CREATE TABLE IF NOT EXISTS emergency_medical_records (
    medical_id SERIAL PRIMARY KEY,
    incident_id VARCHAR(50) REFERENCES incident_reports(incident_id),
    patient_id VARCHAR(50), -- could reference passenger or staff ID
    patient_name VARCHAR(255),
    patient_age INTEGER,
    patient_gender VARCHAR(10),
    medical_condition TEXT,
    treatment_provided TEXT,
    medications_administered JSONB DEFAULT '[]',
    vital_signs JSONB DEFAULT '{}',
    transport_destination VARCHAR(255),
    transport_time TIMESTAMP,
    outcome VARCHAR(30), -- 'treated_on_site', 'transported', 'admitted', 'deceased'
    treating_personnel VARCHAR(50),
    follow_up_required BOOLEAN DEFAULT FALSE,
    follow_up_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Emergency weather monitoring
CREATE TABLE IF NOT EXISTS emergency_weather_monitoring (
    monitoring_id SERIAL PRIMARY KEY,
    incident_id VARCHAR(50) REFERENCES incident_reports(incident_id),
    weather_condition VARCHAR(50) NOT NULL, -- 'storm', 'flooding', 'high_winds', 'lightning', 'tornado', 'hurricane'
    severity_level VARCHAR(20) DEFAULT 'moderate', -- 'minor', 'moderate', 'severe', 'extreme'
    affected_areas JSONB DEFAULT '[]',
    wind_speed_kmh DECIMAL(6,2),
    precipitation_mm DECIMAL(6,2),
    temperature_celsius DECIMAL(5,2),
    visibility_meters INTEGER,
    forecast_data JSONB DEFAULT '{}',
    monitoring_started TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    monitoring_ended TIMESTAMP,
    impact_assessment TEXT,
    mitigation_actions JSONB DEFAULT '[]',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Emergency power systems
CREATE TABLE IF NOT EXISTS emergency_power_systems (
    system_id VARCHAR(50) PRIMARY KEY,
    system_name VARCHAR(255) NOT NULL,
    system_type VARCHAR(30) NOT NULL, -- 'generator', 'ups', 'solar_backup', 'fuel_cell'
    location VARCHAR(255),
    capacity_kw DECIMAL(8,2),
    fuel_type VARCHAR(30), -- 'diesel', 'gasoline', 'natural_gas', 'battery', 'solar'
    fuel_capacity_liters DECIMAL(8,2),
    current_fuel_level DECIMAL(5,2),
    operational_status VARCHAR(20) DEFAULT 'standby', -- 'operational', 'standby', 'maintenance', 'failed'
    last_test TIMESTAMP,
    next_test TIMESTAMP,
    test_interval_days INTEGER DEFAULT 30,
    maintenance_schedule JSONB DEFAULT '{}',
    connected_systems JSONB DEFAULT '[]', -- what systems this powers
    switchover_time_seconds INTEGER,
    runtime_hours_remaining DECIMAL(6,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Emergency contact lists
CREATE TABLE IF NOT EXISTS emergency_contacts (
    contact_id SERIAL PRIMARY KEY,
    contact_name VARCHAR(255) NOT NULL,
    contact_type VARCHAR(30) NOT NULL, -- 'internal', 'external_agency', 'medical', 'security', 'utility', 'supplier'
    organization VARCHAR(255),
    position VARCHAR(100),
    primary_phone VARCHAR(20),
    secondary_phone VARCHAR(20),
    email VARCHAR(255),
    emergency_role VARCHAR(100),
    availability_hours JSONB DEFAULT '{}',
    special_skills JSONB DEFAULT '[]',
    equipment_access JSONB DEFAULT '[]',
    last_contacted TIMESTAMP,
    response_time_minutes INTEGER,
    reliability_rating DECIMAL(3,2),
    notes TEXT,
    status VARCHAR(20) DEFAULT 'active', -- 'active', 'inactive', 'retired'
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Emergency performance metrics
CREATE TABLE IF NOT EXISTS emergency_performance_metrics (
    metric_id SERIAL PRIMARY KEY,
    date DATE,
    metric_type VARCHAR(50) NOT NULL, -- 'response_time', 'evacuation_time', 'equipment_uptime', 'training_completion'
    metric_name VARCHAR(100) NOT NULL,
    metric_value DECIMAL(10,4),
    target_value DECIMAL(10,4),
    variance_percentage DECIMAL(5,2),
    incident_count INTEGER DEFAULT 0,
    drill_count INTEGER DEFAULT 0,
    improvement_actions JSONB DEFAULT '[]',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Emergency audit logs
CREATE TABLE IF NOT EXISTS emergency_audit_logs (
    audit_id SERIAL PRIMARY KEY,
    incident_id VARCHAR(50) REFERENCES incident_reports(incident_id),
    action_type VARCHAR(50) NOT NULL, -- 'protocol_activated', 'resource_allocated', 'communication_sent', 'status_changed'
    action_description TEXT,
    performed_by VARCHAR(50),
    performed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    affected_entities JSONB DEFAULT '{}',
    old_values JSONB DEFAULT '{}',
    new_values JSONB DEFAULT '{}',
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Indexes for performance
CREATE INDEX IF NOT EXISTS idx_emergency_protocols_type ON emergency_protocols(protocol_type);
CREATE INDEX IF NOT EXISTS idx_emergency_protocols_status ON emergency_protocols(status);
CREATE INDEX IF NOT EXISTS idx_incident_reports_type ON incident_reports(incident_type);
CREATE INDEX IF NOT EXISTS idx_incident_reports_status ON incident_reports(incident_status);
CREATE INDEX IF NOT EXISTS idx_incident_reports_location ON incident_reports(location);
CREATE INDEX IF NOT EXISTS idx_incident_reports_date ON incident_reports(reported_at);
CREATE INDEX IF NOT EXISTS idx_response_teams_type ON emergency_response_teams(team_type);
CREATE INDEX IF NOT EXISTS idx_response_teams_status ON emergency_response_teams(status);
CREATE INDEX IF NOT EXISTS idx_emergency_equipment_type ON emergency_equipment(equipment_type);
CREATE INDEX IF NOT EXISTS idx_emergency_equipment_status ON emergency_equipment(operational_status);
CREATE INDEX IF NOT EXISTS idx_emergency_equipment_location ON emergency_equipment(location);
CREATE INDEX IF NOT EXISTS idx_communications_incident ON emergency_communications(incident_id);
CREATE INDEX IF NOT EXISTS idx_communications_type ON emergency_communications(communication_type);
CREATE INDEX IF NOT EXISTS idx_evacuations_incident ON emergency_evacuations(incident_id);
CREATE INDEX IF NOT EXISTS idx_drills_type ON emergency_drills(drill_type);
CREATE INDEX IF NOT EXISTS idx_drills_date ON emergency_drills(planned_date);
CREATE INDEX IF NOT EXISTS idx_resources_incident ON emergency_resources(incident_id);
CREATE INDEX IF NOT EXISTS idx_alerts_incident ON emergency_alerts(incident_id);
CREATE INDEX IF NOT EXISTS idx_medical_incident ON emergency_medical_records(incident_id);
CREATE INDEX IF NOT EXISTS idx_weather_incident ON emergency_weather_monitoring(incident_id);
CREATE INDEX IF NOT EXISTS idx_power_systems_status ON emergency_power_systems(operational_status);
CREATE INDEX IF NOT EXISTS idx_contacts_type ON emergency_contacts(contact_type);
CREATE INDEX IF NOT EXISTS idx_contacts_status ON emergency_contacts(status);
CREATE INDEX IF NOT EXISTS idx_performance_date ON emergency_performance_metrics(date);
CREATE INDEX IF NOT EXISTS idx_performance_type ON emergency_performance_metrics(metric_type);
CREATE INDEX IF NOT EXISTS idx_audit_incident ON emergency_audit_logs(incident_id);
CREATE INDEX IF NOT EXISTS idx_audit_action ON emergency_audit_logs(action_type);

-- Insert sample emergency protocols
INSERT INTO emergency_protocols (protocol_id, protocol_name, protocol_type, severity_level, description, response_procedures) VALUES
('PROTOCOL-FIRE-001', 'Terminal Fire Emergency', 'fire', 'high', 'Protocol for handling fire emergencies in passenger terminals', '{"immediate_actions": ["Activate fire alarm", "Initiate evacuation", "Contact fire department"], "coordination": ["Airport fire team", "Terminal management", "Airlines"], "communication": ["PA system announcements", "Staff alerts", "Emergency notifications"]}'),
('PROTOCOL-MEDICAL-001', 'Medical Emergency Response', 'medical', 'medium', 'Protocol for medical emergencies requiring immediate attention', '{"assessment": ["Initial patient assessment", "Vital signs monitoring"], "response": ["Medical team activation", "Equipment deployment", "Transport coordination"], "documentation": ["Medical records", "Incident reporting"]}'),
('PROTOCOL-SECURITY-001', 'Security Threat Response', 'security', 'critical', 'Protocol for handling security threats and breaches', '{"containment": ["Isolate affected area", "Secure perimeter"], "assessment": ["Threat evaluation", "Resource deployment"], "communication": ["Silent alarms", "Law enforcement coordination"]}'),
('PROTOCOL-WEATHER-001', 'Severe Weather Emergency', 'weather', 'high', 'Protocol for severe weather conditions affecting operations', '{"monitoring": ["Weather tracking", "Impact assessment"], "response": ["Flight diversions", "Ground stop procedures"], "communication": ["Passenger notifications", "Staff briefings"]}');

-- Insert sample emergency response teams
INSERT INTO emergency_response_teams (team_id, team_name, team_type, team_leader, jurisdiction, response_time_minutes) VALUES
('ERT-FIRE-001', 'Airport Fire Response Team', 'fire', 'Captain Johnson', 'entire_airport', 3),
('ERT-MEDICAL-001', 'Emergency Medical Team', 'medical', 'Dr. Smith', 'entire_airport', 5),
('ERT-SECURITY-001', 'Airport Security Response', 'security', 'Lt. Williams', 'entire_airport', 2),
('ERT-TECHNICAL-001', 'Technical Emergency Team', 'technical', 'Engineer Davis', 'terminal_a', 10);

-- Insert sample emergency equipment
INSERT INTO emergency_equipment (equipment_id, equipment_name, equipment_type, location, operational_status) VALUES
('EQUIP-FIRE-001', 'Terminal A Fire Extinguisher Station', 'fire_extinguisher', 'Terminal A - Baggage Claim', 'operational'),
('EQUIP-MEDICAL-001', 'Automated External Defibrillator', 'defibrillator', 'Terminal B - Gate 12', 'operational'),
('EQUIP-COMMS-001', 'Emergency Communication Radio', 'communication', 'Control Tower', 'operational'),
('EQUIP-RESCUE-001', 'Heavy Rescue Vehicle', 'rescue', 'Fire Station 1', 'operational');

-- Insert sample emergency contacts
INSERT INTO emergency_contacts (contact_name, contact_type, organization, position, primary_phone, emergency_role) VALUES
('John Fire Chief', 'external_agency', 'City Fire Department', 'Fire Chief', '+1-555-0101', 'Fire Response Coordination'),
('Dr. Emergency MD', 'medical', 'Regional Hospital', 'ER Director', '+1-555-0102', 'Medical Emergency Support'),
('Captain Police', 'external_agency', 'Airport Police', 'Captain', '+1-555-0103', 'Security Coordination'),
('Utility Manager', 'utility', 'Power Company', 'Emergency Manager', '+1-555-0104', 'Power Restoration');

-- Function to activate emergency protocol
CREATE OR REPLACE FUNCTION activate_emergency_protocol(
    p_incident_id VARCHAR,
    p_protocol_id VARCHAR,
    p_activated_by VARCHAR
) RETURNS JSON AS $$
DECLARE
    v_protocol RECORD;
    v_result JSON;
BEGIN
    -- Get protocol details
    SELECT * INTO v_protocol FROM emergency_protocols WHERE protocol_id = p_protocol_id;

    IF NOT FOUND THEN
        RAISE EXCEPTION 'Emergency protocol not found: %', p_protocol_id;
    END IF;

    -- Log protocol activation
    INSERT INTO emergency_audit_logs (
        incident_id, action_type, action_description, performed_by,
        affected_entities, new_values
    ) VALUES (
        p_incident_id, 'protocol_activated',
        'Emergency protocol ' || v_protocol.protocol_name || ' activated',
        p_activated_by,
        json_build_object('protocol_id', p_protocol_id),
        json_build_object('status', 'activated', 'activated_at', CURRENT_TIMESTAMP)
    );

    -- Update incident with protocol
    UPDATE incident_reports
    SET protocol_used = p_protocol_id,
        updated_at = CURRENT_TIMESTAMP
    WHERE incident_id = p_incident_id;

    -- Return activation details
    SELECT json_build_object(
        'protocol_id', v_protocol.protocol_id,
        'protocol_name', v_protocol.protocol_name,
        'severity_level', v_protocol.severity_level,
        'response_procedures', v_protocol.response_procedures,
        'estimated_response_time', v_protocol.estimated_response_time,
        'activation_time', CURRENT_TIMESTAMP
    ) INTO v_result;

    RETURN v_result;
END;
$$ LANGUAGE plpgsql;

-- Function to report emergency incident
CREATE OR REPLACE FUNCTION report_emergency_incident(
    p_incident_data JSONB,
    p_reported_by VARCHAR
) RETURNS VARCHAR AS $$
DECLARE
    v_incident_id VARCHAR(50);
    v_incident_number VARCHAR(100);
BEGIN
    -- Generate incident ID and number
    v_incident_id := 'INC-' || TO_CHAR(CURRENT_TIMESTAMP, 'YYYYMMDD') || '-' || LPAD(NEXTVAL('incident_seq')::TEXT, 6, '0');
    v_incident_number := 'EMERGENCY-' || TO_CHAR(CURRENT_TIMESTAMP, 'YYYYMMDDHH24MISS');

    -- Insert incident
    INSERT INTO incident_reports (
        incident_id, incident_number, incident_type, severity_level,
        location, location_coordinates, description, reported_by,
        affected_areas, weather_conditions
    ) VALUES (
        v_incident_id,
        v_incident_number,
        COALESCE(p_incident_data->>'incident_type', 'other'),
        COALESCE(p_incident_data->>'severity_level', 'medium'),
        p_incident_data->>'location',
        p_incident_data->'location_coordinates',
        p_incident_data->>'description',
        p_reported_by,
        COALESCE(p_incident_data->'affected_areas', '[]'::jsonb),
        COALESCE(p_incident_data->'weather_conditions', '{}'::jsonb)
    );

    -- Log incident creation
    INSERT INTO emergency_audit_logs (
        incident_id, action_type, action_description, performed_by,
        new_values
    ) VALUES (
        v_incident_id, 'incident_created',
        'Emergency incident reported',
        p_reported_by,
        json_build_object('incident_number', v_incident_number, 'status', 'reported')
    );

    RETURN v_incident_id;
END;
$$ LANGUAGE plpgsql;

-- Function to allocate emergency resources
CREATE OR REPLACE FUNCTION allocate_emergency_resource(
    p_incident_id VARCHAR,
    p_resource_type VARCHAR,
    p_resource_name VARCHAR,
    p_quantity INTEGER,
    p_allocated_by VARCHAR
) RETURNS INTEGER AS $$
DECLARE
    v_resource_id INTEGER;
BEGIN
    INSERT INTO emergency_resources (
        incident_id, resource_type, resource_name, quantity_allocated, allocated_by
    ) VALUES (
        p_incident_id, p_resource_type, p_resource_name, p_quantity, p_allocated_by
    ) RETURNING resource_id INTO v_resource_id;

    -- Log resource allocation
    INSERT INTO emergency_audit_logs (
        incident_id, action_type, action_description, performed_by,
        affected_entities, new_values
    ) VALUES (
        p_incident_id, 'resource_allocated',
        'Emergency resource allocated: ' || p_resource_name,
        p_allocated_by,
        json_build_object('resource_type', p_resource_type, 'resource_name', p_resource_name),
        json_build_object('quantity', p_quantity, 'allocated_at', CURRENT_TIMESTAMP)
    );

    RETURN v_resource_id;
END;
$$ LANGUAGE plpgsql;

-- Function to send emergency alert
CREATE OR REPLACE FUNCTION send_emergency_alert(
    p_incident_id VARCHAR,
    p_alert_data JSONB,
    p_sent_by VARCHAR
) RETURNS INTEGER AS $$
DECLARE
    v_alert_id INTEGER;
BEGIN
    INSERT INTO emergency_alerts (
        incident_id, alert_type, alert_level, alert_title, alert_message,
        target_audience, delivery_channels
    ) VALUES (
        p_incident_id,
        COALESCE(p_alert_data->>'alert_type', 'internal'),
        COALESCE(p_alert_data->>'alert_level', 'warning'),
        p_alert_data->>'alert_title',
        p_alert_data->>'alert_message',
        COALESCE(p_alert_data->'target_audience', '[]'::jsonb),
        COALESCE(p_alert_data->'delivery_channels', '["pa_system"]'::jsonb)
    ) RETURNING alert_id INTO v_alert_id;

    -- Log alert sending
    INSERT INTO emergency_audit_logs (
        incident_id, action_type, action_description, performed_by,
        affected_entities, new_values
    ) VALUES (
        p_incident_id, 'alert_sent',
        'Emergency alert sent: ' || (p_alert_data->>'alert_title'),
        p_sent_by,
        json_build_object('alert_type', p_alert_data->>'alert_type'),
        json_build_object('channels', p_alert_data->'delivery_channels', 'sent_at', CURRENT_TIMESTAMP)
    );

    RETURN v_alert_id;
END;
$$ LANGUAGE plpgsql;

-- Function to calculate emergency response time
CREATE OR REPLACE FUNCTION calculate_response_time(p_incident_id VARCHAR)
RETURNS INTERVAL AS $$
DECLARE
    v_response_time INTERVAL;
BEGIN
    SELECT
        CASE
            WHEN response_started_at IS NOT NULL THEN response_started_at - reported_at
            ELSE NULL
        END
    INTO v_response_time
    FROM incident_reports
    WHERE incident_id = p_incident_id;

    RETURN v_response_time;
END;
$$ LANGUAGE plpgsql;

-- Function to get emergency dashboard data
CREATE OR REPLACE FUNCTION get_emergency_dashboard()
RETURNS JSON AS $$
DECLARE
    result JSON;
BEGIN
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
    ) INTO result;

    RETURN result;
END;
$$ LANGUAGE plpgsql;

-- Function to generate emergency incident report
CREATE OR REPLACE FUNCTION generate_emergency_report(p_start_date DATE, p_end_date DATE)
RETURNS JSON AS $$
DECLARE
    result JSON;
BEGIN
    SELECT json_build_object(
        'period', json_build_object('start_date', p_start_date, 'end_date', p_end_date),
        'incident_summary', json_build_object(
            'total_incidents', (
                SELECT COUNT(*) FROM incident_reports
                WHERE DATE(reported_at) BETWEEN p_start_date AND p_end_date
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
                WHERE DATE(reported_at) BETWEEN p_start_date AND p_end_date
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
                WHERE DATE(reported_at) BETWEEN p_start_date AND p_end_date
                GROUP BY severity_level
            )
        ),
        'response_metrics', json_build_object(
            'average_response_time', (
                SELECT ROUND(AVG(EXTRACT(EPOCH FROM calculate_response_time(incident_id))/60), 2)
                FROM incident_reports
                WHERE DATE(reported_at) BETWEEN p_start_date AND p_end_date
                AND response_started_at IS NOT NULL
            ),
            'response_time_distribution', (
                SELECT json_agg(
                    json_build_object(
                        'time_range', time_range,
                        'count', COUNT(*)
                    )
                )
                FROM (
                    SELECT
                        CASE
                            WHEN EXTRACT(EPOCH FROM calculate_response_time(incident_id))/60 <= 5 THEN '0-5 min'
                            WHEN EXTRACT(EPOCH FROM calculate_response_time(incident_id))/60 <= 15 THEN '5-15 min'
                            WHEN EXTRACT(EPOCH FROM calculate_response_time(incident_id))/60 <= 30 THEN '15-30 min'
                            ELSE '30+ min'
                        END as time_range
                    FROM incident_reports
                    WHERE DATE(reported_at) BETWEEN p_start_date AND p_end_date
                    AND response_started_at IS NOT NULL
                ) response_times
                GROUP BY time_range
            )
        ),
        'resource_utilization', json_build_object(
            'teams_deployed', (
                SELECT COUNT(DISTINCT team_id)
                FROM emergency_resources er
                JOIN incident_reports ir ON er.incident_id = ir.incident_id
                WHERE DATE(ir.reported_at) BETWEEN p_start_date AND p_end_date
                AND er.resource_type = 'personnel'
            ),
            'equipment_used', (
                SELECT COUNT(*)
                FROM emergency_resources er
                JOIN incident_reports ir ON er.incident_id = ir.incident_id
                WHERE DATE(ir.reported_at) BETWEEN p_start_date AND p_end_date
                AND er.resource_type = 'equipment'
            )
        ),
        'training_drills', json_build_object(
            'drills_conducted', (
                SELECT COUNT(*) FROM emergency_drills
                WHERE DATE(actual_date) BETWEEN p_start_date AND p_end_date
                AND drill_status = 'completed'
            ),
            'average_performance', (
                SELECT ROUND(AVG(performance_rating), 2)
                FROM emergency_drills
                WHERE DATE(actual_date) BETWEEN p_start_date AND p_end_date
                AND drill_status = 'completed'
            )
        )
    ) INTO result;

    RETURN result;
END;
$$ LANGUAGE plpgsql;
