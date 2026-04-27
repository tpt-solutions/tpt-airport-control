<?php

/**
 * Drone Traffic Management Integration
 *
 * Integration with drone traffic management systems for UAV airspace coordination,
 * flight planning, and regulatory compliance
 */

class DroneTrafficManagementIntegration
{
    private $db;
    private $logger;
    private $dtmApiUrl;
    private $dtmApiKey;
    private $faaApiUrl;
    private $faaApiKey;

    public function __construct()
    {
        $this->db = new PDO(
            "pgsql:host=" . getenv('DB_HOST') . ";dbname=" . getenv('DB_DATABASE'),
            getenv('DB_USERNAME'),
            getenv('DB_PASSWORD'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $this->logger = new Logger('drone_traffic_management_integration');

        // DTM and FAA API configuration
        $this->dtmApiUrl = getenv('DTM_API_URL') ?: 'https://api.drone-traffic-management.com/v1';
        $this->dtmApiKey = getenv('DTM_API_KEY');
        $this->faaApiUrl = getenv('FAA_API_URL') ?: 'https://api.faa.gov/drones/v1';
        $this->faaApiKey = getenv('FAA_API_KEY');
    }

    /**
     * Submit drone flight plan for approval
     */
    public function submitFlightPlan($flightPlanData)
    {
        $this->logger->info("Submitting drone flight plan", [
            'drone_id' => $flightPlanData['drone_id'],
            'pilot_id' => $flightPlanData['pilot_id']
        ]);

        $this->db->beginTransaction();

        try {
            // Validate flight plan data
            $this->validateFlightPlanData($flightPlanData);

            // Check airspace availability
            $airspaceCheck = $this->checkAirspaceAvailability($flightPlanData);

            if (!$airspaceCheck['available']) {
                throw new Exception("Airspace not available: " . $airspaceCheck['reason']);
            }

            // Check for conflicts with existing flights
            $conflictCheck = $this->checkFlightConflicts($flightPlanData);

            if ($conflictCheck['has_conflicts']) {
                throw new Exception("Flight conflicts detected: " . implode(', ', $conflictCheck['conflicts']));
            }

            // Generate flight plan ID
            $flightPlanId = $this->generateFlightPlanId();

            // Store flight plan
            $this->storeFlightPlan($flightPlanId, $flightPlanData);

            // Submit to DTM system
            $dtmResponse = $this->submitToDTMSystem($flightPlanId, $flightPlanData);

            // Update flight plan with DTM reference
            $this->updateFlightPlanWithDTMReference($flightPlanId, $dtmResponse);

            // Log submission
            $this->logFlightPlanSubmission($flightPlanId, $dtmResponse);

            $this->db->commit();

            return [
                'flight_plan_id' => $flightPlanId,
                'status' => 'submitted',
                'dtm_reference' => $dtmResponse['reference_id'] ?? null,
                'estimated_approval_time' => $dtmResponse['estimated_approval_minutes'] ?? null,
                'message' => 'Flight plan submitted successfully'
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logger->error("Flight plan submission failed", [
                'drone_id' => $flightPlanData['drone_id'],
                'error' => $e->getMessage()
            ]);

            throw new Exception("Flight plan submission failed: " . $e->getMessage());
        }
    }

    /**
     * Get airspace reservations and restrictions
     */
    public function getAirspaceStatus($latitude, $longitude, $altitude, $radiusKm = 5)
    {
        try {
            // Get airspace restrictions from FAA
            $restrictions = $this->getFAARestrictions($latitude, $longitude, $altitude, $radiusKm);

            // Get active reservations
            $reservations = $this->getActiveReservations($latitude, $longitude, $altitude, $radiusKm);

            // Get weather conditions affecting airspace
            $weatherConditions = $this->getWeatherConditions($latitude, $longitude, $altitude);

            // Calculate airspace availability
            $availability = $this->calculateAirspaceAvailability($restrictions, $reservations, $weatherConditions);

            return [
                'location' => [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'altitude' => $altitude,
                    'radius_km' => $radiusKm
                ],
                'restrictions' => $restrictions,
                'active_reservations' => $reservations,
                'weather_conditions' => $weatherConditions,
                'availability' => $availability,
                'last_updated' => date('c')
            ];

        } catch (Exception $e) {
            $this->logger->error("Failed to get airspace status", ['error' => $e->getMessage()]);
            throw new Exception("Failed to get airspace status: " . $e->getMessage());
        }
    }

    /**
     * Track drone in real-time
     */
    public function trackDrone($droneId, $telemetryData)
    {
        try {
            // Validate telemetry data
            $this->validateTelemetryData($telemetryData);

            // Store telemetry data
            $this->storeTelemetryData($droneId, $telemetryData);

            // Check for airspace violations
            $violations = $this->checkAirspaceViolations($droneId, $telemetryData);

            // Check for flight plan compliance
            $compliance = $this->checkFlightPlanCompliance($droneId, $telemetryData);

            // Update drone status
            $this->updateDroneStatus($droneId, $telemetryData, $violations, $compliance);

            // Send alerts if necessary
            if (!empty($violations)) {
                $this->sendViolationAlerts($droneId, $violations);
            }

            return [
                'drone_id' => $droneId,
                'status' => 'tracked',
                'violations' => $violations,
                'compliance_status' => $compliance,
                'last_update' => date('c')
            ];

        } catch (Exception $e) {
            $this->logger->error("Drone tracking failed", [
                'drone_id' => $droneId,
                'error' => $e->getMessage()
            ]);

            throw new Exception("Drone tracking failed: " . $e->getMessage());
        }
    }

    /**
     * Reserve airspace for drone operations
     */
    public function reserveAirspace($reservationData)
    {
        $this->logger->info("Reserving airspace", [
            'operator_id' => $reservationData['operator_id'],
            'purpose' => $reservationData['purpose']
        ]);

        $this->db->beginTransaction();

        try {
            // Validate reservation data
            $this->validateReservationData($reservationData);

            // Check for conflicts
            $conflicts = $this->checkReservationConflicts($reservationData);

            if (!empty($conflicts)) {
                throw new Exception("Airspace reservation conflicts: " . implode(', ', $conflicts));
            }

            // Generate reservation ID
            $reservationId = $this->generateReservationId();

            // Store reservation
            $this->storeAirspaceReservation($reservationId, $reservationData);

            // Submit to FAA if required
            if ($this->requiresFAAApproval($reservationData)) {
                $faaResponse = $this->submitToFAA($reservationId, $reservationData);
                $this->updateReservationWithFAAReference($reservationId, $faaResponse);
            }

            // Log reservation
            $this->logAirspaceReservation($reservationId, $reservationData);

            $this->db->commit();

            return [
                'reservation_id' => $reservationId,
                'status' => 'reserved',
                'valid_from' => $reservationData['start_time'],
                'valid_until' => $reservationData['end_time'],
                'message' => 'Airspace reserved successfully'
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logger->error("Airspace reservation failed", [
                'operator_id' => $reservationData['operator_id'],
                'error' => $e->getMessage()
            ]);

            throw new Exception("Airspace reservation failed: " . $e->getMessage());
        }
    }

    /**
     * Get drone fleet status
     */
    public function getDroneFleetStatus($operatorId = null)
    {
        try {
            $query = "
                SELECT
                    d.*,
                    dp.pilot_name,
                    dp.certification_level,
                    COALESCE(dt.latitude, d.last_known_latitude) as current_latitude,
                    COALESCE(dt.longitude, d.last_known_longitude) as current_longitude,
                    COALESCE(dt.altitude, d.last_known_altitude) as current_altitude,
                    dt.last_update as telemetry_timestamp,
                    fp.flight_plan_id,
                    fp.status as flight_status,
                    fp.start_time,
                    fp.end_time
                FROM drones d
                LEFT JOIN drone_pilots dp ON d.operator_id = dp.operator_id
                LEFT JOIN drone_telemetry dt ON d.drone_id = dt.drone_id
                    AND dt.timestamp = (
                        SELECT MAX(timestamp) FROM drone_telemetry
                        WHERE drone_id = d.drone_id
                    )
                LEFT JOIN flight_plans fp ON d.drone_id = fp.drone_id
                    AND fp.status IN ('approved', 'active')
                    AND CURRENT_TIMESTAMP BETWEEN fp.start_time AND fp.end_time
            ";

            $params = [];

            if ($operatorId) {
                $query .= " WHERE d.operator_id = ?";
                $params[] = $operatorId;
            }

            $query .= " ORDER BY d.drone_id";

            $stmt = $this->db->prepare($query);
            $stmt->execute($params);

            $drones = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get fleet statistics
            $stats = $this->calculateFleetStatistics($drones);

            return [
                'drones' => $drones,
                'statistics' => $stats,
                'last_updated' => date('c')
            ];

        } catch (Exception $e) {
            $this->logger->error("Failed to get drone fleet status", ['error' => $e->getMessage()]);
            throw new Exception("Failed to get drone fleet status: " . $e->getMessage());
        }
    }

    /**
     * Report drone incident
     */
    public function reportDroneIncident($incidentData)
    {
        $this->logger->info("Reporting drone incident", [
            'drone_id' => $incidentData['drone_id'],
            'incident_type' => $incidentData['incident_type']
        ]);

        $this->db->beginTransaction();

        try {
            // Validate incident data
            $this->validateIncidentData($incidentData);

            // Generate incident ID
            $incidentId = $this->generateIncidentId();

            // Store incident
            $this->storeDroneIncident($incidentId, $incidentData);

            // Update drone status to grounded
            $this->groundDrone($incidentData['drone_id'], $incidentId);

            // Notify relevant authorities
            $this->notifyAuthorities($incidentId, $incidentData);

            // Log incident
            $this->logDroneIncident($incidentId, $incidentData);

            $this->db->commit();

            return [
                'incident_id' => $incidentId,
                'status' => 'reported',
                'drone_grounded' => true,
                'authorities_notified' => true,
                'message' => 'Drone incident reported successfully'
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logger->error("Drone incident reporting failed", [
                'drone_id' => $incidentData['drone_id'],
                'error' => $e->getMessage()
            ]);

            throw new Exception("Drone incident reporting failed: " . $e->getMessage());
        }
    }

    // Private helper methods

    private function validateFlightPlanData($data)
    {
        $required = ['drone_id', 'pilot_id', 'start_time', 'end_time', 'flight_path'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                throw new Exception("Missing required field: {$field}");
            }
        }

        if (strtotime($data['start_time']) >= strtotime($data['end_time'])) {
            throw new Exception("End time must be after start time");
        }
    }

    private function checkAirspaceAvailability($flightPlan)
    {
        // Check FAA restrictions
        $faaRestrictions = $this->getFAARestrictions(
            $flightPlan['flight_path']['center']['latitude'],
            $flightPlan['flight_path']['center']['longitude'],
            $flightPlan['max_altitude'],
            $flightPlan['flight_path']['radius_km']
        );

        // Check for restricted airspace
        foreach ($faaRestrictions as $restriction) {
            if ($this->flightPathIntersectsRestriction($flightPlan['flight_path'], $restriction)) {
                return [
                    'available' => false,
                    'reason' => "Flight path intersects {$restriction['type']} airspace"
                ];
            }
        }

        return ['available' => true];
    }

    private function checkFlightConflicts($flightPlan)
    {
        $stmt = $this->db->prepare("
            SELECT fp.flight_plan_id, fp.drone_id, fp.pilot_id
            FROM flight_plans fp
            WHERE fp.status IN ('approved', 'active')
            AND (
                (fp.start_time BETWEEN ? AND ?) OR
                (fp.end_time BETWEEN ? AND ?) OR
                (? BETWEEN fp.start_time AND fp.end_time)
            )
        ");

        $stmt->execute([
            $flightPlan['start_time'], $flightPlan['end_time'],
            $flightPlan['start_time'], $flightPlan['end_time'],
            $flightPlan['start_time']
        ]);

        $conflictingFlights = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $conflicts = [];
        foreach ($conflictingFlights as $flight) {
            if ($this->flightPathsConflict($flightPlan['flight_path'], $flight)) {
                $conflicts[] = "Conflict with flight plan {$flight['flight_plan_id']}";
            }
        }

        return [
            'has_conflicts' => !empty($conflicts),
            'conflicts' => $conflicts
        ];
    }

    private function generateFlightPlanId()
    {
        return 'FP-' . date('YmdHis') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
    }

    private function storeFlightPlan($flightPlanId, $data)
    {
        $stmt = $this->db->prepare("
            INSERT INTO flight_plans (
                flight_plan_id, drone_id, pilot_id, operator_id,
                start_time, end_time, max_altitude, flight_path,
                purpose, status, submitted_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'submitted', CURRENT_TIMESTAMP)
        ");

        $stmt->execute([
            $flightPlanId,
            $data['drone_id'],
            $data['pilot_id'],
            $data['operator_id'] ?? null,
            $data['start_time'],
            $data['end_time'],
            $data['max_altitude'] ?? 400,
            json_encode($data['flight_path']),
            $data['purpose'] ?? 'general',
        ]);
    }

    private function submitToDTMSystem($flightPlanId, $data)
    {
        $dtmData = [
            'flight_plan_id' => $flightPlanId,
            'drone_id' => $data['drone_id'],
            'pilot_id' => $data['pilot_id'],
            'flight_path' => $data['flight_path'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'max_altitude' => $data['max_altitude'] ?? 400,
            'purpose' => $data['purpose'] ?? 'general'
        ];

        return $this->makeDTMApiCall('POST', '/flight-plans', $dtmData);
    }

    private function makeDTMApiCall($method, $endpoint, $data = null)
    {
        $url = $this->dtmApiUrl . $endpoint;

        $headers = [
            'Authorization: Bearer ' . $this->dtmApiKey,
            'Content-Type: application/json',
            'Accept: application/json'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($method === 'POST' && $data) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method === 'PUT' && $data) {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            throw new Exception("DTM API call failed: " . curl_error($ch));
        }

        curl_close($ch);

        if ($httpCode >= 400) {
            throw new Exception("DTM API returned error {$httpCode}: {$response}");
        }

        return json_decode($response, true);
    }

    private function getFAARestrictions($lat, $lon, $alt, $radius)
    {
        // This would integrate with FAA's airspace API
        // For now, return mock data
        return [
            [
                'type' => 'class_b_airspace',
                'latitude' => $lat,
                'longitude' => $lon,
                'radius_km' => 10,
                'min_altitude' => 0,
                'max_altitude' => 10000
            ]
        ];
    }

    private function getActiveReservations($lat, $lon, $alt, $radius)
    {
        $stmt = $this->db->prepare("
            SELECT * FROM airspace_reservations
            WHERE status = 'active'
            AND ST_DWithin(
                ST_MakePoint(longitude, latitude)::geography,
                ST_MakePoint(?, ?)::geography,
                ? * 1000
            )
            AND ? BETWEEN min_altitude AND max_altitude
        ");

        $stmt->execute([$lon, $lat, $radius, $alt]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getWeatherConditions($lat, $lon, $alt)
    {
        // This would integrate with weather APIs
        // For now, return mock data
        return [
            'wind_speed' => 15,
            'wind_direction' => 180,
            'visibility' => 10,
            'precipitation' => 0,
            'temperature' => 22
        ];
    }

    private function calculateAirspaceAvailability($restrictions, $reservations, $weather)
    {
        // Calculate airspace availability based on restrictions, reservations, and weather
        $availability = 'available';

        if (!empty($restrictions)) {
            $availability = 'restricted';
        }

        if (!empty($reservations)) {
            $availability = 'reserved';
        }

        if ($weather['wind_speed'] > 20 || $weather['visibility'] < 3) {
            $availability = 'weather_restricted';
        }

        return [
            'status' => $availability,
            'restrictions_count' => count($restrictions),
            'reservations_count' => count($reservations),
            'weather_suitable' => $weather['wind_speed'] <= 20 && $weather['visibility'] >= 3
        ];
    }

    private function validateTelemetryData($data)
    {
        $required = ['latitude', 'longitude', 'altitude', 'speed', 'heading'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                throw new Exception("Missing required telemetry field: {$field}");
            }
        }
    }

    private function storeTelemetryData($droneId, $data)
    {
        $stmt = $this->db->prepare("
            INSERT INTO drone_telemetry (
                drone_id, latitude, longitude, altitude, speed,
                heading, battery_level, signal_strength, timestamp
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ");

        $stmt->execute([
            $droneId,
            $data['latitude'],
            $data['longitude'],
            $data['altitude'],
            $data['speed'] ?? 0,
            $data['heading'] ?? 0,
            $data['battery_level'] ?? null,
            $data['signal_strength'] ?? null
        ]);
    }

    private function checkAirspaceViolations($droneId, $telemetry)
    {
        $violations = [];

        // Check altitude restrictions
        if ($telemetry['altitude'] > 400) { // FAA limit for most operations
            $violations[] = 'altitude_violation';
        }

        // Check for restricted airspace
        $restrictions = $this->getFAARestrictions(
            $telemetry['latitude'],
            $telemetry['longitude'],
            $telemetry['altitude'],
            1
        );

        if (!empty($restrictions)) {
            $violations[] = 'restricted_airspace';
        }

        return $violations;
    }

    private function checkFlightPlanCompliance($droneId, $telemetry)
    {
        $stmt = $this->db->prepare("
            SELECT * FROM flight_plans
            WHERE drone_id = ? AND status = 'active'
            AND CURRENT_TIMESTAMP BETWEEN start_time AND end_time
        ");

        $stmt->execute([$droneId]);
        $flightPlan = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$flightPlan) {
            return 'no_active_flight_plan';
        }

        // Check if drone is within flight plan boundaries
        $flightPath = json_decode($flightPlan['flight_path'], true);
        $distance = $this->calculateDistance(
            $telemetry['latitude'],
            $telemetry['longitude'],
            $flightPath['center']['latitude'],
            $flightPath['center']['longitude']
        );

        if ($distance > $flightPath['radius_km']) {
            return 'outside_flight_path';
        }

        if ($telemetry['altitude'] > $flightPlan['max_altitude']) {
            return 'altitude_violation';
        }

        return 'compliant';
    }

    private function updateDroneStatus($droneId, $telemetry, $violations, $compliance)
    {
        $status = 'active';

        if (!empty($violations)) {
            $status = 'violation';
        } elseif ($compliance !== 'compliant') {
            $status = 'non_compliant';
        }

        $stmt = $this->db->prepare("
            UPDATE drones
            SET status = ?, last_known_latitude = ?, last_known_longitude = ?,
                last_known_altitude = ?, last_update = CURRENT_TIMESTAMP
            WHERE drone_id = ?
        ");

        $stmt->execute([
            $status,
            $telemetry['latitude'],
            $telemetry['longitude'],
            $telemetry['altitude'],
            $droneId
        ]);
    }

    private function sendViolationAlerts($droneId, $violations)
    {
        // Send alerts to pilot and authorities
        $this->logger->warning("Drone violations detected", [
            'drone_id' => $droneId,
            'violations' => $violations
        ]);

        // Implementation for sending alerts via email, SMS, etc.
    }

    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371; // km

        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($lonDelta / 2) * sin($lonDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    private function calculateFleetStatistics($drones)
    {
        $total = count($drones);
        $active = count(array_filter($drones, fn($d) => $d['status'] === 'active'));
        $maintenance = count(array_filter($drones, fn($d) => $d['status'] === 'maintenance'));
        $grounded = count(array_filter($drones, fn($d) => $d['status'] === 'grounded'));

        return [
            'total_drones' => $total,
            'active_drones' => $active,
            'maintenance_drones' => $maintenance,
            'grounded_drones' => $grounded,
            'active_percentage' => $total > 0 ? round(($active / $total) * 100, 2) : 0
        ];
    }

    // Additional helper methods would be implemented here...
    // (generateReservationId, validateReservationData, etc.)
}
