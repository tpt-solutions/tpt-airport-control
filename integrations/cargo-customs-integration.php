<?php

/**
 * Cargo Customs Clearance Integration
 *
 * Integrates with customs systems for automated clearance processing
 * Handles duty calculations, compliance checks, and border control coordination
 */

require_once __DIR__ . '/../backend/config/database.php';
require_once __DIR__ . '/../backend/src/Logger.php';

class CargoCustomsIntegration {
    private $pdo;
    private $apiKey;
    private $baseUrl;
    private $logger;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->apiKey = getenv('CUSTOMS_API_KEY') ?: '';
        $this->baseUrl = getenv('CUSTOMS_BASE_URL') ?: 'https://api.customs.gov/customs/v1';
        $this->logger = new Logger('cargo_customs_integration');
    }

    /**
     * Submit customs declaration for processing
     */
    public function submitDeclaration($declarationId) {
        try {
            $this->logger->info("Submitting customs declaration", ['declaration_id' => $declarationId]);

            // Get declaration details
            $declaration = $this->getDeclarationDetails($declarationId);
            if (!$declaration) {
                throw new Exception("Declaration not found: $declarationId");
            }

            // Prepare customs data
            $customsData = $this->prepareCustomsData($declaration);

            // Submit to customs API
            $response = $this->callCustomsAPI('POST', '/declarations', $customsData);

            if ($response && isset($response['declaration_number'])) {
                // Update declaration with customs reference
                $this->updateDeclarationStatus($declarationId, 'submitted', $response);
                return $response;
            } else {
                throw new Exception("Failed to submit declaration to customs");
            }

        } catch (Exception $e) {
            $this->logger->error("Customs declaration submission failed: " . $e->getMessage());
            $this->updateDeclarationStatus($declarationId, 'submission_failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Check customs clearance status
     */
    public function checkClearanceStatus($declarationId) {
        try {
            $this->logger->info("Checking customs clearance status", ['declaration_id' => $declarationId]);

            // Get customs reference number
            $customsRef = $this->getCustomsReference($declarationId);
            if (!$customsRef) {
                throw new Exception("No customs reference found for declaration: $declarationId");
            }

            // Query customs API for status
            $response = $this->callCustomsAPI('GET', "/declarations/{$customsRef}/status");

            if ($response && isset($response['status'])) {
                // Update local status
                $this->updateDeclarationStatus($declarationId, $response['status'], $response);
                return $response;
            } else {
                throw new Exception("Failed to get clearance status from customs");
            }

        } catch (Exception $e) {
            $this->logger->error("Customs status check failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Calculate customs duties and taxes
     */
    public function calculateDuties($shipmentId, $destinationCountry = 'US') {
        try {
            $this->logger->info("Calculating customs duties", [
                'shipment_id' => $shipmentId,
                'destination' => $destinationCountry
            ]);

            // Get shipment items
            $items = $this->getShipmentItems($shipmentId);
            if (empty($items)) {
                throw new Exception("No items found for shipment: $shipmentId");
            }

            $totalDuties = 0;
            $calculations = [];

            foreach ($items as $item) {
                $itemDuty = $this->calculateItemDuty($item, $destinationCountry);
                $totalDuties += $itemDuty;
                $calculations[] = [
                    'item_id' => $item['item_id'],
                    'description' => $item['item_description'],
                    'hs_code' => $item['harmonized_code'],
                    'value' => $item['total_value'],
                    'duty_rate' => $itemDuty / $item['total_value'],
                    'duty_amount' => $itemDuty
                ];
            }

            // Calculate additional fees (VAT, processing fees, etc.)
            $additionalFees = $this->calculateAdditionalFees($totalDuties, $destinationCountry);

            return [
                'total_duties' => $totalDuties,
                'additional_fees' => $additionalFees,
                'grand_total' => $totalDuties + $additionalFees,
                'currency' => 'USD',
                'calculations' => $calculations
            ];

        } catch (Exception $e) {
            $this->logger->error("Duty calculation failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Validate compliance requirements
     */
    public function validateCompliance($shipmentId) {
        try {
            $this->logger->info("Validating customs compliance", ['shipment_id' => $shipmentId]);

            $issues = [];

            // Check required documents
            $documents = $this->checkRequiredDocuments($shipmentId);
            if (!empty($documents['missing'])) {
                $issues[] = [
                    'type' => 'missing_documents',
                    'severity' => 'high',
                    'message' => 'Missing required customs documents',
                    'details' => $documents['missing']
                ];
            }

            // Check restricted items
            $restrictions = $this->checkRestrictedItems($shipmentId);
            if (!empty($restrictions)) {
                $issues[] = [
                    'type' => 'restricted_items',
                    'severity' => 'critical',
                    'message' => 'Shipment contains restricted items',
                    'details' => $restrictions
                ];
            }

            // Check value thresholds
            $valueCheck = $this->checkValueThresholds($shipmentId);
            if ($valueCheck['exceeds_threshold']) {
                $issues[] = [
                    'type' => 'value_threshold',
                    'severity' => 'medium',
                    'message' => 'Shipment value exceeds reporting threshold',
                    'details' => $valueCheck
                ];
            }

            return [
                'compliant' => empty($issues),
                'issues' => $issues,
                'recommendations' => $this->generateComplianceRecommendations($issues)
            ];

        } catch (Exception $e) {
            $this->logger->error("Compliance validation failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get customs broker recommendations
     */
    public function getBrokerRecommendations($shipmentId) {
        try {
            $this->logger->info("Getting customs broker recommendations", ['shipment_id' => $shipmentId]);

            // Get shipment details
            $shipment = $this->getShipmentDetails($shipmentId);
            if (!$shipment) {
                throw new Exception("Shipment not found: $shipmentId");
            }

            // Query available brokers
            $brokers = $this->getAvailableBrokers($shipment);

            // Score and rank brokers
            $rankedBrokers = $this->rankBrokers($brokers, $shipment);

            return [
                'recommended_broker' => $rankedBrokers[0] ?? null,
                'alternatives' => array_slice($rankedBrokers, 1, 3),
                'criteria_used' => [
                    'experience_with_origin' => true,
                    'experience_with_destination' => true,
                    'specialized_services' => true,
                    'processing_time' => true,
                    'cost_effectiveness' => true
                ]
            ];

        } catch (Exception $e) {
            $this->logger->error("Broker recommendation failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Process automated clearance
     */
    public function processAutomatedClearance($declarationId) {
        try {
            $this->logger->info("Processing automated customs clearance", ['declaration_id' => $declarationId]);

            // Validate all requirements
            $validation = $this->validateForAutomatedClearance($declarationId);
            if (!$validation['eligible']) {
                return [
                    'success' => false,
                    'reason' => 'not_eligible',
                    'issues' => $validation['issues']
                ];
            }

            // Submit for automated processing
            $response = $this->callCustomsAPI('POST', '/automated-clearance', [
                'declaration_id' => $declarationId,
                'processing_type' => 'automated'
            ]);

            if ($response && isset($response['clearance_id'])) {
                $this->updateDeclarationStatus($declarationId, 'automated_clearance', $response);
                return [
                    'success' => true,
                    'clearance_id' => $response['clearance_id'],
                    'estimated_clearance_time' => $response['estimated_time'] ?? '2-4 hours'
                ];
            } else {
                return [
                    'success' => false,
                    'reason' => 'processing_failed'
                ];
            }

        } catch (Exception $e) {
            $this->logger->error("Automated clearance failed: " . $e->getMessage());
            return [
                'success' => false,
                'reason' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }

    // Helper methods

    private function getDeclarationDetails($declarationId) {
        $stmt = $this->pdo->prepare("
            SELECT cd.*, cs.shipment_number, cs.origin_airport, cs.destination_airport
            FROM customs_declarations cd
            JOIN cargo_shipments cs ON cd.shipment_id = cs.shipment_id
            WHERE cd.declaration_number = ?
        ");
        $stmt->execute([$declarationId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function prepareCustomsData($declaration) {
        return [
            'declaration_number' => $declaration['declaration_number'],
            'shipment_number' => $declaration['shipment_number'],
            'origin' => $declaration['origin_airport'],
            'destination' => $declaration['destination_airport'],
            'declarant' => $declaration['declarant_name'],
            'customs_value' => $declaration['customs_value'],
            'currency' => $declaration['currency'],
            'declaration_type' => $declaration['declaration_type'],
            'items' => $this->getDeclarationItems($declaration['shipment_id'])
        ];
    }

    private function callCustomsAPI($method, $endpoint, $data = null) {
        $url = $this->baseUrl . $endpoint;

        $context = [
            'http' => [
                'method' => $method,
                'header' => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $this->apiKey,
                    'User-Agent: FlightControl-Cargo/1.0'
                ],
                'timeout' => 30
            ]
        ];

        if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $context['http']['content'] = json_encode($data);
        }

        $response = file_get_contents($url, false, stream_context_create($context));

        if ($response === false) {
            throw new Exception("Customs API call failed");
        }

        return json_decode($response, true);
    }

    private function updateDeclarationStatus($declarationId, $status, $responseData = []) {
        $stmt = $this->pdo->prepare("
            UPDATE customs_declarations
            SET declaration_status = ?, customs_response = ?, updated_at = CURRENT_TIMESTAMP
            WHERE declaration_number = ?
        ");

        $stmt->execute([
            $status,
            json_encode($responseData),
            $declarationId
        ]);
    }

    private function getCustomsReference($declarationId) {
        $stmt = $this->pdo->prepare("
            SELECT customs_response
            FROM customs_declarations
            WHERE declaration_number = ?
        ");
        $stmt->execute([$declarationId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result && $result['customs_response']) {
            $response = json_decode($result['customs_response'], true);
            return $response['declaration_number'] ?? null;
        }

        return null;
    }

    private function getShipmentItems($shipmentId) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM cargo_items
            WHERE shipment_id = ?
            ORDER BY item_id
        ");
        $stmt->execute([$shipmentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function calculateItemDuty($item, $destinationCountry) {
        // Simplified duty calculation
        // In production, this would use HS code lookup and current tariff rates
        $baseRate = 0.05; // 5% base rate
        $value = $item['total_value'];

        // Adjust rate based on HS code category
        if (isset($item['harmonized_code'])) {
            $hsCode = substr($item['harmonized_code'], 0, 4);
            if (in_array($hsCode, ['8471', '8517'])) { // Electronics
                $baseRate = 0.02; // 2%
            } elseif (in_array($hsCode, ['6201', '6202'])) { // Apparel
                $baseRate = 0.15; // 15%
            }
        }

        return $value * $baseRate;
    }

    private function calculateAdditionalFees($duties, $destinationCountry) {
        // Calculate VAT, processing fees, etc.
        $vatRate = 0.10; // 10% VAT
        $processingFee = 25.00; // Fixed processing fee

        return ($duties * $vatRate) + $processingFee;
    }

    private function checkRequiredDocuments($shipmentId) {
        // Check for required customs documents
        $required = ['commercial_invoice', 'packing_list', 'certificate_of_origin'];
        $present = [];

        // Query documents table (assuming it exists)
        $stmt = $this->pdo->prepare("
            SELECT document_type FROM shipment_documents
            WHERE shipment_id = ?
        ");
        $stmt->execute([$shipmentId]);
        $documents = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $missing = array_diff($required, $documents);

        return [
            'present' => $documents,
            'missing' => array_values($missing)
        ];
    }

    private function checkRestrictedItems($shipmentId) {
        // Check for restricted/prohibited items
        $stmt = $this->pdo->prepare("
            SELECT ci.*, hm.hazard_class, hm.description as hazard_description
            FROM cargo_items ci
            LEFT JOIN hazardous_materials hm ON ci.harmonized_code = hm.un_number
            WHERE ci.shipment_id = ?
            AND (hm.hazard_class IS NOT NULL OR ci.special_handling IS NOT NULL)
        ");
        $stmt->execute([$shipmentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function checkValueThresholds($shipmentId) {
        $stmt = $this->pdo->prepare("
            SELECT SUM(total_value) as total_value
            FROM cargo_items
            WHERE shipment_id = ?
        ");
        $stmt->execute([$shipmentId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $threshold = 2500; // USD threshold for detailed reporting
        $totalValue = $result['total_value'] ?? 0;

        return [
            'total_value' => $totalValue,
            'threshold' => $threshold,
            'exceeds_threshold' => $totalValue > $threshold
        ];
    }

    private function generateComplianceRecommendations($issues) {
        $recommendations = [];

        foreach ($issues as $issue) {
            switch ($issue['type']) {
                case 'missing_documents':
                    $recommendations[] = "Upload missing documents: " . implode(', ', $issue['details']);
                    break;
                case 'restricted_items':
                    $recommendations[] = "Contact customs broker for restricted item handling";
                    break;
                case 'value_threshold':
                    $recommendations[] = "Prepare detailed value declaration for high-value shipment";
                    break;
            }
        }

        return $recommendations;
    }

    private function getShipmentDetails($shipmentId) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM cargo_shipments
            WHERE shipment_id = ?
        ");
        $stmt->execute([$shipmentId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function getAvailableBrokers($shipment) {
        // Query customs brokers database
        $stmt = $this->pdo->prepare("
            SELECT * FROM customs_brokers
            WHERE active = true
            AND (? = ANY(supported_countries) OR ? = ANY(supported_countries))
        ");
        $stmt->execute([$shipment['origin_airport'], $shipment['destination_airport']]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function rankBrokers($brokers, $shipment) {
        // Simple ranking algorithm
        foreach ($brokers as &$broker) {
            $score = 0;

            // Experience with origin/destination
            if (in_array($shipment['origin_airport'], $broker['supported_countries'])) {
                $score += 20;
            }
            if (in_array($shipment['destination_airport'], $broker['supported_countries'])) {
                $score += 20;
            }

            // Performance rating
            $score += ($broker['performance_rating'] ?? 5) * 10;

            // Cost factor (lower cost = higher score)
            $costScore = max(0, 50 - ($broker['average_cost'] ?? 100));
            $score += $costScore;

            $broker['ranking_score'] = $score;
        }

        // Sort by score descending
        usort($brokers, function($a, $b) {
            return $b['ranking_score'] <=> $a['ranking_score'];
        });

        return $brokers;
    }

    private function validateForAutomatedClearance($declarationId) {
        $issues = [];

        // Check if all required documents are present
        $documents = $this->checkRequiredDocuments($declarationId);
        if (!empty($documents['missing'])) {
            $issues[] = 'Missing required documents';
        }

        // Check if shipment value is below automated threshold
        $valueCheck = $this->checkValueThresholds($declarationId);
        if ($valueCheck['exceeds_threshold']) {
            $issues[] = 'Shipment value exceeds automated clearance threshold';
        }

        // Check for restricted items
        $restrictions = $this->checkRestrictedItems($declarationId);
        if (!empty($restrictions)) {
            $issues[] = 'Contains restricted items requiring manual review';
        }

        return [
            'eligible' => empty($issues),
            'issues' => $issues
        ];
    }

    private function getDeclarationItems($shipmentId) {
        $stmt = $this->pdo->prepare("
            SELECT
                item_description,
                harmonized_code,
                quantity,
                unit_value,
                total_value,
                weight_kg,
                special_handling
            FROM cargo_items
            WHERE shipment_id = ?
        ");
        $stmt->execute([$shipmentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Database tables for customs integration
$customsTablesSQL = "
-- Customs brokers table
CREATE TABLE IF NOT EXISTS customs_brokers (
    broker_id SERIAL PRIMARY KEY,
    company_name VARCHAR(255) NOT NULL,
    license_number VARCHAR(100) UNIQUE,
    contact_email VARCHAR(255),
    contact_phone VARCHAR(50),
    supported_countries TEXT[],
    specializations TEXT[],
    performance_rating DECIMAL(3,2) DEFAULT 5.00,
    average_cost DECIMAL(8,2),
    active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Shipment documents table
CREATE TABLE IF NOT EXISTS shipment_documents (
    document_id SERIAL PRIMARY KEY,
    shipment_id VARCHAR(20) NOT NULL,
    document_type VARCHAR(100) NOT NULL,
    document_number VARCHAR(100),
    file_path VARCHAR(500),
    uploaded_by INTEGER,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (shipment_id) REFERENCES cargo_shipments(shipment_id)
);

-- Customs clearance history
CREATE TABLE IF NOT EXISTS customs_clearance_history (
    clearance_id SERIAL PRIMARY KEY,
    declaration_number VARCHAR(50) NOT NULL,
    clearance_status VARCHAR(50) NOT NULL,
    cleared_at TIMESTAMP,
    cleared_by VARCHAR(100),
    processing_time_minutes INTEGER,
    duty_amount DECIMAL(12,2),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
";

// Usage example:
/*
$customs = new CargoCustomsIntegration($pdo);

// Submit declaration
$result = $customs->submitDeclaration('DEC-20231201-0001');

// Check status
$status = $customs->checkClearanceStatus('DEC-20231201-0001');

// Calculate duties
$duties = $customs->calculateDuties('CGO-20231201-0001', 'US');

// Validate compliance
$compliance = $customs->validateCompliance('CGO-20231201-0001');

// Get broker recommendations
$brokers = $customs->getBrokerRecommendations('CGO-20231201-0001');

// Process automated clearance
$clearance = $customs->processAutomatedClearance('DEC-20231201-0001');
*/
?>
