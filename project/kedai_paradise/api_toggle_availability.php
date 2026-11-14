<?php
/**
 * Toggle Menu Availability API (SRS-F03, BR-01)
 * Accessible to any logged-in role (admin & cashier).
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Metode request tidak diizinkan.', 405);
}

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false) {
    $rawInput = file_get_contents('php://input');
    $decoded = json_decode($rawInput ?: '', true);
    if (is_array($decoded)) {
        $_POST = array_merge($_POST, $decoded);
    }
}

requireCsrf();

$menuId = (int)($_POST['menu_id'] ?? 0);
$available = isset($_POST['available']) ? ((int)$_POST['available'] === 1 ? 1 : 0) : -1;

if ($menuId <= 0 || !in_array($available, [0, 1], true)) {
    jsonError('Parameter tidak valid.', 400);
}

$pdo = db();
$stmt = $pdo->prepare('SELECT menu_id FROM menu WHERE menu_id = :id AND status = 1 LIMIT 1');
$stmt->execute([':id' => $menuId]);

if (!$stmt->fetch()) {
    jsonError('Menu tidak ditemukan atau nonaktif.', 404);
}

$upd = $pdo->prepare('UPDATE menu SET available = :avail WHERE menu_id = :id');
$upd->execute([
    ':avail' => $available,
    ':id' => $menuId,
]);

jsonResponse([
    'success' => true,
    'menu_id' => $menuId,
    'available' => $available,
]);