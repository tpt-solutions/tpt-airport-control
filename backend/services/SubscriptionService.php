<?php
/**
 * Subscription Service for Airport Operations Simulator
 *
 * Manages subscriptions, payments, and premium features
 */

require_once __DIR__ . '/../src/Config.php';
require_once __DIR__ . '/../src/Logger.php';

class SubscriptionService
{
    private $pdo;
    private $logger;
    private $stripeSecretKey;
    private $stripePublishableKey;

    // Subscription plans
    private $plans = [
        'free' => [
            'name' => 'Free',
            'price' => 0,
            'currency' => 'usd',
            'features' => [
                'basic_scenarios' => 10,
                'roles_access' => 3,
                'achievements' => true,
                'leaderboards' => true,
                'support' => 'community'
            ]
        ],
        'premium' => [
            'name' => 'Premium',
            'price' => 999, // $9.99
            'currency' => 'usd',
            'stripe_price_id' => 'price_premium_monthly',
            'features' => [
                'basic_scenarios' => -1, // unlimited
                'advanced_scenarios' => 25,
                'roles_access' => 10,
                'achievements' => true,
                'leaderboards' => true,
                'analytics' => true,
                'custom_scenarios' => false,
                'support' => 'email'
            ]
        ],
        'pro' => [
            'name' => 'Pro',
            'price' => 2999, // $29.99
            'currency' => 'usd',
            'stripe_price_id' => 'price_pro_monthly',
            'features' => [
                'basic_scenarios' => -1,
                'advanced_scenarios' => -1,
                'expert_scenarios' => 10,
                'roles_access' => 15,
                'achievements' => true,
                'leaderboards' => true,
                'analytics' => true,
                'custom_scenarios' => true,
                'scenario_editor' => true,
                'team_features' => false,
                'support' => 'priority'
            ]
        ],
        'institutional' => [
            'name' => 'Institutional',
            'price' => 7999, // $79.99
            'currency' => 'usd',
            'stripe_price_id' => 'price_institutional_monthly',
            'features' => [
                'basic_scenarios' => -1,
                'advanced_scenarios' => -1,
                'expert_scenarios' => -1,
                'roles_access' => 15,
                'achievements' => true,
                'leaderboards' => true,
                'analytics' => true,
                'custom_scenarios' => true,
                'scenario_editor' => true,
                'team_features' => true,
                'multi_user' => 50,
                'api_access' => true,
                'white_label' => false,
                'support' => 'dedicated'
            ]
        ]
    ];

    public function __construct()
    {
        $this->logger = new Logger('subscription_service');
        $this->connectDatabase();
        $this->initializeStripe();
    }

    private function connectDatabase()
    {
        try {
            $config = new Config();
            $dbConfig = $config->getDatabaseConfig();

            $this->pdo = new PDO(
                "pgsql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']}",
                $dbConfig['username'],
                $dbConfig['password']
            );
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (Exception $e) {
            $this->logger->error('Database connection failed', ['error' => $e->getMessage()]);
            throw new Exception('Database connection failed');
        }
    }

    private function initializeStripe()
    {
        // Load Stripe keys from environment
        $this->stripeSecretKey = getenv('STRIPE_SECRET_KEY') ?: 'sk_test_your_stripe_secret_key';
        $this->stripePublishableKey = getenv('STRIPE_PUBLISHABLE_KEY') ?: 'pk_test_your_stripe_publishable_key';

        // Include Stripe PHP SDK if available
        if (file_exists(__DIR__ . '/../vendor/stripe/stripe-php/init.php')) {
            require_once __DIR__ . '/../vendor/stripe/stripe-php/init.php';
            \Stripe\Stripe::setApiKey($this->stripeSecretKey);
        }
    }

    /**
     * Create a subscription for a user
     */
    public function createSubscription($userId, $planId, $paymentMethodId = null)
    {
        if (!isset($this->plans[$planId])) {
            throw new Exception("Invalid plan: {$planId}");
        }

        $plan = $this->plans[$planId];

        try {
            // Check if user already has an active subscription
            $existingSubscription = $this->getUserSubscription($userId);
            if ($existingSubscription && $existingSubscription['status'] === 'active') {
                throw new Exception('User already has an active subscription');
            }

            $subscriptionData = [
                'user_id' => $userId,
                'plan_id' => $planId,
                'status' => 'pending',
                'current_period_start' => date('Y-m-d H:i:s'),
                'current_period_end' => date('Y-m-d H:i:s', strtotime('+1 month')),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];

            // Handle free plan
            if ($plan['price'] === 0) {
                $subscriptionData['status'] = 'active';
                $subscriptionData['stripe_subscription_id'] = null;
            } else {
                // Create Stripe subscription if payment method provided
                if ($paymentMethodId) {
                    $stripeSubscription = $this->createStripeSubscription($userId, $plan, $paymentMethodId);
                    $subscriptionData['stripe_subscription_id'] = $stripeSubscription->id;
                    $subscriptionData['status'] = 'active';
                }
            }

            // Insert subscription record
            $stmt = $this->pdo->prepare("
                INSERT INTO subscriptions (
                    user_id, plan_id, status, stripe_subscription_id,
                    current_period_start, current_period_end, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $subscriptionData['user_id'],
                $subscriptionData['plan_id'],
                $subscriptionData['status'],
                $subscriptionData['stripe_subscription_id'] ?? null,
                $subscriptionData['current_period_start'],
                $subscriptionData['current_period_end'],
                $subscriptionData['created_at'],
                $subscriptionData['updated_at']
            ]);

            $this->logger->info("Subscription created for user {$userId}", ['plan' => $planId]);

            return $subscriptionData;
        } catch (Exception $e) {
            $this->logger->error("Failed to create subscription for user {$userId}", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Create Stripe subscription
     */
    private function createStripeSubscription($userId, $plan, $paymentMethodId)
    {
        if (!class_exists('\Stripe\Customer')) {
            throw new Exception('Stripe PHP SDK not available');
        }

        try {
            // Get or create Stripe customer
            $customer = $this->getOrCreateStripeCustomer($userId);

            // Attach payment method to customer
            $paymentMethod = \Stripe\PaymentMethod::retrieve($paymentMethodId);
            $paymentMethod->attach(['customer' => $customer->id]);

            // Set as default payment method
            \Stripe\Customer::update($customer->id, [
                'invoice_settings' => [
                    'default_payment_method' => $paymentMethodId
                ]
            ]);

            // Create subscription
            $subscription = \Stripe\Subscription::create([
                'customer' => $customer->id,
                'items' => [
                    [
                        'price' => $plan['stripe_price_id']
                    ]
                ],
                'default_payment_method' => $paymentMethodId,
                'expand' => ['latest_invoice.payment_intent']
            ]);

            return $subscription;
        } catch (Exception $e) {
            $this->logger->error("Stripe subscription creation failed", ['error' => $e->getMessage()]);
            throw new Exception('Payment processing failed: ' . $e->getMessage());
        }
    }

    /**
     * Get or create Stripe customer
     */
    private function getOrCreateStripeCustomer($userId)
    {
        try {
            // Check if customer already exists
            $stmt = $this->pdo->prepare("SELECT stripe_customer_id FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && $user['stripe_customer_id']) {
                return \Stripe\Customer::retrieve($user['stripe_customer_id']);
            }

            // Get user details
            $stmt = $this->pdo->prepare("SELECT first_name, last_name, email FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $userDetails = $stmt->fetch(PDO::FETCH_ASSOC);

            // Create new customer
            $customer = \Stripe\Customer::create([
                'email' => $userDetails['email'],
                'name' => $userDetails['first_name'] . ' ' . $userDetails['last_name'],
                'metadata' => [
                    'user_id' => $userId
                ]
            ]);

            // Update user with Stripe customer ID
            $stmt = $this->pdo->prepare("UPDATE users SET stripe_customer_id = ? WHERE id = ?");
            $stmt->execute([$customer->id, $userId]);

            return $customer;
        } catch (Exception $e) {
            throw new Exception('Failed to create customer: ' . $e->getMessage());
        }
    }

    /**
     * Get user's current subscription
     */
    public function getUserSubscription($userId)
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT s.*, p.name as plan_name, p.price, p.currency
                FROM subscriptions s
                JOIN subscription_plans p ON s.plan_id = p.id
                WHERE s.user_id = ?
                ORDER BY s.created_at DESC
                LIMIT 1
            ");
            $stmt->execute([$userId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Check if user has access to a feature
     */
    public function hasFeatureAccess($userId, $feature)
    {
        $subscription = $this->getUserSubscription($userId);

        if (!$subscription) {
            // Free plan features
            $freeFeatures = $this->plans['free']['features'];
            return isset($freeFeatures[$feature]) && $freeFeatures[$feature];
        }

        $plan = $this->plans[$subscription['plan_id']] ?? null;
        if (!$plan) {
            return false;
        }

        // Check if subscription is active
        if ($subscription['status'] !== 'active') {
            return false;
        }

        $features = $plan['features'];
        return isset($features[$feature]) && $features[$feature];
    }

    /**
     * Check if user can access a scenario
     */
    public function canAccessScenario($userId, $scenarioId)
    {
        $subscription = $this->getUserSubscription($userId);
        $planId = $subscription ? $subscription['plan_id'] : 'free';

        // Get scenario requirements
        $scenario = $this->getScenarioRequirements($scenarioId);
        if (!$scenario) {
            return true; // Scenario not found, allow access
        }

        $requiredPlan = $scenario['required_plan'] ?? 'free';

        // Plan hierarchy: free < premium < pro < institutional
        $planHierarchy = ['free' => 0, 'premium' => 1, 'pro' => 2, 'institutional' => 3];
        $userLevel = $planHierarchy[$planId] ?? 0;
        $requiredLevel = $planHierarchy[$requiredPlan] ?? 0;

        return $userLevel >= $requiredLevel;
    }

    /**
     * Get scenario requirements
     */
    private function getScenarioRequirements($scenarioId)
    {
        // This would typically come from a database or configuration
        $scenarioRequirements = [
            // Beginner scenarios - free
            'beginner_flight_processing' => ['required_plan' => 'free'],
            'gate_management_basics' => ['required_plan' => 'free'],
            'baggage_basics' => ['required_plan' => 'free'],

            // Intermediate scenarios - premium
            'peak_hour_challenge' => ['required_plan' => 'premium'],
            'weather_disruption' => ['required_plan' => 'premium'],
            'security_incident_response' => ['required_plan' => 'premium'],

            // Advanced scenarios - pro
            'hurricane_evacuation' => ['required_plan' => 'pro'],
            'cyber_security_breach' => ['required_plan' => 'pro'],
            'mass_casualty_incident' => ['required_plan' => 'pro'],

            // Expert scenarios - institutional
            'arctic_rescue_operation' => ['required_plan' => 'institutional'],
            'presidential_visit' => ['required_plan' => 'institutional'],
            'ultimate_crisis_management' => ['required_plan' => 'institutional']
        ];

        return $scenarioRequirements[$scenarioId] ?? ['required_plan' => 'free'];
    }

    /**
     * Cancel subscription
     */
    public function cancelSubscription($userId)
    {
        $subscription = $this->getUserSubscription($userId);

        if (!$subscription) {
            throw new Exception('No active subscription found');
        }

        try {
            // Cancel Stripe subscription if it exists
            if ($subscription['stripe_subscription_id']) {
                $stripeSubscription = \Stripe\Subscription::retrieve($subscription['stripe_subscription_id']);
                $stripeSubscription->cancel();
            }

            // Update local subscription
            $stmt = $this->pdo->prepare("
                UPDATE subscriptions
                SET status = 'canceled', updated_at = ?
                WHERE user_id = ? AND id = ?
            ");
            $stmt->execute([date('Y-m-d H:i:s'), $userId, $subscription['id']]);

            $this->logger->info("Subscription canceled for user {$userId}");

            return true;
        } catch (Exception $e) {
            $this->logger->error("Failed to cancel subscription for user {$userId}", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get subscription plans
     */
    public function getPlans()
    {
        return $this->plans;
    }

    /**
     * Get Stripe publishable key (safe to expose to frontend)
     */
    public function getStripePublishableKey()
    {
        return $this->stripePublishableKey;
    }

    /**
     * Handle Stripe webhook
     */
    public function handleWebhook($payload, $signature)
    {
        if (!class_exists('\Stripe\Webhook')) {
            throw new Exception('Stripe PHP SDK not available');
        }

        try {
            $endpointSecret = getenv('STRIPE_WEBHOOK_SECRET') ?: 'whsec_your_webhook_secret';
            $event = \Stripe\Webhook::constructEvent($payload, $signature, $endpointSecret);

            switch ($event->type) {
                case 'customer.subscription.updated':
                    $this->handleSubscriptionUpdate($event->data->object);
                    break;
                case 'customer.subscription.deleted':
                    $this->handleSubscriptionCancel($event->data->object);
                    break;
                case 'invoice.payment_succeeded':
                    $this->handlePaymentSuccess($event->data->object);
                    break;
                case 'invoice.payment_failed':
                    $this->handlePaymentFailure($event->data->object);
                    break;
            }

            return true;
        } catch (Exception $e) {
            $this->logger->error("Webhook processing failed", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Handle subscription update from Stripe
     */
    private function handleSubscriptionUpdate($subscription)
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE subscriptions
                SET status = ?, current_period_start = ?, current_period_end = ?, updated_at = ?
                WHERE stripe_subscription_id = ?
            ");

            $stmt->execute([
                $subscription->status,
                date('Y-m-d H:i:s', $subscription->current_period_start),
                date('Y-m-d H:i:s', $subscription->current_period_end),
                date('Y-m-d H:i:s'),
                $subscription->id
            ]);

            $this->logger->info("Subscription updated", ['stripe_id' => $subscription->id, 'status' => $subscription->status]);
        } catch (Exception $e) {
            $this->logger->error("Failed to update subscription", ['error' => $e->getMessage()]);
        }
    }

    /**
     * Handle subscription cancellation from Stripe
     */
    private function handleSubscriptionCancel($subscription)
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE subscriptions
                SET status = 'canceled', updated_at = ?
                WHERE stripe_subscription_id = ?
            ");

            $stmt->execute([date('Y-m-d H:i:s'), $subscription->id]);

            $this->logger->info("Subscription canceled", ['stripe_id' => $subscription->id]);
        } catch (Exception $e) {
            $this->logger->error("Failed to cancel subscription", ['error' => $e->getMessage()]);
        }
    }

    /**
     * Handle successful payment
     */
    private function handlePaymentSuccess($invoice)
    {
        $this->logger->info("Payment succeeded", ['invoice_id' => $invoice->id, 'amount' => $invoice->amount_paid]);
    }

    /**
     * Handle failed payment
     */
    private function handlePaymentFailure($invoice)
    {
        $this->logger->error("Payment failed", ['invoice_id' => $invoice->id, 'customer' => $invoice->customer]);
    }

    /**
     * Get subscription analytics
     */
    public function getSubscriptionAnalytics()
    {
        try {
            $stmt = $this->pdo->query("
                SELECT
                    plan_id,
                    COUNT(*) as subscriber_count,
                    AVG(DATEDIFF(CURRENT_DATE, DATE(created_at))) as avg_tenure_days
                FROM subscriptions
                WHERE status = 'active'
                GROUP BY plan_id
            ");

            $analytics = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $analytics[$row['plan_id']] = $row;
            }

            return $analytics;
        } catch (Exception $e) {
            return [];
        }
    }
}
?>
