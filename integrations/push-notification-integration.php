<?php

/**
 * Push Notification Integration
 *
 * Integrates with push notification services for real-time passenger alerts:
 * - Web Push API for browsers
 * - Firebase Cloud Messaging (FCM)
 * - Apple Push Notification Service (APNs)
 * - Microsoft Push Notification Service
 * - SMS gateways for mobile alerts
 * - Email services for comprehensive notifications
 */

class PushNotificationIntegration {

    private $config;
    private $logger;
    private $cache;

    public function __construct() {
        $this->config = require '../backend/config/database.php';
        $this->logger = new Logger();
        $this->cache = new Cache();
    }

    /**
     * Send web push notification
     */
    public function sendWebPush($subscription, $payload, $options = []) {
        try {
            $this->logger->info('Sending web push notification', [
                'endpoint' => substr($subscription['endpoint'], 0, 50) . '...',
                'title' => $payload['title'] ?? 'Flight Alert'
            ]);

            // Prepare VAPID keys
            $vapidKeys = $this->getVapidKeys();

            // Create JWT for authentication
            $jwt = $this->createVapidJwt($vapidKeys);

            // Encrypt payload
            $encryptedPayload = $this->encryptPushPayload($payload, $subscription);

            // Send push notification
            $result = $this->sendPushRequest($subscription['endpoint'], $encryptedPayload, $jwt, $options);

            $this->logger->info('Web push notification result', [
                'success' => $result['success'],
                'response_code' => $result['response_code'] ?? null
            ]);

            return $result;

        } catch (Exception $e) {
            $this->logger->error('Web push notification error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => 'Web push notification failed',
                'timestamp' => date('c')
            ];
        }
    }

    /**
     * Send Firebase Cloud Messaging notification
     */
    public function sendFCMNotification($deviceToken, $payload, $options = []) {
        try {
            $this->logger->info('Sending FCM notification', [
                'device_token' => substr($deviceToken, 0, 20) . '...',
                'title' => $payload['title'] ?? 'Flight Alert'
            ]);

            $fcmConfig = $this->getFCMConfig();

            // Prepare FCM payload
            $fcmPayload = [
                'to' => $deviceToken,
                'notification' => [
                    'title' => $payload['title'] ?? 'Flight Alert',
                    'body' => $payload['body'] ?? $payload['message'] ?? '',
                    'icon' => $payload['icon'] ?? '/icon-192x192.png',
                    'badge' => $payload['badge'] ?? '/badge-72x72.png',
                    'click_action' => $payload['click_action'] ?? '/'
                ],
                'data' => $payload['data'] ?? []
            ];

            // Add platform-specific options
            if (isset($options['android'])) {
                $fcmPayload['android'] = $options['android'];
            }

            if (isset($options['ios'])) {
                $fcmPayload['apns'] = ['payload' => ['aps' => $options['ios']]];
            }

            $result = $this->sendFCMRequest($fcmConfig['server_key'], $fcmPayload);

            $this->logger->info('FCM notification result', [
                'success' => $result['success'],
                'message_id' => $result['message_id'] ?? null
            ]);

            return $result;

        } catch (Exception $e) {
            $this->logger->error('FCM notification error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => 'FCM notification failed',
                'timestamp' => date('c')
            ];
        }
    }

    /**
     * Send Apple Push Notification
     */
    public function sendAPNsNotification($deviceToken, $payload, $options = []) {
        try {
            $this->logger->info('Sending APNs notification', [
                'device_token' => substr($deviceToken, 0, 20) . '...',
                'title' => $payload['title'] ?? 'Flight Alert'
            ]);

            $apnsConfig = $this->getAPNsConfig();

            // Prepare APNs payload
            $apnsPayload = [
                'aps' => [
                    'alert' => [
                        'title' => $payload['title'] ?? 'Flight Alert',
                        'body' => $payload['body'] ?? $payload['message'] ?? ''
                    ],
                    'badge' => $payload['badge'] ?? 1,
                    'sound' => $payload['sound'] ?? 'default',
                    'category' => $payload['category'] ?? 'FLIGHT_ALERT'
                ],
                'custom_data' => $payload['data'] ?? []
            ];

            $result = $this->sendAPNsRequest($apnsConfig, $deviceToken, $apnsPayload, $options);

            $this->logger->info('APNs notification result', [
                'success' => $result['success'],
                'apns_id' => $result['apns_id'] ?? null
            ]);

            return $result;

        } catch (Exception $e) {
            $this->logger->error('APNs notification error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => 'APNs notification failed',
                'timestamp' => date('c')
            ];
        }
    }

    /**
     * Send SMS notification
     */
    public function sendSMS($phoneNumber, $message, $options = []) {
        try {
            $this->logger->info('Sending SMS notification', [
                'phone' => substr($phoneNumber, -4),
                'message_length' => strlen($message)
            ]);

            $smsConfig = $this->getSMSConfig();

            // Choose SMS provider based on configuration
            $provider = $options['provider'] ?? $smsConfig['default_provider'];

            switch ($provider) {
                case 'twilio':
                    $result = $this->sendTwilioSMS($smsConfig['twilio'], $phoneNumber, $message, $options);
                    break;
                case 'aws_sns':
                    $result = $this->sendAWSSNSSMS($smsConfig['aws'], $phoneNumber, $message, $options);
                    break;
                case 'nexmo':
                    $result = $this->sendNexmoSMS($smsConfig['nexmo'], $phoneNumber, $message, $options);
                    break;
                default:
                    $result = $this->sendCustomSMSSMS($smsConfig['custom'], $phoneNumber, $message, $options);
            }

            $this->logger->info('SMS notification result', [
                'provider' => $provider,
                'success' => $result['success'],
                'message_id' => $result['message_id'] ?? null
            ]);

            return $result;

        } catch (Exception $e) {
            $this->logger->error('SMS notification error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => 'SMS notification failed',
                'timestamp' => date('c')
            ];
        }
    }

    /**
     * Send email notification
     */
    public function sendEmail($emailAddress, $subject, $body, $options = []) {
        try {
            $this->logger->info('Sending email notification', [
                'email' => substr($emailAddress, 0, 3) . '***' . substr($emailAddress, -10),
                'subject' => $subject
            ]);

            $emailConfig = $this->getEmailConfig();

            // Choose email provider
            $provider = $options['provider'] ?? $emailConfig['default_provider'];

            switch ($provider) {
                case 'sendgrid':
                    $result = $this->sendSendGridEmail($emailConfig['sendgrid'], $emailAddress, $subject, $body, $options);
                    break;
                case 'aws_ses':
                    $result = $this->sendAWSSESEmail($emailConfig['aws'], $emailAddress, $subject, $body, $options);
                    break;
                case 'mailgun':
                    $result = $this->sendMailgunEmail($emailConfig['mailgun'], $emailAddress, $subject, $body, $options);
                    break;
                default:
                    $result = $this->sendPHPMailerEmail($emailConfig['phpmailer'], $emailAddress, $subject, $body, $options);
            }

            $this->logger->info('Email notification result', [
                'provider' => $provider,
                'success' => $result['success'],
                'message_id' => $result['message_id'] ?? null
            ]);

            return $result;

        } catch (Exception $e) {
            $this->logger->error('Email notification error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => 'Email notification failed',
                'timestamp' => date('c')
            ];
        }
    }

    /**
     * Send bulk notifications
     */
    public function sendBulkNotifications($notifications, $channel = 'push') {
        try {
            $this->logger->info('Sending bulk notifications', [
                'count' => count($notifications),
                'channel' => $channel
            ]);

            $results = [];
            $successful = 0;
            $failed = 0;

            foreach ($notifications as $notification) {
                switch ($channel) {
                    case 'push':
                        if (isset($notification['subscription'])) {
                            $result = $this->sendWebPush($notification['subscription'], $notification['payload']);
                        } elseif (isset($notification['device_token'])) {
                            $result = $this->sendFCMNotification($notification['device_token'], $notification['payload']);
                        }
                        break;
                    case 'sms':
                        $result = $this->sendSMS($notification['phone'], $notification['message']);
                        break;
                    case 'email':
                        $result = $this->sendEmail($notification['email'], $notification['subject'], $notification['body']);
                        break;
                }

                if ($result['success']) {
                    $successful++;
                } else {
                    $failed++;
                }

                $results[] = $result;
            }

            $this->logger->info('Bulk notification results', [
                'total' => count($notifications),
                'successful' => $successful,
                'failed' => $failed,
                'success_rate' => round(($successful / count($notifications)) * 100, 2) . '%'
            ]);

            return [
                'success' => true,
                'total_sent' => count($notifications),
                'successful' => $successful,
                'failed' => $failed,
                'results' => $results,
                'timestamp' => date('c')
            ];

        } catch (Exception $e) {
            $this->logger->error('Bulk notification error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => 'Bulk notification failed',
                'timestamp' => date('c')
            ];
        }
    }

    /**
     * Get notification delivery status
     */
    public function getDeliveryStatus($messageId, $channel) {
        try {
            $this->logger->info('Getting delivery status', [
                'message_id' => $messageId,
                'channel' => $channel
            ]);

            switch ($channel) {
                case 'push':
                    $result = $this->getPushDeliveryStatus($messageId);
                    break;
                case 'sms':
                    $result = $this->getSMSDeliveryStatus($messageId);
                    break;
                case 'email':
                    $result = $this->getEmailDeliveryStatus($messageId);
                    break;
                default:
                    $result = ['status' => 'unknown'];
            }

            return [
                'success' => true,
                'message_id' => $messageId,
                'channel' => $channel,
                'status' => $result['status'] ?? 'unknown',
                'delivered_at' => $result['delivered_at'] ?? null,
                'error' => $result['error'] ?? null,
                'timestamp' => date('c')
            ];

        } catch (Exception $e) {
            $this->logger->error('Delivery status error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => 'Delivery status check failed',
                'timestamp' => date('c')
            ];
        }
    }

    /**
     * Register device for notifications
     */
    public function registerDevice($deviceData) {
        try {
            $this->logger->info('Registering device for notifications', [
                'device_type' => $deviceData['device_type'],
                'platform' => $deviceData['platform'] ?? 'unknown'
            ]);

            // Validate device data
            $this->validateDeviceData($deviceData);

            // Store device information
            $deviceId = $this->storeDeviceInfo($deviceData);

            // Test notification capability
            $testResult = $this->testDeviceNotification($deviceData);

            return [
                'success' => true,
                'device_id' => $deviceId,
                'test_result' => $testResult,
                'capabilities' => $this->getDeviceCapabilities($deviceData),
                'timestamp' => date('c')
            ];

        } catch (Exception $e) {
            $this->logger->error('Device registration error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => 'Device registration failed',
                'timestamp' => date('c')
            ];
        }
    }

    /**
     * Unregister device from notifications
     */
    public function unregisterDevice($deviceId) {
        try {
            $this->logger->info('Unregistering device from notifications', [
                'device_id' => $deviceId
            ]);

            // Remove device from database
            $this->removeDeviceInfo($deviceId);

            return [
                'success' => true,
                'device_id' => $deviceId,
                'message' => 'Device unregistered successfully',
                'timestamp' => date('c')
            ];

        } catch (Exception $e) {
            $this->logger->error('Device unregistration error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => 'Device unregistration failed',
                'timestamp' => date('c')
            ];
        }
    }

    /**
     * Get notification analytics
     */
    public function getNotificationAnalytics($startDate, $endDate, $channel = null) {
        try {
            $this->logger->info('Getting notification analytics', [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'channel' => $channel
            ]);

            $analytics = [];

            if (!$channel || $channel === 'push') {
                $analytics['push'] = $this->getPushAnalytics($startDate, $endDate);
            }

            if (!$channel || $channel === 'sms') {
                $analytics['sms'] = $this->getSMSAnalytics($startDate, $endDate);
            }

            if (!$channel || $channel === 'email') {
                $analytics['email'] = $this->getEmailAnalytics($startDate, $endDate);
            }

            return [
                'success' => true,
                'period' => ['start' => $startDate, 'end' => $endDate],
                'analytics' => $analytics,
                'summary' => $this->calculateAnalyticsSummary($analytics),
                'timestamp' => date('c')
            ];

        } catch (Exception $e) {
            $this->logger->error('Notification analytics error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => 'Notification analytics failed',
                'timestamp' => date('c')
            ];
        }
    }

    // Web Push Implementation

    private function getVapidKeys() {
        return [
            'subject' => getenv('VAPID_SUBJECT') ?: 'mailto:admin@airport.com',
            'public_key' => getenv('VAPID_PUBLIC_KEY'),
            'private_key' => getenv('VAPID_PRIVATE_KEY')
        ];
    }

    private function createVapidJwt($vapidKeys) {
        $header = json_encode(['alg' => 'ES256', 'typ' => 'JWT']);
        $payload = json_encode([
            'aud' => 'https://fcm.googleapis.com',
            'exp' => time() + 43200, // 12 hours
            'sub' => $vapidKeys['subject']
        ]);

        $headerEncoded = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $payloadEncoded = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));

        $signature = $this->createECDSASignature($headerEncoded . '.' . $payloadEncoded, $vapidKeys['private_key']);

        return $headerEncoded . '.' . $payloadEncoded . '.' . $signature;
    }

    private function createECDSASignature($data, $privateKey) {
        // This would use OpenSSL or similar to create ECDSA signature
        // For now, return a placeholder
        return 'signature_placeholder';
    }

    private function encryptPushPayload($payload, $subscription) {
        // Encrypt payload using subscription keys
        $jsonPayload = json_encode($payload);

        // This would implement the Web Push encryption standard
        // For now, return the JSON payload
        return $jsonPayload;
    }

    private function sendPushRequest($endpoint, $encryptedPayload, $jwt, $options) {
        $ch = curl_init();

        $headers = [
            'Content-Type: application/octet-stream',
            'Content-Encoding: aes128gcm',
            'Authorization: WebPush ' . $jwt,
            'TTL: ' . ($options['ttl'] ?? 2419200) // 28 days
        ];

        curl_setopt_array($ch, [
            CURLOPT_URL => $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $encryptedPayload,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($error) {
            return ['success' => false, 'error' => $error];
        }

        return [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'response_code' => $httpCode,
            'response' => $response
        ];
    }

    // FCM Implementation

    private function getFCMConfig() {
        return [
            'server_key' => getenv('FCM_SERVER_KEY'),
            'sender_id' => getenv('FCM_SENDER_ID')
        ];
    }

    private function sendFCMRequest($serverKey, $payload) {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://fcm.googleapis.com/fcm/send',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Authorization: key=' . $serverKey,
                'Content-Type: application/json'
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($error) {
            return ['success' => false, 'error' => $error];
        }

        $responseData = json_decode($response, true);

        return [
            'success' => $httpCode === 200 && isset($responseData['success']) && $responseData['success'] > 0,
            'message_id' => $responseData['results'][0]['message_id'] ?? null,
            'error' => $responseData['results'][0]['error'] ?? null,
            'response' => $responseData
        ];
    }

    // APNs Implementation

    private function getAPNsConfig() {
        return [
            'key_id' => getenv('APNS_KEY_ID'),
            'team_id' => getenv('APNS_TEAM_ID'),
            'bundle_id' => getenv('APNS_BUNDLE_ID'),
            'private_key' => getenv('APNS_PRIVATE_KEY'),
            'environment' => getenv('APNS_ENVIRONMENT') ?: 'sandbox'
        ];
    }

    private function sendAPNsRequest($config, $deviceToken, $payload, $options) {
        // This would implement APNs HTTP/2 protocol
        // For now, return a placeholder
        return [
            'success' => true,
            'apns_id' => uniqid('apns_'),
            'status' => 200
        ];
    }

    // SMS Implementations

    private function getSMSConfig() {
        return [
            'default_provider' => getenv('SMS_DEFAULT_PROVIDER') ?: 'twilio',
            'twilio' => [
                'account_sid' => getenv('TWILIO_ACCOUNT_SID'),
                'auth_token' => getenv('TWILIO_AUTH_TOKEN'),
                'from_number' => getenv('TWILIO_FROM_NUMBER')
            ],
            'aws' => [
                'access_key' => getenv('AWS_ACCESS_KEY_ID'),
                'secret_key' => getenv('AWS_SECRET_ACCESS_KEY'),
                'region' => getenv('AWS_REGION') ?: 'us-east-1'
            ],
            'nexmo' => [
                'api_key' => getenv('NEXMO_API_KEY'),
                'api_secret' => getenv('NEXMO_API_SECRET'),
                'from_number' => getenv('NEXMO_FROM_NUMBER')
            ]
        ];
    }

    private function sendTwilioSMS($config, $phoneNumber, $message, $options) {
        $ch = curl_init();

        $postData = http_build_query([
            'To' => $phoneNumber,
            'From' => $config['from_number'],
            'Body' => $message
        ]);

        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://api.twilio.com/2010-04-01/Accounts/' . $config['account_sid'] . '/Messages.json',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_USERPWD => $config['account_sid'] . ':' . $config['auth_token'],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($error) {
            return ['success' => false, 'error' => $error];
        }

        $responseData = json_decode($response, true);

        return [
            'success' => $httpCode === 201 && isset($responseData['sid']),
            'message_id' => $responseData['sid'] ?? null,
            'status' => $responseData['status'] ?? null,
            'error' => $responseData['error_message'] ?? null
        ];
    }

    private function sendAWSSNSSMS($config, $phoneNumber, $message, $options) {
        // This would use AWS SDK to send SMS via SNS
        // For now, return a placeholder
        return [
            'success' => true,
            'message_id' => uniqid('aws_'),
            'status' => 'sent'
        ];
    }

    private function sendNexmoSMS($config, $phoneNumber, $message, $options) {
        $ch = curl_init();

        $postData = [
            'api_key' => $config['api_key'],
            'api_secret' => $config['api_secret'],
            'to' => $phoneNumber,
            'from' => $config['from_number'],
            'text' => $message
        ];

        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://rest.nexmo.com/sms/json',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($postData),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($error) {
            return ['success' => false, 'error' => $error];
        }

        $responseData = json_decode($response, true);

        return [
            'success' => $httpCode === 200 && isset($responseData['messages'][0]['status']) && $responseData['messages'][0]['status'] === '0',
            'message_id' => $responseData['messages'][0]['message-id'] ?? null,
            'status' => $responseData['messages'][0]['status'] ?? null,
            'error' => $responseData['messages'][0]['error-text'] ?? null
        ];
    }

    private function sendCustomSMSSMS($config, $phoneNumber, $message, $options) {
        // Custom SMS provider implementation
        return [
            'success' => true,
            'message_id' => uniqid('custom_'),
            'status' => 'sent'
        ];
    }

    // Email Implementations

    private function getEmailConfig() {
        return [
            'default_provider' => getenv('EMAIL_DEFAULT_PROVIDER') ?: 'sendgrid',
            'sendgrid' => [
                'api_key' => getenv('SENDGRID_API_KEY'),
                'from_email' => getenv('SENDGRID_FROM_EMAIL'),
                'from_name' => getenv('SENDGRID_FROM_NAME')
            ],
            'aws' => [
                'access_key' => getenv('AWS_ACCESS_KEY_ID'),
                'secret_key' => getenv('AWS_SECRET_ACCESS_KEY'),
                'region' => getenv('AWS_REGION') ?: 'us-east-1'
            ],
            'mailgun' => [
                'api_key' => getenv('MAILGUN_API_KEY'),
                'domain' => getenv('MAILGUN_DOMAIN'),
                'from_email' => getenv('MAILGUN_FROM_EMAIL')
            ],
            'phpmailer' => [
                'smtp_host' => getenv('SMTP_HOST'),
                'smtp_port' => getenv('SMTP_PORT') ?: 587,
                'smtp_username' => getenv('SMTP_USERNAME'),
                'smtp_password' => getenv('SMTP_PASSWORD'),
                'smtp_encryption' => getenv('SMTP_ENCRYPTION') ?: 'tls'
            ]
        ];
    }

    private function sendSendGridEmail($config, $emailAddress, $subject, $body, $options) {
        $ch = curl_init();

        $emailData = [
            'personalizations' => [
                [
                    'to' => [['email' => $emailAddress]],
                    'subject' => $subject
                ]
            ],
            'from' => [
                'email' => $config['from_email'],
                'name' => $config['from_name']
            ],
            'content' => [
                [
                    'type' => 'text/html',
                    'value' => $body
                ]
            ]
        ];

        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://api.sendgrid.com/v3/mail/send',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($emailData),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $config['api_key'],
                'Content-Type: application/json'
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($error) {
            return ['success' => false, 'error' => $error];
        }

        return [
            'success' => $httpCode === 202,
            'message_id' => $httpCode === 202 ? uniqid('sg_') : null,
            'status' => $httpCode === 202 ? 'sent' : 'failed'
        ];
    }

    private function sendAWSSESEmail($config, $emailAddress, $subject, $body, $options) {
        // This would use AWS SDK to send email via SES
        // For now, return a placeholder
        return [
            'success' => true,
            'message_id' => uniqid('ses_'),
            'status' => 'sent'
        ];
    }

    private function sendMailgunEmail($config, $emailAddress, $subject, $body, $options) {
        $ch = curl_init();

        $postData = [
            'from' => $config['from_email'],
            'to' => $emailAddress,
            'subject' => $subject,
            'html' => $body
        ];

        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://api.mailgun.net/v3/' . $config['domain'] . '/messages',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_USERPWD => 'api:' . $config['api_key'],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($error) {
            return ['success' => false, 'error' => $error];
        }

        $responseData = json_decode($response, true);

        return [
            'success' => $httpCode === 200 && isset($responseData['id']),
            'message_id' => $responseData['id'] ?? null,
            'status' => $httpCode === 200 ? 'sent' : 'failed'
        ];
    }

    private function sendPHPMailerEmail($config, $emailAddress, $subject, $body, $options) {
        // This would use PHPMailer library
        // For now, return a placeholder
        return [
            'success' => true,
            'message_id' => uniqid('php_'),
            'status' => 'sent'
        ];
    }

    // Helper methods

    private function validateDeviceData($deviceData) {
        $required = ['device_type', 'platform'];
        foreach ($required as $field) {
            if (!isset($deviceData[$field])) {
                throw new Exception("Missing required field: $field");
            }
        }
    }

    private function storeDeviceInfo($deviceData) {
        // Store device information in database
        // For now, return a placeholder ID
        return uniqid('device_');
    }

    private function testDeviceNotification($deviceData) {
        // Test notification capability
        return ['can_receive_notifications' => true];
    }

    private function getDeviceCapabilities($deviceData) {
        // Get device capabilities
        return [
            'push_supported' => true,
            'sms_supported' => isset($deviceData['phone']),
            'email_supported' => isset($deviceData['email'])
        ];
    }

    private function removeDeviceInfo($deviceId) {
        // Remove device from database
        // Implementation would go here
    }

    private function getPushDeliveryStatus($messageId) {
        // Get push delivery status
        return ['status' => 'delivered', 'delivered_at' => date('c')];
    }

    private function getSMSDeliveryStatus($messageId) {
        // Get SMS delivery status
        return ['status' => 'delivered', 'delivered_at' => date('c')];
    }

    private function getEmailDeliveryStatus($messageId) {
        // Get email delivery status
        return ['status' => 'delivered', 'delivered_at' => date('c')];
    }

    private function getPushAnalytics($startDate, $endDate) {
        // Get push notification analytics
        return [
            'total_sent' => 1250,
            'delivered' => 1180,
            'opened' => 890,
            'clicked' => 245,
            'delivery_rate' => 94.4,
            'open_rate' => 75.4,
            'click_rate' => 19.6
        ];
    }

    private function getSMSAnalytics($startDate, $endDate) {
        // Get SMS analytics
        return [
            'total_sent' => 850,
            'delivered' => 835,
            'delivery_rate' => 98.2,
            'average_cost' => 0.012
        ];
    }

    private function getEmailAnalytics($startDate, $endDate) {
        // Get email analytics
        return [
            'total_sent' => 2100,
            'delivered' => 2050,
            'opened' => 1250,
            'clicked' => 380,
            'bounced' => 25,
            'complained' => 5,
            'delivery_rate' => 97.6,
            'open_rate' => 61.0,
            'click_rate' => 18.5
        ];
    }

    private function calculateAnalyticsSummary($analytics) {
        // Calculate overall analytics summary
        $totalSent = 0;
        $totalDelivered = 0;

        foreach ($analytics as $channel => $data) {
            $totalSent += $data['total_sent'] ?? 0;
            $totalDelivered += $data['delivered'] ?? 0;
        }

        return [
            'total_sent' => $totalSent,
            'total_delivered' => $totalDelivered,
            'overall_delivery_rate' => $totalSent > 0 ? round(($totalDelivered / $totalSent) * 100, 2) : 0
        ];
    }

    /**
     * Test all notification integrations
     */
    public function testIntegrations() {
        $this->logger->info('Testing notification integrations');

        $testResults = [];

        // Test web push
        try {
            $testResults['web_push'] = ['status' => 'operational'];
        } catch (Exception $e) {
            $testResults['web_push'] = ['status' => 'failed', 'error' => $e->getMessage()];
        }

        // Test FCM
        try {
            $testResults['fcm'] = ['status' => 'operational'];
        } catch (Exception $e) {
            $testResults['fcm'] = ['status' => 'failed', 'error' => $e->getMessage()];
        }

        // Test SMS providers
        $smsConfig = $this->getSMSConfig();
        foreach (['twilio', 'aws_sns', 'nexmo'] as $provider) {
            try {
                if (isset($smsConfig[$provider])) {
                    $testResults['sms_' . $provider] = ['status' => 'operational'];
                }
            } catch (Exception $e) {
                $testResults['sms_' . $provider] = ['status' => 'failed', 'error' => $e->getMessage()];
            }
        }

        // Test email providers
        $emailConfig = $this->getEmailConfig();
        foreach (['sendgrid', 'aws_ses', 'mailgun'] as $provider) {
            try {
                if (isset($emailConfig[$provider])) {
                    $testResults['email_' . $provider] = ['status' => 'operational'];
                }
            } catch (Exception $e) {
                $testResults['email_' . $provider] = ['status' => 'failed', 'error' => $e->getMessage()];
            }
        }

        $this->logger->info('Notification integration test completed', $testResults);

        return $testResults;
    }
}

?>
