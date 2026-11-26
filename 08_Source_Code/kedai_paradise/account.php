<?php
/**
 * My Account (SRS-F02): every logged-in user can change their own password.
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/auth_check.php';

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = (string)($_POST['current_password'] ?? '');
    $new = (string)($_POST['new_password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
        $error = t('csrf_invalid');
    } else {
        $pdo = db();
        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE user_id = :id');
        $stmt->execute([':id' => (int)$_SESSION['user_id']]);
        $hash = (string)$stmt->fetchColumn();

        if (!password_verify($current, $hash)) {
            $error = t('password_wrong_current');
        } elseif (mb_strlen($new, 'UTF-8') < 8) {
            $error = t('password_too_short');
        } elseif (strlen($new) > 72) {
            $error = t('err_password_long');
        } elseif (!hash_equals($new, $confirm)) {
            $error = t('password_mismatch');
        } else {
            $update = $pdo->prepare('UPDATE users SET password_hash = :h WHERE user_id = :id');
            $update->execute([
                ':h'  => password_hash($new, PASSWORD_BCRYPT),
                ':id' => (int)$_SESSION['user_id'],
            ]);
            session_regenerate_id(true);
            unset($_SESSION['csrf_token']);
            $success = true;
        }
    }
}

$pageTitle = t('account_title') . ' - Kedai Paradise';
include __DIR__ . '/includes/layout_header.php';
?>

<div class="max-w-lg mx-auto bg-white rounded-2xl shadow-sm border border-[#E6E4DD] overflow-hidden">
    <div class="p-6 sm:p-7 space-y-5">
        <?php if ($success): ?>
            <div class="text-sm text-emerald-900 bg-emerald-50 border border-emerald-200 rounded-xl px-4 py-3 flex items-center justify-between" role="status">
                <span>&#10004; <?= e(t('password_changed')) ?></span>
            </div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="text-sm text-red-700 bg-red-50 border border-red-200 rounded-xl px-4 py-3" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-extrabold text-[#1E293B]">Akun Saya</h1>
                <p class="text-sm text-gray-500 mt-0.5">Perbarui kata sandi akun kasir Anda secara berkala</p>
            </div>
            <div class="w-11 h-11 rounded-full bg-[#F8F7F4] border border-gray-200 flex items-center justify-center text-[#1B4D3E] font-bold">
                &#128100;
            </div>
        </div>

        <form method="post" action="account.php" class="space-y-4" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
            <div>
                <div class="flex justify-between items-center mb-1">
                    <label for="current_password" class="block text-sm font-semibold text-gray-800"><?= e(t('account_current')) ?></label>
                    <span class="text-xs text-gray-400">Wajib diisi</span>
                </div>
                <input type="password" id="current_password" name="current_password" required autocomplete="current-password"
                       placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;"
                       class="w-full bg-[#F8F7F4] border border-gray-300 rounded-xl px-3.5 py-2.5 text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#1B4D3E]">
            </div>
            <div>
                <label for="new_password" class="block text-sm font-semibold text-gray-800 mb-1"><?= e(t('account_new')) ?></label>
                <input type="password" id="new_password" name="new_password" required minlength="8" autocomplete="new-password"
                       placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;"
                       class="w-full bg-[#F8F7F4] border border-gray-300 rounded-xl px-3.5 py-2.5 text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#1B4D3E]">
                <p class="text-xs text-gray-500 mt-1">&#9432; Minimal 8 karakter</p>
            </div>
            <div>
                <label for="confirm_password" class="block text-sm font-semibold text-gray-800 mb-1"><?= e(t('account_confirm')) ?></label>
                <input type="password" id="confirm_password" name="confirm_password" required minlength="8" autocomplete="new-password"
                       placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;"
                       class="w-full bg-[#F8F7F4] border border-gray-300 rounded-xl px-3.5 py-2.5 text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#1B4D3E]">
            </div>
            <button type="submit" class="w-full bg-[#9E3B1B] hover:bg-[#822F14] text-white font-bold py-3 rounded-xl shadow-sm transition">
                Simpan Password
            </button>
        </form>
    </div>
    <div class="bg-[#F8F7F4] px-6 py-3.5 border-t border-[#E6E4DD] text-xs text-gray-600 flex items-center justify-between">
        <span>&#10004; Sesi Terautentikasi: <strong><?= e($_SESSION['name']) ?></strong> (<?= e(t('role_' . $_SESSION['role'])) ?>)</span>
        <span class="font-mono text-gray-400">@<?= e($_SESSION['username'] ?? '') ?></span>
    </div>
</div>

<?php include __DIR__ . '/includes/layout_footer.php'; ?>