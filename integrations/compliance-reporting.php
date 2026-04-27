<?php
/**
 * Compliance Reporting and Audit System
 *
 * Handles regulatory compliance reporting, audit trails, and data export functionality
 */

class ComplianceReporting
{
    private $db;
    private $logger;

    public function __construct($database, $logger)
    {
        $this->db = $database;
        $this->logger = $logger;
    }

    /**
     * Generate comprehensive audit log report
     */
    public function generateAuditReport($startDate, $endDate, $filters = [])
    {
        try {
            $query = "
                SELECT
                    al.*,
                    u.username,
                    u.first_name,
                    u.last_name,
                    r.name as role_name
                FROM audit_logs al
                LEFT JOIN users u ON al.user_id = u.id
                LEFT JOIN roles r ON u.role_id = r.id
                WHERE al.created_at BETWEEN ? AND ?
            ";

            $params = [$startDate, $endDate];

            // Apply filters
            if (!empty($filters['user_id'])) {
                $query .= " AND al.user_id = ?";
                $params[] = $filters['user_id'];
            }

            if (!empty($filters['action'])) {
                $query .= " AND al.action = ?";
                $params[] = $filters['action'];
            }

            if (!empty($filters['resource_type'])) {
                $query .= " AND al.resource_type = ?";
                $params[] = $filters['resource_type'];
            }

            if (!empty($filters['ip_address'])) {
                $query .= " AND al.ip_address = ?";
                $params[] = $filters['ip_address'];
            }

            $query .= " ORDER BY al.created_at DESC";

            $stmt = $this->db->prepare($query);
            $stmt->execute($params);

            $auditLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'report_type' => 'audit_log',
                'period' => ['start' => $startDate, 'end' => $endDate],
                'filters' => $filters,
                'total_entries' => count($auditLogs),
                'entries' => $auditLogs,
                'generated_at' => date('Y-m-d H:i:s')
            ];

        } catch (Exception $e) {
            $this->logger->error("Failed to generate audit report", ['error' => $e->getMessage()]);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Generate regulatory compliance report
     */
    public function generateComplianceReport($reportType, $period = null)
    {
        switch ($reportType) {
            case 'faa_part_139':
                return $this->generateFAAPart139Report($period);
            case 'icao_annex_14':
                return $this->generateICAOAnnex14Report($period);
            case 'gdpr_compliance':
                return $this->generateGDPRComplianceReport($period);
            case 'security_incidents':
                return $this->generateSecurityIncidentsReport($period);
            case 'data_retention':
                return $this->generateDataRetentionReport($period);
            default:
                return ['error' => 'Unknown report type'];
        }
    }

    /**
     * Generate FAA Part 139 Airport Certification Report
     */
    private function generateFAAPart139Report($period)
    {
        $startDate = $period['start'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $period['end'] ?? date('Y-m-d');

        // Runway inspections and maintenance
        $runwayInspections = $this->getRunwayInspections($startDate, $endDate);

        // Safety equipment checks
        $safetyEquipment = $this->getSafetyEquipmentStatus();

        // Emergency response drills
        $emergencyDrills = $this->getEmergencyDrills($startDate, $endDate);

        // Wildlife hazard management
        $wildlifeHazards = $this->getWildlifeHazards($startDate, $endDate);

        return [
            'report_type' => 'faa_part_139',
            'period' => ['start' => $startDate, 'end' => $endDate],
            'sections' => [
                'runway_maintenance' => $runwayInspections,
                'safety_equipment' => $safetyEquipment,
                'emergency_response' => $emergencyDrills,
                'wildlife_hazard_management' => $wildlifeHazards
            ],
            'compliance_status' => $this->assessFAACompliance([
                'runwayInspections' => $runwayInspections,
                'safetyEquipment' => $safetyEquipment,
                'emergencyDrills' => $emergencyDrills,
                'wildlifeHazards' => $wildlifeHazards
            ]),
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Generate ICAO Annex 14 Aerodrome Standards Report
     */
    private function generateICAOAnnex14Report($period)
    {
        $startDate = $period['start'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $period['end'] ?? date('Y-m-d');

        // Pavement strength and maintenance
        $pavementStatus = $this->getPavementStatus();

        // Lighting systems
        $lightingSystems = $this->getLightingSystemsStatus();

        // Navigation aids
        $navigationAids = $this->getNavigationAidsStatus();

        // Obstacle limitation surfaces
        $obstacleAnalysis = $this->getObstacleAnalysis();

        return [
            'report_type' => 'icao_annex_14',
            'period' => ['start' => $startDate, 'end' => $endDate],
            'sections' => [
                'pavement_maintenance' => $pavementStatus,
                'lighting_systems' => $lightingSystems,
                'navigation_aids' => $navigationAids,
                'obstacle_limitation' => $obstacleAnalysis
            ],
            'compliance_status' => $this->assessICAOCompliance([
                'pavementStatus' => $pavementStatus,
                'lightingSystems' => $lightingSystems,
                'navigationAids' => $navigationAids,
                'obstacleAnalysis' => $obstacleAnalysis
            ]),
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Generate GDPR Compliance Report
     */
    private function generateGDPRComplianceReport($period)
    {
        $startDate = $period['start'] ?? date('Y-m-d', strtotime('-90 days'));
        $endDate = $period['end'] ?? date('Y-m-d');

        // Data processing activities
        $dataProcessing = $this->getDataProcessingActivities($startDate, $endDate);

        // Consent management
        $consentRecords = $this->getConsentRecords($startDate, $endDate);

        // Data subject requests
        $subjectRequests = $this->getSubjectRequests($startDate, $endDate);

        // Data breaches
        $dataBreaches = $this->getDataBreaches($startDate, $endDate);

        return [
            'report_type' => 'gdpr_compliance',
            'period' => ['start' => $startDate, 'end' => $endDate],
            'sections' => [
                'data_processing' => $dataProcessing,
                'consent_management' => $consentRecords,
                'subject_requests' => $subjectRequests,
                'data_breaches' => $dataBreaches
            ],
            'gdpr_assessment' => $this->assessGDPRCompliance([
                'dataProcessing' => $dataProcessing,
                'consentRecords' => $consentRecords,
                'subjectRequests' => $subjectRequests,
                'dataBreaches' => $dataBreaches
            ]),
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Generate Security Incidents Report
     */
    private function generateSecurityIncidentsReport($period)
    {
        $startDate = $period['start'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $period['end'] ?? date('Y-m-d');

        // Security incidents
        $incidents = $this->getSecurityIncidents($startDate, $endDate);

        // Access violations
        $accessViolations = $this->getAccessViolations($startDate, $endDate);

        // System vulnerabilities
        $vulnerabilities = $this->getSystemVulnerabilities($startDate, $endDate);

        // Incident response actions
        $responseActions = $this->getIncidentResponseActions($startDate, $endDate);

        return [
            'report_type' => 'security_incidents',
            'period' => ['start' => $startDate, 'end' => $endDate],
            'sections' => [
                'security_incidents' => $incidents,
                'access_violations' => $accessViolations,
                'system_vulnerabilities' => $vulnerabilities,
                'incident_response' => $responseActions
            ],
            'security_assessment' => $this->assessSecurityPosture([
                'incidents' => $incidents,
                'accessViolations' => $accessViolations,
                'vulnerabilities' => $vulnerabilities,
                'responseActions' => $responseActions
            ]),
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Generate Data Retention Report
     */
    private function generateDataRetentionReport($period)
    {
        $startDate = $period['start'] ?? date('Y-m-d', strtotime('-365 days'));
        $endDate = $period['end'] ?? date('Y-m-d');

        // Data retention policies
        $retentionPolicies = $this->getRetentionPolicies();

        // Data deletion activities
        $deletionActivities = $this->getDeletionActivities($startDate, $endDate);

        // Data archival status
        $archivalStatus = $this->getArchivalStatus();

        // Storage utilization
        $storageUtilization = $this->getStorageUtilization();

        return [
            'report_type' => 'data_retention',
            'period' => ['start' => $startDate, 'end' => $endDate],
            'sections' => [
                'retention_policies' => $retentionPolicies,
                'deletion_activities' => $deletionActivities,
                'archival_status' => $archivalStatus,
                'storage_utilization' => $storageUtilization
            ],
            'retention_compliance' => $this->assessRetentionCompliance([
                'retentionPolicies' => $retentionPolicies,
                'deletionActivities' => $deletionActivities,
                'archivalStatus' => $archivalStatus,
                'storageUtilization' => $storageUtilization
            ]),
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Export report to various formats
     */
    public function exportReport($reportData, $format = 'pdf', $filename = null)
    {
        if (!$filename) {
            $filename = 'compliance_report_' . date('Y-m-d_H-i-s');
        }

        switch ($format) {
            case 'pdf':
                return $this->exportToPDF($reportData, $filename);
            case 'csv':
                return $this->exportToCSV($reportData, $filename);
            case 'json':
                return $this->exportToJSON($reportData, $filename);
            case 'xml':
                return $this->exportToXML($reportData, $filename);
            default:
                return ['error' => 'Unsupported export format'];
        }
    }

    /**
     * Export to PDF format
     */
    private function exportToPDF($reportData, $filename)
    {
        // In a real implementation, this would use a PDF library like TCPDF or FPDF
        // For now, we'll create a simple HTML-to-PDF structure

        $html = $this->generatePDFHTML($reportData);

        return [
            'format' => 'pdf',
            'filename' => $filename . '.pdf',
            'content_type' => 'application/pdf',
            'data' => $html, // In production, this would be actual PDF binary data
            'size' => strlen($html)
        ];
    }

    /**
     * Export to CSV format
     */
    private function exportToCSV($reportData, $filename)
    {
        $csvData = [];

        // Add header
        $csvData[] = ['Report Type', 'Period Start', 'Period End', 'Generated At'];
        $csvData[] = [
            $reportData['report_type'],
            $reportData['period']['start'] ?? '',
            $reportData['period']['end'] ?? '',
            $reportData['generated_at']
        ];

        // Add section data
        if (isset($reportData['sections'])) {
            foreach ($reportData['sections'] as $sectionName => $sectionData) {
                $csvData[] = []; // Empty row for separation
                $csvData[] = [$sectionName];

                if (is_array($sectionData) && !empty($sectionData)) {
                    // Add headers from first item
                    if (isset($sectionData[0]) && is_array($sectionData[0])) {
                        $csvData[] = array_keys($sectionData[0]);
                        foreach ($sectionData as $item) {
                            $csvData[] = array_values($item);
                        }
                    }
                }
            }
        }

        $csvContent = '';
        foreach ($csvData as $row) {
            $csvContent .= '"' . implode('","', $row) . '"' . "\n";
        }

        return [
            'format' => 'csv',
            'filename' => $filename . '.csv',
            'content_type' => 'text/csv',
            'data' => $csvContent,
            'size' => strlen($csvContent)
        ];
    }

    /**
     * Export to JSON format
     */
    private function exportToJSON($reportData, $filename)
    {
        $jsonContent = json_encode($reportData, JSON_PRETTY_PRINT);

        return [
            'format' => 'json',
            'filename' => $filename . '.json',
            'content_type' => 'application/json',
            'data' => $jsonContent,
            'size' => strlen($jsonContent)
        ];
    }

    /**
     * Export to XML format
     */
    private function exportToXML($reportData, $filename)
    {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><report></report>');

        $this->arrayToXML($reportData, $xml);

        $xmlContent = $xml->asXML();

        return [
            'format' => 'xml',
            'filename' => $filename . '.xml',
            'content_type' => 'application/xml',
            'data' => $xmlContent,
            'size' => strlen($xmlContent)
        ];
    }

    /**
     * Convert array to XML
     */
    private function arrayToXML($data, &$xml)
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if (is_numeric($key)) {
                    $key = 'item' . $key;
                }
                $subnode = $xml->addChild($key);
                $this->arrayToXML($value, $subnode);
            } else {
                $xml->addChild($key, htmlspecialchars($value));
            }
        }
    }

    /**
     * Generate PDF HTML content
     */
    private function generatePDFHTML($reportData)
    {
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>' . ucfirst($reportData['report_type']) . ' Report</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
                .section { margin-bottom: 20px; }
                .section h3 { color: #333; border-bottom: 1px solid #ccc; padding-bottom: 5px; }
                table { width: 100%; border-collapse: collapse; margin-top: 10px; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f5f5f5; }
                .footer { margin-top: 30px; text-align: center; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>' . ucfirst(str_replace('_', ' ', $reportData['report_type'])) . ' Report</h1>
                <p>Period: ' . ($reportData['period']['start'] ?? 'N/A') . ' to ' . ($reportData['period']['end'] ?? 'N/A') . '</p>
                <p>Generated: ' . $reportData['generated_at'] . '</p>
            </div>
        ';

        if (isset($reportData['sections'])) {
            foreach ($reportData['sections'] as $sectionName => $sectionData) {
                $html .= '
                <div class="section">
                    <h3>' . ucfirst(str_replace('_', ' ', $sectionName)) . '</h3>
                ';

                if (is_array($sectionData) && !empty($sectionData)) {
                    $html .= '<table>';
                    $headersAdded = false;

                    foreach ($sectionData as $item) {
                        if (is_array($item)) {
                            if (!$headersAdded) {
                                $html .= '<tr>';
                                foreach (array_keys($item) as $header) {
                                    $html .= '<th>' . htmlspecialchars($header) . '</th>';
                                }
                                $html .= '</tr>';
                                $headersAdded = true;
                            }

                            $html .= '<tr>';
                            foreach ($item as $value) {
                                $html .= '<td>' . htmlspecialchars($value) . '</td>';
                            }
                            $html .= '</tr>';
                        }
                    }

                    $html .= '</table>';
                }

                $html .= '</div>';
            }
        }

        $html .= '
            <div class="footer">
                <p>This report was generated by the Flight Control System Compliance Module</p>
                <p>Confidential - For authorized personnel only</p>
            </div>
        </body>
        </html>
        ';

        return $html;
    }

    /**
     * Log audit event
     */
    public function logAuditEvent($userId, $action, $resourceType, $resourceId, $details = null, $ipAddress = null)
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO audit_logs (
                    user_id, action, resource_type, resource_id,
                    details, ip_address, user_agent, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
            ");

            $stmt->execute([
                $userId,
                $action,
                $resourceType,
                $resourceId,
                json_encode($details),
                $ipAddress ?? $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);

            return true;

        } catch (Exception $e) {
            $this->logger->error("Failed to log audit event", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Get audit logs for user
     */
    public function getUserAuditLogs($userId, $limit = 100)
    {
        $stmt = $this->db->prepare("
            SELECT * FROM audit_logs
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT ?
        ");

        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Helper methods for report generation
    private function getRunwayInspections($startDate, $endDate) { return []; }
    private function getSafetyEquipmentStatus() { return []; }
    private function getEmergencyDrills($startDate, $endDate) { return []; }
    private function getWildlifeHazards($startDate, $endDate) { return []; }
    private function assessFAACompliance($data) { return 'compliant'; }

    private function getPavementStatus() { return []; }
    private function getLightingSystemsStatus() { return []; }
    private function getNavigationAidsStatus() { return []; }
    private function getObstacleAnalysis() { return []; }
    private function assessICAOCompliance($data) { return 'compliant'; }

    private function getDataProcessingActivities($startDate, $endDate) { return []; }
    private function getConsentRecords($startDate, $endDate) { return []; }
    private function getSubjectRequests($startDate, $endDate) { return []; }
    private function getDataBreaches($startDate, $endDate) { return []; }
    private function assessGDPRCompliance($data) { return ['status' => 'compliant', 'issues' => []]; }

    private function getSecurityIncidents($startDate, $endDate) { return []; }
    private function getAccessViolations($startDate, $endDate) { return []; }
    private function getSystemVulnerabilities($startDate, $endDate) { return []; }
    private function getIncidentResponseActions($startDate, $endDate) { return []; }
    private function assessSecurityPosture($data) { return ['status' => 'secure', 'risk_level' => 'low']; }

    private function getRetentionPolicies() { return []; }
    private function getDeletionActivities($startDate, $endDate) { return []; }
    private function getArchivalStatus() { return []; }
    private function getStorageUtilization() { return []; }
    private function assessRetentionCompliance($data) { return ['status' => 'compliant', 'utilization' => 'optimal']; }
}
