<?php
/**
 * API Buku Kas / Keuangan Manual (Admin Only) - Prompt 8.1
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/auth_check.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    jsonError('Anda tidak memiliki akses ke fitur ini', 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Metode request tidak diizinkan.', 405);
}

$rawBody = file_get_contents('php://input');
$jsonInput = json_decode($rawBody, true);
$input = is_array($jsonInput) ? array_merge($_POST, $jsonInput) : $_POST;

if (!empty($input['csrf_token']) && empty($_POST['csrf_token'])) {
    $_POST['csrf_token'] = $input['csrf_token'];
}

requireCsrf();

$action = $input['action'] ?? '';
$pdo = db();

if ($action === 'create' || $action === 'update') {
    $rawDate     = trim($input['date'] ?? date('Y-m-d H:i:s'));
    $date        = str_replace('T', ' ', $rawDate);
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $date)) {
        $date .= ':00';
    }
    $type        = $input['type'] ?? '';
    $category    = trim($input['category'] ?? '');
    $pool        = $input['pool'] ?? '';
    $amount      = filter_var($input['amount'] ?? 0, FILTER_VALIDATE_INT);
    $description = trim($input['description'] ?? '');
    $userId      = (int)($_SESSION['user_id'] ?? 1);

    if (!in_array($type, ['income', 'expense'], true)) {
        jsonError('Jenis transaksi tidak valid.');
    }
    if (!in_array($pool, ['restock', 'operating', 'profit'], true)) {
        jsonError('Pos dana tidak valid.');
    }
    if ($amount === false || $amount <= 0) {
        jsonError('Nominal harus berupa bilangan bulat lebih besar dari 0.');
    }
    if ($category === '' || mb_strlen($category) > 100) {
        jsonError('Kategori wajib diisi (maksimal 100 karakter).');
    }
    if (strtotime($date) === false) {
        jsonError('Format tanggal tidak valid.');
    }

    try {
        if ($action === 'create') {
            $stmt = $pdo->prepare("INSERT INTO finance (date, type, category, pool, amount, description, user_id) 
                                   VALUES (:date, :type, :category, :pool, :amount, :description, :userId)");
            $stmt->execute([
                'date'        => date('Y-m-d H:i:s', strtotime($date)),
                'type'        => $type,
                'category'    => $category,
                'pool'        => $pool,
                'amount'      => $amount,
                'description' => $description !== '' ? $description : null,
                'userId'      => $userId,
            ]);
            jsonResponse(['success' => true, 'message' => 'Catatan keuangan berhasil disimpan.']);
        } else {
            $financeId = (int)($input['finance_id'] ?? 0);
            if ($financeId <= 0) {
                jsonError('ID catatan keuangan tidak valid.');
            }
            $stmt = $pdo->prepare("UPDATE finance 
                                   SET date = :date, type = :type, category = :category, pool = :pool, amount = :amount, description = :description 
                                   WHERE finance_id = :id");
            $stmt->execute([
                'date'        => date('Y-m-d H:i:s', strtotime($date)),
                'type'        => $type,
                'category'    => $category,
                'pool'        => $pool,
                'amount'      => $amount,
                'description' => $description !== '' ? $description : null,
                'id'          => $financeId,
            ]);
            jsonResponse(['success' => true, 'message' => 'Catatan keuangan berhasil diperbarui.']);
        }
    } catch (Exception $e) {
        jsonError('Gagal menyimpan ke database: ' . $e->getMessage(), 500);
    }

} elseif ($action === 'delete') {
    $financeId = (int)($input['finance_id'] ?? 0);
    if ($financeId <= 0) {
        jsonError('ID transaksi keuangan tidak valid.');
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM finance WHERE finance_id = :id");
        $stmt->execute(['id' => $financeId]);
        jsonResponse(['success' => true, 'message' => 'Catatan keuangan berhasil dihapus. Saldo telah dihitung ulang.']);
    } catch (Exception $e) {
        jsonError('Gagal menghapus data: ' . $e->getMessage(), 500);
    }

} else {
    jsonError('Aksi tidak dikenal.');
}