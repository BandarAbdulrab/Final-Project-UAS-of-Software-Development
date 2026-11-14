<?php
/**
 * Menu and Modifier Server-Side Business Logic (SRS-F03, BR-16..BR-19, TC-D09)
 * Kedai Paradise POS
 */

const MAX_MODIFIER_GROUPS_PER_MENU = 5;
const MAX_MODIFIER_ITEMS_PER_GROUP = 10;

/**
 * Create a modifier group for a menu item.
 * Enforces max 5 groups per menu item (BR-16, TC-D09).
 * Accepts either ($pdo, $menuId, $name, $type, $isRequired) or ($menuId, $name, $type, $isRequired) or ($arrayData).
 */
function createModifierGroup($arg1, $arg2 = null, string $arg3 = 'single', int $arg4 = 0, int $arg5 = 0): int
{
    if ($arg1 instanceof PDO) {
        $pdo = $arg1;
        if (is_array($arg2)) {
            $menuId = (int)($arg2['menu_id'] ?? 0);
            $name = trim((string)($arg2['name'] ?? ''));
            $type = (string)($arg2['type'] ?? 'single');
            $isRequired = !empty($arg2['is_required']) ? 1 : 0;
        } else {
            $menuId = (int)$arg2;
            $name = trim((string)$arg3);
            $type = in_array((string)$arg4, ['single', 'multiple'], true) ? (string)$arg4 : 'single';
            $isRequired = $arg5 ? 1 : 0;
        }
    } elseif (is_array($arg1)) {
        $pdo = db();
        $menuId = (int)($arg1['menu_id'] ?? 0);
        $name = trim((string)($arg1['name'] ?? ''));
        $type = (string)($arg1['type'] ?? 'single');
        $isRequired = !empty($arg1['is_required']) ? 1 : 0;
    } else {
        $pdo = db();
        $menuId = (int)$arg1;
        $name = trim((string)$arg2);
        $type = in_array($arg3, ['single', 'multiple'], true) ? $arg3 : 'single';
        $isRequired = $arg4 ? 1 : 0;
    }

    if ($menuId <= 0) {
        throw new InvalidArgumentException('ID menu tidak valid.');
    }
    if ($name === '' || mb_strlen($name) > 60) {
        throw new InvalidArgumentException('Nama grup opsi wajib diisi (maksimal 60 karakter).');
    }
    if (!in_array($type, ['single', 'multiple'], true)) {
        throw new InvalidArgumentException('Tipe grup opsi harus single atau multiple.');
    }

    $chkMenu = $pdo->prepare('SELECT COUNT(*) FROM menu WHERE menu_id = :m');
    $chkMenu->execute([':m' => $menuId]);
    if ((int)$chkMenu->fetchColumn() === 0) {
        throw new RuntimeException('Menu tidak ditemukan.');
    }

    $stmtCount = $pdo->prepare('SELECT COUNT(*) FROM menu_modifier_groups WHERE menu_id = :m');
    $stmtCount->execute([':m' => $menuId]);
    if ((int)$stmtCount->fetchColumn() >= MAX_MODIFIER_GROUPS_PER_MENU) {
        throw new RuntimeException('Maksimal ' . MAX_MODIFIER_GROUPS_PER_MENU . ' grup opsi per item menu telah tercapai.');
    }

    $stmt = $pdo->prepare(
        'INSERT INTO menu_modifier_groups (menu_id, name, type, is_required)
         VALUES (:m, :name, :type, :req)'
    );
    $stmt->execute([
        ':m' => $menuId,
        ':name' => $name,
        ':type' => $type,
        ':req' => $isRequired ? 1 : 0,
    ]);

    return (int)$pdo->lastInsertId();
}

/**
 * Create a modifier option item inside a group.
 * Enforces max 10 items per modifier group (BR-16).
 */
function createModifierItem($arg1, $arg2 = null, $arg3 = 0, $arg4 = 0, $arg5 = 1, $arg6 = 1): int
{
    if ($arg1 instanceof PDO) {
        $pdo = $arg1;
        if (is_array($arg2)) {
            $groupId = (int)($arg2['group_id'] ?? 0);
            $name = trim((string)($arg2['name'] ?? ''));
            $extraPrice = (int)($arg2['extra_price'] ?? 0);
            $extraCost = (int)($arg2['extra_cost'] ?? 0);
            $available = isset($arg2['available']) ? ((int)$arg2['available'] ? 1 : 0) : 1;
        } else {
            $groupId = (int)$arg2;
            $name = trim((string)$arg3);
            $extraPrice = (int)$arg4;
            $extraCost = (int)$arg5;
            $available = $arg6 ? 1 : 0;
        }
    } elseif (is_array($arg1)) {
        $pdo = db();
        $groupId = (int)($arg1['group_id'] ?? 0);
        $name = trim((string)($arg1['name'] ?? ''));
        $extraPrice = (int)($arg1['extra_price'] ?? 0);
        $extraCost = (int)($arg1['extra_cost'] ?? 0);
        $available = isset($arg1['available']) ? ((int)$arg1['available'] ? 1 : 0) : 1;
    } else {
        $pdo = db();
        $groupId = (int)$arg1;
        $name = trim((string)$arg2);
        $extraPrice = (int)$arg3;
        $extraCost = (int)$arg4;
        $available = $arg5 ? 1 : 0;
    }

    if ($groupId <= 0) {
        throw new InvalidArgumentException('Grup opsi tidak valid.');
    }
    if ($name === '' || mb_strlen($name) > 60) {
        throw new InvalidArgumentException('Nama item opsi wajib diisi (maksimal 60 karakter).');
    }
    if ($extraPrice < 0 || $extraCost < 0) {
        throw new InvalidArgumentException('Harga tambahan dan HPP tambahan tidak boleh negatif.');
    }

    $chkGroup = $pdo->prepare('SELECT COUNT(*) FROM menu_modifier_groups WHERE group_id = :g');
    $chkGroup->execute([':g' => $groupId]);
    if ((int)$chkGroup->fetchColumn() === 0) {
        throw new RuntimeException('Grup opsi tidak ditemukan.');
    }

    $stmtCount = $pdo->prepare('SELECT COUNT(*) FROM menu_modifier_items WHERE group_id = :g');
    $stmtCount->execute([':g' => $groupId]);
    if ((int)$stmtCount->fetchColumn() >= MAX_MODIFIER_ITEMS_PER_GROUP) {
        throw new RuntimeException('Maksimal ' . MAX_MODIFIER_ITEMS_PER_GROUP . ' item opsi per grup telah tercapai.');
    }

    $stmt = $pdo->prepare(
        'INSERT INTO menu_modifier_items (group_id, name, extra_price, extra_cost, available)
         VALUES (:g, :name, :price, :cost, :avail)'
    );
    $stmt->execute([
        ':g' => $groupId,
        ':name' => $name,
        ':price' => $extraPrice,
        ':cost' => $extraCost,
        ':avail' => $available,
    ]);

    return (int)$pdo->lastInsertId();
}