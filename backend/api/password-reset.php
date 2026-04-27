<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Auth.php';

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON data']);
        return;
    }

    switch ($method) {
        case 'POST':
            if (isset($_GET['action'])) {
                $action = $_GET['action'];
                switch ($action) {
                    case 'request':
                        requestPasswordReset($pdo, $data);
                        break;
                    case 'reset':
                        resetPassword($pdo, $data);
                        break;
                    default:
                        http_response_code(400);
                        echo json_encode(['error' => 'Invalid action']);
                        break;
                }
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Action parameter required']);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error', 'message' => $e->getMessage()]);
}

function requestPasswordReset($pdo, $data) {
    if (!isset($data['email']) || empty($data['email'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Email is required']);
        return;
    }

    $email = trim($data['email']);

    // Check if user exists
    $stmt = $pdo->prepare("SELECT id, username FROM users WHERE email = ? AND is_active = true");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // Don't reveal if email exists or not for security
        echo json_encode(['message' => 'If the email exists, a password reset link has been sent']);
        return;
    }

    // Generate reset token
    $resetToken = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

    // Store reset token (in a real application, you'd want a separate table for this)
    // For now, we'll store it in a simple way - in production, use a proper token table
    $tokenData = json_encode([
        'token' => $resetToken,
        'expires' => $expiresAt,
        'user_id' => $user['id']
    ]);

    // In a real application, you'd send an email here
    // For demo purposes, we'll just return the token
    echo json_encode([
        'message' => 'Password reset link has been sent to your email',
        'reset_token' => $resetToken, // Remove this in production
        'expires_at' => $expiresAt     // Remove this in production
    ]);
}

function resetPassword($pdo, $data) {
    $required = ['token', 'password', 'confirm_password'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Missing required field: $field"]);
            return;
        }
    }

    $token = $data['token'];
    $password = $data['password'];
    $confirmPassword = $data['confirm_password'];

    // Validate password strength
    if (strlen($password) < 8) {
        http_response_code(400);
        echo json_encode(['error' => 'Password must be at least 8 characters long']);
        return;
    }

    if ($password !== $confirmPassword) {
        http_response_code(400);
        echo json_encode(['error' => 'Passwords do not match']);
        return;
    }

    // In a real application, you'd validate the token from a database table
    // For demo purposes, we'll accept any token and update the current user's password
    $currentUser = Auth::getCurrentUser();
    if (!$currentUser) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        return;
    }

    // Hash new password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Update password
    $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$hashedPassword, $currentUser['id']]);

    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'User not found']);
        return;
    }

    echo json_encode(['message' => 'Password has been reset successfully']);
}

// Helper function to send password reset email (placeholder)
function sendPasswordResetEmail($email, $resetToken) {
    // In a real application, implement email sending here
    // Example using PHPMailer or similar library

    $resetLink = "https://yourapp.com/reset-password?token=" . $resetToken;
    $subject = "Password Reset Request";
    $message = "Click the following link to reset your password: " . $resetLink;
    $message .= "\n\nThis link will expire in 1 hour.";
    $message .= "\n\nIf you didn't request this, please ignore this email.";

    // Send email using your preferred method
    // mail($email, $subject, $message); // Basic PHP mail
    // or use a library like PHPMailer

    return true;
}
?>
