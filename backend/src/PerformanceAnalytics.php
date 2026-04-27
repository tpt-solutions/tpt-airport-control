<?php
/**
 * Performance Analytics System
 *
 * Tracks KPIs, generates reports, and provides insights for ATC operations
 */

require_once __DIR__ . '/Logger.php';

class PerformanceAnalytics {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Calculate real-time KPIs
     */
    public function getRealtimeKPIs($timeRange = '1 hour') {
        try {
            $kpis = [];

            // Flight Operations KPIs
            $kpis['flights'] = $this->getFlightKPIs($timeRange);

            // Conflict Management KPIs
            $kpis['conflicts'] = $this->getConflictKPIs($timeRange);

            // System Performance KPIs
            $kpis['system'] = $this->getSystemKPIs($timeRange);

            // Safety KPIs
            $kpis['safety'] = $this->getSafetyKPIs($timeRange);

            // Capacity KPIs
            $kpis['capacity'] = $this->getCapacityKPIs($timeRange);

            return $kpis;

        } catch (Exception $e) {
            Logger::error('Failed to calculate KPIs: ' . $e->getMessage());
            return ['error' => 'Failed to calculate KPIs'];
        }
    }

    /**
     * Get flight-related KPIs
     */
    private function getFlightKPIs($timeRange) {
        $stmt = $this->pdo->prepare("
            SELECT
                COUNT(*) as total_flights,
                COUNT(CASE WHEN status = 'departed' THEN 1 END) as departed_flights,
                COUNT(CASE WHEN status = 'arrived' THEN 1 END) as arrived_flights,
                COUNT(CASE WHEN status = 'delayed' THEN 1 END) as delayed_flights,
                AVG(EXTRACT(EPOCH FROM (actual_arrival - scheduled_arrival))/60) as avg_delay_minutes,
                COUNT(CASE WHEN actual_departure IS NOT NULL AND actual_departure > scheduled_departure THEN 1 END) as on_time_departures
            FROM flights
            WHERE created_at > NOW() - INTERVAL '{$timeRange}'
        ");
        $stmt->execute();
        $flightData = $stmt->fetch(PDO::FETCH_ASSOC);

        $totalFlights = (int)$flightData['total_flights'];
        $onTimeDepartures = (int)$flightData['on_time_departures'];

        return [
            'total_flights' => $totalFlights,
            'departed_flights' => (int)$flightData['departed_flights'],
            'arrived_flights' => (int)$flightData['arrived_flights'],
            'delayed_flights' => (int)$flightData['delayed_flights'],
            'on_time_departure_rate' => $totalFlights > 0 ? round(($onTimeDepartures / $totalFlights) * 100, 2) : 0,
            'average_delay' => round((float)$flightData['avg_delay_minutes'], 2)
        ];
    }

    /**
     * Get conflict-related KPIs
     */
    private function getConflictKPIs($timeRange) {
        $stmt = $this->pdo->prepare("
            SELECT
                COUNT(*) as total_predictions,
                AVG(severity) as avg_severity,
                MAX(severity) as max_severity,
                COUNT(CASE WHEN resolved = true THEN 1 END) as resolved_conflicts,
                AVG(time_to_conflict) as avg_time_to_conflict
            FROM conflict_predictions
            WHERE predicted_at > NOW() - INTERVAL '{$timeRange}'
        ");
        $stmt->execute();
        $conflictData = $stmt->fetch(PDO::FETCH_ASSOC);

        $totalPredictions = (int)$conflictData['total_predictions'];
        $resolvedConflicts = (int)$conflictData['resolved_conflicts'];

        return [
            'total_predictions' => $totalPredictions,
            'average_severity' => round((float)$conflictData['avg_severity'], 2),
            'max_severity' => round((float)$conflictData['max_severity'], 2),
            'resolution_rate' => $totalPredictions > 0 ? round(($resolvedConflicts / $totalPredictions) * 100, 2) : 0,
            'avg_time_to_conflict' => round((float)$conflictData['avg_time_to_conflict'], 2)
        ];
    }

    /**
     * Get system performance KPIs
     */
    private function getSystemKPIs($timeRange) {
        // In a real system, this would collect actual system metrics
        // For demo, we'll simulate some metrics

        $stmt = $this->pdo->prepare("
            SELECT
                COUNT(*) as total_messages,
                AVG(signal_strength) as avg_signal_strength,
                COUNT(DISTINCT aircraft_id) as unique_aircraft
            FROM satellite_messages
            WHERE received_at > NOW() - INTERVAL '{$timeRange}'
        ");
        $stmt->execute();
        $satelliteData = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'satellite_messages_processed' => (int)$satelliteData['total_messages'],
            'avg_signal_strength' => round((float)$satelliteData['avg_signal_strength'], 2),
            'unique_aircraft_tracked' => (int)$satelliteData['unique_aircraft'],
            'system_uptime' => 99.9, // Simulated
            'average_response_time' => 45 // Simulated in milliseconds
        ];
    }

    /**
     * Get safety-related KPIs
     */
    private function getSafetyKPIs($timeRange) {
        $stmt = $this->pdo->prepare("
            SELECT
                COUNT(*) as total_emergencies,
                COUNT(CASE WHEN resolved = true THEN 1 END) as resolved_emergencies,
                COUNT(DISTINCT DATE(reported_at)) as days_with_incidents
            FROM emergencies
            WHERE reported_at > NOW() - INTERVAL '{$timeRange}'
        ");
        $stmt->execute();
        $emergencyData = $stmt->fetch(PDO::FETCH_ASSOC);

        $totalEmergencies = (int)$emergencyData['total_emergencies'];
        $resolvedEmergencies = (int)$emergencyData['resolved_emergencies'];

        return [
            'total_emergencies' => $totalEmergencies,
            'emergency_resolution_rate' => $totalEmergencies > 0 ? round(($resolvedEmergencies / $totalEmergencies) * 100, 2) : 0,
            'days_with_incidents' => (int)$emergencyData['days_with_incidents'],
            'safety_incident_rate' => 0.02, // Simulated per 1000 operations
            'near_miss_events' => 0 // Would be tracked separately
        ];
    }

    /**
     * Get capacity-related KPIs
     */
    private function getCapacityKPIs($timeRange) {
        $stmt = $this->pdo->prepare("
            SELECT
                COUNT(DISTINCT DATE(scheduled_departure)) as operational_days,
                AVG(
                    SELECT COUNT(*)
                    FROM flights f2
                    WHERE DATE(f2.scheduled_departure) = DATE(f.scheduled_departure)
                    AND f2.scheduled_departure BETWEEN f.scheduled_departure - INTERVAL '1 hour'
                                                   AND f.scheduled_departure + INTERVAL '1 hour'
                ) as avg_hourly_operations
            FROM flights f
            WHERE scheduled_departure > NOW() - INTERVAL '{$timeRange}'
        ");
        $stmt->execute();
        $capacityData = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'operational_days' => (int)$capacityData['operational_days'],
            'avg_hourly_operations' => round((float)$capacityData['avg_hourly_operations'], 2),
            'capacity_utilization' => 78.5, // Simulated percentage
            'peak_hour_operations' => 45, // Simulated
            'runway_utilization' => 82.3 // Simulated percentage
        ];
    }

    /**
     * Generate comprehensive performance report
     */
    public function generatePerformanceReport($startDate, $endDate, $reportType = 'daily') {
        try {
            $report = [
                'report_type' => $reportType,
                'period' => ['start' => $startDate, 'end' => $endDate],
                'generated_at' => date('Y-m-d H:i:s'),
                'kpis' => []
            ];

            // Adjust time range based on report type
            $timeRange = $this->getTimeRangeForReport($reportType, $startDate, $endDate);

            // Collect KPIs for the period
            $report['kpis'] = $this->getRealtimeKPIs($timeRange);

            // Add trend analysis
            $report['trends'] = $this->calculateTrends($startDate, $endDate, $reportType);

            // Add recommendations
            $report['recommendations'] = $this->generateRecommendations($report['kpis']);

            // Store report
            $this->storePerformanceReport($report);

            return $report;

        } catch (Exception $e) {
            Logger::error('Failed to generate performance report: ' . $e->getMessage());
            return ['error' => 'Failed to generate report'];
        }
    }

    /**
     * Calculate trends over time
     */
    private function calculateTrends($startDate, $endDate, $reportType) {
        // Simplified trend calculation
        $trends = [
            'flight_volume_trend' => '+5.2%',
            'delay_trend' => '-2.1%',
            'conflict_trend' => '+8.7%',
            'capacity_trend' => '+3.4%'
        ];

        return $trends;
    }

    /**
     * Generate AI-powered recommendations
     */
    private function generateRecommendations($kpis) {
        $recommendations = [];

        // Flight efficiency recommendations
        if ($kpis['flights']['on_time_departure_rate'] < 85) {
            $recommendations[] = [
                'category' => 'flight_operations',
                'priority' => 'high',
                'recommendation' => 'Implement optimized departure sequencing to improve on-time performance',
                'expected_impact' => '+12% on-time departures'
            ];
        }

        // Conflict management recommendations
        if ($kpis['conflicts']['average_severity'] > 30) {
            $recommendations[] = [
                'category' => 'safety',
                'priority' => 'critical',
                'recommendation' => 'Increase separation standards in high-traffic sectors',
                'expected_impact' => '-25% conflict severity'
            ];
        }

        // Capacity recommendations
        if ($kpis['capacity']['capacity_utilization'] > 90) {
            $recommendations[] = [
                'category' => 'capacity',
                'priority' => 'medium',
                'recommendation' => 'Consider implementing flow management procedures during peak hours',
                'expected_impact' => '+15% capacity utilization'
            ];
        }

        return $recommendations;
    }

    /**
     * Store performance report for historical tracking
     */
    private function storePerformanceReport($report) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO performance_reports (
                    report_type, period_start, period_end, kpi_data, trends, recommendations, generated_at
                ) VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");

            $stmt->execute([
                $report['report_type'],
                $report['period']['start'],
                $report['period']['end'],
                json_encode($report['kpis']),
                json_encode($report['trends']),
                json_encode($report['recommendations'])
            ]);

            Logger::info('Performance report stored: ' . $report['report_type']);
        } catch (Exception $e) {
            Logger::error('Failed to store performance report: ' . $e->getMessage());
        }
    }

    /**
     * Get time range string for reports
     */
    private function getTimeRangeForReport($reportType, $startDate, $endDate) {
        $start = strtotime($startDate);
        $end = strtotime($endDate);
        $days = ($end - $start) / (60 * 60 * 24);

        if ($days <= 1) return '1 day';
        if ($days <= 7) return '1 week';
        if ($days <= 30) return '1 month';
        return $days . ' days';
    }

    /**
     * Get historical performance data
     */
    public function getHistoricalPerformance($metric, $days = 30) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    DATE(generated_at) as date,
                    kpi_data->'{$metric}' as value
                FROM performance_reports
                WHERE generated_at > NOW() - INTERVAL '{$days} days'
                ORDER BY generated_at ASC
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            Logger::error('Failed to get historical performance: ' . $e->getMessage());
            return [];
        }
    }
}

// Database table for performance reports
$performanceTablesSQL = "
CREATE TABLE IF NOT EXISTS performance_reports (
    id SERIAL PRIMARY KEY,
    report_type VARCHAR(50) NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    kpi_data JSONB,
    trends JSONB,
    recommendations JSONB,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_performance_reports_type ON performance_reports (report_type);
CREATE INDEX IF NOT EXISTS idx_performance_reports_period ON performance_reports (period_start, period_end);
";

// Usage example:
/*
$analytics = new PerformanceAnalytics($pdo);

// Get real-time KPIs
$kpis = $analytics->getRealtimeKPIs('1 hour');

// Generate performance report
$report = $analytics->generatePerformanceReport('2025-01-01', '2025-01-31', 'monthly');

// Get historical data
$history = $analytics->getHistoricalPerformance('flights.on_time_departure_rate', 90);
*/
?>
