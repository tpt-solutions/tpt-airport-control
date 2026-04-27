<?php
require_once __DIR__ . '/Logger.php';

use Ratchet\ConnectionInterface;
use TPT\FlightControl\Logger;

class FlightBroadcastService {
    protected $clients;
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->clients = new \SplObjectStorage;
    }

    public function addClient(ConnectionInterface $client) {
        $this->clients->offsetSet($client);
    }

    public function removeClient(ConnectionInterface $client) {
        $this->clients->offsetUnset($client);
    }

    public function broadcastFlightUpdate($flightId, $updateData) {
        $message = json_encode([
            'type' => 'flight_update',
            'flight_id' => $flightId,
            'update' => $updateData,
            'timestamp' => time()
        ]);

        $broadcastCount = 0;
        foreach ($this->clients as $client) {
            // Check if client is subscribed to this flight
            if (isset($client->flightSubscription)) {
                $filters = $client->flightSubscription;

                // Apply filters if any
                $sendUpdate = $this->shouldSendFlightUpdate($updateData, $filters);

                if ($sendUpdate) {
                    $client->send($message);
                    $broadcastCount++;
                }
            }
        }

        Logger::debug("Flight update broadcasted to {$broadcastCount} clients: flight {$flightId}");
        return $broadcastCount;
    }

    public function broadcastFlightStatusChange($flightId, $oldStatus, $newStatus, $additionalData = []) {
        $updateData = array_merge([
            'status' => $newStatus,
            'previous_status' => $oldStatus,
            'status_changed_at' => time()
        ], $additionalData);

        return $this->broadcastFlightUpdate($flightId, $updateData);
    }

    public function broadcastFlightDelay($flightId, $delayMinutes, $reason = null) {
        $updateData = [
            'delay_minutes' => $delayMinutes,
            'delay_reason' => $reason,
            'delayed_at' => time()
        ];

        // Update scheduled times if we have them
        if (isset($updateData['scheduled_departure'])) {
            $updateData['estimated_departure'] = $updateData['scheduled_departure'] + ($delayMinutes * 60);
        }

        return $this->broadcastFlightUpdate($flightId, $updateData);
    }

    public function broadcastFlightCancellation($flightId, $reason = null) {
        $updateData = [
            'status' => 'cancelled',
            'cancellation_reason' => $reason,
            'cancelled_at' => time()
        ];

        return $this->broadcastFlightUpdate($flightId, $updateData);
    }

    public function broadcastGateChange($flightId, $newGate, $oldGate = null) {
        $updateData = [
            'gate' => $newGate,
            'previous_gate' => $oldGate,
            'gate_changed_at' => time()
        ];

        return $this->broadcastFlightUpdate($flightId, $updateData);
    }

    public function broadcastDepartureUpdate($flightId, $actualDepartureTime) {
        $updateData = [
            'actual_departure' => $actualDepartureTime,
            'departed_at' => time()
        ];

        return $this->broadcastFlightUpdate($flightId, $updateData);
    }

    public function broadcastArrivalUpdate($flightId, $actualArrivalTime) {
        $updateData = [
            'actual_arrival' => $actualArrivalTime,
            'arrived_at' => time()
        ];

        return $this->broadcastFlightUpdate($flightId, $updateData);
    }

    public function broadcastBulkFlightUpdates($flightUpdates) {
        $totalBroadcasts = 0;

        foreach ($flightUpdates as $update) {
            $flightId = $update['flight_id'];
            unset($update['flight_id']);
            $totalBroadcasts += $this->broadcastFlightUpdate($flightId, $update);
        }

        Logger::info("Bulk flight updates broadcasted to {$totalBroadcasts} total clients");
        return $totalBroadcasts;
    }

    public function broadcastAnnouncement($message, $level = 'info', $targetFilters = null) {
        $data = json_encode([
            'type' => 'announcement',
            'level' => $level,
            'message' => $message,
            'timestamp' => time()
        ]);

        $broadcastCount = 0;
        foreach ($this->clients as $client) {
            // Apply target filters if specified
            if ($targetFilters && !$this->matchesFilters($client, $targetFilters)) {
                continue;
            }

            $client->send($data);
            $broadcastCount++;
        }

        Logger::info("Announcement broadcasted to {$broadcastCount} clients: {$message}");
        return $broadcastCount;
    }

    public function broadcastWeatherAlert($airportCode, $alertType, $message, $severity = 'moderate') {
        $data = json_encode([
            'type' => 'weather_alert',
            'airport_code' => $airportCode,
            'alert_type' => $alertType,
            'severity' => $severity,
            'message' => $message,
            'timestamp' => time()
        ]);

        $broadcastCount = 0;
        foreach ($this->clients as $client) {
            // Send to clients subscribed to weather updates for this airport
            if (isset($client->weatherSubscription)) {
                $filters = $client->weatherSubscription;

                if (!isset($filters['airport']) || $filters['airport'] === $airportCode) {
                    $client->send($data);
                    $broadcastCount++;
                }
            }
        }

        Logger::info("Weather alert broadcasted to {$broadcastCount} clients for airport {$airportCode}");
        return $broadcastCount;
    }

    public function broadcastSystemStatus($status, $message = null) {
        $data = json_encode([
            'type' => 'system_status',
            'status' => $status,
            'message' => $message,
            'timestamp' => time()
        ]);

        $broadcastCount = 0;
        foreach ($this->clients as $client) {
            $client->send($data);
            $broadcastCount++;
        }

        Logger::info("System status broadcasted to {$broadcastCount} clients: {$status}");
        return $broadcastCount;
    }

    public function getSubscriptionStats() {
        $stats = [
            'total_clients' => count($this->clients),
            'flight_subscriptions' => 0,
            'booking_subscriptions' => 0,
            'weather_subscriptions' => 0,
            'authenticated_clients' => 0
        ];

        foreach ($this->clients as $client) {
            if (isset($client->flightSubscription)) {
                $stats['flight_subscriptions']++;
            }
            if (isset($client->bookingSubscription)) {
                $stats['booking_subscriptions']++;
            }
            if (isset($client->weatherSubscription)) {
                $stats['weather_subscriptions']++;
            }
            if (isset($client->authenticated) && $client->authenticated) {
                $stats['authenticated_clients']++;
            }
        }

        return $stats;
    }

    private function shouldSendFlightUpdate($updateData, $filters) {
        // If no filters, send to all subscribed clients
        if (empty($filters)) {
            return true;
        }

        // Apply status filter
        if (isset($filters['status']) && isset($updateData['status'])) {
            if ($updateData['status'] !== $filters['status']) {
                return false;
            }
        }

        // Apply origin filter
        if (isset($filters['origin']) && isset($updateData['origin'])) {
            if ($updateData['origin'] !== $filters['origin']) {
                return false;
            }
        }

        // Apply destination filter
        if (isset($filters['destination']) && isset($updateData['destination'])) {
            if ($updateData['destination'] !== $filters['destination']) {
                return false;
            }
        }

        // Apply airline filter
        if (isset($filters['airline']) && isset($updateData['airline_code'])) {
            if ($updateData['airline_code'] !== $filters['airline']) {
                return false;
            }
        }

        return true;
    }

    private function matchesFilters($client, $targetFilters) {
        // Check user role filter
        if (isset($targetFilters['role']) && isset($client->userRole)) {
            if ($client->userRole !== $targetFilters['role']) {
                return false;
            }
        }

        // Check subscription type filter
        if (isset($targetFilters['subscription_type'])) {
            $hasSubscription = false;
            switch ($targetFilters['subscription_type']) {
                case 'flights':
                    $hasSubscription = isset($client->flightSubscription);
                    break;
                case 'bookings':
                    $hasSubscription = isset($client->bookingSubscription);
                    break;
                case 'weather':
                    $hasSubscription = isset($client->weatherSubscription);
                    break;
            }

            if (!$hasSubscription) {
                return false;
            }
        }

        return true;
    }

    public function notifyFlightCrew($flightId, $message, $crewType = null) {
        // This would integrate with crew management system
        // For now, broadcast to all authenticated clients
        $data = json_encode([
            'type' => 'crew_notification',
            'flight_id' => $flightId,
            'crew_type' => $crewType,
            'message' => $message,
            'timestamp' => time()
        ]);

        $broadcastCount = 0;
        foreach ($this->clients as $client) {
            if (isset($client->authenticated) && $client->authenticated) {
                $client->send($data);
                $broadcastCount++;
            }
        }

        Logger::info("Crew notification sent to {$broadcastCount} authenticated clients for flight {$flightId}");
        return $broadcastCount;
    }
}
?>
