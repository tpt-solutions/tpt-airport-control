-- Enhanced Baggage Handling & Tracking Schema
-- Manages smart baggage tags, RFID tracking, and automated routing

-- Smart baggage tags
CREATE TABLE IF NOT EXISTS smart_baggage_tags (
    tag_id VARCHAR(50) PRIMARY KEY,
    tag_type VARCHAR(30) NOT NULL, -- 'rfid', 'nfc', 'qr', 'barcode'
    passenger_id INTEGER REFERENCES passengers(passenger_id),
    booking_id INTEGER REFERENCES bookings(booking_id),
    flight_id INTEGER REFERENCES flights(flight_id),
    baggage_type VARCHAR(20) NOT NULL, -- 'checked', 'carry_on', 'special'
    weight_kg DECIMAL(6,2),
    dimensions JSONB, -- length, width, height
    contents_description TEXT,
    special_handling JSONB DEFAULT '{}', -- fragile, hazardous, oversized
    security_status VARCHAR(20) DEFAULT 'pending', -- 'pending', 'cleared', 'flagged'
    status VARCHAR(20) DEFAULT 'registered', -- 'registered', 'checked_in', 'loaded', 'in_transit', 'arrived', 'delivered', 'lost'
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Baggage tracking events
CREATE TABLE IF NOT EXISTS baggage_tracking_events (
    event_id SERIAL PRIMARY KEY,
    tag_id VARCHAR(50) REFERENCES smart_baggage_tags(tag_id),
    event_type VARCHAR(30) NOT NULL, -- 'check_in', 'security_scan', 'loaded', 'unloaded', 'transferred', 'delivered'
    location VARCHAR(100),
    zone VARCHAR(50), -- 'check_in_counter', 'security', 'loading_area', 'aircraft', 'baggage_claim'
    sensor_id VARCHAR(50),
    sensor_type VARCHAR(30), -- 'rfid_reader', 'weight_sensor', 'camera', 'conveyor_sensor'
    event_data JSONB DEFAULT '{}',
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    recorded_by VARCHAR(50), -- system, sensor, manual
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Baggage routing rules
CREATE TABLE IF NOT EXISTS baggage_routing_rules (
    rule_id SERIAL PRIMARY KEY,
    rule_name VARCHAR(100) NOT NULL,
    priority INTEGER DEFAULT 1,
    conditions JSONB NOT NULL, -- flight, destination, baggage_type, special_handling
    actions JSONB NOT NULL, -- routing_path, priority_level, handling_instructions
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Conveyor belt systems
CREATE TABLE IF NOT EXISTS conveyor_systems (
    conveyor_id VARCHAR(50) PRIMARY KEY,
    conveyor_name VARCHAR(100) NOT NULL,
    conveyor_type VARCHAR(30) NOT NULL, -- 'check_in', 'security', 'sorting', 'loading', 'unloading'
    location VARCHAR(100),
    terminal VARCHAR(10),
    length_meters DECIMAL(6,2),
    speed_mps DECIMAL(4,2),
    capacity_items_per_minute INTEGER,
    sensors JSONB DEFAULT '[]', -- array of sensor IDs
    status VARCHAR(20) DEFAULT 'operational', -- 'operational', 'maintenance', 'offline'
    last_maintenance TIMESTAMP,
    next_maintenance TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Baggage sorting stations
CREATE TABLE IF NOT EXISTS sorting_stations (
    station_id VARCHAR(50) PRIMARY KEY,
    station_name VARCHAR(100) NOT NULL,
    station_type VARCHAR(30) NOT NULL, -- 'manual', 'automated', 'robotic'
    location VARCHAR(100),
    terminal VARCHAR(10),
    capacity_items_per_hour INTEGER,
    connected_conveyors JSONB DEFAULT '[]',
    operating_hours JSONB,
    status VARCHAR(20) DEFAULT 'operational',
    efficiency_rating DECIMAL(5,2), -- percentage
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Baggage containers and carts
CREATE TABLE IF NOT EXISTS baggage_containers (
    container_id VARCHAR(50) PRIMARY KEY,
    container_type VARCHAR(30) NOT NULL, -- 'uld', 'cart', 'bin', 'pallet'
    flight_id INTEGER REFERENCES flights(flight_id),
    destination VARCHAR(100),
    max_weight_kg DECIMAL(6,2),
    current_weight_kg DECIMAL(6,2) DEFAULT 0,
    max_volume_m3 DECIMAL(6,2),
    current_volume_m3 DECIMAL(6,2) DEFAULT 0,
    status VARCHAR(20) DEFAULT 'empty', -- 'empty', 'loading', 'loaded', 'in_transit', 'unloading'
    location VARCHAR(100),
    assigned_to VARCHAR(50), -- employee or system
    loaded_at TIMESTAMP,
    unloaded_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Container contents
CREATE TABLE IF NOT EXISTS container_contents (
    content_id SERIAL PRIMARY KEY,
    container_id VARCHAR(50) REFERENCES baggage_containers(container_id),
    tag_id VARCHAR(50) REFERENCES smart_baggage_tags(tag_id),
    position_in_container INTEGER,
    loaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    unloaded_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Baggage reconciliation
CREATE TABLE IF NOT EXISTS baggage_reconciliation (
    reconciliation_id SERIAL PRIMARY KEY,
    flight_id INTEGER REFERENCES flights(flight_id),
    reconciliation_type VARCHAR(30) NOT NULL, -- 'departure', 'arrival', 'transfer'
    expected_items INTEGER,
    actual_items INTEGER,
    missing_items INTEGER DEFAULT 0,
    extra_items INTEGER DEFAULT 0,
    discrepancies JSONB DEFAULT '[]',
    status VARCHAR(20) DEFAULT 'pending', -- 'pending', 'in_progress', 'completed', 'issues_found'
    reconciled_by VARCHAR(50),
    reconciled_at TIMESTAMP,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Lost baggage reports
CREATE TABLE IF NOT EXISTS lost_baggage_reports (
    report_id SERIAL PRIMARY KEY,
    tag_id VARCHAR(50) REFERENCES smart_baggage_tags(tag_id),
    passenger_id INTEGER REFERENCES passengers(passenger_id),
    flight_id INTEGER REFERENCES flights(flight_id),
    report_type VARCHAR(30) NOT NULL, -- 'missing', 'damaged', 'delayed'
    description TEXT,
    last_seen_location VARCHAR(100),
    last_seen_timestamp TIMESTAMP,
    reported_by VARCHAR(50),
    reported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(20) DEFAULT 'open', -- 'open', 'investigating', 'found', 'compensated', 'closed'
    investigation_notes TEXT,
    resolution TEXT,
    resolved_by VARCHAR(50),
    resolved_at TIMESTAMP,
    compensation_amount DECIMAL(8,2),
    compensation_currency VARCHAR(3) DEFAULT 'USD',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- RFID sensor network
CREATE TABLE IF NOT EXISTS rfid_sensors (
    sensor_id VARCHAR(50) PRIMARY KEY,
    sensor_name VARCHAR(100) NOT NULL,
    sensor_type VARCHAR(30) NOT NULL, -- 'fixed', 'handheld', 'portal'
    location VARCHAR(100),
    terminal VARCHAR(10),
    zone VARCHAR(50),
    coverage_area_m2 DECIMAL(8,2),
    frequency_mhz DECIMAL(6,2),
    status VARCHAR(20) DEFAULT 'active', -- 'active', 'maintenance', 'offline'
    last_reading TIMESTAMP,
    battery_level DECIMAL(5,2),
    firmware_version VARCHAR(20),
    connected_devices JSONB DEFAULT '[]',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Sensor readings
CREATE TABLE IF NOT EXISTS sensor_readings (
    reading_id SERIAL PRIMARY KEY,
    sensor_id VARCHAR(50) REFERENCES rfid_sensors(sensor_id),
    tag_id VARCHAR(50) REFERENCES smart_baggage_tags(tag_id),
    reading_type VARCHAR(30) NOT NULL, -- 'detection', 'location', 'movement'
    signal_strength DECIMAL(5,2),
    distance_meters DECIMAL(6,2),
    direction VARCHAR(20), -- 'entering', 'exiting', 'passing'
    confidence_level DECIMAL(5,2),
    raw_data JSONB DEFAULT '{}',
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Baggage handling performance metrics
CREATE TABLE IF NOT EXISTS baggage_performance_metrics (
    metric_id SERIAL PRIMARY KEY,
    date DATE,
    terminal VARCHAR(10),
    total_baggage_processed INTEGER DEFAULT 0,
    average_processing_time_minutes DECIMAL(6,2),
    on_time_delivery_percentage DECIMAL(5,2),
    lost_baggage_rate DECIMAL(6,4),
    damaged_baggage_rate DECIMAL(6,4),
    customer_satisfaction_rating DECIMAL(3,2),
    system_uptime_percentage DECIMAL(5,2),
    peak_hour_throughput INTEGER,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Automated baggage routing decisions
CREATE TABLE IF NOT EXISTS baggage_routing_decisions (
    decision_id SERIAL PRIMARY KEY,
    tag_id VARCHAR(50) REFERENCES smart_baggage_tags(tag_id),
    decision_type VARCHAR(30) NOT NULL, -- 'initial_routing', 'rerouting', 'priority_change'
    original_path JSONB,
    new_path JSONB,
    reason VARCHAR(100),
    triggered_by VARCHAR(50), -- 'system', 'manual', 'emergency'
    decision_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    executed_at TIMESTAMP,
    execution_status VARCHAR(20) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Baggage security screening
CREATE TABLE IF NOT EXISTS baggage_security_screening (
    screening_id SERIAL PRIMARY KEY,
    tag_id VARCHAR(50) REFERENCES smart_baggage_tags(tag_id),
    screening_type VARCHAR(30) NOT NULL, -- 'xray', 'explosive_trace', 'chemical', 'manual'
    screening_station VARCHAR(50),
    screener_id VARCHAR(50),
    result VARCHAR(20) DEFAULT 'pending', -- 'pending', 'cleared', 'flagged', 'failed'
    threat_level VARCHAR(20), -- 'none', 'low', 'medium', 'high', 'critical'
    anomalies_detected JSONB DEFAULT '[]',
    screening_duration_seconds INTEGER,
    image_reference VARCHAR(100),
    notes TEXT,
    screened_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Baggage delivery notifications
CREATE TABLE IF NOT EXISTS baggage_delivery_notifications (
    notification_id SERIAL PRIMARY KEY,
    tag_id VARCHAR(50) REFERENCES smart_baggage_tags(tag_id),
    passenger_id INTEGER REFERENCES passengers(passenger_id),
    notification_type VARCHAR(30) NOT NULL, -- 'ready_for_pickup', 'delayed', 'delivered', 'lost'
    message TEXT,
    delivery_location VARCHAR(100),
    estimated_delivery_time TIMESTAMP,
    actual_delivery_time TIMESTAMP,
    notification_channels JSONB DEFAULT '["push"]',
    status VARCHAR(20) DEFAULT 'sent', -- 'pending', 'sent', 'delivered', 'failed'
    sent_at TIMESTAMP,
    delivered_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Indexes for performance
CREATE INDEX IF NOT EXISTS idx_smart_tags_passenger ON smart_baggage_tags(passenger_id);
CREATE INDEX IF NOT EXISTS idx_smart_tags_flight ON smart_baggage_tags(flight_id);
CREATE INDEX IF NOT EXISTS idx_smart_tags_status ON smart_baggage_tags(status);
CREATE INDEX IF NOT EXISTS idx_tracking_events_tag ON baggage_tracking_events(tag_id);
CREATE INDEX IF NOT EXISTS idx_tracking_events_timestamp ON baggage_tracking_events(timestamp);
CREATE INDEX IF NOT EXISTS idx_tracking_events_type ON baggage_tracking_events(event_type);
CREATE INDEX IF NOT EXISTS idx_conveyor_systems_status ON conveyor_systems(status);
CREATE INDEX IF NOT EXISTS idx_sorting_stations_status ON sorting_stations(status);
CREATE INDEX IF NOT EXISTS idx_containers_flight ON baggage_containers(flight_id);
CREATE INDEX IF NOT EXISTS idx_containers_status ON baggage_containers(status);
CREATE INDEX IF NOT EXISTS idx_container_contents_container ON container_contents(container_id);
CREATE INDEX IF NOT EXISTS idx_reconciliation_flight ON baggage_reconciliation(flight_id);
CREATE INDEX IF NOT EXISTS idx_lost_reports_tag ON lost_baggage_reports(tag_id);
CREATE INDEX IF NOT EXISTS idx_lost_reports_status ON lost_baggage_reports(status);
CREATE INDEX IF NOT EXISTS idx_rfid_sensors_status ON rfid_sensors(status);
CREATE INDEX IF NOT EXISTS idx_sensor_readings_sensor ON sensor_readings(sensor_id);
CREATE INDEX IF NOT EXISTS idx_sensor_readings_timestamp ON sensor_readings(timestamp);
CREATE INDEX IF NOT EXISTS idx_performance_date ON baggage_performance_metrics(date);
CREATE INDEX IF NOT EXISTS idx_routing_decisions_tag ON baggage_routing_decisions(tag_id);
CREATE INDEX IF NOT EXISTS idx_security_screening_tag ON baggage_security_screening(tag_id);
CREATE INDEX IF NOT EXISTS idx_delivery_notifications_tag ON baggage_delivery_notifications(tag_id);

-- Insert sample smart baggage tags
INSERT INTO smart_baggage_tags (tag_id, tag_type, passenger_id, baggage_type, weight_kg, status) VALUES
('TAG001RFID', 'rfid', 1, 'checked', 23.5, 'registered'),
('TAG002NFC', 'nfc', 2, 'checked', 18.2, 'checked_in'),
('TAG003QR', 'qr', 3, 'carry_on', 8.7, 'registered'),
('TAG004BAR', 'barcode', 4, 'checked', 15.3, 'in_transit'),
('TAG005RFID', 'rfid', 5, 'special', 12.1, 'registered')
ON CONFLICT DO NOTHING;

-- Insert sample conveyor systems
INSERT INTO conveyor_systems (conveyor_id, conveyor_name, conveyor_type, location, terminal, length_meters, speed_mps, capacity_items_per_minute, status) VALUES
('CONV001', 'Check-in Conveyor A1', 'check_in', 'Terminal A Check-in Hall', 'A', 50.0, 0.5, 120, 'operational'),
('CONV002', 'Security Conveyor B2', 'security', 'Terminal B Security Area', 'B', 75.0, 0.3, 80, 'operational'),
('CONV003', 'Sorting Conveyor C1', 'sorting', 'Baggage Sorting Facility', 'C', 200.0, 1.2, 300, 'operational'),
('CONV004', 'Loading Conveyor D1', 'loading', 'Aircraft Loading Area', 'D', 100.0, 0.8, 150, 'maintenance')
ON CONFLICT DO NOTHING;

-- Insert sample sorting stations
INSERT INTO sorting_stations (station_id, station_name, station_type, location, terminal, capacity_items_per_hour, status, efficiency_rating) VALUES
('SORT001', 'Automated Sorting Station A', 'automated', 'Baggage Hall A', 'A', 2400, 'operational', 95.2),
('SORT002', 'Manual Sorting Station B', 'manual', 'Baggage Hall B', 'B', 1200, 'operational', 87.5),
('SORT003', 'Robotic Sorting Station C', 'robotic', 'Baggage Hall C', 'C', 3600, 'maintenance', 92.8)
ON CONFLICT DO NOTHING;

-- Insert sample RFID sensors
INSERT INTO rfid_sensors (sensor_id, sensor_name, sensor_type, location, terminal, zone, coverage_area_m2, frequency_mhz, status) VALUES
('RFID001', 'Check-in Portal Sensor', 'portal', 'Terminal A Check-in', 'A', 'check_in_counter', 25.0, 865.0, 'active'),
('RFID002', 'Security Scanner Sensor', 'fixed', 'Terminal B Security', 'B', 'security', 15.0, 915.0, 'active'),
('RFID003', 'Conveyor Tracking Sensor', 'fixed', 'Baggage Sorting', 'C', 'sorting', 10.0, 865.0, 'active'),
('RFID004', 'Baggage Claim Sensor', 'portal', 'Terminal A Baggage Claim', 'A', 'baggage_claim', 30.0, 915.0, 'active')
ON CONFLICT DO NOTHING;

-- Insert sample baggage routing rules
INSERT INTO baggage_routing_rules (rule_name, priority, conditions, actions, is_active) VALUES
('Priority Baggage Routing', 1, '{"baggage_type": "special", "special_handling": ["fragile"]}', '{"routing_path": ["express_conveyor", "priority_sorting"], "priority_level": "high", "handling_instructions": ["handle_with_care", "no_stacking"]}', true),
('International Transfer Routing', 2, '{"destination": "international", "transfer_required": true}', '{"routing_path": ["transfer_conveyor", "customs_sorting"], "priority_level": "medium", "handling_instructions": ["customs_declaration_required"]}', true),
('Oversized Baggage Routing', 3, '{"weight_kg": {"gt": 32}, "dimensions": {"length": {"gt": 158}}', '{"routing_path": ["heavy_load_conveyor", "special_handling"], "priority_level": "high", "handling_instructions": ["oversized_handling", "extra_staff_required"]}', true),
('Standard Baggage Routing', 4, '{}', '{"routing_path": ["standard_conveyor", "automated_sorting"], "priority_level": "normal", "handling_instructions": []}', true)
ON CONFLICT DO NOTHING;

-- Function to track baggage movement
CREATE OR REPLACE FUNCTION track_baggage_movement(
    p_tag_id VARCHAR,
    p_event_type VARCHAR,
    p_location VARCHAR,
    p_zone VARCHAR,
    p_sensor_id VARCHAR DEFAULT NULL,
    p_event_data JSONB DEFAULT '{}'
) RETURNS INTEGER AS $$
DECLARE
    v_event_id INTEGER;
BEGIN
    INSERT INTO baggage_tracking_events (
        tag_id, event_type, location, zone, sensor_id, event_data
    ) VALUES (
        p_tag_id, p_event_type, p_location, p_zone, p_sensor_id, p_event_data
    ) RETURNING event_id INTO v_event_id;

    -- Update baggage status based on event type
    UPDATE smart_baggage_tags
    SET status = CASE
        WHEN p_event_type = 'check_in' THEN 'checked_in'
        WHEN p_event_type = 'loaded' THEN 'loaded'
        WHEN p_event_type = 'unloaded' THEN 'arrived'
        WHEN p_event_type = 'delivered' THEN 'delivered'
        ELSE status
    END,
    updated_at = CURRENT_TIMESTAMP
    WHERE tag_id = p_tag_id;

    RETURN v_event_id;
END;
$$ LANGUAGE plpgsql;

-- Function to get baggage location history
CREATE OR REPLACE FUNCTION get_baggage_location_history(p_tag_id VARCHAR)
RETURNS JSON AS $$
DECLARE
    result JSON;
BEGIN
    SELECT json_agg(
        json_build_object(
            'event_type', event_type,
            'location', location,
            'zone', zone,
            'timestamp', timestamp,
            'sensor_id', sensor_id
        ) ORDER BY timestamp
    )
    INTO result
    FROM baggage_tracking_events
    WHERE tag_id = p_tag_id;

    RETURN result;
END;
$$ LANGUAGE plpgsql;

-- Function to calculate baggage processing time
CREATE OR REPLACE FUNCTION calculate_baggage_processing_time(p_tag_id VARCHAR)
RETURNS INTERVAL AS $$
DECLARE
    check_in_time TIMESTAMP;
    delivery_time TIMESTAMP;
BEGIN
    SELECT MIN(timestamp) INTO check_in_time
    FROM baggage_tracking_events
    WHERE tag_id = p_tag_id AND event_type = 'check_in';

    SELECT MAX(timestamp) INTO delivery_time
    FROM baggage_tracking_events
    WHERE tag_id = p_tag_id AND event_type = 'delivered';

    IF check_in_time IS NOT NULL AND delivery_time IS NOT NULL THEN
        RETURN delivery_time - check_in_time;
    END IF;

    RETURN NULL;
END;
$$ LANGUAGE plpgsql;

-- Function to find optimal baggage routing
CREATE OR REPLACE FUNCTION find_optimal_baggage_route(
    p_tag_id VARCHAR,
    p_current_location VARCHAR,
    p_destination VARCHAR
) RETURNS JSON AS $$
DECLARE
    v_tag smart_baggage_tags%ROWTYPE;
    v_route JSON;
BEGIN
    -- Get baggage details
    SELECT * INTO v_tag FROM smart_baggage_tags WHERE tag_id = p_tag_id;

    -- Apply routing rules based on baggage characteristics
    SELECT actions INTO v_route
    FROM baggage_routing_rules
    WHERE is_active = true
    AND (
        -- Check special handling conditions
        (conditions->>'baggage_type' IS NULL OR conditions->>'baggage_type' = v_tag.baggage_type)
        AND (conditions->>'weight_kg' IS NULL OR (v_tag.weight_kg > (conditions->'weight_kg'->>'gt')::DECIMAL))
        AND (conditions->>'special_handling' IS NULL OR v_tag.special_handling ?| ARRAY(SELECT jsonb_array_elements_text(conditions->'special_handling')))
    )
    ORDER BY priority ASC
    LIMIT 1;

    -- Return default route if no specific rule matches
    IF v_route IS NULL THEN
        SELECT actions INTO v_route
        FROM baggage_routing_rules
        WHERE rule_name = 'Standard Baggage Routing';
    END IF;

    RETURN v_route;
END;
$$ LANGUAGE plpgsql;

-- Function to generate baggage performance report
CREATE OR REPLACE FUNCTION generate_baggage_performance_report(p_start_date DATE, p_end_date DATE)
RETURNS JSON AS $$
DECLARE
    result JSON;
BEGIN
    SELECT json_build_object(
        'period', json_build_object('start_date', p_start_date, 'end_date', p_end_date),
        'summary', json_build_object(
            'total_baggage_processed', (
                SELECT COUNT(*) FROM baggage_tracking_events
                WHERE event_type = 'delivered'
                AND DATE(timestamp) BETWEEN p_start_date AND p_end_date
            ),
            'average_processing_time', (
                SELECT AVG(EXTRACT(EPOCH FROM calculate_baggage_processing_time(tag_id))/3600)
                FROM smart_baggage_tags
                WHERE updated_at BETWEEN p_start_date AND p_end_date
                AND status = 'delivered'
            ),
            'on_time_delivery_rate', (
                SELECT ROUND(
                    COUNT(*)::DECIMAL /
                    NULLIF((SELECT COUNT(*) FROM smart_baggage_tags
                           WHERE updated_at BETWEEN p_start_date AND p_end_date), 0) * 100, 2
                )
                FROM smart_baggage_tags
                WHERE updated_at BETWEEN p_start_date AND p_end_date
                AND status = 'delivered'
            ),
            'lost_baggage_rate', (
                SELECT ROUND(
                    COUNT(*)::DECIMAL /
                    NULLIF((SELECT COUNT(*) FROM smart_baggage_tags
                           WHERE created_at BETWEEN p_start_date AND p_end_date), 0) * 100, 4
                )
                FROM lost_baggage_reports
                WHERE reported_at BETWEEN p_start_date AND p_end_date
            )
        ),
        'by_terminal', (
            SELECT json_agg(
                json_build_object(
                    'terminal', terminal,
                    'baggage_processed', total_processed,
                    'avg_processing_time', avg_time,
                    'efficiency_rating', efficiency
                )
            )
            FROM baggage_performance_metrics
            WHERE date BETWEEN p_start_date AND p_end_date
        ),
        'top_issues', (
            SELECT json_agg(
                json_build_object(
                    'issue_type', report_type,
                    'count', issue_count
                )
            )
            FROM (
                SELECT report_type, COUNT(*) as issue_count
                FROM lost_baggage_reports
                WHERE reported_at BETWEEN p_start_date AND p_end_date
                GROUP BY report_type
                ORDER BY issue_count DESC
                LIMIT 5
            ) issues
        )
    ) INTO result;

    RETURN result;
END;
$$ LANGUAGE plpgsql;

-- Function to predict baggage delivery time
CREATE OR REPLACE FUNCTION predict_baggage_delivery_time(
    p_flight_id INTEGER,
    p_terminal VARCHAR
) RETURNS TIMESTAMP AS $$
DECLARE
    avg_delivery_time INTERVAL;
    flight_arrival TIMESTAMP;
BEGIN
    -- Get flight arrival time
    SELECT arrival_time INTO flight_arrival
    FROM flights
    WHERE flight_id = p_flight_id;

    -- Calculate average delivery time for this terminal
    SELECT AVG(calculate_baggage_processing_time(tag_id)) INTO avg_delivery_time
    FROM smart_baggage_tags st
    JOIN baggage_tracking_events bte ON st.tag_id = bte.tag_id
    WHERE bte.zone = 'baggage_claim'
    AND DATE(st.updated_at) >= CURRENT_DATE - INTERVAL '30 days';

    -- Predict delivery time
    IF flight_arrival IS NOT NULL AND avg_delivery_time IS NOT NULL THEN
        RETURN flight_arrival + avg_delivery_time;
    END IF;

    -- Default prediction: 45 minutes after arrival
    RETURN flight_arrival + INTERVAL '45 minutes';
END;
$$ LANGUAGE plpgsql;

-- Trigger to automatically create delivery notifications
CREATE OR REPLACE FUNCTION auto_create_delivery_notification()
RETURNS TRIGGER AS $$
BEGIN
    IF NEW.status = 'arrived' AND OLD.status != 'arrived' THEN
        INSERT INTO baggage_delivery_notifications (
            tag_id, passenger_id, notification_type, message,
            delivery_location, estimated_delivery_time
        )
        SELECT
            NEW.tag_id,
            NEW.passenger_id,
            'ready_for_pickup',
            'Your baggage is ready for pickup at Baggage Claim Carousel ' ||
            (SELECT carousel FROM flights WHERE flight_id = NEW.flight_id),
            'Baggage Claim Area',
            predict_baggage_delivery_time(NEW.flight_id, 'A') -- Assuming terminal A for now
        FROM smart_baggage_tags
        WHERE tag_id = NEW.tag_id;
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Create trigger for automatic delivery notifications
CREATE TRIGGER auto_create_delivery_notification_trigger
    AFTER UPDATE ON smart_baggage_tags
    FOR EACH ROW
    EXECUTE FUNCTION auto_create_delivery_notification();

-- Function to reconcile baggage for a flight
CREATE OR REPLACE FUNCTION reconcile_flight_baggage(p_flight_id INTEGER, p_reconciliation_type VARCHAR)
RETURNS INTEGER AS $$
DECLARE
    expected_count INTEGER;
    actual_count INTEGER;
    reconciliation_id INTEGER;
BEGIN
    -- Count expected baggage (from passenger bookings)
    SELECT COUNT(*) INTO expected_count
    FROM smart_baggage_tags
    WHERE flight_id = p_flight_id AND baggage_type = 'checked';

    -- Count actual baggage (from tracking events)
    SELECT COUNT(DISTINCT tag_id) INTO actual_count
    FROM baggage_tracking_events
    WHERE tag_id IN (
        SELECT tag_id FROM smart_baggage_tags WHERE flight_id = p_flight_id
    ) AND event_type = 'delivered';

    -- Create reconciliation record
    INSERT INTO baggage_reconciliation (
        flight_id, reconciliation_type, expected_items, actual_items,
        missing_items, extra_items, status
    ) VALUES (
        p_flight_id, p_reconciliation_type, expected_count, actual_count,
        GREATEST(expected_count - actual_count, 0),
        GREATEST(actual_count - expected_count, 0),
        CASE
            WHEN expected_count = actual_count THEN 'completed'
            ELSE 'issues_found'
        END
    ) RETURNING reconciliation_id INTO reconciliation_id;

    RETURN reconciliation_id;
END;
$$ LANGUAGE plpgsql;
