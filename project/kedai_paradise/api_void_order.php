<?php
/**
 * Admin API Endpoint for Order Voids (SRS-F09, TC-D25)
 * Kedai Paradise POS
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Metode request tidak diizinkan.', 405);
}

if (($_SESSION['role'] ?? '') !== 'admin') {
    jsonError(t('forbidden'), 403);
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
$reason = trim((string)($_POST['reason'] ?? ''));

if ($transactionId <= 0 || mb_strlen($reason) < 3 || mb_strlen($reason) > 255) {
    jsonError('Alasan pembatalan wajib diisi (minimal 3, maksimal 255 karakter).', 400);
}

$pdo = db();
$pdo->beginTransaction();

try {
    $stmt = $pdo->prepare('SELECT transaction_id, status FROM transactions WHERE transaction_id = :id FOR UPDATE');
    $stmt->execute([':id' => $transactionId]);
    $tx = $stmt->fetch();

    if (!$tx) {
        throw new Exception('Transaksi tidak ditemukan.');
    }
    if ($tx['status'] === 'void') {
        throw new Exception('Transaksi ini sudah dibatalkan sebelumnya.');
    }

    $upd = $pdo->prepare(
        'UPDATE transactions 
         SET status = "void", 
             void_reason = :reason, 
             voided_at = NOW(), 
             voided_by = :uid 
         WHERE transaction_id = :id'
    );
    $upd->execute([
        ':reason' => $reason,
        ':uid' => $_SESSION['user_id'],
        ':id' => $transactionId,
    ]);

    $pdo->commit();
    jsonResponse([
        'success' => true,
        'message' => 'Pesanan berhasil dibatalkan.',
    ]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    jsonError($e->getMessage(), 400);
}