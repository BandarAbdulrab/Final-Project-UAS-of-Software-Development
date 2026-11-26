<?php
/**
 * User management (admin only) - SRS-F02, BR-12, BR-13 (Figma Slides 15 & 16 Match)
 * Users are never deleted, only activated or deactivated.
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/auth_check.php';
requireRole(['admin']);

$pdo = db();
$selfId = (int)$_SESSION['user_id'];

function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function checkPasswordRules(string $password): string
{
    if (mb_strlen($password, 'UTF-8') < 8) {
        return t('err_password_short');
    }
    if (strlen($password) > 72) {
        return t('err_password_long');
    }
    return '';
}

function countActiveAdmins(PDO $pdo): int
{
    return (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 1 FOR UPDATE")->fetchColumn();
}

function handleAddUser(PDO $pdo): array
{
    $name = trim((string)($_POST['name'] ?? ''));
    $username = strtolower(trim((string)($_POST['username'] ?? '')));
    $password = (string)($_POST['password'] ?? '');
    $role = (string)($_POST['role'] ?? '');

    if ($name === '' || mb_strlen($name, 'UTF-8') > 100) {
        return [t('err_name_required'), ''];
    }
    if (!preg_match('/^[a-z0-9_]{3,30}$/', $username)) {
        return [t('err_username_invalid'), ''];
    }
    if (!in_array($role, ['admin', 'cashier'], true)) {
        return [t('err_role_invalid'), ''];
    }
    $passwordError = checkPasswordRules($password);
    if ($passwordError !== '') {
        return [$passwordError, ''];
    }

    $exists = $pdo->prepare('SELECT 1 FROM users WHERE username = :u');
    $exists->execute([':u' => $username]);
    if ($exists->fetchColumn()) {
        return [t('err_username_taken'), ''];
    }

    try {
        $insert = $pdo->prepare(
            'INSERT INTO users (username, password_hash, name, role, status) VALUES (:u, :h, :n, :r, 1)'
        );
        $insert->execute([
            ':u' => $username,
            ':h' => password_hash($password, PASSWORD_BCRYPT),
            ':n' => $name,
            ':r' => $role,
        ]);
    } catch (PDOException $ex) {
        if ((string)$ex->getCode() === '23000') {
            return [t('err_username_taken'), ''];
        }
        throw $ex;
    }
    return ['', 'users_created'];
}

function handleEditUser(PDO $pdo): array
{
    $id = (int)($_POST['user_id'] ?? 0);
    $name = trim((string)($_POST['name'] ?? ''));
    $role = (string)($_POST['role'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if ($name === '' || mb_strlen($name, 'UTF-8') > 100) {
        return [t('err_name_required'), ''];
    }
    if (!in_array($role, ['admin', 'cashier'], true)) {
        return [t('err_role_invalid'), ''];
    }
    if ($password !== '') {
        $passwordError = checkPasswordRules($password);
        if ($passwordError !== '') {
            return [$passwordError, ''];
        }
    }

    $pdo->beginTransaction();
    try {
        $select = $pdo->prepare('SELECT user_id, role, status FROM users WHERE user_id = :id FOR UPDATE');
        $select->execute([':id' => $id]);
        $user = $select->fetch();
        if (!$user) {
            $pdo->rollBack();
            return [t('err_user_not_found'), ''];
        }

        if ($user['role'] === 'admin' && $role === 'cashier' && (int)$user['status'] === 1 && countActiveAdmins($pdo) <= 1) {
            $pdo->rollBack();
            return [t('err_cannot_demote_last_admin'), ''];
        }

        if ($password !== '') {
            $update = $pdo->prepare('UPDATE users SET name = :n, role = :r, password_hash = :h WHERE user_id = :id');
            $update->execute([
                ':n'  => $name,
                ':r'  => $role,
                ':h'  => password_hash($password, PASSWORD_BCRYPT),
                ':id' => $id,
            ]);
        } else {
            $update = $pdo->prepare('UPDATE users SET name = :n, role = :r WHERE user_id = :id');
            $update->execute([':n' => $name, ':r' => $role, ':id' => $id]);
        }
        $pdo->commit();
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
    return ['', 'users_updated'];
}

function handleToggleUser(PDO $pdo, int $selfId): array
{
    $id = (int)($_POST['user_id'] ?? 0);
    $target = (string)($_POST['target'] ?? '');
    if (!in_array($target, ['activate', 'deactivate'], true)) {
        return [t('err_generic'), ''];
    }

    $pdo->beginTransaction();
    try {
        $select = $pdo->prepare('SELECT user_id, role, status FROM users WHERE user_id = :id FOR UPDATE');
        $select->execute([':id' => $id]);
        $user = $select->fetch();
        if (!$user) {
            $pdo->rollBack();
            return [t('err_user_not_found'), ''];
        }

        if ($target === 'deactivate') {
            if ($id === $selfId) {
                $pdo->rollBack();
                return [t('err_cannot_deactivate_self'), ''];
            }
            if ($user['role'] === 'admin' && (int)$user['status'] === 1 && countActiveAdmins($pdo) <= 1) {
                $pdo->rollBack();
                return [t('err_cannot_deactivate_last_admin'), ''];
            }
            $newStatus = 0;
            $successKey = 'users_deactivated';
        } else {
            $newStatus = 1;
            $successKey = 'users_activated';
        }

        $update = $pdo->prepare('UPDATE users SET status = :s WHERE user_id = :id');
        $update->execute([':s' => $newStatus, ':id' => $id]);
        $pdo->commit();
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
    return ['', $successKey];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
        setFlash('error', t('csrf_invalid'));
        redirect('users.php');
    }

    try {
        switch ((string)($_POST['action'] ?? '')) {
            case 'add':
                [$error, $successKey] = handleAddUser($pdo);
                break;
            case 'edit':
                [$error, $successKey] = handleEditUser($pdo);
                break;
            case 'toggle':
                [$error, $successKey] = handleToggleUser($pdo, $selfId);
                break;
            default:
                [$error, $successKey] = [t('err_generic'), ''];
        }
    } catch (Throwable $ex) {
        error_log('users.php: ' . $ex->getMessage());
        [$error, $successKey] = [t('err_generic'), ''];
    }

    if ($error !== '') {
        setFlash('error', $error);
    } else {
        setFlash('success', t($successKey));
    }
    redirect('users.php');
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$users = $pdo->query('SELECT user_id, username, name, role, status FROM users ORDER BY user_id')->fetchAll();
$csrf = generateCsrfToken();

$totalUsersCount = count($users);
$adminCount = 0;
$cashierCount = 0;
$activeCount = 0;
$inactiveCount = 0;
$activeCashierName = '-';

foreach ($users as $u) {
    if ($u['role'] === 'admin') {
        $adminCount++;
    } else {
        $cashierCount++;
        if ((int)$u['status'] === 1 && $activeCashierName === '-') {
            $activeCashierName = $u['name'];
        }
    }
    if ((int)$u['status'] === 1) {
        $activeCount++;
    } else {
        $inactiveCount++;
    }
}

$pageTitle = 'Manajemen Pengguna Sistem - Kedai Paradise';
include __DIR__ . '/includes/layout_header.php';
?>

<div class="space-y-6">
    <!-- Top Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-[#1E293B] uppercase">MANAJEMEN PENGGUNA SISTEM (KASIR &amp; ADMIN)</h1>
            <p class="text-xs text-gray-500">Pengaturan akun staf, kredensial kasir, dan hak akses operasional Kedai Paradise.</p>
        </div>
        <button type="button" id="btnAddUser" class="bg-[#9E3B1B] hover:bg-[#822F14] text-white text-xs font-extrabold uppercase tracking-wider px-5 py-3 rounded-xl shadow-sm transition self-start sm:self-auto">
            + <?= e(t('users_add')) ?>
        </button>
    </div>

    <?php if ($flash): ?>
        <div class="text-sm rounded-xl p-4 border font-medium <?= $flash['type'] === 'success' ? 'text-emerald-900 bg-emerald-50 border-emerald-200' : 'text-red-700 bg-red-50 border-red-200' ?>" role="alert">
            <?= e($flash['message']) ?>
        </div>
    <?php endif; ?>

    <!-- 3 Summary KPI Cards (Figma Slide 15) -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-[#E6E4DD] flex items-center gap-4">
            <span class="w-12 h-12 rounded-2xl bg-emerald-100 text-[#1B4D3E] flex items-center justify-center font-bold text-xl">&#128101;</span>
            <div>
                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Total Pengguna</span>
                <div class="text-2xl font-extrabold text-[#1E293B]"><?= $totalUsersCount ?> Pengguna</div>
                <span class="text-xs text-gray-500"><?= $adminCount ?> Admin &bull; <?= $cashierCount ?> Kasir</span>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl shadow-sm border border-[#E6E4DD] flex items-center gap-4">
            <span class="w-12 h-12 rounded-2xl bg-gray-100 text-gray-700 flex items-center justify-center font-bold text-xl">&#128737;</span>
            <div>
                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Status Akun</span>
                <div class="text-2xl font-extrabold text-[#1E293B]"><?= $activeCount ?> Aktif</div>
                <span class="text-xs text-gray-500"><?= $inactiveCount ?> Terdaftar Nonaktif</span>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl shadow-sm border border-[#E6E4DD] flex items-center gap-4">
            <span class="w-12 h-12 rounded-2xl bg-orange-50 text-[#9E3B1B] flex items-center justify-center font-bold text-xl">&#128188;</span>
            <div>
                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Peran Aktif Kasir</span>
                <div class="text-xl font-extrabold text-[#9E3B1B] truncate max-w-[200px]"><?= e($activeCashierName) ?></div>
                <span class="text-xs text-gray-500">Operator POS Aktif</span>
            </div>
        </div>
    </div>

    <!-- Search & Filter Bar -->
    <div class="bg-white rounded-2xl shadow-sm border border-[#E6E4DD] p-4 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
        <input type="text" id="userSearchInput" placeholder="Cari nama atau username pengguna..."
               class="flex-1 bg-[#F8F7F4] border border-gray-300 rounded-xl px-3.5 py-2 text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#1B4D3E]">
        <div class="flex items-center gap-2">
            <select id="userRoleFilter" class="bg-[#F8F7F4] border border-gray-300 rounded-xl px-3 py-2 text-xs font-bold text-gray-700">
                <option value="">Semua Peran</option>
                <option value="admin">Admin</option>
                <option value="cashier">Kasir</option>
            </select>
            <select id="userStatusFilter" class="bg-[#F8F7F4] border border-gray-300 rounded-xl px-3 py-2 text-xs font-bold text-gray-700">
                <option value="">Semua Status</option>
                <option value="1">Aktif</option>
                <option value="0">Nonaktif</option>
            </select>
        </div>
    </div>

    <!-- Users Table -->
    <div class="bg-white rounded-2xl shadow-sm border border-[#E6E4DD] overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-[#F8F7F4] border-b border-[#E6E4DD] text-left text-[11px] font-bold uppercase tracking-wider text-gray-500">
                    <tr>
                        <th class="px-5 py-3.5">ID</th>
                        <th class="px-5 py-3.5"><?= e(t('users_name')) ?></th>
                        <th class="px-5 py-3.5"><?= e(t('users_username')) ?></th>
                        <th class="px-5 py-3.5"><?= e(t('users_role')) ?></th>
                        <th class="px-5 py-3.5"><?= e(t('users_status')) ?></th>
                        <th class="px-5 py-3.5 text-right"><?= e(t('action')) ?></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100" id="usersTableBody">
                    <?php foreach ($users as $u):
                        $isSelf = (int)$u['user_id'] === $selfId;
                        $isActive = (int)$u['status'] === 1;
                        $words = preg_split('/\s+/', trim($u['name'])) ?: ['U'];
                        $initials = strtoupper(substr($words[0], 0, 1) . (isset($words[1]) ? substr($words[1], 0, 1) : ''));
                    ?>
                        <tr class="user-row hover:bg-[#F8F7F4]/60 <?= $isActive ? '' : 'bg-gray-50/70 text-gray-400' ?>"
                            data-search="<?= e(strtolower($u['name'] . ' ' . $u['username'])) ?>"
                            data-role="<?= e($u['role']) ?>"
                            data-status="<?= (int)$u['status'] ?>">
                            <td class="px-5 py-4 font-bold text-gray-500"><?= (int)$u['user_id'] ?></td>
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-3">
                                    <span class="w-9 h-9 rounded-full font-extrabold text-xs flex items-center justify-center <?= $isSelf ? 'bg-[#9E3B1B] text-white' : ($u['role'] === 'admin' ? 'bg-emerald-100 text-[#1B4D3E]' : 'bg-slate-800 text-white') ?>">
                                        <?= e($initials) ?>
                                    </span>
                                    <div>
                                        <div class="font-extrabold text-[#1E293B]">
                                            <?= e($u['name']) ?>
                                            <?php if ($isSelf): ?>
                                                <span class="ml-1 text-[10px] bg-orange-100 text-[#9E3B1B] px-2 py-0.5 rounded-full font-bold"><?= e(t('users_you')) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-[11px] text-gray-400"><?= e($u['username']) ?>.<?= e($u['role']) ?>@paradise.id</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                <span class="font-mono text-xs bg-[#F8F7F4] border border-gray-200 px-2.5 py-1 rounded-lg text-gray-700">@<?= e($u['username']) ?></span>
                            </td>
                            <td class="px-5 py-4">
                                <span class="text-xs font-extrabold uppercase px-3 py-1 rounded-full <?= $u['role'] === 'admin' ? 'bg-[#1B4D3E] text-white' : 'bg-slate-700 text-white' ?>">
                                    <?= e(t('role_' . $u['role'])) ?>
                                </span>
                            </td>
                            <td class="px-5 py-4">
                                <span class="text-xs font-bold px-3 py-1 rounded-full <?= $isActive ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-gray-200 text-gray-600' ?>">
                                    &bull; <?= e(t($isActive ? 'status_active' : 'status_inactive')) ?>
                                </span>
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex justify-end items-center gap-2">
                                    <button type="button"
                                            class="btn-edit-user text-xs font-bold bg-[#F8F7F4] hover:bg-gray-200 text-[#1E293B] border border-gray-200 px-3 py-1.5 rounded-lg transition"
                                            data-id="<?= (int)$u['user_id'] ?>"
                                            data-name="<?= e($u['name']) ?>"
                                            data-username="<?= e($u['username']) ?>"
                                            data-role="<?= e($u['role']) ?>">
                                        &#9998; <?= e(t('edit')) ?> / Reset
                                    </button>
                                    <form method="post" action="users.php" class="inline"
                                          <?php if ($isActive): ?>onsubmit="return confirm('<?= e(t('users_confirm_deactivate')) ?>');"<?php endif; ?>>
                                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="user_id" value="<?= (int)$u['user_id'] ?>">
                                        <input type="hidden" name="target" value="<?= $isActive ? 'deactivate' : 'activate' ?>">
                                        <?php if ($isActive): ?>
                                            <button type="submit" <?= $isSelf ? 'disabled title="' . e(t('err_cannot_deactivate_self')) . '"' : '' ?>
                                                    class="text-xs font-bold px-3 py-1.5 rounded-lg border transition <?= $isSelf ? 'bg-gray-100 text-gray-400 border-gray-200 cursor-not-allowed' : 'bg-red-50 hover:bg-red-100 text-red-700 border-red-200' ?>">
                                                <?= e(t('users_deactivate')) ?>
                                            </button>
                                        <?php else: ?>
                                            <button type="submit" class="text-xs font-bold bg-emerald-50 hover:bg-emerald-100 text-[#1B4D3E] border border-emerald-200 px-3 py-1.5 rounded-lg transition">
                                                &#10004; <?= e(t('users_activate')) ?>
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add user modal (Figma Slide 16) -->
<div id="modalAdd" class="hidden fixed inset-0 z-40 bg-black/55 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 border border-[#E6E4DD]">
        <div class="flex justify-between items-start mb-4">
            <div>
                <h2 class="text-lg font-extrabold text-[#1E293B]"><?= e(t('users_add_title')) ?></h2>
                <p class="text-xs text-gray-500">Buat akun staf baru untuk operasional kasir atau admin toko.</p>
            </div>
            <button type="button" class="btn-close-modal text-gray-400 hover:text-gray-600 text-xl font-bold">&times;</button>
        </div>
        <form method="post" action="users.php" class="space-y-4" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="action" value="add">
            <div>
                <label for="add_name" class="block text-xs font-bold text-gray-700 mb-1"><?= e(t('users_name')) ?> *</label>
                <input type="text" id="add_name" name="name" required maxlength="100" placeholder="Contoh: Siti Rahmawati"
                       class="w-full bg-[#F8F7F4] border border-gray-300 rounded-xl px-3.5 py-2.5 text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#1B4D3E]">
            </div>
            <div>
                <label for="add_username" class="block text-xs font-bold text-gray-700 mb-1"><?= e(t('users_username')) ?> *</label>
                <input type="text" id="add_username" name="username" required minlength="3" maxlength="30" pattern="[a-z0-9_]{3,30}" placeholder="siti_kasir"
                       class="w-full bg-[#F8F7F4] border border-gray-300 rounded-xl px-3.5 py-2.5 text-sm font-mono focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#1B4D3E]">
                <p class="text-[11px] text-gray-400 mt-1"><?= e(t('err_username_invalid')) ?></p>
            </div>
            <div>
                <label for="add_role" class="block text-xs font-bold text-gray-700 mb-1">Peran Akses *</label>
                <select id="add_role" name="role" class="w-full bg-[#F8F7F4] border border-gray-300 rounded-xl px-3.5 py-2.5 text-sm font-bold">
                    <option value="cashier"><?= e(t('role_cashier')) ?> (POS Kasir &amp; Antrian)</option>
                    <option value="admin"><?= e(t('role_admin')) ?> (Akses Penuh)</option>
                </select>
            </div>
            <div>
                <label for="add_password" class="block text-xs font-bold text-gray-700 mb-1"><?= e(t('users_password')) ?> *</label>
                <input type="password" id="add_password" name="password" required minlength="8" maxlength="72" autocomplete="new-password"
                       class="w-full bg-[#F8F7F4] border border-gray-300 rounded-xl px-3.5 py-2.5 text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#1B4D3E]">
                <p class="text-[11px] text-gray-400 mt-1"><?= e(t('users_password_hint')) ?></p>
            </div>
            <div class="flex justify-end gap-2 pt-3 border-t border-gray-100">
                <button type="button" class="btn-close-modal bg-gray-100 hover:bg-gray-200 text-xs font-bold px-4 py-2.5 rounded-xl transition"><?= e(t('cancel')) ?></button>
                <button type="submit" class="bg-[#9E3B1B] hover:bg-[#822F14] text-white text-xs font-extrabold uppercase tracking-wider px-5 py-2.5 rounded-xl transition"><?= e(t('save')) ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Edit / reset user modal -->
<div id="modalEdit" class="hidden fixed inset-0 z-40 bg-black/55 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 border border-[#E6E4DD]">
        <div class="flex justify-between items-start mb-3">
            <div>
                <h2 class="text-lg font-extrabold text-[#1E293B]"><?= e(t('users_edit_title')) ?></h2>
                <p id="editUsernameLabel" class="text-xs font-mono text-gray-500"></p>
            </div>
            <button type="button" class="btn-close-modal text-gray-400 hover:text-gray-600 text-xl font-bold">&times;</button>
        </div>
        <form method="post" action="users.php" class="space-y-4" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="user_id" id="edit_user_id" value="">
            <div>
                <label for="edit_name" class="block text-xs font-bold text-gray-700 mb-1"><?= e(t('users_name')) ?></label>
                <input type="text" id="edit_name" name="name" required maxlength="100"
                       class="w-full bg-[#F8F7F4] border border-gray-300 rounded-xl px-3.5 py-2.5 text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#1B4D3E]">
            </div>
            <div>
                <label for="edit_role" class="block text-xs font-bold text-gray-700 mb-1"><?= e(t('users_role')) ?></label>
                <select id="edit_role" name="role" class="w-full bg-[#F8F7F4] border border-gray-300 rounded-xl px-3.5 py-2.5 text-sm font-bold">
                    <option value="cashier"><?= e(t('role_cashier')) ?></option>
                    <option value="admin"><?= e(t('role_admin')) ?></option>
                </select>
            </div>
            <div>
                <label for="edit_password" class="block text-xs font-bold text-gray-700 mb-1"><?= e(t('users_password_optional')) ?></label>
                <input type="password" id="edit_password" name="password" minlength="8" maxlength="72" autocomplete="new-password"
                       class="w-full bg-[#F8F7F4] border border-gray-300 rounded-xl px-3.5 py-2.5 text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#1B4D3E]">
                <p class="text-[11px] text-gray-400 mt-1"><?= e(t('users_password_hint')) ?></p>
            </div>
            <div class="flex justify-end gap-2 pt-3 border-t border-gray-100">
                <button type="button" class="btn-close-modal bg-gray-100 hover:bg-gray-200 text-xs font-bold px-4 py-2.5 rounded-xl transition"><?= e(t('cancel')) ?></button>
                <button type="submit" class="bg-[#9E3B1B] hover:bg-[#822F14] text-white text-xs font-extrabold uppercase tracking-wider px-5 py-2.5 rounded-xl transition"><?= e(t('save')) ?></button>
            </div>
        </form>
    </div>
</div>

<script>
    (function () {
        var modalAdd = document.getElementById('modalAdd');
        var modalEdit = document.getElementById('modalEdit');

        function open(modal) { modal.classList.remove('hidden'); }
        function closeAll() { modalAdd.classList.add('hidden'); modalEdit.classList.add('hidden'); }

        document.getElementById('btnAddUser').addEventListener('click', function () { open(modalAdd); });

        document.querySelectorAll('.btn-edit-user').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.getElementById('edit_user_id').value = btn.dataset.id;
                document.getElementById('edit_name').value = btn.dataset.name;
                document.getElementById('edit_role').value = btn.dataset.role;
                document.getElementById('edit_password').value = '';
                document.getElementById('editUsernameLabel').textContent = '@' + btn.dataset.username;
                open(modalEdit);
            });
        });

        document.querySelectorAll('.btn-close-modal').forEach(function (btn) {
            btn.addEventListener('click', closeAll);
        });
        [modalAdd, modalEdit].forEach(function (m) {
            m.addEventListener('click', function (ev) { if (ev.target === m) { closeAll(); } });
        });
        document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape') { closeAll(); } });

        // Client-side search & filter
        var sInput = document.getElementById('userSearchInput');
        var rFilter = document.getElementById('userRoleFilter');
        var stFilter = document.getElementById('userStatusFilter');
        var rows = document.querySelectorAll('.user-row');
        function filterUsers() {
            var q = sInput.value.trim().toLowerCase();
            var r = rFilter.value;
            var st = stFilter.value;
            rows.forEach(function (row) {
                var mQ = row.dataset.search.includes(q);
                var mR = !r || row.dataset.role === r;
                var mS = st === '' || row.dataset.status === st;
                row.style.display = (mQ && mR && mS) ? '' : 'none';
            });
        }
        sInput.addEventListener('input', filterUsers);
        rFilter.addEventListener('change', filterUsers);
        stFilter.addEventListener('change', filterUsers);
    })();
</script>

<?php include __DIR__ . '/includes/layout_footer.php'; ?>