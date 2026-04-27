<?php
/**
 * NOTAM & Airspace Data Integration
 *
 * Handles NOTAM parsing and processing, temporary flight restrictions,
 * dynamic airspace management, UAV/drone airspace coordination.
 */

class NotamAirspaceIntegration
{
    private $db;
    private $logger;

    public function __construct($database, $logger)
    {
        $this->db = $database;
        $this->logger = $logger;
    }

    /**
     * Process NOTAM data
     */
    public function processNOTAM($notamData)
    {
        try {
            $this->db->beginTransaction();

            // Parse NOTAM text and extract structured data
            $parsedData = $this->parseNOTAMText($notamData['notam_text']);

            $stmt = $this->db->prepare("
                INSERT INTO notams (
                    notam_id, notam_type, series, number, year, fir_code,
                    notam_code, traffic_type, purpose, scope, lower_limit,
                    upper_limit, latitude, longitude, radius, affected_area,
                    item_a, item_b, item_c, item_d, item_e, item_f, item_g,
                    start_time, end_time, estimated_end_time, permanent,
                    created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                ON CONFLICT (notam_id) DO UPDATE SET
                    notam_code = EXCLUDED.notam_code,
                    traffic_type = EXCLUDED.traffic_type,
                    purpose = EXCLUDED.purpose,
                    scope = EXCLUDED.scope,
                    lower_limit = EXCLUDED.lower_limit,
                    upper_limit = EXCLUDED.upper_limit,
                    latitude = EXCLUDED.latitude,
                    longitude = EXCLUDED.longitude,
                    radius = EXCLUDED.radius,
                    affected_area = EXCLUDED.affected_area,
                    item_a = EXCLUDED.item_a,
                    item_b = EXCLUDED.item_b,
                    item_c = EXCLUDED.item_c,
                    item_d = EXCLUDED.item_d,
                    item_e = EXCLUDED.item_e,
                    item_f = EXCLUDED.item_f,
                    item_g = EXCLUDED.item_g,
                    start_time = EXCLUDED.start_time,
                    end_time = EXCLUDED.end_time,
                    estimated_end_time = EXCLUDED.estimated_end_time,
                    permanent = EXCLUDED.permanent,
                    updated_at = CURRENT_TIMESTAMP
            ");

            $stmt->execute([
                $notamData['notam_id'],
                $notamData['notam_type'] ?? 'NOTAM',
                $parsedData['series'] ?? null,
                $parsedData['number'] ?? null,
                $parsedData['year'] ?? null,
                $parsedData['fir_code'] ?? null,
                $parsedData['notam_code'] ?? null,
                $parsedData['traffic_type'] ?? null,
                $parsedData['purpose'] ?? null,
                $parsedData['scope'] ?? null,
                $parsedData['lower_limit'] ?? null,
                $parsedData['upper_limit'] ?? null,
                $parsedData['latitude'] ?? null,
                $parsedData['longitude'] ?? null,
                $parsedData['radius'] ?? null,
                $parsedData['affected_area'] ?? null,
                $parsedData['item_a'] ?? null,
                $parsedData['item_b'] ?? null,
                $parsedData['item_c'] ?? null,
                $parsedData['item_d'] ?? null,
                $parsedData['item_e'] ?? null,
                $parsedData['item_f'] ?? null,
                $parsedData['item_g'] ?? null,
                $parsedData['start_time'] ?? null,
                $parsedData['end_time'] ?? null,
                $parsedData['estimated_end_time'] ?? null,
                $parsedData['permanent'] ?? false
            ]);

            $this->db->commit();
            $this->logger->info("NOTAM processed successfully", ['notam_id' => $notamData['notam_id']]);

            return ['success' => true, 'notam_id' => $notamData['notam_id']];

        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logger->error("Failed to process NOTAM", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Process airspace restriction data
     */
    public function processAirspaceRestriction($restrictionData)
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO airspace_restrictions (
                    restriction_id, restriction_type, name, lower_altitude,
                    upper_altitude, geometry_type, geometry_coordinates,
                    effective_from, effective_to, reason, controlling_agency
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON CONFLICT (restriction_id) DO UPDATE SET
                    name = EXCLUDED.name,
                    lower_altitude = EXCLUDED.lower_altitude,
                    upper_altitude = EXCLUDED.upper_altitude,
                    geometry_type = EXCLUDED.geometry_type,
                    geometry_coordinates = EXCLUDED.geometry_coordinates,
                    effective_from = EXCLUDED.effective_from,
                    effective_to = EXCLUDED.effective_to,
                    reason = EXCLUDED.reason,
                    controlling_agency = EXCLUDED.controlling_agency
            ");

            $stmt->execute([
                $restrictionData['restriction_id'],
                $restrictionData['restriction_type'],
                $restrictionData['name'] ?? null,
                $restrictionData['lower_altitude'] ?? null,
                $restrictionData['upper_altitude'] ?? null,
                $restrictionData['geometry_type'] ?? 'circle',
                $restrictionData['geometry_coordinates'] ?? null,
                $restrictionData['effective_from'] ?? null,
                $restrictionData['effective_to'] ?? null,
                $restrictionData['reason'] ?? null,
                $restrictionData['controlling_agency'] ?? null
            ]);

            $this->logger->info("Airspace restriction processed", ['restriction_id' => $restrictionData['restriction_id']]);
            return ['success' => true];

        } catch (Exception $e) {
            $this->logger->error("Failed to process airspace restriction", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Process drone operation data
     */
    public function processDroneOperation($droneData)
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO drone_operations (
                    operation_id, operator_name, operator_contact, drone_type,
                    max_altitude, operation_area, start_time, end_time,
                    emergency_contact, approved, approved_by, approved_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $droneData['operation_id'],
                $droneData['operator_name'] ?? null,
                $droneData['operator_contact'] ?? null,
                $droneData['drone_type'] ?? null,
                $droneData['max_altitude'] ?? null,
                $droneData['operation_area'] ?? null,
                $droneData['start_time'],
                $droneData['end_time'],
                $droneData['emergency_contact'] ?? null,
                $droneData['approved'] ?? false,
                $droneData['approved_by'] ?? null,
                $droneData['approved_at'] ?? null
            ]);

            $this->logger->info("Drone operation processed", ['operation_id' => $droneData['operation_id']]);
            return ['success' => true];

        } catch (Exception $e) {
            $this->logger->error("Failed to process drone operation", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Parse NOTAM text into structured data
     */
    private function parseNOTAMText($notamText)
    {
        $parsed = [];

        // Basic NOTAM parsing - this would be much more sophisticated in production
        // Extract series and number (e.g., A1234/25)
        if (preg_match('/([A-Z])(\d{4})\/(\d{2})/', $notamText, $matches)) {
            $parsed['series'] = $matches[1];
            $parsed['number'] = (int)$matches[2];
            $parsed['year'] = (int)$matches[3];
        }

        // Extract FIR code
        if (preg_match('/FIR\s*:\s*([A-Z]{4})/i', $notamText, $matches)) {
            $parsed['fir_code'] = $matches[1];
        }

        // Extract NOTAM code (e.g., Q)XXXX/XXXX/XXX)
        if (preg_match('/Q\)\s*([A-Z]{5})\/([A-Z]{5})\/([A-Z]{3})/', $notamText, $matches)) {
            $parsed['notam_code'] = $matches[1] . '/' . $matches[2] . '/' . $matches[3];
        }

        // Extract coordinates
        if (preg_match('/(\d{6}[NS]\d{7}[EW])/', $notamText, $matches)) {
            $parsed['latitude'] = $this->parseCoordinate($matches[1], 'lat');
            $parsed['longitude'] = $this->parseCoordinate($matches[1], 'lon');
        }

        // Extract radius
        if (preg_match('/(\d+)\s*KM/i', $notamText, $matches)) {
            $parsed['radius'] = (int)$matches[1];
        }

        // Extract altitudes
        if (preg_match('/(\d+)\s*FT/i', $notamText, $matches)) {
            $parsed['lower_limit'] = (int)$matches[1];
        }

        return $parsed;
    }

    /**
     * Parse coordinate from NOTAM format
     */
    private function parseCoordinate($coord, $type)
    {
        // Convert NOTAM coordinate format to decimal degrees
        // This is a simplified implementation
        if ($type === 'lat') {
            $lat = substr($coord, 0, 6);
            $ns = substr($coord, 6, 1);
            $degrees = (int)substr($lat, 0, 2);
            $minutes = (int)substr($lat, 2, 2);
            $seconds = (int)substr($lat, 4, 2) * 0.01;
            $decimal = $degrees + ($minutes / 60) + ($seconds / 3600);
            return $ns === 'S' ? -$decimal : $decimal;
        } else {
            $lon = substr($coord, 7, 7);
            $ew = substr($coord, 14, 1);
            $degrees = (int)substr($lon, 0, 3);
            $minutes = (int)substr($lon, 3, 2);
            $seconds = (int)substr($lon, 5, 2) * 0.01;
            $decimal = $degrees + ($minutes / 60) + ($seconds / 3600);
            return $ew === 'W' ? -$decimal : $decimal;
        }
    }

    /**
     * Get active NOTAMs for area
     */
    public function getActiveNOTAMs($latitude, $longitude, $radiusKm = 100)
    {
        $stmt = $this->db->prepare("
            SELECT *,
                   (6371 * acos(cos(radians(?)) * cos(radians(latitude)) *
                   cos(radians(longitude) - radians(?)) + sin(radians(?)) *
                   sin(radians(latitude)))) AS distance
            FROM notams
            WHERE (start_time IS NULL OR start_time <= NOW())
            AND (end_time IS NULL OR end_time >= NOW())
            AND permanent = false
            HAVING distance < ?
            ORDER BY distance ASC, created_at DESC
        ");

        $stmt->execute([$latitude, $longitude, $latitude, $radiusKm]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get airspace restrictions for area
     */
    public function getAirspaceRestrictions($latitude, $longitude, $altitude = null)
    {
        $query = "
            SELECT *,
                   (6371 * acos(cos(radians(?)) * cos(radians(latitude)) *
                   cos(radians(longitude) - radians(?)) + sin(radians(?)) *
                   sin(radians(latitude)))) AS distance
            FROM airspace_restrictions
            WHERE active = true
            AND (effective_from IS NULL OR effective_from <= NOW())
            AND (effective_to IS NULL OR effective_to >= NOW())
        ";

        $params = [$latitude, $longitude, $latitude];

        // Add altitude filtering if provided
        if ($altitude !== null) {
            $query .= " AND (lower_altitude IS NULL OR lower_altitude <= ?) AND (upper_altitude IS NULL OR upper_altitude >= ?)";
            $params[] = $altitude;
            $params[] = $altitude;
        }

        $query .= " HAVING distance < 200 ORDER BY distance ASC";

        $stmt = $this->db->prepare($query);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get drone operations in area
     */
    public function getDroneOperations($latitude, $longitude, $radiusKm = 50)
    {
        $stmt = $this->db->prepare("
            SELECT *,
                   ST_Distance(ST_GeomFromText(operation_area), ST_Point(?, ?)) as distance
            FROM drone_operations
            WHERE approved = true
            AND start_time <= NOW()
            AND end_time >= NOW()
            HAVING distance < ?
            ORDER BY distance ASC
        ");

        $stmt->execute([$longitude, $latitude, $radiusKm * 1000]); // Convert km to meters
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Check flight path for restrictions
     */
    public function checkFlightPathRestrictions($flightPath)
    {
        $restrictions = [];

        foreach ($flightPath as $point) {
            $latitude = $point['latitude'];
            $longitude = $point['longitude'];
            $altitude = $point['altitude'] ?? null;

            // Check NOTAMs
            $notams = $this->getActiveNOTAMs($latitude, $longitude, 10);
            if (!empty($notams)) {
                $restrictions['notams'] = array_merge($restrictions['notams'] ?? [], $notams);
            }

            // Check airspace restrictions
            $airspace = $this->getAirspaceRestrictions($latitude, $longitude, $altitude);
            if (!empty($airspace)) {
                $restrictions['airspace'] = array_merge($restrictions['airspace'] ?? [], $airspace);
            }

            // Check drone operations
            $drones = $this->getDroneOperations($latitude, $longitude, 10);
            if (!empty($drones)) {
                $restrictions['drones'] = array_merge($restrictions['drones'] ?? [], $drones);
            }
        }

        return $restrictions;
    }

    /**
     * Validate flight plan against restrictions
     */
    public function validateFlightPlan($flightPlanData)
    {
        $issues = [];

        // Check departure airport restrictions
        $depRestrictions = $this->getActiveNOTAMs(
            $flightPlanData['departure_lat'],
            $flightPlanData['departure_lon'],
            25
        );

        if (!empty($depRestrictions)) {
            $issues[] = [
                'type' => 'departure_restriction',
                'severity' => 'high',
                'description' => 'NOTAM restrictions at departure airport',
                'restrictions' => $depRestrictions
            ];
        }

        // Check arrival airport restrictions
        $arrRestrictions = $this->getActiveNOTAMs(
            $flightPlanData['arrival_lat'],
            $flightPlanData['arrival_lon'],
            25
        );

        if (!empty($arrRestrictions)) {
            $issues[] = [
                'type' => 'arrival_restriction',
                'severity' => 'high',
                'description' => 'NOTAM restrictions at arrival airport',
                'restrictions' => $arrRestrictions
            ];
        }

        // Check route restrictions (simplified - would need actual route points)
        if (isset($flightPlanData['route_points'])) {
            $routeIssues = $this->checkFlightPathRestrictions($flightPlanData['route_points']);
            if (!empty($routeIssues)) {
                $issues[] = [
                    'type' => 'route_restriction',
                    'severity' => 'medium',
                    'description' => 'Restrictions along planned route',
                    'restrictions' => $routeIssues
                ];
            }
        }

        return [
            'valid' => empty($issues),
            'issues' => $issues
        ];
    }

    /**
     * Approve drone operation
     */
    public function approveDroneOperation($operationId, $approvedBy)
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE drone_operations
                SET approved = true, approved_by = ?, approved_at = CURRENT_TIMESTAMP
                WHERE operation_id = ?
            ");

            $stmt->execute([$approvedBy, $operationId]);

            $this->logger->info("Drone operation approved", ['operation_id' => $operationId]);
            return ['success' => true];

        } catch (Exception $e) {
            $this->logger->error("Failed to approve drone operation", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get airspace availability
     */
    public function getAirspaceAvailability($latitude, $longitude, $altitude, $timeWindow = 3600)
    {
        $startTime = date('Y-m-d H:i:s');
        $endTime = date('Y-m-d H:i:s', strtotime("+{$timeWindow} seconds"));

        // Check for active restrictions
        $restrictions = $this->getAirspaceRestrictions($latitude, $longitude, $altitude);

        // Check for planned drone operations
        $droneOps = $this->getDroneOperations($latitude, $longitude, 50);

        // Check for NOTAMs
        $notams = $this->getActiveNOTAMs($latitude, $longitude, 50);

        return [
            'available' => empty($restrictions) && empty($droneOps) && empty($notams),
            'restrictions' => $restrictions,
            'drone_operations' => $droneOps,
            'notams' => $notams,
            'time_window' => [$startTime, $endTime]
        ];
    }
}
