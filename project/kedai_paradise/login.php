<?php
/**
 * Login page (SRS-F01): bcrypt check, 5 failed attempts lock sign-in for 15 minutes,
 * plus automatic dual-database routing (Live vs Testing DB).
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
        // Route to testing DB if username is a test account, otherwise live DB
        $targetMode = isTestUsername($username) ? 'test' : 'live';
        setActiveDbMode($targetMode);
        $pdo = db($targetMode);

        $ip = clientIp();
        purgeOldLoginAttempts($pdo);

        if (isLoginRateLimited($pdo, $username, $ip)) {
            $error = t('login_locked');
        } else {
            $stmt = $pdo->prepare(
                'SELECT user_id, username, password_hash, name, role, status FROM users WHERE username = :u LIMIT 1'
            );
            $stmt->execute([':u' => $username]);
            $user = $stmt->fetch();

            $passwordOk = password_verify($password, $user ? $user['password_hash'] : LOGIN_DUMMY_HASH);

            if ($user && (int)$user['status'] === 1 && $passwordOk) {
                clearLoginAttempts($pdo, $username);
                session_regenerate_id(true);
                unset($_SESSION['csrf_token']);
                $_SESSION['user_id']       = (int)$user['user_id'];
                $_SESSION['username']      = $user['username'];
                $_SESSION['name']          = $user['name'];
                $_SESSION['role']          = $user['role'];
                $_SESSION['db_mode']       = $targetMode;
                $_SESSION['last_activity'] = time();
                redirect(homePageFor($user['role']));
            }

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
<body class="bg-[#F8F7F4] text-[#1E293B] font-sans antialiased min-h-screen flex items-center justify-center p-4">
    <main class="w-full max-w-md bg-white rounded-2xl shadow-lg border border-[#E6E4DD] p-7 sm:p-8">
        <div class="text-center mb-6">
            <div class="w-12 h-12 mx-auto mb-3 rounded-xl bg-[#1B4D3E] text-white flex items-center justify-center text-xl shadow-sm">
                &#127860;
            </div>
            <h1 class="text-2xl font-extrabold tracking-wide text-[#1E293B]">KEDAI PARADISE</h1>
            <p class="text-sm text-gray-500 mt-0.5">Sistem Kasir &amp; Rekap Keuangan</p>
        </div>

        <?php if ($notice !== ''): ?>
            <div class="mb-4 text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-xl p-3.5" role="status"><?= e($notice) ?></div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="mb-5 text-sm text-red-800 bg-red-50 border border-red-200 rounded-xl p-3.5 flex items-start gap-2.5" role="alert">
                <span class="text-red-600 font-bold">&#9888;</span>
                <span><?= e($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="post" action="login.php" class="space-y-4" autocomplete="on">
            <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
            <div>
                <label for="username" class="block text-xs font-bold uppercase tracking-wider text-gray-700 mb-1.5"><?= e(t('login_username')) ?></label>
                <div class="relative">
                    <input type="text" id="username" name="username" value="<?= e($username) ?>" required maxlength="50"
                           autocomplete="username" autofocus placeholder="Masukkan username"
                           class="w-full bg-[#F8F7F4] border border-gray-300 rounded-xl px-3.5 py-2.5 text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#1B4D3E]">
                </div>
            </div>
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label for="password" class="block text-xs font-bold uppercase tracking-wider text-gray-700"><?= e(t('login_password')) ?></label>
                    <span class="text-xs text-[#1B4D3E] font-medium">Hubungi Admin jika lupa</span>
                </div>
                <div class="relative">
                    <input type="password" id="password" name="password" required
                           autocomplete="current-password" placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;"
                           class="w-full bg-[#F8F7F4] border border-gray-300 rounded-xl px-3.5 py-2.5 pr-10 text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#1B4D3E]">
                    <button type="button" onclick="const p=document.getElementById('password');p.type=p.type==='password'?'text':'password';"
                            class="absolute right-3 top-2.5 text-gray-400 hover:text-gray-600 text-sm" title="Tampilkan Password">&#128065;</button>
                </div>
            </div>

            <button type="submit"
                    class="w-full bg-[#9E3B1B] hover:bg-[#822F14] text-white font-bold py-3 rounded-xl shadow-sm transition flex items-center justify-center gap-2 tracking-wide">
                <span>MASUK KE SISTEM</span>
                <span>&rarr;</span>
            </button>
        </form>

        <div class="mt-6 pt-4 border-t border-gray-100 text-center text-xs text-gray-400 flex items-center justify-center gap-1.5">
            <span>&#128274;</span>
            <span>Koneksi terenkripsi HTTPS (TLS 1.2+). Sesi otomatis ditutup setelah 30 menit.</span>
        </div>
    </main>
</body>
</html>