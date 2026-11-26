<?php
/**
 * Mark Order as Completed API (SRS-F08)
 * Kedai Paradise POS
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

$transactionId = (int)($_POST['transaction_id'] ?? 0);
if ($transactionId <= 0) {
    jsonError('ID Transaksi tidak valid.', 400);
}

$pdo = db();
$stmt = $pdo->prepare('SELECT transaction_id, status, date FROM transactions WHERE transaction_id = :id LIMIT 1');
$stmt->execute([':id' => $transactionId]);
$tx = $stmt->fetch();

if (!$tx) {
    jsonError('Pesanan tidak ditemukan.', 404);
}
if ($tx['status'] !== 'processing') {
    jsonError('Pesanan sudah selesai atau dibatalkan.', 409);
}

$upd = $pdo->prepare(
    'UPDATE transactions 
     SET status = "completed", 
         completed_at = NOW(), 
         duration_seconds = TIMESTAMPDIFF(SECOND, date, NOW()) 
     WHERE transaction_id = :id'
);
$upd->execute([':id' => $transactionId]);

jsonResponse([
    'success' => true,
    'message' => 'Pesanan ditandai selesai.',
]);