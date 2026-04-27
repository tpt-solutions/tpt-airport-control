<?php

/**
 * Passenger Alerts Model
 *
 * Manages real-time notifications, travel reminders, and passenger communication
 */

class PassengerAlerts
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
        $this->logger = new Logger('passenger_alerts');
    }

    /**
     * Send alert notification to passenger
     */
    public function sendAlert($alertData)
    {
        $this->logger->info("Sending alert notification", $alertData);

        // Get template
        $template = $this->getAlertTemplate($alertData['template_id'] ?? $alertData['template_name']);
        if (!$template) {
            throw new Exception("Alert template not found");
        }

        // Substitute variables
        $subject = $this->substituteVariables($template['subject'], $alertData['variables'] ?? []);
        $message = $this->substituteVariables($template['message_template'], $alertData['variables'] ?? []);

        // Create alert instance
        $stmt = $this->db->prepare("
            INSERT INTO alert_instances (
                template_id, passenger_id, booking_id, flight_id,
                alert_type, subject, message, channels_used, priority,
                scheduled_time, variables_used
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $channels = $alertData['channels'] ?? $template['channels'];
        $stmt->execute([
            $template['template_id'],
            $alertData['passenger_id'],
            $alertData['booking_id'] ?? null,
            $alertData['flight_id'] ?? null,
            $template['template_type'],
            $subject,
            $message,
            json_encode($channels),
            $alertData['priority'] ?? $template['priority'],
            $alertData['scheduled_time'] ?? date('Y-m-d H:i:s'),
            json_encode($alertData['variables'] ?? [])
        ]);

        $alertId = $this->db->lastInsertId();

        // Queue for delivery
        $this->queueAlertForDelivery($alertId, $channels, $alertData['priority'] ?? $template['priority']);

        return [
            'alert_id' => $alertId,
            'status' => 'queued',
            'channels' => $channels
        ];
    }

    /**
     * Get alert template by ID or name
     */
    private function getAlertTemplate($templateId)
    {
        $stmt = $this->db->prepare("
            SELECT * FROM alert_templates
            WHERE (template_id = ? OR template_name = ?) AND is_active = true
        ");

        $stmt->execute([$templateId, $templateId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Substitute variables in template
     */
    private function substituteVariables($template, $variables)
    {
        $result = $template;

        foreach ($variables as $key => $value) {
            $result = str_replace('{{' . $key . '}}', $value, $result);
        }

        return $result;
    }

    /**
     * Queue alert for delivery
     */
    private function queueAlertForDelivery($alertId, $channels, $priority)
    {
        $priorityMap = [
            'low' => 1,
            'normal' => 2,
            'high' => 3,
            'critical' => 4
        ];

        $queuePriority = $priorityMap[$priority] ?? 2;

        foreach ($channels as $channel) {
            $stmt = $this->db->prepare("
                INSERT INTO alert_queue (alert_id, channel, priority)
                VALUES (?, ?, ?)
            ");

            $stmt->execute([$alertId, $channel, $queuePriority]);
        }
    }

    /**
     * Register push notification subscription
     */
    public function registerPushSubscription($subscriptionData)
    {
        $this->logger->info("Registering push subscription", [
            'passenger_id' => $subscriptionData['passenger_id'],
            'device_type' => $subscriptionData['device_type']
        ]);

        $stmt = $this->db->prepare("
            INSERT INTO push_subscriptions (
                passenger_id, endpoint, p256dh_key, auth_key,
                user_agent, device_type, browser, expires_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON CONFLICT (passenger_id, endpoint) DO UPDATE SET
                p256dh_key = EXCLUDED.p256dh_key,
                auth_key = EXCLUDED.auth_key,
                user_agent = EXCLUDED.user_agent,
                device_type = EXCLUDED.device_type,
                browser = EXCLUDED.browser,
                expires_at = EXCLUDED.expires_at,
                last_used = CURRENT_TIMESTAMP,
                is_active = true
        ");

        $stmt->execute([
            $subscriptionData['passenger_id'],
            $subscriptionData['endpoint'],
            $subscriptionData['p256dh_key'] ?? null,
            $subscriptionData['auth_key'] ?? null,
            $subscriptionData['user_agent'] ?? null,
            $subscriptionData['device_type'] ?? 'unknown',
            $subscriptionData['browser'] ?? null,
            $subscriptionData['expires_at'] ?? null
        ]);

        return ['status' => 'success', 'message' => 'Push subscription registered'];
    }

    /**
     * Update passenger notification preferences
     */
    public function updateNotificationPreferences($passengerId, $preferences)
    {
        $this->logger->info("Updating notification preferences", ['passenger_id' => $passengerId]);

        // Delete existing preferences
        $stmt = $this->db->prepare("DELETE FROM notification_preferences WHERE passenger_id = ?");
        $stmt->execute([$passengerId]);

        // Insert new preferences
        $stmt = $this->db->prepare("
            INSERT INTO notification_preferences (
                passenger_id, alert_type, channels, quiet_hours_start,
                quiet_hours_end, timezone, language, is_active
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($preferences as $preference) {
            $stmt->execute([
                $passengerId,
                $preference['alert_type'] ?? 'all',
                json_encode($preference['channels'] ?? ['push']),
                $preference['quiet_hours_start'] ?? null,
                $preference['quiet_hours_end'] ?? null,
                $preference['timezone'] ?? 'UTC',
                $preference['language'] ?? 'en',
                $preference['is_active'] ?? true
            ]);
        }

        return ['status' => 'success', 'message' => 'Preferences updated'];
    }

    /**
     * Get passenger notification preferences
     */
    public function getNotificationPreferences($passengerId)
    {
        $stmt = $this->db->prepare("
            SELECT * FROM notification_preferences
            WHERE passenger_id = ? AND is_active = true
            ORDER BY alert_type
        ");

        $stmt->execute([$passengerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Create travel itinerary with reminders
     */
    public function createTravelItinerary($bookingId)
    {
        $this->logger->info("Creating travel itinerary", ['booking_id' => $bookingId]);

        // Get booking details
        $booking = $this->getBookingDetails($bookingId);
        if (!$booking) {
            throw new Exception("Booking not found");
        }

        $reminders = [];

        // Check-in opens reminder (24 hours before)
        $reminders[] = [
            'passenger_id' => $booking['passenger_id'],
            'booking_id' => $bookingId,
            'flight_id' => $booking['flight_id'],
            'reminder_type' => 'checkin_opens',
            'scheduled_time' => $booking['departure_time'],
            'reminder_time' => date('Y-m-d H:i:s', strtotime($booking['departure_time']) - 86400) // 24 hours before
        ];

        // Boarding reminder (30 minutes before)
        $reminders[] = [
            'passenger_id' => $booking['passenger_id'],
            'booking_id' => $bookingId,
            'flight_id' => $booking['flight_id'],
            'reminder_type' => 'boarding_starts',
            'scheduled_time' => $booking['departure_time'],
            'reminder_time' => date('Y-m-d H:i:s', strtotime($booking['departure_time']) - 1800) // 30 minutes before
        ];

        // Final boarding call (15 minutes before)
        $reminders[] = [
            'passenger_id' => $booking['passenger_id'],
            'booking_id' => $bookingId,
            'flight_id' => $booking['flight_id'],
            'reminder_type' => 'final_boarding',
            'scheduled_time' => $booking['departure_time'],
            'reminder_time' => date('Y-m-d H:i:s', strtotime($booking['departure_time']) - 900) // 15 minutes before
        ];

        // Insert reminders
        $stmt = $this->db->prepare("
            INSERT INTO travel_itineraries (
                passenger_id, booking_id, flight_id, reminder_type,
                scheduled_time, reminder_time
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");

        foreach ($reminders as $reminder) {
            $stmt->execute([
                $reminder['passenger_id'],
                $reminder['booking_id'],
                $reminder['flight_id'],
                $reminder['reminder_type'],
                $reminder['scheduled_time'],
                $reminder['reminder_time']
            ]);
        }

        return [
            'status' => 'success',
            'reminders_created' => count($reminders),
            'booking' => $booking
        ];
    }

    /**
     * Get passenger's travel itinerary
     */
    public function getTravelItinerary($passengerId, $bookingId = null)
    {
        $whereClause = "WHERE ti.passenger_id = ?";
        $params = [$passengerId];

        if ($bookingId) {
            $whereClause .= " AND ti.booking_id = ?";
            $params[] = $bookingId;
        }

        $stmt = $this->db->prepare("
            SELECT
                ti.*,
                f.flight_number,
                f.origin,
                f.destination,
                f.departure_time,
                f.arrival_time,
                b.booking_reference
            FROM travel_itineraries ti
            JOIN flights f ON ti.flight_id = f.flight_id
            JOIN bookings b ON ti.booking_id = b.booking_id
            $whereClause
            ORDER BY ti.reminder_time ASC
        ");

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Send location-based alert
     */
    public function sendLocationAlert($locationData)
    {
        $this->logger->info("Sending location-based alert", $locationData);

        // Find passengers in the area
        $passengers = $this->findPassengersInArea(
            $locationData['latitude'],
            $locationData['longitude'],
            $locationData['radius_meters'] ?? 100
        );

        $alertsSent = 0;

        foreach ($passengers as $passenger) {
            // Check if passenger has location alerts enabled for this type
            if ($this->shouldSendLocationAlert($passenger['passenger_id'], $locationData['alert_type'])) {
                $this->sendAlert([
                    'template_name' => $this->getLocationAlertTemplate($locationData['alert_type']),
                    'passenger_id' => $passenger['passenger_id'],
                    'variables' => $locationData['variables'] ?? [],
                    'channels' => ['push']
                ]);

                $alertsSent++;
            }
        }

        return ['alerts_sent' => $alertsSent, 'passengers_found' => count($passengers)];
    }

    /**
     * Find passengers in a geographic area
     */
    private function findPassengersInArea($latitude, $longitude, $radiusMeters)
    {
        // This would integrate with GPS tracking or check-in location data
        // For now, return mock data
        return [
            ['passenger_id' => 1, 'distance' => 50],
            ['passenger_id' => 2, 'distance' => 75]
        ];
    }

    /**
     * Check if location alert should be sent to passenger
     */
    private function shouldSendLocationAlert($passengerId, $alertType)
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count
            FROM location_alerts
            WHERE passenger_id = ? AND alert_type = ? AND is_active = true
        ");

        $stmt->execute([$passengerId, $alertType]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result['count'] > 0;
    }

    /**
     * Get location alert template name
     */
    private function getLocationAlertTemplate($alertType)
    {
        $templateMap = [
            'arrival_at_airport' => 'Security Reminder',
            'near_gate' => 'Boarding Reminder',
            'security_line' => 'Security Reminder',
            'baggage_claim' => 'Baggage Claim Alert'
        ];

        return $templateMap[$alertType] ?? 'Security Reminder';
    }

    /**
     * Process pending alerts (for background job)
     */
    public function processPendingAlerts($limit = 50)
    {
        $stmt = $this->db->prepare("
            SELECT ai.*, at.channels
            FROM alert_instances ai
            JOIN alert_templates at ON ai.template_id = at.template_id
            WHERE ai.status = 'pending'
            AND (ai.scheduled_time IS NULL OR ai.scheduled_time <= NOW())
            ORDER BY ai.priority DESC, ai.created_at ASC
            LIMIT ?
        ");

        $stmt->execute([$limit]);
        $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $processed = 0;

        foreach ($alerts as $alert) {
            // Mark as processing
            $this->updateAlertStatus($alert['alert_id'], 'processing');

            // Send through appropriate channels
            $channels = json_decode($alert['channels'], true);
            $this->sendThroughChannels($alert, $channels);

            // Mark as sent
            $this->updateAlertStatus($alert['alert_id'], 'sent');

            $processed++;
        }

        return ['processed' => $processed, 'total_found' => count($alerts)];
    }

    /**
     * Send alert through specified channels
     */
    private function sendThroughChannels($alert, $channels)
    {
        foreach ($channels as $channel) {
            switch ($channel) {
                case 'push':
                    $this->sendPushNotification($alert);
                    break;
                case 'sms':
                    $this->sendSMS($alert);
                    break;
                case 'email':
                    $this->sendEmail($alert);
                    break;
            }
        }
    }

    /**
     * Send push notification
     */
    private function sendPushNotification($alert)
    {
        // Get passenger's push subscriptions
        $stmt = $this->db->prepare("
            SELECT * FROM push_subscriptions
            WHERE passenger_id = ? AND is_active = true
        ");

        $stmt->execute([$alert['passenger_id']]);
        $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($subscriptions as $subscription) {
            // Here you would integrate with a push notification service
            // For now, we'll just log it
            $this->logger->info("Push notification sent", [
                'alert_id' => $alert['alert_id'],
                'endpoint' => $subscription['endpoint']
            ]);
        }
    }

    /**
     * Send SMS notification
     */
    private function sendSMS($alert)
    {
        // Get passenger phone number
        $stmt = $this->db->prepare("
            SELECT phone FROM passengers WHERE passenger_id = ?
        ");

        $stmt->execute([$alert['passenger_id']]);
        $passenger = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($passenger && $passenger['phone']) {
            // Here you would integrate with SMS service (Twilio, AWS SNS, etc.)
            $this->logger->info("SMS sent", [
                'alert_id' => $alert['alert_id'],
                'phone' => $passenger['phone']
            ]);

            // Record SMS delivery
            $stmt = $this->db->prepare("
                INSERT INTO sms_deliveries (alert_id, phone_number, status, sent_time)
                VALUES (?, ?, 'sent', NOW())
            ");

            $stmt->execute([$alert['alert_id'], $passenger['phone']]);
        }
    }

    /**
     * Send email notification
     */
    private function sendEmail($alert)
    {
        // Get passenger email
        $stmt = $this->db->prepare("
            SELECT email FROM passengers WHERE passenger_id = ?
        ");

        $stmt->execute([$alert['passenger_id']]);
        $passenger = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($passenger && $passenger['email']) {
            // Here you would integrate with email service (SendGrid, AWS SES, etc.)
            $this->logger->info("Email sent", [
                'alert_id' => $alert['alert_id'],
                'email' => $passenger['email']
            ]);

            // Record email delivery
            $stmt = $this->db->prepare("
                INSERT INTO email_deliveries (alert_id, email_address, status, sent_time)
                VALUES (?, ?, 'sent', NOW())
            ");

            $stmt->execute([$alert['alert_id'], $passenger['email']]);
        }
    }

    /**
     * Update alert status
     */
    private function updateAlertStatus($alertId, $status)
    {
        $stmt = $this->db->prepare("
            UPDATE alert_instances
            SET status = ?, sent_time = CASE WHEN ? = 'sent' THEN NOW() ELSE sent_time END
            WHERE alert_id = ?
        ");

        $stmt->execute([$status, $status, $alertId]);
    }

    /**
     * Get alert analytics
     */
    public function getAlertAnalytics($startDate, $endDate)
    {
        $stmt = $this->db->prepare("
            SELECT
                DATE(ai.created_at) as date,
                ai.alert_type,
                COUNT(*) as total_sent,
                COUNT(CASE WHEN ai.status = 'delivered' THEN 1 END) as delivered,
                COUNT(CASE WHEN ai.status = 'failed' THEN 1 END) as failed,
                AVG(EXTRACT(EPOCH FROM (ai.sent_time - ai.created_at))) as avg_delivery_time
            FROM alert_instances ai
            WHERE DATE(ai.created_at) BETWEEN ? AND ?
            GROUP BY DATE(ai.created_at), ai.alert_type
            ORDER BY date, alert_type
        ");

        $stmt->execute([$startDate, $endDate]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Suppress notifications for passenger
     */
    public function suppressNotifications($passengerId, $alertType, $durationHours, $reason)
    {
        $suppressUntil = date('Y-m-d H:i:s', time() + ($durationHours * 3600));

        $stmt = $this->db->prepare("
            INSERT INTO notification_suppression (
                passenger_id, alert_type, suppress_until, reason
            ) VALUES (?, ?, ?, ?)
            ON CONFLICT (passenger_id, alert_type) DO UPDATE SET
                suppress_until = EXCLUDED.suppress_until,
                reason = EXCLUDED.reason
        ");

        $stmt->execute([$passengerId, $alertType, $suppressUntil, $reason]);

        return ['status' => 'success', 'suppress_until' => $suppressUntil];
    }

    /**
     * Get booking details
     */
    private function getBookingDetails($bookingId)
    {
        $stmt = $this->db->prepare("
            SELECT
                b.*,
                f.flight_number,
                f.departure_time,
                f.origin,
                f.destination
            FROM bookings b
            JOIN flights f ON b.flight_id = f.flight_id
            WHERE b.booking_id = ?
        ");

        $stmt->execute([$bookingId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Check if passenger should receive notification (considering preferences and suppressions)
     */
    public function shouldReceiveNotification($passengerId, $alertType, $priority = 'normal')
    {
        // Check suppressions
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count
            FROM notification_suppression
            WHERE passenger_id = ? AND (alert_type = ? OR alert_type = 'all')
            AND suppress_until > NOW()
        ");

        $stmt->execute([$passengerId, $alertType]);
        $suppression = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($suppression['count'] > 0) {
            return false;
        }

        // Check preferences
        $preferences = $this->getNotificationPreferences($passengerId);

        foreach ($preferences as $preference) {
            if ($preference['alert_type'] === $alertType || $preference['alert_type'] === 'all') {
                if (!$preference['is_active']) {
                    return false;
                }

                // Check quiet hours
                if ($this->isInQuietHours($preference)) {
                    return $priority === 'critical'; // Only critical alerts during quiet hours
                }

                return true;
            }
        }

        return true; // Default to sending if no specific preference
    }

    /**
     * Check if current time is in passenger's quiet hours
     */
    private function isInQuietHours($preference)
    {
        if (!$preference['quiet_hours_start'] || !$preference['quiet_hours_end']) {
            return false;
        }

        $now = new DateTime('now', new DateTimeZone($preference['timezone'] ?? 'UTC'));
        $currentTime = $now->format('H:i:s');

        $start = $preference['quiet_hours_start'];
        $end = $preference['quiet_hours_end'];

        if ($start < $end) {
            // Same day quiet hours
            return $currentTime >= $start && $currentTime <= $end;
        } else {
            // Overnight quiet hours
            return $currentTime >= $start || $currentTime <= $end;
        }
    }
}
