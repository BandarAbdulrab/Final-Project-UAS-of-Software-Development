<?php
/**
 * Order Void (Cancellation) Business Logic (SRS-F09, TC-D25, TC-D26)
 * Kedai Paradise POS
 *
 * Policy notes:
 * - Only whole-order voids exist; partial refunds are done by voiding the order and entering a corrected one.
 * - Cash refunds are handled manually at the counter.
 * - Voided orders keep all rows/amounts for audit trail and their order_no is never reused.
 */

function voidOrder(PDO $pdo, int $transactionId, string $reason, int $userId, string $role = 'admin'): array
{
    if ($role !== 'admin') {
        throw new RuntimeException('Anda tidak memiliki akses ke fitur ini', 403);
    }

    $reason = trim($reason);
    if ($transactionId <= 0) {
        throw new InvalidArgumentException('ID transaksi tidak valid.', 400);
    }
    if (mb_strlen($reason) < 3 || mb_strlen($reason) > 255) {
        throw new InvalidArgumentException('Alasan pembatalan wajib diisi (minimal 3, maksimal 255 karakter).', 400);
    }

    $ownTx = !$pdo->inTransaction();
    if ($ownTx) {
        $pdo->beginTransaction();
    }

    try {
        $stmt = $pdo->prepare('SELECT transaction_id, order_no, status FROM transactions WHERE transaction_id = :id FOR UPDATE');
        $stmt->execute([':id' => $transactionId]);
        $tx = $stmt->fetch();

        if (!$tx) {
            throw new RuntimeException('Transaksi tidak ditemukan.', 404);
        }
        if ($tx['status'] === 'void') {
            throw new RuntimeException('Transaksi ini sudah dibatalkan sebelumnya.', 409);
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
            ':uid'    => $userId,
            ':id'     => $transactionId,
        ]);

        if ($ownTx) {
            $pdo->commit();
        }

        return [
            'transaction_id' => (int)$tx['transaction_id'],
            'order_no'       => (int)$tx['order_no'],
            'status'         => 'void',
            'void_reason'    => $reason,
            'voided_by'      => $userId,
        ];
    } catch (Throwable $e) {
        if ($ownTx && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}