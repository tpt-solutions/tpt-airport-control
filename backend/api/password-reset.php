<?php
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Logger.php';

use TPT\FlightControl\Logger;

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON data']);
        exit;
    }

    $action = $_GET['action'] ?? '';
    switch ($action) {
        case 'request':
            requestPasswordReset($pdo, $data);
            break;
        case 'reset':
            resetPassword($pdo, $data);
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Action parameter required (request or reset)']);
            break;
    }
} catch (Exception $e) {
    Logger::error('password-reset.php exception', ['message' => \$e->getMessage()]);
    http_response_code(500);
    echo json_encode(['error' => 'An internal error occurred. Please try again.']);
}

/**
 * Step 1 — generate a hashed reset token, store it in the DB, and "send" an email.
 * The raw token is NEVER returned in the response; it travels only via email.
 */
function requestPasswordReset($pdo, array $data): void {
    if (empty($data['email'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Email is required']);
        return;
    }

    $email = trim($data['email']);
    $ip    = $_SERVER['REMOTE_ADDR'] ?? null;

    // Rate limit: max 3 requests per email per 15 minutes
    if ($pdo) {
        try {
            $rateSql = "SELECT COUNT(*) FROM password_reset_tokens
                        WHERE user_id = (SELECT id FROM users WHERE email = ? LIMIT 1)
                          AND created_at > NOW() - INTERVAL '15 minutes'";
            $rateStmt = $pdo->prepare($rateSql);
            $rateStmt->execute([$email]);
            if ((int) $rateStmt->fetchColumn() >= 3) {
                http_response_code(429);
                echo json_encode(['error' => 'Too many reset requests. Please wait before trying again.']);
                return;
            }
        } catch (Exception $e) {
            // SQLite does not support interval syntax; skip rate check in demo mode
        }
    }

    // Look up the user
    $stmt = $pdo ? $pdo->prepare("SELECT id, username FROM users WHERE email = ? AND is_active = true") : null;
    if ($stmt) {
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $user = null;
    }

    // Always return the same message regardless of whether the email exists (prevents enumeration)
    echo json_encode(['message' => 'If that email is registered you will receive a reset link shortly.']);

    if (!$user) {
        return;
    }

    // Generate a cryptographically secure token; only the hash goes in the DB
    $rawToken  = bin2hex(random_bytes(32));          // 64 hex chars
    $tokenHash = hash('sha256', $rawToken);
    $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour

    if ($pdo) {
        try {
            // Delete any existing unused tokens for this user to avoid clutter
            $pdo->prepare("DELETE FROM password_reset_tokens WHERE user_id = ? AND used_at IS NULL")
                ->execute([$user['id']]);

            $pdo->prepare("
                INSERT INTO password_reset_tokens (user_id, token_hash, expires_at, ip_address)
                VALUES (?, ?, ?, ?)
            ")->execute([$user['id'], $tokenHash, $expiresAt, $ip]);
        } catch (Exception $e) {
            Logger::error('Failed to store password reset token', ['message' => \$e->getMessage()]);
            return;
        }
    }

    // Send the email (configure MAIL_* env vars for real delivery)
    sendPasswordResetEmail($email, $user['username'], $rawToken, $expiresAt);

    Logger::info('Password reset requested', ['user_id' => $user['id']]);
}

/**
 * Step 2 — validate the token from the email link, then update the password.
 * No authentication required — this is the unauthenticated reset flow.
 */
function resetPassword($pdo, array $data): void {
    $required = ['token', 'password', 'confirm_password'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Missing required field: {$field}"]);
            return;
        }
    }

    $rawToken       = $data['token'];
    $password       = $data['password'];
    $confirmPassword = $data['confirm_password'];

    if (strlen($password) < 10) {
        http_response_code(400);
        echo json_encode(['error' => 'Password must be at least 10 characters']);
        return;
    }

    if ($password !== $confirmPassword) {
        http_response_code(400);
        echo json_encode(['error' => 'Passwords do not match']);
        return;
    }

    $tokenHash = hash('sha256', $rawToken);

    if (!$pdo) {
        http_response_code(503);
        echo json_encode(['error' => 'Password reset requires a database connection']);
        return;
    }

    // Find a valid, unused, unexpired token
    $stmt = $pdo->prepare("
        SELECT id, user_id FROM password_reset_tokens
        WHERE token_hash = ?
          AND used_at IS NULL
          AND expires_at > CURRENT_TIMESTAMP
        LIMIT 1
    ");
    $stmt->execute([$tokenHash]);
    $tokenRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tokenRow) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid or expired reset token']);
        return;
    }

    $pdo->beginTransaction();
    try {
        // Mark the token as used
        $pdo->prepare("UPDATE password_reset_tokens SET used_at = CURRENT_TIMESTAMP WHERE id = ?")
            ->execute([$tokenRow['id']]);

        // Update the password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $updated = $pdo->prepare("UPDATE users SET password_hash = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $updated->execute([$hashedPassword, $tokenRow['user_id']]);

        if ($updated->rowCount() === 0) {
            $pdo->rollBack();
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
            return;
        }

        // Revoke all existing JWT refresh tokens so old sessions are invalidated
        $pdo->prepare("UPDATE refresh_tokens SET is_revoked = true, revoked_at = NOW() WHERE user_id = ? AND is_revoked = false")
            ->execute([$tokenRow['user_id']]);

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

    Logger::info('Password reset completed', ['user_id' => $tokenRow['user_id']]);
    echo json_encode(['message' => 'Password has been reset successfully. Please sign in.', 'success' => true]);
}

/**
 * Send the password reset email.
 * Configure MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD, MAIL_FROM_ADDRESS via env vars.
 * Falls back to PHP's built-in mail() if SMTP is not configured (useful for dev/demo).
 */
function sendPasswordResetEmail(string $to, string $username, string $rawToken, string $expiresAt): void {
    $appUrl   = rtrim(getenv('APP_URL') ?: 'http://localhost', '/');
    $appName  = getenv('APP_NAME') ?: 'Flight Control';
    $fromAddr = getenv('MAIL_FROM_ADDRESS') ?: 'noreply@flightcontrol.local';
    $fromName = getenv('MAIL_FROM_NAME') ?: $appName;
    $resetUrl = $appUrl . '/reset-password?token=' . urlencode($rawToken);

    $subject = "[{$appName}] Password Reset Request";
    $body    = "Hello {$username},\n\n"
             . "We received a request to reset your password.\n\n"
             . "Click the link below to set a new password (valid for 1 hour):\n"
             . $resetUrl . "\n\n"
             . "Expires at: {$expiresAt} UTC\n\n"
             . "If you did not request this, please ignore this email.\n\n"
             . "— {$appName} Team";

    $smtpHost = getenv('MAIL_HOST');
    if ($smtpHost && class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        // Full SMTP delivery via PHPMailer (composer require phpmailer/phpmailer)
        $mailer = new PHPMailer\PHPMailer\PHPMailer(true);
        $mailer->isSMTP();
        $mailer->Host       = $smtpHost;
        $mailer->Port       = (int)(getenv('MAIL_PORT') ?: 587);
        $mailer->SMTPAuth   = !empty(getenv('MAIL_USERNAME'));
        $mailer->Username   = getenv('MAIL_USERNAME') ?: '';
        $mailer->Password   = getenv('MAIL_PASSWORD') ?: '';
        $mailer->SMTPSecure = (getenv('MAIL_ENCRYPTION') === 'ssl') ? 'ssl' : 'tls';
        $mailer->setFrom($fromAddr, $fromName);
        $mailer->addAddress($to);
        $mailer->Subject = $subject;
        $mailer->Body    = $body;
        $mailer->send();
    } else {
        // Fallback: basic PHP mail() — suitable for dev/demo only
        $headers = "From: {$fromName} <{$fromAddr}>\r\nContent-Type: text/plain; charset=utf-8\r\n";
        mail($to, $subject, $body, $headers);
    }
}
?>
