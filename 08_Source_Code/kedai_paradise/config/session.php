<?php
/**
 * Secure Session Manager & CSRF Utilities
 */

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

$cookieParams = [
    'lifetime' => 1800, // 30 menit
    'path'     => '/',
    'domain'   => '',
    'secure'   => $isHttps,
    'httponly' => true,
    'samesite' => 'Strict',
];

session_set_cookie_params($cookieParams);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Timeout check (30 menit idle)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
    session_unset();
    session_destroy();

    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if (str_starts_with($script, 'api_')) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Sesi Anda telah berakhir. Silakan masuk kembali.']);
        exit;
    }

    header('Location: login.php?error=expired');
    exit;
}

$_SESSION['last_activity'] = time();

// Refresh session cookie expiry on every active request (true sliding 30-min idle window)
if (session_status() === PHP_SESSION_ACTIVE) {
    setcookie(session_name(), session_id(), [
        'expires'  => time() + 1800,
        'path'     => $cookieParams['path'],
        'domain'   => $cookieParams['domain'],
        'secure'   => $cookieParams['secure'],
        'httponly' => $cookieParams['httponly'],
        'samesite' => $cookieParams['samesite'],
    ]);
}

// CSRF Token Generation & Validation
function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCsrfToken(?string $token): bool {
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function requireCsrf(): void {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if ($token === '' && function_exists('getallheaders')) {
        $headers = array_change_key_case(getallheaders() ?: [], CASE_LOWER);
        $token = $headers['x-csrf-token'] ?? '';
    }
    if ($token === '') {
        $token = $_POST['csrf_token'] ?? '';
    }

    if (!validateCsrfToken($token)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Token CSRF tidak valid.']);
        exit;
    }
}
