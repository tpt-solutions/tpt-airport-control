<?php
/**
 * Subscription API for Airport Operations Simulator
 *
 * Handles subscription management, payments, and premium features
 */

require_once __DIR__ . '/../src/Config.php';
require_once __DIR__ . '/../src/Logger.php';
require_once __DIR__ . '/../services/SubscriptionService.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

class SubscriptionAPI
{
    private $subscriptionService;
    private $logger;

    public function __construct()
    {
        $this->logger = new Logger('subscription_api');
        $this->subscriptionService = new SubscriptionService();
    }

    public function handleRequest()
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = $_SERVER['REQUEST_URI'];

        // Extract endpoint from path
        $endpoint = str_replace('/api/subscription/', '', parse_url($path, PHP_URL_PATH));
        $endpoint = trim($endpoint, '/');

        switch ($method) {
            case 'GET':
                $this->handleGet($endpoint);
                break;
            case 'POST':
                $this->handlePost($endpoint);
                break;
            case 'PUT':
                $this->handlePut($endpoint);
                break;
            case 'DELETE':
                $this->handleDelete($endpoint);
                break;
            default:
                $this->sendError('Method not allowed', 405);
        }
    }

    private function handleGet($endpoint)
    {
        switch ($endpoint) {
            case '':
            case 'plans':
                $this->getPlans();
                break;
            case 'status':
                $this->getSubscriptionStatus();
                break;
            case 'usage':
                $this->getUsage();
                break;
            case 'history':
                $this->getPaymentHistory();
                break;
            case 'config':
                $this->getStripeConfig();
                break;
            default:
                $this->sendError('Endpoint not found', 404);
        }
    }

    private function handlePost($endpoint)
    {
        $data = json_decode(file_get_contents('php://input'), true);

        switch ($endpoint) {
            case 'create':
                $this->createSubscription($data);
                break;
            case 'webhook':
                $this->handleWebhook();
                break;
            case 'upgrade':
                $this->upgradeSubscription($data);
                break;
            default:
                $this->sendError('Endpoint not found', 404);
        }
    }

    private function handlePut($endpoint)
    {
        $data = json_decode(file_get_contents('php://input'), true);

        switch ($endpoint) {
            case 'cancel':
                $this->cancelSubscription();
                break;
            case 'reactivate':
                $this->reactivateSubscription();
                break;
            default:
                $this->sendError('Endpoint not found', 404);
        }
    }

    private function getPlans()
    {
        try {
            $plans = $this->subscriptionService->getPlans();

            // Format plans for frontend
            $formattedPlans = [];
            foreach ($plans as $planId => $plan) {
                $formattedPlans[] = [
                    'id' => $planId,
                    'name' => $plan['name'],
                    'description' => $plan['description'] ?? '',
                    'price' => $plan['price'],
                    'currency' => $plan['currency'],
                    'price_formatted' => $this->formatPrice($plan['price'], $plan['currency']),
                    'features' => $plan['features'],
                    'popular' => $planId === 'premium', // Mark premium as most popular
                    'stripe_price_id' => $plan['stripe_price_id']
                ];
            }

            $this->sendResponse(['plans' => $formattedPlans]);
        } catch (Exception $e) {
            $this->logger->error("Failed to get plans", ['error' => $e->getMessage()]);
            $this->sendError('Failed to retrieve subscription plans', 500);
        }
    }

    private function getSubscriptionStatus()
    {
        $userId = $this->getCurrentUserId();

        try {
            $subscription = $this->subscriptionService->getUserSubscription($userId);

            if (!$subscription) {
                // Return free plan info
                $freePlan = $this->subscriptionService->getPlans()['free'];
                $subscription = [
                    'plan_id' => 'free',
                    'plan_name' => $freePlan['name'],
                    'status' => 'active',
                    'features' => $freePlan['features'],
                    'current_period_end' => null
                ];
            }

            // Add feature access information
            $subscription['feature_access'] = [
                'can_access_advanced_scenarios' => $this->subscriptionService->hasFeatureAccess($userId, 'advanced_scenarios'),
                'can_create_custom_scenarios' => $this->subscriptionService->hasFeatureAccess($userId, 'custom_scenarios'),
                'has_analytics_access' => $this->subscriptionService->hasFeatureAccess($userId, 'analytics'),
                'has_team_features' => $this->subscriptionService->hasFeatureAccess($userId, 'team_features'),
                'has_api_access' => $this->subscriptionService->hasFeatureAccess($userId, 'api_access')
            ];

            $this->sendResponse(['subscription' => $subscription]);
        } catch (Exception $e) {
            $this->logger->error("Failed to get subscription status", ['error' => $e->getMessage()]);
            $this->sendError('Failed to retrieve subscription status', 500);
        }
    }

    private function getUsage()
    {
        $userId = $this->getCurrentUserId();

        try {
            // This would typically come from the subscription service
            // For now, return mock usage data
            $usage = [
                'scenarios_used' => 5,
                'scenarios_limit' => 10,
                'roles_accessed' => 3,
                'roles_limit' => 3,
                'storage_used' => 0,
                'storage_limit' => 100, // MB
                'api_calls_used' => 0,
                'api_calls_limit' => 1000
            ];

            $this->sendResponse(['usage' => $usage]);
        } catch (Exception $e) {
            $this->sendError('Failed to retrieve usage data', 500);
        }
    }

    private function getPaymentHistory()
    {
        $userId = $this->getCurrentUserId();

        try {
            // This would typically query the payment_history table
            // For now, return empty array
            $this->sendResponse(['payments' => []]);
        } catch (Exception $e) {
            $this->sendError('Failed to retrieve payment history', 500);
        }
    }

    private function getStripeConfig()
    {
        try {
            $config = [
                'publishable_key' => $this->subscriptionService->getStripePublishableKey(),
                'plans' => $this->subscriptionService->getPlans()
            ];

            $this->sendResponse(['config' => $config]);
        } catch (Exception $e) {
            $this->sendError('Failed to retrieve Stripe configuration', 500);
        }
    }

    private function createSubscription($data)
    {
        $userId = $this->getCurrentUserId();
        $planId = $data['plan_id'] ?? null;
        $paymentMethodId = $data['payment_method_id'] ?? null;

        if (!$planId) {
            $this->sendError('Plan ID is required', 400);
            return;
        }

        try {
            $subscription = $this->subscriptionService->createSubscription($userId, $planId, $paymentMethodId);

            $this->logger->info("Subscription created for user {$userId}", ['plan' => $planId]);

            $this->sendResponse([
                'message' => 'Subscription created successfully',
                'subscription' => $subscription
            ]);
        } catch (Exception $e) {
            $this->logger->error("Failed to create subscription", ['error' => $e->getMessage()]);
            $this->sendError($e->getMessage(), 400);
        }
    }

    private function upgradeSubscription($data)
    {
        $userId = $this->getCurrentUserId();
        $newPlanId = $data['plan_id'] ?? null;
        $paymentMethodId = $data['payment_method_id'] ?? null;

        if (!$newPlanId) {
            $this->sendError('New plan ID is required', 400);
            return;
        }

        try {
            // Cancel current subscription if exists
            $currentSubscription = $this->subscriptionService->getUserSubscription($userId);
            if ($currentSubscription && $currentSubscription['status'] === 'active') {
                $this->subscriptionService->cancelSubscription($userId);
            }

            // Create new subscription
            $subscription = $this->subscriptionService->createSubscription($userId, $newPlanId, $paymentMethodId);

            $this->logger->info("Subscription upgraded for user {$userId}", ['new_plan' => $newPlanId]);

            $this->sendResponse([
                'message' => 'Subscription upgraded successfully',
                'subscription' => $subscription
            ]);
        } catch (Exception $e) {
            $this->logger->error("Failed to upgrade subscription", ['error' => $e->getMessage()]);
            $this->sendError($e->getMessage(), 400);
        }
    }

    private function cancelSubscription()
    {
        $userId = $this->getCurrentUserId();

        try {
            $this->subscriptionService->cancelSubscription($userId);

            $this->logger->info("Subscription canceled for user {$userId}");

            $this->sendResponse([
                'message' => 'Subscription canceled successfully'
            ]);
        } catch (Exception $e) {
            $this->logger->error("Failed to cancel subscription", ['error' => $e->getMessage()]);
            $this->sendError($e->getMessage(), 400);
        }
    }

    private function reactivateSubscription()
    {
        $userId = $this->getCurrentUserId();

        try {
            // This would typically reactivate a canceled subscription
            // For now, just return success
            $this->sendResponse([
                'message' => 'Subscription reactivation initiated'
            ]);
        } catch (Exception $e) {
            $this->sendError('Failed to reactivate subscription', 500);
        }
    }

    private function handleWebhook()
    {
        $payload = file_get_contents('php://input');
        $signature = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

        try {
            $this->subscriptionService->handleWebhook($payload, $signature);

            $this->logger->info("Webhook processed successfully");

            // Stripe expects a 200 response for successful webhook processing
            http_response_code(200);
            echo json_encode(['status' => 'success']);
        } catch (Exception $e) {
            $this->logger->error("Webhook processing failed", ['error' => $e->getMessage()]);
            http_response_code(400);
            echo json_encode(['error' => 'Webhook processing failed']);
        }
        exit;
    }

    private function formatPrice($priceInCents, $currency = 'usd')
    {
        $priceInDollars = $priceInCents / 100;

        if ($currency === 'usd') {
            return '$' . number_format($priceInDollars, 2);
        }

        return number_format($priceInDollars, 2) . ' ' . strtoupper($currency);
    }

    private function getCurrentUserId()
    {
        // Get user ID from session or JWT token
        // This is a simplified implementation
        return $_SESSION['user_id'] ?? 1; // Default to user ID 1 for demo
    }

    private function sendResponse($data, $statusCode = 200)
    {
        http_response_code($statusCode);
        echo json_encode($data);
        exit;
    }

    private function sendError($message, $statusCode = 400)
    {
        http_response_code($statusCode);
        echo json_encode(['error' => $message]);
        exit;
    }
}

// Handle the request
$api = new SubscriptionAPI();
$api->handleRequest();
?>
