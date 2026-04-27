<?php

/**
 * Self Check-in Model
 *
 * Manages automated passenger check-in kiosks and biometric verification
 */

class SelfCheckin
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
        $this->logger = new Logger('self_checkin');
    }

    /**
     * Get available kiosks
     */
    public function getAvailableKiosks()
    {
        $stmt = $this->db->prepare("
            SELECT
                k.*,
                (
                    SELECT COUNT(*)
                    FROM checkin_queue q
                    WHERE q.kiosk_id = k.kiosk_id AND q.queue_status = 'waiting'
                ) as current_queue,
                (
                    SELECT estimate_wait_time(k.kiosk_id, 1)
                ) as estimated_wait_time
            FROM self_checkin_kiosks k
            WHERE k.status = 'active'
            ORDER BY k.location, k.kiosk_name
        ");

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Start a check-in session
     */
    public function startCheckinSession($passengerData)
    {
        $this->logger->info("Starting check-in session", $passengerData);

        // Validate passenger and booking
        $passenger = $this->validatePassenger($passengerData['passenger_id']);
        $booking = $this->validateBooking($passengerData['booking_id'], $passengerData['passenger_id']);

        if (!$passenger || !$booking) {
            throw new Exception("Invalid passenger or booking information");
        }

        // Check if already checked in
        if ($booking['checkin_status'] === 'completed') {
            throw new Exception("Passenger already checked in");
        }

        // Create session
        $stmt = $this->db->prepare("
            INSERT INTO checkin_sessions (
                passenger_id, booking_id, kiosk_id, language_used,
                accessibility_features
            ) VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $passengerData['passenger_id'],
            $passengerData['booking_id'],
            $passengerData['kiosk_id'] ?? null,
            $passengerData['language'] ?? 'en',
            isset($passengerData['accessibility_features']) ? json_encode($passengerData['accessibility_features']) : '{}'
        ]);

        $sessionId = $this->db->lastInsertId();

        // Add to queue if kiosk specified
        if (isset($passengerData['kiosk_id'])) {
            $this->addToQueue($sessionId, $passengerData['kiosk_id'], $passengerData['passenger_id'], $passengerData['booking_id']);
        }

        return [
            'session_id' => $sessionId,
            'status' => 'started',
            'passenger' => $passenger,
            'booking' => $booking
        ];
    }

    /**
     * Perform biometric verification
     */
    public function performBiometricVerification($verificationData)
    {
        $this->logger->info("Performing biometric verification", [
            'passenger_id' => $verificationData['passenger_id'],
            'type' => $verificationData['verification_type']
        ]);

        // Simulate biometric verification (in production, this would integrate with actual biometric systems)
        $confidenceScore = rand(85, 98); // Simulated confidence score
        $status = $confidenceScore >= 90 ? 'success' : 'failed';

        // Store verification record
        $stmt = $this->db->prepare("
            INSERT INTO biometric_verification (
                passenger_id, kiosk_id, verification_type,
                verification_data, confidence_score, verification_status,
                ip_address, device_fingerprint
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $encryptedData = $this->encryptBiometricData($verificationData['biometric_data'] ?? '{}');

        $stmt->execute([
            $verificationData['passenger_id'],
            $verificationData['kiosk_id'] ?? null,
            $verificationData['verification_type'],
            $encryptedData,
            $confidenceScore,
            $status,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $verificationData['device_fingerprint'] ?? null
        ]);

        return [
            'verification_id' => $this->db->lastInsertId(),
            'status' => $status,
            'confidence_score' => $confidenceScore,
            'verified' => $status === 'success'
        ];
    }

    /**
     * Update check-in session progress
     */
    public function updateSessionProgress($sessionId, $progressData)
    {
        $this->logger->info("Updating session progress", ['session_id' => $sessionId, 'step' => $progressData['step']]);

        // Get current session
        $session = $this->getSession($sessionId);
        if (!$session) {
            throw new Exception("Session not found");
        }

        // Update steps completed
        $stepsCompleted = json_decode($session['steps_completed'], true) ?? [];
        if (!in_array($progressData['step'], $stepsCompleted)) {
            $stepsCompleted[] = $progressData['step'];
        }

        // Update session
        $stmt = $this->db->prepare("
            UPDATE checkin_sessions
            SET steps_completed = ?, services_selected = ?
            WHERE session_id = ?
        ");

        $servicesSelected = isset($progressData['services_selected']) ?
            json_encode($progressData['services_selected']) : $session['services_selected'];

        $stmt->execute([
            json_encode($stepsCompleted),
            $servicesSelected,
            $sessionId
        ]);

        return [
            'session_id' => $sessionId,
            'steps_completed' => $stepsCompleted,
            'next_step' => $this->getNextStep($stepsCompleted)
        ];
    }

    /**
     * Select seat during check-in
     */
    public function selectSeat($selectionData)
    {
        $this->logger->info("Processing seat selection", $selectionData);

        // Validate seat availability
        if (!$this->isSeatAvailable($selectionData['flight_id'], $selectionData['selected_seat'])) {
            throw new Exception("Selected seat is not available");
        }

        // Record seat selection
        $stmt = $this->db->prepare("
            INSERT INTO seat_selections (
                session_id, flight_id, original_seat, selected_seat,
                seat_class, selection_reason
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $selectionData['session_id'],
            $selectionData['flight_id'],
            $selectionData['original_seat'] ?? null,
            $selectionData['selected_seat'],
            $selectionData['seat_class'] ?? 'economy',
            $selectionData['selection_reason'] ?? 'preferred'
        ]);

        return [
            'selection_id' => $this->db->lastInsertId(),
            'seat' => $selectionData['selected_seat'],
            'confirmed' => false
        ];
    }

    /**
     * Select additional services
     */
    public function selectServices($serviceData)
    {
        $this->logger->info("Processing service selection", $serviceData);

        $stmt = $this->db->prepare("
            INSERT INTO service_selections (
                session_id, service_type, service_option, service_price
            ) VALUES (?, ?, ?, ?)
        ");

        $stmt->execute([
            $serviceData['session_id'],
            $serviceData['service_type'],
            $serviceData['service_option'],
            $serviceData['service_price'] ?? 0
        ]);

        return [
            'selection_id' => $this->db->lastInsertId(),
            'service_type' => $serviceData['service_type'],
            'service_option' => $serviceData['service_option']
        ];
    }

    /**
     * Complete check-in process
     */
    public function completeCheckin($sessionId, $completionData)
    {
        $this->logger->info("Completing check-in", ['session_id' => $sessionId]);

        // Get session details
        $session = $this->getSession($sessionId);
        if (!$session) {
            throw new Exception("Session not found");
        }

        // Update session as completed
        $stmt = $this->db->prepare("
            UPDATE checkin_sessions
            SET checkin_status = 'completed', session_end = NOW(),
                total_duration = EXTRACT(EPOCH FROM (NOW() - session_start))
            WHERE session_id = ?
        ");

        $stmt->execute([$sessionId]);

        // Update booking status
        $stmt = $this->db->prepare("
            UPDATE bookings
            SET checkin_status = 'completed', checkin_time = NOW()
            WHERE booking_id = ?
        ");

        $stmt->execute([$session['booking_id']]);

        // Generate digital boarding pass
        $boardingPass = $this->generateBoardingPass($sessionId, $completionData);

        // Update queue status
        if ($session['kiosk_id']) {
            $this->updateQueueStatus($session['kiosk_id'], $session['passenger_id'], 'completed');
        }

        return [
            'session_id' => $sessionId,
            'status' => 'completed',
            'boarding_pass' => $boardingPass,
            'duration' => time() - strtotime($session['session_start'])
        ];
    }

    /**
     * Generate digital boarding pass
     */
    private function generateBoardingPass($sessionId, $completionData)
    {
        $session = $this->getSession($sessionId);

        // Get seat selection
        $seatSelection = $this->getSeatSelection($sessionId);

        $stmt = $this->db->prepare("
            INSERT INTO digital_boarding_passes (
                session_id, passenger_id, booking_id, flight_id,
                seat_number, gate, boarding_time
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $sessionId,
            $session['passenger_id'],
            $session['booking_id'],
            $completionData['flight_id'],
            $seatSelection['selected_seat'] ?? $completionData['seat_number'],
            $completionData['gate'],
            $completionData['boarding_time']
        ]);

        $passId = $this->db->lastInsertId();

        // Generate QR code
        $this->generateQRCode($passId);

        return [
            'pass_id' => $passId,
            'seat' => $seatSelection['selected_seat'] ?? $completionData['seat_number'],
            'gate' => $completionData['gate'],
            'boarding_time' => $completionData['boarding_time']
        ];
    }

    /**
     * Get check-in session details
     */
    public function getSession($sessionId)
    {
        $stmt = $this->db->prepare("
            SELECT
                cs.*,
                p.first_name, p.last_name, p.passport_number,
                b.flight_id, b.booking_reference
            FROM checkin_sessions cs
            JOIN passengers p ON cs.passenger_id = p.passenger_id
            JOIN bookings b ON cs.booking_id = b.booking_id
            WHERE cs.session_id = ?
        ");

        $stmt->execute([$sessionId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get available seats for a flight
     */
    public function getAvailableSeats($flightId)
    {
        // This would integrate with the flight seating system
        // For now, return mock data
        return [
            ['seat' => '12A', 'class' => 'economy', 'available' => true],
            ['seat' => '12B', 'class' => 'economy', 'available' => true],
            ['seat' => '12C', 'class' => 'economy', 'available' => false],
            ['seat' => '14A', 'class' => 'premium', 'available' => true],
            ['seat' => '14B', 'class' => 'premium', 'available' => true]
        ];
    }

    /**
     * Get check-in preferences for a passenger
     */
    public function getCheckinPreferences($passengerId)
    {
        $stmt = $this->db->prepare("
            SELECT * FROM checkin_preferences
            WHERE passenger_id = ?
        ");

        $stmt->execute([$passengerId]);
        $preferences = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$preferences) {
            // Create default preferences
            $stmt = $this->db->prepare("
                INSERT INTO checkin_preferences (passenger_id)
                VALUES (?)
            ");
            $stmt->execute([$passengerId]);

            return [
                'passenger_id' => $passengerId,
                'preferred_language' => 'en',
                'accessibility_needs' => '{}',
                'biometric_consent' => false,
                'notification_preferences' => '{}',
                'seat_preferences' => '{}',
                'service_preferences' => '{}'
            ];
        }

        return $preferences;
    }

    /**
     * Update check-in preferences
     */
    public function updateCheckinPreferences($passengerId, $preferences)
    {
        $stmt = $this->db->prepare("
            INSERT INTO checkin_preferences (
                passenger_id, preferred_language, accessibility_needs,
                biometric_consent, notification_preferences,
                seat_preferences, service_preferences, last_updated
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ON CONFLICT (passenger_id) DO UPDATE SET
                preferred_language = EXCLUDED.preferred_language,
                accessibility_needs = EXCLUDED.accessibility_needs,
                biometric_consent = EXCLUDED.biometric_consent,
                notification_preferences = EXCLUDED.notification_preferences,
                seat_preferences = EXCLUDED.seat_preferences,
                service_preferences = EXCLUDED.service_preferences,
                last_updated = NOW()
        ");

        $stmt->execute([
            $passengerId,
            $preferences['preferred_language'] ?? 'en',
            isset($preferences['accessibility_needs']) ? json_encode($preferences['accessibility_needs']) : '{}',
            $preferences['biometric_consent'] ?? false,
            isset($preferences['notification_preferences']) ? json_encode($preferences['notification_preferences']) : '{}',
            isset($preferences['seat_preferences']) ? json_encode($preferences['seat_preferences']) : '{}',
            isset($preferences['service_preferences']) ? json_encode($preferences['service_preferences']) : '{}'
        ]);

        return ['status' => 'success', 'message' => 'Preferences updated'];
    }

    /**
     * Get check-in analytics
     */
    public function getCheckinAnalytics($startDate, $endDate)
    {
        $stmt = $this->db->prepare("
            SELECT
                DATE(session_start) as date,
                COUNT(*) as total_sessions,
                COUNT(CASE WHEN checkin_status = 'completed' THEN 1 END) as completed_sessions,
                ROUND(AVG(total_duration)) as avg_duration,
                COUNT(DISTINCT kiosk_id) as kiosks_used
            FROM checkin_sessions
            WHERE DATE(session_start) BETWEEN ? AND ?
            GROUP BY DATE(session_start)
            ORDER BY date
        ");

        $stmt->execute([$startDate, $endDate]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Validate passenger information
     */
    private function validatePassenger($passengerId)
    {
        $stmt = $this->db->prepare("
            SELECT * FROM passengers WHERE passenger_id = ?
        ");

        $stmt->execute([$passengerId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Validate booking information
     */
    private function validateBooking($bookingId, $passengerId)
    {
        $stmt = $this->db->prepare("
            SELECT
                b.*,
                f.flight_number, f.departure_time, f.arrival_time,
                f.origin, f.destination
            FROM bookings b
            JOIN flights f ON b.flight_id = f.flight_id
            WHERE b.booking_id = ? AND b.passenger_id = ?
        ");

        $stmt->execute([$bookingId, $passengerId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Check if seat is available
     */
    private function isSeatAvailable($flightId, $seatNumber)
    {
        // This would check against the actual seat inventory
        // For now, simulate availability
        return rand(1, 10) > 2; // 80% availability
    }

    /**
     * Add passenger to queue
     */
    private function addToQueue($sessionId, $kioskId, $passengerId, $bookingId)
    {
        $queuePosition = $this->calculateQueuePosition($kioskId);

        $stmt = $this->db->prepare("
            INSERT INTO checkin_queue (
                kiosk_id, passenger_id, booking_id, queue_position,
                estimated_wait_time, priority_level
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $kioskId,
            $passengerId,
            $bookingId,
            $queuePosition,
            $this->estimateWaitTime($kioskId, $queuePosition),
            1 // Default priority
        ]);
    }

    /**
     * Calculate queue position
     */
    private function calculateQueuePosition($kioskId)
    {
        $stmt = $this->db->prepare("
            SELECT COALESCE(MAX(queue_position), 0) + 1
            FROM checkin_queue
            WHERE kiosk_id = ? AND queue_status = 'waiting'
        ");

        $stmt->execute([$kioskId]);
        return $stmt->fetchColumn();
    }

    /**
     * Estimate wait time
     */
    private function estimateWaitTime($kioskId, $queuePosition)
    {
        $stmt = $this->db->prepare("
            SELECT COALESCE(AVG(total_duration), 300) -- default 5 minutes
            FROM checkin_sessions
            WHERE kiosk_id = ?
            AND session_start >= NOW() - INTERVAL '1 hour'
            AND checkin_status = 'completed'
        ");

        $stmt->execute([$kioskId]);
        $avgSessionTime = $stmt->fetchColumn();

        return max(($queuePosition - 1) * $avgSessionTime, 60); // minimum 1 minute
    }

    /**
     * Get next check-in step
     */
    private function getNextStep($completedSteps)
    {
        $allSteps = [
            'identity_verification',
            'document_verification',
            'seat_selection',
            'service_selection',
            'payment_confirmation',
            'boarding_pass_generation'
        ];

        foreach ($allSteps as $step) {
            if (!in_array($step, $completedSteps)) {
                return $step;
            }
        }

        return 'completed';
    }

    /**
     * Get seat selection for session
     */
    private function getSeatSelection($sessionId)
    {
        $stmt = $this->db->prepare("
            SELECT * FROM seat_selections
            WHERE session_id = ?
            ORDER BY selection_time DESC
            LIMIT 1
        ");

        $stmt->execute([$sessionId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Update queue status
     */
    private function updateQueueStatus($kioskId, $passengerId, $status)
    {
        $stmt = $this->db->prepare("
            UPDATE checkin_queue
            SET queue_status = ?, processing_end_time = NOW()
            WHERE kiosk_id = ? AND passenger_id = ? AND queue_status = 'processing'
        ");

        $stmt->execute([$status, $kioskId, $passengerId]);
    }

    /**
     * Generate QR code for boarding pass
     */
    private function generateQRCode($passId)
    {
        // This would integrate with a QR code generation library
        // For now, we'll use a simple text-based approach
        $qrData = "BOARDING_PASS_{$passId}_" . time();

        $stmt = $this->db->prepare("
            UPDATE digital_boarding_passes
            SET qr_code_data = ?, barcode_data = ?
            WHERE pass_id = ?
        ");

        $stmt->execute([$qrData, $qrData, $passId]);
    }

    /**
     * Encrypt biometric data
     */
    private function encryptBiometricData($data)
    {
        // This should use proper encryption in production
        // For now, return as-is
        return json_encode($data);
    }

    /**
     * Get kiosk performance metrics
     */
    public function getKioskPerformance($kioskId, $startDate, $endDate)
    {
        $stmt = $this->db->prepare("
            SELECT
                DATE(session_start) as date,
                COUNT(*) as total_sessions,
                COUNT(CASE WHEN checkin_status = 'completed' THEN 1 END) as completed_sessions,
                ROUND(AVG(total_duration)) as avg_duration,
                COUNT(CASE WHEN total_duration > 600 THEN 1 END) as long_sessions
            FROM checkin_sessions
            WHERE kiosk_id = ? AND DATE(session_start) BETWEEN ? AND ?
            GROUP BY DATE(session_start)
            ORDER BY date
        ");

        $stmt->execute([$kioskId, $startDate, $endDate]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
