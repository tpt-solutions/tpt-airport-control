<?php
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Middleware.php';

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $request = $_SERVER['REQUEST_URI'];

    // Remove query string and decode
    $path = parse_url($request, PHP_URL_PATH);
    $path = str_replace('/api/payments', '', $path);

    // Get payment ID from path if present
    $paymentId = null;
    if (!empty($path) && $path !== '/') {
        $paymentId = trim($path, '/');
    }

    switch ($method) {
        case 'GET':
            if ($paymentId) {
                getPayment($pdo, $paymentId);
            } else {
                getPayments($pdo);
            }
            break;

        case 'POST':
            if (isset($_GET['action'])) {
                $action = $_GET['action'];
                switch ($action) {
                    case 'webhook':
                        handlePaddleWebhook($pdo);
                        break;
                    case 'stripe_webhook':
                        handleStripeWebhook($pdo);
                        break;
                    case 'refund':
                        processRefund($pdo);
                        break;
                    case 'create_checkout':
                        // Defaults to Paddle; pass payment_provider=stripe for Stripe Checkout
                        $provider = $_GET['provider'] ?? 'paddle';
                        if ($provider === 'stripe') {
                            createStripeCheckoutSession($pdo);
                        } else {
                            createCheckoutSession($pdo);
                        }
                        break;
                    default:
                        http_response_code(400);
                        echo json_encode(['error' => 'Invalid action']);
                        break;
                }
            } else {
                createPayment($pdo);
            }
            break;

        case 'PUT':
            if ($paymentId) {
                updatePayment($pdo, $paymentId);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Payment ID required for update']);
            }
            break;

        case 'DELETE':
            if ($paymentId) {
                deletePayment($pdo, $paymentId);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Payment ID required for deletion']);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    error_log('payments.php unhandled exception: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

function getPayments($pdo) {
    // Check permissions - users can view their own payments, admins can view all
    $currentUser = Auth::getCurrentUser();
    if (!$currentUser) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        return;
    }

    $status = $_GET['status'] ?? null;
    $bookingId = $_GET['booking_id'] ?? null;
    $limit = (int)($_GET['limit'] ?? 50);

    $where = [];
    $params = [];

    // If not admin, only show user's own payments
    if (!Auth::hasPermission('admin', 'users')) {
        $where[] = "p.customer_id = ?";
        $params[] = $currentUser['id'];
    }

    if ($status) {
        $where[] = "p.status = ?";
        $params[] = $status;
    }

    if ($bookingId) {
        $where[] = "p.booking_id = ?";
        $params[] = $bookingId;
    }

    $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

    // Get payments with booking and customer details
    $stmt = $pdo->prepare("
        SELECT
            p.*,
            b.booking_reference,
            b.total_amount as booking_amount,
            b.currency as booking_currency,
            u.username as customer_username,
            u.first_name as customer_first_name,
            u.last_name as customer_last_name
        FROM payments p
        LEFT JOIN bookings b ON p.booking_id = b.id
        LEFT JOIN users u ON p.customer_id = u.id
        {$whereClause}
        ORDER BY p.created_at DESC
        LIMIT ?
    ");
    $params[] = $limit;
    $stmt->execute($params);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['payments' => $payments]);
}

function getPayment($pdo, $paymentId) {
    // Check permissions
    $currentUser = Auth::getCurrentUser();
    if (!$currentUser) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        return;
    }

    $stmt = $pdo->prepare("
        SELECT
            p.*,
            b.booking_reference,
            b.total_amount as booking_amount,
            b.currency as booking_currency,
            u.username as customer_username,
            u.first_name as customer_first_name,
            u.last_name as customer_last_name
        FROM payments p
        LEFT JOIN bookings b ON p.booking_id = b.id
        LEFT JOIN users u ON p.customer_id = u.id
        WHERE p.id = ?
    ");
    $stmt->execute([$paymentId]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        http_response_code(404);
        echo json_encode(['error' => 'Payment not found']);
        return;
    }

    // Check if user can view this payment
    if (!Auth::hasPermission('admin', 'users') && $payment['customer_id'] != $currentUser['id']) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    echo json_encode(['payment' => $payment]);
}

function createPayment($pdo) {
    // Check permissions
    if (!Auth::hasPermission('write', 'bookings')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON data']);
        return;
    }

    // Validate required fields
    $required = ['booking_id', 'amount', 'currency'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Missing required field: $field"]);
            return;
        }
    }

    // Check if booking exists
    $stmt = $pdo->prepare("SELECT id, customer_id, total_amount FROM bookings WHERE id = ?");
    $stmt->execute([$data['booking_id']]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        http_response_code(404);
        echo json_encode(['error' => 'Booking not found']);
        return;
    }

    // Get current user
    $currentUser = Auth::getCurrentUser();

    // Insert payment record
    $stmt = $pdo->prepare("
        INSERT INTO payments (
            booking_id, customer_id, amount, currency, status,
            payment_method, description, metadata
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $data['booking_id'],
        $booking['customer_id'],
        $data['amount'],
        $data['currency'],
        $data['status'] ?? 'pending',
        $data['payment_method'] ?? 'paddle',
        $data['description'] ?? 'Flight booking payment',
        json_encode($data['metadata'] ?? [])
    ]);

    $paymentId = $pdo->lastInsertId();

    // Log payment creation
    require_once __DIR__ . '/../src/Logger.php';
    Logger::info('Payment created: ID ' . $paymentId . ' for booking ' . $data['booking_id'] . ' - Amount: ' . $data['amount'] . ' ' . $data['currency']);

    http_response_code(201);
    echo json_encode([
        'message' => 'Payment created successfully',
        'payment_id' => $paymentId
    ]);
}

function updatePayment($pdo, $paymentId) {
    // Check permissions - admin only
    if (!Auth::hasPermission('admin', 'users')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON data']);
        return;
    }

    // Check if payment exists
    $stmt = $pdo->prepare("SELECT id FROM payments WHERE id = ?");
    $stmt->execute([$paymentId]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Payment not found']);
        return;
    }

    // Build update query
    $updates = [];
    $params = [];

    $allowedFields = [
        'status', 'paddle_transaction_id', 'paddle_subscription_id',
        'stripe_payment_intent_id', 'stripe_session_id',
        'processed_at', 'refunded_amount', 'refund_reason',
    ];

    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $updates[] = "$field = ?";
            $params[] = $data[$field];
        }
    }

    if (empty($updates)) {
        http_response_code(400);
        echo json_encode(['error' => 'No valid fields to update']);
        return;
    }

    $params[] = $paymentId;
    $stmt = $pdo->prepare("UPDATE payments SET " . implode(', ', $updates) . ", updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute($params);

    Logger::info('Payment updated: ID ' . $paymentId);

    echo json_encode(['message' => 'Payment updated successfully']);
}

function deletePayment($pdo, $paymentId) {
    // Check permissions - admin only
    if (!Auth::hasPermission('admin', 'users')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    // Check if payment exists
    $stmt = $pdo->prepare("SELECT status FROM payments WHERE id = ?");
    $stmt->execute([$paymentId]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        http_response_code(404);
        echo json_encode(['error' => 'Payment not found']);
        return;
    }

    if ($payment['status'] === 'completed') {
        http_response_code(409);
        echo json_encode(['error' => 'Cannot delete completed payment']);
        return;
    }

    $stmt = $pdo->prepare("DELETE FROM payments WHERE id = ?");
    $stmt->execute([$paymentId]);

    Logger::info('Payment deleted: ID ' . $paymentId);

    echo json_encode(['message' => 'Payment deleted successfully']);
}

function createCheckoutSession($pdo) {
    // Check permissions
    $currentUser = Auth::getCurrentUser();
    if (!$currentUser) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || !isset($data['booking_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Booking ID required']);
        return;
    }

    // Check if booking exists and belongs to user
    $stmt = $pdo->prepare("SELECT id, total_amount, currency FROM bookings WHERE id = ? AND customer_id = ?");
    $stmt->execute([$data['booking_id'], $currentUser['id']]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        http_response_code(404);
        echo json_encode(['error' => 'Booking not found or access denied']);
        return;
    }

    // Check if payment already exists for this booking
    $stmt = $pdo->prepare("SELECT id FROM payments WHERE booking_id = ? AND status IN ('pending', 'completed')");
    $stmt->execute([$data['booking_id']]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['error' => 'Payment already exists for this booking']);
        return;
    }

    // Paddle API configuration — must be set via environment variables.
    $paddleApiKey = getenv('PADDLE_API_KEY') ?: null;
    $paddleEnvironment = getenv('PADDLE_ENVIRONMENT') ?: 'sandbox';
    if (!$paddleApiKey) {
        http_response_code(503);
        echo json_encode(['error' => 'Payment integration is not configured. Set PADDLE_API_KEY.']);
        return;
    }

    // Create Paddle checkout session
    $checkoutData = [
        'items' => [
            [
                'price_id' => $data['price_id'] ?? 'pri_flight_booking',
                'quantity' => 1
            ]
        ],
        'customer' => [
            'email' => $currentUser['email'] ?? 'customer@example.com',
            'custom_data' => [
                'booking_id' => $data['booking_id'],
                'user_id' => $currentUser['id']
            ]
        ],
        'custom_data' => [
            'booking_id' => $data['booking_id'],
            'user_id' => $currentUser['id']
        ],
        'checkout' => [
            'url' => getenv('APP_URL') . '/checkout/complete',
            'success_url' => getenv('APP_URL') . '/booking/confirmed',
            'cancel_url' => getenv('APP_URL') . '/booking/cancelled'
        ]
    ];

    // In a real implementation, you would make an API call to Paddle
    // For now, we'll simulate the response
    $checkoutSession = [
        'id' => 'cs_' . uniqid(),
        'url' => getenv('APP_URL') . '/checkout/' . uniqid(),
        'status' => 'open'
    ];

    // Create payment record
    $stmt = $pdo->prepare("
        INSERT INTO payments (
            booking_id, customer_id, amount, currency, status,
            payment_method, paddle_checkout_id, description
        ) VALUES (?, ?, ?, ?, 'pending', 'paddle', ?, ?)
    ");
    $stmt->execute([
        $data['booking_id'],
        $currentUser['id'],
        $booking['total_amount'],
        $booking['currency'],
        $checkoutSession['id'],
        'Flight booking payment'
    ]);

    $paymentId = $pdo->lastInsertId();

    Logger::info('Paddle checkout session created: Payment ID ' . $paymentId . ' for booking ' . $data['booking_id']);

    echo json_encode([
        'checkout_session' => $checkoutSession,
        'payment_id' => $paymentId
    ]);
}

function handlePaddleWebhook($pdo) {
    $rawBody = file_get_contents('php://input');
    $webhookData = json_decode($rawBody, true);
    $signature = $_SERVER['HTTP_X_PADDLE_SIGNATURE'] ?? '';

    // Reject immediately if signature verification fails or secret is missing.
    if (!verifyPaddleSignature($rawBody, $signature)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid webhook signature']);
        return;
    }

    if (!$webhookData) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid webhook data']);
        return;
    }

    $eventType = $webhookData['event_type'] ?? '';
    $data = $webhookData['data'] ?? [];

    Logger::info('Paddle webhook received: ' . $eventType);

    switch ($eventType) {
        case 'transaction.completed':
            handleTransactionCompleted($pdo, $data);
            break;

        case 'transaction.updated':
            handleTransactionUpdated($pdo, $data);
            break;

        case 'subscription.created':
            handleSubscriptionCreated($pdo, $data);
            break;

        case 'subscription.updated':
            handleSubscriptionUpdated($pdo, $data);
            break;

        case 'subscription.cancelled':
            handleSubscriptionCancelled($pdo, $data);
            break;

        default:
            Logger::info('Unhandled webhook event: ' . $eventType);
            break;
    }

    echo json_encode(['message' => 'Webhook processed successfully']);
}

function handleTransactionCompleted($pdo, $data) {
    $transactionId = $data['id'] ?? '';
    $customData = $data['custom_data'] ?? [];

    if (isset($customData['booking_id'])) {
        // Update payment status
        $stmt = $pdo->prepare("
            UPDATE payments
            SET status = 'completed', paddle_transaction_id = ?, processed_at = CURRENT_TIMESTAMP
            WHERE booking_id = ? AND status = 'pending'
        ");
        $stmt->execute([$transactionId, $customData['booking_id']]);

        // Update booking status
        $stmt = $pdo->prepare("UPDATE bookings SET status = 'confirmed' WHERE id = ?");
        $stmt->execute([$customData['booking_id']]);

        Logger::info('Payment completed for booking: ' . $customData['booking_id']);
    }
}

function handleTransactionUpdated($pdo, $data) {
    $transactionId = $data['id'] ?? '';
    $status = $data['status'] ?? '';

    // Update payment status
    $stmt = $pdo->prepare("
        UPDATE payments
        SET status = ?, updated_at = CURRENT_TIMESTAMP
        WHERE paddle_transaction_id = ?
    ");
    $stmt->execute([$status, $transactionId]);

    Logger::info('Payment updated: Transaction ' . $transactionId . ' - Status: ' . $status);
}

function handleSubscriptionCreated($pdo, $data) {
    $subscriptionId = $data['id'] ?? '';
    $customData = $data['custom_data'] ?? [];

    if (isset($customData['booking_id'])) {
        // Update payment with subscription info
        $stmt = $pdo->prepare("
            UPDATE payments
            SET paddle_subscription_id = ?, status = 'active'
            WHERE booking_id = ?
        ");
        $stmt->execute([$subscriptionId, $customData['booking_id']]);

        Logger::info('Subscription created for booking: ' . $customData['booking_id']);
    }
}

function handleSubscriptionUpdated($pdo, $data) {
    $subscriptionId = $data['id'] ?? '';
    $status = $data['status'] ?? '';

    // Update payment status
    $stmt = $pdo->prepare("
        UPDATE payments
        SET status = ?, updated_at = CURRENT_TIMESTAMP
        WHERE paddle_subscription_id = ?
    ");
    $stmt->execute([$status, $subscriptionId]);

    Logger::info('Subscription updated: ' . $subscriptionId . ' - Status: ' . $status);
}

function handleSubscriptionCancelled($pdo, $data) {
    $subscriptionId = $data['id'] ?? '';

    // Update payment status
    $stmt = $pdo->prepare("
        UPDATE payments
        SET status = 'cancelled', updated_at = CURRENT_TIMESTAMP
        WHERE paddle_subscription_id = ?
    ");
    $stmt->execute([$subscriptionId]);

    Logger::info('Subscription cancelled: ' . $subscriptionId);
}

function processRefund($pdo) {
    // Check permissions — admin only
    if (!Auth::hasPermission('admin', 'users')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    $currentUser = Auth::getCurrentUser();
    $processedBy  = $currentUser['user_id'] ?? $currentUser['id'] ?? null;

    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || !isset($data['payment_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Payment ID required']);
        return;
    }

    $paymentId    = $data['payment_id'];
    $refundAmount = $data['amount'] ?? null;
    $reason       = trim($data['reason'] ?? 'Customer request');

    if (empty($reason)) {
        http_response_code(400);
        echo json_encode(['error' => 'Refund reason is required']);
        return;
    }

    // Get payment details
    $stmt = $pdo->prepare("SELECT * FROM payments WHERE id = ?");
    $stmt->execute([$paymentId]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        http_response_code(404);
        echo json_encode(['error' => 'Payment not found']);
        return;
    }

    if ($payment['status'] !== 'completed') {
        http_response_code(409);
        echo json_encode(['error' => 'Can only refund completed payments']);
        return;
    }

    // Enforce refund window: no refunds more than REFUND_WINDOW_DAYS days after payment
    $refundWindowDays = (int)(getenv('REFUND_WINDOW_DAYS') ?: 30);
    $paymentDate      = strtotime($payment['created_at'] ?? $payment['processed_at'] ?? 'now');
    if ($paymentDate && (time() - $paymentDate) > ($refundWindowDays * 86400)) {
        http_response_code(409);
        echo json_encode(['error' => "Refund window of {$refundWindowDays} days has passed"]);
        return;
    }

    $actualRefundAmount = $refundAmount ?? $payment['amount'];

    if ($actualRefundAmount > $payment['amount']) {
        http_response_code(400);
        echo json_encode(['error' => 'Refund amount cannot exceed payment amount']);
        return;
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            UPDATE payments
            SET refunded_amount = ?,
                refund_reason   = ?,
                status          = 'refunded',
                processed_by    = ?,
                updated_at      = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([$actualRefundAmount, $reason, $processedBy, $paymentId]);

        if ($actualRefundAmount >= $payment['amount']) {
            $stmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?");
            $stmt->execute([$payment['booking_id']]);
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        Logger::error('Refund transaction failed', ['payment_id' => $paymentId, 'error' => $e->getMessage()]);
        http_response_code(500);
        echo json_encode(['error' => 'An internal error occurred. Please try again.']);
        return;
    }

    Logger::info('Refund processed', [
        'payment_id'    => $paymentId,
        'amount'        => $actualRefundAmount,
        'processed_by'  => $processedBy,
        'reason'        => $reason,
    ]);

    echo json_encode([
        'message'       => 'Refund processed successfully',
        'refund_amount' => $actualRefundAmount
    ]);
}

// Verify Paddle webhook signature using the raw request body.
// Paddle signs the raw body bytes, so we must compare against those — not re-encoded JSON.
function verifyPaddleSignature(string $rawBody, string $signature): bool {
    $secretKey = getenv('PADDLE_WEBHOOK_SECRET');
    if (!$secretKey || !$signature) {
        return false;
    }
    $expectedSignature = hash_hmac('sha256', $rawBody, $secretKey);
    return hash_equals($expectedSignature, $signature);
}

// Helper function to get payment statistics
function getPaymentStatistics($pdo, $dateFrom = null, $dateTo = null) {
    $whereClause = "";
    $params = [];

    if ($dateFrom && $dateTo) {
        $whereClause = "WHERE p.created_at BETWEEN ? AND ?";
        $params = [$dateFrom, $dateTo];
    }

    $stmt = $pdo->prepare("
        SELECT
            p.status,
            COUNT(*) as count,
            SUM(p.amount) as total_amount,
            AVG(p.amount) as avg_amount
        FROM payments p
        {$whereClause}
        GROUP BY p.status
        ORDER BY p.status
    ");
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Helper function to get revenue by currency
function getRevenueByCurrency($pdo, $dateFrom = null, $dateTo = null) {
    $whereClause = "";
    $params = [];

    if ($dateFrom && $dateTo) {
        $whereClause = "WHERE p.created_at BETWEEN ? AND ?";
        $params = [$dateFrom, $dateTo];
    }

    $stmt = $pdo->prepare("
        SELECT
            p.currency,
            COUNT(*) as transaction_count,
            SUM(p.amount) as total_revenue,
            AVG(p.amount) as avg_transaction
        FROM payments p
        WHERE p.status = 'completed'
        {$whereClause}
        GROUP BY p.currency
        ORDER BY total_revenue DESC
    ");
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ─── Stripe payment provider ──────────────────────────────────────────────────

/**
 * Create a Stripe Checkout Session for a booking.
 *
 * Requires the Stripe PHP SDK: composer require stripe/stripe-php
 * Configure STRIPE_SECRET_KEY and STRIPE_PUBLISHABLE_KEY in the environment.
 */
function createStripeCheckoutSession($pdo) {
    $currentUser = Auth::getCurrentUser();
    if (!$currentUser) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        return;
    }

    $stripeSecretKey = getenv('STRIPE_SECRET_KEY') ?: null;
    if (!$stripeSecretKey) {
        http_response_code(503);
        echo json_encode(['error' => 'Stripe is not configured. Set STRIPE_SECRET_KEY.']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !isset($data['booking_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'booking_id required']);
        return;
    }

    $stmt = $pdo->prepare("SELECT id, total_amount, currency FROM bookings WHERE id = ? AND customer_id = ?");
    $stmt->execute([$data['booking_id'], $currentUser['id']]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$booking) {
        http_response_code(404);
        echo json_encode(['error' => 'Booking not found or access denied']);
        return;
    }

    // Prevent duplicate checkout sessions for the same booking
    $dupStmt = $pdo->prepare("SELECT id FROM payments WHERE booking_id = ? AND status IN ('pending', 'completed')");
    $dupStmt->execute([$data['booking_id']]);
    if ($dupStmt->fetch()) {
        http_response_code(409);
        echo json_encode(['error' => 'A payment already exists for this booking']);
        return;
    }

    $stripeAvailable = file_exists(__DIR__ . '/../vendor/stripe/stripe-php/init.php');
    if (!$stripeAvailable) {
        http_response_code(503);
        echo json_encode(['error' => 'Stripe PHP SDK not installed. Run: composer require stripe/stripe-php']);
        return;
    }

    require_once __DIR__ . '/../vendor/stripe/stripe-php/init.php';
    \Stripe\Stripe::setApiKey($stripeSecretKey);

    // Stripe amounts are in the smallest currency unit (cents)
    $amountCents = (int) round((float) $booking['total_amount'] * 100);
    $appUrl = rtrim(getenv('APP_URL') ?: 'http://localhost', '/');

    $session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card'],
        'line_items' => [[
            'price_data' => [
                'currency'     => strtolower($booking['currency'] ?? 'usd'),
                'unit_amount'  => $amountCents,
                'product_data' => ['name' => 'Flight Booking #' . $data['booking_id']],
            ],
            'quantity' => 1,
        ]],
        'mode'        => 'payment',
        'success_url' => $appUrl . '/booking/confirmed?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url'  => $appUrl . '/booking/cancelled',
        'metadata'    => [
            'booking_id' => $data['booking_id'],
            'user_id'    => $currentUser['id'],
        ],
    ]);

    // Record the pending payment immediately
    $stmt = $pdo->prepare("
        INSERT INTO payments (booking_id, customer_id, amount, currency, status, payment_method, stripe_session_id, description)
        VALUES (?, ?, ?, ?, 'pending', 'stripe', ?, ?)
    ");
    $stmt->execute([
        $data['booking_id'],
        $currentUser['id'],
        $booking['total_amount'],
        $booking['currency'],
        $session->id,
        'Flight booking payment via Stripe',
    ]);
    $paymentId = $pdo->lastInsertId();

    require_once __DIR__ . '/../src/Logger.php';
    Logger::info('Stripe checkout session created', ['payment_id' => $paymentId, 'session_id' => $session->id]);

    echo json_encode([
        'checkout_url'  => $session->url,
        'session_id'    => $session->id,
        'payment_id'    => $paymentId,
        'publishable_key' => getenv('STRIPE_PUBLISHABLE_KEY') ?: null,
    ]);
}

/**
 * Handle incoming Stripe webhook events.
 *
 * Routes: POST /api/payments.php?action=stripe_webhook
 * Set STRIPE_WEBHOOK_SECRET to the signing secret from the Stripe dashboard.
 */
function handleStripeWebhook($pdo) {
    $rawBody  = file_get_contents('php://input');
    $sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

    $webhookSecret = getenv('STRIPE_WEBHOOK_SECRET') ?: null;
    if (!$webhookSecret) {
        http_response_code(400);
        echo json_encode(['error' => 'STRIPE_WEBHOOK_SECRET is not configured']);
        return;
    }

    $stripeAvailable = file_exists(__DIR__ . '/../vendor/stripe/stripe-php/init.php');
    if (!$stripeAvailable) {
        http_response_code(503);
        echo json_encode(['error' => 'Stripe PHP SDK not installed']);
        return;
    }

    require_once __DIR__ . '/../vendor/stripe/stripe-php/init.php';

    try {
        $event = \Stripe\Webhook::constructEvent($rawBody, $sigHeader, $webhookSecret);
    } catch (\Stripe\Exception\SignatureVerificationException $e) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid Stripe webhook signature']);
        return;
    }

    require_once __DIR__ . '/../src/Logger.php';

    switch ($event->type) {
        case 'checkout.session.completed':
            $session = $event->data->object;
            $bookingId = $session->metadata->booking_id ?? null;
            if ($bookingId) {
                $stmt = $pdo->prepare("
                    UPDATE payments
                    SET status = 'completed', stripe_session_id = ?, stripe_payment_intent_id = ?, processed_at = CURRENT_TIMESTAMP
                    WHERE booking_id = ? AND payment_method = 'stripe' AND status = 'pending'
                ");
                $stmt->execute([$session->id, $session->payment_intent, $bookingId]);
                $stmt2 = $pdo->prepare("UPDATE bookings SET payment_status = 'paid' WHERE id = ?");
                $stmt2->execute([$bookingId]);
                Logger::info('Stripe checkout.session.completed', ['booking_id' => $bookingId]);
            }
            break;

        case 'payment_intent.payment_failed':
            $intent = $event->data->object;
            $stmt = $pdo->prepare("
                UPDATE payments SET status = 'failed', updated_at = CURRENT_TIMESTAMP
                WHERE stripe_payment_intent_id = ?
            ");
            $stmt->execute([$intent->id]);
            Logger::info('Stripe payment_intent.payment_failed', ['intent_id' => $intent->id]);
            break;

        case 'charge.refunded':
            $charge = $event->data->object;
            $refundAmount = ($charge->amount_refunded ?? 0) / 100;
            $stmt = $pdo->prepare("
                UPDATE payments SET status = 'refunded', refunded_amount = ?, updated_at = CURRENT_TIMESTAMP
                WHERE stripe_payment_intent_id = ?
            ");
            $stmt->execute([$refundAmount, $charge->payment_intent]);
            Logger::info('Stripe charge.refunded', ['payment_intent' => $charge->payment_intent]);
            break;

        default:
            Logger::info('Stripe webhook event received (unhandled)', ['type' => $event->type]);
            break;
    }

    echo json_encode(['status' => 'success']);
}
?>
