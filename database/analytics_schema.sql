-- Flight Control Database Schema - Analytics Module
-- Performance reports, analytics, and operational metrics

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

-- AI Conflict Prediction
CREATE TABLE conflict_predictions (
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

CREATE TABLE conflict_history (
    id SERIAL PRIMARY KEY,
    aircraft1_icao VARCHAR(6) NOT NULL,
    aircraft2_icao VARCHAR(6) NOT NULL,
    actual_conflict_time TIMESTAMP,
    min_horizontal_sep DECIMAL(6,2),
    min_vertical_sep DECIMAL(7,1),
    resolution_method TEXT,
    detected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

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
    recommendation_type VARCHAR(50), -- vector_change, altitude_change, speed_change, route_change
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

-- Predictive Conflict Detection
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
    conflict_id VARCHAR(50) REFERENCES conflict_predictions(conflict_id),
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
    conflict_id VARCHAR(50) REFERENCES conflict_predictions(conflict_id),
    alert_type VARCHAR(30), -- early_warning, imminent, critical
    alert_level VARCHAR(10), -- info, warning, danger
    alert_message TEXT,
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
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Indexes for analytics tables
CREATE INDEX idx_performance_reports_type ON performance_reports (report_type);
CREATE INDEX idx_performance_reports_period ON performance_reports (period_start, period_end);

CREATE INDEX idx_conflict_predictions_id ON conflict_predictions (conflict_id);
CREATE INDEX idx_conflict_predictions_time ON conflict_predictions (prediction_time);
CREATE INDEX idx_conflict_predictions_aircraft ON conflict_predictions (aircraft1_icao, aircraft2_icao);
CREATE INDEX idx_conflict_predictions_status ON conflict_predictions (status);
CREATE INDEX idx_conflict_predictions_severity ON conflict_predictions (severity_level);

CREATE INDEX idx_conflict_history_time ON conflict_history (detected_at);

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

CREATE INDEX idx_ml_route_models_name ON ml_route_models (model_name);
CREATE INDEX idx_ml_route_models_active ON ml_route_models (is_active);
CREATE INDEX idx_ml_training_routes_origin_dest ON ml_training_routes (origin_icao, destination_icao);
CREATE INDEX idx_ml_training_routes_aircraft ON ml_training_routes (aircraft_type);
CREATE INDEX idx_ml_route_optimizations_request ON ml_route_optimizations (request_id);
CREATE INDEX idx_ml_route_optimizations_model ON ml_route_optimizations (model_used);
CREATE INDEX idx_ml_route_features_type ON ml_route_features (feature_type);
CREATE INDEX idx_ml_model_performance_name ON ml_model_performance (model_name, recorded_at);
