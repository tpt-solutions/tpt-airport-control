-- Special Services Module Schema
-- Manages accessibility services, special assistance, and passenger support

-- Special assistance requests
CREATE TABLE IF NOT EXISTS special_assistance_requests (
    request_id SERIAL PRIMARY KEY,
    passenger_id INTEGER REFERENCES passengers(passenger_id),
    booking_id INTEGER REFERENCES bookings(booking_id),
    flight_id INTEGER REFERENCES flights(flight_id),
    assistance_type VARCHAR(50) NOT NULL, -- 'wheelchair', 'visual', 'hearing', 'cognitive', 'medical', 'unaccompanied_minor'
    assistance_level VARCHAR(20) DEFAULT 'standard', -- 'standard', 'enhanced', 'complex'
    request_status VARCHAR(20) DEFAULT 'pending', -- 'pending', 'confirmed', 'in_progress', 'completed', 'cancelled'
    requested_by VARCHAR(50),
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    confirmed_by VARCHAR(50),
    confirmed_at TIMESTAMP,
    special_requirements TEXT,
    medical_conditions TEXT,
    mobility_aids JSONB DEFAULT '[]', -- wheelchair, walker, cane, etc.
    communication_needs JSONB DEFAULT '{}', -- languages, communication methods
    companion_details JSONB DEFAULT '{}',
    estimated_duration_minutes INTEGER,
    assigned_staff JSONB DEFAULT '[]',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Accessibility services
CREATE TABLE IF NOT EXISTS accessibility_services (
    service_id SERIAL PRIMARY KEY,
    service_name VARCHAR(100) NOT NULL,
    service_type VARCHAR(50) NOT NULL, -- 'mobility', 'visual', 'hearing', 'cognitive', 'medical'
    description TEXT,
    location VARCHAR(255),
    terminal VARCHAR(10),
    zone VARCHAR(50), -- 'check_in', 'security', 'gate', 'boarding', 'baggage_claim'
    availability_schedule JSONB, -- operating hours by day
    capacity INTEGER,
    current_utilization INTEGER DEFAULT 0,
    equipment_required JSONB DEFAULT '[]',
    staff_required JSONB DEFAULT '[]',
    estimated_service_time INTEGER, -- in minutes
    is_active BOOLEAN DEFAULT TRUE,
    contact_info JSONB,
    emergency_contact JSONB,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Service assignments
CREATE TABLE IF NOT EXISTS service_assignments (
    assignment_id SERIAL PRIMARY KEY,
    request_id INTEGER REFERENCES special_assistance_requests(request_id),
    service_id INTEGER REFERENCES accessibility_services(service_id),
    staff_member_id VARCHAR(50),
    staff_member_name VARCHAR(100),
    assignment_status VARCHAR(20) DEFAULT 'assigned', -- 'assigned', 'in_progress', 'completed', 'cancelled'
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    started_at TIMESTAMP,
    completed_at TIMESTAMP,
    service_duration_minutes INTEGER,
    service_location VARCHAR(255),
    equipment_used JSONB DEFAULT '[]',
    notes TEXT,
    passenger_feedback TEXT,
    staff_feedback TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Medical assistance records
CREATE TABLE IF NOT EXISTS medical_assistance (
    assistance_id SERIAL PRIMARY KEY,
    passenger_id INTEGER REFERENCES passengers(passenger_id),
    request_id INTEGER REFERENCES special_assistance_requests(request_id),
    medical_condition VARCHAR(100),
    severity_level VARCHAR(20), -- 'minor', 'moderate', 'serious', 'critical'
    symptoms TEXT,
    medications JSONB DEFAULT '[]',
    allergies JSONB DEFAULT '[]',
    medical_history TEXT,
    physician_contact JSONB DEFAULT '{}',
    emergency_contact JSONB DEFAULT '{}',
    assistance_required TEXT,
    equipment_needed JSONB DEFAULT '[]',
    special_handling_instructions TEXT,
    incident_report TEXT,
    outcome VARCHAR(50),
    follow_up_required BOOLEAN DEFAULT FALSE,
    follow_up_date TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Unaccompanied minors
CREATE TABLE IF NOT EXISTS unaccompanied_minors (
    minor_id SERIAL PRIMARY KEY,
    passenger_id INTEGER REFERENCES passengers(passenger_id),
    booking_id INTEGER REFERENCES bookings(booking_id),
    flight_id INTEGER REFERENCES flights(flight_id),
    guardian_name VARCHAR(100) NOT NULL,
    guardian_relationship VARCHAR(50),
    guardian_contact JSONB NOT NULL, -- phone, email, address
    emergency_contact JSONB NOT NULL,
    minor_age INTEGER NOT NULL,
    special_instructions TEXT,
    identification_documents JSONB DEFAULT '[]',
    status VARCHAR(20) DEFAULT 'checked_in', -- 'checked_in', 'boarding', 'in_flight', 'arrived', 'delivered'
    check_in_time TIMESTAMP,
    boarding_time TIMESTAMP,
    arrival_time TIMESTAMP,
    delivery_time TIMESTAMP,
    assigned_staff JSONB DEFAULT '[]',
    location_tracking JSONB DEFAULT '[]',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Language assistance services
CREATE TABLE IF NOT EXISTS language_services (
    service_id SERIAL PRIMARY KEY,
    passenger_id INTEGER REFERENCES passengers(passenger_id),
    request_id INTEGER REFERENCES special_assistance_requests(request_id),
    primary_language VARCHAR(10) NOT NULL,
    secondary_languages JSONB DEFAULT '[]',
    required_services JSONB DEFAULT '[]', -- 'translation', 'interpretation', 'documentation'
    proficiency_level VARCHAR(20), -- 'basic', 'intermediate', 'advanced', 'native'
    urgency_level VARCHAR(20) DEFAULT 'standard', -- 'standard', 'urgent', 'emergency'
    service_status VARCHAR(20) DEFAULT 'requested', -- 'requested', 'assigned', 'in_progress', 'completed'
    assigned_interpreter VARCHAR(100),
    interpreter_contact JSONB DEFAULT '{}',
    service_duration_minutes INTEGER,
    service_location VARCHAR(255),
    documents_translated JSONB DEFAULT '[]',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Equipment tracking
CREATE TABLE IF NOT EXISTS mobility_equipment (
    equipment_id VARCHAR(50) PRIMARY KEY,
    equipment_type VARCHAR(50) NOT NULL, -- 'wheelchair', 'scooter', 'walker', 'cane', 'stretcher'
    equipment_model VARCHAR(100),
    equipment_size VARCHAR(20), -- 'standard', 'large', 'bariatric'
    location VARCHAR(255),
    terminal VARCHAR(10),
    zone VARCHAR(50),
    status VARCHAR(20) DEFAULT 'available', -- 'available', 'in_use', 'maintenance', 'out_of_service'
    condition_rating INTEGER CHECK (condition_rating BETWEEN 1 AND 5),
    last_maintenance TIMESTAMP,
    next_maintenance TIMESTAMP,
    battery_level DECIMAL(5,2),
    assigned_to VARCHAR(50), -- passenger or staff ID
    assigned_at TIMESTAMP,
    returned_at TIMESTAMP,
    usage_log JSONB DEFAULT '[]',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Equipment maintenance
CREATE TABLE IF NOT EXISTS equipment_maintenance (
    maintenance_id SERIAL PRIMARY KEY,
    equipment_id VARCHAR(50) REFERENCES mobility_equipment(equipment_id),
    maintenance_type VARCHAR(50) NOT NULL, -- 'routine', 'repair', 'replacement', 'inspection'
    maintenance_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    performed_by VARCHAR(50),
    description TEXT,
    parts_used JSONB DEFAULT '[]',
    cost DECIMAL(8,2),
    next_maintenance_date TIMESTAMP,
    maintenance_status VARCHAR(20) DEFAULT 'completed', -- 'scheduled', 'in_progress', 'completed', 'cancelled'
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Service performance metrics
CREATE TABLE IF NOT EXISTS service_performance_metrics (
    metric_id SERIAL PRIMARY KEY,
    date DATE,
    service_type VARCHAR(50),
    terminal VARCHAR(10),
    total_requests INTEGER DEFAULT 0,
    completed_requests INTEGER DEFAULT 0,
    average_response_time_minutes DECIMAL(6,2),
    average_service_time_minutes DECIMAL(6,2),
    customer_satisfaction_rating DECIMAL(3,2),
    equipment_utilization_rate DECIMAL(5,2),
    staff_utilization_rate DECIMAL(5,2),
    incidents_reported INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Staff scheduling for special services
CREATE TABLE IF NOT EXISTS special_services_staff (
    staff_id VARCHAR(50) PRIMARY KEY,
    staff_name VARCHAR(100) NOT NULL,
    staff_role VARCHAR(50) NOT NULL, -- 'accessibility_assistant', 'medical_assistant', 'interpreter', 'supervisor'
    certifications JSONB DEFAULT '[]',
    languages_spoken JSONB DEFAULT '[]',
    special_skills JSONB DEFAULT '[]',
    availability_schedule JSONB,
    contact_info JSONB,
    status VARCHAR(20) DEFAULT 'active', -- 'active', 'on_leave', 'training', 'inactive'
    hire_date DATE,
    performance_rating DECIMAL(3,2),
    assigned_requests INTEGER DEFAULT 0,
    completed_requests INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Staff assignments and scheduling
CREATE TABLE IF NOT EXISTS staff_assignments (
    assignment_id SERIAL PRIMARY KEY,
    staff_id VARCHAR(50) REFERENCES special_services_staff(staff_id),
    request_id INTEGER REFERENCES special_assistance_requests(request_id),
    assignment_date DATE,
    start_time TIME,
    end_time TIME,
    assignment_type VARCHAR(30), -- 'scheduled', 'emergency', 'backup'
    assignment_status VARCHAR(20) DEFAULT 'scheduled', -- 'scheduled', 'confirmed', 'completed', 'cancelled'
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Accessibility compliance tracking
CREATE TABLE IF NOT EXISTS accessibility_compliance (
    compliance_id SERIAL PRIMARY KEY,
    facility_area VARCHAR(100),
    terminal VARCHAR(10),
    compliance_type VARCHAR(50), -- 'ada', 'section_508', 'wcag', 'local_regulations'
    compliance_status VARCHAR(20) DEFAULT 'compliant', -- 'compliant', 'non_compliant', 'under_review'
    last_audit_date TIMESTAMP,
    next_audit_date TIMESTAMP,
    audit_findings TEXT,
    corrective_actions JSONB DEFAULT '[]',
    responsible_party VARCHAR(100),
    compliance_score INTEGER CHECK (compliance_score BETWEEN 0 AND 100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Emergency response protocols
CREATE TABLE IF NOT EXISTS emergency_protocols (
    protocol_id SERIAL PRIMARY KEY,
    protocol_name VARCHAR(100) NOT NULL,
    protocol_type VARCHAR(50) NOT NULL, -- 'medical_emergency', 'security_incident', 'equipment_failure'
    trigger_conditions JSONB NOT NULL,
    response_steps JSONB NOT NULL,
    required_resources JSONB DEFAULT '[]',
    estimated_response_time INTEGER, -- in minutes
    priority_level VARCHAR(20) DEFAULT 'high', -- 'low', 'medium', 'high', 'critical'
    is_active BOOLEAN DEFAULT TRUE,
    last_tested TIMESTAMP,
    test_results TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Incident reporting
CREATE TABLE IF NOT EXISTS service_incidents (
    incident_id SERIAL PRIMARY KEY,
    request_id INTEGER REFERENCES special_assistance_requests(request_id),
    incident_type VARCHAR(50) NOT NULL, -- 'delay', 'equipment_failure', 'staff_shortage', 'communication_issue'
    severity_level VARCHAR(20) DEFAULT 'minor', -- 'minor', 'moderate', 'major', 'critical'
    description TEXT,
    root_cause TEXT,
    immediate_actions_taken TEXT,
    corrective_actions JSONB DEFAULT '[]',
    reported_by VARCHAR(50),
    reported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP,
    resolution_status VARCHAR(20) DEFAULT 'open', -- 'open', 'investigating', 'resolved', 'closed'
    follow_up_required BOOLEAN DEFAULT FALSE,
    follow_up_date TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Training records for special services staff
CREATE TABLE IF NOT EXISTS staff_training_records (
    training_id SERIAL PRIMARY KEY,
    staff_id VARCHAR(50) REFERENCES special_services_staff(staff_id),
    training_type VARCHAR(50) NOT NULL, -- 'accessibility', 'medical', 'language', 'equipment', 'emergency'
    training_provider VARCHAR(100),
    training_date DATE,
    expiry_date DATE,
    certification_number VARCHAR(50),
    training_score DECIMAL(5,2),
    training_status VARCHAR(20) DEFAULT 'completed', -- 'scheduled', 'in_progress', 'completed', 'expired'
    renewal_required BOOLEAN DEFAULT FALSE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Indexes for performance
CREATE INDEX IF NOT EXISTS idx_special_requests_passenger ON special_assistance_requests(passenger_id);
CREATE INDEX IF NOT EXISTS idx_special_requests_status ON special_assistance_requests(request_status);
CREATE INDEX IF NOT EXISTS idx_special_requests_flight ON special_assistance_requests(flight_id);
CREATE INDEX IF NOT EXISTS idx_accessibility_services_type ON accessibility_services(service_type);
CREATE INDEX IF NOT EXISTS idx_accessibility_services_location ON accessibility_services(location);
CREATE INDEX IF NOT EXISTS idx_service_assignments_request ON service_assignments(request_id);
CREATE INDEX IF NOT EXISTS idx_service_assignments_staff ON service_assignments(staff_member_id);
CREATE INDEX IF NOT EXISTS idx_medical_passenger ON medical_assistance(passenger_id);
CREATE INDEX IF NOT EXISTS idx_minors_passenger ON unaccompanied_minors(passenger_id);
CREATE INDEX IF NOT EXISTS idx_minors_status ON unaccompanied_minors(status);
CREATE INDEX IF NOT EXISTS idx_language_passenger ON language_services(passenger_id);
CREATE INDEX IF NOT EXISTS idx_equipment_type ON mobility_equipment(equipment_type);
CREATE INDEX IF NOT EXISTS idx_equipment_status ON mobility_equipment(status);
CREATE INDEX IF NOT EXISTS idx_equipment_location ON mobility_equipment(location);
CREATE INDEX IF NOT EXISTS idx_maintenance_equipment ON equipment_maintenance(equipment_id);
CREATE INDEX IF NOT EXISTS idx_performance_date ON service_performance_metrics(date);
CREATE INDEX IF NOT EXISTS idx_staff_role ON special_services_staff(staff_role);
CREATE INDEX IF NOT EXISTS idx_staff_status ON special_services_staff(status);
CREATE INDEX IF NOT EXISTS idx_staff_assignments_staff ON staff_assignments(staff_id);
CREATE INDEX IF NOT EXISTS idx_staff_assignments_date ON staff_assignments(assignment_date);
CREATE INDEX IF NOT EXISTS idx_compliance_area ON accessibility_compliance(facility_area);
CREATE INDEX IF NOT EXISTS idx_emergency_protocols_type ON emergency_protocols(protocol_type);
CREATE INDEX IF NOT EXISTS idx_incidents_request ON service_incidents(request_id);
CREATE INDEX IF NOT EXISTS idx_incidents_status ON service_incidents(resolution_status);
CREATE INDEX IF NOT EXISTS idx_training_staff ON staff_training_records(staff_id);
CREATE INDEX IF NOT EXISTS idx_training_type ON staff_training_records(training_type);

-- Insert sample accessibility services
INSERT INTO accessibility_services (service_name, service_type, location, terminal, zone, capacity, estimated_service_time, is_active) VALUES
('Wheelchair Assistance - Check-in', 'mobility', 'Terminal A Check-in Desk 1', 'A', 'check_in', 5, 10, true),
('Wheelchair Assistance - Security', 'mobility', 'Terminal A Security Checkpoint', 'A', 'security', 3, 15, true),
('Wheelchair Assistance - Gate', 'mobility', 'Terminal A Gate A12', 'A', 'gate', 2, 5, true),
('Visual Assistance - Boarding', 'visual', 'Terminal A Gate A12', 'A', 'boarding', 1, 20, true),
('Hearing Assistance - Information', 'hearing', 'Terminal A Information Desk', 'A', 'check_in', 2, 30, true),
('Medical Assistance Station', 'medical', 'Terminal A Medical Center', 'A', 'check_in', 1, 45, true)
ON CONFLICT DO NOTHING;

-- Insert sample mobility equipment
INSERT INTO mobility_equipment (equipment_id, equipment_type, equipment_model, equipment_size, location, terminal, zone, status, condition_rating) VALUES
('WC001', 'wheelchair', 'Standard Manual', 'standard', 'Terminal A Equipment Storage', 'A', 'check_in', 'available', 5),
('WC002', 'wheelchair', 'Standard Manual', 'standard', 'Terminal A Equipment Storage', 'A', 'check_in', 'available', 4),
('WC003', 'wheelchair', 'Electric Scooter', 'large', 'Terminal A Equipment Storage', 'A', 'check_in', 'maintenance', 3),
('WC004', 'wheelchair', 'Bariatric Manual', 'bariatric', 'Terminal A Equipment Storage', 'A', 'check_in', 'available', 5),
('WK001', 'walker', 'Standard Walker', 'standard', 'Terminal A Equipment Storage', 'A', 'check_in', 'available', 4),
('CN001', 'cane', 'Standard Cane', 'standard', 'Terminal A Equipment Storage', 'A', 'check_in', 'available', 5)
ON CONFLICT DO NOTHING;

-- Insert sample special services staff
INSERT INTO special_services_staff (staff_id, staff_name, staff_role, certifications, languages_spoken, status) VALUES
('SSA001', 'Maria Rodriguez', 'accessibility_assistant', '["ADA", "First Aid", "CPR"]', '["en", "es", "fr"]', 'active'),
('SSA002', 'James Chen', 'medical_assistant', '["EMT", "CPR", "Medical Assistant"]', '["en", "zh", "ko"]', 'active'),
('SSA003', 'Sarah Johnson', 'interpreter', '["Certified Interpreter"]', '["en", "es", "pt", "it"]', 'active'),
('SSA004', 'Michael Brown', 'supervisor', '["ADA", "Management", "Safety"]', '["en"]', 'active'),
('SSA005', 'Lisa Wong', 'accessibility_assistant', '["ADA", "Sign Language"]', '["en", "asl"]', 'active')
ON CONFLICT DO NOTHING;

-- Insert sample emergency protocols
INSERT INTO emergency_protocols (protocol_name, protocol_type, trigger_conditions, response_steps, required_resources, estimated_response_time, priority_level) VALUES
('Medical Emergency Response', 'medical_emergency', '{"symptoms": ["chest_pain", "difficulty_breathing", "unconsciousness"]}', '["Assess situation", "Call emergency services", "Provide immediate care", "Clear area", "Assist medical personnel"]', '["defibrillator", "oxygen", "first_aid_kit", "wheelchair"]', 5, 'critical'),
('Wheelchair Equipment Failure', 'equipment_failure', '{"equipment_type": "wheelchair", "failure_type": "mechanical"}', '["Assess passenger safety", "Locate replacement equipment", "Assist passenger transfer", "Report equipment issue"]', '["backup_wheelchair", "staff_assistance"]', 10, 'high'),
('Language Barrier Emergency', 'communication_issue', '{"communication_failure": true, "medical_emergency": true}', '["Locate interpreter immediately", "Use translation apps", "Contact emergency services with interpreter"]', '["interpreter", "translation_devices"]', 3, 'critical'),
('Unaccompanied Minor Delay', 'service_delay', '{"passenger_type": "unaccompanied_minor", "delay_minutes": {"gt": 30}}', '["Contact guardians", "Provide comfort items", "Arrange entertainment", "Monitor closely"]', '["child_care_specialist", "comfort_items"]', 15, 'high')
ON CONFLICT DO NOTHING;

-- Function to request special assistance
CREATE OR REPLACE FUNCTION request_special_assistance(
    p_passenger_id INTEGER,
    p_booking_id INTEGER,
    p_flight_id INTEGER,
    p_assistance_type VARCHAR,
    p_assistance_level VARCHAR DEFAULT 'standard',
    p_special_requirements TEXT DEFAULT NULL
) RETURNS INTEGER AS $$
DECLARE
    v_request_id INTEGER;
BEGIN
    INSERT INTO special_assistance_requests (
        passenger_id, booking_id, flight_id, assistance_type,
        assistance_level, special_requirements, requested_by
    ) VALUES (
        p_passenger_id, p_booking_id, p_flight_id, p_assistance_type,
        p_assistance_level, p_special_requirements, 'system'
    ) RETURNING request_id INTO v_request_id;

    -- Auto-assign available staff if possible
    PERFORM auto_assign_staff(v_request_id, p_assistance_type);

    RETURN v_request_id;
END;
$$ LANGUAGE plpgsql;

-- Function to auto-assign staff to requests
CREATE OR REPLACE FUNCTION auto_assign_staff(p_request_id INTEGER, p_assistance_type VARCHAR)
RETURNS BOOLEAN AS $$
DECLARE
    v_staff_record RECORD;
BEGIN
    -- Find available staff for the assistance type
    SELECT * INTO v_staff_record
    FROM special_services_staff
    WHERE status = 'active'
    AND staff_role = CASE
        WHEN p_assistance_type IN ('wheelchair', 'mobility') THEN 'accessibility_assistant'
        WHEN p_assistance_type = 'medical' THEN 'medical_assistant'
        WHEN p_assistance_type IN ('visual', 'hearing') THEN 'accessibility_assistant'
        ELSE 'accessibility_assistant'
    END
    AND assigned_requests < 5 -- Maximum concurrent assignments
    ORDER BY assigned_requests ASC
    LIMIT 1;

    IF FOUND THEN
        -- Create assignment
        INSERT INTO service_assignments (
            request_id, staff_member_id, staff_member_name
        ) VALUES (
            p_request_id, v_staff_record.staff_id, v_staff_record.staff_name
        );

        -- Update staff assignment count
        UPDATE special_services_staff
        SET assigned_requests = assigned_requests + 1
        WHERE staff_id = v_staff_record.staff_id;

        RETURN TRUE;
    END IF;

    RETURN FALSE;
END;
$$ LANGUAGE plpgsql;

-- Function to get service availability
CREATE OR REPLACE FUNCTION get_service_availability(p_service_type VARCHAR, p_terminal VARCHAR DEFAULT NULL)
RETURNS JSON AS $$
DECLARE
    result JSON;
BEGIN
    SELECT json_agg(
        json_build_object(
            'service_id', service_id,
            'service_name', service_name,
            'location', location,
            'capacity', capacity,
            'current_utilization', current_utilization,
            'availability_percentage', ROUND(
                ((capacity - current_utilization)::DECIMAL / capacity) * 100, 2
            ),
            'estimated_wait_time', CASE
                WHEN current_utilization >= capacity THEN 30
                ELSE ROUND((current_utilization::DECIMAL / capacity) * 20, 0)
            END
        )
    ) INTO result
    FROM accessibility_services
    WHERE service_type = p_service_type
    AND is_active = true
    AND (p_terminal IS NULL OR terminal = p_terminal);

    RETURN result;
END;
$$ LANGUAGE plpgsql;

-- Function to track equipment usage
CREATE OR REPLACE FUNCTION track_equipment_usage(
    p_equipment_id VARCHAR,
    p_assigned_to VARCHAR,
    p_usage_type VARCHAR DEFAULT 'assistance'
) RETURNS BOOLEAN AS $$
DECLARE
    v_usage_entry JSON;
BEGIN
    -- Create usage entry
    v_usage_entry := json_build_object(
        'assigned_to', p_assigned_to,
        'usage_type', p_usage_type,
        'assigned_at', CURRENT_TIMESTAMP
    );

    -- Update equipment
    UPDATE mobility_equipment
    SET status = 'in_use',
        assigned_to = p_assigned_to,
        assigned_at = CURRENT_TIMESTAMP,
        usage_log = usage_log || v_usage_entry
    WHERE equipment_id = p_equipment_id;

    RETURN TRUE;
END;
$$ LANGUAGE plpgsql;

-- Function to return equipment
CREATE OR REPLACE FUNCTION return_equipment(p_equipment_id VARCHAR, p_condition_rating INTEGER DEFAULT NULL)
RETURNS BOOLEAN AS $$
DECLARE
    v_return_entry JSON;
BEGIN
    -- Create return entry
    v_return_entry := json_build_object(
        'returned_at', CURRENT_TIMESTAMP,
        'condition_rating', p_condition_rating
    );

    -- Update equipment
    UPDATE mobility_equipment
    SET status = 'available',
        assigned_to = NULL,
        returned_at = CURRENT_TIMESTAMP,
        condition_rating = COALESCE(p_condition_rating, condition_rating),
        usage_log = usage_log || v_return_entry
    WHERE equipment_id = p_equipment_id;

    RETURN TRUE;
END;
$$ LANGUAGE plpgsql;

-- Function to calculate service performance metrics
CREATE OR REPLACE FUNCTION calculate_service_performance(p_date DATE, p_terminal VARCHAR DEFAULT NULL)
RETURNS JSON AS $$
DECLARE
    result JSON;
BEGIN
    SELECT json_build_object(
        'date', p_date,
        'terminal', p_terminal,
        'total_requests', (
            SELECT COUNT(*) FROM special_assistance_requests
            WHERE DATE(requested_at) = p_date
            AND (p_terminal IS NULL OR flight_id IN (
                SELECT flight_id FROM flights WHERE origin LIKE p_terminal || '%'
            ))
        ),
        'completed_requests', (
            SELECT COUNT(*) FROM special_assistance_requests
            WHERE DATE(requested_at) = p_date
            AND request_status = 'completed'
            AND (p_terminal IS NULL OR flight_id IN (
                SELECT flight_id FROM flights WHERE origin LIKE p_terminal || '%'
            ))
        ),
        'average_response_time', (
            SELECT ROUND(AVG(EXTRACT(EPOCH FROM (confirmed_at - requested_at))/60), 2)
            FROM special_assistance_requests
            WHERE DATE(requested_at) = p_date
            AND confirmed_at IS NOT NULL
            AND (p_terminal IS NULL OR flight_id IN (
                SELECT flight_id FROM flights WHERE origin LIKE p_terminal || '%'
            ))
        ),
        'equipment_utilization', (
            SELECT ROUND(
                COUNT(*)::DECIMAL /
                NULLIF((SELECT COUNT(*) FROM mobility_equipment
                       WHERE terminal = COALESCE(p_terminal, terminal)), 0) * 100, 2
            )
            FROM mobility_equipment
            WHERE status = 'in_use'
            AND terminal = COALESCE(p_terminal, terminal)
        ),
        'staff_utilization', (
            SELECT ROUND(AVG(assigned_requests)::DECIMAL / 5 * 100, 2)
            FROM special_services_staff
            WHERE status = 'active'
        )
    ) INTO result;

    RETURN result;
END;
$$ LANGUAGE plpgsql;

-- Function to check accessibility compliance
CREATE OR REPLACE FUNCTION check_accessibility_compliance(p_facility_area VARCHAR, p_terminal VARCHAR)
RETURNS JSON AS $$
DECLARE
    result JSON;
BEGIN
    SELECT json_build_object(
        'facility_area', facility_area,
        'terminal', terminal,
        'compliance_status', compliance_status,
        'compliance_score', compliance_score,
        'last_audit_date', last_audit_date,
        'next_audit_date', next_audit_date,
        'audit_findings', audit_findings,
        'corrective_actions', corrective_actions
    ) INTO result
    FROM accessibility_compliance
    WHERE facility_area = p_facility_area
    AND terminal = p_terminal
    ORDER BY created_at DESC
    LIMIT 1;

    RETURN result;
END;
$$ LANGUAGE plpgsql;

-- Trigger to update service utilization
CREATE OR REPLACE FUNCTION update_service_utilization()
RETURNS TRIGGER AS $$
BEGIN
    IF TG_OP = 'INSERT' THEN
        UPDATE accessibility_services
        SET current_utilization = current_utilization + 1
        WHERE service_id = NEW.service_id;
    ELSIF TG_OP = 'UPDATE' THEN
        IF OLD.assignment_status != 'completed' AND NEW.assignment_status = 'completed' THEN
            UPDATE accessibility_services
            SET current_utilization = GREATEST(current_utilization - 1, 0)
            WHERE service_id = NEW.service_id;
        END IF;
    END IF;

    RETURN COALESCE(NEW, OLD);
END;
$$ LANGUAGE plpgsql;

-- Create trigger for service utilization
CREATE TRIGGER update_service_utilization_trigger
    AFTER INSERT OR UPDATE ON service_assignments
    FOR EACH ROW
    EXECUTE FUNCTION update_service_utilization();

-- Function to get special services dashboard
CREATE OR REPLACE FUNCTION get_special_services_dashboard()
RETURNS JSON AS $$
DECLARE
    result JSON;
BEGIN
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
    ) INTO result;

    RETURN result;
END;
$$ LANGUAGE plpgsql;
