<?php

/**
 * Sustainability Module API
 *
 * RESTful API for environmental monitoring and sustainability tracking
 */

require_once '../src/ApiResponse.php';
require_once '../models/Sustainability.php';
require_once '../src/Auth.php';

// Initialize components
$apiResponse = new ApiResponse();
$sustainabilityManager = new Sustainability();
$auth = new Auth();

// Get request method and path
$method = $_SERVER['REQUEST_METHOD'];
$request = $_SERVER['REQUEST_URI'];
$path = parse_url($request, PHP_URL_PATH);

// Remove base path
$path = str_replace('/api/sustainability', '', $path);
$path = str_replace('/backend/api/sustainability', '', $path);

// Get path segments
$pathSegments = array_filter(explode('/', trim($path, '/')));
$resource = $pathSegments[0] ?? null;
$action = $pathSegments[1] ?? null;

// Get user from JWT token
$user = null;
$headers = getallheaders();
if (isset($headers['Authorization'])) {
    $token = str_replace('Bearer ', '', $headers['Authorization']);
    try {
        $user = $auth->validateToken($token);
    } catch (Exception $e) {
        $apiResponse->error('Unauthorized', 401);
        exit;
    }
}

try {
    switch ($method) {
        case 'GET':
            handleGetRequest($resource, $action, $sustainabilityManager, $apiResponse);
            break;

        case 'POST':
            handlePostRequest($resource, $action, $sustainabilityManager, $user, $apiResponse);
            break;

        case 'PUT':
            handlePutRequest($resource, $action, $sustainabilityManager, $user, $apiResponse);
            break;

        default:
            $apiResponse->error('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log("Sustainability API Error: " . $e->getMessage());
    error_log('API error: ' . $e->getMessage());
    $apiResponse->error('An internal error occurred', 500);
}

/**
 * Handle GET requests
 */
function handleGetRequest($resource, $action, $sustainabilityManager, $apiResponse)
{
    switch ($resource) {
        case null:
        case 'dashboard':
            // Get dashboard data
            $dashboardData = $sustainabilityManager->getDashboardData();
            $apiResponse->success($dashboardData);
            break;

        case 'emissions':
            if ($action === 'report') {
                // Get emissions report
                $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
                $endDate = $_GET['end_date'] ?? date('Y-m-d');
                $groupBy = $_GET['group_by'] ?? 'day';

                $report = $sustainabilityManager->getEmissionsReport($startDate, $endDate, $groupBy);
                $apiResponse->success($report);
            } else {
                $apiResponse->error('Invalid action', 400);
            }
            break;

        case 'noise':
            if ($action === 'report') {
                // Get noise report
                $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
                $endDate = $_GET['end_date'] ?? date('Y-m-d');

                $report = $sustainabilityManager->getNoiseReport($startDate, $endDate);
                $apiResponse->success($report);
            } else {
                $apiResponse->error('Invalid action', 400);
            }
            break;

        case 'energy':
            if ($action === 'report') {
                // Get energy report
                $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
                $endDate = $_GET['end_date'] ?? date('Y-m-d');

                $report = $sustainabilityManager->getEnergyReport($startDate, $endDate);
                $apiResponse->success($report);
            } elseif ($action === 'systems') {
                // Get green energy systems
                $status = $_GET['status'] ?? null;
                $systems = $sustainabilityManager->getGreenEnergySystems($status);
                $apiResponse->success($systems);
            } else {
                $apiResponse->error('Invalid action', 400);
            }
            break;

        case 'kpis':
            // Get sustainability KPIs
            $kpis = $sustainabilityManager->getKPIs();
            $apiResponse->success($kpis);
            break;

        case 'compliance':
            // Get compliance records
            $limit = $_GET['limit'] ?? 50;
            $records = $sustainabilityManager->getComplianceRecords($limit);
            $apiResponse->success($records);
            break;

        case 'sensors':
            // Get environmental sensors
            $status = $_GET['status'] ?? null;
            $sensors = $sustainabilityManager->getSensors($status);
            $apiResponse->success($sensors);
            break;

        case 'alerts':
            // Get sustainability alerts
            $alerts = $sustainabilityManager->getSustainabilityAlerts();
            $apiResponse->success($alerts);
            break;

        case 'carbon-offset':
            // Get carbon offset calculation
            $offset = $sustainabilityManager->calculateCarbonOffset();
            $apiResponse->success($offset);
            break;

        case 'reports':
            if ($action === 'environmental-impact') {
                // Get environmental impact report
                $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-90 days'));
                $endDate = $_GET['end_date'] ?? date('Y-m-d');
                $report = getEnvironmentalImpactReport($sustainabilityManager, $startDate, $endDate);
                $apiResponse->success($report);
            } elseif ($action === 'regulatory-compliance') {
                // Get regulatory compliance report
                $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-365 days'));
                $endDate = $_GET['end_date'] ?? date('Y-m-d');
                $report = getRegulatoryComplianceReport($sustainabilityManager, $startDate, $endDate);
                $apiResponse->success($report);
            } elseif ($action === 'sustainability-score') {
                // Get sustainability score report
                $report = getSustainabilityScoreReport($sustainabilityManager);
                $apiResponse->success($report);
            } else {
                $apiResponse->error('Invalid report type', 400);
            }
            break;

        default:
            $apiResponse->error('Resource not found', 404);
    }
}

/**
 * Handle POST requests
 */
function handlePostRequest($resource, $action, $sustainabilityManager, $user, $apiResponse)
{
    // Check if user has appropriate permissions
    if (!$user || !in_array($user['role'], ['super_admin', 'admin', 'operator'])) {
        $apiResponse->error('Insufficient permissions', 403);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    switch ($resource) {
        case 'emissions':
            // Record flight emissions
            if (!isset($input['flight_id']) || !isset($input['fuel_consumed'])) {
                $apiResponse->error('Flight ID and fuel consumption required', 400);
                return;
            }

            $result = $sustainabilityManager->recordFlightEmissions($input);
            $apiResponse->success($result);
            break;

        case 'noise':
            // Record noise data
            if (!isset($input['sensor_location']) || !isset($input['noise_level'])) {
                $apiResponse->error('Sensor location and noise level required', 400);
                return;
            }

            $result = $sustainabilityManager->recordNoiseData($input);
            $apiResponse->success($result);
            break;

        case 'energy':
            // Record energy consumption
            if (!isset($input['facility_type']) || !isset($input['consumption_kwh'])) {
                $apiResponse->error('Facility type and consumption required', 400);
                return;
            }

            $result = $sustainabilityManager->recordEnergyConsumption($input);
            $apiResponse->success($result);
            break;

        case 'waste':
            // Record waste data
            if (!isset($input['waste_type']) || !isset($input['weight_kg'])) {
                $apiResponse->error('Waste type and weight required', 400);
                return;
            }

            $result = $sustainabilityManager->recordWasteData($input);
            $apiResponse->success($result);
            break;

        case 'water':
            // Record water data
            if (!isset($input['facility_type']) || !isset($input['consumption_liters'])) {
                $apiResponse->error('Facility type and consumption required', 400);
                return;
            }

            $result = $sustainabilityManager->recordWaterData($input);
            $apiResponse->success($result);
            break;

        case 'compliance':
            // Add compliance record
            if (!isset($input['regulation_name']) || !isset($input['next_inspection_date'])) {
                $apiResponse->error('Regulation name and next inspection date required', 400);
                return;
            }

            $result = $sustainabilityManager->addComplianceRecord($input);
            $apiResponse->success($result);
            break;

        case 'sensors':
            if ($action === 'reading') {
                // Record sensor reading
                if (!isset($input['sensor_id']) || !isset($input['parameter_name']) || !isset($input['parameter_value'])) {
                    $apiResponse->error('Sensor ID, parameter name, and value required', 400);
                    return;
                }

                $result = $sustainabilityManager->recordSensorReading($input);
                $apiResponse->success($result);
            } else {
                $apiResponse->error('Invalid action', 400);
            }
            break;

        default:
            $apiResponse->error('Resource not found', 404);
    }
}

/**
 * Handle PUT requests
 */
function handlePutRequest($resource, $action, $sustainabilityManager, $user, $apiResponse)
{
    // Check if user has admin permissions
    if (!$user || !in_array($user['role'], ['super_admin', 'admin'])) {
        $apiResponse->error('Insufficient permissions', 403);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    switch ($resource) {
        case 'kpis':
            if ($action && is_numeric($action)) {
                // Update KPI status
                if (!isset($input['status'])) {
                    $apiResponse->error('Status required', 400);
                    return;
                }

                $result = $sustainabilityManager->updateKPIStatus($action, $input['status']);
                $apiResponse->success($result);
            } else {
                $apiResponse->error('KPI ID required', 400);
            }
            break;

        case 'energy':
            if ($action === 'output' && isset($input['system_id']) && isset($input['output'])) {
                // Update energy system output
                $result = $sustainabilityManager->updateEnergyOutput($input['system_id'], $input['output']);
                $apiResponse->success($result);
            } else {
                $apiResponse->error('System ID and output required', 400);
            }
            break;

        default:
            $apiResponse->error('Resource not found', 404);
    }
}

/**
 * Validate date range parameters
 */
function validateDateRange($startDate, $endDate)
{
    $start = strtotime($startDate);
    $end = strtotime($endDate);

    if (!$start || !$end) {
        throw new Exception('Invalid date format');
    }

    if ($start > $end) {
        throw new Exception('Start date cannot be after end date');
    }

    // Limit date range to prevent excessive queries
    $maxDays = 365;
    if (($end - $start) / (60 * 60 * 24) > $maxDays) {
        throw new Exception("Date range cannot exceed $maxDays days");
    }

    return true;
}

/**
 * Format sustainability report data
 */
function formatSustainabilityReport($data, $reportType)
{
    $formatted = [
        'report_type' => $reportType,
        'generated_at' => date('Y-m-d H:i:s'),
        'data' => $data
    ];

    // Add summary statistics based on report type
    switch ($reportType) {
        case 'emissions':
            $totalCO2 = array_sum(array_column($data, 'total_co2'));
            $totalFlights = array_sum(array_column($data, 'flight_count'));
            $formatted['summary'] = [
                'total_co2_emitted' => round($totalCO2, 2),
                'total_flights' => $totalFlights,
                'average_co2_per_flight' => $totalFlights > 0 ? round($totalCO2 / $totalFlights, 2) : 0
            ];
            break;

        case 'energy':
            $totalConsumption = array_sum(array_column($data, 'total_consumption'));
            $totalCost = array_sum(array_column($data, 'total_cost'));
            $formatted['summary'] = [
                'total_energy_consumption' => round($totalConsumption, 2),
                'total_energy_cost' => round($totalCost, 2),
                'average_cost_per_kwh' => $totalConsumption > 0 ? round($totalCost / $totalConsumption, 4) : 0
            ];
            break;

        case 'noise':
            $avgNoise = array_sum(array_column($data, 'avg_noise')) / count($data);
            $maxNoise = max(array_column($data, 'max_noise'));
            $formatted['summary'] = [
                'average_noise_level' => round($avgNoise, 2),
                'maximum_noise_level' => round($maxNoise, 2),
                'monitoring_locations' => count(array_unique(array_column($data, 'sensor_location')))
            ];
            break;
    }

    return $formatted;
}

/**
 * Check if sustainability module is enabled
 */
function isSustainabilityEnabled()
{
    // This would typically check the modules table
    // For now, return true as we're implementing it
    return true;
}

/**
 * Get environmental impact report
 */
function getEnvironmentalImpactReport($sustainabilityManager, $startDate, $endDate)
{
    try {
        // Get database connection from sustainability manager
        $db = $sustainabilityManager->getDatabaseConnection();

        // Get carbon emissions data
        $stmt = $db->prepare("
            SELECT
                DATE_TRUNC('month', measurement_date) as period,
                SUM(co2_emitted) as total_co2,
                SUM(nox_emitted) as total_nox,
                COUNT(*) as flight_count,
                ROUND(AVG(co2_emitted), 2) as avg_co2_per_flight
            FROM carbon_emissions
            WHERE measurement_date BETWEEN ? AND ?
            GROUP BY DATE_TRUNC('month', measurement_date)
            ORDER BY period
        ");
        $stmt->execute([$startDate, $endDate]);
        $emissionsData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get energy consumption data
        $stmt = $db->prepare("
            SELECT
                DATE_TRUNC('month', consumption_date) as period,
                SUM(consumption_kwh) as total_energy,
                ROUND(AVG(carbon_intensity), 3) as avg_carbon_intensity,
                SUM(consumption_kwh * carbon_intensity) as total_carbon_equivalent
            FROM energy_consumption
            WHERE consumption_date BETWEEN ? AND ?
            GROUP BY DATE_TRUNC('month', consumption_date)
            ORDER BY period
        ");
        $stmt->execute([$startDate, $endDate]);
        $energyData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get waste management data
        $stmt = $db->prepare("
            SELECT
                DATE_TRUNC('month', collection_date) as period,
                SUM(weight_kg) as total_waste,
                SUM(CASE WHEN disposal_method IN ('recycling', 'composting') THEN weight_kg ELSE 0 END) as recycled_waste,
                ROUND(
                    CASE
                        WHEN SUM(weight_kg) > 0
                        THEN (SUM(CASE WHEN disposal_method IN ('recycling', 'composting') THEN weight_kg ELSE 0 END) * 100.0 / SUM(weight_kg))
                        ELSE 0
                    END, 2
                ) as recycling_rate
            FROM waste_management
            WHERE collection_date BETWEEN ? AND ?
            GROUP BY DATE_TRUNC('month', collection_date)
            ORDER BY period
        ");
        $stmt->execute([$startDate, $endDate]);
        $wasteData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get water conservation data
        $stmt = $db->prepare("
            SELECT
                DATE_TRUNC('month', measurement_date) as period,
                SUM(consumption_liters) as total_water,
                ROUND(AVG(recycled_percentage), 2) as avg_recycling_rate
            FROM water_conservation
            WHERE measurement_date BETWEEN ? AND ?
            GROUP BY DATE_TRUNC('month', measurement_date)
            ORDER BY period
        ");
        $stmt->execute([$startDate, $endDate]);
        $waterData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate overall impact metrics
        $totalCO2 = array_sum(array_column($emissionsData, 'total_co2')) + array_sum(array_column($energyData, 'total_carbon_equivalent'));
        $totalWaste = array_sum(array_column($wasteData, 'total_waste'));
        $avgRecyclingRate = count($wasteData) > 0 ? array_sum(array_column($wasteData, 'recycling_rate')) / count($wasteData) : 0;
        $totalWater = array_sum(array_column($waterData, 'total_water'));
        $avgWaterRecycling = count($waterData) > 0 ? array_sum(array_column($waterData, 'avg_recycling_rate')) / count($waterData) : 0;

        return [
            'report_type' => 'environmental_impact',
            'period' => ['start_date' => $startDate, 'end_date' => $endDate],
            'summary' => [
                'total_carbon_footprint_kg' => round($totalCO2, 2),
                'total_waste_kg' => round($totalWaste, 2),
                'waste_recycling_rate_percent' => round($avgRecyclingRate, 2),
                'total_water_consumption_liters' => round($totalWater, 2),
                'water_recycling_rate_percent' => round($avgWaterRecycling, 2),
                'carbon_intensity_trend' => calculateTrend($emissionsData, 'total_co2'),
                'waste_reduction_trend' => calculateTrend($wasteData, 'total_waste')
            ],
            'emissions_by_month' => $emissionsData,
            'energy_consumption_by_month' => $energyData,
            'waste_management_by_month' => $wasteData,
            'water_conservation_by_month' => $waterData,
            'generated_at' => date('c')
        ];

    } catch (Exception $e) {
        error_log("Environmental impact report error: " . $e->getMessage());
        return [
            'report_type' => 'environmental_impact',
            'period' => ['start_date' => $startDate, 'end_date' => $endDate],
            'error' => 'Failed to generate environmental impact report',
            'summary' => [
                'total_carbon_footprint_kg' => 0,
                'total_waste_kg' => 0,
                'waste_recycling_rate_percent' => 0,
                'total_water_consumption_liters' => 0,
                'water_recycling_rate_percent' => 0
            ],
            'data' => []
        ];
    }
}

/**
 * Get regulatory compliance report
 */
function getRegulatoryComplianceReport($sustainabilityManager, $startDate, $endDate)
{
    try {
        // Get database connection from sustainability manager
        $db = $sustainabilityManager->getDatabaseConnection();

        // Get compliance records
        $stmt = $db->prepare("
            SELECT
                regulation_name,
                regulation_body,
                compliance_status,
                inspection_date,
                next_inspection_date,
                findings,
                corrective_actions,
                fine_amount,
                compliance_officer
            FROM environmental_compliance
            WHERE inspection_date BETWEEN ? AND ?
            ORDER BY inspection_date DESC
        ");
        $stmt->execute([$startDate, $endDate]);
        $complianceRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get compliance status summary
        $stmt = $db->prepare("
            SELECT
                compliance_status,
                COUNT(*) as count
            FROM environmental_compliance
            WHERE inspection_date BETWEEN ? AND ?
            GROUP BY compliance_status
        ");
        $stmt->execute([$startDate, $endDate]);
        $statusSummary = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get upcoming inspections
        $stmt = $db->prepare("
            SELECT
                regulation_name,
                next_inspection_date,
                compliance_status,
                EXTRACT(days FROM (next_inspection_date - CURRENT_DATE)) as days_until_inspection
            FROM environmental_compliance
            WHERE next_inspection_date >= CURRENT_DATE
            AND next_inspection_date <= CURRENT_DATE + INTERVAL '90 days'
            ORDER BY next_inspection_date
        ");
        $stmt->execute();
        $upcomingInspections = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get fines and penalties
        $stmt = $db->prepare("
            SELECT
                regulation_name,
                SUM(fine_amount) as total_fines,
                COUNT(*) as violation_count
            FROM environmental_compliance
            WHERE inspection_date BETWEEN ? AND ?
            AND fine_amount > 0
            GROUP BY regulation_name
            ORDER BY total_fines DESC
        ");
        $stmt->execute([$startDate, $endDate]);
        $finesData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate compliance metrics
        $totalInspections = array_sum(array_column($statusSummary, 'count'));
        $compliantInspections = 0;
        foreach ($statusSummary as $status) {
            if (in_array($status['compliance_status'], ['compliant', 'conditional'])) {
                $compliantInspections += $status['count'];
            }
        }
        $complianceRate = $totalInspections > 0 ? round(($compliantInspections / $totalInspections) * 100, 2) : 0;
        $totalFines = array_sum(array_column($finesData, 'total_fines'));

        return [
            'report_type' => 'regulatory_compliance',
            'period' => ['start_date' => $startDate, 'end_date' => $endDate],
            'summary' => [
                'total_inspections' => $totalInspections,
                'compliant_inspections' => $compliantInspections,
                'compliance_rate_percent' => $complianceRate,
                'total_fines_amount' => round($totalFines, 2),
                'upcoming_inspections_count' => count($upcomingInspections),
                'critical_findings_count' => count(array_filter($complianceRecords, function($record) {
                    return !empty($record['findings']) && stripos($record['findings'], 'critical') !== false;
                }))
            ],
            'compliance_status_summary' => $statusSummary,
            'compliance_records' => $complianceRecords,
            'upcoming_inspections' => $upcomingInspections,
            'fines_and_penalties' => $finesData,
            'generated_at' => date('c')
        ];

    } catch (Exception $e) {
        error_log("Regulatory compliance report error: " . $e->getMessage());
        return [
            'report_type' => 'regulatory_compliance',
            'period' => ['start_date' => $startDate, 'end_date' => $endDate],
            'error' => 'Failed to generate regulatory compliance report',
            'summary' => [
                'total_inspections' => 0,
                'compliance_rate_percent' => 0,
                'total_fines_amount' => 0
            ],
            'data' => []
        ];
    }
}

/**
 * Get sustainability score report
 */
function getSustainabilityScoreReport($sustainabilityManager)
{
    try {
        // Get database connection from sustainability manager
        $db = $sustainabilityManager->getDatabaseConnection();

        // Get KPI data for scoring
        $stmt = $db->prepare("
            SELECT
                kpi_name,
                kpi_category,
                target_value,
                current_value,
                unit,
                status,
                CASE
                    WHEN target_value > 0 THEN
                        ROUND((current_value / target_value) * 100, 2)
                    ELSE 0
                END as achievement_percentage
            FROM sustainability_kpis
            ORDER BY kpi_category, kpi_name
        ");
        $stmt->execute();
        $kpis = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate category scores
        $categories = [];
        foreach ($kpis as $kpi) {
            $category = $kpi['kpi_category'];
            if (!isset($categories[$category])) {
                $categories[$category] = ['kpis' => [], 'total_score' => 0, 'count' => 0];
            }
            $categories[$category]['kpis'][] = $kpi;
            $categories[$category]['total_score'] += min(100, max(0, $kpi['achievement_percentage']));
            $categories[$category]['count']++;
        }

        // Calculate average scores per category
        foreach ($categories as &$category) {
            $category['average_score'] = $category['count'] > 0 ? round($category['total_score'] / $category['count'], 2) : 0;
        }

        // Calculate overall sustainability score
        $totalScore = 0;
        $totalCategories = count($categories);
        foreach ($categories as $category) {
            $totalScore += $category['average_score'];
        }
        $overallScore = $totalCategories > 0 ? round($totalScore / $totalCategories, 2) : 0;

        // Get score trend (compare with previous period)
        $stmt = $db->prepare("
            SELECT
                DATE_TRUNC('month', last_updated) as month,
                AVG(CASE
                    WHEN target_value > 0 THEN
                        ROUND((current_value / target_value) * 100, 2)
                    ELSE 0
                END) as avg_score
            FROM sustainability_kpis
            WHERE last_updated >= CURRENT_DATE - INTERVAL '6 months'
            GROUP BY DATE_TRUNC('month', last_updated)
            ORDER BY month DESC
            LIMIT 6
        ");
        $stmt->execute();
        $scoreTrend = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));

        // Determine score grade
        $grade = 'F';
        if ($overallScore >= 90) $grade = 'A';
        elseif ($overallScore >= 80) $grade = 'B';
        elseif ($overallScore >= 70) $grade = 'C';
        elseif ($overallScore >= 60) $grade = 'D';

        return [
            'report_type' => 'sustainability_score',
            'generated_at' => date('c'),
            'overall_score' => $overallScore,
            'grade' => $grade,
            'score_description' => getScoreDescription($grade),
            'category_scores' => $categories,
            'kpi_details' => $kpis,
            'score_trend' => $scoreTrend,
            'recommendations' => generateScoreRecommendations($categories, $kpis)
        ];

    } catch (Exception $e) {
        error_log("Sustainability score report error: " . $e->getMessage());
        return [
            'report_type' => 'sustainability_score',
            'error' => 'Failed to generate sustainability score report',
            'overall_score' => 0,
            'grade' => 'N/A',
            'data' => []
        ];
    }
}

/**
 * Calculate trend for a metric
 */
function calculateTrend($data, $metric)
{
    if (count($data) < 2) return 'insufficient_data';

    $firstHalf = array_slice($data, 0, floor(count($data) / 2));
    $secondHalf = array_slice($data, floor(count($data) / 2));

    $firstAvg = count($firstHalf) > 0 ? array_sum(array_column($firstHalf, $metric)) / count($firstHalf) : 0;
    $secondAvg = count($secondHalf) > 0 ? array_sum(array_column($secondHalf, $metric)) / count($secondHalf) : 0;

    if ($firstAvg == 0) return 'no_change';

    $changePercent = (($secondAvg - $firstAvg) / $firstAvg) * 100;

    if ($changePercent > 5) return 'increasing';
    elseif ($changePercent < -5) return 'decreasing';
    else return 'stable';
}

/**
 * Get score description based on grade
 */
function getScoreDescription($grade)
{
    $descriptions = [
        'A' => 'Excellent sustainability performance with outstanding environmental stewardship',
        'B' => 'Good sustainability performance with room for improvement in some areas',
        'C' => 'Average sustainability performance requiring focused improvement efforts',
        'D' => 'Below average sustainability performance needing significant attention',
        'F' => 'Poor sustainability performance requiring immediate corrective action'
    ];

    return $descriptions[$grade] ?? 'Score description not available';
}

/**
 * Generate score recommendations based on performance
 */
function generateScoreRecommendations($categories, $kpis)
{
    $recommendations = [];

    foreach ($kpis as $kpi) {
        if ($kpi['achievement_percentage'] < 70) {
            $recommendations[] = [
                'priority' => $kpi['achievement_percentage'] < 50 ? 'high' : 'medium',
                'kpi' => $kpi['kpi_name'],
                'current_performance' => $kpi['achievement_percentage'] . '%',
                'recommendation' => generateKPISpecificRecommendation($kpi)
            ];
        }
    }

    // Sort by priority
    usort($recommendations, function($a, $b) {
        $priorityOrder = ['high' => 3, 'medium' => 2, 'low' => 1];
        return $priorityOrder[$b['priority']] <=> $priorityOrder[$a['priority']];
    });

    return array_slice($recommendations, 0, 10); // Return top 10 recommendations
}

/**
 * Generate KPI-specific recommendations
 */
function generateKPISpecificRecommendation($kpi)
{
    $recommendations = [
        'Carbon Emissions Reduction' => 'Implement fuel efficiency programs and explore sustainable aviation fuels',
        'Energy Consumption' => 'Upgrade to LED lighting and implement energy management systems',
        'Waste Diversion Rate' => 'Enhance recycling programs and reduce single-use materials',
        'Water Conservation' => 'Install water-efficient fixtures and implement rainwater harvesting',
        'Noise Reduction' => 'Optimize flight paths and implement noise abatement procedures',
        'Green Energy Usage' => 'Expand solar and wind energy installations',
        'Air Quality' => 'Improve ventilation systems and monitor air quality regularly'
    ];

    return $recommendations[$kpi['kpi_name']] ?? 'Develop targeted improvement plan for this KPI';
}

/**
 * Get sustainability module configuration
 */
function getSustainabilityConfig()
{
    // This would retrieve configuration from the modules table
    return [
        'carbon_tracking_enabled' => true,
        'noise_monitoring_enabled' => true,
        'energy_tracking_enabled' => true,
        'waste_tracking_enabled' => true,
        'water_tracking_enabled' => true,
        'compliance_tracking_enabled' => true,
        'sensor_integration_enabled' => true,
        'reporting_frequency' => 'weekly',
        'alert_thresholds' => [
            'noise_level' => 70,
            'carbon_emissions' => 1000,
            'energy_consumption' => 50000
        ]
    ];
}
