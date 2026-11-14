<?php
/**
 * api_modifiers.php
 * Mengambil konfigurasi grup modifikasi dan item pilihan untuk menu tertentu (SRS-F03, SRS-F04).
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Metode tidak diizinkan.', 405);
}

$menuId = isset($_GET['menu_id']) ? filter_var($_GET['menu_id'], FILTER_VALIDATE_INT) : false;
if ($menuId === false || $menuId <= 0) {
    jsonError('ID menu tidak valid.', 400);
}

try {
    $pdo = db();

    // 1. Validasi bahwa menu ada dan statusnya aktif (status = 1)
    $stmtMenu = $pdo->prepare('SELECT menu_id, name, price FROM menu WHERE menu_id = :menu_id AND status = 1 LIMIT 1');
    $stmtMenu->execute([':menu_id' => $menuId]);
    $menu = $stmtMenu->fetch();

    if (!$menu) {
        jsonError('Menu tidak ditemukan atau tidak aktif.', 404);
    }

    // 2. Ambil semua grup modifier untuk menu ini urut group_id ASC
    $stmtGroups = $pdo->prepare('
        SELECT group_id, name, type, is_required
        FROM menu_modifier_groups
        WHERE menu_id = :menu_id
        ORDER BY group_id ASC
    ');
    $stmtGroups->execute([':menu_id' => $menuId]);
    $groups = $stmtGroups->fetchAll();

    // Siapkan query item modifier (hanya ambil yang available = 1, JANGAN sertakan extra_cost)
    $stmtItems = $pdo->prepare('
        SELECT modifier_id, name, extra_price
        FROM menu_modifier_items
        WHERE group_id = :group_id AND available = 1
        ORDER BY modifier_id ASC
    ');

    $formattedGroups = [];
    foreach ($groups as $group) {
        $stmtItems->execute([':group_id' => (int)$group['group_id']]);
        $rawItems = $stmtItems->fetchAll();

        $items = [];
        foreach ($rawItems as $item) {
            $items[] = [
                'modifier_id' => (int)$item['modifier_id'],
                'name'        => $item['name'],
                'extra_price' => (int)$item['extra_price'],
            ];
        }

        $formattedGroups[] = [
            'group_id'    => (int)$group['group_id'],
            'name'        => $group['name'],
            'type'        => $group['type'], // 'single' atau 'multiple'
            'is_required' => (int)$group['is_required'],
            'items'       => $items,
        ];
    }

    jsonResponse([
        'success'    => true,
        'menu_id'    => (int)$menu['menu_id'],
        'menu_name'  => $menu['name'],
        'base_price' => (int)$menu['price'],
        'groups'     => $formattedGroups,
    ]);
} catch (Throwable $e) {
    error_log('api_modifiers error: ' . $e->getMessage());
    jsonError(t('err_generic'), 500);
}