<?php
/**
 * Cashier & Admin Transaction History Ledger (SRS-F09, BR-12, BR-25)
 * Kedai Paradise POS
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/auth_check.php';

$isAdmin = ($_SESSION['role'] ?? '') === 'admin';
$pdo = db();

// Filters & Pagination
$fromDate = trim((string)($_GET['from'] ?? ($isAdmin ? date('Y-m-01') : date('Y-m-d'))));
$toDate = trim((string)($_GET['to'] ?? date('Y-m-d')));
if (!$isAdmin) {
    $fromDate = date('Y-m-d');
    $toDate = date('Y-m-d');
}

$statusFilter = trim((string)($_GET['status'] ?? 'all'));
$methodFilter = trim((string)($_GET['method'] ?? 'all'));
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 50;
$offset = ($page - 1) * $limit;

// Whitelist sorting
$allowedSort = ['order_no', 'date', 'table_no', 'method', 'total', 'status', 'duration_seconds'];
if ($isAdmin) {
    $allowedSort = array_merge($allowedSort, ['total_cost', 'gross_profit', 'alloc_profit', 'cashier_name']);
}
$sort = trim((string)($_GET['sort'] ?? 'date'));
if (!in_array($sort, $allowedSort, true)) {
    $sort = 'date';
}
$dir = strtolower(trim((string)($_GET['dir'] ?? 'desc'))) === 'asc' ? 'ASC' : 'DESC';

// Build SQL query
$where = ['1=1'];
$params = [];

if ($isAdmin) {
    $where[] = 't.order_date BETWEEN :fromDate AND :toDate';
    $params[':fromDate'] = $fromDate;
    $params[':toDate'] = $toDate;
} else {
    $where[] = 't.order_date = CURDATE()';
}

if ($statusFilter !== 'all' && in_array($statusFilter, ['processing', 'completed', 'void'], true)) {
    $where[] = 't.status = :status';
    $params[':status'] = $statusFilter;
}

if ($methodFilter !== 'all' && in_array($methodFilter, ['cash', 'qris'], true)) {
    $where[] = 't.method = :method';
    $params[':method'] = $methodFilter;
}

$whereSql = implode(' AND ', $where);

// Count total rows
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM transactions t WHERE $whereSql");
$stmtCount->execute($params);
$totalRows = (int)$stmtCount->fetchColumn();
$totalPages = ceil($totalRows / $limit);

// Fetch summary totals (excluding voids per SALES_FILTER)
$stmtSum = $pdo->prepare(
    "SELECT COUNT(*) AS total_count, SUM(t.total) AS total_revenue 
     FROM transactions t 
     WHERE $whereSql AND t.status <> 'void'"
);
$stmtSum->execute($params);
$summary = $stmtSum->fetch();

// Fetch paginated transactions
$sql = "SELECT t.*, u.name AS cashier_name, ub.name AS voided_by_name 
        FROM transactions t 
        LEFT JOIN users u ON u.user_id = t.user_id 
        LEFT JOIN users ub ON ub.user_id = t.voided_by 
        WHERE $whereSql 
        ORDER BY t.$sort $dir, t.transaction_id DESC 
        LIMIT :limit OFFSET :offset";

$stmtTx = $pdo->prepare($sql);
foreach ($params as $k => $v) {
    $stmtTx->bindValue($k, $v);
}
$stmtTx->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmtTx->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmtTx->execute();
$transactions = $stmtTx->fetchAll();

// Fetch orders and items for these transactions
$txIds = array_column($transactions, 'transaction_id');
$ordersByTx = [];
$modsByOrder = [];

if (!empty($txIds)) {
    $in = implode(',', array_fill(0, count($txIds), '?'));
    $stmtO = $pdo->prepare("SELECT o.*, m.name AS menu_name FROM orders o JOIN menu m ON m.menu_id = o.menu_id WHERE o.transaction_id IN ($in)");
    $stmtO->execute($txIds);
    while ($ord = $stmtO->fetch()) {
        $ordersByTx[(int)$ord['transaction_id']][] = $ord;
        $orderIds[] = (int)$ord['order_id'];
    }

    if (!empty($orderIds)) {
        $inO = implode(',', array_fill(0, count($orderIds), '?'));
        $stmtM = $pdo->prepare("SELECT * FROM order_item_modifiers WHERE order_id IN ($inO)");
        $stmtM->execute($orderIds);
        while ($m = $stmtM->fetch()) {
            $modsByOrder[(int)$m['order_id']][] = $m['modifier_name'];
        }
    }
}

$pageTitle = 'Riwayat Transaksi';
include __DIR__ . '/includes/layout_header.php';
?>

<div class="max-w-7xl mx-auto px-4 py-6 space-y-6">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-2xl font-bold text-[#2C3E50]">Riwayat Transaksi</h1>
            <p class="text-sm text-gray-600">Catatan pesanan, cetak ulang struk, dan audit pembatalan.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="export_csv.php?report=history&from=<?= e($fromDate) ?>&to=<?= e($toDate) ?>&status=<?= e($statusFilter) ?>&method=<?= e($methodFilter) ?>"
               class="bg-emerald-700 hover:bg-emerald-800 text-white px-3.5 py-2 rounded-lg text-xs font-semibold transition">
                Ekspor CSV
            </a>
        </div>
    </div>

    <!-- Filters Bar -->
    <form method="get" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 items-end">
        <?php if ($isAdmin): ?>
        <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">Dari Tanggal</label>
            <input type="date" name="from" value="<?= e($fromDate) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm">
        </div>
        <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">Sampai Tanggal</label>
            <input type="date" name="to" value="<?= e($toDate) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm">
        </div>
        <?php endif; ?>
        <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">Status</label>
            <select name="status" class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm">
                <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>Semua Status</option>
                <option value="processing" <?= $statusFilter === 'processing' ? 'selected' : '' ?>>Diproses</option>
                <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Selesai</option>
                <option value="void" <?= $statusFilter === 'void' ? 'selected' : '' ?>>Dibatalkan</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">Metode</label>
            <select name="method" class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm">
                <option value="all" <?= $methodFilter === 'all' ? 'selected' : '' ?>>Semua Metode</option>
                <option value="cash" <?= $methodFilter === 'cash' ? 'selected' : '' ?>>Tunai</option>
                <option value="qris" <?= $methodFilter === 'qris' ? 'selected' : '' ?>>QRIS</option>
            </select>
        </div>
        <div>
            <button type="submit" class="w-full bg-[#1B4D3E] hover:bg-[#153d31] text-white text-sm font-semibold py-2 rounded-lg transition">
                Filter
            </button>
        </div>
    </form>

    <!-- Summary KPI Bar -->
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
        <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
            <span class="text-xs text-gray-500 font-medium">Total Pesanan (Non-Void)</span>
            <div class="text-xl font-bold text-[#2C3E50] mt-1"><?= (int)($summary['total_count'] ?? 0) ?></div>
        </div>
        <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
            <span class="text-xs text-gray-500 font-medium">Total Omset (Non-Void)</span>
            <div class="text-xl font-bold text-[#1B4D3E] mt-1"><?= e(formatRupiah((int)($summary['total_revenue'] ?? 0))) ?></div>
        </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto">
        <table class="w-full text-left border-collapse text-sm">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200 text-[#2C3E50] font-semibold">
                    <th class="py-3 px-4">No. Pesanan</th>
                    <th class="py-3 px-4">Waktu</th>
                    <th class="py-3 px-4">Meja</th>
                    <th class="py-3 px-4">Metode</th>
                    <th class="py-3 px-4 text-right">Total</th>
                    <?php if ($isAdmin): ?>
                        <th class="py-3 px-4 text-right">HPP</th>
                        <th class="py-3 px-4 text-right">Laba Kotor</th>
                        <th class="py-3 px-4 text-right">Laba Bersih</th>
                        <th class="py-3 px-4">Kasir</th>
                    <?php endif; ?>
                    <th class="py-3 px-4 text-center">Status</th>
                    <th class="py-3 px-4 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php if (empty($transactions)): ?>
                    <tr><td colspan="<?= $isAdmin ? '11' : '7' ?>" class="py-8 text-center text-gray-400 text-sm">Tidak ada transaksi ditemukan.</td></tr>
                <?php else: ?>
                    <?php foreach ($transactions as $tx): 
                        $tid = (int)$tx['transaction_id'];
                        $isVoid = $tx['status'] === 'void';
                        $orders = $ordersByTx[$tid] ?? [];
                    ?>
                    <tr class="hover:bg-gray-50 <?= $isVoid ? 'opacity-60 bg-gray-50 line-through text-gray-500' : '' ?>">
                        <td class="py-3 px-4 font-bold text-[#1B4D3E]">#<?= (int)$tx['order_no'] ?></td>
                        <td class="py-3 px-4 whitespace-nowrap"><?= e(date('d/m/Y H:i', strtotime($tx['date']))) ?></td>
                        <td class="py-3 px-4"><?= e($tx['table_no']) ?></td>
                        <td class="py-3 px-4">
                            <span class="px-2 py-0.5 rounded text-xs font-semibold <?= $tx['method'] === 'cash' ? 'bg-emerald-50 text-emerald-800' : 'bg-purple-50 text-purple-800' ?>">
                                <?= strtoupper(e($tx['method'])) ?>
                            </span>
                        </td>
                        <td class="py-3 px-4 text-right font-bold"><?= e(formatRupiah((int)$tx['total'])) ?></td>
                        <?php if ($isAdmin): ?>
                            <td class="py-3 px-4 text-right text-gray-600"><?= e(formatRupiah((int)$tx['total_cost'])) ?></td>
                            <td class="py-3 px-4 text-right text-gray-700"><?= e(formatRupiah((int)$tx['gross_profit'])) ?></td>
                            <td class="py-3 px-4 text-right font-semibold text-emerald-700"><?= e(formatRupiah((int)$tx['alloc_profit'])) ?></td>
                            <td class="py-3 px-4 text-gray-600"><?= e($tx['cashier_name'] ?? '-') ?></td>
                        <?php endif; ?>
                        <td class="py-3 px-4 text-center">
                            <?php if ($tx['status'] === 'completed'): ?>
                                <span class="bg-blue-100 text-blue-800 text-xs font-semibold px-2.5 py-1 rounded-full">Selesai</span>
                            <?php elseif ($tx['status'] === 'processing'): ?>
                                <span class="bg-amber-100 text-amber-800 text-xs font-semibold px-2.5 py-1 rounded-full">Diproses</span>
                            <?php else: ?>
                                <span class="bg-red-100 text-red-700 text-xs font-semibold px-2.5 py-1 rounded-full no-underline">Dibatalkan</span>
                            <?php endif; ?>
                        </td>
                        <td class="py-3 px-4 text-right whitespace-nowrap space-x-1">
                            <a href="receipt.php?id=<?= $tid ?>&reprint=1" target="_blank"
                               class="px-2.5 py-1 rounded bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold">
                                Cetak Ulang
                            </a>
                            <?php if ($isAdmin && !$isVoid): ?>
                                <button type="button" class="btn-void-order px-2.5 py-1 rounded bg-red-50 hover:bg-red-100 text-red-700 text-xs font-semibold"
                                    data-transaction-id="<?= $tid ?>" data-order-no="<?= (int)$tx['order_no'] ?>">
                                    Batalkan
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if (!empty($orders)): ?>
                        <tr class="bg-gray-50/50 text-xs">
                            <td colspan="<?= $isAdmin ? '11' : '7' ?>" class="py-2 px-6">
                                <span class="font-semibold text-gray-700">Detail Item:</span>
                                <ul class="list-disc list-inside mt-1 space-y-0.5 text-gray-600">
                                    <?php foreach ($orders as $ord): 
                                        $mods = $modsByOrder[(int)$ord['order_id']] ?? [];
                                    ?>
                                        <li>
                                            <?= (int)$ord['quantity'] ?>x <?= e($ord['menu_name']) ?> 
                                            <?php if (!empty($mods)): ?>
                                                <span class="text-gray-500">[<?= e(implode(', ', $mods)) ?>]</span>
                                            <?php endif; ?>
                                            (<?= e(formatRupiah((int)$ord['subtotal'])) ?>)
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                                <?php if ($isVoid && !empty($tx['void_reason'])): ?>
                                    <div class="mt-2 text-red-600 font-medium">
                                        Alasan Batal: <?= e($tx['void_reason']) ?> (Oleh: <?= e($tx['voided_by_name'] ?? 'Admin') ?> pada <?= e($tx['voided_at']) ?>)
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Void Modal -->
<div id="void-modal" class="fixed inset-0 z-50 hidden bg-black/50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden">
        <div class="bg-red-700 text-white px-5 py-3.5 flex justify-between items-center">
            <h3 class="font-bold text-base">Batalkan Pesanan #<span id="void-order-no-text"></span></h3>
            <button type="button" id="void-modal-close" class="text-white/80 hover:text-white text-2xl leading-none">&times;</button>
        </div>
        <form id="void-form" class="p-5 space-y-4">
            <input type="hidden" id="void-tx-id" value="">
            <div class="bg-amber-50 border border-amber-200 text-amber-900 text-xs p-3 rounded-lg leading-relaxed">
                <strong>Kebijakan Pembatalan:</strong> Pesanan yang dibatalkan akan dikecualikan dari omset, laba, dan laporan. Nomor pesanan tidak akan digunakan ulang. Tindakan ini tidak dapat dibatalkan.
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">Alasan Pembatalan (Wajib, min. 3 karakter) <span class="text-red-600">*</span></label>
                <textarea id="void-reason-input" required minlength="3" maxlength="255" rows="3" placeholder="Masukkan alasan pembatalan..."
                    class="w-full border border-gray-300 rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-red-700 focus:outline-none"></textarea>
                <div class="text-right text-[11px] text-gray-400 mt-0.5"><span id="char-count">0</span>/255</div>
            </div>
            <div class="flex justify-end gap-2 pt-2 border-t">
                <button type="button" id="void-modal-cancel" class="px-4 py-2 rounded-lg border text-sm font-medium hover:bg-gray-50">Tutup</button>
                <button type="submit" id="void-submit-btn" disabled class="px-4 py-2 rounded-lg bg-red-600 hover:bg-red-700 disabled:bg-gray-300 text-white text-sm font-semibold">
                    Ya, Batalkan Pesanan
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const voidModal = document.getElementById('void-modal');
    const voidForm = document.getElementById('void-form');
    const voidTxId = document.getElementById('void-tx-id');
    const voidOrderNoText = document.getElementById('void-order-no-text');
    const reasonInput = document.getElementById('void-reason-input');
    const submitBtn = document.getElementById('void-submit-btn');
    const charCount = document.getElementById('char-count');

    document.querySelectorAll('.btn-void-order').forEach(btn => {
        btn.addEventListener('click', () => {
            voidTxId.value = btn.dataset.transactionId;
            voidOrderNoText.textContent = btn.dataset.orderNo;
            reasonInput.value = '';
            charCount.textContent = '0';
            submitBtn.disabled = true;
            voidModal.classList.remove('hidden');
        });
    });

    const closeVoidModal = () => voidModal.classList.add('hidden');
    document.getElementById('void-modal-close').addEventListener('click', closeVoidModal);
    document.getElementById('void-modal-cancel').addEventListener('click', closeVoidModal);

    reasonInput.addEventListener('input', () => {
        const len = reasonInput.value.trim().length;
        charCount.textContent = len;
        submitBtn.disabled = len < 3;
    });

    voidForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const reason = reasonInput.value.trim();
        if (reason.length < 3) return;

        try {
            const res = await apiFetch('api_void_order.php', {
                method: 'POST',
                body: JSON.stringify({
                    transaction_id: Number(voidTxId.value),
                    reason: reason
                })
            });
            showToast(res.message || 'Pesanan dibatalkan.', 'success');
            setTimeout(() => location.reload(), 300);
        } catch (err) {
            showToast(err.message || 'Gagal membatalkan pesanan.', 'error');
        }
    });
});
</script>

<?php include __DIR__ . '/includes/layout_footer.php'; ?>