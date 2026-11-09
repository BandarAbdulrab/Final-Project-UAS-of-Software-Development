<?php
/**
 * My Account (SRS-F01): every logged-in user can change their own password.
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

<div class="max-w-md mx-auto bg-white p-6 rounded-lg shadow-sm border border-gray-200">
    <h1 class="text-2xl font-bold text-[#1B4D3E]"><?= e(t('account_title')) ?></h1>
    <p class="text-sm text-gray-600 mt-1 mb-4"><?= e($_SESSION['name']) ?> (<?= e(t('role_' . $_SESSION['role'])) ?>)</p>

    <?php if ($success): ?>
        <div class="mb-4 text-sm text-green-800 bg-green-50 border border-green-200 rounded p-3" role="status"><?= e(t('password_changed')) ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="mb-4 text-sm text-red-700 bg-red-50 border border-red-200 rounded p-3" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="account.php" class="space-y-4" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
        <div>
            <label for="current_password" class="block text-sm font-medium mb-1"><?= e(t('account_current')) ?></label>
            <input type="password" id="current_password" name="current_password" required autocomplete="current-password"
                   class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B4D3E]">
        </div>
        <div>
            <label for="new_password" class="block text-sm font-medium mb-1"><?= e(t('account_new')) ?></label>
            <input type="password" id="new_password" name="new_password" required minlength="8" autocomplete="new-password"
                   class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B4D3E]">
            <p class="text-xs text-gray-500 mt-1"><?= e(t('users_password_hint')) ?></p>
        </div>
        <div>
            <label for="confirm_password" class="block text-sm font-medium mb-1"><?= e(t('account_confirm')) ?></label>
            <input type="password" id="confirm_password" name="confirm_password" required minlength="8" autocomplete="new-password"
                   class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B4D3E]">
        </div>
        <button type="submit" class="w-full bg-[#1B4D3E] hover:bg-[#153d31] text-white font-semibold py-2.5 rounded transition">
            <?= e(t('save')) ?>
        </button>
    </form>
</div>

<?php include __DIR__ . '/includes/layout_footer.php'; ?>
