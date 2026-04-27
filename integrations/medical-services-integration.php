<?php

/**
 * Medical Services Integration
 *
 * Integrates with external medical services and emergency response systems including:
 * - Hospital emergency departments
 * - Ambulance services (EMS)
 * - Medical supply chains
 * - Telemedicine platforms
 * - Medical device monitoring
 * - Pharmacy systems
 */

class MedicalServicesIntegration {

    private $config;
    private $logger;
    private $cache;

    public function __construct() {
        $this->config = require '../backend/config/database.php';
        $this->logger = new Logger();
        $this->cache = new Cache();
    }

    /**
     * Request emergency medical assistance
     */
    public function requestEmergencyMedical($incidentData) {
        $this->logger->info('Requesting emergency medical assistance', $incidentData);

        $results = [];

        // Request ambulance services
        $ambulanceResult = $this->requestAmbulance($incidentData);
        $results['ambulance'] = $ambulanceResult;

        // Notify nearest hospital
        $hospitalResult = $this->notifyHospital($incidentData);
        $results['hospital'] = $hospitalResult;

        // Alert medical team
        $medicalTeamResult = $this->alertMedicalTeam($incidentData);
        $results['medical_team'] = $medicalTeamResult;

        // If multiple casualties, request additional resources
        if (isset($incidentData['casualty_count']) && $incidentData['casualty_count'] > 1) {
            $additionalResult = $this->requestAdditionalResources($incidentData);
            $results['additional_resources'] = $additionalResult;
        }

        $this->logger->info('Emergency medical assistance requested', [
            'incident_id' => $incidentData['incident_id'],
            'results' => $results
        ]);

        return $results;
    }

    /**
     * Request ambulance services
     */
    private function requestAmbulance($incidentData) {
        try {
            $emsConfig = $this->getEMSConfig();

            $payload = [
                'incident_id' => $incidentData['incident_id'],
                'location' => $incidentData['location'],
                'coordinates' => isset($incidentData['coordinates']) ? $incidentData['coordinates'] : null,
                'incident_type' => $incidentData['incident_type'],
                'casualty_count' => isset($incidentData['casualty_count']) ? $incidentData['casualty_count'] : 1,
                'severity' => isset($incidentData['severity']) ? $incidentData['severity'] : 'unknown',
                'caller_info' => [
                    'name' => isset($incidentData['caller_name']) ? $incidentData['caller_name'] : 'Airport Emergency',
                    'phone' => isset($incidentData['caller_phone']) ? $incidentData['caller_phone'] : 'Emergency Line',
                    'location' => 'Airport Emergency Operations Center'
                ],
                'special_requirements' => isset($incidentData['medical_requirements']) ? $incidentData['medical_requirements'] : [],
                'timestamp' => date('c')
            ];

            $response = $this->makeAPIRequest(
                $emsConfig['api_url'] . '/emergency-dispatch',
                'POST',
                $payload,
                $emsConfig['api_key']
            );

            if ($response['success']) {
                return [
                    'success' => true,
                    'dispatch_id' => isset($response['data']['dispatch_id']) ? $response['data']['dispatch_id'] : null,
                    'estimated_arrival' => isset($response['data']['eta_minutes']) ? $response['data']['eta_minutes'] : null,
                    'ambulance_count' => isset($response['data']['units_dispatched']) ? $response['data']['units_dispatched'] : 1,
                    'status' => 'dispatched'
                ];
            }

            return [
                'success' => false,
                'error' => $response['error'] ?? 'EMS dispatch failed'
            ];

        } catch (Exception $e) {
            $this->logger->error('Ambulance request error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => 'EMS service unavailable'
            ];
        }
    }

    /**
     * Notify hospital emergency department
     */
    private function notifyHospital($incidentData) {
        try {
            $hospitalConfig = $this->getHospitalConfig();

            // Find nearest hospital based on incident location
            $nearestHospital = $this->findNearestHospital($incidentData['location']);

            $payload = [
                'incident_id' => $incidentData['incident_id'],
                'hospital_id' => $nearestHospital['id'],
                'patient_count' => isset($incidentData['casualty_count']) ? $incidentData['casualty_count'] : 1,
                'incident_type' => $incidentData['incident_type'],
                'severity' => isset($incidentData['severity']) ? $incidentData['severity'] : 'unknown',
                'estimated_arrival' => isset($incidentData['estimated_arrival']) ? $incidentData['estimated_arrival'] : null,
                'patient_info' => isset($incidentData['patient_info']) ? $incidentData['patient_info'] : [],
                'special_requirements' => isset($incidentData['medical_requirements']) ? $incidentData['medical_requirements'] : [],
                'referring_facility' => 'Airport Emergency Services',
                'timestamp' => date('c')
            ];

            $response = $this->makeAPIRequest(
                $hospitalConfig['api_url'] . '/emergency-admission',
                'POST',
                $payload,
                $hospitalConfig['api_key']
            );

            if ($response['success']) {
                return [
                    'success' => true,
                    'hospital_name' => $nearestHospital['name'],
                    'admission_id' => isset($response['data']['admission_id']) ? $response['data']['admission_id'] : null,
                    'bed_status' => isset($response['data']['bed_availability']) ? $response['data']['bed_availability'] : 'unknown',
                    'specialists_notified' => isset($response['data']['specialists_alerted']) ? $response['data']['specialists_alerted'] : []
                ];
            }

            return [
                'success' => false,
                'error' => $response['error'] ?? 'Hospital notification failed'
            ];

        } catch (Exception $e) {
            $this->logger->error('Hospital notification error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => 'Hospital notification service unavailable'
            ];
        }
    }

    /**
     * Alert airport medical team
     */
    private function alertMedicalTeam($incidentData) {
        try {
            // Get airport medical team members
            $medicalTeam = $this->getMedicalTeamMembers();

            if (empty($medicalTeam)) {
                return [
                    'success' => false,
                    'error' => 'No medical team members available'
                ];
            }

            $alertData = [
                'alert_type' => 'medical_emergency',
                'message' => "Medical emergency at {$incidentData['location']}. " . (isset($incidentData['casualty_count']) ? $incidentData['casualty_count'] : 1) . " casualty(ies).",
                'priority' => 'high',
                'location' => $incidentData['location'],
                'incident_id' => $incidentData['incident_id'],
                'instructions' => 'Proceed to incident location immediately with medical equipment.'
            ];

            // Use emergency notification system to alert medical team
            $notificationIntegration = new EmergencyNotificationIntegration();
            $result = $notificationIntegration->sendTargetedAlert($alertData, ['medical_team']);

            return [
                'success' => true,
                'team_members_alerted' => count($medicalTeam),
                'notification_result' => $result,
                'response_time' => 'immediate'
            ];

        } catch (Exception $e) {
            $this->logger->error('Medical team alert error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => 'Medical team alert failed'
            ];
        }
    }

    /**
     * Request additional medical resources for mass casualty incidents
     */
    private function requestAdditionalResources($incidentData) {
        try {
            $resourcesConfig = $this->getResourcesConfig();

            $payload = [
                'incident_id' => $incidentData['incident_id'],
                'casualty_count' => $incidentData['casualty_count'],
                'incident_type' => $incidentData['incident_type'],
                'location' => $incidentData['location'],
                'resources_needed' => $this->calculateRequiredResources($incidentData),
                'timeline' => [
                    'immediate' => '0-15 minutes',
                    'short_term' => '15-60 minutes',
                    'long_term' => '1-24 hours'
                ],
                'coordination_center' => 'Airport Emergency Operations Center',
                'timestamp' => date('c')
            ];

            $response = $this->makeAPIRequest(
                $resourcesConfig['api_url'] . '/mass-casualty-resources',
                'POST',
                $payload,
                $resourcesConfig['api_key']
            );

            if ($response['success']) {
                return [
                    'success' => true,
                    'resources_dispatched' => isset($response['data']['resources_allocated']) ? $response['data']['resources_allocated'] : [],
                    'coordination_id' => isset($response['data']['coordination_id']) ? $response['data']['coordination_id'] : null,
                    'estimated_response_times' => isset($response['data']['response_times']) ? $response['data']['response_times'] : []
                ];
            }

            return [
                'success' => false,
                'error' => $response['error'] ?? 'Additional resources request failed'
            ];

        } catch (Exception $e) {
            $this->logger->error('Additional resources request error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => 'Additional resources service unavailable'
            ];
        }
    }

    /**
     * Get medical supplies from pharmacy system
     */
    public function requestMedicalSupplies($supplyRequest) {
        try {
            $pharmacyConfig = $this->getPharmacyConfig();

            $payload = [
                'request_id' => uniqid('supply_', true),
                'facility' => 'Airport Medical Center',
                'supplies' => $supplyRequest['supplies'],
                'priority' => isset($supplyRequest['priority']) ? $supplyRequest['priority'] : 'normal',
                'delivery_location' => isset($supplyRequest['location']) ? $supplyRequest['location'] : 'Airport Medical Center',
                'requester' => isset($supplyRequest['requester']) ? $supplyRequest['requester'] : 'Emergency Services',
                'timestamp' => date('c')
            ];

            $response = $this->makeAPIRequest(
                $pharmacyConfig['api_url'] . '/supply-request',
                'POST',
                $payload,
                $pharmacyConfig['api_key']
            );

            if ($response['success']) {
                return [
                    'success' => true,
                    'request_id' => $payload['request_id'],
                    'estimated_delivery' => isset($response['data']['delivery_time']) ? $response['data']['delivery_time'] : null,
                    'supplies_allocated' => isset($response['data']['allocated_supplies']) ? $response['data']['allocated_supplies'] : [],
                    'status' => 'processing'
                ];
            }

            return [
                'success' => false,
                'error' => $response['error'] ?? 'Medical supplies request failed'
            ];

        } catch (Exception $e) {
            $this->logger->error('Medical supplies request error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => 'Medical supplies service unavailable'
            ];
        }
    }

    /**
     * Connect to telemedicine platform for remote consultation
     */
    public function initiateTelemedicineConsultation($consultationData) {
        try {
            $telemedicineConfig = $this->getTelemedicineConfig();

            $payload = [
                'consultation_id' => uniqid('telemed_', true),
                'patient_info' => isset($consultationData['patient_info']) ? $consultationData['patient_info'] : [],
                'medical_issue' => $consultationData['medical_issue'],
                'severity' => isset($consultationData['severity']) ? $consultationData['severity'] : 'unknown',
                'location' => isset($consultationData['location']) ? $consultationData['location'] : 'Airport Medical Center',
                'specialist_required' => isset($consultationData['specialist_type']) ? $consultationData['specialist_type'] : 'general',
                'equipment_available' => isset($consultationData['equipment']) ? $consultationData['equipment'] : [],
                'timestamp' => date('c')
            ];

            $response = $this->makeAPIRequest(
                $telemedicineConfig['api_url'] . '/consultation-request',
                'POST',
                $payload,
                $telemedicineConfig['api_key']
            );

            if ($response['success']) {
                return [
                    'success' => true,
                    'consultation_id' => $payload['consultation_id'],
                'specialist_assigned' => isset($response['data']['specialist']) ? $response['data']['specialist'] : null,
                'estimated_wait_time' => isset($response['data']['wait_minutes']) ? $response['data']['wait_minutes'] : null,
                'connection_details' => isset($response['data']['connection_info']) ? $response['data']['connection_info'] : [],
                    'status' => 'connecting'
                ];
            }

            return [
                'success' => false,
                'error' => $response['error'] ?? 'Telemedicine consultation failed'
            ];

        } catch (Exception $e) {
            $this->logger->error('Telemedicine consultation error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => 'Telemedicine service unavailable'
            ];
        }
    }

    /**
     * Monitor medical device status
     */
    public function monitorMedicalDevices($deviceQuery = []) {
        try {
            $deviceConfig = $this->getDeviceMonitoringConfig();

            $payload = [
                'query_type' => $deviceQuery['type'] ?? 'all',
                'location' => $deviceQuery['location'] ?? 'airport',
                'device_types' => $deviceQuery['device_types'] ?? ['defibrillator', 'oxygen', 'ventilator'],
                'status_filter' => $deviceQuery['status'] ?? 'all',
                'timestamp' => date('c')
            ];

            $response = $this->makeAPIRequest(
                $deviceConfig['api_url'] . '/device-status',
                'GET',
                $payload,
                $deviceConfig['api_key']
            );

            if ($response['success']) {
                return [
                    'success' => true,
                    'devices' => isset($response['data']['devices']) ? $response['data']['devices'] : [],
                    'summary' => isset($response['data']['summary']) ? $response['data']['summary'] : [],
                    'alerts' => isset($response['data']['alerts']) ? $response['data']['alerts'] : [],
                    'last_updated' => isset($response['data']['timestamp']) ? $response['data']['timestamp'] : date('c')
                ];
            }

            return [
                'success' => false,
                'error' => $response['error'] ?? 'Device monitoring failed'
            ];

        } catch (Exception $e) {
            $this->logger->error('Medical device monitoring error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => 'Medical device monitoring service unavailable'
            ];
        }
    }

    /**
     * Get medical team members
     */
    private function getMedicalTeamMembers() {
        $cacheKey = 'medical_team_members';
        $cached = $this->cache->get($cacheKey);

        if ($cached) {
            return $cached;
        }

        $members = [];

        try {
            $pdo = new PDO(
                "pgsql:host={$this->config['host']};dbname={$this->config['database']}",
                $this->config['username'],
                $this->config['password']
            );

            $stmt = $pdo->query("
                SELECT user_id, first_name, last_name, phone, email, specialization
                FROM users
                WHERE user_type = 'medical'
                AND active = true
                AND on_duty = true
            ");

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $members[] = $row;
            }

            // Cache for 5 minutes
            $this->cache->set($cacheKey, $members, 300);

        } catch (Exception $e) {
            $this->logger->error('Error getting medical team members', ['error' => $e->getMessage()]);
        }

        return $members;
    }

    /**
     * Find nearest hospital
     */
    private function findNearestHospital($location) {
        // In a real implementation, this would use geocoding and distance calculation
        // For now, return a default hospital
        return [
            'id' => 'hospital_001',
            'name' => 'City General Hospital',
            'distance' => '5.2 km',
            'estimated_time' => '8 minutes',
            'specialties' => ['emergency', 'trauma', 'cardiology']
        ];
    }

    /**
     * Calculate required resources for mass casualty incident
     */
    private function calculateRequiredResources($incidentData) {
        $casualtyCount = $incidentData['casualty_count'] ?? 1;
        $severity = $incidentData['severity'] ?? 'moderate';

        $resources = [
            'ambulances' => max(1, ceil($casualtyCount / 2)),
            'paramedics' => max(2, $casualtyCount),
            'doctors' => max(1, ceil($casualtyCount / 5)),
            'nurses' => max(2, ceil($casualtyCount / 3)),
            'medical_supplies' => $this->calculateSupplyRequirements($casualtyCount, $severity),
            'equipment' => $this->calculateEquipmentRequirements($casualtyCount, $severity)
        ];

        return $resources;
    }

    /**
     * Calculate medical supply requirements
     */
    private function calculateSupplyRequirements($casualtyCount, $severity) {
        $multipliers = [
            'minor' => 1,
            'moderate' => 2,
            'serious' => 3,
            'critical' => 4
        ];

        $multiplier = $multipliers[$severity] ?? 2;

        return [
            'bandages' => $casualtyCount * 5 * $multiplier,
            'antiseptics' => $casualtyCount * 2 * $multiplier,
            'pain_medication' => $casualtyCount * 1 * $multiplier,
            'iv_fluids' => $casualtyCount * 2 * $multiplier,
            'oxygen_masks' => $casualtyCount * 1 * $multiplier,
            'splints' => max(2, ceil($casualtyCount * 0.5) * $multiplier)
        ];
    }

    /**
     * Calculate medical equipment requirements
     */
    private function calculateEquipmentRequirements($casualtyCount, $severity) {
        $equipment = [];

        if ($casualtyCount >= 1) {
            $equipment[] = 'defibrillator';
            $equipment[] = 'oxygen_tank';
        }

        if ($casualtyCount >= 3) {
            $equipment[] = 'portable_ventilator';
            $equipment[] = 'mobile_xray';
        }

        if ($casualtyCount >= 5) {
            $equipment[] = 'field_hospital_tent';
            $equipment[] = 'mass_casualty_triage_system';
        }

        if ($severity === 'critical') {
            $equipment[] = 'helicopter_evacuation';
            $equipment[] = 'burn_unit_equipment';
        }

        return $equipment;
    }

    /**
     * Make API request to external medical services
     */
    private function makeAPIRequest($url, $method, $data, $apiKey) {
        $ch = curl_init();

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
            'User-Agent: Airport-Medical-Services/1.0'
        ];

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($error) {
            return [
                'success' => false,
                'error' => $error
            ];
        }

        $responseData = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success' => true,
                'data' => $responseData
            ];
        } else {
            return [
                'success' => false,
                'error' => $responseData['message'] ?? 'API request failed',
                'http_code' => $httpCode
            ];
        }
    }

    /**
     * Get configuration for various medical systems
     */
    private function getEMSConfig() {
        return [
            'api_url' => getenv('EMS_API_URL') ?: 'https://api.ems-service.com',
            'api_key' => getenv('EMS_API_KEY') ?: 'default_key'
        ];
    }

    private function getHospitalConfig() {
        return [
            'api_url' => getenv('HOSPITAL_API_URL') ?: 'https://api.hospital-system.com',
            'api_key' => getenv('HOSPITAL_API_KEY') ?: 'default_key'
        ];
    }

    private function getResourcesConfig() {
        return [
            'api_url' => getenv('MEDICAL_RESOURCES_API_URL') ?: 'https://api.medical-resources.com',
            'api_key' => getenv('MEDICAL_RESOURCES_API_KEY') ?: 'default_key'
        ];
    }

    private function getPharmacyConfig() {
        return [
            'api_url' => getenv('PHARMACY_API_URL') ?: 'https://api.pharmacy-system.com',
            'api_key' => getenv('PHARMACY_API_KEY') ?: 'default_key'
        ];
    }

    private function getTelemedicineConfig() {
        return [
            'api_url' => getenv('TELEMEDICINE_API_URL') ?: 'https://api.telemedicine-platform.com',
            'api_key' => getenv('TELEMEDICINE_API_KEY') ?: 'default_key'
        ];
    }

    private function getDeviceMonitoringConfig() {
        return [
            'api_url' => getenv('MEDICAL_DEVICES_API_URL') ?: 'https://api.medical-devices.com',
            'api_key' => getenv('MEDICAL_DEVICES_API_KEY') ?: 'default_key'
        ];
    }

    /**
     * Test integration with all medical services
     */
    public function testIntegrations() {
        $this->logger->info('Testing medical services integrations');

        $testData = [
            'incident_id' => 'test_' . time(),
            'location' => 'Airport Test Location',
            'incident_type' => 'test',
            'casualty_count' => 1,
            'severity' => 'minor'
        ];

        $results = [];

        // Test each system
        $systems = [
            'ems' => $this->requestAmbulance($testData),
            'hospital' => $this->notifyHospital($testData),
            'medical_team' => $this->alertMedicalTeam($testData),
            'supplies' => $this->requestMedicalSupplies(['supplies' => ['bandages' => 5]]),
            'telemedicine' => $this->initiateTelemedicineConsultation(['medical_issue' => 'test']),
            'devices' => $this->monitorMedicalDevices()
        ];

        foreach ($systems as $system => $result) {
            $results[$system] = [
                'status' => $result['success'] ? 'operational' : 'failed',
                'error' => $result['error'] ?? null,
                'timestamp' => date('c')
            ];
        }

        $this->logger->info('Medical services integration test completed', $results);

        return $results;
    }
}

?>
