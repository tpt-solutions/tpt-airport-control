<?php
/**
 * Shared API bootstrap — CORS + CSRF.
 *
 * Include this at the top of every API file instead of per-file CORS headers.
 *
 * CORS: Allowed origins come from the CORS_ALLOWED_ORIGINS env var (comma-separated).
 * When Nginx handles CORS these headers are redundant but harmless; they are required
 * when running with the PHP built-in server (dev/demo).
 *
 * CSRF: Pure JWT Bearer-token requests are not vulnerable to CSRF because browsers
 * cannot set an Authorization header on cross-origin requests without an explicit
 * CORS grant. CSRF validation is therefore only enforced when a PHP session is active
 * (cookie-based auth). Webhook endpoints must define CSRF_EXEMPT = true before
 * including this file to bypass validation.
 *
 * Example .env value:
 *   CORS_ALLOWED_ORIGINS=http://localhost:5173,https://flight-control.local
 */

(function (): void {
    // ── CORS ──────────────────────────────────────────────────────────────────
    $rawOrigins     = getenv('CORS_ALLOWED_ORIGINS') ?: 'http://localhost:5173,http://localhost:3000,http://127.0.0.1:5173';
    $allowedOrigins = array_filter(array_map('trim', explode(',', $rawOrigins)));
    $requestOrigin  = $_SERVER['HTTP_ORIGIN'] ?? '';

    header('Content-Type: application/json');
    header('Vary: Origin');

    if (in_array($requestOrigin, $allowedOrigins, true)) {
        header('Access-Control-Allow-Origin: ' . $requestOrigin);
    }
    // If origin is not in the allowlist we send no ACAO header — the browser will block.

    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token');
    header('Access-Control-Expose-Headers: X-RateLimit-Limit, X-RateLimit-Remaining, X-RateLimit-Reset, X-CSRF-Token');
    header('Access-Control-Max-Age: 86400');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }

    // ── CSRF ──────────────────────────────────────────────────────────────────
    $csrfExempt     = defined('CSRF_EXEMPT') && CSRF_EXEMPT;
    $hasBearerToken = !empty($_SERVER['HTTP_AUTHORIZATION'])
                   && stripos($_SERVER['HTTP_AUTHORIZATION'], 'Bearer ') === 0;
    $stateMutating  = in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['POST', 'PUT', 'DELETE', 'PATCH'], true);

    // Enforce only for cookie-based sessions without a Bearer token.
    if (!$csrfExempt && $stateMutating && !$hasBearerToken && session_status() !== PHP_SESSION_NONE) {
        $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? null;

        if (!$csrfToken) {
            http_response_code(403);
            echo json_encode(['error' => 'CSRF token required']);
            exit;
        }

        require_once __DIR__ . '/../src/CSRF.php';
        if (!CSRF::getInstance()->validateToken($csrfToken)) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid or expired CSRF token']);
            exit;
        }
    }
})();
