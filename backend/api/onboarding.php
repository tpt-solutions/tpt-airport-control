<?php
/**
 * User Onboarding API
 *
 * Handles user onboarding progress, welcome tour, and training
 */

require_once __DIR__ . '/../src/Config.php';
require_once __DIR__ . '/../src/Logger.php';
require_once __DIR__ . '/../services/OnboardingService.php';
require_once __DIR__ . '/../src/CorsHandler.php';

header('Content-Type: application/json');

// Handle CORS preflight
CorsHandler::handlePreflight();
CorsHandler::applyHeaders();

class OnboardingAPI
{
    private $onboardingService;
    private $logger;

    public function __construct()
    {
        $this->logger = new Logger('onboarding_api');
        $this->onboardingService = new OnboardingService();
    }

    public function handleRequest()
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = $_SERVER['REQUEST_URI'];

        // Extract endpoint from path
        $endpoint = str_replace('/api/onboarding/', '', parse_url($path, PHP_URL_PATH));
        $endpoint = trim($endpoint, '/');

        switch ($method) {
            case 'GET':
                $this->handleGet($endpoint);
                break;
            case 'POST':
                $this->handlePost($endpoint);
                break;
            default:
                $this->sendError('Method not allowed', 405);
        }
    }

    private function handleGet($endpoint)
    {
        $userId = $this->getCurrentUserId();

        switch ($endpoint) {
            case 'progress':
                $this->getProgress($userId);
                break;
            case 'tips':
                $this->getTips($userId);
                break;
            default:
                $this->sendError('Endpoint not found', 404);
        }
    }

    private function handlePost($endpoint)
    {
        $userId = $this->getCurrentUserId();
        $data = json_decode(file_get_contents('php://input'), true);

        switch ($endpoint) {
            case 'complete-step':
                $this->completeStep($userId, $data);
                break;
            case 'initialize':
                $this->initializeOnboarding($userId);
                break;
            case 'send-welcome-email':
                $this->sendWelcomeEmail($userId);
                break;
            default:
                $this->sendError('Endpoint not found', 404);
        }
    }

    private function getProgress($userId)
    {
        try {
            $progress = $this->onboardingService->getUserOnboardingProgress($userId);
            $this->sendResponse(['progress' => $progress]);
        } catch (Exception $e) {
            $this->logger->error("Failed to get onboarding progress", ['error' => $e->getMessage()]);
            $this->sendError('Failed to retrieve onboarding progress', 500);
        }
    }

    private function getTips($userId)
    {
        try {
            $tips = $this->onboardingService->getOnboardingTips($userId);
            $this->sendResponse(['tips' => $tips]);
        } catch (Exception $e) {
            $this->sendError('Failed to retrieve onboarding tips', 500);
        }
    }

    private function completeStep($userId, $data)
    {
        $stepId = $data['step_id'] ?? null;

        if (!$stepId) {
            $this->sendError('Step ID is required', 400);
            return;
        }

        try {
            $progress = $this->onboardingService->completeOnboardingStep($userId, $stepId);
            
            $this->sendResponse([
                'message' => 'Step completed successfully',
                'progress' => $progress
            ]);
        } catch (Exception $e) {
            $this->logger->error("Failed to complete onboarding step", ['error' => $e->getMessage()]);
            $this->sendError($e->getMessage(), 400);
        }
    }

    private function initializeOnboarding($userId)
    {
        try {
            $progress = $this->onboardingService->initializeUserOnboarding($userId);
            
            $this->sendResponse([
                'message' => 'Onboarding initialized successfully',
                'progress' => $progress
            ]);
        } catch (Exception $e) {
            $this->logger->error("Failed to initialize onboarding", ['error' => $e->getMessage()]);
            $this->sendError($e->getMessage(), 400);
        }
    }

    private function sendWelcomeEmail($userId)
    {
        try {
            $this->onboardingService->sendWelcomeEmail($userId);
            
            $this->sendResponse([
                'message' => 'Welcome email sent successfully'
            ]);
        } catch (Exception $e) {
            $this->sendError('Failed to send welcome email', 500);
        }
    }

    private function getCurrentUserId()
    {
        // Get user ID from session or JWT token
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
$api = new OnboardingAPI();
$api->handleRequest();
?>