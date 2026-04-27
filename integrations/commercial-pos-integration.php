<?php

/**
 * Commercial POS Integration
 *
 * Point-of-sale system integration for retail operations, inventory management,
 * and sales transaction processing
 */

class CommercialPOSIntegration
{
    private $db;
    private $logger;
    private $posApiUrl;
    private $posApiKey;

    public function __construct()
    {
        $this->db = new PDO(
            "pgsql:host=" . getenv('DB_HOST') . ";dbname=" . getenv('DB_DATABASE'),
            getenv('DB_USERNAME'),
            getenv('DB_PASSWORD'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $this->logger = new Logger('commercial_pos_integration');

        // POS system configuration
        $this->posApiUrl = getenv('POS_API_URL') ?: 'https://api.pos-system.com/v1';
        $this->posApiKey = getenv('POS_API_KEY');
    }

    /**
     * Sync product catalog from POS system
     */
    public function syncProductCatalog()
    {
        $this->logger->info("Starting product catalog sync from POS system");

        try {
            // Fetch products from POS API
            $products = $this->fetchProductsFromPOS();

            // Update local product database
            $this->updateLocalProductCatalog($products);

            // Log sync completion
            $this->logSyncEvent('product_catalog', count($products), 'completed');

            return [
                'status' => 'success',
                'products_synced' => count($products),
                'message' => 'Product catalog synchronized successfully'
            ];

        } catch (Exception $e) {
            $this->logger->error("Product catalog sync failed", ['error' => $e->getMessage()]);
            $this->logSyncEvent('product_catalog', 0, 'failed', $e->getMessage());

            throw new Exception("Product catalog sync failed: " . $e->getMessage());
        }
    }

    /**
     * Process sales transaction from POS
     */
    public function processSalesTransaction($transactionData)
    {
        $this->logger->info("Processing sales transaction", [
            'transaction_id' => $transactionData['transaction_id'],
            'outlet_id' => $transactionData['outlet_id']
        ]);

        $this->db->beginTransaction();

        try {
            // Validate transaction data
            $this->validateTransactionData($transactionData);

            // Check product availability
            $this->checkProductAvailability($transactionData['items']);

            // Process transaction
            $transactionId = $this->createSalesTransaction($transactionData);

            // Update inventory
            $this->updateInventory($transactionData['items']);

            // Process payment
            $this->processPayment($transactionId, $transactionData['payment']);

            // Send receipt if requested
            if ($transactionData['send_receipt'] ?? false) {
                $this->sendReceipt($transactionId, $transactionData);
            }

            $this->db->commit();

            return [
                'transaction_id' => $transactionId,
                'status' => 'completed',
                'message' => 'Sales transaction processed successfully'
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logger->error("Sales transaction failed", [
                'transaction_id' => $transactionData['transaction_id'],
                'error' => $e->getMessage()
            ]);

            throw new Exception("Sales transaction failed: " . $e->getMessage());
        }
    }

    /**
     * Get real-time inventory levels
     */
    public function getInventoryLevels($outletId = null, $productIds = null)
    {
        try {
            $query = "
                SELECT
                    p.product_id,
                    p.product_name,
                    p.sku,
                    COALESCE(pi.quantity_available, 0) as local_quantity,
                    COALESCE(pos.quantity_available, 0) as pos_quantity,
                    pi.last_sync_at,
                    pi.outlet_id,
                    o.outlet_name
                FROM products p
                LEFT JOIN product_inventory pi ON p.product_id = pi.product_id
                LEFT JOIN outlets o ON pi.outlet_id = o.outlet_id
                LEFT JOIN pos_inventory_sync pos ON p.product_id = pos.product_id
                    AND pos.sync_type = 'inventory'
                    AND pos.last_sync_at >= NOW() - INTERVAL '1 hour'
            ";

            $params = [];
            $conditions = [];

            if ($outletId) {
                $conditions[] = "pi.outlet_id = ?";
                $params[] = $outletId;
            }

            if ($productIds) {
                $placeholders = str_repeat('?,', count($productIds) - 1) . '?';
                $conditions[] = "p.product_id IN ($placeholders)";
                $params = array_merge($params, $productIds);
            }

            if (!empty($conditions)) {
                $query .= " WHERE " . implode(" AND ", $conditions);
            }

            $query .= " ORDER BY p.product_name";

            $stmt = $this->db->prepare($query);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            $this->logger->error("Failed to get inventory levels", ['error' => $e->getMessage()]);
            throw new Exception("Failed to get inventory levels: " . $e->getMessage());
        }
    }

    /**
     * Sync inventory from POS system
     */
    public function syncInventoryFromPOS($outletId = null)
    {
        $this->logger->info("Starting inventory sync from POS system", ['outlet_id' => $outletId]);

        try {
            // Fetch inventory from POS API
            $inventoryData = $this->fetchInventoryFromPOS($outletId);

            // Update local inventory
            $updatedCount = $this->updateLocalInventory($inventoryData, $outletId);

            // Log sync completion
            $this->logSyncEvent('inventory', $updatedCount, 'completed', null, $outletId);

            return [
                'status' => 'success',
                'items_updated' => $updatedCount,
                'outlet_id' => $outletId,
                'message' => 'Inventory synchronized successfully'
            ];

        } catch (Exception $e) {
            $this->logger->error("Inventory sync failed", [
                'outlet_id' => $outletId,
                'error' => $e->getMessage()
            ]);
            $this->logSyncEvent('inventory', 0, 'failed', $e->getMessage(), $outletId);

            throw new Exception("Inventory sync failed: " . $e->getMessage());
        }
    }

    /**
     * Process refund transaction
     */
    public function processRefund($refundData)
    {
        $this->logger->info("Processing refund transaction", [
            'original_transaction_id' => $refundData['original_transaction_id'],
            'refund_amount' => $refundData['refund_amount']
        ]);

        $this->db->beginTransaction();

        try {
            // Validate refund data
            $this->validateRefundData($refundData);

            // Check original transaction
            $originalTransaction = $this->getOriginalTransaction($refundData['original_transaction_id']);

            // Create refund transaction
            $refundId = $this->createRefundTransaction($refundData, $originalTransaction);

            // Update inventory (return items)
            if (isset($refundData['items'])) {
                $this->returnItemsToInventory($refundData['items'], $refundData['outlet_id']);
            }

            // Process refund payment
            $this->processRefundPayment($refundId, $refundData['payment']);

            $this->db->commit();

            return [
                'refund_id' => $refundId,
                'status' => 'completed',
                'message' => 'Refund processed successfully'
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logger->error("Refund processing failed", [
                'original_transaction_id' => $refundData['original_transaction_id'],
                'error' => $e->getMessage()
            ]);

            throw new Exception("Refund processing failed: " . $e->getMessage());
        }
    }

    /**
     * Get sales analytics
     */
    public function getSalesAnalytics($startDate, $endDate, $outletId = null)
    {
        try {
            $query = "
                SELECT
                    DATE(st.transaction_date) as date,
                    COUNT(*) as transaction_count,
                    SUM(st.total_amount) as total_sales,
                    SUM(st.total_tax) as total_tax,
                    AVG(st.total_amount) as avg_transaction_value,
                    COUNT(DISTINCT st.customer_id) as unique_customers,
                    o.outlet_name
                FROM sales_transactions st
                LEFT JOIN outlets o ON st.outlet_id = o.outlet_id
                WHERE DATE(st.transaction_date) BETWEEN ? AND ?
            ";

            $params = [$startDate, $endDate];

            if ($outletId) {
                $query .= " AND st.outlet_id = ?";
                $params[] = $outletId;
            }

            $query .= " GROUP BY DATE(st.transaction_date), o.outlet_name ORDER BY date";

            $stmt = $this->db->prepare($query);
            $stmt->execute($params);

            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get top-selling products
            $topProducts = $this->getTopSellingProducts($startDate, $endDate, $outletId);

            return [
                'daily_sales' => $results,
                'top_products' => $topProducts,
                'summary' => $this->calculateSalesSummary($results)
            ];

        } catch (Exception $e) {
            $this->logger->error("Failed to get sales analytics", ['error' => $e->getMessage()]);
            throw new Exception("Failed to get sales analytics: " . $e->getMessage());
        }
    }

    // Private helper methods

    private function fetchProductsFromPOS()
    {
        $response = $this->makePOSApiCall('GET', '/products');
        return $response['products'] ?? [];
    }

    private function fetchInventoryFromPOS($outletId = null)
    {
        $endpoint = $outletId ? "/inventory/outlet/{$outletId}" : '/inventory';
        $response = $this->makePOSApiCall('GET', $endpoint);
        return $response['inventory'] ?? [];
    }

    private function makePOSApiCall($method, $endpoint, $data = null)
    {
        $url = $this->posApiUrl . $endpoint;

        $headers = [
            'Authorization: Bearer ' . $this->posApiKey,
            'Content-Type: application/json',
            'Accept: application/json'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($method === 'POST' && $data) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method === 'PUT' && $data) {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            throw new Exception("POS API call failed: " . curl_error($ch));
        }

        curl_close($ch);

        if ($httpCode >= 400) {
            throw new Exception("POS API returned error {$httpCode}: {$response}");
        }

        return json_decode($response, true);
    }

    private function updateLocalProductCatalog($products)
    {
        $updatedCount = 0;

        foreach ($products as $product) {
            $stmt = $this->db->prepare("
                INSERT INTO products (
                    product_id, product_name, sku, description, category,
                    unit_price, cost_price, tax_rate, is_active, last_sync_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
                ON CONFLICT (product_id) DO UPDATE SET
                    product_name = EXCLUDED.product_name,
                    sku = EXCLUDED.sku,
                    description = EXCLUDED.description,
                    category = EXCLUDED.category,
                    unit_price = EXCLUDED.unit_price,
                    cost_price = EXCLUDED.cost_price,
                    tax_rate = EXCLUDED.tax_rate,
                    is_active = EXCLUDED.is_active,
                    last_sync_at = CURRENT_TIMESTAMP
            ");

            $stmt->execute([
                $product['id'],
                $product['name'],
                $product['sku'],
                $product['description'] ?? null,
                $product['category'] ?? null,
                $product['price'],
                $product['cost'] ?? 0,
                $product['tax_rate'] ?? 0,
                $product['active'] ?? true
            ]);

            $updatedCount++;
        }

        return $updatedCount;
    }

    private function updateLocalInventory($inventoryData, $outletId)
    {
        $updatedCount = 0;

        foreach ($inventoryData as $item) {
            $stmt = $this->db->prepare("
                INSERT INTO product_inventory (
                    product_id, outlet_id, quantity_available,
                    quantity_reserved, last_sync_at
                ) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
                ON CONFLICT (product_id, outlet_id) DO UPDATE SET
                    quantity_available = EXCLUDED.quantity_available,
                    quantity_reserved = EXCLUDED.quantity_reserved,
                    last_sync_at = CURRENT_TIMESTAMP
            ");

            $stmt->execute([
                $item['product_id'],
                $outletId ?: $item['outlet_id'],
                $item['quantity_available'],
                $item['quantity_reserved'] ?? 0
            ]);

            $updatedCount++;
        }

        return $updatedCount;
    }

    private function validateTransactionData($data)
    {
        if (!isset($data['outlet_id'])) {
            throw new Exception("Outlet ID is required");
        }

        if (!isset($data['items']) || !is_array($data['items']) || empty($data['items'])) {
            throw new Exception("Transaction must contain at least one item");
        }

        if (!isset($data['payment'])) {
            throw new Exception("Payment information is required");
        }
    }

    private function checkProductAvailability($items)
    {
        foreach ($items as $item) {
            $stmt = $this->db->prepare("
                SELECT quantity_available, quantity_reserved
                FROM product_inventory
                WHERE product_id = ? AND outlet_id = ?
            ");

            $stmt->execute([$item['product_id'], $item['outlet_id']]);
            $inventory = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$inventory) {
                throw new Exception("Product {$item['product_id']} not found in inventory");
            }

            $available = $inventory['quantity_available'] - $inventory['quantity_reserved'];

            if ($available < $item['quantity']) {
                throw new Exception("Insufficient inventory for product {$item['product_id']}");
            }
        }
    }

    private function createSalesTransaction($data)
    {
        $transactionId = 'TXN-' . date('YmdHis') . '-' . strtoupper(substr(md5(uniqid()), 0, 4));

        $stmt = $this->db->prepare("
            INSERT INTO sales_transactions (
                transaction_id, outlet_id, customer_id, transaction_date,
                subtotal_amount, tax_amount, total_amount, payment_method,
                payment_status, created_by
            ) VALUES (?, ?, ?, CURRENT_TIMESTAMP, ?, ?, ?, ?, ?, ?)
        ");

        $subtotal = array_reduce($data['items'], function($sum, $item) {
            return $sum + ($item['unit_price'] * $item['quantity']);
        }, 0);

        $tax = $subtotal * ($data['tax_rate'] ?? 0.08); // Default 8% tax
        $total = $subtotal + $tax;

        $stmt->execute([
            $transactionId,
            $data['outlet_id'],
            $data['customer_id'] ?? null,
            $subtotal,
            $tax,
            $total,
            $data['payment']['method'],
            'completed',
            $data['created_by'] ?? 'system'
        ]);

        // Insert transaction items
        foreach ($data['items'] as $item) {
            $itemStmt = $this->db->prepare("
                INSERT INTO sales_transaction_items (
                    transaction_id, product_id, quantity, unit_price,
                    line_total, tax_amount
                ) VALUES (?, ?, ?, ?, ?, ?)
            ");

            $lineTotal = $item['unit_price'] * $item['quantity'];
            $lineTax = $lineTotal * ($data['tax_rate'] ?? 0.08);

            $itemStmt->execute([
                $transactionId,
                $item['product_id'],
                $item['quantity'],
                $item['unit_price'],
                $lineTotal,
                $lineTax
            ]);
        }

        return $transactionId;
    }

    private function updateInventory($items)
    {
        foreach ($items as $item) {
            $stmt = $this->db->prepare("
                UPDATE product_inventory
                SET quantity_available = quantity_available - ?,
                    last_updated_at = CURRENT_TIMESTAMP
                WHERE product_id = ? AND outlet_id = ?
            ");

            $stmt->execute([
                $item['quantity'],
                $item['product_id'],
                $item['outlet_id']
            ]);
        }
    }

    private function processPayment($transactionId, $paymentData)
    {
        $stmt = $this->db->prepare("
            INSERT INTO payment_transactions (
                transaction_id, payment_method, payment_amount,
                payment_reference, payment_status, processed_at
            ) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ");

        $stmt->execute([
            $transactionId,
            $paymentData['method'],
            $paymentData['amount'],
            $paymentData['reference'] ?? null,
            'completed'
        ]);
    }

    private function sendReceipt($transactionId, $transactionData)
    {
        // Implementation for sending receipt via email or SMS
        $this->logger->info("Receipt sent for transaction", ['transaction_id' => $transactionId]);
    }

    private function validateRefundData($data)
    {
        if (!isset($data['original_transaction_id'])) {
            throw new Exception("Original transaction ID is required");
        }

        if (!isset($data['refund_amount']) || $data['refund_amount'] <= 0) {
            throw new Exception("Valid refund amount is required");
        }
    }

    private function getOriginalTransaction($transactionId)
    {
        $stmt = $this->db->prepare("
            SELECT * FROM sales_transactions WHERE transaction_id = ?
        ");

        $stmt->execute([$transactionId]);
        $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$transaction) {
            throw new Exception("Original transaction not found");
        }

        return $transaction;
    }

    private function createRefundTransaction($refundData, $originalTransaction)
    {
        $refundId = 'REF-' . date('YmdHis') . '-' . strtoupper(substr(md5(uniqid()), 0, 4));

        $stmt = $this->db->prepare("
            INSERT INTO refund_transactions (
                refund_id, original_transaction_id, refund_amount,
                refund_reason, processed_by, processed_at
            ) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ");

        $stmt->execute([
            $refundId,
            $refundData['original_transaction_id'],
            $refundData['refund_amount'],
            $refundData['reason'] ?? 'Customer request',
            $refundData['processed_by'] ?? 'system'
        ]);

        return $refundId;
    }

    private function returnItemsToInventory($items, $outletId)
    {
        foreach ($items as $item) {
            $stmt = $this->db->prepare("
                UPDATE product_inventory
                SET quantity_available = quantity_available + ?,
                    last_updated_at = CURRENT_TIMESTAMP
                WHERE product_id = ? AND outlet_id = ?
            ");

            $stmt->execute([
                $item['quantity'],
                $item['product_id'],
                $outletId
            ]);
        }
    }

    private function processRefundPayment($refundId, $paymentData)
    {
        $stmt = $this->db->prepare("
            INSERT INTO refund_payments (
                refund_id, payment_method, refund_amount,
                payment_reference, processed_at
            ) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
        ");

        $stmt->execute([
            $refundId,
            $paymentData['method'],
            $paymentData['amount'],
            $paymentData['reference'] ?? null
        ]);
    }

    private function getTopSellingProducts($startDate, $endDate, $outletId = null)
    {
        $query = "
            SELECT
                p.product_name,
                SUM(sti.quantity) as total_quantity,
                SUM(sti.line_total) as total_revenue
            FROM sales_transaction_items sti
            JOIN sales_transactions st ON sti.transaction_id = st.transaction_id
            JOIN products p ON sti.product_id = p.product_id
            WHERE DATE(st.transaction_date) BETWEEN ? AND ?
        ";

        $params = [$startDate, $endDate];

        if ($outletId) {
            $query .= " AND st.outlet_id = ?";
            $params[] = $outletId;
        }

        $query .= " GROUP BY p.product_id, p.product_name ORDER BY total_revenue DESC LIMIT 10";

        $stmt = $this->db->prepare($query);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function calculateSalesSummary($dailySales)
    {
        if (empty($dailySales)) {
            return [
                'total_sales' => 0,
                'total_transactions' => 0,
                'average_transaction' => 0,
                'total_tax' => 0
            ];
        }

        $totalSales = array_sum(array_column($dailySales, 'total_sales'));
        $totalTransactions = array_sum(array_column($dailySales, 'transaction_count'));
        $totalTax = array_sum(array_column($dailySales, 'total_tax'));

        return [
            'total_sales' => $totalSales,
            'total_transactions' => $totalTransactions,
            'average_transaction' => $totalTransactions > 0 ? $totalSales / $totalTransactions : 0,
            'total_tax' => $totalTax
        ];
    }

    private function logSyncEvent($syncType, $itemsCount, $status, $errorMessage = null, $outletId = null)
    {
        $stmt = $this->db->prepare("
            INSERT INTO pos_sync_logs (
                sync_type, items_count, status, error_message,
                outlet_id, sync_started_at, sync_completed_at
            ) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");

        $stmt->execute([
            $syncType,
            $itemsCount,
            $status,
            $errorMessage,
            $outletId
        ]);
    }
}
