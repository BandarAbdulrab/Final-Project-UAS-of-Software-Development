<?php
/**
 * Admin API Endpoint for Menu & Modifier CRUD (SRS-F03)
 * Kedai Paradise POS
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/includes/menu_functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Metode request tidak diizinkan.', 405);
}

if (($_SESSION['role'] ?? '') !== 'admin') {
    jsonError(t('forbidden'), 403);
}

// Parse JSON body if sent as application/json and merge into $_POST
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false) {
    $rawInput = file_get_contents('php://input');
    $decoded = json_decode($rawInput ?: '', true);
    if (is_array($decoded)) {
        $_POST = array_merge($_POST, $decoded);
    }
}

requireCsrf();

$pdo = db();
$action = trim((string)($_POST['action'] ?? ''));

try {
    switch ($action) {
        case 'menu_create': {
            $name = trim((string)($_POST['name'] ?? ''));
            $category = trim((string)($_POST['category'] ?? 'Lainnya'));
            if ($category === '') {
                $category = 'Lainnya';
            }
            $price = isset($_POST['price']) && $_POST['price'] !== '' ? (int)$_POST['price'] : -1;
            $costPrice = isset($_POST['cost_price']) && $_POST['cost_price'] !== '' ? (int)$_POST['cost_price'] : -1;
            $available = !empty($_POST['available']) ? 1 : 0;
            $status = isset($_POST['status']) ? (!empty($_POST['status']) ? 1 : 0) : 1;

            if ($name === '' || mb_strlen($name) > 100) {
                jsonError('Nama menu wajib diisi (maksimal 100 karakter).', 400);
            }
            if (mb_strlen($category) > 30) {
                jsonError('Kategori maksimal 30 karakter.', 400);
            }
            if ($price < 0 || $costPrice < 0) {
                jsonError('Harga jual dan HPP harus bilangan bulat >= 0.', 400);
            }

            $photoPath = null;
            if (!empty($_FILES['photo']) && ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $photoPath = saveUploadedImage($_FILES['photo'], 'menu', 'menu', 1048576);
            }

            $stmt = $pdo->prepare(
                'INSERT INTO menu (name, category, price, cost_price, photo, available, status)
                 VALUES (:name, :cat, :price, :cost, :photo, :avail, :status)'
            );
            $stmt->execute([
                ':name' => $name,
                ':cat' => $category,
                ':price' => $price,
                ':cost' => $costPrice,
                ':photo' => $photoPath,
                ':avail' => $available,
                ':status' => $status,
            ]);

            jsonResponse([
                'success' => true,
                'menu_id' => (int)$pdo->lastInsertId(),
                'message' => 'Menu berhasil ditambahkan.',
            ]);
        }

        case 'menu_update': {
            $menuId = (int)($_POST['menu_id'] ?? 0);
            $name = trim((string)($_POST['name'] ?? ''));
            $category = trim((string)($_POST['category'] ?? 'Lainnya'));
            if ($category === '') {
                $category = 'Lainnya';
            }
            $price = isset($_POST['price']) && $_POST['price'] !== '' ? (int)$_POST['price'] : -1;
            $costPrice = isset($_POST['cost_price']) && $_POST['cost_price'] !== '' ? (int)$_POST['cost_price'] : -1;
            $available = !empty($_POST['available']) ? 1 : 0;
            $status = !empty($_POST['status']) ? 1 : 0;

            if ($menuId <= 0) {
                jsonError('ID menu tidak valid.', 400);
            }
            if ($name === '' || mb_strlen($name) > 100) {
                jsonError('Nama menu wajib diisi (maksimal 100 karakter).', 400);
            }
            if (mb_strlen($category) > 30) {
                jsonError('Kategori maksimal 30 karakter.', 400);
            }
            if ($price < 0 || $costPrice < 0) {
                jsonError('Harga jual dan HPP harus bilangan bulat >= 0.', 400);
            }

            $stmtOld = $pdo->prepare('SELECT photo FROM menu WHERE menu_id = :id LIMIT 1');
            $stmtOld->execute([':id' => $menuId]);
            $existing = $stmtOld->fetch();
            if (!$existing) {
                jsonError('Menu tidak ditemukan.', 404);
            }

            $photoPath = $existing['photo'];
            if (!empty($_FILES['photo']) && ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $newPhoto = saveUploadedImage($_FILES['photo'], 'menu', 'menu', 1048576);
                if (!empty($photoPath)) {
                    $oldFull = __DIR__ . '/' . ltrim($photoPath, '/');
                    if (is_file($oldFull)) {
                        @unlink($oldFull);
                    }
                }
                $photoPath = $newPhoto;
            }

            $stmt = $pdo->prepare(
                'UPDATE menu
                 SET name = :name, category = :cat, price = :price, cost_price = :cost,
                     photo = :photo, available = :avail, status = :status
                 WHERE menu_id = :id'
            );
            $stmt->execute([
                ':name' => $name,
                ':cat' => $category,
                ':price' => $price,
                ':cost' => $costPrice,
                ':photo' => $photoPath,
                ':avail' => $available,
                ':status' => $status,
                ':id' => $menuId,
            ]);

            jsonResponse([
                'success' => true,
                'message' => 'Menu berhasil diperbarui.',
            ]);
        }

        case 'menu_toggle_status': {
            $menuId = (int)($_POST['menu_id'] ?? 0);
            $status = !empty($_POST['status']) ? 1 : 0;

            $stmt = $pdo->prepare('SELECT menu_id FROM menu WHERE menu_id = :id');
            $stmt->execute([':id' => $menuId]);
            if (!$stmt->fetch()) {
                jsonError('Menu tidak ditemukan.', 404);
            }

            $upd = $pdo->prepare('UPDATE menu SET status = :s WHERE menu_id = :id');
            $upd->execute([':s' => $status, ':id' => $menuId]);

            jsonResponse([
                'success' => true,
                'status' => $status,
                'message' => $status === 1 ? 'Menu diaktifkan.' : 'Menu dinonaktifkan.',
            ]);
        }

        case 'group_create': {
            $menuId = (int)($_POST['menu_id'] ?? 0);
            $name = trim((string)($_POST['name'] ?? ''));
            $type = (string)($_POST['type'] ?? 'single');
            $isRequired = !empty($_POST['is_required']) ? 1 : 0;

            $groupId = createModifierGroup($pdo, [
                'menu_id' => $menuId,
                'name' => $name,
                'type' => $type,
                'is_required' => $isRequired,
            ]);

            jsonResponse([
                'success' => true,
                'group_id' => $groupId,
                'message' => 'Grup opsi berhasil ditambahkan.',
            ]);
        }

        case 'group_update': {
            $groupId = (int)($_POST['group_id'] ?? 0);
            $name = trim((string)($_POST['name'] ?? ''));
            $type = (string)($_POST['type'] ?? 'single');
            $isRequired = !empty($_POST['is_required']) ? 1 : 0;

            if ($groupId <= 0 || $name === '' || mb_strlen($name) > 60) {
                jsonError('Data grup opsi tidak valid.', 400);
            }
            if (!in_array($type, ['single', 'multiple'], true)) {
                jsonError('Tipe grup harus single atau multiple.', 400);
            }

            $stmt = $pdo->prepare(
                'UPDATE menu_modifier_groups
                 SET name = :name, type = :type, is_required = :req
                 WHERE group_id = :id'
            );
            $stmt->execute([
                ':name' => $name,
                ':type' => $type,
                ':req' => $isRequired,
                ':id' => $groupId,
            ]);

            jsonResponse([
                'success' => true,
                'message' => 'Grup opsi berhasil diperbarui.',
            ]);
        }

        case 'group_delete': {
            $groupId = (int)($_POST['group_id'] ?? 0);
            if ($groupId <= 0) {
                jsonError('ID grup opsi tidak valid.', 400);
            }

            $stmt = $pdo->prepare('DELETE FROM menu_modifier_groups WHERE group_id = :id');
            $stmt->execute([':id' => $groupId]);

            jsonResponse([
                'success' => true,
                'message' => 'Grup opsi berhasil dihapus.',
            ]);
        }

        case 'item_create': {
            $groupId = (int)($_POST['group_id'] ?? 0);
            $name = trim((string)($_POST['name'] ?? ''));
            $extraPrice = isset($_POST['extra_price']) && $_POST['extra_price'] !== '' ? (int)$_POST['extra_price'] : 0;
            $extraCost = isset($_POST['extra_cost']) && $_POST['extra_cost'] !== '' ? (int)$_POST['extra_cost'] : 0;
            $available = isset($_POST['available']) ? (!empty($_POST['available']) ? 1 : 0) : 1;

            $modifierId = createModifierItem($pdo, [
                'group_id' => $groupId,
                'name' => $name,
                'extra_price' => $extraPrice,
                'extra_cost' => $extraCost,
                'available' => $available,
            ]);

            jsonResponse([
                'success' => true,
                'modifier_id' => $modifierId,
                'message' => 'Item opsi berhasil ditambahkan.',
            ]);
        }

        case 'item_update': {
            $modifierId = (int)($_POST['modifier_id'] ?? 0);
            $name = trim((string)($_POST['name'] ?? ''));
            $extraPrice = isset($_POST['extra_price']) && $_POST['extra_price'] !== '' ? (int)$_POST['extra_price'] : -1;
            $extraCost = isset($_POST['extra_cost']) && $_POST['extra_cost'] !== '' ? (int)$_POST['extra_cost'] : -1;
            $available = !empty($_POST['available']) ? 1 : 0;

            if ($modifierId <= 0 || $name === '' || mb_strlen($name) > 60 || $extraPrice < 0 || $extraCost < 0) {
                jsonError('Data item opsi tidak valid.', 400);
            }

            $stmt = $pdo->prepare(
                'UPDATE menu_modifier_items
                 SET name = :name, extra_price = :price, extra_cost = :cost, available = :avail
                 WHERE modifier_id = :id'
            );
            $stmt->execute([
                ':name' => $name,
                ':price' => $extraPrice,
                ':cost' => $extraCost,
                ':avail' => $available,
                ':id' => $modifierId,
            ]);

            jsonResponse([
                'success' => true,
                'message' => 'Item opsi berhasil diperbarui.',
            ]);
        }

        case 'item_delete': {
            $modifierId = (int)($_POST['modifier_id'] ?? 0);
            if ($modifierId <= 0) {
                jsonError('ID item opsi tidak valid.', 400);
            }

            $stmt = $pdo->prepare('DELETE FROM menu_modifier_items WHERE modifier_id = :id');
            $stmt->execute([':id' => $modifierId]);

            jsonResponse([
                'success' => true,
                'message' => 'Item opsi berhasil dihapus.',
            ]);
        }

        default:
            jsonError('Aksi tidak dikenali.', 400);
    }
} catch (InvalidArgumentException | RuntimeException $e) {
    jsonError($e->getMessage(), 400);
} catch (Throwable $e) {
    error_log('api_menu error: ' . $e->getMessage());
    jsonError(t('err_generic'), 500);
}