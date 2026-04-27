-- Flight Control Database Schema

-- Users and Authentication
CREATE TABLE roles (
    id SERIAL PRIMARY KEY,
    name VARCHAR(50) UNIQUE NOT NULL,
    description TEXT
);

CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role_id INTEGER REFERENCES roles(id),
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Modules Configuration
CREATE TABLE modules (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    is_enabled BOOLEAN DEFAULT FALSE
);

CREATE TABLE role_permissions (
    id SERIAL PRIMARY KEY,
    role_id INTEGER REFERENCES roles(id),
    module_id INTEGER REFERENCES modules(id),
    permission VARCHAR(50) NOT NULL
);

CREATE TABLE user_permissions (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id),
    module_id INTEGER REFERENCES modules(id),
    permission VARCHAR(50) NOT NULL -- e.g., 'read', 'write', 'admin'
);

-- Flights
CREATE TABLE airlines (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(10) UNIQUE NOT NULL,
    country VARCHAR(100)
);

CREATE TABLE aircraft (
    id SERIAL PRIMARY KEY,
    model VARCHAR(100) NOT NULL,
    registration VARCHAR(20) UNIQUE NOT NULL,
    capacity INTEGER NOT NULL
);

CREATE TABLE flights (
    id SERIAL PRIMARY KEY,
    flight_number VARCHAR(20) UNIQUE NOT NULL,
    airline_id INTEGER REFERENCES airlines(id),
    aircraft_id INTEGER REFERENCES aircraft(id),
    origin VARCHAR(10) NOT NULL,
    destination VARCHAR(10) NOT NULL,
    scheduled_departure TIMESTAMP NOT NULL,
    scheduled_arrival TIMESTAMP NOT NULL,
    actual_departure TIMESTAMP,
    actual_arrival TIMESTAMP,
    status VARCHAR(50) DEFAULT 'scheduled', -- scheduled, boarding, departed, arrived, delayed, cancelled
    gate VARCHAR(10),
    terminal VARCHAR(10),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Passengers and Bookings
CREATE TABLE passengers (
    id SERIAL PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(150),
    phone VARCHAR(20),
    passport_number VARCHAR(20),
    nationality VARCHAR(100),
    date_of_birth DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE bookings (
    id SERIAL PRIMARY KEY,
    passenger_id INTEGER REFERENCES passengers(id),
    flight_id INTEGER REFERENCES flights(id),
    seat_number VARCHAR(10),
    booking_reference VARCHAR(20) UNIQUE NOT NULL,
    status VARCHAR(50) DEFAULT 'confirmed', -- confirmed, cancelled, checked-in
    total_amount DECIMAL(10,2),
    currency VARCHAR(3) DEFAULT 'USD',
    payment_status VARCHAR(50) DEFAULT 'pending', -- pending, paid, refunded
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Baggage
CREATE TABLE baggage (
    id SERIAL PRIMARY KEY,
    booking_id INTEGER REFERENCES bookings(id),
    tag_number VARCHAR(20) UNIQUE NOT NULL,
    weight DECIMAL(5,2),
    status VARCHAR(50) DEFAULT 'checked', -- checked, loaded, unloaded, claimed, lost
    location VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Security and Check-in
CREATE TABLE check_ins (
    id SERIAL PRIMARY KEY,
    booking_id INTEGER REFERENCES bookings(id),
    check_in_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    boarding_pass_issued BOOLEAN DEFAULT FALSE,
    security_cleared BOOLEAN DEFAULT FALSE
);

-- Ground Operations
CREATE TABLE maintenance_schedules (
    id SERIAL PRIMARY KEY,
    aircraft_id INTEGER REFERENCES aircraft(id),
    maintenance_type VARCHAR(100),
    scheduled_date DATE,
    completed BOOLEAN DEFAULT FALSE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE crew_assignments (
    id SERIAL PRIMARY KEY,
    flight_id INTEGER REFERENCES flights(id),
    user_id INTEGER REFERENCES users(id),
    role VARCHAR(50), -- pilot, co-pilot, flight attendant
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ATC Tower
CREATE TABLE runways (
    id SERIAL PRIMARY KEY,
    runway_number VARCHAR(10) UNIQUE NOT NULL,
    length INTEGER,
    status VARCHAR(50) DEFAULT 'available' -- available, occupied, closed
);

CREATE TABLE clearances (
    id SERIAL PRIMARY KEY,
    flight_id INTEGER REFERENCES flights(id),
    clearance_type VARCHAR(50), -- takeoff, landing
    issued_by INTEGER REFERENCES users(id),
    issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT
);

CREATE TABLE communications (
    id SERIAL PRIMARY KEY,
    flight_id INTEGER REFERENCES flights(id),
    user_id INTEGER REFERENCES users(id),
    message TEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE weather_data (
    id SERIAL PRIMARY KEY,
    location VARCHAR(100),
    temperature DECIMAL(5,2),
    wind_speed DECIMAL(5,2),
    visibility DECIMAL(5,2),
    conditions TEXT,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE emergencies (
    id SERIAL PRIMARY KEY,
    flight_id INTEGER REFERENCES flights(id),
    type VARCHAR(100),
    description TEXT,
    reported_by INTEGER REFERENCES users(id),
    reported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved BOOLEAN DEFAULT FALSE
);

-- ADS-B Aircraft Tracking Data
CREATE TABLE aircraft_positions (
    id SERIAL PRIMARY KEY,
    icao24 VARCHAR(6) NOT NULL,
    callsign VARCHAR(8),
    origin_country VARCHAR(100),
    time_position INTEGER,
    last_contact INTEGER,
    longitude DECIMAL(10,6),
    latitude DECIMAL(10,6),
    baro_altitude DECIMAL(7,1),
    on_ground BOOLEAN,
    velocity DECIMAL(5,1),
    true_track DECIMAL(5,1),
    vertical_rate DECIMAL(5,1),
    geo_altitude DECIMAL(7,1),
    squawk VARCHAR(4),
    spi BOOLEAN,
    position_source INTEGER,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(icao24, recorded_at)
);

-- Indexes for performance
CREATE INDEX idx_flights_status ON flights(status);
CREATE INDEX idx_flights_scheduled_departure ON flights(scheduled_departure);
CREATE INDEX idx_bookings_flight_id ON bookings(flight_id);
CREATE INDEX idx_baggage_booking_id ON baggage(booking_id);
CREATE INDEX idx_aircraft_positions_location ON aircraft_positions (latitude, longitude);
CREATE INDEX idx_aircraft_positions_icao24 ON aircraft_positions (icao24);
CREATE INDEX idx_aircraft_positions_time ON aircraft_positions (recorded_at);

-- Satellite Communications
CREATE TABLE satellite_messages (
    id SERIAL PRIMARY KEY,
    aircraft_id VARCHAR(10) NOT NULL,
    satellite_type VARCHAR(20) NOT NULL, -- starlink, iridium
    message_type VARCHAR(50) NOT NULL,
    message_data JSONB,
    received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    signal_strength INTEGER,
    frequency DECIMAL(10,2),
    processed BOOLEAN DEFAULT FALSE
);

CREATE TABLE satellite_commands (
    id SERIAL PRIMARY KEY,
    aircraft_id VARCHAR(10) NOT NULL,
    command_type VARCHAR(50) NOT NULL,
    parameters JSONB,
    status VARCHAR(20) DEFAULT 'pending', -- pending, sent, acknowledged
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sent_at TIMESTAMP,
    acknowledged_at TIMESTAMP
);

CREATE TABLE maintenance_reports (
    id SERIAL PRIMARY KEY,
    aircraft_registration VARCHAR(20),
    report_type VARCHAR(50),
    description TEXT,
    severity VARCHAR(20) DEFAULT 'low', -- low, medium, high, critical
    reported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    satellite_transmission BOOLEAN DEFAULT FALSE,
    resolved BOOLEAN DEFAULT FALSE,
    resolved_at TIMESTAMP
);

CREATE INDEX idx_satellite_messages_aircraft ON satellite_messages (aircraft_id);
CREATE INDEX idx_satellite_messages_time ON satellite_messages (received_at);
CREATE INDEX idx_satellite_commands_status ON satellite_commands (status);

-- AI Conflict Prediction
CREATE TABLE conflict_predictions (
    id SERIAL PRIMARY KEY,
    aircraft1 VARCHAR(10) NOT NULL,
    aircraft2 VARCHAR(10) NOT NULL,
    time_to_conflict INTEGER NOT NULL,
    min_horizontal_sep DECIMAL(6,2),
    min_vertical_sep DECIMAL(7,1),
    severity DECIMAL(5,2),
    confidence DECIMAL(5,2),
    resolved BOOLEAN DEFAULT FALSE,
    predicted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE conflict_history (
    id SERIAL PRIMARY KEY,
    aircraft1 VARCHAR(10) NOT NULL,
    aircraft2 VARCHAR(10) NOT NULL,
    actual_conflict_time TIMESTAMP,
    min_horizontal_sep DECIMAL(6,2),
    min_vertical_sep DECIMAL(7,1),
    resolution_method TEXT,
    detected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_conflict_predictions_time ON conflict_predictions (predicted_at);
CREATE INDEX idx_conflict_predictions_severity ON conflict_predictions (severity DESC);

-- Performance Analytics
CREATE TABLE performance_reports (
    id SERIAL PRIMARY KEY,
    report_type VARCHAR(50) NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    kpi_data JSONB,
    trends JSONB,
    recommendations JSONB,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_performance_reports_type ON performance_reports (report_type);
CREATE INDEX idx_performance_reports_period ON performance_reports (period_start, period_end);

-- Flight Plan & Clearance Data
CREATE TABLE flight_plans (
    id SERIAL PRIMARY KEY,
    flight_id INTEGER REFERENCES flights(id),
    aircraft_id VARCHAR(10) NOT NULL,
    departure_airport VARCHAR(4) NOT NULL,
    arrival_airport VARCHAR(4) NOT NULL,
    departure_time TIMESTAMP NOT NULL,
    arrival_time TIMESTAMP NOT NULL,
    route TEXT,
    altitude_profile TEXT,
    speed_profile TEXT,
    fuel_requirements DECIMAL(8,2),
    alternate_airports TEXT,
    pilot_in_command VARCHAR(100),
    filed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(20) DEFAULT 'filed', -- filed, active, closed
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE clearances (
    id SERIAL PRIMARY KEY,
    flight_plan_id INTEGER REFERENCES flight_plans(id),
    clearance_number VARCHAR(20) UNIQUE NOT NULL,
    clearance_type VARCHAR(50) NOT NULL, -- takeoff, landing, enroute, oceanic
    issued_by VARCHAR(100),
    issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    valid_from TIMESTAMP NOT NULL,
    valid_to TIMESTAMP,
    clearance_text TEXT NOT NULL,
    restrictions TEXT,
    frequency_assignments TEXT,
    squawk_code VARCHAR(4),
    runway_assignment VARCHAR(10),
    heading_assignments TEXT,
    altitude_assignments TEXT,
    speed_assignments TEXT,
    acknowledged BOOLEAN DEFAULT FALSE,
    acknowledged_at TIMESTAMP
);

CREATE TABLE cpdlc_messages (
    id SERIAL PRIMARY KEY,
    flight_plan_id INTEGER REFERENCES flight_plans(id),
    message_id VARCHAR(20) UNIQUE NOT NULL,
    direction VARCHAR(10) NOT NULL, -- uplink, downlink
    message_type VARCHAR(50) NOT NULL,
    message_content TEXT NOT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    received_at TIMESTAMP,
    acknowledged BOOLEAN DEFAULT FALSE,
    response_required BOOLEAN DEFAULT FALSE,
    response_message_id VARCHAR(20)
);

CREATE TABLE acars_messages (
    id SERIAL PRIMARY KEY,
    flight_plan_id INTEGER REFERENCES flight_plans(id),
    message_id VARCHAR(20) UNIQUE NOT NULL,
    message_type VARCHAR(50) NOT NULL,
    origin VARCHAR(10),
    destination VARCHAR(10),
    message_text TEXT NOT NULL,
    received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed BOOLEAN DEFAULT FALSE,
    priority VARCHAR(10) DEFAULT 'normal'
);

-- Weather Radar Integration
CREATE TABLE weather_radar_data (
    id SERIAL PRIMARY KEY,
    radar_station VARCHAR(10) NOT NULL,
    radar_type VARCHAR(20) NOT NULL, -- nexrad, tdwr, satellite
    latitude DECIMAL(10,6) NOT NULL,
    longitude DECIMAL(10,6) NOT NULL,
    altitude INTEGER,
    reflectivity DECIMAL(5,2),
    velocity DECIMAL(5,2),
    spectrum_width DECIMAL(5,2),
    precipitation_type VARCHAR(20),
    intensity VARCHAR(20),
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE weather_alerts (
    id SERIAL PRIMARY KEY,
    alert_type VARCHAR(50) NOT NULL,
    severity VARCHAR(20) NOT NULL, -- minor, moderate, severe, extreme
    location_lat DECIMAL(10,6),
    location_lon DECIMAL(10,6),
    radius_km DECIMAL(6,2),
    description TEXT,
    start_time TIMESTAMP,
    end_time TIMESTAMP,
    issued_by VARCHAR(100),
    issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    active BOOLEAN DEFAULT TRUE
);

CREATE TABLE metar_reports (
    id SERIAL PRIMARY KEY,
    station_id VARCHAR(4) NOT NULL,
    observation_time TIMESTAMP NOT NULL,
    wind_direction INTEGER,
    wind_speed INTEGER,
    wind_gust INTEGER,
    visibility DECIMAL(5,1),
    temperature DECIMAL(5,1),
    dewpoint DECIMAL(5,1),
    altimeter_setting DECIMAL(5,2),
    weather_conditions TEXT,
    sky_conditions TEXT,
    remarks TEXT,
    raw_text TEXT,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE taf_reports (
    id SERIAL PRIMARY KEY,
    station_id VARCHAR(4) NOT NULL,
    issue_time TIMESTAMP NOT NULL,
    valid_from TIMESTAMP NOT NULL,
    valid_to TIMESTAMP NOT NULL,
    forecast_text TEXT,
    raw_text TEXT,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- NOTAM & Airspace Data
CREATE TABLE notams (
    id SERIAL PRIMARY KEY,
    notam_id VARCHAR(20) UNIQUE NOT NULL,
    notam_type VARCHAR(10) NOT NULL, -- NOTAM, SNOWTAM, ASHTAM
    series VARCHAR(5) NOT NULL,
    number INTEGER NOT NULL,
    year INTEGER NOT NULL,
    fir_code VARCHAR(4),
    notam_code VARCHAR(10),
    traffic_type VARCHAR(20),
    purpose VARCHAR(20),
    scope VARCHAR(20),
    lower_limit INTEGER,
    upper_limit INTEGER,
    latitude DECIMAL(10,6),
    longitude DECIMAL(10,6),
    radius INTEGER,
    affected_area TEXT,
    item_a TEXT,
    item_b TEXT,
    item_c TEXT,
    item_d TEXT,
    item_e TEXT,
    item_f TEXT,
    item_g TEXT,
    start_time TIMESTAMP,
    end_time TIMESTAMP,
    estimated_end_time TIMESTAMP,
    permanent BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE airspace_restrictions (
    id SERIAL PRIMARY KEY,
    restriction_id VARCHAR(20) UNIQUE NOT NULL,
    restriction_type VARCHAR(50) NOT NULL, -- tfr, sua, adiz
    name VARCHAR(100),
    lower_altitude INTEGER,
    upper_altitude INTEGER,
    geometry_type VARCHAR(20), -- circle, polygon, corridor
    geometry_coordinates TEXT,
    effective_from TIMESTAMP,
    effective_to TIMESTAMP,
    reason TEXT,
    controlling_agency VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    active BOOLEAN DEFAULT TRUE
);

CREATE TABLE drone_operations (
    id SERIAL PRIMARY KEY,
    operation_id VARCHAR(20) UNIQUE NOT NULL,
    operator_name VARCHAR(100),
    operator_contact VARCHAR(100),
    drone_type VARCHAR(50),
    max_altitude INTEGER,
    operation_area TEXT,
    start_time TIMESTAMP,
    end_time TIMESTAMP,
    emergency_contact VARCHAR(100),
    approved BOOLEAN DEFAULT FALSE,
    approved_by VARCHAR(100),
    approved_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Indexes for new tables
CREATE INDEX idx_flight_plans_flight_id ON flight_plans (flight_id);
CREATE INDEX idx_flight_plans_status ON flight_plans (status);
CREATE INDEX idx_clearances_flight_plan_id ON clearances (flight_plan_id);
CREATE INDEX idx_clearances_valid_from ON clearances (valid_from);
CREATE INDEX idx_cpdlc_messages_flight_plan_id ON cpdlc_messages (flight_plan_id);
CREATE INDEX idx_acars_messages_flight_plan_id ON acars_messages (flight_plan_id);
CREATE INDEX idx_weather_radar_location ON weather_radar_data (latitude, longitude);
CREATE INDEX idx_weather_radar_time ON weather_radar_data (recorded_at);
CREATE INDEX idx_weather_alerts_location ON weather_alerts (location_lat, location_lon);
CREATE INDEX idx_weather_alerts_active ON weather_alerts (active);
CREATE INDEX idx_metar_reports_station ON metar_reports (station_id);
CREATE INDEX idx_metar_reports_time ON metar_reports (observation_time);
CREATE INDEX idx_taf_reports_station ON taf_reports (station_id);
CREATE INDEX idx_notams_location ON notams (latitude, longitude);
CREATE INDEX idx_notams_active ON notams (start_time, end_time);
CREATE INDEX idx_airspace_restrictions_active ON airspace_restrictions (active);
CREATE INDEX idx_drone_operations_area ON drone_operations USING GIST (ST_GeomFromText(operation_area));

-- Airline API Integration
CREATE TABLE flight_search_cache (
    id SERIAL PRIMARY KEY,
    search_key VARCHAR(32) UNIQUE NOT NULL,
    origin VARCHAR(10) NOT NULL,
    destination VARCHAR(10) NOT NULL,
    departure_date DATE NOT NULL,
    return_date DATE,
    passengers INTEGER NOT NULL,
    results JSONB,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE airline_bookings (
    id SERIAL PRIMARY KEY,
    booking_reference VARCHAR(50) UNIQUE NOT NULL,
    provider VARCHAR(20) NOT NULL, -- amadeus, sabre
    flight_data JSONB,
    passenger_data JSONB,
    api_response JSONB,
    status VARCHAR(20) DEFAULT 'pending', -- pending, confirmed, cancelled
    total_amount DECIMAL(10,2),
    currency VARCHAR(3) DEFAULT 'USD',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_flight_search_cache_key ON flight_search_cache (search_key);
CREATE INDEX idx_flight_search_cache_route ON flight_search_cache (origin, destination, departure_date);
CREATE INDEX idx_airline_bookings_reference ON airline_bookings (booking_reference);
CREATE INDEX idx_airline_bookings_status ON airline_bookings (status);

-- Data Fusion Engine
CREATE TABLE data_fusion_reports (
    id SERIAL PRIMARY KEY,
    report_timestamp TIMESTAMP NOT NULL,
    aircraft_count INTEGER NOT NULL DEFAULT 0,
    conflicts_count INTEGER NOT NULL DEFAULT 0,
    weather_summary JSONB,
    aircraft_summary JSONB,
    conflicts_data JSONB,
    system_status VARCHAR(20) DEFAULT 'operational',
    processing_time_seconds DECIMAL(5,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_data_fusion_timestamp ON data_fusion_reports (report_timestamp);
CREATE INDEX idx_data_fusion_status ON data_fusion_reports (system_status);

-- Compliance and Audit System
CREATE TABLE audit_logs (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id),
    action VARCHAR(100) NOT NULL,
    resource_type VARCHAR(100) NOT NULL,
    resource_id VARCHAR(100),
    details JSONB,
    ip_address INET,
    user_agent TEXT,
    session_id VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE compliance_reports (
    id SERIAL PRIMARY KEY,
    report_type VARCHAR(100) NOT NULL,
    report_data JSONB,
    generated_by INTEGER REFERENCES users(id),
    period_start DATE,
    period_end DATE,
    status VARCHAR(50) DEFAULT 'generated',
    file_path VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE data_retention_policies (
    id SERIAL PRIMARY KEY,
    data_type VARCHAR(100) NOT NULL,
    retention_period_days INTEGER NOT NULL,
    archival_required BOOLEAN DEFAULT FALSE,
    encryption_required BOOLEAN DEFAULT TRUE,
    deletion_method VARCHAR(50) DEFAULT 'hard_delete',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE data_deletion_logs (
    id SERIAL PRIMARY KEY,
    data_type VARCHAR(100) NOT NULL,
    record_count INTEGER NOT NULL,
    deletion_method VARCHAR(50) NOT NULL,
    reason VARCHAR(255),
    executed_by INTEGER REFERENCES users(id),
    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_audit_logs_user ON audit_logs (user_id);
CREATE INDEX idx_audit_logs_action ON audit_logs (action);
CREATE INDEX idx_audit_logs_resource ON audit_logs (resource_type, resource_id);
CREATE INDEX idx_audit_logs_timestamp ON audit_logs (created_at);
CREATE INDEX idx_compliance_reports_type ON compliance_reports (report_type);
CREATE INDEX idx_data_deletion_logs_type ON data_deletion_logs (data_type);

-- Stream Processing Pipeline
CREATE TABLE stream_topics (
    id SERIAL PRIMARY KEY,
    topic_name VARCHAR(100) UNIQUE NOT NULL,
    partitions INTEGER NOT NULL DEFAULT 1,
    replication_factor INTEGER NOT NULL DEFAULT 1,
    retention_hours INTEGER NOT NULL DEFAULT 168,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE stream_messages (
    id SERIAL PRIMARY KEY,
    topic_name VARCHAR(100) NOT NULL,
    partition_id INTEGER NOT NULL DEFAULT 0,
    message_key VARCHAR(255),
    message_data JSONB,
    message_offset BIGINT NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_stream_messages_topic (topic_name, partition_id, message_offset)
);

CREATE TABLE stream_consumer_groups (
    id SERIAL PRIMARY KEY,
    group_id VARCHAR(100) NOT NULL,
    topic_name VARCHAR(100) NOT NULL,
    partition_id INTEGER NOT NULL,
    consumer_id VARCHAR(100) NOT NULL,
    last_offset BIGINT NOT NULL DEFAULT 0,
    last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(group_id, topic_name, partition_id)
);

CREATE TABLE stream_processing_metrics (
    id SERIAL PRIMARY KEY,
    metric_name VARCHAR(100) NOT NULL,
    metric_value DECIMAL(15,2),
    labels JSONB,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_stream_metrics_name (metric_name, timestamp)
);

CREATE TABLE stream_processing_jobs (
    id SERIAL PRIMARY KEY,
    job_name VARCHAR(100) UNIQUE NOT NULL,
    job_type VARCHAR(50) NOT NULL, -- producer, consumer, processor
    status VARCHAR(20) DEFAULT 'stopped', -- running, stopped, error
    config JSONB,
    last_run TIMESTAMP,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE stream_data_quality (
    id SERIAL PRIMARY KEY,
    data_source VARCHAR(50) NOT NULL,
    total_records BIGINT NOT NULL DEFAULT 0,
    valid_records BIGINT NOT NULL DEFAULT 0,
    invalid_records BIGINT NOT NULL DEFAULT 0,
    processing_time_avg DECIMAL(8,2),
    error_rate DECIMAL(5,2),
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_stream_quality_source (data_source)
);

CREATE INDEX idx_stream_topics_name ON stream_topics (topic_name);
CREATE INDEX idx_stream_consumer_groups_group ON stream_consumer_groups (group_id);
CREATE INDEX idx_stream_processing_jobs_status ON stream_processing_jobs (status);

-- Time-Series Database for Positional Data
CREATE TABLE aircraft_positions_ts (
    time TIMESTAMPTZ NOT NULL,
    icao24 VARCHAR(6),
    callsign VARCHAR(8),
    latitude DECIMAL(10,6),
    longitude DECIMAL(10,6),
    altitude DECIMAL(7,1),
    speed DECIMAL(5,1),
    heading DECIMAL(5,1),
    vertical_rate DECIMAL(5,1),
    on_ground BOOLEAN,
    data_source VARCHAR(20),
    quality_score DECIMAL(3,2),
    metadata JSONB,
    created_at TIMESTAMPTZ DEFAULT NOW()
) PARTITION BY RANGE (time);

CREATE TABLE radar_tracks_ts (
    time TIMESTAMPTZ NOT NULL,
    latitude DECIMAL(10,6),
    longitude DECIMAL(10,6),
    altitude DECIMAL(7,1),
    reflectivity DECIMAL(5,2),
    velocity DECIMAL(5,2),
    spectrum_width DECIMAL(5,2),
    precipitation_type VARCHAR(20),
    intensity VARCHAR(20),
    radar_station VARCHAR(10),
    data_source VARCHAR(20),
    quality_score DECIMAL(3,2),
    metadata JSONB,
    created_at TIMESTAMPTZ DEFAULT NOW()
) PARTITION BY RANGE (time);

CREATE TABLE satellite_positions_ts (
    time TIMESTAMPTZ NOT NULL,
    aircraft_id VARCHAR(10),
    latitude DECIMAL(10,6),
    longitude DECIMAL(10,6),
    altitude DECIMAL(7,1),
    speed DECIMAL(5,1),
    heading DECIMAL(5,1),
    satellite_type VARCHAR(20),
    signal_strength INTEGER,
    data_source VARCHAR(20),
    quality_score DECIMAL(3,2),
    metadata JSONB,
    created_at TIMESTAMPTZ DEFAULT NOW()
) PARTITION BY RANGE (time);

CREATE TABLE weather_data_ts (
    time TIMESTAMPTZ NOT NULL,
    latitude DECIMAL(10,6),
    longitude DECIMAL(10,6),
    temperature DECIMAL(5,2),
    wind_speed DECIMAL(5,2),
    wind_direction INTEGER,
    visibility DECIMAL(5,2),
    precipitation DECIMAL(5,2),
    pressure DECIMAL(6,2),
    humidity DECIMAL(5,2),
    weather_conditions TEXT,
    station_id VARCHAR(10),
    data_source VARCHAR(20),
    quality_score DECIMAL(3,2),
    metadata JSONB,
    created_at TIMESTAMPTZ DEFAULT NOW()
) PARTITION BY RANGE (time);

CREATE TABLE flight_trajectories_ts (
    time TIMESTAMPTZ NOT NULL,
    flight_id INTEGER REFERENCES flights(id),
    icao24 VARCHAR(6),
    callsign VARCHAR(8),
    latitude DECIMAL(10,6),
    longitude DECIMAL(10,6),
    altitude DECIMAL(7,1),
    speed DECIMAL(5,1),
    heading DECIMAL(5,1),
    vertical_rate DECIMAL(5,1),
    phase VARCHAR(20), -- takeoff, climb, cruise, descent, approach, landing
    data_source VARCHAR(20),
    quality_score DECIMAL(3,2),
    metadata JSONB,
    created_at TIMESTAMPTZ DEFAULT NOW()
) PARTITION BY RANGE (time);

-- Create initial partitions for current and next few months
-- Aircraft positions partitions
CREATE TABLE aircraft_positions_ts_2024_09 PARTITION OF aircraft_positions_ts
    FOR VALUES FROM ('2024-09-01 00:00:00+00') TO ('2024-10-01 00:00:00+00');
CREATE TABLE aircraft_positions_ts_2024_10 PARTITION OF aircraft_positions_ts
    FOR VALUES FROM ('2024-10-01 00:00:00+00') TO ('2024-11-01 00:00:00+00');
CREATE TABLE aircraft_positions_ts_2024_11 PARTITION OF aircraft_positions_ts
    FOR VALUES FROM ('2024-11-01 00:00:00+00') TO ('2024-12-01 00:00:00+00');
CREATE TABLE aircraft_positions_ts_2024_12 PARTITION OF aircraft_positions_ts
    FOR VALUES FROM ('2024-12-01 00:00:00+00') TO ('2025-01-01 00:00:00+00');
CREATE TABLE aircraft_positions_ts_2025_01 PARTITION OF aircraft_positions_ts
    FOR VALUES FROM ('2025-01-01 00:00:00+00') TO ('2025-02-01 00:00:00+00');
CREATE TABLE aircraft_positions_ts_2025_02 PARTITION OF aircraft_positions_ts
    FOR VALUES FROM ('2025-02-01 00:00:00+00') TO ('2025-03-01 00:00:00+00');

-- Radar tracks partitions
CREATE TABLE radar_tracks_ts_2024_09 PARTITION OF radar_tracks_ts
    FOR VALUES FROM ('2024-09-01 00:00:00+00') TO ('2024-10-01 00:00:00+00');
CREATE TABLE radar_tracks_ts_2024_10 PARTITION OF radar_tracks_ts
    FOR VALUES FROM ('2024-10-01 00:00:00+00') TO ('2024-11-01 00:00:00+00');
CREATE TABLE radar_tracks_ts_2024_11 PARTITION OF radar_tracks_ts
    FOR VALUES FROM ('2024-11-01 00:00:00+00') TO ('2024-12-01 00:00:00+00');

-- Satellite positions partitions
CREATE TABLE satellite_positions_ts_2024_09 PARTITION OF satellite_positions_ts
    FOR VALUES FROM ('2024-09-01 00:00:00+00') TO ('2024-10-01 00:00:00+00');
CREATE TABLE satellite_positions_ts_2024_10 PARTITION OF satellite_positions_ts
    FOR VALUES FROM ('2024-10-01 00:00:00+00') TO ('2024-11-01 00:00:00+00');
CREATE TABLE satellite_positions_ts_2024_11 PARTITION OF satellite_positions_ts
    FOR VALUES FROM ('2024-11-01 00:00:00+00') TO ('2024-12-01 00:00:00+00');
CREATE TABLE satellite_positions_ts_2024_12 PARTITION OF satellite_positions_ts
    FOR VALUES FROM ('2024-12-01 00:00:00+00') TO ('2025-01-01 00:00:00+00');
CREATE TABLE satellite_positions_ts_2025_01 PARTITION OF satellite_positions_ts
    FOR VALUES FROM ('2025-01-01 00:00:00+00') TO ('2025-02-01 00:00:00+00');
CREATE TABLE satellite_positions_ts_2025_02 PARTITION OF satellite_positions_ts
    FOR VALUES FROM ('2025-02-01 00:00:00+00') TO ('2025-03-01 00:00:00+00');

-- Weather data partitions
CREATE TABLE weather_data_ts_2024_09 PARTITION OF weather_data_ts
    FOR VALUES FROM ('2024-09-01 00:00:00+00') TO ('2024-10-01 00:00:00+00');
CREATE TABLE weather_data_ts_2024_10 PARTITION OF weather_data_ts
    FOR VALUES FROM ('2024-10-01 00:00:00+00') TO ('2024-11-01 00:00:00+00');
CREATE TABLE weather_data_ts_2024_11 PARTITION OF weather_data_ts
    FOR VALUES FROM ('2024-11-01 00:00:00+00') TO ('2024-12-01 00:00:00+00');
CREATE TABLE weather_data_ts_2024_12 PARTITION OF weather_data_ts
    FOR VALUES FROM ('2024-12-01 00:00:00+00') TO ('2025-01-01 00:00:00+00');
CREATE TABLE weather_data_ts_2025_01 PARTITION OF weather_data_ts
    FOR VALUES FROM ('2025-01-01 00:00:00+00') TO ('2025-02-01 00:00:00+00');
CREATE TABLE weather_data_ts_2025_02 PARTITION OF weather_data_ts
    FOR VALUES FROM ('2025-02-01 00:00:00+00') TO ('2025-03-01 00:00:00+00');

-- Flight trajectories partitions
CREATE TABLE flight_trajectories_ts_2024_09 PARTITION OF flight_trajectories_ts
    FOR VALUES FROM ('2024-09-01 00:00:00+00') TO ('2024-10-01 00:00:00+00');
CREATE TABLE flight_trajectories_ts_2024_10 PARTITION OF flight_trajectories_ts
    FOR VALUES FROM ('2024-10-01 00:00:00+00') TO ('2024-11-01 00:00:00+00');
CREATE TABLE flight_trajectories_ts_2024_11 PARTITION OF flight_trajectories_ts
    FOR VALUES FROM ('2024-11-01 00:00:00+00') TO ('2024-12-01 00:00:00+00');
CREATE TABLE flight_trajectories_ts_2024_12 PARTITION OF flight_trajectories_ts
    FOR VALUES FROM ('2024-12-01 00:00:00+00') TO ('2025-01-01 00:00:00+00');
CREATE TABLE flight_trajectories_ts_2025_01 PARTITION OF flight_trajectories_ts
    FOR VALUES FROM ('2025-01-01 00:00:00+00') TO ('2025-02-01 00:00:00+00');
CREATE TABLE flight_trajectories_ts_2025_02 PARTITION OF flight_trajectories_ts
    FOR VALUES FROM ('2025-02-01 00:00:00+00') TO ('2025-03-01 00:00:00+00');

-- Time-series indexes
CREATE INDEX idx_aircraft_positions_ts_time ON aircraft_positions_ts (time DESC);
CREATE INDEX idx_aircraft_positions_ts_icao24_time ON aircraft_positions_ts (icao24, time DESC);
CREATE INDEX idx_aircraft_positions_ts_location ON aircraft_positions_ts USING gist (point(longitude, latitude));
CREATE INDEX idx_aircraft_positions_ts_callsign ON aircraft_positions_ts (callsign);
CREATE INDEX idx_aircraft_positions_ts_altitude ON aircraft_positions_ts (altitude);
CREATE INDEX idx_aircraft_positions_ts_source ON aircraft_positions_ts (data_source);

CREATE INDEX idx_radar_tracks_ts_time ON radar_tracks_ts (time DESC);
CREATE INDEX idx_radar_tracks_ts_location ON radar_tracks_ts USING gist (point(longitude, latitude));
CREATE INDEX idx_radar_tracks_ts_station ON radar_tracks_ts (radar_station);

CREATE INDEX idx_satellite_positions_ts_time ON satellite_positions_ts (time DESC);
CREATE INDEX idx_satellite_positions_ts_aircraft_time ON satellite_positions_ts (aircraft_id, time DESC);
CREATE INDEX idx_satellite_positions_ts_location ON satellite_positions_ts USING gist (point(longitude, latitude));

CREATE INDEX idx_weather_data_ts_time ON weather_data_ts (time DESC);
CREATE INDEX idx_weather_data_ts_location ON weather_data_ts USING gist (point(longitude, latitude));
CREATE INDEX idx_weather_data_ts_station ON weather_data_ts (station_id);

CREATE INDEX idx_flight_trajectories_ts_time ON flight_trajectories_ts (time DESC);
CREATE INDEX idx_flight_trajectories_ts_flight_time ON flight_trajectories_ts (flight_id, time DESC);
CREATE INDEX idx_flight_trajectories_ts_icao24_time ON flight_trajectories_ts (icao24, time DESC);
CREATE INDEX idx_flight_trajectories_ts_phase ON flight_trajectories_ts (phase);

-- Downsampled aggregate views
CREATE OR REPLACE VIEW aircraft_positions_ts_5min_agg AS
SELECT
    date_trunc('hour', time) + INTERVAL '5 minute' * ROUND(EXTRACT(minute FROM time) / 5.0) as bucket,
    icao24,
    callsign,
    AVG(latitude) as avg_latitude,
    AVG(longitude) as avg_longitude,
    AVG(altitude) as avg_altitude,
    AVG(speed) as avg_speed,
    AVG(heading) as avg_heading,
    MIN(altitude) as min_altitude,
    MAX(altitude) as max_altitude,
    COUNT(*) as sample_count,
    data_source,
    AVG(quality_score) as avg_quality
FROM aircraft_positions_ts
WHERE time >= NOW() - INTERVAL '30 days'
GROUP BY bucket, icao24, callsign, data_source
ORDER BY bucket DESC;

CREATE OR REPLACE VIEW aircraft_positions_ts_1hour_agg AS
SELECT
    date_trunc('hour', time) as bucket,
    icao24,
    callsign,
    AVG(latitude) as avg_latitude,
    AVG(longitude) as avg_longitude,
    AVG(altitude) as avg_altitude,
    AVG(speed) as avg_speed,
    AVG(heading) as avg_heading,
    MIN(altitude) as min_altitude,
    MAX(altitude) as max_altitude,
    COUNT(*) as sample_count,
    data_source,
    AVG(quality_score) as avg_quality
FROM aircraft_positions_ts
WHERE time >= NOW() - INTERVAL '90 days'
GROUP BY bucket, icao24, callsign, data_source
ORDER BY bucket DESC;

CREATE OR REPLACE VIEW aircraft_positions_ts_1day_agg AS
SELECT
    date_trunc('day', time) as bucket,
    icao24,
    callsign,
    AVG(latitude) as avg_latitude,
    AVG(longitude) as avg_longitude,
    AVG(altitude) as avg_altitude,
    AVG(speed) as avg_speed,
    AVG(heading) as avg_heading,
    MIN(altitude) as min_altitude,
    MAX(altitude) as max_altitude,
    COUNT(*) as sample_count,
    data_source,
    AVG(quality_score) as avg_quality
FROM aircraft_positions_ts
WHERE time >= NOW() - INTERVAL '365 days'
GROUP BY bucket, icao24, callsign, data_source
ORDER BY bucket DESC;

-- Time-series metadata table
CREATE TABLE time_series_metadata (
    id SERIAL PRIMARY KEY,
    table_name VARCHAR(50) NOT NULL,
    metric_name VARCHAR(100) NOT NULL,
    metric_value DECIMAL(15,2),
    recorded_at TIMESTAMPTZ DEFAULT NOW(),
    INDEX idx_ts_metadata_table (table_name, recorded_at)
);

CREATE INDEX idx_time_series_metadata_table_metric ON time_series_metadata (table_name, metric_name, recorded_at);

-- Spatial Indexing and Geographic Queries
CREATE TABLE airports (
    id SERIAL PRIMARY KEY,
    icao_code VARCHAR(4) UNIQUE NOT NULL,
    iata_code VARCHAR(3),
    name VARCHAR(100) NOT NULL,
    city VARCHAR(100),
    country VARCHAR(100),
    elevation INTEGER,
    location GEOGRAPHY(POINT, 4326),
    runway_count INTEGER DEFAULT 0,
    type VARCHAR(20) DEFAULT 'airport',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE airspace_sectors (
    id SERIAL PRIMARY KEY,
    sector_id VARCHAR(20) UNIQUE NOT NULL,
    sector_name VARCHAR(100),
    sector_type VARCHAR(20), -- terminal, enroute, oceanic
    lower_limit INTEGER,
    upper_limit INTEGER,
    boundary GEOGRAPHY(POLYGON, 4326),
    controlling_agency VARCHAR(100),
    frequency DECIMAL(6,3),
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE runways (
    id SERIAL PRIMARY KEY,
    airport_id INTEGER REFERENCES airports(id),
    runway_number VARCHAR(10) NOT NULL,
    length INTEGER,
    width INTEGER,
    surface_type VARCHAR(20),
    centerline GEOGRAPHY(LINESTRING, 4326),
    threshold1 GEOGRAPHY(POINT, 4326),
    threshold2 GEOGRAPHY(POINT, 4326),
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE navaids (
    id SERIAL PRIMARY KEY,
    identifier VARCHAR(10) UNIQUE NOT NULL,
    name VARCHAR(100),
    type VARCHAR(20), -- VOR, DME, NDB, ILS
    frequency DECIMAL(8,3),
    location GEOGRAPHY(POINT, 4326),
    elevation INTEGER,
    range INTEGER,
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE weather_cells (
    id SERIAL PRIMARY KEY,
    cell_id VARCHAR(20) UNIQUE NOT NULL,
    cell_type VARCHAR(20), -- convective, turbulence, icing
    severity VARCHAR(10), -- light, moderate, severe
    geometry GEOGRAPHY(POLYGON, 4326),
    altitude_min INTEGER,
    altitude_max INTEGER,
    valid_from TIMESTAMP,
    valid_to TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE restricted_areas (
    id SERIAL PRIMARY KEY,
    area_id VARCHAR(20) UNIQUE NOT NULL,
    area_name VARCHAR(100),
    restriction_type VARCHAR(20), -- prohibited, restricted, danger
    lower_limit INTEGER,
    upper_limit INTEGER,
    boundary GEOGRAPHY(POLYGON, 4326),
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE flight_paths (
    id SERIAL PRIMARY KEY,
    flight_id INTEGER REFERENCES flights(id),
    icao24 VARCHAR(6),
    callsign VARCHAR(8),
    path_geometry GEOGRAPHY(LINESTRING, 4326),
    altitude_profile TEXT, -- JSON array of altitudes
    speed_profile TEXT, -- JSON array of speeds
    start_time TIMESTAMP,
    end_time TIMESTAMP,
    distance DECIMAL(8,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Spatial indexes
CREATE INDEX idx_airports_location ON airports USING GIST (location);
CREATE INDEX idx_airports_location_spgist ON airports USING SPGIST (location);
CREATE INDEX idx_airspace_sectors_boundary ON airspace_sectors USING GIST (boundary);
CREATE INDEX idx_runways_centerline ON runways USING GIST (centerline);
CREATE INDEX idx_navaids_location ON navaids USING GIST (location);
CREATE INDEX idx_weather_cells_geometry ON weather_cells USING GIST (geometry);
CREATE INDEX idx_restricted_areas_boundary ON restricted_areas USING GIST (boundary);
CREATE INDEX idx_flight_paths_geometry ON flight_paths USING GIST (path_geometry);

-- Add geometry column to aircraft positions if it doesn't exist
ALTER TABLE aircraft_positions_ts
ADD COLUMN IF NOT EXISTS position_geom GEOGRAPHY(POINT, 4326);

-- Create index for aircraft positions geometry
CREATE INDEX IF NOT EXISTS idx_aircraft_positions_geom ON aircraft_positions_ts USING GIST (position_geom);
CREATE INDEX IF NOT EXISTS idx_aircraft_positions_geom_brin ON aircraft_positions_ts USING BRIN (time);

-- Spatial functions
CREATE OR REPLACE FUNCTION update_aircraft_position_geom()
RETURNS TRIGGER AS $$
BEGIN
    NEW.position_geom = ST_SetSRID(ST_MakePoint(NEW.longitude, NEW.latitude), 4326)::GEOGRAPHY;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trigger_update_aircraft_position_geom ON aircraft_positions_ts;
CREATE TRIGGER trigger_update_aircraft_position_geom
    BEFORE INSERT OR UPDATE ON aircraft_positions_ts
    FOR EACH ROW EXECUTE FUNCTION update_aircraft_position_geom();

CREATE OR REPLACE FUNCTION calculate_distance(
    lat1 DECIMAL, lon1 DECIMAL, lat2 DECIMAL, lon2 DECIMAL
)
RETURNS DECIMAL AS $$
BEGIN
    RETURN ST_Distance(
        ST_SetSRID(ST_MakePoint(lon1, lat1), 4326)::GEOGRAPHY,
        ST_SetSRID(ST_MakePoint(lon2, lat2), 4326)::GEOGRAPHY
    ) / 1000; -- Convert to kilometers
END;
$$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION find_nearest_airport(
    lat DECIMAL, lon DECIMAL, max_distance INTEGER DEFAULT 500
)
RETURNS TABLE (
    airport_id INTEGER,
    icao_code VARCHAR,
    name VARCHAR,
    distance DECIMAL
) AS $$
BEGIN
    RETURN QUERY
    SELECT
        a.id,
        a.icao_code,
        a.name,
        ST_Distance(a.location, ST_SetSRID(ST_MakePoint(lon, lat), 4326)::GEOGRAPHY) / 1000 as distance
    FROM airports a
    WHERE ST_DWithin(
        a.location,
        ST_SetSRID(ST_MakePoint(lon, lat), 4326)::GEOGRAPHY,
        max_distance * 1000
    )
    ORDER BY distance ASC
    LIMIT 5;
END;
$$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION get_airspace_sector(
    lat DECIMAL, lon DECIMAL, altitude INTEGER
)
RETURNS TABLE (
    sector_id VARCHAR,
    sector_name VARCHAR,
    sector_type VARCHAR
) AS $$
BEGIN
    RETURN QUERY
    SELECT
        s.sector_id,
        s.sector_name,
        s.sector_type
    FROM airspace_sectors s
    WHERE ST_Contains(s.boundary, ST_SetSRID(ST_MakePoint(lon, lat), 4326)::GEOGRAPHY)
    AND altitude >= s.lower_limit
    AND altitude <= s.upper_limit
    AND s.active = TRUE;
END;
$$ LANGUAGE plpgsql;

-- Machine Learning for Route Optimization
CREATE TABLE ml_route_models (
    id SERIAL PRIMARY KEY,
    model_name VARCHAR(100) UNIQUE NOT NULL,
    model_type VARCHAR(50) NOT NULL, -- neural_network, reinforcement_learning, genetic_algorithm
    model_version VARCHAR(20) NOT NULL,
    model_data JSONB,
    training_accuracy DECIMAL(5,4),
    validation_accuracy DECIMAL(5,4),
    last_trained TIMESTAMP,
    is_active BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE ml_training_routes (
    id SERIAL PRIMARY KEY,
    origin_icao VARCHAR(4) NOT NULL,
    destination_icao VARCHAR(4) NOT NULL,
    aircraft_type VARCHAR(20),
    route_geometry GEOGRAPHY(LINESTRING, 4326),
    distance DECIMAL(8,2),
    flight_time INTEGER, -- seconds
    fuel_consumption DECIMAL(8,2),
    weather_conditions JSONB,
    traffic_density INTEGER,
    cost_score DECIMAL(8,2),
    safety_score DECIMAL(5,2),
    efficiency_score DECIMAL(5,2),
    actual_flight_time INTEGER,
    delay_minutes INTEGER,
    success BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE ml_route_optimizations (
    id SERIAL PRIMARY KEY,
    request_id VARCHAR(50) UNIQUE NOT NULL,
    origin_lat DECIMAL(10,6),
    origin_lon DECIMAL(10,6),
    destination_lat DECIMAL(10,6),
    destination_lon DECIMAL(10,6),
    aircraft_type VARCHAR(20),
    optimization_criteria JSONB, -- fuel, time, safety, cost
    constraints JSONB, -- altitude, speed, airspace restrictions
    original_route GEOGRAPHY(LINESTRING, 4326),
    optimized_route GEOGRAPHY(LINESTRING, 4326),
    waypoints JSONB,
    estimated_time INTEGER,
    estimated_fuel DECIMAL(8,2),
    confidence_score DECIMAL(5,2),
    model_used VARCHAR(100),
    processing_time DECIMAL(5,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE ml_route_features (
    id SERIAL PRIMARY KEY,
    route_id INTEGER REFERENCES ml_training_routes(id),
    feature_vector JSONB,
    target_value DECIMAL(8,2),
    feature_type VARCHAR(50), -- distance, time, fuel, safety
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE ml_model_performance (
    id SERIAL PRIMARY KEY,
    model_name VARCHAR(100) NOT NULL,
    metric_name VARCHAR(50) NOT NULL,
    metric_value DECIMAL(10,4),
    test_dataset_size INTEGER,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ml_performance_model (model_name, recorded_at)
);

-- Indexes for ML tables
CREATE INDEX idx_ml_route_models_name ON ml_route_models (model_name);
CREATE INDEX idx_ml_route_models_active ON ml_route_models (is_active);
CREATE INDEX idx_ml_training_routes_origin_dest ON ml_training_routes (origin_icao, destination_icao);
CREATE INDEX idx_ml_training_routes_aircraft ON ml_training_routes (aircraft_type);
CREATE INDEX idx_ml_route_optimizations_request ON ml_route_optimizations (request_id);
CREATE INDEX idx_ml_route_optimizations_model ON ml_route_optimizations (model_used);
CREATE INDEX idx_ml_route_features_type ON ml_route_features (feature_type);
CREATE INDEX idx_ml_model_performance_name ON ml_model_performance (model_name, recorded_at);

-- Predictive Conflict Detection
CREATE TABLE predicted_conflicts (
    id SERIAL PRIMARY KEY,
    conflict_id VARCHAR(50) UNIQUE NOT NULL,
    aircraft1_icao VARCHAR(6) NOT NULL,
    aircraft2_icao VARCHAR(6) NOT NULL,
    prediction_time TIMESTAMP NOT NULL,
    conflict_time TIMESTAMP NOT NULL,
    time_to_conflict INTEGER NOT NULL, -- seconds
    horizontal_separation DECIMAL(6,2), -- nautical miles
    vertical_separation DECIMAL(7,1), -- feet
    conflict_type VARCHAR(20), -- horizontal, vertical, both
    severity_level VARCHAR(10), -- low, medium, high, critical
    confidence_score DECIMAL(5,2),
    prediction_model VARCHAR(50),
    location_lat DECIMAL(10,6),
    location_lon DECIMAL(10,6),
    altitude DECIMAL(7,1),
    status VARCHAR(20) DEFAULT 'predicted', -- predicted, active, resolved, false_positive
    resolution_method TEXT,
    resolved_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE conflict_scenarios (
    id SERIAL PRIMARY KEY,
    scenario_id VARCHAR(50) UNIQUE NOT NULL,
    aircraft_count INTEGER NOT NULL,
    time_window_start TIMESTAMP NOT NULL,
    time_window_end TIMESTAMP NOT NULL,
    geographic_bounds JSONB,
    risk_level VARCHAR(10),
    potential_conflicts INTEGER,
    weather_impact DECIMAL(5,2),
    traffic_density DECIMAL(5,2),
    mitigation_actions JSONB,
    status VARCHAR(20) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE conflict_resolutions (
    id SERIAL PRIMARY KEY,
    conflict_id VARCHAR(50) REFERENCES predicted_conflicts(conflict_id),
    resolution_type VARCHAR(30), -- vector_change, altitude_change, speed_change, route_change
    resolution_details JSONB,
    effectiveness_score DECIMAL(5,2),
    implemented_by VARCHAR(100),
    implemented_at TIMESTAMP,
    outcome VARCHAR(20), -- successful, partial, failed
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE conflict_prediction_models (
    id SERIAL PRIMARY KEY,
    model_name VARCHAR(100) UNIQUE NOT NULL,
    model_type VARCHAR(50) NOT NULL, -- trajectory_analysis, ml_prediction, statistical
    model_version VARCHAR(20) NOT NULL,
    model_parameters JSONB,
    accuracy_score DECIMAL(5,4),
    precision_score DECIMAL(5,4),
    recall_score DECIMAL(5,4),
    last_trained TIMESTAMP,
    is_active BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE conflict_alerts (
    id SERIAL PRIMARY KEY,
    alert_id VARCHAR(50) UNIQUE NOT NULL,
    conflict_id VARCHAR(50) REFERENCES predicted_conflicts(conflict_id),
    alert_type VARCHAR(30), -- early_warning, imminent, critical
    alert_level VARCHAR(10), -- info, warning, danger
    message TEXT,
    affected_aircraft JSONB,
    recommended_actions JSONB,
    acknowledged BOOLEAN DEFAULT FALSE,
    acknowledged_by VARCHAR(100),
    acknowledged_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE conflict_statistics (
    id SERIAL PRIMARY KEY,
    time_period_start TIMESTAMP NOT NULL,
    time_period_end TIMESTAMP NOT NULL,
    total_predictions INTEGER DEFAULT 0,
    true_positives INTEGER DEFAULT 0,
    false_positives INTEGER DEFAULT 0,
    false_negatives INTEGER DEFAULT 0,
    average_time_to_conflict INTEGER,
    average_separation DECIMAL(6,2),
    resolution_success_rate DECIMAL(5,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Indexes for conflict detection tables
CREATE INDEX idx_predicted_conflicts_id ON predicted_conflicts (conflict_id);
CREATE INDEX idx_predicted_conflicts_time ON predicted_conflicts (prediction_time);
CREATE INDEX idx_predicted_conflicts_aircraft ON predicted_conflicts (aircraft1_icao, aircraft2_icao);
CREATE INDEX idx_predicted_conflicts_status ON predicted_conflicts (status);
CREATE INDEX idx_predicted_conflicts_severity ON predicted_conflicts (severity_level);

CREATE INDEX idx_conflict_scenarios_id ON conflict_scenarios (scenario_id);
CREATE INDEX idx_conflict_scenarios_risk ON conflict_scenarios (risk_level);
CREATE INDEX idx_conflict_scenarios_status ON conflict_scenarios (status);

CREATE INDEX idx_conflict_resolutions_conflict ON conflict_resolutions (conflict_id);
CREATE INDEX idx_conflict_resolutions_type ON conflict_resolutions (resolution_type);

CREATE INDEX idx_conflict_prediction_models_name ON conflict_prediction_models (model_name);
CREATE INDEX idx_conflict_prediction_models_active ON conflict_prediction_models (is_active);

CREATE INDEX idx_conflict_alerts_id ON conflict_alerts (alert_id);
CREATE INDEX idx_conflict_alerts_conflict ON conflict_alerts (conflict_id);
CREATE INDEX idx_conflict_alerts_level ON conflict_alerts (alert_level);
CREATE INDEX idx_conflict_alerts_acknowledged ON conflict_alerts (acknowledged);

CREATE INDEX idx_conflict_statistics_period ON conflict_statistics (time_period_start, time_period_end);

-- Automated Decision Support Systems
CREATE TABLE decision_scenarios (
    id SERIAL PRIMARY KEY,
    scenario_id VARCHAR(50) UNIQUE NOT NULL,
    scenario_type VARCHAR(50) NOT NULL, -- conflict_resolution, traffic_management, emergency_response
    priority_level VARCHAR(10), -- low, medium, high, critical
    complexity_score DECIMAL(5,2),
    time_pressure DECIMAL(5,2),
    affected_aircraft JSONB,
    environmental_factors JSONB,
    operational_constraints JSONB,
    status VARCHAR(20) DEFAULT 'active', -- active, resolved, escalated
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE decision_recommendations (
    id SERIAL PRIMARY KEY,
    recommendation_id VARCHAR(50) UNIQUE NOT NULL,
    scenario_id VARCHAR(50) REFERENCES decision_scenarios(scenario_id),
    recommendation_type VARCHAR(50), -- vector_change, altitude_change, speed_change, route_change, hold_pattern
    confidence_score DECIMAL(5,2),
    expected_outcome JSONB,
    alternative_options JSONB,
    implementation_steps JSONB,
    risk_assessment JSONB,
    time_to_implement INTEGER, -- seconds
    priority_score DECIMAL(5,2),
    status VARCHAR(20) DEFAULT 'pending', -- pending, accepted, rejected, implemented
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE decision_outcomes (
    id SERIAL PRIMARY KEY,
    decision_id VARCHAR(50) REFERENCES decision_recommendations(recommendation_id),
    actual_outcome JSONB,
    outcome_quality DECIMAL(5,2),
    controller_feedback TEXT,
    system_feedback TEXT,
    lessons_learned TEXT,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE decision_rules (
    id SERIAL PRIMARY KEY,
    rule_id VARCHAR(50) UNIQUE NOT NULL,
    rule_name VARCHAR(100) NOT NULL,
    rule_type VARCHAR(50), -- safety, efficiency, capacity, environmental
    conditions JSONB,
    actions JSONB,
    priority INTEGER,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE decision_model_performance (
    id SERIAL PRIMARY KEY,
    model_name VARCHAR(100) NOT NULL,
    scenario_type VARCHAR(50) NOT NULL,
    accuracy DECIMAL(5,4),
    precision DECIMAL(5,4),
    recall DECIMAL(5,4),
    decision_quality DECIMAL(5,2),
    response_time_avg DECIMAL(8,2),
    evaluated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE automated_actions (
    id SERIAL PRIMARY KEY,
    action_id VARCHAR(50) UNIQUE NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    trigger_scenario VARCHAR(50),
    affected_entities JSONB,
    action_parameters JSONB,
    execution_status VARCHAR(20), -- pending, executing, completed, failed
    execution_result JSONB,
    executed_by VARCHAR(50), -- system or controller_id
    executed_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE decision_alerts (
    id SERIAL PRIMARY KEY,
    alert_id VARCHAR(50) UNIQUE NOT NULL,
    alert_type VARCHAR(30), -- recommendation, warning, critical_decision
    alert_level VARCHAR(10), -- info, warning, danger
    title VARCHAR(200),
    message TEXT,
    recommended_actions JSONB,
    affected_parties JSONB,
    time_sensitivity VARCHAR(20), -- immediate, urgent, normal
    acknowledged BOOLEAN DEFAULT FALSE,
    acknowledged_by VARCHAR(100),
    acknowledged_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Indexes for decision support tables
CREATE INDEX idx_decision_scenarios_id ON decision_scenarios (scenario_id);
CREATE INDEX idx_decision_scenarios_type ON decision_scenarios (scenario_type);
CREATE INDEX idx_decision_scenarios_status ON decision_scenarios (status);
CREATE INDEX idx_decision_scenarios_priority ON decision_scenarios (priority_level);

CREATE INDEX idx_decision_recommendations_id ON decision_recommendations (recommendation_id);
CREATE INDEX idx_decision_recommendations_scenario ON decision_recommendations (scenario_id);
CREATE INDEX idx_decision_recommendations_type ON decision_recommendations (recommendation_type);
CREATE INDEX idx_decision_recommendations_status ON decision_recommendations (status);
CREATE INDEX idx_decision_recommendations_priority ON decision_recommendations (priority_score DESC);

CREATE INDEX idx_decision_outcomes_decision ON decision_outcomes (decision_id);
CREATE INDEX idx_decision_outcomes_quality ON decision_outcomes (outcome_quality);

CREATE INDEX idx_decision_rules_id ON decision_rules (rule_id);
CREATE INDEX idx_decision_rules_type ON decision_rules (rule_type);
CREATE INDEX idx_decision_rules_active ON decision_rules (is_active);

CREATE INDEX idx_decision_model_performance_name ON decision_model_performance (model_name);
CREATE INDEX idx_decision_model_performance_scenario ON decision_model_performance (scenario_type);

CREATE INDEX idx_automated_actions_id ON automated_actions (action_id);
CREATE INDEX idx_automated_actions_type ON automated_actions (action_type);
CREATE INDEX idx_automated_actions_status ON automated_actions (execution_status);

CREATE INDEX idx_decision_alerts_id ON decision_alerts (alert_id);
CREATE INDEX idx_decision_alerts_type ON decision_alerts (alert_type);
CREATE INDEX idx_decision_alerts_level ON decision_alerts (alert_level);
CREATE INDEX idx_decision_alerts_acknowledged ON decision_alerts (acknowledged);

-- GDPR Compliance Features
CREATE TABLE data_subject_consents (
    id SERIAL PRIMARY KEY,
    consent_id VARCHAR(50) UNIQUE NOT NULL,
    data_subject_id VARCHAR(100) NOT NULL, -- user_id, passenger_id, or external identifier
    data_subject_type VARCHAR(20) NOT NULL, -- user, passenger, employee
    consent_type VARCHAR(50) NOT NULL, -- marketing, analytics, profiling, etc.
    consent_given BOOLEAN NOT NULL,
    consent_date TIMESTAMP,
    consent_expiry TIMESTAMP,
    consent_withdrawn BOOLEAN DEFAULT FALSE,
    withdrawal_date TIMESTAMP,
    consent_version VARCHAR(20),
    legal_basis VARCHAR(100), -- consent, legitimate_interest, contract, etc.
    consent_scope TEXT,
    ip_address INET,
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE data_processing_activities (
    id SERIAL PRIMARY KEY,
    activity_id VARCHAR(50) UNIQUE NOT NULL,
    activity_name VARCHAR(200) NOT NULL,
    activity_description TEXT,
    legal_basis VARCHAR(100) NOT NULL,
    purpose VARCHAR(200) NOT NULL,
    data_categories JSONB, -- personal_data, sensitive_data, etc.
    data_subjects JSONB, -- customers, employees, etc.
    recipients JSONB, -- internal, external, third_parties
    retention_period VARCHAR(50),
    automated_decision_making BOOLEAN DEFAULT FALSE,
    international_transfer BOOLEAN DEFAULT FALSE,
    transfer_countries JSONB,
    dpo_approval_required BOOLEAN DEFAULT FALSE,
    dpo_approved BOOLEAN DEFAULT FALSE,
    dpo_approval_date TIMESTAMP,
    risk_assessment JSONB,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE data_subject_rights_requests (
    id SERIAL PRIMARY KEY,
    request_id VARCHAR(50) UNIQUE NOT NULL,
    data_subject_id VARCHAR(100) NOT NULL,
    data_subject_type VARCHAR(20) NOT NULL,
    request_type VARCHAR(50) NOT NULL, -- access, rectification, erasure, restriction, portability, objection
    request_details TEXT,
    request_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(30) DEFAULT 'pending', -- pending, in_progress, completed, rejected
    completion_deadline TIMESTAMP,
    completed_date TIMESTAMP,
    response_provided TEXT,
    verification_method VARCHAR(50), -- identity_document, email_verification, etc.
    verification_status VARCHAR(20) DEFAULT 'pending',
    appeal_requested BOOLEAN DEFAULT FALSE,
    appeal_details TEXT,
    appeal_date TIMESTAMP,
    appeal_status VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE data_breach_notifications (
    id SERIAL PRIMARY KEY,
    breach_id VARCHAR(50) UNIQUE NOT NULL,
    breach_date TIMESTAMP NOT NULL,
    discovery_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    breach_description TEXT NOT NULL,
    data_categories_affected JSONB,
    number_of_subjects_affected INTEGER,
    potential_consequences TEXT,
    measures_taken TEXT,
    supervisory_authority_notified BOOLEAN DEFAULT FALSE,
    notification_date TIMESTAMP,
    notification_reference VARCHAR(100),
    data_subjects_notified BOOLEAN DEFAULT FALSE,
    subjects_notification_date TIMESTAMP,
    risk_assessment JSONB,
    dpo_notified BOOLEAN DEFAULT FALSE,
    dpo_notification_date TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE privacy_impact_assessments (
    id SERIAL PRIMARY KEY,
    assessment_id VARCHAR(50) UNIQUE NOT NULL,
    project_name VARCHAR(200) NOT NULL,
    assessment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_protection_officer VARCHAR(100),
    processing_activities JSONB,
    data_flows JSONB,
    risks_identified JSONB,
    mitigation_measures JSONB,
    residual_risks JSONB,
    recommendations TEXT,
    approval_status VARCHAR(20) DEFAULT 'pending', -- pending, approved, rejected
    approval_date TIMESTAMP,
    review_date TIMESTAMP,
    next_review_date TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE data_retention_schedules (
    id SERIAL PRIMARY KEY,
    schedule_id VARCHAR(50) UNIQUE NOT NULL,
    data_category VARCHAR(100) NOT NULL,
    retention_purpose VARCHAR(200),
    retention_period VARCHAR(50) NOT NULL,
    retention_basis VARCHAR(100),
    disposal_method VARCHAR(50),
    review_frequency VARCHAR(20),
    last_review_date TIMESTAMP,
    next_review_date TIMESTAMP,
    legal_exceptions TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE cookie_consent_preferences (
    id SERIAL PRIMARY KEY,
    preference_id VARCHAR(50) UNIQUE NOT NULL,
    user_id VARCHAR(100),
    session_id VARCHAR(255),
    ip_address INET,
    user_agent TEXT,
    necessary_cookies BOOLEAN DEFAULT TRUE,
    analytics_cookies BOOLEAN DEFAULT FALSE,
    marketing_cookies BOOLEAN DEFAULT FALSE,
    functional_cookies BOOLEAN DEFAULT FALSE,
    preferences_cookies BOOLEAN DEFAULT FALSE,
    consent_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    consent_expiry TIMESTAMP,
    consent_withdrawn BOOLEAN DEFAULT FALSE,
    withdrawal_date TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE data_anonymization_logs (
    id SERIAL PRIMARY KEY,
    log_id VARCHAR(50) UNIQUE NOT NULL,
    data_subject_id VARCHAR(100),
    data_category VARCHAR(100),
    anonymization_method VARCHAR(50), -- pseudonymization, aggregation, masking, etc.
    anonymization_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    anonymization_reason VARCHAR(200),
    original_data_hash VARCHAR(128),
    anonymized_data_hash VARCHAR(128),
    reversibility BOOLEAN DEFAULT FALSE,
    retention_period VARCHAR(50),
    disposal_date TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE gdpr_audit_logs (
    id SERIAL PRIMARY KEY,
    audit_id VARCHAR(50) UNIQUE NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    data_subject_id VARCHAR(100),
    user_id VARCHAR(100),
    action_details JSONB,
    ip_address INET,
    user_agent TEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    compliance_status VARCHAR(20) DEFAULT 'compliant'
);

-- Indexes for GDPR tables
CREATE INDEX idx_data_subject_consents_subject ON data_subject_consents (data_subject_id, data_subject_type);
CREATE INDEX idx_data_subject_consents_type ON data_subject_consents (consent_type);
CREATE INDEX idx_data_subject_consents_withdrawn ON data_subject_consents (consent_withdrawn);

CREATE INDEX idx_data_processing_activities_id ON data_processing_activities (activity_id);
CREATE INDEX idx_data_processing_activities_basis ON data_processing_activities (legal_basis);

CREATE INDEX idx_data_subject_rights_requests_subject ON data_subject_rights_requests (data_subject_id, data_subject_type);
CREATE INDEX idx_data_subject_rights_requests_type ON data_subject_rights_requests (request_type);
CREATE INDEX idx_data_subject_rights_requests_status ON data_subject_rights_requests (status);
CREATE INDEX idx_data_subject_rights_requests_deadline ON data_subject_rights_requests (completion_deadline);

CREATE INDEX idx_data_breach_notifications_id ON data_breach_notifications (breach_id);
CREATE INDEX idx_data_breach_notifications_date ON data_breach_notifications (breach_date);

CREATE INDEX idx_privacy_impact_assessments_id ON privacy_impact_assessments (assessment_id);
CREATE INDEX idx_privacy_impact_assessments_status ON privacy_impact_assessments (approval_status);

CREATE INDEX idx_data_retention_schedules_id ON data_retention_schedules (schedule_id);
CREATE INDEX idx_data_retention_schedules_category ON data_retention_schedules (data_category);

CREATE INDEX idx_cookie_consent_preferences_user ON cookie_consent_preferences (user_id);
CREATE INDEX idx_cookie_consent_preferences_session ON cookie_consent_preferences (session_id);

CREATE INDEX idx_data_anonymization_logs_subject ON data_anonymization_logs (data_subject_id);
CREATE INDEX idx_data_anonymization_logs_category ON data_anonymization_logs (data_category);

CREATE INDEX idx_gdpr_audit_logs_action ON gdpr_audit_logs (action_type);
CREATE INDEX idx_gdpr_audit_logs_subject ON gdpr_audit_logs (data_subject_id);
CREATE INDEX idx_gdpr_audit_logs_timestamp ON gdpr_audit_logs (timestamp);

-- Data Retention Policies
CREATE TABLE data_archival_logs (
    id SERIAL PRIMARY KEY,
    archival_id VARCHAR(50) UNIQUE NOT NULL,
    data_category VARCHAR(100) NOT NULL,
    record_count INTEGER NOT NULL,
    archival_method VARCHAR(50), -- compression, encryption, offsite
    storage_location VARCHAR(200),
    archival_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    retention_period VARCHAR(50),
    disposal_date TIMESTAMP,
    checksum VARCHAR(128),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE data_disposal_logs (
    id SERIAL PRIMARY KEY,
    disposal_id VARCHAR(50) UNIQUE NOT NULL,
    data_category VARCHAR(100) NOT NULL,
    record_count INTEGER NOT NULL,
    disposal_method VARCHAR(50), -- secure_deletion, shredding, degaussing
    disposal_reason VARCHAR(200),
    disposal_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    disposed_by VARCHAR(100),
    verification_method VARCHAR(50),
    compliance_reference VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE retention_policy_executions (
    id SERIAL PRIMARY KEY,
    execution_id VARCHAR(50) UNIQUE NOT NULL,
    policy_id VARCHAR(50) NOT NULL,
    execution_type VARCHAR(30), -- archival, deletion, review
    records_processed INTEGER NOT NULL DEFAULT 0,
    execution_status VARCHAR(20), -- pending, running, completed, failed
    started_at TIMESTAMP,
    completed_at TIMESTAMP,
    error_message TEXT,
    next_execution_date TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE data_retention_exceptions (
    id SERIAL PRIMARY KEY,
    exception_id VARCHAR(50) UNIQUE NOT NULL,
    data_subject_id VARCHAR(100),
    data_category VARCHAR(100) NOT NULL,
    exception_type VARCHAR(50), -- legal_hold, regulatory_requirement, business_need
    exception_reason TEXT,
    exception_duration VARCHAR(50),
    approved_by VARCHAR(100),
    approval_date TIMESTAMP,
    expiry_date TIMESTAMP,
    status VARCHAR(20) DEFAULT 'active', -- active, expired, revoked
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE data_lifecycle_events (
    id SERIAL PRIMARY KEY,
    event_id VARCHAR(50) UNIQUE NOT NULL,
    data_subject_id VARCHAR(100),
    data_category VARCHAR(100) NOT NULL,
    event_type VARCHAR(30), -- created, accessed, modified, archived, deleted
    event_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    user_id VARCHAR(100),
    ip_address INET,
    user_agent TEXT,
    event_details JSONB,
    compliance_status VARCHAR(20) DEFAULT 'compliant'
);

CREATE TABLE storage_optimization_metrics (
    id SERIAL PRIMARY KEY,
    metric_id VARCHAR(50) UNIQUE NOT NULL,
    data_category VARCHAR(100) NOT NULL,
    total_records BIGINT NOT NULL DEFAULT 0,
    active_records BIGINT NOT NULL DEFAULT 0,
    archived_records BIGINT NOT NULL DEFAULT 0,
    deleted_records BIGINT NOT NULL DEFAULT 0,
    storage_size_bytes BIGINT NOT NULL DEFAULT 0,
    compression_ratio DECIMAL(5,2),
    last_optimization_date TIMESTAMP,
    next_optimization_date TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Indexes for data retention tables
CREATE INDEX idx_data_archival_logs_category ON data_archival_logs (data_category);
CREATE INDEX idx_data_archival_logs_date ON data_archival_logs (archival_date);
CREATE INDEX idx_data_disposal_logs_category ON data_disposal_logs (data_category);
CREATE INDEX idx_data_disposal_logs_date ON data_disposal_logs (disposal_date);
CREATE INDEX idx_retention_policy_executions_policy ON retention_policy_executions (policy_id);
CREATE INDEX idx_retention_policy_executions_status ON retention_policy_executions (execution_status);
CREATE INDEX idx_data_retention_exceptions_subject ON data_retention_exceptions (data_subject_id);
CREATE INDEX idx_data_retention_exceptions_category ON data_retention_exceptions (data_category);
CREATE INDEX idx_data_retention_exceptions_status ON data_retention_exceptions (status);
CREATE INDEX idx_data_lifecycle_events_subject ON data_lifecycle_events (data_subject_id);
CREATE INDEX idx_data_lifecycle_events_category ON data_lifecycle_events (data_category);
CREATE INDEX idx_data_lifecycle_events_type ON data_lifecycle_events (event_type);
CREATE INDEX idx_data_lifecycle_events_timestamp ON data_lifecycle_events (event_timestamp);
CREATE INDEX idx_storage_optimization_metrics_category ON storage_optimization_metrics (data_category);
