-- Customs & Border Protection Module Schema
-- Manages passport data, customs declarations, border control, and immigration processes

-- Passport data management
CREATE TABLE IF NOT EXISTS passport_data (
    passport_id VARCHAR(50) PRIMARY KEY,
    passport_number VARCHAR(20) UNIQUE NOT NULL,
    issuing_country VARCHAR(100) NOT NULL,
    issuing_authority VARCHAR(100),
    issue_date DATE NOT NULL,
    expiry_date DATE NOT NULL,
    passport_type VARCHAR(20) DEFAULT 'ordinary', -- 'ordinary', 'diplomatic', 'official', 'emergency'
    holder_name VARCHAR(255) NOT NULL,
    holder_nationality VARCHAR(100) NOT NULL,
    holder_birth_date DATE,
    holder_gender VARCHAR(10),
    holder_birth_place VARCHAR(255),
    holder_address JSONB,
    holder_contact JSONB,
    biometric_data JSONB DEFAULT '{}', -- fingerprints, facial recognition, iris scans
    visa_information JSONB DEFAULT '[]',
    travel_history JSONB DEFAULT '[]',
    security_clearance_level VARCHAR(20) DEFAULT 'standard', -- 'standard', 'enhanced', 'restricted'
    watchlist_status VARCHAR(20) DEFAULT 'clear', -- 'clear', 'watch', 'intercept', 'deny'
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Customs declarations
CREATE TABLE IF NOT EXISTS customs_declarations (
    declaration_id VARCHAR(50) PRIMARY KEY,
    declaration_number VARCHAR(100) UNIQUE NOT NULL,
    passenger_id VARCHAR(50),
    passport_id VARCHAR(50) REFERENCES passport_data(passport_id),
    flight_number VARCHAR(20),
    arrival_date TIMESTAMP,
    declaration_type VARCHAR(30) DEFAULT 'passenger', -- 'passenger', 'cargo', 'crew', 'diplomatic'
    goods_description TEXT,
    goods_value DECIMAL(12,2),
    goods_quantity INTEGER,
    goods_weight_kg DECIMAL(8,2),
    goods_origin_country VARCHAR(100),
    prohibited_items JSONB DEFAULT '[]',
    restricted_items JSONB DEFAULT '[]',
    duty_amount DECIMAL(10,2),
    tax_amount DECIMAL(10,2),
    currency_code VARCHAR(3) DEFAULT 'USD',
    declaration_status VARCHAR(20) DEFAULT 'pending', -- 'pending', 'approved', 'rejected', 'inspected', 'cleared'
    inspection_required BOOLEAN DEFAULT FALSE,
    inspection_reason TEXT,
    inspector_id VARCHAR(50),
    inspection_date TIMESTAMP,
    inspection_notes TEXT,
    clearance_date TIMESTAMP,
    customs_officer_id VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Border control entries
CREATE TABLE IF NOT EXISTS border_control_entries (
    entry_id VARCHAR(50) PRIMARY KEY,
    entry_number VARCHAR(100) UNIQUE NOT NULL,
    passport_id VARCHAR(50) REFERENCES passport_data(passport_id),
    entry_type VARCHAR(20) NOT NULL, -- 'arrival', 'departure', 'transit'
    port_of_entry VARCHAR(100) NOT NULL,
    flight_number VARCHAR(20),
    nationality VARCHAR(100),
    purpose_of_visit VARCHAR(50), -- 'tourism', 'business', 'transit', 'study', 'work', 'diplomatic'
    intended_stay_days INTEGER,
    accommodation_details JSONB,
    biometric_verification JSONB DEFAULT '{}',
    document_verification_status VARCHAR(20) DEFAULT 'pending', -- 'pending', 'verified', 'rejected', 'flagged'
    security_check_status VARCHAR(20) DEFAULT 'pending', -- 'pending', 'passed', 'failed', 'additional_check'
    health_check_status VARCHAR(20) DEFAULT 'pending', -- 'pending', 'passed', 'quarantine', 'denied'
    customs_declaration_id VARCHAR(50) REFERENCES customs_declarations(declaration_id),
    entry_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processing_officer_id VARCHAR(50),
    processing_notes TEXT,
    entry_status VARCHAR(20) DEFAULT 'processing', -- 'processing', 'approved', 'denied', 'deferred'
    exit_timestamp TIMESTAMP,
    exit_port VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Immigration records
CREATE TABLE IF NOT EXISTS immigration_records (
    record_id VARCHAR(50) PRIMARY KEY,
    passport_id VARCHAR(50) REFERENCES passport_data(passport_id),
    visa_type VARCHAR(30), -- 'tourist', 'business', 'student', 'work', 'diplomatic', 'transit'
    visa_number VARCHAR(50),
    visa_issue_date DATE,
    visa_expiry_date DATE,
    visa_issuing_country VARCHAR(100),
    entry_date TIMESTAMP,
    authorized_stay_days INTEGER,
    actual_departure_date TIMESTAMP,
    overstayed BOOLEAN DEFAULT FALSE,
    overstayed_days INTEGER DEFAULT 0,
    immigration_status VARCHAR(20) DEFAULT 'legal', -- 'legal', 'overstayed', 'deported', 'refugee'
    deportation_order BOOLEAN DEFAULT FALSE,
    deportation_date TIMESTAMP,
    deportation_reason TEXT,
    asylum_application BOOLEAN DEFAULT FALSE,
    asylum_status VARCHAR(20), -- 'pending', 'approved', 'denied', 'appeal'
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Biometric data
CREATE TABLE IF NOT EXISTS biometric_data (
    biometric_id VARCHAR(50) PRIMARY KEY,
    passport_id VARCHAR(50) REFERENCES passport_data(passport_id),
    biometric_type VARCHAR(30) NOT NULL, -- 'fingerprint', 'facial', 'iris', 'voice', 'dna'
    biometric_data BYTEA, -- encrypted biometric data
    capture_device VARCHAR(100),
    capture_location VARCHAR(100),
    capture_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    quality_score DECIMAL(3,2), -- 0.00 to 1.00
    verification_status VARCHAR(20) DEFAULT 'captured', -- 'captured', 'verified', 'rejected', 'expired'
    encryption_key_id VARCHAR(100),
    retention_period_days INTEGER DEFAULT 365,
    privacy_consent BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Watchlist and security alerts
CREATE TABLE IF NOT EXISTS security_watchlist (
    watchlist_id VARCHAR(50) PRIMARY KEY,
    individual_name VARCHAR(255) NOT NULL,
    aliases JSONB DEFAULT '[]',
    passport_numbers JSONB DEFAULT '[]',
    nationalities JSONB DEFAULT '[]',
    date_of_birth DATE,
    threat_level VARCHAR(20) DEFAULT 'low', -- 'low', 'medium', 'high', 'critical'
    threat_category VARCHAR(50), -- 'terrorism', 'organized_crime', 'human_trafficking', 'drug_trafficking'
    issuing_authority VARCHAR(100),
    issue_date DATE,
    expiry_date DATE,
    alert_description TEXT,
    action_required VARCHAR(50), -- 'deny_entry', 'additional_screening', 'monitor', 'notify'
    active BOOLEAN DEFAULT TRUE,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Border crossing history
CREATE TABLE IF NOT EXISTS border_crossing_history (
    crossing_id VARCHAR(50) PRIMARY KEY,
    passport_id VARCHAR(50) REFERENCES passport_data(passport_id),
    crossing_type VARCHAR(20) NOT NULL, -- 'entry', 'exit', 'transit'
    port_of_crossing VARCHAR(100) NOT NULL,
    crossing_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    flight_number VARCHAR(20),
    destination_country VARCHAR(100),
    purpose_of_travel VARCHAR(50),
    stay_duration_days INTEGER,
    accompanying_persons JSONB DEFAULT '[]',
    vehicle_information JSONB,
    goods_declared JSONB DEFAULT '[]',
    officer_id VARCHAR(50),
    processing_time_minutes INTEGER,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Visa applications and processing
CREATE TABLE IF NOT EXISTS visa_applications (
    application_id VARCHAR(50) PRIMARY KEY,
    application_number VARCHAR(100) UNIQUE NOT NULL,
    passport_id VARCHAR(50) REFERENCES passport_data(passport_id),
    applicant_name VARCHAR(255) NOT NULL,
    applicant_nationality VARCHAR(100) NOT NULL,
    visa_type VARCHAR(30) NOT NULL,
    visa_subtype VARCHAR(50),
    purpose_of_visit TEXT,
    intended_entry_date DATE,
    intended_stay_days INTEGER,
    accommodation_details JSONB,
    financial_documents JSONB DEFAULT '[]',
    invitation_letter BOOLEAN DEFAULT FALSE,
    invitation_details JSONB,
    employment_details JSONB,
    education_details JSONB,
    application_status VARCHAR(20) DEFAULT 'submitted', -- 'submitted', 'under_review', 'approved', 'rejected', 'withdrawn'
    submission_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    review_officer_id VARCHAR(50),
    review_date TIMESTAMP,
    approval_date TIMESTAMP,
    rejection_reason TEXT,
    visa_fee DECIMAL(8,2),
    processing_fee DECIMAL(8,2),
    service_fee DECIMAL(8,2),
    total_fee DECIMAL(8,2),
    payment_status VARCHAR(20) DEFAULT 'pending', -- 'pending', 'paid', 'refunded'
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Customs inspection reports
CREATE TABLE IF NOT EXISTS customs_inspection_reports (
    inspection_id VARCHAR(50) PRIMARY KEY,
    inspection_number VARCHAR(100) UNIQUE NOT NULL,
    declaration_id VARCHAR(50) REFERENCES customs_declarations(declaration_id),
    inspection_type VARCHAR(30) DEFAULT 'routine', -- 'routine', 'random', 'targeted', 'risk_based'
    inspection_reason TEXT,
    inspector_id VARCHAR(50),
    inspection_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    inspection_end TIMESTAMP,
    inspection_duration_minutes INTEGER,
    inspection_location VARCHAR(100),
    goods_examined JSONB DEFAULT '[]',
    prohibited_items_found JSONB DEFAULT '[]',
    restricted_items_found JSONB DEFAULT '[]',
    seized_items JSONB DEFAULT '[]',
    estimated_value DECIMAL(12,2),
    violation_type VARCHAR(50),
    fine_amount DECIMAL(10,2),
    penalty_assessed BOOLEAN DEFAULT FALSE,
    legal_action_required BOOLEAN DEFAULT FALSE,
    legal_reference TEXT,
    inspection_notes TEXT,
    follow_up_required BOOLEAN DEFAULT FALSE,
    follow_up_date TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Immigration enforcement actions
CREATE TABLE IF NOT EXISTS immigration_enforcement (
    enforcement_id VARCHAR(50) PRIMARY KEY,
    enforcement_number VARCHAR(100) UNIQUE NOT NULL,
    passport_id VARCHAR(50) REFERENCES passport_data(passport_id),
    enforcement_type VARCHAR(30) NOT NULL, -- 'deportation', 'detention', 'voluntary_return', 'asylum_denial'
    enforcement_reason TEXT,
    enforcement_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    enforcement_officer_id VARCHAR(50),
    detention_facility VARCHAR(100),
    detention_start TIMESTAMP,
    detention_end TIMESTAMP,
    deportation_country VARCHAR(100),
    deportation_flight VARCHAR(20),
    deportation_date TIMESTAMP,
    escort_officers JSONB DEFAULT '[]',
    legal_representation BOOLEAN DEFAULT FALSE,
    legal_rep_details JSONB,
    appeal_filed BOOLEAN DEFAULT FALSE,
    appeal_status VARCHAR(20), -- 'pending', 'approved', 'denied'
    appeal_date TIMESTAMP,
    humanitarian_considerations TEXT,
    enforcement_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Border security incidents
CREATE TABLE IF NOT EXISTS border_security_incidents (
    incident_id VARCHAR(50) PRIMARY KEY,
    incident_number VARCHAR(100) UNIQUE NOT NULL,
    incident_type VARCHAR(30) NOT NULL, -- 'unauthorized_entry', 'document_fraud', 'human_trafficking', 'drug_smuggling', 'weapon_trafficking'
    severity_level VARCHAR(20) DEFAULT 'low', -- 'low', 'medium', 'high', 'critical'
    incident_location VARCHAR(100),
    incident_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reporting_officer_id VARCHAR(50),
    involved_passports JSONB DEFAULT '[]',
    involved_individuals JSONB DEFAULT '[]',
    contraband_details JSONB DEFAULT '[]',
    estimated_value DECIMAL(12,2),
    arrest_made BOOLEAN DEFAULT FALSE,
    arrest_details JSONB,
    investigation_required BOOLEAN DEFAULT TRUE,
    investigation_officer_id VARCHAR(50),
    investigation_status VARCHAR(20) DEFAULT 'pending', -- 'pending', 'ongoing', 'completed', 'closed'
    prosecution_status VARCHAR(20), -- 'pending', 'charged', 'convicted', 'acquitted', 'dismissed'
    incident_description TEXT,
    response_actions JSONB DEFAULT '[]',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Travel document verification
CREATE TABLE IF NOT EXISTS document_verification (
    verification_id VARCHAR(50) PRIMARY KEY,
    passport_id VARCHAR(50) REFERENCES passport_data(passport_id),
    verification_type VARCHAR(30) NOT NULL, -- 'visual', 'uv_light', 'magnetic', 'chip', 'biometric'
    verification_device VARCHAR(100),
    verification_officer_id VARCHAR(50),
    verification_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    verification_result VARCHAR(20) DEFAULT 'valid', -- 'valid', 'invalid', 'suspect', 'damaged'
    authenticity_score DECIMAL(3,2), -- 0.00 to 1.00
    security_features_checked JSONB DEFAULT '[]',
    anomalies_detected JSONB DEFAULT '[]',
    verification_notes TEXT,
    requires_additional_check BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Border control performance metrics
CREATE TABLE IF NOT EXISTS border_performance_metrics (
    metric_id SERIAL PRIMARY KEY,
    date DATE,
    port_of_entry VARCHAR(100),
    total_entries INTEGER DEFAULT 0,
    total_departures INTEGER DEFAULT 0,
    average_processing_time_minutes DECIMAL(5,2),
    peak_hour_entries INTEGER DEFAULT 0,
    denied_entries INTEGER DEFAULT 0,
    inspection_rate DECIMAL(5,2), -- percentage of entries inspected
    biometric_verification_rate DECIMAL(5,2),
    document_fraud_detected INTEGER DEFAULT 0,
    security_incidents INTEGER DEFAULT 0,
    customs_violations INTEGER DEFAULT 0,
    overstayed_passengers INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Indexes for performance
CREATE INDEX IF NOT EXISTS idx_passport_data_number ON passport_data(passport_number);
CREATE INDEX IF NOT EXISTS idx_passport_data_country ON passport_data(issuing_country);
CREATE INDEX IF NOT EXISTS idx_passport_data_expiry ON passport_data(expiry_date);
CREATE INDEX IF NOT EXISTS idx_customs_declarations_passport ON customs_declarations(passport_id);
CREATE INDEX IF NOT EXISTS idx_customs_declarations_status ON customs_declarations(declaration_status);
CREATE INDEX IF NOT EXISTS idx_border_entries_passport ON border_control_entries(passport_id);
CREATE INDEX IF NOT EXISTS idx_border_entries_status ON border_control_entries(entry_status);
CREATE INDEX IF NOT EXISTS idx_border_entries_date ON border_control_entries(entry_timestamp);
CREATE INDEX IF NOT EXISTS idx_immigration_passport ON immigration_records(passport_id);
CREATE INDEX IF NOT EXISTS idx_immigration_status ON immigration_records(immigration_status);
CREATE INDEX IF NOT EXISTS idx_biometric_passport ON biometric_data(passport_id);
CREATE INDEX IF NOT EXISTS idx_biometric_type ON biometric_data(biometric_type);
CREATE INDEX IF NOT EXISTS idx_watchlist_name ON security_watchlist(individual_name);
CREATE INDEX IF NOT EXISTS idx_watchlist_level ON security_watchlist(threat_level);
CREATE INDEX IF NOT EXISTS idx_crossing_passport ON border_crossing_history(passport_id);
CREATE INDEX IF NOT EXISTS idx_crossing_date ON border_crossing_history(crossing_date);
CREATE INDEX IF NOT EXISTS idx_visa_passport ON visa_applications(passport_id);
CREATE INDEX IF NOT EXISTS idx_visa_status ON visa_applications(application_status);
CREATE INDEX IF NOT EXISTS idx_inspection_declaration ON customs_inspection_reports(declaration_id);
CREATE INDEX IF NOT EXISTS idx_enforcement_passport ON immigration_enforcement(passport_id);
CREATE INDEX IF NOT EXISTS idx_enforcement_type ON immigration_enforcement(enforcement_type);
CREATE INDEX IF NOT EXISTS idx_security_incidents_type ON border_security_incidents(incident_type);
CREATE INDEX IF NOT EXISTS idx_security_incidents_date ON border_security_incidents(incident_date);
CREATE INDEX IF NOT EXISTS idx_verification_passport ON document_verification(passport_id);
CREATE INDEX IF NOT EXISTS idx_verification_result ON document_verification(verification_result);
CREATE INDEX IF NOT EXISTS idx_performance_date ON border_performance_metrics(date);
CREATE INDEX IF NOT EXISTS idx_performance_port ON border_performance_metrics(port_of_entry);

-- Insert sample passport data
INSERT INTO passport_data (passport_id, passport_number, issuing_country, issue_date, expiry_date, holder_name, holder_nationality) VALUES
('PASS001', 'A12345678', 'United States', '2020-01-15', '2030-01-14', 'John Smith', 'American'),
('PASS002', 'B98765432', 'United Kingdom', '2019-06-20', '2029-06-19', 'Jane Doe', 'British'),
('PASS003', 'C55566677', 'Canada', '2021-03-10', '2031-03-09', 'Bob Johnson', 'Canadian'),
('PASS004', 'D44433322', 'Australia', '2022-08-05', '2032-08-04', 'Alice Brown', 'Australian');

-- Insert sample security watchlist
INSERT INTO security_watchlist (watchlist_id, individual_name, threat_level, threat_category, issuing_authority) VALUES
('WATCH001', 'John Suspicious', 'high', 'terrorism', 'INTERPOL'),
('WATCH002', 'Jane Fraudster', 'medium', 'organized_crime', 'FBI'),
('WATCH003', 'Bob Trafficker', 'critical', 'human_trafficking', 'ICE');

-- Function to validate passport
CREATE OR REPLACE FUNCTION validate_passport(p_passport_number VARCHAR)
RETURNS JSON AS $$
DECLARE
    v_passport_record RECORD;
    v_validation JSON;
BEGIN
    -- Get passport details
    SELECT * INTO v_passport_record FROM passport_data WHERE passport_number = p_passport_number;

    IF NOT FOUND THEN
        RETURN json_build_object(
            'valid', false,
            'reason', 'Passport not found in database'
        );
    END IF

    -- Check expiry
    IF v_passport_record.expiry_date < CURRENT_DATE THEN
        RETURN json_build_object(
            'valid', false,
            'reason', 'Passport has expired',
            'expiry_date', v_passport_record.expiry_date
        );
    END IF

    -- Check watchlist
    IF EXISTS (
        SELECT 1 FROM security_watchlist
        WHERE individual_name = v_passport_record.holder_name
        AND active = true
    ) THEN
        RETURN json_build_object(
            'valid', false,
            'reason', 'Individual is on security watchlist',
            'requires_additional_screening', true
        );
    END IF

    RETURN json_build_object(
        'valid', true,
        'passport_id', v_passport_record.passport_id,
        'holder_name', v_passport_record.holder_name,
        'nationality', v_passport_record.holder_nationality,
        'expiry_date', v_passport_record.expiry_date
    );
END;
$$ LANGUAGE plpgsql;

-- Function to calculate border processing time
CREATE OR REPLACE FUNCTION calculate_border_processing_time(p_entry_id VARCHAR)
RETURNS INTEGER AS $$
DECLARE
    v_entry_time TIMESTAMP;
    v_processing_time INTEGER;
BEGIN
    -- Get entry timestamp
    SELECT entry_timestamp INTO v_entry_time
    FROM border_control_entries
    WHERE entry_id = p_entry_id;

    IF NOT FOUND THEN
        RETURN NULL;
    END IF

    -- Calculate processing time in minutes
    v_processing_time := EXTRACT(EPOCH FROM (CURRENT_TIMESTAMP - v_entry_time)) / 60;

    RETURN v_processing_time;
END;
$$ LANGUAGE plpgsql;

-- Function to check visa validity
CREATE OR REPLACE FUNCTION check_visa_validity(p_passport_id VARCHAR, p_entry_date DATE)
RETURNS JSON AS $$
DECLARE
    v_visa_record RECORD;
    v_validity JSON;
BEGIN
    -- Get latest visa for passport
    SELECT * INTO v_visa_record
    FROM immigration_records
    WHERE passport_id = p_passport_id
    AND visa_expiry_date >= p_entry_date
    ORDER BY visa_issue_date DESC
    LIMIT 1;

    IF NOT FOUND THEN
        RETURN json_build_object(
            'valid', false,
            'reason', 'No valid visa found'
        );
    END IF

    -- Check if overstayed previous visit
    IF v_visa_record.overstayed THEN
        RETURN json_build_object(
            'valid', false,
            'reason', 'Previous visa overstayed',
            'overstayed_days', v_visa_record.overstayed_days
        );
    END IF

    RETURN json_build_object(
        'valid', true,
        'visa_type', v_visa_record.visa_type,
        'visa_number', v_visa_record.visa_number,
        'expiry_date', v_visa_record.visa_expiry_date,
        'authorized_stay_days', v_visa_record.authorized_stay_days
    );
END;
$$ LANGUAGE plpgsql;

-- Function to generate customs declaration
CREATE OR REPLACE FUNCTION generate_customs_declaration(
    p_passenger_data JSONB,
    p_goods_data JSONB
) RETURNS VARCHAR AS $$
DECLARE
    v_declaration_id VARCHAR(50);
    v_declaration_number VARCHAR(100);
BEGIN
    -- Generate declaration ID and number
    v_declaration_id := 'DECL-' || TO_CHAR(CURRENT_TIMESTAMP, 'YYYYMMDD') || '-' || LPAD(NEXTVAL('declaration_seq')::TEXT, 6, '0');
    v_declaration_number := 'CD-' || TO_CHAR(CURRENT_TIMESTAMP, 'YYYYMMDDHH24MISS');

    -- Insert customs declaration
    INSERT INTO customs_declarations (
        declaration_id, declaration_number, passenger_id, passport_id,
        flight_number, arrival_date, goods_description, goods_value,
        goods_quantity, goods_weight_kg, goods_origin_country
    ) VALUES (
        v_declaration_id,
        v_declaration_number,
        p_passenger_data->>'passenger_id',
        p_passenger_data->>'passport_id',
        p_passenger_data->>'flight_number',
        (p_passenger_data->>'arrival_date')::TIMESTAMP,
        p_goods_data->>'description',
        (p_goods_data->>'value')::DECIMAL,
        (p_goods_data->>'quantity')::INTEGER,
        (p_goods_data->>'weight_kg')::DECIMAL,
        p_goods_data->>'origin_country'
    );

    RETURN v_declaration_id;
END;
$$ LANGUAGE plpgsql;

-- Function to process border entry
CREATE OR REPLACE FUNCTION process_border_entry(p_entry_data JSONB)
RETURNS JSON AS $$
DECLARE
    v_entry_id VARCHAR(50);
    v_entry_number VARCHAR(100);
    v_validation_result JSON;
    v_visa_result JSON;
BEGIN
    -- Validate passport
    SELECT validate_passport(p_entry_data->>'passport_number') INTO v_validation_result;

    IF NOT (v_validation_result->>'valid')::BOOLEAN THEN
        RETURN json_build_object(
            'success', false,
            'reason', v_validation_result->>'reason',
            'entry_status', 'denied'
        );
    END IF

    -- Check visa validity
    SELECT check_visa_validity(v_validation_result->>'passport_id', CURRENT_DATE) INTO v_visa_result;

    IF NOT (v_visa_result->>'valid')::BOOLEAN THEN
        RETURN json_build_object(
            'success', false,
            'reason', v_visa_result->>'reason',
            'entry_status', 'denied'
        );
    END IF

    -- Generate entry ID and number
    v_entry_id := 'ENTRY-' || TO_CHAR(CURRENT_TIMESTAMP, 'YYYYMMDD') || '-' || LPAD(NEXTVAL('entry_seq')::TEXT, 6, '0');
    v_entry_number := 'BE-' || TO_CHAR(CURRENT_TIMESTAMP, 'YYYYMMDDHH24MISS');

    -- Insert border entry
    INSERT INTO border_control_entries (
        entry_id, entry_number, passport_id, entry_type, port_of_entry,
        flight_number, nationality, purpose_of_visit, intended_stay_days
    ) VALUES (
        v_entry_id,
        v_entry_number,
        v_validation_result->>'passport_id',
        COALESCE(p_entry_data->>'entry_type', 'arrival'),
        p_entry_data->>'port_of_entry',
        p_entry_data->>'flight_number',
        v_validation_result->>'nationality',
        p_entry_data->>'purpose_of_visit',
        (p_entry_data->>'intended_stay_days')::INTEGER
    );

    RETURN json_build_object(
        'success', true,
        'entry_id', v_entry_id,
        'entry_number', v_entry_number,
        'entry_status', 'approved',
        'processing_time_minutes', calculate_border_processing_time(v_entry_id)
    );
END;
$$ LANGUAGE plpgsql;

-- Function to get border control dashboard
CREATE OR REPLACE FUNCTION get_border_control_dashboard()
RETURNS JSON AS $$
DECLARE
    result JSON;
BEGIN
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
    ) INTO result;

    RETURN result;
END;
$$ LANGUAGE plpgsql;

-- Function to generate border control report
CREATE OR REPLACE FUNCTION generate_border_control_report(p_start_date DATE, p_end_date DATE)
RETURNS JSON AS $$
DECLARE
    result JSON;
BEGIN
    SELECT json_build_object(
        'period', json_build_object('start_date', p_start_date, 'end_date', p_end_date),
        'border_activity', json_build_object(
            'total_entries', (
                SELECT COUNT(*) FROM border_control_entries
                WHERE DATE(entry_timestamp) BETWEEN p_start_date AND p_end_date
                AND entry_status = 'approved'
            ),
            'total_departures', (
                SELECT COUNT(*) FROM border_control_entries
                WHERE DATE(exit_timestamp) BETWEEN p_start_date AND p_end_date
            ),
            'denied_entries', (
                SELECT COUNT(*) FROM border_control_entries
                WHERE DATE(entry_timestamp) BETWEEN p_start_date AND p_end_date
                AND entry_status = 'denied'
            ),
            'by_nationality', (
                SELECT json_agg(
                    json_build_object(
                        'nationality', nationality,
                        'entries', COUNT(*),
                        'percentage', ROUND(COUNT(*)::DECIMAL /
                            (SELECT COUNT(*) FROM border_control_entries
                             WHERE DATE(entry_timestamp) BETWEEN p_start_date AND p_end_date
                             AND entry_status = 'approved') * 100, 2)
                    )
                )
                FROM border_control_entries
                WHERE DATE(entry_timestamp) BETWEEN p_start_date AND p_end_date
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
                WHERE DATE(bce.entry_timestamp) BETWEEN p_start_date AND p_end_date
                AND sw.active = true
            ),
            'security_incidents', (
                SELECT COUNT(*) FROM border_security_incidents
                WHERE DATE(incident_date) BETWEEN p_start_date AND p_end_date
            ),
            'document_fraud_cases', (
                SELECT COUNT(*) FROM document_verification
                WHERE DATE(verification_timestamp) BETWEEN p_start_date AND p_end_date
                AND verification_result IN ('invalid', 'suspect')
            )
        ),
        'customs_performance', json_build_object(
            'declarations_processed', (
                SELECT COUNT(*) FROM customs_declarations
                WHERE DATE(created_at) BETWEEN p_start_date AND p_end_date
            ),
            'inspections_conducted', (
                SELECT COUNT(*) FROM customs_inspection_reports
                WHERE DATE(inspection_start) BETWEEN p_start_date AND p_end_date
            ),
            'violations_detected', (
                SELECT COUNT(*) FROM customs_inspection_reports
                WHERE DATE(inspection_start) BETWEEN p_start_date AND p_end_date
                AND violation_type IS NOT NULL
            ),
            'revenue_collected', (
                SELECT SUM(duty_amount + tax_amount) FROM customs_declarations
                WHERE DATE(created_at) BETWEEN p_start_date AND p_end_date
                AND declaration_status = 'cleared'
            )
        ),
        'immigration_status', json_build_object(
            'visa_applications_processed', (
                SELECT COUNT(*) FROM visa_applications
                WHERE DATE(submission_date) BETWEEN p_start_date AND p_end_date
                AND application_status IN ('approved', 'rejected')
            ),
            'overstayed_cases', (
                SELECT COUNT(*) FROM immigration_records
                WHERE overstayed = true
                AND entry_date BETWEEN p_start_date AND p_end_date
            ),
            'deportation_orders', (
                SELECT COUNT(*) FROM immigration_enforcement
                WHERE DATE(enforcement_date) BETWEEN p_start_date AND p_end_date
                AND enforcement_type = 'deportation'
            )
        ),
        'processing_efficiency', json_build_object(
            'average_processing_time', (
                SELECT ROUND(AVG(calculate_border_processing_time(entry_id)), 2)
                FROM border_control_entries
                WHERE DATE(entry_timestamp) BETWEEN p_start_date AND p_end_date
                AND entry_status = 'approved'
            ),
            'biometric_success_rate', (
                SELECT ROUND(
                    (SELECT COUNT(*) FROM document_verification
                     WHERE DATE(verification_timestamp) BETWEEN p_start_date AND p_end_date
                     AND verification_result = 'valid')::DECIMAL /
                    (SELECT COUNT(*) FROM document_verification
                     WHERE DATE(verification_timestamp) BETWEEN p_start_date AND p_end_date)::DECIMAL * 100, 2
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
                WHERE DATE(entry_timestamp) BETWEEN p_start_date AND p_end_date
                AND entry_status = 'approved'
                GROUP BY EXTRACT(HOUR FROM entry_timestamp)
                ORDER BY EXTRACT(HOUR FROM entry_timestamp)
            )
        )
    ) INTO result;

    RETURN result;
END;
$$ LANGUAGE plpgsql;
