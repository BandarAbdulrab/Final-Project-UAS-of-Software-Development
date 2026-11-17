<?php
/**
 * Laporan Keuangan & Ringkasan Penjualan (Admin Only) - SRS-F10
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/includes/report_queries.php';
requireRole(['admin']);

$pageTitle = 'Laporan Keuangan - Kedai Paradise';

$fromDate = $_GET['from'] ?? date('Y-m-01');
$toDate   = $_GET['to'] ?? date('Y-m-d');
$sort     = $_GET['sort'] ?? 'order_date';
$dir      = strtolower($_GET['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

$pdo = db();

$kpi        = getSummaryReport($pdo, $fromDate, $toDate);
$voidInfo   = getVoidSummary($pdo, $fromDate, $toDate);
$poolData   = getPoolBreakdown($pdo, $fromDate, $toDate, $kpi);
$methodData = getPaymentMethodBreakdown($pdo, $fromDate, $toDate);
$dailyRows  = getDailyBreakdown($pdo, $fromDate, $toDate, $sort, $dir);

function sortLinkReport(string $col, string $label, string $currentSort, string $currentDir, string $from, string $to, string $align = 'left'): string {
    $nextDir = ($currentSort === $col && $currentDir === 'asc') ? 'desc' : 'asc';
    $arrow = '';
    if ($currentSort === $col) {
        $arrow = $currentDir === 'asc' ? ' ▲' : ' ▼';
    }
    $url = 'reports.php?' . http_build_query(['from' => $from, 'to' => $to, 'sort' => $col, 'dir' => $nextDir]);
    $alignClass = $align === 'right' ? 'text-right' : ($align === 'center' ? 'text-center' : 'text-left');
    return '<th class="p-3 ' . $alignClass . '"><a href="' . e($url) . '" class="hover:text-[#1B4D3E] font-semibold">' . e($label) . $arrow . '</a></th>';
}

include __DIR__ . '/includes/layout_header.php';
?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-2xl font-bold text-[#1B4D3E]">Laporan Keuangan & Penjualan</h1>
            <p class="text-sm text-gray-600">Audit omset, laba bersih, dan keseimbangan pool dana.</p>
        </div>
        <form method="GET" class="flex flex-wrap items-center gap-2">
            <input type="hidden" name="sort" value="<?= e($sort) ?>">
            <input type="hidden" name="dir" value="<?= e($dir) ?>">
            <input type="date" name="from" value="<?= e($fromDate) ?>" class="border border-gray-300 rounded px-3 py-1.5 text-sm">
            <span class="text-gray-500">s/d</span>
            <input type="date" name="to" value="<?= e($toDate) ?>" class="border border-gray-300 rounded px-3 py-1.5 text-sm">
            <button type="submit" class="bg-[#1B4D3E] text-white px-4 py-1.5 rounded text-sm hover:bg-[#153d31]">Filter</button>
        </form>
    </div>

    <!-- Kartu KPI Eksekutif -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
            <p class="text-xs text-gray-500 font-medium uppercase">Total Omset (Kotor)</p>
            <p class="text-lg font-bold text-[#1B4D3E] mt-1"><?= formatRupiah((int)$kpi['total_revenue']) ?></p>
            <p class="text-xs text-gray-500 mt-1"><?= (int)$kpi['total_orders'] ?> pesanan selesai/diproses</p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
            <p class="text-xs text-gray-500 font-medium uppercase">Laba Kotor</p>
            <p class="text-lg font-bold text-blue-600 mt-1"><?= formatRupiah((int)$kpi['total_gross_profit']) ?></p>
            <p class="text-xs text-gray-500 mt-1">HPP: <?= formatRupiah((int)$kpi['total_cost']) ?></p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
            <p class="text-xs text-gray-500 font-medium uppercase">Laba Bersih (Alokasi Pool)</p>
            <p class="text-lg font-bold text-green-600 mt-1"><?= formatRupiah((int)$kpi['total_net_profit']) ?></p>
            <p class="text-xs text-gray-500 mt-1">Rata-rata durasi: <?= round((float)($kpi['avg_duration'] ?? 0)) ?> dtk</p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
            <p class="text-xs text-gray-500 font-medium uppercase">Saldo Kas Bersih</p>
            <p class="text-lg font-bold text-gray-900 mt-1"><?= formatRupiah((int)$poolData['net_cash_balance']) ?></p>
            <p class="text-xs text-gray-500 mt-1">Omset + Pemasukan - Pengeluaran</p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
            <p class="text-xs text-gray-500 font-medium uppercase">Pemasukan Manual</p>
            <p class="text-lg font-bold text-indigo-600 mt-1"><?= formatRupiah((int)$poolData['total_manual_income']) ?></p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
            <p class="text-xs text-gray-500 font-medium uppercase">Total Pengeluaran</p>
            <p class="text-lg font-bold text-red-600 mt-1"><?= formatRupiah((int)$poolData['total_expenses']) ?></p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200 sm:col-span-2">
            <p class="text-xs text-gray-500 font-medium uppercase">Info Pesanan Dibatalkan (Void - Dikecualikan dari Total)</p>
            <p class="text-lg font-bold text-amber-700 mt-1">
                <?= (int)$voidInfo['void_count'] ?> Pesanan (<?= formatRupiah((int)$voidInfo['void_value']) ?>)
            </p>
        </div>
    </div>

    <!-- Tabel Keseimbangan 3 Pos Dana (3-Pool Balance) & Metode Pembayaran -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <div class="p-4 border-b border-gray-200 flex justify-between items-center">
                <h2 class="font-semibold text-gray-800">Keseimbangan Saldo per Pos Dana (3-Pool)</h2>
                <?php if ($poolData['reconciled']): ?>
                    <span class="bg-green-100 text-green-800 text-xs font-semibold px-2.5 py-1 rounded">Saldo sesuai</span>
                <?php else: ?>
                    <span class="bg-red-100 text-red-800 text-xs font-semibold px-2.5 py-1 rounded">
                        Selisih: <?= formatRupiah((int)$poolData['difference']) ?>
                    </span>
                <?php endif; ?>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-200 text-gray-600">
                            <th class="p-3">Pos Dana</th>
                            <th class="p-3 text-right">Alokasi Penjualan</th>
                            <th class="p-3 text-right">Pemasukan Manual</th>
                            <th class="p-3 text-right">Pengeluaran</th>
                            <th class="p-3 text-right">Saldo Bersih Pool</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($poolData['pools'] as $p): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="p-3 font-medium text-gray-800"><?= e($p['label']) ?></td>
                                <td class="p-3 text-right"><?= formatRupiah($p['allocated']) ?></td>
                                <td class="p-3 text-right text-green-600">+<?= formatRupiah($p['manual_income']) ?></td>
                                <td class="p-3 text-right text-red-600">-<?= formatRupiah($p['expenses']) ?></td>
                                <td class="p-3 text-right font-bold text-gray-900"><?= formatRupiah($p['net_balance']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="bg-gray-50 border-t-2 border-gray-200 font-bold">
                            <td class="p-3">TOTAL KESELURUHAN POOL</td>
                            <td class="p-3 text-right"><?= formatRupiah((int)$kpi['total_revenue']) ?></td>
                            <td class="p-3 text-right text-green-700">+<?= formatRupiah($poolData['total_manual_income']) ?></td>
                            <td class="p-3 text-right text-red-700">-<?= formatRupiah($poolData['total_expenses']) ?></td>
                            <td class="p-3 text-right text-[#1B4D3E]"><?= formatRupiah($poolData['sum_pool_net']) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Rincian Metode Pembayaran -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <div class="p-4 border-b border-gray-200">
                <h2 class="font-semibold text-gray-800">Omset per Metode Pembayaran</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-200 text-gray-600">
                            <th class="p-3">Metode</th>
                            <th class="p-3 text-center">Pesanan</th>
                            <th class="p-3 text-right">Total</th>
                            <th class="p-3 text-right">%</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($methodData as $m): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="p-3 font-medium"><?= e($m['label']) ?></td>
                                <td class="p-3 text-center"><?= $m['orders_count'] ?></td>
                                <td class="p-3 text-right font-semibold"><?= formatRupiah($m['amount']) ?></td>
                                <td class="p-3 text-right text-gray-600"><?= number_format($m['percentage'], 1, ',', '.') ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Tabel Rincian Harian -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="p-4 border-b border-gray-200 flex flex-wrap justify-between items-center gap-2">
            <h2 class="font-semibold text-gray-800">Rincian Penjualan Harian</h2>
            <div class="flex items-center gap-2">
                <a href="export_csv.php?<?= e(http_build_query(['report' => 'summary', 'from' => $fromDate, 'to' => $toDate, 'sort' => $sort, 'dir' => $dir])) ?>" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-1.5 rounded text-xs font-medium">Ekspor CSV (Koma)</a>
                <a href="export_csv.php?<?= e(http_build_query(['report' => 'summary', 'from' => $fromDate, 'to' => $toDate, 'sort' => $sort, 'dir' => $dir, 'delimiter' => 'semicolon'])) ?>" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-1.5 rounded text-xs font-medium">Ekspor CSV (Titik Koma)</a>
                <button type="button" onclick="window.print()" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-1.5 rounded text-xs font-medium">Cetak/PDF</button>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200 text-gray-600">
                        <?= sortLinkReport('order_date', 'Tanggal', $sort, $dir, $fromDate, $toDate, 'left') ?>
                        <?= sortLinkReport('orders_count', 'Jml Pesanan', $sort, $dir, $fromDate, $toDate, 'center') ?>
                        <?= sortLinkReport('revenue', 'Omset (Kotor)', $sort, $dir, $fromDate, $toDate, 'right') ?>
                        <?= sortLinkReport('cost', 'Total HPP', $sort, $dir, $fromDate, $toDate, 'right') ?>
                        <?= sortLinkReport('gross_profit', 'Laba Kotor', $sort, $dir, $fromDate, $toDate, 'right') ?>
                        <?= sortLinkReport('net_profit', 'Laba Bersih', $sort, $dir, $fromDate, $toDate, 'right') ?>
                        <?= sortLinkReport('avg_duration', 'Rata-rata Durasi', $sort, $dir, $fromDate, $toDate, 'center') ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($dailyRows)): ?>
                        <tr><td colspan="7" class="p-4 text-center text-gray-500">Tidak ada data penjualan pada periode ini.</td></tr>
                    <?php else: foreach ($dailyRows as $row): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="p-3 font-medium"><?= e($row['order_date']) ?></td>
                            <td class="p-3 text-center"><?= (int)$row['orders_count'] ?></td>
                            <td class="p-3 text-right"><?= formatRupiah((int)$row['revenue']) ?></td>
                            <td class="p-3 text-right"><?= formatRupiah((int)$row['cost']) ?></td>
                            <td class="p-3 text-right"><?= formatRupiah((int)$row['gross_profit']) ?></td>
                            <td class="p-3 text-right"><?= formatRupiah((int)$row['net_profit']) ?></td>
                            <td class="p-3 text-center"><?= round((float)($row['avg_duration'] ?? 0)) ?> detik</td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/layout_footer.php'; ?>