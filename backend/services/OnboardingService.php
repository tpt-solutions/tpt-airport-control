<?php
/**
 * User Onboarding Service
 *
 * Manages automated account setup, training progress, and user onboarding workflow
 */

require_once __DIR__ . '/../src/Config.php';
require_once __DIR__ . '/../src/Logger.php';
require_once __DIR__ . '/SubscriptionService.php';

class OnboardingService
{
    private $pdo;
    private $logger;
    private $subscriptionService;

    // Onboarding steps definition
    private $onboardingSteps = [
        'account_created' => [
            'name' => 'Account Created',
            'description' => 'Your account has been created successfully',
            'completed' => true,
            'order' => 1
        ],
        'email_verified' => [
            'name' => 'Email Verified',
            'description' => 'Verify your email address',
            'completed' => false,
            'order' => 2
        ],
        'role_selected' => [
            'name' => 'Role Selected',
            'description' => 'Choose your starting role',
            'completed' => false,
            'order' => 3
        ],
        'tutorial_completed' => [
            'name' => 'Tutorial Completed',
            'description' => 'Complete the interactive tutorial',
            'completed' => false,
            'order' => 4
        ],
        'first_scenario' => [
            'name' => 'First Scenario',
            'description' => 'Complete your first airport scenario',
            'completed' => false,
            'order' => 5
        ],
        'profile_completed' => [
            'name' => 'Profile Setup',
            'description' => 'Complete your user profile',
            'completed' => false,
            'order' => 6
        ],
        'welcome_tour_completed' => [
            'name' => 'Welcome Tour',
            'description' => 'Complete the platform guided tour',
            'completed' => false,
            'order' => 7
        ]
    ];

    public function __construct()
    {
        $this->logger = new Logger('onboarding_service');
        $this->connectDatabase();
        $this->subscriptionService = new SubscriptionService();
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

    /**
     * Initialize onboarding for a new user
     */
    public function initializeUserOnboarding($userId)
    {
        try {
            // Initialize all steps for new user
            foreach ($this->onboardingSteps as $stepId => $step) {
                $stmt = $this->pdo->prepare("
                    INSERT INTO user_onboarding_progress 
                    (user_id, step_id, step_name, completed, completed_at, order_position)
                    VALUES (?, ?, ?, ?, ?, ?)
                    ON CONFLICT (user_id, step_id) DO NOTHING
                ");

                $stmt->execute([
                    $userId,
                    $stepId,
                    $step['name'],
                    $step['completed'],
                    $step['completed'] ? date('Y-m-d H:i:s') : null,
                    $step['order']
                ]);
            }

            // Create free subscription for new user
            $this->subscriptionService->createSubscription($userId, 'free');

            $this->logger->info("Onboarding initialized for user {$userId}");
            
            return $this->getUserOnboardingProgress($userId);
        } catch (Exception $e) {
            $this->logger->error("Failed to initialize onboarding for user {$userId}", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get user onboarding progress
     */
    public function getUserOnboardingProgress($userId)
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT step_id, step_name, completed, completed_at, order_position
                FROM user_onboarding_progress
                WHERE user_id = ?
                ORDER BY order_position ASC
            ");
            
            $stmt->execute([$userId]);
            $steps = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $completedCount = array_reduce($steps, function($count, $step) {
                return $count + ($step['completed'] ? 1 : 0);
            }, 0);

            return [
                'steps' => $steps,
                'progress_percent' => round(($completedCount / count($steps)) * 100),
                'total_steps' => count($steps),
                'completed_steps' => $completedCount,
                'current_step' => $this->getCurrentStep($steps)
            ];
        } catch (Exception $e) {
            $this->logger->error("Failed to get onboarding progress for user {$userId}", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Mark an onboarding step as completed
     */
    public function completeOnboardingStep($userId, $stepId)
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE user_onboarding_progress
                SET completed = true, completed_at = ?
                WHERE user_id = ? AND step_id = ?
            ");
            
            $stmt->execute([
                date('Y-m-d H:i:s'),
                $userId,
                $stepId
            ]);

            $this->logger->info("Onboarding step {$stepId} completed for user {$userId}");

            // Check if all steps completed
            $progress = $this->getUserOnboardingProgress($userId);
            
            if ($progress['progress_percent'] == 100) {
                $this->onOnboardingComplete($userId);
            }

            return $progress;
        } catch (Exception $e) {
            $this->logger->error("Failed to complete onboarding step {$stepId} for user {$userId}", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get current active step
     */
    private function getCurrentStep($steps)
    {
        foreach ($steps as $step) {
            if (!$step['completed']) {
                return $step;
            }
        }
        
        return null; // All steps completed
    }

    /**
     * Handle onboarding completion
     */
    private function onOnboardingComplete($userId)
    {
        $this->logger->info("User {$userId} completed onboarding");

        // Award onboarding achievement
        $achievementService = new AchievementService();
        $achievementService->awardAchievement($userId, 'onboarding_complete');

        // Update user status
        $stmt = $this->pdo->prepare("
            UPDATE users 
            SET onboarding_completed = true, onboarding_completed_at = ?
            WHERE id = ?
        ");
        
        $stmt->execute([
            date('Y-m-d H:i:s'),
            $userId
        ]);
    }

    /**
     * Send welcome email
     */
    public function sendWelcomeEmail($userId)
    {
        // Implementation would send actual email
        $this->logger->info("Welcome email sent to user {$userId}");
        return true;
    }

    /**
     * Get onboarding tips for user
     */
    public function getOnboardingTips($userId)
    {
        $progress = $this->getUserOnboardingProgress($userId);
        $currentStep = $progress['current_step'];

        $tips = [
            'account_created' => [
                'Next, verify your email address to unlock all features',
                'Check your inbox for the verification link'
            ],
            'email_verified' => [
                'Choose a role that matches your interests',
                'You can change roles at any time'
            ],
            'role_selected' => [
                'Complete the interactive tutorial to learn the basics',
                'The tutorial takes about 5 minutes'
            ],
            'default' => [
                'Explore the different airport operations modules',
                'Try completing your first scenario',
                'Join the community forums for tips and support'
            ]
        ];

        return $tips[$currentStep['step_id']] ?? $tips['default'];
    }
}
?>