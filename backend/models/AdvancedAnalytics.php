<?php

/**
 * Advanced Analytics Model
 *
 * Manages predictive models, demand forecasting, AI-driven insights, and automated decision support
 */

class AdvancedAnalytics
{
    private $db;
    private $logger;

    public function __construct()
    {
        $this->db = new PDO(
            "pgsql:host=" . getenv('DB_HOST') . ";dbname=" . getenv('DB_DATABASE'),
            getenv('DB_USERNAME'),
            getenv('DB_PASSWORD'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $this->logger = new Logger('advanced_analytics');
    }

    /**
     * Get predictive models
     */
    public function getPredictiveModels($modelType = null, $activeOnly = true)
    {
        $whereClause = $activeOnly ? "WHERE is_active = true" : "";
        $params = [];

        if ($modelType) {
            $whereClause .= ($activeOnly ? " AND" : "WHERE") . " model_type = ?";
            $params[] = $modelType;
        }

        $stmt = $this->db->prepare("
            SELECT
                pm.*,
                (
                    SELECT COUNT(*)
                    FROM model_predictions mp
                    WHERE mp.model_id = pm.model_id
                    AND DATE(mp.prediction_date) >= CURRENT_DATE - INTERVAL '30 days'
                ) as recent_predictions,
                (
                    SELECT AVG(accuracy)
                    FROM (
                        SELECT
                            CASE
                                WHEN is_accurate IS NOT NULL THEN
                                    CASE WHEN is_accurate THEN 1.0 ELSE 0.0 END
                                ELSE NULL
                            END as accuracy
                        FROM model_predictions
                        WHERE model_id = pm.model_id
                        AND DATE(prediction_date) >= CURRENT_DATE - INTERVAL '30 days'
                        LIMIT 100
                    ) accuracy_data
                ) as recent_accuracy
            FROM predictive_models pm
            $whereClause
            ORDER BY pm.model_type, pm.model_accuracy DESC
        ");

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Generate demand forecast
     */
    public function generateDemandForecast($forecastType, $startDate, $endDate, $modelId = null)
    {
        $this->logger->info("Generating demand forecast", [
            'forecast_type' => $forecastType,
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);

        // Use specified model or find best available
        if (!$modelId) {
            $stmt = $this->db->prepare("
                SELECT model_id FROM predictive_models
                WHERE model_type = 'demand_forecasting'
                AND is_active = true
                ORDER BY model_accuracy DESC
                LIMIT 1
            ");
            $stmt->execute();
            $model = $stmt->fetch(PDO::FETCH_ASSOC);
            $modelId = $model ? $model['model_id'] : null;
        }

        // Generate forecast (simplified - in production this would call ML service)
        $forecastedDemand = $this->calculateForecastedDemand($forecastType, $startDate, $endDate);

        // Save forecast
        $stmt = $this->db->prepare("
            INSERT INTO demand_forecasts (
                forecast_date, forecast_type, forecast_period_start,
                forecast_period_end, forecasted_demand, confidence_interval_lower,
                confidence_interval_upper, forecast_accuracy, model_used
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            date('Y-m-d'),
            $forecastType,
            $startDate,
            $endDate,
            $forecastedDemand,
            $forecastedDemand * 0.9, // 10% lower bound
            $forecastedDemand * 1.1, // 10% upper bound
            0.85, // forecast accuracy
            $modelId
        ]);

        return [
            'forecast_id' => $this->db->lastInsertId(),
            'forecast_type' => $forecastType,
            'forecasted_demand' => $forecastedDemand,
            'confidence_interval' => [$forecastedDemand * 0.9, $forecastedDemand * 1.1],
            'model_used' => $modelId
        ];
    }

    /**
     * Get demand forecasts
     */
    public function getDemandForecasts($forecastType = null, $startDate = null, $endDate = null)
    {
        $whereClause = "";
        $params = [];

        if ($forecastType) {
            $whereClause .= " AND forecast_type = ?";
            $params[] = $forecastType;
        }

        if ($startDate) {
            $whereClause .= " AND forecast_period_start >= ?";
            $params[] = $startDate;
        }

        if ($endDate) {
            $whereClause .= " AND forecast_period_end <= ?";
            $params[] = $endDate;
        }

        $stmt = $this->db->prepare("
            SELECT df.*, pm.model_name, pm.model_accuracy
            FROM demand_forecasts df
            LEFT JOIN predictive_models pm ON df.model_used = pm.model_id
            WHERE 1=1 $whereClause
            ORDER BY df.forecast_date DESC, df.forecast_period_start ASC
        ");

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Record model prediction
     */
    public function recordModelPrediction($predictionData)
    {
        $this->logger->info("Recording model prediction", $predictionData);

        $stmt = $this->db->prepare("
            INSERT INTO model_predictions (
                model_id, input_features, predicted_value,
                prediction_confidence, actual_value, prediction_error,
                prediction_category, is_accurate
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $predictionError = isset($predictionData['actual_value']) ?
            abs($predictionData['predicted_value'] - $predictionData['actual_value']) : null;

        $isAccurate = $predictionError !== null ?
            ($predictionError / $predictionData['predicted_value'] <= 0.1) : null; // 10% tolerance

        $stmt->execute([
            $predictionData['model_id'],
            json_encode($predictionData['input_features']),
            $predictionData['predicted_value'],
            $predictionData['prediction_confidence'] ?? null,
            $predictionData['actual_value'] ?? null,
            $predictionError,
            $this->categorizePrediction($predictionData['prediction_confidence'] ?? 0),
            $isAccurate
        ]);

        return [
            'prediction_id' => $this->db->lastInsertId(),
            'model_id' => $predictionData['model_id'],
            'predicted_value' => $predictionData['predicted_value'],
            'is_accurate' => $isAccurate
        ];
    }

    /**
     * Get maintenance predictions
     */
    public function getMaintenancePredictions($equipmentType = null, $riskCategory = null)
    {
        $whereClause = "";
        $params = [];

        if ($equipmentType) {
            $whereClause .= " AND equipment_type = ?";
            $params[] = $equipmentType;
        }

        if ($riskCategory) {
            $whereClause .= " AND risk_category = ?";
            $params[] = $riskCategory;
        }

        $stmt = $this->db->prepare("
            SELECT
                mp.*,
                pm.model_name,
                CASE
                    WHEN mp.predicted_failure_date <= CURRENT_DATE + INTERVAL '7 days' THEN 'urgent'
                    WHEN mp.predicted_failure_date <= CURRENT_DATE + INTERVAL '30 days' THEN 'warning'
                    ELSE 'normal'
                END as urgency_level
            FROM maintenance_predictions mp
            LEFT JOIN predictive_models pm ON mp.model_used = pm.model_id
            WHERE mp.predicted_failure_date >= CURRENT_DATE $whereClause
            ORDER BY mp.failure_probability DESC, mp.predicted_failure_date ASC
        ");

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Generate maintenance prediction
     */
    public function generateMaintenancePrediction($equipmentData)
    {
        $this->logger->info("Generating maintenance prediction", $equipmentData);

        // Simplified prediction logic - in production this would use ML model
        $failureProbability = $this->calculateFailureProbability($equipmentData);
        $predictedFailureDate = $this->calculatePredictedFailureDate($equipmentData, $failureProbability);
        $riskCategory = $this->determineRiskCategory($failureProbability, $predictedFailureDate);

        $stmt = $this->db->prepare("
            INSERT INTO maintenance_predictions (
                equipment_type, equipment_id, failure_probability,
                predicted_failure_date, confidence_level, risk_category,
                recommended_action, estimated_cost
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $equipmentData['equipment_type'],
            $equipmentData['equipment_id'],
            $failureProbability,
            $predictedFailureDate,
            0.8, // confidence level
            $riskCategory,
            $this->getRecommendedAction($riskCategory),
            $this->estimateMaintenanceCost($equipmentData['equipment_type'], $riskCategory)
        ]);

        return [
            'prediction_id' => $this->db->lastInsertId(),
            'equipment_id' => $equipmentData['equipment_id'],
            'failure_probability' => $failureProbability,
            'predicted_failure_date' => $predictedFailureDate,
            'risk_category' => $riskCategory
        ];
    }

    /**
     * Get revenue optimization recommendations
     */
    public function getRevenueOptimizations($startDate = null, $endDate = null, $optimizationType = null)
    {
        $whereClause = "";
        $params = [];

        if ($startDate) {
            $whereClause .= " AND optimization_date >= ?";
            $params[] = $startDate;
        }

        if ($endDate) {
            $whereClause .= " AND optimization_date <= ?";
            $params[] = $endDate;
        }

        if ($optimizationType) {
            $whereClause .= " AND optimization_type = ?";
            $params[] = $optimizationType;
        }

        $stmt = $this->db->prepare("
            SELECT
                ro.*,
                pm.model_name,
                CASE
                    WHEN ro.implemented THEN 'implemented'
                    WHEN ro.implementation_priority = 'high' THEN 'high_priority'
                    WHEN ro.expected_roi > 3.0 THEN 'high_roi'
                    ELSE 'normal'
                END as recommendation_status
            FROM revenue_optimization ro
            LEFT JOIN predictive_models pm ON ro.model_used = pm.model_id
            WHERE 1=1 $whereClause
            ORDER BY ro.expected_roi DESC, ro.optimization_date DESC
        ");

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Generate revenue optimization recommendation
     */
    public function generateRevenueOptimization($optimizationData)
    {
        $this->logger->info("Generating revenue optimization", $optimizationData);

        // Simplified optimization logic - in production this would use ML model
        $optimizedValue = $this->calculateOptimizedValue($optimizationData);
        $improvementPercentage = (($optimizedValue - $optimizationData['current_value']) / $optimizationData['current_value']) * 100;
        $expectedRoi = $this->calculateExpectedROI($optimizationData['optimization_type'], $improvementPercentage);

        $stmt = $this->db->prepare("
            INSERT INTO revenue_optimization (
                optimization_date, optimization_type, target_metric,
                current_value, optimized_value, improvement_percentage,
                recommended_changes, expected_roi, implementation_priority
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            date('Y-m-d'),
            $optimizationData['optimization_type'],
            $optimizationData['target_metric'],
            $optimizationData['current_value'],
            $optimizedValue,
            $improvementPercentage,
            json_encode($optimizationData['recommended_changes'] ?? []),
            $expectedRoi,
            $this->determineImplementationPriority($expectedRoi, $improvementPercentage)
        ]);

        return [
            'optimization_id' => $this->db->lastInsertId(),
            'optimization_type' => $optimizationData['optimization_type'],
            'improvement_percentage' => $improvementPercentage,
            'expected_roi' => $expectedRoi
        ];
    }

    /**
     * Detect operational anomalies
     */
    public function detectAnomalies($system = null, $severity = null)
    {
        $whereClause = "";
        $params = [];

        if ($system) {
            $whereClause .= " AND affected_system = ?";
            $params[] = $system;
        }

        if ($severity) {
            $whereClause .= " AND severity_level = ?";
            $params[] = $severity;
        }

        $stmt = $this->db->prepare("
            SELECT
                ad.*,
                pm.model_name,
                CASE
                    WHEN ad.investigation_status = 'pending' THEN 'needs_attention'
                    WHEN ad.severity_level = 'critical' THEN 'critical'
                    WHEN ad.confidence_score > 0.8 THEN 'high_confidence'
                    ELSE 'normal'
                END as priority_level
            FROM anomaly_detection ad
            LEFT JOIN predictive_models pm ON ad.model_used = pm.model_id
            WHERE DATE(ad.detection_date) >= CURRENT_DATE - INTERVAL '7 days' $whereClause
            ORDER BY ad.confidence_score DESC, ad.detection_date DESC
        ");

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Record anomaly detection
     */
    public function recordAnomaly($anomalyData)
    {
        $this->logger->info("Recording anomaly detection", $anomalyData);

        $stmt = $this->db->prepare("
            INSERT INTO anomaly_detection (
                anomaly_type, severity_level, affected_system,
                anomaly_description, root_cause, impact_assessment,
                recommended_actions, confidence_score, false_positive
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $anomalyData['anomaly_type'],
            $anomalyData['severity_level'] ?? 'medium',
            $anomalyData['affected_system'],
            $anomalyData['anomaly_description'],
            $anomalyData['root_cause'] ?? null,
            $anomalyData['impact_assessment'] ?? null,
            json_encode($anomalyData['recommended_actions'] ?? []),
            $anomalyData['confidence_score'] ?? 0.8,
            $anomalyData['false_positive'] ?? false
        ]);

        return [
            'anomaly_id' => $this->db->lastInsertId(),
            'anomaly_type' => $anomalyData['anomaly_type'],
            'severity_level' => $anomalyData['severity_level']
        ];
    }

    /**
     * Get performance analytics
     */
    public function getPerformanceAnalytics($startDate, $endDate, $category = null)
    {
        $whereClause = "WHERE analytics_date BETWEEN ? AND ?";
        $params = [$startDate, $endDate];

        if ($category) {
            $whereClause .= " AND metric_category = ?";
            $params[] = $category;
        }

        $stmt = $this->db->prepare("
            SELECT
                pa.*,
                LAG(pa.metric_value) OVER (
                    PARTITION BY pa.metric_name
                    ORDER BY pa.analytics_date
                ) as previous_value,
                CASE
                    WHEN LAG(pa.metric_value) OVER (
                        PARTITION BY pa.metric_name
                        ORDER BY pa.analytics_date
                    ) > 0 THEN
                        ROUND(
                            ((pa.metric_value - LAG(pa.metric_value) OVER (
                                PARTITION BY pa.metric_name
                                ORDER BY pa.analytics_date
                            )) / LAG(pa.metric_value) OVER (
                                PARTITION BY pa.metric_name
                                ORDER BY pa.analytics_date
                            )) * 100, 2
                        )
                    ELSE 0
                END as percent_change
            FROM performance_analytics pa
            $whereClause
            ORDER BY pa.analytics_date DESC, pa.metric_category, pa.metric_name
        ");

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Record performance metric
     */
    public function recordPerformanceMetric($metricData)
    {
        $stmt = $this->db->prepare("
            INSERT INTO performance_analytics (
                analytics_date, metric_category, metric_name,
                metric_value, target_value, variance_percentage,
                trend_direction, benchmark_value, influencing_factors, recommendations
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $variancePercentage = $metricData['target_value'] > 0 ?
            (($metricData['metric_value'] - $metricData['target_value']) / $metricData['target_value']) * 100 : 0;

        $stmt->execute([
            $metricData['analytics_date'] ?? date('Y-m-d'),
            $metricData['metric_category'],
            $metricData['metric_name'],
            $metricData['metric_value'],
            $metricData['target_value'] ?? null,
            $variancePercentage,
            $this->determineTrendDirection($metricData['metric_value'], $metricData['previous_value'] ?? null),
            $metricData['benchmark_value'] ?? null,
            json_encode($metricData['influencing_factors'] ?? []),
            json_encode($metricData['recommendations'] ?? [])
        ]);

        return [
            'analytics_id' => $this->db->lastInsertId(),
            'metric_name' => $metricData['metric_name'],
            'metric_value' => $metricData['metric_value']
        ];
    }

    /**
     * Get automated insights
     */
    public function getAutomatedInsights($insightType = null, $reviewed = null)
    {
        $whereClause = "";
        $params = [];

        if ($insightType) {
            $whereClause .= " AND insight_type = ?";
            $params[] = $insightType;
        }

        if ($reviewed !== null) {
            $whereClause .= " AND is_reviewed = ?";
            $params[] = $reviewed;
        }

        $stmt = $this->db->prepare("
            SELECT
                ai.*,
                pm.model_name,
                CASE
                    WHEN ai.confidence_level > 0.8 AND ai.impact_level = 'high' THEN 'critical'
                    WHEN ai.confidence_level > 0.7 THEN 'high'
                    WHEN ai.impact_level IN ('medium', 'high') THEN 'medium'
                    ELSE 'low'
                END as priority_score
            FROM automated_insights ai
            LEFT JOIN predictive_models pm ON ai.model_used = pm.model_id
            WHERE DATE(ai.insight_date) >= CURRENT_DATE - INTERVAL '30 days' $whereClause
            ORDER BY ai.confidence_level DESC, ai.impact_level DESC, ai.insight_date DESC
        ");

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Generate automated insight
     */
    public function generateInsight($insightData)
    {
        $this->logger->info("Generating automated insight", $insightData);

        $stmt = $this->db->prepare("
            INSERT INTO automated_insights (
                insight_type, insight_title, insight_description,
                confidence_level, impact_level, affected_areas,
                recommended_actions, data_sources
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $insightData['insight_type'],
            $insightData['insight_title'],
            $insightData['insight_description'],
            $insightData['confidence_level'] ?? 0.8,
            $insightData['impact_level'] ?? 'medium',
            json_encode($insightData['affected_areas'] ?? []),
            json_encode($insightData['recommended_actions'] ?? []),
            json_encode($insightData['data_sources'] ?? [])
        ]);

        return [
            'insight_id' => $this->db->lastInsertId(),
            'insight_type' => $insightData['insight_type'],
            'insight_title' => $insightData['insight_title']
        ];
    }

    /**
     * Get monitoring alerts
     */
    public function getMonitoringAlerts($status = 'active', $severity = null)
    {
        $whereClause = "WHERE alert_status = ?";
        $params = [$status];

        if ($severity) {
            $whereClause .= " AND alert_severity = ?";
            $params[] = $severity;
        }

        $stmt = $this->db->prepare("
            SELECT
                ma.*,
                pm.model_name,
                CASE
                    WHEN ma.alert_severity = 'critical' THEN 5
                    WHEN ma.alert_severity = 'high' THEN 4
                    WHEN ma.alert_severity = 'medium' THEN 3
                    WHEN ma.alert_severity = 'low' THEN 2
                    ELSE 1
                END as priority_score
            FROM monitoring_alerts ma
            LEFT JOIN predictive_models pm ON ma.model_used = pm.model_id
            $whereClause
            ORDER BY priority_score DESC, ma.alert_date DESC
        ");

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Create monitoring alert
     */
    public function createMonitoringAlert($alertData)
    {
        $this->logger->info("Creating monitoring alert", $alertData);

        $stmt = $this->db->prepare("
            INSERT INTO monitoring_alerts (
                alert_type, alert_severity, alert_message,
                affected_system, threshold_breached, current_value,
                threshold_value, auto_generated
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $alertData['alert_type'],
            $alertData['alert_severity'] ?? 'medium',
            $alertData['alert_message'],
            $alertData['affected_system'] ?? null,
            $alertData['threshold_breached'] ?? null,
            $alertData['current_value'] ?? null,
            $alertData['threshold_value'] ?? null,
            $alertData['auto_generated'] ?? true
        ]);

        return [
            'alert_id' => $this->db->lastInsertId(),
            'alert_type' => $alertData['alert_type'],
            'alert_severity' => $alertData['alert_severity']
        ];
    }

    /**
     * Get risk assessments
     */
    public function getRiskAssessments($category = null, $riskLevel = null)
    {
        $whereClause = "";
        $params = [];

        if ($category) {
            $whereClause .= " AND risk_category = ?";
            $params[] = $category;
        }

        if ($riskLevel) {
            $whereClause .= " AND risk_level = ?";
            $params[] = $riskLevel;
        }

        $stmt = $this->db->prepare("
            SELECT
                ra.*,
                pm.model_name,
                CASE
                    WHEN ra.risk_level = 'very_high' THEN 5
                    WHEN ra.risk_level = 'high' THEN 4
                    WHEN ra.risk_level = 'medium' THEN 3
                    WHEN ra.risk_level = 'low' THEN 2
                    ELSE 1
                END as priority_score
            FROM risk_assessment ra
            LEFT JOIN predictive_models pm ON ra.model_used = pm.model_id
            WHERE DATE(ra.assessment_date) >= CURRENT_DATE - INTERVAL '90 days' $whereClause
            ORDER BY ra.risk_score DESC, ra.assessment_date DESC
        ");

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Create risk assessment
     */
    public function createRiskAssessment($riskData)
    {
        $this->logger->info("Creating risk assessment", $riskData);

        $riskScore = ($riskData['risk_probability'] ?? 0) * ($riskData['risk_impact'] ?? 0);
        $riskLevel = $this->determineRiskLevel($riskScore);

        $stmt = $this->db->prepare("
            INSERT INTO risk_assessment (
                risk_category, risk_description, risk_probability,
                risk_impact, risk_score, risk_level, mitigation_strategies,
                responsible_party, monitoring_frequency
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $riskData['risk_category'],
            $riskData['risk_description'],
            $riskData['risk_probability'] ?? 0,
            $riskData['risk_impact'] ?? 0,
            $riskScore,
            $riskLevel,
            json_encode($riskData['mitigation_strategies'] ?? []),
            $riskData['responsible_party'] ?? null,
            $riskData['monitoring_frequency'] ?? 'weekly'
        ]);

        return [
            'assessment_id' => $this->db->lastInsertId(),
            'risk_category' => $riskData['risk_category'],
            'risk_score' => $riskScore,
            'risk_level' => $riskLevel
        ];
    }

    /**
     * Get advanced analytics dashboard data
     */
    public function getDashboardData()
    {
        $stmt = $this->db->prepare("
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
                        SELECT ROUND(AVG(model_accuracy), 3)
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
                        SELECT ROUND(AVG(efficiency_percentage), 2)
                        FROM operational_efficiency
                        WHERE measurement_date >= CURRENT_DATE - INTERVAL '7 days'
                    ),
                    'customer_satisfaction', (
                        SELECT ROUND(AVG(metric_value), 2)
                        FROM performance_analytics
                        WHERE metric_category = 'customer'
                        AND analytics_date >= CURRENT_DATE - INTERVAL '7 days'
                    ),
                    'revenue_optimization', (
                        SELECT ROUND(SUM(improvement_percentage), 2)
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
                            'confidence', ROUND(confidence_level, 2)
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
            ) as dashboard_data
        ");

        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return json_decode($result['dashboard_data'], true);
    }

    // Helper methods

    private function calculateForecastedDemand($forecastType, $startDate, $endDate)
    {
        // Simplified forecasting logic - in production this would use ML models
        $baseDemand = [
            'passenger_demand' => 2500,
            'cargo_demand' => 150,
            'service_demand' => 450
        ];

        $demand = $baseDemand[$forecastType] ?? 1000;

        // Add some variation based on time
        $hour = date('H', strtotime($startDate));
        if ($hour >= 6 && $hour <= 10) $demand *= 1.2; // Morning peak
        if ($hour >= 16 && $hour <= 20) $demand *= 1.1; // Evening peak

        return round($demand);
    }

    private function categorizePrediction($confidence)
    {
        if ($confidence >= 0.8) return 'high';
        if ($confidence >= 0.6) return 'medium';
        return 'low';
    }

    private function calculateFailureProbability($equipmentData)
    {
        // Simplified calculation - in production this would use ML model
        $baseProbability = 0.1; // 10% base failure rate

        // Adjust based on factors
        if (($equipmentData['usage_hours'] ?? 0) > 1000) $baseProbability += 0.1;
        if (($equipmentData['temperature'] ?? 20) > 30) $baseProbability += 0.05;
        if (($equipmentData['age'] ?? 0) > 365) $baseProbability += 0.05; // Age in days

        return min($baseProbability, 0.9); // Cap at 90%
    }

    private function calculatePredictedFailureDate($equipmentData, $failureProbability)
    {
        // Simplified prediction - in production this would use ML model
        $daysUntilFailure = (1 - $failureProbability) * 365; // Convert probability to days
        return date('Y-m-d', strtotime("+{$daysUntilFailure} days"));
    }

    private function determineRiskCategory($failureProbability, $predictedFailureDate)
    {
        $daysUntilFailure = (strtotime($predictedFailureDate) - time()) / (60 * 60 * 24);

        if ($failureProbability > 0.7 || $daysUntilFailure < 7) return 'critical';
        if ($failureProbability > 0.5 || $daysUntilFailure < 30) return 'high';
        if ($failureProbability > 0.3 || $daysUntilFailure < 90) return 'medium';
        return 'low';
    }

    private function getRecommendedAction($riskCategory)
    {
        $actions = [
            'critical' => 'Immediate inspection and replacement required',
            'high' => 'Schedule preventive maintenance within 7 days',
            'medium' => 'Monitor closely and schedule maintenance within 30 days',
            'low' => 'Continue regular monitoring'
        ];

        return $actions[$riskCategory] ?? 'Monitor equipment condition';
    }

    private function estimateMaintenanceCost($equipmentType, $riskCategory)
    {
        $baseCosts = [
            'conveyor_belt' => 2500,
            'check_in_kiosk' => 800,
            'security_scanner' => 1500,
            'wheelchair' => 300,
            'elevator' => 5000
        ];

        $multipliers = [
            'critical' => 1.5,
            'high' => 1.2,
            'medium' => 1.0,
            'low' => 0.8
        ];

        $baseCost = $baseCosts[$equipmentType] ?? 1000;
        $multiplier = $multipliers[$riskCategory] ?? 1.0;

        return round($baseCost * $multiplier);
    }

    private function calculateOptimizedValue($optimizationData)
    {
        // Simplified optimization - in production this would use ML model
        $currentValue = $optimizationData['current_value'];
        $improvementFactor = 1.0;

        switch ($optimizationData['optimization_type']) {
            case 'pricing':
                $improvementFactor = 1.08; // 8% price increase
                break;
            case 'capacity':
                $improvementFactor = 1.12; // 12% capacity utilization increase
                break;
            case 'service_offering':
                $improvementFactor = 1.15; // 15% service improvement
                break;
            default:
                $improvementFactor = 1.05; // 5% default improvement
        }

        return round($currentValue * $improvementFactor);
    }

    private function calculateExpectedROI($optimizationType, $improvementPercentage)
    {
        // Simplified ROI calculation
        $baseROI = $improvementPercentage * 0.8; // 80% of improvement becomes ROI
        return round($baseROI, 2);
    }

    private function determineImplementationPriority($expectedRoi, $improvementPercentage)
    {
        if ($expectedRoi > 4.0 || $improvementPercentage > 15) return 'high';
        if ($expectedRoi > 2.0 || $improvementPercentage > 8) return 'medium';
        return 'low';
    }

    private function determineTrendDirection($currentValue, $previousValue)
    {
        if (!$previousValue) return 'stable';

        $change = (($currentValue - $previousValue) / $previousValue) * 100;

        if ($change > 5) return 'up';
        if ($change < -5) return 'down';
        return 'stable';
    }

    private function determineRiskLevel($riskScore)
    {
        if ($riskScore >= 0.7) return 'very_high';
        if ($riskScore >= 0.5) return 'high';
        if ($riskScore >= 0.3) return 'medium';
        if ($riskScore >= 0.1) return 'low';
        return 'very_low';
    }
}
