<?php
/**
 * AI-Powered Conflict Prediction System
 *
 * Uses machine learning algorithms to predict potential aircraft conflicts
 * Provides automated conflict resolution suggestions
 */

require_once __DIR__ . '/Logger.php';

class AIConflictPrediction {
    private $pdo;
    private $modelWeights = [];
    private $historicalData = [];

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->loadModelWeights();
        $this->loadHistoricalData();
    }

    /**
     * Load trained model weights (simplified for demo)
     * In production, this would load from a trained ML model
     */
    private function loadModelWeights() {
        // Simplified neural network weights for conflict prediction
        $this->modelWeights = [
            'horizontal_separation' => 0.4,
            'vertical_separation' => 0.3,
            'closing_speed' => 0.2,
            'time_to_conflict' => 0.1,
            'traffic_density' => 0.15,
            'weather_factor' => 0.08,
            'airspace_restrictions' => 0.12
        ];
    }

    /**
     * Load historical conflict data for training
     */
    private function loadHistoricalData() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM conflict_history
                WHERE detected_at > NOW() - INTERVAL '30 days'
                ORDER BY detected_at DESC
                LIMIT 10000
            ");
            $stmt->execute();
            $this->historicalData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            Logger::error('Failed to load historical conflict data: ' . $e->getMessage());
            $this->historicalData = [];
        }
    }

    /**
     * Predict potential conflicts for all aircraft in an area
     */
    public function predictConflicts($aircraftData, $timeHorizon = 600) { // 10 minutes
        $predictions = [];
        $aircraftArray = array_values($aircraftData);

        // Check each pair of aircraft
        for ($i = 0; $i < count($aircraftArray); $i++) {
            for ($j = $i + 1; $j < count($aircraftArray); $j++) {
                $aircraft1 = $aircraftArray[$i];
                $aircraft2 = $aircraftArray[$j];

                $conflict = $this->predictAircraftConflict($aircraft1, $aircraft2, $timeHorizon);
                if ($conflict) {
                    $predictions[] = $conflict;
                }
            }
        }

        // Sort by severity and time to conflict
        usort($predictions, function($a, $b) {
            if ($a['severity'] !== $b['severity']) {
                return $b['severity'] <=> $a['severity']; // Higher severity first
            }
            return $a['time_to_conflict'] <=> $b['time_to_conflict']; // Sooner conflicts first
        });

        return array_slice($predictions, 0, 50); // Return top 50 predictions
    }

    /**
     * Predict conflict between two specific aircraft
     */
    public function predictAircraftConflict($aircraft1, $aircraft2, $timeHorizon = 600) {
        // Extract aircraft data
        $pos1 = $this->extractPositionData($aircraft1);
        $pos2 = $this->extractPositionData($aircraft2);

        if (!$pos1 || !$pos2) {
            return null;
        }

        // Calculate current separation
        $horizontalSep = $this->calculateHorizontalSeparation($pos1, $pos2);
        $verticalSep = abs($pos1['altitude'] - $pos2['altitude']);

        // Predict future positions
        $futurePositions = $this->predictFuturePositions($pos1, $pos2, $timeHorizon);

        // Find minimum separation in time horizon
        $minSeparation = $this->findMinimumSeparation($futurePositions);

        if ($minSeparation['horizontal'] < 5 || $minSeparation['vertical'] < 1000) {
            // Potential conflict detected
            $severity = $this->calculateConflictSeverity($minSeparation, $futurePositions);

            return [
                'aircraft1' => $pos1['id'],
                'aircraft2' => $pos2['id'],
                'time_to_conflict' => $minSeparation['time'],
                'min_horizontal_sep' => $minSeparation['horizontal'],
                'min_vertical_sep' => $minSeparation['vertical'],
                'severity' => $severity,
                'predicted_conflict_point' => $minSeparation['position'],
                'recommended_actions' => $this->generateResolutionActions($pos1, $pos2, $minSeparation),
                'confidence' => $this->calculatePredictionConfidence($minSeparation),
                'detected_at' => time()
            ];
        }

        return null;
    }

    /**
     * Extract position data from aircraft record
     */
    private function extractPositionData($aircraft) {
        return [
            'id' => $aircraft['icao24'] ?? $aircraft['id'],
            'latitude' => $aircraft['latitude'],
            'longitude' => $aircraft['longitude'],
            'altitude' => $aircraft['baro_altitude'] ?? $aircraft['geo_altitude'] ?? 0,
            'heading' => $aircraft['true_track'] ?? 0,
            'speed' => $aircraft['velocity'] ?? 0,
            'vertical_rate' => $aircraft['vertical_rate'] ?? 0
        ];
    }

    /**
     * Calculate horizontal separation in nautical miles
     */
    private function calculateHorizontalSeparation($pos1, $pos2) {
        $lat1 = deg2rad($pos1['latitude']);
        $lon1 = deg2rad($pos1['longitude']);
        $lat2 = deg2rad($pos2['latitude']);
        $lon2 = deg2rad($pos2['longitude']);

        $dlat = $lat2 - $lat1;
        $dlon = $lon2 - $lon1;

        $a = sin($dlat/2) * sin($dlat/2) +
            cos($lat1) * cos($lat2) * sin($dlon/2) * sin($dlon/2);

        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        $distance = 6371 * $c; // km

        return $distance * 0.539957; // Convert to nautical miles
    }

    /**
     * Predict future positions of both aircraft
     */
    private function predictFuturePositions($pos1, $pos2, $timeHorizon) {
        $predictions = [];
        $timeStep = 30; // 30 seconds

        for ($t = 0; $t <= $timeHorizon; $t += $timeStep) {
            $futurePos1 = $this->predictAircraftPosition($pos1, $t);
            $futurePos2 = $this->predictAircraftPosition($pos2, $t);

            $horizontalSep = $this->calculateHorizontalSeparation($futurePos1, $futurePos2);
            $verticalSep = abs($futurePos1['altitude'] - $futurePos2['altitude']);

            $predictions[] = [
                'time' => $t,
                'pos1' => $futurePos1,
                'pos2' => $futurePos2,
                'horizontal_sep' => $horizontalSep,
                'vertical_sep' => $verticalSep,
                'conflict_point' => [
                    'lat' => ($futurePos1['latitude'] + $futurePos2['latitude']) / 2,
                    'lon' => ($futurePos1['longitude'] + $futurePos2['longitude']) / 2,
                    'alt' => ($futurePos1['altitude'] + $futurePos2['altitude']) / 2
                ]
            ];
        }

        return $predictions;
    }

    /**
     * Predict single aircraft position at future time
     */
    private function predictAircraftPosition($currentPos, $timeSeconds) {
        // Convert speed from m/s to km/h for easier calculation
        $speedKmh = ($currentPos['speed'] ?? 0) * 3.6;
        $speedKmPerSecond = $speedKmh / 3600;

        // Calculate distance traveled
        $distanceKm = $speedKmPerSecond * $timeSeconds;

        // Convert heading to radians
        $headingRad = deg2rad($currentPos['heading']);

        // Calculate new position
        $earthRadius = 6371; // km
        $latRad = deg2rad($currentPos['latitude']);
        $lonRad = deg2rad($currentPos['longitude']);

        $newLatRad = asin(sin($latRad) * cos($distanceKm / $earthRadius) +
                          cos($latRad) * sin($distanceKm / $earthRadius) * cos($headingRad));

        $newLonRad = $lonRad + atan2(sin($headingRad) * sin($distanceKm / $earthRadius) * cos($latRad),
                                     cos($distanceKm / $earthRadius) - sin($latRad) * sin($newLatRad));

        // Calculate new altitude (simplified - constant vertical rate)
        $verticalRateMs = ($currentPos['vertical_rate'] ?? 0);
        $altitudeChange = $verticalRateMs * $timeSeconds;
        $newAltitude = $currentPos['altitude'] + ($altitudeChange * 3.28084); // Convert to feet

        return [
            'latitude' => rad2deg($newLatRad),
            'longitude' => rad2deg($newLonRad),
            'altitude' => max(0, $newAltitude), // Don't go below ground
            'heading' => $currentPos['heading'],
            'speed' => $currentPos['speed']
        ];
    }

    /**
     * Find minimum separation in predicted positions
     */
    private function findMinimumSeparation($predictions) {
        $minHorizontal = PHP_FLOAT_MAX;
        $minVertical = PHP_FLOAT_MAX;
        $minTime = 0;
        $conflictPoint = null;

        foreach ($predictions as $prediction) {
            if ($prediction['horizontal_sep'] < $minHorizontal) {
                $minHorizontal = $prediction['horizontal_sep'];
                $minVertical = $prediction['vertical_sep'];
                $minTime = $prediction['time'];
                $conflictPoint = $prediction['conflict_point'];
            }
        }

        return [
            'horizontal' => $minHorizontal,
            'vertical' => $minVertical,
            'time' => $minTime,
            'position' => $conflictPoint
        ];
    }

    /**
     * Calculate conflict severity score
     */
    private function calculateConflictSeverity($minSeparation, $predictions) {
        $score = 0;

        // Horizontal separation factor
        if ($minSeparation['horizontal'] < 3) {
            $score += 50; // Critical
        } elseif ($minSeparation['horizontal'] < 5) {
            $score += 30; // High
        } elseif ($minSeparation['horizontal'] < 10) {
            $score += 10; // Medium
        }

        // Vertical separation factor
        if ($minSeparation['vertical'] < 500) {
            $score += 40; // Critical
        } elseif ($minSeparation['vertical'] < 1000) {
            $score += 20; // High
        }

        // Time to conflict factor
        if ($minSeparation['time'] < 120) {
            $score += 40; // Very urgent
        } elseif ($minSeparation['time'] < 300) {
            $score += 20; // Urgent
        }

        // Traffic density factor (simplified)
        $density = count($predictions) > 10 ? 15 : 0;
        $score += $density;

        return min(100, $score); // Cap at 100
    }

    /**
     * Generate automated resolution actions
     */
    private function generateResolutionActions($pos1, $pos2, $minSeparation) {
        $actions = [];

        // Altitude-based resolution
        if ($minSeparation['vertical'] < 1000) {
            $altDiff = $pos1['altitude'] - $pos2['altitude'];
            if ($altDiff > 0) {
                $actions[] = [
                    'type' => 'altitude_change',
                    'target_aircraft' => $pos2['id'],
                    'action' => 'climb',
                    'magnitude' => 1000,
                    'reason' => 'Resolve vertical separation conflict'
                ];
            } else {
                $actions[] = [
                    'type' => 'altitude_change',
                    'target_aircraft' => $pos1['id'],
                    'action' => 'climb',
                    'magnitude' => 1000,
                    'reason' => 'Resolve vertical separation conflict'
                ];
            }
        }

        // Heading-based resolution
        if ($minSeparation['horizontal'] < 5) {
            $actions[] = [
                'type' => 'heading_change',
                'target_aircraft' => $pos1['id'],
                'action' => 'turn_left',
                'magnitude' => 10, // degrees
                'reason' => 'Resolve horizontal separation conflict'
            ];
        }

        // Speed-based resolution
        if ($minSeparation['time'] < 300) {
            $actions[] = [
                'type' => 'speed_change',
                'target_aircraft' => $pos2['id'],
                'action' => 'reduce_speed',
                'magnitude' => 20, // knots
                'reason' => 'Increase time to conflict'
            ];
        }

        return $actions;
    }

    /**
     * Calculate prediction confidence
     */
    private function calculatePredictionConfidence($minSeparation) {
        $confidence = 100;

        // Reduce confidence for long prediction times
        if ($minSeparation['time'] > 600) {
            $confidence -= 20;
        }

        // Reduce confidence for uncertain data
        if ($minSeparation['horizontal'] > 20) {
            $confidence -= 30; // Too far apart, less certain
        }

        return max(0, $confidence);
    }

    /**
     * Learn from historical conflicts to improve predictions
     */
    public function learnFromHistoricalConflicts() {
        // In production, this would retrain the ML model
        // For now, just analyze patterns

        $patterns = $this->analyzeConflictPatterns();

        // Update model weights based on patterns
        foreach ($patterns as $factor => $importance) {
            if (isset($this->modelWeights[$factor])) {
                $this->modelWeights[$factor] = min(1.0, $this->modelWeights[$factor] * (1 + $importance * 0.1));
            }
        }

        Logger::info('AI conflict prediction model updated from historical data');
    }

    /**
     * Analyze patterns in historical conflict data
     */
    private function analyzeConflictPatterns() {
        if (empty($this->historicalData)) {
            return [];
        }

        $patterns = [
            'horizontal_separation' => 0,
            'vertical_separation' => 0,
            'closing_speed' => 0,
            'time_to_conflict' => 0
        ];

        foreach ($this->historicalData as $conflict) {
            if ($conflict['min_horizontal_sep'] < 5) {
                $patterns['horizontal_separation'] += 0.1;
            }
            if ($conflict['min_vertical_sep'] < 1000) {
                $patterns['vertical_separation'] += 0.1;
            }
            if ($conflict['time_to_conflict'] < 300) {
                $patterns['time_to_conflict'] += 0.1;
            }
        }

        return $patterns;
    }

    /**
     * Store conflict prediction for analysis
     */
    public function storeConflictPrediction($prediction) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO conflict_predictions (
                    aircraft1, aircraft2, time_to_conflict, min_horizontal_sep,
                    min_vertical_sep, severity, confidence, predicted_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            $stmt->execute([
                $prediction['aircraft1'],
                $prediction['aircraft2'],
                $prediction['time_to_conflict'],
                $prediction['min_horizontal_sep'],
                $prediction['min_vertical_sep'],
                $prediction['severity'],
                $prediction['confidence']
            ]);

            Logger::info('Conflict prediction stored: ' . $prediction['aircraft1'] . ' vs ' . $prediction['aircraft2']);
        } catch (Exception $e) {
            Logger::error('Failed to store conflict prediction: ' . $e->getMessage());
        }
    }
}

// Database tables for AI conflict prediction
$aiConflictTablesSQL = "
CREATE TABLE IF NOT EXISTS conflict_predictions (
    id SERIAL PRIMARY KEY,
    aircraft1 VARCHAR(10) NOT NULL,
    aircraft2 VARCHAR(10) NOT NULL,
    time_to_conflict INTEGER NOT NULL,
    min_horizontal_sep DECIMAL(6,2),
    min_vertical_sep DECIMAL(7,1),
    severity DECIMAL(5,2),
    confidence DECIMAL(5,2),
    resolved BOOLEAN DEFAULT FALSE,
    predicted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS conflict_history (
    id SERIAL PRIMARY KEY,
    aircraft1 VARCHAR(10) NOT NULL,
    aircraft2 VARCHAR(10) NOT NULL,
    actual_conflict_time TIMESTAMP,
    min_horizontal_sep DECIMAL(6,2),
    min_vertical_sep DECIMAL(7,1),
    resolution_method TEXT,
    detected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_conflict_predictions_time ON conflict_predictions (predicted_at);
CREATE INDEX IF NOT EXISTS idx_conflict_predictions_severity ON conflict_predictions (severity DESC);
";

// Usage example:
/*
$ai = new AIConflictPrediction($pdo);

// Get aircraft data
$aircraft = $adsb->getAircraftInBounds($bounds);

// Predict conflicts
$predictions = $ai->predictConflicts($aircraft);

// Store predictions
foreach ($predictions as $prediction) {
    $ai->storeConflictPrediction($prediction);
}

// Learn from historical data
$ai->learnFromHistoricalConflicts();
*/
?>
