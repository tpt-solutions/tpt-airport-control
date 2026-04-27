<?php

/**
 * Commercial Model
 *
 * Manages retail operations, advertising, VIP services, and revenue optimization
 */

class Commercial
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
        $this->logger = new Logger('commercial');
    }

    /**
     * Get all retail outlets
     */
    public function getRetailOutlets($status = null)
    {
        $whereClause = $status ? "WHERE status = ?" : "";
        $params = $status ? [$status] : [];

        $stmt = $this->db->prepare("
            SELECT * FROM retail_outlets
            $whereClause
            ORDER BY terminal, outlet_name
        ");

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get products by category or outlet
     */
    public function getProducts($category = null, $outletId = null, $availableOnly = true)
    {
        $whereClause = "";
        $params = [];

        if ($category) {
            $whereClause .= " AND product_category = ?";
            $params[] = $category;
        }

        if ($outletId) {
            // This would need a products-to-outlets relationship table in production
            // For now, we'll return all products
        }

        if ($availableOnly) {
            $whereClause .= " AND is_available = true AND stock_quantity > 0";
        }

        $stmt = $this->db->prepare("
            SELECT * FROM products
            WHERE 1=1 $whereClause
            ORDER BY product_category, product_name
        ");

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Process a sales transaction
     */
    public function processSale($saleData)
    {
        $this->logger->info("Processing sale", $saleData);

        $this->db->beginTransaction();

        try {
            // Generate transaction number
            $transactionNumber = $this->generateTransactionNumber($saleData['outlet_id']);

            // Calculate totals
            $totals = $this->calculateTransactionTotals($saleData['items']);

            // Create transaction
            $stmt = $this->db->prepare("
                INSERT INTO sales_transactions (
                    outlet_id, transaction_number, customer_type, passenger_id,
                    flight_id, payment_method, payment_reference, subtotal,
                    tax_amount, discount_amount, total_amount, cashier_id, notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $saleData['outlet_id'],
                $transactionNumber,
                $saleData['customer_type'] ?? 'walk_in',
                $saleData['passenger_id'] ?? null,
                $saleData['flight_id'] ?? null,
                $saleData['payment_method'] ?? 'cash',
                $saleData['payment_reference'] ?? null,
                $totals['subtotal'],
                $totals['tax_amount'],
                $saleData['discount_amount'] ?? 0,
                $totals['total_amount'],
                $saleData['cashier_id'] ?? null,
                $saleData['notes'] ?? null
            ]);

            $transactionId = $this->db->lastInsertId();

            // Add transaction items
            $this->addTransactionItems($transactionId, $saleData['items']);

            // Update inventory
            $this->updateInventory($saleData['items']);

            $this->db->commit();

            return [
                'transaction_id' => $transactionId,
                'transaction_number' => $transactionNumber,
                'total_amount' => $totals['total_amount'],
                'status' => 'completed'
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Get advertising spaces
     */
    public function getAdvertisingSpaces($status = null, $type = null)
    {
        $whereClause = "";
        $params = [];

        if ($status) {
            $whereClause .= " AND status = ?";
            $params[] = $status;
        }

        if ($type) {
            $whereClause .= " AND space_type = ?";
            $params[] = $type;
        }

        $stmt = $this->db->prepare("
            SELECT * FROM advertising_spaces
            WHERE 1=1 $whereClause
            ORDER BY terminal, location, space_name
        ");

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Create advertising campaign
     */
    public function createAdvertisingCampaign($campaignData)
    {
        $this->logger->info("Creating advertising campaign", $campaignData);

        $stmt = $this->db->prepare("
            INSERT INTO advertising_campaigns (
                campaign_name, advertiser_name, advertiser_contact,
                campaign_type, target_audience, start_date, end_date,
                total_budget, spaces_allocated, creative_assets
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $campaignData['campaign_name'],
            $campaignData['advertiser_name'],
            isset($campaignData['advertiser_contact']) ? json_encode($campaignData['advertiser_contact']) : null,
            $campaignData['campaign_type'],
            isset($campaignData['target_audience']) ? json_encode($campaignData['target_audience']) : null,
            $campaignData['start_date'],
            $campaignData['end_date'],
            $campaignData['total_budget'],
            isset($campaignData['spaces_allocated']) ? json_encode($campaignData['spaces_allocated']) : null,
            isset($campaignData['creative_assets']) ? json_encode($campaignData['creative_assets']) : null
        ]);

        return [
            'campaign_id' => $this->db->lastInsertId(),
            'status' => 'created'
        ];
    }

    /**
     * Get VIP lounges
     */
    public function getVipLounges($status = 'active')
    {
        $stmt = $this->db->prepare("
            SELECT * FROM vip_lounges
            WHERE status = ?
            ORDER BY terminal, lounge_name
        ");

        $stmt->execute([$status]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Record lounge visit
     */
    public function recordLoungeVisit($visitData)
    {
        $this->logger->info("Recording lounge visit", $visitData);

        $stmt = $this->db->prepare("
            INSERT INTO lounge_visits (
                lounge_id, passenger_id, booking_id, access_type,
                amenities_used, total_charged
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $visitData['lounge_id'],
            $visitData['passenger_id'],
            $visitData['booking_id'] ?? null,
            $visitData['access_type'] ?? 'paid',
            isset($visitData['amenities_used']) ? json_encode($visitData['amenities_used']) : '[]',
            $visitData['total_charged'] ?? 0
        ]);

        return [
            'visit_id' => $this->db->lastInsertId(),
            'status' => 'recorded'
        ];
    }

    /**
     * Check out from lounge
     */
    public function checkoutLoungeVisit($visitId, $checkoutData)
    {
        $stmt = $this->db->prepare("
            UPDATE lounge_visits
            SET check_out_time = ?, duration_minutes = ?, payment_status = ?,
                satisfaction_rating = ?, feedback = ?
            WHERE visit_id = ?
        ");

        $stmt->execute([
            $checkoutData['check_out_time'] ?? date('Y-m-d H:i:s'),
            $checkoutData['duration_minutes'] ?? null,
            $checkoutData['payment_status'] ?? 'completed',
            $checkoutData['satisfaction_rating'] ?? null,
            $checkoutData['feedback'] ?? null,
            $visitId
        ]);

        return ['status' => 'checked_out'];
    }

    /**
     * Get passenger loyalty information
     */
    public function getPassengerLoyalty($passengerId)
    {
        $stmt = $this->db->prepare("
            SELECT
                lp.*,
                (
                    SELECT json_agg(
                        json_build_object(
                            'transaction_type', transaction_type,
                            'points', points,
                            'description', description,
                            'transaction_date', transaction_date
                        )
                    )
                    FROM loyalty_transactions lt
                    WHERE lt.loyalty_id = lp.loyalty_id
                    ORDER BY transaction_date DESC
                    LIMIT 10
                ) as recent_transactions
            FROM loyalty_program lp
            WHERE lp.passenger_id = ?
        ");

        $stmt->execute([$passengerId]);
        $loyalty = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$loyalty) {
            // Create loyalty record if it doesn't exist
            $stmt = $this->db->prepare("
                INSERT INTO loyalty_program (passenger_id)
                VALUES (?)
            ");
            $stmt->execute([$passengerId]);

            return [
                'passenger_id' => $passengerId,
                'membership_tier' => 'bronze',
                'points_balance' => 0,
                'total_points_earned' => 0,
                'total_points_redeemed' => 0,
                'join_date' => date('Y-m-d'),
                'status' => 'active',
                'recent_transactions' => []
            ];
        }

        return $loyalty;
    }

    /**
     * Redeem loyalty points
     */
    public function redeemLoyaltyPoints($passengerId, $points, $reason)
    {
        $loyalty = $this->getPassengerLoyalty($passengerId);

        if ($loyalty['points_balance'] < $points) {
            throw new Exception("Insufficient points balance");
        }

        $this->db->beginTransaction();

        try {
            // Update balance
            $stmt = $this->db->prepare("
                UPDATE loyalty_program
                SET points_balance = points_balance - ?,
                    total_points_redeemed = total_points_redeemed + ?,
                    last_activity = CURRENT_DATE
                WHERE passenger_id = ?
            ");

            $stmt->execute([$points, $points, $passengerId]);

            // Record transaction
            $stmt = $this->db->prepare("
                INSERT INTO loyalty_transactions (
                    loyalty_id, transaction_type, points, description
                ) VALUES (?, 'redeem', ?, ?)
            ");

            $stmt->execute([$loyalty['loyalty_id'], $points, $reason]);

            $this->db->commit();

            return [
                'points_redeemed' => $points,
                'new_balance' => $loyalty['points_balance'] - $points,
                'status' => 'redeemed'
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Get commercial dashboard data
     */
    public function getDashboardData()
    {
        $stmt = $this->db->prepare("
            SELECT
                json_build_object(
                    'today_revenue', COALESCE((
                        SELECT SUM(total_amount)
                        FROM sales_transactions
                        WHERE DATE(transaction_date) = CURRENT_DATE
                        AND status = 'completed'
                    ), 0),
                    'yesterday_revenue', COALESCE((
                        SELECT SUM(total_amount)
                        FROM sales_transactions
                        WHERE DATE(transaction_date) = CURRENT_DATE - INTERVAL '1 day'
                        AND status = 'completed'
                    ), 0),
                    'today_transactions', (
                        SELECT COUNT(*)
                        FROM sales_transactions
                        WHERE DATE(transaction_date) = CURRENT_DATE
                        AND status = 'completed'
                    ),
                    'active_campaigns', (
                        SELECT COUNT(*)
                        FROM advertising_campaigns
                        WHERE status = 'active'
                        AND CURRENT_DATE BETWEEN start_date AND end_date
                    ),
                    'lounge_occupancy', (
                        SELECT json_agg(
                            json_build_object(
                                'lounge_name', lounge_name,
                                'current_occupancy', (
                                    SELECT COUNT(*)
                                    FROM lounge_visits
                                    WHERE lounge_id = vl.lounge_id
                                    AND check_out_time IS NULL
                                ),
                                'capacity', capacity
                            )
                        )
                        FROM vip_lounges vl
                        WHERE status = 'active'
                    ),
                    'available_spaces', (
                        SELECT COUNT(*)
                        FROM advertising_spaces
                        WHERE status = 'available'
                    ),
                    'low_stock_alerts', (
                        SELECT COUNT(*)
                        FROM products
                        WHERE stock_quantity <= min_stock_level
                        AND is_available = true
                    )
                ) as dashboard_data
        ");

        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return json_decode($result['dashboard_data'], true);
    }

    /**
     * Get sales analytics
     */
    public function getSalesAnalytics($startDate, $endDate, $outletId = null)
    {
        $whereClause = $outletId ? " AND st.outlet_id = ?" : "";
        $params = [$startDate, $endDate];
        if ($outletId) $params[] = $outletId;

        $stmt = $this->db->prepare("
            SELECT
                DATE(st.transaction_date) as date,
                COUNT(st.transaction_id) as transaction_count,
                SUM(st.total_amount) as total_revenue,
                AVG(st.total_amount) as avg_transaction,
                COUNT(DISTINCT st.passenger_id) as unique_customers
            FROM sales_transactions st
            WHERE DATE(st.transaction_date) BETWEEN ? AND ?
            AND st.status = 'completed' $whereClause
            GROUP BY DATE(st.transaction_date)
            ORDER BY date
        ");

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get top selling products
     */
    public function getTopProducts($startDate, $endDate, $limit = 10)
    {
        $stmt = $this->db->prepare("
            SELECT
                p.product_name,
                p.product_category,
                p.brand,
                SUM(ti.quantity) as total_quantity,
                SUM(ti.total_amount) as total_revenue,
                COUNT(DISTINCT st.transaction_id) as transaction_count
            FROM transaction_items ti
            JOIN products p ON ti.product_id = p.product_id
            JOIN sales_transactions st ON ti.transaction_id = st.transaction_id
            WHERE DATE(st.transaction_date) BETWEEN ? AND ?
            AND st.status = 'completed'
            GROUP BY p.product_id, p.product_name, p.product_category, p.brand
            ORDER BY total_revenue DESC
            LIMIT ?
        ");

        $stmt->execute([$startDate, $endDate, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Update product stock
     */
    public function updateProductStock($productId, $quantityChange, $movementType, $reason = null)
    {
        $this->db->beginTransaction();

        try {
            // Update stock
            $stmt = $this->db->prepare("
                UPDATE products
                SET stock_quantity = stock_quantity + ?, updated_at = CURRENT_TIMESTAMP
                WHERE product_id = ?
            ");

            $stmt->execute([$quantityChange, $productId]);

            // Record movement
            $stmt = $this->db->prepare("
                INSERT INTO inventory_movements (
                    product_id, movement_type, quantity, reason
                ) VALUES (?, ?, ?, ?)
            ");

            $stmt->execute([$productId, $movementType, $quantityChange, $reason]);

            $this->db->commit();

            return ['status' => 'updated'];

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Get low stock alerts
     */
    public function getLowStockAlerts()
    {
        $stmt = $this->db->prepare("
            SELECT
                p.product_id,
                p.product_name,
                p.product_category,
                p.stock_quantity,
                p.min_stock_level,
                p.brand,
                r.outlet_name
            FROM products p
            LEFT JOIN retail_outlets r ON p.product_category IN (
                SELECT UNNEST(string_to_array(r.outlet_type, ','))
            )
            WHERE p.stock_quantity <= p.min_stock_level
            AND p.is_available = true
            ORDER BY (p.min_stock_level - p.stock_quantity) DESC
        ");

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Generate transaction number
     */
    private function generateTransactionNumber($outletId)
    {
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 4));

        return "TXN-{$outletId}-{$date}-{$random}";
    }

    /**
     * Calculate transaction totals
     */
    private function calculateTransactionTotals($items)
    {
        $subtotal = 0;
        $taxAmount = 0;

        foreach ($items as $item) {
            $itemTotal = $item['quantity'] * $item['unit_price'];
            $subtotal += $itemTotal;

            // Calculate tax
            $taxRate = $item['tax_rate'] ?? 0;
            $taxAmount += $itemTotal * ($taxRate / 100);
        }

        return [
            'subtotal' => round($subtotal, 2),
            'tax_amount' => round($taxAmount, 2),
            'total_amount' => round($subtotal + $taxAmount, 2)
        ];
    }

    /**
     * Add transaction items
     */
    private function addTransactionItems($transactionId, $items)
    {
        $stmt = $this->db->prepare("
            INSERT INTO transaction_items (
                transaction_id, product_id, quantity, unit_price,
                discount_amount, tax_amount, total_amount, product_name, product_category
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($items as $item) {
            $itemTotal = $item['quantity'] * $item['unit_price'];
            $taxAmount = $itemTotal * (($item['tax_rate'] ?? 0) / 100);
            $discountAmount = $item['discount_amount'] ?? 0;

            $stmt->execute([
                $transactionId,
                $item['product_id'],
                $item['quantity'],
                $item['unit_price'],
                $discountAmount,
                $taxAmount,
                $itemTotal - $discountAmount + $taxAmount,
                $item['product_name'],
                $item['product_category']
            ]);
        }
    }

    /**
     * Update inventory after sale
     */
    private function updateInventory($items)
    {
        foreach ($items as $item) {
            $this->updateProductStock(
                $item['product_id'],
                -$item['quantity'], // Negative for stock reduction
                'stock_out',
                'Sale transaction'
            );
        }
    }

    /**
     * Get advertising campaign performance
     */
    public function getCampaignPerformance($campaignId)
    {
        $stmt = $this->db->prepare("
            SELECT
                ac.*,
                (
                    SELECT COUNT(*)
                    FROM digital_content dc
                    WHERE dc.schedule::text LIKE '%' || ac.campaign_name || '%'
                ) as content_items,
                (
                    SELECT SUM(base_price_per_day)
                    FROM advertising_spaces
                    WHERE space_id IN (
                        SELECT jsonb_object_keys(spaces_allocated)::integer
                        FROM advertising_campaigns
                        WHERE campaign_id = ac.campaign_id
                    )
                ) as space_cost
            FROM advertising_campaigns ac
            WHERE ac.campaign_id = ?
        ");

        $stmt->execute([$campaignId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get lounge utilization report
     */
    public function getLoungeUtilization($startDate, $endDate)
    {
        $stmt = $this->db->prepare("
            SELECT
                vl.lounge_name,
                vl.lounge_type,
                COUNT(lv.visit_id) as total_visits,
                AVG(lv.duration_minutes) as avg_duration,
                SUM(lv.total_charged) as total_revenue,
                AVG(lv.satisfaction_rating) as avg_satisfaction
            FROM vip_lounges vl
            LEFT JOIN lounge_visits lv ON vl.lounge_id = lv.lounge_id
            AND DATE(lv.check_in_time) BETWEEN ? AND ?
            WHERE vl.status = 'active'
            GROUP BY vl.lounge_id, vl.lounge_name, vl.lounge_type
            ORDER BY total_revenue DESC
        ");

        $stmt->execute([$startDate, $endDate]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get database connection (for use by API functions)
     */
    public function getDatabaseConnection()
    {
        return $this->db;
    }
}
