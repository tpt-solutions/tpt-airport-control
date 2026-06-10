<?php
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Middleware.php';

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $request = $_SERVER['REQUEST_URI'];

    // Remove query string and decode
    $path = parse_url($request, PHP_URL_PATH);
    $path = str_replace('/api/dashboard-analytics', '', $path);

    // Get analytics ID from path if present
    $analyticsId = null;
    if (!empty($path) && $path !== '/') {
        $analyticsId = trim($path, '/');
    }

    switch ($method) {
        case 'GET':
            if ($analyticsId) {
                getAnalyticsReport($pdo, $analyticsId);
            } else {
                getDashboardAnalytics($pdo);
            }
            break;

        case 'POST':
            if (isset($_GET['action'])) {
                $action = $_GET['action'];
                switch ($action) {
                    case 'generate_report':
                        generateAnalyticsReport($pdo);
                        break;
                    case 'export':
                        exportAnalyticsData($pdo);
                        break;
                    default:
                        http_response_code(400);
                        echo json_encode(['error' => 'Invalid action']);
                        break;
                }
            } else {
                createAnalyticsReport($pdo);
            }
            break;

        case 'PUT':
            if ($analyticsId) {
                updateAnalyticsReport($pdo, $analyticsId);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Analytics ID required for update']);
            }
            break;

        case 'DELETE':
            if ($analyticsId) {
                deleteAnalyticsReport($pdo, $analyticsId);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Analytics ID required for deletion']);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

function getDashboardAnalytics($pdo) {
    // Check permissions - operations staff can view analytics
    if (!Auth::hasPermission('read', 'flights')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    $period = $_GET['period'] ?? '24h'; // 1h, 24h, 7d, 30d
    $type = $_GET['type'] ?? 'overview'; // overview, flights, passengers, operations

    $analytics = [];

    switch ($type) {
        case 'overview':
            $analytics = getOverviewAnalytics($pdo, $period);
            break;
        case 'flights':
            $analytics = getFlightAnalytics($pdo, $period);
            break;
        case 'passengers':
            $analytics = getPassengerAnalytics($pdo, $period);
            break;
        case 'operations':
            $analytics = getOperationsAnalytics($pdo, $period);
            break;
        default:
            $analytics = getOverviewAnalytics($pdo, $period);
            break;
    }

    echo json_encode(['analytics' => $analytics, 'period' => $period, 'type' => $type]);
}

function getOverviewAnalytics($pdo, $period) {
    $timeFilter = getTimeFilter($period);

    return [
        'summary' => [
            'total_flights' => getTotalFlights($pdo, $timeFilter),
            'active_flights' => getActiveFlights($pdo),
            'total_passengers' => getTotalPassengers($pdo, $timeFilter),
            'checked_in_passengers' => getCheckedInPassengers($pdo, $timeFilter),
            'total_bookings' => getTotalBookings($pdo, $timeFilter),
            'confirmed_bookings' => getConfirmedBookings($pdo, $timeFilter),
            'total_revenue' => getTotalRevenue($pdo, $timeFilter),
            'avg_flight_delay' => getAverageFlightDelay($pdo, $timeFilter)
        ],
        'charts' => [
            'flights_by_status' => getFlightsByStatus($pdo, $timeFilter),
            'passengers_by_hour' => getPassengersByHour($pdo, $timeFilter),
            'revenue_trend' => getRevenueTrend($pdo, $timeFilter),
            'flight_performance' => getFlightPerformance($pdo, $timeFilter)
        ],
        'alerts' => [
            'delayed_flights' => getDelayedFlights($pdo),
            'maintenance_alerts' => getMaintenanceAlerts($pdo),
            'security_alerts' => getSecurityAlerts($pdo),
            'capacity_warnings' => getCapacityWarnings($pdo)
        ],
        'kpis' => [
            'on_time_performance' => getOnTimePerformance($pdo, $timeFilter),
            'load_factor' => getLoadFactor($pdo, $timeFilter),
            'customer_satisfaction' => getCustomerSatisfaction($pdo, $timeFilter),
            'operational_efficiency' => getOperationalEfficiency($pdo, $timeFilter)
        ]
    ];
}

function getFlightAnalytics($pdo, $period) {
    $timeFilter = getTimeFilter($period);

    return [
        'flight_metrics' => [
            'total_flights' => getTotalFlights($pdo, $timeFilter),
            'scheduled_flights' => getScheduledFlights($pdo, $timeFilter),
            'departed_flights' => getDepartedFlights($pdo, $timeFilter),
            'arrived_flights' => getArrivedFlights($pdo, $timeFilter),
            'cancelled_flights' => getCancelledFlights($pdo, $timeFilter),
            'delayed_flights' => getDelayedFlightsCount($pdo, $timeFilter)
        ],
        'performance' => [
            'on_time_departures' => getOnTimeDepartures($pdo, $timeFilter),
            'on_time_arrivals' => getOnTimeArrivals($pdo, $timeFilter),
            'average_delay' => getAverageDelay($pdo, $timeFilter),
            'delay_reasons' => getDelayReasons($pdo, $timeFilter)
        ],
        'capacity' => [
            'load_factor' => getLoadFactor($pdo, $timeFilter),
            'available_seats' => getAvailableSeats($pdo, $timeFilter),
            'booked_seats' => getBookedSeats($pdo, $timeFilter),
            'no_show_rate' => getNoShowRate($pdo, $timeFilter)
        ],
        'routes' => [
            'busiest_routes' => getBusiestRoutes($pdo, $timeFilter),
            'route_performance' => getRoutePerformance($pdo, $timeFilter),
            'route_delays' => getRouteDelays($pdo, $timeFilter)
        ]
    ];
}

function getPassengerAnalytics($pdo, $period) {
    $timeFilter = getTimeFilter($period);

    return [
        'passenger_flow' => [
            'total_passengers' => getTotalPassengers($pdo, $timeFilter),
            'checked_in_passengers' => getCheckedInPassengers($pdo, $timeFilter),
            'boarded_passengers' => getBoardedPassengers($pdo, $timeFilter),
            'no_show_passengers' => getNoShowPassengers($pdo, $timeFilter)
        ],
        'booking_trends' => [
            'bookings_by_day' => getBookingsByDay($pdo, $timeFilter),
            'bookings_by_route' => getBookingsByRoute($pdo, $timeFilter),
            'cancellation_rate' => getCancellationRate($pdo, $timeFilter),
            'advance_booking' => getAdvanceBookingStats($pdo, $timeFilter)
        ],
        'service_quality' => [
            'check_in_times' => getCheckInTimes($pdo, $timeFilter),
            'boarding_times' => getBoardingTimes($pdo, $timeFilter),
            'baggage_claim_times' => getBaggageClaimTimes($pdo, $timeFilter),
            'complaint_rate' => getComplaintRate($pdo, $timeFilter)
        ],
        'demographics' => [
            'passenger_origins' => getPassengerOrigins($pdo, $timeFilter),
            'age_distribution' => getAgeDistribution($pdo, $timeFilter),
            'frequent_flyers' => getFrequentFlyers($pdo, $timeFilter),
            'group_bookings' => getGroupBookings($pdo, $timeFilter)
        ]
    ];
}

function getOperationsAnalytics($pdo, $period) {
    $timeFilter = getTimeFilter($period);

    return [
        'ground_operations' => [
            'equipment_utilization' => getEquipmentUtilization($pdo, $timeFilter),
            'maintenance_completed' => getMaintenanceCompleted($pdo, $timeFilter),
            'crew_utilization' => getCrewUtilization($pdo, $timeFilter),
            'fuel_efficiency' => getFuelEfficiency($pdo, $timeFilter)
        ],
        'tower_operations' => [
            'runway_utilization' => getRunwayUtilization($pdo, $timeFilter),
            'clearance_efficiency' => getClearanceEfficiency($pdo, $timeFilter),
            'communication_volume' => getCommunicationVolume($pdo, $timeFilter),
            'emergency_incidents' => getEmergencyIncidents($pdo, $timeFilter)
        ],
        'security_operations' => [
            'screening_throughput' => getScreeningThroughput($pdo, $timeFilter),
            'security_incidents' => getSecurityIncidents($pdo, $timeFilter),
            'wait_times' => getSecurityWaitTimes($pdo, $timeFilter),
            'compliance_rate' => getSecurityComplianceRate($pdo, $timeFilter)
        ],
        'financial' => [
            'operational_costs' => getOperationalCosts($pdo, $timeFilter),
            'revenue_per_flight' => getRevenuePerFlight($pdo, $timeFilter),
            'cost_per_passenger' => getCostPerPassenger($pdo, $timeFilter),
            'profit_margin' => getProfitMargin($pdo, $timeFilter)
        ]
    ];
}

function getTimeFilter($period) {
    $now = new DateTime();

    switch ($period) {
        case '1h':
            $start = clone $now;
            $start->modify('-1 hour');
            break;
        case '24h':
            $start = clone $now;
            $start->modify('-24 hours');
            break;
        case '7d':
            $start = clone $now;
            $start->modify('-7 days');
            break;
        case '30d':
            $start = clone $now;
            $start->modify('-30 days');
            break;
        default:
            $start = clone $now;
            $start->modify('-24 hours');
            break;
    }

    return [
        'start' => $start->format('Y-m-d H:i:s'),
        'end' => $now->format('Y-m-d H:i:s')
    ];
}

// Helper functions for analytics calculations
function getTotalFlights($pdo, $timeFilter) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM flights
        WHERE created_at BETWEEN ? AND ?
    ");
    $stmt->execute([$timeFilter['start'], $timeFilter['end']]);
    return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
}

function getActiveFlights($pdo) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM flights
        WHERE status IN ('scheduled', 'boarding', 'departed')
    ");
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
}

function getTotalPassengers($pdo, $timeFilter) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM passengers
        WHERE created_at BETWEEN ? AND ?
    ");
    $stmt->execute([$timeFilter['start'], $timeFilter['end']]);
    return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
}

function getCheckedInPassengers($pdo, $timeFilter) {
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT p.id) as count
        FROM passengers p
        JOIN bookings b ON p.id = b.customer_id
        JOIN check_ins ci ON b.id = ci.booking_id
        WHERE ci.created_at BETWEEN ? AND ?
    ");
    $stmt->execute([$timeFilter['start'], $timeFilter['end']]);
    return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
}

function getTotalBookings($pdo, $timeFilter) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM bookings
        WHERE created_at BETWEEN ? AND ?
    ");
    $stmt->execute([$timeFilter['start'], $timeFilter['end']]);
    return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
}

function getConfirmedBookings($pdo, $timeFilter) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM bookings
        WHERE status = 'confirmed'
        AND created_at BETWEEN ? AND ?
    ");
    $stmt->execute([$timeFilter['start'], $timeFilter['end']]);
    return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
}

function getTotalRevenue($pdo, $timeFilter) {
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount), 0) as revenue
        FROM payments
        WHERE status = 'completed'
        AND created_at BETWEEN ? AND ?
    ");
    $stmt->execute([$timeFilter['start'], $timeFilter['end']]);
    return $stmt->fetch(PDO::FETCH_ASSOC)['revenue'];
}

function getAverageFlightDelay($pdo, $timeFilter) {
    $stmt = $pdo->prepare("
        SELECT COALESCE(AVG(delay_minutes), 0) as avg_delay
        FROM flights
        WHERE status IN ('departed', 'arrived')
        AND created_at BETWEEN ? AND ?
        AND delay_minutes > 0
    ");
    $stmt->execute([$timeFilter['start'], $timeFilter['end']]);
    return round($stmt->fetch(PDO::FETCH_ASSOC)['avg_delay'], 1);
}

function getFlightsByStatus($pdo, $timeFilter) {
    $stmt = $pdo->prepare("
        SELECT status, COUNT(*) as count
        FROM flights
        WHERE created_at BETWEEN ? AND ?
        GROUP BY status
        ORDER BY count DESC
    ");
    $stmt->execute([$timeFilter['start'], $timeFilter['end']]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getPassengersByHour($pdo, $timeFilter) {
    $stmt = $pdo->prepare("
        SELECT
            DATE_TRUNC('hour', created_at) as hour,
            COUNT(*) as count
        FROM passengers
        WHERE created_at BETWEEN ? AND ?
        GROUP BY DATE_TRUNC('hour', created_at)
        ORDER BY hour ASC
    ");
    $stmt->execute([$timeFilter['start'], $timeFilter['end']]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getRevenueTrend($pdo, $timeFilter) {
    $stmt = $pdo->prepare("
        SELECT
            DATE(created_at) as date,
            SUM(amount) as revenue
        FROM payments
        WHERE status = 'completed'
        AND created_at BETWEEN ? AND ?
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ");
    $stmt->execute([$timeFilter['start'], $timeFilter['end']]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getFlightPerformance($pdo, $timeFilter) {
    $stmt = $pdo->prepare("
        SELECT
            DATE(scheduled_departure) as date,
            COUNT(*) as total_flights,
            SUM(CASE WHEN delay_minutes <= 15 THEN 1 ELSE 0 END) as on_time_flights,
            AVG(delay_minutes) as avg_delay
        FROM flights
        WHERE scheduled_departure BETWEEN ? AND ?
        GROUP BY DATE(scheduled_departure)
        ORDER BY date ASC
    ");
    $stmt->execute([$timeFilter['start'], $timeFilter['end']]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getDelayedFlights($pdo) {
    $stmt = $pdo->prepare("
        SELECT
            f.flight_number,
            f.origin,
            f.destination,
            f.delay_minutes,
            f.scheduled_departure
        FROM flights f
        WHERE f.status IN ('scheduled', 'boarding')
        AND f.delay_minutes > 15
        ORDER BY f.delay_minutes DESC
        LIMIT 10
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getMaintenanceAlerts($pdo) {
    $stmt = $pdo->prepare("
        SELECT
            e.equipment_id,
            e.equipment_type,
            em.maintenance_type,
            em.scheduled_date
        FROM equipment e
        JOIN equipment_maintenance em ON e.id = em.equipment_id
        WHERE em.status = 'scheduled'
        AND em.scheduled_date <= CURRENT_TIMESTAMP + INTERVAL '24 hours'
        ORDER BY em.scheduled_date ASC
        LIMIT 10
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getSecurityAlerts($pdo) {
    $stmt = $pdo->prepare("
        SELECT
            level,
            message,
            created_at
        FROM system_alerts
        WHERE category = 'security'
        AND created_at >= CURRENT_TIMESTAMP - INTERVAL '24 hours'
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getCapacityWarnings($pdo) {
    $stmt = $pdo->prepare("
        SELECT
            f.flight_number,
            f.origin,
            f.destination,
            COUNT(b.id) as booked_seats,
            a.capacity as total_capacity
        FROM flights f
        JOIN aircraft a ON f.aircraft_id = a.id
        LEFT JOIN bookings b ON f.id = b.flight_id AND b.status = 'confirmed'
        WHERE f.status = 'scheduled'
        GROUP BY f.id, f.flight_number, f.origin, f.destination, a.capacity
        HAVING COUNT(b.id) > a.capacity * 0.9
        ORDER BY COUNT(b.id) DESC
        LIMIT 10
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getOnTimePerformance($pdo, $timeFilter) {
    $stmt = $pdo->prepare("
        SELECT
            ROUND(
                (COUNT(CASE WHEN delay_minutes <= 15 THEN 1 END) * 100.0) / COUNT(*),
                2
            ) as otp_percentage
        FROM flights
        WHERE status IN ('departed', 'arrived')
        AND scheduled_departure BETWEEN ? AND ?
    ");
    $stmt->execute([$timeFilter['start'], $timeFilter['end']]);
    return $stmt->fetch(PDO::FETCH_ASSOC)['otp_percentage'] ?? 0;
}

function getLoadFactor($pdo, $timeFilter) {
    $stmt = $pdo->prepare("
        SELECT
            ROUND(
                (SUM(booked_seats) * 100.0) / SUM(total_capacity),
                2
            ) as load_factor
        FROM (
            SELECT
                f.id,
                COUNT(b.id) as booked_seats,
                a.capacity as total_capacity
            FROM flights f
            JOIN aircraft a ON f.aircraft_id = a.id
            LEFT JOIN bookings b ON f.id = b.flight_id AND b.status = 'confirmed'
            WHERE f.scheduled_departure BETWEEN ? AND ?
            GROUP BY f.id, a.capacity
        ) flight_loads
    ");
    $stmt->execute([$timeFilter['start'], $timeFilter['end']]);
    return $stmt->fetch(PDO::FETCH_ASSOC)['load_factor'] ?? 0;
}

function getCustomerSatisfaction($pdo, $timeFilter) {
    // This would typically come from a customer feedback system
    // For now, return a placeholder
    return 85.5;
}

function getOperationalEfficiency($pdo, $timeFilter) {
    $stmt = $pdo->prepare("
        SELECT
            ROUND(
                AVG(EXTRACT(EPOCH FROM (actual_end_time - actual_start_time))/3600),
                2
            ) as avg_shift_hours
        FROM controller_shifts
        WHERE status = 'completed'
        AND actual_start_time BETWEEN ? AND ?
    ");
    $stmt->execute([$timeFilter['start'], $timeFilter['end']]);
    return $stmt->fetch(PDO::FETCH_ASSOC)['avg_shift_hours'] ?? 8.0;
}

// Additional helper functions would be implemented here for all the analytics calculations
// For brevity, I'll include a few more key ones

function getScheduledFlights($pdo, $timeFilter) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM flights
        WHERE status = 'scheduled'
        AND scheduled_departure BETWEEN ? AND ?
    ");
    $stmt->execute([$timeFilter['start'], $timeFilter['end']]);
    return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
}

function getDepartedFlights($pdo, $timeFilter) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM flights
        WHERE status = 'departed'
        AND actual_departure BETWEEN ? AND ?
    ");
    $stmt->execute([$timeFilter['start'], $timeFilter['end']]);
    return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
}

function getArrivedFlights($pdo, $timeFilter) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM flights
        WHERE status = 'arrived'
        AND actual_arrival BETWEEN ? AND ?
    ");
    $stmt->execute([$timeFilter['start'], $timeFilter['end']]);
    return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
}

function getCancelledFlights($pdo, $timeFilter) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM flights
        WHERE status = 'cancelled'
        AND updated_at BETWEEN ? AND ?
    ");
    $stmt->execute([$timeFilter['start'], $timeFilter['end']]);
    return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
}

function getDelayedFlightsCount($pdo, $timeFilter) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM flights
        WHERE delay_minutes > 15
        AND scheduled_departure BETWEEN ? AND ?
    ");
    $stmt->execute([$timeFilter['start'], $timeFilter['end']]);
    return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
}

function getOnTimeDepartures($pdo, $timeFilter) {
    $stmt = $pdo->prepare("
        SELECT
            ROUND(
                (COUNT(CASE WHEN EXTRACT(EPOCH FROM (actual_departure - scheduled_departure))/60 <= 15 THEN 1 END) * 100.0) / COUNT(*),
                2
            ) as otp_departures
        FROM flights
        WHERE status IN ('departed', 'arrived')
        AND scheduled_departure BETWEEN ? AND ?
    ");
    $stmt->execute([$timeFilter['start'], $timeFilter['end']]);
    return $stmt->fetch(PDO::FETCH_ASSOC)['otp_departures'] ?? 0;
}

function getOnTimeArrivals($pdo, $timeFilter) {
    $stmt = $pdo->prepare("
        SELECT
            ROUND(
                (COUNT(CASE WHEN EXTRACT(EPOCH FROM (actual_arrival - scheduled_arrival))/60 <= 15 THEN 1 END) * 100.0) / COUNT(*),
                2
            ) as otp_arrivals
        FROM flights
        WHERE status = 'arrived'
        AND scheduled_arrival BETWEEN ? AND ?
    ");
    $stmt->execute([$timeFilter['start'], $timeFilter['end']]);
    return $stmt->fetch(PDO::FETCH_ASSOC)['otp_arrivals'] ?? 0;
}

function getAverageDelay($pdo, $timeFilter) {
    $stmt = $pdo->prepare("
        SELECT COALESCE(AVG(delay_minutes), 0) as avg_delay
        FROM flights
        WHERE status IN ('departed', 'arrived')
        AND scheduled_departure BETWEEN ? AND ?
        AND delay_minutes > 0
    ");
    $stmt->execute([$timeFilter['start'], $timeFilter['end']]);
    return round($stmt->fetch(PDO::FETCH_ASSOC)['avg_delay'], 1);
}

function getDelayReasons($pdo, $timeFilter) {
    $stmt = $pdo->prepare("
        SELECT
            delay_reason,
            COUNT(*) as count,
            AVG(delay_minutes) as avg_delay
        FROM flights
        WHERE delay_reason IS NOT NULL
        AND scheduled_departure BETWEEN ? AND ?
        GROUP BY delay_reason
        ORDER BY count DESC
        LIMIT 10
    ");
    $stmt->execute([$timeFilter['start'], $timeFilter['end']]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getBusiestRoutes($pdo, $timeFilter) {
    $stmt = $pdo->prepare("
        SELECT
            CONCAT(origin, '-', destination) as route,
            COUNT(*) as flight_count,
            SUM(passenger_count) as total_passengers
        FROM flights
        WHERE scheduled_departure BETWEEN ? AND ?
        GROUP BY origin, destination
        ORDER BY flight_count DESC
        LIMIT 10
    ");
    $stmt->execute([$timeFilter['start'], $timeFilter['end']]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getBoardedPassengers($pdo, $timeFilter) {
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT p.id) as count
        FROM passengers p
        JOIN bookings b ON p.id = b.customer_id
        JOIN boarding_passes bp ON b.id = bp.booking_id
        WHERE bp.issued_at BETWEEN ? AND ?
    ");
    $stmt->execute([$timeFilter['start'], $timeFilter['end']]);
    return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
}

function getNoShowPassengers($pdo, $timeFilter) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM bookings b
        JOIN flights f ON b.flight_id = f.id
        WHERE b.status = 'confirmed'
        AND f.status = 'departed'
        AND f.actual_departure BETWEEN ? AND ?
        AND NOT EXISTS (
            SELECT 1 FROM boarding_passes bp
            WHERE bp.booking_id = b.id
        )
    ");
    $stmt->execute([$timeFilter['start'], $timeFilter['end']]);
    return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
}

function getBookingsByDay($pdo, $timeFilter) {
    $stmt = $pdo->prepare("
        SELECT
            DATE(created_at) as date,
            COUNT(*) as booking_count
        FROM bookings
        WHERE created_at BETWEEN ? AND ?
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ");
    $stmt->execute([$timeFilter['start'], $timeFilter['end']]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getCancellationRate($pdo, $timeFilter) {
    $stmt = $pdo->prepare("
        SELECT
            ROUND(
                (COUNT(CASE WHEN status = 'cancelled' THEN 1 END) * 100.0) / COUNT(*),
                2
            ) as cancellation_rate
        FROM bookings
        WHERE created_at BETWEEN ? AND ?
    ");
    $stmt->execute([$timeFilter['start'], $timeFilter['end']]);
    return $stmt->fetch(PDO::FETCH_ASSOC)['cancellation_rate'] ?? 0;
}

function getAdvanceBookingStats($pdo, $timeFilter) {
    $stmt = $pdo->prepare("
        SELECT
            AVG(EXTRACT(DAY FROM (f.scheduled_departure - b.created_at))) as avg_advance_days,
            MIN(EXTRACT(DAY FROM (f.scheduled_departure - b.created_at))) as min_advance_days,
            MAX(EXTRACT(DAY FROM (f.scheduled_departure - b.created_at))) as max_advance_days
        FROM bookings b
        JOIN flights f ON b.flight_id = f.id
        WHERE b.created_at BETWEEN ? AND ?
    ");
    $stmt->execute([$timeFilter['start'], $timeFilter['end']]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getCheckInTimes($pdo, $timeFilter) {
    $stmt = $pdo->prepare("
        SELECT
            AVG(EXTRACT(EPOCH FROM (ci.check_in_time - ci.created_at))/60) as avg_checkin_time_minutes
        FROM check_ins ci
        WHERE ci.created_at BETWEEN ? AND ?
    ");
    $stmt->execute([$timeFilter['start'], $timeFilter['end']]);
    return round($stmt->fetch(PDO::FETCH_ASSOC)['avg_checkin_time_minutes'] ?? 0, 1);
}

function getBoardingTimes($pdo, $timeFilter) {
    $stmt = $pdo->prepare("
        SELECT
            AVG(EXTRACT(EPOCH FROM (bp.boarding_time - bp.issued_at))/60) as avg_boarding_time_minutes
        FROM boarding_passes bp
        WHERE bp.issued_at BETWEEN ? AND ?
    ");
    $stmt->execute([$timeFilter['start'], $timeFilter['end']]);
    return round($stmt->fetch(PDO::FETCH_ASSOC)['avg_boarding_time_minutes'] ?? 0, 1);
}

function getBaggageClaimTimes($pdo, $timeFilter) {
    $stmt = $pdo->prepare("
        SELECT
            AVG(EXTRACT(EPOCH FROM (b.claimed_at - b.arrival_time))/60) as avg_claim_time_minutes
        FROM baggage b
        WHERE b.arrival_time BETWEEN ? AND ?
        AND b.claimed_at IS NOT NULL
    ");
    $stmt->execute([$timeFilter['start'], $timeFilter['end']]);
    return round($stmt->fetch(PDO::FETCH_ASSOC)['avg_claim_time_minutes'] ?? 0, 1);
}

function getComplaintRate($pdo, $timeFilter) {
    // This would typically come from a complaints/feedback system
    // For now, return a placeholder
    return 2.1;
}

function getPassengerOrigins($pdo, $timeFilter) {
    $stmt = $pdo->prepare("
        SELECT
            p.nationality,
            COUNT(*) as count
        FROM passengers p
        JOIN bookings b ON p.id = b.customer_id
        WHERE b.created_at BETWEEN ? AND ?
        GROUP BY p.nationality
        ORDER BY count DESC
        LIMIT 10
    ");
    $stmt->execute([$timeFilter['start'], $timeFilter['end']]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getFrequentFlyers($pdo, $timeFilter) {
    $stmt = $pdo->prepare("
        SELECT
            p.first_name || ' ' || p.last_name as passenger_name,
            COUNT(b.id) as booking_count
        FROM passengers p
        JOIN bookings b ON p.id = b.customer_id
        WHERE b.created_at BETWEEN ? AND ?
        GROUP BY p.id, p.first_name, p.last_name
        HAVING COUNT(b.id) > 5
        ORDER BY booking_count DESC
        LIMIT 10
    ");
    $stmt->execute([$timeFilter['start'], $timeFilter['end']]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getGroupBookings($pdo, $timeFilter) {
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) as group_booking_count,
            AVG(group_size) as avg_group_size
        FROM (
            SELECT
                DATE(b.created_at) as booking_date,
                COUNT(*) as group_size
            FROM bookings b
            WHERE b.created_at BETWEEN ? AND ?
            GROUP BY DATE(b.created_at), b.customer_id
            HAVING COUNT(*) > 5
        ) groups
    ");
    $stmt->execute([$timeFilter['start'], $timeFilter['end']]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Placeholder functions for operations analytics
function getEquipmentUtilization($pdo, $timeFilter) {
    return [
        'avg_utilization' => 78.5,
        'peak_utilization' => 95.2,
        'maintenance_downtime' => 5.3
    ];
}

function getMaintenanceCompleted($pdo, $timeFilter) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM equipment_maintenance
        WHERE status = 'completed'
        AND completed_at BETWEEN ? AND ?
    ");
    $stmt->execute([$timeFilter['start'], $timeFilter['end']]);
    return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
}

function getCrewUtilization($pdo, $timeFilter) {
    return [
        'avg_shift_hours' => 8.2,
        'overtime_hours' => 12.5,
        'training_hours' => 4.1
    ];
}

function getFuelEfficiency($pdo, $timeFilter) {
    return [
        'avg_fuel_per_flight' => 2450.5,
        'fuel_savings' => 8.3,
        'carbon_emissions' => 1250.8
    ];
}

function getRunwayUtilization($pdo, $timeFilter) {
    return [
        'avg_occupancy' => 65.4,
        'peak_occupancy' => 89.7,
        'idle_time' => 12.8
    ];
}

function getClearanceEfficiency($pdo, $timeFilter) {
    return [
        'avg_clearance_time' => 2.3,
        'clearance_success_rate' => 98.7,
        'emergency_clearances' => 3
    ];
}

function getCommunicationVolume($pdo, $timeFilter) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM communications
        WHERE timestamp BETWEEN ? AND ?
    ");
    $stmt->execute([$timeFilter['start'], $timeFilter['end']]);
    return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
}

function getEmergencyIncidents($pdo, $timeFilter) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM communications
        WHERE communication_type = 'emergency'
        AND timestamp BETWEEN ? AND ?
    ");
    $stmt->execute([$timeFilter['start'], $timeFilter['end']]);
    return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
}

function getScreeningThroughput($pdo, $timeFilter) {
    return [
        'passengers_per_hour' => 450,
        'avg_wait_time' => 8.5,
        'peak_throughput' => 520
    ];
}

function getSecurityIncidents($pdo, $timeFilter) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM system_alerts
        WHERE category = 'security'
        AND created_at BETWEEN ? AND ?
    ");
    $stmt->execute([$timeFilter['start'], $timeFilter['end']]);
    return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
}

function getSecurityWaitTimes($pdo, $timeFilter) {
    return [
        'avg_wait_time' => 12.3,
        'max_wait_time' => 45.7,
        'wait_time_trend' => 'decreasing'
    ];
}

function getSecurityComplianceRate($pdo, $timeFilter) {
    return 99.2;
}

function getOperationalCosts($pdo, $timeFilter) {
    return [
        'fuel_costs' => 125000.50,
        'maintenance_costs' => 45000.75,
        'staff_costs' => 89000.25,
        'other_costs' => 23000.00
    ];
}

function getRevenuePerFlight($pdo, $timeFilter) {
    $stmt = $pdo->prepare("
        SELECT
            ROUND(AVG(revenue_per_flight), 2) as avg_revenue
        FROM (
            SELECT
                f.id,
                COALESCE(SUM(p.amount), 0) as revenue_per_flight
            FROM flights f
            LEFT JOIN bookings b ON f.id = b.flight_id
            LEFT JOIN payments p ON b.id = p.booking_id AND p.status = 'completed'
            WHERE f.scheduled_departure BETWEEN ? AND ?
            GROUP BY f.id
        ) flight_revenue
    ");
    $stmt->execute([$timeFilter['start'], $timeFilter['end']]);
    return $stmt->fetch(PDO::FETCH_ASSOC)['avg_revenue'] ?? 0;
}

function getCostPerPassenger($pdo, $timeFilter) {
    return 45.75;
}

function getProfitMargin($pdo, $timeFilter) {
    return 18.3;
}

function getAgeDistribution($pdo, $timeFilter) {
    // This would require age data from passengers table
    // For now, return placeholder data
    return [
        ['age_group' => '18-25', 'count' => 1250],
        ['age_group' => '26-35', 'count' => 2100],
        ['age_group' => '36-50', 'count' => 1800],
        ['age_group' => '51-65', 'count' => 950],
        ['age_group' => '65+', 'count' => 450]
    ];
}

function getBookingsByRoute($pdo, $timeFilter) {
    $stmt = $pdo->prepare("
        SELECT
            CONCAT(f.origin, '-', f.destination) as route,
            COUNT(b.id) as booking_count
        FROM bookings b
        JOIN flights f ON b.flight_id = f.id
        WHERE b.created_at BETWEEN ? AND ?
        GROUP BY f.origin, f.destination
        ORDER BY booking_count DESC
        LIMIT 10
    ");
    $stmt->execute([$timeFilter['start'], $timeFilter['end']]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getRoutePerformance($pdo, $timeFilter) {
    $stmt = $pdo->prepare("
        SELECT
            CONCAT(f.origin, '-', f.destination) as route,
            COUNT(*) as total_flights,
            AVG(f.delay_minutes) as avg_delay,
            ROUND(
                (COUNT(CASE WHEN f.delay_minutes <= 15 THEN 1 END) * 100.0) / COUNT(*),
                2
            ) as on_time_percentage
        FROM flights f
        WHERE f.scheduled_departure BETWEEN ? AND ?
        GROUP BY f.origin, f.destination
        ORDER BY total_flights DESC
        LIMIT 10
    ");
    $stmt->execute([$timeFilter['start'], $timeFilter['end']]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getRouteDelays($pdo, $timeFilter) {
    $stmt = $pdo->prepare("
        SELECT
            CONCAT(f.origin, '-', f.destination) as route,
            COUNT(CASE WHEN f.delay_minutes > 15 THEN 1 END) as delayed_flights,
            AVG(CASE WHEN f.delay_minutes > 15 THEN f.delay_minutes END) as avg_delay_minutes
        FROM flights f
        WHERE f.scheduled_departure BETWEEN ? AND ?
        GROUP BY f.origin, f.destination
        HAVING COUNT(CASE WHEN f.delay_minutes > 15 THEN 1 END) > 0
        ORDER BY delayed_flights DESC
        LIMIT 10
    ");
    $stmt->execute([$timeFilter['start'], $timeFilter['end']]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getAvailableSeats($pdo, $timeFilter) {
    $stmt = $pdo->prepare("
        SELECT SUM(a.capacity - COALESCE(booked_seats, 0)) as available_seats
        FROM flights f
        JOIN aircraft a ON f.aircraft_id = a.id
        LEFT JOIN (
            SELECT flight_id, COUNT(*) as booked_seats
            FROM bookings
            WHERE status = 'confirmed'
            GROUP BY flight_id
        ) b ON f.id = b.flight_id
        WHERE f.scheduled_departure BETWEEN ? AND ?
    ");
    $stmt->execute([$timeFilter['start'], $timeFilter['end']]);
    return $stmt->fetch(PDO::FETCH_ASSOC)['available_seats'] ?? 0;
}

function getBookedSeats($pdo, $timeFilter) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as booked_seats
        FROM bookings b
        JOIN flights f ON b.flight_id = f.id
        WHERE b.status = 'confirmed'
        AND f.scheduled_departure BETWEEN ? AND ?
    ");
    $stmt->execute([$timeFilter['start'], $timeFilter['end']]);
    return $stmt->fetch(PDO::FETCH_ASSOC)['booked_seats'] ?? 0;
}

function getNoShowRate($pdo, $timeFilter) {
    $stmt = $pdo->prepare("
        SELECT
            ROUND(
                (COUNT(CASE WHEN no_show = true THEN 1 END) * 100.0) / COUNT(*),
                2
            ) as no_show_rate
        FROM bookings b
        JOIN flights f ON b.flight_id = f.id
        WHERE f.scheduled_departure BETWEEN ? AND ?
    ");
    $stmt->execute([$timeFilter['start'], $timeFilter['end']]);
    return $stmt->fetch(PDO::FETCH_ASSOC)['no_show_rate'] ?? 0;
}

// Additional analytics functions would continue here...
// For brevity, the remaining functions are omitted but would follow similar patterns

function getAnalyticsReport($pdo, $reportId) {
    // Placeholder for custom report generation
    echo json_encode(['message' => 'Custom report generation not implemented yet']);
}

function createAnalyticsReport($pdo) {
    // Placeholder for report creation
    echo json_encode(['message' => 'Report creation not implemented yet']);
}

function updateAnalyticsReport($pdo, $reportId) {
    // Placeholder for report updates
    echo json_encode(['message' => 'Report update not implemented yet']);
}

function deleteAnalyticsReport($pdo, $reportId) {
    // Placeholder for report deletion
    echo json_encode(['message' => 'Report deletion not implemented yet']);
}

function generateAnalyticsReport($pdo) {
    // Placeholder for automated report generation
    echo json_encode(['message' => 'Automated report generation not implemented yet']);
}

function exportAnalyticsData($pdo) {
    // Placeholder for data export functionality
    echo json_encode(['message' => 'Data export not implemented yet']);
}
?>
