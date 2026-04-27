-- Infrastructure Management Module Schema
-- Manages building systems, IoT sensors, utilities, and facility monitoring

-- Building systems
CREATE TABLE IF NOT EXISTS building_systems (
    system_id VARCHAR(50) PRIMARY KEY,
    system_name VARCHAR(255) NOT NULL,
    system_type VARCHAR(50) NOT NULL, -- 'hvac', 'electrical', 'plumbing', 'fire_safety', 'security', 'elevator', 'escalator', 'lighting'
    building_name VARCHAR(100),
    floor_number VARCHAR(20),
    room_number VARCHAR(20),
    location_coordinates JSONB,
    manufacturer VARCHAR(100),
    model VARCHAR(100),
    serial_number VARCHAR(100),
    installation_date DATE,
    warranty_expiration DATE,
    operational_status VARCHAR(20) DEFAULT 'operational', -- 'operational', 'maintenance', 'out_of_service', 'failed'
    maintenance_schedule JSONB DEFAULT '{}',
    last_maintenance TIMESTAMP,
    next_maintenance TIMESTAMP,
    maintenance_interval_months INTEGER DEFAULT 12,
    energy_rating VARCHAR(10),
    capacity VARCHAR(100),
    specifications JSONB DEFAULT '{}',
    connected_systems JSONB DEFAULT '[]',
    emergency_backup BOOLEAN DEFAULT FALSE,
    critical_system BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- IoT sensors
CREATE TABLE IF NOT EXISTS iot_sensors (
    sensor_id VARCHAR(50) PRIMARY KEY,
    sensor_name VARCHAR(255) NOT NULL,
    sensor_type VARCHAR(50) NOT NULL, -- 'temperature', 'humidity', 'motion', 'smoke', 'co2', 'air_quality', 'vibration', 'pressure', 'current', 'voltage'
    location VARCHAR(255),
    location_coordinates JSONB,
    building_system_id VARCHAR(50) REFERENCES building_systems(system_id),
    manufacturer VARCHAR(100),
    model VARCHAR(100),
    serial_number VARCHAR(100),
    installation_date DATE,
    battery_level DECIMAL(5,2),
    signal_strength DECIMAL(5,2),
    firmware_version VARCHAR(20),
    calibration_date TIMESTAMP,
    calibration_interval_months INTEGER DEFAULT 12,
    measurement_unit VARCHAR(20),
    normal_range_min DECIMAL(10,4),
    normal_range_max DECIMAL(10,4),
    alert_threshold_min DECIMAL(10,4),
    alert_threshold_max DECIMAL(10,4),
    sampling_interval_seconds INTEGER DEFAULT 300,
    operational_status VARCHAR(20) DEFAULT 'active', -- 'active', 'maintenance', 'failed', 'offline'
    last_reading TIMESTAMP,
    last_reading_value DECIMAL(10,4),
    data_retention_days INTEGER DEFAULT 365,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Sensor readings
CREATE TABLE IF NOT EXISTS sensor_readings (
    reading_id SERIAL PRIMARY KEY,
    sensor_id VARCHAR(50) REFERENCES iot_sensors(sensor_id),
    reading_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reading_value DECIMAL(10,4),
    reading_unit VARCHAR(20),
    reading_quality VARCHAR(20) DEFAULT 'good', -- 'good', 'fair', 'poor', 'invalid'
    environmental_conditions JSONB DEFAULT '{}',
    calibration_applied BOOLEAN DEFAULT FALSE,
    alert_triggered BOOLEAN DEFAULT FALSE,
    alert_type VARCHAR(50), -- 'threshold_exceeded', 'sensor_failure', 'communication_error'
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Utilities monitoring
CREATE TABLE IF NOT EXISTS utilities_monitoring (
    monitoring_id SERIAL PRIMARY KEY,
    utility_type VARCHAR(30) NOT NULL, -- 'electricity', 'water', 'gas', 'steam', 'compressed_air', 'sewage'
    meter_id VARCHAR(50),
    meter_name VARCHAR(255),
    location VARCHAR(255),
    building_name VARCHAR(100),
    floor_number VARCHAR(20),
    reading_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    consumption_value DECIMAL(12,4),
    consumption_unit VARCHAR(20),
    cost_per_unit DECIMAL(8,4),
    total_cost DECIMAL(10,2),
    peak_demand DECIMAL(12,4),
    peak_demand_time TIMESTAMP,
    meter_accuracy DECIMAL(5,2),
    billing_period_start DATE,
    billing_period_end DATE,
    utility_provider VARCHAR(100),
    contract_number VARCHAR(50),
    service_level_agreement JSONB DEFAULT '{}',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Energy management
CREATE TABLE IF NOT EXISTS energy_management (
    energy_id SERIAL PRIMARY KEY,
    date DATE,
    building_name VARCHAR(100),
    total_energy_consumption DECIMAL(12,2),
    energy_consumption_unit VARCHAR(20) DEFAULT 'kWh',
    peak_demand DECIMAL(10,2),
    peak_demand_time TIME,
    energy_cost DECIMAL(10,2),
    energy_cost_currency VARCHAR(3) DEFAULT 'USD',
    renewable_energy_percentage DECIMAL(5,2),
    carbon_emissions DECIMAL(10,2),
    carbon_emissions_unit VARCHAR(10) DEFAULT 'kgCO2',
    energy_efficiency_rating DECIMAL(5,2),
    baseline_consumption DECIMAL(12,2),
    energy_savings DECIMAL(10,2),
    energy_savings_percentage DECIMAL(5,2),
    optimization_recommendations JSONB DEFAULT '[]',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Facility zones
CREATE TABLE IF NOT EXISTS facility_zones (
    zone_id VARCHAR(50) PRIMARY KEY,
    zone_name VARCHAR(255) NOT NULL,
    zone_type VARCHAR(50) NOT NULL, -- 'terminal', 'cargo_area', 'maintenance_hangar', 'office_building', 'parking', 'runway', 'taxiway'
    building_name VARCHAR(100),
    floor_number VARCHAR(20),
    area_sqm DECIMAL(10,2),
    occupancy_capacity INTEGER,
    current_occupancy INTEGER DEFAULT 0,
    temperature_controlled BOOLEAN DEFAULT FALSE,
    humidity_controlled BOOLEAN DEFAULT FALSE,
    security_level VARCHAR(20) DEFAULT 'standard',
    access_restrictions JSONB DEFAULT '[]',
    emergency_exits JSONB DEFAULT '[]',
    fire_suppression_system VARCHAR(50),
    lighting_system VARCHAR(50),
    hvac_zone VARCHAR(50),
    utilities_meters JSONB DEFAULT '[]',
    iot_sensors JSONB DEFAULT '[]',
    operational_status VARCHAR(20) DEFAULT 'active', -- 'active', 'maintenance', 'closed', 'under_construction'
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Maintenance work orders
CREATE TABLE IF NOT EXISTS maintenance_work_orders (
    work_order_id VARCHAR(50) PRIMARY KEY,
    work_order_number VARCHAR(100) UNIQUE NOT NULL,
    work_order_type VARCHAR(30) NOT NULL, -- 'preventive', 'corrective', 'predictive', 'emergency'
    priority_level VARCHAR(20) DEFAULT 'medium', -- 'low', 'medium', 'high', 'critical'
    system_id VARCHAR(50) REFERENCES building_systems(system_id),
    zone_id VARCHAR(50) REFERENCES facility_zones(zone_id),
    sensor_id VARCHAR(50) REFERENCES iot_sensors(sensor_id),
    description TEXT,
    reported_by VARCHAR(50),
    reported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    assigned_to VARCHAR(50),
    assigned_at TIMESTAMP,
    scheduled_start TIMESTAMP,
    scheduled_end TIMESTAMP,
    actual_start TIMESTAMP,
    actual_end TIMESTAMP,
    estimated_duration_hours DECIMAL(6,2),
    actual_duration_hours DECIMAL(6,2),
    work_order_status VARCHAR(30) DEFAULT 'open', -- 'open', 'assigned', 'in_progress', 'completed', 'cancelled', 'on_hold'
    parts_required JSONB DEFAULT '[]',
    parts_used JSONB DEFAULT '[]',
    labor_cost DECIMAL(8,2),
    parts_cost DECIMAL(8,2),
    total_cost DECIMAL(8,2),
    contractor_name VARCHAR(100),
    contractor_contact JSONB,
    safety_requirements TEXT,
    completion_notes TEXT,
    quality_check_passed BOOLEAN,
    quality_check_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Facility inspections
CREATE TABLE IF NOT EXISTS facility_inspections (
    inspection_id VARCHAR(50) PRIMARY KEY,
    inspection_number VARCHAR(100) UNIQUE NOT NULL,
    inspection_type VARCHAR(30) NOT NULL, -- 'routine', 'safety', 'compliance', 'damage', 'pre_season', 'post_event'
    zone_id VARCHAR(50) REFERENCES facility_zones(zone_id),
    system_id VARCHAR(50) REFERENCES building_systems(system_id),
    inspection_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    inspector_name VARCHAR(50),
    inspector_certification VARCHAR(100),
    inspection_criteria JSONB DEFAULT '[]',
    findings JSONB DEFAULT '[]',
    critical_issues INTEGER DEFAULT 0,
    major_issues INTEGER DEFAULT 0,
    minor_issues INTEGER DEFAULT 0,
    recommendations JSONB DEFAULT '[]',
    compliance_status VARCHAR(20) DEFAULT 'compliant', -- 'compliant', 'non_compliant', 'conditional'
    follow_up_required BOOLEAN DEFAULT FALSE,
    follow_up_date TIMESTAMP,
    corrective_actions JSONB DEFAULT '[]',
    inspection_duration_minutes INTEGER,
    weather_conditions JSONB DEFAULT '{}',
    photos_references JSONB DEFAULT '[]',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Asset management
CREATE TABLE IF NOT EXISTS asset_management (
    asset_id VARCHAR(50) PRIMARY KEY,
    asset_name VARCHAR(255) NOT NULL,
    asset_type VARCHAR(50) NOT NULL, -- 'equipment', 'furniture', 'vehicle', 'tool', 'fixture', 'appliance'
    category VARCHAR(50),
    subcategory VARCHAR(50),
    manufacturer VARCHAR(100),
    model VARCHAR(100),
    serial_number VARCHAR(100),
    purchase_date DATE,
    purchase_cost DECIMAL(10,2),
    purchase_currency VARCHAR(3) DEFAULT 'USD',
    supplier_name VARCHAR(100),
    warranty_period_months INTEGER,
    warranty_expiration DATE,
    location VARCHAR(255),
    zone_id VARCHAR(50) REFERENCES facility_zones(zone_id),
    assigned_to VARCHAR(50),
    operational_status VARCHAR(20) DEFAULT 'active', -- 'active', 'maintenance', 'retired', 'lost', 'stolen'
    condition_rating DECIMAL(3,1), -- 1.0 to 5.0 scale
    last_condition_assessment TIMESTAMP,
    maintenance_schedule JSONB DEFAULT '{}',
    last_maintenance TIMESTAMP,
    next_maintenance TIMESTAMP,
    depreciation_method VARCHAR(30),
    current_value DECIMAL(10,2),
    disposal_date TIMESTAMP,
    disposal_value DECIMAL(10,2),
    disposal_reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Space utilization
CREATE TABLE IF NOT EXISTS space_utilization (
    utilization_id SERIAL PRIMARY KEY,
    zone_id VARCHAR(50) REFERENCES facility_zones(zone_id),
    measurement_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    total_area_sqm DECIMAL(10,2),
    occupied_area_sqm DECIMAL(10,2),
    available_area_sqm DECIMAL(10,2),
    utilization_percentage DECIMAL(5,2),
    peak_occupancy_time TIME,
    average_occupancy DECIMAL(5,2),
    occupancy_trend VARCHAR(10), -- 'increasing', 'decreasing', 'stable'
    seasonal_variation JSONB DEFAULT '{}',
    optimization_recommendations JSONB DEFAULT '[]',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Environmental monitoring
CREATE TABLE IF NOT EXISTS environmental_monitoring (
    monitoring_id SERIAL PRIMARY KEY,
    zone_id VARCHAR(50) REFERENCES facility_zones(zone_id),
    monitoring_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    temperature_celsius DECIMAL(5,2),
    humidity_percentage DECIMAL(5,2),
    co2_ppm INTEGER,
    voc_ppm DECIMAL(6,2),
    particulate_matter_ugm3 DECIMAL(6,2),
    noise_level_db DECIMAL(5,2),
    light_level_lux INTEGER,
    air_pressure_hpa DECIMAL(6,2),
    air_quality_index INTEGER,
    comfort_index DECIMAL(3,1),
    environmental_compliance VARCHAR(20) DEFAULT 'compliant', -- 'compliant', 'warning', 'non_compliant'
    recommendations JSONB DEFAULT '[]',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Facility access control
CREATE TABLE IF NOT EXISTS facility_access_control (
    access_id SERIAL PRIMARY KEY,
    zone_id VARCHAR(50) REFERENCES facility_zones(zone_id),
    access_point_name VARCHAR(255) NOT NULL,
    access_point_type VARCHAR(30) NOT NULL, -- 'door', 'gate', 'turnstile', 'elevator', 'escalator'
    access_control_system VARCHAR(50),
    security_clearance_required VARCHAR(20) DEFAULT 'standard',
    biometric_enabled BOOLEAN DEFAULT FALSE,
    card_reader_enabled BOOLEAN DEFAULT TRUE,
    pin_required BOOLEAN DEFAULT FALSE,
    time_restrictions JSONB DEFAULT '{}',
    access_log_enabled BOOLEAN DEFAULT TRUE,
    emergency_override BOOLEAN DEFAULT TRUE,
    operational_status VARCHAR(20) DEFAULT 'active', -- 'active', 'maintenance', 'out_of_service'
    last_maintenance TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Access logs
CREATE TABLE IF NOT EXISTS access_logs (
    log_id SERIAL PRIMARY KEY,
    access_point_id INTEGER REFERENCES facility_access_control(access_id),
    person_id VARCHAR(50), -- could reference employee or visitor ID
    person_name VARCHAR(255),
    person_type VARCHAR(20) DEFAULT 'employee', -- 'employee', 'visitor', 'contractor', 'emergency'
    access_card_number VARCHAR(50),
    access_method VARCHAR(30), -- 'card', 'biometric', 'pin', 'manual_override'
    access_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    access_result VARCHAR(20) DEFAULT 'granted', -- 'granted', 'denied', 'error'
    denial_reason VARCHAR(100),
    zone_entered VARCHAR(50),
    zone_exited VARCHAR(50),
    duration_minutes INTEGER,
    security_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Facility performance metrics
CREATE TABLE IF NOT EXISTS facility_performance_metrics (
    metric_id SERIAL PRIMARY KEY,
    date DATE,
    zone_id VARCHAR(50) REFERENCES facility_zones(zone_id),
    system_id VARCHAR(50) REFERENCES building_systems(system_id),
    metric_type VARCHAR(50) NOT NULL, -- 'uptime', 'energy_efficiency', 'occupancy', 'maintenance_cost', 'response_time'
    metric_name VARCHAR(100) NOT NULL,
    metric_value DECIMAL(10,4),
    target_value DECIMAL(10,4),
    variance_percentage DECIMAL(5,2),
    benchmark_value DECIMAL(10,4),
    performance_rating VARCHAR(10), -- 'excellent', 'good', 'average', 'poor', 'critical'
    improvement_actions JSONB DEFAULT '[]',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Facility alerts and notifications
CREATE TABLE IF NOT EXISTS facility_alerts (
    alert_id SERIAL PRIMARY KEY,
    alert_type VARCHAR(30) NOT NULL, -- 'system_failure', 'sensor_alert', 'maintenance_due', 'security_breach', 'environmental'
    severity_level VARCHAR(20) DEFAULT 'medium', -- 'low', 'medium', 'high', 'critical'
    system_id VARCHAR(50) REFERENCES building_systems(system_id),
    sensor_id VARCHAR(50) REFERENCES iot_sensors(sensor_id),
    zone_id VARCHAR(50) REFERENCES facility_zones(zone_id),
    alert_title VARCHAR(255) NOT NULL,
    alert_description TEXT,
    alert_value DECIMAL(10,4),
    threshold_value DECIMAL(10,4),
    affected_areas JSONB DEFAULT '[]',
    recommended_actions JSONB DEFAULT '[]',
    assigned_to VARCHAR(50),
    acknowledged_by VARCHAR(50),
    acknowledged_at TIMESTAMP,
    resolved_by VARCHAR(50),
    resolved_at TIMESTAMP,
    resolution_notes TEXT,
    alert_status VARCHAR(20) DEFAULT 'active', -- 'active', 'acknowledged', 'resolved', 'escalated'
    escalation_level INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Indexes for performance
CREATE INDEX IF NOT EXISTS idx_building_systems_type ON building_systems(system_type);
CREATE INDEX IF NOT EXISTS idx_building_systems_status ON building_systems(operational_status);
CREATE INDEX IF NOT EXISTS idx_building_systems_location ON building_systems(building_name, floor_number);
CREATE INDEX IF NOT EXISTS idx_iot_sensors_type ON iot_sensors(sensor_type);
CREATE INDEX IF NOT EXISTS idx_iot_sensors_status ON iot_sensors(operational_status);
CREATE INDEX IF NOT EXISTS idx_iot_sensors_system ON iot_sensors(building_system_id);
CREATE INDEX IF NOT EXISTS idx_sensor_readings_sensor ON sensor_readings(sensor_id);
CREATE INDEX IF NOT EXISTS idx_sensor_readings_timestamp ON sensor_readings(reading_timestamp);
CREATE INDEX IF NOT EXISTS idx_utilities_type ON utilities_monitoring(utility_type);
CREATE INDEX IF NOT EXISTS idx_utilities_timestamp ON utilities_monitoring(reading_timestamp);
CREATE INDEX IF NOT EXISTS idx_energy_date ON energy_management(date);
CREATE INDEX IF NOT EXISTS idx_energy_building ON energy_management(building_name);
CREATE INDEX IF NOT EXISTS idx_facility_zones_type ON facility_zones(zone_type);
CREATE INDEX IF NOT EXISTS idx_facility_zones_status ON facility_zones(operational_status);
CREATE INDEX IF NOT EXISTS idx_work_orders_type ON maintenance_work_orders(work_order_type);
CREATE INDEX IF NOT EXISTS idx_work_orders_status ON maintenance_work_orders(work_order_status);
CREATE INDEX IF NOT EXISTS idx_work_orders_system ON maintenance_work_orders(system_id);
CREATE INDEX IF NOT EXISTS idx_inspections_type ON facility_inspections(inspection_type);
CREATE INDEX IF NOT EXISTS idx_inspections_date ON facility_inspections(inspection_date);
CREATE INDEX IF NOT EXISTS idx_assets_type ON asset_management(asset_type);
CREATE INDEX IF NOT EXISTS idx_assets_status ON asset_management(operational_status);
CREATE INDEX IF NOT EXISTS idx_space_utilization_zone ON space_utilization(zone_id);
CREATE INDEX IF NOT EXISTS idx_environmental_zone ON environmental_monitoring(zone_id);
CREATE INDEX IF NOT EXISTS idx_access_control_zone ON facility_access_control(zone_id);
CREATE INDEX IF NOT EXISTS idx_access_logs_point ON access_logs(access_point_id);
CREATE INDEX IF NOT EXISTS idx_access_logs_timestamp ON access_logs(access_timestamp);
CREATE INDEX IF NOT EXISTS idx_performance_date ON facility_performance_metrics(date);
CREATE INDEX IF NOT EXISTS idx_performance_type ON facility_performance_metrics(metric_type);
CREATE INDEX IF NOT EXISTS idx_alerts_type ON facility_alerts(alert_type);
CREATE INDEX IF NOT EXISTS idx_alerts_status ON facility_alerts(alert_status);
CREATE INDEX IF NOT EXISTS idx_alerts_severity ON facility_alerts(severity_level);

-- Insert sample building systems
INSERT INTO building_systems (system_id, system_name, system_type, building_name, floor_number, operational_status) VALUES
('SYS-HVAC-001', 'Terminal A Main HVAC Unit', 'hvac', 'Terminal A', 'All Floors', 'operational'),
('SYS-ELEC-001', 'Main Electrical Distribution Panel', 'electrical', 'Terminal A', 'Basement', 'operational'),
('SYS-FIRE-001', 'Terminal A Fire Suppression System', 'fire_safety', 'Terminal A', 'All Floors', 'operational'),
('SYS-ELEV-001', 'Terminal A Elevator Bank A', 'elevator', 'Terminal A', 'Floors 1-3', 'operational'),
('SYS-SECURITY-001', 'Terminal A Security System', 'security', 'Terminal A', 'All Floors', 'operational');

-- Insert sample IoT sensors
INSERT INTO iot_sensors (sensor_id, sensor_name, sensor_type, location, normal_range_min, normal_range_max, alert_threshold_min, alert_threshold_max) VALUES
('SENSOR-TEMP-001', 'Terminal A Baggage Claim Temperature', 'temperature', 'Terminal A - Baggage Claim', 18.0, 25.0, 15.0, 30.0),
('SENSOR-HUMID-001', 'Terminal A Main Hall Humidity', 'humidity', 'Terminal A - Main Hall', 30.0, 60.0, 20.0, 70.0),
('SENSOR-CO2-001', 'Terminal A Gate 12 CO2 Levels', 'co2', 'Terminal A - Gate 12', 400.0, 1000.0, 350.0, 1500.0),
('SENSOR-MOTION-001', 'Terminal A Security Corridor Motion', 'motion', 'Terminal A - Security Corridor', 0.0, 1.0, 0.0, 1.0),
('SENSOR-SMOKE-001', 'Terminal A Kitchen Smoke Detector', 'smoke', 'Terminal A - Kitchen Area', 0.0, 1.0, 0.0, 1.0);

-- Insert sample facility zones
INSERT INTO facility_zones (zone_id, zone_name, zone_type, building_name, area_sqm, occupancy_capacity, temperature_controlled) VALUES
('ZONE-TERM-A', 'Terminal A Main Hall', 'terminal', 'Terminal A', 5000.00, 1000, true),
('ZONE-CARGO-001', 'Main Cargo Terminal', 'cargo_area', 'Cargo Terminal', 8000.00, 200, false),
('ZONE-MAINT-001', 'Maintenance Hangar Alpha', 'maintenance_hangar', 'Maintenance Facility', 3000.00, 50, false),
('ZONE-OFFICE-001', 'Administration Building', 'office_building', 'Admin Building', 2000.00, 150, true),
('ZONE-PARKING-001', 'Main Parking Structure', 'parking', 'Parking Garage', 10000.00, 800, false);

-- Function to record sensor reading
CREATE OR REPLACE FUNCTION record_sensor_reading(
    p_sensor_id VARCHAR,
    p_reading_value DECIMAL,
    p_reading_unit VARCHAR DEFAULT NULL,
    p_environmental_conditions JSONB DEFAULT '{}'
) RETURNS INTEGER AS $$
DECLARE
    v_reading_id INTEGER;
    v_sensor_record RECORD;
    v_alert_triggered BOOLEAN := FALSE;
    v_alert_type VARCHAR(50);
BEGIN
    -- Get sensor details
    SELECT * INTO v_sensor_record FROM iot_sensors WHERE sensor_id = p_sensor_id;

    IF NOT FOUND THEN
        RAISE EXCEPTION 'Sensor not found: %', p_sensor_id;
    END IF;

    -- Determine if alert should be triggered
    IF p_reading_value < v_sensor_record.alert_threshold_min THEN
        v_alert_triggered := TRUE;
        v_alert_type := 'threshold_exceeded_low';
    ELSIF p_reading_value > v_sensor_record.alert_threshold_max THEN
        v_alert_triggered := TRUE;
        v_alert_type := 'threshold_exceeded_high';
    END IF;

    -- Insert reading
    INSERT INTO sensor_readings (
        sensor_id, reading_value, reading_unit, environmental_conditions,
        alert_triggered, alert_type
    ) VALUES (
        p_sensor_id, p_reading_value,
        COALESCE(p_reading_unit, v_sensor_record.measurement_unit),
        p_environmental_conditions, v_alert_triggered, v_alert_type
    ) RETURNING reading_id INTO v_reading_id;

    -- Update sensor last reading
    UPDATE iot_sensors
    SET last_reading = CURRENT_TIMESTAMP,
        last_reading_value = p_reading_value,
        updated_at = CURRENT_TIMESTAMP
    WHERE sensor_id = p_sensor_id;

    -- Create alert if triggered
    IF v_alert_triggered THEN
        INSERT INTO facility_alerts (
            alert_type, severity_level, sensor_id, alert_title,
            alert_description, alert_value, threshold_value, affected_areas
        ) VALUES (
            'sensor_alert',
            CASE WHEN v_alert_type LIKE '%low%' THEN 'high' ELSE 'medium' END,
            p_sensor_id,
            'Sensor Alert: ' || v_sensor_record.sensor_name,
            'Sensor reading outside acceptable range',
            p_reading_value,
            CASE WHEN v_alert_type LIKE '%low%' THEN v_sensor_record.alert_threshold_min
                 ELSE v_sensor_record.alert_threshold_max END,
            json_build_array(v_sensor_record.location)
        );
    END IF;

    RETURN v_reading_id;
END;
$$ LANGUAGE plpgsql;

-- Function to create maintenance work order
CREATE OR REPLACE FUNCTION create_maintenance_work_order(
    p_work_order_data JSONB,
    p_created_by VARCHAR
) RETURNS VARCHAR AS $$
DECLARE
    v_work_order_id VARCHAR(50);
    v_work_order_number VARCHAR(100);
BEGIN
    -- Generate work order ID and number
    v_work_order_id := 'WO-' || TO_CHAR(CURRENT_TIMESTAMP, 'YYYYMMDD') || '-' || LPAD(NEXTVAL('work_order_seq')::TEXT, 6, '0');
    v_work_order_number := 'MAINT-' || TO_CHAR(CURRENT_TIMESTAMP, 'YYYYMMDDHH24MISS');

    -- Insert work order
    INSERT INTO maintenance_work_orders (
        work_order_id, work_order_number, work_order_type, priority_level,
        system_id, zone_id, sensor_id, description, reported_by,
        estimated_duration_hours, safety_requirements
    ) VALUES (
        v_work_order_id,
        v_work_order_number,
        COALESCE(p_work_order_data->>'work_order_type', 'corrective'),
        COALESCE(p_work_order_data->>'priority_level', 'medium'),
        p_work_order_data->>'system_id',
        p_work_order_data->>'zone_id',
        p_work_order_data->>'sensor_id',
        p_work_order_data->>'description',
        p_created_by,
        (p_work_order_data->>'estimated_duration_hours')::DECIMAL,
        p_work_order_data->>'safety_requirements'
    );

    -- Create alert for high priority work orders
    IF (p_work_order_data->>'priority_level') IN ('high', 'critical') THEN
        INSERT INTO facility_alerts (
            alert_type, severity_level, alert_title, alert_description,
            affected_areas, recommended_actions
        ) VALUES (
            'maintenance_due',
            CASE WHEN p_work_order_data->>'priority_level' = 'critical' THEN 'critical' ELSE 'high' END,
            'High Priority Maintenance Required',
            p_work_order_data->>'description',
            json_build_array('Maintenance Required'),
            json_build_array('Schedule maintenance immediately')
        );
    END IF;

    RETURN v_work_order_id;
END;
$$ LANGUAGE plpgsql;

-- Function to calculate facility utilization
CREATE OR REPLACE FUNCTION calculate_facility_utilization(p_zone_id VARCHAR)
RETURNS DECIMAL AS $$
DECLARE
    v_utilization DECIMAL;
BEGIN
    SELECT
        CASE
            WHEN area_sqm > 0 THEN ROUND((occupied_area_sqm / area_sqm) * 100, 2)
            ELSE 0
        END
    INTO v_utilization
    FROM facility_zones
    WHERE zone_id = p_zone_id;

    RETURN v_utilization;
END;
$$ LANGUAGE plpgsql;

-- Function to get building system health score
CREATE OR REPLACE FUNCTION get_system_health_score(p_system_id VARCHAR)
RETURNS DECIMAL AS $$
DECLARE
    v_health_score DECIMAL := 100.0;
    v_system_record RECORD;
    v_days_since_maintenance INTEGER;
    v_alerts_count INTEGER;
BEGIN
    -- Get system details
    SELECT * INTO v_system_record FROM building_systems WHERE system_id = p_system_id;

    IF NOT FOUND THEN
        RETURN 0;
    END IF;

    -- Reduce score based on operational status
    IF v_system_record.operational_status = 'maintenance' THEN
        v_health_score := v_health_score - 20;
    ELSIF v_system_record.operational_status = 'out_of_service' THEN
        v_health_score := v_health_score - 50;
    ELSIF v_system_record.operational_status = 'failed' THEN
        v_health_score := v_health_score - 100;
    END IF;

    -- Reduce score based on time since last maintenance
    IF v_system_record.last_maintenance IS NOT NULL THEN
        v_days_since_maintenance := EXTRACT(EPOCH FROM (CURRENT_TIMESTAMP - v_system_record.last_maintenance)) / 86400;
        IF v_days_since_maintenance > (v_system_record.maintenance_interval_months * 30) THEN
            v_health_score := v_health_score - 15;
        END IF;
    END IF;

    -- Reduce score based on active alerts
    SELECT COUNT(*) INTO v_alerts_count
    FROM facility_alerts
    WHERE system_id = p_system_id
    AND alert_status = 'active'
    AND severity_level IN ('high', 'critical');

    v_health_score := v_health_score - (v_alerts_count * 10);

    -- Ensure score is between 0 and 100
    RETURN GREATEST(0, LEAST(100, v_health_score));
END;
$$ LANGUAGE plpgsql;

-- Function to generate facility inspection report
CREATE OR REPLACE FUNCTION generate_facility_inspection_report(p_start_date DATE, p_end_date DATE)
RETURNS JSON AS $$
DECLARE
    result JSON;
BEGIN
    SELECT json_build_object(
        'period', json_build_object('start_date', p_start_date, 'end_date', p_end_date),
        'inspection_summary', json_build_object(
            'total_inspections', (
                SELECT COUNT(*) FROM facility_inspections
                WHERE DATE(inspection_date) BETWEEN p_start_date AND p_end_date
            ),
            'by_type', (
                SELECT json_agg(
                    json_build_object(
                        'type', inspection_type,
                        'count', COUNT(*),
                        'avg_duration', ROUND(AVG(inspection_duration_minutes), 2)
                    )
                )
                FROM facility_inspections
                WHERE DATE(inspection_date) BETWEEN p_start_date AND p_end_date
                GROUP BY inspection_type
            ),
            'compliance_status', (
                SELECT json_agg(
                    json_build_object(
                        'status', compliance_status,
                        'count', COUNT(*),
                        'percentage', ROUND(
                            COUNT(*)::DECIMAL /
                            (SELECT COUNT(*) FROM facility_inspections
                             WHERE DATE(inspection_date) BETWEEN p_start_date AND p_end_date)::DECIMAL * 100, 2
                        )
                    )
                )
                FROM facility_inspections
                WHERE DATE(inspection_date) BETWEEN p_start_date AND p_end_date
                GROUP BY compliance_status
            )
        ),
        'issues_found', json_build_object(
            'critical_issues', (
                SELECT SUM(critical_issues) FROM facility_inspections
                WHERE DATE(inspection_date) BETWEEN p_start_date AND p_end_date
            ),
            'major_issues', (
                SELECT SUM(major_issues) FROM facility_inspections
                WHERE DATE(inspection_date) BETWEEN p_start_date AND p_end_date
            ),
            'minor_issues', (
                SELECT SUM(minor_issues) FROM facility_inspections
                WHERE DATE(inspection_date) BETWEEN p_start_date AND p_end_date
            )
        ),
        'maintenance_recommendations', (
            SELECT json_agg(
                json_build_object(
                    'system_name', bs.system_name,
                    'recommendations', fi.recommendations,
                    'inspection_date', fi.inspection_date
                )
            )
            FROM facility_inspections fi
            JOIN building_systems bs ON fi.system_id = bs.system_id
            WHERE DATE(fi.inspection_date) BETWEEN p_start_date AND p_end_date
            AND jsonb_array_length(fi.recommendations) > 0
            LIMIT 10
        )
    ) INTO result;

    RETURN result;
END;
$$ LANGUAGE plpgsql;

-- Function to get infrastructure dashboard data
CREATE OR REPLACE FUNCTION get_infrastructure_dashboard()
RETURNS JSON AS $$
DECLARE
    result JSON;
BEGIN
    SELECT json_build_object(
        'building_systems_status', (
            SELECT json_agg(
                json_build_object(
                    'system_type', system_type,
                    'operational_count', COUNT(CASE WHEN operational_status = 'operational' THEN 1 END),
                    'maintenance_count', COUNT(CASE WHEN operational_status = 'maintenance' THEN 1 END),
                    'failed_count', COUNT(CASE WHEN operational_status = 'failed' THEN 1 END),
                    'avg_health_score', ROUND(AVG(get_system_health_score(system_id)), 2)
                )
            )
            FROM building_systems
            GROUP BY system_type
        ),
        'sensor_status', (
            SELECT json_agg(
                json_build_object(
                    'sensor_type', sensor_type,
                    'active_count', COUNT(CASE WHEN operational_status = 'active' THEN 1 END),
                    'failed_count', COUNT(CASE WHEN operational_status = 'failed' THEN 1 END),
                    'alerts_today', COUNT(CASE WHEN sr.alert_triggered = true THEN 1 END)
                )
            )
            FROM iot_sensors s
            LEFT JOIN sensor_readings sr ON s.sensor_id = sr.sensor_id
            AND DATE(sr.reading_timestamp) = CURRENT_DATE
            GROUP BY sensor_type
        ),
        'facility_zones_utilization', (
            SELECT json_agg(
                json_build_object(
                    'zone_name', zone_name,
                    'zone_type', zone_type,
                    'utilization_rate', calculate_facility_utilization(zone_id),
                    'operational_status', operational_status
                )
            )
            FROM facility_zones
            WHERE operational_status = 'active'
            LIMIT 10
        ),
        'active_alerts', (
            SELECT COUNT(*) FROM facility_alerts
            WHERE alert_status = 'active'
        ),
        'maintenance_due', (
            SELECT COUNT(*) FROM maintenance_work_orders
            WHERE work_order_status IN ('open', 'assigned')
            AND priority_level IN ('high', 'critical')
        ),
        'energy_consumption_today', (
            SELECT total_energy_consumption
            FROM energy_management
            WHERE date = CURRENT_DATE
            ORDER BY date DESC
            LIMIT 1
        ),
        'environmental_compliance', (
            SELECT json_build_object(
                'compliant_zones', COUNT(CASE WHEN environmental_compliance = 'compliant' THEN 1 END),
                'warning_zones', COUNT(CASE WHEN environmental_compliance = 'warning' THEN 1 END),
                'non_compliant_zones', COUNT(CASE WHEN environmental_compliance = 'non_compliant' THEN 1 END)
            )
            FROM environmental_monitoring
            WHERE DATE(monitoring_date) = CURRENT_DATE
        ),
        'recent_work_orders', (
            SELECT json_agg(
                json_build_object(
                    'work_order_number', work_order_number,
                    'work_order_type', work_order_type,
                    'priority_level', priority_level,
                    'status', work_order_status,
                    'description', description
                )
            )
            FROM maintenance_work_orders
            ORDER BY reported_at DESC
            LIMIT 5
        )
    ) INTO result;

    RETURN result;
END;
$$ LANGUAGE plpgsql;
