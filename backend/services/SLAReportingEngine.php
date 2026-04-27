<?php
/**
 * TPT Flight Control System
 * SLA Reporting Engine
 * 
 * Tracks service level agreement compliance and generates formal reports
 */

declare(strict_types=1);

use TPT\FlightControl\Config\Database;

class SLAReportingEngine
{
    const SLA_AVAILABILITY_TARGET = 99.9;
    const SLA_RESPONSE_TIME_TARGET = 100; // milliseconds
    const SLA_UPTIME_WINDOW = 2592000; // 30 days

    public static function calculateAvailability(int $period = 2592000): array
    {
        $db = Database::getConnection();
        
        $endTime = time();
        $startTime = $endTime - $period;

        $stmt = $db->prepare("
            SELECT status, start_time, end_time 
            FROM system_outage_log 
            WHERE start_time >= :start_time
            ORDER BY start_time ASC
        ");
        
        $stmt->execute(['start_time' => date('Y-m-d H:i:s', $startTime)]);
        $outages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalDowntime = 0;
        $outageCount = 0;

        foreach ($outages as $outage) {
            $outageStart = max(strtotime($outage['start_time']), $startTime);
            $outageEnd = $outage['end_time'] ? min(strtotime($outage['end_time']), $endTime) : $endTime;
            
            $totalDowntime += ($outageEnd - $outageStart);
            $outageCount++;
        }

        $totalTime = $endTime - $startTime;
        $uptime = $totalTime - $totalDowntime;
        $availability = ($uptime / $totalTime) * 100;

        return [
            'period_start' => date('Y-m-d H:i:s', $startTime),
            'period_end' => date('Y-m-d H:i:s', $endTime),
            'total_time_seconds' => $totalTime,
            'uptime_seconds' => $uptime,
            'downtime_seconds' => $totalDowntime,
            'outage_count' => $outageCount,
            'availability_percent' => number_format($availability, 3),
            'sla_target' => self::SLA_AVAILABILITY_TARGET,
            'sla_compliant' => $availability >= self::SLA_AVAILABILITY_TARGET,
            'sla_credit_earned' => $availability < 99.0 ? 100 : ($availability < 99.5 ? 50 : ($availability < 99.9 ? 25 : 0))
        ];
    }

    public static function calculatePerformanceMetrics(): array
    {
        $db = Database::getConnection();
        
        $stmt = $db->prepare("
            SELECT 
                PERCENTILE_CONT(0.95) WITHIN GROUP (ORDER BY response_time) as p95,
                PERCENTILE_CONT(0.99) WITHIN GROUP (ORDER BY response_time) as p99,
                AVG(response_time) as average,
                COUNT(*) as total_requests
            FROM request_performance_log 
            WHERE created_at >= NOW() - INTERVAL '24 hours'
        ");
        
        $stmt->execute();
        $metrics = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'average_response_ms' => (int)$metrics['average'],
            'p95_response_ms' => (int)$metrics['p95'],
            'p99_response_ms' => (int)$metrics['p99'],
            'total_requests' => (int)$metrics['total_requests'],
            'sla_target_ms' => self::SLA_RESPONSE_TIME_TARGET,
            'sla_compliant' => (int)$metrics['p95'] <= self::SLA_RESPONSE_TIME_TARGET
        ];
    }

    public static function generateTenantReport(int $tenantId, int $month, int $year): array
    {
        return [
            'tenant_id' => $tenantId,
            'report_period' => "{$year}-{$month}",
            'generated_at' => date('Y-m-d H:i:s'),
            'availability' => self::calculateAvailability(),
            'performance' => self::calculatePerformanceMetrics(),
            'incidents' => self::getIncidentsForPeriod($month, $year),
            'summary' => self::generateSLASummary()
        ];
    }

    private static function getIncidentsForPeriod(int $month, int $year): array
    {
        $db = Database::getConnection();
        
        $stmt = $db->prepare("
            SELECT id, title, start_time, end_time, duration_seconds, severity
            FROM incidents
            WHERE EXTRACT(MONTH FROM start_time) = :month
            AND EXTRACT(YEAR FROM start_time) = :year
            ORDER BY start_time DESC
        ");
        
        $stmt->execute(['month' => $month, 'year' => $year]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function generateSLASummary(): array
    {
        $availability = self::calculateAvailability();
        $performance = self::calculatePerformanceMetrics();

        return [
            'overall_compliance' => $availability['sla_compliant'] && $performance['sla_compliant'],
            'uptime_achieved' => "{$availability['availability_percent']}%",
            'uptime_target' => self::SLA_AVAILABILITY_TARGET . "%",
            'response_time_achieved' => "{$performance['p95_response_ms']}ms",
            'response_time_target' => self::SLA_RESPONSE_TIME_TARGET . "ms",
            'credit_amount' => "\${$availability['sla_credit_earned']}"
        ];
    }
}