<?php

/**
 * Infrastructure Management Model
 *
 * Manages building systems, IoT sensors, utilities, and facility monitoring
 */

class InfrastructureManagement
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
        $this->logger = new Logger('infrastructure_management');
    }

    /**
     * Get building systems
     */
    public function getBuildingSystems($type = null, $status = null)
    {
        $whereClause = "";
        $params = [];

        if ($type) {
            $whereClause .= " AND system_type = ?";
            $params[] = $type;
        }

        if ($status) {
            $whereClause .= " AND operational_status = ?";
            $params[] = $status;
        }

        $stmt = $this->db->prepare("
            SELECT
                bs.*,
                get_system_health_score(bs.system_id) as health_score,
                (
                    SELECT COUNT(*)
                    FROM facility_alerts
                    WHERE system_id = bs.system_id
                    AND alert_status = 'active'
                    AND severity_level IN ('high', 'critical')
                ) as active_alerts,
                (
                    SELECT COUNT(*)
                    FROM maintenance_work_orders
                    WHERE system_id = bs.system_id
                    AND work_order_status IN ('open', 'assigned', 'in_progress')
                ) as pending_work_orders
            FROM building_systems bs
            WHERE 1=1 $whereClause
            ORDER BY bs.system_type, bs.operational_status, bs.building_name
        ");

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get IoT sensors
     */
    public function getIoTSensors($type = null, $status = null, $systemId = null)
    {
        $whereClause = "";
        $params = [];

        if ($type) {
            $whereClause .= " AND sensor_type = ?";
            $params[] = $type;
        }

        if ($status) {
            $whereClause .= " AND operational_status = ?";
            $params[] = $status;
        }

        if ($systemId) {
            $whereClause .= " AND building_system_id = ?";
            $params[] = $systemId;
        }

        $stmt = $this->db->prepare("
            SELECT
                s.*,
                bs.system_name,
                bs.building_name,
                (
                    SELECT COUNT(*)
                    FROM sensor_readings
                    WHERE sensor_id = s.sensor_id
                    AND DATE(reading_timestamp) = CURRENT_DATE
                ) as readings_today,
                (
                    SELECT COUNT(*)
                    FROM sensor_readings
                    WHERE sensor_id = s.sensor_id
                    AND alert_triggered = true
                    AND DATE(reading_timestamp) >= CURRENT_DATE - INTERVAL '7 days'
                ) as alerts_last_week
            FROM iot_sensors s
            LEFT JOIN building_systems bs ON s.building_system_id = bs.system_id
            WHERE 1=1 $whereClause
            ORDER BY s.sensor_type, s.operational_status, s.location
        ");

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Record sensor reading
     */
    public function recordSensorReading($sensorId, $readingValue, $readingUnit = null, $environmentalConditions = [])
    {
        $this->logger->info("Recording sensor reading", [
            'sensor_id' => $sensorId,
            'value' => $readingValue
        ]);

        $stmt = $this->db->prepare("
            SELECT record_sensor_reading(?, ?, ?, ?)
        ");

        $stmt->execute([
            $sensorId,
            $readingValue,
            $readingUnit,
            json_encode($environmentalConditions)
        ]);

        return [
            'reading_id' => $stmt->fetchColumn(),
            'status' => 'recorded',
            'message' => 'Sensor reading recorded successfully'
        ];
    }

    /**
     * Get sensor readings
     */
    public function getSensorReadings($sensorId, $startDate = null, $endDate = null, $limit = 100)
    {
        $whereClause = "WHERE sensor_id = ?";
        $params = [$sensorId];

        if ($startDate) {
            $whereClause .= " AND reading_timestamp >= ?";
            $params[] = $startDate;
        }

        if ($endDate) {
            $whereClause .= " AND reading_timestamp <= ?";
            $params[] = $endDate;
        }

        $stmt = $this->db->prepare("
            SELECT
                sr.*,
                s.sensor_name,
                s.sensor_type,
                s.measurement_unit
            FROM sensor_readings sr
            JOIN iot_sensors s ON sr.sensor_id = s.sensor_id
            $whereClause
            ORDER BY sr.reading_timestamp DESC
            LIMIT ?
        ");

        $params[] = $limit;
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Record utilities consumption
     */
    public function recordUtilitiesConsumption($consumptionData)
    {
        $this->logger->info("Recording utilities consumption", $consumptionData);

        $stmt = $this->db->prepare("
            INSERT INTO utilities_monitoring (
                utility_type, meter_id, meter_name, location, building_name,
                floor_number, consumption_value, consumption_unit, cost_per_unit,
                total_cost, peak_demand, peak_demand_time, utility_provider
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $consumptionData['utility_type'],
            $consumptionData['meter_id'] ?? null,
            $consumptionData['meter_name'] ?? null,
            $consumptionData['location'] ?? null,
            $consumptionData['building_name'] ?? null,
            $consumptionData['floor_number'] ?? null,
            $consumptionData['consumption_value'],
            $consumptionData['consumption_unit'] ?? null,
            $consumptionData['cost_per_unit'] ?? null,
            $consumptionData['total_cost'] ?? null,
            $consumptionData['peak_demand'] ?? null,
            $consumptionData['peak_demand_time'] ?? null,
            $consumptionData['utility_provider'] ?? null
        ]);

        return [
            'monitoring_id' => $this->db->lastInsertId(),
            'status' => 'recorded',
            'message' => 'Utilities consumption recorded successfully'
        ];
    }

    /**
     * Get utilities consumption
     */
    public function getUtilitiesConsumption($utilityType = null, $startDate = null, $endDate = null, $buildingName = null)
    {
        $whereClause = "";
        $params = [];

        if ($utilityType) {
            $whereClause .= " AND utility_type = ?";
            $params[] = $utilityType;
        }

        if ($startDate) {
            $whereClause .= " AND reading_timestamp >= ?";
            $params[] = $startDate;
        }

        if ($endDate) {
            $whereClause .= " AND reading_timestamp <= ?";
            $params[] = $endDate;
        }

        if ($buildingName) {
            $whereClause .= " AND building_name = ?";
            $params[] = $buildingName;
        }

        $stmt = $this->db->prepare("
            SELECT * FROM utilities_monitoring
            WHERE 1=1 $whereClause
            ORDER BY reading_timestamp DESC
            LIMIT 1000
        ");

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Record energy consumption
     */
    public function recordEnergyConsumption($energyData)
    {
        $this->logger->info("Recording energy consumption", $energyData);

        $stmt = $this->db->prepare("
            INSERT INTO energy_management (
                date, building_name, total_energy_consumption, energy_consumption_unit,
                peak_demand, peak_demand_time, energy_cost, energy_cost_currency,
                renewable_energy_percentage, carbon_emissions, carbon_emissions_unit,
                energy_efficiency_rating, baseline_consumption, energy_savings,
                energy_savings_percentage, optimization_recommendations
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $energyData['date'] ?? date('Y-m-d'),
            $energyData['building_name'] ?? null,
            $energyData['total_energy_consumption'],
            $energyData['energy_consumption_unit'] ?? 'kWh',
            $energyData['peak_demand'] ?? null,
            $energyData['peak_demand_time'] ?? null,
            $energyData['energy_cost'] ?? null,
            $energyData['energy_cost_currency'] ?? 'USD',
            $energyData['renewable_energy_percentage'] ?? null,
            $energyData['carbon_emissions'] ?? null,
            $energyData['carbon_emissions_unit'] ?? 'kgCO2',
            $energyData['energy_efficiency_rating'] ?? null,
            $energyData['baseline_consumption'] ?? null,
            $energyData['energy_savings'] ?? null,
            $energyData['energy_savings_percentage'] ?? null,
            isset($energyData['optimization_recommendations']) ? json_encode($energyData['optimization_recommendations']) : '[]'
        ]);

        return [
            'energy_id' => $this->db->lastInsertId(),
            'status' => 'recorded',
            'message' => 'Energy consumption recorded successfully'
        ];
    }

    /**
     * Get facility zones
     */
    public function getFacilityZones($type = null, $status = null)
    {
        $whereClause = "";
        $params = [];

        if ($type) {
            $whereClause .= " AND zone_type = ?";
            $params[] = $type;
        }

        if ($status) {
            $whereClause .= " AND operational_status = ?";
            $params[] = $status;
        }

        $stmt = $this->db->prepare("
            SELECT
                fz.*,
                calculate_facility_utilization(fz.zone_id) as utilization_rate,
                (
                    SELECT COUNT(*)
                    FROM iot_sensors
                    WHERE zone_id = fz.zone_id
                ) as sensor_count,
                (
                    SELECT COUNT(*)
                    FROM building_systems
                    WHERE building_name = fz.building_name
                ) as system_count
            FROM facility_zones fz
            WHERE 1=1 $whereClause
            ORDER BY fz.zone_type, fz.building_name, fz.zone_name
        ");

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Create maintenance work order
     */
    public function createMaintenanceWorkOrder($workOrderData, $createdBy)
    {
        $this->logger->info("Creating maintenance work order", $workOrderData);

        $stmt = $this->db->prepare("
            SELECT create_maintenance_work_order(?, ?)
        ");

        $stmt->execute([
            json_encode($workOrderData),
            $createdBy
        ]);

        return [
            'work_order_id' => $stmt->fetchColumn(),
            'status' => 'created',
            'message' => 'Maintenance work order created successfully'
        ];
    }

    /**
     * Get maintenance work orders
     */
    public function getMaintenanceWorkOrders($status = null, $type = null, $priority = null)
    {
        $whereClause = "";
        $params = [];

        if ($status) {
            $whereClause .= " AND work_order_status = ?";
            $params[] = $status;
        }

        if ($type) {
            $whereClause .= " AND work_order_type = ?";
            $params[] = $type;
        }

        if ($priority) {
            $whereClause .= " AND priority_level = ?";
            $params[] = $priority;
        }

        $stmt = $this->db->prepare("
            SELECT
                mwo.*,
                bs.system_name,
                fz.zone_name,
                fz.building_name,
                s.sensor_name,
                (
                    SELECT COUNT(*)
                    FROM facility_alerts
                    WHERE system_id = mwo.system_id
                    AND alert_status = 'active'
                ) as related_alerts
            FROM maintenance_work_orders mwo
            LEFT JOIN building_systems bs ON mwo.system_id = bs.system_id
            LEFT JOIN facility_zones fz ON mwo.zone_id = fz.zone_id
            LEFT JOIN iot_sensors s ON mwo.sensor_id = s.sensor_id
            WHERE 1=1 $whereClause
            ORDER BY
                CASE mwo.priority_level
                    WHEN 'critical' THEN 1
                    WHEN 'high' THEN 2
                    WHEN 'medium' THEN 3
                    WHEN 'low' THEN 4
                END,
                mwo.reported_at DESC
        ");

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Update work order status
     */
    public function updateWorkOrderStatus($workOrderId, $status, $updatedBy, $notes = null)
    {
        $stmt = $this->db->prepare("
            UPDATE maintenance_work_orders
            SET work_order_status = ?, updated_at = CURRENT_TIMESTAMP
            WHERE work_order_id = ?
        ");
        $stmt->execute([$status, $workOrderId]);

        // Set timestamps based on status
        $timestampFields = [
            'assigned' => 'assigned_at',
            'in_progress' => 'actual_start',
            'completed' => 'actual_end'
        ];

        if (isset($timestampFields[$status])) {
            $stmt = $this->db->prepare("
                UPDATE maintenance_work_orders
                SET {$timestampFields[$status]} = CURRENT_TIMESTAMP
                WHERE work_order_id = ?
            ");
            $stmt->execute([$workOrderId]);
        }

        // Add completion notes if provided
        if ($notes && $status === 'completed') {
            $stmt = $this->db->prepare("
                UPDATE maintenance_work_orders
                SET completion_notes = ?
                WHERE work_order_id = ?
            ");
            $stmt->execute([$notes, $workOrderId]);
        }

        return [
            'work_order_id' => $workOrderId,
            'status' => 'updated',
            'message' => 'Work order status updated successfully'
        ];
    }

    /**
     * Record facility inspection
     */
    public function recordFacilityInspection($inspectionData)
    {
        $this->logger->info("Recording facility inspection", $inspectionData);

        $stmt = $this->db->prepare("
            INSERT INTO facility_inspections (
                inspection_id, inspection_number, inspection_type, zone_id, system_id,
                inspection_date, inspector_name, inspector_certification, inspection_criteria,
                findings, critical_issues, major_issues, minor_issues, recommendations,
                compliance_status, follow_up_required, follow_up_date, inspection_duration_minutes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $inspectionId = $this->generateInspectionId();
        $inspectionNumber = $this->generateInspectionNumber();

        $stmt->execute([
            $inspectionId,
            $inspectionNumber,
            $inspectionData['inspection_type'],
            $inspectionData['zone_id'] ?? null,
            $inspectionData['system_id'] ?? null,
            $inspectionData['inspection_date'] ?? date('c'),
            $inspectionData['inspector_name'],
            $inspectionData['inspector_certification'] ?? null,
            isset($inspectionData['inspection_criteria']) ? json_encode($inspectionData['inspection_criteria']) : '[]',
            isset($inspectionData['findings']) ? json_encode($inspectionData['findings']) : '[]',
            $inspectionData['critical_issues'] ?? 0,
            $inspectionData['major_issues'] ?? 0,
            $inspectionData['minor_issues'] ?? 0,
            isset($inspectionData['recommendations']) ? json_encode($inspectionData['recommendations']) : '[]',
            $inspectionData['compliance_status'] ?? 'compliant',
            $inspectionData['follow_up_required'] ?? false,
            $inspectionData['follow_up_date'] ?? null,
            $inspectionData['inspection_duration_minutes'] ?? null
        ]);

        return [
            'inspection_id' => $inspectionId,
            'inspection_number' => $inspectionNumber,
            'status' => 'recorded',
            'message' => 'Facility inspection recorded successfully'
        ];
    }

    /**
     * Get facility inspections
     */
    public function getFacilityInspections($type = null, $status = null, $startDate = null, $endDate = null)
    {
        $whereClause = "";
        $params = [];

        if ($type) {
            $whereClause .= " AND inspection_type = ?";
            $params[] = $type;
        }

        if ($status) {
            $whereClause .= " AND compliance_status = ?";
            $params[] = $status;
        }

        if ($startDate) {
            $whereClause .= " AND inspection_date >= ?";
            $params[] = $startDate;
        }

        if ($endDate) {
            $whereClause .= " AND inspection_date <= ?";
            $params[] = $endDate;
        }

        $stmt = $this->db->prepare("
            SELECT
                fi.*,
                fz.zone_name,
                fz.building_name,
                bs.system_name
            FROM facility_inspections fi
            LEFT JOIN facility_zones fz ON fi.zone_id = fz.zone_id
            LEFT JOIN building_systems bs ON fi.system_id = bs.system_id
            WHERE 1=1 $whereClause
            ORDER BY fi.inspection_date DESC
        ");

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Record environmental monitoring
     */
    public function recordEnvironmentalMonitoring($monitoringData)
    {
        $stmt = $this->db->prepare("
            INSERT INTO environmental_monitoring (
                zone_id, monitoring_date, temperature_celsius, humidity_percentage,
                co2_ppm, voc_ppm, particulate_matter_ugm3, noise_level_db,
                light_level_lux, air_pressure_hpa, air_quality_index, comfort_index,
                environmental_compliance, recommendations
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $monitoringData['zone_id'],
            $monitoringData['monitoring_date'] ?? date('c'),
            $monitoringData['temperature_celsius'] ?? null,
            $monitoringData['humidity_percentage'] ?? null,
            $monitoringData['co2_ppm'] ?? null,
            $monitoringData['voc_ppm'] ?? null,
            $monitoringData['particulate_matter_ugm3'] ?? null,
            $monitoringData['noise_level_db'] ?? null,
            $monitoringData['light_level_lux'] ?? null,
            $monitoringData['air_pressure_hpa'] ?? null,
            $monitoringData['air_quality_index'] ?? null,
            $monitoringData['comfort_index'] ?? null,
            $monitoringData['environmental_compliance'] ?? 'compliant',
            isset($monitoringData['recommendations']) ? json_encode($monitoringData['recommendations']) : '[]'
        ]);

        return [
            'monitoring_id' => $this->db->lastInsertId(),
            'status' => 'recorded',
            'message' => 'Environmental monitoring recorded successfully'
        ];
    }

    /**
     * Get environmental monitoring data
     */
    public function getEnvironmentalMonitoring($zoneId = null, $startDate = null, $endDate = null)
    {
        $whereClause = "";
        $params = [];

        if ($zoneId) {
            $whereClause .= " AND zone_id = ?";
            $params[] = $zoneId;
        }

        if ($startDate) {
            $whereClause .= " AND monitoring_date >= ?";
            $params[] = $startDate;
        }

        if ($endDate) {
            $whereClause .= " AND monitoring_date <= ?";
            $params[] = $endDate;
        }

        $stmt = $this->db->prepare("
            SELECT
                em.*,
                fz.zone_name,
                fz.building_name
            FROM environmental_monitoring em
            LEFT JOIN facility_zones fz ON em.zone_id = fz.zone_id
            WHERE 1=1 $whereClause
            ORDER BY em.monitoring_date DESC
            LIMIT 1000
        ");

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Record access log
     */
    public function recordAccessLog($accessData)
    {
        $stmt = $this->db->prepare("
            INSERT INTO access_logs (
                access_point_id, person_id, person_name, person_type, access_card_number,
                access_method, access_result, denial_reason, zone_entered, zone_exited,
                duration_minutes, security_notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $accessData['access_point_id'],
            $accessData['person_id'] ?? null,
            $accessData['person_name'] ?? null,
            $accessData['person_type'] ?? 'employee',
            $accessData['access_card_number'] ?? null,
            $accessData['access_method'] ?? 'card',
            $accessData['access_result'] ?? 'granted',
            $accessData['denial_reason'] ?? null,
            $accessData['zone_entered'] ?? null,
            $accessData['zone_exited'] ?? null,
            $accessData['duration_minutes'] ?? null,
            $accessData['security_notes'] ?? null
        ]);

        return [
            'log_id' => $this->db->lastInsertId(),
            'status' => 'recorded',
            'message' => 'Access log recorded successfully'
        ];
    }

    /**
     * Get access logs
     */
    public function getAccessLogs($accessPointId = null, $startDate = null, $endDate = null, $personType = null)
    {
        $whereClause = "";
        $params = [];

        if ($accessPointId) {
            $whereClause .= " AND access_point_id = ?";
            $params[] = $accessPointId;
        }

        if ($startDate) {
            $whereClause .= " AND access_timestamp >= ?";
            $params[] = $startDate;
        }

        if ($endDate) {
            $whereClause .= " AND access_timestamp <= ?";
            $params[] = $endDate;
        }

        if ($personType) {
            $whereClause .= " AND person_type = ?";
            $params[] = $personType;
        }

        $stmt = $this->db->prepare("
            SELECT
                al.*,
                fac.access_point_name,
                fac.zone_id,
                fz.zone_name,
                fz.building_name
            FROM access_logs al
            LEFT JOIN facility_access_control fac ON al.access_point_id = fac.access_id
            LEFT JOIN facility_zones fz ON fac.zone_id = fz.zone_id
            WHERE 1=1 $whereClause
            ORDER BY al.access_timestamp DESC
            LIMIT 1000
        ");

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get facility alerts
     */
    public function getFacilityAlerts($status = 'active', $severity = null, $type = null)
    {
        $whereClause = "WHERE alert_status = ?";
        $params = [$status];

        if ($severity) {
            $whereClause .= " AND severity_level = ?";
            $params[] = $severity;
        }

        if ($type) {
            $whereClause .= " AND alert_type = ?";
            $params[] = $type;
        }

        $stmt = $this->db->prepare("
            SELECT
                fa.*,
                bs.system_name,
                s.sensor_name,
                fz.zone_name,
                fz.building_name
            FROM facility_alerts fa
            LEFT JOIN building_systems bs ON fa.system_id = bs.system_id
            LEFT JOIN iot_sensors s ON fa.sensor_id = s.sensor_id
            LEFT JOIN facility_zones fz ON fa.zone_id = fz.zone_id
            $whereClause
            ORDER BY
                CASE fa.severity_level
                    WHEN 'critical' THEN 1
                    WHEN 'high' THEN 2
                    WHEN 'medium' THEN 3
                    WHEN 'low' THEN 4
                END,
                fa.created_at DESC
        ");

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Update alert status
     */
    public function updateAlertStatus($alertId, $status, $updatedBy, $resolutionNotes = null)
    {
        $stmt = $this->db->prepare("
            UPDATE facility_alerts
            SET alert_status = ?, updated_at = CURRENT_TIMESTAMP
            WHERE alert_id = ?
        ");
        $stmt->execute([$status, $alertId]);

        // Set resolution details if resolved
        if ($status === 'resolved') {
            $stmt = $this->db->prepare("
                UPDATE facility_alerts
                SET resolved_by = ?, resolved_at = CURRENT_TIMESTAMP, resolution_notes = ?
                WHERE alert_id = ?
            ");
            $stmt->execute([$updatedBy, $resolutionNotes, $alertId]);
        }

        return [
            'alert_id' => $alertId,
            'status' => 'updated',
            'message' => 'Alert status updated successfully'
        ];
    }

    /**
     * Get asset management data
     */
    public function getAssetManagement($type = null, $status = null)
    {
        $whereClause = "";
        $params = [];

        if ($type) {
            $whereClause .= " AND asset_type = ?";
            $params[] = $type;
        }

        if ($status) {
            $whereClause .= " AND operational_status = ?";
            $params[] = $status;
        }

        $stmt = $this->db->prepare("
            SELECT
                am.*,
                fz.zone_name,
                fz.building_name,
                (
                    SELECT COUNT(*)
                    FROM maintenance_work_orders
                    WHERE system_id IN (
                        SELECT system_id FROM building_systems
                        WHERE building_name = fz.building_name
                    )
                    AND work_order_status IN ('open', 'assigned', 'in_progress')
                ) as pending_maintenance
            FROM asset_management am
            LEFT JOIN facility_zones fz ON am.zone_id = fz.zone_id
            WHERE 1=1 $whereClause
            ORDER BY am.asset_type, am.operational_status, am.last_condition_assessment DESC
        ");

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get infrastructure dashboard data
     */
    public function getDashboardData()
    {
        $stmt = $this->db->prepare("
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
            ) as dashboard_data
        ");

        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return json_decode($result['dashboard_data'], true);
    }

    /**
     * Generate facility performance report
     */
    public function generatePerformanceReport($startDate, $endDate)
    {
        $stmt = $this->db->prepare("
            SELECT json_build_object(
                'period', json_build_object('start_date', p_start_date, 'end_date', p_end_date),
                'system_performance', json_build_object(
                    'overall_health_score', ROUND(AVG(get_system_health_score(system_id)), 2),
                    'operational_uptime', ROUND(
                        COUNT(CASE WHEN operational_status = 'operational' THEN 1 END)::DECIMAL /
                        COUNT(*)::DECIMAL * 100, 2
                    ),
                    'maintenance_compliance', ROUND(
                        COUNT(CASE WHEN last_maintenance >= CURRENT_DATE - INTERVAL '1 year' THEN 1 END)::DECIMAL /
                        COUNT(*)::DECIMAL * 100, 2
                    )
                ),
                'sensor_performance', json_build_object(
                    'active_sensors', COUNT(CASE WHEN operational_status = 'active' THEN 1 END),
                    'sensor_uptime', ROUND(
                        COUNT(CASE WHEN operational_status = 'active' THEN 1 END)::DECIMAL /
                        COUNT(*)::DECIMAL * 100, 2
                    ),
                    'alerts_generated', (
                        SELECT COUNT(*) FROM sensor_readings
                        WHERE alert_triggered = true
                        AND DATE(reading_timestamp) BETWEEN ? AND ?
                    )
                ),
                'maintenance_metrics', json_build_object(
                    'work_orders_completed', (
                        SELECT COUNT(*) FROM maintenance_work_orders
                        WHERE work_order_status = 'completed'
                        AND DATE(actual_end) BETWEEN ? AND ?
                    ),
                    'average_completion_time', (
                        SELECT ROUND(AVG(EXTRACT(EPOCH FROM (actual_end - reported_at))/3600), 2)
                        FROM maintenance_work_orders
                        WHERE work_order_status = 'completed'
                        AND DATE(actual_end) BETWEEN ? AND ?
                    ),
                    'preventive_maintenance_ratio', ROUND(
                        COUNT(CASE WHEN work_order_type = 'preventive' THEN 1 END)::DECIMAL /
                        COUNT(*)::DECIMAL * 100, 2
                    )
                ),
                'energy_efficiency', json_build_object(
                    'total_energy_consumption', SUM(total_energy_consumption),
                    'average_efficiency_rating', ROUND(AVG(energy_efficiency_rating), 2),
                    'energy_savings_achieved', SUM(energy_savings),
                    'renewable_energy_percentage', ROUND(AVG(renewable_energy_percentage), 2)
                ),
                'environmental_compliance', json_build_object(
                    'compliant_readings', COUNT(CASE WHEN environmental_compliance = 'compliant' THEN 1 END),
                    'warning_readings', COUNT(CASE WHEN environmental_compliance = 'warning' THEN 1 END),
                    'non_compliant_readings', COUNT(CASE WHEN environmental_compliance = 'non_compliant' THEN 1 END),
                    'compliance_rate', ROUND(
                        COUNT(CASE WHEN environmental_compliance = 'compliant' THEN 1 END)::DECIMAL /
                        COUNT(*)::DECIMAL * 100, 2
                    )
                )
            ) as report_data
            FROM building_systems bs
            CROSS JOIN iot_sensors s
            CROSS JOIN environmental_monitoring em
            CROSS JOIN maintenance_work_orders mwo
            CROSS JOIN energy_management en
            WHERE em.monitoring_date BETWEEN ? AND ?
            AND mwo.reported_at BETWEEN ? AND ?
            AND en.date BETWEEN ? AND ?
        ");

        $params = array_fill(0, 8, $startDate);
        $params = array_merge($params, array_fill(0, 8, $endDate));

        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return json_decode($result['report_data'], true);
    }

    // Helper methods

    private function generateInspectionId()
    {
        return 'INSP-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
    }

    private function generateInspectionNumber()
    {
        return 'INSPECTION-' . date('YmdHis');
    }
}
