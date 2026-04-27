<?php

/**
 * Emergency Notification Integration
 *
 * Integrates with external emergency notification systems including:
 * - Mass notification platforms (Everbridge, Blackboard Connect)
 * - SMS gateways for emergency alerts
 * - Email systems for emergency communications
 * - Public address systems integration
 * - Mobile alert systems (WEA, EU-ALERT)
 */

class EmergencyNotificationIntegration {

    private $config;
    private $logger;
    private $cache;

    public function __construct() {
        $this->config = require '../backend/config/database.php';
        $this->logger = new Logger();
        $this->cache = new Cache();
    }

    /**
     * Send emergency notification to all airport personnel
     */
    public function sendEmergencyAlert($alertData) {
        $this->logger->info('Sending emergency alert to all personnel', $alertData);

        $results = [];

        // Send via multiple channels simultaneously
        $channels = [
            'internal_paging' => $this->sendInternalPaging($alertData),
            'sms_gateway' => $this->sendSMSAlert($alertData),
            'email_system' => $this->sendEmailAlert($alertData),
            'mobile_alert' => $this->sendMobileAlert($alertData),
            'public_address' => $this->sendPublicAddressAlert($alertData)
        ];

        foreach ($channels as $channel => $result) {
            $results[$channel] = $result;
            if ($result['success']) {
                $this->logger->info("Emergency alert sent via $channel", [
                    'recipients' => $result['recipient_count'],
                    'message_id' => $result['message_id']
                ]);
            } else {
                $this->logger->error("Failed to send emergency alert via $channel", [
                    'error' => $result['error']
                ]);
            }
        }

        return $results;
    }

    /**
     * Send targeted emergency notification to specific groups
     */
    public function sendTargetedAlert($alertData, $targetGroups) {
        $this->logger->info('Sending targeted emergency alert', [
            'groups' => $targetGroups,
            'alert_type' => $alertData['alert_type']
        ]);

        $results = [];

        foreach ($targetGroups as $group) {
            $groupRecipients = $this->getGroupRecipients($group);

            if (empty($groupRecipients)) {
                $this->logger->warning("No recipients found for group: $group");
                continue;
            }

            $results[$group] = $this->sendGroupAlert($alertData, $groupRecipients, $group);
        }

        return $results;
    }

    /**
     * Send alert via internal paging system
     */
    private function sendInternalPaging($alertData) {
        try {
            // Integration with airport's internal paging system
            $pagingConfig = $this->getPagingSystemConfig();

            $payload = [
                'message' => $alertData['message'],
                'priority' => $alertData['priority'],
                'zones' => $alertData['zones'] ?? ['all'],
                'repeat_count' => $alertData['repeat_count'] ?? 3,
                'timestamp' => date('c')
            ];

            $response = $this->makeAPIRequest(
                $pagingConfig['api_url'] . '/alert',
                'POST',
                $payload,
                $pagingConfig['api_key']
            );

            if ($response['success']) {
                return [
                    'success' => true,
                    'recipient_count' => $response['data']['zones_covered'] ?? 0,
                    'message_id' => $response['data']['alert_id'] ?? null,
                    'channel' => 'internal_paging'
                ];
            }

            return [
                'success' => false,
                'error' => $response['error'] ?? 'Unknown paging system error'
            ];

        } catch (Exception $e) {
            $this->logger->error('Internal paging system error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => 'Internal paging system unavailable'
            ];
        }
    }

    /**
     * Send SMS emergency alert
     */
    private function sendSMSAlert($alertData) {
        try {
            $smsConfig = $this->getSMSConfig();

            // Get all personnel phone numbers
            $phoneNumbers = $this->getAllPersonnelPhones();

            if (empty($phoneNumbers)) {
                return [
                    'success' => false,
                    'error' => 'No phone numbers available'
                ];
            }

            $payload = [
                'message' => $this->formatSMSMessage($alertData),
                'recipients' => $phoneNumbers,
                'priority' => 'high',
                'sender_id' => 'AIRPORT_EMERGENCY',
                'timestamp' => date('c')
            ];

            $response = $this->makeAPIRequest(
                $smsConfig['api_url'] . '/send-emergency',
                'POST',
                $payload,
                $smsConfig['api_key']
            );

            if ($response['success']) {
                return [
                    'success' => true,
                    'recipient_count' => count($phoneNumbers),
                    'message_id' => $response['data']['batch_id'] ?? null,
                    'channel' => 'sms'
                ];
            }

            return [
                'success' => false,
                'error' => $response['error'] ?? 'SMS gateway error'
            ];

        } catch (Exception $e) {
            $this->logger->error('SMS alert error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => 'SMS service unavailable'
            ];
        }
    }

    /**
     * Send email emergency alert
     */
    private function sendEmailAlert($alertData) {
        try {
            $emailConfig = $this->getEmailConfig();

            // Get all personnel email addresses
            $emailAddresses = $this->getAllPersonnelEmails();

            if (empty($emailAddresses)) {
                return [
                    'success' => false,
                    'error' => 'No email addresses available'
                ];
            }

            $payload = [
                'subject' => 'AIRPORT EMERGENCY ALERT - ' . strtoupper($alertData['alert_type']),
                'message' => $this->formatEmailMessage($alertData),
                'recipients' => $emailAddresses,
                'priority' => 'high',
                'sender' => 'emergency@airport.com',
                'timestamp' => date('c')
            ];

            $response = $this->makeAPIRequest(
                $emailConfig['api_url'] . '/send-emergency',
                'POST',
                $payload,
                $emailConfig['api_key']
            );

            if ($response['success']) {
                return [
                    'success' => true,
                    'recipient_count' => count($emailAddresses),
                    'message_id' => $response['data']['campaign_id'] ?? null,
                    'channel' => 'email'
                ];
            }

            return [
                'success' => false,
                'error' => $response['error'] ?? 'Email system error'
            ];

        } catch (Exception $e) {
            $this->logger->error('Email alert error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => 'Email service unavailable'
            ];
        }
    }

    /**
     * Send mobile alert (WEA, EU-ALERT, etc.)
     */
    private function sendMobileAlert($alertData) {
        try {
            $mobileConfig = $this->getMobileAlertConfig();

            $payload = [
                'alert_type' => 'emergency',
                'message' => $this->formatMobileMessage($alertData),
                'area' => $alertData['area'] ?? 'airport',
                'severity' => $alertData['priority'],
                'timestamp' => date('c'),
                'expires_at' => date('c', strtotime('+1 hour'))
            ];

            $response = $this->makeAPIRequest(
                $mobileConfig['api_url'] . '/send-alert',
                'POST',
                $payload,
                $mobileConfig['api_key']
            );

            if ($response['success']) {
                return [
                    'success' => true,
                    'recipient_count' => $response['data']['estimated_reach'] ?? 0,
                    'message_id' => $response['data']['alert_id'] ?? null,
                    'channel' => 'mobile_alert'
                ];
            }

            return [
                'success' => false,
                'error' => $response['error'] ?? 'Mobile alert system error'
            ];

        } catch (Exception $e) {
            $this->logger->error('Mobile alert error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => 'Mobile alert service unavailable'
            ];
        }
    }

    /**
     * Send alert via public address system
     */
    private function sendPublicAddressAlert($alertData) {
        try {
            $paConfig = $this->getPublicAddressConfig();

            $payload = [
                'message' => $alertData['message'],
                'voice' => $alertData['voice'] ?? 'female',
                'language' => $alertData['language'] ?? 'en',
                'zones' => $alertData['zones'] ?? ['all'],
                'repeat_count' => $alertData['repeat_count'] ?? 3,
                'priority' => 'emergency',
                'timestamp' => date('c')
            ];

            $response = $this->makeAPIRequest(
                $paConfig['api_url'] . '/announce',
                'POST',
                $payload,
                $paConfig['api_key']
            );

            if ($response['success']) {
                return [
                    'success' => true,
                    'recipient_count' => $response['data']['zones_covered'] ?? 0,
                    'message_id' => $response['data']['announcement_id'] ?? null,
                    'channel' => 'public_address'
                ];
            }

            return [
                'success' => false,
                'error' => $response['error'] ?? 'Public address system error'
            ];

        } catch (Exception $e) {
            $this->logger->error('Public address system error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => 'Public address system unavailable'
            ];
        }
    }

    /**
     * Send alert to specific group
     */
    private function sendGroupAlert($alertData, $recipients, $groupName) {
        $this->logger->info("Sending alert to group: $groupName", [
            'recipient_count' => count($recipients)
        ]);

        // Use SMS for targeted alerts (most reliable for immediate response)
        $smsConfig = $this->getSMSConfig();

        $payload = [
            'message' => $this->formatSMSMessage($alertData),
            'recipients' => $recipients,
            'priority' => 'high',
            'sender_id' => 'AIRPORT_EMERGENCY',
            'group' => $groupName,
            'timestamp' => date('c')
        ];

        $response = $this->makeAPIRequest(
            $smsConfig['api_url'] . '/send-group-alert',
            'POST',
            $payload,
            $smsConfig['api_key']
        );

        if ($response['success']) {
            return [
                'success' => true,
                'recipient_count' => count($recipients),
                'message_id' => $response['data']['batch_id'] ?? null,
                'group' => $groupName
            ];
        }

        return [
            'success' => false,
            'error' => $response['error'] ?? 'Group alert failed',
            'group' => $groupName
        ];
    }

    /**
     * Get recipients for a specific group
     */
    private function getGroupRecipients($groupName) {
        // Cache group recipients for 5 minutes
        $cacheKey = "group_recipients_$groupName";
        $cached = $this->cache->get($cacheKey);

        if ($cached) {
            return $cached;
        }

        $recipients = [];

        try {
            // Query database for group members
            $pdo = new PDO(
                "pgsql:host={$this->config['host']};dbname={$this->config['database']}",
                $this->config['username'],
                $this->config['password']
            );

            $stmt = $pdo->prepare("
                SELECT u.phone, u.email
                FROM users u
                JOIN user_groups ug ON u.user_id = ug.user_id
                JOIN groups g ON ug.group_id = g.group_id
                WHERE g.group_name = ? AND u.active = true
            ");
            $stmt->execute([$groupName]);

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (!empty($row['phone'])) {
                    $recipients[] = $row['phone'];
                }
            }

            // Cache for 5 minutes
            $this->cache->set($cacheKey, $recipients, 300);

        } catch (Exception $e) {
            $this->logger->error('Error getting group recipients', [
                'group' => $groupName,
                'error' => $e->getMessage()
            ]);
        }

        return $recipients;
    }

    /**
     * Get all personnel phone numbers
     */
    private function getAllPersonnelPhones() {
        $cacheKey = 'all_personnel_phones';
        $cached = $this->cache->get($cacheKey);

        if ($cached) {
            return $cached;
        }

        $phones = [];

        try {
            $pdo = new PDO(
                "pgsql:host={$this->config['host']};dbname={$this->config['database']}",
                $this->config['username'],
                $this->config['password']
            );

            $stmt = $pdo->query("
                SELECT phone FROM users
                WHERE phone IS NOT NULL
                AND active = true
                AND user_type IN ('staff', 'security', 'medical', 'fire', 'police')
            ");

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $phones[] = $row['phone'];
            }

            // Cache for 10 minutes
            $this->cache->set($cacheKey, $phones, 600);

        } catch (Exception $e) {
            $this->logger->error('Error getting personnel phones', ['error' => $e->getMessage()]);
        }

        return $phones;
    }

    /**
     * Get all personnel email addresses
     */
    private function getAllPersonnelEmails() {
        $cacheKey = 'all_personnel_emails';
        $cached = $this->cache->get($cacheKey);

        if ($cached) {
            return $cached;
        }

        $emails = [];

        try {
            $pdo = new PDO(
                "pgsql:host={$this->config['host']};dbname={$this->config['database']}",
                $this->config['username'],
                $this->config['password']
            );

            $stmt = $pdo->query("
                SELECT email FROM users
                WHERE email IS NOT NULL
                AND active = true
                AND user_type IN ('staff', 'security', 'medical', 'fire', 'police')
            ");

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $emails[] = $row['email'];
            }

            // Cache for 10 minutes
            $this->cache->set($cacheKey, $emails, 600);

        } catch (Exception $e) {
            $this->logger->error('Error getting personnel emails', ['error' => $e->getMessage()]);
        }

        return $emails;
    }

    /**
     * Format message for SMS (limited characters)
     */
    private function formatSMSMessage($alertData) {
        $message = "EMERGENCY ALERT: {$alertData['message']}";

        // Add location if specified
        if (isset($alertData['location'])) {
            $message .= " Location: {$alertData['location']}";
        }

        // Add instructions if provided
        if (isset($alertData['instructions'])) {
            $message .= " Instructions: {$alertData['instructions']}";
        }

        // Truncate if too long
        if (strlen($message) > 160) {
            $message = substr($message, 0, 157) . '...';
        }

        return $message;
    }

    /**
     * Format message for email (rich text)
     */
    private function formatEmailMessage($alertData) {
        $message = "
            <div style='background-color: #ff4444; color: white; padding: 20px; margin-bottom: 20px;'>
                <h2 style='margin: 0;'>🚨 AIRPORT EMERGENCY ALERT</h2>
            </div>

            <div style='padding: 20px;'>
                <h3>Alert Type: " . strtoupper($alertData['alert_type']) . "</h3>
                <p style='font-size: 16px; line-height: 1.5;'>{$alertData['message']}</p>
        ";

        if (isset($alertData['location'])) {
            $message .= "<p><strong>Location:</strong> {$alertData['location']}</p>";
        }

        if (isset($alertData['instructions'])) {
            $message .= "<p><strong>Instructions:</strong> {$alertData['instructions']}</p>";
        }

        if (isset($alertData['contact'])) {
            $message .= "<p><strong>Emergency Contact:</strong> {$alertData['contact']}</p>";
        }

        $message .= "
                <div style='margin-top: 30px; padding: 15px; background-color: #f0f0f0; border-radius: 5px;'>
                    <p style='margin: 0;'><strong>Time:</strong> " . date('Y-m-d H:i:s') . "</p>
                    <p style='margin: 5px 0 0 0;'><strong>Priority:</strong> " . strtoupper($alertData['priority']) . "</p>
                </div>
            </div>
        ";

        return $message;
    }

    /**
     * Format message for mobile alerts (very concise)
     */
    private function formatMobileMessage($alertData) {
        return "EMERGENCY: {$alertData['message']} - Follow safety instructions immediately.";
    }

    /**
     * Make API request to external services
     */
    private function makeAPIRequest($url, $method, $data, $apiKey) {
        $ch = curl_init();

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
            'User-Agent: Airport-Emergency-System/1.0'
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
     * Get configuration for various systems
     */
    private function getPagingSystemConfig() {
        return [
            'api_url' => getenv('PAGING_SYSTEM_API_URL') ?: 'https://api.pagingsystem.com',
            'api_key' => getenv('PAGING_SYSTEM_API_KEY') ?: 'default_key'
        ];
    }

    private function getSMSConfig() {
        return [
            'api_url' => getenv('SMS_GATEWAY_API_URL') ?: 'https://api.smsgateway.com',
            'api_key' => getenv('SMS_GATEWAY_API_KEY') ?: 'default_key'
        ];
    }

    private function getEmailConfig() {
        return [
            'api_url' => getenv('EMAIL_SYSTEM_API_URL') ?: 'https://api.emailsystem.com',
            'api_key' => getenv('EMAIL_SYSTEM_API_KEY') ?: 'default_key'
        ];
    }

    private function getMobileAlertConfig() {
        return [
            'api_url' => getenv('MOBILE_ALERT_API_URL') ?: 'https://api.mobilealert.com',
            'api_key' => getenv('MOBILE_ALERT_API_KEY') ?: 'default_key'
        ];
    }

    private function getPublicAddressConfig() {
        return [
            'api_url' => getenv('PUBLIC_ADDRESS_API_URL') ?: 'https://api.publicaddress.com',
            'api_key' => getenv('PUBLIC_ADDRESS_API_KEY') ?: 'default_key'
        ];
    }

    /**
     * Test integration with all notification systems
     */
    public function testIntegrations() {
        $this->logger->info('Testing emergency notification integrations');

        $testData = [
            'alert_type' => 'test',
            'message' => 'This is a test emergency alert',
            'priority' => 'low',
            'timestamp' => date('c')
        ];

        $results = [];

        // Test each system
        $systems = [
            'paging' => $this->sendInternalPaging($testData),
            'sms' => $this->sendSMSAlert($testData),
            'email' => $this->sendEmailAlert($testData),
            'mobile' => $this->sendMobileAlert($testData),
            'public_address' => $this->sendPublicAddressAlert($testData)
        ];

        foreach ($systems as $system => $result) {
            $results[$system] = [
                'status' => $result['success'] ? 'operational' : 'failed',
                'error' => $result['error'] ?? null,
                'timestamp' => date('c')
            ];
        }

        $this->logger->info('Emergency notification integration test completed', $results);

        return $results;
    }
}

?>
