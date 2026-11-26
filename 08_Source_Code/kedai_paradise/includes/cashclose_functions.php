<?php
/**
 * End-of-Day Cash Closing Business Logic (SRS-F15, TC-D28)
 * Kedai Paradise POS
 */

function getNonVoidCashSalesForDate(PDO $pdo, string $closingDate): int
{
    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(total), 0) 
         FROM transactions 
         WHERE method = 'cash' AND status <> 'void' AND order_date = :date"
    );
    $stmt->execute([':date' => $closingDate]);
    return (int)$stmt->fetchColumn();
}

function saveCashClosing(
    PDO $pdo,
    string $closingDate,
    int $userId,
    int $openingCash,
    int $cashOut,
    int $countedCash,
    ?string $note = null,
    string $role = 'admin'
): array {
    if ($role !== 'admin') {
        throw new RuntimeException('Anda tidak memiliki akses ke fitur ini', 403);
    }

    $today = date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $closingDate) || $closingDate > $today) {
        throw new InvalidArgumentException('Tanggal tutup kasir tidak boleh di masa depan.', 400);
    }
    if ($openingCash < 0 || $cashOut < 0 || $countedCash < 0) {
        throw new InvalidArgumentException('Nominal uang harus berupa angka bulat >= 0.', 400);
    }

    $cleanNote = $note !== null ? trim($note) : '';
    if (mb_strlen($cleanNote) > 255) {
        throw new InvalidArgumentException('Catatan maksimal 255 karakter.', 400);
    }

    $cashSales    = getNonVoidCashSalesForDate($pdo, $closingDate);
    $expectedCash = $openingCash + $cashSales - $cashOut;
    $difference   = $countedCash - $expectedCash;
    $dbNote       = $cleanNote !== '' ? $cleanNote : null;

    $stmtSave = $pdo->prepare(
        "INSERT INTO cash_closings 
            (closing_date, user_id, opening_cash, cash_sales, cash_out, expected_cash, counted_cash, difference, note, created_at) 
         VALUES 
            (:date, :uid, :op, :cs, :co, :exp, :cnt, :diff, :note, NOW())
         ON DUPLICATE KEY UPDATE 
            user_id = :uid2, opening_cash = :op2, cash_sales = :cs2, cash_out = :co2, 
            expected_cash = :exp2, counted_cash = :cnt2, difference = :diff2, note = :note2, created_at = NOW()"
    );
    $stmtSave->execute([
        ':date'  => $closingDate,
        ':uid'   => $userId,
        ':op'    => $openingCash,
        ':cs'    => $cashSales,
        ':co'    => $cashOut,
        ':exp'   => $expectedCash,
        ':cnt'   => $countedCash,
        ':diff'  => $difference,
        ':note'  => $dbNote,
        ':uid2'  => $userId,
        ':op2'   => $openingCash,
        ':cs2'   => $cashSales,
        ':co2'   => $cashOut,
        ':exp2'  => $expectedCash,
        ':cnt2'  => $countedCash,
        ':diff2' => $difference,
        ':note2' => $dbNote,
    ]);

    return [
        'closing_date'  => $closingDate,
        'opening_cash'  => $openingCash,
        'cash_sales'    => $cashSales,
        'cash_out'      => $cashOut,
        'expected_cash' => $expectedCash,
        'counted_cash'  => $countedCash,
        'difference'    => $difference,
        'note'          => $dbNote,
    ];
}