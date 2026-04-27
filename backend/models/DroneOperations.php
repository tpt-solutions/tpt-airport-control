<?php

/**
 * Drone Operations Model
 *
 * Manages drone traffic control, airspace management, regulatory compliance, and UAV operations
 */

class DroneOperations
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
        $this->logger = new Logger('drone_operations');
    }

    /**
     * Register a new drone
     */
    public function registerDrone($droneData)
    {
        $this->logger->info("Registering drone", $droneData);

        $stmt = $this->db->prepare("
            INSERT INTO drone_registrations (
                drone_id, registration_number, serial_number, manufacturer, model,
                drone_type, max_takeoff_weight_kg, max_payload_kg, flight_duration_minutes,
                max_speed_kmh, max_altitude_meters, communication_range_km, gps_enabled,
                autonomous_capable, camera_type, camera_resolution, owner_name,
                owner_contact, operator_license_number, insurance_provider,
                insurance_policy_number, insurance_expiry, registration_expiry
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $droneId = $this->generateDroneId();

        $stmt->execute([
            $droneId,
            $droneData['registration_number'],
            $droneData['serial_number'] ?? null,
            $droneData['manufacturer'],
            $droneData['model'],
            $droneData['drone_type'] ?? 'multirotor',
            $droneData['max_takeoff_weight_kg'] ?? null,
            $droneData['max_payload_kg'] ?? null,
            $droneData['flight_duration_minutes'] ?? null,
            $droneData['max_speed_kmh'] ?? null,
            $droneData['max_altitude_meters'] ?? null,
            $droneData['communication_range_km'] ?? null,
            $droneData['gps_enabled'] ?? true,
            $droneData['autonomous_capable'] ?? false,
            $droneData['camera_type'] ?? null,
            $droneData['camera_resolution'] ?? null,
            $droneData['owner_name'],
            isset($droneData['owner_contact']) ? json_encode($droneData['owner_contact']) : null,
            $droneData['operator_license_number'] ?? null,
            $droneData['insurance_provider'] ?? null,
            $droneData['insurance_policy_number'] ?? null,
            $droneData['insurance_expiry'] ?? null,
            $droneData['registration_expiry'] ?? null
        ]);

        return [
            'drone_id' => $droneId,
            'status' => 'registered',
            'message' => 'Drone registered successfully'
        ];
    }

    /**
     * Get drone details
     */
    public function getDrone($droneId)
    {
        $stmt = $this->db->prepare("
            SELECT
                dr.*,
                (
                    SELECT COUNT(*)
                    FROM drone_flight_plans
                    WHERE drone_id = dr.drone_id
                    AND status IN ('approved', 'active', 'completed')
                ) as total_flights,
                (
                    SELECT COUNT(*)
                    FROM drone_incidents
                    WHERE drone_id = dr.drone_id
                ) as incident_count,
                (
                    SELECT COUNT(*)
                    FROM airspace_violations
                    WHERE drone_id = dr.drone_id
                    AND status = 'open'
                ) as active_violations
            FROM drone_registrations dr
            WHERE dr.drone_id = ?
        ");

        $stmt->execute([$droneId]);
        $drone = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$drone) {
            throw new Exception("Drone not found");
        }

        return $drone;
    }

    /**
     * Get drones with filtering
     */
    public function getDrones($filters = [])
    {
        $whereClause = "";
        $params = [];

        if (isset($filters['status'])) {
            $whereClause .= " AND operational_status = ?";
            $params[] = $filters['status'];
        }

        if (isset($filters['type'])) {
            $whereClause .= " AND drone_type = ?";
            $params[] = $filters['type'];
        }

        if (isset($filters['owner'])) {
            $whereClause .= " AND owner_name = ?";
            $params[] = $filters['owner'];
        }

        $stmt = $this->db->prepare("
            SELECT
                dr.*,
                (
                    SELECT COUNT(*)
                    FROM drone_flight_plans
                    WHERE drone_id = dr.drone_id
                    AND DATE(planned_departure) >= CURRENT_DATE - INTERVAL '30 days'
                ) as recent_flights
            FROM drone_registrations dr
            WHERE 1=1 $whereClause
            ORDER BY dr.registration_date DESC
            LIMIT ?
        ");

        $params[] = $filters['limit'] ?? 50;
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Create flight plan
     */
    public function createFlightPlan($planData, $createdBy)
    {
        $this->logger->info("Creating flight plan", $planData);

        $stmt = $this->db->prepare("
            SELECT create_drone_flight_plan(?, ?)
        ");

        $stmt->execute([
            json_encode($planData),
            $createdBy
        ]);

        return [
            'flight_plan_id' => $stmt->fetchColumn(),
            'status' => 'created',
            'message' => 'Flight plan created successfully'
        ];
    }

    /**
     * Get flight plans
     */
    public function getFlightPlans($filters = [])
    {
        $whereClause = "";
        $params = [];

        if (isset($filters['status'])) {
            $whereClause .= " AND status = ?";
            $params[] = $filters['status'];
        }

        if (isset($filters['drone_id'])) {
            $whereClause .= " AND drone_id = ?";
            $params[] = $filters['drone_id'];
        }

        if (isset($filters['operator_id'])) {
            $whereClause .= " AND operator_id = ?";
            $params[] = $filters['operator_id'];
        }

        if (isset($filters['start_date'])) {
            $whereClause .= " AND planned_departure >= ?";
            $params[] = $filters['start_date'];
        }

        if (isset($filters['end_date'])) {
            $whereClause .= " AND planned_departure <= ?";
            $params[] = $filters['end_date'];
        }

        $stmt = $this->db->prepare("
            SELECT
                dfp.*,
                dr.registration_number,
                dr.owner_name,
                calculate_flight_risk_score(dfp.drone_id, json_build_object(
                    'max_altitude_meters', dfp.max_altitude_meters,
                    'purpose', dfp.purpose
                )) as risk_score
            FROM drone_flight_plans dfp
            JOIN drone_registrations dr ON dfp.drone_id = dr.drone_id
            WHERE 1=1 $whereClause
            ORDER BY dfp.planned_departure DESC
            LIMIT ?
        ");

        $params[] = $filters['limit'] ?? 50;
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Approve flight plan
     */
    public function approveFlightPlan($flightPlanId, $approvedBy, $conditions = null)
    {
        $stmt = $this->db->prepare("
            UPDATE drone_flight_plans
            SET status = 'approved', approved_by = ?, approved_at = CURRENT_TIMESTAMP,
                approval_notes = ?
            WHERE flight_plan_id = ?
        ");

        $stmt->execute([$approvedBy, $conditions, $flightPlanId]);

        return [
            'flight_plan_id' => $flightPlanId,
            'status' => 'approved',
            'message' => 'Flight plan approved successfully'
        ];
    }

    /**
     * Record flight operation
     */
    public function recordFlightOperation($operationData)
    {
        $this->logger->info("Recording flight operation", $operationData);

        $stmt = $this->db->prepare("
            INSERT INTO drone_flight_operations (
                flight_plan_id, drone_id, actual_departure, actual_arrival,
                actual_duration_minutes, actual_max_altitude_meters, actual_flight_path,
                weather_conditions, incidents_reported, operational_notes,
                fuel_consumption, battery_cycles_used, maintenance_required,
                maintenance_notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $operationId = $this->generateOperationId();

        $stmt->execute([
            $operationData['flight_plan_id'],
            $operationData['drone_id'],
            $operationData['actual_departure'] ?? date('c'),
            $operationData['actual_arrival'] ?? null,
            $operationData['actual_duration_minutes'] ?? null,
            $operationData['actual_max_altitude_meters'] ?? null,
            isset($operationData['actual_flight_path']) ? json_encode($operationData['actual_flight_path']) : null,
            isset($operationData['weather_conditions']) ? json_encode($operationData['weather_conditions']) : '{}',
            isset($operationData['incidents_reported']) ? json_encode($operationData['incidents_reported']) : '[]',
            $operationData['operational_notes'] ?? null,
            $operationData['fuel_consumption'] ?? null,
            $operationData['battery_cycles_used'] ?? null,
            $operationData['maintenance_required'] ?? false,
            $operationData['maintenance_notes'] ?? null
        ]);

        // Update flight plan status
        $stmt = $this->db->prepare("
            UPDATE drone_flight_plans
            SET status = 'completed', updated_at = CURRENT_TIMESTAMP
            WHERE flight_plan_id = ?
        ");
        $stmt->execute([$operationData['flight_plan_id']]);

        return [
            'operation_id' => $operationId,
            'status' => 'recorded',
            'message' => 'Flight operation recorded successfully'
        ];
    }

    /**
     * Get airspace zones
     */
    public function getAirspaceZones($type = null, $status = 'active')
    {
        $whereClause = "WHERE status = ?";
        $params = [$status];

        if ($type) {
            $whereClause .= " AND zone_type = ?";
            $params[] = $type;
        }

        $stmt = $this->db->prepare("
            SELECT
                az.*,
                (
                    SELECT COUNT(*)
                    FROM airspace_reservations
                    WHERE zone_id = az.zone_id
                    AND status = 'active'
                ) as active_reservations
            FROM airspace_zones az
            $whereClause
            ORDER BY az.zone_type, az.zone_name
        ");

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Create airspace reservation
     */
    public function createAirspaceReservation($reservationData, $requestedBy)
    {
        $this->logger->info("Creating airspace reservation", $reservationData);

        $stmt = $this->db->prepare("
            INSERT INTO airspace_reservations (
                reservation_id, reservation_number, zone_id, drone_id, operator_id,
                reservation_start, reservation_end, reservation_type, altitude_min_msl,
                altitude_max_msl, activity_type
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $reservationId = $this->generateReservationId();
        $reservationNumber = $this->generateReservationNumber();

        $stmt->execute([
            $reservationId,
            $reservationNumber,
            $reservationData['zone_id'],
            $reservationData['drone_id'] ?? null,
            $requestedBy,
            $reservationData['reservation_start'],
            $reservationData['reservation_end'],
            $reservationData['reservation_type'] ?? 'exclusive',
            $reservationData['altitude_min_msl'] ?? null,
            $reservationData['altitude_max_msl'] ?? null,
            $reservationData['activity_type'] ?? null
        ]);

        return [
            'reservation_id' => $reservationId,
            'reservation_number' => $reservationNumber,
            'status' => 'requested',
            'message' => 'Airspace reservation created successfully'
        ];
    }

    /**
     * Get airspace reservations
     */
    public function getAirspaceReservations($status = null, $zoneId = null)
    {
        $whereClause = "";
        $params = [];

        if ($status) {
            $whereClause .= " AND status = ?";
            $params[] = $status;
        }

        if ($zoneId) {
            $whereClause .= " AND zone_id = ?";
            $params[] = $zoneId;
        }

        $stmt = $this->db->prepare("
            SELECT
                ar.*,
                az.zone_name,
                az.zone_type,
                dr.registration_number,
                dr.owner_name
            FROM airspace_reservations ar
            LEFT JOIN airspace_zones az ON ar.zone_id = az.zone_id
            LEFT JOIN drone_registrations dr ON ar.drone_id = dr.drone_id
            WHERE 1=1 $whereClause
            ORDER BY ar.reservation_start DESC
        ");

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Record drone telemetry
     */
    public function recordTelemetry($telemetryData)
    {
        $stmt = $this->db->prepare("
            INSERT INTO drone_telemetry (
                drone_id, operation_id, latitude, longitude, altitude_msl,
                altitude_agl, ground_speed_kmh, heading_degrees, battery_voltage,
                battery_percentage, signal_strength_dbm, gps_satellites,
                temperature_celsius, vibration_g, wind_speed_kmh,
                wind_direction_degrees, payload_status, system_status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $telemetryData['drone_id'],
            $telemetryData['operation_id'] ?? null,
            $telemetryData['latitude'],
            $telemetryData['longitude'],
            $telemetryData['altitude_msl'] ?? null,
            $telemetryData['altitude_agl'] ?? null,
            $telemetryData['ground_speed_kmh'] ?? null,
            $telemetryData['heading_degrees'] ?? null,
            $telemetryData['battery_voltage'] ?? null,
            $telemetryData['battery_percentage'] ?? null,
            $telemetryData['signal_strength_dbm'] ?? null,
            $telemetryData['gps_satellites'] ?? null,
            $telemetryData['temperature_celsius'] ?? null,
            $telemetryData['vibration_g'] ?? null,
            $telemetryData['wind_speed_kmh'] ?? null,
            $telemetryData['wind_direction_degrees'] ?? null,
            isset($telemetryData['payload_status']) ? json_encode($telemetryData['payload_status']) : '{}',
            isset($telemetryData['system_status']) ? json_encode($telemetryData['system_status']) : '{}'
        ]);

        return [
            'telemetry_id' => $this->db->lastInsertId(),
            'status' => 'recorded',
            'message' => 'Drone telemetry recorded successfully'
        ];
    }

    /**
     * Get drone telemetry
     */
    public function getDroneTelemetry($droneId, $startDate = null, $endDate = null, $limit = 1000)
    {
        $whereClause = "WHERE drone_id = ?";
        $params = [$droneId];

        if ($startDate) {
            $whereClause .= " AND timestamp >= ?";
            $params[] = $startDate;
        }

        if ($endDate) {
            $whereClause .= " AND timestamp <= ?";
            $params[] = $endDate;
        }

        $stmt = $this->db->prepare("
            SELECT * FROM drone_telemetry
            $whereClause
            ORDER BY timestamp DESC
            LIMIT ?
        ");

        $params[] = $limit;
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Record drone incident
     */
    public function recordIncident($incidentData, $reportedBy)
    {
        $this->logger->info("Recording drone incident", $incidentData);

        $stmt = $this->db->prepare("
            INSERT INTO drone_incidents (
                incident_id, incident_number, drone_id, operation_id, incident_type,
                severity_level, incident_location, incident_altitude, weather_conditions,
                incident_description, contributing_factors, injuries, injuries_description,
                property_damage, property_damage_description, reported_by,
                regulatory_notification_required
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $incidentId = $this->generateIncidentId();
        $incidentNumber = $this->generateIncidentNumber();

        $stmt->execute([
            $incidentId,
            $incidentNumber,
            $incidentData['drone_id'] ?? null,
            $incidentData['operation_id'] ?? null,
            $incidentData['incident_type'],
            $incidentData['severity_level'] ?? 'minor',
            isset($incidentData['incident_location']) ? json_encode($incidentData['incident_location']) : null,
            $incidentData['incident_altitude'] ?? null,
            isset($incidentData['weather_conditions']) ? json_encode($incidentData['weather_conditions']) : '{}',
            $incidentData['incident_description'],
            isset($incidentData['contributing_factors']) ? json_encode($incidentData['contributing_factors']) : '[]',
            $incidentData['injuries'] ?? false,
            $incidentData['injuries_description'] ?? null,
            $incidentData['property_damage'] ?? false,
            $incidentData['property_damage_description'] ?? null,
            $reportedBy,
            $incidentData['regulatory_notification_required'] ?? false
        ]);

        return [
            'incident_id' => $incidentId,
            'incident_number' => $incidentNumber,
            'status' => 'reported',
            'message' => 'Drone incident recorded successfully'
        ];
    }

    /**
     * Get drone incidents
     */
    public function getIncidents($type = null, $severity = null, $startDate = null, $endDate = null)
    {
        $whereClause = "";
        $params = [];

        if ($type) {
            $whereClause .= " AND incident_type = ?";
            $params[] = $type;
        }

        if ($severity) {
            $whereClause .= " AND severity_level = ?";
            $params[] = $severity;
        }

        if ($startDate) {
            $whereClause .= " AND DATE(reported_at) >= ?";
            $params[] = $startDate;
        }

        if ($endDate) {
            $whereClause .= " AND DATE(reported_at) <= ?";
            $params[] = $endDate;
        }

        $stmt = $this->db->prepare("
            SELECT
                di.*,
                dr.registration_number,
                dr.owner_name,
                dfp.flight_plan_number
            FROM drone_incidents di
            LEFT JOIN drone_registrations dr ON di.drone_id = dr.drone_id
            LEFT JOIN drone_flight_operations dfo ON di.operation_id = dfo.operation_id
            LEFT JOIN drone_flight_plans dfp ON dfo.flight_plan_id = dfp.flight_plan_id
            WHERE 1=1 $whereClause
            ORDER BY di.reported_at DESC
        ");

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Record airspace violation
     */
    public function recordViolation($violationData, $reportedBy)
    {
        $this->logger->info("Recording airspace violation", $violationData);

        $stmt = $this->db->prepare("
            INSERT INTO airspace_violations (
                drone_id, operation_id, violation_type, violation_location,
                violation_description, severity_level, corrective_action_required,
                corrective_action_taken, fine_amount, points_assessed
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $violationData['drone_id'] ?? null,
            $violationData['operation_id'] ?? null,
            $violationData['violation_type'],
            isset($violationData['violation_location']) ? json_encode($violationData['violation_location']) : null,
            $violationData['violation_description'],
            $violationData['severity_level'] ?? 'minor',
            $violationData['corrective_action_required'] ?? true,
            $violationData['corrective_action_taken'] ?? null,
            $violationData['fine_amount'] ?? null,
            $violationData['points_assessed'] ?? 0
        ]);

        return [
            'violation_id' => $this->db->lastInsertId(),
            'status' => 'recorded',
            'message' => 'Airspace violation recorded successfully'
        ];
    }

    /**
     * Get airspace violations
     */
    public function getViolations($status = 'open', $type = null)
    {
        $whereClause = "WHERE status = ?";
        $params = [$status];

        if ($type) {
            $whereClause .= " AND violation_type = ?";
            $params[] = $type;
        }

        $stmt = $this->db->prepare("
            SELECT
                av.*,
                dr.registration_number,
                dr.owner_name
            FROM airspace_violations av
            LEFT JOIN drone_registrations dr ON av.drone_id = dr.drone_id
            $whereClause
            ORDER BY av.violation_timestamp DESC
        ");

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get operator certifications
     */
    public function getOperatorCertifications($status = 'active', $type = null)
    {
        $whereClause = "WHERE status = ?";
        $params = [$status];

        if ($type) {
            $whereClause .= " AND certification_type = ?";
            $params[] = $type;
        }

        $stmt = $this->db->prepare("
            SELECT
                oc.*,
                (
                    SELECT COUNT(*)
                    FROM drone_flight_plans
                    WHERE operator_id = oc.operator_id
                    AND DATE(planned_departure) >= CURRENT_DATE - INTERVAL '30 days'
                ) as recent_flights
            FROM operator_certifications oc
            $whereClause
            ORDER BY oc.expiry_date ASC, oc.certification_class DESC
        ");

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get no-fly zones
     */
    public function getNoFlyZones($type = null, $activeOnly = true)
    {
        $whereClause = $activeOnly ? "WHERE (effective_end IS NULL OR effective_end > CURRENT_TIMESTAMP)" : "";
        $params = [];

        if ($type) {
            $whereClause .= ($activeOnly ? " AND" : "WHERE") . " zone_type = ?";
            $params[] = $type;
        }

        $stmt = $this->db->prepare("
            SELECT * FROM no_fly_zones
            $whereClause
            ORDER BY zone_type, zone_name
        ");

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Check airspace conflicts
     */
    public function checkAirspaceConflicts($flightPath, $altitudeMin, $altitudeMax, $startTime, $endTime)
    {
        $stmt = $this->db->prepare("
            SELECT check_airspace_conflicts(?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            json_encode($flightPath),
            $altitudeMin,
            $altitudeMax,
            $startTime,
            $endTime
        ]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return json_decode($result['check_airspace_conflicts'], true);
    }

    /**
     * Validate drone registration
     */
    public function validateDroneRegistration($droneId)
    {
        $stmt = $this->db->prepare("
            SELECT validate_drone_registration(?)
        ");

        $stmt->execute([$droneId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return json_decode($result['validate_drone_registration'], true);
    }

    /**
     * Get drone dashboard data
     */
    public function getDashboardData()
    {
        $stmt = $this->db->prepare("
            SELECT json_build_object(
                'active_drones', (
                    SELECT COUNT(*) FROM drone_registrations
                    WHERE operational_status = 'active'
                ),
                'flights_today', (
                    SELECT COUNT(*) FROM drone_flight_operations
                    WHERE DATE(actual_departure) = CURRENT_DATE
                ),
                'pending_approvals', (
                    SELECT COUNT(*) FROM drone_flight_plans
                    WHERE status = 'planned' AND approval_required = true
                ),
                'airspace_reservations', (
                    SELECT COUNT(*) FROM airspace_reservations
                    WHERE status = 'active'
                ),
                'active_violations', (
                    SELECT COUNT(*) FROM airspace_violations
                    WHERE status = 'open'
                ),
                'maintenance_due', (
                    SELECT COUNT(*) FROM drone_maintenance_logs
                    WHERE next_maintenance_date <= CURRENT_DATE + INTERVAL '7 days'
                ),
                'compliance_issues', (
                    SELECT COUNT(*) FROM drone_compliance_records
                    WHERE compliance_status IN ('warning', 'non_compliant')
                    AND expiry_date <= CURRENT_DATE + INTERVAL '30 days'
                ),
                'recent_incidents', (
                    SELECT json_agg(
                        json_build_object(
                            'incident_id', incident_id,
                            'drone_id', drone_id,
                            'incident_type', incident_type,
                            'severity_level', severity_level,
                            'reported_at', reported_at
                        )
                    )
                    FROM drone_incidents
                    ORDER BY reported_at DESC
                    LIMIT 5
                ),
                'traffic_density', (
                    SELECT json_build_object(
                        'low', COUNT(CASE WHEN traffic_density = 'low' THEN 1 END),
                        'medium', COUNT(CASE WHEN traffic_density = 'medium' THEN 1 END),
                        'high', COUNT(CASE WHEN traffic_density = 'high' THEN 1 END),
                        'restricted', COUNT(CASE WHEN traffic_density = 'restricted' THEN 1 END)
                    )
                    FROM drone_traffic_management
                    WHERE time_slot >= CURRENT_TIMESTAMP - INTERVAL '1 hour'
                )
            ) as dashboard_data
        ");

        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return json_decode($result['dashboard_data'], true);
    }

    /**
     * Generate drone operations report
     */
    public function generateOperationsReport($startDate, $endDate)
    {
        $stmt = $this->db->prepare("
            SELECT json_build_object(
                'period', json_build_object('start_date', p_start_date, 'end_date', p_end_date),
                'flight_operations', json_build_object(
                    'total_flights', (
                        SELECT COUNT(*) FROM drone_flight_operations
                        WHERE DATE(actual_departure) BETWEEN ? AND ?
                    ),
                    'by_purpose', (
                        SELECT json_agg(
                            json_build_object(
                                'purpose', purpose,
                                'count', COUNT(*),
                                'avg_duration', ROUND(AVG(actual_duration_minutes), 2)
                            )
                        )
                        FROM drone_flight_operations dfo
                        JOIN drone_flight_plans dfp ON dfo.flight_plan_id = dfp.flight_plan_id
                        WHERE DATE(dfo.actual_departure) BETWEEN ? AND ?
                        GROUP BY purpose
                    ),
                    'completion_rate', ROUND(
                        (SELECT COUNT(*) FROM drone_flight_operations
                         WHERE DATE(actual_departure) BETWEEN ? AND ?
                         AND status = 'completed')::DECIMAL /
                        (SELECT COUNT(*) FROM drone_flight_operations
                         WHERE DATE(actual_departure) BETWEEN ? AND ?
                         AND status != 'aborted')::DECIMAL * 100, 2
                    )
                ),
                'safety_metrics', json_build_object(
                    'total_incidents', (
                        SELECT COUNT(*) FROM drone_incidents
                        WHERE DATE(reported_at) BETWEEN ? AND ?
                    ),
                    'incident_rate', ROUND(
                        (SELECT COUNT(*) FROM drone_incidents
                         WHERE DATE(reported_at) BETWEEN ? AND ?)::DECIMAL /
                        (SELECT COUNT(*) FROM drone_flight_operations
                         WHERE DATE(actual_departure) BETWEEN ? AND ?)::DECIMAL * 100, 2
                    ),
                    'violations_count', (
                        SELECT COUNT(*) FROM airspace_violations
                        WHERE DATE(violation_timestamp) BETWEEN ? AND ?
                    )
                ),
                'compliance_status', json_build_object(
                    'compliant_registrations', (
                        SELECT COUNT(*) FROM drone_registrations
                        WHERE operational_status = 'active'
                        AND registration_expiry > CURRENT_DATE
                    ),
                    'expired_registrations', (
                        SELECT COUNT(*) FROM drone_registrations
                        WHERE registration_expiry <= CURRENT_DATE
                    ),
                    'maintenance_compliance', ROUND(
                        (SELECT COUNT(*) FROM drone_maintenance_logs
                         WHERE maintenance_date BETWEEN ? AND ?
                         AND maintenance_status = 'completed')::DECIMAL /
                        (SELECT COUNT(*) FROM drone_maintenance_logs
                         WHERE maintenance_date BETWEEN ? AND ?)::DECIMAL * 100, 2
                    )
                ),
                'airspace_utilization', json_build_object(
                    'reservations_approved', (
                        SELECT COUNT(*) FROM airspace_reservations
                        WHERE status = 'approved'
                        AND reservation_start BETWEEN ? AND ?
                    ),
                    'peak_traffic_hours', (
                        SELECT json_agg(
                            json_build_object(
                                'hour', EXTRACT(HOUR FROM time_slot),
                                'avg_drones', ROUND(AVG(current_active_drones), 2)
                            )
                        )
                        FROM drone_traffic_management
                        WHERE DATE(time_slot) BETWEEN ? AND ?
                        GROUP BY EXTRACT(HOUR FROM time_slot)
                        ORDER BY EXTRACT(HOUR FROM time_slot)
                    )
                )
            ) as report_data
            FROM (SELECT ? as p_start_date, ? as p_end_date) params
        ");

        $params = array_fill(0, 16, $startDate);
        $params = array_merge($params, array_fill(0, 16, $endDate));

        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return json_decode($result['report_data'], true);
    }

    // Helper methods

    private function generateDroneId()
    {
        return 'DRONE-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
    }

    private function generateOperationId()
    {
        return 'OP-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
    }

    private function generateReservationId()
    {
        return 'RES-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
    }

    private function generateIncidentId()
    {
        return 'INC-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
    }

    private function generateReservationNumber()
    {
        return 'AR-' . date('YmdHis');
    }

    private function generateIncidentNumber()
    {
        return 'DI-' . date('YmdHis');
    }
}
