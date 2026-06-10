<?php
// Webhooks arrive from Stripe's servers, not a browser — CSRF does not apply.
define('CSRF_EXEMPT', true);
require_once __DIR__ . '/../cors.php';

/**
 * Stripe Billing Webhook Endpoint
 *
 * Handles payment events, subscription updates, and billing webhooks
 */

require_once __DIR__ . '/../../src/Logger.php';
require_once __DIR__ . '/../../services/SubscriptionService.php';
require_once __DIR__ . '/../../services/UsageMeteringService.php';

header('Content-Type: application/json');

$logger = Logger::getInstance();

try {
    // Get webhook payload
    $payload = @file_get_contents('php://input');
    $signature = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
    
    if (empty($payload)) {
        throw new Exception('Empty payload');
    }
    
    $logger->info('Stripe webhook received', [
        'signature_present' => !empty($signature),
        'payload_size' => strlen($payload)
    ]);
    
    $subscriptionService = new SubscriptionService();
    $result = $subscriptionService->handleWebhook($payload, $signature);
    
    $logger->info('Stripe webhook processed successfully');
    
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'message' => 'Webhook processed'
    ]);
    
} catch (Exception $e) {
    $logger->error('Stripe webhook processing failed', [
        'error' => 'An internal error occurred'
    ]);

    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Webhook processing failed'
    ]);
}

?>