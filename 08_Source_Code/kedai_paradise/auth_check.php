<?php
/**
 * Authentication middleware. Include right after includes/bootstrap.php on every protected page.
 * - Returns HTTP 401 JSON for unauthenticated API calls (api_*.php), redirects browser pages to login.php.
 * - Re-checks the account on every request so deactivated users and role changes take effect immediately.
 * - requireRole() enforces BR-12 on the server (JSON 403 for APIs, styled 403 for pages).
 */

$isApiRequest = str_starts_with(basename($_SERVER['SCRIPT_NAME'] ?? ''), 'api_');

if (empty($_SESSION['user_id']) || empty($_SESSION['role'])) {
    if ($isApiRequest) {
        jsonError('Sesi tidak valid. Silakan masuk kembali.', 401);
    }
    redirect('login.php');
}

$authStmt = db()->prepare('SELECT username, name, role, status FROM users WHERE user_id = :id LIMIT 1');
$authStmt->execute([':id' => (int)$_SESSION['user_id']]);
$currentUser = $authStmt->fetch();

if (!$currentUser || (int)$currentUser['status'] !== 1) {
    $_SESSION = [];
    session_destroy();
    if ($isApiRequest) {
        jsonError('Sesi tidak valid atau akun telah dinonaktifkan.', 401);
    }
    redirect('login.php');
}

$_SESSION['username'] = $currentUser['username'];
$_SESSION['name'] = $currentUser['name'];
$_SESSION['role'] = $currentUser['role'];

/** Allows only the given roles; anyone else gets HTTP 403. */
function requireRole(array $allowedRoles): void
{
    $role = $_SESSION['role'] ?? '';
    if (!in_array($role, $allowedRoles, true)) {
        if (str_starts_with(basename($_SERVER['SCRIPT_NAME'] ?? ''), 'api_')) {
            jsonError(t('forbidden'), 403);
        }
        http_response_code(403);
        $pageTitle = t('forbidden');
        include __DIR__ . '/includes/layout_header.php';
        echo '<div class="bg-white p-6 rounded-lg shadow-sm border border-red-200 text-center">';
        echo '<h1 class="text-xl font-bold text-red-700 mb-2">403</h1>';
        echo '<p class="text-sm text-gray-700">' . e(t('forbidden')) . '</p>';
        echo '</div>';
        include __DIR__ . '/includes/layout_footer.php';
        exit;
    }
}
