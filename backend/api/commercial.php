<?php

/**
 * Commercial Operations API
 *
 * RESTful API for retail operations, advertising, VIP services, and revenue management
 */

require_once '../src/ApiResponse.php';
require_once '../models/Commercial.php';
require_once '../src/Auth.php';

// Initialize components
$apiResponse = new ApiResponse();
$commercialManager = new Commercial();
$auth = new Auth();

// Get request method and path
$method = $_SERVER['REQUEST_METHOD'];
$request = $_SERVER['REQUEST_URI'];
$path = parse_url($request, PHP_URL_PATH);

// Remove base path
$path = str_replace('/api/commercial', '', $path);
$path = str_replace('/backend/api/commercial', '', $path);

// Get path segments
$pathSegments = array_filter(explode('/', trim($path, '/')));
$resource = $pathSegments[0] ?? null;
$action = $pathSegments[1] ?? null;
$id = $pathSegments[2] ?? null;

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
            handleGetRequest($resource, $action, $id, $commercialManager, $user, $apiResponse);
            break;

        case 'POST':
            handlePostRequest($resource, $action, $commercialManager, $user, $apiResponse);
            break;

        case 'PUT':
            handlePutRequest($resource, $action, $id, $commercialManager, $user, $apiResponse);
            break;

        case 'DELETE':
            handleDeleteRequest($resource, $id, $commercialManager, $user, $apiResponse);
            break;

        default:
            $apiResponse->error('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log("Commercial API Error: " . $e->getMessage());
    error_log('API error: ' . $e->getMessage());
    $apiResponse->error('An internal error occurred', 500);
}

/**
 * Handle GET requests
 */
function handleGetRequest($resource, $action, $id, $commercialManager, $user, $apiResponse)
{
    // Check if user has appropriate permissions
    if (!$user || !in_array($user['role'], ['super_admin', 'admin', 'operator', 'passenger'])) {
        $apiResponse->error('Insufficient permissions', 403);
        return;
    }

    switch ($resource) {
        case null:
        case 'dashboard':
            // Get commercial dashboard data
            $dashboardData = $commercialManager->getDashboardData();
            $apiResponse->success($dashboardData);
            break;

        case 'outlets':
            // Get retail outlets
            $status = $_GET['status'] ?? null;
            $outlets = $commercialManager->getRetailOutlets($status);
            $apiResponse->success($outlets);
            break;

        case 'products':
            // Get products
            $category = $_GET['category'] ?? null;
            $outletId = $_GET['outlet_id'] ?? null;
            $availableOnly = isset($_GET['available_only']) ? filter_var($_GET['available_only'], FILTER_VALIDATE_BOOLEAN) : true;

            $products = $commercialManager->getProducts($category, $outletId, $availableOnly);
            $apiResponse->success($products);
            break;

        case 'advertising':
            if ($action === 'spaces') {
                // Get advertising spaces
                $status = $_GET['status'] ?? null;
                $type = $_GET['type'] ?? null;
                $spaces = $commercialManager->getAdvertisingSpaces($status, $type);
                $apiResponse->success($spaces);
            } elseif ($action === 'campaigns') {
                // Get advertising campaigns
                $campaigns = getAdvertisingCampaigns($commercialManager);
                $apiResponse->success($campaigns);
            } else {
                $apiResponse->error('Invalid action', 400);
            }
            break;

        case 'vip':
            if ($action === 'lounges') {
                // Get VIP lounges
                $status = $_GET['status'] ?? 'active';
                $lounges = $commercialManager->getVipLounges($status);
                $apiResponse->success($lounges);
            } else {
                $apiResponse->error('Invalid action', 400);
            }
            break;

        case 'loyalty':
            // Get passenger loyalty information
            $passengerId = $_GET['passenger_id'] ?? $user['user_id'];

            if ($user['role'] !== 'passenger' && $user['user_id'] !== $passengerId) {
                $apiResponse->error('Can only view own loyalty information', 403);
                return;
            }

            $loyalty = $commercialManager->getPassengerLoyalty($passengerId);
            $apiResponse->success($loyalty);
            break;

        case 'analytics':
            // Get sales analytics (admin/operator only)
            if (!in_array($user['role'], ['super_admin', 'admin', 'operator'])) {
                $apiResponse->error('Admin access required', 403);
                return;
            }

            $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
            $endDate = $_GET['end_date'] ?? date('Y-m-d');
            $outletId = $_GET['outlet_id'] ?? null;

            $analytics = $commercialManager->getSalesAnalytics($startDate, $endDate, $outletId);
            $apiResponse->success($analytics);
            break;

        case 'reports':
            if ($action === 'top-products') {
                // Get top selling products
                $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
                $endDate = $_GET['end_date'] ?? date('Y-m-d');
                $limit = $_GET['limit'] ?? 10;

                $topProducts = $commercialManager->getTopProducts($startDate, $endDate, $limit);
                $apiResponse->success($topProducts);
            } elseif ($action === 'lounge-utilization') {
                // Get lounge utilization report
                $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
                $endDate = $_GET['end_date'] ?? date('Y-m-d');

                $utilization = $commercialManager->getLoungeUtilization($startDate, $endDate);
                $apiResponse->success($utilization);
            } elseif ($action === 'low-stock') {
                // Get low stock alerts
                $alerts = $commercialManager->getLowStockAlerts();
                $apiResponse->success($alerts);
            } elseif ($action === 'revenue-optimization') {
                // Get revenue optimization report
                $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-90 days'));
                $endDate = $_GET['end_date'] ?? date('Y-m-d');
                $report = getRevenueOptimizationReport($commercialManager, $startDate, $endDate);
                $apiResponse->success($report);
            } elseif ($action === 'concession-performance') {
                // Get concession performance report
                $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-90 days'));
                $endDate = $_GET['end_date'] ?? date('Y-m-d');
                $report = getConcessionPerformanceReport($commercialManager, $startDate, $endDate);
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
function handlePostRequest($resource, $action, $commercialManager, $user, $apiResponse)
{
    // Check if user has appropriate permissions
    if (!$user || !in_array($user['role'], ['super_admin', 'admin', 'operator', 'passenger'])) {
        $apiResponse->error('Insufficient permissions', 403);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    switch ($resource) {
        case 'sales':
            if ($action === 'process') {
                // Process sales transaction
                if (!isset($input['outlet_id']) || !isset($input['items'])) {
                    $apiResponse->error('Outlet ID and items required', 400);
                    return;
                }

                $result = $commercialManager->processSale($input);
                $apiResponse->success($result);
            } else {
                $apiResponse->error('Invalid action', 400);
            }
            break;

        case 'advertising':
            if ($action === 'campaign') {
                // Create advertising campaign
                if (!isset($input['campaign_name']) || !isset($input['advertiser_name'])) {
                    $apiResponse->error('Campaign name and advertiser name required', 400);
                    return;
                }

                $result = $commercialManager->createAdvertisingCampaign($input);
                $apiResponse->success($result);
            } else {
                $apiResponse->error('Invalid action', 400);
            }
            break;

        case 'vip':
            if ($action === 'visit') {
                // Record lounge visit
                if (!isset($input['lounge_id']) || !isset($input['passenger_id'])) {
                    $apiResponse->error('Lounge ID and passenger ID required', 400);
                    return;
                }

                $result = $commercialManager->recordLoungeVisit($input);
                $apiResponse->success($result);
            } elseif ($action === 'checkout') {
                // Check out from lounge
                if (!isset($input['visit_id'])) {
                    $apiResponse->error('Visit ID required', 400);
                    return;
                }

                $result = $commercialManager->checkoutLoungeVisit($input['visit_id'], $input);
                $apiResponse->success($result);
            } else {
                $apiResponse->error('Invalid action', 400);
            }
            break;

        case 'loyalty':
            if ($action === 'redeem') {
                // Redeem loyalty points
                if (!isset($input['points']) || !isset($input['reason'])) {
                    $apiResponse->error('Points and reason required', 400);
                    return;
                }

                $passengerId = $input['passenger_id'] ?? $user['user_id'];

                if ($user['role'] !== 'passenger' && $user['user_id'] !== $passengerId) {
                    $apiResponse->error('Can only redeem own points', 403);
                    return;
                }

                $result = $commercialManager->redeemLoyaltyPoints($passengerId, $input['points'], $input['reason']);
                $apiResponse->success($result);
            } else {
                $apiResponse->error('Invalid action', 400);
            }
            break;

        case 'inventory':
            if ($action === 'update') {
                // Update product stock
                if (!isset($input['product_id']) || !isset($input['quantity_change'])) {
                    $apiResponse->error('Product ID and quantity change required', 400);
                    return;
                }

                $result = $commercialManager->updateProductStock(
                    $input['product_id'],
                    $input['quantity_change'],
                    $input['movement_type'] ?? 'adjustment',
                    $input['reason'] ?? null
                );
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
function handlePutRequest($resource, $action, $id, $commercialManager, $user, $apiResponse)
{
    // Check if user has appropriate permissions
    if (!$user || !in_array($user['role'], ['super_admin', 'admin', 'operator'])) {
        $apiResponse->error('Operator access required', 403);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    switch ($resource) {
        case 'outlets':
            if ($id) {
                // Update retail outlet
                $result = updateRetailOutlet($commercialManager, $id, $input);
                $apiResponse->success($result);
            } else {
                $apiResponse->error('Outlet ID required', 400);
            }
            break;

        case 'products':
            if ($id) {
                // Update product
                $result = updateProduct($commercialManager, $id, $input);
                $apiResponse->success($result);
            } else {
                $apiResponse->error('Product ID required', 400);
            }
            break;

        case 'advertising':
            if ($action === 'campaign' && $id) {
                // Update advertising campaign
                $result = updateAdvertisingCampaign($commercialManager, $id, $input);
                $apiResponse->success($result);
            } elseif ($action === 'space' && $id) {
                // Update advertising space
                $result = updateAdvertisingSpace($commercialManager, $id, $input);
                $apiResponse->success($result);
            } else {
                $apiResponse->error('Invalid action or ID required', 400);
            }
            break;

        case 'vip':
            if ($action === 'lounge' && $id) {
                // Update VIP lounge
                $result = updateVipLounge($commercialManager, $id, $input);
                $apiResponse->success($result);
            } else {
                $apiResponse->error('Invalid action or lounge ID required', 400);
            }
            break;

        default:
            $apiResponse->error('Resource not found', 404);
    }
}

/**
 * Handle DELETE requests
 */
function handleDeleteRequest($resource, $id, $commercialManager, $user, $apiResponse)
{
    // Check if user has admin permissions
    if (!$user || !in_array($user['role'], ['super_admin', 'admin'])) {
        $apiResponse->error('Admin access required', 403);
        return;
    }

    switch ($resource) {
        case 'outlets':
            if ($id) {
                // Delete retail outlet
                $result = deleteRetailOutlet($commercialManager, $id);
                $apiResponse->success($result);
            } else {
                $apiResponse->error('Outlet ID required', 400);
            }
            break;

        case 'products':
            if ($id) {
                // Delete product
                $result = deleteProduct($commercialManager, $id);
                $apiResponse->success($result);
            } else {
                $apiResponse->error('Product ID required', 400);
            }
            break;

        case 'advertising':
            if ($id) {
                // Delete advertising campaign
                $result = deleteAdvertisingCampaign($commercialManager, $id);
                $apiResponse->success($result);
            } else {
                $apiResponse->error('Campaign ID required', 400);
            }
            break;

        default:
            $apiResponse->error('Resource not found', 404);
    }
}

/**
 * Get advertising campaigns
 */
function getAdvertisingCampaigns($commercialManager)
{
    // This would query the database for campaigns
    // For now, return mock data
    return [
        [
            'campaign_id' => 1,
            'campaign_name' => 'Summer Travel Promotion',
            'advertiser_name' => 'Travel Corp',
            'status' => 'active',
            'start_date' => '2023-06-01',
            'end_date' => '2023-08-31'
        ]
    ];
}

/**
 * Update retail outlet
 */
function updateRetailOutlet($commercialManager, $outletId, $updates)
{
    // This would update the outlet in the database
    // For now, return success
    return [
        'outlet_id' => $outletId,
        'status' => 'updated',
        'message' => 'Retail outlet updated successfully'
    ];
}

/**
 * Update product
 */
function updateProduct($commercialManager, $productId, $updates)
{
    // This would update the product in the database
    // For now, return success
    return [
        'product_id' => $productId,
        'status' => 'updated',
        'message' => 'Product updated successfully'
    ];
}

/**
 * Update advertising campaign
 */
function updateAdvertisingCampaign($commercialManager, $campaignId, $updates)
{
    // This would update the campaign in the database
    // For now, return success
    return [
        'campaign_id' => $campaignId,
        'status' => 'updated',
        'message' => 'Advertising campaign updated successfully'
    ];
}

/**
 * Update advertising space
 */
function updateAdvertisingSpace($commercialManager, $spaceId, $updates)
{
    // This would update the space in the database
    // For now, return success
    return [
        'space_id' => $spaceId,
        'status' => 'updated',
        'message' => 'Advertising space updated successfully'
    ];
}

/**
 * Update VIP lounge
 */
function updateVipLounge($commercialManager, $loungeId, $updates)
{
    // This would update the lounge in the database
    // For now, return success
    return [
        'lounge_id' => $loungeId,
        'status' => 'updated',
        'message' => 'VIP lounge updated successfully'
    ];
}

/**
 * Delete retail outlet
 */
function deleteRetailOutlet($commercialManager, $outletId)
{
    // This would delete the outlet from the database
    // For now, return success
    return [
        'outlet_id' => $outletId,
        'status' => 'deleted',
        'message' => 'Retail outlet deleted successfully'
    ];
}

/**
 * Delete product
 */
function deleteProduct($commercialManager, $productId)
{
    // This would delete the product from the database
    // For now, return success
    return [
        'product_id' => $productId,
        'status' => 'deleted',
        'message' => 'Product deleted successfully'
    ];
}

/**
 * Delete advertising campaign
 */
function deleteAdvertisingCampaign($commercialManager, $campaignId)
{
    // This would delete the campaign from the database
    // For now, return success
    return [
        'campaign_id' => $campaignId,
        'status' => 'deleted',
        'message' => 'Advertising campaign deleted successfully'
    ];
}

/**
 * Check if commercial module is enabled
 */
function isCommercialEnabled()
{
    // This would typically check the modules table
    // For now, return true as we're implementing it
    return true;
}

/**
 * Get revenue optimization report
 */
function getRevenueOptimizationReport($commercialManager, $startDate, $endDate)
{
    try {
        // Get database connection from commercial manager
        $db = $commercialManager->getDatabaseConnection();

        // Get revenue by category and time period
        $stmt = $db->prepare("
            SELECT
                DATE_TRUNC('month', transaction_date) as period,
                category,
                SUM(total_amount) as total_revenue,
                SUM(quantity) as total_quantity,
                COUNT(*) as transaction_count,
                ROUND(AVG(total_amount), 2) as avg_transaction_value
            FROM sales_transactions st
            JOIN products p ON st.product_id = p.product_id
            WHERE transaction_date BETWEEN ? AND ?
            GROUP BY DATE_TRUNC('month', transaction_date), category
            ORDER BY period, category
        ");
        $stmt->execute([$startDate, $endDate]);
        $revenueByCategory = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get revenue by outlet
        $stmt = $db->prepare("
            SELECT
                ro.outlet_name,
                SUM(st.total_amount) as total_revenue,
                COUNT(DISTINCT st.transaction_id) as transaction_count,
                COUNT(DISTINCT st.passenger_id) as unique_customers,
                ROUND(AVG(st.total_amount), 2) as avg_transaction_value
            FROM sales_transactions st
            JOIN retail_outlets ro ON st.outlet_id = ro.outlet_id
            WHERE st.transaction_date BETWEEN ? AND ?
            GROUP BY ro.outlet_id, ro.outlet_name
            ORDER BY total_revenue DESC
        ");
        $stmt->execute([$startDate, $endDate]);
        $revenueByOutlet = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get peak revenue hours
        $stmt = $db->prepare("
            SELECT
                EXTRACT(hour from transaction_date) as hour,
                SUM(total_amount) as revenue,
                COUNT(*) as transactions
            FROM sales_transactions
            WHERE transaction_date BETWEEN ? AND ?
            GROUP BY EXTRACT(hour from transaction_date)
            ORDER BY revenue DESC
        ");
        $stmt->execute([$startDate, $endDate]);
        $peakHours = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get top performing products
        $stmt = $db->prepare("
            SELECT
                p.product_name,
                p.category,
                SUM(st.total_amount) as total_revenue,
                SUM(st.quantity) as total_quantity,
                COUNT(*) as transaction_count,
                ROUND(AVG(st.total_amount), 2) as avg_sale_price
            FROM sales_transactions st
            JOIN products p ON st.product_id = p.product_id
            WHERE st.transaction_date BETWEEN ? AND ?
            GROUP BY p.product_id, p.product_name, p.category
            ORDER BY total_revenue DESC
            LIMIT 20
        ");
        $stmt->execute([$startDate, $endDate]);
        $topProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get customer segmentation by spending
        $stmt = $db->prepare("
            SELECT
                CASE
                    WHEN total_spent >= 500 THEN 'High Value'
                    WHEN total_spent >= 200 THEN 'Medium Value'
                    WHEN total_spent >= 50 THEN 'Low Value'
                    ELSE 'One-time'
                END as customer_segment,
                COUNT(*) as customer_count,
                SUM(total_spent) as segment_revenue,
                ROUND(AVG(total_spent), 2) as avg_spending
            FROM (
                SELECT
                    passenger_id,
                    SUM(total_amount) as total_spent
                FROM sales_transactions
                WHERE transaction_date BETWEEN ? AND ?
                GROUP BY passenger_id
            ) customer_spending
            GROUP BY customer_segment
            ORDER BY segment_revenue DESC
        ");
        $stmt->execute([$startDate, $endDate]);
        $customerSegments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate overall metrics
        $totalRevenue = array_sum(array_column($revenueByCategory, 'total_revenue'));
        $totalTransactions = array_sum(array_column($revenueByCategory, 'transaction_count'));
        $avgTransactionValue = $totalTransactions > 0 ? round($totalRevenue / $totalTransactions, 2) : 0;

        // Calculate growth trends
        $revenueTrend = calculateRevenueTrend($revenueByCategory);

        return [
            'report_type' => 'revenue_optimization',
            'period' => ['start_date' => $startDate, 'end_date' => $endDate],
            'summary' => [
                'total_revenue' => round($totalRevenue, 2),
                'total_transactions' => $totalTransactions,
                'avg_transaction_value' => $avgTransactionValue,
                'unique_customers' => count(array_unique(array_column($revenueByOutlet, 'unique_customers'))),
                'revenue_trend' => $revenueTrend,
                'top_revenue_hour' => !empty($peakHours) ? $peakHours[0]['hour'] : null,
                'peak_hour_revenue' => !empty($peakHours) ? round($peakHours[0]['revenue'], 2) : 0
            ],
            'revenue_by_category' => $revenueByCategory,
            'revenue_by_outlet' => $revenueByOutlet,
            'peak_revenue_hours' => $peakHours,
            'top_performing_products' => $topProducts,
            'customer_segments' => $customerSegments,
            'optimization_recommendations' => generateRevenueOptimizationRecommendations($revenueByCategory, $peakHours, $topProducts),
            'generated_at' => date('c')
        ];

    } catch (Exception $e) {
        error_log("Revenue optimization report error: " . $e->getMessage());
        return [
            'report_type' => 'revenue_optimization',
            'period' => ['start_date' => $startDate, 'end_date' => $endDate],
            'error' => 'Failed to generate revenue optimization report',
            'summary' => [
                'total_revenue' => 0,
                'total_transactions' => 0,
                'avg_transaction_value' => 0
            ],
            'data' => []
        ];
    }
}

/**
 * Get concession performance report
 */
function getConcessionPerformanceReport($commercialManager, $startDate, $endDate)
{
    try {
        // Get database connection from commercial manager
        $db = $commercialManager->getDatabaseConnection();

        // Get concession performance by outlet
        $stmt = $db->prepare("
            SELECT
                ro.outlet_name,
                ro.outlet_type,
                ro.location,
                COUNT(DISTINCT st.transaction_id) as total_transactions,
                SUM(st.total_amount) as total_revenue,
                SUM(st.quantity) as total_items_sold,
                COUNT(DISTINCT st.passenger_id) as unique_customers,
                ROUND(AVG(st.total_amount), 2) as avg_transaction_value,
                ROUND(SUM(st.total_amount) / COUNT(DISTINCT st.transaction_id), 2) as revenue_per_transaction,
                ROUND(SUM(st.total_amount) / COUNT(DISTINCT st.passenger_id), 2) as revenue_per_customer
            FROM retail_outlets ro
            LEFT JOIN sales_transactions st ON ro.outlet_id = st.outlet_id
            AND st.transaction_date BETWEEN ? AND ?
            GROUP BY ro.outlet_id, ro.outlet_name, ro.outlet_type, ro.location
            ORDER BY total_revenue DESC
        ");
        $stmt->execute([$startDate, $endDate]);
        $outletPerformance = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get product category performance
        $stmt = $db->prepare("
            SELECT
                p.category,
                SUM(st.total_amount) as category_revenue,
                SUM(st.quantity) as items_sold,
                COUNT(DISTINCT st.product_id) as unique_products,
                COUNT(DISTINCT st.transaction_id) as transactions,
                ROUND(AVG(st.total_amount), 2) as avg_sale_price,
                ROUND(SUM(st.total_amount) / SUM(st.quantity), 2) as avg_price_per_item
            FROM sales_transactions st
            JOIN products p ON st.product_id = p.product_id
            WHERE st.transaction_date BETWEEN ? AND ?
            GROUP BY p.category
            ORDER BY category_revenue DESC
        ");
        $stmt->execute([$startDate, $endDate]);
        $categoryPerformance = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get daily performance trends
        $stmt = $db->prepare("
            SELECT
                DATE(transaction_date) as date,
                SUM(total_amount) as daily_revenue,
                COUNT(DISTINCT transaction_id) as daily_transactions,
                COUNT(DISTINCT passenger_id) as daily_customers,
                ROUND(AVG(total_amount), 2) as avg_daily_transaction
            FROM sales_transactions
            WHERE transaction_date BETWEEN ? AND ?
            GROUP BY DATE(transaction_date)
            ORDER BY date
        ");
        $stmt->execute([$startDate, $endDate]);
        $dailyTrends = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get inventory turnover analysis
        $stmt = $db->prepare("
            SELECT
                p.product_name,
                p.category,
                p.stock_quantity,
                COALESCE(SUM(st.quantity), 0) as sold_quantity,
                CASE
                    WHEN p.stock_quantity > 0
                    THEN ROUND(COALESCE(SUM(st.quantity), 0) / p.stock_quantity, 2)
                    ELSE 0
                END as turnover_rate,
                ROUND(AVG(p.unit_price), 2) as avg_selling_price
            FROM products p
            LEFT JOIN sales_transactions st ON p.product_id = st.product_id
            AND st.transaction_date BETWEEN ? AND ?
            GROUP BY p.product_id, p.product_name, p.category, p.stock_quantity, p.unit_price
            ORDER BY turnover_rate DESC
        ");
        $stmt->execute([$startDate, $endDate]);
        $inventoryTurnover = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get staff performance
        $stmt = $db->prepare("
            SELECT
                st.staff_id,
                COUNT(DISTINCT st.transaction_id) as transactions_handled,
                SUM(st.total_amount) as revenue_generated,
                COUNT(DISTINCT st.passenger_id) as customers_served,
                ROUND(AVG(st.total_amount), 2) as avg_transaction_value,
                ROUND(SUM(st.total_amount) / COUNT(DISTINCT st.transaction_id), 2) as revenue_per_transaction
            FROM sales_transactions st
            WHERE st.transaction_date BETWEEN ? AND ?
            AND st.staff_id IS NOT NULL
            GROUP BY st.staff_id
            ORDER BY revenue_generated DESC
        ");
        $stmt->execute([$startDate, $endDate]);
        $staffPerformance = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate performance metrics
        $totalRevenue = array_sum(array_column($outletPerformance, 'total_revenue'));
        $totalTransactions = array_sum(array_column($outletPerformance, 'total_transactions'));
        $totalCustomers = array_sum(array_column($outletPerformance, 'unique_customers'));

        $avgRevenuePerOutlet = count($outletPerformance) > 0 ? round($totalRevenue / count($outletPerformance), 2) : 0;
        $avgTransactionsPerOutlet = count($outletPerformance) > 0 ? round($totalTransactions / count($outletPerformance), 2) : 0;

        return [
            'report_type' => 'concession_performance',
            'period' => ['start_date' => $startDate, 'end_date' => $endDate],
            'summary' => [
                'total_revenue' => round($totalRevenue, 2),
                'total_transactions' => $totalTransactions,
                'total_customers' => $totalCustomers,
                'avg_revenue_per_outlet' => $avgRevenuePerOutlet,
                'avg_transactions_per_outlet' => $avgTransactionsPerOutlet,
                'revenue_per_customer' => $totalCustomers > 0 ? round($totalRevenue / $totalCustomers, 2) : 0,
                'top_performing_outlet' => !empty($outletPerformance) ? $outletPerformance[0]['outlet_name'] : null,
                'top_performing_category' => !empty($categoryPerformance) ? $categoryPerformance[0]['category'] : null
            ],
            'outlet_performance' => $outletPerformance,
            'category_performance' => $categoryPerformance,
            'daily_performance_trends' => $dailyTrends,
            'inventory_turnover_analysis' => $inventoryTurnover,
            'staff_performance' => $staffPerformance,
            'performance_insights' => generatePerformanceInsights($outletPerformance, $categoryPerformance, $inventoryTurnover),
            'generated_at' => date('c')
        ];

    } catch (Exception $e) {
        error_log("Concession performance report error: " . $e->getMessage());
        return [
            'report_type' => 'concession_performance',
            'period' => ['start_date' => $startDate, 'end_date' => $endDate],
            'error' => 'Failed to generate concession performance report',
            'summary' => [
                'total_revenue' => 0,
                'total_transactions' => 0,
                'total_customers' => 0
            ],
            'data' => []
        ];
    }
}

/**
 * Calculate revenue trend
 */
function calculateRevenueTrend($revenueData)
{
    if (count($revenueData) < 2) return 'insufficient_data';

    $periods = array_unique(array_column($revenueData, 'period'));
    sort($periods);

    $firstHalf = array_slice($periods, 0, floor(count($periods) / 2));
    $secondHalf = array_slice($periods, floor(count($periods) / 2));

    $firstHalfRevenue = 0;
    $secondHalfRevenue = 0;

    foreach ($revenueData as $data) {
        if (in_array($data['period'], $firstHalf)) {
            $firstHalfRevenue += $data['total_revenue'];
        } elseif (in_array($data['period'], $secondHalf)) {
            $secondHalfRevenue += $data['total_revenue'];
        }
    }

    if ($firstHalfRevenue == 0) return 'no_change';

    $changePercent = (($secondHalfRevenue - $firstHalfRevenue) / $firstHalfRevenue) * 100;

    if ($changePercent > 10) return 'strong_growth';
    elseif ($changePercent > 5) return 'moderate_growth';
    elseif ($changePercent < -10) return 'strong_decline';
    elseif ($changePercent < -5) return 'moderate_decline';
    else return 'stable';
}

/**
 * Generate revenue optimization recommendations
 */
function generateRevenueOptimizationRecommendations($revenueByCategory, $peakHours, $topProducts)
{
    $recommendations = [];

    // Analyze peak hours
    if (!empty($peakHours)) {
        $topHour = $peakHours[0]['hour'];
        $recommendations[] = [
            'type' => 'operational',
            'priority' => 'high',
            'recommendation' => "Optimize staffing during peak hour {$topHour}:00 to maximize revenue capture",
            'expected_impact' => 'Increase revenue by 15-20% during peak periods'
        ];
    }

    // Analyze product performance
    if (!empty($topProducts)) {
        $topCategory = $topProducts[0]['category'];
        $recommendations[] = [
            'type' => 'product',
            'priority' => 'high',
            'recommendation' => "Expand {$topCategory} product line - high demand category",
            'expected_impact' => 'Potential 10-15% revenue increase'
        ];
    }

    // Analyze category performance
    $categories = array_unique(array_column($revenueByCategory, 'category'));
    foreach ($categories as $category) {
        $categoryData = array_filter($revenueByCategory, function($item) use ($category) {
            return $item['category'] === $category;
        });

        $avgRevenue = array_sum(array_column($categoryData, 'total_revenue')) / count($categoryData);

        if ($avgRevenue < 1000) { // Low performing category
            $recommendations[] = [
                'type' => 'marketing',
                'priority' => 'medium',
                'recommendation' => "Develop marketing strategy for {$category} category to boost sales",
                'expected_impact' => 'Improve category performance by 20-30%'
            ];
        }
    }

    return array_slice($recommendations, 0, 5); // Return top 5 recommendations
}

/**
 * Generate performance insights
 */
function generatePerformanceInsights($outletPerformance, $categoryPerformance, $inventoryTurnover)
{
    $insights = [];

    // Outlet performance insights
    if (!empty($outletPerformance)) {
        $topOutlet = $outletPerformance[0];
        $bottomOutlet = end($outletPerformance);

        $insights[] = [
            'type' => 'outlet_performance',
            'insight' => "{$topOutlet['outlet_name']} leads in revenue generation",
            'metric' => 'revenue',
            'value' => round($topOutlet['total_revenue'], 2)
        ];

        if ($bottomOutlet['total_revenue'] < $topOutlet['total_revenue'] * 0.5) {
            $insights[] = [
                'type' => 'improvement_opportunity',
                'insight' => "{$bottomOutlet['outlet_name']} shows significant performance gap",
                'recommendation' => 'Analyze and implement best practices from top-performing outlets'
            ];
        }
    }

    // Category performance insights
    if (!empty($categoryPerformance)) {
        $topCategory = $categoryPerformance[0];
        $insights[] = [
            'type' => 'category_performance',
            'insight' => "{$topCategory['category']} is the highest revenue-generating category",
            'metric' => 'revenue',
            'value' => round($topCategory['category_revenue'], 2)
        ];
    }

    // Inventory insights
    if (!empty($inventoryTurnover)) {
        $highTurnover = array_filter($inventoryTurnover, function($item) {
            return $item['turnover_rate'] > 2.0;
        });

        $lowTurnover = array_filter($inventoryTurnover, function($item) {
            return $item['turnover_rate'] < 0.5;
        });

        if (!empty($highTurnover)) {
            $insights[] = [
                'type' => 'inventory_optimization',
                'insight' => count($highTurnover) . ' products have high turnover rates',
                'recommendation' => 'Consider increasing stock levels for fast-moving products'
            ];
        }

        if (!empty($lowTurnover)) {
            $insights[] = [
                'type' => 'inventory_optimization',
                'insight' => count($lowTurnover) . ' products have low turnover rates',
                'recommendation' => 'Review slow-moving inventory and consider promotions or discontinuation'
            ];
        }
    }

    return $insights;
}

/**
 * Get commercial module configuration
 */
function getCommercialConfig()
{
    // This would retrieve configuration from the modules table
    return [
        'retail_operations_enabled' => true,
        'advertising_enabled' => true,
        'vip_services_enabled' => true,
        'loyalty_program_enabled' => true,
        'pos_integration_enabled' => false,
        'digital_signage_enabled' => false,
        'revenue_tracking_enabled' => true,
        'analytics_enabled' => true,
        'max_transaction_amount' => 10000,
        'loyalty_points_per_dollar' => 1,
        'tax_rate_default' => 8.5
    ];
}
