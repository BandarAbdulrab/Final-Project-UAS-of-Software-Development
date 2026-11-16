<?php
/**
 * Issue a fresh checkout token for idempotency recovery (SRS Rev. D.1, BR-26)
 * Kedai Paradise POS
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Metode request tidak diizinkan.', 405);
}

if (empty($_SESSION['checkout_token'])) {
    $_SESSION['checkout_token'] = bin2hex(random_bytes(32));
}

jsonResponse([
    'success' => true,
    'token' => $_SESSION['checkout_token'],
]);