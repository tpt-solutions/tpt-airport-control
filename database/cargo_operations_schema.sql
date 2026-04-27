-- Cargo Operations Module Schema
-- Manages freight forwarding, cargo terminals, customs clearance, and hazardous materials

-- Cargo shipments
CREATE TABLE IF NOT EXISTS cargo_shipments (
    shipment_id VARCHAR(50) PRIMARY KEY,
    shipment_number VARCHAR(100) UNIQUE NOT NULL,
    shipment_type VARCHAR(30) NOT NULL, -- 'air_freight', 'ground_transport', 'express', 'standard'
    origin_airport VARCHAR(10) NOT NULL,
    destination_airport VARCHAR(10) NOT NULL,
    shipper_name VARCHAR(255) NOT NULL,
    shipper_contact JSONB,
    consignee_name VARCHAR(255) NOT NULL,
    consignee_contact JSONB,
    freight_forwarder VARCHAR(255),
    carrier_name VARCHAR(255),
    flight_id INTEGER REFERENCES flights(flight_id),
    awb_number VARCHAR(50), -- Air Waybill number
    hawb_number VARCHAR(50), -- House Air Waybill number
    booking_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    scheduled_departure TIMESTAMP,
    actual_departure TIMESTAMP,
    scheduled_arrival TIMESTAMP,
    actual_arrival TIMESTAMP,
    shipment_status VARCHAR(30) DEFAULT 'booked', -- 'booked', 'received', 'processed', 'loaded', 'in_transit', 'arrived', 'delivered', 'cancelled'
    priority_level VARCHAR(20) DEFAULT 'standard', -- 'express', 'priority', 'standard', 'economy'
    service_level VARCHAR(20) DEFAULT 'standard', -- 'next_flight', 'same_day', 'time_definite', 'standard'
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Cargo items
CREATE TABLE IF NOT EXISTS cargo_items (
    item_id SERIAL PRIMARY KEY,
    shipment_id VARCHAR(50) REFERENCES cargo_shipments(shipment_id),
    item_description TEXT NOT NULL,
    harmonized_code VARCHAR(20), -- HS code for customs
    quantity INTEGER NOT NULL,
    weight_kg DECIMAL(10,2),
    volume_m3 DECIMAL(8,3),
    dimensions JSONB, -- length, width, height
    unit_value DECIMAL(10,2),
    total_value DECIMAL(12,2),
    currency VARCHAR(3) DEFAULT 'USD',
    package_type VARCHAR(50), -- 'box', 'pallet', 'drum', 'crate', 'envelope'
    special_handling JSONB DEFAULT '{}', -- fragile, hazardous, temperature_controlled, etc.
    insurance_required BOOLEAN DEFAULT FALSE,
    insurance_value DECIMAL(12,2),
    customs_status VARCHAR(30) DEFAULT 'pending', -- 'pending', 'cleared', 'held', 'rejected'
    security_status VARCHAR(30) DEFAULT 'pending', -- 'pending', 'cleared', 'flagged'
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Cargo terminal operations
CREATE TABLE IF NOT EXISTS cargo_terminals (
    terminal_id VARCHAR(50) PRIMARY KEY,
    terminal_name VARCHAR(255) NOT NULL,
    airport_code VARCHAR(10) NOT NULL,
    terminal_type VARCHAR(30) NOT NULL, -- 'freight_terminal', 'cargo_village', 'express_center'
    location VARCHAR(255),
    operating_hours JSONB,
    capacity_m3 DECIMAL(12,2),
    current_utilization_m3 DECIMAL(12,2) DEFAULT 0,
    temperature_zones JSONB DEFAULT '[]', -- refrigerated, frozen, ambient
    security_clearance VARCHAR(20) DEFAULT 'standard',
    contact_info JSONB,
    status VARCHAR(20) DEFAULT 'operational', -- 'operational', 'maintenance', 'closed'
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Cargo warehouse zones
CREATE TABLE IF NOT EXISTS warehouse_zones (
    zone_id VARCHAR(50) PRIMARY KEY,
    terminal_id VARCHAR(50) REFERENCES cargo_terminals(terminal_id),
    zone_name VARCHAR(100) NOT NULL,
    zone_type VARCHAR(30) NOT NULL, -- 'ambient', 'refrigerated', 'frozen', 'hazardous', 'valuable', 'oversized'
    temperature_range VARCHAR(20), -- e.g., '2-8°C', '-20°C', '15-25°C'
    humidity_control BOOLEAN DEFAULT FALSE,
    security_level VARCHAR(20) DEFAULT 'standard',
    capacity_m3 DECIMAL(10,2),
    current_occupancy_m3 DECIMAL(10,2) DEFAULT 0,
    rack_configuration JSONB DEFAULT '{}',
    access_restrictions JSONB DEFAULT '[]',
    status VARCHAR(20) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Freight forwarders
CREATE TABLE IF NOT EXISTS freight_forwarders (
    forwarder_id VARCHAR(50) PRIMARY KEY,
    company_name VARCHAR(255) NOT NULL,
    business_registration VARCHAR(100),
    iata_code VARCHAR(10),
    contact_info JSONB,
    service_areas JSONB DEFAULT '[]', -- regions/countries served
    specializations JSONB DEFAULT '[]', -- types of cargo handled
    insurance_coverage DECIMAL(15,2),
    bonding_capacity DECIMAL(15,2),
    performance_rating DECIMAL(3,2),
    compliance_status VARCHAR(20) DEFAULT 'compliant',
    contract_status VARCHAR(20) DEFAULT 'active', -- 'active', 'suspended', 'terminated'
    contract_start_date DATE,
    contract_end_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Hazardous materials
CREATE TABLE IF NOT EXISTS hazardous_materials (
    material_id SERIAL PRIMARY KEY,
    un_number VARCHAR(10), -- UN number for hazardous materials
    proper_name VARCHAR(255) NOT NULL,
    hazard_class VARCHAR(10),
    packing_group VARCHAR(5),
    subsidiary_risks JSONB DEFAULT '[]',
    special_provisions TEXT,
    limited_quantity BOOLEAN DEFAULT FALSE,
    excepted_quantity BOOLEAN DEFAULT FALSE,
    marine_pollutant BOOLEAN DEFAULT FALSE,
    tunnel_restriction VARCHAR(10),
    transport_category VARCHAR(5),
    environmental_hazards JSONB DEFAULT '{}',
    emergency_response JSONB DEFAULT '{}',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Cargo security screening
CREATE TABLE IF NOT EXISTS cargo_security_screening (
    screening_id SERIAL PRIMARY KEY,
    shipment_id VARCHAR(50) REFERENCES cargo_shipments(shipment_id),
    screening_type VARCHAR(30) NOT NULL, -- 'xray', 'explosive_trace', 'chemical', 'physical'
    screening_station VARCHAR(50),
    screener_id VARCHAR(50),
    screening_result VARCHAR(20) DEFAULT 'pending', -- 'pending', 'cleared', 'flagged', 'rejected'
    threat_level VARCHAR(20), -- 'none', 'low', 'medium', 'high', 'critical'
    anomalies_detected JSONB DEFAULT '[]',
    screening_duration_minutes INTEGER,
    equipment_used VARCHAR(100),
    image_references JSONB DEFAULT '[]',
    notes TEXT,
    screened_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Customs declarations
CREATE TABLE IF NOT EXISTS customs_declarations (
    declaration_id SERIAL PRIMARY KEY,
    shipment_id VARCHAR(50) REFERENCES cargo_shipments(shipment_id),
    declaration_number VARCHAR(50) UNIQUE,
    declarant_name VARCHAR(255),
    declarant_contact JSONB,
    customs_broker VARCHAR(255),
    broker_contact JSONB,
    declaration_type VARCHAR(30) NOT NULL, -- 'import', 'export', 'transit', 'temporary_import'
    customs_value DECIMAL(12,2),
    currency VARCHAR(3) DEFAULT 'USD',
    exchange_rate DECIMAL(8,4),
    customs_duties DECIMAL(10,2),
    taxes DECIMAL(10,2),
    total_charges DECIMAL(10,2),
    preferential_treatment VARCHAR(100),
    special_programs JSONB DEFAULT '[]',
    supporting_documents JSONB DEFAULT '[]',
    declaration_status VARCHAR(30) DEFAULT 'draft', -- 'draft', 'submitted', 'under_review', 'approved', 'rejected', 'cleared'
    submitted_at TIMESTAMP,
    approved_at TIMESTAMP,
    clearance_number VARCHAR(50),
    customs_officer VARCHAR(100),
    inspection_required BOOLEAN DEFAULT FALSE,
    inspection_scheduled TIMESTAMP,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Cargo tracking events
CREATE TABLE IF NOT EXISTS cargo_tracking_events (
    event_id SERIAL PRIMARY KEY,
    shipment_id VARCHAR(50) REFERENCES cargo_shipments(shipment_id),
    event_type VARCHAR(50) NOT NULL, -- 'received', 'processed', 'loaded', 'departed', 'arrived', 'cleared', 'delivered'
    event_location VARCHAR(255),
    event_description TEXT,
    event_data JSONB DEFAULT '{}',
    recorded_by VARCHAR(50),
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    next_expected_event VARCHAR(50),
    estimated_completion TIMESTAMP,
    delay_reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Temperature monitoring
CREATE TABLE IF NOT EXISTS temperature_monitoring (
    monitoring_id SERIAL PRIMARY KEY,
    shipment_id VARCHAR(50) REFERENCES cargo_shipments(shipment_id),
    zone_id VARCHAR(50) REFERENCES warehouse_zones(zone_id),
    sensor_id VARCHAR(50),
    temperature_celsius DECIMAL(5,2),
    humidity_percentage DECIMAL(5,2),
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    acceptable_range_min DECIMAL(5,2),
    acceptable_range_max DECIMAL(5,2),
    alert_triggered BOOLEAN DEFAULT FALSE,
    alert_type VARCHAR(30), -- 'temperature_high', 'temperature_low', 'humidity_high', 'humidity_low'
    corrective_action TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Cargo insurance
CREATE TABLE IF NOT EXISTS cargo_insurance (
    insurance_id SERIAL PRIMARY KEY,
    shipment_id VARCHAR(50) REFERENCES cargo_shipments(shipment_id),
    insurance_provider VARCHAR(255) NOT NULL,
    policy_number VARCHAR(100) UNIQUE,
    coverage_type VARCHAR(50) NOT NULL, -- 'all_risk', 'named_perils', 'transit_only'
    insured_value DECIMAL(12,2),
    premium_amount DECIMAL(8,2),
    deductible_amount DECIMAL(8,2),
    coverage_start TIMESTAMP,
    coverage_end TIMESTAMP,
    special_conditions TEXT,
    claim_history JSONB DEFAULT '[]',
    status VARCHAR(20) DEFAULT 'active', -- 'active', 'expired', 'claimed', 'cancelled'
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Cargo performance metrics
CREATE TABLE IF NOT EXISTS cargo_performance_metrics (
    metric_id SERIAL PRIMARY KEY,
    date DATE,
    terminal_id VARCHAR(50) REFERENCES cargo_terminals(terminal_id),
    total_shipments INTEGER DEFAULT 0,
    on_time_deliveries INTEGER DEFAULT 0,
    average_processing_time_hours DECIMAL(6,2),
    customs_clearance_time_hours DECIMAL(6,2),
    security_clearance_time_hours DECIMAL(6,2),
    warehouse_utilization_percentage DECIMAL(5,2),
    equipment_uptime_percentage DECIMAL(5,2),
    customer_satisfaction_rating DECIMAL(3,2),
    incidents_reported INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Perishable goods monitoring
CREATE TABLE IF NOT EXISTS perishable_monitoring (
    monitoring_id SERIAL PRIMARY KEY,
    shipment_id VARCHAR(50) REFERENCES cargo_shipments(shipment_id),
    item_id INTEGER REFERENCES cargo_items(item_id),
    product_type VARCHAR(50) NOT NULL, -- 'fresh_produce', 'dairy', 'meat', 'pharmaceuticals', 'flowers'
    temperature_required_min DECIMAL(5,2),
    temperature_required_max DECIMAL(5,2),
    humidity_required_min DECIMAL(5,2),
    humidity_required_max DECIMAL(5,2),
    shelf_life_days INTEGER,
    monitoring_frequency_minutes INTEGER DEFAULT 15,
    alerts_enabled BOOLEAN DEFAULT TRUE,
    last_check TIMESTAMP,
    next_check TIMESTAMP,
    compliance_status VARCHAR(20) DEFAULT 'compliant', -- 'compliant', 'warning', 'critical', 'breached'
    corrective_actions JSONB DEFAULT '[]',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Cargo equipment tracking
CREATE TABLE IF NOT EXISTS cargo_equipment (
    equipment_id VARCHAR(50) PRIMARY KEY,
    equipment_type VARCHAR(50) NOT NULL, -- 'forklift', 'pallet_jack', 'conveyor', 'scanner', 'loader'
    equipment_model VARCHAR(100),
    terminal_id VARCHAR(50) REFERENCES cargo_terminals(terminal_id),
    status VARCHAR(20) DEFAULT 'available', -- 'available', 'in_use', 'maintenance', 'out_of_service'
    fuel_level DECIMAL(5,2),
    battery_level DECIMAL(5,2),
    last_maintenance TIMESTAMP,
    next_maintenance TIMESTAMP,
    maintenance_schedule JSONB DEFAULT '{}',
    usage_hours INTEGER DEFAULT 0,
    location VARCHAR(100),
    assigned_operator VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Equipment maintenance logs
CREATE TABLE IF NOT EXISTS equipment_maintenance_logs (
    log_id SERIAL PRIMARY KEY,
    equipment_id VARCHAR(50) REFERENCES cargo_equipment(equipment_id),
    maintenance_type VARCHAR(30) NOT NULL, -- 'preventive', 'corrective', 'inspection', 'repair'
    maintenance_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    performed_by VARCHAR(50),
    description TEXT,
    parts_used JSONB DEFAULT '[]',
    labor_hours DECIMAL(4,2),
    cost DECIMAL(8,2),
    next_maintenance_date TIMESTAMP,
    maintenance_status VARCHAR(20) DEFAULT 'completed',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Indexes for performance
CREATE INDEX IF NOT EXISTS idx_cargo_shipments_status ON cargo_shipments(shipment_status);
CREATE INDEX IF NOT EXISTS idx_cargo_shipments_flight ON cargo_shipments(flight_id);
CREATE INDEX IF NOT EXISTS idx_cargo_shipments_dates ON cargo_shipments(scheduled_departure, scheduled_arrival);
CREATE INDEX IF NOT EXISTS idx_cargo_items_shipment ON cargo_items(shipment_id);
CREATE INDEX IF NOT EXISTS idx_cargo_items_harmonized ON cargo_items(harmonized_code);
CREATE INDEX IF NOT EXISTS idx_cargo_terminals_airport ON cargo_terminals(airport_code);
CREATE INDEX IF NOT EXISTS idx_warehouse_zones_terminal ON warehouse_zones(terminal_id);
CREATE INDEX IF NOT EXISTS idx_warehouse_zones_type ON warehouse_zones(zone_type);
CREATE INDEX IF NOT EXISTS idx_freight_forwarders_status ON freight_forwarders(contract_status);
CREATE INDEX IF NOT EXISTS idx_hazardous_un_number ON hazardous_materials(un_number);
CREATE INDEX IF NOT EXISTS idx_security_screening_shipment ON cargo_security_screening(shipment_id);
CREATE INDEX IF NOT EXISTS idx_customs_declarations_shipment ON customs_declarations(shipment_id);
CREATE INDEX IF NOT EXISTS idx_customs_declarations_status ON customs_declarations(declaration_status);
CREATE INDEX IF NOT EXISTS idx_tracking_events_shipment ON cargo_tracking_events(shipment_id);
CREATE INDEX IF NOT EXISTS idx_tracking_events_type ON cargo_tracking_events(event_type);
CREATE INDEX IF NOT EXISTS idx_temperature_shipment ON temperature_monitoring(shipment_id);
CREATE INDEX IF NOT EXISTS idx_temperature_zone ON temperature_monitoring(zone_id);
CREATE INDEX IF NOT EXISTS idx_insurance_shipment ON cargo_insurance(shipment_id);
CREATE INDEX IF NOT EXISTS idx_performance_date ON cargo_performance_metrics(date);
CREATE INDEX IF NOT EXISTS idx_performance_terminal ON cargo_performance_metrics(terminal_id);
CREATE INDEX IF NOT EXISTS idx_perishable_shipment ON perishable_monitoring(shipment_id);
CREATE INDEX IF NOT EXISTS idx_perishable_status ON perishable_monitoring(compliance_status);
CREATE INDEX IF NOT EXISTS idx_equipment_terminal ON cargo_equipment(terminal_id);
CREATE INDEX IF NOT EXISTS idx_equipment_status ON cargo_equipment(status);
CREATE INDEX IF NOT EXISTS idx_equipment_maintenance_equipment ON equipment_maintenance_logs(equipment_id);

-- Insert sample cargo terminals
INSERT INTO cargo_terminals (terminal_id, terminal_name, airport_code, terminal_type, capacity_m3, status) VALUES
('CGO001', 'Main Cargo Terminal', 'JFK', 'freight_terminal', 50000.00, 'operational'),
('CGO002', 'Express Cargo Center', 'LAX', 'express_center', 25000.00, 'operational'),
('CGO003', 'Perishable Goods Facility', 'ORD', 'cargo_village', 30000.00, 'operational'),
('CGO004', 'Hazardous Materials Terminal', 'DFW', 'freight_terminal', 20000.00, 'operational')
ON CONFLICT DO NOTHING;

-- Insert sample warehouse zones
INSERT INTO warehouse_zones (zone_id, terminal_id, zone_name, zone_type, temperature_range, capacity_m3) VALUES
('ZONE001', 'CGO001', 'Ambient Storage A', 'ambient', '15-25°C', 10000.00),
('ZONE002', 'CGO001', 'Refrigerated Storage B', 'refrigerated', '2-8°C', 5000.00),
('ZONE003', 'CGO003', 'Frozen Storage C', 'frozen', '-20°C', 8000.00),
('ZONE004', 'CGO004', 'Hazardous Storage D', 'hazardous', '15-25°C', 3000.00)
ON CONFLICT DO NOTHING;

-- Insert sample freight forwarders
INSERT INTO freight_forwarders (forwarder_id, company_name, iata_code, performance_rating, contract_status) VALUES
('FF001', 'Global Freight Solutions', 'GFS001', 4.5, 'active'),
('FF002', 'International Cargo Services', 'ICS001', 4.2, 'active'),
('FF003', 'Express Logistics Group', 'ELG001', 4.7, 'active'),
('FF004', 'Premium Freight Forwarders', 'PFF001', 4.3, 'active')
ON CONFLICT DO NOTHING;

-- Insert sample hazardous materials
INSERT INTO hazardous_materials (un_number, proper_name, hazard_class, packing_group) VALUES
('UN1993', 'Flammable liquids, n.o.s.', '3', 'III'),
('UN2814', 'Infectious substance, affecting humans', '6.2', 'I'),
('UN3480', 'Lithium ion batteries', '9', 'II'),
('UN3077', 'Environmentally hazardous substance, solid, n.o.s.', '9', 'III')
ON CONFLICT DO NOTHING;

-- Function to create cargo shipment
CREATE OR REPLACE FUNCTION create_cargo_shipment(
    p_shipment_data JSONB,
    p_items JSONB
) RETURNS VARCHAR AS $$
DECLARE
    v_shipment_id VARCHAR(50);
    v_item JSONB;
BEGIN
    -- Generate shipment ID
    v_shipment_id := 'CGO-' || TO_CHAR(CURRENT_TIMESTAMP, 'YYYYMMDD') || '-' || LPAD(NEXTVAL('cargo_shipment_seq')::TEXT, 6, '0');

    -- Insert shipment
    INSERT INTO cargo_shipments (
        shipment_id, shipment_number, shipment_type, origin_airport, destination_airport,
        shipper_name, shipper_contact, consignee_name, consignee_contact,
        freight_forwarder, carrier_name, priority_level, service_level
    ) VALUES (
        v_shipment_id,
        COALESCE(p_shipment_data->>'shipment_number', v_shipment_id),
        COALESCE(p_shipment_data->>'shipment_type', 'standard'),
        p_shipment_data->>'origin_airport',
        p_shipment_data->>'destination_airport',
        p_shipment_data->>'shipper_name',
        p_shipment_data->'shipper_contact',
        p_shipment_data->>'consignee_name',
        p_shipment_data->'consignee_contact',
        p_shipment_data->>'freight_forwarder',
        p_shipment_data->>'carrier_name',
        COALESCE(p_shipment_data->>'priority_level', 'standard'),
        COALESCE(p_shipment_data->>'service_level', 'standard')
    );

    -- Insert items
    FOR v_item IN SELECT * FROM jsonb_array_elements(p_items)
    LOOP
        INSERT INTO cargo_items (
            shipment_id, item_description, harmonized_code, quantity,
            weight_kg, volume_m3, unit_value, total_value, package_type,
            special_handling
        ) VALUES (
            v_shipment_id,
            v_item->>'item_description',
            v_item->>'harmonized_code',
            (v_item->>'quantity')::INTEGER,
            (v_item->>'weight_kg')::DECIMAL,
            (v_item->>'volume_m3')::DECIMAL,
            (v_item->>'unit_value')::DECIMAL,
            (v_item->>'total_value')::DECIMAL,
            COALESCE(v_item->>'package_type', 'box'),
            COALESCE(v_item->'special_handling', '{}')
        );
    END LOOP;

    -- Create initial tracking event
    PERFORM track_cargo_event(v_shipment_id, 'created', 'Cargo Terminal', 'Shipment created and registered');

    RETURN v_shipment_id;
END;
$$ LANGUAGE plpgsql;

-- Function to track cargo events
CREATE OR REPLACE FUNCTION track_cargo_event(
    p_shipment_id VARCHAR,
    p_event_type VARCHAR,
    p_location VARCHAR,
    p_description TEXT,
    p_event_data JSONB DEFAULT '{}'
) RETURNS INTEGER AS $$
DECLARE
    v_event_id INTEGER;
BEGIN
    INSERT INTO cargo_tracking_events (
        shipment_id, event_type, event_location, event_description, event_data
    ) VALUES (
        p_shipment_id, p_event_type, p_location, p_description, p_event_data
    ) RETURNING event_id INTO v_event_id;

    -- Update shipment status based on event
    UPDATE cargo_shipments
    SET shipment_status = CASE
        WHEN p_event_type = 'received' THEN 'received'
        WHEN p_event_type = 'processed' THEN 'processed'
        WHEN p_event_type = 'loaded' THEN 'loaded'
        WHEN p_event_type = 'departed' THEN 'in_transit'
        WHEN p_event_type = 'arrived' THEN 'arrived'
        WHEN p_event_type = 'delivered' THEN 'delivered'
        ELSE shipment_status
    END,
    updated_at = CURRENT_TIMESTAMP
    WHERE shipment_id = p_shipment_id;

    RETURN v_event_id;
END;
$$ LANGUAGE plpgsql;

-- Function to calculate cargo terminal utilization
CREATE OR REPLACE FUNCTION calculate_terminal_utilization(p_terminal_id VARCHAR)
RETURNS DECIMAL AS $$
DECLARE
    v_total_capacity DECIMAL;
    v_current_utilization DECIMAL;
BEGIN
    SELECT capacity_m3, current_utilization_m3
    INTO v_total_capacity, v_current_utilization
    FROM cargo_terminals
    WHERE terminal_id = p_terminal_id;

    IF v_total_capacity > 0 THEN
        RETURN ROUND((v_current_utilization / v_total_capacity) * 100, 2);
    END IF;

    RETURN 0;
END;
$$ LANGUAGE plpgsql;

-- Function to check hazardous materials compatibility
CREATE OR REPLACE FUNCTION check_hazardous_compatibility(p_un_numbers JSONB)
RETURNS JSON AS $$
DECLARE
    result JSON;
    v_un_number TEXT;
    v_material RECORD;
BEGIN
    -- This would implement complex hazardous materials compatibility checking
    -- For now, return basic compatibility assessment
    SELECT json_build_object(
        'compatible', true,
        'warnings', '[]'::jsonb,
        'restrictions', '[]'::jsonb,
        'special_handling_required', false
    ) INTO result;

    RETURN result;
END;
$$ LANGUAGE plpgsql;

-- Function to calculate customs duties
CREATE OR REPLACE FUNCTION calculate_customs_duties(
    p_shipment_id VARCHAR,
    p_destination_country VARCHAR
) RETURNS DECIMAL AS $$
DECLARE
    v_total_duties DECIMAL := 0;
    v_item RECORD;
BEGIN
    -- Simplified customs calculation - in production this would integrate with customs APIs
    FOR v_item IN
        SELECT * FROM cargo_items WHERE shipment_id = p_shipment_id
    LOOP
        -- Apply duty rates based on harmonized code and destination
        -- This is a simplified calculation
        v_total_duties := v_total_duties + (v_item.total_value * 0.05); -- 5% duty rate
    END LOOP;

    RETURN ROUND(v_total_duties, 2);
END;
$$ LANGUAGE plpgsql;

-- Function to generate cargo performance report
CREATE OR REPLACE FUNCTION generate_cargo_performance_report(p_start_date DATE, p_end_date DATE)
RETURNS JSON AS $$
DECLARE
    result JSON;
BEGIN
    SELECT json_build_object(
        'period', json_build_object('start_date', p_start_date, 'end_date', p_end_date),
        'summary', json_build_object(
            'total_shipments', (
                SELECT COUNT(*) FROM cargo_shipments
                WHERE booking_date BETWEEN p_start_date AND p_end_date
            ),
            'on_time_deliveries', (
                SELECT COUNT(*) FROM cargo_shipments
                WHERE booking_date BETWEEN p_start_date AND p_end_date
                AND shipment_status = 'delivered'
                AND actual_arrival <= scheduled_arrival
            ),
            'average_processing_time', (
                SELECT ROUND(AVG(EXTRACT(EPOCH FROM (actual_departure - booking_date))/3600), 2)
                FROM cargo_shipments
                WHERE booking_date BETWEEN p_start_date AND p_end_date
                AND actual_departure IS NOT NULL
            ),
            'total_revenue', (
                SELECT SUM(total_value) FROM cargo_items ci
                JOIN cargo_shipments cs ON ci.shipment_id = cs.shipment_id
                WHERE cs.booking_date BETWEEN p_start_date AND p_end_date
            )
        ),
        'by_terminal', (
            SELECT json_agg(
                json_build_object(
                    'terminal_name', ct.terminal_name,
                    'shipments_processed', COUNT(cs.shipment_id),
                    'utilization_rate', calculate_terminal_utilization(ct.terminal_id),
                    'on_time_performance', ROUND(
                        COUNT(CASE WHEN cs.actual_arrival <= cs.scheduled_arrival THEN 1 END)::DECIMAL /
                        COUNT(*)::DECIMAL * 100, 2
                    )
                )
            )
            FROM cargo_terminals ct
            LEFT JOIN cargo_shipments cs ON ct.terminal_id = cs.origin_airport
            WHERE cs.booking_date BETWEEN p_start_date AND p_end_date
            GROUP BY ct.terminal_id, ct.terminal_name
        ),
        'by_shipment_type', (
            SELECT json_agg(
                json_build_object(
                    'shipment_type', shipment_type,
                    'count', COUNT(*),
                    'avg_processing_time', ROUND(AVG(EXTRACT(EPOCH FROM (actual_departure - booking_date))/3600), 2)
                )
            )
            FROM cargo_shipments
            WHERE booking_date BETWEEN p_start_date AND p_end_date
            GROUP BY shipment_type
        )
    ) INTO result;

    RETURN result;
END;
$$ LANGUAGE plpgsql;

-- Function to monitor temperature compliance
CREATE OR REPLACE FUNCTION monitor_temperature_compliance(p_shipment_id VARCHAR)
RETURNS JSON AS $$
DECLARE
    result JSON;
BEGIN
    SELECT json_build_object(
        'shipment_id', p_shipment_id,
        'compliance_status', (
            SELECT CASE
                WHEN COUNT(*) = 0 THEN 'no_monitoring'
                WHEN COUNT(CASE WHEN alert_triggered THEN 1 END) = 0 THEN 'compliant'
                WHEN COUNT(CASE WHEN alert_triggered THEN 1 END) > 0 THEN 'breached'
                ELSE 'unknown'
            END
            FROM temperature_monitoring
            WHERE shipment_id = p_shipment_id
        ),
        'temperature_readings', (
            SELECT COUNT(*)
            FROM temperature_monitoring
            WHERE shipment_id = p_shipment_id
        ),
        'alerts_triggered', (
            SELECT COUNT(*)
            FROM temperature_monitoring
            WHERE shipment_id = p_shipment_id AND alert_triggered = true
        ),
        'last_reading', (
            SELECT json_build_object(
                'temperature', temperature_celsius,
                'humidity', humidity_percentage,
                'timestamp', recorded_at
            )
            FROM temperature_monitoring
            WHERE shipment_id = p_shipment_id
            ORDER BY recorded_at DESC
            LIMIT 1
        )
    ) INTO result;

    RETURN result;
END;
$$ LANGUAGE plpgsql;

-- Function to get cargo dashboard data
CREATE OR REPLACE FUNCTION get_cargo_dashboard()
RETURNS JSON AS $$
DECLARE
    result JSON;
BEGIN
    SELECT json_build_object(
        'active_shipments', (
            SELECT COUNT(*) FROM cargo_shipments
            WHERE shipment_status NOT IN ('delivered', 'cancelled')
        ),
        'shipments_today', (
            SELECT COUNT(*) FROM cargo_shipments
            WHERE DATE(booking_date) = CURRENT_DATE
        ),
        'delivered_today', (
            SELECT COUNT(*) FROM cargo_shipments
            WHERE DATE(actual_arrival) = CURRENT_DATE
            AND shipment_status = 'delivered'
        ),
        'pending_customs', (
            SELECT COUNT(*) FROM customs_declarations
            WHERE declaration_status IN ('draft', 'submitted', 'under_review')
        ),
        'terminal_utilization', (
            SELECT json_agg(
                json_build_object(
                    'terminal_name', terminal_name,
                    'utilization_rate', calculate_terminal_utilization(terminal_id)
                )
            )
            FROM cargo_terminals
            WHERE status = 'operational'
        ),
        'temperature_alerts', (
            SELECT COUNT(*) FROM temperature_monitoring
            WHERE alert_triggered = true
            AND recorded_at >= CURRENT_TIMESTAMP - INTERVAL '24 hours'
        ),
        'equipment_status', (
            SELECT json_agg(
                json_build_object(
                    'equipment_type', equipment_type,
                    'available', COUNT(CASE WHEN status = 'available' THEN 1 END),
                    'in_use', COUNT(CASE WHEN status = 'in_use' THEN 1 END),
                    'maintenance', COUNT(CASE WHEN status = 'maintenance' THEN 1 END)
                )
            )
            FROM cargo_equipment
            GROUP BY equipment_type
        ),
        'recent_events', (
            SELECT json_agg(
                json_build_object(
                    'shipment_id', shipment_id,
                    'event_type', event_type,
                    'location', event_location,
                    'timestamp', recorded_at
                )
            )
            FROM cargo_tracking_events
            ORDER BY recorded_at DESC
            LIMIT 10
        )
    ) INTO result;

    RETURN result;
END;
$$ LANGUAGE plpgsql;
