<?php
/**
 * Cashier & Admin Transaction History Ledger (SRS-F09, BR-12, BR-23, BR-24, BR-25) - Figma Slides 2, 5, 6
 * Kedai Paradise POS
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/auth_check.php';

$isAdmin = ($_SESSION['role'] ?? '') === 'admin';
$pdo = db();

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

$sortMap = [
    'order_no'         => 't.order_no',
    'date'             => 't.date',
    'table_no'         => 't.table_no',
    'method'           => 't.method',
    'total'            => 't.total',
    'status'           => 't.status',
    'duration_seconds' => 't.duration_seconds',
];
if ($isAdmin) {
    $sortMap['total_cost']   = 't.total_cost';
    $sortMap['gross_profit'] = 't.gross_profit';
    $sortMap['alloc_profit'] = 't.alloc_profit';
    $sortMap['cashier']      = 'u.name';
}

$sort = trim((string)($_GET['sort'] ?? 'date'));
if (!array_key_exists($sort, $sortMap)) {
    $sort = 'date';
}
$orderBySql = $sortMap[$sort];
$dir = strtolower(trim((string)($_GET['dir'] ?? 'desc'))) === 'asc' ? 'asc' : 'desc';
$dirSql = strtoupper($dir);

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

$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM transactions t WHERE $whereSql");
$stmtCount->execute($params);
$totalRows = (int)$stmtCount->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $limit));

$stmtSum = $pdo->prepare(
    "SELECT
        COALESCE(SUM(CASE WHEN t.status <> 'void' THEN 1 ELSE 0 END), 0) AS valid_count,
        COALESCE(SUM(CASE WHEN t.status = 'void' THEN 1 ELSE 0 END), 0) AS void_count,
        COALESCE(SUM(CASE WHEN t.status <> 'void' THEN t.total ELSE 0 END), 0) AS total_revenue,
        COALESCE(SUM(CASE WHEN t.status <> 'void' THEN t.total_cost ELSE 0 END), 0) AS total_cost,
        COALESCE(SUM(CASE WHEN t.status <> 'void' THEN t.gross_profit ELSE 0 END), 0) AS total_gross_profit,
        COALESCE(SUM(CASE WHEN t.status <> 'void' THEN t.alloc_profit ELSE 0 END), 0) AS total_net_profit
     FROM transactions t
     WHERE $whereSql"
);
$stmtSum->execute($params);
$summary = $stmtSum->fetch();

$sql = "SELECT t.*, u.name AS cashier_name, ub.username AS voided_by_user, ub.name AS voided_by_name
        FROM transactions t
        LEFT JOIN users u ON u.user_id = t.user_id
        LEFT JOIN users ub ON ub.user_id = t.voided_by
        WHERE $whereSql
        ORDER BY $orderBySql $dirSql, t.transaction_id DESC
        LIMIT :limit OFFSET :offset";

$stmtTx = $pdo->prepare($sql);
foreach ($params as $k => $v) {
    $stmtTx->bindValue($k, $v);
}
$stmtTx->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmtTx->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmtTx->execute();
$transactions = $stmtTx->fetchAll();

$txIds = array_column($transactions, 'transaction_id');
$ordersByTx = [];
$modsByOrder = [];
$orderIds = [];

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

$baseQuery = [
    'from'   => $fromDate,
    'to'     => $toDate,
    'status' => $statusFilter === 'all' ? '' : $statusFilter,
    'method' => $methodFilter === 'all' ? '' : $methodFilter,
];

function sortThHistory(string $col, string $label, string $currentSort, string $currentDir, array $baseParams, string $align = 'left'): string {
    $nextDir = ($currentSort === $col && $currentDir === 'asc') ? 'desc' : 'asc';
    $indicator = $currentSort === $col ? ($currentDir === 'asc' ? ' &#9650;' : ' &#9660;') : ' &#8693;';
    $q = array_merge($baseParams, ['sort' => $col, 'dir' => $nextDir]);
    $alignCls = $align === 'right' ? 'text-right' : ($align === 'center' ? 'text-center' : 'text-left');
    return '<th class="py-3.5 px-2.5 ' . $alignCls . '"><a href="history.php?' . e(http_build_query($q)) . '" class="hover:text-[#1B4D3E] whitespace-nowrap">' . e($label) . '<span class="text-[10px] text-gray-400">' . $indicator . '</span></a></th>';
}

$csvQueryBase = http_build_query(array_merge($baseQuery, ['report' => 'history', 'sort' => $sort, 'dir' => $dir]));
$colCount = $isAdmin ? 12 : 8;

$pageTitle = 'Riwayat Transaksi Penjualan - Kedai Paradise';
$printPeriodLabel = $isAdmin
    ? (date('d/m/Y', strtotime($fromDate)) . ' s/d ' . date('d/m/Y', strtotime($toDate)))
    : (date('d/m/Y') . ' (Hari Ini)');
include __DIR__ . '/includes/layout_header.php';
?>

<div class="space-y-5">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <span class="text-xs font-bold uppercase tracking-wider text-[#1B4D3E]">Laporan Penjualan</span>
            <h1 class="text-2xl font-extrabold text-[#1E293B] uppercase">RIWAYAT TRANSAKSI PENJUALAN</h1>
            <?php if (!$isAdmin): ?>
                <p class="text-xs text-gray-500">Daftar penjualan hari ini (<?= date('d/m/Y') ?>)</p>
            <?php endif; ?>
        </div>
        <div class="flex flex-wrap items-center gap-2 print:hidden">
            <a href="export_csv.php?<?= e($csvQueryBase) ?>&mode=summary"
               class="bg-white hover:bg-gray-50 text-gray-700 border border-gray-300 px-3.5 py-2 rounded-xl text-xs font-bold transition shadow-sm">
                &darr; Ekspor CSV (Ringkasan)
            </a>
            <a href="export_csv.php?<?= e($csvQueryBase) ?>&mode=lines"
               class="bg-white hover:bg-gray-50 text-gray-700 border border-gray-300 px-3.5 py-2 rounded-xl text-xs font-bold transition shadow-sm">
                &darr; CSV (Rincian Item)
            </a>
            <button type="button" onclick="window.print()"
               class="bg-white hover:bg-gray-50 text-gray-700 border border-gray-300 px-3.5 py-2 rounded-xl text-xs font-bold transition shadow-sm">
                &#128424; Cetak / PDF
            </button>
        </div>
    </div>

    <!-- Filters Bar (Figma Slide 5) -->
    <form method="get" class="bg-white rounded-2xl shadow-sm border border-[#E6E4DD] p-4 flex flex-wrap items-end gap-3 print:hidden">
        <input type="hidden" name="sort" value="<?= e($sort) ?>">
        <input type="hidden" name="dir" value="<?= e($dir) ?>">

        <?php if ($isAdmin): ?>
        <div>
            <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 mb-1">Periode Tanggal</label>
            <div class="flex items-center gap-2">
                <input type="date" name="from" value="<?= e($fromDate) ?>" class="bg-[#F8F7F4] border border-gray-300 rounded-xl px-3 py-2 text-xs font-semibold">
                <span class="text-xs text-gray-400">s/d</span>
                <input type="date" name="to" value="<?= e($toDate) ?>" class="bg-[#F8F7F4] border border-gray-300 rounded-xl px-3 py-2 text-xs font-semibold">
            </div>
        </div>
        <?php else: ?>
        <div>
            <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 mb-1">Periode Shift</label>
            <div class="bg-[#F8F7F4] border border-gray-200 rounded-xl px-3.5 py-2 text-xs font-bold text-gray-600">
                <?= date('d/m/Y') ?> s/d <?= date('d/m/Y') ?>
            </div>
        </div>
        <?php endif; ?>

        <div>
            <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 mb-1">Metode Bayar</label>
            <select name="method" class="bg-[#F8F7F4] border border-gray-300 rounded-xl px-3 py-2 text-xs font-semibold">
                <option value="all" <?= $methodFilter === 'all' ? 'selected' : '' ?>>Semua</option>
                <option value="cash" <?= $methodFilter === 'cash' ? 'selected' : '' ?>>Tunai</option>
                <option value="qris" <?= $methodFilter === 'qris' ? 'selected' : '' ?>>QRIS</option>
            </select>
        </div>

        <div>
            <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 mb-1">Status Transaksi</label>
            <select name="status" class="bg-[#F8F7F4] border border-gray-300 rounded-xl px-3 py-2 text-xs font-semibold">
                <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>Semua</option>
                <option value="processing" <?= $statusFilter === 'processing' ? 'selected' : '' ?>>Diproses</option>
                <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Selesai</option>
                <option value="void" <?= $statusFilter === 'void' ? 'selected' : '' ?>>Dibatalkan</option>
            </select>
        </div>

        <button type="submit" class="bg-[#9E3B1B] hover:bg-[#822F14] text-white text-xs font-extrabold px-5 py-2.5 rounded-xl shadow-sm transition">
            &#9906; Filter
        </button>
    </form>

    <!-- Main Ledger Table -->
    <div class="bg-white rounded-2xl shadow-sm border border-[#E6E4DD] overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="bg-[#F8F7F4] border-b border-[#E6E4DD] text-[11px] font-bold uppercase tracking-wider text-gray-500">
                        <?= sortThHistory('order_no', 'No.', $sort, $dir, $baseQuery, 'left') ?>
                        <?= sortThHistory('date', 'Tanggal/Waktu', $sort, $dir, $baseQuery, 'left') ?>
                        <?= sortThHistory('table_no', 'Meja', $sort, $dir, $baseQuery, 'center') ?>
                        <?= sortThHistory('method', 'Metode', $sort, $dir, $baseQuery, 'left') ?>
                        <?= sortThHistory('total', 'Total', $sort, $dir, $baseQuery, 'right') ?>
                        <?= sortThHistory('status', 'Status', $sort, $dir, $baseQuery, 'center') ?>
                        <?= sortThHistory('duration_seconds', 'Durasi', $sort, $dir, $baseQuery, 'center') ?>
                        <?php if ($isAdmin): ?>
                            <?= sortThHistory('total_cost', 'HPP', $sort, $dir, $baseQuery, 'right') ?>
                            <?= sortThHistory('gross_profit', 'Laba Kotor', $sort, $dir, $baseQuery, 'right') ?>
                            <?= sortThHistory('alloc_profit', 'Laba Bersih', $sort, $dir, $baseQuery, 'right') ?>
                            <?= sortThHistory('cashier', 'Kasir', $sort, $dir, $baseQuery, 'center') ?>
                        <?php endif; ?>
                        <th class="py-3.5 px-2.5 text-right print:hidden">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($transactions)): ?>
                        <tr><td colspan="<?= $colCount ?>" class="py-10 text-center text-gray-400 text-sm">Tidak ada transaksi ditemukan pada periode ini.</td></tr>
                    <?php else: ?>
                        <?php foreach ($transactions as $tx):
                            $tid = (int)$tx['transaction_id'];
                            $isVoid = ($tx['status'] === 'void');
                            $orders = $ordersByTx[$tid] ?? [];
                            $durSec = $tx['duration_seconds'] !== null ? (int)$tx['duration_seconds'] : null;
                            $durFormatted = $durSec !== null ? sprintf('%02d:%02d', floor($durSec / 60), $durSec % 60) : '--';
                            $itemSummaryParts = [];
                            foreach ($orders as $ord) {
                                $mods = $modsByOrder[(int)$ord['order_id']] ?? [];
                                $part = (int)$ord['quantity'] . 'x ' . $ord['menu_name'];
                                if (!empty($mods)) {
                                    $part .= ' [' . implode(', ', $mods) . ']';
                                }
                                $itemSummaryParts[] = $part;
                            }
                        ?>
                        <tr class="hover:bg-[#F8F7F4]/50 <?= $isVoid ? 'bg-red-50/20 text-gray-400' : '' ?>">
                            <td class="py-3.5 px-2.5 font-extrabold text-[#1E293B] whitespace-nowrap">
                                #<?= str_pad((string)$tx['order_no'], 3, '0', STR_PAD_LEFT) ?>
                            </td>
                            <td class="py-3.5 px-2.5 whitespace-nowrap">
                                <div class="font-semibold text-gray-800"><?= e(date('d/m H:i', strtotime($tx['date']))) ?> WIB</div>
                                <div class="text-[11px] <?= $isVoid ? 'text-red-600 font-semibold' : 'text-gray-400' ?>">
                                    <?= $isVoid ? 'Dibatalkan' : ($tx['order_date'] === date('Y-m-d') ? 'Hari ini' : e($tx['order_date'])) ?>
                                </div>
                            </td>
                            <td class="py-3.5 px-2.5 text-center whitespace-nowrap">
                                <span class="inline-block bg-[#F8F7F4] border border-gray-200 text-gray-800 font-extrabold text-xs px-2.5 py-1 rounded-lg">
                                    <?= $tx['table_no'] === 'TA' ? 'TA' : str_pad((string)$tx['table_no'], 2, '0', STR_PAD_LEFT) ?>
                                </span>
                            </td>
                            <td class="py-3.5 px-2.5 whitespace-nowrap">
                                <span class="px-2.5 py-1 rounded-lg text-xs font-bold <?= $tx['method'] === 'cash' ? 'bg-emerald-50 text-[#1B4D3E]' : 'bg-orange-50 text-[#9E3B1B]' ?>">
                                    <?= $tx['method'] === 'cash' ? 'Tunai' : 'QRIS' ?>
                                </span>
                            </td>
                            <td class="py-3.5 px-2.5 text-right whitespace-nowrap font-extrabold text-sm <?= $isVoid ? 'line-through text-gray-400' : 'text-[#9E3B1B]' ?>">
                                <?= e(formatRupiah((int)$tx['total'])) ?>
                            </td>
                            <td class="py-3.5 px-2.5 text-center whitespace-nowrap">
                                <?php if ($tx['status'] === 'completed'): ?>
                                    <span class="bg-emerald-100 text-[#1B4D3E] text-xs font-bold px-2.5 py-1 rounded-full">&bull; Selesai</span>
                                <?php elseif ($tx['status'] === 'processing'): ?>
                                    <span class="bg-slate-100 text-slate-800 text-xs font-bold px-2.5 py-1 rounded-full">&bull; Diproses</span>
                                <?php else: ?>
                                    <span class="bg-red-100 text-red-700 text-xs font-bold px-2.5 py-1 rounded-full">&otimes; Dibatalkan</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3.5 px-2.5 text-center font-mono text-xs text-gray-500 whitespace-nowrap"><?= $durFormatted ?></td>
                            <?php if ($isAdmin): ?>
                                <td class="py-3.5 px-2.5 text-right font-medium text-gray-600 whitespace-nowrap">
                                    <?= $isVoid ? '--' : e(formatRupiah((int)$tx['total_cost'])) ?>
                                </td>
                                <td class="py-3.5 px-2.5 text-right font-bold text-[#1B4D3E] whitespace-nowrap">
                                    <?= $isVoid ? '--' : e(formatRupiah((int)$tx['gross_profit'])) ?>
                                </td>
                                <td class="py-3.5 px-2.5 text-right font-extrabold text-emerald-700 whitespace-nowrap">
                                    <?= $isVoid ? '--' : e(formatRupiah((int)$tx['alloc_profit'])) ?>
                                </td>
                                <td class="py-3.5 px-2.5 text-center whitespace-nowrap">
                                    <span class="bg-[#1B4D3E] text-white text-[11px] font-bold px-2 py-0.5 rounded">
                                        <?= e(strtok((string)($tx['cashier_name'] ?? 'Staff'), ' ')) ?>
                                    </span>
                                </td>
                            <?php endif; ?>
                            <td class="py-3.5 px-2.5 text-right whitespace-nowrap space-x-1 print:hidden">
                                <a href="receipt.php?id=<?= $tid ?>&reprint=1" target="_blank"
                                   class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-xs font-bold shadow-sm">
                                    &#128462; Struk
                                </a>
                                <?php if ($isAdmin && !$isVoid): ?>
                                    <button type="button" class="btn-void-order inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-red-50 hover:bg-red-100 text-red-700 border border-red-200 text-xs font-bold"
                                        data-transaction-id="<?= $tid ?>"
                                        data-order-no="<?= str_pad((string)$tx['order_no'], 3, '0', STR_PAD_LEFT) ?>"
                                        data-table-no="<?= e($tx['table_no']) ?>"
                                        data-method="<?= $tx['method'] === 'cash' ? 'TUNAI' : 'QRIS' ?>"
                                        data-total="<?= e(formatRupiah((int)$tx['total'])) ?>"
                                        data-items="<?= e(implode(', ', $itemSummaryParts)) ?>">
                                        &oslash; Batal
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>

                        <!-- Sub-row: Menu Snapshot or Void Reason Banner -->
                        <?php if ($isVoid && !empty($tx['void_reason'])): ?>
                            <tr class="bg-red-50/70 text-xs">
                                <td colspan="<?= $colCount ?>" class="py-2.5 px-5 text-red-800">
                                    <span class="font-extrabold uppercase">&#9888; Alasan Batal:</span>
                                    <span class="font-semibold"><?= e($tx['void_reason']) ?></span>
                                    <span class="text-gray-500 ml-2">(Dibatalkan oleh: <strong><?= e($tx['voided_by_user'] ?? $tx['voided_by_name'] ?? 'admin') ?></strong> pada <?= e(date('H:i', strtotime($tx['voided_at']))) ?> WIB)</span>
                                </td>
                            </tr>
                        <?php elseif (!empty($orders)): ?>
                            <tr class="bg-[#FAF6F0] text-xs">
                                <td colspan="<?= $colCount ?>" class="py-2 px-5">
                                    <span class="font-extrabold text-[#9E3B1B] uppercase mr-2">&#9432; Rincian Menu:</span>
                                    <?php foreach ($orders as $ord):
                                        $mods = $modsByOrder[(int)$ord['order_id']] ?? [];
                                    ?>
                                        <span class="inline-block bg-white border border-[#E6E4DD] rounded-lg px-2.5 py-0.5 mr-1.5 my-0.5 text-gray-800 font-semibold">
                                            <strong><?= (int)$ord['quantity'] ?>x <?= e($ord['menu_name']) ?></strong>
                                            <?php if (!empty($mods)): ?>
                                                <em class="text-gray-500 font-normal">[<?= e(implode(', ', $mods)) ?>]</em>
                                            <?php endif; ?>
                                        </span>
                                    <?php endforeach; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Bottom Summary Bar (Figma Slide 5) -->
        <div class="bg-[#F4FBF7] border-t border-emerald-200 px-5 py-3.5 flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-2 text-xs">
                <span class="font-bold text-gray-700">Ringkasan Transaksi Sah:</span>
                <span class="bg-emerald-100 text-[#1B4D3E] font-extrabold px-2.5 py-0.5 rounded-full">
                    <?= (int)($summary['valid_count'] ?? 0) ?> Transaksi Aktif
                </span>
                <?php if ((int)($summary['void_count'] ?? 0) > 0): ?>
                    <span class="text-gray-400">(&#9888; <?= (int)$summary['void_count'] ?> void diabaikan)</span>
                <?php endif; ?>
            </div>

            <div class="flex flex-wrap items-center gap-5 text-sm">
                <div>
                    <span class="text-xs text-gray-500 mr-1">Total Omset:</span>
                    <strong class="text-lg font-extrabold text-[#9E3B1B]"><?= formatRupiah((int)($summary['total_revenue'] ?? 0)) ?></strong>
                </div>
                <?php if ($isAdmin): ?>
                    <div>
                        <span class="text-xs text-gray-500 mr-1">Total HPP:</span>
                        <strong class="font-extrabold text-gray-800"><?= formatRupiah((int)($summary['total_cost'] ?? 0)) ?></strong>
                    </div>
                    <div>
                        <span class="text-xs text-gray-500 mr-1">Total Laba Kotor:</span>
                        <strong class="text-base font-extrabold text-[#1B4D3E]"><?= formatRupiah((int)($summary['total_gross_profit'] ?? 0)) ?></strong>
                    </div>
                    <div>
                        <span class="text-xs text-gray-500 mr-1">Total Laba Bersih:</span>
                        <strong class="text-lg font-extrabold text-emerald-700"><?= formatRupiah((int)($summary['total_net_profit'] ?? 0)) ?></strong>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Pagination Footer -->
        <div class="bg-white border-t border-gray-100 px-5 py-3 flex items-center justify-between text-xs text-gray-500">
            <span>Menampilkan <strong><?= count($transactions) ?></strong> dari <strong><?= $totalRows ?></strong> transaksi &bull; 50 baris per halaman</span>
            <?php if ($totalPages > 1): ?>
                <div class="flex items-center gap-1">
                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <a href="history.php?<?= e(http_build_query(array_merge($baseQuery, ['sort' => $sort, 'dir' => $dir, 'page' => $p]))) ?>"
                           class="w-7 h-7 rounded-lg flex items-center justify-center font-bold <?= $p === $page ? 'bg-[#9E3B1B] text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
                            <?= $p ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Void Modal (Figma Slide 6) -->
<div id="void-modal" class="fixed inset-0 z-50 hidden bg-black/55 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden border border-[#E6E4DD]">
        <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center">
            <div class="flex items-center gap-3">
                <span class="w-9 h-9 rounded-full bg-red-100 text-red-700 flex items-center justify-center font-bold">&oslash;</span>
                <div>
                    <h3 class="font-extrabold text-base text-[#1E293B] uppercase">BATALKAN PESANAN #<span id="void-order-no-text"></span></h3>
                    <p class="text-xs text-gray-500">Konfirmasi otorisasi void transaksi oleh Admin</p>
                </div>
            </div>
            <button type="button" id="void-modal-close" class="text-gray-400 hover:text-gray-700 text-2xl leading-none">&times;</button>
        </div>

        <form id="void-form" class="p-6 space-y-4">
            <input type="hidden" id="void-tx-id" value="">

            <!-- Selected Order Summary Box -->
            <div class="bg-[#F8F7F4] border border-[#E6E4DD] rounded-xl p-3.5 flex items-center justify-between">
                <div>
                    <span id="void-meta-badge" class="text-[10px] font-extrabold uppercase bg-gray-200 text-gray-700 px-2 py-0.5 rounded">MEJA</span>
                    <div id="void-items-preview" class="text-xs text-gray-600 font-medium mt-1"></div>
                </div>
                <div id="void-total-preview" class="text-lg font-extrabold text-[#1E293B]">Rp0</div>
            </div>

            <div>
                <div class="flex justify-between items-center mb-1">
                    <label class="block text-xs font-bold text-gray-800">Alasan Pembatalan (Wajib Diisi) <span class="text-red-600">*</span></label>
                    <span class="text-[10px] font-semibold text-gray-400">Terekam di Log Audit</span>
                </div>
                <textarea id="void-reason-input" required minlength="3" maxlength="255" rows="2" placeholder="Contoh: Salah input tingkat pedas; pesanan dibuat ulang"
                    class="w-full bg-[#F8F7F4] border border-gray-300 rounded-xl p-3 text-sm focus:bg-white focus:ring-2 focus:ring-red-700 focus:outline-none"></textarea>
                <div class="flex justify-between text-[11px] text-gray-400 mt-1">
                    <span>Pastikan alasan jelas untuk verifikasi rekap keuangan kasir.</span>
                    <span><span id="char-count">0</span>/255 karakter</span>
                </div>
            </div>

            <!-- Critical Warning Box (5 Bullet Rules from SRS & Figma Slide 6) -->
            <div class="bg-red-50/80 border border-red-200 text-red-900 text-xs p-4 rounded-xl space-y-1.5">
                <div class="font-extrabold uppercase text-red-800 flex items-center gap-1.5">
                    <span>&#9888; Peringatan Kritis</span>
                </div>
                <ul class="list-disc list-inside space-y-1 text-red-800/90">
                    <li>Pesanan akan dibatalkan secara permanen (status: <strong>VOID</strong>).</li>
                    <li>Dikeluarkan dari omset, laba, dan seluruh laporan keuangan.</li>
                    <li>Nomor pesanan tidak akan digunakan ulang.</li>
                    <li>Tindakan ini tidak dapat dibatalkan (audit tersimpan).</li>
                    <li>Pengembalian uang ke pelanggan dilakukan manual di kasir.</li>
                </ul>
            </div>

            <div class="flex justify-between items-center gap-3 pt-2">
                <button type="button" id="void-modal-cancel" class="px-4 py-2.5 rounded-xl border border-gray-300 text-xs font-bold text-gray-700 hover:bg-gray-50">
                    &larr; Kembali
                </button>
                <button type="submit" id="void-submit-btn" disabled class="flex-1 py-3 rounded-xl bg-[#9E3B1B] hover:bg-[#822F14] disabled:bg-gray-300 text-white text-xs font-extrabold uppercase tracking-wider shadow-sm transition">
                    &#10004; YA, BATALKAN PESANAN
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
            document.getElementById('void-meta-badge').textContent = (btn.dataset.tableNo === 'TA' ? 'MEJA TA' : 'MEJA ' + btn.dataset.tableNo) + ' • ' + btn.dataset.method;
            document.getElementById('void-items-preview').textContent = btn.dataset.items || '-';
            document.getElementById('void-total-preview').textContent = btn.dataset.total || 'Rp0';
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
