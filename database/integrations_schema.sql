-- Flight Control Database Schema - Integrations Module
-- External system integrations and data sources

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

-- WebSocket Connection Monitoring
CREATE TABLE websocket_connections (
    id SERIAL PRIMARY KEY,
    client_id VARCHAR(50) UNIQUE NOT NULL,
    user_id INTEGER REFERENCES users(id),
    ip_address INET,
    user_agent TEXT,
    authenticated BOOLEAN DEFAULT FALSE,
    authenticated_at TIMESTAMP,
    connected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    disconnected_at TIMESTAMP,
    duration_seconds INTEGER,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Indexes for integrations tables
CREATE INDEX idx_aircraft_positions_location ON aircraft_positions (latitude, longitude);
CREATE INDEX idx_aircraft_positions_icao24 ON aircraft_positions (icao24);
CREATE INDEX idx_aircraft_positions_time ON aircraft_positions (recorded_at);

CREATE INDEX idx_satellite_messages_aircraft ON satellite_messages (aircraft_id);
CREATE INDEX idx_satellite_messages_time ON satellite_messages (received_at);
CREATE INDEX idx_satellite_commands_status ON satellite_commands (status);

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

CREATE INDEX idx_flight_search_cache_key ON flight_search_cache (search_key);
CREATE INDEX idx_flight_search_cache_route ON flight_search_cache (origin, destination, departure_date);
CREATE INDEX idx_airline_bookings_reference ON airline_bookings (booking_reference);
CREATE INDEX idx_airline_bookings_status ON airline_bookings (status);

CREATE INDEX idx_data_fusion_timestamp ON data_fusion_reports (report_timestamp);
CREATE INDEX idx_data_fusion_status ON data_fusion_reports (system_status);

CREATE INDEX idx_stream_topics_name ON stream_topics (topic_name);
CREATE INDEX idx_stream_consumer_groups_group ON stream_consumer_groups (group_id);
CREATE INDEX idx_stream_processing_jobs_status ON stream_processing_jobs (status);

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

-- Spatial indexes
CREATE INDEX idx_airports_location ON airports USING GIST (location);
CREATE INDEX idx_airports_location_spgist ON airports USING SPGIST (location);
CREATE INDEX idx_airspace_sectors_boundary ON airspace_sectors USING GIST (boundary);
CREATE INDEX idx_runways_centerline ON runways USING GIST (centerline);
CREATE INDEX idx_navaids_location ON navaids USING GIST (location);
CREATE INDEX idx_weather_cells_geometry ON weather_cells USING GIST (geometry);
CREATE INDEX idx_restricted_areas_boundary ON restricted_areas USING GIST (boundary);
CREATE INDEX idx_flight_paths_geometry ON flight_paths USING GIST (path_geometry);

-- WebSocket connection indexes
CREATE INDEX idx_websocket_connections_client ON websocket_connections (client_id);
CREATE INDEX idx_websocket_connections_user ON websocket_connections (user_id);
CREATE INDEX idx_websocket_connections_connected ON websocket_connections (connected_at);
CREATE INDEX idx_websocket_connections_disconnected ON websocket_connections (disconnected_at);

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

-- Spatial functions
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
