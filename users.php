<?php
/**
 * User management (admin only) - SRS-F02, BR-12, BR-13.
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

/** Returns an error message, or '' when the password is acceptable (8 characters minimum, 72 bytes maximum for bcrypt). */
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

/** Counts active admins and locks those rows, so two admins cannot remove the last two at the same time. */
function countActiveAdmins(PDO $pdo): int
{
    return (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 1 FOR UPDATE")->fetchColumn();
}

/** @return array{0:string,1:string} [error message or '', success message key or ''] */
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

/** @return array{0:string,1:string} */
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

        // BR-13: the last active admin cannot be turned into a cashier.
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

/** @return array{0:string,1:string} */
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

$pageTitle = t('users_title') . ' - Kedai Paradise';
include __DIR__ . '/includes/layout_header.php';
?>

<div class="space-y-4">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-[#1B4D3E]"><?= e(t('users_title')) ?></h1>
        <button type="button" id="btnAddUser" class="bg-[#1B4D3E] hover:bg-[#153d31] text-white text-sm font-semibold px-4 py-2 rounded transition">
            + <?= e(t('users_add')) ?>
        </button>
    </div>

    <?php if ($flash): ?>
        <div class="text-sm rounded p-3 border <?= $flash['type'] === 'success' ? 'text-green-800 bg-green-50 border-green-200' : 'text-red-700 bg-red-50 border-red-200' ?>" role="alert">
            <?= e($flash['message']) ?>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-3">ID</th>
                    <th class="px-4 py-3"><?= e(t('users_name')) ?></th>
                    <th class="px-4 py-3"><?= e(t('users_username')) ?></th>
                    <th class="px-4 py-3"><?= e(t('users_role')) ?></th>
                    <th class="px-4 py-3"><?= e(t('users_status')) ?></th>
                    <th class="px-4 py-3 text-right"><?= e(t('action')) ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php if (!$users): ?>
                    <tr><td colspan="6" class="px-4 py-6 text-center text-gray-500"><?= e(t('users_empty')) ?></td></tr>
                <?php endif; ?>
                <?php foreach ($users as $u): ?>
                    <?php $isSelf = (int)$u['user_id'] === $selfId; $isActive = (int)$u['status'] === 1; ?>
                    <tr class="<?= $isActive ? '' : 'bg-gray-50 text-gray-500' ?>">
                        <td class="px-4 py-3"><?= (int)$u['user_id'] ?></td>
                        <td class="px-4 py-3 font-medium">
                            <?= e($u['name']) ?>
                            <?php if ($isSelf): ?>
                                <span class="ml-1 text-xs bg-amber-100 text-amber-800 px-1.5 py-0.5 rounded"><?= e(t('users_you')) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 font-mono">@<?= e($u['username']) ?></td>
                        <td class="px-4 py-3">
                            <span class="text-xs font-semibold px-2 py-1 rounded <?= $u['role'] === 'admin' ? 'bg-[#1B4D3E] text-white' : 'bg-slate-200 text-[#2C3E50]' ?>">
                                <?= e(t('role_' . $u['role'])) ?>
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="text-xs font-semibold px-2 py-1 rounded <?= $isActive ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-600' ?>">
                                <?= e(t($isActive ? 'status_active' : 'status_inactive')) ?>
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-2">
                                <button type="button"
                                        class="btn-edit-user text-xs bg-gray-100 hover:bg-gray-200 text-[#2C3E50] px-3 py-1.5 rounded transition"
                                        data-id="<?= (int)$u['user_id'] ?>"
                                        data-name="<?= e($u['name']) ?>"
                                        data-username="<?= e($u['username']) ?>"
                                        data-role="<?= e($u['role']) ?>">
                                    <?= e(t('edit')) ?> / Reset
                                </button>
                                <form method="post" action="users.php" class="inline"
                                      <?php if ($isActive): ?>onsubmit="return confirm('<?= e(t('users_confirm_deactivate')) ?>');"<?php endif; ?>>
                                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="user_id" value="<?= (int)$u['user_id'] ?>">
                                    <input type="hidden" name="target" value="<?= $isActive ? 'deactivate' : 'activate' ?>">
                                    <?php if ($isActive): ?>
                                        <button type="submit" <?= $isSelf ? 'disabled title="' . e(t('err_cannot_deactivate_self')) . '"' : '' ?>
                                                class="text-xs px-3 py-1.5 rounded transition <?= $isSelf ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-red-50 hover:bg-red-100 text-red-700' ?>">
                                            <?= e(t('users_deactivate')) ?>
                                        </button>
                                    <?php else: ?>
                                        <button type="submit" class="text-xs bg-green-50 hover:bg-green-100 text-green-800 px-3 py-1.5 rounded transition">
                                            <?= e(t('users_activate')) ?>
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

<!-- Add user modal -->
<div id="modalAdd" class="hidden fixed inset-0 z-40 bg-black/50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6">
        <h2 class="text-lg font-bold text-[#1B4D3E] mb-4"><?= e(t('users_add_title')) ?></h2>
        <form method="post" action="users.php" class="space-y-3" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="action" value="add">
            <div>
                <label for="add_name" class="block text-sm font-medium mb-1"><?= e(t('users_name')) ?></label>
                <input type="text" id="add_name" name="name" required maxlength="100"
                       class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B4D3E]">
            </div>
            <div>
                <label for="add_username" class="block text-sm font-medium mb-1"><?= e(t('users_username')) ?></label>
                <input type="text" id="add_username" name="username" required minlength="3" maxlength="30" pattern="[a-z0-9_]{3,30}"
                       class="w-full border border-gray-300 rounded px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-[#1B4D3E]">
                <p class="text-xs text-gray-500 mt-1"><?= e(t('err_username_invalid')) ?></p>
            </div>
            <div>
                <label for="add_role" class="block text-sm font-medium mb-1"><?= e(t('users_role')) ?></label>
                <select id="add_role" name="role" class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
                    <option value="cashier"><?= e(t('role_cashier')) ?></option>
                    <option value="admin"><?= e(t('role_admin')) ?></option>
                </select>
            </div>
            <div>
                <label for="add_password" class="block text-sm font-medium mb-1"><?= e(t('users_password')) ?></label>
                <input type="password" id="add_password" name="password" required minlength="8" maxlength="72" autocomplete="new-password"
                       class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B4D3E]">
                <p class="text-xs text-gray-500 mt-1"><?= e(t('users_password_hint')) ?></p>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" class="btn-close-modal bg-gray-100 hover:bg-gray-200 text-sm px-4 py-2 rounded transition"><?= e(t('cancel')) ?></button>
                <button type="submit" class="bg-[#1B4D3E] hover:bg-[#153d31] text-white text-sm font-semibold px-4 py-2 rounded transition"><?= e(t('save')) ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Edit / reset user modal -->
<div id="modalEdit" class="hidden fixed inset-0 z-40 bg-black/50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6">
        <h2 class="text-lg font-bold text-[#1B4D3E] mb-1"><?= e(t('users_edit_title')) ?></h2>
        <p id="editUsernameLabel" class="text-sm font-mono text-gray-500 mb-4"></p>
        <form method="post" action="users.php" class="space-y-3" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="user_id" id="edit_user_id" value="">
            <div>
                <label for="edit_name" class="block text-sm font-medium mb-1"><?= e(t('users_name')) ?></label>
                <input type="text" id="edit_name" name="name" required maxlength="100"
                       class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B4D3E]">
            </div>
            <div>
                <label for="edit_role" class="block text-sm font-medium mb-1"><?= e(t('users_role')) ?></label>
                <select id="edit_role" name="role" class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
                    <option value="cashier"><?= e(t('role_cashier')) ?></option>
                    <option value="admin"><?= e(t('role_admin')) ?></option>
                </select>
            </div>
            <div>
                <label for="edit_password" class="block text-sm font-medium mb-1"><?= e(t('users_password_optional')) ?></label>
                <input type="password" id="edit_password" name="password" minlength="8" maxlength="72" autocomplete="new-password"
                       class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B4D3E]">
                <p class="text-xs text-gray-500 mt-1"><?= e(t('users_password_hint')) ?></p>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" class="btn-close-modal bg-gray-100 hover:bg-gray-200 text-sm px-4 py-2 rounded transition"><?= e(t('cancel')) ?></button>
                <button type="submit" class="bg-[#1B4D3E] hover:bg-[#153d31] text-white text-sm font-semibold px-4 py-2 rounded transition"><?= e(t('save')) ?></button>
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
    })();
</script>

<?php include __DIR__ . '/includes/layout_footer.php'; ?>
