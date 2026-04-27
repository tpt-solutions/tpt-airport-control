<?php

/**
 * Special Services Service
 *
 * Business logic for special assistance requests, accessibility services,
 * medical equipment tracking, and language services coordination
 */

class SpecialServicesService
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
        $this->logger = new Logger('special_services');
    }

    /**
     * Get all special services requests with filtering
     */
    public function getAllRequests($filters = [])
    {
        $whereClause = "";
        $params = [];

        if (isset($filters['status'])) {
            $whereClause .= " AND ssr.status = ?";
            $params[] = $filters['status'];
        }

        if (isset($filters['service_type'])) {
            $whereClause .= " AND ssr.service_type = ?";
            $params[] = $filters['service_type'];
        }

        if (isset($filters['start_date'])) {
            $whereClause .= " AND ssr.created_at >= ?";
            $params[] = $filters['start_date'];
        }

        if (isset($filters['end_date'])) {
            $whereClause .= " AND ssr.created_at <= ?";
            $params[] = $filters['end_date'];
        }

        $stmt = $this->db->prepare("
            SELECT
                ssr.*,
                p.first_name,
                p.last_name,
                p.passport_number,
                f.flight_number,
                f.origin,
                f.destination,
                f.departure_time
            FROM special_services_requests ssr
            LEFT JOIN passengers p ON ssr.passenger_id = p.passenger_id
            LEFT JOIN flights f ON ssr.flight_id = f.flight_id
            WHERE 1=1 $whereClause
            ORDER BY ssr.created_at DESC
            LIMIT ?
        ");

        $params[] = $filters['limit'] ?? 50;
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get request by ID
     */
    public function getRequestById($requestId)
    {
        $stmt = $this->db->prepare("
            SELECT
                ssr.*,
                p.first_name,
                p.last_name,
                p.passport_number,
                p.contact_number,
                f.flight_number,
                f.origin,
                f.destination,
                f.departure_time,
                f.arrival_time
            FROM special_services_requests ssr
            LEFT JOIN passengers p ON ssr.passenger_id = p.passenger_id
            LEFT JOIN flights f ON ssr.flight_id = f.flight_id
            WHERE ssr.request_id = ?
        ");

        $stmt->execute([$requestId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Create new special services request
     */
    public function createRequest($requestData)
    {
        $this->logger->info("Creating special services request", $requestData);

        $stmt = $this->db->prepare("
            INSERT INTO special_services_requests (
                passenger_id, service_type, request_details, priority_level,
                requested_time, flight_id, special_requirements, contact_info
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $requestData['passenger_id'],
            $requestData['service_type'],
            $requestData['request_details'],
            $requestData['priority_level'] ?? 'medium',
            $requestData['requested_time'] ?? date('Y-m-d H:i:s'),
            $requestData['flight_id'] ?? null,
            isset($requestData['special_requirements']) ? json_encode($requestData['special_requirements']) : null,
            isset($requestData['contact_info']) ? json_encode($requestData['contact_info']) : null
        ]);

        return $this->db->lastInsertId();
    }

    /**
     * Update request
     */
    public function updateRequest($requestId, $updateData)
    {
        $this->logger->info("Updating special services request", ['request_id' => $requestId, 'updates' => $updateData]);

        $setClause = "";
        $params = [];

        if (isset($updateData['status'])) {
            $setClause .= "status = ?, ";
            $params[] = $updateData['status'];
        }

        if (isset($updateData['assigned_staff_id'])) {
            $setClause .= "assigned_staff_id = ?, ";
            $params[] = $updateData['assigned_staff_id'];
        }

        if (isset($updateData['completion_time'])) {
            $setClause .= "completion_time = ?, ";
            $params[] = $updateData['completion_time'];
        }

        if (isset($updateData['notes'])) {
            $setClause .= "notes = ?, ";
            $params[] = $updateData['notes'];
        }

        $setClause = rtrim($setClause, ', ');
        $params[] = $requestId;

        $stmt = $this->db->prepare("
            UPDATE special_services_requests
            SET $setClause, updated_at = CURRENT_TIMESTAMP
            WHERE request_id = ?
        ");

        $stmt->execute($params);
        return true;
    }

    /**
     * Delete request
     */
    public function deleteRequest($requestId)
    {
        $stmt = $this->db->prepare("DELETE FROM special_services_requests WHERE request_id = ?");
        $stmt->execute([$requestId]);
        return true;
    }

    /**
     * Assign request to staff member
     */
    public function assignRequest($requestId, $staffId)
    {
        $stmt = $this->db->prepare("
            UPDATE special_services_requests
            SET assigned_staff_id = ?, status = 'assigned', updated_at = CURRENT_TIMESTAMP
            WHERE request_id = ?
        ");

        $stmt->execute([$staffId, $requestId]);
        return true;
    }

    /**
     * Get service types
     */
    public function getServiceTypes()
    {
        return [
            ['type' => 'wheelchair', 'name' => 'Wheelchair Assistance', 'description' => 'Assistance with wheelchair services'],
            ['type' => 'medical_assistance', 'name' => 'Medical Assistance', 'description' => 'Medical aid and support services'],
            ['type' => 'visual_impairment', 'name' => 'Visual Impairment', 'description' => 'Services for visually impaired passengers'],
            ['type' => 'hearing_impairment', 'name' => 'Hearing Impairment', 'description' => 'Services for hearing impaired passengers'],
            ['type' => 'mobility_aid', 'name' => 'Mobility Aid', 'description' => 'Assistance with mobility aids and devices'],
            ['type' => 'oxygen_support', 'name' => 'Oxygen Support', 'description' => 'Oxygen equipment and monitoring'],
            ['type' => 'language_assistance', 'name' => 'Language Assistance', 'description' => 'Translation and interpretation services'],
            ['type' => 'unaccompanied_minor', 'name' => 'Unaccompanied Minor', 'description' => 'Special care for unaccompanied children']
        ];
    }

    /**
     * Get medical equipment
     */
    public function getMedicalEquipment()
    {
        $stmt = $this->db->prepare("
            SELECT * FROM medical_equipment
            ORDER BY equipment_type, location
        ");

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Add medical equipment
     */
    public function addMedicalEquipment($equipmentData)
    {
        $stmt = $this->db->prepare("
            INSERT INTO medical_equipment (
                equipment_type, location, status, serial_number,
                last_maintenance_date, next_maintenance_date, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $equipmentData['equipment_type'],
            $equipmentData['location'],
            $equipmentData['status'],
            $equipmentData['serial_number'] ?? null,
            $equipmentData['last_maintenance_date'] ?? null,
            $equipmentData['next_maintenance_date'] ?? null,
            $equipmentData['notes'] ?? null
        ]);

        return $this->db->lastInsertId();
    }

    /**
     * Update medical equipment
     */
    public function updateMedicalEquipment($equipmentId, $updateData)
    {
        $setClause = "";
        $params = [];

        if (isset($updateData['status'])) {
            $setClause .= "status = ?, ";
            $params[] = $updateData['status'];
        }

        if (isset($updateData['location'])) {
            $setClause .= "location = ?, ";
            $params[] = $updateData['location'];
        }

        if (isset($updateData['last_maintenance_date'])) {
            $setClause .= "last_maintenance_date = ?, ";
            $params[] = $updateData['last_maintenance_date'];
        }

        if (isset($updateData['next_maintenance_date'])) {
            $setClause .= "next_maintenance_date = ?, ";
            $params[] = $updateData['next_maintenance_date'];
        }

        if (isset($updateData['notes'])) {
            $setClause .= "notes = ?, ";
            $params[] = $updateData['notes'];
        }

        $setClause = rtrim($setClause, ', ');
        $params[] = $equipmentId;

        $stmt = $this->db->prepare("
            UPDATE medical_equipment
            SET $setClause, updated_at = CURRENT_TIMESTAMP
            WHERE equipment_id = ?
        ");

        $stmt->execute($params);
        return true;
    }

    /**
     * Delete medical equipment
     */
    public function deleteMedicalEquipment($equipmentId)
    {
        $stmt = $this->db->prepare("DELETE FROM medical_equipment WHERE equipment_id = ?");
        $stmt->execute([$equipmentId]);
        return true;
    }

    /**
     * Get language services
     */
    public function getLanguageServices()
    {
        $stmt = $this->db->prepare("
            SELECT * FROM language_providers
            ORDER BY availability_status, provider_name
        ");

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Add language provider
     */
    public function addLanguageProvider($providerData)
    {
        $stmt = $this->db->prepare("
            INSERT INTO language_providers (
                provider_name, languages, contact_info, availability_status,
                certifications, hourly_rate, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $providerData['provider_name'],
            json_encode($providerData['languages']),
            isset($providerData['contact_info']) ? json_encode($providerData['contact_info']) : null,
            $providerData['availability_status'],
            isset($providerData['certifications']) ? json_encode($providerData['certifications']) : null,
            $providerData['hourly_rate'] ?? null,
            $providerData['notes'] ?? null
        ]);

        return $this->db->lastInsertId();
    }

    /**
     * Update language provider
     */
    public function updateLanguageProvider($providerId, $updateData)
    {
        $setClause = "";
        $params = [];

        if (isset($updateData['availability_status'])) {
            $setClause .= "availability_status = ?, ";
            $params[] = $updateData['availability_status'];
        }

        if (isset($updateData['contact_info'])) {
            $setClause .= "contact_info = ?, ";
            $params[] = json_encode($updateData['contact_info']);
        }

        if (isset($updateData['hourly_rate'])) {
            $setClause .= "hourly_rate = ?, ";
            $params[] = $updateData['hourly_rate'];
        }

        if (isset($updateData['notes'])) {
            $setClause .= "notes = ?, ";
            $params[] = $updateData['notes'];
        }

        $setClause = rtrim($setClause, ', ');
        $params[] = $providerId;

        $stmt = $this->db->prepare("
            UPDATE language_providers
            SET $setClause, updated_at = CURRENT_TIMESTAMP
            WHERE provider_id = ?
        ");

        $stmt->execute($params);
        return true;
    }

    /**
     * Delete language provider
     */
    public function deleteLanguageProvider($providerId)
    {
        $stmt = $this->db->prepare("DELETE FROM language_providers WHERE provider_id = ?");
        $stmt->execute([$providerId]);
        return true;
    }

    /**
     * Get service statistics
     */
    public function getServiceStatistics()
    {
        $stmt = $this->db->prepare("
            SELECT
                COUNT(*) as total_requests,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_requests,
                COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress_requests,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_requests,
                COUNT(DISTINCT passenger_id) as unique_passengers_served,
                ROUND(AVG(CASE WHEN completion_time IS NOT NULL THEN
                    EXTRACT(EPOCH FROM (completion_time - created_at))/3600 END), 2) as avg_resolution_time_hours
            FROM special_services_requests
            WHERE created_at >= CURRENT_DATE - INTERVAL '30 days'
        ");

        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get database connection (for use by API functions)
     */
    public function getDatabaseConnection()
    {
        return $this->db;
    }
}
