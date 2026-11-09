<?php
/**
 * Login page (SRS-F01): bcrypt check, 5 failed attempts per username or IP lock sign-in for 15 minutes.
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth_functions.php';

if (!empty($_SESSION['user_id']) && !empty($_SESSION['role'])) {
    redirect(homePageFor((string)$_SESSION['role']));
}

$error = '';
$notice = (($_GET['error'] ?? '') === 'expired') ? t('session_expired') : '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = mb_substr(strtolower(trim((string)($_POST['username'] ?? ''))), 0, 50);
    $password = (string)($_POST['password'] ?? '');

    if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
        $error = t('csrf_invalid');
    } elseif ($username === '' || $password === '') {
        $error = t('login_required');
    } else {
        $pdo = db();
        $ip = clientIp();
        purgeOldLoginAttempts($pdo);

        if (isLoginRateLimited($pdo, $username, $ip)) {
            // Locked: do not even check the password and do not record this attempt.
            $error = t('login_locked');
        } else {
            $stmt = $pdo->prepare(
                'SELECT user_id, username, password_hash, name, role, status FROM users WHERE username = :u LIMIT 1'
            );
            $stmt->execute([':u' => $username]);
            $user = $stmt->fetch();

            // Always run password_verify so unknown usernames take the same time as real ones.
            $passwordOk = password_verify($password, $user ? $user['password_hash'] : LOGIN_DUMMY_HASH);

            if ($user && (int)$user['status'] === 1 && $passwordOk) {
                clearLoginAttempts($pdo, $username);
                session_regenerate_id(true);
                unset($_SESSION['csrf_token']);
                $_SESSION['user_id'] = (int)$user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['last_activity'] = time();
                redirect(homePageFor($user['role']));
            }

            // Wrong password, unknown user and inactive user all get the same message.
            recordFailedLogin($pdo, $username, $ip);
            $error = t('login_failed');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title><?= e(t('login_title')) ?> - Kedai Paradise</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 text-[#2C3E50] font-sans antialiased min-h-screen flex items-center justify-center p-4">
    <main class="w-full max-w-sm bg-white rounded-lg shadow-md border border-gray-200 p-6">
        <div class="text-center mb-6">
            <h1 class="text-2xl font-bold tracking-wider text-[#1B4D3E]">KEDAI PARADISE</h1>
            <p class="text-sm text-gray-600 mt-1"><?= e(t('login_subtitle')) ?></p>
        </div>

        <?php if ($notice !== ''): ?>
            <div class="mb-4 text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded p-3" role="status"><?= e($notice) ?></div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="mb-4 text-sm text-red-700 bg-red-50 border border-red-200 rounded p-3" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" action="login.php" class="space-y-4" autocomplete="on">
            <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
            <div>
                <label for="username" class="block text-sm font-medium mb-1"><?= e(t('login_username')) ?></label>
                <input type="text" id="username" name="username" value="<?= e($username) ?>" required maxlength="50"
                       autocomplete="username" autofocus placeholder="<?= e(t('login_username_placeholder')) ?>"
                       class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B4D3E]">
            </div>
            <div>
                <label for="password" class="block text-sm font-medium mb-1"><?= e(t('login_password')) ?></label>
                <input type="password" id="password" name="password" required
                       autocomplete="current-password" placeholder="<?= e(t('login_password_placeholder')) ?>"
                       class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B4D3E]">
            </div>
            <button type="submit"
                    class="w-full bg-[#1B4D3E] hover:bg-[#153d31] text-white font-semibold py-2.5 rounded transition">
                <?= e(t('login_button')) ?>
            </button>
        </form>
    </main>
</body>
</html>
