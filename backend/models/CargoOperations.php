<?php

/**
 * Cargo Operations Model
 *
 * Manages freight forwarding, cargo terminals, customs clearance, and hazardous materials handling
 */

class CargoOperations
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
        $this->logger = new Logger('cargo_operations');
    }

    /**
     * Create a new cargo shipment
     */
    public function createShipment($shipmentData, $items = [])
    {
        $this->logger->info("Creating cargo shipment", $shipmentData);

        $this->db->beginTransaction();

        try {
            // Generate shipment ID
            $shipmentId = $this->generateShipmentId();

            // Insert shipment
            $stmt = $this->db->prepare("
                INSERT INTO cargo_shipments (
                    shipment_id, shipment_number, shipment_type, origin_airport,
                    destination_airport, shipper_name, shipper_contact,
                    consignee_name, consignee_contact, freight_forwarder,
                    carrier_name, flight_id, priority_level, service_level
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $shipmentId,
                $shipmentData['shipment_number'] ?? $shipmentId,
                $shipmentData['shipment_type'] ?? 'standard',
                $shipmentData['origin_airport'],
                $shipmentData['destination_airport'],
                $shipmentData['shipper_name'],
                isset($shipmentData['shipper_contact']) ? json_encode($shipmentData['shipper_contact']) : null,
                $shipmentData['consignee_name'],
                isset($shipmentData['consignee_contact']) ? json_encode($shipmentData['consignee_contact']) : null,
                $shipmentData['freight_forwarder'] ?? null,
                $shipmentData['carrier_name'] ?? null,
                $shipmentData['flight_id'] ?? null,
                $shipmentData['priority_level'] ?? 'standard',
                $shipmentData['service_level'] ?? 'standard'
            ]);

            // Add items if provided
            if (!empty($items)) {
                $this->addShipmentItems($shipmentId, $items);
            }

            // Create initial tracking event
            $this->trackShipmentEvent($shipmentId, 'created', 'Cargo Terminal', 'Shipment created and registered');

            $this->db->commit();

            return [
                'shipment_id' => $shipmentId,
                'status' => 'created',
                'message' => 'Cargo shipment created successfully'
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Get shipment details
     */
    public function getShipment($shipmentId)
    {
        $stmt = $this->db->prepare("
            SELECT
                cs.*,
                f.flight_number,
                f.origin,
                f.destination,
                f.departure_time,
                f.arrival_time,
                (
                    SELECT json_agg(
                        json_build_object(
                            'event_type', event_type,
                            'location', event_location,
                            'description', event_description,
                            'timestamp', recorded_at
                        ) ORDER BY recorded_at DESC
                    )
                    FROM cargo_tracking_events
                    WHERE shipment_id = cs.shipment_id
                ) as tracking_events,
                (
                    SELECT COUNT(*)
                    FROM cargo_items
                    WHERE shipment_id = cs.shipment_id
                ) as total_items,
                (
                    SELECT SUM(total_value)
                    FROM cargo_items
                    WHERE shipment_id = cs.shipment_id
                ) as total_value
            FROM cargo_shipments cs
            LEFT JOIN flights f ON cs.flight_id = f.flight_id
            WHERE cs.shipment_id = ?
        ");

        $stmt->execute([$shipmentId]);
        $shipment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$shipment) {
            throw new Exception("Shipment not found");
        }

        // Get shipment items
        $shipment['items'] = $this->getShipmentItems($shipmentId);

        return $shipment;
    }

    /**
     * Get shipments with filtering
     */
    public function getShipments($filters = [])
    {
        $whereClause = "";
        $params = [];

        if (isset($filters['status'])) {
            $whereClause .= " AND cs.shipment_status = ?";
            $params[] = $filters['status'];
        }

        if (isset($filters['origin_airport'])) {
            $whereClause .= " AND cs.origin_airport = ?";
            $params[] = $filters['origin_airport'];
        }

        if (isset($filters['destination_airport'])) {
            $whereClause .= " AND cs.destination_airport = ?";
            $params[] = $filters['destination_airport'];
        }

        if (isset($filters['freight_forwarder'])) {
            $whereClause .= " AND cs.freight_forwarder = ?";
            $params[] = $filters['freight_forwarder'];
        }

        if (isset($filters['start_date'])) {
            $whereClause .= " AND cs.booking_date >= ?";
            $params[] = $filters['start_date'];
        }

        if (isset($filters['end_date'])) {
            $whereClause .= " AND cs.booking_date <= ?";
            $params[] = $filters['end_date'];
        }

        $stmt = $this->db->prepare("
            SELECT
                cs.*,
                f.flight_number,
                (
                    SELECT COUNT(*)
                    FROM cargo_items
                    WHERE shipment_id = cs.shipment_id
                ) as total_items,
                (
                    SELECT SUM(total_value)
                    FROM cargo_items
                    WHERE shipment_id = cs.shipment_id
                ) as total_value
            FROM cargo_shipments cs
            LEFT JOIN flights f ON cs.flight_id = f.flight_id
            WHERE 1=1 $whereClause
            ORDER BY cs.booking_date DESC
            LIMIT ?
        ");

        $params[] = $filters['limit'] ?? 50;
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Add items to shipment
     */
    public function addShipmentItems($shipmentId, $items)
    {
        $stmt = $this->db->prepare("
            INSERT INTO cargo_items (
                shipment_id, item_description, harmonized_code, quantity,
                weight_kg, volume_m3, dimensions, unit_value, total_value,
                package_type, special_handling
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($items as $item) {
            $totalValue = $item['quantity'] * $item['unit_value'];

            $stmt->execute([
                $shipmentId,
                $item['item_description'],
                $item['harmonized_code'] ?? null,
                $item['quantity'],
                $item['weight_kg'] ?? null,
                $item['volume_m3'] ?? null,
                isset($item['dimensions']) ? json_encode($item['dimensions']) : null,
                $item['unit_value'],
                $totalValue,
                $item['package_type'] ?? 'box',
                isset($item['special_handling']) ? json_encode($item['special_handling']) : '{}'
            ]);
        }
    }

    /**
     * Get shipment items
     */
    public function getShipmentItems($shipmentId)
    {
        $stmt = $this->db->prepare("
            SELECT * FROM cargo_items
            WHERE shipment_id = ?
            ORDER BY item_id
        ");

        $stmt->execute([$shipmentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Track shipment event
     */
    public function trackShipmentEvent($shipmentId, $eventType, $location, $description, $eventData = [])
    {
        $this->logger->info("Tracking shipment event", [
            'shipment_id' => $shipmentId,
            'event_type' => $eventType,
            'location' => $location
        ]);

        $stmt = $this->db->prepare("
            INSERT INTO cargo_tracking_events (
                shipment_id, event_type, event_location, event_description,
                event_data, recorded_by
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $shipmentId,
            $eventType,
            $location,
            $description,
            json_encode($eventData),
            'system'
        ]);

        // Update shipment status based on event
        $this->updateShipmentStatus($shipmentId, $eventType);

        return ['status' => 'tracked', 'event_type' => $eventType];
    }

    /**
     * Update shipment status
     */
    private function updateShipmentStatus($shipmentId, $eventType)
    {
        $statusMap = [
            'received' => 'received',
            'processed' => 'processed',
            'loaded' => 'loaded',
            'departed' => 'in_transit',
            'arrived' => 'arrived',
            'cleared' => 'cleared',
            'delivered' => 'delivered'
        ];

        if (isset($statusMap[$eventType])) {
            $stmt = $this->db->prepare("
                UPDATE cargo_shipments
                SET shipment_status = ?, updated_at = CURRENT_TIMESTAMP
                WHERE shipment_id = ?
            ");

            $stmt->execute([$statusMap[$eventType], $shipmentId]);
        }
    }

    /**
     * Get cargo terminals
     */
    public function getCargoTerminals($airportCode = null)
    {
        $whereClause = $airportCode ? "WHERE airport_code = ?" : "";
        $params = $airportCode ? [$airportCode] : [];

        $stmt = $this->db->prepare("
            SELECT
                ct.*,
                (
                    SELECT COUNT(*)
                    FROM warehouse_zones
                    WHERE terminal_id = ct.terminal_id
                ) as total_zones,
                calculate_terminal_utilization(ct.terminal_id) as utilization_rate
            FROM cargo_terminals ct
            $whereClause
            ORDER BY ct.airport_code, ct.terminal_name
        ");

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get warehouse zones
     */
    public function getWarehouseZones($terminalId = null)
    {
        $whereClause = $terminalId ? "WHERE terminal_id = ?" : "";
        $params = $terminalId ? [$terminalId] : [];

        $stmt = $this->db->prepare("
            SELECT
                wz.*,
                ct.terminal_name,
                ct.airport_code,
                ROUND((wz.current_occupancy_m3 / wz.capacity_m3) * 100, 2) as occupancy_rate
            FROM warehouse_zones wz
            JOIN cargo_terminals ct ON wz.terminal_id = ct.terminal_id
            $whereClause
            ORDER BY wz.zone_type, wz.zone_name
        ");

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get freight forwarders
     */
    public function getFreightForwarders($status = 'active')
    {
        $stmt = $this->db->prepare("
            SELECT
                ff.*,
                (
                    SELECT COUNT(*)
                    FROM cargo_shipments
                    WHERE freight_forwarder = ff.company_name
                    AND booking_date >= CURRENT_DATE - INTERVAL '30 days'
                ) as recent_shipments
            FROM freight_forwarders ff
            WHERE contract_status = ?
            ORDER BY ff.performance_rating DESC, ff.company_name
        ");

        $stmt->execute([$status]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Create customs declaration
     */
    public function createCustomsDeclaration($declarationData)
    {
        $this->logger->info("Creating customs declaration", $declarationData);

        $stmt = $this->db->prepare("
            INSERT INTO customs_declarations (
                shipment_id, declaration_number, declarant_name, declarant_contact,
                customs_broker, broker_contact, declaration_type, customs_value,
                currency, preferential_treatment, special_programs
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $declarationNumber = $this->generateDeclarationNumber();

        $stmt->execute([
            $declarationData['shipment_id'],
            $declarationNumber,
            $declarationData['declarant_name'],
            isset($declarationData['declarant_contact']) ? json_encode($declarationData['declarant_contact']) : null,
            $declarationData['customs_broker'] ?? null,
            isset($declarationData['broker_contact']) ? json_encode($declarationData['broker_contact']) : null,
            $declarationData['declaration_type'] ?? 'import',
            $declarationData['customs_value'] ?? 0,
            $declarationData['currency'] ?? 'USD',
            $declarationData['preferential_treatment'] ?? null,
            isset($declarationData['special_programs']) ? json_encode($declarationData['special_programs']) : '[]'
        ]);

        // Calculate duties
        $duties = $this->calculateCustomsDuties($declarationData['shipment_id'], $declarationData['destination_country'] ?? 'US');

        // Update declaration with calculated duties
        $stmt = $this->db->prepare("
            UPDATE customs_declarations
            SET customs_duties = ?, total_charges = ?
            WHERE declaration_number = ?
        ");

        $stmt->execute([$duties, $duties, $declarationNumber]);

        return [
            'declaration_id' => $this->db->lastInsertId(),
            'declaration_number' => $declarationNumber,
            'customs_duties' => $duties,
            'status' => 'created'
        ];
    }

    /**
     * Get customs declarations
     */
    public function getCustomsDeclarations($status = null)
    {
        $whereClause = $status ? "WHERE declaration_status = ?" : "";
        $params = $status ? [$status] : [];

        $stmt = $this->db->prepare("
            SELECT
                cd.*,
                cs.shipment_number,
                cs.shipper_name,
                cs.consignee_name
            FROM customs_declarations cd
            JOIN cargo_shipments cs ON cd.shipment_id = cs.shipment_id
            $whereClause
            ORDER BY cd.submitted_at DESC
        ");

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Record security screening
     */
    public function recordSecurityScreening($screeningData)
    {
        $this->logger->info("Recording security screening", $screeningData);

        $stmt = $this->db->prepare("
            INSERT INTO cargo_security_screening (
                shipment_id, screening_type, screening_station, screener_id,
                screening_result, threat_level, anomalies_detected,
                screening_duration_minutes, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $screeningData['shipment_id'],
            $screeningData['screening_type'] ?? 'xray',
            $screeningData['screening_station'] ?? null,
            $screeningData['screener_id'] ?? null,
            $screeningData['screening_result'] ?? 'cleared',
            $screeningData['threat_level'] ?? 'none',
            isset($screeningData['anomalies_detected']) ? json_encode($screeningData['anomalies_detected']) : '[]',
            $screeningData['screening_duration_minutes'] ?? null,
            $screeningData['notes'] ?? null
        ]);

        // Update shipment security status
        $stmt = $this->db->prepare("
            UPDATE cargo_shipments
            SET updated_at = CURRENT_TIMESTAMP
            WHERE shipment_id = ?
        ");

        $stmt->execute([$screeningData['shipment_id']]);

        return [
            'screening_id' => $this->db->lastInsertId(),
            'result' => $screeningData['screening_result'],
            'status' => 'recorded'
        ];
    }

    /**
     * Get hazardous materials
     */
    public function getHazardousMaterials($hazardClass = null)
    {
        $whereClause = $hazardClass ? "WHERE hazard_class = ?" : "";
        $params = $hazardClass ? [$hazardClass] : [];

        $stmt = $this->db->prepare("
            SELECT * FROM hazardous_materials
            $whereClause
            ORDER BY hazard_class, un_number
        ");

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Check hazardous materials compatibility
     */
    public function checkHazardousCompatibility($unNumbers)
    {
        // This would implement complex compatibility checking
        // For now, return basic assessment
        return [
            'compatible' => true,
            'warnings' => [],
            'restrictions' => [],
            'special_handling_required' => false
        ];
    }

    /**
     * Record temperature monitoring
     */
    public function recordTemperatureMonitoring($monitoringData)
    {
        $stmt = $this->db->prepare("
            INSERT INTO temperature_monitoring (
                shipment_id, zone_id, sensor_id, temperature_celsius,
                humidity_percentage, acceptable_range_min, acceptable_range_max
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $alertTriggered = false;
        if (isset($monitoringData['temperature_celsius']) &&
            isset($monitoringData['acceptable_range_min']) &&
            isset($monitoringData['acceptable_range_max'])) {
            $temp = $monitoringData['temperature_celsius'];
            $min = $monitoringData['acceptable_range_min'];
            $max = $monitoringData['acceptable_range_max'];
            $alertTriggered = ($temp < $min || $temp > $max);
        }

        $stmt->execute([
            $monitoringData['shipment_id'],
            $monitoringData['zone_id'] ?? null,
            $monitoringData['sensor_id'],
            $monitoringData['temperature_celsius'],
            $monitoringData['humidity_percentage'] ?? null,
            $monitoringData['acceptable_range_min'] ?? null,
            $monitoringData['acceptable_range_max'] ?? null
        ]);

        if ($alertTriggered) {
            // Update alert status
            $stmt = $this->db->prepare("
                UPDATE temperature_monitoring
                SET alert_triggered = true,
                    alert_type = 'temperature_out_of_range'
                WHERE monitoring_id = ?
            ");

            $stmt->execute([$this->db->lastInsertId()]);
        }

        return ['status' => 'recorded', 'alert_triggered' => $alertTriggered];
    }

    /**
     * Get cargo equipment
     */
    public function getCargoEquipment($terminalId = null, $status = null)
    {
        $whereClause = "";
        $params = [];

        if ($terminalId) {
            $whereClause .= " AND terminal_id = ?";
            $params[] = $terminalId;
        }

        if ($status) {
            $whereClause .= " AND status = ?";
            $params[] = $status;
        }

        $stmt = $this->db->prepare("
            SELECT
                ce.*,
                ct.terminal_name,
                ct.airport_code
            FROM cargo_equipment ce
            LEFT JOIN cargo_terminals ct ON ce.terminal_id = ct.terminal_id
            WHERE 1=1 $whereClause
            ORDER BY ce.equipment_type, ce.status
        ");

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Update equipment status
     */
    public function updateEquipmentStatus($equipmentId, $status, $additionalData = [])
    {
        $stmt = $this->db->prepare("
            UPDATE cargo_equipment
            SET status = ?, updated_at = CURRENT_TIMESTAMP
            WHERE equipment_id = ?
        ");

        $stmt->execute([$status, $equipmentId]);

        // Record maintenance if applicable
        if ($status === 'maintenance' && isset($additionalData['maintenance_type'])) {
            $this->recordEquipmentMaintenance($equipmentId, $additionalData);
        }

        return ['status' => 'updated', 'equipment_id' => $equipmentId];
    }

    /**
     * Record equipment maintenance
     */
    private function recordEquipmentMaintenance($equipmentId, $maintenanceData)
    {
        $stmt = $this->db->prepare("
            INSERT INTO equipment_maintenance_logs (
                equipment_id, maintenance_type, performed_by, description,
                parts_used, labor_hours, cost
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $equipmentId,
            $maintenanceData['maintenance_type'],
            $maintenanceData['performed_by'] ?? 'system',
            $maintenanceData['description'] ?? null,
            isset($maintenanceData['parts_used']) ? json_encode($maintenanceData['parts_used']) : '[]',
            $maintenanceData['labor_hours'] ?? null,
            $maintenanceData['cost'] ?? null
        ]);
    }

    /**
     * Get cargo performance metrics
     */
    public function getPerformanceMetrics($startDate, $endDate, $terminalId = null)
    {
        $whereClause = "";
        $params = [$startDate, $endDate];

        if ($terminalId) {
            $whereClause = " AND terminal_id = ?";
            $params[] = $terminalId;
        }

        $stmt = $this->db->prepare("
            SELECT * FROM cargo_performance_metrics
            WHERE date BETWEEN ? AND ?
            $whereClause
            ORDER BY date DESC
        ");

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get cargo dashboard data
     */
    public function getDashboardData()
    {
        $stmt = $this->db->prepare("
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
            ) as dashboard_data
        ");

        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return json_decode($result['dashboard_data'], true);
    }

    // Helper methods

    private function generateShipmentId()
    {
        return 'CGO-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
    }

    private function generateDeclarationNumber()
    {
        return 'DEC-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 4));
    }

    private function calculateCustomsDuties($shipmentId, $destinationCountry)
    {
        // Simplified customs calculation
        $stmt = $this->db->prepare("
            SELECT SUM(total_value) as total_value
            FROM cargo_items
            WHERE shipment_id = ?
        ");

        $stmt->execute([$shipmentId]);
        $totalValue = $stmt->fetch(PDO::FETCH_ASSOC)['total_value'] ?? 0;

        // Apply duty rate (simplified)
        return round($totalValue * 0.05, 2); // 5% duty
    }

    /**
     * Get database connection (for use by API functions)
     */
    public function getDatabaseConnection()
    {
        return $this->db;
    }
}
