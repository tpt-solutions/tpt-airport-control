<?php
/**
 * Flight Plan & Clearance Data Integration
 *
 * Handles integration with ATC flight plan data feeds, clearance processing,
 * CPDLC (Controller-Pilot Data Link Communications), and ACARS message integration.
 */

class FlightPlanIntegration
{
    private $db;
    private $logger;

    public function __construct($database, $logger)
    {
        $this->db = $database;
        $this->logger = $logger;
    }

    /**
     * Process incoming flight plan data from ATC system
     */
    public function processFlightPlan($flightPlanData)
    {
        try {
            $this->db->beginTransaction();

            // Insert or update flight plan
            $flightPlanId = $this->insertFlightPlan($flightPlanData);

            // Process associated clearances if provided
            if (isset($flightPlanData['clearances'])) {
                foreach ($flightPlanData['clearances'] as $clearance) {
                    $this->processClearance($flightPlanId, $clearance);
                }
            }

            $this->db->commit();
            $this->logger->info("Flight plan processed successfully", ['flight_plan_id' => $flightPlanId]);

            return ['success' => true, 'flight_plan_id' => $flightPlanId];

        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logger->error("Failed to process flight plan", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Insert flight plan data
     */
    private function insertFlightPlan($data)
    {
        $stmt = $this->db->prepare("
            INSERT INTO flight_plans (
                flight_id, aircraft_id, departure_airport, arrival_airport,
                departure_time, arrival_time, route, altitude_profile,
                speed_profile, fuel_requirements, alternate_airports,
                pilot_in_command, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON CONFLICT (flight_id) DO UPDATE SET
                route = EXCLUDED.route,
                altitude_profile = EXCLUDED.altitude_profile,
                speed_profile = EXCLUDED.speed_profile,
                status = EXCLUDED.status,
                updated_at = CURRENT_TIMESTAMP
            RETURNING id
        ");

        $stmt->execute([
            $data['flight_id'] ?? null,
            $data['aircraft_id'],
            $data['departure_airport'],
            $data['arrival_airport'],
            $data['departure_time'],
            $data['arrival_time'],
            $data['route'] ?? null,
            $data['altitude_profile'] ?? null,
            $data['speed_profile'] ?? null,
            $data['fuel_requirements'] ?? null,
            $data['alternate_airports'] ?? null,
            $data['pilot_in_command'] ?? null,
            $data['status'] ?? 'filed'
        ]);

        return $stmt->fetchColumn();
    }

    /**
     * Process clearance data
     */
    public function processClearance($flightPlanId, $clearanceData)
    {
        $stmt = $this->db->prepare("
            INSERT INTO clearances (
                flight_plan_id, clearance_number, clearance_type, issued_by,
                valid_from, valid_to, clearance_text, restrictions,
                frequency_assignments, squawk_code, runway_assignment,
                heading_assignments, altitude_assignments, speed_assignments
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $flightPlanId,
            $clearanceData['clearance_number'],
            $clearanceData['clearance_type'],
            $clearanceData['issued_by'] ?? null,
            $clearanceData['valid_from'],
            $clearanceData['valid_to'] ?? null,
            $clearanceData['clearance_text'],
            $clearanceData['restrictions'] ?? null,
            $clearanceData['frequency_assignments'] ?? null,
            $clearanceData['squawk_code'] ?? null,
            $clearanceData['runway_assignment'] ?? null,
            $clearanceData['heading_assignments'] ?? null,
            $clearanceData['altitude_assignments'] ?? null,
            $clearanceData['speed_assignments'] ?? null
        ]);

        return $this->db->lastInsertId();
    }

    /**
     * Process CPDLC message
     */
    public function processCPDLCMessage($messageData)
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO cpdlc_messages (
                    flight_plan_id, message_id, direction, message_type,
                    message_content, response_required, response_message_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $messageData['flight_plan_id'],
                $messageData['message_id'],
                $messageData['direction'],
                $messageData['message_type'],
                $messageData['message_content'],
                $messageData['response_required'] ?? false,
                $messageData['response_message_id'] ?? null
            ]);

            // Update acknowledgment if provided
            if (isset($messageData['acknowledged'])) {
                $this->acknowledgeCPDLCMessage($messageData['message_id'], $messageData['acknowledged']);
            }

            $this->logger->info("CPDLC message processed", ['message_id' => $messageData['message_id']]);
            return ['success' => true];

        } catch (Exception $e) {
            $this->logger->error("Failed to process CPDLC message", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Acknowledge CPDLC message
     */
    public function acknowledgeCPDLCMessage($messageId, $acknowledged = true)
    {
        $stmt = $this->db->prepare("
            UPDATE cpdlc_messages
            SET acknowledged = ?, acknowledged_at = CURRENT_TIMESTAMP
            WHERE message_id = ?
        ");

        $stmt->execute([$acknowledged, $messageId]);
    }

    /**
     * Process ACARS message
     */
    public function processACARSMessage($messageData)
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO acars_messages (
                    flight_plan_id, message_id, message_type, origin, destination,
                    message_text, priority
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $messageData['flight_plan_id'] ?? null,
                $messageData['message_id'],
                $messageData['message_type'],
                $messageData['origin'] ?? null,
                $messageData['destination'] ?? null,
                $messageData['message_text'],
                $messageData['priority'] ?? 'normal'
            ]);

            $this->logger->info("ACARS message processed", ['message_id' => $messageData['message_id']]);
            return ['success' => true];

        } catch (Exception $e) {
            $this->logger->error("Failed to process ACARS message", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get active flight plans
     */
    public function getActiveFlightPlans()
    {
        $stmt = $this->db->query("
            SELECT fp.*, f.flight_number, a.registration as aircraft_registration
            FROM flight_plans fp
            LEFT JOIN flights f ON fp.flight_id = f.id
            LEFT JOIN aircraft a ON f.aircraft_id = a.id
            WHERE fp.status IN ('filed', 'active')
            ORDER BY fp.departure_time ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get clearances for flight plan
     */
    public function getClearancesForFlight($flightPlanId)
    {
        $stmt = $this->db->prepare("
            SELECT * FROM clearances
            WHERE flight_plan_id = ?
            ORDER BY issued_at DESC
        ");

        $stmt->execute([$flightPlanId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Validate clearance data
     */
    public function validateClearance($clearanceData)
    {
        $errors = [];

        if (empty($clearanceData['clearance_number'])) {
            $errors[] = "Clearance number is required";
        }

        if (empty($clearanceData['clearance_type'])) {
            $errors[] = "Clearance type is required";
        }

        if (empty($clearanceData['valid_from'])) {
            $errors[] = "Valid from time is required";
        }

        if (empty($clearanceData['clearance_text'])) {
            $errors[] = "Clearance text is required";
        }

        return $errors;
    }

    /**
     * Automated clearance validation
     */
    public function validateClearanceAutomatically($clearanceData)
    {
        // Check for conflicts with existing clearances
        $conflicts = $this->checkClearanceConflicts($clearanceData);

        // Validate against airspace restrictions
        $airspaceIssues = $this->checkAirspaceRestrictions($clearanceData);

        // Check weather conditions
        $weatherIssues = $this->checkWeatherConditions($clearanceData);

        return [
            'valid' => empty($conflicts) && empty($airspaceIssues) && empty($weatherIssues),
            'conflicts' => $conflicts,
            'airspace_issues' => $airspaceIssues,
            'weather_issues' => $weatherIssues
        ];
    }

    /**
     * Check for clearance conflicts
     */
    private function checkClearanceConflicts($clearanceData)
    {
        // Implementation for checking conflicts with other clearances
        // This would involve spatial and temporal analysis
        return [];
    }

    /**
     * Check airspace restrictions
     */
    private function checkAirspaceRestrictions($clearanceData)
    {
        // Implementation for checking airspace restrictions
        return [];
    }

    /**
     * Check weather conditions
     */
    private function checkWeatherConditions($clearanceData)
    {
        // Implementation for checking weather conditions
        return [];
    }
}
