<?php

/**
 * Baggage Tracking Model
 *
 * Manages smart baggage tags, RFID tracking, automated routing, and real-time monitoring
 */

class BaggageTracking
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
        $this->logger = new Logger('baggage_tracking');
    }

    /**
     * Register a new smart baggage tag
     */
    public function registerBaggageTag($tagData)
    {
        $this->logger->info("Registering smart baggage tag", $tagData);

        $stmt = $this->db->prepare("
            INSERT INTO smart_baggage_tags (
                tag_id, tag_type, passenger_id, booking_id, flight_id,
                baggage_type, weight_kg, dimensions, contents_description,
                special_handling, security_status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $tagData['tag_id'],
            $tagData['tag_type'] ?? 'rfid',
            $tagData['passenger_id'],
            $tagData['booking_id'] ?? null,
            $tagData['flight_id'] ?? null,
            $tagData['baggage_type'] ?? 'checked',
            $tagData['weight_kg'] ?? null,
            isset($tagData['dimensions']) ? json_encode($tagData['dimensions']) : null,
            $tagData['contents_description'] ?? null,
            isset($tagData['special_handling']) ? json_encode($tagData['special_handling']) : '{}',
            $tagData['security_status'] ?? 'pending'
        ]);

        // Create initial tracking event
        $this->trackBaggageMovement([
            'tag_id' => $tagData['tag_id'],
            'event_type' => 'registered',
            'location' => $tagData['location'] ?? 'Check-in Counter',
            'zone' => 'check_in_counter',
            'recorded_by' => 'system'
        ]);

        return [
            'tag_id' => $tagData['tag_id'],
            'status' => 'registered',
            'message' => 'Baggage tag registered successfully'
        ];
    }

    /**
     * Track baggage movement event
     */
    public function trackBaggageMovement($eventData)
    {
        $this->logger->info("Tracking baggage movement", $eventData);

        $stmt = $this->db->prepare("
            INSERT INTO baggage_tracking_events (
                tag_id, event_type, location, zone, sensor_id,
                sensor_type, event_data, recorded_by, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $eventData['tag_id'],
            $eventData['event_type'],
            $eventData['location'] ?? null,
            $eventData['zone'] ?? null,
            $eventData['sensor_id'] ?? null,
            $eventData['sensor_type'] ?? null,
            isset($eventData['event_data']) ? json_encode($eventData['event_data']) : '{}',
            $eventData['recorded_by'] ?? 'system',
            $eventData['notes'] ?? null
        ]);

        $eventId = $this->db->lastInsertId();

        // Update baggage status based on event type
        $this->updateBaggageStatus($eventData['tag_id'], $eventData['event_type']);

        // Check for routing decisions
        $this->checkRoutingRules($eventData['tag_id'], $eventData);

        return [
            'event_id' => $eventId,
            'tag_id' => $eventData['tag_id'],
            'event_type' => $eventData['event_type'],
            'status' => 'tracked'
        ];
    }

    /**
     * Get baggage location and status
     */
    public function getBaggageStatus($tagId)
    {
        $stmt = $this->db->prepare("
            SELECT
                sbt.*,
                f.flight_number,
                f.origin,
                f.destination,
                f.departure_time,
                f.arrival_time,
                p.first_name,
                p.last_name,
                (
                    SELECT json_build_object(
                        'event_type', event_type,
                        'location', location,
                        'zone', zone,
                        'timestamp', timestamp,
                        'sensor_id', sensor_id
                    )
                    FROM baggage_tracking_events
                    WHERE tag_id = sbt.tag_id
                    ORDER BY timestamp DESC
                    LIMIT 1
                ) as last_event,
                (
                    SELECT json_agg(
                        json_build_object(
                            'event_type', event_type,
                            'location', location,
                            'timestamp', timestamp
                        ) ORDER BY timestamp DESC
                    )
                    FROM baggage_tracking_events
                    WHERE tag_id = sbt.tag_id
                    LIMIT 10
                ) as recent_events
            FROM smart_baggage_tags sbt
            LEFT JOIN flights f ON sbt.flight_id = f.flight_id
            LEFT JOIN passengers p ON sbt.passenger_id = p.passenger_id
            WHERE sbt.tag_id = ?
        ");

        $stmt->execute([$tagId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            throw new Exception("Baggage tag not found");
        }

        return $result;
    }

    /**
     * Get passenger's baggage list
     */
    public function getPassengerBaggage($passengerId, $bookingId = null)
    {
        $whereClause = "WHERE sbt.passenger_id = ?";
        $params = [$passengerId];

        if ($bookingId) {
            $whereClause .= " AND sbt.booking_id = ?";
            $params[] = $bookingId;
        }

        $stmt = $this->db->prepare("
            SELECT
                sbt.*,
                f.flight_number,
                f.origin,
                f.destination,
                f.departure_time,
                f.arrival_time,
                (
                    SELECT json_build_object(
                        'event_type', event_type,
                        'location', location,
                        'zone', zone,
                        'timestamp', timestamp
                    )
                    FROM baggage_tracking_events
                    WHERE tag_id = sbt.tag_id
                    ORDER BY timestamp DESC
                    LIMIT 1
                ) as last_event
            FROM smart_baggage_tags sbt
            LEFT JOIN flights f ON sbt.flight_id = f.flight_id
            $whereClause
            ORDER BY sbt.created_at DESC
        ");

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Record RFID sensor reading
     */
    public function recordSensorReading($readingData)
    {
        $this->logger->info("Recording sensor reading", $readingData);

        $stmt = $this->db->prepare("
            INSERT INTO sensor_readings (
                sensor_id, tag_id, reading_type, signal_strength,
                distance_meters, direction, confidence_level, raw_data
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $readingData['sensor_id'],
            $readingData['tag_id'],
            $readingData['reading_type'] ?? 'detection',
            $readingData['signal_strength'] ?? null,
            $readingData['distance_meters'] ?? null,
            $readingData['direction'] ?? null,
            $readingData['confidence_level'] ?? null,
            isset($readingData['raw_data']) ? json_encode($readingData['raw_data']) : '{}'
        ]);

        // Update sensor last reading time
        $stmt = $this->db->prepare("
            UPDATE rfid_sensors
            SET last_reading = CURRENT_TIMESTAMP
            WHERE sensor_id = ?
        ");

        $stmt->execute([$readingData['sensor_id']]);

        // Auto-track movement if confidence is high enough
        if (($readingData['confidence_level'] ?? 0) >= 80) {
            $this->autoTrackMovement($readingData);
        }

        return ['status' => 'recorded'];
    }

    /**
     * Get baggage routing path
     */
    public function getBaggageRoute($tagId)
    {
        $stmt = $this->db->prepare("
            SELECT
                br.rule_name,
                br.priority,
                br.conditions,
                br.actions
            FROM baggage_routing_rules br
            JOIN smart_baggage_tags sbt ON true
            WHERE br.is_active = true
            AND sbt.tag_id = ?
            AND (
                (br.conditions->>'baggage_type' IS NULL OR br.conditions->>'baggage_type' = sbt.baggage_type)
                OR (br.conditions->>'special_handling' IS NULL OR sbt.special_handling ?| ARRAY(SELECT jsonb_array_elements_text(br.conditions->'special_handling')))
                OR (br.conditions = '{}')
            )
            ORDER BY br.priority ASC
            LIMIT 1
        ");

        $stmt->execute([$tagId]);
        $rule = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$rule) {
            // Return default routing
            return [
                'rule_name' => 'Standard Baggage Routing',
                'routing_path' => ['standard_conveyor', 'automated_sorting'],
                'priority_level' => 'normal',
                'handling_instructions' => []
            ];
        }

        return [
            'rule_name' => $rule['rule_name'],
            'routing_path' => json_decode($rule['actions'], true)['routing_path'] ?? [],
            'priority_level' => json_decode($rule['actions'], true)['priority_level'] ?? 'normal',
            'handling_instructions' => json_decode($rule['actions'], true)['handling_instructions'] ?? []
        ];
    }

    /**
     * Report lost or damaged baggage
     */
    public function reportBaggageIssue($reportData)
    {
        $this->logger->info("Reporting baggage issue", $reportData);

        $stmt = $this->db->prepare("
            INSERT INTO lost_baggage_reports (
                tag_id, passenger_id, flight_id, report_type,
                description, last_seen_location, last_seen_timestamp,
                reported_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $reportData['tag_id'],
            $reportData['passenger_id'],
            $reportData['flight_id'] ?? null,
            $reportData['report_type'],
            $reportData['description'],
            $reportData['last_seen_location'] ?? null,
            $reportData['last_seen_timestamp'] ?? null,
            $reportData['reported_by'] ?? 'passenger'
        ]);

        $reportId = $this->db->lastInsertId();

        // Update baggage status
        $stmt = $this->db->prepare("
            UPDATE smart_baggage_tags
            SET status = 'lost', updated_at = CURRENT_TIMESTAMP
            WHERE tag_id = ?
        ");

        $stmt->execute([$reportData['tag_id']]);

        return [
            'report_id' => $reportId,
            'tag_id' => $reportData['tag_id'],
            'status' => 'reported',
            'message' => 'Baggage issue reported successfully'
        ];
    }

    /**
     * Get baggage performance metrics
     */
    public function getPerformanceMetrics($startDate, $endDate, $terminal = null)
    {
        $whereClause = "";
        $params = [$startDate, $endDate];

        if ($terminal) {
            $whereClause = " AND bpm.terminal = ?";
            $params[] = $terminal;
        }

        $stmt = $this->db->prepare("
            SELECT
                bpm.*,
                (
                    SELECT COUNT(*)
                    FROM baggage_tracking_events bte
                    WHERE DATE(bte.timestamp) = bpm.date
                    AND bte.event_type = 'delivered'
                    AND ($terminal IS NULL OR bte.location LIKE '%' || $terminal || '%')
                ) as actual_deliveries
            FROM baggage_performance_metrics bpm
            WHERE bpm.date BETWEEN ? AND ?
            $whereClause
            ORDER BY bpm.date DESC
        ");

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get conveyor system status
     */
    public function getConveyorStatus($terminal = null)
    {
        $whereClause = $terminal ? "WHERE terminal = ?" : "";
        $params = $terminal ? [$terminal] : [];

        $stmt = $this->db->prepare("
            SELECT
                cs.*,
                (
                    SELECT COUNT(*)
                    FROM baggage_tracking_events bte
                    WHERE bte.location = cs.location
                    AND bte.timestamp >= CURRENT_TIMESTAMP - INTERVAL '1 hour'
                ) as recent_activity
            FROM conveyor_systems cs
            $whereClause
            ORDER BY cs.terminal, cs.conveyor_name
        ");

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get RFID sensor status
     */
    public function getSensorStatus($terminal = null)
    {
        $whereClause = $terminal ? "WHERE terminal = ?" : "";
        $params = $terminal ? [$terminal] : [];

        $stmt = $this->db->prepare("
            SELECT
                rs.*,
                (
                    SELECT COUNT(*)
                    FROM sensor_readings sr
                    WHERE sr.sensor_id = rs.sensor_id
                    AND sr.timestamp >= CURRENT_TIMESTAMP - INTERVAL '1 hour'
                ) as recent_readings,
                (
                    SELECT AVG(confidence_level)
                    FROM sensor_readings sr
                    WHERE sr.sensor_id = rs.sensor_id
                    AND sr.timestamp >= CURRENT_TIMESTAMP - INTERVAL '24 hours'
                ) as avg_confidence
            FROM rfid_sensors rs
            $whereClause
            ORDER BY rs.terminal, rs.sensor_name
        ");

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Reconcile baggage for a flight
     */
    public function reconcileFlightBaggage($flightId, $reconciliationType = 'arrival')
    {
        $this->logger->info("Reconciling baggage for flight", ['flight_id' => $flightId]);

        // Count expected baggage
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as expected_count
            FROM smart_baggage_tags
            WHERE flight_id = ? AND baggage_type = 'checked'
        ");

        $stmt->execute([$flightId]);
        $expected = $stmt->fetch(PDO::FETCH_ASSOC)['expected_count'];

        // Count actual baggage delivered
        $stmt = $this->db->prepare("
            SELECT COUNT(DISTINCT tag_id) as actual_count
            FROM baggage_tracking_events
            WHERE tag_id IN (
                SELECT tag_id FROM smart_baggage_tags WHERE flight_id = ?
            ) AND event_type = 'delivered'
        ");

        $stmt->execute([$flightId]);
        $actual = $stmt->fetch(PDO::FETCH_ASSOC)['actual_count'];

        // Create reconciliation record
        $stmt = $this->db->prepare("
            INSERT INTO baggage_reconciliation (
                flight_id, reconciliation_type, expected_items, actual_items,
                missing_items, extra_items, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $missing = max(0, $expected - $actual);
        $extra = max(0, $actual - $expected);
        $status = ($expected == $actual) ? 'completed' : 'issues_found';

        $stmt->execute([
            $flightId,
            $reconciliationType,
            $expected,
            $actual,
            $missing,
            $extra,
            $status
        ]);

        $reconciliationId = $this->db->lastInsertId();

        return [
            'reconciliation_id' => $reconciliationId,
            'flight_id' => $flightId,
            'expected' => $expected,
            'actual' => $actual,
            'missing' => $missing,
            'extra' => $extra,
            'status' => $status
        ];
    }

    /**
     * Get baggage delivery notifications
     */
    public function getDeliveryNotifications($passengerId, $status = null)
    {
        $whereClause = "WHERE bdn.passenger_id = ?";
        $params = [$passengerId];

        if ($status) {
            $whereClause .= " AND bdn.status = ?";
            $params[] = $status;
        }

        $stmt = $this->db->prepare("
            SELECT
                bdn.*,
                sbt.baggage_type,
                sbt.weight_kg,
                f.flight_number,
                f.origin,
                f.destination
            FROM baggage_delivery_notifications bdn
            JOIN smart_baggage_tags sbt ON bdn.tag_id = sbt.tag_id
            LEFT JOIN flights f ON sbt.flight_id = f.flight_id
            $whereClause
            ORDER BY bdn.created_at DESC
        ");

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Update baggage security screening
     */
    public function updateSecurityScreening($screeningData)
    {
        $this->logger->info("Updating security screening", $screeningData);

        $stmt = $this->db->prepare("
            INSERT INTO baggage_security_screening (
                tag_id, screening_type, screening_station, screener_id,
                result, threat_level, anomalies_detected, screening_duration_seconds,
                image_reference, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $screeningData['tag_id'],
            $screeningData['screening_type'] ?? 'xray',
            $screeningData['screening_station'] ?? null,
            $screeningData['screener_id'] ?? null,
            $screeningData['result'] ?? 'cleared',
            $screeningData['threat_level'] ?? 'none',
            isset($screeningData['anomalies_detected']) ? json_encode($screeningData['anomalies_detected']) : '[]',
            $screeningData['screening_duration_seconds'] ?? null,
            $screeningData['image_reference'] ?? null,
            $screeningData['notes'] ?? null
        ]);

        // Update baggage security status
        $stmt = $this->db->prepare("
            UPDATE smart_baggage_tags
            SET security_status = ?, updated_at = CURRENT_TIMESTAMP
            WHERE tag_id = ?
        ");

        $stmt->execute([$screeningData['result'], $screeningData['tag_id']]);

        return [
            'screening_id' => $this->db->lastInsertId(),
            'tag_id' => $screeningData['tag_id'],
            'result' => $screeningData['result'],
            'status' => 'screened'
        ];
    }

    /**
     * Get baggage container status
     */
    public function getContainerStatus($flightId = null)
    {
        $whereClause = $flightId ? "WHERE flight_id = ?" : "";
        $params = $flightId ? [$flightId] : [];

        $stmt = $this->db->prepare("
            SELECT
                bc.*,
                (
                    SELECT COUNT(*)
                    FROM container_contents cc
                    WHERE cc.container_id = bc.container_id
                ) as item_count,
                (
                    SELECT json_agg(
                        json_build_object(
                            'tag_id', cc.tag_id,
                            'baggage_type', sbt.baggage_type,
                            'weight_kg', sbt.weight_kg
                        )
                    )
                    FROM container_contents cc
                    JOIN smart_baggage_tags sbt ON cc.tag_id = sbt.tag_id
                    WHERE cc.container_id = bc.container_id
                ) as contents
            FROM baggage_containers bc
            $whereClause
            ORDER BY bc.created_at DESC
        ");

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Load baggage into container
     */
    public function loadBaggageIntoContainer($containerId, $tagId, $position = null)
    {
        $this->logger->info("Loading baggage into container", [
            'container_id' => $containerId,
            'tag_id' => $tagId
        ]);

        // Check container capacity
        $stmt = $this->db->prepare("
            SELECT
                max_weight_kg,
                current_weight_kg,
                max_volume_m3,
                current_volume_m3,
                (
                    SELECT COUNT(*)
                    FROM container_contents
                    WHERE container_id = ?
                ) as current_items
            FROM baggage_containers
            WHERE container_id = ?
        ");

        $stmt->execute([$containerId, $containerId]);
        $container = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$container) {
            throw new Exception("Container not found");
        }

        // Get baggage details
        $stmt = $this->db->prepare("
            SELECT weight_kg, dimensions
            FROM smart_baggage_tags
            WHERE tag_id = ?
        ");

        $stmt->execute([$tagId]);
        $baggage = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$baggage) {
            throw new Exception("Baggage not found");
        }

        // Check weight capacity
        $newWeight = $container['current_weight_kg'] + ($baggage['weight_kg'] ?? 0);
        if ($newWeight > $container['max_weight_kg']) {
            throw new Exception("Container weight capacity exceeded");
        }

        // Add to container
        $stmt = $this->db->prepare("
            INSERT INTO container_contents (container_id, tag_id, position_in_container)
            VALUES (?, ?, ?)
        ");

        $stmt->execute([$containerId, $tagId, $position]);

        // Update container weight
        $stmt = $this->db->prepare("
            UPDATE baggage_containers
            SET current_weight_kg = ?, status = 'loading', updated_at = CURRENT_TIMESTAMP
            WHERE container_id = ?
        ");

        $stmt->execute([$newWeight, $containerId]);

        // Track loading event
        $this->trackBaggageMovement([
            'tag_id' => $tagId,
            'event_type' => 'loaded',
            'location' => 'Loading Area',
            'zone' => 'loading_area',
            'recorded_by' => 'system',
            'event_data' => ['container_id' => $containerId]
        ]);

        return [
            'container_id' => $containerId,
            'tag_id' => $tagId,
            'status' => 'loaded'
        ];
    }

    /**
     * Update baggage status based on event type
     */
    private function updateBaggageStatus($tagId, $eventType)
    {
        $statusMap = [
            'check_in' => 'checked_in',
            'security_scan' => 'security_cleared',
            'loaded' => 'loaded',
            'unloaded' => 'arrived',
            'transferred' => 'in_transit',
            'delivered' => 'delivered'
        ];

        if (isset($statusMap[$eventType])) {
            $stmt = $this->db->prepare("
                UPDATE smart_baggage_tags
                SET status = ?, updated_at = CURRENT_TIMESTAMP
                WHERE tag_id = ?
            ");

            $stmt->execute([$statusMap[$eventType], $tagId]);
        }
    }

    /**
     * Check routing rules and create decisions
     */
    private function checkRoutingRules($tagId, $eventData)
    {
        // This would implement intelligent routing based on baggage characteristics
        // For now, we'll just log the potential routing decision
        $this->logger->info("Checking routing rules for baggage", [
            'tag_id' => $tagId,
            'event_type' => $eventData['event_type'],
            'location' => $eventData['location']
        ]);
    }

    /**
     * Auto-track movement based on sensor readings
     */
    private function autoTrackMovement($readingData)
    {
        // Get sensor location details
        $stmt = $this->db->prepare("
            SELECT location, zone FROM rfid_sensors WHERE sensor_id = ?
        ");

        $stmt->execute([$readingData['sensor_id']]);
        $sensor = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($sensor) {
            $this->trackBaggageMovement([
                'tag_id' => $readingData['tag_id'],
                'event_type' => 'sensor_detected',
                'location' => $sensor['location'],
                'zone' => $sensor['zone'],
                'sensor_id' => $readingData['sensor_id'],
                'sensor_type' => 'rfid_reader',
                'recorded_by' => 'sensor',
                'event_data' => $readingData
            ]);
        }
    }

    /**
     * Get baggage dashboard data
     */
    public function getDashboardData()
    {
        $stmt = $this->db->prepare("
            SELECT json_build_object(
                'total_active_tags', (
                    SELECT COUNT(*) FROM smart_baggage_tags
                    WHERE status NOT IN ('delivered', 'lost')
                ),
                'tags_in_transit', (
                    SELECT COUNT(*) FROM smart_baggage_tags
                    WHERE status = 'in_transit'
                ),
                'delivered_today', (
                    SELECT COUNT(*) FROM baggage_tracking_events
                    WHERE event_type = 'delivered'
                    AND DATE(timestamp) = CURRENT_DATE
                ),
                'lost_baggage_reports', (
                    SELECT COUNT(*) FROM lost_baggage_reports
                    WHERE status = 'open'
                ),
                'system_uptime', (
                    SELECT ROUND(
                        COUNT(*)::DECIMAL /
                        NULLIF((SELECT COUNT(*) FROM rfid_sensors), 0) * 100, 2
                    )
                    FROM rfid_sensors
                    WHERE status = 'active'
                ),
                'conveyor_status', (
                    SELECT json_agg(
                        json_build_object(
                            'conveyor_name', conveyor_name,
                            'status', status,
                            'terminal', terminal
                        )
                    )
                    FROM conveyor_systems
                    WHERE status != 'operational'
                ),
                'recent_events', (
                    SELECT json_agg(
                        json_build_object(
                            'tag_id', tag_id,
                            'event_type', event_type,
                            'location', location,
                            'timestamp', timestamp
                        )
                    )
                    FROM baggage_tracking_events
                    ORDER BY timestamp DESC
                    LIMIT 10
                )
            ) as dashboard_data
        ");

        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return json_decode($result['dashboard_data'], true);
    }
}
