-- Self Check-in System Schema
-- Manages automated passenger check-in kiosks and biometric verification

-- Self-service kiosks
CREATE TABLE IF NOT EXISTS self_checkin_kiosks (
    kiosk_id SERIAL PRIMARY KEY,
    kiosk_name VARCHAR(100) NOT NULL,
    location VARCHAR(255),
    terminal VARCHAR(10),
    gate VARCHAR(10),
    latitude DECIMAL(10,8),
    longitude DECIMAL(11,8),
    status VARCHAR(20) DEFAULT 'active', -- 'active', 'maintenance', 'offline'
    kiosk_type VARCHAR(50) DEFAULT 'standard', -- 'standard', 'premium', 'accessible'
    supported_languages JSONB DEFAULT '["en"]',
    features JSONB DEFAULT '{}', -- biometric, touch_screen, voice_guidance, etc.
    last_maintenance DATE,
    next_maintenance DATE,
    battery_level INTEGER CHECK (battery_level BETWEEN 0 AND 100),
    network_status VARCHAR(20) DEFAULT 'online',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Biometric verification data
CREATE TABLE IF NOT EXISTS biometric_verification (
    verification_id SERIAL PRIMARY KEY,
    passenger_id INTEGER REFERENCES passengers(passenger_id),
    kiosk_id INTEGER REFERENCES self_checkin_kiosks(kiosk_id),
    verification_type VARCHAR(50) NOT NULL, -- 'facial', 'fingerprint', 'iris', 'voice'
    verification_data JSONB, -- encrypted biometric data
    confidence_score DECIMAL(5,2), -- 0-100
    verification_status VARCHAR(20) DEFAULT 'pending', -- 'success', 'failed', 'pending'
    verification_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address INET,
    device_fingerprint VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Check-in sessions
CREATE TABLE IF NOT EXISTS checkin_sessions (
    session_id SERIAL PRIMARY KEY,
    passenger_id INTEGER REFERENCES passengers(passenger_id),
    booking_id INTEGER REFERENCES bookings(booking_id),
    kiosk_id INTEGER REFERENCES self_checkin_kiosks(kiosk_id),
    session_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    session_end TIMESTAMP,
    checkin_status VARCHAR(20) DEFAULT 'in_progress', -- 'completed', 'cancelled', 'timeout'
    steps_completed JSONB DEFAULT '[]', -- array of completed check-in steps
    documents_verified JSONB DEFAULT '[]', -- passport, visa, etc.
    services_selected JSONB DEFAULT '{}', -- seat selection, meals, etc.
    total_duration INTEGER, -- in seconds
    language_used VARCHAR(10) DEFAULT 'en',
    accessibility_features JSONB DEFAULT '{}',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Seat selection during check-in
CREATE TABLE IF NOT EXISTS seat_selections (
    selection_id SERIAL PRIMARY KEY,
    session_id INTEGER REFERENCES checkin_sessions(session_id),
    flight_id INTEGER REFERENCES flights(flight_id),
    original_seat VARCHAR(10),
    selected_seat VARCHAR(10),
    seat_class VARCHAR(20),
    selection_reason VARCHAR(100), -- 'preferred', 'extra_legroom', 'emergency_exit'
    selection_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    confirmed BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Service selections during check-in
CREATE TABLE IF NOT EXISTS service_selections (
    selection_id SERIAL PRIMARY KEY,
    session_id INTEGER REFERENCES checkin_sessions(session_id),
    service_type VARCHAR(50), -- 'meal', 'baggage', 'insurance', 'lounge'
    service_option VARCHAR(100),
    service_price DECIMAL(8,2),
    selection_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    confirmed BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Check-in analytics
CREATE TABLE IF NOT EXISTS checkin_analytics (
    analytics_id SERIAL PRIMARY KEY,
    kiosk_id INTEGER REFERENCES self_checkin_kiosks(kiosk_id),
    date DATE,
    total_sessions INTEGER DEFAULT 0,
    completed_sessions INTEGER DEFAULT 0,
    average_duration INTEGER, -- in seconds
    peak_hour INTEGER, -- 0-23
    busiest_day VARCHAR(10), -- 'monday', 'tuesday', etc.
    error_rate DECIMAL(5,2), -- percentage
    customer_satisfaction DECIMAL(3,1), -- 1-5 scale
    biometric_success_rate DECIMAL(5,2), -- percentage
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Kiosk maintenance logs
CREATE TABLE IF NOT EXISTS kiosk_maintenance (
    maintenance_id SERIAL PRIMARY KEY,
    kiosk_id INTEGER REFERENCES self_checkin_kiosks(kiosk_id),
    maintenance_type VARCHAR(50), -- 'cleaning', 'software_update', 'hardware_repair'
    maintenance_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    technician_name VARCHAR(100),
    description TEXT,
    parts_replaced JSONB DEFAULT '[]',
    downtime_minutes INTEGER,
    cost DECIMAL(8,2),
    next_maintenance DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Check-in queue management
CREATE TABLE IF NOT EXISTS checkin_queue (
    queue_id SERIAL PRIMARY KEY,
    kiosk_id INTEGER REFERENCES self_checkin_kiosks(kiosk_id),
    passenger_id INTEGER REFERENCES passengers(passenger_id),
    booking_id INTEGER REFERENCES bookings(booking_id),
    queue_position INTEGER,
    estimated_wait_time INTEGER, -- in minutes
    queue_status VARCHAR(20) DEFAULT 'waiting', -- 'waiting', 'processing', 'completed'
    queue_entry_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processing_start_time TIMESTAMP,
    processing_end_time TIMESTAMP,
    priority_level INTEGER DEFAULT 1, -- 1=normal, 2=premium, 3=vip
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Digital boarding passes generated during check-in
CREATE TABLE IF NOT EXISTS digital_boarding_passes (
    pass_id SERIAL PRIMARY KEY,
    session_id INTEGER REFERENCES checkin_sessions(session_id),
    passenger_id INTEGER REFERENCES passengers(passenger_id),
    booking_id INTEGER REFERENCES bookings(booking_id),
    flight_id INTEGER REFERENCES flights(flight_id),
    seat_number VARCHAR(10),
    gate VARCHAR(10),
    boarding_time TIME,
    qr_code_data TEXT,
    barcode_data TEXT,
    pass_status VARCHAR(20) DEFAULT 'active', -- 'active', 'used', 'expired'
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP,
    download_count INTEGER DEFAULT 0,
    last_download TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Check-in preferences
CREATE TABLE IF NOT EXISTS checkin_preferences (
    preference_id SERIAL PRIMARY KEY,
    passenger_id INTEGER REFERENCES passengers(passenger_id),
    preferred_language VARCHAR(10) DEFAULT 'en',
    accessibility_needs JSONB DEFAULT '{}',
    biometric_consent BOOLEAN DEFAULT FALSE,
    notification_preferences JSONB DEFAULT '{}',
    seat_preferences JSONB DEFAULT '{}',
    service_preferences JSONB DEFAULT '{}',
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(passenger_id)
);

-- Kiosk performance metrics
CREATE TABLE IF NOT EXISTS kiosk_performance (
    performance_id SERIAL PRIMARY KEY,
    kiosk_id INTEGER REFERENCES self_checkin_kiosks(kiosk_id),
    metric_date DATE,
    uptime_percentage DECIMAL(5,2),
    average_session_time INTEGER, -- in seconds
    error_count INTEGER DEFAULT 0,
    user_satisfaction_score DECIMAL(3,1),
    touch_screen_accuracy DECIMAL(5,2), -- percentage
    biometric_accuracy DECIMAL(5,2), -- percentage
    network_latency DECIMAL(8,2), -- in milliseconds
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Indexes for performance
CREATE INDEX IF NOT EXISTS idx_kiosks_location ON self_checkin_kiosks(location);
CREATE INDEX IF NOT EXISTS idx_kiosks_status ON self_checkin_kiosks(status);
CREATE INDEX IF NOT EXISTS idx_biometric_passenger ON biometric_verification(passenger_id);
CREATE INDEX IF NOT EXISTS idx_biometric_status ON biometric_verification(verification_status);
CREATE INDEX IF NOT EXISTS idx_sessions_passenger ON checkin_sessions(passenger_id);
CREATE INDEX IF NOT EXISTS idx_sessions_status ON checkin_sessions(checkin_status);
CREATE INDEX IF NOT EXISTS idx_sessions_kiosk ON checkin_sessions(kiosk_id);
CREATE INDEX IF NOT EXISTS idx_queue_kiosk ON checkin_queue(kiosk_id);
CREATE INDEX IF NOT EXISTS idx_queue_status ON checkin_queue(queue_status);
CREATE INDEX IF NOT EXISTS idx_boarding_passes_passenger ON digital_boarding_passes(passenger_id);
CREATE INDEX IF NOT EXISTS idx_boarding_passes_flight ON digital_boarding_passes(flight_id);
CREATE INDEX IF NOT EXISTS idx_analytics_kiosk_date ON checkin_analytics(kiosk_id, date);
CREATE INDEX IF NOT EXISTS idx_maintenance_kiosk ON kiosk_maintenance(kiosk_id);

-- Insert sample kiosks
INSERT INTO self_checkin_kiosks (kiosk_name, location, terminal, gate, status, kiosk_type, supported_languages, features) VALUES
('Terminal A Kiosk 01', 'Terminal A Main Entrance', 'A', NULL, 'active', 'standard', '["en", "es", "fr"]', '{"biometric": true, "touch_screen": true, "voice_guidance": true}'),
('Terminal B Premium Kiosk', 'Terminal B Business Lounge', 'B', NULL, 'active', 'premium', '["en", "de", "ja"]', '{"biometric": true, "touch_screen": true, "voice_guidance": true, "priority_service": true}'),
('Gate 12 Express Kiosk', 'Gate 12 Waiting Area', 'A', '12', 'active', 'standard', '["en", "zh", "ko"]', '{"biometric": false, "touch_screen": true, "voice_guidance": true, "express_checkin": true}'),
('Accessible Kiosk Main', 'Main Terminal Accessible Area', 'A', NULL, 'active', 'accessible', '["en", "es", "fr", "de"]', '{"biometric": true, "touch_screen": true, "voice_guidance": true, "wheelchair_accessible": true, "large_text": true}'),
('Terminal C Family Kiosk', 'Terminal C Family Area', 'C', NULL, 'maintenance', 'standard', '["en", "pt", "it"]', '{"biometric": true, "touch_screen": true, "voice_guidance": true, "family_services": true}')
ON CONFLICT DO NOTHING;

-- Insert sample check-in preferences
INSERT INTO checkin_preferences (passenger_id, preferred_language, accessibility_needs, biometric_consent, notification_preferences) VALUES
(1, 'en', '{}', true, '{"email": true, "sms": true, "push": false}'),
(2, 'es', '{"large_text": true}', false, '{"email": true, "sms": false, "push": true}'),
(3, 'fr', '{}', true, '{"email": false, "sms": true, "push": true}')
ON CONFLICT (passenger_id) DO NOTHING;

-- Function to calculate queue position
CREATE OR REPLACE FUNCTION calculate_queue_position(p_kiosk_id INTEGER)
RETURNS INTEGER AS $$
DECLARE
    queue_pos INTEGER;
BEGIN
    SELECT COALESCE(MAX(queue_position), 0) + 1
    INTO queue_pos
    FROM checkin_queue
    WHERE kiosk_id = p_kiosk_id AND queue_status = 'waiting';

    RETURN queue_pos;
END;
$$ LANGUAGE plpgsql;

-- Function to estimate wait time
CREATE OR REPLACE FUNCTION estimate_wait_time(p_kiosk_id INTEGER, p_queue_position INTEGER)
RETURNS INTEGER AS $$
DECLARE
    avg_session_time INTEGER;
    estimated_time INTEGER;
BEGIN
    -- Get average session time for this kiosk in the last hour
    SELECT COALESCE(AVG(total_duration), 300) -- default 5 minutes
    INTO avg_session_time
    FROM checkin_sessions
    WHERE kiosk_id = p_kiosk_id
    AND session_start >= CURRENT_TIMESTAMP - INTERVAL '1 hour'
    AND checkin_status = 'completed';

    -- Calculate estimated wait time (position - 1) * average time
    estimated_time := (p_queue_position - 1) * GREATEST(avg_session_time, 60); -- minimum 1 minute

    RETURN estimated_time;
END;
$$ LANGUAGE plpgsql;

-- Function to generate QR code data for boarding pass
CREATE OR REPLACE FUNCTION generate_boarding_pass_qr(p_pass_id INTEGER)
RETURNS TEXT AS $$
DECLARE
    pass_data JSON;
    qr_string TEXT;
BEGIN
    -- Get boarding pass data
    SELECT json_build_object(
        'pass_id', pass_id,
        'passenger_id', passenger_id,
        'flight_id', flight_id,
        'seat', seat_number,
        'gate', gate,
        'boarding_time', boarding_time,
        'generated_at', generated_at
    )
    INTO pass_data
    FROM digital_boarding_passes
    WHERE pass_id = p_pass_id;

    -- Generate QR code string (simplified for demo)
    qr_string := 'BPASS_' || encode(pass_data::text::bytea, 'base64');

    -- Update the boarding pass with QR data
    UPDATE digital_boarding_passes
    SET qr_code_data = qr_string
    WHERE pass_id = p_pass_id;

    RETURN qr_string;
END;
$$ LANGUAGE plpgsql;

-- Function to get kiosk availability
CREATE OR REPLACE FUNCTION get_kiosk_availability()
RETURNS JSON AS $$
DECLARE
    result JSON;
BEGIN
    SELECT json_agg(
        json_build_object(
            'kiosk_id', kiosk_id,
            'kiosk_name', kiosk_name,
            'location', location,
            'status', status,
            'current_queue', (
                SELECT COUNT(*)
                FROM checkin_queue
                WHERE kiosk_id = sk.kiosk_id AND queue_status = 'waiting'
            ),
            'estimated_wait_time', (
                SELECT estimate_wait_time(kiosk_id, 1)
            )
        )
    )
    INTO result
    FROM self_checkin_kiosks sk
    WHERE status = 'active'
    ORDER BY location, kiosk_name;

    RETURN result;
END;
$$ LANGUAGE plpgsql;

-- Trigger to update kiosk performance metrics
CREATE OR REPLACE FUNCTION update_kiosk_performance()
RETURNS TRIGGER AS $$
BEGIN
    -- Update analytics when session completes
    IF NEW.checkin_status = 'completed' AND (OLD.checkin_status IS NULL OR OLD.checkin_status != 'completed') THEN
        INSERT INTO checkin_analytics (
            kiosk_id, date, total_sessions, completed_sessions,
            average_duration, peak_hour
        ) VALUES (
            NEW.kiosk_id,
            CURRENT_DATE,
            1,
            1,
            EXTRACT(EPOCH FROM (NEW.session_end - NEW.session_start)),
            EXTRACT(HOUR FROM NEW.session_start)
        )
        ON CONFLICT (kiosk_id, date) DO UPDATE SET
            total_sessions = checkin_analytics.total_sessions + 1,
            completed_sessions = checkin_analytics.completed_sessions + 1,
            average_duration = (
                (checkin_analytics.average_duration * (checkin_analytics.completed_sessions - 1) +
                 EXTRACT(EPOCH FROM (NEW.session_end - NEW.session_start))) /
                checkin_analytics.completed_sessions
            );
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Create trigger for session completion
CREATE TRIGGER update_kiosk_performance_trigger
    AFTER UPDATE ON checkin_sessions
    FOR EACH ROW
    EXECUTE FUNCTION update_kiosk_performance();

-- Function to get check-in statistics
CREATE OR REPLACE FUNCTION get_checkin_statistics(p_start_date DATE, p_end_date DATE)
RETURNS JSON AS $$
DECLARE
    result JSON;
BEGIN
    SELECT json_build_object(
        'total_sessions', (
            SELECT COUNT(*) FROM checkin_sessions
            WHERE session_start::date BETWEEN p_start_date AND p_end_date
        ),
        'completed_sessions', (
            SELECT COUNT(*) FROM checkin_sessions
            WHERE checkin_status = 'completed'
            AND session_start::date BETWEEN p_start_date AND p_end_date
        ),
        'average_duration', (
            SELECT ROUND(AVG(total_duration))
            FROM checkin_sessions
            WHERE checkin_status = 'completed'
            AND session_start::date BETWEEN p_start_date AND p_end_date
        ),
        'biometric_success_rate', (
            SELECT ROUND(
                (COUNT(CASE WHEN verification_status = 'success' THEN 1 END) * 100.0) /
                NULLIF(COUNT(*), 0), 2
            )
            FROM biometric_verification
            WHERE verification_time::date BETWEEN p_start_date AND p_end_date
        ),
        'peak_hours', (
            SELECT json_agg(
                json_build_object(
                    'hour', hour,
                    'sessions', session_count
                )
            )
            FROM (
                SELECT
                    EXTRACT(HOUR FROM session_start) as hour,
                    COUNT(*) as session_count
                FROM checkin_sessions
                WHERE session_start::date BETWEEN p_start_date AND p_end_date
                GROUP BY EXTRACT(HOUR FROM session_start)
                ORDER BY session_count DESC
                LIMIT 5
            ) peak
        )
    ) INTO result;

    RETURN result;
END;
$$ LANGUAGE plpgsql;
