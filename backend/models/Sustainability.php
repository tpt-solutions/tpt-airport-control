<?php

/**
 * Sustainability Model
 *
 * Manages environmental monitoring, carbon emissions, and green initiatives
 */

class Sustainability
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
        $this->logger = new Logger('sustainability');
    }

    /**
     * Record carbon emissions for a flight
     */
    public function recordFlightEmissions($flightData)
    {
        $this->logger->info("Recording flight emissions", $flightData);

        // Calculate emissions if not provided
        if (!isset($flightData['co2_emitted'])) {
            $flightData['co2_emitted'] = $this->calculateEmissions(
                $flightData['fuel_consumed'],
                $flightData['fuel_type'] ?? 'jet_fuel'
            );
        }

        $stmt = $this->db->prepare("
            INSERT INTO carbon_emissions (
                flight_id, aircraft_type, fuel_type, fuel_consumed,
                distance_flown, emission_factor, co2_emitted, nox_emitted,
                measurement_date, data_source, confidence_level
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $flightData['flight_id'],
            $flightData['aircraft_type'],
            $flightData['fuel_type'] ?? 'jet_fuel',
            $flightData['fuel_consumed'],
            $flightData['distance_flown'],
            $flightData['emission_factor'] ?? 3.15,
            $flightData['co2_emitted'],
            $flightData['nox_emitted'] ?? null,
            $flightData['measurement_date'] ?? date('Y-m-d H:i:s'),
            $flightData['data_source'] ?? 'calculated',
            $flightData['confidence_level'] ?? 85
        ]);

        return ['status' => 'success', 'message' => 'Emissions recorded successfully'];
    }

    /**
     * Calculate CO2 emissions based on fuel type and consumption
     */
    private function calculateEmissions($fuelConsumed, $fuelType = 'jet_fuel')
    {
        $emissionFactors = [
            'jet_fuel' => 3.15,      // kg CO2 per liter
            'avgas' => 2.25,         // kg CO2 per liter
            'diesel' => 2.68,        // kg CO2 per liter
            'electric' => 0          // kg CO2 per kWh (converted)
        ];

        $factor = $emissionFactors[$fuelType] ?? $emissionFactors['jet_fuel'];
        return $fuelConsumed * $factor;
    }

    /**
     * Record noise monitoring data
     */
    public function recordNoiseData($noiseData)
    {
        $this->logger->info("Recording noise data", $noiseData);

        $stmt = $this->db->prepare("
            INSERT INTO noise_monitoring (
                sensor_location, latitude, longitude, noise_level,
                measurement_type, aircraft_callsign, flight_id,
                wind_speed, wind_direction, temperature, humidity,
                measurement_time, sensor_status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $noiseData['sensor_location'],
            $noiseData['latitude'] ?? null,
            $noiseData['longitude'] ?? null,
            $noiseData['noise_level'],
            $noiseData['measurement_type'] ?? 'continuous',
            $noiseData['aircraft_callsign'] ?? null,
            $noiseData['flight_id'] ?? null,
            $noiseData['wind_speed'] ?? null,
            $noiseData['wind_direction'] ?? null,
            $noiseData['temperature'] ?? null,
            $noiseData['humidity'] ?? null,
            $noiseData['measurement_time'] ?? date('Y-m-d H:i:s'),
            $noiseData['sensor_status'] ?? 'active'
        ]);

        return ['status' => 'success', 'message' => 'Noise data recorded successfully'];
    }

    /**
     * Record energy consumption
     */
    public function recordEnergyConsumption($energyData)
    {
        $this->logger->info("Recording energy consumption", $energyData);

        $stmt = $this->db->prepare("
            INSERT INTO energy_consumption (
                facility_type, energy_source, consumption_kwh,
                consumption_date, peak_demand_kw, off_peak_consumption,
                carbon_intensity, cost_per_kwh
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $energyData['facility_type'],
            $energyData['energy_source'],
            $energyData['consumption_kwh'],
            $energyData['consumption_date'] ?? date('Y-m-d'),
            $energyData['peak_demand_kw'] ?? null,
            $energyData['off_peak_consumption'] ?? null,
            $energyData['carbon_intensity'] ?? null,
            $energyData['cost_per_kwh'] ?? null
        ]);

        return ['status' => 'success', 'message' => 'Energy consumption recorded successfully'];
    }

    /**
     * Record waste management data
     */
    public function recordWasteData($wasteData)
    {
        $this->logger->info("Recording waste data", $wasteData);

        $stmt = $this->db->prepare("
            INSERT INTO waste_management (
                waste_type, facility_area, weight_kg, volume_cubic_m,
                disposal_method, contractor_name, cost, collection_date,
                carbon_footprint
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $wasteData['waste_type'],
            $wasteData['facility_area'],
            $wasteData['weight_kg'],
            $wasteData['volume_cubic_m'] ?? null,
            $wasteData['disposal_method'],
            $wasteData['contractor_name'] ?? null,
            $wasteData['cost'] ?? null,
            $wasteData['collection_date'] ?? date('Y-m-d'),
            $wasteData['carbon_footprint'] ?? null
        ]);

        return ['status' => 'success', 'message' => 'Waste data recorded successfully'];
    }

    /**
     * Record water conservation data
     */
    public function recordWaterData($waterData)
    {
        $this->logger->info("Recording water data", $waterData);

        $stmt = $this->db->prepare("
            INSERT INTO water_conservation (
                facility_type, usage_type, consumption_liters,
                measurement_date, conservation_measures, recycled_percentage,
                cost_per_liter
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $waterData['facility_type'],
            $waterData['usage_type'],
            $waterData['consumption_liters'],
            $waterData['measurement_date'] ?? date('Y-m-d'),
            isset($waterData['conservation_measures']) ? json_encode($waterData['conservation_measures']) : null,
            $waterData['recycled_percentage'] ?? null,
            $waterData['cost_per_liter'] ?? null
        ]);

        return ['status' => 'success', 'message' => 'Water data recorded successfully'];
    }

    /**
     * Get sustainability dashboard data
     */
    public function getDashboardData()
    {
        $stmt = $this->db->prepare("
            SELECT
                json_build_object(
                    'current_month_emissions', COALESCE((
                        SELECT SUM(co2_emitted)
                        FROM carbon_emissions
                        WHERE DATE_TRUNC('month', measurement_date) = DATE_TRUNC('month', CURRENT_DATE)
                    ), 0),
                    'total_green_energy_capacity', COALESCE((
                        SELECT SUM(capacity_kw)
                        FROM green_energy_systems
                        WHERE status = 'operational'
                    ), 0),
                    'current_green_energy_output', COALESCE((
                        SELECT SUM(current_output_kw)
                        FROM green_energy_systems
                        WHERE status = 'operational'
                    ), 0),
                    'waste_diversion_rate', COALESCE((
                        SELECT CASE
                            WHEN SUM(weight_kg) = 0 THEN 0
                            ELSE ROUND(
                                (SUM(CASE WHEN disposal_method IN ('recycling', 'composting') THEN weight_kg ELSE 0 END) * 100.0 /
                                 SUM(weight_kg)), 2
                            )
                        END
                        FROM waste_management
                        WHERE collection_date >= CURRENT_DATE - INTERVAL '30 days'
                    ), 0),
                    'active_sensors', (
                        SELECT COUNT(*)
                        FROM environmental_sensors
                        WHERE status = 'active'
                    ),
                    'average_noise_level', COALESCE((
                        SELECT ROUND(AVG(noise_level), 2)
                        FROM noise_monitoring
                        WHERE measurement_time >= CURRENT_DATE - INTERVAL '24 hours'
                        AND sensor_status = 'active'
                    ), 0)
                ) as dashboard_data
        ");

        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return json_decode($result['dashboard_data'], true);
    }

    /**
     * Get sustainability KPIs
     */
    public function getKPIs()
    {
        $stmt = $this->db->prepare("
            SELECT
                kpi_name,
                kpi_category,
                target_value,
                current_value,
                unit,
                measurement_period,
                last_updated,
                target_achievement_date,
                status,
                CASE
                    WHEN target_value > 0 THEN
                        ROUND((current_value / target_value) * 100, 2)
                    ELSE 0
                END as achievement_percentage
            FROM sustainability_kpis
            ORDER BY kpi_category, kpi_name
        ");

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Update KPI status based on current values
     */
    public function updateKPIStatus($kpiId, $status)
    {
        $stmt = $this->db->prepare("
            UPDATE sustainability_kpis
            SET status = ?, last_updated = CURRENT_TIMESTAMP
            WHERE kpi_id = ?
        ");

        $stmt->execute([$status, $kpiId]);
        return ['status' => 'success', 'message' => 'KPI status updated'];
    }

    /**
     * Get environmental compliance records
     */
    public function getComplianceRecords($limit = 50)
    {
        $stmt = $this->db->prepare("
            SELECT * FROM environmental_compliance
            ORDER BY next_inspection_date ASC
            LIMIT ?
        ");

        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Add compliance record
     */
    public function addComplianceRecord($complianceData)
    {
        $stmt = $this->db->prepare("
            INSERT INTO environmental_compliance (
                regulation_name, regulation_body, compliance_status,
                inspection_date, next_inspection_date, findings,
                corrective_actions, fine_amount, compliance_officer
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $complianceData['regulation_name'],
            $complianceData['regulation_body'],
            $complianceData['compliance_status'],
            $complianceData['inspection_date'] ?? null,
            $complianceData['next_inspection_date'],
            $complianceData['findings'] ?? null,
            $complianceData['corrective_actions'] ?? null,
            $complianceData['fine_amount'] ?? null,
            $complianceData['compliance_officer'] ?? null
        ]);

        return ['status' => 'success', 'message' => 'Compliance record added'];
    }

    /**
     * Get environmental sensors
     */
    public function getSensors($status = null)
    {
        $whereClause = $status ? "WHERE status = ?" : "";
        $params = $status ? [$status] : [];

        $stmt = $this->db->prepare("
            SELECT
                s.*,
                (
                    SELECT json_agg(
                        json_build_object(
                            'parameter_name', sr.parameter_name,
                            'parameter_value', sr.parameter_value,
                            'unit', sr.unit,
                            'reading_timestamp', sr.reading_timestamp,
                            'data_quality', sr.data_quality
                        )
                    )
                    FROM sensor_readings sr
                    WHERE sr.sensor_id = s.sensor_id
                    AND sr.reading_timestamp >= CURRENT_TIMESTAMP - INTERVAL '1 hour'
                    ORDER BY sr.reading_timestamp DESC
                    LIMIT 10
                ) as recent_readings
            FROM environmental_sensors s
            $whereClause
            ORDER BY s.sensor_name
        ");

        $stmt->execute($params);
        $sensors = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Decode JSON for recent readings
        foreach ($sensors as &$sensor) {
            if ($sensor['recent_readings']) {
                $sensor['recent_readings'] = json_decode($sensor['recent_readings'], true);
            }
        }

        return $sensors;
    }

    /**
     * Record sensor reading
     */
    public function recordSensorReading($readingData)
    {
        $stmt = $this->db->prepare("
            INSERT INTO sensor_readings (
                sensor_id, parameter_name, parameter_value,
                unit, reading_timestamp, data_quality
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $readingData['sensor_id'],
            $readingData['parameter_name'],
            $readingData['parameter_value'],
            $readingData['unit'],
            $readingData['reading_timestamp'] ?? date('Y-m-d H:i:s'),
            $readingData['data_quality'] ?? 'good'
        ]);

        return ['status' => 'success', 'message' => 'Sensor reading recorded'];
    }

    /**
     * Get green energy systems
     */
    public function getGreenEnergySystems($status = null)
    {
        $whereClause = $status ? "WHERE status = ?" : "";
        $params = $status ? [$status] : [];

        $stmt = $this->db->prepare("
            SELECT * FROM green_energy_systems
            $whereClause
            ORDER BY system_name
        ");

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Update green energy system output
     */
    public function updateEnergyOutput($systemId, $output)
    {
        $stmt = $this->db->prepare("
            UPDATE green_energy_systems
            SET current_output_kw = ?, updated_at = CURRENT_TIMESTAMP
            WHERE system_id = ?
        ");

        $stmt->execute([$output, $systemId]);
        return ['status' => 'success', 'message' => 'Energy output updated'];
    }

    /**
     * Get emissions by date range
     */
    public function getEmissionsReport($startDate, $endDate, $groupBy = 'day')
    {
        $groupClause = match($groupBy) {
            'month' => "DATE_TRUNC('month', measurement_date)",
            'week' => "DATE_TRUNC('week', measurement_date)",
            'day' => "DATE_TRUNC('day', measurement_date)",
            default => "DATE_TRUNC('day', measurement_date)"
        };

        $stmt = $this->db->prepare("
            SELECT
                $groupClause as period,
                SUM(co2_emitted) as total_co2,
                SUM(nox_emitted) as total_nox,
                COUNT(*) as flight_count,
                AVG(fuel_consumed) as avg_fuel_consumed
            FROM carbon_emissions
            WHERE measurement_date BETWEEN ? AND ?
            GROUP BY $groupClause
            ORDER BY period
        ");

        $stmt->execute([$startDate, $endDate]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get noise monitoring report
     */
    public function getNoiseReport($startDate, $endDate)
    {
        $stmt = $this->db->prepare("
            SELECT
                DATE_TRUNC('day', measurement_time) as date,
                sensor_location,
                AVG(noise_level) as avg_noise,
                MAX(noise_level) as max_noise,
                MIN(noise_level) as min_noise,
                COUNT(*) as reading_count
            FROM noise_monitoring
            WHERE measurement_time BETWEEN ? AND ?
            AND sensor_status = 'active'
            GROUP BY DATE_TRUNC('day', measurement_time), sensor_location
            ORDER BY date, sensor_location
        ");

        $stmt->execute([$startDate, $endDate]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get energy consumption report
     */
    public function getEnergyReport($startDate, $endDate)
    {
        $stmt = $this->db->prepare("
            SELECT
                consumption_date,
                facility_type,
                energy_source,
                SUM(consumption_kwh) as total_consumption,
                AVG(carbon_intensity) as avg_carbon_intensity,
                SUM(cost_per_kwh * consumption_kwh) as total_cost
            FROM energy_consumption
            WHERE consumption_date BETWEEN ? AND ?
            GROUP BY consumption_date, facility_type, energy_source
            ORDER BY consumption_date, facility_type
        ");

        $stmt->execute([$startDate, $endDate]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Calculate carbon offset from green energy
     */
    public function calculateCarbonOffset()
    {
        $stmt = $this->db->prepare("
            SELECT
                SUM(carbon_offset_kg) as annual_offset,
                SUM(current_output_kw * 24 * 365 * 0.0004) as calculated_offset
            FROM green_energy_systems
            WHERE status = 'operational'
        ");

        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get sustainability alerts
     */
    public function getSustainabilityAlerts()
    {
        $alerts = [];

        // Check KPI status
        $kpis = $this->getKPIs();
        foreach ($kpis as $kpi) {
            if ($kpi['status'] === 'at_risk' || $kpi['status'] === 'off_track') {
                $alerts[] = [
                    'type' => 'kpi',
                    'severity' => 'warning',
                    'message' => "KPI '{$kpi['kpi_name']}' is {$kpi['status']}",
                    'data' => $kpi
                ];
            }
        }

        // Check compliance deadlines
        $stmt = $this->db->prepare("
            SELECT * FROM environmental_compliance
            WHERE next_inspection_date <= CURRENT_DATE + INTERVAL '30 days'
            AND compliance_status != 'compliant'
        ");
        $stmt->execute();
        $complianceIssues = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($complianceIssues as $issue) {
            $alerts[] = [
                'type' => 'compliance',
                'severity' => 'critical',
                'message' => "Compliance inspection due for '{$issue['regulation_name']}'",
                'data' => $issue
            ];
        }

        // Check sensor status
        $stmt = $this->db->prepare("
            SELECT * FROM environmental_sensors
            WHERE status != 'active'
            OR (battery_level IS NOT NULL AND battery_level < 20)
            OR (calibration_due IS NOT NULL AND calibration_due <= CURRENT_DATE)
        ");
        $stmt->execute();
        $sensorIssues = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($sensorIssues as $sensor) {
            $alerts[] = [
                'type' => 'sensor',
                'severity' => 'warning',
                'message' => "Sensor '{$sensor['sensor_name']}' requires attention",
                'data' => $sensor
            ];
        }

        return $alerts;
    }

    /**
     * Get database connection (for use by API functions)
     */
    public function getDatabaseConnection()
    {
        return $this->db;
    }
}
