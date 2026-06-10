<?php
/**
 * Self-Service Account Management Portal API
 *
 * Customer account management, subscription, billing, and profile endpoints
 */

require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Validator.php';
require_once __DIR__ . '/../src/Logger.php';
require_once __DIR__ . '/../services/SubscriptionService.php';
require_once __DIR__ . '/../services/UsageMeteringService.php';
require_once __DIR__ . '/../services/OnboardingService.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Credentials: true');

$logger = Logger::getInstance();

// Authenticate user
$user = Auth::authenticateRequest();

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? 'profile';
    
    $subscriptionService = new SubscriptionService();
    $usageService = new UsageMeteringService();
    $onboardingService = new OnboardingService();
    
    switch ($action) {
        // Profile Management
        case 'profile':
            if ($method === 'GET') {
                $stmt = $pdo->prepare("
                    SELECT id, username, email, first_name, last_name, phone, 
                           created_at, onboarding_completed, last_login
                    FROM users WHERE id = ?
                ");
                $stmt->execute([$user['user_id']]);
                $profile = $stmt->fetch(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'status' => 'success',
                    'profile' => $profile
                ]);
            } elseif ($method === 'PUT') {
                $data = json_decode(file_get_contents('php://input'), true);
                
                $validator = Validator::getInstance();
                $rules = [
                    'first_name' => 'max_length:50|no_html',
                    'last_name' => 'max_length:50|no_html',
                    'phone' => 'phone_number|max_length:20'
                ];
                
                if (!$validator->validate($data, $rules)) {
                    http_response_code(400);
                    echo json_encode([
                        'status' => 'error',
                        'errors' => $validator->getErrors()
                    ]);
                    exit;
                }
                
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET first_name = ?, last_name = ?, phone = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $data['first_name'] ?? $user['first_name'],
                    $data['last_name'] ?? $user['last_name'],
                    $data['phone'] ?? $user['phone'],
                    $user['user_id']
                ]);
                
                $logger->info('User profile updated', ['user_id' => $user['user_id']]);
                
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Profile updated successfully'
                ]);
            }
            break;
            
        // Subscription Management
        case 'subscription':
            if ($method === 'GET') {
                $subscription = $subscriptionService->getUserSubscription($user['user_id']);
                $plans = $subscriptionService->getPlans();
                $usage = $usageService->getTenantQuotas($user['user_id']);
                
                echo json_encode([
                    'status' => 'success',
                    'subscription' => $subscription,
                    'plans' => $plans,
                    'usage' => $usage
                ]);
            }
            break;
            
        // Usage & Billing
        case 'usage':
            $period = $_GET['period'] ?? 'current_month';
            $usage = $usageService->getCurrentUsage($user['user_id'], $period);
            $quotas = $usageService->getTenantQuotas($user['user_id']);
            $history = [
                'scenarios_played' => $usageService->getUsageHistory($user['user_id'], 'scenarios_played', $period),
                'simulation_time' => $usageService->getUsageHistory($user['user_id'], 'simulation_time_seconds', $period)
            ];
            
            echo json_encode([
                'status' => 'success',
                'period' => $period,
                'usage' => $usage,
                'quotas' => $quotas,
                'history' => $history
            ]);
            break;
            
        // Onboarding Progress
        case 'onboarding':
            $progress = $onboardingService->getUserOnboardingProgress($user['user_id']);
            $tips = $onboardingService->getOnboardingTips($user['user_id']);
            
            echo json_encode([
                'status' => 'success',
                'progress' => $progress,
                'tips' => $tips
            ]);
            break;
            
        // Update onboarding step
        case 'complete_onboarding_step':
            if ($method === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                $stepId = $data['step_id'] ?? '';
                
                $progress = $onboardingService->completeOnboardingStep($user['user_id'], $stepId);
                
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Step completed',
                    'progress' => $progress
                ]);
            }
            break;
            
        // Change Password
        case 'change_password':
            if ($method === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                
                $validator = Validator::getInstance();
                $rules = [
                    'current_password' => 'required|min_length:8',
                    'new_password' => 'required|min_length:8|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/'
                ];
                
                if (!$validator->validate($data, $rules)) {
                    http_response_code(400);
                    echo json_encode([
                        'status' => 'error',
                        'errors' => $validator->getErrors()
                    ]);
                    exit;
                }
                
                // Verify current password
                $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
                $stmt->execute([$user['user_id']]);
                $hash = $stmt->fetchColumn();
                
                if (!password_verify($data['current_password'], $hash)) {
                    http_response_code(400);
                    echo json_encode([
                        'status' => 'error',
                        'message' => 'Current password is incorrect'
                    ]);
                    exit;
                }
                
                $newHash = password_hash($data['new_password'], PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$newHash, $user['user_id']]);
                
                $logger->info('User password changed', ['user_id' => $user['user_id']]);
                
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Password changed successfully'
                ]);
            }
            break;
            
        // Billing History
        case 'billing_history':
            $stmt = $pdo->prepare("
                SELECT id, amount, status, created_at, description
                FROM billing_transactions 
                WHERE user_id = ?
                ORDER BY created_at DESC
                LIMIT 50
            ");
            $stmt->execute([$user['user_id']]);
            $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'status' => 'success',
                'transactions' => $transactions
            ]);
            break;
            
        // API Keys Management
        case 'api_keys':
            if ($method === 'GET') {
                $stmt = $pdo->prepare("
                    SELECT id, name, key_prefix, created_at, last_used, expires_at
                    FROM api_keys 
                    WHERE user_id = ?
                    ORDER BY created_at DESC
                ");
                $stmt->execute([$user['user_id']]);
                $apiKeys = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'status' => 'success',
                    'api_keys' => $apiKeys
                ]);
            } elseif ($method === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                
                $keyName = $data['name'] ?? 'API Key';
                $apiKey = bin2hex(random_bytes(32));
                
                $stmt = $pdo->prepare("
                    INSERT INTO api_keys (user_id, name, api_key_hash, key_prefix, created_at)
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $user['user_id'],
                    $keyName,
                    password_hash($apiKey, PASSWORD_DEFAULT),
                    substr($apiKey, 0, 8)
                ]);
                
                $logger->info('API key created', ['user_id' => $user['user_id'], 'name' => $keyName]);
                
                echo json_encode([
                    'status' => 'success',
                    'message' => 'API key created',
                    'api_key' => $apiKey
                ]);
            } elseif ($method === 'DELETE') {
                $keyId = $_GET['id'] ?? 0;
                
                $stmt = $pdo->prepare("DELETE FROM api_keys WHERE id = ? AND user_id = ?");
                $stmt->execute([$keyId, $user['user_id']]);
                
                $logger->info('API key revoked', ['user_id' => $user['user_id'], 'key_id' => $keyId]);
                
                echo json_encode([
                    'status' => 'success',
                    'message' => 'API key revoked'
                ]);
            }
            break;
            
        default:
            http_response_code(404);
            echo json_encode([
                'status' => 'error',
                'message' => 'Action not found'
            ]);
    }
    
} catch (Exception $e) {
    $logger->error('Account API error', [
        'error' => 'An internal error occurred',
        'user_id' => $user['user_id']
    ]);
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Internal server error'
    ]);
}

?>