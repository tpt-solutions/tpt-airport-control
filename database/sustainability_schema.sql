-- Environmental & Sustainability Module Schema
-- Tracks carbon emissions, noise monitoring, and green energy initiatives

-- Carbon emissions tracking
CREATE TABLE IF NOT EXISTS carbon_emissions (
    emission_id SERIAL PRIMARY KEY,
    flight_id INTEGER REFERENCES flights(flight_id),
    aircraft_type VARCHAR(50),
    fuel_type VARCHAR(50),
    fuel_consumed DECIMAL(10,2), -- in liters
    distance_flown DECIMAL(8,2), -- in kilometers
    emission_factor DECIMAL(6,4), -- kg CO2 per liter
    co2_emitted DECIMAL(10,2), -- in kg
    nox_emitted DECIMAL(8,2), -- in kg
    measurement_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_source VARCHAR(100), -- 'calculated', 'sensor', 'api'
    confidence_level INTEGER CHECK (confidence_level BETWEEN 1 AND 100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Noise monitoring data
CREATE TABLE IF NOT EXISTS noise_monitoring (
    noise_id SERIAL PRIMARY KEY,
    sensor_location VARCHAR(100),
    latitude DECIMAL(10,8),
    longitude DECIMAL(11,8),
    noise_level DECIMAL(6,2), -- in dB
    measurement_type VARCHAR(20), -- 'continuous', 'event', 'peak'
    aircraft_callsign VARCHAR(20),
    flight_id INTEGER REFERENCES flights(flight_id),
    wind_speed DECIMAL(5,2),
    wind_direction INTEGER,
    temperature DECIMAL(5,2),
    humidity DECIMAL(5,2),
    measurement_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sensor_status VARCHAR(20) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Green energy systems
CREATE TABLE IF NOT EXISTS green_energy_systems (
    system_id SERIAL PRIMARY KEY,
    system_name VARCHAR(100) NOT NULL,
    system_type VARCHAR(50) NOT NULL, -- 'solar', 'wind', 'geothermal', 'hydro'
    location VARCHAR(255),
    capacity_kw DECIMAL(10,2),
    current_output_kw DECIMAL(8,2),
    efficiency_percentage DECIMAL(5,2),
    installation_date DATE,
    maintenance_schedule JSONB,
    status VARCHAR(20) DEFAULT 'operational',
    carbon_offset_kg DECIMAL(12,2), -- annual carbon offset
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Energy consumption tracking
CREATE TABLE IF NOT EXISTS energy_consumption (
    consumption_id SERIAL PRIMARY KEY,
    facility_type VARCHAR(50), -- 'terminal', 'tower', 'hangar', 'runway_lighting'
    energy_source VARCHAR(50), -- 'grid', 'solar', 'wind', 'diesel_generator'
    consumption_kwh DECIMAL(12,2),
    consumption_date DATE,
    peak_demand_kw DECIMAL(8,2),
    off_peak_consumption DECIMAL(10,2),
    carbon_intensity DECIMAL(6,4), -- kg CO2 per kWh
    cost_per_kwh DECIMAL(6,4),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Waste management tracking
CREATE TABLE IF NOT EXISTS waste_management (
    waste_id SERIAL PRIMARY KEY,
    waste_type VARCHAR(50), -- 'municipal', 'hazardous', 'recyclable', 'organic'
    facility_area VARCHAR(100),
    weight_kg DECIMAL(10,2),
    volume_cubic_m DECIMAL(8,2),
    disposal_method VARCHAR(50), -- 'landfill', 'incineration', 'recycling', 'composting'
    contractor_name VARCHAR(100),
    cost DECIMAL(8,2),
    collection_date DATE,
    carbon_footprint DECIMAL(8,2), -- kg CO2 equivalent
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Water conservation tracking
CREATE TABLE IF NOT EXISTS water_conservation (
    water_id SERIAL PRIMARY KEY,
    facility_type VARCHAR(50),
    usage_type VARCHAR(50), -- 'potable', 'non_potable', 'recycled'
    consumption_liters DECIMAL(12,2),
    measurement_date DATE,
    conservation_measures JSONB, -- array of applied measures
    recycled_percentage DECIMAL(5,2),
    cost_per_liter DECIMAL(6,4),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Sustainability KPIs
CREATE TABLE IF NOT EXISTS sustainability_kpis (
    kpi_id SERIAL PRIMARY KEY,
    kpi_name VARCHAR(100) NOT NULL,
    kpi_category VARCHAR(50), -- 'emissions', 'energy', 'waste', 'water', 'noise'
    target_value DECIMAL(12,2),
    current_value DECIMAL(12,2),
    unit VARCHAR(20),
    measurement_period VARCHAR(20), -- 'daily', 'weekly', 'monthly', 'quarterly', 'yearly'
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    target_achievement_date DATE,
    status VARCHAR(20) DEFAULT 'on_track', -- 'on_track', 'at_risk', 'off_track'
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Environmental compliance records
CREATE TABLE IF NOT EXISTS environmental_compliance (
    compliance_id SERIAL PRIMARY KEY,
    regulation_name VARCHAR(255) NOT NULL,
    regulation_body VARCHAR(100), -- 'EPA', 'FAA', 'ICAO', 'Local'
    compliance_status VARCHAR(20), -- 'compliant', 'non_compliant', 'pending'
    inspection_date DATE,
    next_inspection_date DATE,
    findings TEXT,
    corrective_actions TEXT,
    fine_amount DECIMAL(10,2),
    compliance_officer VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- IoT sensors for environmental monitoring
CREATE TABLE IF NOT EXISTS environmental_sensors (
    sensor_id SERIAL PRIMARY KEY,
    sensor_name VARCHAR(100) NOT NULL,
    sensor_type VARCHAR(50), -- 'air_quality', 'noise', 'emissions', 'weather'
    location VARCHAR(255),
    latitude DECIMAL(10,8),
    longitude DECIMAL(11,8),
    installation_date DATE,
    last_calibration DATE,
    calibration_due DATE,
    status VARCHAR(20) DEFAULT 'active',
    battery_level INTEGER CHECK (battery_level BETWEEN 0 AND 100),
    data_transmission_interval INTEGER, -- in minutes
    api_endpoint VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Sensor readings
CREATE TABLE IF NOT EXISTS sensor_readings (
    reading_id SERIAL PRIMARY KEY,
    sensor_id INTEGER REFERENCES environmental_sensors(sensor_id),
    parameter_name VARCHAR(50), -- 'pm25', 'noise_level', 'co2', 'temperature'
    parameter_value DECIMAL(10,4),
    unit VARCHAR(20),
    reading_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_quality VARCHAR(20) DEFAULT 'good', -- 'good', 'fair', 'poor'
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Green building certifications
CREATE TABLE IF NOT EXISTS green_certifications (
    certification_id SERIAL PRIMARY KEY,
    facility_name VARCHAR(100),
    certification_type VARCHAR(50), -- 'LEED', 'BREEAM', 'Green Star'
    certification_level VARCHAR(20), -- 'Certified', 'Silver', 'Gold', 'Platinum'
    issue_date DATE,
    expiry_date DATE,
    certifying_body VARCHAR(100),
    score DECIMAL(5,2),
    points_achieved INTEGER,
    total_points INTEGER,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Indexes for performance
CREATE INDEX IF NOT EXISTS idx_carbon_emissions_flight ON carbon_emissions(flight_id);
CREATE INDEX IF NOT EXISTS idx_carbon_emissions_date ON carbon_emissions(measurement_date);
CREATE INDEX IF NOT EXISTS idx_noise_monitoring_location ON noise_monitoring(sensor_location);
CREATE INDEX IF NOT EXISTS idx_noise_monitoring_time ON noise_monitoring(measurement_time);
CREATE INDEX IF NOT EXISTS idx_green_energy_status ON green_energy_systems(status);
CREATE INDEX IF NOT EXISTS idx_energy_consumption_date ON energy_consumption(consumption_date);
CREATE INDEX IF NOT EXISTS idx_waste_collection_date ON waste_management(collection_date);
CREATE INDEX IF NOT EXISTS idx_water_measurement_date ON water_conservation(measurement_date);
CREATE INDEX IF NOT EXISTS idx_sensor_readings_sensor ON sensor_readings(sensor_id);
CREATE INDEX IF NOT EXISTS idx_sensor_readings_timestamp ON sensor_readings(reading_timestamp);

-- Insert sample sustainability KPIs
INSERT INTO sustainability_kpis (kpi_name, kpi_category, target_value, current_value, unit, measurement_period, target_achievement_date) VALUES
('Annual CO2 Emissions', 'emissions', 50000.00, 45000.00, 'tons', 'yearly', '2025-12-31'),
('Renewable Energy Usage', 'energy', 75.00, 65.00, 'percentage', 'yearly', '2025-12-31'),
('Waste Diversion Rate', 'waste', 80.00, 72.00, 'percentage', 'quarterly', '2025-12-31'),
('Water Consumption Reduction', 'water', 15.00, 12.00, 'percentage', 'yearly', '2025-12-31'),
('Average Noise Level', 'noise', 65.00, 63.00, 'dB', 'monthly', '2025-12-31')
ON CONFLICT DO NOTHING;

-- Insert sample environmental sensors
INSERT INTO environmental_sensors (sensor_name, sensor_type, location, latitude, longitude, installation_date, status) VALUES
('Terminal Air Quality Sensor', 'air_quality', 'Main Terminal Entrance', -37.0082, 174.7850, '2024-01-15', 'active'),
('Runway Noise Monitor', 'noise', 'Runway 05R Threshold', -37.0100, 174.7900, '2024-02-01', 'active'),
('Emissions Monitoring Station', 'emissions', 'Engine Test Bay', -37.0120, 174.7880, '2024-01-20', 'active'),
('Weather Station', 'weather', 'Control Tower Roof', -37.0095, 174.7865, '2024-01-10', 'active')
ON CONFLICT DO NOTHING;

-- Function to calculate carbon emissions for a flight
CREATE OR REPLACE FUNCTION calculate_flight_emissions(
    p_flight_id INTEGER,
    p_fuel_consumed DECIMAL,
    p_distance DECIMAL
) RETURNS DECIMAL AS $$
DECLARE
    emission_factor DECIMAL := 3.15; -- kg CO2 per liter for jet fuel
    emissions DECIMAL;
BEGIN
    emissions := p_fuel_consumed * emission_factor;

    INSERT INTO carbon_emissions (
        flight_id, fuel_consumed, distance_flown,
        emission_factor, co2_emitted, data_source, confidence_level
    ) VALUES (
        p_flight_id, p_fuel_consumed, p_distance,
        emission_factor, emissions, 'calculated', 85
    );

    RETURN emissions;
END;
$$ LANGUAGE plpgsql;

-- Function to get sustainability dashboard data
CREATE OR REPLACE FUNCTION get_sustainability_dashboard()
RETURNS JSON AS $$
DECLARE
    result JSON;
BEGIN
    SELECT json_build_object(
        'current_month_emissions', (
            SELECT COALESCE(SUM(co2_emitted), 0)
            FROM carbon_emissions
            WHERE DATE_TRUNC('month', measurement_date) = DATE_TRUNC('month', CURRENT_DATE)
        ),
        'total_green_energy', (
            SELECT COALESCE(SUM(capacity_kw), 0)
            FROM green_energy_systems
            WHERE status = 'operational'
        ),
        'current_green_energy_output', (
            SELECT COALESCE(SUM(current_output_kw), 0)
            FROM green_energy_systems
            WHERE status = 'operational'
        ),
        'waste_diversion_rate', (
            SELECT CASE
                WHEN SUM(CASE WHEN disposal_method IN ('recycling', 'composting') THEN weight_kg ELSE 0 END) = 0 THEN 0
                ELSE ROUND(
                    (SUM(CASE WHEN disposal_method IN ('recycling', 'composting') THEN weight_kg ELSE 0 END) * 100.0 /
                     NULLIF(SUM(weight_kg), 0)), 2
                )
            END
            FROM waste_management
            WHERE collection_date >= CURRENT_DATE - INTERVAL '30 days'
        ),
        'active_sensors', (
            SELECT COUNT(*)
            FROM environmental_sensors
            WHERE status = 'active'
        ),
        'compliance_status', (
            SELECT json_agg(
                json_build_object(
                    'regulation', regulation_name,
                    'status', compliance_status,
                    'next_inspection', next_inspection_date
                )
            )
            FROM environmental_compliance
            WHERE next_inspection_date >= CURRENT_DATE
            ORDER BY next_inspection_date
            LIMIT 5
        )
    ) INTO result;

    RETURN result;
END;
$$ LANGUAGE plpgsql;

-- Trigger to update sustainability KPIs
CREATE OR REPLACE FUNCTION update_sustainability_kpis()
RETURNS TRIGGER AS $$
BEGIN
    -- Update emissions KPI
    IF TG_TABLE_NAME = 'carbon_emissions' THEN
        UPDATE sustainability_kpis
        SET current_value = (
            SELECT COALESCE(SUM(co2_emitted)/1000, 0) -- Convert to tons
            FROM carbon_emissions
            WHERE DATE_TRUNC('year', measurement_date) = DATE_TRUNC('year', CURRENT_DATE)
        ),
        last_updated = CURRENT_TIMESTAMP
        WHERE kpi_name = 'Annual CO2 Emissions';
    END IF;

    -- Update energy KPI
    IF TG_TABLE_NAME = 'energy_consumption' THEN
        UPDATE sustainability_kpis
        SET current_value = (
            SELECT CASE
                WHEN SUM(consumption_kwh) = 0 THEN 0
                ELSE ROUND(
                    (SUM(CASE WHEN energy_source IN ('solar', 'wind') THEN consumption_kwh ELSE 0 END) * 100.0 /
                     NULLIF(SUM(consumption_kwh), 0)), 2
                )
            END
            FROM energy_consumption
            WHERE DATE_TRUNC('year', consumption_date) = DATE_TRUNC('year', CURRENT_DATE)
        ),
        last_updated = CURRENT_TIMESTAMP
        WHERE kpi_name = 'Renewable Energy Usage';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Create triggers for KPI updates
CREATE TRIGGER update_emissions_kpi_trigger
    AFTER INSERT ON carbon_emissions
    FOR EACH STATEMENT
    EXECUTE FUNCTION update_sustainability_kpis();

CREATE TRIGGER update_energy_kpi_trigger
    AFTER INSERT ON energy_consumption
    FOR EACH STATEMENT
    EXECUTE FUNCTION update_sustainability_kpis();
