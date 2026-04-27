-- Advanced Analytics Module Schema
-- Manages predictive models, demand forecasting, and AI-driven insights

-- Predictive models
CREATE TABLE IF NOT EXISTS predictive_models (
    model_id VARCHAR(50) PRIMARY KEY,
    model_name VARCHAR(255) NOT NULL,
    model_type VARCHAR(50) NOT NULL, -- 'demand_forecasting', 'delay_prediction', 'maintenance_prediction', 'revenue_optimization'
    algorithm VARCHAR(50) NOT NULL, -- 'linear_regression', 'random_forest', 'neural_network', 'xgboost'
    target_variable VARCHAR(100) NOT NULL,
    feature_columns JSONB NOT NULL,
    hyperparameters JSONB DEFAULT '{}',
    training_data_start TIMESTAMP,
    training_data_end TIMESTAMP,
    model_accuracy DECIMAL(5,4),
    model_precision DECIMAL(5,4),
    model_recall DECIMAL(5,4),
    model_f1_score DECIMAL(5,4),
    model_file_path TEXT,
    model_version VARCHAR(20) DEFAULT '1.0.0',
    is_active BOOLEAN DEFAULT TRUE,
    last_trained TIMESTAMP,
    next_training TIMESTAMP,
    training_frequency VARCHAR(20) DEFAULT 'daily', -- 'hourly', 'daily', 'weekly', 'monthly'
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Model predictions
CREATE TABLE IF NOT EXISTS model_predictions (
    prediction_id SERIAL PRIMARY KEY,
    model_id VARCHAR(50) REFERENCES predictive_models(model_id),
    prediction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    input_features JSONB NOT NULL,
    predicted_value DECIMAL(10,4),
    prediction_confidence DECIMAL(5,4),
    actual_value DECIMAL(10,4),
    prediction_error DECIMAL(10,4),
    prediction_category VARCHAR(20), -- 'high', 'medium', 'low' confidence
    is_accurate BOOLEAN,
    feedback_provided BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Demand forecasting
CREATE TABLE IF NOT EXISTS demand_forecasts (
    forecast_id SERIAL PRIMARY KEY,
    forecast_date DATE NOT NULL,
    forecast_type VARCHAR(50) NOT NULL, -- 'passenger_demand', 'cargo_demand', 'service_demand'
    time_granularity VARCHAR(20) DEFAULT 'hourly', -- 'hourly', 'daily', 'weekly', 'monthly'
    forecast_period_start TIMESTAMP NOT NULL,
    forecast_period_end TIMESTAMP NOT NULL,
    forecasted_demand DECIMAL(10,2),
    confidence_interval_lower DECIMAL(10,2),
    confidence_interval_upper DECIMAL(10,2),
    forecast_accuracy DECIMAL(5,4),
    influencing_factors JSONB DEFAULT '{}',
    weather_impact DECIMAL(5,4),
    seasonal_trend DECIMAL(5,4),
    external_events JSONB DEFAULT '[]',
    model_used VARCHAR(50) REFERENCES predictive_models(model_id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Maintenance predictions
CREATE TABLE IF NOT EXISTS maintenance_predictions (
    prediction_id SERIAL PRIMARY KEY,
    equipment_type VARCHAR(50) NOT NULL,
    equipment_id VARCHAR(50) NOT NULL,
    failure_probability DECIMAL(5,4),
    predicted_failure_date TIMESTAMP,
    confidence_level DECIMAL(5,4),
    risk_category VARCHAR(20), -- 'critical', 'high', 'medium', 'low'
    recommended_action VARCHAR(100),
    estimated_cost DECIMAL(8,2),
    downtime_impact_hours INTEGER,
    preventive_measures JSONB DEFAULT '[]',
    model_used VARCHAR(50) REFERENCES predictive_models(model_id),
    prediction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    action_taken BOOLEAN DEFAULT FALSE,
    action_date TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Revenue optimization
CREATE TABLE IF NOT EXISTS revenue_optimization (
    optimization_id SERIAL PRIMARY KEY,
    optimization_date DATE NOT NULL,
    optimization_type VARCHAR(50) NOT NULL, -- 'pricing', 'capacity', 'service_offering'
    target_metric VARCHAR(50) NOT NULL, -- 'revenue', 'profit', 'utilization'
    current_value DECIMAL(10,2),
    optimized_value DECIMAL(10,2),
    improvement_percentage DECIMAL(5,2),
    recommended_changes JSONB NOT NULL,
    implementation_cost DECIMAL(8,2),
    expected_roi DECIMAL(5,2),
    implementation_priority VARCHAR(20) DEFAULT 'medium',
    model_used VARCHAR(50) REFERENCES predictive_models(model_id),
    implemented BOOLEAN DEFAULT FALSE,
    implementation_date TIMESTAMP,
    actual_improvement DECIMAL(5,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Anomaly detection
CREATE TABLE IF NOT EXISTS anomaly_detection (
    anomaly_id SERIAL PRIMARY KEY,
    anomaly_type VARCHAR(50) NOT NULL, -- 'operational', 'security', 'performance', 'revenue'
    detection_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    severity_level VARCHAR(20) DEFAULT 'medium', -- 'low', 'medium', 'high', 'critical'
    affected_system VARCHAR(100),
    anomaly_description TEXT,
    root_cause TEXT,
    impact_assessment TEXT,
    recommended_actions JSONB DEFAULT '[]',
    confidence_score DECIMAL(5,4),
    false_positive BOOLEAN DEFAULT FALSE,
    investigation_status VARCHAR(20) DEFAULT 'pending', -- 'pending', 'investigating', 'resolved', 'closed'
    resolved_date TIMESTAMP,
    resolution_notes TEXT,
    model_used VARCHAR(50) REFERENCES predictive_models(model_id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Performance analytics
CREATE TABLE IF NOT EXISTS performance_analytics (
    analytics_id SERIAL PRIMARY KEY,
    analytics_date DATE NOT NULL,
    metric_category VARCHAR(50) NOT NULL, -- 'operational', 'financial', 'customer', 'safety'
    metric_name VARCHAR(100) NOT NULL,
    metric_value DECIMAL(10,4),
    target_value DECIMAL(10,4),
    variance_percentage DECIMAL(5,2),
    trend_direction VARCHAR(10), -- 'up', 'down', 'stable'
    benchmark_value DECIMAL(10,4),
    benchmark_comparison DECIMAL(5,2),
    influencing_factors JSONB DEFAULT '{}',
    forecast_next_period DECIMAL(10,4),
    recommendations JSONB DEFAULT '[]',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Customer behavior analytics
CREATE TABLE IF NOT EXISTS customer_behavior_analytics (
    behavior_id SERIAL PRIMARY KEY,
    analysis_date DATE NOT NULL,
    customer_segment VARCHAR(50),
    behavior_type VARCHAR(50) NOT NULL, -- 'booking_pattern', 'spending_pattern', 'service_usage', 'loyalty_trends'
    key_findings JSONB NOT NULL,
    segment_size INTEGER,
    average_value DECIMAL(8,2),
    growth_rate DECIMAL(5,2),
    churn_risk DECIMAL(5,2),
    recommendations JSONB DEFAULT '[]',
    predictive_insights JSONB DEFAULT '{}',
    model_used VARCHAR(50) REFERENCES predictive_models(model_id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Operational efficiency metrics
CREATE TABLE IF NOT EXISTS operational_efficiency (
    efficiency_id SERIAL PRIMARY KEY,
    measurement_date DATE NOT NULL,
    process_name VARCHAR(100) NOT NULL,
    process_category VARCHAR(50) NOT NULL, -- 'check_in', 'security', 'boarding', 'baggage', 'maintenance'
    baseline_time DECIMAL(6,2), -- in minutes
    actual_time DECIMAL(6,2),
    efficiency_percentage DECIMAL(5,2),
    bottleneck_identified VARCHAR(100),
    improvement_opportunities JSONB DEFAULT '[]',
    automation_potential DECIMAL(5,2),
    cost_savings_potential DECIMAL(8,2),
    implementation_priority VARCHAR(20) DEFAULT 'medium',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Risk assessment
CREATE TABLE IF NOT EXISTS risk_assessment (
    assessment_id SERIAL PRIMARY KEY,
    assessment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    risk_category VARCHAR(50) NOT NULL, -- 'operational', 'financial', 'safety', 'regulatory'
    risk_description TEXT,
    risk_probability DECIMAL(5,4), -- 0.0 to 1.0
    risk_impact DECIMAL(5,4), -- 0.0 to 1.0
    risk_score DECIMAL(5,4), -- probability * impact
    risk_level VARCHAR(20), -- 'very_low', 'low', 'medium', 'high', 'very_high'
    mitigation_strategies JSONB DEFAULT '[]',
    responsible_party VARCHAR(100),
    monitoring_frequency VARCHAR(20) DEFAULT 'weekly',
    last_reviewed TIMESTAMP,
    review_notes TEXT,
    model_used VARCHAR(50) REFERENCES predictive_models(model_id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Scenario planning
CREATE TABLE IF NOT EXISTS scenario_planning (
    scenario_id SERIAL PRIMARY KEY,
    scenario_name VARCHAR(255) NOT NULL,
    scenario_type VARCHAR(50) NOT NULL, -- 'business_continuity', 'capacity_planning', 'revenue_forecasting'
    trigger_event VARCHAR(100),
    probability DECIMAL(5,4),
    impact_assessment JSONB NOT NULL,
    response_plan JSONB NOT NULL,
    contingency_measures JSONB DEFAULT '[]',
    resource_requirements JSONB DEFAULT '{}',
    estimated_cost DECIMAL(10,2),
    implementation_timeline JSONB DEFAULT '{}',
    success_metrics JSONB DEFAULT '[]',
    model_used VARCHAR(50) REFERENCES predictive_models(model_id),
    is_active BOOLEAN DEFAULT TRUE,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Model training history
CREATE TABLE IF NOT EXISTS model_training_history (
    training_id SERIAL PRIMARY KEY,
    model_id VARCHAR(50) REFERENCES predictive_models(model_id),
    training_start TIMESTAMP NOT NULL,
    training_end TIMESTAMP,
    training_duration_seconds INTEGER,
    training_data_size INTEGER,
    training_accuracy DECIMAL(5,4),
    validation_accuracy DECIMAL(5,4),
    test_accuracy DECIMAL(5,4),
    hyperparameters_used JSONB DEFAULT '{}',
    feature_importance JSONB DEFAULT '{}',
    training_logs TEXT,
    training_status VARCHAR(20) DEFAULT 'completed', -- 'running', 'completed', 'failed'
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Automated insights
CREATE TABLE IF NOT EXISTS automated_insights (
    insight_id SERIAL PRIMARY KEY,
    insight_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    insight_type VARCHAR(50) NOT NULL, -- 'trend', 'anomaly', 'opportunity', 'risk'
    insight_title VARCHAR(255) NOT NULL,
    insight_description TEXT,
    confidence_level DECIMAL(5,4),
    impact_level VARCHAR(20), -- 'low', 'medium', 'high', 'critical'
    affected_areas JSONB DEFAULT '[]',
    recommended_actions JSONB DEFAULT '[]',
    data_sources JSONB DEFAULT '[]',
    model_used VARCHAR(50) REFERENCES predictive_models(model_id),
    is_reviewed BOOLEAN DEFAULT FALSE,
    reviewed_by VARCHAR(50),
    review_date TIMESTAMP,
    review_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Real-time monitoring alerts
CREATE TABLE IF NOT EXISTS monitoring_alerts (
    alert_id SERIAL PRIMARY KEY,
    alert_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    alert_type VARCHAR(50) NOT NULL, -- 'performance', 'capacity', 'security', 'operational'
    alert_severity VARCHAR(20) DEFAULT 'medium', -- 'low', 'medium', 'high', 'critical'
    alert_message TEXT,
    affected_system VARCHAR(100),
    threshold_breached VARCHAR(100),
    current_value DECIMAL(10,4),
    threshold_value DECIMAL(10,4),
    alert_status VARCHAR(20) DEFAULT 'active', -- 'active', 'acknowledged', 'resolved'
    acknowledged_by VARCHAR(50),
    acknowledged_date TIMESTAMP,
    resolved_date TIMESTAMP,
    resolution_notes TEXT,
    auto_generated BOOLEAN DEFAULT TRUE,
    model_used VARCHAR(50) REFERENCES predictive_models(model_id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Indexes for performance
CREATE INDEX IF NOT EXISTS idx_predictive_models_type ON predictive_models(model_type);
CREATE INDEX IF NOT EXISTS idx_predictive_models_active ON predictive_models(is_active);
CREATE INDEX IF NOT EXISTS idx_model_predictions_model ON model_predictions(model_id);
CREATE INDEX IF NOT EXISTS idx_model_predictions_date ON model_predictions(prediction_date);
CREATE INDEX IF NOT EXISTS idx_demand_forecasts_date ON demand_forecasts(forecast_date);
CREATE INDEX IF NOT EXISTS idx_demand_forecasts_type ON demand_forecasts(forecast_type);
CREATE INDEX IF NOT EXISTS idx_maintenance_predictions_equipment ON maintenance_predictions(equipment_id);
CREATE INDEX IF NOT EXISTS idx_maintenance_predictions_risk ON maintenance_predictions(risk_category);
CREATE INDEX IF NOT EXISTS idx_revenue_optimization_date ON revenue_optimization(optimization_date);
CREATE INDEX IF NOT EXISTS idx_revenue_optimization_type ON revenue_optimization(optimization_type);
CREATE INDEX IF NOT EXISTS idx_anomaly_detection_type ON anomaly_detection(anomaly_type);
CREATE INDEX IF NOT EXISTS idx_anomaly_detection_status ON anomaly_detection(investigation_status);
CREATE INDEX IF NOT EXISTS idx_performance_analytics_date ON performance_analytics(analytics_date);
CREATE INDEX IF NOT EXISTS idx_performance_analytics_category ON performance_analytics(metric_category);
CREATE INDEX IF NOT EXISTS idx_customer_behavior_date ON customer_behavior_analytics(analysis_date);
CREATE INDEX IF NOT EXISTS idx_operational_efficiency_date ON operational_efficiency(measurement_date);
CREATE INDEX IF NOT EXISTS idx_risk_assessment_category ON risk_assessment(risk_category);
CREATE INDEX IF NOT EXISTS idx_risk_assessment_level ON risk_assessment(risk_level);
CREATE INDEX IF NOT EXISTS idx_scenario_planning_type ON scenario_planning(scenario_type);
CREATE INDEX IF NOT EXISTS idx_training_history_model ON model_training_history(model_id);
CREATE INDEX IF NOT EXISTS idx_automated_insights_type ON automated_insights(insight_type);
CREATE INDEX IF NOT EXISTS idx_automated_insights_reviewed ON automated_insights(is_reviewed);
CREATE INDEX IF NOT EXISTS idx_monitoring_alerts_type ON monitoring_alerts(alert_type);
CREATE INDEX IF NOT EXISTS idx_monitoring_alerts_status ON monitoring_alerts(alert_status);

-- Insert sample predictive models
INSERT INTO predictive_models (model_id, model_name, model_type, algorithm, target_variable, feature_columns, model_accuracy, is_active) VALUES
('DEMAND_FL001', 'Passenger Demand Forecasting', 'demand_forecasting', 'xgboost', 'passenger_count', '["hour_of_day", "day_of_week", "weather_condition", "season", "holiday_flag"]', 0.87, true),
('DELAY_PR001', 'Flight Delay Prediction', 'delay_prediction', 'random_forest', 'delay_probability', '["departure_time", "weather_condition", "aircraft_type", "crew_availability", "passenger_load"]', 0.82, true),
('MAINT_PR001', 'Equipment Maintenance Prediction', 'maintenance_prediction', 'neural_network', 'failure_probability', '["usage_hours", "temperature", "vibration", "maintenance_history", "age"]', 0.91, true),
('REV_OPT001', 'Revenue Optimization', 'revenue_optimization', 'linear_regression', 'optimal_price', '["demand_level", "competition", "season", "customer_segment", "time_to_departure"]', 0.79, true)
ON CONFLICT DO NOTHING;

-- Insert sample demand forecasts
INSERT INTO demand_forecasts (forecast_date, forecast_type, forecast_period_start, forecast_period_end, forecasted_demand, confidence_interval_lower, confidence_interval_upper, forecast_accuracy) VALUES
(CURRENT_DATE, 'passenger_demand', CURRENT_DATE + INTERVAL '1 day', CURRENT_DATE + INTERVAL '2 days', 2500.00, 2300.00, 2700.00, 0.85),
(CURRENT_DATE, 'cargo_demand', CURRENT_DATE + INTERVAL '1 day', CURRENT_DATE + INTERVAL '2 days', 150.50, 140.00, 161.00, 0.88),
(CURRENT_DATE, 'service_demand', CURRENT_DATE + INTERVAL '1 day', CURRENT_DATE + INTERVAL '2 days', 450.00, 420.00, 480.00, 0.82)
ON CONFLICT DO NOTHING;

-- Insert sample maintenance predictions
INSERT INTO maintenance_predictions (equipment_type, equipment_id, failure_probability, predicted_failure_date, confidence_level, risk_category, recommended_action, estimated_cost) VALUES
('conveyor_belt', 'CONV001', 0.15, CURRENT_DATE + INTERVAL '30 days', 0.78, 'medium', 'Schedule preventive maintenance', 2500.00),
('check_in_kiosk', 'KIOSK001', 0.08, CURRENT_DATE + INTERVAL '60 days', 0.65, 'low', 'Monitor usage patterns', 800.00),
('security_scanner', 'SCAN001', 0.25, CURRENT_DATE + INTERVAL '15 days', 0.82, 'high', 'Immediate inspection required', 1500.00)
ON CONFLICT DO NOTHING;

-- Insert sample revenue optimization recommendations
INSERT INTO revenue_optimization (optimization_date, optimization_type, target_metric, current_value, optimized_value, improvement_percentage, recommended_changes, expected_roi) VALUES
(CURRENT_DATE, 'pricing', 'revenue', 150000.00, 165000.00, 10.00, '{"price_increase": {"business_class": 5, "economy": 3}, "dynamic_pricing": true}', 2.5),
(CURRENT_DATE, 'capacity', 'utilization', 75.00, 85.00, 13.33, '{"additional_flights": ["LAX-JFK", "ORD-MIA"], "schedule_optimization": true}', 3.2),
(CURRENT_DATE, 'service_offering', 'profit', 50000.00, 62000.00, 24.00, '{"premium_services": ["priority_boarding", "lounge_access"], "loyalty_program": "enhanced"}', 4.1)
ON CONFLICT DO NOTHING;

-- Function to generate demand forecast
CREATE OR REPLACE FUNCTION generate_demand_forecast(
    p_forecast_type VARCHAR,
    p_start_date TIMESTAMP,
    p_end_date TIMESTAMP,
    p_model_id VARCHAR DEFAULT NULL
) RETURNS JSON AS $$
DECLARE
    result JSON;
    v_model_id VARCHAR(50);
BEGIN
    -- Use specified model or find best available model
    IF p_model_id IS NOT NULL THEN
        v_model_id := p_model_id;
    ELSE
        SELECT model_id INTO v_model_id
        FROM predictive_models
        WHERE model_type = 'demand_forecasting'
        AND is_active = true
        ORDER BY model_accuracy DESC
        LIMIT 1;
    END IF;

    -- Generate forecast (simplified - in production this would call ML model)
    SELECT json_build_object(
        'forecast_type', p_forecast_type,
        'period_start', p_start_date,
        'period_end', p_end_date,
        'model_used', v_model_id,
        'forecasted_demand', (
            SELECT AVG(forecasted_demand)
            FROM demand_forecasts
            WHERE forecast_type = p_forecast_type
            AND forecast_date >= CURRENT_DATE - INTERVAL '30 days'
        ),
        'confidence_level', 0.85,
        'factors_considered', '["weather", "seasonality", "historical_data", "external_events"]'
    ) INTO result;

    RETURN result;
END;
$$ LANGUAGE plpgsql;

-- Function to detect operational anomalies
CREATE OR REPLACE FUNCTION detect_operational_anomalies(p_date DATE, p_system VARCHAR DEFAULT NULL)
RETURNS JSON AS $$
DECLARE
    result JSON;
BEGIN
    SELECT json_build_object(
        'detection_date', p_date,
        'system', p_system,
        'anomalies_found', (
            SELECT COUNT(*)
            FROM anomaly_detection
            WHERE DATE(detection_date) = p_date
            AND (p_system IS NULL OR affected_system = p_system)
            AND investigation_status = 'pending'
        ),
        'critical_anomalies', (
            SELECT COUNT(*)
            FROM anomaly_detection
            WHERE DATE(detection_date) = p_date
            AND severity_level = 'critical'
            AND (p_system IS NULL OR affected_system = p_system)
        ),
        'anomaly_types', (
            SELECT json_agg(
                json_build_object(
                    'type', anomaly_type,
                    'count', anomaly_count
                )
            )
            FROM (
                SELECT anomaly_type, COUNT(*) as anomaly_count
                FROM anomaly_detection
                WHERE DATE(detection_date) = p_date
                AND (p_system IS NULL OR affected_system = p_system)
                GROUP BY anomaly_type
            ) anomaly_counts
        ),
        'recommendations', '["Review system logs", "Check performance metrics", "Investigate root causes", "Implement preventive measures"]'
    ) INTO result;

    RETURN result;
END;
$$ LANGUAGE plpgsql;

-- Function to calculate performance metrics
CREATE OR REPLACE FUNCTION calculate_performance_metrics(p_start_date DATE, p_end_date DATE)
RETURNS JSON AS $$
DECLARE
    result JSON;
BEGIN
    SELECT json_build_object(
        'period', json_build_object('start_date', p_start_date, 'end_date', p_end_date),
        'operational_efficiency', json_build_object(
            'average_check_in_time', (
                SELECT AVG(actual_time)
                FROM operational_efficiency
                WHERE process_category = 'check_in'
                AND measurement_date BETWEEN p_start_date AND p_end_date
            ),
            'boarding_efficiency', (
                SELECT AVG(efficiency_percentage)
                FROM operational_efficiency
                WHERE process_category = 'boarding'
                AND measurement_date BETWEEN p_start_date AND p_end_date
            ),
            'baggage_processing_time', (
                SELECT AVG(actual_time)
                FROM operational_efficiency
                WHERE process_category = 'baggage'
                AND measurement_date BETWEEN p_start_date AND p_end_date
            )
        ),
        'customer_satisfaction', json_build_object(
            'overall_rating', (
                SELECT AVG(metric_value)
                FROM performance_analytics
                WHERE metric_category = 'customer'
                AND metric_name = 'satisfaction_rating'
                AND analytics_date BETWEEN p_start_date AND p_end_date
            ),
            'service_quality_score', (
                SELECT AVG(metric_value)
                FROM performance_analytics
                WHERE metric_category = 'customer'
                AND metric_name = 'service_quality'
                AND analytics_date BETWEEN p_start_date AND p_end_date
            )
        ),
        'financial_performance', json_build_object(
            'revenue_per_passenger', (
                SELECT AVG(metric_value)
                FROM performance_analytics
                WHERE metric_category = 'financial'
                AND metric_name = 'revenue_per_passenger'
                AND analytics_date BETWEEN p_start_date AND p_end_date
            ),
            'cost_efficiency_ratio', (
                SELECT AVG(metric_value)
                FROM performance_analytics
                WHERE metric_category = 'financial'
                AND metric_name = 'cost_efficiency'
                AND analytics_date BETWEEN p_start_date AND p_end_date
            )
        ),
        'safety_metrics', json_build_object(
            'incident_rate', (
                SELECT AVG(metric_value)
                FROM performance_analytics
                WHERE metric_category = 'safety'
                AND metric_name = 'incident_rate'
                AND analytics_date BETWEEN p_start_date AND p_end_date
            ),
            'compliance_score', (
                SELECT AVG(metric_value)
                FROM performance_analytics
                WHERE metric_category = 'safety'
                AND metric_name = 'compliance_score'
                AND analytics_date BETWEEN p_start_date AND p_end_date
            )
        )
    ) INTO result;

    RETURN result;
END;
$$ LANGUAGE plpgsql;

-- Function to generate automated insights
CREATE OR REPLACE FUNCTION generate_automated_insights(p_date DATE)
RETURNS JSON AS $$
DECLARE
    result JSON;
BEGIN
    SELECT json_build_object(
        'insights_date', p_date,
        'operational_insights', (
            SELECT json_agg(
                json_build_object(
                    'title', insight_title,
                    'description', insight_description,
                    'confidence', confidence_level,
                    'impact', impact_level,
                    'recommendations', recommended_actions
                )
            )
            FROM automated_insights
            WHERE insight_type = 'operational'
            AND DATE(insight_date) = p_date
            LIMIT 5
        ),
        'performance_insights', (
            SELECT json_agg(
                json_build_object(
                    'title', insight_title,
                    'description', insight_description,
                    'confidence', confidence_level,
                    'trend', trend_direction
                )
            )
            FROM automated_insights ai
            JOIN performance_analytics pa ON ai.insight_date::date = pa.analytics_date
            WHERE ai.insight_type = 'performance'
            AND DATE(ai.insight_date) = p_date
            LIMIT 5
        ),
        'risk_insights', (
            SELECT json_agg(
                json_build_object(
                    'title', insight_title,
                    'description', insight_description,
                    'risk_level', impact_level,
                    'mitigation', recommended_actions
                )
            )
            FROM automated_insights
            WHERE insight_type = 'risk'
            AND DATE(insight_date) = p_date
            LIMIT 5
        ),
        'opportunity_insights', (
            SELECT json_agg(
                json_build_object(
                    'title', insight_title,
                    'description', insight_description,
                    'potential_impact', impact_level,
                    'implementation', recommended_actions
                )
            )
            FROM automated_insights
            WHERE insight_type = 'opportunity'
            AND DATE(insight_date) = p_date
            LIMIT 5
        )
    ) INTO result;

    RETURN result;
END;
$$ LANGUAGE plpgsql;

-- Function to assess operational risks
CREATE OR REPLACE FUNCTION assess_operational_risks(p_date DATE)
RETURNS JSON AS $$
DECLARE
    result JSON;
BEGIN
    SELECT json_build_object(
        'assessment_date', p_date,
        'risk_summary', json_build_object(
            'total_risks', (
                SELECT COUNT(*)
                FROM risk_assessment
                WHERE DATE(assessment_date) = p_date
            ),
            'high_risk_count', (
                SELECT COUNT(*)
                FROM risk_assessment
                WHERE DATE(assessment_date) = p_date
                AND risk_level IN ('high', 'very_high')
            ),
            'critical_risks', (
                SELECT COUNT(*)
                FROM risk_assessment
                WHERE DATE(assessment_date) = p_date
                AND risk_level = 'very_high'
            )
        ),
        'risk_categories', (
            SELECT json_agg(
                json_build_object(
                    'category', risk_category,
                    'risk_count', risk_count,
                    'avg_score', avg_score
                )
            )
            FROM (
                SELECT
                    risk_category,
                    COUNT(*) as risk_count,
                    AVG(risk_score) as avg_score
                FROM risk_assessment
                WHERE DATE(assessment_date) = p_date
                GROUP BY risk_category
            ) category_risks
        ),
        'top_risks', (
            SELECT json_agg(
                json_build_object(
                    'description', risk_description,
                    'score', risk_score,
                    'level', risk_level,
                    'mitigation', mitigation_strategies
                )
            )
            FROM risk_assessment
            WHERE DATE(assessment_date) = p_date
            ORDER BY risk_score DESC
            LIMIT 5
        ),
        'recommendations', '["Implement risk mitigation strategies", "Enhance monitoring systems", "Develop contingency plans", "Regular risk assessments"]'
    ) INTO result;

    RETURN result;
END;
$$ LANGUAGE plpgsql;

-- Function to optimize revenue based on predictive models
CREATE OR REPLACE FUNCTION optimize_revenue_strategy(p_date DATE, p_segment VARCHAR DEFAULT NULL)
RETURNS JSON AS $$
DECLARE
    result JSON;
BEGIN
    SELECT json_build_object(
        'optimization_date', p_date,
        'target_segment', p_segment,
        'pricing_recommendations', (
            SELECT json_agg(
                json_build_object(
                    'service', optimization_type,
                    'current_price', current_value,
                    'recommended_price', optimized_value,
                    'expected_improvement', improvement_percentage,
                    'confidence', 0.85
                )
            )
            FROM revenue_optimization
            WHERE optimization_date = p_date
            AND optimization_type = 'pricing'
            AND (p_segment IS NULL OR recommended_changes::text LIKE '%' || p_segment || '%')
        ),
        'capacity_optimization', (
            SELECT json_agg(
                json_build_object(
                    'resource', optimization_type,
                    'current_utilization', current_value,
                    'recommended_capacity', optimized_value,
                    'cost_benefit', expected_roi
                )
            )
            FROM revenue_optimization
            WHERE optimization_date = p_date
            AND optimization_type = 'capacity'
        ),
        'service_optimization', (
            SELECT json_agg(
                json_build_object(
                    'service_offering', optimization_type,
                    'current_performance', current_value,
                    'recommended_changes', recommended_changes,
                    'expected_revenue_impact', optimized_value
                )
            )
            FROM revenue_optimization
            WHERE optimization_date = p_date
            AND optimization_type = 'service_offering'
        ),
        'implementation_priority', 'high',
        'expected_roi', (
            SELECT AVG(expected_roi)
            FROM revenue_optimization
            WHERE optimization_date = p_date
        )
    ) INTO result;

    RETURN result;
END;
$$ LANGUAGE plpgsql;

-- Function to get advanced analytics dashboard
CREATE OR REPLACE FUNCTION get_advanced_analytics_dashboard()
RETURNS JSON AS $$
DECLARE
    result JSON;
BEGIN
    SELECT json_build_object(
        'dashboard_date', CURRENT_DATE,
        'predictive_insights', json_build_object(
            'active_models', (
                SELECT COUNT(*)
                FROM predictive_models
                WHERE is_active = true
            ),
            'predictions_today', (
                SELECT COUNT(*)
                FROM model_predictions
                WHERE DATE(prediction_date) = CURRENT_DATE
            ),
            'forecast_accuracy', (
                SELECT AVG(model_accuracy)
                FROM predictive_models
                WHERE is_active = true
            )
        ),
        'demand_forecasting', json_build_object(
            'next_24h_demand', (
                SELECT forecasted_demand
                FROM demand_forecasts
                WHERE forecast_type = 'passenger_demand'
                AND forecast_period_start <= CURRENT_TIMESTAMP + INTERVAL '24 hours'
                ORDER BY forecast_date DESC
                LIMIT 1
            ),
            'forecast_confidence', 0.87,
            'capacity_utilization', 78.5
        ),
        'maintenance_predictions', json_build_object(
            'critical_predictions', (
                SELECT COUNT(*)
                FROM maintenance_predictions
                WHERE risk_category = 'critical'
                AND predicted_failure_date <= CURRENT_DATE + INTERVAL '7 days'
            ),
            'preventive_maintenance_savings', 25000.00,
            'equipment_health_score', 84.2
        ),
        'anomaly_detection', json_build_object(
            'anomalies_today', (
                SELECT COUNT(*)
                FROM anomaly_detection
                WHERE DATE(detection_date) = CURRENT_DATE
            ),
            'unresolved_anomalies', (
                SELECT COUNT(*)
                FROM anomaly_detection
                WHERE investigation_status != 'resolved'
            ),
            'false_positive_rate', 0.05
        ),
        'performance_metrics', json_build_object(
            'operational_efficiency', (
                SELECT AVG(efficiency_percentage)
                FROM operational_efficiency
                WHERE measurement_date >= CURRENT_DATE - INTERVAL '7 days'
            ),
            'customer_satisfaction', (
                SELECT AVG(metric_value)
                FROM performance_analytics
                WHERE metric_category = 'customer'
                AND analytics_date >= CURRENT_DATE - INTERVAL '7 days'
            ),
            'revenue_optimization', (
                SELECT SUM(improvement_percentage)
                FROM revenue_optimization
                WHERE optimization_date >= CURRENT_DATE - INTERVAL '30 days'
                AND implemented = true
            )
        ),
        'automated_insights', (
            SELECT json_agg(
                json_build_object(
                    'type', insight_type,
                    'title', insight_title,
                    'impact', impact_level,
                    'confidence', confidence_level
                )
            )
            FROM automated_insights
            WHERE DATE(insight_date) = CURRENT_DATE
            AND is_reviewed = false
            LIMIT 5
        ),
        'alerts', (
            SELECT json_agg(
                json_build_object(
                    'type', alert_type,
                    'severity', alert_severity,
                    'message', alert_message,
                    'status', alert_status
                )
            )
            FROM monitoring_alerts
            WHERE alert_status = 'active'
            ORDER BY alert_date DESC
            LIMIT 5
        )
    ) INTO result;

    RETURN result;
END;
$$ LANGUAGE plpgsql;
