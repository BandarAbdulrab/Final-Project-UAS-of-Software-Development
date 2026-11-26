<?php
/**
 * Admin API Endpoint for Order Voids (SRS-F09, TC-D25, TC-D26)
 * Kedai Paradise POS
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/includes/void_functions.php';

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
$reason = trim((string)($_POST['reason'] ?? ''));
$role = (string)($_SESSION['role'] ?? '');
$userId = (int)($_SESSION['user_id'] ?? 0);

try {
    $result = voidOrder(db(), $transactionId, $reason, $userId, $role);
    jsonResponse([
        'success' => true,
        'message' => 'Pesanan berhasil dibatalkan.',
        'data'    => $result,
    ]);
} catch (Throwable $e) {
    $code = (int)$e->getCode();
    if (!in_array($code, [400, 403, 404, 409], true)) {
        $code = 400;
    }
    jsonError($e->getMessage(), $code);
}